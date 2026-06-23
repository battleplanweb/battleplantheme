<?php
/* Battle Plan Web Design — Site Pulse: Agency Reviews (hub-only "Client Reviews" view)

/*--------------------------------------------------------------
The agency dashboard that runs ON the hub (bp-webdev.com): every mapped client's Google
reviews in one place, with one-click reply and one-click push of a review to that client's
own site as a Testimonial CPT.

The hub already holds the token and pulls per-location reviews for client sites over HMAC.
This file lets the hub's OWN dashboard read/reply for ANY mapped location in-process (it
trusts itself — no HMAC round-trip to localhost) via BPGBP_Hub's public methods, and push a
testimonial OUT to a client by signing with that client's secret from bpgbp-sites.json
(reverse of the normal client→hub direction; the client verifies with its BPGBP_SITE_SECRET).

Everything here is agency-only: gated on manage_reviews / god, and a no-op unless this site is
the hub. bpgbp-sites.json entries gain one optional field — "site_url" — the push target.
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

const SP_AGENCY_REVIEWS_CACHE_KEY = 'agency_reviews_cache';
const SP_AGENCY_REVIEWS_TTL       = 3600; // per-location cache, same hour-long window as the client module
const SP_AGENCY_DISMISSED_KEY     = 'agency_reviews_dismissed'; // handled reviews removed from the list, kept out on every re-pull

/** This install is the hub (holds the token) and the hub class exposes the in-process API. */
function sp_agency_is_hub(): bool {
	return defined( 'BPGBP_REFRESH_TOKEN' ) && BPGBP_REFRESH_TOKEN
		&& class_exists( 'BPGBP_Hub' ) && method_exists( 'BPGBP_Hub', 'fetch_reviews_for_location' );
}

function sp_agency_can_manage(): bool {
	$user_id = site_pulse_effective_user_id();
	return site_pulse_user_can( $user_id, 'manage_reviews' ) || site_pulse_is_god( get_current_user_id() );
}

/* per-location cache lives in the SP config table, keyed by the Google location string */
function sp_agency_cache(): array {
	$raw = site_pulse_get_setting( SP_AGENCY_REVIEWS_CACHE_KEY, '' );
	$d   = $raw ? json_decode( $raw, true ) : null;
	return is_array( $d ) ? $d : [];
}
function sp_agency_cache_save( array $c ): void {
	site_pulse_set_setting( SP_AGENCY_REVIEWS_CACHE_KEY, wp_json_encode( $c ) );
}

/* Dismissed (handled) reviews, kept out of the list permanently: { location => { reviewId: 1 } }. */
function sp_agency_dismissed(): array {
	$raw = site_pulse_get_setting( SP_AGENCY_DISMISSED_KEY, '' );
	$d   = $raw ? json_decode( $raw, true ) : null;
	return is_array( $d ) ? $d : [];
}
function sp_agency_dismissed_save( array $d ): void {
	site_pulse_set_setting( SP_AGENCY_DISMISSED_KEY, wp_json_encode( $d ) );
}

/**
 * The earliest review date the agency dashboard pulls/shows. Older reviews are never fetched (keeps the
 * pull fast) — you work forward from here, removing each as you handle it. Returns a unix timestamp;
 * filter `sp_agency_reviews_since` (a 'Y-m-d' date) to change the start.
 */
function sp_agency_reviews_since(): int {
	$date = (string) apply_filters( 'sp_agency_reviews_since', '2026-05-29' );
	$ts   = strtotime( $date . ' 00:00:00' );
	return $ts ?: 0;
}

/** Find one review by id within a location's review list, or null. */
function sp_agency_find_review( array $reviews, string $review_id ) {
	foreach ( $reviews as $r ) {
		if ( (string) ( $r['reviewId'] ?? '' ) === $review_id ) return $r;
	}
	return null;
}


