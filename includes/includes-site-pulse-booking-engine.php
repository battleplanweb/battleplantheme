<?php
/* Battle Plan Web Design — Site Pulse: Booking Engine

/*--------------------------------------------------------------
The two halves of any booking system that have to be right:

  1. AVAILABILITY — given a service and a date range, what can be booked?
     One entry point, sp_book_slots(), with a resolver per mode. The whole
     computation is interval algebra over UTC timestamps: build the open
     windows from the availability rules, subtract blackouts, subtract what
     is already booked (plus buffers), then lay a grid over what is left.

  2. THE CLAIM — turning an offered slot into a row without ever selling the
     same slot twice. sp_book_claim() is the ONLY function that may create a
     booking. Availability is advisory (it is computed from a snapshot and is
     stale the moment it is returned); the claim is authoritative, and it
     re-verifies everything inside a transaction before it commits.

Everything here is stored and computed in UTC. Availability RULES are stored
as local wall-clock (a trainer works "9am Tuesdays", not "14:00 UTC Tuesdays"),
so each day's windows are materialised by converting that day's local times to
UTC timestamps — which keeps the arithmetic correct across DST boundaries
instead of assuming every day is 1440 minutes long.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Record Helpers
# Cache
# Interval Algebra
# Availability Rules → Open Windows
# Busy Time (existing bookings)
# Slot Resolvers (appointment | session | stay)
# sp_book_slots() — the public entry point
# The Claim (transactional)
# Cancel / Release / Hold Expiry
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Record Helpers
--------------------------------------------------------------*/

function sp_book_get_service( int $id ): ?array {
	global $wpdb;
	if ( ! $id ) return null;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'services' ) . " WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

function sp_book_get_service_by_slug( string $slug ): ?array {
	global $wpdb;
	if ( $slug === '' ) return null;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'services' ) . " WHERE slug = %s", $slug ), ARRAY_A );
	return $row ?: null;
}

function sp_book_get_resource( int $id ): ?array {
	global $wpdb;
	if ( ! $id ) return null;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'resources' ) . " WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

function sp_book_get_session( int $id ): ?array {
	global $wpdb;
	if ( ! $id ) return null;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'sessions' ) . " WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

function sp_book_get_booking( int $id ): ?array {
	global $wpdb;
	if ( ! $id ) return null;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'bookings' ) . " WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

/** Active services, display order then name. */
function sp_book_active_services(): array {
	global $wpdb;
	return $wpdb->get_results(
		"SELECT * FROM " . sp_book_table( 'services' ) . " WHERE status = 'active' ORDER BY display_order ASC, name ASC",
		ARRAY_A
	) ?: [];
}

/**
 * The active resources that can deliver a service, in preference order. A
 * service with NO explicit resource links falls back to every active resource —
 * so a one-person business (the Unscripted Fitness case) never has to wire the
 * join table up before it can take a booking.
 */
function sp_book_service_resources( int $service_id ): array {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT r.* FROM " . sp_book_table( 'resources' ) . " r
		 INNER JOIN " . sp_book_table( 'service_resources' ) . " sr ON sr.resource_id = r.id
		 WHERE sr.service_id = %d AND r.status = 'active'
		 ORDER BY sr.display_order ASC, r.display_order ASC, r.name ASC",
		$service_id
	), ARRAY_A ) ?: [];

	if ( $rows ) return $rows;

	return $wpdb->get_results(
		"SELECT * FROM " . sp_book_table( 'resources' ) . " WHERE status = 'active' ORDER BY display_order ASC, name ASC",
		ARRAY_A
	) ?: [];
}

/**
 * Does this booking occupy a wall-clock slot on its resource (and therefore
 * need the slot_guard), or is it one seat among many in a scheduled session?
 *
 * That is the ONLY distinction that matters for concurrency, and it is decided
 * by whether the booking has a session — never by resources.capacity. A studio
 * room seating 12 still hosts one private lesson at a time; its capacity sets
 * the default seat count of classes held there, not how many separate
 * appointments can run in it at once.
 */
function sp_book_is_timed_booking( ?array $booking ): bool {
	return ! $booking || (int) ( $booking['session_id'] ?? 0 ) === 0;
}

/** The timezone a resource's wall-clock rules are expressed in. */
function sp_book_resource_tz( ?array $resource ): DateTimeZone {
	return sp_book_tz( $resource ? (string) $resource['timezone'] : '' );
}


/*--------------------------------------------------------------
# Cache
--------------------------------------------------------------*/

/**
 * Availability is expensive to compute and read far more often than it changes,
 * so computed day-slots are cached. Rather than tracking which keys a write
 * invalidates (and getting it subtly wrong), every cached key carries a global
 * version number that any write bumps — so a stale entry can never be read, it
 * simply becomes unreachable and expires on its own.
 */
function sp_book_cache_version(): int {
	return (int) sp_book_get_setting( 'cache_version', '1' );
}

function sp_book_cache_bust(): void {
	sp_book_set_setting( 'cache_version', (string) ( sp_book_cache_version() + 1 ) );
}

function sp_book_cache_key( string $suffix ): string {
	return 'sp_book_' . sp_book_cache_version() . '_' . md5( $suffix );
}


/*--------------------------------------------------------------
# Interval Algebra
--------------------------------------------------------------*/

/**
 * Intervals are [start_ts, end_ts] pairs of UTC unix timestamps, half-open:
 * a booking ending exactly when the next begins does NOT overlap it.
 */

/** Sort and merge overlapping/adjacent intervals into a minimal set. */
function sp_book_intervals_merge( array $intervals ): array {
	$intervals = array_values( array_filter( $intervals, fn( $i ) => $i[1] > $i[0] ) );
	if ( ! $intervals ) return [];

	usort( $intervals, fn( $a, $b ) => $a[0] <=> $b[0] );

	$out = [ array_shift( $intervals ) ];
	foreach ( $intervals as $iv ) {
		$last = count( $out ) - 1;
		if ( $iv[0] <= $out[ $last ][1] ) {
			$out[ $last ][1] = max( $out[ $last ][1], $iv[1] );
		} else {
			$out[] = $iv;
		}
	}
	return $out;
}

/** Everything in $base that is not in $cut. Both are merged first. */
function sp_book_intervals_subtract( array $base, array $cut ): array {
	$base = sp_book_intervals_merge( $base );
	$cut  = sp_book_intervals_merge( $cut );
	if ( ! $cut ) return $base;

	$out = [];
	foreach ( $base as $b ) {
		$pieces = [ $b ];
		foreach ( $cut as $c ) {
			$next = [];
			foreach ( $pieces as $p ) {
				if ( $c[1] <= $p[0] || $c[0] >= $p[1] ) {   // no overlap
					$next[] = $p;
					continue;
				}
				if ( $c[0] > $p[0] ) $next[] = [ $p[0], $c[0] ];   // remainder before the cut
				if ( $c[1] < $p[1] ) $next[] = [ $c[1], $p[1] ];   // remainder after the cut
			}
			$pieces = $next;
			if ( ! $pieces ) break;
		}
		foreach ( $pieces as $p ) $out[] = $p;
	}
	return sp_book_intervals_merge( $out );
}

/** Is [$start,$end) fully contained in one of $windows? */
function sp_book_interval_fits( array $windows, int $start, int $end ): bool {
	foreach ( $windows as $w ) {
		if ( $start >= $w[0] && $end <= $w[1] ) return true;
	}
	return false;
}

/** A local date + 'HH:MM:SS' → UTC unix timestamp, DST-correct. */
function sp_book_local_ts( string $date, string $time, DateTimeZone $tz ): int {
	try {
		$dt = new DateTime( $date . ' ' . substr( $time . ':00:00', 0, 8 ), $tz );
	} catch ( Exception $e ) {
		return 0;
	}
	return $dt->getTimestamp();
}

/** A stored UTC datetime string → unix timestamp. */
function sp_book_utc_ts( string $utc ): int {
	if ( $utc === '' || $utc === '0000-00-00 00:00:00' ) return 0;
	return (int) strtotime( $utc . ' UTC' );
}


/*--------------------------------------------------------------
# Availability Rules → Open Windows
--------------------------------------------------------------*/

/**
 * Every rule that could apply to this resource + service, loaded once per
 * request. Rules are few (a weekly template plus exceptions), so one read and
 * filtering in PHP beats a query per day of the requested range.
 */
function sp_book_rules( int $resource_id, int $service_id ): array {
	static $cache = [];
	$key = $resource_id . ':' . $service_id;
	if ( isset( $cache[ $key ] ) ) return $cache[ $key ];

	global $wpdb;
	$cache[ $key ] = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_book_table( 'rules' ) . "
		 WHERE ( resource_id = %d OR resource_id = 0 )
		   AND ( service_id  = %d OR service_id  = 0 )
		 ORDER BY id ASC",
		$resource_id, $service_id
	), ARRAY_A ) ?: [];

	return $cache[ $key ];
}

