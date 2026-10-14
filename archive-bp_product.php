<?php /* The template for displaying the store archive and product category pages */

bp_inline_minified_css( get_template_directory() . '/style-posts.css' );

get_header(); ?>

<div id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>

	<div class="site-main-inner">

		<?php bp_before_the_content(); ?>

		<?php
		// On a category archive use the term's own name and description, so a client
		// who writes an intro for /shop-category/hats/ actually sees it on the page.
		$sp_store_term = is_tax( 'bp_product_cat' ) ? get_queried_object() : null;

		$sp_store_heading = $sp_store_term
			? $sp_store_term->name
			: ( post_type_archive_title( '', false ) ?: 'Shop' );

		echo '<h1 class="store-archive-title">' . esc_html( $sp_store_heading ) . '</h1>';

		// A checkout that could not start sends the customer back here with its reason.
		// Escaped on the way out: it round-trips through the URL, so however trustworthy
		// the text was when we wrote it, it is untrusted input by the time it returns.
		if ( ! empty( $_GET['sp_store_error'] ) ) {
			echo '<p class="store-notice store-notice-error">'
				. esc_html( sanitize_text_field( wp_unslash( (string) $_GET['sp_store_error'] ) ) )
				. '</p>';
		}

		if ( $sp_store_term && trim( (string) $sp_store_term->description ) !== '' ) {
			echo '<div class="store-archive-intro">' . wpautop( wp_kses_post( $sp_store_term->description ) ) . '</div>';
		}

		if ( have_posts() ) {

			echo '<div class="store-grid" style="--store-cols:3">';
			while ( have_posts() ) : the_post();
				echo sp_store_product_card( get_the_ID() );
			endwhile;
			echo '</div>';

			// Paged archives need real pagination — an archive silently capped at the
			// first page is how products stop being reachable without anyone noticing.
			the_posts_pagination( [
				'mid_size'  => 2,
				'prev_text' => '&lsaquo; Previous',
				'next_text' => 'Next &rsaquo;',
			] );

		} else {

			echo '<p class="store-empty">There is nothing in the shop just yet. Please check back soon.</p>';

		}
		?>

		<?php bp_after_the_content(); ?>

	</div><!-- .site-main-inner -->

	<?php bp_after_site_main_inner(); ?>

</div><!-- #primary .site-main -->

<?php get_footer(); ?>
