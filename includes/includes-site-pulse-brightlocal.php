<?php
/* Battle Plan Web Design — Site Pulse: BrightLocal (reviews from the directories we don't pull ourselves)

/*--------------------------------------------------------------
BrightLocal aggregates reviews from directories our own pipelines don't reach —
TripAdvisor, Yelp, Foursquare and a long tail of smaller sites. Google and Facebook
keep coming from the GBP hub / Facebook hub exactly as before, on every install,
whether or not BrightLocal is in play; anything BrightLocal reports for those two
platforms is DROPPED at the ingest boundary so our own feeds stay the only copy.
(sp_review_source_is_native() in includes-site-pulse-reviews.php is that test.)

The key belongs to the CLIENT, not the agency — each client has their own BrightLocal
account, plan and credits — so it lives in this install's Settings → API Keys rather
than on the hub. No key means every function here no-ops: no cron, no ingest, and the
Reviews panel simply keeps showing the Google + Facebook it always did.

THE ENDPOINT CONTRACT (confirmed against the live account, 2026-08-16):

    base    https://api.brightlocal.com/manage/v1
    auth    x-api-key: <key>          (header; the LEGACY tools.brightlocal.com API used an
                                       api-key QUERY param — different generation, don't mix)
    accept  application/json
    reviews GET /rm/reports/{report_id}/reviews

Note the shape: reviews hang off a REPORT, not a location. Each Reputation Manager report
covers exactly one location, so the mapping Site Pulse stores is location → report_id, and
the ingest walks one report at a time. `rm` is the Reputation Manager tool prefix, matching
the legacy API's /v2/ct/ (Citation Tracker) and /v4/gpw/ (GMB) convention.

CURRENT STATE — the base and auth are known; the RESPONSE SHAPES are not (the developer
portal renders client-side, so the field names can only be read off a live call). This file
ships the request helper plus the probe used to read them. The cron and the ingest get
written once a real reports/reviews payload has been seen.
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Credentials
--------------------------------------------------------------*/

/**
 * This install's BrightLocal API key. Constant first so a staging box can override
 * without touching the client's saved settings; otherwise the value pasted into
 * Settings → API Keys.
 *
 * An empty string is the off switch for the whole integration.
 */
function bp_brightlocal_key(): string {
	if ( defined( 'BP_BRIGHTLOCAL_API_KEY' ) && BP_BRIGHTLOCAL_API_KEY ) return (string) BP_BRIGHTLOCAL_API_KEY;
	return function_exists( 'site_pulse_get_setting' ) ? (string) site_pulse_get_setting( 'brightlocal_api_key', '' ) : '';
}

/** Is BrightLocal configured on this install at all? */
function bp_brightlocal_enabled(): bool {
	return '' !== bp_brightlocal_key();
}

/** API root. Filterable so a future API generation doesn't need a code change here. */
function bp_brightlocal_base(): string {
	return (string) apply_filters( 'bp_brightlocal_api_base', 'https://api.brightlocal.com/manage/v1' );
}


/*--------------------------------------------------------------
# Request helper
--------------------------------------------------------------*/

/**
 * GET a BrightLocal endpoint. $path is relative to the API root ('/rm/reports').
 * Returns the decoded body, or a WP_Error — including for a non-2xx, so a caller never
 * mistakes an error payload for data.
 *
 * No key = WP_Error, never a request. That's the gate that keeps every install without
 * BrightLocal completely inert.
 */
function bp_brightlocal_get( string $path, array $query = [], int $timeout = 20 ) {
	$key = bp_brightlocal_key();
	if ( '' === $key ) return new WP_Error( 'bl_no_key', 'No BrightLocal API key is configured.' );

	$url = rtrim( bp_brightlocal_base(), '/' ) . '/' . ltrim( $path, '/' );
	if ( $query ) $url = add_query_arg( $query, $url );

	$res = wp_remote_get( $url, [
		'timeout' => $timeout,
		'headers' => [ 'Accept' => 'application/json', 'x-api-key' => $key ],
	] );
	if ( is_wp_error( $res ) ) return $res;

	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( $code < 200 || $code >= 300 ) {
		// Surface BrightLocal's own message when it sent one — its errors are JSON and useful.
		$msg = is_array( $body ) ? (string) ( $body['message'] ?? '' ) : '';
		return new WP_Error( 'bl_http_' . $code, sprintf( 'BrightLocal returned %d%s', $code, $msg ? ': ' . $msg : '' ) );
	}
	return $body;
}


/*--------------------------------------------------------------
# Endpoints
--------------------------------------------------------------*/

/*
A Reputation Manager report covers exactly one location, and every report row carries the
location_id it belongs to — so the location→report pairing is READ LIVE on each sync rather
than stored. Nothing to keep in step, nothing to go stale when a report is deleted and remade.

Row shape:
  report_id 643042 · location_id 4113629 · complete true · is_running false
  last_update "2026-08-16 18:48:19"   ← the incremental key: unchanged means nothing to re-fetch
*/
function bp_brightlocal_reports() {
	$res = bp_brightlocal_get( '/rm/reports' );
	if ( is_wp_error( $res ) ) return $res;
	return is_array( $res['items'] ?? null ) ? $res['items'] : [];
}

/**
 * One page of a report's reviews. Returns [ items[], total ] or a WP_Error.
 *
 * NOTE: the page parameter name is the one part of the contract still unconfirmed — the docs
 * portal can't be read server-side and the sample report only had a single review, so there was
 * no paging to observe. `page` is the assumption; sp_bl_sync_report() defends against it being
 * wrong by stopping the moment a page returns nothing new (see there), so a bad guess costs us
 * the tail of a report, never a runaway loop or duplicate rows.
 */
