<?php
/* Battle Plan Web Design — Site Pulse: Reviews Module (Google Business Profile)

/*--------------------------------------------------------------
Google review aggregation, one-click replies, and one-click testimonials.

Reviews ride the SAME central GBP hub as the auto-poster (gbp-poster/bpgbp-hub.php):
the hub holds the one refresh token and is locked to this site's GBP location, so a
client site never sees the token or the location — it just calls the hub with its
per-site HMAC secret. Reviews live on the legacy Google My Business v4 API, under the
same business.manage scope the hub already uses for localPosts.

This site authenticates to the hub via three wp-config constants (the client side of
the hub's bpgbp-sites.json allowlist):
    define( 'BPGBP_HUB_URL',     'https://your-hub-site.com' );
    define( 'BPGBP_SITE_KEY',    'this-sites-key' );
    define( 'BPGBP_SITE_SECRET', '64-hex-chars-matching-the-hub-entry' );

Everything here is gated by the `reviews` module + the view_reviews / manage_reviews
capabilities. When the module is off those caps go inert (site_pulse_user_can returns
false), so every handler below rejects on its own — no extra module check needed.
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Hub client — signed request to the GBP hub
--------------------------------------------------------------*/

/**
 * Call the GBP hub with this site's HMAC credentials. Mirrors the hub's
 * verify_site_signature(): sign timestamp . '.' . rawBody (empty body for GET).
 * Returns the decoded array on success, or a WP_Error.
 */
function sp_reviews_hub_request( string $method, string $path, ?array $body = null, array $query = [] ) {
	// Credentials come from wp-config constants OR (when the site was auto-paired) stored options.
	$hub_url     = function_exists( 'bpgbp_cfg' ) ? bpgbp_cfg( 'HUB_URL' )     : ( defined( 'BPGBP_HUB_URL' ) ? BPGBP_HUB_URL : '' );
	$site_key    = function_exists( 'bpgbp_cfg' ) ? bpgbp_cfg( 'SITE_KEY' )    : ( defined( 'BPGBP_SITE_KEY' ) ? BPGBP_SITE_KEY : '' );
	$site_secret = function_exists( 'bpgbp_cfg' ) ? bpgbp_cfg( 'SITE_SECRET' ) : ( defined( 'BPGBP_SITE_SECRET' ) ? BPGBP_SITE_SECRET : '' );
	if ( '' === $hub_url || '' === $site_key || '' === $site_secret ) {
		return new WP_Error( 'sp_reviews_unconfigured', 'Google Reviews are not configured for this site yet (not paired with the hub).' );
	}

	// Use the ?rest_route= form, NOT the pretty /wp-json/ path: on the hub the pretty REST URL is
	// 301-redirected (to the homepage), so a client following the redirect receives HTML instead of
	// JSON. ?rest_route= is handled by index.php directly and isn't affected by that redirect.
	$url       = rtrim( $hub_url, '/' ) . '/?rest_route=/bpgbp/v1/' . ltrim( $path, '/' );
	$timestamp = (string) time();
	$raw       = ( null === $body ) ? '' : wp_json_encode( $body );
	$signature = hash_hmac( 'sha256', $timestamp . '.' . $raw, $site_secret );

	// Cache-buster so an edge cache (Cloudflare/WP Engine) can't serve a stale response. Not part of
	// the HMAC (which signs only timestamp + body), so it doesn't affect auth.
	$url .= '&_cb=' . rawurlencode( $timestamp . (string) wp_rand( 1000, 9999 ) );
	foreach ( $query as $qk => $qv ) {
		$url .= '&' . rawurlencode( (string) $qk ) . '=' . rawurlencode( (string) $qv );
	}

	$args = [
		'method'  => $method,
		'timeout' => 25,
		'headers' => [
			'X-BPGBP-Site'      => $site_key,
			'X-BPGBP-Timestamp' => $timestamp,
			'X-BPGBP-Signature' => $signature,
			'Content-Type'      => 'application/json',
			'Cache-Control'     => 'no-cache',
		],
	];
	if ( null !== $body ) $args['body'] = $raw;

	$response = ( 'GET' === $method ) ? wp_remote_get( $url, $args ) : wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) return $response;

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		$msg = ( is_array( $data ) && isset( $data['message'] ) ) ? $data['message'] : ( 'Hub returned ' . $code );
		return new WP_Error( 'sp_reviews_hub', $msg, [ 'status' => $code ] );
	}
	return is_array( $data ) ? $data : [];
}


/*--------------------------------------------------------------
# Store — reviews accumulate in site_pulse_reviews; summary/cursor in config
--------------------------------------------------------------*/

const SP_REVIEWS_META_KEY = 'reviews_meta'; // { total, avg, next_token, synced_at }
const SP_REVIEWS_TTL      = 3600;           // re-sync the newest set at most hourly on plain views

function sp_reviews_table(): string {
	return site_pulse_table( 'reviews' );
}

function sp_reviews_get_meta(): array {
	$d = json_decode( site_pulse_get_setting( SP_REVIEWS_META_KEY, '' ), true );
	return is_array( $d ) ? $d : [];
}
function sp_reviews_set_meta( array $m ): void {
	site_pulse_set_setting( SP_REVIEWS_META_KEY, wp_json_encode( $m ) );
}

function sp_reviews_count(): int {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_reviews_table() );
}

/* per-location meta accessor: meta['loc'][locationId] = { total, avg, next_token, complete, synced_at } */
function sp_reviews_loc_meta( array $meta, string $id ): array {
	return ( isset( $meta['loc'][ $id ] ) && is_array( $meta['loc'][ $id ] ) ) ? $meta['loc'][ $id ] : [];
}

