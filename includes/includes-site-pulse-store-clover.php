<?php
/* Battle Plan Web Design — Site Pulse: Store Clover Provider

/*--------------------------------------------------------------
Clover Hosted Checkout: the second way the store can collect money.

Clover decides nothing about the order. The store has already worked out the
subtotal, shipping, tax and total (includes-site-pulse-store-totals.php) and
written them to the order; this file hands Clover a cart that adds up to that
total and a URL to come back to. Clover's own tax engine is never involved —
the docs are explicit that a merchant's default tax is NOT applied to Hosted
Checkout payments, which suits us, because ours is already in the figure.

SHAPE: 'redirect'. The shopper leaves for a page Clover hosts and returns. The
iframe ('embedded') shape is the other option Clover offers and the provider
registry already models it; moving to it later changes begin() and nothing else.

FOUR THINGS CLOVER'S DOCS DO NOT SETTLE, all handled by failing closed rather
than by guessing:

  1. No production hostname is published. sp_store_clover_base() has only the
     sandbox host, and going live is a deliberate edit, not a flag flip.
  2. No idempotency key and no order-reference field on the checkout call. The
     returned checkoutSessionId is stored on the order and the webhook reconciles
     against it — that is the only thread between the two sides.
  3. The webhook payload's JSON key names are documented as display labels
     ("Created Time", "Status", "Data") with no sample JSON, so the reader below
     matches keys case- and separator-insensitively and logs anything it cannot
     read instead of silently dropping a payment.
  4. Whether a Hosted Checkout payment refunds as a charge or as an order is not
     documented. So there is no refund callback at all — the registry allows a
     provider without one and the order falls back to being refunded in Clover's
     dashboard. A wrong refund endpoint moves real money to the wrong place.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Credentials
# The API
# Beginning a Payment
# The Webhook
# Registration
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Credentials
--------------------------------------------------------------*/

/**
 * The private (Bearer) token.
 *
 * A wp-config constant wins over the stored setting, exactly as the Stripe keys
 * work: it is how a sandbox is driven before the settings UI exists, and a
 * constant never travels in a database snapshot handed to someone else.
 */
function sp_store_clover_token(): string {
	if ( defined( 'BP_STORE_CLOVER_TOKEN' ) && BP_STORE_CLOVER_TOKEN ) {
		return (string) BP_STORE_CLOVER_TOKEN;
	}
	return sp_store_get_secret( 'clover_token' );
}

/** The merchant this token belongs to. Sent on every call as its own header. */
function sp_store_clover_merchant(): string {
	if ( defined( 'BP_STORE_CLOVER_MERCHANT' ) && BP_STORE_CLOVER_MERCHANT ) {
		return (string) BP_STORE_CLOVER_MERCHANT;
	}
	return (string) sp_store_get_setting( 'clover_merchant', '' );
}

/** The Hosted Checkout signing secret, from Settings > Ecommerce > Hosted Checkout. */
function sp_store_clover_webhook_secret(): string {
	if ( defined( 'BP_STORE_CLOVER_WEBHOOK' ) && BP_STORE_CLOVER_WEBHOOK ) {
		return (string) BP_STORE_CLOVER_WEBHOOK;
	}
	return sp_store_get_secret( 'clover_webhook' );
}

/**
 * Sandbox or live.
 *
 * Stripe's mode is DERIVED from its key prefix on purpose — a switch beside the
 * key can only ever disagree with it. A Clover token carries no such marker and
 * the two environments are different hosts, so here the flag is unavoidable. It
 * defaults to sandbox: the failure of a forgotten flag should be "the test card
 * stopped working", never "a live card was charged from a staging site".
 */
function sp_store_clover_sandbox(): bool {
	if ( defined( 'BP_STORE_CLOVER_SANDBOX' ) ) return (bool) BP_STORE_CLOVER_SANDBOX;
	return sp_store_get_setting( 'clover_sandbox', '1' ) === '1';
}

/**
 * The API host.
 *
 * Clover publishes no production hostname for the Hosted Checkout service, so
 * live mode has nothing to return and says so. Better a configuration error on
 * our side than a POST to a hostname somebody guessed.
 */
function sp_store_clover_base(): string {
	return sp_store_clover_sandbox() ? 'https://apisandbox.dev.clover.com' : '';
}

