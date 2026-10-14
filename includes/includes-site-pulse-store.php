<?php
/* Battle Plan Web Design — Site Pulse: Store

/*--------------------------------------------------------------
A proprietary store for Battle Plan clients — merch, apparel, parts, gift
items — replacing WooCommerce on the handful of fleet sites that sell things.

The reason it exists is maintenance, not licensing. Woo is free; what it costs
is a permanent tax of template overrides, filter archaeology, and plugin churn
that breaks a checkout quietly and is discovered by a customer. This module is
deliberately small enough to read, has no third-party dependency, and reports
its own health to the fleet hub so a broken store is noticed here rather than
in an email from the client.

THE SPLIT THAT DEFINES THIS MODULE:

  product CONTENT  lives in a WordPress post type. Titles, descriptions,
                   galleries, categories, permalinks. That buys the framework
                   SEO module (includes-seo.php works off public post types),
                   the media library, media-replace, alt-sync, and
                   bp_admin_columns() inline editing for nothing.

  product COMMERCE lives in the variants table below. Price, SKU, stock,
                   weight. Stock is a real indexed integer column because
                   selling the last one of something is a concurrency problem
                   and postmeta cannot express it safely.

Woo's postmeta swamp comes from putting the second thing in the first place.

THE OTHER DEFINING CALL: there is no simple-vs-variable product distinction.
EVERY product has at least one variant row; a product with no options has one
default variant. Woo's split between simple and variable products is the source
of most of its branching, and every extension has to handle both.

Audience: the STAFF side is Site Pulse (orders, fulfillment, inventory). The
CUSTOMER side is the storefront on the client's own public site. Customers are
not wp_users: an order links to a cc_customers row when Customer Connect is
installed, and always carries its own name/email/phone snapshot, so the store
works on a site running Site Pulse alone.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Constants & Table Helper
# Vocabulary (statuses, methods)
# Permission Helpers
# Settings & Secret Storage
# Money & Identifiers
# Database Schema
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Constants & Table Helper
--------------------------------------------------------------*/

// The store owns its own schema version, separate from SITE_PULSE_DB_VERSION, so a
// store schema change doesn't re-run dbDelta across all ~40 core Site Pulse tables
// on every install in the fleet (and vice versa). Same idiom as Booking and Fleet.
define( 'SITE_PULSE_STORE_DB_VERSION', '1.1' );

/** The post type that holds product CONTENT. Commerce lives in the tables below. */
define( 'SITE_PULSE_STORE_CPT', 'bp_product' );

/**
 * All store tables live under the site_pulse_store_ prefix, so the whole module
 * is one SHOW TABLES LIKE '%site_pulse_store%' away from being audited, dumped,
 * or dropped.
 */
function sp_store_table( string $name ): string {
	return $GLOBALS['wpdb']->prefix . 'site_pulse_store_' . $name;
}

// The other halves of the module. These load BELOW the defines above, not at the
// top of the file: the catalog hooks save_post_{SITE_PULSE_STORE_CPT} at file
// scope, so the constant has to exist by the time it is required.
require_once get_template_directory() . '/includes/includes-site-pulse-store-catalog.php';
require_once get_template_directory() . '/includes/includes-site-pulse-store-cart.php';
require_once get_template_directory() . '/includes/includes-site-pulse-store-totals.php';
require_once get_template_directory() . '/includes/includes-site-pulse-store-providers.php';
require_once get_template_directory() . '/includes/includes-site-pulse-store-checkout.php';
require_once get_template_directory() . '/includes/includes-site-pulse-store-checkout-page.php';
require_once get_template_directory() . '/includes/includes-site-pulse-store-admin.php';
require_once get_template_directory() . '/includes/includes-site-pulse-store-front.php';


/*--------------------------------------------------------------
# Vocabulary (statuses, methods)
--------------------------------------------------------------*/

