<?php
require_once get_template_directory() . '/includes/includes-customer-connect-pwa.php';
require_once get_template_directory() . '/includes/includes-customer-connect-equipment.php';
require_once get_template_directory() . '/includes/includes-customer-connect-push.php';

/* Battle Plan Web Design — Customer Connect
   ---------------------------------------------------------------------------
   A PUBLIC-facing, white-labeled companion app for a client's *customers*
   (e.g. the homeowners an HVAC contractor serves). Installs per-site like
   Site Pulse, deploys on the client's own website, and is completely
   INDEPENDENT of Site Pulse — a site may run either, both, or neither with no
   cross-dependency. Any shared primitives (VAPID push crypto, OKLCH color
   engine) are COPIED into this module (namespaced cc_*), never required from
   Site Pulse, so Customer Connect loads and runs whether or not Site Pulse is on.

   Audience separation: customers live in their OWN tables (never wp_users) and
   authenticate with a one-time code (SMS where Twilio is configured, email
   otherwise) that mints a long-lived, HMAC-signed bearer token the PWA stores
   on the device. OTP is once-per-device, not once-per-session.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Config Helpers
# Secret + Token Auth
# Database (tables + versioned upgrade)
# Customer Helpers
# OTP: request + verify (SMS/email)
# REST API (customer-connect/v1)
# Front-end App Page (auto-create + chrome strip + assets)
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CUSTOMER_CONNECT_DB_VERSION', '1.1' );

// Front-end app slug (a `universal` CPT post auto-created below). One page; the
// PWA is a client-side SPA that talks to the REST API, so login/app are one URL.
if ( ! defined( 'CUSTOMER_CONNECT_SLUG' ) ) define( 'CUSTOMER_CONNECT_SLUG', 'customer-connect' );


/*--------------------------------------------------------------
# Config Helpers
--------------------------------------------------------------*/

/**
 * The whole module config (the `customer_connect` site_option array), set in
 * functions-site.php via battleplan_updateSiteOptions() / update_option().
 */
function cc_config(): array {
	return get_option( 'customer_connect', [] );
}

/**
 * Read a single config value with a default. Keys are flat option keys.
 */
function cc_get( string $key, $default = '' ) {
	// Prefer a Site Pulse setting (the staff-editable source of truth, Settings → Customer Connect)
	// when present; otherwise fall back to the functions-site.php install option, then the default.
	if ( function_exists( 'site_pulse_get_setting' ) ) {
		$sp = site_pulse_get_setting( 'cc_' . $key, '' );
		if ( $sp !== '' ) return $sp;
	}
	$cfg = cc_config();
	$val = $cfg[ $key ] ?? $default;
	return $val === '' ? $default : $val;
}

/** Customer-facing product name (white-label; each client overrides). */
function cc_app_name(): string {
	return (string) cc_get( 'app_name', 'Customer Connect' );
}

/** The contractor's business name shown under the app name. */
function cc_company_name(): string {
	return (string) cc_get( 'company_name', get_bloginfo( 'name' ) );
}

/**
 * Anthropic key — reuse the framework helper if present, else the constants.
 * (Used by the AI troubleshooter phase; defined here so all phases share it.)
 */
function cc_api_key(): string {
	if ( function_exists( 'bp_ai_alt_api_key' ) ) return bp_ai_alt_api_key();
	if ( defined( 'BP_ANTHROPIC_API_KEY' ) && BP_ANTHROPIC_API_KEY ) return BP_ANTHROPIC_API_KEY;
	if ( defined( 'ANTHROPIC_API_KEY' )    && ANTHROPIC_API_KEY )    return ANTHROPIC_API_KEY;
	return '';
}

/** Prefixed table name: {prefix}customer_connect_{name}. */
function cc_table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'customer_connect_' . $name;
}


/*--------------------------------------------------------------
# Secret + Token Auth
--------------------------------------------------------------*/

