<?php /* Template part for displaying events */

$buildEvent = '<div class="entry-content">';

	$buildEvent .= include('wp-content/themes/battleplantheme/elements/element-events-meta.php');			
		// added wpautop here instead of globally 2/28/2025
	$buildEvent .= bp_wpautop(get_the_content(), true);  // added wpautop here instead of globally 2/28/2025

	// Anything that belongs under an event's description — a bookable event's dates + Book buttons
	// (includes-site-pulse-booking-public.php).
	$buildEvent .= apply_filters( 'bp_event_page_after_content', '', get_the_ID() );

	$buildEvent .= '</div><!-- .entry-content -->';		
		
echo do_shortcode($buildEvent);
?>