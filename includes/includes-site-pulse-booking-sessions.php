<?php
/* Battle Plan Web Design — Site Pulse: Booking Session Schedule

/*--------------------------------------------------------------
Session-mode services (classes, workshops, retreat weekends) are booked against
CONCRETE `sessions` rows: a start, an end, a capacity and a seats_taken counter.
The engine has always read and claimed those rows; this file is what CREATES them.

Two sources, one table:

  one-off    staff add a single date + time ("Saturday Oct 3, 10am, 12 seats").
  weekly     a `recurrences` row ("every Tuesday 6pm, 60 min, 15 seats, from
             Sep 1 until Dec 15") that the roll-forward turns into sessions a
             rolling horizon ahead. generated_through records how far it got,
             so each run only fills the new days.

An EVENT can be linked to a session booking (includes-site-pulse-booking-events.php);
the booking owns the dates and the event copies them — every change here fires
`sp_book_sessions_changed` (service id) so it can.

REMOVING a date never deletes anyone's booking: a session with seats taken is
refused (staff cancel those bookings first, which also tells the customers);
an empty one is deleted outright. Ending a weekly schedule deletes its empty
future sessions and leaves any that already have bookings on the calendar.

Per-service display options live in services.meta (JSON), read through
sp_book_service_opts(): the public button text, the "full" text, whether the
booking line is taxed at checkout, and — for an event's hidden service — the
event it belongs to.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Service options (meta)
# Creating sessions
# Weekly roll-forward
# Explicit sync (bookable events)
# AJAX — Settings → Booking → Schedule
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Service options (meta)
--------------------------------------------------------------*/

/** A service's display/commerce options, with defaults. */
function sp_book_service_opts( array $service ): array {
	$meta = json_decode( (string) ( $service['meta'] ?? '' ), true );
	$meta = is_array( $meta ) ? $meta : [];
	return [
		'book_label' => trim( (string) ( $meta['book_label'] ?? '' ) ) ?: 'Book Now',
		'full_label' => trim( (string) ( $meta['full_label'] ?? '' ) ) ?: 'Class Full',
		'taxable'    => ! empty( $meta['taxable'] ),
		'event_id'   => (int) ( $meta['event_id'] ?? 0 ),
		// SERIES: one price buys a seat in every date (a 4-week class). Off = each date is booked on its own.
		'series'     => ! empty( $meta['series'] ),
	];
}

/** Merge keys into a service's meta JSON without disturbing anything else stored there. */
function sp_book_service_set_opts( int $service_id, array $opts ): void {
	global $wpdb;
	$svc  = sp_book_get_service( $service_id );
	if ( ! $svc ) return;
	$meta = json_decode( (string) ( $svc['meta'] ?? '' ), true );
	$meta = is_array( $meta ) ? $meta : [];
	foreach ( $opts as $k => $v ) $meta[ $k ] = $v;
	$wpdb->update( sp_book_table( 'services' ), [ 'meta' => wp_json_encode( $meta ), 'updated_at' => sp_book_now() ], [ 'id' => $service_id ] );
}

/**
 * What a customer pays for one seat, and whether it goes through checkout. A service that doesn't
 * require payment (or costs nothing) is reserved directly; otherwise the seat is sold through the
 * Store's cart — the full price, or the deposit when that's what the service asks for.
 */
function sp_book_seat_price( array $service ): array {
	$mode  = (string) ( $service['requires_payment'] ?? 'none' );
	$cents = $mode === 'deposit' ? (int) $service['deposit_cents'] : (int) $service['price_cents'];
	return [ 'cents' => max( 0, $cents ), 'checkout' => $mode !== 'none' && $cents > 0 ];
}


/*--------------------------------------------------------------
# Creating sessions
--------------------------------------------------------------*/