/**
 * Signing secret for customer bearer tokens + OTP hashing. Prefers a
 * wp-config.php constant (CUSTOMER_CONNECT_SECRET); otherwise a persisted random
 * secret so the module works with zero wp-config edits. Rotating the secret
 * invalidates every outstanding token (customers just re-verify once).
 */
function cc_secret(): string {
	if ( defined( 'CUSTOMER_CONNECT_SECRET' ) && CUSTOMER_CONNECT_SECRET ) return (string) CUSTOMER_CONNECT_SECRET;
	$s = get_option( 'customer_connect_secret', '' );
	if ( $s === '' ) {
		$s = wp_generate_password( 64, true, true );
		update_option( 'customer_connect_secret', $s, false );
	}
	return $s;
}

/**
 * Mint a bearer token for a customer id. Long-lived (90 days) — the PWA stores
 * it on the device and silently renews on each open, so OTP is once-per-device.
 */
function cc_generate_token( int $customer_id ): string {
	$expires = time() + ( 90 * 24 * 60 * 60 );
	$data    = $customer_id . '|' . $expires;
	$sig     = hash_hmac( 'sha256', $data, cc_secret() );
	return base64_encode( $data . '|' . $sig );
}

/**
 * Verify a bearer token → the customer row (array) or false. Rejects expired,
 * tampered, blocked, or unknown-customer tokens.
 */
function cc_verify_token( string $token ) {
	if ( $token === '' ) return false;
	$decoded = base64_decode( $token, true );
	if ( $decoded === false ) return false;
	$parts = explode( '|', $decoded );
	if ( count( $parts ) !== 3 ) return false;
	[ $customer_id, $expires, $sig ] = $parts;
	if ( time() > (int) $expires ) return false;
	$expected = hash_hmac( 'sha256', $customer_id . '|' . $expires, cc_secret() );
	if ( ! hash_equals( $expected, $sig ) ) return false;
	$customer = cc_get_customer( (int) $customer_id );
	if ( ! $customer || $customer['status'] !== 'active' ) return false;
	return $customer;
}

/**
 * Resolve the authenticated customer for a REST request from the X-CC-Token
 * header (or an Authorization: Bearer fallback). Returns the customer row or null.
 */
function cc_current_customer( WP_REST_Request $request ) {
	$token = (string) $request->get_header( 'X-CC-Token' );
	if ( $token === '' ) {
		$auth = (string) $request->get_header( 'Authorization' );
		if ( stripos( $auth, 'Bearer ' ) === 0 ) $token = trim( substr( $auth, 7 ) );
	}
	$customer = cc_verify_token( $token );
	return $customer ?: null;
}


/*--------------------------------------------------------------
# Database (tables + versioned upgrade)
--------------------------------------------------------------*/

/**
 * Create/upgrade all Customer Connect tables. Bump CUSTOMER_CONNECT_DB_VERSION to ship a
 * schema change; the init hook below diffs and re-runs dbDelta.
 */
