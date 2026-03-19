<?php
/* Battle Plan Web Design - CCSO Overtime Scheduling Includes

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Constants & Setup
# Database Schema
# Asset Enqueueing
# User Roles & Capabilities
# User Meta Helpers
# Schedule Query Helpers
# Claims Engine
# Admin Functions
# Email Notifications
# Cron — Rolling Window
# AJAX Handlers
# Body Class & Auth Guard
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Constants & Setup
--------------------------------------------------------------*/

define( 'SCHEDULES_DB_VERSION', '1.1' );

/**
 * Returns a fully-qualified table name for the schedules module.
 * Using a function avoids the $wpdb-at-define-time issue.
 */
function schedules_table( string $name ): string {
	return $GLOBALS['wpdb']->prefix . 'schedules_' . $name;
}


/*--------------------------------------------------------------
# Database Schema
--------------------------------------------------------------*/

/**
 * Creates / upgrades all schedules tables using dbDelta.
 */
function schedules_install_db(): void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE " . schedules_table('shifts') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		shift_letter char(1) NOT NULL,
		start_hour tinyint(2) NOT NULL DEFAULT 6,
		work_days varchar(20) NOT NULL,
		floor_count tinyint(2) NOT NULL DEFAULT 0,
		max_capacity tinyint(2) NOT NULL DEFAULT 14,
		PRIMARY KEY  (id),
		UNIQUE KEY shift_letter (shift_letter)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('days') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		shift_letter char(1) NOT NULL,
		schedule_date date NOT NULL,
		floor_count tinyint(2) NOT NULL DEFAULT 0,
		max_capacity tinyint(2) NOT NULL DEFAULT 14,
		is_archived tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY shift_date (shift_letter, schedule_date)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('blocks') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		day_id int(11) NOT NULL,
		block_index tinyint(1) NOT NULL,
		start_hour tinyint(2) NOT NULL,
		end_hour tinyint(2) NOT NULL,
		discipline_filter varchar(50) DEFAULT NULL,
		PRIMARY KEY  (id)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('claims') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		block_id int(11) NOT NULL,
		user_id int(11) NOT NULL,
		claimed_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY block_user (block_id, user_id)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('adjustments') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		day_id int(11) NOT NULL,
		added_by int(11) NOT NULL,
		adjustment tinyint(2) NOT NULL DEFAULT 1,
		reason varchar(255) DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $charset;";

	dbDelta( $sql );

	schedules_seed_shifts();
}

/**
 * Seeds the 4 default shift rows if the shifts table is empty.
 */
function schedules_seed_shifts(): void {
	global $wpdb;

	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . schedules_table('shifts') );
	if ( $count > 0 ) return;

	$shifts = [
		[ 'shift_letter' => 'A', 'start_hour' => 6,  'work_days' => '0,1,2,3' ],   // Sun–Wed day
		[ 'shift_letter' => 'B', 'start_hour' => 6,  'work_days' => '3,4,5,6' ],   // Wed–Sat day
		[ 'shift_letter' => 'C', 'start_hour' => 18, 'work_days' => '0,1,2,3' ],   // Sun–Wed night
		[ 'shift_letter' => 'D', 'start_hour' => 18, 'work_days' => '3,4,5,6' ],   // Wed–Sat night
	];

	foreach ( $shifts as $shift ) {
		$wpdb->insert(
			schedules_table('shifts'),
			[
				'shift_letter' => $shift['shift_letter'],
				'start_hour'   => $shift['start_hour'],
				'work_days'    => $shift['work_days'],
				'floor_count'  => 0,
				'max_capacity' => 14,
			],
			[ '%s', '%d', '%s', '%d', '%d' ]
		);
	}
}

/**
 * Returns an array of 3 block hour pairs for a given shift start hour (normal day).
 */
function schedules_get_block_hours( int $start_hour ): array {
	$blocks  = [];
	$current = $start_hour;
	for ( $i = 0; $i < 3; $i++ ) {
		$end      = ( $current + 4 ) % 24;
		$blocks[] = [ 'start' => $current, 'end' => $end ];
		$current  = $end;
	}
	return $blocks;
}

/**
 * Returns 2×3-hour block pairs for a Wednesday half-shift.
 * A: 06–12, B: 12–18, C: 18–00, D: 00–06
 */
function schedules_get_wednesday_block_hours( string $shift_letter ): array {
	$map = [
		'A' => [ [ 'start' => 6,  'end' => 9  ], [ 'start' => 9,  'end' => 12 ] ],
		'B' => [ [ 'start' => 12, 'end' => 15 ], [ 'start' => 15, 'end' => 18 ] ],
		'C' => [ [ 'start' => 18, 'end' => 21 ], [ 'start' => 21, 'end' => 0  ] ],
		'D' => [ [ 'start' => 0,  'end' => 3  ], [ 'start' => 3,  'end' => 6  ] ],
	];
	return $map[ $shift_letter ] ?? [];
}

/**
 * Generates a schedules_days row + 3 schedules_blocks rows for a given date,
 * for each shift whose work_days includes that date's day-of-week.
 */
