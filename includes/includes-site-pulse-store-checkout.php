<?php
/* Battle Plan Web Design — Site Pulse: Store Checkout

/*--------------------------------------------------------------
The money path: cart -> Stripe Checkout Session -> webhook -> paid order.

NO STRIPE SDK, NO COMPOSER. Everything here is wp_remote_post() against Stripe's
REST API and one hash_hmac() for the webhook signature — the same approach the
Web Push module takes with payload-less VAPID. A payment library is the single
most consequential thing that could rot on a site nobody is watching, so there
isn't one.

WE ARE NOT THE MERCHANT. Each client's own standalone Stripe account takes the
money directly (not Connect), so there is no platform balance, no KYC and no
liability here. Card data never touches this server: Checkout is a redirect to
Stripe, which keeps every site in the fleet at SAQ-A.

THE ORDER EXISTS BEFORE THE PAYMENT. A `pending` order is written and its stock
reserved BEFORE the customer is sent to Stripe. That ordering is deliberate:
  - stock is reserved at the only moment worth reserving it, when somebody is
    actually paying, and for minutes rather than days
  - the webhook has a row to find, so it only ever flips a status — it never has
    to invent an order out of a payload, which is where duplicates come from
  - an abandoned checkout is visible as a pending order rather than as nothing

THE WEBHOOK IS AUTHORITATIVE, THE RETURN PAGE IS A SAFETY NET. A customer can
close the tab before the redirect, and Stripe's delivery can be slow or out of
order, so the return page also verifies the session directly and settles the
order if the webhook has not arrived. Both routes run through the same function
and the same idempotency guard, so whichever gets there first wins and the other
is a no-op.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Settings & Keys
# Stripe API
# Totals, Shipping & Tax
# Starting a Checkout
# Settling an Order
# Webhook
# Return Page
# Sweeper
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Settings & Keys
--------------------------------------------------------------*/

/**
 * Live or test — DERIVED from the key, never stored separately.
 *
 * Stripe keys announce their own mode (sk_test_ / sk_live_, rk_test_ / rk_live_), so a
 * mode setting alongside them could only ever be a second source of truth that
 * disagrees with the first. Nobody has to remember to flip a switch, and "test keys
 * running in live mode" is not a state this can get into.
 */
function sp_store_stripe_mode(): string {
	return strpos( sp_store_stripe_secret(), '_live_' ) !== false ? 'live' : 'test';
}

/**
 * The secret key for the current mode.
 *
 * Normally the AES-256-GCM encrypted setting. A wp-config constant overrides it, which
 * is there for two real cases: testing before the settings screen exists, and a client
 * whose policy keeps payment credentials out of the database entirely. A constant is
 * also the only form of this secret that survives a database dump going somewhere it
 * should not.
 */
function sp_store_stripe_secret(): string {
	if ( defined( 'BP_STORE_STRIPE_SECRET' ) && BP_STORE_STRIPE_SECRET ) {
		return (string) BP_STORE_STRIPE_SECRET;
	}
	return sp_store_get_secret( 'stripe_secret' );
}

function sp_store_stripe_webhook_secret(): string {
	if ( defined( 'BP_STORE_STRIPE_WEBHOOK_SECRET' ) && BP_STORE_STRIPE_WEBHOOK_SECRET ) {
		return (string) BP_STORE_STRIPE_WEBHOOK_SECRET;
	}
	return sp_store_get_secret( 'stripe_webhook' );
}

/** Is checkout actually usable? Everything customer-facing gates on this. */
function sp_store_stripe_ready(): bool {
	return sp_store_stripe_secret() !== '';
}

/**
 * How tax is worked out. Left as a per-install setting rather than a build-time
 * decision because the honest answer differs per client:
 *   none    no tax added (a client under their state's threshold, or tax-inclusive pricing)
 *   flat    one percentage, the right answer for a single-state seller — free, and no dependency
 *   stripe  Stripe Tax: real nexus and rate handling, billed per transaction. The only
 *           sane answer once a client sells across state lines, and not worth its cost
 *           before that.
 */
function sp_store_tax_mode(): string {
	$mode = sp_store_get_setting( 'tax_mode', 'none' );
	// 'stripe' was the old name for this, from when Stripe was the only processor.
	// Any site that saved it keeps working rather than silently dropping to no tax.
	if ( $mode === 'stripe' ) $mode = 'provider';
	return in_array( $mode, [ 'none', 'flat', 'provider' ], true ) ? $mode : 'none';
}

