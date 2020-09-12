<?php
/* The template for displaying 404 pages (not found) */

get_header();
?>

	<main id="primary" class="site-main">

		<article class="error-404 not-found">
			
			<h1>Sorry!  We can't find that page.</h1>
			
			<p>The page you are looking for does not exist, or has been removed.</p>
			
			<p>Please try using the menu options to navigate the site, or use the form below to contact us.</p>
			
			<?php echo do_shortcode('[contact-form-7 title="Contact Us Form"]'); ?>

		</article><!-- .error-404 -->

	</main><!-- #primary -->

<?php
get_sidebar();
get_footer();
?>