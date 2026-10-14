<?php /* The template for displaying a single store product */

bp_inline_minified_css( get_template_directory() . '/style-posts.css' );

get_header(); ?>

<div id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>

	<div class="site-main-inner">

		<?php bp_before_the_content(); ?>

		<?php while ( have_posts() ) : the_post();

			// The buy block — gallery, price, variant picker, add to cart. Everything
			// that reads the variants table lives in one function so this template and
			// the [store-product] shortcode can never drift apart.
			echo sp_store_single_product( get_the_ID() );

			// The long description is the post body, so the client writes it in the
			// editor like any other page and every framework shortcode works in it.
			$sp_store_body = trim( get_the_content() );
			if ( $sp_store_body !== '' ) {
				echo '<div class="store-single-body">';
				// Editable under Settings → Store; blank means no heading at all.
				$sp_store_heading = trim( (string) sp_store_get_setting( 'description_heading', 'About This Item' ) );
				if ( $sp_store_heading !== '' ) {
					echo '<h2 class="store-single-body-title">' . esc_html( $sp_store_heading ) . '</h2>';
				}
				the_content();
				echo '</div>';
			}

			$sp_store_terms = get_the_term_list( get_the_ID(), 'bp_product_cat', '<p class="store-single-cats">', ', ', '</p>' );
			if ( $sp_store_terms && ! is_wp_error( $sp_store_terms ) ) {
				echo $sp_store_terms;
			}

		endwhile; ?>

		<?php bp_after_the_content(); ?>

	</div>

	<?php bp_after_site_main_inner(); ?>

</div>

<?php get_footer(); ?>
