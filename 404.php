<?php /* The template for displaying 404 pages (not found) */

get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	

		<article class="<?php echo $class;?>">
			
			<?php				
				$headlines = array("Well, this is embarrassing.", "We took a wrong turn.", "Something went wrong.", "Don't panic.", "Keep calm.", "Oops... our mistake.", "Hmmmmm... interesting.", "Blame the website guy.", "Well, this is unfortunate.", "Houston, we have a problem.", "We hit a wall.", "This page was shredded.");
				
				$num = count($headlines) - 1;				
				$rand = rand(0,$num);
				
				$page_content = '[section width="inline"][layout][col align="center"]';				
				$page_content .= '<h1>'.$headlines[$rand].'</h1>';
				$page_content .= '[/col][/layout][layout grid="2-3" valign="center"][col]<img src="/wp-content/themes/battleplantheme/common/logos/404-error-page.png" alt="404 error: This page does not exist" width="500" height="262" style="aspect-ratio:500/262" class="img-404" />[/col][col align="left"][txt]';
				$page_content .= '<p>Apparently, this page has been moved or does not exist.</p>';				
   				$page_content .= '<p>You have options:</p><ul>';
				$page_content .= '<li>Use the menu buttons to find a different page</li>';
				$page_content .= '<li>Call us for help: [get-biz info="phone-link"]</li>';
				$page_content .= '<li>Email us for help: <a href="mailto:[get-biz info="email"]">[get-biz info="email"]</a>';
				$page_content .= '<li>Use the form below to send us a message</li>';
				$page_content .= '</ul><p>Thank you for your patience.  We appreciate your business!</p>';				
  				$page_content .= '[/txt][/col][/layout][layout][col][contact-form-7 title="Contact Us Form"][/col][/layout][/section]';
				
				echo do_shortcode($page_content);			
			?>

		</article><!-- <?php echo $class;?> -->
		
		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</main><!-- #primary .site-main -->

<?php get_footer();