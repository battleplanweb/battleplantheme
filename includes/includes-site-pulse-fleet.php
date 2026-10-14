<?php
/* Battle Plan Web Design — Site Pulse: Fleet Pulse HUB (agency-only "My Sites" view)

/*--------------------------------------------------------------
The inbound half of Fleet Pulse, and the screen it feeds. Runs ONLY on the hub
(bp-webdev.com): every Battle Plan site POSTs a nightly snapshot of itself here
(includes-fleet-pulse-client.php), this stores it, and the Site Pulse dashboard renders
the whole fleet on one sortable screen — traffic, last audit, keyword position, reviews,
and whether each site's own nightly jobs are still running.

Mirrors includes-site-pulse-agency-reviews.php: it ships in the framework to every client
install but is inert everywhere except the hub, gated on sp_fleet_is_hub().

AUTH reuses the GBP pairing — no new secrets, no new config. A pushing site signs with the
`bpgbp_site_secret` it generated during bpgbp_client_autopair(); this verifies against the
matching row in wp_bpgbp_sites. A site that isn't paired, or is still sitting in the
pending queue, gets a 403 and never lands in the table — it shows on the dashboard as
"never reported", which is the signal you want rather than an error you have to go find.

THE SITE LIST IS THE REGISTRY, NOT THE SNAPSHOTS. Every active row in wp_bpgbp_sites is a
row on the dashboard, LEFT JOINed to its latest snapshot. A site that stops reporting
therefore stays visible and goes stale, instead of quietly vanishing from the list — the
failure mode that would make this whole dashboard untrustworthy.

Storage: one row per site per day (a forced re-push overwrites that day rather than
stacking), pruned past BP_FLEET_KEEP_DAYS so the trend sparklines have history without the
table growing without limit.
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

const BP_FLEET_DB_VERSION  = '1.0';
const BP_FLEET_KEEP_DAYS   = 120;   // snapshot retention
const BP_FLEET_MAX_SKEW    = 900;   // 15 min, matches the GBP hub's clock-skew allowance
const BP_FLEET_STALE_HOURS = 48;    // no snapshot in this long = "not reporting"
const BP_FLEET_AUDIT_DAYS  = 90;    // audit older than this = "audit due"
const BP_FLEET_CRON_DAYS   = 7;     // a client-side chron job quiet this long = "job stalled"
const BP_FLEET_DROP_PCT    = 30;    // 30-day sessions down this much vs prior 30 = "traffic drop"


/*--------------------------------------------------------------
# Gate + schema
--------------------------------------------------------------*/

/** This install is the hub: it holds the GBP token and the client registry lives here. */
function sp_fleet_is_hub(): bool {
	return defined( 'BPGBP_REFRESH_TOKEN' ) && (bool) BPGBP_REFRESH_TOKEN;
}

/** Agency-only, and never for an impersonated non-agency user (effective-user check). */
function sp_fleet_can_view(): bool {
	if ( ! function_exists( 'site_pulse_effective_user_id' ) ) return false;
	$user_id = site_pulse_effective_user_id();
	return site_pulse_user_can( $user_id, 'view_fleet' )
		|| site_pulse_user_can( $user_id, 'manage_fleet' )
		|| site_pulse_is_god( $user_id );
}

function sp_fleet_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'bp_fleet_snapshots';
}

add_action( 'init', 'sp_fleet_install_db' );
function sp_fleet_install_db(): void {
	if ( ! sp_fleet_is_hub() ) return;
	if ( get_option( 'bp_fleet_db_version' ) === BP_FLEET_DB_VERSION ) return;

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$table   = sp_fleet_table();

	// The denormalised columns exist purely so the fleet list can sort and flag without
	// json_decode()-ing every row on every page load. `payload` stays the source of truth.
	dbDelta( "CREATE TABLE $table (
		id int(11) NOT NULL AUTO_INCREMENT,
		site_key varchar(100) NOT NULL,
		snapshot_date date NOT NULL,
		captured_at datetime NOT NULL,
		received_at datetime NOT NULL,
		sessions_30d int(11) NOT NULL DEFAULT 0,
		sessions_30d_prior int(11) NOT NULL DEFAULT 0,
		sessions_7d int(11) NOT NULL DEFAULT 0,
		audit_at datetime DEFAULT NULL,
		audit_score tinyint(4) DEFAULT NULL,
		kw_avg_rank decimal(5,1) DEFAULT NULL,
		kw_top10 int(11) NOT NULL DEFAULT 0,
		review_rating decimal(3,2) DEFAULT NULL,
		review_count int(11) NOT NULL DEFAULT 0,
		payload longtext NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY site_day (site_key, snapshot_date),
		KEY captured_at (captured_at)
	) $charset;" );

	update_option( 'bp_fleet_db_version', BP_FLEET_DB_VERSION );
}


