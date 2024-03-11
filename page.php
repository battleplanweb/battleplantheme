<?php /* The template for displaying all pages */

get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	
	
		<article id="post-<?php the_ID(); ?>">
			
			<?php the_content();?>

		</article><!-- #post-<?php the_ID(); ?> -->

		<?php if ( comments_open() || get_comments_number() ) comments_template(); ?>

		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</main><!-- #primary .site-main -->

<?php get_footer();