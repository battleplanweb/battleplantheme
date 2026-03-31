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

define( 'SCHEDULES_DB_VERSION', '2.7' );

/**
 * Returns a fully-qualified table name for the schedules module.
 * Using a function avoids the $wpdb-at-define-time issue.
 */
function schedules_table( string $name ): string {
	return $GLOBALS['wpdb']->prefix . 'schedules_' . $name;
}

function schedules_unread_count( int $user_id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . schedules_table('notifications') . " WHERE user_id = %d AND is_read = 0",
		$user_id
	) );
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
		end_hour tinyint(2) NOT NULL DEFAULT 18,
		work_days varchar(20) NOT NULL,
		work_days_week2 varchar(20) DEFAULT NULL,
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

	$sql .= "CREATE TABLE " . schedules_table('config') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		config_key varchar(100) NOT NULL,
		config_value text DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY config_key (config_key)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('disciplines') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		display_order tinyint(3) NOT NULL DEFAULT 0,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		UNIQUE KEY name (name)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('positions') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		required_discipline_id int(11) NOT NULL DEFAULT 0,
		display_order tinyint(3) NOT NULL DEFAULT 0,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY  (id)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('titles') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		display_order tinyint(3) NOT NULL DEFAULT 0,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		UNIQUE KEY name (name)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('duty') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		day_id int(11) NOT NULL,
		position_id int(11) NOT NULL,
		user_id int(11) NOT NULL,
		start_time time NOT NULL DEFAULT '06:00:00',
		end_time time NOT NULL DEFAULT '18:00:00',
		assigned_by int(11) NOT NULL,
		assigned_at datetime NOT NULL,
		is_voided tinyint(1) NOT NULL DEFAULT 0,
		voided_by int(11) DEFAULT NULL,
		voided_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY day_position (day_id, position_id)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('trades') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		type varchar(10) NOT NULL DEFAULT 'swap',
		requester_id int(11) NOT NULL,
		recipient_id int(11) NOT NULL,
		requester_day_id int(11) NOT NULL,
		recipient_day_id int(11) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		requester_note text DEFAULT NULL,
		recipient_note text DEFAULT NULL,
		supervisor_note text DEFAULT NULL,
		requested_at datetime NOT NULL,
		responded_at datetime DEFAULT NULL,
		reviewed_by int(11) DEFAULT NULL,
		reviewed_at datetime DEFAULT NULL,
		PRIMARY KEY  (id)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('timeoff') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		type varchar(10) NOT NULL,
		start_date date NOT NULL,
		end_date date NOT NULL,
		hours decimal(5,2) NOT NULL DEFAULT 0,
		status varchar(10) NOT NULL DEFAULT 'pending',
		notes text DEFAULT NULL,
		supervisor_note text DEFAULT NULL,
		reviewed_by int(11) DEFAULT NULL,
		reviewed_at datetime DEFAULT NULL,
		requested_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('notifications') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		type varchar(50) NOT NULL,
		related_id int(11) DEFAULT NULL,
		related_type varchar(50) DEFAULT NULL,
		message text NOT NULL,
		is_read tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $charset;";

	dbDelta( $sql );

	schedules_seed_shifts();
	schedules_seed_disciplines();
}

/**
 * Seeds the 4 default shift rows if the shifts table is empty.
 */
function schedules_seed_shifts(): void {
	global $wpdb;

	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . schedules_table('shifts') );
	if ( $count > 0 ) return;

	$shifts = [
		[ 'shift_letter' => 'A', 'start_hour' => 6,  'end_hour' => 18, 'work_days' => '0,1,2,3' ],   // Sun–Wed day
		[ 'shift_letter' => 'B', 'start_hour' => 6,  'end_hour' => 18, 'work_days' => '3,4,5,6' ],   // Wed–Sat day
		[ 'shift_letter' => 'C', 'start_hour' => 18, 'end_hour' => 6,  'work_days' => '0,1,2,3' ],   // Sun–Wed night
		[ 'shift_letter' => 'D', 'start_hour' => 18, 'end_hour' => 6,  'work_days' => '3,4,5,6' ],   // Wed–Sat night
	];

	foreach ( $shifts as $shift ) {
		$wpdb->insert(
			schedules_table('shifts'),
			[
				'shift_letter' => $shift['shift_letter'],
				'start_hour'   => $shift['start_hour'],
				'end_hour'     => $shift['end_hour'],
				'work_days'    => $shift['work_days'],
				'floor_count'  => 0,
				'max_capacity' => 14,
			],
			[ '%s', '%d', '%d', '%s', '%d', '%d' ]
		);
	}
}

/**
 * Seeds the default disciplines if the disciplines table is empty.
 */
function schedules_seed_disciplines(): void {
	global $wpdb;

	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . schedules_table('disciplines') );
	if ( $count > 0 ) return;

	$disciplines = [
		[ 'name' => 'Phones',   'display_order' => 1 ],
		[ 'name' => 'Radio',    'display_order' => 2 ],
		[ 'name' => 'Control',  'display_order' => 3 ],
		[ 'name' => 'Non-911',  'display_order' => 4 ],
		[ 'name' => 'Training', 'display_order' => 5 ],
	];

	foreach ( $disciplines as $d ) {
		$wpdb->insert(
			schedules_table('disciplines'),
			[ 'name' => $d['name'], 'display_order' => $d['display_order'], 'is_active' => 1 ],
			[ '%s', '%d', '%d' ]
		);
	}
}

/**
 * Returns an array of 1-hour block pairs for a given shift start hour (normal 12-hr day).
 */
function schedules_get_block_hours( int $start_hour ): array {
	$blocks  = [];
	$current = $start_hour;
	for ( $i = 0; $i < 12; $i++ ) {
		$end      = ( $current + 1 ) % 24;
		$blocks[] = [ 'start' => $current, 'end' => $end ];
		$current  = $end;
	}
	return $blocks;
}

/**
 * Returns 1-hour block pairs for a Wednesday half-shift (6 hours each).
 * A: 06–12, B: 12–18, C: 18–00, D: 00–06
 */
function schedules_get_wednesday_block_hours( string $shift_letter ): array {
	$start_map = [ 'A' => 6, 'B' => 12, 'C' => 18, 'D' => 0 ];
	$start     = $start_map[ $shift_letter ] ?? null;
	if ( $start === null ) return [];
	$blocks  = [];
	$current = $start;
	for ( $i = 0; $i < 6; $i++ ) {
		$end      = ( $current + 1 ) % 24;
		$blocks[] = [ 'start' => $current, 'end' => $end ];
		$current  = $end;
	}
	return $blocks;
}

function schedules_get_block_hours_range( int $start_hour, int $num_hours ): array {
	$blocks  = [];
	$current = $start_hour;
	for ( $i = 0; $i < $num_hours; $i++ ) {
		$end      = ( $current + 1 ) % 24;
		$blocks[] = [ 'start' => $current, 'end' => $end ];
		$current  = $end;
	}
	return $blocks;
}

function schedules_shift_duration( int $start, int $end ): int {
	return $start < $end ? ( $end - $start ) : ( 24 - $start + $end );
}

/**
 * Generates a schedules_days row + schedules_blocks rows for a given date,
 * for each shift whose work_days (week 1 or week 2) includes that date's day-of-week.
 */
