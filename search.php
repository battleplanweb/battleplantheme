<?php /*** The template for displaying search results pages */

get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	
	
		<?php if ( have_posts() ) : ?>

			<header class="page-header">
				<h1 class="page-title">
					<?php
					/* translators: %s: search query. */
					printf( esc_html__( 'Search Results for: %s', 'battleplan' ), '<span>' . get_search_query() . '</span>' );
					?>
				</h1>
			</header><!-- .page-header -->

			<?php
			/* Start the Loop */
			while ( have_posts() ) :
				the_post();

				/**
				 * Run the loop for the search to output the results.
				 * If you want to overload this in a child theme then include a file
				 * called content-search.php and that will be used instead.
				 */
				get_template_part( 'template-parts/content', 'search' );

			endwhile;

			the_posts_navigation();

		else :

			get_template_part( 'template-parts/content', 'none' );

		endif;
		?>

		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</main><!-- #primary .site-main -->

<?php
get_footer();