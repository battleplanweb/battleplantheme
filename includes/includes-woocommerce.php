<?php
/* Battle Plan Web Design Woocommerce Includes

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Functions to extend WordPress
# Import Advanced Custom Fields
# Admin Columns Set Up
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
# Admin Columns Set Up
--------------------------------------------------------------*/
add_action( 'init', 'battleplan_woo_column_settings' );
function battleplan_woo_column_settings() {				
	if (function_exists('ac_get_site_url')) {
		ac_register_columns( 'shop_order', array(
			array(
				'columns'=>array(
					'order_number'=>array(
						'type'=>'order_number',
						'label'=>'Order',
						'width'=>'300',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'order_number',
						'label_type'=>'',
						'search'=>'on'
					),
					'date-published'=>array(
						'type'=>'column-date_published',
						'label'=>'Date',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'wp_default',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'date-published',
						'label_type'=>'',
						'search'=>'on'
					),
					'shipping_address'=>array(
						'type'=>'shipping_address',
						'label'=>'Ship To',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'shipping_address',
						'label_type'=>'',
						'search'=>'on'
					),
					'order_total'=>array(
						'type'=>'order_total',
						'label'=>'Total',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'order_total',
						'label_type'=>'',
						'search'=>'on'
					),
					'order_status'=>array(
						'type'=>'order_status',
						'label'=>'Status',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'title',
						'label_type'=>'',
						'search'=>'on'
					),
				),
				'layout'=>array(
					'id'=>'battleplan-woo-orders-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );
		ac_register_columns( 'product', array(
			array(
				'columns'=>array(
					'featured-image'=>array(
						'type'=>'column-featured_image',
						'label'=>'',
						'width'=>'80',
						'width_unit'=>'px',
						'featured_image_display'=>'image',
						'image_size'=>'cpac-custom',
						'image_size_w'=>'60',
						'image_size_h'=>'60',
						'edit'=>'off',
						'sort'=>'off',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'featured-image',
						'label_type'=>'',
						'search'=>'on'
					),
					'post-id'=>array(
						'type'=>'column-postid',
						'label'=>'ID',
						'width'=>'100',
						'width_unit'=>'px',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'post-id',
						'label_type'=>'',
						'search'=>'on'
					),
					'title'=>array(
						'type'=>'title',
						'label'=>'Title',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'title',
						'label_type'=>'',
						'search'=>'on'
					),
					'column-slug'=>array(
						'type'=>'column-slug',
						'label'=>'Slug',
						'width'=>'15',
						'width_unit'=>'%',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'column-slug',
						'label_type'=>'',
						'search'=>'on'
					),
					'last-modified'=>array(
						'type'=>'column-modified',
						'label'=>'Modified',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'diff',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'last-modified',
						'label_type'=>'',
						'search'=>'on'
					),
					'date-published'=>array(
						'type'=>'column-date_published',
						'label'=>'Published',
						'width'=>'130',
						'width_unit'=>'px',
						'date_format'=>'wp_default',
						'edit'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'date-published',
						'label_type'=>'',
						'search'=>'on'
					),
					'categories'=>array(
						'type'=>'categories',
						'label'=>'Categories',
						'width'=>'100',
						'width_unit'=>'px',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'name'=>'categories',
						'label_type'=>'',
						'filter_label'=>'',
						'search'=>'on'
					),
					'tags'=>array(
						'type'=>'tags',
						'label'=>'Tags',
						'width'=>'100',
						'width_unit'=>'px',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'tags',
						'label_type'=>'',
						'search'=>'on'
					),
					'author'=>array(
						'type'=>'author',
						'label'=>'Author',
						'width'=>'',
						'width_unit'=>'%',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'author',
						'label_type'=>'',
						'search'=>'on'
					),
					'menu-order'=>array(
						'type'=>'column-order',
						'label'=>'Order',
						'width'=>'100',
						'width_unit'=>'px',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'menu-order',
						'label_type'=>'',
						'search'=>''
					)
				),
				'layout'=>array(
					'id'=>'battleplan-woo-products-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
					
		) );
	}
}


/*--------------------------------------------------------------
# Basic Theme Set Up
--------------------------------------------------------------*/

// Declare support for Woocommerce
add_theme_support( 'woocommerce' );

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

?>