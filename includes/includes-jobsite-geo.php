<?php
require_once get_template_directory() . '/prompts/prompts-jobsite-geo.php';

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
# AI Integration
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Admin
--------------------------------------------------------------*/

function bp_cleanup_duplicate_attachments($delete = false) {

	// Get all jobsite_geo post IDs
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

	// Get only attachments attached to jobsite_geo posts
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
	$jobsite_service = $jobsite_service === "Service Area" ? "Recent Jobsites" : $jobsite_service;
	return $jobsite_service;
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

	$exif = @exif_read_data($file['tmp_name']);
	if (empty($exif['Orientation']) || (int)$exif['Orientation'] === 1) {
		return $file;
	}

	$image = wp_get_image_editor($file['tmp_name']);
	if (is_wp_error($image)) {
		return $file;
	}

	switch ((int)$exif['Orientation']) {
		case 3: $image->rotate(180); break;
		case 6: $image->rotate(-90); break;
		case 8: $image->rotate(90);  break;
		default: return $file;
	}

	$saved = $image->save($file['tmp_name']);
	if (is_wp_error($saved)) {
		return $file;
	}

	return $file;
});

// Convert uploaded images to WebP at 80%, resize to 1000px max side.
// Uses wp_handle_upload (post-filter) — fires after WP has moved the file to the
// uploads directory, where it has a real filename/extension and can be freely modified.
add_filter('wp_handle_upload', function($upload, $context) {

	if (defined('_USER_LOGIN') && _USER_LOGIN === 'battleplanweb') {
		return $upload;
	}

	if (empty($upload['file']) || !file_exists($upload['file'])) {
		return $upload;
	}

	if (!str_starts_with($upload['type'] ?? '', 'image/')) {
		return $upload; // not an image
	}

	$image = wp_get_image_editor($upload['file']);
	if (is_wp_error($image)) {
		return $upload;
	}

	// Resize so the longest side is ≤ 1000px (no upscaling).
	$size = $image->get_size();
	$needs_resize = !is_wp_error($size) && !empty($size['width']) && !empty($size['height'])
	                && max($size['width'], $size['height']) > 1000;

	if ($needs_resize) {
		$w = (int) $size['width'];
		$h = (int) $size['height'];
		$scale = 1000 / max($w, $h);
		$image->resize((int) round($w * $scale), (int) round($h * $scale), false);
	}

	$image->set_quality(80);

	if ($upload['type'] === 'image/webp') {
		// Already WebP — only re-save if we resized
		if ($needs_resize) {
			$image->save($upload['file'], 'image/webp');
		}
		return $upload;
	}

	// Convert non-WebP to WebP
	if (!$image->supports_mime_type('image/webp')) {
		return $upload; // server doesn't support WebP output — leave as-is
	}

	$webp_file = preg_replace('/\.[^.\/]+$/', '.webp', $upload['file']);
	$webp_url  = preg_replace('/\.[^.\/]+$/', '.webp', $upload['url']);

	$saved = $image->save($webp_file, 'image/webp');
	if (is_wp_error($saved)) {
		return $upload;
	}

	// Remove the original (jpg/png/etc) now that WebP is saved
	if ($webp_file !== $upload['file']) {
		@unlink($upload['file']);
	}

	$upload['file'] = $webp_file;
	$upload['url']  = $webp_url;
	$upload['type'] = 'image/webp';

	return $upload;

}, 10, 2);


