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

// Routed sections (emails addressed to a specific person). A viewer sees only what's addressed to them;
// a manager sees the whole section and can act on any of it.
function sp_emails_can_view_routed( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id )
		|| site_pulse_user_can( $user_id, 'view_routed_emails' )
		|| site_pulse_user_can( $user_id, 'manage_routed_emails' );
}
function sp_emails_can_manage_routed( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_routed_emails' );
}

// Can $user_id act on (mark handled / delete) THIS specific email row? A managed feedback email needs
// manage_emails; a routed email needs manage_routed_emails, or routed-view when it's addressed to them.
function sp_emails_can_act_on_row( array $row, int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	$stream = (string) ( $row['stream'] ?? '' );
	if ( '' === $stream ) return sp_emails_can_manage( $user_id );
	if ( sp_emails_can_manage_routed( $user_id ) ) return true;
	if ( ! sp_emails_can_view_routed( $user_id ) ) return false;
	$wpu = get_userdata( $user_id );
	return $wpu && strtolower( (string) $wpu->user_email ) === strtolower( (string) ( $row['to_email'] ?? '' ) );
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
		'stream'        => (string) ( $r['stream'] ?? '' ),
		'to_email'      => (string) ( $r['to_email'] ?? '' ),
		'subject'       => (string) ( $r['subject'] ?? '' ),
		'status'        => (string) ( $r['status'] ?? 'new' ),
		'created_at'    => (string) ( $r['created_at'] ?? '' ),
	];
}