function cc_install_db(): void {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	global $wpdb;
	$charset = $wpdb->get_charset_collate();

	$customers = cc_table( 'customers' );
	$otps      = cc_table( 'otps' );
	$equipment = cc_table( 'equipment' );
	$subs      = cc_table( 'push_subscriptions' );
	$requests  = cc_table( 'schedule_requests' );
	$notifs    = cc_table( 'notifications' );
	$ai        = cc_table( 'ai_messages' );

	$sql = "
	CREATE TABLE $customers (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		phone VARCHAR(20) NOT NULL DEFAULT '',
		email VARCHAR(190) NOT NULL DEFAULT '',
		first_name VARCHAR(100) NOT NULL DEFAULT '',
		last_name VARCHAR(100) NOT NULL DEFAULT '',
		address VARCHAR(255) NOT NULL DEFAULT '',
		city VARCHAR(100) NOT NULL DEFAULT '',
		state VARCHAR(20) NOT NULL DEFAULT '',
		zip VARCHAR(20) NOT NULL DEFAULT '',
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		crm_source VARCHAR(40) NOT NULL DEFAULT '',
		crm_id VARCHAR(100) NOT NULL DEFAULT '',
		meta LONGTEXT NULL,
		created_at DATETIME NULL,
		last_login_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY phone (phone),
		KEY email (email),
		KEY crm (crm_source,crm_id)
	) $charset;

	CREATE TABLE $otps (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		identifier VARCHAR(190) NOT NULL DEFAULT '',
		channel VARCHAR(10) NOT NULL DEFAULT 'sms',
		code_hash CHAR(64) NOT NULL DEFAULT '',
		attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
		ip VARCHAR(45) NOT NULL DEFAULT '',
		expires_at DATETIME NULL,
		created_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY identifier (identifier)
	) $charset;

	CREATE TABLE $equipment (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		type VARCHAR(60) NOT NULL DEFAULT '',
		brand VARCHAR(80) NOT NULL DEFAULT '',
		model VARCHAR(120) NOT NULL DEFAULT '',
		serial VARCHAR(120) NOT NULL DEFAULT '',
		install_year SMALLINT UNSIGNED NULL,
		filter_size VARCHAR(60) NOT NULL DEFAULT '',
		filter_changed_at DATE NULL,
		filter_interval_days SMALLINT UNSIGNED NULL,
		location_label VARCHAR(120) NOT NULL DEFAULT '',
		notes TEXT NULL,
		last_service_at DATE NULL,
		created_at DATETIME NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY customer_id (customer_id)
	) $charset;

	CREATE TABLE $subs (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		endpoint TEXT NOT NULL,
		endpoint_hash CHAR(64) NOT NULL DEFAULT '',
		p256dh VARCHAR(255) NOT NULL DEFAULT '',
		auth VARCHAR(255) NOT NULL DEFAULT '',
		ua VARCHAR(255) NOT NULL DEFAULT '',
		created_at DATETIME NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY endpoint_hash (endpoint_hash),
		KEY customer_id (customer_id)
	) $charset;

	CREATE TABLE $requests (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		type VARCHAR(60) NOT NULL DEFAULT '',
		urgency VARCHAR(20) NOT NULL DEFAULT 'normal',
		preferred_date DATE NULL,
		preferred_window VARCHAR(40) NOT NULL DEFAULT '',
		message TEXT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'new',
		fsm_source VARCHAR(40) NOT NULL DEFAULT '',
		fsm_ref VARCHAR(100) NOT NULL DEFAULT '',
		created_at DATETIME NULL,
		updated_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY customer_id (customer_id),
		KEY status (status)
	) $charset;

	CREATE TABLE $notifs (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		title VARCHAR(190) NOT NULL DEFAULT '',
		body TEXT NULL,
		url VARCHAR(255) NOT NULL DEFAULT '',
		channel VARCHAR(20) NOT NULL DEFAULT 'push',
		read_at DATETIME NULL,
		sent_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY customer_id (customer_id)
	) $charset;

	CREATE TABLE $ai (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		thread VARCHAR(64) NOT NULL DEFAULT '',
		role VARCHAR(12) NOT NULL DEFAULT 'user',
		content LONGTEXT NULL,
		created_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY customer_thread (customer_id,thread)
	) $charset;
	";

	dbDelta( $sql );
	update_option( 'customer_connect_db_version', CUSTOMER_CONNECT_DB_VERSION );
}

// Run install/upgrade when the stored version lags the constant.
add_action( 'init', function () {
	if ( get_option( 'customer_connect_db_version' ) !== CUSTOMER_CONNECT_DB_VERSION ) {
		cc_install_db();
	}
}, 5 );

