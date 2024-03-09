<?php
/* Battle Plan Web Design Jobsite GEO
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Admin
# Register Custom Post Types
# Setup Advanced Custom Fields
# Basic Site Setup
# Shortcodes
# Setup Re-directs
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Admin
--------------------------------------------------------------*/

// save important info to meta data upon publishing or updating post
add_action('save_post', 'battleplan_saveJobsite', 10, 3);
function battleplan_saveJobsite($post_id, $post, $update) {
    if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || ( defined('DOING_AJAX') && DOING_AJAX ) || !current_user_can('edit_post', $post_id) || get_post_type($post_id) != 'jobsite_geo') return;
	
	// retrieve lat & long from Google API and add as post meta	
	$address = esc_attr(get_field( "address" )).', '.esc_attr(get_field( "city" )).', '.esc_attr(get_field( "state" )).' '.esc_attr(get_field( "zip" ));
	$googleAPI = "https://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&key=AIzaSyBqf0idxwuOxaG-j3eCpef1Bunv-YVdVP8";	
	
    $response = wp_remote_get($googleAPI);
    if ( !is_wp_error($response)) :
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		if ($data['status'] == 'OK') update_post_meta($post_id, 'geocode', $data['results'][0]['geometry']['location']);
	endif;	
	
	// add city-state as location tag
    $locationTag = esc_attr(get_field("city")).'-'.esc_attr(get_field("state")); 
    $term = term_exists($locationTag, 'jobsite_geo-service-areas');
    if (empty($term)) $term = wp_insert_term($locationTag, 'jobsite_geo-service-areas');
    if (!is_wp_error($term)) wp_set_post_terms($post_id, $locationTag, 'jobsite_geo-service-areas');	
	
	// add username as technician tag
	$current_user = wp_get_current_user();
	if ( in_array( 'bp_jobsite_geo', $current_user->roles ) ) :
		$techTag = $current_user->user_firstname.'-'.$current_user->user_lastname; 
		$term = term_exists($techTag, 'jobsite_geo-techs');
		if (empty($term)) $term = wp_insert_term($techTag, 'jobsite_geo-techs');
		if (!is_wp_error($term)) wp_set_post_terms($post_id, $techTag, 'jobsite_geo-techs');	
	endif;

	// set first uploaded pic as jobsite thumbnail
	set_post_thumbnail($post_id, esc_attr(get_field( "jobsite_photo_1")) );	
}


