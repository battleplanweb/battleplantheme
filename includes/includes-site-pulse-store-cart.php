<?php
/* Battle Plan Web Design — Site Pulse: Store Cart

/*--------------------------------------------------------------
The cart: server-side, keyed by an opaque token in a cookie.

WHY SERVER-SIDE. A cart that lives in the browser cannot be looked at when a
customer phones to ask what happened to their order, cannot survive them moving
from their phone to their laptop, and cannot be turned into an abandoned-cart
figure worth knowing. The cookie holds nothing but the token.

WHEN STOCK IS RESERVED — read this before changing it. Adding to a cart
VALIDATES availability but does NOT reserve it. The reservation happens at
checkout, atomically, in sp_store_commit_stock(). Reserving on add sounds more
generous and is a trap: one abandoned cart locks inventory for the whole hold
window, a crawler that follows Add to Cart links can zero out a shop in a
minute, and releasing it all again depends on a sweeper that this framework's
traffic-triggered cron cannot promise to run on time. Woo and Shopify both
reserve at checkout, for exactly these reasons.

The cost of that choice is honest and small: two people can hold the last shirt
in their carts, and the second one to reach checkout is told it has gone. That
is a far better failure than a shop that says sold out because a bot went
shopping last Tuesday.

NO NONCES ON THE CART ENDPOINTS, DELIBERATELY. Pages here are edge-cached, so a
nonce printed into the HTML is stale for everyone who gets a cached copy — the
same trap as is_mobile(). The cart token IS the credential: unguessable, and
carried in a SameSite=Lax cookie that a cross-site POST cannot send. Adding a
shirt to your own cart is also not an action worth forging.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Cookie & Resolution
# Cart Operations
# Lines, Totals & Payload
# Sweeper
# Endpoints
# Storefront Markup
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Cookie & Resolution
--------------------------------------------------------------*/

/**
 * The cart cookie's name.
 *
 * Deliberately NOT one of the names a host's edge cache treats as "logged in"
 * (wordpress_logged_in_*, woocommerce_items_in_cart, comment_author_*). This cookie
 * must never punch a hole in page caching — which is also why nothing server-rendered
 * ever reads it to draw the cart count. See sp_store_cart_count_shortcode().
 */
function sp_store_cart_cookie_name(): string {
	return 'bp_store_cart';
}

/** How long an untouched cart survives. Filterable; two weeks by default. */
function sp_store_cart_lifetime(): int {
	return max( HOUR_IN_SECONDS, (int) apply_filters( 'sp_store_cart_lifetime', 14 * DAY_IN_SECONDS ) );
}

/** The token this browser is carrying, or ''. */
function sp_store_cart_token(): string {
	$raw = (string) ( $_COOKIE[ sp_store_cart_cookie_name() ] ?? '' );
	return preg_match( '/^[a-f0-9]{48}$/', $raw ) ? $raw : '';
}

/**
 * Set (or refresh) the cart cookie.
 *
 * SameSite=Lax is the CSRF defence for every endpoint in this file — a form on
 * another origin cannot cause the browser to send this cookie, so a forged POST
 * arrives with no cart and does nothing.
 */
function sp_store_cart_set_cookie( string $token ): void {
	if ( headers_sent() ) return;
	setcookie( sp_store_cart_cookie_name(), $token, [
		'expires'  => time() + sp_store_cart_lifetime(),
		'path'     => '/',
		'domain'   => COOKIE_DOMAIN ?: '',
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	] );
	$_COOKIE[ sp_store_cart_cookie_name() ] = $token;
}

function sp_store_cart_clear_cookie(): void {
	if ( headers_sent() ) return;
	setcookie( sp_store_cart_cookie_name(), '', [
		'expires' => time() - 3600, 'path' => '/', 'domain' => COOKIE_DOMAIN ?: '',
		'secure'  => is_ssl(), 'httponly' => true, 'samesite' => 'Lax',
	] );
	unset( $_COOKIE[ sp_store_cart_cookie_name() ] );
}

