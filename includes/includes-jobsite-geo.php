<?php
/* Battle Plan Web Design Jobsite GEO
 
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

$get_jobsite_geo = get_option('jobsite_geo');	

// Housecall Pro Webhook
add_action('rest_api_init', function() {
	register_rest_route('hcpro/v1', '/job-callback', [
		'methods' => 'POST',
		'callback' => 'hcpro_handle_job_webhook',
		'permission_callback' => '__return_true'
	]);
});

function hcpro_handle_job_webhook($req) {
	// 🔒 Security check
	
	$get_jobsite_geo = get_option('jobsite_geo');	
	
	if ( empty($_GET['token']) || $_GET['token'] !== $get_jobsite_geo['token'] ) {
		return new WP_REST_Response(['error' => 'Invalid token'], 403);
	}

	if (empty($get_jobsite_geo['fsm_brand']) || $get_jobsite_geo['fsm_brand'] !== 'Housecall Pro') {
		return new WP_REST_Response(['error' => 'Not using Housecall Pro'], 403);
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// 🧾 Decode JSON
	$data = json_decode($req->get_body());
	if (!$data) return new WP_REST_Response(['error' => 'Invalid JSON'], 400);

	// 🧩 Handle test webhook
	if (isset($data->foo) && $data->foo === 'bar') {
		return new WP_REST_Response(['test' => 'Webhook verified OK'], 200);
	}

	if (empty($data->event) || empty($data->job->id)) {
		return new WP_REST_Response(['error' => 'Missing job info'], 400);
	}

	// Log payload (debugging only)
	file_put_contents(WP_CONTENT_DIR . '/hcp-last-payload.txt', print_r($data, true));

	// Shortcuts
	$job  = $data->job;
	$cust = $job->customer ?? new stdClass();
	$addr = $job->address ?? new stdClass();

	// 🧩 Parse notes for description and photo captions
	$description_note = '';
	$photo_note = '';
	$captions = [];

	// We’ll track whether a valid publishable note was found
	$has_publishable_note = false;

	if (!empty($job->notes)) {
		foreach ($job->notes as $n) {
			$content = trim($n->content);

			// 🔹 If it starts with *** (3+ asterisks), treat as the publishable description
			if (preg_match('/^\*{3,}/', $content)) {
				$has_publishable_note = true;
				// Remove all leading asterisks and whitespace
				$description_note = trim(preg_replace('/^\*{3,}\s*/', '', $content));
			}

			// 🔹 If it contains "Photo" (or Pic/Image), treat as the photo captions note
			elseif (preg_match('/\b(Photo|Pic|Image)\s*\d+/i', $content)) {
				$photo_note = $content;
			}
		}
	}

	// 🚫 If no valid description note found, stop — we don’t want to publish this job
	if (!$has_publishable_note) {
		return new WP_REST_Response(['skipped' => 'No publishable note found (missing ***)'], 200);
	}

	// 🧩 Parse photo captions if a "Photo" note exists
	if ($photo_note) {
		// Normalize whitespace
		$text = str_replace(["\r", "\n"], ' ', $photo_note);
		$text = preg_replace('/\s+/', ' ', $text);

		// 🧩 Match "Photo"/"Pic"/"Image" <num> followed by optional bullet/punctuation, then caption
		// Capture until next "Photo <num>" or end
		$pattern = '/\b(?:Photo|Pic|Image)\s*(\d+)\s*[\-\–—\*\:\.\=\)\s]*([^\b]*?)(?=\b(?:Photo|Pic|Image)\s*\d+|$)/i';

		if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $m) {
				$idx = (int)$m[1];
				$cap = trim($m[2]);

				// Clean up leftover leading symbols
				$cap = preg_replace('/^[\s\-\–—\*\:\.\=\)\(]+/', '', $cap);
				$cap = trim($cap, " \t\n\r\0\x0B-–—*:=.");
				if ($cap !== '') $captions[$idx] = $cap;
			}
		}
		
		$max_photos = count($captions);
	}


	if (!$description_note && isset($job->description)) $description_note = $job->description;

	// 🧱 Build job info
	$title   = trim(($cust->first_name ?? '') . ' ' . ($cust->last_name ?? ''));
	$content = $description_note;
	$street  = $addr->street ?? '';
	$city    = $addr->city ?? '';
	$state   = $addr->state ?? '';
	$zip     = isset($addr->zip) ? preg_replace('/^(\d{5}).*/', '$1', $addr->zip) : '';
	$date    = $job->work_timestamps->completed_at ?? '';
	$invoice = $job->invoice_number ?? '';
	$total   = isset($job->total_amount) ? $job->total_amount / 100 : 0;
	$job_id  = $job->id ?? '';

	// 🧾 Create or update post
	$existing = get_posts([
		'post_type'   => 'jobsite_geo',
		'meta_key'    => 'hcp_job_id',
		'meta_value'  => $job_id,
		'numberposts' => 1,
		'post_status' => ['publish', 'draft', 'pending']
	]);

	if ($existing) {
		$post_id = $existing[0]->ID;
		wp_update_post([
			'ID'           => $post_id,
			'post_title'   => $title,
			'post_content' => $content,
		]);
	} else {
		$post_id = wp_insert_post([
			'post_title'   => $title,
			'post_content' => $content,
			'post_type'    => 'jobsite_geo',
			'post_status'  => 'publish'
		]);
	}

	if (!$post_id) return new WP_REST_Response(['error' => 'Failed to create post'], 500);
	
	
	// 🧩 Update ACF/meta fields
	update_field('hcp_job_id', $job_id, $post_id);
	update_field('invoice_number', $invoice, $post_id);
	update_field('customer_name', $title, $post_id);
	update_field('address', $street, $post_id);
	update_field('city', $city, $post_id);
	update_field('state', $state, $post_id);
	update_field('zip', $zip, $post_id);
	update_field('job_date', $date, $post_id);
	update_field('notes', $description_note, $post_id);
	update_field('total_amount', $total, $post_id);
	update_post_meta($post_id, '_hcp_raw', $req->get_body());
	if (!empty($job->assigned_employees[0]->name)) {
		update_post_meta($post_id, '_hcp_tech_name', $job->assigned_employees[0]->name);
	}

	// 📷 Handle attachments
	$saved_hcp_ids = get_post_meta($post_id, '_hcp_attachment_ids', true);
	if (!is_array($saved_hcp_ids)) $saved_hcp_ids = [];
	$current_hcp_ids = !empty($job->attachments) ? array_map(fn($a) => $a->id, $job->attachments) : [];
	
	$photo_index = 1;

	if (!empty($job->attachments)) {
		$attachments = array_reverse($job->attachments ?? []);
		
		foreach ($attachments as $a) {
			if ($photo_index > $max_photos && $max_photos > 0) break;
			
			// Skip existing ones
			if (in_array($a->id, $saved_hcp_ids, true)) {
				$caption_text = $captions[$photo_index] ?? '';
				update_field("jobsite_photo_{$photo_index}_alt", $caption_text, $post_id);
				$photo_index++;
				continue;
			}

			// Download new image
			$tmp = download_url($a->url);
			if (is_wp_error($tmp)) continue;

			$file = [
				'name'     => basename($a->file_name),
				'type'     => $a->file_type,
				'tmp_name' => $tmp,
				'error'    => 0,
				'size'     => filesize($tmp)
			];
			$m = wp_handle_sideload($file, ['test_form' => false]);
			if (empty($m['file'])) continue;

			$aid = wp_insert_attachment([
				'post_mime_type' => $file['type'],
				'post_title'     => sanitize_file_name($file['name']),
				'post_status'    => 'inherit'
			], $m['file'], $post_id);

			wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $m['file']));

			// Store the HCP attachment ID on this image
			update_post_meta($aid, '_hcp_attachment_id', $a->id);

			// ACF
			$caption_text = $captions[$photo_index] ?? '';
			update_field("jobsite_photo_{$photo_index}", $aid, $post_id);
			update_field("jobsite_photo_{$photo_index}_alt", $caption_text, $post_id);

			// Media Library caption/alt
			if ($caption_text) {
				wp_update_post(['ID' => $aid, 'post_excerpt' => $caption_text]);
				update_post_meta($aid, '_wp_attachment_image_alt', $caption_text);
			}

			$saved_hcp_ids[] = $a->id;
			$photo_index++;
		}

		// 🧹 Remove old photos no longer in payload
		$removed = array_diff($saved_hcp_ids, $current_hcp_ids);
		if (!empty($removed)) {
			foreach ($removed as $old_id) {
				for ($i = 1; $i <= 20; $i++) {
					$field = "jobsite_photo_{$i}";
					$aid = get_field($field, $post_id);
					if ($aid && get_post_meta($aid, '_hcp_attachment_id', true) === $old_id) {
						update_field($field, null, $post_id);
						update_field("{$field}_alt", '', $post_id);
						wp_delete_attachment($aid, true); // full delete
					}
				}
			}
		}
		update_post_meta($post_id, '_hcp_attachment_ids', $current_hcp_ids);
	}

	bp_jobsite_setup($post_id, 'Housecall Pro');	

	return new WP_REST_Response(['success' => true, 'post_id' => $post_id], 200);
}

	

	
	


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
function bp_match_key_from_title($title){
	$key = sanitize_title(trim((string)$title));
	return $key ?: '';
}