/**
 * An order carries THREE independent statuses, not one.
 *
 * Woo folds lifecycle, money, and shipping into a single wc-* status, which is
 * why "processing" has to mean paid-but-not-shipped and there is nowhere to
 * record a paid order that was partially refunded and partially shipped. Keep
 * the three axes apart and every one of those states is just a combination.
 *
 * This axis is the ORDER's own lifecycle — does it exist and is it live?
 *   pending    created, awaiting a completed checkout. Holds committed stock.
 *   open       a real order. The normal state for everything in the queue.
 *   cancelled  called off. Committed stock has been released.
 *   archived   done with, hidden from the working list. Never deleted.
 */
function sp_store_order_statuses(): array {
	return [
		'pending'   => 'Pending',
		'open'      => 'Open',
		'cancelled' => 'Cancelled',
		'archived'  => 'Archived',
	];
}

/** Axis two: where the MONEY stands. Refunds move this, never the order status. */
function sp_store_payment_statuses(): array {
	return [
		'unpaid'             => 'Unpaid',
		'authorized'         => 'Authorized',
		'paid'               => 'Paid',
		'partially_refunded' => 'Partially refunded',
		'refunded'           => 'Refunded',
		'failed'             => 'Failed',
	];
}

/** Axis three: where the GOODS stand. */
function sp_store_fulfillment_statuses(): array {
	return [
		'unfulfilled'         => 'Unfulfilled',
		'partially_fulfilled' => 'Partially fulfilled',
		'fulfilled'           => 'Fulfilled',
		'returned'            => 'Returned',
	];
}

/**
 * The order statuses that HOLD COMMITTED STOCK — the ones inventory must treat
 * as spoken for. A cancelled order releases; an archived one already shipped.
 */
function sp_store_committing_statuses(): array {
	return [ 'pending', 'open' ];
}

/** Where an order came from. */
function sp_store_sources(): array {
	return [
		'web'   => 'Website',
		'pwa'   => 'Customer App',
		'staff' => 'Entered by staff',
		'pos'   => 'In person',
	];
}

/**
 * How a shipping rate decides whether it applies and what it costs.
 *   flat    one price for the order
 *   free    no charge (usually with a minimum subtotal)
 *   weight  banded on total cart weight
 *   price   banded on subtotal
 * Live carrier rates are deliberately not here — see the shipping_rates table.
 */
function sp_store_shipping_methods(): array {
	return [
		'flat'   => 'Flat rate',
		'free'   => 'Free shipping',
		'weight' => 'Rate by weight',
		'price'  => 'Rate by order total',
	];
}

/** Why inventory moved. Every change to variants.stock writes one of these. */
function sp_store_inventory_reasons(): array {
	return [
		'sale'    => 'Sold',
		'refund'  => 'Refunded',
		'cancel'  => 'Order cancelled',
		'restock' => 'Restocked',
		'manual'  => 'Manual adjustment',
		'import'  => 'Imported',
		'shrink'  => 'Damaged or lost',
	];
}


/*--------------------------------------------------------------
# Permission Helpers
--------------------------------------------------------------*/

/** Can this user see the store area at all? */
function sp_store_can_view( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id )
		|| site_pulse_user_can( $user_id, 'view_orders' )
		|| site_pulse_user_can( $user_id, 'manage_orders' )
		|| site_pulse_user_can( $user_id, 'manage_products' );
}

/** Can fulfill, cancel, edit and enter orders. */
function sp_store_can_manage_orders( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_orders' );
}

/** Can edit products, variants, pricing and stock. */
function sp_store_can_manage_products( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_products' );
}

/** Can refund money and see payment records. */
function sp_store_can_manage_payments( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_store_payments' );
}

/** Can change store settings — Stripe keys, tax, shipping rates. */
function sp_store_can_manage_settings( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_store_settings' );
}


/*--------------------------------------------------------------
# Settings & Secret Storage
--------------------------------------------------------------*/

/** A store setting, namespaced in the shared site_pulse config table. */
function sp_store_get_setting( string $key, string $default = '' ): string {
	return site_pulse_get_setting( 'store_' . $key, $default );
}

function sp_store_set_setting( string $key, string $value ): void {
	site_pulse_set_setting( 'store_' . $key, $value );
}

