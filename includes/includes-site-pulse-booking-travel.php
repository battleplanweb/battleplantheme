<?php
/* Battle Plan Web Design — Site Pulse: Travel-Time Buffers

/*--------------------------------------------------------------
Dynamic spacing between on-location appointments, based on how far apart the
customers actually are.

THE RULE, and everything follows from it:

    buffer = max( the service's minimum buffer, driving time )

Two consequences make this cheap and safe rather than fragile:

  1. THE FALLBACK IS AUTOMATIC. No address, a failed geocode, an API outage, a
     blown quota — every one of them collapses to "use the minimum", which is
     exactly the behaviour before this file existed. There is no degraded mode
     to design, and a booking can never be blocked because Google was slow.

  2. MOST PAIRS NEVER REACH THE API. If driving time cannot possibly exceed the
     minimum, the minimum wins and the real number is irrelevant. A conservative
     straight-line estimate settles that for free — see sp_book_travel_minutes().
     Two clients on the same street cost nothing to schedule.

Cost is a first-class concern here, not an afterthought: this fires while a
customer is BROWSING, not just when they book, and the Places bill on this
account has run away once before. Hence the cache, the short-circuit, and the
per-gap (not per-slot) call pattern in the engine.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Settings
# Points + keys
# Straight-line short-circuit
# Travel time (cache → API → estimate)
# Points for bookings and resources
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Settings
--------------------------------------------------------------*/

/**
 * Straight-line miles are converted to driving minutes by a two-band formula:
 *
 *     minutes = miles × detour × pace
 *
 * `detour` accounts for the road grid not being straight; `pace` is minutes per
 * road mile. Two bands because short local hops and cross-metro runs behave
 * differently — the long ones hit highways AND chokepoints, so they get a lower
 * detour but a slower pace.
 *
 * The defaults are Dallas-calibrated. They are SETTINGS, not constants, because
 * the framework runs clients in Houston, rural Texas, and Southwest Florida, and
 * a number tuned for LBJ traffic is wrong in Naples. Each install tunes its own.
 */
function sp_book_travel_band_miles(): float {
	return max( 1.0, (float) sp_book_get_setting( 'travel_band_miles', '10' ) );
}

function sp_book_travel_local(): array {
	return [
		max( 1.0, (float) sp_book_get_setting( 'travel_local_detour', '1.4' ) ),
		max( 0.1, (float) sp_book_get_setting( 'travel_local_pace', '1.3' ) ),
	];
}

function sp_book_travel_metro(): array {
	return [
		max( 1.0, (float) sp_book_get_setting( 'travel_metro_detour', '1.3' ) ),
		max( 0.1, (float) sp_book_get_setting( 'travel_metro_pace', '1.6' ) ),
	];
}

/**
 * Minutes allowed for a trip WITHIN one ZIP, and — importantly — the FLOOR under
 * every other trip.
 *
 * Treating it as a separate band inverts: with the Dallas numbers a 2.5-mile hop
 * to the next ZIP computes to 4.6 minutes, less than the 8-12 allowed for a trip
 * inside one ZIP. A client further away would get a smaller buffer than one
 * across the street. Making it a floor removes that discontinuity entirely: no
 * trip is ever costed below the neighbourhood minimum.
 */
function sp_book_travel_intrazonal(): int {
	return max( 0, (int) sp_book_get_setting( 'travel_intrazonal_min', '10' ) );
}

/**
 * Hard ceiling on a computed buffer. A single bad geocode (a typo putting a
 * client in another state) would otherwise blank out a whole day's availability.
 */
function sp_book_travel_cap(): int {
	return max( 0, (int) sp_book_get_setting( 'travel_cap_min', '120' ) );
}

/** Travel buffers are per-service, using the flag already on the services table. */
function sp_book_travel_enabled( array $service ): bool {
	return ! empty( $service['travel_buffer'] ) && (int) $service['requires_address'] === 1;
}