/*--------------------------------------------------------------
# Ingest — POST bpfleet/v1/report
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	if ( ! sp_fleet_is_hub() ) return;
	register_rest_route( 'bpfleet/v1', '/report', [
		'methods'             => 'POST',
		'callback'            => 'sp_fleet_handle_report',
		'permission_callback' => 'sp_fleet_verify_signature',
	] );
} );

/**
 * Per-site HMAC over `timestamp . "." . rawBody`, verified against the registry secret —
 * the same envelope BPGBP_Hub::verify_site_signature() uses.
 *
 * Verified separately rather than calling that method because it additionally requires the
 * site to have a mapped GBP *location*. Plenty of sites in the fleet have no GBP listing
 * and must still be able to report their traffic and audit status.
 */
function sp_fleet_verify_signature( WP_REST_Request $request ) {
	$site_key  = (string) $request->get_header( 'x-bpgbp-site' );
	$timestamp = (string) $request->get_header( 'x-bpgbp-timestamp' );
	$signature = (string) $request->get_header( 'x-bpgbp-signature' );

	if ( '' === $site_key || '' === $timestamp || '' === $signature ) {
		return new WP_Error( 'bp_fleet_missing_auth', 'Missing authentication headers.', [ 'status' => 401 ] );
	}
	if ( abs( time() - (int) $timestamp ) > BP_FLEET_MAX_SKEW ) {
		return new WP_Error( 'bp_fleet_stale', 'Request timestamp outside allowed window.', [ 'status' => 401 ] );
	}

	global $wpdb;
	$secret = $wpdb->get_var( $wpdb->prepare(
		'SELECT secret FROM ' . bpgbp_registry_table() . ' WHERE site_key = %s AND status = %s',
		$site_key,
		'active'
	) );
	if ( ! $secret ) {
		// Pending (awaiting your approval) and unknown keys are the same answer on purpose —
		// this endpoint shouldn't tell an unauthenticated caller which site keys exist.
		return new WP_Error( 'bp_fleet_unknown_site', 'Unknown or unapproved site key.', [ 'status' => 403 ] );
	}

	$expected = hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), $secret );
	if ( ! hash_equals( $expected, $signature ) ) {
		return new WP_Error( 'bp_fleet_bad_signature', 'Signature mismatch.', [ 'status' => 403 ] );
	}

	$request->set_param( '_bp_fleet_site_key', $site_key );
	return true;
}

function sp_fleet_handle_report( WP_REST_Request $request ) {
	$site_key = (string) $request->get_param( '_bp_fleet_site_key' );
	$payload  = json_decode( $request->get_body(), true );

	if ( ! is_array( $payload ) || empty( $payload['v'] ) ) {
		return new WP_Error( 'bp_fleet_bad_payload', 'Malformed snapshot.', [ 'status' => 400 ] );
	}

	sp_fleet_store( $site_key, $payload );
	sp_fleet_prune();

	// Keep the registry's label/URL fresh from what the site reports about itself, so a
	// rebranded or migrated client doesn't sit in the list under its old name forever.
	sp_fleet_refresh_registry_meta( $site_key, $payload );

	return new WP_REST_Response( [ 'ok' => true, 'site_key' => $site_key ], 200 );
}

