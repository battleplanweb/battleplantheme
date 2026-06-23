<?php
/* Battle Plan Web Design - Site Pulse Customer Emails (Customer Feedback → Emails)

   Customer-service emails (complaints, compliments, comments…) submitted through the public client
   sites' contact forms are forwarded — cross-site, HMAC-signed — into MyRovin, where they appear under
   Customer Feedback → Emails. Only FLAGGED submissions are forwarded; the sender decides which (see the
   configurable forwarder in functions-rovin.php / the bp_feedback_* filters). This file is the receiver
   + the in-app inbox, and mirrors the Surveys pipeline.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Helpers & Permissions
# REST receiver — POST /wp-json/site-pulse/v1/email
# AJAX — list / status / delete
# Migrations (caps seed)
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Helpers & Permissions
--------------------------------------------------------------*/

function sp_emails_table(): string {
	return site_pulse_table( 'emails' );
}

// The shared cross-site secret (same one the survey pipeline uses for this rovin.work install).
function sp_emails_secret(): string {
	$opt = apply_filters( 'site_pulse_email_secret_option', 'bp_rovin_survey_secret' );
	return (string) get_site_option( $opt );
}

function sp_emails_can_view( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id )
		|| site_pulse_user_can( $user_id, 'view_emails' )
		|| site_pulse_user_can( $user_id, 'manage_emails' );
}

function sp_emails_can_manage( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_emails' );
}

// One DB row → the clean payload the JS expects.
function sp_emails_format_row( array $r ): array {
	return [
		'id'            => (int) $r['id'],
		'source_site'   => (string) ( $r['source_site'] ?? '' ),
		'brand'         => (string) ( $r['brand'] ?? '' ),
		'location'      => (string) ( $r['location'] ?? '' ),
		'category'      => (string) ( $r['category'] ?? '' ),
		'customer_name' => (string) ( $r['customer_name'] ?? '' ),
		'email'         => (string) ( $r['email'] ?? '' ),
		'phone'         => (string) ( $r['phone'] ?? '' ),
		'message'       => (string) ( $r['message'] ?? '' ),
		'page_url'      => (string) ( $r['page_url'] ?? '' ),
		'status'        => (string) ( $r['status'] ?? 'new' ),
		'created_at'    => (string) ( $r['created_at'] ?? '' ),
	];
}


/*--------------------------------------------------------------
# REST receiver — POST /wp-json/site-pulse/v1/email
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'site-pulse/v1', '/email', [
		'methods'             => 'POST',
		'callback'            => 'sp_emails_rest_receive',
		'permission_callback' => '__return_true', // auth is the HMAC check inside the callback
	] );
} );

/**
 * Verify the shared-secret HMAC over the raw request body, then store the email.
 * Contract (matches the sender): sig = hash_hmac('sha256', rawBody, secret), sent as
 * `Authorization: Bearer <sig>` — recomputed here over the exact bytes received.
 */
function sp_emails_rest_receive( WP_REST_Request $request ) {
	$secret = sp_emails_secret();
	if ( empty( $secret ) ) {
		return new WP_Error( 'sp_email_unconfigured', 'Email receiver not configured.', [ 'status' => 503 ] );
	}

	$raw    = $request->get_body();
	$header = (string) $request->get_header( 'authorization' );
	$given  = preg_replace( '/^\s*Bearer\s+/i', '', $header );
	$expect = hash_hmac( 'sha256', $raw, $secret );

	if ( ! $given || ! hash_equals( $expect, $given ) ) {
		return new WP_Error( 'sp_email_bad_sig', 'Signature mismatch.', [ 'status' => 403 ] );
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'sp_email_bad_body', 'Malformed payload.', [ 'status' => 400 ] );
	}

	$id = sp_emails_store( $data );
	if ( ! $id ) {
		return new WP_Error( 'sp_email_store_failed', 'Could not store email.', [ 'status' => 500 ] );
	}

	return new WP_REST_Response( [ 'ok' => true, 'id' => $id ], 200 );
}