function schedules_generate_day( string $date ): void {
	global $wpdb;

	$day_of_week  = (int) date( 'w', strtotime( $date ) ); // 0=Sun...6=Sat
	$cycle_anchor = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';
	$cycle_week   = schedules_get_cycle_week( $date, $cycle_anchor ); // 0 or 1

	$shifts = $wpdb->get_results( "SELECT * FROM " . schedules_table('shifts'), ARRAY_A );

	foreach ( $shifts as $shift ) {
		$work_days_w1 = array_map( 'intval', explode( ',', $shift['work_days'] ) );
		$work_days_w2 = ! empty( $shift['work_days_week2'] )
			? array_map( 'intval', explode( ',', $shift['work_days_week2'] ) )
			: $work_days_w1;
		$active_days  = $cycle_week === 0 ? $work_days_w1 : $work_days_w2;

		if ( ! in_array( $day_of_week, $active_days, true ) ) continue;

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
		$start_hour  = isset( $shift['end_hour'] ) ? (int) $shift['start_hour'] : (int) $shift['start_hour'];
		$end_hour    = isset( $shift['end_hour'] ) ? (int) $shift['end_hour']   : ( ( (int) $shift['start_hour'] + 12 ) % 24 );

		// Wednesday overlap: count how many shifts share this start_hour on this date
		if ( $day_of_week === 3 ) {
			$same_time_count = 0;
			$same_time_before = 0;
			foreach ( $shifts as $other ) {
				$ow1 = array_map( 'intval', explode( ',', $other['work_days'] ) );
				$ow2 = ! empty( $other['work_days_week2'] )
					? array_map( 'intval', explode( ',', $other['work_days_week2'] ) )
					: $ow1;
				$other_active = $cycle_week === 0 ? $ow1 : $ow2;
				if ( ! in_array( 3, $other_active, true ) ) continue;
				$other_start = (int) $other['start_hour'];
				if ( $other_start === (int) $shift['start_hour'] ) {
					$same_time_count++;
					if ( $other['shift_letter'] < $shift['shift_letter'] ) {
						$same_time_before++;
					}
				}
			}
			if ( $same_time_count > 1 ) {
				// Split: divide the shift hours evenly among overlapping shifts
				$total_hours = $start_hour < $end_hour
					? $end_hour - $start_hour
					: ( 24 - $start_hour + $end_hour );
				$chunk = (int) floor( $total_hours / $same_time_count );
				$my_start = ( $start_hour + ( $chunk * $same_time_before ) ) % 24;
				$block_hours = schedules_get_block_hours_range( $my_start, $chunk );
			} else {
				$block_hours = schedules_get_block_hours_range( $start_hour, schedules_shift_duration( $start_hour, $end_hour ) );
			}
		} else {
			$block_hours = schedules_get_block_hours_range( $start_hour, schedules_shift_duration( $start_hour, $end_hour ) );
		}

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
 * Deletes all future unclaimed day rows and regenerates them.
 * Called when shift settings change so rotation/hours are correct.
 */
function schedules_regenerate_future_days(): void {
	global $wpdb;

	$today = date( 'Y-m-d' );

	// Find future, non-archived day IDs that have NO claims
	$day_ids = $wpdb->get_col(
		"SELECT d.id FROM " . schedules_table('days') . " d
		 WHERE d.schedule_date >= '{$today}'
		   AND d.is_archived = 0
		   AND NOT EXISTS (
		       SELECT 1 FROM " . schedules_table('blocks') . " b
		       JOIN " . schedules_table('claims') . " c ON c.block_id = b.id
		       WHERE b.day_id = d.id
		   )"
	);

	if ( ! empty( $day_ids ) ) {
		$placeholders = implode( ',', array_map( 'intval', $day_ids ) );
		$wpdb->query( "DELETE FROM " . schedules_table('blocks') . " WHERE day_id IN ($placeholders)" );
		$wpdb->query( "DELETE FROM " . schedules_table('days')   . " WHERE id IN ($placeholders)" );
	}

	// Regenerate the full 28-day window
	for ( $i = 0; $i <= 28; $i++ ) {
		schedules_generate_day( date( 'Y-m-d', strtotime( "+{$i} days" ) ) );
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
			// Fresh install
			schedules_generate_initial_window();
			schedules_seed_disciplines();
		} elseif ( $stored === '1.0' ) {
			// 1.0 → 2.0
			schedules_migrate_wednesdays();
			schedules_seed_disciplines();
		} elseif ( $stored === '1.1' ) {
			// 1.1 → 2.0: new tables only, existing data untouched
			schedules_seed_disciplines();
		} elseif ( $stored === '2.0' ) {
			// 2.0 → 2.1: add start_time/end_time to seating, drop unique key
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('seating') );
			if ( ! in_array('start_time', $cols) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('seating') . " ADD COLUMN start_time time NOT NULL DEFAULT '06:00:00' AFTER user_id" );
			}
			if ( ! in_array('end_time', $cols) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('seating') . " ADD COLUMN end_time time NOT NULL DEFAULT '18:00:00' AFTER start_time" );
			}
			// Drop the old UNIQUE KEY if it still exists (Non_unique=0 means unique)
			$idx = $wpdb->get_row( "SHOW INDEX FROM " . schedules_table('seating') . " WHERE Key_name = 'day_position' AND Non_unique = 0" );
			if ( $idx ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('seating') . " DROP INDEX day_position, ADD KEY day_position (day_id, position_id)" );
			}
		} elseif ( $stored === '2.1' ) {
			// 2.1 → 2.2: rename seating table to duty
			global $wpdb;
			$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}schedules_seating'" );
			if ( ! empty($tables) ) {
				$wpdb->query( "RENAME TABLE {$wpdb->prefix}schedules_seating TO {$wpdb->prefix}schedules_duty" );
			}
		} elseif ( $stored === '2.2' ) {
			// 2.2 → 2.3: add soft-delete columns to duty table
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('duty') );
			if ( ! in_array('is_voided', $cols) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('duty') . " ADD COLUMN is_voided tinyint(1) NOT NULL DEFAULT 0 AFTER assigned_at" );
			}
			if ( ! in_array('voided_by', $cols) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('duty') . " ADD COLUMN voided_by int(11) DEFAULT NULL AFTER is_voided" );
			}
			if ( ! in_array('voided_at', $cols) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('duty') . " ADD COLUMN voided_at datetime DEFAULT NULL AFTER voided_by" );
			}
		} elseif ( $stored === '2.3' ) {
			// 2.3 → 2.4: titles table added (handled by dbDelta in schedules_install_db)
		} elseif ( $stored === '2.4' ) {
			// 2.4 → 2.5: switch from multi-hour blocks to 1-hour blocks
			global $wpdb;
			$wpdb->query( "DELETE FROM " . schedules_table('claims') );
			$wpdb->query( "DELETE FROM " . schedules_table('blocks') );
			$wpdb->query( "DELETE FROM " . schedules_table('days') );
			schedules_generate_initial_window();
		} elseif ( $stored === '2.5' ) {
			// 2.5 → 2.6: rename priority values from color names to numeric tiers
			global $wpdb;
			$priority_map = [
				'floor'  => '1',
				'green'  => '2',
				'yellow' => '3',
				'red'    => '4',
				'black'  => '5',
			];
			foreach ( $priority_map as $old => $new ) {
				$wpdb->update(
					$wpdb->usermeta,
					[ 'meta_value' => $new ],
					[ 'meta_key' => 'schedules_priority', 'meta_value' => $old ],
					[ '%s' ],
					[ '%s', '%s' ]
				);
			}
			// Rename config keys
			$config_table = schedules_table('config');
			$wpdb->update( $config_table, [ 'config_key' => 'ot_priority_2_max' ], [ 'config_key' => 'ot_priority_green_max' ] );
			$wpdb->update( $config_table, [ 'config_key' => 'ot_priority_3_max' ], [ 'config_key' => 'ot_priority_yellow_max' ] );
		} elseif ( $stored === '2.6' ) {
			// 2.6 → 2.7: add end_hour and work_days_week2 to shifts table
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('shifts') );
			if ( ! in_array( 'end_hour', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN end_hour tinyint(2) NOT NULL DEFAULT 18 AFTER start_hour" );
				// Backfill end_hour from start_hour (12-hour shifts)
				$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET end_hour = (start_hour + 12) % 24" );
			}
			if ( ! in_array( 'work_days_week2', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN work_days_week2 varchar(20) DEFAULT NULL AFTER work_days" );
			}
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

	add_filter( 'body_class', function( $classes ) {
		$classes[] = 'has-schedules-app';
		return $classes;
	} );
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
		filemtime( get_template_directory() . '/style-schedules.css' )
	);

	wp_enqueue_script(
		'schedules-script',
		get_template_directory_uri() . '/js/script-schedules.js',
		[],
		filemtime( get_template_directory() . '/js/script-schedules.js' ),
		true
	);

	$user_id       = defined('_USER_ID') ? _USER_ID : get_current_user_id();
	$user          = get_userdata( $user_id );
	$is_supervisor = $user && $user->has_cap('manage_schedules') ? 'true' : 'false';
	$is_admin      = $user && $user->has_cap('admin_schedules')  ? 'true' : 'false';

	wp_localize_script( 'schedules-script', 'schedulesData', [
		'ajaxUrl'            => admin_url('admin-ajax.php'),
		'nonce'              => wp_create_nonce('schedules_nonce'),
		'userId'             => $user_id,
		'userShift'          => schedules_get_user_shift( $user_id ),
		'userPriority'       => schedules_get_user_priority( $user_id ),
		'userFirstName'      => $user ? $user->first_name : '',
		'userLastName'       => $user ? $user->last_name : '',
		'isSupervisor'       => $is_supervisor,
		'isAdmin'            => $is_admin,
		'disciplines'        => schedules_get_disciplines(),
		'titles'             => schedules_get_titles(),
		'dutyConflictPairs'  => apply_filters( 'schedules_duty_conflict_pairs', [] ),
		'dutyTimeIncrement'        => (int) schedules_get_config( 'duty_time_increment', '60' ),
		'dutyNameFormat'           => get_user_meta( $user_id, 'schedules_name_format', true ) ?: 'first_last',
		'supervisorsCanClaimOt'    => schedules_get_config( 'supervisors_can_claim_ot', '0' ),
		'minClaimHours'            => (int) apply_filters( 'schedules_min_claim_hours', max( 1, (int) schedules_get_config( 'ot_min_claim_hours', '0' ) ) ),
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

	add_role( 'schedules_admin', 'Schedules Admin', [
		'read'             => true,
		'manage_schedules' => true,
		'admin_schedules'  => true,
	] );
}

function schedules_remove_roles(): void {
	remove_role( 'schedules_member' );
	remove_role( 'schedules_supervisor' );
	remove_role( 'schedules_admin' );
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
 * Returns all disciplines, optionally only active ones.
 */
function schedules_get_disciplines( bool $active_only = true ): array {
	global $wpdb;
	$where = $active_only ? 'WHERE is_active = 1' : '';
	return $wpdb->get_results(
		"SELECT * FROM " . schedules_table('disciplines') . " {$where} ORDER BY display_order ASC, name ASC",
		ARRAY_A
	) ?: [];
}

/**
 * Returns all positions with their discipline name, optionally only active ones.
 */
function schedules_get_positions( bool $active_only = true ): array {
	global $wpdb;
	$where = $active_only ? 'WHERE p.is_active = 1' : '';
	return $wpdb->get_results(
		"SELECT p.*, d.name AS discipline_name
		 FROM " . schedules_table('positions') . " p
		 LEFT JOIN " . schedules_table('disciplines') . " d ON d.id = p.required_discipline_id AND p.required_discipline_id > 0
		 {$where}
		 ORDER BY p.display_order ASC, p.name ASC",
		ARRAY_A
	) ?: [];
}

/**
 * Recalculates priority tiers 2/3/4 for all auto-tier members based on pay rate cutoffs.
 * Cutoffs are stored in config: ot_priority_2_max, ot_priority_3_max.
 * Members with priority = '1' or '5' are left untouched (manually assigned).
 * Skipped entirely if cutoffs have not been configured.
 */
function schedules_recalculate_priorities(): void {
	$tier2_max = (float) schedules_get_config( 'ot_priority_2_max', '0' );
	$tier3_max = (float) schedules_get_config( 'ot_priority_3_max', '0' );

	// Don't auto-assign if cutoffs haven't been configured yet
	if ( $tier2_max <= 0 && $tier3_max <= 0 ) return;

	$users = get_users([
		'role__in' => [ 'schedules_member', 'schedules_supervisor', 'schedules_admin' ],
	]);

	foreach ( $users as $user ) {
		$priority = get_user_meta( $user->ID, 'schedules_priority', true );
		if ( $priority === '1' || $priority === '5' ) continue;

		$pay_rate = (float) get_user_meta( $user->ID, 'schedules_pay_rate', true );

		if ( $pay_rate <= $tier2_max ) {
			$new_priority = '2';
		} elseif ( $pay_rate <= $tier3_max ) {
			$new_priority = '3';
		} else {
			$new_priority = '4';
		}

		update_user_meta( $user->ID, 'schedules_priority', $new_priority );
	}
}

/**
 * Returns all titles, optionally only active ones.
 */
function schedules_get_titles( bool $active_only = true ): array {
	global $wpdb;
	$where = $active_only ? 'WHERE is_active = 1' : '';
	return $wpdb->get_results(
		"SELECT * FROM " . schedules_table('titles') . " {$where} ORDER BY display_order ASC, name ASC",
		ARRAY_A
	) ?: [];
}

/**
 * Get a config value.
 */
function schedules_get_config( string $key, string $default = '' ): string {
	global $wpdb;
	$val = $wpdb->get_var( $wpdb->prepare(
		"SELECT config_value FROM " . schedules_table('config') . " WHERE config_key = %s",
		$key
	) );
	return $val !== null ? (string) $val : $default;
}

/**
 * Set a config value.
 */
function schedules_set_config( string $key, string $value ): void {
	global $wpdb;
	$wpdb->replace(
		schedules_table('config'),
		[ 'config_key' => $key, 'config_value' => $value ],
		[ '%s', '%s' ]
	);
}

/**
 * Returns the number of days ahead a user with given priority can see.
 */
function schedules_get_max_days( string $priority ): int {
	switch ( $priority ) {
		case '1': return 28;
		case '2': return 21;
		case '3': return 14;
		case '4': return 7;
		case '5': return 3;
		default:  return 0;
	}
}

/**
 * Returns true if a shift's working hours have ended.
 * Day shifts (A/B) run 06:00–18:00; night shifts (C/D) run 18:00–06:00 (next day).
 */
function schedules_is_shift_ended( string $date, string $shift_letter ): bool {
	$now = current_time('timestamp');
	$shift_letter = strtoupper( $shift_letter );

	if ( in_array( $shift_letter, [ 'A', 'B' ], true ) ) {
		$end = strtotime( $date . ' 18:00:00' );
	} else {
		// Night shifts end at 06:00 the NEXT day
		$end = strtotime( $date . ' 06:00:00 +1 day' );
	}

	return $now >= $end;
}

/**
 * Returns true if the user's priority window includes the given date.
 */
function schedules_user_can_see_date( int $user_id, string $date ): bool {
	$priority = schedules_get_user_priority( $user_id );
	$max_days = schedules_get_max_days( $priority );

	if ( $max_days === 0 ) {
		$sup_can_claim = schedules_get_config( 'supervisors_can_claim_ot', '0' ) === '1';
		if ( $sup_can_claim ) {
			$user_obj   = get_userdata( $user_id );
			$user_roles = $user_obj ? (array) $user_obj->roles : [];
			if ( ! empty( array_intersect( $user_roles, [ 'schedules_supervisor', 'schedules_admin' ] ) ) ) {
				$max_days = 28;
			}
		}
		if ( $max_days === 0 ) return false;
	}

	$today     = strtotime( date('Y-m-d') );
	$target    = strtotime( $date );
	$diff_days = (int) floor( ( $target - $today ) / DAY_IN_SECONDS );

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

	$priority   = schedules_get_user_priority( $user_id );
	$max_days   = schedules_get_max_days( $priority );
	$user_shift = schedules_get_user_shift( $user_id );

	// If supervisors/admins are allowed to claim OT and this user is one, give them a full window
	$sup_can_claim = schedules_get_config( 'supervisors_can_claim_ot', '0' ) === '1';
	if ( $max_days === 0 && $sup_can_claim ) {
		$user_obj  = get_userdata( $user_id );
		$user_roles = $user_obj ? (array) $user_obj->roles : [];
		if ( ! empty( array_intersect( $user_roles, [ 'schedules_supervisor', 'schedules_admin' ] ) ) ) {
			$max_days = 28;
		}
	}

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

	// Get all blocks for those days with claims_count, user_claimed flag, and claimed_at
	$blocks_query = $wpdb->prepare(
		"SELECT b.*,
		        COUNT(c.id) AS claims_count,
		        SUM(CASE WHEN c.user_id = %d THEN 1 ELSE 0 END) AS user_claimed,
		        MAX(CASE WHEN c.user_id = %d THEN c.claimed_at ELSE NULL END) AS user_claimed_at
		 FROM " . schedules_table('blocks') . " b
		 LEFT JOIN " . schedules_table('claims') . " c ON c.block_id = b.id
		 WHERE b.day_id IN ($day_ids_placeholder)
		 GROUP BY b.id
		 ORDER BY b.day_id ASC, b.block_index ASC",
		array_merge( [$user_id, $user_id], $day_ids )
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
					'user_claimed_at'  => $block['user_claimed_at'] ?: null,
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

	$user_id    = get_current_user_id();
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
			"SELECT b.*, COUNT(c.id) AS claims_count,
			        SUM(CASE WHEN c.user_id = %d THEN 1 ELSE 0 END) AS user_claimed,
			        MAX(CASE WHEN c.user_id = %d THEN c.claimed_at ELSE NULL END) AS user_claimed_at
			 FROM " . schedules_table('blocks') . " b
			 LEFT JOIN " . schedules_table('claims') . " c ON c.block_id = b.id
			 WHERE b.day_id IN ($placeholders)
			 GROUP BY b.id
			 ORDER BY b.day_id ASC, b.block_index ASC",
			array_merge( [ $user_id, $user_id ], $day_ids )
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
					'user_claimed'     => (bool) $block['user_claimed'],
					'user_claimed_at'  => $block['user_claimed_at'] ?: null,
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
 * Formats a block's start/end hours as a military time range.
 * e.g., 6, 7 → "0600–0700"; 18, 19 → "1800–1900"
 */
function schedules_format_block_time( int $start, int $end ): string {
	return sprintf( '%04d', $start * 100 ) . '–' . sprintf( '%04d', $end * 100 );
}

/**
 * Returns the type label for a shift.
 */
function schedules_shift_type_label( string $shift_letter ): string {
	return in_array( $shift_letter, ['A','B'], true ) ? 'Day' : 'Night';
}

/**
 * Gets the day_id for a given date/shift, creating the record if it doesn't exist.
 * Used by seating so supervisors can assign seats for today or future dates
 * even if the rolling OT window hasn't generated a days record yet.
 */
function schedules_get_or_create_day_id( string $date, string $shift_letter ): int {
	global $wpdb;

	$day_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . schedules_table('days') . " WHERE schedule_date = %s AND shift_letter = %s",
		$date, $shift_letter
	) );

	if ( $day_id ) return $day_id;

	$shift = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('shifts') . " WHERE shift_letter = %s",
		$shift_letter
	), ARRAY_A );

	if ( ! $shift ) return 0;

	$wpdb->insert(
		schedules_table('days'),
		[
			'shift_letter'  => $shift_letter,
			'schedule_date' => $date,
			'floor_count'   => (int) $shift['floor_count'],
			'max_capacity'  => (int) $shift['max_capacity'],
			'is_archived'   => 0,
			'created_at'    => current_time('mysql'),
		],
		[ '%s', '%s', '%d', '%d', '%d', '%s' ]
	);

	return (int) $wpdb->insert_id;
}

/**
 * Returns members available for seating on a given date/shift.
 * Includes: regular shift members + OT claimants for that day's blocks.
 */
function schedules_get_available_members_for_day( string $date, string $shift_letter ): array {
	global $wpdb;

	$members = [];

	$regulars = get_users( [
		'meta_key'   => 'schedules_shift',
		'meta_value' => $shift_letter,
		'role__in'   => [ 'schedules_member', 'schedules_supervisor', 'schedules_admin' ],
	] );

	foreach ( $regulars as $u ) {
		$disc     = (string) get_user_meta( $u->ID, 'schedules_discipline', true );
		$disc_arr = $disc ? array_values( array_filter( array_map( 'trim', explode( ',', $disc ) ) ) ) : [];
		$name     = trim( "{$u->first_name} {$u->last_name}" ) ?: $u->display_name;
		$members[ $u->ID ] = [
			'user_id'      => $u->ID,
			'display_name' => $name,
			'first_name'   => $u->first_name,
			'last_name'    => $u->last_name,
			'disciplines'  => $disc_arr,
			'type'         => 'regular',
		];
	}

	// OT claimants: people who claimed blocks under this shift/date
	$day_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . schedules_table('days') . " WHERE schedule_date = %s AND shift_letter = %s",
		$date, $shift_letter
	) );

	if ( $day_id ) {
		$claimant_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT c.user_id
			 FROM " . schedules_table('claims') . " c
			 JOIN " . schedules_table('blocks') . " b ON b.id = c.block_id
			 WHERE b.day_id = %d",
			$day_id
		) ) ?: [];

		// Get each OT claimant's claimed block hours
		$ot_blocks = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.user_id, b.start_hour, b.end_hour
			 FROM " . schedules_table('claims') . " c
			 JOIN " . schedules_table('blocks') . " b ON b.id = c.block_id
			 WHERE b.day_id = %d
			 ORDER BY b.start_hour ASC",
			$day_id
		), ARRAY_A ) ?: [];

		// Group blocks by user, then merge consecutive into ranges
		$ot_raw_by_user = [];
		foreach ( $ot_blocks as $ob ) {
			$ot_raw_by_user[ (int) $ob['user_id'] ][] = [
				'start' => (int) $ob['start_hour'],
				'end'   => (int) $ob['end_hour'],
			];
		}
		$ot_hours_by_user = [];
		foreach ( $ot_raw_by_user as $uid => $blocks ) {
			$merged = [];
			$cur    = null;
			foreach ( $blocks as $b ) {
				if ( $cur && $b['start'] === $cur['end'] ) {
					$cur['end'] = $b['end'];
				} else {
					if ( $cur ) $merged[] = $cur;
					$cur = $b;
				}
			}
			if ( $cur ) $merged[] = $cur;
			$ot_hours_by_user[ $uid ] = $merged;
		}

		foreach ( $claimant_ids as $uid ) {
			$uid = (int) $uid;
			if ( isset( $members[$uid] ) ) continue;
			$u = get_userdata( $uid );
			if ( ! $u ) continue;
			$disc     = (string) get_user_meta( $uid, 'schedules_discipline', true );
			$disc_arr = $disc ? array_values( array_filter( array_map( 'trim', explode( ',', $disc ) ) ) ) : [];
			$name     = trim( "{$u->first_name} {$u->last_name}" ) ?: $u->display_name;
			$members[$uid] = [
				'user_id'      => $uid,
				'display_name' => $name,
				'first_name'   => $u->first_name,
				'last_name'    => $u->last_name,
				'disciplines'  => $disc_arr,
				'type'         => 'ot',
				'ot_hours'     => $ot_hours_by_user[$uid] ?? [],
			];
		}
	}

	usort( $members, function( $a, $b ) { return strcmp( $a['display_name'], $b['display_name'] ); } );

	return array_values( $members );
}

