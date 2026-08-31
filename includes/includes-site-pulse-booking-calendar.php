<?php
/* Battle Plan Web Design — Site Pulse: Booking Calendar & Bookings (AJAX)

/*--------------------------------------------------------------
The staff side of the booking module: the calendar, the bookings list, and the
flow that creates, moves, cancels and closes out a booking.

This is where sp_book_claim() and sp_book_reschedule() are first exercised for
real — deliberately behind a login, before anything is exposed to a customer.
The riskiest code in the module is the concurrency handling, and proving it with
staff bookings costs nothing; discovering it is wrong when two customers hold
the same 9am costs a client relationship.

Permission model, and the two halves that differ:
  READ  — `view_bookings` (or manage/god) sees everything; `view_own_bookings`
          is scoped to the resources the viewer is linked to, so a tech sees
          their own route and nothing else.
  WRITE — always `manage_bookings`. Seeing a calendar never implies editing it.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Gates + Shaping
# Calendar (a date range)
# Bookings list
# One booking
# Slots for the New Booking picker
# Create · Reschedule · Cancel · Close out
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Gates + Shaping
--------------------------------------------------------------*/

/** Read gate. Sends its own error, like site_pulse_admin_check(). */
function sp_book_view_gate(): bool {
	if ( ! site_pulse_module_on( 'booking' ) ) {
		wp_send_json_error( [ 'message' => 'The Booking module is switched off for this site.' ] );
		return false;
	}
	if ( sp_book_can_view() ) return true;
	wp_send_json_error( [ 'message' => 'You do not have permission to view bookings.' ] );
	return false;
}

/** Write gate — always the manage capability, never merely "can see it". */
function sp_book_manage_gate(): bool {
	if ( ! site_pulse_module_on( 'booking' ) ) {
		wp_send_json_error( [ 'message' => 'The Booking module is switched off for this site.' ] );
		return false;
	}
	if ( sp_book_can_manage() ) return true;
	wp_send_json_error( [ 'message' => 'You do not have permission to change bookings.' ] );
	return false;
}

/**
 * The SQL fragment that limits a query to the resources this viewer may see.
 * Returns '' for an unrestricted viewer. A scoped viewer with no linked
 * resources gets a condition that matches nothing — "no resources" must mean
 * "no bookings", never "all bookings".
 */
function sp_book_scope_sql(): string {
	if ( sp_book_can_view_all() ) return '';
	$ids = sp_book_visible_resource_ids();
	if ( ! $ids ) return ' AND 1 = 0 ';
	return ' AND b.resource_id IN ( ' . implode( ',', array_map( 'intval', $ids ) ) . ' ) ';
}

/** Every resource name, id => name, for labelling without a join per row. */
function sp_book_resource_names(): array {
	global $wpdb;
	$out = [];
	foreach ( $wpdb->get_results( "SELECT id, name, color FROM " . sp_book_table( 'resources' ), ARRAY_A ) ?: [] as $r ) {
		$out[ (int) $r['id'] ] = [ 'name' => (string) $r['name'], 'color' => (string) $r['color'] ];
	}
	return $out;
}

/**
 * One booking, shaped for the client. Times are sent BOTH ways on purpose: the
 * UTC value is what any write must send back (the server never trusts a local
 * string), and the preformatted local strings mean the browser never has to
 * reimplement the install's timezone rules to render a label.
 */
