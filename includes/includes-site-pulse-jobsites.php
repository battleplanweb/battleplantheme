<?php
/* Battle Plan Web Design - Site Pulse Jobsites
   Jobsite GEO entries (the jobsite_geo CPT — real completed jobs that build the /service/ landing pages)
   added and edited from Site Pulse, so clients never need wp-admin. Every save goes through
   bp_geo_save_jobsite() in includes-jobsite-geo.php — the same path the GEO app uses — so geocoding,
   the service-area tag, testimonial matching, the duplicate guard, AI captions and the (queued) AI
   rewrite all behave exactly as they do for app submissions.

   Caps: view_jobsites (see the list), add_jobsites (add + edit/delete their OWN), manage_jobsites
   (edit/delete everyone's, publish held drafts). The module only does anything where Jobsite GEO is
   installed (the CPT + its functions exist); it's switched on automatically on those installs.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Helpers & Permissions
# AJAX — List / Get
# AJAX — Save / Delete
# AJAX — Photo Upload / Locate
# Migrations (auto-enable + caps seed)
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Helpers & Permissions
--------------------------------------------------------------*/

// Jobsite GEO itself is installed and loaded on this site (its includes only load when it's on).
function sp_jobsites_ready(): bool {
	return post_type_exists( 'jobsite_geo' ) && function_exists( 'bp_geo_save_jobsite' );
}

function sp_jobsites_can_view( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_user_can( $user_id, 'view_jobsites' )
		|| site_pulse_user_can( $user_id, 'add_jobsites' )
		|| site_pulse_user_can( $user_id, 'manage_jobsites' );
}

function sp_jobsites_can_add( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_user_can( $user_id, 'add_jobsites' ) || site_pulse_user_can( $user_id, 'manage_jobsites' );
}

function sp_jobsites_can_manage( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_user_can( $user_id, 'manage_jobsites' );
}

// Edit/delete one job: managers any, adders only the ones they created.
function sp_jobsites_can_edit( int $post_id, int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	if ( get_post_type( $post_id ) !== 'jobsite_geo' ) return false;
	if ( sp_jobsites_can_manage( $user_id ) ) return true;
	return site_pulse_user_can( $user_id, 'add_jobsites' ) && (int) get_post_field( 'post_author', $post_id ) === $user_id;
}

// Common gate for every handler: nonce, module installed, and the given permission check.
function sp_jobsites_gate( callable $check, string $msg ): int {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_jobsites_ready() ) wp_send_json_error( [ 'message' => 'Jobsite GEO is not installed on this site.' ] );
	$me = site_pulse_effective_user_id();
	if ( ! $check( $me ) ) wp_send_json_error( [ 'message' => $msg ] );
	return $me;
}

// Everyone who has added a jobsite, for the "Added by" filter: the current (effective) user first, then
// the rest A–Z. Returns [ { id, name } ].
function sp_jobsites_authors( int $me ): array {
	global $wpdb;
	$ids = array_map( 'intval', (array) $wpdb->get_col(
		"SELECT DISTINCT post_author FROM {$wpdb->posts}
		 WHERE post_type = 'jobsite_geo' AND post_status IN ('publish','draft','pending') AND post_author > 0"
	) );
	$out = [];
	foreach ( $ids as $id ) {
		$u = get_userdata( $id );
		if ( $u ) $out[] = [ 'id' => $id, 'name' => $u->display_name ?: $u->user_login ];
	}
	usort( $out, static function ( $a, $b ) use ( $me ) {
		if ( $a['id'] === $me ) return -1;
		if ( $b['id'] === $me ) return 1;
		return strcasecmp( $a['name'], $b['name'] );
	} );
	return $out;
}