function schedules_generate_day( string $date ): void {
	global $wpdb;

	$day_of_week = (int) date( 'w', strtotime( $date ) ); // 0=Sun...6=Sat

	$shifts = $wpdb->get_results( "SELECT * FROM " . schedules_table('shifts'), ARRAY_A );

	foreach ( $shifts as $shift ) {
		$work_days = array_map( 'intval', explode( ',', $shift['work_days'] ) );

		if ( ! in_array( $day_of_week, $work_days, true ) ) continue;

		// Insert day row if not already existing
		$existing_day_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM " . schedules_table('days') . " WHERE shift_letter = %s AND schedule_date = %s",
				$shift['shift_letter'],
				$date
			)
		);

		if ( $existing_day_id ) continue;

		$wpdb->insert(
			schedules_table('days'),
			[
				'shift_letter'  => $shift['shift_letter'],
				'schedule_date' => $date,
				'floor_count'   => (int) $shift['floor_count'],
				'max_capacity'  => (int) $shift['max_capacity'],
				'is_archived'   => 0,
				'created_at'    => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%d', '%d', '%d', '%s' ]
		);

		$day_id      = $wpdb->insert_id;
		$block_hours = ( $day_of_week === 3 )
			? schedules_get_wednesday_block_hours( $shift['shift_letter'] )
			: schedules_get_block_hours( (int) $shift['start_hour'] );

		foreach ( $block_hours as $index => $hours ) {
			$wpdb->insert(
				schedules_table('blocks'),
				[
					'day_id'      => $day_id,
					'block_index' => $index,
					'start_hour'  => $hours['start'],
					'end_hour'    => $hours['end'],
				],
				[ '%d', '%d', '%d', '%d' ]
			);
		}
	}
}

/**
 * Seeds the initial 28-day window. Only runs when schedules_days table is empty.
 */
function schedules_generate_initial_window(): void {
	global $wpdb;

	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . schedules_table('days') );
	if ( $count > 0 ) return;

	for ( $i = 1; $i <= 28; $i++ ) {
		schedules_generate_day( date( 'Y-m-d', strtotime( "+{$i} days" ) ) );
	}
}

/**
 * Deletes all non-archived Wednesday day rows (and their blocks/claims),
 * then regenerates them with the correct half-shift block structure.
 */
function schedules_migrate_wednesdays(): void {
	global $wpdb;

	$today = date( 'Y-m-d' );

	// Find future, non-archived Wednesday day IDs
	$day_ids = $wpdb->get_col(
		"SELECT id FROM " . schedules_table('days') . "
		 WHERE DAYOFWEEK(schedule_date) = 4
		   AND is_archived = 0
		   AND schedule_date >= '{$today}'"
	);

	if ( ! empty($day_ids) ) {
		$placeholders = implode( ',', array_map('intval', $day_ids) );

		// Delete claims, blocks, then days (in dependency order)
		$block_ids = $wpdb->get_col(
			"SELECT id FROM " . schedules_table('blocks') . " WHERE day_id IN ($placeholders)"
		);
		if ( ! empty($block_ids) ) {
			$bph = implode( ',', array_map('intval', $block_ids) );
			$wpdb->query( "DELETE FROM " . schedules_table('claims') . " WHERE block_id IN ($bph)" );
		}
		$wpdb->query( "DELETE FROM " . schedules_table('blocks') . " WHERE day_id IN ($placeholders)" );
		$wpdb->query( "DELETE FROM " . schedules_table('days')   . " WHERE id IN ($placeholders)" );
	}

	// Regenerate every Wednesday in the next 28 days
	for ( $i = 1; $i <= 28; $i++ ) {
		$date = date( 'Y-m-d', strtotime( "+{$i} days" ) );
		if ( (int) date( 'w', strtotime($date) ) === 3 ) {
			schedules_generate_day( $date );
		}
	}
}

// Run DB install on init if version doesn't match
add_action( 'init', function() {
	$stored = get_option( 'schedules_db_version' );
	if ( $stored !== SCHEDULES_DB_VERSION ) {
		schedules_install_db();
		if ( ! $stored ) {
			// Fresh install — seed full window
			schedules_generate_initial_window();
		} else {
			// 1.0 → 1.1: rebuild Wednesday records with correct half-shift blocks
			schedules_migrate_wednesdays();
		}
		update_option( 'schedules_db_version', SCHEDULES_DB_VERSION );
	}
}, 5 );


/*--------------------------------------------------------------
# Cache Prevention
--------------------------------------------------------------*/