/**
 * Insert one session. $start_local is 'Y-m-d H:i:s' in the booking timezone (the resource's, if it
 * has one). INSERT IGNORE against UNIQUE (resource_id, service_id, starts_at), so re-running the
 * roll-forward — or two requests racing it — can never double a session.
 * Returns the session id (the existing one when it was already there), or 0.
 */
function sp_book_add_session( int $service_id, string $start_local, int $duration_min, int $capacity, int $resource_id = 0, int $recurrence_id = 0, string $note = '', string $end_local = '' ): int {
	global $wpdb;
	$tz       = '';
	if ( $resource_id && ( $res = sp_book_get_resource( $resource_id ) ) ) $tz = (string) ( $res['timezone'] ?? '' );
	$start    = sp_book_to_utc( $start_local, $tz );
	if ( $start === '' ) return 0;
	$end      = $end_local !== '' ? sp_book_to_utc( $end_local, $tz ) : '';
	if ( $end === '' || $end <= $start ) $end = gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) + max( 5, $duration_min ) * 60 );
	$now      = sp_book_now();

	$wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO " . sp_book_table( 'sessions' ) . "
		 (service_id, resource_id, recurrence_id, starts_at, ends_at, capacity, seats_taken, waitlist_count, status, note, created_at, updated_at)
		 VALUES (%d, %d, %d, %s, %s, %d, 0, 0, 'scheduled', %s, %s, %s)",
		$service_id, $resource_id, $recurrence_id, $start, $end, max( 1, $capacity ), $note, $now, $now
	) );
	if ( $wpdb->insert_id ) return (int) $wpdb->insert_id;

	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . sp_book_table( 'sessions' ) . " WHERE resource_id = %d AND service_id = %d AND starts_at = %s",
		$resource_id, $service_id, $start
	) );
}

/**
 * Remove a date. Empty → deleted. Seats taken → refused, unless $force, which marks it cancelled
 * (off the calendar, nothing bookable) and leaves every booking on it for staff to deal with.
 * Returns true, or a WP_Error explaining why not.
 */
function sp_book_remove_session( int $session_id, bool $force = false ) {
	global $wpdb;
	$s = sp_book_get_session( $session_id );
	if ( ! $s ) return true;
	if ( (int) $s['seats_taken'] > 0 ) {
		if ( ! $force ) {
			return new WP_Error( 'booked', sprintf(
				'This date has %d seat%s booked. Cancel %s bookings first (Booking → Bookings), then remove the date.',
				(int) $s['seats_taken'], (int) $s['seats_taken'] === 1 ? '' : 's', (int) $s['seats_taken'] === 1 ? 'that' : 'those'
			) );
		}
		$wpdb->update( sp_book_table( 'sessions' ), [ 'status' => 'cancelled', 'updated_at' => sp_book_now() ], [ 'id' => $session_id ] );
	} else {
		$wpdb->delete( sp_book_table( 'sessions' ), [ 'id' => $session_id ] );
	}
	sp_book_cache_bust();
	do_action( 'sp_book_sessions_changed', (int) $s['service_id'] );
	return true;
}


/*--------------------------------------------------------------
# Weekly roll-forward
--------------------------------------------------------------*/

/** How many days ahead weekly sessions exist: the service's booking window, kept to 2 weeks – 1 year. */
function sp_book_session_horizon_days( array $service ): int {
	return max( 14, min( 366, (int) ( $service['max_advance_days'] ?? 60 ) ) );
}

/**
 * Fill one weekly schedule forward to its horizon (or its end date), resuming after
 * generated_through. Weekday is 0 = Sunday … 6 = Saturday, matching PHP's date('w').
 */
