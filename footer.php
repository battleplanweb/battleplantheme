<?php /* The template for displaying the footer */

	$current_page = sanitize_post( $GLOBALS['wp_the_query']->get_queried_object() );
	if ( get_post_meta( $current_page->ID ?? 0, '_bp_remove_sidebar', true ) != true ) : get_sidebar(); endif;
	$customer_info = customer_info();
?>
		</div><!-- #main-content -->

		<?php bp_after_main_content(); ?>

	</main><!-- #wrapper-content -->

	<?php bp_after_wrapper_content(); ?>

	<?php
	$textarea = get_post_meta($current_page->ID ?? 0, 'page-bottom_text', true);
	if ($textarea) :
		echo "<section id='wrapper-bottom'>" . bp_wpautop($textarea) . "</section><!-- #wrapper-bottom -->";
	endif;
	?>

	<?php bp_before_colophon(); ?>

	<footer id="colophon">

		<?php echo do_shortcode('[get-element slug="site-footer"]'); ?>

		<section class="section site-info">

			<?php
			if ( function_exists('battleplan_siteInfo') ) {
				battleplan_siteInfo();
			} else {
				if ( function_exists('battleplan_siteInfoLeft') ) {
					$buildLeft = battleplan_siteInfoLeft();
				} else {
					$buildLeft = bp_footer_left();
				}

				if ( function_exists('battleplan_siteInfoRight') ) {
					$buildRight = battleplan_siteInfoRight();
				} else {
					$buildRight = bp_footer_right();
				}

				$buildFooter = '[layout grid="1-2"]';
				$buildFooter .= '[col class="site-info-left"]'.$buildLeft.'[/col]';
				$buildFooter .= '[col class="site-info-right"]'.$buildRight.'[/col]';
				$buildFooter .= '[/layout]';

				echo do_shortcode($buildFooter);
			} ?>

			<a class="bot-trap" href="/wp-content/themes/battleplantheme/_bot/tripwire.php" rel="nofollow" aria-hidden="true">Press Kit</a>

		</section><!-- .site-info -->
	</footer><!-- #colophon -->

	<?php bp_after_colophon(); ?>

</div><!-- #page -->

<?php echo do_shortcode('[get-icon type="chevron-up" class="scroll-top hide-1 hide-2 hide-3" link="#page" sr="Scroll To Top"]'); ?>

<?php if ( shortcode_exists( 'get-svg' ) ) echo '<div id="include-svg">'.do_shortcode('[get-svg]').'</div>' ?>

<?php
	$icon_style = '';

	if (isset($GLOBALS['icon_css']) && is_array($GLOBALS['icon_css'])) :
		$icon_css = array_unique($GLOBALS['icon_css']);
		foreach ($icon_css as $icon) :
			if (is_array($GLOBALS['icons']) && array_key_exists($icon, $GLOBALS['icons'])) $icon_style .= '.icon.' . $icon . '::after { content: "' . $GLOBALS['icons'][$icon] . '"; }';
		endforeach;
	endif;
	if ( $icon_style != '' ) : ?>
		<style><?php echo $icon_style; ?></style>
	<?php endif;

	if ( _USER_LOGIN != "battleplanweb" && _IS_BOT != true ) updateOption('last_visitor_time', strtotime(date("F j, Y g:i a")));
?>

<?php wp_footer(); ?>

</body>
</html>