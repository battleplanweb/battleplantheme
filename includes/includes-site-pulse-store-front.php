<?php
/* Battle Plan Web Design — Site Pulse: Storefront

/*--------------------------------------------------------------
The customer-facing half of the store: the product grid shortcode, the single
product page, and the variant picker that drives both.

Everything here renders from the variants table, which is why a product grid
can show a real price range and a real in-stock state without a single
postmeta query — the thing Woo needs a lookup table and a cron job to fake.

THE ADD-TO-CART BUTTON IS SELF-GATING. It renders only once the cart API from
phase 3 exists (sp_store_cart_add). Until then a customer sees the product,
the price and the stock, and no button that would do nothing when pressed.
Staff see a note saying why. Nothing needs to be turned on when phase 3 lands.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Assets
# Product Card & Grid
# Single Product
# Templates
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Assets
--------------------------------------------------------------*/

/**
 * Storefront CSS/JS, loaded only on pages that actually show products: the
 * archive, a single product, or a page whose content uses the shortcode.
 * A merch shortcode on one page must not cost the other forty pages anything.
 */
function sp_store_enqueue_front(): void {
	static $done = false;
	if ( $done ) return;
	$done = true;

	// CSS is emitted by sp_store_inline_css() on wp_head, NOT through
	// bp_inline_minified_css(). See that function for why.
	$GLOBALS['sp_store_needs_css'] = true;

	if ( function_exists( 'bp_enqueue_script' ) ) {
		bp_enqueue_script( 'battleplan-script-store', 'script-store', [], [ 'strategy' => 'defer' ] );
	} else {
		wp_enqueue_script( 'battleplan-script-store', get_template_directory_uri() . '/js/script-store.js', [], null, true );
	}

	wp_localize_script( 'battleplan-script-store', 'BPStore', [
		'ajax' => admin_url( 'admin-ajax.php' ),
	] );
}

/**
 * The storefront assets load on EVERY front-end page while the store is on, not only
 * on product pages.
 *
 * That is a deliberate cost. The header cart count and the cart drawer have to work
 * wherever a customer happens to be when they open the cart, and both are drawn by
 * JavaScript because the pages are edge-cached. Loading the script only on product
 * pages would mean the cart silently does nothing everywhere else.
 *
 * It is ~2KB of script plus the inlined CSS. If that ever shows up in LCP on a site
 * with one shop page and forty others, the fix is to split the cart out of
 * script-store.js rather than to make this conditional and reintroduce the bug.
 */
/**
 * Emit the storefront CSS in <head>, AHEAD of the inlined site CSS.
 *
 * Deliberately not bp_inline_minified_css(). That helper buffers and flushes on
 * wp_footer priority 20 — the very bottom of the page — while style-site.css is
 * inlined in the head at wp_head priority 11. So anything loaded through it lands
 * AFTER the site's own CSS and wins the cascade at equal specificity, which makes a
 * client's colour override silently do nothing. (That is the framework-wide behaviour
 * for every component stylesheet, and it contradicts the stated intent of the site
 * inline, whose own comment says it is placed at 11 so that "site rules still win the
 * cascade over the deferred component styles". Worth fixing framework-side; fixed here
 * for the store because a shop a client cannot recolour is not shippable.)
 *
 * Priority 10 puts this before the site CSS at 11, so style-site.css overrides the
 * storefront with no !important needed. Being in the head also removes the flash of
 * unstyled product grid that a footer stylesheet guarantees.
 */
add_action( 'wp_head', 'sp_store_inline_css', 10 );
function sp_store_inline_css(): void {
	if ( is_admin() || empty( $GLOBALS['sp_store_needs_css'] ) ) return;

	// A screen that opts out of the site's branding doesn't want the storefront's
	// either — Site Pulse is the case, being staff tooling served from the front end.
	if ( ! apply_filters( 'bp_inline_site_css', true ) ) return;

	$path = get_template_directory() . '/style-store.css';
	if ( ! file_exists( $path ) ) return;

	$raw = (string) file_get_contents( $path );
	$css = ( defined( '_USER_LOGIN' ) && _USER_LOGIN === 'battleplanweb' ) || ! function_exists( 'bp_minify_css' )
		? $raw
		: bp_minify_css( $raw );

	// Colour overrides from Settings → Store, appended AFTER the stylesheet so they beat
	// its :root defaults (same specificity, so order decides). They are still inside
	// this priority-10 block, which keeps the intended order intact:
	// stylesheet defaults < Site Pulse colours < style-site.css.
	$css .= sp_store_color_overrides();

	echo '<style id="bp-store-inline">' . $css . '</style>' . "\n";
}