function sp_book_roll_recurrence( array $rec ): int {
	global $wpdb;
	if ( ( $rec['status'] ?? '' ) !== 'active' ) return 0;
	$service = sp_book_get_service( (int) $rec['service_id'] );
	if ( ! $service || $service['status'] !== 'active' ) return 0;

	$tz      = sp_book_tz();
	$today   = new DateTime( 'today', $tz );
	$horizon = ( clone $today )->modify( '+' . sp_book_session_horizon_days( $service ) . ' days' );

	$from = clone $today;
	if ( ! empty( $rec['start_date'] ) ) { $sd = new DateTime( $rec['start_date'], $tz ); if ( $sd > $from ) $from = $sd; }
	if ( ! empty( $rec['generated_through'] ) ) { $gt = ( new DateTime( $rec['generated_through'], $tz ) )->modify( '+1 day' ); if ( $gt > $from ) $from = $gt; }
	$to = clone $horizon;
	if ( ! empty( $rec['end_date'] ) ) { $ed = new DateTime( $rec['end_date'], $tz ); if ( $ed < $to ) $to = $ed; }
	if ( $from > $to ) return 0;

	$made = 0;
	for ( $d = clone $from; $d <= $to; $d->modify( '+1 day' ) ) {
		if ( (int) $d->format( 'w' ) !== (int) $rec['weekday'] ) continue;
		$id = sp_book_add_session( (int) $rec['service_id'], $d->format( 'Y-m-d' ) . ' ' . $rec['start_time'], (int) $rec['duration_min'], (int) $rec['capacity'], (int) $rec['resource_id'], (int) $rec['id'] );
		if ( $id ) $made++;
	}
	$wpdb->update( sp_book_table( 'recurrences' ), [ 'generated_through' => $to->format( 'Y-m-d' ), 'updated_at' => sp_book_now() ], [ 'id' => (int) $rec['id'] ] );
	if ( $made ) {
		sp_book_cache_bust();
		/** A booking's dates changed — a linked event copies them (includes-site-pulse-booking-events.php). */
		do_action( 'sp_book_sessions_changed', (int) $rec['service_id'] );
	}
	return $made;
}

/** Every active weekly schedule. Once a day, from the booking module's own init tick. */
function sp_book_roll_all_recurrences(): void {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT * FROM " . sp_book_table( 'recurrences' ) . " WHERE status = 'active'", ARRAY_A ) ?: [];
	foreach ( $rows as $rec ) sp_book_roll_recurrence( $rec );
}

add_action( 'init', 'sp_book_maybe_roll_sessions', 21 );
function sp_book_maybe_roll_sessions(): void {
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'booking' ) ) return;
	// Not until the booking tables exist — on the request that first switches the module on, they don't yet.
	if ( get_option( 'site_pulse_booking_db_version' ) !== SITE_PULSE_BOOKING_DB_VERSION ) return;
	$today = ( new DateTime( 'today', sp_book_tz() ) )->format( 'Y-m-d' );
	if ( get_option( 'sp_book_sessions_rolled' ) === $today ) return;
	update_option( 'sp_book_sessions_rolled', $today, false );   // claim first: one run per day even under load
	sp_book_roll_all_recurrences();
	if ( function_exists( 'sp_book_events_roll' ) ) sp_book_events_roll();
}

/** End a weekly schedule: no more dates are made, and its empty future dates are removed. */
function sp_book_end_recurrence( int $rec_id ): int {
	global $wpdb;
	$sid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT service_id FROM " . sp_book_table( 'recurrences' ) . " WHERE id = %d", $rec_id ) );
	$wpdb->update( sp_book_table( 'recurrences' ), [ 'status' => 'ended', 'updated_at' => sp_book_now() ], [ 'id' => $rec_id ] );
	$kept = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . sp_book_table( 'sessions' ) . " WHERE recurrence_id = %d AND starts_at > %s AND seats_taken > 0", $rec_id, sp_book_now()
	) );
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM " . sp_book_table( 'sessions' ) . " WHERE recurrence_id = %d AND starts_at > %s AND seats_taken = 0", $rec_id, sp_book_now()
	) );
	sp_book_cache_bust();
	if ( $sid ) do_action( 'sp_book_sessions_changed', $sid );
	return $kept;
}