/** The locations this site owns, from the hub (cached a day in meta). [] for a site with none configured. */
function sp_reviews_locations( bool $force = false ): array {
	$meta = sp_reviews_get_meta();
	$age  = time() - (int) ( $meta['loc_list_at'] ?? 0 );
	if ( ! $force && ! empty( $meta['loc_list'] ) && $age < DAY_IN_SECONDS ) return $meta['loc_list'];

	$res = sp_reviews_hub_request( 'GET', 'site-locations' );
	if ( is_wp_error( $res ) ) return $meta['loc_list'] ?? [];

	$list = [];
	foreach ( (array) ( $res['locations'] ?? [] ) as $l ) {
		$id = (string) ( $l['id'] ?? '' );
		if ( '' === $id ) continue;
		$list[] = [ 'id' => $id, 'label' => (string) ( $l['label'] ?? '' ), 'brand' => (string) ( $l['brand'] ?? '' ) ];
	}
	if ( $list ) {
		$meta['loc_list']    = $list;
		$meta['loc_list_at'] = time();
		sp_reviews_set_meta( $meta );
	}
	return $list ?: ( $meta['loc_list'] ?? [] );
}

/** Sum of per-location reported totals (falls back to the row count when none are known yet). */
function sp_reviews_total_expected( array $meta, array $locations, int $fallback ): int {
	$sum = 0; $have = false;
	foreach ( $locations as $L ) {
		$lm = sp_reviews_loc_meta( $meta, $L['id'] );
		if ( isset( $lm['total'] ) ) { $sum += (int) $lm['total']; $have = true; }
	}
	return $have ? $sum : $fallback;
}

/** True while any location hasn't been walked to Google's end yet. */
function sp_reviews_any_incomplete( array $meta, array $locations ): bool {
	foreach ( $locations as $L ) {
		if ( empty( sp_reviews_loc_meta( $meta, $L['id'] )['complete'] ) ) return true;
	}
	return false;
}

/** One-time: stamp the primary location/store/brand onto rows synced before the multi-location build. */
function sp_reviews_backfill_primary_location( array $locations ): void {
	global $wpdb;
	if ( empty( $locations ) ) return;
	$t     = sp_reviews_table();
	$nulls = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE location IS NULL OR location = ''" );
	if ( $nulls < 1 ) return;
	$p = $locations[0]; // legacy rows were all the single (primary) location
	$wpdb->query( $wpdb->prepare(
		"UPDATE $t SET location = %s, store = %s, brand = %s WHERE location IS NULL OR location = ''",
		$p['id'], $p['label'], $p['brand']
	) );
}

// Helpers to safely inline a value into a bulk upsert (esc_sql'd string, or a parsed datetime / NULL).
function sp_reviews_sql_str( string $s ): string {
	return "'" . esc_sql( $s ) . "'";
}
function sp_reviews_sql_date( $rfc ): string {
	$rfc = trim( (string) $rfc );
	if ( $rfc === '' ) return 'NULL';
	$ts = strtotime( $rfc );
	return $ts ? "'" . gmdate( 'Y-m-d H:i:s', $ts ) . "'" : 'NULL';
}

/** Upsert normalized hub reviews into the table, keyed by Google reviewId, stamping location/store/brand. */
function sp_reviews_upsert( array $reviews, string $location, string $store, string $brand ): void {
	global $wpdb;
	$now  = current_time( 'mysql' );
	$locS = sp_reviews_sql_str( $location );
	$stoS = sp_reviews_sql_str( $store );
	$braS = sp_reviews_sql_str( $brand );
	$rows = [];
	foreach ( $reviews as $r ) {
		$rid = (string) ( $r['reviewId'] ?? '' );
		if ( $rid === '' ) continue;
		$reply = is_array( $r['reply'] ?? null ) ? $r['reply'] : null;
		$rows[] = '(' . implode( ',', [
			sp_reviews_sql_str( $rid ),
			sp_reviews_sql_str( (string) ( $r['reviewer'] ?? '' ) ),
			sp_reviews_sql_str( (string) ( $r['photo'] ?? '' ) ),
			(string) (int) ( $r['starRating'] ?? 0 ),
			sp_reviews_sql_str( (string) ( $r['comment'] ?? '' ) ),
			sp_reviews_sql_date( $r['createTime'] ?? '' ),
			$reply ? sp_reviews_sql_str( (string) ( $reply['comment'] ?? '' ) ) : 'NULL',
			$reply ? sp_reviews_sql_date( $reply['updateTime'] ?? '' ) : 'NULL',
			$locS, $stoS, $braS,
			sp_reviews_sql_str( $now ),
		] ) . ')';
	}
	if ( ! $rows ) return;

	$table = sp_reviews_table();
	foreach ( array_chunk( $rows, 100 ) as $chunk ) {
		$wpdb->query(
			"INSERT INTO $table (review_id, reviewer, photo, star_rating, comment, create_time, reply_comment, reply_time, location, store, brand, synced_at) VALUES "
			. implode( ',', $chunk )
			// Note: tags / tagged_at are intentionally left out so re-syncing never wipes AI tags.
			. ' ON DUPLICATE KEY UPDATE reviewer=VALUES(reviewer), photo=VALUES(photo), star_rating=VALUES(star_rating),'
			. ' comment=VALUES(comment), create_time=VALUES(create_time), reply_comment=VALUES(reply_comment),'
			. ' reply_time=VALUES(reply_time), location=VALUES(location), store=VALUES(store), brand=VALUES(brand), synced_at=VALUES(synced_at)'
		);
	}
}

