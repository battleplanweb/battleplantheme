<?php
/**
 * Battle Plan — Facebook (Meta) Hub  ·  OAuth + Page reviews
 *
 * Mirrors the Google Business Profile hub (includes-gbp-hub.php): ONE site (bp-webdev.com) holds the
 * Meta app credentials + the Page access tokens and talks to the Graph API on behalf of every client
 * Page. Ships in the framework, auto-updates with it, but ONLY activates on the install whose wp-config
 * defines the app credentials — everywhere else the class loads dormant and registers nothing.
 *
 * ── Turn a site INTO the FB hub (wp-config.php, above the "stop editing" line) ──
 *   define( 'BP_FB_APP_ID',     '123456789012345' );
 *   define( 'BP_FB_APP_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' ); // ← presence activates the hub
 *   // optional: define( 'BP_FB_GRAPH_VERSION', 'v21.0' );
 *
 * ── In the Meta app settings ──
 *   Add this EXACT string to "Valid OAuth Redirect URIs" (shown on Tools → Facebook):
 *     https://bp-webdev.com/wp-json/bpfb/v1/callback   (or whatever that page prints)
 *
 * Phase 1 = OAuth + read Page reviews (recommendations). DMs (Messenger/Instagram) are a later phase
 * and need different scopes (pages_messaging, instagram_manage_messages) + Webhooks.
 *
 * NOTE: Meta deprecated Page recommendations on the Graph API (v22.0 changelog: error code 12 across
 * ALL versions as of 2025-09-09). The fetch below handles that gracefully so the admin "Test" button
 * shows exactly what the API returns for THIS app's Pages — the definitive answer for our account.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BPFB_Hub {

	const NS         = 'bpfb/v1';
	const OPT_CONN   = 'bpfb_connection';     // { user_token, obtained_at, pages:[{id,name,access_token}] }
	const OPT_STATE  = 'bpfb_oauth_state';    // short-lived CSRF state for the OAuth round-trip
	// Scopes for Phase 1 (reviews). DMs would add pages_messaging / instagram_manage_messages later.
	const SCOPES     = 'pages_show_list,pages_read_engagement,pages_read_user_content,business_management';

	public static function graph_version() {
		return defined( 'BP_FB_GRAPH_VERSION' ) ? (string) BP_FB_GRAPH_VERSION : 'v21.0';
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		// No standalone "Facebook" tab — the Connect controls render inside Tools → Client Reviews.
		add_action( 'admin_post_bpfb_connect', array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_bpfb_disconnect', array( __CLASS__, 'handle_disconnect' ) );
	}

	// Where the OAuth round-trip returns to (now the consolidated Client Reviews screen).
	public static function admin_return_url() { return admin_url( 'tools.php?page=bpgbp-clients' ); }

	/* ─────────────── Config ─────────────── */

	public static function app_id()     { return defined( 'BP_FB_APP_ID' ) ? (string) BP_FB_APP_ID : ''; }
	public static function app_secret() { return defined( 'BP_FB_APP_SECRET' ) ? (string) BP_FB_APP_SECRET : ''; }
	public static function redirect_uri() { return rest_url( self::NS . '/callback' ); }

	public static function connection() {
		$c = get_option( self::OPT_CONN, array() );
		return is_array( $c ) ? $c : array();
	}
	public static function pages() {
		$c = self::connection();
		return ! empty( $c['pages'] ) && is_array( $c['pages'] ) ? $c['pages'] : array();
	}
	public static function page_token( $page_id ) {
		foreach ( self::pages() as $p ) {
			if ( (string) ( $p['id'] ?? '' ) === (string) $page_id ) return (string) ( $p['access_token'] ?? '' );
		}
		return '';
	}

	/* ─────────────── Graph helper ─────────────── */

	/**
	 * GET a Graph API path. Throws on any Graph error with a "(code) message" string — so callers can
	 * surface the EXACT Meta response (e.g. "(12) ... has been deprecated").
	 */
	private static function graph_get( $path, $params, $token ) {
		$params['access_token'] = $token;
		$url = 'https://graph.facebook.com/' . self::graph_version() . '/' . ltrim( $path, '/' )
			. '?' . http_build_query( $params );
		$res = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $res ) ) throw new Exception( $res->get_error_message() );
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $body['error'] ) ) {
			$ec  = $body['error']['code'] ?? $code;
			$msg = $body['error']['message'] ?? 'Unknown Graph error';
			throw new Exception( '(' . $ec . ') ' . $msg );
		}
		if ( $code < 200 || $code >= 300 ) throw new Exception( 'HTTP ' . $code );
		return is_array( $body ) ? $body : array();
	}

	/* ─────────────── OAuth ─────────────── */

	// Step 1: admin clicks "Connect Facebook" → we stash a CSRF state and bounce to Meta's dialog.
	public static function handle_connect() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
		check_admin_referer( 'bpfb_connect' );
		$state = wp_generate_password( 24, false );
		set_transient( self::OPT_STATE, $state, 15 * MINUTE_IN_SECONDS );
		$url = 'https://www.facebook.com/' . self::graph_version() . '/dialog/oauth?' . http_build_query( array(
			'client_id'     => self::app_id(),
			'redirect_uri'  => self::redirect_uri(),
			'state'         => $state,
			'response_type' => 'code',
			'scope'         => self::SCOPES,
		) );
		wp_redirect( $url );
		exit;
	}

	public static function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
		check_admin_referer( 'bpfb_disconnect' );
		delete_option( self::OPT_CONN );
		wp_safe_redirect( add_query_arg( 'disconnected', 1, self::admin_return_url() ) );
		exit;
	}

	public static function register_routes() {
		register_rest_route( self::NS, '/callback', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_callback' ),
			'permission_callback' => '__return_true', // validated by the OAuth `state` below
		) );
	}

	// Step 2: Meta redirects back here with ?code & ?state. Verify state, exchange code → long-lived
	// user token → list the user's Pages (each carries its own long-lived Page token), then store.
	public static function rest_callback( WP_REST_Request $req ) {
		$admin = self::admin_return_url();

		$err = $req->get_param( 'error' );
		if ( $err ) { wp_redirect( add_query_arg( 'fb_error', rawurlencode( (string) ( $req->get_param( 'error_description' ) ?: $err ) ), $admin ) ); exit; }

		$state  = (string) $req->get_param( 'state' );
		$stored = (string) get_transient( self::OPT_STATE );
		if ( ! $state || ! $stored || ! hash_equals( $stored, $state ) ) {
			wp_redirect( add_query_arg( 'fb_error', rawurlencode( 'Security check failed (state mismatch). Try connecting again.' ), $admin ) ); exit;
		}
		delete_transient( self::OPT_STATE );

		$code = (string) $req->get_param( 'code' );
		if ( ! $code ) { wp_redirect( add_query_arg( 'fb_error', rawurlencode( 'No authorization code returned.' ), $admin ) ); exit; }

		try {
			// code → short-lived user token
			$short = self::graph_get( 'oauth/access_token', array(
				'client_id'     => self::app_id(),
				'redirect_uri'  => self::redirect_uri(),
				'client_secret' => self::app_secret(),
				'code'          => $code,
			), '' );
			$user_token = (string) ( $short['access_token'] ?? '' );
			if ( ! $user_token ) throw new Exception( 'No user token returned.' );

			// short-lived → long-lived user token (~60 days)
			$long = self::graph_get( 'oauth/access_token', array(
				'grant_type'        => 'fb_exchange_token',
				'client_id'         => self::app_id(),
				'client_secret'     => self::app_secret(),
				'fb_exchange_token' => $user_token,
			), '' );
			$user_token = (string) ( $long['access_token'] ?? $user_token );

			// list Pages the user manages — each entry includes that Page's own access_token
			$pages = array();
			$accounts = self::graph_get( 'me/accounts', array( 'fields' => 'id,name,access_token,link,website', 'limit' => 200 ), $user_token );
			foreach ( ( $accounts['data'] ?? array() ) as $p ) {
				$pages[] = array(
					'id'           => (string) ( $p['id'] ?? '' ),
					'name'         => (string) ( $p['name'] ?? '' ),
					'access_token' => (string) ( $p['access_token'] ?? '' ),
					'website'      => (string) ( $p['website'] ?? '' ), // the Page's listed external site — used to auto-match clients
					'link'         => (string) ( $p['link'] ?? '' ),    // the facebook.com Page URL
				);
			}
		} catch ( Exception $e ) {
			wp_redirect( add_query_arg( 'fb_error', rawurlencode( $e->getMessage() ), $admin ) ); exit;
		}

		update_option( self::OPT_CONN, array(
			'user_token'  => $user_token,
			'obtained_at' => current_time( 'mysql' ),
			'pages'       => $pages,
		) );

		wp_redirect( add_query_arg( 'connected', count( $pages ), $admin ) );
		exit;
	}

	/* ─────────────── Reviews (recommendations) ─────────────── */

	/**
	 * Fetch a Page's recommendations, normalized to the shared review shape (source=facebook). Throws
	 * on Graph error (incl. the deprecation error 12) so the caller can show the raw message.
	 */
	public static function fetch_reviews( $page_id, $limit = 50 ) {
		$token = self::page_token( $page_id );
		if ( ! $token ) throw new Exception( 'No access token stored for that Page — reconnect.' );
		$resp = self::graph_get( (string) $page_id . '/ratings', array(
			'fields' => 'created_time,recommendation_type,review_text,reviewer{name,id}',
			'limit'  => max( 1, (int) $limit ),
		), $token );
		$out = array();
		foreach ( ( $resp['data'] ?? array() ) as $r ) $out[] = self::normalize_rating( $r );
		return $out;
	}

	public static function normalize_rating( $r ) {
		$type = (string) ( $r['recommendation_type'] ?? '' ); // positive | negative
		return array(
			'source'     => 'facebook',
			'rating'     => $type === 'positive' ? 5 : ( $type === 'negative' ? 1 : 0 ), // positive=5★, negative=1★
			'recommends' => $type,
			'comment'    => (string) ( $r['review_text'] ?? '' ),
			'author'     => (string) ( $r['reviewer']['name'] ?? 'Facebook user' ),
			'createTime' => (string) ( $r['created_time'] ?? '' ),
		);
	}

	/* ─────────────── Facebook connection controls (rendered inside Tools → Client Reviews) ─────────────── */

	// A compact Connect / Reconnect / Disconnect block, embedded at the top of the Client Reviews screen.
	// No Pages list — Page IDs live in the client registry's Facebook column now.
	public static function render_connection_controls() {
		// Flashes from the OAuth round-trip.
		if ( isset( $_GET['connected'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Facebook connected — linked <strong>' . (int) $_GET['connected'] . '</strong> Page(s).</p></div>';
		}
		if ( isset( $_GET['disconnected'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Facebook disconnected.</p></div>';
		if ( isset( $_GET['fb_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Facebook said:</strong> ' . esc_html( wp_unslash( $_GET['fb_error'] ) ) . '</p></div>';
		}

		$pages = self::pages();
		$count = count( $pages );

		echo '<div style="margin:6px 0 18px;padding:12px 14px;border:1px solid #dcdcde;border-radius:6px;background:#fff;max-width:1000px">';
		echo '<strong>Facebook:</strong> ' . ( $pages ? esc_html( $count . ' Page' . ( 1 === $count ? '' : 's' ) . ' connected.' ) : 'not connected.' ) . ' ';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 6px">';
		wp_nonce_field( 'bpfb_connect' );
		echo '<input type="hidden" name="action" value="bpfb_connect">';
		echo '<button class="button button-primary button-small">' . ( $pages ? 'Reconnect Facebook' : 'Connect Facebook' ) . '</button>';
		echo '</form>';
		if ( $pages ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block">';
			wp_nonce_field( 'bpfb_disconnect' );
			echo '<input type="hidden" name="action" value="bpfb_disconnect">';
			echo '<button class="button button-small">Disconnect</button>';
			echo '</form>';
		}
		echo '<p class="description" style="margin:8px 0 0">Redirect URI for your Meta app (Facebook Login → Settings → Valid OAuth Redirect URIs): <code>' . esc_html( self::redirect_uri() ) . '</code></p>';
		echo '</div>';
	}
}

// Single-site role: only the install whose wp-config carries the Meta app credentials becomes the FB
// hub and registers its routes/admin page. Everywhere else the class loads dormant.
if ( BPFB_Hub::app_id() && BPFB_Hub::app_secret() ) {
	BPFB_Hub::init();
}
