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

require_once get_template_directory().'/vendor/autoload.php';
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;

// delete all options that begin with {$prefix}
function battleplan_delete_prefixed_options( $prefix ) {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'" );
}	


//if ( get_option('bp_setup_2023_09_15') != "completed" ) :

	//add_action("init", "bp_remove_cron_job"); 
	//function bp_remove_cron_job() {
	//	wp_clear_scheduled_hook("wphb_clear_logs"); 
	//	wp_clear_scheduled_hook("wphb_minify_clear_files"); 
	//	wp_clear_scheduled_hook("wpmudev_scheduled_jobs"); 
	//}

	//battleplan_delete_prefixed_options( 'bp_admin_btn' );
	//delete_option('bp_admin_settings');

	//delete_option('bp_setup_2023_08_15');
	//updateOption( 'bp_setup_2023_09_15', 'completed', false );	
//endif;

delete_option('bp_setup_2023_09_15');

/*
if ( get_option('bp_product_upload_2024_03_18') != "completed" && $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'american standard' || $GLOBALS['customer_info']['site-brand'] == 'American Standard' || (is_array($GLOBALS['customer_info']['site-brand']) && ( in_array('american standard', $GLOBALS['customer_info']['site-brand']) || in_array('American Standard', $GLOBALS['customer_info']['site-brand'])) ))) :		
 	require_once get_template_directory().'/includes/include-hvac-products/includes-american-standard-products.php';
	updateOption( 'bp_product_upload_2024_03_18', 'completed', false );		
endif; 
*/


// Determine if Chron should run
$forceChron = get_option('bp_force_chron') !== null ? get_option('bp_force_chron') : 'false';
$chronTime = get_option('bp_chron_time') !== null ? get_option('bp_chron_time') : 0;
$chronDue = $chronTime + rand(40000,70000);

if ( $forceChron == true || ( _IS_BOT && !_IS_GOOGLEBOT && ( $chronDue < time() ) )) :
	delete_option('bp_force_chron');
	update_option('bp_chron_time', time());
	processChron($forceChron);
endif;
	