// Opportunistic OTP cleanup — drop expired codes on the housekeeping-ish cadence
// (cheap, delete-by-index; keeps the tiny otps table from growing).
add_action( 'init', function () {
	if ( wp_rand( 1, 50 ) !== 1 ) return; // ~2% of loads
	global $wpdb;
	$t = cc_table( 'otps' );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE expires_at < %s", gmdate( 'Y-m-d H:i:s', time() - 3600 ) ) );
}, 20 );


/*--------------------------------------------------------------
# Customer Helpers
--------------------------------------------------------------*/

/** Normalize a US phone to E.164-ish digits (+1XXXXXXXXXX). Returns '' if unusable. */
function cc_normalize_phone( string $raw ): string {
	$digits = preg_replace( '/\D+/', '', $raw );
	if ( $digits === '' ) return '';
	if ( strlen( $digits ) === 10 ) $digits = '1' . $digits;
	if ( strlen( $digits ) === 11 && $digits[0] === '1' ) return '+' . $digits;
	return '';
}

function cc_get_customer( int $id ) {
	global $wpdb;
	$t   = cc_table( 'customers' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

/** Find a customer by phone (preferred) or email. Returns row or null. */
function cc_find_customer( string $phone, string $email ) {
	global $wpdb;
	$t = cc_table( 'customers' );
	if ( $phone !== '' ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE phone = %s LIMIT 1", $phone ), ARRAY_A );
		if ( $row ) return $row;
	}
	if ( $email !== '' ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE email = %s LIMIT 1", $email ), ARRAY_A );
		if ( $row ) return $row;
	}
	return null;
}

/**
 * Find-or-create a customer by phone/email. New rows are 'active' (self-register
 * base). The optional per-client CRM connector add-on can later enrich these.
 */
function cc_upsert_customer( string $phone, string $email ): array {
	global $wpdb;
	$existing = cc_find_customer( $phone, $email );
	if ( $existing ) {
		// Backfill a missing channel (e.g. verified by SMS first, later added email).
		$patch = [];
		if ( $phone !== '' && $existing['phone'] === '' ) $patch['phone'] = $phone;
		if ( $email !== '' && $existing['email'] === '' ) $patch['email'] = $email;
		if ( $patch ) {
			$wpdb->update( cc_table( 'customers' ), $patch, [ 'id' => (int) $existing['id'] ] );
			$existing = array_merge( $existing, $patch );
		}
		return $existing;
	}
	$now = current_time( 'mysql' );
	$wpdb->insert( cc_table( 'customers' ), [
		'phone'      => $phone,
		'email'      => $email,
		'status'     => 'active',
		'created_at' => $now,
	] );
	return cc_get_customer( (int) $wpdb->insert_id );
}

/** Public-safe customer shape returned to the app (no internal/meta fields). */
function cc_customer_public( array $c ): array {
	return [
		'id'         => (int) $c['id'],
		'phone'      => $c['phone'],
		'email'      => $c['email'],
		'first_name' => $c['first_name'],
		'last_name'  => $c['last_name'],
		'address'    => $c['address'],
		'city'       => $c['city'],
		'state'      => $c['state'],
		'zip'        => $c['zip'],
	];
}


/*--------------------------------------------------------------
# OTP: request + verify (SMS/email)
--------------------------------------------------------------*/

/** Is per-client Twilio wired up (so we can send an SMS code)? */
function cc_sms_ready(): bool {
	// Reuse the AI-chat Twilio resolver + sender when that module is present on
	// the site; both read the same shared-ISV credentials. Never a hard dependency
	// — absent it, we fall back to email OTP.
	return function_exists( 'bp_chat_send_sms' )
		&& function_exists( 'bp_chat_twilio' )
		&& bp_chat_twilio( 'sid' ) !== ''
		&& bp_chat_twilio( 'number' ) !== '';
}

/**
 * Send a one-time code. Prefers SMS (to the normalized phone) when Twilio is
 * configured, else email. Returns the channel used ('sms'|'email') or '' on failure.
 */
function cc_send_otp( string $phone, string $email, string $code ): string {
	$app = cc_app_name();
	if ( $phone !== '' && cc_sms_ready() ) {
		$msg = sprintf( '%s: your verification code is %s. It expires in 10 minutes.', $app, $code );
		if ( bp_chat_send_sms( $phone, $msg ) ) return 'sms';
	}
	if ( $email !== '' ) {
		$subject = sprintf( '%s verification code: %s', $app, $code );
		$body    = sprintf( "Your %s verification code is:\n\n%s\n\nIt expires in 10 minutes. If you didn't request this, you can ignore this email.", $app, $code );
		if ( wp_mail( $email, $subject, $body ) ) return 'email';
	}
	return '';
}

/**
 * REST: POST /request-otp — body { phone?, email? }. Creates + sends a code.
 * Rate-limited per IP. Always returns a generic success shape (no account
 * enumeration): "we sent a code if that contact is valid."
 */
function cc_rest_request_otp( WP_REST_Request $request ) {
	$body  = json_decode( $request->get_body(), true ) ?: [];
	$phone = cc_normalize_phone( (string) ( $body['phone'] ?? '' ) );
	$email = sanitize_email( (string) ( $body['email'] ?? '' ) );
	$email = is_email( $email ) ? $email : '';

	if ( $phone === '' && $email === '' ) {
		return new WP_Error( 'cc_missing', 'Enter a mobile number or email.', [ 'status' => 400 ] );
	}

	$ip = cc_client_ip();
	if ( ! cc_rate_ok( 'otp_' . md5( $ip ), 8, HOUR_IN_SECONDS ) ) {
		return new WP_Error( 'cc_rate', 'Too many attempts. Please try again later.', [ 'status' => 429 ] );
	}

	global $wpdb;
	$identifier = $phone !== '' ? $phone : $email;
	$channel    = ( $phone !== '' && cc_sms_ready() ) ? 'sms' : 'email';
	$code       = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );

	$wpdb->insert( cc_table( 'otps' ), [
		'identifier' => $identifier,
		'channel'    => $channel,
		'code_hash'  => hash_hmac( 'sha256', $code, cc_secret() ),
		'ip'         => $ip,
		'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 600 ),
		'created_at' => current_time( 'mysql' ),
	] );

	$sent = cc_send_otp( $phone, $email, $code );

	return rest_ensure_response( [
		'ok'         => true,
		'channel'    => $sent ?: $channel,
		'identifier' => $identifier,
		// Never leak the code. In a local/dev context you can inspect the otps table.
	] );
}

/**
 * REST: POST /verify-otp — body { identifier, code }. On success mints a token,
 * find-or-creates the customer, returns token + customer profile.
 */
function cc_rest_verify_otp( WP_REST_Request $request ) {
	$body       = json_decode( $request->get_body(), true ) ?: [];
	$identifier = sanitize_text_field( (string) ( $body['identifier'] ?? '' ) );
	$code       = preg_replace( '/\D+/', '', (string) ( $body['code'] ?? '' ) );

	if ( $identifier === '' || strlen( $code ) !== 6 ) {
		return new WP_Error( 'cc_bad', 'Enter the 6-digit code.', [ 'status' => 400 ] );
	}

	global $wpdb;
	$t   = cc_table( 'otps' );
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $t WHERE identifier = %s ORDER BY id DESC LIMIT 1", $identifier
	), ARRAY_A );

	if ( ! $row ) return new WP_Error( 'cc_none', 'Request a new code.', [ 'status' => 400 ] );
	if ( (int) $row['attempts'] >= 5 ) return new WP_Error( 'cc_locked', 'Too many tries. Request a new code.', [ 'status' => 429 ] );
	if ( strtotime( $row['expires_at'] . ' UTC' ) < time() ) return new WP_Error( 'cc_expired', 'That code expired. Request a new one.', [ 'status' => 400 ] );

	$expected = hash_hmac( 'sha256', $code, cc_secret() );
	if ( ! hash_equals( $row['code_hash'], $expected ) ) {
		$wpdb->update( $t, [ 'attempts' => (int) $row['attempts'] + 1 ], [ 'id' => (int) $row['id'] ] );
		return new WP_Error( 'cc_wrong', 'Incorrect code. Try again.', [ 'status' => 400 ] );
	}

	// Consume the code.
	$wpdb->delete( $t, [ 'identifier' => $identifier ] );

	$is_email = is_email( $identifier );
	$customer = cc_upsert_customer( $is_email ? '' : $identifier, $is_email ? $identifier : '' );
	$wpdb->update( cc_table( 'customers' ), [ 'last_login_at' => current_time( 'mysql' ) ], [ 'id' => (int) $customer['id'] ] );

	return rest_ensure_response( [
		'ok'       => true,
		'token'    => cc_generate_token( (int) $customer['id'] ),
		'customer' => cc_customer_public( $customer ),
	] );
}


