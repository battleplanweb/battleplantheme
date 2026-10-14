<?php
/* Battle Plan Web Design — Site Pulse: On-site Checkout

/*--------------------------------------------------------------
The page where a customer enters their address, picks a shipping option, sees
what they owe, and pays.

This exists because the store now prices its own orders. Previously the cart
button threw the shopper straight at Stripe, which collected the address,
offered the postage and worked out the tax. A processor that cannot do those
things — Clover — left nowhere for them to happen. So they happen here, on a
page we own, and the processor is handed a total that is already final.

THE RULE THAT MATTERS: no amount is ever accepted from the browser. The page
posts an address and the id of a chosen shipping rate; the server recomputes
the quote from the database every time and the order stores that. A total that
made a round trip through a browser is a total somebody can edit.

Both provider shapes land here. A redirect provider returns a URL for the page
to send the shopper to; an embedded provider returns a config the page mounts
its iframe from. Either way the card fields belong to the processor and nothing
on this page may read them.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# The Page
# Address
# Endpoints
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# The Page
--------------------------------------------------------------*/

/** Where the cart's checkout button goes. */
function sp_store_checkout_page_url(): string {
	return add_query_arg( 'sp_store_checkout', '1', home_url( '/' ) );
}

add_action( 'template_redirect', 'sp_store_checkout_page' );
function sp_store_checkout_page(): void {
	if ( ! sp_store_active() || empty( $_GET['sp_store_checkout'] ) ) return;

	$cart  = sp_store_current_cart();
	$lines = $cart ? sp_store_cart_lines( (int) $cart['id'] ) : [];

	status_header( 200 );
	nocache_headers();
	get_header();

	// The framework's own page structure, not a wrapper of our own: it carries the
	// site's width, spacing and the bp_before_* hooks a child theme may rely on.
	echo '<div id="primary" class="site-main store-checkout-main" role="main" aria-label="main content">';
	if ( function_exists( 'bp_before_site_main_inner' ) ) bp_before_site_main_inner();
	echo '<div class="site-main-inner">';
	if ( function_exists( 'bp_before_the_content' ) ) bp_before_the_content();

	if ( ! $lines ) {
		echo '<h1 class="store-checkout-title">Your cart is empty</h1>';
		echo '<p><a href="' . esc_url( sp_store_shop_url() ) . '">Back to the shop</a></p>';
	} elseif ( ! sp_store_provider_ready() ) {
		// Staff see why; customers should never reach this, because the cart hides
		// its checkout button when no processor is configured.
		echo '<h1 class="store-checkout-title">Checkout is unavailable</h1>';
		echo '<p>We are not able to take payment right now. Please try again shortly.</p>';
	} else {
		echo sp_store_checkout_html( $cart, $lines );
	}

	echo '</div></div>';
	get_footer();
	exit;
}

/**
 * The checkout form.
 *
 * Rendered server-side with a first quote already in it, so the page is correct
 * before any JavaScript runs — someone on a slow connection sees real figures
 * rather than empty boxes that fill in later.
 */