/** Does a rule's optional start_date/end_date window contain this date? */
function sp_book_rule_covers_date( array $rule, string $date ): bool {
	$from = (string) ( $rule['start_date'] ?? '' );
	$to   = (string) ( $rule['end_date'] ?? '' );
	if ( $from !== '' && $from !== '0000-00-00' && $date < $from ) return false;
	if ( $to   !== '' && $to   !== '0000-00-00' && $date > $to )   return false;
	return true;
}

/**
 * The OPEN windows for one resource on one local date, before existing bookings
 * are subtracted. Returns UTC interval pairs.
 *
 * Precedence, and the reason for it:
 *   - `hours` rules are the weekly template. If the resource has ANY hours
 *     rules of its own, ONLY those are used — the company-wide (resource_id 0)
 *     template is ignored entirely for that resource. Falling back per-weekday
 *     instead would silently give a Mon–Fri trainer the company's Saturday
 *     hours, which is the opposite of what "she set her own schedule" means.
 *   - `open` rules ADD availability on specific dates (a one-off Saturday).
 *   - `blackout` rules REMOVE time, and always union across both scopes — a
 *     company holiday and a personal day off both apply.
 */
function sp_book_open_windows( array $service, int $resource_id, string $date ): array {
	$resource = sp_book_get_resource( $resource_id );
	$tz       = sp_book_resource_tz( $resource );
	$rules    = sp_book_rules( $resource_id, (int) $service['id'] );

	$weekday = (int) ( new DateTime( $date . ' 12:00:00', $tz ) )->format( 'w' );   // 0=Sun

	// Does this resource own any hours rules at all?
	$has_own_hours = false;
	foreach ( $rules as $r ) {
		if ( $r['rule_type'] === 'hours' && (int) $r['resource_id'] === $resource_id && $resource_id !== 0 ) {
			$has_own_hours = true;
			break;
		}
	}

	$open  = [];
	$close = [];

	foreach ( $rules as $r ) {
		if ( ! sp_book_rule_covers_date( $r, $date ) ) continue;

		$type = (string) $r['rule_type'];
		$mine = ( (int) $r['resource_id'] === $resource_id && $resource_id !== 0 );

		if ( $type === 'hours' ) {
			if ( $has_own_hours && ! $mine ) continue;                       // the resource's own template wins outright
			if ( $r['weekday'] === null || (int) $r['weekday'] !== $weekday ) continue;
			$open[] = [ sp_book_local_ts( $date, (string) $r['start_time'], $tz ), sp_book_local_ts( $date, (string) $r['end_time'], $tz ) ];

		} elseif ( $type === 'open' ) {
			// Date-scoped extra availability. A weekday of NULL means "the dates
			// in this rule's range"; a set weekday narrows it to that weekday.
			if ( $r['weekday'] !== null && (int) $r['weekday'] !== $weekday ) continue;
			$open[] = [ sp_book_local_ts( $date, (string) $r['start_time'], $tz ), sp_book_local_ts( $date, (string) $r['end_time'], $tz ) ];

		} elseif ( $type === 'blackout' ) {
			if ( $r['weekday'] !== null && (int) $r['weekday'] !== $weekday ) continue;
			if ( (int) $r['all_day'] === 1 || ! $r['start_time'] || ! $r['end_time'] ) {
				// Local midnight to local midnight — computed, not +86400, so a
				// 23- or 25-hour DST day is still covered exactly.
				$midnight = new DateTime( $date . ' 00:00:00', $tz );
				$next     = ( clone $midnight )->modify( '+1 day' );
				$close[]  = [ $midnight->getTimestamp(), $next->getTimestamp() ];
			} else {
				$close[] = [ sp_book_local_ts( $date, (string) $r['start_time'], $tz ), sp_book_local_ts( $date, (string) $r['end_time'], $tz ) ];
			}
		}
	}

	return sp_book_intervals_subtract( $open, $close );
}


/*--------------------------------------------------------------
# Busy Time (existing bookings)
--------------------------------------------------------------*/

/**
 * The SQL condition for "this booking still occupies its slot", plus its bound
 * parameters. An active status is not enough on its own: a `pending` hold whose
 * hold_expires_at has passed is abandoned checkout, and must stop blocking the
 * slot the moment it lapses rather than waiting for the sweeper to notice. That
 * is what lets sp_book_expire_holds() be a lazy sweep instead of a promise to
 * run at an exact minute — which this framework's traffic-triggered cron could
 * not keep anyway.
 */
function sp_book_active_sql( string $alias = '' ): array {
	$p        = $alias !== '' ? $alias . '.' : '';
	$statuses = sp_book_active_statuses();
	$ph       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

	$sql = "( {$p}status IN ( $ph ) AND ( {$p}status <> 'pending' OR {$p}hold_expires_at IS NULL OR {$p}hold_expires_at > %s ) )";

	return [ $sql, array_merge( $statuses, [ sp_book_now() ] ) ];
}

/**
 * The intervals a resource is already committed for, across a UTC timestamp
 * range, PADDED by the service's buffers. The padding is applied to the busy
 * side rather than the slot side so that a 30-minute drive-time buffer keeps
 * the next slot clear without also shortening the working day at both ends.
 *
 * Appointment and stay bookings are treated as EXCLUSIVE regardless of the
 * resource's capacity: `capacity` describes how many people fit in a scheduled
 * session, not how many 1:1 appointments a resource can run at once. A studio
 * room seating 12 still hosts one private lesson at a time. Overlapping
 * appointments on one resource are therefore never offered.
 */
function sp_book_busy_intervals( array $service, int $resource_id, int $from_ts, int $to_ts, int $ignore_booking_id = 0, ?array $dest = null ): array {
	global $wpdb;

	$pad_before = ( (int) $service['buffer_before_min'] ) * 60;
	$pad_after  = ( (int) $service['buffer_after_min'] ) * 60;

	// Travel buffers only engage for an on-location service when we know where the NEW booking
	// is going. Without a destination there is nothing to measure against, so the flat buffer
	// stands — which is also the fallback for a missing or unresolvable address.
	$travel = $dest && sp_book_travel_enabled( $service );
	$base   = $travel ? sp_book_resource_point( sp_book_get_resource( $resource_id ) ) : null;

	[ $active_sql, $active_params ] = sp_book_active_sql();

	$params = array_merge(
		[ $resource_id ],
		$active_params,
		// The 24h slack already covers any buffer up to the travel cap, so a distant neighbour
		// is still fetched and can still push a slot out.
		[ gmdate( 'Y-m-d H:i:s', $to_ts + $pad_before + 86400 ), gmdate( 'Y-m-d H:i:s', $from_ts - $pad_after - 86400 ) ]
	);

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, starts_at, ends_at, lat, lng FROM " . sp_book_table( 'bookings' ) . "
		 WHERE resource_id = %d AND $active_sql
		   AND starts_at < %s AND ends_at > %s",
		$params
	), ARRAY_A ) ?: [];

	$busy = [];
	foreach ( $rows as $row ) {
		if ( $ignore_booking_id && (int) $row['id'] === $ignore_booking_id ) continue;
		$s = sp_book_utc_ts( (string) $row['starts_at'] );
		$e = sp_book_utc_ts( (string) $row['ends_at'] );
		if ( ! $s || ! $e ) continue;

		$before = $pad_before;
		$after  = $pad_after;

		if ( $travel ) {
			// Directional, and both directions matter: getting FROM this booking TO the new one
			// governs the gap after it, and getting from the new one BACK here governs the gap
			// before it. The floor is passed in so a pair that cannot beat the minimum never
			// reaches the API at all.
			$here = sp_book_booking_point( $row );
			$to   = sp_book_travel_minutes( $here, $dest, (int) $service['buffer_before_min'] );
			$back = sp_book_travel_minutes( $dest, $here, (int) $service['buffer_after_min'] );
			if ( $to   !== null ) $before = max( $before, $to * 60 );
			if ( $back !== null ) $after  = max( $after,  $back * 60 );
		}

		$busy[] = [ $s - $after, $e + $before ];
	}

	// Session-mode instances also occupy an exclusive resource's calendar — a
	// class in the studio blocks a 1:1 in the same room even though the class
	// itself is seat-limited rather than wall-clock-limited.
	$sessions = $wpdb->get_results( $wpdb->prepare(
		"SELECT starts_at, ends_at FROM " . sp_book_table( 'sessions' ) . "
		 WHERE resource_id = %d AND status = 'scheduled' AND starts_at < %s AND ends_at > %s",
		$resource_id,
		gmdate( 'Y-m-d H:i:s', $to_ts + 86400 ),
		gmdate( 'Y-m-d H:i:s', $from_ts - 86400 )
	), ARRAY_A ) ?: [];

	foreach ( $sessions as $row ) {
		$s = sp_book_utc_ts( (string) $row['starts_at'] );
		$e = sp_book_utc_ts( (string) $row['ends_at'] );
		if ( $s && $e ) $busy[] = [ $s - $pad_after, $e + $pad_before ];
	}

	return sp_book_intervals_merge( $busy );
}