function bp_jobsite_setup($post_id, $user) {
	$current_type = get_post_type($post_id);
	if (!in_array($current_type, ['jobsite_geo', 'testimonials'], true)) return;

	static $processed = [];
	if (isset($processed[$post_id])) return;
	$processed[$post_id] = true;

	static $geo_opts = null;
	$geo_opts ??= get_option('jobsite_geo');

	$customer_info = customer_info();
	$current_user  = wp_get_current_user();

	$location    = '';
	$new_address = '';

	// Ensure match key exists for this post
	$key = get_post_meta($post_id, '_bp_match_key', true);
	if (!$key) {
		$key = bp_match_key_from_title(get_the_title($post_id));
		if (!$key) return; // bail if title produces no usable match key — prevents empty-value meta_query matching all posts
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

	// Link jobsite back to matching testimonial
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

	// Find lat/lng based on customer address
     // ── Geocoding: use GPS coords from app if available, else Google API ──
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

     // Check if GPS coordinates came from the Battle Plan app
     $bp_lat = get_post_meta($post_id, 'bp_geo_lat', true);
     $bp_lon = get_post_meta($post_id, 'bp_geo_lon', true);

     if ($bp_lat && $bp_lon) {
          // ── GPS coords from app — skip Google API entirely ────────
          update_post_meta($post_id, 'geocode', [
               'lat' => (float) $bp_lat,
               'lng' => (float) $bp_lon,
          ]);
          $new_address = $address . ', ' . $city . ', ' . $state . ' ' . $zip;
          update_post_meta($post_id, '_last_geocode_address', $new_address);

     } elseif ($address !== '' && $city !== '' && $state !== '' && $zip !== '') {
          // ── No GPS — fall back to Google API as before ────────────
          $new_address  = $address . ', ' . $city . ', ' . $state . ' ' . $zip;
          $last_address = get_post_meta($post_id, '_last_geocode_address', true);

          if ($new_address !== $last_address && defined('_PLACES_API') && _PLACES_API) {
               $fail_key = 'geocode_fail_' . md5($new_address);
               if (get_transient($fail_key)) return;

               $googleAPI = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($new_address) . '&key=' . _PLACES_API;
               $response  = wp_remote_get($googleAPI, ['timeout' => 10]);

               if (is_wp_error($response)) {
                    $email_label = $customer_info['name'] ?? get_bloginfo('name');
                    emailMe('Geocoding HTTP Error - ' . $email_label, $response->get_error_message());
                    set_transient($fail_key, true, HOUR_IN_SECONDS * 6);
               } else {
                    $http_code = wp_remote_retrieve_response_code($response);
                    $body      = wp_remote_retrieve_body($response);
                    $data      = json_decode($body, true);

                    if ($http_code !== 200 || !is_array($data)) {
                         $html = 'HTTP ' . $http_code . '<br><br>Raw body:<br><pre>' . esc_html($body) . '</pre>';
                         $email_label = $customer_info['name'] ?? get_bloginfo('name');
                         emailMe('Geocoding Bad Response - ' . $email_label, $html);
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

	// Add city-state as location tag (jobsite_geo-service-areas)
	if ($city && $state) {
		$location = sanitize_title(ucwords($city) . '-' . strtoupper($state));

		$term = term_exists($location, 'jobsite_geo-service-areas');
		if (empty($term)) $term = wp_insert_term($location, 'jobsite_geo-service-areas');
		if (!is_wp_error($term)) wp_set_post_terms($post_id, [$location], 'jobsite_geo-service-areas', false);
	}

	// NOTE: jobsite_geo-services taxonomy is now handled exclusively by the AI
	// integration (bp_geo_assign_taxonomy_term). The legacy keyword-based
	// classifier has been removed to prevent conflicts.

	// Handle photos uploaded to the jobsite
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

	// Set first uploaded pic as jobsite thumbnail
	$thumb_id = (int) get_field('jobsite_photo_1', $post_id);
	$thumb_id ? set_post_thumbnail($post_id, $thumb_id) : null;

	// Notification email is now sent from bp_geo_run_ai_rewrite() after the AI
	// finishes — not here — so that only fully-completed posts trigger a notification.
}

// Save important info to meta data upon publishing or updating post
add_action('save_post', 'battleplan_saveJobsite', 10, 3);
function battleplan_saveJobsite($post_id, $post, $update) {

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (defined('DOING_AJAX') && DOING_AJAX) return;
	if (!in_array(get_post_type($post_id), ['jobsite_geo', 'testimonials'], true)) return;

	if (!empty($GLOBALS['bp_jobsite_setup_running'])) return;

	bp_jobsite_setup($post_id, 'user');

	if ( get_option('bp_setup_2025_09_29') !== "completed" ) {
		$testimonials = get_posts(['post_type' => 'testimonials', 'posts_per_page' => -1, 'post_status' => 'publish']);
		$t_index = [];
		foreach ($testimonials as $t) {
			$key = bp_match_key_from_title($t->post_title);
			update_post_meta($t->ID, '_bp_match_key', $key);
			$t_index[$key] = $t->ID;
		}

		$jobsites = get_posts(['post_type' => 'jobsite_geo', 'posts_per_page' => -1, 'post_status' => 'publish']);

		foreach ($jobsites as $j) {
			$key = bp_match_key_from_title($j->post_title);
			update_post_meta($j->ID, '_bp_match_key', $key);
			isset($t_index[$key]) ? update_post_meta($j->ID, 'review', $t_index[$key]) : null;
		}

		foreach ($jobsites as $jid) {
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

// Add drop-down to view specific landing pages
add_action('restrict_manage_posts', 'battleplan_view_jobsite_geo_pages');
function battleplan_view_jobsite_geo_pages() {
	global $typenow;

	if ($typenow === 'jobsite_geo') {
		$terms = get_terms([
			'taxonomy'   => 'jobsite_geo-services',
			'hide_empty' => false,
		]);
		$selected_value = isset($_GET['view_jobsite_geo_pages']) ? $_GET['view_jobsite_geo_pages'] : '';
		?>
		<select name="view_jobsite_geo_pages" id="view_jobsite_geo_pages">
			<option value=""><?php _e('View Jobsite GEO Pages', 'battleplan'); ?></option>
			<?php foreach ($terms as $term) {
				$parts = explode('--', $term->slug);
				if (count($parts) === 2) {
					$service_part  = bp_format_service($parts[0]);
					$location_part = bp_format_location($parts[1]);
					$formatted_title = $service_part . ' in ' . $location_part;
				} else {
					$formatted_title = $term->name;
				} ?>
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
	register_post_type( 'jobsite_geo', [
		'label'   => __( 'jobsite_geo', 'battleplan' ),
		'labels'  => [
			'name'          => _x( 'Jobsites', 'Post Type General Name', 'battleplan' ),
			'singular_name' => _x( 'Jobsite', 'Post Type Singular Name', 'battleplan' ),
		],
		'public'             => true,
		'publicly_queryable' => true,
		'exclude_from_search'=> false,
		'show_in_nav_menus'  => true,
		'supports'           => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'post-formats' ],
		'hierarchical'       => false,
		'menu_position'      => 20,
		'menu_icon'          => 'dashicons-location',
		'has_archive'        => true,
		'capability_type'    => 'post',
		'capabilities'       => [ 'create_posts' => 'publish_jobsites' ],
		'map_meta_cap'       => true,
		'rewrite'            => [ 'slug' => 'jobsites', 'with_front' => false ],
		'show_in_rest' 	 => true,
		'rest_base'    	 => 'jobsite_geo',
	]);

	$taxonomies = [
		'jobsite_geo-service-types'  => ['Service Types',  'Service Type',  'service-type'],
		'jobsite_geo-service-areas'  => ['Service Areas',  'Service Area',  'service-area'],
		'jobsite_geo-services'       => ['Services',       'Service',       'service'],
	];

	foreach ($taxonomies as $slug => [$plural, $singular, $rewrite]) {
		register_taxonomy($slug, ['jobsite_geo'], [
			'labels' => [
				'name'          => _x($plural, 'Taxonomy General Name', 'text_domain'),
				'singular_name' => _x($singular, 'Taxonomy Singular Name', 'text_domain'),
				'menu_name'     => $plural,
			],
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => false,
			'rewrite'           => ['slug' => $rewrite, 'with_front' => false],
		]);
	}
}

add_filter( 'register_post_type_args', 'battleplan_changeJobsiteGEOCaps', 10, 2 );
function battleplan_changeJobsiteGEOCaps( $args, $post_type ) {
	if ( $post_type !== 'jobsite_geo' ) return $args;

	$args['capabilities'] = [
		'publish_posts'         => 'publish_jobsites',
		'edit_posts'            => 'edit_jobsites',
		'delete_posts'          => 'delete_jobsites',
		'edit_others_posts'     => 'edit_others_jobsites',
		'delete_others_posts'   => 'delete_others_jobsites',
		'edit_published_posts'  => 'edit_published_jobsites',
		'delete_published_posts'=> 'delete_published_jobsites',
		'read_private_posts'    => 'read_private_jobsites',
		'edit_private_posts'    => 'edit_private_jobsites',
		'delete_private_posts'  => 'delete_private_jobsites',
		'copy_posts'            => 'copy_jobsites',
	];

	return $args;
}


/*--------------------------------------------------------------
# Setup Advanced Custom Fields
--------------------------------------------------------------*/
add_action('acf/init', 'battleplan_add_jobsite_geo_acf_fields');
function battleplan_add_jobsite_geo_acf_fields() {
	$get_jobsite_geo = get_option('jobsite_geo');
	$media_library   = $get_jobsite_geo['media_library'] == 'limited' ? 'uploadedTo' : 'all';
	$default_state   = $get_jobsite_geo['default_state'] != '' ? $get_jobsite_geo['default_state'] : '';

	acf_add_local_field_group([
		'key'   => 'group_jobsite_details',
		'title' => 'Jobsite Details',
		'fields' => [
			[
				'key'            => 'field_date',
				'label'          => 'Date',
				'name'           => 'job_date',
				'type'           => 'date_picker',
				'required'       => 1,
				'wrapper'        => ['width' => '15%', 'class' => '', 'id' => ''],
				'display_format' => 'Y-m-d',
				'return_format'  => 'Y-m-d',
				'first_day'      => 0,
			],
			[
				'key'      => 'field_address',
				'label'    => 'Address',
				'name'     => 'address',
				'type'     => 'text',
				'required' => 1,
				'wrapper'  => ['width' => '45%', 'class' => '', 'id' => ''],
			],
			[
				'key'      => 'field_city',
				'label'    => 'City',
				'name'     => 'city',
				'type'     => 'text',
				'required' => 1,
				'wrapper'  => ['width' => '23%', 'class' => '', 'id' => ''],
			],
			[
				'key'           => 'field_state',
				'label'         => 'State',
				'name'          => 'state',
				'type'          => 'text',
				'required'      => 1,
				'default_value' => $default_state,
				'wrapper'       => ['width' => '7%', 'class' => '', 'id' => ''],
			],
			[
				'key'      => 'field_zip',
				'label'    => 'Zip Code',
				'name'     => 'zip',
				'type'     => 'text',
				'required' => 1,
				'wrapper'  => ['width' => '10%', 'class' => '', 'id' => ''],
			],
			[
				'key'          => 'field_photo_1',
				'label'        => 'Photo #1',
				'name'         => 'jobsite_photo_1',
				'type'         => 'image',
				'required'     => 0,
				'return_format'=> 'id',
				'library'      => $media_library,
				'preview_size' => 'third-s',
			],
			[
				'key'          => 'field_photo_2',
				'label'        => 'Photo #2',
				'name'         => 'jobsite_photo_2',
				'type'         => 'image',
				'required'     => 0,
				'return_format'=> 'id',
				'library'      => $media_library,
				'preview_size' => 'third-s',
			],
			[
				'key'          => 'field_photo_3',
				'label'        => 'Photo #3',
				'name'         => 'jobsite_photo_3',
				'type'         => 'image',
				'required'     => 0,
				'return_format'=> 'id',
				'library'      => $media_library,
				'preview_size' => 'third-s',
			],
			[
				'key'          => 'field_photo_4',
				'label'        => 'Photo #4',
				'name'         => 'jobsite_photo_4',
				'type'         => 'image',
				'required'     => 0,
				'return_format'=> 'id',
				'library'      => $media_library,
				'preview_size' => 'third-s',
			],
			[
				'key'      => 'field_caption_1',
				'label'    => 'Caption #1',
				'name'     => 'jobsite_photo_1_alt',
				'type'     => 'text',
				'required' => 0,
			],
			[
				'key'      => 'field_caption_2',
				'label'    => 'Caption #2',
				'name'     => 'jobsite_photo_2_alt',
				'type'     => 'text',
				'required' => 0,
			],
			[
				'key'      => 'field_caption_3',
				'label'    => 'Caption #3',
				'name'     => 'jobsite_photo_3_alt',
				'type'     => 'text',
				'required' => 0,
			],
			[
				'key'      => 'field_caption_4',
				'label'    => 'Caption #4',
				'name'     => 'jobsite_photo_4_alt',
				'type'     => 'text',
				'required' => 0,
			],
			[
				'key'          => 'field_priority_job',
				'label'        => 'Prioritize this job in listings.',
				'name'         => 'is_priority_job',
				'type'         => 'checkbox',
				'required'     => 0,
				'choices'      => ['1' => 'High Priority Job'],
				'default_value'=> [],
				'allow_custom' => 0,
				'save_custom'  => 0,
				'toggle'       => 0,
				'return_format'=> 'value',
			],
			[
				'key'          => 'field_review_link',
				'label'        => 'Link To Review',
				'name'         => 'review',
				'type'         => 'post_object',
				'required'     => 0,
				'post_type'    => ['testimonials'],
				'post_status'  => ['publish'],
				'return_format'=> 'id',
				'multiple'     => 0,
				'allow_null'   => 1,
				'ui'           => 0,
			],
		],
		'location' => [
			[[ 'param' => 'post_type', 'operator' => '==', 'value' => 'jobsite_geo' ]],
		],
		'menu_order'          => 0,
		'position'            => 'normal',
		'style'               => 'default',
		'label_placement'     => 'top',
		'instruction_placement'=> 'label',
		'active'              => true,
		'show_in_rest'        => 0,
	]);
}

add_filter('acf/validate_value', function($valid, $value, $field, $input) {
	$caption_fields = [
		'field_caption_1' => 'field_photo_1',
		'field_caption_2' => 'field_photo_2',
		'field_caption_3' => 'field_photo_3',
		'field_caption_4' => 'field_photo_4',
	];

	if (!isset($caption_fields[$field['key']])) return $valid;

	$photo_key = $caption_fields[$field['key']];
	$photo_val = $_POST['acf'][$photo_key] ?? '';

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

	$taxonomy  = 'image-categories';
	$term_name = 'Jobsite GEO';
	$term      = term_exists($term_name, $taxonomy);
	if (!$term) $term = wp_insert_term($term_name, $taxonomy);

	if (!is_wp_error($term)) {
		wp_set_object_terms($attachment_id, (int)$term['term_id'], $taxonomy, true);
	}
});

add_action('admin_enqueue_scripts', function($hook) {
	if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

	$screen = get_current_screen();
	if ($screen && $screen->post_type !== 'jobsite_geo') return;

	bp_enqueue_script( 'battleplan-jobsite-geo-admin', 'script-jobsite_geo-admin' );

	wp_localize_script('battleplan-jobsite-geo-admin', 'bpRotate', [
		'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce'   => wp_create_nonce('bp_rotate_image'),
	]);

	wp_add_inline_script('battleplan-jobsite-geo-admin', '
		document.addEventListener("DOMContentLoaded", function() {

			// Move AI Description meta box to just below the Publish box
			var aiBox      = document.getElementById("bp_geo_ai_rewrite");
			var publishBox = document.getElementById("submitdiv");
			if (aiBox && publishBox && publishBox.parentNode) {
				publishBox.parentNode.insertBefore(aiBox, publishBox.nextSibling);
			}

			// Insert Customer First & Last Name label inside #titlewrap
			var titleWrap = document.getElementById("titlewrap");
			if (titleWrap) {
				var label = document.createElement("div");
				label.className = "jobsite_geo-title";
				label.textContent = "Jobsite Customer (First & Last Name)";
				titleWrap.insertBefore(label, titleWrap.firstChild);
			}

			// Insert prompts above the editor
			var contentWrap = document.getElementById("wp-content-wrap");
			if (contentWrap) {
				var prompts = document.createElement("div");
				prompts.className = "jobsite_geo-prompts";
				prompts.innerHTML =
					"<span>Why did the customer call?</span>" +
					"<span>What was the problem?</span>" +
					"<span>How did you fix it?</span>";
				contentWrap.parentNode.insertBefore(prompts, contentWrap);

				// Voice to text button — inserted at top of #wp-content-wrap (sits in padding-top space)
				var voiceBtn = document.createElement("button");
				voiceBtn.type      = "button";
				voiceBtn.id        = "bp-geo-voice-btn";
				voiceBtn.className = "button";
				voiceBtn.textContent = "Voice to Text";
				contentWrap.insertBefore(voiceBtn, contentWrap.firstChild);

				var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
				if (SpeechRec) {
					var recognition = new SpeechRec();
					recognition.continuous      = true;
					recognition.interimResults  = false;
					recognition.lang            = "en-US";
					var listening = false;

					recognition.onresult = function(event) {
						var transcript = "";
						for (var i = event.resultIndex; i < event.results.length; i++) {
							if (event.results[i].isFinal) {
								transcript += event.results[i][0].transcript + " ";
							}
						}
						var editor = typeof tinymce !== "undefined" && tinymce.get("content");
						if (editor && !editor.isHidden()) {
							editor.insertContent(transcript);
						} else {
							var ta = document.getElementById("content");
							if (ta) {
								var pos = ta.selectionStart;
								ta.value = ta.value.slice(0, pos) + transcript + ta.value.slice(pos);
								ta.selectionStart = ta.selectionEnd = pos + transcript.length;
							}
						}
					};

					recognition.onerror = function() {
						voiceBtn.textContent = "Voice to Text";
						voiceBtn.classList.remove("bp-geo-voice-active");
						listening = false;
					};

					recognition.onend = function() {
						if (listening) recognition.start();
					};

					voiceBtn.addEventListener("click", function() {
						if (!listening) {
							recognition.start();
							listening = true;
							voiceBtn.textContent = "Stop Listening";
							voiceBtn.classList.add("bp-geo-voice-active");
						} else {
							recognition.stop();
							listening = false;
							voiceBtn.textContent = "Voice to Text";
							voiceBtn.classList.remove("bp-geo-voice-active");
						}
					});
				} else {
					voiceBtn.disabled = true;
					voiceBtn.title    = "Speech recognition not supported in this browser";
				}
			}

		});
	');
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
	$image->rotate(-90);

	$saved = $image->save($file);
	if (is_wp_error($saved)) wp_send_json_error('Save failed');

	wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file));

	wp_send_json_success(['filename' => basename($file)]);
});


/*--------------------------------------------------------------
# Basic Site Setup
--------------------------------------------------------------*/

add_filter( 'body_class', function( $classes ) {
	if ( is_post_type_archive('jobsite_geo') || is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') ) {
		$classes = str_replace(['sidebar-line', 'sidebar-right', 'sidebar-left'], 'sidebar-none', $classes);
		return array_merge( $classes, ['jobsite_geo'] );
	}
	return $classes;
}, 30);

add_action( 'pre_get_posts', 'battleplan_override_jobsite_query', 10 );
function battleplan_override_jobsite_query( $query ) {
	if (!is_admin() && $query->is_main_query()) {
		if ( is_post_type_archive('jobsite_geo') || is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') ) {
			$query->set( 'post_type', 'jobsite_geo' );
			$query->set( 'posts_per_page', 30 );
			$query->set( 'meta_key', 'job_date' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', 'DESC' );
		}
	}
}

add_filter('template_include', 'battleplan_jobsite_template');
function battleplan_jobsite_template($template) {
	if ( is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') ) {
		$template = get_template_directory() . '/archive-jobsite_geo.php';
		$sep = ' · ';
		$jobsite_term = get_queried_object();
		$GLOBALS['jobsite_geo-service'] = get_option('jobsite_geo')['default_service'];

		if ($jobsite_term) {
			$jobsite_service  = '';
			$jobsite_location = $jobsite_term->slug;

			if (is_tax('jobsite_geo-services')) {
				// Look up service-type and service-area directly from a tagged post —
				// avoids parsing the slug, which WordPress sanitizes and may alter.
				$sample = get_posts([
					'post_type'      => 'jobsite_geo',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'tax_query'      => [['taxonomy' => 'jobsite_geo-services', 'terms' => $jobsite_term->term_id]],
				]);
				if ($sample) {
					$type_terms = wp_get_post_terms($sample[0], 'jobsite_geo-service-types', ['fields' => 'slugs']);
					$area_terms = wp_get_post_terms($sample[0], 'jobsite_geo-service-areas', ['fields' => 'slugs']);
					if (!empty($type_terms)) $jobsite_service  = $type_terms[0];
					if (!empty($area_terms))  $jobsite_location = $area_terms[0];
				}

				if ($jobsite_service) {
					$GLOBALS['jobsite_geo-service'] = bp_format_service($jobsite_service);
				}
			}

			$GLOBALS['jobsite_geo-city-state'] = bp_format_location($jobsite_location);
			$splitLoc = explode(', ', $GLOBALS['jobsite_geo-city-state']);
			$GLOBALS['jobsite_geo-city']  = $splitLoc[0];
			$GLOBALS['jobsite_geo-state'] = $splitLoc[1];
		}

		if ( is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') ) {
			$GLOBALS['jobsite_geo-headline']   = $GLOBALS['jobsite_geo-service'] . ' in ' . $GLOBALS['jobsite_geo-city-state'];
			$GLOBALS['jobsite_geo-page_title'] = $GLOBALS['jobsite_geo-headline'] . $sep . get_bloginfo('name');

			$service_full = [
				"Air Conditioner Installation",
				"Heating Installation",
				"Air Conditioner Repair",
				"Heating Repair",
				"HVAC Maintenance",
				"Air Duct Cleaning",
				"Indoor Air Quality",
				"Plumbing Services",
			];
			$service_short = [
				"Recent air conditioner replacements in the " . $GLOBALS['jobsite_geo-city'] . " area.",
				$GLOBALS['jobsite_geo-city'] . " homes where we recently installed new heating equipment.",
				"We have recently repaired air conditioners for these " . $GLOBALS['jobsite_geo-city'] . " customers.",
				"Recent heating system repairs for customers living in " . $GLOBALS['jobsite_geo-city'] . ".",
				$GLOBALS['jobsite_geo-city'] . " customers that recently trusted us with their HVAC service.",
				"Homes in " . $GLOBALS['jobsite_geo-city'] . " where we recently cleaned and/or repaired air ducts.",
				"We've improved the indoor air quality for these " . $GLOBALS['jobsite_geo-city'] . " customers.",
				"Plumbing issues we have recently solved for " . $GLOBALS['jobsite_geo-city'] . " homes.",
			];

			$_meta_caption = $jobsite_term ? get_term_meta( $jobsite_term->term_id, 'bp_geo_map_caption', true ) : '';
			if ( $_meta_caption ) {
				$GLOBALS['jobsite_geo-map-caption'] = esc_html( $_meta_caption );
			} elseif (in_array($GLOBALS['jobsite_geo-service'], $service_full)) {
				$index = array_search($GLOBALS['jobsite_geo-service'], $service_full);
				$GLOBALS['jobsite_geo-map-caption'] = $service_short[$index];
			} else {
				$GLOBALS['jobsite_geo-map-caption'] = "Recent " . $GLOBALS['jobsite_geo-service'] . " in " . $GLOBALS['jobsite_geo-city'] . ", " . $GLOBALS['jobsite_geo-state'];
			}

			$plural = ( stripos($GLOBALS['jobsite_geo-service'], 'services') === false && stripos($GLOBALS['jobsite_geo-service'], 'maintenance') === false && stripos($GLOBALS['jobsite_geo-service'], 'air quality') === false ) ? 's' : '';

			$GLOBALS['jobsite_geo-bottom-headline'] = "Recent " . $GLOBALS['jobsite_geo-service'] . $plural . " In " . $GLOBALS['jobsite_geo-city'];

			$term_desc = get_term_meta( $jobsite_term->term_id, 'bp_geo_service_intro', true );
			if ( $term_desc ) {
				$GLOBALS['jobsite_geo-content']   = do_shortcode( wp_kses_post( $term_desc ) );
				$GLOBALS['jobsite_geo-page_desc'] = wp_trim_words( strip_tags( $term_desc ), 30, '...' );
				$GLOBALS['mapGrid']               = '3-2';
			} else {
				$GLOBALS['jobsite_geo-content'] = '';
				$GLOBALS['mapGrid']             = '1';
			}
			$GLOBALS['jobsite_geo-page_title'] = $GLOBALS['jobsite_geo-service'] . ' in ' . $GLOBALS['jobsite_geo-city'] . ', ' . $GLOBALS['jobsite_geo-state'] . $sep . get_bloginfo( 'name' );

		}

		add_filter('wpseo_title', function($title) {
			return !empty($GLOBALS['jobsite_geo-page_title']) ? $GLOBALS['jobsite_geo-page_title'] : $title;
		}, 20);

		add_filter('wpseo_metadesc', function($description) {
			return isset($GLOBALS['jobsite_geo-page_desc']) && $GLOBALS['jobsite_geo-page_desc'] !== '' ? $GLOBALS['jobsite_geo-page_desc'] : $description;
		}, 20);
	}

	return $template;
}

// Rename files for jobsite geo images
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

// Remove empty service tags (both services and service-types)
add_action('save_post_jobsite_geo', 'bp_cleanup_empty_service_tags');
add_action('deleted_post', 'bp_cleanup_empty_service_tags');
function bp_cleanup_empty_service_tags() {
	foreach (['jobsite_geo-services', 'jobsite_geo-service-types'] as $taxonomy) {
		$terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
		if (is_wp_error($terms) || empty($terms)) continue;
		foreach ($terms as $term) {
			$count = get_posts([
				'post_type'      => 'jobsite_geo',
				'post_status'    => ['publish', 'draft', 'pending'],
				'tax_query'      => [['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term->term_id]],
				'fields'         => 'ids',
				'posts_per_page' => 1,
			]);
			if (empty($count)) wp_delete_term($term->term_id, $taxonomy);
		}
	}
}

/**
 * Rebuild jobsite_geo-services (combined terms) from jobsite_geo-service-types × jobsite_geo-service-areas.
 * Called on save and can be called manually for bulk migration.
 */
function bp_geo_sync_services_from_types( $post_id ) {
	$type_slugs = wp_get_post_terms( $post_id, 'jobsite_geo-service-types', ['fields' => 'slugs'] );
	$area_slugs = wp_get_post_terms( $post_id, 'jobsite_geo-service-areas', ['fields' => 'slugs'] );

	if ( is_wp_error($type_slugs) || is_wp_error($area_slugs) || empty($type_slugs) || empty($area_slugs) ) return;

	$service_slugs = [];
	foreach ( $type_slugs as $type ) {
		foreach ( $area_slugs as $area ) {
			$slug = $type . '-' . $area;
			if ( ! term_exists( $slug, 'jobsite_geo-services' ) ) {
				wp_insert_term( $slug, 'jobsite_geo-services' );
			}
			$service_slugs[] = $slug;
		}
	}

	wp_set_post_terms( $post_id, $service_slugs, 'jobsite_geo-services', false );
}

// Rebuild services when service-types or service-areas are changed on a manual save
add_action('save_post_jobsite_geo', function($post_id, $post, $update) {
	if ( wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) ) return;
	if ( ! empty($GLOBALS['bp_jobsite_setup_running']) ) return; // AI flow handles its own assignment
	bp_geo_sync_services_from_types($post_id);
}, 25, 3);

add_filter('wpseo_schema_organization', function ($data) {
	$city = $GLOBALS['jobsite_geo-city'] ?? '';
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
function battleplan_getJobsiteCityState($atts, $content = null) {
	$a    = shortcode_atts(['type' => ''], $atts);
	$type = esc_attr($a['type']);

	if ( $type == 'city'  ) return trim($GLOBALS['jobsite_geo-city']);
	if ( $type == 'state' ) return trim($GLOBALS['jobsite_geo-state']);
}

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
	$uri_path = strtok($_SERVER['REQUEST_URI'], '?');
	$uri_path = trim($uri_path, '/');

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


/*--------------------------------------------------------------
# Jobsite GEO Scoring System
--------------------------------------------------------------*/

function bp_jobsite_runtime_score($post_id, &$notes = []) {
	$points = 0;

	if (get_field('review', $post_id)) {
		$points += 25;
		$notes[] = '25-review';
	}

	if (get_field('is_priority_job', $post_id)) {
		$points += 25;
		$notes[] = '25-priority';
	}

	$desc = trim(strip_tags(get_post_field('post_content', $post_id)));
	$len  = strlen($desc);

	if ($len >= 300)      { $points += 15; $notes[] = '15-desc300+'; }
	elseif ($len >= 150)  { $points += 10; $notes[] = '10-desc150+'; }
	elseif ($len >= 75)   { $points += 5;  $notes[] = '5-desc75+'; }
	else                  { $points -= 10; $notes[] = '-10-descTooShort'; }

	$keywords = ['repair', 'replace', 'install', 'service', 'inspection', 'maintenance'];
	$kw_score = 0;
	foreach ($keywords as $kw) {
		if (stripos($desc, $kw) !== false) $kw_score += 2;
	}
	$kw_score = min($kw_score, 10);
	if ($kw_score > 0) $notes[] = "{$kw_score}-keywords";
	$points += $kw_score;

	$photo_points = 0;
	foreach (['jobsite_photo_1', 'jobsite_photo_2', 'jobsite_photo_3', 'jobsite_photo_4'] as $ph) {
		if (get_field($ph, $post_id)) $photo_points += 10;
	}
	if ($photo_points > 0) $notes[] = "{$photo_points}-photos";
	$points += $photo_points;

	$days = floor((time() - get_post_time('U', true, $post_id)) / DAY_IN_SECONDS);
	$age_bonus =
		($days <= 3  ? 50 :
		($days <= 7  ? 25 :
		($days <= 30 ? 10 : 0)));

	if ($age_bonus > 0) $notes[] = "{$age_bonus}-age({$days}days)";
	$points += $age_bonus;

	return $points;
}

add_filter('the_posts', function($posts, $query) {
	if (is_admin() || !$query->is_main_query()) return $posts;

	if (
		is_post_type_archive('jobsite_geo') ||
		is_tax('jobsite_geo-service-areas') ||
		is_tax('jobsite_geo-services')
	) {
		foreach ($posts as $p) {
			$notes = [];
			$score = bp_jobsite_runtime_score($p->ID, $notes);

			$p->bp_score         = $score;
			$p->bp_score_notes   = implode('; ', $notes);
			$p->bp_score_comment = '// ' . implode('; ', $notes);
		}

		usort($posts, function($a, $b) {
			return ($b->bp_score ?? 0) <=> ($a->bp_score ?? 0);
		});
	}

	return $posts;
}, 10, 2);


/*--------------------------------------------------------------
# One-time Legacy Term Migration
--------------------------------------------------------------*/

add_action( 'init', 'bp_geo_migrate_legacy_term_slugs' );

function bp_geo_migrate_legacy_term_slugs() {
	if ( get_option( 'bp_geo_slug_migration_v1' ) === 'yes' ) return;

	global $wpdb;

	$state_abbrs = [
		'al','ak','az','ar','ca','co','ct','de','fl','ga','hi','id','il','in','ia','ks',
		'ky','la','me','md','ma','mi','mn','ms','mo','mt','ne','nv','nh','nj','nm','ny',
		'nc','nd','oh','ok','or','pa','ri','sc','sd','tn','tx','ut','vt','va','wa','wv',
		'wi','wy',
	];

	$terms = get_terms([
		'taxonomy'   => BP_GEO_TAXONOMY,
		'hide_empty' => false,
	]);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		update_option( 'bp_geo_slug_migration_v1', 'yes' );
		return;
	}

	foreach ( $terms as $term ) {
		$slug = $term->slug;

		// Skip terms already in correct double-dash format
		if ( strpos( $slug, '--' ) !== false ) continue;

		$parts = explode( '-', $slug );
		if ( count( $parts ) < 3 ) continue;

		// State must be the last segment and a known 2-letter abbreviation
		$state = end( $parts );
		if ( ! in_array( $state, $state_abbrs, true ) ) continue;

		// Try city lengths 1-3 words, pick the split that gives the longest service slug
		$found_service = '';
		$found_city    = '';
		$max_city_words = count( $parts ) - 2;

		for ( $city_len = 1; $city_len <= min( $max_city_words, 3 ); $city_len++ ) {
			$city_parts    = array_slice( $parts, -1 - $city_len, $city_len );
			$service_parts = array_slice( $parts, 0, count( $parts ) - 1 - $city_len );

			if ( empty( $service_parts ) ) continue;

			if ( strlen( implode( '-', $service_parts ) ) > strlen( $found_service ) ) {
				$found_service = implode( '-', $service_parts );
				$found_city    = implode( '-', $city_parts );
			}
		}

		if ( empty( $found_service ) || empty( $found_city ) ) continue;

		$new_slug = $found_service . '--' . $found_city . '-' . $state;
		$new_name = ucwords( str_replace( '-', ' ', $found_service ) )
		            . ' — '
		            . ucwords( str_replace( '-', ' ', $found_city ) )
		            . ', '
		            . strtoupper( $state );

		$new_name = str_replace( 'Hvac', 'HVAC', $new_name );

		// Check for slug collision
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT term_id FROM {$wpdb->terms} WHERE slug = %s",
			$new_slug
		));

		if ( $existing && (int) $existing !== (int) $term->term_id ) {
			// Merge: reassign all posts from old term to existing term
			$old_tt = $wpdb->get_var( $wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
				$term->term_id, BP_GEO_TAXONOMY
			));
			$new_tt = $wpdb->get_var( $wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
				(int) $existing, BP_GEO_TAXONOMY
			));

			if ( $old_tt && $new_tt ) {
				$wpdb->query( $wpdb->prepare(
					"UPDATE IGNORE {$wpdb->term_relationships} SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d",
					$new_tt, $old_tt
				));
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",
					$old_tt
				));
			}

			wp_delete_term( $term->term_id, BP_GEO_TAXONOMY );
			error_log( "bp_geo_migrate: merged term id={$term->term_id} slug='{$slug}' into existing id={$existing} new_slug='{$new_slug}'" );
			continue;
		}

		// Update term directly in DB to preserve the double-dash slug
		$wpdb->update( $wpdb->terms,
			[ 'name' => $new_name, 'slug' => $new_slug ],
			[ 'term_id' => $term->term_id ]
		);

		error_log( "bp_geo_migrate: updated term id={$term->term_id} '{$slug}' => '{$new_slug}' name='{$new_name}'" );
		clean_term_cache( $term->term_id, BP_GEO_TAXONOMY );
	}

	update_option( 'bp_geo_slug_migration_v1', 'yes' );
	error_log( 'bp_geo_migrate_legacy_term_slugs: completed' );
}


/*--------------------------------------------------------------
# AI Integration
--------------------------------------------------------------*/

/*
 * TABLE OF CONTENTS
 * ---------------------------------------------------------
 * # Constants & Config
 * # Auto-trigger on first publish
 * # Manual "Re-run AI" button in post editor
 * # Core rewrite function
 * # Anthropic API call
 * # State name normalizer
 * # Taxonomy helper (match or create term)
 * ---------------------------------------------------------
 */


// ---------------------------------------------------------
// # Constants & Config
// ---------------------------------------------------------

// Store your Anthropic API key in wp-config.php:
// define( 'BP_ANTHROPIC_API_KEY', 'sk-ant-...' );

define( 'BP_GEO_AI_MODEL',       'claude-sonnet-4-20250514' );
define( 'BP_GEO_AI_MAX_TOKENS',  1024 );
define( 'BP_GEO_FIELD_ORIGINAL', 'bp_geo_original_description' ); // client's raw text, saved once on first publish
define( 'BP_GEO_FIELD_AI_RAN',   'bp_geo_ai_ran' );               // timestamp of last AI run
define( 'BP_GEO_TAXONOMY',       'jobsite_geo-services' );
define( 'BP_GEO_CPT',            'jobsite_geo' );

// Meta field keys used by your Jobsite GEO posts
define( 'BP_GEO_FIELD_ADDRESS',  'address' );
define( 'BP_GEO_FIELD_CITY',     'city' );
define( 'BP_GEO_FIELD_STATE',    'state' );
define( 'BP_GEO_FIELD_ZIP',      'zip' );
define( 'BP_GEO_FIELD_JOB_DATE', 'job_date' );


// ---------------------------------------------------------
// # Required-field validation — block publish if fields are missing
// ---------------------------------------------------------

// Fires at priority 20, after ACF has saved fields (priority 10 on save_post).
// Must hook save_post (not save_post_jobsite_geo) because save_post_{type} fires
// BEFORE save_post, meaning ACF hasn't written anything yet when save_post_{type} runs.
add_action( 'save_post', 'bp_geo_validate_required_fields', 20, 3 );

function bp_geo_validate_required_fields( $post_id, $post, $update ) {
	if ( $post->post_type !== 'jobsite_geo' ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( wp_is_post_revision( $post_id ) ) return;
	if ( $post->post_status !== 'publish' ) return;
	if ( ! empty( $GLOBALS['bp_geo_validation_running'] ) ) return;
	// Never redirect+exit during a REST API request — would kill the response mid-send
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;

	$required = [
		'City'  => trim( (string) get_post_meta( $post_id, BP_GEO_FIELD_CITY,  true ) ),
		'State' => trim( (string) get_post_meta( $post_id, BP_GEO_FIELD_STATE, true ) ),
	];

	$missing = array_keys( array_filter( $required, fn( $v ) => $v === '' ) );

	if ( empty( $missing ) ) return;

	// Revert to draft directly in DB — avoids triggering save_post recursion
	global $wpdb;
	$GLOBALS['bp_geo_validation_running'] = true;
	$wpdb->update( $wpdb->posts, [ 'post_status' => 'draft' ], [ 'ID' => $post_id ] );
	clean_post_cache( $post_id );
	unset( $GLOBALS['bp_geo_validation_running'] );

	set_transient(
		'bp_geo_required_' . get_current_user_id(),
		implode( ', ', $missing ),
		60
	);

	wp_safe_redirect( add_query_arg(
		[ 'post' => $post_id, 'action' => 'edit' ],
		admin_url( 'post.php' )
	) );
	exit;
}

add_action( 'admin_notices', 'bp_geo_show_required_notice' );

function bp_geo_show_required_notice() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->base !== 'post' ) return;

	$uid = get_current_user_id();
	$msg = get_transient( 'bp_geo_required_' . $uid );
	if ( ! $msg ) return;

	delete_transient( 'bp_geo_required_' . $uid );
	echo '<div class="notice notice-error is-dismissible"><p>'
		. '<strong>Jobsite GEO:</strong> The following required fields must be filled in before publishing: <strong>'
		. esc_html( $msg )
		. '</strong>. The post has been saved as a draft.</p></div>';
}


// ---------------------------------------------------------
// # Auto-trigger on first publish
// ---------------------------------------------------------

// Auto-trigger fires on save_post at priority 99 so ACF fields are already saved
add_action( 'save_post', 'bp_geo_ai_on_first_publish', 99, 3 );

function bp_geo_ai_on_first_publish( $post_id, $post, $update ) {
	if ( $post->post_type !== BP_GEO_CPT ) return;
	if ( $post->post_status !== 'publish' ) return;

	// Only auto-run once per post — this is the sole guard against re-running on updates
	if ( get_post_meta( $post_id, BP_GEO_FIELD_AI_RAN, true ) ) return;

	// Don't run during our own wp_update_post call
	if ( ! empty( $GLOBALS['bp_jobsite_setup_running'] ) ) return;

	error_log( 'bp_geo_ai_on_first_publish: firing for post ' . $post_id );
	bp_geo_run_ai_rewrite( $post_id );
}


// ---------------------------------------------------------
// # Manual "Re-run AI" button in post editor
// ---------------------------------------------------------

add_action( 'add_meta_boxes', 'bp_geo_ai_meta_box' );

function bp_geo_ai_meta_box() {
	add_meta_box(
		'bp_geo_ai_rewrite',
		'AI Description',
		'bp_geo_ai_meta_box_html',
		BP_GEO_CPT,
		'side',
		'low'
	);
}

function bp_geo_ai_meta_box_html( $post ) {
	$original = get_post_meta( $post->ID, BP_GEO_FIELD_ORIGINAL, true );
	$ai_ran   = get_post_meta( $post->ID, BP_GEO_FIELD_AI_RAN, true );
	$nonce    = wp_create_nonce( 'bp_geo_ai_rewrite_nonce' );
	?>
	<p style="margin-bottom:8px;">
		<?php if ( $ai_ran ) : ?>
			<span style="color:#2e7d32;">&#10003; AI last ran: <?php echo esc_html( $ai_ran ); ?></span>
		<?php else : ?>
			<span style="color:#999;">AI has not run on this post yet.</span>
		<?php endif; ?>
	</p>

	<?php if ( $original ) : ?>
		<div style="font-size:12px;color:#555;margin-bottom:10px;padding:8px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:3px;">
			<strong>Original (client text):</strong><br><br>
			<?php echo esc_html( $original ); ?>
		</div>
	<?php endif; ?>

	<button type="button"
	        class="button button-primary"
	        id="bp-geo-run-ai"
	        data-post-id="<?php echo esc_attr( $post->ID ); ?>"
	        data-nonce="<?php echo esc_attr( $nonce ); ?>"
	        style="width:100%;">
		AI Rewrite
	</button>
	<p style="font-size:11px;color:#888;margin-top:5px;">
		<?php if ( $ai_ran ) : ?>
			Re-running will overwrite current post content with a fresh AI rewrite.<br>The original client text is always preserved above.
		<?php else : ?>
			Runs automatically on first publish. Use this button to run manually or re-run after editing.
		<?php endif; ?>
	</p>

	<div id="bp-geo-ai-status" style="margin-top:8px;font-size:12px;"></div>

	<script>
	(function(){
		document.getElementById('bp-geo-run-ai').addEventListener('click', function(){
			var btn    = this;
			var status = document.getElementById('bp-geo-ai-status');
			btn.disabled    = true;
			btn.textContent = 'Running...';
			status.textContent = '';

			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action:  'bp_geo_run_ai_rewrite',
					post_id: btn.dataset.postId,
					nonce:   btn.dataset.nonce
				})
			})
			.then(r => r.json())
			.then(function(data){
				if (data.success) {
					status.style.color = '#2e7d32';
					status.textContent = '✓ ' + data.data.message + ' — reloading...';
					setTimeout(function(){ location.reload(); }, 1500);
				} else {
					status.style.color  = '#c62828';
					status.textContent  = '✗ ' + (data.data || 'Unknown error');
					btn.textContent     = 'AI Rewrite';
					btn.disabled        = false;
				}
			})
			.catch(function(err){
				status.style.color = '#c62828';
				status.textContent = '✗ Request failed.';
				btn.disabled       = false;
				btn.textContent    = 'AI Rewrite';
			});
		});
	})();
	</script>
	<?php
}

