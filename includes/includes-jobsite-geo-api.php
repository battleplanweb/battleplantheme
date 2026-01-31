<?php
/* Battle Plan Web Design Jobsite GEO API Framework */

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# API Framework Helpers
# Housecall Pro
# Company Cam
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# API Framework Helpers
--------------------------------------------------------------*/

function bp_ingest_jobsite(array $job) {

	static $photo_runs = [];
	$key = ($job['external_id'] ?? '') . ':' . ($job['photo_driver'] ?? '');
	if (isset($photo_runs[$key])) {
		return bp_upsert_jobsite_post_exact($job);
	}
	$photo_runs[$key] = true;

	$GLOBALS['bp_jobsite_setup_running'] = true;

	$post_id = bp_upsert_jobsite_post_exact($job);

	if (!$post_id) {
		unset($GLOBALS['bp_jobsite_setup_running']);
		return false;
	}

	// ACF + extra fields (exact per driver)
	if (!empty($job['acf_fields']) && is_array($job['acf_fields'])) {
		foreach ($job['acf_fields'] as $acf_field => $val) {
			if ($val === null || $val === '') continue;
			update_field($acf_field, $val, $post_id);
		}
	}

	// Post meta (exact per driver)
	if (!empty($job['post_meta']) && is_array($job['post_meta'])) {
		foreach ($job['post_meta'] as $k => $v) {
			update_post_meta($post_id, $k, $v);
		}
	}

	// Photos (exact driver behavior)
	if (!empty($job['photos']) && is_array($job['photos']) && !empty($job['photo_driver'])) {
		$driver = $job['photo_driver'];
		$driver === 'hcp' ? bp_sync_jobsite_photos_hcp($post_id, $job['photos']) : null;
		$driver === 'cc'  ? bp_sync_jobsite_photos_companycam($post_id, $job['photos']) : null;
	}

	// Finalize (exact)
	bp_jobsite_setup($post_id, $job['source'] ?? '');
	unset($GLOBALS['bp_jobsite_setup_running']);

	return $post_id;
}

function bp_upsert_jobsite_post_exact(array $job) {

	$post_type = 'jobsite_geo';
	$title     = (string)($job['title'] ?? '');
	$content   = (string)($job['description'] ?? '');

	$existing = null;

	// Primary lookup by meta (exact)
	if (!empty($job['external_meta_key']) && isset($job['external_id'])) {
		$found = get_posts([
			'post_type'   => $post_type,
			'meta_key'    => $job['external_meta_key'],
			'meta_value'  => $job['external_id'],
			'numberposts' => 1,
		]);
		$existing = $found[0] ?? null;
	}

	// Optional title fallback (Company Cam exact behavior)
	if (!$existing && !empty($job['title_fallback']) && $title !== '') {
		$existing = get_page_by_title($title, OBJECT, $post_type);
	}

	// Update vs insert (exact knobs)
	if ($existing) {

		$args = [
			'ID'           => $existing->ID,
			'post_title'   => $title,
			'post_content' => $content,
		];

		// Company Cam explicitly sets publish on update; HCP does not
		if (!empty($job['force_publish'])) {
			$args['post_status'] = 'publish';
		}

		$post_id = wp_update_post($args);

	} else {

		$post_id = wp_insert_post([
			'post_title'   => $title,
			'post_content' => $content,
			'post_type'    => $post_type,
			'post_status'  => 'publish',
		]);
	}

	// Always ensure the external meta key exists when provided (originals do this)
	if ($post_id && !empty($job['external_meta_key']) && isset($job['external_id'])) {
		update_post_meta($post_id, $job['external_meta_key'], $job['external_id']);
	}

	return $post_id ?: false;
}