/**
 * Returns all duty assignments for a day, ordered by position + start_time.
 * Each row includes id, position_id, user_id, start_time, end_time, display_name.
 */
function schedules_get_duty_for_day( int $day_id ): array {
	global $wpdb;

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT s.id, s.position_id, s.user_id, s.start_time, s.end_time,
		        u.display_name,
		        um_fn.meta_value AS first_name,
		        um_ln.meta_value AS last_name
		 FROM " . schedules_table('duty') . " s
		 JOIN {$wpdb->users} u ON u.ID = s.user_id
		 LEFT JOIN {$wpdb->usermeta} um_fn ON um_fn.user_id = s.user_id AND um_fn.meta_key = 'first_name'
		 LEFT JOIN {$wpdb->usermeta} um_ln ON um_ln.user_id = s.user_id AND um_ln.meta_key = 'last_name'
		 WHERE s.day_id = %d
		   AND s.is_voided = 0
		 ORDER BY s.position_id ASC, s.start_time ASC",
		$day_id
	), ARRAY_A ) ?: [];
}


/**
 * Returns the full day roster: all regular shift members + OT claimants,
 * each annotated with approved time-off and their duty assignments for the day.
 *
 * @param string  $date
 * @param string  $shift_letter
 * @param int     $day_id
 * @param array   $assignments  Output of schedules_get_duty_for_day()
 * @param array   $positions    Positions array (id, name) from the AJAX handler
 */
function schedules_get_roster_for_day( string $date, string $shift_letter, int $day_id, array $assignments, array $positions ): array {
	global $wpdb;

	// position_id → name lookup
	$pos_map = [];
	foreach ( $positions as $p ) {
		$pos_map[ (int) $p['id'] ] = $p['name'];
	}

	$members = [];

	// Regular shift members
	$regulars = get_users( [
		'meta_key'   => 'schedules_shift',
		'meta_value' => $shift_letter,
		'role__in'   => [ 'schedules_member', 'schedules_supervisor', 'schedules_admin' ],
	] );
	$_titles_map = [];
	foreach ( ( function_exists('schedules_get_titles') ? schedules_get_titles( false ) : [] ) as $_t ) {
		$_titles_map[ (int) $_t['id'] ] = $_t['name'];
	}

	foreach ( $regulars as $u ) {
		$disc     = (string) get_user_meta( $u->ID, 'schedules_discipline', true );
		$title_id = (int) get_user_meta( $u->ID, 'schedules_title', true );
		$name     = trim( "{$u->first_name} {$u->last_name}" ) ?: $u->display_name;
		$members[ $u->ID ] = [
			'user_id'      => $u->ID,
			'display_name' => $name,
			'first_name'   => $u->first_name,
			'last_name'    => $u->last_name,
			'discipline'   => $disc,
			'title'        => $title_id && isset($_titles_map[$title_id]) ? $_titles_map[$title_id] : '',
			'type'         => 'regular',
			'timeoff'      => null,
			'assignments'  => [],
		];
	}

	// OT claimants
	if ( $day_id ) {
		$claimant_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT c.user_id
			 FROM " . schedules_table('claims') . " c
			 JOIN " . schedules_table('blocks') . " b ON b.id = c.block_id
			 WHERE b.day_id = %d",
			$day_id
		) ) ?: [];

		// Get OT block hours and merge consecutive
		$ot_blocks_raw = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.user_id, b.start_hour, b.end_hour
			 FROM " . schedules_table('claims') . " c
			 JOIN " . schedules_table('blocks') . " b ON b.id = c.block_id
			 WHERE b.day_id = %d
			 ORDER BY b.start_hour ASC",
			$day_id
		), ARRAY_A ) ?: [];
		$_ot_raw = [];
		foreach ( $ot_blocks_raw as $ob ) {
			$_ot_raw[ (int) $ob['user_id'] ][] = [ 'start' => (int) $ob['start_hour'], 'end' => (int) $ob['end_hour'] ];
		}
		$_ot_merged = [];
		foreach ( $_ot_raw as $_uid => $_blocks ) {
			$_cur = null;
			foreach ( $_blocks as $_b ) {
				if ( $_cur && $_b['start'] === $_cur['end'] ) { $_cur['end'] = $_b['end']; }
				else { if ( $_cur ) $_ot_merged[$_uid][] = $_cur; $_cur = $_b; }
			}
			if ( $_cur ) $_ot_merged[$_uid][] = $_cur;
		}

		foreach ( $claimant_ids as $uid ) {
			$uid = (int) $uid;
			if ( isset( $members[$uid] ) ) continue;
			$u = get_userdata( $uid );
			if ( ! $u ) continue;
			$disc     = (string) get_user_meta( $uid, 'schedules_discipline', true );
			$title_id = (int) get_user_meta( $uid, 'schedules_title', true );
			$name     = trim( "{$u->first_name} {$u->last_name}" ) ?: $u->display_name;
			$members[$uid] = [
				'user_id'      => $uid,
				'display_name' => $name,
				'first_name'   => $u->first_name,
				'last_name'    => $u->last_name,
				'discipline'   => $disc,
				'title'        => $title_id && isset($_titles_map[$title_id]) ? $_titles_map[$title_id] : '',
				'type'         => 'ot',
				'ot_hours'     => $_ot_merged[$uid] ?? [],
				'timeoff'      => null,
				'assignments'  => [],
			];
		}
	}

	if ( empty( $members ) ) return [];

	// Approved time-off overlapping this date
	$ids_sql  = implode( ',', array_map( 'intval', array_keys( $members ) ) );
	$timeoffs = $wpdb->get_results( $wpdb->prepare(
		"SELECT user_id, type FROM " . schedules_table('timeoff') . "
		 WHERE user_id IN ($ids_sql)
		   AND status    = 'approved'
		   AND start_date <= %s
		   AND end_date   >= %s",
		$date, $date
	), ARRAY_A ) ?: [];
	foreach ( $timeoffs as $to ) {
		$uid = (int) $to['user_id'];
		if ( isset( $members[$uid] ) ) {
			$members[$uid]['timeoff'] = strtolower( $to['type'] );
		}
	}

	// Cross-reference duty assignments
	foreach ( $assignments as $a ) {
		$uid    = (int) $a['user_id'];
		$pos_id = (int) $a['position_id'];
		if ( ! isset( $members[$uid] ) ) continue;
		$members[$uid]['assignments'][] = [
			'position'   => $pos_map[$pos_id] ?? '',
			'start_time' => substr( $a['start_time'], 0, 5 ),
			'end_time'   => substr( $a['end_time'],   0, 5 ),
		];
	}

	usort( $members, fn( $a, $b ) => strcmp( $a['display_name'], $b['display_name'] ) );

	return array_values( $members );
}