// Remove taxonomy meta boxes from sidebar (AI assigns these automatically)
add_action('add_meta_boxes_jobsite_geo', function() {
	remove_meta_box('tagsdiv-jobsite_geo-service-types', 'jobsite_geo', 'side');
	remove_meta_box('tagsdiv-jobsite_geo-service-areas', 'jobsite_geo', 'side');
	remove_meta_box('tagsdiv-jobsite_geo-services',      'jobsite_geo', 'side');
});

// Display taxonomy terms below the permalink, with inline edit for Service Type and Service Area
add_action('edit_form_after_title', function($post) {
	if ($post->post_type !== 'jobsite_geo') return;

	$type_terms = wp_get_post_terms($post->ID, 'jobsite_geo-service-types', ['fields' => 'slugs']);
	$area_terms = wp_get_post_terms($post->ID, 'jobsite_geo-service-areas', ['fields' => 'slugs']);
	$svc_terms  = wp_get_post_terms($post->ID, 'jobsite_geo-services',      ['fields' => 'slugs']);

	$type         = (!is_wp_error($type_terms) && !empty($type_terms)) ? implode(', ', $type_terms) : '—';
	$area         = (!is_wp_error($area_terms) && !empty($area_terms)) ? implode(', ', $area_terms) : '—';
	$svc          = (!is_wp_error($svc_terms)  && !empty($svc_terms))  ? implode(', ', $svc_terms)  : '—';
	$all_types    = get_terms(['taxonomy' => 'jobsite_geo-service-types', 'hide_empty' => false]);
	$all_areas    = get_terms(['taxonomy' => 'jobsite_geo-service-areas', 'hide_empty' => false]);
	$type_nonce   = wp_create_nonce('bp_geo_update_service_type');
	$area_nonce   = wp_create_nonce('bp_geo_update_service_area');
	$current_type = (!is_wp_error($type_terms) && !empty($type_terms)) ? $type_terms[0] : '';
	$current_area = (!is_wp_error($area_terms) && !empty($area_terms)) ? $area_terms[0] : '';
	$btn_style    = 'font-size:11px;padding:0 6px;line-height:1.8;';
	$btn_ok_style = 'font-size:11px;padding:0 8px;line-height:1.8;';
	$sel_style    = 'font-size:13px;';
	?>
	<div id="bp-geo-taxonomy-info">

		<div><strong style="display:inline-block;width:100px;">Service Page:</strong> <span id="bp-geo-svc-display"><?php echo esc_html($svc); ?></span></div>

		<div>
			<strong style="display:inline-block;width:100px;">Service Type:</strong>
			<span id="bp-geo-type-display"><?php echo esc_html($type); ?></span>
			&nbsp;
			<button type="button" class="button button-small" id="bp-geo-edit-type-btn" style="<?php echo $btn_style; ?>">Edit</button>
			<span id="bp-geo-edit-type-wrap" style="display:none;">
				<select id="bp-geo-type-select" style="<?php echo $sel_style; ?>">
					<?php if (!is_wp_error($all_types)) foreach ($all_types as $t) : ?>
						<option value="<?php echo esc_attr($t->slug); ?>" <?php selected($t->slug, $current_type); ?>><?php echo esc_html($t->slug); ?></option>
					<?php endforeach; ?>
				</select>
				&nbsp;
				<button type="button" class="button button-primary button-small" id="bp-geo-type-ok" style="<?php echo $btn_ok_style; ?>">OK</button>
				<button type="button" class="button button-small" id="bp-geo-type-cancel" style="<?php echo $btn_style; ?>">Cancel</button>
				<span id="bp-geo-type-status" style="margin-left:8px;font-size:12px;"></span>
			</span>
		</div>

		<div>
			<strong style="display:inline-block;width:100px;">Service Area:</strong>
			<span id="bp-geo-area-display"><?php echo esc_html($area); ?></span>
			&nbsp;
			<button type="button" class="button button-small" id="bp-geo-edit-area-btn" style="<?php echo $btn_style; ?>">Edit</button>
			<span id="bp-geo-edit-area-wrap" style="display:none;">
				<select id="bp-geo-area-select" style="<?php echo $sel_style; ?>">
					<?php if (!is_wp_error($all_areas)) foreach ($all_areas as $a) : ?>
						<option value="<?php echo esc_attr($a->slug); ?>" <?php selected($a->slug, $current_area); ?>><?php echo esc_html($a->slug); ?></option>
					<?php endforeach; ?>
				</select>
				&nbsp;
				<button type="button" class="button button-primary button-small" id="bp-geo-area-ok" style="<?php echo $btn_ok_style; ?>">OK</button>
				<button type="button" class="button button-small" id="bp-geo-area-cancel" style="<?php echo $btn_style; ?>">Cancel</button>
				<span id="bp-geo-area-status" style="margin-left:8px;font-size:12px;"></span>
			</span>
		</div>

	</div>
	<script>
	(function(){
		var svcDisp = document.getElementById('bp-geo-svc-display');

		function makeInlineEdit(editBtnId, wrapId, cancelBtnId, okBtnId, selectId, displayId, statusId, ajaxAction, postId, nonce) {
			var editBtn   = document.getElementById(editBtnId);
			var wrap      = document.getElementById(wrapId);
			var cancelBtn = document.getElementById(cancelBtnId);
			var okBtn     = document.getElementById(okBtnId);
			var select    = document.getElementById(selectId);
			var display   = document.getElementById(displayId);
			var status    = document.getElementById(statusId);

			editBtn.addEventListener('click', function() {
				wrap.style.display    = 'inline';
				editBtn.style.display = 'none';
			});

			cancelBtn.addEventListener('click', function() {
				wrap.style.display    = 'none';
				editBtn.style.display = 'inline';
				status.textContent    = '';
			});

			okBtn.addEventListener('click', function() {
				okBtn.disabled     = true;
				status.style.color = '#555';
				status.textContent = 'Saving…';

				fetch(ajaxurl, {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: new URLSearchParams({
						action:    ajaxAction,
						post_id:   postId,
						term_slug: select.value,
						nonce:     nonce
					})
				})
				.then(function(r){ return r.json(); })
				.then(function(data) {
					if (data.success) {
						display.textContent   = select.value;
						svcDisp.textContent   = data.data.service || '—';
						wrap.style.display    = 'none';
						editBtn.style.display = 'inline';
						status.textContent    = '';
					} else {
						status.style.color = '#c62828';
						status.textContent = '✗ ' + (data.data || 'Error');
						okBtn.disabled     = false;
					}
				})
				.catch(function() {
					status.style.color = '#c62828';
					status.textContent = '✗ Request failed.';
					okBtn.disabled     = false;
				});
			});
		}

		makeInlineEdit(
			'bp-geo-edit-type-btn', 'bp-geo-edit-type-wrap', 'bp-geo-type-cancel', 'bp-geo-type-ok',
			'bp-geo-type-select', 'bp-geo-type-display', 'bp-geo-type-status',
			'bp_geo_update_service_type', <?php echo (int) $post->ID; ?>, '<?php echo esc_js($type_nonce); ?>'
		);

		makeInlineEdit(
			'bp-geo-edit-area-btn', 'bp-geo-edit-area-wrap', 'bp-geo-area-cancel', 'bp-geo-area-ok',
			'bp-geo-area-select', 'bp-geo-area-display', 'bp-geo-area-status',
			'bp_geo_update_service_area', <?php echo (int) $post->ID; ?>, '<?php echo esc_js($area_nonce); ?>'
		);
	})();
	</script>
	<?php
});

