<?php
/* Battle Plan Web Design Event Calendar
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Setup
# Setup Custom Events + Fields
# Shortcodes
--------------------------------------------------------------*/



/*--------------------------------------------------------------
# Setup
--------------------------------------------------------------*/
// Set up javascript param with all events + expire old events
add_action( 'wp_head', 'battleplan_createEventLog', 0 );
function battleplan_createEventLog() {	
	$eventData = array();
	$events = new WP_Query(array( 'post_type' => 'events', 'posts_per_page' => -1, ));

	if ($events->have_posts()) :
		while ($events->have_posts()) :
			$events->the_post();	
			$eventStart = strtotime(esc_attr(get_field( "start_date" )));			
			$eventEnd = esc_attr(get_field( "end_date" )) ? strtotime(esc_attr(get_field( "end_date" ))) : $eventStart;
			$days = (($eventEnd - $eventStart) / 86400) + 1;
	
			for ($i=0; $i<$days; $i++) :
				$eventDate = date('j, n, Y', $eventStart + ( 86400 * $i));
				$eventData[] =  array('date' => $eventDate, 'event'=>'<div class="event"><a href="'. get_permalink().'">'.get_the_post_thumbnail(get_the_ID(), "thumbnail", array( 'class' => 'calendar-event-icon' )).'<span class="hide-3 hide-2 hide-1">'.get_the_title().'</span></a></div>' );
			endfor;
	
			if ( $eventEnd < time() ) wp_set_object_terms(get_the_ID(), 'expired', 'event-tags', false);			
			if ( $eventEnd < strtotime('-2 months') ) wp_update_post(array( 'ID' => get_the_ID(), 'post_status' => 'draft' ));
	
	  	endwhile;
	endif;
	
	wp_reset_postdata();
	
	$abbr_days = get_option('event_calendar')['abbr_days'] == true ? true : false;
	
	?><script nonce="<?php echo _BP_NONCE; ?>">var eventLog = <?php echo json_encode($eventData); ?>; var abbr_days = <?php echo $abbr_days; ?>;</script><?php
}


/*--------------------------------------------------------------
# Setup Custom Events + Fields
--------------------------------------------------------------*/
add_action( 'init', 'battleplan_registerEventPostType', 0 );
function battleplan_registerEventPostType() {
	register_post_type( 'events', array (
		'label'=>				__( 'events', 'battleplan' ),
		'labels'=>array(
			'name'=>			_x( 'Events', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>	_x( 'Event', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>			true,
		'publicly_queryable'=>	true,
		'exclude_from_search'=>	false,
		'show_in_nav_menus'=>	true,
		'supports'=>			array( 'title', 'editor', 'comments', 'author', 'excerpt', 'page-attributes', 'thumbnail', 'custom-fields', 'post-formats' ),
		'hierarchical'=>		false,
		'menu_position'=>		20,
		'menu_icon'=>			'dashicons-calendar-alt',
		'has_archive'=>		true,
		'capability_type'=>		'post',
	));
	
	register_taxonomy( 'event-tags', array( 'events' ), array(
		'labels'=>array(
			'name'=>			_x( 'Event Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Event Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>		false,
		'show_ui'=>			true,
        	'show_admin_column'=>	true,
	));
	
	wp_insert_term( 'upcoming', 'event-tags' );	
	wp_insert_term( 'expired', 'event-tags' );	
	wp_insert_term( 'featured', 'event-tags' );	
}

add_action('acf/init', 'battleplan_add_event_acf_fields');
function battleplan_add_event_acf_fields() {
	acf_add_local_field_group( array(
		'key' => 'group_6478d57ca3a2e',
		'title' => 'Events',
		'fields' => array(
			array(
				'key' => 'field_6478d5780be98',
				'label' => 'Start Date',
				'name' => 'start_date',
				'aria-label' => '',
				'type' => 'date_picker',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '25%',
					'class' => '',
					'id' => '',
				),
				'display_format' => 'F j, Y',
				'return_format' => 'F j, Y',
				'first_day' => 0,
			),
			array(
				'key' => 'field_6478d5ed02ec9',
				'label' => 'End Date',
				'name' => 'end_date',
				'aria-label' => '',
				'type' => 'date_picker',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '25%',
					'class' => '',
					'id' => '',
				),
				'display_format' => 'F j, Y',
				'return_format' => 'F j, Y',
				'first_day' => 0,
			),
			array(
				'key' => 'field_6478d5c50be99',
				'label' => 'Start Time',
				'name' => 'start_time',
				'aria-label' => '',
				'type' => 'time_picker',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '25%',
					'class' => '',
					'id' => '',
				),
				'display_format' => 'g:ia',
				'return_format' => 'g:ia',
			),
			array(
				'key' => 'field_6478d5ff02eca',
				'label' => 'End Time',
				'name' => 'end_time',
				'aria-label' => '',
				'type' => 'time_picker',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '25%',
					'class' => '',
					'id' => '',
				),
				'display_format' => 'g:ia',
				'return_format' => 'g:ia',
			),
			array(
				'key' => 'field_6478d5ds03gwk',
				'label' => 'Venue',
				'name' => 'venue',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_6478d5eg35acx',
				'label' => 'Location',
				'name' => 'location',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_6478d5hr36erd',
				'label' => 'Venue Link',
				'name' => 'venue_link',
				'aria-label' => '',
				'type' => 'url',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'events',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));
}


/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

// Build the event calendar
add_shortcode( 'get-event-calendar', 'battleplan_getEventCalendar' );
function battleplan_getEventCalendar($atts, $content = null ) {
	//$a = shortcode_atts( array( 'info' => 'name', ), $atts );
	//$info = esc_attr($a['info']);
	
	$buildCalendar = '<div id="calendar"></div>';
	$buildCalendar .= '<div class="calendar-buttons">';
	$buildCalendar .= '<button id="prevButton" aria-label="Previous Month"><span class="sr-only">Previous Month</span></button>';
	$buildCalendar .= '<button id="currentButton">Return To Today</button>';
	$buildCalendar .= '<button id="nextButton" aria-label="Next Month"><span class="sr-only">Next Month</span></button>';
	$buildCalendar .= '</div>';
	
	echo $buildCalendar;
}







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
?>