/** Open windows minus busy time — what is genuinely bookable on that date. */
function sp_book_free_windows( array $service, int $resource_id, string $date, int $ignore_booking_id = 0, ?array $dest = null ): array {
	$open = sp_book_open_windows( $service, $resource_id, $date );
	if ( ! $open ) return [];

	$from_ts = $open[0][0];
	$to_ts   = $open[ count( $open ) - 1 ][1];

	return sp_book_intervals_subtract( $open, sp_book_busy_intervals( $service, $resource_id, $from_ts, $to_ts, $ignore_booking_id, $dest ) );
}


/*--------------------------------------------------------------
# Slot Resolvers
--------------------------------------------------------------*/

/**
 * The earliest and latest bookable moments, from the service's own policy.
 *
 * $ignore lifts both limits. Minimum notice and maximum advance are CUSTOMER-facing
 * rules — they stop someone booking a 6am slot at 5:55am off the website. They are
 * not constraints on the business booking its own day: when a client rings and asks
 * for this afternoon, staff must be able to put it in. Staff paths pass true here;
 * the public widget never will.
 */
function sp_book_policy_bounds( array $service, bool $ignore = false ): array {
	if ( $ignore ) return [ 0, PHP_INT_MAX ];
	$now = time();
	return [
		$now + ( (int) $service['lead_time_min'] ) * 60,
		$now + ( (int) $service['max_advance_days'] ) * DAY_IN_SECONDS,
	];
}

/**
 * APPOINTMENT MODE — lay a grid over each free window.
 *
 * The grid is aligned to the START OF EACH WINDOW, not to the top of the hour:
 * a window that opens at 8:15 with a 30-minute interval offers 8:15, 8:45, …
 * That is what "she starts at quarter past" should mean, and it keeps a short
 * window (a 90-minute gap between two jobs) usable instead of rounding it away.
 */
function sp_book_slots_appointment( array $service, array $resources, array $dates, bool $ignore_policy = false, ?array $dest = null ): array {
	$duration = max( 1, (int) $service['duration_min'] ) * 60;
	$interval = max( 1, (int) $service['slot_interval_min'] ) * 60;
	[ $earliest, $latest ] = sp_book_policy_bounds( $service, $ignore_policy );

	$slots = [];
	foreach ( $resources as $resource ) {
		$rid = (int) $resource['id'];
		foreach ( $dates as $date ) {
			foreach ( sp_book_free_windows( $service, $rid, $date, 0, $dest ) as $w ) {
				for ( $start = $w[0]; $start + $duration <= $w[1]; $start += $interval ) {
					if ( $start < $earliest || $start > $latest ) continue;
					$slots[] = [
						'starts_at'   => gmdate( 'Y-m-d H:i:s', $start ),
						'ends_at'     => gmdate( 'Y-m-d H:i:s', $start + $duration ),
						'resource_id' => $rid,
						'session_id'  => 0,
						'seats_left'  => 1,
					];
				}
			}
		}
	}
	return $slots;
}

/**
 * SESSION MODE — concrete instances that already exist, with their remaining
 * seats. Nothing is generated here; the recurrence roll-forward creates the
 * session rows, and this only reports what is on the schedule and not yet full.
 */
function sp_book_slots_session( array $service, array $resources, int $from_ts, int $to_ts, bool $ignore_policy = false ): array {
	global $wpdb;
	[ $earliest, $latest ] = sp_book_policy_bounds( $service, $ignore_policy );

	$rid_filter = '';
	$params     = [ (int) $service['id'], gmdate( 'Y-m-d H:i:s', $from_ts ), gmdate( 'Y-m-d H:i:s', $to_ts ) ];

	if ( $resources ) {
		$ids        = array_map( fn( $r ) => (int) $r['id'], $resources );
		$rid_filter = ' AND ( resource_id = 0 OR resource_id IN ( ' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ' ) )';
		$params     = array_merge( $params, $ids );
	}

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_book_table( 'sessions' ) . "
		 WHERE service_id = %d AND status = 'scheduled' AND starts_at >= %s AND starts_at <= %s" . $rid_filter . "
		 ORDER BY starts_at ASC",
		$params
	), ARRAY_A ) ?: [];

	$slots = [];
	foreach ( $rows as $row ) {
		$start = sp_book_utc_ts( (string) $row['starts_at'] );
		if ( $start < $earliest || $start > $latest ) continue;

		$seats_left = max( 0, (int) $row['capacity'] - (int) $row['seats_taken'] );
		$slots[] = [
			'starts_at'   => (string) $row['starts_at'],
			'ends_at'     => (string) $row['ends_at'],
			'resource_id' => (int) $row['resource_id'],
			'session_id'  => (int) $row['id'],
			'seats_left'  => $seats_left,
			'capacity'    => (int) $row['capacity'],
			'waitlist'    => (int) $row['waitlist_count'],
		];
	}
	return $slots;
}

/**
 * STAY MODE — day granularity. A date is offered as a possible ARRIVAL when the
 * unit is free for at least min_units consecutive days from it. Per-night rate
 * calendars and channel restrictions are not modelled yet; this answers only
 * "can someone start a stay here", which is what the calendar needs to paint.
 */
function sp_book_slots_stay( array $service, array $resources, array $dates, bool $ignore_policy = false ): array {
	$min_units = max( 1, (int) $service['min_units'] );
	[ $earliest, $latest ] = sp_book_policy_bounds( $service, $ignore_policy );

	$slots = [];
	foreach ( $resources as $resource ) {
		$rid = (int) $resource['id'];
		$tz  = sp_book_resource_tz( $resource );

		// One free/busy pass over the whole span, then walk the dates.
		$free_by_date = [];
		foreach ( $dates as $date ) {
			$day_start = sp_book_local_ts( $date, '00:00:00', $tz );
			$day_end   = sp_book_local_ts( $date, '00:00:00', $tz ) + DAY_IN_SECONDS;
			$open      = sp_book_open_windows( $service, $rid, $date );
			$busy      = sp_book_busy_intervals( $service, $rid, $day_start, $day_end );
			$free_by_date[ $date ] = ( $open || $busy )
				? (bool) sp_book_intervals_subtract( $open ?: [ [ $day_start, $day_end ] ], $busy )
				: true;
		}

		foreach ( $dates as $i => $date ) {
			$start = sp_book_local_ts( $date, '00:00:00', $tz );
			if ( $start < $earliest || $start > $latest ) continue;

			$run_ok = true;
			for ( $n = 0; $n < $min_units; $n++ ) {
				$d = $dates[ $i + $n ] ?? null;
				if ( $d === null || empty( $free_by_date[ $d ] ) ) { $run_ok = false; break; }
			}
			if ( ! $run_ok ) continue;

			$slots[] = [
				'starts_at'   => gmdate( 'Y-m-d H:i:s', $start ),
				'ends_at'     => gmdate( 'Y-m-d H:i:s', $start + $min_units * DAY_IN_SECONDS ),
				'resource_id' => $rid,
				'session_id'  => 0,
				'seats_left'  => 1,
				'units'       => $min_units,
			];
		}
	}
	return $slots;
}


/*--------------------------------------------------------------
# sp_book_slots() — the public entry point
--------------------------------------------------------------*/

/**
 * Everything bookable for a service between two LOCAL dates (inclusive).
 * Returns slots sorted by time, each with the UTC start/end, the resource that
 * would serve it, and how many seats remain.
 *
 * This is ADVISORY. It is a snapshot, and another customer may claim a slot
 * between this call and a checkout completing — which is exactly why
 * sp_book_claim() re-verifies rather than trusting what was offered.
 */