/*--------------------------------------------------------------
# Claims Engine
--------------------------------------------------------------*/

/**
 * Claims a block (or multiple consecutive blocks if min_claim_hours > 1) for a user.
 * Returns ['success' => bool, 'message' => string, 'remaining' => int, 'claimed_blocks' => int[]]
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

	$day_id       = (int) $block['day_id'];
	$max_capacity = (int) $block['max_capacity'];
	$floor_count  = (int) $block['floor_count'];

	$adj = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE(SUM(adjustment), 0) FROM " . schedules_table('adjustments') . " WHERE day_id = %d",
			$day_id
		)
	);

	// Determine blocks to claim (min consecutive hours)
	$min_hours = (int) apply_filters( 'schedules_min_claim_hours', max( 1, (int) schedules_get_config( 'ot_min_claim_hours', '0' ) ) );

	// Get all blocks for this day, ordered by start_hour
	$all_blocks = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT b.id, b.start_hour, b.end_hour, b.discipline_filter,
			        (SELECT COUNT(*) FROM " . schedules_table('claims') . " c WHERE c.block_id = b.id) AS claims_count,
			        (SELECT COUNT(*) FROM " . schedules_table('claims') . " c WHERE c.block_id = b.id AND c.user_id = %d) AS user_claimed
			 FROM " . schedules_table('blocks') . " b
			 WHERE b.day_id = %d
			 ORDER BY b.start_hour ASC",
			$user_id,
			$day_id
		),
		ARRAY_A
	);

	// Build a map and find the clicked block's position
	$block_map = [];
	$click_idx = -1;
	foreach ( $all_blocks as $i => $b ) {
		$block_map[$i] = $b;
		if ( (int) $b['id'] === $block_id ) $click_idx = $i;
	}

	if ( $click_idx === -1 ) {
		return [ 'success' => false, 'message' => 'Block not found.', 'remaining' => 0 ];
	}

	// Count how many consecutive blocks the user already has adjacent to (and including) the clicked block
	// Walk backward from click to find the start of the chain
	$chain_start = $click_idx;
	while ( $chain_start > 0 && (int) $block_map[ $chain_start - 1 ]['user_claimed'] > 0 ) {
		$chain_start--;
	}
	// Walk forward from click to find existing claimed chain end
	$chain_end = $click_idx;
	while ( $chain_end < count($block_map) - 1 && (int) $block_map[ $chain_end + 1 ]['user_claimed'] > 0 ) {
		$chain_end++;
	}

	$existing_chain_length = $chain_end - $chain_start + 1; // includes the clicked block

	// Determine which unclaimed blocks to claim (starting from clicked, extending forward)
	$to_claim = [];
	if ( $existing_chain_length >= $min_hours ) {
		// Already meets minimum with this addition — just claim the clicked block
		$to_claim[] = $click_idx;
	} else {
		// Need more blocks — collect unclaimed blocks from click forward
		$needed = $min_hours - $existing_chain_length + 1; // +1 because clicked block is unclaimed
		for ( $i = $click_idx; $i < count($block_map) && count($to_claim) < $needed; $i++ ) {
			if ( (int) $block_map[$i]['user_claimed'] === 0 ) {
				$to_claim[] = $i;
			} else {
				break; // gap in chain — stop
			}
		}

		if ( count($to_claim) + ($existing_chain_length - 1) < $min_hours ) {
			return [ 'success' => false, 'message' => 'Not enough consecutive available blocks to meet the ' . $min_hours . '-hour minimum.', 'remaining' => 0 ];
		}
	}

	// Validate and claim each block
	$claimed_ids = [];
	$last_remaining = 0;

	foreach ( $to_claim as $idx ) {
		$b = $block_map[$idx];

		// Already claimed by this user?
		if ( (int) $b['user_claimed'] > 0 ) continue;

		// Discipline filter
		if ( ! empty($b['discipline_filter']) ) {
			$user_discipline = schedules_get_user_discipline( $user_id );
			if ( $user_discipline !== $b['discipline_filter'] ) {
				return [ 'success' => false, 'message' => 'Block ' . schedules_format_block_time((int)$b['start_hour'], (int)$b['end_hour']) . ' is restricted to a specific discipline.', 'remaining' => 0 ];
			}
		}

		// Availability
		$b_available = $max_capacity - $floor_count + $adj - (int) $b['claims_count'];
		if ( $b_available <= 0 ) {
			return [ 'success' => false, 'message' => 'Block ' . schedules_format_block_time((int)$b['start_hour'], (int)$b['end_hour']) . ' is full.', 'remaining' => 0 ];
		}

		$wpdb->insert(
			schedules_table('claims'),
			[
				'block_id'   => (int) $b['id'],
				'user_id'    => $user_id,
				'claimed_at' => current_time('mysql'),
			],
			[ '%d', '%d', '%s' ]
		);

		$claimed_ids[] = (int) $b['id'];
		$last_remaining = max( 0, $b_available - 1 );
	}

	return [
		'success'        => true,
		'message'        => count($claimed_ids) . ' block(s) claimed.',
		'remaining'      => $last_remaining,
		'claimed_blocks' => $claimed_ids,
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

	$name_format = get_user_meta( get_current_user_id(), 'schedules_name_format', true ) ?: 'first_last';

	$grouped = [];
	foreach ( $rows as $row ) {
		$discipline = get_user_meta( (int) $row['user_id'], 'schedules_discipline', true );
		$row['discipline'] = $discipline ? $discipline : '';
		$u = get_userdata( (int) $row['user_id'] );
		if ( $u && $u->first_name && $u->last_name ) {
			$row['display_name'] = $name_format === 'last_first'
				? $u->last_name . ', ' . $u->first_name
				: $u->first_name . ' ' . $u->last_name;
		}
		$grouped[ $row['shift_letter'] ][] = $row;
	}

	// Merge consecutive 1-hour blocks by the same user into single ranges
	foreach ( $grouped as $shift => &$claims ) {
		$by_user = [];
		foreach ( $claims as $c ) {
			$by_user[ $c['user_id'] ][] = $c;
		}
		$merged = [];
		foreach ( $by_user as $uid => $user_claims ) {
			usort( $user_claims, fn( $a, $b ) => (int) $a['start_hour'] - (int) $b['start_hour'] );
			$cur = null;
			foreach ( $user_claims as $c ) {
				if ( $cur && (int) $c['start_hour'] === (int) $cur['_end_hour'] ) {
					$cur['_end_hour'] = (int) $c['end_hour'];
					$cur['time_range'] = schedules_format_block_time( (int) $cur['start_hour'], (int) $c['end_hour'] );
				} else {
					if ( $cur ) $merged[] = $cur;
					$cur = $c;
					$cur['_end_hour'] = (int) $c['end_hour'];
					$cur['time_range'] = schedules_format_block_time( (int) $c['start_hour'], (int) $c['end_hour'] );
				}
			}
			if ( $cur ) $merged[] = $cur;
		}
		usort( $merged, fn( $a, $b ) => (int) $a['start_hour'] - (int) $b['start_hour'] );
		$claims = $merged;
	}
	unset( $claims );

	return $grouped;
}

/**
 * Returns 0 (week 1) or 1 (week 2) for a given date within a biweekly rotation.
 * Uses the Monday of each week relative to the anchor Monday.
 */
function schedules_get_cycle_week( string $date, string $anchor ): int {
	$date_ts   = strtotime( $date );
	$anchor_ts = strtotime( $anchor );
	// Walk anchor back to its Sunday (date('w'): 0=Sun … 6=Sat)
	$anchor_w      = (int) date( 'w', $anchor_ts );
	$anchor_sunday = $anchor_ts - ( $anchor_w * DAY_IN_SECONDS );
	// Walk date back to its Sunday
	$date_w        = (int) date( 'w', $date_ts );
	$date_sunday   = $date_ts   - ( $date_w * DAY_IN_SECONDS );
	// Full weeks between the two Sundays
	$weeks = (int) round( ( $date_sunday - $anchor_sunday ) / ( 7 * DAY_IN_SECONDS ) );
	return ( ( $weeks % 2 ) + 2 ) % 2; // 0 or 1, handles negative
}

/**
 * Sanitizes and returns a custom_schedule array from POST/data.
 * Expects data['custom_day'][N], ['custom_start'][N], ['custom_end'][N] where N is 0–6 (Sun–Sat).
 */
function schedules_sanitize_custom_schedule( array $data ): array {
	$schedule = [];
	$days = $data['custom_day'] ?? [];
	if ( ! is_array( $days ) ) return $schedule;
	for ( $dow = 0; $dow < 7; $dow++ ) {
		if ( ! empty( $days[ $dow ] ) ) {
			$start = min( 23, max( 0, (int) ($data['custom_start'][$dow] ?? 0) ) );
			$end   = min( 23, max( 0, (int) ($data['custom_end'][$dow]   ?? 0) ) );
			$schedule[ $dow ] = [ 'start' => $start, 'end' => $end ];
		}
	}
	return $schedule;
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
	$priority    = sanitize_text_field( $data['priority'] ?? '' );
	$pay_rate    = (float) ($data['pay_rate'] ?? 0);
	$member_role = sanitize_text_field( $data['member_role'] ?? '' );
	if ( ! $member_role ) {
		$member_role = ! empty( $data['is_supervisor'] ) ? 'supervisor' : 'member';
	}

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
	$role = $member_role === 'admin' ? 'schedules_admin' : ( $member_role === 'supervisor' ? 'schedules_supervisor' : 'schedules_member' );
	$user->set_role( $role );

	wp_update_user([
		'ID'           => $user_id,
		'first_name'   => $first_name,
		'last_name'    => $last_name,
		'display_name' => trim("{$first_name} {$last_name}"),
	]);

	$title_id = (int) ($data['title_id'] ?? 0);

	$manual_priority = in_array( $priority, [ '1', '5' ], true ) ? $priority : '';

	update_user_meta( $user_id, 'schedules_shift',      $shift );
	update_user_meta( $user_id, 'schedules_discipline', $discipline );
	update_user_meta( $user_id, 'schedules_priority',   $manual_priority );
	update_user_meta( $user_id, 'schedules_pay_rate',   $pay_rate );
	update_user_meta( $user_id, 'schedules_title',      $title_id );

	$schedule_type   = sanitize_text_field( $data['schedule_type'] ?? 'shift' );
	$custom_schedule = schedules_sanitize_custom_schedule( $data );
	update_user_meta( $user_id, 'schedules_schedule_type',   $schedule_type );
	update_user_meta( $user_id, 'schedules_custom_schedule', $custom_schedule );

	schedules_recalculate_priorities();

	return [ 'success' => true, 'message' => 'Member created.', 'user_id' => $user_id ];
}

/**
 * Returns all schedules members and supervisors with their meta.
 */
function schedules_get_all_members(): array {
	$users = get_users([
		'role__in' => [ 'schedules_member', 'schedules_supervisor', 'schedules_admin' ],
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
			'role'         => in_array('schedules_admin', (array)$user->roles) ? 'admin' : ( in_array('schedules_supervisor', (array)$user->roles) ? 'supervisor' : 'member' ),
			'shift'        => get_user_meta( $user->ID, 'schedules_shift',      true ),
			'discipline'   => get_user_meta( $user->ID, 'schedules_discipline', true ),
			'priority'     => get_user_meta( $user->ID, 'schedules_priority',   true ),
			'pay_rate'        => (float) get_user_meta( $user->ID, 'schedules_pay_rate',        true ),
			'title_id'        => (int) get_user_meta( $user->ID, 'schedules_title',           true ),
			'schedule_type'   => get_user_meta( $user->ID, 'schedules_schedule_type',   true ) ?: 'shift',
			'custom_schedule' => get_user_meta( $user->ID, 'schedules_custom_schedule', true ) ?: [],
		];
	}

	return $members;
}

/**
 * Returns all deactivated members (subscribers with schedules_deactivated_at meta).
 */
