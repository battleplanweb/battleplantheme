<?php
/**
 * Customer Connect — Web Push (customer-keyed, payload-carrying)
 * ---------------------------------------------------------------------------
 * Client→customer notifications ("time to change your filter", "schedule your
 * seasonal maintenance"). Self-contained VAPID + RFC 8291 (aes128gcm) payload
 * encryption in pure PHP (openssl + hash_hkdf) — NO Composer, NO dependency on
 * the Site Pulse push module (this module stands alone).
 *
 * Why payload-carrying (not Site Pulse's payload-less + authed fetch): customers
 * aren't WP users, so the service worker has no cookie to authenticate a
 * follow-up fetch. Encrypting the message straight into the push delivers real
 * content with the app closed and no second request.
 *
 * Subscriptions are keyed on customer_id (table customer_connect_push_subscriptions),
 * deduped by endpoint hash. Dispatch: cc_push_send_to_customer() /
 * cc_push_broadcast(). @package battleplan
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# VAPID keys (per-site, auto-generated once)
--------------------------------------------------------------*/

/** base64url encode/decode (no padding) — the Web Push wire format. */
function cc_b64url_encode( string $bin ): string { return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); }
function cc_b64url_decode( string $txt ): string {
	$txt = strtr( $txt, '-_', '+/' );
	$pad = strlen( $txt ) % 4;
	if ( $pad ) $txt .= str_repeat( '=', 4 - $pad );
	return (string) base64_decode( $txt );
}

/**
 * The site's VAPID keypair: ['public'=>b64url raw 65-byte point, 'private'=>PEM].
 * Generated once and stored in options. Returns [] if openssl EC is unavailable.
 */
function cc_vapid_keys(): array {
	$pub  = get_option( 'customer_connect_vapid_public', '' );
	$priv = get_option( 'customer_connect_vapid_private', '' );
	if ( $pub && $priv ) return [ 'public' => $pub, 'private' => $priv ];

	if ( ! function_exists( 'openssl_pkey_new' ) ) return [];
	$res = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
	if ( ! $res ) return [];
	openssl_pkey_export( $res, $pem );
	$d = openssl_pkey_get_details( $res );
	if ( empty( $d['ec']['x'] ) || empty( $d['ec']['y'] ) ) return [];

	$raw_pub = "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT );
	$pub  = cc_b64url_encode( $raw_pub );
	update_option( 'customer_connect_vapid_public', $pub, false );
	update_option( 'customer_connect_vapid_private', $pem, false );
	return [ 'public' => $pub, 'private' => $pem ];
}

/** The public application server key (b64url) the browser subscribes with. */
function cc_vapid_public(): string {
	$k = cc_vapid_keys();
	return $k['public'] ?? '';
}

/** True when push can actually be sent (keys + crypto present). */
function cc_push_ready(): bool {
	return cc_vapid_public() !== '' && function_exists( 'hash_hkdf' ) && function_exists( 'openssl_encrypt' );
}


/*--------------------------------------------------------------
# VAPID JWT (ES256) for the Authorization header
--------------------------------------------------------------*/

/** DER ECDSA signature → raw r||s (64 bytes). Copied pattern (not shared with SP). */
function cc_der_to_raw( string $der ): string {
	$off = 0;
	if ( ( $der[$off++] ?? '' ) !== "\x30" ) return $der; // not a SEQUENCE
	$len = ord( $der[$off++] );
	if ( $len & 0x80 ) $off += ( $len & 0x7f );            // long-form length
	$read = function () use ( $der, &$off ) {
		$off++; // 0x02 INTEGER
		$l = ord( $der[$off++] );
		$v = substr( $der, $off, $l );
		$off += $l;
		return ltrim( $v, "\x00" );
	};
	$r = $read(); $s = $read();
	return str_pad( $r, 32, "\0", STR_PAD_LEFT ) . str_pad( $s, 32, "\0", STR_PAD_LEFT );
}