function sp_store_checkout_html( array $cart, array $lines ): string {
	$quote = sp_store_quote( $lines );

	$out  = '<div class="store-checkout" data-store-checkout>';
	$out .= '<h1 class="store-checkout-title">Checkout</h1>';
	$out .= '<div class="store-checkout-grid">';

	// ---- Left: the form.
	$out .= '<div class="store-checkout-form">';

	$out .= '<section class="store-checkout-section">';
	$out .= '<h2>Contact</h2>';
	$out .= '<label class="store-field"><span>Email</span>';
	$out .= '<input type="email" id="sp-co-email" name="email" autocomplete="email" required value="' . esc_attr( (string) $cart['email'] ) . '">';
	$out .= '</label>';
	$out .= '<p class="store-field-note">Your receipt and any delivery updates go here.</p>';
	$out .= '</section>';

	if ( $quote['needs_shipping'] ) {
		$out .= '<section class="store-checkout-section">';
		$out .= '<h2>Delivery address</h2>';
		$out .= '<label class="store-field"><span>Full name</span><input type="text" id="sp-co-name" autocomplete="name" required></label>';
		$out .= '<label class="store-field"><span>Address</span><input type="text" id="sp-co-address1" autocomplete="address-line1" required></label>';
		$out .= '<label class="store-field"><span>Apartment, suite, etc. <em>(optional)</em></span><input type="text" id="sp-co-address2" autocomplete="address-line2"></label>';
		$out .= '<div class="store-field-row">';
		$out .= '<label class="store-field"><span>City</span><input type="text" id="sp-co-city" autocomplete="address-level2" required></label>';
		$out .= '<label class="store-field"><span>State</span>' . sp_store_state_select() . '</label>';
		$out .= '<label class="store-field"><span>ZIP</span><input type="text" id="sp-co-zip" autocomplete="postal-code" inputmode="numeric" required></label>';
		$out .= '</div>';
		$out .= '</section>';

		$out .= '<section class="store-checkout-section">';
		$out .= '<h2>Delivery method</h2>';
		$out .= '<div class="store-ship-options" data-store-ship-options>';
		$out .= sp_store_shipping_choices_html( $quote );
		$out .= '</div>';
		$out .= '</section>';
	}

	$out .= '<section class="store-checkout-section">';
	$out .= '<h2>Payment</h2>';
	$out .= '<div class="store-pay-mount" data-store-pay></div>';
	$out .= '<p class="store-checkout-error" data-store-co-error hidden></p>';
	$out .= '<button type="button" class="unique store-place-order" data-store-place>Place order</button>';
	$out .= '<p class="store-field-note">Payment is handled by ' . esc_html( sp_store_provider_def()['label'] ?? 'our payment processor' ) . '. Card details never reach this website.</p>';
	$out .= '</section>';

	$out .= '</div>';

	// ---- Right: the summary.
	$out .= '<aside class="store-checkout-summary" data-store-summary>';
	$out .= sp_store_summary_html( $lines, $quote );
	$out .= '</aside>';

	$out .= '</div></div>';
	return $out;
}

/** The shipping choices, as radios. Re-rendered by the quote endpoint. */
function sp_store_shipping_choices_html( array $quote ): string {
	if ( empty( $quote['shipping_rates'] ) ) {
		return '<p class="store-field-note">No delivery options are set up for this order. Please contact us.</p>';
	}

	$chosen = (int) $quote['shipping_rate_id'];
	$out    = '';

	foreach ( $quote['shipping_rates'] as $i => $rate ) {
		// Default to the first rate rather than nothing: an unselected radio group
		// makes the total look wrong until the shopper works out what to click.
		$on  = $chosen ? ( $chosen === $rate['id'] ) : ( $i === 0 );
		$out .= '<label class="store-ship-option' . ( $on ? ' is-active' : '' ) . '">';
		$out .= '<input type="radio" name="sp_ship_rate" value="' . (int) $rate['id'] . '"' . ( $on ? ' checked' : '' ) . '>';
		$out .= '<span class="store-ship-name">' . esc_html( $rate['name'] ) . '</span>';
		$out .= '<span class="store-ship-price">' . sp_store_money( (int) $rate['amount_cents'] ) . '</span>';
		$out .= '</label>';
	}

	return $out;
}

/** The order summary panel. Re-rendered whenever the quote changes. */
function sp_store_summary_html( array $lines, array $quote ): string {
	$out = '<h2>Your order</h2><div class="store-summary-lines">';

	foreach ( $lines as $line ) {
		$out .= '<div class="store-summary-line">';
		if ( $line['image'] !== '' ) {
			$out .= '<span class="store-summary-img"><img src="' . esc_url( $line['image'] ) . '" alt=""><em>' . (int) $line['qty'] . '</em></span>';
		}
		$out .= '<div class="store-summary-detail">';
		$out .= '<strong>' . esc_html( sp_store_plain_text( (string) $line['title'] ) ) . '</strong>';
		if ( $line['variant'] !== '' && $line['variant'] !== 'Default' ) {
			$out .= '<div class="store-summary-variant">' . esc_html( sp_store_plain_text( (string) $line['variant'] ) ) . '</div>';
		}
		$out .= '</div>';
		$out .= '<span class="store-summary-amount">' . $line['line'] . '</span>';
		$out .= '</div>';
	}

	$out .= '</div><div class="store-summary-totals">';
	$out .= sp_store_totals_html( $quote );
	$out .= '</div>';

	return $out;
}