/** Flat tax rate as a percentage, e.g. 8.25. Only consulted in 'flat' mode. */
function sp_store_flat_tax_rate(): float {
	return max( 0, min( 100, (float) sp_store_get_setting( 'tax_flat_rate', '0' ) ) );
}

/** The URL that starts a checkout. Its existence is what enables the cart's button. */
/**
 * Where the cart's checkout button goes: OUR page, not the processor.
 *
 * It used to post straight to an endpoint that redirected to Stripe. The store
 * now collects the address, offers the postage and works out the tax itself, so
 * there is a page in between — and it is the only thing that makes an embedded
 * processor possible at all, since an iframe needs somewhere to mount.
 */
function sp_store_checkout_url(): string {
	if ( ! sp_store_provider_ready() ) return '';
	return sp_store_checkout_page_url();
}


/*--------------------------------------------------------------
# Stripe API
--------------------------------------------------------------*/

/**
 * One call to Stripe. Form-encoded, as its API expects.
 *
 * $idempotency is passed on anything that CREATES something. Stripe then guarantees a
 * retried request returns the original object instead of making a second one, which is
 * what stops a customer who double-clicks paying twice.
 */
function sp_store_stripe( string $method, string $path, array $params = [], string $idempotency = '' ) {
	$secret = sp_store_stripe_secret();
	if ( $secret === '' ) {
		return new WP_Error( 'sp_store_no_key', 'Stripe is not configured for this site yet.' );
	}

	$args = [
		'method'  => strtoupper( $method ),
		'timeout' => 20,
		'headers' => [
			'Authorization'  => 'Bearer ' . $secret,
			'Content-Type'   => 'application/x-www-form-urlencoded',
			// Pinning the API version means Stripe changing a default cannot alter this
			// site's behaviour on a random Tuesday.
			'Stripe-Version' => '2024-06-20',
		],
	];
	if ( $idempotency !== '' ) $args['headers']['Idempotency-Key'] = $idempotency;
	if ( $params ) $args['body'] = http_build_query( $params, '', '&' );

	$res = wp_remote_request( 'https://api.stripe.com/v1/' . ltrim( $path, '/' ), $args );
	if ( is_wp_error( $res ) ) return $res;

	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( $code >= 200 && $code < 300 ) return is_array( $body ) ? $body : [];

	$message = (string) ( $body['error']['message'] ?? 'Stripe returned an unexpected error.' );
	error_log( 'sp_store stripe ' . $path . ' ' . $code . ': ' . $message );
	return new WP_Error( 'sp_store_stripe', $message, [ 'status' => $code ] );
}


/*--------------------------------------------------------------
# Totals, Shipping & Tax
--------------------------------------------------------------*/

/**
 * The shipping options offered at checkout, from the client's own rate table.
 *
 * Returned in Stripe's shipping_options shape so Checkout can present them and collect
 * the choice — which also means the address form, its validation and the rate picker
 * are all Stripe's problem rather than three more things to maintain here.
 */
/**
 * The shared shipping rates, in the shape Stripe Checkout wants.
 *
 * The MATCHING lives in sp_store_shipping_rates_for(); this only translates. When
 * the two were one function, the rates existed solely as a Stripe payload and
 * nothing else in the store could find out what postage cost.
 */
function sp_store_shipping_options( array $lines ): array {
	$options = [];

	foreach ( sp_store_shipping_rates_for( $lines ) as $rate ) {
		$options[] = [
			'shipping_rate_data' => [
				'type'         => 'fixed_amount',
				'display_name' => $rate['name'],
				'fixed_amount' => [
					'amount'   => $rate['amount_cents'],
					'currency' => strtolower( sp_store_currency() ),
				],
			],
		];

		// Stripe accepts at most five.
		if ( count( $options ) === 5 ) break;
	}

	return $options;
}


