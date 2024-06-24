<?php
/* Battle Plan Web Design Pedigree Includes

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Register Custom Post Types
# Import Advanced Custom Fields
# Basic Theme Set Up
# AJAX Functions
# Grid Set Up


/*--------------------------------------------------------------
# Register Custom Post Types
--------------------------------------------------------------*/
add_action( 'init', 'battleplan_registerPedigreePostTypes', 0 );
function battleplan_registerPedigreePostTypes() {
	register_post_type( 'resources', array (
		'label'               => __( 'resources', 'battleplan' ),
		'labels'              => array(
			'name'                => _x( 'Resources', 'Post Type General Name', 'battleplan' ),
			'singular_name'       => _x( 'Resource', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'              => true,
		'publicly_queryable'  => true,
		'exclude_from_search' => false,
		'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
		'hierarchical'        => false,
		'menu_position'       => 20,
		'menu_icon'           => 'dashicons-paperclip',
		'has_archive'         => true,
		'capability_type'     => 'page',
	));
	register_post_type( 'dogs', array (
		'label'               => __( 'dogs', 'battleplan' ),
		'labels'              => array(
			'name'                => _x( 'Dogs', 'Post Type General Name', 'battleplan' ),
			'singular_name'       => _x( 'Dog', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'              => true,
		'publicly_queryable'  => true,
		'exclude_from_search' => false,
		'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
		'hierarchical'        => false,
		'menu_position'       => 20,
		'menu_icon'           => 'dashicons-exerpt-view',
		'has_archive'         => true,
		'capability_type'     => 'page',
	));
	register_taxonomy( 'dog-tags', array( 'dogs' ), array(
		'labels'=>array(
			'name'=>_x( 'Dog Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Dog Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>true,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
	wp_insert_term( 'featured', 'dog-tags' );
	register_post_type( 'litters', array (
		'label'               => __( 'litters', 'battleplan' ),
		'labels'              => array(
			'name'                => _x( 'Litters', 'Post Type General Name', 'battleplan' ),
			'singular_name'       => _x( 'Litter', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'              => true,
		'publicly_queryable'  => true,
		'exclude_from_search' => false,
		'supports'            => array( 'title', 'editor', 'excerpt' ),
		'hierarchical'        => false,
		'menu_position'       => 20,
		'menu_icon'           => 'dashicons-clock',
		'has_archive'         => true,
		'capability_type'     => 'page',
	));
}

/*--------------------------------------------------------------
# Import Advanced Custom Fields
--------------------------------------------------------------*/
add_action('acf/init', 'battleplan_add_acf_pedigree_fields');
function battleplan_add_acf_pedigree_fields() {
	acf_add_local_field_group(array(
		'key' => 'group_5bd2188631fd0',
		'title' => 'Dog Profiles',
		'fields' => array(
			array(
				'key' => 'field_52dd9b09d73d3',
				'label' => 'Call Name',
				'name' => 'call_name',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
			),
			array(
				'key' => 'field_5ce68ee020b9a',
				'label' => 'Sex',
				'name' => 'sex',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'Male' => 'Male',
					'Female' => 'Female',
					'Legacy' => 'Legacy',
				),
				'allow_null' => 0,
				'other_choice' => 0,
				'layout' => 'vertical',
				'return_format' => 'value',
				'save_other_choice' => 0,
			),
			array(
				'key' => 'field_5ce6a9337f294',
				'label' => 'Color',
				'name' => 'color',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'Black' => 'Black',
					'Yellow' => 'Yellow',
					'Chocolate' => 'Chocolate',
				),
				'allow_null' => 0,
				'other_choice' => 0,
				'layout' => 'vertical',
				'return_format' => 'value',
				'save_other_choice' => 0,
			),
			array(
				'key' => 'field_5ce8a7649j998',
				'label' => 'Hidden Genes',
				'name' => 'geno',
				'type' => 'checkbox',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
				    'Black' => 'Black',
					'Yellow' => 'Yellow',
					'Chocolate' => 'Chocolate',
				),
				'allow_null' => 0,
				'other_choice' => 0,
				'layout' => 'vertical',
				'return_format' => 'value',
				'save_other_choice' => 0,
			),
			array(
				'key' => 'field_58e4dd15724ff',
				'label' => 'Birth Date',
				'name' => 'birth_date',
				'type' => 'date_picker',
				'required' => 0,
				'conditional_logic' => 0,
				'display_format' => 'm/d/Y',
				'first_day' => 0,
				'return_format' => 'd/m/Y',
				'save_format' => 'yy-mm-dd',
			),
			array(
				'key' => 'field_52dd130479865',
				'label' => 'EIC',
				'name' => 'eic',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'Clear' => 'Clear',
					'Carrier' => 'Carrier',
					'NA' => 'NA',
				),
				'other_choice' => 1,
				'save_other_choice' => 0,
				'default_value' => 'NA',
				'layout' => 'vertical',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_52dd12f079864',
				'label' => 'CNM',
				'name' => 'cnm',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'Clear' => 'Clear',
					'White List' => 'White List',
					'NA' => 'NA',
				),
				'other_choice' => 1,
				'save_other_choice' => 0,
				'default_value' => 'NA',
				'layout' => 'vertical',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_52dd12bc79863',
				'label' => 'Hips (OFA)',
				'name' => 'hips',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'Excellent' => 'Excellent',
					'Good' => 'Good',
					'Normal' => 'Normal',
					'Pending' => 'Pending',
					'NA' => 'NA',
				),
				'allow_null' => 0,
				'other_choice' => 1,
				'save_other_choice' => 1,
				'default_value' => 'NA',
				'layout' => 'vertical',
				'return_format' => 'value',
			),
			array(
				'key' => 'field_52dd6a184e280',
				'label' => 'Elbows',
				'name' => 'elbows',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'Normal' => 'Normal',
					'Pending' => 'Pending',
					'NA' => 'NA',
				),
				'allow_null' => 0,
				'other_choice' => 1,
				'save_other_choice' => 1,
				'default_value' => 'NA',
				'layout' => 'vertical',
				'return_format' => 'value',
			),
			array(
				'key' => 'field_58e4dc0ad9240',
				'label' => 'Eyes',
				'name' => 'eyes',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'CERFed' => 'CERFed',
					'Clear' => 'Clear',
					'Normal' => 'Normal',
					'Pending' => 'Pending',
					'NA' => 'NA',
				),
				'allow_null' => 0,
				'other_choice' => 1,
				'save_other_choice' => 1,
				'default_value' => 'NA',
				'layout' => 'vertical',
				'return_format' => 'value',
			),
			array(
				'key' => 'field_74rg13k063578',
				'label' => 'PRA',
				'name' => 'pra',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'Clear' => 'Clear',
					'NA' => 'NA',
				),
				'other_choice' => 1,
				'save_other_choice' => 0,
				'default_value' => 'NA',
				'layout' => 'vertical',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_57c020ea9a063',
				'label' => 'Breeding Info',
				'name' => 'breeding_info',
				'type' => 'text',
				'instructions' => 'Jon Smith: (800) 111-2233',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd106579376',
				'label' => 'Sire',
				'name' => 'sire',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dfc086edc66',
				'label' => 'Dam',
				'name' => 'dam',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10b679378',
				'label' => 'Grandparent 1',
				'name' => 'grandparent_1',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10c979379',
				'label' => 'Grandparent 2',
				'name' => 'grandparent_2',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10cf7937a',
				'label' => 'Grandparent 3',
				'name' => 'grandparent_3',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10d57937b',
				'label' => 'Grandparent 4',
				'name' => 'grandparent_4',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10da7937c',
				'label' => 'Great Grandparent 1',
				'name' => 'great_grandparent_1',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10e47937d',
				'label' => 'Great Grandparent 2',
				'name' => 'great_grandparent_2',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10ea7937e',
				'label' => 'Great Grandparent 3',
				'name' => 'great_grandparent_3',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10f07937f',
				'label' => 'Great Grandparent 4',
				'name' => 'great_grandparent_4',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10f479380',
				'label' => 'Great Grandparent 5',
				'name' => 'great_grandparent_5',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10f879381',
				'label' => 'Great Grandparent 6',
				'name' => 'great_grandparent_6',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dd10fc79382',
				'label' => 'Great Grandparent 7',
				'name' => 'great_grandparent_7',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52dfc066d6483',
				'label' => 'Great Grandparent 8',
				'name' => 'great_grandparent_8',
				'type' => 'text',
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
					'value' => 'dogs',
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
	));

	acf_add_local_field_group(array(
		'key' => 'group_5bd21886429c7',
		'title' => 'Litter Pages',
		'fields' => array(
			array(
				'key' => 'field_52dd96224ea17',
				'label' => 'Sire',
				'name' => 'sire',
				'type' => 'post_object',
				'required' => 0,
				'conditional_logic' => 0,
				'post_type' => array(
					0 => 'dogs',
				),
				'allow_null' => 0,
				'multiple' => 0,
				'return_format' => 'object',
				'ui' => 1,
			),
			array(
				'key' => 'field_5fbd6ea6f7cf5',
				'label' => 'Sire (no link)',
				'name' => 'sire_no_link',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
			),
			array(
				'key' => 'field_52dd96514ea18',
				'label' => 'Dam',
				'name' => 'dam',
				'type' => 'post_object',
				'required' => 0,
				'conditional_logic' => 0,
				'post_type' => array(
					0 => 'dogs',
				),
				'allow_null' => 0,
				'multiple' => 0,
				'return_format' => 'object',
				'ui' => 1,
			),
			array(
				'key' => 'field_5fbd6ec8c5900',
				'label' => 'Dam (no link)',
				'name' => 'dam_no_link',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
			),
			array(
				'key' => 'field_5ce9329f30cb0',
				'label' => 'Litter Status',
				'name' => 'litter_status',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'Available' => 'Available',
					'Expecting' => 'Expecting',
				),
				'allow_null' => 0,
				'other_choice' => 0,
				'default_value' => '',
				'layout' => 'vertical',
				'return_format' => 'value',
				'save_other_choice' => 0,
			),
			array(
				'key' => 'field_52dd8f4f60233',
				'label' => 'Expecting / Birth Date',
				'name' => 'birth_date',
				'type' => 'date_picker',
				'required' => 0,
				'conditional_logic' => 0,
				'display_format' => 'F j, Y',
				'return_format' => 'F j, Y',
				'first_day' => 0,
			),
			array(
				'key' => 'field_52dd8f8e60235',
				'label' => 'Price',
				'name' => 'price',
				'type' => 'number',
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => 0,
			),
			array(
				'key' => 'field_52dd8f7a60234',
				'label' => 'Deposit',
				'name' => 'deposit',
				'type' => 'number',
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => 0,
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'litters',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'seamless',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
	));
}

/*--------------------------------------------------------------
# Basic Theme Set Up
--------------------------------------------------------------*/
add_action( 'pre_get_posts', 'battleplan_override_main_query_with_pedigree', 10 );
function battleplan_override_main_query_with_pedigree( $query ) {
	if (!is_admin() && $query->is_main_query()) :		
		if ( is_post_type_archive('dogs') ) :
			$query->set( 'post_type','dogs');
			$query->set( 'posts_per_page',-1);
			$query->set( 'orderby','name');
			$query->set( 'order','asc');
		endif;
		if ( is_post_type_archive('litters')) :
			$query->set( 'post_type','litters');
			$query->set( 'posts_per_page', -1);	
			$meta_query = array( array(
    			'status_clause' => array(
      				'key' => 'litter_status',
      				'compare' => 'EXISTS'
    			)), array(
    			'birth_date_clause' => array(
      				'key' => 'birth_date',
      				'compare' => 'EXISTS'
    			) )
			);
			$query->set('meta_query', $meta_query);
			$query->set('orderby', array('status_clause' => 'ASC', 'birth_date_clause' => 'ASC'));
		endif;
	endif; 
}

// Load and enqueue styles & scripts
add_action( 'wp_enqueue_scripts', 'battleplan_pedigree_scripts' );
function battleplan_pedigree_scripts() {
	wp_enqueue_style( 'battleplan-css-pedigree', get_template_directory_uri().'/style-pedigree.css', array(), _BP_VERSION );	 
	wp_enqueue_script( 'battleplan-script-pedigree', get_template_directory_uri().'/js/script-pedigree.js', array(), _BP_VERSION, true );
}

// Add call name to archives and random widgets
add_filter( 'bp_archive_filter_title', 'battleplan_add_callname', 10, 2 );
function battleplan_add_callname($archiveTitle, $archiveLink) {
	return '<h2>'.$archiveLink.'“'.esc_attr(get_field( "call_name" )).'”</a></h2>'.$archiveTitle;
}

/*--------------------------------------------------------------
# Grid Set Up
--------------------------------------------------------------*/
// Bracket Section
add_shortcode( 'bracket', 'battleplan_buildBracket' );
function battleplan_buildBracket( $atts, $content = null ) {
	$a = shortcode_atts( array( 'a1'=>'', 'a2'=>'', 'b1'=>'', 'b2'=>'', 'b3'=>'', 'b4'=>'', 'c1'=>'', 'c2'=>'', 'c3'=>'', 'c4'=>'', 'c5'=>'', 'c6'=>'', 'c7'=>'', 'c8'=>'' ), $atts );
	$a1 = esc_attr($a['a1']); $a2 = esc_attr($a['a2']); $b1 = esc_attr($a['b1']); $b2 = esc_attr($a['b2']); $b3 = esc_attr($a['b3']); $b4 = esc_attr($a['b4']); $c1 = esc_attr($a['c1']); $c2 = esc_attr($a['c2']); $c3 = esc_attr($a['c3']); $c4 = esc_attr($a['c4']); $c5 = esc_attr($a['c5']); $c6 = esc_attr($a['c6']); $c7 = esc_attr($a['c7']); $c8 = esc_attr($a['c8']);

	$buildBracket = '<section class="bracket-scroller"><table class="bracket-table">';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-blank">&nbsp;</td><td class="bracket-top">'.$c1.'</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-top">'.$b1.'</td><td class="bracket-fill">&nbsp;</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-fill">&nbsp;</td><td class="bracket-bottom">'.$c2.'</td></tr>';
	$buildBracket .= '<tr><td class="bracket-top">'.$a1.'</td><td class="bracket-fill">&nbsp;</td><td class="bracket-blank">&nbsp;</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-fill">&nbsp;</td><td class="bracket-top">'.$c3.'</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-bottom">'.$b2.'</td><td class="bracket-fill">&nbsp;</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-blank">&nbsp;</td><td class="bracket-bottom">'.$c4.'</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-blank">&nbsp;</td><td class="bracket-blank">&nbsp;</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-blank">&nbsp;</td><td class="bracket-top">'.$c5.'</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-top">'.$b3.'</td><td class="bracket-fill">&nbsp;</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-fill">&nbsp;</td><td class="bracket-bottom">'.$c6.'</td></tr>';
	$buildBracket .= '<tr><td class="bracket-top">'.$a2.'</td><td class="bracket-fill">&nbsp;</td><td class="bracket-blank">&nbsp;</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-fill">&nbsp;</td><td class="bracket-top">'.$c7.'</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-bottom">'.$b4.'</td><td class="bracket-fill">&nbsp;</td></tr>';
	$buildBracket .= '<tr><td class="bracket-blank">&nbsp;</td><td class="bracket-blank">&nbsp;</td><td class="bracket-bottom">&nbsp;'. $c8.'</td></tr>';
	$buildBracket .= '</table></section>';

	return $buildBracket;	
}	

//Establish default thumbnail size
update_option( 'thumbnail_size_w', 280 );
update_option( 'thumbnail_size_h', 255 );
update_option( 'thumbnail_crop', 1 );
?>