/** Stored reviews, newest first, scoped by date/store/brand so a view never pulls the whole back-catalogue. */
/**
 * Build the shared WHERE clause + bound args for the review list. Scope filters (cutoff/store/brand)
 * plus the secondary list filters (star rating, reply status, AI topic) — all server-side now so the
 * list can be paginated without losing any filtering. Returns [ whereSql, args ].
 */
function sp_reviews_filter_sql( string $cutoff, string $store, string $brand, string $stars, string $reply, string $topic ): array {
	global $wpdb;
	$where = '1=1'; $args = [];
	if ( '' !== $cutoff ) { $where .= ' AND create_time >= %s'; $args[] = $cutoff; }
	if ( '' !== $store )  { $where .= ' AND store = %s';        $args[] = $store; }
	if ( '' !== $brand )  { $where .= ' AND brand = %s';        $args[] = $brand; }
	if ( '' !== $stars )  { $where .= ' AND star_rating = %d';  $args[] = (int) $stars; }
	if ( 'replied' === $reply )   { $where .= " AND reply_comment IS NOT NULL AND reply_comment <> ''"; }
	elseif ( 'unreplied' === $reply ) { $where .= " AND (reply_comment IS NULL OR reply_comment = '')"; }
	// tags is a JSON array of {label,sentiment}; match the topic by its label substring.
	if ( '' !== $topic )  { $where .= ' AND tags LIKE %s'; $args[] = '%"label":"' . $wpdb->esc_like( $topic ) . '"%'; }
	return [ $where, $args ];
}

/** Count of stored reviews matching the given filters (drives "Showing X of Y" + has_more). */
function sp_reviews_count_where( string $cutoff = '', string $store = '', string $brand = '', string $stars = '', string $reply = '', string $topic = '' ): int {
	global $wpdb;
	list( $where, $args ) = sp_reviews_filter_sql( $cutoff, $store, $brand, $stars, $reply, $topic );
	$sql = 'SELECT COUNT(*) FROM ' . sp_reviews_table() . " WHERE $where";
	return (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_var( $sql ) );
}

function sp_reviews_get_rows( string $cutoff = '', string $store = '', string $brand = '', string $stars = '', string $reply = '', string $topic = '', int $limit = 0, int $offset = 0 ): array {
	global $wpdb;
	list( $where, $args ) = sp_reviews_filter_sql( $cutoff, $store, $brand, $stars, $reply, $topic );
	$sql = 'SELECT * FROM ' . sp_reviews_table() . " WHERE $where ORDER BY create_time DESC, id DESC";
	if ( $limit > 0 ) { $sql .= ' LIMIT %d OFFSET %d'; $args[] = $limit; $args[] = max( 0, $offset ); }
	$rows = ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ) ) ?: [];
	$out  = [];
	foreach ( $rows as $r ) {
		$has_reply = ( null !== $r['reply_comment'] && '' !== $r['reply_comment'] );
		$out[] = [
			'reviewId'   => $r['review_id'],
			'reviewer'   => $r['reviewer'],
			'photo'      => $r['photo'],
			'starRating' => (int) $r['star_rating'],
			'comment'    => $r['comment'],
			'createTime' => $r['create_time'] ? gmdate( 'c', strtotime( $r['create_time'] ) ) : '',
			'reply'      => $has_reply ? [ 'comment' => $r['reply_comment'], 'updateTime' => $r['reply_time'] ] : null,
			'tags'       => ( isset( $r['tags'] ) && $r['tags'] ) ? ( json_decode( $r['tags'], true ) ?: [] ) : [],
			'location'   => isset( $r['location'] ) ? (string) $r['location'] : '',
			'store'      => isset( $r['store'] ) ? (string) $r['store'] : '',
			'brand'      => isset( $r['brand'] ) ? (string) $r['brand'] : '',
		];
	}
	return $out;
}

/**
 * Fetch one batch for a location from the hub (starting at $page_token, up to $max), upsert it stamped
 * with that location's store/brand, and return the summary bits — or a WP_Error.
 */
function sp_reviews_sync( string $location, string $store, string $brand, string $page_token = '', int $max = 200 ) {
	$query = [ 'max' => (string) $max, 'location' => $location ];
	if ( $page_token !== '' ) $query['page_token'] = $page_token;

	$res = sp_reviews_hub_request( 'GET', 'reviews', null, $query );
	if ( is_wp_error( $res ) ) return $res;

	// Prefer the hub's authoritative label/brand for this location; fall back to what the caller passed.
	if ( '' !== (string) ( $res['label'] ?? '' ) ) $store = (string) $res['label'];
	if ( '' !== (string) ( $res['brand'] ?? '' ) ) $brand = (string) $res['brand'];

	sp_reviews_upsert( is_array( $res['reviews'] ?? null ) ? $res['reviews'] : [], $location, $store, $brand );

	return [
		'total'      => isset( $res['totalReviewCount'] ) ? (int) $res['totalReviewCount'] : null,
		'avg'        => $res['averageRating'] ?? null,
		'next_token' => (string) ( $res['nextPageToken'] ?? '' ),
		'fetched'    => count( (array) ( $res['reviews'] ?? [] ) ),
		'store'      => $store,
		'brand'      => $brand,
	];
}


/*--------------------------------------------------------------
# Testimonial linkage
--------------------------------------------------------------*/

/** Google reviewIds already imported as testimonials (any post status) — for the "imported" badge + dedupe. */
function sp_reviews_imported_ids(): array {
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_bp_google_review_id'" );
	return array_values( array_filter( array_map( 'strval', $ids ?: [] ) ) );
}