add_action('wp_ajax_bp_geo_update_service_type', function() {
	if (!check_ajax_referer('bp_geo_update_service_type', 'nonce', false)) wp_send_json_error('Invalid nonce.');
	if (!current_user_can('edit_posts')) wp_send_json_error('Insufficient permissions.');

	$post_id   = intval($_POST['post_id']   ?? 0);
	$type_slug = sanitize_title($_POST['term_slug'] ?? '');
	if (!$post_id || !$type_slug) wp_send_json_error('Invalid data.');

	if (!term_exists($type_slug, 'jobsite_geo-service-types')) wp_insert_term($type_slug, 'jobsite_geo-service-types');
	wp_set_post_terms($post_id, [$type_slug], 'jobsite_geo-service-types', false);
	bp_geo_sync_services_from_types($post_id);

	$svc_terms = wp_get_post_terms($post_id, 'jobsite_geo-services', ['fields' => 'slugs']);
	wp_send_json_success(['service' => (!is_wp_error($svc_terms) && !empty($svc_terms)) ? implode(', ', $svc_terms) : '']);
});

add_action('wp_ajax_bp_geo_update_service_area', function() {
	if (!check_ajax_referer('bp_geo_update_service_area', 'nonce', false)) wp_send_json_error('Invalid nonce.');
	if (!current_user_can('edit_posts')) wp_send_json_error('Insufficient permissions.');

	$post_id   = intval($_POST['post_id']   ?? 0);
	$area_slug = sanitize_title($_POST['term_slug'] ?? '');
	if (!$post_id || !$area_slug) wp_send_json_error('Invalid data.');

	if (!term_exists($area_slug, 'jobsite_geo-service-areas')) wp_insert_term($area_slug, 'jobsite_geo-service-areas');
	wp_set_post_terms($post_id, [$area_slug], 'jobsite_geo-service-areas', false);
	bp_geo_sync_services_from_types($post_id);

	$svc_terms = wp_get_post_terms($post_id, 'jobsite_geo-services', ['fields' => 'slugs']);
	wp_send_json_success(['service' => (!is_wp_error($svc_terms) && !empty($svc_terms)) ? implode(', ', $svc_terms) : '']);
});

