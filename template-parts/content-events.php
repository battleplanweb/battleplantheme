<?php /* Template part for displaying events */

$venue = esc_attr(get_field( "venue" ));
$location = esc_attr(get_field( "location" ));
$venueLink = esc_url(get_field( "venue_link" ));
$startTime = esc_attr(get_field( "start_time" ));
$endTime = esc_attr(get_field( "end_time" ));
$startDate = esc_attr(get_field( "start_date" ));
$endDate = esc_attr(get_field( "end_date" ));

$buildEvent = '<div class="entry-content">';

	$buildEvent .= include('wp-content/themes/battleplantheme/elements/element-events-meta.php');			
		
	$buildEvent .= get_the_content( sprintf ( wp_kses( __( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'battleplan' ), array( 'span' => array( 'class' => array(), ), ) ), wp_kses_post( get_the_title() ) ) ); 
	
	$buildEvent .= '</div><!-- .entry-content -->';		
		
echo do_shortcode($buildEvent);
?>