function sp_book_slots( int $service_id, string $from_date, string $to_date, int $resource_id = 0, bool $ignore_policy = false, ?array $dest = null ): array {
	$service = sp_book_get_service( $service_id );
	if ( ! $service || $service['status'] !== 'active' ) return [];

	// $ignore_policy is part of the key: a staff lookup and a customer lookup for the same
	// service and day return DIFFERENT lists, and sharing one cache entry would leak the
	// unrestricted list to the public widget (or hide today from staff, depending on order).
	// The destination is part of the cache key: with travel buffers on, two customers at
	// different addresses genuinely get different answers for the same day, and sharing one
	// entry between them would hand one of them the other's availability.
	$dest_key  = $dest ? sp_book_geo_key( $dest ) : '-';
	$cache_key = sp_book_cache_key( "slots|$service_id|$from_date|$to_date|$resource_id|" . ( $ignore_policy ? 1 : 0 ) . "|$dest_key" );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) return $cached;

	$resources = sp_book_service_resources( $service_id );
	if ( $resource_id ) {
		$resources = array_values( array_filter( $resources, fn( $r ) => (int) $r['id'] === $resource_id ) );
	}

	// Cap the span so a malformed range can't walk a decade of days.
	$dates = [];
	try {
		$cursor = new DateTime( $from_date . ' 00:00:00', new DateTimeZone( 'UTC' ) );
		$stop   = new DateTime( $to_date . ' 00:00:00', new DateTimeZone( 'UTC' ) );
	} catch ( Exception $e ) {
		return [];
	}
	$guard = 0;
	while ( $cursor <= $stop && $guard < 400 ) {
		$dates[] = $cursor->format( 'Y-m-d' );
		$cursor->modify( '+1 day' );
		$guard++;
	}
	if ( ! $dates ) return [];

	switch ( (string) $service['mode'] ) {
		case 'session':
			$slots = sp_book_slots_session(
				$service,
				$resources,
				sp_book_local_ts( $dates[0], '00:00:00', sp_book_tz() ),
				sp_book_local_ts( end( $dates ), '00:00:00', sp_book_tz() ) + DAY_IN_SECONDS,
				$ignore_policy
			);
			break;
		case 'stay':
			$slots = sp_book_slots_stay( $service, $resources, $dates, $ignore_policy );
			break;
		default:
			// An appointment service can sell either an exact start time or an arrival window;
			// the mode decides which resolver answers, and everything downstream is identical.
			$slots = ( (string) ( $service['slot_mode'] ?? 'exact' ) === 'window' )
				? sp_book_slots_window( $service, $resources, $dates, $ignore_policy, $dest )
				: sp_book_slots_appointment( $service, $resources, $dates, $ignore_policy, $dest );
	}

	usort( $slots, fn( $a, $b ) => [ $a['starts_at'], $a['resource_id'] ] <=> [ $b['starts_at'], $b['resource_id'] ] );

	// Proximity dispatch: score each offer by how much driving it adds, keep the cheapest
	// resource per time, and apply the out-of-area policy. Runs before the cache is written,
	// so the stored list is exactly what the customer will be shown.
	$slots = sp_book_dispatch_rank( $service, $slots, $dest );

	/**
	 * Last word on availability, for anything the rules engine can't express —
	 * a service-area radius check, a CRM's own busy feed, a per-client rule.
	 */
	$slots = apply_filters( 'sp_book_slots', $slots, $service, $resources, $dates );

	set_transient( $cache_key, $slots, 5 * MINUTE_IN_SECONDS );
	return $slots;
}

/** Convenience: the slots for a single local date. */
function sp_book_slots_for_date( int $service_id, string $date, int $resource_id = 0, bool $ignore_policy = false, ?array $dest = null ): array {
	return sp_book_slots( $service_id, $date, $date, $resource_id, $ignore_policy, $dest );
}


/*--------------------------------------------------------------
# The Claim (transactional)
--------------------------------------------------------------*/

/**
 * Create a booking, or fail. THE ONLY supported way to create one.
 *
 * $args:
 *   service_id   (required)
 *   starts_at    UTC 'Y-m-d H:i:s' — required for appointment/stay
 *   session_id   required for session mode
 *   resource_id  0 = pick the first resource that is actually free
 *   party_size   seats (session mode) — default 1
 *   units        nights (stay mode) — default the service's min_units
 *   status       'pending' (a hold, the default) or 'confirmed'
 *   hold_minutes how long a pending hold survives — default 10
 *   source, created_by, customer_id, customer_name/email/phone,
 *   address/city/state/zip, notes, intake (array), pass_id
 *
 * Returns the created booking row, or WP_Error:
 *   invalid_service · invalid_slot · slot_taken · session_full · outside_policy
 *
 * On WP Engine (InnoDB) the appointment path holds a real transaction with
 * SELECT … FOR UPDATE over the overlapping range, so a concurrent claim blocks
 * rather than racing. The UNIQUE slot_guard index is the belt to that braces:
 * it cannot express "overlaps" (no partial/exclusion indexes in MySQL), but it
 * makes the common case — two people taking the same gridded start time — a
 * hard database error rather than a matter of timing.
 */
function sp_book_claim( array $args ) {
	global $wpdb;

	$service_id = (int) ( $args['service_id'] ?? 0 );
	$service    = sp_book_get_service( $service_id );
	if ( ! $service || $service['status'] !== 'active' ) {
		return new WP_Error( 'invalid_service', 'That service is not available for booking.' );
	}

	$mode = (string) $service['mode'];
	$now  = sp_book_now();

	$base = [
		'service_id'     => $service_id,
		'status'         => in_array( (string) ( $args['status'] ?? 'pending' ), [ 'pending', 'confirmed' ], true ) ? (string) $args['status'] : 'pending',
		'source'         => in_array( (string) ( $args['source'] ?? 'web' ), array_keys( sp_book_sources() ), true ) ? (string) $args['source'] : 'web',
		'customer_id'    => (int) ( $args['customer_id'] ?? 0 ),
		'customer_name'  => sanitize_text_field( (string) ( $args['customer_name'] ?? '' ) ),
		'customer_email' => sanitize_email( (string) ( $args['customer_email'] ?? '' ) ),
		'customer_phone' => sanitize_text_field( (string) ( $args['customer_phone'] ?? '' ) ),
		'address'        => sanitize_text_field( (string) ( $args['address'] ?? '' ) ),
		'city'           => sanitize_text_field( (string) ( $args['city'] ?? '' ) ),
		'state'          => sanitize_text_field( (string) ( $args['state'] ?? '' ) ),
		'zip'            => sanitize_text_field( (string) ( $args['zip'] ?? '' ) ),
		'notes'          => sanitize_textarea_field( (string) ( $args['notes'] ?? '' ) ),
		'intake'         => isset( $args['intake'] ) ? wp_json_encode( $args['intake'] ) : null,
		'pass_id'        => (int) ( $args['pass_id'] ?? 0 ),
		'party_size'     => max( 1, (int) ( $args['party_size'] ?? 1 ) ),
		'price_cents'    => (int) ( $args['price_cents'] ?? $service['price_cents'] ),
		'deposit_cents'  => (int) ( $args['deposit_cents'] ?? $service['deposit_cents'] ),
		'currency'       => (string) $service['currency'],
		'created_by'     => (int) ( $args['created_by'] ?? 0 ),
		'created_at'     => $now,
		'updated_at'     => $now,
	];

	// A hold only makes sense while a booking is pending; a confirmed booking never expires.
	$hold_minutes = max( 1, (int) ( $args['hold_minutes'] ?? 10 ) );
	$base['hold_expires_at'] = $base['status'] === 'pending'
		? gmdate( 'Y-m-d H:i:s', time() + $hold_minutes * 60 )
		: null;

	// Paying with a pass/membership. Checked BEFORE the slot is claimed so an exhausted
	// allowance fails cleanly instead of leaving a booking nobody has paid for, and redeemed
	// AFTER, because the redemption needs the booking id. The redemption ledger's UNIQUE key on
	// booking_id makes that second step safe even if this is somehow reached twice.
	// Where this booking is going. Geocoded ONCE here — never from the availability path, which
	// runs on every calendar view — and stored on the row so the travel cache can use it later.
	$dest = sp_book_point( $args['lat'] ?? 0, $args['lng'] ?? 0 );
	if ( ! $dest && sp_book_travel_enabled( $service ) ) {
		$dest = sp_book_zip_point( (string) ( $args['zip'] ?? '' ) );
	}
	if ( $dest ) {
		$base['lat'] = $dest[0];
		$base['lng'] = $dest[1];
	}
	$args['_dest'] = $dest;

	$pass_id = (int) ( $args['pass_id'] ?? 0 );
	$pass    = $pass_id ? sp_book_get_pass( $pass_id ) : null;

	if ( $pass_id && ! $pass ) {
		return new WP_Error( 'invalid_pass', 'That membership no longer exists.' );
	}

	if ( $pass ) {
		$start_for_pass = sp_book_utc_ts( (string) ( $args['starts_at'] ?? '' ) );
		if ( ! $start_for_pass && ! empty( $args['session_id'] ) ) {
			$sess = sp_book_get_session( (int) $args['session_id'] );
			$start_for_pass = $sess ? sp_book_utc_ts( (string) $sess['starts_at'] ) : 0;
		}
		[ $covered, $why ] = sp_book_pass_can_cover( $pass, $service_id, $start_for_pass ?: time() );
		if ( ! $covered ) {
			return new WP_Error( 'pass_exhausted', $why );
		}
	}

	if ( $mode === 'session' ) {
		$booking = sp_book_claim_session( $service, $base, $args );
	} elseif ( $mode !== 'stay' && (string) ( $service['slot_mode'] ?? 'exact' ) === 'window' ) {
		$booking = sp_book_claim_window( $service, $base, $args );
	} else {
		$booking = sp_book_claim_timed( $service, $base, $args, $mode );
	}

	if ( ! is_wp_error( $booking ) && $pass ) {
		// An over-limit booking on an `allow`/`charge` membership is deliberately NOT redeemed —
		// it happened, but it must not eat an allowance the customer has already spent.
		if ( sp_book_pass_counts_against( $pass, $service_id, sp_book_utc_ts( (string) $booking['starts_at'] ), (int) $booking['id'] ) ) {
			sp_book_pass_redeem( $pass_id, (int) $booking['id'] );
			$booking = sp_book_get_booking( (int) $booking['id'] );
		}
	}

	return $booking;
}