/*--------------------------------------------------------------
# REST API (customer-connect/v1)
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	$ns = 'customer-connect/v1';

	// Public (auth happens inside via OTP / rate-limit).
	register_rest_route( $ns, '/request-otp', [
		'methods'             => 'POST',
		'callback'            => 'cc_rest_request_otp',
		'permission_callback' => '__return_true',
	] );
	register_rest_route( $ns, '/verify-otp', [
		'methods'             => 'POST',
		'callback'            => 'cc_rest_verify_otp',
		'permission_callback' => '__return_true',
	] );

	// Authenticated (token verified in the callback via cc_current_customer()).
	register_rest_route( $ns, '/me', [
		'methods'             => 'GET',
		'callback'            => 'cc_rest_me',
		'permission_callback' => '__return_true',
	] );
	register_rest_route( $ns, '/profile', [
		'methods'             => 'POST',
		'callback'            => 'cc_rest_update_profile',
		'permission_callback' => '__return_true',
	] );
} );

/** GET /me — verify a stored token on app open + return the current profile. */
function cc_rest_me( WP_REST_Request $request ) {
	$customer = cc_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'cc_auth', 'Not signed in.', [ 'status' => 401 ] );
	return rest_ensure_response( [
		'ok'       => true,
		'customer' => cc_customer_public( $customer ),
		// Silent token renewal keeps the device signed in without another OTP.
		'token'    => cc_generate_token( (int) $customer['id'] ),
	] );
}

