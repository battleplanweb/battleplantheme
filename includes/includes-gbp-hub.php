<?php
/**
 * Battle Plan — Google Business Profile Hub (framework-native)
 *
 * The "hub" is a single-site role: the ONE site that holds the Google refresh token and
 * relays to Google on behalf of every client site (GBP posting + reviews). This code ships
 * in the framework and auto-updates with it, but it ONLY activates on the site whose
 * wp-config defines the token constants — every other install loads the class dormant and
 * registers no routes. The powerful refresh token still lives only on that one site.
 *
 * ── To turn a site INTO the hub (wp-config.php, above the "stop editing" line) ──
 *   define( 'BPGBP_CLIENT_ID',     '....apps.googleusercontent.com' );
 *   define( 'BPGBP_CLIENT_SECRET', 'GOCSPX-....' );
 *   define( 'BPGBP_REFRESH_TOKEN', '1//....' );   // ← presence of this activates the hub
 *
 * ── Site map (on the hub site only) ──
 * Client sites are listed in a JSON file: key => { secret, location, label }. 'location'
 * comes from GET bpgbp/v1/locations; 'secret' is a unique 64-hex per site. The file holds
 * secrets, so it MUST NOT be web-accessible — on WP Engine the hub auto-detects the install's
 * _wpeprivate/ dir (outside the web root): put it at <site-root>/_wpeprivate/bpgbp-sites.json.
 * Override the path with define( 'BPGBP_SITES_FILE', '/abs/path/outside/webroot/...json' ).
 *
 * Client sites consume the hub via the reviews module (includes-site-pulse-reviews.php) and
 * the posting client, authenticating with BPGBP_HUB_URL + BPGBP_SITE_KEY + BPGBP_SITE_SECRET.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BPGBP_Hub {

	const NS              = 'bpgbp/v1';
	const TOKEN_TRANSIENT = 'bpgbp_access_token';
	const TOKEN_URL       = 'https://oauth2.googleapis.com/token';
	const ACCOUNTS_URL    = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';
	const LOCATIONS_URL   = 'https://mybusinessbusinessinformation.googleapis.com/v1/%s/locations';
	const POST_URL        = 'https://mybusiness.googleapis.com/v4/%s/localPosts';
	const REVIEWS_URL     = 'https://mybusiness.googleapis.com/v4/%s/reviews';            // %s = accounts/X/locations/Y
	const REVIEW_REPLY_URL= 'https://mybusiness.googleapis.com/v4/%s/reviews/%s/reply';   // %s = location, %s = reviewId
	const MAX_CLOCK_SKEW  = 300; // seconds — replay/clock-skew window for signed requests
	const SUMMARY_LIMIT   = 1500; // Google's hard cap on local-post summary length

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	/* ─────────────── Admin helper page: Tools → GBP Locations ─────────────── */

	public static function admin_menu() {
		add_management_page(
			'GBP Locations',
			'GBP Locations',
			'manage_options',
			'bpgbp-locations',
			array( __CLASS__, 'render_locations_page' )
		);
	}

	/**
	 * A plain wp-admin page (Tools → GBP Locations) that lists every Google location this
	 * hub can manage, with the exact `location` string to paste into bpgbp-sites.json.
	 * No REST/nonce/CLI needed — it's a normal admin screen, so being logged in is enough.
	 */
	public static function render_locations_page() {
		echo '<div class="wrap">';
		echo '<h1>Google Business Profile — Locations</h1>';
		echo '<p>These are the listings this hub can manage. Copy a <strong>location</strong> value into <code>bpgbp-sites.json</code> for the matching site.</p>';

		try {
			$token    = self::get_access_token();
			$accounts = self::google_request( 'GET', self::ACCOUNTS_URL, $token );
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p><strong>Google said:</strong> ' . esc_html( $e->getMessage() ) . '</p></div>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th style="width:35%">Business</th><th>location (copy this)</th></tr></thead><tbody>';
		$found = false;

		if ( ! empty( $accounts['accounts'] ) ) {
			foreach ( $accounts['accounts'] as $account ) {
				$account_name = $account['name']; // "accounts/{id}"
				try {
					$url  = sprintf( self::LOCATIONS_URL, $account_name ) . '?readMask=name,title,storefrontAddress&pageSize=100';
					$locs = self::google_request( 'GET', $url, $token );
				} catch ( Exception $e ) {
					echo '<tr><td colspan="2"><em>Could not read ' . esc_html( $account_name ) . ': ' . esc_html( $e->getMessage() ) . '</em></td></tr>';
					continue;
				}
				if ( ! empty( $locs['locations'] ) ) {
					foreach ( $locs['locations'] as $loc ) {
						$found    = true;
						$label    = isset( $loc['title'] ) ? $loc['title'] : '(no title)';
						$location = $account_name . '/' . $loc['name']; // accounts/X/locations/Y
						echo '<tr><td>' . esc_html( $label ) . '</td><td><code>' . esc_html( $location ) . '</code></td></tr>';
					}
				}
			}
		}

		if ( ! $found ) {
			echo '<tr><td colspan="2">No locations found on this Google account.</td></tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	public static function register_routes() {
		// Client sites post here. Auth is per-site HMAC (see verify_site_signature).
		register_rest_route(
			self::NS,
			'/post',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_post' ),
				'permission_callback' => array( __CLASS__, 'verify_site_signature' ),
			)
		);

		// Client sites read their own location's reviews. Per-site HMAC; a GET has an
		// empty body, so the client signs hash_hmac(sha256, timestamp . '.' . '', secret).
		register_rest_route(
			self::NS,
			'/reviews',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_reviews' ),
				'permission_callback' => array( __CLASS__, 'verify_site_signature' ),
			)
		);

		// Client sites reply to one of their reviews (or, with an empty comment, delete the reply).
		register_rest_route(
			self::NS,
			'/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_reply' ),
				'permission_callback' => array( __CLASS__, 'verify_site_signature' ),
			)
		);

		// Admin-only helper: list every account/location as ready-to-paste mapping strings.
		register_rest_route(
			self::NS,
			'/locations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_locations' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/* ───────────────────────── auth: per-site HMAC ───────────────────────── */

	public static function verify_site_signature( WP_REST_Request $request ) {
		$site_key  = $request->get_header( 'x-bpgbp-site' );
		$timestamp = $request->get_header( 'x-bpgbp-timestamp' );
		$signature = $request->get_header( 'x-bpgbp-signature' );

		if ( ! $site_key || ! $timestamp || ! $signature ) {
			return new WP_Error( 'bpgbp_missing_auth', 'Missing authentication headers.', array( 'status' => 401 ) );
		}

		if ( abs( time() - (int) $timestamp ) > self::MAX_CLOCK_SKEW ) {
			return new WP_Error( 'bpgbp_stale', 'Request timestamp outside allowed window.', array( 'status' => 401 ) );
		}

		$sites = self::get_sites();
		if ( empty( $sites[ $site_key ]['secret'] ) || empty( $sites[ $site_key ]['location'] ) ) {
			return new WP_Error( 'bpgbp_unknown_site', 'Unknown site key.', array( 'status' => 403 ) );
		}

		// Sign timestamp + raw body so the payload can't be tampered with or replayed.
		$body     = $request->get_body();
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $sites[ $site_key ]['secret'] );

		if ( ! hash_equals( $expected, (string) $signature ) ) {
			return new WP_Error( 'bpgbp_bad_signature', 'Signature mismatch.', array( 'status' => 403 ) );
		}

		// Stash the verified site key for the handler.
		$request->set_param( '_bpgbp_site_key', $site_key );
		return true;
	}

	/* ───────────────────────── POST bpgbp/v1/post ───────────────────────── */

	public static function handle_post( WP_REST_Request $request ) {
		$sites    = self::get_sites();
		$site_key = $request->get_param( '_bpgbp_site_key' );
		$location = $sites[ $site_key ]['location']; // hub-enforced; never taken from the client

		$params  = (array) $request->get_json_params();
		$summary = isset( $params['summary'] ) ? trim( (string) $params['summary'] ) : '';

		if ( '' === $summary ) {
			return new WP_Error( 'bpgbp_no_summary', 'A post summary is required.', array( 'status' => 400 ) );
		}

		$post = array(
			'languageCode' => isset( $params['language_code'] ) ? $params['language_code'] : 'en-US',
			'summary'      => self::truncate( $summary, self::SUMMARY_LIMIT ),
			'topicType'    => isset( $params['topic_type'] ) ? $params['topic_type'] : 'STANDARD',
		);

		if ( ! empty( $params['cta_url'] ) ) {
			$post['callToAction'] = array(
				'actionType' => ! empty( $params['cta_type'] ) ? $params['cta_type'] : 'LEARN_MORE',
				'url'        => esc_url_raw( $params['cta_url'] ),
			);
		}

		if ( ! empty( $params['image_url'] ) ) {
			$post['media'] = array(
				array(
					'mediaFormat' => 'PHOTO',
					'sourceUrl'   => esc_url_raw( $params['image_url'] ),
				),
			);
		}

		try {
			$token  = self::get_access_token();
			$result = self::google_request( 'POST', sprintf( self::POST_URL, $location ), $token, $post );
		} catch ( Exception $e ) {
			return new WP_Error( 'bpgbp_google_error', $e->getMessage(), array( 'status' => 502 ) );
		}

		return new WP_REST_Response(
			array(
				'ok'        => true,
				'name'      => isset( $result['name'] ) ? $result['name'] : null,
				'searchUrl' => isset( $result['searchUrl'] ) ? $result['searchUrl'] : null,
				'state'     => isset( $result['state'] ) ? $result['state'] : null,
			),
			200
		);
	}

	/* ───────────────────────── GET bpgbp/v1/reviews ───────────────────────── */

	public static function handle_reviews( WP_REST_Request $request ) {
		$sites    = self::get_sites();
		$site_key = $request->get_param( '_bpgbp_site_key' );
		$location = $sites[ $site_key ]['location']; // hub-enforced; never taken from the client

		try {
			$token   = self::get_access_token();
			$reviews = array();
			$avg     = null;
			$total   = null;
			$page    = '';
			do {
				$url = sprintf( self::REVIEWS_URL, $location ) . '?pageSize=50';
				if ( $page ) {
					$url .= '&pageToken=' . rawurlencode( $page );
				}
				$data = self::google_request( 'GET', $url, $token );

				if ( ! empty( $data['reviews'] ) ) {
					foreach ( $data['reviews'] as $r ) {
						$reviews[] = self::normalize_review( $r );
					}
				}
				if ( null === $avg && isset( $data['averageRating'] ) ) {
					$avg = $data['averageRating'];
				}
				if ( null === $total && isset( $data['totalReviewCount'] ) ) {
					$total = (int) $data['totalReviewCount'];
				}
				$page = ! empty( $data['nextPageToken'] ) ? $data['nextPageToken'] : '';
			} while ( $page && count( $reviews ) < 2000 ); // safety cap against runaway paging
		} catch ( Exception $e ) {
			return new WP_Error( 'bpgbp_google_error', $e->getMessage(), array( 'status' => 502 ) );
		}

		return new WP_REST_Response(
			array(
				'ok'               => true,
				'averageRating'    => $avg,
				'totalReviewCount' => $total,
				'reviews'          => $reviews,
			),
			200
		);
	}

	/* ───────────────────────── POST bpgbp/v1/reply ───────────────────────── */

	public static function handle_reply( WP_REST_Request $request ) {
		$sites    = self::get_sites();
		$site_key = $request->get_param( '_bpgbp_site_key' );
		$location = $sites[ $site_key ]['location']; // hub-enforced

		$params    = (array) $request->get_json_params();
		$review_id = isset( $params['review_id'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $params['review_id'] ) : '';
		$comment   = isset( $params['comment'] ) ? trim( (string) $params['comment'] ) : '';

		if ( '' === $review_id ) {
			return new WP_Error( 'bpgbp_no_review', 'A review_id is required.', array( 'status' => 400 ) );
		}

		$url = sprintf( self::REVIEW_REPLY_URL, $location, $review_id );

		try {
			$token = self::get_access_token();
			if ( '' === $comment ) {
				// Empty comment = remove the existing reply.
				self::google_request( 'DELETE', $url, $token );
				return new WP_REST_Response( array( 'ok' => true, 'deleted' => true ), 200 );
			}
			$result = self::google_request( 'PUT', $url, $token, array( 'comment' => $comment ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'bpgbp_google_error', $e->getMessage(), array( 'status' => 502 ) );
		}

		return new WP_REST_Response(
			array(
				'ok'         => true,
				'comment'    => isset( $result['comment'] ) ? $result['comment'] : $comment,
				'updateTime' => isset( $result['updateTime'] ) ? $result['updateTime'] : null,
			),
			200
		);
	}

	/** Flatten a v4 review object into the lean shape client sites consume. */
	private static function normalize_review( $r ) {
		$reply = null;
		if ( ! empty( $r['reviewReply'] ) ) {
			$reply = array(
				'comment'    => isset( $r['reviewReply']['comment'] ) ? $r['reviewReply']['comment'] : '',
				'updateTime' => isset( $r['reviewReply']['updateTime'] ) ? $r['reviewReply']['updateTime'] : '',
			);
		}

		// v4 review name is "accounts/X/locations/Y/reviews/{reviewId}"; prefer the explicit field.
		$review_id = isset( $r['reviewId'] ) ? $r['reviewId']
			: ( ! empty( $r['name'] ) ? substr( strrchr( $r['name'], '/' ), 1 ) : '' );

		return array(
			'reviewId'   => $review_id,
			'reviewer'   => isset( $r['reviewer']['displayName'] ) ? $r['reviewer']['displayName'] : 'Anonymous',
			'photo'      => isset( $r['reviewer']['profilePhotoUrl'] ) ? $r['reviewer']['profilePhotoUrl'] : '',
			'starRating' => self::star_rating_to_int( isset( $r['starRating'] ) ? $r['starRating'] : '' ),
			'comment'    => isset( $r['comment'] ) ? $r['comment'] : '',
			'createTime' => isset( $r['createTime'] ) ? $r['createTime'] : '',
			'updateTime' => isset( $r['updateTime'] ) ? $r['updateTime'] : '',
			'reply'      => $reply,
		);
	}

	/** Google returns star ratings as an enum (ONE..FIVE); map to 1..5 (0 if unknown). */
	private static function star_rating_to_int( $enum ) {
		$map = array( 'ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5 );
		return isset( $map[ $enum ] ) ? $map[ $enum ] : 0;
	}

	/* ─────────────────── GET bpgbp/v1/locations (admin) ─────────────────── */

	public static function handle_locations() {
		try {
			$token    = self::get_access_token();
			$accounts = self::google_request( 'GET', self::ACCOUNTS_URL, $token );
		} catch ( Exception $e ) {
			return new WP_Error( 'bpgbp_google_error', $e->getMessage(), array( 'status' => 502 ) );
		}

		$out = array();
		if ( ! empty( $accounts['accounts'] ) ) {
			foreach ( $accounts['accounts'] as $account ) {
				$account_name = $account['name']; // "accounts/{id}"
				try {
					$url  = sprintf( self::LOCATIONS_URL, $account_name )
						. '?readMask=name,title,storefrontAddress&pageSize=100';
					$locs = self::google_request( 'GET', $url, $token );
				} catch ( Exception $e ) {
					$out[] = array(
						'account' => $account_name,
						'error'   => $e->getMessage(),
					);
					continue;
				}
				if ( ! empty( $locs['locations'] ) ) {
					foreach ( $locs['locations'] as $loc ) {
						// loc['name'] is "locations/{id}"; v4 localPosts wants "accounts/{id}/locations/{id}".
						$out[] = array(
							'label'    => isset( $loc['title'] ) ? $loc['title'] : '(no title)',
							'location' => $account_name . '/' . $loc['name'],
						);
					}
					if ( ! empty( $locs['nextPageToken'] ) ) {
						$out[] = array(
							'account' => $account_name,
							'note'    => 'More than 100 locations — pagination not yet implemented.',
						);
					}
				}
			}
		}

		return new WP_REST_Response( array( 'locations' => $out ), 200 );
	}

	/* ───────────────────────── Google plumbing ───────────────────────── */

	private static function get_access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( $cached ) {
			return $cached;
		}

		foreach ( array( 'BPGBP_CLIENT_ID', 'BPGBP_CLIENT_SECRET', 'BPGBP_REFRESH_TOKEN' ) as $const ) {
			if ( ! defined( $const ) || ! constant( $const ) ) {
				throw new Exception( "Missing required constant {$const} in wp-config.php." );
			}
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => BPGBP_CLIENT_ID,
					'client_secret' => BPGBP_CLIENT_SECRET,
					'refresh_token' => BPGBP_REFRESH_TOKEN,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		$data = self::decode( $response );
		if ( empty( $data['access_token'] ) ) {
			$msg = isset( $data['error_description'] ) ? $data['error_description']
				: ( isset( $data['error'] ) ? $data['error'] : 'unknown error' );
			throw new Exception( 'Token refresh failed: ' . $msg );
		}

		$ttl = isset( $data['expires_in'] ) ? (int) $data['expires_in'] - 60 : 3000;
		set_transient( self::TOKEN_TRANSIENT, $data['access_token'], max( 60, $ttl ) );
		return $data['access_token'];
	}

	private static function google_request( $method, $url, $token, $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = ( 'GET' === $method )
			? wp_remote_get( $url, $args )
			: wp_remote_request( $url, $args );

		return self::decode( $response );
	}

	private static function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : $raw;
			throw new Exception( "Google API {$code}: {$msg}" );
		}
		return is_array( $data ) ? $data : array();
	}

	/* ───────────────────────── helpers ───────────────────────── */

	private static function get_sites() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$sites = array();
		$path  = defined( 'BPGBP_SITES_FILE' ) ? BPGBP_SITES_FILE : self::default_sites_path();

		if ( $path && is_readable( $path ) ) {
			$decoded = json_decode( (string) file_get_contents( $path ), true );
			if ( is_array( $decoded ) ) {
				$sites = $decoded;
			}
		}

		// Filter still runs after the file loads, for optional programmatic overrides.
		$sites = apply_filters( 'bpgbp_sites', $sites );

		$cache = is_array( $sites ) ? $sites : array();
		return $cache;
	}

	private static function default_sites_path() {
		// WP Engine: _wpeprivate sits in the site root but is blocked from web access.
		$wpe_dir = ABSPATH . '_wpeprivate';
		if ( is_dir( $wpe_dir ) ) {
			return $wpe_dir . '/bpgbp-sites.json';
		}
		// Fallback — NOT web-protected by default; see header notes.
		return WP_CONTENT_DIR . '/bpgbp-sites.json';
	}

	private static function truncate( $text, $limit ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text ) > $limit ? mb_substr( $text, 0, $limit ) : $text;
		}
		return strlen( $text ) > $limit ? substr( $text, 0, $limit ) : $text;
	}
}

// Single-site role: only the install whose wp-config carries the refresh token becomes the
// hub and registers the /bpgbp/v1/* routes. Everywhere else the class loads dormant.
if ( defined( 'BPGBP_REFRESH_TOKEN' ) && BPGBP_REFRESH_TOKEN ) {
	BPGBP_Hub::init();
}
