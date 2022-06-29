<?php 
/* Battle Plan Web Design Functions: Chron Jobs

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Chron Jobs
# Universal Pages
# Convert States to Abbr
# Sync with Google Analytics

/*--------------------------------------------------------------go
# Chron Jobs
--------------------------------------------------------------*/

if ( get_option('bp_setup_2022_06_26') != "completed" ) :	
	require_once get_template_directory().'/includes/includes-mass-site-update.php';
endif;	

delete_option( 'bp_setup_2022_05_02' );
update_option( 'bp_setup_2022_06_26', 'completed' );
		
		

require_once get_template_directory().'/vendor/autoload.php';
require_once get_template_directory().'/google-api-php-client/vendor/autoload.php';

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;

add_action( 'wp_ajax_run_chron_jobs', 'battleplan_run_chron_jobs_ajax' );
add_action( 'wp_ajax_nopriv_run_chron_jobs', 'battleplan_run_chron_jobs_ajax' );
function battleplan_run_chron_jobs_ajax() {
	$admin = $_POST['admin'];	
	$runChron = "false";

	if ( $admin == "true" ) : 
		if (function_exists('battleplan_updateSiteOptions')) battleplan_updateSiteOptions();
		$chronSpan = 3600; // every 60 min
		$bpChrons = get_option( 'bp_chrons_last_run' );	
		$timePast = time() - $bpChrons;		
		if ( $timePast > $chronSpan ) : $runChron = "true";	endif;	
	endif;
	
	$chronViews = get_option('bp_page_stats_250')['views'];
	if ( is_array($chronViews) ) :
		$chronViews = round(intval(array_sum($chronViews)) / 15); // get avg pageviews for 2 days
	else:
		$chronViews = 10;
	endif;
	$bpChrons = get_option( 'bp_chrons_pages' );	
	if ( !$bpChrons ) : $bpChrons = 0; endif;
	$pagesLeft = $chronViews - $bpChrons;		
	if ( $pagesLeft <= 0 ) : $runChron = "true"; endif;	
		
	if ( $runChron == "true" || get_option('bp_setup_2022_06_26') != "completed" ) :	

		if (function_exists('battleplan_remove_user_roles')) battleplan_remove_user_roles();
		if (function_exists('battleplan_create_user_roles')) battleplan_create_user_roles();

// delete all options that begin with {$prefix}
		function battleplan_delete_prefixed_options( $prefix ) {
			global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'" );
		}	
		
// ARI FancyBox Settings Update
		if ( is_plugin_active('ari-fancy-lightbox/ari-fancy-lightbox.php') ) : 
		    //delete_option( 'ari_fancy_lightbox_settings' );
			
			/*
			$wpARISettings = get_option( 'ari_fancy_lightbox_settings' );
			$wpARISettings['convert']['wp_gallery']['convert'] = true;			
			$wpARISettings['convert']['wp_gallery']['grouping'] = true;
			
			$wpARISettings['convert']['images']['convert'] = true;			
			$wpARISettings['convert']['images']['post_grouping'] = true;
			$wpARISettings['convert']['images']['grouping_selector'] = '.gallery$$A';		
			$wpARISettings['convert']['images']['filenameToTitle'] = true;		
			$wpARISettings['convert']['images']['convertNameSmart'] = true;		
					
			$wpARISettings['lightbox']['thumbs']['autoStart'] = false;	
			$wpARISettings['lightbox']['loop'] = true;		
			$wpARISettings['lightbox']['arrows'] = true;			
			$wpARISettings['lightbox']['closeClickOutside'] = true;	
			$wpARISettings['lightbox']['keyboard'] = true;	
			$wpARISettings['lightbox']['touch_enabled'] = true;			
			$wpARISettings['lightbox']['autoFocus'] = true;		
			$wpARISettings['lightbox']['infobar'] = true;		
			$wpARISettings['lightbox']['trapFocus'] = false;	
			
			$wpARISettings['lightbox']['thumbs']['hideOnClose'] = false;		
			$wpARISettings['advanced']['load_scripts_in_footer'] = true;						
			update_option( 'ari_fancy_lightbox_settings', $wpARISettings ); 
			*/
		endif;

// WP Mail SMTP Settings Update
		if ( is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') ) : 	
			$wpMailSettings = get_option( 'wp_mail_smtp' );			
			$wpMailSettings['mail']['from_email'] = 'email@admin.'.str_replace('https://', '', get_bloginfo('url'));
			$wpMailSettings['mail']['from_name'] = 'Website Administrator';
			$wpMailSettings['mail']['mailer'] = 'sendinblue';
			$wpMailSettings['mail']['from_email_force'] = '1';
			$wpMailSettings['mail']['from_name_force'] = '1';		
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
		
// Widget Options - Extended Settings
		if ( is_plugin_active('extended-widget-options/plugin.php') ) :
			$widgetOpts = get_option( 'widgetopts_settings' );

			$widgetOpts['settings']['visibility']['post_type'] = '1';
			$widgetOpts['settings']['visibility']['taxonomies'] = '1';
			$widgetOpts['settings']['visibility']['misc'] = '1';		

			$widgetOpts['settings']['classes']['id'] = '1';
			$widgetOpts['settings']['classes']['type'] = 'both';
			$widgetOpts['settings']['classes']['classlists']['0'] = 'lock-to-top';
			$widgetOpts['settings']['classes']['classlists']['1'] = 'lock-to-bottom';
			$widgetOpts['settings']['classes']['classlists']['2'] = 'widget-essential';
			$widgetOpts['settings']['classes']['classlists']['3'] = 'widget-important';
			$widgetOpts['settings']['classes']['classlists']['4'] = 'remove-first';
			$widgetOpts['settings']['classes']['classlists']['5'] = 'widget-image';
			$widgetOpts['settings']['classes']['classlists']['6'] = 'widget-financing';
			$widgetOpts['settings']['classes']['classlists']['7'] = 'widget-set';

			$widgetOpts['settings']['dates']['days'] = '1';		
			$widgetOpts['settings']['dates']['date_range'] = '1';		

			$widgetOpts['visibility'] = 'activate';		
			$widgetOpts['devices'] = 'deactivate';
			$widgetOpts['urls'] = 'activate';
			$widgetOpts['alignment'] = 'deactivate';
			$widgetOpts['hide_title'] = 'activate';
			$widgetOpts['classes'] = 'activate';
			$widgetOpts['logic'] = 'deactivate';
			$widgetOpts['move'] = 'deactivate';
			$widgetOpts['clone'] = 'activate';
			$widgetOpts['links'] = 'deactivate';
			$widgetOpts['fixed'] = 'deactivate';
			$widgetOpts['columns'] = 'deactivate';
			$widgetOpts['roles'] = 'deactivate';			
			$widgetOpts['state'] = 'deactivate';
			$widgetOpts['dates'] = 'activate';			
			$widgetOpts['styling'] = 'deactivate';
			$widgetOpts['animation'] = 'deactivate';
			$widgetOpts['taxonomies'] = 'deactivate';
			$widgetOpts['disable_widgets'] = 'deactivate';
			$widgetOpts['permission'] = 'deactivate';
			$widgetOpts['shortcodes'] = 'deactivate';		
			$widgetOpts['cache'] = 'deactivate';
			$widgetOpts['search'] = 'deactivate';
			$widgetOpts['widget_area'] = 'deactivate';		
			$widgetOpts['import_export'] = 'deactivate';
			$widgetOpts['elementor'] = 'deactivate';
			$widgetOpts['beaver'] = 'deactivate';
			$widgetOpts['acf'] = 'deactivate';						
			update_option( 'widgetopts_settings', $widgetOpts );
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
			$wpSEOSettings['display-metabox-pt-testimonials'] = '0';
			$wpSEOSettings['noindex-elements'] = '1';
			$wpSEOSettings['display-metabox-pt-elements'] = '0';	
			$wpSEOSettings['noindex-universal'] = '1';
			$wpSEOSettings['display-metabox-pt-universal'] = '0';				
			$wpSEOSettings['noindex-tax-gallery-type'] = '1';	
			$wpSEOSettings['display-metabox-tax-gallery-type'] = '0';				
			$wpSEOSettings['noindex-tax-gallery-tags'] = '1';	
			$wpSEOSettings['display-metabox-tax-gallery-tags'] = '0';				
			$wpSEOSettings['noindex-tax-image-categories'] = '1';
			$wpSEOSettings['display-metabox-tax-image-categories'] = '0';	
			$wpSEOSettings['noindex-tax-image-tags'] = '1';	
			$wpSEOSettings['display-metabox-tax-image-tags'] = '0';	
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
			$wpSEOLocal['location_phone'] = $GLOBALS['customer_info']['area'].'-'.$GLOBALS['customer_info']['phone'];
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
		$ga4_id = $GLOBALS['customer_info']['google-tags']['prop-id'];
		$ua_id = $GLOBALS['customer_info']['google-tags']['ua-view'];
		$client = new BetaAnalyticsDataClient(['credentials'=>get_template_directory().'/vendor/atomic-box-306317-0b19b6a3a6c1.json']);
		$today = $ua_end = date( "Y-m-d" );		
		$rewind = date('Y-m-d', strtotime('-4 years'));		
		
		$citiesToExclude = array('Orangetree', 'Ashburn', 'Boardman');
		$states = array('alabama'=>'AL', 'arizona'=>'AZ', 'arkansas'=>'AR', 'california'=>'CA', 'colorado'=>'CO', 'connecticut'=>'CT', 'delaware'=>'DE', 'dist of columbia'=>'DC', 'dist. of columbia'=>'DC', 'district of columbia'=>'DC', 'florida'=>'FL', 'georgia'=>'GA', 'idaho'=>'ID', 'illinois'=>'IL', 'indiana'=>'IN', 'iowa'=>'IA', 'kansas'=>'KS', 'kentucky'=>'KY', 'louisiana'=>'LA', 'maine'=>'ME', 'maryland'=>'MD', 'massachusetts'=>'MA', 'michigan'=>'MI', 'minnesota'=>'MN', 'mississippi'=>'MS', 'missouri'=>'MO', 'montana'=>'MT', 'nebraska'=>'NE', 'nevada'=>'NV', 'new hampshire'=>'NH', 'new jersey'=>'NJ', 'new mexico'=>'NM', 'new york'=>'NY', 'north carolina'=>'NC', 'north dakota'=>'ND', 'ohio'=>'OH', 'oklahoma'=>'OK', 'oregon'=>'OR', 'pennsylvania'=>'PA', 'rhode island'=>'RI', 'south carolina'=>'SC', 'south dakota'=>'SD', 'tennessee'=>'TN', 'texas'=>'TX', 'utah'=>'UT', 'vermont'=>'VT', 'virginia'=>'VA', 'washington'=>'WA', 'washington d.c.'=>'DC', 'washington dc'=>'DC', 'west virginia'=>'WV', 'wisconsin'=>'WI', 'wyoming'=>'WY');
		$removedStates = array('alaska'=>'AK', 'hawaii'=>'HI',);
		
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

// Gather GA4 Stats 
		if ( $ga4_id ) :
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
					
				if ( $states[$state] && !in_array( $city, $citiesToExclude ) ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);					
					$page = rtrim($page, '/\\');
									
					$analytics[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'pages-viewed'=>$pagesViewed, 'resolution'=>$resolution, 'referrer'=>$referrer, 'engaged'=>$engaged, 'new-users'=>$newUsers );	
				endif;
			endforeach;			
			arsort($analytics);			
			$ua_end = date('Y-m-d', strtotime($analytics[0]['date']));
		endif;
		
// Gather UA Stats		 
		if ( $ua_id ) :
			$initAnalytics = initializeAnalytics();			
			$param2 = array('dimensions'=>'ga:date, ga:city, ga:region, ga:sourceMedium, ga:pagePath, ga:browser, ga:deviceCategory', 'max-results'=>10000);
			$param1 = 'ga:pageviews, ga:sessions, ga:bounces, ga:newUsers';
			
			$results = getResults($initAnalytics, $ua_id, $rewind, $ua_end, $param2, $param1);
			
			foreach ( $results->getRows() as $row ) : 
				$date = $row[0];
				$city = $row[1];
				$state = strtolower($row[2]);
				if ( array_key_exists($state, $states) ) $location = $city.', '.$states[$state];
				$source = $row[3];
				$page = $row[4];
				$browser = $row[5];
				$device = $row[6];				
				$pagesViewed = $row[7];		
				$sessions = $row[8];		
				$engaged = $sessions - $row[9];			
				$newUsers = $row[10];						

				if ( $states[$state] && !in_array( $city, $citiesToExclude ) && $sessions != '' ) :					
					if ( $city == '(not set)' ) $location = ucwords($state);					
					$page = rtrim($page, '/\\');
					
					$analytics[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'pages-viewed'=>$pagesViewed, 'sessions'=>$sessions, 'engaged'=>$engaged, 'new-users'=>$newUsers );	
				endif;
			endforeach;
		endif;
		
		arsort($analytics);			
		$ua_end = date('Y-m-d', strtotime($analytics[0]['date']));	
		
// Split session data into site hits
		foreach ( $analytics as $analyze ) :
			$date = $analyze['date'];
			$location = $analyze['location'];
			$page = $analyze['page'];
			$browser = $analyze['browser'];
			$device = $analyze['device'];	
			$resolution = $analyze['resolution'];
			$pageviews = $analyze['pages-viewed'];
			$sessions = (int)$analyze['sessions'];
			$engaged = $analyze['engaged'];
			$newUsers = $analyze['new-users'];
			$referrer = $analyze['referrer'];
			list($source, $medium) = explode(" / ", $analyze['source']);
			
	// Handle GA4 data points
			if ( $referrer ) :
				if ( strpos( $referrer, parse_url( get_site_url(), PHP_URL_HOST ) ) === false ) :	
					$siteHits[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'medium'=>$medium, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'resolution'=>$resolution, 'pages-viewed'=>$pageviews, 'sessions'=>'1', 'engaged'=>$engaged, 'new-users'=>$newUsers );	
				else :
					$siteHits[] = array ('date'=>$date, 'page'=>$page, 'pages-viewed'=>$pageviews, 'sessions'=>'0', 'engaged'=>$engaged );				
				endif;

	// Handle UA data points
			else:			
				if ( $sessions > 1 ) :			
					$pageviews = $pageviews / $sessions;
					$engaged = $engaged / $sessions;
					$newUsers = $newUsers / $newUsers;

					for ( $x=0;$x<$sessions;$x++) :			
						$siteHits[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'medium'=>$medium, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'pages-viewed'=>$pageviews, 'sessions'=>'1', 'engaged'=>$engaged, 'new-users'=>$newUsers );	
					endfor;

				elseif ( $sessions == 1 ) :			
					$siteHits[] = array ('date'=>$date, 'location'=>$location, 'source'=>$source, 'medium'=>$medium, 'page'=>$page, 'browser'=>$browser, 'device'=>$device, 'pages-viewed'=>$pageviews, 'sessions'=>'1', 'engaged'=>$engaged, 'new-users'=>$newUsers );	

				elseif ( $sessions == 0 ) :
					$siteHits[] = array ('date'=>$date, 'page'=>$page, 'pages-viewed'=>$pageviews, 'sessions'=>'0', 'engaged'=>$engaged );				
				endif;
			endif;

		endforeach;

// Set up array accounting for each day, no skips	
		$blankDate = strtotime($siteHits[array_key_last($siteHits)]['date']);
		$totalDays = (strtotime($today) - $blankDate) / 86400;
		
		for ( $x=0;$x<$totalDays;$x++) :
			$blankDate = $blankDate + 86400;		
			$dailyStats[ date('Y-m-d', $blankDate) ] = array ('location'=>array(), 'source'=>array(), 'medium'=>array(), 'page'=>array(), 'browser'=>array(), 'device'=>array(), 'resolution'=>array(), 'pages-viewed'=>'0', 'sessions'=>'0', 'engaged'=>'0', 'new-users'=>'0' );
		endfor;
				
// Compile data into daily stats
		foreach ( $siteHits as $siteHit ) :	
			$processing = strtotime($siteHit['date']);
			$timeSinceToday = $today - $processing;				
			$timeSinceLastView = $lastView - $processing;			
			$daysSinceToday = $timeSinceToday / 86400;

			if ( $timeSinceLastView > 0 ) :			
				$dailyStats[ date('Y-m-d', ($processing + 86400)) ] = array ('location'=>$allLocations, 'source'=>$allSources, 'medium'=>$allMediums, 'page'=>$allPages, 'browser'=>$allBrowsers, 'device'=>$allDevices, 'resolution'=>$allResolutions, 'pages-viewed'=>$totalPageviews, 'sessions'=>$totalSessions, 'engaged'=>$totalEngaged, 'new-users'=>$totalNewUsers );	

				$allLocations = $allSources = $allMediums = $allPages = $allBrowsers = $allDevices = $allResolutions = array();
				$totalPageviews = $totalSessions = $totalEngaged = $totalNewUsers = 0;
			endif;

			$lastView = $processing;

			$totalPageviews = $totalPageviews + $siteHit['pages-viewed'];
			$totalSessions = $totalSessions + $siteHit['sessions'];
			$totalEngaged = $totalEngaged + $siteHit['engaged'];
			$totalNewUsers = $totalNewUsers + $siteHit['new-users'];											
			
			if ( is_array($allPages) && array_key_exists($siteHit['page'], $allPages ) ) :
				$allPages[$siteHit['page']] += $siteHit['pages-viewed'];
			else:
				$allPages[$siteHit['page']] = $siteHit['pages-viewed'];
			endif;	
			
			if ( $siteHit['sessions'] == 1 ) :			
				if ( is_array($allLocations) && array_key_exists($siteHit['location'], $allLocations ) ) :
					$allLocations[$siteHit['location']] += 1;
				else:
					$allLocations[$siteHit['location']] = 1;
				endif;									
			
				if ( is_array($allSources) && array_key_exists($siteHit['source'], $allSources ) ) :
					$allSources[$siteHit['source']] += 1;
				else:
					$allSources[$siteHit['source']] = 1;
				endif;									
			
				if ( is_array($allMediums) && array_key_exists($siteHit['medium'], $allMediums ) ) :
					$allMediums[$siteHit['medium']] += 1;
				else:
					$allMediums[$siteHit['medium']] = 1;
				endif;									
			
				if ( is_array($allBrowsers) && array_key_exists($siteHit['browser'], $allBrowsers ) ) :
					$allBrowsers[$siteHit['browser']] += 1;
				else:
					$allBrowsers[$siteHit['browser']] = 1;
				endif;									
			
				if ( is_array($allDevices) && array_key_exists($siteHit['device'], $allDevices ) ) :
					$allDevices[$siteHit['device']] += 1;
				else:
					$allDevices[$siteHit['device']] = 1;
				endif;													
			
				if ( is_array($allResolutions) && array_key_exists($siteHit['resolution'], $allResolutions ) ) :
					$allResolutions[$siteHit['resolution']] += 1;
				else:
					$allResolutions[$siteHit['resolution']] = 1;
				endif;				
			endif;
			
		endforeach;	

		krsort($dailyStats);
		array_shift($dailyStats);
		update_option('bp_daily_stats', $dailyStats);	
		

		
		delete_option('bp_referrer_stats_100'); 
		delete_option('bp_location_stats_100'); 
		delete_option('bp_page_stats_100'); 
		delete_option('bp_tech_stats_100'); 
		delete_option('bp_referrer_stats_250'); 
		delete_option('bp_location_stats_250'); 
		delete_option('bp_page_stats_250'); 
		delete_option('bp_tech_stats_250'); 
		delete_option('bp_referrer_stats_500'); 
		delete_option('bp_location_stats_500'); 
		delete_option('bp_page_stats_500'); 
		delete_option('bp_tech_stats_500'); 
		delete_option('bp_referrer_stats'); 
		delete_option('bp_location_stats');  
		delete_option('bp_page_stats'); 
		delete_option('bp_tech_stats'); 
		
		
		
			
	/*	if ( $ga4_id ) :			
// Gather page view stats to attach to the individual IDs			
			$pageCounts = array(7,30,90,365);
			foreach ($pageCounts as $pageCount) :	
				$start = date('Y-m-d', strtotime('-'.$pageCount.' days'));	
				$end = date( "Y-m-d" );		
				$response = $client->runReport([
					'property' => 'properties/'.$ga4_id,
					'dateRanges' => [
						new DateRange([ 'start_date' => $start, 'end_date' => $end ]),
					],
					'dimensions' => [
						new Dimension([ 'name' => 'city' ]),
						new Dimension([ 'name' => 'country' ]),		
						new Dimension([ 'name' => 'pagePath' ]),
					],
					'metrics' => [
						new Metric([ 'name' => 'screenPageViews' ]),
					]
				]);

				unset($compilePaths);
				$complilePaths = array();

				foreach ( $response->getRows() as $row ) :
					$city = $row->getDimensionValues()[0]->getValue();
					$country = $row->getDimensionValues()[1]->getValue();
					$path = $row->getDimensionValues()[2]->getValue();
					$views = $row->getMetricValues()[0]->getValue();

					if ( $country == "United States" && !in_array( $city, $citiesToExclude ) ) :					
						if ( $path == "" || $path == "/" ) $path = "Home";
						if ( is_array($compilePaths) && array_key_exists($path, $compilePaths ) ) :
							$compilePaths[$path] += $views;
						else:
							$compilePaths[$path] = $views;
						endif;
					endif;
				endforeach;

				foreach ($compilePaths as $path=>$pathViews) :
					if ( $path == "Home" ) :
						$id = get_option('page_on_front');					
					else:
						$id = getID($path);
					endif;				

					if ( $pageCount == 1) : $pageKey = 'log-views-today';
					else: $pageKey = 'log-views-total-'.$pageCount.'day'; endif;

					updateMeta($id, 'log-views-total-'.$pageCount.'day', $pathViews);		
				endforeach;

			endforeach;	
		endif;
*/
		update_option( 'bp_chrons_last_run', time() );	
		delete_option( 'bp_chrons_pages' );
		update_option( 'bp_chrons_pages', 0 );	
		$response = array( 'dashboard' => 'The chron has updated.' );		
	else: 	 	
		$response = array( 'dashboard' => 'The chron will update after '.$pagesLeft.' more pageviews.' );
	endif;	

	if ( $admin == "false" ) :
		$bpChrons = get_option( 'bp_chrons_pages' );
		delete_option( 'bp_chrons_pages' );
		$bpChrons++;
		update_option( 'bp_chrons_pages', $bpChrons );	
	endif;
			
	wp_send_json( $response );	
}

?>