/**
 * This browser's open cart.
 *
 * $create is false almost everywhere ON PURPOSE. A cart row (and its cookie) is
 * created only when somebody actually puts something in it — never on a plain page
 * view. Otherwise every crawler that touches the site would mint a cart, and every
 * visitor would be carrying a cookie that some edge configurations treat as a reason
 * to skip the cache.
 */
function sp_store_current_cart( bool $create = false ): ?array {
	global $wpdb;

	$token = sp_store_cart_token();
	if ( $token !== '' ) {
		$cart = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . sp_store_table( 'carts' ) . " WHERE token = %s AND status = 'open'",
			$token
		), ARRAY_A );
		if ( $cart ) return $cart;
	}

	if ( ! $create ) return null;

	$token = sp_store_generate_cart_token();
	$now   = current_time( 'mysql', true );

	$wpdb->insert( sp_store_table( 'carts' ), [
		'token'      => $token,
		'user_id'    => get_current_user_id(),
		'currency'   => sp_store_currency(),
		'status'     => 'open',
		'ip'         => substr( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), 0, 45 ),
		'expires_at' => gmdate( 'Y-m-d H:i:s', time() + sp_store_cart_lifetime() ),
		'created_at' => $now,
		'updated_at' => $now,
	] );
	if ( ! $wpdb->insert_id ) return null;

	sp_store_cart_set_cookie( $token );

	return $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'carts' ) . " WHERE id = %d",
		(int) $wpdb->insert_id
	), ARRAY_A );
}

/** Push the expiry out. Any activity keeps a cart alive. */
function sp_store_cart_touch( int $cart_id ): void {
	global $wpdb;
	$wpdb->update( sp_store_table( 'carts' ), [
		'expires_at' => gmdate( 'Y-m-d H:i:s', time() + sp_store_cart_lifetime() ),
		'updated_at' => current_time( 'mysql', true ),
	], [ 'id' => $cart_id ] );
}


/*--------------------------------------------------------------
# Cart Operations
--------------------------------------------------------------*/

/**
 * How many of a variant a customer may still put in their cart.
 *
 * Availability minus what is already in THIS cart, so the second "add" of the last
 * shirt is refused rather than silently making a line of two that cannot be bought.
 * An untracked variant returns null, meaning no limit.
 */
function sp_store_cart_room( array $variant, int $cart_id, int $exclude_line_qty = 0 ): ?int {
	if ( empty( $variant['track_stock'] ) || ! empty( $variant['allow_backorder'] ) ) return null;

	global $wpdb;
	$in_cart = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT qty FROM " . sp_store_table( 'cart_items' ) . " WHERE cart_id = %d AND variant_id = %d",
		$cart_id, (int) $variant['id']
	) );

	$available = (int) sp_store_available( $variant );
	return max( 0, $available - max( 0, $in_cart - $exclude_line_qty ) );
}

/**
 * Add to the cart. THE function the storefront gates its buy button on — see
 * sp_store_cart_ready(); the Add to Cart control appears the moment this exists.
 *
 * The cart line's UNIQUE (cart_id, variant_id) means adding the same variant twice
 * raises the quantity instead of making a second line, so the database enforces what
 * would otherwise be an easily-forgotten branch here.
 */
