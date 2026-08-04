<?php
/* Battle Plan Web Design - Site Pulse: Customer Connect (staff side)
   The STAFF side of Customer Connect, native to Site Pulse. Customer Connect's customer-facing
   PWA (OTP + custom customer_connect_customers table + customer-connect/v1 REST) lives in
   includes-customer-connect*.php and is unchanged — this file is only the company's
   dashboard surface: the customer roster, the push composer, and the scheduling
   request inbox. Ported from the old standalone /customer-connect-admin/ dashboard
   (includes-customer-connect-admin.php, now retired) onto Site Pulse's admin-ajax +
   capability model.

   Staff are real Site Pulse users, so access is gated by the Customer Connect module
   caps (role default + per-user override; god always passes), exactly like the
   Directory module. All the customer/push/scheduling DATA still lives in the
   customer_connect_* tables and is reached through the cc_* helpers.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Permissions
# AJAX — Customers (roster)
# AJAX — Push (segments + send)
# AJAX — Scheduling (list + status)
# Migration (caps seed)
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Permissions
--------------------------------------------------------------*/

// See the customer roster.
function sp_cc_can_view( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id )
		|| site_pulse_user_can( $user_id, 'view_customers' )
		|| site_pulse_user_can( $user_id, 'manage_customers' );
}

// Edit customers (reserved — the roster is read-only today; the cap exists so a
// future customer-edit panel gates cleanly).
function sp_cc_can_manage( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_customers' );
}

// Compose and send customer notifications.
function sp_cc_can_send( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'send_customer_push' );
}

// See the scheduling request inbox.
function sp_cc_can_view_schedule( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id )
		|| site_pulse_user_can( $user_id, 'view_schedule_requests' )
		|| site_pulse_user_can( $user_id, 'manage_schedule_requests' );
}

// Act on scheduling requests (change status).
function sp_cc_can_manage_schedule( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_schedule_requests' );
}


/*--------------------------------------------------------------
# AJAX — Customers (roster)
--------------------------------------------------------------*/

/**
 * GET-style roster with equipment + subscribed-device counts. Ported from the
 * old cc_admin_rest_customers. Reads the customer-facing tables via cc_table().
 */
add_action( 'wp_ajax_site_pulse_cc_customers', 'sp_cc_ajax_customers' );
function sp_cc_ajax_customers(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_cc_can_view( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have access to Customer Connect customers.' ] );
	}
	if ( ! function_exists( 'cc_table' ) ) {
		wp_send_json_error( [ 'message' => 'Customer Connect is not fully installed on this site.' ] );
	}

	global $wpdb;
	$c = cc_table( 'customers' );
	$e = cc_table( 'equipment' );
	$p = cc_table( 'push_subscriptions' );

	$search = trim( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ) );
	$where  = "WHERE c.status = 'active'";
	$params = [];
	if ( $search !== '' ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= " AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.phone LIKE %s OR c.email LIKE %s)";
		array_push( $params, $like, $like, $like, $like );
	}

	$sql = "SELECT c.id, c.first_name, c.last_name, c.phone, c.email, c.city, c.state,
				(SELECT COUNT(*) FROM $e e WHERE e.customer_id = c.id) AS equipment_count,
				(SELECT COUNT(*) FROM $p p WHERE p.customer_id = c.id) AS device_count
			FROM $c c $where ORDER BY c.last_login_at DESC, c.id DESC LIMIT 200";
	$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	$rows = $rows ?: [];

	$items = array_map( function ( $r ) {
		$name = trim( $r['first_name'] . ' ' . $r['last_name'] );
		return [
			'id'         => (int) $r['id'],
			'name'       => $name !== '' ? $name : '(no name)',
			'contact'    => $r['phone'] ?: $r['email'],
			'location'   => trim( trim( $r['city'] . ', ' . $r['state'], ', ' ) ),
			'equipment'  => (int) $r['equipment_count'],
			'subscribed' => (int) $r['device_count'] > 0,
		];
	}, $rows );

	wp_send_json_success( [
		'items'    => $items,
		'can_send' => sp_cc_can_send( $me ),
	] );
}


/*--------------------------------------------------------------
# AJAX — Push (segments + send)
--------------------------------------------------------------*/

/** Audience sizes for the compose screen + whether push is configured. */
add_action( 'wp_ajax_site_pulse_cc_segments', 'sp_cc_ajax_segments' );
function sp_cc_ajax_segments(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_cc_can_send() ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to send notifications.' ] );
	}
	if ( ! function_exists( 'cc_push_segment_ids' ) ) {
		wp_send_json_error( [ 'message' => 'Customer Connect push is not installed on this site.' ] );
	}

	$segs = [
		[ 'key' => 'all',        'label' => 'All subscribers',      'count' => count( cc_push_segment_ids( 'all' ) ) ],
		[ 'key' => 'filter_due', 'label' => 'Filter due / overdue', 'count' => count( cc_push_segment_ids( 'filter_due' ) ) ],
	];
	wp_send_json_success( [ 'segments' => $segs, 'push_ready' => cc_push_ready() ] );
}