// One post -> the row the list renders.
function sp_jobsites_format_row( WP_Post $p, int $me ): array {
	$id     = (int) $p->ID;
	// Every photo that shows on the job (the four ACF photo slots, in order) as card thumbnails.
	$thumbs = [];
	for ( $n = 1; $n <= 4; $n++ ) {
		$aid = (int) get_post_meta( $id, "jobsite_photo_{$n}", true );
		$url = $aid ? (string) wp_get_attachment_image_url( $aid, 'thumbnail' ) : '';
		if ( $url ) $thumbs[] = $url;
	}
	$author  = get_userdata( (int) $p->post_author );
	$live    = $p->post_status === 'publish';
	$date    = (string) get_post_meta( $id, 'job_date', true );
	if ( preg_match( '/^\d{8}$/', $date ) ) $date = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );

	return [
		'id'           => $id,
		'title'        => get_the_title( $id ),
		'status'       => $live ? 'publish' : 'draft',
		'duplicate_of' => (int) get_post_meta( $id, 'bp_geo_duplicate_of', true ),
		'job_date'     => $date,
		'address'      => (string) get_post_meta( $id, 'address', true ),
		'city'         => (string) get_post_meta( $id, 'city', true ),
		'state'        => (string) get_post_meta( $id, 'state', true ),
		'thumbs'       => $thumbs,
		'photos'       => count( $thumbs ),
		'link'         => $live ? get_permalink( $id ) : '',
		'ai_pending'   => $live && ! get_post_meta( $id, BP_GEO_FIELD_AI_RAN, true ),
		'priority'     => ! empty( get_post_meta( $id, 'is_priority_job', true ) ),
		'author'       => $author ? $author->display_name : '',
		'can_edit'     => sp_jobsites_can_edit( $id, $me ),
	];
}


