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
require_once get_template_directory().'/google-api-php-client/vendor/autoload.php';

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;

add_action( 'wp_ajax_run_chron_jobs', 'battleplan_run_chron_jobs_ajax' );
add_action( 'wp_ajax_nopriv_run_chron_jobs', 'battleplan_run_chron_jobs_ajax' );
function battleplan_run_chron_jobs_ajax() {
	$admin = $_POST['admin'];	

	if ( $admin == "true" ) : $chronSpan = 600; 
	else: $chronSpan = 2 * (24 * 60 * 60); endif;
	
	$bpChrons = get_option( 'bp_chrons_last_run' );	
	$timePast = time() - $bpChrons;
		
	if ( $timePast > $chronSpan || get_option('bp_setup_2022_05_02') != "completed" ) :	

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
			$wpSEOSettings['display-metabox-pt-testimonials'] = '1';
			$wpSEOSettings['noindex-elements'] = '1';
			$wpSEOSettings['display-metabox-pt-elements'] = '1';	
			$wpSEOSettings['noindex-universal'] = '1';
			$wpSEOSettings['display-metabox-pt-universal'] = '1';	
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
		update_option( 'users_can_register', '0' );

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
# Convert States to Abbr
--------------------------------------------------------------*/				
		function state_abbr($name) {
			$states = array('alabama'=>'AL','alaska'=>'AK','arizona'=>'AZ','arkansas'=>'AR','california'=>'CA','colorado'=>'CO','connecticut'=>'CT','delaware'=>'DE','dist of columbia'=>'DC','dist. of columbia'=>'DC','district of columbia'=>'DC','florida'=>'FL','georgia'=>'GA','guam'=>'GU','hawaii'=>'HI','idaho'=>'ID','illinois'=>'IL','indiana'=>'IN','iowa'=>'IA','kansas'=>'KS','kentucky'=>'KY','louisiana'=>'LA','maine'=>'ME','maryland'=>'MD','massachusetts'=>'MA','michigan'=>'MI','minnesota'=>'MN','mississippi'=>'MS','missouri'=>'MO','montana'=>'MT','nebraska'=>'NE','nevada'=>'NV','new hampshire'=>'NH','new jersey'=>'NJ','new mexico'=>'NM','new york'=>'NY','north carolina'=>'NC','north dakota'=>'ND','ohio'=>'OH','oklahoma'=>'OK','oregon'=>'OR','pennsylvania'=>'PA','puerto rico'=>'PR','rhode island'=>'RI','south carolina'=>'SC','south dakota'=>'SD','tennessee'=>'TN','texas'=>'TX','utah'=>'UT','vermont'=>'VT','virgin islands'=>'VI','virginia'=>'VA','washington'=>'WA','washington d.c.'=>'DC','washington dc'=>'DC','west virginia'=>'WV','wisconsin'=>'WI','wyoming'=>'WY','armed forces africa'=>'AF','armed forces americas'=>'AA','armed forces canada'=>'AC','armed forces europe'=>'AE','armed forces middle east'=>'AM','armed forces pacific'=>'AP','alberta'=>'AB','british columbia'=>'BC','manitoba'=>'MB','new brunswick'=>'NB','newfoundland & labrador'=>'NL','northwest territories'=>'NT','nova scotia'=>'NS','nunavut'=>'NU','ontario'=>'ON','prince edward island'=>'PE','quebec'=>'QC','saskatchewan'=>'SK','yukon territory'=>'YT');
			if ($name) $name = trim($name);
			$new = $states[strtolower($name)];
			if (!$new) :
				foreach ($states as $str => $res) :
					if (strpos($str,$name) !== false) $state[] .= $res;
				endforeach;
				if (count($state) < 2) return ($state[0]) ? $state[0] : false;
			endif;

			return ($new) ? $new : (isset($req) ? false : ucwords(strtolower($name)));
		}		
		
/*--------------------------------------------------------------
# Sync with Google Analytics
--------------------------------------------------------------*/
		$GLOBALS['customer_info'] = get_option('customer_info');
		$ga4_id = $GLOBALS['customer_info']['google-tags']['prop-id'];
		$ua_id = $GLOBALS['customer_info']['google-tags']['ua-view'];
		$client = new BetaAnalyticsDataClient(['credentials'=>get_template_directory().'/vendor/atomic-box-306317-0b19b6a3a6c1.json']);
		
		$rewind4Years = '2014-01-01';
		
		function initializeAnalytics() {
			$client = new Google_Client();
			$client->setApplicationName("Stats Reporting");
			$client->setAuthConfig(get_template_directory().'/vendor/atomic-box-306317-0b19b6a3a6c1.json');
			$client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
			$analytics = new Google_Service_Analytics($client);
			return $analytics; 
		}

		function getResults($analytics, $ua_id, $start_date, $end_date, $param2, $param1) {
			return $analytics->data_ga->get ( 'ga:'.$ua_id, $start_date, $end_date, $param1, $param2 );
		}
		
		$getCPT = getCPT();
		foreach ($getCPT as $postType) :
			$getPosts = new WP_Query( array ('posts_per_page'=>-1, 'post_type'=>$postType ));
			if ( $getPosts->have_posts() ) : while ( $getPosts->have_posts() ) : $getPosts->the_post(); 
				deleteMeta( get_the_ID(), 'log-views-now');			
				deleteMeta( get_the_ID(), 'log-views-time');					
				deleteMeta( get_the_ID(), 'log-tease-time');			
				deleteMeta( get_the_ID(), 'log-views-today');	
				deleteMeta( get_the_ID(), 'log-views-total-7day');			
				deleteMeta( get_the_ID(), 'log-views-total-30day');	
				deleteMeta( get_the_ID(), 'log-views-total-90day');	
				deleteMeta( get_the_ID(), 'log-views-total-180day');	
				deleteMeta( get_the_ID(), 'log-views-total-365day');	
				deleteMeta( get_the_ID(), 'log-views');				
			endwhile; wp_reset_postdata(); endif;		
		endforeach;
		$siteHeader = getID('site-header');
		deleteMeta( $siteHeader, 'load-number-desktop');			
		deleteMeta( $siteHeader, 'load-speed-desktop');		
		deleteMeta( $siteHeader, 'load-number-mobile');				
		deleteMeta( $siteHeader, 'load-speed-mobile');	
		deleteMeta( $siteHeader, 'log-views');					
		deleteMeta( $siteHeader, 'log-views-referrers');
		deleteMeta( $siteHeader, 'log-views-cities');
		deleteMeta( $siteHeader, 'pages-viewed');
		deleteMeta( $siteHeader, 'call-clicks');
		deleteMeta( $siteHeader, 'email-clicks');		


// Gather GA4 stats (for Popular Pages Stats)
		$pageCounts = array(1,7,30,90,180,365);

		foreach ($pageCounts as $pageCount) :
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => $pageCount.'daysAgo', 'end_date' => 'today' ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'pagePath' ]),
					new Dimension([ 'name' => 'country' ]),		
				],
				'metrics' => [
					new Metric([ 'name' => 'screenPageViews' ]),	
				]
			]);

			foreach ($response->getRows() as $row) :			
				if ( $row->getDimensionValues()[1]->getValue() == "United States" ) :	
					$pagePaths[] = $row->getDimensionValues()[0]->getValue();					
					$views[] = $row->getMetricValues()[0]->getValue();					
				endif;
			endforeach;

			foreach ($pagePaths as $key=>$path) :
				if ( $path == "" || $path == "/" ) :
					$id = get_option('page_on_front');					
				else:
					$id = getID($path);
				endif;				

				if ( $pageCount == 1) : $pageKey = 'log-views-today';
				else: $pageKey = 'log-views-total-'.$pageCount.'day'; endif;

				updateMeta($id, $pageKey, $views[$key]);
			endforeach; 	