/** The :root block for whichever storefront colours have been set. Empty when none are. */
function sp_store_color_overrides(): string {
	if ( ! function_exists( 'sp_store_color_roles' ) ) return '';

	$vars = '';
	foreach ( sp_store_color_roles() as $var => $role ) {
		$value = sp_store_get_setting( 'color_' . $var, '' );
		// Re-validated on the way out, not trusted from storage: a hex that somehow got
		// into the database by another route must never reach a style attribute.
		if ( $value === '' || ! preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) continue;
		$vars .= '--' . $var . ':' . $value . ';';
	}

	return $vars === '' ? '' : ':root{' . $vars . '}';
}

add_action( 'wp_enqueue_scripts', 'sp_store_maybe_enqueue_front' );
function sp_store_maybe_enqueue_front(): void {
	if ( ! sp_store_active() ) return;
	sp_store_enqueue_front();
}


/*--------------------------------------------------------------
# Product Card & Grid
--------------------------------------------------------------*/

/** Is the cart layer from phase 3 loaded yet? Gates every buy control on the site. */
function sp_store_cart_ready(): bool {
	return function_exists( 'sp_store_cart_add' );
}

/** One product card. Used by the grid, the archive, and anything else listing products. */
function sp_store_product_card( int $product_id ): string {
	$range   = sp_store_price_range( $product_id );
	$link    = get_permalink( $product_id );
	$title   = get_the_title( $product_id );
	$variants = sp_store_get_variants( $product_id, true );

	// "Sold out" on a card means EVERY variant is unavailable. A shirt that has run out
	// of smalls is still a shirt you can buy.
	$any_in_stock = false;
	foreach ( $variants as $variant ) {
		if ( sp_store_in_stock( $variant ) ) { $any_in_stock = true; break; }
	}

	$out  = '<article class="store-card' . ( $variants && ! $any_in_stock ? ' store-card-soldout' : '' ) . '">';
	$out .= '<a class="store-card-link" href="' . esc_url( $link ) . '">';

	$out .= '<div class="store-card-media">';
	if ( has_post_thumbnail( $product_id ) ) {
		$out .= get_the_post_thumbnail( $product_id, 'medium_large', [ 'class' => 'store-card-img', 'loading' => 'lazy' ] );
	} else {
		$out .= '<div class="store-card-noimg" aria-hidden="true"></div>';
	}
	if ( $variants && ! $any_in_stock ) {
		$out .= '<span class="store-card-badge">Sold out</span>';
	}
	$out .= '</div>';

	// Title and price share a wrapper, as the image does. It gives the text block one
	// padding box instead of per-element side margins, and it lets the card be a flex
	// column — which is what pins the price to the bottom edge so a row of cards lines
	// up whether their titles run to one line or three.
	$out .= '<div class="store-card-body">';
	$out .= '<h3 class="store-card-title">' . esc_html( $title ) . '</h3>';

	if ( $range['count'] ) {
		$out .= '<p class="store-card-price">' . sp_store_price_range_label( $product_id ) . '</p>';
	}
	$out .= '</div>';

	$out .= '</a></article>';

	return $out;
}

/**
 * [store-products] — a grid of products.
 *
 * Attributes: category (slug), limit, columns, orderby, order, ids (comma list).
 * Deliberately few: a shortcode with thirty attributes is a template engine
 * nobody can remember, and this framework already has one of those.
 */