/*--------------------------------------------------------------
# AJAX — List / Get
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_jobsites_list', 'sp_jobsites_ajax_list' );
function sp_jobsites_ajax_list(): void {
	$me = sp_jobsites_gate( 'sp_jobsites_can_view', 'You do not have access to jobsites.' );

	$status = sanitize_key( $_POST['status'] ?? 'all' );                      // all | publish | draft
	$search = trim( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ) );
	$author = (int) ( $_POST['author'] ?? 0 );                                 // 0 = everyone
	$sort   = sanitize_key( $_POST['sort'] ?? 'date' );                        // date | date_asc | az | za
	$page   = max( 1, (int) ( $_POST['page'] ?? 1 ) );

	$args = [
		'post_type'      => 'jobsite_geo',
		'post_status'    => in_array( $status, [ 'publish', 'draft' ], true ) ? $status : [ 'publish', 'draft', 'pending' ],
		'posts_per_page' => 30,
		'paged'          => $page,
	];
	if ( in_array( $sort, [ 'az', 'za' ], true ) ) {
		// A–Z / Z–A sort on the title = the customer name.
		$args['orderby'] = 'title';
		$args['order']   = $sort === 'az' ? 'ASC' : 'DESC';
	} else {
		// Date = the JOB date (stored as Ymd everywhere — bp_geo_normalize_job_date — so it sorts as a string),
		// then date added. The OR pair keeps any job with no job_date in the list instead of dropping it.
		$dir = $sort === 'date_asc' ? 'ASC' : 'DESC';
		$args['meta_query'] = [ 'relation' => 'OR',
			'jd'   => [ 'key' => 'job_date', 'compare' => 'EXISTS' ],
			'nojd' => [ 'key' => 'job_date', 'compare' => 'NOT EXISTS' ],
		];
		$args['orderby'] = [ 'jd' => $dir, 'date' => $dir ];
	}
	if ( $search !== '' ) $args['s'] = $search;
	if ( $author > 0 ) $args['author'] = $author;

	$q    = new WP_Query( $args );
	$rows = [];
	foreach ( $q->posts as $p ) $rows[] = sp_jobsites_format_row( $p, $me );

	$counts = wp_count_posts( 'jobsite_geo' );
	wp_send_json_success( [
		'authors'    => sp_jobsites_authors( $me ),
		'jobs'       => $rows,
		'pages'      => (int) $q->max_num_pages,
		'page'       => $page,
		'total'      => (int) $q->found_posts,
		'counts'     => [ 'publish' => (int) ( $counts->publish ?? 0 ), 'draft' => (int) ( $counts->draft ?? 0 ) + (int) ( $counts->pending ?? 0 ) ],
		'can_add'    => sp_jobsites_can_add( $me ),
		'can_manage' => sp_jobsites_can_manage( $me ),
		'defaults'   => [
			'state' => strtoupper( (string) ( get_option( 'jobsite_geo' )['default_state'] ?? '' ) ),
			'date'  => current_time( 'Y-m-d' ),
		],
		'can_locate' => defined( '_PLACES_API' ) && _PLACES_API,
	] );
}

add_action( 'wp_ajax_site_pulse_jobsites_get', 'sp_jobsites_ajax_get' );
function sp_jobsites_ajax_get(): void {
	$me = sp_jobsites_gate( 'sp_jobsites_can_view', 'You do not have access to jobsites.' );
	$id = (int) ( $_POST['id'] ?? 0 );
	$p  = get_post( $id );
	if ( ! $p || $p->post_type !== 'jobsite_geo' ) wp_send_json_error( [ 'message' => 'Jobsite not found.' ] );

	$photos = [];
	for ( $n = 1; $n <= 4; $n++ ) {
		$aid = (int) get_post_meta( $id, "jobsite_photo_{$n}", true );
		$photos[ $n ] = $aid ? [
			'id'      => $aid,
			'url'     => (string) wp_get_attachment_image_url( $aid, 'medium' ),
			'caption' => (string) get_post_meta( $id, "jobsite_photo_{$n}_alt", true ),
		] : null;
	}

	// What the person originally typed (kept once, before the first AI rewrite).
	$original = (string) get_post_meta( $id, BP_GEO_FIELD_ORIGINAL, true );
	if ( $original === '' ) $original = (string) get_post_meta( $id, 'bp_raw_description', true );

	wp_send_json_success( [
		'job' => array_merge( sp_jobsites_format_row( $p, $me ), [
			'customer'    => (string) ( get_post_meta( $id, 'bp_customer_name', true ) ?: $p->post_title ),
			'zip'         => (string) get_post_meta( $id, 'zip', true ),
			'description' => trim( wp_strip_all_tags( $p->post_content ) ),
			'original'    => $original,
			'ai_ran'      => (bool) get_post_meta( $id, BP_GEO_FIELD_AI_RAN, true ),
			'photo_slots' => $photos,
			'source'      => (string) get_post_meta( $id, 'bp_submission_source', true ),
		] ),
	] );
}


/*--------------------------------------------------------------
# AJAX — Save / Delete
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_jobsites_save', 'sp_jobsites_ajax_save' );
function sp_jobsites_ajax_save(): void {
	$me = sp_jobsites_gate( 'sp_jobsites_can_add', 'You do not have permission to add jobsites.' );
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( $id && ! sp_jobsites_can_edit( $id, $me ) ) wp_send_json_error( [ 'message' => 'You can only edit the jobsites you added.' ] );

	$in   = wp_unslash( $_POST );
	$data = [];
	foreach ( [ 'customer', 'description', 'address', 'city', 'state', 'zip', 'job_date' ] as $k ) {
		if ( array_key_exists( $k, $in ) ) $data[ $k ] = (string) $in[ $k ];
	}
	$data['priority']  = ! empty( $in['priority'] );
	$data['keep_text'] = ! empty( $in['ai_polished'] );   // AI Rewrite used in the form → keep the text as saved

	// Photo slots 1–4 (attachment ids; 0 clears) + captions. Only ids this person may use are accepted:
	// one already in this job, or an upload of their own (an unattached Site Pulse upload).
	$cur      = $id ? bp_geo_photo_slot_ids( $id ) : [];
	$photos   = is_array( $in['photos'] ?? null ) ? $in['photos'] : [];
	$captions = is_array( $in['captions'] ?? null ) ? $in['captions'] : [];
	$data['photos'] = []; $data['captions'] = [];
	for ( $n = 1; $n <= 4; $n++ ) {
		$aid = (int) ( $photos[ $n ] ?? 0 );
		if ( $aid && ! in_array( $aid, array_map( 'intval', $cur ), true ) ) {
			$ok = get_post_type( $aid ) === 'attachment'
				&& ! (int) get_post_field( 'post_parent', $aid )
				&& (int) get_post_field( 'post_author', $aid ) === $me;
			if ( ! $ok ) $aid = 0;
		}
		$data['photos'][ $n ]   = $aid;
		$data['captions'][ $n ] = $aid ? (string) ( $captions[ $n ] ?? '' ) : '';
	}

	if ( $id ) {
		$data['ai']      = ! empty( $in['ai'] );
		// Republish a draft: anyone who can edit it — except a held possible-duplicate, which only a manager clears.
		$held            = (int) get_post_meta( $id, 'bp_geo_duplicate_of', true );
		$data['publish'] = ! empty( $in['publish'] ) && ( sp_jobsites_can_manage( $me ) || ! $held );
	} else {
		$data['source'] = 'Site Pulse';
	}

	$res = bp_geo_save_jobsite( $data, $id, $me );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );

	if ( function_exists( 'site_pulse_log' ) ) {
		site_pulse_log( $id ? 'jobsite_edit' : 'jobsite_add', ( $id ? 'Edited' : 'Added' ) . ' jobsite: ' . get_the_title( $res['id'] ) );
	}

	$msg = $res['duplicate_of']
		? 'Saved as a draft — it looks like a repeat of a job added in the last 48 hours, so it\'s held for review.'
		: ( $id ? 'Jobsite updated.' : 'Jobsite added — it\'s live, and the AI description and landing page will be ready in a minute.' );
	wp_send_json_success( [ 'job' => sp_jobsites_format_row( get_post( $res['id'] ), $me ), 'message' => $msg ] );
}

// Card trash button on a LIVE job = unpublish: back to a draft (off the website, still listed, re-publishable
// from the edit form). The setup guard keeps the save_post chain (validator, AI, setup) out of it.
add_action( 'wp_ajax_site_pulse_jobsites_unpublish', 'sp_jobsites_ajax_unpublish' );
function sp_jobsites_ajax_unpublish(): void {
	$me = sp_jobsites_gate( 'sp_jobsites_can_add', 'You do not have permission to edit jobsites.' );
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id || ! sp_jobsites_can_edit( $id, $me ) ) wp_send_json_error( [ 'message' => 'You can only change the jobsites you added.' ] );

	$GLOBALS['bp_jobsite_setup_running'] = true;
	$res = wp_update_post( [ 'ID' => $id, 'post_status' => 'draft' ], true );
	unset( $GLOBALS['bp_jobsite_setup_running'] );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => 'Could not move that jobsite to drafts.' ] );

	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'jobsite_unpublish', 'Moved jobsite to drafts: ' . get_the_title( $id ) );
	wp_send_json_success( [ 'job' => sp_jobsites_format_row( get_post( $id ), $me ) ] );
}

// Delete = move to the trash (recoverable in WordPress). Photos are removed when the trash is emptied.
add_action( 'wp_ajax_site_pulse_jobsites_delete', 'sp_jobsites_ajax_delete' );
function sp_jobsites_ajax_delete(): void {
	$me = sp_jobsites_gate( 'sp_jobsites_can_add', 'You do not have permission to delete jobsites.' );
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id || ! sp_jobsites_can_edit( $id, $me ) ) wp_send_json_error( [ 'message' => 'You can only delete the jobsites you added.' ] );

	$title = get_the_title( $id );
	if ( ! wp_trash_post( $id ) ) wp_send_json_error( [ 'message' => 'Could not delete that jobsite.' ] );
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'jobsite_delete', 'Deleted jobsite: ' . $title );
	wp_send_json_success( [ 'id' => $id ] );
}


/*--------------------------------------------------------------
# AJAX — Photo Upload / Locate
--------------------------------------------------------------*/

