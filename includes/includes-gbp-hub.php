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
		// The "GBP Locations" tab is retired — location IDs now show in the Client Reviews list (Locations column).
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

	/**
	 * Every manageable GBP location WITH its websiteUri — used to auto-match a client site to its
	 * location during pairing approval. Paginated (your listings exceed 100 under one account) and
	 * cached for an hour. Returns [ [ 'location' => 'accounts/X/locations/Y', 'title', 'website' ], … ];
	 * [] on any API error. Pass $fresh = true to bypass the cache.
	 */
	public static function locations_for_match( $fresh = false ) {
		if ( ! $fresh ) {
			$cached = get_transient( 'bpgbp_loc_match' );
			if ( is_array( $cached ) ) return $cached;
		}

		$out = array();
		try {
			$token    = self::get_access_token();
			$accounts = self::google_request( 'GET', self::ACCOUNTS_URL, $token );
		} catch ( Exception $e ) {
			return array();
		}

		if ( ! empty( $accounts['accounts'] ) ) {
			foreach ( $accounts['accounts'] as $account ) {
				$account_name = $account['name'];
				$next         = '';
				do {
					try {
						$url = sprintf( self::LOCATIONS_URL, $account_name ) . '?readMask=name,title,websiteUri&pageSize=100';
						if ( $next ) $url .= '&pageToken=' . rawurlencode( $next );
						$locs = self::google_request( 'GET', $url, $token );
					} catch ( Exception $e ) {
						break;
					}
					if ( ! empty( $locs['locations'] ) ) {
						foreach ( $locs['locations'] as $loc ) {
							$out[] = array(
								'location' => $account_name . '/' . $loc['name'],
								'title'    => isset( $loc['title'] ) ? $loc['title'] : '',
								'website'  => isset( $loc['websiteUri'] ) ? $loc['websiteUri'] : '',
							);
						}
					}
					$next = isset( $locs['nextPageToken'] ) ? (string) $locs['nextPageToken'] : '';
				} while ( $next );
			}
		}

		set_transient( 'bpgbp_loc_match', $out, HOUR_IN_SECONDS );
		return $out;
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

		// Client sites ask which locations they own (so a multi-location client knows what to sync).
		register_rest_route(
			self::NS,
			'/site-locations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_site_locations' ),
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
		$site     = $sites[ $site_key ];

		// The client may target any location it owns; the hub validates it against this site's allowlist
		// (so a site still can't read another client's listing). No location → the site's primary.
		$location = self::resolve_site_location( $site, (string) $request->get_param( 'location' ) );
		if ( '' === $location ) {
			return new WP_Error( 'bpgbp_bad_location', 'That location is not configured for this site.', array( 'status' => 403 ) );
		}
		$lm = self::location_meta( $site, $location );

		// Optional pagination: page_token resumes older pages; max caps this batch (default newest 200).
		$start_token = (string) $request->get_param( 'page_token' );
		$max         = (int) ( $request->get_param( 'max' ) ?: 200 );
		$max         = min( 1000, max( 1, $max ) ); // hard ceiling so one call can't run away

		try {
			$data = self::fetch_reviews_for_location( $location, $start_token, $max );
		} catch ( Exception $e ) {
			return new WP_Error( 'bpgbp_google_error', $e->getMessage(), array( 'status' => 502 ) );
		}

		$response = new WP_REST_Response(
			array(
				'ok'               => true,
				'averageRating'    => $data['averageRating'],
				'totalReviewCount' => $data['totalReviewCount'],
				'reviews'          => $data['reviews'],
				'nextPageToken'    => $data['nextPageToken'],
				'location'         => $location,
				'label'            => $lm['label'], // friendly location name for the card
				'brand'            => $lm['brand'], // brand grouping for the by-brand filter
			),
			200
		);
		// Never let an edge cache (Cloudflare/WPE) store this per-site response.
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	/* ───────────────────────── POST bpgbp/v1/reply ───────────────────────── */

	public static function handle_reply( WP_REST_Request $request ) {
		$sites    = self::get_sites();
		$site_key = $request->get_param( '_bpgbp_site_key' );
		$site     = $sites[ $site_key ];

		$params    = (array) $request->get_json_params();
		$review_id = isset( $params['review_id'] ) ? (string) $params['review_id'] : '';
		$comment   = isset( $params['comment'] ) ? (string) $params['comment'] : '';

		// The review's own location (validated against the site's allowlist); falls back to primary.
		$location = self::resolve_site_location( $site, isset( $params['location'] ) ? (string) $params['location'] : '' );
		if ( '' === $location ) {
			return new WP_Error( 'bpgbp_bad_location', 'That location is not configured for this site.', array( 'status' => 403 ) );
		}
		if ( '' === trim( $review_id ) ) {
			return new WP_Error( 'bpgbp_no_review', 'A review_id is required.', array( 'status' => 400 ) );
		}

		try {
			$result = self::reply_for_location( $location, $review_id, $comment );
		} catch ( Exception $e ) {
			return new WP_Error( 'bpgbp_google_error', $e->getMessage(), array( 'status' => 502 ) );
		}

		if ( ! empty( $result['deleted'] ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'deleted' => true ), 200 );
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

	/* ───────────────────────── GET bpgbp/v1/site-locations ───────────────────────── */

	public static function handle_site_locations( WP_REST_Request $request ) {
		$sites    = self::get_sites();
		$site_key = $request->get_param( '_bpgbp_site_key' );
		$site     = $sites[ $site_key ];

		$response = new WP_REST_Response(
			array(
				'ok'        => true,
				'locations' => array_values( isset( $site['locations'] ) ? $site['locations'] : array() ),
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	/* ───────────── in-process API (the hub's own dashboard uses these) ─────────────
	 * The REST handlers above lock each call to the caller's own location via HMAC. The hub
	 * site itself (its agency "Client Reviews" view) trusts itself, so it calls these directly
	 * with an explicit location — no HMAC round-trip to localhost. Same Google plumbing. */

	/**
	 * Pull reviews + the exact summary for one location. Starts from $start_token (Google's
	 * nextPageToken — '' = newest first) and gathers up to $max reviews, returning the
	 * nextPageToken so a caller can resume for older pages ('' once Google has no more).
	 * Throws on Google error.
	 */
	public static function fetch_reviews_for_location( $location, $start_token = '', $max = 200, $since = 0 ) {
		$max     = max( 1, (int) $max );
		$since   = (int) $since; // unix timestamp cutoff; 0 = no cutoff (full history up to $max)
		$token   = self::get_access_token();
		$reviews = array();
		$avg     = null;
		$total   = null;
		$page    = (string) $start_token;
		do {
			$url = sprintf( self::REVIEWS_URL, $location ) . '?pageSize=50';
			if ( $page ) {
				$url .= '&pageToken=' . rawurlencode( $page );
			}
			$data = self::google_request( 'GET', $url, $token );

			// Summary fields come from page 1 — capture them before any early break so they stay exact.
			if ( null === $avg && isset( $data['averageRating'] ) ) {
				$avg = $data['averageRating'];
			}
			if ( null === $total && isset( $data['totalReviewCount'] ) ) {
				$total = (int) $data['totalReviewCount'];
			}

			if ( ! empty( $data['reviews'] ) ) {
				foreach ( $data['reviews'] as $r ) {
					$nr = self::normalize_review( $r );
					// Reviews come newest-first, so the first one older than the cutoff means we're done —
					// stop here instead of walking the entire back-catalogue.
					if ( $since > 0 && ! empty( $nr['createTime'] ) ) {
						$ts = strtotime( (string) $nr['createTime'] );
						if ( $ts && $ts < $since ) { $page = ''; break 2; }
					}
					$reviews[] = $nr;
				}
			}
			$page = ! empty( $data['nextPageToken'] ) ? $data['nextPageToken'] : '';
		} while ( $page && count( $reviews ) < $max );

		return array(
			'averageRating'    => $avg,
			'totalReviewCount' => $total,
			'reviews'          => $reviews,
			'nextPageToken'    => $page, // '' when Google has no older pages left
		);
	}

	/** Reply to a review (empty comment = delete the reply). Throws on Google error. */
	public static function reply_for_location( $location, $review_id, $comment ) {
		$review_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $review_id );
		if ( '' === $review_id ) {
			throw new Exception( 'A review_id is required.' );
		}
		$comment = trim( (string) $comment );
		$url     = sprintf( self::REVIEW_REPLY_URL, $location, $review_id );
		$token   = self::get_access_token();

		if ( '' === $comment ) {
			self::google_request( 'DELETE', $url, $token );
			return array( 'deleted' => true );
		}
		return self::google_request( 'PUT', $url, $token, array( 'comment' => $comment ) );
	}

	/** Read-only accessor for the site map (key => { secret, location, label, site_url }). */
	public static function get_site_map() {
		return self::get_sites();
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

		// Normalize every entry to a `locations` array of { id, label, brand } so multi-location and the
		// legacy single `location`/`label` form behave identically downstream. `location` is kept as the
		// primary id for single-location consumers (the post route + the signature precheck).
		foreach ( $cache as $key => $entry ) {
			if ( ! is_array( $entry ) ) { $cache[ $key ] = array(); continue; }
			$entry['locations'] = self::normalize_locations( $entry );
			if ( empty( $entry['location'] ) && ! empty( $entry['locations'][0]['id'] ) ) {
				$entry['location'] = $entry['locations'][0]['id'];
			}
			$cache[ $key ] = $entry;
		}
		return $cache;
	}

	/** Build a uniform [ {id,label,brand}, ... ] from either a `locations` array or a single `location`. */
	private static function normalize_locations( array $entry ) {
		$out = array();
		if ( ! empty( $entry['locations'] ) && is_array( $entry['locations'] ) ) {
			foreach ( $entry['locations'] as $l ) {
				$id = is_array( $l ) ? (string) ( isset( $l['id'] ) ? $l['id'] : '' ) : (string) $l;
				if ( '' === $id ) continue;
				$out[] = array(
					'id'    => $id,
					'label' => ( is_array( $l ) && isset( $l['label'] ) ) ? (string) $l['label'] : '',
					'brand' => ( is_array( $l ) && isset( $l['brand'] ) ) ? (string) $l['brand'] : '',
				);
			}
		} elseif ( ! empty( $entry['location'] ) ) {
			$out[] = array(
				'id'    => (string) $entry['location'],
				'label' => isset( $entry['label'] ) ? (string) $entry['label'] : '',
				'brand' => isset( $entry['brand'] ) ? (string) $entry['brand'] : '',
			);
		}
		return $out;
	}

	/** Validate a requested location against a site; '' → the site's primary; returns '' if not owned. */
	private static function resolve_site_location( array $site, $requested ) {
		$locs      = isset( $site['locations'] ) ? $site['locations'] : array();
		$requested = (string) $requested;
		if ( '' === $requested ) {
			if ( ! empty( $locs[0]['id'] ) ) return $locs[0]['id'];
			return ! empty( $site['location'] ) ? (string) $site['location'] : '';
		}
		foreach ( $locs as $l ) {
			if ( isset( $l['id'] ) && $l['id'] === $requested ) return $requested;
		}
		return '';
	}

	/** label/brand for a location id within a site (falls back to the site-level label/brand). */
	private static function location_meta( array $site, $location_id ) {
		foreach ( ( isset( $site['locations'] ) ? $site['locations'] : array() ) as $l ) {
			if ( isset( $l['id'] ) && $l['id'] === $location_id ) {
				return array( 'label' => (string) $l['label'], 'brand' => (string) $l['brand'] );
			}
		}
		return array(
			'label' => isset( $site['label'] ) ? (string) $site['label'] : '',
			'brand' => isset( $site['brand'] ) ? (string) $site['brand'] : '',
		);
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


/* ─────────────────── CLIENT CONFIG: constant OR stored option ───────────────────
 * A client's hub credentials used to come only from wp-config constants. To let a site be PAIRED
 * automatically (no wp-config edit), each value now falls back to a stored option that the pairing
 * handshake writes. A constant always wins, so existing hand-configured sites are unaffected. */
function bpgbp_cfg( $which ) {
	static $map = array(
		'HUB_URL'     => array( 'BPGBP_HUB_URL', 'bpgbp_hub_url' ),
		'SITE_KEY'    => array( 'BPGBP_SITE_KEY', 'bpgbp_site_key' ),
		'SITE_SECRET' => array( 'BPGBP_SITE_SECRET', 'bpgbp_site_secret' ),
	);
	if ( ! isset( $map[ $which ] ) ) return '';
	list( $const, $opt ) = $map[ $which ];
	if ( defined( $const ) && constant( $const ) ) return (string) constant( $const );
	return (string) get_option( $opt, '' );
}


/* ─────────────────── GBP CLIENT: testimonial receiver (mirror of the hub) ───────────────────
 * The client-side counterpart to the hub. Any battleplantheme site configured as a hub client
 * (wp-config defines BPGBP_SITE_SECRET) accepts a signed push from the hub's agency dashboard and
 * posts the review as a `testimonials` CPT. Like the hub above, this lives in a file loaded on
 * EVERY install but stays dormant unless the secret exists — so the push reaches plain client
 * sites (e.g. the HVAC contractors) that don't run the Site Pulse app, not only Site Pulse ones.
 *
 * Auth is the same per-site HMAC as the hub, reversed: the hub signs timestamp . '.' . rawBody
 * with the secret it holds for this site in bpgbp-sites.json; we verify with BPGBP_SITE_SECRET. */

/**
 * Create a `testimonials` post from a normalized Google review (one per reviewId). Shared by the
 * push receiver here ($status 'publish' — goes live) and Site Pulse's local importer ($status
 * defaults to 'draft' — client reviews before publishing).
 * Returns array( 'post_id', 'edit_url' ), array( 'already_imported' => true, 'post_id' ), or WP_Error.
 */
function bpgbp_create_testimonial_from_review( array $review, $status = 'draft' ) {
	$review_id = (string) ( isset( $review['reviewId'] ) ? $review['reviewId'] : '' );
	if ( '' === $review_id ) {
		return new WP_Error( 'bpgbp_no_review', 'Missing review id.' );
	}

	$status = ( 'publish' === $status ) ? 'publish' : 'draft';

	// Source platform — Google by default; the push sends 'Facebook' for FB recommendations.
	$platform = (string) ( isset( $review['platform'] ) ? $review['platform'] : '' );
	$platform = in_array( $platform, array( 'Google', 'Facebook' ), true ) ? $platform : 'Google';

	// One testimonial per Google review.
	$dupe = get_posts( array(
		'post_type'   => 'testimonials',
		'post_status' => 'any',
		'meta_key'    => '_bp_google_review_id',
		'meta_value'  => $review_id,
		'fields'      => 'ids',
		'numberposts' => 1,
	) );
	if ( $dupe ) {
		$existing     = (int) $dupe[0];
		// Keep the platform tag current on re-push — an earlier test may have created this as Google.
		if ( function_exists( 'update_field' ) ) update_field( 'testimonial_platform', $platform, $existing );
		else update_post_meta( $existing, 'testimonial_platform', $platform );
		$photo_status = 'none';
		// Backfill the photo if this review was imported before we supported it (and has none yet).
		$photo = (string) ( isset( $review['photo'] ) ? $review['photo'] : '' );
		if ( '' !== $photo ) $photo_status = bpgbp_set_testimonial_photo( $existing, $photo );
		return array( 'already_imported' => true, 'post_id' => $existing, 'photo' => $photo_status, 'platform' => $platform );
	}

	$post_id = wp_insert_post( array(
		'post_type'    => 'testimonials',
		'post_status'  => $status,
		'post_title'   => ( isset( $review['reviewer'] ) ? $review['reviewer'] : '' ) ?: ( $platform . ' Reviewer' ),
		'post_content' => isset( $review['comment'] ) ? $review['comment'] : '',
	), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$rating = (int) ( isset( $review['starRating'] ) ? $review['starRating'] : 0 );
	if ( function_exists( 'update_field' ) ) {
		update_field( 'testimonial_rating', $rating, $post_id );
		update_field( 'testimonial_platform', $platform, $post_id );
	} else {
		update_post_meta( $post_id, 'testimonial_rating', $rating );
		update_post_meta( $post_id, 'testimonial_platform', $platform );
	}
	update_post_meta( $post_id, '_bp_google_review_id', $review_id );

	// Optional: the reviewer's Google profile photo → featured image (skipped if it's a monogram default).
	$photo_status = 'none';
	$photo = (string) ( isset( $review['photo'] ) ? $review['photo'] : '' );
	if ( '' !== $photo ) {
		$photo_status = bpgbp_set_testimonial_photo( (int) $post_id, $photo );
	}

	return array( 'post_id' => (int) $post_id, 'edit_url' => get_edit_post_link( $post_id, 'raw' ), 'photo' => $photo_status, 'platform' => $platform );
}

/**
 * Sideload a Google-hosted reviewer photo and set it as the testimonial's featured image. Restricted to
 * googleusercontent.com hosts. Skips Google's monogram default (colored circle + initial). Returns a
 * status string: 'set' | 'exists' | 'skipped-monogram' | 'skipped-host' | 'error-download' |
 * 'error-type' | 'error-sideload'.
 */
function bpgbp_set_testimonial_photo( int $post_id, string $url ) {
	if ( ! $post_id || '' === $url ) return 'none';
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	// Allow Google's photo CDN AND the agency hub's own domain (manually-uploaded Facebook profile pics
	// are hosted on the hub, which already signs the push, so it's a trusted source). Trust both the
	// configured hub URL (the hub this site actually pairs with) and the BPGBP_PAIR_HUB constant.
	$hub_hosts = array();
	if ( function_exists( 'bpgbp_cfg' ) && bpgbp_cfg( 'HUB_URL' ) ) $hub_hosts[] = strtolower( (string) wp_parse_url( bpgbp_cfg( 'HUB_URL' ), PHP_URL_HOST ) );
	if ( defined( 'BPGBP_PAIR_HUB' ) )                              $hub_hosts[] = strtolower( (string) wp_parse_url( BPGBP_PAIR_HUB, PHP_URL_HOST ) );
	$ok_host = preg_match( '/(^|\.)googleusercontent\.com$/', $host ) || in_array( $host, array_filter( $hub_hosts ), true );
	if ( ! $ok_host ) return 'skipped-host';
	if ( has_post_thumbnail( $post_id ) ) return 'exists';

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url, 20 );
	if ( is_wp_error( $tmp ) ) return 'error-download';

	// Skip Google's monogram default (few distinct colors); real photos have many. If we can't analyze
	// (no GD), we DON'T skip — better to occasionally allow a monogram than to drop a real photo.
	if ( bpgbp_image_is_monogram( $tmp ) ) { @unlink( $tmp ); return 'skipped-monogram'; }

	// Google may serve jpeg/png/webp regardless of the URL, so name the temp file by its REAL type —
	// otherwise the sideload's filetype check rejects a .jpg name on non-jpeg bytes (a silent failure).
	$mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $tmp ) : '';
	$map  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp' );
	$ext  = isset( $map[ $mime ] ) ? $map[ $mime ] : '';
	if ( '' === $ext ) { @unlink( $tmp ); return 'error-type'; }

	// Normalize the avatar like the Jobsite GEO photos: cap at 400x400 (keep aspect, no crop) and convert
	// to WebP. Falls back to the original file if the image editor / WebP support isn't available.
	$final_path = $tmp;
	$final_ext  = $ext;
	$editor = wp_get_image_editor( $tmp );
	if ( ! is_wp_error( $editor ) ) {
		$editor->resize( 400, 400, false );
		$webp  = preg_replace( '/\.[^.]+$/', '', $tmp ) . '-400.webp';
		$saved = $editor->save( $webp, 'image/webp' );
		if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
			@unlink( $tmp );
			$final_path = $saved['path'];
			$final_ext  = 'webp';
		}
	}

	$file   = array( 'name' => 'review-' . $post_id . '.' . $final_ext, 'tmp_name' => $final_path );
	$att_id = media_handle_sideload( $file, $post_id, '' );
	if ( is_wp_error( $att_id ) ) { @unlink( $final_path ); return 'error-sideload'; }

	set_post_thumbnail( $post_id, (int) $att_id );
	return 'set';
}

/** True if a local image file looks like Google's monogram default (very few distinct colors). */
function bpgbp_image_is_monogram( $path ) {
	if ( ! function_exists( 'imagecreatefromstring' ) ) return false; // can't analyze → treat as real
	$bytes = @file_get_contents( $path );
	if ( false === $bytes || '' === $bytes ) return false;
	$img = @imagecreatefromstring( $bytes );
	if ( ! $img ) return false;

	$w = imagesx( $img );
	$h = imagesy( $img );
	$step    = max( 1, (int) floor( min( $w, $h ) / 24 ) ); // ~24x24 sample grid
	$buckets = array();
	for ( $x = 0; $x < $w; $x += $step ) {
		for ( $y = 0; $y < $h; $y += $step ) {
			$rgb = imagecolorat( $img, $x, $y );
			// Quantize to 4 bits/channel so anti-aliasing noise doesn't inflate the count.
			$buckets[ ( ( $rgb >> 20 ) & 0xF ) . '-' . ( ( $rgb >> 12 ) & 0xF ) . '-' . ( ( $rgb >> 4 ) & 0xF ) ] = true;
			if ( count( $buckets ) >= 16 ) { imagedestroy( $img ); return false; } // many colors → real photo
		}
	}
	imagedestroy( $img );
	return count( $buckets ) < 16; // few colors → monogram
}

if ( bpgbp_cfg( 'SITE_SECRET' ) ) {
	add_action( 'rest_api_init', 'bpgbp_register_testimonial_receiver' );
}

function bpgbp_register_testimonial_receiver() {
	register_rest_route( 'bpgbp-client/v1', '/testimonial', array(
		'methods'             => 'POST',
		'callback'            => 'bpgbp_receive_testimonial',
		'permission_callback' => 'bpgbp_verify_hub_signature',
	) );
}

/** Mirror of BPGBP_Hub::verify_site_signature(): sign timestamp . '.' . rawBody with our secret. */
function bpgbp_verify_hub_signature( WP_REST_Request $request ) {
	$timestamp = $request->get_header( 'x-bpgbp-timestamp' );
	$signature = $request->get_header( 'x-bpgbp-signature' );

	if ( ! $timestamp || ! $signature ) {
		return new WP_Error( 'bpgbp_missing_auth', 'Missing authentication headers.', array( 'status' => 401 ) );
	}
	if ( abs( time() - (int) $timestamp ) > 300 ) {
		return new WP_Error( 'bpgbp_stale', 'Request timestamp outside allowed window.', array( 'status' => 401 ) );
	}

	$expected = hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), bpgbp_cfg( 'SITE_SECRET' ) );
	if ( ! hash_equals( $expected, (string) $signature ) ) {
		return new WP_Error( 'bpgbp_bad_signature', 'Signature mismatch.', array( 'status' => 403 ) );
	}
	return true;
}

function bpgbp_receive_testimonial( WP_REST_Request $request ) {
	$p      = (array) $request->get_json_params();
	$review = array(
		'reviewId'   => isset( $p['reviewId'] )   ? sanitize_text_field( (string) $p['reviewId'] ) : '',
		'reviewer'   => isset( $p['reviewer'] )   ? sanitize_text_field( (string) $p['reviewer'] ) : '',
		'comment'    => isset( $p['comment'] )    ? wp_kses_post( (string) $p['comment'] ) : '',
		'starRating' => isset( $p['starRating'] ) ? (int) $p['starRating'] : 0,
		'photo'      => isset( $p['photo'] )      ? esc_url_raw( (string) $p['photo'] ) : '',
		'platform'   => isset( $p['platform'] )   ? sanitize_text_field( (string) $p['platform'] ) : '',
	);
	if ( '' === $review['reviewId'] ) {
		return new WP_Error( 'bpgbp_no_review', 'Missing review id.', array( 'status' => 400 ) );
	}

	// Pushed testimonials go LIVE on the client site (the agency reviewed them before sending).
	$created = bpgbp_create_testimonial_from_review( $review, 'publish' );
	if ( is_wp_error( $created ) ) {
		return new WP_Error( 'bpgbp_create_failed', $created->get_error_message(), array( 'status' => 500 ) );
	}

	return new WP_REST_Response( array_merge( array( 'ok' => true ), $created ), 200 );
}
