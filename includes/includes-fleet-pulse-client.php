<?php
/* Battle Plan Web Design — Fleet Pulse: CLIENT (every site in the fleet)

/*--------------------------------------------------------------
The nightly outbound half of Fleet Pulse. Every Battle Plan site collects a small
snapshot of itself — traffic, last audit, keyword position, reviews, and whether its
own nightly jobs are actually running — and POSTs it to the hub (bp-webdev.com), which
renders the whole fleet on one Site Pulse screen.

WHY THIS IS CHEAP: it invents no data. Every number below is already sitting in an
option that Chron A–D wrote. This file reads, summarises, signs, and sends — one HTTP
request per site per night, a few KB. Nothing here queries an external API.

AUTH — no new config. It reuses the GBP hub pairing that every site already completed:
bpgbp_client_autopair() (includes-gbp-hub-registry.php) generated this site's
`bpgbp_site_key` + `bpgbp_site_secret` and registered them with the hub, where you
approved them into wp_bpgbp_sites. Fleet Pulse signs with that same secret. A site that
has never paired (or is still pending approval) simply gets a 403 and stays quiet — it
shows up on the hub as "never reported", which is itself the signal you want.

DIRECTION: client → hub only. The hub never has to reach back into a client site.

  Turn it off on one site:   define( 'BP_FLEET_PUSH', false );   // wp-config
  Force a send on next hit:  update_option( 'bp_force_chron_e', true );

Loaded on demand by Chron E (functions-chron.php) — not on normal page loads.
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

const BP_FLEET_PAYLOAD_VERSION = 1;
const BP_FLEET_ROUTE           = '/?rest_route=/bpfleet/v1/report';
const BP_FLEET_TIMEOUT         = 20;


/*--------------------------------------------------------------
# Gate
--------------------------------------------------------------*/

/**
 * This site should report if it isn't the hub itself, hasn't been switched off, and has
 * a pairing to sign with. The hub is excluded because it reports to itself in-process
 * (see bp_fleet_hub_capture_self() on the hub side) rather than HTTP-looping to localhost.
 */
function bp_fleet_client_can_push(): bool {
	if ( defined( 'BP_FLEET_PUSH' ) && ! BP_FLEET_PUSH )                 return false;
	if ( defined( 'BPGBP_REFRESH_TOKEN' ) && BPGBP_REFRESH_TOKEN )       return false;  // the hub
	if ( ! function_exists( 'bpgbp_cfg' ) )                              return false;
	return '' !== bpgbp_cfg( 'SITE_KEY' ) && '' !== bpgbp_cfg( 'SITE_SECRET' ) && '' !== bp_fleet_hub_url();
}

/** The hub to report to — the same one the GBP pairing uses. */
function bp_fleet_hub_url(): string {
	$hub = function_exists( 'bpgbp_cfg' ) ? bpgbp_cfg( 'HUB_URL' ) : '';
	if ( '' === $hub && defined( 'BPGBP_PAIR_HUB' ) ) $hub = (string) BPGBP_PAIR_HUB;
	return rtrim( (string) $hub, '/' );
}


/*--------------------------------------------------------------
# Collectors — one per metric group, each independently fault-tolerant
#
# Every one returns a plain array and NEVER throws: a site with no GA4 property, no
# audit, or no tracked keywords reports zeros/nulls rather than failing the whole push.
# "This site has no keyword data" is a fact the fleet view wants to show, not an error.
--------------------------------------------------------------*/

/**
 * Sum the raw GA4 daily rows over a window ending `$offset` days back.
 * bp_ga4_daily_clean is [ 'Y-m-d' => [sessions,users,newUsers,engagedSessions,pageviews,engagementDuration] ].
 * Windows are counted back from the LAST DATE PRESENT, not from today — GA4 lags a day or
 * two, so anchoring on today would silently shear the most recent (and most interesting)
 * days off every window and make every site look like it was losing traffic.
 */
