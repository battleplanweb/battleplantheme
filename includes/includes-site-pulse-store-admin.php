<?php
/* Battle Plan Web Design — Site Pulse: Store Settings

/*--------------------------------------------------------------
Settings → Store. The store's non-credential configuration.

Payment KEYS deliberately live elsewhere — on Settings → API Keys with every
other credential, encrypted, behind `manage_api_keys`. What belongs here is the
configuration a client changes themselves: how the shop looks and behaves.

The fields are DECLARED, not hand-written. One entry in sp_store_setting_defs()
gives a field its type, default, sanitiser and help text, and the get/save
endpoints and the panel all read from that one list — so adding tax mode or a
currency selector later is a row here rather than four edits in three files.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Field Definitions
# Endpoints
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Field Definitions
--------------------------------------------------------------*/

/**
 * Every setting this screen manages.
 *
 * `type` drives both the control that renders and the sanitiser that runs, so a
 * field cannot be displayed as one thing and saved as another.
 */
/**
 * The storefront colours a client may change, as CSS-variable name => label + note.
 *
 * A short curated list of the colours that actually mean something, not every token
 * in the stylesheet. An unset
 * value is left ALONE rather than written as a default — so the stylesheet keeps
 * inheriting the site's own palette until somebody deliberately overrides a colour,
 * and a site that never opens this screen looks exactly as it does today.
 *
 * Deliberately excluded: --store-border and friends, which are `1px solid …`
 * shorthands rather than colours, and would need splitting before a picker could
 * safely write to them.
 */
function sp_store_color_roles(): array {
	return [
		'store-btn-bg'       => [ 'label' => 'Button background', 'desc' => 'Add to Cart, Checkout, and the joined buy pill.' ],
		'store-btn-color'    => [ 'label' => 'Button text',       'desc' => 'Wording on those buttons, and the quantity caret beside it.' ],
		'store-title-color'  => [ 'label' => 'Product title',     'desc' => 'Product names on the shop grid.' ],
		'store-price-color'  => [ 'label' => 'Price',             'desc' => 'The normal, undiscounted price.' ],
		'store-sale-color'   => [ 'label' => 'Sale price',        'desc' => 'What a discounted item is being sold for.' ],
		'store-was-color'    => [ 'label' => 'Was price',         'desc' => 'The struck-through price beside a sale price.' ],
		'store-card-bg'      => [ 'label' => 'Card background',   'desc' => 'Behind each product on the shop grid.' ],
		'store-muted'        => [ 'label' => 'Secondary text',    'desc' => 'Stock lines, shipping notes, and other quiet text.' ],
		'store-soldout-bg'   => [ 'label' => 'Sold out badge',    'desc' => 'The badge over a product that has run out.' ],
		'store-soldout-color'=> [ 'label' => 'Sold out text',     'desc' => 'Wording inside that badge.' ],
	];
}

/**
 * Every custom property declared in a :root block across the stylesheets that build
 * the storefront, later files winning — the same order the browser would apply them.
 *
 * This exists because the colour pickers have to show the colour ACTUALLY IN USE, and
 * they are rendered inside Site Pulse, which deliberately opts out of the client
 * site's CSS. So there is no DOM on that screen to read the values from, and they have
 * to be resolved from the files instead.
 *
 * Only :root blocks are read. A variable redefined inside a media query or another
 * selector is not followed — the resolver returns nothing for it rather than guessing,
 * and the picker falls back to showing no swatch rather than a wrong one.
 */
function sp_store_css_var_map(): array {
	static $map = null;
	if ( $map !== null ) return $map;

	$map   = [];
	$child = get_stylesheet_directory();
	$files = [
		get_template_directory() . '/style.css',
		$child . '/dist/site.min.css',
		$child . '/style-site.css',
		get_template_directory() . '/style-store.css',
	];

	foreach ( $files as $file ) {
		if ( ! file_exists( $file ) ) continue;
		$css = (string) file_get_contents( $file );

		if ( ! preg_match_all( '/:root\s*\{([^}]*)\}/s', $css, $blocks ) ) continue;
		foreach ( $blocks[1] as $block ) {
			if ( ! preg_match_all( '/(--[A-Za-z0-9_-]+)\s*:\s*([^;]+);/', $block, $vars, PREG_SET_ORDER ) ) continue;
			foreach ( $vars as $var ) {
				$map[ $var[1] ] = trim( $var[2] );
			}
		}
	}

	return $map;
}