/** Signed VAPID JWT for a push endpoint origin ($audience). */
function cc_vapid_jwt( string $audience ): string {
	$keys = cc_vapid_keys();
	if ( ! $keys ) return '';
	$sub = (string) ( function_exists( 'cc_get' ) ? cc_get( 'push_contact', '' ) : '' );
	if ( $sub === '' ) $sub = 'mailto:' . get_option( 'admin_email' );

	$header  = cc_b64url_encode( wp_json_encode( [ 'typ' => 'JWT', 'alg' => 'ES256' ] ) );
	$payload = cc_b64url_encode( wp_json_encode( [
		'aud' => $audience,
		'exp' => time() + 12 * HOUR_IN_SECONDS,
		'sub' => $sub,
	] ) );
	$signing = $header . '.' . $payload;

	$sig = '';
	if ( ! openssl_sign( $signing, $sig, $keys['private'], OPENSSL_ALGO_SHA256 ) ) return '';
	return $signing . '.' . cc_b64url_encode( cc_der_to_raw( $sig ) );
}


/*--------------------------------------------------------------
# RFC 8291 payload encryption (aes128gcm)
--------------------------------------------------------------*/

/**
 * Build an EC public key resource from a raw 65-byte P-256 point, so openssl can
 * derive an ECDH shared secret against it. (openssl needs a SPKI-wrapped key.)
 */
function cc_ec_pub_from_raw( string $raw ) {
	// Fixed DER prefix for an uncompressed prime256v1 SubjectPublicKeyInfo.
	$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . $raw;
	$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	return openssl_pkey_get_public( $pem );
}

/**
 * Encrypt $payload for a subscription. Returns the aes128gcm body bytes, or ''
 * on failure. $ua_public / $auth are the subscription's raw p256dh + auth secret.
 */
function cc_encrypt_payload( string $payload, string $ua_public, string $auth ): string {
	if ( ! cc_push_ready() ) return '';

	// Ephemeral (application-server) keypair for this message.
	$as = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
	if ( ! $as ) return '';
	$asd = openssl_pkey_get_details( $as );
	$as_public = "\x04" . str_pad( $asd['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $asd['ec']['y'], 32, "\0", STR_PAD_LEFT );

	$ua_key = cc_ec_pub_from_raw( $ua_public );
	if ( ! $ua_key ) return '';
	$ecdh = openssl_pkey_derive( $ua_key, $as );
	if ( ! $ecdh ) return '';

	// RFC 8291 §3.4: IKM = HKDF(auth, ecdh, "WebPush: info\0"|ua|as, 32).
	$info = "WebPush: info\0" . $ua_public . $as_public;
	$ikm  = hash_hkdf( 'sha256', $ecdh, 32, $info, $auth );

	// RFC 8188 aes128gcm: derive CEK + NONCE from the record salt.
	$salt  = random_bytes( 16 );
	$cek   = hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt );
	$nonce = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt );

	// Single record: plaintext || 0x02 delimiter. Encrypt (tag appended).
	$tag = '';
	$ct  = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
	if ( $ct === false ) return '';

	// Header: salt(16) | rs(4=4096) | idlen(1=65) | as_public(65) | ciphertext+tag.
	$rs = pack( 'N', 4096 );
	return $salt . $rs . chr( 65 ) . $as_public . $ct . $tag;
}


/*--------------------------------------------------------------
# Subscriptions (customer-keyed)
--------------------------------------------------------------*/

/** REST: POST /push/subscribe — store this device's subscription for the customer. */
function cc_rest_push_subscribe( WP_REST_Request $request ) {
	$customer = cc_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'cc_auth', 'Not signed in.', [ 'status' => 401 ] );

	$body = json_decode( $request->get_body(), true ) ?: [];
	$sub  = $body['subscription'] ?? $body;
	$endpoint = esc_url_raw( (string) ( $sub['endpoint'] ?? '' ) );
	$p256dh   = (string) ( $sub['keys']['p256dh'] ?? '' );
	$auth     = (string) ( $sub['keys']['auth'] ?? '' );
	if ( $endpoint === '' || $p256dh === '' || $auth === '' ) {
		return new WP_Error( 'cc_bad', 'Invalid subscription.', [ 'status' => 400 ] );
	}

	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO " . cc_table( 'push_subscriptions' ) . "
			(customer_id, endpoint, endpoint_hash, p256dh, auth, ua, created_at)
			VALUES (%d, %s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE customer_id = VALUES(customer_id), p256dh = VALUES(p256dh), auth = VALUES(auth)",
		(int) $customer['id'], $endpoint, hash( 'sha256', $endpoint ), $p256dh, $auth,
		substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ), current_time( 'mysql' )
	) );
	return rest_ensure_response( [ 'ok' => true ] );
}

