<?php 
/* Battle Plan Web Design Functions: Chron Jobs

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Chron Jobs
# Universal Pages
# Convert States to Abbr
# Sync with Google Analytics

/*--------------------------------------------------------------
# Chron Jobs
--------------------------------------------------------------*/

// delete all options that begin with {$prefix}
function battleplan_delete_prefixed_options( $prefix ) {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'" );
}	

if ( get_option('bp_setup_2022_08_09') != "completed" ) :
	delete_option('bp_setup_2022_07_26');
	delete_option('bp_setup_2022_08_03');
	
	delete_option('stats_basic');
	delete_option('stats_referrers');
	delete_option('stats_locations');	
	delete_option('stats_tech');	
	delete_option('bp_site_hits');
	delete_option('page-scroll-pct');
	delete_option('bg_debug');
	delete_option('content-tracking');
	delete_option('content-scroll-pct');
	
	battleplan_delete_prefixed_options( 'strx-magic-floating-sidebar-' );	
	battleplan_delete_prefixed_options( 'ewww_image_optimizer' );
	battleplan_delete_prefixed_options( 'gplkit_' );
	battleplan_delete_prefixed_options( 'wp_rocket' );
	battleplan_delete_prefixed_options( 'aam_' );
	battleplan_delete_prefixed_options( 'bp_track_content_' );	

	updateOption( 'bp_setup_2022_08_09', 'completed', false );			
endif;

$customerInfo = get_option( 'customer_info' );
if ( get_option('bp_analytics_ua_complete') ) :
	unset($customerInfo['google-tags']['ua-view']);
	unset($GLOBALS['customer_info']['google-tags']['ua-view']);
endif;
if ( $customerInfo['site-type'] != 'profile' ) delete_option('site_login');
unset($customerInfo['radius']);
update_option( 'customer_info', $customerInfo );

//wp_cache_delete ( 'alloptions', 'options' );
	
$bpChrons = get_option( 'bp_chrons_pages' ) ? get_option( 'bp_chrons_pages' ) : 0;
$pagesLeft = 50 - $bpChrons;	// change 50 in functions-admin.php to get accurate count on Run Chron menu item
$bpChrons = $bpChrons + 0.5;
updateOption( 'bp_chrons_pages', $bpChrons );

require_once get_template_directory().'/vendor/autoload.php';
require_once get_template_directory().'/google-api-php-client/vendor/autoload.php';

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
//use Google\Analytics\Data\V1beta\FilterExpression;
//use Google\Analytics\Data\V1beta\Filter;

if ( ( $pagesLeft <= 0 || _IS_BOT ) && !_IS_GOOGLEBOT && !is_mobile() ) :

	if (function_exists('battleplan_remove_user_roles')) battleplan_remove_user_roles();
	if (function_exists('battleplan_create_user_roles')) battleplan_create_user_roles();
		
	// Needed to clear out the unnecessary customer_info options such as 'ua-view' and 'radius'
	if ( $GLOBALS['site-loc'] && $GLOBALS['site-loc'] != 1 ) :
		delete_option('customer_info_'.$loc);
		if (function_exists('battleplan_updateSiteOptions')) battleplan_updateSiteOptions();
		$GLOBALS['customer_info'] = get_option('customer_info_'.$loc);
	else:
		delete_option('customer_info');
		if (function_exists('battleplan_updateSiteOptions')) battleplan_updateSiteOptions();
		$GLOBALS['customer_info'] = get_option('customer_info');
	endif;

