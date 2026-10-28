<?php
/* Battle Plan Web Design — Site Pulse: Store Catalog

/*--------------------------------------------------------------
The CATALOG half of the store module: the product post type, the variant data
API that every other half is built on, and the admin screens for both.

The split this file implements is the one in includes-site-pulse-store.php:

  CONTENT  is a WordPress post (SITE_PULSE_STORE_CPT). Title, description,
           gallery, categories, permalink, SEO. WordPress is genuinely good at
           all of that and the framework already has tooling for every bit of
           it, so none of it is rebuilt here.

  COMMERCE is the variants table. Price, SKU, stock, weight. None of it is
           postmeta, because selling the last one of something is a concurrency
           problem and postmeta cannot express it safely.

DELIBERATELY NO ACF. Product content fields could be an ACF group, and most of
the framework would do it that way. Not here: the numbers on this screen decide
what a customer is charged, and the whole point of this module is that the
money path has no plugin in it. Plain metaboxes and $_POST.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Post Type & Taxonomy
# Product Options
# Variant API
# Inventory
# Admin — Variant Editor
# Admin — Columns
# SEO
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

/** Is the store module on for this install? One place, so every gate reads the same. */
function sp_store_active(): bool {
	return function_exists( 'site_pulse_module_on' ) && site_pulse_module_on( 'store' );
}


/*--------------------------------------------------------------
# Post Type & Taxonomy
--------------------------------------------------------------*/

/**
 * The storefront's URL root. Filterable because a client may already rank for
 * /store/ or /merch/ and changing the address of a shop that ranks is a real
 * cost. Products live at /{slug}/{product}, the archive at /{slug}/.
 *
 * NB: a site still running WooCommerce owns /shop/ — do not turn both on.
 */
function sp_store_base_slug(): string {
	$slug = sanitize_title( (string) apply_filters( 'sp_store_base_slug', 'shop' ) );
	return $slug !== '' ? $slug : 'shop';
}

add_action( 'init', 'sp_store_register_cpt', 5 );
function sp_store_register_cpt(): void {
	if ( ! sp_store_active() ) return;

	$slug = sp_store_base_slug();

	register_post_type( SITE_PULSE_STORE_CPT, [
		'label'               => __( 'Products', 'battleplan' ),
		'labels'              => [
			'name'          => _x( 'Products', 'Post Type General Name', 'battleplan' ),
			'singular_name' => _x( 'Product', 'Post Type Singular Name', 'battleplan' ),
			'menu_name'     => __( 'Products', 'battleplan' ),
			'add_new'       => __( 'Add Product', 'battleplan' ),
			'add_new_item'  => __( 'Add New Product', 'battleplan' ),
			'edit_item'     => __( 'Edit Product', 'battleplan' ),
			'search_items'  => __( 'Search Products', 'battleplan' ),
			'not_found'     => __( 'No products yet', 'battleplan' ),
		],
		'public'              => true,
		'publicly_queryable'  => true,
		// Products SHOULD turn up in site search — this is the one content type a
		// visitor is most likely to be hunting for by name.
		'exclude_from_search' => false,
		'show_in_nav_menus'   => true,
		'show_in_rest'        => true,
		'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'revisions' ],
		'hierarchical'        => false,
		'menu_position'       => 21,
		'menu_icon'           => 'dashicons-cart',
		'has_archive'         => $slug,
		'rewrite'             => [ 'slug' => $slug, 'with_front' => false ],
		'capability_type'     => 'page',
	] );

	register_taxonomy( 'bp_product_cat', [ SITE_PULSE_STORE_CPT ], [
		'labels'            => [
			'name'          => _x( 'Product Categories', 'Taxonomy General Name', 'battleplan' ),
			'singular_name' => _x( 'Product Category', 'Taxonomy Singular Name', 'battleplan' ),
			'menu_name'     => __( 'Categories', 'battleplan' ),
		],
		'public'            => true,
		'hierarchical'      => true,
		'show_admin_column' => false,   // the admin-columns engine draws this instead
		'show_in_rest'      => true,
		'rewrite'           => [ 'slug' => $slug . '-category', 'with_front' => false ],
	] );

	// Rewrite rules are expensive to flush and must not be flushed on every request.
	// Keying the flag on the SLUG means turning the module on flushes once, and changing
	// the slug filter later flushes exactly once more — with no manual step and no
	// "re-save your permalinks" instruction in a handover document.
	if ( get_option( 'sp_store_rewrite_slug' ) !== $slug ) {
		flush_rewrite_rules( false );
		update_option( 'sp_store_rewrite_slug', $slug );
	}
}


/*--------------------------------------------------------------
# Product Options
--------------------------------------------------------------*/

/**
 * A product's options — up to three, each a name plus the choices it offers:
 *   [ [ 'name' => 'Size', 'values' => ['Small','Medium','Large'] ], ... ]
 *
 * Names and choices describe the PRODUCT, so they are post meta. The chosen value
 * identifies a VARIANT, so it is a column on the variant row. Getting that backwards
 * is how you end up querying serialized postmeta to answer "is the large one in
 * stock" — the question the storefront asks on every page view.
 *
 * Reads the pre-2026-09-25 format too (a flat list of names, with no declared
 * choices), recovering the choices from the variants that exist so an older product
 * does not lose its option list the first time it is opened.
 */
function sp_store_options( int $product_id ): array {
	$raw = get_post_meta( $product_id, '_sp_store_options', true );
	if ( ! is_array( $raw ) ) return [];

	$out    = [];
	$legacy = false;

	foreach ( $raw as $entry ) {
		if ( is_string( $entry ) ) {
			$name = trim( $entry );
			if ( $name === '' ) continue;
			$out[]  = [ 'name' => $name, 'values' => [] ];
			$legacy = true;
		} elseif ( is_array( $entry ) ) {
			$name = trim( (string) ( $entry['name'] ?? '' ) );
			if ( $name === '' ) continue;
			$values = [];
			foreach ( (array) ( $entry['values'] ?? [] ) as $value ) {
				$value = trim( (string) $value );
				if ( $value !== '' && ! in_array( $value, $values, true ) ) $values[] = $value;
			}
			$out[] = [ 'name' => $name, 'values' => $values ];
		}
		if ( count( $out ) === 3 ) break;
	}

	if ( $legacy ) {
		foreach ( sp_store_get_variants( $product_id ) as $variant ) {
			foreach ( $out as $index => $option ) {
				if ( $option['values'] ) continue;
				$value = trim( (string) ( $variant[ 'option' . ( $index + 1 ) ] ?? '' ) );
				if ( $value !== '' && ! in_array( $value, $out[ $index ]['values'], true ) ) {
					$out[ $index ]['values'][] = $value;
				}
			}
		}
	}

	return $out;
}

/** Just the option names, in order — what the storefront's pickers are labelled with. */
function sp_store_option_names( int $product_id ): array {
	return array_column( sp_store_options( $product_id ), 'name' );
}

/** Store the option structure. An empty list clears it. */
function sp_store_set_options( int $product_id, array $options ): void {
	$clean = [];
	foreach ( $options as $option ) {
		$name = trim( wp_strip_all_tags( (string) ( $option['name'] ?? '' ) ) );
		if ( $name === '' ) continue;

		$values = [];
		foreach ( (array) ( $option['values'] ?? [] ) as $value ) {
			$value = trim( wp_strip_all_tags( (string) $value ) );
			if ( $value === '' ) continue;
			$value = mb_substr( $value, 0, 100 );
			if ( ! in_array( $value, $values, true ) ) $values[] = $value;
		}

		$clean[] = [ 'name' => mb_substr( $name, 0, 60 ), 'values' => $values ];
		if ( count( $clean ) === 3 ) break;
	}

	if ( $clean ) update_post_meta( $product_id, '_sp_store_options', $clean );
	else          delete_post_meta( $product_id, '_sp_store_options' );
}

/**
 * "Small, Medium , Large" -> ['Small','Medium','Large']. Commas, trimmed, deduped —
 * IGNORING CASE, because the variants table's UNIQUE index does: "M" and "m" kept as
 * two choices would generate two variants the database then refuses to store.
 */
function sp_store_split_values( string $input ): array {
	$out  = [];
	$seen = [];
	foreach ( explode( ',', $input ) as $value ) {
		$value = trim( sanitize_text_field( $value ) );
		$fold  = mb_strtolower( $value );
		if ( $value === '' || isset( $seen[ $fold ] ) ) continue;
		$seen[ $fold ] = true;
		$out[] = $value;
	}
	return $out;
}


/*--------------------------------------------------------------
# Variant API
--------------------------------------------------------------*/

/**
 * Every variant row for a product, ordered as the client arranged them.
 *
 * Results are memoised per request: a storefront grid asks for the price range of
 * thirty products and a single page asks for the same product's variants three or
 * four times over, and neither should mean repeat queries.
 */
function sp_store_get_variants( int $product_id, bool $active_only = false ): array {
	$cache = &sp_store_variant_cache();

	if ( ! isset( $cache[ $product_id ] ) ) {
		global $wpdb;
		$cache[ $product_id ] = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . sp_store_table( 'variants' ) . "
			 WHERE product_id = %d ORDER BY position ASC, id ASC",
			$product_id
		), ARRAY_A ) ?: [];
	}

	if ( ! $active_only ) return $cache[ $product_id ];

	return array_values( array_filter(
		$cache[ $product_id ],
		static fn( $v ) => ( $v['status'] ?? '' ) === 'active'
	) );
}

/**
 * The per-request variant cache, returned BY REFERENCE so both the getter and the
 * flusher act on the same array. A static inside the getter alone could never be
 * invalidated, which matters on the save request: the editor writes variants and
 * then asks for them again, and a stale empty read there would make
 * sp_store_ensure_default_variant() add a second, unwanted default row.
 */
function &sp_store_variant_cache(): array {
	static $cache = [];
	return $cache;
}

/** Drop the memoised variants for a product, or for everything. Called after every write. */
function sp_store_flush_variant_cache( int $product_id = 0 ): void {
	$cache = &sp_store_variant_cache();
	if ( $product_id ) unset( $cache[ $product_id ] );
	else               $cache = [];
}

/** One variant row, or null. */
function sp_store_get_variant( int $variant_id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'variants' ) . " WHERE id = %d",
		$variant_id
	), ARRAY_A );
	return $row ?: null;
}

/**
 * A variant's human label — "Large / Black", or its own title, or "Default".
 * This is what the storefront, the cart line, and the packing slip all show.
 */