// One photo → a media-library attachment (the ACF photo fields hold attachment ids). The framework's
// upload filters still apply: EXIF rotation, WebP conversion + resize, and — when post_id names the
// job being edited — the jobsite_geo-{id}-- rename. Left unattached until the job is saved, which
// parents it and files it under the Jobsite GEO image category.
add_action( 'wp_ajax_site_pulse_jobsites_upload', 'sp_jobsites_ajax_upload' );
function sp_jobsites_ajax_upload(): void {
	$me = sp_jobsites_gate( 'sp_jobsites_can_add', 'You do not have permission to add jobsites.' );

	if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ?? '' ) ) {
		wp_send_json_error( [ 'message' => 'No file received.' ] );
	}
	$file = $_FILES['file'];
	if ( (int) $file['size'] > 25 * 1024 * 1024 ) wp_send_json_error( [ 'message' => 'Photo is too large (max 25 MB).' ] );

	$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Please upload a photo (jpg, png, webp or heic).' ] );
	}
	$check = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'] );
	if ( empty( $check['type'] ) || strpos( (string) $check['type'], 'image/' ) !== 0 ) {
		wp_send_json_error( [ 'message' => 'That file is not a valid image.' ] );
	}

	// The rename filter reads $_REQUEST['post_id']; only honor it for a job this person may edit.
	$post_id = (int) ( $_POST['post_id'] ?? 0 );
	if ( $post_id && ! sp_jobsites_can_edit( $post_id, $me ) ) $_REQUEST['post_id'] = 0;

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$moved = wp_handle_upload( $file, [ 'test_form' => false ] );
	if ( empty( $moved['file'] ) || ! empty( $moved['error'] ) ) {
		wp_send_json_error( [ 'message' => $moved['error'] ?? 'Upload failed.' ] );
	}

	$aid = wp_insert_attachment( [
		'post_mime_type' => $moved['type'],
		'post_title'     => sanitize_text_field( pathinfo( (string) $file['name'], PATHINFO_FILENAME ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_author'    => $me,
	], $moved['file'] );
	if ( is_wp_error( $aid ) || ! $aid ) wp_send_json_error( [ 'message' => 'Could not save the photo.' ] );
	wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $moved['file'] ) );

	wp_send_json_success( [ 'id' => (int) $aid, 'url' => (string) ( wp_get_attachment_image_url( $aid, 'medium' ) ?: $moved['url'] ) ] );
}