/** Build Stripe line_items straight from the cart. Prices are ad-hoc, so no Stripe catalog to sync. */
function sp_store_line_items( array $lines ): array {
	$items    = [];
	$currency = strtolower( sp_store_currency() );

	foreach ( $lines as $line ) {
		$name = $line['title'];
		if ( $line['variant'] !== '' && $line['variant'] !== 'Default' ) {
			$name .= ' — ' . $line['variant'];
		}

		$product_data = [ 'name' => mb_substr( $name, 0, 250 ) ];
		if ( $line['image'] !== '' ) $product_data['images'] = [ $line['image'] ];

		$item = [
			'quantity'   => (int) $line['qty'],
			'price_data' => [
				'currency'     => $currency,
				'unit_amount'  => (int) $line['unit_cents'],
				'product_data' => $product_data,
			],
		];

		if ( sp_store_tax_mode() === 'provider' ) {
			$item['price_data']['tax_behavior'] = 'exclusive';
		}

		$items[] = $item;
	}

	return $items;
}


/*--------------------------------------------------------------
# Starting a Checkout
--------------------------------------------------------------*/

add_action( 'wp_ajax_sp_store_checkout', 'sp_store_start_checkout' );
add_action( 'wp_ajax_nopriv_sp_store_checkout', 'sp_store_start_checkout' );

/**
 * Turn the cart into a pending order, reserve its stock, and send the customer to
 * Stripe. A GET that redirects, because it is reached by clicking a link in the cart
 * drawer and its whole job is to hand off to another origin.
 */
/**
 * Begin checkout: reserve stock, open a pending order, hand off to the provider.
 *
 * Deliberately knows NOTHING about Stripe. Everything above the handoff is the
 * same whoever takes the money; everything below it is the provider's business.
 */
function sp_store_start_checkout(): void {
	if ( ! sp_store_active() || ! sp_store_provider_ready() ) {
		wp_die( 'Checkout is not available on this site yet.', 'Checkout', [ 'response' => 503 ] );
	}

	$cart = sp_store_current_cart();
	if ( ! $cart ) sp_store_checkout_bail( 'Your cart has expired.' );

	$lines = sp_store_cart_lines( (int) $cart['id'] );
	if ( ! $lines ) sp_store_checkout_bail( 'Your cart is empty.' );

	// ---- Reserve the stock FIRST. If the last shirt has gone since it was added to the
	// cart, the customer is told here rather than after paying for it.
	$reserved = [];
	foreach ( $lines as $line ) {
		if ( sp_store_commit_stock( (int) $line['variant_id'], (int) $line['qty'] ) ) {
			$reserved[ (int) $line['variant_id'] ] = (int) $line['qty'];
			continue;
		}
		// Give back whatever this attempt already took before bailing out. A partial
		// reservation left behind would take real stock off sale for nothing.
		foreach ( $reserved as $variant_id => $qty ) {
			sp_store_release_stock( $variant_id, $qty );
		}
		sp_store_checkout_bail( sprintf(
			'Sorry — "%s" sold out while it was in your cart. Please remove it and try again.',
			$line['title']
		) );
	}

	// ---- The order, pending, before the payment exists.
	$order_id = sp_store_create_pending_order( $cart, $lines );
	if ( ! $order_id ) {
		foreach ( $reserved as $variant_id => $qty ) sp_store_release_stock( $variant_id, $qty );
		sp_store_checkout_bail( 'We could not start your order. Please try again.' );
	}

	// ---- Hand off.
	$payment = sp_store_begin_payment( $order_id );
	if ( is_wp_error( $payment ) ) {
		sp_store_cancel_pending_order( $order_id, $payment->get_error_message() );
		sp_store_checkout_bail( $payment->get_error_message() );
	}

	// An embedded provider has no URL to send anyone to — its fields mount into a
	// page of ours. Until the on-site checkout exists there is nowhere to put them,
	// so say so plainly rather than redirecting to nothing.
	if ( ( $payment['shape'] ?? 'redirect' ) !== 'redirect' ) {
		sp_store_cancel_pending_order( $order_id, 'No on-site checkout page for an embedded provider yet.' );
		sp_store_checkout_bail( 'This store is mid-setup and cannot take payment yet.' );
	}

	wp_redirect( (string) $payment['url'] );
	exit;
}

/**
 * Stripe's handoff: a Checkout Session, returned as a URL to send the shopper to.
 *
 * Everything Stripe-shaped lives here. Note what it still delegates to Stripe —
 * shipping choices and, optionally, tax. Once the on-site checkout lands, those
 * move to the store side and this will be handed a total that is already final.
 */