function sp_store_variant_label( array $variant ): string {
	$parts = [];
	foreach ( [ 'option1', 'option2', 'option3' ] as $key ) {
		$val = trim( (string) ( $variant[ $key ] ?? '' ) );
		if ( $val !== '' ) $parts[] = $val;
	}
	if ( $parts ) return implode( ' / ', $parts );

	$title = trim( (string) ( $variant['title'] ?? '' ) );
	return $title !== '' ? $title : 'Default';
}

/**
 * WHAT THE CUSTOMER ACTUALLY PAYS.
 *
 * `price_cents` is the REGULAR price and never moves. `sale_price_cents` is the
 * discount, set only while something is on sale and cleared to end it — so putting
 * an item on sale is one field, and taking it off is deleting that field. (The older
 * "compare at" arrangement made you move the regular price aside and retype it later,
 * which is two edits in each direction and one to forget.)
 *
 * A sale price of 0, or one that is not actually lower, is IGNORED rather than
 * honoured: a typo must never quietly raise a price, and an old sale left at the same
 * value as the regular price should simply read as "not on sale".
 *
 * Every place that needs a real price goes through here — cart, schema, storefront,
 * admin list. Nothing reads price_cents directly and reapplies the rule itself.
 */
function sp_store_variant_price( array $variant ): int {
	$regular = (int) ( $variant['price_cents'] ?? 0 );
	$sale    = (int) ( $variant['sale_price_cents'] ?? 0 );
	return ( $sale > 0 && $sale < $regular ) ? $sale : $regular;
}

/** Is this variant actually discounted right now? */
function sp_store_variant_on_sale( array $variant ): bool {
	$sale = (int) ( $variant['sale_price_cents'] ?? 0 );
	return $sale > 0 && $sale < (int) ( $variant['price_cents'] ?? 0 );
}

/**
 * The price span across a product's active variants, as
 * [ 'min' => cents, 'max' => cents, 'currency' => 'USD', 'count' => n ].
 * A product with no variants returns zeroes and count 0, which is the storefront's
 * cue to say nothing rather than to say "$0.00".
 */
/** The shop archive, falling back to the home page if the CPT has no archive. */
function sp_store_shop_url(): string {
	return get_post_type_archive_link( SITE_PULSE_STORE_CPT ) ?: home_url( '/' );
}

function sp_store_price_range( int $product_id ): array {
	$variants = sp_store_get_variants( $product_id, true );
	if ( ! $variants ) {
		return [ 'min' => 0, 'max' => 0, 'currency' => sp_store_currency(), 'count' => 0 ];
	}

	// TWO ranges, not one. `min`/`max` are what a customer actually pays;
	// `regular_min`/`regular_max` are the undiscounted prices. Collapsing a sale into a
	// single effective range turns "$20, on sale for $15" into "$15.00 – $20.00", which
	// reads as a product with two prices rather than as a discount.
	$effective = array_map( 'sp_store_variant_price', $variants );
	$regular   = array_map( static fn( $v ) => (int) $v['price_cents'], $variants );

	$on_sale = false;
	foreach ( $variants as $variant ) {
		if ( sp_store_variant_on_sale( $variant ) ) { $on_sale = true; break; }
	}

	return [
		'min'         => min( $effective ),
		'max'         => max( $effective ),
		'regular_min' => min( $regular ),
		'regular_max' => max( $regular ),
		'on_sale'     => $on_sale,
		'currency'    => (string) ( $variants[0]['currency'] ?: sp_store_currency() ),
		'count'       => count( $variants ),
	];
}

/** The price range as a display string: "$24.00" or "$24.00 – $32.00". */
/**
 * The price as shown on a card or a list row.
 *
 * Not on sale:  "$24.00"            or "$24.00 – $32.00" across variants
 * On sale:      "<del>$20.00</del> <span>$15.00</span>"
 *
 * <del> rather than a styled span, because it is the element HTML actually has for
 * this and browsers strike it through unaided — so the strikethrough survives in the
 * WordPress product list and anywhere else this is rendered without the storefront CSS.
 */
function sp_store_price_range_label( int $product_id ): string {
	$range = sp_store_price_range( $product_id );
	if ( ! $range['count'] ) return '';

	$currency = $range['currency'];
	$span = static function ( int $low, int $high ) use ( $currency ): string {
		return $low === $high
			? sp_store_money( $low, $currency )
			: sp_store_money( $low, $currency ) . ' &ndash; ' . sp_store_money( $high, $currency );
	};

	$now = $span( $range['min'], $range['max'] );

	// A product that renews says how often, everywhere its price is shown.
	$interval = function_exists( 'sp_member_product_interval' ) ? sp_member_product_interval( $product_id ) : '';
	if ( $interval !== '' ) $now .= '<span class="store-price-per"> / ' . ( $interval === 'year' ? 'year' : 'month' ) . '</span>';

	if ( empty( $range['on_sale'] ) ) return $now;

	return '<del class="store-price-was">' . $span( $range['regular_min'], $range['regular_max'] ) . '</del>'
		. ' <span class="store-price-now">' . $now . '</span>';
}


/*--------------------------------------------------------------
# Sort Keys
--------------------------------------------------------------*/

/**
 * Prices live in the variants table, which WP_Query has no way to sort on. So the
 * numbers a shopper sorts by are mirrored onto the product as ordinary post meta and
 * refreshed whenever the data behind them changes.
 *
 * Every product carries every key, even as 0. `orderby => meta_value_num` is an INNER
 * JOIN: a product missing the key would vanish from a sorted page altogether rather
 * than merely sort oddly, and a product nobody can find is worse than one in the
 * wrong place.
 */
function sp_store_refresh_sort_meta( int $product_id ): void {
	if ( get_post_type( $product_id ) !== SITE_PULSE_STORE_CPT ) return;

	$price   = null;
	$savings = 0;

	foreach ( sp_store_get_variants( $product_id, true ) as $variant ) {
		$effective = sp_store_variant_price( $variant );
		$price     = $price === null ? $effective : min( $price, $effective );
		$savings   = max( $savings, (int) $variant['price_cents'] - $effective );
	}

	update_post_meta( $product_id, '_sp_store_sort_price',   (int) $price );
	update_post_meta( $product_id, '_sp_store_sort_savings', (int) $savings );

	// Brand is typed by hand and may never have been saved; sorting by it is the same
	// INNER JOIN, so an absent brand has to exist as an empty string.
	if ( get_post_meta( $product_id, '_sp_store_brand', true ) === '' ) {
		update_post_meta( $product_id, '_sp_store_brand', '' );
	}

	delete_transient( 'sp_store_sort_flags' );
}

/** Units sold and kept: refunds subtracted, unpaid orders never counted at all. */
function sp_store_refresh_sold_count( int $product_id ): void {
	global $wpdb;

	$sold = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE( SUM( i.qty - i.qty_refunded ), 0 )
		   FROM " . sp_store_table( 'order_items' ) . " i
		   JOIN " . sp_store_table( 'orders' ) . " o ON o.id = i.order_id
		  WHERE i.product_id = %d AND o.payment_status = 'paid'",
		$product_id
	) );

	update_post_meta( $product_id, '_sp_store_sold', $sold );
	delete_transient( 'sp_store_sort_flags' );
}

// A sale moves every product it touched up the Top Sellers order.
add_action( 'sp_store_order_paid', 'sp_store_refresh_sold_after_order' );
function sp_store_refresh_sold_after_order( int $order_id ): void {
	foreach ( sp_store_order_items( $order_id ) as $item ) {
		if ( (int) $item['product_id'] ) sp_store_refresh_sold_count( (int) $item['product_id'] );
	}
}

// Priority 20: after sp_store_save_product has written the variants this reads.
add_action( 'save_post_' . SITE_PULSE_STORE_CPT, 'sp_store_refresh_sort_meta_on_save', 20 );
function sp_store_refresh_sort_meta_on_save( $post_id ): void {
	$post_id = (int) $post_id;
	sp_store_refresh_sort_meta( $post_id );
	if ( get_post_meta( $post_id, '_sp_store_sold', true ) === '' ) {
		update_post_meta( $post_id, '_sp_store_sold', 0 );
	}
}

/** Build every product's sort keys from scratch. Runs on install and on DB upgrade. */
function sp_store_backfill_sort_meta(): void {
	$ids = get_posts( [
		'post_type'        => SITE_PULSE_STORE_CPT,
		'post_status'      => 'any',
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	] );

	foreach ( $ids as $id ) {
		sp_store_refresh_sort_meta( (int) $id );
		sp_store_refresh_sold_count( (int) $id );
	}
}

/**
 * Which sorts this shop can honestly offer.
 *
 * A shop with no sale on and nothing sold yet should not show "Savings" and "Top
 * Sellers" options that every product ties at zero on — that looks broken in a way
 * an empty result never does.
 */
function sp_store_sort_flags(): array {
	$flags = get_transient( 'sp_store_sort_flags' );
	if ( is_array( $flags ) ) return $flags;

	global $wpdb;
	$flags = [
		'savings' => (bool) $wpdb->get_var( "SELECT 1 FROM $wpdb->postmeta WHERE meta_key = '_sp_store_sort_savings' AND meta_value > 0 LIMIT 1" ),
		'sold'    => (bool) $wpdb->get_var( "SELECT 1 FROM $wpdb->postmeta WHERE meta_key = '_sp_store_sold' AND meta_value > 0 LIMIT 1" ),
		'brand'   => (bool) $wpdb->get_var( "SELECT 1 FROM $wpdb->postmeta WHERE meta_key = '_sp_store_brand' AND meta_value <> '' LIMIT 1" ),
	];

	set_transient( 'sp_store_sort_flags', $flags, HOUR_IN_SECONDS );
	return $flags;
}

/**
 * Create or update one variant. Returns the id, or a WP_Error.
 *
 * The UNIQUE (product_id, option1, option2, option3) index means two variants
 * cannot share an option combination — so this reports that as a real error
 * rather than letting the insert fail somewhere the caller isn't looking.
 */
