<?php
/* The template for displaying the footer */

get_sidebar(); ?>

		</div><!-- #main-content -->
	</section><!-- #wrapper-content -->

	<?php	
	$current_page = sanitize_post( $GLOBALS['wp_the_query']->get_queried_object() );
	$textarea = get_post_meta( $current_page->ID, 'page-bottom_text', true );
 	if ( $textarea != "" ) : echo "<section id='wrapper-bottom'>".apply_filters('the_content', $textarea)."</section><!-- #wrapper-bottom -->"; endif;
	?>

	<footer id="colophon" role="banner" aria-label="footer">		
		
		<?php echo do_shortcode('[get-element slug="site-footer"]'); ?>
		
		<section class="section site-info">			
			<?php if (function_exists('battleplan_siteInfo')) {
				 battleplan_siteInfo();
			 } else { 
				if (function_exists('battleplan_siteInfoLeft')) {
					$buildLeft = battleplan_siteInfoLeft();
				} else {	
					$buildLeft = battleplan_footer_social_box();
				}
	
				if (function_exists('battleplan_siteInfoRight')) {
					$buildRight = battleplan_siteInfoRight();
				} else {	
					$buildCopyright = "";
					$buildCopyright .= wp_nav_menu( array( 'theme_location' => 'footer-menu', 'container' => 'div', 'container_id' => 'footer-navigation', 'container_class' => 'secondary-navigation', 'menu_id' => 'footer-menu', 'menu_class' => 'menu secondary-menu', 'fallback_cb' => 'false', 'echo' => false ) );
					if ( do_shortcode('[get-biz info="misc2"]') ) $buildCopyright .= "<div class='site-info-misc2'>".do_shortcode('[get-biz info="misc2"]')."</div>";						
					$buildCopyright .= "<div class='site-info-copyright'>".do_shortcode('[get-biz info="copyright"]')." ".do_shortcode('[get-biz info="name"]')." • All Rights Reserved • <a href='/privacy-policy/'>Privacy Policy</a></div><div class='site-info-address'>";
					if ( do_shortcode('[get-biz info="street"]') ) $buildCopyright .= do_shortcode('[get-biz info="street"]')." • ";							
					if ( do_shortcode('[get-biz info="city"]') ) :
						$buildCopyright .= do_shortcode('[get-biz info="city"]').", ".do_shortcode('[get-biz info="state-abbr"]')." ".do_shortcode('[get-biz info="zip"]')." • ";
					elseif ( do_shortcode('[get-biz info="region"]') ) : 
						$buildCopyright .= do_shortcode('[get-biz info="region"]')." • "; 
					endif;
					if ( do_shortcode('[get-biz info="license"]') ) $buildCopyright .= "License ".do_shortcode('[get-biz info="license"]')." • "; 						
					if ( do_shortcode('[get-biz info="phone-link"]') ) $buildCopyright .= do_shortcode('[get-biz info="phone-link"]');							
					$buildCopyright .= "</div><div class='site-info-battleplan'>Website developed & maintained by <a href='http://battleplanwebdesign.com' target='_blank' rel='noreferrer'>Battle Plan Web Design</a>";
					if ( do_shortcode('[get-biz info="misc1"]') ) $buildCopyright .= " • ".do_shortcode('[get-biz info="misc1"]');	
					$buildCopyright .= "</div>";					
					if ( do_shortcode('[get-biz info="misc3"]') ) $buildCopyright .= "<div class='site-info-misc3'>".do_shortcode('[get-biz info="misc3"]')."</div>";	
					
					if (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon-80x80.png' ) ) : $iconName = "site-icon-80x80.png"; $iconWH = " width='80' height='80'"; else: $iconName = "site-icon.png"; endif; 

					$buildRight = do_shortcode('[img size="1/6" link = "/" class="site-icon"]<img class="site-icon noFX" src="../../../wp-content/uploads/'.$iconName.'" loading="lazy" alt="Return to Home Page"'.$iconWH.'/>[/img]');
					$buildRight .= do_shortcode('[txt size="5/6"]'.$buildCopyright.'[/txt]');
				}

				echo do_shortcode('[layout grid="1-2"][col class="site-info-left"]'.$buildLeft.'[/col][col class="site-info-right"]'.$buildRight.'[/col][/layout]');
			} ?>					
			
		</section><!-- .site-info -->
	</footer><!-- #colophon -->
</div><!-- #page -->

<!-- Scroll to Top btn -->
<a class ="scroll-top hide-1 hide-2 hide-3" href="#page" role="button"><i class="fa fa-chevron-up" aria-hidden="true"></i><span class="sr-only">Scroll To Top</span></a>	

<?php wp_footer(); ?>	
	
<?php echo '<div id="include-svg">'.do_shortcode('[get-element slug="svg"]').'</div>'; ?>

</body>
</html>
