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

	// On a re-sync where the description text actually changed, force a fresh AI
	// rewrite so the page copy + service terms reflect the new text. The scheduler
	// below only fires when AI hasn't run, so clear the flag first.
	if ( ! empty($job['desc_changed']) && defined('BP_GEO_FIELD_AI_RAN')
		&& get_post_meta($post_id, BP_GEO_FIELD_AI_RAN, true) ) {
		delete_post_meta($post_id, BP_GEO_FIELD_AI_RAN);
	}

	// Ingested posts skip bp_geo_ai_on_first_publish (suppressed by the guard above
	// for the whole ingest), so schedule the AI rewrite here. It rewrites the raw
	// description AND assigns the service-type/service terms that seed the /service/
	// page — without it the import is a dead-end. Deferred via WP-Cron (like AI
	// alt-text) so the slow API call doesn't block the importer, and staggered so a
	// bulk Company Cam sync doesn't fire them all in one cron spawn.
	if (
		get_post_status($post_id) === 'publish'
		&& defined('BP_GEO_FIELD_AI_RAN')
		&& ! get_post_meta($post_id, BP_GEO_FIELD_AI_RAN, true)
		&& ! wp_next_scheduled('bp_geo_ai_rewrite_cron', [$post_id])
	) {
		static $ai_stagger = 0;
		wp_schedule_single_event(time() + 30 + ($ai_stagger++ * 90), 'bp_geo_ai_rewrite_cron', [$post_id]);
	}

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
			'ID'         => $existing->ID,
			'post_title' => $title,
		];

		// Only overwrite the body on a fresh import or when the source text changed.
		// On a photo-only re-sync (update_content === false) we leave post_content
		// alone so the AI-rewritten description isn't clobbered with the raw notepad.
		// Default (key absent, e.g. HCP) keeps the original always-update behavior.
		if (!array_key_exists('update_content', $job) || $job['update_content']) {
			$args['post_content'] = $content;
		}

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

	$lock_key = 'hcp_job_lock_' . ($job_id ?? '');
	if (get_transient($lock_key)) {
		return new WP_REST_Response(['skipped' => 'Job sync already running'], 200);
	}
	set_transient($lock_key, time(), 5 * MINUTE_IN_SECONDS);

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
	$captioned_photos = [];
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

	if ($photo_notes) {
		foreach ($photo_notes as $note) {

			$note = preg_replace('/\s+/', ' ', trim($note));

			if (preg_match(
				'/(?:Photo|Pic|Image)\s*(\d+)\s*[\-\:\=\)\.]*\s*(.+)/i',
				$note,
				$m
			)) {
				$captioned_photos[] = [
					'photo_num' => (int) $m[1],
					'caption'   => trim($m[2]),
				];
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


	// HCP sends attachments newest → oldest
	// Display order is the reverse of API order
	$attachments = array_values($job->attachments ?? []);

	$display = array_reverse($attachments);

	// Select ONLY captioned photos, mapped by display number
	$photos = [];
	$slot = 1;

	foreach ($captioned_photos as $entry) {

		if ($slot > 4) break;

		$idx = $entry['photo_num'] - 1;
		if (!isset($display[$idx])) continue;

		$a = $display[$idx];

		$photos[] = [
			'id'        => $a->id,
			'url'       => $a->url,
			'caption'   => $entry['caption'],
			'slot'      => $slot,
			'file_name' => $a->file_name ?? '',
			'file_type' => $a->file_type ?? 'image/jpeg',
		];

		$slot++;
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

	delete_transient($lock_key);

	return new WP_REST_Response(['success' => true, 'post_id' => $post_id], 200);
}

function bp_sync_jobsite_photos_hcp($post_id, array $photos) {

	// --------------------------------------------
	// 1️⃣ Capture existing slot state
	// --------------------------------------------
	$existing_slots = [];
	for ($i = 1; $i <= 4; $i++) {
		$aid = get_field("jobsite_photo_{$i}", $post_id);
		if ($aid) $existing_slots[$i] = (int) $aid;
	}
	$deleted_attachments = [];
	$used_slots = [];


	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// --------------------------------------------
	// 2️⃣ Process incoming photos (authoritative)
	// --------------------------------------------
	foreach ($photos as $p) {

		$photo_id = $p['id'] ?? '';
		$caption  = trim((string) ($p['caption'] ?? ''));
		$acf_slot = (int) ($p['slot'] ?? 0);

		if (
			!$photo_id ||
			$caption === '' ||
			$acf_slot < 1 ||
			$acf_slot > 4
		) {
			continue;
		}

		$used_slots[] = $acf_slot;

		$expected_filename = basename(parse_url($p['url'], PHP_URL_PATH));
		$aid = 0;

		// --------------------------------------------
		// 2a️⃣ SLOT CHECK — filename match?
		// --------------------------------------------
		if (isset($existing_slots[$acf_slot])) {

			$current_aid  = $existing_slots[$acf_slot];
			$current_file = get_attached_file($current_aid);

			if ($current_file && basename($current_file) === $expected_filename) {
				$aid = $current_aid; // keep
			} else {
				if ( (int) get_post_field('post_parent', $current_aid) === (int) $post_id && !in_array($current_aid, $deleted_attachments, true) ) {
					wp_delete_attachment($current_aid, true);
					$deleted_attachments[] = $current_aid;
				}
				$aid = 0;
			}
		}

		// --------------------------------------------
		// 2b️⃣ Reuse existing attachment by HCP ID
		// --------------------------------------------
		if (!$aid) {
			$found = get_posts([
				'post_type'   => 'attachment',
				'meta_key'    => '_hcp_attachment_id',
				'meta_value'  => $photo_id,
				'fields'      => 'ids',
				'numberposts' => 1,
			]);
			if ($found) $aid = (int) $found[0];
		}

		// --------------------------------------------
		// 2c️⃣ Download if still missing
		// --------------------------------------------
		if (!$aid) {

			if (empty($p['url']) || !filter_var($p['url'], FILTER_VALIDATE_URL)) {
				continue;
			}

			$tmp = download_url($p['url']);
			if (is_wp_error($tmp)) continue;

			$existing_aid = bp_find_existing_attachment_by_hash($tmp);

			if ($existing_aid) {
				@unlink($tmp);
				$aid = $existing_aid;
			} else {

				$ext = pathinfo($expected_filename, PATHINFO_EXTENSION) ?: 'jpg';

				$file = [
					'name'     => "jobsite-geo-{$post_id}-{$photo_id}.{$ext}",
					'type'     => $p['file_type'] ?? 'image/jpeg',
					'tmp_name' => $tmp,
					'error'    => 0,
					'size'     => filesize($tmp),
				];

				$m = wp_handle_sideload($file, ['test_form' => false]);
				if (empty($m['file'])) continue;

				$aid = wp_insert_attachment([
					'post_mime_type' => $file['type'],
					'post_title'     => "Jobsite GEO [{$post_id}] {$photo_id}",
					'post_status'    => 'inherit',
				], $m['file'], $post_id);

				wp_update_attachment_metadata(
					$aid,
					wp_generate_attachment_metadata($aid, $m['file'])
				);

				update_post_meta($aid, '_bp_file_hash', md5_file(get_attached_file($aid)));
			}

			update_post_meta($aid, '_hcp_attachment_id', $photo_id);
		}

		// --------------------------------------------
		// 2d️⃣ Attach + caption (authoritative)
		// --------------------------------------------
		update_field("jobsite_photo_{$acf_slot}", $aid, $post_id);
		update_field("jobsite_photo_{$acf_slot}_alt", $caption, $post_id);

		update_post_meta($aid, '_wp_attachment_image_alt', $caption);
		wp_update_post([
			'ID'           => $aid,
			'post_parent'  => $post_id,
			'post_excerpt' => $caption,
		]);
	}

	// --------------------------------------------
	// 3️⃣ Remove orphaned slots (CRITICAL)
	// --------------------------------------------
	for ($i = 1; $i <= 4; $i++) {
		if (isset($existing_slots[$i]) && !in_array($i, $used_slots, true)) {

			$aid = $existing_slots[$i];

			delete_field("jobsite_photo_{$i}", $post_id);
			delete_field("jobsite_photo_{$i}_alt", $post_id);

			wp_delete_attachment($aid, true);
		}
	}
}

/*--------------------------------------------------------------
# Company Cam
--------------------------------------------------------------*/

// Named to match the cron caller in functions-chron-housekeeping.php. The
// earlier "_exact" suffix broke the function_exists() guard, so the nightly
// sync silently never ran.
function bp_run_companycam_sync() {

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Per-site Company Cam API token, stored in the jobsite_geo option (same
	// place HCP keeps its token). Was hardcoded to a placeholder, which 401'd.
	$token = get_option('jobsite_geo')['token'] ?? '';
	if (empty($token)) {
		error_log('bp_run_companycam_sync: no Company Cam API token in the jobsite_geo option; aborting sync.');
		return;
	}

	// Company Cam returns only the first page (default 25) for an unparameterized
	// request, AND it CAPS per_page at 50 (larger values are ignored — 100 still
	// returns 50). So we must page until a page comes back EMPTY, NOT until a page
	// is "short": every page is 50 even when hundreds of projects remain. Older /
	// less-recently-touched projects (including ones with publishable ***notes***)
	// live on later pages — accounts can have hundreds (this one has 200+).
	$projects = [];
	$page     = 1;
	do {
		$res = wp_remote_get( add_query_arg(
			[ 'per_page' => 50, 'page' => $page ],   // 50 = Company Cam's max page size
			'https://api.companycam.com/v2/projects'
		), [ 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ] );

		if ( is_wp_error( $res ) ) break;

		$data  = json_decode( wp_remote_retrieve_body( $res ) );
		$batch = is_array( $data ) ? $data : ( $data->projects ?? [] );
		if ( empty( $batch ) ) break;   // an empty page is the only reliable end-of-list signal

		$projects = array_merge( $projects, $batch );
		$page++;
	} while ( $page <= 200 ); // safety cap: 200 pages × 50 = 10,000 projects

	foreach ($projects as $p) {

		$found    = get_posts([
			'post_type'   => 'jobsite_geo',
			'meta_key'    => '_companycam_project_id',
			'meta_value'  => $p->id,
			'numberposts' => 1,
		]);
		$existing = $found[0] ?? null;

		$project_updated = (int) ($p->updated_at ?? 0);

		// Skip only if we've already synced this project AND it hasn't changed in
		// Company Cam since — compare CC's updated_at to the watermark we stored last
		// time. This is what lets later edits / added photos flow through instead of
		// being ignored forever by a plain "already exists" guard. Falls back to the
		// legacy _companycam_photos_synced timestamp for posts synced before this.
		if ($existing) {
			$last_synced = (int) get_post_meta($existing->ID, '_companycam_synced_updated_at', true);
			if (!$last_synced) $last_synced = (int) get_post_meta($existing->ID, '_companycam_photos_synced', true);
			if ($last_synced && $project_updated && $project_updated <= $last_synced) {
				continue;
			}
		}

		if (($p->status ?? '') === 'deleted') continue;

		$raw = wp_strip_all_tags($p->notepad ?? '');

		// EXACT: must be **...** (2+ stars) and capture non-greedy
		if (!preg_match('/\*{2,}([\s\S]*?)\*{2,}/', $raw, $m)) continue;

		$jobsite_desc = trim($m[1]);

		// Did the publishable description text itself change? If not (e.g. only photos
		// were added), we keep the AI-written page copy and just refresh the photos.
		$desc_hash    = md5($jobsite_desc);
		$desc_changed = !$existing
			|| ((string) get_post_meta($existing->ID, '_companycam_notepad_hash', true) !== $desc_hash);

		$a = $p->address ?? null;

		// Photos are also paginated (default 25). Page through until we've collected
		// the 4 captioned photos we actually use (or run out) — otherwise captioned
		// photos deeper than the first page get missed on photo-heavy projects.
		$photos   = [];
		$photo_pg = 1;
		do {
			$photos_res = wp_remote_get( add_query_arg(
				[ 'per_page' => 50, 'page' => $photo_pg ],   // 50 = Company Cam's max page size
				"https://api.companycam.com/v2/projects/{$p->id}/photos"
			), [ 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ] );

			if ( is_wp_error( $photos_res ) ) break;

			$photo_data = json_decode( wp_remote_retrieve_body( $photos_res ) );
			$photo_list = is_array( $photo_data ) ? $photo_data : ( $photo_data->photos ?? [] );
			if ( empty( $photo_list ) ) break;   // empty page = end; don't rely on page size

			foreach ( $photo_list as $photo ) {

				$caption = wp_strip_all_tags( $photo->description->plain_text_content ?? '' );
				if ( $caption === '' ) continue;

				$web_uri_obj = array_filter( $photo->uris ?? [], fn($u) => $u->type === 'original' );
				$web_uri     = array_values( $web_uri_obj )[0]->uri ?? null;
				if ( ! $web_uri ) continue;

				$photos[] = [
					'id'      => $photo->id,
					'url'     => $web_uri,
					'caption' => $caption,
				];

				if ( count( $photos ) >= 4 ) break; // only 4 ACF slots are used downstream
			}

			$photo_pg++;
		} while ( count( $photos ) < 4 && $photo_pg <= 50 ); // empty-page break above ends the loop; keep paging until we have 4

		$post_id = bp_ingest_jobsite([
			'source'            => 'Company Cam',
			'external_id'       => $p->id,
			'external_meta_key' => '_companycam_project_id',

			// EXACT: title fallback enabled; force publish on update
			'title_fallback' => true,
			'force_publish'  => true,

			'title'       => $p->name,
			'description' => $jobsite_desc,

			// Only rewrite the body when the text changed (photo-only re-syncs keep
			// the AI copy); desc_changed also re-triggers the AI rewrite downstream.
			'update_content' => $desc_changed,
			'desc_changed'   => $desc_changed,

			// EXACT ACF field names used in original Company Cam
			'acf_fields' => [
				'field_address' => $a?->street_address_1 ?? null,
				'field_city'    => $a?->city ?? null,
				'field_state'   => $a?->state ?? null,
				'field_zip'     => ($a?->postal_code ?? null) ? substr($a->postal_code, 0, 5) : null,
				'field_date'    => $p->created_at ? date('Y-m-d', $p->created_at) : null,
			],

			'post_meta' => [
				'_companycam_project_id'        => $p->id,
				'_companycam_notepad_hash'      => $desc_hash,
				// Watermark: the CC updated_at we just synced. Next run skips this
				// project unless its updated_at climbs above this value.
				'_companycam_synced_updated_at' => $project_updated ?: time(),
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

			/* --------------------------------------------
			 * 🔑 HCP ATTACHMENT ID IS AUTHORITATIVE
			 * -------------------------------------------- */
			if ($existing_aid) {
				$existing_hcp_id = get_post_meta($existing_aid, '_hcp_attachment_id', true);

				// Different source photo → DO NOT reuse by hash.
				// (Was comparing against an undefined $photo_id; the CC id is $cc_photo_id.)
				if ($existing_hcp_id && (string) $existing_hcp_id !== (string) $cc_photo_id) {
					$existing_aid = 0;
				}
			}

			if ($existing_aid) {

				@unlink($tmp);
				$aid = $existing_aid;

				update_post_meta(
					$aid,
					'_bp_file_hash',
					md5_file(get_attached_file($aid))
				);

				// Ensure correct parent
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
