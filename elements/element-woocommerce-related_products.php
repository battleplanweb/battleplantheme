<?php
/* Battle Plan Web Design - Woocommerce related products */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( $related_products ) :
	echo '</div><div class="related products">';

	$heading = apply_filters( 'woocommerce_product_related_products_heading', __( 'Related products', 'woocommerce' ) );
	if ( $heading ) {
		echo '<h2>' . esc_html( $heading ) . '</h2>';
	}

	woocommerce_product_loop_start();

	foreach ( $related_products as $related_product ) :
		$post_object = get_post( $related_product->get_id() );
		setup_postdata( $GLOBALS['post'] =& $post_object );
		wc_get_template_part( 'content', 'product' ); // echoes
	endforeach;

	woocommerce_product_loop_end();

endif;

wp_reset_postdata();
?>