// Orient uploaded photos correctly
add_filter('wp_generate_attachment_metadata', function($metadata, $attachment_id) {
	$filepath = get_attached_file($attachment_id);
	$image = wp_get_image_editor($filepath);
	if (!is_wp_error($image)) {
		$exif = @exif_read_data($filepath);
		if (!empty($exif['Orientation'])) {
			switch ($exif['Orientation']) {
				case 3: $image->rotate(180); break;
				case 6: $image->rotate(-90); break;
				case 8: $image->rotate(90); break;
			}
			$image->save($filepath);
		}
	}
	return $metadata;
}, 10, 2);



function bp_jobsite_setup($post_id, $user) {	
	$customer_info = customer_info();
	$get_jobsite_geo = get_option('jobsite_geo');	
	$current_user = wp_get_current_user();

// Sync jobs with reviews
	if (get_post_type($post_id) === 'testimonials') {
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
	}

	if ( get_post_type($post_id) === 'jobsite_geo' ) {			
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
	}

// Find the longitude and latitude based on customer address
    $address = trim(esc_attr(get_field("address", $post_id)));
    $city = trim(esc_attr(get_field( "city", $post_id)));
	$state = strtoupper(trim(esc_attr(get_field( "state", $post_id))));
    $zip = trim(esc_attr(get_field( "zip", $post_id)));	

    $stateAbbrs = ["Alabama" => "AL", "Alaska" => "AK", "Arizona" => "AZ", "Arkansas" => "AR", "California" => "CA", "Colorado" => "CO", "Connecticut" => "CT", "Delaware" => "DE", "Florida" => "FL", "Georgia" => "GA", "Hawaii" => "HI", "Idaho" => "ID", "Illinois" => "IL", "Indiana" => "IN", "Iowa" => "IA", "Kansas" => "KS",
    "Kentucky" => "KY", "Louisiana" => "LA", "Maine" => "ME", "Maryland" => "MD", "Massachusetts" => "MA", "Michigan" => "MI", "Minnesota" => "MN", "Mississippi" => "MS",
    "Missouri" => "MO", "Montana" => "MT", "Nebraska" => "NE", "Nevada" => "NV", "New Hampshire" => "NH", "New Jersey" => "NJ", "New Mexico" => "NM", "New York" => "NY",
    "North Carolina" => "NC", "North Dakota" => "ND", "Ohio" => "OH", "Oklahoma" => "OK", "Oregon" => "OR", "Pennsylvania" => "PA", "Rhode Island" => "RI", "South Carolina" => "SC", "South Dakota" => "SD", "Tennessee" => "TN", "Texas" => "TX", "Utah" => "UT", "Vermont" => "VT", "Virginia" => "VA", "Washington" => "WA", "West Virginia" => "WV",
    "Wisconsin" => "WI", "Wyoming" => "WY", "Tex" => "TX", "Calif" => "CA", "Penn" => "PA"];

    foreach ($stateAbbrs as $name => $abbreviation) {
        if ($state === strtoupper($name)) $state = $abbreviation;
    };
	
    if ($address !== '' && $city !== '' && $state !== '' && $zip !== '') :
        $new_address = $address.', '.$city.', '.$state.' '.$zip;

        // check last saved full address
        $last_address = get_post_meta($post_id, '_last_geocode_address', true);

        if ($new_address !== $last_address) {
            $googleAPI = 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($new_address).'&key='._PLACES_API;

            $response = wp_remote_get($googleAPI, ['timeout' => 10]);
            if (is_wp_error($response)) :
                emailMe('Geocoding HTTP Error - '.$customer_info['name'], $response->get_error_message());
            else :
                $http_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if ($http_code !== 200 || !is_array($data)) :
                    $html = 'HTTP '.$http_code.'<br><br>Raw body:<br><pre>'.esc_html($body).'</pre>';
                    emailMe('Geocoding Bad Response - '.$customer_info['name'], $html);
                elseif (($data['status'] ?? '') === 'OK') :
                    update_post_meta($post_id, 'geocode', $data['results'][0]['geometry']['location']);
                    update_post_meta($post_id, '_last_geocode_address', $new_address); // save for future comparison
                else :
                    $err = ($data['error_message'] ?? 'No error_message returned.');
                    $html = $new_address.'<br><br>Status: '.esc_html($data['status']).'<br>Error: '.esc_html($err);
                    emailMe('Geocoding API Error - '.$customer_info['name'], $html);
                endif;
            endif;
        }
    endif;

//Generate service, location and technician tags	
    // add city-state as location tag
    if ($city && $state) {
        $location = sanitize_title(ucwords($city) . '-' . strtoupper($state));

        $term = term_exists($location, 'jobsite_geo-service-areas');
        if (empty($term)) $term = wp_insert_term($location, 'jobsite_geo-service-areas');
        if (!is_wp_error($term)) wp_set_post_terms($post_id, [$location], 'jobsite_geo-service-areas', false);
    }

    // add username as technician tag
    if ( in_array('bp_jobsite_geo', $current_user->roles) ) {
        $tech_tag = $current_user->user_firstname . '-' . $current_user->user_lastname;
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
    $service = '';
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
    if ( strtolower($btVal) === "generalcontractor" ) :		
        foreach ( array( 'gutter', 'seamless' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'gutters';
                break; 
            } 
        }

        foreach ( array( 'insulation', 'insulate', 'fiberglass' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'insulation';
                break; 
            } 
        }	
    endif;		


    // Customize for Plumber website
    if ( strtolower($btVal) === "plumber" ) :
        $service = 'plumbing-services';	
	
        $type = str_contains($description, 'repair') || str_contains($description, 'service') ? '-repair' :
            (str_contains($description, 'install') || str_contains($description, 'replace') ? '-installation' :
            (esc_attr(get_field('new_brand')) ? '-installation' : '-repair'));	
	
	   foreach ( array( 'water heater', 'tank' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'water-heater'.$type;				
                break; 
            } 
        }
	
	   foreach ( array( 'drain', 'clog', 'clogged', 'blockage' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'clogged-drains';
                break; 
            } 
        }
	
	   foreach ( array( 'bathroom', 'toilet', 'tub', 'shower' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'bathroom-plumbing-services';
                break; 
            } 
        }
	
	   foreach ( array( 'kitchen', 'dish washer', 'refrigerator', 'fridge', 'ice maker' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'kitchen-plumbing-services';
                break; 
            } 
        }
	
	   foreach ( array( 'gas line', 'gas service', 'gas leak' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'gas-line'.$type;
                break; 
            } 
        }
	
	   foreach ( array( 'water pressure', 'pressure reducing valve', 'prv' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'pressure-reducing-valves';
                break; 
            } 
        }
	
	   foreach ( array( 'lighting', 'gas lamps', 'lantern' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'outdoor-lighting'.$type;
                break; 
            } 
        }
		
    endif;		
	

    // Customize for HVAC website
    if ( $customer_info['site-type'] === "hvac" ) :		
        $equipment = array (
            'air-conditioner' 	=> array( 'air conditioner', 'air conditioning', 'cooling', 'a/c', 'ac', 'compressor', 'evaporator', 'condenser', 'drain line', 'refrigerant'),
            'heating' 			=> array( 'heater', 'heating', 'furnace' ),
            'hvac'				=> array( 'hvac', 'fan motor', 'blower', 'mini split' ),
            'thermostat'		=> array( 'thermostat', 't-stat', 'tstat' ),
        );

        $type = str_contains($description, 'repair') || str_contains($description, 'service') ? '-repair' :
            (str_contains($description, 'install') || str_contains($description, 'replace') ? '-installation' :
            (esc_attr(get_field('new_brand')) ? '-installation' : '-repair'));

        foreach ( array( 'allergies', 'duct cleaning', 'indoor air', 'air quality', 'clean air' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'indoor-air-quality';
                break; 
            } 
        }

        foreach ( array( 'maintenance', 'tune up', 'tune-up', 'check up', 'check-up', 'inspection' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'hvac-maintenance';
                break; 
            } 
        }	

        foreach ( array( 'dyer vent' , 'lint', 'lent' ) as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $service = 'dryer-vent-cleaning';
                break; 
            } 
        }		

        if (!$service) {
            foreach ($equipment as $tag => $keywords) {
                foreach ($keywords as $keyword) {
                    if (stripos($description, $keyword) !== false) {
                        $service = $tag.$type;
                        break 2;
                    }
                }
            }
        }
    endif;

    $service = $service ?: 'service-area';
	$service && $location
          ? wp_set_object_terms(
              $post_id,
              [$service.'--'.strtolower($location)],
              'jobsite_geo-services',
              false // overwrite existing terms
          )
          : null;


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
    set_post_thumbnail($post_id, esc_attr(get_field( "jobsite_photo_1")) );	

	
// Send email when jobsite post is updated or created
    $created  = new DateTime( $post_id->post_date_gmt );
    $modified = new DateTime( $post_id->post_modified_gmt );
    $diff = $created->diff( $modified );
    $seconds = ((($diff->y * 365.25 + $diff->m * 30 + $diff->d) * 24 + $diff->h) * 60 + $diff->i)*60 + $diff->s;
    $action = $seconds <= 2 ? 'created' : 'updated';
    $get_jobsite_geo = get_option('jobsite_geo');	
    $notifyTo = $get_jobsite_geo['notify'] != 'false' ? $get_jobsite_geo['notify'] : '';
    $notifyBc = $get_jobsite_geo['copy_me'] == 'true' ? 'info@battleplanwebdesign.com' : '';

    if ( $notifyTo == '' && $notifyBc != '' ) :
        $notifyTo = $notifyBc;
        $notifyBc = '';
    endif;
	
	$display_user = $user === 'user' ? $current_user->first_name.' '.$current_user->last_name : $user;

    if ( $notifyTo != '' ) :	
        $subject = 'Jobsite '.$action.' by '.$display_user;
        $message = $display_user.' '.$action.' a jobsite post'.($new_address != '' ? ' for this address: '.$new_address.'.': '.');
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: Website Administrator ' . "\r\n";
        $headers[] = "Reply-To: noreply@admin.".str_replace('https://', '', get_bloginfo('url'));
        $headers[] = 'Bcc: <'.$notifyBc.'>';

        wp_mail($notifyTo, $subject, $message, $headers);
    endif;	
}
	

