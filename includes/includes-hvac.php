<?php
/* Battle Plan Web Design - HVAC Includes
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Product Overview
# American Standard Customer Care
# Ruud Pro Partner
# Why Choose Us?
# HVAC Maintenance Tips Teaser
# HVAC Tip Of The Month
# Register Custom Post Types
# Import Advanced Custom Fields
# Set Up Admin Columns
# Widgets
	- Brand Logo
	- Symptom Checker
	- Customer Care Dealer
	- Financing widget
	- Wells Fargo
# Basic Theme Set Up

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Product Overview
--------------------------------------------------------------*/
add_shortcode( 'product-overview', 'battleplan_product_overview' );
function battleplan_product_overview( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	
	if (strpos($type, 'american standard') !== false) { include("wp-content/themes/battleplantheme/includes/includes-american-standard-product-overview.php"); }
	elseif (strpos($type, 'ruud') !== false) { include("wp-content/themes/battleplantheme/includes/includes-ruud-product-overview.php"); }
	elseif (strpos($type, 'carrier') !== false) { include("wp-content/themes/battleplantheme/includes/includes-carrier-product-overview.php"); }	
	elseif (strpos($type, 'york') !== false) { include("wp-content/themes/battleplantheme/includes/includes-york-product-overview.php"); }
	elseif (strpos($type, 'lennox') !== false) { include("wp-content/themes/battleplantheme/includes/includes-lennox-product-overview.php"); }
	else { include("wp-content/themes/battleplantheme/includes/includes-generic-product-overview.php"); }
	
	return do_shortcode('
		[col class="col-archive col-products"]
		 [img size="1/3" link="'.$link.'" ada-hidden="true"]<img class="img-archive img-products" src="/wp-content/uploads/'.$pic.'" alt="'.$alt.'" />[/img]
		 [group size="2/3"]
		  [txt size="100" class="text-products"]<h3><a class="link-archive link-products" href="'.$link.'" aria-hidden="true" tabindex="-1">'.$title.'</a></h3>'.$excerpt.'[/txt]
		  [btn size="100" class="button-products" link="'.$link.'"]View '.$title.'[/btn]
		 [/group]
		[/col]
	');	
}


/*--------------------------------------------------------------
# American Standard Customer Care
--------------------------------------------------------------*/
add_shortcode( 'american-standard-customer-care', 'battleplan_american_standard_customer_care' );
function battleplan_american_standard_customer_care( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);

	return include "wp-content/themes/battleplantheme/includes/includes-customer-care-dealer.php";
}	

/*--------------------------------------------------------------
# Ruud Pro Partner
--------------------------------------------------------------*/
add_shortcode( 'ruud-pro-partner', 'battleplan_ruud_pro_partner' );
function battleplan_ruud_pro_partner( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);

	return include "wp-content/themes/battleplantheme/includes/includes-ruud-pro-partner.php";
}				

/*--------------------------------------------------------------
# Why Choose Us?
--------------------------------------------------------------*/
add_shortcode( 'why-choose-as', 'battleplan_why_choose_us' );
add_shortcode( 'why-choose-us', 'battleplan_why_choose_us' );
function battleplan_why_choose_us( $atts, $content = null ) {
	$a = shortcode_atts( array( 'brand'=>'', 'style'=>'1', 'width'=>'stretch', 'img'=>'', 'alt'=>'' ), $atts );	
	$brand = esc_attr($a['brand']);
	$name = ucwords($brand);
	$style = esc_attr($a['style']);
	$width = esc_attr($a['width']);	
	$img = esc_attr($a['img']);
	$alt = esc_attr($a['alt']);
	if ( $brand == '' ) :
		$brand = strtolower(str_replace(" ", "-", get_option('site_brand')));
		$name = ucwords($brand);
	endif;
	if ( $alt == '' ) $alt='We are proud to be a dealer of '.$name.', offering the top rated HVAC products on the market.';
	
	if ( $img == "grey" ) : $img = "/wp-content/themes/battleplantheme/common/hvac-".$brand."/why-choose-".$brand."-logo-grey.png";
	elseif ( $img == "white" ) : $img = "/wp-content/themes/battleplantheme/common/hvac-".$brand."/why-choose-".$brand."-logo-white.png";
	elseif ( $img == "" ) :	$img = "/wp-content/themes/battleplantheme/common/hvac-".$brand."/why-choose-".$brand."-logo.png";
	else: $img = "/wp-content/uploads/".$img;
	endif;
	
	return include "wp-content/themes/battleplantheme/includes/includes-why-choose-".$brand.".php";
}		
	