// Prevent WP Engine EverCache (and other caching plugins) from caching
// any schedules page — nonces must be fresh on every request.
add_action( 'template_redirect', function() {
	$schedules_slugs = [ 'schedules-login', 'schedules-overtime', 'schedules-supervisor' ];
	global $post;
	if ( ! $post || ! in_array( $post->post_name, $schedules_slugs, true ) ) return;

	if ( ! defined('DONOTCACHEPAGE') ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();
} );


/*--------------------------------------------------------------
# Asset Enqueueing
--------------------------------------------------------------*/

add_action( 'wp_enqueue_scripts', 'schedules_enqueue_assets' );
function schedules_enqueue_assets(): void {
	$schedules_slugs = [ 'schedules-login', 'schedules-overtime', 'schedules-supervisor' ];

	global $post;
	$on_schedules_page = $post && in_array( $post->post_name, $schedules_slugs, true );

	if ( ! $on_schedules_page ) return;

	wp_enqueue_style(
		'schedules-css',
		get_template_directory_uri() . '/style-schedules.css',
		[],
		defined('_BP_VERSION') ? _BP_VERSION : '1.0'
	);

	if ( function_exists('bp_enqueue_script') ) {
		bp_enqueue_script( 'schedules-script', 'script-schedules', [] );
	} else {
		wp_enqueue_script(
			'schedules-script',
			get_template_directory_uri() . '/js/script-schedules.js',
			[],
			defined('_BP_VERSION') ? _BP_VERSION : '1.0',
			true
		);
	}

	$user_id       = defined('_USER_ID') ? _USER_ID : get_current_user_id();
	$user          = get_userdata( $user_id );
	$is_supervisor = $user && $user->has_cap('manage_schedules') ? 'true' : 'false';

	wp_localize_script( 'schedules-script', 'schedulesData', [
		'ajaxUrl'       => admin_url('admin-ajax.php'),
		'nonce'         => wp_create_nonce('schedules_nonce'),
		'userId'        => $user_id,
		'userShift'     => schedules_get_user_shift( $user_id ),
		'userPriority'  => schedules_get_user_priority( $user_id ),
		'userFirstName' => $user ? $user->first_name : '',
		'userLastName'  => $user ? $user->last_name : '',
		'isSupervisor'  => $is_supervisor,
	] );
}


/*--------------------------------------------------------------
# User Roles & Capabilities
--------------------------------------------------------------*/

function schedules_register_roles(): void {
	add_role( 'schedules_member', 'Schedules Member', [
		'read' => true,
	] );

	add_role( 'schedules_supervisor', 'Schedules Supervisor', [
		'read'             => true,
		'manage_schedules' => true,
	] );
}

function schedules_remove_roles(): void {
	remove_role( 'schedules_member' );
	remove_role( 'schedules_supervisor' );
}

add_action( 'admin_init', function() {
	schedules_remove_roles();
	schedules_register_roles();
} );


/*--------------------------------------------------------------
# User Meta Helpers
--------------------------------------------------------------*/

function schedules_get_user_shift( int $user_id ): string {
	return (string) get_user_meta( $user_id, 'schedules_shift', true );
}

function schedules_set_user_shift( int $user_id, string $shift ): void {
	update_user_meta( $user_id, 'schedules_shift', sanitize_text_field( $shift ) );
}

function schedules_get_user_priority( int $user_id ): string {
	return (string) get_user_meta( $user_id, 'schedules_priority', true );
}

function schedules_set_user_priority( int $user_id, string $priority ): void {
	update_user_meta( $user_id, 'schedules_priority', sanitize_text_field( $priority ) );
}

function schedules_get_user_discipline( int $user_id ): string {
	return (string) get_user_meta( $user_id, 'schedules_discipline', true );
}

function schedules_set_user_discipline( int $user_id, string $discipline ): void {
	update_user_meta( $user_id, 'schedules_discipline', sanitize_text_field( $discipline ) );
}

function schedules_get_user_badge( int $user_id ): string {
	$user = get_userdata( $user_id );
	return $user ? (string) $user->user_login : '';
}

/**
 * Returns the number of days ahead a user with given priority can see.
 */
function schedules_get_max_days( string $priority ): int {
	switch ( $priority ) {
		case 'floor':  return 28;
		case 'green':  return 21;
		case 'yellow': return 14;
		case 'red':    return 7;
		default:       return 0;
	}
}

/**
 * Returns true if the user's priority window includes the given date.
 */
function schedules_user_can_see_date( int $user_id, string $date ): bool {
	$priority = schedules_get_user_priority( $user_id );
	$max_days = schedules_get_max_days( $priority );
	if ( $max_days === 0 ) return false;

	$today      = strtotime( date('Y-m-d') );
	$target     = strtotime( $date );
	$diff_days  = (int) floor( ( $target - $today ) / DAY_IN_SECONDS );

	return $diff_days >= 1 && $diff_days <= $max_days;
}


/*--------------------------------------------------------------
# Schedule Query Helpers
--------------------------------------------------------------*/

/**
 * Returns the full structured calendar data for a given user.
 */
function schedules_get_calendar_data( int $user_id ): array {
	global $wpdb;

	$priority = schedules_get_user_priority( $user_id );
	$max_days = schedules_get_max_days( $priority );
	$user_shift = schedules_get_user_shift( $user_id );

	if ( $max_days === 0 ) {
		return [ 'weeks' => [], 'max_week' => 0 ];
	}

	$today      = date('Y-m-d');
	$date_start = date('Y-m-d', strtotime('+1 day') );
	$date_end   = date('Y-m-d', strtotime("+{$max_days} days") );

	// Query all non-archived days within window, excluding user's own shift
	$days_query = $wpdb->prepare(
		"SELECT d.*, s.start_hour FROM " . schedules_table('days') . " d
		 JOIN " . schedules_table('shifts') . " s ON s.shift_letter = d.shift_letter
		 WHERE d.schedule_date BETWEEN %s AND %s
		   AND d.is_archived = 0
		   AND d.shift_letter != %s
		 ORDER BY d.schedule_date ASC, d.shift_letter ASC",
		$date_start,
		$date_end,
		$user_shift
	);
	$days = $wpdb->get_results( $days_query, ARRAY_A );

	if ( empty( $days ) ) {
		return [ 'weeks' => [], 'max_week' => (int) ceil( $max_days / 7 ) ];
	}

	$day_ids = array_column( $days, 'id' );
	$day_ids_placeholder = implode( ',', array_fill( 0, count($day_ids), '%d' ) );

	// Get all blocks for those days with claims_count and user_claimed flag
	$blocks_query = $wpdb->prepare(
		"SELECT b.*,
		        COUNT(c.id) AS claims_count,
		        SUM(CASE WHEN c.user_id = %d THEN 1 ELSE 0 END) AS user_claimed
		 FROM " . schedules_table('blocks') . " b
		 LEFT JOIN " . schedules_table('claims') . " c ON c.block_id = b.id
		 WHERE b.day_id IN ($day_ids_placeholder)
		 GROUP BY b.id
		 ORDER BY b.day_id ASC, b.block_index ASC",
		array_merge( [$user_id], $day_ids )
	);
	$blocks_raw = $wpdb->get_results( $blocks_query, ARRAY_A );

	// Get adjustments per day
	$adj_query = $wpdb->prepare(
		"SELECT day_id, SUM(adjustment) AS total_adjustment
		 FROM " . schedules_table('adjustments') . "
		 WHERE day_id IN ($day_ids_placeholder)
		 GROUP BY day_id",
		$day_ids
	);
	$adjustments_raw = $wpdb->get_results( $adj_query, ARRAY_A );

	$adjustments = [];
	foreach ( $adjustments_raw as $adj ) {
		$adjustments[ (int) $adj['day_id'] ] = (int) $adj['total_adjustment'];
	}

	// Index blocks by day_id
	$blocks_by_day = [];
	foreach ( $blocks_raw as $block ) {
		$blocks_by_day[ (int) $block['day_id'] ][] = $block;
	}

	// Build weeks structure
	$weeks    = [];
	$max_week = (int) ceil( $max_days / 7 );

	foreach ( $days as $day ) {
		$date      = $day['schedule_date'];
		$diff      = (int) floor( ( strtotime($date) - strtotime($today) ) / DAY_IN_SECONDS );
		$week_num  = (int) ceil( $diff / 7 );

		$day_id        = (int) $day['id'];
		$adj           = isset($adjustments[$day_id]) ? $adjustments[$day_id] : 0;
		$floor_count   = (int) $day['floor_count'];
		$max_capacity  = (int) $day['max_capacity'];

		$blocks_data = [];
		if ( isset($blocks_by_day[$day_id]) ) {
			foreach ( $blocks_by_day[$day_id] as $block ) {
				$claims_count = (int) $block['claims_count'];
				$available    = max( 0, $max_capacity - $floor_count + $adj - $claims_count );
				$blocks_data[] = [
					'id'               => (int) $block['id'],
					'block_index'      => (int) $block['block_index'],
					'start_hour'       => (int) $block['start_hour'],
					'end_hour'         => (int) $block['end_hour'],
					'available'        => $available,
					'user_claimed'     => (bool) $block['user_claimed'],
					'discipline_filter'=> $block['discipline_filter'],
				];
			}
		}

		$shift_entry = [
			'id'           => $day_id,
			'shift_letter' => $day['shift_letter'],
			'start_hour'   => (int) $day['start_hour'],
			'blocks'       => $blocks_data,
		];

		$weeks[ $week_num ][ $date ][] = $shift_entry;
	}

	ksort( $weeks );

	return [
		'weeks'    => $weeks,
		'max_week' => $max_week,
	];
}

/**
 * Returns supervisor calendar data (all shifts, all users).
 */
function schedules_get_supervisor_calendar_data(): array {
	global $wpdb;

	$max_days   = 28;
	$today      = date('Y-m-d');
	$date_start = date('Y-m-d', strtotime('+1 day'));
	$date_end   = date('Y-m-d', strtotime("+{$max_days} days"));

	$days = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT d.*, s.start_hour FROM " . schedules_table('days') . " d
			 JOIN " . schedules_table('shifts') . " s ON s.shift_letter = d.shift_letter
			 WHERE d.schedule_date BETWEEN %s AND %s
			   AND d.is_archived = 0
			 ORDER BY d.schedule_date ASC, d.shift_letter ASC",
			$date_start,
			$date_end
		),
		ARRAY_A
	);

	if ( empty($days) ) return [ 'weeks' => [], 'max_week' => 4 ];

	$day_ids = array_column( $days, 'id' );
	$placeholders = implode( ',', array_fill( 0, count($day_ids), '%d' ) );

	$blocks_raw = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT b.*, COUNT(c.id) AS claims_count
			 FROM " . schedules_table('blocks') . " b
			 LEFT JOIN " . schedules_table('claims') . " c ON c.block_id = b.id
			 WHERE b.day_id IN ($placeholders)
			 GROUP BY b.id
			 ORDER BY b.day_id ASC, b.block_index ASC",
			$day_ids
		),
		ARRAY_A
	);

	$adj_raw = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT day_id, SUM(adjustment) AS total_adjustment
			 FROM " . schedules_table('adjustments') . "
			 WHERE day_id IN ($placeholders)
			 GROUP BY day_id",
			$day_ids
		),
		ARRAY_A
	);

	$adjustments = [];
	foreach ( $adj_raw as $a ) {
		$adjustments[ (int)$a['day_id'] ] = (int)$a['total_adjustment'];
	}

	$blocks_by_day = [];
	foreach ( $blocks_raw as $b ) {
		$blocks_by_day[ (int)$b['day_id'] ][] = $b;
	}

	$weeks = [];

	foreach ( $days as $day ) {
		$date    = $day['schedule_date'];
		$diff    = (int) floor( (strtotime($date) - strtotime($today)) / DAY_IN_SECONDS );
		$week_num = (int) ceil( $diff / 7 );

		$day_id       = (int) $day['id'];
		$adj          = isset($adjustments[$day_id]) ? $adjustments[$day_id] : 0;
		$floor_count  = (int) $day['floor_count'];
		$max_capacity = (int) $day['max_capacity'];

		$blocks_data = [];
		if ( isset($blocks_by_day[$day_id]) ) {
			foreach ( $blocks_by_day[$day_id] as $block ) {
				$claims_count  = (int) $block['claims_count'];
				$available     = max( 0, $max_capacity - $floor_count + $adj - $claims_count );
				$blocks_data[] = [
					'id'               => (int) $block['id'],
					'block_index'      => (int) $block['block_index'],
					'start_hour'       => (int) $block['start_hour'],
					'end_hour'         => (int) $block['end_hour'],
					'available'        => $available,
					'claims_count'     => $claims_count,
					'user_claimed'     => false,
					'discipline_filter'=> $block['discipline_filter'],
				];
			}
		}

		$weeks[ $week_num ][ $date ][] = [
			'id'           => $day_id,
			'shift_letter' => $day['shift_letter'],
			'start_hour'   => (int) $day['start_hour'],
			'blocks'       => $blocks_data,
		];
	}

	ksort($weeks);

	return [ 'weeks' => $weeks, 'max_week' => 4 ];
}