// Gather UA stats (for Popular Pages Stats)
			if ( $ua_id ) :
				$analytics = initializeAnalytics();

				$end_date = date("Y-m-d");
				$start_date = "-".$pageCount." Days";
				$start_date = date("Y-m-d", strtotime($start_date));
				$param2 = array('dimensions'=>'ga:pagePath,ga:country', 'max-results'=>10000);
				$param1 = 'ga:pageviews';				
				$results = getResults($analytics, $ua_id, $start_date, $end_date, $param2, $param1);
				$rows = $results->getRows();

				foreach ($rows as $path) :
					if ( $path[1] == "United States" ) :
						if ( $path[0] == "" || $path[0] == "/" ) :
							$id = get_option('page_on_front');					
						else:
							$id = getID($path[0]);
						endif;				

						if ( $pageCount == 1) : $pageKey = 'log-views-today';
						else: $pageKey = 'log-views-total-'.$pageCount.'day'; endif;

						$pageViews = intval(readMeta($id, $pageKey));

						if ( $pageViews ) : $pageViews = $pageViews + intval($path[2]);
						else: $pageViews = $path[2]; endif;

						updateMeta($id, $pageKey, $pageViews);
					endif;
				endforeach; 
			endif;
		endforeach;	
		
		if ( $admin == true ) :
		
