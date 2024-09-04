<?php
/* Battle Plan Web Design Timeline
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Setup
# Setup Custom Timelines + Fields
# Shortcodes
--------------------------------------------------------------*/



/*--------------------------------------------------------------
# Setup
--------------------------------------------------------------*/



/*--------------------------------------------------------------
# Setup Custom Timelines + Fields
--------------------------------------------------------------*/ 
add_action( 'init', 'battleplan_registerTimelinePostType', 0 );
function battleplan_registerTimelinePostType() {
	register_post_type( 'timeline', array (
		'label'=>				__( 'timeline', 'battleplan' ),
		'labels'=>array(
			'name'=>			_x( 'Timeline', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>	_x( 'Timeline', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>				true,
		'publicly_queryable'=>	true,
		'exclude_from_search'=>	false,
		'show_in_nav_menus'=>	true,
		'supports'=>			array( 'title', 'editor', 'comments', 'author', 'excerpt', 'page-attributes', 'thumbnail', 'custom-fields', 'post-formats' ),
		'hierarchical'=>		false,
		'menu_position'=>		20,
		'menu_icon'=>			'dashicons-list-view',
		'has_archive'=>			true,
		'capability_type'=>		'post',
	));
	
	register_taxonomy( 'timeline-cats', array( 'timeline' ), array(
		'labels'=>array(
			'name'=>			_x( 'Timeline Categories', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Timeline Category', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>		true,
		'show_ui'=>				true,
        'show_admin_column'=>	true,
	));
	
	register_taxonomy( 'timeline-tags', array( 'timeline' ), array(
		'labels'=>array(
			'name'=>			_x( 'Timeline Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Timeline Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>		false,
		'show_ui'=>				true,
        'show_admin_column'=>	true,
	));
}

add_action('acf/init', 'battleplan_add_timeline_acf_fields');
function battleplan_add_timeline_acf_fields() {
	acf_add_local_field_group( array(
		'key' => 'group_timelines',
		'title' => 'Timelines',
		'fields' => array(
			array(
				'key' => 'field_timeline_date',
				'label' => 'Date',
				'name' => 'timeline_date',
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
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'timeline',
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

// Build the timeline 
add_shortcode( 'get-timeline', 'battleplan_getTimeline' );
function battleplan_getTimeline($atts, $content = null ) {
	$a = shortcode_atts( array( 'start'=>'oldest' ), $atts );
	
	$order = esc_attr($a['start']) == 'oldest' ? 'ASC' : 'DESC';
		
	$currYear = 0;
	$storyIndex = 0;
	$storySide = 'right';
	$buildTimeline = '';
	
	$args = [ 'post_type' => 'timeline', 'posts_per_page' => -1, 'orderby' => 'meta_value', 'meta_key' => 'timeline_date', 'order' => $order ];

	$timeline_query = new WP_Query($args);
 
	if ($timeline_query->have_posts()) : 
		$buildTimeline .= '<div class="timeline">';
			$buildTimeline .= '<div class="timeline-line"></div>';
			while ($timeline_query->have_posts()) : $timeline_query->the_post(); 
				$storyIndex++;
				$storySide = ($storySide === 'left') ? 'right' : 'left';
				$date = get_post_meta(get_the_ID(), 'timeline_date', true);
				$date_formatted = date('F j, Y', strtotime($date));
				$date_year = date('Y', strtotime($date));	
				if ( $date_year !== $currYear ) :
					$currYear = $date_year;	
					$buildTimeline .= '<div id="year-'.$currYear.'" class="timeline-year" data-timeline-section="'.$currYear.'"><div class="timeline-year-dot">'.$currYear.'</div></div>';	
				endif;
	
				if ( $storySide === "right" ) $buildTimeline .= '<div class="timeline-placeholder timeline-placeholder-'.$storySide.'"></div>';	
					
				$buildTimeline .= '<div id="timeline-story-'.get_the_ID().'" class="timeline-story timeline-story-'.$storySide.'" data-timeline-story="'.$storyIndex.'">';	
					$buildTimeline .= '<h2 class="timeline-story-headline">'.get_the_title().'</h2>';	
					$buildTimeline .= '<div class="timeline-arrow"></div>';
					if (has_post_thumbnail()) $buildTimeline .= '<div class="timeline-image">'.get_the_post_thumbnail(get_the_ID(), 'size-full').'</div>';
					$buildTimeline .= '<div class="timeline-story-content">'.get_the_content().'</div>';				
				$buildTimeline .= '</div>';	
	
				if ( $storySide === "left" ) $buildTimeline .= '<div class="timeline-placeholder timeline-placeholder-'.$storySide.'"></div>';	
			endwhile;
			wp_reset_postdata(); 
		$buildTimeline .= '</div>';	
	endif;
	
	return do_shortcode($buildTimeline);
}