// AI Rewrite (Description) — polish the text currently in the form, same prompt as the publish-time
// rewrite. Returned for review; nothing is saved until the form is. Then the save sends ai_polished so the
// publish-time AI pass only classifies the job and keeps this text.
add_action( 'wp_ajax_site_pulse_jobsites_ai_text', 'sp_jobsites_ajax_ai_text' );
function sp_jobsites_ajax_ai_text(): void {
	sp_jobsites_gate( 'sp_jobsites_can_add', 'You do not have permission to add jobsites.' );
	$in   = wp_unslash( $_POST );
	$desc = sanitize_textarea_field( (string) ( $in['description'] ?? '' ) );
	if ( trim( $desc ) === '' ) wp_send_json_error( [ 'message' => 'Type a description first — AI polishes what you wrote.' ] );

	$terms = get_terms( [ 'taxonomy' => 'jobsite_geo-services', 'hide_empty' => false, 'fields' => 'slugs' ] );
	$city  = sanitize_text_field( (string) ( $in['city'] ?? '' ) );
	$state = sanitize_text_field( (string) ( $in['state'] ?? '' ) );
	$res   = bp_geo_call_anthropic( [
		'raw_description'  => $desc,
		'company_name'     => bp_business_name(),
		'city'             => $city,
		'state'            => $state,
		'location_context' => trim( $city . ', ' . $state, ', ' ),
		'job_date'         => sanitize_text_field( (string) ( $in['job_date'] ?? '' ) ),
		'street'           => sanitize_text_field( (string) ( $in['address'] ?? '' ) ),   // context only, never output
		'zip'              => sanitize_text_field( (string) ( $in['zip'] ?? '' ) ),       // context only, never output
		'existing_terms'   => is_wp_error( $terms ) ? [] : $terms,
	] );
	if ( is_wp_error( $res ) || empty( $res['rewritten_description'] ) ) {
		wp_send_json_error( [ 'message' => 'The AI rewrite didn\'t come back — please try again.' ] );
	}
	wp_send_json_success( [ 'text' => trim( wp_strip_all_tags( bp_fix_entities( (string) $res['rewritten_description'] ) ) ) ] );
}