/*--------------------------------------------------------------
# Points + keys
--------------------------------------------------------------*/

/**
 * Why the last sp_book_zip_point() call came back empty: '' (fine), 'unknown_zip' (Google says
 * no such ZIP), 'no_key', 'no_geocoder', or 'api:<google status>'. Kept separate from the return
 * value so the settings screen can say something true instead of blaming the ZIP for everything.
 */
function sp_book_zip_fail( ?string $set = null ): string {
	static $why = '';
	if ( $set !== null ) $why = $set;
	return $why;
}

/** A point is [ lat, lng ], or null when we don't know where something is. */
function sp_book_point( $lat, $lng ): ?array {
	$lat = (float) $lat;
	$lng = (float) $lng;
	if ( ! $lat && ! $lng ) return null;
	if ( abs( $lat ) > 90 || abs( $lng ) > 180 ) return null;
	return [ $lat, $lng ];
}

/**
 * The cache key for a point. Three decimals is ~110m: fine enough that two
 * different clients on one street share an entry, coarse enough that the cache
 * actually fills up instead of holding one row per house number.
 */
function sp_book_geo_key( array $p ): string {
	return number_format( $p[0], 3, '.', '' ) . ',' . number_format( $p[1], 3, '.', '' );
}

/** Great-circle distance in kilometres. */
function sp_book_haversine_km( array $a, array $b ): float {
	$r  = 6371.0;
	$dLat = deg2rad( $b[0] - $a[0] );
	$dLng = deg2rad( $b[1] - $a[1] );
	$s = sin( $dLat / 2 ) ** 2
	   + cos( deg2rad( $a[0] ) ) * cos( deg2rad( $b[0] ) ) * sin( $dLng / 2 ) ** 2;
	return $r * 2 * atan2( sqrt( $s ), sqrt( 1 - $s ) );
}


/*--------------------------------------------------------------
# Straight-line short-circuit
--------------------------------------------------------------*/

/** Great-circle distance in miles. */
function sp_book_haversine_miles( array $a, array $b ): float {
	return sp_book_haversine_km( $a, $b ) * 0.621371;
}

/**
 * Driving minutes between two points, from the banded formula. No network call:
 * this is arithmetic on two coordinate pairs, which is the whole point — the
 * schedule never depends on a third party being up, in budget, or fast.
 */
function sp_book_travel_estimate_min( array $from, array $to ): int {
	return sp_book_minutes_for_miles( sp_book_haversine_miles( $from, $to ) );
}

/**
 * The band arithmetic on its own, taking straight-line miles directly.
 *
 * Split out from sp_book_travel_estimate_min() so the settings screen can show what the
 * current numbers DO — "a 5-mile hop is costed at 9 minutes" — without inventing a pair of
 * points to ask about, and so the calibration endpoint can invert it. One implementation,
 * so the preview can never drift from the scheduler.
 */
function sp_book_minutes_for_miles( float $miles ): int {
	[ $detour, $pace ] = $miles < sp_book_travel_band_miles()
		? sp_book_travel_local()
		: sp_book_travel_metro();

	$minutes = (int) ceil( $miles * $detour * $pace );

	// The intrazonal allowance is a floor on every trip, not a band of its own.
	return max( sp_book_travel_intrazonal(), $minutes );
}

/**
 * Everything the Travel Time settings card needs, in one place.
 *
 * `examples` is the part that matters: the raw multipliers are meaningless to the person
 * setting them, but "12 miles → 25 minutes" is a claim they can check against their own
 * experience of the drive. Every endpoint that writes travel settings returns this block so
 * the card always re-renders from computed truth rather than from what the browser sent.
 */