/** Configured enough to take money? */
function sp_store_clover_ready(): bool {
	return sp_store_clover_token() !== ''
		&& sp_store_clover_merchant() !== ''
		&& sp_store_clover_base() !== '';
}


/*--------------------------------------------------------------
# The API
--------------------------------------------------------------*/

/**
 * One Clover call. Returns the decoded body or a WP_Error.
 *
 * No SDK and no Composer, same as the Stripe path: wp_remote_post and the REST
 * API. Clover requires a User-Agent, and omitting it is a confusing 4xx rather
 * than an obvious one.
 */
function sp_store_clover_api( string $method, string $path, array $body = [] ) {
	$base = sp_store_clover_base();
	if ( $base === '' ) {
		return new WP_Error( 'sp_store_clover', 'Clover has no published production host; the store is set to live mode.' );
	}

	$args = [
		'method'  => $method,
		'timeout' => 30,
		'headers' => [
			'accept'               => 'application/json',
			'content-type'         => 'application/json',
			'authorization'        => 'Bearer ' . sp_store_clover_token(),
			'X-Clover-Merchant-Id' => sp_store_clover_merchant(),
			'User-Agent'           => 'BattlePlanSitePulse/1.0',
		],
	];
	if ( $body ) $args['body'] = wp_json_encode( $body );

	$res = wp_remote_request( $base . $path, $args );
	if ( is_wp_error( $res ) ) return $res;

	$code = (int) wp_remote_retrieve_response_code( $res );
	$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( $code < 200 || $code >= 300 ) {
		$message = '';
		if ( is_array( $json ) ) {
			$message = (string) ( $json['message'] ?? $json['error']['message'] ?? $json['error'] ?? '' );
		}
		// The status code is kept in the message: Clover's error bodies vary and a
		// bare "something went wrong" is not worth waking up to.
		return new WP_Error( 'sp_store_clover', sprintf( 'Clover returned %d%s', $code, $message !== '' ? ': ' . $message : '' ) );
	}

	return is_array( $json ) ? $json : [];
}


/*--------------------------------------------------------------
# Beginning a Payment
--------------------------------------------------------------*/

/**
 * Create a Hosted Checkout session for an order and return where to send the shopper.
 *
 * The cart sent to Clover is built from what is ALREADY on the order, not from
 * the live basket — the order is the record of what was agreed, and the basket
 * can have moved on. Shipping and tax ride as their own line items because
 * Hosted Checkout has no field for either.
 *
 * @return array{url:string}|WP_Error
 */
