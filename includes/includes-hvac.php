<?php
/* Battle Plan Web Design - HVAC Includes

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Product Overview
# Customer Care / Pro Partners
	- American Standard Customer Care
	- Rheem Pro Partner
	- Ruud Pro Partner
	- Comfortmaker Elite Dealer
	- Tempstar Elite Dealer
# Why Choose Us?
# HVAC Maintenance Tips
# HVAC Tip Of The Month
# Register Custom Post Types
# Import Advanced Custom Fields
# Widgets
	- Brand Logo
	- Symptom Checker
	- Customer Care Dealer
	- Comfortmaker Elite Dealer
	- Tempstar Elite Dealer
	- Financing Widget
	- Wells Fargo
# Shortcodes
# Basic Theme Set Up
# Employment Application
# Mass Product Update

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Product Overview
--------------------------------------------------------------*/
add_shortcode( 'product-overview', 'battleplan_product_overview' );
function battleplan_product_overview( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	$brands = array('amana', 'american standard', 'bryant', 'carrier', 'comfortmaker', 'lennox', 'lg', 'mitsubishi', 'rheem', 'ruud', 'tempstar', 'trane', 'york');

	foreach( $brands as $brand ) :
		if (strpos($type, $brand) !== false) $file = str_replace( ' ', '-', $brand );
	endforeach;

	include('wp-content/themes/battleplantheme/elements/element-product-overview-generic.php');
	include('wp-content/themes/battleplantheme/elements/element-product-overview-'.$file.'.php');


	if (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/'.$pic.'.webp' ) ) : $pic = $pic.".webp";
	elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/'.$pic.'.jpg' ) ) : $pic = $pic.".jpg";
	elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/'.$pic.'.png' ) ) : $pic = $pic.".png";
	else : $pic = 'na.webp';
	endif;

	return do_shortcode('
		[col class="col-archive col-products"]
		 [img size="1/3" link="'.$link.'" ada-hidden="true"]<img class="img-archive img-products" src="/wp-content/uploads/'.$pic.'" loading="lazy" alt="'.$alt.'" style="aspect-ratio:1/1">[/img]
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
	return include "wp-content/themes/battleplantheme/pages/page-hvac-customer-care-dealer.php";
}

/*--------------------------------------------------------------
# Rheem Pro Partner
--------------------------------------------------------------*/
add_shortcode( 'rheem-pro-partner', 'battleplan_rheem_pro_partner' );
function battleplan_rheem_pro_partner( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	return include "wp-content/themes/battleplantheme/pages/page-hvac-rheem-pro-partner.php";
}

/*--------------------------------------------------------------
# Ruud Pro Partner
--------------------------------------------------------------*/
add_shortcode( 'ruud-pro-partner', 'battleplan_ruud_pro_partner' );
function battleplan_ruud_pro_partner( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	return include "wp-content/themes/battleplantheme/pages/page-hvac-ruud-pro-partner.php";
}

/*--------------------------------------------------------------
# Comfortmaker Elite Dealer
--------------------------------------------------------------*/
add_shortcode( 'comfortmaker-elite-dealer', 'battleplan_comfortmaker_elite_dealer' );
function battleplan_comfortmaker_elite_dealer( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	return include "wp-content/themes/battleplantheme/pages/page-hvac-comfortmaker-elite-dealer.php";
}

/*--------------------------------------------------------------
# York Certified Comfort Expert
--------------------------------------------------------------*/
add_shortcode( 'york-cert-comfort-expert', 'battleplan_york_cert_comfort_expert' );
function battleplan_york_cert_comfort_expert( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	return include "wp-content/themes/battleplantheme/pages/page-hvac-york-cert-comfort-expert.php";
}

/*--------------------------------------------------------------
# Tempstar Elite Dealer
--------------------------------------------------------------*/
add_shortcode( 'tempstar-elite-dealer', 'battleplan_tempstar_elite_dealer' );
function battleplan_tempstar_elite_dealer( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);
	return include "wp-content/themes/battleplantheme/pages/page-hvac-tempstar-elite-dealer.php";
}

/*--------------------------------------------------------------
# Why Choose Us?
--------------------------------------------------------------*/
add_shortcode( 'why-choose-as', 'battleplan_why_choose_us' );
add_shortcode( 'why-choose-us', 'battleplan_why_choose_us' );
function battleplan_why_choose_us( $atts, $content = null ) {
	$a = shortcode_atts( array( 'brand'=>'', 'style'=>'1', 'width'=>'stretch', 'img'=>'', 'alt'=>'' ), $atts );
	$brand = esc_attr($a['brand']);
	$style = esc_attr($a['style']);
	$width = esc_attr($a['width']);
	$img = esc_attr($a['img']);
	$alt = esc_attr($a['alt']);
	if ( $brand == '' ) :
		$brand = customer_info()['site-brand'];
		if ( is_array($brand) ) $brand = $brand[0];
	endif;
	$name = ucwords($brand);
	$brand = strtolower(str_replace(" ", "-", $brand));
	if ( $alt == '' ) $alt='We are proud to be a dealer of '.$name.', offering the top rated HVAC products on the market.';

	if ( $img == "grey" ) : $img = "/wp-content/themes/battleplantheme/common/hvac-".$brand."/why-choose-".$brand."-logo-grey.webp";
	elseif ( $img == "white" ) : $img = "/wp-content/themes/battleplantheme/common/hvac-".$brand."/why-choose-".$brand."-logo-white.webp";
	elseif ( $img == "" ) :	$img = "/wp-content/themes/battleplantheme/common/hvac-".$brand."/why-choose-".$brand."-logo.webp";
	else: $img = "/wp-content/uploads/".$img;
	endif;

	return include "wp-content/themes/battleplantheme/elements/element-why-choose-".$brand.".php";
}

/*--------------------------------------------------------------
# HVAC Maintenance Tips
--------------------------------------------------------------*/
add_shortcode( 'hvac-maintenance-tips', 'battleplan_hvac_maintenance_tips' );
function battleplan_hvac_maintenance_tips( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);

	return include "wp-content/themes/battleplantheme/pages/page-hvac-maintenance-tips.php";
}

