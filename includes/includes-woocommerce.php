<?php
/* Battle Plan Web Design Woocommerce Includes

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Functions to extend WordPress
# Import Advanced Custom Fields
# Basic Theme Set Up

--------------------------------------------------------------*/

if ( ! defined( '_BP_SET_ALT_TEXT' ) ) { define( '_BP_SET_ALT_TEXT', 'false' ); }

/*--------------------------------------------------------------
# Functions to extend WordPress
--------------------------------------------------------------*/

// Display number of items in the cart
add_shortcode( 'get-cart', 'battleplan_getCartNum' );
function battleplan_getCartNum($atts, $content = null ) {
	if ( !is_admin() && !is_wplogin() ) :
		global $woocommerce; $cartQty = $woocommerce->cart->get_cart_contents_count();
		$printCart = "[get-icon type='cart' link='/cart/' sr='shopping cart'" . ($cartQty > 0 ? " after='".$cartQty."']" : "]"); 
		return do_shortcode($printCart);
	endif;
}
/*
add_filter('woocommerce_product_add_to_cart_text', function($text, $product){
	return do_shortcode('[get-icon type="cart"]&nbsp;&nbsp;'. $text);
}, 10, 2);

add_filter('woocommerce_product_single_add_to_cart_text', function($text){
	return do_shortcode('[get-icon type="cart"]&nbsp;&nbsp;'. $text);
}, 10, 1);
*/

add_filter('woocommerce_add_to_cart_fragments', function($frags){
	$qty = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	$html = '<a class="header-cart-link" href="'.esc_url(wc_get_cart_url()).'">'
		.'[get-icon type="cart"]'.($qty>0 ? '&nbsp;&nbsp;'.$qty : '')
		.'</a>';
	$frags['a.header-cart-link'] = $html;
	return do_shortcode($frags);
});


// Change labels on woocommerce 'product' post type
function battleplan_woo_labels($single, $plural){
   $arr = array(
      'name' => $plural,
      'singular_name' => $single,
      'menu_name' => $plural,
      'add_new' => 'Add '.$single,
      'add_new_item' => 'Add New '.$single,
      'edit' => 'Edit',
      'edit_item' => 'Edit '.$single,
      'new_item' => 'New '.$single,
      'view' => 'View '.$plural,
      'view_item' => 'View '.$single,
      'search_items' => 'Search '.$plural,
      'not_found' => 'No '.$plural.' Found',
      'not_found_in_trash' => 'No '.$plural.' Found in Trash',
      'parent' => 'Parent '.$single
   );
   return $arr;
}

/*--------------------------------------------------------------
# Basic Theme Set Up
--------------------------------------------------------------*/

// Declare support for Woocommerce
add_theme_support( 'woocommerce' );

add_action( 'enqueue_block_assets', 'battleplan_disable_blocks', 1, 1 );
function battleplan_disable_blocks() {
  	wp_deregister_style( 'wc-block-editor' );
  	wp_deregister_style( 'wc-block-style' );
}


// Add nonce to Stripe payment form
add_filter('final_output', function($content) {
	if ( !is_admin() && defined('_BP_NONCE') ) : 
		$content = str_replace("src='https://js.stripe.com","nonce='"._BP_NONCE."' src='https://js.stripe.com", $content); 
	endif;
	return $content;
}); 

// Add theme support for Woocommerce photo gallery
add_action( 'after_setup_theme', 'battleplan_woo_gallery_support' );
function battleplan_woo_gallery_support() {
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}

// Move Short Product Description above main description
add_action( 'edit_form_after_title', 'battleplan_move_excerpt_meta_box' );
function battleplan_move_excerpt_meta_box( $post ) {
    if ( $post->post_type == "product" ) {
        remove_meta_box( 'postexcerpt', $post->post_type, 'normal' ); ?>
        <h2 style="padding: 20px 0 0;">Product Description</h2>
		<style>textarea#excerpt { height:20em; }</style>
        <?php post_excerpt_meta_box( $post );
    }
}

// Use product title to add alt text to shop images
add_action( 'woocommerce_after_shop_loop_item', 'battleplan_set_alt_text_on_images', 10, 2 );
function battleplan_set_alt_text_on_images() {
	if ( _BP_SET_ALT_TEXT == "true" ) :
		$attachment_id = get_post_thumbnail_id( get_the_ID() );
		if ( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) == "" ) :
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', get_the_title() );
		endif;
	endif;		
}

// Fix issue with products containing more than 20 variations
add_filter( 'woocommerce_ajax_variation_threshold', 'battleplan_ajax_variation_threshold', 100, 2 );
function battleplan_ajax_variation_threshold( $qty, $product ) { return 100; }

// Hide other shipping methods if Free Shipping is available 
add_filter( 'woocommerce_package_rates', 'battleplan_hide_shipping_when_free_is_available', 100 );
function battleplan_hide_shipping_when_free_is_available( $rates ) {
	$free = array();
	foreach ( $rates as $rate_id => $rate ) {
		if ( 'free_shipping' === $rate->method_id ) {
			$free[ $rate_id ] = $rate;
			break;
		}
	}
	return ! empty( $free ) ? $free : $rates;
}

// Change breadcrumbs separator to match other archive pages
add_filter( 'woocommerce_breadcrumb_defaults', 'battleplan_change_breadcrumb_separator' );
function battleplan_change_breadcrumb_separator( $defaults ) {
	$defaults['delimiter'] = ' » ';
	return $defaults;
}

// Change number of products per row to 5
add_filter('loop_shop_columns', 'loop_columns', 990);
if (!function_exists('loop_columns')) { function loop_columns() { return 5; }} 

// Change number of products per page to 25 
add_filter('loop_shop_per_page', 'battleplan_loop_shop_per_page', 990, 0);
function battleplan_loop_shop_per_page() { return 25; };

// Change number of related products to 6
add_filter( 'woocommerce_output_related_products_args', 'battleplan_related_products_args', 990 );
function battleplan_related_products_args( $args ) { 
	$args['posts_per_page'] = 6; // 6 total related products
	$args['columns'] = 6; // arranged in 6 columns
	return $args;
}

// Add appropriate formatting to the related products block
add_filter( 'woocommerce_locate_template', 'bp_custom_related_template', 999, 3 );
function bp_custom_related_template( $template, $template_name, $template_path ) {
    if ( $template_name === 'single-product/related.php' ) {
        $custom = trailingslashit( get_template_directory() ) . 'elements/element-woocommerce-related_products.php';
        if ( file_exists( $custom ) && is_readable( $custom ) ) {
            return $custom;
        }
    }
    return $template;
}
?>