function bp_find_existing_attachment_by_hash($tmp_file) {
	if (!file_exists($tmp_file)) return 0;

	$hash = md5_file($tmp_file);

	/* --------------------------------------------
	 * FAST PATH — indexed meta lookup
	 * -------------------------------------------- */
	$existing = get_posts([
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'meta_key'    => '_bp_file_hash',
		'meta_value'  => $hash,
		'fields'      => 'ids',
		'numberposts' => 1,
	]);

	if ($existing) {
		return (int) $existing[0];
	}

	/* --------------------------------------------
	 * SLOW FALLBACK — legacy scan (one-time cost)
	 * -------------------------------------------- */
	$attachments = get_posts([
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);

	foreach ($attachments as $aid) {
		$file = get_attached_file($aid);
		if ($file && file_exists($file) && md5_file($file) === $hash) {
			return (int) $aid;
		}
	}

	return 0;
}


/*--------------------------------------------------------------
# Housecall Pro
--------------------------------------------------------------*/

add_action('rest_api_init', function() {
	register_rest_route('hcpro/v1', '/job-callback', [
		'methods'  => 'POST',
		'callback' => 'bp_handle_hcp_webhook_exact',
		'permission_callback' => '__return_true',
	]);
});

function bp_handle_hcp_webhook_exact(WP_REST_Request $req) {

	$raw = $req->get_body();

					// --------------------------------------------
					// DEBUG: log full raw HCP payload
					// --------------------------------------------
					error_log('================ HCP WEBHOOK START ================');
					error_log('[HCP RAW BODY]');
					error_log($raw ?: '[EMPTY BODY]');

					// Also log decoded structure when possible
					$decoded = json_decode($raw);
					if (json_last_error() === JSON_ERROR_NONE) {
						error_log('[HCP DECODED PAYLOAD]');
						error_log(print_r($decoded, true));
					} else {
						error_log('[HCP JSON ERROR] ' . json_last_error_msg());
					}

					error_log('================ HCP WEBHOOK END ==================');


	// HCP webhook verification payload (required)
	if (trim($raw) === '{"foo":"bar"}') {
		return new WP_REST_Response(['ok' => true], 200);
	}

	if (defined('WP_DEBUG') && WP_DEBUG) {
		error_log('[HCP WEBHOOK] Payload received');
		error_log('[HCP WEBHOOK] Body: ' . substr($raw, 0, 2000));
	}

	$get_jobsite_geo = get_option('jobsite_geo');

	// Token + brand gating (required)
	if (empty($_GET['token']) || $_GET['token'] !== ($get_jobsite_geo['token'] ?? '')) {
		return new WP_REST_Response(['error' => 'Invalid token'], 403);
	}

	if (($get_jobsite_geo['fsm_brand'] ?? '') !== 'Housecall Pro') {
		return new WP_REST_Response(['error' => 'Not using Housecall Pro'], 403);
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$data = json_decode($raw);

	if (!$data || !is_object($data) || !isset($data->job)) {
		return new WP_REST_Response(['error' => 'Invalid job object'], 400);
	}

	if (defined('WP_DEBUG') && WP_DEBUG) {
		file_put_contents(WP_CONTENT_DIR . '/hcp-last-payload.txt', print_r($data, true));
	}

	$job  = $data->job;
	$cust = $job->customer ?? new stdClass();
	$addr = $job->address ?? new stdClass();

	$description_note = '';
	$photo_notes = [];
	$captions = [];
	$has_publishable_note = false;

	// EXACT sloppy-note parsing (same as original)
	if (!empty($job->notes)) {
		foreach ($job->notes as $n) {
			$note_text = trim($n->content);

			if (preg_match('/^\*{3,}/', $note_text)) {
				$has_publishable_note = true;
				$description_note = trim(preg_replace('/^\*{3,}\s*/', '', $note_text));
			} elseif (preg_match('/\b(Photo|Pic|Image)\s*\d+/i', $note_text)) {
				$photo_notes[] = $note_text;
			}
		}
	}

	if (!$has_publishable_note) {
		return new WP_REST_Response(['skipped' => 'No publishable note found'], 200);
	}

	// EXACT caption extraction (multi-photo, multi-line tolerant)
	if ($photo_notes) {
		$text = implode(' ', array_map(function($n) {
			return preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $n));
		}, $photo_notes));

		$pattern = '/(?:Photo|Pic|Image)\s*(\d+)\s*[\-\:\=\)\.]*\s*(.*?)(?=(?:Photo|Pic|Image)\s*\d+|$)/is';

		if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $m) {
				$idx = (int) $m[1];
				$cap = trim($m[2]);
				if ($cap !== '') $captions[$idx] = $cap;
			}
		}
	}

	$title   = trim(($cust->first_name ?? '') . ' ' . ($cust->last_name ?? ''));
	$content = $description_note;
	$job_id  = $job->id ?? '';

	$street = $addr->street ?? '';
	$city   = $addr->city ?? '';
	$state  = $addr->state ?? '';
	$zip    = isset($addr->zip) ? preg_replace('/^(\d{5}).*/', '$1', $addr->zip) : '';
	$date   = $job->work_timestamps->completed_at ?? '';

	// Captions keyed by photo number (1-based)
	ksort($captions);

	// HCP sends attachments newest → oldest
	// Display order is the reverse of API order
	$attachments = array_values(array_reverse($job->attachments ?? []));

	// Select ONLY captioned photos, mapped by display number
	$photos = [];

	foreach ($captions as $photo_num => $caption) {

		if (count($photos) >= 4) break;

		$idx = (int) $photo_num - 1;

		if ($idx < 0 || !isset($attachments[$idx])) {
			continue;
		}

		$a = $attachments[$idx];

		$photos[] = [
			'id'        => $a->id,
			'url'       => $a->url,
			'caption'   => $caption,
			'file_name' => $a->file_name ?? '',
			'file_type' => $a->file_type ?? 'image/jpeg',
		];
	}


	$post_id = bp_ingest_jobsite([
		'source'            => 'Housecall Pro',
		'external_id'       => $job_id,
		'external_meta_key' => 'hcp_job_id',

		// HCP does not do title fallback; does not force publish on update
		'title_fallback' => false,
		'force_publish'  => false,

		'title'       => $title,
		'description' => $content,

		// EXACT ACF field names used in original HCP
		'acf_fields' => [
			'address'       => $street,
			'city'          => $city,
			'state'         => $state,
			'zip'           => $zip,
			'job_date'      => $date,
			'hcp_job_id'    => $job_id,
			'customer_name' => $title,
		],

		// HCP stores attachment ids in THIS meta key
		'post_meta' => [],

		'photo_driver' => 'hcp',
		'photos'        => $photos,
	]);

	if (!$post_id) {
		return new WP_REST_Response(['error' => 'Failed to create post'], 500);
	}

	return new WP_REST_Response(['success' => true, 'post_id' => $post_id], 200);
}

