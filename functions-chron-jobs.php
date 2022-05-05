<?php 
/* Battle Plan Web Design Functions: Chron Jobs

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Chron Jobs
# Universal Pages

/*--------------------------------------------------------------
# Chron Jobs
--------------------------------------------------------------*/
if (function_exists('battleplan_remove_user_roles')) battleplan_remove_user_roles();
if (function_exists('battleplan_create_user_roles')) battleplan_create_user_roles();
if (function_exists('battleplan_updateSiteOptions')) battleplan_updateSiteOptions();

// delete all options that begin with {$prefix}
function battleplan_delete_prefixed_options( $prefix ) {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'" );
}	

// WP Mail SMTP Settings Update
if ( is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') ) : 	
	$wpMailSettings = get_option( 'wp_mail_smtp' );		
	$wpMailSettings['mail']['from_name'] = "Website Administrator";
	$wpMailSettings['mail']['from_name_force'] = '0';		
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

// ARI FancyBox Settings Update
if ( is_plugin_active('ari-fancy-lightbox/ari-fancy-lightbox.php') ) : 
	$wpARISettings = get_option( 'ari_fancy_lightbox_settings' );
	$wpARISettings['convert']['wp_gallery']['convert'] = '1';
	$wpARISettings['convert']['wp_gallery']['grouping'] = '1';
	$wpARISettings['convert']['images']['convert'] = '1';
	$wpARISettings['convert']['images']['post_grouping'] = '1';
	$wpARISettings['convert']['images']['grouping_selector'] = '.gallery$$A';		
	$wpARISettings['convert']['images']['filenameToTitle'] = '1';		
	$wpARISettings['convert']['images']['convertNameSmart'] = '1';		
	$wpARISettings['lightbox']['loop'] = '1';		
	$wpARISettings['lightbox']['arrows'] = '1';		
	$wpARISettings['lightbox']['infobar'] = '1';		
	$wpARISettings['lightbox']['keyboard'] = '1';		
	$wpARISettings['lightbox']['autoFocus'] = '1';		
	$wpARISettings['lightbox']['trapFocus'] = '0';		
	$wpARISettings['lightbox']['closeClickOutside'] = '1';		
	$wpARISettings['lightbox']['touch_enabled'] = '1';		
	$wpARISettings['lightbox']['thumbs']['autoStart'] = '0';		
	$wpARISettings['lightbox']['thumbs']['hideOnClose'] = '0';		
	$wpARISettings['advanced']['load_scripts_in_footer'] = '1';			
	update_option( 'ari_fancy_lightbox_settings', $wpARISettings ); 

	delete_option( 'bp_setup_ari_fancy_lightbox_initial', 'completed' );
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
	
	$wpSEOSettings['noindex-testimonials'] = '1';
	$wpSEOSettings['post_types-testimonials-maintax'] = '1';
	$wpSEOSettings['noindex-elements'] = '1';
	$wpSEOSettings['post_types-elements-maintax'] = '1';	
	$wpSEOSettings['noindex-universal'] = '1';
	$wpSEOSettings['post_types-universal-maintax'] = '1';	
	$wpSEOSettings['noindex-tax-image-categories'] = '1';
	$wpSEOSettings['noindex-tax-image-tags'] = '1';	
	update_option( 'wpseo_titles', $wpSEOSettings );

	$wpSEOSocial = get_option( 'wpseo_social' );		
	$wpSEOSocial['facebook_site'] = $GLOBALS['customer_info']['facebook'];
	$wpSEOSocial['instagram_url'] = $GLOBALS['customer_info']['instagram'];
	$wpSEOSocial['linkedin_url'] = $GLOBALS['customer_info']['linkedin'];
	$wpSEOSocial['og_default_image'] = get_bloginfo("url").'/wp-content/uploads/logo.png';
	$wpSEOSocial['og_default_image_id'] = attachment_url_to_postid( get_bloginfo("url").'/wp-content/uploads/logo.png' );
	$wpSEOSocial['opengraph'] = '1';
	$wpSEOSocial['pinterest_url'] = $GLOBALS['customer_info']['pinterest'];
	$wpSEOSocial['twitter_site'] = $GLOBALS['customer_info']['twitter'];
	$wpSEOSocial['youtube_url'] = $GLOBALS['customer_info']['youtube'];	
	update_option( 'wpseo_social', $wpSEOSocial );

	$wpSEOLocal = get_option( 'wpseo_local' );		
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
	if ( $GLOBALS['customer_info']['business-type'] == "tattoo shop" ) $wpSEOLocal['business_type'] = 'Tattoo parlor';	

	if ( $GLOBALS['customer_info']['site-type'] == "hvac" ) $wpSEOLocal['business_type'] = 'HVACBusiness';		
	
	$wpSEOLocal['location_address'] = $GLOBALS['customer_info']['street'];
	$wpSEOLocal['location_city'] = $GLOBALS['customer_info']['site-city'];
	$wpSEOLocal['location_state'] = $GLOBALS['customer_info']['state-full'];
	$wpSEOLocal['location_zipcode'] = $GLOBALS['customer_info']['zip'];
	$wpSEOLocal['location_country'] = 'US';
	$wpSEOLocal['location_phone'] = $GLOBALS['customer_info']['area'].$GLOBALS['customer_info']['phone'];
	$wpSEOLocal['location_email'] = $GLOBALS['customer_info']['email'];
	$wpSEOLocal['location_url'] = get_bloginfo("url");
	$wpSEOLocal['location_price_range'] = '$$';
	$wpSEOLocal['location_payment_accepted'] = "Cash, Credit Cards, Paypal";
	$wpSEOLocal['location_area_served'] = $GLOBALS['customer_info']['service-area'];
	$wpSEOLocal['location_coords_lat'] = $GLOBALS['customer_info']['lat'];
	$wpSEOLocal['location_coords_long'] = $GLOBALS['customer_info']['long'];
	$wpSEOLocal['hide_opening_hours'] = 'on';
	$wpSEOLocal['address_format'] = 'address-state-postal';
	update_option( 'wpseo_local', $wpSEOLocal );
endif;

// Basic Settings		
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

if ( get_option('bp_setup_2022_05_02') != "completed" ) :	
	//deleteMeta( get_page_by_path('site-header', OBJECT, 'elements')->ID, 'call-clicks' );
	//deleteMeta( get_page_by_path('site-header', OBJECT, 'elements')->ID, 'email-clicks' );
endif;	

delete_option( 'bp_setup_2022_03_29' );
update_option( 'bp_setup_2022_05_02', 'completed' );

update_option( 'bp_chrons_last_run', time() );	

/*--------------------------------------------------------------
# Universal Pages
--------------------------------------------------------------*/
if ( is_null(get_page_by_path('customer-care-dealer', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'american standard' || in_array('american standard', $GLOBALS['customer_info']['site-brand'])) ) wp_insert_post( array( 'post_title' => 'Customer Care Dealer', 'post_content' => '[get-universal-page slug="page-hvac-customer-care-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

if ( is_null(get_page_by_path('ruud-pro-partner', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'ruud' || in_array('ruud', $GLOBALS['customer_info']['site-brand'])) ) wp_insert_post( array( 'post_title' => 'Ruud Pro Partner', 'post_content' => '[get-universal-page slug="page-hvac-ruud-pro-partner"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

if ( is_null(get_page_by_path('comfortmaker-elite-dealer', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'comfortmaker' || in_array('comfortmaker', $GLOBALS['customer_info']['site-brand'])) ) wp_insert_post( array( 'post_title' => 'Comfortmaker Elite Dealer', 'post_content' => '[get-universal-page slug="page-hvac-comfortmaker-elite-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', ));		

if ( is_null(get_page_by_path('tempstar-elite-dealer', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'tempstar' || in_array('tempstar', $GLOBALS['customer_info']['site-brand'])) ) wp_insert_post( array( 'post_title' => 'Tempstar Elite Dealer', 'post_content' => '[get-universal-page slug="page-hvac-tempstar-elite-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

if ( is_null(get_page_by_path('maintenance-tips', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' ) wp_insert_post( array( 'post_title' => 'Maintenance Tips', 'post_content' => '[get-universal-page slug="page-hvac-maintenance-tips"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

if ( is_null(get_page_by_path('symptom-checker', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' ) wp_insert_post( array( 'post_title' => 'Symptom Checker', 'post_content' => '[get-universal-page slug="page-hvac-symptom-checker"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

if ( is_null(get_page_by_path('faq', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' ) wp_insert_post( array( 'post_title' => 'FAQ', 'post_content' => '[get-universal-page slug="page-hvac-faq"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

if ( is_null(get_page_by_path('profile', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'profile' ) wp_insert_post( array( 'post_title' => 'Profile', 'post_content' => '[get-universal-page slug="page-profile"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

if ( is_null(get_page_by_path('profile-directory', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'profile' ) wp_insert_post( array( 'post_title' => 'Profile Directory', 'post_content' => '[get-universal-page slug="page-profile-directory"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

if ( is_null(get_page_by_path('privacy-policy', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Privacy Policy', 'post_content' => '[get-universal-page slug="page-privacy-policy"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

if ( is_null(get_page_by_path('terms-conditions', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Terms & Conditions', 'post_content' => '[get-universal-page slug="page-terms-conditions"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

if ( is_null(get_page_by_path('review', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Review', 'post_content' => '[get-universal-page slug="page-review"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

if ( is_null(get_page_by_path('email-received', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Email Received', 'post_content' => '[get-universal-page slug="page-email-received"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

?>