/** Just the figures, so the quote endpoint can replace them on their own. */
function sp_store_totals_html( array $quote ): string {
	$q = sp_store_quote_display( $quote );

	$out  = '<p><span>Subtotal</span><span>' . $q['subtotal'] . '</span></p>';

	if ( $quote['needs_shipping'] ) {
		$out .= '<p><span>Shipping</span><span>'
			. ( $quote['shipping_rate_id'] ? $q['shipping'] : '&mdash;' )
			. '</span></p>';
	}

	if ( ! empty( $quote['tax_by_provider'] ) ) {
		// Honest rather than reassuring: saying "$0.00" when the processor is about
		// to add tax would make the total on this page a lie.
		$out .= '<p><span>Tax</span><span class="store-summary-note">worked out at payment</span></p>';
	} elseif ( $quote['tax_cents'] > 0 ) {
		$out .= '<p><span>Tax</span><span>' . $q['tax'] . '</span></p>';
	}

	$out .= '<p class="store-summary-total"><span>Total</span><span>' . $q['total'] . '</span></p>';
	return $out;
}


/*--------------------------------------------------------------
# Address
--------------------------------------------------------------*/

/** US states. Shipping is US-only for now, matching the Stripe path. */
function sp_store_states(): array {
	return [
		'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
		'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
		'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
		'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
		'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
		'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
		'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
		'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
		'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
		'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
		'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
		'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
		'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
	];
}

function sp_store_state_select(): string {
	$out = '<select id="sp-co-state" autocomplete="address-level1" required><option value="">Choose…</option>';
	foreach ( sp_store_states() as $code => $name ) {
		$out .= '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
	}
	return $out . '</select>';
}

/**
 * Clean a posted address, and say what is wrong with it.
 *
 * @return array{address: array, errors: array}
 */
function sp_store_clean_address( array $raw ): array {
	$a = [
		'name'     => sanitize_text_field( (string) ( $raw['name'] ?? '' ) ),
		'address1' => sanitize_text_field( (string) ( $raw['address1'] ?? '' ) ),
		'address2' => sanitize_text_field( (string) ( $raw['address2'] ?? '' ) ),
		'city'     => sanitize_text_field( (string) ( $raw['city'] ?? '' ) ),
		'state'    => strtoupper( sanitize_text_field( (string) ( $raw['state'] ?? '' ) ) ),
		'zip'      => sanitize_text_field( (string) ( $raw['zip'] ?? '' ) ),
		'country'  => 'US',
	];

	$errors = [];
	if ( $a['name'] === '' )     $errors[] = 'Please enter a name.';
	if ( $a['address1'] === '' ) $errors[] = 'Please enter a street address.';
	if ( $a['city'] === '' )     $errors[] = 'Please enter a city.';
	if ( ! isset( sp_store_states()[ $a['state'] ] ) ) $errors[] = 'Please choose a state.';
	if ( ! preg_match( '/^\d{5}(-\d{4})?$/', $a['zip'] ) ) $errors[] = 'Please enter a 5-digit ZIP code.';

	return [ 'address' => $a, 'errors' => $errors ];
}


/*--------------------------------------------------------------
# Endpoints
--------------------------------------------------------------*/

add_action( 'wp_ajax_sp_store_quote', 'sp_store_ajax_quote' );
add_action( 'wp_ajax_nopriv_sp_store_quote', 'sp_store_ajax_quote' );
/**
 * Re-price the cart when the shopper changes something.
 *
 * Returns rendered HTML rather than raw figures, so the markup for a total
 * exists in exactly one place and the page cannot drift from the server's idea
 * of what the order costs.
 */