function bp_brightlocal_report_reviews( int $report_id, int $page = 1, string $directory = '' ) {
	$query = [];
	if ( $page > 1 )      $query['page']      = $page;
	if ( '' !== $directory ) $query['directory'] = $directory;

	$res = bp_brightlocal_get( sprintf( '/rm/reports/%d/reviews', $report_id ), $query );
	if ( is_wp_error( $res ) ) return $res;
	return [
		'items' => is_array( $res['items'] ?? null ) ? $res['items'] : [],
		'total' => (int) ( $res['total_count'] ?? 0 ),
	];
}


/*--------------------------------------------------------------
# Review mapping
--------------------------------------------------------------*/

/**
 * Which platform slug a BrightLocal review belongs to. It sends both a lowercase `directory`
 * ("google") and a display `source` ("Google"). The registry keys ARE BrightLocal's directory
 * values, so `directory` normally matches outright; the label pass is the safety net for a
 * platform they add later under a name we haven't registered.
 */
function sp_bl_source_slug( array $item ): string {
	$all = sp_review_sources();

	$directory = sp_review_source_slug( (string) ( $item['directory'] ?? '' ) );
	if ( $directory && isset( $all[ $directory ] ) ) return $directory;

	$source = sp_review_source_slug( (string) ( $item['source'] ?? $item['name'] ?? '' ) );
	if ( $source && isset( $all[ $source ] ) ) return $source;

	return $directory ?: $source;   // unregistered platform — still stored, still filterable
}

/**
 * Turn one BrightLocal review into the shape sp_reviews_upsert() consumes, or NULL to drop it.
 *
 * Dropped on purpose:
 *  - anything from a NATIVE platform (Google, Facebook). We run our own pipelines for those on
 *    every install; taking BrightLocal's copy as well would double every Google review under a
 *    different id and corrupt every average, count and trend in the panel.
 *  - unrated rows. Foursquare "tips", InsiderPages and Citysearch come through with rating 0 —
 *    they aren't ratings, and storing them as 0 stars would drag every average down.
 *  - anything BrightLocal doesn't call a review.
 */
function sp_bl_map_review( array $item ): ?array {
	$slug = sp_bl_source_slug( $item );
	if ( '' === $slug || sp_review_source_is_native( $slug ) ) return null;
	if ( 'review' !== (string) ( $item['type'] ?? 'review' ) ) return null;

	$rating = (int) ( $item['rating'] ?? 0 );
	if ( $rating < 1 ) return null;

	$id = (string) ( $item['review_id'] ?? '' );
	if ( '' === $id ) return null;

	// Some platforms (TripAdvisor especially) carry a headline separate from the body. The
	// reviews table has no title column, so fold it in rather than lose it.
	$text  = trim( (string) ( $item['text'] ?? '' ) );
	$title = trim( (string) ( $item['title'] ?? '' ) );
	if ( '' !== $title && 0 !== stripos( $text, $title ) ) {
		$text = $title . "\n\n" . $text;
	}

	$published = (string) ( $item['publish_time'] ?? '' );

	return [
		// Namespaced so a BrightLocal id can never collide with a native Google review token
		// in the table's single global UNIQUE KEY on review_id.
		'reviewId'   => 'bl:' . $id,
		'reviewer'   => (string) ( $item['author'] ?? '' ),
		'photo'      => '',                 // BrightLocal returns no reviewer avatar
		'starRating' => $rating,
		'comment'    => $text,
		'createTime' => $published,
		// No separate edit timestamp in the payload — `hash` changes when the text does, but
		// there's nothing to date it to, so update time tracks publish time.
		'updateTime' => $published,
		'reply'      => null,               // owner replies aren't exposed by this endpoint
		'_source'    => $slug,
	];
}


/*--------------------------------------------------------------
# Location resolution
--------------------------------------------------------------*/

/*
A report says which BrightLocal location it covers; we need the Site Pulse store it belongs to,
because store/brand are what every filter, scorecard and trend in the Reviews panel group by.

The pairing lives in sp_locations.meta as `brightlocal_location_id` — an EXPLICIT mapping, not a
name match, so renaming a location on either side can't silently detach a store's reviews. It's
resolved once by postcode against BrightLocal's own location list and then persisted, so the
expensive /locations call happens on first contact and effectively never again.
*/

/** Cached BrightLocal location list, keyed by location_id. /locations is ~150KB, so cache hard. */
function sp_bl_remote_locations(): array {
	$cached = get_transient( 'sp_bl_locations' );
	if ( is_array( $cached ) ) return $cached;

	$res = bp_brightlocal_get( '/locations', [], 30 );
	if ( is_wp_error( $res ) ) return [];

	$out = [];
	foreach ( (array) ( $res['items'] ?? [] ) as $row ) {
		// Each item wraps the record: { distance, location: { ... } }
		$loc = is_array( $row['location'] ?? null ) ? $row['location'] : $row;
		$id  = (int) ( $loc['location_id'] ?? 0 );
		if ( $id ) $out[ $id ] = $loc;
	}
	set_transient( 'sp_bl_locations', $out, DAY_IN_SECONDS );
	return $out;
}

/** This install's locations, with any stored BrightLocal id decoded from meta. */
function sp_bl_local_locations(): array {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT id, name, zip, is_store, meta FROM ' . site_pulse_table( 'locations' ) . " WHERE status = 'active'", ARRAY_A ) ?: [];
	foreach ( $rows as &$r ) {
		$meta     = json_decode( (string) $r['meta'], true );
		$r['meta'] = is_array( $meta ) ? $meta : [];
		$r['bl_id'] = (int) ( $r['meta']['brightlocal_location_id'] ?? 0 );
	}
	return $rows;
}

