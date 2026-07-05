<?php
/**
 * Battle Plan — Google Business Profile Posting Client (framework-native)
 *
 * The client-side counterpart to the GBP hub (includes-gbp-hub.php). When a blog post or a
 * Jobsite GEO post is published on a client site, this fires a signed POST to the hub's
 * bpgbp/v1/post route, which relays it to Google as a local post on THIS site's listing.
 *
 * Like the testimonial receiver in the hub file, this ships in the framework and loads on every
 * install but stays DORMANT unless the site is a configured hub client — i.e. wp-config (or a
 * pairing) supplies BPGBP_HUB_URL + BPGBP_SITE_KEY + BPGBP_SITE_SECRET (read via bpgbp_cfg()).
 * So it works on plain contractor sites that don't run Site Pulse, not only Site Pulse ones.
 *
 * The hub ENFORCES which Google location a site may post to (from bpgbp-sites.json), so the
 * client never sends a location — a compromised site can only ever post to its own listing.
 *
 * ── Turn it off / tune it (client site) ──
 *   define( 'BPGBP_AUTOPOST', false );                       // kill switch, all post types
 *   add_filter( 'bpgbp_client_post_types', fn() => ['post'] ); // e.g. blog only, not jobsites
 *   add_filter( 'bpgbp_client_autopost', '__return_false' );   // programmatic gate
 *   add_filter( 'bpgbp_client_geo_daily_limit', fn() => 2 );   // jobsite-geo posts/day (default 1; 0 = off)
 *   Per-post: set post meta `_bpgbp_skip` = 1 before publish to skip that one.
 *
 * Frequency: a BLOG posts once, on first publish only — re-editing/re-saving never re-posts (first-publish
 * guard + the `_bpgbp_posted` marker). JOBSITE GEO posts are rate-limited to bpgbp_client_geo_daily_limit
 * per site per day (default 1): every published job is QUEUED, and a daily cron drains one (oldest first) per
 * day, so a batch entered on one day spreads out over the following days rather than flooding the listing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const BPGBP_CLIENT_SUMMARY_LIMIT = 1500;      // Google's hard cap on local-post summary length
const BPGBP_CLIENT_CRON_HOOK     = 'bpgbp_client_publish_event'; // per-post blog send
const BPGBP_CLIENT_GEO_DAILY     = 'bpgbp_client_geo_daily';     // recurring daily jobsite-geo drainer
const BPGBP_CLIENT_GEO_SOON      = 'bpgbp_client_geo_soon';      // ad-hoc drain shortly after a publish
const BPGBP_CLIENT_PHOTOS_HOOK   = 'bpgbp_client_photos_event';  // upload a jobsite's photos to the GBP gallery
const BPGBP_CLIENT_POSTED_META   = '_bpgbp_posted';   // stores the Google localPost name once posted
const BPGBP_CLIENT_LOG_META      = '_bpgbp_post_log';  // last attempt result (for debugging)
const BPGBP_CLIENT_ATTEMPTS_META = '_bpgbp_post_attempts'; // failed-send count (queue give-up guard)
const BPGBP_CLIENT_QUEUE_OPT     = 'bpgbp_client_geo_queue';    // FIFO of jobsite-geo post IDs awaiting their day

/** This site can talk to a hub only if all three credentials resolve (constant or paired option). */
function bpgbp_client_is_configured(): bool {
	if ( ! function_exists( 'bpgbp_cfg' ) ) return false;
	return '' !== bpgbp_cfg( 'HUB_URL' ) && '' !== bpgbp_cfg( 'SITE_KEY' ) && '' !== bpgbp_cfg( 'SITE_SECRET' );
}

/** Which post types auto-post on publish. Filterable; blog posts + jobsite geo by default. */
function bpgbp_client_post_types(): array {
	return array_values( array_unique( (array) apply_filters( 'bpgbp_client_post_types', array( 'post', 'jobsite_geo' ) ) ) );
}