/*--------------------------------------------------------------
# Explicit sync (bookable events)
--------------------------------------------------------------*/

/**
 * Make a service's FUTURE sessions exactly match a list — used by bookable events, whose dates live
 * on the event. $want: [ [ 'start' => 'Y-m-d H:i:s' local, 'end' => local, 'capacity' => n ], … ].
 * Dates that exist keep their seats_taken (capacity is updated, never below what's already sold);
 * new dates are added; dates no longer wanted are removed (or cancelled, if anyone booked them).
 * Past sessions are never touched. Returns [ 'added' => n, 'removed' => n, 'kept_booked' => n ].
 */
function sp_book_sync_sessions( int $service_id, array $want ): array {
	global $wpdb;
	$now   = sp_book_now();
	$table = sp_book_table( 'sessions' );
	$have  = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE service_id = %d AND starts_at > %s AND status = 'scheduled'", $service_id, $now
	), ARRAY_A ) ?: [];
	$by_start = [];
	foreach ( $have as $s ) $by_start[ $s['starts_at'] ] = $s;

	$out  = [ 'added' => 0, 'removed' => 0, 'kept_booked' => 0 ];
	$keep = [];
	foreach ( $want as $w ) {
		$start = sp_book_to_utc( (string) $w['start'] );
		if ( $start === '' || $start <= $now ) continue;
		$end   = sp_book_to_utc( (string) ( $w['end'] ?? '' ) );
		$cap   = max( 1, (int) ( $w['capacity'] ?? 1 ) );
		$keep[ $start ] = true;
		if ( isset( $by_start[ $start ] ) ) {
			$s = $by_start[ $start ];
			$upd = [ 'capacity' => max( $cap, (int) $s['seats_taken'] ) ];
			if ( $end !== '' && $end > $start ) $upd['ends_at'] = $end;
			if ( (int) $s['capacity'] !== $upd['capacity'] || ( isset( $upd['ends_at'] ) && $upd['ends_at'] !== $s['ends_at'] ) ) {
				$upd['updated_at'] = $now;
				$wpdb->update( $table, $upd, [ 'id' => (int) $s['id'] ] );
			}
			continue;
		}
		// A cancelled row at the same instant blocks the UNIQUE key — bring it back instead.
		$old = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE service_id = %d AND resource_id = 0 AND starts_at = %s", $service_id, $start ), ARRAY_A );
		if ( $old ) {
			$wpdb->update( $table, [ 'status' => 'scheduled', 'capacity' => max( $cap, (int) $old['seats_taken'] ), 'ends_at' => ( $end !== '' && $end > $start ) ? $end : $old['ends_at'], 'updated_at' => $now ], [ 'id' => (int) $old['id'] ] );
		} else {
			$mins = ( $end !== '' && $end > $start ) ? (int) round( ( strtotime( $end . ' UTC' ) - strtotime( $start . ' UTC' ) ) / 60 ) : 60;
			sp_book_add_session( $service_id, (string) $w['start'], $mins, $cap, 0, 0, '', (string) ( $w['end'] ?? '' ) );
		}
		$out['added']++;
	}

	foreach ( $by_start as $start => $s ) {
		if ( isset( $keep[ $start ] ) ) continue;
		if ( (int) $s['seats_taken'] > 0 ) $out['kept_booked']++;
		sp_book_remove_session( (int) $s['id'], true );
		$out['removed']++;
	}
	sp_book_cache_bust();
	do_action( 'sp_book_sessions_changed', $service_id );
	return $out;
}


/*--------------------------------------------------------------
# AJAX — Settings → Booking → Schedule
--------------------------------------------------------------*/