function sp_book_shape( array $row, array $resources, array $services ): array {
	$rid = (int) $row['resource_id'];
	$sid = (int) $row['service_id'];
	return [
		'id'          => (int) $row['id'],
		'code'        => (string) $row['code'],
		'service_id'  => $sid,
		'service'     => $services[ $sid ] ?? '',
		'resource_id' => $rid,
		'resource'    => $resources[ $rid ]['name'] ?? '',
		'color'       => $resources[ $rid ]['color'] ?? '',
		'starts_at'   => (string) $row['starts_at'],
		'ends_at'     => (string) $row['ends_at'],
		'date'        => sp_book_to_local( (string) $row['starts_at'], 'Y-m-d' ),
		'time'        => sp_book_to_local( (string) $row['starts_at'], 'g:i A' ),
		'end_time'    => sp_book_to_local( (string) $row['ends_at'], 'g:i A' ),
		'day_label'   => sp_book_to_local( (string) $row['starts_at'], 'D, M j' ),
		'status'      => (string) $row['status'],
		'source'      => (string) $row['source'],
		'customer'    => (string) $row['customer_name'],
		'email'       => (string) $row['customer_email'],
		'phone'       => (string) $row['customer_phone'],
		'address'     => trim( implode( ' ', array_filter( [
			(string) $row['address'], (string) $row['city'],
			trim( (string) $row['state'] . ' ' . (string) $row['zip'] ),
		] ) ) ),
		'party_size'  => (int) $row['party_size'],
		'price_cents' => (int) $row['price_cents'],
		'notes'       => (string) $row['notes'],
		'staff_notes' => (string) $row['staff_notes'],
	];
}

/** id => name for every service, for labelling. */
function sp_book_service_names(): array {
	global $wpdb;
	$out = [];
	foreach ( $wpdb->get_results( "SELECT id, name FROM " . sp_book_table( 'services' ), ARRAY_A ) ?: [] as $s ) {
		$out[ (int) $s['id'] ] = (string) $s['name'];
	}
	return $out;
}


/*--------------------------------------------------------------
# Calendar (a date range)
--------------------------------------------------------------*/

/**
 * Every booking overlapping a LOCAL date range. The client sends local dates
 * (what the user is looking at); the range is converted to UTC bounds here so
 * the browser never has to know the install's timezone.
 */
add_action( 'wp_ajax_site_pulse_booking_calendar', 'sp_book_ajax_calendar' );
function sp_book_ajax_calendar(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_view_gate() ) return;

	global $wpdb;
	$from = sp_book_date_value( sp_book_post( 'from' ) );
	$to   = sp_book_date_value( sp_book_post( 'to' ) );
	if ( $from === '' ) $from = sp_book_to_local( sp_book_now(), 'Y-m-d' );
	if ( $to === '' || $to < $from ) $to = $from;

	// Local midnight → the following local midnight, so a range is inclusive of its last day.
	$tz    = sp_book_tz();
	$start = sp_book_local_ts( $from, '00:00:00', $tz );
	$end   = ( new DateTime( $to . ' 00:00:00', $tz ) )->modify( '+1 day' )->getTimestamp();

	$resource_id = (int) ( $_POST['resource_id'] ?? 0 );
	$where       = $resource_id ? $wpdb->prepare( ' AND b.resource_id = %d ', $resource_id ) : '';

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT b.* FROM " . sp_book_table( 'bookings' ) . " b
		 WHERE b.starts_at < %s AND b.ends_at > %s
		   AND b.status NOT IN ( 'cancelled', 'expired' )"
		 . $where . sp_book_scope_sql() . "
		 ORDER BY b.starts_at ASC",
		gmdate( 'Y-m-d H:i:s', $end ), gmdate( 'Y-m-d H:i:s', $start )
	), ARRAY_A ) ?: [];

	$resources = sp_book_resource_names();
	$services  = sp_book_service_names();

	$out = [];
	foreach ( $rows as $row ) $out[] = sp_book_shape( $row, $resources, $services );

	// The filter list only offers resources this viewer is actually allowed to see.
	$visible = sp_book_visible_resource_ids();
	$list    = [];
	foreach ( $resources as $id => $r ) {
		if ( ! sp_book_can_view_all() && ! in_array( $id, $visible, true ) ) continue;
		$list[] = [ 'id' => $id, 'name' => $r['name'], 'color' => $r['color'] ];
	}

	wp_send_json_success( [
		'bookings'   => $out,
		'resources'  => $list,
		'can_manage' => sp_book_can_manage(),
		'timezone'   => $tz->getName(),
		'today'      => sp_book_to_local( sp_book_now(), 'Y-m-d' ),
	] );
}