// Only wire up the publish hooks on a configured client — and only if not hard-disabled in wp-config.
if ( bpgbp_client_is_configured() && ! ( defined( 'BPGBP_AUTOPOST' ) && ! BPGBP_AUTOPOST ) ) {
	add_action( 'transition_post_status', 'bpgbp_client_on_transition', 20, 3 );
	add_action( BPGBP_CLIENT_CRON_HOOK, 'bpgbp_client_run', 10, 1 );          // blog: send one post
	add_action( BPGBP_CLIENT_GEO_DAILY, 'bpgbp_client_geo_drain', 10, 0 );    // geo: drain the queue (daily)
	add_action( BPGBP_CLIENT_GEO_SOON,  'bpgbp_client_geo_drain', 10, 0 );    // geo: drain shortly after a publish
	add_action( BPGBP_CLIENT_PHOTOS_HOOK, 'bpgbp_client_upload_geo_photos', 10, 1 ); // geo: photos → GBP gallery

	// The daily drainer is what spreads a backlog across days — schedule it once. WP-cron on WP Engine is
	// backed by a real system cron, so a recurring daily event fires reliably.
	if ( ! wp_next_scheduled( BPGBP_CLIENT_GEO_DAILY ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', BPGBP_CLIENT_GEO_DAILY );
	}

	// A one-line GBP status right under the Publish-box date ("Posted to GBP on …" / "Scheduled for GBP …").
	add_action( 'post_submitbox_misc_actions', 'bpgbp_client_submitbox_status' );
	// Live-refresh reader for that line, so a pending post resolves on-screen without a manual reload.
	add_action( 'wp_ajax_bpgbp_client_status', 'bpgbp_client_ajax_status' );
	// Manual "Post to GBP now" trigger from that line (runs the send immediately, bypassing wp-cron).
	add_action( 'wp_ajax_bpgbp_client_post_now', 'bpgbp_client_ajax_post_now' );
}

/**
 * Act whenever a supported post is (re)published. Exactly-once posting is enforced by the `_bpgbp_posted`
 * marker (checked below), NOT by the transition direction — so editing/re-saving a post that already went
 * to GBP never re-posts, while a post that is published but was never posted (e.g. first published before
 * this code shipped, or whose background job never fired) still gets picked up the next time it's saved.
 */
function bpgbp_client_on_transition( $new_status, $old_status, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! ( $post instanceof WP_Post ) ) return;
	if ( 'publish' !== $new_status ) return;                                       // only when live/published
	if ( ! in_array( $post->post_type, bpgbp_client_post_types(), true ) ) return;
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) return;
	if ( '' !== (string) $post->post_password ) return;                            // never post protected content
	if ( get_post_meta( $post->ID, BPGBP_CLIENT_POSTED_META, true ) ) return;      // already sent once
	if ( get_post_meta( $post->ID, '_bpgbp_skip', true ) ) return;                 // per-post opt-out
	if ( ! apply_filters( 'bpgbp_client_autopost', true, $post->post_type, $post ) ) return;

	$is_geo = ( 'jobsite_geo' === $post->post_type );
	$async  = apply_filters( 'bpgbp_client_async', true, $post );

	// Jobsite GEO has TWO independent outputs, each with its own toggle:
	//   (a) Photos → the GBP Photos gallery (every job photo, deduped).
	//   (b) A local post → queued and drained at most one per day (so a batch of jobs spreads over days).
	if ( $is_geo ) {
		// (a) Photos → gallery
		if ( bpgbp_client_gallery_enabled() ) {
			if ( $async ) {
				if ( ! wp_next_scheduled( BPGBP_CLIENT_PHOTOS_HOOK, array( $post->ID ) ) ) {
					wp_schedule_single_event( time() + 45, BPGBP_CLIENT_PHOTOS_HOOK, array( $post->ID ) );
				}
			} else {
				bpgbp_client_upload_geo_photos( $post->ID );
			}
		}

		// (b) Local post → daily-throttled queue
		if ( bpgbp_client_geo_daily_limit() >= 1 ) {
			bpgbp_client_geo_enqueue( $post->ID );
			if ( $async ) {
				if ( ! wp_next_scheduled( BPGBP_CLIENT_GEO_SOON ) ) {
					wp_schedule_single_event( time() + 60, BPGBP_CLIENT_GEO_SOON ); // prompt first-of-day post
				}
			} else {
				bpgbp_client_geo_drain();
			}
		}
		return;
	}

	// Blog → send out-of-band so a slow/failed Google call never blocks (or breaks) the editor save.
	// Filter bpgbp_client_async to false to send inline (e.g. if wp-cron is disabled on the site).
	if ( $async ) {
		if ( ! wp_next_scheduled( BPGBP_CLIENT_CRON_HOOK, array( $post->ID ) ) ) {
			wp_schedule_single_event( time() + 20, BPGBP_CLIENT_CRON_HOOK, array( $post->ID ) );
		}
	} else {
		bpgbp_client_run( $post->ID );
	}
}

/** Cron handler (blog): re-validate, build the payload, send, and record the outcome on the post. */
function bpgbp_client_run( $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) return;
	if ( ! in_array( $post->post_type, bpgbp_client_post_types(), true ) ) return;

	// Safety net: a jobsite-geo post always goes through the daily queue, never this direct path.
	if ( 'jobsite_geo' === $post->post_type ) {
		bpgbp_client_geo_enqueue( $post->ID );
		bpgbp_client_geo_drain();
		return;
	}

	if ( get_post_meta( $post->ID, BPGBP_CLIENT_POSTED_META, true ) ) return; // already posted

	// Per-post lock so a cron run and a manual "Post now" (or two crons) can't both send at once.
	$lock = 'bpgbp_client_send_' . $post->ID;
	if ( get_transient( $lock ) ) return;
	set_transient( $lock, 1, 2 * MINUTE_IN_SECONDS );

	$payload = bpgbp_client_build_payload( $post );
	if ( empty( $payload['summary'] ) ) {
		bpgbp_client_log( $post->ID, 'skipped', 'No summary could be built for this post.' );
		delete_transient( $lock );
		return;
	}

	$result = bpgbp_client_send( $payload );
	delete_transient( $lock );

	if ( is_wp_error( $result ) ) {
		bpgbp_client_log( $post->ID, 'error', $result->get_error_message() );
		return;
	}

	bpgbp_client_post_success( $post->ID, $result, $payload );
}