function sp_store_clover_begin( int $order_id ) {
	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return new WP_Error( 'sp_store_clover', 'That order no longer exists.' );

	$items = sp_store_order_items( $order_id );
	if ( ! $items ) return new WP_Error( 'sp_store_clover', 'That order has nothing in it.' );

	// Hosted Checkout has no negative line item and no discount field. Rather than
	// quietly bill the undiscounted total, refuse — a discounted order has to go to a
	// processor that can express the discount.
	if ( (int) $order['discount_cents'] > 0 ) {
		return new WP_Error( 'sp_store_clover', 'Clover checkout cannot carry a discount on this order.' );
	}

	$lines = [];
	$sum   = 0;

	foreach ( $items as $item ) {
		$name = trim( (string) $item['name'] );
		if ( (string) $item['variant_title'] !== '' ) $name .= ' — ' . $item['variant_title'];

		$qty   = max( 1, (int) $item['qty'] );
		$price = (int) $item['unit_price_cents'];

		$lines[] = [
			'name'    => mb_substr( $name, 0, 127 ),
			'price'   => $price,
			'unitQty' => $qty,
		];
		$sum += $price * $qty;
	}

	if ( (int) $order['shipping_cents'] > 0 ) {
		$lines[] = [
			'name'    => mb_substr( (string) ( $order['shipping_method'] ?: 'Shipping' ), 0, 127 ),
			'price'   => (int) $order['shipping_cents'],
			'unitQty' => 1,
		];
		$sum += (int) $order['shipping_cents'];
	}

	if ( (int) $order['tax_cents'] > 0 ) {
		$lines[] = [ 'name' => 'Sales tax', 'price' => (int) $order['tax_cents'], 'unitQty' => 1 ];
		$sum += (int) $order['tax_cents'];
	}

	// The cart must come to the figure the customer was shown. If it does not, something
	// upstream has changed shape and the right answer is to stop, not to charge a number
	// nobody has seen. This is also the tripwire for Clover reading unitQty differently
	// from the way it is sent — the docs give one example and no units.
	if ( $sum !== (int) $order['total_cents'] ) {
		return new WP_Error( 'sp_store_clover', sprintf(
			'Clover cart totals %d but the order totals %d; refusing to charge.',
			$sum, (int) $order['total_cents']
		) );
	}

	$body = [
		'customer'     => array_filter( [
			'email'     => (string) $order['customer_email'],
			'firstName' => trim( (string) strtok( (string) $order['customer_name'], ' ' ) ),
			'lastName'  => trim( (string) substr( (string) $order['customer_name'], strlen( (string) strtok( (string) $order['customer_name'], ' ' ) ) ) ),
		] ),
		'shoppingCart' => [
			'lineItems' => $lines,
			'total'     => $sum,
			'subtotal'  => (int) $order['subtotal_cents'],
		],
		'redirectUrls' => [
			'success' => sp_store_thanks_url( (string) $order['number'] ),
			'failure' => add_query_arg( 'sp_store_error', rawurlencode( 'That payment did not go through.' ), sp_store_shop_url() ),
			'cancel'  => sp_store_shop_url(),
		],
	];

	$session = sp_store_clover_api( 'POST', '/invoicingcheckoutservice/v1/checkouts', $body );
	if ( is_wp_error( $session ) ) return $session;

	$href = (string) ( $session['href'] ?? '' );
	$sid  = (string) ( $session['checkoutSessionId'] ?? '' );
	if ( $href === '' || $sid === '' ) {
		return new WP_Error( 'sp_store_clover', 'Clover did not return a checkout session.' );
	}

	// checkoutSessionId is the ONLY identifier shared with the webhook — Hosted Checkout
	// takes no reference of ours — so the order is unreachable from the webhook without it.
	global $wpdb;
	$wpdb->update( sp_store_table( 'orders' ), [
		'provider'          => 'clover',
		'provider_checkout' => sp_store_checkout_guard( $sid ),
		'updated_at'        => current_time( 'mysql', true ),
	], [ 'id' => $order_id ] );

	return [ 'url' => $href ];
}


/*--------------------------------------------------------------
# The Webhook
--------------------------------------------------------------*/

/**
 * Pull a value out of Clover's payload without knowing its exact spelling.
 *
 * The webhook documentation lists its fields as display labels — "Created Time",
 * "Status", "Data" — and shows no sample JSON, so the real keys could be any of
 * data/Data/DATA. Matching loosely here is not laziness; guessing one spelling
 * and being wrong silently drops paid orders on the floor.
 */
function sp_store_clover_field( array $payload, string $want ): string {
	$want = strtolower( preg_replace( '/[^a-z0-9]/i', '', $want ) );
	foreach ( $payload as $key => $value ) {
		if ( ! is_scalar( $value ) ) continue;
		if ( strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $key ) ) === $want ) {
			return (string) $value;
		}
	}
	return '';
}

/**
 * Is this request really from Clover?
 *
 * Clover-Signature carries "t=<unix>,v1=<hmac>", and the signed string is the
 * timestamp, a period, and the RAW body — re-encoding the JSON first would
 * change the bytes and never match.
 */
function sp_store_clover_signature_ok( string $raw ): bool {
	$secret = sp_store_clover_webhook_secret();
	$header = (string) ( $_SERVER['HTTP_CLOVER_SIGNATURE'] ?? '' );
	if ( $secret === '' || $header === '' ) return false;

	$t = '';
	$v = '';
	foreach ( explode( ',', $header ) as $part ) {
		$bits = explode( '=', trim( $part ), 2 );
		if ( count( $bits ) !== 2 ) continue;
		if ( $bits[0] === 't' )  $t = $bits[1];
		if ( $bits[0] === 'v1' ) $v = $bits[1];
	}
	if ( $t === '' || $v === '' ) return false;

	$expected = hash_hmac( 'sha256', $t . '.' . $raw, $secret );

	return hash_equals( $expected, strtolower( $v ) );
}

/**
 * Handle a posted Clover webhook.
 *
 * Always answers 200 once the signature has passed, including for a payment we
 * have already settled: anything else makes Clover retry a delivery that was
 * never going to change anything.
 */