/**
 * Formats a block's start/end hours as a human-readable time range.
 * e.g., 6, 10 → "6am–10am"; 22, 2 → "10pm–2am"
 */
function schedules_format_block_time( int $start, int $end ): string {
	$fmt = function( int $h ): string {
		if ( $h === 0 )  return '12am';
		if ( $h === 12 ) return '12pm';
		if ( $h < 12 )  return $h . 'am';
		return ( $h - 12 ) . 'pm';
	};
	return $fmt($start) . '–' . $fmt($end);
}

/**
 * Returns the type label for a shift.
 */
function schedules_shift_type_label( string $shift_letter ): string {
	return in_array( $shift_letter, ['A','B'], true ) ? 'Day' : 'Night';
}


/*--------------------------------------------------------------
# Claims Engine
--------------------------------------------------------------*/

/**
 * Claims a block for a user.
 * Returns ['success' => bool, 'message' => string, 'remaining' => int]
 */
function schedules_claim_block( int $block_id, int $user_id ): array {
	global $wpdb;

	// 1. Block must exist and day must not be archived
	$block = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT b.*, d.is_archived, d.schedule_date, d.shift_letter, d.floor_count, d.max_capacity
			 FROM " . schedules_table('blocks') . " b
			 JOIN " . schedules_table('days') . " d ON d.id = b.day_id
			 WHERE b.id = %d",
			$block_id
		),
		ARRAY_A
	);

	if ( ! $block ) {
		return [ 'success' => false, 'message' => 'Block not found.', 'remaining' => 0 ];
	}

	if ( (int) $block['is_archived'] === 1 ) {
		return [ 'success' => false, 'message' => 'This schedule day is no longer available.', 'remaining' => 0 ];
	}

	// 2. User's priority window must include this date
	if ( ! schedules_user_can_see_date( $user_id, $block['schedule_date'] ) ) {
		return [ 'success' => false, 'message' => 'This date is outside your visibility window.', 'remaining' => 0 ];
	}

	// 3. User must not be on this shift
	$user_shift = schedules_get_user_shift( $user_id );
	if ( $user_shift === $block['shift_letter'] ) {
		return [ 'success' => false, 'message' => 'You cannot claim OT on your own shift.', 'remaining' => 0 ];
	}

	// 4. Discipline filter check
	if ( ! empty($block['discipline_filter']) ) {
		$user_discipline = schedules_get_user_discipline( $user_id );
		if ( $user_discipline !== $block['discipline_filter'] ) {
			return [ 'success' => false, 'message' => 'This block is restricted to a specific discipline.', 'remaining' => 0 ];
		}
	}

	// 5. Check availability
	$day_id = (int) $block['day_id'];

	$adj = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE(SUM(adjustment), 0) FROM " . schedules_table('adjustments') . " WHERE day_id = %d",
			$day_id
		)
	);

	$claims_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM " . schedules_table('claims') . " WHERE block_id = %d",
			$block_id
		)
	);

	$available = (int) $block['max_capacity'] - (int) $block['floor_count'] + $adj - $claims_count;

	if ( $available <= 0 ) {
		return [ 'success' => false, 'message' => 'No slots available for this block.', 'remaining' => 0 ];
	}

	// 6. User must not have already claimed this block
	$already_claimed = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM " . schedules_table('claims') . " WHERE block_id = %d AND user_id = %d",
			$block_id,
			$user_id
		)
	);

	if ( $already_claimed > 0 ) {
		return [ 'success' => false, 'message' => 'You have already claimed this block.', 'remaining' => 0 ];
	}

	// Insert claim
	$wpdb->insert(
		schedules_table('claims'),
		[
			'block_id'   => $block_id,
			'user_id'    => $user_id,
			'claimed_at' => current_time('mysql'),
		],
		[ '%d', '%d', '%s' ]
	);

	$remaining = max( 0, $available - 1 );

	return [
		'success'   => true,
		'message'   => 'Block claimed successfully.',
		'remaining' => $remaining,
	];
}