/** POST /profile — customer edits their own name/address. */
function cc_rest_update_profile( WP_REST_Request $request ) {
	$customer = cc_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'cc_auth', 'Not signed in.', [ 'status' => 401 ] );

	$body  = json_decode( $request->get_body(), true ) ?: [];
	$allow = [ 'first_name', 'last_name', 'address', 'city', 'state', 'zip', 'email' ];
	$patch = [];
	foreach ( $allow as $k ) {
		if ( ! array_key_exists( $k, $body ) ) continue;
		if ( $k === 'email' ) {
			$e = sanitize_email( (string) $body[$k] );
			if ( $e !== '' && ! is_email( $e ) ) continue;
			$patch[$k] = $e;
		} else {
			$patch[$k] = sanitize_text_field( (string) $body[$k] );
		}
	}
	if ( $patch ) {
		global $wpdb;
		$wpdb->update( cc_table( 'customers' ), $patch, [ 'id' => (int) $customer['id'] ] );
		$customer = array_merge( $customer, $patch );
	}
	return rest_ensure_response( [ 'ok' => true, 'customer' => cc_customer_public( $customer ) ] );
}


/*--------------------------------------------------------------
# Shared small utilities (rate limit + client IP)
--------------------------------------------------------------*/

/** Best-effort client IP (behind Cloudflare/WPE proxies). */
function cc_client_ip(): string {
	foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			$ip = trim( explode( ',', (string) $_SERVER[ $k ] )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
		}
	}
	return '0.0.0.0';
}