// Save important info to meta data upon publishing or updating post
add_action('save_post', 'battleplan_saveJobsite', 10, 3);
function battleplan_saveJobsite($post_id, $post, $update) {

	if ( (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (defined('DOING_AJAX') && DOING_AJAX) ) return;
	if (!in_array(get_post_type($post_id), ['jobsite_geo','testimonials'], true)) return;
	
	bp_jobsite_setup($post_id, 'user');	
	
	if ( get_option('bp_setup_2025_09_29') !== "completed" ) : 
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
	endif;
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
			<?php foreach ($terms as $term) : 
				$parts = explode('--', esc_html($term->name));

				if (count($parts) === 2) {
					$service_part = bp_format_service($parts[0]);
					$location_part = bp_format_location($parts[1]);
					$formatted_title = $service_part . ' in ' . $location_part;
				}?>
				<option value="<?php echo esc_url(get_term_link($term)); ?>"><?php echo $formatted_title; ?></option>
			<?php endforeach; ?>
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
	
	register_taxonomy( 'jobsite_geo-techs', array( 'jobsite_geo' ), array(
		'labels'=>array(
			'name'=>			_x( 'Technicians', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Technician', 'Taxonomy Singular Name', 'text_domain' ),
        	'menu_name' => 		'Technicians',
		),
        'public' => 			true,
		'hierarchical'=>		false,
        'show_ui' => 			true,
        'show_in_menu' => 		true,
        'show_in_nav_menus' => 	true,
        'show_admin_column' => 	true,
        'rewrite' => array(
            'slug' => 			'tech',
            'with_front' => 	false,
        ),
	));
	
	register_taxonomy( 'jobsite_geo-services', array( 'jobsite_geo' ), array(
		'labels'=>array(
			'name'=>			_x( 'Services', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Service', 'Taxonomy Singular Name', 'text_domain' ),
        	'menu_name' => 		'Services',
		),
        'public' => 			true,
		'hierarchical'=>		false,
        'show_ui' => 			true,
        'show_in_menu' => 		true,
        'show_in_nav_menus' => 	true,
        'show_admin_column' => 	true,
		'rewrite' => array(
            'slug' => 			'service',
            'with_front' => 	false,
        ),
	));
	
	register_taxonomy( 'jobsite_geo-service-areas', array( 'jobsite_geo' ), array(
		'labels'=>array(
			'name'=>			_x( 'Service Areas', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Service Area', 'Taxonomy Singular Name', 'text_domain' ),
        	'menu_name' => 		'Service Areas',
		),
        'public' => 			true,
		'hierarchical'=>		false,
        'show_ui' => 			true,
        'show_in_menu' => 		true,
        'show_in_nav_menus' => 	true,
        'show_admin_column' => 	true,
        'rewrite' => array(
            'slug' => 			'service-area',
            'with_front' => 	false,
        ),
	));

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
				'key' => 'field_old_brand',
				'label' => 'What BRAND equipment does/did customer have?',
				'name' => 'old_brand',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_old_equipment',
				'label' => 'What TYPE of equipment does/did customer have?',
				'name' => 'old_equipment',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),			
			array(
				'key' => 'field_new_brand',
				'label' => 'If you replaced equipment, what BRAND did you use?',
				'name' => 'new_brand',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_new_equipment',
				'label' => 'If you replaced equipment, what TYPE was it?',
				'name' => 'new_equipment',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),						
			array(
				'key' => 'field_auto_make',
				'label' => 'What MAKE of vehicle does customer have?',
				'name' => 'auto_make',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_auto_model',
				'label' => 'What MODEL of vehicle does customer have?',
				'name' => 'auto_model',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_auto_year',
				'label' => 'What YEAR is the customer\'s vehicle?',
				'name' => 'auto_year',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
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
				'bidirectional' => 0,
				'ui' => 1,
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

add_filter('acf/validate_value', function($valid, $value, $field, $input){

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

/*--------------------------------------------------------------
# Basic Site Setup
--------------------------------------------------------------*/

add_filter( 'body_class', function( $classes ) {
	if ( is_post_type_archive('jobsite_geo') || is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') || is_tax('jobsite_geo-techs') ) :
		$classes = str_replace(array('sidebar-line', 'sidebar-right', 'sidebar-left'), 'sidebar-none', $classes);
		return array_merge( $classes, array( 'jobsite_geo' ) );
	endif;
	return $classes;
}, 30);

add_action( 'pre_get_posts', 'battleplan_override_jobsite_query', 10 );
function battleplan_override_jobsite_query( $query ) {
	if (!is_admin() && $query->is_main_query()) :		
		if ( is_post_type_archive('jobsite_geo') || is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') || is_tax('jobsite_geo-techs') ) : 
			$query->set( 'post_type','jobsite_geo');
			$query->set( 'posts_per_page', 30);
			$query->set( 'meta_key', 'job_date' );
        	$query->set( 'orderby', 'meta_value' );
        	$query->set( 'order', 'DESC');
		endif;
	endif; 
}

add_filter('template_include', 'battleplan_jobsite_template');
function battleplan_jobsite_template($template) {
    if ( is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') || is_tax('jobsite_geo-techs') ) :
	
		$template = get_template_directory().'/archive-jobsite_geo.php';
		$sep = ' · ';
        $jobsite_term = get_queried_object();
	
		if ($jobsite_term) {
			if ( is_tax('jobsite_geo-services') && $jobsite_service !== "service-area") :
				$term_parts = explode('--', $jobsite_term->name);
				$jobsite_service = isset($term_parts[0]) ? $term_parts[0] : null;
				$jobsite_location = isset($term_parts[1]) ? $term_parts[1] : null;			
				$GLOBALS['jobsite_geo-service'] = bp_format_service($jobsite_service);
			else:
				$jobsite_location = $jobsite_term->name;			
				$GLOBALS['jobsite_geo-service'] = "Service";
			endif;
			
			$GLOBALS['jobsite_geo-city-state'] = bp_format_location($jobsite_location);
			$splitLoc = explode(', ', $GLOBALS['jobsite_geo-city-state']);
			$GLOBALS['jobsite_geo-city'] = $splitLoc[0];
			$GLOBALS['jobsite_geo-state'] = $splitLoc[1];			
		}

		if ( is_tax('jobsite_geo-service-areas') || is_tax('jobsite_geo-services') ) :	
			$GLOBALS['jobsite_geo-headline'] = $GLOBALS['jobsite_geo-service'].' in '.$GLOBALS['jobsite_geo-city-state'];
            $GLOBALS['jobsite_geo-page_title'] = $GLOBALS['jobsite_geo-headline'].$sep.get_bloginfo('name');
	
			$service_full = [
							"Air Conditioner Installation", 
							"Heating Installation", 
							"Thermostat Installation", 
							"Air Conditioner Repair", 
							"Heating Repair", 
							"Thermostat Repair", 
							"HVAC Maintenance", 
							"Plumbing Services"];
			$service_short = ["Recent air conditioner replacements in the ".$GLOBALS['jobsite_geo-city']." area.", 
							  $GLOBALS['jobsite_geo-city']." customers we have recently helped with new heating equipment.", 
							  "Recent thermostat installations for residents of ".$GLOBALS['jobsite_geo-city'].".", 
							  "We have recently repaired air conditioners for these ".$GLOBALS['jobsite_geo-city']." customers.",
							  "Recent heating system repairs for customers living in ".$GLOBALS['jobsite_geo-city'].".", 
							  "We recently repaired thermostats in these ".$GLOBALS['jobsite_geo-city']." locations.", 
							  $GLOBALS['jobsite_geo-city']." customers that recently trusted us with their HVAC service.", 
							  "Plumbing issues we have recently solved for customers in the ".$GLOBALS['jobsite_geo-city']." area."];	
			$GLOBALS['jobsite_geo-map-caption'] = str_replace($service_full, $service_short, $GLOBALS['jobsite_geo-service']);	

			$plural = ( stripos($GLOBALS['jobsite_geo-service'], 'services') === false && stripos($GLOBALS['jobsite_geo-service'], 'maintenance') === false ) ? 's' : '';
	
			$GLOBALS['jobsite_geo-bottom-headline'] = "Recent ".$GLOBALS['jobsite_geo-service'].$plural." In ".$GLOBALS['jobsite_geo-city'];

			$query = bp_WP_Query('landing', [
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'title'          => $GLOBALS['jobsite_geo-city'] . ', ' . $GLOBALS['jobsite_geo-state']
			]);

			if($query->have_posts()) :
				while($query->have_posts()) :
					$query->the_post();
					$GLOBALS['jobsite_geo-content'] = apply_filters('the_content', get_the_content());
					$GLOBALS['jobsite_geo-page_title'] = str_replace('%%sep%%', $sep, get_post_meta(get_the_ID(), '_yoast_wpseo_title', true) );
					$GLOBALS['jobsite_geo-page_desc'] = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true);
					$GLOBALS['mapGrid'] = "1-1";
					//$position = strpos($GLOBALS['jobsite_geo-page_title'], '·');
					//if ($position !== false) $GLOBALS['jobsite_geo-headline'] = trim(substr($GLOBALS['jobsite_geo-page_title'], 0, $position));
				endwhile; 
			else:
				$query = bp_WP_Query('landing', [
					'posts_per_page' => 1,
					'name'           => 'jobsite-geo-default',
					'post_status'    => 'publish'
				]);

				if ($query->have_posts()) :
					while($query->have_posts()) :
						$query->the_post();
						$GLOBALS['jobsite_geo-content'] = apply_filters('the_content', get_the_content());
						$GLOBALS['jobsite_geo-page_title'] = str_replace('%%sep%%', $sep, get_post_meta(get_the_ID(), '_yoast_wpseo_title', true) ).$sep.$GLOBALS['jobsite_geo-city'].", ".$GLOBALS['jobsite_geo-state'];
						$GLOBALS['jobsite_geo-page_desc'] = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true);
						$GLOBALS['mapGrid'] = "1-1";
						//$position = strpos($GLOBALS['jobsite_geo-page_title'], '·');
						//if ($position !== false) $GLOBALS['jobsite_geo-headline'] = trim(substr($GLOBALS['jobsite_geo-page_title'], 0, $position)).' in '.$GLOBALS['jobsite_geo-city'].", ".$GLOBALS['jobsite_geo-state'];
					endwhile; 
				else:
					$GLOBALS['jobsite_geo-content'] = '';
					$GLOBALS['mapGrid'] = "1";
				endif;
			endif;		
			wp_reset_postdata();
	
		elseif ( is_tax('jobsite_geo-techs') ) :		
            $jobsite_term = get_term_by('slug', $jobsite_term, 'jobsite_geo-techs');
            $jobsite_term = ucwords(str_replace('-', ' ', $jobsite_term->name));

            $GLOBALS['jobsite_geo-headline'] = $jobsite_term.'\'s Recent Jobs';
            $GLOBALS['jobsite_geo-page_title'] = $GLOBALS['jobsite_geo-headline'].$sep.get_bloginfo('name');
			$GLOBALS['jobsite_geo-map-caption'] = 'This map shows the location of some of '.$jobsite_term.'\'s recent work.';
		endif;
	endif;
	
    add_filter( 'wpseo_title', function( $title ) {
        return $GLOBALS['jobsite_geo-page_title'];
    });
	
    add_filter( 'wpseo_metadesc', function( $description ) {
        return $GLOBALS['jobsite_geo-page_desc'];
    });
	
    return $template;
}

// rename files for jobsite geo images
add_filter('wp_handle_upload_prefilter', 'battleplan_handle_jobsite_geo_image_upload');
function battleplan_handle_jobsite_geo_image_upload($file) {
	$current_user = wp_get_current_user();
	
	if ( isset($_REQUEST['post_id']) ) :
        $post_id = $_REQUEST['post_id'];
        $post = get_post($post_id);
	endif;

    if ( ($post && $post->post_type === 'jobsite_geo') || in_array('bp_jobsite_geo_mgr', $current_user->roles) || in_array('bp_jobsite_geo', $current_user->roles) ):
   		$file['name'] = 'jobsite_geo-'.$post_id.'--'. $file['name'];
    endif;	
	
    return $file;
};


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


/*--------------------------------------------------------------
# Setup Re-directs
--------------------------------------------------------------*/
add_action('template_redirect', 'battleplan_jobsite_geo_intercept');
function battleplan_jobsite_geo_intercept() {
	$uri = trim($_SERVER['REQUEST_URI'], '/');
    $uri = explode('/', $uri);
    $uri_slug = $uri[0];
	
	$terms = get_terms(array( 'taxonomy' => 'jobsite_geo-service-areas', 'hide_empty' => false, ));
    if (!empty($terms) && !is_wp_error($terms)) :
        foreach ($terms as $term) :
            if ($term->slug === $uri_slug) :
                wp_redirect( home_url('/service-area/'.$uri_slug.'/'), 301 ); 
                exit;
            endif;
        endforeach;
    endif;
}

?>