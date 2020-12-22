<?php
/* Battle Plan Web Design Woocommerce Includes

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Functions to extend WordPress
# Import Advanced Custom Fields
# Set Up Admin Columns
# Basic Theme Set Up



/*--------------------------------------------------------------
# Functions to extend WordPress
--------------------------------------------------------------*/

// Display number of items in the cart
add_shortcode( 'get-cart', 'battleplan_getCartNum' );
function battleplan_getCartNum($atts, $content = null ) {
	if ( !is_admin() && !is_wplogin() ) :
		global $woocommerce; $cartQty = $woocommerce->cart->get_cart_contents_count();
		if ( $cartQty > 0 ) : return "&nbsp;&nbsp;".$cartQty; else: return ""; endif; 
	endif;
}

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
# Import Advanced Custom Fields
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Set Up Admin Columns
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Basic Theme Set Up
--------------------------------------------------------------*/

// Declare support for Woocommerce
add_theme_support( 'woocommerce' );

// Add theme support for Woocommerce photo gallery
add_action( 'after_setup_theme', 'battleplan_woo_gallery_support' );
function battleplan_woo_gallery_support() {
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
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
?>