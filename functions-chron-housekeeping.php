<?php
/* Battle Plan Web Design Functions: Chron B — Housekeeping */

function bp_run_chron_housekeeping(bool $force = false): void {

/*--------------------------------------------------------------
# Form attachment temp folder — sweep orphans
#
# bp_handle_form_submission registers a shutdown function to delete
# uploaded attachments after wp_mail. If a request fatals before that
# fires, files are left behind. Sweep anything older than 24h.
--------------------------------------------------------------*/

	$tmpdir = trailingslashit(wp_upload_dir()['basedir'] ?? '') . 'bp-form-tmp';
	if (is_dir($tmpdir)) {
		$cutoff = time() - DAY_IN_SECONDS;
		foreach ((array) glob($tmpdir . '/*') as $file) {
			if (is_file($file) && filemtime($file) < $cutoff) @unlink($file);
		}
	}

/*--------------------------------------------------------------
# CF7 Legacy Shortcode Sweep
#
# Rewrite any leftover [contact-form-7 …] tags in the DB to the bp form that
# replaced them, so old content stops printing the raw shortcode as text.
# Near-no-op once a site is clean (gated by a LIKE inside the helper).
--------------------------------------------------------------*/

	if (function_exists('bp_cf7_sweep_content')) bp_cf7_sweep_content();

	bp_typeface_refresh();

	if (function_exists('battleplan_remove_user_roles')) battleplan_remove_user_roles();
	if (function_exists('battleplan_create_user_roles')) battleplan_create_user_roles();
	if (function_exists('battleplan_updateSiteOptions')) battleplan_updateSiteOptions();
	bp_check_for_post_updates();

	$customer_info = customer_info();

	// Bare host only — no scheme, no www., lowercased. Everything below is built from
	// this: the from_email, the Brevo sending domain, and both site-list comparisons.
	// (It used to be a plain str_replace of 'https://', so a site whose WP Address
	// carried a www. never matched its own list entry AND got a from_email of
	// email@admin.www.example.com.)
	$site          = strtolower( (string) ( wp_parse_url( get_bloginfo('url'), PHP_URL_HOST ) ?: '' ) );
	$site          = preg_replace('#^www\.#', '', $site);

	// Rovin sends on their OWN Brevo account, not ours. A site counts as Rovin if it is
	// on the domain list or if it simply defines BP_BREVO_ROVIN_KEY — the constant is the
	// authoritative signal, so a Rovin install we forget to list (or that moves domain)
	// still routes correctly the moment its wp-config.php has the key.
	$rovin         = defined('BP_BREVO_ROVIN_KEY') || in_array($site, ["babeschicken.com","babescatering.com","babeschicken.tv","sweetiepiesribeyes.com","bubbascookscountry.com","rovindirectory.com","rovininc.com","rovin.work"], true);
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

			// This write is unconditional every night, so it overwrites anything set by hand
			// in the WP Mail SMTP UI. A Rovin site with no BP_BREVO_ROVIN_KEY therefore falls
			// back to OUR key — we keep the fallback (their mail must never fail) but alert,
			// because otherwise the fallback is completely silent: their transactional mail
			// bills to and sends from our Brevo account with nothing to indicate it.
			$bp_brevo_fallback = false;
			$bp_brevo_key      = _BREVO_API;
			if ($rovin === true) {
				if (defined('BP_BREVO_ROVIN_KEY') && BP_BREVO_ROVIN_KEY !== '') {
					$bp_brevo_key = BP_BREVO_ROVIN_KEY;
				} else {
					$bp_brevo_fallback = true;
				}
			}
			$wpMailSettings['sendinblue']['api_key']     = 'x' . $bp_brevo_key;
			update_option('wp_mail_smtp', $wpMailSettings);

			// Alert AFTER the write, so the warning itself goes out on a known-good transport.
			if ($bp_brevo_fallback) bp_brevo_key_alert($site, $wpMailSettings, $customer_info);
		}
	}