function processChron($forceChron) {
	if (function_exists('battleplan_remove_user_roles')) battleplan_remove_user_roles();
	if (function_exists('battleplan_create_user_roles')) battleplan_create_user_roles();
	
	$site = str_replace('https://', '', get_bloginfo('url'));	
	$exempt = $site == "sweetiepiesribeyes.com" || $site == "bubbascookscountry.com" || $site == "babeschicken.com" || $site == "airzoneexperts.com" ? "true" : "false";
	
	if ( $site != "asairconditioning.com") :	
// WP Mail SMTP Settings Update
		if ( is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') ) : 	
			if ( $exempt == "true" ) :	
				$apiKey2 = "-b916aeccb98bf3fcca73";
				$apiKey3 = "a606526cefdf92084ce7a9048d5cf734124e09f9bb26";
				$apiKey4 = "-YcYFamx5FrGvCxXe";
				$wpMailSettings['mail']['from_email'] = 'customer@website.'.$site;
				$wpMailSettings['sendinblue']['domain'] = 'website.'.$site;				
			else :	
				$apiKey2 = "-d08cc84fe45b37a420ef3";
				$apiKey3 = "a9074e001fa21f640578f699994cba854489d3ef793";
				$apiKey4 = "-bzWkS9dgt05KccIF";
				$wpMailSettings['mail']['from_email'] = 'email@admin.'.$site;
				$wpMailSettings['sendinblue']['domain'] = 'admin.'.$site;				
			endif;

			$apiKey1 = "keysib";
			$wpMailSettings = get_option( 'wp_mail_smtp' );			
			$wpMailSettings['mail']['from_name'] = strip_tags('Website · '.str_replace(',', '', $GLOBALS['customer_info']['name']));
			$wpMailSettings['mail']['mailer'] = 'sendinblue';
			$wpMailSettings['mail']['from_email_force'] = '1';
			$wpMailSettings['mail']['from_name_force'] = '1';	
			$wpMailSettings['sendinblue']['api_key'] = 'x'.$apiKey1.$apiKey2.$apiKey3.$apiKey4;
			update_option( 'wp_mail_smtp', $wpMailSettings );
		endif;
	endif;
		
// Contact Form 7 Settings Update
	if ( is_plugin_active('contact-form-7/wp-contact-form-7.php') ) : 
		$forms = get_posts( array ( 'numberposts'=>-1, 'post_type'=>'wpcf7_contact_form' ));
		foreach ( $forms as $form ) :
			$formID = $form->ID;
			$formMail = readMeta( $formID, "_mail" );
			$formTitle = get_the_title($formID);

			if ( $formTitle == "Quote Request Form" ) $formTitle = "Quote Request";
			if ( $formTitle == "Contact Us Form" ) $formTitle = "Customer Contact";		
			if ( $formTitle == "Request A Catering Quote" ) $formTitle = "Catering Quote";	
	
			$server_email = $site != "asairconditioning.com" ? "<email@admin.".str_replace('https://', '', get_bloginfo('url')).">" : "<aswebform@asairconditioning.com>";

			if ( $exempt != "true" ) :					
				$formMail['subject'] = $formTitle." · [user-name]";	
				$formMail['sender'] = "Website · ".str_replace(',', '', $GLOBALS['customer_info']['name'])." ".$server_email;
				$formMail['additional_headers'] = "Reply-to: [user-name] <[user-email]>\nBcc: Website Administrator <email@battleplanwebdesign.com>";
			endif;
	
			$formMail['use_html'] = 1;
			$formMail['exclude_blank'] = 1;

			updateMeta( $formID, "_mail", $formMail );	
		endforeach;
	endif; 

// Yoast SEO Settings Update
	if ( is_plugin_active('wordpress-seo-premium/wp-seo-premium.php') ) :	
		$schema = get_option( 'bp_schema' );
		$wpSEOBase = get_option( 'wpseo' );		
		$wpSEOBase['enable_admin_bar_menu'] = 0;
		$wpSEOBase['enable_cornerstone_content'] = 0;
		$wpSEOBase['enable_xml_sitemap'] = 1;		
		$wpSEOBase['remove_feed_global'] = 1;
		$wpSEOBase['remove_feed_global_comments'] = 1;
		$wpSEOBase['remove_feed_post_comments'] = 1;
		$wpSEOBase['remove_feed_authors'] = 1;
		$wpSEOBase['remove_feed_categories'] = 1;
		$wpSEOBase['remove_feed_tags'] = 1;
		$wpSEOBase['remove_feed_custom_taxonomies'] = 1;
		$wpSEOBase['remove_feed_post_types'] = 1;
		$wpSEOBase['remove_feed_search'] = 1;
		$wpSEOBase['remove_atom_rdf_feeds'] = 1;
		$wpSEOBase['remove_shortlinks'] = 1;
		$wpSEOBase['remove_rest_api_links'] = 1;
		$wpSEOBase['remove_rsd_wlw_links'] = 1;
		$wpSEOBase['remove_oembed_links'] = 1;
		$wpSEOBase['remove_generator'] = 1;
		$wpSEOBase['remove_emoji_scripts'] = 1;
		$wpSEOBase['remove_powered_by_header'] = 1;
		$wpSEOBase['remove_pingback_header'] = 1;
		$wpSEOBase['clean_campaign_tracking_urls'] = 1;
		$wpSEOBase['clean_permalinks'] = 1;
		$wpSEOBase['clean_permalinks_extra_variables'] = 'loc,int,invite,rs,se_action';	
		$wpSEOBase['search_cleanup'] = 1;
		$wpSEOBase['search_cleanup_emoji'] = 1;
		$wpSEOBase['search_cleanup_patterns'] = 1;
		$wpSEOBase['deny_search_crawling'] = 1;
		$wpSEOBase['deny_wp_json_crawling'] = 1;
		$wpSEOBase['redirect_search_pretty_urls'] = 1;
		update_option( 'wpseo', $wpSEOBase );	
	
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
			if ( $postType == "post" || $postType == "page" || $postType == "universal" || $postType == "products" || $postType == "landing" || $postType == "events" ) :
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
		if (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/logo.png' ) ) : $logoFile = "logo.png";
		elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/logo.webp' ) ) : $logoFile = "logo.webp";
		elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/logo.jpg' ) ) : $logoFile = "logo.jpg";
		elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon.png' ) ) : $logoFile = "site-icon.png";
		elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon.jpg' ) ) : $logoFile = "site-icon.jpg";
		elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon.webp' ) ) : $logoFile = "site-icon.webp";
		elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/favicon.png' ) ) : $logoFile = "favicon.png";
		elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/favicon.jpg' ) ) : $logoFile = "favicon.jpg";
		elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/favicon.webp' ) ) : $logoFile = "favicon.webp";
		endif;	
		$wpSEOSettings['company_logo'] = $schema['company_logo'] = $logoFile;
		$wpSEOSettings['company_logo_id'] = attachment_url_to_postid( $logoFile );
		$wpSEOSettings['company_logo_meta']['url'] = $logoFile;	
		$wpSEOSettings['company_logo_meta']['path'] = get_attached_file( attachment_url_to_postid( $logoFile ) );
		$wpSEOSettings['company_logo_meta']['id'] = attachment_url_to_postid( $logoFile );
		$wpSEOSettings['company_name'] = get_bloginfo('name');
		$wpSEOSettings['company_or_person'] = 'company';
		$wpSEOSettings['stripcategorybase'] = '1';
		$wpSEOSettings['breadcrumbs-enable'] = '1';				
		$wpSEOSettings['noindex-ptarchive-optimized'] = '1';			
		$wpSEOSettings['noindex-testimonials'] = '1';					
		$wpSEOSettings['noindex-ptarchive-testimonials'] = '1';	
		$wpSEOSettings['display-metabox-pt-testimonials'] = '0';
		$wpSEOSettings['noindex-elements'] = '1';
		$wpSEOSettings['display-metabox-pt-elements'] = '0';	
		$wpSEOSettings['noindex-products'] = '1';						
		$wpSEOSettings['noindex-ptarchive-products'] = '1';	
		$wpSEOSettings['display-metabox-pt-products'] = '0';	
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
		$wpSEOSocial['og_default_image'] = $logoFile;
		$wpSEOSocial['og_default_image_id'] = attachment_url_to_postid( $logoFile );
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

		if ( $GLOBALS['customer_info']['site-type'] == "hvac" ) :
			$wpSEOLocal['business_type'] = $schema['business_type'] = 'HVACBusiness';
		 	$schema['additional_type'] = "Heating,_ventilation,_and_air_conditioning";	
		else:
			$schema['business_type'] = $wpSEOLocal['business_type'];
		endif;

		if ( isset($GLOBALS['customer_info']['street']) ) $wpSEOLocal['location_address'] = $GLOBALS['customer_info']['street'];
		if ( isset($GLOBALS['customer_info']['city']) ) $wpSEOLocal['location_city'] = $GLOBALS['customer_info']['city'];
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
		if ( isset($GLOBALS['customer_info']['hours']) && $GLOBALS['customer_info']['hours'] != "na" ) :
			$wpSEOLocal['hide_opening_hours'] = 'off';				
			$days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
			$num = 0;
			$schema['hours'] = null;
			foreach( $days as $day) :			
				$wpSEOLocal['opening_hours_'.$day.'_from'] = $GLOBALS['customer_info']['hours']['periods'][$num]['open']['time'] != "" ? rtrim(chunk_split(substr($GLOBALS['customer_info']['hours']['periods'][$num]['open']['time'],-4),2,':'),':') : "Closed";				
				$wpSEOLocal['opening_hours_'.$day.'_to'] = $GLOBALS['customer_info']['hours']['periods'][$num]['close']['time'] != "" ? rtrim(chunk_split(substr($GLOBALS['customer_info']['hours']['periods'][$num]['close']['time'],-4),2,':'),':') : "Closed";			
				$wpSEOLocal['opening_hours_'.$day.'_second_from'] = $wpSEOLocal['opening_hours_'.$day.'_second_to'] = "";
				
				$schema['hours'] .= ucwords(substr($day, 0, 2)).' '.$wpSEOLocal['opening_hours_'.$day.'_from'];
				$schema['hours'] .= $wpSEOLocal['opening_hours_'.$day.'_from'] != "Closed" ? '-'.$wpSEOLocal['opening_hours_'.$day.'_to'].' ' : ' ';
				$num++;			
			endforeach;
		else:
			$wpSEOLocal['hide_opening_hours'] = 'on';		
		endif;				
		$wpSEOLocal['location_timezone'] = get_option('timezone_string');		
		$wpSEOLocal['address_format'] = 'address-state-postal';
		update_option( 'wpseo_local', $wpSEOLocal );
		update_option( 'bp_schema', $schema );
	endif;
	
// Basic Settings		
	$update_menu_order = array ('site-header'=>100, 'widgets'=>200, 'office-hours'=>700, 'hours'=>700, 'coupon'=>700, 'site-message'=>800, 'site-footer'=>900);

	foreach ($update_menu_order as $page=>$order) :
		$updatePage = get_page_by_path($page, OBJECT, 'elements' );
		if ( !empty( $updatePage ) ) : 
			wp_update_post(array(
				'ID' 		  		=> $updatePage->ID,
				'menu_order'    	=> $order,
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
	update_option( 'auto_update_core_dev', 'enabled' );
	update_option( 'auto_update_core_minor', 'enabled' );
	update_option( 'auto_update_core_major', 'enabled' );			

	battleplan_delete_prefixed_options( 'ac_cache_data_' );
	battleplan_delete_prefixed_options( 'ac_cache_expires_' );
	battleplan_delete_prefixed_options( 'ac_api_request_' );	
	battleplan_delete_prefixed_options( 'ac_sorting_' );
	battleplan_delete_prefixed_options( 'client_' );
	
	battleplan_fetch_background_image(true);
	battleplan_fetch_site_icon(true);
	

	
	
	if ( get_option('jobsite_geo') && get_option('jobsite_geo')['install'] == 'true' && get_option('bp_setup_2024_07_09') != "completed" ) :
	
		add_action("init", "bp_add_terms_to_jobsites"); 
		function bp_add_terms_to_jobsites() {
	
			$stateAbbrs = ["Alabama" => "AL", "Alaska" => "AK", "Arizona" => "AZ", "Arkansas" => "AR", "California" => "CA", "Colorado" => "CO", "Connecticut" => "CT", "Delaware" => "DE", "Florida" => "FL", "Georgia" => "GA", "Hawaii" => "HI", "Idaho" => "ID", "Illinois" => "IL", "Indiana" => "IN", "Iowa" => "IA", "Kansas" => "KS",
			"Kentucky" => "KY", "Louisiana" => "LA", "Maine" => "ME", "Maryland" => "MD", "Massachusetts" => "MA", "Michigan" => "MI", "Minnesota" => "MN", "Mississippi" => "MS",
			"Missouri" => "MO", "Montana" => "MT", "Nebraska" => "NE", "Nevada" => "NV", "New Hampshire" => "NH", "New Jersey" => "NJ", "New Mexico" => "NM", "New York" => "NY",
			"North Carolina" => "NC", "North Dakota" => "ND", "Ohio" => "OH", "Oklahoma" => "OK", "Oregon" => "OR", "Pennsylvania" => "PA", "Rhode Island" => "RI", "South Carolina" => "SC", "South Dakota" => "SD", "Tennessee" => "TN", "Texas" => "TX", "Utah" => "UT", "Vermont" => "VT", "Virginia" => "VA", "Washington" => "WA", "West Virginia" => "WV",
			"Wisconsin" => "WI", "Wyoming" => "WY", "Tex" => "TX", "Calif" => "CA", "Penn" => "PA"];

			$equipment = [
				'air-conditioner' => ['air conditioner', 'air conditioning', 'cooling', 'a/c', 'compressor', 'evaporator coil', 'condenser coil'],
				'heating' => ['heater', 'heating', 'furnace'],
				'thermostat' => ['thermostat', 't-stat', 'tstat']
			];

			$query = new WP_Query(['post_type' => 'jobsite_geo', 'posts_per_page' => -1]);

			if ($query->have_posts()) {
				while ($query->have_posts()) {
					$query->the_post();
					$post_id = get_the_ID();
					$city = trim(esc_attr(get_field("city")));
					$state = strtoupper(trim(esc_attr(get_field("state"))));
					$description = get_post_field('post_content', $post_id);
					$type = esc_attr(get_field("new_brand")) ? '-installation' : '-repair';
					$service = '';


					foreach ($stateAbbrs as $name => $abbreviation) {
						if ($state === strtoupper($name)) {
							$state = $abbreviation;
							break;
						}
					}

					$location = $city . '-' . $state;

					foreach (['maintenance', 'tune up', 'tune-up', 'check up', 'check-up', 'inspection'] as $keyword) {
						if (stripos($description, $keyword) !== false) {
							$service = 'hvac-maintenance';
							break;
						}
					}

					if (!$service) {
						foreach ($equipment as $tag => $keywords) {
							foreach ($keywords as $keyword) {
								if (stripos($description, $keyword) !== false) {
									$service = $tag . $type;
									break 2;
								}
							}
						}
					}

					if ($service && $location) {
						wp_set_object_terms($post_id, $service . '--' . strtolower($location), 'jobsite_geo-services', false);
					}
				}
				wp_reset_postdata();
			}	
		}
	
		updateOption( 'bp_setup_2024_07_09', 'completed', false );	
	
	endif;
	
	
	
	
		
// Prune weak testimonials
	/*
	$args = array ( 'post_type' => 'testimonials', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' );
	$getTestimonials = new WP_Query($args);
	
  	while ($getTestimonials->have_posts()) : $getTestimonials->the_post();
		$quality = get_field( "testimonial_quality" );	
        if ( $getTestimonials->found_posts > 20 && !has_post_thumbnail() && $quality[0] != 1 && strlen(wp_strip_all_tags(get_the_content(), true)) < 300 ) $draft = get_the_id();	
		if ( has_post_thumbnail() && $quality[0] != 1 ) :
			$quality[0] = 1;
			update_field('testimonial_quality', $quality);
		endif;
    endwhile; 

	wp_reset_postdata();
    if ( $draft ) wp_update_post( array ( 'ID' => $draft, 'post_status' => 'draft' ));
*/
	
/*--------------------------------------------------------------
# Update 'customer_info' with Google Business Profile info
--------------------------------------------------------------*/	
	$updateInfo = false;
	$customer_info = get_option('customer_info');
	$pidSync = $customer_info['pid-sync'] == "true" ? true : false;
	$placeIDs = $customer_info['pid'];
	if ( isset($placeIDs) ) :
		$apiKey = "AIzaSyBqf";
		$apiKey .= "0idxwuOxaG";
		$apiKey .= "-j3eCpef1Bunv";
		$apiKey .= "-YVdVP8";	
		$googleInfo = get_option('bp_gbp_update');
		$today = strtotime(date("F j, Y"));	
		$daysSinceCheck = $today - intval($googleInfo['date']);
		if ( !is_array($placeIDs) ) $placeIDs = array($placeIDs);
		
		if ( $forceChron == true || $daysSinceCheck > 5 ) :
			$gRating = $gNumber = 0;		
			foreach ( $placeIDs as $placeID ) :	
				if ( strlen($placeID) > 10 ) :
					$updateInfo = true;
					$url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=".$placeID."&key=".$apiKey;
					$ch = curl_init();
					curl_setopt ($ch, CURLOPT_URL, $url);
					curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
					$result = curl_exec ($ch);
					$res = json_decode($result,true);
					
					$googleInfo[$placeID]['google-reviews'] = $res['result']['user_ratings_total'];						
					$googleInfo[$placeID]['google-rating'] = $res['result']['rating'];						
					$gNumber = $gNumber + $res['result']['user_ratings_total'];
					$gRating = $gRating + ( $res['result']['rating'] * $res['result']['user_ratings_total'] );	
					
					$googleInfo[$placeID]['area'] = preg_replace('/(.*)\((.*)\)(.*)/s', '\2', $res['result']['formatted_phone_number']);	
					$googleInfo[$placeID]['phone'] = substr($res['result']['formatted_phone_number'], strpos($res['result']['formatted_phone_number'], ") ") + 2);					
					if ( str_contains($customer_info['area-after'], '.') ) $googleInfo[$placeID]['phone'] = str_replace('-', '.', $googleInfo[$placeID]['phone']);			
					$googleInfo[$placeID]['phone-format'] = $customer_info['area-before'].$googleInfo[$placeID]['area'].$customer_info['area-after'].$googleInfo[$placeID]['phone'];	

					$name = strtolower($res['result']['name']);					
					$name = str_replace( array('llc', 'hvac', 'a/c', 'inc', 'mcm', 'a-ale', 'hph', 'lecornu', 'ss&l'), array('LLC', 'HVAC', 'A/C', 'INC', 'MCM', 'A-Ale', 'HPH', 'LeCornu', 'SS&L'), $name);
					$googleInfo[$placeID]['name'] = ucwords($name);
					
					$streetNumber = $street = $subpremise = $city = $state_abbr = $state_full = $county = $country = $zip = '';
					$googleInfo[$placeID]['address_components'] = $res['result']['address_components'];					
					$googleInfo[$placeID]['adr_address'] = $res['result']['adr_address'];
					foreach ( $googleInfo[$placeID]['address_components'] as $comp ) :
						//if ( $comp['types'][0] == "street_number" ) $streetNumber = $comp['long_name'];
						//if ( $comp['types'][0] == "subpremise" ) $subpremise = $comp['short_name'];
						//if ( $comp['types'][0] == "route" ) $street = $comp['short_name'];						
						if ( $comp['types'][0] == "locality" ) $city = $comp['long_name'];
						if ( $comp['types'][0] == "administrative_area_level_1" ) :
						 	$state_abbr = $comp['short_name'];
							$state_full = $comp['long_name'];
						endif;
						if ( $comp['types'][0] == "administrative_area_level_2" ) $county = $comp['long_name'];
						if ( $comp['types'][0] == "country" ) $country = $comp['long_name'];
						if ( $comp['types'][0] == "postal_code" ) $zip = $comp['short_name'];
					endforeach;		
					
					//$googleInfo[$placeID]['street'] = $streetNumber.' '.$street.' '.$subpremise;					
					$googleInfo[$placeID]['street'] = strtok( $googleInfo[$placeID]['adr_address'], ',' );					
					$googleInfo[$placeID]['street'] = str_replace('<span class="street-address">', '', $googleInfo[$placeID]['street']);
					$googleInfo[$placeID]['street'] = str_replace('</span>', '', $googleInfo[$placeID]['street']);					
					if ( strlen($googleInfo[$placeID]['street']) < 5 ) $googleInfo[$placeID]['street'] = $customer_info['street'];			
					$googleInfo[$placeID]['city'] = $city;
					$googleInfo[$placeID]['state-abbr'] = $state_abbr;
					$googleInfo[$placeID]['state-full'] = $state_full;
					$googleInfo[$placeID]['county'] = $county;					
					$googleInfo[$placeID]['country'] = $country;					
					$googleInfo[$placeID]['zip'] = $zip;					
					$googleInfo[$placeID]['lat'] = $res['result']['geometry']['location']['lat'];
					$googleInfo[$placeID]['long'] = $res['result']['geometry']['location']['lng'];	
					$googleInfo[$placeID]['hours'] = $res['result']['opening_hours'];
					$googleInfo[$placeID]['current-hours'] = $res['result']['current_opening_hours'];
	
	
				// Add any new reviews to testimonials 	
				/*
					if (isset($res['result']['reviews']) && is_array($res['result']['reviews'])) :
						$reviews = $res['result']['reviews'];
						$googleInfo[$placeID]['reviews'] = array();

						foreach ($reviews as $review) :
							$reviewData = array(
								'author_name' => $review['author_name'],
								'rating' => $review['rating'],
								'text' => $review['text'],
								'time' => $review['time'],
								'author_url' => $review['author_url']
							);
							if ( $review['rating'] > 3 ) $googleInfo[$placeID]['reviews'][] = $reviewData;
						endforeach;
					endif;
	
					$args = array( 'post_type' => 'testimonials', 'posts_per_page' => -1, 'fields' => 'ids', );
					$existing_posts = new WP_Query($args);
					$existing_titles = [];

					if ($existing_posts->have_posts()) : while ($existing_posts->have_posts()) :
							$existing_posts->the_post();
							$existing_titles[] = strtolower(get_the_title());
						endwhile;
					endif;

					foreach ($googleInfo[$placeID]['reviews'] as $review) :
						if (!in_array( strtolower($review['author_name']), $existing_titles)) :
							$new_post = array(
								'post_title'   => wp_strip_all_tags($review['author_name']),
								'post_content' => $review['text'],
								'post_status'  => 'publish',
								'post_type'    => 'testimonials',
							);

							$new_post_id = wp_insert_post($new_post);
							if (!is_wp_error($new_post_id)) :
								update_field('testimonial_rating', $review['rating'], $new_post_id);
								update_field('platform', 'Google', $new_post_id);
								update_field('testimonial_website', $review['author_url'], $new_post_id);
							endif;
						endif;
					endforeach;	
				*/
				endif;
			endforeach;

			$googleInfo['google-reviews'] = $gNumber;	
			if ( $gNumber > 0 ) $googleInfo['google-rating'] = $gRating / $gNumber;	
			$googleInfo['date'] = $today;				
			updateOption('bp_gbp_update', $googleInfo);	
		endif;
		
		if ( $updateInfo == true && $pidSync == true ) :
			$primePID = $placeIDs[0];		
			
			$changed = $customer_info['area'] != $googleInfo[$primePID]['area'] ? 'Area Code<br>' : '';
			$changed .= $customer_info['phone'] != $googleInfo[$primePID]['phone'] ? 'Phone<br>' : '';
			$changed .= $customer_info['name'] != $googleInfo[$primePID]['name'] ? 'Name<br>' : '';
			$changed .= $customer_info['street'] != $googleInfo[$primePID]['street'] ? 'Street<br>' : '';
			$changed .= $customer_info['city'] != $googleInfo[$primePID]['city'] ? 'Phone<br>' : '';
			$changed .= $customer_info['state-abbr'] != $googleInfo[$primePID]['state-abbr'] ? 'State Abbr<br>' : '';
			$changed .= $customer_info['state-full'] != $googleInfo[$primePID]['state-full'] ? 'State Full<br>' : '';
			$changed .= $customer_info['zip'] != $googleInfo[$primePID]['zip'] ? 'Zip<br>' : '';
			
			$customer_info['area'] = $googleInfo[$primePID]['area'] != null ? $googleInfo[$primePID]['area'] : $customer_info['area'];		
			$customer_info['phone'] = $googleInfo[$primePID]['phone'] != null ? $googleInfo[$primePID]['phone'] : $customer_info['phone'];		
			$customer_info['phone-format'] = $customer_info['area-before'].$customer_info['area'].$customer_info['area-after'].$customer_info['phone'];	
			$customer_info['name'] = $googleInfo[$primePID]['name'] != null ? $googleInfo[$primePID]['name'] : $customer_info['name'];		
			$customer_info['street'] = strlen($googleInfo[$primePID]['street']) > 5 ? $googleInfo[$primePID]['street'] : $customer_info['street'];		
			$customer_info['city'] = $googleInfo[$primePID]['city'] != null ? $googleInfo[$primePID]['city'] : $customer_info['city'];		
			$customer_info['state-abbr'] = $googleInfo[$primePID]['state-abbr'] != null ? $googleInfo[$primePID]['state-abbr'] : $customer_info['state-abbr'];		
			$customer_info['state-full'] = $googleInfo[$primePID]['state-full'] != null ? $googleInfo[$primePID]['state-full'] : $customer_info['state-full'];		
			$customer_info['zip'] = $googleInfo[$primePID]['zip'] != null ? $googleInfo[$primePID]['zip'] : $customer_info['zip'];		
			$customer_info['lat'] = $googleInfo[$primePID]['lat'] != null ? $googleInfo[$primePID]['lat'] : $customer_info['lat'];		
			$customer_info['long'] = $googleInfo[$primePID]['long'] != null ? $googleInfo[$primePID]['long'] : $customer_info['long'];		
			$customer_info['hours'] = $googleInfo[$primePID]['hours'] != null ? $googleInfo[$primePID]['hours'] : "na";		
			$customer_info['hours-sun'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][6], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][6], ": ") + 2);
			$customer_info['hours-mon'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][0], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][0], ": ") + 2);
			$customer_info['hours-tue'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][1], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][1], ": ") + 2);
			$customer_info['hours-wed'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][2], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][2], ": ") + 2);
			$customer_info['hours-thu'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][3], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][3], ": ") + 2);
			$customer_info['hours-fri'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][4], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][4], ": ") + 2);
			$customer_info['hours-sat'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][5], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][5], ": ") + 2);

			updateOption( 'customer_info', $customer_info );
			$GLOBALS['customer_info'] = get_option('customer_info');			
		endif;
	endif;