/** rgb()/rgba() to #rrggbb, because the framework's palette is written in rgba(). */
function sp_store_rgb_to_hex( string $value ): string {
	if ( ! preg_match( '/rgba?\(\s*([0-9.]+)[,\s]+([0-9.]+)[,\s]+([0-9.]+)/i', $value, $m ) ) return '';
	return sprintf( '#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3] );
}

/**
 * Resolve a custom property to a literal hex, following var() chains.
 *
 * Depth-capped: a stylesheet with a circular reference would otherwise recurse until
 * PHP gives up, and a colour picker is not worth a fatal.
 */
function sp_store_resolve_var( string $token, int $depth = 0 ): string {
	if ( $depth > 8 ) return '';

	$map   = sp_store_css_var_map();
	$value = trim( (string) ( $map[ $token ] ?? '' ) );
	if ( $value === '' ) return '';

	if ( preg_match( '/^var\(\s*(--[A-Za-z0-9_-]+)/', $value, $m ) ) {
		return sp_store_resolve_var( $m[1], $depth + 1 );
	}
	if ( preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) return strtolower( $value );
	if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $m ) ) {
		return strtolower( '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3] );
	}
	return sp_store_rgb_to_hex( $value );
}

function sp_store_setting_defs(): array {
	$defs = [
		'variant_picker' => [
			'label'   => 'How customers choose options',
			'type'    => 'choice',
			'default' => 'dropdown',
			'choices' => [
				'dropdown' => 'Drop-down menus — compact, best when a product has many choices or long names',
				'tiles'    => 'Tiles — a button per choice, showing the tagged picture where there is one',
			],
			'help'    => 'Applies to every product in the shop. Tiles use the picture you tagged with that choice under Product Images, so a colour swatch needs no extra upload.',
		],
		'products_per_page' => [
			'label'   => 'Products per page',
			'type'    => 'choice',
			'default' => '24',
			'choices' => [
				'12' => '12',
				'24' => '24',
				'48' => '48',
				'96' => '96',
			],
			'help'    => 'How many products a shop or category page shows before it paginates. Customers can switch to a different count from the shop page itself; this is what they get before they choose.',
		],
		'default_sort' => [
			'label'   => 'Default product order',
			'type'    => 'choice',
			'default' => 'featured',
			'choices' => [
				'featured'     => 'Featured — the order you drag products into',
				'newest'       => 'New Products — most recently added first',
				'name'         => 'Name (A–Z)',
				'price-low'    => 'Price Low to High',
				'price-high'   => 'Price High to Low',
				'best-sellers' => 'Top Sellers — most units sold first',
			],
			'help'    => 'The order products appear in before a customer sorts them. <strong>Featured</strong> uses the Order field on each product, so it is the only one you control by hand. Top Sellers only appears in the customer drop-down once something has actually sold.',
		],
		'buy_style' => [
			'label'   => 'Add to Cart button',
			'type'    => 'choice',
			'default' => 'standard',
			'choices' => [
				'standard' => 'Standard — a quantity box beside the button',
				'joined'   => 'Joined — quantity and button fused into one pill',
			],
			'help'    => 'The joined style uses a compact quantity drop-down instead of a number box, which is why it fits against the button.',
		],
		'buy_label' => [
			'label'   => 'Button wording',
			'type'    => 'text',
			'default' => 'Add to Cart',
			'help'    => 'e.g. <code>Add to Cart</code>, <code>Add to Basket</code>, <code>Add to Bag</code>.',
		],
		'buy_note' => [
			'label'   => 'Second line on the button',
			'type'    => 'text',
			'default' => '',
			'help'    => 'Optional small text under the button wording, e.g. <code>Get It Shipped</code>. Only shown by the joined style; leave blank for none.',
		],
		'number_prefix' => [
			'label'   => 'Order number prefix',
			'type'    => 'text',
			'default' => 'BP',
			'help'    => 'Two letters at the front of every order number, e.g. <code>BP-K4R9TM</code>. Order numbers are random rather than sequential, so they do not tell customers how many orders you have taken.',
		],
		'allow_promo_codes' => [
			'label'   => 'Discount codes',
			'type'    => 'toggle',
			'default' => '',
			'help'    => 'Shows a promotion-code box at checkout. The codes themselves are created in your Stripe dashboard under Products → Coupons.',
		],
		'tax_mode' => [
			'label'   => 'Sales tax',
			'type'    => 'choice',
			'default' => 'none',
			'section' => 'Tax and shipping',
			'choices' => [
				'none'     => 'Do not charge tax',
				'flat'     => 'One flat rate on every order',
				'provider' => 'Let the payment processor work it out (Stripe Tax only — Clover cannot)',
			],
			'help'    => 'A flat rate is the usual choice when you ship mostly within one state. It is always slightly wrong somewhere, because local rates differ by city and county, so confirm the rate with whoever files the return.',
		],
		'tax_flat_rate' => [
			'label'   => 'Flat tax rate (%)',
			'type'    => 'text',
			'default' => '0',
			'section' => 'Tax and shipping',
			'help'    => 'Used only by the flat-rate option above. Texas, for example, is 6.25% state plus up to 2% local, so 8.25% is the ceiling.',
		],
		'tax_shipping' => [
			'label'   => 'Charge tax on shipping too',
			'type'    => 'toggle',
			'default' => '1',
			'section' => 'Tax and shipping',
			'help'    => 'In most states, including Texas, the delivery charge on taxable goods is itself taxable. Turn this off only if you have been told your state does not.',
		],
		'description_heading' => [
			'label'   => 'Heading above the description',
			'type'    => 'text',
			'default' => 'About This Item',
			'help'    => 'Sits between the buy button and the product description, e.g. <code>About This Item</code>, <code>Details</code>, <code>Specifications</code>. Leave blank for no heading.',
		],

		// ---- Extras: features most stores never need. Each is OFF until switched on,
		// and while off none of its fields appear anywhere — not on products, not at
		// checkout, not in emails.
		'feature_memberships' => [
			'label'   => 'Memberships',
			'type'    => 'toggle',
			'default' => '',
			'section' => 'Extras',
			'help'    => 'For selling access rather than (or as well as) things: lessons, videos, member pages. Buying a product can give a membership, members get a login and a My Account page, and you choose which pages only members can see. Plans are set up in their own card once this is on.',
		],
		'feature_agreements' => [
			'label'   => 'Purchase agreements',
			'type'    => 'toggle',
			'default' => '',
			'section' => 'Extras',
			'help'    => 'Terms a customer must accept at checkout before buying certain products, such as a waiver or a warranty agreement, with a typed signature if you want one. A copy of exactly what they agreed to is kept on the order. Agreements are written in their own card once this is on.',
		],
		'feature_downloads' => [
			'label'   => 'Downloads',
			'type'    => 'toggle',
			'default' => '',
			'section' => 'Extras',
			'help'    => 'Files a buyer receives, such as a PDF, an e-book or an audio course. Attach them to a product in its Extras; buyers get private links in their receipt and on their account page, and a refund switches the links off. The files are kept out of the media library.',
		],
		'feature_distance_shipping' => [
			'label'   => 'Shipping by distance',
			'type'    => 'toggle',
			'default' => '',
			'section' => 'Extras',
			'help'    => 'For freight and heavy items: shipping is priced from the driving distance to the customer&rsquo;s address, at a per-mile rate you set for each distance band, plus a handling fee. Replaces the shipping rates table while it is on. Needs the Google key under Settings &rarr; API Keys.',
		],
	];

	// The processor list is BUILT from the registry, so the picker can never offer
	// one that is not actually wired up, and a new provider needs no edit here.
	if ( function_exists( 'sp_store_providers' ) ) {
		$choices = [];
		foreach ( sp_store_providers() as $pid => $def ) {
			$choices[ $pid ] = $def['label'] . ( ! empty( $def['note'] ) ? ' — ' . $def['note'] : '' );
		}

		if ( count( $choices ) > 1 ) {
			$defs = [ 'payment_provider' => [
				'label'   => 'Payment processor',
				'type'    => 'choice',
				'default' => 'stripe',
				'choices' => $choices,
				'help'    => 'Who takes the money. The store works out the subtotal, shipping and tax itself either way — the processor only collects the amount it is given.',
			] ] + $defs;
		}

		// Offer provider-calculated tax only where the provider can actually do it.
		// Leaving it selectable would let a site choose an option that silently
		// charges no tax at all.
		if ( ! sp_store_provider_does_tax() && isset( $defs['tax_mode']['choices']['provider'] ) ) {
			unset( $defs['tax_mode']['choices']['provider'] );
		}
	}

	// Colours are appended from the role list rather than typed out again, so the
	// pickers and the CSS variables they write can never drift apart.
	foreach ( sp_store_color_roles() as $var => $role ) {
		$defs[ 'color_' . $var ] = [
			'label'   => $role['label'],
			'type'    => 'color',
			'default' => '',
			'section' => 'Colors',
			'help'    => $role['desc'],
		];
	}

	return $defs;
}