/**
 * Dispatch a notification to one customer or a segment. Ported from
 * cc_admin_rest_send onto admin-ajax. Body via $_POST:
 *   target=customer, customer_id, title, body, url?
 *   target=segment,  segment,     title, body, url?
 */
add_action( 'wp_ajax_site_pulse_cc_send', 'sp_cc_ajax_send' );
function sp_cc_ajax_send(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_cc_can_send( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to send notifications.' ] );
	}
	if ( ! function_exists( 'cc_push_ready' ) || ! cc_push_ready() ) {
		wp_send_json_error( [ 'message' => 'Push is not configured on this site yet.' ] );
	}

	$title  = trim( sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) );
	$text   = trim( sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ) );
	$url    = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
	$target = sanitize_key( $_POST['target'] ?? '' );

	if ( $title === '' ) wp_send_json_error( [ 'message' => 'Add a title.' ] );

	$note = [ 'title' => $title, 'body' => $text ];
	if ( $url !== '' ) $note['url'] = $url;
	$by = (int) get_current_user_id();

	if ( $target === 'customer' ) {
		$cid = (int) ( $_POST['customer_id'] ?? 0 );
		if ( $cid <= 0 ) wp_send_json_error( [ 'message' => 'Pick a customer.' ] );
		$delivered = cc_push_send_to_customer( $cid, $note, $by );
		wp_send_json_success( [ 'recipients' => 1, 'delivered' => $delivered ] );
	}

	if ( $target === 'segment' ) {
		$segment = sanitize_key( $_POST['segment'] ?? '' );
		$ids     = cc_push_segment_ids( $segment );
		if ( ! $ids ) wp_send_json_error( [ 'message' => 'That audience has no subscribers right now.' ] );
		$res = cc_push_broadcast( $ids, $note, $by );
		wp_send_json_success( $res );
	}

	wp_send_json_error( [ 'message' => 'Choose who to send to.' ] );
}


/*--------------------------------------------------------------
# AJAX — Scheduling (list + status)
--------------------------------------------------------------*/

/** The scheduling-request inbox, newest + still-open first, with the customer joined on. */
add_action( 'wp_ajax_site_pulse_cc_schedule', 'sp_cc_ajax_schedule' );
function sp_cc_ajax_schedule(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_cc_can_view_schedule( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have access to scheduling requests.' ] );
	}
	if ( ! function_exists( 'cc_table' ) ) {
		wp_send_json_error( [ 'message' => 'Customer Connect is not fully installed on this site.' ] );
	}

	global $wpdb;
	$r = cc_table( 'schedule_requests' );
	$c = cc_table( 'customers' );

	$rows = $wpdb->get_results(
		"SELECT req.id, req.customer_id, req.type, req.urgency, req.preferred_date, req.preferred_window,
				req.message, req.status, req.created_at,
				cust.first_name, cust.last_name, cust.phone, cust.email
		 FROM $r req LEFT JOIN $c cust ON cust.id = req.customer_id
		 ORDER BY (req.status = 'new') DESC, req.created_at DESC LIMIT 200",
		ARRAY_A
	) ?: [];

	$items = array_map( function ( $row ) {
		$name = trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] );
		return [
			'id'               => (int) $row['id'],
			'customer_id'      => (int) $row['customer_id'],
			'customer'         => $name !== '' ? $name : '(unknown customer)',
			'contact'          => $row['phone'] ?: $row['email'],
			'type'             => (string) $row['type'],
			'urgency'          => (string) $row['urgency'],
			'preferred_date'   => ( $row['preferred_date'] && $row['preferred_date'] !== '0000-00-00' ) ? $row['preferred_date'] : '',
			'preferred_window' => (string) $row['preferred_window'],
			'message'          => (string) $row['message'],
			'status'           => (string) $row['status'],
			'created_at'       => (string) $row['created_at'],
		];
	}, $rows );

	wp_send_json_success( [
		'items'      => $items,
		'can_manage' => sp_cc_can_manage_schedule( $me ),
	] );
}