/**
 * SESSION MODE claim. The concurrency point is one conditional UPDATE: the seat
 * count and the capacity test happen in the same statement, so the database —
 * not the application — decides who gets the last seat. A zero affected-row
 * count IS the "class is full" answer; there is no separate check to race with.
 */
function sp_book_claim_session( array $service, array $base, array $args ) {
	global $wpdb;

	$session_id = (int) ( $args['session_id'] ?? 0 );
	$session    = sp_book_get_session( $session_id );
	if ( ! $session || $session['status'] !== 'scheduled' ) {
		return new WP_Error( 'invalid_slot', 'That class is no longer on the schedule.' );
	}

	[ $earliest, $latest ] = sp_book_policy_bounds( $service, ! empty( $args['ignore_policy'] ) );
	$start_ts = sp_book_utc_ts( (string) $session['starts_at'] );
	if ( $start_ts < $earliest || $start_ts > $latest ) {
		return new WP_Error( 'outside_policy', 'That class is outside the window when it can be booked.' );
	}

	$seats = $base['party_size'];
	$table = sp_book_table( 'sessions' );

	$claimed = $wpdb->query( $wpdb->prepare(
		"UPDATE $table SET seats_taken = seats_taken + %d, updated_at = %s
		 WHERE id = %d AND status = 'scheduled' AND seats_taken + %d <= capacity",
		$seats, sp_book_now(), $session_id, $seats
	) );

	if ( ! $claimed ) {
		return new WP_Error( 'session_full', 'That class is full.' );
	}

	$base['session_id']  = $session_id;
	$base['resource_id'] = (int) $session['resource_id'];
	$base['starts_at']   = (string) $session['starts_at'];
	$base['ends_at']     = (string) $session['ends_at'];
	$base['slot_guard']  = null;   // seat-limited, not slot-limited

	$booking_id = sp_book_insert_booking( $base );
	if ( ! $booking_id ) {
		// Hand the seats back — the reservation must not outlive the failed insert.
		$wpdb->query( $wpdb->prepare(
			"UPDATE $table SET seats_taken = GREATEST(0, seats_taken - %d) WHERE id = %d",
			$seats, $session_id
		) );
		return new WP_Error( 'slot_taken', 'That booking could not be saved. Please try again.' );
	}

	sp_book_cache_bust();
	return sp_book_get_booking( $booking_id );
}

/**
 * APPOINTMENT / STAY claim — the transactional path.
 *
 * Order of operations matters: lock the overlapping range FIRST, then re-verify
 * against freshly-computed availability, then insert. Verifying before locking
 * would leave exactly the window this is meant to close.
 */
function sp_book_claim_timed( array $service, array $base, array $args, string $mode ) {
	global $wpdb;

	$starts_at = (string) ( $args['starts_at'] ?? '' );
	$start_ts  = sp_book_utc_ts( $starts_at );
	if ( ! $start_ts ) {
		return new WP_Error( 'invalid_slot', 'That appointment time is not valid.' );
	}

	if ( $mode === 'stay' ) {
		$min    = max( 1, (int) $service['min_units'] );
		$max    = max( $min, (int) $service['max_units'] );
		$units  = max( $min, min( (int) ( $args['units'] ?? $min ), $max ) );
		$end_ts = $start_ts + $units * DAY_IN_SECONDS;
	} else {
		$units  = 1;
		$end_ts = $start_ts + max( 1, (int) $service['duration_min'] ) * 60;
	}
	$base['units'] = $units;

	[ $earliest, $latest ] = sp_book_policy_bounds( $service, ! empty( $args['ignore_policy'] ) );
	if ( $start_ts < $earliest || $start_ts > $latest ) {
		return new WP_Error( 'outside_policy', 'That time is outside the window when it can be booked.' );
	}

	// Candidate resources, in preference order. A caller-supplied resource
	// narrows it to one; otherwise the first genuinely free resource wins.
	$resources = sp_book_service_resources( (int) $service['id'] );
	$want_rid  = (int) ( $args['resource_id'] ?? 0 );
	if ( $want_rid ) {
		$resources = array_values( array_filter( $resources, fn( $r ) => (int) $r['id'] === $want_rid ) );
	}
	if ( ! $resources ) {
		return new WP_Error( 'invalid_slot', 'No one is available to take that appointment.' );
	}

	// Try the cheapest insertion first. This only reorders the attempts — each is still
	// verified — so a resource that filled up since the calendar was drawn falls through to
	// the next best instead of failing the booking.
	$resources = sp_book_order_resources( $service, $resources, $start_ts, $end_ts, $args['_dest'] ?? null );

	[ $active_sql, $active_params ] = sp_book_active_sql();
	$bookings = sp_book_table( 'bookings' );
	$start_s  = gmdate( 'Y-m-d H:i:s', $start_ts );
	$end_s    = gmdate( 'Y-m-d H:i:s', $end_ts );

	$wpdb->query( 'START TRANSACTION' );

	$was_offered = false;   // did ANY resource have this slot free? distinguishes "gone" from "never offered"

	// The span that must be clear: the booking itself, widened by the buffers so an
	// adjacent job still leaves the drive time the service asks for.
	$pad_before = ( (int) $service['buffer_before_min'] ) * 60;
	$pad_after  = ( (int) $service['buffer_after_min'] ) * 60;

	// With travel buffers on, the required gap depends on WHERE the neighbour is, so the locking
	// read has to reach as far as the largest buffer that could apply — the cap — and each
	// neighbour is then judged individually. Checking only the flat window here would accept a
	// booking the calendar would never have offered, which is the one inconsistency that turns
	// into a missed appointment.
	$dest       = $args['_dest'] ?? null;
	$use_travel = $dest && sp_book_travel_enabled( $service );
	$reach      = $use_travel ? sp_book_travel_cap() * 60 : 0;

	$guard_from = gmdate( 'Y-m-d H:i:s', $start_ts - max( $pad_before, $reach ) );
	$guard_to   = gmdate( 'Y-m-d H:i:s', $end_ts + max( $pad_after, $reach ) );

	foreach ( $resources as $resource ) {
		$rid = (int) $resource['id'];

		// 1. Do the RULES allow this time at all? Pure rule arithmetic — no bookings
		// involved, so it needs no transactional consistency and can run unlocked.
		$date = sp_book_to_local( $start_s, 'Y-m-d', (string) $resource['timezone'] );
		if ( ! sp_book_interval_fits( sp_book_open_windows( $service, $rid, $date ), $start_ts, $end_ts ) ) {
			continue;
		}
		$was_offered = true;

		// 2. Is anything already there? This is a LOCKING read, and its result IS the
		// answer — deliberately not a second plain SELECT afterwards. Under REPEATABLE
		// READ a plain read is served from the transaction's snapshot, which is fixed
		// at the first consistent read; on the second pass of this loop that snapshot
		// predates any row a competing transaction committed in between, so verifying
		// that way would look at stale data while holding perfectly good locks. Reading
		// FOR UPDATE sees the latest committed rows AND gap-locks the range, so a
		// competing INSERT blocks here rather than landing between check and write.
		$neighbours = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, starts_at, ends_at, lat, lng FROM $bookings
			 WHERE resource_id = %d AND $active_sql AND starts_at < %s AND ends_at > %s
			 FOR UPDATE",
			array_merge( [ $rid ], $active_params, [ $guard_to, $guard_from ] )
		) ) ?: [];

		$clash = false;
		foreach ( $neighbours as $n ) {
			$ns = sp_book_utc_ts( (string) $n['starts_at'] );
			$ne = sp_book_utc_ts( (string) $n['ends_at'] );
			if ( ! $ns || ! $ne ) continue;

			$before = $pad_before;
			$after  = $pad_after;
			if ( $use_travel ) {
				$here = sp_book_booking_point( $n );
				$to   = sp_book_travel_minutes( $here, $dest, (int) $service['buffer_before_min'] );
				$back = sp_book_travel_minutes( $dest, $here, (int) $service['buffer_after_min'] );
				if ( $to   !== null ) $before = max( $before, $to * 60 );
				if ( $back !== null ) $after  = max( $after,  $back * 60 );
			}

			// Same overlap test the availability side uses, with this neighbour's own buffers.
			if ( $ns - $after < $end_ts && $ne + $before > $start_ts ) { $clash = true; break; }
		}
		if ( $clash ) continue;

		// A scheduled class occupies the resource too, even though its own seats are
		// counted separately.
		$clash_session = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . sp_book_table( 'sessions' ) . "
			 WHERE resource_id = %d AND status = 'scheduled' AND starts_at < %s AND ends_at > %s
			 LIMIT 1 FOR UPDATE",
			$rid, $guard_to, $guard_from
		) );
		if ( $clash_session ) continue;

		$row = $base;
		$row['resource_id'] = $rid;
		$row['session_id']  = 0;
		$row['starts_at']   = $start_s;
		$row['ends_at']     = $end_s;
		$row['slot_guard']  = sp_book_slot_guard( $rid, $start_s, $row['status'], true );   // timed booking: always slot-exclusive

		$booking_id = sp_book_insert_booking( $row );
		if ( $booking_id ) {
			$wpdb->query( 'COMMIT' );
			sp_book_cache_bust();
			return sp_book_get_booking( $booking_id );
		}
		// A slot_guard collision means someone committed this exact start first.
		// Try the next resource rather than failing the whole claim.
	}

	$wpdb->query( 'ROLLBACK' );

	// "Free, then lost the race" and "never bookable in the first place" are
	// different problems and deserve different messages — the first is worth
	// retrying, the second means the customer is looking at a stale calendar.
	return $was_offered
		? new WP_Error( 'slot_taken', 'That time was just taken. Please pick another.' )
		: new WP_Error( 'invalid_slot', 'That time is not available. Please pick another.' );
}

