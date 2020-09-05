<?php
/* The template for displaying all pages */

get_header();
?>

	<main id="primary" class="site-main">

		<article id="post-<?php the_ID(); ?>">
			
			<?php the_content();?>

		</article><!-- #post-<?php the_ID(); ?> -->

		<?php if ( comments_open() || get_comments_number() ) comments_template(); ?>

	</main><!-- #primary -->

<?php
get_sidebar();
get_footer();
