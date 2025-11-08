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
			
			if (function_exists('battleplan_siteInfo')) : battleplan_siteInfo();
			else : 
				if (function_exists('battleplan_siteInfoLeft')) : $buildLeft = battleplan_siteInfoLeft();
				else : $buildLeft = battleplan_footer_social_box();
				endif;
	
				if (function_exists('battleplan_siteInfoRight')) : $buildRight = battleplan_siteInfoRight();
				else: 	
				
					$googleInfo = get_option('bp_gbp_update');
					$customer_info = customer_info();

					$buildCopyright = "";
					$buildCopyright .= wp_nav_menu( array( 'theme_location' => 'footer-menu', 'container' => 'div', 'container_id' => 'footer-navigation', 'container_class' => 'secondary-navigation', 'menu_id' => 'footer-menu', 'menu_class' => 'menu secondary-menu', 'fallback_cb' => 'false', 'echo' => false ) );
					
					if ( isset($customer_info['misc2']) ) $buildCopyright .= "<div class='site-info-misc2'>".$customer_info['misc2']."</div>";					
					if ( isset($customer_info['copyright']) ) $buildCopyright .= "<div class='site-info-copyright'>".$customer_info['copyright']." ".$customer_info['name']." • All Rights Reserved</div>";		
					
					$placeIDs = $customer_info['pid'] ?? null;
					if ( !is_array($placeIDs) ) $placeIDs = array($placeIDs);
					$primePID = true;
					foreach ( $placeIDs as $placeID ) :	
						if ( $primePID === true ) :
							$google_info = customer_info();
							$primePID = false;
						else:
							$google_info = $googleInfo[$placeID];
						endif;
						$buildCopyright .= "<div class='site-info-address'>";
						if ( strlen($google_info['street']) > 5 ) $buildCopyright .= trim($google_info['street']).", ";							
						if ( array_key_exists('city', $google_info) ) :
							$buildCopyright .= $google_info['city'].", ".$google_info['state-abbr']." ".$google_info['zip'];
						elseif ( array_key_exists('region', $google_info) ) : 
							$buildCopyright .= $google_info['region']; 
						endif;
						if ( array_key_exists('phone-format', $google_info) ) $buildCopyright .= " • ".$google_info['phone-format'];

						$buildCopyright .= "</div>";
					endforeach;
					
					if ( isset($customer_info['misc3']) ) $buildCopyright .= "<div class='site-info-misc3'>".$customer_info['misc3']."</div>";
			
					$buildCopyright .= "<div class='site-info-links'>";
					
					if ( isset($customer_info['license']) ) $buildCopyright .= "License ".$customer_info['license']." • "; 

					$links = [];

					if ($privacy = get_page_by_path('privacy-policy', OBJECT, 'universal')) {
						$links[] = "<span class='privacy-policy-link'><a href='" . get_permalink($privacy->ID) . "'>Privacy Policy</a></span>";
					}

					if ($terms = get_page_by_path('terms-conditions', OBJECT, 'universal')) {
						$links[] = "<span class='terms-conditions-link'><a href='" . get_permalink($terms->ID) . "'>Terms & Conditions</a></span>";
					}

					if ($areas = get_page_by_path('areas-we-serve', OBJECT, 'universal')) {
						$links[] = "<span class='areas-we-serve-link'><a href='" . get_permalink($areas->ID) . "'>Areas We Serve</a></span>";
					}

					if (!empty($links)) {
						$buildCopyright .= implode(' • ', $links);
					}
					
					if ( isset($customer_info['misc1']) ) $buildCopyright .= " • ".$customer_info['misc1'];
					
					$buildCopyright .= "</div><div class='site-info-battleplan'>Website developed & maintained by <a href='http://battleplanwebdesign.com' target='_blank' rel='noreferrer'>Battle Plan Web Design</a></div>";
					
					//$buildCopyright .= "</div>";				

					$siteIcon = battleplan_fetch_site_icon();
			
					if ($siteIcon) :
						$buildRight = do_shortcode('[img size="1/6" link = "/" class="site-icon"]<img class="site-icon noFX" src="/wp-content/uploads/'.$siteIcon['name'].'" loading="lazy" alt="Return to Home Page"'.$siteIcon['wh'].'/>[/img]');
						$buildRight .= do_shortcode('[txt size="5/6"]'.$buildCopyright.'[/txt]');
					else:
						$buildRight .= do_shortcode('[txt size="100"]'.$buildCopyright.'[/txt]');
					endif;
				endif;

				echo do_shortcode('[layout grid="1-2"][col class="site-info-left"]'.$buildLeft.'[/col][col class="site-info-right"]'.$buildRight.'[/col][/layout]');
			endif; ?>
			
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