<?php
/* Battle Plan Web Design — Site Pulse: Booking Setup (AJAX)

/*--------------------------------------------------------------
The Settings → Booking screen: services, resources, weekly availability, and a
slot PREVIEW that runs the real engine.

The preview is the point of this file. Until something calls sp_book_slots()
with real rows, the availability engine is untested code — so this screen is
deliberately built before the customer-facing calendar: set a service and some
hours, hit Preview, and see exactly what the engine offers, plus the open
windows it derived, so an empty day tells you WHY it is empty (no hours that
weekday vs. fully booked vs. outside the lead time).

Every handler is nonce-checked and gated on `manage_booking_services`, and every
write busts the availability cache — a stale slot list is a double-booking
waiting to happen.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Gate + Shared Helpers
# Read: the whole setup screen in one call
# Services
# Resources
# Availability (weekly hours + date exceptions)
# Slot Preview — runs the real engine
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Gate + Shared Helpers
--------------------------------------------------------------*/

/** Every endpoint here edits setup, so they all share one capability. */
function sp_book_admin_gate(): bool {
	if ( ! site_pulse_module_on( 'booking' ) ) {
		wp_send_json_error( [ 'message' => 'The Booking module is switched off for this site.' ] );
		return false;
	}
	return site_pulse_admin_check( 'manage_booking_services' );
}

/** POST value, unslashed then sanitized — WordPress slash-escapes every superglobal. */
function sp_book_post( string $key, string $default = '' ): string {
	return sanitize_text_field( wp_unslash( (string) ( $_POST[ $key ] ?? $default ) ) );
}

/** 'HH:MM' (what <input type="time"> posts) → 'HH:MM:00'. '' when unparseable. */
function sp_book_time_value( string $raw ): string {
	$raw = trim( $raw );
	if ( ! preg_match( '/^([0-9]{1,2}):([0-9]{2})(?::([0-9]{2}))?$/', $raw, $m ) ) return '';
	$h = (int) $m[1];
	$i = (int) $m[2];
	if ( $h > 23 || $i > 59 ) return '';
	return sprintf( '%02d:%02d:%02d', $h, $i, isset( $m[3] ) ? (int) $m[3] : 0 );
}

/** 'YYYY-MM-DD' or ''. Anything else becomes '' rather than a 0000-00-00 row. */
function sp_book_date_value( string $raw ): string {
	$raw = trim( $raw );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
}

/** A URL-safe, unique service slug. The slug is the service's stable identity. */
function sp_book_unique_slug( string $name, int $ignore_id = 0 ): string {
	global $wpdb;
	$base = sanitize_title( $name );
	if ( $base === '' ) $base = 'service';
	$slug = $base;
	$n    = 2;
	while ( true ) {
		$found = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . sp_book_table( 'services' ) . " WHERE slug = %s AND id != %d",
			$slug, $ignore_id
		) );
		if ( ! $found ) return $slug;
		$slug = $base . '-' . $n++;
		if ( $n > 200 ) return $base . '-' . wp_rand( 1000, 9999 );   // pathological safety valve
	}
}

/** Money in, cents out. Accepts "85", "85.00", "$85.00". */
function sp_book_cents( string $raw ): int {
	$clean = preg_replace( '/[^0-9.\-]/', '', $raw );
	if ( $clean === '' || $clean === '-' ) return 0;
	return (int) round( ( (float) $clean ) * 100 );
}