/** Shared success path: stamp the Google name so we never double-post this content, log it, fire the action. */
function bpgbp_client_post_success( int $post_id, array $result, array $payload ) {
	update_post_meta( $post_id, BPGBP_CLIENT_POSTED_META, (string) ( $result['name'] ?? 'posted' ) );
	bpgbp_client_log( $post_id, 'posted', (string) ( $result['searchUrl'] ?? ( $result['name'] ?? 'posted' ) ) );
	do_action( 'bpgbp_client_posted', $post_id, $result, $payload );
}

/* ─────────────────── Jobsite GEO: queue + one-per-day drainer ─────────────────── */

/** Max jobsite-geo GBP posts to release per day. Default 1; filter to raise, or 0 to disable geo posting. */
function bpgbp_client_geo_daily_limit(): int {
	return max( 0, (int) apply_filters( 'bpgbp_client_geo_daily_limit', 1 ) );
}

/**
 * Drain the jobsite-geo queue: post up to the daily limit (oldest first), then stop until tomorrow — so a
 * batch of jobs entered on one day spreads out across the following days. Runs both shortly after a publish
 * (prompt first-of-day post) and once daily (drains the backlog on days with no new jobs). A persistently
 * failing post is retried a few times then dropped, so it can't wedge the queue behind it.
 */
function bpgbp_client_geo_drain() {
	if ( ! bpgbp_client_is_configured() ) return;
	$limit = bpgbp_client_geo_daily_limit();
	if ( $limit < 1 ) return;

	$queue = bpgbp_client_geo_queue();
	if ( empty( $queue ) ) return;

	// Reentrancy guard so overlapping cron runs can't double-post.
	if ( get_transient( 'bpgbp_client_geo_lock' ) ) return;
	set_transient( 'bpgbp_client_geo_lock', 1, 2 * MINUTE_IN_SECONDS );

	$today  = current_time( 'Y-m-d' );
	$errors = 0;

	foreach ( $queue as $post_id ) { // iterate a snapshot; the stored queue is mutated as we go
		if ( bpgbp_client_geo_count_today( $today ) >= $limit ) break; // today's allotment is spent

		$post = get_post( (int) $post_id );

		// Drop stale/invalid entries without spending the day's slot.
		if ( ! $post || 'publish' !== $post->post_status || 'jobsite_geo' !== $post->post_type
			|| get_post_meta( $post_id, BPGBP_CLIENT_POSTED_META, true )
			|| get_post_meta( $post_id, '_bpgbp_skip', true ) ) {
			bpgbp_client_geo_dequeue( (int) $post_id );
			continue;
		}

		$payload = bpgbp_client_build_payload( $post );
		if ( empty( $payload['summary'] ) ) {
			bpgbp_client_log( (int) $post_id, 'skipped', 'No summary could be built for this post.' );
			bpgbp_client_geo_dequeue( (int) $post_id );
			continue;
		}

		// Claim the slot before sending so a concurrent run can't also use it; released again on failure.
		bpgbp_client_geo_set_count( $today, bpgbp_client_geo_count_today( $today ) + 1 );

		$result = bpgbp_client_send( $payload );

		if ( is_wp_error( $result ) ) {
			bpgbp_client_geo_set_count( $today, bpgbp_client_geo_count_today( $today ) - 1 ); // give the slot back
			$attempts = (int) get_post_meta( $post_id, BPGBP_CLIENT_ATTEMPTS_META, true ) + 1;
			update_post_meta( $post_id, BPGBP_CLIENT_ATTEMPTS_META, $attempts );
			bpgbp_client_log( (int) $post_id, 'error', $result->get_error_message() . " (attempt {$attempts})" );
			if ( $attempts >= 5 ) {
				bpgbp_client_geo_dequeue( (int) $post_id ); // give up — don't let it block the rest of the queue
			}
			if ( ++$errors >= 3 ) break; // hub likely down; leave the rest for the next tick
			continue;                     // slot is open again for the next queued job
		}

		bpgbp_client_post_success( (int) $post_id, $result, $payload );
		bpgbp_client_geo_dequeue( (int) $post_id );
	}

	delete_transient( 'bpgbp_client_geo_lock' );
}

/** The pending jobsite-geo queue (post IDs, oldest first). */
function bpgbp_client_geo_queue(): array {
	$q = get_option( BPGBP_CLIENT_QUEUE_OPT, array() );
	if ( ! is_array( $q ) ) return array();
	return array_values( array_unique( array_map( 'intval', $q ) ) );
}

