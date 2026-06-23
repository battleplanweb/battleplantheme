<?php
/* Battle Plan Web Design - Site Pulse Company Directory
   A staff directory stored natively in Site Pulse (one row per person), not a CPT. Most people
   in the directory are NOT Site Pulse users — it's just a place to keep names, photos, contact
   info, and a few personal notes. Access is gated by the view_directory / manage_directory
   capabilities (so it's controlled by role AND by per-user override, like everything else).

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Helpers & Permissions
# AJAX — List
# AJAX — Save / Delete
# AJAX — Photo Upload
# Migrations (caps seed + legacy CPT import)
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Helpers & Permissions
--------------------------------------------------------------*/

function sp_directory_table(): string {
	return site_pulse_table( 'employees' );
}

// Who can SEE the directory. Driven purely by capability (role default + per-user override); god
// always passes. site_pulse_user_can() already returns true for god, but we keep the explicit
// check for clarity and the wp-admin fallback path.
function sp_directory_can_view( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id )
		|| site_pulse_user_can( $user_id, 'view_directory' )
		|| site_pulse_user_can( $user_id, 'manage_directory' );
}

// Who can ADD / EDIT / DELETE directory records.
function sp_directory_can_manage( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_directory' );
}

// Normalize whatever date string we're handed (Y-m-d, m/d/Y, or ACF's stored Ymd) to Y-m-d, or ''.
function sp_directory_clean_date( $v ): string {
	$v = trim( (string) $v );
	if ( $v === '' || $v === '0000-00-00' ) return '';
	if ( preg_match( '/^\d{8}$/', $v ) ) {
		$v = substr( $v, 0, 4 ) . '-' . substr( $v, 4, 2 ) . '-' . substr( $v, 6, 2 );
	}
	$ts = strtotime( $v );
	return $ts ? gmdate( 'Y-m-d', $ts ) : '';
}

// One DB row -> the clean payload the JS expects. Labels for position/brand/location are mapped
// client-side from the option lists also returned by the list endpoint, so we send the raw keys.
function sp_directory_format_row( array $r ): array {
	$dob  = $r['date_of_birth'] ?? '';
	$hire = $r['date_of_hire'] ?? '';
	return [
		'id'            => (int) $r['id'],
		'user_id'       => (int) ( $r['user_id'] ?? 0 ),
		'status'        => $r['status'] ?? 'active',
		'first_name'    => (string) ( $r['first_name'] ?? '' ),
		'last_name'     => (string) ( $r['last_name'] ?? '' ),
		'photo_url'     => (string) ( $r['photo_url'] ?? '' ),
		'position'      => (string) ( $r['position'] ?? '' ),
		'brand'         => (string) ( $r['brand'] ?? '' ),
		'location'      => (string) ( $r['location'] ?? '' ),
		'email'         => (string) ( $r['email'] ?? '' ),
		'cell_phone'    => (string) ( $r['cell_phone'] ?? '' ),
		'sec_phone'     => (string) ( $r['sec_phone'] ?? '' ),
		'date_of_birth' => ( $dob && $dob !== '0000-00-00' ) ? $dob : '',
		'date_of_hire'  => ( $hire && $hire !== '0000-00-00' ) ? $hire : '',
		'home_address'  => (string) ( $r['home_address'] ?? '' ),
		'address_city'  => (string) ( $r['address_city'] ?? '' ),
		'address_zip'   => (string) ( $r['address_zip'] ?? '' ),
		'spouse'        => (string) ( $r['spouse'] ?? '' ),
		'children'      => (string) ( $r['children'] ?? '' ),
		'hobbies'       => (string) ( $r['hobbies'] ?? '' ),
		'other_info'    => (string) ( $r['other_info'] ?? '' ),
		'days_off'      => (string) ( $r['days_off'] ?? '' ),
	];
}

// The three option lists (key => label), sourced from the site filters so each company supplies
// its own (see functions-site.php). Bundled into list responses so the JS can render selects + map
// keys to labels on the cards.
function sp_directory_options(): array {
	return [
		'positions' => function_exists( 'site_pulse_employee_positions' ) ? site_pulse_employee_positions() : [],
		'brands'    => function_exists( 'site_pulse_employee_brands' )    ? site_pulse_employee_brands()    : [],
		'locations' => function_exists( 'site_pulse_employee_locations' ) ? site_pulse_employee_locations() : [],
	];
}