add_action( 'wp_ajax_bp_geo_run_ai_rewrite', 'bp_geo_ajax_handler' );

function bp_geo_ajax_handler() {
	if ( ! check_ajax_referer( 'bp_geo_ai_rewrite_nonce', 'nonce', false ) ) {
		wp_send_json_error( 'Invalid nonce.' );
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$post_id = intval( $_POST['post_id'] ?? 0 );
	if ( ! $post_id ) {
		wp_send_json_error( 'Invalid post ID.' );
	}

	$result = bp_geo_run_ai_rewrite( $post_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success([
		'message' => 'Rewrite complete. Category: ' . ( $result['term_slug'] ?? 'n/a' ),
		'excerpt' => wp_trim_words( $result['rewritten'], 20, '...' ),
	]);
}


// ---------------------------------------------------------
// # Core rewrite function
// ---------------------------------------------------------

function bp_geo_run_ai_rewrite( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== BP_GEO_CPT ) {
		return new WP_Error( 'invalid_post', 'Not a jobsite_geo post.' );
	}

	$current_content = wp_strip_all_tags( $post->post_content );
	if ( empty( trim( $current_content ) ) ) {
		return new WP_Error( 'empty_content', 'Post has no content to rewrite.' );
	}

	// Save original description once, on first AI run only
	$original_saved = get_post_meta( $post_id, BP_GEO_FIELD_ORIGINAL, true );
	if ( empty( $original_saved ) ) {
		update_post_meta( $post_id, BP_GEO_FIELD_ORIGINAL, sanitize_textarea_field( $current_content ) );
	}

	// Gather context (private — sent to API but never stored in output)
	$company_name = get_bloginfo( 'name' );
	$street       = get_post_meta( $post_id, BP_GEO_FIELD_ADDRESS,  true );
	$city         = get_post_meta( $post_id, BP_GEO_FIELD_CITY,     true );
	$state        = get_post_meta( $post_id, BP_GEO_FIELD_STATE,    true );
	$zip          = get_post_meta( $post_id, BP_GEO_FIELD_ZIP,      true );
	$job_date     = get_post_meta( $post_id, BP_GEO_FIELD_JOB_DATE, true );

	$location_context = trim( $city . ', ' . $state );

	// Get all existing taxonomy terms for this site
	$terms      = get_terms([
		'taxonomy'   => BP_GEO_TAXONOMY,
		'hide_empty' => false,
		'fields'     => 'slugs',
	]);
	$terms_list = is_wp_error( $terms ) ? [] : $terms;

	// Call Anthropic API
	$api_result = bp_geo_call_anthropic([
		'raw_description' => $current_content,
		'company_name'    => $company_name,
		'city'            => $city,
		'state'           => $state,
		'location_context'=> $location_context,
		'job_date'        => $job_date,
		'street'          => $street,  // context only, never output
		'zip'             => $zip,     // context only, never output
		'existing_terms'  => $terms_list,
	]);

	if ( is_wp_error( $api_result ) ) {
		return $api_result;
	}

	$rewritten    = $api_result['rewritten_description'] ?? '';
	$base_service = $api_result['service_category'] ?? '';

	if ( empty( $rewritten ) ) {
		return new WP_Error( 'empty_rewrite', 'API returned empty rewritten description.' );
	}

	// Write AI output directly to post_content
	// Set the global guard flag so bp_jobsite_setup() skips when save_post fires
	// from wp_update_post — otherwise it would re-attach old taxonomy terms
	$GLOBALS['bp_jobsite_setup_running'] = true;
	remove_action( 'save_post', 'bp_geo_ai_on_first_publish', 99 );

	wp_update_post([
		'ID'           => $post_id,
		'post_content' => wp_kses_post( $rewritten ),
	]);

	add_action( 'save_post', 'bp_geo_ai_on_first_publish', 99, 3 );
	unset( $GLOBALS['bp_jobsite_setup_running'] );

	// Record timestamp of last AI run
	update_post_meta( $post_id, BP_GEO_FIELD_AI_RAN, current_time( 'mysql' ) );

	// Assign taxonomy term (runs AFTER wp_update_post and guard is cleared)
	$term_slug = '';
	if ( ! empty( $base_service ) && ! empty( $city ) && ! empty( $state ) ) {
		$term_slug = bp_geo_assign_taxonomy_term( $post_id, $base_service, $city, $state, $terms_list );
	}

	// Send completion notification — only fires once AI rewrite is done
	bp_jobsite_send_completion_email( $post_id, $street, $city, $state, $rewritten, $term_slug );

	return [
		'rewritten' => $rewritten,
		'term_slug' => $term_slug,
	];
}

// Send a "jobsite completed" notification email after AI rewrite finishes.
// Replaces the old per-save notification in bp_jobsite_setup().
function bp_jobsite_send_completion_email( int $post_id, string $street, string $city, string $state, string $rewritten, string $term_slug ): void {

	$geo_opts = get_option('jobsite_geo');

	$notifyTo = !empty($geo_opts['notify']) && $geo_opts['notify'] !== 'false'
		? $geo_opts['notify']
		: '';

	$notifyBc = !empty($geo_opts['copy_me']) && $geo_opts['copy_me'] === 'true'
		? 'info@bp-webdev.com'
		: '';

	if ($notifyTo === '' && $notifyBc === '') return;

	if ($notifyTo === '') {
		$notifyTo = $notifyBc;
		$notifyBc = '';
	}

	$post    = get_post($post_id);
	$title   = $post ? get_the_title($post_id) : 'Untitled';
	$url     = get_permalink($post_id);
	$address = trim("{$street}, {$city}, {$state}");

	$photo_count = 0;
	foreach (['jobsite_photo_1', 'jobsite_photo_2', 'jobsite_photo_3', 'jobsite_photo_4'] as $field) {
		if ((int)get_post_meta($post_id, $field, true)) $photo_count++;
	}

	$domain    = parse_url(home_url(), PHP_URL_HOST);
	$site_name = get_bloginfo('name');
	$subject   = "New Jobsite Post Ready: {$title}";

	$message  = "<p>A new jobsite post has been published and the AI description is ready.</p>";
	$message .= "<p><strong>Site:</strong> {$site_name}</p>";
	if ($address !== ', , ') $message .= "<p><strong>Address:</strong> " . esc_html($address) . "</p>";
	if ($term_slug)          $message .= "<p><strong>Category:</strong> " . esc_html($term_slug) . "</p>";
	$message .= "<p><strong>Photos:</strong> " . ($photo_count > 0 ? $photo_count . ' ' . _n('photo', 'photos', $photo_count) : 'none') . "</p>";
	$message .= "<p><strong>Description:</strong></p><p>" . nl2br(esc_html($rewritten)) . "</p>";
	if ($url)                $message .= "<p><a href='" . esc_url($url) . "'>View Post</a> &nbsp;|&nbsp; <a href='" . esc_url(admin_url('post.php?post=' . $post_id . '&action=edit')) . "'>Edit Post</a></p>";

	$headers   = [];
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	$headers[] = 'From: Website Administrator <noreply@' . $domain . '>';
	$headers[] = 'Reply-To: noreply@' . $domain;
	if ($notifyBc) $headers[] = 'Bcc: <' . $notifyBc . '>';

	wp_mail($notifyTo, $subject, $message, $headers);
}


// ---------------------------------------------------------
// # Anthropic API call
// ---------------------------------------------------------

function bp_geo_call_anthropic( $data ) {
	if ( ! defined( 'BP_ANTHROPIC_API_KEY' ) || empty( BP_ANTHROPIC_API_KEY ) ) {
		return new WP_Error( 'no_api_key', 'BP_ANTHROPIC_API_KEY is not defined in wp-config.php.' );
	}

	$existing_terms_str = ! empty( $data['existing_terms'] )
		? implode( ', ', $data['existing_terms'] )
		: 'No existing terms found.';

	$location_note = '';
	if ( ! empty( $data['city'] ) && ! empty( $data['state'] ) ) {
		$location_note = "The job took place in {$data['city']}, {$data['state']}.";
		if ( ! empty( $data['job_date'] ) ) {
			$location_note .= " Job date: {$data['job_date']}.";
		}
		$location_note .= " You may reference the city and state naturally in the writing, but DO NOT include the street address or zip code anywhere in the output.";
	}

	// Strip legal suffixes from company name — they never read naturally in prose
	$company_clean = preg_replace( '/\s*,?\s*(LLC|Inc\.?|Corp\.?|Ltd\.?|Co\.?)\.?\s*$/i', '', $data['company_name'] );
	$company_clean = trim( $company_clean );

	// Decide per-post whether to use the company name (50% of the time)
	$use_company_name = rand( 0, 1 ) === 1;
	$company_name_instruction = $use_company_name
		? "Use the company name '{}' naturally once in this post. Do not use it more than once."
		: "Do NOT use the company name in this post. Write entirely in first-person team voice: 'Our technician...', 'We diagnosed...', 'The team found...'";

	$prompt = bp_geo_prompt_rewrite_description( $company_clean, $company_name_instruction, $location_note, $existing_terms_str, $data['raw_description'] );

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 30,
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => BP_ANTHROPIC_API_KEY,
			'anthropic-version' => '2023-06-01',
		],
		'body' => wp_json_encode([
			'model'      => BP_GEO_AI_MODEL,
			'max_tokens' => BP_GEO_AI_MAX_TOKENS,
			'messages'   => [
				[ 'role' => 'user', 'content' => $prompt ]
			],
		]),
	]);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status  = wp_remote_retrieve_response_code( $response );
	$body    = wp_remote_retrieve_body( $response );
	$decoded = json_decode( $body, true );

	if ( $status !== 200 ) {
		$error_msg = $decoded['error']['message'] ?? 'Unknown API error.';
		return new WP_Error( 'api_error', "Anthropic API error ($status): $error_msg" );
	}

	$text = $decoded['content'][0]['text'] ?? '';

	if ( empty( $text ) ) {
		return new WP_Error( 'empty_response', 'Anthropic returned an empty response.' );
	}

	// Strip any accidental markdown code fences
	$text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
	$text = preg_replace( '/\s*```$/', '', $text );

	$parsed = json_decode( $text, true );

	if ( json_last_error() !== JSON_ERROR_NONE || empty( $parsed['rewritten_description'] ) ) {
		return new WP_Error( 'parse_error', 'Could not parse JSON from API response: ' . substr( $text, 0, 200 ) );
	}

	return $parsed;
}