/** Append a jobsite-geo post to the queue (deduped, capped so a runaway can't balloon the option). */
function bpgbp_client_geo_enqueue( int $post_id ) {
	if ( ! $post_id ) return;
	$q = bpgbp_client_geo_queue();
	if ( in_array( $post_id, $q, true ) ) return;

	$max = max( 1, (int) apply_filters( 'bpgbp_client_geo_queue_max', 365 ) ); // ~a year's backlog at one/day
	if ( count( $q ) >= $max ) {
		bpgbp_client_log( $post_id, 'skipped', "GBP jobsite queue is full ({$max}) — not queued." );
		return;
	}
	$q[] = $post_id;
	update_option( BPGBP_CLIENT_QUEUE_OPT, $q, false );
}

/** Remove a post from the queue. */
function bpgbp_client_geo_dequeue( int $post_id ) {
	$q = array_values( array_diff( bpgbp_client_geo_queue(), array( $post_id ) ) );
	update_option( BPGBP_CLIENT_QUEUE_OPT, $q, false );
}

/** How many jobsite-geo posts have gone to GBP today (site-local date). Auto-resets when the day rolls over. */
function bpgbp_client_geo_count_today( string $today ): int {
	$d = get_option( 'bpgbp_client_geo_count' );
	return ( is_array( $d ) && ( $d['date'] ?? '' ) === $today ) ? (int) $d['count'] : 0;
}

/** Record today's jobsite-geo post count (the "N per day" ledger; single site-wide option). */
function bpgbp_client_geo_set_count( string $today, int $count ) {
	update_option( 'bpgbp_client_geo_count', array( 'date' => $today, 'count' => max( 0, $count ) ), false );
}

/* ─────────────────── Jobsite GEO → GBP Photos gallery ─────────────────── */

/** Whether jobsite photos auto-upload to the location's GBP Photos gallery. Filterable; on by default. */
function bpgbp_client_gallery_enabled(): bool {
	return (bool) apply_filters( 'bpgbp_client_gallery_photos', true ) && bpgbp_client_gallery_daily_limit() > 0;
}

/** Safety cap on gallery uploads per site per day (runaway guard for bulk imports). Filterable; default 25. */
function bpgbp_client_gallery_daily_limit(): int {
	return max( 0, (int) apply_filters( 'bpgbp_client_gallery_daily_limit', 25 ) );
}

/**
 * Upload a jobsite's photos (ACF jobsite_photo_1..4) to the location's GBP Photos gallery — distinct from the
 * image that rides along on the local post. Each photo is uploaded once (deduped via the attachment meta
 * `_bpgbp_gallery`), WebP is converted to JPG first, and a per-day cap guards against a bulk-import flood.
 */
function bpgbp_client_upload_geo_photos( $post_id ) {
	if ( ! bpgbp_client_is_configured() || ! bpgbp_client_gallery_enabled() ) return;
	if ( ! function_exists( 'get_field' ) ) return;

	$post = get_post( (int) $post_id );
	if ( ! $post || 'publish' !== $post->post_status || 'jobsite_geo' !== $post->post_type ) return;
	if ( get_post_meta( $post_id, '_bpgbp_skip', true ) ) return;

	// Reentrancy lock so overlapping runs can't double-upload.
	$lock = 'bpgbp_client_photos_' . $post_id;
	if ( get_transient( $lock ) ) return;
	set_transient( $lock, 1, 2 * MINUTE_IN_SECONDS );

	$today    = current_time( 'Y-m-d' );
	$limit    = bpgbp_client_gallery_daily_limit();
	$category = (string) apply_filters( 'bpgbp_client_gallery_category', 'ADDITIONAL', $post );

	for ( $i = 1; $i <= 4; $i++ ) {
		if ( bpgbp_client_gallery_count_today( $today ) >= $limit ) break; // daily cap hit — overflow retries on re-save

		$att = (int) get_field( 'jobsite_photo_' . $i, $post_id );
		if ( ! $att ) continue;
		if ( get_post_meta( $att, '_bpgbp_gallery', true ) ) continue; // already in the gallery

		$url = bpgbp_client_image_url( $att ); // WebP → JPG rendition; '' if not convertible
		if ( '' === $url ) continue;

		// Claim a slot before sending; release it if the upload fails.
		bpgbp_client_gallery_set_count( $today, bpgbp_client_gallery_count_today( $today ) + 1 );
		$result = bpgbp_client_send( array( 'image_url' => $url, 'category' => $category ), 'media' );

		if ( is_wp_error( $result ) ) {
			bpgbp_client_gallery_set_count( $today, bpgbp_client_gallery_count_today( $today ) - 1 );
			error_log( sprintf( 'BPGBP gallery upload failed (post #%d, photo %d): %s', $post_id, $i, $result->get_error_message() ) );
			break; // likely a hub/Google issue — stop; unmarked photos retry on the next save/publish
		}

		update_post_meta( $att, '_bpgbp_gallery', (string) ( $result['name'] ?? 'uploaded' ) );
	}

	delete_transient( $lock );
}

/** How many gallery photos have uploaded today (site-local date). Auto-resets when the day rolls over. */
function bpgbp_client_gallery_count_today( string $today ): int {
	$d = get_option( 'bpgbp_client_gallery_count' );
	return ( is_array( $d ) && ( $d['date'] ?? '' ) === $today ) ? (int) $d['count'] : 0;
}