function sp_book_weekdays(): array {
	return [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
}


/*--------------------------------------------------------------
# Read: the whole setup screen in one call
--------------------------------------------------------------*/

/**
 * Everything the setup panel renders. One round trip rather than four — the
 * tables are tiny and the screen needs all of it before it can draw anything.
 */
add_action( 'wp_ajax_site_pulse_booking_admin_data', 'sp_book_ajax_admin_data' );
function sp_book_ajax_admin_data(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	global $wpdb;

	$services = $wpdb->get_results(
		"SELECT * FROM " . sp_book_table( 'services' ) . " ORDER BY display_order ASC, name ASC",
		ARRAY_A
	) ?: [];

	$resources = $wpdb->get_results(
		"SELECT * FROM " . sp_book_table( 'resources' ) . " ORDER BY display_order ASC, name ASC",
		ARRAY_A
	) ?: [];

	$rules = $wpdb->get_results(
		"SELECT * FROM " . sp_book_table( 'rules' ) . " ORDER BY resource_id ASC, rule_type ASC, weekday ASC, start_time ASC",
		ARRAY_A
	) ?: [];

	// Which resources each service is linked to (empty = every active resource).
	$links = [];
	foreach ( $wpdb->get_results( "SELECT service_id, resource_id FROM " . sp_book_table( 'service_resources' ), ARRAY_A ) ?: [] as $row ) {
		$links[ (int) $row['service_id'] ][] = (int) $row['resource_id'];
	}

	foreach ( $services as &$svc ) {
		$svc['resource_ids'] = $links[ (int) $svc['id'] ] ?? [];
		$svc['opts']         = sp_book_service_opts( $svc );
		// The event this booking is linked to (Events → the event's Booking dropdown), shown on its row.
		$eid = function_exists( 'sp_book_service_event' ) ? sp_book_service_event( $svc ) : 0;
		$svc['event'] = $eid ? [ 'id' => $eid, 'title' => html_entity_decode( get_the_title( $eid ), ENT_QUOTES ), 'status' => get_post_status( $eid ), 'url' => (string) get_permalink( $eid ) ] : null;
	}
	unset( $svc );

	// Site Pulse users, so a resource can be linked to the person who fills it —
	// that link is what `view_own_bookings` scopes a tech's calendar by.
	// NB: these rows are user_profiles rows (up.*) with the WP user joined on, so the
	// key is `user_id` — there is no `ID` column here.
	$users = [];
	foreach ( site_pulse_get_all_users( true, false ) as $u ) {
		$uid = (int) ( $u['user_id'] ?? 0 );
		if ( ! $uid ) continue;
		$users[] = [ 'id' => $uid, 'name' => (string) ( $u['display_name'] ?? ( 'User #' . $uid ) ) ];
	}

	// Types (the templates) with how many offerings follow each; per-offering date / seat facts.
	$type_counts = [];
	$stats       = sp_book_offering_stats();
	foreach ( $services as &$svc ) {
		$svc['type_id']   = (int) $svc['type_id'];
		$svc['kind']      = sp_book_service_kind( $svc );
		$svc['rules']     = sp_book_service_rules( $svc );
		$svc['overrides'] = sp_book_service_overrides( $svc );
		$svc['stats']     = $stats[ (int) $svc['id'] ] ?? null;
		if ( $svc['type_id'] ) $type_counts[ $svc['type_id'] ] = ( $type_counts[ $svc['type_id'] ] ?? 0 ) + 1;
	}
	unset( $svc );
	$types = array_map( static fn( $t ) => sp_book_type_shape( $t, $type_counts ), sp_book_get_types() );
	$kinds = [];
	foreach ( sp_book_type_kinds() as $k => $v ) $kinds[ $k ] = [ 'label' => $v['label'], 'short' => $v['short'], 'mode' => $v['mode'], 'series' => $v['series'] ];

	wp_send_json_success( [
		'travel'    => sp_book_travel_settings(),
		'types'     => $types,
		'kinds'     => $kinds,
		'events_on' => function_exists( 'sp_book_events_ready' ) && sp_book_events_ready(),
		'events'    => sp_book_unlinked_events(),
		'can_edit_events' => function_exists( 'sp_events_can_manage' ) && sp_events_can_manage(),
		'services'  => $services,
		'resources' => $resources,
		'rules'     => $rules,
		'users'     => $users,
		'modes'     => sp_book_modes(),
		'weekdays'  => sp_book_weekdays(),
		'timezone'  => sp_book_tz()->getName(),
		'today'     => sp_book_to_local( sp_book_now(), 'Y-m-d' ),
	] );
}


/*--------------------------------------------------------------
# Services
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_booking_save_service', 'sp_book_ajax_save_service' );
/**
 * Save an offering. With a type (includes-site-pulse-booking-types.php) the kind comes from the type,
 * every rule the form sends that differs from the type becomes an override, and every rule it doesn't
 * send keeps following the type (or keeps its own value when it was already overridden).
 */
function sp_book_ajax_save_service(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	global $wpdb;
	$id   = (int) ( $_POST['id'] ?? 0 );
	$svc  = $id ? sp_book_get_service( $id ) : null;
	if ( $id && ! $svc ) wp_send_json_error( [ 'message' => 'That booking no longer exists.' ] );
	$name = sp_book_post( 'name' );
	if ( $name === '' ) {
		wp_send_json_error( [ 'message' => 'A name is required.' ] );
	}

	$type_id = isset( $_POST['type_id'] ) ? (int) $_POST['type_id'] : (int) ( $svc['type_id'] ?? 0 );
	$type    = $type_id ? sp_book_get_type( $type_id ) : null;
	if ( ! $type ) $type_id = 0;
	$kinds   = sp_book_type_kinds();

	$kind = $type ? (string) $type['kind'] : sp_book_post( 'kind', $svc ? sp_book_service_kind( $svc ) : '' );
	if ( isset( $kinds[ $kind ] ) ) {
		$mode   = $kinds[ $kind ]['mode'];
		$series = $kinds[ $kind ]['series'];
	} else {
		$kind = '';
		$mode = sp_book_post( 'mode', (string) ( $svc['mode'] ?? 'appointment' ) );
		if ( ! array_key_exists( $mode, sp_book_modes() ) ) $mode = 'appointment';
		$series = isset( $_POST['series'] ) ? ! empty( $_POST['series'] ) : ( $svc ? sp_book_service_opts( $svc )['series'] : false );
	}

	// A booking with dates or sign-ups can't change shape (a class can't become an appointment).
	if ( $svc && $mode !== $svc['mode'] ) {
		$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . sp_book_table( 'bookings' ) . " WHERE service_id = %d", $id ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . sp_book_table( 'sessions' ) . " WHERE service_id = %d", $id ) );
		if ( $used ) wp_send_json_error( [ 'message' => 'This service already has dates or sign-ups, so it can’t switch to a different kind. Pick a template of the same kind, or make a new service.' ] );
	}

	$posted   = sp_book_rules_from_post();
	$existing = $svc ? sp_book_service_rules( $svc ) : sp_book_rules_normalize( [] );
	$old_over = $svc && (int) $svc['type_id'] === $type_id ? sp_book_service_overrides( $svc ) : [];
	$trules   = $type ? sp_book_type_rules( $type ) : null;
	$rules    = [];
	foreach ( array_keys( sp_book_rule_defaults() ) as $k ) {
		if ( array_key_exists( $k, $posted ) )                 $rules[ $k ] = $posted[ $k ];
		elseif ( $trules && ! in_array( $k, $old_over, true ) ) $rules[ $k ] = $trules[ $k ];
		else                                                    $rules[ $k ] = $existing[ $k ];
	}
	$rules = sp_book_rules_normalize( $kind !== '' ? sp_book_kind_force( $rules, $kind ) : $rules );
	if ( $trules && $kind !== '' ) $trules = sp_book_rules_normalize( sp_book_kind_force( $trules, $kind ) );
	$over  = $trules ? array_keys( array_filter( $rules, static fn( $v, $k ) => $v !== $trules[ $k ], ARRAY_FILTER_USE_BOTH ) ) : [];

	$now  = sp_book_now();
	$data = [
		'name'        => $name,
		'slug'        => sp_book_unique_slug( sp_book_post( 'slug' ) !== '' ? sp_book_post( 'slug' ) : $name, $id ),
		'type_id'     => $type_id,
		'description' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['description'] ?? '' ) ) ),
		'status'      => sp_book_post( 'status', 'active' ) === 'inactive' ? 'inactive' : 'active',
		'updated_at'  => $now,
	];
	if ( isset( $_POST['display_order'] ) ) $data['display_order'] = (int) $_POST['display_order'];

	if ( $id ) {
		$wpdb->update( sp_book_table( 'services' ), $data, [ 'id' => $id ] );
	} else {
		$data['created_at'] = $now;
		$wpdb->insert( sp_book_table( 'services' ), $data );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) wp_send_json_error( [ 'message' => 'Could not save that booking.' ] );
	}
	sp_book_write_rules( $id, $rules, $mode, $series );
	sp_book_service_set_opts( $id, [ 'overrides' => $over ] );

	// The website page (session offerings): new / an event id / none.
	$page = sanitize_key( (string) ( $_POST['page'] ?? '' ) );
	$res  = sp_book_offering_set_page( $id, $page );

	sp_book_cache_bust();
	site_pulse_log( 'booking_service_saved', sprintf( 'Saved booking service: %s', $name ) );
	$msg = 'Saved.';
	if ( is_wp_error( $res ) ) $msg = 'Saved, but the website page wasn’t linked: ' . $res->get_error_message();
	wp_send_json_success( [ 'message' => $msg, 'id' => $id ] );
}

