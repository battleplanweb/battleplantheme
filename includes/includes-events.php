<?php
/* Battle Plan Web Design Events Calendar PRO Includes

https://docs.theeventscalendar.com/reference/functions/
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shortcodes
# Plug-in Setup
# Set Up Admin Columns
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/
// display teasers of upcoming events 
add_shortcode( 'event_teasers', 'battleplan_event_teasers' );
function battleplan_event_teasers( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'1', 'width'=>'default', 'grid'=>'1-1-1', 'tag'=>'featured', 'max'=>'3', 'offset'=>'0', 'start'=>'today', 'end'=>'', 'valign'=>'stretch', 'show_btn'=>'true', 'btn_text'=>'Read More', 'excerpt'=>'true' ), $atts );
	$name = esc_attr($a['name']);
	$style = esc_attr($a['style']);
	$width = esc_attr($a['cat']);
	$grid = esc_attr($a['grid']);
	$tag = esc_attr($a['tag']);
	$num = 0;
	$max = esc_attr($a['max']);
	$offset = esc_attr($a['offset']);
	$cutoff = esc_attr($a['start']);	
	$start = strtotime($cutoff."-7 days");
	$end = esc_attr($a['end']);
	$valign = esc_attr($a['valign']);
	$showBtn = esc_attr($a['show_btn']);
	$btnText = esc_attr($a['btn_text']);
	$excerpt = esc_attr($a['excerpt']);
	$buildEvents = "";
	
	$events = tribe_get_events( [ 'start_date' => $start, 'end_date' => $end, 'eventDisplay' => 'list', 'posts_per_page' => $max, 'offset' => $offset, 'tag' => $tag] );
	
	if ( $events ) :
		foreach ( $events as $post ) {
			setup_postdata( $post );			
			if ( tribe_get_end_date($post, false) < $cutoff && $num <= $max ) {			
				$buildEvents .= '[col]';		
				$buildEvents .= get_the_post_thumbnail( $post->ID, 'thumbnail', array( 'class' => 'aligncenter' ) ); 
				$buildEvents .= '[txt]<h3>'.$post->post_title.'</h3>';
				$buildEvents .= '<p class="event-meta"><span class="tribe-event-date-start">'.tribe_get_start_date($post, false);
				if ( tribe_get_end_date($post, false) != tribe_get_start_date( $post, false ) ) $buildEvents .= ' to '.tribe_get_end_date($post, false);			
				if ( tribe_get_start_time($post) ) $buildEvents .= '<br/><span class="tribe-event-time-start">'.tribe_get_start_time($post) .' to '. tribe_get_end_time($post);			
				$buildEvents .= '</p>';
				if ( $excerpt == "true" ) $buildEvents.= '<p>'.$post->post_excerpt.'</p>';		
				$buildEvents .= '[/txt]';
				if ( $showBtn == "true" ) $buildEvents .= '[btn link="'.esc_url(get_the_permalink($post->ID)).'"]'.$btnText.'[/btn]';			
				$buildEvents .= '[/col]';
				$num++;
			}
		}
	
		if ( $buildEvents ) :
			$buildList = '[section name="'.$name.'" style="'.$style.'" width="'.$width.'" class="event-teasers"]';
			$buildList .= '[layout grid="'.$grid.'" valign="'.$valign.'"]';		
			$buildList .= $buildEvents;
			$buildList .= '[/layout][/section]';	
		endif;
		return do_shortcode($buildList);
	endif;
}	

/*--------------------------------------------------------------
# Plug-in Setup
--------------------------------------------------------------*/
add_filter( 'tribe_the_notices', 'bp_change_notice', 10, 1 );
function bp_change_notice( $html ) {
	if ( stristr( $html, 'There were no results found.' ) ) {
		$html = str_replace( 'There were no results found.', 'There are no events scheduled at this time.', $html );
	}
	return $html; 
}

// Display past events in reverse order in list view
add_filter( 'tribe_events_views_v2_view_list_template_vars', 'bp_reverse_chronological', 100 );
add_filter( 'tribe_events_views_v2_view_photo_template_vars', 'bp_reverse_chronological', 100 );
function bp_reverse_chronological( $template_vars ) { 
 	if ( ! empty( $template_vars['is_past'] ) ) $template_vars['events'] = array_reverse( $template_vars['events'] );
 
  	return $template_vars;
}
?>