/** Record today's gallery upload count (single site-wide option). */
function bpgbp_client_gallery_set_count( string $today, int $count ) {
	update_option( 'bpgbp_client_gallery_count', array( 'date' => $today, 'count' => max( 0, $count ) ), false );
}

/** Dispatch to the right builder by post type. Returns a payload array (may be empty → skipped). */
function bpgbp_client_build_payload( WP_Post $post ): array {
	$payload = ( 'jobsite_geo' === $post->post_type )
		? bpgbp_client_build_geo_payload( $post )
		: bpgbp_client_build_blog_payload( $post );

	// Common defaults + a per-site escape hatch to fully customize the local post.
	$payload = wp_parse_args( $payload, array(
		'topic_type'    => 'STANDARD',
		'language_code' => 'en-US',
		'cta_type'      => 'LEARN_MORE',
	) );
	return (array) apply_filters( 'bpgbp_client_payload', $payload, $post );
}

/** Blog post → STANDARD local post: excerpt (or trimmed body) as summary, LEARN_MORE → permalink, featured image. */
function bpgbp_client_build_blog_payload( WP_Post $post ): array {
	// Prefer a hand-written excerpt; fall back to the body copy.
	$raw = has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content;
	$summary = bpgbp_client_text_summary( $raw, BPGBP_CLIENT_SUMMARY_LIMIT );

	$payload = array(
		'summary' => $summary,
		'cta_url' => get_permalink( $post ),
	);

	$img = bpgbp_client_image_url( get_post_thumbnail_id( $post ) );
	if ( $img ) $payload['image_url'] = $img;

	return $payload;
}

/**
 * Jobsite GEO post → STANDARD local post built from the job's ACF fields: "{Service} in {City}, {ST}."
 * followed by the job description, LEARN_MORE → the jobsite permalink, first jobsite photo as the image.
 */
function bpgbp_client_build_geo_payload( WP_Post $post ): array {
	$city  = function_exists( 'get_field' ) ? trim( (string) get_field( 'city', $post->ID ) )  : '';
	$state = function_exists( 'get_field' ) ? trim( (string) get_field( 'state', $post->ID ) ) : '';

	// Service label: the primary jobsite_geo-services term, else the site's default_service option.
	$service = '';
	$terms   = get_the_terms( $post->ID, 'jobsite_geo-services' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$service = (string) $terms[0]->name;
	}
	if ( '' === $service ) {
		$opt     = get_option( 'jobsite_geo' );
		$service = is_array( $opt ) && ! empty( $opt['default_service'] ) ? (string) $opt['default_service'] : '';
	}

	// Headline line: "AC Repair in Allen, TX." — omit any piece that's missing.
	$place = trim( $city . ( $city && $state ? ', ' : '' ) . $state );
	$lead  = trim( $service . ( $service && $place ? ' in ' : '' ) . $place );
	if ( '' !== $lead ) $lead = rtrim( $lead, '.' ) . '.';

	$body    = bpgbp_client_text_summary( $post->post_content, BPGBP_CLIENT_SUMMARY_LIMIT - ( strlen( $lead ) + 2 ) );
	$summary = trim( $lead . ( $lead && $body ? "\n\n" : '' ) . $body );
	$summary = bpgbp_client_text_summary( $summary, BPGBP_CLIENT_SUMMARY_LIMIT ); // final safety clamp

	$payload = array(
		'summary' => $summary,
		'cta_url' => get_permalink( $post ),
	);

	// First jobsite photo (ACF returns an attachment ID), else the featured image.
	$photo_id = function_exists( 'get_field' ) ? (int) get_field( 'jobsite_photo_1', $post->ID ) : 0;
	if ( ! $photo_id ) $photo_id = (int) get_post_thumbnail_id( $post );
	$img = bpgbp_client_image_url( $photo_id );
	if ( $img ) $payload['image_url'] = $img;

	return $payload;
}

/**
 * Public image URL for Google to fetch, guaranteed to be JPG/PNG. GBP local-post media rejects WebP (which
 * this framework uploads for everything), so a jpg/png is passed through as-is and anything else is served
 * as a one-time JPG rendition. Returns '' if none / not convertible — the caller then posts text-only.
 */
