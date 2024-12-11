<?php /*** The template for displaying search results pages */

get_header(); ?>

<div id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	
	
		<?php if ( have_posts() ) : ?>

			<header class="page-header">
				<h3 class="page-title">Search Results For:</h3>
				<h2>
					<?php
					printf( esc_html__( '%s', 'battleplan' ), '<span>' . get_search_query() . '</span>' );
					?>
				</h2>
			</header><!-- .page-header -->

			<ol class="search-results">
		<?php

			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'search' );
			endwhile;
			
		?>			
			</ol>
			
		<?php

			the_posts_navigation();

		else :
			get_template_part( 'template-parts/content', 'none' );
		endif;
		?>

		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</div><!-- #primary .site-main -->

<?php get_footer();