function schedules_get_deactivated_members(): array {
	$users = get_users([
		'role'       => 'subscriber',
		'meta_key'   => 'schedules_deactivated_at',
		'meta_compare' => 'EXISTS',
		'orderby'    => 'display_name',
		'order'      => 'ASC',
	]);

	$members = [];
	foreach ( $users as $user ) {
		$members[] = [
			'user_id'        => $user->ID,
			'display_name'   => $user->display_name,
			'first_name'     => $user->first_name,
			'last_name'      => $user->last_name,
			'badge_number'   => $user->user_login,
			'email'          => $user->user_email,
			'former_role'    => get_user_meta( $user->ID, 'schedules_former_role',   true ),
			'deactivated_at' => get_user_meta( $user->ID, 'schedules_deactivated_at', true ),
			'shift'          => get_user_meta( $user->ID, 'schedules_shift',          true ),
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

// --- Login ---
add_action( 'wp_ajax_nopriv_schedules_login', 'schedules_ajax_login' );
add_action( 'wp_ajax_schedules_login',        'schedules_ajax_login' );
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
	$allowed = [ 'schedules_member', 'schedules_supervisor', 'schedules_admin' ];
	if ( ! array_intersect( $allowed, $roles ) ) {
		wp_logout();
		wp_send_json_error( [ 'message' => 'You do not have access to this system.' ] );
	}

	$redirect = ( in_array('schedules_supervisor', $roles, true) || in_array('schedules_admin', $roles, true) )
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

// --- Unclaim Block (undo within grace period, cascades to maintain minimum) ---
add_action( 'wp_ajax_schedules_unclaim_block', 'schedules_ajax_unclaim_block' );
function schedules_ajax_unclaim_block(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$block_id = (int) ($_POST['block_id'] ?? 0);
	$user_id  = get_current_user_id();

	if ( ! $block_id || ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Invalid request.' ] );
	}

	// Verify claim exists and is within grace period
	$claim = $wpdb->get_row( $wpdb->prepare(
		"SELECT c.id, c.claimed_at, b.day_id FROM " . schedules_table('claims') . " c
		 JOIN " . schedules_table('blocks') . " b ON b.id = c.block_id
		 WHERE c.block_id = %d AND c.user_id = %d",
		$block_id,
		$user_id
	), ARRAY_A );

	if ( ! $claim ) {
		wp_send_json_error( [ 'message' => 'Claim not found.' ] );
	}

	$grace_minutes = (int) apply_filters( 'schedules_unclaim_grace_minutes', 5 );
	$claimed_at    = strtotime( $claim['claimed_at'] );
	$deadline      = $claimed_at + ( $grace_minutes * 60 );
	$now           = current_time('timestamp');

	if ( $now > $deadline ) {
		wp_send_json_error( [ 'message' => 'The undo window has expired.' ] );
	}

	$day_id    = (int) $claim['day_id'];
	$min_hours = (int) apply_filters( 'schedules_min_claim_hours', max( 1, (int) schedules_get_config( 'ot_min_claim_hours', '0' ) ) );

	// Get all blocks for this day ordered by start_hour, with user's claim info
	$all_blocks = $wpdb->get_results( $wpdb->prepare(
		"SELECT b.id, b.start_hour,
		        c.id AS claim_id, c.claimed_at AS claim_time
		 FROM " . schedules_table('blocks') . " b
		 LEFT JOIN " . schedules_table('claims') . " c ON c.block_id = b.id AND c.user_id = %d
		 WHERE b.day_id = %d
		 ORDER BY b.start_hour ASC",
		$user_id,
		$day_id
	), ARRAY_A );

	// Simulate removing the clicked block, then find chains < min_hours to also remove
	$to_remove = [ $block_id ];

	if ( $min_hours > 1 ) {
		// Build list of remaining claimed block IDs after removing the clicked one
		$remaining = [];
		foreach ( $all_blocks as $b ) {
			if ( $b['claim_id'] && (int) $b['id'] !== $block_id ) {
				$remaining[] = $b;
			}
		}

		// Find consecutive chains in the remaining blocks
		$chains = [];
		$chain  = [];
		$prev_hour = null;
		foreach ( $remaining as $b ) {
			$h = (int) $b['start_hour'];
			if ( $prev_hour !== null && $h !== ( $prev_hour + 1 ) % 24 ) {
				if ( ! empty($chain) ) $chains[] = $chain;
				$chain = [];
			}
			$chain[] = $b;
			$prev_hour = $h;
		}
		if ( ! empty($chain) ) $chains[] = $chain;

		// Any chain shorter than min_hours must also be removed
		foreach ( $chains as $ch ) {
			if ( count($ch) < $min_hours ) {
				foreach ( $ch as $b ) {
					$to_remove[] = (int) $b['id'];
				}
			}
		}

		$to_remove = array_unique( $to_remove );
	}

	// Verify all blocks being removed are within their grace period
	$removed = [];
	foreach ( $all_blocks as $b ) {
		if ( ! in_array( (int) $b['id'], $to_remove, true ) ) continue;
		if ( ! $b['claim_id'] ) continue;
		$b_deadline = strtotime( $b['claim_time'] ) + ( $grace_minutes * 60 );
		if ( $now > $b_deadline ) {
			wp_send_json_error( [ 'message' => 'Some blocks in this chain have passed the undo window.' ] );
		}
	}

	// Delete all claims
	foreach ( $all_blocks as $b ) {
		if ( ! in_array( (int) $b['id'], $to_remove, true ) ) continue;
		if ( ! $b['claim_id'] ) continue;
		$wpdb->delete( schedules_table('claims'), [ 'id' => (int) $b['claim_id'] ], [ '%d' ] );
		$removed[] = (int) $b['id'];
	}

	wp_send_json_success( [ 'message' => count($removed) . ' block(s) undone.', 'removed_blocks' => $removed ] );
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
	$shift       = sanitize_text_field( $_POST['shift'] ?? '' );
	$discipline  = sanitize_text_field( $_POST['discipline'] ?? '' );
	$priority    = sanitize_text_field( $_POST['priority'] ?? '' );
	$member_role = sanitize_text_field( $_POST['member_role'] ?? '' );
	if ( ! $member_role ) {
		$member_role = ! empty( $_POST['is_supervisor'] ) ? 'supervisor' : 'member';
	}
	$new_pass    = $_POST['member_pass'] ?? $_POST['password'] ?? '';

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

	$title_id = (int) ($_POST['title_id'] ?? 0);
	$pay_rate = (float) ($_POST['pay_rate'] ?? 0);

	update_user_meta( $user_id, 'schedules_shift',    $shift );
	update_user_meta( $user_id, 'schedules_title',    $title_id );
	update_user_meta( $user_id, 'schedules_pay_rate', $pay_rate );

	$schedule_type   = sanitize_text_field( $_POST['schedule_type'] ?? 'shift' );
	$custom_schedule = schedules_sanitize_custom_schedule( $_POST );
	update_user_meta( $user_id, 'schedules_schedule_type',   $schedule_type );
	update_user_meta( $user_id, 'schedules_custom_schedule', $custom_schedule );

	if ( $discipline ) update_user_meta( $user_id, 'schedules_discipline', $discipline );
	// Only persist manual tiers from the form; 2/3/4 are auto-calculated below
	if ( in_array( $priority, [ '1', '5' ], true ) ) {
		update_user_meta( $user_id, 'schedules_priority', $priority );
	} elseif ( $priority === '' ) {
		// Admin moved member back to auto — clear any manual tier so recalc takes over
		$current = get_user_meta( $user_id, 'schedules_priority', true );
		if ( in_array( $current, [ '1', '5' ], true ) ) {
			update_user_meta( $user_id, 'schedules_priority', '' );
		}
	}

	$user = new WP_User( $user_id );
	$new_role = $member_role === 'admin' ? 'schedules_admin' : ( $member_role === 'supervisor' ? 'schedules_supervisor' : 'schedules_member' );
	$user->set_role( $new_role );

	schedules_recalculate_priorities();

	wp_send_json_success( [ 'message' => 'Member updated.' ] );
}

// --- Deactivate Member (supervisor only) ---
add_action( 'wp_ajax_schedules_deactivate_member', 'schedules_ajax_deactivate_member' );
function schedules_ajax_deactivate_member(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$user_id = (int) ($_POST['user_id'] ?? 0);

	if ( ! $user_id || ! get_userdata($user_id) ) {
		wp_send_json_error( [ 'message' => 'User not found.' ] );
	}

	$user         = new WP_User( $user_id );
	$former_roles = $user->roles;
	$user->set_role('subscriber');
	update_user_meta( $user_id, 'schedules_deactivated_at', current_time('mysql') );
	update_user_meta( $user_id, 'schedules_former_role', $former_roles[0] ?? '' );

	wp_send_json_success( [ 'message' => 'Member deactivated.' ] );
}

// --- Purge Member (admin only — permanent delete) ---
add_action( 'wp_ajax_schedules_purge_member', 'schedules_ajax_purge_member' );
function schedules_ajax_purge_member(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$user_id = (int) ($_POST['user_id'] ?? 0);

	if ( ! $user_id || ! get_userdata($user_id) ) {
		wp_send_json_error( [ 'message' => 'User not found.' ] );
	}

	// Hard-delete all duty assignments for this member
	$wpdb->delete( schedules_table('duty'), [ 'user_id' => $user_id ], [ '%d' ] );

	// Delete the WP user account
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user_id );

	wp_send_json_success( [ 'message' => 'Member and all associated records permanently deleted.' ] );
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

// --- Update Shift Floor Count (admin only) ---
add_action( 'wp_ajax_schedules_update_shift_floor_count', 'schedules_ajax_update_shift_floor_count' );
function schedules_ajax_update_shift_floor_count(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('admin_schedules') ) {
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

// --- Save All Shift Settings at once (admin only) ---
add_action( 'wp_ajax_schedules_save_shift_settings', 'schedules_ajax_save_shift_settings' );
function schedules_ajax_save_shift_settings(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	// Save cycle anchor date
	$anchor = sanitize_text_field( $_POST['cycle_anchor'] ?? '' );
	if ( $anchor && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $anchor ) ) {
		update_option( 'schedules_cycle_anchor', $anchor );
	}

	// Shift data arrives as a JSON string for reliability
	$shift_data = json_decode( stripslashes( $_POST['shifts_json'] ?? '{}' ), true );
	if ( ! is_array( $shift_data ) || empty( $shift_data ) ) {
		wp_send_json_error( [ 'message' => 'No shift data provided.' ] );
	}

	// Check which columns exist so the UPDATE doesn't fail on a missing column
	$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('shifts') );
	$has_end_hour = in_array( 'end_hour', $cols, true );
	$has_week2    = in_array( 'work_days_week2', $cols, true );

	$errors = [];

	foreach ( $shift_data as $shift_letter => $data ) {
		$shift_letter = strtoupper( sanitize_text_field( $shift_letter ) );
		if ( ! in_array( $shift_letter, ['A','B','C','D'], true ) ) continue;

		$floor_count  = max( 0, (int) ( $data['member_count'] ?? 0 ) );
		$max_capacity = max( 1, (int) ( $data['max_capacity'] ?? 14 ) );
		$start_hour   = min( 23, max( 0, (int) ( $data['start_hour'] ?? 6 ) ) );
		$end_hour     = min( 23, max( 0, (int) ( $data['end_hour']   ?? 18 ) ) );

		// Work days (week 1)
		$work_days = '';
		if ( ! empty( $data['days_week1'] ) ) {
			$raw   = array_map( 'intval', (array) $data['days_week1'] );
			$valid = [];
			foreach ( $raw as $d ) {
				if ( $d >= 0 && $d <= 6 ) $valid[] = $d;
			}
			$valid = array_values( array_unique( $valid ) );
			sort( $valid );
			$work_days = implode( ',', $valid );
		}

		// Work days (week 2)
		$work_days_w2 = '';
		if ( ! empty( $data['days_week2'] ) ) {
			$raw   = array_map( 'intval', (array) $data['days_week2'] );
			$valid = [];
			foreach ( $raw as $d ) {
				if ( $d >= 0 && $d <= 6 ) $valid[] = $d;
			}
			$valid = array_values( array_unique( $valid ) );
			sort( $valid );
			$work_days_w2 = implode( ',', $valid );
		}

		// Build UPDATE data based on which columns exist
		$update_data   = [
			'start_hour'   => $start_hour,
			'work_days'    => $work_days,
			'floor_count'  => $floor_count,
			'max_capacity' => $max_capacity,
		];
		$update_format = [ '%d', '%s', '%d', '%d' ];

		if ( $has_end_hour ) {
			$update_data['end_hour'] = $end_hour;
			$update_format[]         = '%d';
		}
		if ( $has_week2 ) {
			$update_data['work_days_week2'] = $work_days_w2;
			$update_format[]                = '%s';
		}

		$result = $wpdb->update(
			schedules_table('shifts'),
			$update_data,
			[ 'shift_letter' => $shift_letter ],
			$update_format,
			[ '%s' ]
		);

		if ( $result === false ) {
			$errors[] = "Shift {$shift_letter}: " . $wpdb->last_error;
		}

		// Propagate floor_count and max_capacity to unarchived days
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE " . schedules_table('days') . "
				 SET floor_count = %d, max_capacity = %d
				 WHERE shift_letter = %s AND is_archived = 0",
				$floor_count,
				$max_capacity,
				$shift_letter
			)
		);
	}

	if ( ! empty( $errors ) ) {
		wp_send_json_error( [ 'message' => implode( ' | ', $errors ) ] );
	}

	// Regenerate future OT days to reflect new shift settings
	schedules_regenerate_future_days();

	wp_send_json_success( [ 'message' => 'Shift settings saved.' ] );
}