/*--------------------------------------------------------------
# AJAX — read reviews (view_reviews)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_reviews', 'site_pulse_ajax_get_reviews' );
function site_pulse_ajax_get_reviews(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	$can_view = site_pulse_user_can( $user_id, 'view_reviews' ) || site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() );
	if ( ! $can_view ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	$force     = ! empty( $_POST['refresh'] );
	$cutoff    = sp_reviews_range_cutoff( sanitize_text_field( wp_unslash( $_POST['range'] ?? '30' ) ) );
	$fStore    = sanitize_text_field( wp_unslash( $_POST['store'] ?? '' ) );
	$fBrand    = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
	$fStars    = sanitize_text_field( wp_unslash( $_POST['stars'] ?? '' ) );
	$fReply    = sanitize_text_field( wp_unslash( $_POST['reply'] ?? '' ) );
	$fTopic    = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );

	// Pagination — the list is infinite-scrolled a page at a time so an all-time view doesn't try to
	// ship (and render) tens of thousands of rows at once.
	$per_page = (int) ( $_POST['per_page'] ?? 50 );
	$per_page = max( 10, min( 100, $per_page ) );
	$page     = max( 1, (int) ( $_POST['page'] ?? 1 ) );
	$offset   = ( $page - 1 ) * $per_page;

	$locations = sp_reviews_locations( $force );
	sp_reviews_backfill_primary_location( $locations ); // settle pre-multi-location rows onto the primary
	$meta  = sp_reviews_get_meta();
	$count = sp_reviews_count();
	$error = null;

	// Top up the NEWEST set per location ONLY on the first page (a scroll to page 2+ is a pure DB read).
	// Forced / never-synced / hourly-window-lapsed locations get a fresh pull; the first sync also seeds
	// the backfill cursor, and Load Older + the cron walk the rest of the history.
	if ( 1 === $page ) {
		$deadline = time() + 22;
		if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 60 );
		foreach ( $locations as $L ) {
			if ( time() >= $deadline ) break;
			$lm        = sp_reviews_loc_meta( $meta, $L['id'] );
			$never_loc = empty( $lm['synced_at'] );
			$stale     = $never_loc || ( time() - (int) $lm['synced_at'] > SP_REVIEWS_TTL );
			if ( ! $force && ! $stale && 0 !== $count ) continue;

			$sync = sp_reviews_sync( $L['id'], $L['label'], $L['brand'], '', $never_loc ? 200 : 50 );
			if ( is_wp_error( $sync ) ) { $error = $sync->get_error_message(); continue; }

			if ( $never_loc ) {
				$lm['next_token'] = $sync['next_token'];                  // seed the backfill cursor once
				if ( '' === $sync['next_token'] ) $lm['complete'] = true; // ≤200 total for this location
			}
			if ( null !== $sync['total'] ) $lm['total'] = $sync['total'];
			if ( null !== $sync['avg'] )   $lm['avg']   = $sync['avg'];
			$lm['synced_at']         = time();
			$meta['loc'][ $L['id'] ] = $lm;
		}
		if ( $locations ) sp_reviews_set_meta( $meta );
		$count = sp_reviews_count();
	}

	$rows     = sp_reviews_get_rows( $cutoff, $fStore, $fBrand, $fStars, $fReply, $fTopic, $per_page, $offset );
	$imported = array_flip( sp_reviews_imported_ids() );
	foreach ( $rows as &$r ) { $r['imported'] = isset( $imported[ (string) $r['reviewId'] ] ); }
	unset( $r );

	if ( $error && 0 === $count ) wp_send_json_error( [ 'message' => $error ] );

	$matched   = sp_reviews_count_where( $cutoff, $fStore, $fBrand, $fStars, $fReply, $fTopic );
	$has_more  = ( $offset + count( $rows ) ) < $matched;
	$total     = sp_reviews_total_expected( $meta, $locations, $matched );
	$has_older = $locations ? sp_reviews_any_incomplete( $meta, $locations ) : ! empty( $meta['next_token'] );

	wp_send_json_success( [
		'reviews'          => $rows,
		'page'             => $page,
		'per_page'         => $per_page,
		'matched'          => $matched,       // total stored rows matching the current filters
		'has_more'         => $has_more,      // another page exists for infinite scroll
		'averageRating'    => null,           // per-location now; the stat cards compute the average
		'totalReviewCount' => $total,
		'loadedCount'      => count( $rows ),
		'has_older'        => $has_older,      // Google back-catalogue still un-synced (Load Older)
		'stale'            => (bool) $error,
		'error'            => $error,
		'can_manage'       => site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() ),
	] );
}


/*--------------------------------------------------------------
# AJAX — load the next older batch (view_reviews)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_load_older_reviews', 'site_pulse_ajax_load_older_reviews' );
function site_pulse_ajax_load_older_reviews(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	$can_view = site_pulse_user_can( $user_id, 'view_reviews' ) || site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() );
	if ( ! $can_view ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	$cutoff    = sp_reviews_range_cutoff( sanitize_text_field( wp_unslash( $_POST['range'] ?? '30' ) ) );
	$fStore    = sanitize_text_field( wp_unslash( $_POST['store'] ?? '' ) );
	$fBrand    = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
	$locations = sp_reviews_locations();
	$meta      = sp_reviews_get_meta();
	$error     = null;
	$deadline  = time() + 25; // keeps the request under WPE's 60s cap (the per-call hub timeout is 25s)
	if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 60 );

	// Walk the back-catalogue of each not-yet-complete location, chaining freshly-issued page tokens within
	// this request (so they never go stale). One click finishes whatever fits the budget; the client loops
	// and the cron also advances these, so large histories fill in over a few passes. Completion is decided
	// per location by Google running out of pages (count-vs-total is unreliable — Google's total includes
	// star-only ratings + deleted reviews that never come back as objects).
	foreach ( $locations as $L ) {
		if ( time() >= $deadline ) break;
		$lm = sp_reviews_loc_meta( $meta, $L['id'] );
		if ( ! empty( $lm['complete'] ) ) continue;

		$token       = (string) ( $lm['next_token'] ?? '' );
		$did_restart = false;
		while ( time() < $deadline ) {
			$resume = ( '' !== $token );
			$sync   = sp_reviews_sync( $L['id'], $L['label'], $L['brand'], $token, 250 );
			if ( is_wp_error( $sync ) ) { $error = $sync->get_error_message(); break; }
			$next = (string) $sync['next_token'];
			if ( null !== $sync['total'] ) $lm['total'] = $sync['total'];
			if ( null !== $sync['avg'] )   $lm['avg']   = $sync['avg'];
			// A resume token that returns nothing is a stale snapshot — restart from newest once.
			if ( $resume && 0 === (int) $sync['fetched'] && '' === $next && ! $did_restart ) {
				$did_restart = true; $token = ''; continue;
			}
			$token = $next;
			if ( '' === $token ) break;
		}
		$lm['next_token']        = $token;
		$lm['complete']          = ( '' === $token ) && ( null === $error );
		$lm['synced_at']         = time();
		$meta['loc'][ $L['id'] ] = $lm;
		if ( null !== $error ) break;
	}
	sp_reviews_set_meta( $meta );

	// Don't ship the rows here — the list is paginated, so this endpoint just reports backfill progress.
	// The client refreshes the (page-1) list afterward to surface whatever newly landed in the DB.
	$stored    = sp_reviews_count_where( $cutoff, $fStore, $fBrand );
	$total     = sp_reviews_total_expected( $meta, $locations, $stored );
	$has_older = $locations ? sp_reviews_any_incomplete( $meta, $locations ) : false;

	if ( $error && 0 === $stored ) wp_send_json_error( [ 'message' => $error ] );

	wp_send_json_success( [
		'stored'           => $stored,        // reviews now stored within the current scope
		'totalReviewCount' => $total,
		'has_older'        => $has_older,
		'error'            => $error,
		'can_manage'       => site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() ),
	] );
}


/*--------------------------------------------------------------
# AJAX — reply to a review (manage_reviews)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_reply_review', 'site_pulse_ajax_reply_review' );
function site_pulse_ajax_reply_review(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() ) ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$review_id = sanitize_text_field( wp_unslash( $_POST['review_id'] ?? '' ) );
	$comment   = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) ); // GBP replies are plain text
	if ( '' === $review_id ) wp_send_json_error( [ 'message' => 'Missing review.' ] );

	// Tell the hub which location this review belongs to (it validates against the site's allowlist).
	global $wpdb;
	$location = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT location FROM ' . sp_reviews_table() . ' WHERE review_id = %s', $review_id ) );

	// Empty comment = delete the existing reply (hub interprets it that way).
	$res = sp_reviews_hub_request( 'POST', 'reply', [ 'review_id' => $review_id, 'comment' => $comment, 'location' => $location ] );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );

	$reply = ( '' === $comment ) ? null : [ 'comment' => $comment, 'updateTime' => ( $res['updateTime'] ?? '' ) ];

	// Reflect the reply in the stored row so the panel updates without a re-fetch.
	global $wpdb;
	$t = sp_reviews_table();
	if ( '' === $comment ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET reply_comment = '', reply_time = NULL WHERE review_id = %s", $review_id ) );
	} else {
		$rtime = ! empty( $res['updateTime'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $res['updateTime'] ) ) : current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET reply_comment = %s, reply_time = %s WHERE review_id = %s", $comment, $rtime, $review_id ) );
	}

	site_pulse_log(
		'review_reply',
		( '' === $comment ? 'Removed reply to' : 'Replied to' ) . ' Google review',
		[ 'review_id' => $review_id ]
	);

	wp_send_json_success( [ 'reply' => $reply ] );
}


/*--------------------------------------------------------------
# AJAX — convert a review into a testimonial (manage_reviews)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_review_to_testimonial', 'site_pulse_ajax_review_to_testimonial' );
function site_pulse_ajax_review_to_testimonial(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() ) ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$review_id = sanitize_text_field( wp_unslash( $_POST['review_id'] ?? '' ) );
	if ( '' === $review_id ) wp_send_json_error( [ 'message' => 'Missing review.' ] );

	// Pull the content from our store — never trust client-posted review text.
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . sp_reviews_table() . ' WHERE review_id = %s', $review_id ), ARRAY_A );
	if ( ! $row ) wp_send_json_error( [ 'message' => 'Review not found — try refreshing the list.' ] );
	$review = [
		'reviewId'   => $row['review_id'],
		'reviewer'   => $row['reviewer'],
		'comment'    => $row['comment'],
		'starRating' => (int) $row['star_rating'],
	];

	// Creator lives in includes-gbp-hub.php (loaded everywhere) so the same logic backs the
	// hub→client push receiver on client sites that don't run the Site Pulse app.
	$created = bpgbp_create_testimonial_from_review( $review );
	if ( is_wp_error( $created ) ) wp_send_json_error( [ 'message' => $created->get_error_message() ] );
	if ( ! empty( $created['already_imported'] ) ) wp_send_json_error( [ 'message' => 'This review is already imported as a testimonial.' ] );

	site_pulse_log( 'review_to_testimonial', 'Created testimonial from a Google review', [ 'review_id' => $review_id, 'post_id' => $created['post_id'] ] );

	wp_send_json_success( [
		'post_id'  => $created['post_id'],
		'edit_url' => $created['edit_url'],
	] );
}


/*--------------------------------------------------------------
# AI topic + sentiment tagging — chips on each review card
--------------------------------------------------------------*/

