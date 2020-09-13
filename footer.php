<?php
/* The template for displaying the footer */
?>

		</div><!-- #main-content -->
	</section><!-- #wrapper-content -->

	<?php	
	$current_page = sanitize_post( $GLOBALS['wp_the_query']->get_queried_object() );
	$page_slug = $current_page->post_name;
	$page_data = get_page_by_path($page_slug."-bottom", OBJECT, 'page' );
	if ( $page_data && $page_data->post_status == 'publish' ) : echo "<section id='wrapper-bottom'>".apply_filters('the_content', $page_data->post_content)."</section><!-- #wrapper-bottom -->"; endif; 
	?>

	<footer id="colophon">		
		<?php		
		if ( is_front_page() && is_home() ) :
			$page_slug = "site-footer-home"; 
			$page_data = get_page_by_path( $page_slug, OBJECT, 'page' );
			if ( !$page_data ) : $page_slug = "site-footer"; endif;				
		else: $page_slug = "site-footer"; endif;
		$page_data = get_page_by_path( $page_slug, OBJECT, 'page' );
		if ( $page_data && $page_data->post_status == 'publish' ) : echo "<div class='site-footer'>".apply_filters('the_content', $page_data->post_content)."</div><!-- .site-footer -->"; endif;
		?>
		
		<section class="section site-info">			
			<?php if (function_exists('battleplan_siteInfo')) {
				 battleplan_siteInfo();
			 } else { 
				$buildLeft = "<div class='social-box'>";
					if ( do_shortcode('[get-biz info="facebook"]') ) $buildLeft .= do_shortcode('[social-btn type="facebook"]'); 							
					if ( do_shortcode('[get-biz info="twitter"]') ) $buildLeft .= do_shortcode('[social-btn type="twitter"]');						
					if ( do_shortcode('[get-biz info="instagram"]') ) $buildLeft .= do_shortcode('[social-btn type="instagram"]');							
					if ( do_shortcode('[get-biz info="email"]') ) $buildLeft .= do_shortcode('[social-btn type="email"]');
				$buildLeft .= "</div>";

				$buildCopyright = "<div>".do_shortcode('[get-biz info="copyright"]')." ".do_shortcode('[get-biz info="name"]')." • All Rights Reserved • <a href='/privacy-policy/'>Privacy Policy</a></div><div>";
				if ( do_shortcode('[get-biz info="street"]') ) $buildCopyright .= do_shortcode('[get-biz info="street"]')." • ";							
				if ( do_shortcode('[get-biz info="city"]') ) :
					$buildCopyright .= do_shortcode('[get-biz info="city"]').", ".do_shortcode('[get-biz info="state-abbr"]')." ".do_shortcode('[get-biz info="zip"]')." • ";
				elseif ( do_shortcode('[get-biz info="region"]') ) : 
					$buildCopyright .= do_shortcode('[get-biz info="region"]')." • "; 
				endif;
				if ( do_shortcode('[get-biz info="license"]') ) $buildCopyright .= "License #".do_shortcode('[get-biz info="license"]')." • "; 						
				if ( do_shortcode('[get-biz info="phone-link"]') ) $buildCopyright .= do_shortcode('[get-biz info="phone-link"]');							
				$buildCopyright .= "</div><div>Website developed & maintained by <a href='http://battleplanwebdesign.com' target='_blank'>Battle Plan Web Design</a>";
				if ( do_shortcode('[get-biz info="misc1"]') ) $buildCopyright .= " • ".do_shortcode('[get-biz info="misc1"]');	
				$buildCopyright .= "</div>";

				$buildRight = do_shortcode('[img size="1/6" link = "/" class="site-icon"]<img class="site-icon noFX" src="../../../wp-content/uploads/site-icon.png" alt="Return to Home Page"/>[/img]');
				$buildRight .= do_shortcode('[txt size="5/6"]'.$buildCopyright.'[/txt]');

				echo do_shortcode('[layout grid="1-2"][col class="site-info-left"]'.$buildLeft.'[/col][col class="site-info-right"]'.$buildRight.'[/col][/layout]');
			} ?>					
			
		</section><!-- .site-info -->
	</footer><!-- #colophon -->
</div><!-- #page -->

<!-- Scroll to Top btn -->
<a class ="scroll-top hide-1 hide-2 hide-3" href="#page"><i class="fa fa-chevron-up" aria-hidden="true"></i><span class="sr-only">Scroll To Top</span></a>


<?php bp_footer_scripts(); ?>

<?php wp_footer(); ?>

</body>
</html>