// --- Get Duty Data (supervisor) ---
add_action( 'wp_ajax_schedules_get_duty_data', 'schedules_ajax_get_duty_data' );
function schedules_ajax_get_duty_data(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$date         = sanitize_text_field( $_POST['date']  ?? '' );
	$shift_letter = strtoupper( sanitize_text_field( $_POST['shift'] ?? '' ) );

	if ( ! $date || ! in_array( $shift_letter, ['A','B','C','D'], true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid parameters.' ] );
	}

	$day_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . schedules_table('days') . " WHERE schedule_date = %s AND shift_letter = %s",
		$date, $shift_letter
	) );

	$positions = $wpdb->get_results(
		"SELECT p.id, p.name, p.required_discipline_id, p.display_order,
		        LOWER(REPLACE(IFNULL(d.name,''), ' ', '-')) AS discipline_slug
		 FROM " . schedules_table('positions') . " p
		 LEFT JOIN " . schedules_table('disciplines') . " d ON d.id = p.required_discipline_id AND p.required_discipline_id > 0
		 WHERE p.is_active = 1
		 ORDER BY p.display_order ASC, p.name ASC",
		ARRAY_A
	) ?: [];

	$shift_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT start_hour FROM " . schedules_table('shifts') . " WHERE shift_letter = %s",
		$shift_letter
	), ARRAY_A );
	$shift_start_hour = $shift_row ? (int)$shift_row['start_hour'] : 6;

	$members     = schedules_get_available_members_for_day( $date, $shift_letter );
	$assignments = $day_id ? schedules_get_duty_for_day( $day_id ) : [];
	$roster      = schedules_get_roster_for_day( $date, $shift_letter, $day_id, $assignments, $positions );

	wp_send_json_success( [
		'day_id'           => $day_id,
		'shift_letter'     => $shift_letter,
		'date'             => $date,
		'shift_start_hour' => $shift_start_hour,
		'positions'        => $positions,
		'members'          => $members,
		'assignments'      => $assignments,
		'roster'           => $roster,
		'is_locked'        => schedules_is_shift_ended( $date, $shift_letter ),
	] );
}

// --- Add Duty Assignment (supervisor) ---
add_action( 'wp_ajax_schedules_add_duty_assignment', 'schedules_ajax_add_duty_assignment' );
function schedules_ajax_add_duty_assignment(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$day_id      = (int) ($_POST['day_id'] ?? 0);
	$date        = sanitize_text_field( $_POST['date']  ?? '' );
	$shift       = strtoupper( sanitize_text_field( $_POST['shift'] ?? '' ) );
	$position_id = (int) ($_POST['position_id'] ?? 0);
	$user_id     = (int) ($_POST['user_id']     ?? 0);
	$start_time  = sanitize_text_field( $_POST['start_time'] ?? '' );
	$end_time    = sanitize_text_field( $_POST['end_time']   ?? '' );

	if ( ! $position_id || ! $user_id || ! $start_time || ! $end_time ) {
		wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
	}

	if ( $date && $shift && schedules_is_shift_ended( $date, $shift ) ) {
		wp_send_json_error( [ 'message' => 'This shift has ended. Duty assignments are locked.' ] );
	}

	// Normalize times to HH:MM:SS
	$start_time = strlen($start_time) === 5 ? $start_time . ':00' : $start_time;
	$end_time   = strlen($end_time)   === 5 ? $end_time   . ':00' : $end_time;

	// Get or create day record
	if ( ! $day_id ) {
		if ( ! $date || ! in_array( $shift, ['A','B','C','D'], true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid date or shift.' ] );
		}
		$day_id = schedules_get_or_create_day_id( $date, $shift );
		if ( ! $day_id ) {
			wp_send_json_error( [ 'message' => 'Could not create shift record for this date.' ] );
		}
	}

	$assigned_by = get_current_user_id();
	$now         = current_time('mysql');

	$wpdb->insert(
		schedules_table('duty'),
		[
			'day_id'      => $day_id,
			'position_id' => $position_id,
			'user_id'     => $user_id,
			'start_time'  => $start_time,
			'end_time'    => $end_time,
			'assigned_by' => $assigned_by,
			'assigned_at' => $now,
		],
		[ '%d', '%d', '%d', '%s', '%s', '%d', '%s' ]
	);

	$new_id = (int) $wpdb->insert_id;

	$u = get_userdata( $user_id );
	$name = $u ? ( trim("{$u->first_name} {$u->last_name}") ?: $u->display_name ) : '';

	wp_send_json_success( [
		'assignment' => [
			'id'          => $new_id,
			'day_id'      => $day_id,
			'position_id' => $position_id,
			'user_id'     => $user_id,
			'start_time'  => $start_time,
			'end_time'    => $end_time,
			'display_name'=> $name,
		],
	] );
}

// --- Remove Duty Assignment (supervisor) ---
add_action( 'wp_ajax_schedules_remove_duty_assignment', 'schedules_ajax_remove_duty_assignment' );
function schedules_ajax_remove_duty_assignment(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$id = (int) ($_POST['assignment_id'] ?? 0);
	if ( ! $id ) {
		wp_send_json_error( [ 'message' => 'Invalid assignment.' ] );
	}

	// Check if the shift has ended
	$assignment_day = $wpdb->get_row( $wpdb->prepare(
		"SELECT d.schedule_date, d.shift_letter FROM " . schedules_table('duty') . " s
		 JOIN " . schedules_table('days') . " d ON d.id = s.day_id
		 WHERE s.id = %d",
		$id
	), ARRAY_A );

	if ( $assignment_day && schedules_is_shift_ended( $assignment_day['schedule_date'], $assignment_day['shift_letter'] ) ) {
		wp_send_json_error( [ 'message' => 'This shift has ended. Duty assignments are locked.' ] );
	}

	$voided_by = get_current_user_id();
	$wpdb->update(
		schedules_table('duty'),
		[
			'is_voided' => 1,
			'voided_by' => $voided_by,
			'voided_at' => current_time('mysql'),
		],
		[ 'id' => $id ],
		[ '%d', '%d', '%s' ],
		[ '%d' ]
	);

	wp_send_json_success( [ 'message' => 'Assignment removed.' ] );
}

// --- Purge Duty Assignment (admin only — hard delete for data entry errors) ---
add_action( 'wp_ajax_schedules_purge_duty_assignment', 'schedules_ajax_purge_duty_assignment' );
function schedules_ajax_purge_duty_assignment(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$id = (int) ($_POST['assignment_id'] ?? 0);
	if ( ! $id ) {
		wp_send_json_error( [ 'message' => 'Invalid assignment.' ] );
	}

	$wpdb->delete( schedules_table('duty'), [ 'id' => $id ], [ '%d' ] );

	wp_send_json_success( [ 'message' => 'Assignment permanently deleted.' ] );
}

// --- Update Duty Assignment (supervisor) ---
add_action( 'wp_ajax_schedules_update_duty_assignment', 'schedules_ajax_update_duty_assignment' );
function schedules_ajax_update_duty_assignment(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$id         = (int) ($_POST['assignment_id'] ?? 0);
	$user_id    = (int) ($_POST['user_id']       ?? 0);
	$start_time = sanitize_text_field( $_POST['start_time'] ?? '' );
	$end_time   = sanitize_text_field( $_POST['end_time']   ?? '' );

	if ( ! $id || ! $user_id || ! $start_time || ! $end_time ) {
		wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
	}

	$start_time = strlen($start_time) === 5 ? $start_time . ':00' : $start_time;
	$end_time   = strlen($end_time)   === 5 ? $end_time   . ':00' : $end_time;

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('duty') . " WHERE id = %d",
		$id
	), ARRAY_A );

	if ( ! $row ) {
		wp_send_json_error( [ 'message' => 'Assignment not found.' ] );
	}

	$wpdb->update(
		schedules_table('duty'),
		[ 'user_id' => $user_id, 'start_time' => $start_time, 'end_time' => $end_time ],
		[ 'id' => $id ],
		[ '%d', '%s', '%s' ],
		[ '%d' ]
	);

	$u    = get_userdata( $user_id );
	$name = $u ? ( trim("{$u->first_name} {$u->last_name}") ?: $u->display_name ) : '';

	wp_send_json_success( [
		'assignment' => [
			'id'           => $id,
			'day_id'       => (int) $row['day_id'],
			'position_id'  => (int) $row['position_id'],
			'user_id'      => $user_id,
			'start_time'   => $start_time,
			'end_time'     => $end_time,
			'display_name' => $name,
		],
	] );
}

// --- Get Member Duty (any logged-in user — read-only) ---
add_action( 'wp_ajax_schedules_get_member_duty', 'schedules_ajax_get_member_duty' );
function schedules_ajax_get_member_duty(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( [ 'message' => 'Not logged in.' ] );
	}

	global $wpdb;

	$user_id = get_current_user_id();
	$date    = sanitize_text_field( $_POST['date'] ?? date('Y-m-d') );
	$shift   = schedules_get_user_shift( $user_id );

	if ( ! $shift ) {
		wp_send_json_error( [ 'message' => 'No shift assigned.' ] );
	}

	$day_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . schedules_table('days') . " WHERE schedule_date = %s AND shift_letter = %s",
		$date, $shift
	) );

	if ( ! $day_id ) {
		wp_send_json_success( [ 'date' => $date, 'shift' => $shift, 'positions' => [], 'duty' => [] ] );
		return;
	}

	$positions = $wpdb->get_results(
		"SELECT id, name, display_order FROM " . schedules_table('positions') . "
		 WHERE is_active = 1 ORDER BY display_order ASC, name ASC",
		ARRAY_A
	) ?: [];

	$shift_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT start_hour FROM " . schedules_table('shifts') . " WHERE shift_letter = %s",
		$shift
	), ARRAY_A );
	$shift_start_hour = $shift_row ? (int)$shift_row['start_hour'] : 6;

	$assignments = schedules_get_duty_for_day( $day_id );

	wp_send_json_success( [
		'date'             => $date,
		'shift'            => $shift,
		'shift_start_hour' => $shift_start_hour,
		'positions'        => $positions,
		'assignments'      => $assignments,
	] );
}


// --- Get Config Data (admin only) ---
add_action( 'wp_ajax_schedules_save_user_settings', 'schedules_ajax_save_user_settings' );
function schedules_ajax_save_user_settings(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	$user_id     = get_current_user_id();
	$name_format  = sanitize_key( $_POST['name_format'] ?? 'first_last' );
	if ( ! in_array( $name_format, [ 'first_last', 'last_first' ], true ) ) $name_format = 'first_last';
	$members_view = sanitize_key( $_POST['members_view'] ?? 'row' );
	if ( ! in_array( $members_view, [ 'card', 'row' ], true ) ) $members_view = 'row';
	update_user_meta( $user_id, 'schedules_name_format', $name_format );
	update_user_meta( $user_id, 'schedules_members_view', $members_view );
	wp_send_json_success( [ 'message' => 'Preferences saved.' ] );
}

add_action( 'wp_ajax_schedules_save_app_settings', 'schedules_ajax_save_app_settings' );
function schedules_ajax_save_app_settings(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$increment = (int) ($_POST['duty_time_increment'] ?? 60);
	if ( ! in_array( $increment, [ 15, 30, 60 ], true ) ) $increment = 60;
	schedules_set_config( 'duty_time_increment', (string) $increment );

	$sup_can_claim = isset($_POST['supervisors_can_claim_ot']) && $_POST['supervisors_can_claim_ot'] === '1' ? '1' : '0';
	schedules_set_config( 'supervisors_can_claim_ot', $sup_can_claim );

	$min_ot_hours = max( 0, (int) ($_POST['ot_min_claim_hours'] ?? 0) );
	schedules_set_config( 'ot_min_claim_hours', (string) $min_ot_hours );

	$tier2_max = max( 0.0, (float) ($_POST['ot_priority_2_max'] ?? 0) );
	$tier3_max = max( 0.0, (float) ($_POST['ot_priority_3_max'] ?? 0) );
	schedules_set_config( 'ot_priority_2_max', number_format( $tier2_max, 2, '.', '' ) );
	schedules_set_config( 'ot_priority_3_max', number_format( $tier3_max, 2, '.', '' ) );

	// Immediately reshuffle with the new cutoffs
	schedules_recalculate_priorities();

	wp_send_json_success( [ 'message' => 'Settings saved.', 'supervisors_can_claim_ot' => $sup_can_claim ] );
}

