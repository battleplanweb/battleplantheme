<?php /* The template for displaying all pages */

get_header(); ?>

<div id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	
	
		<article id="post-<?php the_ID(); ?>">
			
			<?php echo bp_wpautop(get_the_content());  // added wpautop here instead of globally 2/28/2025 ?>

		</article><!-- #post-<?php the_ID(); ?> -->

		<?php if ( comments_open() || get_comments_number() ) comments_template(); ?>

		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</div><!-- #primary .site-main -->

<?php get_footer();