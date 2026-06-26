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

		$fbid = (string) ( $cfg['facebook_page_id'] ?? '' );
		if ( '' === $location && '' === $fbid ) { $entry['error'] = 'No Google location or Facebook Page set — add one in Tools → Client Reviews.'; $out[] = $entry; continue; }

		$reviews = [];

		// ── Google reviews ──
		if ( '' !== $location ) {
			$cached = $cache[ $location ] ?? null;
			// Only the Refresh button re-polls (live Google fetch); opening the panel serves cache instantly.
			if ( $force ) {
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
					if ( empty( $cached['reviews'] ) ) { $entry['error'] = $e->getMessage(); }
					else { $entry['error'] = 'Showing saved reviews — ' . $e->getMessage(); }
				}
			}
			$entry['averageRating']    = $cached['averageRating']    ?? null;
			$entry['totalReviewCount'] = $cached['totalReviewCount'] ?? null;
			$greviews = $cached['reviews'] ?? [];
			foreach ( $greviews as &$gr ) { if ( empty( $gr['source'] ) ) $gr['source'] = 'google'; }
			unset( $gr );
			$dis = $dismissed[ $location ] ?? [];
			if ( $dis && $greviews ) {
				$greviews = array_values( array_filter( $greviews, function ( $r ) use ( $dis ) {
					return empty( $dis[ (string) ( $r['reviewId'] ?? '' ) ] );
				} ) );
			}
			$reviews = array_merge( $reviews, $greviews );
		}

		// ── Facebook recommendations (own cache bucket 'fb:<pageId>'; positive=5★, negative=1★) ──
		if ( '' !== $fbid && class_exists( 'BPFB_Hub' ) ) {
			$fbkey  = 'fb:' . $fbid;
			$fbc    = $cache[ $fbkey ] ?? null;
			// Only the Refresh button re-polls (live FB fetch); opening the panel serves cache instantly.
			if ( $force ) {
				try {
					$fbrev = [];
					foreach ( BPFB_Hub::fetch_reviews( $fbid, 100 ) as $r ) {
						$fbrev[] = [
							'reviewId'   => 'fb_' . sha1( $fbid . '|' . $r['createTime'] . '|' . $r['comment'] ),
							'reviewer'   => $r['author'],   // anonymous "Facebook user"; named at post time
							'comment'    => $r['comment'],
							'starRating' => (int) $r['rating'],
							'createTime' => $r['createTime'],
							'photo'      => '',
							'reply'      => '',
							'source'     => 'facebook',
						];
					}
					$fbc = [ 'fetched_at' => $now, 'reviews' => $fbrev ];
					$cache[ $fbkey ] = $fbc;
				} catch ( Exception $e ) {
					// Surface the FB fetch problem (token/mapping/deprecation) so it isn't an invisible blank.
					$entry['fb_error'] = $e->getMessage();
				}
			}
			$fbreviews = $fbc['reviews'] ?? [];
			$fdis = $dismissed[ $fbkey ] ?? [];
			if ( $fdis && $fbreviews ) {
				$fbreviews = array_values( array_filter( $fbreviews, function ( $r ) use ( $fdis ) {
					return empty( $fdis[ (string) ( $r['reviewId'] ?? '' ) ] );
				} ) );
			}
			$reviews = array_merge( $reviews, $fbreviews );
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
	// FB reviews dismiss into their own bucket ('fb:<pageId>'); Google into the location bucket.
	$is_fb  = strpos( $review_id, 'fb_' ) === 0;
	$bucket = $is_fb ? ( 'fb:' . (string) ( $cfg['facebook_page_id'] ?? '' ) ) : (string) ( $cfg['location'] ?? '' );
	if ( '' === $bucket || 'fb:' === $bucket ) wp_send_json_error( [ 'message' => 'Unknown client.' ] );

	// Record it as dismissed so it stays out of the list on every future pull.
	$dismissed = sp_agency_dismissed();
	if ( ! isset( $dismissed[ $bucket ] ) || ! is_array( $dismissed[ $bucket ] ) ) $dismissed[ $bucket ] = [];
	$dismissed[ $bucket ][ $review_id ] = 1;
	sp_agency_dismissed_save( $dismissed );

	// Also drop it from the live cache now, so it's gone immediately without waiting for a re-fetch.
	$cache = sp_agency_cache();
	if ( ! empty( $cache[ $bucket ]['reviews'] ) ) {
		$cache[ $bucket ]['reviews'] = array_values( array_filter( $cache[ $bucket ]['reviews'], function ( $r ) use ( $review_id ) {
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

/** Save a base64 data-URI image to the hub's uploads and return its URL (or '' on failure). Used for the
 *  manually-uploaded profile photo on a Facebook review, so the client site can sideload it. */
function sp_agency_save_fb_photo( string $data_uri ): string {
	if ( ! preg_match( '#^data:image/(jpe?g|png|gif|webp);base64,#i', $data_uri, $m ) ) return '';
	$bytes = base64_decode( substr( $data_uri, strpos( $data_uri, ',' ) + 1 ) );
	if ( false === $bytes || '' === $bytes ) return '';
	$ext = strtolower( $m[1] ); if ( 'jpeg' === $ext ) $ext = 'jpg';
	$up  = wp_upload_bits( 'fb-review-' . wp_generate_password( 8, false ) . '.' . $ext, null, $bytes );
	return ( ! empty( $up['url'] ) && empty( $up['error'] ) ) ? (string) $up['url'] : '';
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
	if ( '' === $site_url ) wp_send_json_error( [ 'message' => 'No Site URL set for this client — add it in Tools → Client Reviews.' ] );
	if ( '' === $secret )   wp_send_json_error( [ 'message' => 'No secret set for this client — approve/pair it in Tools → Client Reviews.' ] );

	$with_photo = ! empty( $_POST['with_photo'] );
	$is_fb      = strpos( $review_id, 'fb_' ) === 0;
	$cache      = sp_agency_cache();

	if ( $is_fb ) {
		// Facebook → testimonial. FB is anonymous, so the NAME (and optional PHOTO) come from the agent at
		// post time; the TEXT + rating come from our authoritative FB cache (never client-posted text).
		$fbid = (string) ( $cfg['facebook_page_id'] ?? '' );
		if ( '' === $fbid ) wp_send_json_error( [ 'message' => 'No Facebook Page mapped for this client.' ] );
		$bucket = 'fb:' . $fbid;
		$review = sp_agency_find_review( $cache[ $bucket ]['reviews'] ?? [], $review_id );
		if ( ! $review && class_exists( 'BPFB_Hub' ) ) {
			try {
				$fbrev = [];
				foreach ( BPFB_Hub::fetch_reviews( $fbid, 100 ) as $r ) {
					$fbrev[] = [
						'reviewId'   => 'fb_' . sha1( $fbid . '|' . $r['createTime'] . '|' . $r['comment'] ),
						'reviewer'   => $r['author'], 'comment' => $r['comment'],
						'starRating' => (int) $r['rating'], 'createTime' => $r['createTime'], 'photo' => '', 'source' => 'facebook',
					];
				}
				$cache[ $bucket ] = [ 'fetched_at' => time(), 'reviews' => $fbrev ];
				sp_agency_cache_save( $cache );
				$review = sp_agency_find_review( $fbrev, $review_id );
			} catch ( Exception $e ) {}
		}
		if ( ! $review ) wp_send_json_error( [ 'message' => 'That Facebook review is no longer available — Refresh and try again.' ] );

		$manual_name = sanitize_text_field( wp_unslash( $_POST['reviewer'] ?? '' ) );
		$payload = [
			'reviewId'   => (string) ( $review['reviewId'] ?? '' ),
			'reviewer'   => '' !== $manual_name ? $manual_name : (string) ( $review['reviewer'] ?? '' ),
			'comment'    => (string) ( $review['comment'] ?? '' ),
			'starRating' => (int) ( $review['starRating'] ?? 0 ),
			'platform'   => 'Facebook',
		];
		if ( $with_photo && ! empty( $_POST['photo_data'] ) ) {
			$purl = sp_agency_save_fb_photo( (string) wp_unslash( $_POST['photo_data'] ) );
			if ( '' !== $purl ) $payload['photo'] = $purl;
		}
	} else {
		// Google → testimonial. Pull from cache (never trust posted text); re-fetch live if missing.
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
			wp_send_json_error( [ 'message' => 'That review is no longer on this Google location — it may have been deleted, or the wrong location is mapped for this client (check Tools → Client Reviews).' ] );
		}
		$payload = [
			'reviewId'   => (string) ( $review['reviewId'] ?? '' ),
			'reviewer'   => (string) ( $review['reviewer'] ?? '' ),
			'comment'    => (string) ( $review['comment'] ?? '' ),
			'starRating' => (int) ( $review['starRating'] ?? 0 ),
		];
		$photo = (string) ( $review['photo'] ?? '' );
		if ( $with_photo && '' !== $photo ) {
			$payload['photo'] = preg_replace( '/=s\d+-c[\w-]*$/', '=s400-c', $photo );
		}
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
