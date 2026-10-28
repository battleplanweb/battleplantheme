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

/** Does anything in these lines get posted? Asked on its own, never inferred from the rates. */
function sp_store_lines_need_shipping( array $lines ): bool {
	foreach ( $lines as $line ) {
		$variant = sp_store_get_variant( (int) $line['variant_id'] );
		if ( $variant && ! empty( $variant['requires_shipping'] ) ) return true;
	}
	return false;
}

/*
 * SHIPPING BY DISTANCE (Extras). For freight — a 40 lb steel apparatus costs what the
 * truck costs, and the truck costs by the mile. Priced exactly the way the old
 * "Distance Based Shipping Calculator" plugin did, so a migrating store's customers
 * see the same figures: the DRIVING distance from the ship-from address, times the
 * per-mile rate of the band that distance falls in (the whole trip at that band's
 * rate, not tiered), plus a flat handling fee, once per order.
 */

/** Is distance pricing switched on? When it is, it replaces the rate table entirely. */
function sp_store_distance_on(): bool {
	return sp_store_get_setting( 'feature_distance_shipping', '' ) === '1';
}

/**
 * The distance settings: ship-from address, handling fee, label, and mile bands.
 * A band is [ from, to, rate, fee ]: miles, cents-per-mile (fractions allowed: 29.5),
 * and a flat amount in cents added when a trip falls in that band. The flat amount is
 * per BAND because that is how freight is really quoted — the first real store on
 * this (Aerial Spiral) charged $186 within 250 miles but $255–$260 further out, on top
 * of the per-mile rate. The store-wide handling fee is added on top of both.
 */
function sp_store_distance_config(): array {
	$bands = json_decode( sp_store_get_setting( 'distance_bands', '' ), true );
	$clean = [];
	foreach ( is_array( $bands ) ? $bands : [] as $b ) {
		$from = max( 0, (float) ( $b['from'] ?? 0 ) );
		$to   = max( 0, (float) ( $b['to'] ?? 0 ) );
		$rate = max( 0, (float) ( $b['rate'] ?? 0 ) );
		$fee  = max( 0, (int) ( $b['fee'] ?? 0 ) );
		if ( $to <= 0 || $to < $from ) continue;
		$clean[] = [ 'from' => $from, 'to' => $to, 'rate' => $rate, 'fee' => $fee ];
	}
	usort( $clean, static fn( $a, $b ) => $a['from'] <=> $b['from'] );

	return [
		'origin'   => trim( sp_store_get_setting( 'distance_origin', '' ) ),
		'handling' => max( 0, (int) sp_store_get_setting( 'distance_handling_cents', '0' ) ),
		'label'    => trim( sp_store_get_setting( 'distance_label', '' ) ) ?: 'Shipping',
		'bands'    => $clean,
	];
}

/** Enough of an address to route a truck to. */
function sp_store_address_complete( array $a ): bool {
	return trim( (string) ( $a['address1'] ?? '' ) ) !== ''
		&& trim( (string) ( $a['city'] ?? '' ) ) !== ''
		&& trim( (string) ( $a['state'] ?? '' ) ) !== ''
		&& preg_match( '/^\d{5}(-\d{4})?$/', trim( (string) ( $a['zip'] ?? '' ) ) );
}

/**
 * Driving miles from the ship-from address, or null when it cannot be worked out.
 *
 * Google's Routes API through the Mileage tracker's own call — one integration, one
 * key (Settings → API Keys → Google), no second copy to maintain. Cached for 30 days
 * per destination: the checkout re-prices on every change, and one shopper fiddling
 * with their address must not become a dozen paid API calls.
 */