/** One session row for the schedule list, in local time. */
function sp_book_session_shape( array $s ): array {
	return [
		'id'            => (int) $s['id'],
		'date'          => sp_book_to_local( (string) $s['starts_at'], 'Y-m-d' ),
		'label'         => sp_book_to_local( (string) $s['starts_at'], 'D, M j, Y' ),
		'time'          => sp_book_to_local( (string) $s['starts_at'], 'g:ia' ) . '–' . sp_book_to_local( (string) $s['ends_at'], 'g:ia' ),
		'capacity'      => (int) $s['capacity'],
		'seats_taken'   => (int) $s['seats_taken'],
		'recurrence_id' => (int) $s['recurrence_id'],
		'status'        => (string) $s['status'],
	];
}

add_action( 'wp_ajax_site_pulse_booking_schedule', 'sp_book_ajax_schedule' );
function sp_book_ajax_schedule(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;
	global $wpdb;

	$service_id = (int) ( $_POST['service_id'] ?? 0 );
	$service    = sp_book_get_service( $service_id );
	if ( ! $service ) wp_send_json_error( [ 'message' => 'That service no longer exists.' ] );

	$recs = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_book_table( 'recurrences' ) . " WHERE service_id = %d AND status = 'active' ORDER BY weekday, start_time", $service_id
	), ARRAY_A ) ?: [];
	$days = sp_book_weekdays();
	$recs = array_map( static fn( $r ) => [
		'id'       => (int) $r['id'],
		'label'    => 'Every ' . $days[ (int) $r['weekday'] ] . ' at ' . date( 'g:ia', strtotime( $r['start_time'] ) ) . ' (' . (int) $r['duration_min'] . ' min)',
		'range'    => trim( ( $r['start_date'] ? 'from ' . date( 'M j, Y', strtotime( $r['start_date'] ) ) : '' ) . ( $r['end_date'] ? ' until ' . date( 'M j, Y', strtotime( $r['end_date'] ) ) : '' ) ),
		'capacity' => (int) $r['capacity'],
	], $recs );

	$sessions = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_book_table( 'sessions' ) . " WHERE service_id = %d AND starts_at > %s AND status = 'scheduled' ORDER BY starts_at ASC LIMIT 200",
		$service_id, sp_book_now()
	), ARRAY_A ) ?: [];

	wp_send_json_success( [
		'recurrences' => $recs,
		'sessions'    => array_map( 'sp_book_session_shape', $sessions ),
		'horizon'     => sp_book_session_horizon_days( $service ),
		'capacity'    => (int) $service['capacity'],
		'duration'    => (int) $service['duration_min'],
	] );
}