// AI Rewrite (Photos) — a fresh AI caption for one photo (the form calls this per photo, in parallel).
// Only for a photo this person may use: one already in a job they can edit, or their own new upload.
add_action( 'wp_ajax_site_pulse_jobsites_ai_caption', 'sp_jobsites_ajax_ai_caption' );
function sp_jobsites_ajax_ai_caption(): void {
	$me      = sp_jobsites_gate( 'sp_jobsites_can_add', 'You do not have permission to add jobsites.' );
	$aid     = (int) ( $_POST['id'] ?? 0 );
	$post_id = (int) ( $_POST['post_id'] ?? 0 );
	if ( ! function_exists( 'bp_ai_generate_alt_text' ) || ! function_exists( 'bp_ai_alt_available' ) || ! bp_ai_alt_available() ) {
		wp_send_json_error( [ 'message' => 'AI captions aren\'t set up on this site.' ] );
	}
	$in_job = $post_id && sp_jobsites_can_edit( $post_id, $me ) && in_array( $aid, array_map( 'intval', bp_geo_photo_slot_ids( $post_id ) ), true );
	$mine   = get_post_type( $aid ) === 'attachment' && (int) get_post_field( 'post_author', $aid ) === $me
		&& ( ! (int) get_post_field( 'post_parent', $aid ) || (int) get_post_field( 'post_parent', $aid ) === $post_id );
	if ( ! $aid || ! ( $in_job || $mine ) ) wp_send_json_error( [ 'message' => 'Photo not found.' ] );

	$alt = bp_ai_generate_alt_text( $aid, $post_id && get_post_type( $post_id ) === 'jobsite_geo' ? $post_id : 0 );
	if ( is_wp_error( $alt ) || $alt === '' ) wp_send_json_error( [ 'message' => 'No caption came back for that photo.' ] );
	wp_send_json_success( [ 'caption' => $alt ] );
}

// "Use my location" — phone GPS → street address, via the same Google proxy the GEO app uses.
add_action( 'wp_ajax_site_pulse_jobsites_locate', 'sp_jobsites_ajax_locate' );
function sp_jobsites_ajax_locate(): void {
	sp_jobsites_gate( 'sp_jobsites_can_add', 'You do not have permission to add jobsites.' );
	$lat = (float) ( $_POST['lat'] ?? 0 );
	$lon = (float) ( $_POST['lon'] ?? 0 );
	if ( ! $lat || ! $lon ) wp_send_json_error( [ 'message' => 'No location received.' ] );

	$r = bp_geo_reverse_geocode( $lat, $lon );
	if ( is_wp_error( $r ) ) wp_send_json_error( [ 'message' => 'Couldn\'t look up an address for this spot — please type it in.' ] );
	wp_send_json_success( [
		'address' => trim( $r['house_number'] . ' ' . $r['road'] ),
		'city'    => $r['city'],
		'state'   => $r['state'],
		'zip'     => $r['zip'],
	] );
}


/*--------------------------------------------------------------
# Auto-link Jobsite GEO WordPress users into Site Pulse
--------------------------------------------------------------*/

/**
 * WordPress users with a Jobsite GEO role become Site Pulse users on their own — no "Link Existing WP
 * User" step: bp_jobsite_geo_mgr → Owner tier (all jobs), bp_jobsite_geo (tech) → User tier (add + own).
 * Their WP login and roles are kept (site_pulse_link_existing_user is additive). Administrators are NOT
 * auto-linked — admin accounts are often ours, not the client's — so those stay a manual link.
 * Never touches someone who already has a Site Pulse profile. Returns true if it linked them.
 */
function sp_jobsites_auto_link_user( int $user_id ): bool {
	if ( ! function_exists( 'site_pulse_link_existing_user' ) || ! function_exists( 'site_pulse_module_on' ) ) return false;
	if ( ! sp_jobsites_ready() || ! site_pulse_module_on( 'jobsites' ) ) return false;
	if ( site_pulse_get_user_profile( $user_id ) ) return false;

	$user = get_userdata( $user_id );
	if ( ! $user ) return false;
	$roles = (array) $user->roles;
	if ( in_array( 'bp_jobsite_geo_mgr', $roles, true ) )  $tier = 'owner';
	elseif ( in_array( 'bp_jobsite_geo', $roles, true ) ) $tier = 'user';
	else return false;

	$role = site_pulse_get_role_by_slug( $tier );
	if ( ! $role ) return false;                                // tiers not seeded yet — the backfill retries

	// Re-entry guard: linking adds the site_pulse_user role BEFORE the profile row exists, which fires
	// add_user_role → this function again → another link attempt, in a loop.
	static $busy = [];
	if ( isset( $busy[ $user_id ] ) ) return false;
	$busy[ $user_id ] = true;
	$res = site_pulse_link_existing_user( $user_id, [ 'role_id' => (int) $role['id'] ] );
	unset( $busy[ $user_id ] );
	return ! empty( $res['success'] );
}