function sp_store_save_variant( array $data ) {
	global $wpdb;

	$id         = (int) ( $data['id'] ?? 0 );
	$product_id = (int) ( $data['product_id'] ?? 0 );
	if ( ! $product_id ) return new WP_Error( 'sp_store_no_product', 'A variant needs a product.' );

	$now = current_time( 'mysql', true );

	$fields = [
		'product_id'        => $product_id,
		'sku'               => mb_substr( trim( (string) ( $data['sku'] ?? '' ) ), 0, 100 ),
		'title'             => mb_substr( trim( (string) ( $data['title'] ?? '' ) ), 0, 255 ),
		'option1'           => mb_substr( trim( (string) ( $data['option1'] ?? '' ) ), 0, 100 ),
		'option2'           => mb_substr( trim( (string) ( $data['option2'] ?? '' ) ), 0, 100 ),
		'option3'           => mb_substr( trim( (string) ( $data['option3'] ?? '' ) ), 0, 100 ),
		'position'          => (int) ( $data['position'] ?? 0 ),
		'price_cents'       => max( 0, (int) ( $data['price_cents'] ?? 0 ) ),
		'sale_price_cents'  => max( 0, (int) ( $data['sale_price_cents'] ?? 0 ) ),
		'cost_cents'        => max( 0, (int) ( $data['cost_cents'] ?? 0 ) ),
		'currency'          => (string) ( $data['currency'] ?? sp_store_currency() ),
		'track_stock'       => ! empty( $data['track_stock'] ) ? 1 : 0,
		'low_stock_at'      => max( 0, (int) ( $data['low_stock_at'] ?? 0 ) ),
		'allow_backorder'   => ! empty( $data['allow_backorder'] ) ? 1 : 0,
		'requires_shipping' => ! empty( $data['requires_shipping'] ) ? 1 : 0,
		'weight_grams'      => max( 0, (int) ( $data['weight_grams'] ?? 0 ) ),
		'taxable'           => ! empty( $data['taxable'] ) ? 1 : 0,
		'image_id'          => max( 0, (int) ( $data['image_id'] ?? 0 ) ),
		'barcode'           => mb_substr( trim( (string) ( $data['barcode'] ?? '' ) ), 0, 100 ),
		'status'            => ( ( $data['status'] ?? 'active' ) === 'archived' ) ? 'archived' : 'active',
		'updated_at'        => $now,
	];

	// STOCK IS NOT SET HERE. It moves only through sp_store_adjust_stock() /
	// sp_store_set_stock(), so that every change writes a ledger row. A stock column
	// that anything may write is a stock column nobody can explain.
	if ( $id ) {
		$existing = sp_store_get_variant( $id );
		if ( ! $existing ) return new WP_Error( 'sp_store_no_variant', 'That variant no longer exists.' );

		$clash = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . sp_store_table( 'variants' ) . "
			 WHERE product_id = %d AND option1 = %s AND option2 = %s AND option3 = %s AND id != %d",
			$product_id, $fields['option1'], $fields['option2'], $fields['option3'], $id
		) );
		if ( $clash ) return new WP_Error( 'sp_store_duplicate', 'Another variant already uses that option combination.' );

		$wpdb->update( sp_store_table( 'variants' ), $fields, [ 'id' => $id ] );
	} else {
		$clash = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . sp_store_table( 'variants' ) . "
			 WHERE product_id = %d AND option1 = %s AND option2 = %s AND option3 = %s",
			$product_id, $fields['option1'], $fields['option2'], $fields['option3']
		) );
		if ( $clash ) return new WP_Error( 'sp_store_duplicate', 'A variant already uses that option combination.' );

		$fields['stock']           = 0;
		$fields['stock_committed'] = 0;
		$fields['created_at']      = $now;
		$wpdb->insert( sp_store_table( 'variants' ), $fields );
		$id = (int) $wpdb->insert_id;
	}

	sp_store_flush_variant_cache( $product_id );
	sp_store_refresh_sort_meta( $product_id );

	return $id ?: new WP_Error( 'sp_store_save_failed', 'Could not save that variant.' );
}

/**
 * Delete a variant — but never one that an order line still points at, because
 * an order must stay explainable forever. Those are archived instead, which
 * hides them from the storefront and leaves the history intact.
 */
function sp_store_delete_variant( int $variant_id ): bool {
	global $wpdb;

	$variant = sp_store_get_variant( $variant_id );
	if ( ! $variant ) return false;

	$in_use = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . sp_store_table( 'order_items' ) . " WHERE variant_id = %d",
		$variant_id
	) );

	if ( $in_use ) {
		$wpdb->update(
			sp_store_table( 'variants' ),
			[ 'status' => 'archived', 'updated_at' => current_time( 'mysql', true ) ],
			[ 'id' => $variant_id ]
		);
		$done = true;
	} else {
		$done = (bool) $wpdb->delete( sp_store_table( 'variants' ), [ 'id' => $variant_id ] );
	}

	sp_store_flush_variant_cache( (int) $variant['product_id'] );
	sp_store_refresh_sort_meta( (int) $variant['product_id'] );
	return $done;
}

/**
 * Guarantee a product has at least one variant. A product with none is
 * unbuyable and, worse, looks fine in the editor — so the save handler calls
 * this and a product with no options quietly gets its single default row.
 */
function sp_store_ensure_default_variant( int $product_id ): int {
	$existing = sp_store_get_variants( $product_id );
	if ( $existing ) return (int) $existing[0]['id'];

	// Deliberately NOT tracked, unlike a row added in the editor. This is the safety net
	// for a product saved with no variant rows at all, and its job is to leave the
	// product buyable. Tracked with a stock of zero would quietly make it sold out —
	// turning "you forgot a variant" into "your product vanished from the shop".
	$id = sp_store_save_variant( [
		'product_id'        => $product_id,
		'title'             => 'Default',
		'track_stock'       => 0,
		'requires_shipping' => 1,
		'taxable'           => 1,
	] );
	return is_wp_error( $id ) ? 0 : (int) $id;
}


/*--------------------------------------------------------------
# Inventory
--------------------------------------------------------------*/

/**
 * How many of this variant can still be sold. NULL means "not tracked", which is
 * not the same as zero and must never be rendered as a number.
 */
function sp_store_available( array $variant ): ?int {
	if ( empty( $variant['track_stock'] ) ) return null;
	return (int) $variant['stock'] - (int) $variant['stock_committed'];
}

/** Can a customer put this in a cart right now? */
function sp_store_in_stock( array $variant ): bool {
	if ( ( $variant['status'] ?? 'active' ) !== 'active' ) return false;
	if ( empty( $variant['track_stock'] ) )   return true;
	if ( ! empty( $variant['allow_backorder'] ) ) return true;
	return (int) sp_store_available( $variant ) > 0;
}

/**
 * Reserve stock. THE CONCURRENCY POINT OF THE WHOLE MODULE.
 *
 * One atomic conditional UPDATE: the condition that there is enough stock is
 * evaluated by MySQL as part of the write, and the affected-row count is the
 * answer. Zero rows affected IS "out of stock" — there is no separate check to
 * race against, which is the only reason two people cannot buy the last shirt.
 *
 * Never replace this with SELECT-then-UPDATE, however much clearer it reads.
 *
 * (An UPDATE whose values do not change reports zero affected rows in MySQL,
 * which would make this lie — so a non-positive qty is rejected up front rather
 * than being allowed to look like a stock failure.)
 */
function sp_store_commit_stock( int $variant_id, int $qty ): bool {
	if ( $qty <= 0 ) return false;

	global $wpdb;
	$affected = $wpdb->query( $wpdb->prepare(
		"UPDATE " . sp_store_table( 'variants' ) . "
		 SET stock_committed = stock_committed + %d, updated_at = %s
		 WHERE id = %d
		   AND status = 'active'
		   AND ( track_stock = 0 OR allow_backorder = 1 OR stock_committed + %d <= stock )",
		$qty, current_time( 'mysql', true ), $variant_id, $qty
	) );

	sp_store_flush_variant_cache();
	return $affected === 1;
}

/**
 * Give reserved stock back — an abandoned cart, a cancelled order.
 * GREATEST(0, ...) so a double release can never drive the reservation negative,
 * which would silently hand out stock that does not exist.
 */
function sp_store_release_stock( int $variant_id, int $qty ): bool {
	if ( $qty <= 0 ) return false;

	global $wpdb;
	$ok = $wpdb->query( $wpdb->prepare(
		"UPDATE " . sp_store_table( 'variants' ) . "
		 SET stock_committed = GREATEST(0, stock_committed - %d), updated_at = %s
		 WHERE id = %d",
		$qty, current_time( 'mysql', true ), $variant_id
	) );

	sp_store_flush_variant_cache();
	return false !== $ok;
}

/**
 * Move the on-hand stock level and record WHY in the ledger.
 *
 * Every write to variants.stock goes through here. A stock number on its own can
 * only ever tell you that it is wrong; the ledger tells you when it went wrong
 * and what claimed to have done it.
 *
 * stock_after is read back after the update, so under a concurrent adjustment it
 * records the level as it then stood rather than this row's own arithmetic. The
 * delta is the authoritative part; stock_after is there for reading the history.
 */
function sp_store_adjust_stock( int $variant_id, int $delta, string $reason = 'manual', int $order_id = 0, string $note = '' ): bool {
	if ( ! $delta ) return true;

	global $wpdb;
	$now = current_time( 'mysql', true );

	$ok = $wpdb->query( $wpdb->prepare(
		"UPDATE " . sp_store_table( 'variants' ) . "
		 SET stock = stock + %d, updated_at = %s WHERE id = %d",
		$delta, $now, $variant_id
	) );
	if ( false === $ok ) return false;

	$after = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT stock FROM " . sp_store_table( 'variants' ) . " WHERE id = %d",
		$variant_id
	) );

	$reasons = sp_store_inventory_reasons();
	$wpdb->insert( sp_store_table( 'inventory_log' ), [
		'variant_id'  => $variant_id,
		'order_id'    => $order_id,
		'delta'       => $delta,
		'stock_after' => $after,
		'reason'      => isset( $reasons[ $reason ] ) ? $reason : 'manual',
		'note'        => mb_substr( trim( wp_strip_all_tags( $note ) ), 0, 255 ),
		'user_id'     => get_current_user_id(),
		'created_at'  => $now,
	] );

	sp_store_flush_variant_cache();
	return true;
}

/** Set an absolute stock level, expressed as the delta it really is. */
function sp_store_set_stock( int $variant_id, int $stock, string $reason = 'manual', string $note = '' ): bool {
	$variant = sp_store_get_variant( $variant_id );
	if ( ! $variant ) return false;

	$delta = $stock - (int) $variant['stock'];
	return $delta ? sp_store_adjust_stock( $variant_id, $delta, $reason, 0, $note ) : true;
}


/*--------------------------------------------------------------
# Admin — Variant Editor
--------------------------------------------------------------*/