/** Persist a resolved pairing onto the Site Pulse location, leaving the rest of meta intact. */
function sp_bl_store_mapping( int $sp_location_id, int $bl_location_id ): void {
	global $wpdb;
	$table = site_pulse_table( 'locations' );
	$raw   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta FROM $table WHERE id = %d", $sp_location_id ) );
	$meta  = json_decode( $raw, true );
	if ( ! is_array( $meta ) ) $meta = [];
	$meta['brightlocal_location_id'] = $bl_location_id;
	$wpdb->update( $table, [ 'meta' => wp_json_encode( $meta ) ], [ 'id' => $sp_location_id ] );
}

/**
 * The Site Pulse store a BrightLocal location maps to, or NULL to skip this report entirely.
 *
 * NULL means: no matching store, or the match is a non-store location (the accounting office,
 * a storage yard). Rovin's BrightLocal account carries a report for "Rovin, Inc." — the corporate
 * office — and its stray corporate-listing reviews have no business in a per-restaurant panel.
 */
function sp_bl_resolve_location( int $bl_location_id ): ?array {
	$locals = sp_bl_local_locations();

	$match = null;
	foreach ( $locals as $row ) {
		if ( $row['bl_id'] === $bl_location_id ) { $match = $row; break; }
	}

	if ( ! $match ) {
		// First contact for this location: match on postcode, then remember the answer.
		$remote = sp_bl_remote_locations();
		$zip    = trim( (string) ( $remote[ $bl_location_id ]['address']['postcode'] ?? '' ) );
		if ( '' === $zip ) return null;

		foreach ( $locals as $row ) {
			if ( 0 === strcasecmp( trim( (string) $row['zip'] ), $zip ) ) { $match = $row; break; }
		}
		if ( ! $match ) return null;

		sp_bl_store_mapping( (int) $match['id'], $bl_location_id );
		site_pulse_log( 'brightlocal_mapped', sprintf( 'Mapped BrightLocal location %d to %s', $bl_location_id, $match['name'] ) );
	}

	if ( empty( $match['is_store'] ) ) return null;

	// Adopt the label and brand the existing (Google) reviews already use for this store, so
	// BrightLocal rows land under the same labels the store filter, scorecard and trends group by.
	$canon = sp_bl_canonical_store( (string) $match['name'] );

	// A store with no Google listing keeps its own name — pick up whatever brand its rows already carry.
	if ( '' === $canon['brand'] ) {
		global $wpdb;
		$canon['brand'] = (string) $wpdb->get_var( $wpdb->prepare(
			'SELECT brand FROM ' . sp_reviews_table() . " WHERE store = %s AND brand <> '' LIMIT 1",
			$canon['store']
		) );
	}

	return $canon;
}

/**
 * The canonical review label for a Site Pulse location name, as [ store, brand ].
 *
 * Google rows are stamped with the GBP listing's OWN label — "Babe's Chicken — Cedar Hill" — while
 * a BrightLocal report only knows the sp_locations name, "Babe's Chicken Cedar Hill". Stamping the
 * raw name splits one restaurant into two stores, and not just cosmetically: the panel's store
 * filter is populated from the GBP labels and matches `store` exactly, so picking a store dropped
 * every Yahoo/Yelp/TripAdvisor review for it, and the scorecard counted the store twice.
 *
 * So the comparison ignores punctuation and spacing (same normalization the supervisor map uses)
 * and the GBP label wins whenever one matches. A store Google doesn't list keeps its own name.
 */
function sp_bl_canonical_store( string $name, ?array $locations = null ): array {
	$norm = function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) ); };
	$key  = $norm( $name );

	if ( '' !== $key ) {
		// Pass the list in when looping — an empty cache makes this a hub request, not a free read.
		foreach ( ( null === $locations ? sp_reviews_locations() : $locations ) as $L ) {
			$label = (string) ( $L['label'] ?? '' );
			if ( '' !== $label && $norm( $label ) === $key ) {
				return [ 'store' => $label, 'brand' => (string) ( $L['brand'] ?? '' ) ];
			}
		}
	}
	return [ 'store' => $name, 'brand' => '' ];
}

/**
 * One-time: re-stamp directory rows written before the labels were canonicalized above.
 *
 * Cheap enough to run at the head of every sync (one DISTINCT, then nothing to do), and
 * self-healing — it also catches rows stranded by a GBP listing that gets renamed later.
 */
function sp_bl_canonicalize_stored_stores(): void {
	global $wpdb;
	$t      = sp_reviews_table();
	$stores = $wpdb->get_col( "SELECT DISTINCT store FROM $t WHERE store <> ''" ) ?: [];
	if ( ! $stores ) return;

	$locs = sp_reviews_locations();
	if ( ! $locs ) return;   // no Google labels to canonicalize against — leave the rows alone

	foreach ( $stores as $store ) {
		$store = (string) $store;
		$canon = sp_bl_canonical_store( $store, $locs );
		if ( $canon['store'] === $store ) continue;   // already canonical (every Google row is)

		// Brand went blank on these rows too: the old lookup keyed on a name no Google row used.
		$sql = ( '' !== $canon['brand'] )
			? $wpdb->prepare( "UPDATE $t SET store = %s, brand = %s WHERE store = %s", $canon['store'], $canon['brand'], $store )
			: $wpdb->prepare( "UPDATE $t SET store = %s WHERE store = %s", $canon['store'], $store );

		$rows = (int) $wpdb->query( $sql );
		if ( $rows ) {
			site_pulse_log( 'brightlocal_relabel', sprintf( 'Re-stamped %d reviews from "%s" to "%s"', $rows, $store, $canon['store'] ) );
		}
	}
}