// Preferred labels Claude maps to (consistency lets us filter by topic later); it may add a short new
// one only when none of these fit. Kept restaurant-flavoured since that's the current use.
const SP_REVIEWS_TAG_TOPICS = [ 'Food', 'Service', 'Atmosphere', 'Cleanliness', 'Value', 'Wait Time', 'Drinks', 'Portions', 'Staff', 'Order Accuracy' ];

function sp_reviews_untagged_count(): int {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_reviews_table() . ' WHERE tags IS NULL' );
}

/** Pull the JSON array out of Claude's reply (tolerating code fences / stray prose) → [ index => tags[] ]. */
function sp_reviews_parse_tag_reply( string $reply ): array {
	$s     = preg_replace( '/```(?:json)?/i', '', trim( $reply ) );
	$start = strpos( $s, '[' );
	$end   = strrpos( $s, ']' );
	if ( false === $start || false === $end || $end <= $start ) return [];
	$data = json_decode( substr( $s, $start, $end - $start + 1 ), true );
	if ( ! is_array( $data ) ) return [];

	$out = [];
	foreach ( $data as $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['i'] ) ) continue;
		$tags = [];
		foreach ( (array) ( $entry['tags'] ?? [] ) as $tg ) {
			$label = isset( $tg['label'] ) ? trim( (string) $tg['label'] ) : '';
			if ( '' === $label ) continue;
			$sent = isset( $tg['sentiment'] ) ? strtolower( trim( (string) $tg['sentiment'] ) ) : '';
			if ( ! in_array( $sent, [ 'positive', 'negative', 'neutral' ], true ) ) $sent = 'neutral';
			$tags[] = [ 'label' => $label, 'sentiment' => $sent ];
			if ( count( $tags ) >= 4 ) break; // cap chips per card
		}
		$out[ (int) $entry['i'] ] = $tags;
	}
	return $out;
}