/** Duplicate a booking's settings (no dates, no sign-ups) — the next run of a class. */
add_action( 'wp_ajax_site_pulse_booking_duplicate_service', 'sp_book_ajax_duplicate_service' );
function sp_book_ajax_duplicate_service(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;
	$new = function_exists( 'sp_book_offering_duplicate' ) ? sp_book_offering_duplicate( (int) ( $_POST['id'] ?? 0 ) ) : 0;
	if ( ! $new ) wp_send_json_error( [ 'message' => 'Could not copy that booking.' ] );
	site_pulse_log( 'booking_service_saved', sprintf( 'Duplicated booking service #%d', (int) $_POST['id'] ) );
	wp_send_json_success( [ 'id' => $new, 'message' => 'Copied (no dates or sign-ups). Give the copy its dates with Dates.' ] );
}

/**
 * Deleting a service that has bookings would orphan them, so this refuses and
 * points at deactivation instead — which hides it from booking without touching
 * anything already on the calendar.
 */
add_action( 'wp_ajax_site_pulse_booking_delete_service', 'sp_book_ajax_delete_service' );
function sp_book_ajax_delete_service(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Bad request.' ] );

	$booked = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . sp_book_table( 'bookings' ) . " WHERE service_id = %d", $id
	) );
	if ( $booked ) {
		wp_send_json_error( [ 'message' => sprintf(
			'This service has %d booking%s. Set it to Inactive instead — deleting it would orphan them.',
			$booked, $booked === 1 ? '' : 's'
		) ] );
	}

	// A linked event lets go of it (the event stays, with no booking).
	$svc = sp_book_get_service( $id );
	if ( $svc && function_exists( 'sp_book_service_event' ) && ( $eid = sp_book_service_event( $svc ) ) ) delete_post_meta( $eid, '_bp_book_service_id' );

	$wpdb->delete( sp_book_table( 'services' ), [ 'id' => $id ] );
	$wpdb->delete( sp_book_table( 'service_resources' ), [ 'service_id' => $id ] );
	sp_book_cache_bust();
	wp_send_json_success( [ 'message' => 'Service deleted.' ] );
}