/*--------------------------------------------------------------
# AJAX — List
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_directory_list', 'sp_directory_ajax_list' );
function sp_directory_ajax_list(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_directory_can_view( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have access to the directory.' ] );
	}

	global $wpdb;
	$t = sp_directory_table();

	$status   = sanitize_key( $_POST['status'] ?? 'active' );          // active | inactive | all
	$search   = trim( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ) );
	$brand    = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
	$location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
	$position = sanitize_text_field( wp_unslash( $_POST['position'] ?? '' ) );

	$where = [];
	$args  = [];
	if ( $status === 'active' || $status === 'inactive' ) { $where[] = 'status = %s';   $args[] = $status; }
	if ( $brand !== '' )    { $where[] = 'brand = %s';    $args[] = $brand; }
	if ( $location !== '' ) { $where[] = 'location = %s'; $args[] = $location; }
	if ( $position !== '' ) { $where[] = 'position = %s'; $args[] = $position; }
	if ( $search !== '' ) {
		$like    = '%' . $wpdb->esc_like( $search ) . '%';
		$where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR cell_phone LIKE %s OR sec_phone LIKE %s OR CONCAT(first_name, " ", last_name) LIKE %s)';
		array_push( $args, $like, $like, $like, $like, $like, $like );
	}

	$sql = "SELECT * FROM $t";
	if ( $where ) $sql .= ' WHERE ' . implode( ' AND ', $where );
	$sql .= ' ORDER BY last_name ASC, first_name ASC';

	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

	$out = [];
	foreach ( $rows ?: [] as $r ) $out[] = sp_directory_format_row( $r );

	wp_send_json_success( [
		'employees'      => $out,
		'can_manage'     => sp_directory_can_manage( $me ),
		'active_count'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'active'" ),
		'inactive_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'inactive'" ),
		'options'        => sp_directory_options(),
	] );
}


/*--------------------------------------------------------------
# AJAX — Save / Delete
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_directory_save', 'sp_directory_ajax_save' );
function sp_directory_ajax_save(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_directory_can_manage( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to edit the directory.' ] );
	}

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );

	$first = trim( sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ) );
	$last  = trim( sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ) );
	if ( $first === '' && $last === '' ) {
		wp_send_json_error( [ 'message' => 'Please enter a first or last name.' ] );
	}

	$days = $_POST['days_off'] ?? [];
	if ( ! is_array( $days ) ) $days = ( $days !== '' ) ? explode( ',', (string) $days ) : [];
	$days = implode( ',', array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $days ) ) ) );

	$data = [
		'status'        => ( ( $_POST['status'] ?? 'active' ) === 'inactive' ) ? 'inactive' : 'active',
		'first_name'    => $first,
		'last_name'     => $last,
		'photo_url'     => esc_url_raw( wp_unslash( $_POST['photo_url'] ?? '' ) ) ?: null,
		'position'      => sanitize_text_field( wp_unslash( $_POST['position'] ?? '' ) ),
		'brand'         => sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) ),
		'location'      => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ),
		'email'         => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
		'cell_phone'    => sanitize_text_field( wp_unslash( $_POST['cell_phone'] ?? '' ) ),
		'sec_phone'     => sanitize_text_field( wp_unslash( $_POST['sec_phone'] ?? '' ) ),
		'date_of_birth' => sp_directory_clean_date( $_POST['date_of_birth'] ?? '' ) ?: null,
		'date_of_hire'  => sp_directory_clean_date( $_POST['date_of_hire'] ?? '' ) ?: null,
		'home_address'  => sanitize_text_field( wp_unslash( $_POST['home_address'] ?? '' ) ),
		'address_city'  => sanitize_text_field( wp_unslash( $_POST['address_city'] ?? '' ) ),
		'address_zip'   => sanitize_text_field( wp_unslash( $_POST['address_zip'] ?? '' ) ),
		'spouse'        => sanitize_text_field( wp_unslash( $_POST['spouse'] ?? '' ) ),
		'children'      => sanitize_text_field( wp_unslash( $_POST['children'] ?? '' ) ),
		'hobbies'       => sanitize_text_field( wp_unslash( $_POST['hobbies'] ?? '' ) ),
		'other_info'    => sanitize_textarea_field( wp_unslash( $_POST['other_info'] ?? '' ) ),
		'days_off'      => $days,
		'user_id'       => (int) ( $_POST['user_id'] ?? 0 ),
		'updated_at'    => current_time( 'mysql' ),
	];

	if ( $id ) {
		$wpdb->update( sp_directory_table(), $data, [ 'id' => $id ] );
	} else {
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( sp_directory_table(), $data );
		$id = (int) $wpdb->insert_id;
	}

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_directory_table() . " WHERE id = %d", $id ), ARRAY_A );
	wp_send_json_success( [ 'employee' => $row ? sp_directory_format_row( $row ) : null ] );
}

add_action( 'wp_ajax_site_pulse_directory_delete', 'sp_directory_ajax_delete' );
function sp_directory_ajax_delete(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_directory_can_manage( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to edit the directory.' ] );
	}
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Missing employee.' ] );

	global $wpdb;
	$wpdb->delete( sp_directory_table(), [ 'id' => $id ] );
	wp_send_json_success( [ 'id' => $id ] );
}


/*--------------------------------------------------------------
# AJAX — Photo Upload
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_directory_upload_photo', 'sp_directory_ajax_upload_photo' );
function sp_directory_ajax_upload_photo(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_directory_can_manage( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to edit the directory.' ] );
	}

	if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ?? '' ) ) {
		wp_send_json_error( [ 'message' => 'No file received.' ] );
	}
	$file = $_FILES['file'];
	if ( (int) $file['size'] > 10 * 1024 * 1024 ) {
		wp_send_json_error( [ 'message' => 'Image is too large (max 10 MB).' ] );
	}

	$ext     = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	$allowed = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'bmp' ];
	if ( ! in_array( $ext, $allowed, true ) ) {
		wp_send_json_error( [ 'message' => 'Please upload an image (jpg, png, gif, or webp).' ] );
	}
	$check = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'] );
	if ( empty( $check['ext'] ) || empty( $check['type'] ) || strpos( (string) $check['type'], 'image/' ) !== 0 ) {
		wp_send_json_error( [ 'message' => 'That file is not a valid image.' ] );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$subdir = 'site-pulse-directory';
	$redir  = function ( $dirs ) use ( $subdir ) {
		$dirs['subdir'] = '/' . $subdir . $dirs['subdir'];
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	};
	add_filter( 'upload_dir', $redir );
	$file['name'] = 'emp-' . wp_generate_password( 16, false, false ) . '.' . $check['ext'];
	$moved = wp_handle_upload( $file, [ 'test_form' => false ] );
	remove_filter( 'upload_dir', $redir );

	if ( empty( $moved['url'] ) || ! empty( $moved['error'] ) ) {
		wp_send_json_error( [ 'message' => $moved['error'] ?? 'Upload failed.' ] );
	}
	wp_send_json_success( [ 'url' => $moved['url'] ] );
}


/*--------------------------------------------------------------
# Migrations (caps seed + legacy CPT import)
--------------------------------------------------------------*/