function sp_store_cart_add( int $variant_id, int $qty = 1 ) {
	$qty = max( 1, $qty );

	$variant = sp_store_get_variant( $variant_id );
	if ( ! $variant || ( $variant['status'] ?? '' ) !== 'active' ) {
		return new WP_Error( 'sp_store_no_variant', 'That item is no longer available.' );
	}
	if ( get_post_status( (int) $variant['product_id'] ) !== 'publish' ) {
		return new WP_Error( 'sp_store_unpublished', 'That item is no longer available.' );
	}
	if ( ! sp_store_in_stock( $variant ) ) {
		return new WP_Error( 'sp_store_out_of_stock', 'That item is sold out.' );
	}

	$cart = sp_store_current_cart( true );
	if ( ! $cart ) return new WP_Error( 'sp_store_no_cart', 'Could not start a cart.' );

	$room = sp_store_cart_room( $variant, (int) $cart['id'] );
	if ( $room !== null && $qty > $room ) {
		if ( $room <= 0 ) {
			return new WP_Error( 'sp_store_all_in_cart', 'You already have all the stock we have in your cart.' );
		}
		$qty = $room;
	}

	global $wpdb;
	$now = current_time( 'mysql', true );

	// INSERT ... ON DUPLICATE KEY UPDATE, so a double-click cannot make two lines. The
	// unique index does the deciding, not a read-then-branch that can lose a race.
	$table = sp_store_table( 'cart_items' );
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO $table (cart_id, variant_id, product_id, qty, unit_price_cents, created_at, updated_at)
		 VALUES (%d, %d, %d, %d, %d, %s, %s)
		 ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty), updated_at = VALUES(updated_at)",
		(int) $cart['id'], $variant_id, (int) $variant['product_id'], $qty,
		sp_store_variant_price( $variant ), $now, $now
	) );

	// Clamp, in case the add raced another tab past the limit.
	if ( $room !== null ) {
		$wpdb->query( $wpdb->prepare(
			"UPDATE $table SET qty = LEAST(qty, %d) WHERE cart_id = %d AND variant_id = %d",
			(int) sp_store_available( $variant ), (int) $cart['id'], $variant_id
		) );
	}

	sp_store_cart_touch( (int) $cart['id'] );
	return sp_store_cart_recalc( (int) $cart['id'] );
}

/** Set a line's quantity. Zero removes it. */
function sp_store_cart_set_qty( int $variant_id, int $qty ) {
	$cart = sp_store_current_cart();
	if ( ! $cart ) return new WP_Error( 'sp_store_no_cart', 'Your cart has expired.' );

	if ( $qty <= 0 ) return sp_store_cart_remove( $variant_id );

	$variant = sp_store_get_variant( $variant_id );
	if ( ! $variant ) return sp_store_cart_remove( $variant_id );

	$available = sp_store_available( $variant );
	if ( $available !== null && empty( $variant['allow_backorder'] ) ) {
		$qty = min( $qty, max( 0, $available ) );
		if ( $qty <= 0 ) return sp_store_cart_remove( $variant_id );
	}

	global $wpdb;
	$wpdb->update(
		sp_store_table( 'cart_items' ),
		[ 'qty' => $qty, 'updated_at' => current_time( 'mysql', true ) ],
		[ 'cart_id' => (int) $cart['id'], 'variant_id' => $variant_id ]
	);

	sp_store_cart_touch( (int) $cart['id'] );
	return sp_store_cart_recalc( (int) $cart['id'] );
}

function sp_store_cart_remove( int $variant_id ) {
	$cart = sp_store_current_cart();
	if ( ! $cart ) return new WP_Error( 'sp_store_no_cart', 'Your cart has expired.' );

	global $wpdb;
	$wpdb->delete( sp_store_table( 'cart_items' ), [
		'cart_id'    => (int) $cart['id'],
		'variant_id' => $variant_id,
	] );

	sp_store_cart_touch( (int) $cart['id'] );
	return sp_store_cart_recalc( (int) $cart['id'] );
}


/*--------------------------------------------------------------
# Lines, Totals & Payload
--------------------------------------------------------------*/

/**
 * A title as plain text, for JSON payloads.
 *
 * Anything travelling to the browser as DATA must carry real characters, never HTML
 * entities: whatever renders it will escape it once, and an entity that arrives
 * pre-encoded gets escaped a second time and shows its own source.
 */