/** Read a setting through its definition, so a missing value always falls back sanely. */
function sp_store_setting_value( string $key ) {
	$defs = sp_store_setting_defs();
	if ( ! isset( $defs[ $key ] ) ) return '';
	return sp_store_get_setting( $key, (string) $defs[ $key ]['default'] );
}

/** Sanitise one posted value according to its declared type. */
function sp_store_sanitize_setting( string $key, $value ): string {
	$defs = sp_store_setting_defs();
	if ( ! isset( $defs[ $key ] ) ) return '';
	$def = $defs[ $key ];

	switch ( $def['type'] ) {
		case 'choice':
			// An unknown choice falls back to the default rather than being stored — a
			// tampered or stale value must never end up deciding how the shop renders.
			$value = sanitize_text_field( (string) $value );
			return isset( $def['choices'][ $value ] ) ? $value : (string) $def['default'];

		case 'toggle':
			return (string) $value === '1' ? '1' : '';

		case 'color':
			// Empty is meaningful — it means "leave this colour alone", which is how a
			// client clears an override and goes back to the site's own palette.
			$value = trim( (string) $value );
			if ( $value === '' ) return '';
			return function_exists( 'site_pulse_sanitize_hex' ) ? site_pulse_sanitize_hex( $value ) : '';

		case 'text':
		default:
			return sanitize_text_field( (string) $value );
	}
}