/*--------------------------------------------------------------
# Admin Functions
--------------------------------------------------------------*/

/**
 * Adds an adjustment (supervisor action) to a day's capacity.
 */
function schedules_add_adjustment( int $day_id, int $supervisor_id, int $adjustment, string $reason ): array {
	global $wpdb;

	$day = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM " . schedules_table('days') . " WHERE id = %d",
			$day_id
		),
		ARRAY_A
	);

	if ( ! $day ) {
		return [ 'success' => false, 'message' => 'Day not found.' ];
	}

	$wpdb->insert(
		schedules_table('adjustments'),
		[
			'day_id'     => $day_id,
			'added_by'   => $supervisor_id,
			'adjustment' => $adjustment,
			'reason'     => sanitize_text_field( $reason ),
			'created_at' => current_time('mysql'),
		],
		[ '%d', '%d', '%d', '%s', '%s' ]
	);

	schedules_notify_new_slot( $day_id );

	return [ 'success' => true, 'message' => 'Adjustment added.' ];
}

/**
 * Returns all claims for a given date, optionally filtered by shift letter.
 */
function schedules_get_claims_for_day( string $date, string $shift_letter = '' ): array {
	global $wpdb;

	$shift_clause = '';
	$args = [ $date ];

	if ( ! empty($shift_letter) ) {
		$shift_clause = " AND d.shift_letter = %s";
		$args[] = $shift_letter;
	}

	$query = $wpdb->prepare(
		"SELECT c.*, u.display_name, u.user_login AS badge_number,
		        b.start_hour, b.end_hour, b.block_index,
		        d.shift_letter, d.schedule_date
		 FROM " . schedules_table('claims') . " c
		 JOIN " . schedules_table('blocks') . " b ON b.id = c.block_id
		 JOIN " . schedules_table('days') . " d ON d.id = b.day_id
		 JOIN {$wpdb->users} u ON u.ID = c.user_id
		 WHERE d.schedule_date = %s" . $shift_clause . "
		 ORDER BY d.shift_letter ASC, b.block_index ASC, c.claimed_at ASC",
		...$args
	);

	$rows = $wpdb->get_results( $query, ARRAY_A );

	$grouped = [];
	foreach ( $rows as $row ) {
		$discipline = get_user_meta( (int) $row['user_id'], 'schedules_discipline', true );
		$row['discipline'] = $discipline ? $discipline : '';
		$row['time_range'] = schedules_format_block_time( (int)$row['start_hour'], (int)$row['end_hour'] );
		$grouped[ $row['shift_letter'] ][] = $row;
	}

	return $grouped;
}