/** Insert one email row from a decoded payload. Returns the new id or 0. */
function sp_emails_store( array $data ): int {
	global $wpdb;

	$ok = $wpdb->insert(
		sp_emails_table(),
		[
			'source_site'   => sanitize_text_field( (string) ( $data['site']     ?? '' ) ) ?: null,
			'brand'         => sanitize_text_field( (string) ( $data['brand']    ?? '' ) ) ?: null,
			'location'      => sanitize_text_field( (string) ( $data['location'] ?? '' ) ) ?: null,
			'category'      => sanitize_text_field( (string) ( $data['category'] ?? '' ) ) ?: null,
			'customer_name' => sanitize_text_field( (string) ( $data['name']     ?? '' ) ) ?: null,
			'email'         => sanitize_email(      (string) ( $data['email']    ?? '' ) ) ?: null,
			'phone'         => sanitize_text_field( (string) ( $data['phone']    ?? '' ) ) ?: null,
			'message'       => sanitize_textarea_field( (string) ( $data['message'] ?? '' ) ) ?: null,
			'page_url'      => esc_url_raw( (string) ( $data['page'] ?? '' ) ) ?: null,
			'status'        => 'new',
			'source_ip'     => sanitize_text_field( (string) ( $data['ip'] ?? '' ) ) ?: null,
			'created_at'    => current_time( 'mysql' ),
		],
		[ '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
	);

	if ( ! $ok ) return 0;
	$email_id = (int) $wpdb->insert_id;

	// Route per the Notifications matrix (no-op until an admin configures the event/recipients).
	if ( function_exists( 'site_pulse_dispatch_notification' ) ) {
		$cat  = sanitize_text_field( (string) ( $data['category'] ?? '' ) );
		$loc  = sanitize_text_field( (string) ( $data['location'] ?? '' ) );
		$msg  = 'New customer ' . ( $cat !== '' ? strtolower( $cat ) : 'message' ) . ( $loc !== '' ? ' for ' . $loc : '' );
		site_pulse_dispatch_notification( 'email_received', 0, $msg, $email_id, 'email' );
	}

	return $email_id;
}


/*--------------------------------------------------------------
# AJAX — list / status / delete
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_emails_list', 'sp_emails_ajax_list' );
function sp_emails_ajax_list(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_emails_can_view( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have access to customer emails.' ] );
	}

	global $wpdb;
	$t = sp_emails_table();

	$status   = sanitize_key( $_POST['status'] ?? 'new' );           // new | handled | all
	$brand    = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
	$location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
	$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
	$search   = trim( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ) );

	$where = []; $args = [];
	if ( $status === 'new' || $status === 'handled' ) { $where[] = 'status = %s'; $args[] = $status; }
	if ( $brand !== '' )    { $where[] = 'brand = %s';    $args[] = $brand; }
	if ( $location !== '' ) { $where[] = 'location = %s'; $args[] = $location; }
	if ( $category !== '' ) { $where[] = 'category = %s'; $args[] = $category; }
	if ( $search !== '' ) {
		$like    = '%' . $wpdb->esc_like( $search ) . '%';
		$where[] = '(customer_name LIKE %s OR email LIKE %s OR phone LIKE %s OR message LIKE %s)';
		array_push( $args, $like, $like, $like, $like );
	}

	$sql = "SELECT * FROM $t";
	if ( $where ) $sql .= ' WHERE ' . implode( ' AND ', $where );
	$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 500';
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

	$out = [];
	foreach ( $rows ?: [] as $r ) $out[] = sp_emails_format_row( $r );

	wp_send_json_success( [
		'emails'        => $out,
		'can_manage'    => sp_emails_can_manage( $me ),
		'new_count'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'new'" ),
		'handled_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'handled'" ),
		'brands'        => $wpdb->get_col( "SELECT DISTINCT brand FROM $t WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand" ) ?: [],
		'locations'     => $wpdb->get_col( "SELECT DISTINCT location FROM $t WHERE location IS NOT NULL AND location <> '' ORDER BY location" ) ?: [],
		'categories'    => $wpdb->get_col( "SELECT DISTINCT category FROM $t WHERE category IS NOT NULL AND category <> '' ORDER BY category" ) ?: [],
	] );
}

add_action( 'wp_ajax_site_pulse_emails_set_status', 'sp_emails_ajax_set_status' );
function sp_emails_ajax_set_status(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_emails_can_manage( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to manage customer emails.' ] );
	}
	$id     = (int) ( $_POST['id'] ?? 0 );
	$status = ( ( $_POST['status'] ?? 'new' ) === 'handled' ) ? 'handled' : 'new';
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Missing email.' ] );

	global $wpdb;
	$wpdb->update(
		sp_emails_table(),
		[
			'status'      => $status,
			'handled_by'  => 'handled' === $status ? $me : 0,
			'handled_at'  => 'handled' === $status ? current_time( 'mysql' ) : null,
		],
		[ 'id' => $id ]
	);
	wp_send_json_success( [ 'id' => $id, 'status' => $status ] );
}

add_action( 'wp_ajax_site_pulse_emails_delete', 'sp_emails_ajax_delete' );
function sp_emails_ajax_delete(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! sp_emails_can_manage( $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to manage customer emails.' ] );
	}
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Missing email.' ] );

	global $wpdb;
	$wpdb->delete( sp_emails_table(), [ 'id' => $id ] );
	wp_send_json_success( [ 'id' => $id ] );
}


/*--------------------------------------------------------------
# Migrations (caps seed)
--------------------------------------------------------------*/

/**
 * One-time: grant the customer-email caps to the tiers that should have it out of the box —
 * owner/admin get view + manage, supervisor gets view. Insert-only per role, self-guarded by an
 * option flag. God always sees it regardless. Mirrors the other module cap seeds.
 */
function sp_emails_migrate_caps(): void {
	if ( get_option( 'site_pulse_emails_caps_seeded' ) ) return;
	global $wpdb;

	$grants = [
		'owner'      => [ 'view_emails', 'manage_emails' ],
		'admin'      => [ 'view_emails', 'manage_emails' ],
		'supervisor' => [ 'view_emails' ],
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
	update_option( 'site_pulse_emails_caps_seeded', '1' );
}
