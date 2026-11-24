<?php
/* Battle Plan Web Design — Site Pulse: Events ↔ Booking (linking)

/*--------------------------------------------------------------
Bookings and events are separate things that can be LINKED:

  booking only   an appointment, a private lesson, an HVAC visit — no event page.
  event only     a show, an open house — nothing to sign up for.
  both           an event page that sells / RSVPs a booking: a free RSVP is a
                 booking with no price; a $300 class is a paid booking.

EVERYTHING about booking lives on the booking (Settings → Booking): dates,
seats, price, button wording, "each date separately" vs "all dates together".
An event just points at one: the event's "Booking" dropdown stores the
service id in post meta `_bp_book_service_id`, and the service stores the
event back in services.meta.event_id (so the calendar shows the class ONCE,
through its event, and a booking can be linked to only one event).

THE BOOKING OWNS THE DATES. A linked event's dates are copied FROM the
booking's sessions (sp_book_event_pull_dates) whenever those change, so the
calendar, the archive, the event page and the auto-expire all keep reading
the event's own date fields exactly as before. Unlinked events keep their
own dates.

Duplicate (next run of a class): sp_events_duplicate() copies the event AND
its booking (sp_book_duplicate_service — settings only, no dates) and links
the two, so each run has its own sign-ups.

(Before 2026-11-20 an event owned a HIDDEN service whose sessions were made
from the event's dates. Those services are now ordinary visible bookings,
still linked to their events — nothing to migrate beyond dropping the link
from events that had booking switched off.)

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Readiness & the link
# Dates: booking → event
# Duplicating a booking
# Hooks
# wp-admin field
# Read helpers (editor + calendar)
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Readiness & the link
--------------------------------------------------------------*/

/** Booking is on and Events is loaded — the only time an event can be linked to a booking. */
function sp_book_events_ready(): bool {
	return function_exists( 'site_pulse_module_on' ) && site_pulse_module_on( 'booking' )
		&& post_type_exists( 'events' ) && function_exists( 'bp_event_date_blocks' );
}

/** The booking an event is linked to, or null. */
function sp_book_event_service( int $post_id ): ?array {
	$sid = (int) get_post_meta( $post_id, '_bp_book_service_id', true );
	$svc = $sid ? sp_book_get_service( $sid ) : null;
	if ( $svc && sp_book_service_opts( $svc )['event_id'] === $post_id ) return $svc;
	return null;
}

/** The event a booking is linked to (any status but trash), or 0. */
function sp_book_service_event( array $service ): int {
	$eid = sp_book_service_opts( $service )['event_id'];
	if ( ! $eid ) return 0;
	$st = get_post_status( $eid );
	if ( ! $st || $st === 'trash' || get_post_type( $eid ) !== 'events' ) return 0;
	return (int) get_post_meta( $eid, '_bp_book_service_id', true ) === (int) $service['id'] ? $eid : 0;
}

/**
 * Bookings an event can link to: every session-type booking, each saying which event (if any) it's
 * already linked to. Only session bookings have dates to put on a calendar.
 */
function sp_book_linkable_services(): array {
	if ( ! function_exists( 'sp_book_table' ) ) return [];
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT * FROM " . sp_book_table( 'services' ) . " WHERE mode = 'session' ORDER BY status ASC, name ASC", ARRAY_A ) ?: [];
	// Upcoming dates per booking, so the dropdown can say "no upcoming dates" — free or paid, a booking
	// with dates is listed the same way (a free one is how RSVPs are counted).
	$upcoming = [];
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT service_id, COUNT(*) AS n FROM " . sp_book_table( 'sessions' ) . " WHERE status = 'scheduled' AND starts_at > %s GROUP BY service_id", sp_book_now() ), ARRAY_A ) ?: [] as $u ) {
		$upcoming[ (int) $u['service_id'] ] = (int) $u['n'];
	}
	return array_map( static function ( $s ) use ( $upcoming ) {
		$eid   = sp_book_service_event( $s );
		$price = (int) $s['price_cents'];
		return [
			'id'          => (int) $s['id'],
			'name'        => (string) $s['name'],
			'status'      => (string) $s['status'],
			'upcoming'    => $upcoming[ (int) $s['id'] ] ?? 0,
			'price'       => $price && $s['requires_payment'] !== 'none' ? '$' . number_format( $price / 100, 2 ) : 'Free',
			'series'      => sp_book_service_opts( $s )['series'],
			'event_id'    => $eid,
			'event_title' => $eid ? html_entity_decode( get_the_title( $eid ), ENT_QUOTES ) : '',
		];
	}, $rows );
}