function bp_sync_jobsite_photos_hcp($post_id, array $photos) {

	// EXACT HCP meta key behavior
	$saved_ids = get_post_meta($post_id, '_hcp_attachment_ids', true);
	if (!is_array($saved_ids)) $saved_ids = [];

	$acf_slot = 1;

	// Photos in $photos are already in Photo 1..4 order.
	foreach ($photos as $p) {

		$photo_id = $p['id'] ?? '';
		if (!$photo_id) continue;
		if ($acf_slot > 4) break;

		/* --------------------------------------------
		 * 🔒 HARD LOCK (prevents race-condition dupes)
		 * -------------------------------------------- */
		$lock_key = 'hcp_photo_lock_' . $photo_id;
		if (get_transient($lock_key)) continue;
		set_transient($lock_key, time(), 5 * MINUTE_IN_SECONDS);

		$attachment_title = sprintf(
			'Jobsite GEO [%d] %s -- %s',
			$post_id,
			wp_strip_all_tags(get_the_title($post_id)),
			$photo_id
		);

		$existing_attachment = get_posts([
			'post_type'   => 'attachment',
			'meta_key'    => '_hcp_attachment_id',
			'meta_value'  => $photo_id,
			'fields'      => 'ids',
			'numberposts' => 1,
		]);

		if ($existing_attachment) {

			$aid = (int) $existing_attachment[0];

			if ((int) get_post_field('post_parent', $aid) !== (int) $post_id) {
				wp_update_post([
					'ID'          => $aid,
					'post_parent' => $post_id,
				]);
			}

			wp_update_post([
				'ID'         => $aid,
				'post_title' => $attachment_title,
			]);

		} else {

			$tmp = download_url($p['url']);
			if (is_wp_error($tmp)) {
				delete_transient($lock_key);
				continue;
			}

			/* --------------------------------------------
			 * 🧠 HASH DEDUPE (reuse existing file)
			 * -------------------------------------------- */
			$existing_aid = bp_find_existing_attachment_by_hash($tmp);

			if ($existing_aid) {

				@unlink($tmp);
				$aid = $existing_aid;
				update_post_meta($aid, '_bp_file_hash', md5_file(get_attached_file($aid)));

				if ((int) get_post_field('post_parent', $aid) !== (int) $post_id) {
					wp_update_post([
						'ID'          => $aid,
						'post_parent' => $post_id,
					]);
				}

			} else {

				$ext = pathinfo($p['file_name'] ?? '', PATHINFO_EXTENSION) ?: 'jpg';

				$file = [
					'name'     => "jobsite-geo-{$post_id}-{$photo_id}.{$ext}",
					'type'     => $p['file_type'] ?? 'image/jpeg',
					'tmp_name' => $tmp,
					'error'    => 0,
					'size'     => filesize($tmp),
				];

				$m = wp_handle_sideload($file, ['test_form' => false]);
				if (empty($m['file'])) {
					delete_transient($lock_key);
					continue;
				}

				$aid = wp_insert_attachment([
					'post_mime_type' => $file['type'],
					'post_title'     => $attachment_title,
					'post_status'    => 'inherit',
				], $m['file'], $post_id);

				wp_update_attachment_metadata(
					$aid,
					wp_generate_attachment_metadata($aid, $m['file'])
				);

				update_post_meta($aid, '_bp_file_hash', md5_file(get_attached_file($aid)));
				update_post_meta($aid, '_hcp_attachment_id', $photo_id);
				$saved_ids[] = $photo_id;
			}
		}

		/* --------------------------------------------
		 * 📌 ACF + META (unchanged behavior)
		 * -------------------------------------------- */
		update_field("jobsite_photo_{$acf_slot}", $aid, $post_id);

		$caption = trim((string)($p['caption'] ?? ''));

		// HARD RULE: HCP photos without captions NEVER consume a slot
		if ($caption === '') {
			delete_transient($lock_key);
			continue;
		}

		if ($caption !== '') {
			update_post_meta($aid, '_wp_attachment_image_alt', $caption);
			wp_update_post(['ID' => $aid, 'post_excerpt' => $caption]);
		}

		delete_transient($lock_key);
		$acf_slot++;
	}

	update_post_meta($post_id, '_hcp_attachment_ids', array_unique($saved_ids));
}