// Gather GA4 Advanced Stats
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => '90daysAgo', 'end_date' => 'today' ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'pageReferrer' ]),
					new Dimension([ 'name' => 'country' ]),	
					new Dimension([ 'name' => 'city' ]),	
					new Dimension([ 'name' => 'region' ]),	
					new Dimension([ 'name' => 'browser' ]),	
					new Dimension([ 'name' => 'screenResolution' ]),	
					new Dimension([ 'name' => 'deviceCategory' ]),	
					new Dimension([ 'name' => 'percentScrolled' ]),	
				],
				'metrics' => [
					new Metric([ 'name' => 'totalUsers' ]),	
				]
			]);
			
			foreach ($response->getRows() as $row) :			
				if ( $row->getDimensionValues()[1]->getValue() == "United States" ) :	
					$referrers[] =$row->getDimensionValues()[0]->getValue();					
					$location[] = $row->getDimensionValues()[2]->getValue().', '.state_abbr($row->getDimensionValues()[3]->getValue());
					$browser[] = $row->getDimensionValues()[4]->getValue();					
					$resolution[] = $row->getDimensionValues()[5]->getValue();					
					$deviceCategory[] = $row->getDimensionValues()[6]->getValue();					
					$scrolled[] = $row->getDimensionValues()[7]->getValue();					
				endif;
			endforeach;

			$referrer_stats = $referrers;
			$location_stats = $location;
			$tech_stats = array( 'browser'=> $browser, 'device'=>$deviceCategory, 'resolution'=>$resolution, 'scrolled'=>$scrolled );		

		
// Gather GA4 stats (for Visitor Trends)
			$response = $client->runReport([
				'property' => 'properties/'.$ga4_id,
				'dateRanges' => [
					new DateRange([ 'start_date' => $rewind4Years, 'end_date' => 'today' ]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'date' ]),
					new Dimension([ 'name' => 'country' ]),		
					new Dimension([ 'name' => 'firstUserMedium' ]),	
				],
				'metrics' => [
					new Metric([ 'name' => 'totalUsers' ]),	
					new Metric([ 'name' => 'screenPageViews' ]),	
					new Metric([ 'name' => 'sessions' ]),	
					new Metric([ 'name' => 'engagedSessions' ]),
				]
			]);

			foreach ($response->getRows() as $row) :			
				if ( $row->getDimensionValues()[1]->getValue() == "United States" ) :	
					$dates[] = strtotime($row->getDimensionValues()[0]->getValue());
					$sources[] = $row->getDimensionValues()[2]->getValue();
					$users[] = $row->getMetricValues()[0]->getValue();
					$pageviews[] = $row->getMetricValues()[1]->getValue();
					$sessions[] = $row->getMetricValues()[2]->getValue();
					$engaged[] = $row->getMetricValues()[3]->getValue();
				endif;
			endforeach;

			array_multisort($dates, $sources, $users, $pageviews, $sessions, $engaged);
			
			$days = count($dates);

			$cDate = $dates[0];
			$cUsers = $cPageviews = $cSearch = $cSessions = $cEngaged = 0;

			foreach ( $dates as $key=>$date ) :
				if ( $date != $cDate ) :
					$diff = $date - $cDate;
					$dDate = $cDate;
					if ( $diff > 86400 ) :
						$skip = ( $diff / 86400 ) - 1;		
						for ( $x=0; $x<$skip; $x++ ) :
							$dDate = $dDate + 86400;
							$stats[] = array ('date'=>date( "Y-m-d", $dDate), 'users'=>0, 'search'=>0, 'pageviews'=>0, 'sessions'=>0, 'engaged'=>0 );
						endfor;			
					endif;

					$stats[] = array ('date'=>date( "Y-m-d", $cDate), 'users'=>$cUsers, 'search'=>$cSearch, 'pageviews'=>$cPageviews, 'sessions'=>$cSessions, 'engaged'=>$cEngaged );
				
					$cUsers = $cPageviews = $cSearch = $cSessions = $cEngaged = 0;
					$cDate = $date;
				endif;	
				$cUsers = $cUsers + $users[$key];	
				$cPageviews = $cPageviews + $pageviews[$key];	
				$cSessions = $cSessions + $sessions[$key];	
				$cEngaged = $cEngaged + $engaged[$key];	
				if ( $sources[$key] == 'organic' ) : $cSearch = $cSearch + $users[$key]; endif;
			endforeach;

			$ua_end = $stats[0]['date'];