/**
 * Link an event to a booking (0 = unlink). One booking per event, one event per booking — a booking
 * already linked to ANOTHER live event is refused. Returns true or a WP_Error.
 */
function sp_book_event_link( int $event_id, int $service_id ) {
	if ( ! sp_book_events_ready() || get_post_type( $event_id ) !== 'events' ) return true;
	$current = (int) get_post_meta( $event_id, '_bp_book_service_id', true );

	if ( $service_id ) {
		$svc = sp_book_get_service( $service_id );
		if ( ! $svc || $svc['mode'] !== 'session' ) return new WP_Error( 'sp_book_link', 'That booking no longer exists, or it isn\'t a class / session booking.' );
		$other = sp_book_service_event( $svc );
		if ( $other && $other !== $event_id ) {
			return new WP_Error( 'sp_book_link', sprintf( '"%s" is already linked to the event "%s". A booking can be linked to one event.', $svc['name'], html_entity_decode( get_the_title( $other ), ENT_QUOTES ) ) );
		}
	}

	if ( $current && $current !== $service_id ) {
		$old = sp_book_get_service( $current );
		if ( $old && sp_book_service_opts( $old )['event_id'] === $event_id ) sp_book_service_set_opts( $current, [ 'event_id' => 0 ] );
	}

	if ( $service_id ) {
		update_post_meta( $event_id, '_bp_book_service_id', $service_id );
		update_post_meta( $event_id, 'book_service', $service_id );   // the wp-admin dropdown's own value
		sp_book_service_set_opts( $service_id, [ 'event_id' => $event_id ] );
		sp_book_event_pull_dates( $event_id );
	} else {
		delete_post_meta( $event_id, '_bp_book_service_id' );
		delete_post_meta( $event_id, 'book_service' );
	}
	sp_book_cache_bust();
	return true;
}


/*--------------------------------------------------------------
# Dates: booking → event
--------------------------------------------------------------*/

/**
 * Copy a linked booking's dates onto its event (as specific date blocks), from the start of this month
 * on — the same window a repeating event keeps. The calendar, archive ordering, event page and the
 * auto-expire all read these, so a linked event behaves like any other.
 */
function sp_book_event_pull_dates( int $event_id ): void {
	$svc = sp_book_event_service( $event_id );
	if ( ! $svc ) return;
	global $wpdb;
	$from = sp_book_to_utc( ( new DateTime( 'first day of this month', sp_book_tz() ) )->format( 'Y-m-d' ) . ' 00:00:00' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT starts_at, ends_at FROM " . sp_book_table( 'sessions' ) . " WHERE service_id = %d AND status = 'scheduled' AND starts_at >= %s ORDER BY starts_at ASC LIMIT 800",
		(int) $svc['id'], $from
	), ARRAY_A ) ?: [];

	$blocks = array_map( static function ( $s ) {
		$st = sp_book_to_local( (string) $s['starts_at'], 'H:i' );
		$et = sp_book_to_local( (string) $s['ends_at'], 'H:i' );
		$all_day = $st === '00:00' && $et === '23:59';
		return [
			's_date' => sp_book_to_local( (string) $s['starts_at'], 'Y-m-d' ),
			'e_date' => sp_book_to_local( (string) $s['ends_at'], 'Y-m-d' ),
			's_time' => $all_day ? '' : $st,
			'e_time' => $all_day ? '' : $et,
		];
	}, $rows );

	// Written as plain meta with ACF's reference keys, so get_field() and the wp-admin editor read them.
	update_post_meta( $event_id, 'date_type', 'specific' );
	update_post_meta( $event_id, '_date_type', 'field_bp_date_type' );
	update_post_meta( $event_id, 'event_dates', wp_json_encode( $blocks ) );
	update_post_meta( $event_id, '_event_dates', 'field_bp_event_dates' );
	if ( $blocks ) {
		if ( function_exists( 'bp_event_sync_span' ) ) bp_event_sync_span( $event_id );
	} else {
		// No dates on the booking = none on the event. The legacy span would otherwise stand in for them
		// (bp_event_date_blocks() falls back to it).
		foreach ( [ 'start_date', 'end_date', 'start_time', 'end_time' ] as $k ) delete_post_meta( $event_id, $k );
	}
	// ACF caches field values for the request; drop them so a later read sees the new dates.
	if ( function_exists( 'acf_flush_value_cache' ) ) {
		acf_flush_value_cache( $event_id, 'event_dates' );
		acf_flush_value_cache( $event_id, 'date_type' );
	}
}