add_action( 'wp_ajax_schedules_get_config_data', 'schedules_ajax_get_config_data' );
function schedules_ajax_get_config_data(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}
	wp_send_json_success( [
		'disciplines'              => schedules_get_disciplines( false ),
		'positions'                => schedules_get_positions( false ),
		'titles'                   => schedules_get_titles( false ),
		'duty_time_increment'      => schedules_get_config( 'duty_time_increment', '60' ),
		'supervisors_can_claim_ot' => schedules_get_config( 'supervisors_can_claim_ot', '0' ),
		'ot_min_claim_hours'       => schedules_get_config( 'ot_min_claim_hours', '0' ),
		'ot_priority_2_max'        => schedules_get_config( 'ot_priority_2_max', '0' ),
		'ot_priority_3_max'        => schedules_get_config( 'ot_priority_3_max', '0' ),
	] );
}

// --- Save Discipline (supervisor only) ---
add_action( 'wp_ajax_schedules_save_discipline', 'schedules_ajax_save_discipline' );
function schedules_ajax_save_discipline(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$id            = (int) ($_POST['id'] ?? 0);
	$name          = sanitize_text_field( $_POST['name'] ?? '' );
	$display_order = (int) ($_POST['display_order'] ?? 0);
	$is_active     = (int) (bool) ($_POST['is_active'] ?? 1);

	if ( empty($name) ) {
		wp_send_json_error( [ 'message' => 'Name is required.' ] );
	}

	if ( $id > 0 ) {
		$wpdb->update(
			schedules_table('disciplines'),
			[ 'name' => $name, 'is_active' => $is_active ],
			[ 'id'   => $id ],
			[ '%s', '%d' ],
			[ '%d' ]
		);
		wp_send_json_success( [ 'message' => 'Discipline updated.', 'id' => $id ] );
	} else {
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . schedules_table('disciplines') . " WHERE name = %s",
			$name
		) );
		if ( $exists ) {
			wp_send_json_error( [ 'message' => 'A discipline with that name already exists.' ] );
		}
		$max_order = (int) $wpdb->get_var( "SELECT MAX(display_order) FROM " . schedules_table('disciplines') );
		$wpdb->insert(
			schedules_table('disciplines'),
			[ 'name' => $name, 'display_order' => $max_order + 1, 'is_active' => 1 ],
			[ '%s', '%d', '%d' ]
		);
		wp_send_json_success( [ 'message' => 'Discipline added.', 'id' => $wpdb->insert_id ] );
	}
}

// --- Reorder Disciplines ---
add_action( 'wp_ajax_schedules_reorder_disciplines', 'schedules_ajax_reorder_disciplines' );
function schedules_ajax_reorder_disciplines(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}
	global $wpdb;
	$ids = array_values( array_filter( array_map( 'intval', explode( ',', $_POST['ids'] ?? '' ) ) ) );
	foreach ( $ids as $order => $id ) {
		$wpdb->update( schedules_table('disciplines'), [ 'display_order' => $order ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
	}
	wp_send_json_success();
}

// --- Delete Discipline (supervisor only — soft delete) ---
add_action( 'wp_ajax_schedules_delete_discipline', 'schedules_ajax_delete_discipline' );
function schedules_ajax_delete_discipline(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;
	$id = (int) ($_POST['id'] ?? 0);
	if ( ! $id ) {
		wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
	}

	$in_use = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . schedules_table('positions') . " WHERE required_discipline_id = %d AND is_active = 1",
		$id
	) );
	if ( $in_use > 0 ) {
		wp_send_json_error( [ 'message' => 'Cannot delete: one or more active positions require this discipline.' ] );
	}

	$wpdb->update(
		schedules_table('disciplines'),
		[ 'is_active' => 0 ],
		[ 'id'        => $id ],
		[ '%d' ],
		[ '%d' ]
	);
	wp_send_json_success( [ 'message' => 'Discipline removed.' ] );
}

// --- Save Position (supervisor only) ---
add_action( 'wp_ajax_schedules_save_position', 'schedules_ajax_save_position' );
function schedules_ajax_save_position(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$id            = (int) ($_POST['id'] ?? 0);
	$name          = sanitize_text_field( $_POST['name'] ?? '' );
	$disc_id       = (int) ($_POST['required_discipline_id'] ?? 0);
	$display_order = (int) ($_POST['display_order'] ?? 0);
	$is_active     = (int) (bool) ($_POST['is_active'] ?? 1);

	if ( empty($name) ) {
		wp_send_json_error( [ 'message' => 'Name is required.' ] );
	}

	if ( $id > 0 ) {
		$wpdb->update(
			schedules_table('positions'),
			[ 'name' => $name, 'required_discipline_id' => $disc_id, 'is_active' => $is_active ],
			[ 'id'   => $id ],
			[ '%s', '%d', '%d' ],
			[ '%d' ]
		);
		wp_send_json_success( [ 'message' => 'Position updated.', 'id' => $id ] );
	} else {
		$max_order = (int) $wpdb->get_var( "SELECT MAX(display_order) FROM " . schedules_table('positions') );
		$wpdb->insert(
			schedules_table('positions'),
			[ 'name' => $name, 'required_discipline_id' => $disc_id, 'display_order' => $max_order + 1, 'is_active' => 1 ],
			[ '%s', '%d', '%d', '%d' ]
		);
		wp_send_json_success( [ 'message' => 'Position added.', 'id' => $wpdb->insert_id ] );
	}
}

// --- Reorder Positions ---
add_action( 'wp_ajax_schedules_reorder_positions', 'schedules_ajax_reorder_positions' );
function schedules_ajax_reorder_positions(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}
	global $wpdb;
	$ids = array_values( array_filter( array_map( 'intval', explode( ',', $_POST['ids'] ?? '' ) ) ) );
	foreach ( $ids as $order => $id ) {
		$wpdb->update( schedules_table('positions'), [ 'display_order' => $order ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
	}
	wp_send_json_success();
}

// --- Delete Position (supervisor only — soft delete) ---
add_action( 'wp_ajax_schedules_delete_position', 'schedules_ajax_delete_position' );
function schedules_ajax_delete_position(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;
	$id = (int) ($_POST['id'] ?? 0);
	if ( ! $id ) {
		wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
	}

	$wpdb->update(
		schedules_table('positions'),
		[ 'is_active' => 0 ],
		[ 'id'        => $id ],
		[ '%d' ],
		[ '%d' ]
	);
	wp_send_json_success( [ 'message' => 'Position removed.' ] );
}

// --- Save Title (admin only) ---
add_action( 'wp_ajax_schedules_save_title', 'schedules_ajax_save_title' );
function schedules_ajax_save_title(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$id            = (int) ($_POST['id'] ?? 0);
	$name          = sanitize_text_field( $_POST['name'] ?? '' );
	$display_order = (int) ($_POST['display_order'] ?? 0);
	$is_active     = (int) (bool) ($_POST['is_active'] ?? 1);

	if ( empty($name) ) {
		wp_send_json_error( [ 'message' => 'Name is required.' ] );
	}

	if ( $id > 0 ) {
		$wpdb->update(
			schedules_table('titles'),
			[ 'name' => $name, 'is_active' => $is_active ],
			[ 'id'   => $id ],
			[ '%s', '%d' ],
			[ '%d' ]
		);
		wp_send_json_success( [ 'message' => 'Title updated.', 'id' => $id ] );
	} else {
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . schedules_table('titles') . " WHERE name = %s",
			$name
		) );
		if ( $exists ) {
			wp_send_json_error( [ 'message' => 'A title with that name already exists.' ] );
		}
		$max_order = (int) $wpdb->get_var( "SELECT MAX(display_order) FROM " . schedules_table('titles') );
		$wpdb->insert(
			schedules_table('titles'),
			[ 'name' => $name, 'display_order' => $max_order + 1, 'is_active' => 1 ],
			[ '%s', '%d', '%d' ]
		);
		wp_send_json_success( [ 'message' => 'Title added.', 'id' => $wpdb->insert_id ] );
	}
}

// --- Reorder Titles ---
add_action( 'wp_ajax_schedules_reorder_titles', 'schedules_ajax_reorder_titles' );
function schedules_ajax_reorder_titles(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}
	global $wpdb;
	$ids = array_values( array_filter( array_map( 'intval', explode( ',', $_POST['ids'] ?? '' ) ) ) );
	foreach ( $ids as $order => $id ) {
		$wpdb->update( schedules_table('titles'), [ 'display_order' => $order ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
	}
	wp_send_json_success();
}

// --- Delete Title (admin only — soft delete) ---
add_action( 'wp_ajax_schedules_delete_title', 'schedules_ajax_delete_title' );
function schedules_ajax_delete_title(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;
	$id = (int) ($_POST['id'] ?? 0);
	if ( ! $id ) {
		wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
	}

	$wpdb->update(
		schedules_table('titles'),
		[ 'is_active' => 0 ],
		[ 'id'        => $id ],
		[ '%d' ],
		[ '%d' ]
	);
	wp_send_json_success( [ 'message' => 'Title removed.' ] );
}


/*--------------------------------------------------------------
# Personal Schedule Calendar
--------------------------------------------------------------*/

// --- Get Notifications ---
add_action( 'wp_ajax_schedules_get_notifications', 'schedules_ajax_get_notifications' );
function schedules_ajax_get_notifications(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$user_id = get_current_user_id();

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('notifications') . "
		 WHERE user_id = %d
		 ORDER BY created_at DESC
		 LIMIT 50",
		$user_id
	), ARRAY_A );

	$notifications = [];
	foreach ( $rows as $r ) {
		$notifications[] = [
			'id'           => (int) $r['id'],
			'type'         => $r['type'],
			'related_id'   => (int) $r['related_id'],
			'related_type' => $r['related_type'],
			'message'      => $r['message'],
			'is_read'      => (int) $r['is_read'],
			'created_at'   => $r['created_at'],
		];
	}

	wp_send_json_success( [ 'notifications' => $notifications ] );
}

// --- Mark Notifications Read ---
add_action( 'wp_ajax_schedules_mark_notifications_read', 'schedules_ajax_mark_notifications_read' );
function schedules_ajax_mark_notifications_read(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$user_id = get_current_user_id();

	$wpdb->update(
		schedules_table('notifications'),
		[ 'is_read' => 1 ],
		[ 'user_id' => $user_id, 'is_read' => 0 ],
		[ '%d' ],
		[ '%d', '%d' ]
	);

	wp_send_json_success();
}

// --- Approve / Deny Time Off (from notification) ---
add_action( 'wp_ajax_schedules_review_timeoff', 'schedules_ajax_review_timeoff' );
function schedules_ajax_review_timeoff(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_schedules' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$timeoff_id = (int) ( $_POST['timeoff_id'] ?? 0 );
	$decision   = sanitize_text_field( $_POST['decision'] ?? '' );

	if ( ! $timeoff_id || ! in_array( $decision, [ 'approved', 'denied' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid request.' ] );
	}

	$request = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('timeoff') . " WHERE id = %d",
		$timeoff_id
	), ARRAY_A );

	if ( ! $request ) {
		wp_send_json_error( [ 'message' => 'Request not found.' ] );
	}

	if ( $request['status'] !== 'pending' ) {
		wp_send_json_error( [ 'message' => 'Request has already been ' . $request['status'] . '.' ] );
	}

	$current_user_id = get_current_user_id();

	$wpdb->update(
		schedules_table('timeoff'),
		[
			'status'      => $decision,
			'reviewed_by' => $current_user_id,
			'reviewed_at' => current_time( 'mysql' ),
		],
		[ 'id' => $timeoff_id ],
		[ '%s', '%d', '%s' ],
		[ '%d' ]
	);

	// If approved, add OT slot
	if ( $decision === 'approved' ) {
		schedules_timeoff_add_ot_slot( (int) $request['user_id'], $request['start_date'], $current_user_id );
	}

	// Notify the member of the decision
	$reviewer    = get_userdata( $current_user_id );
	$reviewer_name = $reviewer ? trim( $reviewer->first_name . ' ' . $reviewer->last_name ) : 'A supervisor';
	$type_label  = strtoupper( $request['type'] );
	$date_label  = date( 'M j, Y', strtotime( $request['start_date'] ) );
	$status_word = $decision === 'approved' ? 'approved' : 'denied';

	$wpdb->insert(
		schedules_table('notifications'),
		[
			'user_id'      => (int) $request['user_id'],
			'type'         => 'timeoff_' . $decision,
			'related_id'   => $timeoff_id,
			'related_type' => 'timeoff',
			'message'      => "{$type_label} request for {$date_label} has been {$status_word} by {$reviewer_name}.",
			'is_read'      => 0,
			'created_at'   => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ]
	);

	wp_send_json_success( [ 'message' => "{$type_label} request {$status_word}." ] );
}