add_action( 'add_meta_boxes', 'sp_store_add_meta_boxes' );
function sp_store_add_meta_boxes(): void {
	if ( ! sp_store_active() ) return;
	add_meta_box(
		'sp_store_variants',
		'Pricing &amp; Inventory',
		'sp_store_render_variants_box',
		SITE_PULSE_STORE_CPT,
		'normal',
		'high'
	);
	add_meta_box(
		'sp_store_images',
		'Product Images',
		'sp_store_render_images_box',
		SITE_PULSE_STORE_CPT,
		'normal',
		'high'
	);
}

/** Cents to the editable dollars string the form shows. Blank stays blank, not "0.00". */
function sp_store_price_input( int $cents ): string {
	return $cents ? number_format( $cents / 100, 2, '.', '' ) : '';
}

/**
 * The editor is built around ONE rule: you declare options, and the variants are
 * GENERATED from them. You never type an option value into a variant row.
 *
 * The first version of this screen asked for option names in one place and option
 * values in another, as free text, with nothing showing they were different kinds of
 * thing — and the very first product typed "Small" into the NAME field. That is a UI
 * failure, not a user error, and the fix is structural rather than a better label:
 *
 *   - name and values are entered TOGETHER, so a field holding a list ("Small,
 *     Medium, Large") is visibly not the same kind of thing as one holding "Size"
 *   - variant rows are generated as the combinations of those values
 *   - a variant's option cells are READ-ONLY labels backed by hidden inputs
 *
 * That last point makes a wrong value unrepresentable rather than merely discouraged,
 * and it makes duplicate combinations impossible by construction.
 */
function sp_store_render_variants_box( WP_Post $post ): void {
	$product_id = (int) $post->ID;
	$options    = sp_store_options( $product_id );
	$variants   = sp_store_get_variants( $product_id );
	$currency   = sp_store_currency();

	wp_nonce_field( 'sp_store_save_product', 'sp_store_nonce' );

	// ---- Shipping. One answer for the product, so it sits above the variants rather
	// than as a column nobody would set differently row to row.
	echo '<div class="sp-store-block">';
	printf(
		'<label><input type="checkbox" name="sp_store_ships" value="1"%s> <strong>Ships to the customer</strong></label>',
		checked( sp_store_product_ships( $product_id ), true, false )
	);
	echo '<p class="description">Untick for anything that is not posted &mdash; a download, a class, a membership. Checkout then asks for no address and charges no shipping.</p>';
	echo '</div>';

	// ---- Extras. Each appears only while its feature is switched on.
	if ( function_exists( 'sp_store_members_on' ) && sp_store_members_on() ) {
		$plans = sp_member_plans( true );
		$have  = sp_member_product_plan( $product_id );
		echo '<div class="sp-store-block"><h4 class="sp-store-h">Membership</h4>';
		echo '<label>Buying this gives <select name="sp_store_grants_plan"><option value="0">No membership</option>';
		foreach ( $plans as $plan ) {
			printf( '<option value="%d"%s>%s (%s)</option>', (int) $plan['id'], selected( $have, (int) $plan['id'], false ), esc_html( $plan['name'] ), esc_html( sp_member_plan_length_label( $plan ) ) );
		}
		echo '</select></label>';
		if ( ! $plans ) echo '<p class="description">Set up a plan first, in Site Pulse under Settings &rarr; Store.</p>';
		echo '</div>';
	}

	if ( function_exists( 'sp_store_agreements_on' ) && sp_store_agreements_on() ) {
		$mine = sp_store_product_agreements( $product_id );
		echo '<div class="sp-store-block"><h4 class="sp-store-h">Agreements</h4>';
		echo '<input type="hidden" name="sp_store_agreements_shown" value="1">';
		echo '<p class="description">The buyer has to accept these at checkout.</p>';
		foreach ( sp_store_agreements() as $a ) {
			printf( '<label style="display:block;margin:4px 0"><input type="checkbox" name="sp_store_agreements[]" value="%d"%s> %s%s</label>',
				(int) $a['id'], checked( in_array( (int) $a['id'], $mine, true ), true, false ), esc_html( $a['title'] ), $a['signature'] ? ' <span class="description">(signature)</span>' : '' );
		}
		if ( ! sp_store_agreements() ) echo '<p class="description">Write an agreement first, in Site Pulse under Settings &rarr; Store.</p>';
		echo '</div>';
	}

	if ( function_exists( 'sp_store_downloads_on' ) && sp_store_downloads_on() ) {
		echo '<div class="sp-store-block"><h4 class="sp-store-h">Downloads</h4>';
		echo '<p class="description">Files the buyer gets. They receive private links in their receipt and on their account page.</p>';
		foreach ( sp_store_product_downloads( $product_id ) as $f ) {
			printf( '<label style="display:block;margin:4px 0">%s <span class="description">%s</span> &nbsp; <input type="checkbox" name="sp_store_dl_remove[]" value="%s"> remove</label>',
				esc_html( $f['name'] ), esc_html( sp_store_file_size_label( (int) $f['size'] ) ), esc_attr( $f['key'] ) );
		}
		echo '<p><input type="file" name="sp_store_dl_file"> <span class="description">Added when you press Update.</span></p>';
		echo '</div>';
	}

	// ---- Options
	echo '<div class="sp-store-block">';
	echo '<h4 class="sp-store-h">Options</h4>';
	echo '<p class="description">Does this product come in more than one version? Name the option and list its choices. Leave this empty for a product that has only one version.</p>';

	echo '<div id="sp-store-options">';
	for ( $i = 0; $i < 3; $i++ ) {
		$option  = $options[ $i ] ?? null;
		// Always render the row after the last filled one, so there is somewhere to type.
		$visible = $option !== null || $i === count( $options );
		printf(
			'<div class="sp-store-option-row"%s data-index="%d">',
			$visible ? '' : ' hidden',
			$i
		);
		printf( '<span class="sp-store-option-num">Option %d</span>', $i + 1 );
		printf(
			'<label>Name<input type="text" class="sp-store-oname" name="sp_store_options[%d][name]" value="%s" placeholder="%s"></label>',
			$i, esc_attr( $option['name'] ?? '' ), esc_attr( $i === 0 ? 'Size' : ( $i === 1 ? 'Color' : 'Material' ) )
		);
		printf(
			'<label class="sp-store-ovals">Choices<input type="text" class="sp-store-ovalues" name="sp_store_options[%d][values]" value="%s" placeholder="%s"></label>',
			$i,
			esc_attr( implode( ', ', $option['values'] ?? [] ) ),
			esc_attr( $i === 0 ? 'Small, Medium, Large' : ( $i === 1 ? 'Grey, Red' : 'Cotton, Wool' ) )
		);
		echo '<span class="description sp-store-ohint">separate choices with commas</span>';
		echo '</div>';
	}
	echo '</div>';

	echo '<p><button type="button" class="button" id="sp-store-add-option">+ Add another option</button> ';
	echo '<button type="button" class="button button-primary" id="sp-store-generate">Generate variants</button></p>';
	echo '</div>';

	// ---- Variants
	echo '<div class="sp-store-block">';
	echo '<h4 class="sp-store-h">Variants</h4>';
	echo '<p class="description sp-store-varhint">Each row is one thing a customer can buy. Prices are in ' . esc_html( $currency ) . '.</p>';

	// Bulk price controls. Apparel is usually one price across every size, and putting a
	// whole product on sale means every variant — a sale set on only some of them shows
	// on the shop as a RANGE ("$15.00 – $20.00"), which is accurate and almost never what
	// was meant. One button is the difference between that being easy and being a trap.
	echo '<p class="sp-store-bulk">';
	echo 'Set every <strong>regular</strong> price to <input type="text" inputmode="decimal" id="sp-store-bulk-price" placeholder="0.00" style="width:7em"> ';
	echo '<button type="button" class="button" id="sp-store-apply-price">Apply to all</button>';
	echo '</p>';

	echo '<p class="sp-store-bulk">';
	echo 'Set every <strong>sale</strong> price to <input type="text" inputmode="decimal" id="sp-store-bulk-sale" placeholder="0.00" style="width:7em"> ';
	echo '<button type="button" class="button" id="sp-store-apply-sale">Apply to all</button> ';
	echo '<button type="button" class="button" id="sp-store-clear-sale">End the sale</button>';
	echo '<span class="description" style="display:block;margin-top:4px">A sale price on only some variants makes the shop show a price range. &ldquo;End the sale&rdquo; clears every sale price and puts the product back to full price.</span>';
	echo '</p>';

	echo '<p class="sp-store-bulk">';
	echo 'Set every variant to ';
	echo '<button type="button" class="button" id="sp-store-track-all">Track stock</button> ';
	echo '<button type="button" class="button" id="sp-store-untrack-all">Don&rsquo;t track</button> ';
	echo '&nbsp;&nbsp;<button type="button" class="button" id="sp-store-backorder-all">Allow backorder</button> ';
	echo '<button type="button" class="button" id="sp-store-nobackorder-all">No backorder</button>';
	echo '<span class="description" style="display:block;margin-top:4px">';
	echo '<strong>Track</strong> counts this variant&rsquo;s stock &mdash; it can run out, the page shows &ldquo;Only 2 left&rdquo;, and checkout refuses when it&rsquo;s gone. Untracked variants are always available and <em>the Stock number is ignored</em> (right for made-to-order or digital goods). ';
	echo '<strong>Backorder</strong> only does anything when Track is on, and means keep selling past zero &mdash; the count goes negative so you can see how many you owe.';
	echo '</span>';
	echo '</p>';

	echo '<table class="widefat striped sp-store-variants"><thead><tr>';
	for ( $i = 0; $i < 3; $i++ ) {
		printf(
			'<th class="sp-store-opt-col sp-store-opt-%d"%s>%s</th>',
			$i + 1,
			isset( $options[ $i ] ) ? '' : ' hidden',
			esc_html( $options[ $i ]['name'] ?? '' )
		);
	}
	echo '<th>SKU</th><th>Regular price</th><th>Sale price</th><th>Stock</th>';
	echo '<th>Track</th><th>Backorder</th><th>Weight (g)</th><th>Remove</th>';
	echo '</tr></thead><tbody id="sp-store-rows">';

	if ( ! $variants ) {
		// A brand-new product: one blank row, so a single-version product is complete
		// without touching the options block at all.
		echo sp_store_variant_row( 0, [], $options );
	} else {
		foreach ( $variants as $row => $variant ) {
			echo sp_store_variant_row( $row, $variant, $options );
		}
	}

	echo '</tbody></table>';
	echo '<p><button type="button" class="button" id="sp-store-add-variant">+ Add a row by hand</button> ';
	echo '<span class="description">Removing a variant that appears on a past order archives it instead of deleting it, so order history stays readable.</span></p>';
	echo '</div>';

	// The blank-row template JS clones. A template beats cloning the last row, which
	// meant clearing a dozen fields by hand and getting one of them wrong.
	echo '<template id="sp-store-row-tpl">' . sp_store_variant_row( -1, [], $options ) . '</template>';

	sp_store_variants_box_assets();
}