/**
 * Insert a booking row, generating a unique reference. Returns the new id, or 0.
 * Errors are suppressed because a duplicate key here is an EXPECTED outcome (a
 * lost race on slot_guard, or a code collision), not an exception.
 */
function sp_book_insert_booking( array $row ): int {
	global $wpdb;

	$suppress = $wpdb->suppress_errors( true );
	$id       = 0;

	for ( $attempt = 0; $attempt < 5; $attempt++ ) {
		$row['code'] = sp_book_generate_code();
		if ( $wpdb->insert( sp_book_table( 'bookings' ), $row ) ) {
			$id = (int) $wpdb->insert_id;
			break;
		}
		// Only a code collision is worth retrying; a slot_guard collision means
		// the slot is genuinely gone and another attempt would collide again.
		if ( strpos( (string) $wpdb->last_error, 'slot_guard' ) !== false ) break;
	}

	$wpdb->suppress_errors( $suppress );
	return $id;
}


/*--------------------------------------------------------------
# Cancel / Release / Hold Expiry
--------------------------------------------------------------*/

/**
 * Move a booking out of the active set and free what it was holding: the
 * slot_guard is cleared (so the slot can be re-sold) and any session seats are
 * handed back. Idempotent — releasing an already-released booking is a no-op,
 * which matters because both a customer cancel and the hold sweeper can reach
 * the same row.
 */
function sp_book_release( int $booking_id, string $status, int $by_user = 0, string $reason = '' ): bool {
	global $wpdb;

	$booking = sp_book_get_booking( $booking_id );
	if ( ! $booking ) return false;
	if ( ! in_array( (string) $booking['status'], sp_book_active_statuses(), true ) ) return true;   // already released

	$now = sp_book_now();
	$ok  = $wpdb->update(
		sp_book_table( 'bookings' ),
		[
			'status'        => $status,
			'slot_guard'    => null,
			'cancelled_at'  => $now,
			'cancelled_by'  => $by_user,
			'cancel_reason' => sanitize_text_field( $reason ),
			'updated_at'    => $now,
		],
		[ 'id' => $booking_id, 'status' => (string) $booking['status'] ]   // lost-update guard
	);

	if ( ! $ok ) return false;

	if ( (int) $booking['session_id'] ) {
		$wpdb->query( $wpdb->prepare(
			"UPDATE " . sp_book_table( 'sessions' ) . "
			 SET seats_taken = GREATEST(0, seats_taken - %d), updated_at = %s WHERE id = %d",
			max( 1, (int) $booking['party_size'] ), $now, (int) $booking['session_id']
		) );
	}

	// Anything still queued for a booking that is no longer happening must not send.
	$wpdb->update(
		sp_book_table( 'notifications' ),
		[ 'status' => 'cancelled' ],
		[ 'booking_id' => $booking_id, 'status' => 'queued' ]
	);

	sp_book_cache_bust();
	return true;
}

function sp_book_cancel( int $booking_id, int $by_user = 0, string $reason = '' ): bool {
	return sp_book_release( $booking_id, 'cancelled', $by_user, $reason );
}

/** Promote a paid hold to a real booking, and stop it expiring underneath the customer. */
function sp_book_confirm( int $booking_id ): bool {
	global $wpdb;

	$booking = sp_book_get_booking( $booking_id );
	if ( ! $booking ) return false;
	if ( (string) $booking['status'] === 'confirmed' ) return true;
	if ( (string) $booking['status'] !== 'pending' ) return false;   // expired or cancelled holds are not revivable here

	$now = sp_book_now();
	$ok  = $wpdb->update(
		sp_book_table( 'bookings' ),
		[
			'status'          => 'confirmed',
			'hold_expires_at' => null,
			'slot_guard'      => sp_book_slot_guard(
				(int) $booking['resource_id'],
				(string) $booking['starts_at'],
				'confirmed',
				sp_book_is_timed_booking( $booking )
			),
			'updated_at'      => $now,
		],
		[ 'id' => $booking_id, 'status' => 'pending' ]
	);

	if ( $ok ) sp_book_cache_bust();
	return (bool) $ok;
}

/**
 * Sweep holds whose checkout never completed. Returns how many were released.
 *
 * This is a SWEEP rather than a scheduled per-booking job on purpose: this
 * framework's cron is traffic-triggered, so "run at 10:14 to expire this one
 * hold" is a promise it cannot keep. A sweep is correct whenever it happens to
 * run, and availability additionally treats a hold as busy only until its
 * hold_expires_at passes — so a late sweep costs bookkeeping, not a lost slot.
 */
function sp_book_expire_holds(): int {
	global $wpdb;

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id FROM " . sp_book_table( 'bookings' ) . "
		 WHERE status = 'pending' AND hold_expires_at IS NOT NULL AND hold_expires_at < %s
		 LIMIT 200",
		sp_book_now()
	), ARRAY_A ) ?: [];

	$n = 0;
	foreach ( $rows as $row ) {
		if ( sp_book_release( (int) $row['id'], 'expired', 0, 'Hold expired before payment' ) ) $n++;
	}
	return $n;
}

/**
 * Drive the sweep from ordinary traffic, at most once every five minutes.
 *
 * The alternative — a wp-cron event — is unreliable here for the same reason
 * the reminder queue is a queue: this framework's cron only fires when a bot
 * happens to hit the site overnight. A transient-gated sweep on init costs one
 * cached option read per request and is self-healing on any site that gets
 * visitors at all.
 */
add_action( 'init', 'sp_book_maybe_expire_holds', 20 );
function sp_book_maybe_expire_holds(): void {
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'booking' ) ) return;
	if ( get_transient( 'sp_book_hold_sweep' ) ) return;

	set_transient( 'sp_book_hold_sweep', 1, 5 * MINUTE_IN_SECONDS );
	sp_book_expire_holds();

	// Same tick drains the outbound queue: one transient read covers both jobs, and
	// reminders are due on wall-clock time the traffic-triggered cron can't promise.
	if ( function_exists( 'sp_book_send_due' ) ) sp_book_send_due();
}


/*--------------------------------------------------------------
# Reschedule
--------------------------------------------------------------*/

/**
 * Move a timed booking to a new start (and optionally a new resource).
 *
 * This is NOT cancel-and-rebook. That would drop the customer's details on the
 * floor if the second half failed, and it would free the old slot before the new
 * one was secured — so a race could leave the customer with nothing. Instead the
 * row is UPDATED in place under the same locking discipline as sp_book_claim():
 * the destination range is locked, verified against the rules, and only then
 * written, with the booking excluded from its own clash check so moving it by
 * ten minutes doesn't collide with itself.
 *
 * The booking keeps its ORIGINAL length rather than re-deriving it from the
 * service, so editing a service's duration later never silently resizes
 * appointments already on the calendar.
 *
 * Returns the updated booking row, or WP_Error.
 */