/*--------------------------------------------------------------
# Resources
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_booking_save_resource', 'sp_book_ajax_save_resource' );
function sp_book_ajax_save_resource(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	global $wpdb;
	$id   = (int) ( $_POST['id'] ?? 0 );
	$name = sp_book_post( 'name' );
	if ( $name === '' ) {
		wp_send_json_error( [ 'message' => 'A resource name is required.' ] );
	}

	$type = sp_book_post( 'resource_type', 'staff' );
	if ( ! in_array( $type, [ 'staff', 'room', 'unit', 'equipment' ], true ) ) $type = 'staff';

	$now  = sp_book_now();
	$data = [
		'name'          => $name,
		'resource_type' => $type,
		'user_id'       => (int) ( $_POST['user_id'] ?? 0 ),
		'location_id'   => (int) ( $_POST['location_id'] ?? 0 ),
		'capacity'      => max( 1, (int) ( $_POST['capacity'] ?? 1 ) ),
		'timezone'      => sp_book_post( 'timezone' ),
		'color'         => sp_book_post( 'color' ),
		'email'         => sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ),
		'phone'         => sp_book_post( 'phone' ),
		'display_order' => (int) ( $_POST['display_order'] ?? 0 ),
		'status'        => sp_book_post( 'status', 'active' ) === 'inactive' ? 'inactive' : 'active',
		'updated_at'    => $now,
	];

	// A bad timezone string would make every slot for this resource wrong, so it is
	// validated here rather than discovered later as an hour-off booking.
	if ( $data['timezone'] !== '' ) {
		try { new DateTimeZone( $data['timezone'] ); }
		catch ( Exception $e ) { wp_send_json_error( [ 'message' => 'That is not a valid timezone name (e.g. America/Chicago).' ] ); }
	}

	if ( $id ) {
		$wpdb->update( sp_book_table( 'resources' ), $data, [ 'id' => $id ] );
	} else {
		$data['created_at'] = $now;
		$wpdb->insert( sp_book_table( 'resources' ), $data );
		$id = (int) $wpdb->insert_id;
	}

	sp_book_cache_bust();
	site_pulse_log( 'booking_resource_saved', sprintf( 'Saved booking resource: %s', $name ) );
	wp_send_json_success( [ 'message' => 'Resource saved.', 'id' => $id ] );
}

add_action( 'wp_ajax_site_pulse_booking_delete_resource', 'sp_book_ajax_delete_resource' );
function sp_book_ajax_delete_resource(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Bad request.' ] );

	$booked = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . sp_book_table( 'bookings' ) . " WHERE resource_id = %d", $id
	) );
	if ( $booked ) {
		wp_send_json_error( [ 'message' => sprintf(
			'This resource has %d booking%s. Set it to Inactive instead.',
			$booked, $booked === 1 ? '' : 's'
		) ] );
	}

	$wpdb->delete( sp_book_table( 'resources' ), [ 'id' => $id ] );
	$wpdb->delete( sp_book_table( 'service_resources' ), [ 'resource_id' => $id ] );
	$wpdb->delete( sp_book_table( 'rules' ), [ 'resource_id' => $id ] );   // its schedule goes with it
	sp_book_cache_bust();
	wp_send_json_success( [ 'message' => 'Resource deleted.' ] );
}


/*--------------------------------------------------------------
# Availability (weekly hours + date exceptions)
--------------------------------------------------------------*/