add_shortcode( 'store-products', 'sp_store_products_shortcode' );
function sp_store_products_shortcode( $atts ): string {
	if ( ! sp_store_active() ) return '';

	$atts = shortcode_atts( [
		'category' => '',
		'limit'    => 12,
		'columns'  => 3,
		'orderby'  => 'menu_order',
		'order'    => 'ASC',
		'ids'      => '',
	], $atts, 'store-products' );

	$query = [
		'post_type'           => SITE_PULSE_STORE_CPT,
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $atts['limit'],
		'orderby'             => sanitize_key( $atts['orderby'] ) ?: 'menu_order',
		'order'               => strtoupper( $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	];

	if ( $atts['orderby'] === 'menu_order' ) $query['orderby'] = 'menu_order title';

	if ( trim( (string) $atts['ids'] ) !== '' ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $atts['ids'] ) ) );
		if ( $ids ) {
			$query['post__in'] = $ids;
			$query['orderby']  = 'post__in';
		}
	}

	if ( trim( (string) $atts['category'] ) !== '' ) {
		$query['tax_query'] = [ [
			'taxonomy' => 'bp_product_cat',
			'field'    => 'slug',
			'terms'    => array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) $atts['category'] ) ) ),
		] ];
	}

	$products = new WP_Query( $query );
	if ( ! $products->have_posts() ) return '';

	sp_store_enqueue_front();

	$columns = max( 1, min( 6, (int) $atts['columns'] ) );
	$out     = '<div class="store-grid" style="--store-cols:' . $columns . '">';
	foreach ( $products->posts as $product ) {
		$out .= sp_store_product_card( (int) $product->ID );
	}
	$out .= '</div>';

	wp_reset_postdata();
	return $out;
}


/*--------------------------------------------------------------
# Single Product
--------------------------------------------------------------*/

/**
 * The variant data the picker needs, as a JS-ready array.
 *
 * Only what the page legitimately shows: price, availability, label, SKU. Cost
 * price and committed stock are business data and never reach the browser —
 * a detail Woo gets wrong by dumping whole objects into the page.
 */
function sp_store_variant_payload( int $product_id ): array {
	$payload = [];
	foreach ( sp_store_get_variants( $product_id, true ) as $variant ) {
		$available = sp_store_available( $variant );
		$payload[] = [
			'id'       => (int) $variant['id'],
			'options'  => [
				(string) $variant['option1'],
				(string) $variant['option2'],
				(string) $variant['option3'],
			],
			'label'    => sp_store_variant_label( $variant ),
			'sku'      => (string) $variant['sku'],
			// The option-value tags this variant matches, so the picker knows which
			// gallery thumbnails belong to it.
			'tags'     => sp_store_variant_tags( $variant ),
			// `price` is what is charged, `was` is the struck-through regular price shown
			// only while the variant is genuinely discounted.
			'price'    => sp_store_money( sp_store_variant_price( $variant ), (string) $variant['currency'] ),
			'was'      => sp_store_variant_on_sale( $variant )
				? sp_store_money( (int) $variant['price_cents'], (string) $variant['currency'] )
				: '',
			'inStock'  => sp_store_in_stock( $variant ),
			// null for an untracked variant, so the page can stay quiet about quantity
			// instead of announcing a number that means nothing.
			'left'     => ( $available !== null && $available <= 10 ) ? max( 0, $available ) : null,
		];
	}
	return $payload;
}

/**
 * The product gallery: one main image plus a thumbnail strip.
 *
 * EVERY gallery image is rendered, each carrying its tag in data-tag; the picker then
 * shows and hides thumbnails client-side as the selection changes. Rendering the whole
 * set once and filtering in the browser means switching colour is instant and needs no
 * request — and it means the images are all in the HTML for a crawler, rather than
 * appearing only after a script runs.
 *
 * The server still opens on the right image for $variant, so the page is correct
 * before any JS has executed. With no gallery set, this falls back to the featured
 * image exactly as before.
 */