/**
 * Creates a new schedules member.
 */
function schedules_create_member( array $data ): array {
	$first_name  = sanitize_text_field( $data['first_name'] ?? '' );
	$last_name   = sanitize_text_field( $data['last_name'] ?? '' );
	$badge       = sanitize_text_field( $data['badge_number'] ?? '' );
	$email       = sanitize_email( $data['email'] ?? '' );
	$password    = $data['member_pass'] ?? $data['password'] ?? '';
	$shift       = sanitize_text_field( $data['shift'] ?? '' );
	$discipline  = sanitize_text_field( $data['discipline'] ?? '' );
	$priority    = sanitize_text_field( $data['priority'] ?? 'red' );
	$is_supervisor = ! empty( $data['is_supervisor'] );

	if ( empty($badge) || empty($password) || empty($email) ) {
		return [ 'success' => false, 'message' => 'Badge number, password, and email are required.' ];
	}

	if ( username_exists($badge) ) {
		return [ 'success' => false, 'message' => 'A user with this badge number already exists.' ];
	}

	$user_id = wp_create_user( $badge, $password, $email );

	if ( is_wp_error($user_id) ) {
		return [ 'success' => false, 'message' => $user_id->get_error_message() ];
	}

	$user = new WP_User( $user_id );
	$role = $is_supervisor ? 'schedules_supervisor' : 'schedules_member';
	$user->set_role( $role );

	wp_update_user([
		'ID'           => $user_id,
		'first_name'   => $first_name,
		'last_name'    => $last_name,
		'display_name' => trim("{$first_name} {$last_name}"),
	]);

	update_user_meta( $user_id, 'schedules_shift',      $shift );
	update_user_meta( $user_id, 'schedules_discipline', $discipline );
	update_user_meta( $user_id, 'schedules_priority',   $priority );

	return [ 'success' => true, 'message' => 'Member created.', 'user_id' => $user_id ];
}

/**
 * Returns all schedules members and supervisors with their meta.
 */
function schedules_get_all_members(): array {
	$users = get_users([
		'role__in' => [ 'schedules_member', 'schedules_supervisor' ],
		'orderby'  => 'display_name',
		'order'    => 'ASC',
	]);

	$members = [];
	foreach ( $users as $user ) {
		$members[] = [
			'user_id'      => $user->ID,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'badge_number' => $user->user_login,
			'email'        => $user->user_email,
			'role'         => in_array('schedules_supervisor', (array)$user->roles) ? 'supervisor' : 'member',
			'shift'        => get_user_meta( $user->ID, 'schedules_shift',      true ),
			'discipline'   => get_user_meta( $user->ID, 'schedules_discipline', true ),
			'priority'     => get_user_meta( $user->ID, 'schedules_priority',   true ),
		];
	}

	return $members;
}


/*--------------------------------------------------------------
# Email Notifications
--------------------------------------------------------------*/

/**
 * Notifies eligible users when a new OT slot is added.
 */
function schedules_notify_new_slot( int $day_id ): void {
	global $wpdb;

	$day = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM " . schedules_table('days') . " WHERE id = %d",
			$day_id
		),
		ARRAY_A
	);

	if ( ! $day ) return;

	$shift_letter = $day['shift_letter'];
	$date         = $day['schedule_date'];
	$type_label   = schedules_shift_type_label( $shift_letter );
	$date_label   = date( 'l, F j, Y', strtotime($date) );
	$site_url     = home_url('/schedules-overtime/');

	$subject = "New OT Slot Available — Shift {$shift_letter} ({$type_label}) on {$date_label}";

	$eligible_users = get_users([
		'role__in' => [ 'schedules_member', 'schedules_supervisor' ],
	]);

	foreach ( $eligible_users as $user ) {
		$user_shift = get_user_meta( $user->ID, 'schedules_shift', true );
		if ( $user_shift === $shift_letter ) continue;
		if ( ! schedules_user_can_see_date( $user->ID, $date ) ) continue;

		$body  = "Hello {$user->first_name},\n\n";
		$body .= "A new overtime slot has become available:\n\n";
		$body .= "Shift {$shift_letter} ({$type_label}) — {$date_label}\n\n";
		$body .= "Log in to claim your spot: {$site_url}\n\n";
		$body .= "First come, first served. Claims cannot be undone.\n\n";
		$body .= "CCSO Scheduling System";

		wp_mail( $user->user_email, $subject, $body );
	}
}