function sp_book_travel_settings(): array {
	$examples = [];
	foreach ( [ 2, 5, 12, 25 ] as $miles ) {
		$examples[] = [ 'miles' => $miles, 'minutes' => sp_book_minutes_for_miles( (float) $miles ) ];
	}

	return [
		'intrazonal'   => sp_book_travel_intrazonal(),
		'band_miles'   => sp_book_travel_band_miles(),
		'local_detour' => sp_book_travel_local()[0],
		'local_pace'   => sp_book_travel_local()[1],
		'metro_detour' => sp_book_travel_metro()[0],
		'metro_pace'   => sp_book_travel_metro()[1],
		'cap'          => sp_book_travel_cap(),
		'examples'     => $examples,
	];
}

/**
 * Turn "these two ZIPs are about 20 minutes apart" into the pace multiplier for that band.
 *
 * This is the whole point of the redesign: nobody can answer "minutes per road mile times a
 * detour factor", but everybody who dispatches for a living knows how long the drive across
 * town takes. We geocode the pair, measure the straight line, and solve for the one unknown:
 *
 *     pace = stated minutes / ( straight-line miles x detour )
 *
 * The detour factor is left alone rather than also being solved for — two unknowns from one
 * measurement is guesswork, and the grid-vs-straight-line ratio is the more stable of the two.
 *
 * Returns [ ok, message, band ] — band is which one the distance actually fell in, which is
 * how a mismatched pair gets reported back instead of silently miscalibrating the wrong band.
 */
function sp_book_zip_fail_message( string $zip, string $why ): string {
	if ( $why === 'no_key' ) {
		return 'ZIP codes cannot be looked up because no Google API key is set. Add one under Settings → API Keys, then try again.';
	}
	if ( $why === 'no_geocoder' ) {
		return 'ZIP lookup is unavailable on this install.';
	}
	if ( strpos( $why, 'api:' ) === 0 ) {
		// Google answered and refused. Its own wording is far more use than anything we'd invent.
		return 'Google refused the ZIP lookup (' . substr( $why, 4 ) . '). This is usually the Geocoding API not being enabled on the key, or billing being off — check the key under Settings → API Keys.';
	}
	return 'Could not find ZIP ' . $zip . '. Check it is a real US ZIP code and try again.';
}

function sp_book_travel_calibrate( string $zip_a, string $zip_b, int $minutes, string $band ): array {
	if ( $minutes < 1 ) {
		return [ false, 'Enter how many minutes that drive usually takes.', '' ];
	}

	$a   = sp_book_zip_point( $zip_a );
	$why = sp_book_zip_fail();
	if ( $a ) {
		$b   = sp_book_zip_point( $zip_b );
		$why = sp_book_zip_fail();
	}

	if ( ! $a || empty( $b ) ) {
		$bad = ! $a ? $zip_a : $zip_b;
		return [ false, sp_book_zip_fail_message( $bad, $why ), '' ];
	}

	$miles = sp_book_haversine_miles( $a, $b );
	if ( $miles < 0.5 ) {
		return [ false, 'Those two ZIPs are practically on top of each other, so there is no drive to measure. Pick two that are genuinely apart.', '' ];
	}

	// Which band does this pair ACTUALLY belong to? Calibrating the short-trip rule with a
	// cross-county pair would quietly make every local hop wrong, so a mismatch is refused
	// with the distance shown rather than accepted.
	$actual = $miles < sp_book_travel_band_miles() ? 'local' : 'metro';
	if ( $actual !== $band ) {
		$msg = $band === 'local'
			? 'Those ZIPs are about ' . round( $miles ) . ' miles apart, which counts as a long trip here. Use the long-trip row for this pair, and pick two closer ZIPs for the short-trip row.'
			: 'Those ZIPs are only about ' . round( $miles ) . ' miles apart, which counts as a short trip here. Pick two ZIPs on opposite sides of the area you cover.';
		return [ false, $msg, $actual ];
	}

	$detour = $band === 'local' ? sp_book_travel_local()[0] : sp_book_travel_metro()[0];
	$pace   = $minutes / ( $miles * $detour );

	// Clamp to something physically possible. A fat-fingered "200 minutes" for a 3-mile hop
	// would otherwise poison every estimate in that band.
	if ( $pace < 0.5 || $pace > 12 ) {
		return [ false, 'That works out to ' . round( $pace, 1 ) . ' minutes per mile, which does not look right for a ' . round( $miles ) . '-mile drive. Check the ZIPs and the minutes.', '' ];
	}

	sp_book_set_setting( 'travel_' . $band . '_pace', (string) round( $pace, 3 ) );

	return [
		true,
		'Set from a ' . round( $miles ) . '-mile drive at ' . $minutes . ' minutes.',
		$band,
	];
}


