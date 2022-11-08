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

if ( get_option('bp_setup_2022_11_08') != "completed" ) :
	delete_option('bp_setup_2022_10_05');
	
	$bp_ua_1 = get_option('bp_site_hits_ua_1') != null ? get_option('bp_site_hits_ua_1') : null;
	$bp_ua_2 = get_option('bp_site_hits_ua_2') != null ? get_option('bp_site_hits_ua_2') : null;
	$bp_ua_3 = get_option('bp_site_hits_ua_3') != null ? get_option('bp_site_hits_ua_3') : null;
	$bp_ua_4 = get_option('bp_site_hits_ua_4') != null ? get_option('bp_site_hits_ua_4') : null;
	$bp_ua_5 = get_option('bp_site_hits_ua_5') != null ? get_option('bp_site_hits_ua_5') : null;
	
	if ( $bp_ua_1 != null ) updateOption('bp_site_hits_ua_1_backup', $bp_ua_1, false);
	if ( $bp_ua_2 != null ) updateOption('bp_site_hits_ua_2_backup', $bp_ua_2, false);
	if ( $bp_ua_3 != null ) updateOption('bp_site_hits_ua_3_backup', $bp_ua_3, false);
	if ( $bp_ua_4 != null ) updateOption('bp_site_hits_ua_4_backup', $bp_ua_4, false);
	if ( $bp_ua_5 != null ) updateOption('bp_site_hits_ua_5_backup', $bp_ua_5, false);
	
	delete_option('bp_google_reviews');
	
	//battleplan_delete_prefixed_options( 'wdp_' );

	if ( $customerInfo['site-type'] != 'profile' ) delete_option('site_login');

	updateOption( 'bp_setup_2022_11_08', 'completed', false );			
endif;

// Determine if Chron should run
require_once get_template_directory().'/vendor/autoload.php';
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;

$forceChron = get_option('bp_force_chron') !== null ? get_option('bp_force_chron') : false;
$chronTime = get_option('bp_chron_time') !== null ? get_option('bp_chron_time') : 0;
$chronDue = $chronTime + rand(40000,70000);

if ( $forceChron == true || ( _IS_BOT && !_IS_GOOGLEBOT && ( $chronDue < time() ) )) :
	delete_option('bp_force_chron');
	update_option('bp_chron_time', time());
	processChron();
endif;
	
