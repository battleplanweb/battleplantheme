<?php
/* Battle Plan Web Design Jobsite GEO */

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Admin
# Register Custom Post Types
# Setup Advanced Custom Fields
# Basic Site Setup
# Shortcodes
# Setup Re-directs
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Admin
--------------------------------------------------------------*/

function bp_cleanup_duplicate_attachments($delete = false) {

	// 1️⃣ Get all jobsite_geo post IDs
	$jobsite_ids = get_posts([
		'post_type'      => 'jobsite_geo',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);

	if (!$jobsite_ids) {
		error_log('No jobsite_geo posts found');
		return;
	}

	// 2️⃣ Get only attachments attached to jobsite_geo posts
	$attachments = get_posts([
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_parent__in'=> $jobsite_ids,
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);

	$hash_map = [];

	foreach ($attachments as $aid) {

		$file = get_attached_file($aid);
		if (!$file || !file_exists($file)) continue;

		$hash = md5_file($file);

		if (!isset($hash_map[$hash])) {
			$hash_map[$hash] = $aid;
			continue;
		}

		$keep = $hash_map[$hash];
		$kill = $aid;

		error_log("DUPLICATE JOBSITE IMAGE FOUND");
		error_log("KEEP: " . get_attached_file($keep) . " (ID {$keep})");
		error_log("KILL: " . get_attached_file($kill) . " (ID {$kill})");

		if ($delete) {
			wp_delete_attachment($kill, true);
			error_log("DELETED attachment {$kill}");
		}
	}
}

if ( get_option('bp_jobsite_photos_housekeeping_2026_01_30') !=="completed" ) :
	//bp_cleanup_duplicate_attachments(false);
	bp_cleanup_duplicate_attachments(true);
	updateOption( 'bp_jobsite_photos_housekeeping_2026_01_30', 'completed', false );
endif;

// Format location
function bp_format_location($location) {
	$splitLoc = explode('-', $location);
	$townParts = array_slice($splitLoc, 0, -1);
    $jobsite_town = ucwords(implode(' ', $townParts));

    $jobsite_town = preg_replace_callback('/\bMc[a-z]/i', function($matches) {
        return 'Mc' . strtoupper($matches[0][2]);
    }, $jobsite_town);

	$jobsite_state = strtoupper(end($splitLoc));

	return $jobsite_town . ', ' . $jobsite_state;
}

// Format service
function bp_format_service($service) {
	$jobsite_service = ucwords(str_replace('-', ' ', $service));
	$jobsite_service = str_replace('Hvac', 'HVAC', $jobsite_service);
	$jobsite_service = $jobsite_service === "Service Area" ? "Recent Jobsites" : $jobsite_service; return $jobsite_service;
 }

 // Match up reviews and jobsites
 function bp_match_key_from_title($title) {
	$key = sanitize_title(trim((string)$title));
	return $key ?: '';
}

// Orient uploaded photos correctly
add_filter('wp_handle_upload_prefilter', function($file) {

	if (!empty($GLOBALS['bp_skip_exif_rotation'])) {
	    return $file;
	}

	if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
	    return $file;
	}

	$mime = $file['type'] ?? '';
	if (!str_starts_with($mime, 'image/')) {
	    return $file;
	}

	// Read EXIF safely
	$exif = @exif_read_data($file['tmp_name']);
	if (empty($exif['Orientation']) || (int)$exif['Orientation'] === 1) {
	    return $file;
	}

	$image = wp_get_image_editor($file['tmp_name']);
	if (is_wp_error($image)) {
	    return $file;
	}

	switch ((int)$exif['Orientation']) {
	    case 3:
		   $image->rotate(180);
		   break;
	    case 6:
		   $image->rotate(-90);
		   break;
	    case 8:
		   $image->rotate(90);
		   break;
	    default:
		   return $file;
	}

	// Save rotated image back to same temp file
	$saved = $image->save($file['tmp_name']);
	if (is_wp_error($saved)) {
	    return $file;
	}

	return $file;

 });


function bp_jobsite_setup($post_id, $user) {
	$current_type = get_post_type($post_id);
	if (!in_array($current_type, ['jobsite_geo', 'testimonials'], true)) return;

	static $processed = [];
	if (isset($processed[$post_id])) {
		return; // already processed this post in this execution
	}
	$processed[$post_id] = true;

	static $geo_opts = null;
	$geo_opts ??= get_option('jobsite_geo');

	$customer_info = customer_info();
	$current_user = wp_get_current_user();

	$location    = '';
	$tech_tag    = '';
	$new_address = '';

	// Ensure match key exists for this post
	$key = get_post_meta($post_id, '_bp_match_key', true);

	if (!$key) {
		if (!function_exists('bp_match_key_from_title')) {
			function bp_match_key_from_title($title) {
				return sanitize_title(strtolower(preg_replace('/[^a-z0-9]/i', '', $title)));
			}
		}

		$key = bp_match_key_from_title(get_the_title($post_id));
		update_post_meta($post_id, '_bp_match_key', $key);
	}

	// Sync jobs with reviews when saving a testimonial, then exit
	if ($current_type === 'testimonials') {
		$jobsites = get_posts([
			'post_type'      => 'jobsite_geo',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [[
				'key'   => '_bp_match_key',
				'value' => $key,
			]],
		]);
		foreach ($jobsites as $j) update_post_meta($j->ID, 'review', $post_id);
		return;
	}

	// At this point we know it is jobsite_geo, so attempt to link back to a testimonial
	$match = get_posts([
		'post_type'      => 'testimonials',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => [[
			'key'   => '_bp_match_key',
			'value' => $key,
		]],
	]);
	!empty($match) ? update_post_meta($post_id, 'review', $match[0]->ID) : null;

	// Find the longitude and latitude based on customer address
	$address = trim((string) get_field('address', $post_id));
	$city    = trim((string) get_field('city', $post_id));
	$state   = strtoupper(trim((string) get_field('state', $post_id)));
	$zip     = trim((string) get_field('zip', $post_id));

	$stateAbbrs = [
		"Alabama" => "AL", "Alaska" => "AK", "Arizona" => "AZ", "Arkansas" => "AR", "California" => "CA",
		"Colorado" => "CO", "Connecticut" => "CT", "Delaware" => "DE", "Florida" => "FL", "Georgia" => "GA",
		"Hawaii" => "HI", "Idaho" => "ID", "Illinois" => "IL", "Indiana" => "IN", "Iowa" => "IA", "Kansas" => "KS",
		"Kentucky" => "KY", "Louisiana" => "LA", "Maine" => "ME", "Maryland" => "MD", "Massachusetts" => "MA",
		"Michigan" => "MI", "Minnesota" => "MN", "Mississippi" => "MS", "Missouri" => "MO", "Montana" => "MT",
		"Nebraska" => "NE", "Nevada" => "NV", "New Hampshire" => "NH", "New Jersey" => "NJ", "New Mexico" => "NM",
		"New York" => "NY", "North Carolina" => "NC", "North Dakota" => "ND", "Ohio" => "OH", "Oklahoma" => "OK",
		"Oregon" => "OR", "Pennsylvania" => "PA", "Rhode Island" => "RI", "South Carolina" => "SC",
		"South Dakota" => "SD", "Tennessee" => "TN", "Texas" => "TX", "Utah" => "UT", "Vermont" => "VT",
		"Virginia" => "VA", "Washington" => "WA", "West Virginia" => "WV", "Wisconsin" => "WI", "Wyoming" => "WY",
		"Tex" => "TX", "Calif" => "CA", "Penn" => "PA"
	];

	foreach ($stateAbbrs as $name => $abbreviation) {
		if ($state === strtoupper($name)) $state = $abbreviation;
	}

	if ($address !== '' && $city !== '' && $state !== '' && $zip !== '') {
		$new_address = $address . ', ' . $city . ', ' . $state . ' ' . $zip;

		// check last saved full address
		$last_address = get_post_meta($post_id, '_last_geocode_address', true);

		if ($new_address !== $last_address && defined('_PLACES_API') && _PLACES_API) {
			$fail_key = 'geocode_fail_' . md5($new_address);
			if (get_transient($fail_key)) {
				// Skip geocoding temporarily if a recent failure was logged
				return;
			}

			$googleAPI = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($new_address) . '&key=' . _PLACES_API;
			$response = wp_remote_get($googleAPI, ['timeout' => 10]);

			if (is_wp_error($response)) {
				$email_label = $customer_info['name'] ?? get_bloginfo('name');
				emailMe('Geocoding HTTP Error - ' .  $email_label, $response->get_error_message());
				set_transient($fail_key, true, HOUR_IN_SECONDS * 6);
			} else {
				$http_code = wp_remote_retrieve_response_code($response);
				$body      = wp_remote_retrieve_body($response);
				$data      = json_decode($body, true);

				if ($http_code !== 200 || !is_array($data)) {
					$html = 'HTTP ' . $http_code . '<br><br>Raw body:<br><pre>' . esc_html($body) . '</pre>';
					$email_label = $customer_info['name'] ?? get_bloginfo('name');
					emailMe('Geocoding Bad Response - ' .  $email_label, $html);
					set_transient($fail_key, true, HOUR_IN_SECONDS * 6);
				} elseif (($data['status'] ?? '') === 'OK') {
					update_post_meta($post_id, 'geocode', $data['results'][0]['geometry']['location']);
					update_post_meta($post_id, '_last_geocode_address', $new_address);
					delete_transient($fail_key);
				} else {
					$err  = ($data['error_message'] ?? 'No error_message returned.');
					$html = $new_address . '<br><br>Status: ' . esc_html($data['status']) . '<br>Error: ' . esc_html($err);
					$email_label = $customer_info['name'] ?? get_bloginfo('name');
					emailMe('Geocoding API Error - ' . $email_label, $html);
					set_transient($fail_key, true, HOUR_IN_SECONDS * 6);
				}
			}
		}
	}

	// Generate service, location and technician tags

	// add city-state as location tag
	if ($city && $state) {
		$location = sanitize_title(ucwords($city) . '-' . strtoupper($state));

		$term = term_exists($location, 'jobsite_geo-service-areas');
		if (empty($term)) $term = wp_insert_term($location, 'jobsite_geo-service-areas');
		if (!is_wp_error($term)) wp_set_post_terms($post_id, [$location], 'jobsite_geo-service-areas', false);
	}

	// add username as technician tag
	if (in_array('bp_jobsite_geo', $current_user->roles, true)) {
		$tech_tag = sanitize_title($current_user->user_firstname . '-' . $current_user->user_lastname);
	} else {
		$hcp_tech = get_post_meta($post_id, '_hcp_tech_name', true);
		if ($hcp_tech) $tech_tag = sanitize_title(str_replace(' ', '-', $hcp_tech));
	}

	if ($tech_tag) {
		$term = term_exists($tech_tag, 'jobsite_geo-techs');
		if (empty($term)) $term = wp_insert_term($tech_tag, 'jobsite_geo-techs');
		if (!is_wp_error($term)) wp_set_post_terms($post_id, [$tech_tag], 'jobsite_geo-techs', false);
	}

	// scan for keywords for service tag
	$service     = '';
	$description = strtolower(get_post_field('post_content', $post_id));

	$btRaw = $customer_info['business-type'] ?? null;
	if (is_array($btRaw)) {
		$btVal  = $btRaw[0] ?? '';
		$btServ = $btRaw[1] ?? '';
	} else {
		$btVal  = (string) $btRaw;
		$btServ = '';
	}

	// Customize for General Contractor website
	if (strtolower($btVal) === 'generalcontractor') {
		foreach (['gutter', 'seamless'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'gutters';
				break;
			}
		}

		foreach (['insulation', 'insulate', 'fiberglass'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'insulation';
				break;
			}
		}
	}

	// Customize for Roofing Contractor website
	if (strtolower($btVal) === 'roofingcontractor') {
		$service = 'roofing';

		$type = str_contains($description, 'repair') ? '-repair' :  '-installation';
		$service .= $type;

		foreach (['gutter'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'gutters';
				break;
			}
		}
	}

	// Customize for Plumber website
	if (strtolower($btVal) === 'plumber') {
		$service = 'plumbing-services';

		$type = str_contains($description, 'repair') || str_contains($description, 'service') ? '-repair' :
			(str_contains($description, 'install') || str_contains($description, 'replace') ? '-installation' :
			(esc_attr(get_field('new_brand')) ? '-installation' : '-repair'));

		foreach (['water heater', 'tank'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'water-heater' . $type;
				break;
			}
		}

		foreach (['drain', 'clog', 'clogged', 'blockage'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'clogged-drains';
				break;
			}
		}

		foreach (['bathroom', 'toilet', 'tub', 'shower'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'bathroom-plumbing-services';
				break;
			}
		}

		foreach (['kitchen', 'dish washer', 'refrigerator', 'fridge', 'ice maker'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'kitchen-plumbing-services';
				break;
			}
		}

		foreach (['gas line', 'gas service', 'gas leak'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'gas-line' . $type;
				break;
			}
		}

		foreach (['water pressure', 'pressure reducing valve', 'prv'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'pressure-reducing-valves';
				break;
			}
		}

		foreach (['lighting', 'gas lamps', 'lantern'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'outdoor-lighting' . $type;
				break;
			}
		}
	}

	// Customize for HVAC website
	if (($customer_info['site-type'] ?? '') === 'hvac') {
		$equipment = [
			'air-conditioner' 		=> ['air conditioner', 'air conditioning', 'cooling', 'a/c', 'ac', 'compressor', 'evaporator', 'condenser', 'drain line', 'refrigerant'],
			'heating'         		=> ['heater', 'heating', 'furnace'],
			'hvac'            		=> ['hvac', 'fan motor', 'blower', 'mini split'],
		];

		$type = str_contains($description, 'repair') || str_contains($description, 'service') || str_contains($description, 'replace') ? '-repair' :  '-installation';

		foreach (['duct cleaning', 'airflow', 'air flow'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'air-duct-cleaning';
				break;
			}
		}

		foreach (['dryer vent'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'dryer-vent-cleaning';
				break;
			}
		}

		foreach (['allergies', 'indoor air', 'air quality', 'clean air', 'air purifi'] as $keyword) {
			if (stripos($description, $keyword) !== false) {
				$service = 'indoor-air-quality';
				break;
			}
		}

		if (!$service) {
			foreach (['maintenance', 'tune up', 'tune-up', 'check up', 'check-up', 'inspection'] as $keyword) {
				if (stripos($description, $keyword) !== false) {
					$service = 'hvac-maintenance';
					break;
				}
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
	}

	$service = $service ?: 'service-area';

	if ($service && $location) {
		wp_set_object_terms(
			$post_id,
			[$service . '--' . strtolower($location)],
			'jobsite_geo-services',
			false
		);
	}

	// Handle the photos uploaded to the jobsite
	foreach ([
		'jobsite_photo_1' => 'jobsite_photo_1_alt',
		'jobsite_photo_2' => 'jobsite_photo_2_alt',
		'jobsite_photo_3' => 'jobsite_photo_3_alt',
		'jobsite_photo_4' => 'jobsite_photo_4_alt',
	] as $img => $cap) {
		$aid = (int) get_field($img, $post_id);
		$alt = trim((string) get_field($cap, $post_id));
		$aid ? update_post_meta($aid, '_wp_attachment_image_alt', $alt ?: get_the_title($post_id)) : null;
	}

	// set first uploaded pic as jobsite thumbnail
	$thumb_id = (int) get_field('jobsite_photo_1', $post_id);
	$thumb_id ? set_post_thumbnail($post_id, $thumb_id) : null;

	// Send email when jobsite post is updated or created
	$post_obj = get_post($post_id);
	if ($post_obj) {
		$created  = new DateTime($post_obj->post_date_gmt);
		$modified = new DateTime($post_obj->post_modified_gmt);
		$diff     = $created->diff($modified);
		$seconds  = ((($diff->y * 365.25 + $diff->m * 30 + $diff->d) * 24 + $diff->h) * 60 + $diff->i) * 60 + $diff->s;
		$action   = $seconds <= 2 ? 'created' : 'updated';
	} else {
		$action = 'updated';
	}

	$notifyTo = !empty($geo_opts['notify']) && $geo_opts['notify'] !== 'false'
		? $geo_opts['notify']
		: '';

	$notifyBc = !empty($geo_opts['copy_me']) && $geo_opts['copy_me'] === 'true'
		? 'info@battleplanwebdesign.com'
		: '';

	if ($notifyTo === '' && $notifyBc !== '') {
		$notifyTo = $notifyBc;
		$notifyBc = '';
	}

	$display_user = '';

	if ($user === 'user') {
		$current_user = wp_get_current_user();
		if ($current_user && $current_user->ID) {
			$display_user = trim($current_user->first_name . ' ' . $current_user->last_name);
			if ($display_user === '') $display_user = $current_user->user_login;
		} else {
			$display_user = 'System';
		}
	} else {
		$display_user = $user;
	}

	if ($notifyTo !== '') {
		$subject  = 'Jobsite ' . $action . ' by ' . $display_user;
		$message  = $display_user . ' ' . $action . ' a jobsite post' . ($new_address !== '' ? ' for this address: ' . $new_address . '.' : '.');
		$headers  = [];
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$domain = parse_url(home_url(), PHP_URL_HOST);
		$headers[] = 'From: Website Administrator <noreply@' . $domain . '>';
		$headers[] = 'Reply-To: noreply@' . $domain;
		if ($notifyBc) $headers[] = 'Bcc: <' . $notifyBc . '>';

		wp_mail($notifyTo, $subject, $message, $headers);
	}
}

// Save important info to meta data upon publishing or updating post
add_action('save_post', 'battleplan_saveJobsite', 10, 3);
function battleplan_saveJobsite($post_id, $post, $update) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (!in_array(get_post_type($post_id), ['jobsite_geo','testimonials'], true)) return;

    // Skip if webhook already ran setup in this same request
    if (!empty($GLOBALS['bp_jobsite_setup_running'])) return;

    bp_jobsite_setup($post_id, 'user');

	if ( get_option('bp_setup_2025_09_29') !== "completed" ) {
		$testimonials = get_posts(['post_type'=>'testimonials','posts_per_page'=>-1,'post_status'=>'publish']);
		$t_index = [];
		foreach ($testimonials as $t) {
			$key = bp_match_key_from_title($t->post_title);
			update_post_meta($t->ID, '_bp_match_key', $key);
			$t_index[$key] = $t->ID;
		}

		$jobsites = get_posts(['post_type'=>'jobsite_geo','posts_per_page'=>-1,'post_status'=>'publish']);

		foreach ($jobsites as $j){
			$key = bp_match_key_from_title($j->post_title);
			update_post_meta($j->ID, '_bp_match_key', $key);
			isset($t_index[$key]) ? update_post_meta($j->ID, 'review', $t_index[$key]) : null;
		}

		foreach ($jobsites as $jid){
			$pairs = [
				'jobsite_photo_1' => 'jobsite_photo_1_alt',
				'jobsite_photo_2' => 'jobsite_photo_2_alt',
				'jobsite_photo_3' => 'jobsite_photo_3_alt',
				'jobsite_photo_4' => 'jobsite_photo_4_alt',
			];
			foreach ($pairs as $img => $cap) {
				$aid = (int) ( function_exists('get_field') ? get_field($img, $jid) : get_post_meta($jid, $img, true) );
				if (!$aid) continue;

				$current_alt = (string) get_post_meta($aid, '_wp_attachment_image_alt', true);
				if (stripos($current_alt, 'Jobsite Geo') === 0) {
					$alt = trim((string) ( function_exists('get_field') ? get_field($cap, $jid) : get_post_meta($jid, $cap, true) ));
					update_post_meta($aid, '_wp_attachment_image_alt', $alt ?: get_the_title($jid));
				}
			}
		}


		updateOption( 'bp_setup_2025_09_29', 'completed', false );
	}
}

// add drop-down to view specific landing pages
add_action('restrict_manage_posts', 'battleplan_view_jobsite_geo_pages');
function battleplan_view_jobsite_geo_pages() {
	global $typenow;

	if ($typenow === 'jobsite_geo') {
		$terms = get_terms(array(
			'taxonomy' => 'jobsite_geo-services',
			'hide_empty' => false,
		));
		$selected_value = isset($_GET['view_jobsite_geo_pages']) ? $_GET['view_jobsite_geo_pages'] : '';
		?>
		<select name="view_jobsite_geo_pages" id="view_jobsite_geo_pages">
			<option value=""><?php _e('View Jobsite GEO Pages', 'battleplan'); ?></option>
			<?php foreach ($terms as $term) {
				$parts = explode('--', esc_html($term->name));

				if (count($parts) === 2) {
					$service_part = bp_format_service($parts[0]);
					$location_part = bp_format_location($parts[1]);
					$formatted_title = $service_part . ' in ' . $location_part;
				}?>
				<option value="<?php echo esc_url(get_term_link($term)); ?>"><?php echo $formatted_title; ?></option>
			<?php } ?>
		</select>
		<?php
	}
}


/*--------------------------------------------------------------
# Register Custom Post Types
--------------------------------------------------------------*/
add_action( 'init', 'battleplan_registerJobsiteGEOPostType', 0 );
function battleplan_registerJobsiteGEOPostType() {
	register_post_type( 'jobsite_geo', array (
		'label'=>				__( 'jobsite_geo', 'battleplan' ),
		'labels'=>array(
			'name'=>			_x( 'Jobsites', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>	_x( 'Jobsite', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>				true,
		'publicly_queryable'=>	true,
		'exclude_from_search'=>	false,
		'show_in_nav_menus'=>	true,
		'supports'=>			array( 'title', 'editor', 'thumbnail', 'custom-fields', 'post-formats' ),
		'hierarchical'=>		false,
		'menu_position'=>		20,
		'menu_icon'=>			'dashicons-location',
		'has_archive'=>			true,
		'capability_type' => 	'post',
		'capabilities' => array(
			'create_posts' => 	false,
		),
		'map_meta_cap' => true,
		'rewrite' => array(
            'slug' => 			'jobsites',
            'with_front' => 	false,
        ),
	));

	$taxonomies = [
		'jobsite_geo-techs'         => ['Technicians', 'Technician', 'tech'],
		'jobsite_geo-services'      => ['Services', 'Service', 'service'],
		'jobsite_geo-service-areas' => ['Service Areas', 'Service Area', 'service-area']
	];

	foreach ($taxonomies as $slug => [$plural, $singular, $rewrite]) {
		register_taxonomy($slug, ['jobsite_geo'], [
			'labels' => [
				'name' => _x($plural, 'Taxonomy General Name', 'text_domain'),
				'singular_name' => _x($singular, 'Taxonomy Singular Name', 'text_domain'),
				'menu_name' => $plural,
			],
			'public' => true,
			'show_ui' => true,
			'show_admin_column' => true,
			'hierarchical' => false,
			'rewrite' => ['slug' => $rewrite, 'with_front' => false],
		]);
	}
	//wp_insert_term( 'upcoming', 'event-tags' );
	//wp_insert_term( 'expired', 'event-tags' );
	//wp_insert_term( 'featured', 'event-tags' );
}

add_filter( 'register_post_type_args', 'battleplan_changeJobsiteGEOCaps' , 10, 2 );
function battleplan_changeJobsiteGEOCaps( $args, $post_type ) {
    if ( $post_type !== 'jobsite_geo'  ) return $args;

    $args['capabilities'] = array(
		'publish_posts'					=> 'publish_jobsites',
		'edit_posts'					=> 'edit_jobsites',
		'delete_posts'					=> 'delete_jobsites',
		'edit_others_posts'				=> 'edit_others_jobsites',
		'delete_others_posts'			=> 'delete_others_jobsites',
		'edit_published_posts'			=> 'edit_published_jobsites',
		'delete_published_posts'		=> 'delete_published_jobsites',
		'read_private_posts'			=> 'read_private_jobsites',
		'edit_private_posts'			=> 'edit_private_jobsites',
		'delete_private_posts'			=> 'delete_private_jobsites',
		'copy_posts'					=> 'copy_jobsites',
    );

    return $args;
}


/*--------------------------------------------------------------
# Setup Advanced Custom Fields
--------------------------------------------------------------*/
add_action('acf/init', 'battleplan_add_jobsite_geo_acf_fields');
function battleplan_add_jobsite_geo_acf_fields() {
	$get_jobsite_geo = get_option('jobsite_geo');
	$media_library = $get_jobsite_geo['media_library'] == 'limited' ? 'uploadedTo' : 'all';
	$default_state = $get_jobsite_geo['default_state'] != '' ? $get_jobsite_geo['default_state'] : '';

	acf_add_local_field_group( array(
		'key' => 'group_jobsite_details',
		'title' => 'Jobsite Details',
		'fields' => array(
			array(
				'key' => 'field_date',
				'label' => 'Date',
				'name' => 'job_date',
				'aria-label' => '',
				'type' => 'date_picker',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '15%',
					'class' => '',
					'id' => '',
				),
				'display_format' => 'Y-m-d',
				'return_format' => 'Y-m-d',
				'first_day' => 0,
			),
			array(
				'key' => 'field_address',
				'label' => 'Address',
				'name' => 'address',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '45%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_city',
				'label' => 'City',
				'name' => 'city',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '23%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_state',
				'label' => 'State',
				'name' => 'state',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'default_value' => $default_state,
				'wrapper' => array(
					'width' => '7%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_zip',
				'label' => 'Zip Code',
				'name' => 'zip',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 1,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '10%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_photo_1',
				'label' => 'Photo #1',
				'name' => 'jobsite_photo_1',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => $media_library,
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'preview_size' => 'third-s',
			),
			array(
				'key' => 'field_photo_2',
				'label' => 'Photo #2',
				'name' => 'jobsite_photo_2',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => $media_library,
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'preview_size' => 'third-s',
			),
			array(
				'key' => 'field_photo_3',
				'label' => 'Photo #3',
				'name' => 'jobsite_photo_3',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => $media_library,
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'preview_size' => 'third-s',
			),
			array(
				'key' => 'field_photo_4',
				'label' => 'Photo #4',
				'name' => 'jobsite_photo_4',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => $media_library,
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'preview_size' => 'third-s',
			),
			array(
				'key' => 'field_caption_1',
				'label' => 'Caption #1',
				'name' => 'jobsite_photo_1_alt',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_caption_2',
				'label' => 'Caption #2',
				'name' => 'jobsite_photo_2_alt',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_caption_3',
				'label' => 'Caption #3',
				'name' => 'jobsite_photo_3_alt',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_caption_4',
				'label' => 'Caption #4',
				'name' => 'jobsite_photo_4_alt',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'maxlength' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_priority_job',
				'label' => 'Prioritize this job in listings.',
				'name' => 'is_priority_job',
				'type' => 'checkbox',
				'instructions' => '',
				'required' => 0,
				'choices' => array(
					'1' => 'High Priority Job',
				),
				'default_value' => array(),
				'allow_custom' => 0,
				'save_custom' => 0,
				'toggle' => 0,
				'return_format' => 'value',
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_review_link',
				'label' => 'Link To Review',
				'name' => 'review',
				'aria-label' => '',
				'type' => 'post_object',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'post_type' => array(
					0 => 'testimonials',
				),
				'post_status' => array(
					0 => 'publish',
				),
				'taxonomy' => '',
				'return_format' => 'id',
				'multiple' => 0,
				'allow_null' => 1,
				'ui' => 0,
				'bidirectional' => 0,
				'bidirectional_target' => array(),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'jobsite_geo',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));
}

add_filter('acf/validate_value', function($valid, $value, $field, $input) {
	// Only run for your caption fields
	$caption_fields = array(
		'field_caption_1' => 'field_photo_1',
		'field_caption_2' => 'field_photo_2',
		'field_caption_3' => 'field_photo_3',
		'field_caption_4' => 'field_photo_4',
	);

	if (!isset($caption_fields[$field['key']])) {
		return $valid;
	}

	$photo_key = $caption_fields[$field['key']];
	$photo_val = $_POST['acf'][$photo_key] ?? '';

	// If photo exists but caption is empty → error
	if ($photo_val && trim((string)$value) === '') {
		return 'Caption is required when a photo is provided.';
	}

	return $valid;

}, 10, 4);

add_action('add_attachment', function($attachment_id) {

    $post = get_post($attachment_id);
    if (!$post || $post->post_type !== 'attachment') return;

    $parent = (int) $post->post_parent;
    if (!$parent || get_post_type($parent) !== 'jobsite_geo') return;

    $taxonomy = 'image-categories';
    $term_name = 'Jobsite GEO';

    $term = term_exists($term_name, $taxonomy);
    if (!$term) $term = wp_insert_term($term_name, $taxonomy);

    if (!is_wp_error($term)) {
        wp_set_object_terms($attachment_id, (int)$term['term_id'], $taxonomy, true);
    }
});

add_action('admin_enqueue_scripts', function($hook) {
	// Only load in post editor or ACF screen
	if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

	// Only load for jobsite_geo post type
	$screen = get_current_screen();
	if ($screen && $screen->post_type !== 'jobsite_geo') return;

	bp_enqueue_script( 'battleplan-jobsite-geo-admin', 'script-jobsite_geo-admin' );

	wp_localize_script('battleplan-jobsite-geo-admin', 'bpRotate', [
		'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce'   => wp_create_nonce('bp_rotate_image')
	]);
});


add_action('wp_ajax_bp_rotate_image', function() {
    check_ajax_referer('bp_rotate_image', 'nonce');

    $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
    if (!$attachment_id) wp_send_json_error('Missing attachment ID');

    $file = get_attached_file($attachment_id);
    if (!file_exists($file)) wp_send_json_error('File missing');

   $image = wp_get_image_editor($file);
	if (is_wp_error($image)) wp_send_json_error('Image editor error');

	$GLOBALS['bp_skip_exif_rotation'] = true;

	// Rotate 90 degrees clockwise
	$image->rotate(-90);

	// Save back to original file
	$saved = $image->save($file);
    if (is_wp_error($saved)) wp_send_json_error('Save failed');

    // Regenerate all media sizes
    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file));

    wp_send_json_success([
        'filename' => basename($file)
    ]);
});



/*--------------------------------------------------------------
# Basic Site Setup
--------------------------------------------------------------*/

add_filter( 'body_class', function( $classes ) {
	if ( is_post_type_archive('jobsite_geo') || is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') || is_tax('jobsite_geo-techs') ) {
		$classes = str_replace(array('sidebar-line', 'sidebar-right', 'sidebar-left'), 'sidebar-none', $classes);
		return array_merge( $classes, array( 'jobsite_geo' ) );
	}
	return $classes;
}, 30);

add_action( 'pre_get_posts', 'battleplan_override_jobsite_query', 10 );
function battleplan_override_jobsite_query( $query ) {
	if (!is_admin() && $query->is_main_query()) {
		if ( is_post_type_archive('jobsite_geo') || is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') || is_tax('jobsite_geo-techs') ) {
			$query->set( 'post_type','jobsite_geo');
			$query->set( 'posts_per_page', 30);
			$query->set( 'meta_key', 'job_date' );
        	$query->set( 'orderby', 'meta_value' );
        	$query->set( 'order', 'DESC');
		}
	}
}

add_filter('template_include', 'battleplan_jobsite_template');
function battleplan_jobsite_template($template) {
    if ( is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') || is_tax('jobsite_geo-techs') ) {
		$template = get_template_directory().'/archive-jobsite_geo.php';
		$sep = ' · ';
        $jobsite_term = get_queried_object();
		$GLOBALS['jobsite_geo-service'] = get_option('jobsite_geo')['default_service'];

		if ($jobsite_term) {
			$jobsite_service  = '';
			$jobsite_location = $jobsite_term->name;

			if (is_tax('jobsite_geo-services')) {
				$term_parts = explode('--', $jobsite_term->name);
				$jobsite_service  = $term_parts[0] ?? '';
				$jobsite_location = $term_parts[1] ?? $jobsite_term->name;

				$GLOBALS['jobsite_geo-service'] = bp_format_service($jobsite_service);
			}

			$GLOBALS['jobsite_geo-city-state'] = bp_format_location($jobsite_location);
			$splitLoc = explode(', ', $GLOBALS['jobsite_geo-city-state']);
			$GLOBALS['jobsite_geo-city'] = $splitLoc[0];
			$GLOBALS['jobsite_geo-state'] = $splitLoc[1];
		}

		if ( is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') ) {
			$GLOBALS['jobsite_geo-headline'] = $GLOBALS['jobsite_geo-service'].' in '.$GLOBALS['jobsite_geo-city-state'];
            $GLOBALS['jobsite_geo-page_title'] = $GLOBALS['jobsite_geo-headline'].$sep.get_bloginfo('name');

		  // [get-service default="" air-conditioner-repair="" heating-repair="" air-conditioner-installation="" hvac-maintenance="" indoor-air-quality=""]

			$service_full = [
							"Air Conditioner Installation",
							"Heating Installation",
							"Air Conditioner Repair",
							"Heating Repair",
							"HVAC Maintenance",
							"Air Duct Cleaning",
							"Indoor Air Quality",
							"Plumbing Services"];
			$service_short = ["Recent air conditioner replacements in the ".$GLOBALS['jobsite_geo-city']." area.",
							  $GLOBALS['jobsite_geo-city']." homes where we recently installed new heating equipment.",
							  "We have recently repaired air conditioners for these ".$GLOBALS['jobsite_geo-city']." customers.",
							  "Recent heating system repairs for customers living in ".$GLOBALS['jobsite_geo-city'].".",
							  $GLOBALS['jobsite_geo-city']." customers that recently trusted us with their HVAC service.",
							  "Homes in ".$GLOBALS['jobsite_geo-city']." where we recently cleaned and/or repaired air ducts.",
							  "We've improved the indoor air quality for these ".$GLOBALS['jobsite_geo-city']." customers.",
							  "Plumbing issues we have recently solved for ".$GLOBALS['jobsite_geo-city']." homes."];

			if (in_array($GLOBALS['jobsite_geo-service'], $service_full)) {
				$index = array_search($GLOBALS['jobsite_geo-service'], $service_full);
				$GLOBALS['jobsite_geo-map-caption'] = $service_short[$index];
			} else {
				$GLOBALS['jobsite_geo-map-caption'] = "Recent ".$GLOBALS['jobsite_geo-service']." in ".$GLOBALS['jobsite_geo-city'].", ".$GLOBALS['jobsite_geo-state'];
			}

			$plural = ( stripos($GLOBALS['jobsite_geo-service'], 'services') === false && stripos($GLOBALS['jobsite_geo-service'], 'maintenance') === false && stripos($GLOBALS['jobsite_geo-service'], 'air quality') === false ) ? 's' : '';

			$GLOBALS['jobsite_geo-bottom-headline'] = "Recent ".$GLOBALS['jobsite_geo-service'].$plural." In ".$GLOBALS['jobsite_geo-city'];

			$query = bp_WP_Query('landing', [
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'title'          => $GLOBALS['jobsite_geo-city'] . ', ' . $GLOBALS['jobsite_geo-state']
			]);

			if($query->have_posts()) {
				while($query->have_posts()) {
					$query->the_post();
					$GLOBALS['jobsite_geo-content'] = apply_filters('the_content', get_the_content());
					$GLOBALS['jobsite_geo-page_title'] = str_replace('%%sep%%', $sep, $GLOBALS['jobsite_geo-service'] ).' in '.$GLOBALS['jobsite_geo-city'].", ".$GLOBALS['jobsite_geo-state'].$sep.get_bloginfo('name');
					$GLOBALS['jobsite_geo-page_desc'] = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true);
					$GLOBALS['mapGrid'] = "3-2";
				}
			} else {
					$query = bp_WP_Query('landing', [
					'posts_per_page' => 1,
					'name'           => 'jobsite-geo-default',
					'post_status'    => 'publish'
				]);

				if ($query->have_posts()) {
					while($query->have_posts()) {
						$query->the_post();
						$GLOBALS['jobsite_geo-content'] = apply_filters('the_content', get_the_content());
						$GLOBALS['jobsite_geo-page_title'] = str_replace('%%sep%%', $sep, $GLOBALS['jobsite_geo-service'] ).' in '.$GLOBALS['jobsite_geo-city'].", ".$GLOBALS['jobsite_geo-state'].$sep.get_bloginfo('name');
						$GLOBALS['jobsite_geo-page_desc'] = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true);
						$GLOBALS['mapGrid'] = "3-2";
					}
				} else {
					$GLOBALS['jobsite_geo-content'] = '';
					$GLOBALS['mapGrid'] = "1";
				}
			}
			wp_reset_postdata();

		} elseif (is_tax('jobsite_geo-techs')) {
			$tech_name = ucwords(str_replace('-', ' ', $jobsite_term->name));

			$GLOBALS['jobsite_geo-headline']   = $tech_name . '\'s Recent Jobs';
			$GLOBALS['jobsite_geo-page_title'] = $GLOBALS['jobsite_geo-headline'] . $sep . get_bloginfo('name');
			$GLOBALS['jobsite_geo-page_desc']  = ''; // set something so wpseo_metadesc has a defined value
			$GLOBALS['jobsite_geo-map-caption'] = 'This map shows the location of some of ' . $tech_name . '\'s recent work.';
		}

		add_filter('wpseo_title', function($title) {
			return !empty($GLOBALS['jobsite_geo-page_title'])
				? $GLOBALS['jobsite_geo-page_title']
				: $title;
		}, 20);

		add_filter('wpseo_metadesc', function($description) {
			return isset($GLOBALS['jobsite_geo-page_desc']) && $GLOBALS['jobsite_geo-page_desc'] !== ''
				? $GLOBALS['jobsite_geo-page_desc']
				: $description;
		}, 20);

    }

    return $template;
}

// rename files for jobsite geo images
add_filter('wp_handle_upload_prefilter', 'battleplan_handle_jobsite_geo_image_upload');
function battleplan_handle_jobsite_geo_image_upload($file) {
	$current_user = wp_get_current_user();
	$post   = null;
	$post_id = 0;

	if (isset($_REQUEST['post_id'])) {
		$post_id = (int) $_REQUEST['post_id'];
		$post    = get_post($post_id);
	}

	if (($post && $post->post_type === 'jobsite_geo') || in_array('bp_jobsite_geo_mgr', $current_user->roles, true) || in_array('bp_jobsite_geo', $current_user->roles, true)) {
		if ($post_id) $file['name'] = 'jobsite_geo-' . $post_id . '--' . $file['name'];
	}

	return $file;
}

// remove empty service tags
add_action('save_post_jobsite_geo', 'bp_cleanup_empty_service_tags');
add_action('deleted_post', 'bp_cleanup_empty_service_tags');
function bp_cleanup_empty_service_tags() {

	$taxonomy = 'jobsite_geo-services';

	$terms = get_terms([
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
	]);

	if (is_wp_error($terms) || empty($terms)) return;

	foreach ($terms as $term) {

		$count = get_posts([
			'post_type'      => 'jobsite_geo',
			'post_status'    => ['publish','draft','pending'],
			'tax_query'      => [[
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			]],
			'fields'         => 'ids',
			'posts_per_page' => 1,
		]);

		if (empty($count)) {
			wp_delete_term($term->term_id, $taxonomy);
		}
	}
}



add_filter('wpseo_schema_organization', function ($data) {
	$city  = $GLOBALS['jobsite_geo-city'] ?? '';
	if (!$city) return $data;

	$parts = explode('-', sanitize_title($city));

	if (!empty($data['areaServed']) && is_array($data['areaServed'])) {
		$data['areaServed'] = array_values(array_filter(
			$data['areaServed'],
			function ($area) use ($parts) {

				if (empty($area['name'])) return false;

				$nameSlug = sanitize_title($area['name']);

				return isset($parts[0]) && strpos($nameSlug, $parts[0]) !== false;
			}
		));
	}

	return $data;
});



/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

add_shortcode( 'get-jobsite', 'battleplan_getJobsiteCityState' );
function battleplan_getJobsiteCityState($atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', ), $atts );
	$type = esc_attr($a['type']);

	if ( $type == 'city' ) return trim($GLOBALS['jobsite_geo-city']);
	if ( $type == 'state' ) return trim($GLOBALS['jobsite_geo-state']);
}

// Determine which verbiage to use based on service
add_shortcode( 'get-service', 'battleplan_getService' );
function battleplan_getService($atts, $content = null) {
	foreach ($atts as $key => $value) {
		if (sanitize_key($key) === sanitize_title($GLOBALS['jobsite_geo-service'])) return wp_kses_post($value);
	}

	return isset($atts['default']) ? wp_kses_post($atts['default']) : $GLOBALS['jobsite_geo-service'];
}

/*--------------------------------------------------------------
# Setup Re-directs
--------------------------------------------------------------*/
add_action('template_redirect', 'battleplan_jobsite_geo_intercept');
function battleplan_jobsite_geo_intercept() {
	$uri_path = strtok($_SERVER['REQUEST_URI'], '?'); // strip query string
	$uri_path = trim($uri_path, '/');                 // remove leading/trailing slashes

	if ($uri_path === '') return;

	$parts    = explode('/', $uri_path);
	$uri_slug = $parts[0] ?? '';

	if (!$uri_slug) return;

	$term = get_term_by('slug', $uri_slug, 'jobsite_geo-service-areas');
	if ($term && !is_wp_error($term)) {
		wp_safe_redirect(home_url("/service-area/{$uri_slug}/"), 301);
		exit;
	}
}

/* --------------------------------------------------------------
   JOBSITE GEO SCORING SYSTEM (RUNTIME WITH BREAKDOWN NOTES)
-------------------------------------------------------------- */

function bp_jobsite_runtime_score($post_id, &$notes = []) {

	$points = 0;

	// Review attached
	if (get_field('review', $post_id)) {
		$points += 25;
		$notes[] = '25-review';
	}

	// Priority job
	if (get_field('is_priority_job', $post_id)) {
		$points += 25;
		$notes[] = '25-priority';
	}

	// Description quality
	$desc = trim(strip_tags(get_post_field('post_content', $post_id)));
	$len  = strlen($desc);

	if ($len >= 300) { $points += 15; $notes[] = '15-desc300+'; }
	elseif ($len >= 150) { $points += 10; $notes[] = '10-desc150+'; }
	elseif ($len >= 75) { $points += 5; $notes[] = '5-desc75+'; }
	else { $points -= 10; $notes[] = '-10-descTooShort'; }

	// Keyword weighting
	$keywords = ['repair','replace','install','service','inspection','maintenance'];
	$kw_score = 0;
	foreach ($keywords as $kw) {
		if (stripos($desc, $kw) !== false) $kw_score += 2;
	}
	$kw_score = min($kw_score, 10);
	if ($kw_score > 0) $notes[] = "{$kw_score}-keywords";
	$points += $kw_score;

	// Photos
	$photo_points = 0;
	foreach (['jobsite_photo_1','jobsite_photo_2','jobsite_photo_3','jobsite_photo_4'] as $ph) {
		if (get_field($ph, $post_id)) $photo_points += 10;
	}
	if ($photo_points > 0) $notes[] = "{$photo_points}-photos";
	$points += $photo_points;

	// Age bonus
	$days = floor((time() - get_post_time('U', true, $post_id)) / DAY_IN_SECONDS);
	$age_bonus =
		($days <= 3 ? 50 :
		($days <= 7 ? 25 :
		($days <= 30 ? 10 : 0)));

	if ($age_bonus > 0) $notes[] = "{$age_bonus}-age({$days}days)";
	$points += $age_bonus;

	return $points;
}


/* Score jobsites on archive queries and attach comment strings. */
add_filter('the_posts', function($posts, $query) {

	if (is_admin() || !$query->is_main_query()) return $posts;

	if (
		is_post_type_archive('jobsite_geo') ||
		is_tax('jobsite_geo-service-areas') ||
		is_tax('jobsite_geo-services') ||
		is_tax('jobsite_geo-techs')
	) {
		foreach ($posts as $p) {

			$notes = [];
			$score = bp_jobsite_runtime_score($p->ID, $notes);

			$p->bp_score = $score;

			// Nicely formatted versions
			$p->bp_score_notes   = implode('; ', $notes);
			$p->bp_score_comment = '// ' . implode('; ', $notes);
		}

		usort($posts, function($a, $b) {
			return ($b->bp_score ?? 0) <=> ($a->bp_score ?? 0);
		});
	}

	return $posts;

}, 10, 2);