/*--------------------------------------------------------------
# Sync
--------------------------------------------------------------*/

/** Per-report sync state: report_id => [ last_update, synced_at, imported ]. */
function sp_bl_state(): array {
	$raw = site_pulse_get_setting( 'brightlocal_sync_state', '' );
	$d   = $raw ? json_decode( $raw, true ) : null;
	return is_array( $d ) ? $d : [];
}
function sp_bl_state_save( array $state ): void {
	site_pulse_set_setting( 'brightlocal_sync_state', wp_json_encode( $state ) );
}

/** Oldest review date to import. Keeps a decade of dead-directory history out of a 30-day panel. */
function sp_bl_backfill_cutoff(): string {
	$months = (int) site_pulse_get_setting( 'brightlocal_backfill_months', '24' );
	if ( $months < 1 ) $months = 24;
	return gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );
}

/**
 * Pull one report's reviews and upsert them. Returns a per-report summary.
 *
 * Reviews from one report can span several platforms, and sp_reviews_upsert() stamps a single
 * source per batch — so rows are grouped by platform and written one group at a time.
 */
function sp_bl_sync_report( array $report ): array {
	$report_id = (int) ( $report['report_id'] ?? 0 );
	$place     = sp_bl_resolve_location( (int) ( $report['location_id'] ?? 0 ) );
	if ( ! $report_id || ! $place ) return [ 'report_id' => $report_id, 'skipped' => 'unmapped or non-store location' ];

	/*
	Fetched PER PLATFORM (?directory=), never as one mixed list.

	Two reasons. Correctness first: the mixed list is 85% Google, so a platform with five recent
	reviews among thousands can simply fall outside whatever slice we walk — that's how Yahoo's
	2026 reviews went missing on the first working run. Asking for Yahoo by name can't miss them.
	Efficiency second: Google is never fetched at all, which is ~20x less traffic.

	Within a platform, the list is still OLDEST FIRST at 20 a page, so each one is walked
	BACKWARDS from its last page and stops at the first page that predates the backfill window.
	Most platforms here hold single digits, so that's one request each.
	*/
	$cutoff    = sp_bl_backfill_cutoff();
	$max_pages = (int) apply_filters( 'sp_bl_max_pages', 40 );
	$seen      = [];            // review ids collected this run
	$batches   = [];            // slug => rows
	$partial   = false;
	$total     = 0;

	foreach ( sp_review_sources() as $slug => $meta ) {
		if ( sp_review_source_is_native( $slug ) ) continue;   // our own feeds own Google + Facebook

		$first = bp_brightlocal_report_reviews( $report_id, 1, $slug );
		if ( is_wp_error( $first ) ) continue;                 // unknown directory for this account

		$count = $first['total'];
		if ( $count < 1 ) continue;
		$total += $count;

		$size = max( 1, count( $first['items'] ) );            // page size read, not assumed
		$last = max( 1, (int) ceil( $count / $size ) );
		$done = 0;

		for ( $page = $last; $page >= 1; $page-- ) {
			$res = ( 1 === $page ) ? $first : bp_brightlocal_report_reviews( $report_id, $page, $slug );
			if ( is_wp_error( $res ) ) break;
			$done++;

			$in_window = 0;
			foreach ( $res['items'] as $item ) {
				$rid = (string) ( $item['review_id'] ?? '' );
				if ( '' === $rid || isset( $seen[ $rid ] ) ) continue;
				$seen[ $rid ] = true;

				// Window test on the RAW date, before mapping — a row we go on to discard still
				// tells us where in time this page sits, which is what decides whether to continue.
				$published = (string) ( $item['publish_time'] ?? '' );
				if ( $published && $published < $cutoff ) continue;
				$in_window++;

				$mapped = sp_bl_map_review( $item );
				if ( ! $mapped ) continue;

				$row = $mapped['_source'];
				unset( $mapped['_source'] );
				$batches[ $row ][] = $mapped;
			}

			// Backwards means each page is older than the last, so the first page with nothing
			// in the window ends this platform.
			if ( 0 === $in_window ) break;
			if ( $done >= $max_pages ) { $partial = true; break; }
		}
	}

	/*
	sp_reviews_upsert() builds raw SQL and returns nothing, so a rejected INSERT would leave this
	reporting a happy "12 imported" while the table stayed empty. Count what's actually in the
	table afterwards and carry any wpdb error through, so the sync's own output can be trusted.
	*/
	global $wpdb;
	$imported = 0;
	$db_error = '';
	foreach ( $batches as $slug => $rows ) {
		$wpdb->last_error = '';
		sp_reviews_upsert( $rows, 'bl:' . $report_id, $place['store'], $place['brand'], $slug );
		if ( $wpdb->last_error ) $db_error = $slug . ': ' . $wpdb->last_error;
		$imported += count( $rows );
	}

	$stored = [];
	foreach ( array_keys( $batches ) as $slug ) {
		$stored[ $slug ] = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . sp_reviews_table() . ' WHERE source = %s AND store = %s',
			$slug, $place['store']
		) );
	}

	return [
		'report_id' => $report_id,
		'store'     => $place['store'],
		'fetched'   => count( $seen ),
		'total'     => $total,
		'imported'  => $imported,
		'stored'    => $stored,          // what the table really holds for this store now
		'db_error'  => $db_error,
		'partial'   => $partial,
		'platforms' => array_keys( $batches ),
	];
}