/** Add dates: one-off (date + time) or weekly (weekdays + from/until), all with length + seats. */
add_action( 'wp_ajax_site_pulse_booking_schedule_add', 'sp_book_ajax_schedule_add' );
function sp_book_ajax_schedule_add(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;
	global $wpdb;

	$service_id = (int) ( $_POST['service_id'] ?? 0 );
	$service    = sp_book_get_service( $service_id );
	if ( ! $service ) wp_send_json_error( [ 'message' => 'That service no longer exists.' ] );
	if ( $service['mode'] !== 'session' ) wp_send_json_error( [ 'message' => 'Only session-type services have a schedule.' ] );

	$time     = sp_book_time_value( sp_book_post( 'time' ) );
	$duration = max( 5, (int) ( $_POST['duration_min'] ?? $service['duration_min'] ) );
	$capacity = max( 1, (int) ( $_POST['capacity'] ?? $service['capacity'] ) );
	if ( $time === '' ) wp_send_json_error( [ 'message' => 'Pick a start time.' ] );

	if ( sp_book_post( 'kind' ) === 'weekly' ) {
		$days  = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $_POST['weekdays'] ?? [] ) ), static fn( $d ) => $d >= 0 && $d <= 6 ) ) );
		$start = sp_book_date_value( sp_book_post( 'start_date' ) );
		$end   = sp_book_date_value( sp_book_post( 'end_date' ) );
		if ( ! $days ) wp_send_json_error( [ 'message' => 'Pick at least one day of the week.' ] );
		if ( $end !== '' && $start !== '' && $end < $start ) wp_send_json_error( [ 'message' => 'The end date is before the start date.' ] );
		$made = 0;
		foreach ( $days as $wd ) {
			$now = sp_book_now();
			$wpdb->insert( sp_book_table( 'recurrences' ), [
				'service_id' => $service_id, 'resource_id' => 0, 'weekday' => $wd, 'start_time' => $time,
				'duration_min' => $duration, 'capacity' => $capacity,
				'start_date' => $start ?: null, 'end_date' => $end ?: null,
				'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
			] );
			$rec = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'recurrences' ) . " WHERE id = %d", (int) $wpdb->insert_id ), ARRAY_A );
			if ( $rec ) $made += sp_book_roll_recurrence( $rec );
		}
		site_pulse_log( 'booking_schedule_added', sprintf( 'Added a weekly schedule to %s (%d dates)', $service['name'], $made ) );
		wp_send_json_success( [ 'message' => $made ? sprintf( 'Weekly schedule added — %d date%s on the calendar so far.', $made, $made === 1 ? '' : 's' ) : 'Weekly schedule added.' ] );
	}

	$date = sp_book_date_value( sp_book_post( 'date' ) );
	if ( $date === '' ) wp_send_json_error( [ 'message' => 'Pick a date.' ] );
	if ( sp_book_to_utc( $date . ' ' . $time ) <= sp_book_now() ) wp_send_json_error( [ 'message' => 'That date and time has already passed.' ] );
	$id = sp_book_add_session( $service_id, $date . ' ' . $time, $duration, $capacity );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Could not add that date.' ] );
	sp_book_cache_bust();
	do_action( 'sp_book_sessions_changed', $service_id );
	site_pulse_log( 'booking_schedule_added', sprintf( 'Added %s %s to %s', $date, $time, $service['name'] ) );
	wp_send_json_success( [ 'message' => 'Date added.' ] );
}

/** Change one date's seats (never below what's already booked). */
add_action( 'wp_ajax_site_pulse_booking_session_capacity', 'sp_book_ajax_session_capacity' );
function sp_book_ajax_session_capacity(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;
	global $wpdb;
	$s   = sp_book_get_session( (int) ( $_POST['id'] ?? 0 ) );
	$cap = (int) ( $_POST['capacity'] ?? 0 );
	if ( ! $s ) wp_send_json_error( [ 'message' => 'That date no longer exists.' ] );
	if ( $cap < max( 1, (int) $s['seats_taken'] ) ) wp_send_json_error( [ 'message' => sprintf( '%d seats are already booked — seats can\'t go below that.', (int) $s['seats_taken'] ) ] );
	$wpdb->update( sp_book_table( 'sessions' ), [ 'capacity' => $cap, 'updated_at' => sp_book_now() ], [ 'id' => (int) $s['id'] ] );
	sp_book_cache_bust();
	wp_send_json_success( [ 'message' => 'Seats updated.' ] );
}

add_action( 'wp_ajax_site_pulse_booking_session_remove', 'sp_book_ajax_session_remove' );
function sp_book_ajax_session_remove(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;
	$res = sp_book_remove_session( (int) ( $_POST['id'] ?? 0 ) );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );
	wp_send_json_success( [ 'message' => 'Date removed.' ] );
}

add_action( 'wp_ajax_site_pulse_booking_recurrence_end', 'sp_book_ajax_recurrence_end' );
function sp_book_ajax_recurrence_end(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;
	$kept = sp_book_end_recurrence( (int) ( $_POST['id'] ?? 0 ) );
	wp_send_json_success( [ 'message' => $kept
		? sprintf( 'Weekly schedule ended. %d upcoming date%s with bookings stayed on the calendar.', $kept, $kept === 1 ? '' : 's' )
		: 'Weekly schedule ended.' ] );
}