/*--------------------------------------------------------------
# Endpoints
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_store_settings', 'sp_store_ajax_settings' );
function sp_store_ajax_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	$fields = [];
	foreach ( sp_store_setting_defs() as $key => $def ) {
		$field = [
			'key'     => $key,
			'label'   => $def['label'],
			'type'    => $def['type'],
			'help'    => $def['help'] ?? '',
			'choices' => $def['choices'] ?? null,
			'section' => $def['section'] ?? '',
			'value'   => sp_store_setting_value( $key ),
		];

		// A colour carries BOTH its saved override and the colour the storefront is
		// currently using, so the picker can open on the real colour and Reset has
		// something to go back to.
		if ( $def['type'] === 'color' ) {
			$field['inherited'] = sp_store_resolve_var( '--' . substr( $key, 6 ) );
			$field['effective'] = $field['value'] !== '' ? $field['value'] : $field['inherited'];
		}

		$fields[] = $field;
	}

	wp_send_json_success( [
		'fields' => $fields,
		// Shown as a status line: a store with no key cannot take money, and that is
		// worth saying on the screen rather than leaving to be discovered at checkout.
		'stripe_ready'   => function_exists( 'sp_store_provider_ready' ) && sp_store_provider_ready(),
		'stripe_mode'    => function_exists( 'sp_store_stripe_mode' ) ? sp_store_stripe_mode() : '',
		'webhook_url'    => function_exists( 'sp_store_webhook_url' ) ? sp_store_webhook_url() : '',
		'provider_label' => function_exists( 'sp_store_provider_def' ) ? ( sp_store_provider_def()['label'] ?? '' ) : '',
		// An impossible combination (provider-calculated tax on a processor that
		// cannot calculate it) is shown HERE, not discovered on a receipt.
		'warning'        => function_exists( 'sp_store_provider_warning' ) ? sp_store_provider_warning() : '',
	] );
}

add_action( 'wp_ajax_site_pulse_store_save_settings', 'sp_store_ajax_save_settings' );
function sp_store_ajax_save_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	$posted = (array) ( $_POST['settings'] ?? [] );
	$saved  = [];

	// Iterate the DEFINITIONS, not the POST: an unknown key can then never be written,
	// and a toggle the browser omitted when off is correctly stored as off rather than
	// silently keeping its old value.
	foreach ( sp_store_setting_defs() as $key => $def ) {
		$value = sp_store_sanitize_setting( $key, wp_unslash( $posted[ $key ] ?? '' ) );
		sp_store_set_setting( $key, $value );
		$saved[ $key ] = $value;
	}

	if ( function_exists( 'site_pulse_log' ) ) {
		site_pulse_log( 'store_settings_saved', 'Updated store settings.', $saved );
	}

	wp_send_json_success( [ 'message' => 'Store settings saved.', 'settings' => $saved ] );
}


/*--------------------------------------------------------------
# Shipping by Distance
--------------------------------------------------------------*/