/*--------------------------------------------------------------
# Register Custom Post Types
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
		'supports'=>			array( 'title', 'editor', 'thumbnail', 'custom-fields', 'post-formats' ),
		'hierarchical'=>		false,
		'menu_position'=>		20,
		'menu_icon'=>			'dashicons-location',
		'has_archive'=>			true,
		'capability_type' => 	'post',
		'capabilities' => array(
			'create_posts' => 	false,
		),
		'map_meta_cap' => true,        
		'rewrite' => array(
            'slug' => 			'jobsites',
            'with_front' => 	false,
        ),
	));
	
	register_taxonomy( 'jobsite_geo-services', array( 'jobsite_geo' ), array(
		'labels'=>array(
			'name'=>			_x( 'Services', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Service', 'Taxonomy Singular Name', 'text_domain' ),
        	'menu_name' => 		'Services',
		),
        'public' => 			true,
		'hierarchical'=>		false,
        'show_ui' => 			true,
        'show_in_menu' => 		true,
        'show_in_nav_menus' => 	true,
        'show_admin_column' => 	true,
        'rewrite' => array(
            'slug' => 			'service',
            'with_front' => 	false,
        ),
	));
	
	register_taxonomy( 'jobsite_geo-techs', array( 'jobsite_geo' ), array(
		'labels'=>array(
			'name'=>			_x( 'Technicians', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Technician', 'Taxonomy Singular Name', 'text_domain' ),
        	'menu_name' => 		'Technicians',
		),
        'public' => 			true,
		'hierarchical'=>		false,
        'show_ui' => 			true,
        'show_in_menu' => 		true,
        'show_in_nav_menus' => 	true,
        'show_admin_column' => 	true,
        'rewrite' => array(
            'slug' => 			'tech',
            'with_front' => 	false,
        ),
	));
	
	register_taxonomy( 'jobsite_geo-service-areas', array( 'jobsite_geo' ), array(
		'labels'=>array(
			'name'=>			_x( 'Service Areas', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Service Area', 'Taxonomy Singular Name', 'text_domain' ),
        	'menu_name' => 		'Service Areas',
		),
        'public' => 			true,
		'hierarchical'=>		false,
        'show_ui' => 			true,
        'show_in_menu' => 		true,
        'show_in_nav_menus' => 	true,
        'show_admin_column' => 	true,
        'rewrite' => array(
            'slug' => 			'service-area',
            'with_front' => 	false,
        ),
	));

	//wp_insert_term( 'upcoming', 'event-tags' );	
	//wp_insert_term( 'expired', 'event-tags' );	
	//wp_insert_term( 'featured', 'event-tags' );	
}
	
add_filter( 'register_post_type_args', 'battleplan_changeJobsiteGEOCaps' , 10, 2 );
function battleplan_changeJobsiteGEOCaps( $args, $post_type ) {
    if ( $post_type !== 'jobsite_geo'  ) return $args;

    $args['capabilities'] = array(
		'publish_posts'					=> 'publish_jobsites',
		'edit_posts'					=> 'edit_jobsites',
		'delete_posts'					=> 'delete_jobsites',
		'edit_others_posts'				=> 'edit_others_jobsites',
		'delete_others_posts'			=> 'delete_others_jobsites',
		'edit_published_posts'			=> 'edit_published_jobsites',
		'delete_published_posts'		=> 'delete_published_jobsites',
		'read_private_posts'			=> 'read_private_jobsites',
		'edit_private_posts'			=> 'edit_private_jobsites',
		'delete_private_posts'			=> 'delete_private_jobsites',
		'copy_posts'					=> 'copy_jobsites',
    );

    return $args;
}


/*--------------------------------------------------------------
# Setup Advanced Custom Fields
--------------------------------------------------------------*/
$media_library = get_option('jobsite_geo')['media_library'] == 'limited' ? 'uploadedTo' : 'all';
$default_state = get_option('jobsite_geo')['default_state'] != '' ? get_option('jobsite_geo')['default_state'] : '';

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
				'display_format' => 'Y-m-d',
				'return_format' => 'Y-m-d',
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
				'default_value' => $default_state,
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
				'key' => 'field_auto_make',
				'label' => 'Make',
				'name' => 'auto_make',
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
				'key' => 'field_auto_model',
				'label' => 'Model',
				'name' => 'auto_model',
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
				'key' => 'field_auto_year',
				'label' => 'Year',
				'name' => 'auto_year',
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
				'library' => $media_library,
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
				'library' => $media_library,
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
				'library' => $media_library,
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
				'library' => $media_library,
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
				'allow_null' => 1,
				'bidirectional' => 0,
				'ui' => 1,
				'bidirectional_target' => array(),
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
# Basic Site Setup
--------------------------------------------------------------*/