/*--------------------------------------------------------------
# Travel time (cache → API → estimate)
--------------------------------------------------------------*/

/**
 * Driving minutes from $from to $to, or null when either point is unknown.
 *
 * Pure arithmetic, memoised for the request. There is no live routing call: a
 * schedule that depends on a third-party API being up, in budget and fast is a
 * schedule that breaks on the day the card expires. The cached table exists so a
 * future batch job CAN overwrite these with measured times without any caller
 * changing — the `source` column tells the two apart.
 *
 * $floor is the service's minimum buffer, kept in the signature because a trip
 * that cannot beat the minimum need not be computed precisely at all.
 */
function sp_book_travel_minutes( ?array $from, ?array $to, int $floor = 0 ): ?int {
	if ( ! $from || ! $to ) return null;
	if ( ! sp_book_point( $from[0], $from[1] ) || ! sp_book_point( $to[0], $to[1] ) ) return null;

	$ka = sp_book_geo_key( $from );
	$kb = sp_book_geo_key( $to );

	static $memo = [];
	$mk = $ka . '>' . $kb;
	if ( isset( $memo[ $mk ] ) ) return $memo[ $mk ];

	global $wpdb;
	$table = sp_book_table( 'travel' );

	// A measured time, if a batch job has ever written one for this pair.
	$hit = $wpdb->get_var( $wpdb->prepare(
		"SELECT minutes FROM $table WHERE origin_key = %s AND dest_key = %s AND source = 'api'", $ka, $kb
	) );
	if ( $hit !== null ) return $memo[ $mk ] = (int) $hit;

	$minutes = min( sp_book_travel_estimate_min( $from, $to ), sp_book_travel_cap() );

	// Stored so the matrix is inspectable and refinable, not because it is expensive to redo.
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO $table ( origin_key, dest_key, minutes, source, fetched_at )
		 VALUES ( %s, %s, %d, 'estimate', %s )
		 ON DUPLICATE KEY UPDATE minutes = VALUES(minutes), fetched_at = VALUES(fetched_at)",
		$ka, $kb, $minutes, sp_book_now()
	) );

	return $memo[ $mk ] = $minutes;
}

/*--------------------------------------------------------------
# ZIP centroids — resolved once, kept forever
--------------------------------------------------------------*/

/**
 * The centre of a ZIP code. Geocoded on first use and stored permanently, so a
 * business pays for each ZIP it serves exactly once, ever — typically a dozen or
 * two lookups in the first week and none after that.
 *
 * A ZIP that cannot be resolved is remembered as a failure with an attempt count,
 * so a bad postcode does not re-query on every page view.
 */