/** The distance settings for the editor, in dollars rather than cents. */
add_action( 'wp_ajax_site_pulse_store_distance', 'sp_store_ajax_distance' );
function sp_store_ajax_distance(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	$cfg = sp_store_distance_config();
	wp_send_json_success( [
		'on'       => sp_store_distance_on(),
		'origin'   => $cfg['origin'],
		'label'    => sp_store_get_setting( 'distance_label', '' ),
		'handling' => number_format( $cfg['handling'] / 100, 2, '.', '' ),
		'bands'    => array_map( static fn( $b ) => [
			'from' => $b['from'] + 0, 'to' => $b['to'] + 0, 'rate' => rtrim( rtrim( number_format( $b['rate'] / 100, 4, '.', '' ), '0' ), '.' ),
			'fee'  => $b['fee'] ? number_format( $b['fee'] / 100, 2, '.', '' ) : '',
		], $cfg['bands'] ),
		'has_key'  => function_exists( 'site_pulse_mileage_google_key' ) && site_pulse_mileage_google_key() !== '',
	] );
}

add_action( 'wp_ajax_site_pulse_store_save_distance', 'sp_store_ajax_save_distance' );
function sp_store_ajax_save_distance(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	$bands = [];
	foreach ( (array) json_decode( wp_unslash( (string) ( $_POST['bands'] ?? '[]' ) ), true ) as $b ) {
		$from = (float) preg_replace( '/[^0-9.]/', '', (string) ( $b['from'] ?? '' ) );
		$to   = (float) preg_replace( '/[^0-9.]/', '', (string) ( $b['to'] ?? '' ) );
		// Typed as dollars per mile (0.29); stored as cents per mile (29), fractions kept.
		$rate = (float) preg_replace( '/[^0-9.]/', '', (string) ( $b['rate'] ?? '' ) ) * 100;
		$fee  = sp_store_parse_money( (string) ( $b['fee'] ?? '' ) );
		if ( $to <= 0 && $rate <= 0 && $fee <= 0 ) continue;
		if ( $to < $from ) wp_send_json_error( [ 'message' => sprintf( 'A band from %s miles ends before it starts.', $from ) ] );
		$bands[] = [ 'from' => $from, 'to' => $to, 'rate' => round( $rate, 4 ), 'fee' => $fee ];
	}

	sp_store_set_setting( 'distance_origin', sanitize_text_field( wp_unslash( (string) ( $_POST['origin'] ?? '' ) ) ) );
	sp_store_set_setting( 'distance_label', sanitize_text_field( wp_unslash( (string) ( $_POST['label'] ?? '' ) ) ) );
	sp_store_set_setting( 'distance_handling_cents', (string) sp_store_parse_money( wp_unslash( (string) ( $_POST['handling'] ?? '' ) ) ) );
	sp_store_set_setting( 'distance_bands', wp_json_encode( $bands ) );

	wp_send_json_success( [ 'message' => 'Distance shipping saved.' ] );
}