/**
 * One variant row. $index of -1 renders the JS template, with __I__ where the row
 * number goes and the option cells left blank for JS to fill.
 */
function sp_store_variant_row( int $index, array $variant, array $options ): string {
	$i         = $index < 0 ? '__I__' : (string) $index;
	$vid       = (int) ( $variant['id'] ?? 0 );
	$committed = (int) ( $variant['stock_committed'] ?? 0 );
	$archived  = ( $variant['status'] ?? 'active' ) === 'archived';

	$out = '<tr' . ( $archived ? ' class="sp-store-archived"' : '' ) . '>';

	// Option cells are READ-ONLY labels backed by a hidden input. This is the whole
	// point of the redesign: there is nowhere to type a wrong value.
	for ( $n = 1; $n <= 3; $n++ ) {
		$value = (string) ( $variant[ 'option' . $n ] ?? '' );
		$out  .= sprintf(
			'<td class="sp-store-opt-col sp-store-opt-%d"%s>'
			. '<span class="sp-store-optval">%s</span>'
			. '<input type="hidden" class="sp-store-opt-input" name="sp_store_variants[%s][option%d]" value="%s"></td>',
			$n,
			isset( $options[ $n - 1 ] ) ? '' : ' hidden',
			esc_html( $value ),
			$i, $n, esc_attr( $value )
		);
	}

	// The row's hidden id/position ride INSIDE a cell: stray content directly in a <tr>
	// is hoisted out of the table by the HTML parser and detached from its row.
	$out .= sprintf(
		'<td><input type="hidden" name="sp_store_variants[%s][id]" value="%d">'
		. '<input type="hidden" class="sp-store-pos" name="sp_store_variants[%s][position]" value="%s">'
		. '<input type="text" name="sp_store_variants[%s][sku]" value="%s" style="width:100%%"></td>',
		$i, $vid, $i, $index < 0 ? '__I__' : (string) $index, $i, esc_attr( $variant['sku'] ?? '' )
	);

	$out .= sprintf(
		'<td><input type="text" inputmode="decimal" class="sp-store-price" name="sp_store_variants[%s][price]" value="%s" style="width:7em" placeholder="0.00"></td>',
		$i, esc_attr( sp_store_price_input( (int) ( $variant['price_cents'] ?? 0 ) ) )
	);
	$out .= sprintf(
		'<td><input type="text" inputmode="decimal" class="sp-store-sale" name="sp_store_variants[%s][sale_price]" value="%s" style="width:7em" placeholder="&mdash;"></td>',
		$i, esc_attr( sp_store_price_input( (int) ( $variant['sale_price_cents'] ?? 0 ) ) )
	);

	$out .= sprintf(
		'<td><input type="number" name="sp_store_variants[%s][stock]" value="%d" style="width:5.5em" step="1">',
		$i, (int) ( $variant['stock'] ?? 0 )
	);
	if ( $committed > 0 ) $out .= sprintf( '<br><span class="description">%d committed</span>', $committed );
	$out .= '</td>';

	// A NEW row (no track_stock key at all) defaults to tracked, because counting stock
	// is what almost every product wants and "I typed 12 and it never sold out" is a
	// worse surprise than unticking this for made-to-order goods. An EXISTING row is
	// respected exactly, including a deliberate 0 — array_key_exists, not ! empty(),
	// is what keeps those two cases apart.
	$track = array_key_exists( 'track_stock', $variant ) ? ! empty( $variant['track_stock'] ) : true;

	$out .= sprintf(
		'<td style="text-align:center"><input type="checkbox" class="sp-store-track" name="sp_store_variants[%s][track_stock]" value="1"%s></td>',
		$i, checked( $track, true, false )
	);
	$out .= sprintf(
		'<td style="text-align:center"><input type="checkbox" class="sp-store-backorder" name="sp_store_variants[%s][allow_backorder]" value="1"%s></td>',
		$i, checked( ! empty( $variant['allow_backorder'] ), true, false )
	);
	$out .= sprintf(
		'<td><input type="number" name="sp_store_variants[%s][weight_grams]" value="%d" style="width:6em" step="1" min="0"></td>',
		$i, (int) ( $variant['weight_grams'] ?? 0 )
	);
	$out .= sprintf(
		'<td style="text-align:center"><input type="checkbox" class="sp-store-remove" name="sp_store_variants[%s][remove]" value="1"></td>',
		$i
	);

	return $out . '</tr>';
}

/**
 * The editor's CSS/JS, inline and dependency-free. Generation is pure client-side:
 * the rows only have to exist by the time Update is pressed, so there is no reason
 * for the AJAX-per-variation round trip Woo does.
 */