/** A booking's dates changed (added, removed, rolled forward): refresh its event's copy. */
add_action( 'sp_book_sessions_changed', 'sp_book_event_on_sessions_changed' );
function sp_book_event_on_sessions_changed( $service_id ): void {
	$svc = sp_book_get_service( (int) $service_id );
	if ( ! $svc || ! sp_book_events_ready() ) return;
	$eid = sp_book_service_event( $svc );
	if ( $eid ) sp_book_event_pull_dates( $eid );
}

/** Daily: every linked event's dates move forward with its booking (and last month's drop off). */
function sp_book_events_roll(): void {
	if ( ! sp_book_events_ready() ) return;
	$ids = get_posts( [
		'post_type' => 'events', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1,
		'meta_query' => [ [ 'key' => '_bp_book_service_id', 'compare' => 'EXISTS' ] ],
	] );
	foreach ( $ids as $id ) sp_book_event_pull_dates( (int) $id );
}


/*--------------------------------------------------------------
# Duplicating a booking
--------------------------------------------------------------*/

/**
 * Copy a booking's SETTINGS (price, seats, wording, series, who delivers it) into a new booking —
 * no dates and no sign-ups, and not linked to any event. Returns the new id or 0.
 */
function sp_book_duplicate_service( int $service_id, string $name = '' ): int {
	global $wpdb;
	$svc = sp_book_get_service( $service_id );
	if ( ! $svc ) return 0;
	$row = $svc;
	unset( $row['id'] );
	$now  = sp_book_now();
	$name = $name !== '' ? $name : $svc['name'] . ' (copy)';
	$row['name']       = mb_substr( $name, 0, 150 );
	$row['slug']       = sp_book_unique_slug( $row['name'], 0 );
	$row['created_at'] = $now;
	$row['updated_at'] = $now;
	$meta = json_decode( (string) ( $svc['meta'] ?? '' ), true );
	$meta = is_array( $meta ) ? $meta : [];
	unset( $meta['event_id'] );
	$row['meta'] = wp_json_encode( $meta );
	// Only real columns — sp_book_get_service() may add computed keys.
	$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . sp_book_table( 'services' ) );
	$row  = array_intersect_key( $row, array_flip( $cols ) );
	if ( ! $wpdb->insert( sp_book_table( 'services' ), $row ) ) return 0;
	$new = (int) $wpdb->insert_id;

	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT resource_id, display_order FROM " . sp_book_table( 'service_resources' ) . " WHERE service_id = %d", $service_id ), ARRAY_A ) ?: [] as $l ) {
		$wpdb->insert( sp_book_table( 'service_resources' ), [ 'service_id' => $new, 'resource_id' => (int) $l['resource_id'], 'display_order' => (int) $l['display_order'] ] );
	}
	sp_book_cache_bust();
	return $new;
}


/*--------------------------------------------------------------
# Hooks
--------------------------------------------------------------*/