/**
 * Replace a resource's ENTIRE weekly template in one call.
 *
 * Wholesale replacement rather than row-by-row edits: the weekly grid is what
 * the user is actually editing, and a partial save is how you end up with a
 * stale Tuesday nobody notices until a customer books into it. Blackouts and
 * one-off open dates are separate rows and are left alone here.
 *
 * `days` is posted as [ { weekday, start, end } ] — a weekday with no row is
 * simply a day off.
 */
add_action( 'wp_ajax_site_pulse_booking_save_hours', 'sp_book_ajax_save_hours' );
function sp_book_ajax_save_hours(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	global $wpdb;
	$resource_id = (int) ( $_POST['resource_id'] ?? 0 );
	if ( ! $resource_id || ! sp_book_get_resource( $resource_id ) ) {
		wp_send_json_error( [ 'message' => 'Pick a resource first.' ] );
	}

	$days = json_decode( wp_unslash( (string) ( $_POST['days'] ?? '[]' ) ), true );
	if ( ! is_array( $days ) ) $days = [];

	$rows = [];
	foreach ( $days as $day ) {
		$weekday = isset( $day['weekday'] ) ? (int) $day['weekday'] : -1;
		$start   = sp_book_time_value( (string) ( $day['start'] ?? '' ) );
		$end     = sp_book_time_value( (string) ( $day['end'] ?? '' ) );

		if ( $weekday < 0 || $weekday > 6 || $start === '' || $end === '' ) continue;
		if ( $end <= $start ) {
			wp_send_json_error( [ 'message' => sprintf(
				'%s ends before it starts. Overnight shifts aren\'t supported yet — split them across two days.',
				sp_book_weekdays()[ $weekday ]
			) ] );
		}
		$rows[] = [ 'weekday' => $weekday, 'start' => $start, 'end' => $end ];
	}

	$wpdb->delete( sp_book_table( 'rules' ), [ 'resource_id' => $resource_id, 'rule_type' => 'hours' ] );

	$now = sp_book_now();
	foreach ( $rows as $row ) {
		$wpdb->insert( sp_book_table( 'rules' ), [
			'resource_id' => $resource_id,
			'service_id'  => 0,
			'rule_type'   => 'hours',
			'weekday'     => $row['weekday'],
			'start_time'  => $row['start'],
			'end_time'    => $row['end'],
			'source'      => 'manual',
			'created_at'  => $now,
		] );
	}

	sp_book_cache_bust();
	wp_send_json_success( [ 'message' => 'Hours saved.', 'count' => count( $rows ) ] );
}

