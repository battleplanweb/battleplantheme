<?php
/* Battle Plan Web Design — Site Pulse: Customer Surveys Module

/*--------------------------------------------------------------
Customer satisfaction surveys collected on the public restaurant websites and
forwarded — cross-site, HMAC-signed — into MyRovin, where they appear as a
"Surveys" tab under Reviews (per-location ratings, comments, summaries).

Pipeline:
  Restaurant site  bp_form_after_send (form_id ending '-survey')
      → HMAC-sign the structured fields with the shared secret
      → POST to  THIS site /wp-json/site-pulse/v1/survey
  MyRovin (here)   verify HMAC → insert a wp_site_pulse_surveys row
      → Surveys panel reads it via site_pulse_get_surveys (view_surveys cap)

The sender side lives in functions-rovin.php (bp_rovin_forward_survey). Both
sides share ONE secret: get_site_option('bp_rovin_survey_secret'), set
identically on MyRovin and every restaurant site. Ingestion is gated only by
that secret (not the module toggle) so turning the display off never drops
incoming submissions; the `surveys` module gates VIEWING.
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Survey dimensions — the canonical rating questions
--------------------------------------------------------------*/

/**
 * Canonical dimension key => display label. The sender maps each `user-<key>`
 * radio to one of these keys; the panel renders whatever keys arrive (a brand
 * with different questions just stores different keys — unknown keys fall back
 * to a title-cased label in the UI). Order here is the display order.
 */
function sp_survey_dimensions(): array {
	return [
		'speed'        => 'Speed of Service',
		'friendliness' => 'Friendliness',
		'temperature'  => 'Food Temperature',
		'flavor'       => 'Food Flavor',
		'portion'      => 'Size of Portion',
		'cleanliness'  => 'Cleanliness',
		'atmosphere'   => 'Atmosphere',
		'convenience'  => 'Convenience',
		'value'        => 'Value for the Money',
		'impression'   => 'Overall Impression',
	];
}


/*--------------------------------------------------------------
# REST receiver — POST /wp-json/site-pulse/v1/survey
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'site-pulse/v1', '/survey', [
		'methods'             => 'POST',
		'callback'            => 'sp_survey_rest_receive',
		'permission_callback' => '__return_true', // auth is the HMAC check inside the callback
	] );
} );

/**
 * Verify the shared-secret HMAC over the raw request body, then store the survey.
 * Contract (matches bp_rovin_forward_survey): the sender signs the EXACT bytes it
 * posts — sig = hash_hmac('sha256', rawBody, secret) — sent as `Authorization: Bearer <sig>`.
 */
function sp_survey_rest_receive( WP_REST_Request $request ) {
	$secret = get_site_option( 'bp_rovin_survey_secret' );
	if ( empty( $secret ) ) {
		return new WP_Error( 'sp_survey_unconfigured', 'Survey receiver not configured.', [ 'status' => 503 ] );
	}

	$raw    = $request->get_body();
	$header = (string) $request->get_header( 'authorization' );
	$given  = preg_replace( '/^\s*Bearer\s+/i', '', $header );
	$expect = hash_hmac( 'sha256', $raw, $secret );

	if ( ! $given || ! hash_equals( $expect, $given ) ) {
		return new WP_Error( 'sp_survey_bad_sig', 'Signature mismatch.', [ 'status' => 403 ] );
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'sp_survey_bad_body', 'Malformed payload.', [ 'status' => 400 ] );
	}

	$id = sp_survey_store( $data );
	if ( ! $id ) {
		return new WP_Error( 'sp_survey_store_failed', 'Could not store survey.', [ 'status' => 500 ] );
	}

	return new WP_REST_Response( [ 'ok' => true, 'id' => $id ], 200 );
}

/**
 * Insert one survey row from a decoded payload. Returns the new id or 0.
 * Ratings arrive as a { key: 1-5 } map; we keep only integer 1-5 values, store
 * them as JSON, and denormalize the average + the "overall" dimension for fast
 * sorting and summary cards.
 */