/**
 * Analyze not-yet-tagged reviews with Claude (Haiku) and store sentiment chips. Empty-comment reviews
 * are marked done with no chips (no API spend). Processes up to $max reviews in $per-sized Claude calls,
 * bounded by a wall-clock budget. Returns [ 'tagged'=>int, 'remaining'=>int, 'error'=>?string ].
 */
function sp_reviews_tag_batch( int $max = 60, int $per = 20 ): array {
	global $wpdb;
	$t = sp_reviews_table();

	// Reviews with no text can't be analyzed — settle them so they don't clog the queue.
	$wpdb->query( "UPDATE $t SET tags = '[]', tagged_at = UTC_TIMESTAMP() WHERE tags IS NULL AND ( comment IS NULL OR comment = '' )" );

	if ( ! site_pulse_get_api_key() ) {
		return [ 'tagged' => 0, 'remaining' => sp_reviews_untagged_count(), 'error' => 'No AI API key configured.' ];
	}

	$tagged   = 0;
	$deadline = time() + 25; // keep one request well under the host PHP timeout; the client loops calls
	if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 60 );

	while ( $tagged < $max && time() < $deadline ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, comment FROM $t WHERE tags IS NULL AND comment <> '' ORDER BY create_time DESC LIMIT %d", $per
		), ARRAY_A );
		if ( ! $rows ) break;

		$items = [];
		foreach ( array_values( $rows ) as $idx => $r ) {
			$items[] = [ 'i' => $idx, 'text' => mb_substr( (string) $r['comment'], 0, 1200 ) ];
		}

		$prompt = 'Preferred topic labels (use when they fit; add a short new one only if none do): '
			. implode( ', ', SP_REVIEWS_TAG_TOPICS ) . ".\n\nReviews (JSON):\n" . wp_json_encode( $items );

		$debug = null;
		$reply = site_pulse_call_claude( $prompt, site_pulse_prompt_review_tags(), [
			'model'      => 'claude-haiku-4-5-20251001',
			'max_tokens' => 1500,
		], $debug );

		if ( null === $reply ) {
			return [ 'tagged' => $tagged, 'remaining' => sp_reviews_untagged_count(), 'error' => $debug ?: 'AI request failed.' ];
		}

		$parsed = sp_reviews_parse_tag_reply( $reply );
		if ( ! $parsed ) {
			// Unparseable reply — stop rather than blank-tag the batch; the next run retries these rows.
			return [ 'tagged' => $tagged, 'remaining' => sp_reviews_untagged_count(), 'error' => 'Could not read the AI response.' ];
		}

		// Write a result for every row in the batch (missing index → empty set) so the queue always advances.
		foreach ( array_values( $rows ) as $idx => $r ) {
			$tags = $parsed[ $idx ] ?? [];
			$wpdb->query( $wpdb->prepare(
				"UPDATE $t SET tags = %s, tagged_at = UTC_TIMESTAMP() WHERE id = %d", wp_json_encode( $tags ), (int) $r['id']
			) );
			$tagged++;
		}
	}

	return [ 'tagged' => $tagged, 'remaining' => sp_reviews_untagged_count(), 'error' => null ];
}