// WP Mail SMTP Settings Update
	if ( is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') ) : 
		$apiKey1 = "keysib";
		$apiKey2 = "ef3a9074e001fa21f640578f699994cba854489d3ef793";
		$wpMailSettings = get_option( 'wp_mail_smtp' );			
		$wpMailSettings['mail']['from_email'] = 'email@admin.'.str_replace('https://', '', get_bloginfo('url'));
		$wpMailSettings['mail']['from_name'] = 'Website Administrator';
		$wpMailSettings['mail']['mailer'] = 'sendinblue';
		$wpMailSettings['mail']['from_email_force'] = '1';
		$wpMailSettings['mail']['from_name_force'] = '1';	
		$wpMailSettings['sendinblue']['api_key'] = 'x'.$apiKey1.'-d08cc84fe45b37a420'.$apiKey2.'-AafFpD2zKkIN3SBZ';
		$wpMailSettings['sendinblue']['domain'] = 'admin.'.str_replace('https://', '', get_bloginfo('url'));				
		update_option( 'wp_mail_smtp', $wpMailSettings );
	endif;
	
// Contact Form 7 Settings Update
	if ( is_plugin_active('contact-form-7/wp-contact-form-7.php') ) : 
		$forms = get_posts( array ( 'numberposts'=>-1, 'post_type'=>'wpcf7_contact_form' ));
		foreach ( $forms as $form ) :
			$formID = $form->ID;
			$formMail = readMeta( $formID, "_mail" );
			//$formB = $formMail['body'];
			$formTitle = get_the_title($formID);

			if ( $formTitle == "Quote Request Form" ) $formTitle = "Quote Request";
			if ( $formTitle == "Contact Us Form" ) $formTitle = "Customer Contact";		

			$formMail['subject'] = $formTitle." · Website · ".$GLOBALS['customer_info']['name'];
			$formMail['sender'] = "[user-name] <email@admin.".do_shortcode('[get-domain-name ext="true"]').">";
			$formMail['additional_headers'] = "Reply-to: [user-name] <[user-email]>\nBcc: Website Administrator <email@battleplanwebdesign.com>";
			$formMail['use_html'] = 1;
			$formMail['exclude_blank'] = 1;

			updateMeta( $formID, "_mail", $formMail );	
		endforeach;
	endif;

// Yoast SEO Settings Update
	if ( is_plugin_active('wordpress-seo-premium/wp-seo-premium.php') ) :		
		$wpSEOSettings = get_option( 'wpseo_titles' );		
		$wpSEOSettings['separator'] = 'sc-bull';
		$wpSEOSettings['title-home-wpseo'] = '%%page%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$wpSEOSettings['title-author-wpseo'] = '%%name%%, Author at %%sitename%% %%page%%';
		$wpSEOSettings['title-archive-wpseo'] = 'Archive %%sep%% %%sitename%% %%sep%% %%date%% ';
		$wpSEOSettings['title-search-wpseo'] = 'You searched for %%searchphrase%% %%sep%% %%sitename%%';
		$wpSEOSettings['title-404-wpseo'] = 'Page Not Found %%sep%% %%sitename%%';
		$wpSEOTitle = ' %%page%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';		
		$getCPT = get_post_types(); 
		foreach ($getCPT as $postType) :
			if ( $postType == "post" || $postType == "page" || $postType == "optimized" || $postType == "universal" || $postType == "products" ) :
				$wpSEOSettings['title-'.$postType] = '%%title%%'.$wpSEOTitle;
				$wpSEOSettings['social-title-'.$postType] = '%%title%%'.$wpSEOTitle;
			elseif ( $postType == "attachment" || $postType == "revision" || $postType == "nav_menu_item" || $postType == "custom_css" || $postType == "customize_changeset" || $postType == "oembed_cache" || $postType == "user_request" || $postType == "wp_block" || $postType == "elements" || $postType == "acf-field-group" || $postType == "acf-field" || $postType == "wpcf7_contact_form" ) :
				// nothing //
			else:
				$wpSEOSettings['title-'.$postType] = ucfirst($postType).$wpSEOTitle;			
				$wpSEOSettings['social-title-'.$postType] = ucfirst($postType).$wpSEOTitle;			
			endif;		
		endforeach;	
		$wpSEOSettings['social-title-author-wpseo'] = '%%name%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$wpSEOSettings['social-title-archive-wpseo'] = '%%date%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$wpSEOSettings['noindex-author-wpseo'] = '1';
		$wpSEOSettings['noindex-author-noposts-wpseo'] = '1';
		$wpSEOSettings['noindex-archive-wpseo'] = '1';
		$wpSEOSettings['disable-author'] = '1';
		$wpSEOSettings['disable-date'] = '1';
		$wpSEOSettings['disable-attachment'] = '1';
		$wpSEOSettings['breadcrumbs-404crumb'] = 'Error 404: Page not found';
		$wpSEOSettings['breadcrumbs-boldlast'] = '1';
		$wpSEOSettings['breadcrumbs-archiveprefix'] = 'Archives for';
		$wpSEOSettings['breadcrumbs-enable'] = '1';
		$wpSEOSettings['breadcrumbs-home'] = 'Home';
		$wpSEOSettings['breadcrumbs-searchprefix'] = 'You searched for';
		$wpSEOSettings['breadcrumbs-sep'] = '»';
		$wpSEOSettings['company_logo'] = get_bloginfo("url").'/wp-content/uploads/logo.png';
		$wpSEOSettings['company_logo_id'] = attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' );
		$wpSEOSettings['company_logo_meta']['url'] = get_bloginfo("url").'/wp-content/uploads/logo.png';	
		$wpSEOSettings['company_logo_meta']['path'] = get_attached_file( attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' ) );
		$wpSEOSettings['company_logo_meta']['id'] = attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' );
		$wpSEOSettings['company_name'] = get_bloginfo('name');
		$wpSEOSettings['company_or_person'] = 'company';
		$wpSEOSettings['stripcategorybase'] = '1';
		$wpSEOSettings['breadcrumbs-enable'] = '1';				
		$wpSEOSettings['noindex-ptarchive-optimized'] = '1';			
		$wpSEOSettings['noindex-testimonials'] = '1';
		$wpSEOSettings['display-metabox-pt-testimonials'] = '0';
		$wpSEOSettings['noindex-elements'] = '1';
		$wpSEOSettings['display-metabox-pt-elements'] = '0';	
		$wpSEOSettings['noindex-universal'] = '1';
		$wpSEOSettings['display-metabox-pt-universal'] = '0';				
		$wpSEOSettings['noindex-tax-gallery-type'] = '1';	
		$wpSEOSettings['display-metabox-tax-gallery-type'] = '0';				
		$wpSEOSettings['noindex-tax-gallery-tags'] = '1';	
		$wpSEOSettings['display-metabox-tax-gallery-tags'] = '0';			
		$wpSEOSettings['noindex-ptarchive-galleries'] = '1';				
		$wpSEOSettings['noindex-tax-image-categories'] = '1';
		$wpSEOSettings['display-metabox-tax-image-categories'] = '0';	
		$wpSEOSettings['noindex-tax-image-tags'] = '1';	
		$wpSEOSettings['display-metabox-tax-image-tags'] = '0';	
		update_option( 'wpseo_titles', $wpSEOSettings );

		$wpSEOSocial = get_option( 'wpseo_social' );		
		if ( isset($GLOBALS['customer_info']['facebook']) ) $wpSEOSocial['facebook_site'] = $GLOBALS['customer_info']['facebook'];
		if ( isset($GLOBALS['customer_info']['instagram']) ) $wpSEOSocial['instagram_url'] = $GLOBALS['customer_info']['instagram'];
		if ( isset($GLOBALS['customer_info']['linkedin']) ) $wpSEOSocial['linkedin_url'] = $GLOBALS['customer_info']['linkedin'];
		$wpSEOSocial['og_default_image'] = get_bloginfo("url").'/wp-content/uploads/logo.png';
		$wpSEOSocial['og_default_image_id'] = attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' );
		$wpSEOSocial['opengraph'] = '1';
		if ( isset($GLOBALS['customer_info']['pinterest']) ) $wpSEOSocial['pinterest_url'] = $GLOBALS['customer_info']['pinterest'];
		if ( isset($GLOBALS['customer_info']['twitter']) ) $wpSEOSocial['twitter_site'] = $GLOBALS['customer_info']['twitter'];
		if ( isset($GLOBALS['customer_info']['youtube']) ) $wpSEOSocial['youtube_url'] = $GLOBALS['customer_info']['youtube'];	
		update_option( 'wpseo_social', $wpSEOSocial );

		$wpSEOLocal = get_option( 'wpseo_local' );
		if ( isset($GLOBALS['customer_info']['business-type']) ) :
			if ( $GLOBALS['customer_info']['business-type'] == "organization" || $GLOBALS['customer_info']['business-type'] == "public figure" ) $wpSEOLocal['business_type'] = 'Organization';
			if ( $GLOBALS['customer_info']['business-type'] == "" || $GLOBALS['customer_info']['business-type'] == "agriculture" || $GLOBALS['customer_info']['business-type'] == "animals" || $GLOBALS['customer_info']['business-type'] == "industrial" ) $wpSEOLocal['business_type'] = 'LocalBusiness';		

			if ( $GLOBALS['customer_info']['business-type'] == "auto body" ) $wpSEOLocal['business_type'] = 'AutoBodyShop';		
			if ( $GLOBALS['customer_info']['business-type'] == "automotive" ) $wpSEOLocal['business_type'] = 'AutomotiveBusiness';		
			if ( $GLOBALS['customer_info']['business-type'] == "book store" ) $wpSEOLocal['business_type'] = 'BookStore';	
			if ( $GLOBALS['customer_info']['business-type'] == "cleaning" || $GLOBALS['customer_info']['business-type'] == "landscaper" || $GLOBALS['customer_info']['business-type'] == "flooring contractor" || $GLOBALS['customer_info']['business-type'] == "stone" ) $wpSEOLocal['business_type'] = 'HomeAndConstructionBusiness';	
			if ( $GLOBALS['customer_info']['business-type'] == "clothing store" ) $wpSEOLocal['business_type'] = 'ClothingStore';	
			if ( $GLOBALS['customer_info']['business-type'] == "electrician" ) $wpSEOLocal['business_type'] = 'Electrician';	
			if ( $GLOBALS['customer_info']['business-type'] == "financial" ) $wpSEOLocal['business_type'] = 'FinancialService';	
			if ( $GLOBALS['customer_info']['business-type'] == "fire safety" || $GLOBALS['customer_info']['business-type'] == "professional" ) $wpSEOLocal['business_type'] = 'ProfessionalService';	
			if ( $GLOBALS['customer_info']['business-type'] == "fitness" ) $wpSEOLocal['business_type'] = 'ExerciseGym';	
			if ( $GLOBALS['customer_info']['business-type'] == "government" ) $wpSEOLocal['business_type'] = 'GovernmentOrganization';	
			if ( $GLOBALS['customer_info']['business-type'] == "motel" ) $wpSEOLocal['business_type'] = 'Motel';	
			if ( $GLOBALS['customer_info']['business-type'] == "musician" ) $wpSEOLocal['business_type'] = 'Store';	
			if ( $GLOBALS['customer_info']['business-type'] == "novelty store" ) $wpSEOLocal['business_type'] = 'MusicGroup';	
			if ( $GLOBALS['customer_info']['business-type'] == "physician" || $GLOBALS['customer_info']['business-type'] == "chiropractor" ) $wpSEOLocal['business_type'] = 'Physician';	
			if ( $GLOBALS['customer_info']['business-type'] == "resort" ) $wpSEOLocal['business_type'] = 'Resort';		
			if ( $GLOBALS['customer_info']['business-type'] == "restaurant" ) $wpSEOLocal['business_type'] = 'Restaurant';			
			if ( $GLOBALS['customer_info']['business-type'] == "real estate" ) $wpSEOLocal['business_type'] = 'RealEstateAgent';		
			if ( $GLOBALS['customer_info']['business-type'] == "tattoo shop" ) $wpSEOLocal['business_type'] = 'Tattoo parlor';	
		endif;

		if ( $GLOBALS['customer_info']['site-type'] == "hvac" ) $wpSEOLocal['business_type'] = 'HVACBusiness';		

		if ( isset($GLOBALS['customer_info']['street']) ) $wpSEOLocal['location_address'] = $GLOBALS['customer_info']['street'];
		if ( isset($GLOBALS['customer_info']['site-city']) ) $wpSEOLocal['location_city'] = $GLOBALS['customer_info']['site-city'];
		if ( isset($GLOBALS['customer_info']['state-full']) ) $wpSEOLocal['location_state'] = $GLOBALS['customer_info']['state-full'];
		if ( isset($GLOBALS['customer_info']['zip']) ) $wpSEOLocal['location_zipcode'] = $GLOBALS['customer_info']['zip'];
		$wpSEOLocal['location_country'] = 'US';
		if ( isset($GLOBALS['customer_info']['area']) && isset($GLOBALS['customer_info']['phone']) ) $wpSEOLocal['location_phone'] = $GLOBALS['customer_info']['area'].'-'.$GLOBALS['customer_info']['phone'];
		if ( isset($GLOBALS['customer_info']['email']) ) $wpSEOLocal['location_email'] = $GLOBALS['customer_info']['email'];
		$wpSEOLocal['location_url'] = get_bloginfo("url");
		$wpSEOLocal['location_price_range'] = '$$';
		$wpSEOLocal['location_payment_accepted'] = "Cash, Credit Cards, Paypal";
		if ( isset($GLOBALS['customer_info']['service-area']) ) $wpSEOLocal['location_area_served'] = $GLOBALS['customer_info']['service-area'];
		if ( isset($GLOBALS['customer_info']['lat']) ) $wpSEOLocal['location_coords_lat'] = $GLOBALS['customer_info']['lat'];
		if ( isset($GLOBALS['customer_info']['long']) ) $wpSEOLocal['location_coords_long'] = $GLOBALS['customer_info']['long'];
		$wpSEOLocal['hide_opening_hours'] = 'on';
		$wpSEOLocal['address_format'] = 'address-state-postal';
		update_option( 'wpseo_local', $wpSEOLocal );
	endif;

// Widget Options - Extended Settings
	if ( !is_plugin_active('extended-widget-options/plugin.php') ) :
		delete_option( 'bp_setup_widget_options_initial' );			
		battleplan_delete_prefixed_options( '_extended_widgetopts' );
		battleplan_delete_prefixed_options( '_transient_widgetopts' );
		battleplan_delete_prefixed_options( '_widgetopts' );
		battleplan_delete_prefixed_options( 'widgetopts' );
		battleplan_delete_prefixed_options( 'widget_' );
	endif;
	
// Blackhold for Bad Bots
	if ( is_plugin_active('blackhole-bad-bots/blackhole.php') ) : 	
		$blackholeSettings = get_option( 'bbb_options' );			
		$blackholeSettings['email_alerts'] = 1;
		$blackholeSettings['email_address'] = get_option( 'admin_email' );
		$blackholeSettings['email_from'] = get_option( 'wp_mail_smtp' )['mail']['from_email'];
		update_option( 'bbb_options', $blackholeSettings );
	endif;

// Basic Settings		
	$update_menu_order = array ('site-header'=>100, 'widgets'=>200, 'office-hours'=>700, 'coupon'=>700, 'site-message'=>800, 'site-footer'=>900);

	foreach ($update_menu_order as $page=>$order) :
		$updatePage = get_page_by_path($page, OBJECT, 'elements' );
		if ( !empty( $updatePage ) ) : 
			wp_update_post(array(
				'ID' 			 => $updatePage->ID,
				'menu_order'     => $order,
			));	
		endif;
	endforeach;

	update_option( 'blogname', $GLOBALS['customer_info']['name'] );
	$blogDesc = '';
	if ( $GLOBALS['customer_info']['city'] != '' ) $blogDesc .= $GLOBALS['customer_info']['city'];
	if ( $GLOBALS['customer_info']['city'] != '' && $GLOBALS['customer_info']['state-abbr'] != '' ) $blogDesc .= ', ';
	if ( $GLOBALS['customer_info']['state-abbr'] != '' ) $blogDesc .= $GLOBALS['customer_info']['state-abbr'];
	update_option( 'blogdescription', $blogDesc );
	update_option( 'admin_email', 'info@battleplanwebdesign.com' );
	update_option( 'admin_email_lifespan', '9999999999999' );
	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'wpe-rand-enabled', '1' );
	update_option( 'users_can_register', '0' );		
	update_option( 'wpe-rand-enabled', '1' );
	update_option( 'auto_update_core_dev', 'enabled' );
	update_option( 'auto_update_core_minor', 'enabled' );
	update_option( 'auto_update_core_major', 'enabled' );			

	battleplan_delete_prefixed_options( 'ac_cache_data_' );
	battleplan_delete_prefixed_options( 'ac_cache_expires_' );
	battleplan_delete_prefixed_options( 'ac_api_request_' );	
	battleplan_delete_prefixed_options( 'ac_sorting_' );
	battleplan_delete_prefixed_options( 'wp-smush-' );
	battleplan_delete_prefixed_options( 'wp_smush_' );
	battleplan_delete_prefixed_options( 'client_' );	
	
	battleplan_delete_prefixed_options( 'bp_track_content_' );					
	battleplan_delete_prefixed_options( 'bp_track_speed_' );	
	battleplan_delete_prefixed_options( 'bp_track_l_' );	
	battleplan_delete_prefixed_options( 'pct-viewed-' );

/*--------------------------------------------------------------
# Universal Pages
--------------------------------------------------------------*/

/* Add appropriate pages */
	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'american standard' || (is_array($GLOBALS['customer_info']['site-brand']) && in_array('american standard', $GLOBALS['customer_info']['site-brand']))) ) :
		if (is_null(get_page_by_path('customer-care-dealer', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Customer Care Dealer', 'post_content' => '[get-universal-page slug="page-hvac-customer-care-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('customer-care-dealer', OBJECT, 'universal'); if ( $getPage) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'ruud' || (is_array($GLOBALS['customer_info']['site-brand']) && in_array('ruud', $GLOBALS['customer_info']['site-brand']))) ) :
		if (is_null(get_page_by_path('ruud-pro-partner', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Ruud Pro Partner', 'post_content' => '[get-universal-page slug="page-hvac-ruud-pro-partner"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('ruud-pro-partner', OBJECT, 'universal'); if ( $getPage) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'comfortmaker' || (is_array($GLOBALS['customer_info']['site-brand']) && in_array('comfortmaker', $GLOBALS['customer_info']['site-brand']))) ) :
		if (is_null(get_page_by_path('comfortmaker-elite-dealer', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Comfortmaker Elite Dealer', 'post_content' => '[get-universal-page slug="page-hvac-comfortmaker-elite-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('comfortmaker-elite-dealer', OBJECT, 'universal'); if ( $getPage) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'tempstar' || (is_array($GLOBALS['customer_info']['site-brand']) && in_array('tempstar', $GLOBALS['customer_info']['site-brand']))) ) :
		if (is_null(get_page_by_path('tempstar-elite-dealer', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Tempstar Elite Dealer', 'post_content' => '[get-universal-page slug="page-hvac-tempstar-elite-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('tempstar-elite-dealer', OBJECT, 'universal'); if ( $getPage) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' ) :
		if (is_null(get_page_by_path('maintenance-tips', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Maintenance Tips', 'post_content' => '[get-universal-page slug="page-hvac-maintenance-tips"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('maintenance-tips', OBJECT, 'universal'); if ( $getPage) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' ) :
		if (is_null(get_page_by_path('symptom-checker', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Symptom Checker', 'post_content' => '[get-universal-page slug="page-hvac-symptom-checker"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('symptom-checker', OBJECT, 'universal'); if ( $getPage) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' ) :
		if (is_null(get_page_by_path('faq', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'FAQ', 'post_content' => '[get-universal-page slug="page-hvac-faq"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('faq', OBJECT, 'universal'); if ( $getPage) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'profile' ) :
		if (is_null(get_page_by_path('profile', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Profile', 'post_content' => '[get-universal-page slug="page-profile"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('profile', OBJECT, 'universal'); if ( $getPage) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'profile' ) :
		if (is_null(get_page_by_path('profile-directory', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Profile Directory', 'post_content' => '[get-universal-page slug="page-profile-directory"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('profile-directory', OBJECT, 'universal'); if ( $getPage) wp_delete_post( $getPage->ID, true );
	endif;

	/* Add generic pages */
	if ( is_null(get_page_by_path('privacy-policy', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Privacy Policy', 'post_content' => '[get-universal-page slug="page-privacy-policy"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

	if ( is_null(get_page_by_path('terms-conditions', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Terms & Conditions', 'post_content' => '[get-universal-page slug="page-terms-conditions"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

	if ( is_null(get_page_by_path('review', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Review', 'post_content' => '[get-universal-page slug="page-review"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

	if ( is_null(get_page_by_path('email-received', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Email Received', 'post_content' => '[get-universal-page slug="page-email-received"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

/*--------------------------------------------------------------
# Sync with Google Analytics
--------------------------------------------------------------*/

	$GLOBALS['customer_info'] = get_option('customer_info');
	$ga4_id = isset($GLOBALS['customer_info']['google-tags']['prop-id']) ? $GLOBALS['customer_info']['google-tags']['prop-id'] : null;
	$ua_id = isset($GLOBALS['customer_info']['google-tags']['ua-view']) ? $GLOBALS['customer_info']['google-tags']['ua-view'] : null;
	$client = new BetaAnalyticsDataClient(['credentials'=>get_template_directory().'/vendor/atomic-box-306317-0b19b6a3a6c1.json']);
	$today = $end = date( "Y-m-d" );	
	$rewind = date('Y-m-d', strtotime('-4 years'));
	if ( $rewind == '1970-01-01' ) $rewind = '2018-01-01';

	//$prevHits = get_option('bp_site_hits');		
	//foreach ( $prevHits as $hit=>$data ) :
	//	if ( strtotime($data['date'] ) >= strtotime($rewind) ) :
	//		unset($prevHits[$hit]);
	//	else:
	//		break;
	//	endif;				
	//endforeach;	

	$states = array('alabama'=>'AL', 'arizona'=>'AZ', 'arkansas'=>'AR', 'california'=>'CA', 'colorado'=>'CO', 'connecticut'=>'CT', 'delaware'=>'DE', 'dist of columbia'=>'DC', 'dist. of columbia'=>'DC', 'district of columbia'=>'DC', 'florida'=>'FL', 'georgia'=>'GA', 'idaho'=>'ID', 'illinois'=>'IL', 'indiana'=>'IN', 'iowa'=>'IA', 'kansas'=>'KS', 'kentucky'=>'KY', 'louisiana'=>'LA', 'maine'=>'ME', 'maryland'=>'MD', 'massachusetts'=>'MA', 'michigan'=>'MI', 'minnesota'=>'MN', 'mississippi'=>'MS', 'missouri'=>'MO', 'montana'=>'MT', 'nebraska'=>'NE', 'nevada'=>'NV', 'new hampshire'=>'NH', 'new jersey'=>'NJ', 'new mexico'=>'NM', 'new york'=>'NY', 'north carolina'=>'NC', 'north dakota'=>'ND', 'ohio'=>'OH', 'oklahoma'=>'OK', 'oregon'=>'OR', 'pennsylvania'=>'PA', 'rhode island'=>'RI', 'south carolina'=>'SC', 'south dakota'=>'SD', 'tennessee'=>'TN', 'texas'=>'TX', 'utah'=>'UT', 'vermont'=>'VT', 'virginia'=>'VA', 'washington'=>'WA', 'washington d.c.'=>'DC', 'washington dc'=>'DC', 'west virginia'=>'WV', 'wisconsin'=>'WI', 'wyoming'=>'WY');
	$removedStates = array('alaska'=>'AK', 'hawaii'=>'HI',);

// Gather GA4 Stats 
	if ( $ga4_id && substr($ga4_id, 0, 2) != '00' ) :
		$response = $client->runReport([
			'property' => 'properties/'.$ga4_id,
			'dateRanges' => [
				new DateRange([ 'start_date' => $rewind, 'end_date' => $today ]),
			],
			'dimensions' => [
				new Dimension([ 'name' => 'date' ]),
				new Dimension([ 'name' => 'city' ]),
				new Dimension([ 'name' => 'region' ]),	
				new Dimension([ 'name' => 'firstUserSourceMedium' ]),	
				new Dimension([ 'name' => 'pagePath' ]),
				new Dimension([ 'name' => 'browser' ]),		
				new Dimension([ 'name' => 'deviceCategory' ]),
				new Dimension([ 'name' => 'screenResolution' ]),
				new Dimension([ 'name' => 'pageReferrer' ]),
			],
			'metrics' => [
				new Metric([ 'name' => 'screenPageViews' ]),
				new Metric([ 'name' => 'engagedSessions' ]),
				new Metric([ 'name' => 'newUsers' ]),
			]
		]);

		foreach ( $response->getRows() as $row ) :
			$date = $row->getDimensionValues()[0]->getValue();
			$city = $row->getDimensionValues()[1]->getValue();
			$state = strtolower($row->getDimensionValues()[2]->getValue());
			if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
			$source = $row->getDimensionValues()[3]->getValue();	
			$page = $row->getDimensionValues()[4]->getValue();
			$browser = $row->getDimensionValues()[5]->getValue();
			$device = $row->getDimensionValues()[6]->getValue();
			$resolution = $row->getDimensionValues()[7]->getValue();							
			$referrer = $row->getDimensionValues()[8]->getValue();							
			$pagesViewed = $row->getMetricValues()[0]->getValue();				
			$engaged = $row->getMetricValues()[1]->getValue();							
			$newUsers = $row->getMetricValues()[2]->getValue();							

			if ( isset($states[$state]) ) :					
				if ( $city == '(not set)' ) $location = ucwords($state);					
				$page = rtrim($page, '/\\');

				$analyticsGA4[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'pages-viewed'=>$pagesViewed, 'resolution'=>$resolution, 'referrer'=>$referrer, 'engaged'=>$engaged, 'new-users'=>$newUsers );	
			endif;
		endforeach;			
		if ( is_array($analyticsGA4) ) :
			arsort($analyticsGA4);
			$end = date('Y-m-d', strtotime(end($analyticsGA4)['date']));
		endif;

		// Split session data into site hits
		foreach ( $analyticsGA4 as $analyze ) :
			$date = isset($analyze['date']) ? $analyze['date'] : null;
			$location = isset($analyze['location']) ? $analyze['location'] : null;
			$page = isset($analyze['page']) ? $analyze['page'] : null;
			$browser = isset($analyze['browser']) ? $analyze['browser'] : null;
			$device = isset($analyze['device']) ? $analyze['device'] : null;
			$resolution = isset($analyze['resolution']) ? $analyze['resolution'] : null;
			$pageviews = isset($analyze['pages-viewed']) ? $analyze['pages-viewed'] : null;
			$sessions = isset($analyze['sessions']) ? $analyze['sessions'] : null;
			$engaged = isset($analyze['engaged']) ? $analyze['engaged'] : null;
			$newUsers = isset($analyze['new-users']) ? $analyze['new-users'] : null;
			$referrer = isset($analyze['referrer']) ? $analyze['referrer'] : null;
			list($source, $medium) = explode(" / ", $analyze['source']);

			if ( strpos( $referrer, parse_url( get_site_url(), PHP_URL_HOST ) ) === false ) :	
				$siteHitsGA4[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'medium'=>$medium, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'resolution'=>$resolution, 'pages-viewed'=>$pageviews, 'sessions'=>'1', 'engaged'=>$engaged, 'new-users'=>$newUsers );	
			else :
				$siteHitsGA4[] = array ('date'=>$date, 'page'=>$page, 'pages-viewed'=>$pageviews, 'sessions'=>'0', 'engaged'=>$engaged );				
			endif;
		endforeach;

		update_option('bp_site_hits_ga4', $siteHitsGA4, false);		
	endif;

// Gather UA Stats	
	$siteHitsUA1 = get_option('bp_site_hits_ua_1') ? get_option('bp_site_hits_ua_1') : array();
	$siteHitsUA2 = get_option('bp_site_hits_ua_2') ? get_option('bp_site_hits_ua_2') : array();
	$siteHitsUA3 = get_option('bp_site_hits_ua_3') ? get_option('bp_site_hits_ua_3') : array();
	$siteHitsUA4 = get_option('bp_site_hits_ua_4') ? get_option('bp_site_hits_ua_4') : array();
	$siteHitsUA5 = get_option('bp_site_hits_ua_5') ? get_option('bp_site_hits_ua_5') : array();
	//$siteHitsUA = array_merge( $siteHitsUA1, $siteHitsUA2, $siteHitsUA3, $siteHitsUA4, $siteHitsUA5);
	
	if ( $siteHitsUA1 ) $siteHitsUA = $siteHitsUA1;
	if ( $siteHitsUA2 && is_array($siteHitsUA2) && is_array($siteHitsUA) ) $siteHitsUA = array_merge( $siteHitsUA, $siteHitsUA2 );
	if ( $siteHitsUA3 && is_array($siteHitsUA3) && is_array($siteHitsUA) ) $siteHitsUA = array_merge( $siteHitsUA, $siteHitsUA3 );
	if ( $siteHitsUA4 && is_array($siteHitsUA4) && is_array($siteHitsUA) ) $siteHitsUA = array_merge( $siteHitsUA, $siteHitsUA4 );
	if ( $siteHitsUA5 && is_array($siteHitsUA5) && is_array($siteHitsUA) ) $siteHitsUA = array_merge( $siteHitsUA, $siteHitsUA5 );

	if ( $ua_id && substr($ua_id, 0, 2) != '00' && !get_option('bp_analytics_ua_complete') ) :		
	
		if ( $siteHitsUA ) $end = date('Y-m-d', strtotime(end($siteHitsUA)['date']));	

		$end = date('Y-m-d', strtotime($end.' - 1 day'));
		$rewind = date('Y-m-d', strtotime($end.' - 80 days'));
		
		if ( strtotime($end) < 1532995200 || strtotime($end) < strtotime(get_option('bp_launch_date')) ) : // July 31, 2018		
			updateOption('bp_analytics_ua_complete', date('Y-m-d'), false);		
		else:
		
			function initializeAnalytics() {
				$client = new Google_Client();
				$client->setApplicationName("Stats Reporting");
				$client->setAuthConfig(get_template_directory().'/vendor/atomic-box-306317-0b19b6a3a6c1.json');
				$client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
				$initAnalytics = new Google_Service_Analytics($client);
				return $initAnalytics; 
			}

			function getResults($initAnalytics, $ua_id, $start_date, $end_date, $param2, $param1) {
				return $initAnalytics->data_ga->get ( 'ga:'.$ua_id, $start_date, $end_date, $param1, $param2 );
			}

			$initAnalytics = initializeAnalytics();	

			if ( strtotime($rewind) < 1590969600 ) :
				$param2 = array('dimensions'=>'ga:date, ga:city, ga:region, ga:date, ga:date, ga:date, ga:date', 'max-results'=>10000);
				$param1 = 'ga:users, ga:users, ga:users, ga:users';
			else :		
				$param2 = array('dimensions'=>'ga:date, ga:city, ga:region, ga:sourceMedium, ga:pagePath, ga:browser, ga:deviceCategory', 'max-results'=>10000);
				$param1 = 'ga:pageviews, ga:sessions, ga:bounces, ga:newUsers';
			endif;

			$results = getResults($initAnalytics, $ua_id, $rewind, $end, $param2, $param1);

			foreach ( $results->getRows() as $row ) : 
				$date = $row[0];
				$city = $row[1];
				$state = strtolower($row[2]);
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$source = $row[3];
				if ( $source == $date ) $source = "";
				$page = $row[4];
				if ( $page == $date ) $page = "";
				$browser = $row[5];
				if ( $browser == $date ) $browser = "";
				$device = $row[6];		
				if ( $device == $date ) $device = "";
				$pagesViewed = $row[7];		
				$sessions = $row[8];
				if ( $sessions == $pagesViewed ) $pagesViewed = "";
				if ( $row[9] != $date ) $engaged = $sessions - $row[9];			
				$newUsers = $row[10];						

				if ( isset($states[$state]) && $sessions != '' ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);					
					$page = rtrim($page, '/\\');

					$analyticsUA[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'pages-viewed'=>$pagesViewed, 'sessions'=>$sessions, 'engaged'=>$engaged, 'new-users'=>$newUsers );	
				endif;
			endforeach;

			if ( is_array($analyticsUA) ) arsort($analyticsUA);		
			//$siteHitsUA = get_option('bp_site_hits_ua');

			// Split session data into site hits
			foreach ( $analyticsUA as $analyze ) :
				if ( isset ($analyze['date']) ) $date = $analyze['date'];
				if ( isset ($analyze['location']) ) $location = $analyze['location'];
				if ( isset ($analyze['page']) ) $page = $analyze['page'];
				if ( isset ($analyze['browser']) ) $browser = $analyze['browser'];
				if ( isset ($analyze['device']) ) $device = $analyze['device'];	
				if ( isset ($analyze['resolution']) ) $resolution = $analyze['resolution'];
				if ( isset ($analyze['pages-viewed']) ) $pageviews = (int)$analyze['pages-viewed'];
				if ( isset ($analyze['sessions']) ) $sessions = (int)$analyze['sessions'];
				if ( isset ($analyze['engaged']) ) $engaged = (int)$analyze['engaged'];
				if ( isset ($analyze['new-users']) ) $newUsers = (int)$analyze['new-users'];
				if ( isset ($analyze['referrer']) ) $referrer = (int)$analyze['referrer'];
				if ( isset ($analyze['source']) ) list($source, $medium) = explode(" / ", $analyze['source']);			

				if ( $sessions > 1 ) :			
					$pageviews = $pageviews / $sessions;
					$engaged = $engaged / $sessions;
					$newUsers = $newUsers / $sessions;

					for ( $x=0;$x<$sessions;$x++) :			
						$siteHitsUA[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'medium'=>$medium, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'pages-viewed'=>$pageviews, 'sessions'=>'1', 'engaged'=>$engaged, 'new-users'=>$newUsers );	
					endfor;

				elseif ( $sessions == 1 ) :			
					$siteHitsUA[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'medium'=>$medium, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'pages-viewed'=>$pageviews, 'sessions'=>'1', 'engaged'=>$engaged, 'new-users'=>$newUsers );	

				elseif ( $sessions == 0 ) :
					$siteHitsUA[] = array ('date'=>$date, 'page'=>$page, 'pages-viewed'=>$pageviews, 'sessions'=>'0', 'engaged'=>$engaged );				
				endif;
			endforeach;

			if ( is_array($siteHitsUA) && array_slice($siteHitsUA, 160000, 40000) ) updateOption('bp_site_hits_ua_5', array_slice($siteHitsUA, 160000, 40000), false);	
			if ( is_array($siteHitsUA) && array_slice($siteHitsUA, 120000, 40000) ) updateOption('bp_site_hits_ua_4', array_slice($siteHitsUA, 120000, 40000), false);	
			if ( is_array($siteHitsUA) && array_slice($siteHitsUA, 80000, 40000) ) updateOption('bp_site_hits_ua_3', array_slice($siteHitsUA, 80000, 40000), false);	
			if ( is_array($siteHitsUA) && array_slice($siteHitsUA, 40000, 40000) ) updateOption('bp_site_hits_ua_2', array_slice($siteHitsUA, 40000, 40000), false);	
			if ( is_array($siteHitsUA) && array_slice($siteHitsUA, 0, 40000) ) updateOption('bp_site_hits_ua_1', array_slice($siteHitsUA, 0, 40000), false);	
		endif;
	endif;

	$siteHits = $siteHitsGA4;
	if ( $siteHitsUA && is_array($siteHitsGA4) ) $siteHits = array_merge($siteHitsGA4, $siteHitsUA);

// Compile hits on specific pages
	$pageCounts = array(1, 7, 30, 90, 365);
	$today = strtotime($today);		
	$citiesToExclude = array('Orangetree, FL', 'Ashburn, VA', 'Boardman, OR'); // also change in functions-admin.php

	foreach ( $siteHits as $siteHit ) :		
		$page = $siteHit['page'];
		if ( isset($siteHit['location']) && !in_array( $siteHit['location'], $citiesToExclude) && strpos($page, 'fbclid') === false && strpos($page, 'reportkey') === false ) :					
			if ( $page == "" || $page == "/" ) $page = "Home";
			if ( isset($compilePaths) && is_array($compilePaths) && array_key_exists($page, $compilePaths ) ) :
				$compilePaths[$page] += (int)$siteHit['pages-viewed'];
			else:
				$compilePaths[$page] = (int)$siteHit['pages-viewed'];
			endif;
		endif;

		$checkDate = strtotime($siteHit['date']);
		$howLong = $today - $checkDate;

		foreach ( $pageCounts as $count ) :
			if ( $howLong > (($count + 1) * 86400) ) :				
				foreach ($compilePaths as $page=>$pageViews) :

					if ( $page == "Home" ) :
						$id = get_option('page_on_front');					
					else:
						$id = getID($page);
					endif;				
					if ( $count == 1) : $pageKey = 'log-views-today';
					else: $pageKey = 'log-views-total-'.$count.'day'; endif;

					updateMeta($id, $pageKey, $pageViews);

				endforeach;
				array_shift($pageCounts);						
			endif;
		endforeach;	
	endforeach;	

	// Gather Content Tracking & Speed Tracking stats
	if ( $ga4_id && is_admin() ) :
		$today = date( "Y-m-d" );	
		$rewind = date('Y-m-d', strtotime($today.' - 31 days'));

		$response = $client->runReport([
			'property' => 'properties/'.$ga4_id,
			'dateRanges' => [
				new DateRange([ 'start_date' => $rewind, 'end_date' => $today ]),
			],
			'dimensions' => [
				new Dimension([ 'name' => 'achievementId' ]),
				new Dimension([ 'name' => 'groupId' ]),
				new Dimension([ 'name' => 'city' ]),
				new Dimension([ 'name' => 'region' ]),	
			],
		]);

		foreach ( $response->getRows() as $row ) :
			$content_tracking = $row->getDimensionValues()[0]->getValue();			
			$site_speed = $row->getDimensionValues()[1]->getValue();
			$city = $row->getDimensionValues()[2]->getValue();	
			$state = strtolower($row->getDimensionValues()[3]->getValue());
			if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
			if ( $city == '(not set)' ) $location = ucwords($state);	
			
			$allTracking[] = array('content'=>$content_tracking, 'speed'=>$site_speed, 'location'=>$location);		
			updateOption('bp_tracking_content', $allTracking, false);		
		endforeach;
	endif;

	updateOption( 'bp_chrons_pages', 0, true );
	
	$to = get_option( 'admin_email' );
	$subject = "Chron Job: ".get_bloginfo('url');
	$txt = "User Agent: ".$_SERVER['HTTP_USER_AGENT']."\r\n";
	if ( _IS_BOT == true ) $txt .= "Flagged as Bot\r\n";
	if ( _IS_GOOGLEBOT == true ) $txt .= "Flagged as Googlebot\r\n";
	if ( _IS_BOT == false ) $txt .= "Not a Bot\r\n";
	if ( _IS_GOOGLEBOT == false ) $txt .= "Not a Googlebot\r\n";
	$headers = "From: ".get_option( 'wp_mail_smtp' )['mail']['from_email'];

	mail($to,$subject,$txt,$headers);

	//updateOption( 'bp_chrons_rewind', date('Y-m-d', strtotime("-2 days")));
endif;	
?>