function sp_store_ajax_quote(): void {
	$cart  = sp_store_current_cart();
	$lines = $cart ? sp_store_cart_lines( (int) $cart['id'] ) : [];
	if ( ! $lines ) wp_send_json_error( [ 'message' => 'Your cart is empty.' ] );

	$quote = sp_store_quote( $lines, [
		'shipping_rate_id' => (int) ( $_POST['shipping_rate_id'] ?? 0 ),
	] );

	wp_send_json_success( [
		'totals'      => sp_store_totals_html( $quote ),
		'shipping'    => sp_store_shipping_choices_html( $quote ),
		'total_cents' => (int) $quote['total_cents'],
	] );
}

add_action( 'wp_ajax_sp_store_place_order', 'sp_store_ajax_place_order' );
add_action( 'wp_ajax_nopriv_sp_store_place_order', 'sp_store_ajax_place_order' );
/**
 * Validate, reserve stock, open the order, and hand off to the processor.
 *
 * The same sequence the old Stripe-only path used, with the difference that the
 * order is created with a total THIS SERVER worked out, rather than one the
 * processor will decide later.
 */
function sp_store_ajax_place_order(): void {
	if ( ! sp_store_provider_ready() ) {
		wp_send_json_error( [ 'message' => 'Payment is not available right now.' ] );
	}

	$cart  = sp_store_current_cart();
	$lines = $cart ? sp_store_cart_lines( (int) $cart['id'] ) : [];
	if ( ! $lines ) wp_send_json_error( [ 'message' => 'Your cart is empty.' ] );

	$email = sanitize_email( (string) ( $_POST['email'] ?? '' ) );
	if ( ! is_email( $email ) ) wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );

	$rate_id = (int) ( $_POST['shipping_rate_id'] ?? 0 );
	$quote   = sp_store_quote( $lines, [ 'shipping_rate_id' => $rate_id ] );

	$address = [];
	if ( $quote['needs_shipping'] ) {
		$clean = sp_store_clean_address( (array) ( $_POST['address'] ?? [] ) );
		if ( $clean['errors'] ) wp_send_json_error( [ 'message' => implode( ' ', $clean['errors'] ) ] );
		$address = $clean['address'];

		// A rate id that is no longer available — the basket changed in another tab,
		// or the order dropped below a free-shipping threshold.
		if ( ! $quote['shipping_rate_id'] ) {
			wp_send_json_error( [ 'message' => 'Please choose a delivery method.' ] );
		}
	}

	sp_store_set_cart_email( (int) $cart['id'], $email );

	// ---- Reserve stock BEFORE the order exists, so a sell-out is reported now
	// rather than after someone has paid.
	$reserved = [];
	foreach ( $lines as $line ) {
		if ( sp_store_commit_stock( (int) $line['variant_id'], (int) $line['qty'] ) ) {
			$reserved[ (int) $line['variant_id'] ] = (int) $line['qty'];
			continue;
		}
		foreach ( $reserved as $vid => $qty ) sp_store_release_stock( $vid, $qty );
		wp_send_json_error( [ 'message' => sprintf(
			'Sorry — "%s" sold out while it was in your cart. Please remove it and try again.',
			sp_store_plain_text( (string) $line['title'] )
		) ] );
	}

	$cart['email'] = $email;
	$order_id = sp_store_create_pending_order( $cart, $lines, $quote, $address );
	if ( ! $order_id ) {
		foreach ( $reserved as $vid => $qty ) sp_store_release_stock( $vid, $qty );
		wp_send_json_error( [ 'message' => 'We could not start your order. Please try again.' ] );
	}

	$payment = sp_store_begin_payment( $order_id );
	if ( is_wp_error( $payment ) ) {
		sp_store_cancel_pending_order( $order_id, $payment->get_error_message() );
		wp_send_json_error( [ 'message' => $payment->get_error_message() ] );
	}

	wp_send_json_success( [
		'shape'  => (string) ( $payment['shape'] ?? 'redirect' ),
		'url'    => (string) ( $payment['url'] ?? '' ),
		'config' => (array) ( $payment['config'] ?? [] ),
		'number' => (string) ( sp_store_get_order( $order_id )['number'] ?? '' ),
	] );
}