function sp_store_gallery_html( int $product_id, array $variant ): string {
	$gallery = sp_store_gallery( $product_id );

	if ( ! $gallery ) {
		$out = '<div class="store-single-media">';
		$out .= has_post_thumbnail( $product_id )
			? get_the_post_thumbnail( $product_id, 'large', [ 'class' => 'store-single-img' ] )
			: '<div class="store-card-noimg" aria-hidden="true"></div>';
		return $out . '</div>';
	}

	// Which image the page opens on: the first one this variant actually matches.
	$initial = sp_store_variant_images( $product_id, $variant );
	$lead    = $initial ? (int) $initial[0]['id'] : (int) $gallery[0]['id'];

	// ONE set of markup for both layouts, because the server must not know whether this
	// is a phone: pages are edge-cached with no Vary on user agent, so anything
	// is_mobile() changed would be served to everyone. Every image is rendered as a
	// slide; CSS decides whether that is a desktop stack (one visible, thumbnails
	// below) or a phone carousel (all of them, swipeable). See style-store.css.
	$out  = '<div class="store-single-media" data-store-gallery>';
	$out .= '<div class="store-gallery-track" data-store-track>';

	foreach ( $gallery as $item ) {
		$src = wp_get_attachment_image_url( $item['id'], 'large' );
		if ( ! $src ) continue;

		// The zoom source is the FULL file, not the display size — the point of zooming
		// is to see detail the page is not already showing. Falls back to the display
		// image when there is no larger original.
		$zoom = wp_get_attachment_image_url( $item['id'], 'full' ) ?: $src;

		$out .= sprintf(
			'<div class="store-slide%s" data-tag="%s" data-id="%d">',
			(int) $item['id'] === $lead ? ' is-active' : '',
			esc_attr( $item['tag'] ),
			(int) $item['id']
		);
		$out .= wp_get_attachment_image( $item['id'], 'large', false, [
			'class'     => 'store-single-img',
			'data-zoom' => esc_url( $zoom ),
		] );
		$out .= '</div>';
	}

	$out .= '</div>';

	if ( count( $gallery ) > 1 ) {
		// Dots for the phone carousel, thumbnails for the desktop stack. Both are in the
		// DOM always; CSS shows whichever belongs.
		$out .= '<div class="store-gallery-dots" data-store-dots aria-hidden="true"></div>';

		$out .= '<div class="store-gallery-thumbs">';
		foreach ( $gallery as $item ) {
			$thumb = wp_get_attachment_image( $item['id'], 'thumbnail' );
			if ( ! $thumb ) continue;
			$out .= sprintf(
				'<button type="button" class="unique store-gallery-thumb%s" data-tag="%s" data-id="%d" aria-label="%s">%s</button>',
				(int) $item['id'] === $lead ? ' is-active' : '',
				esc_attr( $item['tag'] ),
				(int) $item['id'],
				esc_attr( trim( (string) get_post_meta( $item['id'], '_wp_attachment_image_alt', true ) ) ?: 'View image' ),
				$thumb
			);
		}
		$out .= '</div>';
	}

	return $out . '</div>';
}

/**
 * How a product's options are chosen: 'dropdown' or 'tiles'.
 *
 * A store-wide setting, because a shop where half the products use tiles and half use
 * selects reads as unfinished rather than as flexible. The filter is there for the one
 * product that genuinely needs the other treatment.
 */
function sp_store_picker_style( int $product_id = 0 ): string {
	$style = sp_store_get_setting( 'variant_picker', 'dropdown' ) === 'tiles' ? 'tiles' : 'dropdown';
	$style = (string) apply_filters( 'sp_store_picker_style', $style, $product_id );
	return $style === 'tiles' ? 'tiles' : 'dropdown';
}

/**
 * The gallery image tagged to one option value, if there is one.
 *
 * This is where the image tagging earns a second keep: a photo tagged "Color: Red"
 * already exists for the gallery, so a Red tile can show it without anyone uploading
 * or assigning anything twice.
 */
function sp_store_option_value_image( int $product_id, int $index, string $value ): int {
	$tag = ( $index + 1 ) . ':' . $value;
	foreach ( sp_store_gallery( $product_id ) as $item ) {
		if ( $item['tag'] === $tag ) return (int) $item['id'];
	}
	return 0;
}

/** The distinct values for one option position, in the order the variants define them. */
function sp_store_option_values( array $variants, int $index ): array {
	$values = [];
	foreach ( $variants as $variant ) {
		$value = trim( (string) ( $variant[ 'option' . ( $index + 1 ) ] ?? '' ) );
		if ( $value !== '' && ! in_array( $value, $values, true ) ) $values[] = $value;
	}
	return $values;
}

/**
 * Render the option pickers.
 *
 * Both styles carry the SAME data attributes (data-index on the group, data-value on
 * the choice), so the picker script reads a selection the same way whichever is on and
 * nothing downstream has to branch.
 */