/**
 * The key used to encrypt payment secrets at rest. Derived from the install's own
 * WP salts, so it is unique per site and never stored in the database that holds
 * the ciphertext. Namespaced apart from Booking's, so the two modules' secrets are
 * not interchangeable even on a site running both. If the salts are ever rotated
 * the stored secrets become unreadable — the correct failure mode: re-enter them.
 */
function sp_store_secret_key(): string {
	$salt = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' );
	return hash( 'sha256', 'sp_store|' . $salt, true );
}

/**
 * Store a payment secret ENCRYPTED, not base64'd. The Claude/Google keys elsewhere
 * in Site Pulse are only obfuscated, which is defensible for a metered read API and
 * is not defensible for a Stripe secret key, where read access to the database is
 * read access to the money. AES-256-GCM with a random IV, the auth tag stored
 * alongside; storing an empty string clears the key.
 */
function sp_store_set_secret( string $key, string $value ): void {
	$value = trim( $value );
	if ( $value === '' ) { sp_store_set_setting( $key, '' ); return; }
	if ( ! function_exists( 'openssl_encrypt' ) ) {
		// No OpenSSL: refuse to silently downgrade a payment secret to plaintext.
		sp_store_set_setting( $key, '' );
		return;
	}
	$iv  = random_bytes( 12 );
	$tag = '';
	$ct  = openssl_encrypt( $value, 'aes-256-gcm', sp_store_secret_key(), OPENSSL_RAW_DATA, $iv, $tag );
	if ( $ct === false ) { sp_store_set_setting( $key, '' ); return; }
	sp_store_set_setting( $key, 'v1:' . base64_encode( $iv . $tag . $ct ) );
}

/** Read back a secret stored by sp_store_set_secret(). Empty when unset or undecryptable. */
function sp_store_get_secret( string $key ): string {
	$raw = sp_store_get_setting( $key, '' );
	if ( $raw === '' || strpos( $raw, 'v1:' ) !== 0 ) return '';
	$bin = base64_decode( substr( $raw, 3 ), true );
	if ( $bin === false || strlen( $bin ) < 29 ) return '';
	$iv  = substr( $bin, 0, 12 );
	$tag = substr( $bin, 12, 16 );
	$ct  = substr( $bin, 28 );
	$out = openssl_decrypt( $ct, 'aes-256-gcm', sp_store_secret_key(), OPENSSL_RAW_DATA, $iv, $tag );
	return $out === false ? '' : $out;
}

/** Mask a secret for display — never send the real value back to the browser. */
function sp_store_mask_secret( string $value ): string {
	if ( $value === '' ) return '';
	return substr( $value, 0, 7 ) . '…' . substr( $value, -4 );
}


/*--------------------------------------------------------------
# Money & Identifiers
--------------------------------------------------------------*/

/**
 * Money is stored as INTEGER CENTS everywhere, never as a float or a decimal
 * string. Stripe's API speaks cents, so this also removes every conversion
 * between here and the money.
 */
function sp_store_currency(): string {
	$c = strtoupper( trim( sp_store_get_setting( 'currency', 'USD' ) ) );
	return preg_match( '/^[A-Z]{3}$/', $c ) ? $c : 'USD';
}

/** Cents to a display string. Presentation only — never feed this back into math. */
function sp_store_money( int $cents, string $currency = '' ): string {
	$currency = $currency !== '' ? strtoupper( $currency ) : sp_store_currency();
	$symbols  = [ 'USD' => '$', 'CAD' => '$', 'GBP' => '&pound;', 'EUR' => '&euro;' ];
	$symbol   = $symbols[ $currency ] ?? '';
	$sign     = $cents < 0 ? '-' : '';
	$amount   = number_format( abs( $cents ) / 100, 2 );
	return $symbol !== ''
		? $sign . $symbol . $amount
		: $sign . $amount . ' ' . $currency;
}

/**
 * A typed price ("19.99", "$1,299", " 24 ") to integer cents.
 *
 * Everything a human types at a price field lands here, so it is deliberately
 * forgiving about currency symbols, thousands separators and stray spaces — and
 * deliberately unforgiving about the result: anything it cannot read becomes 0,
 * never a half-parsed number. Rounds rather than truncates, so 19.995 is 2000
 * and not 1999.
 */