/*--------------------------------------------------------------
# Cron — Rolling Window
--------------------------------------------------------------*/

add_action( 'wp', 'schedules_setup_cron' );
function schedules_setup_cron(): void {
	if ( ! wp_next_scheduled('schedules_daily_rollover') ) {
		wp_schedule_event( strtotime('tomorrow midnight'), 'daily', 'schedules_daily_rollover' );
	}
}

add_action( 'schedules_daily_rollover', 'schedules_run_daily_rollover' );
function schedules_run_daily_rollover(): void {
	global $wpdb;

	$today = date('Y-m-d');

	// Archive today's days
	$wpdb->update(
		schedules_table('days'),
		[ 'is_archived' => 1 ],
		[ 'schedule_date' => $today ],
		[ '%d' ],
		[ '%s' ]
	);

	// Generate 28 days from now
	schedules_generate_day( date('Y-m-d', strtotime('+28 days')) );
}


/*--------------------------------------------------------------
# AJAX Handlers
--------------------------------------------------------------*/

// --- Login (nopriv) ---
add_action( 'wp_ajax_nopriv_schedules_login', 'schedules_ajax_login' );
function schedules_ajax_login(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	$badge    = sanitize_text_field( $_POST['badge'] ?? '' );
	$password = $_POST['password'] ?? '';

	if ( empty($badge) || empty($password) ) {
		wp_send_json_error( [ 'message' => 'Badge number and password are required.' ] );
	}

	$creds = [
		'user_login'    => $badge,
		'user_password' => $password,
		'remember'      => true,
	];

	$user = wp_signon( $creds, false );

	if ( is_wp_error($user) ) {
		wp_send_json_error( [ 'message' => 'Invalid badge number or password.' ] );
	}

	$roles = (array) $user->roles;
	if ( ! in_array('schedules_member', $roles, true) && ! in_array('schedules_supervisor', $roles, true) ) {
		wp_logout();
		wp_send_json_error( [ 'message' => 'You do not have access to this system.' ] );
	}

	$redirect = in_array('schedules_supervisor', $roles, true)
		? home_url('/schedules-supervisor/')
		: home_url('/schedules-overtime/');

	wp_send_json_success( [ 'redirect' => $redirect ] );
}

// --- Logout ---
add_action( 'wp_ajax_schedules_logout', 'schedules_ajax_logout' );
function schedules_ajax_logout(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	wp_logout();
	wp_send_json_success( [ 'redirect' => home_url('/schedules-login/') ] );
}

// --- Claim Block ---
add_action( 'wp_ajax_schedules_claim_block', 'schedules_ajax_claim_block' );
function schedules_ajax_claim_block(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	$block_id = (int) ($_POST['block_id'] ?? 0);
	$user_id  = get_current_user_id();

	if ( ! $block_id || ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Invalid request.' ] );
	}

	$result = schedules_claim_block( $block_id, $user_id );

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}

// --- Get Calendar ---
add_action( 'wp_ajax_schedules_get_calendar', 'schedules_ajax_get_calendar' );
function schedules_ajax_get_calendar(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	$user_id = get_current_user_id();
	$data    = schedules_get_calendar_data( $user_id );

	wp_send_json_success( $data );
}

// --- Add Adjustment (supervisor only) ---
add_action( 'wp_ajax_schedules_add_adjustment', 'schedules_ajax_add_adjustment' );
function schedules_ajax_add_adjustment(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$day_id  = (int) ($_POST['day_id'] ?? 0);
	$reason  = sanitize_text_field( $_POST['reason'] ?? '' );
	$user_id = get_current_user_id();

	if ( ! $day_id ) {
		wp_send_json_error( [ 'message' => 'Invalid day ID.' ] );
	}

	$result = schedules_add_adjustment( $day_id, $user_id, 1, $reason );

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}

// --- Get Claims (supervisor only) ---
add_action( 'wp_ajax_schedules_get_claims', 'schedules_ajax_get_claims' );
function schedules_ajax_get_claims(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$date         = sanitize_text_field( $_POST['date'] ?? '' );
	$shift_letter = sanitize_text_field( $_POST['shift_letter'] ?? '' );

	if ( ! $date ) {
		wp_send_json_error( [ 'message' => 'Date is required.' ] );
	}

	$claims = schedules_get_claims_for_day( $date, $shift_letter );
	wp_send_json_success( $claims );
}

// --- Create Member (supervisor only) ---
add_action( 'wp_ajax_schedules_create_member', 'schedules_ajax_create_member' );
function schedules_ajax_create_member(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$result = schedules_create_member( $_POST );

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}

// --- Update Member (supervisor only) ---
add_action( 'wp_ajax_schedules_update_member', 'schedules_ajax_update_member' );
function schedules_ajax_update_member(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$user_id    = (int) ($_POST['user_id'] ?? 0);
	$first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
	$last_name  = sanitize_text_field( $_POST['last_name'] ?? '' );
	$email      = sanitize_email( $_POST['email'] ?? '' );
	$shift      = sanitize_text_field( $_POST['shift'] ?? '' );
	$discipline = sanitize_text_field( $_POST['discipline'] ?? '' );
	$priority   = sanitize_text_field( $_POST['priority'] ?? '' );
	$is_sup     = ! empty( $_POST['is_supervisor'] );
	$new_pass   = $_POST['member_pass'] ?? $_POST['password'] ?? '';

	if ( ! $user_id || ! get_userdata($user_id) ) {
		wp_send_json_error( [ 'message' => 'User not found.' ] );
	}

	$update_data = [ 'ID' => $user_id ];
	if ( $first_name ) $update_data['first_name']   = $first_name;
	if ( $last_name )  $update_data['last_name']    = $last_name;
	if ( $first_name || $last_name ) {
		$update_data['display_name'] = trim("{$first_name} {$last_name}");
	}
	if ( $email )    $update_data['user_email']  = $email;
	if ( $new_pass ) $update_data['user_pass']   = $new_pass;

	wp_update_user( $update_data );

	update_user_meta( $user_id, 'schedules_shift', $shift );
	if ( $discipline ) update_user_meta( $user_id, 'schedules_discipline', $discipline );
	if ( $priority )   update_user_meta( $user_id, 'schedules_priority',   $priority );

	$user = new WP_User( $user_id );
	$new_role = $is_sup ? 'schedules_supervisor' : 'schedules_member';
	$user->set_role( $new_role );

	wp_send_json_success( [ 'message' => 'Member updated.' ] );
}