/** Upsert one site's snapshot for its capture date. */
function sp_fleet_store( string $site_key, array $payload ): void {
	global $wpdb;

	$captured = (int) ( $payload['captured_at'] ?? time() );
	if ( $captured <= 0 ) $captured = time();

	$traffic  = is_array( $payload['traffic'] ?? null )  ? $payload['traffic']  : [];
	$audit    = is_array( $payload['audit'] ?? null )    ? $payload['audit']    : [];
	$keywords = is_array( $payload['keywords'] ?? null ) ? $payload['keywords'] : [];
	$reviews  = is_array( $payload['reviews'] ?? null )  ? $payload['reviews']  : [];

	$audit_at = (int) ( $audit['last_at'] ?? 0 );

	$row = [
		'site_key'           => $site_key,
		'snapshot_date'      => gmdate( 'Y-m-d', $captured ),
		'captured_at'        => gmdate( 'Y-m-d H:i:s', $captured ),
		'received_at'        => gmdate( 'Y-m-d H:i:s' ),
		'sessions_30d'       => (int) ( $traffic['d30']['sessions'] ?? 0 ),
		'sessions_30d_prior' => (int) ( $traffic['d30_prior']['sessions'] ?? 0 ),
		'sessions_7d'        => (int) ( $traffic['d7']['sessions'] ?? 0 ),
		'audit_at'           => $audit_at ? gmdate( 'Y-m-d H:i:s', $audit_at ) : null,
		'audit_score'        => isset( $audit['avg_score'] ) && null !== $audit['avg_score'] ? (int) $audit['avg_score'] : null,
		'kw_avg_rank'        => isset( $keywords['avg_rank'] ) && null !== $keywords['avg_rank'] ? (float) $keywords['avg_rank'] : null,
		'kw_top10'           => (int) ( $keywords['top10'] ?? 0 ),
		'review_rating'      => isset( $reviews['rating'] ) && null !== $reviews['rating'] ? (float) $reviews['rating'] : null,
		'review_count'       => (int) ( $reviews['count'] ?? 0 ),
		'payload'            => wp_json_encode( $payload ),
	];

	$existing = $wpdb->get_var( $wpdb->prepare(
		'SELECT id FROM ' . sp_fleet_table() . ' WHERE site_key = %s AND snapshot_date = %s',
		$site_key,
		$row['snapshot_date']
	) );

	if ( $existing ) {
		$wpdb->update( sp_fleet_table(), $row, [ 'id' => (int) $existing ] );
	} else {
		$wpdb->insert( sp_fleet_table(), $row );
	}
}

/** Drop snapshots past the retention window. Cheap, and runs on ingest so it needs no cron. */
function sp_fleet_prune(): void {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		'DELETE FROM ' . sp_fleet_table() . ' WHERE snapshot_date < %s',
		gmdate( 'Y-m-d', time() - ( BP_FLEET_KEEP_DAYS * DAY_IN_SECONDS ) )
	) );
}

/** Keep the registry row's label / URL in step with what the site now calls itself. */
function sp_fleet_refresh_registry_meta( string $site_key, array $payload ): void {
	global $wpdb;
	$label = trim( (string) ( $payload['site']['label'] ?? '' ) );
	$url   = trim( (string) ( $payload['site']['url'] ?? '' ) );
	if ( '' === $label && '' === $url ) return;

	$data = [ 'updated_at' => current_time( 'mysql' ) ];
	if ( '' !== $label ) $data['label']    = $label;
	if ( '' !== $url )   $data['site_url'] = $url;

	$wpdb->update( bpgbp_registry_table(), $data, [ 'site_key' => $site_key ] );
}

/**
 * The hub's OWN site reports in-process. It can't HTTP-POST to itself (it has no client
 * pairing — bpgbp_client_autopair() explicitly skips the hub), so Chron E on this install
 * calls straight through to sp_fleet_store() instead of going out and back over the wire.
 */
add_action( 'bp_fleet_capture_self', 'sp_fleet_capture_self' );
function sp_fleet_capture_self(): void {
	if ( ! sp_fleet_is_hub() ) return;

	$client = get_template_directory() . '/includes/includes-fleet-pulse-client.php';
	if ( ! file_exists( $client ) ) return;
	require_once $client;
	if ( ! function_exists( 'bp_fleet_collect_snapshot' ) ) return;

	$key = (string) get_option( 'bpgbp_site_key', '' );
	if ( '' === $key && function_exists( 'bpgbp_key_from_url' ) ) {
		$key = bpgbp_key_from_url( home_url() );
		update_option( 'bpgbp_site_key', $key );
	}
	if ( '' === $key ) return;

	sp_fleet_store( $key, bp_fleet_collect_snapshot() );
	sp_fleet_prune();
}


/*--------------------------------------------------------------
# Read side — the fleet list
--------------------------------------------------------------*/

/**
 * Every active registry site, joined to its most recent snapshot.
 *
 * The correlated subquery picks each site's newest row; the fleet is ~200 sites with one
 * row per site per day, so this stays comfortably small — no window functions needed (and
 * none available on the MySQL 5.7 installs still out there).
 */