function sp_store_parse_money( string $input ): int {
	$clean = preg_replace( '/[^0-9.\-]/', '', $input );
	if ( $clean === '' || $clean === '-' || $clean === '.' ) return 0;
	return (int) round( ( (float) $clean ) * 100 );
}

/**
 * A human-quotable order number. Unambiguous alphabet (no O/0/I/1) so it can be
 * read down a phone. Random rather than sequential on purpose: a sequential
 * number tells every customer how many orders the business has taken.
 */
function sp_store_generate_number(): string {
	$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$prefix   = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', sp_store_get_setting( 'number_prefix', 'BP' ) ) . 'BP', 0, 2 ) );
	$code     = '';
	for ( $i = 0; $i < 6; $i++ ) $code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
	return $prefix . '-' . $code;
}

/** A cart token: opaque, unguessable, and the only thing the browser holds. */
function sp_store_generate_cart_token(): string {
	return bin2hex( random_bytes( 24 ) );
}

/**
 * The value written into orders.provider_checkout, which carries a UNIQUE index
 * with `provider`. One Stripe Checkout Session can therefore produce exactly one
 * order, no matter how many times the webhook is delivered or how the customer
 * hammers the back button — Stripe guarantees at-least-once delivery, so this
 * WILL be exercised in production.
 *
 * NULL is returned for an order with no checkout session (a staff-entered or
 * in-person order). MySQL unique indexes ignore NULLs, which is the partial-index
 * behaviour needed here; an empty string would NOT be ignored and the second
 * staff order would fail to insert. Same trick as Booking's slot_guard.
 */
function sp_store_checkout_guard( string $session_id ): ?string {
	$session_id = trim( $session_id );
	return $session_id === '' ? null : $session_id;
}


/*--------------------------------------------------------------
# Database Schema
--------------------------------------------------------------*/

/**
 * Create/upgrade the store tables. Runs only when the module is ON, so the fleet
 * sites that never sell anything stay clean. Bump SITE_PULSE_STORE_DB_VERSION to
 * ship a schema change.
 *
 * NB: keep prose OUT of the CREATE TABLE strings. A comment inside one is parsed
 * by dbDelta as part of the column list, and a double quote or a $ inside it
 * terminates the PHP string. Booking shipped broken that way once.
 */