// --- Delete Member (supervisor only — soft delete) ---
add_action( 'wp_ajax_schedules_delete_member', 'schedules_ajax_delete_member' );
function schedules_ajax_delete_member(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$user_id = (int) ($_POST['user_id'] ?? 0);

	if ( ! $user_id || ! get_userdata($user_id) ) {
		wp_send_json_error( [ 'message' => 'User not found.' ] );
	}

	$user = new WP_User( $user_id );
	$user->set_role('subscriber');

	wp_send_json_success( [ 'message' => 'Member removed from scheduling system.' ] );
}

// --- Change Password ---
add_action( 'wp_ajax_schedules_change_password', 'schedules_ajax_change_password' );
function schedules_ajax_change_password(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	$user_id          = get_current_user_id();
	$current_password = $_POST['current_password'] ?? '';
	$new_password     = $_POST['new_password'] ?? '';

	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not logged in.' ] );
	}

	$user = get_userdata( $user_id );
	if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Current password is incorrect.' ] );
	}

	if ( strlen($new_password) < 8 ) {
		wp_send_json_error( [ 'message' => 'New password must be at least 8 characters.' ] );
	}

	wp_set_password( $new_password, $user_id );

	// Re-authenticate so session stays valid
	$user = get_userdata( $user_id );
	wp_set_auth_cookie( $user_id, true );

	wp_send_json_success( [ 'message' => 'Password updated successfully.' ] );
}

// --- Update Shift Floor Count (supervisor only) ---
add_action( 'wp_ajax_schedules_update_shift_floor_count', 'schedules_ajax_update_shift_floor_count' );
function schedules_ajax_update_shift_floor_count(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$shift_letter = sanitize_text_field( $_POST['shift_letter'] ?? '' );
	$floor_count  = (int) ($_POST['floor_count'] ?? 0);

	if ( ! in_array($shift_letter, ['A','B','C','D'], true) ) {
		wp_send_json_error( [ 'message' => 'Invalid shift letter.' ] );
	}

	// Update the shift template
	$wpdb->update(
		schedules_table('shifts'),
		[ 'floor_count' => $floor_count ],
		[ 'shift_letter' => $shift_letter ],
		[ '%d' ],
		[ '%s' ]
	);

	// Propagate to all non-archived day rows for this shift
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE " . schedules_table('days') . "
			 SET floor_count = %d
			 WHERE shift_letter = %s AND is_archived = 0",
			$floor_count,
			$shift_letter
		)
	);

	wp_send_json_success( [ 'message' => "Shift {$shift_letter} floor count updated to {$floor_count}." ] );
}

// --- Save All Floor Counts at once (supervisor only) ---
add_action( 'wp_ajax_schedules_save_all_floor_counts', 'schedules_ajax_save_all_floor_counts' );
function schedules_ajax_save_all_floor_counts(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$counts = $_POST['counts'] ?? [];
	if ( ! is_array($counts) || empty($counts) ) {
		wp_send_json_error( [ 'message' => 'No floor counts provided.' ] );
	}

	foreach ( $counts as $shift_letter => $floor_count ) {
		$shift_letter = sanitize_text_field( $shift_letter );
		$floor_count  = (int) $floor_count;

		if ( ! in_array( $shift_letter, ['A','B','C','D'], true ) ) continue;

		$wpdb->update(
			schedules_table('shifts'),
			[ 'floor_count' => $floor_count ],
			[ 'shift_letter' => $shift_letter ],
			[ '%d' ],
			[ '%s' ]
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE " . schedules_table('days') . "
				 SET floor_count = %d
				 WHERE shift_letter = %s AND is_archived = 0",
				$floor_count,
				$shift_letter
			)
		);
	}

	wp_send_json_success( [ 'message' => 'All floor counts saved.' ] );
}


/*--------------------------------------------------------------
# Body Class & Auth Guard
--------------------------------------------------------------*/

add_filter( 'body_class', 'schedules_body_class' );
function schedules_body_class( array $classes ): array {
	global $post;
	$schedules_slugs = [ 'schedules-login', 'schedules-overtime', 'schedules-supervisor' ];

	if ( $post && in_array( $post->post_name, $schedules_slugs, true ) ) {
		$classes[] = 'schedules-page';
		$classes[] = 'slug-' . $post->post_name;
	}

	return $classes;
}

add_action( 'template_redirect', 'schedules_auth_guard' );
function schedules_auth_guard(): void {
	global $post;
	if ( ! $post ) return;

	$slug = $post->post_name;

	if ( in_array($slug, ['schedules-overtime', 'schedules-supervisor'], true) ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url('/schedules-login/') );
			exit;
		}
	}

	if ( $slug === 'schedules-supervisor' && is_user_logged_in() ) {
		if ( ! current_user_can('manage_schedules') ) {
			wp_safe_redirect( home_url('/schedules-overtime/') );
			exit;
		}
	}
}