// One-time: routing columns for the multi-stream "routed email" retention — a `stream` (which section),
// the `to_email` it was addressed to (per-recipient visibility), and a `subject`. dbDelta can be
// unreliable adding columns, so add them explicitly. Idempotent, option-guarded.
add_action( 'init', 'sp_emails_migrate_routing_cols' );
function sp_emails_migrate_routing_cols(): void {
	if ( get_option( 'sp_emails_routing_cols' ) ) return;
	global $wpdb;
	$t = sp_emails_table();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$t` LIKE %s", 'stream' ) ) )   $wpdb->query( "ALTER TABLE `$t` ADD COLUMN stream varchar(50) DEFAULT NULL, ADD KEY stream (stream)" );
		if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$t` LIKE %s", 'to_email' ) ) ) $wpdb->query( "ALTER TABLE `$t` ADD COLUMN to_email varchar(190) DEFAULT NULL, ADD KEY to_email (to_email)" );
		if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$t` LIKE %s", 'subject' ) ) )  $wpdb->query( "ALTER TABLE `$t` ADD COLUMN subject varchar(255) DEFAULT NULL" );
	}
	update_option( 'sp_emails_routing_cols', '1' );
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
			// Routing: a `stream` (which section this belongs to) + the address it was addressed to. Absent
			// on a plain customer-feedback forward, which keeps landing in the default (feedback) section.
			'stream'        => sanitize_key( (string) ( $data['stream'] ?? '' ) ) ?: null,
			'to_email'      => sanitize_email( (string) ( $data['to'] ?? '' ) ) ?: null,
			'subject'       => sanitize_text_field( (string) ( $data['subject'] ?? '' ) ) ?: null,
			'created_at'    => current_time( 'mysql' ),
		],
		[ '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
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
	$me     = site_pulse_effective_user_id();
	$stream = sanitize_key( $_POST['stream'] ?? '' ); // '' = customer-feedback section; else a routed section

	// A routed section uses its own caps + per-recipient scope; the feedback section is unchanged.
	if ( $stream !== '' ) {
		if ( ! sp_emails_can_view_routed( $me ) ) wp_send_json_error( [ 'message' => 'You do not have access to these emails.' ] );
		$manage = sp_emails_can_manage_routed( $me );
	} else {
		if ( ! sp_emails_can_view( $me ) ) wp_send_json_error( [ 'message' => 'You do not have access to customer emails.' ] );
		$manage = sp_emails_can_manage( $me );
	}

	global $wpdb;
	$t = sp_emails_table();

	$status = sanitize_key( $_POST['status'] ?? 'new' );           // new | handled | all
	$search = trim( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ) );

	// Section scope: feedback = untagged rows; routed = this stream's rows, and a NON-manager viewer only
	// sees the ones addressed to their own account email. Reused for the list + the status counts.
	$my_email = '';
	if ( $stream !== '' && ! $manage ) { $wpu = get_userdata( $me ); $my_email = $wpu ? strtolower( (string) $wpu->user_email ) : "\0no-match"; }
	$scope = []; $scope_args = [];
	if ( $stream !== '' ) {
		$scope[] = 'stream = %s'; $scope_args[] = $stream;
		if ( ! $manage ) { $scope[] = 'LOWER(to_email) = %s'; $scope_args[] = $my_email; }
	} else {
		$scope[] = "( stream IS NULL OR stream = '' )";
	}

	$where = $scope; $args = $scope_args;
	if ( $status === 'new' || $status === 'handled' ) { $where[] = 'status = %s'; $args[] = $status; }
	if ( $stream === '' ) {
		$brand    = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
		$location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
		$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		if ( $brand !== '' )    { $where[] = 'brand = %s';    $args[] = $brand; }
		if ( $location !== '' ) { $where[] = 'location = %s'; $args[] = $location; }
		if ( $category !== '' ) { $where[] = 'category = %s'; $args[] = $category; }
	}
	if ( $search !== '' ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		if ( $stream !== '' ) { $where[] = '(subject LIKE %s OR email LIKE %s OR to_email LIKE %s OR customer_name LIKE %s OR message LIKE %s)'; array_push( $args, $like, $like, $like, $like, $like ); }
		else                  { $where[] = '(customer_name LIKE %s OR email LIKE %s OR phone LIKE %s OR message LIKE %s)';                    array_push( $args, $like, $like, $like, $like ); }
	}

	$sql = "SELECT * FROM $t WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT 500';
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

	$out = [];
	foreach ( $rows ?: [] as $r ) $out[] = sp_emails_format_row( $r );

	$scope_sql = 'WHERE ' . implode( ' AND ', $scope );
	$count = static function ( $st ) use ( $wpdb, $t, $scope_sql, $scope_args ) {
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t $scope_sql AND status = %s", array_merge( $scope_args, [ $st ] ) ) );
	};

	$resp = [
		'emails'        => $out,
		'can_manage'    => $manage,
		'new_count'     => $count( 'new' ),
		'handled_count' => $count( 'handled' ),
	];
	if ( $stream === '' ) {
		$resp['brands']     = $wpdb->get_col( "SELECT DISTINCT brand FROM $t WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand" ) ?: [];
		$resp['locations']  = $wpdb->get_col( "SELECT DISTINCT location FROM $t WHERE location IS NOT NULL AND location <> '' ORDER BY location" ) ?: [];
		$resp['categories'] = $wpdb->get_col( "SELECT DISTINCT category FROM $t WHERE category IS NOT NULL AND category <> '' ORDER BY category" ) ?: [];
	}
	wp_send_json_success( $resp );
}

add_action( 'wp_ajax_site_pulse_emails_set_status', 'sp_emails_ajax_set_status' );
function sp_emails_ajax_set_status(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me     = site_pulse_effective_user_id();
	$id     = (int) ( $_POST['id'] ?? 0 );
	$status = ( ( $_POST['status'] ?? 'new' ) === 'handled' ) ? 'handled' : 'new';
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Missing email.' ] );

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, stream, to_email FROM ' . sp_emails_table() . ' WHERE id = %d', $id ), ARRAY_A );
	if ( ! $row || ! sp_emails_can_act_on_row( $row, $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to update this email.' ] );
	}

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
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Missing email.' ] );

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, stream, to_email FROM ' . sp_emails_table() . ' WHERE id = %d', $id ), ARRAY_A );
	if ( ! $row || ! sp_emails_can_act_on_row( $row, $me ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to delete this email.' ] );
	}

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
