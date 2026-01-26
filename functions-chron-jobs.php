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

if (
	!is_admin() &&
	isset($_SERVER['REQUEST_URI']) &&
	preg_match('#sitemap(_index)?\.xml|/wp-sitemap\.xml|/feed/#i', $_SERVER['REQUEST_URI'])
) {
	return;
}

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

//delete_option('bp_product_upload_2023_03_06');
//battleplan_delete_prefixed_options( 'widget_' );

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

//delete_option('bp_setup_2023_09_15');

/*
if ( get_option('bp_product_upload_2024_03_18') != "completed" && $customer_info['site-type'] == 'hvac' && ($customer_info['site-brand'] == 'american standard' || $customer_info['site-brand'] == 'American Standard' || (is_array($customer_info['site-brand']) && ( in_array('american standard', $customer_info['site-brand']) || in_array('American Standard', $customer_info['site-brand'])) ))) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-american-standard-products.php';
	updateOption( 'bp_product_upload_2024_03_18', 'completed', false );
endif;
*/

// Fix descrepencies in Jobsite GEO
/*
add_action("init", "bp_add_terms_to_jobsites");
function bp_add_terms_to_jobsites() {

    if ( get_option('jobsite_geo') && get_option('jobsite_geo')['install'] == 'true' && get_option('bp_setup_2024_07_09') != "completed" ) {

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

        $query = bp_WP_Query('jobsite_geo', [
            'posts_per_page' => -1
        ]);


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
}
*/





// Determine if Chron should run
$forceChron = filter_var(get_option('bp_force_chron', false), FILTER_VALIDATE_BOOLEAN);
$next    	= (int) get_option('bp_chron_next', 0);
$lastRun   	= (int) get_option('bp_chron_time', 0);
$staleDays 	= (time() - $lastRun) > (86400 * 3); // more than 3 days idle

if ($next <= 0) {
	$next = time() + rand(40000, 70000);
	update_option('bp_chron_next', $next);
}

$due       	= time() >= $next;

// Perform action
if ( $forceChron || $staleDays || ( _IS_BOT && !_IS_SERP_BOT && $due )) {

	if (get_transient('bp_chron_running')) {
		return;
	}

	set_transient('bp_chron_running', 1, 2 * HOUR_IN_SECONDS);

	delete_option('bp_force_chron');
	update_option('bp_chron_time', time());
	update_option('bp_chron_next', time() + rand(40000, 70000));

	processChron($forceChron);

	delete_transient('bp_chron_running');
}

function processChron($forceChron) {

// 0) Site bootstrapping
	if (function_exists('battleplan_remove_user_roles')) battleplan_remove_user_roles();
	if (function_exists('battleplan_create_user_roles')) battleplan_create_user_roles();
	if (function_exists('battleplan_updateSiteOptions')) battleplan_updateSiteOptions();

	$google_info 	= get_option('bp_gbp_update') ?: [];

	$site           	= str_replace('https://', '', get_bloginfo('url'));
	$rovin          	= in_array($site, ["babeschicken.com", "babescatering.com", "babeschicken.tv", "sweetiepiesribeyes.com", "bubbascookscountry.com", "rovindirectory.com", "rovininc.com"], true);
	$bp_handles_mail	= ($site !== "asairconditioning.com");

// 1) Load CI + normalize PIDs
	$customer_info	= customer_info();
	$pid_sync		= filter_var($customer_info['pid-sync'] ?? false, FILTER_VALIDATE_BOOL);
	$placeIDs  		= ci_normalize_pids($customer_info['pid'] ?? []);

	if (!empty($placeIDs)) {
// 2) Decide whether to hit the API
		$today 			= strtotime(date("F j, Y"));
		$daysSince     	= ($today - (int)($google_info['date'] ?? 0)) / 86400;
		$reviewCount   	= (int)($google_info['google-reviews'] ?? 0);
		$thresholds    	= [1000=>1, 500=>2, 250=>3, 125=>4, 75=>5, 50=>6];
		$days          	= 7;

		foreach ($thresholds as $limit=>$val) { $days = ($reviewCount >= $limit) ? $val : $days; }

		if ( $forceChron === true || $daysSince > $days ) {
// 3) Fetch GBP for each PID
			$google_rating = 0.0;
			$google_review_num = 0;

			foreach ( $placeIDs as $placeID ) {
				if (strlen($placeID) <= 10) { continue; }

				$fields = 'displayName,formattedAddress,addressComponents,location,regularOpeningHours,currentOpeningHours,internationalPhoneNumber,rating,userRatingCount,utcOffsetMinutes';
    			$url = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeID) . '?fields=' . urlencode($fields) . '&key=' . _PLACES_API;

				$ch = curl_init();
				 curl_setopt_array($ch, [
    				CURLOPT_URL            => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_CONNECTTIMEOUT => 5,
					CURLOPT_TIMEOUT        => 12,
					CURLOPT_HTTPHEADER     => ['Accept: application/json'],
				]);
				$result = curl_exec($ch);
				$http   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
				$cerr   = curl_errno($ch);
				$cerrm  = curl_error($ch);
				curl_close($ch);

				if ($cerr && function_exists('emailMe')) {
					emailMe('Chron - Places API cURL Error - '.$customer_info['name'], "PID: $placeID\nError: $cerr $cerrm");
					continue;
				}

				if (($http < 200 || $http >= 300) && function_exists('emailMe')) {
					emailMe('Chron - Places API HTTP Error - '.$customer_info['name'], "PID: $placeID\nHTTP: $http\nBody:\n".$result);
					continue;
				}

				$gbp = json_decode($result, true);

				if (!is_array($gbp) && function_exists('emailMe')) {
					emailMe('Chron - Places API JSON Error - '.$customer_info['name'], "PID: $placeID\nBody:\n".$result);
					continue;
				}

				if (isset($gbp['error']) && function_exists('emailMe')) {
					emailMe('Chron - Places API Error - '.$customer_info['name'], print_r($gbp['error'], true) . "\n\nFull response:\n" . $result);
					continue;
				}

				$google_info[$placeID]['utcOffsetMinutes'] = $gbp['utcOffsetMinutes'] ?? null;

				$urc = isset($gbp['userRatingCount']) ? (int)$gbp['userRatingCount'] : 0;
				$rat = isset($gbp['rating']) ? (float)$gbp['rating'] : 0.0;
				$google_info[$placeID]['google-reviews'] = $urc;
				$google_info[$placeID]['google-rating']  = $rat;
				$google_review_num += $urc;
				$google_rating     += ($rat * $urc);

				$phone = $gbp['internationalPhoneNumber'] ?? '';
				if (preg_match('/^\+1[\s\-\.]?(\d{3})[\s\-\.]?(\d{3})[\s\-\.]?(\d{4})$/', $phone, $m)) {
					$areaDigits  = $m[1];
					$localDigits = $m[2] . '-' . $m[3];
					$google_info[$placeID]['area']  = $areaDigits;
					$google_info[$placeID]['phone'] = $localDigits;
					if (str_contains((string)$customer_info['area-after'], '.')) {
						$google_info[$placeID]['phone'] = str_replace('-', '.', $google_info[$placeID]['phone']);
					}
					$google_info[$placeID]['phone-format'] =
						($customer_info['area-before'] ?? '') . $areaDigits .
						($customer_info['area-after'] ?? '') .
						$google_info[$placeID]['phone'];
				} else {
					$google_info[$placeID]['area']         = '';
					$google_info[$placeID]['phone']        = '';
					$google_info[$placeID]['phone-format'] = '';
				}

				$nm = strtolower($gbp['displayName']['text'] ?? '');
				$nm = str_replace(
					['llc','hvac','a/c','inc','mcm','a-ale','hph','gps plumbing','lecornu','ss&l','ag heat'],
					['LLC','HVAC','A/C','INC','MCM','A-Ale','HPH','GPS Plumbing','LeCornu','SS&L','AG Heat'],
					$nm
				);

				$google_info[$placeID]['name'] = ucwords($nm);

				$google_info[$placeID]['adr_address']        = $gbp['formattedAddress'] ?? '';
				$google_info[$placeID]['address_components'] = $gbp['addressComponents'] ?? [];

				$comp = [
					'street_num'   => '',
					'route'        => '',
					'premise'      => '',
					'subpremise'   => '',
					'floor'        => '',
					'city'         => '',
					'state_abbr'   => '',
					'state_full'   => '',
					'zip'          => '',
					'county'       => '',
					'country_abbr' => '',
					'country_full' => '',
				];

				foreach (($google_info[$placeID]['address_components'] ?? []) as $c) {
					$types = $c['types'] ?? [];
					$long  = $c['longText']  ?? '';
					$short = $c['shortText'] ?? '';

					if (in_array('street_number', $types, true))                 $comp['street_num'] = $long ?: $short;
					if (in_array('route', $types, true))                         $comp['route']      = $short ?: $long; // prefer abbreviated
					if (in_array('premise', $types, true))                       $comp['premise']    = $long ?: $short; // building name
					if (in_array('subpremise', $types, true))                    $comp['subpremise'] = $long ?: $short; // suite/unit
					if (in_array('floor', $types, true))                         $comp['floor']      = $long ?: $short;

					if (in_array('locality', $types, true))                      $comp['city']       = $long ?: $short;
					if (in_array('administrative_area_level_1', $types, true)) { $comp['state_abbr'] = $short; $comp['state_full'] = $long; }
					if (in_array('administrative_area_level_2', $types, true))   $comp['county']     = $long ?: $short;
					if (in_array('country', $types, true)) { $comp['country_full'] = $long ?: $short; $comp['country_abbr'] = $short ?: $long; }
					if (in_array('postal_code', $types, true))                   $comp['zip']        = $long ?: $short;
				}

				// Compose street lines
				$base   = trim($comp['street_num'].' '.$comp['route']);
				$sub    = trim((string)$comp['subpremise']); // suite/unit
				$prem   = trim((string)$comp['premise']);    // building name (optional)
				$floor  = trim((string)$comp['floor']);      // floor (optional)

				// Normalize suite/unit to a consistent shape: "#101", "Suite L #642", "Ste 5", etc.
				$normalizeSubpremise = function(string $s): string {
					$s = preg_replace('/\s+/', ' ', trim($s));
					if ($s === '') return '';

					// Already "#101" (allow whitespace after #)
					if (preg_match('/^#\s*\S+$/', $s)) {
						return '#' . preg_replace('/^#\s*/', '', $s);
					}

					// Bare unit like "101" or "12B" -> "#101"
					if (preg_match('/^[0-9]+[A-Za-z]?$/', $s)) {
						return '#'.$s;
					}

					// Labeled variants -> keep label, normalize casing/punctuation
					if (preg_match('/^(suite|ste|unit|apt|apartment|bldg)\b[\s\-#]*([\w\-# ]+)$/i', $s, $m)) {
						$label = ucfirst(strtolower($m[1])); // Suite/Ste/Unit/Apt/Apartment/Bldg
						$rest  = preg_replace('/\s+/', ' ', trim($m[2]));
						if (preg_match('/^[0-9]+[A-Za-z]?$/', $rest)) $rest = '#'.$rest;
						return $label.' '.$rest;
					}

					// Fallback: return as-is
					return $s;
				};

				$subNorm = $normalizeSubpremise($sub);

				// Build final line1; **no comma before suite/unit**; commas ok for building/floor
				$line1 = $base;
				if ($subNorm !== '') $line1 .= ' '.$subNorm;
				if ($prem !== '')    $line1 .= ', '.$prem;
				if ($floor !== '')   $line1 .= ', '.$floor;

				// Cleanup spacing
				$line1 = preg_replace('/\s+/', ' ', trim($line1));

				// NOTE: per your request, we DO NOT append ZIP+4 to zip
				$zip = $comp['zip'];

				// Save back to google_info
				$google_info[$placeID]['street']        = $line1;
				$google_info[$placeID]['street_line1']  = $base;                 // optional
				$google_info[$placeID]['street_line2']  = trim(($prem ? $prem : '').($floor ? ($prem ? ', ' : '').$floor : '')); // optional
				$google_info[$placeID]['suite']         = $subNorm;              // optional discrete
				$google_info[$placeID]['city']          = $comp['city'];
				$google_info[$placeID]['state-abbr']    = $comp['state_abbr'];
				$google_info[$placeID]['state-full']    = $comp['state_full'];
				$google_info[$placeID]['zip']           = $zip;                  // no +4 appended
				$google_info[$placeID]['county']        = $comp['county'];
				$google_info[$placeID]['country']       = $comp['country_abbr'] ?: $comp['country_full'];


				$google_info[$placeID]['lat']  			= isset($gbp['location']['latitude'])  ? (float)$gbp['location']['latitude']  : null;
				$google_info[$placeID]['long'] 			= isset($gbp['location']['longitude']) ? (float)$gbp['location']['longitude'] : null;

				$google_info[$placeID]['hours'] 		= $gbp['regularOpeningHours'] ?? null;
    			$google_info[$placeID]['current-hours'] = $gbp['currentOpeningHours'] ?? null;
			}

			$google_info['google-reviews'] = $google_review_num;
			if ($google_review_num > 0) {
				$google_info['google-rating'] = $google_rating / $google_review_num;
			}

			$google_info['date'] = $today;

// 4) Update bp_bgp_update and notify of differences
			update_option('bp_gbp_update', $google_info, false);
			gbp_diff_vs_ci_and_notify($customer_info, $google_info, $placeIDs);
		}
	}