function sp_store_stripe_begin( int $order_id ) {
	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return new WP_Error( 'sp_store_stripe', 'That order no longer exists.' );

	$cart  = sp_store_current_cart();
	$lines = $cart ? sp_store_cart_lines( (int) $cart['id'] ) : [];
	if ( ! $lines ) return new WP_Error( 'sp_store_stripe', 'Your cart is empty.' );

	$params = [
		'mode'                 => 'payment',
		'line_items'           => sp_store_line_items( $lines ),
		'client_reference_id'  => $order['number'],
		'metadata'             => [ 'order_id' => $order_id, 'order_number' => $order['number'] ],
		'payment_intent_data'  => [ 'metadata' => [ 'order_id' => $order_id, 'order_number' => $order['number'] ] ],
		'success_url'          => add_query_arg( [
			'sp_store_thanks' => $order['number'],
			'session_id'      => '{CHECKOUT_SESSION_ID}',
		], home_url( '/' ) ),
		'cancel_url'           => home_url( '/' ),
		// Expire the hold in about half an hour: long enough to go and find a card, short
		// enough that an abandoned checkout is not sitting on stock all afternoon.
		// 35 rather than 30 because Stripe REJECTS anything less than 30 minutes ahead,
		// and a few seconds of clock skew between here and Stripe would make exactly 30 fail.
		'expires_at'           => time() + 35 * MINUTE_IN_SECONDS,
		'allow_promotion_codes' => sp_store_get_setting( 'allow_promo_codes', '' ) === '1' ? 'true' : 'false',
	];

	if ( $cart['email'] !== '' ) $params['customer_email'] = $cart['email'];

	$shipping = sp_store_shipping_options( $lines );
	if ( $shipping ) {
		$params['shipping_options']            = $shipping;
		$params['shipping_address_collection'] = [ 'allowed_countries' => [ 'US' ] ];
	}

	if ( sp_store_tax_mode() === 'provider' ) {
		$params['automatic_tax'] = [ 'enabled' => 'true' ];
	}

	// The order number as the idempotency key: a double-click makes one session, not two.
	$session = sp_store_stripe( 'POST', 'checkout/sessions', $params, 'order-' . $order['number'] );
	if ( is_wp_error( $session ) ) return $session;

	global $wpdb;
	$wpdb->update( sp_store_table( 'orders' ), [
		'provider'          => 'stripe',
		'provider_checkout' => sp_store_checkout_guard( (string) ( $session['id'] ?? '' ) ),
		'updated_at'        => current_time( 'mysql', true ),
	], [ 'id' => $order_id ] );

	return [ 'url' => (string) $session['url'] ];
}


/** Send the customer back to the shop with something readable. */
function sp_store_checkout_bail( string $message ): void {
	wp_safe_redirect( add_query_arg(
		'sp_store_error', rawurlencode( $message ),
		sp_store_shop_url()
	) );
	exit;
}

/** Read one order. */
function sp_store_get_order( int $order_id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'orders' ) . " WHERE id = %d", $order_id
	), ARRAY_A );
	return $row ?: null;
}

function sp_store_get_order_by_number( string $number ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'orders' ) . " WHERE number = %s", $number
	), ARRAY_A );
	return $row ?: null;
}

/**
 * Write the pending order and its line snapshots.
 *
 * Every line copies the name, variant, SKU and price as they are NOW. Renaming or
 * deleting a product next year must not rewrite what somebody bought today.
 */
/**
 * Open a pending order.
 *
 * @param array $quote   A sp_store_quote() result. The order stores THESE amounts,
 *                       and the processor is later told to collect exactly the
 *                       stored total — so what is charged is what was computed
 *                       here, server-side, and never a figure from the browser.
 * @param array $address Validated shipping address, or [] when nothing ships.
 */
