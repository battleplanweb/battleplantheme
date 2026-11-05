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

/**
 * The store's own addresses.
 *
 * Checkout and the receipt are real URLs -- /checkout/ and /order-received/{number}/ --
 * and not query arguments on the site root. Rendering them at the root meant inheriting
 * the front page: its Page Top and Page Bottom blocks printed around them, body_class()
 * said `home`, and a site whose home page lives entirely in those blocks (so it hides
 * #wrapper-content on body.home) swallowed the checkout whole.
 */
function sp_store_checkout_slug(): string {
	return sanitize_title( (string) apply_filters( 'sp_store_checkout_slug', 'checkout' ) ) ?: 'checkout';
}
function sp_store_thanks_slug(): string {
	return sanitize_title( (string) apply_filters( 'sp_store_thanks_slug', 'order-received' ) ) ?: 'order-received';
}

/** Where the cart's checkout button goes. */
function sp_store_checkout_page_url(): string {
	return get_option( 'permalink_structure' )
		? home_url( user_trailingslashit( sp_store_checkout_slug() ) )
		: add_query_arg( 'sp_store_checkout', '1', home_url( '/' ) );
}

/** Where a processor sends the shopper once it has their money. */
function sp_store_thanks_url( string $number ): string {
	return get_option( 'permalink_structure' )
		? home_url( user_trailingslashit( sp_store_thanks_slug() . '/' . rawurlencode( $number ) ) )
		: add_query_arg( 'sp_store_thanks', rawurlencode( $number ), home_url( '/' ) );
}

add_filter( 'query_vars', 'sp_store_route_query_vars' );
function sp_store_route_query_vars( array $vars ): array {
	$vars[] = 'sp_store_checkout';
	$vars[] = 'sp_store_thanks';
	return $vars;
}

// Priority 4: before sp_store_register_cpt() at 5, which is what flushes the rules.
// Rules added after that flush would not be in the set it wrote.
add_action( 'init', 'sp_store_route_rewrites', 4 );
function sp_store_route_rewrites(): void {
	if ( ! sp_store_active() ) return;
	add_rewrite_rule( '^' . sp_store_checkout_slug() . '/?$', 'index.php?sp_store_checkout=1', 'top' );
	add_rewrite_rule( '^' . sp_store_thanks_slug() . '/([^/]+)/?$', 'index.php?sp_store_thanks=$matches[1]', 'top' );
}

// Adding or renaming a route flushes the rules once, the same way the catalogue slug does.
add_filter( 'sp_store_rewrite_signature', 'sp_store_route_rewrite_signature' );
function sp_store_route_rewrite_signature( array $parts ): array {
	$parts[] = sp_store_checkout_slug();
	$parts[] = sp_store_thanks_slug();
	return $parts;
}

/**
 * A store route's value, from the pretty URL or the old query argument.
 *
 * The query arguments still work: a page cached before the rewrite rules were flushed
 * may hold the old checkout link, and a processor may hold the old return URL against
 * an order placed minutes before a deploy.
 */
function sp_store_route_value( string $var ): string {
	$value = (string) get_query_var( $var, '' );
	if ( $value === '' && isset( $_GET[ $var ] ) ) $value = (string) wp_unslash( $_GET[ $var ] );
	return sanitize_text_field( $value );
}

/**
 * Stop the main query claiming to be the front page, and label the body.
 *
 * A store route renders its own markup and exits, but header.php runs first and
 * WordPress has already decided what this request is -- the rewrite resolves to
 * index.php with no post of its own, so the query reads as the blog home and
 * body_class() prints `home`. Child-theme CSS written for the real front page then
 * applies to the checkout. Clearing the flags also empties the queried object, which
 * is what stops the front page's Page Top / Page Bottom blocks printing around it.
 */
function sp_store_virtual_page( string $class ): void {
	global $wp_query;

	if ( $wp_query instanceof WP_Query ) {
		$wp_query->is_home              = false;
		$wp_query->is_posts_page        = false;
		$wp_query->is_archive           = false;
		$wp_query->is_post_type_archive = false;
		$wp_query->is_404               = false;
		$wp_query->queried_object       = null;
		$wp_query->queried_object_id    = 0;
	}

	if ( function_exists( 'bp_hide_page_blocks' ) ) bp_hide_page_blocks();

	add_filter( 'body_class', function ( $classes ) use ( $class ) {
		// Dropped as well as unset: the flags above are enough for WordPress itself,
		// but a child theme adding `home` by hand would hide this page all the same.
		$classes   = array_values( array_diff( (array) $classes, [ 'home', 'blog' ] ) );
		$classes[] = 'store-page';
		$classes[] = $class;
		return $classes;
	} );
}