// --- Submit Time Off Request ---
add_action( 'wp_ajax_schedules_submit_timeoff', 'schedules_ajax_submit_timeoff' );
function schedules_ajax_submit_timeoff(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$current_user_id = get_current_user_id();
	$target_user_id  = (int) ( $_POST['user_id'] ?? 0 );
	$date            = sanitize_text_field( $_POST['date'] ?? '' );
	$type            = sanitize_text_field( $_POST['type'] ?? '' );
	$hours           = (float) ( $_POST['hours'] ?? 0 );
	$notes           = sanitize_textarea_field( $_POST['notes'] ?? '' );

	if ( ! $target_user_id || ! $date || ! $type ) {
		wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
	}

	if ( ! in_array( $type, [ 'pdo', 'sick', 'fmla' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid time off type.' ] );
	}

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		wp_send_json_error( [ 'message' => 'Invalid date.' ] );
	}

	// Members can only request for themselves; supervisors/admins for anyone
	if ( $target_user_id !== $current_user_id && ! current_user_can( 'manage_schedules' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	// Check for duplicate
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . schedules_table('timeoff') . "
		 WHERE user_id = %d AND start_date = %s AND end_date = %s AND type = %s AND status != 'denied'",
		$target_user_id, $date, $date, $type
	) );
	if ( $existing ) {
		wp_send_json_error( [ 'message' => 'A ' . strtoupper( $type ) . ' request already exists for this date.' ] );
	}

	// Auto-approve if submitted by a supervisor/admin
	$status = current_user_can( 'manage_schedules' ) ? 'approved' : 'pending';

	$wpdb->insert(
		schedules_table('timeoff'),
		[
			'user_id'      => $target_user_id,
			'type'         => $type,
			'start_date'   => $date,
			'end_date'     => $date,
			'hours'        => $hours,
			'status'       => $status,
			'notes'        => $notes,
			'reviewed_by'  => $status === 'approved' ? $current_user_id : null,
			'reviewed_at'  => $status === 'approved' ? current_time( 'mysql' ) : null,
			'requested_at' => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s' ]
	);

	if ( ! $wpdb->insert_id ) {
		wp_send_json_error( [ 'message' => 'Failed to save request.' ] );
	}

	// If approved, add a +1 OT adjustment for this shift/date
	if ( $status === 'approved' ) {
		schedules_timeoff_add_ot_slot( $target_user_id, $date, $current_user_id );
	}

	// Notify supervisors of pending requests so they can approve/deny
	if ( $status === 'pending' ) {
		$member      = get_userdata( $target_user_id );
		$member_name = $member ? trim( $member->first_name . ' ' . $member->last_name ) : "User #{$target_user_id}";
		$type_label  = strtoupper( $type );
		$date_label  = date( 'M j, Y', strtotime( $date ) );
		$timeoff_id  = $wpdb->insert_id;

		$supervisors = get_users( [ 'role__in' => [ 'schedules_supervisor', 'schedules_admin' ] ] );
		foreach ( $supervisors as $sup ) {
			$wpdb->insert(
				schedules_table('notifications'),
				[
					'user_id'      => $sup->ID,
					'type'         => 'timeoff_request',
					'related_id'   => $timeoff_id,
					'related_type' => 'timeoff',
					'message'      => "{$member_name} is requesting {$type_label} for {$date_label}.",
					'is_read'      => 0,
					'created_at'   => current_time( 'mysql' ),
				],
				[ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ]
			);
		}
	}

	$label = $status === 'approved' ? 'approved' : 'submitted';
	wp_send_json_success( [ 'message' => strtoupper( $type ) . " request {$label} for {$date}." ] );
}

/**
 * Adds a +1 OT adjustment for a user's shift on a given date.
 * Called when a time off request is approved.
 */
function schedules_timeoff_add_ot_slot( int $user_id, string $date, int $supervisor_id ): void {
	global $wpdb;

	$user_shift = get_user_meta( $user_id, 'schedules_shift', true );
	if ( ! $user_shift ) return;

	$day_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . schedules_table('days') . "
		 WHERE shift_letter = %s AND schedule_date = %s AND is_archived = 0",
		$user_shift, $date
	) );

	if ( ! $day_id ) return;

	$user_data   = get_userdata( $user_id );
	$member_name = $user_data ? trim( $user_data->first_name . ' ' . $user_data->last_name ) : "User #{$user_id}";

	schedules_add_adjustment(
		(int) $day_id,
		$supervisor_id,
		1,
		'PDO approved for ' . $member_name
	);
}

add_action( 'wp_ajax_schedules_get_personal_calendar', 'schedules_ajax_get_personal_calendar' );
function schedules_ajax_get_personal_calendar(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$current_user_id = get_current_user_id();
	$target_user_id  = (int) ($_POST['user_id'] ?? $current_user_id);

	// Supervisors can view any member; members can only view themselves
	if ( $target_user_id !== $current_user_id && ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$year  = (int) ($_POST['year']  ?? date('Y'));
	$month = (int) ($_POST['month'] ?? date('n'));

	// Build the calendar grid (6 weeks max)
	$first_of_month  = mktime( 0, 0, 0, $month, 1, $year );
	$days_in_month   = (int) date( 't', $first_of_month );
	$first_dow       = (int) date( 'w', $first_of_month ); // 0=Sun
	$month_label     = date( 'F Y', $first_of_month );

	// Get the user's shift and custom schedule settings
	$user_shift    = get_user_meta( $target_user_id, 'schedules_shift',          true );
	$schedule_type = get_user_meta( $target_user_id, 'schedules_schedule_type',   true ) ?: 'shift';
	$custom_sched  = get_user_meta( $target_user_id, 'schedules_custom_schedule', true );
	if ( ! is_array( $custom_sched ) ) $custom_sched = [];

	$shift_row  = null;
	if ( $schedule_type !== 'custom' && $user_shift ) {
		$shift_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . schedules_table('shifts') . " WHERE shift_letter = %s",
			$user_shift
		), ARRAY_A );
	}

	$work_days    = $shift_row ? array_map( 'intval', explode( ',', $shift_row['work_days'] ) ) : [];
	$work_days_w2 = ( ! empty( $shift_row['work_days_week2'] ) )
		? array_map( 'intval', explode( ',', $shift_row['work_days_week2'] ) )
		: $work_days;
	$cycle_anchor = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';
	$start_hour   = $shift_row ? (int) $shift_row['start_hour'] : 0;
	$end_hour     = $shift_row ? (int) $shift_row['end_hour']   : 0;

	// Date range for the visible calendar (may include days from prev/next month)
	$cal_start = date( 'Y-m-d', strtotime( "-{$first_dow} days", $first_of_month ) );
	$total_cells = $first_dow + $days_in_month;
	$grid_rows   = (int) ceil( $total_cells / 7 );
	$total_days  = $grid_rows * 7;
	$cal_end   = date( 'Y-m-d', strtotime( '+' . ($total_days - 1) . ' days', strtotime($cal_start) ) );

	// Get OT claims for this user in the date range
	$ot_claims = $wpdb->get_results( $wpdb->prepare(
		"SELECT d.schedule_date, d.shift_letter, b.start_hour, b.end_hour
		 FROM " . schedules_table('claims') . " c
		 JOIN " . schedules_table('blocks') . " b ON b.id = c.block_id
		 JOIN " . schedules_table('days') . " d ON d.id = b.day_id
		 WHERE c.user_id = %d
		   AND d.schedule_date BETWEEN %s AND %s
		 ORDER BY d.schedule_date ASC, b.start_hour ASC",
		$target_user_id,
		$cal_start,
		$cal_end
	), ARRAY_A ) ?: [];

	// Merge consecutive OT blocks per date
	$ot_by_date = [];
	foreach ( $ot_claims as $oc ) {
		$ot_by_date[ $oc['schedule_date'] ][] = [
			'start' => (int) $oc['start_hour'],
			'end'   => (int) $oc['end_hour'],
			'shift' => $oc['shift_letter'],
		];
	}
	foreach ( $ot_by_date as $date => &$blocks ) {
		$merged = [];
		$cur = null;
		foreach ( $blocks as $b ) {
			if ( $cur && $b['start'] === $cur['end'] && $b['shift'] === $cur['shift'] ) {
				$cur['end'] = $b['end'];
			} else {
				if ( $cur ) $merged[] = $cur;
				$cur = $b;
			}
		}
		if ( $cur ) $merged[] = $cur;
		$blocks = $merged;
	}
	unset( $blocks );

	// Get time off entries overlapping this date range
	$timeoff = $wpdb->get_results( $wpdb->prepare(
		"SELECT type, start_date, end_date, hours, status
		 FROM " . schedules_table('timeoff') . "
		 WHERE user_id = %d
		   AND start_date <= %s
		   AND end_date   >= %s
		   AND status IN ('approved', 'pending')
		 ORDER BY start_date ASC",
		$target_user_id,
		$cal_end,
		$cal_start
	), ARRAY_A ) ?: [];

	// Index time off by date
	$timeoff_by_date = [];
	foreach ( $timeoff as $to ) {
		$d = max( $to['start_date'], $cal_start );
		$e = min( $to['end_date'], $cal_end );
		while ( $d <= $e ) {
			$timeoff_by_date[ $d ][] = [
				'type'   => $to['type'],
				'status' => $to['status'],
			];
			$d = date( 'Y-m-d', strtotime( '+1 day', strtotime($d) ) );
		}
	}

	// Build calendar days
	$days = [];
	for ( $i = 0; $i < $total_days; $i++ ) {
		$date     = date( 'Y-m-d', strtotime( "+{$i} days", strtotime($cal_start) ) );
		$dow      = (int) date( 'w', strtotime($date) );
		$day_num  = (int) date( 'j', strtotime($date) );
		$in_month = (int) date( 'n', strtotime($date) ) === $month;

		$day_data = [
			'date'     => $date,
			'day'      => $day_num,
			'dow'      => $dow,
			'in_month' => $in_month,
			'is_today' => $date === date('Y-m-d'),
			'shift'    => null,
			'ot'       => [],
			'timeoff'  => [],
		];

		// Regular shift
		if ( $schedule_type === 'custom' ) {
			if ( isset( $custom_sched[ $dow ] ) ) {
				$cs = $custom_sched[ $dow ];
				$day_data['shift'] = [
					'letter' => 'custom',
					'start'  => (int) $cs['start'],
					'end'    => (int) $cs['end'],
				];
			}
		} elseif ( $user_shift && $work_days ) {
			if ( $cycle_anchor ) {
				$cycle_week       = schedules_get_cycle_week( $date, $cycle_anchor );
				$todays_work_days = $cycle_week === 0 ? $work_days : $work_days_w2;
			} else {
				$todays_work_days = $work_days;
			}
			if ( in_array( $dow, $todays_work_days, true ) ) {
				$day_data['shift'] = [
					'letter' => $user_shift,
					'start'  => $start_hour,
					'end'    => $end_hour,
				];
			}
		}

		// OT
		if ( isset( $ot_by_date[$date] ) ) {
			$day_data['ot'] = $ot_by_date[$date];
		}

		// Time off
		if ( isset( $timeoff_by_date[$date] ) ) {
			$day_data['timeoff'] = $timeoff_by_date[$date];
		}

		$days[] = $day_data;
	}

	// Get target user info
	$target_user = get_userdata( $target_user_id );
	$name_format = get_user_meta( $current_user_id, 'schedules_name_format', true ) ?: 'first_last';
	$display_name = '';
	if ( $target_user ) {
		$display_name = $target_user->first_name && $target_user->last_name
			? ( $name_format === 'last_first'
				? $target_user->last_name . ', ' . $target_user->first_name
				: $target_user->first_name . ' ' . $target_user->last_name )
			: $target_user->display_name;
	}

	wp_send_json_success( [
		'year'         => $year,
		'month'        => $month,
		'month_label'  => $month_label,
		'user_id'      => $target_user_id,
		'user_name'    => $display_name,
		'user_shift'   => $user_shift ?: '',
		'days'         => $days,
	] );
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