function sp_store_clover_webhook(): void {
	$raw    = (string) file_get_contents( 'php://input' );
	$signed = (string) ( $_SERVER['HTTP_CLOVER_SIGNATURE'] ?? '' ) !== '';

	// NOT a webhook at all: no signature header. A browser following the URL, an uptime
	// check, or Clover testing that the endpoint resolves. Answer 200 and do nothing —
	// 401 here is alarming, looks broken, and tells an unauthenticated caller that the
	// URL matters. Nothing is read from an unsigned request either way.
	if ( ! $signed ) {
		status_header( 200 );
		exit;
	}

	// Signed, but we cannot check it. Clover retries a non-2xx, so the payment is not
	// lost while the secret is being set — and the log says why it is not settling.
	if ( sp_store_clover_webhook_secret() === '' ) {
		site_pulse_log( 'store_clover_webhook', 'Clover webhook arrived but BP_STORE_CLOVER_WEBHOOK is not set; cannot verify it.' );
		status_header( 401 );
		exit;
	}

	if ( ! sp_store_clover_signature_ok( $raw ) ) {
		status_header( 401 );
		exit;
	}

	$payload = json_decode( $raw, true );
	if ( ! is_array( $payload ) ) {
		status_header( 400 );
		exit;
	}

	$type    = strtoupper( sp_store_clover_field( $payload, 'type' ) );
	$status  = strtoupper( sp_store_clover_field( $payload, 'status' ) );
	$session = sp_store_clover_field( $payload, 'data' );      // the checkout session UUID
	$payment = sp_store_clover_field( $payload, 'id' );        // Clover's payment UUID

	// A payload we cannot read is recorded WITH its body: the field names are
	// unconfirmed, so the first real delivery is what settles them. Silently
	// returning 200 here would lose a paid order and leave nothing to look at.
	if ( $session === '' ) {
		site_pulse_log( 'store_clover_webhook', 'Clover webhook could not be read', [ 'body' => mb_substr( $raw, 0, 2000 ) ] );
		status_header( 200 );
		exit;
	}

	if ( $type !== '' && $type !== 'PAYMENT' ) { status_header( 200 ); exit; }
	if ( $status !== 'APPROVED' )              { status_header( 200 ); exit; }

	global $wpdb;
	$order_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . sp_store_table( 'orders' ) . " WHERE provider_checkout = %s",
		$session
	) );

	if ( ! $order_id ) {
		site_pulse_log( 'store_clover_webhook', 'Clover webhook for an unknown checkout session', [ 'session' => $session ] );
		status_header( 200 );
		exit;
	}

	$order = sp_store_get_order( $order_id );

	// sp_store_settle_order() speaks Stripe's session shape because Stripe was once the
	// only caller. Clover reports no amounts of its own worth trusting over ours, so the
	// figures handed over are the ones already on the order — which is what settle would
	// have kept for a flat-tax order anyway.
	sp_store_settle_order( $order_id, [
		'payment_status' => 'paid',
		'amount_total'   => (int) $order['total_cents'],
		'payment_intent' => $payment,
		'total_details'  => [
			'amount_shipping' => (int) $order['shipping_cents'],
			'amount_discount' => 0,
		],
	] );

	status_header( 200 );
	exit;
}


/*--------------------------------------------------------------
# Registration
--------------------------------------------------------------*/

/**
 * Add Clover to the registry.
 *
 * Through the filter rather than by editing the registry literal, so the two
 * providers stay in their own files.
 *
 * No 'refund' key: see the note at the top of this file. The registry treats a
 * provider without one as "refund it in the processor's own dashboard", which is
 * true and safe, where a guessed endpoint would not be.
 */
add_filter( 'sp_store_providers', 'sp_store_register_clover' );
function sp_store_register_clover( array $providers ): array {
	$providers['clover'] = [
		'label'    => 'Clover',
		'shape'    => 'redirect',
		'ready'    => 'sp_store_clover_ready',
		'begin'    => 'sp_store_clover_begin',
		'webhook'  => 'sp_store_clover_webhook',
		'does_tax' => false,
		'note'     => 'Hosted Checkout: the shopper pays on a page Clover hosts. The store works out its own tax — Clover cannot. Refunds are issued from the Clover dashboard.',
	];
	return $providers;
}
