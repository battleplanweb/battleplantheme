<?php
/**
 * Site Pulse — Customer Messages (Facebook / Instagram DMs).
 *
 * The agency hub (includes-fb-hub.php) holds the Meta tokens, receives the webhooks, and FORWARDS each
 * inbound DM to the Site Pulse site that Page is routed to. This file is that site's side: a signed REST
 * receiver that stores the message, an inbox UI (conversations + thread), and replies that go back OUT
 * through the hub's /bpfb/v1/send relay (which holds the Page token).
 *
 * Auth (both directions) reuses the GBP per-site HMAC: the hub signs with this site's registry secret,
 * which the site holds as BPGBP_SITE_SECRET (bpgbp_cfg('SITE_SECRET')).
 *
 * Tables: cm_conversations (one per customer+Page) and cm_messages (in/out).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ─────────────── Install ─────────────── */

const SP_CM_DB_VERSION = '1.0';

function sp_cm_table( string $name ): string { return site_pulse_table( $name ); }

add_action( 'init', 'sp_cm_install', 20 );
function sp_cm_install(): void {
	if ( get_option( 'site_pulse_cm_db' ) === SP_CM_DB_VERSION ) return;
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	$conv = sp_cm_table( 'cm_conversations' );
	$msgs = sp_cm_table( 'cm_messages' );

	dbDelta( "CREATE TABLE $conv (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		platform varchar(20) NOT NULL DEFAULT 'facebook',
		page_id varchar(40) NOT NULL,
		page_name varchar(190) DEFAULT NULL,
		customer_id varchar(64) NOT NULL,
		customer_name varchar(190) DEFAULT NULL,
		last_text text,
		last_at datetime DEFAULT NULL,
		unread int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'open',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY page_customer (page_id, customer_id),
		KEY status (status),
		KEY updated_at (updated_at)
	) $charset;" );

	dbDelta( "CREATE TABLE $msgs (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		conversation_id bigint(20) NOT NULL,
		direction varchar(3) NOT NULL DEFAULT 'in',
		body text,
		mid varchar(190) DEFAULT NULL,
		at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY conversation_id (conversation_id)
	) $charset;" );

	update_option( 'site_pulse_cm_db', SP_CM_DB_VERSION );
	sp_cm_seed_caps();
}

// Grant the caps to owner/admin (manage) + supervisor (view) once, like the other module seeds.
function sp_cm_seed_caps(): void {
	if ( get_option( 'site_pulse_cm_caps_seeded' ) ) return;
	global $wpdb;
	$grants = [ 'owner' => [ 'view_customer_messages', 'manage_customer_messages' ], 'admin' => [ 'view_customer_messages', 'manage_customer_messages' ], 'supervisor' => [ 'view_customer_messages' ] ];
	foreach ( $grants as $slug => $caps ) {
		if ( ! function_exists( 'site_pulse_get_role_by_slug' ) ) break;
		$role = site_pulse_get_role_by_slug( $slug );
		if ( ! $role ) continue;
		$have   = json_decode( $role['capabilities'], true ) ?: [];
		$merged = array_values( array_unique( array_merge( $have, $caps ) ) );
		if ( $merged !== $have ) $wpdb->update( site_pulse_table( 'roles' ), [ 'capabilities' => wp_json_encode( $merged ) ], [ 'id' => (int) $role['id'] ] );
	}
	update_option( 'site_pulse_cm_caps_seeded', '1' );
}

/* ─────────────── Permissions ─────────────── */

function sp_cm_can_view( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'view_customer_messages' ) || site_pulse_user_can( $user_id, 'manage_customer_messages' );
}
function sp_cm_can_manage( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_customer_messages' );
}

/* ─────────────── REST receiver (hub → this site) ─────────────── */

add_action( 'rest_api_init', function () {
	register_rest_route( 'bpfb-dm/v1', '/message', array(
		'methods'             => 'POST',
		'callback'            => 'sp_cm_rest_receive',
		'permission_callback' => '__return_true', // HMAC-verified inside
	) );
} );