// Gather UA stats (for Visitor Trends)
			if ( $ua_id ) :
				$analytics = initializeAnalytics();			
				$param2 = array('dimensions'=>'ga:date,ga:country', 'max-results'=>10000);
				$param1 = 'ga:newUsers,ga:pageviews,ga:sessions,ga:bounces';
				$param1b = $param1.',ga:organicSearches';		
				$results = getResults($analytics, $ua_id, $rewind4Years, '2020-04-04', $param2, $param1);
				$rows = $results->getRows();
				$cDate = 1240790400;

				foreach ( $rows as $dates ) :
					if ( $dates[1] == "United States" ) :

						if ( strtotime($dates[0]) != $cDate ) :
							$diff = strtotime($dates[0]) - $cDate;
							$dDate = $cDate;
							if ( $diff > 86400 ) :
								$skip = ( $diff / 86400 ) - 1;		
								for ( $x=0; $x<$skip; $x++ ):
									$dDate = $dDate + 86400;
									$stats[] = array ('date'=>date( "Y-m-d", $dDate), 'users'=>0, 'search'=>0, 'pageviews'=>0, 'sessions'=>0, 'engaged'=>0 );
								endfor;			
							endif;

							$stats[] = array ('date'=>date( "Y-m-d", strtotime($dates[0])), 'users'=>$dates[2], 'search'=>$dates[6], 'pageviews'=>$dates[3], 'sessions'=>$dates[4], 'engaged'=>($dates[4] -$dates[5]) );
							$cDate = strtotime($dates[0]);
						endif;		
					endif;
				endforeach;

				$results = getResults($analytics, $ua_id, '2020-04-05', $ua_end, $param2, $param1b);
				$rows = $results->getRows();
				$cDate = 1586131200;

				foreach ( $rows as $dates ) :
					if ( $dates[1] == "United States" ) :
						if ( strtotime($dates[0]) != $cDate ) :
							$diff = strtotime($dates[0]) - $cDate;
							$dDate = $cDate;
							if ( $diff > 86400 ) :
								$skip = ( $diff / 86400 ) - 1;		
								for ( $x=0; $x<$skip; $x++ ):
									$dDate = $dDate + 86400;
									$stats[] = array ('date'=>date( "Y-m-d", $dDate), 'users'=>0, 'search'=>0, 'pageviews'=>0, 'sessions'=>0, 'engaged'=>0 );
								endfor;			
							endif;

							$stats[] = array ('date'=>date( "Y-m-d", strtotime($dates[0])), 'users'=>$dates[2], 'search'=>$dates[6], 'pageviews'=>$dates[3], 'sessions'=>$dates[4], 'engaged'=>($dates[4] -$dates[5]) );
							$cDate = strtotime($dates[0]);
						endif;		
					endif;
				endforeach;
			endif;

			asort($stats);
			update_option( 'stats_basic', $stats );
			update_option( 'stats_referrers', $referrer_stats);			
			update_option( 'stats_locations', $location_stats);
			update_option( 'stats_tech', $tech_stats);
			delete_option( 'stats_page_stats', $page_stats);
		endif;			


		update_option( 'bp_chrons_last_run', time() );	
		$response = array( 'chron' => 'Updated'.$chronUpdated.'!' );		
	else: 	 
		$timeUntil = (($chronSpan - $timePast) / 60) + 1;		
		if ( floor($timeUntil / 60) > 0 ) $hours = floor($timeUntil / 60)." hours & ";
    	$response = array( 'chron' => 'Will run in '.$hours.($timeUntil % 60).' minutes.' );
	endif;	
			
	wp_send_json( $response );	
}

?>