// Priority 5: ahead of WordPress's own redirect_canonical() at 10, which would otherwise
// try to "correct" a URL that belongs to no post.
add_action( 'template_redirect', 'sp_store_checkout_page', 5 );
function sp_store_checkout_page(): void {
	if ( ! sp_store_active() || sp_store_route_value( 'sp_store_checkout' ) === '' ) return;

	sp_store_virtual_page( 'store-checkout-page' );

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

	// Order notes: optional, for the things a shop needs to be told ("clockwise coil,
	// please"). Stored on the order's customer_note and shown to staff on the order.
	$out .= '<section class="store-checkout-section">';
	$out .= '<h2>Order notes <span class="store-field-note">(optional)</span></h2>';
	$out .= '<label class="store-field"><span class="screen-reader-text">Order notes</span>';
	$out .= '<textarea id="sp-co-note" rows="3" maxlength="1000" placeholder="Anything we should know about your order?"></textarea>';
	$out .= '</label>';
	$out .= '</section>';

	// Terms the basket's products require (Extras). '' when none, or when the feature is off.
	if ( function_exists( 'sp_store_agreements_checkout_html' ) ) $out .= sp_store_agreements_checkout_html( $lines );

	$out .= '<section class="store-checkout-section">';
	$out .= '<h2>Payment</h2>';
	$out .= '<div class="store-pay-mount" data-store-pay></div>';
	$out .= '<p class="store-checkout-error" data-store-co-error hidden></p>';
	$out .= '<button type="button" class="store-place-order" data-store-place>Place order</button>';
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
	// Distance pricing: one figure, worked out from the address — nothing to choose.
	if ( ! empty( $quote['shipping_by_distance'] ) ) {
		if ( empty( $quote['shipping_rates'] ) ) {
			return '<p class="store-field-note" data-store-ship-note>' . esc_html( (string) $quote['shipping_note'] ) . '</p>';
		}
		$rate = $quote['shipping_rates'][0];
		return '<div class="store-ship-option is-active"><input type="hidden" name="sp_ship_rate" value="-1">'
			. '<span class="store-ship-name">' . esc_html( $rate['name'] ) . '</span>'
			. '<span class="store-ship-price">' . sp_store_money( (int) $rate['amount_cents'] ) . '</span></div>';
	}

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

	// The address as typed so far, unvalidated: distance pricing re-prices as soon as it
	// is complete enough to route to, before the shopper presses anything.
	$partial = sp_store_clean_address( wp_unslash( (array) ( $_POST['address'] ?? [] ) ) )['address'];

	$quote = sp_store_quote( $lines, [
		'shipping_rate_id' => (int) ( $_POST['shipping_rate_id'] ?? 0 ),
		'address'          => $partial,
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

	// A renewing membership has to go through on its own (see sp_member_checkout_problem).
	if ( function_exists( 'sp_member_checkout_problem' ) ) {
		$problem = sp_member_checkout_problem( $lines );
		if ( $problem !== '' ) wp_send_json_error( [ 'message' => $problem ] );
	}

	$rate_id = (int) ( $_POST['shipping_rate_id'] ?? 0 );

	// The address is validated BEFORE pricing, because with distance shipping the
	// address is part of what the order costs.
	$address = [];
	if ( sp_store_lines_need_shipping( $lines ) ) {
		$clean = sp_store_clean_address( wp_unslash( (array) ( $_POST['address'] ?? [] ) ) );
		if ( $clean['errors'] ) wp_send_json_error( [ 'message' => implode( ' ', $clean['errors'] ) ] );
		$address = $clean['address'];
	}

	$quote = sp_store_quote( $lines, [ 'shipping_rate_id' => $rate_id, 'address' => $address ] );

	// A rate id that is no longer available — the basket changed in another tab, the
	// order dropped below a free-shipping threshold, or no distance price could be found.
	if ( $quote['needs_shipping'] && ! $quote['shipping_rate_id'] ) {
		wp_send_json_error( [ 'message' => $quote['shipping_note'] !== '' ? $quote['shipping_note'] : 'Please choose a delivery method.' ] );
	}

	// Agreements are checked against what the BASKET needs, not what the page sent.
	$agreed = [ 'list' => [], 'name' => '' ];
	if ( function_exists( 'sp_store_agreements_check' ) ) {
		$agreed = sp_store_agreements_check(
			$lines,
			array_map( 'intval', (array) ( $_POST['agree'] ?? [] ) ),
			(string) wp_unslash( $_POST['sign_name'] ?? '' )
		);
		if ( is_wp_error( $agreed ) ) wp_send_json_error( [ 'message' => $agreed->get_error_message() ] );
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

	$note = trim( sanitize_textarea_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ) );
	if ( $note !== '' ) {
		global $wpdb;
		$wpdb->update( sp_store_table( 'orders' ), [ 'customer_note' => mb_substr( $note, 0, 1000 ) ], [ 'id' => $order_id ] );
	}

	// The snapshot is written now, against the pending order, so it exists before any
	// money moves — an order is never paid without its agreements on record.
	if ( function_exists( 'sp_store_agreements_record' ) ) sp_store_agreements_record( $order_id, $agreed );

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