/**
 * Decide what needs syncing, WITHOUT fetching any reviews. One cheap list call.
 *
 * Returns a work list the caller steps through one report at a time. Doing all ten inline was
 * the first version and it died: a full backfill pages through thousands of reviews per report,
 * which blows PHP's execution limit long before it finishes and returns a 502 the browser can't
 * even parse. Same lesson as the AI site audit — long jobs get stepped, never run inline.
 */
function sp_bl_sync_plan( bool $force = false ): array {
	if ( ! bp_brightlocal_enabled() ) return [ 'error' => 'No BrightLocal API key configured.' ];

	// Once per run, not per report — bring any legacy/renamed store labels in line first, so this
	// run's rows and the ones already stored end up under the same store.
	sp_bl_canonicalize_stored_stores();

	$reports = bp_brightlocal_reports();
	if ( is_wp_error( $reports ) ) return [ 'error' => $reports->get_error_message() ];

	$state = sp_bl_state();
	$plan  = [];

	foreach ( $reports as $report ) {
		$rid = (string) ( $report['report_id'] ?? '' );
		if ( '' === $rid ) continue;

		$row = [ 'report_id' => (int) $rid, 'name' => (string) ( $report['report_name'] ?? '' ) ];

		// A report mid-refresh serves a partial list; let it finish and catch it next run.
		if ( ! empty( $report['is_running'] ) || ! empty( $report['fetching'] ) ) {
			$plan[] = $row + [ 'skipped' => 'still running' ];
			continue;
		}

		$last = (string) ( $report['last_update'] ?? '' );
		if ( ! $force && '' !== $last && ( $state[ $rid ]['last_update'] ?? '' ) === $last ) {
			$plan[] = $row + [ 'skipped' => 'unchanged' ];
			continue;
		}

		$plan[] = $row + [ 'last_update' => $last ];
	}
	return [ 'plan' => $plan ];
}

/** Sync exactly one report by id, and record its watermark. Used by both the stepped UI and cron. */
function sp_bl_sync_one( int $report_id, bool $force = false ): array {
	$reports = bp_brightlocal_reports();
	if ( is_wp_error( $reports ) ) return [ 'report_id' => $report_id, 'error' => $reports->get_error_message() ];

	$report = null;
	foreach ( $reports as $r ) {
		if ( (int) ( $r['report_id'] ?? 0 ) === $report_id ) { $report = $r; break; }
	}
	if ( ! $report ) return [ 'report_id' => $report_id, 'error' => 'Report not found.' ];

	$result = sp_bl_sync_report( $report );

	// Only record the watermark on a clean run, so a failure retries instead of being skipped.
	if ( empty( $result['error'] ) ) {
		$state = sp_bl_state();
		$state[ (string) $report_id ] = [
			'last_update' => (string) ( $report['last_update'] ?? '' ),
			'synced_at'   => current_time( 'mysql' ),
			'imported'    => (int) ( $result['imported'] ?? 0 ),
		];
		sp_bl_state_save( $state );
	}
	return $result;
}

/**
 * Cron entry point. Works the same plan the UI does, but stops when it runs out of time budget
 * rather than pushing its luck — the watermark means the next run picks up exactly where this
 * one stopped, so a large backfill simply finishes over several nights instead of failing.
 */
function sp_bl_sync_cron(): void {
	$plan = sp_bl_sync_plan();
	if ( ! empty( $plan['error'] ) ) return;

	$deadline = time() + (int) apply_filters( 'sp_bl_cron_budget_seconds', 25 );
	$imported = 0;
	$done     = 0;

	foreach ( $plan['plan'] as $row ) {
		if ( isset( $row['skipped'] ) ) continue;
		if ( time() >= $deadline ) break;

		$r         = sp_bl_sync_one( (int) $row['report_id'] );
		$imported += (int) ( $r['imported'] ?? 0 );
		$done++;
	}

	if ( $done ) {
		site_pulse_log( 'brightlocal_sync', sprintf( 'BrightLocal sync: %d reviews imported across %d reports', $imported, $done ) );
	}
}


/*--------------------------------------------------------------
# Cron
--------------------------------------------------------------*/

/*
Daily, even though reports refresh weekly on most plans. Polling more often than the data
changes costs almost nothing (the `last_update` check short-circuits an unchanged report before
any review call) and it means each location's new batch appears within a day of ITS refresh,
without tracking eleven separate weekly schedules.
*/
add_action( 'init', 'sp_bl_schedule_cron' );
function sp_bl_schedule_cron(): void {
	if ( ! bp_brightlocal_enabled() ) {
		// Key removed → stop the job rather than leave it firing into a no-op forever.
		$ts = wp_next_scheduled( 'site_pulse_brightlocal_sync' );
		if ( $ts ) wp_unschedule_event( $ts, 'site_pulse_brightlocal_sync' );
		return;
	}
	if ( ! wp_next_scheduled( 'site_pulse_brightlocal_sync' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'site_pulse_brightlocal_sync' );
	}
}

add_action( 'site_pulse_brightlocal_sync', 'sp_bl_sync_cron' );