/*--------------------------------------------------------------
# Company Cam
--------------------------------------------------------------*/

function bp_run_companycam_sync_exact() {

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$token = 'YOUR_TOKEN_HERE';

	$res = wp_remote_get('https://api.companycam.com/v2/projects', [
		'headers' => ['Authorization' => 'Bearer ' . $token]
	]);

	if (is_wp_error($res)) return;

	$data = json_decode(wp_remote_retrieve_body($res));
	$projects = is_array($data) ? $data : ($data->projects ?? []);

	foreach ($projects as $p) {

		$existing = get_posts([
			'post_type'   => 'jobsite_geo',
			'meta_key'    => '_companycam_project_id',
			'meta_value'  => $p->id,
			'numberposts' => 1,
		]);

		if ($existing && get_post_meta($existing[0]->ID, '_companycam_photos_synced', true)) {
			continue;
		}

		if (($p->status ?? '') === 'deleted') continue;

		$raw = wp_strip_all_tags($p->notepad ?? '');

		// EXACT: must be **...** (2+ stars) and capture non-greedy
		if (!preg_match('/\*{2,}([\s\S]*?)\*{2,}/', $raw, $m)) continue;

		$jobsite_desc = trim($m[1]);

		$a = $p->address ?? null;

		// Fetch photos EXACTLY like original
		$photos_res = wp_remote_get("https://api.companycam.com/v2/projects/{$p->id}/photos", [
			'headers' => ['Authorization' => 'Bearer ' . $token]
		]);

		$photo_data = json_decode(wp_remote_retrieve_body($photos_res));
		$photo_list = is_array($photo_data) ? $photo_data : ($photo_data->photos ?? []);

		$photos = [];
		foreach ($photo_list as $photo) {

			$caption = wp_strip_all_tags($photo->description->plain_text_content ?? '');
			if ($caption === '') continue;

			$web_uri_obj = array_filter($photo->uris ?? [], fn($u) => $u->type === 'original');
			$web_uri = array_values($web_uri_obj)[0]->uri ?? null;
			if (!$web_uri) continue;

			$photos[] = [
				'id'      => $photo->id,
				'url'     => $web_uri,
				'caption' => $caption,
			];

			// EXACT: stop by ACF slots later (driver stops at 4),
			// but we can also early stop here to reduce load
			if (count($photos) >= 4) {
				// Do NOT break here in original if later photos have captions and earlier didn’t.
				// Original breaks only on $acf_slot > 4 inside processing loop, not while collecting.
				// So we intentionally do NOT break here.
			}
		}

		$post_id = bp_ingest_jobsite([
			'source'            => 'Company Cam',
			'external_id'       => $p->id,
			'external_meta_key' => '_companycam_project_id',

			// EXACT: title fallback enabled; force publish on update
			'title_fallback' => true,
			'force_publish'  => true,

			'title'       => $p->name,
			'description' => $jobsite_desc,

			// EXACT ACF field names used in original Company Cam
			'acf_fields' => [
				'field_address' => $a?->street_address_1 ?? null,
				'field_city'    => $a?->city ?? null,
				'field_state'   => $a?->state ?? null,
				'field_zip'     => ($a?->postal_code ?? null) ? substr($a->postal_code, 0, 5) : null,
				'field_date'    => $p->created_at ? date('Y-m-d', $p->created_at) : null,
			],

			'post_meta' => [
				'_companycam_project_id' => $p->id,
			],

			'photo_driver' => 'cc',
			'photos'        => $photos,
		]);

		// Original returns a WP_REST_Response at end of template_redirect, but for nightly sync we do not.
		// No return needed here.

		update_post_meta($post_id, '_companycam_photos_synced', time());
	}

}

