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
	// OAuth scopes: reviews (pages_read_*) + Facebook Messenger DMs. Instagram DMs are NOT handled through
	// this Facebook-Login flow — the Meta app is configured for the NEW Instagram API ("Instagram Login",
	// instagram_business_* perms + Instagram-native tokens), which is a separate token flow, not FB Page
	// tokens. Do NOT add instagram_* scopes here (Meta will reject the OAuth and break the working FB/reviews
	// connection). IG is wired separately once we adopt that API. Still filterable via `bpfb_oauth_scopes`.
	const SCOPES     = 'pages_show_list,pages_read_engagement,pages_read_user_content,business_management,pages_messaging,pages_manage_metadata';
	const OPT_EVENTS = 'bpfb_webhook_events'; // small ring buffer of recent webhook events (debug/verify)

	public static function oauth_scopes() { return (string) apply_filters( 'bpfb_oauth_scopes', self::SCOPES ); }

	public static function graph_version() {
		return defined( 'BP_FB_GRAPH_VERSION' ) ? (string) BP_FB_GRAPH_VERSION : 'v21.0';
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		// No standalone "Facebook" tab — the Connect controls render inside Tools → Client Reviews.
		add_action( 'admin_post_bpfb_connect', array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_bpfb_disconnect', array( __CLASS__, 'handle_disconnect' ) );
		add_action( 'admin_post_bpfb_subscribe', array( __CLASS__, 'handle_subscribe' ) );
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

	/**
	 * Current follower count for a Page — `followers_count`, falling back to the legacy `fan_count` (likes).
	 * Returns null on no token / Graph error / zero, so callers can leave the last known value untouched.
	 */
	public static function followers_count( $page_id ) {
		$page_id = (string) $page_id;
		$token   = self::page_token( $page_id );
		if ( '' === $page_id || '' === $token ) return null;
		try {
			$data = self::graph_get( $page_id, array( 'fields' => 'followers_count,fan_count' ), $token );
		} catch ( Exception $e ) {
			error_log( 'BPFB followers_count (' . $page_id . '): ' . $e->getMessage() );
			return null;
		}
		$n = (int) ( $data['followers_count'] ?? $data['fan_count'] ?? 0 );
		return $n > 0 ? $n : null;
	}

	/* ─────────────── Graph helper ─────────────── */

	/**
	 * GET a Graph API path. Throws on any Graph error with a "(code) message" string — so callers can
	 * surface the EXACT Meta response (e.g. "(12) ... has been deprecated").
	 */
	private static function graph_get( $path, $params, $token, $timeout = 20 ) {
		$params['access_token'] = $token;
		$url = 'https://graph.facebook.com/' . self::graph_version() . '/' . ltrim( $path, '/' )
			. '?' . http_build_query( $params );
		$res = wp_remote_get( $url, array( 'timeout' => (int) $timeout ) );
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

	/** POST to a Graph path (form-encoded). Throws on Graph error, same contract as graph_get(). */
	private static function graph_post( $path, $params, $token ) {
		$params['access_token'] = $token;
		$url = 'https://graph.facebook.com/' . self::graph_version() . '/' . ltrim( $path, '/' );
		$res = wp_remote_post( $url, array( 'timeout' => 20, 'body' => $params ) );
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

	/* ─────────────── Messaging: webhooks + send (Messenger + Instagram DMs) ─────────────── */

	public static function webhook_url() { return rest_url( self::NS . '/webhook' ); }

	// The verify token you ALSO paste into Meta's webhook config. Auto-generated once; override with a
	// constant define( 'BP_FB_WEBHOOK_TOKEN', '...' ) if you prefer.
	public static function webhook_token() {
		if ( defined( 'BP_FB_WEBHOOK_TOKEN' ) && BP_FB_WEBHOOK_TOKEN ) return (string) BP_FB_WEBHOOK_TOKEN;
		$t = get_option( 'bpfb_webhook_token' );
		if ( ! $t ) { $t = wp_generate_password( 24, false ); update_option( 'bpfb_webhook_token', $t ); }
		return (string) $t;
	}

	// GET: Meta's one-time subscription handshake. Echo the bare challenge back.
	public static function rest_webhook_verify( WP_REST_Request $req ) {
		$mode      = (string) $req->get_param( 'hub_mode' );          // 'hub.mode' → hub_mode (PHP dots→underscores)
		$token     = (string) $req->get_param( 'hub_verify_token' );
		$challenge = (string) $req->get_param( 'hub_challenge' );
		if ( 'subscribe' === $mode && hash_equals( self::webhook_token(), $token ) ) {
			status_header( 200 );
			header( 'Content-Type: text/plain' );
			echo $challenge; // Meta expects the raw value, not JSON
			exit;
		}
		// Plain visit (no verification params): return a tiny status so we can confirm the LIVE code +
		// how many webhook events have been stored. Open the webhook URL in a browser to see this.
		if ( '' === $mode ) {
			return new WP_REST_Response( array(
				'bpfb'   => 'webhook',
				'build'  => 'dm-debug-3',
				'events' => count( self::recent_events() ),
			), 200 );
		}
		return new WP_Error( 'bpfb_verify_failed', 'Verification failed.', array( 'status' => 403 ) );
	}

	// POST: message events. Verify X-Hub-Signature-256 over the RAW body, parse, store (Stage 2 forwards).
	public static function rest_webhook_receive( WP_REST_Request $req ) {
		$raw      = $req->get_body();
		$sig      = (string) $req->get_header( 'x-hub-signature-256' );
		$expected = 'sha256=' . hash_hmac( 'sha256', $raw, self::app_secret() );
		$sig_ok   = $sig && hash_equals( $expected, $sig );

		// Ground-truth log (independent of any option/cache) so we can confirm Meta is hitting us at all.
		error_log( '[bpfb webhook] POST len=' . strlen( (string) $raw ) . ' sig=' . ( $sig ? 'present' : 'none' ) . ' sig_ok=' . ( $sig_ok ? '1' : '0' ) );

		// DEBUG: record every POST hit (with signature status) so the admin table proves whether Meta is
		// reaching us and whether the app-secret signature matches. Remove once messaging is confirmed.
		self::store_event( array(
			'platform' => 'webhook-hit',
			'page_id'  => $sig_ok ? 'sig OK' : ( $sig ? 'SIG MISMATCH' : 'no sig header' ),
			'sender'   => '',
			'text'     => substr( (string) $raw, 0, 300 ),
		) );

		if ( ! $sig_ok ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 ); // 200 so Meta won't retry-storm; ignored
		}

		$data     = json_decode( $raw, true );
		$object   = is_array( $data ) ? (string) ( $data['object'] ?? '' ) : '';
		$platform = ( 'instagram' === $object ) ? 'instagram' : 'facebook';

		foreach ( ( $data['entry'] ?? array() ) as $entry ) {
			$page_id = (string) ( $entry['id'] ?? '' );
			foreach ( ( $entry['messaging'] ?? array() ) as $m ) {
				if ( empty( $m['message'] ) ) continue; // skip delivery/read receipts for now
				$ev = array(
					'platform' => $platform,
					'page_id'  => $page_id,
					'sender'   => (string) ( $m['sender']['id'] ?? '' ),
					'text'     => (string) ( $m['message']['text'] ?? '' ),
					'mid'      => (string) ( $m['message']['mid'] ?? '' ),
					'at'       => (int) ( $m['timestamp'] ?? 0 ),
					'echo'     => ! empty( $m['message']['is_echo'] ),
				);
				self::store_event( $ev );
				if ( empty( $ev['echo'] ) ) self::forward_message( $ev ); // route → Site Pulse inbox
			}
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	// Keep the last ~50 events for admin verification (debug only — the real inbox lives in Site Pulse).
	private static function store_event( array $ev ) {
		$ev['stored_at'] = current_time( 'mysql' );
		$list = get_option( self::OPT_EVENTS, array() );
		if ( ! is_array( $list ) ) $list = array();
		array_unshift( $list, $ev );
		update_option( self::OPT_EVENTS, array_slice( $list, 0, 50 ) );
	}
	public static function recent_events() {
		$l = get_option( self::OPT_EVENTS, array() );
		return is_array( $l ) ? $l : array();
	}

	// Subscribe a Page to the app's webhooks so its messages start flowing to /webhook.
	public static function subscribe_page( $page_id ) {
		$token = self::page_token( $page_id );
		if ( ! $token ) throw new Exception( 'No token for page ' . $page_id );
		return self::graph_post( $page_id . '/subscribed_apps', array(
			'subscribed_fields' => 'messages,messaging_postbacks,message_reactions,messaging_referrals',
		), $token );
	}

	public static function handle_subscribe() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
		check_admin_referer( 'bpfb_subscribe' );
		@set_time_limit( 120 );

		// Only subscribe Pages MAPPED to a client (those with a Facebook Page ID in the registry). Looping
		// over every connected Page is dozens of Graph calls and times out — and we only need DMs for the
		// Pages we actually manage.
		$mapped = array();
		if ( class_exists( 'BPGBP_Hub' ) && method_exists( 'BPGBP_Hub', 'get_site_map' ) ) {
			foreach ( BPGBP_Hub::get_site_map() as $cfg ) {
				$fp = (string) ( $cfg['facebook_page_id'] ?? '' );
				if ( '' !== $fp ) $mapped[ $fp ] = true;
			}
		}

		$ok = 0; $err = '';
		foreach ( self::pages() as $p ) {
			$pid = (string) ( $p['id'] ?? '' );
			if ( empty( $mapped[ $pid ] ) ) continue; // skip Pages not mapped to a client
			try { self::subscribe_page( $pid ); $ok++; }
			catch ( Exception $e ) { $err = $e->getMessage(); }
		}
		$args = $err ? array( 'fb_error' => rawurlencode( $err ) ) : array( 'subscribed' => $ok );
		wp_safe_redirect( add_query_arg( $args, self::admin_return_url() ) );
		exit;
	}

	// Send a reply to a customer. Same endpoint for FB + IG. RESPONSE = inside the 24h window; pass a $tag
	// (e.g. HUMAN_AGENT) to message outside it. Returns the Graph response or throws.
	public static function send_message( $page_id, $recipient_id, $text, $tag = '' ) {
		$token = self::page_token( $page_id );
		if ( ! $token ) throw new Exception( 'No token for page ' . $page_id );
		$body = array(
			'recipient'      => wp_json_encode( array( 'id' => (string) $recipient_id ) ),
			'message'        => wp_json_encode( array( 'text' => (string) $text ) ),
			'messaging_type' => $tag ? 'MESSAGE_TAG' : 'RESPONSE',
		);
		if ( $tag ) $body['tag'] = $tag;
		return self::graph_post( $page_id . '/messages', $body, $token );
	}

	/* ─────────────── DM routing → Site Pulse + reply relay ─────────────── */

	const OPT_ROUTES = 'bpfb_dm_routes'; // { page_id: site_key } overrides; default = registry FB-page match

	// The registry client a Page's DMs route to: explicit override, else the client whose facebook_page_id
	// matches that Page. Returns [ site_key, site_url, secret, label ] or null.
	public static function route_for_page( $page_id ) {
		if ( ! class_exists( 'BPGBP_Hub' ) || ! method_exists( 'BPGBP_Hub', 'get_site_map' ) ) return null;
		$sites  = BPGBP_Hub::get_site_map();
		$routes = get_option( self::OPT_ROUTES, array() );
		$key    = ( is_array( $routes ) && ! empty( $routes[ $page_id ] ) ) ? (string) $routes[ $page_id ] : '';
		if ( '' === $key ) {
			foreach ( $sites as $sk => $cfg ) {
				if ( (string) ( $cfg['facebook_page_id'] ?? '' ) === (string) $page_id ) { $key = (string) $sk; break; }
			}
		}
		if ( '' === $key || empty( $sites[ $key ] ) ) return null;
		$cfg = $sites[ $key ];
		return array(
			'site_key' => $key,
			'site_url' => ! empty( $cfg['site_url'] ) ? rtrim( (string) $cfg['site_url'], '/' ) : '',
			'secret'   => (string) ( $cfg['secret'] ?? '' ),
			'label'    => (string) ( $cfg['label'] ?? $key ),
		);
	}

	// Best-effort sender display name (FB: name; IG: name/username). Empty if Meta won't share it.
	public static function sender_name( $page_id, $sender_id, $platform ) {
		$token = self::page_token( $page_id );
		if ( ! $token || ! $sender_id ) return '';
		try {
			$fields = ( 'instagram' === $platform ) ? 'name,username' : 'name';
			$r = self::graph_get( (string) $sender_id, array( 'fields' => $fields ), $token );
			return (string) ( $r['name'] ?? $r['username'] ?? '' );
		} catch ( Exception $e ) { return ''; }
	}

	// Forward one inbound message to its routed Site Pulse site (signed with that site's registry secret —
	// the same HMAC scheme as the testimonial push). Silent no-op if the Page isn't routed/configured.
	public static function forward_message( array $ev ) {
		$route = self::route_for_page( (string) ( $ev['page_id'] ?? '' ) );
		if ( ! $route || '' === $route['site_url'] || '' === $route['secret'] ) return;

		$page_name = '';
		foreach ( self::pages() as $p ) { if ( (string) $p['id'] === (string) $ev['page_id'] ) { $page_name = (string) ( $p['name'] ?? '' ); break; } }

		$payload = array(
			'platform'    => (string) ( $ev['platform'] ?? 'facebook' ),
			'page_id'     => (string) ( $ev['page_id'] ?? '' ),
			'page_name'   => $page_name,
			'sender_id'   => (string) ( $ev['sender'] ?? '' ),
			'sender_name' => self::sender_name( (string) $ev['page_id'], (string) ( $ev['sender'] ?? '' ), (string) ( $ev['platform'] ?? '' ) ),
			'text'        => (string) ( $ev['text'] ?? '' ),
			'mid'         => (string) ( $ev['mid'] ?? '' ),
			'at'          => (int) ( $ev['at'] ?? 0 ),
		);
		$raw = wp_json_encode( $payload );
		$ts  = (string) time();
		$sig = hash_hmac( 'sha256', $ts . '.' . $raw, $route['secret'] );
		wp_remote_post( $route['site_url'] . '/?rest_route=/bpfb-dm/v1/message', array(
			'timeout' => 15,
			'headers' => array(
				'X-BPGBP-Site'      => $route['site_key'],
				'X-BPGBP-Timestamp' => $ts,
				'X-BPGBP-Signature' => $sig,
				'Content-Type'      => 'application/json',
			),
			'body'    => $raw,
		) );
	}

	// Reply relay: a Site Pulse site POSTs here (signed with its registry secret) to send a reply out via
	// the Page token the hub holds. Verifies the caller owns that Page's route.
	public static function rest_send( WP_REST_Request $req ) {
		$raw  = $req->get_body();
		$site = (string) $req->get_header( 'x-bpgbp-site' );
		$ts   = (string) $req->get_header( 'x-bpgbp-timestamp' );
		$sig  = (string) $req->get_header( 'x-bpgbp-signature' );
		if ( ! $site || ! $ts || abs( time() - (int) $ts ) > 300 ) return new WP_Error( 'bpfb_send_auth', 'Auth failed.', array( 'status' => 401 ) );

		$sites  = ( class_exists( 'BPGBP_Hub' ) && method_exists( 'BPGBP_Hub', 'get_site_map' ) ) ? BPGBP_Hub::get_site_map() : array();
		$secret = (string) ( $sites[ $site ]['secret'] ?? '' );
		if ( '' === $secret || ! hash_equals( hash_hmac( 'sha256', $ts . '.' . $raw, $secret ), $sig ) ) {
			return new WP_Error( 'bpfb_send_sig', 'Signature mismatch.', array( 'status' => 403 ) );
		}

		$d       = json_decode( $raw, true );
		$page_id = (string) ( $d['page_id'] ?? '' );
		$to      = (string) ( $d['recipient_id'] ?? '' );
		$text    = (string) ( $d['text'] ?? '' );
		if ( ! $page_id || ! $to || '' === $text ) return new WP_Error( 'bpfb_send_bad', 'Missing fields.', array( 'status' => 400 ) );

		// The Page must route to the calling site (a site can't send as another site's Page).
		$route = self::route_for_page( $page_id );
		if ( ! $route || $route['site_key'] !== $site ) return new WP_Error( 'bpfb_send_forbidden', 'Page not routed to this site.', array( 'status' => 403 ) );

		try {
			$res = self::send_message( $page_id, $to, $text );
			return new WP_REST_Response( array( 'ok' => true, 'mid' => (string) ( $res['message_id'] ?? '' ) ), 200 );
		} catch ( Exception $e ) {
			return new WP_Error( 'bpfb_send_failed', $e->getMessage(), array( 'status' => 502 ) );
		}
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
			'scope'         => self::oauth_scopes(),
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
		// Messenger / Instagram webhook: GET = Meta's subscription verification, POST = message events.
		register_rest_route( self::NS, '/webhook', array(
			array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'rest_webhook_verify' ),  'permission_callback' => '__return_true' ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_webhook_receive' ), 'permission_callback' => '__return_true' ),
		) );
		// Reply relay: Site Pulse sites POST here (HMAC-signed) to send a reply out through the Page token.
		register_rest_route( self::NS, '/send', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_send' ),
			'permission_callback' => '__return_true', // HMAC-verified inside
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
		// 8s, not the default 20: the agency Refresh pulls this per client, and a dead/slow Page token
		// should fail fast so it only briefly slows its own client instead of dragging the sweep.
		$resp = self::graph_get( (string) $page_id . '/ratings', array(
			'fields' => 'created_time,recommendation_type,review_text,reviewer{name,id}',
			'limit'  => max( 1, (int) $limit ),
		), $token, 8 );
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
		if ( isset( $_GET['subscribed'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Subscribed <strong>' . (int) $_GET['subscribed'] . '</strong> Page(s) to messaging webhooks.</p></div>';
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

		// ── Messaging (Messenger + Instagram DMs) ──
		echo '<div style="margin:6px 0 18px;padding:12px 14px;border:1px solid #dcdcde;border-radius:6px;background:#fff;max-width:1000px">';
		echo '<strong>Messaging (DMs):</strong> the inbox lives in Site Pulse — this just wires the Meta webhook + lets Pages subscribe.';
		echo '<p class="description" style="margin:8px 0 2px">In your Meta app → <em>Webhooks</em> (and the Messenger / Instagram products), use:</p>';
		echo '<table class="widefat striped" style="max-width:900px;margin:4px 0 10px"><tbody>';
		echo '<tr><td style="width:160px"><strong>Callback URL</strong></td><td><code>' . esc_html( self::webhook_url() ) . '</code></td></tr>';
		echo '<tr><td><strong>Verify token</strong></td><td><code>' . esc_html( self::webhook_token() ) . '</code></td></tr>';
		echo '<tr><td><strong>Subscribe fields</strong></td><td><code>messages, messaging_postbacks</code> (Page) &amp; the Instagram <code>messages</code> field</td></tr>';
		echo '</tbody></table>';
		if ( $pages ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block">';
			wp_nonce_field( 'bpfb_subscribe' );
			echo '<input type="hidden" name="action" value="bpfb_subscribe">';
			echo '<button class="button button-small">Subscribe mapped Pages to messaging</button> <span class="description">Subscribes only Pages mapped to a client (the Facebook column). Run after the webhook verifies green.</span>';
			echo '</form>';
		}
		$events = self::recent_events();
		if ( $events ) {
			echo '<p class="description" style="margin:12px 0 4px"><strong>Recent webhook messages</strong> (debug — newest first):</p>';
			echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>When</th><th>Platform</th><th>Page</th><th>Sender</th><th>Text</th></tr></thead><tbody>';
			foreach ( array_slice( $events, 0, 10 ) as $ev ) {
				echo '<tr><td>' . esc_html( $ev['stored_at'] ?? '' ) . '</td><td>' . esc_html( $ev['platform'] ?? '' ) . '</td><td><code>' . esc_html( $ev['page_id'] ?? '' ) . '</code></td><td><code>' . esc_html( $ev['sender'] ?? '' ) . '</code></td><td>' . esc_html( mb_substr( (string) ( $ev['text'] ?? '' ), 0, 120 ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}

// Single-site role: only the install whose wp-config carries the Meta app credentials becomes the FB
// hub and registers its routes/admin page. Everywhere else the class loads dormant.
if ( BPFB_Hub::app_id() && BPFB_Hub::app_secret() ) {
	BPFB_Hub::init();
}