function sp_fleet_rows(): array {
	global $wpdb;

	$reg  = bpgbp_registry_table();
	$snap = sp_fleet_table();

	$rows = $wpdb->get_results(
		"SELECT r.site_key, r.label, r.site_url, r.status,
		        s.captured_at, s.sessions_30d, s.sessions_30d_prior, s.sessions_7d,
		        s.audit_at, s.audit_score, s.kw_avg_rank, s.kw_top10,
		        s.review_rating, s.review_count, s.payload
		   FROM $reg r
		   LEFT JOIN $snap s
		     ON s.site_key = r.site_key
		    AND s.id = (SELECT id FROM $snap s2 WHERE s2.site_key = r.site_key ORDER BY s2.snapshot_date DESC LIMIT 1)
		  WHERE r.status = 'active'
		  ORDER BY r.label ASC, r.site_key ASC",
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : [];
}

/**
 * Sparkline series for EVERY site in one query, keyed by site_key, oldest → newest.
 *
 * Deliberately not a per-site lookup: the fleet list renders ~200 rows, and a query each
 * would put 200 round-trips behind one panel load. One 30-day sweep is a few thousand
 * narrow rows, grouped in PHP.
 */
function sp_fleet_sparklines( int $days = 30 ): array {
	global $wpdb;

	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT site_key, sessions_7d FROM ' . sp_fleet_table() . '
		  WHERE snapshot_date >= %s
		  ORDER BY site_key ASC, snapshot_date ASC',
		gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) )
	), ARRAY_A );

	$out = [];
	foreach ( (array) $rows as $r ) {
		$out[ (string) $r['site_key'] ][] = (int) $r['sessions_7d'];
	}
	return $out;
}

/**
 * The alert chips for one site. Order matters — the list sorts by these, so the most
 * actionable ("this site stopped reporting entirely") has to outrank the merely untidy
 * ("three plugin updates pending").
 *
 * Each chip is [ key, label, tone ]; tone drives the colour class in the panel.
 */
function sp_fleet_chips( array $row ): array {
	$chips   = [];
	$payload = $row['payload'] ? json_decode( (string) $row['payload'], true ) : null;
	$payload = is_array( $payload ) ? $payload : [];

	// 1. Not reporting at all.
	if ( empty( $row['captured_at'] ) ) {
		return [ [ 'key' => 'never', 'label' => 'Never reported', 'tone' => 'bad' ] ];
	}
	$captured  = (int) strtotime( (string) $row['captured_at'] . ' UTC' );
	$age_hours = ( time() - $captured ) / HOUR_IN_SECONDS;
	if ( $age_hours > BP_FLEET_STALE_HOURS ) {
		$chips[] = [ 'key' => 'stale', 'label' => 'Silent ' . (int) round( $age_hours / 24 ) . 'd', 'tone' => 'bad' ];
	}

	// 2. Its own nightly jobs have stopped — the numbers below may be frozen, not real.
	//    Measured against CAPTURE time, not now: these timestamps were frozen into the
	//    payload the night it was sent, so comparing them to time() would turn every stale
	//    snapshot into a false "all its jobs stalled" — reporting the age of the snapshot
	//    rather than the health of the site. How long it has been silent is chip 1's job.
	$cron    = is_array( $payload['health']['chron'] ?? null ) ? $payload['health']['chron'] : [];
	$stalled = [];
	foreach ( [ 'a' => 'GBP', 'b' => 'Housekeeping', 'c' => 'Analytics', 'd' => 'Keywords' ] as $slot => $name ) {
		$ts = (int) ( $cron[ $slot ] ?? 0 );
		// Chron D is weekly by design, so it gets a wider window before it counts as stalled.
		$limit = ( 'd' === $slot ? BP_FLEET_CRON_DAYS * 2 : BP_FLEET_CRON_DAYS ) * DAY_IN_SECONDS;
		if ( $ts > 0 && ( $captured - $ts ) > $limit ) $stalled[] = $name;
	}
	if ( $stalled ) {
		$chips[] = [ 'key' => 'cron', 'label' => implode( ' + ', $stalled ) . ' stalled', 'tone' => 'bad' ];
	}

	// 3. Traffic falling off a cliff month over month. Needs a meaningful prior month —
	//    a site that went 4 sessions → 2 is noise, not a 50% collapse.
	$prior = (int) $row['sessions_30d_prior'];
	$now   = (int) $row['sessions_30d'];
	if ( $prior >= 50 ) {
		$delta = ( ( $now - $prior ) / $prior ) * 100;
		if ( $delta <= -BP_FLEET_DROP_PCT ) {
			$chips[] = [ 'key' => 'traffic', 'label' => 'Traffic ' . (int) round( $delta ) . '%', 'tone' => 'bad' ];
		}
	}

	// 4. Audit overdue.
	if ( empty( $row['audit_at'] ) ) {
		$chips[] = [ 'key' => 'audit', 'label' => 'No audit yet', 'tone' => 'warn' ];
	} else {
		$days = ( time() - strtotime( (string) $row['audit_at'] . ' UTC' ) ) / DAY_IN_SECONDS;
		if ( $days > BP_FLEET_AUDIT_DAYS ) {
			$chips[] = [ 'key' => 'audit', 'label' => 'Audit ' . (int) round( $days ) . 'd old', 'tone' => 'warn' ];
		}
	}

	// 5. An audit run that died partway leaves its breadcrumb behind.
	if ( ! empty( $payload['health']['audit_stuck'] ) ) {
		$chips[] = [ 'key' => 'audit_stuck', 'label' => 'Audit stuck', 'tone' => 'warn' ];
	}

	// 6. Keywords losing ground.
	$kw = is_array( $payload['keywords'] ?? null ) ? $payload['keywords'] : [];
	if ( (int) ( $kw['declined'] ?? 0 ) > (int) ( $kw['improved'] ?? 0 ) && (int) ( $kw['declined'] ?? 0 ) >= 3 ) {
		$chips[] = [ 'key' => 'keywords', 'label' => (int) $kw['declined'] . ' keywords down', 'tone' => 'warn' ];
	}

	// 7. Housekeeping.
	$updates = (int) ( $payload['health']['updates']['plugins'] ?? 0 ) + (int) ( $payload['health']['updates']['themes'] ?? 0 );
	if ( (int) ( $payload['health']['updates']['core'] ?? 0 ) > 0 ) {
		$chips[] = [ 'key' => 'core', 'label' => 'WP update', 'tone' => 'info' ];
	}
	if ( $updates > 0 ) {
		$chips[] = [ 'key' => 'updates', 'label' => $updates . ' update' . ( 1 === $updates ? '' : 's' ), 'tone' => 'info' ];
	}

	return $chips;
}