/*--------------------------------------------------------------
# HVAC Maintenance Tips 
--------------------------------------------------------------*/
add_shortcode( 'hvac-maintenance-tips', 'battleplan_hvac_maintenance_tips' );
function battleplan_hvac_maintenance_tips( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	
	return include "wp-content/themes/battleplantheme/includes/includes-hvac-maintenance-tips.php";
}		
	
/*--------------------------------------------------------------
# HVAC Tip Of The Month
--------------------------------------------------------------*/
add_shortcode( 'hvac-tip-of-the-month', 'battleplan_hvac_tip_of_the_month' );
function battleplan_hvac_tip_of_the_month( $atts, $content = null ) {
	return include "wp-content/themes/battleplantheme/includes/includes-hvac-tip-of-the-month.php";
}

/*--------------------------------------------------------------
# Register Custom Post Types
--------------------------------------------------------------*/
add_action( 'init', 'battleplan_registerHVACPostTypes', 0 );
function battleplan_registerHVACPostTypes() {
	register_post_type( 'products', array (
		'label'=>__( 'products', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Products', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Product', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'supports'=>array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields', 'author' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-cart',
		'has_archive'=>true,
		'capability_type'=>'post',
	));
	register_taxonomy( 'product-brand', array( 'products' ), array(
		'labels'=>array(
			'name'=>_x( 'Product Brands', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Product Brand', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>false,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
	register_taxonomy( 'product-type', array( 'products' ), array(
		'labels'=>array(
			'name'=>_x( 'Product Types', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Product Type', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>false,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
	register_taxonomy( 'product-class', array( 'products' ), array(
		'labels'=>array(
			'name'=>_x( 'Product Classes', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Product Class', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>false,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
}

/*--------------------------------------------------------------
# Import Advanced Custom Fields
--------------------------------------------------------------*/
add_action('acf/init', 'battleplan_add_acf_hvac_fields');
function battleplan_add_acf_hvac_fields() {
	acf_add_local_field_group(array(
		'key' => 'group_5bd6f6742fbdb',
		'title' => 'American Standard Products',
		'fields' => array(
			array(
				'key' => 'field_55352a1d450ac',
				'label' => 'Comfort',
				'name' => 'comfort',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					5 => '5',
					4 => '4',
					3 => '3',
					2 => '2',
					1 => '1',
					'na' => 'n/a',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 'na',
				'layout' => 'vertical',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_55352a47450ad',
				'label' => 'Efficiency',
				'name' => 'efficiency',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					5 => '5',
					4 => '4',
					3 => '3',
					2 => '2',
					1 => '1',
					'na' => 'n/a',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 'na',
				'layout' => 'vertical',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_55352a56450ae',
				'label' => 'Price',
				'name' => 'price',
				'type' => 'radio',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					5 => '5',
					4 => '4',
					3 => '3',
					2 => '2',
					1 => '1',
					'na' => 'n/a',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 'na',
				'layout' => 'vertical',
				'allow_null' => 0,
				'return_format' => 'value',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_taxonomy',
					'operator' => '==',
					'value' => 'product-brand:american-standard',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'seamless',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => array(
		),
		'active' => true,
		'description' => '',
	));
}


/*--------------------------------------------------------------
# Set Up Admin Columns
--------------------------------------------------------------*/
add_action( 'ac/ready', 'battleplan_hvac_column_settings' );
function battleplan_hvac_column_settings() {
	if (function_exists('ac_get_site_url')) {
		ac_register_columns( 'products', array(
			array(
				'columns'=>array(
					'featured-image'=>array(
						'type'=>'column-featured_image',
						'label'=>'',
						'width'=>'80',
						'width_unit'=>'px',
						'featured_image_display'=>'image',
						'image_size'=>'cpac-custom',
						'image_size_w'=>'60',
						'image_size_h'=>'60',
						'edit'=>'off',
						'sort'=>'off',
						'filter'=>'off',
						'filter_label'=>'',
						'name'=>'featured-image',
						'label_type'=>'',
						'search'=>''
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
						'search'=>''
					),
					'slug'=>array(
						'type'=>'column-slug',
						'label'=>'Slug',
						'width'=>'15',
						'width_unit'=>'%',
						'edit'=>'off',
						'sort'=>'on',
						'name'=>'slug',
						'label_type'=>'',
						'search'=>''
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
					'product-brand'=>array(
						'type'=>'column-taxonomy',
						'label'=>'Product Brand',
						'width'=>'',
						'width_unit'=>'%',
						'taxonomy'=>'product-brand',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'product-brand',
						'label_type'=>'',
						'search'=>''
					),
					'product-type'=>array(
						'type'=>'column-taxonomy',
						'label'=>'Product Type',
						'width'=>'',
						'width_unit'=>'%',
						'taxonomy'=>'product-type',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'product-type',
						'label_type'=>'',
						'search'=>''
					),
					'product-class'=>array(
						'type'=>'column-taxonomy',
						'label'=>'Product Class',
						'width'=>'',
						'width_unit'=>'%',
						'taxonomy'=>'product-class',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'product-class',
						'label_type'=>'',
						'search'=>''
					),
					'menu-order'=>array(
						'type'=>'column-order',
						'label'=>'Order',
						'width'=>'',
						'width_unit'=>'%',
						'edit'=>'on',
						'enable_term_creation'=>'on',
						'sort'=>'on',
						'filter'=>'on',
						'filter_label'=>'',
						'name'=>'menu-order',
						'label_type'=>'',
						'search'=>''
					),
				),
				'layout'=>array(
					'id'=>'battleplan-products-main',
					'name'=>'Main View',
					'roles'=>false,
					'users'=>false,
					'read_only'=>false
				)			
			)
		) );
	}
}

/*--------------------------------------------------------------
# Widgets
--------------------------------------------------------------*/

// Add Brand Logo widget to Sidebar
add_shortcode( 'get-brand-logo', 'battleplan_getBrandLogo' );
function battleplan_getBrandLogo($atts, $content = null) {
	$a = shortcode_atts( array( 'alt'=>'', 'brand'=>'' ), $atts );
	$alt = esc_attr($a['alt']);
	$brand = esc_attr($a['brand']);
	$name = ucwords($brand);
	if ( $alt != '' ) $alt="-".$alt;
	if ( $brand == '' ) :
		$brand = strtolower(str_replace(" ", "-", get_option('site_brand')));
		$name = ucwords($brand);
	endif;
	return '<img class="noFX" src="/wp-content/themes/battleplantheme/common/hvac-'.$brand.'/'.$brand.'-sidebar-logo'.$alt.'.png" alt="We offer '.$name.' heating and air conditioning products." />';
}

// Add Symptom Checker widget to Sidebar
add_shortcode( 'get-symptom-checker', 'battleplan_getSymptomChecker' );
function battleplan_getSymptomChecker() {	
	$brand = strtolower(str_replace(" ", "-", get_option('site_brand')));
	if ( $brand == "" ) $brand = "american-standard";
	return '<a href="/symptom-checker/" title="Click here for troublshooting ideas to solve common HVAC problems."><img class="noFX" src="/wp-content/themes/battleplantheme/common/hvac-'.$brand.'/symptom-checker.jpg" alt="HVAC unit pictured on colorful background." width="300" height="250"/></a>';
}

// Add Customer Care Dealer widget to Sidebar
add_shortcode( 'get-customer-care', 'battleplan_getCustomerCare' );
function battleplan_getCustomerCare() {	
	return '<a href="/customer-care-dealer/" title="Click here to read more about the American Standard Heating & Cooling Customer Care Dealer program"><img class="noFX" src="/wp-content/themes/battleplantheme/common/hvac-american-standard/customer-care-dealer-logo-alt.png" alt="We are proud to be an American Standard Customer Care Dealer" /></a>';
}

// Add Financing widget to Sidebar
add_shortcode( 'get-financing', 'battleplan_getFinancing' );
function battleplan_getFinancing($atts, $content = null) {
	$a = shortcode_atts( array( 'bank'=>'', 'link'=>''  ), $atts );
	$bank = esc_attr($a['bank']);
	$img = strtolower(str_replace(" ", "-", $bank));
	$link = esc_attr($a['link']);	
	$buildFinancing = "";
	
	if ( $link != "" ) $buildFinancing .= '<a href="'.$link.'" title="Click here to apply for financing for AC repair at '.$bank.'">';
	$buildFinancing .= '<img src="/wp-content/themes/battleplantheme/common/financing/'.$img.'.png" alt="Apply for financing for your HVAC needs at '.$bank.'" />';
	if ( $link != "" ) $buildFinancing .= '</a>';
	
	return $buildFinancing;
}

// Add Wells Fargo widget to Sidebar
add_shortcode( 'get-wells-fargo', 'battleplan_getWellsFargo' );
function battleplan_getWellsFargo($atts, $content = null) {
	$a = shortcode_atts( array( 'graphic1'=>'', 'graphic2'=>'', 'link'=>'', 'class'=>''  ), $atts );
	$graphic1 = esc_attr($a['graphic1']);	
	$graphic2 = esc_attr($a['graphic2']);	
	$link = esc_attr($a['link']);	
	$class = esc_attr($a['class']);	
	if ( $class != '' ) : $class = 'class="'.$class.'"'; endif;
	$rand = rand(1,2);
	if ($rand == "1") : $ad = $graphic1; endif;
	if ($rand == "2") : $ad = $graphic2; endif;
	if ($ad=="Wells-Fargo-A.png" || $ad=="Wells-Fargo-B.png") $alt = "Looking for financing options? Special financing available. This credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more.";
	if ($ad=="Wells-Fargo-C.png" || $ad=="Wells-Fargo-D.png") $alt = "Special financing available. This credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more.";	
	if ($ad=="Wells-Fargo-E.png") $alt = "Financing available through Wells Fargo Bank, NA. This credit card is issued with approved credit.  Equal Housing Lender.";		
	if ($ad=="Wells-Fargo-Splash-A.png" || $ad=="Wells-Fargo-Splash-B.png" || $ad=="Wells-Fargo-Splash-C.png") $alt = "Buy today, pay over time. This credit card also brings you revolving line of credit that you can use over and over again, special financing where available, convenient monthly payments to fit your budget, easy-to-use online account management and bill payment options. This credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more.";	
	if ($ad=="Wells-Fargo-Splash-D.png") $alt = "Buy today, pay over time. Your Wells Fargo Home Projects credit card also brings you revolving line of credit that you can use over and over again, special financing where available, convenient monthly payments to fit your budget, easy-to-use online account management and bill payment options. The Wells Fargo Home Projects credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more.";	
	$output = '<a href="'.$link.'"><img src="/wp-content/themes/battleplantheme/common/financing/'.$ad.'" alt="'.$alt.'" '.$class.' width="300" height="250"/></a>';
	return $output; 
}

/*--------------------------------------------------------------
# Basic Theme Set Up
--------------------------------------------------------------*/
add_action( 'pre_get_posts', 'battleplan_override_main_query_with_hvac', 10 );
function battleplan_override_main_query_with_hvac( $query ) {
	if (!is_admin() && $query->is_main_query()) :		
		if ( is_post_type_archive('products') || is_tax('product-type') || is_tax('product-class') ) :
			$query->set( 'posts_per_page',10);
			$query->set( 'orderby','menu_order'); 
			$query->set( 'order','asc');
		endif;
	endif; 
}	
?>