function sp_store_variants_box_assets(): void {
	?>
	<style>
		.sp-store-block      { margin: 0 0 24px; }
		.sp-store-h          { margin: 12px 0 4px; font-size: 14px; }
		.sp-store-option-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 0 0 8px; }
		.sp-store-option-num { min-width: 70px; font-weight: 600; color: #50575e; }
		.sp-store-option-row label { display: flex; align-items: center; gap: 6px; }
		.sp-store-oname      { width: 12em; }
		.sp-store-ovalues    { width: 26em; }
		.sp-store-ohint      { color: #646970; }
		.sp-store-bulk       { margin: 8px 0 12px; }
		.sp-store-variants th { font-weight: 600; }
		.sp-store-variants td { vertical-align: top; }
		.sp-store-optval     { display: inline-block; padding: 6px 0; font-weight: 600; }
		.sp-store-variants tr.sp-store-archived { opacity: .55; }
		.sp-store-variants tr.sp-store-orphan   { background: #fcf3cf; }
		.sp-store-orphan-note { display: block; color: #856404; font-size: 11px; }
		@media screen and (max-width: 860px) {
			.sp-store-ovalues { width: 100%; }
		}
	</style>
	<script>
	( function () {
		var optionsWrap = document.getElementById( 'sp-store-options' );
		var tbody       = document.getElementById( 'sp-store-rows' );
		var tpl         = document.getElementById( 'sp-store-row-tpl' );
		if ( ! optionsWrap || ! tbody || ! tpl ) return;

		function optionRows()  { return Array.prototype.slice.call( optionsWrap.querySelectorAll( '.sp-store-option-row' ) ); }
		function variantRows() { return Array.prototype.slice.call( tbody.querySelectorAll( 'tr' ) ); }

		/** The declared options: [{name, values[]}], skipping any without a name. */
		function readOptions() {
			var out = [];
			optionRows().forEach( function ( row ) {
				if ( row.hidden ) return;
				var name = row.querySelector( '.sp-store-oname' ).value.trim();
				if ( ! name ) return;
				var values = row.querySelector( '.sp-store-ovalues' ).value
					.split( ',' )
					.map( function ( v ) { return v.trim(); } )
					.filter( function ( v, i, a ) { return v !== '' && a.indexOf( v ) === i; } );
				out.push( { name: name, values: values } );
			} );
			return out;
		}

		/** Show or hide the option columns, and relabel their headers, to match. */
		function syncColumns( options ) {
			for ( var n = 1; n <= 3; n++ ) {
				var show = !! options[ n - 1 ];
				document.querySelectorAll( '.sp-store-opt-' + n ).forEach( function ( cell ) {
					cell.hidden = ! show;
					if ( cell.tagName === 'TH' ) cell.textContent = show ? options[ n - 1 ].name : '';
				} );
			}
		}

		/** Every combination of the declared values, in order. */
		function combinations( options ) {
			var combos = [ [ '', '', '' ] ];
			options.forEach( function ( option, index ) {
				var next = [];
				combos.forEach( function ( combo ) {
					option.values.forEach( function ( value ) {
						var copy = combo.slice();
						copy[ index ] = value;
						next.push( copy );
					} );
				} );
				if ( option.values.length ) combos = next;
			} );
			return combos;
		}

		function rowKey( values ) { return values.join( '\u0000' ).toLowerCase(); }

		function rowValues( tr ) {
			return Array.prototype.slice.call( tr.querySelectorAll( '.sp-store-opt-input' ) )
				.map( function ( input ) { return input.value; } );
		}

		function setRowValues( tr, values ) {
			tr.querySelectorAll( '.sp-store-opt-input' ).forEach( function ( input, n ) {
				input.value = values[ n ] || '';
			} );
			tr.querySelectorAll( '.sp-store-optval' ).forEach( function ( span, n ) {
				span.textContent = values[ n ] || '';
			} );
		}

		function blankRow() {
			var html = tpl.innerHTML.replace( /__I__/g, String( Date.now() ) + String( Math.floor( Math.random() * 1000 ) ) );
			var host = document.createElement( 'tbody' );
			host.innerHTML = html;
			return host.firstElementChild;
		}

		/** Renumber [position] so the saved order matches what is on screen. */
		function renumber() {
			variantRows().forEach( function ( tr, i ) {
				var pos = tr.querySelector( '.sp-store-pos' );
				if ( pos ) pos.value = String( i );
			} );
		}

		document.getElementById( 'sp-store-add-option' ).addEventListener( 'click', function () {
			var hidden = optionRows().filter( function ( r ) { return r.hidden; } );
			if ( hidden.length ) hidden[ 0 ].hidden = false;
		} );

		document.getElementById( 'sp-store-generate' ).addEventListener( 'click', function () {
			var options = readOptions();
			syncColumns( options );

			if ( ! options.length ) {
				window.alert( 'Name at least one option and list its choices first.' );
				return;
			}
			var missing = options.filter( function ( o ) { return ! o.values.length; } );
			if ( missing.length ) {
				window.alert( 'Option "' + missing[ 0 ].name + '" has no choices yet. List them separated by commas.' );
				return;
			}

			// Reconcile rather than rebuild. A row whose combination still exists keeps its
			// price, SKU and stock — regenerating must never quietly wipe what was typed,
			// which is exactly what Woo does.
			var existing = {};
			variantRows().forEach( function ( tr ) { existing[ rowKey( rowValues( tr ) ) ] = tr; } );

			var wanted = combinations( options );
			var keep   = {};
			var frag   = document.createDocumentFragment();

			wanted.forEach( function ( values ) {
				var key = rowKey( values );
				var tr  = existing[ key ];
				if ( ! tr ) {
					tr = blankRow();
					setRowValues( tr, values );
				}
				tr.classList.remove( 'sp-store-orphan' );
				var note = tr.querySelector( '.sp-store-orphan-note' );
				if ( note ) note.remove();
				keep[ key ] = true;
				frag.appendChild( tr );
			} );

			// Anything left over is NOT deleted — it may sit on a past order. It is flagged
			// and left for a person to decide about.
			Object.keys( existing ).forEach( function ( key ) {
				if ( keep[ key ] ) return;
				var tr = existing[ key ];
				tr.classList.add( 'sp-store-orphan' );
				if ( ! tr.querySelector( '.sp-store-orphan-note' ) ) {
					var note = document.createElement( 'span' );
					note.className = 'sp-store-orphan-note';
					note.textContent = 'no longer in your options';
					var cell = tr.querySelector( 'td' );
					if ( cell ) cell.appendChild( note );
				}
				frag.appendChild( tr );
			} );

			tbody.innerHTML = '';
			tbody.appendChild( frag );
			renumber();
			// Rows built from the template carry the column visibility as it was when PHP
			// rendered it, so re-sync AFTER they are in the DOM, not just before.
			syncColumns( options );
		} );

		document.getElementById( 'sp-store-add-variant' ).addEventListener( 'click', function () {
			tbody.appendChild( blankRow() );
			renumber();
			syncColumns( readOptions() );
		} );

		function applyToAll( selector, value ) {
			tbody.querySelectorAll( selector ).forEach( function ( input ) { input.value = value; } );
		}

		document.getElementById( 'sp-store-apply-price' ).addEventListener( 'click', function () {
			var value = document.getElementById( 'sp-store-bulk-price' ).value.trim();
			if ( value === '' ) return;
			applyToAll( '.sp-store-price', value );
		} );

		document.getElementById( 'sp-store-apply-sale' ).addEventListener( 'click', function () {
			var value = document.getElementById( 'sp-store-bulk-sale' ).value.trim();
			if ( value === '' ) return;
			applyToAll( '.sp-store-sale', value );
		} );

		// Clearing every sale price is how a sale ends, so it gets its own button rather
		// than relying on somebody emptying each field by hand and missing one.
		document.getElementById( 'sp-store-clear-sale' ).addEventListener( 'click', function () {
			applyToAll( '.sp-store-sale', '' );
			document.getElementById( 'sp-store-bulk-sale' ).value = '';
		} );

		function checkAll( selector, on ) {
			tbody.querySelectorAll( selector ).forEach( function ( box ) { box.checked = on; } );
		}

		[
			[ 'sp-store-track-all',        '.sp-store-track',     true  ],
			[ 'sp-store-untrack-all',      '.sp-store-track',     false ],
			[ 'sp-store-backorder-all',    '.sp-store-backorder', true  ],
			[ 'sp-store-nobackorder-all',  '.sp-store-backorder', false ],
		].forEach( function ( row ) {
			var btn = document.getElementById( row[ 0 ] );
			if ( btn ) btn.addEventListener( 'click', function () { checkAll( row[ 1 ], row[ 2 ] ); } );
		} );

		// Keep the columns honest as the options are typed, so the table always reflects
		// what has been declared without needing a save first.
		optionsWrap.addEventListener( 'input', function () { syncColumns( readOptions() ); } );
	} )();
	</script>
	<?php
}

/**
 * Does this product get posted to the customer? One answer for the whole product —
 * a shirt is shipped in every size, a curriculum in none — read from its variants.
 * A product with no variants yet is assumed to ship, the safe default.
 */
function sp_store_product_ships( int $product_id ): bool {
	$variants = sp_store_get_variants( $product_id );
	if ( ! $variants ) return true;
	foreach ( $variants as $variant ) {
		if ( (int) $variant['requires_shipping'] ) return true;
	}
	return false;
}

/**
 * THE product save. Both editors end here — the wp-admin metaboxes (via
 * sp_store_save_product below) and Site Pulse's product editor — so a rule enforced
 * on one screen cannot be missing from the other.
 *
 * Takes CLEAN data, never $_POST: each caller reads its own form and hands over
 *   options   [ [ 'name' => 'Size', 'values' => 'Small, Medium' | ['Small','Medium'] ], … ]
 *   gallery   [ [ 'id' => 12, 'tag' => '1:Red' ], … ]          omit to leave unchanged
 *   variants  [ row, … ]                                       omit to leave unchanged
 *   ships     bool — applied to every variant                  omit to keep each as is
 * where a variant row is id, remove, option1-3, sku, price, sale_price (as typed),
 * stock ('' = leave alone), track_stock, allow_backorder, weight_grams.
 *
 * @return string[] warnings to show the person who saved — never fatal, the rest saves
 */
function sp_store_save_product_data( int $post_id, array $data ): array {
	$errors = [];

	// ---- Options: name + its declared values, entered together.
	if ( array_key_exists( 'options', $data ) ) {
		$options = [];
		foreach ( (array) $data['options'] as $entry ) {
			$entry = (array) $entry;
			$name  = sanitize_text_field( (string) ( $entry['name'] ?? '' ) );
			if ( trim( $name ) === '' ) continue;
			$values = $entry['values'] ?? '';
			$options[] = [
				'name'   => $name,
				'values' => is_array( $values ) ? sp_store_split_values( implode( ',', array_map( 'strval', $values ) ) ) : sp_store_split_values( (string) $values ),
			];
		}
		sp_store_set_options( $post_id, $options );
	}

	// ---- Gallery: ordered attachment ids, each optionally tagged to an option value.
	if ( array_key_exists( 'gallery', $data ) ) {
		$gallery = [];
		foreach ( (array) $data['gallery'] as $item ) {
			$item = (array) $item;
			$gallery[] = [
				'id'  => (int) ( $item['id'] ?? 0 ),
				'tag' => sanitize_text_field( (string) ( $item['tag'] ?? '' ) ),
			];
		}
		sp_store_set_gallery( $post_id, $gallery );
	}

	// ---- Extras: the membership a purchase gives. Only touched when the caller sends
	// it, which each editor does only while Memberships is switched on — so turning the
	// feature off and saving a product never quietly unlinks its plan.
	if ( array_key_exists( 'grants_plan', $data ) ) {
		$plan = (int) $data['grants_plan'];
		if ( $plan ) update_post_meta( $post_id, '_sp_store_grants_plan', $plan );
		else         delete_post_meta( $post_id, '_sp_store_grants_plan' );
	}

	// ---- Extras: the agreements a buyer must accept. Same rule as grants_plan — only
	// touched when the caller sends it, i.e. while the feature is switched on.
	if ( array_key_exists( 'agreements', $data ) && function_exists( 'sp_store_set_product_agreements' ) ) {
		sp_store_set_product_agreements( $post_id, (array) $data['agreements'] );
	}

	// Ships is a product-level answer stored per variant. Unspecified keeps whatever
	// each variant already says, so a caller that does not know about it changes nothing.
	$ships_given = array_key_exists( 'ships', $data );
	$ships       = $ships_given ? (bool) $data['ships'] : true;

	// ---- Variant rows
	if ( array_key_exists( 'variants', $data ) ) {
		$seen     = [];
		$position = 0;

		foreach ( (array) $data['variants'] as $row ) {
			$row = (array) $row;
			$id  = (int) ( $row['id'] ?? 0 );

			if ( ! empty( $row['remove'] ) ) {
				if ( $id ) sp_store_delete_variant( $id );
				continue;
			}

			$opts = [];
			foreach ( [ 'option1', 'option2', 'option3' ] as $key ) {
				$opts[ $key ] = sanitize_text_field( (string) ( $row[ $key ] ?? '' ) );
			}

			// A row that is blank in every field is an "Add variant" click the user thought
			// better of. Skip it silently rather than saving an empty SKU at $0.
			$blank = implode( '', $opts ) === ''
				&& trim( (string) ( $row['sku'] ?? '' ) ) === ''
				&& trim( (string) ( $row['price'] ?? '' ) ) === '';
			if ( ! $id && $blank ) continue;

			// The DB would reject a duplicate option combination anyway; catching it here
			// means the editor can say which row, instead of the save half-failing.
			$key = strtolower( implode( "\0", $opts ) );
			if ( isset( $seen[ $key ] ) ) {
				$errors[] = sprintf( 'Two rows share the options "%s" — only the first was saved.', sp_store_variant_label( $opts ) );
				continue;
			}
			$seen[ $key ] = true;

			$existing = $id ? sp_store_get_variant( $id ) : null;

			$saved = sp_store_save_variant( [
				'id'                => $id,
				'product_id'        => $post_id,
				'sku'               => sanitize_text_field( (string) ( $row['sku'] ?? '' ) ),
				'option1'           => $opts['option1'],
				'option2'           => $opts['option2'],
				'option3'           => $opts['option3'],
				'position'          => $position++,
				'price_cents'       => sp_store_parse_money( (string) ( $row['price'] ?? '' ) ),
				'sale_price_cents'  => sp_store_parse_money( (string) ( $row['sale_price'] ?? '' ) ),
				'track_stock'       => ! empty( $row['track_stock'] ),
				'allow_backorder'   => ! empty( $row['allow_backorder'] ),
				'requires_shipping' => $ships_given ? $ships : ( $existing ? (bool) (int) $existing['requires_shipping'] : true ),
				'taxable'           => $existing ? (bool) (int) $existing['taxable'] : true,
				'weight_grams'      => (int) ( $row['weight_grams'] ?? 0 ),
				'currency'          => sp_store_currency(),
			] );

			if ( is_wp_error( $saved ) ) {
				$errors[] = $saved->get_error_message();
				continue;
			}

			// A sale price that is not lower than the regular price is ignored at display
			// time (see sp_store_variant_price). Say so — a fat-fingered 150 instead of 15
			// would otherwise just silently not be a sale, which is exactly the kind of
			// quiet nothing-happened this module exists to avoid.
			$regular_cents = sp_store_parse_money( (string) ( $row['price'] ?? '' ) );
			$sale_cents    = sp_store_parse_money( (string) ( $row['sale_price'] ?? '' ) );
			if ( $sale_cents > 0 && $sale_cents >= $regular_cents ) {
				$errors[] = sprintf(
					'"%s": the sale price (%s) is not lower than the regular price (%s), so it is being ignored.',
					sp_store_variant_label( $opts ),
					html_entity_decode( sp_store_money( $sale_cents ), ENT_QUOTES, 'UTF-8' ),
					html_entity_decode( sp_store_money( $regular_cents ), ENT_QUOTES, 'UTF-8' )
				);
			}

			// Stock is applied as a DELTA through the ledger, never written straight to the
			// column — so an edit made here is as explainable as one made by a sale.
			if ( isset( $row['stock'] ) && $row['stock'] !== '' ) {
				sp_store_set_stock( (int) $saved, (int) $row['stock'], 'manual', 'Edited on the product screen' );
			}
		}
	}

	sp_store_ensure_default_variant( $post_id );

	// A product saved without variant rows (the default-variant path, or a caller that
	// only changed "ships") still has to carry the ships answer on what it does have.
	if ( $ships_given ) {
		global $wpdb;
		$wpdb->update( sp_store_table( 'variants' ), [ 'requires_shipping' => $ships ? 1 : 0 ], [ 'product_id' => $post_id ] );
		sp_store_flush_variant_cache( $post_id );
	}

	sp_store_refresh_sort_meta( $post_id );
	return $errors;
}

/**
 * wp-admin's save: read the metabox form, hand it to sp_store_save_product_data().
 * Everything that decides anything lives in that function, not here.
 */
add_action( 'save_post_' . SITE_PULSE_STORE_CPT, 'sp_store_save_product', 10, 2 );
function sp_store_save_product( int $post_id, WP_Post $post ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( wp_is_post_revision( $post_id ) ) return;
	if ( ! isset( $_POST['sp_store_nonce'] ) ) return;
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sp_store_nonce'] ) ), 'sp_store_save_product' ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$data = [
		'options'  => wp_unslash( (array) ( $_POST['sp_store_options'] ?? [] ) ),
		'gallery'  => wp_unslash( (array) ( $_POST['sp_store_gallery'] ?? [] ) ),
		'variants' => wp_unslash( (array) ( $_POST['sp_store_variants'] ?? [] ) ),
		'ships'    => ! empty( $_POST['sp_store_ships'] ),
	];
	// Present in the form only while Memberships is on; absent means "leave it alone".
	if ( isset( $_POST['sp_store_grants_plan'] ) ) $data['grants_plan'] = (int) $_POST['sp_store_grants_plan'];
	// The marker field says the agreements box was on screen, so "none ticked" can be told
	// apart from "the box was not there" (feature off) — only the first clears them.
	if ( isset( $_POST['sp_store_agreements_shown'] ) ) $data['agreements'] = array_map( 'intval', (array) ( $_POST['sp_store_agreements'] ?? [] ) );

	$errors = sp_store_save_product_data( $post_id, $data );

	if ( $errors ) {
		set_transient( 'sp_store_notice_' . $post_id, $errors, 60 );
	}
}

add_action( 'admin_notices', 'sp_store_admin_notices' );
function sp_store_admin_notices(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== SITE_PULSE_STORE_CPT ) return;

	$post_id = (int) ( $_GET['post'] ?? 0 );
	if ( ! $post_id ) return;

	$errors = get_transient( 'sp_store_notice_' . $post_id );
	if ( ! $errors ) return;
	delete_transient( 'sp_store_notice_' . $post_id );

	echo '<div class="notice notice-warning"><p><strong>Check these variants:</strong></p><ul style="list-style:disc;margin-left:2em">';
	foreach ( (array) $errors as $error ) {
		echo '<li>' . esc_html( $error ) . '</li>';
	}
	echo '</ul></div>';
}


/*--------------------------------------------------------------
# Product Gallery
--------------------------------------------------------------*/

/**
 * The product's images, in order: [ [ 'id' => 123, 'tag' => '1:Red' ], ... ].
 *
 * `tag` is empty for an image that always shows, or "{option index}:{value}" for one
 * that belongs to a particular choice — so a photo tagged 2:Red appears for every Red
 * variant whatever its size.
 *
 * TAGGED BY OPTION VALUE, NOT BY VARIANT, and that is the whole point. Three sizes in
 * two colours is six variants but only two colours; per-variant assignment would mean
 * six pickers that each just repeat the colour. What a photo actually depends on is
 * one option, so that is what it is attached to.
 *
 * Attachment ids are validated on read: an image deleted from the media library drops
 * out silently rather than rendering a broken tile.
 */
function sp_store_gallery( int $product_id ): array {
	$raw = get_post_meta( $product_id, '_sp_store_gallery', true );
	if ( ! is_array( $raw ) ) return [];

	$out = [];
	foreach ( $raw as $item ) {
		$id = (int) ( is_array( $item ) ? ( $item['id'] ?? 0 ) : $item );
		if ( $id <= 0 || get_post_type( $id ) !== 'attachment' ) continue;
		$out[] = [
			'id'  => $id,
			'tag' => is_array( $item ) ? trim( (string) ( $item['tag'] ?? '' ) ) : '',
		];
	}
	return $out;
}

/** Store the gallery. Drops blanks and duplicates; an empty list clears the meta. */
function sp_store_set_gallery( int $product_id, array $items ): void {
	$clean = [];
	$seen  = [];
	foreach ( $items as $item ) {
		$id = (int) ( $item['id'] ?? 0 );
		if ( $id <= 0 || isset( $seen[ $id ] ) ) continue;
		$seen[ $id ] = true;
		$clean[] = [ 'id' => $id, 'tag' => trim( (string) ( $item['tag'] ?? '' ) ) ];
	}
	if ( $clean ) update_post_meta( $product_id, '_sp_store_gallery', $clean );
	else          delete_post_meta( $product_id, '_sp_store_gallery' );
}

/**
 * Every taggable option value, as tag => label:
 *   [ '1:Small' => 'Size: Small', '2:Red' => 'Color: Red', ... ]
 * The option INDEX is carried in the tag so two options that happen to share a value
 * ("Red" as both a colour and a finish) cannot be confused for each other.
 */
function sp_store_option_value_tags( int $product_id ): array {
	$tags = [];
	foreach ( sp_store_options( $product_id ) as $index => $option ) {
		foreach ( $option['values'] as $value ) {
			$tags[ ( $index + 1 ) . ':' . $value ] = $option['name'] . ': ' . $value;
		}
	}
	return $tags;
}

/** The tags a given variant matches — one per option it has a value for. */
function sp_store_variant_tags( array $variant ): array {
	$tags = [];
	for ( $n = 1; $n <= 3; $n++ ) {
		$value = trim( (string) ( $variant[ 'option' . $n ] ?? '' ) );
		if ( $value !== '' ) $tags[] = $n . ':' . $value;
	}
	return $tags;
}

/**
 * The images to show for one variant.
 *
 * Anything tagged to one of the variant's own values, plus everything untagged. If
 * that leaves nothing — a product whose images are all tagged to other colours — the
 * whole gallery is returned rather than an empty frame, because a page with no
 * picture is a worse answer than a page with the wrong-ish picture.
 */
function sp_store_variant_images( int $product_id, array $variant ): array {
	$gallery = sp_store_gallery( $product_id );
	if ( ! $gallery ) return [];

	$tags  = sp_store_variant_tags( $variant );
	$shown = [];
	foreach ( $gallery as $item ) {
		if ( $item['tag'] === '' || in_array( $item['tag'], $tags, true ) ) $shown[] = $item;
	}
	return $shown ?: $gallery;
}


/*--------------------------------------------------------------
# Admin — Images
--------------------------------------------------------------*/

add_action( 'admin_enqueue_scripts', 'sp_store_admin_media' );
function sp_store_admin_media( $hook ): void {
	if ( ! sp_store_active() ) return;
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== SITE_PULSE_STORE_CPT ) return;
	// The media modal this metabox opens is core's own; nothing is bundled.
	wp_enqueue_media();
}

