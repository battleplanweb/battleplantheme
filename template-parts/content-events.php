<?php /* Template part for displaying events */

$buildEvent = '<div class="entry-content">';

	$buildEvent .= include('wp-content/themes/battleplantheme/elements/element-events-meta.php');			
		// added wpautop here instead of globally 2/28/2025
	$buildEvent .= bp_wpautop(get_the_content(), true);  // added wpautop here instead of globally 2/28/2025
	
	$buildEvent .= '</div><!-- .entry-content -->';		
		
echo do_shortcode($buildEvent);
?>