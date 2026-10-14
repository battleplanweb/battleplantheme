<?php
/* Battle Plan Web Design — Site Pulse: Store Totals

/*--------------------------------------------------------------
What an order costs. Computed HERE, by us, with no payment processor involved.

This exists because the store could not previously answer that question. Stripe
Checkout was handed the line items and worked out the tax, offered the shipping
choices and produced the total — fine until a client wanted a different
processor. Clover calculates no destination tax and presents no shipping
choices, so everything that had been delegated to Stripe had to come home.

That turns out to be the right shape regardless. A shop knowing its own prices
is not a Clover workaround; a payment processor's job is to move an amount of
money, not to decide what is owed.

Every figure is INTEGER CENTS. The tax rate is the only percentage in the file
and it is rounded ONCE, against the whole taxable base rather than per line, so
an order's tax depends only on its taxable total and never on how the basket
happens to be divided into lines. Three $25 lines and one $75 line both pay
619c; rounding each line first would pay 618c and disagree with itself.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shipping
# Tax
# The Quote
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Shipping
--------------------------------------------------------------*/

/**
 * The shipping rates available for a set of lines, as neutral rows.
 *
 * Shaped like no processor in particular. sp_store_shipping_options() wraps
 * these into what Stripe wants, the on-site checkout renders the same rows as
 * radio buttons, and Clover never sees them at all — it receives only the
 * chosen amount. The matching rules live in ONE place so those three can never
 * quietly disagree about what postage costs.
 *
 * @return array<int, array{id:int, name:string, amount_cents:int}>
 */
function sp_store_shipping_rates_for( array $lines ): array {
	global $wpdb;

	$weight   = 0;
	$subtotal = 0;
	$items    = 0;
	$needs    = false;

	foreach ( $lines as $line ) {
		$subtotal += (int) $line['line_cents'];
		$items    += (int) $line['qty'];

		$variant = sp_store_get_variant( (int) $line['variant_id'] );
		if ( $variant && ! empty( $variant['requires_shipping'] ) ) {
			$needs   = true;
			$weight += (int) $variant['weight_grams'] * (int) $line['qty'];
		}
	}

	// Nothing physical in the basket, so no rates — and the checkout must then not
	// ask for a delivery address it has no use for.
	if ( ! $needs ) return [];

	$rates = $wpdb->get_results(
		"SELECT * FROM " . sp_store_table( 'shipping_rates' ) . "
		  WHERE status = 'active' ORDER BY display_order ASC, id ASC",
		ARRAY_A
	) ?: [];

	$out = [];
	foreach ( $rates as $rate ) {
		// A band with a 0 maximum means "no upper limit" — otherwise every rate would
		// need a deliberately enormous number typed into it.
		if ( (int) $rate['min_subtotal_cents'] > $subtotal ) continue;
		if ( (int) $rate['max_subtotal_cents'] > 0 && $subtotal > (int) $rate['max_subtotal_cents'] ) continue;
		if ( (int) $rate['min_weight_grams'] > $weight ) continue;
		if ( (int) $rate['max_weight_grams'] > 0 && $weight > (int) $rate['max_weight_grams'] ) continue;

		$amount = (int) $rate['amount_cents'];
		if ( $rate['method'] === 'free' ) {
			$amount = 0;
		} elseif ( (int) $rate['per_item_cents'] > 0 ) {
			// The base amount covers the first item; each additional one adds.
			$amount += (int) $rate['per_item_cents'] * max( 0, $items - 1 );
		}

		$out[] = [
			'id'           => (int) $rate['id'],
			'name'         => (string) $rate['name'],
			'amount_cents' => max( 0, $amount ),
		];
	}

	return $out;
}

/**
 * One rate by id, but only if it is genuinely available for these lines.
 *
 * Never trust the posted id on its own: a basket can change between choosing a
 * rate and paying for it, and "free over $75" must not survive removing the
 * item that got the order over $75.
 */
function sp_store_shipping_rate_pick( array $lines, int $rate_id ): ?array {
	foreach ( sp_store_shipping_rates_for( $lines ) as $rate ) {
		if ( $rate['id'] === $rate_id ) return $rate;
	}
	return null;
}