function bp_sync_jobsite_photos_companycam($post_id, array $photos) {

	$saved_ids = [];
	$acf_slot  = 1;

	foreach ($photos as $photo) {

		if ($acf_slot > 4) break;

		$caption = wp_strip_all_tags($photo['caption'] ?? '');
		if ($caption === '') continue;

		$web_uri = $photo['url'] ?? null;
		if (!$web_uri) continue;

		$cc_photo_id = $photo['id'] ?? null;
		if (!$cc_photo_id) continue;

		/* --------------------------------------------
		 * 🔒 HARD LOCK (prevents duplicate downloads)
		 * -------------------------------------------- */
		$lock_key = 'cc_photo_lock_' . $cc_photo_id;
		if (get_transient($lock_key)) continue;
		set_transient($lock_key, time(), 5 * MINUTE_IN_SECONDS);

		$attachment_title = sprintf(
			'Jobsite GEO [%d] %s -- %s',
			$post_id,
			wp_strip_all_tags(get_the_title($post_id)),
			$cc_photo_id
		);

		$existing_attachment = get_posts([
			'post_type'   => 'attachment',
			'meta_key'    => '_companycam_photo_id',
			'meta_value'  => $cc_photo_id,
			'fields'      => 'ids',
			'numberposts' => 1,
		]);

		if ($existing_attachment) {

			$aid = (int) $existing_attachment[0];

			if ((int) get_post_field('post_parent', $aid) !== (int) $post_id) {
				wp_update_post([
					'ID'          => $aid,
					'post_parent' => $post_id,
				]);
			}

			wp_update_post([
				'ID'         => $aid,
				'post_title' => $attachment_title,
			]);

		} else {

			$tmp = download_url($web_uri);
			if (is_wp_error($tmp)) {
				delete_transient($lock_key);
				continue;
			}

			/* --------------------------------------------
			 * 🧠 HASH DEDUPE (reuse existing file)
			 * -------------------------------------------- */
			$existing_aid = bp_find_existing_attachment_by_hash($tmp);

			if ($existing_aid) {

				@unlink($tmp);
				$aid = $existing_aid;
				update_post_meta($aid, '_bp_file_hash', md5_file(get_attached_file($aid)));

				if ((int) get_post_field('post_parent', $aid) !== (int) $post_id) {
					wp_update_post([
						'ID'          => $aid,
						'post_parent' => $post_id,
					]);
				}

			} else {

				$ext = pathinfo(parse_url($web_uri, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';

				$file = [
					'name'     => "jobsite-geo-{$post_id}-{$cc_photo_id}.{$ext}",
					'tmp_name' => $tmp,
					'error'    => 0,
					'size'     => filesize($tmp),
				];

				$m = wp_handle_sideload($file, ['test_form' => false]);
				if (empty($m['file'])) {
					delete_transient($lock_key);
					continue;
				}

				$aid = wp_insert_attachment([
					'post_mime_type' => mime_content_type($m['file']),
					'post_title'     => $attachment_title,
					'post_status'    => 'inherit',
				], $m['file'], $post_id);

				wp_update_attachment_metadata(
					$aid,
					wp_generate_attachment_metadata($aid, $m['file'])
				);

				update_post_meta($aid, '_bp_file_hash', md5_file(get_attached_file($aid)));
				update_post_meta($aid, '_companycam_photo_id', $cc_photo_id);
				$saved_ids[] = $cc_photo_id;
			}
		}

		/* --------------------------------------------
		 * 📌 ACF + META (unchanged behavior)
		 * -------------------------------------------- */
		update_field("jobsite_photo_{$acf_slot}", $aid, $post_id);
		update_field("jobsite_photo_{$acf_slot}_alt", $caption, $post_id);

		update_post_meta($aid, '_wp_attachment_image_alt', $caption);
		wp_update_post([
			'ID'           => $aid,
			'post_excerpt' => $caption,
		]);

		delete_transient($lock_key);
		$acf_slot++;
	}

	update_post_meta($post_id, '_companycam_photo_ids', array_unique($saved_ids));
}
