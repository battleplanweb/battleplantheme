<?php 
/* Battle Plan Web Design Functions: Custom Post Types

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Register Custom Post Types
# Import Advanced Custom Fields

/*--------------------------------------------------------------
# Register Custom Post Types
--------------------------------------------------------------*/

add_action( 'init', 'battleplan_registerPostTypes', 0 );
function battleplan_registerPostTypes() {
	register_post_type( 'testimonials', array (
		'label'=>__( 'testimonials', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Testimonials', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Testimonial', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>true,
		'show_in_nav_menus'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-format-quote',
		'has_archive'=>true,
		'capability_type'=>'page',
	));
	register_post_type( 'galleries', array (
		'label'=>__( 'galleries', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Galleries', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Gallery', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields', 'comments' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-images-alt',
		'has_archive'=>true,
		'capability_type'=>'page',
	));
	register_taxonomy( 'gallery-type', array( 'galleries' ), array(
		'labels'=>array(
			'name'=>_x( 'Gallery Type', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Gallery Type', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>true,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
	wp_insert_term( 'Auto Generated', 'gallery-type' );	
	wp_insert_term( 'Shortcode', 'gallery-type' );
	register_taxonomy( 'gallery-tags', array( 'galleries' ), array(
		'labels'=>array(
			'name'=>_x( 'Gallery Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Gallery Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>false,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
	register_taxonomy( 'image-categories', array( 'attachment' ), array(
		'labels'=>array(
			'name'=>_x( 'Image Categories', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Image Category', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>true,
		'show_ui'=>true,        
		'query_var'=>true,
        'rewrite'=>true,
        'show_admin_column'=>true,
		'exclude_from_search'=>true,
		'show_in_nav_menus'=>false,
	));
	register_taxonomy( 'image-tags', array( 'attachment' ), array(
		'labels'=>array(
			'name'=>_x( 'Image Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Image Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>false,
		'show_ui'=>true,        
		'query_var'=>true,
        'rewrite'=>true,
        'show_admin_column'=>true,
		'exclude_from_search'=>true,
		'show_in_nav_menus'=>false,
	));
	register_post_type( 'landing', array (
		'label'=>__( 'landing', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Landing', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Landing', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'show_in_nav_menus'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-edit-page',
		'has_archive'=>false,
		'capability_type' => 'page',
		
		//'rewrite' => array('slug' => 'service-areas'), // This line changes the slug
	));
	register_post_type( 'elements', array (
		'label'=>__( 'elements', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Elements', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Element', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>false,
		'exclude_from_search'=>true,
		'show_in_nav_menus'=>false,
		'supports'=>array( 'title', 'editor' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-block-default', 
		'has_archive'=>false,
		'capability_type'=>'page',
	));
	register_post_type( 'universal', array (
		'label'=>__( 'universal', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Universal', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Universal', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'show_in_nav_menus'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-admin-site-alt3', 
		'has_archive'=>false,
		'capability_type' => 'page',
		'capabilities' => array(
			'create_posts' => false,
		),
		'map_meta_cap' => true,
	));
}

// Remove 'landing', 'universal', and 'elements' from the url so that pages look like regular pages
add_filter( 'post_type_link', 'battleplan_remove_cpt_slug', 10, 2 );
function battleplan_remove_cpt_slug( $post_link, $post ) {
	if ( 'universal' === $post->post_type || 'landing' === $post->post_type || 'elements' === $post->post_type ) $post_link = str_replace( '/' . $post->post_type . '/', '/', $post_link );
 	return $post_link;
}

// Add certain post types to the main query, along with pages and posts
add_action( 'pre_get_posts', 'battleplan_add_cpt_to_main_query' );
function battleplan_add_cpt_to_main_query( $query ) {
	if ( !$query->is_main_query() ) return;
	if ( !isset( $query->query['page'] ) || 2 !== count( $query->query ) ) return;
	if ( empty( $query->query['name'] ) ) return;
	$query->set( 'post_type', array( 'post', 'page', 'landing', 'universal', 'jobsite_geo' ) );
}

/*--------------------------------------------------------------
# Import Advanced Custom Fields
--------------------------------------------------------------*/
add_action('acf/init', 'battleplan_add_acf_fields');
function battleplan_add_acf_fields() {	
	acf_add_local_field_group(array(
		'key' => 'group_7df6f4843vdfg',
		'title' => 'Reference',
		'fields' => array(
			array(
				'key' => 'field_reference',
				'label' => 'Reference #',
				'name' => 'reference',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'post',
				),
			),
		),
		'menu_order' => 1,
		'position' => 'acf_after_title',
		'style' => 'seamless',
		'label_placement' => 'top', 
		'active' => true,
		'description' => '',
	));

	acf_add_local_field_group(array(
		'key' => 'group_5bd6f6743bbfe',
		'title' => 'Testimonials',
		'fields' => array(
			array(
				'key' => 'field_testimonial-rating',
				'label' => 'Rating',
				'name' => 'testimonial_rating',
				'type' => 'radio',
				'instructions' => 'Enter the testimonial\'s rating on a scale of 1 to 5.',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					0 => 'Unrated',
					1 => '1',
					2 => '2',
					3 => '3',
					4 => '4',
					5 => '5',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 0,
				'layout' => 'horizontal',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_testimonial-quality',
				'label' => '',
				'name' => 'testimonial_quality',
				'type' => 'checkbox',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					1 => 'Quality Review',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 0,
				'layout' => 'horizontal',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_platform',
				'label' => 'Platform',
				'name' => 'testimonial_platform',
				'type' => 'radio',
				'instructions' => 'What platform did this testimonial come from?',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'None' => 'None',
					'Google' => 'Google',
					'Facebook' => 'Facebook',
					'Yelp' => 'Yelp',
					'YP' => 'YP',
					'Jobber' => 'Jobber',
					'Nextdoor' => 'Nextdoor',
					'BBB' => 'BBB',
					'Angi' => 'Angi',
					'Home_advisor' => 'Home Advisor',
					'Houzz' => 'Houzz',
					'Fiverr' => 'Fiverr',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 0,
				'layout' => 'horizontal',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_location',
				'label' => 'Location',
				'name' => 'testimonial_location',
				'type' => 'text',
				'instructions' => 'Enter the location of the person giving the testimonial.',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_business-name',
				'label' => 'Business Name',
				'name' => 'testimonial_biz',
				'type' => 'text',
				'instructions' => 'Enter the business name of the person giving the testimonial.',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_business-website',
				'label' => 'Business Website',
				'name' => 'testimonial_website',
				'type' => 'text',
				'instructions' => 'Enter the website of the person giving the testimonial (include http:// or https://).',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'testimonials',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'seamless',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => array(
			0 => 'custom_fields',
			1 => 'discussion',
			2 => 'comments',
			3 => 'revisions',
			4 => 'slug',
			5 => 'author',
			6 => 'format',
			7 => 'categories',
			8 => 'tags',
			9 => 'send-trackbacks',
		),
		'active' => true,
		'description' => '',
	));
}