/** Simple transient counter rate-limit. Returns true if still under the cap. */
function cc_rate_ok( string $key, int $max, int $window ): bool {
	$k = 'cc_rl_' . md5( $key );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) return false;
	set_transient( $k, $n + 1, $window );
	return true;
}


/*--------------------------------------------------------------
# Front-end App Page (auto-create + chrome strip + assets)
--------------------------------------------------------------*/

/**
 * Ensure the single `universal` page that hosts the PWA exists. Gated by an
 * option so the query runs once per version, not every load. Standalone — does
 * not depend on Site Pulse's housekeeping cron.
 */
// Priority 99 so the `universal` CPT (registered in functions-cpt.php on init)
// exists before we insert/query a post of that type.
add_action( 'init', function () {
	if ( get_option( 'customer_connect_pages_v' ) === CUSTOMER_CONNECT_DB_VERSION ) return;
	$pages = [
		[ 'slug' => CUSTOMER_CONNECT_SLUG,       'title' => cc_app_name(),               'tpl' => 'page-customer-connect' ],
	];
	foreach ( $pages as $p ) {
		if ( is_null( get_page_by_path( $p['slug'], OBJECT, 'universal' ) ) ) {
			wp_insert_post( [
				'post_title'   => $p['title'],
				'post_name'    => $p['slug'],
				'post_content' => '[get-universal-page slug="' . $p['tpl'] . '"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			] );
		}
	}
	update_option( 'customer_connect_pages_v', CUSTOMER_CONNECT_DB_VERSION );
}, 99 );

/** True on the Customer Connect app page. */
function cc_is_app_page(): bool {
	global $post;
	return $post && $post->post_name === CUSTOMER_CONNECT_SLUG;
}

// Never cache the app shell (it hands out nonces + per-device state via JS).
add_action( 'template_redirect', function () {
	if ( ! cc_is_app_page() ) return;
	if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();
	if ( ! headers_sent() ) header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	add_filter( 'show_admin_bar', '__return_false' );
	add_filter( 'body_class', function ( $c ) { $c[] = 'has-customer-connect'; return $c; } );
} );

// This IS a customer-facing app, so — unlike Site Pulse — it KEEPS the client's
// brand styling. We only enqueue the app's own CSS/JS on top.
add_action( 'wp_enqueue_scripts', function () {
	if ( ! cc_is_app_page() ) return;

	$css = file_exists( get_template_directory() . '/style-customer-connect.css' )
		? '/style-customer-connect.css' : '';
	if ( $css ) {
		wp_enqueue_style( 'customer-connect', get_template_directory_uri() . $css, [], _BP_VERSION );
	}

	$js = file_exists( get_template_directory() . '/js/script-customer-connect.min.js' )
		? '/js/script-customer-connect.min.js'
		: ( file_exists( get_template_directory() . '/js/script-customer-connect.js' ) ? '/js/script-customer-connect.js' : '' );
	if ( $js ) {
		wp_enqueue_script( 'customer-connect', get_template_directory_uri() . $js, [], _BP_VERSION, true );
		wp_localize_script( 'customer-connect', 'customerConnectData', [
			'restBase'    => esc_url_raw( rest_url( 'customer-connect/v1' ) ),
			'appName'     => cc_app_name(),
			'company'     => cc_company_name(),
			'smsReady'    => cc_sms_ready(),
			'pushReady'   => function_exists( 'cc_push_ready' ) ? cc_push_ready() : false,
			'vapidPublic' => function_exists( 'cc_vapid_public' ) ? cc_vapid_public() : '',
			'homeUrl'     => home_url( '/' . CUSTOMER_CONNECT_SLUG . '/' ),
		] );
	}
}, 20 );