function sp_store_plain_text( string $value ): string {
	return html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * The cart's lines, joined to their variant and product.
 *
 * A line whose variant or product has since gone away is DROPPED from the cart here
 * rather than rendered as a blank row — an unbuyable line that sits in the cart until
 * checkout fails is the sort of quiet breakage this module exists to avoid.
 */
function sp_store_cart_lines( int $cart_id ): array {
	global $wpdb;

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ci.*, v.sku, v.option1, v.option2, v.option3, v.status AS variant_status,
		        v.price_cents, v.sale_price_cents, v.track_stock, v.allow_backorder,
		        v.stock, v.stock_committed, v.currency
		   FROM " . sp_store_table( 'cart_items' ) . " ci
		   JOIN " . sp_store_table( 'variants' ) . " v ON v.id = ci.variant_id
		  WHERE ci.cart_id = %d
		  ORDER BY ci.id ASC",
		$cart_id
	), ARRAY_A ) ?: [];

	$lines = [];
	$stale = [];

	foreach ( $rows as $row ) {
		$product_id = (int) $row['product_id'];

		if ( $row['variant_status'] !== 'active' || get_post_status( $product_id ) !== 'publish' ) {
			$stale[] = (int) $row['variant_id'];
			continue;
		}

		// Priced live, not from the snapshot: a price that changed since the item was
		// added must be shown before checkout, not discovered on the receipt.
		$unit      = sp_store_variant_price( $row );
		$qty       = max( 1, (int) $row['qty'] );
		$available = sp_store_available( $row );

		$lines[] = [
			'variant_id' => (int) $row['variant_id'],
			'product_id' => $product_id,
			// Entities DECODED, because this is JSON: data, not HTML. wptexturize turns
			// a straight apostrophe into the entity &#8217; inside get_the_title(), and
			// the browser then escapes the & again when it inserts the text safely --
			// so the shopper reads "Babe&#8217;s". Sending the real character means one
			// escape happens, in the one place that should do it.
			'title'      => sp_store_plain_text( get_the_title( $product_id ) ),
			'variant'    => sp_store_plain_text( sp_store_variant_label( $row ) ),
			'sku'        => (string) $row['sku'],
			'url'        => (string) get_permalink( $product_id ),
			'image'      => (string) ( get_the_post_thumbnail_url( $product_id, 'thumbnail' ) ?: '' ),
			'qty'        => $qty,
			'max'        => ( $available !== null && empty( $row['allow_backorder'] ) ) ? max( 0, $available ) : null,
			'unit_cents' => $unit,
			'unit'       => sp_store_money( $unit, (string) $row['currency'] ),
			'line_cents' => $unit * $qty,
			'line'       => sp_store_money( $unit * $qty, (string) $row['currency'] ),
		];
	}

	if ( $stale ) {
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM " . sp_store_table( 'cart_items' ) . "
			  WHERE cart_id = %d AND variant_id IN (" . implode( ',', array_fill( 0, count( $stale ), '%d' ) ) . ")",
			array_merge( [ $cart_id ], $stale )
		) );
	}

	return $lines;
}

/** Recompute and store the cart's cached count/subtotal, and return the payload. */
function sp_store_cart_recalc( int $cart_id ): array {
	global $wpdb;

	$lines    = sp_store_cart_lines( $cart_id );
	$count    = 0;
	$subtotal = 0;
	foreach ( $lines as $line ) {
		$count    += $line['qty'];
		$subtotal += $line['line_cents'];
	}

	$wpdb->update( sp_store_table( 'carts' ), [
		'item_count'     => $count,
		'subtotal_cents' => $subtotal,
		'updated_at'     => current_time( 'mysql', true ),
	], [ 'id' => $cart_id ] );

	return [
		'count'          => $count,
		'subtotal_cents' => $subtotal,
		'subtotal'       => sp_store_money( $subtotal ),
		'items'          => $lines,
		// Empty when no payment processor is configured, which is what disables the
		// drawer's checkout button.
		'checkout'       => function_exists( 'sp_store_checkout_url' ) ? sp_store_checkout_url() : '',
		'checkout_note'  => sp_store_checkout_blocked_note(),
	];
}

/**
 * Why checkout is unavailable — for STAFF only.
 *
 * A customer gets a quietly disabled button; there is nothing useful they can do
 * with the reason. Someone who can edit the site gets told exactly what is
 * missing, because otherwise the only symptom is a greyed-out button with no
 * hint that a key is all it needs.
 */