/*--------------------------------------------------------------
# Tax
--------------------------------------------------------------*/

/**
 * Is the shipping charge itself taxable?
 *
 * It depends on the state and it is wrong in both directions, so it is a
 * setting rather than an assumption. Default ON: in most US states, Texas
 * included, delivery charges on taxable goods are themselves taxable.
 */
function sp_store_tax_on_shipping(): bool {
	return sp_store_get_setting( 'tax_shipping', '1' ) === '1';
}

/**
 * Tax for a quote, in cents.
 *
 * Rounded ONCE against the combined base. Rounding each line and summing would
 * make the figure depend on how the basket happened to be divided into lines —
 * 3 x $25 would pay 618c where a single $75 line pays 619c.
 *
 * round() here is PHP's half-away-from-zero, which is what a customer expects
 * of a half-cent; it is NOT the banker's rounding some languages default to.
 */
function sp_store_tax_cents( int $taxable_goods, int $shipping_cents ): int {
	if ( sp_store_tax_mode() !== 'flat' ) return 0;

	$rate = sp_store_flat_tax_rate();
	if ( $rate <= 0 || $taxable_goods <= 0 ) return 0;

	$base = $taxable_goods;
	if ( sp_store_tax_on_shipping() ) $base += max( 0, $shipping_cents );

	return (int) round( $base * ( $rate / 100 ) );
}


/*--------------------------------------------------------------
# The Quote
--------------------------------------------------------------*/

/**
 * Price a basket: subtotal, shipping, tax, total — every figure in cents.
 *
 * The ONE place an order total is decided. The checkout displays what this
 * returns, the order stores what this returns, and the processor charges what
 * was stored. A total never travels out to the browser and back to be trusted.
 *
 * @param array $lines Cart lines (variant_id, qty, line_cents).
 * @param array $args  shipping_rate_id — the customer's choice; 0 if not chosen yet.
 */
function sp_store_quote( array $lines, array $args = [] ): array {
	$shipping_rate_id = (int) ( $args['shipping_rate_id'] ?? 0 );

	$subtotal      = 0;
	$taxable_goods = 0;

	foreach ( $lines as $line ) {
		$line_total = (int) $line['line_cents'];
		$subtotal  += $line_total;

		// Only taxable items enter the taxable base — a gift card or a digital
		// download in the same basket must not be taxed as merchandise.
		$variant = sp_store_get_variant( (int) $line['variant_id'] );
		if ( ! $variant || ! empty( $variant['taxable'] ) ) $taxable_goods += $line_total;
	}

	$rates    = sp_store_shipping_rates_for( $lines );
	$chosen   = $shipping_rate_id ? sp_store_shipping_rate_pick( $lines, $shipping_rate_id ) : null;
	$shipping = $chosen ? (int) $chosen['amount_cents'] : 0;

	// When the PROCESSOR does the tax (Stripe Tax), this stays 0 and the processor
	// adds it at its own checkout. The flag says so out loud, so the on-site
	// checkout can print "tax calculated at payment" rather than implying none.
	$provider_tax = sp_store_tax_mode() === 'provider';
	$tax          = $provider_tax ? 0 : sp_store_tax_cents( $taxable_goods, $shipping );

	return [
		'subtotal_cents'   => $subtotal,
		'discount_cents'   => 0,
		'shipping_cents'   => $shipping,
		'tax_cents'        => $tax,
		'total_cents'      => $subtotal + $shipping + $tax,
		'taxable_cents'    => $taxable_goods,
		'needs_shipping'   => ! empty( $rates ),
		'shipping_rates'   => $rates,
		'shipping_rate_id' => $chosen ? (int) $chosen['id'] : 0,
		'shipping_name'    => $chosen ? (string) $chosen['name'] : '',
		'tax_by_provider'  => $provider_tax,
		'currency'         => sp_store_currency(),
	];
}

/** The same quote with every amount also formatted for display. */
function sp_store_quote_display( array $quote ): array {
	foreach ( [ 'subtotal', 'discount', 'shipping', 'tax', 'total' ] as $key ) {
		$quote[ $key ] = sp_store_money( (int) $quote[ $key . '_cents' ], $quote['currency'] );
	}
	return $quote;
}