function sp_store_render_images_box( WP_Post $post ): void {
	$product_id = (int) $post->ID;
	$gallery    = sp_store_gallery( $product_id );
	$tags       = sp_store_option_value_tags( $product_id );

	echo '<p class="description">The pictures shown on the product page, in this order. Tag a picture with an option choice and it will only appear when a customer picks that choice &mdash; so tagging three photos <em>Color: Red</em> swaps all three in when Red is selected. Leave a picture untagged and it always shows.</p>';

	if ( ! $tags ) {
		echo '<p class="description"><em>Add options and save to be able to tag pictures to a choice.</em></p>';
	}
	if ( ! $gallery ) {
		echo '<p class="description"><em>With no pictures here, the product page falls back to the Featured Image.</em></p>';
	}

	echo '<div id="sp-store-gallery" class="sp-store-gallery">';
	foreach ( $gallery as $i => $item ) {
		echo sp_store_gallery_tile( $i, $item, $tags );
	}
	echo '</div>';

	echo '<p><button type="button" class="button button-primary" id="sp-store-add-images">Add pictures</button></p>';

	echo '<template id="sp-store-tile-tpl">' . sp_store_gallery_tile( -1, [ 'id' => 0, 'tag' => '' ], $tags ) . '</template>';

	sp_store_images_box_assets();
}

/** One gallery tile. $index of -1 renders the JS template (__I__ / __ID__ / __SRC__). */
function sp_store_gallery_tile( int $index, array $item, array $tags ): string {
	$i   = $index < 0 ? '__I__' : (string) $index;
	$id  = $index < 0 ? '__ID__' : (string) (int) $item['id'];
	$src = $index < 0 ? '__SRC__' : (string) ( wp_get_attachment_image_url( (int) $item['id'], 'thumbnail' ) ?: '' );

	$out  = '<div class="sp-store-tile" draggable="true">';
	$out .= sprintf( '<input type="hidden" class="sp-store-tile-id" name="sp_store_gallery[%s][id]" value="%s">', $i, esc_attr( $id ) );
	$out .= sprintf( '<img src="%s" alt="">', esc_url( $src ) );

	$out .= sprintf( '<select class="sp-store-tile-tag" name="sp_store_gallery[%s][tag]">', $i );
	$out .= '<option value="">Always show</option>';
	foreach ( $tags as $tag => $label ) {
		$out .= sprintf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $tag ),
			$index >= 0 ? selected( $item['tag'], $tag, false ) : '',
			esc_html( $label )
		);
	}
	// A tag whose option value has since been renamed or removed would otherwise vanish
	// from the select and silently reset to "always show" on the next save.
	if ( $index >= 0 && $item['tag'] !== '' && ! isset( $tags[ $item['tag'] ] ) ) {
		$out .= sprintf( '<option value="%s" selected>%s (no longer an option)</option>',
			esc_attr( $item['tag'] ), esc_html( $item['tag'] ) );
	}
	$out .= '</select>';

	$out .= '<button type="button" class="sp-store-tile-remove" aria-label="Remove picture" title="Remove picture">&times;</button>';
	return $out . '</div>';
}