/** A single date exception: `blackout` (time removed) or `open` (extra time). */
add_action( 'wp_ajax_site_pulse_booking_save_exception', 'sp_book_ajax_save_exception' );
function sp_book_ajax_save_exception(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	global $wpdb;
	$type = sp_book_post( 'rule_type', 'blackout' ) === 'open' ? 'open' : 'blackout';
	$from = sp_book_date_value( sp_book_post( 'start_date' ) );
	$to   = sp_book_date_value( sp_book_post( 'end_date' ) );
	if ( $from === '' ) {
		wp_send_json_error( [ 'message' => 'A start date is required.' ] );
	}
	if ( $to === '' ) $to = $from;
	if ( $to < $from ) {
		wp_send_json_error( [ 'message' => 'The end date is before the start date.' ] );
	}

	$all_day = ! empty( $_POST['all_day'] ) ? 1 : 0;
	$start   = sp_book_time_value( sp_book_post( 'start_time' ) );
	$end     = sp_book_time_value( sp_book_post( 'end_time' ) );

	// An `open` rule with no times would add a window of zero length — it has to be timed.
	if ( $type === 'open' && ( $start === '' || $end === '' ) ) {
		wp_send_json_error( [ 'message' => 'Extra availability needs a start and end time.' ] );
	}
	if ( ! $all_day && $start !== '' && $end !== '' && $end <= $start ) {
		wp_send_json_error( [ 'message' => 'The end time is before the start time.' ] );
	}
	if ( $type === 'blackout' && ( $start === '' || $end === '' ) ) $all_day = 1;

	$wpdb->insert( sp_book_table( 'rules' ), [
		'resource_id' => (int) ( $_POST['resource_id'] ?? 0 ),   // 0 = applies to everyone
		'service_id'  => 0,
		'rule_type'   => $type,
		'weekday'     => null,
		'start_time'  => $all_day ? null : ( $start ?: null ),
		'end_time'    => $all_day ? null : ( $end ?: null ),
		'start_date'  => $from,
		'end_date'    => $to,
		'all_day'     => $all_day,
		'note'        => sp_book_post( 'note' ),
		'source'      => 'manual',
		'created_at'  => sp_book_now(),
	] );

	sp_book_cache_bust();
	wp_send_json_success( [ 'message' => 'Saved.', 'id' => (int) $wpdb->insert_id ] );
}

add_action( 'wp_ajax_site_pulse_booking_delete_rule', 'sp_book_ajax_delete_rule' );
function sp_book_ajax_delete_rule(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Bad request.' ] );

	$wpdb->delete( sp_book_table( 'rules' ), [ 'id' => $id ] );
	sp_book_cache_bust();
	wp_send_json_success( [ 'message' => 'Removed.' ] );
}


/*--------------------------------------------------------------
# Slot Preview — runs the real engine
--------------------------------------------------------------*/

/**
 * Run sp_book_slots() for a service over a date range and return what it offers,
 * grouped by local day.
 *
 * Each day also carries the OPEN WINDOWS the rules produced, before bookings
 * were subtracted. That is the difference between a preview and a diagnostic:
 * an empty Tuesday with no windows means "she doesn't work Tuesdays", an empty
 * Tuesday with a 9–5 window means "it's booked solid or inside the lead time" —
 * and you can tell which at a glance instead of guessing at the rules.
 */