/** REST: POST /push/unsubscribe — drop a device subscription. */
function cc_rest_push_unsubscribe( WP_REST_Request $request ) {
	$customer = cc_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'cc_auth', 'Not signed in.', [ 'status' => 401 ] );
	$body = json_decode( $request->get_body(), true ) ?: [];
	$endpoint = esc_url_raw( (string) ( $body['endpoint'] ?? '' ) );
	if ( $endpoint !== '' ) {
		global $wpdb;
		$wpdb->delete( cc_table( 'push_subscriptions' ), [ 'endpoint_hash' => hash( 'sha256', $endpoint ) ] );
	}
	return rest_ensure_response( [ 'ok' => true ] );
}

/** REST: GET /push/meta — the VAPID public key + whether push is available. */
function cc_rest_push_meta( WP_REST_Request $request ) {
	return rest_ensure_response( [ 'ready' => cc_push_ready(), 'vapidPublic' => cc_vapid_public() ] );
}


/*--------------------------------------------------------------
# Dispatch
--------------------------------------------------------------*/

/**
 * Deliver a notification to one subscription row. Returns the HTTP status (or 0).
 * Prunes the subscription on a 404/410 (gone).
 */
function cc_push_deliver( array $subrow, array $payload ): int {
	if ( ! cc_push_ready() ) return 0;
	$endpoint = $subrow['endpoint'];
	$origin   = (string) wp_parse_url( $endpoint, PHP_URL_SCHEME ) . '://' . (string) wp_parse_url( $endpoint, PHP_URL_HOST );

	$body = cc_encrypt_payload(
		wp_json_encode( $payload ),
		cc_b64url_decode( $subrow['p256dh'] ),
		cc_b64url_decode( $subrow['auth'] )
	);
	if ( $body === '' ) return 0;

	$jwt = cc_vapid_jwt( $origin );
	if ( $jwt === '' ) return 0;

	$res = wp_remote_post( $endpoint, [
		'headers' => [
			'Authorization'    => 'vapid t=' . $jwt . ', k=' . cc_vapid_public(),
			'Content-Type'     => 'application/octet-stream',
			'Content-Encoding' => 'aes128gcm',
			'TTL'              => '2419200', // 4 weeks
			'Urgency'          => 'normal',
		],
		'body'    => $body,
		'timeout' => 8,
	] );
	if ( is_wp_error( $res ) ) return 0;

	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( $code === 404 || $code === 410 ) {
		global $wpdb;
		$wpdb->delete( cc_table( 'push_subscriptions' ), [ 'id' => (int) $subrow['id'] ] );
	}
	return $code;
}

/**
 * Send a notification to every device of one customer, and log it. $note =
 * ['title'=>, 'body'=>, 'url'=>?]. Returns the number of endpoints delivered (2xx).
 */
function cc_push_send_to_customer( int $customer_id, array $note, int $sent_by = 0 ): int {
	global $wpdb;
	$note = wp_parse_args( $note, [ 'title' => cc_app_name(), 'body' => '', 'url' => home_url( '/' . CUSTOMER_CONNECT_SLUG . '/' ) ] );

	// Log to the notifications table (also feeds the in-app list) regardless of push success.
	$wpdb->insert( cc_table( 'notifications' ), [
		'customer_id' => $customer_id,
		'title'       => mb_substr( (string) $note['title'], 0, 190 ),
		'body'        => (string) $note['body'],
		'url'         => esc_url_raw( (string) $note['url'] ),
		'channel'     => 'push',
		'sent_by'     => $sent_by,
		'created_at'  => current_time( 'mysql' ),
	] );

	$subs = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . cc_table( 'push_subscriptions' ) . " WHERE customer_id = %d", $customer_id
	), ARRAY_A ) ?: [];

	$ok = 0;
	foreach ( $subs as $s ) {
		$code = cc_push_deliver( $s, $note );
		if ( $code >= 200 && $code < 300 ) $ok++;
	}
	return $ok;
}