function bp_fleet_ga4_window( array $daily, int $days, int $offset = 0 ): array {
	$out = [ 'sessions' => 0, 'users' => 0, 'newUsers' => 0, 'engagedSessions' => 0, 'pageviews' => 0, 'duration' => 0.0 ];
	if ( ! $daily ) return $out;

	$dates = array_keys( $daily );
	sort( $dates );
	$anchor = end( $dates );
	if ( ! $anchor ) return $out;

	$end   = strtotime( $anchor . ' -' . $offset . ' days' );
	$start = strtotime( $anchor . ' -' . ( $offset + $days - 1 ) . ' days' );

	foreach ( $daily as $date => $row ) {
		$ts = strtotime( (string) $date );
		if ( false === $ts || $ts < $start || $ts > $end ) continue;
		$out['sessions']        += (int)   ( $row['sessions'] ?? 0 );
		$out['users']           += (int)   ( $row['users'] ?? 0 );
		$out['newUsers']        += (int)   ( $row['newUsers'] ?? 0 );
		$out['engagedSessions'] += (int)   ( $row['engagedSessions'] ?? 0 );
		$out['pageviews']       += (int)   ( $row['pageviews'] ?? 0 );
		$out['duration']        += (float) ( $row['engagementDuration'] ?? $row['duration'] ?? 0 );
	}
	$out['duration'] = round( $out['duration'], 1 );
	return $out;
}

/** Traffic: two windows plus the matching prior window, so the hub can show a real trend arrow. */
function bp_fleet_collect_traffic(): array {
	$daily = get_option( 'bp_ga4_daily_clean' );
	if ( ! is_array( $daily ) || ! $daily ) {
		return [ 'has_data' => false, 'days' => 0, 'last_date' => '', 'channels' => [] ];
	}

	$dates = array_keys( $daily );
	sort( $dates );

	return [
		'has_data'  => true,
		'days'      => count( $daily ),
		'last_date' => (string) end( $dates ),
		'd7'        => bp_fleet_ga4_window( $daily, 7 ),
		'd7_prior'  => bp_fleet_ga4_window( $daily, 7, 7 ),
		'd30'       => bp_fleet_ga4_window( $daily, 30 ),
		'd30_prior' => bp_fleet_ga4_window( $daily, 30, 30 ),
		'channels'  => bp_fleet_collect_channels(),
	];
}

/**
 * Top traffic channels for the most recent complete month, from the per-channel monthly
 * series (bp_ga4_channel_history[YYYYMM][channel] = {sessions,...}). Paid vs organic at a
 * glance across the fleet. Capped at 8 channels so the payload stays small.
 */
function bp_fleet_collect_channels(): array {
	$hist = get_option( 'bp_ga4_channel_history' );
	if ( ! is_array( $hist ) || ! $hist ) return [];

	$months = array_keys( $hist );
	sort( $months );
	$month = (string) end( $months );
	if ( ! isset( $hist[ $month ] ) || ! is_array( $hist[ $month ] ) ) return [];

	$out = [];
	foreach ( $hist[ $month ] as $channel => $row ) {
		$out[] = [ 'name' => (string) $channel, 'sessions' => (int) ( $row['sessions'] ?? 0 ) ];
	}
	usort( $out, static fn( $a, $b ) => $b['sessions'] <=> $a['sessions'] );

	return [ 'month' => $month, 'rows' => array_slice( $out, 0, 8 ) ];
}

/**
 * Last AI Site Audit: when it ran and how it scored. The score is the mean of the
 * per-category scores, matching what the audit screen prints as "avg score" so the
 * fleet number and the site's own number can never disagree.
 */
function bp_fleet_collect_audit(): array {
	$out = [ 'last_at' => 0, 'last_date' => '', 'avg_score' => null, 'scores' => [], 'summary' => '', 'count' => 0 ];

	$last_at = (int) get_option( 'bp_audit_time', 0 );
	if ( $last_at ) $out['last_at'] = $last_at;

	$hist = get_option( 'bp_site_audit_ai_history' );
	if ( ! is_array( $hist ) || ! $hist ) return $out;

	$out['count'] = count( $hist );
	ksort( $hist );
	$latest = end( $hist );
	if ( ! is_array( $latest ) ) return $out;

	$scores = is_array( $latest['scores'] ?? null ) ? $latest['scores'] : [];
	$out['scores']    = array_map( 'intval', $scores );
	$out['avg_score'] = $scores ? (int) round( array_sum( array_map( 'intval', $scores ) ) / max( 1, count( $scores ) ) ) : null;
	$out['last_date'] = (string) ( $latest['date'] ?? (string) key( array_slice( $hist, -1, 1, true ) ) );
	$out['summary']   = mb_substr( trim( (string) ( $latest['summary'] ?? '' ) ), 0, 400 );

	// bp_audit_time can be missing on installs that predate it — fall back to the report date.
	if ( ! $out['last_at'] && $out['last_date'] ) {
		$ts = strtotime( $out['last_date'] );
		if ( $ts ) $out['last_at'] = $ts;
	}
	return $out;
}