function sp_store_distance_miles( array $address ): ?float {
	$cfg = sp_store_distance_config();
	if ( $cfg['origin'] === '' || ! sp_store_address_complete( $address ) ) return null;
	if ( ! function_exists( 'site_pulse_mileage_distance_matrix_call' ) || ! function_exists( 'site_pulse_mileage_google_key' ) ) return null;

	$dest  = trim( $address['address1'] . ', ' . $address['city'] . ', ' . $address['state'] . ' ' . $address['zip'] );
	$cache = 'sp_store_dist_' . md5( strtolower( $cfg['origin'] . '|' . $dest ) );
	$hit   = get_transient( $cache );
	if ( $hit !== false ) return $hit === 'none' ? null : (float) $hit;

	$key = (string) site_pulse_mileage_google_key();
	if ( $key === '' ) return null;

	$matrix = site_pulse_mileage_distance_matrix_call( [ $cfg['origin'] ], [ $dest ], $key );
	$miles  = $matrix[0][0] ?? null;

	// An address with no route is remembered briefly, so a typo is not re-asked on
	// every keystroke; a real failure (API down) is not cached at all.
	if ( $miles === null ) {
		if ( is_array( $matrix ) ) set_transient( $cache, 'none', HOUR_IN_SECONDS );
		return null;
	}
	set_transient( $cache, (string) $miles, 30 * DAY_IN_SECONDS );
	return (float) $miles;
}

/** What shipping that many miles costs, in cents, or null when it is beyond the last band. */
function sp_store_distance_price( float $miles ): ?int {
	$cfg = sp_store_distance_config();
	foreach ( $cfg['bands'] as $band ) {
		// Bands as the client typed them read "0–100, 101–250…", so a trip of 100.4
		// miles has to land somewhere: anything up to the next band's start counts.
		if ( $miles <= $band['to'] + 0.999 && $miles >= $band['from'] - 0.999 ) {
			return (int) round( $miles * $band['rate'] ) + (int) $band['fee'] + $cfg['handling'];
		}
	}
	return null;
}

/**
 * The shipping rates available for a set of lines, as neutral rows.
 *
 * Shaped like no processor in particular. sp_store_shipping_options() wraps
 * these into what Stripe wants, the on-site checkout renders the same rows as
 * radio buttons, and Clover never sees them at all — it receives only the
 * chosen amount. The matching rules live in ONE place so those three can never
 * quietly disagree about what postage costs.
 *
 * With distance pricing on, the answer is a single rate worked out from $address,
 * or nothing until there is an address to route to.
 *
 * @return array<int, array{id:int, name:string, amount_cents:int}>
 */
function sp_store_shipping_rates_for( array $lines, array $address = [] ): array {
	global $wpdb;

	if ( sp_store_distance_on() ) {
		if ( ! sp_store_lines_need_shipping( $lines ) ) return [];
		$miles = sp_store_distance_miles( $address );
		if ( $miles === null ) return [];
		$cents = sp_store_distance_price( $miles );
		if ( $cents === null ) return [];
		return [ [
			'id'           => -1,   // not a row in the rate table; the quote picks it automatically
			'name'         => sp_store_distance_config()['label'] . ' (' . number_format( $miles ) . ' miles)',
			'amount_cents' => $cents,
			'miles'        => $miles,
		] ];
	}

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
function sp_store_shipping_rate_pick( array $lines, int $rate_id, array $address = [] ): ?array {
	foreach ( sp_store_shipping_rates_for( $lines, $address ) as $rate ) {
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
 *                     address — the delivery address so far; distance pricing needs it.
 */
function sp_store_quote( array $lines, array $args = [] ): array {
	$shipping_rate_id = (int) ( $args['shipping_rate_id'] ?? 0 );
	$address          = (array) ( $args['address'] ?? [] );

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

	// Asked directly. Inferring it from "are there any rates" let a shippable basket that
	// matched no rate skip the address and go out with no shipping charged at all.
	$needs    = sp_store_lines_need_shipping( $lines );
	$rates    = $needs ? sp_store_shipping_rates_for( $lines, $address ) : [];
	$distance = $needs && sp_store_distance_on();

	// Distance pricing has exactly one answer, so it is chosen for the shopper.
	$chosen   = $distance
		? ( $rates[0] ?? null )
		: ( $shipping_rate_id ? sp_store_shipping_rate_pick( $lines, $shipping_rate_id, $address ) : null );
	$shipping = $chosen ? (int) $chosen['amount_cents'] : 0;

	// What the page should say while there is no shipping figure yet.
	$ship_note = '';
	if ( $distance && ! $chosen ) {
		$ship_note = sp_store_address_complete( $address )
			? 'We could not work out shipping to that address automatically. Please check it, or contact us for a quote.'
			: 'Enter your delivery address to see the shipping cost.';
	}

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
		'needs_shipping'   => $needs,
		'shipping_rates'   => $rates,
		'shipping_by_distance' => $distance,
		'shipping_note'    => $ship_note,
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