function bp_geo_fetch_wiki_summary( string $city, string $state ): string {
	$search = urlencode( "{$city}, {$state}" );
	$url    = "https://en.wikipedia.org/api/rest_v1/page/summary/{$search}";
	$res    = wp_remote_get( $url, [ 'timeout' => 8 ] );

	if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
		return '';
	}

	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	return $data['extract'] ?? '';
}

function bp_geo_generate_term_intro( int $term_id ) {
	if ( ! defined( 'BP_ANTHROPIC_API_KEY' ) || empty( BP_ANTHROPIC_API_KEY ) ) {
		return new WP_Error( 'no_api_key', 'BP_ANTHROPIC_API_KEY is not defined.' );
	}

	$term = get_term( $term_id, 'jobsite_geo-services' );
	if ( ! $term || is_wp_error( $term ) ) {
		return new WP_Error( 'invalid_term', 'Term not found.' );
	}

	// Get service + location from a tagged post (avoids slug-parsing issues)
	$sample = get_posts( [
		'post_type'      => 'jobsite_geo',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'tax_query'      => [ [ 'taxonomy' => 'jobsite_geo-services', 'terms' => $term_id ] ],
	] );

	$service = '';
	$city    = '';
	$state   = '';

	if ( $sample ) {
		$type_terms = wp_get_post_terms( $sample[0], 'jobsite_geo-service-types', [ 'fields' => 'slugs' ] );
		$area_terms = wp_get_post_terms( $sample[0], 'jobsite_geo-service-areas', [ 'fields' => 'slugs' ] );
		if ( ! empty( $type_terms ) ) $service = bp_format_service( $type_terms[0] );
		if ( ! empty( $area_terms ) ) {
			$loc   = bp_format_location( $area_terms[0] );
			$parts = explode( ', ', $loc );
			$city  = $parts[0] ?? '';
			$state = $parts[1] ?? '';
		}
	}

	if ( ! $service || ! $city ) {
		return new WP_Error( 'missing_data', "Could not determine service/location for term #{$term_id} ({$term->slug})." );
	}

	$company = preg_replace( '/\s*,?\s*(LLC|Inc\.?|Corp\.?|Ltd\.?|Co\.?)\.?\s*$/i', '', get_bloginfo( 'name' ) );
	$company = trim( $company );

	$wiki = bp_geo_fetch_wiki_summary( $city, $state );
	$prompt = bp_geo_prompt_service_intro( $company, $service, $city, $state, $wiki );

	/* REPLACED — prompts moved to /prompts/prompts-jobsite-geo.php
	$opening_para = rand( 1, 7 );
	$second_para = rand( 1, 5 );
	$choose_h2 = rand( 1, 5 );
	$local_para  = rand( 1, 7 );
	$wrap_up_para  = rand( 1, 5 );
	$map_caption  = rand( 1, 5 );

	$prompt = "You are an SEO copywriter for an HVAC home services company called \"{$company}\".

Write a service page intro for:
- Company: {$company}
- Service: {$service}
- City, State: {$city}, {$state}

OPENING PARAGRAPH - ";
if ( $opening_para === 1 ) {
	$prompt .= "Start with something that goes wrong for a homeowner before they call; a symptom, a breakdown moment, a frustration.";
} elseif ( $opening_para === 2 ) {
	$prompt .= "Start with a weather or seasonal observation told from the homeowner's daily experience, not a weather report.";
} elseif ( $opening_para === 3 ) {
	$prompt .= "Start with what makes {$service} worth doing right; what's at stake if it's skipped or done poorly.";
} elseif ( $opening_para === 4 ) {
	$prompt .= "Start with a direct, confident statement about {$company} and what they do for {$city} homeowners.";
} elseif ( $opening_para === 5 ) {
	$prompt .= "Start with the financial angle; what ignoring this costs over time, in bills or repairs.";
} elseif ( $opening_para === 6 ) {
	$prompt .= "Start with a counterintuitive or surprising fact about {$service} that most homeowners don't know.";
} else {
	$prompt .= "Start with a question the homeowner is asking themselves right now about their system.";
}
$prompt .= "Introduce {$company} and {$service} naturally. Use <strong> tags around the company name on first use only. First-person plural (\"we\", \"our\") for the rest of the paragraph.

SECOND PARAGRAPH - ";
if ( $second_para === 1 ) {
	$prompt .= "Speak to the homeowner directly. What does getting {$service} right protect them from? What happens if it's ignored?";
} elseif ( $second_para === 2 ) {
	$prompt .= "Build trust by showing empathy for the homeowner's situation and confidence in the solution. Use a reassuring, conversational tone.";
} elseif ( $second_para === 3 ) {
	$prompt .= "Address a common misconception homeowners have about {$service} — what they assume vs. what's actually true.";
} elseif ( $second_para === 4 ) {
	$prompt .= "Focus on the long-term payoff; what a properly handled {$service} means over the next several years.";
} else {
	$prompt .= "Explain {$company}'s approach to {$service} in a way that makes the homeowner feel like they understand what to expect and are in good hands.";
}
$prompt .= "Two to four concrete sentences. Do not open with \"We handle\" or list service types. Make it feel like a helpful conversation, not a sales pitch. Avoid jargon and fluff.

H2 HEADING — (SEO-friendly. No filler. Standalone tag, not inside a paragraph.)";
if ( $choose_h2 === 1 ) {
	$prompt .= "Make a short declarative statement pairing {$service} and {$city}";
} elseif ( $choose_h2 === 2 ) {
	$prompt .= "Pose a question the homeowner would actually ask.";
} elseif ( $choose_h2 === 3 ) {
	$prompt .= "State the outcome the homeowner gets, not just the service or city.";
} elseif ( $choose_h2 === 4 ) {
	$prompt .= "Imply timing matters without being pushy (\"Before the Heat Hits\" type angle).";
} else {
	$prompt .= "Place-focused — names {$city} or the region in a natural, specific way.";
}

$wiki = bp_geo_fetch_wiki_summary( $city, $state );

$prompt .= "\n\nLOCAL PARAGRAPH — ";
if ( $wiki ) {
	$prompt .="Use the following information about the neighborhoods, geography, climate, and character of {$city} to infuse this paragraph with local SEO value. Do not copy any sentences verbatim:\n\n\"{$wiki}\"\n\n";
} else {
	$prompt .= "Use your training knowledge about {$city}, {$state}, to describe the regional climate and housing character. ";
}

if ( $local_para === 1 ) {
	$prompt .= "Then focus on timing: when does {$service} become urgent in {$city}? What months push systems hardest in this climate?";
} elseif ( $local_para === 2 ) {
	$prompt .= "Then focus on the homes specifically: what is the housing stock like in {$city}? Age, size, construction type, or neighborhood character.";
} elseif ( $local_para === 3 ) {
	$prompt .= "Then focus on the climate: how does the weather and climate in this region affect {$service} specifically?";
} elseif ( $local_para === 4 ) {
	$prompt .= "Focus on the growth of {$city}; new construction vs. older neighborhoods, rapid development, how that creates mixed HVAC needs across the city.";
} elseif ( $local_para === 5 ) {
	$prompt .= "Focus on a weather extreme specific to the region — ice storms, drought summers, humidity spikes — told as something that happened, not a forecast.";
} elseif ( $local_para === 6 ) {
	$prompt .= "Focus on what makes {$city} feel like home — the character of the community, how long people tend to stay, and how that investment in their home connects to keeping their HVAC system in good shape.";
} else {
	$prompt .= "Then focus on energy demands: how does the local climate drive up bills, and what that means practically for homeowners.";
}

$prompt .= "\n\nWRAP-UP PARAGRAPH — ";
if ( $wrap_up_para === 1 ) {
	$prompt .= "Describe what the homeowner experiences during a visit; what gets done, how it's handled, what they walk away with.";
} elseif ( $wrap_up_para === 2 ) {
	$prompt .= "Keep it simple: what {$company} shows up to do, stated plainly. Short sentences. No jargon.";
} elseif ( $wrap_up_para === 3 ) {
	$prompt .= "Describe the before and after; what the problem was, what's different when the job is done.";
} elseif ( $wrap_up_para === 4 ) {
	$prompt .= "Focus on communication; how the homeowner stays informed during the visit, no surprises on scope or price.";
} else {
	$prompt .= "Talk about follow-through: what the homeowner knows after the job is finished and why it was worth the call.";
}
$prompt .= "End with a sentence that leads naturally into: contact us today at <strong>[get-biz info=\"phone-link\"]</strong>!

RULES:
- Every paragraph in <p> tags. H2 is standalone, not inside <p>.
- First-person plural (\"we\", \"our\") throughout except the first sentence of the opening paragraph.
- 6th grade reading level. Mix short and longer sentences.
- 200-260 words total.
- No em-dashes. No: seamless, cutting-edge, state-of-the-art, peace of mind, look no further, dedicated team, expert, trust us.
- Output ONLY the HTML.

Also write a map_caption (~8-12 words, plain text): Should be a concise, natural-sounding sentence. If necessary, use the plural version of {$service}. Example: ";
if ( $map_caption === 1 ) {
	$prompt .= "{$service} completed by {$company} in {$city}";
} elseif ( $map_caption === 2 ) {
	$prompt .= "Recent {$service} we completed in {$city}";
} elseif ( $map_caption === 3 ) {
	$prompt .= "{$company} {$service} projects completed in {$city} area";
} elseif ( $map_caption === 4 ) {
	$prompt .= "{$service} in {$city} and surrounding areas";
} else {
	$prompt .= "{$city} homes where {$company} provided {$service} solutions.";
}
$prompt .= "No HTML tags.

Respond ONLY with valid JSON: {\"intro\": \"...html content...\", \"map_caption\": \"...plain text...\"}";
	REPLACED */

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 45,
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => BP_ANTHROPIC_API_KEY,
			'anthropic-version' => '2023-06-01',
		],
		'body' => wp_json_encode( [
			'model'      => BP_GEO_AI_MODEL,
			'max_tokens' => 1024,
			'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
		] ),
	] );

	if ( is_wp_error( $response ) ) return $response;

	$status  = wp_remote_retrieve_response_code( $response );
	$body    = wp_remote_retrieve_body( $response );
	$decoded = json_decode( $body, true );

	if ( $status !== 200 ) {
		$error_msg = $decoded['error']['message'] ?? 'Unknown API error.';
		return new WP_Error( 'api_error', "Anthropic API error ({$status}): {$error_msg}" );
	}

	$text = $decoded['content'][0]['text'] ?? '';
	$text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
	$text = preg_replace( '/\s*```$/', '', $text );
	$parsed = json_decode( $text, true );

	if ( json_last_error() !== JSON_ERROR_NONE || empty( $parsed['intro'] ) ) {
		return new WP_Error( 'parse_error', 'Could not parse JSON from API: ' . substr( $text, 0, 200 ) );
	}

	// Store in term meta (bypasses WordPress kses stripping on term description)
	update_term_meta( $term_id, 'bp_geo_service_intro', $parsed['intro'] );
	if ( ! empty( $parsed['map_caption'] ) ) {
		update_term_meta( $term_id, 'bp_geo_map_caption', sanitize_text_field( $parsed['map_caption'] ) );
	}

	// Email notification
	if ( function_exists( 'emailMe' ) ) {
		$caption  = sanitize_text_field( $parsed['map_caption'] ?? '' );
		$edit_url = get_edit_term_link( $term_id, 'jobsite_geo-services' );
		$list_url = admin_url( 'edit-tags.php?taxonomy=jobsite_geo-services' );
		$subject  = get_bloginfo( 'name' ) . ': New Service Intro — ' . $service . ' in ' . $city . ', ' . $state;
		$body     = '
<div style="font-family:sans-serif;max-width:680px;">
<h2 style="margin:0 0 4px;">' . esc_html( $service ) . ' in ' . esc_html( $city ) . ', ' . esc_html( $state ) . '</h2>
<p style="margin:0 0 20px;color:#666;font-size:13px;"><strong>Company:</strong> ' . esc_html( get_bloginfo( 'name' ) ) . ' &nbsp;|&nbsp; <strong>Term:</strong> ' . esc_html( $term->slug ) . '</p>

<h3 style="margin:0 0 6px;font-size:14px;color:#333;">Map Caption</h3>
<p style="margin:0 0 24px;padding:10px 14px;background:#f5f5f5;border-left:3px solid #ccc;font-size:14px;">' . esc_html( $caption ) . '</p>

<h3 style="margin:0 0 6px;font-size:14px;color:#333;">Page Intro</h3>
<div style="padding:16px;background:#fafafa;border:1px solid #e0e0e0;border-radius:4px;font-size:14px;line-height:1.6;">' . $parsed['intro'] . '</div>

<p style="margin:24px 0 0;">
  <a href="' . esc_url( $edit_url ) . '" style="display:inline-block;padding:8px 16px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;font-size:13px;margin-right:8px;">Edit This Term</a>
  <a href="' . esc_url( $list_url ) . '" style="display:inline-block;padding:8px 16px;background:#f0f0f1;color:#2c3338;text-decoration:none;border-radius:4px;font-size:13px;">View All Service Terms</a>
</p>
</div>';
		emailMe( $subject, $body );
	}

	return $parsed['intro'];
}

