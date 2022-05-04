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
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'1', 'width'=>'default', 'grid'=>'1-1-1', 'tag'=>'featured', 'max'=>'3', 'start'=>'today', 'end'=>'', 'valign'=>'stretch', 'show_btn'=>'true', 'btn_text'=>'Read More' ), $atts );
	$name = esc_attr($a['name']);
	$style = esc_attr($a['style']);
	$width = esc_attr($a['cat']);
	$grid = esc_attr($a['grid']);
	$tag = esc_attr($a['tag']);
	$num = 0;
	$max = esc_attr($a['max']);
	$get = $max * 3;
	$cutoff = esc_attr($a['start']);	
	$start = strtotime($cutoff."-7 days");
	$end = esc_attr($a['end']);
	$valign = esc_attr($a['valign']);
	$showBtn = esc_attr($a['show_btn']);
	$btnText = esc_attr($a['btn_text']);
	$buildEvents = "";
	
	$events = tribe_get_events( [ 'start_date' => $start, 'end_date' => $end, 'eventDisplay' => 'list', 'posts_per_page' => $get, 'tag' => $tag] );
	
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
				$buildEvents .= '</p><p>'.$post->post_excerpt.'</p>';		
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
add_filter( 'tribe_the_notices', 'change_notice', 10, 1 );
function change_notice( $html ) {
	if ( stristr( $html, 'There were no results found.' ) ) {
		$html = str_replace( 'There were no results found.', 'There are no events scheduled at this time.', $html );
	}
	return $html; 
}

/*--------------------------------------------------------------
# Set Up Admin Columns
--------------------------------------------------------------*/
add_action( 'ac/ready', 'battleplan_event_column_settings' );
function battleplan_event_column_settings() {
	if (function_exists('ac_get_site_url')) {
		ac_register_columns( 'tribe_events', array(
			array(
				'columns'=>array(
					'featured-image'=>array(
						'type'=>'column-featured_image',
						'label'=>'',
						'width'=>'80',
						'width_unit'=>'px',
						'featured_image_display'=>'image',
						'image_size'=>'icon',
						'image_size_w'=>'60',
						'image_size_h'=>'60',
						'edit'=>'off',
						'sort'=>'off',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'featured-image',
						'label_type'=>'',
						'search'=>'on'
					),
					'title'=>array(
						'type'=>'title',
						'label'=>'Title',
						'width'=>'200',
						'width_unit'=>'px',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'title',
						'label_type'=>'',
						'search'=>'on'
					),		
					'column-slug'=>array(
						'type'=>'column-slug',

						'label'=>'Slug',
						'width'=>'15',
						'width_unit'=>'%',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'column-slug',
						'label_type'=>'',
						'search'=>'on'
					),
					'post-id'=>array(
						'type'=>'column-postid',
						'label'=>'ID',
						'width'=>'100',
						'width_unit'=>'px',
						'before'=>'',
						'after'=>'',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'post-id',
						'label_type'=>'',
						'search'=>'on'
					),
					'start-date'=>array(
						'type'=>'start-date',
						'label'=>'Start Date',
						'width'=>'',
						'width_unit'=>'%',
						'date_format'=>'wp_default',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'start-date',
						'label_type'=>'',
						'search'=>'on'
					),		
					'end-date'=>array(
						'type'=>'end-date',
						'label'=>'End Date',
						'width'=>'',
						'width_unit'=>'%',
						'date_format'=>'wp_default',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'filter_format'=>'monthly',
						'name'=>'end-date',
						'label_type'=>'',
						'search'=>'on'
					),				
					'recurring'=>array(
						'type'=>'recurring',
						'label'=>'Recurring',
						'width'=>'',
						'width_unit'=>'%',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'end-date',
						'label_type'=>'',
						'search'=>'on'
					),					
					'events-cats'=>array(
						'type'=>'events-cats',
						'label'=>'Categories',
						'width'=>'',
						'width_unit'=>'%',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'events-cats',
						'label_type'=>'',
						'search'=>'on'
					),							
					'tags'=>array(
						'type'=>'tags',
						'label'=>'Tags',
						'width'=>'',
						'width_unit'=>'%',
						'edit'=>'off',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'tags',
						'label_type'=>'',
						'search'=>'on'
					),	
					'author'=>array(
						'type'=>'author',
						'label'=>'Author',
						'width'=>'',
						'width_unit'=>'%',
						'edit'=>'on',
						'sort'=>'on',
						'name'=>'author',
						'label_type'=>'',
						'search'=>'on'
					)
				),
				'layout'=>array(
					'id'=>'battleplan-tribe_events-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );
	}
}
?>