add_action( 'wp_ajax_site_pulse_booking_preview_slots', 'sp_book_ajax_preview_slots' );
function sp_book_ajax_preview_slots(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	$service_id = (int) ( $_POST['service_id'] ?? 0 );
	$service    = sp_book_get_service( $service_id );
	if ( ! $service ) {
		wp_send_json_error( [ 'message' => 'Pick a service to preview.' ] );
	}
	if ( $service['status'] !== 'active' ) {
		wp_send_json_error( [ 'message' => 'That service is inactive, so it offers no slots. Set it Active to preview.' ] );
	}

	$from = sp_book_date_value( sp_book_post( 'from' ) );
	$to   = sp_book_date_value( sp_book_post( 'to' ) );
	if ( $from === '' ) $from = sp_book_to_local( sp_book_now(), 'Y-m-d' );
	if ( $to === '' || $to < $from ) {
		$to = ( new DateTime( $from ) )->modify( '+13 days' )->format( 'Y-m-d' );
	}

	$resource_id = (int) ( $_POST['resource_id'] ?? 0 );
	$resources   = sp_book_service_resources( $service_id );
	if ( $resource_id ) {
		$resources = array_values( array_filter( $resources, fn( $r ) => (int) $r['id'] === $resource_id ) );
	}
	if ( ! $resources ) {
		wp_send_json_error( [ 'message' => 'This service has no active resource to deliver it. Add a resource first.' ] );
	}

	$names = [];
	foreach ( $resources as $r ) $names[ (int) $r['id'] ] = (string) $r['name'];

	$slots = sp_book_slots( $service_id, $from, $to, $resource_id );

	// Bucket the slots by their LOCAL date — the engine returns UTC, and a late
	// evening slot would otherwise land on the wrong day for the person reading it.
	$by_date = [];
	foreach ( $slots as $slot ) {
		$date = sp_book_to_local( $slot['starts_at'], 'Y-m-d' );
		$by_date[ $date ][] = [
			'time'        => sp_book_to_local( $slot['starts_at'], 'g:i A' ),
			'starts_at'   => $slot['starts_at'],
			'resource_id' => (int) $slot['resource_id'],
			'resource'    => $names[ (int) $slot['resource_id'] ] ?? '',
			'seats_left'  => (int) ( $slot['seats_left'] ?? 1 ),
		];
	}

	$days   = [];
	$cursor = new DateTime( $from );
	$stop   = new DateTime( $to );
	$guard  = 0;
	while ( $cursor <= $stop && $guard < 120 ) {
		$date = $cursor->format( 'Y-m-d' );

		// The rules' own output, so an empty day explains itself.
		$windows = [];
		foreach ( $resources as $r ) {
			foreach ( sp_book_open_windows( $service, (int) $r['id'], $date ) as $w ) {
				$windows[] = [
					'resource' => (string) $r['name'],
					'label'    => sp_book_to_local( gmdate( 'Y-m-d H:i:s', $w[0] ), 'g:i A', (string) $r['timezone'] )
					              . ' – ' . sp_book_to_local( gmdate( 'Y-m-d H:i:s', $w[1] ), 'g:i A', (string) $r['timezone'] ),
				];
			}
		}

		$days[] = [
			'date'    => $date,
			'label'   => $cursor->format( 'D, M j' ),
			'slots'   => $by_date[ $date ] ?? [],
			'windows' => $windows,
		];
		$cursor->modify( '+1 day' );
		$guard++;
	}

	wp_send_json_success( [
		'days'      => $days,
		'total'     => count( $slots ),
		'timezone'  => sp_book_tz()->getName(),
		'service'   => [
			'name'         => (string) $service['name'],
			'mode'         => (string) $service['mode'],
			'duration_min' => (int) $service['duration_min'],
			'lead_time_min'    => (int) $service['lead_time_min'],
			'max_advance_days' => (int) $service['max_advance_days'],
		],
	] );
}