function sp_store_option_pickers( int $product_id, array $names, array $variants ): string {
	$tiles = sp_store_picker_style( $product_id ) === 'tiles';

	$out = '<div class="store-options' . ( $tiles ? ' store-options-tiles' : '' ) . '">';

	foreach ( $names as $index => $name ) {
		$values = sp_store_option_values( $variants, $index );
		if ( ! $values ) continue;

		if ( ! $tiles ) {
			$id   = 'store-opt-' . $product_id . '-' . $index;
			$out .= '<p class="store-option">';
			$out .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</label>';
			$out .= '<select id="' . esc_attr( $id ) . '" class="store-option-select" data-index="' . (int) $index . '">';
			foreach ( $values as $value ) {
				$out .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $value ) . '</option>';
			}
			$out .= '</select></p>';
			continue;
		}

		$out .= '<div class="store-option-group" data-index="' . (int) $index . '">';
		$out .= '<span class="store-option-label">' . esc_html( $name ) . '</span>';
		$out .= '<div class="store-tiles" role="radiogroup" aria-label="' . esc_attr( $name ) . '">';

		foreach ( $values as $position => $value ) {
			$image = sp_store_option_value_image( $product_id, $index, $value );
			// Real buttons in a radiogroup, not divs: keyboard and screen-reader support
			// comes free, and class="unique" keeps style-forms.css from giving every one
			// of them the site's pill treatment.
			$out .= sprintf(
				'<button type="button" class="unique store-tile%s" role="radio" aria-checked="%s" data-index="%d" data-value="%s">',
				$position === 0 ? ' is-active' : '',
				$position === 0 ? 'true' : 'false',
				(int) $index,
				esc_attr( $value )
			);
			if ( $image ) {
				$out .= '<span class="store-tile-img">' . wp_get_attachment_image( $image, 'thumbnail', false, [ 'alt' => '' ] ) . '</span>';
			}
			$out .= '<span class="store-tile-label">' . esc_html( $value ) . '</span>';
			$out .= '</button>';
		}

		$out .= '</div></div>';
	}

	return $out . '</div>';
}

/**
 * The quantity + Add to Cart control, in whichever style the store is set to.
 *
 *   standard  a small quantity box with its label above it, the button beside it
 *   joined    quantity and button fused into one pill, with an optional second line
 *
 * Both render a control carrying `.store-qty` whose `.value` is the quantity — a
 * number input in one, a select in the other — so the picker script reads it the same
 * way and needs no branch.
 */
function sp_store_buy_control( int $product_id ): string {
	$joined = sp_store_get_setting( 'buy_style', 'standard' ) === 'joined';
	$label  = sp_store_get_setting( 'buy_label', 'Add to Cart' ) ?: 'Add to Cart';
	$note   = trim( (string) sp_store_get_setting( 'buy_note', '' ) );
	$qty_id = 'store-qty-' . $product_id;

	// class="unique" on every button here: style-forms.css styles EVERY bare <button>
	// with the site's pill and shadow, which fights both of these layouts.
	if ( ! $joined ) {
		$out  = '<div class="store-buy store-buy-standard">';
		$out .= '<span class="store-qty-wrap">';
		$out .= '<label class="store-qty-label" for="' . esc_attr( $qty_id ) . '">Qty</label>';
		$out .= '<input id="' . esc_attr( $qty_id ) . '" class="store-qty" type="number" value="1" min="1" step="1" inputmode="numeric">';
		$out .= '</span>';
		$out .= '<button type="button" class="unique store-add" data-store-add>' . esc_html( $label ) . '</button>';
		return $out . '</div>';
	}

	$out  = '<div class="store-buy store-buy-joined">';
	$out .= '<div class="store-buy-pill">';

	// A select rather than a number box: it is narrower, it cannot be typed into
	// nonsensically, and a stepper looks wrong fused against a button. Ten is plenty —
	// the server clamps to real stock anyway, so this is about the control, not the limit.
	// Wrapped so the caret can be drawn as a pseudo-element on the wrapper. A native
	// select's arrow sits wherever the browser puts it — usually hard against the right
	// edge, leaving a gap between it and the number — and no amount of padding moves it.
	$out .= '<span class="store-qty-pick">';
	$out .= '<select id="' . esc_attr( $qty_id ) . '" class="store-qty" aria-label="Quantity">';
	for ( $n = 1; $n <= 10; $n++ ) {
		$out .= '<option value="' . $n . '">' . $n . '</option>';
	}
	$out .= '</select>';
	$out .= '</span>';

	$out .= '<button type="button" class="unique store-add" data-store-add>';
	$out .= '<span class="store-add-label">' . esc_html( $label ) . '</span>';
	if ( $note !== '' ) $out .= '<span class="store-add-note">' . esc_html( $note ) . '</span>';
	$out .= '</button>';

	return $out . '</div></div>';
}