function sp_book_reschedule( int $booking_id, string $new_start_utc, int $new_resource_id = 0 ) {
	global $wpdb;

	$booking = sp_book_get_booking( $booking_id );
	if ( ! $booking ) {
		return new WP_Error( 'not_found', 'That booking no longer exists.' );
	}
	if ( ! in_array( (string) $booking['status'], [ 'pending', 'confirmed' ], true ) ) {
		return new WP_Error( 'not_movable', 'Only pending or confirmed bookings can be moved.' );
	}
	if ( ! sp_book_is_timed_booking( $booking ) ) {
		return new WP_Error( 'not_movable', 'A class booking moves by changing the class, not the time.' );
	}

	$service = sp_book_get_service( (int) $booking['service_id'] );
	if ( ! $service ) {
		return new WP_Error( 'invalid_service', 'That booking\'s service is missing.' );
	}

	$start_ts = sp_book_utc_ts( $new_start_utc );
	if ( ! $start_ts ) {
		return new WP_Error( 'invalid_slot', 'That is not a valid time.' );
	}

	// Preserve the length that was actually booked.
	$length = sp_book_utc_ts( (string) $booking['ends_at'] ) - sp_book_utc_ts( (string) $booking['starts_at'] );
	if ( $length <= 0 ) $length = max( 1, (int) $service['duration_min'] ) * 60;
	$end_ts = $start_ts + $length;

	$rid = $new_resource_id ?: (int) $booking['resource_id'];
	$resource = sp_book_get_resource( $rid );
	if ( ! $resource ) {
		return new WP_Error( 'invalid_slot', 'That resource no longer exists.' );
	}

	$start_s = gmdate( 'Y-m-d H:i:s', $start_ts );
	$end_s   = gmdate( 'Y-m-d H:i:s', $end_ts );

	$pad_before = ( (int) $service['buffer_before_min'] ) * 60;
	$pad_after  = ( (int) $service['buffer_after_min'] ) * 60;
	$guard_from = gmdate( 'Y-m-d H:i:s', $start_ts - $pad_before );
	$guard_to   = gmdate( 'Y-m-d H:i:s', $end_ts + $pad_after );

	[ $active_sql, $active_params ] = sp_book_active_sql();
	$bookings = sp_book_table( 'bookings' );

	$wpdb->query( 'START TRANSACTION' );

	// The destination must be inside the resource's declared hours. Staff can move a
	// booking to any time the RULES allow — the lead-time and max-advance policies are
	// customer-facing limits and deliberately don't apply to a staff reschedule.
	$date = sp_book_to_local( $start_s, 'Y-m-d', (string) $resource['timezone'] );
	if ( ! sp_book_interval_fits( sp_book_open_windows( $service, $rid, $date ), $start_ts, $end_ts ) ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'invalid_slot', 'That time is outside the working hours set for this resource.' );
	}

	// Locking read, excluding this booking from its own clash check.
	$clash = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM $bookings
		 WHERE resource_id = %d AND id != %d AND $active_sql AND starts_at < %s AND ends_at > %s
		 LIMIT 1 FOR UPDATE",
		array_merge( [ $rid, $booking_id ], $active_params, [ $guard_to, $guard_from ] )
	) );
	if ( $clash ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'slot_taken', 'Something else is already booked at that time.' );
	}

	$ok = $wpdb->update( $bookings, [
		'resource_id' => $rid,
		'starts_at'   => $start_s,
		'ends_at'     => $end_s,
		'slot_guard'  => sp_book_slot_guard( $rid, $start_s, (string) $booking['status'], true ),
		'updated_at'  => sp_book_now(),
	], [ 'id' => $booking_id ] );

	if ( $ok === false ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'slot_taken', 'That time was just taken. Please pick another.' );
	}

	$wpdb->query( 'COMMIT' );
	sp_book_cache_bust();
	return sp_book_get_booking( $booking_id );
}


/*--------------------------------------------------------------
# Window mode — arrival windows with capacity
--------------------------------------------------------------*/

/** The client's configured arrival windows for a service, or [] for exact-time services. */
function sp_book_service_windows( array $service ): array {
	if ( (string) ( $service['slot_mode'] ?? 'exact' ) !== 'window' ) return [];
	$raw = json_decode( (string) ( $service['windows'] ?? '' ), true );
	if ( ! is_array( $raw ) ) return [];

	$out = [];
	foreach ( $raw as $w ) {
		$start = sp_book_time_value( (string) ( $w['start'] ?? '' ) );
		$end   = sp_book_time_value( (string) ( $w['end'] ?? '' ) );
		if ( $start === '' || $end === '' || $end <= $start ) continue;
		$out[] = [
			'label' => (string) ( $w['label'] ?? '' ),
			'start' => $start,
			'end'   => $end,
		];
	}
	usort( $out, fn( $a, $b ) => strcmp( $a['start'], $b['start'] ) );
	return $out;
}

/**
 * How much work a resource already has committed inside a window, in seconds,
 * and the count of jobs.
 *
 * Window bookings deliberately share wall-clock time, so "is the clock free" is
 * the wrong question — this asks "how much of the promise is already spoken for".
 */
function sp_book_window_load( array $service, int $resource_id, int $ws, int $we, int $ignore_booking_id = 0 ): array {
	global $wpdb;
	[ $active_sql, $active_params ] = sp_book_active_sql();

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, lat, lng FROM " . sp_book_table( 'bookings' ) . "
		 WHERE resource_id = %d AND $active_sql AND starts_at >= %s AND starts_at < %s",
		array_merge( [ $resource_id ], $active_params, [ gmdate( 'Y-m-d H:i:s', $ws ), gmdate( 'Y-m-d H:i:s', $we ) ] )
	), ARRAY_A ) ?: [];

	$jobs = [];
	foreach ( $rows as $r ) {
		if ( $ignore_booking_id && (int) $r['id'] === $ignore_booking_id ) continue;
		$jobs[] = $r;
	}
	return [ count( $jobs ), $jobs ];
}

/**
 * Does another job of this service fit in this window for this resource?
 *
 * The test is workload, not overlap: every job costs its ESTIMATED duration plus
 * the spacing between it and its neighbours, and the total has to fit inside the
 * promise. Spacing uses the same max(minimum, travel) rule as exact mode, taking
 * the WORST travel among the jobs already in the window — the sequence is decided
 * on the morning, so the safe assumption is the least convenient order.
 */
function sp_book_window_fits( array $service, int $resource_id, int $ws, int $we, ?array $dest = null, int $ignore_booking_id = 0 ): bool {
	$duration = max( 1, (int) $service['duration_min'] ) * 60;
	[ $count, $jobs ] = sp_book_window_load( $service, $resource_id, $ws, $we, $ignore_booking_id );

	$cap = (int) $service['window_max_jobs'];
	if ( $cap > 0 && $count >= $cap ) return false;

	// Spacing between consecutive jobs. With n+1 jobs there are n gaps.
	$gap = ( (int) $service['buffer_before_min'] ) * 60;
	if ( $dest && sp_book_travel_enabled( $service ) ) {
		foreach ( $jobs as $j ) {
			$t = sp_book_travel_minutes( sp_book_booking_point( $j ), $dest, (int) $service['buffer_before_min'] );
			if ( $t !== null ) $gap = max( $gap, $t * 60 );
		}
	}

	$needed = ( $count + 1 ) * $duration + $count * $gap;
	return $needed <= ( $we - $ws );
}

/**
 * WINDOW MODE — one offer per (date, window, resource) that still has room.
 *
 * Each configured window is materialised for the date and INTERSECTED with the
 * resource's actual working hours, so a Saturday that only runs 8-12 drops the
 * afternoon window without anyone configuring it twice. A window clipped shorter
 * than one job simply isn't offered.
 */
function sp_book_slots_window( array $service, array $resources, array $dates, bool $ignore_policy = false, ?array $dest = null ): array {
	$windows = sp_book_service_windows( $service );
	if ( ! $windows ) return [];

	[ $earliest, $latest ] = sp_book_policy_bounds( $service, $ignore_policy );
	$duration = max( 1, (int) $service['duration_min'] ) * 60;

	$slots = [];
	foreach ( $resources as $resource ) {
		$rid = (int) $resource['id'];
		$tz  = sp_book_resource_tz( $resource );

		foreach ( $dates as $date ) {
			$open = sp_book_open_windows( $service, $rid, $date );
			if ( ! $open ) continue;

			foreach ( $windows as $w ) {
				$ws = sp_book_local_ts( $date, $w['start'], $tz );
				$we = sp_book_local_ts( $date, $w['end'], $tz );
				if ( ! $ws || ! $we || $we <= $ws ) continue;
				if ( $ws < $earliest || $ws > $latest ) continue;

				// Clip to the hours actually worked; a window outside them does not exist.
				$clipped = sp_book_intervals_subtract( [ [ $ws, $we ] ], sp_book_intervals_subtract( [ [ $ws, $we ] ], $open ) );
				if ( ! $clipped ) continue;

				$cs = $clipped[0][0];
				$ce = $clipped[ count( $clipped ) - 1 ][1];
				if ( ( $ce - $cs ) < $duration ) continue;

				if ( ! sp_book_window_fits( $service, $rid, $cs, $ce, $dest ) ) continue;

				$slots[] = [
					'starts_at'   => gmdate( 'Y-m-d H:i:s', $cs ),
					'ends_at'     => gmdate( 'Y-m-d H:i:s', $ce ),
					'resource_id' => $rid,
					'session_id'  => 0,
					'seats_left'  => 1,
					'window'      => $w['label'] !== '' ? $w['label'] : '',
				];
			}
		}
	}
	return $slots;
}

/**
 * WINDOW MODE claim.
 *
 * Structurally closer to the session path than the timed one: the window is a
 * container, so the question is whether another job FITS, not whether the clock
 * is free. The capacity re-check happens with the window's rows locked, so two
 * customers taking the last space in the same window cannot both succeed.
 *
 * No slot_guard: window bookings share wall-clock time on purpose, and a unique
 * index on (resource, start) would reject the second booking in a window outright.
 */