/**
 * Sessions + trend % for every traffic window the panel can cycle through (30d / 3m / 6m / 12m).
 *
 * 30d comes from the denormalised columns so snapshots pushed before the longer windows
 * existed still show it; the rest come from the payload and are null until a site has run
 * the updated collector. A % is only given when the prior window is ~fully covered by the
 * site's GA4 history — otherwise a young site's 12m reads as an enormous fake gain.
 */
function sp_fleet_traffic_windows( array $row, array $payload ): array {
	$traffic = is_array( $payload['traffic'] ?? null ) ? $payload['traffic'] : [];
	$out     = [];

	foreach ( [ 30, 90, 180, 365 ] as $days ) {
		$key = 'd' . $days;
		if ( 30 === $days ) {
			$now   = (int) $row['sessions_30d'];
			$prior = (int) $row['sessions_30d_prior'];
			$full  = $prior > 0;
		} elseif ( isset( $traffic[ $key ]['sessions'] ) ) {
			$now   = (int) $traffic[ $key ]['sessions'];
			$prior = (int) ( $traffic[ $key . '_prior' ]['sessions'] ?? 0 );
			$full  = (int) ( $traffic[ $key . '_prior' ]['days'] ?? 0 ) >= $days * 0.9;
		} else {
			$out[ $key ] = null;
			continue;
		}
		$out[ $key ] = [
			'sessions' => $now,
			'prior'    => $full ? $prior : 0,
			'pct'      => $full && $prior > 0 ? (int) round( ( ( $now - $prior ) / $prior ) * 100 ) : null,
		];
	}
	return $out;
}

/** Worst tone present, for sorting problem sites to the top. */
function sp_fleet_severity( array $chips ): int {
	$worst = 0;
	foreach ( $chips as $c ) {
		$rank = [ 'info' => 1, 'warn' => 2, 'bad' => 3 ][ $c['tone'] ] ?? 0;
		if ( $rank > $worst ) $worst = $rank;
	}
	return $worst;
}