function sp_store_checkout_blocked_note(): string {
	if ( sp_store_checkout_url() !== '' ) return '';
	if ( ! current_user_can( 'edit_posts' ) ) return '';

	$label = function_exists( 'sp_store_provider_def' )
		? ( sp_store_provider_def()['label'] ?? 'your payment processor' )
		: 'your payment processor';

	return sprintf(
		'Staff only: no %s key is set, so customers cannot check out. Add one under Site Pulse → Settings → API Keys.',
		$label
	);
}

/** The payload for a browser with no cart yet — an empty cart, not an error. */
function sp_store_cart_payload(): array {
	$cart = sp_store_current_cart();
	if ( ! $cart ) {
		return [
			'count' => 0, 'subtotal_cents' => 0, 'subtotal' => sp_store_money( 0 ),
			'items' => [],
			'checkout' => function_exists( 'sp_store_checkout_url' ) ? sp_store_checkout_url() : '',
			'checkout_note' => sp_store_checkout_blocked_note(),
		];
	}
	return sp_store_cart_recalc( (int) $cart['id'] );
}


/*--------------------------------------------------------------
# Sweeper
--------------------------------------------------------------*/

/**
 * Delete carts nobody came back to.
 *
 * Housekeeping only — no stock is released, because adding to a cart never reserved
 * any (see the file header). That is precisely what lets this be lazy: the framework's
 * cron is traffic-triggered and cannot promise a firing minute, and nothing here
 * breaks if it runs late or not at all.
 *
 * Throttled by a transient so a busy site sweeps at most once every fifteen minutes,
 * and capped per run so it can never turn one unlucky page view into a slow request.
 */
function sp_store_cart_sweep(): void {
	if ( get_transient( 'sp_store_cart_swept' ) ) return;
	set_transient( 'sp_store_cart_swept', 1, 15 * MINUTE_IN_SECONDS );

	global $wpdb;
	$now = current_time( 'mysql', true );

	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT id FROM " . sp_store_table( 'carts' ) . "
		  WHERE status = 'open' AND expires_at IS NOT NULL AND expires_at < %s
		  LIMIT 200",
		$now
	) ) ?: [];
	if ( ! $ids ) return;

	$in = implode( ',', array_map( 'intval', $ids ) );
	$wpdb->query( "DELETE FROM " . sp_store_table( 'cart_items' ) . " WHERE cart_id IN ($in)" );
	$wpdb->query( "UPDATE " . sp_store_table( 'carts' ) . " SET status = 'expired' WHERE id IN ($in)" );
}

add_action( 'init', 'sp_store_cart_maybe_sweep', 20 );
function sp_store_cart_maybe_sweep(): void {
	if ( ! sp_store_active() || is_admin() ) return;
	sp_store_cart_sweep();
}


/*--------------------------------------------------------------
# Endpoints
--------------------------------------------------------------*/

/**
 * All four cart endpoints, for logged-in and logged-out visitors alike.
 *
 * No check_ajax_referer(): see the file header. The cart token in a SameSite=Lax
 * cookie is the credential, and a nonce baked into an edge-cached page would be stale
 * for most of the people who received it.
 */
function sp_store_cart_routes(): array {
	return [
		'sp_store_cart_get'    => 'sp_store_ajax_cart_get',
		'sp_store_cart_add'    => 'sp_store_ajax_cart_add',
		'sp_store_cart_qty'    => 'sp_store_ajax_cart_qty',
		'sp_store_cart_remove' => 'sp_store_ajax_cart_remove',
	];
}
foreach ( sp_store_cart_routes() as $sp_store_action => $sp_store_handler ) {
	add_action( 'wp_ajax_' . $sp_store_action, $sp_store_handler );
	add_action( 'wp_ajax_nopriv_' . $sp_store_action, $sp_store_handler );
}

/** Shared guard + reply, so one endpoint cannot quietly answer differently from another. */
function sp_store_cart_reply( $result ): void {
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [
			'message' => $result->get_error_message(),
			'cart'    => sp_store_cart_payload(),
		] );
	}
	wp_send_json_success( [ 'cart' => $result ] );
}

function sp_store_cart_guard(): bool {
	if ( sp_store_active() ) return true;
	wp_send_json_error( [ 'message' => 'The store is not available.' ] );
	return false;
}