function sp_store_create_pending_order( array $cart, array $lines, array $quote = [], array $address = [] ): int {
	global $wpdb;

	$now   = current_time( 'mysql', true );
	$count = 0;
	foreach ( $lines as $line ) $count += $line['qty'];

	// No quote passed means an older call path that let the processor total the
	// order up. Price it here instead, so an order row is never written without
	// knowing what it is worth.
	if ( ! $quote ) $quote = sp_store_quote( $lines );

	$ship = [
		'ship_name'     => (string) ( $address['name'] ?? '' ),
		'ship_address1' => (string) ( $address['address1'] ?? '' ),
		'ship_address2' => (string) ( $address['address2'] ?? '' ),
		'ship_city'     => (string) ( $address['city'] ?? '' ),
		'ship_state'    => (string) ( $address['state'] ?? '' ),
	];
	// ship_zip, not ship_postcode. An unknown column makes wpdb::insert return false,
	// which here would exhaust the collide-and-retry loop and report "we could not
	// start your order" for every single checkout.
	$ship['ship_zip']     = (string) ( $address['zip'] ?? '' );
	$ship['ship_country'] = (string) ( $address['country'] ?? 'US' );

	// Collide-and-retry rather than assume: the number is random, and UNIQUE means a
	// clash is a failed insert rather than a shared order.
	for ( $attempt = 0; $attempt < 5; $attempt++ ) {
		$ok = $wpdb->insert( sp_store_table( 'orders' ), [
			'number'         => sp_store_generate_number(),
			'cart_id'        => (int) $cart['id'],
			'user_id'        => (int) $cart['user_id'],
			'customer_email' => (string) $cart['email'],
			'status'         => 'pending',
			'payment_status' => 'unpaid',
			'item_count'     => $count,
			'subtotal_cents' => (int) $quote['subtotal_cents'],
			'discount_cents' => (int) $quote['discount_cents'],
			'shipping_cents' => (int) $quote['shipping_cents'],
			'tax_cents'      => (int) $quote['tax_cents'],
			'total_cents'    => (int) $quote['total_cents'],
			'currency'       => sp_store_currency(),
			'source'         => 'web',
			'created_at'     => $now,
			'updated_at'     => $now,
		] + array_filter( $ship, static fn( $v ) => $v !== '' ) );
		if ( $ok ) break;
	}

	$order_id = (int) $wpdb->insert_id;
	if ( ! $order_id ) return 0;

	foreach ( $lines as $line ) {
		$variant = sp_store_get_variant( (int) $line['variant_id'] );
		$wpdb->insert( sp_store_table( 'order_items' ), [
			'order_id'          => $order_id,
			'variant_id'        => (int) $line['variant_id'],
			'product_id'        => (int) $line['product_id'],
			'sku'               => (string) $line['sku'],
			'name'              => (string) $line['title'],
			'variant_title'     => (string) $line['variant'],
			'qty'               => (int) $line['qty'],
			'unit_price_cents'  => (int) $line['unit_cents'],
			'subtotal_cents'    => (int) $line['line_cents'],
			'total_cents'       => (int) $line['line_cents'],
			'taxable'           => $variant ? (int) $variant['taxable'] : 1,
			'requires_shipping' => $variant ? (int) $variant['requires_shipping'] : 1,
			'weight_grams'      => $variant ? (int) $variant['weight_grams'] * (int) $line['qty'] : 0,
			'created_at'        => $now,
		] );
	}

	return $order_id;
}

/** Cancel a pending order and give its stock back. Safe to call twice. */
function sp_store_cancel_pending_order( int $order_id, string $reason = '' ): void {
	global $wpdb;

	$order = sp_store_get_order( $order_id );
	if ( ! $order || $order['status'] !== 'pending' ) return;

	foreach ( sp_store_order_items( $order_id ) as $item ) {
		sp_store_release_stock( (int) $item['variant_id'], (int) $item['qty'] );
	}

	$wpdb->update( sp_store_table( 'orders' ), [
		'status'        => 'cancelled',
		'cancelled_at'  => current_time( 'mysql', true ),
		'cancel_reason' => mb_substr( $reason, 0, 255 ),
		'updated_at'    => current_time( 'mysql', true ),
	], [ 'id' => $order_id ] );
}

function sp_store_order_items( int $order_id ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'order_items' ) . " WHERE order_id = %d ORDER BY id ASC",
		$order_id
	), ARRAY_A ) ?: [];
}


/*--------------------------------------------------------------
# Settling an Order
--------------------------------------------------------------*/

/**
 * Mark an order paid from a completed Stripe session. THE ONE PLACE this happens.
 *
 * Reached from two directions — the webhook and the return page — and from Stripe
 * retrying the webhook, possibly out of order. So it is written to be safely callable
 * any number of times: an order that is already open is left exactly as it is, and the
 * conditional UPDATE means two concurrent callers cannot both do the work.
 *
 * Reserved stock becomes SOLD here: the reservation is converted into an actual
 * decrement, then released, so stock and stock_committed both land correctly and the
 * inventory ledger gains a row explaining it.
 */