/**
 * Keyword position summary. Ranks are "lower is better", so `avg_rank` is only averaged
 * over keywords that actually HAVE a rank — folding unranked keywords in as 0 or 100
 * would make a site with a few strong terms look identical to one with none.
 */
function bp_fleet_collect_keywords(): array {
	$out = [
		'tracked' => 0, 'ranked' => 0, 'avg_rank' => null, 'top3' => 0, 'top10' => 0,
		'improved' => 0, 'declined' => 0, 'last_check' => '',
	];
	if ( ! function_exists( 'bp_kw_tracked' ) ) return $out;

	$tracked = bp_kw_tracked();
	$history = function_exists( 'bp_kw_history' ) ? bp_kw_history() : [];
	if ( ! is_array( $tracked ) || ! $tracked ) return $out;

	$out['tracked'] = count( $tracked );
	$sum = 0;

	foreach ( $tracked as $key => $row ) {
		if ( ! is_array( $row ) ) continue;

		$rank = (int) ( $row['last_rank'] ?? 0 );
		if ( $rank > 0 ) {
			$out['ranked']++;
			$sum += $rank;
			if ( $rank <= 3 )  $out['top3']++;
			if ( $rank <= 10 ) $out['top10']++;
		}

		if ( function_exists( 'bp_kw_rank_change' ) && $history ) {
			$change = bp_kw_rank_change( (string) $key, $history );   // positive = moved up
			if ( null !== $change && $change > 0 ) $out['improved']++;
			if ( null !== $change && $change < 0 ) $out['declined']++;
		}

		$check = (string) ( $row['last_check'] ?? '' );
		if ( $check > $out['last_check'] ) $out['last_check'] = $check;
	}

	if ( $out['ranked'] > 0 ) $out['avg_rank'] = round( $sum / $out['ranked'], 1 );
	return $out;
}

/**
 * Google rating + review count, summed across every Place this site tracks. The rating is
 * weighted by each location's review count (a 5.0 from one review must not outweigh a 4.6
 * from four hundred), matching how the footer review bar computes it.
 */
function bp_fleet_collect_reviews(): array {
	$out  = [ 'rating' => null, 'count' => 0, 'locations' => 0 ];
	$info = get_option( 'bp_gbp_update' );
	if ( ! is_array( $info ) || ! $info ) return $out;

	$weighted = 0.0;
	foreach ( $info as $place ) {
		if ( ! is_array( $place ) ) continue;
		$count  = (int)   ( $place['google-reviews'] ?? 0 );
		$rating = (float) ( $place['google-rating'] ?? 0 );
		if ( $count <= 0 ) continue;
		$out['locations']++;
		$out['count'] += $count;
		$weighted     += $rating * $count;
	}
	if ( $out['count'] > 0 ) $out['rating'] = round( $weighted / $out['count'], 2 );
	return $out;
}

/**
 * Site health. The chron timestamps are the load-bearing part: a site whose Chron C
 * stopped weeks ago is still serving pages and looks fine from outside, but its analytics
 * (and this snapshot's traffic numbers) are quietly frozen. Surfacing the run times is the
 * only way that shows up before you happen to open the site.
 */
