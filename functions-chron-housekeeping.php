<?php
/* Battle Plan Web Design Functions: Chron B — Housekeeping */

function bp_run_chron_housekeeping(bool $force = false): void {

	if (function_exists('battleplan_remove_user_roles')) battleplan_remove_user_roles();
	if (function_exists('battleplan_create_user_roles')) battleplan_create_user_roles();
	if (function_exists('battleplan_updateSiteOptions')) battleplan_updateSiteOptions();
	bp_check_for_post_updates();

	$customer_info = customer_info();
	$site          = str_replace('https://', '', get_bloginfo('url'));
	$rovin         = in_array($site, ["babeschicken.com","babescatering.com","babeschicken.tv","sweetiepiesribeyes.com","bubbascookscountry.com","rovindirectory.com","rovininc.com"], true);
	$bp_handles_mail = ($site !== "asairconditioning.com");

/*--------------------------------------------------------------
# WP Mail SMTP Settings
--------------------------------------------------------------*/

	if ($bp_handles_mail === true) {
		if (is_plugin_active('wp-mail-smtp/wp_mail_smtp.php')) {
			$wpMailSettings = get_option('wp_mail_smtp');
			if ($rovin === true) {
				$wpMailSettings['mail']['from_email']    = 'customer@website.' . $site;
				$wpMailSettings['sendinblue']['domain']  = 'website.' . $site;
			} else {
				$wpMailSettings['mail']['from_email']    = 'email@admin.' . $site;
				$wpMailSettings['sendinblue']['domain']  = 'admin.' . $site;
			}
			$wpMailSettings['mail']['from_name']        = strip_tags('Website · ' . str_replace(',', '', $customer_info['name']));
			$wpMailSettings['mail']['mailer']            = 'sendinblue';
			$wpMailSettings['mail']['from_email_force']  = '1';
			$wpMailSettings['mail']['from_name_force']   = '1';
			$wpMailSettings['sendinblue']['api_key']     = 'x' . _BREVO_API;
			update_option('wp_mail_smtp', $wpMailSettings);
		}
	}

/*--------------------------------------------------------------
# Contact Form 7 Settings
--------------------------------------------------------------*/

	if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
		$forms = get_posts(['numberposts' => -1, 'post_type' => 'wpcf7_contact_form']);
		foreach ($forms as $form) {
			$formID    = $form->ID;
			$formMail  = readMeta($formID, "_mail");
			$formTitle = get_the_title($formID);

			if ($formTitle == "Quote Request Form")       $formTitle = "Quote Request";
			if ($formTitle == "Contact Us Form")          $formTitle = "Customer Contact";
			if ($formTitle == "Request A Catering Quote") $formTitle = "Catering Quote";

			if ($rovin !== true && $bp_handles_mail === true) {
				$server_email            = "<email@admin." . str_replace('https://', '', get_bloginfo('url')) . ">";
				$formMail['subject']     = $formTitle . " · [user-name]";
				$formMail['sender']      = "Website · " . str_replace(',', '', $customer_info['name']) . " " . $server_email;
				$formMail['additional_headers'] = "Reply-to: [user-name] <[user-email]>\nBcc: Website Administrator <email@battleplanwebdesign.com>";
			}

			$formMail['use_html']      = 1;
			$formMail['exclude_blank'] = 1;
			updateMeta($formID, "_mail", $formMail);
		}
	}