/*--------------------------------------------------------------
# AJAX — all clients' reviews, grouped by client
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_agency_reviews', 'site_pulse_ajax_get_agency_reviews' );
function site_pulse_ajax_get_agency_reviews(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_agency_can_manage() ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	if ( ! sp_agency_is_hub() )     wp_send_json_error( [ 'message' => 'This site is not the review hub.' ] );

	$force     = ! empty( $_POST['refresh'] );
	$sites     = BPGBP_Hub::get_site_map();
	$cache     = sp_agency_cache();
	$dismissed = sp_agency_dismissed();
	$now       = time();
	$out       = [];

	foreach ( $sites as $site_key => $cfg ) {
		// Only businesses YOU manage show on the agency dashboard. Entries without "agency": true
		// are client-managed — they appear on that client's OWN Site Pulse dashboard, not here.
		if ( empty( $cfg['agency'] ) ) continue;

		$location = $cfg['location'] ?? '';
		$entry    = [
			'site_key'         => (string) $site_key,
			'label'            => (string) ( $cfg['label'] ?? $site_key ),
			'site_url'         => ! empty( $cfg['site_url'] ) ? (string) $cfg['site_url'] : '',
			'averageRating'    => null,
			'totalReviewCount' => null,
			'reviews'          => [],
			'error'            => null,
		];

		if ( '' === $location ) { $entry['error'] = 'No Google location set for this client — add it in Tools → GBP Clients.'; $out[] = $entry; continue; }

		$cached = $cache[ $location ] ?? null;
		$stale  = empty( $cached['fetched_at'] ) || ( $now - (int) $cached['fetched_at'] > SP_AGENCY_REVIEWS_TTL );

		if ( $force || $stale ) {
			try {
				$data   = BPGBP_Hub::fetch_reviews_for_location( $location, '', 200, sp_agency_reviews_since() );
				$cached = [
					'fetched_at'       => $now,
					'averageRating'    => $data['averageRating'],
					'totalReviewCount' => $data['totalReviewCount'],
					'reviews'          => array_values( $data['reviews'] ),
				];
				$cache[ $location ] = $cached;
			} catch ( Exception $e ) {
				// No usable cache → surface the error for this client; otherwise show saved reviews + flag.
				if ( empty( $cached['reviews'] ) ) { $entry['error'] = $e->getMessage(); $out[] = $entry; continue; }
				$entry['error'] = 'Showing saved reviews — ' . $e->getMessage();
			}
		}

		$entry['averageRating']    = $cached['averageRating']    ?? null;
		$entry['totalReviewCount'] = $cached['totalReviewCount'] ?? null;
		$reviews                   = $cached['reviews']          ?? [];

		// Drop reviews the agency has already handled + removed — kept out on every pull, including re-fetches.
		$dis = $dismissed[ $location ] ?? [];
		if ( $dis && $reviews ) {
			$reviews = array_values( array_filter( $reviews, function ( $r ) use ( $dis ) {
				return empty( $dis[ (string) ( $r['reviewId'] ?? '' ) ] );
			} ) );
		}

		$entry['reviews'] = $reviews;
		$out[] = $entry;
	}

	sp_agency_cache_save( $cache );
	wp_send_json_success( [ 'clients' => $out ] );
}


/*--------------------------------------------------------------
# AJAX — dismiss (remove) a handled review from the list
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# AJAX — AI-draft a reply for a client's review (brand voice via filters)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_agency_generate_reply', 'site_pulse_ajax_agency_generate_reply' );
function site_pulse_ajax_agency_generate_reply(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_agency_can_manage() ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	if ( ! sp_agency_is_hub() )     wp_send_json_error( [ 'message' => 'This site is not the review hub.' ] );

	$site_key  = sanitize_text_field( wp_unslash( $_POST['site_key'] ?? '' ) );
	$review_id = sanitize_text_field( wp_unslash( $_POST['review_id'] ?? '' ) );
	if ( '' === $site_key ) wp_send_json_error( [ 'message' => 'Missing client.' ] );

	$sites = BPGBP_Hub::get_site_map();
	$cfg   = $sites[ $site_key ] ?? null;
	if ( ! $cfg || empty( $cfg['agency'] ) ) wp_send_json_error( [ 'message' => 'Not an agency-managed client.' ] );

	if ( ! function_exists( 'sp_reviews_generate_reply' ) ) wp_send_json_error( [ 'message' => 'Reply generator unavailable.' ] );

	// Prefer the authoritative cached review; if it isn't there (stale cache, location changed, or the
	// review predates the fetch cutoff), fall back to what the dashboard already shows. This is only a
	// DRAFT the user edits before posting — it isn't published as-is — so trusting the displayed text here
	// is fine, and it means hitting Reply never errors out.
	$location = $cfg['location'] ?? '';
	$cache    = sp_agency_cache();
	$review   = sp_agency_find_review( $cache[ $location ]['reviews'] ?? [], $review_id );

	if ( $review ) {
		$reviewer = (string) ( $review['reviewer'] ?? '' );
		$comment  = (string) ( $review['comment'] ?? '' );
		$rating   = (int) ( $review['starRating'] ?? 0 );
	} else {
		$reviewer = sanitize_text_field( wp_unslash( $_POST['reviewer'] ?? '' ) );
		$comment  = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
		$rating   = (int) ( $_POST['star_rating'] ?? 0 );
	}

	// Reuse the generic drafter; the client's business name becomes the "brand" so the prompt addresses it.
	$label = (string) ( $cfg['label'] ?? $site_key );
	$row   = [
		'reviewer'    => $reviewer,
		'comment'     => $comment,
		'star_rating' => $rating,
		'brand'       => $label,
		'store'       => $label,
	];

	$reply = sp_reviews_generate_reply( $row );
	if ( is_wp_error( $reply ) ) wp_send_json_error( [ 'message' => $reply->get_error_message() ] );

	wp_send_json_success( [ 'reply' => $reply ] );
}


add_action( 'wp_ajax_site_pulse_agency_dismiss_review', 'site_pulse_ajax_agency_dismiss_review' );
function site_pulse_ajax_agency_dismiss_review(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_agency_can_manage() ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	if ( ! sp_agency_is_hub() )     wp_send_json_error( [ 'message' => 'This site is not the review hub.' ] );

	$site_key  = sanitize_text_field( wp_unslash( $_POST['site_key'] ?? '' ) );
	$review_id = sanitize_text_field( wp_unslash( $_POST['review_id'] ?? '' ) );
	if ( '' === $site_key || '' === $review_id ) wp_send_json_error( [ 'message' => 'Missing review.' ] );

	$sites    = BPGBP_Hub::get_site_map();
	$cfg      = $sites[ $site_key ] ?? null;
	if ( ! $cfg || empty( $cfg['agency'] ) ) wp_send_json_error( [ 'message' => 'Not an agency-managed client.' ] );
	$location = $cfg['location'] ?? '';
	if ( '' === $location ) wp_send_json_error( [ 'message' => 'Unknown client.' ] );

	// Record it as dismissed so it stays out of the list on every future pull.
	$dismissed = sp_agency_dismissed();
	if ( ! isset( $dismissed[ $location ] ) || ! is_array( $dismissed[ $location ] ) ) $dismissed[ $location ] = [];
	$dismissed[ $location ][ $review_id ] = 1;
	sp_agency_dismissed_save( $dismissed );

	// Also drop it from the live cache now, so it's gone immediately without waiting for a re-fetch.
	$cache = sp_agency_cache();
	if ( ! empty( $cache[ $location ]['reviews'] ) ) {
		$cache[ $location ]['reviews'] = array_values( array_filter( $cache[ $location ]['reviews'], function ( $r ) use ( $review_id ) {
			return (string) ( $r['reviewId'] ?? '' ) !== $review_id;
		} ) );
		sp_agency_cache_save( $cache );
	}

	wp_send_json_success( [ 'review_id' => $review_id ] );
}


/*--------------------------------------------------------------
# AJAX — reply to any client's review
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_agency_reply_review', 'site_pulse_ajax_agency_reply_review' );
function site_pulse_ajax_agency_reply_review(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_agency_can_manage() ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	if ( ! sp_agency_is_hub() )     wp_send_json_error( [ 'message' => 'This site is not the review hub.' ] );

	$site_key  = sanitize_text_field( wp_unslash( $_POST['site_key'] ?? '' ) );
	$review_id = sanitize_text_field( wp_unslash( $_POST['review_id'] ?? '' ) );
	$comment   = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) ); // GBP replies are plain text
	if ( '' === $site_key || '' === $review_id ) wp_send_json_error( [ 'message' => 'Missing review.' ] );

	$sites    = BPGBP_Hub::get_site_map();
	$cfg      = $sites[ $site_key ] ?? null;
	if ( ! $cfg || empty( $cfg['agency'] ) ) wp_send_json_error( [ 'message' => 'Not an agency-managed client.' ] );
	$location = $cfg['location'] ?? '';
	if ( '' === $location ) wp_send_json_error( [ 'message' => 'Unknown client.' ] );

	try {
		$res = BPGBP_Hub::reply_for_location( $location, $review_id, $comment );
	} catch ( Exception $e ) {
		wp_send_json_error( [ 'message' => $e->getMessage() ] );
	}

	$reply = ( '' === trim( $comment ) ) ? null : [ 'comment' => $comment, 'updateTime' => ( $res['updateTime'] ?? '' ) ];

	// Patch the per-location cache so the panel reflects the reply without a re-fetch.
	$cache = sp_agency_cache();
	if ( ! empty( $cache[ $location ]['reviews'] ) ) {
		foreach ( $cache[ $location ]['reviews'] as &$r ) {
			if ( (string) ( $r['reviewId'] ?? '' ) === $review_id ) { $r['reply'] = $reply; break; }
		}
		unset( $r );
		sp_agency_cache_save( $cache );
	}

	site_pulse_log(
		'agency_review_reply',
		( '' === trim( $comment ) ? 'Removed reply to' : 'Replied to' ) . ' client Google review',
		[ 'site_key' => $site_key, 'review_id' => $review_id ]
	);

	wp_send_json_success( [ 'reply' => $reply ] );
}


/*--------------------------------------------------------------
# AJAX — push a review to the client's site as a Testimonial CPT
--------------------------------------------------------------*/