/**
 * Price a test address with the SAVED settings — the way to check the setup against
 * a real past order before a customer ever sees a figure.
 */
add_action( 'wp_ajax_site_pulse_store_distance_test', 'sp_store_ajax_distance_test' );
function sp_store_ajax_distance_test(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	$a = sp_store_clean_address( wp_unslash( (array) ( $_POST['address'] ?? [] ) ) )['address'];
	if ( ! sp_store_address_complete( $a ) ) wp_send_json_error( [ 'message' => 'Enter a street, city, state and 5-digit ZIP.' ] );
	if ( sp_store_distance_config()['origin'] === '' ) wp_send_json_error( [ 'message' => 'Save a ship-from address first.' ] );

	$miles = sp_store_distance_miles( $a );
	if ( $miles === null ) {
		$why = function_exists( 'site_pulse_mileage_api_error' ) ? site_pulse_mileage_api_error() : '';
		wp_send_json_error( [ 'message' => 'Google could not find a driving route to that address.' . ( $why !== '' ? ' (' . $why . ')' : '' ) ] );
	}
	$cents = sp_store_distance_price( $miles );
	wp_send_json_success( [
		'miles' => number_format( $miles, 1 ),
		'price' => $cents === null ? '' : html_entity_decode( sp_store_money( $cents ), ENT_QUOTES, 'UTF-8' ),
		'note'  => $cents === null ? 'That is further than your last band, so checkout would not be able to offer shipping.' : '',
	] );
}


/*--------------------------------------------------------------
# Shipping Rates
--------------------------------------------------------------*/

/**
 * Every rate, for the editor.
 *
 * Cents are converted to dollars on the way OUT and back to cents on the way IN,
 * so nobody has to type 1295 to mean $12.95. Storage stays integer cents.
 */
add_action( 'wp_ajax_site_pulse_store_shipping_rates', 'sp_store_ajax_shipping_rates' );
function sp_store_ajax_shipping_rates(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT * FROM " . sp_store_table( 'shipping_rates' ) . " ORDER BY display_order ASC, id ASC",
		ARRAY_A
	) ?: [];

	$rates = [];
	foreach ( $rows as $row ) {
		$rates[] = [
			'id'           => (int) $row['id'],
			'name'         => (string) $row['name'],
			'method'       => (string) $row['method'],
			'amount'       => sp_store_cents_to_input( (int) $row['amount_cents'] ),
			'per_item'     => sp_store_cents_to_input( (int) $row['per_item_cents'] ),
			'min_subtotal' => sp_store_cents_to_input( (int) $row['min_subtotal_cents'] ),
			'max_subtotal' => sp_store_cents_to_input( (int) $row['max_subtotal_cents'] ),
			'min_weight'   => (int) $row['min_weight_grams'],
			'max_weight'   => (int) $row['max_weight_grams'],
			'estimate'     => (string) $row['delivery_estimate'],
			'active'       => $row['status'] === 'active' ? 1 : 0,
		];
	}

	wp_send_json_success( [ 'rates' => $rates, 'distance_on' => sp_store_distance_on() ] );
}

