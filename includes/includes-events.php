<?php
/* Battle Plan Web Design Events Calendar PRO Includes
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Event Teasers

--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Event Teasers
--------------------------------------------------------------*/
add_shortcode( 'event_teasers', 'battleplan_event_teasers' );
function battleplan_event_teasers( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'1', 'width'=>'default', 'grid'=>'1-1-1', 'tag'=>'featured', 'max'=>'3', 'start'=>'now', 'end'=>'', 'valign'=>'stretch', 'show_btn'=>'true', 'btn_text'=>'Read More' ), $atts );
	$name = esc_attr($a['name']);
	$style = esc_attr($a['style']);
	$width = esc_attr($a['cat']);
	$grid = esc_attr($a['grid']);
	$tag = esc_attr($a['tag']);
	$max = esc_attr($a['max']);
	$start = esc_attr($a['start']);	
	$end = esc_attr($a['end']);
	$valign = esc_attr($a['valign']);
	$showBtn = esc_attr($a['show_btn']);
	$btnText = esc_attr($a['btn_text']);
	
	$events = tribe_get_events( [ 'start_date' => $start, 'end_date' => $end, 'eventDisplay' => 'list', 'posts_per_page' => $max, 'tag' => $tag] );
	
	if ( $events ) :
		$buildEvents = '[section name="'.$name.'" style="'.$style.'" width="'.$width.'" class="event-teasers"]';
		$buildEvents .= '[layout grid="'.$grid.'" valign="'.$valign.'"]';	
		foreach ( $events as $post ) {
			setup_postdata( $post );		
			$buildEvents .= '[col]';		
			$buildEvents .= get_the_post_thumbnail( $post->ID, 'thumbnail', array( 'class' => 'aligncenter' ) ); 
			$buildEvents .= '[txt]<h3>'.$post->post_title.'</h3>';
			$buildEvents .= '<p class="event-meta"><span class="tribe-event-date-start">'.tribe_get_start_date( $post );
			if ( tribe_get_end_time($post) ) $buildEvents .= ' to '.tribe_get_end_time($post);
			$buildEvents .= '</p><p>'.$post->post_excerpt.'</p>';		
			$buildEvents .= '[/txt]';
			if ( $showBtn == "true" ) $buildEvents .= '[btn link="'.esc_url(get_the_permalink($post->ID)).'"]'.$btnText.'[/btn]';			
			$buildEvents .= '[/col]';
		}
		$buildEvents .= '[/layout][/section]';	
		return do_shortcode($buildEvents);
	endif;
}	
?>