/**
 * Is a Google reviewer photo a REAL uploaded picture (vs Google's monogram default — a colored circle
 * with an initial)? We can't tell reliably from the URL (the size directive is stripped), so we fetch the
 * image and count distinct (quantized) colors: a monogram resolves to a handful of buckets, a real photo
 * to many. Returns false when it can't verify, so only confirmed real photos are ever sent.
 */
function sp_agency_photo_is_real( string $url ): bool {
	if ( '' === $url || stripos( $url, 'default-user' ) !== false ) return false;

	$res = wp_remote_get( $url, [ 'timeout' => 10 ] );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) return false;

	$bytes = (string) wp_remote_retrieve_body( $res );
	if ( '' === $bytes || ! function_exists( 'imagecreatefromstring' ) ) return false;

	$img = @imagecreatefromstring( $bytes );
	if ( ! $img ) return false;

	$w = imagesx( $img );
	$h = imagesy( $img );
	$step = max( 1, (int) floor( min( $w, $h ) / 24 ) ); // ~24x24 sample grid
	$buckets = [];
	for ( $x = 0; $x < $w; $x += $step ) {
		for ( $y = 0; $y < $h; $y += $step ) {
			$rgb = imagecolorat( $img, $x, $y );
			// Quantize to 4 bits/channel so anti-aliasing noise doesn't inflate the count.
			$buckets[ ( ( $rgb >> 20 ) & 0xF ) . '-' . ( ( $rgb >> 12 ) & 0xF ) . '-' . ( ( $rgb >> 4 ) & 0xF ) ] = true;
			if ( count( $buckets ) >= 16 ) { imagedestroy( $img ); return true; } // clearly a real photo
		}
	}
	imagedestroy( $img );
	return count( $buckets ) >= 16;
}