function processChron() {
	if (function_exists('battleplan_remove_user_roles')) battleplan_remove_user_roles();
	if (function_exists('battleplan_create_user_roles')) battleplan_create_user_roles();
		
// WP Mail SMTP Settings Update
	if ( is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') ) : 
		$apiKey1 = "keysib";
		$apiKey2 = "ef3a9074e001fa21f640578f699994cba854489d3ef793";
		$wpMailSettings = get_option( 'wp_mail_smtp' );			
		$wpMailSettings['mail']['from_email'] = 'email@admin.'.str_replace('https://', '', get_bloginfo('url'));
		$wpMailSettings['mail']['from_name'] = 'Website Administrator · '.$GLOBALS['customer_info']['name'];
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

			$formMail['subject'] = $formTitle." · [user-name]";
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
			if ( $postType == "post" || $postType == "page" || $postType == "optimized" || $postType == "universal" || $postType == "products" || $postType == "tribe_events" ) :
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

// Blackhole for Bad Bots
	if ( is_plugin_active('blackhole-bad-bots/blackhole.php') ) : 	
		$blackholeSettings = get_option( 'bbb_options' );			
		$blackholeSettings['email_alerts'] = 0;
		$blackholeSettings['email_address'] = get_option( 'admin_email' );
		$blackholeSettings['email_from'] = get_option( 'wp_mail_smtp' )['mail']['from_email'];
		$blackholeSettings['message_display'] = 'custom';
		$blackholeSettings['message_custom'] = '<h1>Service Unavailable</h1>';
		$blackholeSettings['bot_whitelist'] = '';
		$blackholeSettings['ip_whitelist'] = '73.28.89.12';
		update_option( 'bbb_options', $blackholeSettings );		
	endif;
	
// The Events Calendar
	if ( is_plugin_active('the-events-calendar/the-events-calendar.php') ) : 	
		global $post; 
		$getPosts = new WP_Query( array ('posts_per_page'=>-1, 'post_type'=>'tribe_events') );
		if ( $getPosts->have_posts() ) : while ( $getPosts->have_posts() ) : $getPosts->the_post(); 	
			$end = strtotime(get_post_meta( get_the_id(), '_EventEndDate', true ));		
			if ( $end < time() ) wp_set_post_tags( get_the_id(), array( 'expired' ) );		
		endwhile; wp_reset_postdata(); endif;	
	endif;
	
// Basic Settings		
	$update_menu_order = array ('site-header'=>100, 'widgets'=>200, 'office-hours'=>700, 'hours'=>700, 'coupon'=>700, 'site-message'=>800, 'site-footer'=>900);

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
	update_option( 'auto_update_core_dev', 'enabled' );
	update_option( 'auto_update_core_minor', 'enabled' );
	update_option( 'auto_update_core_major', 'enabled' );			

	battleplan_delete_prefixed_options( 'ac_cache_data_' );
	battleplan_delete_prefixed_options( 'ac_cache_expires_' );
	battleplan_delete_prefixed_options( 'ac_api_request_' );	
	battleplan_delete_prefixed_options( 'ac_sorting_' );
	battleplan_delete_prefixed_options( 'client_' );
	
/*--------------------------------------------------------------
# Update 'customer_info' with Google Business Profile info
--------------------------------------------------------------*/	
	if ( function_exists('battleplan_updateSiteOptions') ) battleplan_updateSiteOptions();	
		 
	$updateInfo = false;
	$customer_info = get_option('customer_info');
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
					$googleInfo[$placeID]['phone-format'] = $GLOBALS['customer_info']['area-before'].$googleInfo[$placeID]['area'].$GLOBALS['customer_info']['area-after'].$googleInfo[$placeID]['phone'];					
					$googleInfo[$placeID]['name'] = ucwords(strtolower($res['result']['name']));	
					
					$streetNumber = $street = $subpremise = $city = $state_abbr = $state_full = $county = $country = $zip = '';
					$googleInfo[$placeID]['address_components'] = $res['result']['address_components'];					
					$googleInfo[$placeID]['adr_address'] = $res['result']['adr_address'];
					foreach ( $googleInfo[$placeID]['address_components'] as $comp ) :
						if ( $comp['types'][0] == "street_number" ) $streetNumber = $comp['long_name'];
						if ( $comp['types'][0] == "subpremise" ) $subpremise = $comp['short_name'];
						if ( $comp['types'][0] == "route" ) $street = $comp['short_name'];						
						if ( $comp['types'][0] == "locality" ) $city = $comp['long_name'];
						if ( $comp['types'][0] == "administrative_area_level_1" ) :
						 	$state_abbr = $comp['short_name'];
							$state_full = $comp['long_name'];
						endif;
						if ( $comp['types'][0] == "administrative_area_level_2" ) $county = $comp['long_name'];
						if ( $comp['types'][0] == "country" ) $country = $comp['long_name'];
						if ( $comp['types'][0] == "postal_code" ) $zip = $comp['short_name'];
					endforeach;		
					
					$googleInfo[$placeID]['street'] = $streetNumber.' '.$street.' '.$subpremise;					
					if ( $googleInfo[$placeID]['street'] == '  ' ) $googleInfo[$placeID]['street'] = strtok( $googleInfo[$placeID]['adr_address'], ',' );					
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
				endif;
			endforeach;

			$googleInfo['google-reviews'] = $gNumber;	
			if ( $gNumber > 0 ) $googleInfo['google-rating'] = $gRating / $gNumber;	
			$googleInfo['date'] = $today;				
			updateOption('bp_gbp_update', $googleInfo);	
		endif;
		
		if ( $updateInfo == true ) :
			$primePID = $placeIDs[0];		
			$customer_info['area'] = $googleInfo[$primePID]['area'];		
			$customer_info['phone'] = $googleInfo[$primePID]['phone'];
			$customer_info['phone-format'] = $googleInfo[$primePID]['phone-format'];
			$customer_info['name'] = $googleInfo[$primePID]['name'];
			$customer_info['street'] = $googleInfo[$primePID]['street'];
			$customer_info['city'] = $googleInfo[$primePID]['city'];
			$customer_info['state-abbr'] = $googleInfo[$primePID]['state-abbr'];
			$customer_info['state-full'] = $googleInfo[$primePID]['state-full'];
			$customer_info['zip'] = $googleInfo[$primePID]['zip'];
			$customer_info['lat'] = $googleInfo[$primePID]['lat'];
			$customer_info['long'] = $googleInfo[$primePID]['long'];
			$customer_info['hours-sun'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][6], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][6], ": ") + 2);
			$customer_info['hours-mon'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][0], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][0], ": ") + 2);
			$customer_info['hours-tue'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][1], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][1], ": ") + 2);
			$customer_info['hours-wed'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][2], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][2], ": ") + 2);
			$customer_info['hours-thu'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][3], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][3], ": ") + 2);
			$customer_info['hours-fri'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][4], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][4], ": ") + 2);
			$customer_info['hours-sat'] = substr($googleInfo[$primePID]['current-hours']['weekday_text'][5], strpos($googleInfo[$primePID]['current-hours']['weekday_text'][5], ": ") + 2);

			updateOption( 'customer_info', $customer_info );
			$GLOBALS['customer_info'] = $customer_info;
		endif;
	endif;

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
	$client = new BetaAnalyticsDataClient(['credentials'=>get_template_directory().'/vendor/atomic-box-306317-0b19b6a3a6c1.json']);
	$today = date( "Y-m-d" );
	$rewind = date("Y-m-d", strtotime("-12 month"));

	$siteHitsGA4 = is_array(get_option('bp_site_hits_ga4')) ? get_option('bp_site_hits_ga4') : array();		

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

				$analyticsGA4[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'pages-viewed'=>$pagesViewed, 'resolution'=>$resolution, 'referrer'=>$referrer, 'engaged'=>$engaged, 'new-users'=>$newUsers );	
			endif;
		endforeach;	
		
		if ( is_array($analyticsGA4) ) arsort($analyticsGA4);
		
		// Split session data into site hits
		foreach ( $analyticsGA4 as $analyze ) :
			$date = $analyze['date'];
			$location = $analyze['location'];
			$page = $analyze['page'];
			$browser = $analyze['browser'];
			$device = $analyze['device'];
			$resolution = $analyze['resolution'];
			$pageviews = $analyze['pages-viewed'];
			$engaged = $analyze['engaged'];
			$newUsers = $analyze['new-users'];
			$referrer = $analyze['referrer'];
			list($source, $medium) = explode(" / ", $analyze['source']);
			
			if ( strpos( $referrer, parse_url( get_site_url(), PHP_URL_HOST ) ) === false ) :	
				$siteHitsGA4[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'medium'=>$medium, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'resolution'=>$resolution, 'pages-viewed'=>$pageviews, 'sessions'=>'1', 'engaged'=>$engaged, 'new-users'=>$newUsers );	
			else :
				$siteHitsGA4[] = array ('date'=>$date, 'page'=>$page, 'pages-viewed'=>$pageviews, 'sessions'=>'0', 'engaged'=>$engaged );				
			endif;
		endforeach;		
		
		$bpSiteHitsGA4 = array_intersect_key( $siteHitsGA4, array_unique( array_map('serialize', $siteHitsGA4 )));
		update_option('bp_site_hits_ga4', $bpSiteHitsGA4, false);		
	endif;