function sp_book_zip_point( string $zip ): ?array {
	sp_book_zip_fail( '' );

	$zip = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $zip ) );
	if ( $zip === '' ) return null;
	$zip = substr( $zip, 0, 5 );   // ZIP+4 shares a centroid with its ZIP5

	global $wpdb;
	$table = sp_book_table( 'zips' );

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE zip = %s", $zip ), ARRAY_A );
	if ( $row && (int) $row['resolved'] ) {
		return sp_book_point( $row['lat'], $row['lng'] );
	}
	if ( $row && (int) $row['attempts'] >= 3 ) {
		sp_book_zip_fail( 'unknown_zip' );
		return null;   // Google has told us three times this ZIP isn't real. Stop asking.
	}

	if ( ! function_exists( 'site_pulse_mileage_geocode' ) ) {
		sp_book_zip_fail( 'no_geocoder' );
		return null;
	}

	$geo = site_pulse_mileage_geocode( $zip . ', USA' );

	if ( ! $geo ) {
		$status = function_exists( 'site_pulse_geocode_last_status' ) ? site_pulse_geocode_last_status() : '';
		$head   = strtok( $status, ':' );

		// ONLY a real answer from Google about this ZIP counts against the retry budget. An
		// unconfigured key, a refused request or a dropped connection says nothing whatsoever
		// about whether the ZIP exists — burning attempts on those would permanently blacklist
		// perfectly good ZIP codes because of a problem that has since been fixed.
		if ( $head === 'ZERO_RESULTS' || $head === 'INVALID_REQUEST' ) {
			sp_book_zip_fail( 'unknown_zip' );
			if ( $row ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $table SET attempts = attempts + 1 WHERE zip = %s", $zip ) );
			} else {
				$wpdb->insert( $table, [ 'zip' => $zip, 'attempts' => 1, 'created_at' => sp_book_now() ] );
			}
		} else {
			sp_book_zip_fail( $head === 'NO_KEY' ? 'no_key' : 'api:' . $status );
		}
		return null;
	}

	sp_book_zip_fail( '' );

	$wpdb->query( $wpdb->prepare(
		"INSERT INTO $table ( zip, lat, lng, resolved, attempts, created_at )
		 VALUES ( %s, %f, %f, 1, 0, %s )
		 ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng), resolved = 1",
		$zip, $geo['lat'], $geo['lng'], sp_book_now()
	) );

	return sp_book_point( $geo['lat'], $geo['lng'] );
}

/* The live Routes lookup was removed: travel is arithmetic now, and a scheduler that
   depends on a billed API being reachable is a scheduler that fails on the worst day. The
   travel table keeps a `source` column so measured times can still be batch-loaded later. */


/*--------------------------------------------------------------
# Points for bookings and resources
--------------------------------------------------------------*/

/** Where a booking happens. Null when it has no usable coordinates. */
function sp_book_booking_point( array $booking ): ?array {
	return sp_book_point( $booking['lat'] ?? 0, $booking['lng'] ?? 0 );
}

/**
 * Where a resource starts and ends its day — the anchor for the first and last
 * appointment, which have no neighbouring client to travel from. Falls back to
 * the resource's home base location when it has one.
 */
function sp_book_resource_point( ?array $resource ): ?array {
	if ( ! $resource ) return null;

	$meta = json_decode( (string) ( $resource['meta'] ?? '' ), true );
	if ( is_array( $meta ) ) {
		$p = sp_book_point( $meta['lat'] ?? 0, $meta['lng'] ?? 0 );
		if ( $p ) return $p;
	}

	if ( ! empty( $resource['location_id'] ) && function_exists( 'site_pulse_get_location' ) ) {
		$loc = site_pulse_get_location( (int) $resource['location_id'] );
		if ( $loc ) {
			$p = sp_book_point( $loc['lat'] ?? 0, $loc['lng'] ?? 0 );
			if ( $p ) return $p;
		}
	}
	return null;
}

/**
 * Where a request is headed, from its ZIP.
 *
 * Scheduling deliberately uses the ZIP centre, not the street address: it is accurate enough to
 * space a day correctly, it keeps the travel matrix tiny, and it is a far smaller thing to ask a
 * stranger for before showing them any times. The street address is still collected for the
 * appointment itself — it just isn't what decides the schedule.
 */
function sp_book_dest_from_request(): ?array {
	$p = sp_book_point( $_POST['lat'] ?? 0, $_POST['lng'] ?? 0 );
	if ( $p ) return $p;
	return sp_book_zip_point( sp_book_post( 'zip' ) );
}