// 5) Merge GBP into CI (only if pid-sync true; also fill missing fields with GBP)
	$primePID 					= $placeIDs[0] ?? null;
	$gbp_primary 				= $primePID ? ($google_info[$primePID] ?? []) : [];
	list($ci_new, $merge_diffs) = ci_merge_gbp_into_ci($customer_info, $gbp_primary, $pid_sync);

// 6) Finalize derived fields (phone-format, default-loc) and prune trivial empties
	ci_finalize_fields($ci_new);

// 7) Build schema from FINAL CI
	$schema = ci_build_schema($ci_new, $gbp_primary, $google_info, [
		'include_aggregate_rating' => true,
		'min_rating_value'         => 4.0,
	]);
	$ci_new['schema'] = $schema;

// 8) Save CI if changed
	if ($ci_new !== $customer_info) {
		update_customer_info($ci_new);
	}

// 9) WP Mail SMTP Settings Update
	if ( $bp_handles_mail === true) {
		if ( is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') ) {
			if ( $rovin === true ) {
				$wpMailSettings['mail']['from_email'] = 'customer@website.'.$site;
				$wpMailSettings['sendinblue']['domain'] = 'website.'.$site;
			} else {
				$wpMailSettings['mail']['from_email'] = 'email@admin.'.$site;
				$wpMailSettings['sendinblue']['domain'] = 'admin.'.$site;
			}

			$wpMailSettings = get_option( 'wp_mail_smtp' );
			$wpMailSettings['mail']['from_name'] = strip_tags('Website · '.str_replace(',', '', $customer_info['name']));
			$wpMailSettings['mail']['mailer'] = 'sendinblue';
			$wpMailSettings['mail']['from_email_force'] = '1';
			$wpMailSettings['mail']['from_name_force'] = '1';
			$wpMailSettings['sendinblue']['api_key'] = 'x'._BREVO_API;
			update_option( 'wp_mail_smtp', $wpMailSettings );
		}
	}

// 10) Contact Form 7 Settings Update
	if ( is_plugin_active('contact-form-7/wp-contact-form-7.php') ) {
		$forms = get_posts( array ( 'numberposts'=>-1, 'post_type'=>'wpcf7_contact_form' ));
		foreach ( $forms as $form ) {
			$formID = $form->ID;
			$formMail = readMeta( $formID, "_mail" );
			$formTitle = get_the_title($formID);

			if ( $formTitle == "Quote Request Form" ) $formTitle = "Quote Request";
			if ( $formTitle == "Contact Us Form" ) $formTitle = "Customer Contact";
			if ( $formTitle == "Request A Catering Quote" ) $formTitle = "Catering Quote";

			if ( $rovin !== true && $bp_handles_mail === true ) {
				$server_email = "<email@admin.".str_replace('https://', '', get_bloginfo('url')).">";
				$formMail['subject'] = $formTitle." · [user-name]";
				$formMail['sender'] = "Website · ".str_replace(',', '', $customer_info['name'])." ".$server_email;
				$formMail['additional_headers'] = "Reply-to: [user-name] <[user-email]>\nBcc: Website Administrator <email@battleplanwebdesign.com>";
			}

			$formMail['use_html'] = 1;
			$formMail['exclude_blank'] = 1;

			updateMeta( $formID, "_mail", $formMail );
		}
	}