/** Cents to a plain dollar string for a text box. 0 shows as empty, not "0.00". */
function sp_store_cents_to_input( int $cents ): string {
	return $cents > 0 ? number_format( $cents / 100, 2, '.', '' ) : '';
}

/** A typed dollar amount back to cents, tolerating "$12.95", "12.95" and "1,295.00". */
function sp_store_input_to_cents( $value ): int {
	$clean = preg_replace( '/[^0-9.\-]/', '', (string) $value );
	if ( $clean === '' || $clean === '-' ) return 0;
	return max( 0, (int) round( ( (float) $clean ) * 100 ) );
}

/**
 * Save the whole set.
 *
 * The posted list is the WHOLE truth: rows not in it are deleted. That is what
 * makes removing a rate work with no separate delete call, and it is safe here
 * because the editor always posts every row it is showing.
 */
add_action( 'wp_ajax_site_pulse_store_save_shipping_rates', 'sp_store_ajax_save_shipping_rates' );
function sp_store_ajax_save_shipping_rates(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	global $wpdb;
	$table  = sp_store_table( 'shipping_rates' );
	$posted = json_decode( wp_unslash( (string) ( $_POST['rates'] ?? '[]' ) ), true );
	if ( ! is_array( $posted ) ) $posted = [];

	$now  = current_time( 'mysql', true );
	$kept = [];
	$order = 0;

	foreach ( $posted as $rate ) {
		$name = sanitize_text_field( (string) ( $rate['name'] ?? '' ) );
		// An unnamed rate is a blank row somebody added and did not fill in, not a
		// rate called "". Skipping it silently is kinder than an error.
		if ( $name === '' ) continue;

		$method = in_array( ( $rate['method'] ?? 'flat' ), [ 'flat', 'free' ], true ) ? $rate['method'] : 'flat';

		$data = [
			'name'               => $name,
			'method'             => $method,
			'amount_cents'       => $method === 'free' ? 0 : sp_store_input_to_cents( $rate['amount'] ?? 0 ),
			'per_item_cents'     => $method === 'free' ? 0 : sp_store_input_to_cents( $rate['per_item'] ?? 0 ),
			'min_subtotal_cents' => sp_store_input_to_cents( $rate['min_subtotal'] ?? 0 ),
			'max_subtotal_cents' => sp_store_input_to_cents( $rate['max_subtotal'] ?? 0 ),
			'min_weight_grams'   => max( 0, (int) ( $rate['min_weight'] ?? 0 ) ),
			'max_weight_grams'   => max( 0, (int) ( $rate['max_weight'] ?? 0 ) ),
			'delivery_estimate'  => sanitize_text_field( (string) ( $rate['estimate'] ?? '' ) ),
			'display_order'      => $order++,
			'status'             => empty( $rate['active'] ) ? 'inactive' : 'active',
			'updated_at'         => $now,
		];

		$id = (int) ( $rate['id'] ?? 0 );
		if ( $id > 0 ) {
			$wpdb->update( $table, $data, [ 'id' => $id ] );
			$kept[] = $id;
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$kept[] = (int) $wpdb->insert_id;
		}
	}

	// Anything no longer in the list is gone. Deleting LAST means a failed save
	// cannot wipe the rates before the replacements are safely in.
	if ( $kept ) {
		$in = implode( ',', array_map( 'intval', $kept ) );
		$wpdb->query( "DELETE FROM $table WHERE id NOT IN ($in)" );
	} else {
		$wpdb->query( "DELETE FROM $table" );
	}

	if ( function_exists( 'site_pulse_log' ) ) {
		site_pulse_log( 'store_shipping_saved', 'Updated store shipping rates.', [ 'count' => count( $kept ) ] );
	}

	wp_send_json_success( [ 'message' => 'Shipping rates saved.', 'count' => count( $kept ) ] );
}