function sp_book_claim_window( array $service, array $base, array $args ) {
	global $wpdb;

	$start_ts = sp_book_utc_ts( (string) ( $args['starts_at'] ?? '' ) );
	$end_ts   = sp_book_utc_ts( (string) ( $args['ends_at'] ?? '' ) );
	if ( ! $start_ts || ! $end_ts || $end_ts <= $start_ts ) {
		return new WP_Error( 'invalid_slot', 'That arrival window is not valid.' );
	}

	[ $earliest, $latest ] = sp_book_policy_bounds( $service, ! empty( $args['ignore_policy'] ) );
	if ( $start_ts < $earliest || $start_ts > $latest ) {
		return new WP_Error( 'outside_policy', 'That window is outside the time when it can be booked.' );
	}

	$dest      = $args['_dest'] ?? null;
	$resources = sp_book_service_resources( (int) $service['id'] );
	$want_rid  = (int) ( $args['resource_id'] ?? 0 );
	if ( $want_rid ) {
		$resources = array_values( array_filter( $resources, fn( $r ) => (int) $r['id'] === $want_rid ) );
	}
	if ( ! $resources ) {
		return new WP_Error( 'invalid_slot', 'Nobody is available for that window.' );
	}

	$resources = sp_book_order_resources( $service, $resources, $start_ts, $end_ts, $dest );

	$wpdb->query( 'START TRANSACTION' );

	foreach ( $resources as $resource ) {
		$rid = (int) $resource['id'];

		// Lock this window's rows for this resource before counting them, so a concurrent
		// booking cannot slip into the same space between the count and the insert.
		[ $active_sql, $active_params ] = sp_book_active_sql();
		$wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM " . sp_book_table( 'bookings' ) . "
			 WHERE resource_id = %d AND $active_sql AND starts_at >= %s AND starts_at < %s
			 FOR UPDATE",
			array_merge( [ $rid ], $active_params, [ gmdate( 'Y-m-d H:i:s', $start_ts ), gmdate( 'Y-m-d H:i:s', $end_ts ) ] )
		) );

		$date = sp_book_to_local( gmdate( 'Y-m-d H:i:s', $start_ts ), 'Y-m-d', (string) $resource['timezone'] );
		if ( ! sp_book_interval_fits( sp_book_open_windows( $service, $rid, $date ), $start_ts, $end_ts ) ) {
			continue;
		}
		if ( ! sp_book_window_fits( $service, $rid, $start_ts, $end_ts, $dest ) ) {
			continue;
		}

		$row = $base;
		$row['resource_id'] = $rid;
		$row['session_id']  = 0;
		$row['starts_at']   = gmdate( 'Y-m-d H:i:s', $start_ts );
		$row['ends_at']     = gmdate( 'Y-m-d H:i:s', $end_ts );
		$row['slot_guard']  = null;

		$booking_id = sp_book_insert_booking( $row );
		if ( $booking_id ) {
			$wpdb->query( 'COMMIT' );
			sp_book_cache_bust();
			return sp_book_get_booking( $booking_id );
		}
	}

	$wpdb->query( 'ROLLBACK' );
	return new WP_Error( 'slot_taken', 'That arrival window just filled up. Please pick another.' );
}


/*--------------------------------------------------------------
# Dispatch — choosing WHO by insertion cost
--------------------------------------------------------------*/

/** Is this service dispatched by proximity rather than by display order? */
function sp_book_dispatch_nearest( array $service ): bool {
	return (string) ( $service['dispatch_mode'] ?? 'order' ) === 'nearest'
		&& sp_book_travel_enabled( $service );
}

/**
 * How much EXTRA driving does putting this job on this resource's day create?
 *
 *     cost = travel(previous → new) + travel(new → next) − travel(previous → next)
 *
 * This is the measure that makes dispatch look clever, because it is what a good
 * dispatcher actually reasons about. Raw distance from the tech gets it wrong:
 * someone already driving past the door is nearly free even from twenty miles
 * away, while someone parked five miles off with a full day on the other side of
 * town is expensive.
 *
 * A day with nothing before or after the slot is anchored to the resource's home
 * base at that end, so an empty day costs a round trip — which is correct, and is
 * also why an idle person is the natural home for an out-of-the-way job: there is
 * no route to damage. Once they have that anchor, the next job nearby is cheap
 * for them, so the greedy rule clusters work on its own.
 *
 * Returns minutes, or null when travel cannot be established at all.
 */
function sp_book_insertion_cost( array $service, int $resource_id, int $start_ts, int $end_ts, ?array $dest ): ?int {
	if ( ! $dest ) return null;

	global $wpdb;
	[ $active_sql, $active_params ] = sp_book_active_sql();
	$table = sp_book_table( 'bookings' );

	// The job immediately before, and the one immediately after, on that resource's day.
	$prev = $wpdb->get_row( $wpdb->prepare(
		"SELECT lat, lng FROM $table
		 WHERE resource_id = %d AND $active_sql AND starts_at < %s
		 ORDER BY starts_at DESC LIMIT 1",
		array_merge( [ $resource_id ], $active_params, [ gmdate( 'Y-m-d H:i:s', $start_ts ) ] )
	), ARRAY_A );

	$next = $wpdb->get_row( $wpdb->prepare(
		"SELECT lat, lng FROM $table
		 WHERE resource_id = %d AND $active_sql AND starts_at >= %s
		 ORDER BY starts_at ASC LIMIT 1",
		array_merge( [ $resource_id ], $active_params, [ gmdate( 'Y-m-d H:i:s', $end_ts ) ] )
	), ARRAY_A );

	// Anchor an open end to the home base, so an empty day is costed as a round trip
	// rather than as free.
	$base = sp_book_resource_point( sp_book_get_resource( $resource_id ) );
	$a = $prev ? sp_book_booking_point( $prev ) : $base;
	$b = $next ? sp_book_booking_point( $next ) : $base;

	// Ranking needs REAL numbers, so the buffer short-circuit is bypassed here (floor 0).
	// Passing the service minimum would collapse every nearby pair to zero and make the
	// whole ranking a tie — the short-circuit exists to skip work, not to compare.
	$to   = sp_book_travel_minutes( $a, $dest, 0 );
	$from = sp_book_travel_minutes( $dest, $b, 0 );
	$thru = sp_book_travel_minutes( $a, $b, 0 );

	if ( $to === null && $from === null ) return null;

	return max( 0, (int) $to + (int) $from - (int) $thru );
}

/**
 * Rank and filter slots for a proximity-dispatched service.
 *
 * Two things happen here, and both are deliberate:
 *
 *  1. ONE OFFER PER TIME. The customer is booking a time, not a person — showing
 *     four techs for the same 8am is noise they cannot act on. The cheapest
 *     resource wins the slot and the rest are dropped; who actually goes is
 *     settled at claim time against live data anyway.
 *
 *  2. OUT-OF-AREA POLICY. A slot whose insertion cost exceeds the threshold is
 *     kept, dropped, or surcharged according to the client's setting — never
 *     silently withheld unless they asked for that.
 */
function sp_book_dispatch_rank( array $service, array $slots, ?array $dest ): array {
	if ( ! $slots || ! sp_book_dispatch_nearest( $service ) || ! $dest ) return $slots;

	$threshold = max( 0, (int) $service['out_of_area_min'] );
	$policy    = (string) $service['out_of_area_policy'];
	$fee       = (int) $service['out_of_area_fee_cents'];

	$best = [];
	foreach ( $slots as $slot ) {
		$cost = sp_book_insertion_cost(
			$service,
			(int) $slot['resource_id'],
			sp_book_utc_ts( $slot['starts_at'] ),
			sp_book_utc_ts( $slot['ends_at'] ),
			$dest
		);
		if ( $cost === null ) $cost = 0;

		$far = $cost > $threshold;
		if ( $far && $policy === 'block' ) continue;

		$slot['travel_cost'] = $cost;
		$slot['out_of_area'] = $far ? 1 : 0;
		$slot['surcharge']   = ( $far && $policy === 'surcharge' ) ? $fee : 0;

		// Keep only the cheapest resource for each distinct time.
		$key = $slot['starts_at'] . '|' . $slot['ends_at'];
		if ( ! isset( $best[ $key ] ) || $cost < $best[ $key ]['travel_cost'] ) {
			$best[ $key ] = $slot;
		}
	}

	$out = array_values( $best );
	usort( $out, fn( $a, $b ) => [ $a['starts_at'], $a['travel_cost'] ] <=> [ $b['starts_at'], $b['travel_cost'] ] );
	return $out;
}

/**
 * Candidate resources for a claim, cheapest first when dispatching by proximity.
 *
 * The claim still verifies each one in turn — this only changes the ORDER it
 * tries them in, so a resource that has become busy since the calendar was drawn
 * simply falls through to the next-best rather than failing the booking.
 */
function sp_book_order_resources( array $service, array $resources, int $start_ts, int $end_ts, ?array $dest ): array {
	if ( count( $resources ) < 2 || ! sp_book_dispatch_nearest( $service ) || ! $dest ) return $resources;

	$scored = [];
	foreach ( $resources as $r ) {
		$cost = sp_book_insertion_cost( $service, (int) $r['id'], $start_ts, $end_ts, $dest );
		$scored[] = [ 'cost' => $cost === null ? PHP_INT_MAX : $cost, 'resource' => $r ];
	}
	usort( $scored, fn( $a, $b ) => $a['cost'] <=> $b['cost'] );

	return array_map( fn( $x ) => $x['resource'], $scored );
}