/** What this business calls what it books (site_pulse_term 'service'): Services, Retreats, Classes… */
add_action( 'wp_ajax_site_pulse_booking_save_names', 'sp_book_ajax_save_names' );
function sp_book_ajax_save_names(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;
	sp_book_set_setting( 'name_one',  mb_substr( sanitize_text_field( wp_unslash( $_POST['one'] ?? '' ) ), 0, 30 ) );
	sp_book_set_setting( 'name_many', mb_substr( sanitize_text_field( wp_unslash( $_POST['many'] ?? '' ) ), 0, 30 ) );
	wp_send_json_success( [ 'message' => 'Saved. Reload Site Pulse to see the new name everywhere.' ] );
}


/*--------------------------------------------------------------
# Travel constants
--------------------------------------------------------------*/

/**
 * The straight-line-to-driving-time conversion, tuned per install.
 *
 * These ship with Dallas-calibrated defaults, but a number derived from LBJ traffic is wrong in
 * Naples or a rural county — so every client tunes their own rather than inheriting someone
 * else's metro. Nothing here reaches an API; it is the arithmetic the scheduler runs on.
 */
add_action( 'wp_ajax_site_pulse_booking_save_travel', 'sp_book_ajax_save_travel' );
function sp_book_ajax_save_travel(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	sp_book_set_setting( 'travel_intrazonal_min', (string) max( 0, (int) ( $_POST['intrazonal'] ?? 10 ) ) );
	sp_book_set_setting( 'travel_band_miles',     (string) max( 1, (float) ( $_POST['band_miles'] ?? 10 ) ) );
	sp_book_set_setting( 'travel_local_detour',   (string) max( 1, (float) ( $_POST['local_detour'] ?? 1.4 ) ) );
	sp_book_set_setting( 'travel_local_pace',     (string) max( 0.1, (float) ( $_POST['local_pace'] ?? 1.3 ) ) );
	sp_book_set_setting( 'travel_metro_detour',   (string) max( 1, (float) ( $_POST['metro_detour'] ?? 1.3 ) ) );
	sp_book_set_setting( 'travel_metro_pace',     (string) max( 0.1, (float) ( $_POST['metro_pace'] ?? 1.6 ) ) );
	sp_book_set_setting( 'travel_cap_min',        (string) max( 0, (int) ( $_POST['cap'] ?? 120 ) ) );

	// Cached pair times were computed with the OLD numbers, so they are now wrong. Only the
	// estimates are cleared — any measured 'api' rows stay, since real times don't change
	// because a multiplier did.
	global $wpdb;
	sp_book_travel_flush();

	wp_send_json_success( [
		'message' => 'Saved. Cached estimates were cleared so the new numbers apply immediately.',
		'travel'  => sp_book_travel_settings(),
	] );
}

/**
 * Cached pair times were computed with the OLD numbers, so they are now wrong. Only the
 * estimates are cleared — any measured 'api' rows stay, since a real drive doesn't get
 * longer because a multiplier changed.
 */
function sp_book_travel_flush(): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM " . sp_book_table( 'travel' ) . " WHERE source = 'estimate'" );
	sp_book_cache_bust();
}

/**
 * Derive one band's pace from a drive the client actually knows the length of.
 */
add_action( 'wp_ajax_site_pulse_booking_calibrate_travel', 'sp_book_ajax_calibrate_travel' );
function sp_book_ajax_calibrate_travel(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	$band = sp_book_post( 'band' ) === 'metro' ? 'metro' : 'local';

	[ $ok, $message, $actual ] = sp_book_travel_calibrate(
		sp_book_post( 'zip_a' ),
		sp_book_post( 'zip_b' ),
		(int) ( $_POST['minutes'] ?? 0 ),
		$band
	);

	if ( ! $ok ) {
		wp_send_json_error( [ 'message' => $message, 'band' => $actual ] );
	}

	sp_book_travel_flush();

	wp_send_json_success( [ 'message' => $message, 'travel' => sp_book_travel_settings() ] );
}