/*--------------------------------------------------------------
# Jobsite GEO — CompanyCam Sync
--------------------------------------------------------------*/

	$jobsite = get_option('jobsite_geo');
	if ($jobsite && ($jobsite['fsm_brand'] ?? '') == 'Company Cam' && function_exists('bp_run_companycam_sync')) {
		bp_run_companycam_sync();
	}

	// Workiz jobs -> jobsite_geo (same nightly window as Company Cam).
	if ($jobsite && ($jobsite['fsm_brand'] ?? '') == 'Workiz' && function_exists('bp_run_workiz_sync')) {
		bp_run_workiz_sync();
	}

	// Reconciliation: ingested jobs (Housecall Pro / Company Cam) publish without an
	// AI rewrite, so they have no service-type/service term and never become a
	// /service/ page. Seed any published jobsite_geo posts still missing a rewrite —
	// bounded per run, and handed to the deferred bp_geo_ai_rewrite_cron event
	// (staggered) so we never run a pile of API calls inside this pass. This also
	// drains the pre-existing backlog and recovers any one-off API failures.
	if ( bp_module_on($jobsite)
		&& defined('BP_GEO_CPT') && defined('BP_GEO_FIELD_AI_RAN')
		&& function_exists('bp_geo_run_ai_rewrite')) {
		$bp_geo_pending = get_posts([
			'post_type'      => BP_GEO_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_query'     => [[ 'key' => BP_GEO_FIELD_AI_RAN, 'compare' => 'NOT EXISTS' ]],
		]);
		$bp_geo_stagger = 0;
		foreach ($bp_geo_pending as $bp_geo_pid) {
			if (!wp_next_scheduled('bp_geo_ai_rewrite_cron', [$bp_geo_pid])) {
				wp_schedule_single_event(time() + 60 + ($bp_geo_stagger++ * 90), 'bp_geo_ai_rewrite_cron', [$bp_geo_pid]);
			}
		}
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
	update_option('admin_email', 'info@bp-webdev.com');
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
			if ($id && !has_post_thumbnail() && $q !== 1 && strlen(wp_strip_all_tags(get_the_content(), true)) < 100) {
				// If linked to a jobsite, mark as quality instead of drafting
				$linked_jobsite = get_posts([
					'post_type'      => 'jobsite_geo',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => [[
						'key'   => 'review',
						'value' => $id,
					]],
				]);

				if (!empty($linked_jobsite)) {
					$quality[0] = 1;
					update_field('testimonial_quality', $quality);
				} elseif (!isset($draft)) {
					$draft = $id;
				}
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

	// Requires jobsite_geo as well as service-areas: the page's whole job is to give crawlers
	// a path to the /service/{service--city-st}/ pages. Without the module those pages don't
	// exist, so the page rendered blank and the footer linked to it from every page. Sites in
	// that state get it cleaned up by the else branch below.
	if (!empty($customer_info['service-areas']) && is_array($customer_info['service-areas']) && bp_module_on(get_option('jobsite_geo'))) {
		if (is_null(get_page_by_path('areas-we-serve', OBJECT, 'universal'))) {
			wp_insert_post(['post_title' => 'Areas We Serve', 'post_content' => '[get-service-areas]', 'post_status' => 'publish', 'post_type' => 'universal']);
		}
	} else {
		// Unpublish rather than force-delete. The old wp_delete_post($id, true) skipped the
		// trash entirely — fine when the page is our auto-generated [get-service-areas] stub,
		// but this branch now also fires on jobsite_geo-off sites, and force-deleting would
		// destroy any hand-written content someone put on that page with no way back. Drafting
		// takes it out of the footer + sitemap (bp_footer_areas is gated too) and is reversible.
		// Only touch a currently-published page so a manually re-published one isn't re-drafted
		// on every run beyond the first — and if the auto-stub was already trashed, leave it.
		$getPage = get_page_by_path('areas-we-serve', OBJECT, 'universal');
		if ($getPage && $getPage->post_status === 'publish') {
			wp_update_post(['ID' => $getPage->ID, 'post_status' => 'draft']);
		}
	}

	$eventCalendar = get_option('event_calendar');
	if ( bp_module_on($eventCalendar) ) {
		if (is_null(get_page_by_path('calendar', OBJECT, 'universal'))) {
			wp_insert_post(['post_title' => 'Calendar', 'post_content' => '[get-event-calendar]', 'post_status' => 'publish', 'post_type' => 'universal']);
		}
		// Roll recurring events' generated dates forward (rolling horizon) + prune past.
		if (function_exists('bp_event_topup_recurrences')) bp_event_topup_recurrences();
	} else {
		$getPage = get_page_by_path('calendar', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	$sitePulse = get_option('site_pulse');
	if ( bp_module_on($sitePulse) ) {
		$sitePulsePages = [
			['slug' => 'site-pulse-login',     'title' => 'Site Pulse Login',     'shortcode' => 'page-site-pulse-login'],
			['slug' => 'site-pulse-dashboard', 'title' => 'Site Pulse Dashboard', 'shortcode' => 'page-site-pulse-dashboard'],
		];
		foreach ($sitePulsePages as $page) {
			if (is_null(get_page_by_path($page['slug'], OBJECT, 'universal'))) {
				wp_insert_post(['post_title' => $page['title'], 'post_name' => $page['slug'], 'post_content' => '[get-universal-page slug="' . $page['shortcode'] . '"]', 'post_status' => 'publish', 'post_type' => 'universal']);
			}
		}
	} else {
		foreach (['site-pulse-login', 'site-pulse-dashboard'] as $slug) {
			$getPage = get_page_by_path($slug, OBJECT, 'universal');
			if ($getPage) wp_delete_post($getPage->ID, true);
		}
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


/*--------------------------------------------------------------
# Brevo key fallback alert
#
# Fires when a site is identified as belonging to a client with its own Brevo
# account (currently Rovin) but that account's key is missing from wp-config.php.
# The nightly cron has just written OUR key into WP Mail SMTP so their mail keeps
# flowing — this says so, loudly, with everything needed to fix it.
#
# Throttled to one email per site per week (transients are per-install), so a site
# that stays unfixed nags weekly instead of nightly.
--------------------------------------------------------------*/

function bp_brevo_key_alert(string $site, array $wpMailSettings, array $customer_info): void {

	if (get_transient('bp_brevo_key_alert')) return;
	set_transient('bp_brevo_key_alert', 1, WEEK_IN_SECONDS);

	$biz        = $customer_info['name'] ?? get_bloginfo('name');
	$from       = $wpMailSettings['mail']['from_email'] ?? '(unset)';
	$from_name  = $wpMailSettings['mail']['from_name'] ?? '(unset)';
	$brevo_dom  = $wpMailSettings['sendinblue']['domain'] ?? '(unset)';
	$listed     = in_array($site, ["babeschicken.com","babescatering.com","babeschicken.tv","sweetiepiesribeyes.com","bubbascookscountry.com","rovindirectory.com","rovininc.com","rovin.work"], true);
	$key_tail   = _BREVO_API !== '' ? '…' . substr(_BREVO_API, -6) : '(BP_BREVO_KEY is not defined either!)';

	// WP Engine install name — the folder to SSH/SFTP into to edit wp-config.php.
	$install    = defined('PWP_NAME') ? PWP_NAME : '(unknown — not a WP Engine install?)';

	$body  = '<p><strong>' . esc_html($biz) . '</strong> is flagged as a client with its own Brevo account, '
	       . 'but <code>BP_BREVO_ROVIN_KEY</code> is not defined in its <code>wp-config.php</code>.</p>'
	       . '<p>Tonight\'s housekeeping cron wrote <strong>YOUR</strong> Brevo key into WP Mail SMTP so their email keeps '
	       . 'sending — but every message from this site is going out on your Brevo account: your sending quota, your '
	       . 'reputation, your bill, and their stats missing from their own dashboard.</p>'
	       . '<h3>Site</h3><ul>'
	       . '<li><strong>URL:</strong> ' . esc_html(home_url()) . '</li>'
	       . '<li><strong>Host matched as:</strong> ' . esc_html($site) . '</li>'
	       . '<li><strong>WP Engine install:</strong> ' . esc_html($install) . '</li>'
	       . '<li><strong>Matched by:</strong> ' . ($listed ? 'the domain list in functions-chron-housekeeping.php' : 'nothing — see the warning below') . '</li>'
	       . '</ul>'
	       . '<h3>What it is sending as right now</h3><ul>'
	       . '<li><strong>From:</strong> ' . esc_html($from_name) . ' &lt;' . esc_html($from) . '&gt;</li>'
	       . '<li><strong>Brevo sending domain:</strong> ' . esc_html($brevo_dom) . '</li>'
	       . '<li><strong>Brevo key in use:</strong> yours, ' . esc_html($key_tail) . '</li>'
	       . '</ul>'
	       . '<h3>Fix</h3>'
	       . '<p>Add this to <code>wp-config.php</code> on this install (above the "stop editing" line):</p>'
	       . '<pre>define( \'BP_BREVO_ROVIN_KEY\', \'keysib-xxxxxxxx…\' );</pre>'
	       . '<p><strong>Store it WITHOUT the leading <code>x</code>.</strong> The cron prefixes <code>x</code> itself '
	       . '(<code>\'x\' . BP_BREVO_ROVIN_KEY</code>), so paste the Brevo key as <code>keysib-…</code>, not '
	       . '<code>xkeysib-…</code> — a doubled <code>x</code> authenticates as nothing and their mail stops dead.</p>'
	       . '<p>The next nightly Chron B run picks it up and rewrites WP Mail SMTP. To apply it immediately: '
	       . '<code>wp option update bp_force_chron_b 1</code>, then load a page as <code>battleplanweb</code>. '
	       . 'Confirm under Settings &rarr; WP Mail SMTP that the API key changed.</p>';

	if (!$listed) {
		$body .= '<h3>Also worth fixing</h3>'
		       . '<p>This site was identified only because <code>BP_BREVO_ROVIN_KEY</code> was expected here — its host '
		       . '(<code>' . esc_html($site) . '</code>) is <strong>not</strong> in the domain list in '
		       . '<code>functions-chron-housekeeping.php</code>. Add it there too so the routing does not depend on '
		       . 'the constant alone.</p>';
	}

	emailMe('[Battle Plan] Client Brevo key missing · ' . $biz, $body);
	error_log('bp_brevo_key_alert: BP_BREVO_ROVIN_KEY undefined on ' . $site . ' — fell back to BP_BREVO_KEY');
}