function sp_survey_store( array $data ): int {
	global $wpdb;

	$ratings = [];
	foreach ( (array) ( $data['ratings'] ?? [] ) as $k => $v ) {
		$key = sanitize_key( $k );
		$n   = (int) $v;
		if ( $key !== '' && $n >= 1 && $n <= 5 ) $ratings[ $key ] = $n;
	}

	$avg = $ratings ? round( array_sum( $ratings ) / count( $ratings ), 2 ) : null;

	$visit = '';
	if ( ! empty( $data['visit_date'] ) ) {
		$ts = strtotime( (string) $data['visit_date'] );
		if ( $ts ) $visit = gmdate( 'Y-m-d', $ts );
	}

	$ok = $wpdb->insert(
		site_pulse_table( 'surveys' ),
		[
			'location'      => sanitize_text_field( (string) ( $data['location']    ?? '' ) ) ?: null,
			'source_site'   => sanitize_text_field( (string) ( $data['site']        ?? '' ) ) ?: null,
			'customer_name' => sanitize_text_field( (string) ( $data['name']        ?? '' ) ) ?: null,
			'email'         => sanitize_email(      (string) ( $data['email']       ?? '' ) ) ?: null,
			'phone'         => sanitize_text_field( (string) ( $data['phone']       ?? '' ) ) ?: null,
			'address'       => sanitize_text_field( (string) ( $data['address']     ?? '' ) ) ?: null,
			'city'          => sanitize_text_field( (string) ( $data['city']        ?? '' ) ) ?: null,
			'state'         => sanitize_text_field( (string) ( $data['state']       ?? '' ) ) ?: null,
			'zip'           => sanitize_text_field( (string) ( $data['zip']         ?? '' ) ) ?: null,
			'experience'    => sanitize_text_field( (string) ( $data['experience']  ?? '' ) ) ?: null,
			'visit_date'    => $visit ?: null,
			'referral'      => sanitize_text_field( (string) ( $data['referral']    ?? '' ) ) ?: null,
			'ratings'       => $ratings ? wp_json_encode( $ratings ) : null,
			'avg_rating'    => $avg,
			'overall'       => isset( $ratings['impression'] ) ? $ratings['impression'] : null,
			'comments'      => sanitize_textarea_field( (string) ( $data['comments'] ?? '' ) ) ?: null,
			'source_ip'     => sanitize_text_field( (string) ( $data['ip']          ?? '' ) ) ?: null,
			'created_at'    => current_time( 'mysql' ),
		],
		[ '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%d','%s','%s','%s' ]
	);

	if ( ! $ok ) return 0;
	$survey_id = (int) $wpdb->insert_id;

	// Notify per the Notifications matrix. Subject = the store's GM (so the contextual GM/Supervisor
	// columns target the right store's people); falls back to 0 when the store can't be resolved.
	$loc_name = sanitize_text_field( (string) ( $data['location'] ?? '' ) );
	$subject  = sp_survey_subject_user( $loc_name );
	$msg      = 'New customer survey' . ( $loc_name !== '' ? ' for ' . $loc_name : '' )
		. ( $avg !== null ? sprintf( ' (avg %.1f stars)', $avg ) : '' );
	if ( function_exists( 'site_pulse_dispatch_notification' ) ) {
		site_pulse_dispatch_notification( 'survey_received', $subject, $msg, $survey_id, 'survey' );
	}

	return $survey_id;
}

// Best-effort: the active GM (manager role) assigned to the store named in a survey, for routing the
// "new survey" notification's contextual GM/Supervisor columns. 0 if the store/GM can't be matched.
function sp_survey_subject_user( string $location_name ): int {
	if ( $location_name === '' ) return 0;
	global $wpdb;
	$loc_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . site_pulse_table( 'locations' ) . " WHERE name = %s LIMIT 1",
		$location_name
	) );
	if ( ! $loc_id ) return 0;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT up.user_id FROM " . site_pulse_table( 'user_profiles' ) . " up
		 INNER JOIN " . site_pulse_table( 'roles' ) . " r ON r.id = up.role_id
		 WHERE up.status = 'active' AND up.location_id = %d AND r.slug = 'manager' LIMIT 1",
		$loc_id
	) );
}


