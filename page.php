<?php /* The template for displaying all pages */

get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="main content">
	<div class="site-main-inner">
	
		<article id="post-<?php the_ID(); ?>">
			
			<?php the_content();?>

		</article><!-- #post-<?php the_ID(); ?> -->

		<?php if ( comments_open() || get_comments_number() ) comments_template(); ?>

	</div><!-- .site-main-inner -->
</main><!-- #primary .site-main -->

<?php
get_footer();