/*--------------------------------------------------------------
# Yoast SEO Settings
--------------------------------------------------------------*/

	if (is_plugin_active('wordpress-seo-premium/wp-seo-premium.php')) {
		$customer_info = customer_info();
		$cur = get_option('wpseo_local') ?: [];

		$mapType = function(array $customer_info): string {
			$bt   = $customer_info['business-type'] ?? '';
			$type = is_array($bt) ? ($bt[0] ?? '') : $bt;
			if (!empty($customer_info['site-type']) && strtolower((string)$customer_info['site-type']) === 'hvac') return 'HVACBusiness';
			$type = trim($type) === '' ? 'LocalBusiness' : preg_replace('/\s+/', '', $type);
			return $type;
		};

		$desired = [
			'business_type'       => $mapType($customer_info),
			'location_address'    => $customer_info['street']     ?? '',
			'location_city'       => $customer_info['city']       ?? '',
			'location_state'      => $customer_info['state-full'] ?? ($customer_info['state-abbr'] ?? ''),
			'location_zipcode'    => $customer_info['zip']        ?? '',
			'location_country'    => 'US',
			'location_phone'      => (isset($customer_info['area'], $customer_info['phone']) ? $customer_info['area'] . '-' . $customer_info['phone'] : ''),
			'location_email'      => $customer_info['email']      ?? '',
			'location_url'        => get_bloginfo('url'),
			'location_coords_lat' => $customer_info['lat']        ?? '',
			'location_coords_long'=> $customer_info['long']       ?? '',
		];

		$hours = ci_build_hours($customer_info['hours']['periods'] ?? null, $customer_info['current-hours'] ?? null);
		$order     = [1,2,3,4,5,6,0];
		$daysYoast = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
		$byDay     = array_fill(0, 7, []);

		if (!empty($hours['openingHoursSpecification'])) {
			$mapName = ['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6,'Sunday'=>0];
			foreach ($hours['openingHoursSpecification'] as $spec) {
				$opens  = $spec['opens']  ?? null;
				$closes = $spec['closes'] ?? null;
				foreach ((array)($spec['dayOfWeek'] ?? []) as $dname) {
					$g = $mapName[$dname] ?? null;
					if ($g === null || !$opens || !$closes) continue;
					$byDay[$g][] = [$opens, $closes];
				}
			}
		}

		foreach ($order as $i => $g) {
			[$from, $to] = !empty($byDay[$g]) ? $byDay[$g][0] : ['Closed', 'Closed'];
			$desired["opening_hours_{$daysYoast[$i]}_from"]        = $from;
			$desired["opening_hours_{$daysYoast[$i]}_to"]          = $to;
			$desired["opening_hours_{$daysYoast[$i]}_second_from"] = '';
			$desired["opening_hours_{$daysYoast[$i]}_second_to"]   = '';
		}
		$desired['hide_opening_hours'] = (empty($byDay[0]) && empty($byDay[1]) && empty($byDay[2]) && empty($byDay[3]) && empty($byDay[4]) && empty($byDay[5]) && empty($byDay[6])) ? 'on' : 'off';

		$delta = array_diff_assoc($desired, $cur);
		if (!empty($delta)) {
			update_option('wpseo_local', array_replace($cur, $desired));
		}

		$wpSEOBase = get_option('wpseo');
		$wpSEOBase['enable_admin_bar_menu']              = 0;
		$wpSEOBase['enable_cornerstone_content']         = 0;
		$wpSEOBase['enable_xml_sitemap']                 = 1;
		$wpSEOBase['remove_feed_global']                 = 1;
		$wpSEOBase['remove_feed_global_comments']        = 1;
		$wpSEOBase['remove_feed_post_comments']          = 1;
		$wpSEOBase['remove_feed_authors']                = 1;
		$wpSEOBase['remove_feed_categories']             = 1;
		$wpSEOBase['remove_feed_tags']                   = 1;
		$wpSEOBase['remove_feed_custom_taxonomies']      = 1;
		$wpSEOBase['remove_feed_post_types']             = 1;
		$wpSEOBase['remove_feed_search']                 = 1;
		$wpSEOBase['remove_atom_rdf_feeds']              = 1;
		$wpSEOBase['remove_shortlinks']                  = 1;
		$wpSEOBase['remove_rest_api_links']              = 1;
		$wpSEOBase['remove_rsd_wlw_links']               = 1;
		$wpSEOBase['remove_oembed_links']                = 1;
		$wpSEOBase['remove_generator']                   = 1;
		$wpSEOBase['remove_emoji_scripts']               = 1;
		$wpSEOBase['remove_powered_by_header']           = 1;
		$wpSEOBase['remove_pingback_header']             = 1;
		$wpSEOBase['clean_campaign_tracking_urls']       = 0;
		$wpSEOBase['clean_permalinks']                   = 1;
		$wpSEOBase['clean_permalinks_extra_variables']   = 'loc,int,invite,rs,se_action,pmax,gclid,gbraid,wbraid,fbclid,msclkid';
		$wpSEOBase['search_cleanup']                     = 1;
		$wpSEOBase['search_cleanup_emoji']               = 1;
		$wpSEOBase['search_cleanup_patterns']            = 1;
		$wpSEOBase['deny_search_crawling']               = 1;
		$wpSEOBase['deny_wp_json_crawling']              = 1;
		$wpSEOBase['redirect_search_pretty_urls']        = 1;
		update_option('wpseo', $wpSEOBase);

		$wpSEOSettings = get_option('wpseo_titles');
		$wpSEOSettings['separator']                    = 'sc-bull';
		$wpSEOSettings['title-home-wpseo']             = '%%page%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$wpSEOSettings['title-author-wpseo']           = '%%name%%, Author at %%sitename%% %%page%%';
		$wpSEOSettings['title-archive-wpseo']          = 'Archive %%sep%% %%sitename%% %%sep%% %%date%% ';
		$wpSEOSettings['title-search-wpseo']           = 'You searched for %%searchphrase%% %%sep%% %%sitename%%';
		$wpSEOSettings['title-404-wpseo']              = 'Page Not Found %%sep%% %%sitename%%';
		$wpSEOTitle = ' %%page%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$getCPT = get_post_types();

		foreach ($getCPT as $postType) {
			if (in_array($postType, ["post","page","universal","products","landing","events"], true)) {
				$wpSEOSettings['title-' . $postType]        = '%%title%%' . $wpSEOTitle;
				$wpSEOSettings['social-title-' . $postType] = '%%title%%' . $wpSEOTitle;
			} elseif (in_array($postType, ["attachment","revision","nav_menu_item","custom_css","customize_changeset","oembed_cache","user_request","wp_block","elements","acf-field-group","acf-field","wpcf7_contact_form"], true)) {
				// skip
			} else {
				$wpSEOSettings['title-' . $postType]        = ucfirst($postType) . $wpSEOTitle;
				$wpSEOSettings['social-title-' . $postType] = ucfirst($postType) . $wpSEOTitle;
			}
		}

		$wpSEOSettings['social-title-author-wpseo']          = '%%name%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$wpSEOSettings['social-title-archive-wpseo']         = '%%date%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
		$wpSEOSettings['noindex-author-wpseo']               = '1';
		$wpSEOSettings['noindex-author-noposts-wpseo']       = '1';
		$wpSEOSettings['noindex-archive-wpseo']              = '1';
		$wpSEOSettings['disable-author']                     = '1';
		$wpSEOSettings['disable-date']                       = '1';
		$wpSEOSettings['disable-attachment']                 = '1';
		$wpSEOSettings['breadcrumbs-404crumb']               = 'Error 404: Page not found';
		$wpSEOSettings['breadcrumbs-boldlast']               = '1';
		$wpSEOSettings['breadcrumbs-archiveprefix']          = 'Archives for';
		$wpSEOSettings['breadcrumbs-enable']                 = '1';
		$wpSEOSettings['breadcrumbs-home']                   = 'Home';
		$wpSEOSettings['breadcrumbs-searchprefix']           = 'You searched for';
		$wpSEOSettings['breadcrumbs-sep']                    = '»';
		$wpSEOSettings['noindex-ptarchive-optimized']        = '1';
		$wpSEOSettings['noindex-testimonials']               = '1';
		$wpSEOSettings['noindex-ptarchive-testimonials']     = '1';
		$wpSEOSettings['display-metabox-pt-testimonials']    = '0';
		$wpSEOSettings['noindex-elements']                   = '1';
		$wpSEOSettings['display-metabox-pt-elements']        = '0';
		$wpSEOSettings['noindex-products']                   = '1';
		$wpSEOSettings['noindex-ptarchive-products']         = '1';
		$wpSEOSettings['display-metabox-pt-products']        = '0';
		$wpSEOSettings['noindex-universal']                  = '1';
		$wpSEOSettings['display-metabox-pt-universal']       = '0';
		$wpSEOSettings['noindex-tax-gallery-type']           = '1';
		$wpSEOSettings['display-metabox-tax-gallery-type']   = '0';
		$wpSEOSettings['noindex-tax-gallery-tags']           = '1';
		$wpSEOSettings['display-metabox-tax-gallery-tags']   = '0';
		$wpSEOSettings['noindex-ptarchive-galleries']        = '1';
		$wpSEOSettings['noindex-tax-image-categories']       = '1';
		$wpSEOSettings['display-metabox-tax-image-categories'] = '0';
		$wpSEOSettings['noindex-tax-image-tags']             = '1';
		$wpSEOSettings['display-metabox-tax-image-tags']     = '0';

		$uploadDir     = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/';
		$possibleFiles = ['logo.webp','logo.png','logo.jpg','site-icon.webp','site-icon.png','site-icon.jpg','favicon.webp','favicon.png','favicon.jpg'];
		$logoFile      = null;

		foreach ($possibleFiles as $file) {
			if (is_file($uploadDir . $file)) { $logoFile = $file; break; }
		}

		if ($logoFile !== null) {
			$wpSEOSettings['company_logo'] = $logoFile;
			$id = attachment_url_to_postid($logoFile);
			if ($id) {
				$wpSEOSettings['company_logo_id']           = $id;
				$wpSEOSettings['company_logo_meta']['url']  = $logoFile;
				$wpSEOSettings['company_logo_meta']['path'] = get_attached_file($id);
				$wpSEOSettings['company_logo_meta']['id']   = $id;
			}
		}

		$wpSEOSettings['company_name']       = get_bloginfo('name');
		$wpSEOSettings['company_or_person']  = 'company';
		$wpSEOSettings['stripcategorybase']  = '1';
		$wpSEOSettings['breadcrumbs-enable'] = '1';
		update_option('wpseo_titles', $wpSEOSettings);

		$wpSEOSocial = get_option('wpseo_social');
		if (isset($customer_info['facebook']))  $wpSEOSocial['facebook_site']  = $customer_info['facebook'];
		if (isset($customer_info['instagram'])) $wpSEOSocial['instagram_url']  = $customer_info['instagram'];
		if (isset($customer_info['linkedin']))  $wpSEOSocial['linkedin_url']   = $customer_info['linkedin'];
		$wpSEOSocial['og_default_image']    = $wpSEOSettings['company_logo'];
		$wpSEOSocial['og_default_image_id'] = $wpSEOSettings['company_logo_id'];
		$wpSEOSocial['opengraph']           = '1';
		if (isset($customer_info['pinterest'])) $wpSEOSocial['pinterest_url']  = $customer_info['pinterest'];
		if (isset($customer_info['twitter']))   $wpSEOSocial['twitter_site']   = $customer_info['twitter'];
		if (isset($customer_info['youtube']))   $wpSEOSocial['youtube_url']    = $customer_info['youtube'];
		update_option('wpseo_social', $wpSEOSocial);
	}

/*--------------------------------------------------------------
# Jobsite GEO — CompanyCam Sync
--------------------------------------------------------------*/

	$jobsite = get_site_option('jobsite_geo');
	if ($jobsite && ($jobsite['fsm_brand'] ?? '') == 'Company Cam' && function_exists('bp_run_companycam_sync')) {
		bp_run_companycam_sync();
	}

/*--------------------------------------------------------------
# Basic WordPress Settings
--------------------------------------------------------------*/

	$update_menu_order = ['site-header'=>100,'widgets'=>200,'office-hours'=>700,'hours'=>700,'coupon'=>700,'site-message'=>800,'site-footer'=>900];

	foreach ($update_menu_order as $page => $order) {
		$updatePage = get_page_by_path($page, OBJECT, 'elements');
		if (!empty($updatePage)) {
			wp_update_post(['ID' => $updatePage->ID, 'menu_order' => $order]);
		}
	}

	update_option('blogname', $customer_info['name']);
	$blogDesc = '';
	if ($customer_info['city'] != '')       $blogDesc .= $customer_info['city'];
	if ($customer_info['city'] != '' && $customer_info['state-abbr'] != '') $blogDesc .= ', ';
	if ($customer_info['state-abbr'] != '') $blogDesc .= $customer_info['state-abbr'];
	update_option('blogdescription', $blogDesc);
	update_option('admin_email', 'info@battleplanwebdesign.com');
	update_option('admin_email_lifespan', '9999999999999');
	update_option('default_comment_status', 'closed');
	update_option('default_ping_status', 'closed');
	update_option('permalink_structure', '/%postname%/');
	update_option('wpe-rand-enabled', '1');
	update_option('users_can_register', '0');
	update_option('auto_update_core_dev', 'enabled');
	update_option('auto_update_core_minor', 'enabled');
	update_option('auto_update_core_major', 'enabled');

	battleplan_delete_prefixed_options('ac_cache_data_');
	battleplan_delete_prefixed_options('ac_cache_expires_');
	battleplan_delete_prefixed_options('ac_api_request_');
	battleplan_delete_prefixed_options('ac_sorting_');
	battleplan_delete_prefixed_options('client_');

	battleplan_fetch_background_image(true);
	battleplan_fetch_site_icon(true);
	battleplan_fetch_site_logo(true);

/*--------------------------------------------------------------
# Taxonomy Cleanup — Remove empty terms
--------------------------------------------------------------*/

	$attachment_taxonomies = ['image-tags'];

	foreach (get_taxonomies(['public' => true], 'names') as $taxonomy) {
		if (in_array($taxonomy, $attachment_taxonomies, true)) continue;

		foreach (get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]) as $term) {
			if ($term->count === 0 || $term->slug === 'service-area---') {
				wp_delete_term($term->term_id, $taxonomy);
			}
		}
	}

	foreach ($attachment_taxonomies as $taxonomy) {
		$terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'all']);
		if (is_wp_error($terms) || empty($terms)) continue;

		foreach ($terms as $term) {
			$term_id = (int)$term->term_id;
			$slug    = (string)$term->slug;

			$query = new WP_Query([
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'tax_query'      => [['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id]],
			]);

			if ($query->found_posts === 0 || $slug === 'service-area---') {
				wp_delete_term($term_id, $taxonomy);
			}

			wp_reset_postdata();
		}
	}

/*--------------------------------------------------------------
# Prune Weak Testimonials
--------------------------------------------------------------*/

	$query = bp_WP_Query('testimonials', [
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	]);

	$draft = null;

	if ($query->found_posts > 75) {
		while ($query->have_posts()) {
			$query->the_post();

			$quality = get_field('testimonial_quality');
			$quality = is_array($quality) ? $quality : [];
			$q       = (int)($quality[0] ?? 0);

			$id = get_the_id();
			if ($id && !isset($draft) && !has_post_thumbnail() && $q !== 1 && strlen(wp_strip_all_tags(get_the_content(), true)) < 100) {
				$draft = $id;
			}

			if (has_post_thumbnail() && $q !== 1) {
				$quality[0] = 1;
				update_field('testimonial_quality', $quality);
			}
		}

		wp_reset_postdata();
		if ($draft) wp_update_post(['ID' => $draft, 'post_status' => 'draft']);
	}

/*--------------------------------------------------------------
# Universal Pages
--------------------------------------------------------------*/

	$hvacBrands = [
		'american standard' => ['slug' => 'customer-care-dealer',        'title' => 'Customer Care Dealer',        'shortcode' => 'page-hvac-customer-care-dealer'],
		'rheem'             => ['slug' => 'rheem-pro-partner',           'title' => 'Rheem Pro Partner',           'shortcode' => 'page-hvac-rheem-pro-partner'],
		'ruud'              => ['slug' => 'ruud-pro-partner',            'title' => 'Ruud Pro Partner',            'shortcode' => 'page-hvac-ruud-pro-partner'],
		'comfortmaker'      => ['slug' => 'comfortmaker-elite-dealer',   'title' => 'Comfortmaker Elite Dealer',   'shortcode' => 'page-hvac-comfortmaker-elite-dealer'],
		'york'              => ['slug' => 'york-certified-comfort-expert','title'=> 'York Certified Comfort Expert','shortcode'=> 'page-hvac-york-cert-comfort-expert'],
		'tempstar'          => ['slug' => 'tempstar-elite-dealer',       'title' => 'Tempstar Elite Dealer',       'shortcode' => 'page-hvac-tempstar-elite-dealer'],
	];

	$isHvac     = !empty($customer_info['site-type']) && strtolower(trim($customer_info['site-type'])) === 'hvac';
	$siteBrand  = $customer_info['site-brand'] ?? '';
	$siteBrands = is_array($siteBrand) ? array_map(fn($b) => strtolower(trim($b)), $siteBrand) : [strtolower(trim((string)$siteBrand))];

	foreach ($hvacBrands as $brand => $page) {
		$hasBrand = $isHvac && in_array($brand, $siteBrands, true);
		if ($hasBrand) {
			if (is_null(get_page_by_path($page['slug'], OBJECT, 'universal'))) {
				wp_insert_post(['post_title' => $page['title'], 'post_content' => '[get-universal-page slug="' . $page['shortcode'] . '"]', 'post_status' => 'publish', 'post_type' => 'universal']);
			}
		} else {
			$getPage = get_page_by_path($page['slug'], OBJECT, 'universal');
			if ($getPage) wp_delete_post($getPage->ID, true);
		}
	}

	$hvacOnlyPages = [
		['slug' => 'maintenance-tips', 'title' => 'Maintenance Tips', 'shortcode' => 'page-hvac-maintenance-tips'],
		['slug' => 'symptom-checker',  'title' => 'Symptom Checker',  'shortcode' => 'page-hvac-symptom-checker'],
		['slug' => 'faq',              'title' => 'FAQ',               'shortcode' => 'page-hvac-faq'],
	];

	foreach ($hvacOnlyPages as $page) {
		if ($isHvac) {
			if (is_null(get_page_by_path($page['slug'], OBJECT, 'universal'))) {
				wp_insert_post(['post_title' => $page['title'], 'post_content' => '[get-universal-page slug="' . $page['shortcode'] . '"]', 'post_status' => 'publish', 'post_type' => 'universal']);
			}
		} else {
			$getPage = get_page_by_path($page['slug'], OBJECT, 'universal');
			if ($getPage) wp_delete_post($getPage->ID, true);
		}
	}

	$isProfile = !empty($customer_info['site-type']) && strtolower(trim($customer_info['site-type'])) === 'profile';
	$profilePages = [
		['slug' => 'profile',           'title' => 'Profile',           'shortcode' => 'page-profile'],
		['slug' => 'profile-directory', 'title' => 'Profile Directory', 'shortcode' => 'page-profile-directory'],
	];

	foreach ($profilePages as $page) {
		if ($isProfile) {
			if (is_null(get_page_by_path($page['slug'], OBJECT, 'universal'))) {
				wp_insert_post(['post_title' => $page['title'], 'post_content' => '[get-universal-page slug="' . $page['shortcode'] . '"]', 'post_status' => 'publish', 'post_type' => 'universal']);
			}
		} else {
			$getPage = get_page_by_path($page['slug'], OBJECT, 'universal');
			if ($getPage) wp_delete_post($getPage->ID, true);
		}
	}

	if (!empty($customer_info['service-areas']) && is_array($customer_info['service-areas'])) {
		if (is_null(get_page_by_path('areas-we-serve', OBJECT, 'universal'))) {
			wp_insert_post(['post_title' => 'Areas We Serve', 'post_content' => '[get-service-areas]', 'post_status' => 'publish', 'post_type' => 'universal']);
		}
	} else {
		$getPage = get_page_by_path('areas-we-serve', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	if (is_null(get_page_by_path('debug', OBJECT, 'universal'))) {
		wp_insert_post(['post_title' => 'BP Debug Log', 'post_name' => 'debug', 'post_content' => '[show_debug_log]', 'post_status' => 'publish', 'post_type' => 'universal']);
	}

	if (is_null(get_page_by_path('privacy-policy',      OBJECT, 'universal'))) wp_insert_post(['post_title' => 'Privacy Policy',      'post_content' => '[get-universal-page slug="page-privacy-policy"]',      'post_status' => 'publish', 'post_type' => 'universal']);
	if (is_null(get_page_by_path('accessibility-policy', OBJECT, 'universal'))) wp_insert_post(['post_title' => 'Accessibility Policy', 'post_content' => '[get-universal-page slug="page-accessibility-policy"]', 'post_status' => 'publish', 'post_type' => 'universal']);
	if (is_null(get_page_by_path('terms-conditions',    OBJECT, 'universal'))) wp_insert_post(['post_title' => 'Terms & Conditions',   'post_content' => '[get-universal-page slug="page-terms-conditions"]',    'post_status' => 'publish', 'post_type' => 'universal']);
	if (is_null(get_page_by_path('review',              OBJECT, 'universal'))) wp_insert_post(['post_title' => 'Review',               'post_content' => '[get-universal-page slug="page-review"]',               'post_status' => 'publish', 'post_type' => 'universal']);
	if (is_null(get_page_by_path('email-received',      OBJECT, 'universal'))) wp_insert_post(['post_title' => 'Email Received',       'post_content' => '[get-universal-page slug="page-email-received"]',       'post_status' => 'publish', 'post_type' => 'universal']);


}


/*--------------------------------------------------------------
# Helpers (used by housekeeping)
--------------------------------------------------------------*/

function bp_check_for_post_updates(): void {
	$excluded_user = get_user_by('login', 'battleplanweb');
	$excluded_id   = $excluded_user ? $excluded_user->ID : 0;

	// Use bp_chron_b_time so this accurately reflects housekeeping's last run
	$lastRun = (int) get_option('bp_chron_b_time', 0);

	$args = [
		'post_type'      => get_post_types(['public' => true], 'names'),
		'post_status'    => ['publish','future','draft','pending','private'],
		'posts_per_page' => -1,
		'date_query'     => [['column' => 'post_modified_gmt', 'after' => gmdate('Y-m-d H:i:s', $lastRun)]],
	];

	$posts = get_posts($args);
	if (!$posts) return;

	$other = '';
	$mine  = '';

	foreach ($posts as $p) {
		$url = get_permalink($p->ID);
		if (!$url) continue;
		$author_id = (int)$p->post_author;
		$author    = get_the_author_meta('display_name', $author_id);
		$item      = '<li><a href="' . $url . '">' . $p->post_title . '</a> <em>(by ' . $author . ')</em></li>';
		$is_other  = $excluded_id ? $author_id !== $excluded_id : true;
		$is_other ? $other .= $item : $mine .= $item;
	}

	$body = '';
	if ($other) $body .= '<h3>Updated by Other Users</h3><ul>' . $other . '</ul>';
	if ($mine)  $body .= '<h3>Updated by battleplanweb</h3><ul>' . $mine . '</ul>';
	if ($body)  emailMe('Content Updates Detected · ' . get_bloginfo('name'), $body);
}