/** Hourly cron: advance any location's back-catalogue a step, then AI-tag untagged reviews. Self-gating. */
add_action( 'site_pulse_tag_reviews', 'sp_reviews_tag_cron' );
function sp_reviews_tag_cron(): void {
	$locations = sp_reviews_locations();
	if ( $locations ) {
		$meta     = sp_reviews_get_meta();
		$deadline = time() + 30;
		if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 90 );
		foreach ( $locations as $L ) {
			if ( time() >= $deadline ) break;
			$lm = sp_reviews_loc_meta( $meta, $L['id'] );
			if ( ! empty( $lm['complete'] ) ) continue;
			$token = (string) ( $lm['next_token'] ?? '' );
			$did_restart = false;
			while ( time() < $deadline ) {
				$resume = ( '' !== $token );
				$sync   = sp_reviews_sync( $L['id'], $L['label'], $L['brand'], $token, 250 );
				if ( is_wp_error( $sync ) ) break 2;
				$next = (string) $sync['next_token'];
				if ( null !== $sync['total'] ) $lm['total'] = $sync['total'];
				if ( null !== $sync['avg'] )   $lm['avg']   = $sync['avg'];
				if ( $resume && 0 === (int) $sync['fetched'] && '' === $next && ! $did_restart ) {
					$did_restart = true; $token = ''; continue;
				}
				$token = $next;
				if ( '' === $token ) break;
			}
			$lm['next_token']        = $token;
			$lm['complete']          = ( '' === $token );
			$lm['synced_at']         = time();
			$meta['loc'][ $L['id'] ] = $lm;
		}
		sp_reviews_set_meta( $meta );
	}

	if ( sp_reviews_untagged_count() > 0 ) sp_reviews_tag_batch( 40, 20 );
}

/** AJAX — analyze one chunk of reviews (manage_reviews); the client loops this to backfill the history. */
add_action( 'wp_ajax_site_pulse_analyze_reviews', 'site_pulse_ajax_analyze_reviews' );
function site_pulse_ajax_analyze_reviews(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() ) ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$res = sp_reviews_tag_batch( 60, 20 );
	if ( $res['error'] && 0 === $res['tagged'] ) wp_send_json_error( [ 'message' => $res['error'] ] );
	wp_send_json_success( $res );
}


/*--------------------------------------------------------------
# AI review analytics — per-topic positive / neutral / negative summary
--------------------------------------------------------------*/

/** Resolve a range key to a UTC "created on/after" cutoff ('' = no lower bound). */
function sp_reviews_range_cutoff( string $range ): string {
	$now = current_time( 'timestamp', true ); // UTC
	switch ( $range ) {
		case '30':  return gmdate( 'Y-m-d H:i:s', $now - 30 * DAY_IN_SECONDS );
		case '90':  return gmdate( 'Y-m-d H:i:s', $now - 90 * DAY_IN_SECONDS );
		case '365': return gmdate( 'Y-m-d H:i:s', $now - 365 * DAY_IN_SECONDS );
		case 'ytd': return gmdate( 'Y', $now ) . '-01-01 00:00:00';
		default:    return ''; // 'all'
	}
}

/** AJAX — sentiment analytics over the stored reviews, scoped by the time-range (and later store/brand). */
add_action( 'wp_ajax_site_pulse_review_stats', 'site_pulse_ajax_review_stats' );
function site_pulse_ajax_review_stats(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$can_view = site_pulse_user_can( $user_id, 'view_reviews' ) || site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() );
	if ( ! $can_view ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	global $wpdb;
	$t = sp_reviews_table();

	$range  = sanitize_text_field( wp_unslash( $_POST['range'] ?? 'all' ) );
	$cutoff = sp_reviews_range_cutoff( $range );
	$fStore = sanitize_text_field( wp_unslash( $_POST['store'] ?? '' ) );
	$fBrand = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );

	$where = '1=1';
	$args  = [];
	if ( '' !== $cutoff ) { $where .= ' AND create_time >= %s'; $args[] = $cutoff; }
	if ( '' !== $fStore ) { $where .= ' AND store = %s';        $args[] = $fStore; }
	if ( '' !== $fBrand ) { $where .= ' AND brand = %s';        $args[] = $fBrand; }

	$sql  = "SELECT star_rating, tags FROM $t WHERE $where";
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	$rows = $rows ?: [];

	$count    = 0;
	$star_sum = 0;
	$rated    = 0;
	$stardist = [ 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ];
	$topics   = []; // label => [ positive, neutral, negative ]

	foreach ( $rows as $r ) {
		$count++;
		$s = (int) $r['star_rating'];
		if ( $s >= 1 && $s <= 5 ) { $star_sum += $s; $rated++; $stardist[ $s ]++; }

		$tags = $r['tags'] ? json_decode( $r['tags'], true ) : [];
		if ( ! is_array( $tags ) ) continue;
		$seen = []; // count each topic at most once per review
		foreach ( $tags as $tg ) {
			$label = isset( $tg['label'] ) ? trim( (string) $tg['label'] ) : '';
			$key   = strtolower( $label );
			if ( '' === $label || isset( $seen[ $key ] ) ) continue;
			$seen[ $key ] = true;
			$sent = isset( $tg['sentiment'] ) ? strtolower( (string) $tg['sentiment'] ) : 'neutral';
			if ( ! in_array( $sent, [ 'positive', 'neutral', 'negative' ], true ) ) $sent = 'neutral';
			if ( ! isset( $topics[ $label ] ) ) $topics[ $label ] = [ 'positive' => 0, 'neutral' => 0, 'negative' => 0 ];
			$topics[ $label ][ $sent ]++;
		}
	}

	$out = [];
	foreach ( $topics as $label => $c ) {
		$mentions = $c['positive'] + $c['neutral'] + $c['negative'];
		if ( ! $mentions ) continue;
		$out[] = [
			'label'    => $label,
			'mentions' => $mentions,
			'pos_pct'  => (int) round( $c['positive'] / $mentions * 100 ),
			'neu_pct'  => (int) round( $c['neutral']  / $mentions * 100 ),
			'neg_pct'  => (int) round( $c['negative'] / $mentions * 100 ),
		];
	}
	usort( $out, function ( $a, $b ) { return $b['mentions'] <=> $a['mentions']; } );

	// Restaurant/brand options come from the site's configured locations.
	$locs   = sp_reviews_locations();
	$stores = []; $brands = [];
	foreach ( $locs as $L ) {
		if ( '' !== $L['label'] ) $stores[ $L['label'] ] = true;
		if ( '' !== $L['brand'] ) $brands[ $L['brand'] ] = true;
	}

	wp_send_json_success( [
		'count'    => $count,
		'avg'      => $rated ? round( $star_sum / $rated, 1 ) : null,
		'stardist' => $stardist,
		'topics'   => $out,
		'stores'   => array_keys( $stores ),
		'brands'   => array_keys( $brands ),
		'range'    => $range,
		'untagged' => sp_reviews_untagged_count(),
		'syncing'  => sp_reviews_any_incomplete( sp_reviews_get_meta(), $locs ), // back-catalogue still loading
	] );
}


