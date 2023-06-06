<?php /* Template part for displaying events */

$buildEvent = '<div class="entry-content">';

	$buildEvent .= include('wp-content/themes/battleplantheme/elements/element-events-meta.php');			
		
	$buildEvent .= get_the_content( sprintf ( wp_kses( __( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'battleplan' ), array( 'span' => array( 'class' => array(), ), ) ), wp_kses_post( get_the_title() ) ) ); 
	
	$buildEvent .= '</div><!-- .entry-content -->';		
		
echo do_shortcode($buildEvent);
?>