/*--------------------------------------------------------------
# HVAC Tip Of The Month
--------------------------------------------------------------*/
add_shortcode( 'hvac-tip-of-the-month', 'battleplan_hvac_tip_of_the_month' );
function battleplan_hvac_tip_of_the_month( $atts, $content = null ) {
	return include "wp-content/themes/battleplantheme/elements/element-hvac-tip-of-the-month.php";
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
		'capability_type'=>'page',
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
		'title' => 'HVAC Products',
		'fields' => array(
			array(
				'key' => 'product_brochure',
				'label' => 'Brochure',
				'name' => 'brochure',
				'type' => 'url',
				'required' => 0,
				'conditional_logic' => 0,
				'default_value' => '',
				'layout' => 'vertical',
				'allow_null' => 0,
				'return_format' => 'value',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'products',
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
# Widgets
--------------------------------------------------------------*/

// Add Brand Logo widget to Sidebar
add_shortcode( 'get-brand-logo', 'battleplan_getBrandLogo' );
function battleplan_getBrandLogo($atts, $content = null) {
	$a = shortcode_atts( array( 'alt'=>'', 'brand'=>'' ), $atts );
	$altImg = $a['alt'] ? '-'.esc_attr($a['alt']) : '';
	$brand = esc_attr($a['brand']);
	if ( $brand === '' ) :
		$brand = customer_info()['site-brand'];
		if ( is_array($brand) ) $brand = $brand[0];
	endif;
	$brand = trim($brand);
	$name = ucwords($brand);
	$alt = $name !== 'Generac' ? $name.' heating and air conditioning products.' : $name.' home generators.';
	$brand = strtolower(str_replace(" ", "-", $brand));
	$imagePath = get_template_directory().'/common/hvac-'.$brand.'/'.$brand.'-sidebar-logo'.$altImg.'.webp';
	list($width, $height) = getimagesize($imagePath);

	return '<img class="noFX brand-logo '.$brand.'-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/hvac-'.$brand.'/'.$brand.'-sidebar-logo'.$altImg.'.webp" alt="We offer '.$alt.'" width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" >';
}

// Add Symptom Checker widget to Sidebar
add_shortcode( 'get-symptom-checker', 'battleplan_getSymptomChecker' );
function battleplan_getSymptomChecker() {
	$brand = customer_info()['site-brand'];
	if ( is_array($brand) ) $brand = $brand[0];
	$name = ucwords($brand);
	$brand = strtolower(str_replace(" ", "-", $brand));
	return '<a href="/symptom-checker/" title="Click here for troublshooting ideas to solve common HVAC problems."><img class="noFX" src="/wp-content/themes/battleplantheme/common/hvac-'.$brand.'/symptom-checker.webp" loading="lazy" alt="'.$name.' HVAC unit pictured on colorful background." width="300" height="250" style="aspect-ratio:300/250" ></a>';
}

// Add Customer Care Dealer widget to Sidebar
add_shortcode( 'get-customer-care', 'battleplan_getCustomerCare' );
function battleplan_getCustomerCare() {
	return '<a href="/customer-care-dealer/" title="Click here to read more about the American Standard Heating & Cooling Customer Care Dealer program"><img class="noFX" src="/wp-content/themes/battleplantheme/common/hvac-american-standard/customer-care-dealer-logo.webp" loading="lazy" alt="We are proud to be an American Standard Customer Care Dealer" width="400" height="400" style="aspect-ratio:400/400" ></a>';
}

// Add Comfortmaker Elite Dealer widget to Sidebar
add_shortcode( 'get-comfortmaker-elite-dealer', 'battleplan_getComfortmakerEliteDealer' );
function battleplan_getComfortmakerEliteDealer() {
	return '<a href="/comfortmaker-elite-dealer/" title="Click here to read more about the Comfortmaker Elite Dealer program"><img class="noFX" src="/wp-content/themes/battleplantheme/common/hvac-comfortmaker/comfortmaker-elite-dealer-logo.webp" loading="lazy" alt="We are proud to be a Comfortmaker Elite Dealer" width="400" height="400" style="aspect-ratio:400/400" ></a>';
}

// Add Tempstar Elite Dealer widget to Sidebar
add_shortcode( 'get-tempstar-elite-dealer', 'battleplan_getTempstarEliteDealer' );
function battleplan_getTempstarEliteDealer() {
	return '<a href="/tempstar-elite-dealer/" title="Click here to read more about the Tempstar Elite Dealer program"><img class="noFX" src="/wp-content/themes/battleplantheme/common/hvac-tempstar/tempstar-elite-dealer-logo.webp" loading="lazy" alt="We are proud to be a Tempstar Elite Dealer" width="400" height="400" style="aspect-ratio:400/400" ></a>';
}

// Add Financing widget to Sidebar
add_shortcode( 'get-financing', 'battleplan_getFinancing' );
function battleplan_getFinancing($atts, $content = null) {
	$a = shortcode_atts( array( 'bank'=>'', 'link'=>'biz-info', 'text'=>'', 'loc'=>'below', 'graphic'=>'', 'class'=>'' ), $atts );
	$bank = esc_attr($a['bank']);
	$text = esc_attr($a['text']);
	$loc = esc_attr($a['loc']);
	$link = esc_attr($a['link']) === 'biz-info' ? customer_info()['finance-link'] : esc_attr($a['link']);
	$class = esc_attr($a['class']) != '' ? ' '.esc_attr($a['class']) : '';
	$img = strtolower(str_replace(" ", "-", $bank));
	if ( $img == "enerbank-usa" ) $img = "Enerbank-USA";
	$graphic = esc_attr($a['graphic']);
	if ( $graphic != '' ) $img = $img.'-'.$graphic;
	$buildFinancing = "";
	$imagePath = get_template_directory().'/common/financing/'.$img.'.webp';
	list($width, $height) = getimagesize($imagePath);

	if ( $link != "" ) $buildFinancing .= '<a href="'.$link.'" title="Click here to apply for financing for AC repair at '.$bank.'" target="_blank">';
	if ( $text != "" && $loc == "above" ) $buildFinancing .= '<span class="link-text">'.$text.'</span>';
	$buildFinancing .= '<img class="financing-img tracking'.$class.'" data-track="financing"" src="/wp-content/themes/battleplantheme/common/financing/'.$img.'.webp" loading="lazy" alt="Apply for financing for your HVAC needs at '.$bank.'" width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" >';
	if ( $text != "" && $loc == "below" ) $buildFinancing .= '<span class="link-text">'.$text.'</span>';
	if ( $link != "" ) $buildFinancing .= '</a>';

	return do_shortcode($buildFinancing);
}

// Add Wells Fargo widget to Sidebar
add_shortcode( 'get-wells-fargo', 'battleplan_getWellsFargo' );
function battleplan_getWellsFargo($atts, $content = null) {
	$a = shortcode_atts( array( 'graphic1'=>'', 'graphic2'=>'', 'link'=>'biz-info', 'class'=>''  ), $atts );
	$graphic1 = esc_attr($a['graphic1']);
	$graphic2 = esc_attr($a['graphic2']);
	$link = esc_attr($a['link']) === 'biz-info' ? customer_info()['finance-link'] : esc_attr($a['link']);
	$class = esc_attr($a['class']) != '' ? ' '.esc_attr($a['class']) : '';
	$rand = rand(1,2);
	if ($rand == "1") : $ad = $graphic1; endif;
	if ($rand == "2") : $ad = $graphic2; endif;
	if ($ad=="Wells-Fargo-A.webp" || $ad=="Wells-Fargo-B.webp") : $alt = "Looking for financing options? Special financing available. This credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more."; $width="300"; $height="250"; endif;
	if ($ad=="Wells-Fargo-C.webp" || $ad=="Wells-Fargo-D.webp") : $alt = "Special financing available. This credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more."; $width="300"; $height="250"; endif;
	if ($ad=="Wells-Fargo-E.webp") : $alt = "Financing available through Wells Fargo Bank, NA. This credit card is issued with approved credit.  Equal Housing Lender."; $width="200"; $height="152"; endif;
	if ($ad=="Wells-Fargo-Splash-A.webp" || $ad=="Wells-Fargo-Splash-B.webp" || $ad=="Wells-Fargo-Splash-C.webp" || $ad=="Wells-Fargo-Splash-D.webp") : $alt = "Buy today, pay over time with this Wells Fargo credit card. Learn more."; $width="600"; $height="300"; endif;
	$output = '<a href="'.$link.'" title="Click to learn more about Wells Fargo financing options." target="_blank"><img src="/wp-content/themes/battleplantheme/common/financing/'.$ad.'" loading="lazy" alt="'.$alt.'" class="tracking'.$class.'" data-track="financing" width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" ></a>';
	return $output;
}

/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/
// Set up "good better best" product comparison display
add_shortcode( 'get-product-comparison', 'battleplan_getProductComparison' );
function battleplan_getProductComparison($atts, $content = null ) {
	$a = shortcode_atts( array( 'products' => '', 'size'=>'100', 'pic_size'=>'100'), $atts );
	$size = esc_attr($a['size']);
	$picSize = esc_attr($a['pic_size']);
	$products = esc_attr($a['products']) != '' ? esc_attr($a['products']) : 'air-conditioners,heat-pumps,furnaces';
	$productList = explode(",", $products);
	$class = 'current';

	$buildBox = '<ul class="tabs">';
	foreach ( $productList as $product ) :
		$buildBox .= '<li data-tab="'.$product.'" tabindex="0" class="'.$class.'">'.ucwords(str_replace("-", " ", $product)).'</li>';
		$class = '';
	endforeach;
	$buildBox .= '</ul>';
	$buildBox .= '<div class="tab-content-holder">';
	$class = ' current';

	foreach ( $productList as $product ) :
		$buildBox .= '[section name="'.$product.'" class="tab-content'.$class.'"][layout grid="3e"]';

		$query = bp_WP_Query('products', [
			'taxonomy'       => 'product-type',
			'terms'          => $product,
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'DESC'
		]);

		$current_product_class = '';

		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) :
				$query->the_post();
				$product_class = get_the_terms( get_the_ID(), 'product-class' )[0]->name;
				if ( $product_class !== $current_product_class ) :
					$buildBox .= '[col]';
					$buildBox .=  '<h2>'.$product_class.'</h2>';
					$buildBox .= do_shortcode('[build-archive type="products" show_thumb="true" size="'.$size.'" show_btn="true" btn_text="Learn More" title_pos="inside" show_excerpt="true" show_date="false" show_author="false" pic_size="'.$picSize.'"]');
					$buildBox .= '[/col]';
					$current_product_class = $product_class;
				endif;
			endwhile;
		endif;

		wp_reset_postdata();
		$class = '';
		$buildBox .= '[/layout][/section]';
	endforeach;

	$buildBox .= '</div>';

	return do_shortcode($buildBox);
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

/*--------------------------------------------------------------
# Employment Application Form
#
# Drop [bp-employment-form] on a page to render the standard HVAC
# employment application. Per-site customization via filters below.
--------------------------------------------------------------*/

// Default screening Yes/No questions (criminal history, driver's license, manual labor)
function bp_employment_default_screening() {
	return [
		'criminal-history' => ['label' => 'Criminal History',                    'options' => 'Yes|No'],
		'driver-license'   => ['label' => "Driver's License",                    'options' => 'Yes|No'],
		'manual-labor'     => ['label' => 'Have You Done Manual Labor Before?', 'options' => 'Yes|No'],
	];
}

// The form shortcode
add_shortcode('bp-employment-form', function($atts) {
	$a = shortcode_atts(['submit' => 'Submit'], $atts);

	$screening = apply_filters('bp_employment_screening_questions', bp_employment_default_screening());
	$positions = apply_filters('bp_employment_position_options', 'Installation Technician|Service Technician|Duct Team|Office|Management|Internship');
	$types     = apply_filters('bp_employment_type_options',     'HVAC|Plumbing|Electrical|Other');

	// Build the screening row — one [col] per question
	$screening_cols = '';
	foreach ($screening as $name => $cfg) {
		$label = $cfg['label']   ?? ucwords(str_replace(['-', '_'], ' ', $name));
		$opts  = $cfg['options'] ?? 'Yes|No';
		$screening_cols .= '[col][seek label="' . esc_attr($label) . '" id="' . esc_attr($name) . '" req="true"][bp-radio name="' . esc_attr($name) . '" options="' . esc_attr($opts) . '" required="true"][/seek][/col]';
	}

	// Pick a sensible grid for the screening row based on how many questions there are
	$count = count($screening);
	$grid  = match (true) {
		$count <= 1 => '1',
		$count === 2 => '1-1',
		$count === 3 => '1-1-2',
		$count === 4 => '1-1-1-1',
		default      => '1',
	};

	return do_shortcode('[bp-form id="employment" subject="Employment Application" class="application"]
		[layout grid="3-3-2"]
			[col][seek label="Name" id="user-name" req="true"][bp-text name="user-name" required="true" autocomplete="name"][/seek][/col]
			[col][seek label="Email" id="user-email" req="true"][bp-email name="user-email" required="true"][/seek][/col]
			[col][seek label="Phone" id="user-phone" req="true"][bp-tel name="user-phone" required="true"][/seek][/col]
		[/layout]

		[layout grid="200px 1fr" class="form-stacked"]
			[col][seek label="Years of Exp." id="years-exp" req="true" max-w="180px"][bp-text name="years-exp" required="true" pattern="\d+"][/seek][/col]
			[col][seek label="Type(s) of Experience" id="type-exp" req="true"][bp-checkboxes name="type-exp" options="' . esc_attr($types) . '"][/seek][/col]
		[/layout]

		[layout grid="' . $grid . '" class="form-stacked break-2"]
			' . $screening_cols . '
		[/layout]

		[layout grid="1" class="form-stacked"]
			[col][seek label="Position(s) Interested In" id="pos-interest" req="true"][bp-checkboxes name="pos-interest" options="' . esc_attr($positions) . '"][/seek][/col]
		[/layout]

		[seek label="Tell us a little about yourself." id="user-message" width="full"][bp-textarea name="user-message"][/seek]

		[seek label="button"][bp-submit]' . esc_html($a['submit']) . '[/bp-submit][/seek]
	[/bp-form]');
});

// Default field labels — matches the email body template below
add_filter('bp_field_labels', function($labels, $form_id) {
	if ($form_id !== 'employment') return $labels;

	$screening = apply_filters('bp_employment_screening_questions', bp_employment_default_screening());
	foreach ($screening as $name => $cfg) {
		$labels[$name] = $cfg['label'] ?? ucwords(str_replace(['-', '_'], ' ', $name));
	}

	return array_merge($labels, [
		'years-exp'    => 'Years Exp',
		'type-exp'     => 'Type Exp',
		'pos-interest' => 'Positions',
		'user-message' => 'Additional Info',
	]);
}, 10, 2);

// Default email body template (matches the legacy CF7 layout exactly)
add_filter('bp_form_email_template', function($template, $form_id, $ctx) {
	if ($form_id !== 'employment') return $template;

	$screening = apply_filters('bp_employment_screening_questions', bp_employment_default_screening());
	$screening_lines = '';
	foreach ($screening as $name => $cfg) {
		$label = $cfg['label'] ?? ucwords(str_replace(['-', '_'], ' ', $name));
		$screening_lines .= "$label: [$name]\n";
	}

	return "Name: [user-name]\nEmail: [user-email]\nPhone: [user-phone]\n\n"
		 . "Years Exp: [years-exp]\nType Exp: [type-exp]\n\n"
		 . trim($screening_lines) . "\n\n"
		 . "Positions: [pos-interest]\n\n"
		 . "Additional Info:\n[user-message]";
}, 10, 3);

// Set recipient(s). Default: customer_info['email']. Sites add via bp_employment_recipients.
add_filter('bp_form_before_send', function($email, $ctx) {
	if (($ctx['form_id'] ?? '') !== 'employment') return $email;

	$default_emails = [];
	$business_email = $ctx['customer']['email'] ?? '';
	if ($business_email) $default_emails[] = $business_email;

	$emails = apply_filters('bp_employment_recipients', $default_emails, $ctx);
	$emails = array_filter(array_map('sanitize_email', (array)$emails));
	if (!empty($emails)) $email['to'] = implode(', ', $emails);

	return $email;
}, 11, 2);

// Qualification check + subject prefix. Sites can override the qualification logic
// via bp_employment_qualified if their screening questions differ.
add_filter('bp_form_before_send', 'battleplan_handleEmploymentApp', 10, 2);
function battleplan_handleEmploymentApp($email, $ctx) {
	if (($ctx['form_id'] ?? '') !== 'employment') return $email;

	$qualified = apply_filters('bp_employment_qualified', bp_employment_default_qualified_check($ctx), $ctx);

	$email['subject'] = ($qualified ? '< QUALIFIED >' : '< unqualified >') . ' ' . $email['subject'];

	$GLOBALS['bp_employment_qualified'] = $qualified;
	return $email;
}

// Default qualification: criminal=No, license=Yes, background-chk-or-manual-labor=Yes.
// Missing fields are treated as "pass" so removing a screening question doesn't auto-fail anyone.
function bp_employment_default_qualified_check($ctx) {
	$fields = $ctx['fields'] ?? [];
	$norm   = fn($v) => is_array($v) ? ($v[0] ?? null) : $v;

	$criminal   = $norm($fields['criminal-history'] ?? null);
	$license    = $norm($fields['driver-license']   ?? null);
	$background = $norm($fields['background-chk'] ?? $fields['manual-labor'] ?? null);

	return ($criminal == null   || str_contains((string)$criminal,   'No'))
		&& ($license == null    || str_contains((string)$license,    'Yes'))
		&& ($background == null || str_contains((string)$background, 'Yes'));
}

// Auto-reply with PDF — fires only when a PDF path is configured AND candidate qualified.
// Test-mode aware: messages starting with "test" reroute the auto-reply to the dev mailbox too.
add_action('bp_form_after_send', function($email, $ctx, $sent) {
	if (!$sent) return;
	if (($ctx['form_id'] ?? '') !== 'employment') return;
	if (empty($GLOBALS['bp_employment_qualified'])) return;

	$pdf_path = apply_filters('bp_employment_autoreply_pdf', '', $ctx);
	if (empty($pdf_path)) return;

	$applicant_email = $ctx['fields']['user-email'] ?? '';
	if (!is_email($applicant_email)) return;

	// Reroute auto-reply during test submissions so we don't email real people
	if (function_exists('bp_is_test_submission') && bp_is_test_submission($ctx['fields'])) {
		$applicant_email = apply_filters('bp_form_test_recipient', 'glendon@bp-webdev.com');
	}

	$autoreply = apply_filters('bp_employment_autoreply', bp_employment_default_autoreply($ctx), $ctx);

	$pdf_full = (strpos($pdf_path, ABSPATH) === 0) ? $pdf_path : ABSPATH . ltrim($pdf_path, '/');
	$attachments = file_exists($pdf_full) ? [$pdf_full] : [];

	$business_name = $ctx['customer']['name'] ?? get_bloginfo('name');
	$from_email    = 'email@admin.' . preg_replace('#https?://#', '', get_bloginfo('url'));
	$headers = [
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $business_name . ' <' . $from_email . '>',
	];

	wp_mail(
		$applicant_email,
		$autoreply['subject'] ?? 'Thank you for your employment application.',
		$autoreply['body']    ?? '',
		$headers,
		$attachments
	);
}, 10, 3);

function bp_employment_default_autoreply($ctx) {
	$business_name = $ctx['customer']['name']   ?? get_bloginfo('name');
	$applicant     = $ctx['fields']['user-name'] ?? '';

	$body  = '<p>' . esc_html($applicant) . ', thank you for your interest in employment with ' . esc_html($business_name) . '.</p>';
	$body .= '<p>Based on your initial responses on our website, we feel you may be a qualified candidate for employment.</p>';
	$body .= '<p>Attached is a PDF of our full employment application. Please fill out this application and return it to us in person or by email.</p>';
	$body .= '<p>Once we receive your application, a member of our management team will review it and be in contact as soon as possible.</p>';
	$body .= '<p>Thank you!<br>' . esc_html($business_name) . ' Management</p>';

	return [
		'subject' => 'Thank you for your employment application.',
		'body'    => $body,
	];
}

/*--------------------------------------------------------------
# Mass Product Update
--------------------------------------------------------------*/
//add_action( 'init', 'battleplan_mass_product_update' );
function battleplan_mass_product_update() {
	$customer_info = customer_info();

	if ( $customer_info['site-brand'] == 'american standard' || (is_array($customer_info['site-brand']) && in_array('american standard', $customer_info['site-brand'])) ) :

		if ( get_option( 'product-update-may-2022' ) != 'completed' ) :
			require_once get_template_directory() . '/includes/includes-mass-site-update.php';
			update_option( 'product-update-may-2022', 'completed' );
		endif;

	endif;
}
?>