function sp_store_settle_order( int $order_id, array $session ): bool {
	global $wpdb;

	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return false;
	if ( $order['payment_status'] === 'paid' ) return true;   // already done

	// Only settle a session Stripe actually considers paid.
	if ( ( $session['payment_status'] ?? '' ) !== 'paid' ) return false;

	$now = current_time( 'mysql', true );

	// The conditional UPDATE is the lock: whoever flips it away from unpaid owns the
	// rest of this function, and the loser's affected-row count is zero.
	$claimed = $wpdb->query( $wpdb->prepare(
		"UPDATE " . sp_store_table( 'orders' ) . "
		    SET status = 'open', payment_status = 'paid',
		        paid_cents = %d, total_cents = %d, tax_cents = %d, shipping_cents = %d,
		        discount_cents = %d, provider_payment = %s, provider_customer = %s,
		        customer_email = %s, customer_name = %s,
		        placed_at = %s, updated_at = %s
		  WHERE id = %d AND payment_status = 'unpaid'",
		(int) ( $session['amount_total'] ?? 0 ),
		(int) ( $session['amount_total'] ?? 0 ),
		(int) ( $session['total_details']['amount_tax'] ?? 0 ),
		(int) ( $session['total_details']['amount_shipping'] ?? 0 ),
		(int) ( $session['total_details']['amount_discount'] ?? 0 ),
		(string) ( $session['payment_intent'] ?? '' ),
		(string) ( $session['customer'] ?? '' ),
		(string) ( $session['customer_details']['email'] ?? $order['customer_email'] ),
		(string) ( $session['customer_details']['name'] ?? '' ),
		$now, $now,
		$order_id
	) );

	if ( $claimed !== 1 ) return true;   // somebody else settled it first; nothing to do

	// ---- Addresses, where Stripe collected them.
	$ship = $session['shipping_details']['address'] ?? ( $session['customer_details']['address'] ?? [] );
	if ( $ship ) {
		$wpdb->update( sp_store_table( 'orders' ), [
			'ship_name'     => (string) ( $session['shipping_details']['name'] ?? $session['customer_details']['name'] ?? '' ),
			'ship_address1' => (string) ( $ship['line1'] ?? '' ),
			'ship_address2' => (string) ( $ship['line2'] ?? '' ),
			'ship_city'     => (string) ( $ship['city'] ?? '' ),
			'ship_state'    => (string) ( $ship['state'] ?? '' ),
			'ship_zip'      => (string) ( $ship['postal_code'] ?? '' ),
			'ship_country'  => substr( (string) ( $ship['country'] ?? 'US' ), 0, 2 ),
			'customer_phone'=> (string) ( $session['customer_details']['phone'] ?? '' ),
		], [ 'id' => $order_id ] );
	}

	// ---- Reservation becomes a sale.
	foreach ( sp_store_order_items( $order_id ) as $item ) {
		$variant_id = (int) $item['variant_id'];
		$qty        = (int) $item['qty'];
		sp_store_adjust_stock( $variant_id, -$qty, 'sale', $order_id, 'Order ' . $order['number'] );
		sp_store_release_stock( $variant_id, $qty );
	}

	// ---- The payment record. UNIQUE (provider, provider_object) is the guard that makes
	// a redelivered webhook a duplicate-key no-op rather than a second payment.
	$wpdb->insert( sp_store_table( 'payments' ), [
		'order_id'          => $order_id,
		'provider'          => 'stripe',
		'kind'              => 'payment',
		'amount_cents'      => (int) ( $session['amount_total'] ?? 0 ),
		'currency'          => strtoupper( (string) ( $session['currency'] ?? sp_store_currency() ) ),
		'status'            => 'succeeded',
		'provider_object'   => (string) ( $session['payment_intent'] ?? $session['id'] ?? '' ),
		'provider_customer' => (string) ( $session['customer'] ?? '' ),
		'description'       => 'Order ' . $order['number'],
		'created_at'        => $now,
		'updated_at'        => $now,
	] );

	// ---- Retire the cart.
	if ( (int) $order['cart_id'] ) {
		$wpdb->update( sp_store_table( 'carts' ),
			[ 'status' => 'converted', 'order_id' => $order_id, 'updated_at' => $now ],
			[ 'id' => (int) $order['cart_id'] ]
		);
		$wpdb->delete( sp_store_table( 'cart_items' ), [ 'cart_id' => (int) $order['cart_id'] ] );
	}

	// Receipts and staff alerts hang off this in phase 4. Firing it here means the
	// notification layer never has to know which route settled the order.
	do_action( 'sp_store_order_paid', $order_id );

	return true;
}