/*--------------------------------------------------------------
# AJAX — read surveys (view_surveys)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_surveys', 'site_pulse_ajax_get_surveys' );
function site_pulse_ajax_get_surveys(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	$can_view = site_pulse_user_can( $user_id, 'view_surveys' )
		|| site_pulse_is_god( get_current_user_id() );
	if ( ! $can_view ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	global $wpdb;
	$table = site_pulse_table( 'surveys' );

	// Active list by default; pass archived=1 to view the ones THIS user archived. Archiving is
	// per-user (wp_site_pulse_survey_archives), so it never hides a survey from anyone else.
	$archived = ! empty( $_POST['archived'] ) ? 1 : 0;
	$arch_tbl = site_pulse_table( 'survey_archives' );

	$where  = [ $archived
		? "id IN ( SELECT survey_id FROM $arch_tbl WHERE user_id = %d )"
		: "id NOT IN ( SELECT survey_id FROM $arch_tbl WHERE user_id = %d )" ];
	$params = [ $user_id ];

	$location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
	if ( $location !== '' ) { $where[] = 'location = %s'; $params[] = $location; }

	$start = sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) );
	$end   = sanitize_text_field( wp_unslash( $_POST['end']   ?? '' ) );
	if ( $start !== '' ) { $where[] = 'created_at >= %s'; $params[] = $start . ' 00:00:00'; }
	if ( $end   !== '' ) { $where[] = 'created_at <= %s'; $params[] = $end   . ' 23:59:59'; }

	// Free-text lookup by customer name / phone / address (matches the whole list, not just the loaded page).
	$search = trim( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ) );
	if ( $search !== '' ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$where[] = '( customer_name LIKE %s OR phone LIKE %s OR address LIKE %s OR city LIKE %s OR state LIKE %s OR email LIKE %s )';
		array_push( $params, $like, $like, $like, $like, $like, $like );
	}

	$where_sql = implode( ' AND ', $where );
	$sql       = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT 1000";
	$rows      = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	$rows      = $rows ?: [];

	// Distinct locations present (for the filter dropdown) — across the whole table, not the filtered set.
	$locations = $wpdb->get_col( "SELECT DISTINCT location FROM $table WHERE location IS NOT NULL AND location <> '' ORDER BY location" ) ?: [];

	// Summary over the filtered set: count, average overall impression, and per-dimension averages.
	$dims      = sp_survey_dimensions();
	$dim_sums  = [];
	$dim_count = [];
	$dim_dist  = [];                                  // key => [1=>n .. 5=>n] — the Amazon-style histogram
	$overall_sum = 0; $overall_n = 0;
	$overall_dist = [ 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ];
	$surveys   = [];

	foreach ( $rows as $r ) {
		$ratings = json_decode( (string) $r['ratings'], true );
		$ratings = is_array( $ratings ) ? $ratings : [];
		foreach ( $ratings as $k => $v ) {
			$v = (int) $v;
			$dim_sums[ $k ]  = ( $dim_sums[ $k ]  ?? 0 ) + $v;
			$dim_count[ $k ] = ( $dim_count[ $k ] ?? 0 ) + 1;
			if ( $v >= 1 && $v <= 5 ) {
				if ( ! isset( $dim_dist[ $k ] ) ) $dim_dist[ $k ] = [ 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ];
				$dim_dist[ $k ][ $v ]++;
			}
		}
		if ( $r['overall'] !== null && $r['overall'] !== '' ) {
			$ov = (int) $r['overall'];
			$overall_sum += $ov; $overall_n++;
			if ( $ov >= 1 && $ov <= 5 ) $overall_dist[ $ov ]++;
		}

		$surveys[] = [
			'id'          => (int) $r['id'],
			'location'    => $r['location'],
			'name'        => $r['customer_name'],
			'email'       => $r['email'],
			'phone'       => $r['phone'],
			'city'        => $r['city'],
			'state'       => $r['state'],
			'address'     => $r['address'],
			'experience'  => $r['experience'],
			'visit_date'  => $r['visit_date'],
			'referral'    => $r['referral'],
			'ratings'     => (object) $ratings,
			'avg_rating'  => $r['avg_rating'] !== null ? (float) $r['avg_rating'] : null,
			'overall'     => $r['overall'] !== null ? (int) $r['overall'] : null,
			'comments'    => $r['comments'],
			'created_at'  => $r['created_at'],
		];
	}

	$dim_avgs = [];
	foreach ( $dim_sums as $k => $sum ) {
		$dim_avgs[ $k ] = round( $sum / max( 1, $dim_count[ $k ] ), 2 );
	}

	wp_send_json_success( [
		'surveys'    => $surveys,
		'locations'  => array_values( $locations ),
		'dimensions' => $dims, // key => label, canonical display order
		'summary'    => [
			'count'             => count( $surveys ),
			'avg_overall'       => $overall_n ? round( $overall_sum / $overall_n, 2 ) : null,
			'dim_averages'      => (object) $dim_avgs,
			'overall_dist'      => (object) $overall_dist,
			'dim_distributions' => (object) array_map( fn( $d ) => (object) $d, $dim_dist ),
		],
		'can_manage'     => site_pulse_user_can( $user_id, 'manage_surveys' ) || site_pulse_is_god( get_current_user_id() ),
		'is_god'         => site_pulse_is_god( get_current_user_id() ), // delete is god-only — gate the button on this
		'archived'       => $archived,                                  // which list this is (0 active, 1 archived)
		'archived_count' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $arch_tbl a JOIN $table s ON s.id = a.survey_id WHERE a.user_id = %d", $user_id ) ),
	] );
}


/*--------------------------------------------------------------
# AJAX — archive / restore a survey (view_surveys — available to everyone who can see them)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_archive_survey', 'site_pulse_ajax_archive_survey' );
function site_pulse_ajax_archive_survey(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	$can = site_pulse_user_can( $user_id, 'view_surveys' )
		|| site_pulse_is_god( get_current_user_id() );
	if ( ! $can ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	$id       = (int) ( $_POST['id'] ?? 0 );
	$archived = ! empty( $_POST['archived'] ) ? 1 : 0;
	if ( $id <= 0 ) wp_send_json_error( [ 'message' => 'Missing survey id.' ] );

	global $wpdb;
	$arch_tbl = site_pulse_table( 'survey_archives' );

	if ( $archived ) {
		// Archive for THIS user only — idempotent (the unique key guards duplicates).
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $arch_tbl WHERE user_id = %d AND survey_id = %d", $user_id, $id ) );
		if ( ! $exists ) {
			$wpdb->insert( $arch_tbl, [ 'user_id' => $user_id, 'survey_id' => $id, 'created_at' => current_time( 'mysql' ) ], [ '%d', '%d', '%s' ] );
		}
	} else {
		$wpdb->delete( $arch_tbl, [ 'user_id' => $user_id, 'survey_id' => $id ], [ '%d', '%d' ] );
	}

	site_pulse_log( $archived ? 'survey_archive' : 'survey_restore', ( $archived ? 'Archived' : 'Restored' ) . ' a customer survey (personal)', [ 'survey_id' => $id ] );
	wp_send_json_success( [ 'id' => $id, 'archived' => $archived ] );
}


/*--------------------------------------------------------------
# AJAX — delete a survey (god-only)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_delete_survey', 'site_pulse_ajax_delete_survey' );
function site_pulse_ajax_delete_survey(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	// Delete is god-only (battleplanweb), even for users who hold manage_surveys.
	if ( ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$id = (int) ( $_POST['id'] ?? 0 );
	if ( $id <= 0 ) wp_send_json_error( [ 'message' => 'Missing survey id.' ] );

	global $wpdb;
	$wpdb->delete( site_pulse_table( 'surveys' ), [ 'id' => $id ], [ '%d' ] );
	$wpdb->delete( site_pulse_table( 'survey_archives' ), [ 'survey_id' => $id ], [ '%d' ] ); // drop everyone's archive rows for it

	site_pulse_log( 'survey_delete', 'Deleted a customer survey', [ 'survey_id' => $id ] );
	wp_send_json_success( [ 'id' => $id ] );
}


/*--------------------------------------------------------------
# AJAX — survey analytics: per-location rating distributions (view_analytics / view_surveys)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_survey_analytics', 'site_pulse_ajax_get_survey_analytics' );
function site_pulse_ajax_get_survey_analytics(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	$can = site_pulse_user_can( $user_id, 'view_analytics' )
		|| site_pulse_user_can( $user_id, 'view_surveys' )
		|| site_pulse_is_god( get_current_user_id() );
	if ( ! $can ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	global $wpdb;
	$table = site_pulse_table( 'surveys' );
	// All surveys — analytics is an aggregate, unaffected by anyone's personal archiving.
	$rows  = $wpdb->get_results( "SELECT location, ratings FROM $table", ARRAY_A ) ?: [];

	// location => [ count, dists{ dim => [1..5] } ]
	$by_loc = [];
	foreach ( $rows as $r ) {
		$loc = ( $r['location'] !== null && $r['location'] !== '' ) ? $r['location'] : '(Unspecified)';
		if ( ! isset( $by_loc[ $loc ] ) ) $by_loc[ $loc ] = [ 'count' => 0, 'dists' => [] ];
		$by_loc[ $loc ]['count']++;

		$ratings = json_decode( (string) $r['ratings'], true );
		if ( ! is_array( $ratings ) ) continue;
		foreach ( $ratings as $k => $v ) {
			$v = (int) $v;
			if ( $v < 1 || $v > 5 ) continue;
			if ( ! isset( $by_loc[ $loc ]['dists'][ $k ] ) ) $by_loc[ $loc ]['dists'][ $k ] = [ 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ];
			$by_loc[ $loc ]['dists'][ $k ][ $v ]++;
		}
	}

	$locations = [];
	foreach ( $by_loc as $loc => $info ) {
		$dists = [];
		foreach ( $info['dists'] as $k => $d ) $dists[ $k ] = (object) $d;
		$locations[] = [ 'location' => $loc, 'count' => $info['count'], 'dists' => (object) $dists ];
	}
	usort( $locations, fn( $a, $b ) => strcmp( $a['location'], $b['location'] ) );

	wp_send_json_success( [
		'dimensions' => sp_survey_dimensions(), // key => label, for the question dropdown
		'locations'  => $locations,
	] );
}