/*--------------------------------------------------------------
# AI-drafted review replies (brand voice via site filters, regenerate)
--------------------------------------------------------------*/

/**
 * Draft the owner's public reply to a stored review row. Generic by design: each SITE supplies its own
 * brand voice (and, if it wants, the whole prompt) through filters in its child-theme functions-site.php —
 * since every company replies differently. Returns the reply text or a WP_Error.
 *
 * Filters:
 *   site_pulse_review_reply_voice  ( $default_voice, $brand, $row )  → brand voice/style guidance string
 *   site_pulse_review_reply_nudges ( $nudges[], $brand, $row )       → randomized "angles" so drafts vary
 *   site_pulse_review_reply_prompt ( $system, $brand, $voice, $row ) → full system-prompt override
 *   site_pulse_review_reply_model  ( $model, $brand )                → model id override
 */
function sp_reviews_generate_reply( array $row ) {
	$brand    = (string) ( $row['brand'] ?? '' );
	$store    = (string) ( $row['store'] ?? '' );
	$reviewer = (string) ( $row['reviewer'] ?? '' );
	$rating   = (int) ( $row['star_rating'] ?? 0 );
	$comment  = trim( (string) ( $row['comment'] ?? '' ) );

	// Generic fallback voice; the real brand voice comes from the site (functions-site.php) via this filter.
	$default_voice = 'Warm, genuine, and personable — like a real owner who cares about every guest: appreciative, specific, and human.';
	$voice = (string) apply_filters( 'site_pulse_review_reply_voice', $default_voice, $brand, $row );

	// Random angle each call so regenerated drafts don't converge on the same phrasing.
	$nudges = apply_filters( 'site_pulse_review_reply_nudges', [
		'Open by reacting to a specific detail they mentioned.',
		'Open with understated, genuine warmth.',
		'Lead with appreciation for them taking the time to write.',
		'Start by naming what they loved (or, for a complaint, owning it plainly).',
		'Begin conversationally — like a person, not a form letter.',
		'Open with a little brand-appropriate personality.',
	], $brand, $row );
	$nudges = ( is_array( $nudges ) && $nudges ) ? array_values( $nudges ) : [ '' ];
	$nudge  = $nudges[ array_rand( $nudges ) ];

	$parts = preg_split( '/\s+/', trim( $reviewer ) );
	$first = $parts ? $parts[0] : '';

	$ctx  = 'Location: ' . ( $store ?: '(unspecified)' ) . "\n";
	$ctx .= 'Reviewer first name: ' . ( $first ?: '(unknown)' ) . "\n";
	$ctx .= "Star rating: {$rating} of 5\n";
	$ctx .= 'Review: ' . ( '' !== $comment ? $comment : "(no written comment — a {$rating}-star rating only)" ) . "\n";
	if ( '' !== $nudge ) $ctx .= "\nStyle nudge for THIS draft (so it differs from previous replies): {$nudge}";

	$system = (string) apply_filters( 'site_pulse_review_reply_prompt', site_pulse_prompt_review_reply( $brand, $voice ), $brand, $voice, $row );
	$model  = (string) apply_filters( 'site_pulse_review_reply_model', 'claude-sonnet-4-6', $brand );

	$debug = null;
	$reply = site_pulse_call_claude( $ctx, $system, [ 'model' => $model, 'max_tokens' => 400 ], $debug );
	if ( null === $reply ) return new WP_Error( 'sp_reply_ai', $debug ?: 'AI request failed.' );

	return trim( wp_strip_all_tags( $reply ) );
}

/** AJAX — draft (or regenerate) an owner reply for a review (manage_reviews). */
add_action( 'wp_ajax_site_pulse_generate_review_reply', 'site_pulse_ajax_generate_review_reply' );
function site_pulse_ajax_generate_review_reply(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() ) ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}
	$review_id = sanitize_text_field( wp_unslash( $_POST['review_id'] ?? '' ) );
	if ( '' === $review_id ) wp_send_json_error( [ 'message' => 'Missing review.' ] );

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		'SELECT reviewer, comment, star_rating, brand, store FROM ' . sp_reviews_table() . ' WHERE review_id = %s', $review_id
	), ARRAY_A );
	if ( ! $row ) wp_send_json_error( [ 'message' => 'Review not found — refresh the list.' ] );

	$reply = sp_reviews_generate_reply( $row );
	if ( is_wp_error( $reply ) ) wp_send_json_error( [ 'message' => $reply->get_error_message() ] );

	wp_send_json_success( [ 'reply' => $reply ] );
}