add_action( 'init', 'sp_store_install_db' );
function sp_store_install_db(): void {
	// Cheap autoloaded-option check first, so the settled case costs no query.
	if ( get_option( 'site_pulse_store_db_version' ) === SITE_PULSE_STORE_DB_VERSION ) return;
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'store' ) ) return;

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	// ---- 1.1: compare_at_cents -> sale_price_cents.
	//
	// The column changed MEANING, not just name. It used to hold a "compare at" price —
	// the higher struck-through number, with `price` being what you actually pay. That
	// forces two edits to put something on sale and two more to take it off, and the
	// regular price has to be retyped from memory. Now `price_cents` is the REGULAR
	// price and never moves, and `sale_price_cents` is the discount when there is one.
	//
	// Values carry over unchanged because the two readings coincide for anything not on
	// sale (both are 0), and nothing shipped with real sale data.
	//
	// Runs BEFORE dbDelta on purpose — see above.
	$variants_table = sp_store_table( 'variants' );
	$cols = $wpdb->get_col( $wpdb->prepare(
		"SELECT COLUMN_NAME FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
		   AND COLUMN_NAME IN ('compare_at_cents','sale_price_cents')",
		$variants_table
	) ) ?: [];
	if ( in_array( 'compare_at_cents', $cols, true ) && ! in_array( 'sale_price_cents', $cols, true ) ) {
		$wpdb->query( "ALTER TABLE $variants_table CHANGE compare_at_cents sale_price_cents int(11) NOT NULL DEFAULT 0" );
	}

	// ---- Variants — the thing that is actually bought. One row per SKU.
	//
	// EVERY product has at least one, including a product with no options, which gets a
	// single default variant. There is no simple-vs-variable product split anywhere in
	// this module; that split is where Woo's branching comes from.
	//
	// product_id is a post ID in the SITE_PULSE_STORE_CPT post type. Option NAMES ("Size",
	// "Color") live in post meta on the product because they describe the product; option
	// VALUES live here because they identify the variant.
	//
	// STOCK IS TWO NUMBERS, and this is the part that must stay right:
	//   stock            what is physically on the shelf
	//   stock_committed  what is spoken for by a pending checkout or an unshipped order
	// Available = stock - stock_committed. Committing is ONE atomic conditional UPDATE
	// (SET stock_committed = stock_committed + n WHERE ... AND stock_committed + n <= stock)
	// and the affected-row count is the answer — zero rows IS "out of stock". Never read
	// the level and then decide; that race is how two people buy the last shirt.
	$sql = "CREATE TABLE " . sp_store_table( 'variants' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		product_id bigint(20) unsigned NOT NULL,
		sku varchar(100) NOT NULL DEFAULT '',
		title varchar(255) NOT NULL DEFAULT '',
		option1 varchar(100) NOT NULL DEFAULT '',
		option2 varchar(100) NOT NULL DEFAULT '',
		option3 varchar(100) NOT NULL DEFAULT '',
		position int(11) NOT NULL DEFAULT 0,
		price_cents int(11) NOT NULL DEFAULT 0,
		sale_price_cents int(11) NOT NULL DEFAULT 0,
		cost_cents int(11) NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'USD',
		track_stock tinyint(1) NOT NULL DEFAULT 1,
		stock int(11) NOT NULL DEFAULT 0,
		stock_committed int(11) NOT NULL DEFAULT 0,
		low_stock_at int(11) NOT NULL DEFAULT 0,
		allow_backorder tinyint(1) NOT NULL DEFAULT 0,
		requires_shipping tinyint(1) NOT NULL DEFAULT 1,
		weight_grams int(11) NOT NULL DEFAULT 0,
		taxable tinyint(1) NOT NULL DEFAULT 1,
		tax_code varchar(40) NOT NULL DEFAULT '',
		image_id bigint(20) unsigned NOT NULL DEFAULT 0,
		barcode varchar(100) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'active',
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY product_options (product_id, option1, option2, option3),
		KEY sku (sku),
		KEY status (status),
		KEY product_id (product_id)
	) $charset;";

	// ---- Carts — an in-progress order, keyed by an opaque token the browser holds.
	//
	// Server-side, not a cookie blob: a cart that lives in the browser cannot reserve
	// stock, cannot be recovered on another device, and cannot be looked at when a
	// customer phones to ask what happened. The cookie holds only the token.
	$sql .= "CREATE TABLE " . sp_store_table( 'carts' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		token varchar(64) NOT NULL,
		user_id int(11) NOT NULL DEFAULT 0,
		customer_id int(11) NOT NULL DEFAULT 0,
		order_id int(11) NOT NULL DEFAULT 0,
		email varchar(190) NOT NULL DEFAULT '',
		currency char(3) NOT NULL DEFAULT 'USD',
		status varchar(20) NOT NULL DEFAULT 'open',
		item_count int(11) NOT NULL DEFAULT 0,
		subtotal_cents int(11) NOT NULL DEFAULT 0,
		note varchar(255) NOT NULL DEFAULT '',
		ip varchar(45) NOT NULL DEFAULT '',
		meta longtext DEFAULT NULL,
		expires_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token (token),
		KEY status_expires (status, expires_at),
		KEY customer_id (customer_id),
		KEY email (email)
	) $charset;";

	// ---- Cart lines.
	//
	// UNIQUE (cart_id, variant_id) means adding the same variant twice BUMPS THE QUANTITY
	// rather than creating a second line. Duplicate lines for one SKU are a bug that looks
	// like a feature until someone tries to reconcile stock against them.
	//
	// unit_price_cents is snapshotted when the line is added, so a price change mid-session
	// cannot silently alter what the customer was shown. Re-pricing is an explicit step at
	// checkout, where the customer can be told.
	//
	// `committed` records how many units of this line are currently reserved against
	// variants.stock_committed, so releasing a cart releases exactly what it took — never
	// what the line happens to say now.
	$sql .= "CREATE TABLE " . sp_store_table( 'cart_items' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		cart_id int(11) NOT NULL,
		variant_id int(11) NOT NULL,
		product_id bigint(20) unsigned NOT NULL DEFAULT 0,
		qty int(11) NOT NULL DEFAULT 1,
		unit_price_cents int(11) NOT NULL DEFAULT 0,
		committed int(11) NOT NULL DEFAULT 0,
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY cart_variant (cart_id, variant_id),
		KEY variant_id (variant_id)
	) $charset;";

	// ---- Orders.
	//
	// THREE status columns, not one — see sp_store_order_statuses() for why.
	//
	// Customer identity is DUAL, as on bookings: customer_id points at cc_customers when
	// Customer Connect is installed, and the name/email/phone snapshot always carries the
	// contact details, so a guest order on a Site-Pulse-only site is complete on its own
	// and a later account link never loses what was actually entered.
	//
	// provider_checkout is the idempotency guard — see sp_store_checkout_guard(). It is
	// NULL, never an empty string, for orders that never went through a checkout session.
	$sql .= "CREATE TABLE " . sp_store_table( 'orders' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		number varchar(20) NOT NULL,
		cart_id int(11) NOT NULL DEFAULT 0,
		customer_id int(11) NOT NULL DEFAULT 0,
		user_id int(11) NOT NULL DEFAULT 0,
		customer_name varchar(190) NOT NULL DEFAULT '',
		customer_email varchar(190) NOT NULL DEFAULT '',
		customer_phone varchar(30) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'pending',
		payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
		fulfillment_status varchar(20) NOT NULL DEFAULT 'unfulfilled',
		item_count int(11) NOT NULL DEFAULT 0,
		subtotal_cents int(11) NOT NULL DEFAULT 0,
		discount_cents int(11) NOT NULL DEFAULT 0,
		shipping_cents int(11) NOT NULL DEFAULT 0,
		tax_cents int(11) NOT NULL DEFAULT 0,
		total_cents int(11) NOT NULL DEFAULT 0,
		paid_cents int(11) NOT NULL DEFAULT 0,
		refunded_cents int(11) NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'USD',
		discount_code varchar(60) NOT NULL DEFAULT '',
		ship_name varchar(190) NOT NULL DEFAULT '',
		ship_address1 varchar(255) NOT NULL DEFAULT '',
		ship_address2 varchar(255) NOT NULL DEFAULT '',
		ship_city varchar(100) NOT NULL DEFAULT '',
		ship_state varchar(50) NOT NULL DEFAULT '',
		ship_zip varchar(20) NOT NULL DEFAULT '',
		ship_country char(2) NOT NULL DEFAULT 'US',
		bill_name varchar(190) NOT NULL DEFAULT '',
		bill_address1 varchar(255) NOT NULL DEFAULT '',
		bill_address2 varchar(255) NOT NULL DEFAULT '',
		bill_city varchar(100) NOT NULL DEFAULT '',
		bill_state varchar(50) NOT NULL DEFAULT '',
		bill_zip varchar(20) NOT NULL DEFAULT '',
		bill_country char(2) NOT NULL DEFAULT 'US',
		shipping_rate_id int(11) NOT NULL DEFAULT 0,
		shipping_method varchar(120) NOT NULL DEFAULT '',
		carrier varchar(60) NOT NULL DEFAULT '',
		tracking_number varchar(120) NOT NULL DEFAULT '',
		tracking_url varchar(255) NOT NULL DEFAULT '',
		shipped_at datetime DEFAULT NULL,
		delivered_at datetime DEFAULT NULL,
		provider varchar(20) NOT NULL DEFAULT 'stripe',
		provider_checkout varchar(100) DEFAULT NULL,
		provider_payment varchar(100) NOT NULL DEFAULT '',
		provider_customer varchar(100) NOT NULL DEFAULT '',
		source varchar(20) NOT NULL DEFAULT 'web',
		customer_note text DEFAULT NULL,
		staff_notes text DEFAULT NULL,
		placed_at datetime DEFAULT NULL,
		cancelled_at datetime DEFAULT NULL,
		cancelled_by int(11) NOT NULL DEFAULT 0,
		cancel_reason varchar(255) NOT NULL DEFAULT '',
		created_by int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY number (number),
		UNIQUE KEY provider_checkout (provider, provider_checkout),
		KEY status (status),
		KEY payment_status (payment_status),
		KEY fulfillment_status (fulfillment_status),
		KEY customer_email (customer_email),
		KEY customer_id (customer_id),
		KEY placed_at (placed_at),
		KEY created_at (created_at)
	) $charset;";

	// ---- Order lines.
	//
	// Everything the customer was shown is SNAPSHOTTED here — name, variant title, SKU,
	// unit price. Deleting or renaming a product two years later must not rewrite what
	// somebody once bought, and it must not turn an old order into a row of blanks.
	// variant_id and product_id are kept for reporting, not for display.
	//
	// qty_fulfilled and qty_refunded are per-line, which is what makes a partial shipment
	// or a single returned item expressible without inventing a second order.
	$sql .= "CREATE TABLE " . sp_store_table( 'order_items' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		order_id int(11) NOT NULL,
		variant_id int(11) NOT NULL DEFAULT 0,
		product_id bigint(20) unsigned NOT NULL DEFAULT 0,
		sku varchar(100) NOT NULL DEFAULT '',
		name varchar(255) NOT NULL DEFAULT '',
		variant_title varchar(255) NOT NULL DEFAULT '',
		qty int(11) NOT NULL DEFAULT 1,
		qty_fulfilled int(11) NOT NULL DEFAULT 0,
		qty_refunded int(11) NOT NULL DEFAULT 0,
		unit_price_cents int(11) NOT NULL DEFAULT 0,
		subtotal_cents int(11) NOT NULL DEFAULT 0,
		discount_cents int(11) NOT NULL DEFAULT 0,
		tax_cents int(11) NOT NULL DEFAULT 0,
		total_cents int(11) NOT NULL DEFAULT 0,
		taxable tinyint(1) NOT NULL DEFAULT 1,
		requires_shipping tinyint(1) NOT NULL DEFAULT 1,
		weight_grams int(11) NOT NULL DEFAULT 0,
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY order_id (order_id),
		KEY variant_id (variant_id),
		KEY product_id (product_id)
	) $charset;";

	// ---- Payments — one row per money movement, refunds as negative amounts.
	// UNIQUE (provider, provider_object) is the idempotency guard: a webhook delivered
	// twice (Stripe guarantees at-least-once) cannot double-record. Same shape as the
	// Booking module's payments table on purpose — one mental model for the money.
	$sql .= "CREATE TABLE " . sp_store_table( 'payments' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		order_id int(11) NOT NULL DEFAULT 0,
		customer_id int(11) NOT NULL DEFAULT 0,
		provider varchar(20) NOT NULL DEFAULT 'stripe',
		kind varchar(20) NOT NULL DEFAULT 'payment',
		amount_cents int(11) NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'USD',
		status varchar(20) NOT NULL DEFAULT 'pending',
		provider_object varchar(100) NOT NULL DEFAULT '',
		provider_customer varchar(100) NOT NULL DEFAULT '',
		description varchar(255) NOT NULL DEFAULT '',
		reason varchar(255) NOT NULL DEFAULT '',
		created_by int(11) NOT NULL DEFAULT 0,
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY provider_object (provider, provider_object),
		KEY order_id (order_id),
		KEY customer_id (customer_id),
		KEY status (status)
	) $charset;";

	// ---- Webhook events — the replay/idempotency log.
	// Stripe retries until it gets a 2xx, and can deliver out of order. Every event id is
	// recorded BEFORE processing; a duplicate id short-circuits. This table is also the
	// evidence for the fleet health check: a store whose last received_at is days old has
	// a broken webhook, which is the failure that otherwise goes unnoticed until a
	// customer says they paid and got nothing.
	$sql .= "CREATE TABLE " . sp_store_table( 'webhook_events' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		provider varchar(20) NOT NULL DEFAULT 'stripe',
		event_id varchar(100) NOT NULL,
		event_type varchar(80) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'received',
		attempts int(11) NOT NULL DEFAULT 0,
		error varchar(255) NOT NULL DEFAULT '',
		payload longtext DEFAULT NULL,
		received_at datetime NOT NULL,
		processed_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY event_id (provider, event_id),
		KEY status (status),
		KEY received_at (received_at)
	) $charset;";

	// ---- Outbound message queue — receipts, shipping notices, refund notices.
	// A QUEUE, not a send-at-the-time cron: this framework's cron is traffic-triggered, so
	// anything due is swept and sent on the next run, and a failed wp_mail can never fail
	// an order. UNIQUE (order, template, channel) means a re-run cannot send twice.
	$sql .= "CREATE TABLE " . sp_store_table( 'notifications' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		order_id int(11) NOT NULL DEFAULT 0,
		template_key varchar(60) NOT NULL,
		channel varchar(20) NOT NULL DEFAULT 'email',
		recipient varchar(190) NOT NULL DEFAULT '',
		send_at datetime NOT NULL,
		sent_at datetime DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'queued',
		attempts int(11) NOT NULL DEFAULT 0,
		error varchar(255) NOT NULL DEFAULT '',
		payload longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY order_template (order_id, template_key, channel),
		KEY due (status, send_at)
	) $charset;";

	// ---- Shipping rates — the client's own rate table.
	// Deliberately NOT live carrier rates. A rate table is a few rows the client
	// understands and can change; live rates are an API dependency, an account, and a
	// class of checkout failure that happens at the worst possible moment. When a client
	// genuinely needs real-time rates, that is a resolver added here, not a rewrite.
	$sql .= "CREATE TABLE " . sp_store_table( 'shipping_rates' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL DEFAULT '',
		description varchar(255) NOT NULL DEFAULT '',
		method varchar(20) NOT NULL DEFAULT 'flat',
		amount_cents int(11) NOT NULL DEFAULT 0,
		per_item_cents int(11) NOT NULL DEFAULT 0,
		min_subtotal_cents int(11) NOT NULL DEFAULT 0,
		max_subtotal_cents int(11) NOT NULL DEFAULT 0,
		min_weight_grams int(11) NOT NULL DEFAULT 0,
		max_weight_grams int(11) NOT NULL DEFAULT 0,
		countries varchar(255) NOT NULL DEFAULT '',
		states varchar(255) NOT NULL DEFAULT '',
		zips text DEFAULT NULL,
		delivery_estimate varchar(120) NOT NULL DEFAULT '',
		display_order int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY status (status)
	) $charset;";

	// ---- Inventory ledger — every movement of variants.stock, with its reason.
	// A stock level on its own can only tell you that it is wrong. This tells you when it
	// went wrong and what claimed to have done it, which is the difference between a
	// five-minute answer and an afternoon.
	$sql .= "CREATE TABLE " . sp_store_table( 'inventory_log' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		variant_id int(11) NOT NULL,
		order_id int(11) NOT NULL DEFAULT 0,
		delta int(11) NOT NULL DEFAULT 0,
		stock_after int(11) NOT NULL DEFAULT 0,
		reason varchar(20) NOT NULL DEFAULT 'manual',
		note varchar(255) NOT NULL DEFAULT '',
		user_id int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY variant_id (variant_id),
		KEY order_id (order_id),
		KEY created_at (created_at)
	) $charset;";

	dbDelta( $sql );

	// dbDelta is unreliable about adding an index to a table that already exists, and a
	// MISSING provider_checkout index means a redelivered Stripe webhook can create a
	// SECOND order for one payment — the one failure this module must never have.
	// Verify it landed, and add it if not. (Mirrors the slot_guard check in Booking.)
	$orders    = sp_store_table( 'orders' );
	$has_guard = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM information_schema.STATISTICS
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = 'provider_checkout'",
		$orders
	) );
	if ( ! $has_guard ) {
		$wpdb->query( "ALTER TABLE $orders ADD UNIQUE KEY provider_checkout (provider, provider_checkout)" );
	}

	update_option( 'site_pulse_store_db_version', SITE_PULSE_STORE_DB_VERSION );
}