/*--------------------------------------------------------------
# Bookings list
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_booking_list', 'sp_book_ajax_list' );
function sp_book_ajax_list(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_view_gate() ) return;

	global $wpdb;
	$status = sanitize_key( $_POST['status'] ?? 'upcoming' );
	$search = sp_book_post( 'search' );

	$where  = '';
	$params = [];

	if ( $status === 'upcoming' ) {
		// "Upcoming" is the working view: what still needs to happen, soonest first.
		$where   .= " AND b.starts_at >= %s AND b.status IN ( 'pending', 'confirmed' ) ";
		$params[] = sp_book_now();
		$order    = 'ASC';
	} elseif ( $status !== '' && array_key_exists( $status, sp_book_statuses() ) ) {
		$where   .= ' AND b.status = %s ';
		$params[] = $status;
		$order    = 'DESC';
	} else {
		$order = 'DESC';
	}

	if ( $search !== '' ) {
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$where   .= ' AND ( b.customer_name LIKE %s OR b.customer_email LIKE %s OR b.customer_phone LIKE %s OR b.code LIKE %s ) ';
		array_push( $params, $like, $like, $like, $like );
	}

	$sql = "SELECT b.* FROM " . sp_book_table( 'bookings' ) . " b WHERE 1 = 1 "
		. $where . sp_book_scope_sql() . " ORDER BY b.starts_at $order LIMIT 300";

	$rows = $params
		? ( $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [] )
		: ( $wpdb->get_results( $sql, ARRAY_A ) ?: [] );

	$resources = sp_book_resource_names();
	$services  = sp_book_service_names();

	$out = [];
	foreach ( $rows as $row ) $out[] = sp_book_shape( $row, $resources, $services );

	wp_send_json_success( [ 'bookings' => $out, 'can_manage' => sp_book_can_manage() ] );
}


/*--------------------------------------------------------------
# One booking
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_booking_get', 'sp_book_ajax_get' );
function sp_book_ajax_get(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_view_gate() ) return;

	$booking = sp_book_get_booking( (int) ( $_POST['id'] ?? 0 ) );
	if ( ! $booking ) wp_send_json_error( [ 'message' => 'That booking no longer exists.' ] );

	// A scoped viewer must not be able to read a booking simply by guessing its id.
	if ( ! sp_book_can_view_all() && ! in_array( (int) $booking['resource_id'], sp_book_visible_resource_ids(), true ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to view that booking.' ] );
	}

	wp_send_json_success( [
		'booking'    => sp_book_shape( $booking, sp_book_resource_names(), sp_book_service_names() ),
		'can_manage' => sp_book_can_manage(),
		'statuses'   => sp_book_statuses(),
	] );
}


/*--------------------------------------------------------------
# Slots for the New Booking picker
--------------------------------------------------------------*/

/**
 * The bookable slots for one service on one local date. Same engine call the
 * public widget will make later — the staff picker is not a separate code path,
 * so anything proven here is proven for customers too.
 */