/*--------------------------------------------------------------
# Universal Pages
--------------------------------------------------------------*/

/* Add appropriate pages */
	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'american standard' || $GLOBALS['customer_info']['site-brand'] == 'American Standard' || (is_array($GLOBALS['customer_info']['site-brand']) && ( in_array('american standard', $GLOBALS['customer_info']['site-brand']) || in_array('American Standard', $GLOBALS['customer_info']['site-brand'])) ))) :		
		if (is_null(get_page_by_path('customer-care-dealer', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Customer Care Dealer', 'post_content' => '[get-universal-page slug="page-hvac-customer-care-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('customer-care-dealer', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'rheem' || $GLOBALS['customer_info']['site-brand'] == 'Rheem' || (is_array($GLOBALS['customer_info']['site-brand']) && ( in_array('rheem', $GLOBALS['customer_info']['site-brand']) || in_array('Rheem', $GLOBALS['customer_info']['site-brand'])) ))) :
		if (is_null(get_page_by_path('rheem-pro-partner', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Rheem Pro Partner', 'post_content' => '[get-universal-page slug="page-hvac-rheem-pro-partner"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('rheem-pro-partner', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'ruud' || $GLOBALS['customer_info']['site-brand'] == 'Ruud' || (is_array($GLOBALS['customer_info']['site-brand']) && ( in_array('ruud', $GLOBALS['customer_info']['site-brand']) || in_array('Ruud', $GLOBALS['customer_info']['site-brand'])) ))) :
		if (is_null(get_page_by_path('ruud-pro-partner', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Ruud Pro Partner', 'post_content' => '[get-universal-page slug="page-hvac-ruud-pro-partner"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('ruud-pro-partner', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'comfortmaker' || $GLOBALS['customer_info']['site-brand'] == 'Comfortmaker' || (is_array($GLOBALS['customer_info']['site-brand']) && ( in_array('comfortmaker', $GLOBALS['customer_info']['site-brand']) || in_array('Comfortmaker', $GLOBALS['customer_info']['site-brand'])) ))) :
		if (is_null(get_page_by_path('comfortmaker-elite-dealer', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Comfortmaker Elite Dealer', 'post_content' => '[get-universal-page slug="page-hvac-comfortmaker-elite-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('comfortmaker-elite-dealer', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'york' || $GLOBALS['customer_info']['site-brand'] == 'York' || (is_array($GLOBALS['customer_info']['site-brand']) && ( in_array('york', $GLOBALS['customer_info']['site-brand']) || in_array('York', $GLOBALS['customer_info']['site-brand'])) ))) :
		if (is_null(get_page_by_path('york-certified-comfort-expert', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'York Certified Comfort Expert', 'post_content' => '[get-universal-page slug="page-hvac-york-cert-comfort-expert"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('york-cert-comfort-expert', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'tempstar' || $GLOBALS['customer_info']['site-brand'] == 'Tempstar' || (is_array($GLOBALS['customer_info']['site-brand']) && ( in_array('tempstar', $GLOBALS['customer_info']['site-brand']) || in_array('Tempstar', $GLOBALS['customer_info']['site-brand'])) ))) :
		if (is_null(get_page_by_path('tempstar-elite-dealer', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Tempstar Elite Dealer', 'post_content' => '[get-universal-page slug="page-hvac-tempstar-elite-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('tempstar-elite-dealer', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' ) :
		if (is_null(get_page_by_path('maintenance-tips', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Maintenance Tips', 'post_content' => '[get-universal-page slug="page-hvac-maintenance-tips"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('maintenance-tips', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' ) :
		if (is_null(get_page_by_path('symptom-checker', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Symptom Checker', 'post_content' => '[get-universal-page slug="page-hvac-symptom-checker"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('symptom-checker', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'hvac' ) :
		if (is_null(get_page_by_path('faq', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'FAQ', 'post_content' => '[get-universal-page slug="page-hvac-faq"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('faq', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'profile' ) :
		if (is_null(get_page_by_path('profile', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Profile', 'post_content' => '[get-universal-page slug="page-profile"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('profile', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	if ( $GLOBALS['customer_info']['site-type'] == 'profile' ) :
		if (is_null(get_page_by_path('profile-directory', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Profile Directory', 'post_content' => '[get-universal-page slug="page-profile-directory"]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('profile-directory', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;
 
	if ( get_option('event_calendar')['install'] == 'true' ) :
		if (is_null(get_page_by_path('calendar', OBJECT, 'universal'))) : wp_insert_post( array( 'post_title' => 'Calendar', 'post_content' => '[get-event-calendar]', 'post_status' => 'publish', 'post_type' => 'universal', )); endif;
	else:
		$getPage = get_page_by_path('calendar', OBJECT, 'universal'); if ( $getPage ) wp_delete_post( $getPage->ID, true );
	endif;

	/* Add generic pages */
	if ( is_null(get_page_by_path('privacy-policy', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Privacy Policy', 'post_content' => '[get-universal-page slug="page-privacy-policy"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

	if ( is_null(get_page_by_path('accessibility-policy', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Accessibility Policy', 'post_content' => '[get-universal-page slug="page-accessibility-policy"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

	if ( is_null(get_page_by_path('terms-conditions', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Terms & Conditions', 'post_content' => '[get-universal-page slug="page-terms-conditions"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

	if ( is_null(get_page_by_path('review', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Review', 'post_content' => '[get-universal-page slug="page-review"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

	if ( is_null(get_page_by_path('email-received', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Email Received', 'post_content' => '[get-universal-page slug="page-email-received"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

/*--------------------------------------------------------------
# Sync with Google Analytics
--------------------------------------------------------------*/
	$GLOBALS['customer_info'] = get_option('customer_info') ? get_option('customer_info') : array();
	$GLOBALS['dataTerms'] = get_option('bp_data_terms') ? get_option('bp_data_terms') : array();
	$ga4_id = isset($GLOBALS['customer_info']['google-tags']['prop-id']) ? $GLOBALS['customer_info']['google-tags']['prop-id'] : null;
	$client = new BetaAnalyticsDataClient(['credentials'=>get_template_directory().'/vendor/atomic-box-306317-0b19b6a3a6c1.json']);
	$today = date("Y-m-d", strtotime("-1 day"));
	$rewind = date("Y-m-d", strtotime("-6 years"));
	
	$siteHitsGA4 = is_array(get_option('bp_site_hits_ga4')) ? get_option('bp_site_hits_ga4') : array();		

	$states = array('alabama'=>'AL', 'arizona'=>'AZ', 'arkansas'=>'AR', 'california'=>'CA', 'colorado'=>'CO', 'connecticut'=>'CT', 'delaware'=>'DE', 'dist of columbia'=>'DC', 'dist. of columbia'=>'DC', 'district of columbia'=>'DC', 'florida'=>'FL', 'georgia'=>'GA', 'idaho'=>'ID', 'illinois'=>'IL', 'indiana'=>'IN', 'iowa'=>'IA', 'kansas'=>'KS', 'kentucky'=>'KY', 'louisiana'=>'LA', 'maine'=>'ME', 'maryland'=>'MD', 'massachusetts'=>'MA', 'michigan'=>'MI', 'minnesota'=>'MN', 'mississippi'=>'MS', 'missouri'=>'MO', 'montana'=>'MT', 'nebraska'=>'NE', 'nevada'=>'NV', 'new hampshire'=>'NH', 'new jersey'=>'NJ', 'new mexico'=>'NM', 'new york'=>'NY', 'north carolina'=>'NC', 'north dakota'=>'ND', 'ohio'=>'OH', 'oklahoma'=>'OK', 'oregon'=>'OR', 'pennsylvania'=>'PA', 'rhode island'=>'RI', 'south carolina'=>'SC', 'south dakota'=>'SD', 'tennessee'=>'TN', 'texas'=>'TX', 'utah'=>'UT', 'vermont'=>'VT', 'virginia'=>'VA', 'washington'=>'WA', 'washington d.c.'=>'DC', 'washington dc'=>'DC', 'west virginia'=>'WV', 'wisconsin'=>'WI', 'wyoming'=>'WY');
	$removedStates = array('alaska'=>'AK', 'hawaii'=>'HI',);

// Gather GA4 Stats 
	if ( $ga4_id && substr($ga4_id, 0, 2) != '00' ) :
	
		// Weekly Visitor Trends
		$analyticsGA4 = array();
		$response = $client->runReport([
			'property' => 'properties/'.$ga4_id,
			'dateRanges' => [
				new DateRange([ 'start_date' => $rewind, 'end_date' => $today ]),
			],
			'dimensions' => [
				new Dimension([ 'name' => 'date' ]),
				new Dimension([ 'name' => 'city' ]),
				new Dimension([ 'name' => 'region' ]),	
			],
			'metrics' => [
				new Metric([ 'name' => 'totalUsers' ]),
				new Metric([ 'name' => 'newUsers' ]),
				new Metric([ 'name' => 'sessions' ]),
				new Metric([ 'name' => 'engagedSessions' ]),
				new Metric([ 'name' => 'userEngagementDuration' ]),
				new Metric([ 'name' => 'screenPageViews' ]),
			]
		]);
	
		foreach ( $response->getRows() as $row ) :
			$date = $row->getDimensionValues()[0]->getValue();
			$city = $row->getDimensionValues()[1]->getValue();
			$state = strtolower($row->getDimensionValues()[2]->getValue());
			if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];

			$totalUsers = $row->getMetricValues()[0]->getValue();	
			$newUsers = $row->getMetricValues()[1]->getValue();				
			$sessions = $row->getMetricValues()[2]->getValue();				
			$engagedSessions = $row->getMetricValues()[3]->getValue();				
			$sessionDuration = $row->getMetricValues()[4]->getValue();	
			$pageViews = $row->getMetricValues()[5]->getValue();	
	
			if ( isset($states[$state]) ) :					
				if ( $city == '(not set)' ) $location = ucwords($state);					

				$analyticsGA4[] = array ('date'=>$date, 'location'=>$location, 'total-users'=>$totalUsers, 'new-users'=>$newUsers, 'sessions'=>$sessions, 'engaged-sessions'=>$engagedSessions, 'session-duration'=>$sessionDuration, 'page-views'=>$pageViews );	
			endif;
		endforeach;		
	
		if ( is_array($analyticsGA4) ) arsort($analyticsGA4);	
		update_option('bp_ga4_trends_01', $analyticsGA4, false);		

		
		// Site Visitors	
		$analyticsGA4 = array();
		$dataTerms = array('day' => 1) + $GLOBALS['dataTerms'];
		foreach ( $dataTerms as $termTitle=>$termDays ) :		
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => date("Y-m-d", strtotime("-".$termDays." days")), 'end_date' => $today ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					new Metric([ 'name' => 'totalUsers' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$city = $row->getDimensionValues()[0]->getValue();
				$state = strtolower($row->getDimensionValues()[1]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$totalUsers = $row->getMetricValues()[0]->getValue();	

				if ( isset($states[$state]) ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);	
					$analyticsGA4[$location]['page-views-'.$termDays] = $totalUsers;	
				endif;
			endforeach;		 

			if ( is_array($analyticsGA4) ) arsort($analyticsGA4);

			update_option('bp_ga4_visitors_01', $analyticsGA4, false);	
	
		endforeach;	
	
	
		// Most Popular Pages	
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :		
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => date("Y-m-d", strtotime("-".$termDays." days")), 'end_date' => $today ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'pagePath' ]),				
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					new Metric([ 'name' => 'screenPageViews' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$pagePath = $row->getDimensionValues()[0]->getValue();
				$city = $row->getDimensionValues()[1]->getValue();
				$state = strtolower($row->getDimensionValues()[2]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$pageViews = $row->getMetricValues()[0]->getValue();	

				if ( $pagePath == "/" ) $pagePath = "Home";
				$pagePath = str_replace('-', ' ', trim($pagePath, '/'));				
				$pagePath = str_replace('/', ' » ', $pagePath);				
				$pagePath = ucwords($pagePath);

				if ( isset($states[$state]) ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);	
					$analyticsGA4[$pagePath][$location]['page-views-'.$termDays] = $pageViews;	
				endif;
			endforeach;		 

			if ( is_array($analyticsGA4) ) arsort($analyticsGA4);

			update_option('bp_ga4_pages_01', $analyticsGA4, false);	
		endforeach;	
	
			
		// Referrers
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :	
	
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => date("Y-m-d", strtotime("-".$termDays." days")), 'end_date' => $today ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'firstUserSourceMedium' ]),				
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					//new Metric([ 'name' => 'sessions' ]),					
					new Metric([ 'name' => 'engagedSessions' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$pageReferrer = $row->getDimensionValues()[0]->getValue();
				$city = $row->getDimensionValues()[1]->getValue();
				$state = strtolower($row->getDimensionValues()[2]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$sessions = $row->getMetricValues()[0]->getValue();	
	
				$switchRef = array ('facebook'=>'Facebook', 'yelp'=>'Yelp', 'youtube'=>'YouTube', 'instagram'=>'Instagram');
	
				if (strpos($pageReferrer, $_SERVER['HTTP_HOST']) === false) :
					foreach ( $switchRef as $find=>$replace ) :
						if ( strpos( $pageReferrer, $find ) !== false ) $pageReferrer = $replace;
					endforeach;		
	
					if ( $pageReferrer == '' ) $pageReferrer = "Direct";
	
					if ( isset($states[$state]) ) :					
						if ( $city == '(not set)' ) $location = ucwords($state);	
						$sessions += $analyticsGA4[$pageReferrer][$location]['sessions-'.$termDays];
						$analyticsGA4[$pageReferrer][$location]['sessions-'.$termDays] = $sessions;	
					endif;
				endif;
			endforeach;		 

			if ( is_array($analyticsGA4) ) arsort($analyticsGA4);
	
			update_option('bp_ga4_referrers_01', $analyticsGA4, false);		
		endforeach;		

	
		// Locations
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :	
	
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => date("Y-m-d", strtotime("-".$termDays." days")), 'end_date' => $today ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					//new Metric([ 'name' => 'sessions' ]),					
					new Metric([ 'name' => 'engagedSessions' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$city = $row->getDimensionValues()[0]->getValue();
				$state = strtolower($row->getDimensionValues()[1]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$sessions = $row->getMetricValues()[0]->getValue();	
	
				if ( isset($states[$state]) ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);	
					$analyticsGA4[$location]['sessions-'.$termDays] = $sessions;	
				endif;

			endforeach;		 

			if ( is_array($analyticsGA4) ) arsort($analyticsGA4);
	
			update_option('bp_ga4_locations_01', $analyticsGA4, false);	
		endforeach;		
	
			
		// Browsers
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :	
	
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => date("Y-m-d", strtotime("-".$termDays." days")), 'end_date' => $today ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'browser' ]),				
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					//new Metric([ 'name' => 'sessions' ]),					
					new Metric([ 'name' => 'engagedSessions' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$browser = $row->getDimensionValues()[0]->getValue();
				$city = $row->getDimensionValues()[1]->getValue();
				$state = strtolower($row->getDimensionValues()[2]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$sessions = $row->getMetricValues()[0]->getValue();	

				if ( isset($states[$state]) ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);	
					$sessions += $analyticsGA4[$browser][$location]['sessions-'.$termDays];
					$analyticsGA4[$browser][$location]['sessions-'.$termDays] = $sessions;	
				endif;
			endforeach;		 

			if ( is_array($analyticsGA4) ) arsort($analyticsGA4);
	
			update_option('bp_ga4_browsers_01', $analyticsGA4, false);		
		endforeach;		
	
			
		// Devices
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :	
	
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => date("Y-m-d", strtotime("-".$termDays." days")), 'end_date' => $today ]),
				],
				'dimensions' => [
					//new Dimension([ 'name' => 'mobileDeviceBranding' ]),				
					new Dimension([ 'name' => 'deviceCategory' ]),				
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					//new Metric([ 'name' => 'sessions' ]),					
					new Metric([ 'name' => 'engagedSessions' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$deviceType = $row->getDimensionValues()[0]->getValue();
				$city = $row->getDimensionValues()[1]->getValue();
				$state = strtolower($row->getDimensionValues()[2]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$sessions = $row->getMetricValues()[0]->getValue();	

				if ( isset($states[$state]) ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);	
					$sessions += $analyticsGA4[$deviceType][$location]['sessions-'.$termDays];
					$analyticsGA4[$deviceType][$location]['sessions-'.$termDays] = $sessions;	
				endif;
			endforeach;		 

			if ( is_array($analyticsGA4) ) arsort($analyticsGA4);
	
			update_option('bp_ga4_devices_01', $analyticsGA4, false);		
		endforeach;				
	
			
		// Site Load Speed
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :	
	
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => date("Y-m-d", strtotime("-".$termDays." days")), 'end_date' => $today ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'groupId' ]),				
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					//new Metric([ 'name' => 'sessions' ]),					
					//new Metric([ 'name' => 'engagedSessions' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$groupId = $row->getDimensionValues()[0]->getValue();
				$city = $row->getDimensionValues()[1]->getValue();
				$state = strtolower($row->getDimensionValues()[2]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				//$sessions = $row->getMetricValues()[0]->getValue();	

				if ( isset($states[$state]) ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);	
					$analyticsGA4[$location]['sessions-'.$termDays][] = $groupId;
				endif;
			endforeach;		 

			if ( is_array($analyticsGA4) ) arsort($analyticsGA4);
	
			update_option('bp_ga4_speed_01', $analyticsGA4, false);		
		endforeach;			
	
			
		// Screen Resolutions
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :	
	
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => date("Y-m-d", strtotime("-".$termDays." days")), 'end_date' => $today ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'screenResolution' ]),				
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					//new Metric([ 'name' => 'sessions' ]),					
					new Metric([ 'name' => 'engagedSessions' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$screenResolution = $row->getDimensionValues()[0]->getValue();
				$city = $row->getDimensionValues()[1]->getValue();
				$state = strtolower($row->getDimensionValues()[2]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$sessions = $row->getMetricValues()[0]->getValue();	

				if ( isset($states[$state]) ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);	
					$sessions += $analyticsGA4[$screenResolution][$location]['sessions-'.$termDays];
					$analyticsGA4[$screenResolution][$location]['sessions-'.$termDays] = $sessions;	
				endif;
			endforeach;		 

			if ( is_array($analyticsGA4) ) arsort($analyticsGA4);
	
			update_option('bp_ga4_resolution_01', $analyticsGA4, false);		
		endforeach;			
	
			
		// Content Visibility
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :	
	
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => date("Y-m-d", strtotime("-".$termDays." days")), 'end_date' => $today ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'achievementId' ]),				
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					new Metric([ 'name' => 'sessions' ]),					
					//new Metric([ 'name' => 'engagedSessions' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$achievementId = $row->getDimensionValues()[0]->getValue();
				$city = $row->getDimensionValues()[1]->getValue();
				$state = strtolower($row->getDimensionValues()[2]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$sessions = $row->getMetricValues()[0]->getValue();	

				if ( isset($states[$state]) ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);	
					$sessions += $analyticsGA4[$achievementId][$location]['sessions-'.$termDays];
					$analyticsGA4[$achievementId][$location]['sessions-'.$termDays] = $sessions;	
				endif;
			endforeach;		 

			if ( is_array($analyticsGA4) ) arsort($analyticsGA4);
	
			update_option('bp_ga4_achievementId_01', $analyticsGA4, false);		
		endforeach;		
	endif;
}