/*--------------------------------------------------------------
# Webhook
--------------------------------------------------------------*/

add_action( 'init', 'sp_store_webhook_route' );
function sp_store_webhook_route(): void {
	// A plain query-var endpoint, not a rewrite rule: it must keep working even if
	// permalinks are ever flushed badly, because a webhook that 404s loses orders.
	if ( ! isset( $_GET['sp_store_webhook'] ) ) return;

	if ( ! sp_store_active() || sp_store_webhook_provider() === '' ) {
		status_header( 404 );
		exit;
	}

	sp_store_dispatch_webhook();
	exit;
}


/** The URL to paste into Stripe. Shown on the settings screen. */
/** Back-compat shim: the active provider's webhook URL. */
function sp_store_webhook_url(): string {
	return sp_store_provider_webhook_url();
}


/**
 * Verify Stripe's signature over the RAW body.
 *
 * Header shape: t=1690000000,v1=<hex>,v1=<hex>. The signed payload is "{t}.{body}".
 * hash_equals() so the comparison cannot be timed, and a five-minute tolerance so a
 * captured request cannot be replayed indefinitely.
 */
function sp_store_verify_webhook( string $payload, string $header, string $secret ): bool {
	if ( $secret === '' || $header === '' ) return false;

	$timestamp = 0;
	$signatures = [];
	foreach ( explode( ',', $header ) as $part ) {
		$bits = explode( '=', trim( $part ), 2 );
		if ( count( $bits ) !== 2 ) continue;
		if ( $bits[0] === 't' )  $timestamp = (int) $bits[1];
		if ( $bits[0] === 'v1' ) $signatures[] = $bits[1];
	}
	if ( ! $timestamp || ! $signatures ) return false;
	if ( abs( time() - $timestamp ) > 5 * MINUTE_IN_SECONDS ) return false;

	$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
	foreach ( $signatures as $signature ) {
		if ( hash_equals( $expected, $signature ) ) return true;
	}
	return false;
}

function sp_store_handle_webhook(): void {
	$payload = (string) file_get_contents( 'php://input' );
	$header  = (string) ( $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '' );

	if ( ! sp_store_verify_webhook( $payload, $header, sp_store_stripe_webhook_secret() ) ) {
		status_header( 400 );
		echo 'invalid signature';
		return;
	}

	$event = json_decode( $payload, true );
	if ( ! is_array( $event ) || empty( $event['id'] ) ) {
		status_header( 400 );
		echo 'bad payload';
		return;
	}

	global $wpdb;
	$now = current_time( 'mysql', true );

	// Record the event BEFORE processing. UNIQUE (provider, event_id) makes a redelivery
	// a failed insert, which is how we know to stop — Stripe guarantees at-least-once, so
	// this WILL happen in production, not just in theory.
	$fresh = $wpdb->insert( sp_store_table( 'webhook_events' ), [
		'provider'    => 'stripe',
		'event_id'    => (string) $event['id'],
		'event_type'  => (string) ( $event['type'] ?? '' ),
		'status'      => 'received',
		'payload'     => wp_json_encode( $event ),
		'received_at' => $now,
	] );

	// Always 200 on a duplicate. Anything else makes Stripe retry forever.
	if ( ! $fresh ) {
		status_header( 200 );
		echo 'duplicate';
		return;
	}

	$row_id = (int) $wpdb->insert_id;
	$type   = (string) ( $event['type'] ?? '' );
	$object = (array) ( $event['data']['object'] ?? [] );

	if ( $type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded' ) {
		$order_id = (int) ( $object['metadata']['order_id'] ?? 0 );
		if ( $order_id ) sp_store_settle_order( $order_id, $object );

	} elseif ( $type === 'checkout.session.expired' || $type === 'checkout.session.async_payment_failed' ) {
		$order_id = (int) ( $object['metadata']['order_id'] ?? 0 );
		if ( $order_id ) sp_store_cancel_pending_order( $order_id, 'Checkout expired or failed.' );
	}

	$wpdb->update( sp_store_table( 'webhook_events' ),
		[ 'status' => 'processed', 'processed_at' => current_time( 'mysql', true ) ],
		[ 'id' => $row_id ]
	);

	status_header( 200 );
	echo 'ok';
}