// Auto-generate intro for any new jobsite_geo-services term when a post is saved
add_action( 'wp_after_insert_post', function( $post_id, $post, $update ) {
	if ( $post->post_type !== 'jobsite_geo' || $post->post_status !== 'publish' ) return;
	$terms = wp_get_post_terms( $post_id, 'jobsite_geo-services' );
	if ( is_wp_error( $terms ) ) return;
	foreach ( $terms as $term ) {
		if ( ! get_term_meta( $term->term_id, 'bp_geo_service_intro', true ) ) {
			bp_geo_generate_term_intro( $term->term_id );
		}
	}
}, 10, 3 );

// Add "Regenerate Intro" row action on the jobsite_geo-services taxonomy list
add_filter( 'jobsite_geo-services_row_actions', function( $actions, $term ) {
	$url = wp_nonce_url(
		add_query_arg( [
			'action'  => 'bp_geo_regen_intro',
			'term_id' => $term->term_id,
		], admin_url( 'edit-tags.php?taxonomy=jobsite_geo-services' ) ),
		'bp_geo_regen_intro_' . $term->term_id
	);
	$actions['regen_intro'] = '<a href="' . esc_url( $url ) . '">Regenerate Intro</a>';
	return $actions;
}, 10, 2 );

// Handle the regenerate action
add_action( 'admin_init', function() {
	if (
		! is_admin() ||
		empty( $_GET['action'] ) || $_GET['action'] !== 'bp_geo_regen_intro' ||
		empty( $_GET['term_id'] )
	) return;

	$term_id = (int) $_GET['term_id'];

	check_admin_referer( 'bp_geo_regen_intro_' . $term_id );

	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_die( 'You do not have permission to do this.' );
	}

	$result = bp_geo_generate_term_intro( $term_id );

	$redirect = add_query_arg(
		[
			'taxonomy'        => 'jobsite_geo-services',
			'bp_regen_status' => is_wp_error( $result ) ? 'error' : 'success',
		],
		admin_url( 'edit-tags.php' )
	);

	wp_safe_redirect( $redirect );
	exit;
} );

// Show admin notice after regenerate
add_action( 'admin_notices', function() {
	if ( empty( $_GET['bp_regen_status'] ) ) return;
	if ( $_GET['bp_regen_status'] === 'success' ) {
		echo '<div class="notice notice-success is-dismissible"><p>Service intro regenerated.</p></div>';
	} elseif ( $_GET['bp_regen_status'] === 'error' ) {
		echo '<div class="notice notice-error is-dismissible"><p>Intro regeneration failed. Check error logs.</p></div>';
	}
} );


function bp_geo_normalize_state( $state ) {
	$state = strtolower( trim( $state ) );

	$map = [
		// Full names
		'alabama' => 'al', 'alaska' => 'ak', 'arizona' => 'az', 'arkansas' => 'ar',
		'california' => 'ca', 'colorado' => 'co', 'connecticut' => 'ct', 'delaware' => 'de',
		'florida' => 'fl', 'georgia' => 'ga', 'hawaii' => 'hi', 'idaho' => 'id',
		'illinois' => 'il', 'indiana' => 'in', 'iowa' => 'ia', 'kansas' => 'ks',
		'kentucky' => 'ky', 'louisiana' => 'la', 'maine' => 'me', 'maryland' => 'md',
		'massachusetts' => 'ma', 'michigan' => 'mi', 'minnesota' => 'mn', 'mississippi' => 'ms',
		'missouri' => 'mo', 'montana' => 'mt', 'nebraska' => 'ne', 'nevada' => 'nv',
		'new hampshire' => 'nh', 'new jersey' => 'nj', 'new mexico' => 'nm', 'new york' => 'ny',
		'north carolina' => 'nc', 'north dakota' => 'nd', 'ohio' => 'oh', 'oklahoma' => 'ok',
		'oregon' => 'or', 'pennsylvania' => 'pa', 'rhode island' => 'ri', 'south carolina' => 'sc',
		'south dakota' => 'sd', 'tennessee' => 'tn', 'texas' => 'tx', 'utah' => 'ut',
		'vermont' => 'vt', 'virginia' => 'va', 'washington' => 'wa', 'west virginia' => 'wv',
		'wisconsin' => 'wi', 'wyoming' => 'wy',
		// Common alternate spellings
		'tex' => 'tx', 'cal' => 'ca', 'cali' => 'ca', 'fla' => 'fl', 'flo' => 'fl',
		'geo' => 'ga', 'ill' => 'il', 'ind' => 'in', 'kan' => 'ks', 'ken' => 'ky',
		'lou' => 'la', 'mas' => 'ma', 'mass' => 'ma', 'mic' => 'mi', 'mich' => 'mi',
		'min' => 'mn', 'minn' => 'mn', 'mis' => 'ms', 'miss' => 'ms', 'mon' => 'mt',
		'neb' => 'ne', 'nebr' => 'ne', 'nev' => 'nv', 'ore' => 'or', 'oreg' => 'or',
		'pen' => 'pa', 'penn' => 'pa', 'tenn' => 'tn', 'vir' => 'va', 'virg' => 'va',
		'was' => 'wa', 'wash' => 'wa', 'wis' => 'wi', 'wisc' => 'wi', 'wyo' => 'wy',
	];

	// Already a valid 2-letter abbreviation
	if ( strlen( $state ) === 2 && ctype_alpha( $state ) ) {
		return $state;
	}

	return $map[ $state ] ?? sanitize_title( $state );
}


// ---------------------------------------------------------
// # Taxonomy helper — match or create term
// ---------------------------------------------------------

function bp_geo_assign_taxonomy_term( $post_id, $base_service, $city, $state, $existing_slugs ) {

	// Normalize service slug
	$base_service = sanitize_title( strtolower( $base_service ) );

	$abbreviations = [
		'/\bac\b/'  => 'air-conditioner',
		'/\ba-c\b/' => 'air-conditioner',
	];
	$base_service = preg_replace( array_keys( $abbreviations ), array_values( $abbreviations ), $base_service );

	$city_slug  = sanitize_title( strtolower( $city ) );
	$state_abbr = bp_geo_normalize_state( $state );

	// Combined services slug: {service}-{city}-{state}  e.g. air-conditioner-repair-frisco-tx
	// Single-dash is fine — archive pages now look up service-type/service-area from post terms
	// rather than parsing the slug, so slug format no longer matters.
	$target_slug = "{$base_service}-{$city_slug}-{$state_abbr}";

	// Assign service-type term (service only, no location)
	if ( ! term_exists( $base_service, 'jobsite_geo-service-types' ) ) {
		wp_insert_term( $base_service, 'jobsite_geo-service-types' );
	}
	wp_set_post_terms( $post_id, [ $base_service ], 'jobsite_geo-service-types', false );

	// Assign combined services term
	if ( ! term_exists( $target_slug, BP_GEO_TAXONOMY ) ) {
		wp_insert_term( $target_slug, BP_GEO_TAXONOMY );
	}
	wp_set_post_terms( $post_id, [ $target_slug ], BP_GEO_TAXONOMY, false );

	error_log( "bp_geo_assign_taxonomy_term: post={$post_id} slug={$target_slug}" );

	return $target_slug;
}


/* BP JOBSITE GEO APP */

define('BP_GEO_SECRET', 'bp-geo-' . hash('sha256', DB_PASSWORD . DB_NAME));

add_action('rest_api_init', function() {
	register_rest_route('bp-geo/v1', '/login',   ['methods' => ['POST','OPTIONS'], 'callback' => 'bp_geo_login',   'permission_callback' => '__return_true']);
	register_rest_route('bp-geo/v1', '/submit',  ['methods' => ['POST','OPTIONS'], 'callback' => 'bp_geo_submit',  'permission_callback' => '__return_true']);
	register_rest_route('bp-geo/v1', '/verify',  ['methods' => ['GET','OPTIONS'],  'callback' => 'bp_geo_verify',  'permission_callback' => '__return_true']);
	register_rest_route('bp-geo/v1', '/geocode', ['methods' => ['GET','OPTIONS'],  'callback' => 'bp_geo_geocode', 'permission_callback' => '__return_true']);
	register_rest_route('bp-geo/v1', '/upload',  ['methods' => ['POST','OPTIONS'], 'callback' => 'bp_geo_upload',  'permission_callback' => '__return_true']);
});

// ── CORS ──────────────────────────────────────────────────────
add_action('init', function() {
	if (isset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']) &&
		$_SERVER['REQUEST_METHOD'] === 'OPTIONS' &&
		strpos($_SERVER['REQUEST_URI'], '/bp-geo/') !== false) {
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type, X-BP-Token');
		http_response_code(200);
		exit();
	}
});

add_filter('rest_pre_serve_request', function($served, $result, $request) {
	if (strpos($request->get_route(), '/bp-geo/') !== false) {
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type, X-BP-Token');
	}
	return $served;
}, 10, 3);