add_action( 'wp_ajax_site_pulse_agency_push_testimonial', 'site_pulse_ajax_agency_push_testimonial' );
function site_pulse_ajax_agency_push_testimonial(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_agency_can_manage() ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	if ( ! sp_agency_is_hub() )     wp_send_json_error( [ 'message' => 'This site is not the review hub.' ] );

	$site_key  = sanitize_text_field( wp_unslash( $_POST['site_key'] ?? '' ) );
	$review_id = sanitize_text_field( wp_unslash( $_POST['review_id'] ?? '' ) );
	if ( '' === $site_key || '' === $review_id ) wp_send_json_error( [ 'message' => 'Missing review.' ] );

	$sites = BPGBP_Hub::get_site_map();
	$cfg   = $sites[ $site_key ] ?? null;
	if ( ! $cfg || empty( $cfg['agency'] ) ) wp_send_json_error( [ 'message' => 'Not an agency-managed client.' ] );

	$location = $cfg['location'] ?? '';
	$site_url = ! empty( $cfg['site_url'] ) ? rtrim( (string) $cfg['site_url'], '/' ) : '';
	$secret   = $cfg['secret'] ?? '';
	if ( '' === $site_url ) wp_send_json_error( [ 'message' => 'No Site URL set for this client — add it in Tools → GBP Clients.' ] );
	if ( '' === $secret )   wp_send_json_error( [ 'message' => 'No secret set for this client — approve/pair it in Tools → GBP Clients.' ] );

	// Pull the review from our cache — never trust client-posted review text. If it isn't there (stale
	// cache, or the location was just set/changed in GBP Clients so the cache is keyed differently),
	// re-fetch this location live and look again before giving up.
	$cache  = sp_agency_cache();
	$review = sp_agency_find_review( $cache[ $location ]['reviews'] ?? [], $review_id );

	if ( ! $review && '' !== $location ) {
		try {
			$data               = BPGBP_Hub::fetch_reviews_for_location( $location, '', 200, sp_agency_reviews_since() );
			$cache[ $location ] = [
				'fetched_at'       => time(),
				'averageRating'    => $data['averageRating'],
				'totalReviewCount' => $data['totalReviewCount'],
				'reviews'          => array_values( $data['reviews'] ),
			];
			sp_agency_cache_save( $cache );
			$review = sp_agency_find_review( $cache[ $location ]['reviews'], $review_id );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => 'Could not reach Google to verify the review: ' . $e->getMessage() ] );
		}
	}

	if ( ! $review ) {
		wp_send_json_error( [ 'message' => 'That review is no longer on this Google location — it may have been deleted, or the wrong location is mapped for this client (check Tools → GBP Clients).' ] );
	}

	$payload = [
		'reviewId'   => (string) ( $review['reviewId'] ?? '' ),
		'reviewer'   => (string) ( $review['reviewer'] ?? '' ),
		'comment'    => (string) ( $review['comment'] ?? '' ),
		'starRating' => (int) ( $review['starRating'] ?? 0 ),
	];

	// Reviewer profile photo → testimonial featured image. Send the (upscaled) URL and let the CLIENT
	// decide whether it's a real photo vs Google's monogram default — the client downloads the image to
	// sideload it anyway, so it does the check there (one download, no hub-side GD dependency).
	$photo = (string) ( $review['photo'] ?? '' );
	if ( '' !== $photo ) {
		$payload['photo'] = preg_replace( '/=s\d+-c[\w-]*$/', '=s400-c', $photo );
	}

	// Use the ?rest_route= form, NOT the pretty /wp-json/ path: pretty REST paths get
	// 301-redirected on these sites (WPE/Cloudflare), and wp_remote_post would follow the
	// redirect as a GET and drop the body. The query form is redirect-proof.
	$url       = $site_url . '/?rest_route=/bpgbp-client/v1/testimonial';
	$timestamp = (string) time();
	$raw       = wp_json_encode( $payload );
	$signature = hash_hmac( 'sha256', $timestamp . '.' . $raw, $secret );

	$response = wp_remote_post( $url, [
		'timeout' => 25,
		'headers' => [
			'X-BPGBP-Site'      => $site_key,
			'X-BPGBP-Timestamp' => $timestamp,
			'X-BPGBP-Signature' => $signature,
			'Content-Type'      => 'application/json',
		],
		'body'    => $raw,
	] );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( [ 'message' => 'Could not reach client site: ' . $response->get_error_message() ] );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['ok'] ) ) {
		$msg = ( is_array( $data ) && isset( $data['message'] ) ) ? $data['message'] : ( 'Client site returned ' . $code );
		wp_send_json_error( [ 'message' => $msg ] );
	}

	site_pulse_log(
		'agency_push_testimonial',
		'Pushed a Google review to a client site as a testimonial',
		[ 'site_key' => $site_key, 'review_id' => $review_id, 'post_id' => $data['post_id'] ?? null ]
	);

	wp_send_json_success( [
		'post_id'          => $data['post_id'] ?? null,
		'edit_url'         => $data['edit_url'] ?? null,
		'already_imported' => ! empty( $data['already_imported'] ),
		'photo'            => $data['photo'] ?? null, // 'set' | 'skipped-monogram' | 'exists' | 'error-*' | null (client on old code)
	] );
}