/*--------------------------------------------------------------
# AJAX — the fleet panel
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_fleet', 'sp_fleet_ajax_get_fleet' );
function sp_fleet_ajax_get_fleet(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_fleet_can_view() ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	if ( ! sp_fleet_is_hub() )   wp_send_json_error( [ 'message' => 'This site is not the fleet hub.' ] );

	$sites  = [];
	$sparks = sp_fleet_sparklines();
	$totals = [ 'sites' => 0, 'reporting' => 0, 'sessions_30d' => 0, 'sessions_30d_prior' => 0, 'alerts' => 0, 'audits_due' => 0 ];
	// Fleet-wide sum per traffic window, so the stat card follows the period being viewed.
	$window_totals = [];

	foreach ( sp_fleet_rows() as $row ) {
		$chips   = sp_fleet_chips( $row );
		$payload = $row['payload'] ? json_decode( (string) $row['payload'], true ) : null;
		$payload = is_array( $payload ) ? $payload : [];

		$reporting = ! empty( $row['captured_at'] );
		$totals['sites']++;
		if ( $reporting ) $totals['reporting']++;
		$totals['sessions_30d']       += (int) $row['sessions_30d'];
		$totals['sessions_30d_prior'] += (int) $row['sessions_30d_prior'];
		if ( sp_fleet_severity( $chips ) >= 2 ) $totals['alerts']++;
		foreach ( $chips as $c ) if ( 'audit' === $c['key'] ) $totals['audits_due']++;

		$windows = sp_fleet_traffic_windows( $row, $payload );
		foreach ( $windows as $wkey => $w ) {
			if ( ! $w ) continue;
			$t = $window_totals[ $wkey ] ?? [ 'sessions' => 0, 'cmp' => 0, 'prior' => 0 ];
			$t['sessions'] += $w['sessions'];
			// The fleet % compares like with like: only sites that have a full prior window.
			if ( $w['prior'] > 0 ) {
				$t['cmp']   += $w['sessions'];
				$t['prior'] += $w['prior'];
			}
			$window_totals[ $wkey ] = $t;
		}

		$sites[] = [
			'key'          => (string) $row['site_key'],
			'label'        => (string) ( $row['label'] ?: $row['site_key'] ),
			// rtrim so the dashboard's "{url}/wp-admin/" links don't end up with a double slash
			// on the rows whose registry URL was saved with a trailing one.
			'url'          => rtrim( (string) $row['site_url'], '/' ),
			'reporting'    => $reporting,
			'captured_at'  => $row['captured_at'] ? strtotime( (string) $row['captured_at'] . ' UTC' ) : 0,
			'sessions_30d' => (int) $row['sessions_30d'],
			'sessions_7d'  => (int) $row['sessions_7d'],
			'traffic_pct'  => (int) $row['sessions_30d_prior'] > 0
				? (int) round( ( ( (int) $row['sessions_30d'] - (int) $row['sessions_30d_prior'] ) / (int) $row['sessions_30d_prior'] ) * 100 )
				: null,
			'traffic'      => $windows,
			'audit_at'     => $row['audit_at'] ? strtotime( (string) $row['audit_at'] . ' UTC' ) : 0,
			'audit_score'  => null !== $row['audit_score'] ? (int) $row['audit_score'] : null,
			'kw_avg_rank'  => null !== $row['kw_avg_rank'] ? (float) $row['kw_avg_rank'] : null,
			'kw_top10'     => (int) $row['kw_top10'],
			'kw_tracked'   => (int) ( $payload['keywords']['tracked'] ?? 0 ),
			'rating'       => null !== $row['review_rating'] ? (float) $row['review_rating'] : null,
			'reviews'      => (int) $row['review_count'],
			'wp'           => (string) ( $payload['health']['wp'] ?? '' ),
			'php'          => (string) ( $payload['health']['php'] ?? '' ),
			'theme'        => (string) ( $payload['site']['theme'] ?? '' ),
			'summary'      => (string) ( $payload['audit']['summary'] ?? '' ),
			'spark'        => $sparks[ (string) $row['site_key'] ] ?? [],
			'chips'        => $chips,
			'severity'     => sp_fleet_severity( $chips ),
		];
	}

	$totals['traffic_pct'] = $totals['sessions_30d_prior'] > 0
		? (int) round( ( ( $totals['sessions_30d'] - $totals['sessions_30d_prior'] ) / $totals['sessions_30d_prior'] ) * 100 )
		: null;

	foreach ( $window_totals as $wkey => $w ) {
		$window_totals[ $wkey ] = [
			'sessions' => $w['sessions'],
			'pct'      => $w['prior'] > 0 ? (int) round( ( ( $w['cmp'] - $w['prior'] ) / $w['prior'] ) * 100 ) : null,
		];
	}
	$totals['traffic'] = $window_totals;

	wp_send_json_success( [ 'sites' => $sites, 'totals' => $totals ] );
}
