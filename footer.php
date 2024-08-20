<?php /* The template for displaying the footer */

	$current_page = sanitize_post( $GLOBALS['wp_the_query']->get_queried_object() );
	if ( get_post_meta( $current_page->ID ?? 0, '_bp_remove_sidebar', true ) != true ) : get_sidebar(); endif;
?>

		</div><!-- #main-content -->
		
		<?php bp_after_main_content(); ?>
				
	</section><!-- #wrapper-content -->
	
	<?php bp_after_wrapper_content(); ?>

	<?php	
	$textarea = get_post_meta( $current_page->ID ?? 0, 'page-bottom_text', true );
 	if ( $textarea != "" ) : echo "<section id='wrapper-bottom'>".apply_filters('the_content', $textarea)."</section><!-- #wrapper-bottom -->"; endif;
	?>
	
	<?php bp_before_colophon(); ?>

	<footer id="colophon" role="region" aria-label="footer">		
		
		<?php echo do_shortcode('[get-element slug="site-footer"]'); ?>
		
		<section class="section site-info">			
			<?php if (function_exists('battleplan_siteInfo')) : battleplan_siteInfo();
			else : 
				if (function_exists('battleplan_siteInfoLeft')) : $buildLeft = battleplan_siteInfoLeft();
				else : $buildLeft = battleplan_footer_social_box();
				endif;
	
				if (function_exists('battleplan_siteInfoRight')) : $buildRight = battleplan_siteInfoRight();
				else: 	
				
					$googleInfo = get_option('bp_gbp_update');

					$buildCopyright = "";
					$buildCopyright .= wp_nav_menu( array( 'theme_location' => 'footer-menu', 'container' => 'div', 'container_id' => 'footer-navigation', 'container_class' => 'secondary-navigation', 'menu_id' => 'footer-menu', 'menu_class' => 'menu secondary-menu', 'fallback_cb' => 'false', 'echo' => false ) );
					
					if ( isset($GLOBALS['customer_info']['misc2']) ) $buildCopyright .= "<div class='site-info-misc2'>".$GLOBALS['customer_info']['misc2']."</div>";					
					
					$buildCopyright .= "<div class='site-info-copyright'>".$GLOBALS['customer_info']['copyright']." ".$GLOBALS['customer_info']['name']." • All Rights Reserved</div>";
					
					$placeIDs = $GLOBALS['customer_info']['pid'] ?? null;
					if ( !is_array($placeIDs) ) $placeIDs = array($placeIDs);
					$primePID = true;
					foreach ( $placeIDs as $placeID ) :	
						if ( $primePID == true ) :
							$customer_info = $GLOBALS['customer_info'];
							$primePID = false;
						else:
							$customer_info = $googleInfo[$placeID];
						endif;
						$buildCopyright .= "<div class='site-info-address'>";
						if ( strlen($customer_info['street']) > 5 ) $buildCopyright .= trim($customer_info['street']).", ";							
						if ( array_key_exists('city', $customer_info) ) :
							$buildCopyright .= $customer_info['city'].", ".$customer_info['state-abbr']." ".$customer_info['zip'];
						elseif ( array_key_exists('region', $customer_info) ) : 
							$buildCopyright .= $customer_info['region']; 
						endif;
						if ( isset($customer_info['phone-format']) && $customer_info['phone-format'] ) $buildCopyright .= " • ".$customer_info['phone-format'];
						$buildCopyright .= "</div>";
					endforeach;
					
					if ( isset($GLOBALS['customer_info']['misc3']) ) $buildCopyright .= "<div class='site-info-misc3'>".$GLOBALS['customer_info']['misc3']."</div>";
			
					$buildCopyright .= "<div class='site-info-links'>";
					
					if ( isset($GLOBALS['customer_info']['license']) ) $buildCopyright .= "License ".$GLOBALS['customer_info']['license']." • "; 
					
					$buildCopyright .= "<span class='privacy-policy-link'><a href='/privacy-policy/'>Privacy Policy</a></span><span class='terms-conditions-link'> • <a href='/terms-conditions/'>Terms & Conditions</a></span>";
					
					if ( isset($GLOBALS['customer_info']['misc1']) ) $buildCopyright .= " • ".$GLOBALS['customer_info']['misc1'];
					
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
			
		</section><!-- .site-info -->
	</footer><!-- #colophon -->
	
	<?php bp_after_colophon(); ?>
	
	<?php 
		$buildLinks = '<div class="wp-google-badge-faux"></div>';		
		$buildLinks .= '<div class="bp-service-areas">Our Service Areas:<br><ul class="three-col">';
		$buildLinks .= do_shortcode('[get-service-areas]');
		$buildLinks .= '</ul></div>';	
		echo $buildLinks;	
	?>	
		
</div><!-- #page -->

<?php echo do_shortcode('[get-icon type="chevron-up" class="scroll-top hide-1 hide-2 hide-3" link="#page" sr="Scroll To Top"]'); ?>

<?php wp_footer(); ?>
<?php if ( shortcode_exists( 'get-svg' ) ) echo '<div id="include-svg">'.do_shortcode('[get-svg]').'</div>' ?>

<?php  	
	$icon_style = '';
	if (is_array($GLOBALS['icon-css'])) :
		$icon_css = array_unique($GLOBALS['icon-css']);
		foreach ($icon_css as $icon) :
			if (is_array($GLOBALS['icons']) && array_key_exists($icon, $GLOBALS['icons'])) $icon_style .= '.icon.' . $icon . '::after { content: "' . $GLOBALS['icons'][$icon] . '"; }';
		endforeach;
	endif;
	if ( $icon_style != '' ) : ?>
		<style><?php echo $icon_style; ?></style>
	<?php endif; 

	if ( _USER_LOGIN != "battleplanweb" && _IS_BOT != true ) updateOption('last_visitor_time', strtotime(date("F j, Y g:i a"))); 
?>

</body>
</html>