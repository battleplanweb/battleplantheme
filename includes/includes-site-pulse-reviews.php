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

/**
 * create_time SQL for the reviews upsert — never NULL, so a review can't be silently hidden by the list's
 * `create_time >= cutoff` filter. Prefers createTime, falls back to updateTime, and as a last resort stamps
 * the current time so a malformed/absent timestamp still SURFACES the review (at the top) instead of losing it.
 */
function sp_reviews_create_time_sql( $create, $update ): string {
	foreach ( [ $create, $update ] as $v ) {
		$v = trim( (string) $v );
		if ( '' !== $v ) { $ts = strtotime( $v ); if ( $ts ) return "'" . gmdate( 'Y-m-d H:i:s', $ts ) . "'"; }
	}
	return "'" . gmdate( 'Y-m-d H:i:s' ) . "'";
}

// One-time: make review_id CASE-SENSITIVE. Google review IDs are case-sensitive tokens, but the table's
// default (case-insensitive) collation could treat two IDs that differ only by case as equal on the UNIQUE
// KEY — so the upsert's ON DUPLICATE KEY UPDATE silently merged them and dropped one review. Binary
// collation on the id column (and thus its unique index) fixes it. Already-lost rows re-appear on the next
// sync that returns them (now stored as distinct ids).
add_action( 'init', 'sp_reviews_migrate_id_binary' );
function sp_reviews_migrate_id_binary(): void {
	if ( get_option( 'sp_reviews_id_binary' ) ) return;
	global $wpdb;
	$t = sp_reviews_table();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
		$wpdb->query( "ALTER TABLE `$t` MODIFY `review_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL" );
	}
	update_option( 'sp_reviews_id_binary', '1' );
}