/** The whole single-product block: gallery, price, picker, buy control. */
function sp_store_single_product( int $product_id ): string {
	$variants = sp_store_get_variants( $product_id, true );
	if ( ! $variants ) {
		return '<p class="store-empty">This product is not available.</p>';
	}

	$names   = sp_store_option_names( $product_id );
	$payload = sp_store_variant_payload( $product_id );
	$first   = $variants[0];

	$out = '<div class="store-single" data-product="' . (int) $product_id . '">';

	// ---- Gallery
	$out .= sp_store_gallery_html( $product_id, $first );

	// ---- Detail
	$out .= '<div class="store-single-detail">';
	$out .= '<h1 class="store-single-title">' . esc_html( get_the_title( $product_id ) ) . '</h1>';

	// Rendered server-side for the first variant, not left to JS, so the right price is
	// on the page even if the script never runs.
	$was = sp_store_variant_on_sale( $first ) ? sp_store_money( (int) $first['price_cents'], (string) $first['currency'] ) : '';
	$out .= '<p class="store-single-price' . ( $was !== '' ? ' is-sale' : '' ) . '" data-store-price>'
		. sp_store_money( sp_store_variant_price( $first ), (string) $first['currency'] ) . '</p>';
	$out .= '<p class="store-single-was" data-store-was' . ( $was === '' ? ' hidden' : '' ) . '>' . $was . '</p>';

	// ---- Option pickers, in whichever style the store is set to
	if ( $names ) {
		$out .= sp_store_option_pickers( $product_id, $names, $variants );
	}

	$out .= '<p class="store-stock" data-store-stock></p>';

	// ---- Buy control, self-gating on the phase 3 cart API.
	if ( sp_store_cart_ready() ) {
		$out .= sp_store_buy_control( $product_id );
	} elseif ( current_user_can( 'edit_posts' ) ) {
		$out .= '<p class="store-notice">Checkout is not wired up on this site yet, so the Add to Cart button is hidden from customers. Only logged-in staff see this message.</p>';
	}

	// The short description beside the buy button — only when the client actually WROTE
	// one. Read the raw post_excerpt field, never get_the_excerpt(): that manufactures an
	// excerpt by truncating the content when none is set, so on every product without a
	// hand-written one the full description would print below and a chopped copy of the
	// same text would print here. Which is exactly what it did.
	$excerpt = trim( (string) get_post_field( 'post_excerpt', $product_id ) );
	if ( $excerpt !== '' ) {
		$out .= '<div class="store-single-excerpt">' . wpautop( esc_html( $excerpt ) ) . '</div>';
	}

	$out .= '</div></div>';

	// The picker's data. wp_json_encode escapes for safe embedding in a script block.
	$out .= '<script type="application/json" id="store-variants-' . (int) $product_id . '">'
		. wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. '</script>';

	return $out;
}

/** [store-product id="123"] — drop one product's buy block into any page. */
add_shortcode( 'store-product', 'sp_store_product_shortcode' );
function sp_store_product_shortcode( $atts ): string {
	if ( ! sp_store_active() ) return '';

	$atts       = shortcode_atts( [ 'id' => 0 ], $atts, 'store-product' );
	$product_id = (int) $atts['id'];
	if ( ! $product_id || get_post_type( $product_id ) !== SITE_PULSE_STORE_CPT ) return '';

	sp_store_enqueue_front();
	return sp_store_single_product( $product_id );
}


/*--------------------------------------------------------------
# Templates
--------------------------------------------------------------*/

/**
 * Serve the store's own templates from the parent theme.
 *
 * WordPress already looks for single-bp_product.php and archive-bp_product.php in
 * the theme, so this only matters for a CHILD theme: without it, a child theme
 * that has neither file falls back to its own single.php and the product renders
 * as a blog post. A child theme that DOES define one still wins, because
 * locate_template() is checked first.
 */
add_filter( 'template_include', 'sp_store_template_include', 20 );
function sp_store_template_include( $template ) {
	if ( ! sp_store_active() ) return $template;

	$wanted = '';
	if ( is_singular( SITE_PULSE_STORE_CPT ) ) {
		$wanted = 'single-' . SITE_PULSE_STORE_CPT . '.php';
	} elseif ( is_post_type_archive( SITE_PULSE_STORE_CPT ) || is_tax( 'bp_product_cat' ) ) {
		$wanted = 'archive-' . SITE_PULSE_STORE_CPT . '.php';
	}
	if ( $wanted === '' ) return $template;

	$child = locate_template( [ $wanted ] );
	if ( $child ) return $child;

	$parent = get_template_directory() . '/' . $wanted;
	return file_exists( $parent ) ? $parent : $template;
}