/*
Manual trigger, in TWO modes, because one request can't do the whole job:

  no report_id → planning pass. One cheap list call, returns the work list.
  report_id    → sync that ONE report and return its result.

The browser walks the list, so each HTTP request stays small and a slow or failing report names
itself instead of taking the whole run down with a 502 the JS can't even parse. Gated on
manage_reviews OR manage_api_keys — it writes review data, but it's also the natural "did my key
work" button for whoever just pasted the key.

`force` ignores the last_update watermark — needed after widening the backfill window, when
every report would otherwise look unchanged.
*/
add_action( 'wp_ajax_site_pulse_brightlocal_sync_now', 'site_pulse_ajax_brightlocal_sync_now' );
function site_pulse_ajax_brightlocal_sync_now(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	if ( ! site_pulse_user_can( $user_id, 'manage_reviews' ) && ! site_pulse_user_can( $user_id, 'manage_api_keys' ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}
	if ( ! bp_brightlocal_enabled() ) {
		wp_send_json_error( [ 'message' => 'No BrightLocal API key saved yet.' ] );
	}

	$force     = ! empty( $_POST['force'] );
	$report_id = (int) ( $_POST['report_id'] ?? 0 );

	if ( $report_id ) {
		wp_send_json_success( [ 'result' => sp_bl_sync_one( $report_id, $force ) ] );
	}

	$out = sp_bl_sync_plan( $force );
	if ( ! empty( $out['error'] ) ) wp_send_json_error( [ 'message' => $out['error'] ] );

	wp_send_json_success( $out );
}


/*--------------------------------------------------------------
# Paging probe — finds the pagination + filter parameters
--------------------------------------------------------------*/

/*
The reviews endpoint returns 40 items and a total_count, but the parameter that advances the
list isn't documented anywhere we can read. Guessing cost a whole sync run (every report fetched
the same 40 rows), so this finds it empirically instead: fetch page one, then re-fetch with each
candidate parameter and see which one changes the FIRST review_id.

It also tries a couple of platform filters. If the API can filter server-side, the ingest gets
dramatically cheaper — the reviews we actually want are a thin slice of a mostly-Google list, so
filtering at the source beats paging through hundreds of rows to discard them.
*/
function sp_bl_paging_probe( int $report_id = 0 ): array {
	if ( ! $report_id ) {
		$reports = bp_brightlocal_reports();
		if ( is_wp_error( $reports ) ) return [ 'error' => $reports->get_error_message() ];
		// Prefer a report with plenty of reviews — a one-review report can't demonstrate paging.
		$report_id = (int) ( $reports[0]['report_id'] ?? 0 );
		foreach ( $reports as $r ) {
			if ( ! empty( $r['complete'] ) ) { $report_id = (int) $r['report_id']; break; }
		}
	}
	if ( ! $report_id ) return [ 'error' => 'No reports available to probe.' ];

	$path = sprintf( '/rm/reports/%d/reviews', $report_id );

	$first = bp_brightlocal_get( $path );
	if ( is_wp_error( $first ) ) return [ 'error' => $first->get_error_message() ];

	$items    = (array) ( $first['items'] ?? [] );
	$baseline = (string) ( $items[0]['review_id'] ?? '' );
	$total    = (int) ( $first['total_count'] ?? 0 );

	// Sort order matters as much as the parameter: if the newest reviews come last, the backfill
	// window has to be applied from the other end of the list.
	$dates = array_values( array_filter( array_map( fn( $i ) => (string) ( $i['publish_time'] ?? '' ), $items ) ) );
	$order = ( count( $dates ) > 1 )
		? ( ( $dates[0] > end( $dates ) ) ? 'newest first' : 'oldest first' )
		: 'unknown';

	$candidates = [
		'page=2'                 => [ 'page' => 2 ],
		'offset=40'              => [ 'offset' => 40 ],
		'start=40'               => [ 'start' => 40 ],
		'skip=40'                => [ 'skip' => 40 ],
		'page_number=2'          => [ 'page_number' => 2 ],
		'per_page=40&page=2'     => [ 'per_page' => 40, 'page' => 2 ],
		'limit=40&offset=40'     => [ 'limit' => 40, 'offset' => 40 ],
		'directory=tripadvisor'  => [ 'directory' => 'tripadvisor' ],
		'source=TripAdvisor'     => [ 'source' => 'TripAdvisor' ],
	];

	$rows = [];
	foreach ( $candidates as $label => $query ) {
		$res = bp_brightlocal_get( $path, $query, 12 );
		if ( is_wp_error( $res ) ) {
			$rows[] = [ 'param' => $label, 'result' => 'ERROR: ' . $res->get_error_message() ];
			continue;
		}
		$got   = (array) ( $res['items'] ?? [] );
		$id    = (string) ( $got[0]['review_id'] ?? '' );
		$count = count( $got );
		$tot   = (int) ( $res['total_count'] ?? 0 );

		$rows[] = [
			'param'  => $label,
			'result' => sprintf(
				'%d items, total_count %d, first review_id %s — %s',
				$count, $tot, $id ?: 'none',
				( '' !== $id && $id !== $baseline ) ? '*** CHANGED — this parameter works ***' : 'same as page 1'
			),
		];
	}

	return [
		'report_id' => $report_id,
		'baseline'  => sprintf( 'Page 1: %d items of %d total, first review_id %s, sorted %s', count( $items ), $total, $baseline ?: 'none', $order ),
		'rows'      => $rows,
	];
}

/*
Directory census — which platform slugs this account actually has data for.

`?directory=` filters server-side, but an UNRECOGNISED value is ignored rather than rejected: the
response comes back with the unfiltered total. So a slug is real if and only if its total_count
DIFFERS from the unfiltered baseline. That's the test below.

This exists because chronological paging can miss a sparse platform — Yahoo! Local has a handful
of recent reviews among thousands of Google ones, and whether they land inside the pages we walk
is luck. Fetching per directory removes the luck (and is far cheaper).
*/
function sp_bl_directory_census( int $report_id = 0 ): array {
	if ( ! $report_id ) {
		$reports = bp_brightlocal_reports();
		if ( is_wp_error( $reports ) ) return [ 'error' => $reports->get_error_message() ];
		// Census the biggest report — the widest spread of platforms.
		foreach ( $reports as $r ) {
			if ( ! empty( $r['complete'] ) ) { $report_id = (int) $r['report_id']; break; }
		}
	}
	if ( ! $report_id ) return [ 'error' => 'No reports available.' ];

	$path = sprintf( '/rm/reports/%d/reviews', $report_id );
	$base = bp_brightlocal_get( $path );
	if ( is_wp_error( $base ) ) return [ 'error' => $base->get_error_message() ];
	$baseline = (int) ( $base['total_count'] ?? 0 );

	// Registry slugs plus the spelling variants BrightLocal might use, plus the platforms worth
	// ruling out (Bing/Apple/OpenTable) so the answer is measured rather than assumed.
	$candidates = [
		'google', 'facebook', 'tripadvisor', 'yelp', 'foursquare',
		'yellowpages', 'yellow-pages', 'yahoo', 'yahoolocal', 'yahoo-local',
		'dexknows', 'superpages', 'citysearch', 'insiderpages', 'insider-pages',
		'judysbook', 'judys-book', 'merchantcircle', 'merchant-circle', 'ezlocal',
		'bing', 'apple', 'apple-maps', 'opentable',
	];

	$found = [];
	$ignored = [];
	foreach ( $candidates as $slug ) {
		$res = bp_brightlocal_get( $path, [ 'directory' => $slug ], 10 );
		if ( is_wp_error( $res ) ) continue;
		$tc = (int) ( $res['total_count'] ?? 0 );
		if ( $tc !== $baseline ) {
			$found[ $slug ] = $tc;                        // real slug (0 is a valid answer: none here)
		} else {
			$ignored[] = $slug;                           // unrecognised — filter was ignored
		}
	}

	return [
		'report_id' => $report_id,
		'baseline'  => $baseline,
		'found'     => $found,
		'ignored'   => $ignored,
	];
}

add_action( 'wp_ajax_site_pulse_brightlocal_directories', 'site_pulse_ajax_brightlocal_directories' );
function site_pulse_ajax_brightlocal_directories(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! site_pulse_user_can( $user_id, 'manage_reviews' ) && ! site_pulse_user_can( $user_id, 'manage_api_keys' ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}
	if ( ! bp_brightlocal_enabled() ) wp_send_json_error( [ 'message' => 'No BrightLocal API key saved yet.' ] );

	$out = sp_bl_directory_census( (int) ( $_POST['report_id'] ?? 0 ) );
	if ( ! empty( $out['error'] ) ) wp_send_json_error( [ 'message' => $out['error'] ] );
	wp_send_json_success( $out );
}

add_action( 'wp_ajax_site_pulse_brightlocal_paging', 'site_pulse_ajax_brightlocal_paging' );
function site_pulse_ajax_brightlocal_paging(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	if ( ! site_pulse_user_can( $user_id, 'manage_reviews' ) && ! site_pulse_user_can( $user_id, 'manage_api_keys' ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}
	if ( ! bp_brightlocal_enabled() ) wp_send_json_error( [ 'message' => 'No BrightLocal API key saved yet.' ] );

	$out = sp_bl_paging_probe( (int) ( $_POST['report_id'] ?? 0 ) );
	if ( ! empty( $out['error'] ) ) wp_send_json_error( [ 'message' => $out['error'] ] );

	wp_send_json_success( $out );
}


/*--------------------------------------------------------------
# Connection Probe — reads response shapes
--------------------------------------------------------------*/

/*
The probe found the contract and now serves the next job: reading RESPONSE SHAPES. The
developer portal is a client-rendered app, so the only way to see a payload's field names
is to fetch one — and this panel is where that happens, without a deploy per attempt.

Reading the results:
  200                 → the body shows the response shape
  401 / 403 with JSON → path is right; the key isn't authorized for that API (BrightLocal
                        gates the Data APIs separately — that's a plan question, not a bug)
  404                 → wrong path
  connection error    → wrong host

Leave the box empty to sweep the known endpoints below; paste a full URL to fetch one
directly, which is how you follow an id from one payload into the next.
*/
function sp_bl_probe_bases(): array {
	return [ bp_brightlocal_base() ];
}

/* The endpoints worth looking at, all relative to the confirmed /manage/v1 root. */
function sp_bl_probe_paths(): array {
	return [
		'/rm/reports',          // Reputation Manager report list — reviews hang off these
		'/locations',
		'/clients',
		'/business-categories',
	];
}

/**
 * Ways a key might be presented. Each returns the wp_remote_get args plus any querystring
 * to append, so the caller doesn't need to know which style it's testing.
 */
function sp_bl_probe_auth_styles( string $key ): array {
	return [
		'x-api-key header'      => [ 'headers' => [ 'x-api-key'     => $key ], 'query' => [] ],
		'Authorization: Bearer' => [ 'headers' => [ 'Authorization' => 'Bearer ' . $key ], 'query' => [] ],
		'api-key query param'   => [ 'headers' => [], 'query' => [ 'api-key' => $key ] ],
	];
}

/**
 * Fire one probe request. Returns a result row — never throws, never exposes the key.
 * Bodies are truncated: we want the response SHAPE, not a full review dump.
 */
function sp_bl_probe_request( string $url, array $auth, int $timeout = 6 ): array {
	if ( ! empty( $auth['query'] ) ) {
		$url = add_query_arg( $auth['query'], $url );
	}

	$res = wp_remote_get( $url, [
		'timeout'     => $timeout,
		'redirection' => 2,
		'headers'     => array_merge( [ 'Accept' => 'application/json' ], $auth['headers'] ),
	] );

	// Never let the key reach the screen or the log, even though the operator owns it —
	// the results panel is shareable and screenshots travel.
	$shown_url = sp_bl_redact( $url );

	if ( is_wp_error( $res ) ) {
		return [ 'url' => $shown_url, 'status' => 0, 'type' => '', 'body' => 'CONNECTION ERROR: ' . $res->get_error_message() ];
	}

	// Generous truncation: we're reading response SHAPE, and pagination/meta blocks usually sit
	// AFTER the data array — cut too early and you lose exactly the part that says how to page.
	$body = (string) wp_remote_retrieve_body( $res );
	if ( strlen( $body ) > 6000 ) {
		$body = substr( $body, 0, 3000 ) . "\n\n… (middle trimmed — " . strlen( $body ) . " bytes total) …\n\n" . substr( $body, -2000 );
	}

	return [
		'url'    => $shown_url,
		'status' => (int) wp_remote_retrieve_response_code( $res ),
		'type'   => (string) wp_remote_retrieve_header( $res, 'content-type' ),
		'body'   => sp_bl_redact( $body ),
	];
}

/** Replace the API key wherever it appears (URL or echoed back in a body) with a marker. */
function sp_bl_redact( string $text ): string {
	$key = bp_brightlocal_key();
	if ( '' === $key ) return $text;
	return str_replace( [ $key, rawurlencode( $key ) ], 'REDACTED-KEY', $text );
}


/*--------------------------------------------------------------
# Probe AJAX
--------------------------------------------------------------*/

/*
Gated on manage_api_keys — the same capability that governs the screen this runs from.
(Deliberately NOT gated on god: per the framework's permission rules a feature must be a
grantable capability, and a god already holds every capability anyway.)
*/
add_action( 'wp_ajax_site_pulse_brightlocal_probe', 'site_pulse_ajax_brightlocal_probe' );
function site_pulse_ajax_brightlocal_probe(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	if ( ! site_pulse_user_can( site_pulse_effective_user_id(), 'manage_api_keys' ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$key = bp_brightlocal_key();
	if ( '' === $key ) {
		wp_send_json_error( [ 'message' => 'No BrightLocal API key saved yet — add one above first.' ] );
	}

	$custom = trim( (string) wp_unslash( $_POST['custom_url'] ?? '' ) );
	$styles = sp_bl_probe_auth_styles( $key );
	$rows   = [];

	if ( '' !== $custom ) {
		// Custom mode: one URL, every auth style. This is how you walk the rest of the API
		// once the sweep has found a combination that answers.
		$custom = esc_url_raw( $custom );
		if ( ! $custom || 0 !== stripos( $custom, 'https://' ) ) {
			wp_send_json_error( [ 'message' => 'Custom URL must be a full https:// address.' ] );
		}
		foreach ( $styles as $label => $auth ) {
			$rows[] = [ 'combo' => $label ] + sp_bl_probe_request( $custom, $auth );
		}
	} else {
		/*
		Sweep mode, two phases — because trying every path against every auth style would be
		~66 requests, and a 404 says nothing about auth anyway.

		Phase 1 walks base × path with ONE auth style, just to find a path that exists. Every
		request here is fast: both hosts answer immediately, including their 404s.

		Phase 2 re-tests only the paths that did NOT 404, this time with all three auth styles.
		That's where a 401-vs-200 difference actually tells us something.

		Only non-404s are reported, plus a summary line — thirty "404" rows would bury the
		result. A host that fails to CONNECT is dropped entirely (its remaining paths are
		skipped) so one bad hostname can't spend the whole execution limit on timeouts.
		*/
		$first_style = array_key_first( $styles );
		$hits        = [];
		$tried       = 0;

		foreach ( sp_bl_probe_bases() as $base ) {
			foreach ( sp_bl_probe_paths() as $path ) {
				$url = rtrim( $base, '/' ) . $path;
				$r   = sp_bl_probe_request( $url, $styles[ $first_style ] );
				$tried++;

				if ( 0 === $r['status'] ) {
					$rows[] = [ 'combo' => 'host unreachable' ] + $r;
					continue 2;                     // abandon this host, don't burn timeouts on it
				}
				if ( 404 !== $r['status'] ) $hits[] = $url;
			}
		}

		foreach ( $hits as $url ) {
			foreach ( $styles as $label => $auth ) {
				$rows[] = [ 'combo' => $label ] + sp_bl_probe_request( $url, $auth );
			}
		}

		$rows[] = [
			'combo'  => 'sweep summary',
			'url'    => sprintf( '%d paths tried', $tried ),
			'status' => $hits ? 200 : 404,
			'type'   => '',
			'body'   => $hits
				? "Paths that exist (not 404):\n" . implode( "\n", $hits )
				: "Every candidate path returned 404. Read the correct path off the docs page and use the Custom request box above.",
		];
	}

	site_pulse_log( 'brightlocal_probe', sprintf( 'Ran a BrightLocal connection probe (%d requests)', count( $rows ) ) );

	wp_send_json_success( [
		'rows' => $rows,
		'hint' => 'A 200 means that combination works. A 401/403 returning JSON means the host and path are right but the auth style (or the key\'s authorization) is not. A 404 means wrong path for that host. A connection error means wrong host.',
	] );
}