// 11) Yoast SEO Settings Update
	if ( is_plugin_active('wordpress-seo-premium/wp-seo-premium.php') ) {
		$customer_info = customer_info();
		$cur = get_option('wpseo_local') ?: [];

		$mapType = function(array $customer_info): string {
			$bt = $customer_info['business-type'] ?? '';
			$type = is_array($bt) ? ($bt[0] ?? '') : $bt;
			if (!empty($customer_info['site-type']) && strtolower((string)$customer_info['site-type']) === 'hvac') return 'HVACBusiness';
			$type = trim($type) === '' ? 'LocalBusiness' : preg_replace('/\s+/', '', $type);
			return $type;
		};

		$desired = [
			'business_type'          => $mapType($customer_info),
			'location_address'       => $customer_info['street'] ?? '',
			'location_city'          => $customer_info['city'] ?? '',
			'location_state'         => $customer_info['state-full'] ?? ($customer_info['state-abbr'] ?? ''),
			'location_zipcode'       => $customer_info['zip'] ?? '',
			'location_country'       => 'US',
			'location_phone'         => (isset($customer_info['area'],$customer_info['phone']) ? $customer_info['area'].'-'.$customer_info['phone'] : ''),
			'location_email'         => $customer_info['email'] ?? '',
			'location_url'           => get_bloginfo('url'),
			'location_coords_lat'    => $customer_info['lat']  ?? '',
			'location_coords_long'   => $customer_info['long'] ?? '',
		];

		// Hours (single slot per day for Yoast’s UI)
		$hours = ci_build_hours($customer_info['hours']['periods'] ?? null, $customer_info['current-hours'] ?? null);
		$order = [1,2,3,4,5,6,0]; // Mon..Sun mapped to Google 0..6
		$daysYoast = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
		$byDay = array_fill(0, 7, []);
		// Reconstruct $byDay from the same logic used in ci_build_hours
		// Easiest: parse the OpeningHoursSpecification you already returned:
		if (!empty($hours['openingHoursSpecification'])) {
			$mapName = ['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6,'Sunday'=>0];
			foreach ($hours['openingHoursSpecification'] as $spec) {
				$opens  = $spec['opens']  ?? null;
				$closes = $spec['closes'] ?? null;
				foreach ((array)($spec['dayOfWeek'] ?? []) as $dname) {
					$g = $mapName[$dname] ?? null;
					if ($g === null || !$opens || !$closes) continue;
					$byDay[$g][] = [$opens,$closes];
				}
			}
		}
		foreach ($order as $i=>$g) {
			[$from,$to] = !empty($byDay[$g]) ? $byDay[$g][0] : ['Closed','Closed'];
			$desired["opening_hours_{$daysYoast[$i]}_from"] = $from;
			$desired["opening_hours_{$daysYoast[$i]}_to"]   = $to;
			$desired["opening_hours_{$daysYoast[$i]}_second_from"] = '';
			$desired["opening_hours_{$daysYoast[$i]}_second_to"]   = '';
		}
		$desired['hide_opening_hours'] = empty($byDay[0]) && empty($byDay[1]) && empty($byDay[2]) && empty($byDay[3]) && empty($byDay[4]) && empty($byDay[5]) && empty($byDay[6]) ? 'on' : 'off';

		// Write only if changed
		$delta = array_diff_assoc($desired, $cur);
		if (!empty($delta)) {
			update_option('wpseo_local', array_replace($cur, $desired));
		}

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
		$wpSEOBase['clean_permalinks_extra_variables'] = 'loc,int,invite,rs,se_action,pmax';
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
		foreach ($getCPT as $postType) {
			if ( $postType == "post" || $postType == "page" || $postType == "universal" || $postType == "products" || $postType == "landing" || $postType == "events" ) {
				$wpSEOSettings['title-'.$postType] = '%%title%%'.$wpSEOTitle;
				$wpSEOSettings['social-title-'.$postType] = '%%title%%'.$wpSEOTitle;
			} elseif ( $postType == "attachment" || $postType == "revision" || $postType == "nav_menu_item" || $postType == "custom_css" || $postType == "customize_changeset" || $postType == "oembed_cache" || $postType == "user_request" || $postType == "wp_block" || $postType == "elements" || $postType == "acf-field-group" || $postType == "acf-field" || $postType == "wpcf7_contact_form" ) {
				// nothing //
			} else {
				$wpSEOSettings['title-'.$postType] = ucfirst($postType).$wpSEOTitle;
				$wpSEOSettings['social-title-'.$postType] = ucfirst($postType).$wpSEOTitle;
			}
		}
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

		$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/';
		$possibleFiles = [ 'logo.webp', 'logo.png', 'logo.jpg', 'site-icon.webp', 'site-icon.png', 'site-icon.jpg', 'favicon.webp', 'favicon.png', 'favicon.jpg' ];
		$logoFile = null;

		foreach ($possibleFiles as $file) {
			if (is_file($uploadDir . $file)) {
				$logoFile = $file;
				break;
			}
		}

		if ($logoFile !== null) {
			$wpSEOSettings['company_logo'] = $logoFile;

			$id = attachment_url_to_postid($logoFile);
			if ($id) {
				$wpSEOSettings['company_logo_id'] = $id;
				$wpSEOSettings['company_logo_meta']['url']  = $logoFile;
				$wpSEOSettings['company_logo_meta']['path'] = get_attached_file($id);
				$wpSEOSettings['company_logo_meta']['id']   = $id;
			}
		}

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
		if ( isset($customer_info['facebook']) ) $wpSEOSocial['facebook_site'] = $customer_info['facebook'];
		if ( isset($customer_info['instagram']) ) $wpSEOSocial['instagram_url'] = $customer_info['instagram'];
		if ( isset($customer_info['linkedin']) ) $wpSEOSocial['linkedin_url'] = $customer_info['linkedin'];
		$wpSEOSocial['og_default_image'] = $wpSEOSettings['company_logo'];
		$wpSEOSocial['og_default_image_id'] = $wpSEOSettings['company_logo_id'];
		$wpSEOSocial['opengraph'] = '1';
		if ( isset($customer_info['pinterest']) ) $wpSEOSocial['pinterest_url'] = $customer_info['pinterest'];
		if ( isset($customer_info['twitter']) ) $wpSEOSocial['twitter_site'] = $customer_info['twitter'];
		if ( isset($customer_info['youtube']) ) $wpSEOSocial['youtube_url'] = $customer_info['youtube'];
		update_option( 'wpseo_social', $wpSEOSocial );
	}

// 12) Jobsite GEO - polling jobs
	$jobsite = get_site_option('jobsite_geo');	
	if ($jobsite && ($jobsite['fsm_brand'] ?? '') == 'Company Cam' && function_exists('bp_run_companycam_sync')) {
			bp_run_companycam_sync();
	}

	
// Basic Settings
	$update_menu_order = array ('site-header'=>100, 'widgets'=>200, 'office-hours'=>700, 'hours'=>700, 'coupon'=>700, 'site-message'=>800, 'site-footer'=>900);

	foreach ($update_menu_order as $page=>$order) {
		$updatePage = get_page_by_path($page, OBJECT, 'elements' );
		if ( !empty( $updatePage ) ) {
			wp_update_post(array(
				'ID' 		  		=> $updatePage->ID,
				'menu_order'    	=> $order,
			));
		}
	}

	update_option( 'blogname', $customer_info['name'] );
	$blogDesc = '';
	if ( $customer_info['city'] != '' ) $blogDesc .= $customer_info['city'];
	if ( $customer_info['city'] != '' && $customer_info['state-abbr'] != '' ) $blogDesc .= ', ';
	if ( $customer_info['state-abbr'] != '' ) $blogDesc .= $customer_info['state-abbr'];
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


// Clear terms, tags and categories with 0 entries
	$attachment_taxonomies = ['image-tags'];

	foreach (get_taxonomies(['public' => true], 'names') as $taxonomy) {
		if (in_array($taxonomy, $attachment_taxonomies, true)) continue;

		foreach (get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		]) as $term) {
			if ($term->count === 0 || $term->slug === 'service-area---') {
				wp_delete_term($term->term_id, $taxonomy);
			}
		}
	}

	foreach ($attachment_taxonomies as $taxonomy) {
		$terms = get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		]);

		foreach ($terms as $term) {
			$query = new WP_Query([
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'tax_query'      => [[
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				]]
			]);

			if ($query->found_posts === 0 || $term->slug === 'service-area---') {
				wp_delete_term($term->term_id, $taxonomy);
			}
		}
	}


// Prune weak testimonials
	$query = bp_WP_Query('testimonials', [
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC'
	]);

	if ( $query->found_posts > 75 ) {
		while ($query->have_posts()) {
			$query->the_post();
			$quality = get_field( "testimonial_quality" );
			if ( !has_post_thumbnail() && $quality[0] != 1 && strlen(wp_strip_all_tags(get_the_content(), true)) < 100 ) $draft = get_the_id();
			if ( has_post_thumbnail() && $quality[0] != 1 ) {
				$quality[0] = 1;
				update_field('testimonial_quality', $quality);
			}
		}

		wp_reset_postdata();
		if ( $draft ) wp_update_post( array ( 'ID' => $draft, 'post_status' => 'draft' ));
	}



/*--------------------------------------------------------------
# Universal Pages
--------------------------------------------------------------*/