/** Change a scheduling request's status (new | scheduled | done | dismissed). */
add_action( 'wp_ajax_site_pulse_cc_schedule_update', 'sp_cc_ajax_schedule_update' );
function sp_cc_ajax_schedule_update(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_cc_can_manage_schedule() ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to update scheduling requests.' ] );
	}
	if ( ! function_exists( 'cc_table' ) ) {
		wp_send_json_error( [ 'message' => 'Customer Connect is not fully installed on this site.' ] );
	}

	$id     = (int) ( $_POST['id'] ?? 0 );
	$status = sanitize_key( $_POST['status'] ?? '' );
	$allowed = [ 'new', 'scheduled', 'done', 'dismissed' ];
	if ( ! $id || ! in_array( $status, $allowed, true ) ) {
		wp_send_json_error( [ 'message' => 'Bad request.' ] );
	}

	global $wpdb;
	$wpdb->update(
		cc_table( 'schedule_requests' ),
		[ 'status' => $status, 'updated_at' => current_time( 'mysql' ) ],
		[ 'id' => $id ]
	);
	wp_send_json_success( [ 'id' => $id, 'status' => $status ] );
}


/*--------------------------------------------------------------
# Settings (Settings → Customer Connect) — manage_settings
--------------------------------------------------------------*/

/** Staff-editable Customer Connect settings; stored as site_pulse settings under 'cc_'-prefixed keys. */
function sp_cc_settings_keys(): array {
	// Brand colors are intentionally NOT here — Customer Connect draws its palette from the Site Pulse
	// color scheme (see cc_brand_tones()), so there's no separate customer-app color to save.
	return [ 'app_name', 'company_name', 'pwa_short_name', 'pwa_background_color', 'pwa_icon_url', 'push_contact' ];
}

add_action( 'wp_ajax_site_pulse_cc_get_settings', 'sp_cc_ajax_get_settings' );
function sp_cc_ajax_get_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	$out = [];
	foreach ( sp_cc_settings_keys() as $key ) {
		// The EFFECTIVE value (SP setting, else the functions-site.php install option) so the form mirrors reality.
		$out[ $key ] = function_exists( 'cc_get' ) ? (string) cc_get( $key, '' ) : (string) site_pulse_get_setting( 'cc_' . $key, '' );
	}
	wp_send_json_success( [ 'settings' => $out ] );
}

add_action( 'wp_ajax_site_pulse_cc_save_settings', 'sp_cc_ajax_save_settings' );
function sp_cc_ajax_save_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	foreach ( sp_cc_settings_keys() as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) continue;
		site_pulse_set_setting( 'cc_' . $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
	}
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'cc_settings_saved', 'Updated Customer Connect settings.' );
	wp_send_json_success( [ 'message' => 'Saved.' ] );
}


/*--------------------------------------------------------------
# Migrations
--------------------------------------------------------------*/

/**
 * One-time carry-over: any install that had the OLD standalone Customer Connect enabled
 * (the get_option('customer_connect') install flag) gets the Site Pulse "customer_connect" module
 * switched on, so its customer PWA keeps loading after the loader gate moved to
 * site_pulse_module_on(). Runs AFTER the module seed (site_pulse_seed_modules, init
 * priority 10) so a fresh install's seed can't overwrite this back to off. Self-guarded.
 */
add_action( 'init', 'sp_cc_migrate_enable', 20 );
function sp_cc_migrate_enable(): void {
	if ( get_option( 'site_pulse_customer_connect_enabled_migrated' ) ) return;

	$old       = get_option( 'customer_connect' );
	$installed = is_array( $old ) && ( (string) ( $old['install'] ?? '' ) === 'true' );
	if ( ! $installed ) {
		update_option( 'site_pulse_customer_connect_enabled_migrated', '1' ); // nothing to carry over
		return;
	}
	if ( ! function_exists( 'site_pulse_get_setting' ) ) return; // SP not ready yet; retry next load

	$state = json_decode( site_pulse_get_setting( 'modules', '{}' ), true );
	if ( ! is_array( $state ) ) $state = [];
	if ( (string) ( $state['customer_connect'] ?? '' ) !== '1' ) {
		$state['customer_connect'] = '1';
		site_pulse_set_setting( 'modules', wp_json_encode( $state ) );
	}
	update_option( 'site_pulse_customer_connect_enabled_migrated', '1' );
}

/**
 * One-time, only on installs that ENABLE Customer Connect: grant the Customer Connect caps to
 * the tiers that should have them out of the box — owner/admin get the full set.
 * Insert-only per role, and self-guarded by an option flag once the module is on
 * (so an install that never enables Customer Connect is never touched, and enabling it
 * later triggers the seed on the next load). Mirrors sp_directory_migrate_caps().
 */
add_action( 'init', 'sp_cc_migrate_caps', 25 );
function sp_cc_migrate_caps(): void {
	if ( get_option( 'site_pulse_customer_connect_caps_seeded' ) ) return;
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'customer_connect' ) ) return; // wait until enabled

	global $wpdb;
	$all = [ 'view_customers', 'send_customer_push', 'view_schedule_requests', 'manage_customers', 'manage_schedule_requests' ];
	$grants = [
		'owner' => $all,
		'admin' => $all,
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
	update_option( 'site_pulse_customer_connect_caps_seeded', '1' );
}