function bpgbp_client_image_url( $attachment_id ): string {
	$attachment_id = (int) $attachment_id;
	if ( ! $attachment_id ) return '';

	$src = wp_get_attachment_image_url( $attachment_id, 'large' );
	if ( ! $src ) $src = wp_get_attachment_url( $attachment_id );
	if ( ! $src ) return '';

	$ext = strtolower( (string) pathinfo( (string) wp_parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	if ( in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) return (string) $src;

	return bpgbp_client_jpeg_rendition( $attachment_id ); // '' → image skipped, post still goes out
}

/**
 * Build (once, cached) a JPG copy of an attachment for GBP, since Google rejects WebP post media. Returns the
 * public URL of the JPG, or '' if the image editor isn't available / conversion fails (caller skips the image).
 */
function bpgbp_client_jpeg_rendition( int $attachment_id ): string {
	$cached = (string) get_post_meta( $attachment_id, '_bpgbp_gbp_jpg', true );
	if ( '' !== $cached && file_exists( $cached ) ) return bpgbp_client_path_to_url( $cached );

	$file = get_attached_file( $attachment_id ); // the original (WebP) file
	if ( ! $file || ! file_exists( $file ) ) return '';

	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) return '';
	$editor->resize( 1200, 1200, false ); // fit within 1200px, no upscale (GBP wants ≥250×250, ~720×540 ideal)
	$target = preg_replace( '/\.[^.]+$/', '', $file ) . '-gbp.jpg';
	$saved  = $editor->save( $target, 'image/jpeg' );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) return '';

	update_post_meta( $attachment_id, '_bpgbp_gbp_jpg', $saved['path'] );
	return bpgbp_client_path_to_url( $saved['path'] );
}

/** Map an absolute uploads path to its public URL, or '' if it isn't under the uploads dir. */
function bpgbp_client_path_to_url( string $path ): string {
	$up = wp_get_upload_dir();
	return ( 0 === strpos( $path, $up['basedir'] ) )
		? $up['baseurl'] . substr( $path, strlen( $up['basedir'] ) )
		: '';
}

/**
 * Turn post content/excerpt into a clean plain-text summary: run shortcodes' output away, strip tags,
 * collapse whitespace, then truncate at a word boundary with an ellipsis. Never exceeds $limit.
 */
function bpgbp_client_text_summary( $raw, int $limit ): string {
	$text = (string) $raw;
	$text = strip_shortcodes( $text );
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

	if ( $limit < 1 ) return '';
	if ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) <= $limit : strlen( $text ) <= $limit ) {
		return $text;
	}

	// Cut to the limit (leaving room for the ellipsis), then back up to the last whole word.
	$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit - 1 ) : substr( $text, 0, $limit - 1 );
	$sp  = function_exists( 'mb_strrpos' ) ? mb_strrpos( $cut, ' ' ) : strrpos( $cut, ' ' );
	if ( false !== $sp && $sp > $limit * 0.5 ) {
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $cut, 0, $sp ) : substr( $cut, 0, $sp );
	}
	return rtrim( $cut, " \t\n\r\0\x0B.,;:" ) . '…';
}

/**
 * Send a signed POST to the hub's bpgbp/v1/post route. Mirrors sp_reviews_hub_request()'s auth exactly
 * (HMAC of timestamp . '.' . rawBody with SITE_SECRET) but stands alone so posting works on plain client
 * sites that don't load the reviews module. Returns the decoded response array, or a WP_Error.
 */
function bpgbp_client_send( array $payload, string $path = 'post' ) {
	$hub_url     = bpgbp_cfg( 'HUB_URL' );
	$site_key    = bpgbp_cfg( 'SITE_KEY' );
	$site_secret = bpgbp_cfg( 'SITE_SECRET' );
	if ( '' === $hub_url || '' === $site_key || '' === $site_secret ) {
		return new WP_Error( 'bpgbp_client_unconfigured', 'This site is not paired with a GBP hub.' );
	}

	// Use ?rest_route= (not the pretty /wp-json/ path): on the hub the pretty REST URL is 301-redirected,
	// so a client following it would receive HTML instead of JSON. index.php handles ?rest_route= directly.
	$url       = rtrim( $hub_url, '/' ) . '/?rest_route=/bpgbp/v1/' . ltrim( $path, '/' );
	$timestamp = (string) time();
	$raw       = wp_json_encode( $payload );
	$signature = hash_hmac( 'sha256', $timestamp . '.' . $raw, $site_secret );
	$url      .= '&_cb=' . rawurlencode( $timestamp . (string) wp_rand( 1000, 9999 ) ); // edge-cache buster

	$response = wp_remote_post( $url, array(
		'timeout' => 25,
		'headers' => array(
			'X-BPGBP-Site'      => $site_key,
			'X-BPGBP-Timestamp' => $timestamp,
			'X-BPGBP-Signature' => $signature,
			'Content-Type'      => 'application/json',
			'Cache-Control'     => 'no-cache',
		),
		'body'    => $raw,
	) );
	if ( is_wp_error( $response ) ) return $response;

	$code = wp_remote_retrieve_response_code( $response );
	$raw  = wp_remote_retrieve_body( $response );
	$data = json_decode( $raw, true );

	if ( $code < 200 || $code >= 300 ) {
		$msg = ( is_array( $data ) && isset( $data['message'] ) ) ? $data['message'] : '';
		if ( '' === $msg ) { // non-JSON (e.g. a host/CDN error page) — include a snippet so it isn't opaque
			$snippet = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $raw ) ) );
			$msg     = 'Hub returned ' . $code . ( '' !== $snippet ? ': ' . mb_substr( $snippet, 0, 300 ) : '' );
		}
		return new WP_Error( 'bpgbp_client_hub', $msg, array( 'status' => $code ) );
	}

	// The hub returns HTTP 200 with { ok:false, error } for a Google-side failure (so the real message
	// survives the CDN) — surface it as an error rather than treating the 200 as a successful post.
	if ( is_array( $data ) && isset( $data['ok'] ) && ! $data['ok'] ) {
		$msg = ! empty( $data['error'] ) ? (string) $data['error'] : 'Hub reported a Google error.';
		return new WP_Error( 'bpgbp_client_hub', $msg, array( 'status' => 502 ) );
	}

	return is_array( $data ) ? $data : array();
}

