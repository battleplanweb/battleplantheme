<?php session_start();
/* Battle Plan Web Design Chron Jobs
 

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
	$formB = $formMail['body'];
	$formC = readMeta( $formID, "_form" );
	
	$formC = str_replace('<label for="user-name">Name<span class="required"></span></label>[text* user-name akismet:author id:user-name ]', "[seek label='Name' id='user-name' req='true'][text* user-name id:user-name akismet:author][/seek]", $formC);
	
	$formC = str_replace('<label for="user-city">City</label>[text user-city id:user-city]', "[seek label='City' id='user-city'][text user-city id:user-city][/seek]", $formC);	

	$formC = str_replace('<label for="user-email">Email<span class="required"></span></label>[email* user-email akismet:email id:user-email]', "[seek label='Email' id='user-email' req='true'][email* user-email id:user-email akismet:email][/seek]", $formC);	

	$formC = str_replace('<label for="user-phone">Phone</label>[tel user-phone id:user-phone placeholder "xxx-xxx-xxxx" ]', "[seek label='Phone' id='user-phone' req='true'][tel* user-phone id:user-phone][/seek]", $formC);	

	$formC = str_replace('<label for="user-message" class="full-width">What are your service needs?<span class="required"></span></label>[text* user-message class:full-width id:user-message]', "[seek label='How can we help?' id='user-message' width='full'][text user-message id:user-message][/seek]", $formC);	

	$formC = str_replace('<div class="vc_clearfix"></div>', "[seek label='button'][submit 'Submit'][/seek]", $formC);	
	$formC = str_replace('<div class="block block-button block-100">[submit "Submit"]</div>', "", $formC);	

	$formC = str_replace('<label for="user-message">Your Message</label>', "[layout grid='3-3-2']
", $formC);	

	$formC = str_replace('    [textarea user-message id:user-message]'
, "[seek label='Name' id='user-name' req='true'][text* user-name id:user-name akismet:author][/seek]", $formC);

	$formC = str_replace('    [text* user-name akismet:author id:user-name]', "[seek label='Phone' id='user-phone' req='true'][tel* user-phone id:user-phone][/seek]", $formC);		
		
	$formC = str_replace('<label for="user-email">Email<span class="required"></span></label>', "[/layout]
", $formC);	

	$formC = str_replace('    [email* user-email akismet:email id:user-email]', "[seek label='Message' id='user-message' width='full'][textarea user-message id:user-message][/seek]", $formC);		

	$formC = str_replace('<label for="user-phone">Phone</label>', "[seek label='button'][submit 'Submit'][/seek]", $formC);	
	$formC = str_replace('    [tel user-phone id:user-phone placeholder "xxx-xxx-xxxx"]', "", $formC);	
	$formC = str_replace('<div class="block block-button block-100">[submit "Submit"]</div>', "", $formC);
	
	$formC = str_replace('<label for="user-name">Name<span class="required"></span></label>', "[seek label='Email' id='user-email' req='true'][email* user-email id:user-email akismet:email][/seek]", $formC);	
	
	
	$formB = str_replace('Phone: [user-phone]', 'Email2: [user-email2]', $formB);	
	$formB = str_replace('Email: [user-email]', 'Phone: [user-phone]', $formB);	
	$formB = str_replace('Email2: [user-email2]', 'Email: [user-email]', $formB);
	$formB = str_replace('[user-message]', 'Message:
[user-message]', $formB);
	$formB = str_replace('--', '', $formB);
	$formB = str_replace('This e-mail was sent from the "Contact Us" page on the website.', '', $formB);
	$formB = str_replace('This e-mail was sent from the "Quote Request" form on the website.', '', $formB);
	
	$formB = str_replace('Service needs: Message:
[user-message]', 'How can we help?
[user-message]', $formB);

	$formMail = array( "subject"=>"", "sender"=>"", "recipient"=>"[get-biz info='email']", "body"=>$formB, "additional_headers"=>"", "attachments"=>"", "use_html"=>0, "exclude_blank"=>1);	

	updateMeta( $formID, "_form", $formC );				
	updateMeta( $formID, "_mail", $formMail );	
	deleteMeta ( $formID, "_mail_2" );			 

endforeach;	
	$forms = get_posts( array ( 'numberposts'=>-1, 'post_type'=>'wpcf7_contact_form' ));
	foreach ( $forms as $form ) :
		$formID = $form->ID;
		$formMail = readMeta( $formID, "_mail" );
		$formTitle = get_the_title($formID);
		if ( strpos( strtolower($formTitle), 'contact' ) !== false) $formTitle = "Customer Contact";
		if ( strpos( strtolower($formTitle), 'quote' ) !== false) $formTitle = "Quote Request";		
		 
		$formMail['subject'] = $formTitle." · Website · ".$GLOBALS['customer_info']['name'];
		$formMail['sender'] = "[user-name] <email@admin.".do_shortcode('[get-domain-name ext="true"]').">";
		$formMail['additional_headers'] = "Reply-to: [user-name] <[user-email]>\nCc: Website Administrator <email@battleplanwebdesign.com>";
		$formMail['use_html'] = 0;
		$formMail['exclude_blank'] = 1;

		updateMeta( $formID, "_mail", $formMail );	
		deleteMeta ( $formID, "_mail_2" );
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
		if ( $postType == "post" || $postType == "page" || $postType == "optimized" ) :
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
	$wpSEOLocal['business_type'] = 'Organization';
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

	delete_option( 'wpmudev_recommended_plugins_registered');			
	delete_option( 'wphb_settings');
	delete_option( 'smush_global_stats');
	delete_option( 'dir_smush_stats');
	delete_option( 'theia-upload-cleaner-progress-file');
	delete_option( 'wprmenu_options');
	delete_option( 'wr2x_rating_date');
	delete_option( 'wphb-notice-uptime-info-show');
	delete_option( 'wphb_version');
	delete_option( 'wphb-new-user-tour');
	delete_option( 'wphb-notice-http2-info-show');
	delete_option( 'wphb_styles_collection');
	delete_option( 'wphb_scripts_collection');
	delete_option( 'wpmudev_recommended_plugins_registered');

	update_option( 'auto_update_core_dev', 'enabled' );
	update_option( 'auto_update_core_minor', 'enabled' );
	update_option( 'auto_update_core_major', 'enabled' );			
endif;

// Basic Settings		
$sidebars_widgets = get_option( 'sidebars_widgets' );
$sidebars_widgets['wp_inactive_widgets'] = array();
update_option( 'sidebars_widgets', $sidebars_widgets );

update_option( 'blogname', $GLOBALS['customer_info']['name'] );
update_option( 'blogdescription', $GLOBALS['customer_info']['city'].', '.$GLOBALS['customer_info']['state-abbr'] );
update_option( 'admin_email', 'info@battleplanwebdesign.com' );
update_option( 'admin_email_lifespan', '9999999999999' );
update_option( 'default_comment_status', 'closed' );
update_option( 'default_ping_status', 'closed' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'wpe-rand-enabled', '1' );

battleplan_delete_prefixed_options( 'ac_cache_data_' );
battleplan_delete_prefixed_options( 'ac_cache_expires_' );
battleplan_delete_prefixed_options( 'ac_api_request_' );	
battleplan_delete_prefixed_options( 'ac_sorting_' );
battleplan_delete_prefixed_options( 'wp-smush-' );
battleplan_delete_prefixed_options( 'wp_smush_' );
battleplan_delete_prefixed_options( 'client_' );

delete_option( 'bp_setup_2021_08_15' );
update_option( 'bp_setup_2021_12_05', 'completed' );

update_option( 'bp_chrons_last_run', time() );			

/*--------------------------------------------------------------
# Universal Pages
--------------------------------------------------------------*/
add_action('init', 'battleplan_buildUniversalPages');
function battleplan_buildUniversalPages() {
	if ( is_null(get_page_by_path('customer-care-dealer', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'american standard' || in_array('american standard', $GLOBALS['customer_info']['site-brand'])) ) wp_insert_post( array( 'post_title' => 'Customer Care Dealer', 'post_content' => '[get-universal-page slug="page-hvac-customer-care-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

	if ( is_null(get_page_by_path('ruud-pro-partner', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'ruud' || in_array('ruud', $GLOBALS['customer_info']['site-brand'])) ) wp_insert_post( array( 'post_title' => 'Ruud Pro Partner', 'post_content' => '[get-universal-page slug="page-hvac-ruud-pro-partner"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

	if ( is_null(get_page_by_path('comfortmaker-elite-dealer', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' && ($GLOBALS['customer_info']['site-brand'] == 'comfortmaker' || in_array('comfortmaker', $GLOBALS['customer_info']['site-brand'])) ) wp_insert_post( array( 'post_title' => 'Comfortmaker Elite Dealer', 'post_content' => '[get-universal-page slug="page-hvac-comfortmaker-elite-dealer"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

	if ( is_null(get_page_by_path('maintenance-tips', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' ) wp_insert_post( array( 'post_title' => 'Maintenance Tips', 'post_content' => '[get-universal-page slug="page-hvac-maintenance-tips"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	

	if ( is_null(get_page_by_path('symptom-checker', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' ) wp_insert_post( array( 'post_title' => 'Symptom Checker', 'post_content' => '[get-universal-page slug="page-hvac-symptom-checker"]', 'post_status' => 'publish', 'post_type' => 'universal', ));
	
	if ( is_null(get_page_by_path('faq', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'hvac' ) wp_insert_post( array( 'post_title' => 'FAQ', 'post_content' => '[get-universal-page slug="page-hvac-faq"]', 'post_status' => 'publish', 'post_type' => 'universal', ));
	
	if ( is_null(get_page_by_path('profile', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'profile' ) wp_insert_post( array( 'post_title' => 'Profile', 'post_content' => '[get-universal-page slug="page-profile"]', 'post_status' => 'publish', 'post_type' => 'universal', ));
		
	if ( is_null(get_page_by_path('profile-directory', OBJECT, 'universal')) && $GLOBALS['customer_info']['site-type'] == 'profile' ) wp_insert_post( array( 'post_title' => 'Profile Directory', 'post_content' => '[get-universal-page slug="page-profile-directory"]', 'post_status' => 'publish', 'post_type' => 'universal', ));
	
	if ( is_null(get_page_by_path('privacy-policy', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Privacy Policy', 'post_content' => '[get-universal-page slug="page-privacy-policy"]', 'post_status' => 'publish', 'post_type' => 'universal', ));
	
	if ( is_null(get_page_by_path('terms-conditions', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Terms & Conditions', 'post_content' => '[get-universal-page slug="page-terms-conditions"]', 'post_status' => 'publish', 'post_type' => 'universal', ));
	
	if ( is_null(get_page_by_path('review', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Review', 'post_content' => '[get-universal-page slug="page-review"]', 'post_status' => 'publish', 'post_type' => 'universal', ));	
}

?>