// wp-admin save: the Booking dropdown (ACF meta `book_service`) → the link. After the date derivations.
add_action( 'acf/save_post', 'sp_book_event_on_acf_save', 30 );
function sp_book_event_on_acf_save( $post_id ): void {
	if ( ! is_numeric( $post_id ) || get_post_type( (int) $post_id ) !== 'events' || ! sp_book_events_ready() ) return;
	if ( ! isset( $_POST['acf']['field_bp_book_service'] ) ) return;   // the field wasn't on the form
	$res = sp_book_event_link( (int) $post_id, (int) get_post_meta( (int) $post_id, 'book_service', true ) );
	// A refused link (already linked elsewhere) leaves the dropdown showing what's actually linked.
	if ( is_wp_error( $res ) ) update_post_meta( (int) $post_id, 'book_service', (int) get_post_meta( (int) $post_id, '_bp_book_service_id', true ) );
}

// Site Pulse save — the editor sends book_service with the event.
add_action( 'sp_events_saved', 'sp_book_event_on_sp_save', 10, 2 );
function sp_book_event_on_sp_save( int $post_id, array $d ): void {
	if ( ! sp_book_events_ready() || ! array_key_exists( 'book_service', $d ) ) return;
	sp_book_event_link( $post_id, (int) $d['book_service'] );
}

// A trashed / deleted event lets go of its booking — the booking (and anyone signed up) stays.
add_action( 'wp_trash_post', 'sp_book_event_on_delete' );
add_action( 'before_delete_post', 'sp_book_event_on_delete' );
function sp_book_event_on_delete( $post_id ): void {
	if ( get_post_type( $post_id ) !== 'events' || ! sp_book_events_ready() ) return;
	$svc = sp_book_event_service( (int) $post_id );
	if ( $svc ) sp_book_service_set_opts( (int) $svc['id'], [ 'event_id' => 0 ] );
	delete_post_meta( (int) $post_id, '_bp_book_service_id' );
}

// One-time: events from the old model that had booking switched OFF keep no link (their hidden
// service had been set inactive). Bookable ones are already linked and need nothing.
/**
 * Once (2026-11-21): the old event model switched an event's booking to INACTIVE whenever the event
 * was saved as a draft, and nothing switched it back — so a linked booking (often a free RSVP) could
 * sit inactive and refuse sign-ups. Reactivate every booking that's linked to an event and still has
 * upcoming dates. Inactive now only ever means someone turned it off.
 */
add_action( 'init', 'sp_book_events_reactivate_linked', 41 );
function sp_book_events_reactivate_linked(): void {
	if ( get_option( 'sp_book_reactivate_linked_v1' ) || ! sp_book_events_ready() ) return;
	if ( get_option( 'site_pulse_booking_db_version' ) !== SITE_PULSE_BOOKING_DB_VERSION ) return;
	update_option( 'sp_book_reactivate_linked_v1', 1, false );
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT s.* FROM " . sp_book_table( 'services' ) . " s JOIN " . sp_book_table( 'sessions' ) . " d ON d.service_id = s.id
		 WHERE s.status = 'inactive' AND s.mode = 'session' AND d.status = 'scheduled' AND d.starts_at > %s",
		sp_book_now()
	), ARRAY_A ) ?: [];
	$n = 0;
	foreach ( $rows as $svc ) {
		if ( ! sp_book_service_event( $svc ) ) continue;
		$wpdb->update( sp_book_table( 'services' ), [ 'status' => 'active', 'updated_at' => sp_book_now() ], [ 'id' => (int) $svc['id'] ] );
		$n++;
	}
	if ( $n ) sp_book_cache_bust();
}

add_action( 'init', 'sp_book_events_migrate_links', 40 );
function sp_book_events_migrate_links(): void {
	if ( get_option( 'sp_book_events_linked_v2' ) || ! sp_book_events_ready() ) return;
	if ( get_option( 'site_pulse_booking_db_version' ) !== SITE_PULSE_BOOKING_DB_VERSION ) return;
	update_option( 'sp_book_events_linked_v2', 1, false );
	$ids = get_posts( [ 'post_type' => 'events', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1,
		'meta_query' => [ [ 'key' => '_bp_book_service_id', 'compare' => 'EXISTS' ] ] ] );
	foreach ( $ids as $id ) {
		// Only events from the old model carry book_enabled; an event linked the new way is left alone.
		if ( ! metadata_exists( 'post', $id, 'book_enabled' ) ) continue;
		if ( ! get_post_meta( $id, 'book_enabled', true ) ) sp_book_event_link( (int) $id, 0 );
		else update_post_meta( $id, 'book_service', (int) get_post_meta( $id, '_bp_book_service_id', true ) );
	}
}


