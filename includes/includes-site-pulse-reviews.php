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
function sp_reviews_hub_request( string $method, string $path, ?array $body = null ) {
	foreach ( [ 'BPGBP_HUB_URL', 'BPGBP_SITE_KEY', 'BPGBP_SITE_SECRET' ] as $const ) {
		if ( ! defined( $const ) || ! constant( $const ) ) {
			return new WP_Error( 'sp_reviews_unconfigured', 'Google Reviews are not configured for this site (missing ' . $const . ' in wp-config.php).' );
		}
	}

	$url       = rtrim( BPGBP_HUB_URL, '/' ) . '/wp-json/bpgbp/v1/' . ltrim( $path, '/' );
	$timestamp = (string) time();
	$raw       = ( null === $body ) ? '' : wp_json_encode( $body );
	$signature = hash_hmac( 'sha256', $timestamp . '.' . $raw, BPGBP_SITE_SECRET );

	$args = [
		'method'  => $method,
		'timeout' => 25,
		'headers' => [
			'X-BPGBP-Site'      => BPGBP_SITE_KEY,
			'X-BPGBP-Timestamp' => $timestamp,
			'X-BPGBP-Signature' => $signature,
			'Content-Type'      => 'application/json',
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
# Cache — reviews change rarely; cache in the SP config table
--------------------------------------------------------------*/

const SP_REVIEWS_CACHE_KEY = 'reviews_cache';
const SP_REVIEWS_TTL       = 3600; // serve cache up to an hour old before re-fetching

function sp_reviews_get_cached(): array {
	$raw = site_pulse_get_setting( SP_REVIEWS_CACHE_KEY, '' );
	$d   = $raw ? json_decode( $raw, true ) : null;
	return is_array( $d ) ? $d : [];
}

function sp_reviews_set_cached( array $payload ): void {
	site_pulse_set_setting( SP_REVIEWS_CACHE_KEY, wp_json_encode( $payload ) );
}

/** Pull the full review set from the hub and refresh the cache. Returns the payload or WP_Error. */
function sp_reviews_refresh() {
	$res = sp_reviews_hub_request( 'GET', 'reviews' );
	if ( is_wp_error( $res ) ) return $res;

	$payload = [
		'fetched_at'       => time(),
		'averageRating'    => $res['averageRating']    ?? null,
		'totalReviewCount' => $res['totalReviewCount'] ?? null,
		'reviews'          => array_values( $res['reviews'] ?? [] ),
	];
	sp_reviews_set_cached( $payload );
	return $payload;
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

	$can_view   = site_pulse_user_can( $user_id, 'view_reviews' ) || site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() );
	if ( ! $can_view ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	$force = ! empty( $_POST['refresh'] );
	$cache = sp_reviews_get_cached();
	$stale = empty( $cache['fetched_at'] ) || ( time() - (int) $cache['fetched_at'] > SP_REVIEWS_TTL );

	if ( $force || $stale ) {
		$fresh = sp_reviews_refresh();
		if ( is_wp_error( $fresh ) ) {
			// Fall back to stale cache if we have one, but surface the error so the UI can flag it.
			if ( ! empty( $cache['reviews'] ) ) {
				$cache['stale'] = true;
				$cache['error'] = $fresh->get_error_message();
			} else {
				wp_send_json_error( [ 'message' => $fresh->get_error_message() ] );
			}
		} else {
			$cache = $fresh;
		}
	}

	// Annotate each review with whether it's already a testimonial.
	$imported = sp_reviews_imported_ids();
	$reviews  = [];
	foreach ( ( $cache['reviews'] ?? [] ) as $r ) {
		$r['imported'] = in_array( (string) ( $r['reviewId'] ?? '' ), $imported, true );
		$reviews[]     = $r;
	}

	wp_send_json_success( [
		'reviews'          => $reviews,
		'averageRating'    => $cache['averageRating']    ?? null,
		'totalReviewCount' => $cache['totalReviewCount'] ?? null,
		'fetched_at'       => $cache['fetched_at']       ?? null,
		'stale'            => ! empty( $cache['stale'] ),
		'error'            => $cache['error']            ?? null,
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

	// Empty comment = delete the existing reply (hub interprets it that way).
	$res = sp_reviews_hub_request( 'POST', 'reply', [ 'review_id' => $review_id, 'comment' => $comment ] );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );

	$reply = ( '' === $comment ) ? null : [ 'comment' => $comment, 'updateTime' => ( $res['updateTime'] ?? '' ) ];

	// Patch the cache so the panel reflects the reply without a full re-fetch.
	$cache = sp_reviews_get_cached();
	if ( ! empty( $cache['reviews'] ) ) {
		foreach ( $cache['reviews'] as &$r ) {
			if ( (string) ( $r['reviewId'] ?? '' ) === $review_id ) { $r['reply'] = $reply; break; }
		}
		unset( $r );
		sp_reviews_set_cached( $cache );
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

	// Pull the content from our cache — never trust client-posted review text.
	$review = null;
	foreach ( ( sp_reviews_get_cached()['reviews'] ?? [] ) as $r ) {
		if ( (string) ( $r['reviewId'] ?? '' ) === $review_id ) { $review = $r; break; }
	}
	if ( ! $review ) wp_send_json_error( [ 'message' => 'Review not found — try refreshing the list.' ] );

	// One testimonial per Google review.
	$dupe = get_posts( [
		'post_type'   => 'testimonials',
		'post_status' => 'any',
		'meta_key'    => '_bp_google_review_id',
		'meta_value'  => $review_id,
		'fields'      => 'ids',
		'numberposts' => 1,
	] );
	if ( $dupe ) wp_send_json_error( [ 'message' => 'This review is already imported as a testimonial.' ] );

	$post_id = wp_insert_post( [
		'post_type'    => 'testimonials',
		'post_status'  => 'draft', // land as a draft so it can be reviewed before publishing
		'post_title'   => $review['reviewer'] ?: 'Google Reviewer',
		'post_content' => $review['comment'] ?? '',
	], true );
	if ( is_wp_error( $post_id ) ) wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );

	$rating = (int) ( $review['starRating'] ?? 0 );
	if ( function_exists( 'update_field' ) ) {
		update_field( 'testimonial_rating', $rating, $post_id );
		update_field( 'testimonial_platform', 'Google', $post_id );
	} else {
		update_post_meta( $post_id, 'testimonial_rating', $rating );
		update_post_meta( $post_id, 'testimonial_platform', 'Google' );
	}
	update_post_meta( $post_id, '_bp_google_review_id', $review_id );

	site_pulse_log( 'review_to_testimonial', 'Created testimonial from a Google review', [ 'review_id' => $review_id, 'post_id' => $post_id ] );

	wp_send_json_success( [
		'post_id'  => $post_id,
		'edit_url' => get_edit_post_link( $post_id, 'raw' ),
	] );
}