/**
 * Broadcast to many customers. $customer_ids = array of ids. Returns
 * ['recipients'=>N, 'delivered'=>M]. Caller resolves the audience (e.g. overdue
 * filters). Runs inline for small lists; large sends should be chunked by a cron.
 */
function cc_push_broadcast( array $customer_ids, array $note, int $sent_by = 0 ): array {
	$delivered = 0;
	$ids = array_unique( array_map( 'intval', $customer_ids ) );
	foreach ( $ids as $cid ) {
		if ( $cid > 0 ) $delivered += cc_push_send_to_customer( $cid, $note, $sent_by );
	}
	return [ 'recipients' => count( $ids ), 'delivered' => $delivered ];
}

/**
 * Resolve a named audience segment → customer ids. Extensible via the
 * `customer_connect_push_segment` filter. Built-in: 'all', 'filter_due'.
 */
function cc_push_segment_ids( string $segment ): array {
	global $wpdb;
	if ( $segment === 'all' ) {
		return array_map( 'intval', $wpdb->get_col(
			"SELECT DISTINCT customer_id FROM " . cc_table( 'push_subscriptions' )
		) );
	}
	if ( $segment === 'filter_due' ) {
		// Customers with at least one unit whose filter is due/overdue today.
		$t   = cc_table( 'equipment' );
		$rows = $wpdb->get_results( "SELECT customer_id, filter_changed_at, filter_interval_days FROM $t WHERE filter_changed_at IS NOT NULL", ARRAY_A ) ?: [];
		$ids = [];
		foreach ( $rows as $r ) {
			$interval = (int) ( $r['filter_interval_days'] ?: 90 );
			$due = strtotime( $r['filter_changed_at'] . ' 00:00:00' ) + $interval * DAY_IN_SECONDS;
			if ( $due <= time() ) $ids[] = (int) $r['customer_id'];
		}
		return array_values( array_unique( $ids ) );
	}
	return array_map( 'intval', (array) apply_filters( 'customer_connect_push_segment', [], $segment ) );
}


/*--------------------------------------------------------------
# REST routes (customer-side push endpoints)
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	$ns = 'customer-connect/v1';
	register_rest_route( $ns, '/push/meta',        [ 'methods' => 'GET',  'callback' => 'cc_rest_push_meta',        'permission_callback' => '__return_true' ] );
	register_rest_route( $ns, '/push/subscribe',   [ 'methods' => 'POST', 'callback' => 'cc_rest_push_subscribe',   'permission_callback' => '__return_true' ] );
	register_rest_route( $ns, '/push/unsubscribe', [ 'methods' => 'POST', 'callback' => 'cc_rest_push_unsubscribe', 'permission_callback' => '__return_true' ] );

	// In-app notification list + unread badge.
	register_rest_route( $ns, '/notifications', [ 'methods' => 'GET', 'callback' => 'cc_rest_notifications', 'permission_callback' => '__return_true' ] );
	register_rest_route( $ns, '/notifications/read', [ 'methods' => 'POST', 'callback' => 'cc_rest_notifications_read', 'permission_callback' => '__return_true' ] );
} );

/** GET /notifications — the customer's recent notifications + unread count. */
function cc_rest_notifications( WP_REST_Request $request ) {
	$customer = cc_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'cc_auth', 'Not signed in.', [ 'status' => 401 ] );
	global $wpdb;
	$t   = cc_table( 'notifications' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, title, body, url, read_at, created_at FROM $t WHERE customer_id = %d ORDER BY id DESC LIMIT 50",
		(int) $customer['id']
	), ARRAY_A ) ?: [];
	$unread = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM $t WHERE customer_id = %d AND read_at IS NULL", (int) $customer['id']
	) );
	return rest_ensure_response( [ 'ok' => true, 'items' => $rows, 'unread' => $unread ] );
}

/** POST /notifications/read — mark all of the customer's notifications read. */
function cc_rest_notifications_read( WP_REST_Request $request ) {
	$customer = cc_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'cc_auth', 'Not signed in.', [ 'status' => 401 ] );
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . cc_table( 'notifications' ) . " SET read_at = %s WHERE customer_id = %d AND read_at IS NULL",
		current_time( 'mysql' ), (int) $customer['id']
	) );
	return rest_ensure_response( [ 'ok' => true ] );
}