/*--------------------------------------------------------------
# wp-admin field
--------------------------------------------------------------*/

// The Booking dropdown, added to the Events field group only where booking can happen.
add_action( 'acf/init', 'sp_book_event_acf_fields', 20 );
function sp_book_event_acf_fields(): void {
	if ( ! function_exists( 'acf_add_local_field' ) || ! sp_book_events_ready() ) return;
	acf_add_local_field( [
		'parent'        => 'group_6478d57ca3a2e',
		'key'           => 'field_bp_book_service',
		'label'         => 'Booking',
		'name'          => 'book_service',
		'type'          => 'select',
		'choices'       => [],   // filled per request below
		'allow_null'    => 1,
		'ui'            => 0,
		'required'      => 0,
		'instructions'  => 'Let people sign up for this event through an offering from Site Pulse → Booking → Offerings (a free RSVP is an offering with no price). The offering\'s dates replace the dates above when you save.',
	] );
}

add_filter( 'acf/load_field/key=field_bp_book_service', 'sp_book_event_acf_choices' );
function sp_book_event_acf_choices( $field ) {
	$choices = [ '' => '— None —' ];
	foreach ( sp_book_linkable_services() as $s ) {
		$choices[ $s['id'] ] = $s['name'] . ' (' . $s['price'] . ( $s['status'] !== 'active' ? ', inactive' : '' ) . ')'
			. ( $s['event_title'] !== '' ? ' — linked to "' . $s['event_title'] . '"' : '' );
	}
	$field['choices'] = $choices;
	return $field;
}

// Show what's actually linked, whatever the field last stored.
add_filter( 'acf/load_value/key=field_bp_book_service', 'sp_book_event_acf_value', 10, 2 );
function sp_book_event_acf_value( $value, $post_id ) {
	return is_numeric( $post_id ) ? ( (int) get_post_meta( (int) $post_id, '_bp_book_service_id', true ) ?: '' ) : $value;
}


/*--------------------------------------------------------------
# Read helpers (editor + calendar)
--------------------------------------------------------------*/

/** Seats sold / offered across a linked event's upcoming dates, for the Site Pulse list. */
function sp_book_event_summary( int $post_id ): ?array {
	if ( ! sp_book_events_ready() ) return null;
	$svc = sp_book_event_service( $post_id );
	if ( ! $svc ) return null;
	global $wpdb;
	$base = [ 'name' => (string) $svc['name'], 'active' => $svc['status'] === 'active' ];
	// A series sells one seat across every date, so "8 of 10 enrolled" — not the per-date sums.
	if ( sp_book_service_opts( $svc )['series'] ) {
		$st  = sp_book_series_state( $svc );
		$cap = $st['sessions'] ? (int) $st['sessions'][0]['capacity'] : 0;
		return $base + [ 'dates' => count( $st['sessions'] ), 'taken' => $cap - $st['left'], 'capacity' => $cap, 'series' => true, 'started' => $st['started'] ];
	}
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) AS dates, COALESCE(SUM(seats_taken),0) AS taken, COALESCE(SUM(capacity),0) AS cap
		 FROM " . sp_book_table( 'sessions' ) . " WHERE service_id = %d AND status = 'scheduled' AND starts_at > %s",
		(int) $svc['id'], sp_book_now()
	), ARRAY_A );
	return $base + [ 'dates' => (int) $row['dates'], 'taken' => (int) $row['taken'], 'capacity' => (int) $row['cap'] ];
}

/** Upcoming dates of a linked event's booking, with seats (the editor's "who's booked" view). */
function sp_book_event_dates( int $post_id ): array {
	$svc = sp_book_event_service( $post_id );
	if ( ! $svc ) return [];
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_book_table( 'sessions' ) . " WHERE service_id = %d AND status = 'scheduled' AND starts_at > %s ORDER BY starts_at LIMIT 100",
		(int) $svc['id'], sp_book_now()
	), ARRAY_A ) ?: [];
	return array_map( 'sp_book_session_shape', $rows );
}