function sp_store_images_box_assets(): void {
	?>
	<style>
		.sp-store-gallery { display: flex; flex-wrap: wrap; gap: 12px; margin: 12px 0; }
		.sp-store-tile    { position: relative; width: 150px; padding: 8px; border: 1px solid #dcdcde; border-radius: 4px; background: #fff; cursor: grab; }
		.sp-store-tile.is-dragging { opacity: .4; }
		.sp-store-tile img { display: block; width: 100%; height: 110px; object-fit: cover; border-radius: 2px; background: #f0f0f1; }
		.sp-store-tile select { width: 100%; margin-top: 6px; font-size: 12px; }
		/* INSIDE the tile, not hanging off the corner: a negative offset is clipped to
		   nothing by any ancestor with overflow hidden, and a metabox is a likely one. */
		.sp-store-tile-remove { position: absolute; top: 12px; right: 12px; z-index: 2; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; padding: 0; border: 0; border-radius: 50%; background: #b32d2e; color: #fff !important; font-size: 17px; line-height: 1; text-decoration: none; cursor: pointer; box-shadow: 0 1px 4px rgba(0,0,0,.45); }
		.sp-store-tile-remove:hover { background: #8a2324; }
	</style>
	<script>
	( function () {
		var wrap = document.getElementById( 'sp-store-gallery' );
		var tpl  = document.getElementById( 'sp-store-tile-tpl' );
		var add  = document.getElementById( 'sp-store-add-images' );
		if ( ! wrap || ! tpl || ! add ) return;

		var next = wrap.querySelectorAll( '.sp-store-tile' ).length;

		/** Field names carry the row index, so they must be rewritten after any reorder. */
		function renumber() {
			Array.prototype.forEach.call( wrap.querySelectorAll( '.sp-store-tile' ), function ( tile, i ) {
				tile.querySelectorAll( '[name]' ).forEach( function ( field ) {
					field.name = field.name.replace( /sp_store_gallery\[[^\]]*\]/, 'sp_store_gallery[' + i + ']' );
				} );
			} );
		}

		add.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( {
				title: 'Add product pictures',
				button: { text: 'Use these pictures' },
				library: { type: 'image' },
				multiple: true
			} );

			frame.on( 'select', function () {
				frame.state().get( 'selection' ).each( function ( attachment ) {
					var a = attachment.toJSON();
					// Don't add the same image twice — a duplicate tile would just be two
					// rows fighting over one tag.
					var already = Array.prototype.some.call( wrap.querySelectorAll( '.sp-store-tile-id' ), function ( input ) {
						return parseInt( input.value, 10 ) === a.id;
					} );
					if ( already ) return;

					var thumb = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
					var html  = tpl.innerHTML
						.replace( /__I__/g, String( next++ ) )
						.replace( /__ID__/g, String( a.id ) )
						.replace( /__SRC__/g, thumb );
					var host = document.createElement( 'div' );
					host.innerHTML = html;
					wrap.appendChild( host.firstElementChild );
				} );
				renumber();
			} );

			frame.open();
		} );

		wrap.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.sp-store-tile-remove' );
			if ( ! btn ) return;
			e.preventDefault();
			btn.closest( '.sp-store-tile' ).remove();
			renumber();
		} );

		// Drag to reorder. The first picture is the one the product page opens on, so
		// order is a real setting and not decoration.
		var dragging = null;
		wrap.addEventListener( 'dragstart', function ( e ) {
			dragging = e.target.closest( '.sp-store-tile' );
			if ( dragging ) dragging.classList.add( 'is-dragging' );
		} );
		wrap.addEventListener( 'dragend', function () {
			if ( dragging ) dragging.classList.remove( 'is-dragging' );
			dragging = null;
			renumber();
		} );
		wrap.addEventListener( 'dragover', function ( e ) {
			if ( ! dragging ) return;
			e.preventDefault();
			var over = e.target.closest( '.sp-store-tile' );
			if ( ! over || over === dragging ) return;
			var rect = over.getBoundingClientRect();
			var after = ( e.clientX - rect.left ) > rect.width / 2;
			wrap.insertBefore( dragging, after ? over.nextSibling : over );
		} );
	} )();
	</script>
	<?php
}


/*--------------------------------------------------------------
# Admin — Columns
--------------------------------------------------------------*/

/**
 * The product list table. Deferred to admin_init because functions-admin-columns.php
 * is required from functions-admin.php, which loads AFTER includes-site-pulse.php —
 * calling bp_admin_columns() at file scope here would hit an undefined function.
 */
add_action( 'admin_init', 'sp_store_register_admin_columns', 20 );
function sp_store_register_admin_columns(): void {
	if ( ! sp_store_active() ) return;
	if ( ! function_exists( 'bp_admin_columns' ) || ! function_exists( 'bp_col' ) ) return;

	bp_admin_columns( [
		'post_type' => SITE_PULSE_STORE_CPT,
		'columns'   => array_merge(
			[ 'bp-featured-image' => [ 'type' => 'bp-featured-image', 'label' => '' ] ],
			[ 'bp-post-id'        => [ 'type' => 'bp-post-id', 'label' => 'ID', 'sortable' => true ] ],
			[ 'bp-title'          => [ 'type' => 'bp-title', 'label' => 'Product', 'sortable' => true ] ],
			bp_col( 'sp-store-price', 'Price', 'sp_store_column_price' ),
			bp_col( 'sp-store-stock', 'Stock', 'sp_store_column_stock' ),
			bp_col( 'sp-store-sku',   'SKU',   'sp_store_column_sku' ),
			bp_taxonomy( 'bp-product-cat', 'bp_product_cat', 'Category', [] ),
			[ 'bp-modified'       => [ 'type' => 'bp-modified', 'label' => 'Updated', 'sortable' => true ] ]
		),
	] );
}

function sp_store_column_price( int $post_id ): string {
	$label = sp_store_price_range_label( $post_id );
	if ( $label === '' ) return '<span style="color:#b32d2e">No variants</span>';

	$count = sp_store_price_range( $post_id )['count'];
	return $label . ( $count > 1 ? '<br><span class="description">' . (int) $count . ' variants</span>' : '' );
}

function sp_store_column_stock( int $post_id ): string {
	$variants = sp_store_get_variants( $post_id, true );
	if ( ! $variants ) return '&mdash;';

	$tracked = array_filter( $variants, static fn( $v ) => ! empty( $v['track_stock'] ) );
	if ( ! $tracked ) return '<span class="description">Not tracked</span>';

	$total = 0;
	$out   = 0;
	$low   = 0;
	foreach ( $tracked as $variant ) {
		$available = (int) sp_store_available( $variant );
		$total    += $available;
		if ( $available <= 0 ) $out++;
		elseif ( (int) $variant['low_stock_at'] > 0 && $available <= (int) $variant['low_stock_at'] ) $low++;
	}

	$color = $out ? '#b32d2e' : ( $low ? '#996800' : '#2ecc71' );
	$note  = $out ? sprintf( '%d out of stock', $out ) : ( $low ? sprintf( '%d low', $low ) : '' );

	return '<strong style="color:' . $color . '">' . (int) $total . '</strong>'
		. ( $note ? '<br><span class="description">' . esc_html( $note ) . '</span>' : '' );
}

function sp_store_column_sku( int $post_id ): string {
	$skus = array_values( array_filter( array_map(
		static fn( $v ) => trim( (string) $v['sku'] ),
		sp_store_get_variants( $post_id, true )
	) ) );

	if ( ! $skus ) return '&mdash;';
	if ( count( $skus ) === 1 ) return esc_html( $skus[0] );

	return esc_html( $skus[0] ) . '<br><span class="description">+' . ( count( $skus ) - 1 ) . ' more</span>';
}


/*--------------------------------------------------------------
# SEO
--------------------------------------------------------------*/

/**
 * Product pages are the whole reason the catalog is a post type rather than a
 * table, so make sure nothing quietly excludes them.
 *
 * The SEO module noindexes the legacy WooCommerce `products` post type and drops
 * it from the sitemap. That rule is aimed at Woo's thin auto-generated pages and
 * keys on a different slug, so bp_product is not caught by it today — this filter
 * states the intent anyway, so a future tightening of that list cannot silently
 * deindex a client's shop.
 */
add_filter( 'bp_sitemap_exclude_post_types', 'sp_store_keep_products_in_sitemap' );
function sp_store_keep_products_in_sitemap( $drop ) {
	return array_values( array_diff( (array) $drop, [ SITE_PULSE_STORE_CPT ] ) );
}

/**
 * Product structured data. Offers carry the real price and availability, which is
 * what earns the price and in-stock treatment in search results — and which is
 * exactly the data a table-backed catalog has and a postmeta one has to go
 * digging for.
 */
add_action( 'wp_head', 'sp_store_product_schema', 20 );
function sp_store_product_schema(): void {
	if ( ! sp_store_active() || ! is_singular( SITE_PULSE_STORE_CPT ) ) return;

	$product_id = get_the_ID();
	$variants   = sp_store_get_variants( $product_id, true );
	if ( ! $variants ) return;

	$currency = sp_store_currency();
	$offers   = [];
	foreach ( $variants as $variant ) {
		$offer = [
			'@type'         => 'Offer',
			'price'         => number_format( sp_store_variant_price( $variant ) / 100, 2, '.', '' ),
			'priceCurrency' => (string) ( $variant['currency'] ?: $currency ),
			'availability'  => sp_store_in_stock( $variant )
				? 'https://schema.org/InStock'
				: 'https://schema.org/OutOfStock',
			'url'           => get_permalink( $product_id ),
		];
		if ( trim( (string) $variant['sku'] ) !== '' ) $offer['sku'] = (string) $variant['sku'];
		$offers[] = $offer;
	}

	$schema = [
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => wp_strip_all_tags( get_the_title( $product_id ) ),
		'description' => wp_strip_all_tags( get_the_excerpt( $product_id ) ),
		'url'         => get_permalink( $product_id ),
		'offers'      => count( $offers ) === 1 ? $offers[0] : $offers,
	];

	$image = get_the_post_thumbnail_url( $product_id, 'large' );
	if ( $image ) $schema['image'] = $image;

	$brand = trim( (string) get_post_meta( $product_id, '_sp_store_brand', true ) );
	if ( $brand !== '' ) $schema['brand'] = [ '@type' => 'Brand', 'name' => $brand ];

	echo "\n" . '<script type="application/ld+json">'
		. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. '</script>' . "\n";
}