/** Record the last attempt on the post (for at-a-glance debugging) and mirror errors to the PHP log. */
function bpgbp_client_log( int $post_id, string $status, string $detail ) {
	update_post_meta( $post_id, BPGBP_CLIENT_LOG_META, array(
		'status' => $status,
		'detail' => $detail,
		'time'   => current_time( 'mysql' ),
	) );
	if ( 'error' === $status ) {
		error_log( sprintf( 'BPGBP client post #%d failed: %s', $post_id, $detail ) );
	}
}

/* ─────────────────── Publish-box status line (under the date) ─────────────────── */

/**
 * Compute this post's GBP state for the Publish-box line. Returns [ icon, note, done, can_send ]:
 *  - `done`     : false only for transient states that resolve within ~a minute (blog mid-post, jobsite due
 *                 today) — the signal to keep polling the line live.
 *  - `can_send` : true when a manual "Post now" makes sense (published, supported, not yet posted, not
 *                 skipped) — i.e. pending or failed. Drives the "Post now" link + hides it once terminal.
 */
function bpgbp_client_status_parts( WP_Post $post ): array {
	$is_geo = ( 'jobsite_geo' === $post->post_type );
	$posted = get_post_meta( $post->ID, BPGBP_CLIENT_POSTED_META, true );
	$log    = get_post_meta( $post->ID, BPGBP_CLIENT_LOG_META, true );
	$log    = is_array( $log ) ? $log : array();
	$status = isset( $log['status'] ) ? $log['status'] : '';

	$icon     = 'location-alt';
	$note     = '';
	$done     = true;
	$can_send = false;

	if ( $posted ) {
		$when = ! empty( $log['time'] ) ? $log['time'] : $post->post_date;
		$icon = 'yes-alt';
		$note = sprintf( 'Posted to GBP on %s', date_i18n( get_option( 'date_format' ), strtotime( $when ) ) );

	} elseif ( get_post_meta( $post->ID, '_bpgbp_skip', true ) ) {
		$icon = 'dismiss';
		$note = 'GBP auto-post is turned off for this post.';

	} elseif ( 'publish' === $post->post_status ) {
		if ( 'error' === $status ) {
			$icon     = 'warning';
			$note     = ( $is_geo ? 'GBP post failed — will retry.' : 'GBP post failed.' )
				. ( ! empty( $log['detail'] ) ? ' (' . $log['detail'] . ')' : '' );
			$can_send = true;
		} elseif ( 'skipped' === $status ) {
			$icon = 'minus';
			$note = 'Not posted to GBP' . ( ! empty( $log['detail'] ) ? ' — ' . $log['detail'] : '.' );
		} elseif ( $is_geo ) {
			$idx = array_search( (int) $post->ID, bpgbp_client_geo_queue(), true );
			if ( false !== $idx ) {
				$icon            = 'clock';
				$remaining_today = max( 0, bpgbp_client_geo_daily_limit() - bpgbp_client_geo_count_today( current_time( 'Y-m-d' ) ) );
				$note            = sprintf( 'Scheduled for GBP — %s', bpgbp_client_geo_eta_label( (int) $idx ) );
				$done            = ! ( $idx < $remaining_today ); // poll only if it's due to post today
				$can_send        = true;
			} else {
				$icon     = 'clock';
				$note     = 'Queued for GBP.';
				$done     = false;
				$can_send = true;
			}
		} else { // blog: the background send is in flight
			$icon     = 'clock';
			$note     = 'Posting to GBP…';
			$done     = false;
			$can_send = true;
		}

	} else { // draft / pending / scheduled — will act when it goes live
		$note = $is_geo ? 'Will be scheduled for GBP when published.' : 'Will auto-post to GBP on publish.';
	}

	return array( 'icon' => $icon, 'note' => $note, 'done' => $done, 'can_send' => $can_send );
}

/** Markup for the status line's inner content (icon + text) — shared by the initial render and AJAX. */
function bpgbp_client_status_html( array $parts ): string {
	return '<span class="dashicons dashicons-' . esc_attr( $parts['icon'] ) . '" style="color:#787c82;font-size:18px"></span>'
		. '<span>' . esc_html( $parts['note'] ) . '</span>';
}

/**
 * The Publish-box line, right below the "Published on" date. Shows the GBP state, offers a "Post now" link
 * while a send is pending/failed (runs immediately, bypassing wp-cron), and live-polls so a pending post
 * resolves on-screen without a reload. Classic-editor only (the block editor's Publish panel ignores this hook).
 */
