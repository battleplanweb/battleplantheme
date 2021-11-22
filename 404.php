<?php /* The template for displaying 404 pages (not found) */

get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	

		<article class="<?php echo $class;?>">
			
			<?php echo $content;?>

		</article><!-- <?php echo $class;?> -->
		
		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</main><!-- #primary .site-main -->

<?php get_footer(); ?>