<?php
/* Battle Plan Web Design Jobsite GEO
 
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
//add_action( 'wp_head', 'battleplan_createEventLog', 0 );
function battleplan_createEventLog() {	
	$eventData = array();
	$events = new WP_Query(array( 'post_type' => 'events', 'posts_per_page' => -1, ));

	if ($events->have_posts()) :
		while ($events->have_posts()) :
			$events->the_post();	
			$eventStart = strtotime(esc_attr(get_field( "start_date" )));			
			$eventEnd = esc_attr(get_field( "end_date" )) ? strtotime(esc_attr(get_field( "end_date" ))) : $eventStart;
			$days = (($eventEnd - $eventStart) / 86400) + 1;
			$tag = '';
			$eventTags = wp_get_post_terms(get_the_ID(), 'event-tags', array('field' => 'slug')); 
			$eventSlugs = wp_list_pluck($eventTags, 'slug');
			foreach ($eventSlugs as $eventSlug ) :
				$tag .= ' event-'.$eventSlug;
			endforeach;

			for ($i=0; $i<$days; $i++) :
				$eventDate = date('j, n, Y', $eventStart + ( 86400 * $i));
				$eventData[] =  array('date' => $eventDate, 'event'=>'<div class="event'.$tag.'"><a href="'. get_permalink().'">'.get_the_post_thumbnail(get_the_ID(), "thumbnail", array( 'class' => 'calendar-event-icon' )).'<span class="hide-3 hide-2 hide-1">'.get_the_title().'</span></a></div>' );
			endfor;
	
			if ( $eventEnd < time() ) wp_set_object_terms(get_the_ID(), 'expired', 'event-tags', false);			
			if ( $eventEnd < strtotime('-2 months') ) wp_update_post(array( 'ID' => get_the_ID(), 'post_status' => 'draft' ));
	
	  	endwhile;
	endif;
	
	wp_reset_postdata();
	

}




add_action('add_meta_boxes', 'battleplan_move_tag_meta_box');
function battleplan_move_tag_meta_box() {
    remove_meta_box('tagsdiv-jobsite_geo-services', 'jobsite_geo', 'side'); 
    add_meta_box('tagsdiv-jobsite_geo-services', 'Jobsite Services', 'post_tags_meta_box', 'jobsite_geo', 'normal', 'low'); 
	
	remove_meta_box('tagsdiv-jobsite_geo-techs', 'jobsite_geo', 'side');
    add_meta_box('tagsdiv-jobsite_geo-techs', 'Jobsite Technicians', 'post_tags_meta_box', 'jobsite_geo', 'normal', 'low'); 
}




/*--------------------------------------------------------------
# Setup Custom Events + Fields
--------------------------------------------------------------*/
add_action( 'init', 'battleplan_registerJobsiteGEOPostType', 0 );
function battleplan_registerJobsiteGEOPostType() {
	register_post_type( 'jobsite_geo', array (
		'label'=>				__( 'jobsite_geo', 'battleplan' ),
		'labels'=>array(
			'name'=>			_x( 'Jobsites', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>	_x( 'Jobsite', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>				true,
		'publicly_queryable'=>	true,
		'exclude_from_search'=>	false,
		'show_in_nav_menus'=>	true,
		'supports'=>			array( 'title', 'editor',  'thumbnail', 'custom-fields', 'post-formats' ),
		'hierarchical'=>		false,
		'menu_position'=>		20,
		'menu_icon'=>			'dashicons-location',
		'has_archive'=>			true,
		'capability_type'=>		'post',
	));
/*
	register_taxonomy( 'event-cats', array( 'events' ), array(
		'labels'=>array(
			'name'=>			_x( 'Event Categories', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Event Category', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>		true,
		'show_ui'=>			true,
        'show_admin_column'=>	true,
	));
	*/
	
	register_taxonomy( 'jobsite_geo-services', array( 'jobsite_geo' ), array(
		'labels'=>array(
			'name'=>			_x( 'Jobsite Services', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Jobsite Service', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>		false,
		'show_ui'=>				true,
        'show_admin_column'=>	true,
	));
	
	register_taxonomy( 'jobsite_geo-techs', array( 'jobsite_geo' ), array(
		'labels'=>array(
			'name'=>			_x( 'Jobsite Technicians', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Jobsite Technician', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>		false,
		'show_ui'=>				true,
        'show_admin_column'=>	true,
	));

	//wp_insert_term( 'upcoming', 'event-tags' );	
	//wp_insert_term( 'expired', 'event-tags' );	
	//wp_insert_term( 'featured', 'event-tags' );	
}










add_action('acf/init', 'battleplan_add_jobsite_geo_acf_fields');
function battleplan_add_jobsite_geo_acf_fields() {
	acf_add_local_field_group( array(
		'key' => 'group_jobsite_details',
		'title' => 'Jobsite Details',
		'fields' => array(				
			array(
				'key' => 'field_date',
				'label' => 'Date',
				'name' => 'job_date',
				'aria-label' => '',
				'type' => 'date_picker',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '15%',
					'class' => '',
					'id' => '',
				),
				'display_format' => 'F j, Y',
				'return_format' => 'F j, Y',
				'first_day' => 0,
			),	
			array(
				'key' => 'field_address',
				'label' => 'Address',
				'name' => 'address',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '45%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_city',
				'label' => 'City',
				'name' => 'city',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '23%',
					'class' => '',
					'id' => '',
				),
			),	
			array(
				'key' => 'field_state',
				'label' => 'State',
				'name' => 'state',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '7%',
					'class' => '',
					'id' => '',
				),
			),		
			array(
				'key' => 'field_zip',
				'label' => 'Zip Code',
				'name' => 'zip',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '10%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_old_brand',
				'label' => 'Current / Former Brand',
				'name' => 'old_brand',
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
				'key' => 'field_old_equipment',
				'label' => 'Current / Former Equipment',
				'name' => 'old_equipment',
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
				'key' => 'field_old_model_no',
				'label' => 'Current / Former Model #',
				'name' => 'old_model_no',
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
				'key' => 'field_new_brand',
				'label' => 'Replacement Brand',
				'name' => 'new_brand',
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
				'key' => 'field_new_equipment',
				'label' => 'Replacement Equipment',
				'name' => 'new_equipment',
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
				'key' => 'field_new_model_no',
				'label' => 'Replacement Model #',
				'name' => 'new_model_no',
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
				'key' => 'field_photo_1',
				'label' => 'Photo #1',
				'name' => 'jobsite_photo_1',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => 'uploadedTo',
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'preview_size' => 'third-s',
			),
			array(
				'key' => 'field_photo_2',
				'label' => 'Photo #2',
				'name' => 'jobsite_photo_2',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => 'uploadedTo',
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'preview_size' => 'third-s',
			),
			array(
				'key' => 'field_photo_3',
				'label' => 'Photo #3',
				'name' => 'jobsite_photo_3',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => 'uploadedTo',
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'preview_size' => 'third-s',
			),
			array(
				'key' => 'field_photo_4',
				'label' => 'Photo #4',
				'name' => 'jobsite_photo_4',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => 'uploadedTo',
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'preview_size' => 'third-s',
			),
			array(
				'key' => 'field_caption_1',
				'label' => 'Caption #1',
				'name' => 'jobsite_photo_1_alt',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_caption_2',
				'label' => 'Caption #2',
				'name' => 'jobsite_photo_2_alt',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_caption_3',
				'label' => 'Caption #3',
				'name' => 'jobsite_photo_3_alt',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_caption_4',
				'label' => 'Caption #4',
				'name' => 'jobsite_photo_4_alt',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_review_link',
				'label' => 'Link To Review',
				'name' => 'review',
				'aria-label' => '',
				'type' => 'post_object',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'post_type' => array(
					0 => 'testimonials',
				),
				'post_status' => array(
					0 => 'publish',
				),
				'taxonomy' => '',
				'return_format' => 'id',
				'multiple' => 0,
				'allow_null' => 0,
				'bidirectional' => 0,
				'ui' => 1,
				'bidirectional_target' => array(
				),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'jobsite_geo',
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

// Display teasers of upcoming events 
//add_shortcode( 'event_teasers', 'battleplan_event_teasers' );
function battleplan_event_teasers( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'1', 'width'=>'default', 'grid'=>'1-1-1', 'tag'=>'featured', 'max'=>'3', 'offset'=>'0', 'start'=>'today', 'end'=>'1 year', 'valign'=>'stretch', 'show_btn'=>'true', 'btn_text'=>'Read More', 'excerpt'=>'true' ), $atts );
	$start = date('Y-m-d', strtotime(esc_attr($a['start'])));	
	$end = date('Y-m-d', strtotime(esc_attr($a['end'])));
	$buildEvents = "";
	
	//$time = ($end - $start ) / 86400;
	//echo 'start'.$start.' end'.$end.' time'.$time;
	
	$events = new WP_Query(array( 'post_type' => 'events', 'posts_per_page' => esc_attr($a['max']), 'offset' => esc_attr($a['offset']), 'tax_query' => array( array( 'taxonomy' => 'event-tags', 'field' => 'slug', 'terms' => esc_attr($a['tag']))), 'meta_key' => 'start_date', 'orderby' => 'meta_value_num', 'order' => 'ASC', 'meta_query' => array( 'relation' => 'AND', array( 'key' => 'start_date', 'value' => $start, 'compare' => '>=', 'type' => 'DATE' ), array( 'key' => 'start_date', 'value' => $end, 'compare' => '<=', 'type' => 'DATE' ) )));

	if ($events->have_posts()) :
		while ($events->have_posts()) :	
			$events->the_post();
	
			$buildEvents .= '[col]';		
			$buildEvents .= get_the_post_thumbnail( get_the_ID(), 'thumbnail', array( 'class' => 'aligncenter' ) ); 
			$buildEvents .= '[txt]<h3>'.get_the_title().'</h3>';
			$buildEvents .= include('wp-content/themes/battleplantheme/elements/element-events-meta.php');	
			if ( esc_attr($a['excerpt']) == "true" ) $buildEvents.= '<p>'.get_the_excerpt().'</p>';		
			$buildEvents .= '[/txt]';
			if ( esc_attr($a['show_btn']) == "true" ) $buildEvents .= '[btn link="'.esc_url(get_the_permalink($post->ID)).'"]'.esc_attr($a['btn_text']).'[/btn]';			
			$buildEvents .= '[/col]';
			$num++;	
	  	endwhile;
	endif;
	
	wp_reset_postdata();

	if ( $buildEvents ) :
		$buildList = '[section name="'.esc_attr($a['name']).'" style="'.esc_attr($a['style']).'" width="'.esc_attr($a['width']).'" class="event-teasers"]';
		$buildList .= '[layout grid="'.esc_attr($a['grid']).'" valign="'.esc_attr($a['valign']).'"]';		
		$buildList .= $buildEvents;
		$buildList .= '[/layout][/section]';	
	endif;
	return do_shortcode($buildList);
}
?>