add_action( 'wp_ajax_site_pulse_booking_slots', 'sp_book_ajax_slots' );
function sp_book_ajax_slots(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_manage_gate() ) return;

	$service_id = (int) ( $_POST['service_id'] ?? 0 );
	$service    = sp_book_get_service( $service_id );
	if ( ! $service || $service['status'] !== 'active' ) {
		wp_send_json_error( [ 'message' => 'Pick an active service.' ] );
	}

	$date = sp_book_date_value( sp_book_post( 'date' ) );
	if ( $date === '' ) wp_send_json_error( [ 'message' => 'Pick a date.' ] );

	// Staff picker: the full working day, not the customer-facing window. This endpoint is
	// manage-gated, so anyone reaching it is entitled to book outside the notice period.
	// With travel buffers on, availability depends on WHERE this booking is going, so the
	// picker sends the address and gets an answer specific to it. Without an address the
	// destination is null and the flat minimum buffer applies — the same answer as before.
	$resource_id = (int) ( $_POST['resource_id'] ?? 0 );
	$dest        = sp_book_travel_enabled( $service ) ? sp_book_dest_from_request() : null;
	$slots       = sp_book_slots_for_date( $service_id, $date, $resource_id, true, $dest );
	$resources   = sp_book_resource_names();

	$out = [];
	foreach ( $slots as $slot ) {
		// A window is sold as a RANGE with the client's own label; an exact service is sold as a
		// start time. The picker renders whichever came back rather than deciding for itself.
		$is_window = isset( $slot['window'] );
		$label     = sp_book_to_local( $slot['starts_at'], 'g:i A' );
		if ( $is_window ) {
			$label = sp_book_to_local( $slot['starts_at'], 'g:i A' ) . ' – ' . sp_book_to_local( $slot['ends_at'], 'g:i A' );
		}

		$out[] = [
			'starts_at'   => $slot['starts_at'],
			'ends_at'     => $slot['ends_at'],
			'time'        => $label,
			'window'      => $is_window ? (string) $slot['window'] : '',
			'is_window'   => $is_window ? 1 : 0,
			'resource_id' => (int) $slot['resource_id'],
			'resource'    => $resources[ (int) $slot['resource_id'] ]['name'] ?? '',
			'seats_left'  => (int) ( $slot['seats_left'] ?? 1 ),
			'session_id'  => (int) ( $slot['session_id'] ?? 0 ),
			// Set only when the service dispatches by proximity: how far out of the way this
			// job is for whoever would take it, and what the client's policy does about it.
			'out_of_area' => (int) ( $slot['out_of_area'] ?? 0 ),
			'surcharge'   => (int) ( $slot['surcharge'] ?? 0 ),
			'travel_cost' => (int) ( $slot['travel_cost'] ?? 0 ),
		];
	}

	wp_send_json_success( [ 'slots' => $out, 'date' => $date ] );
}


/*--------------------------------------------------------------
# Create · Reschedule · Cancel · Close out
--------------------------------------------------------------*/

/**
 * Staff booking on a customer's behalf. Goes through sp_book_claim() like every
 * other booking — there is no privileged "just insert it" path, because that is
 * exactly how a double-booking gets written.
 *
 * Staff bookings are created `confirmed` rather than as a hold: nobody is
 * heading to a checkout, so a hold would only expire underneath the customer.
 */
add_action( 'wp_ajax_site_pulse_booking_create', 'sp_book_ajax_create' );
function sp_book_ajax_create(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_manage_gate() ) return;

	$name = sp_book_post( 'customer_name' );
	if ( $name === '' ) {
		wp_send_json_error( [ 'message' => 'A customer name is required.' ] );
	}

	// Resolved once here (from the cache the picker just warmed), so the claim does not
	// geocode again and the row is stored with its coordinates.
	$dest_pt = sp_book_dest_from_request();

	$result = sp_book_claim( [
		'service_id'     => (int) ( $_POST['service_id'] ?? 0 ),
		'resource_id'    => (int) ( $_POST['resource_id'] ?? 0 ),
		'session_id'     => (int) ( $_POST['session_id'] ?? 0 ),
		'starts_at'      => sp_book_post( 'starts_at' ),
		'ends_at'        => sp_book_post( 'ends_at' ),   // window mode books a range, not a start
		'status'         => 'confirmed',
		'source'         => 'staff',
		'ignore_policy'  => true,   // see sp_book_policy_bounds(): notice/advance are customer rules
		'created_by'     => site_pulse_effective_user_id(),
		'party_size'     => (int) ( $_POST['party_size'] ?? 1 ),
		'customer_name'  => $name,
		'customer_email' => sp_book_post( 'customer_email' ),
		'customer_phone' => sp_book_post( 'customer_phone' ),
		'address'        => sp_book_post( 'address' ),
		'city'           => sp_book_post( 'city' ),
		'state'          => sp_book_post( 'state' ),
		'zip'            => sp_book_post( 'zip' ),
		'notes'          => (string) ( $_POST['notes'] ?? '' ),
		'lat'            => $dest_pt ? $dest_pt[0] : 0,
		'lng'            => $dest_pt ? $dest_pt[1] : 0,
	] );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ] );
	}

	sp_book_notify_created( $result );
	site_pulse_log( 'booking_created', sprintf( 'Booked %s (%s)', $name, (string) $result['code'] ) );
	wp_send_json_success( [
		'message' => 'Booking created.',
		'booking' => sp_book_shape( $result, sp_book_resource_names(), sp_book_service_names() ),
	] );
}

