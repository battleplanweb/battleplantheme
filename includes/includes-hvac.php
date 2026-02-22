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
# Employment Application
--------------------------------------------------------------*/
add_action( 'wpcf7_before_send_mail', 'battleplan_handleEmploymentApp', 10, 1 );
function battleplan_handleEmploymentApp( $contact_form ) {
	$formMail = $contact_form->prop( 'mail' );
	$formSubject = $formMail['subject'];

	if ( str_contains( $formMail['subject'], "Employment Application" ) ) :
		$submission = WPCF7_Submission::get_instance();
		$submitted['posted_data'] = $submission->get_posted_data();
		$age = $submitted['posted_data']['user-age'][0] . $submitted['posted_data']['user-age'][1] ;
		$criminal = $submitted['posted_data']['criminal-history'][0] !== null ? $submitted['posted_data']['criminal-history'][0] : null;
		$license = $submitted['posted_data']['driver-license'][0] !== null ? $submitted['posted_data']['driver-license'][0] : null;
		$background = $submitted['posted_data']['background-chk'][0] !== null ? $submitted['posted_data']['background-chk'][0] : null;

		if ( ($criminal == null || str_contains($criminal, "No")) && ($license == null || str_contains($license, "Yes")) && ($background == null || str_contains($background, "Yes")) ):
			$preSub = "< QUALIFIED >";
		else:
			$preSub = "< unqualified >";
		endif;

		$formMail['subject'] = $preSub." ".$formSubject;
		$contact_form->set_properties( array( 'mail' => $formMail ) );
	endif;
};

add_filter('wpcf7_additional_mail', 'battleplan_handleEmploymentAppResponse', 10, 2);
function battleplan_handleEmploymentAppResponse($additional_mail, $contact_form) {
	$submission = WPCF7_Submission::get_instance();
	$submitted['posted_data'] = $submission->get_posted_data();
	$age = $submitted['posted_data']['user-age'][0] . $submitted['posted_data']['user-age'][1] ;
	$criminal = $submitted['posted_data']['criminal-history'][0] !== null ? $submitted['posted_data']['criminal-history'][0] : null;
	$license = $submitted['posted_data']['driver-license'][0] !== null ? $submitted['posted_data']['driver-license'][0] : null;
	$background = $submitted['posted_data']['background-chk'][0] !== null ? $submitted['posted_data']['background-chk'][0] : null;

	if ( ($criminal == null || str_contains($criminal, "No")) && ($license == null || str_contains($license, "Yes")) && ($background == null || str_contains($background, "Yes")) ):
		return $additional_mail;
	else:
		return;
	endif;
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