function bpgbp_client_submitbox_status() {
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) ) return;
	if ( ! in_array( $post->post_type, bpgbp_client_post_types(), true ) ) return;

	$parts = bpgbp_client_status_parts( $post );

	echo '<div class="misc-pub-section bpgbp-pub-status" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">'
		. '<span id="bpgbp-pub-status" style="display:inline-flex;align-items:center;gap:4px">'
		. bpgbp_client_status_html( $parts ) . '</span>'
		. ( $parts['can_send'] ? ' <a href="#" id="bpgbp-post-now" style="text-decoration:none">Post now</a>' : '' )
		. '</div>';

	if ( $parts['done'] && ! $parts['can_send'] ) return; // nothing to poll or trigger

	$n_status = wp_create_nonce( 'bpgbp_client_status_' . $post->ID );
	$n_now    = wp_create_nonce( 'bpgbp_client_post_now_' . $post->ID );
	$pid      = (int) $post->ID;
	$pending  = $parts['done'] ? 'false' : 'true';
	?>
	<script>
	( function () {
		var el   = document.getElementById( 'bpgbp-pub-status' );
		var link = document.getElementById( 'bpgbp-post-now' );
		if ( ! el || typeof ajaxurl === 'undefined' ) return;

		function req( action, nonce ) {
			return fetch( ajaxurl, { method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=' + action + '&post=<?php echo $pid; ?>&nonce=' + nonce
			} ).then( function ( r ) { return r.json(); } );
		}
		function apply( d ) {
			if ( ! d ) return;
			el.innerHTML = d.html;
			if ( link && d.can_send === false ) { link.remove(); link = null; }
		}

		if ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				link.textContent = 'Sending…'; link.style.pointerEvents = 'none';
				req( 'bpgbp_client_post_now', '<?php echo esc_js( $n_now ); ?>' )
					.then( function ( j ) { if ( j && j.success ) apply( j.data ); } )
					.catch( function () {} )
					.then( function () { if ( link ) { link.textContent = 'Post now'; link.style.pointerEvents = ''; } } );
			} );
		}

		var tries = 0, pending = <?php echo $pending; ?>;
		function poll() {
			if ( ! pending || ++tries > 40 ) return; // ~8 min cap
			req( 'bpgbp_client_status', '<?php echo esc_js( $n_status ); ?>' )
				.then( function ( j ) { if ( j && j.success ) { apply( j.data ); if ( j.data.done ) pending = false; } } )
				.catch( function () {} )
				.then( function () { if ( pending ) setTimeout( poll, 12000 ); } );
		}
		if ( pending ) setTimeout( poll, 12000 );
	} )();
	</script>
	<?php
}

/** admin-ajax (read-only): current status line for one post, so the poller can refresh it. */
function bpgbp_client_ajax_status() {
	$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error();
	check_ajax_referer( 'bpgbp_client_status_' . $post_id, 'nonce' );

	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) ) wp_send_json_error();

	$parts = bpgbp_client_status_parts( $post );
	wp_send_json_success( array(
		'done'     => (bool) $parts['done'],
		'can_send' => (bool) $parts['can_send'],
		'html'     => bpgbp_client_status_html( $parts ),
	) );
}

/** admin-ajax: run the send for one post right now (bypasses wp-cron), then return the refreshed line. */
function bpgbp_client_ajax_post_now() {
	$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error();
	check_ajax_referer( 'bpgbp_client_post_now_' . $post_id, 'nonce' );

	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) ) wp_send_json_error();

	if ( 'publish' === $post->post_status
		&& ! get_post_meta( $post_id, BPGBP_CLIENT_POSTED_META, true )
		&& ! get_post_meta( $post_id, '_bpgbp_skip', true ) ) {
		if ( 'jobsite_geo' === $post->post_type ) {
			bpgbp_client_geo_enqueue( $post_id );
			bpgbp_client_geo_drain();
		} else {
			bpgbp_client_run( $post_id );
		}
		$post = get_post( $post_id ); // refresh state after the attempt
	}

	$parts = bpgbp_client_status_parts( $post );
	wp_send_json_success( array(
		'done'     => (bool) $parts['done'],
		'can_send' => (bool) $parts['can_send'],
		'html'     => bpgbp_client_status_html( $parts ),
	) );
}

/**
 * Friendly ETA for a jobsite-geo post sitting at queue index $idx, given the per-day limit and how much of
 * today's allotment is already spent: "today", or an approximate calendar date a few days out.
 */
function bpgbp_client_geo_eta_label( int $idx ): string {
	$limit = bpgbp_client_geo_daily_limit();
	if ( $limit < 1 ) return 'on hold';

	$today           = current_time( 'Y-m-d' );
	$remaining_today = max( 0, $limit - bpgbp_client_geo_count_today( $today ) );

	if ( $idx < $remaining_today ) return 'today';

	$days = 1 + intdiv( $idx - $remaining_today, $limit ); // whole days ahead of today
	return '~' . date_i18n( get_option( 'date_format' ), strtotime( $today . ' +' . $days . ' day' ) );
}