function sp_cm_rest_receive( WP_REST_Request $req ) {
	$secret = function_exists( 'bpgbp_cfg' ) ? (string) bpgbp_cfg( 'SITE_SECRET' ) : '';
	if ( '' === $secret ) return new WP_Error( 'sp_cm_unconfigured', 'Messaging receiver not configured.', array( 'status' => 503 ) );

	$raw = $req->get_body();
	$ts  = (string) $req->get_header( 'x-bpgbp-timestamp' );
	$sig = (string) $req->get_header( 'x-bpgbp-signature' );
	if ( ! $ts || abs( time() - (int) $ts ) > 300 ) return new WP_Error( 'sp_cm_stale', 'Stale request.', array( 'status' => 401 ) );
	if ( ! hash_equals( hash_hmac( 'sha256', $ts . '.' . $raw, $secret ), $sig ) ) return new WP_Error( 'sp_cm_sig', 'Signature mismatch.', array( 'status' => 403 ) );

	$d = json_decode( $raw, true );
	if ( ! is_array( $d ) || empty( $d['page_id'] ) || empty( $d['sender_id'] ) ) return new WP_Error( 'sp_cm_bad', 'Malformed.', array( 'status' => 400 ) );

	sp_cm_store_inbound( $d );
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

// Upsert the conversation (per page+customer) and append the inbound message.
function sp_cm_store_inbound( array $d ): int {
	global $wpdb;
	$conv = sp_cm_table( 'cm_conversations' );
	$msgs = sp_cm_table( 'cm_messages' );
	$now  = current_time( 'mysql' );

	$page_id  = sanitize_text_field( (string) $d['page_id'] );
	$cust_id  = sanitize_text_field( (string) $d['sender_id'] );
	$text     = sanitize_textarea_field( (string) ( $d['text'] ?? '' ) );
	$cust_nm  = sanitize_text_field( (string) ( $d['sender_name'] ?? '' ) );
	$platform = ( ( $d['platform'] ?? '' ) === 'instagram' ) ? 'instagram' : 'facebook';
	$at       = ! empty( $d['at'] ) ? gmdate( 'Y-m-d H:i:s', (int) ( $d['at'] / 1000 ) ) : $now;

	$cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $conv WHERE page_id = %s AND customer_id = %s", $page_id, $cust_id ) );
	if ( $cid ) {
		$wpdb->update( $conv, [
			'page_name'     => sanitize_text_field( (string) ( $d['page_name'] ?? '' ) ) ?: null,
			'customer_name' => $cust_nm ?: null,
			'last_text'     => $text,
			'last_at'       => $at,
			'unread'        => 1, // simple flag; cleared on open
			'status'        => 'open',
			'updated_at'    => $now,
		], [ 'id' => $cid ] );
	} else {
		$wpdb->insert( $conv, [
			'platform' => $platform, 'page_id' => $page_id, 'page_name' => sanitize_text_field( (string) ( $d['page_name'] ?? '' ) ) ?: null,
			'customer_id' => $cust_id, 'customer_name' => $cust_nm ?: null,
			'last_text' => $text, 'last_at' => $at, 'unread' => 1, 'status' => 'open',
			'created_at' => $now, 'updated_at' => $now,
		] );
		$cid = (int) $wpdb->insert_id;
	}

	$wpdb->insert( $msgs, [
		'conversation_id' => $cid, 'direction' => 'in', 'body' => $text,
		'mid' => sanitize_text_field( (string) ( $d['mid'] ?? '' ) ) ?: null, 'at' => $at, 'created_at' => $now,
	] );

	if ( function_exists( 'site_pulse_dispatch_notification' ) ) {
		site_pulse_dispatch_notification( 'customer_message', 0, 'New ' . $platform . ' message' . ( $cust_nm ? ' from ' . $cust_nm : '' ), $cid, 'customer_message' );
	}
	return $cid;
}

/* ─────────────── AJAX — inbox ─────────────── */

add_action( 'wp_ajax_site_pulse_cm_conversations', 'sp_cm_ajax_conversations' );
function sp_cm_ajax_conversations(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_cm_can_view() ) wp_send_json_error( [ 'message' => 'No access to customer messages.' ] );
	global $wpdb;
	$conv = sp_cm_table( 'cm_conversations' );
	$rows = $wpdb->get_results( "SELECT * FROM $conv ORDER BY updated_at DESC, id DESC LIMIT 300", ARRAY_A ) ?: [];
	$out  = array_map( 'sp_cm_format_conv', $rows );
	wp_send_json_success( [ 'conversations' => $out, 'can_manage' => sp_cm_can_manage(), 'unread' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $conv WHERE unread = 1" ) ] );
}

function sp_cm_format_conv( array $r ): array {
	return [
		'id'            => (int) $r['id'],
		'platform'      => (string) $r['platform'],
		'page_name'     => (string) ( $r['page_name'] ?? '' ),
		'customer_name' => (string) ( $r['customer_name'] ?? '' ) ?: ( $r['platform'] === 'instagram' ? 'Instagram user' : 'Facebook user' ),
		'last_text'     => (string) ( $r['last_text'] ?? '' ),
		'last_at'       => (string) ( $r['last_at'] ?? '' ),
		'unread'        => (int) $r['unread'],
		'status'        => (string) $r['status'],
	];
}

add_action( 'wp_ajax_site_pulse_cm_thread', 'sp_cm_ajax_thread' );
function sp_cm_ajax_thread(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_cm_can_view() ) wp_send_json_error( [ 'message' => 'No access.' ] );
	$cid = (int) ( $_POST['conversation_id'] ?? 0 );
	if ( ! $cid ) wp_send_json_error( [ 'message' => 'Missing conversation.' ] );
	global $wpdb;
	$conv = sp_cm_table( 'cm_conversations' );
	$msgs = sp_cm_table( 'cm_messages' );
	$c = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $conv WHERE id = %d", $cid ), ARRAY_A );
	if ( ! $c ) wp_send_json_error( [ 'message' => 'Not found.' ] );

	if ( ! site_pulse_is_impersonating() ) $wpdb->update( $conv, [ 'unread' => 0 ], [ 'id' => $cid ] );

	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT direction, body, at FROM $msgs WHERE conversation_id = %d ORDER BY id ASC LIMIT 500", $cid ), ARRAY_A ) ?: [];
	$messages = array_map( fn( $m ) => [ 'direction' => $m['direction'], 'body' => (string) $m['body'], 'at' => (string) $m['at'] ], $rows );

	// 24h reply window: outbound replies are allowed only within 24h of the latest INBOUND message.
	$last_in = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(at) FROM $msgs WHERE conversation_id = %d AND direction = 'in'", $cid ) );
	$within  = $last_in ? ( ( time() - strtotime( $last_in . ' UTC' ) ) < DAY_IN_SECONDS ) : false;

	wp_send_json_success( [ 'conversation' => sp_cm_format_conv( $c ), 'messages' => $messages, 'can_manage' => sp_cm_can_manage(), 'reply_open' => $within ] );
}

add_action( 'wp_ajax_site_pulse_cm_send', 'sp_cm_ajax_send' );
function sp_cm_ajax_send(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_cm_can_manage() ) wp_send_json_error( [ 'message' => 'You can’t reply to customer messages.' ] );
	$cid  = (int) ( $_POST['conversation_id'] ?? 0 );
	$text = trim( (string) wp_unslash( $_POST['body'] ?? '' ) );
	if ( ! $cid || '' === $text ) wp_send_json_error( [ 'message' => 'Nothing to send.' ] );

	global $wpdb;
	$conv = sp_cm_table( 'cm_conversations' );
	$msgs = sp_cm_table( 'cm_messages' );
	$c = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $conv WHERE id = %d", $cid ), ARRAY_A );
	if ( ! $c ) wp_send_json_error( [ 'message' => 'Conversation not found.' ] );

	// Relay the reply out through the hub (which holds the Page token).
	$hub    = function_exists( 'bpgbp_cfg' ) ? rtrim( (string) bpgbp_cfg( 'HUB_URL' ), '/' ) : '';
	$key    = function_exists( 'bpgbp_cfg' ) ? (string) bpgbp_cfg( 'SITE_KEY' ) : '';
	$secret = function_exists( 'bpgbp_cfg' ) ? (string) bpgbp_cfg( 'SITE_SECRET' ) : '';
	if ( '' === $hub || '' === $key || '' === $secret ) wp_send_json_error( [ 'message' => 'This site isn’t paired with the messaging hub.' ] );

	$payload = wp_json_encode( [ 'page_id' => $c['page_id'], 'recipient_id' => $c['customer_id'], 'text' => $text ] );
	$ts  = (string) time();
	$sig = hash_hmac( 'sha256', $ts . '.' . $payload, $secret );
	$res = wp_remote_post( $hub . '/?rest_route=/bpfb/v1/send', [
		'timeout' => 20,
		'headers' => [ 'X-BPGBP-Site' => $key, 'X-BPGBP-Timestamp' => $ts, 'X-BPGBP-Signature' => $sig, 'Content-Type' => 'application/json' ],
		'body'    => $payload,
	] );

	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	if ( $code < 200 || $code >= 300 ) {
		$body = is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_body( $res );
		$m    = json_decode( (string) $body, true );
		wp_send_json_error( [ 'message' => 'Could not send: ' . ( is_array( $m ) && isset( $m['message'] ) ? $m['message'] : ( 'HTTP ' . $code ) ) ] );
	}

	$now = current_time( 'mysql' );
	$wpdb->insert( $msgs, [ 'conversation_id' => $cid, 'direction' => 'out', 'body' => $text, 'at' => $now, 'created_at' => $now ] );
	$wpdb->update( $conv, [ 'last_text' => $text, 'last_at' => $now, 'updated_at' => $now ], [ 'id' => $cid ] );

	wp_send_json_success( [ 'sent' => true ] );
}

// Lightweight unread count for the nav badge.
add_action( 'wp_ajax_site_pulse_cm_unread', 'sp_cm_ajax_unread' );
function sp_cm_ajax_unread(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_cm_can_view() ) wp_send_json_success( [ 'count' => 0 ] );
	global $wpdb;
	wp_send_json_success( [ 'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . sp_cm_table( 'cm_conversations' ) . " WHERE unread = 1" ) ] );
}