/*--------------------------------------------------------------
# Return Page
--------------------------------------------------------------*/

add_action( 'template_redirect', 'sp_store_thanks_page' );
function sp_store_thanks_page(): void {
	if ( ! sp_store_active() || empty( $_GET['sp_store_thanks'] ) ) return;

	$number = sanitize_text_field( wp_unslash( (string) $_GET['sp_store_thanks'] ) );
	$order  = sp_store_get_order_by_number( $number );

	// THE SAFETY NET. The customer is standing here, so if the webhook has not landed
	// yet we ask Stripe directly rather than showing them an unpaid order. Both routes
	// end in sp_store_settle_order(), whose guard makes the second one a no-op.
	$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['session_id'] ) ) : '';
	if ( $order && $order['payment_status'] === 'unpaid' && $session_id !== '' && strpos( $session_id, 'cs_' ) === 0 ) {
		$session = sp_store_stripe( 'GET', 'checkout/sessions/' . $session_id );
		if ( ! is_wp_error( $session ) && (int) ( $session['metadata']['order_id'] ?? 0 ) === (int) $order['id'] ) {
			sp_store_settle_order( (int) $order['id'], $session );
			$order = sp_store_get_order( (int) $order['id'] );
		}
	}

	status_header( 200 );
	nocache_headers();
	get_header();
	?>
	<div id="primary" class="site-main" role="main" aria-label="main content">
		<?php bp_before_site_main_inner(); ?>
		<div class="site-main-inner">
			<?php bp_before_the_content(); ?>
			<div class="store-thanks">
			<?php if ( $order && $order['payment_status'] === 'paid' ) : ?>
				<h1>Thank you &mdash; your order is confirmed</h1>
				<p>Your order number is <strong><?php echo esc_html( $order['number'] ); ?></strong>. We have emailed a receipt to
					<strong><?php echo esc_html( $order['customer_email'] ); ?></strong>.</p>
				<p class="store-thanks-total">Total paid: <strong><?php echo sp_store_money( (int) $order['total_cents'], (string) $order['currency'] ); ?></strong></p>
			<?php elseif ( $order ) : ?>
				<h1>Thank you &mdash; we are confirming your payment</h1>
				<p>Your order number is <strong><?php echo esc_html( $order['number'] ); ?></strong>. Payment is still being
					confirmed; this usually takes a few seconds. You will get an email as soon as it clears, and
					there is no need to pay again.</p>
			<?php else : ?>
				<h1>Thank you</h1>
				<p>We could not find that order. If you were charged, please get in touch and quote
					<strong><?php echo esc_html( $number ); ?></strong>.</p>
			<?php endif; ?>
				<p><a href="<?php echo esc_url( sp_store_shop_url() ); ?>">Continue shopping</a></p>
			</div>
			<?php bp_after_the_content(); ?>
		</div>
		<?php bp_after_site_main_inner(); ?>
	</div>
	<?php
	get_footer();
	exit;
}


/*--------------------------------------------------------------
# Sweeper
--------------------------------------------------------------*/

/**
 * Release stock from checkouts that were started and never finished.
 *
 * Stripe sends checkout.session.expired and that is the normal path — this is the
 * backstop for the case where the webhook is misconfigured, which is exactly when
 * stock would otherwise stay reserved forever and nobody would know why the shop says
 * sold out. The grace period is generous (the session expires at 30 minutes) so this
 * can never race a customer who is slowly typing in their card.
 */
function sp_store_checkout_sweep(): void {
	if ( get_transient( 'sp_store_checkout_swept' ) ) return;
	set_transient( 'sp_store_checkout_swept', 1, 10 * MINUTE_IN_SECONDS );

	global $wpdb;
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS );

	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT id FROM " . sp_store_table( 'orders' ) . "
		  WHERE status = 'pending' AND payment_status = 'unpaid' AND created_at < %s
		  LIMIT 50",
		$cutoff
	) ) ?: [];

	foreach ( $ids as $id ) {
		sp_store_cancel_pending_order( (int) $id, 'Checkout was never completed.' );
	}
}
add_action( 'init', 'sp_store_checkout_sweep_hook', 21 );
function sp_store_checkout_sweep_hook(): void {
	if ( ! sp_store_active() || is_admin() ) return;
	sp_store_checkout_sweep();
}