// ── Token auth for ALL REST requests (media uploads, geocode, etc.) ──
// Uses determine_current_user so WP permission checks see the authenticated
// user before the capability check on /wp/v2/media fires.
add_filter('determine_current_user', function($user_id) {
	if (!defined('REST_REQUEST') || !REST_REQUEST) return $user_id;
	if ($user_id) return $user_id; // already authenticated
	$token = $_SERVER['HTTP_X_BP_TOKEN'] ?? '';
	if (empty($token)) return $user_id;
	$user = bp_geo_verify_token($token);
	return $user ? $user->ID : $user_id;
}, 20);

// ── Token helpers ─────────────────────────────────────────────
function bp_geo_generate_token($user_id) {
	$expires = time() + (30 * 24 * 60 * 60);
	$data    = $user_id . '|' . $expires;
	$sig     = hash_hmac('sha256', $data, BP_GEO_SECRET);
	return base64_encode($data . '|' . $sig);
}

function bp_geo_verify_token($token) {
	if (empty($token)) return false;
	try {
		$decoded = base64_decode($token);
		$parts   = explode('|', $decoded);
		if (count($parts) !== 3) return false;
		[$user_id, $expires, $sig] = $parts;
		if (time() > (int)$expires) return false;
		$expected = hash_hmac('sha256', $user_id . '|' . $expires, BP_GEO_SECRET);
		if (!hash_equals($expected, $sig)) return false;
		return get_user_by('id', (int)$user_id) ?: false;
	} catch(Exception $e) { return false; }
}

// ── LOGIN ─────────────────────────────────────────────────────
function bp_geo_login(WP_REST_Request $request) {
	$body     = json_decode($request->get_body(), true);
	$username = sanitize_text_field($body['username'] ?? '');
	$password = $body['password'] ?? '';

	if (empty($username) || empty($password))
		return new WP_Error('missing_fields', 'Username and password required.', ['status' => 400]);

	$user = wp_authenticate($username, $password);
	if (is_wp_error($user))
		return new WP_Error('invalid_credentials', 'Invalid username or password.', ['status' => 401]);

	return rest_ensure_response([
		'token'        => bp_geo_generate_token($user->ID),
		'display_name' => $user->display_name,
		'site_name'    => get_bloginfo('name'),
		'site_url'     => get_site_url(),
	]);
}

// ── VERIFY ────────────────────────────────────────────────────
function bp_geo_verify(WP_REST_Request $request) {
	$user = bp_geo_verify_token($request->get_header('X-BP-Token'));
	if (!$user)
		return new WP_Error('invalid_token', 'Token invalid or expired.', ['status' => 401]);
	return rest_ensure_response(['valid' => true, 'display_name' => $user->display_name]);
}

// ── GEOCODE ───────────────────────────────────────────────────
// Proxy to Google Maps Geocoding API using the site's _PLACES_API key.
// Fixes house number retrieval and guarantees a clean 2-letter state abbreviation.
function bp_geo_geocode(WP_REST_Request $request) {
	$user = bp_geo_verify_token($request->get_header('X-BP-Token'));
	if (!$user)
		return new WP_Error('invalid_token', 'Token invalid or expired.', ['status' => 401]);

	$lat = (float)($request->get_param('lat') ?? 0);
	$lon = (float)($request->get_param('lon') ?? 0);

	if (!$lat || !$lon)
		return new WP_Error('missing_coords', 'lat and lon parameters are required.', ['status' => 400]);

	if (!defined('_PLACES_API') || !_PLACES_API)
		return new WP_Error('no_api_key', 'Google Maps API key not configured on this site.', ['status' => 503]);

	$url = add_query_arg([
		'latlng' => $lat . ',' . $lon,
		'key'    => _PLACES_API,
	], 'https://maps.googleapis.com/maps/api/geocode/json');

	$response = wp_remote_get($url, ['timeout' => 10]);
	if (is_wp_error($response))
		return new WP_Error('geocode_error', $response->get_error_message(), ['status' => 503]);

	$body = json_decode(wp_remote_retrieve_body($response), true);

	if (($body['status'] ?? '') !== 'OK' || empty($body['results']))
		return new WP_Error('no_results', 'No geocode results from Google Maps.', ['status' => 503]);

	$components   = $body['results'][0]['address_components'] ?? [];
	$house_number = '';
	$road         = '';
	$city         = '';
	$state        = '';
	$zip          = '';
	$county       = '';

	foreach ($components as $c) {
		$types = $c['types'] ?? [];
		if (in_array('street_number',              $types)) $house_number = $c['long_name'];
		if (in_array('route',                       $types)) $road         = $c['long_name'];
		if (in_array('locality',                    $types)) $city         = $c['long_name'];
		if (in_array('administrative_area_level_1', $types)) $state        = $c['short_name']; // always 2-letter
		if (in_array('postal_code',                 $types)) $zip          = $c['long_name'];
		if (in_array('administrative_area_level_2', $types)) $county       = $c['long_name'];
	}

	// Fallback for city — some rural areas return no locality component
	if (!$city) {
		foreach ($components as $c) {
			$types = $c['types'] ?? [];
			if (in_array('neighborhood', $types) || in_array('sublocality', $types) || in_array('administrative_area_level_3', $types)) {
				$city = $c['long_name'];
				break;
			}
		}
	}

	return rest_ensure_response([
		'house_number' => $house_number,
		'road'         => $road,
		'city'         => $city,
		'state'        => $state,
		'zip'          => $zip,
		'county'       => $county,
		'lat'          => $lat,
		'lon'          => $lon,
	]);
}

// ── UPLOAD ────────────────────────────────────────────────────
// Custom photo upload endpoint — bypasses /wp/v2/media capability check
// (upload_files) which field tech accounts may not have.
function bp_geo_upload(WP_REST_Request $request) {
	$user = bp_geo_verify_token($request->get_header('X-BP-Token'));
	if (!$user)
		return new WP_Error('invalid_token', 'Token invalid or expired.', ['status' => 401]);

	if (empty($_FILES['file']))
		return new WP_Error('no_file', 'No file received.', ['status' => 400]);

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	wp_set_current_user($user->ID);

	$file = wp_handle_upload($_FILES['file'], ['test_form' => false]);

	if (!empty($file['error']))
		return new WP_Error('upload_failed', $file['error'], ['status' => 500]);

	$title = sanitize_text_field($_POST['title'] ?? basename($file['file']));

	$attach_id = wp_insert_attachment([
		'post_mime_type' => $file['type'],
		'post_title'     => $title,
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_author'    => $user->ID,
	], $file['file']);

	if (is_wp_error($attach_id))
		return new WP_Error('attach_failed', $attach_id->get_error_message(), ['status' => 500]);

	wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $file['file']));

	return rest_ensure_response(['id' => $attach_id, 'url' => $file['url']]);
}

// ── SUBMIT ────────────────────────────────────────────────────
function bp_geo_submit(WP_REST_Request $request) {
	$user = bp_geo_verify_token($request->get_header('X-BP-Token'));
	if (!$user)
		return new WP_Error('invalid_token', 'Token invalid or expired. Please log in again.', ['status' => 401]);

	$body = json_decode($request->get_body(), true);
	$meta = $body['meta'] ?? [];

	$customer = sanitize_text_field($meta['bp_technician_name'] ?? '');
	$address  = sanitize_text_field($meta['address'] ?? '');
	$city     = sanitize_text_field($meta['city'] ?? '');
	$state    = sanitize_text_field($meta['state'] ?? '');
	$zip      = sanitize_text_field($meta['zip'] ?? '');
	$desc     = sanitize_textarea_field($body['content'] ?? '');
	$date     = sanitize_text_field($meta['job_date'] ?? date('Y-m-d'));
	$title    = sanitize_text_field($body['title'] ?? $customer);

	if (empty($customer) || empty($address) || empty($city) || empty($desc))
		return new WP_Error('missing_fields', 'Customer name, address, city, and description are required.', ['status' => 400]);

	// Normalize state — server-side safeguard in case a full name slips through
	if (strlen($state) > 2) {
		$stateMap = [
			'Alabama'=>'AL','Alaska'=>'AK','Arizona'=>'AZ','Arkansas'=>'AR','California'=>'CA',
			'Colorado'=>'CO','Connecticut'=>'CT','Delaware'=>'DE','Florida'=>'FL','Georgia'=>'GA',
			'Hawaii'=>'HI','Idaho'=>'ID','Illinois'=>'IL','Indiana'=>'IN','Iowa'=>'IA','Kansas'=>'KS',
			'Kentucky'=>'KY','Louisiana'=>'LA','Maine'=>'ME','Maryland'=>'MD','Massachusetts'=>'MA',
			'Michigan'=>'MI','Minnesota'=>'MN','Mississippi'=>'MS','Missouri'=>'MO','Montana'=>'MT',
			'Nebraska'=>'NE','Nevada'=>'NV','New Hampshire'=>'NH','New Jersey'=>'NJ','New Mexico'=>'NM',
			'New York'=>'NY','North Carolina'=>'NC','North Dakota'=>'ND','Ohio'=>'OH','Oklahoma'=>'OK',
			'Oregon'=>'OR','Pennsylvania'=>'PA','Rhode Island'=>'RI','South Carolina'=>'SC',
			'South Dakota'=>'SD','Tennessee'=>'TN','Texas'=>'TX','Utah'=>'UT','Vermont'=>'VT',
			'Virginia'=>'VA','Washington'=>'WA','West Virginia'=>'WV','Wisconsin'=>'WI','Wyoming'=>'WY',
		];
		$state = $stateMap[trim($state)] ?? strtoupper(substr(trim($state), 0, 2));
	} else {
		$state = strtoupper(trim($state));
	}

	wp_set_current_user($user->ID);

	// Guard: prevent bp_jobsite_setup() from running during wp_insert_post()
	// with empty ACF fields. We'll set taxonomy inline after fields are saved.
	$GLOBALS['bp_jobsite_setup_running'] = true;

	$post_id = wp_insert_post([
		'post_title'   => $title,
		'post_content' => $desc,
		'post_status'  => 'draft',
		'post_type'    => 'jobsite_geo',
		'post_author'  => $user->ID,
	], true);

	if (is_wp_error($post_id)) {
		unset($GLOBALS['bp_jobsite_setup_running']);
		return new WP_Error('insert_failed', $post_id->get_error_message(), ['status' => 500]);
	}

	// ── ACF fields ────────────────────────────────────────────
	update_field('job_date', $date,    $post_id);
	update_field('address',  $address, $post_id);
	update_field('city',     $city,    $post_id);
	update_field('state',    $state,   $post_id);
	update_field('zip',      $zip,     $post_id);

	// Photos — ACF image fields with return_format='id' expect a plain integer
	$photo_fields = ['jobsite_photo_1','jobsite_photo_2','jobsite_photo_3','jobsite_photo_4'];
	$first_photo  = 0;
	foreach ($photo_fields as $field_name) {
		$photo_id = intval($meta[$field_name] ?? 0);
		if ($photo_id > 0) {
			update_field($field_name, $photo_id, $post_id);
			if (!$first_photo) $first_photo = $photo_id;
		}
	}
	if ($first_photo) set_post_thumbnail($post_id, $first_photo);

	// ── Service-area taxonomy ─────────────────────────────────
	// bp_jobsite_setup() is guarded above so it won't run with empty fields.
	// Set the taxonomy here so drafts are filterable in the admin right away.
	if ($city && $state) {
		$location = sanitize_title(ucwords($city) . '-' . strtoupper($state));
		if (!term_exists($location, 'jobsite_geo-service-areas'))
			wp_insert_term($location, 'jobsite_geo-service-areas');
		wp_set_post_terms($post_id, [$location], 'jobsite_geo-service-areas', false);
	}

	// ── Post meta ─────────────────────────────────────────────
	update_post_meta($post_id, 'bp_raw_description',   $desc);
	update_post_meta($post_id, 'bp_submission_source', 'Battle Plan GEO App');
	update_post_meta($post_id, 'bp_customer_name',     $customer);
	if (!empty($meta['bp_geo_lat'])) {
		update_post_meta($post_id, 'bp_geo_lat',    sanitize_text_field($meta['bp_geo_lat']));
		update_post_meta($post_id, 'bp_geo_lon',    sanitize_text_field($meta['bp_geo_lon']));
		update_post_meta($post_id, 'bp_geo_county', sanitize_text_field($meta['bp_geo_county'] ?? ''));
	}

	unset($GLOBALS['bp_jobsite_setup_running']);

	// Publish the post now that all fields are saved.
	// (Created as draft above so the required-field validation hook doesn't
	// fire before ACF fields exist. Publish here after everything is in place.)
	wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);

	return rest_ensure_response([
		'id'      => $post_id,
		'link'    => get_permalink($post_id),
		'status'  => 'publish',
		'message' => 'Jobsite submitted successfully.',
	]);
}

// ============================================================
// END BATTLE PLAN GEO APP
// ============================================================