add_filter( 'body_class', function( $classes ) {
	if ( is_post_type_archive('jobsite_geo') || is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') || is_tax('jobsite_geo-techs') ) :
		$addClasses = "jobsite_geo";
		$classes = str_replace(array('sidebar-line', 'sidebar-right', 'sidebar-left'), 'sidebar-none', $classes);
	endif;
	return array_merge( $classes, array( $addClasses ) );
}, 30);

add_action( 'pre_get_posts', 'battleplan_override_jobsite_query', 10 );
function battleplan_override_jobsite_query( $query ) {
	if (!is_admin() && $query->is_main_query()) :		
		if ( is_post_type_archive('jobsite_geo') || is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') || is_tax('jobsite_geo-techs') ) : 
			$query->set( 'post_type','jobsite_geo');
			$query->set( 'posts_per_page', 10);
			$query->set( 'meta_key', 'job_date' );
        	$query->set( 'orderby', 'meta_value' );
        	$query->set( 'order', 'DESC');
		endif;
	endif; 
}

add_filter('template_include', 'battleplan_jobsite_template');
function battleplan_jobsite_template($template) {
    if ( is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') || is_tax('jobsite_geo-techs') ) :
	
		$template = get_template_directory().'/archive-jobsite_geo.php';
		$sep = ' · ';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $url = $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $parsedUrl = parse_url($url);
        $path = trim($parsedUrl['path'], '/');
        $pathSegments = explode('/', $path);
        $jobsite_type = isset($pathSegments[0]) ? $pathSegments[0] : null;
        $jobsite_spec = isset($pathSegments[1]) ? $pathSegments[1] : null;

		if ( is_tax('jobsite_geo-service-areas') ) :		
			$jobsite_spec = get_term_by('slug', $jobsite_spec, 'jobsite_geo-service-areas');
			$jobsite_spec = $jobsite_spec->name;
			$splitApart = explode('-', $jobsite_spec);
			$GLOBALS['jobsite_geo-city'] = ucwords($splitApart[0]);
			$GLOBALS['jobsite_geo-state'] = strtoupper($splitApart[1]);		
			$GLOBALS['jobsite_geo-map-caption'] = "This map shows some of our recent jobs in the ".$GLOBALS['jobsite_geo-city']." area.";
			$GLOBALS['jobsite_geo-bottom-headline'] = "Our HVAC Work In ".$GLOBALS['jobsite_geo-city'].", ".$GLOBALS['jobsite_geo-state'];

			$query = new WP_Query(array( 'post_type' => 'landing', 'posts_per_page' => 1, 'title' => $GLOBALS['jobsite_geo-city'].', '.$GLOBALS['jobsite_geo-state'], 'post_status' => 'publish', ));

			if($query->have_posts()) :
				while($query->have_posts()) :
					$query->the_post();
					$GLOBALS['jobsite_geo-content'] = get_the_content();
					$GLOBALS['jobsite_geo-page_title'] = str_replace('%%sep%%', $sep, get_post_meta(get_the_ID(), '_yoast_wpseo_title', true) );
					$GLOBALS['jobsite_geo-page_desc'] = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true);
					$GLOBALS['mapGrid'] = "1-1";
					$position = strpos($GLOBALS['jobsite_geo-page_title'], '·');
					if ($position !== false) $GLOBALS['jobsite_geo-headline'] = trim(substr($GLOBALS['jobsite_geo-page_title'], 0, $position));
				endwhile; 
			else:
				$default_args = array( 'post_type' => 'landing', 'posts_per_page' => 1, 'name' => 'jobsite-geo-default', 'post_status' => 'publish', );
				$default_query = new WP_Query($default_args);
				if ($default_query->have_posts()) :
					while($default_query->have_posts()) :
						$default_query->the_post();
						$GLOBALS['jobsite_geo-content'] = get_the_content();
						$GLOBALS['jobsite_geo-page_title'] = str_replace('%%sep%%', $sep, get_post_meta(get_the_ID(), '_yoast_wpseo_title', true) ).$sep.$GLOBALS['jobsite_geo-city'].", ".$GLOBALS['jobsite_geo-state'];
						$GLOBALS['jobsite_geo-page_desc'] = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true);
						$GLOBALS['mapGrid'] = "1-1";
						$position = strpos($GLOBALS['jobsite_geo-page_title'], '·');
						if ($position !== false) $GLOBALS['jobsite_geo-headline'] = trim(substr($GLOBALS['jobsite_geo-page_title'], 0, $position)).' in '.$GLOBALS['jobsite_geo-city'].", ".$GLOBALS['jobsite_geo-state'];
					endwhile; 
				else:
					$GLOBALS['jobsite_geo-content'] = '';
					$GLOBALS['mapGrid'] = "1";
				endif;
			endif;		
			wp_reset_postdata();			

		elseif ( is_tax('jobsite_geo-services') ) :		
            $jobsite_spec = get_term_by('slug', $jobsite_spec, 'jobsite_geo-services');
            $jobsite_spec = $jobsite_spec->name;

            $GLOBALS['jobsite_geo-headline'] = 'Recent '.$jobsite_spec.' Jobs';
            $GLOBALS['jobsite_geo-page_title'] = $GLOBALS['jobsite_geo-headline'].$sep.get_bloginfo('name');
			$GLOBALS['jobsite_geo-map-caption'] = 'This map shows the location of some of our recent '.$jobsite_spec.' jobs.';
			//$GLOBALS['jobsite_geo-bottom-headline'] = "Our Recent '.$jobsite_spec.' Work";
		elseif ( is_tax('jobsite_geo-techs') ) :		
            $jobsite_spec = get_term_by('slug', $jobsite_spec, 'jobsite_geo-techs');
            $jobsite_spec = $jobsite_spec->name;

            $GLOBALS['jobsite_geo-headline'] = $jobsite_spec.'\'s Recent Jobs';
            $GLOBALS['jobsite_geo-page_title'] = $GLOBALS['jobsite_geo-headline'].$sep.get_bloginfo('name');
			$GLOBALS['jobsite_geo-map-caption'] = 'This map shows the location of some of '.$jobsite_spec.'\'s recent work.';
		endif;
	endif;
	
    add_filter( 'wpseo_title', function( $title ) {
        return $GLOBALS['jobsite_geo-page_title'];
    });
	
    add_filter( 'wpseo_metadesc', function( $description ) {
        return $GLOBALS['jobsite_geo-page_desc'];
    });
	
    return $template;
}

// rename files for jobsite geo images
add_filter('wp_handle_upload_prefilter', 'battleplan_handle_jobsite_geo_image_upload');
function battleplan_handle_jobsite_geo_image_upload($file) {
	$current_user = wp_get_current_user();
	
	if ( isset($_REQUEST['post_id']) ) :
        $post_id = $_REQUEST['post_id'];
        $post = get_post($post_id);
	endif;

    if ( ($post && $post->post_type === 'jobsite_geo') || in_array('bp_jobsite_geo_mgr', $current_user->roles) || in_array('bp_jobsite_geo', $current_user->roles) ):
   		$file['name'] = 'jobsite_geo-'.$post_id.'--'. $file['name'];
    endif;	
	
    return $file;
};


/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

// Build SVG for icons
add_shortcode( 'get-jobsite', 'battleplan_getJobsiteCityState' );
function battleplan_getJobsiteCityState($atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);	

	if ( $type == 'city' ) return $GLOBALS['jobsite_geo-city'];
	if ( $type == 'state' ) return $GLOBALS['jobsite_geo-state'];
}