// Gather UA Stats	
	$siteHits = is_array($siteHitsGA4) ? $siteHitsGA4 : array();
	$siteHitsUA1 = is_array(get_option('bp_site_hits_ua_1')) ? get_option('bp_site_hits_ua_1') : array();
	$siteHitsUA2 = is_array(get_option('bp_site_hits_ua_2')) ? get_option('bp_site_hits_ua_2') : array();
	$siteHitsUA3 = is_array(get_option('bp_site_hits_ua_3')) ? get_option('bp_site_hits_ua_3') : array();
	$siteHitsUA4 = is_array(get_option('bp_site_hits_ua_4')) ? get_option('bp_site_hits_ua_4') : array();
	$siteHitsUA5 = is_array(get_option('bp_site_hits_ua_5')) ? get_option('bp_site_hits_ua_5') : array();
	$siteHits = array_merge( $siteHits, $siteHitsUA1, $siteHitsUA2, $siteHitsUA3, $siteHitsUA4, $siteHitsUA5);

// Compile hits on specific pages
	$pageCounts = array(1, 7, 30, 90, 365);
	$today = strtotime($today);		
	$citiesToExclude = array('Orangetree, FL', 'Ashburn, VA', 'Boardman, OR'); // also change in functions-admin.php
	$compilePaths = $statOverview = array();
	$lastKnownVisit = get_option('last_visitor_time');

	foreach ( $siteHits as $siteHit ) :		
		$page = rtrim($siteHit['page'], "/");
		if ( isset($siteHit['location']) && !in_array( $siteHit['location'], $citiesToExclude) && strpos($page, 'fbclid') === false && strpos($page, 'reportkey') === false ) :			
			if ( $page == "" || $page == "/" ) $page = "Home";
			if ( array_key_exists($page, $compilePaths ) ) :
				$compilePaths[$page] += (int)$siteHit['pages-viewed'];
			else:
				$compilePaths[$page] = (int)$siteHit['pages-viewed'];
			endif; 
		endif;

		$checkDate = strtotime($siteHit['date']);
		$howLong = $today - $checkDate;
		if ( $lastKnownVisit < $checkDate ) $lastKnownVisit = $checkDate;
		
		foreach ( $pageCounts as $count ) :
			if ( $howLong > (($count + 1) * 86400) ) :				
				foreach ($compilePaths as $page=>$pageViews) :
					if ( $page == "Home" ) :
						$id = get_option('page_on_front');					
					else:
						$id = getID($page);
					endif;		
					
					$pageKey = $count==1 ? 'log-views-today' : 'log-views-total-'.$count.'day';
					updateMeta($id, $pageKey, $pageViews);
				endforeach;
			
				array_shift($pageCounts);			
			endif;	
		endforeach;	
	endforeach;	
	
	updateOption('last_visitor_time', $lastKnownVisit, false); 

	if ( $ga4_id ) :
		// Gather Content Tracking & Speed Tracking stats
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
		endforeach;
		updateOption('bp_tracking_content', $allTracking, false);		
		
		// Tally sessions for use on bp stats page
		$statOverview = array();		
		$pageCounts = array(7, 30, 90, 365);		
		foreach ( $pageCounts as $count ) :
			$totalViews = 0;	
			$statKey = $count==365 ? 'lastYearViews' : 'last'.$count.'Views';
			$today = date( "Y-m-d" );	
			$rewind = date('Y-m-d', strtotime($today.' - '.$count.' days'));

			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => $rewind, 'end_date' => $today ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),	
				],
				'metrics' => [
					new Metric([ 'name' => 'engagedSessions' ]),
				]
			]);

			foreach ( $response->getRows() as $row ) :
				$city = $row->getDimensionValues()[0]->getValue();
				$state = strtolower($row->getDimensionValues()[1]->getValue());
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$sessions = $row->getMetricValues()[0]->getValue();							

				if ( isset($states[$state]) && !in_array( $location, $citiesToExclude) ) :	
					$totalViews = $totalViews + $sessions;
				endif;
			endforeach;			

			$statOverview[$statKey] = $totalViews;
		endforeach;
		
		updateOption('stat-overview', $statOverview, false );	
	endif;
}
?>