function sp_store_ajax_cart_get(): void {
	if ( ! sp_store_cart_guard() ) return;
	wp_send_json_success( [ 'cart' => sp_store_cart_payload() ] );
}

function sp_store_ajax_cart_add(): void {
	if ( ! sp_store_cart_guard() ) return;
	sp_store_cart_reply( sp_store_cart_add(
		(int) ( $_POST['variant_id'] ?? 0 ),
		(int) ( $_POST['qty'] ?? 1 )
	) );
}

function sp_store_ajax_cart_qty(): void {
	if ( ! sp_store_cart_guard() ) return;
	sp_store_cart_reply( sp_store_cart_set_qty(
		(int) ( $_POST['variant_id'] ?? 0 ),
		(int) ( $_POST['qty'] ?? 0 )
	) );
}

function sp_store_ajax_cart_remove(): void {
	if ( ! sp_store_cart_guard() ) return;
	sp_store_cart_reply( sp_store_cart_remove( (int) ( $_POST['variant_id'] ?? 0 ) ) );
}


/*--------------------------------------------------------------
# Storefront Markup
--------------------------------------------------------------*/

/**
 * [store-cart-count] — the header cart link.
 *
 * Renders EMPTY and is filled by JavaScript, always. The page it sits in is
 * edge-cached and shared between visitors, so a server-rendered count would show one
 * shopper's cart to everybody else. Same rule as is_mobile(): nothing that varies per
 * visitor may be baked into the HTML.
 */
add_shortcode( 'store-cart-count', 'sp_store_cart_count_shortcode' );
function sp_store_cart_count_shortcode( $atts ): string {
	if ( ! sp_store_active() ) return '';

	$atts  = shortcode_atts( [ 'label' => 'Cart' ], $atts, 'store-cart-count' );
	$icon  = function_exists( 'do_shortcode' ) ? do_shortcode( '[get-icon type="cart"]' ) : '';

	return '<button type="button" class="unique store-cart-link" data-store-cart-open aria-label="'
		. esc_attr( $atts['label'] ) . '">'
		. $icon
		. '<span class="store-cart-count" data-store-count hidden>0</span>'
		. '</button>';
}

/**
 * The cart drawer, printed once in the footer of every page.
 *
 * Structure only — every line is drawn by JS from the cart payload, for the same
 * caching reason as the count above.
 */
add_action( 'wp_footer', 'sp_store_cart_drawer' );
function sp_store_cart_drawer(): void {
	if ( ! sp_store_active() || is_admin() ) return;

	?>
	<div class="store-drawer" data-store-drawer hidden aria-hidden="true">
		<div class="store-drawer-scrim" data-store-cart-close></div>
		<aside class="store-drawer-panel" role="dialog" aria-modal="true" aria-label="Shopping cart">
			<header class="store-drawer-head">
				<h2>Your Cart</h2>
				<button type="button" class="unique store-drawer-close" data-store-cart-close aria-label="Close cart">&times;</button>
			</header>
			<div class="store-drawer-body" data-store-cart-body></div>
			<footer class="store-drawer-foot" data-store-cart-foot hidden>
				<p class="store-drawer-subtotal"><span>Subtotal</span> <strong data-store-cart-subtotal></strong></p>
				<p class="store-drawer-note">Shipping and tax are worked out at checkout.</p>
				<a class="store-drawer-checkout" data-store-cart-checkout href="#">Checkout</a>
				<p class="store-drawer-staff" data-store-cart-note hidden></p>
			</footer>
		</aside>
	</div>
	<?php
}

/**
 * Remember the shopper's email on the cart.
 *
 * Kept on the CART, not just the order, so an abandoned checkout still has
 * somewhere to send a reminder from and a returning shopper does not retype it.
 */
function sp_store_set_cart_email( int $cart_id, string $email ): void {
	if ( ! is_email( $email ) ) return;

	global $wpdb;
	$wpdb->update(
		sp_store_table( 'carts' ),
		[ 'email' => $email, 'updated_at' => current_time( 'mysql', true ) ],
		[ 'id' => $cart_id ]
	);
}