// Build archive page from multiple taxonomies
add_shortcode( 'build-jobsite-archive', 'battleplan_buildJobsiteArchive' );
function battleplan_buildJobsiteArchive($atts, $content = null ) {
	$a = shortcode_atts( array( 'services'=>'', 'areas'=>'' ), $atts );
	$services = preg_split('/,\s?/', esc_attr($a['services']));
	$areas = preg_split('/,\s?/', esc_attr($a['areas']));	

	$args = array(
		'post_type' => 'jobsite_geo', // Replace with your custom post type
		'posts_per_page' => -1, // Adjust as needed
		'tax_query' => array(
			'relation' => 'AND', // Important: Posts must match both taxonomies
			array(
				'taxonomy' => 'jobsite_geo-services',
				'field'    => 'slug',
				'terms'    => $services,
			),
			array(
				'taxonomy' => 'jobsite_geo-service-areas',
				'field'    => 'slug',
				'terms'    => $areas,
			),
		),
	);

	$query = new WP_Query($args);

	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			// Output your custom post type's content, such as the title or custom fields
			the_title('<h2>', '</h2>');
			the_excerpt();
		}
	} else {
		echo 'No posts found.';
	}

	// Reset post data to avoid conflicts
	wp_reset_postdata();
}

/*--------------------------------------------------------------
# Setup Re-directs
--------------------------------------------------------------*/
add_action('template_redirect', 'battleplan_jobsite_geo_intercept');
function battleplan_jobsite_geo_intercept() {
	$uri = trim($_SERVER['REQUEST_URI'], '/');
    $uri = explode('/', $uri);
    $uri_slug = $uri[0];
	
	$terms = get_terms(array( 'taxonomy' => 'jobsite_geo-service-areas', 'hide_empty' => false, ));
    if (!empty($terms) && !is_wp_error($terms)) :
        foreach ($terms as $term) :
            if ($term->slug === $uri_slug) :
                wp_redirect( home_url('/service-area/'.$uri_slug.'/'), 301 ); 
                exit;
            endif;
        endforeach;
    endif;
}

?>