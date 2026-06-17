<?php
/**
 * Site Pulse — Web Push (closed-app notifications + icon badge).
 *
 * Payload-less VAPID push: the server sends a bare, VAPID-signed "wake" to each of a user's push
 * subscriptions (no encrypted body — so no Composer crypto library needed). The service worker's
 * push handler then fetches the latest unread summary from an authenticated endpoint and shows the
 * notification + sets the app-icon badge. Fully per-site and turnkey: VAPID keys auto-generate on
 * first use and store in this install's options. Site-gated by the `push_enabled` setting.
 *
 * Platform note: works on Android Chrome, desktop Chrome/Edge, and iOS/iPadOS 16.4+ ONLY when the
 * app is installed to the Home Screen. Best-effort (services throttle); the bell + email remain the
 * reliable channels. Sending is deferred to shutdown (after fastcgi_finish_request) so it never
 * blocks the user's request.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---- App icon (PWA) --------------------------------------------------------

// The currently-configured app-icon source as a URL (from pwa_icon_id or pwa_icon_path), for the
// Settings UI. Empty = falling back to child-theme/Site-Icon/logo.
function site_pulse_pwa_icon_setting_url(): string {
	$id = (int) site_pulse_get_setting( 'pwa_icon_id', '0' );
	if ( $id ) { $u = wp_get_attachment_url( $id ); if ( $u ) return $u; }
	$path = site_pulse_get_setting( 'pwa_icon_path', '' );
	if ( $path !== '' ) return $path;
	return '';
}

// Save the app icon from a pasted Media Library URL (or path). Resolves to the attachment ID when
// possible (most robust); else stores the URL's path. Clearing reverts to the fallback chain. Drops
// the generated-icon cache so the new icon rebuilds immediately.
add_action( 'wp_ajax_site_pulse_admin_save_app_icon', 'site_pulse_ajax_admin_save_app_icon' );
function site_pulse_ajax_admin_save_app_icon(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	$raw = trim( (string) wp_unslash( $_POST['url'] ?? '' ) );

	if ( $raw === '' ) {
		site_pulse_set_setting( 'pwa_icon_id', '0' );
		site_pulse_set_setting( 'pwa_icon_path', '' );
	} else {
		// Prefer the attachment ID (most robust). attachment_url_to_postid is picky about scheme/host,
		// so try the scheme-flipped URL too before giving up.
		$id = function_exists( 'attachment_url_to_postid' ) ? attachment_url_to_postid( $raw ) : 0;
		if ( ! $id && function_exists( 'attachment_url_to_postid' ) ) {
			$flip = ( strpos( $raw, 'https://' ) === 0 ) ? 'http://' . substr( $raw, 8 )
				: ( ( strpos( $raw, 'http://' ) === 0 ) ? 'https://' . substr( $raw, 7 ) : $raw );
			if ( $flip !== $raw ) $id = attachment_url_to_postid( $flip );
		}

		if ( $id ) {
			site_pulse_set_setting( 'pwa_icon_id', (string) $id );
			site_pulse_set_setting( 'pwa_icon_path', '' );
		} else {
			// Store the path RELATIVE TO THE UPLOADS DIR so the resolver maps it under the real
			// uploads basedir (avoids ABSPATH assumptions on WP Engine).
			$uploads = wp_upload_dir();
			if ( ! empty( $uploads['baseurl'] ) && strpos( $raw, $uploads['baseurl'] ) === 0 ) {
				$rel = ltrim( substr( $raw, strlen( $uploads['baseurl'] ) ), '/' );
			} else {
				$p   = (string) wp_parse_url( $raw, PHP_URL_PATH );
				$pos = strpos( $p, '/uploads/' );
				$rel = $pos !== false ? ltrim( substr( $p, $pos + 9 ), '/' ) : ltrim( $p, '/' );
			}
			site_pulse_set_setting( 'pwa_icon_id', '0' );
			site_pulse_set_setting( 'pwa_icon_path', sanitize_text_field( $rel ) );
		}
	}

	// Force a clean rebuild of the generated icon set.
	$cache = trailingslashit( wp_upload_dir()['basedir'] ) . 'site-pulse-pwa/icons.json';
	if ( file_exists( $cache ) ) @unlink( $cache );

	// Diagnostics so the UI can confirm it actually took (and so a fallback is obvious).
	$resolved = site_pulse_pwa_source_image();
	$preview  = function_exists( 'site_pulse_pwa_preview_url' ) ? site_pulse_pwa_preview_url() : '';
	wp_send_json_success( [
		'app_icon' => site_pulse_pwa_icon_setting_url(),
		'preview'  => $preview,
		'resolved' => $resolved ? basename( $resolved ) : '',
		'built'    => $preview !== '',
	] );
}

// ---- Config / keys ---------------------------------------------------------

function site_pulse_push_enabled(): bool {
	return site_pulse_get_setting( 'push_enabled', '0' ) === '1';
}

function sp_push_b64url( string $bin ): string {
	return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
}

// Per-site VAPID keypair (P-256). Auto-generated and stored once; the public key (uncompressed
// point, base64url) is the browser's applicationServerKey and the `k=` value on each send.
function sp_push_vapid_keys(): array {
	$pub  = site_pulse_get_setting( 'vapid_public', '' );
	$priv = site_pulse_get_setting( 'vapid_private', '' );
	if ( $pub && $priv ) return [ 'public' => $pub, 'private_pem' => base64_decode( $priv ) ];

	if ( ! function_exists( 'openssl_pkey_new' ) ) return [];
	$res = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
	if ( ! $res ) return [];
	openssl_pkey_export( $res, $priv_pem );
	$det = openssl_pkey_get_details( $res );
	if ( empty( $det['ec']['x'] ) || empty( $det['ec']['y'] ) ) return [];
	$x = str_pad( $det['ec']['x'], 32, "\0", STR_PAD_LEFT );
	$y = str_pad( $det['ec']['y'], 32, "\0", STR_PAD_LEFT );
	$pub_b64 = sp_push_b64url( "\x04" . $x . $y );

	site_pulse_set_setting( 'vapid_public', $pub_b64 );
	site_pulse_set_setting( 'vapid_private', base64_encode( $priv_pem ) );
	return [ 'public' => $pub_b64, 'private_pem' => $priv_pem ];
}

function sp_push_vapid_public(): string {
	return sp_push_vapid_keys()['public'] ?? '';
}

// scheme://host of a push endpoint (the VAPID JWT audience).
function sp_push_audience( string $endpoint ): string {
	$p = wp_parse_url( $endpoint );
	return ( $p['scheme'] ?? 'https' ) . '://' . ( $p['host'] ?? '' );
}

// A VAPID (ES256) JWT for the given audience: header.payload.signature, signature in JOSE r||s form.
function sp_push_vapid_jwt( string $audience ): string {
	$keys = sp_push_vapid_keys();
	if ( empty( $keys['private_pem'] ) ) return '';

	$header  = sp_push_b64url( wp_json_encode( [ 'typ' => 'JWT', 'alg' => 'ES256' ] ) );
	$sub     = site_pulse_get_setting( 'push_contact_email', get_option( 'admin_email' ) );
	$sub     = is_email( $sub ) ? 'mailto:' . $sub : 'mailto:admin@example.com';
	$payload = sp_push_b64url( wp_json_encode( [ 'aud' => $audience, 'exp' => time() + 12 * HOUR_IN_SECONDS, 'sub' => $sub ] ) );

	$input = $header . '.' . $payload;
	$der   = '';
	if ( ! openssl_sign( $input, $der, $keys['private_pem'], OPENSSL_ALGO_SHA256 ) ) return '';
	$raw = sp_push_der_to_raw( $der );
	if ( $raw === '' ) return '';
	return $input . '.' . sp_push_b64url( $raw );
}

// Convert an ECDSA DER signature to the fixed 64-byte JOSE form (R||S, each 32 bytes).
function sp_push_der_to_raw( string $der ): string {
	$off = 0;
	$len = strlen( $der );
	if ( $len < 8 || ord( $der[ $off++ ] ) !== 0x30 ) return '';
	$off++; // sequence length (short form for P-256)
	if ( ord( $der[ $off++ ] ) !== 0x02 ) return '';
	$rlen = ord( $der[ $off++ ] );
	$r = substr( $der, $off, $rlen ); $off += $rlen;
	if ( ord( $der[ $off++ ] ) !== 0x02 ) return '';
	$slen = ord( $der[ $off++ ] );
	$s = substr( $der, $off, $slen );
	$r = str_pad( ltrim( $r, "\0" ), 32, "\0", STR_PAD_LEFT );
	$s = str_pad( ltrim( $s, "\0" ), 32, "\0", STR_PAD_LEFT );
	if ( strlen( $r ) !== 32 || strlen( $s ) !== 32 ) return '';
	return $r . $s;
}

// ---- Sending (deferred to shutdown) ----------------------------------------

// Queue a wake-push to all of a user's devices. Self-gates on the push setting. The actual HTTP runs
// on shutdown (after the response is flushed) so it never slows the page.
function site_pulse_push_send( int $user_id ): void {
	if ( ! $user_id || ! site_pulse_push_enabled() ) return;
	$GLOBALS['_sp_push_queue'][ $user_id ] = true;
	if ( empty( $GLOBALS['_sp_push_hooked'] ) ) {
		$GLOBALS['_sp_push_hooked'] = true;
		add_action( 'shutdown', 'sp_push_flush_queue', 99 );
	}
}

function sp_push_flush_queue(): void {
	$ids = array_keys( $GLOBALS['_sp_push_queue'] ?? [] );
	if ( ! $ids ) return;
	if ( function_exists( 'fastcgi_finish_request' ) ) @fastcgi_finish_request();
	foreach ( $ids as $uid ) sp_push_deliver( (int) $uid );
}

function sp_push_deliver( int $user_id ): void {
	global $wpdb;
	$subs = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, endpoint FROM " . site_pulse_table( 'push_subscriptions' ) . " WHERE user_id = %d", $user_id
	), ARRAY_A );
	if ( ! $subs ) return;
	$pub = sp_push_vapid_public();
	if ( ! $pub ) return;

	foreach ( $subs as $s ) {
		$jwt = sp_push_vapid_jwt( sp_push_audience( $s['endpoint'] ) );
		if ( ! $jwt ) continue;
		$resp = wp_remote_post( $s['endpoint'], [
			'headers' => [
				'Authorization' => 'vapid t=' . $jwt . ', k=' . $pub,
				'TTL'           => '86400',
				'Content-Length'=> '0',
			],
			'body'    => '',
			'timeout' => 5,
		] );
		$code = (int) wp_remote_retrieve_response_code( $resp );
		// 404/410 = the subscription is gone; prune it.
		if ( $code === 404 || $code === 410 ) {
			$wpdb->delete( site_pulse_table( 'push_subscriptions' ), [ 'id' => (int) $s['id'] ] );
		}
	}
}

// ---- AJAX ------------------------------------------------------------------

// What the front end needs to subscribe: support flag, the VAPID public key, current setting state.
add_action( 'wp_ajax_site_pulse_push_meta', 'site_pulse_ajax_push_meta' );
function site_pulse_ajax_push_meta(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	wp_send_json_success( [
		'enabled'      => site_pulse_push_enabled(),
		'vapid_public' => site_pulse_push_enabled() ? sp_push_vapid_public() : '',
	] );
}

// Store (or refresh) this device's push subscription for the current user.
add_action( 'wp_ajax_site_pulse_push_subscribe', 'site_pulse_ajax_push_subscribe' );
function site_pulse_ajax_push_subscribe(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid = site_pulse_effective_user_id();
	if ( ! $uid ) wp_send_json_error( [ 'message' => 'Not signed in.' ] );

	$sub = json_decode( (string) wp_unslash( $_POST['sub'] ?? '' ), true );
	$endpoint = is_array( $sub ) ? ( $sub['endpoint'] ?? '' ) : '';
	if ( ! $endpoint || ! filter_var( $endpoint, FILTER_VALIDATE_URL ) ) wp_send_json_error( [ 'message' => 'Bad subscription.' ] );

	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO " . site_pulse_table( 'push_subscriptions' ) . "
		 (user_id, endpoint, endpoint_hash, p256dh, auth, ua, created_at)
		 VALUES (%d, %s, %s, %s, %s, %s, %s)
		 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), endpoint = VALUES(endpoint)",
		$uid,
		esc_url_raw( $endpoint ),
		hash( 'sha256', $endpoint ),
		sanitize_text_field( $sub['keys']['p256dh'] ?? '' ),
		sanitize_text_field( $sub['keys']['auth'] ?? '' ),
		substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
		current_time( 'mysql' )
	) );
	wp_send_json_success( [ 'subscribed' => true ] );
}

add_action( 'wp_ajax_site_pulse_push_unsubscribe', 'site_pulse_ajax_push_unsubscribe' );
function site_pulse_ajax_push_unsubscribe(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid = site_pulse_effective_user_id();
	$endpoint = (string) wp_unslash( $_POST['endpoint'] ?? '' );
	if ( ! $uid || ! $endpoint ) wp_send_json_error( [ 'message' => 'Bad request.' ] );
	global $wpdb;
	$wpdb->delete( site_pulse_table( 'push_subscriptions' ), [ 'endpoint_hash' => hash( 'sha256', $endpoint ), 'user_id' => $uid ] );
	wp_send_json_success( [ 'unsubscribed' => true ] );
}

// The service worker fetches this (cookie-authed, read-only) on a push to learn what to show. No
// nonce — it's a GET of the caller's own unread summary, and the SW can't carry a fresh nonce.
add_action( 'wp_ajax_site_pulse_push_data', 'site_pulse_ajax_push_data' );
function site_pulse_ajax_push_data(): void {
	$app = site_pulse_get_setting( 'app_name', 'Site Pulse' );
	$uid = get_current_user_id();
	if ( ! $uid ) { wp_send_json( [ 'title' => $app, 'body' => 'You have new activity.', 'count' => 0, 'url' => home_url( '/site-pulse-dashboard/' ) ] ); }

	global $wpdb;
	$n = $wpdb->get_row( $wpdb->prepare(
		"SELECT message, related_type FROM " . site_pulse_table( 'notifications' ) . "
		 WHERE user_id = %d AND is_read = 0 AND is_archived = 0 ORDER BY created_at DESC LIMIT 1", $uid
	), ARRAY_A );
	$bell = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . site_pulse_table( 'notifications' ) . " WHERE user_id = %d AND is_read = 0 AND is_archived = 0", $uid
	) );
	$msgs = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . site_pulse_table( 'messages' ) . " m
		 JOIN " . site_pulse_table( 'conversation_participants' ) . " p ON p.conversation_id = m.conversation_id AND p.user_id = %d
		 WHERE m.id > p.last_read_message_id AND m.sender_id != %d", $uid, $uid
	) );

	$url = home_url( '/site-pulse-dashboard/' );
	if ( $n && $n['related_type'] === 'conversation' ) $url = home_url( '/site-pulse-dashboard/?sp_panel=messages' );

	wp_send_json( [
		'title' => $app,
		'body'  => $n ? $n['message'] : 'You have new activity.',
		'count' => $bell + $msgs,
		'url'   => $url,
	] );
}