// New users, and anyone later given a Jobsite GEO role. Late priority so the role is already set.
add_action( 'user_register', function ( $user_id ) { sp_jobsites_auto_link_user( (int) $user_id ); }, 50 );
add_action( 'set_user_role', function ( $user_id ) { sp_jobsites_auto_link_user( (int) $user_id ); }, 50 );
add_action( 'add_user_role', function ( $user_id ) { sp_jobsites_auto_link_user( (int) $user_id ); }, 50 );

/**
 * One-time backfill: link every EXISTING Jobsite GEO user. Runs after the module/tier seeding (init 10–25),
 * and only marks itself done once the module is on and the tiers exist, so it waits rather than misses.
 */
add_action( 'init', 'sp_jobsites_backfill_users', 30 );
function sp_jobsites_backfill_users(): void {
	if ( get_option( 'site_pulse_jobsites_users_linked' ) ) return;
	if ( ! function_exists( 'site_pulse_module_on' ) || ! sp_jobsites_ready() || ! site_pulse_module_on( 'jobsites' ) ) return;
	if ( ! site_pulse_get_role_by_slug( 'owner' ) || ! site_pulse_get_role_by_slug( 'user' ) ) return;

	foreach ( get_users( [ 'role__in' => [ 'bp_jobsite_geo', 'bp_jobsite_geo_mgr' ], 'fields' => 'ID' ] ) as $uid ) {
		sp_jobsites_auto_link_user( (int) $uid );
	}
	update_option( 'site_pulse_jobsites_users_linked', '1' );
}


/*--------------------------------------------------------------
# Migrations (auto-enable + caps seed)
--------------------------------------------------------------*/

/**
 * One-time: switch the Jobsites module on for installs that already run Jobsite GEO. Runs after the
 * module seed (init 10) so a fresh install's seed can't overwrite it. An install without Jobsite GEO is
 * left alone (and re-checked on later loads, so installing Jobsite GEO afterwards still flips it on).
 */
add_action( 'init', 'sp_jobsites_migrate_enable', 20 );
function sp_jobsites_migrate_enable(): void {
	if ( get_option( 'site_pulse_jobsites_enabled_migrated' ) ) return;
	if ( ! function_exists( 'bp_module_on' ) || ! bp_module_on( get_option( 'jobsite_geo' ) ) ) return;
	if ( ! function_exists( 'site_pulse_get_setting' ) ) return;

	$state = json_decode( site_pulse_get_setting( 'modules', '{}' ), true );
	if ( ! is_array( $state ) ) $state = [];
	if ( ! array_key_exists( 'jobsites', $state ) ) {          // never override a deliberate off
		$state['jobsites'] = '1';
		site_pulse_set_setting( 'modules', wp_json_encode( $state ) );
	}
	update_option( 'site_pulse_jobsites_enabled_migrated', '1' );
}

/**
 * One-time, once the module is on: owner/admin/system-admin get everything; every other staff tier
 * can view and add (and edit their own). Insert-only per role; self-guarded. Mirrors sp_cc_migrate_caps().
 */
add_action( 'init', 'sp_jobsites_migrate_caps', 25 );
function sp_jobsites_migrate_caps(): void {
	if ( get_option( 'site_pulse_jobsites_caps_seeded' ) ) return;
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'jobsites' ) ) return;

	global $wpdb;
	$all    = [ 'view_jobsites', 'add_jobsites', 'manage_jobsites' ];
	$add    = [ 'view_jobsites', 'add_jobsites' ];
	$grants = [
		'owner'             => $all,
		'admin'             => $all,
		'system-admin'      => $all,
		'supervisor'        => $add,
		'manager'           => $add,
		'non-store-manager' => $add,
		'user'              => $add,
	];
	foreach ( $grants as $slug => $caps ) {
		$role = site_pulse_get_role_by_slug( $slug );
		if ( ! $role ) continue;
		$have   = json_decode( $role['capabilities'], true ) ?: [];
		$merged = array_values( array_unique( array_merge( $have, $caps ) ) );
		if ( $merged !== $have ) {
			$wpdb->update( site_pulse_table( 'roles' ), [ 'capabilities' => wp_json_encode( $merged ) ], [ 'id' => (int) $role['id'] ] );
		}
	}
	update_option( 'site_pulse_jobsites_caps_seeded', '1' );
}