/**
 * One-time: grant the directory caps to the tiers that should have it out of the box — owner/admin
 * get view + manage, supervisor gets view. GMs (manager) get nothing. Insert-only per role (never
 * removes a cap an admin later took away), and self-guarded by an option flag. God always sees it
 * regardless. Mirrors site_pulse_migrate_survey_caps().
 */
function sp_directory_migrate_caps(): void {
	if ( get_option( 'site_pulse_directory_caps_seeded' ) ) return;
	global $wpdb;

	$grants = [
		'owner'      => [ 'view_directory', 'manage_directory' ],
		'admin'      => [ 'view_directory', 'manage_directory' ],
		'supervisor' => [ 'view_directory' ],
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
	update_option( 'site_pulse_directory_caps_seeded', '1' );
}

/**
 * One-time: import legacy Employees / Former Employees CPT records into the directory table. Reads
 * straight from wp_posts/postmeta (no dependency on the CPT being registered or ACF rendering), so
 * it survives the eventual removal of the CPT. former-employees (or current_employee = No) become
 * 'inactive'. Idempotent via legacy_post_id, and self-guarded by an option flag.
 */
function sp_directory_import_from_cpt(): void {
	if ( get_option( 'site_pulse_directory_imported' ) ) return;
	global $wpdb;

	$t = sp_directory_table();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) return; // table not built yet

	$posts = $wpdb->get_results(
		"SELECT ID, post_type, post_title FROM {$wpdb->posts}
		 WHERE post_type IN ('employees','former-employees')
		 AND post_status NOT IN ('trash','auto-draft')",
		ARRAY_A
	);
	if ( ! $posts ) { update_option( 'site_pulse_directory_imported', '1' ); return; }

	$now      = current_time( 'mysql' );
	$imported = 0;

	foreach ( $posts as $p ) {
		$pid = (int) $p['ID'];

		// Idempotent: never import the same post twice.
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE legacy_post_id = %d", $pid ) ) ) continue;

		$m     = function ( $key ) use ( $pid ) { return get_post_meta( $pid, $key, true ); };
		$first = trim( (string) $m( 'first_name' ) );
		$last  = trim( (string) $m( 'last_name' ) );

		// Salvage records whose name lived only in the post title.
		if ( $first === '' && $last === '' ) {
			$title = trim( (string) $p['post_title'] );
			if ( $title !== '' ) {
				if ( strpos( $title, ',' ) !== false ) {           // "Last, First"
					list( $last, $first ) = array_pad( array_map( 'trim', explode( ',', $title, 2 ) ), 2, '' );
				} else {                                            // "First Last"
					$bits  = preg_split( '/\s+/', $title );
					$first = array_shift( $bits );
					$last  = implode( ' ', $bits );
				}
			}
		}
		if ( $first === '' && $last === '' ) continue; // nothing usable

		$current = strtolower( trim( (string) $m( 'current_employee' ) ) );
		$status  = ( $p['post_type'] === 'former-employees' || $current === 'no' ) ? 'inactive' : 'active';

		$days = $m( 'days_off' );
		if ( is_array( $days ) ) $days = implode( ',', $days );

		$photo = get_the_post_thumbnail_url( $pid, 'medium' );

		$wpdb->insert( $t, [
			'user_id'        => 0,
			'status'         => $status,
			'first_name'     => sanitize_text_field( $first ),
			'last_name'      => sanitize_text_field( $last ),
			'photo_url'      => $photo ? esc_url_raw( $photo ) : null,
			'position'       => sanitize_text_field( (string) $m( 'position' ) ),
			'brand'          => sanitize_text_field( (string) $m( 'brand' ) ),
			'location'       => sanitize_text_field( (string) $m( 'location' ) ),
			'email'          => sanitize_email( (string) $m( 'email' ) ),
			'cell_phone'     => sanitize_text_field( (string) $m( 'cell_phone' ) ),
			'sec_phone'      => sanitize_text_field( (string) $m( 'sec_phone' ) ),
			'date_of_birth'  => sp_directory_clean_date( $m( 'date_of_birth' ) ) ?: null,
			'date_of_hire'   => sp_directory_clean_date( $m( 'date_of_hire' ) ) ?: null,
			'home_address'   => sanitize_text_field( (string) $m( 'home_address' ) ),
			'address_city'   => sanitize_text_field( (string) $m( 'address_city' ) ),
			'address_zip'    => sanitize_text_field( (string) $m( 'address_zip' ) ),
			'spouse'         => sanitize_text_field( (string) $m( 'spouse' ) ),
			'children'       => sanitize_text_field( (string) $m( 'children' ) ),
			'hobbies'        => sanitize_text_field( (string) $m( 'hobbies' ) ),
			'other_info'     => sanitize_textarea_field( (string) $m( 'other_info' ) ),
			'days_off'       => sanitize_text_field( (string) $days ),
			'legacy_post_id' => $pid,
			'created_at'     => $now,
			'updated_at'     => $now,
		] );
		$imported++;
	}

	update_option( 'site_pulse_directory_imported', '1' );
	if ( function_exists( 'site_pulse_log' ) ) {
		site_pulse_log( 'directory_import', "Imported {$imported} employees from the legacy directory." );
	}
}