// One-time: add the update_time column (so the list can bubble edited reviews to the top). Backfilled to
// create_time for existing rows; the next sync fills the real Google updateTime.
add_action( 'init', 'sp_reviews_migrate_update_time' );
function sp_reviews_migrate_update_time(): void {
	if ( get_option( 'sp_reviews_update_time_col' ) ) return;
	global $wpdb;
	$t = sp_reviews_table();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
		$has = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$t` LIKE %s", 'update_time' ) );
		if ( ! $has ) {
			$wpdb->query( "ALTER TABLE `$t` ADD COLUMN update_time datetime DEFAULT NULL AFTER create_time, ADD KEY update_time (update_time)" );
			$wpdb->query( "UPDATE `$t` SET update_time = create_time WHERE update_time IS NULL" );
		}
	}
	update_option( 'sp_reviews_update_time_col', '1' );
}

/** Upsert normalized hub reviews into the table, keyed by reviewId, stamping location/store/brand/source. */
function sp_reviews_upsert( array $reviews, string $location, string $store, string $brand, string $source = 'google' ): void {
	global $wpdb;
	$now  = current_time( 'mysql' );
	$locS = sp_reviews_sql_str( $location );
	$stoS = sp_reviews_sql_str( $store );
	$braS = sp_reviews_sql_str( $brand );
	$srcS = sp_reviews_sql_str( $source );
	$rows     = [];
	$seen_ids = [];
	foreach ( $reviews as $r ) {
		$rid = (string) ( $r['reviewId'] ?? '' );
		if ( $rid === '' ) {
			// A review with no id can't be stored (the table keys on review_id) — log it so a silent
			// drop is visible instead of a review just vanishing from the list.
			error_log( 'sp_reviews_upsert: skipped a review with an empty reviewId (reviewer="' . (string) ( $r['reviewer'] ?? '' ) . '", location="' . $location . '")' );
			continue;
		}
		if ( isset( $seen_ids[ $rid ] ) ) {
			// Two rows with the same id in one batch → one overwrites the other. Shouldn't happen; log if it does.
			error_log( 'sp_reviews_upsert: duplicate reviewId within one batch (one copy overwrites the other): ' . $rid . ' @ ' . $location );
		}
		$seen_ids[ $rid ] = true;
		$reply = is_array( $r['reply'] ?? null ) ? $r['reply'] : null;
		$rows[] = '(' . implode( ',', [
			sp_reviews_sql_str( $rid ),
			sp_reviews_sql_str( (string) ( $r['reviewer'] ?? '' ) ),
			sp_reviews_sql_str( (string) ( $r['photo'] ?? '' ) ),
			(string) (int) ( $r['starRating'] ?? 0 ),
			sp_reviews_sql_str( (string) ( $r['comment'] ?? '' ) ),
			sp_reviews_create_time_sql( $r['createTime'] ?? '', $r['updateTime'] ?? '' ),
			sp_reviews_create_time_sql( $r['updateTime'] ?? '', $r['createTime'] ?? '' ), // update_time (edits bubble the list)
			$reply ? sp_reviews_sql_str( (string) ( $reply['comment'] ?? '' ) ) : 'NULL',
			$reply ? sp_reviews_sql_date( $reply['updateTime'] ?? '' ) : 'NULL',
			$locS, $stoS, $braS, $srcS,
			sp_reviews_sql_str( $now ),
		] ) . ')';
	}
	if ( ! $rows ) return;

	$table = sp_reviews_table();
	foreach ( array_chunk( $rows, 100 ) as $chunk ) {
		$wpdb->query(
			"INSERT INTO $table (review_id, reviewer, photo, star_rating, comment, create_time, update_time, reply_comment, reply_time, location, store, brand, source, synced_at) VALUES "
			. implode( ',', $chunk )
			// Note: tags / tagged_at are intentionally left out so re-syncing never wipes AI tags.
			. ' ON DUPLICATE KEY UPDATE reviewer=VALUES(reviewer), photo=VALUES(photo), star_rating=VALUES(star_rating),'
			. ' comment=VALUES(comment), create_time=VALUES(create_time), update_time=VALUES(update_time), reply_comment=VALUES(reply_comment),'
			. ' reply_time=VALUES(reply_time), location=VALUES(location), store=VALUES(store), brand=VALUES(brand), source=VALUES(source), synced_at=VALUES(synced_at)'
		);
	}
}

/** Stored reviews, newest first, scoped by date/store/brand so a view never pulls the whole back-catalogue. */
/**
 * Build the shared WHERE clause + bound args for the review list. Scope filters (cutoff/store/brand)
 * plus the secondary list filters (star rating, reply status, AI topic) — all server-side now so the
 * list can be paginated without losing any filtering. Returns [ whereSql, args ].
 */
function sp_reviews_filter_sql( string $cutoff, string $store, string $brand, string $stars, string $reply, string $topic, string $source = '', bool $by_update = false ): array {
	global $wpdb;
	$where = '1=1'; $args = [];
	// The LIST scopes by UPDATE time (so an edited older review bubbles back into the recent window);
	// analytics pass $by_update=false and keep create_time (a rating counts for when it was written).
	if ( '' !== $cutoff ) { $where .= $by_update ? ' AND COALESCE(update_time, create_time) >= %s' : ' AND create_time >= %s'; $args[] = $cutoff; }
	if ( '' !== $store )  { $where .= ' AND store = %s';        $args[] = $store; }
	if ( '' !== $brand )  { $where .= ' AND brand = %s';        $args[] = $brand; }
	if ( '' !== $stars )  { $where .= ' AND star_rating = %d';  $args[] = (int) $stars; }
	// Platform filter: rows synced before the source column existed are NULL/'' → treat them as Google.
	if ( 'facebook' === $source )    { $where .= " AND source = 'facebook'"; }
	elseif ( 'google' === $source )  { $where .= " AND ( source = 'google' OR source IS NULL OR source = '' )"; }
	if ( 'replied' === $reply )   { $where .= " AND reply_comment IS NOT NULL AND reply_comment <> ''"; }
	elseif ( 'unreplied' === $reply ) { $where .= " AND (reply_comment IS NULL OR reply_comment = '')"; }
	// tags is a JSON array of {label,sentiment}; match the topic by its label substring.
	if ( '' !== $topic )  { $where .= ' AND tags LIKE %s'; $args[] = '%"label":"' . $wpdb->esc_like( $topic ) . '"%'; }
	return [ $where, $args ];
}

/** Count of stored reviews matching the given filters (drives "Showing X of Y" + has_more). */
function sp_reviews_count_where( string $cutoff = '', string $store = '', string $brand = '', string $stars = '', string $reply = '', string $topic = '', string $source = '' ): int {
	global $wpdb;
	list( $where, $args ) = sp_reviews_filter_sql( $cutoff, $store, $brand, $stars, $reply, $topic, $source, true );
	$sql = 'SELECT COUNT(*) FROM ' . sp_reviews_table() . " WHERE $where";
	return (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_var( $sql ) );
}

function sp_reviews_get_rows( string $cutoff = '', string $store = '', string $brand = '', string $stars = '', string $reply = '', string $topic = '', int $limit = 0, int $offset = 0, string $source = '' ): array {
	global $wpdb;
	list( $where, $args ) = sp_reviews_filter_sql( $cutoff, $store, $brand, $stars, $reply, $topic, $source, true );
	// Bubble edited reviews up: order by update time (falls back to create time for un-edited rows).
	$sql = 'SELECT * FROM ' . sp_reviews_table() . " WHERE $where ORDER BY COALESCE(update_time, create_time) DESC, id DESC";
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
			'updateTime' => ! empty( $r['update_time'] ) ? gmdate( 'c', strtotime( $r['update_time'] ) ) : '',
			'reply'      => $has_reply ? [ 'comment' => $r['reply_comment'], 'updateTime' => $r['reply_time'], 'by' => ( ! empty( $r['reply_by'] ) ? site_pulse_display_name( (int) $r['reply_by'] ) : '' ) ] : null,
			'tags'       => ( isset( $r['tags'] ) && $r['tags'] ) ? ( json_decode( $r['tags'], true ) ?: [] ) : [],
			'location'   => isset( $r['location'] ) ? (string) $r['location'] : '',
			'store'      => isset( $r['store'] ) ? (string) $r['store'] : '',
			'brand'      => isset( $r['brand'] ) ? (string) $r['brand'] : '',
			'source'     => ! empty( $r['source'] ) ? (string) $r['source'] : 'google',
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

/**
 * Pull this site's Facebook Page recommendations from the hub and upsert them alongside Google reviews,
 * tagged source='facebook' (positive=5★, negative=1★, already normalized hub-side). They live under a
 * single synthetic 'facebook' location with a "Facebook" store label so they group and badge together;
 * there's no pagination (Meta returns the recent set) and no reply support. Returns a summary or WP_Error.
 */
function sp_reviews_sync_facebook() {
	$res = sp_reviews_hub_request( 'GET', 'fb-reviews', null, [ 'max' => '100' ] );
	if ( is_wp_error( $res ) ) return $res;

	$reviews = is_array( $res['reviews'] ?? null ) ? $res['reviews'] : [];
	sp_reviews_upsert( $reviews, 'facebook', 'Facebook', '', 'facebook' );

	return [ 'fetched' => count( $reviews ) ];
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

	$can_view = site_pulse_user_can( $user_id, 'view_reviews' ) || site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_god_can_override();
	if ( ! $can_view ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	$force     = ! empty( $_POST['refresh'] );
	$cutoff    = sp_reviews_range_cutoff( sanitize_text_field( wp_unslash( $_POST['range'] ?? '30' ) ) );
	$fStore    = sanitize_text_field( wp_unslash( $_POST['store'] ?? '' ) );
	$fBrand    = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
	$fStars    = sanitize_text_field( wp_unslash( $_POST['stars'] ?? '' ) );
	$fReply    = sanitize_text_field( wp_unslash( $_POST['reply'] ?? '' ) );
	$fTopic    = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
	$fSource   = sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) ); // '' | google | facebook (Platforms filter)

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

		// Facebook recommendations: refresh at most hourly (or on a forced sync), stored under the
		// synthetic 'facebook' location. FB errors are kept OUT of $error on purpose — Meta's recommendation
		// API is deprecated, and a dead FB source must not nag the (working) Google list with a stale banner
		// on every view. We stamp synced_at even on failure so a broken source isn't hammered each load; the
		// last successfully-synced set simply stays in place.
		$fb_meta   = is_array( $meta['fb'] ?? null ) ? $meta['fb'] : [];
		$fb_stale  = empty( $fb_meta['synced_at'] ) || ( time() - (int) $fb_meta['synced_at'] > SP_REVIEWS_TTL );
		if ( ( $force || $fb_stale ) && time() < $deadline ) {
			$fb = sp_reviews_sync_facebook();
			if ( is_wp_error( $fb ) ) {
				error_log( 'sp_reviews_sync_facebook: ' . $fb->get_error_message() );
			}
			$fb_meta['synced_at'] = time();
			$meta['fb'] = $fb_meta;
		}

		if ( $locations || isset( $meta['fb'] ) ) sp_reviews_set_meta( $meta );
		$count = sp_reviews_count();
	}

	$rows     = sp_reviews_get_rows( $cutoff, $fStore, $fBrand, $fStars, $fReply, $fTopic, $per_page, $offset, $fSource );
	$imported = array_flip( sp_reviews_imported_ids() );
	foreach ( $rows as &$r ) { $r['imported'] = isset( $imported[ (string) $r['reviewId'] ] ); }
	unset( $r );

	if ( $error && 0 === $count ) wp_send_json_error( [ 'message' => $error ] );

	$matched   = sp_reviews_count_where( $cutoff, $fStore, $fBrand, $fStars, $fReply, $fTopic, $fSource );
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
		// Reply / Add-as-testimonial are gated on this. Impersonation-aware (effective user), so "view as"
		// a role without Manage reviews correctly hides the buttons.
		'can_manage'       => site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( $user_id ),
	] );
}


/*--------------------------------------------------------------
# AJAX — load the next older batch (view_reviews)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_load_older_reviews', 'site_pulse_ajax_load_older_reviews' );
function site_pulse_ajax_load_older_reviews(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	$can_view = site_pulse_user_can( $user_id, 'view_reviews' ) || site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_god_can_override();
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
		'can_manage'       => site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_god_can_override(),
	] );
}


/*--------------------------------------------------------------
# AJAX — reply to a review (manage_reviews)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_reply_review', 'site_pulse_ajax_reply_review' );
function site_pulse_ajax_reply_review(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( $user_id ) ) ) {
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

	// Internal credit: record WHO on the team posted the reply (effective user), shown in the panel only —
	// Google has no concept of an internal author. `by` rides the response so the card updates immediately.
	$reply = ( '' === $comment ) ? null : [ 'comment' => $comment, 'updateTime' => ( $res['updateTime'] ?? '' ), 'by' => site_pulse_display_name( $user_id ) ];

	// Reflect the reply in the stored row so the panel updates without a re-fetch.
	global $wpdb;
	$t = sp_reviews_table();
	if ( '' === $comment ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET reply_comment = '', reply_time = NULL, reply_by = NULL WHERE review_id = %s", $review_id ) );
	} else {
		$rtime = ! empty( $res['updateTime'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $res['updateTime'] ) ) : current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET reply_comment = %s, reply_time = %s, reply_by = %d WHERE review_id = %s", $comment, $rtime, $user_id, $review_id ) );
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

	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( $user_id ) ) ) {
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

// The configured "super categories" the client wants reviews ranked into — each with guiding sub-topics.
// Stored as a setting (JSON): [ { name, subs:[…] }, … ]. Empty until configured in Review Settings.
function sp_reviews_categories(): array {
	$raw = site_pulse_get_setting( 'review_categories', '' );
	$arr = $raw !== '' ? json_decode( $raw, true ) : null;
	if ( ! is_array( $arr ) ) return [];
	$out = [];
	foreach ( $arr as $c ) {
		if ( ! is_array( $c ) ) continue;
		$name = trim( (string) ( $c['name'] ?? '' ) );
		if ( '' === $name ) continue;
		$subs = [];
		foreach ( (array) ( $c['subs'] ?? [] ) as $s ) { $s = trim( (string) $s ); if ( '' !== $s ) $subs[] = $s; }
		$out[] = [ 'name' => $name, 'subs' => $subs ];
	}
	return $out;
}

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
			// Per-category 1-5 score (new). 0 = not provided (old/fallback tags that only had sentiment).
			$score = isset( $tg['score'] ) ? (int) $tg['score'] : 0;
			if ( $score < 1 || $score > 5 ) $score = 0;
			// Sentiment: use the AI's if given, otherwise derive it from the score (for chip colour + filters).
			$sent = isset( $tg['sentiment'] ) ? strtolower( trim( (string) $tg['sentiment'] ) ) : '';
			if ( ! in_array( $sent, [ 'positive', 'negative', 'neutral' ], true ) ) {
				$sent = $score >= 4 ? 'positive' : ( 3 === $score ? 'neutral' : ( $score >= 1 ? 'negative' : 'neutral' ) );
			}
			$tags[] = [ 'label' => $label, 'score' => $score, 'sentiment' => $sent ];
			if ( count( $tags ) >= 6 ) break; // cap chips per card (enough for the configured super-categories)
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
			"SELECT id, comment, star_rating FROM $t WHERE tags IS NULL AND comment <> '' ORDER BY create_time DESC LIMIT %d", $per
		), ARRAY_A );
		if ( ! $rows ) break;

		$items = [];
		foreach ( array_values( $rows ) as $idx => $r ) {
			// Include the overall star rating so the AI can weight per-category scores by review context.
			$items[] = [ 'i' => $idx, 'stars' => (int) $r['star_rating'], 'text' => mb_substr( (string) $r['comment'], 0, 1200 ) ];
		}

		// When super-categories are configured, classify strictly into them; otherwise fall back to the
		// original free-form topic tagging.
		$cats = sp_reviews_categories();
		if ( $cats ) {
			$system = site_pulse_prompt_review_tags( $cats );
			$prompt = "Reviews (JSON):\n" . wp_json_encode( $items );
		} else {
			$system = site_pulse_prompt_review_tags();
			$prompt = 'Preferred topic labels (use when they fit; add a short new one only if none do): '
				. implode( ', ', SP_REVIEWS_TAG_TOPICS ) . ".\n\nReviews (JSON):\n" . wp_json_encode( $items );
		}

		$debug = null;
		$reply = site_pulse_call_claude( $prompt, $system, [
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

			// Back-catalogue already fully synced → just pull the NEWEST page each run so reviews that have
			// come in since last time land in the DB (and get AI-tagged below) — no manual panel visit /
			// "Analyze reviews" click needed. Untagged rows are settled by sp_reviews_tag_batch() at the end.
			if ( ! empty( $lm['complete'] ) ) {
				$sync = sp_reviews_sync( $L['id'], $L['label'], $L['brand'], '', 50 );
				if ( ! is_wp_error( $sync ) ) {
					if ( null !== $sync['total'] ) $lm['total'] = $sync['total'];
					if ( null !== $sync['avg'] )   $lm['avg']   = $sync['avg'];
					$lm['synced_at']         = time();
					$meta['loc'][ $L['id'] ] = $lm;
				}
				continue;
			}

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
	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_god_can_override() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$res = sp_reviews_tag_batch( 60, 20 );
	if ( $res['error'] && 0 === $res['tagged'] ) wp_send_json_error( [ 'message' => $res['error'] ] );
	wp_send_json_success( $res );
}

/* --------------------------------------------------------------
# Review categories (super-categories + sub-categories) — settings
-------------------------------------------------------------- */

add_action( 'wp_ajax_site_pulse_get_review_categories', 'site_pulse_ajax_get_review_categories' );
function site_pulse_ajax_get_review_categories(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_god_can_override() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}
	wp_send_json_success( [ 'categories' => sp_reviews_categories() ] );
}

add_action( 'wp_ajax_site_pulse_save_review_categories', 'site_pulse_ajax_save_review_categories' );
function site_pulse_ajax_save_review_categories(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_god_can_override() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$in    = json_decode( (string) wp_unslash( $_POST['categories'] ?? '[]' ), true );
	$clean = [];
	if ( is_array( $in ) ) {
		foreach ( $in as $c ) {
			if ( ! is_array( $c ) ) continue;
			$name = sanitize_text_field( (string) ( $c['name'] ?? '' ) );
			if ( '' === $name ) continue;                 // skip blank category rows
			$subs = [];
			foreach ( (array) ( $c['subs'] ?? [] ) as $s ) {
				$s = sanitize_text_field( (string) $s );
				if ( '' !== $s ) $subs[] = $s;
			}
			$clean[] = [ 'name' => $name, 'subs' => array_values( array_unique( $subs ) ) ];
		}
	}

	$prev = site_pulse_get_setting( 'review_categories', '' );
	$next = wp_json_encode( $clean );
	site_pulse_set_setting( 'review_categories', $next );

	// If the categories actually changed, clear existing tags so reviews re-classify under the new scheme
	// on the next "Analyze reviews" run / hourly cron (the old arbitrary labels no longer apply).
	$recategorized = false;
	if ( $prev !== $next ) {
		global $wpdb;
		$wpdb->query( 'UPDATE ' . sp_reviews_table() . ' SET tags = NULL, tagged_at = NULL' );
		$recategorized = true;
	}

	wp_send_json_success( [ 'categories' => $clean, 'recategorized' => $recategorized ] );
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
	$can_view = site_pulse_user_can( $user_id, 'view_reviews' ) || site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_god_can_override();
	if ( ! $can_view ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	global $wpdb;
	$t = sp_reviews_table();

	$range  = sanitize_text_field( wp_unslash( $_POST['range'] ?? 'all' ) );
	$cutoff = sp_reviews_range_cutoff( $range );
	$fStore  = sanitize_text_field( wp_unslash( $_POST['store'] ?? '' ) );
	$fBrand  = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
	$fStars  = sanitize_text_field( wp_unslash( $_POST['stars'] ?? '' ) );
	$fReply  = sanitize_text_field( wp_unslash( $_POST['reply'] ?? '' ) );
	$fSource = sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) );

	// Scope the stat cards with the SAME filter as the review list (minus the topic chip), so every
	// dropdown moves the graphs: restaurant/brand/time above the charts AND ratings/reviews/platform below.
	list( $where, $args ) = sp_reviews_filter_sql( $cutoff, $fStore, $fBrand, $fStars, $fReply, '', $fSource );

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
			$sc = isset( $tg['score'] ) ? (int) $tg['score'] : 0;
			if ( ! isset( $topics[ $label ] ) ) $topics[ $label ] = [ 'positive' => 0, 'neutral' => 0, 'negative' => 0, 'score_sum' => 0, 'score_n' => 0 ];
			$topics[ $label ][ $sent ]++;
			if ( $sc >= 1 && $sc <= 5 ) { $topics[ $label ]['score_sum'] += $sc; $topics[ $label ]['score_n']++; }
		}
	}

	$out = [];
	foreach ( $topics as $label => $c ) {
		$mentions = $c['positive'] + $c['neutral'] + $c['negative'];
		if ( ! $mentions ) continue;
		$out[] = [
			'label'     => $label,
			'mentions'  => $mentions,
			'pos_pct'   => (int) round( $c['positive'] / $mentions * 100 ),
			'neu_pct'   => (int) round( $c['neutral']  / $mentions * 100 ),
			'neg_pct'   => (int) round( $c['negative'] / $mentions * 100 ),
			'avg_score' => $c['score_n'] ? round( $c['score_sum'] / $c['score_n'], 1 ) : null, // per-category 1-5 rating
			'score_n'   => $c['score_n'],
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
# Trends — per-store scorecard vs the company average
--------------------------------------------------------------*/

/** Map each review store (GBP location label) to the supervisor of that store's GM. Bridges the GBP
 *  location list to the Site Pulse locations table by normalized name, then locations to supervisors via
 *  the GM's supervisor_id. Returns [ storeLabel => [ 'id' => sup_user_id, 'name' => display_name ] ]. */
function sp_reviews_store_supervisor_map(): array {
	static $cache = null;
	if ( null !== $cache ) return $cache;

	$norm = function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) ); };

	// Site Pulse store locations indexed by normalized name → location id.
	$spByNorm = [];
	foreach ( site_pulse_get_all_locations( true, true ) as $L ) {
		$k = $norm( $L['name'] );
		if ( '' !== $k && ! isset( $spByNorm[ $k ] ) ) $spByNorm[ $k ] = (int) $L['id'];
	}

	// A store's supervisor = the supervisor of that store's GM (role 'manager') — NOT just any employee
	// who happens to sit at the location (a line cook's supervisor is the GM, which would be wrong here).
	global $wpdb;
	$gmRole   = site_pulse_get_role_by_slug( 'manager' );
	$gmRoleId = $gmRole ? (int) $gmRole['id'] : 0;
	$locSup   = [];
	if ( $gmRoleId ) {
		$profiles = $wpdb->get_results( $wpdb->prepare(
			"SELECT location_id, supervisor_id FROM " . site_pulse_table( 'user_profiles' ) . " WHERE status = 'active' AND role_id = %d AND location_id > 0 AND supervisor_id > 0",
			$gmRoleId
		), ARRAY_A ) ?: [];
		foreach ( $profiles as $p ) {
			$lid = (int) $p['location_id'];
			if ( ! isset( $locSup[ $lid ] ) ) $locSup[ $lid ] = (int) $p['supervisor_id'];
		}
	}

	$map = [];
	foreach ( sp_reviews_locations() as $g ) {
		$lid   = $spByNorm[ $norm( $g['label'] ) ] ?? 0;
		$supId = $lid ? ( $locSup[ $lid ] ?? 0 ) : 0;
		if ( $supId ) $map[ (string) $g['label'] ] = [ 'id' => $supId, 'name' => site_pulse_display_name( $supId ) ];
	}
	return $cache = $map;
}

add_action( 'wp_ajax_site_pulse_review_scorecard', 'site_pulse_ajax_review_scorecard' );
function site_pulse_ajax_review_scorecard(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$can_view = site_pulse_user_can( $user_id, 'view_reviews' ) || site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_god_can_override();
	if ( ! $can_view ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	global $wpdb;
	$t = sp_reviews_table();

	$range  = sanitize_text_field( wp_unslash( $_POST['range'] ?? 'all' ) );
	$cutoff = sp_reviews_range_cutoff( $range );
	$fBrand = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
	$group  = sanitize_text_field( wp_unslash( $_POST['group'] ?? 'store' ) );
	if ( 'supervisor' !== $group ) $group = 'store';
	$fSup   = (int) ( $_POST['supervisor'] ?? 0 );

	$where = '1=1'; $args = [];
	if ( '' !== $cutoff ) { $where .= ' AND create_time >= %s'; $args[] = $cutoff; }
	if ( '' !== $fBrand ) { $where .= ' AND brand = %s';        $args[] = $fBrand; }

	$sql  = "SELECT store, brand, star_rating, tags FROM $t WHERE $where";
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	$rows = $rows ?: [];

	$catNames = array_map( function ( $c ) { return $c['name']; }, sp_reviews_categories() );
	$supMap   = sp_reviews_store_supervisor_map();

	$groups  = [];
	$co      = [ 'count' => 0, 'star_sum' => 0, 'star_n' => 0, 'cat' => [] ];
	$supSeen = []; // sup_id => name, for the filter dropdown
	foreach ( $rows as $r ) {
		$store   = (string) $r['store']; if ( '' === $store ) $store = '(unknown)';
		$supInfo = $supMap[ $store ] ?? null;
		$supId   = $supInfo ? (int) $supInfo['id'] : 0;
		$supName = $supInfo ? (string) $supInfo['name'] : '';
		if ( $supId ) $supSeen[ $supId ] = $supName;

		$s       = (int) $r['star_rating'];
		$hasStar = ( $s >= 1 && $s <= 5 );

		// Parse this review's category scores once (label => score), de-duping repeats within a review.
		$parsed = [];
		$tags   = $r['tags'] ? json_decode( $r['tags'], true ) : [];
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tg ) {
				$label = isset( $tg['label'] ) ? trim( (string) $tg['label'] ) : '';
				$sc    = isset( $tg['score'] ) ? (int) $tg['score'] : 0;
				if ( '' !== $label && $sc >= 1 && $sc <= 5 ) $parsed[ $label ] = $sc;
			}
		}

		// Company baseline = every review in range/brand (NOT scoped by the supervisor filter), so a
		// filtered view still compares against the whole company.
		$co['count']++;
		if ( $hasStar ) { $co['star_sum'] += $s; $co['star_n']++; }
		foreach ( $parsed as $label => $sc ) {
			if ( ! isset( $co['cat'][ $label ] ) ) $co['cat'][ $label ] = [ 'sum' => 0, 'n' => 0 ];
			$co['cat'][ $label ]['sum'] += $sc; $co['cat'][ $label ]['n']++;
		}

		// Grouped rows honor the supervisor filter.
		if ( $fSup && $supId !== $fSup ) continue;
		$key = ( 'supervisor' === $group ) ? ( $supId ? $supName : '(Unassigned)' ) : $store;
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = [ 'brand' => ( 'supervisor' === $group ? '' : (string) $r['brand'] ), 'sup_id' => $supId, 'count' => 0, 'star_sum' => 0, 'star_n' => 0, 'cat' => [] ];
		}
		$groups[ $key ]['count']++;
		if ( $hasStar ) { $groups[ $key ]['star_sum'] += $s; $groups[ $key ]['star_n']++; }
		foreach ( $parsed as $label => $sc ) {
			if ( ! isset( $groups[ $key ]['cat'][ $label ] ) ) $groups[ $key ]['cat'][ $label ] = [ 'sum' => 0, 'n' => 0 ];
			$groups[ $key ]['cat'][ $label ]['sum'] += $sc; $groups[ $key ]['cat'][ $label ]['n']++;
		}
	}

	$avg = function ( $sum, $n ) { return $n ? round( $sum / $n, 1 ) : null; };

	$company = [ 'count' => $co['count'], 'stars' => $avg( $co['star_sum'], $co['star_n'] ), 'scores' => [] ];
	foreach ( $catNames as $cn ) {
		$company['scores'][ $cn ] = isset( $co['cat'][ $cn ] ) ? $avg( $co['cat'][ $cn ]['sum'], $co['cat'][ $cn ]['n'] ) : null;
	}

	$out = [];
	foreach ( $groups as $name => $d ) {
		$scores = [];
		foreach ( $catNames as $cn ) {
			$scores[ $cn ] = isset( $d['cat'][ $cn ] ) ? $avg( $d['cat'][ $cn ]['sum'], $d['cat'][ $cn ]['n'] ) : null;
		}
		$out[] = [ 'store' => $name, 'brand' => $d['brand'], 'sup_id' => (int) $d['sup_id'], 'count' => (int) $d['count'], 'stars' => $avg( $d['star_sum'], $d['star_n'] ), 'scores' => $scores ];
	}
	usort( $out, function ( $a, $b ) { return ( $b['stars'] ?? 0 ) <=> ( $a['stars'] ?? 0 ); } );

	$supervisors = [];
	foreach ( $supSeen as $id => $nm ) $supervisors[] = [ 'id' => $id, 'name' => $nm ];
	usort( $supervisors, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );

	$brands = $wpdb->get_col( "SELECT DISTINCT brand FROM $t WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand" ) ?: [];

	wp_send_json_success( [
		'categories'  => $catNames,
		'company'     => $company,
		'stores'      => $out,
		'brands'      => $brands,
		'supervisors' => $supervisors,
		'group'       => $group,
		'supervisor'  => $fSup,
		'range'       => $range,
	] );
}

/** AJAX — monthly time series per store (or per supervisor) for every metric, for the line chart that
 *  compares stores over time. Same filters as the scorecard. Returns all metrics so the client can
 *  switch the metric picker without re-fetching. */
add_action( 'wp_ajax_site_pulse_review_trend_series', 'site_pulse_ajax_review_trend_series' );
function site_pulse_ajax_review_trend_series(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$can_view = site_pulse_user_can( $user_id, 'view_reviews' ) || site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_god_can_override();
	if ( ! $can_view ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	global $wpdb;
	$t = sp_reviews_table();

	$range  = sanitize_text_field( wp_unslash( $_POST['range'] ?? 'all' ) );
	$cutoff = sp_reviews_range_cutoff( $range );
	$fBrand = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
	$group  = sanitize_text_field( wp_unslash( $_POST['group'] ?? 'store' ) );
	if ( 'supervisor' !== $group ) $group = 'store';
	$fSup   = (int) ( $_POST['supervisor'] ?? 0 );

	$where = '1=1'; $args = [];
	if ( '' !== $cutoff ) { $where .= ' AND create_time >= %s'; $args[] = $cutoff; }
	if ( '' !== $fBrand ) { $where .= ' AND brand = %s';        $args[] = $fBrand; }
	$where .= ' AND create_time IS NOT NULL';

	$sql  = "SELECT store, star_rating, tags, create_time FROM $t WHERE $where";
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	$rows = $rows ?: [];

	$catNames = array_map( function ( $c ) { return $c['name']; }, sp_reviews_categories() );
	$metrics  = array_merge( [ 'stars' ], $catNames );
	$supMap   = sp_reviews_store_supervisor_map();

	$agg = []; $co = []; $buckets_set = [];
	foreach ( $rows as $r ) {
		$store   = (string) $r['store']; if ( '' === $store ) $store = '(unknown)';
		$supInfo = $supMap[ $store ] ?? null;
		$supId   = $supInfo ? (int) $supInfo['id'] : 0;
		$supName = $supInfo ? (string) $supInfo['name'] : '';
		if ( $fSup && $supId !== $fSup ) continue;

		$gkey   = ( 'supervisor' === $group ) ? ( $supId ? $supName : '(Unassigned)' ) : $store;
		$bucket = substr( (string) $r['create_time'], 0, 7 ); // YYYY-MM
		if ( '' === $bucket ) continue;
		$buckets_set[ $bucket ] = true;

		$vals = [];
		$s = (int) $r['star_rating']; if ( $s >= 1 && $s <= 5 ) $vals['stars'] = $s;
		$tags = $r['tags'] ? json_decode( $r['tags'], true ) : [];
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tg ) {
				$lab = isset( $tg['label'] ) ? trim( (string) $tg['label'] ) : '';
				$sc  = isset( $tg['score'] ) ? (int) $tg['score'] : 0;
				if ( '' !== $lab && $sc >= 1 && $sc <= 5 ) $vals[ $lab ] = $sc;
			}
		}
		foreach ( $vals as $m => $v ) {
			$agg[ $gkey ][ $bucket ][ $m ]['sum'] = ( $agg[ $gkey ][ $bucket ][ $m ]['sum'] ?? 0 ) + $v;
			$agg[ $gkey ][ $bucket ][ $m ]['n']   = ( $agg[ $gkey ][ $bucket ][ $m ]['n'] ?? 0 ) + 1;
			$co[ $bucket ][ $m ]['sum'] = ( $co[ $bucket ][ $m ]['sum'] ?? 0 ) + $v;
			$co[ $bucket ][ $m ]['n']   = ( $co[ $bucket ][ $m ]['n'] ?? 0 ) + 1;
		}
	}

	ksort( $buckets_set );
	$buckets = array_keys( $buckets_set );
	$labels  = array_map( function ( $b ) { $ts = strtotime( $b . '-01' ); return $ts ? date( 'M y', $ts ) : $b; }, $buckets );

	$avg = function ( $cell ) { return ( isset( $cell['n'] ) && $cell['n'] ) ? round( $cell['sum'] / $cell['n'], 2 ) : null; };

	$out = [ 'buckets' => $buckets, 'bucket_labels' => $labels, 'group' => $group, 'metrics' => [] ];
	foreach ( $metrics as $m ) {
		$series = [];
		foreach ( $agg as $gkey => $bdata ) {
			$values = []; $has = false;
			foreach ( $buckets as $b ) {
				$v = isset( $agg[ $gkey ][ $b ][ $m ] ) ? $avg( $agg[ $gkey ][ $b ][ $m ] ) : null;
				$values[] = $v;
				if ( null !== $v ) $has = true;
			}
			if ( $has ) $series[] = [ 'key' => $gkey, 'values' => $values ];
		}
		// Stable legend order: best overall average first.
		usort( $series, function ( $a, $b ) {
			$af = array_filter( $a['values'], function ( $x ) { return null !== $x; } );
			$bf = array_filter( $b['values'], function ( $x ) { return null !== $x; } );
			$aa = $af ? array_sum( $af ) / count( $af ) : 0;
			$ba = $bf ? array_sum( $bf ) / count( $bf ) : 0;
			return $ba <=> $aa;
		} );
		$company = [];
		foreach ( $buckets as $b ) $company[] = isset( $co[ $b ][ $m ] ) ? $avg( $co[ $b ][ $m ] ) : null;
		$out['metrics'][ $m ] = [ 'series' => $series, 'company' => $company ];
	}

	wp_send_json_success( $out );
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
function sp_reviews_generate_reply( array $row, string $guidance = '' ) {
	$brand    = (string) ( $row['brand'] ?? '' );
	$store    = (string) ( $row['store'] ?? '' );
	$reviewer = (string) ( $row['reviewer'] ?? '' );
	$rating   = (int) ( $row['star_rating'] ?? 0 );
	$comment  = trim( (string) ( $row['comment'] ?? '' ) );
	$guidance = trim( $guidance );   // the user's edited draft / extra instructions, when regenerating

	// Admin-defined keyword prompts whose keyphrase appears in THIS review (e.g. "green beans" → a note
	// about a recipe change). Injected into the prompt below so the reply can address the known issue;
	// empty when nothing matches. Set in Settings → AI Prompts.
	$kw_context = site_pulse_match_ai_prompts( $comment, $brand );

	// Standing guidance for handling a bad review — added to the prompt for any low rating (≤3 stars), no
	// keyword needed. Empty for 4–5 star reviews or when none is configured. Set in Settings → AI Prompts.
	$critical_context = ( $rating >= 1 && $rating <= 3 ) ? site_pulse_match_critical_prompts( $brand ) : '';

	// Too short to be worth an AI draft (a bare star rating, "Great service!", etc.) — a model call would
	// just burn tokens producing a generic line. Inject a quick canned thank-you instead. (Skipped when the
	// user handed us an edited draft, a keyword prompt matched, or a critical-review prompt applies — then
	// we always run the model so a bad review still gets a real, on-message reply.)
	if ( '' === $guidance && '' === $kw_context && '' === $critical_context && mb_strlen( $comment ) < 40 ) {
		$canned = [
			'Thank you!',
			'Thank you for the review!',
			'We appreciate the review!',
			'Thanks for taking the time to review our business!',
			'We appreciate your business!',
			'Thank you for your business!',
			'Thanks!',
		];
		if ( 5 === $rating ) $canned[] = 'Thanks for the 5 stars!'; // only when it's actually 5 stars
		return $canned[ array_rand( $canned ) ];
	}

	// Brand voice: defaults to whatever's set in Settings → AI Prompts → Company Voice for this brand (or
	// the blank-brand default). A site can still override via this filter (functions-site.php); if no voice
	// is configured anywhere, the prompt's own rules cover tone and no "Brand voice" section is added.
	$voice = (string) apply_filters( 'site_pulse_review_reply_voice', site_pulse_get_company_voice( $brand ), $brand, $row );

	// Random angle each call so regenerated drafts don't converge on the same phrasing. Skipped when the
	// user supplied an edited draft — we follow THEIR wording rather than a random opener.
	$nudge = '';
	if ( '' === $guidance ) {
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
	}

	$parts = preg_split( '/\s+/', trim( $reviewer ) );
	$first = $parts ? $parts[0] : '';

	$ctx  = 'Location: ' . ( $store ?: '(unspecified)' ) . "\n";
	$ctx .= 'Reviewer first name: ' . ( $first ?: '(unknown)' ) . "\n";
	$ctx .= "Star rating: {$rating} of 5\n";
	$ctx .= 'Review: ' . ( '' !== $comment ? $comment : "(no written comment — a {$rating}-star rating only)" ) . "\n";

	// Topic-specific context the business flagged (only present because the review mentions those topics).
	if ( '' !== $kw_context ) {
		$ctx .= "\nContext the business wants reflected for topics this review raises — weave it in naturally where it fits (don't quote it verbatim, don't over-explain, never invent specifics beyond it):\n{$kw_context}\n";
	}

	// Standing "how we handle bad reviews" guidance — only present for low-rated reviews (≤3 stars).
	if ( '' !== $critical_context ) {
		$ctx .= "\nThis is a critical review ({$rating} of 5). The business's standing guidance for replying to negative reviews — follow it closely (apply what fits this review; don't quote it verbatim or invent specifics beyond it):\n{$critical_context}\n";
	}

	if ( '' === $guidance ) {
		// Proportional length: the reply must not be longer than the review. Give the model the actual size.
		$rev_words = max( 1, str_word_count( $comment ) );
		$ctx .= "\nLength: keep the reply no longer than the review itself — this review is about {$rev_words} words, so reply in roughly that many words or fewer. A short review gets a short reply.";

		if ( '' !== $nudge ) $ctx .= "\nStyle nudge for THIS draft (so it differs from previous replies): {$nudge}";
	} else {
		// The user edited the draft before hitting Regenerate. Treat their version as the PRIMARY instruction
		// for the final reply — keep their wording, additions, deletions, tone, and any directions — and use
		// the review above only as context. They may have rewritten it, trimmed it, or left a note about what
		// to change; honor all of it. Return one polished reply (no preamble, no quotes), roughly matching the
		// length and structure of their version.
		$ctx .= "\n\nThe user has revised a draft of this reply — their version is below. Treat it as the"
			. " PRIMARY instruction for the final reply: preserve their wording, additions, deletions, tone,"
			. " and any directions they wrote (a line like \"make it warmer\" or \"mention the refund\" is an"
			. " instruction to follow, not text to echo). The review above is only context. Produce a single"
			. " polished reply, no preamble or surrounding quotes, roughly matching the length and structure of"
			. " their version.\n\nThe user's revised draft / instructions:\n\"\"\"\n{$guidance}\n\"\"\"";
	}

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
	if ( ! ( site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( $user_id ) ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}
	$review_id = sanitize_text_field( wp_unslash( $_POST['review_id'] ?? '' ) );
	if ( '' === $review_id ) wp_send_json_error( [ 'message' => 'Missing review.' ] );

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		'SELECT reviewer, comment, star_rating, brand, store FROM ' . sp_reviews_table() . ' WHERE review_id = %s', $review_id
	), ARRAY_A );
	if ( ! $row ) wp_send_json_error( [ 'message' => 'Review not found — refresh the list.' ] );

	$guidance = sanitize_textarea_field( wp_unslash( $_POST['guidance'] ?? '' ) );
	$reply    = sp_reviews_generate_reply( $row, $guidance );
	if ( is_wp_error( $reply ) ) wp_send_json_error( [ 'message' => $reply->get_error_message() ] );

	wp_send_json_success( [ 'reply' => $reply ] );
}


/*--------------------------------------------------------------
# AI Prompts — keyword-targeted context injected into review replies
# (Settings → AI Prompts). Each entry = a keyword/keyphrase + details; when the
# keyphrase appears in a review, its details are added to the reply prompt.
--------------------------------------------------------------*/

// Stored as a JSON list of { keyword, details } under the 'ai_review_prompts' setting.
function site_pulse_get_ai_prompts(): array {
	$raw = site_pulse_get_setting( 'ai_review_prompts', '' );
	$arr = $raw ? json_decode( $raw, true ) : [];
	if ( ! is_array( $arr ) ) return [];
	$out = [];
	foreach ( $arr as $p ) {
		if ( ! is_array( $p ) ) continue;
		$kw = trim( (string) ( $p['keyword'] ?? '' ) );
		$dt = trim( (string) ( $p['details'] ?? '' ) );
		if ( '' === $kw || '' === $dt ) continue;
		$out[] = [ 'brand' => trim( (string) ( $p['brand'] ?? '' ) ), 'keyword' => $kw, 'details' => $dt ];
	}
	return $out;
}

// Return the details (one per matched prompt, as "- …" lines) whose keyword/keyphrase appears in $text.
// Brand-scoped like the voices: a prompt with a brand only applies when it matches $brand (case-insensitive);
// a blank brand applies to every brand. The keyword field may hold comma-separated phrases — ANY matches.
function site_pulse_match_ai_prompts( string $text, string $brand = '' ): string {
	$text = trim( $text );
	if ( '' === $text ) return '';
	$lower    = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
	$lc       = $lower( $text );
	$brand_lc = $lower( trim( $brand ) );
	$lines    = [];
	foreach ( site_pulse_get_ai_prompts() as $p ) {
		$pb = $lower( trim( (string) ( $p['brand'] ?? '' ) ) );
		if ( '' !== $pb && $pb !== $brand_lc ) continue;          // brand-specific prompt for a different brand
		foreach ( explode( ',', $p['keyword'] ) as $kw ) {
			$kw = trim( $kw );
			if ( '' === $kw ) continue;
			if ( false !== strpos( $lc, $lower( $kw ) ) ) { $lines[] = '- ' . $p['details']; break; }
		}
	}
	return implode( "\n", $lines );
}

/*--------------------------------------------------------------
# Critical Review Prompt — standing guidance injected into EVERY reply draft for a
# low-rated review (3 stars or less), no keyword needed. Same brand scoping as the
# keyword prompts (blank brand = every brand). Stored as a JSON list of
# { brand, details } under 'ai_review_critical'. Set in Settings → AI Prompts.
--------------------------------------------------------------*/

function site_pulse_get_critical_prompts(): array {
	$raw = site_pulse_get_setting( 'ai_review_critical', '' );
	$arr = $raw ? json_decode( $raw, true ) : [];
	if ( ! is_array( $arr ) ) return [];
	$out = [];
	foreach ( $arr as $p ) {
		if ( ! is_array( $p ) ) continue;
		$dt = trim( (string) ( $p['details'] ?? '' ) );
		if ( '' === $dt ) continue;
		$out[] = [ 'brand' => trim( (string) ( $p['brand'] ?? '' ) ), 'details' => $dt ];
	}
	return $out;
}

// Details ("- …" lines) for every critical-review prompt that applies to $brand (exact brand match, or a
// blank-brand default). Rating-agnostic — the caller decides WHEN to use it (only for low-rated reviews).
function site_pulse_match_critical_prompts( string $brand = '' ): string {
	$lower    = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
	$brand_lc = $lower( trim( $brand ) );
	$lines    = [];
	foreach ( site_pulse_get_critical_prompts() as $p ) {
		$pb = $lower( trim( (string) ( $p['brand'] ?? '' ) ) );
		if ( '' !== $pb && $pb !== $brand_lc ) continue;   // brand-specific prompt for a different brand
		$lines[] = '- ' . $p['details'];
	}
	return implode( "\n", $lines );
}

// Brand/label suggestions for the AI Prompts pickers: distinct review brands on this install + agency
// client labels when this is the review hub. Sorted, de-duped (case-insensitively).
function site_pulse_ai_brand_options(): array {
	$seen = [];   // lowercase => original
	$add  = function ( $s ) use ( &$seen ) {
		$s = trim( (string) $s );
		if ( '' === $s ) return;
		$k = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
		if ( ! isset( $seen[ $k ] ) ) $seen[ $k ] = $s;
	};

	// This site's configured locations (from the hub) — the authoritative brand list for a Site Pulse
	// install, populated even before any reviews are stored locally (e.g. Babe's / Bubba's on MyRovin).
	if ( function_exists( 'sp_reviews_locations' ) ) {
		foreach ( sp_reviews_locations() as $l ) $add( $l['brand'] ?? '' );
	}

	if ( function_exists( 'sp_reviews_table' ) ) {
		global $wpdb;
		$t = sp_reviews_table();
		foreach ( (array) $wpdb->get_col( "SELECT DISTINCT brand FROM $t WHERE brand IS NOT NULL AND brand <> ''" ) as $b ) $add( $b );
	}

	if ( class_exists( 'BPGBP_Hub' ) && method_exists( 'BPGBP_Hub', 'get_site_map' ) ) {
		$map = BPGBP_Hub::get_site_map();
		if ( is_array( $map ) ) {
			// Only the site-level `label` per client — that's exactly what the agency reply path matches a
			// brand against ($cfg['label']). The per-location labels (GBP/Facebook location titles) are NOT
			// used for matching and would just duplicate each client (one for Google, one for Facebook).
			foreach ( $map as $cfg ) {
				if ( is_array( $cfg ) ) $add( $cfg['label'] ?? '' );
			}
		}
	}

	$list = array_values( $seen );
	natcasesort( $list );
	return array_values( $list );
}

/*--------------------------------------------------------------
# Company Voice — the brand tone the AI writes review replies in (Settings → AI
# Prompts). Stored as a JSON list of { brand, voice }; feeds the default value of
# the site_pulse_review_reply_voice filter (a functions-site.php filter can still
# override). Brand-keyed so a multi-brand company (e.g. Babe's vs Bubba's) can
# differ; a blank brand is the default voice for any brand.
--------------------------------------------------------------*/

function site_pulse_get_company_voices(): array {
	$raw = site_pulse_get_setting( 'ai_company_voices', '' );
	$arr = $raw ? json_decode( $raw, true ) : [];
	if ( ! is_array( $arr ) ) return [];
	$out = [];
	foreach ( $arr as $v ) {
		if ( ! is_array( $v ) ) continue;
		$voice = trim( (string) ( $v['voice'] ?? '' ) );
		if ( '' === $voice ) continue;
		$out[] = [ 'brand' => trim( (string) ( $v['brand'] ?? '' ) ), 'voice' => $voice ];
	}
	return $out;
}

// The configured voice for $brand: exact brand match (case-insensitive) wins, else the blank-brand
// default, else '' (no voice).
function site_pulse_get_company_voice( string $brand = '' ): string {
	$lc      = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $brand ) ) : strtolower( trim( $brand ) );
	$default = '';
	foreach ( site_pulse_get_company_voices() as $v ) {
		$b = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $v['brand'] ) ) : strtolower( trim( $v['brand'] ) );
		if ( '' === $b ) { if ( '' === $default ) $default = $v['voice']; continue; }
		if ( '' !== $lc && $b === $lc ) return $v['voice'];
	}
	return $default;
}

add_action( 'wp_ajax_site_pulse_get_ai_prompts', 'site_pulse_ajax_get_ai_prompts' );
function site_pulse_ajax_get_ai_prompts(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;
	wp_send_json_success( [
		'prompts'  => site_pulse_get_ai_prompts(),
		'voices'   => site_pulse_get_company_voices(),
		'critical' => site_pulse_get_critical_prompts(),
		'brands'   => site_pulse_ai_brand_options(),
	] );
}

add_action( 'wp_ajax_site_pulse_save_ai_prompts', 'site_pulse_ajax_save_ai_prompts' );
function site_pulse_ajax_save_ai_prompts(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	// Each section is saved only when its key is present, so a partial save never wipes the other.
	if ( isset( $_POST['prompts'] ) ) {
		$raw = json_decode( (string) wp_unslash( $_POST['prompts'] ), true );
		$out = [];
		if ( is_array( $raw ) ) {
			foreach ( $raw as $p ) {
				if ( ! is_array( $p ) ) continue;
				$br = trim( sanitize_text_field( (string) ( $p['brand'] ?? '' ) ) );
				$kw = trim( sanitize_text_field( (string) ( $p['keyword'] ?? '' ) ) );
				$dt = trim( sanitize_textarea_field( (string) ( $p['details'] ?? '' ) ) );
				if ( '' === $kw || '' === $dt ) continue;     // keyword + details required (brand optional)
				$out[] = [ 'brand' => mb_substr( $br, 0, 100 ), 'keyword' => mb_substr( $kw, 0, 200 ), 'details' => mb_substr( $dt, 0, 2000 ) ];
				if ( count( $out ) >= 100 ) break;            // sane cap
			}
		}
		site_pulse_set_setting( 'ai_review_prompts', wp_json_encode( $out ) );
	}

	if ( isset( $_POST['voices'] ) ) {
		$rawV = json_decode( (string) wp_unslash( $_POST['voices'] ), true );
		$outV = [];
		if ( is_array( $rawV ) ) {
			foreach ( $rawV as $v ) {
				if ( ! is_array( $v ) ) continue;
				$brand = trim( sanitize_text_field( (string) ( $v['brand'] ?? '' ) ) );
				$voice = trim( sanitize_textarea_field( (string) ( $v['voice'] ?? '' ) ) );
				if ( '' === $voice ) continue;                // voice required; brand optional
				$outV[] = [ 'brand' => mb_substr( $brand, 0, 100 ), 'voice' => mb_substr( $voice, 0, 2000 ) ];
				if ( count( $outV ) >= 50 ) break;
			}
		}
		site_pulse_set_setting( 'ai_company_voices', wp_json_encode( $outV ) );
	}

	if ( isset( $_POST['critical'] ) ) {
		$rawC = json_decode( (string) wp_unslash( $_POST['critical'] ), true );
		$outC = [];
		if ( is_array( $rawC ) ) {
			foreach ( $rawC as $c ) {
				if ( ! is_array( $c ) ) continue;
				$brand = trim( sanitize_text_field( (string) ( $c['brand'] ?? '' ) ) );
				$dt    = trim( sanitize_textarea_field( (string) ( $c['details'] ?? '' ) ) );
				if ( '' === $dt ) continue;                   // details required; brand optional
				$outC[] = [ 'brand' => mb_substr( $brand, 0, 100 ), 'details' => mb_substr( $dt, 0, 2000 ) ];
				if ( count( $outC ) >= 50 ) break;
			}
		}
		site_pulse_set_setting( 'ai_review_critical', wp_json_encode( $outC ) );
	}

	wp_send_json_success( [
		'prompts'  => site_pulse_get_ai_prompts(),
		'voices'   => site_pulse_get_company_voices(),
		'critical' => site_pulse_get_critical_prompts(),
	] );
}