/* Add appropriate pages */
	// American Standard - Customer Care Dealer
	if (
		!empty($customer_info['site-type']) &&
		strtolower(trim($customer_info['site-type'])) === 'hvac' &&
		!empty($customer_info['site-brand']) &&
		(
			(is_string($customer_info['site-brand']) &&
				strtolower(trim($customer_info['site-brand'])) === 'american standard')
			||
			(is_array($customer_info['site-brand']) &&
				in_array('american standard', array_map('strtolower', array_map('trim', $customer_info['site-brand'])), true))
		)
	) {
		if (is_null(get_page_by_path('customer-care-dealer', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Customer Care Dealer',
				'post_content' => '[get-universal-page slug="page-hvac-customer-care-dealer"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('customer-care-dealer', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// Rheem - Pro Partner
	if (
		!empty($customer_info['site-type']) &&
		strtolower(trim($customer_info['site-type'])) === 'hvac' &&
		!empty($customer_info['site-brand']) &&
		(
			(is_string($customer_info['site-brand']) &&
				strtolower(trim($customer_info['site-brand'])) === 'rheem')
			||
			(is_array($customer_info['site-brand']) &&
				in_array('rheem', array_map('strtolower', array_map('trim', $customer_info['site-brand'])), true))
		)
	) {
		if (is_null(get_page_by_path('rheem-pro-partner', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Rheem Pro Partner',
				'post_content' => '[get-universal-page slug="page-hvac-rheem-pro-partner"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('rheem-pro-partner', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// Ruud - Pro Partner
	if (
		!empty($customer_info['site-type']) &&
		strtolower(trim($customer_info['site-type'])) === 'hvac' &&
		!empty($customer_info['site-brand']) &&
		(
			(is_string($customer_info['site-brand']) &&
				strtolower(trim($customer_info['site-brand'])) === 'ruud')
			||
			(is_array($customer_info['site-brand']) &&
				in_array('ruud', array_map('strtolower', array_map('trim', $customer_info['site-brand'])), true))
		)
	) {
		if (is_null(get_page_by_path('ruud-pro-partner', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Ruud Pro Partner',
				'post_content' => '[get-universal-page slug="page-hvac-ruud-pro-partner"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('ruud-pro-partner', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// Comfortmaker - Elite Dealer
	if (
		!empty($customer_info['site-type']) &&
		strtolower(trim($customer_info['site-type'])) === 'hvac' &&
		!empty($customer_info['site-brand']) &&
		(
			(is_string($customer_info['site-brand']) &&
				strtolower(trim($customer_info['site-brand'])) === 'comfortmaker')
			||
			(is_array($customer_info['site-brand']) &&
				in_array('comfortmaker', array_map('strtolower', array_map('trim', $customer_info['site-brand'])), true))
		)
	) {
		if (is_null(get_page_by_path('comfortmaker-elite-dealer', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Comfortmaker Elite Dealer',
				'post_content' => '[get-universal-page slug="page-hvac-comfortmaker-elite-dealer"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('comfortmaker-elite-dealer', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// York - Certified Comfort Expert
	if (
		!empty($customer_info['site-type']) &&
		strtolower(trim($customer_info['site-type'])) === 'hvac' &&
		!empty($customer_info['site-brand']) &&
		(
			(is_string($customer_info['site-brand']) &&
				strtolower(trim($customer_info['site-brand'])) === 'york')
			||
			(is_array($customer_info['site-brand']) &&
				in_array('york', array_map('strtolower', array_map('trim', $customer_info['site-brand'])), true))
		)
	) {
		if (is_null(get_page_by_path('york-certified-comfort-expert', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'York Certified Comfort Expert',
				'post_content' => '[get-universal-page slug="page-hvac-york-cert-comfort-expert"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('york-certified-comfort-expert', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// Tempstar - Elite Dealer
	if (
		!empty($customer_info['site-type']) &&
		strtolower(trim($customer_info['site-type'])) === 'hvac' &&
		!empty($customer_info['site-brand']) &&
		(
			(is_string($customer_info['site-brand']) &&
				strtolower(trim($customer_info['site-brand'])) === 'tempstar')
			||
			(is_array($customer_info['site-brand']) &&
				in_array('tempstar', array_map('strtolower', array_map('trim', $customer_info['site-brand'])), true))
		)
	) {
		if (is_null(get_page_by_path('tempstar-elite-dealer', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Tempstar Elite Dealer',
				'post_content' => '[get-universal-page slug="page-hvac-tempstar-elite-dealer"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('tempstar-elite-dealer', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// Maintenance Tips
	if (!empty($customer_info['site-type']) && strtolower(trim($customer_info['site-type'])) === 'hvac') {
		if (is_null(get_page_by_path('maintenance-tips', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Maintenance Tips',
				'post_content' => '[get-universal-page slug="page-hvac-maintenance-tips"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('maintenance-tips', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// Symptom Checker
	if (!empty($customer_info['site-type']) && strtolower(trim($customer_info['site-type'])) === 'hvac') {
		if (is_null(get_page_by_path('symptom-checker', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Symptom Checker',
				'post_content' => '[get-universal-page slug="page-hvac-symptom-checker"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('symptom-checker', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// FAQ
	if (!empty($customer_info['site-type']) && strtolower(trim($customer_info['site-type'])) === 'hvac') {
		if (is_null(get_page_by_path('faq', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'FAQ',
				'post_content' => '[get-universal-page slug="page-hvac-faq"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('faq', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// Profile Page
	if (!empty($customer_info['site-type']) && strtolower(trim($customer_info['site-type'])) === 'profile') {
		if (is_null(get_page_by_path('profile', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Profile',
				'post_content' => '[get-universal-page slug="page-profile"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('profile', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// Profile Directory
	if (!empty($customer_info['site-type']) && strtolower(trim($customer_info['site-type'])) === 'profile') {
		if (is_null(get_page_by_path('profile-directory', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Profile Directory',
				'post_content' => '[get-universal-page slug="page-profile-directory"]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('profile-directory', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// Areas We Serve
	if (!empty($customer_info['service-areas']) && is_array($customer_info['service-areas'])) {
		if (is_null(get_page_by_path('areas-we-serve', OBJECT, 'universal'))) {
			wp_insert_post([
				'post_title'   => 'Areas We Serve',
				'post_content' => '[get-service-areas]',
				'post_status'  => 'publish',
				'post_type'    => 'universal',
			]);
		}
	} else {
		$getPage = get_page_by_path('areas-we-serve', OBJECT, 'universal');
		if ($getPage) wp_delete_post($getPage->ID, true);
	}

	// BP Debug Log
	if (is_null(get_page_by_path('debug', OBJECT, 'universal'))) {
		wp_insert_post([
			'post_title'   => 'BP Debug Log',
			'post_name'    => 'debug',
			'post_content' => '[show_debug_log]',
			'post_status'  => 'publish',
			'post_type'    => 'universal',
		]);
	}

	/* Add generic pages */
	if ( is_null(get_page_by_path('privacy-policy', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Privacy Policy', 'post_content' => '[get-universal-page slug="page-privacy-policy"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

	if ( is_null(get_page_by_path('accessibility-policy', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Accessibility Policy', 'post_content' => '[get-universal-page slug="page-accessibility-policy"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

	if ( is_null(get_page_by_path('terms-conditions', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Terms & Conditions', 'post_content' => '[get-universal-page slug="page-terms-conditions"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

	if ( is_null(get_page_by_path('review', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Review', 'post_content' => '[get-universal-page slug="page-review"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

	if ( is_null(get_page_by_path('email-received', OBJECT, 'universal')) ) wp_insert_post( array( 'post_title' => 'Email Received', 'post_content' => '[get-universal-page slug="page-email-received"]', 'post_status' => 'publish', 'post_type' => 'universal', ));

/*--------------------------------------------------------------
# Sync with Google Analytics
--------------------------------------------------------------*/
	$customer_info = customer_info();
	$GLOBALS['dataTerms'] = get_option('bp_data_terms') ? get_option('bp_data_terms') : array();
	$ga4_id = isset($customer_info['google-tags']['prop-id']) ? $customer_info['google-tags']['prop-id'] : null;

	try {
		if (!defined('GA4_SERVICE_ACCOUNT_JSON')) {throw new \Exception('GA4 credentials missing');}

		$credentials = json_decode(base64_decode(GA4_SERVICE_ACCOUNT_JSON), true);

		if (!is_array($credentials)) {throw new \Exception('GA4 credentials invalid');}

		$client = new BetaAnalyticsDataClient(['credentials' => $credentials,]);

	 } catch (\Throwable $e) {
		error_log('GA4 client init failed: ' . $e->getMessage());
		return;
	 }

	$today = date("Y-m-d", strtotime("-1 day"));
	$rewind = date("Y-m-d", strtotime("-6 years"));

	$siteHitsGA4 = is_array(get_option('bp_site_hits_ga4')) ? get_option('bp_site_hits_ga4') : array();

	$states = array('alabama'=>'AL', 'arizona'=>'AZ', 'arkansas'=>'AR', 'california'=>'CA', 'colorado'=>'CO', 'connecticut'=>'CT', 'delaware'=>'DE', 'dist of columbia'=>'DC', 'dist. of columbia'=>'DC', 'district of columbia'=>'DC', 'florida'=>'FL', 'georgia'=>'GA', 'idaho'=>'ID', 'illinois'=>'IL', 'indiana'=>'IN', 'iowa'=>'IA', 'kansas'=>'KS', 'kentucky'=>'KY', 'louisiana'=>'LA', 'maine'=>'ME', 'maryland'=>'MD', 'massachusetts'=>'MA', 'michigan'=>'MI', 'minnesota'=>'MN', 'mississippi'=>'MS', 'missouri'=>'MO', 'montana'=>'MT', 'nebraska'=>'NE', 'nevada'=>'NV', 'new hampshire'=>'NH', 'new jersey'=>'NJ', 'new mexico'=>'NM', 'new york'=>'NY', 'north carolina'=>'NC', 'north dakota'=>'ND', 'ohio'=>'OH', 'oklahoma'=>'OK', 'oregon'=>'OR', 'pennsylvania'=>'PA', 'rhode island'=>'RI', 'south carolina'=>'SC', 'south dakota'=>'SD', 'tennessee'=>'TN', 'texas'=>'TX', 'utah'=>'UT', 'vermont'=>'VT', 'virginia'=>'VA', 'washington'=>'WA', 'washington d.c.'=>'DC', 'washington dc'=>'DC', 'west virginia'=>'WV', 'wisconsin'=>'WI', 'wyoming'=>'WY');
	$removedStates = array('alaska'=>'AK', 'hawaii'=>'HI',);

// Gather GA4 Stats
	if ( $ga4_id && substr($ga4_id, 0, 2) != '00' ) {

		// Weekly Visitor Trends
		$analyticsGA4 = array();

		$response = null;

		try {
			$response = $client->runReport([
				'property' => 'properties/' . $ga4_id,
				'dateRanges' => [
					new DateRange([
						'start_date' => $rewind,
						'end_date'   => $today
					]),
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
		} catch (\Google\ApiCore\ApiException $e) {
			error_log('GA4 API error: ' . $e->getMessage());
			$response = null;
		}

		if ( $response ) {
			foreach ( $response->getRows() as $row ) {

				$date  = $row->getDimensionValues()[0]->getValue();
				$city  = $row->getDimensionValues()[1]->getValue();
				$state = strtolower($row->getDimensionValues()[2]->getValue());

				if ( isset($states[$state]) ) {

					$location = $city === '(not set)'
						? ucwords($state)
						: $city . ', ' . $states[$state];

					$analyticsGA4[] = [
						'date'              => $date,
						'location'          => $location,
						'total-users'       => $row->getMetricValues()[0]->getValue(),
						'new-users'         => $row->getMetricValues()[1]->getValue(),
						'sessions'          => $row->getMetricValues()[2]->getValue(),
						'engaged-sessions'  => $row->getMetricValues()[3]->getValue(),
						'session-duration'  => $row->getMetricValues()[4]->getValue(),
						'page-views'        => $row->getMetricValues()[5]->getValue(),
					];
				}
			}
		}

		if ( is_array($analyticsGA4) ) arsort($analyticsGA4);
		update_option('bp_ga4_trends_01', $analyticsGA4, false);


		// Site Visitors
		$analyticsGA4 = array();
		$dataTerms = array('day' => 1) + $GLOBALS['dataTerms'];
		foreach ( $dataTerms as $termTitle => $termDays ) {

		$response = null;

		try {
			$response = $client->runReport([
				'property' => 'properties/' . $ga4_id,
				'dateRanges' => [
					new DateRange([
						'start_date' => date('Y-m-d', strtotime("-{$termDays} days")),
						'end_date'   => $today,
					]),
				],
				'dimensions' => [
					new Dimension([ 'name' => 'city' ]),
					new Dimension([ 'name' => 'region' ]),
				],
				'metrics' => [
					new Metric([ 'name' => 'totalUsers' ]),
				],
			]);
		} catch (\Google\ApiCore\ApiException $e) {
			error_log('GA4 visitors runReport failed: ' . $e->getMessage());
			continue; // skip THIS term only
		}

		if ( !$response ) {
			continue;
		}

		foreach ( $response->getRows() as $row ) {

			$city  = $row->getDimensionValues()[0]->getValue() ?? '';
			$state = strtolower($row->getDimensionValues()[1]->getValue() ?? '');

			if ( !isset($states[$state]) ) {
				continue;
			}

			$location = ($city === '(not set)')
				? ucwords($state)
				: $city . ', ' . $states[$state];

			$totalUsers = (int) ($row->getMetricValues()[0]->getValue() ?? 0);

			if ( !isset($analyticsGA4[$location]) ) {
				$analyticsGA4[$location] = [];
			}

			$analyticsGA4[$location]['page-views-' . $termDays] = $totalUsers;
		}
		}

		if ( !empty($analyticsGA4) ) {
		arsort($analyticsGA4);
		update_option('bp_ga4_visitors_01', $analyticsGA4, false);
		}

		// Most Popular Pages
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle => $termDays ) {

			$response = null;

			try {
				$response = $client->runReport([
					'property' => 'properties/' . $ga4_id,
					'dateRanges' => [
						new DateRange([
							'start_date' => date('Y-m-d', strtotime("-{$termDays} days")),
							'end_date'   => $today,
						]),
					],
					'dimensions' => [
						new Dimension([ 'name' => 'pagePath' ]),
						new Dimension([ 'name' => 'city' ]),
						new Dimension([ 'name' => 'region' ]),
					],
					'metrics' => [
						new Metric([ 'name' => 'screenPageViews' ]),
					],
				]);
			} catch ( \Google\ApiCore\ApiException $e ) {
				error_log('GA4 pages runReport failed: ' . $e->getMessage());
				continue; // skip this term only
			}

			if ( !$response ) {
				continue;
			}

			foreach ( $response->getRows() as $row ) {

				$pagePath = $row->getDimensionValues()[0]->getValue() ?? '';
				$city     = $row->getDimensionValues()[1]->getValue() ?? '';
				$state    = strtolower($row->getDimensionValues()[2]->getValue() ?? '');

				if ( !isset($states[$state]) ) {
					continue;
				}

				$location = ($city === '(not set)')
					? ucwords($state)
					: $city . ', ' . $states[$state];

				$pageViews = (int) ($row->getMetricValues()[0]->getValue() ?? 0);

				// Normalize page name
				if ( $pagePath === '/' || $pagePath === '' ) {
					$pageName = 'Home';
				} else {
					$pageName = trim($pagePath, '/');
					$pageName = str_replace('-', ' ', $pageName);
					$pageName = str_replace('/', ' » ', $pageName);
					$pageName = ucwords($pageName);
				}

				// Ensure array depth exists
				if ( !isset($analyticsGA4[$pageName]) ) {
					$analyticsGA4[$pageName] = [];
				}
				if ( !isset($analyticsGA4[$pageName][$location]) ) {
					$analyticsGA4[$pageName][$location] = [];
				}

				$analyticsGA4[$pageName][$location]['page-views-' . $termDays] = $pageViews;
			}
		}

		if ( !empty($analyticsGA4) ) {
			arsort($analyticsGA4);
			update_option('bp_ga4_pages_01', $analyticsGA4, false);
		}


		// Referrers
		$analyticsGA4 = array();

		foreach ( $GLOBALS['dataTerms'] as $termTitle => $termDays ) {

			$response = null;

			try {
				$response = $client->runReport([
					'property' => 'properties/' . $ga4_id,
					'dateRanges' => [
						new DateRange([
							'start_date' => date('Y-m-d', strtotime("-{$termDays} days")),
							'end_date'   => $today,
						]),
					],
					'dimensions' => [
						new Dimension([ 'name' => 'firstUserSourceMedium' ]),
						new Dimension([ 'name' => 'city' ]),
						new Dimension([ 'name' => 'region' ]),
					],
					'metrics' => [
						new Metric([ 'name' => 'engagedSessions' ]),
					],
				]);
			} catch ( \Google\ApiCore\ApiException $e ) {
				error_log('GA4 referrers runReport failed: ' . $e->getMessage());
			}

			if ( $response ) {

				foreach ( $response->getRows() as $row ) {

					$pageReferrer = $row->getDimensionValues()[0]->getValue() ?? '';
					$city         = $row->getDimensionValues()[1]->getValue() ?? '';
					$state        = strtolower($row->getDimensionValues()[2]->getValue() ?? '');

					if ( !isset($states[$state]) ) {
						continue;
					}

					// Ignore self-referrals
					if ( $pageReferrer && strpos($pageReferrer, $_SERVER['HTTP_HOST']) !== false ) {
						continue;
					}

					// Normalize referrer labels
					$switchRef = [
						'facebook'  => 'Facebook',
						'yelp'      => 'Yelp',
						'youtube'   => 'YouTube',
						'instagram' => 'Instagram',
					];

					foreach ( $switchRef as $find => $replace ) {
						if ( stripos($pageReferrer, $find) !== false ) {
							$pageReferrer = $replace;
							break;
						}
					}

					if ( $pageReferrer === '' ) {
						$pageReferrer = 'Direct';
					}

					$location = ($city === '(not set)')
						? ucwords($state)
						: $city . ', ' . $states[$state];

					$sessions = (int) ($row->getMetricValues()[0]->getValue() ?? 0);

					// Safe initialization
					if ( !isset($analyticsGA4[$pageReferrer]) ) {
						$analyticsGA4[$pageReferrer] = [];
					}
					if ( !isset($analyticsGA4[$pageReferrer][$location]) ) {
						$analyticsGA4[$pageReferrer][$location] = [];
					}
					if ( !isset($analyticsGA4[$pageReferrer][$location]['sessions-' . $termDays]) ) {
						$analyticsGA4[$pageReferrer][$location]['sessions-' . $termDays] = 0;
					}

					$analyticsGA4[$pageReferrer][$location]['sessions-' . $termDays] += $sessions;
				}
			}
		}

		if ( !empty($analyticsGA4) ) {
			update_option('bp_ga4_referrers_01', $analyticsGA4, false);
		}


		// Locations
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle => $termDays ) {

			$response = null;

			try {
				$response = $client->runReport([
					'property' => 'properties/' . $ga4_id,
					'dateRanges' => [
						new DateRange([
							'start_date' => date('Y-m-d', strtotime("-{$termDays} days")),
							'end_date'   => $today,
						]),
					],
					'dimensions' => [
						new Dimension([ 'name' => 'firstUserSourceMedium' ]),
						new Dimension([ 'name' => 'city' ]),
						new Dimension([ 'name' => 'region' ]),
					],
					'metrics' => [
						new Metric([ 'name' => 'engagedSessions' ]),
					],
				]);
			} catch ( \Google\ApiCore\ApiException $e ) {
				error_log('GA4 referrers runReport failed: ' . $e->getMessage());
				continue; // skip this term only
			}

			if ( !$response ) {
				continue;
			}

			foreach ( $response->getRows() as $row ) {

				$pageReferrer = $row->getDimensionValues()[0]->getValue() ?? '';
				$city         = $row->getDimensionValues()[1]->getValue() ?? '';
				$state        = strtolower($row->getDimensionValues()[2]->getValue() ?? '');

				if ( !isset($states[$state]) ) {
					continue;
				}

				$location = ($city === '(not set)')
					? ucwords($state)
					: $city . ', ' . $states[$state];

				$sessions = (int) ($row->getMetricValues()[0]->getValue() ?? 0);

				// Normalize referrer labels
				$switchRef = [
					'facebook'  => 'Facebook',
					'yelp'      => 'Yelp',
					'youtube'   => 'YouTube',
					'instagram' => 'Instagram',
				];

				// Ignore self-referrals
				if ( strpos($pageReferrer, $_SERVER['HTTP_HOST']) !== false ) {
					continue;
				}

				foreach ( $switchRef as $find => $replace ) {
					if ( stripos($pageReferrer, $find) !== false ) {
						$pageReferrer = $replace;
						break;
					}
				}

				if ( $pageReferrer === '' ) {
					$pageReferrer = 'Direct';
				}

				// Ensure array depth exists
				if ( !isset($analyticsGA4[$pageReferrer]) ) {
					$analyticsGA4[$pageReferrer] = [];
				}
				if ( !isset($analyticsGA4[$pageReferrer][$location]) ) {
					$analyticsGA4[$pageReferrer][$location] = [];
				}
				if ( !isset($analyticsGA4[$pageReferrer][$location]['sessions-' . $termDays]) ) {
					$analyticsGA4[$pageReferrer][$location]['sessions-' . $termDays] = 0;
				}

				// Accumulate sessions
				$analyticsGA4[$pageReferrer][$location]['sessions-' . $termDays] += $sessions;
			}
		}

		if ( !empty($analyticsGA4) ) {
			arsort($analyticsGA4);
			update_option('bp_ga4_referrers_01', $analyticsGA4, false);
		}


		// Browsers
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle => $termDays ) {

			$response = null;

			try {
				$response = $client->runReport([
					'property' => 'properties/' . $ga4_id,
					'dateRanges' => [
						new DateRange([
							'start_date' => date('Y-m-d', strtotime("-{$termDays} days")),
							'end_date'   => $today,
						]),
					],
					'dimensions' => [
						new Dimension([ 'name' => 'browser' ]),
						new Dimension([ 'name' => 'city' ]),
						new Dimension([ 'name' => 'region' ]),
					],
					'metrics' => [
						new Metric([ 'name' => 'engagedSessions' ]),
					],
				]);
			} catch ( \Google\ApiCore\ApiException $e ) {
				error_log('GA4 browsers runReport failed: ' . $e->getMessage());
				continue; // skip this term only
			}

			if ( !$response ) {
				continue;
			}

			foreach ( $response->getRows() as $row ) {

				$browser = $row->getDimensionValues()[0]->getValue() ?? '';
				$city    = $row->getDimensionValues()[1]->getValue() ?? '';
				$state   = strtolower($row->getDimensionValues()[2]->getValue() ?? '');

				if ( !isset($states[$state]) || $browser === '' ) {
					continue;
				}

				$location = ($city === '(not set)')
					? ucwords($state)
					: $city . ', ' . $states[$state];

				$sessions = (int) ($row->getMetricValues()[0]->getValue() ?? 0);

				// Ensure array depth exists
				if ( !isset($analyticsGA4[$browser]) ) {
					$analyticsGA4[$browser] = [];
				}
				if ( !isset($analyticsGA4[$browser][$location]) ) {
					$analyticsGA4[$browser][$location] = [];
				}
				if ( !isset($analyticsGA4[$browser][$location]['sessions-' . $termDays]) ) {
					$analyticsGA4[$browser][$location]['sessions-' . $termDays] = 0;
				}

				// Accumulate
				$analyticsGA4[$browser][$location]['sessions-' . $termDays] += $sessions;
			}
		}

		if ( !empty($analyticsGA4) ) {
			arsort($analyticsGA4);
			update_option('bp_ga4_browsers_01', $analyticsGA4, false);
		}


		// Devices
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle => $termDays ) {

			$response = null;

			try {
				$response = $client->runReport([
					'property' => 'properties/' . $ga4_id,
					'dateRanges' => [
						new DateRange([
							'start_date' => date('Y-m-d', strtotime("-{$termDays} days")),
							'end_date'   => $today,
						]),
					],
					'dimensions' => [
						new Dimension([ 'name' => 'deviceCategory' ]),
						new Dimension([ 'name' => 'city' ]),
						new Dimension([ 'name' => 'region' ]),
					],
					'metrics' => [
						new Metric([ 'name' => 'engagedSessions' ]),
					],
				]);
			} catch ( \Google\ApiCore\ApiException $e ) {
				error_log('GA4 devices runReport failed: ' . $e->getMessage());
				continue; // skip this term only
			}

			if ( !$response ) {
				continue;
			}

			foreach ( $response->getRows() as $row ) {

				$deviceType = $row->getDimensionValues()[0]->getValue() ?? '';
				$city       = $row->getDimensionValues()[1]->getValue() ?? '';
				$state      = strtolower($row->getDimensionValues()[2]->getValue() ?? '');

				if ( $deviceType === '' || !isset($states[$state]) ) {
					continue;
				}

				$location = ($city === '(not set)')
					? ucwords($state)
					: $city . ', ' . $states[$state];

				$sessions = (int) ($row->getMetricValues()[0]->getValue() ?? 0);

				// Ensure array structure exists before +=
				if ( !isset($analyticsGA4[$deviceType]) ) {
					$analyticsGA4[$deviceType] = [];
				}
				if ( !isset($analyticsGA4[$deviceType][$location]) ) {
					$analyticsGA4[$deviceType][$location] = [];
				}
				if ( !isset($analyticsGA4[$deviceType][$location]['sessions-' . $termDays]) ) {
					$analyticsGA4[$deviceType][$location]['sessions-' . $termDays] = 0;
				}

				$analyticsGA4[$deviceType][$location]['sessions-' . $termDays] += $sessions;
			}
		}

		if ( !empty($analyticsGA4) ) {
			arsort($analyticsGA4);
			update_option('bp_ga4_devices_01', $analyticsGA4, false);
		}


		// Site Load Speed
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle => $termDays ) {

			$response = null;

			try {
				$response = $client->runReport([
					'property' => 'properties/' . $ga4_id,
					'dateRanges' => [
						new DateRange([
							'start_date' => date('Y-m-d', strtotime("-{$termDays} days")),
							'end_date'   => $today,
						]),
					],
					'dimensions' => [
						new Dimension([ 'name' => 'groupId' ]),
						new Dimension([ 'name' => 'city' ]),
						new Dimension([ 'name' => 'region' ]),
					],
					// NOTE: no metrics returned for this report
				]);
			} catch ( \Google\ApiCore\ApiException $e ) {
				error_log('GA4 speed runReport failed: ' . $e->getMessage());
				continue; // skip this term only
			}

			if ( !$response ) {
				continue;
			}

			foreach ( $response->getRows() as $row ) {

				$groupId = $row->getDimensionValues()[0]->getValue() ?? '';
				$city    = $row->getDimensionValues()[1]->getValue() ?? '';
				$state   = strtolower($row->getDimensionValues()[2]->getValue() ?? '');

				if ( $groupId === '' || !isset($states[$state]) ) {
					continue;
				}

				$location = ($city === '(not set)')
					? ucwords($state)
					: $city . ', ' . $states[$state];

				// Ensure array structure exists
				if ( !isset($analyticsGA4[$location]) ) {
					$analyticsGA4[$location] = [];
				}
				if ( !isset($analyticsGA4[$location]['sessions-' . $termDays]) ) {
					$analyticsGA4[$location]['sessions-' . $termDays] = [];
				}

				$analyticsGA4[$location]['sessions-' . $termDays][] = $groupId;
			}
		}

		if ( !empty($analyticsGA4) ) {
			arsort($analyticsGA4);
			update_option('bp_ga4_speed_01', $analyticsGA4, false);
		}


		// Screen Resolutions
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle => $termDays ) {

			$response = null;

			try {
				$response = $client->runReport([
					'property' => 'properties/' . $ga4_id,
					'dateRanges' => [
						new DateRange([
							'start_date' => date('Y-m-d', strtotime("-{$termDays} days")),
							'end_date'   => $today,
						]),
					],
					'dimensions' => [
						new Dimension([ 'name' => 'screenResolution' ]),
						new Dimension([ 'name' => 'city' ]),
						new Dimension([ 'name' => 'region' ]),
					],
					'metrics' => [
						new Metric([ 'name' => 'engagedSessions' ]),
					]
				]);
			} catch ( \Google\ApiCore\ApiException $e ) {
				error_log('GA4 resolution runReport failed: ' . $e->getMessage());
				continue; // skip this term only
			}

			if ( !$response ) {
				continue;
			}

			foreach ( $response->getRows() as $row ) {

				$screenResolution = $row->getDimensionValues()[0]->getValue() ?? '';
				$city             = $row->getDimensionValues()[1]->getValue() ?? '';
				$state            = strtolower($row->getDimensionValues()[2]->getValue() ?? '');

				if ( $screenResolution === '' || !isset($states[$state]) ) {
					continue;
				}

				$location = ($city === '(not set)')
					? ucwords($state)
					: $city . ', ' . $states[$state];

				$sessions = (int) ($row->getMetricValues()[0]->getValue() ?? 0);

				// Initialize array structure safely
				if ( !isset($analyticsGA4[$screenResolution]) ) {
					$analyticsGA4[$screenResolution] = [];
				}
				if ( !isset($analyticsGA4[$screenResolution][$location]) ) {
					$analyticsGA4[$screenResolution][$location] = [];
				}
				if ( !isset($analyticsGA4[$screenResolution][$location]['sessions-' . $termDays]) ) {
					$analyticsGA4[$screenResolution][$location]['sessions-' . $termDays] = 0;
				}

				$analyticsGA4[$screenResolution][$location]['sessions-' . $termDays] += $sessions;
			}
		}

		if ( !empty($analyticsGA4) ) {
			arsort($analyticsGA4);
			update_option('bp_ga4_resolution_01', $analyticsGA4, false);
		}


		// Content Visibility
		$analyticsGA4 = array();
		foreach ( $GLOBALS['dataTerms'] as $termTitle => $termDays ) {

			$response = null;

			try {
				$response = $client->runReport([
					'property' => 'properties/' . $ga4_id,
					'dateRanges' => [
						new DateRange([
							'start_date' => date('Y-m-d', strtotime("-{$termDays} days")),
							'end_date'   => $today,
						]),
					],
					'dimensions' => [
						new Dimension([ 'name' => 'achievementId' ]),
						new Dimension([ 'name' => 'city' ]),
						new Dimension([ 'name' => 'region' ]),
					],
					'metrics' => [
						new Metric([ 'name' => 'sessions' ]),
					]
				]);
			} catch ( \Google\ApiCore\ApiException $e ) {
				error_log('GA4 achievementId runReport failed: ' . $e->getMessage());
				continue; // skip this term only
			}

			if ( !$response ) {
				continue;
			}

			foreach ( $response->getRows() as $row ) {

				$achievementId = $row->getDimensionValues()[0]->getValue() ?? '';
				$city          = $row->getDimensionValues()[1]->getValue() ?? '';
				$state         = strtolower($row->getDimensionValues()[2]->getValue() ?? '');

				if ( $achievementId === '' || !isset($states[$state]) ) {
					continue;
				}

				$location = ($city === '(not set)')
					? ucwords($state)
					: $city . ', ' . $states[$state];

				$sessions = (int) ($row->getMetricValues()[0]->getValue() ?? 0);

				// Initialize array structure safely
				if ( !isset($analyticsGA4[$achievementId]) ) {
					$analyticsGA4[$achievementId] = [];
				}
				if ( !isset($analyticsGA4[$achievementId][$location]) ) {
					$analyticsGA4[$achievementId][$location] = [];
				}
				if ( !isset($analyticsGA4[$achievementId][$location]['sessions-' . $termDays]) ) {
					$analyticsGA4[$achievementId][$location]['sessions-' . $termDays] = 0;
				}

				$analyticsGA4[$achievementId][$location]['sessions-' . $termDays] += $sessions;
			}
		}

		if ( !empty($analyticsGA4) ) {
			arsort($analyticsGA4);
			update_option('bp_ga4_achievementId_01', $analyticsGA4, false);
		}
	}

	wp_cache_delete('customer_info', 'options');
	wp_cache_flush();
}

























function ci_normalize_pids($raw): array {
    $pid = is_array($raw) ? $raw : ( ($raw === null || $raw === false) ? [] : [$raw] );
    $pid = array_map('strval', $pid);
    $pid = array_map('trim', $pid);
    $pid = array_filter($pid, fn($x) => $x !== '' && strlen($x) > 10);
    return array_values(array_unique($pid));
}

function ci_email_diff(array $old, array $new, string $site_name): void {
    $diffs = [];
    $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
    foreach ($keys as $k) {
        $ov = $old[$k] ?? null;
        $nv = $new[$k] ?? null;
        if ($ov === $nv) continue;
        $toS = function($x) {
            if (is_array($x)) {
                $j = json_encode($x);
                return strlen($j) > 400 ? substr($j,0,400).'…' : $j;
            }
            return (string)$x;
        };
        $diffs[$k] = ['old'=>$toS($ov), 'new'=>$toS($nv)];
    }
    if (!$diffs) return;
    $msg = "customer_info changes for {$site_name}:\n\n";
    foreach ($diffs as $k => $d) {
        $msg .= "• {$k}\n  - old: {$d['old']}\n  - new: {$d['new']}\n\n";
    }
    if (function_exists('emailMe')) { emailMe('customer_info updated - '.$site_name, $msg); }
    else { error_log($msg); }
}

// Helper: make values readable in email
function _fmt_email_val($v): string {
    if (is_array($v)) {
        return rtrim(print_r($v, true));
    }
    $s = trim((string)$v);

    // normalize fancy punctuation (thin spaces, en dash, em dash)
    $s = strtr($s, [
        "\xE2\x80\x89" => ' ',  // thin space
        "\xE2\x80\x93" => '-',  // en dash
        "\xE2\x80\x94" => '-',  // em dash
    ]);

    // If this looks like weekday hours, split at pipes to new lines
    if (preg_match('/\b(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/i', $s)) {
        $s = preg_replace('/\s*\|\s*/', "\n            ", $s);
    }

    return $s;
}


function gbp_diff_vs_ci_and_notify(array $ci, array $google_info, array $placeIDs): void {
    $primePID    = $placeIDs[0] ?? null;
    $gbp_primary = $primePID ? ($google_info[$primePID] ?? []) : [];

    $norm_str = fn($v) => strtolower(trim(preg_replace('/\s+/', ' ', (string)$v)));
    $norm_phone = function($area, $phone) {
        $a = preg_replace('/\D+/', '', (string)$area);
        $p = preg_replace('/\D+/', '', (string)$phone);
        return (strlen($a) === 3 && strlen($p) === 7) ? $a.$p : preg_replace('/\D+/', '', $a.$p);
    };

    $diffs = [];
    $pairs = [
        'name'       => ['ci'=>$ci['name']??null,       'gbp'=>$gbp_primary['name']??null,       'norm'=>'str'],
        'street'     => ['ci'=>$ci['street']??null,     'gbp'=>$gbp_primary['street']??null,     'norm'=>'str'],
        'city'       => ['ci'=>$ci['city']??null,       'gbp'=>$gbp_primary['city']??null,       'norm'=>'str'],
        'state-abbr' => ['ci'=>$ci['state-abbr']??null, 'gbp'=>$gbp_primary['state-abbr']??null, 'norm'=>'upper'],
        'state-full' => ['ci'=>$ci['state-full']??null, 'gbp'=>$gbp_primary['state-full']??null, 'norm'=>'str'],
        'zip'        => ['ci'=>$ci['zip']??null,        'gbp'=>$gbp_primary['zip']??null,        'norm'=>'raw'],
    ];
    foreach ($pairs as $label=>$pair) {
        $ciV=$pair['ci']; $gbpV=$pair['gbp'];
        $ciN = $pair['norm']==='str'   ? $norm_str($ciV)
             : ($pair['norm']==='upper'? strtoupper(trim((string)$ciV))
             : trim((string)$ciV));
        $gbpN= $pair['norm']==='str'   ? $norm_str($gbpV)
             : ($pair['norm']==='upper'? strtoupper(trim((string)$gbpV))
             : trim((string)$gbpV));
        if ($gbpN !== '' && $ciN !== $gbpN) $diffs[$label]=['current'=>$ciV,'gbp'=>$gbpV];
    }
    $ciPhone  = $norm_phone($ci['area']??'', $ci['phone']??'');
    $gbpPhone = $norm_phone($gbp_primary['area']??'', $gbp_primary['phone']??'');
    if ($gbpPhone !== '' && $ciPhone !== $gbpPhone) {
        $diffs['phone_full'] = [
            'current' => trim(($ci['area']??'').' '.($ci['phone']??'')),
            'gbp'     => trim(($gbp_primary['area']??'').' '.($gbp_primary['phone']??'')),
        ];
    }
	/*
    $ciHoursDesc  = is_array($ci['current-hours']??null) ? implode(' | ', (array)$ci['current-hours']) : (string)($ci['current-hours']??'');
    $gbpHoursDesc = !empty($gbp_primary['current-hours']['weekdayDescriptions'])
                    ? implode(' | ', (array)$gbp_primary['current-hours']['weekdayDescriptions'])
                    : '';
    if ($gbpHoursDesc !== '' && trim($ciHoursDesc) !== trim($gbpHoursDesc)) {
        $diffs['hours'] = ['current'=>$ciHoursDesc, 'gbp'=>$gbpHoursDesc];
    }
	*/
    if (!$diffs) return;
    $hash = md5(json_encode($diffs));
	if ($hash === get_option('bp_diffhash_gbp_vs_ci')) return;
	update_option('bp_diffhash_gbp_vs_ci', $hash, false);

	$NL = "<br>\n";
	$msg  = "GBP vs customer_info differences for ".($ci['name'] ?? '(site)').":".$NL.$NL;
	foreach ($diffs as $k => $v) {
		$label = strtoupper(str_replace('_',' ',$k));
		$cur   = _fmt_email_val($v['current'] ?? '');
		$gbp   = _fmt_email_val($v['gbp'] ?? '');
		$msg  .= "• {$label}{$NL}"
			  .  "  current: {$cur}{$NL}"
			  .  "  gbp:     {$gbp}{$NL}{$NL}";
	}
	if (function_exists('emailMe')) emailMe('GBP/customer_info discrepancy - '.($ci['name']??''), $msg);
}

function ci_merge_gbp_into_ci(array $ci, array $gbp_primary, bool $pid_sync): array {
    $map = ['name'=>'name', 'area'=>'area', 'phone'=>'phone', 'street'=>'street', 'city'=>'city', 'state-abbr'=>'state-abbr', 'state-full'=>'state-full', 'zip'=>'zip', 'lat'=>'lat', 'long'=>'long'];
    $diffs = [];
    $new   = $ci;

    foreach ($map as $ck => $gk) {
        $gv = $gbp_primary[$gk] ?? null;
        if ($gv === null || $gv === '') continue;

        $cv_exists = array_key_exists($ck, $new) && $new[$ck] !== '' && $new[$ck] !== null;
        if (!$cv_exists) { $new[$ck] = $gv; continue; }           // fill missing, no email/diff needed

        $different = ($ck === 'state-abbr')
            ? (strtoupper(trim((string)$new[$ck])) !== strtoupper(trim((string)$gv)))
            : (($ck === 'lat' || $ck === 'long')
                ? (abs((float)$new[$ck] - (float)$gv) > 0.001)
                : (strtolower(trim((string)$new[$ck])) !== strtolower(trim((string)$gv)))
              );

        if ($ck === 'area' || $ck === 'phone') { /* handled as combined phone elsewhere if you want */ }

        if ($different) {
            $diffs[$ck] = ['current'=>$new[$ck], 'gbp'=>$gv];
            if ($pid_sync) $new[$ck] = $gv;
        }
    }

    // Phone as a combined comparison/overlay
    $digits = fn($v) => preg_replace('/\D+/', '', (string)$v);
    $ci10   = (function($a,$p,$d){ $a=$d($a); $p=$d($p); return (strlen($a)===3 && strlen($p)===7)?$a.$p:$d($a.$p); })($new['area']??'', $new['phone']??'', $digits);
    $gbp10  = (function($a,$p,$d){ $a=$d($a); $p=$d($p); return (strlen($a)===3 && strlen($p)===7)?$a.$p:$d($a.$p); })($gbp_primary['area']??'', $gbp_primary['phone']??'', $digits);
    if ($gbp10 !== '' && $ci10 !== $gbp10) {
        $diffs['phone_full'] = ['current'=>trim(($new['area']??'').' '.($new['phone']??'')),
                                'gbp'    =>trim(($gbp_primary['area']??'').' '.($gbp_primary['phone']??''))];
        if ($pid_sync) { $new['area'] = $gbp_primary['area']; $new['phone'] = $gbp_primary['phone']; }
    }

    // Hours (fill if missing; overwrite if pid_sync and different)
    $ciHoursDesc  = is_array($new['current-hours'] ?? null) ? implode(' | ', (array)$new['current-hours']) : (string)($new['current-hours'] ?? '');
    $gbpHoursDesc = !empty($gbp_primary['current-hours']['weekdayDescriptions'])
                    ? implode(' | ', (array)$gbp_primary['current-hours']['weekdayDescriptions'])
                    : '';
    if ($gbpHoursDesc !== '' && trim($ciHoursDesc) !== trim($gbpHoursDesc)) {
        $diffs['hours'] = ['current'=>$ciHoursDesc, 'gbp'=>$gbpHoursDesc];
        if ($pid_sync) {
            if (!empty($gbp_primary['hours']))         $new['hours'] = $gbp_primary['hours'];
            if (!empty($gbp_primary['current-hours'])) $new['current-hours'] = $gbp_primary['current-hours']['weekdayDescriptions'] ?? $gbp_primary['current-hours'];
        }
    } else {
        if (!isset($new['hours']) && !empty($gbp_primary['hours'])) $new['hours'] = $gbp_primary['hours'];
        if (!isset($new['current-hours']) && !empty($gbp_primary['current-hours']['weekdayDescriptions']))
            $new['current-hours'] = $gbp_primary['current-hours']['weekdayDescriptions'];
    }

// ---- ENSURE HOURS ALWAYS GET SEEDED INTO CI AT LEAST ONCE ----
    if (empty($new['hours']) && !empty($gbp_primary['hours'])) {
        // full Google regularOpeningHours payload (has periods)
        $new['hours'] = $gbp_primary['hours'];
    }

    if (empty($new['current-hours'])) {
        // prefer the plain weekdayDescriptions array if present
        if (!empty($gbp_primary['current-hours']['weekdayDescriptions'])) {
            $new['current-hours'] = $gbp_primary['current-hours']['weekdayDescriptions'];
        } elseif (!empty($gbp_primary['current-hours']) && is_array($gbp_primary['current-hours'])) {
            // fallback: if GBP currentOpeningHours exists but you didn't get weekdayDescriptions for some reason
            $new['current-hours'] = $gbp_primary['current-hours'];
        }
    }

    return [$new, $diffs];
}

function ci_finalize_fields(array &$ci): void {
    // phone-format
    $digits = fn($v) => preg_replace('/\D+/', '', (string)$v);
    $a = $digits($ci['area'] ?? ''); $p = $digits($ci['phone'] ?? '');
    if (strlen($a)===3 && strlen($p)===7) {
        $ci['phone-format'] = ($ci['area-before'] ?? '(').$a.($ci['area-after'] ?? ') ')
                            . substr($p,0,3).'-'.substr($p,3);
    } else {
        unset($ci['phone-format']);
    }

    // default-loc
    $city = trim((string)($ci['city'] ?? ''));
    $st   = trim((string)($ci['state-abbr'] ?? ''));
    if ($city !== '' && $st !== '') $ci['default-loc'] = "$city, $st";
    else unset($ci['default-loc']);

    // prune trivial empties
    foreach ($ci as $k=>$v) {
        if ($v === null) { unset($ci[$k]); continue; }
        if (is_string($v) && trim($v)==='') { unset($ci[$k]); continue; }
        if (is_array($v)) {
            $trimmed = array_filter(array_map(fn($x)=>is_string($x)?trim($x):$x, $v),
                                    fn($x)=>$x!=='' && $x!==null && $x!==[]);
            if ($trimmed === []) unset($ci[$k]);
        }
    }
}

function ci_build_hours($periods, $wkdesc): array {
    $res   = ['compact'=>'', 'openingHoursSpecification'=>[]];
    $byDay = array_fill(0, 7, []); // Google: 0=Sun..6=Sat

    $fmtTime = function(array $node): ?string {
        if (isset($node['time']) && preg_match('/^\d{3,4}$/', $node['time'])) {
            $t = str_pad($node['time'], 4, '0', STR_PAD_LEFT);
            return substr($t,0,2).':'.substr($t,2,2);
        }
        if (isset($node['hour'])) {
            $h = (int)$node['hour'];
            $m = isset($node['minute']) ? (int)$node['minute'] : 0;
            return sprintf('%02d:%02d',$h,$m);
        }
        return null;
    };

    // Build daily buckets
    if (is_array($periods)) {
        foreach ($periods as $p) {
            if (!isset($p['open'])) continue;
            $od = isset($p['open']['day']) ? (int)$p['open']['day'] : null;
            $ot = $fmtTime($p['open']); if ($od===null || $ot===null) continue;
            if (!isset($p['close'])) { $byDay[$od][] = ['00:00','23:59']; continue; }
            $cd = isset($p['close']['day']) ? (int)$p['close']['day'] : $od;
            $ct = $fmtTime($p['close']); if ($ct===null) continue;
            if ($cd === $od) $byDay[$od][] = [$ot,$ct];
            else { $byDay[$od][] = [$ot,'23:59']; $byDay[$cd][] = ['00:00',$ct]; } // overnight
        }
    } elseif (is_array($wkdesc)) {
        // (Optional) parse weekdayDescriptions if you want – safe to skip if not needed
    }

    // If we found NO intervals at all, treat as "unknown": return empty
    $any = false;
    foreach ($byDay as $slots) { if (!empty($slots)) { $any = true; break; } }
    if (!$any) return $res;

    // Build compact + OpeningHoursSpecification
    $abbrs = ['Mo','Tu','We','Th','Fr','Sa','Su'];
    $days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $order = [1,2,3,4,5,6,0]; // Mon..Sun (Google index mapping)

    $schemaParts = [];
    $groups = []; // "HH:MM-HH:MM" => days[]

    foreach ($order as $i => $gDay) {
        if (!empty($byDay[$gDay])) {
            [$from,$to] = $byDay[$gDay][0]; // first slot only (simple)
            $schemaParts[] = $abbrs[$i].' '.$from.'-'.$to;
            $groups["$from-$to"][] = $days[$i];
        }
    }

    foreach ($groups as $range => $dows) {
        [$opens,$closes] = explode('-', $range);
        $res['openingHoursSpecification'][] = [
            '@type'=>'OpeningHoursSpecification',
            'dayOfWeek'=>$dows,'opens'=>$opens,'closes'=>$closes
        ];
    }

    $res['compact'] = implode(' ', $schemaParts);
    return $res;
}

function ci_build_schema(array $ci, array $gbp_primary = [], array $google_info = [], array $opts = []): array {
    $opts += [
        'include_aggregate_rating' => true,
        'min_rating_value'         => 4.0,   // gate AR unless rating >= this
    ];

    // -------- basics --------
    $url  = get_bloginfo('url');
    $name = $ci['name'] ?? get_bloginfo('name');
    $bt   = $ci['business-type'] ?? '';
    $type = is_array($bt) ? ($bt[0] ?? '') : $bt;
    $type = trim($type) === '' ? 'LocalBusiness' : preg_replace('/\s+/', '', $type);

    // bump to HVAC when site-type demands it
    if (!empty($ci['site-type']) && strtolower((string)$ci['site-type']) === 'hvac') {
        $type = 'HVACBusiness';
    }

    // email / phone (E.164-ish)
    $email  = $ci['email'] ?? null;
    $digits = fn($v) => preg_replace('/\D+/', '', (string)$v);
    $a = $digits($ci['area'] ?? '');
    $p = $digits($ci['phone'] ?? '');
    if ($a === '' && strlen($p) === 10) { $a = substr($p,0,3); $p = substr($p,3); } // recover 10-digit paste
    $telephone = (strlen($a) === 3 && strlen($p) === 7) ? ('+1'.$a.$p) : ( ($a.$p) ? '+1'.$a.$p : null );

    // address
    $addr = array_filter([
        'streetAddress'   => $ci['street']     ?? '',
        'addressLocality' => $ci['city']       ?? '',
        'addressRegion'   => $ci['state-abbr'] ?? ($ci['state-full'] ?? ''),
        'postalCode'      => $ci['zip']        ?? '',
        'addressCountry'  => 'US',
    ], fn($v) => $v !== '');
    $addressNode = $addr ? (['@type'=>'PostalAddress'] + $addr) : null;

    // geo
    $geo = (isset($ci['lat'], $ci['long']) && $ci['lat'] !== '' && $ci['long'] !== '')
        ? ['@type'=>'GeoCoordinates','latitude'=>(float)$ci['lat'],'longitude'=>(float)$ci['long']]
        : null;

    // hours (compact + OpeningHoursSpecification) — build FIRST, add to $schema AFTER we create it
    $hours = ci_build_hours($ci['hours']['periods'] ?? null, $ci['current-hours'] ?? null);

    // social -> sameAs
    $same = [];
    foreach (['facebook','instagram','linkedin','twitter','youtube','pinterest'] as $s) {
        if (!empty($ci[$s])) $same[] = $ci[$s];
    }

    // areaServed from service-areas
    $areaServed = [];
    if (!empty($ci['service-areas']) && is_array($ci['service-areas'])) {
        $seen = [];
		$ci['service-areas'][] = array($ci['city'],$ci['state-full']);
        foreach ($ci['service-areas'] as $city) {
            if (!isset($city[0], $city[1])) continue;
            $c = preg_replace('/\s+/', ' ', trim((string)$city[0]));
            $st= preg_replace('/\s+/', ' ', trim((string)$city[1]));
            if ($c === '' || $st === '') continue;
            $label = "$c, $st";
            if (isset($seen[$label])) continue;
            $seen[$label] = 1;
            $citySlug  = rawurlencode(str_replace(' ','_',$c));
            $stateSlug = rawurlencode(str_replace(' ','_',$st));
            $areaServed[] = [
                '@type'  => 'AdministrativeArea',
                'name'   => $label,
                'sameAs' => "https://en.wikipedia.org/wiki/{$citySlug},_{$stateSlug}",
            ];
        }
    }

    // hasOfferCatalog from service-names (+ a catalog name)
    $catalogName = 'Our Services';
    if (!empty($ci['site-type']) && strtolower((string)$ci['site-type']) === 'hvac') {
        $catalogName = 'HVAC Services';
    } elseif (is_array($bt) && !empty($bt[1])) {
        $catalogName = trim((string)$bt[1]);
    }
    $serviceNames = array_values(array_unique(array_filter(array_map('trim', (array)($ci['service-names'] ?? [])))));
    if (empty($serviceNames)) $serviceNames = [$catalogName];

    $offers = [];
    foreach ($serviceNames as $svc) {
        if ($svc === '') continue;
        $offers[] = [
            '@type'       => 'Offer',
            'itemOffered' => [
                '@type'    => 'Service',
                'name'     => $svc,
                'provider' => ['@id' => home_url('#organization')],
            ],
        ];
    }
    $hasOfferCatalog = $offers ? [
        '@type'           => 'OfferCatalog',
        '@id'             => home_url('#offer-catalog'),
        'name'            => $catalogName,
        'itemListElement' => $offers,
    ] : null;

    // logo (optional)
    $logo = null;
    if (!empty($ci['logo'])) {
        // allow either string URL or ImageObject
        $logo = is_array($ci['logo']) ? $ci['logo'] : ['@type'=>'ImageObject','url'=>$ci['logo']];
    }

    // additionalType (optional)
    $additionalType = '';
    if (function_exists('ci_additional_type_url')) {
        $friendly = is_array($bt) ? ($bt[1] ?? '') : (string)$bt;
        $additionalType = ci_additional_type_url($type, $friendly, $ci['site-type'] ?? '');
    }

    // aggregateRating from $google_info (prime PID)
    if (empty($google_info)) {
        $google_info = get_option('bp_gbp_update') ?: [];
    }
    $aggregateRating = null;
    if ($opts['include_aggregate_rating'] && !empty($ci['pid'])) {
        $pids  = is_array($ci['pid']) ? $ci['pid'] : [$ci['pid']];
        $prime = $pids[0] ?? null;
        if ($prime && isset($google_info[$prime])) {
            $rv = (float)($google_info[$prime]['google-rating']  ?? 0);
            $rc = (int)  ($google_info[$prime]['google-reviews'] ?? 0);
            if ($rc > 0 && $rv >= $opts['min_rating_value']) {
                $aggregateRating = [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => round($rv, 1),
                    'bestRating'  => 5,
                    'worstRating' => 1,
                    'ratingCount' => $rc,
                ];
            }
        }
    }

    // priceRange & hasMap
    $priceRange = $ci['price-range'] ?? '$$';
    $hasMap     = (!empty($ci['cid'])) ? ('https://maps.google.com/?cid=' . rawurlencode((string)$ci['cid'])) : null;

    // -------- assemble --------
    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => $type,
        'name'       => $name,
        'url'        => $url,
        'priceRange' => $priceRange,
    ];
    if ($email)         $schema['email'] = $email;
    if ($telephone)     $schema['telephone'] = $telephone;
    if ($addressNode)   $schema['address'] = $addressNode;
    if ($geo)           $schema['geo'] = $geo;

    // ADD HOURS HERE (so we don't overwrite them later)
    if (!empty($hours['openingHoursSpecification'])) {
        $schema['openingHoursSpecification'] = $hours['openingHoursSpecification'];
        if (!empty($hours['compact'])) {
            $schema['openingHours'] = $hours['compact']; // optional helper string
        }
    }

    if ($same)             $schema['sameAs'] = $same;
    if ($areaServed)       $schema['areaServed'] = $areaServed;
    if ($hasOfferCatalog)  $schema['hasOfferCatalog'] = $hasOfferCatalog;
    if ($aggregateRating)  $schema['aggregateRating'] = $aggregateRating;
    if ($logo)             $schema['logo'] = $logo;
    if ($additionalType)   $schema['additionalType'] = $additionalType;
    if ($hasMap)           $schema['hasMap'] = $hasMap;

    return $schema;
}