function bp_fleet_collect_health(): array {
	$updates = [ 'core' => 0, 'plugins' => 0, 'themes' => 0 ];

	// Read the cached update transients only — never force a check. A remote version probe
	// on a nightly cron across the whole fleet is a lot of outbound traffic for a number
	// WordPress already refreshes on its own schedule.
	$core = get_site_transient( 'update_core' );
	if ( isset( $core->updates ) && is_array( $core->updates ) ) {
		foreach ( $core->updates as $u ) {
			if ( isset( $u->response ) && 'upgrade' === $u->response ) { $updates['core'] = 1; break; }
		}
	}
	$plugins = get_site_transient( 'update_plugins' );
	if ( isset( $plugins->response ) && is_array( $plugins->response ) ) $updates['plugins'] = count( $plugins->response );
	$themes  = get_site_transient( 'update_themes' );
	if ( isset( $themes->response ) && is_array( $themes->response ) )   $updates['themes'] = count( $themes->response );

	return [
		'wp'          => get_bloginfo( 'version' ),
		'php'         => PHP_VERSION,
		'https'       => 0 === strpos( (string) home_url(), 'https://' ),
		'updates'     => $updates,
		'chron'       => [
			'a' => (int) get_option( 'bp_chron_a_time', 0 ),
			'b' => (int) get_option( 'bp_chron_b_time', 0 ),
			'c' => (int) get_option( 'bp_chron_c_time', 0 ),
			'd' => (int) get_option( 'bp_chron_d_time', 0 ),
		],
		'audit_stuck' => (bool) get_option( 'bp_site_audit_stage' ),   // a breadcrumb left behind = a run that died mid-flight
	];
}


/*--------------------------------------------------------------
# Assemble + send
--------------------------------------------------------------*/

/** The full nightly snapshot. Public so the hub can call it in-process for its own site. */
function bp_fleet_collect_snapshot(): array {
	$theme = wp_get_theme( get_template() );

	return [
		'v'           => BP_FLEET_PAYLOAD_VERSION,
		'captured_at' => time(),
		'site'        => [
			'url'      => home_url(),
			'label'    => function_exists( 'bp_business_name' ) ? (string) bp_business_name() : (string) get_bloginfo( 'name' ),
			'theme'    => $theme ? (string) $theme->get( 'Version' ) : '',
			'timezone' => (string) ( get_option( 'timezone_string' ) ?: '' ),
		],
		'traffic'     => bp_fleet_collect_traffic(),
		'audit'       => bp_fleet_collect_audit(),
		'keywords'    => bp_fleet_collect_keywords(),
		'reviews'     => bp_fleet_collect_reviews(),
		'health'      => bp_fleet_collect_health(),
	];
}

/**
 * Collect and POST, signed with this site's GBP pairing secret (timestamp + raw body,
 * the same envelope BPGBP_Hub::verify_site_signature() uses — one signing convention
 * across everything that talks to the hub).
 *
 * Failures are logged and swallowed: a hub that's down must never leave a client site's
 * nightly chron in a broken state. The hub notices the silence on its own.
 */
function bp_fleet_client_push(): bool {
	if ( ! bp_fleet_client_can_push() ) return false;

	$body = wp_json_encode( bp_fleet_collect_snapshot() );
	if ( false === $body ) {
		error_log( 'bp_fleet: could not encode snapshot for ' . home_url() );
		return false;
	}

	$ts  = (string) time();
	$sig = hash_hmac( 'sha256', $ts . '.' . $body, bpgbp_cfg( 'SITE_SECRET' ) );

	$res = wp_remote_post( bp_fleet_hub_url() . BP_FLEET_ROUTE, [
		'timeout' => BP_FLEET_TIMEOUT,
		'headers' => [
			'Content-Type'       => 'application/json',
			'X-BPGBP-Site'       => bpgbp_cfg( 'SITE_KEY' ),
			'X-BPGBP-Timestamp'  => $ts,
			'X-BPGBP-Signature'  => $sig,
		],
		'body'    => $body,
	] );

	if ( is_wp_error( $res ) ) {
		error_log( 'bp_fleet: push failed — ' . $res->get_error_message() );
		update_option( 'bp_fleet_last_error', $res->get_error_message(), false );
		return false;
	}

	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 200 !== $code ) {
		$msg = 'HTTP ' . $code . ' — ' . mb_substr( (string) wp_remote_retrieve_body( $res ), 0, 300 );
		error_log( 'bp_fleet: hub rejected push — ' . $msg );
		update_option( 'bp_fleet_last_error', $msg, false );
		return false;
	}

	update_option( 'bp_fleet_last_push', time(), false );
	delete_option( 'bp_fleet_last_error' );
	return true;
}