add_action( 'wp_ajax_site_pulse_booking_reschedule', 'sp_book_ajax_reschedule' );
function sp_book_ajax_reschedule(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_manage_gate() ) return;

	$result = sp_book_reschedule(
		(int) ( $_POST['id'] ?? 0 ),
		sp_book_post( 'starts_at' ),
		(int) ( $_POST['resource_id'] ?? 0 )
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ] );
	}

	sp_book_notify_rescheduled( $result );
	site_pulse_log( 'booking_moved', sprintf( 'Rescheduled booking %s', (string) $result['code'] ) );
	wp_send_json_success( [
		'message' => 'Booking moved.',
		'booking' => sp_book_shape( $result, sp_book_resource_names(), sp_book_service_names() ),
	] );
}

add_action( 'wp_ajax_site_pulse_booking_cancel', 'sp_book_ajax_cancel' );
function sp_book_ajax_cancel(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_manage_gate() ) return;

	$id = (int) ( $_POST['id'] ?? 0 );
	$ok = sp_book_cancel( $id, site_pulse_effective_user_id(), sp_book_post( 'reason' ) );
	if ( ! $ok ) {
		wp_send_json_error( [ 'message' => 'That booking could not be cancelled.' ] );
	}

	sp_book_notify_cancelled( $id );
	site_pulse_log( 'booking_cancelled', sprintf( 'Cancelled booking #%d', $id ) );
	wp_send_json_success( [ 'message' => 'Booking cancelled.' ] );
}

/**
 * Close a booking out after the fact: `completed` or `no_show`.
 *
 * Both stay in the ACTIVE set (they still occupy their historical slot, so past
 * availability stays truthful) — which is why this is a plain status write and
 * not a release. `no_show` is also the hook a late-cancel fee will hang off once
 * payments land.
 */
add_action( 'wp_ajax_site_pulse_booking_status', 'sp_book_ajax_status' );
function sp_book_ajax_status(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_manage_gate() ) return;

	global $wpdb;
	$id     = (int) ( $_POST['id'] ?? 0 );
	$status = sanitize_key( $_POST['status'] ?? '' );
	if ( ! $id || ! in_array( $status, [ 'completed', 'no_show', 'confirmed' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Bad request.' ] );
	}

	$booking = sp_book_get_booking( $id );
	if ( ! $booking ) wp_send_json_error( [ 'message' => 'That booking no longer exists.' ] );
	if ( in_array( (string) $booking['status'], [ 'cancelled', 'expired' ], true ) ) {
		wp_send_json_error( [ 'message' => 'That booking was cancelled — reinstate it by creating a new one.' ] );
	}

	$wpdb->update( sp_book_table( 'bookings' ), [
		'status'     => $status,
		'updated_at' => sp_book_now(),
	], [ 'id' => $id ] );

	sp_book_cache_bust();
	wp_send_json_success( [ 'message' => 'Updated.', 'status' => $status ] );
}

/** Free-text staff note on a booking — the "he prefers the side gate" field. */
add_action( 'wp_ajax_site_pulse_booking_note', 'sp_book_ajax_note' );
function sp_book_ajax_note(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_manage_gate() ) return;

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Bad request.' ] );

	$wpdb->update( sp_book_table( 'bookings' ), [
		'staff_notes' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['staff_notes'] ?? '' ) ) ),
		'updated_at'  => sp_book_now(),
	], [ 'id' => $id ] );

	wp_send_json_success( [ 'message' => 'Note saved.' ] );
}
