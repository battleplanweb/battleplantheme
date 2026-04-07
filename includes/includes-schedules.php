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

define( 'SCHEDULES_DB_VERSION', '2.18' );

/**
 * Returns a fully-qualified table name for the schedules module.
 * Using a function avoids the $wpdb-at-define-time issue.
 */
function schedules_table( string $name ): string {
	return $GLOBALS['wpdb']->prefix . 'schedules_' . $name;
}

/**
 * Writes one row to the activity log.
 * $action    : short machine key  e.g. 'ot_claim'
 * $description: human-readable sentence shown in the log view
 * $meta      : optional extra data stored as JSON
 * $for_user_id: the member the action was performed ON (0 = self / n/a)
 */
function schedules_log( string $action, string $description, array $meta = [], int $for_user_id = 0 ): void {
	global $wpdb;
	$actor_id  = get_current_user_id();
	$actor     = get_userdata( $actor_id );
	$wpdb->insert(
		schedules_table('activity_log'),
		[
			'actor_id'    => $actor_id,
			'actor_name'  => $actor ? $actor->display_name : 'System',
			'actor_badge' => $actor ? $actor->user_login   : '',
			'for_user_id' => $for_user_id,
			'action'      => $action,
			'description' => $description,
			'meta'        => $meta ? wp_json_encode( $meta ) : null,
			'created_at'  => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
	);
}

function schedules_unread_count( int $user_id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . schedules_table('notifications') . " WHERE user_id = %d AND is_read = 0 AND is_archived = 0",
		$user_id
	) );
}

add_action( 'wp_ajax_schedules_get_unread_count', 'schedules_ajax_get_unread_count' );
function schedules_ajax_get_unread_count(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	$count = schedules_unread_count( get_current_user_id() );
	wp_send_json_success( [ 'count' => $count ] );
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
		half_day_dow tinyint DEFAULT NULL,
		half_day_blocked_start tinyint DEFAULT NULL,
		half_day_blocked_end tinyint DEFAULT NULL,
		day_schedule text DEFAULT NULL,
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
		is_trainee tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY day_position (day_id, position_id)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('trades') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		type varchar(10) NOT NULL DEFAULT 'cover',
		requester_id int(11) NOT NULL,
		recipient_id int(11) NOT NULL,
		requester_day_id int(11) DEFAULT NULL,
		recipient_day_id int(11) DEFAULT NULL,
		trade_date date DEFAULT NULL,
		shift_letter char(1) DEFAULT NULL,
		status varchar(30) NOT NULL DEFAULT 'pending',
		requester_note text DEFAULT NULL,
		recipient_note text DEFAULT NULL,
		supervisor_note text DEFAULT NULL,
		requested_at datetime NOT NULL,
		responded_at datetime DEFAULT NULL,
		reviewed_by int(11) DEFAULT NULL,
		reviewed_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY requester_status (requester_id, status),
		KEY recipient_status (recipient_id, status)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('timeoff') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		type varchar(10) NOT NULL,
		start_date date NOT NULL,
		end_date date NOT NULL,
		start_time time DEFAULT NULL,
		end_time time DEFAULT NULL,
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
		is_archived tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $charset;";

	$sql .= "CREATE TABLE " . schedules_table('activity_log') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		actor_id int(11) NOT NULL,
		actor_name varchar(255) NOT NULL,
		actor_badge varchar(50) NOT NULL DEFAULT '',
		for_user_id int(11) NOT NULL DEFAULT 0,
		action varchar(50) NOT NULL,
		description text NOT NULL,
		meta text DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY actor_idx (actor_id),
		KEY action_idx (action),
		KEY created_idx (created_at)
	) $charset;";

	// Immutable roster snapshot for each archived duty day.
	// Written at rollover time so past rosters never change even if members switch shifts.
	$sql .= "CREATE TABLE " . schedules_table('roster_snapshots') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		day_id int(11) NOT NULL,
		roster_json longtext NOT NULL,
		snapped_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY day_id (day_id)
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

	// [ letter, start_hour, end_hour, work_days, half_day_dow, half_day_blocked_start, half_day_blocked_end, day_schedule ]
	$_ds_A = '{"week1":{"0":{"start":6,"end":18},"1":{"start":6,"end":18},"2":{"start":6,"end":18},"3":{"start":6,"end":12}},"week2":{"0":{"start":6,"end":18},"1":{"start":6,"end":18},"2":{"start":6,"end":18},"3":{"start":6,"end":12}}}';
	$_ds_B = '{"week1":{"3":{"start":12,"end":18},"4":{"start":6,"end":18},"5":{"start":6,"end":18},"6":{"start":6,"end":18}},"week2":{"3":{"start":12,"end":18},"4":{"start":6,"end":18},"5":{"start":6,"end":18},"6":{"start":6,"end":18}}}';
	$_ds_C = '{"week1":{"0":{"start":18,"end":6},"1":{"start":18,"end":6},"2":{"start":18,"end":6},"3":{"start":18,"end":6}},"week2":{"0":{"start":18,"end":6},"1":{"start":18,"end":6},"2":{"start":18,"end":6},"3":{"start":18,"end":6}}}';
	$_ds_D = '{"week1":{"3":{"start":18,"end":6},"4":{"start":18,"end":6},"5":{"start":18,"end":6},"6":{"start":18,"end":6}},"week2":{"3":{"start":18,"end":6},"4":{"start":18,"end":6},"5":{"start":18,"end":6},"6":{"start":18,"end":6}}}';
	$shifts = [
		[ 'A', 6,  18, '0,1,2,3', 3,    12, 18, $_ds_A ],  // Sun–Wed day  — leaves at noon Wednesday
		[ 'B', 6,  18, '3,4,5,6', 3,     6, 12, $_ds_B ],  // Wed–Sat day  — arrives at noon Wednesday
		[ 'C', 18,  6, '0,1,2,3', null, null, null, $_ds_C ],  // Sun–Wed night
		[ 'D', 18,  6, '3,4,5,6', null, null, null, $_ds_D ],  // Wed–Sat night
	];

	foreach ( $shifts as $s ) {
		$data = [
			'shift_letter' => $s[0],
			'start_hour'   => $s[1],
			'end_hour'     => $s[2],
			'work_days'    => $s[3],
			'floor_count'  => 0,
			'max_capacity' => 14,
			'day_schedule' => $s[7],
		];
		$fmt = [ '%s', '%d', '%d', '%s', '%d', '%d', '%s' ];
		if ( $s[4] !== null ) {
			$data['half_day_dow']           = $s[4];
			$data['half_day_blocked_start'] = $s[5];
			$data['half_day_blocked_end']   = $s[6];
			array_push( $fmt, '%d', '%d', '%d' );
		}
		$wpdb->insert( schedules_table('shifts'), $data, $fmt );
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
 * Computes the start_hour values (as an int[]) that a given shift covers on a specific date,
 * using the same logic as schedules_generate_day (day_schedule JSON → Wednesday heuristic → canonical).
 * Returns null if the shift does not work on that date at all.
 *
 * @param string $date         Y-m-d
 * @param array  $shift_row    Full row from schedules_shifts for this shift
 * @param array  $all_shifts   All rows from schedules_shifts (needed for Wednesday heuristic)
 * @return int[]|null
 */
function schedules_compute_shift_hours_for_date( string $date, array $shift_row, array $all_shifts ): ?array {
	$day_of_week  = (int) date( 'w', strtotime( $date ) );
	$cycle_anchor = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';
	$cycle_week   = schedules_get_cycle_week( $date, $cycle_anchor );

	$work_days_w1 = array_map( 'intval', explode( ',', $shift_row['work_days'] ) );
	$work_days_w2 = ! empty( $shift_row['work_days_week2'] )
		? array_map( 'intval', explode( ',', $shift_row['work_days_week2'] ) )
		: $work_days_w1;
	$active_days  = $cycle_week === 0 ? $work_days_w1 : $work_days_w2;

	if ( ! in_array( $day_of_week, $active_days, true ) ) return null;

	$start_hour = (int) $shift_row['start_hour'];
	$end_hour   = (int) $shift_row['end_hour'];

	$_ds_json = $shift_row['day_schedule'] ?? '';
	$_ds_data = $_ds_json ? json_decode( $_ds_json, true ) : null;
	$_ds_week = $cycle_week === 0 ? 'week1' : 'week2';
	$_ds_key  = (string) $day_of_week;

	if ( $_ds_data && isset( $_ds_data[ $_ds_week ][ $_ds_key ] ) ) {
		$start_hour  = (int) $_ds_data[ $_ds_week ][ $_ds_key ]['start'];
		$end_hour    = (int) $_ds_data[ $_ds_week ][ $_ds_key ]['end'];
		$block_hours = schedules_get_block_hours_range( $start_hour, schedules_shift_duration( $start_hour, $end_hour ) );
	} elseif ( $day_of_week === 3 ) {
		// Wednesday overlap heuristic — same as schedules_generate_day
		$same_time_count  = 0;
		$same_time_before = 0;
		foreach ( $all_shifts as $other ) {
			$ow1 = array_map( 'intval', explode( ',', $other['work_days'] ) );
			$ow2 = ! empty( $other['work_days_week2'] )
				? array_map( 'intval', explode( ',', $other['work_days_week2'] ) )
				: $ow1;
			$other_active = $cycle_week === 0 ? $ow1 : $ow2;
			if ( ! in_array( 3, $other_active, true ) ) continue;
			if ( (int) $other['start_hour'] === $start_hour ) {
				$same_time_count++;
				if ( $other['shift_letter'] < $shift_row['shift_letter'] ) $same_time_before++;
			}
		}
		if ( $same_time_count > 1 ) {
			$total_hours = schedules_shift_duration( $start_hour, $end_hour );
			$chunk       = (int) floor( $total_hours / $same_time_count );
			$my_start    = ( $start_hour + $chunk * $same_time_before ) % 24;
			$block_hours = schedules_get_block_hours_range( $my_start, $chunk );
		} else {
			$block_hours = schedules_get_block_hours_range( $start_hour, schedules_shift_duration( $start_hour, $end_hour ) );
		}
	} else {
		$block_hours = schedules_get_block_hours_range( $start_hour, schedules_shift_duration( $start_hour, $end_hour ) );
	}

	return array_column( $block_hours, 'start' );
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
		$_hours_set  = schedules_compute_shift_hours_for_date( $date, $shift, $shifts );
		// Convert start_hour list → block_hours array (each entry: start, end=start+1)
		$block_hours = array_map( function( $h ) {
			return [ 'start' => $h, 'end' => ( $h + 1 ) % 24 ];
		}, $_hours_set ?? [] );

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
		} elseif ( $stored === '2.7' ) {
			// 2.7 → 2.8: add is_trainee to duty table
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('duty') );
			if ( ! in_array( 'is_trainee', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('duty') . " ADD COLUMN is_trainee tinyint(1) NOT NULL DEFAULT 0 AFTER voided_at" );
			}
		} elseif ( $stored === '2.8' ) {
			// 2.8 → 2.9: add start_time/end_time to timeoff table
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('timeoff') );
			if ( ! in_array( 'start_time', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('timeoff') . " ADD COLUMN start_time time DEFAULT NULL AFTER end_date" );
			}
			if ( ! in_array( 'end_time', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('timeoff') . " ADD COLUMN end_time time DEFAULT NULL AFTER start_time" );
			}
		} elseif ( $stored === '2.9' ) {
			// 2.9 → 2.10: add is_archived to notifications table
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('notifications') );
			if ( ! in_array( 'is_archived', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('notifications') . " ADD COLUMN is_archived tinyint(1) NOT NULL DEFAULT 0 AFTER is_read" );
			}
		} elseif ( $stored === '2.10' ) {
			// 2.10 → 2.11: add half-day columns to shifts table
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('shifts') );
			if ( ! in_array( 'half_day_dow', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN half_day_dow tinyint DEFAULT NULL AFTER work_days_week2" );
				$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN half_day_blocked_start tinyint DEFAULT NULL AFTER half_day_dow" );
				$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN half_day_blocked_end tinyint DEFAULT NULL AFTER half_day_blocked_start" );
				// Backfill: Shift A leaves at noon on Wednesday; Shift B arrives at noon on Wednesday
				$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET half_day_dow = 3, half_day_blocked_start = 12, half_day_blocked_end = 18 WHERE shift_letter = 'A'" );
				$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET half_day_dow = 3, half_day_blocked_start = 6,  half_day_blocked_end = 12 WHERE shift_letter = 'B'" );
			}
		} elseif ( $stored === '2.11' ) {
			// 2.11 → 2.12: add day_schedule TEXT column to shifts table
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('shifts') );
			if ( ! in_array( 'day_schedule', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN day_schedule text DEFAULT NULL AFTER half_day_blocked_end" );
			}
		} elseif ( $stored === '2.12' ) {
			// 2.12 → 2.13: upgrade day_schedule to two-week format {"week1":{...},"week2":{...}}
			// Overwrite unconditionally — if a custom schedule was saved in old flat format it needs upgrading too
			global $wpdb;
			$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET day_schedule = '{\"week1\":{\"0\":{\"start\":6,\"end\":18},\"1\":{\"start\":6,\"end\":18},\"2\":{\"start\":6,\"end\":18},\"3\":{\"start\":6,\"end\":12}},\"week2\":{\"0\":{\"start\":6,\"end\":18},\"1\":{\"start\":6,\"end\":18},\"2\":{\"start\":6,\"end\":18},\"3\":{\"start\":6,\"end\":12}}}' WHERE shift_letter = 'A'" );
			$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET day_schedule = '{\"week1\":{\"3\":{\"start\":12,\"end\":18},\"4\":{\"start\":6,\"end\":18},\"5\":{\"start\":6,\"end\":18},\"6\":{\"start\":6,\"end\":18}},\"week2\":{\"3\":{\"start\":12,\"end\":18},\"4\":{\"start\":6,\"end\":18},\"5\":{\"start\":6,\"end\":18},\"6\":{\"start\":6,\"end\":18}}}' WHERE shift_letter = 'B'" );
			$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET day_schedule = '{\"week1\":{\"0\":{\"start\":18,\"end\":6},\"1\":{\"start\":18,\"end\":6},\"2\":{\"start\":18,\"end\":6},\"3\":{\"start\":18,\"end\":6}},\"week2\":{\"0\":{\"start\":18,\"end\":6},\"1\":{\"start\":18,\"end\":6},\"2\":{\"start\":18,\"end\":6},\"3\":{\"start\":18,\"end\":6}}}' WHERE shift_letter = 'C'" );
			$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET day_schedule = '{\"week1\":{\"3\":{\"start\":18,\"end\":6},\"4\":{\"start\":18,\"end\":6},\"5\":{\"start\":18,\"end\":6},\"6\":{\"start\":18,\"end\":6}},\"week2\":{\"3\":{\"start\":18,\"end\":6},\"4\":{\"start\":18,\"end\":6},\"5\":{\"start\":18,\"end\":6},\"6\":{\"start\":18,\"end\":6}}}' WHERE shift_letter = 'D'" );
		} elseif ( $stored === '2.13' ) {
			// 2.13 → 2.14: migrate CTO from title to is_cto flag
			// (falls through to schedules_install_db which handles the rest)
			global $wpdb;
			$cto_title_id = (int) $wpdb->get_var(
				"SELECT id FROM " . schedules_table('titles') . " WHERE LOWER(TRIM(name)) = 'cto' LIMIT 1"
			);
			if ( $cto_title_id ) {
				// Set is_cto flag for everyone who had CTO as their primary title
				$cto_users = $wpdb->get_col( $wpdb->prepare(
					"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'schedules_title' AND meta_value = %s",
					(string) $cto_title_id
				) );
				foreach ( $cto_users as $_uid ) {
					update_user_meta( (int) $_uid, 'schedules_is_cto', 1 );
					update_user_meta( (int) $_uid, 'schedules_title',  0 );
				}
				// Deactivate the CTO title row so it won't reappear
				$wpdb->update( schedules_table('titles'), [ 'is_active' => 0 ], [ 'id' => $cto_title_id ], [ '%d' ], [ '%d' ] );
			}
		} elseif ( $stored === '2.15' ) {
			// 2.15 → 2.16: regenerate future unclaimed blocks using day_schedule as source of truth
			schedules_regenerate_future_days();
		} elseif ( $stored === '2.14' ) {
			// 2.14 → 2.15: add trade_date and shift_letter to trades table
			global $wpdb;
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('trades') );
			if ( ! in_array( 'trade_date', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('trades') . " ADD COLUMN trade_date date DEFAULT NULL AFTER recipient_day_id" );
			}
			if ( ! in_array( 'shift_letter', $cols ) ) {
				$wpdb->query( "ALTER TABLE " . schedules_table('trades') . " ADD COLUMN shift_letter char(1) DEFAULT NULL AFTER trade_date" );
			}
			// Widen status column to accommodate new status strings
			$wpdb->query( "ALTER TABLE " . schedules_table('trades') . " MODIFY COLUMN status varchar(30) NOT NULL DEFAULT 'pending'" );
		}
		if ( version_compare( $stored, '2.18', '<' ) ) {
			// 2.17 → 2.18: create roster_snapshots table for immutable past-day rosters
			global $wpdb;
			$charset = $wpdb->get_charset_collate();
			$wpdb->query( "CREATE TABLE IF NOT EXISTS " . schedules_table('roster_snapshots') . " (
				id int(11) NOT NULL AUTO_INCREMENT,
				day_id int(11) NOT NULL,
				roster_json longtext NOT NULL,
				snapped_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY day_id (day_id)
			) $charset" );
		}
		update_option( 'schedules_db_version', SCHEDULES_DB_VERSION );
	}

	// Safety net: ensure half-day and day_schedule columns exist regardless of migration path
	global $wpdb;
	$_shift_cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('shifts') );
	if ( ! in_array( 'half_day_dow', $_shift_cols ) ) {
		$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN half_day_dow tinyint DEFAULT NULL AFTER work_days_week2" );
		$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN half_day_blocked_start tinyint DEFAULT NULL AFTER half_day_dow" );
		$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN half_day_blocked_end tinyint DEFAULT NULL AFTER half_day_blocked_start" );
		$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET half_day_dow = 3, half_day_blocked_start = 12, half_day_blocked_end = 18 WHERE shift_letter = 'A'" );
		$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET half_day_dow = 3, half_day_blocked_start = 6,  half_day_blocked_end = 12 WHERE shift_letter = 'B'" );
	}
	if ( ! in_array( 'day_schedule', $_shift_cols ) ) {
		$wpdb->query( "ALTER TABLE " . schedules_table('shifts') . " ADD COLUMN day_schedule text DEFAULT NULL AFTER half_day_blocked_end" );
		$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET day_schedule = '{\"week1\":{\"0\":{\"start\":6,\"end\":18},\"1\":{\"start\":6,\"end\":18},\"2\":{\"start\":6,\"end\":18},\"3\":{\"start\":6,\"end\":12}},\"week2\":{\"0\":{\"start\":6,\"end\":18},\"1\":{\"start\":6,\"end\":18},\"2\":{\"start\":6,\"end\":18},\"3\":{\"start\":6,\"end\":12}}}' WHERE shift_letter = 'A'" );
		$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET day_schedule = '{\"week1\":{\"3\":{\"start\":12,\"end\":18},\"4\":{\"start\":6,\"end\":18},\"5\":{\"start\":6,\"end\":18},\"6\":{\"start\":6,\"end\":18}},\"week2\":{\"3\":{\"start\":12,\"end\":18},\"4\":{\"start\":6,\"end\":18},\"5\":{\"start\":6,\"end\":18},\"6\":{\"start\":6,\"end\":18}}}' WHERE shift_letter = 'B'" );
		$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET day_schedule = '{\"week1\":{\"0\":{\"start\":18,\"end\":6},\"1\":{\"start\":18,\"end\":6},\"2\":{\"start\":18,\"end\":6},\"3\":{\"start\":18,\"end\":6}},\"week2\":{\"0\":{\"start\":18,\"end\":6},\"1\":{\"start\":18,\"end\":6},\"2\":{\"start\":18,\"end\":6},\"3\":{\"start\":18,\"end\":6}}}' WHERE shift_letter = 'C'" );
		$wpdb->query( "UPDATE " . schedules_table('shifts') . " SET day_schedule = '{\"week1\":{\"3\":{\"start\":18,\"end\":6},\"4\":{\"start\":18,\"end\":6},\"5\":{\"start\":18,\"end\":6},\"6\":{\"start\":18,\"end\":6}},\"week2\":{\"3\":{\"start\":18,\"end\":6},\"4\":{\"start\":18,\"end\":6},\"5\":{\"start\":18,\"end\":6},\"6\":{\"start\":18,\"end\":6}}}' WHERE shift_letter = 'D'" );
	}
}, 5 );


/*--------------------------------------------------------------
# Cache Prevention
--------------------------------------------------------------*/

// Prevent WP Engine EverCache (and other caching plugins) from caching
// any schedules page — nonces must be fresh on every request.
add_action( 'template_redirect', function() {
	$schedules_slugs = [ 'schedules-login', 'schedules-member', 'schedules-supervisor' ];
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
	$schedules_slugs = [ 'schedules-login', 'schedules-member', 'schedules-supervisor' ];

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
		'scheduleTimeIncrement'    => (int) schedules_get_config( 'schedule_time_increment', '60' ),
		'dutyNameFormat'           => get_user_meta( $user_id, 'schedules_name_format', true ) ?: 'first_last',
		'supervisorsCanClaimOt'    => schedules_get_config( 'supervisors_can_claim_ot', '0' ),
		'minClaimHours'            => (int) apply_filters( 'schedules_min_claim_hours', max( 1, (int) schedules_get_config( 'ot_min_claim_hours', '0' ) ) ),
		'cycleAnchor'              => get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06',
		'shifts'                   => ( function() {
			global $wpdb;
			$rows = $wpdb->get_results( "SELECT shift_letter, start_hour, end_hour, work_days, work_days_week2 FROM " . schedules_table('shifts') . " ORDER BY shift_letter", ARRAY_A ) ?: [];
			return array_map( function( $s ) {
				return [
					'letter'        => $s['shift_letter'],
					'startHour'     => (int) $s['start_hour'],
					'endHour'       => (int) $s['end_hour'],
					'workDays'      => array_map( 'intval', explode( ',', $s['work_days'] ) ),
					'workDaysWeek2' => ! empty( $s['work_days_week2'] )
						? array_map( 'intval', explode( ',', $s['work_days_week2'] ) )
						: array_map( 'intval', explode( ',', $s['work_days'] ) ),
				];
			}, $rows );
		} )(),
		'shiftMembers'             => ( $user && $user->has_cap('manage_schedules') ) ? array_map( function( $m ) {
			return [
				'id'    => (int) $m['user_id'],
				'name'  => trim( $m['first_name'] . ' ' . $m['last_name'] ) ?: $m['badge_number'],
				'shift' => (string) $m['shift'],
				'role'  => $m['role'] ?? 'member',
			];
		}, schedules_get_all_members() ) : [],
		'coverMembers'             => ( function() use ( $user_id ) {
			$all    = schedules_get_all_members();
			$result = [];
			foreach ( $all as $m ) {
				if ( (int) $m['user_id'] === (int) $user_id ) continue;
				$result[] = [
					'id'    => (int) $m['user_id'],
					'name'  => trim( $m['first_name'] . ' ' . $m['last_name'] ) ?: $m['badge_number'],
					'shift' => (string) $m['shift'],
					'role'  => $m['role'] ?? 'member',
				];
			}
			return $result;
		} )(),
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
 * Returns true if the user's title is "Trainee".
 */
function schedules_user_is_trainee( int $user_id ): bool {
	global $wpdb;
	$title_id = (int) get_user_meta( $user_id, 'schedules_title', true );
	if ( ! $title_id ) return false;
	$name = $wpdb->get_var( $wpdb->prepare(
		"SELECT name FROM " . schedules_table('titles') . " WHERE id = %d",
		$title_id
	) );
	return $name && strtolower( trim( $name ) ) === 'trainee';
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
		if ( $priority === '5' ) continue;

		$shift = (string) get_user_meta( $user->ID, 'schedules_shift', true );
		if ( $shift ) {
			update_user_meta( $user->ID, 'schedules_priority', '1' );
			continue;
		}

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

	// Custom schedule members: no shift assigned, but scheduled to work on this date's day-of-week
	$dow          = (int) date( 'w', strtotime( $date ) ); // 0=Sun … 6=Sat
	$cycle_anchor = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';
	$cycle_week   = schedules_get_cycle_week( $date, $cycle_anchor ); // 0 or 1
	$wk_key       = $cycle_week === 0 ? 'week1' : 'week2';

	$custom_users = get_users( [
		'meta_key'   => 'schedules_schedule_type',
		'meta_value' => 'custom',
		'role__in'   => [ 'schedules_member', 'schedules_supervisor', 'schedules_admin' ],
	] );

	foreach ( $custom_users as $u ) {
		if ( isset( $members[ $u->ID ] ) ) continue; // already in list (shouldn't happen)
		$raw_sched   = get_user_meta( $u->ID, 'schedules_custom_schedule', true );
		$raw_sched   = is_array( $raw_sched ) ? $raw_sched : [];
		$norm        = schedules_normalize_custom_schedule( $raw_sched );
		$week_sched  = $norm[ $wk_key ] ?? [];
		if ( ! isset( $week_sched[ $dow ] ) ) continue; // not scheduled this day
		$day_entry   = $week_sched[ $dow ];
		$disc        = (string) get_user_meta( $u->ID, 'schedules_discipline', true );
		$disc_arr    = $disc ? array_values( array_filter( array_map( 'trim', explode( ',', $disc ) ) ) ) : [];
		$name        = trim( "{$u->first_name} {$u->last_name}" ) ?: $u->display_name;
		$members[ $u->ID ] = [
			'user_id'      => $u->ID,
			'display_name' => $name,
			'first_name'   => $u->first_name,
			'last_name'    => $u->last_name,
			'disciplines'  => $disc_arr,
			'type'         => 'custom',
			'custom_hours' => [
				'start' => $day_entry['start'] ?? 0,
				'end'   => $day_entry['end']   ?? 0,
			],
		];
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
		"SELECT s.id, s.position_id, s.user_id, s.start_time, s.end_time, s.is_trainee,
		        u.display_name,
		        um_fn.meta_value AS first_name,
		        um_ln.meta_value AS last_name
		 FROM " . schedules_table('duty') . " s
		 JOIN {$wpdb->users} u ON u.ID = s.user_id
		 LEFT JOIN {$wpdb->usermeta} um_fn ON um_fn.user_id = s.user_id AND um_fn.meta_key = 'first_name'
		 LEFT JOIN {$wpdb->usermeta} um_ln ON um_ln.user_id = s.user_id AND um_ln.meta_key = 'last_name'
		 WHERE s.day_id = %d
		   AND s.is_voided = 0
		 ORDER BY s.position_id ASC, s.is_trainee ASC, s.start_time ASC",
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

	// Build the authoritative set of start_hours this shift covers on this date.
	// Primary source: blocks (already generated). Fallback: recompute using the same
	// logic as schedules_generate_day (day_schedule JSON → Wednesday heuristic → canonical).
	// This ensures custom-schedule members are always filtered correctly, even for past
	// days whose blocks may have been cleared or never generated.
	$_shift_hours_set = null;
	if ( $day_id ) {
		$_shift_block_hours = $wpdb->get_col( $wpdb->prepare(
			"SELECT start_hour FROM " . schedules_table('blocks') . " WHERE day_id = %d",
			$day_id
		) );
		if ( ! empty( $_shift_block_hours ) ) {
			$_shift_hours_set = array_map( 'intval', $_shift_block_hours );
		}
	}
	if ( $_shift_hours_set === null && $shift_letter ) {
		$_all_shift_rows = $wpdb->get_results(
			"SELECT * FROM " . schedules_table('shifts'), ARRAY_A
		) ?: [];
		foreach ( $_all_shift_rows as $_sr ) {
			if ( $_sr['shift_letter'] === $shift_letter ) {
				$_shift_hours_set = schedules_compute_shift_hours_for_date( $date, $_sr, $_all_shift_rows );
				break;
			}
		}
	}

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
		$is_cto   = (bool) get_user_meta( $u->ID, 'schedules_is_cto', true );
		$title_str = $title_id && isset($_titles_map[$title_id]) ? $_titles_map[$title_id] : '';
		if ( $is_cto ) $title_str .= ( $title_str ? ' / CTO' : 'CTO' );
		$name     = trim( "{$u->first_name} {$u->last_name}" ) ?: $u->display_name;
		$members[ $u->ID ] = [
			'user_id'      => $u->ID,
			'display_name' => $name,
			'first_name'   => $u->first_name,
			'last_name'    => $u->last_name,
			'discipline'   => $disc,
			'title'        => $title_str,
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
			$is_cto   = (bool) get_user_meta( $uid, 'schedules_is_cto', true );
			$title_str = $title_id && isset($_titles_map[$title_id]) ? $_titles_map[$title_id] : '';
			if ( $is_cto ) $title_str .= ( $title_str ? ' / CTO' : 'CTO' );
			$name     = trim( "{$u->first_name} {$u->last_name}" ) ?: $u->display_name;
			$members[$uid] = [
				'user_id'      => $uid,
				'display_name' => $name,
				'first_name'   => $u->first_name,
				'last_name'    => $u->last_name,
				'discipline'   => $disc,
				'title'        => $title_str,
				'type'         => 'ot',
				'ot_hours'     => $_ot_merged[$uid] ?? [],
				'timeoff'      => null,
				'assignments'  => [],
			];
		}
	}

	// Custom schedule members: no shift, but scheduled on this date's day-of-week
	$_dow          = (int) date( 'w', strtotime( $date ) );
	$_cycle_anchor = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';
	$_cycle_week   = schedules_get_cycle_week( $date, $_cycle_anchor );
	$_wk_key       = $_cycle_week === 0 ? 'week1' : 'week2';

	$_custom_users = get_users( [
		'meta_key'   => 'schedules_schedule_type',
		'meta_value' => 'custom',
		'role__in'   => [ 'schedules_member', 'schedules_supervisor', 'schedules_admin' ],
	] );

	foreach ( $_custom_users as $u ) {
		if ( isset( $members[ $u->ID ] ) ) continue;
		$_raw   = get_user_meta( $u->ID, 'schedules_custom_schedule', true );
		$_raw   = is_array( $_raw ) ? $_raw : [];
		$_norm  = schedules_normalize_custom_schedule( $_raw );
		$_wsched = $_norm[ $_wk_key ] ?? [];
		if ( ! isset( $_wsched[ $_dow ] ) ) continue;
		$_entry   = $_wsched[ $_dow ];

		// Only include this custom-schedule member if their hours overlap this shift
		if ( $_shift_hours_set !== null ) {
			$_cs_start = (int)( $_entry['start'] ?? 0 );
			$_cs_end   = (int)( $_entry['end']   ?? 0 );
			$_cs_hours = [];
			if ( $_cs_end > $_cs_start ) {
				for ( $_h = $_cs_start; $_h < $_cs_end; $_h++ ) $_cs_hours[] = $_h;
			} else {
				for ( $_h = $_cs_start; $_h < 24; $_h++ ) $_cs_hours[] = $_h;
				for ( $_h = 0; $_h < $_cs_end; $_h++ ) $_cs_hours[] = $_h;
			}
			if ( empty( array_intersect( $_shift_hours_set, $_cs_hours ) ) ) continue;
		}
		$disc     = (string) get_user_meta( $u->ID, 'schedules_discipline', true );
		$title_id = (int) get_user_meta( $u->ID, 'schedules_title', true );
		$is_cto   = (bool) get_user_meta( $u->ID, 'schedules_is_cto', true );
		$title_str = $title_id && isset($_titles_map[$title_id]) ? $_titles_map[$title_id] : '';
		if ( $is_cto ) $title_str .= ( $title_str ? ' / CTO' : 'CTO' );
		$name     = trim( "{$u->first_name} {$u->last_name}" ) ?: $u->display_name;
		$members[ $u->ID ] = [
			'user_id'      => $u->ID,
			'display_name' => $name,
			'first_name'   => $u->first_name,
			'last_name'    => $u->last_name,
			'discipline'   => $disc,
			'title'        => $title_str,
			'type'         => 'custom',
			'custom_hours' => [
				'start' => $_entry['start'] ?? 0,
				'end'   => $_entry['end']   ?? 0,
			],
			'timeoff'      => null,
			'assignments'  => [],
		];
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
 * Sanitizes and returns a 2-week custom_schedule array from POST/data.
 * Expects data['custom_day'][week][dow], ['custom_start'][week][dow], ['custom_end'][week][dow]
 * where week is 1 or 2 and dow is 0–6 (Sun–Sat).
 */
function schedules_sanitize_custom_schedule( array $data ): array {
	$schedule = [ 'week1' => [], 'week2' => [] ];
	foreach ( [ 1 => 'week1', 2 => 'week2' ] as $wk_num => $wk_key ) {
		$days = $data['custom_day'][ $wk_num ] ?? [];
		if ( ! is_array( $days ) ) continue;
		for ( $dow = 0; $dow < 7; $dow++ ) {
			if ( ! empty( $days[ $dow ] ) ) {
				$start = round( min( 23.75, max( 0, (float) ($data['custom_start'][ $wk_num ][ $dow ] ?? 0) ) ) * 4 ) / 4;
				$end   = round( min( 23.75, max( 0, (float) ($data['custom_end'][   $wk_num ][ $dow ] ?? 0) ) ) * 4 ) / 4;
				$schedule[ $wk_key ][ $dow ] = [ 'start' => $start, 'end' => $end ];
			}
		}
	}
	return $schedule;
}

/**
 * Normalizes a stored custom_schedule to the 2-week format.
 * Old format was a flat array keyed 0–6; treat that as week1.
 */
function schedules_normalize_custom_schedule( array $sched ): array {
	if ( array_key_exists( 'week1', $sched ) || array_key_exists( 'week2', $sched ) ) {
		return $sched;
	}
	// Old flat format — promote to week1
	return [ 'week1' => $sched, 'week2' => [] ];
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
	$is_cto   = ! empty( $data['is_cto'] ) ? 1 : 0;

	$manual_priority = $priority === '5' ? '5' : '';

	update_user_meta( $user_id, 'schedules_shift',      $shift );
	update_user_meta( $user_id, 'schedules_discipline', $discipline );
	update_user_meta( $user_id, 'schedules_priority',   $manual_priority );
	update_user_meta( $user_id, 'schedules_pay_rate',   $pay_rate );
	update_user_meta( $user_id, 'schedules_title',      $title_id );
	update_user_meta( $user_id, 'schedules_is_cto',     $is_cto );

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
			'pay_rate'        => current_user_can('admin_schedules') ? (float) get_user_meta( $user->ID, 'schedules_pay_rate', true ) : 0.0,
			'title_id'        => (int) get_user_meta( $user->ID, 'schedules_title',           true ),
			'is_cto'          => (bool) get_user_meta( $user->ID, 'schedules_is_cto',         true ),
			'schedule_type'   => get_user_meta( $user->ID, 'schedules_schedule_type',   true ) ?: 'shift',
			'custom_schedule' => get_user_meta( $user->ID, 'schedules_custom_schedule', true ) ?: [],
			'sick_hours'      => schedules_get_sick_hours( $user->ID ),
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
	$site_url     = home_url('/schedules-member/');

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

		// wp_mail( $user->user_email, $subject, $body ); // TODO: re-enable when out of test mode
	}
}


/**
 * Returns the sick hours for a member in a given year (default: current year).
 * Meta is stored as an array keyed by year string, e.g. ['2025' => 22.0, '2026' => 15.5].
 */
function schedules_get_sick_hours( int $user_id, int $year = 0 ): float {
	if ( ! $year ) $year = (int) date( 'Y' );
	$data = get_user_meta( $user_id, 'schedules_sick_hours', true );
	if ( is_array( $data ) ) return (float) ( $data[ (string) $year ] ?? 0.0 );
	return 0.0; // no data yet
}

/**
 * Sets the sick hours for a member in a given year (default: current year),
 * preserving all other years in the array.
 */
function schedules_set_sick_hours( int $user_id, float $hours, int $year = 0 ): void {
	if ( ! $year ) $year = (int) date( 'Y' );
	$data = get_user_meta( $user_id, 'schedules_sick_hours', true );
	if ( ! is_array( $data ) ) $data = [];
	$data[ (string) $year ] = $hours;
	update_user_meta( $user_id, 'schedules_sick_hours', $data );
}

/**
 * Add hours to a member's YTD sick total and fire a notification for each
 * configured threshold crossed for the first time this year.
 */
function schedules_accumulate_sick_hours( int $user_id, float $hours, int $reviewer_id ): void {
	if ( $hours <= 0 ) return;
	$year  = (int) date( 'Y' );
	$prev  = schedules_get_sick_hours( $user_id, $year );
	$total = $prev + $hours;
	schedules_set_sick_hours( $user_id, $total, $year );

	// Parse configured thresholds (comma-separated, e.g. "30,40,60"); default 30.
	$raw        = schedules_get_config( 'sick_hour_thresholds', '30' );
	$thresholds = array_values( array_filter( array_unique( array_map( 'intval', explode( ',', $raw ) ) ) ) );
	if ( empty( $thresholds ) ) return;
	sort( $thresholds );

	// Load already-notified thresholds for this user/year to avoid duplicate notifications.
	$notified_meta = get_user_meta( $user_id, 'schedules_sick_notified', true );
	if ( ! is_array( $notified_meta ) ) $notified_meta = [];
	$year_key = (string) $year;
	$notified = isset( $notified_meta[ $year_key ] ) ? (array) $notified_meta[ $year_key ] : [];

	$newly_notified = [];
	foreach ( $thresholds as $threshold ) {
		if ( $prev < (float) $threshold && $total >= (float) $threshold && ! in_array( $threshold, $notified, true ) ) {
			schedules_notify_sick_threshold( $user_id, $total, $threshold );
			$newly_notified[] = $threshold;
		}
	}

	if ( ! empty( $newly_notified ) ) {
		$notified_meta[ $year_key ] = array_merge( $notified, $newly_notified );
		update_user_meta( $user_id, 'schedules_sick_notified', $notified_meta );
	}
}

/**
 * Insert a notification for all supervisors on the member's shift (and all admins)
 * when a member crosses a sick-hour threshold.
 */
function schedules_notify_sick_threshold( int $user_id, float $total, int $threshold ): void {
	global $wpdb;

	$member     = get_userdata( $user_id );
	$name       = $member ? trim( $member->first_name . ' ' . $member->last_name ) : "User #{$user_id}";
	$user_shift = get_user_meta( $user_id, 'schedules_shift', true );
	$message    = "{$name} has reached " . number_format( $total, 1 ) . " sick hours YTD ({$threshold}-hr threshold).";

	$shift_sups = $user_shift ? get_users( [
		'role'       => 'schedules_supervisor',
		'meta_key'   => 'schedules_shift',
		'meta_value' => $user_shift,
	] ) : [];
	$admins   = get_users( [ 'role' => 'schedules_admin' ] );
	$seen_ids = [];
	foreach ( array_merge( $shift_sups, $admins ) as $sup ) {
		if ( in_array( $sup->ID, $seen_ids, true ) ) continue;
		$seen_ids[] = $sup->ID;
		$wpdb->insert(
			schedules_table( 'notifications' ),
			[
				'user_id'      => $sup->ID,
				'type'         => 'sick_threshold',
				'related_id'   => $user_id,
				'related_type' => 'member',
				'message'      => $message,
				'is_read'      => 0,
				'created_at'   => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ]
		);
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

	// Snapshot each today-day's roster BEFORE archiving, so the record is frozen
	// at the moment of rollover with the exact people who were on that shift today.
	$today_days = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, shift_letter FROM " . schedules_table('days') . " WHERE schedule_date = %s AND is_archived = 0",
		$today
	), ARRAY_A ) ?: [];

	if ( ! empty( $today_days ) ) {
		// Fetch positions once — same for all shifts
		$positions = $wpdb->get_results(
			"SELECT p.id, p.name, p.required_discipline_id, p.display_order
			 FROM " . schedules_table('positions') . " p WHERE p.is_active = 1",
			ARRAY_A
		) ?: [];

		foreach ( $today_days as $_rd ) {
			schedules_snapshot_roster( (int) $_rd['id'], $today, $_rd['shift_letter'], $positions );
		}
	}

	// Now archive today's days
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

/**
 * Captures the complete roster for a duty day into roster_snapshots.
 * Called at rollover time so past rosters are immutable regardless of future shift changes.
 * Assignments (positions/times) are already immutable in schedules_duty — not duplicated here.
 */
function schedules_snapshot_roster( int $day_id, string $date, string $shift_letter, array $positions ): void {
	global $wpdb;

	// Don't overwrite an existing snapshot
	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . schedules_table('roster_snapshots') . " WHERE day_id = %d",
		$day_id
	) );
	if ( $exists ) return;

	// Build the live roster (empty assignments — those live in schedules_duty permanently)
	$roster = schedules_get_roster_for_day( $date, $shift_letter, $day_id, [], $positions );

	// Store a clean snapshot: drop the assignments array (re-joined from duty table at display time)
	$snapshot = array_map( function( $m ) {
		unset( $m['assignments'] );
		return $m;
	}, $roster );

	$wpdb->insert(
		schedules_table('roster_snapshots'),
		[
			'day_id'      => $day_id,
			'roster_json' => wp_json_encode( $snapshot ),
			'snapped_at'  => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%s' ]
	);
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
		: home_url('/schedules-member/');

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

	// Supervisor proxy-claim: claim on behalf of another member
	$proxy_id = (int) ($_POST['proxy_user_id'] ?? 0);
	if ( $proxy_id && $proxy_id !== $user_id && current_user_can('manage_schedules') ) {
		$user_id = $proxy_id;
	}

	if ( ! $block_id || ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Invalid request.' ] );
	}

	$actor_id = get_current_user_id();
	$result   = schedules_claim_block( $block_id, $user_id );

	if ( $result['success'] ) {
		$_blk = $wpdb->get_row( $wpdb->prepare(
			"SELECT b.start_hour, b.end_hour, d.schedule_date, d.shift_letter
			 FROM " . schedules_table('blocks') . " b
			 JOIN " . schedules_table('days')   . " d ON d.id = b.day_id
			 WHERE b.id = %d", $block_id
		), ARRAY_A );
		if ( $_blk ) {
			$_range = schedules_format_block_time( (int) $_blk['start_hour'], (int) $_blk['end_hour'] );
			if ( $user_id !== $actor_id ) {
				$_for = get_userdata( $user_id );
				$_for_name = $_for ? $_for->display_name : "User #{$user_id}";
				schedules_log( 'ot_claim', "Claimed OT for {$_for_name} — Shift {$_blk['shift_letter']} {$_blk['schedule_date']} {$_range}", [ 'proxy' => true ], $user_id );
			} else {
				schedules_log( 'ot_claim', "Claimed OT — Shift {$_blk['shift_letter']} {$_blk['schedule_date']} {$_range}" );
			}
		}
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}

// --- Get OT claim state for a specific user (supervisor view) ---
add_action( 'wp_ajax_schedules_get_ot_user_state', 'schedules_ajax_get_ot_user_state' );
function schedules_ajax_get_ot_user_state(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$target_id = (int) ($_POST['user_id'] ?? 0);
	if ( ! $target_id ) {
		wp_send_json_error( [ 'message' => 'No user specified.' ] );
	}

	$today = date('Y-m-d');
	$end   = date('Y-m-d', strtotime('+35 days') ); // cover a 5-week window

	$claimed_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT c.block_id
		 FROM " . schedules_table('claims') . " c
		 JOIN " . schedules_table('blocks') . " b ON b.id = c.block_id
		 JOIN " . schedules_table('days')   . " d ON d.id = b.day_id
		 WHERE c.user_id = %d
		   AND d.schedule_date BETWEEN %s AND %s
		   AND d.is_archived = 0",
		$target_id, $today, $end
	) ) ?: [];

	wp_send_json_success( [
		'user_id'           => $target_id,
		'claimed_block_ids' => array_map('intval', $claimed_ids),
	] );
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

	if ( ! empty( $removed ) ) {
		$_blk2 = $wpdb->get_row( $wpdb->prepare(
			"SELECT b.start_hour, b.end_hour, d.schedule_date, d.shift_letter
			 FROM " . schedules_table('blocks') . " b
			 JOIN " . schedules_table('days')   . " d ON d.id = b.day_id
			 WHERE b.id = %d", $removed[0]
		), ARRAY_A );
		if ( $_blk2 ) {
			$_range2 = schedules_format_block_time( (int) $_blk2['start_hour'], (int) $_blk2['end_hour'] );
			schedules_log( 'ot_unclaim', "Undid OT claim — Shift {$_blk2['shift_letter']} {$_blk2['schedule_date']} {$_range2} (" . count($removed) . " block(s))" );
		}
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
		$_badge = sanitize_text_field( $_POST['badge'] ?? $_POST['user_login'] ?? '' );
		$_fn    = sanitize_text_field( $_POST['first_name'] ?? '' );
		$_ln    = sanitize_text_field( $_POST['last_name']  ?? '' );
		$_role  = sanitize_text_field( $_POST['member_role'] ?? 'member' );
		schedules_log( 'member_create', "Created member {$_fn} {$_ln} (badge {$_badge}) as {$_role}", [], (int) ( $result['user_id'] ?? 0 ) );
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
	$is_cto   = ! empty( $_POST['is_cto'] ) ? 1 : 0;

	update_user_meta( $user_id, 'schedules_shift',  $shift );
	update_user_meta( $user_id, 'schedules_title',  $title_id );
	update_user_meta( $user_id, 'schedules_is_cto', $is_cto );

	if ( current_user_can('admin_schedules') ) {
		$pay_rate = (float) ($_POST['pay_rate'] ?? 0);
		update_user_meta( $user_id, 'schedules_pay_rate', $pay_rate );
	}

	$schedule_type   = sanitize_text_field( $_POST['schedule_type'] ?? 'shift' );
	$custom_schedule = schedules_sanitize_custom_schedule( $_POST );
	update_user_meta( $user_id, 'schedules_schedule_type',   $schedule_type );
	update_user_meta( $user_id, 'schedules_custom_schedule', $custom_schedule );

	if ( $discipline ) update_user_meta( $user_id, 'schedules_discipline', $discipline );

	schedules_set_sick_hours( $user_id, max( 0.0, (float) ( $_POST['sick_hours'] ?? 0 ) ) );

	// Only persist manual tier 5 from the form; tier 1 is auto (shift assigned), 2/3/4 are auto by pay rate
	if ( $priority === '5' ) {
		update_user_meta( $user_id, 'schedules_priority', '5' );
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

	$_upd_user = get_userdata( $user_id );
	$_upd_name = $_upd_user ? $_upd_user->display_name : "User #{$user_id}";
	schedules_log( 'member_update', "Updated member {$_upd_name} — role: {$new_role}, shift: {$shift}", [], $user_id );

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
	$cols             = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('shifts') );
	$has_end_hour     = in_array( 'end_hour',     $cols, true );
	$has_week2        = in_array( 'work_days_week2', $cols, true );
	$has_day_schedule = in_array( 'day_schedule', $cols, true );

	$errors = [];

	foreach ( $shift_data as $shift_letter => $data ) {
		$shift_letter = strtoupper( sanitize_text_field( $shift_letter ) );
		if ( ! in_array( $shift_letter, ['A','B','C','D'], true ) ) continue;

		$floor_count  = max( 0, (int) ( $data['member_count'] ?? 0 ) );
		$max_capacity = max( 1, (int) ( $data['max_capacity'] ?? 14 ) );

		// Per-day schedule — validate and encode two-week format
		$day_schedule_json = null;
		if ( $has_day_schedule && ! empty( $data['day_schedule'] ) && is_array( $data['day_schedule'] ) ) {
			$clean_ds = [];
			foreach ( [ 'week1', 'week2' ] as $wk ) {
				if ( empty( $data['day_schedule'][ $wk ] ) || ! is_array( $data['day_schedule'][ $wk ] ) ) continue;
				$clean_ds[ $wk ] = [];
				foreach ( $data['day_schedule'][ $wk ] as $dow => $times ) {
					$dow_int = (int) $dow;
					if ( $dow_int < 0 || $dow_int > 6 ) continue;
					$ds_start = min( 23, max( 0, (int) ( $times['start'] ?? 0 ) ) );
					$ds_end   = min( 23, max( 0, (int) ( $times['end']   ?? 0 ) ) );
					$clean_ds[ $wk ][ $dow_int ] = [ 'start' => $ds_start, 'end' => $ds_end ];
				}
			}
			if ( ! empty( $clean_ds['week1'] ) || ! empty( $clean_ds['week2'] ) ) {
				$day_schedule_json = wp_json_encode( $clean_ds );
			}
		}

		// When day_schedule is present, derive work_days and start/end from it
		if ( $day_schedule_json ) {
			$decoded_ds = json_decode( $day_schedule_json, true );

			// work_days (week 1) — keys of week1
			if ( ! empty( $decoded_ds['week1'] ) ) {
				$w1_keys = array_map( 'intval', array_keys( $decoded_ds['week1'] ) );
				sort( $w1_keys );
				$work_days = implode( ',', $w1_keys );
			}
			// work_days_week2 — keys of week2 (use week1 if week2 identical)
			if ( ! empty( $decoded_ds['week2'] ) ) {
				$w2_keys = array_map( 'intval', array_keys( $decoded_ds['week2'] ) );
				sort( $w2_keys );
				$work_days_w2 = implode( ',', $w2_keys );
			}

			// Canonical start/end from first day of week1
			$first_wk = $decoded_ds['week1'] ?? $decoded_ds['week2'] ?? [];
			ksort( $first_wk );
			$first      = reset( $first_wk );
			$start_hour = (int) ( $first['start'] ?? 6 );
			$end_hour   = (int) ( $first['end']   ?? 18 );
		} else {
			$start_hour = min( 23, max( 0, (int) ( $data['start_hour'] ?? 6 ) ) );
			$end_hour   = min( 23, max( 0, (int) ( $data['end_hour']   ?? 18 ) ) );
		}

		// Work days (week 1 & 2) — only use legacy checkbox fields if no day_schedule was sent
		if ( ! $day_schedule_json ) {
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
		if ( $has_day_schedule && $day_schedule_json !== null ) {
			$update_data['day_schedule'] = $day_schedule_json;
			$update_format[]             = '%s';
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

	schedules_log( 'shift_settings', 'Shift settings saved' );
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

	$day_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, is_archived FROM " . schedules_table('days') . " WHERE schedule_date = %s AND shift_letter = %s",
		$date, $shift_letter
	), ARRAY_A );
	$day_id      = $day_row ? (int) $day_row['id'] : 0;
	$is_archived = $day_row ? (bool) $day_row['is_archived'] : false;

	// Past date with no day record — nothing to show
	if ( $date < date('Y-m-d') && ! $day_id ) {
		wp_send_json_success( [ 'no_record' => true ] );
		return;
	}

	$positions = $wpdb->get_results(
		"SELECT p.id, p.name, p.required_discipline_id, p.display_order,
		        LOWER(REPLACE(IFNULL(d.name,''), ' ', '-')) AS discipline_slug
		 FROM " . schedules_table('positions') . " p
		 LEFT JOIN " . schedules_table('disciplines') . " d ON d.id = p.required_discipline_id AND p.required_discipline_id > 0
		 WHERE p.is_active = 1
		 ORDER BY p.display_order ASC, p.name ASC",
		ARRAY_A
	) ?: [];

	$half_day_blocked_start = null;
	$half_day_blocked_end   = null;
	$shift_end_hour         = null;
	$shift_duration_hours   = 12; // fallback
	$shift_start_hour       = 6;  // fallback

	// Primary: derive actual shift window from blocks generated for this specific day.
	// Uses gap-detection to find true start (handles overnight shifts correctly).
	if ( $day_id ) {
		// Duration = total block count (each block is 1 hour)
		$block_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . schedules_table('blocks') . " WHERE day_id = %d",
			$day_id
		) );
		// Start = block whose start_hour has no preceding block (no other block ends at it)
		$block_start = $wpdb->get_var( $wpdb->prepare(
			"SELECT b1.start_hour
			 FROM " . schedules_table('blocks') . " b1
			 WHERE b1.day_id = %d
			   AND NOT EXISTS (
			       SELECT 1 FROM " . schedules_table('blocks') . " b2
			       WHERE b2.day_id = b1.day_id AND b2.end_hour = b1.start_hour
			   )
			 LIMIT 1",
			$day_id
		) );
		if ( $block_start !== null && $block_count > 0 ) {
			$shift_start_hour     = (int) $block_start;
			$shift_duration_hours = $block_count;
			$shift_end_hour       = ( $shift_start_hour + $shift_duration_hours ) % 24;
		}
	}

	// Secondary: if no day record exists yet (future date with no blocks), fall back to day_schedule.
	if ( ! $day_id ) {
		$_hd_cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('shifts') );
		if ( in_array( 'day_schedule', $_hd_cols ) ) {
			$ds_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT day_schedule FROM " . schedules_table('shifts') . " WHERE shift_letter = %s",
				$shift_letter
			), ARRAY_A );
			if ( $ds_row && ! empty( $ds_row['day_schedule'] ) ) {
				$ds       = json_decode( $ds_row['day_schedule'], true );
				$date_dow = (int) date( 'w', strtotime( $date ) );
				$dow_key  = (string) $date_dow;
				if ( is_array( $ds ) ) {
					$cycle_anchor = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';
					$cycle_week   = schedules_get_cycle_week( $date, $cycle_anchor );
					$week_key     = $cycle_week === 0 ? 'week1' : 'week2';
					$week_ds      = isset( $ds['week1'] ) || isset( $ds['week2'] ) ? ( $ds[ $week_key ] ?? [] ) : $ds;
					if ( isset( $week_ds[ $dow_key ] ) ) {
						$shift_start_hour     = (int) $week_ds[ $dow_key ]['start'];
						$shift_end_hour       = (int) $week_ds[ $dow_key ]['end'];
						$shift_duration_hours = $shift_end_hour > $shift_start_hour
							? $shift_end_hour - $shift_start_hour
							: 24 - $shift_start_hour + $shift_end_hour;
					}
				}
			}
		} else {
			// Legacy fallback: shifts table canonical start_hour
			$shift_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT start_hour FROM " . schedules_table('shifts') . " WHERE shift_letter = %s",
				$shift_letter
			), ARRAY_A );
			if ( $shift_row ) $shift_start_hour = (int) $shift_row['start_hour'];
		}
	}

	$assignments = $day_id ? schedules_get_duty_for_day( $day_id ) : [];

	// --- Roster source: snapshot (archived days) vs. live computation (today/future) ---
	if ( $is_archived && $day_id ) {
		// Use the immutable snapshot written at rollover time
		$_snap_json = $wpdb->get_var( $wpdb->prepare(
			"SELECT roster_json FROM " . schedules_table('roster_snapshots') . " WHERE day_id = %d",
			$day_id
		) );
		if ( $_snap_json ) {
			$_snap_roster = json_decode( $_snap_json, true ) ?: [];
			// Re-join assignments from the permanent duty table (already immutable)
			$_pos_map = [];
			foreach ( $positions as $_p ) $_pos_map[ (int) $_p['id'] ] = $_p['name'];
			foreach ( $_snap_roster as &$_sm ) {
				$_sm['assignments'] = [];
				foreach ( $assignments as $_a ) {
					if ( (int) $_a['user_id'] !== (int) $_sm['user_id'] ) continue;
					$_sm['assignments'][] = [
						'position'   => $_pos_map[ (int) $_a['position_id'] ] ?? '',
						'start_time' => substr( $_a['start_time'], 0, 5 ),
						'end_time'   => substr( $_a['end_time'],   0, 5 ),
					];
				}
			}
			unset( $_sm );
			$roster  = array_values( $_snap_roster );
			$members = []; // archived — no drag-to-seat
		} else {
			// Pre-2.18 archived day — no snapshot available
			wp_send_json_success( [ 'no_record' => true ] );
			return;
		}
		$addable_members = [];
	} else {
		// Today / future: compute live from current schedules
		$roster  = schedules_get_roster_for_day( $date, $shift_letter, $day_id, $assignments, $positions );
		$members = schedules_get_available_members_for_day( $date, $shift_letter );
		$all_m   = schedules_get_all_members();
		$addable_members = array_values( array_map( function( $m ) {
			return [ 'user_id' => $m['user_id'], 'display_name' => $m['display_name'] ];
		}, $all_m ) );
	}

	wp_send_json_success( [
		'day_id'                 => $day_id,
		'shift_letter'           => $shift_letter,
		'date'                   => $date,
		'shift_start_hour'       => $shift_start_hour,
		'shift_end_hour'         => $shift_end_hour,
		'shift_duration_hours'   => $shift_duration_hours,
		'half_day_blocked_start' => $half_day_blocked_start,
		'half_day_blocked_end'   => $half_day_blocked_end,
		'positions'              => $positions,
		'members'                => $members,
		'assignments'            => $assignments,
		'roster'                 => $roster,
		'is_archived'            => $is_archived,
		'is_locked'              => $is_archived || schedules_is_shift_ended( $date, $shift_letter ),
		'addable_members'        => $addable_members,
	] );
}

// --- Supervisor Add OT Member (creates claim records, bypassing capacity) ---
add_action( 'wp_ajax_schedules_supervisor_add_ot', 'schedules_ajax_supervisor_add_ot' );
function schedules_ajax_supervisor_add_ot(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$date       = sanitize_text_field( $_POST['date']       ?? '' );
	$shift      = strtoupper( sanitize_text_field( $_POST['shift'] ?? '' ) );
	$user_id    = (int) ( $_POST['user_id']    ?? 0 );
	$start_time = sanitize_text_field( $_POST['start_time'] ?? '' );
	$end_time   = sanitize_text_field( $_POST['end_time']   ?? '' );

	if ( ! $date || ! in_array( $shift, ['A','B','C','D'], true ) || ! $user_id || ! $start_time || ! $end_time || $start_time === $end_time ) {
		wp_send_json_error( [ 'message' => 'Invalid parameters.' ] );
	}

	// Parse HH:MM → block-hour boundaries (floor start, ceiling end)
	$sp    = explode( ':', $start_time );
	$ep    = explode( ':', $end_time );
	$s_min = (int) $sp[0] * 60 + (int) ( $sp[1] ?? 0 );
	$e_min = (int) $ep[0] * 60 + (int) ( $ep[1] ?? 0 );
	$s_h   = ( (int) floor( $s_min / 60 ) ) % 24;
	$e_h   = ( $e_min % 60 > 0 ) ? ( (int) ceil( $e_min / 60 ) ) % 24 : ( $e_min / 60 ) % 24;

	// Get shift config
	$shift_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('shifts') . " WHERE shift_letter = %s", $shift
	), ARRAY_A );

	if ( ! $shift_row ) {
		wp_send_json_error( [ 'message' => 'Shift not configured.' ] );
	}

	// Get the existing day, or generate it if it doesn't exist yet (respects work_days rotation)
	$day_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . schedules_table('days') . " WHERE schedule_date = %s AND shift_letter = %s",
		$date, $shift
	) );

	if ( ! $day_id ) {
		schedules_generate_day( $date );
		$day_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . schedules_table('days') . " WHERE schedule_date = %s AND shift_letter = %s",
			$date, $shift
		) );
	}

	if ( ! $day_id ) {
		wp_send_json_error( [ 'message' => 'Shift ' . esc_html( $shift ) . ' is not scheduled for this date. Verify the date and shift are correct.' ] );
	}

	// Get blocks and filter to requested time range (handles midnight-crossing)
	$all_blocks = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, start_hour FROM " . schedules_table('blocks') . " WHERE day_id = %d",
		$day_id
	), ARRAY_A ) ?: [];

	$claim_ids = [];
	foreach ( $all_blocks as $b ) {
		$bh = (int) $b['start_hour'];
		if ( $e_h > $s_h ) {
			if ( $bh >= $s_h && $bh < $e_h ) $claim_ids[] = (int) $b['id'];
		} elseif ( $e_h === 0 ) {
			if ( $bh >= $s_h ) $claim_ids[] = (int) $b['id'];
		} else {
			if ( $bh >= $s_h || $bh < $e_h ) $claim_ids[] = (int) $b['id'];
		}
	}

	if ( empty( $claim_ids ) ) {
		wp_send_json_error( [ 'message' => 'No OT blocks found for that time range.' ] );
	}

	$inserted = 0;
	foreach ( $claim_ids as $block_id ) {
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . schedules_table('claims') . " WHERE user_id = %d AND block_id = %d",
			$user_id, $block_id
		) );
		if ( $exists ) continue;
		$wpdb->insert( schedules_table('claims'), [ 'user_id' => $user_id, 'block_id' => $block_id ], [ '%d', '%d' ] );
		$inserted++;
	}

	wp_send_json_success( [ 'message' => 'OT member added.', 'claimed' => $inserted ] );
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

	$is_trainee = (int) ($_POST['is_sub'] ?? 0) ? 1 : 0;

	// Validate discipline match (skipped for sub-line assignments)
	if ( ! $is_trainee ) {
		$req_disc = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.required_discipline_id,
			        LOWER(REPLACE(IFNULL(d.name,''), ' ', '-')) AS discipline_slug
			 FROM " . schedules_table('positions') . " p
			 LEFT JOIN " . schedules_table('disciplines') . " d ON d.id = p.required_discipline_id
			 WHERE p.id = %d",
			$position_id
		), ARRAY_A );
		if ( $req_disc && (int) $req_disc['required_discipline_id'] > 0 ) {
			$user_disc = (string) get_user_meta( $user_id, 'schedules_discipline', true );
			$disc_arr  = array_filter( array_map( 'trim', explode( ',', $user_disc ) ) );
			if ( ! in_array( $req_disc['discipline_slug'], $disc_arr, true ) ) {
				wp_send_json_error( [ 'message' => 'This member does not have the required discipline for this position.' ] );
			}
		}
	}

	// Normalize times to HH:MM:SS
	$start_time = strlen($start_time) === 5 ? $start_time . ':00' : $start_time;
	$end_time   = strlen($end_time)   === 5 ? $end_time   . ':00' : $end_time;

	// Check for scheduling conflict — any existing assignment (regular or trainee) overlapping this time
	// Skipped for trainee/sub assignments since they intentionally overlap
	if ( ! $is_trainee && $day_id ) {
		$conflict = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . schedules_table('duty') .
			" WHERE day_id = %d AND user_id = %d AND is_voided = 0
			   AND start_time < %s AND end_time > %s",
			$day_id, $user_id, $end_time, $start_time
		) );
		if ( $conflict ) {
			wp_send_json_error( [ 'message' => 'This member is already assigned during part of that time.' ] );
		}
	}

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
			'is_trainee'  => $is_trainee,
		],
		[ '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%d' ]
	);

	$new_id = (int) $wpdb->insert_id;

	$u = get_userdata( $user_id );
	$name = $u ? ( trim("{$u->first_name} {$u->last_name}") ?: $u->display_name ) : '';

	$_log_start = date( 'Hi', strtotime( $start_time ) );
	$_log_end   = date( 'Hi', strtotime( $end_time ) );
	schedules_log( 'duty_add', "Assigned {$name} to duty position {$position_id} — Shift {$shift} {$date} {$_log_start}–{$_log_end}", [], $user_id );

	wp_send_json_success( [
		'assignment' => [
			'id'          => $new_id,
			'day_id'      => $day_id,
			'position_id' => $position_id,
			'user_id'     => $user_id,
			'start_time'  => $start_time,
			'end_time'    => $end_time,
			'display_name'=> $name,
			'is_trainee'  => $is_trainee,
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

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT day_id, position_id, is_trainee FROM " . schedules_table('duty') . " WHERE id = %d",
		$id
	), ARRAY_A );

	$voided_by = get_current_user_id();
	$now       = current_time('mysql');

	$wpdb->update(
		schedules_table('duty'),
		[ 'is_voided' => 1, 'voided_by' => $voided_by, 'voided_at' => $now ],
		[ 'id' => $id ],
		[ '%d', '%d', '%s' ],
		[ '%d' ]
	);

	// If this was a main assignment, also void any sub-row (trainee) assignments for the same position
	$voided_sub_ids = [];
	if ( $row && ! (int) $row['is_trainee'] ) {
		$sub_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM " . schedules_table('duty') .
			" WHERE day_id = %d AND position_id = %d AND is_trainee = 1 AND is_voided = 0",
			(int) $row['day_id'], (int) $row['position_id']
		) );
		if ( $sub_ids ) {
			$placeholders = implode( ',', array_fill( 0, count($sub_ids), '%d' ) );
			$wpdb->query( $wpdb->prepare(
				"UPDATE " . schedules_table('duty') .
				" SET is_voided = 1, voided_by = %d, voided_at = %s WHERE id IN ($placeholders)",
				array_merge( [ $voided_by, $now ], array_map('intval', $sub_ids) )
			) );
			$voided_sub_ids = array_map('intval', $sub_ids);
		}
	}

	if ( $assignment_day ) {
		schedules_log( 'duty_remove', "Removed duty assignment #{$id} — Shift {$assignment_day['shift_letter']} {$assignment_day['schedule_date']}" );
	}
	wp_send_json_success( [ 'message' => 'Assignment removed.', 'voided_sub_ids' => $voided_sub_ids ] );
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

	// Validate discipline match (skipped for sub-line assignments)
	if ( ! (int) $row['is_trainee'] ) {
		$req_disc = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.required_discipline_id,
			        LOWER(REPLACE(IFNULL(d.name,''), ' ', '-')) AS discipline_slug
			 FROM " . schedules_table('positions') . " p
			 LEFT JOIN " . schedules_table('disciplines') . " d ON d.id = p.required_discipline_id
			 WHERE p.id = %d",
			(int) $row['position_id']
		), ARRAY_A );
		if ( $req_disc && (int) $req_disc['required_discipline_id'] > 0 ) {
			$user_disc = (string) get_user_meta( $user_id, 'schedules_discipline', true );
			$disc_arr  = array_filter( array_map( 'trim', explode( ',', $user_disc ) ) );
			if ( ! in_array( $req_disc['discipline_slug'], $disc_arr, true ) ) {
				wp_send_json_error( [ 'message' => 'This member does not have the required discipline for this position.' ] );
			}
		}
	}

	// Check for scheduling conflict (skipped for trainee assignments)
	if ( ! (int) $row['is_trainee'] ) {
		$conflict = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . schedules_table('duty') .
			" WHERE day_id = %d AND user_id = %d AND is_voided = 0 AND id != %d
			   AND start_time < %s AND end_time > %s",
			(int) $row['day_id'], $user_id, $id, $end_time, $start_time
		) );
		if ( $conflict ) {
			wp_send_json_error( [ 'message' => 'This member is already assigned during part of that time.' ] );
		}
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
			'is_trainee'   => (int) $row['is_trainee'],
		],
	] );
}

// --- Get Shifts Working on a Date (for member duty dropdown) ---
add_action( 'wp_ajax_schedules_get_shifts_for_date', 'schedules_ajax_get_shifts_for_date' );
function schedules_ajax_get_shifts_for_date(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! is_user_logged_in() ) { wp_send_json_error(); }

	global $wpdb;
	$date = sanitize_text_field( $_POST['date'] ?? date('Y-m-d') );
	if ( ! $date ) $date = date('Y-m-d');

	$shifts = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT shift_letter FROM " . schedules_table('days') .
		" WHERE schedule_date = %s ORDER BY shift_letter ASC",
		$date
	) );

	wp_send_json_success( [ 'shifts' => $shifts ] );
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
	if ( ! $date ) $date = date('Y-m-d');

	// Use posted shift if provided, else fall back to user's assigned shift
	$posted_shift = strtoupper( sanitize_text_field( $_POST['shift'] ?? '' ) );
	$shift        = $posted_shift ?: schedules_get_user_shift( $user_id );

	if ( ! $shift ) {
		wp_send_json_error( [ 'message' => 'No shift assigned.' ] );
	}

	$day_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, is_archived FROM " . schedules_table('days') . " WHERE schedule_date = %s AND shift_letter = %s",
		$date, $shift
	), ARRAY_A );
	$day_id      = $day_row ? (int) $day_row['id'] : 0;
	$is_archived = $day_row ? (bool) $day_row['is_archived'] : false;

	// Past date with no day record — nothing to show
	if ( $date < date('Y-m-d') && ! $day_id ) {
		wp_send_json_success( [ 'no_record' => true ] );
		return;
	}

	$positions = $wpdb->get_results(
		"SELECT id, name, display_order FROM " . schedules_table('positions') . "
		 WHERE is_active = 1 ORDER BY display_order ASC, name ASC",
		ARRAY_A
	) ?: [];

	// Derive actual shift window from blocks (same ground-truth approach as supervisor endpoint)
	$half_day_blocked_start = null;
	$half_day_blocked_end   = null;
	$shift_end_hour         = null;
	$shift_duration_hours   = 12;
	$shift_start_hour       = 6;

	if ( $day_id ) {
		$block_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . schedules_table('blocks') . " WHERE day_id = %d",
			$day_id
		) );
		$block_start = $wpdb->get_var( $wpdb->prepare(
			"SELECT b1.start_hour FROM " . schedules_table('blocks') . " b1
			 WHERE b1.day_id = %d
			   AND NOT EXISTS (
			       SELECT 1 FROM " . schedules_table('blocks') . " b2
			       WHERE b2.day_id = b1.day_id AND b2.end_hour = b1.start_hour
			   ) LIMIT 1",
			$day_id
		) );
		if ( $block_start !== null && $block_count > 0 ) {
			$shift_start_hour     = (int) $block_start;
			$shift_duration_hours = $block_count;
			$shift_end_hour       = ( $shift_start_hour + $shift_duration_hours ) % 24;
		}
	} else {
		$_hd_cols = $wpdb->get_col( "SHOW COLUMNS FROM " . schedules_table('shifts') );
		if ( in_array( 'day_schedule', $_hd_cols ) ) {
			$ds_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT day_schedule FROM " . schedules_table('shifts') . " WHERE shift_letter = %s", $shift
			), ARRAY_A );
			if ( $ds_row && ! empty( $ds_row['day_schedule'] ) ) {
				$ds       = json_decode( $ds_row['day_schedule'], true );
				$date_dow = (int) date( 'w', strtotime( $date ) );
				$dow_key  = (string) $date_dow;
				if ( is_array( $ds ) ) {
					$cycle_anchor = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';
					$cycle_week   = schedules_get_cycle_week( $date, $cycle_anchor );
					$week_key     = $cycle_week === 0 ? 'week1' : 'week2';
					$week_ds      = isset( $ds['week1'] ) || isset( $ds['week2'] ) ? ( $ds[ $week_key ] ?? [] ) : $ds;
					if ( isset( $week_ds[ $dow_key ] ) ) {
						$shift_start_hour     = (int) $week_ds[ $dow_key ]['start'];
						$shift_end_hour       = (int) $week_ds[ $dow_key ]['end'];
						$shift_duration_hours = $shift_end_hour > $shift_start_hour
							? $shift_end_hour - $shift_start_hour
							: 24 - $shift_start_hour + $shift_end_hour;
					}
				}
			}
		}
	}

	$assignments = $day_id ? schedules_get_duty_for_day( $day_id ) : [];

	// Archived days: use immutable snapshot roster. Today/future: compute live.
	if ( $is_archived && $day_id ) {
		$_snap_json = $wpdb->get_var( $wpdb->prepare(
			"SELECT roster_json FROM " . schedules_table('roster_snapshots') . " WHERE day_id = %d",
			$day_id
		) );
		if ( $_snap_json ) {
			$_snap_roster = json_decode( $_snap_json, true ) ?: [];
			$_pos_map     = array_column( $positions, 'name', 'id' );
			foreach ( $_snap_roster as &$_sm ) {
				$_sm['assignments'] = [];
				foreach ( $assignments as $_a ) {
					if ( (int) $_a['user_id'] !== (int) $_sm['user_id'] ) continue;
					$_sm['assignments'][] = [
						'position'   => $_pos_map[ (int) $_a['position_id'] ] ?? '',
						'start_time' => substr( $_a['start_time'], 0, 5 ),
						'end_time'   => substr( $_a['end_time'],   0, 5 ),
					];
				}
			}
			unset( $_sm );
			$roster = array_values( $_snap_roster );
		} else {
			// Pre-2.18 archived day — no snapshot available
			wp_send_json_success( [ 'no_record' => true ] );
			return;
		}
	} else {
		$roster = schedules_get_roster_for_day( $date, $shift, $day_id, $assignments, $positions );
	}

	wp_send_json_success( [
		'date'                   => $date,
		'shift'                  => $shift,
		'shift_start_hour'       => $shift_start_hour,
		'shift_end_hour'         => $shift_end_hour,
		'shift_duration_hours'   => $shift_duration_hours,
		'half_day_blocked_start' => $half_day_blocked_start,
		'half_day_blocked_end'   => $half_day_blocked_end,
		'positions'              => $positions,
		'assignments'            => $assignments,
		'roster'                 => $roster,
		'is_locked'              => $is_archived || schedules_is_shift_ended( $date, $shift ),
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

	$sched_increment = (int) ($_POST['schedule_time_increment'] ?? 60);
	if ( ! in_array( $sched_increment, [ 30, 60 ], true ) ) $sched_increment = 60;
	schedules_set_config( 'schedule_time_increment', (string) $sched_increment );

	$sup_can_claim = isset($_POST['supervisors_can_claim_ot']) && $_POST['supervisors_can_claim_ot'] === '1' ? '1' : '0';
	schedules_set_config( 'supervisors_can_claim_ot', $sup_can_claim );

	$min_ot_hours = max( 0, (int) ($_POST['ot_min_claim_hours'] ?? 0) );
	schedules_set_config( 'ot_min_claim_hours', (string) $min_ot_hours );

	$tier2_max = max( 0.0, (float) ($_POST['ot_priority_2_max'] ?? 0) );
	$tier3_max = max( 0.0, (float) ($_POST['ot_priority_3_max'] ?? 0) );
	schedules_set_config( 'ot_priority_2_max', number_format( $tier2_max, 2, '.', '' ) );
	schedules_set_config( 'ot_priority_3_max', number_format( $tier3_max, 2, '.', '' ) );

	$raw_thresholds = sanitize_text_field( $_POST['sick_hour_thresholds'] ?? '30' );
	$parsed         = array_values( array_filter( array_unique( array_map( 'intval', explode( ',', $raw_thresholds ) ) ) ) );
	sort( $parsed );
	schedules_set_config( 'sick_hour_thresholds', $parsed ? implode( ',', $parsed ) : '30' );

	// Immediately reshuffle with the new cutoffs
	schedules_recalculate_priorities();

	schedules_log( 'app_settings', 'App settings updated' );
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
		'schedule_time_increment'  => schedules_get_config( 'schedule_time_increment', '60' ),
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
	wp_send_json_success( [ 'message' => 'Discipline deactivated.' ] );
}

// --- Purge Discipline (admin only — hard delete) ---
add_action( 'wp_ajax_schedules_purge_discipline', 'schedules_ajax_purge_discipline' );
function schedules_ajax_purge_discipline(): void {
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
		"SELECT COUNT(*) FROM " . schedules_table('positions') . " WHERE required_discipline_id = %d",
		$id
	) );
	if ( $in_use > 0 ) {
		wp_send_json_error( [ 'message' => 'Cannot delete: one or more positions require this discipline.' ] );
	}

	$wpdb->delete( schedules_table('disciplines'), [ 'id' => $id ], [ '%d' ] );
	wp_send_json_success( [ 'message' => 'Discipline permanently deleted.' ] );
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
	wp_send_json_success( [ 'message' => 'Position deactivated.' ] );
}

// --- Purge Position (admin only — hard delete) ---
add_action( 'wp_ajax_schedules_purge_position', 'schedules_ajax_purge_position' );
function schedules_ajax_purge_position(): void {
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
		"SELECT COUNT(*) FROM " . schedules_table('duty') . " WHERE position_id = %d AND is_voided = 0",
		$id
	) );
	if ( $in_use > 0 ) {
		wp_send_json_error( [ 'message' => 'Cannot delete: this position has existing duty assignments.' ] );
	}

	$wpdb->delete( schedules_table('positions'), [ 'id' => $id ], [ '%d' ] );
	wp_send_json_success( [ 'message' => 'Position permanently deleted.' ] );
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
	wp_send_json_success( [ 'message' => 'Title deactivated.' ] );
}

// --- Purge Title (admin only — hard delete) ---
add_action( 'wp_ajax_schedules_purge_title', 'schedules_ajax_purge_title' );
function schedules_ajax_purge_title(): void {
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
		"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'schedules_title' AND meta_value = %s",
		(string) $id
	) );
	if ( $in_use > 0 ) {
		wp_send_json_error( [ 'message' => 'Cannot delete: one or more members are assigned this title.' ] );
	}

	$wpdb->delete( schedules_table('titles'), [ 'id' => $id ], [ '%d' ] );
	wp_send_json_success( [ 'message' => 'Title permanently deleted.' ] );
}


/*--------------------------------------------------------------
# Personal Schedule Calendar
--------------------------------------------------------------*/

// --- Get Notifications ---
add_action( 'wp_ajax_schedules_get_notifications', 'schedules_ajax_get_notifications' );
function schedules_ajax_get_notifications(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$user_id          = get_current_user_id();
	$include_archived = ! empty( $_POST['include_archived'] );

	$archived_clause = $include_archived ? '' : 'AND is_archived = 0';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('notifications') . "
		 WHERE user_id = %d {$archived_clause}
		 ORDER BY created_at DESC
		 LIMIT 100",
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
			'is_archived'  => (int) $r['is_archived'],
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

// --- Archive Notifications ---
add_action( 'wp_ajax_schedules_archive_notifications', 'schedules_ajax_archive_notifications' );
function schedules_ajax_archive_notifications(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$user_id    = get_current_user_id();
	$archive_id = (int) ( $_POST['notification_id'] ?? 0 ); // 0 = archive all

	if ( $archive_id ) {
		$wpdb->update(
			schedules_table('notifications'),
			[ 'is_archived' => 1 ],
			[ 'id' => $archive_id, 'user_id' => $user_id ],
			[ '%d' ],
			[ '%d', '%d' ]
		);
	} else {
		$wpdb->update(
			schedules_table('notifications'),
			[ 'is_archived' => 1 ],
			[ 'user_id' => $user_id, 'is_archived' => 0 ],
			[ '%d' ],
			[ '%d', '%d' ]
		);
	}

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

	if ( ! $timeoff_id || ! in_array( $decision, [ 'approved', 'coverage', 'denied' ], true ) ) {
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

	// Build human-readable status word and notification message
	$reviewer      = get_userdata( $current_user_id );
	$reviewer_name = $reviewer ? trim( $reviewer->first_name . ' ' . $reviewer->last_name ) : 'A supervisor';
	$type_label    = strtoupper( $request['type'] );
	$date_label    = date( 'M j, Y', strtotime( $request['start_date'] ) );

	if ( $decision === 'approved' ) {
		$status_word    = 'approved';
		$member_message = "{$type_label} request for {$date_label} has been approved by {$reviewer_name}.";
	} elseif ( $decision === 'coverage' ) {
		$status_word    = 'approved (pending coverage)';
		$member_message = "{$type_label} request for {$date_label} has been approved (pending coverage) by {$reviewer_name}.";
	} else {
		$status_word    = 'denied';
		$member_message = "{$type_label} request for {$date_label} has been denied by {$reviewer_name}.";
	}

	// Notify the member of the decision
	$wpdb->insert(
		schedules_table('notifications'),
		[
			'user_id'      => (int) $request['user_id'],
			'type'         => 'timeoff_' . $decision,
			'related_id'   => $timeoff_id,
			'related_type' => 'timeoff',
			'message'      => $member_message,
			'is_read'      => 0,
			'created_at'   => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ]
	);

	// Mark the supervisor's original timeoff_request notification as reviewed
	// so the review buttons don't reappear the next time the panel is opened.
	$wpdb->update(
		schedules_table('notifications'),
		[ 'type' => 'timeoff_reviewed' ],
		[ 'related_id' => $timeoff_id, 'related_type' => 'timeoff', 'type' => 'timeoff_request' ],
		[ '%s' ],
		[ '%d', '%s', '%s' ]
	);

	// Add OT slot for approved requests. Wrapped in output buffering so that
	// any stray output from wp_mail inside schedules_notify_new_slot cannot
	// corrupt the JSON response that follows.
	if ( $decision === 'approved' ) {
		ob_start();
		schedules_timeoff_add_ot_slot( (int) $request['user_id'], $request['start_date'], $current_user_id, $request['type'] ?? 'pdo' );
		ob_end_clean();

		// Accumulate YTD sick hours
		if ( $request['type'] === 'sick' && (float) $request['hours'] > 0 ) {
			schedules_accumulate_sick_hours( (int) $request['user_id'], (float) $request['hours'], $current_user_id );
		}
	}

	$_member = get_userdata( (int) $request['user_id'] );
	$_mname  = $_member ? $_member->display_name : "User #{$request['user_id']}";
	schedules_log( 'timeoff_review', "{$type_label} request for {$_mname} ({$date_label}): {$status_word}", [], (int) $request['user_id'] );

	wp_send_json_success( [ 'message' => "{$type_label} request {$status_word}." ] );
}

// --- Get Pending Time-Off Requests (supervisor approvals view) ---
add_action( 'wp_ajax_schedules_get_pending_timeoff', 'schedules_ajax_get_pending_timeoff' );
function schedules_ajax_get_pending_timeoff(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;

	$rows = $wpdb->get_results(
		"SELECT t.id, t.user_id, t.type, t.start_date, t.end_date, t.hours, t.notes,
		        u.display_name,
		        um_f.meta_value AS first_name,
		        um_l.meta_value AS last_name
		 FROM " . schedules_table('timeoff') . " t
		 JOIN {$wpdb->users} u ON u.ID = t.user_id
		 LEFT JOIN {$wpdb->usermeta} um_f ON um_f.user_id = t.user_id AND um_f.meta_key = 'first_name'
		 LEFT JOIN {$wpdb->usermeta} um_l ON um_l.user_id = t.user_id AND um_l.meta_key = 'last_name'
		 WHERE t.status = 'pending'
		 ORDER BY t.start_date ASC, t.id ASC",
		ARRAY_A
	) ?: [];

	$requests = [];
	foreach ( $rows as $r ) {
		$name = trim( ($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '') );
		if ( ! $name ) $name = $r['display_name'];
		$requests[] = [
			'id'         => (int) $r['id'],
			'user_id'    => (int) $r['user_id'],
			'member'     => $name,
			'type'       => $r['type'],
			'start_date' => $r['start_date'],
			'end_date'   => $r['end_date'],
			'hours'      => (float) $r['hours'],
			'notes'      => $r['notes'] ?: '',
		];
	}

	wp_send_json_success( [ 'requests' => $requests ] );
}

// --- Submit Time Off Request ---
add_action( 'wp_ajax_schedules_submit_timeoff', 'schedules_ajax_submit_timeoff' );
function schedules_ajax_submit_timeoff(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$current_user_id = get_current_user_id();
	$target_user_id  = (int) ( $_POST['user_id'] ?? 0 );
	$start_date      = sanitize_text_field( $_POST['start_date'] ?? $_POST['date'] ?? '' );
	$end_date        = sanitize_text_field( $_POST['end_date']   ?? $start_date );
	$type            = sanitize_text_field( $_POST['type']       ?? '' );
	$start_time      = sanitize_text_field( $_POST['start_time'] ?? '' );
	$end_time        = sanitize_text_field( $_POST['end_time']   ?? '' );
	$notes           = sanitize_textarea_field( $_POST['notes']  ?? '' );

	if ( ! $target_user_id || ! $start_date || ! $type ) {
		wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
	}
	if ( ! in_array( $type, [ 'pdo', 'sick', 'fmla' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid time off type.' ] );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
		wp_send_json_error( [ 'message' => 'Invalid date.' ] );
	}
	if ( $end_date < $start_date ) $end_date = $start_date;
	if ( $start_time && ! preg_match( '/^\d{2}:\d{2}$/', $start_time ) ) {
		wp_send_json_error( [ 'message' => 'Invalid start time.' ] );
	}
	if ( $end_time && ! preg_match( '/^\d{2}:\d{2}$/', $end_time ) ) {
		wp_send_json_error( [ 'message' => 'Invalid end time.' ] );
	}

	// Members can only request for themselves; supervisors/admins for anyone
	if ( $target_user_id !== $current_user_id && ! current_user_can( 'manage_schedules' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	// Calculate hours from start/end times
	$hours = 0;
	if ( $start_time && $end_time ) {
		list( $sh, $sm ) = array_map( 'intval', explode( ':', $start_time ) );
		list( $eh, $em ) = array_map( 'intval', explode( ':', $end_time ) );
		$start_mins = $sh * 60 + $sm;
		$end_mins   = $eh * 60 + $em;
		$hours = $end_mins > $start_mins
			? ( $end_mins - $start_mins ) / 60
			: ( 1440 - $start_mins + $end_mins ) / 60;
	}

	$status       = current_user_can( 'manage_schedules' ) ? 'approved' : 'pending';
	$created      = 0;
	$skipped      = 0;
	$first_id     = null;
	$current_dt   = new DateTime( $start_date );
	$end_dt       = new DateTime( $end_date );

	while ( $current_dt <= $end_dt ) {
		$day = $current_dt->format( 'Y-m-d' );

		// Skip days that already have a request of this type
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . schedules_table('timeoff') . "
			 WHERE user_id = %d AND start_date = %s AND type = %s AND status NOT IN ('denied', 'coverage')",
			$target_user_id, $day, $type
		) );
		if ( $existing ) {
			$skipped++;
			$current_dt->modify( '+1 day' );
			continue;
		}

		$wpdb->insert(
			schedules_table('timeoff'),
			[
				'user_id'      => $target_user_id,
				'type'         => $type,
				'start_date'   => $day,
				'end_date'     => $day,
				'start_time'   => $start_time ?: null,
				'end_time'     => $end_time   ?: null,
				'hours'        => $hours,
				'status'       => $status,
				'notes'        => $notes,
				'reviewed_by'  => $status === 'approved' ? $current_user_id : null,
				'reviewed_at'  => $status === 'approved' ? current_time( 'mysql' ) : null,
				'requested_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s' ]
		);

		if ( $wpdb->insert_id ) {
			if ( ! $first_id ) $first_id = $wpdb->insert_id;
			$created++;
			if ( $status === 'approved' ) {
				schedules_timeoff_add_ot_slot( $target_user_id, $day, $current_user_id, $type );
				if ( $type === 'sick' && $hours > 0 ) {
					schedules_accumulate_sick_hours( $target_user_id, (float) $hours, $current_user_id );
				}
			}
		}

		$current_dt->modify( '+1 day' );
	}

	if ( ! $created && ! $skipped ) {
		wp_send_json_error( [ 'message' => 'No valid dates to process.' ] );
	}

	// Send one notification summarising the range (pending requests only)
	if ( $status === 'pending' && $created > 0 && $first_id ) {
		$member       = get_userdata( $target_user_id );
		$member_name  = $member ? trim( $member->first_name . ' ' . $member->last_name ) : "User #{$target_user_id}";
		$member_shift = get_user_meta( $target_user_id, 'schedules_shift', true );
		$type_label   = strtoupper( $type );
		$time_label   = ( $start_time && $end_time ) ? ' (' . $start_time . '–' . $end_time . ')' : '';
		if ( $start_date === $end_date ) {
			$range_label = date( 'M j, Y', strtotime( $start_date ) );
		} else {
			$range_label = date( 'M j', strtotime( $start_date ) ) . '–' . date( 'M j, Y', strtotime( $end_date ) );
		}
		$day_word = $created === 1 ? '1 day' : "{$created} days";
		$message  = "{$member_name} is requesting {$type_label} for {$range_label} ({$day_word}){$time_label}.";

		$shift_sups = $member_shift ? get_users( [
			'role'       => 'schedules_supervisor',
			'meta_key'   => 'schedules_shift',
			'meta_value' => $member_shift,
		] ) : [];
		$admins      = get_users( [ 'role' => 'schedules_admin' ] );
		$seen_ids    = [];
		$notif_users = [];
		foreach ( array_merge( $shift_sups, $admins ) as $u ) {
			if ( ! in_array( $u->ID, $seen_ids, true ) ) {
				$seen_ids[]    = $u->ID;
				$notif_users[] = $u;
			}
		}
		foreach ( $notif_users as $sup ) {
			$wpdb->insert(
				schedules_table('notifications'),
				[
					'user_id'      => $sup->ID,
					'type'         => 'timeoff_request',
					'related_id'   => $first_id,
					'related_type' => 'timeoff',
					'message'      => $message,
					'is_read'      => 0,
					'created_at'   => current_time( 'mysql' ),
				],
				[ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ]
			);
		}
	}

	$label     = $status === 'approved' ? 'approved' : 'submitted';
	$_type_uc  = strtoupper( $type );
	$_range    = $start_date === $end_date ? date( 'M j, Y', strtotime( $start_date ) ) : date( 'M j', strtotime( $start_date ) ) . '–' . date( 'M j, Y', strtotime( $end_date ) );
	if ( $target_user_id !== $current_user_id ) {
		$_for      = get_userdata( $target_user_id );
		$_for_name = $_for ? $_for->display_name : "User #{$target_user_id}";
		schedules_log( 'timeoff_request', "Submitted {$_type_uc} for {$_for_name} ({$_range}): {$created} created, {$skipped} skipped", [], $target_user_id );
	} else {
		schedules_log( 'timeoff_request', "Submitted {$_type_uc} for {$_range}: {$created} created, {$skipped} skipped" );
	}
	wp_send_json_success( [ 'created' => $created, 'skipped' => $skipped ] );
}

/**
 * Adds a +1 OT adjustment for a user's shift on a given date.
 * Called when a time off request is approved.
 */
function schedules_timeoff_add_ot_slot( int $user_id, string $date, int $supervisor_id, string $type = 'pdo' ): void {
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
		strtoupper( $type ) . ' for ' . $member_name
	);
}

// --- PDO Calendar (shift-wide view of all approved/pending time off) ---
add_action( 'wp_ajax_schedules_get_pdo_calendar', 'schedules_ajax_get_pdo_calendar' );
function schedules_ajax_get_pdo_calendar(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$year         = (int) ($_POST['year']  ?? date('Y'));
	$month        = (int) ($_POST['month'] ?? date('n'));
	$shift_letter = strtoupper( sanitize_text_field( $_POST['shift'] ?? '' ) );

	if ( ! $shift_letter ) {
		wp_send_json_error( [ 'message' => 'No shift specified.' ] );
		return;
	}

	// Build calendar grid
	$first_of_month = mktime( 0, 0, 0, $month, 1, $year );
	$days_in_month  = (int) date( 't', $first_of_month );
	$first_dow      = (int) date( 'w', $first_of_month );
	$month_label    = date( 'F Y', $first_of_month );

	$cal_start   = date( 'Y-m-d', strtotime( "-{$first_dow} days", $first_of_month ) );
	$total_cells = $first_dow + $days_in_month;
	$grid_rows   = (int) ceil( $total_cells / 7 );
	$total_days  = $grid_rows * 7;
	$cal_end     = date( 'Y-m-d', strtotime( '+' . ($total_days - 1) . ' days', strtotime($cal_start) ) );

	// Get shift info for work-day highlighting
	$shift_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('shifts') . " WHERE shift_letter = %s",
		$shift_letter
	), ARRAY_A );

	// Get all members on this shift
	$shift_users = get_users( [
		'meta_key'   => 'schedules_shift',
		'meta_value' => $shift_letter,
		'fields'     => 'ID',
	] );
	$shift_user_ids = array_map( 'intval', $shift_users );

	// Get all approved/pending time off for these members in the date range
	$timeoff_rows = [];
	if ( ! empty( $shift_user_ids ) ) {
		// Integers already validated via intval; safe to interpolate directly.
		$id_list = implode( ',', $shift_user_ids );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$timeoff_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.user_id, t.type, t.start_date, t.end_date, t.status
			 FROM " . schedules_table('timeoff') . " t
			 WHERE t.user_id IN ({$id_list})
			   AND t.start_date <= %s
			   AND t.end_date   >= %s
			   AND t.status IN ('approved', 'pending', 'coverage')
			 ORDER BY t.start_date ASC, t.user_id ASC",
			$cal_end,
			$cal_start
		), ARRAY_A ) ?: [];
	}

	// Build name map for users
	$name_format = get_user_meta( get_current_user_id(), 'schedules_name_format', true ) ?: 'first_last';
	$name_map    = [];
	foreach ( $shift_user_ids as $uid ) {
		$ud = get_userdata( $uid );
		if ( ! $ud ) continue;
		$name_map[$uid] = $ud->first_name && $ud->last_name
			? ( $name_format === 'last_first'
				? $ud->last_name . ', ' . $ud->first_name
				: $ud->first_name . ' ' . $ud->last_name )
			: $ud->display_name;
	}

	// Index by date
	$pdo_by_date = [];
	foreach ( $timeoff_rows as $to ) {
		$d = max( $to['start_date'], $cal_start );
		$e = min( $to['end_date'], $cal_end );
		while ( $d <= $e ) {
			$pdo_by_date[$d][] = [
				'name'   => $name_map[ (int)$to['user_id'] ] ?? "User #{$to['user_id']}",
				'type'   => $to['type'],
				'status' => $to['status'],
			];
			$d = date( 'Y-m-d', strtotime( '+1 day', strtotime($d) ) );
		}
	}

	// Build shift work-day set for highlighting
	$work_days    = $shift_row ? array_map( 'intval', explode( ',', $shift_row['work_days'] ) ) : [];
	$work_days_w2 = $shift_row && ! empty( $shift_row['work_days_week2'] )
		? array_map( 'intval', explode( ',', $shift_row['work_days_week2'] ) )
		: $work_days;
	$cycle_anchor = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';

	// Build calendar days
	$days = [];
	for ( $i = 0; $i < $total_days; $i++ ) {
		$date    = date( 'Y-m-d', strtotime( "+{$i} days", strtotime($cal_start) ) );
		$dow     = (int) date( 'w', strtotime($date) );
		$in_month = (int) date( 'n', strtotime($date) ) === $month;

		// Is this a shift work day?
		$is_work_day = false;
		if ( $shift_row && $work_days ) {
			$cycle_week      = schedules_get_cycle_week( $date, $cycle_anchor );
			$todays_work_days = $cycle_week === 0 ? $work_days : $work_days_w2;
			$is_work_day     = in_array( $dow, $todays_work_days, true );
		}

		$days[] = [
			'date'        => $date,
			'day'         => (int) date( 'j', strtotime($date) ),
			'dow'         => $dow,
			'in_month'    => $in_month,
			'is_today'    => $date === date('Y-m-d'),
			'is_work_day' => $is_work_day,
			'pdo'         => $is_work_day ? ( $pdo_by_date[$date] ?? [] ) : [],
		];
	}

	wp_send_json_success( [
		'year'        => $year,
		'month'       => $month,
		'month_label' => $month_label,
		'shift'       => $shift_letter,
		'shift_start' => $shift_row ? (int) $shift_row['start_hour'] : 6,
		'shift_end'   => $shift_row ? (int) $shift_row['end_hour']   : 18,
		'days'        => $days,
	] );
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
	$custom_sched  = schedules_normalize_custom_schedule( $custom_sched );

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
		   AND status IN ('approved', 'pending', 'coverage')
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

	// Get outgoing coverage requests (sent by this user, not cancelled/declined/rejected)
	$cover_out_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.id, t.trade_date, t.shift_letter, t.status,
		        ru.display_name AS rec_name
		 FROM " . schedules_table('trades') . " t
		 JOIN {$wpdb->users} ru ON ru.ID = t.recipient_id
		 WHERE t.requester_id = %d
		   AND t.trade_date BETWEEN %s AND %s
		   AND t.status NOT IN ('cancelled','declined','rejected')
		 ORDER BY t.trade_date ASC",
		$target_user_id, $cal_start, $cal_end
	), ARRAY_A ) ?: [];

	$cover_out_by_date = [];
	foreach ( $cover_out_rows as $r ) {
		$cover_out_by_date[ $r['trade_date'] ][] = [
			'id'       => (int) $r['id'],
			'status'   => $r['status'],
			'rec_name' => $r['rec_name'],
		];
	}

	// Get incoming coverage requests (this user is recipient, status = pending)
	$cover_in_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.id, t.trade_date, t.shift_letter, t.status,
		        ru.display_name AS req_name,
		        s.start_hour, s.end_hour
		 FROM " . schedules_table('trades') . " t
		 JOIN {$wpdb->users} ru ON ru.ID = t.requester_id
		 LEFT JOIN " . schedules_table('shifts') . " s ON s.shift_letter = t.shift_letter
		 WHERE t.recipient_id = %d
		   AND t.trade_date BETWEEN %s AND %s
		   AND t.status IN ('pending', 'pending_supervisor')
		 ORDER BY t.trade_date ASC",
		$target_user_id, $cal_start, $cal_end
	), ARRAY_A ) ?: [];

	$cover_in_by_date = [];
	foreach ( $cover_in_rows as $r ) {
		$cover_in_by_date[ $r['trade_date'] ][] = [
			'id'       => (int) $r['id'],
			'shift'    => $r['shift_letter'],
			'status'   => $r['status'],
			'req_name' => $r['req_name'],
			'start'    => (int) $r['start_hour'],
			'end'      => (int) $r['end_hour'],
		];
	}

	// Build calendar days
	$days = [];
	for ( $i = 0; $i < $total_days; $i++ ) {
		$date     = date( 'Y-m-d', strtotime( "+{$i} days", strtotime($cal_start) ) );
		$dow      = (int) date( 'w', strtotime($date) );
		$day_num  = (int) date( 'j', strtotime($date) );
		$in_month = (int) date( 'n', strtotime($date) ) === $month;

		$day_data = [
			'date'         => $date,
			'day'          => $day_num,
			'dow'          => $dow,
			'in_month'     => $in_month,
			'is_today'     => $date === date('Y-m-d'),
			'shift'        => null,
			'ot'           => [],
			'timeoff'      => [],
			'coverage_out' => [],
			'coverage_in'  => [],
		];

		// Regular shift
		if ( $schedule_type === 'custom' ) {
			$cycle_week  = schedules_get_cycle_week( $date, $cycle_anchor );
			$wk_key      = $cycle_week === 0 ? 'week1' : 'week2';
			$week_sched  = $custom_sched[ $wk_key ] ?? [];
			if ( isset( $week_sched[ $dow ] ) ) {
				$cs = $week_sched[ $dow ];
				$day_data['shift'] = [
					'letter' => 'custom',
					'start'  => (float) $cs['start'],
					'end'    => (float) $cs['end'],
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
				// Prefer day_schedule for DOW-specific hours over canonical start/end
				$eff_start = $start_hour;
				$eff_end   = $end_hour;
				if ( ! empty( $shift_row['day_schedule'] ) ) {
					$_ds = json_decode( $shift_row['day_schedule'], true );
					if ( is_array( $_ds ) ) {
						$_wk   = $cycle_week === 0 ? 'week1' : 'week2';
						$_wds  = ( isset( $_ds['week1'] ) || isset( $_ds['week2'] ) ) ? ( $_ds[ $_wk ] ?? [] ) : $_ds;
						$_dstr = (string) $dow;
						if ( isset( $_wds[ $_dstr ] ) ) {
							$eff_start = (int) $_wds[ $_dstr ]['start'];
							$eff_end   = (int) $_wds[ $_dstr ]['end'];
						}
					}
				}
				$day_data['shift'] = [
					'letter' => $user_shift,
					'start'  => $eff_start,
					'end'    => $eff_end,
				];
			}
		}

		// OT
		if ( isset( $ot_by_date[$date] ) ) {
			$day_data['ot'] = $ot_by_date[$date];
		}

		// Time off — only on scheduled work days so members aren't charged for non-work days
		if ( $day_data['shift'] !== null && isset( $timeoff_by_date[$date] ) ) {
			$day_data['timeoff'] = $timeoff_by_date[$date];
		}

		// Coverage requests
		if ( isset( $cover_out_by_date[$date] ) ) {
			$day_data['coverage_out'] = $cover_out_by_date[$date];
		}
		if ( isset( $cover_in_by_date[$date] ) ) {
			$day_data['coverage_in'] = $cover_in_by_date[$date];
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
	$schedules_slugs = [ 'schedules-login', 'schedules-member', 'schedules-supervisor' ];

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

	if ( in_array($slug, ['schedules-member', 'schedules-supervisor'], true) ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url('/schedules-login/') );
			exit;
		}
	}

	if ( $slug === 'schedules-supervisor' && is_user_logged_in() ) {
		if ( ! current_user_can('manage_schedules') ) {
			wp_safe_redirect( home_url('/schedules-member/') );
			exit;
		}
	}
}

/*--------------------------------------------------------------
# Cover Requests
--------------------------------------------------------------*/

add_action( 'wp_ajax_schedules_get_cover_members', 'schedules_ajax_get_cover_members' );
function schedules_ajax_get_cover_members(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$user_id      = get_current_user_id();
	$trade_date   = sanitize_text_field( $_POST['trade_date']   ?? '' );
	$shift_letter = sanitize_text_field( $_POST['shift_letter'] ?? '' );

	if ( ! $trade_date || ! $shift_letter ) {
		wp_send_json_error( [ 'message' => 'Missing parameters.' ] );
	}

	$dow          = (int) date( 'w', strtotime( $trade_date ) );
	$cycle_anchor = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';
	$cycle_week   = schedules_get_cycle_week( $trade_date, $cycle_anchor );

	// Load all shift rows keyed by letter so we can check work_days per member
	$shift_rows = $wpdb->get_results(
		"SELECT shift_letter, work_days, work_days_week2 FROM " . schedules_table('shifts'),
		ARRAY_A
	) ?: [];
	$shifts_by_letter = [];
	foreach ( $shift_rows as $sr ) {
		$shifts_by_letter[ $sr['shift_letter'] ] = $sr;
	}

	$all = schedules_get_all_members();
	$result = [];
	foreach ( $all as $m ) {
		// Exclude self
		if ( (int) $m['user_id'] === (int) $user_id ) continue;

		// Determine if this member works on trade_date
		$m_works = false;
		$m_shift  = $m['shift'] ?? '';
		$sched_type = $m['schedule_type'] ?? 'shift';

		if ( $sched_type === 'custom' ) {
			// Custom schedule: check their custom_schedule array
			$custom = $m['custom_schedule'];
			if ( is_array( $custom ) ) {
				$custom = schedules_normalize_custom_schedule( $custom );
				$wk_key = $cycle_week === 0 ? 'week1' : 'week2';
				$m_works = isset( $custom[ $wk_key ][ $dow ] );
			}
		} elseif ( $m_shift && isset( $shifts_by_letter[ $m_shift ] ) ) {
			// Standard shift rotation
			$sr       = $shifts_by_letter[ $m_shift ];
			$wdays1   = array_map( 'intval', explode( ',', $sr['work_days'] ) );
			$wdays2   = ! empty( $sr['work_days_week2'] )
				? array_map( 'intval', explode( ',', $sr['work_days_week2'] ) )
				: $wdays1;
			$active   = $cycle_week === 0 ? $wdays1 : $wdays2;
			$m_works  = in_array( $dow, $active, true );
		}
		// Members with no shift assignment (non-floor) never "work" a shift — they're available

		if ( $m_works ) continue;

		$result[] = [
			'id'    => (int) $m['user_id'],
			'name'  => trim( $m['first_name'] . ' ' . $m['last_name'] ) ?: $m['badge_number'],
			'shift' => $m_shift,
			'role'  => $m['role'] ?? 'member',
		];
	}

	wp_send_json_success( [ 'members' => $result ] );
}

add_action( 'wp_ajax_schedules_get_cover_requests', 'schedules_ajax_get_cover_requests' );
function schedules_ajax_get_cover_requests(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$user_id = get_current_user_id();

	if ( current_user_can('manage_schedules') ) {
		// Supervisors see all pending_supervisor requests for their assigned shift
		$user_shift = get_user_meta( $user_id, 'schedules_shift', true );
		if ( $user_shift ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT t.*,
				        ru.first_name AS req_first, ru.last_name AS req_last,
				        rc.first_name AS rec_first, rc.last_name AS rec_last
				 FROM " . schedules_table('trades') . " t
				 JOIN {$wpdb->users} ru ON ru.ID = t.requester_id
				 JOIN {$wpdb->users} rc ON rc.ID = t.recipient_id
				 WHERE t.status = 'pending_supervisor'
				   AND t.shift_letter = %s
				 ORDER BY t.requested_at ASC",
				$user_shift
			), ARRAY_A ) ?: [];
		} else {
			// Admin with no shift: see all pending_supervisor
			$rows = $wpdb->get_results(
				"SELECT t.*,
				        ru.first_name AS req_first, ru.last_name AS req_last,
				        rc.first_name AS rec_first, rc.last_name AS rec_last
				 FROM " . schedules_table('trades') . " t
				 JOIN {$wpdb->users} ru ON ru.ID = t.requester_id
				 JOIN {$wpdb->users} rc ON rc.ID = t.recipient_id
				 WHERE t.status = 'pending_supervisor'
				 ORDER BY t.requested_at ASC",
			ARRAY_A ) ?: [];
		}
	} else {
		// Members see their own incoming + outgoing (non-terminal)
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.*,
			        ru.first_name AS req_first, ru.last_name AS req_last,
			        rc.first_name AS rec_first, rc.last_name AS rec_last
			 FROM " . schedules_table('trades') . " t
			 JOIN {$wpdb->users} ru ON ru.ID = t.requester_id
			 JOIN {$wpdb->users} rc ON rc.ID = t.recipient_id
			 WHERE (t.requester_id = %d OR t.recipient_id = %d)
			   AND t.status NOT IN ('cancelled', 'rejected', 'declined')
			 ORDER BY t.requested_at DESC",
			$user_id, $user_id
		), ARRAY_A ) ?: [];
	}

	wp_send_json_success( [ 'requests' => $rows ] );
}

add_action( 'wp_ajax_schedules_send_cover_request', 'schedules_ajax_send_cover_request' );
function schedules_ajax_send_cover_request(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$current_user_id = get_current_user_id();
	$proxy_id        = (int) ( $_POST['user_id'] ?? 0 );
	$is_supervisor   = current_user_can('manage_schedules') || current_user_can('admin_schedules');
	$user_id         = ( $proxy_id && $is_supervisor ) ? $proxy_id : $current_user_id;
	$recipient_id = (int) ( $_POST['recipient_id'] ?? 0 );
	$start_date   = sanitize_text_field( $_POST['trade_date'] ?? $_POST['start_date'] ?? '' );
	$end_date     = sanitize_text_field( $_POST['end_date']   ?? $start_date );
	$note         = sanitize_textarea_field( $_POST['note'] ?? '' );

	if ( ! $recipient_id || ! $start_date ) {
		wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
	}
	if ( $recipient_id === $user_id ) {
		wp_send_json_error( [ 'message' => 'Cannot send a request to yourself.' ] );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || $start_date <= date('Y-m-d') ) {
		wp_send_json_error( [ 'message' => 'Trade date must be a valid future date.' ] );
	}
	if ( $end_date < $start_date ) $end_date = $start_date;

	$shift_letter = get_user_meta( $user_id, 'schedules_shift', true );
	if ( ! $shift_letter ) {
		wp_send_json_error( [ 'message' => 'You are not assigned to a shift.' ] );
	}

	$created    = 0;
	$skipped    = 0;
	$first_id   = null;
	$current_dt = new DateTime( $start_date );
	$end_dt     = new DateTime( $end_date );

	while ( $current_dt <= $end_dt ) {
		$day = $current_dt->format( 'Y-m-d' );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . schedules_table('trades') . "
			 WHERE requester_id = %d AND recipient_id = %d AND trade_date = %s
			   AND status IN ('pending', 'pending_supervisor')",
			$user_id, $recipient_id, $day
		) );
		if ( $existing ) {
			$skipped++;
			$current_dt->modify( '+1 day' );
			continue;
		}

		$wpdb->insert(
			schedules_table('trades'),
			[
				'type'           => 'cover',
				'requester_id'   => $user_id,
				'recipient_id'   => $recipient_id,
				'trade_date'     => $day,
				'shift_letter'   => $shift_letter,
				'status'         => 'pending',
				'requester_note' => $note ?: null,
				'requested_at'   => current_time('mysql'),
			],
			[ '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( $wpdb->insert_id ) {
			if ( ! $first_id ) $first_id = $wpdb->insert_id;
			$created++;
		}

		$current_dt->modify( '+1 day' );
	}

	if ( ! $created && ! $skipped ) {
		wp_send_json_error( [ 'message' => 'No valid dates to process.' ] );
	}

	// Send one notification to the recipient summarising the range
	if ( $created > 0 && $first_id ) {
		$requester = get_userdata( $user_id );
		$req_name  = trim( $requester->first_name . ' ' . $requester->last_name ) ?: $requester->user_login;
		if ( $start_date === $end_date ) {
			$range_label = date( 'l, F j, Y', strtotime( $start_date ) );
		} else {
			$range_label = date( 'M j', strtotime( $start_date ) ) . '–' . date( 'M j, Y', strtotime( $end_date ) );
		}
		$day_word = $created === 1 ? '1 day' : "{$created} days";
		$wpdb->insert(
			schedules_table('notifications'),
			[
				'user_id'      => $recipient_id,
				'type'         => 'cover_request_received',
				'related_id'   => $first_id,
				'related_type' => 'trade',
				'message'      => "{$req_name} is requesting coverage for Shift {$shift_letter}: {$range_label} ({$day_word}).",
				'is_read'      => 0,
				'created_at'   => current_time('mysql'),
			],
			[ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ]
		);

		$_rec      = get_userdata( $recipient_id );
		$_rec_name = $_rec ? $_rec->display_name : "User #{$recipient_id}";
		schedules_log( 'coverage_request', "Sent coverage request to {$_rec_name} — Shift {$shift_letter} {$start_date}–{$end_date}: {$created} days", [], $recipient_id );
	}

	wp_send_json_success( [ 'created' => $created, 'skipped' => $skipped ] );
}

add_action( 'wp_ajax_schedules_respond_cover_request', 'schedules_ajax_respond_cover_request' );
function schedules_ajax_respond_cover_request(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$user_id  = get_current_user_id();
	$trade_id = (int) ( $_POST['trade_id'] ?? 0 );
	$response = sanitize_key( $_POST['response'] ?? '' ); // 'accept' or 'decline'
	$note     = sanitize_textarea_field( $_POST['note'] ?? '' );

	if ( ! $trade_id || ! in_array( $response, [ 'accept', 'decline' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid request.' ] );
	}

	$trade = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('trades') . " WHERE id = %d AND recipient_id = %d AND status = 'pending'",
		$trade_id, $user_id
	), ARRAY_A );

	if ( ! $trade ) {
		wp_send_json_error( [ 'message' => 'Request not found or already actioned.' ] );
	}

	$recipient = get_userdata( $user_id );
	$rec_name  = trim( $recipient->first_name . ' ' . $recipient->last_name ) ?: $recipient->user_login;
	$date_fmt  = date( 'l, F j, Y', strtotime( $trade['trade_date'] ) );

	if ( $response === 'accept' ) {
		$wpdb->update(
			schedules_table('trades'),
			[ 'status' => 'pending_supervisor', 'recipient_note' => $note ?: null, 'responded_at' => current_time('mysql') ],
			[ 'id' => $trade_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		// Notify requester
		$wpdb->insert( schedules_table('notifications'), [
			'user_id'      => (int) $trade['requester_id'],
			'type'         => 'cover_request_accepted',
			'related_id'   => $trade_id,
			'related_type' => 'trade',
			'message'      => "{$rec_name} accepted your coverage request for {$date_fmt}. Pending supervisor approval.",
			'is_read'      => 0,
			'created_at'   => current_time('mysql'),
		], [ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ] );

		// Notify shift supervisors
		$requester    = get_userdata( (int) $trade['requester_id'] );
		$req_name     = trim( $requester->first_name . ' ' . $requester->last_name ) ?: $requester->user_login;
		$message_sup  = "Coverage pending approval: {$req_name} → {$rec_name} for Shift {$trade['shift_letter']} on {$date_fmt}.";
		$shift_sups   = get_users( [ 'role' => 'schedules_supervisor', 'meta_key' => 'schedules_shift', 'meta_value' => $trade['shift_letter'] ] );
		$admins       = get_users( [ 'role' => 'schedules_admin' ] );
		$seen         = [];
		foreach ( array_merge( $shift_sups, $admins ) as $sup ) {
			if ( in_array( $sup->ID, $seen, true ) ) continue;
			$seen[] = $sup->ID;
			$wpdb->insert( schedules_table('notifications'), [
				'user_id'      => $sup->ID,
				'type'         => 'cover_pending_supervisor',
				'related_id'   => $trade_id,
				'related_type' => 'trade',
				'message'      => $message_sup,
				'is_read'      => 0,
				'created_at'   => current_time('mysql'),
			], [ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ] );
		}

		schedules_log( 'coverage_respond', "Accepted coverage request from {$req_name} — Shift {$trade['shift_letter']} {$trade['trade_date']}", [], (int) $trade['requester_id'] );
		wp_send_json_success( [ 'message' => 'Request accepted. Awaiting supervisor approval.' ] );

	} else {
		$wpdb->update(
			schedules_table('trades'),
			[ 'status' => 'declined', 'recipient_note' => $note ?: null, 'responded_at' => current_time('mysql') ],
			[ 'id' => $trade_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		$wpdb->insert( schedules_table('notifications'), [
			'user_id'      => (int) $trade['requester_id'],
			'type'         => 'cover_request_declined',
			'related_id'   => $trade_id,
			'related_type' => 'trade',
			'message'      => "{$rec_name} declined your coverage request for {$date_fmt}.",
			'is_read'      => 0,
			'created_at'   => current_time('mysql'),
		], [ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ] );

		$_creq = get_userdata( (int) $trade['requester_id'] );
		$_creq_name = $_creq ? $_creq->display_name : "User #{$trade['requester_id']}";
		schedules_log( 'coverage_respond', "Declined coverage request from {$_creq_name} — Shift {$trade['shift_letter']} {$trade['trade_date']}", [], (int) $trade['requester_id'] );
		wp_send_json_success( [ 'message' => 'Request declined.' ] );
	}
}

add_action( 'wp_ajax_schedules_cancel_cover_request', 'schedules_ajax_cancel_cover_request' );
function schedules_ajax_cancel_cover_request(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	global $wpdb;

	$user_id  = get_current_user_id();
	$trade_id = (int) ( $_POST['trade_id'] ?? 0 );

	$trade = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('trades') . " WHERE id = %d AND requester_id = %d AND status IN ('pending', 'pending_supervisor')",
		$trade_id, $user_id
	), ARRAY_A );

	if ( ! $trade ) {
		wp_send_json_error( [ 'message' => 'Request not found or cannot be cancelled.' ] );
	}

	$wpdb->update(
		schedules_table('trades'),
		[ 'status' => 'cancelled' ],
		[ 'id' => $trade_id ],
		[ '%s' ],
		[ '%d' ]
	);

	// If it was already accepted (pending supervisor), notify supervisors
	if ( $trade['status'] === 'pending_supervisor' ) {
		$requester  = get_userdata( $user_id );
		$req_name   = trim( $requester->first_name . ' ' . $requester->last_name ) ?: $requester->user_login;
		$date_fmt   = date( 'l, F j, Y', strtotime( $trade['trade_date'] ) );
		$message    = "Coverage request cancelled: {$req_name}'s Shift {$trade['shift_letter']} on {$date_fmt}.";
		$shift_sups = get_users( [ 'role' => 'schedules_supervisor', 'meta_key' => 'schedules_shift', 'meta_value' => $trade['shift_letter'] ] );
		foreach ( $shift_sups as $sup ) {
			$wpdb->insert( schedules_table('notifications'), [
				'user_id'      => $sup->ID,
				'type'         => 'cover_cancelled',
				'related_id'   => $trade_id,
				'related_type' => 'trade',
				'message'      => $message,
				'is_read'      => 0,
				'created_at'   => current_time('mysql'),
			], [ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ] );
		}
	}

	$_ctrade_date = $trade['trade_date'] ?? '';
	$_cshift      = $trade['shift_letter'] ?? '';
	schedules_log( 'coverage_cancel', "Cancelled coverage request — Shift {$_cshift} {$_ctrade_date}" );
	wp_send_json_success( [ 'message' => 'Request cancelled.' ] );
}

add_action( 'wp_ajax_schedules_review_cover_request', 'schedules_ajax_review_cover_request' );
function schedules_ajax_review_cover_request(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('manage_schedules') ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	global $wpdb;
	$reviewer_id = get_current_user_id();
	$trade_id    = (int) ( $_POST['trade_id'] ?? 0 );
	$decision    = sanitize_key( $_POST['decision'] ?? '' ); // 'approve' or 'reject'
	$note        = sanitize_textarea_field( $_POST['note'] ?? '' );

	if ( ! $trade_id || ! in_array( $decision, [ 'approve', 'reject' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid request.' ] );
	}

	$trade = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . schedules_table('trades') . " WHERE id = %d AND status = 'pending_supervisor'",
		$trade_id
	), ARRAY_A );

	if ( ! $trade ) {
		wp_send_json_error( [ 'message' => 'Request not found or already reviewed.' ] );
	}

	$new_status = $decision === 'approve' ? 'approved' : 'rejected';
	$wpdb->update(
		schedules_table('trades'),
		[
			'status'         => $new_status,
			'supervisor_note' => $note ?: null,
			'reviewed_by'    => $reviewer_id,
			'reviewed_at'    => current_time('mysql'),
		],
		[ 'id' => $trade_id ],
		[ '%s', '%s', '%d', '%s' ],
		[ '%d' ]
	);

	$requester = get_userdata( (int) $trade['requester_id'] );
	$recipient = get_userdata( (int) $trade['recipient_id'] );
	$req_name  = trim( $requester->first_name . ' ' . $requester->last_name ) ?: $requester->user_login;
	$rec_name  = trim( $recipient->first_name . ' ' . $recipient->last_name ) ?: $recipient->user_login;
	$date_fmt  = date( 'l, F j, Y', strtotime( $trade['trade_date'] ) );
	$note_sfx  = $note ? " Note: {$note}" : '';

	if ( $decision === 'approve' ) {
		$msg_req = "Your coverage request for Shift {$trade['shift_letter']} on {$date_fmt} was approved. {$rec_name} will cover your shift.";
		$msg_rec = "You are approved to cover {$req_name}'s Shift {$trade['shift_letter']} on {$date_fmt}.";
	} else {
		$msg_req = "Your coverage request for Shift {$trade['shift_letter']} on {$date_fmt} was not approved.{$note_sfx}";
		$msg_rec = "The coverage request for {$req_name}'s Shift {$trade['shift_letter']} on {$date_fmt} was not approved.{$note_sfx}";
	}

	$notif_type = $decision === 'approve' ? 'cover_approved' : 'cover_rejected';
	foreach ( [ (int) $trade['requester_id'] => $msg_req, (int) $trade['recipient_id'] => $msg_rec ] as $uid => $msg ) {
		$wpdb->insert( schedules_table('notifications'), [
			'user_id'      => $uid,
			'type'         => $notif_type,
			'related_id'   => $trade_id,
			'related_type' => 'trade',
			'message'      => $msg,
			'is_read'      => 0,
			'created_at'   => current_time('mysql'),
		], [ '%d', '%s', '%d', '%s', '%s', '%d', '%s' ] );
	}

	schedules_log( 'coverage_review', "Coverage {$new_status}: {$req_name} → {$rec_name}, Shift {$trade['shift_letter']} {$trade['trade_date']}", [], (int) $trade['requester_id'] );

	wp_send_json_success( [ 'message' => $decision === 'approve' ? 'Coverage approved.' : 'Coverage rejected.' ] );
}

// --- TEST ONLY: Reset — clears OT claims, PDO requests, and generated OT calendar data ---
add_action( 'wp_ajax_schedules_nuclear_reset', 'schedules_ajax_nuclear_reset' );
function schedules_ajax_nuclear_reset(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );

	if ( ! current_user_can('admin_schedules') ) {
		wp_send_json_error( [ 'message' => 'Admin only.' ] );
	}

	global $wpdb;

	// Clear OT claims first (foreign key on blocks)
	$wpdb->query( "TRUNCATE TABLE " . schedules_table('claims') );
	// Clear generated OT calendar (days + blocks — auto-rebuilt by cron)
	$wpdb->query( "TRUNCATE TABLE " . schedules_table('blocks') );
	$wpdb->query( "TRUNCATE TABLE " . schedules_table('days') );
	// Clear PDO / time-off requests
	$wpdb->query( "TRUNCATE TABLE " . schedules_table('timeoff') );
	// Clear adjustments, trades, notifications
	$wpdb->query( "TRUNCATE TABLE " . schedules_table('adjustments') );
	$wpdb->query( "TRUNCATE TABLE " . schedules_table('trades') );
	$wpdb->query( "TRUNCATE TABLE " . schedules_table('notifications') );
	// Duty assignments are preserved

	// Immediately rebuild the 28-day OT window so claim OT is usable right away
	schedules_generate_initial_window();

	schedules_log( 'nuclear_reset', 'Cleared all test data (OT claims, time-off, coverage, notifications)' );
	wp_send_json_success( [ 'message' => 'All activity data cleared (OT claims, time-off requests, coverage requests, notifications). Duty assignments preserved.' ] );
}

// ============================================================
// SICK TIME HISTORY
// ============================================================

add_action( 'wp_ajax_schedules_get_sick_history', 'schedules_ajax_get_sick_history' );
function schedules_ajax_get_sick_history(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('manage_schedules') ) wp_send_json_error( [ 'message' => 'Permission denied.' ] );

	global $wpdb;
	$user_id = (int) ( $_POST['user_id'] ?? 0 );
	if ( ! $user_id ) wp_send_json_error( [ 'message' => 'Invalid user.' ] );

	$year = (int) date( 'Y' );

	$records = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, type, start_date, end_date, hours, notes, created_at
		 FROM " . schedules_table('timeoff') . "
		 WHERE user_id = %d
		   AND status  = 'approved'
		   AND type    IN ('sick', 'fmla')
		 ORDER BY start_date DESC",
		$user_id
	), ARRAY_A ) ?: [];

	$ytd_hours = schedules_get_sick_hours( $user_id, $year );
	$threshold_raw = schedules_get_config( 'sick_hour_thresholds', '30' );
	$thresholds    = array_values( array_filter( array_unique( array_map( 'intval', explode( ',', $threshold_raw ) ) ) ) );
	sort( $thresholds );

	$u     = get_userdata( $user_id );
	$shift = $u ? (string) get_user_meta( $user_id, 'schedules_shift', true ) : '';

	wp_send_json_success( [
		'records'    => $records,
		'ytd_hours'  => $ytd_hours,
		'thresholds' => $thresholds,
		'year'       => $year,
		'shift'      => $shift,
	] );
}

// ============================================================
// MEMBER DAY HOURS — return a specific member's hours for a specific date
// ============================================================

add_action( 'wp_ajax_schedules_get_member_day_hours', 'schedules_ajax_get_member_day_hours' );
function schedules_ajax_get_member_day_hours(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('manage_schedules') ) wp_send_json_error( [ 'message' => 'Permission denied.' ] );

	global $wpdb;
	$user_id = (int) ( $_POST['user_id'] ?? 0 );
	$date    = sanitize_text_field( $_POST['date'] ?? '' );

	if ( ! $user_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		wp_send_json_error( [ 'message' => 'Invalid parameters.' ] );
		return;
	}

	$user_shift    = get_user_meta( $user_id, 'schedules_shift',          true );
	$schedule_type = get_user_meta( $user_id, 'schedules_schedule_type',   true ) ?: 'shift';
	$custom_sched  = get_user_meta( $user_id, 'schedules_custom_schedule', true );
	if ( ! is_array( $custom_sched ) ) $custom_sched = [];
	$custom_sched  = schedules_normalize_custom_schedule( $custom_sched );
	$cycle_anchor  = get_option( 'schedules_cycle_anchor', '' ) ?: '2025-01-06';

	$dow        = (int) date( 'w', strtotime( $date ) );
	$start_hour = null;
	$end_hour   = null;
	$shift_letter = null;

	if ( $schedule_type === 'custom' ) {
		$cycle_week = schedules_get_cycle_week( $date, $cycle_anchor );
		$wk_key     = $cycle_week === 0 ? 'week1' : 'week2';
		$week_sched = $custom_sched[ $wk_key ] ?? [];
		if ( isset( $week_sched[ $dow ] ) ) {
			$cs           = $week_sched[ $dow ];
			$start_hour   = (float) $cs['start'];
			$end_hour     = (float) $cs['end'];
			$shift_letter = 'custom';
		}
	} elseif ( $user_shift ) {
		$shift_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . schedules_table('shifts') . " WHERE shift_letter = %s",
			$user_shift
		), ARRAY_A );

		if ( $shift_row ) {
			$work_days    = array_map( 'intval', explode( ',', $shift_row['work_days'] ) );
			$work_days_w2 = ! empty( $shift_row['work_days_week2'] )
				? array_map( 'intval', explode( ',', $shift_row['work_days_week2'] ) )
				: $work_days;

			$cycle_week       = schedules_get_cycle_week( $date, $cycle_anchor );
			$todays_work_days = $cycle_week === 0 ? $work_days : $work_days_w2;

			if ( in_array( $dow, $todays_work_days, true ) ) {
				$eff_start = (int) $shift_row['start_hour'];
				$eff_end   = (int) $shift_row['end_hour'];
				if ( ! empty( $shift_row['day_schedule'] ) ) {
					$_ds = json_decode( $shift_row['day_schedule'], true );
					if ( is_array( $_ds ) ) {
						$_wk  = $cycle_week === 0 ? 'week1' : 'week2';
						$_wds = ( isset( $_ds['week1'] ) || isset( $_ds['week2'] ) ) ? ( $_ds[ $_wk ] ?? [] ) : $_ds;
						if ( isset( $_wds[ (string) $dow ] ) ) {
							$eff_start = (int) $_wds[ (string) $dow ]['start'];
							$eff_end   = (int) $_wds[ (string) $dow ]['end'];
						}
					}
				}
				$start_hour   = $eff_start;
				$end_hour     = $eff_end;
				$shift_letter = $user_shift;
			}
		}
	}

	if ( $start_hour === null ) {
		wp_send_json_success( [ 'work_day' => false ] );
		return;
	}

	wp_send_json_success( [
		'work_day'     => true,
		'start_hour'   => $start_hour,
		'end_hour'     => $end_hour,
		'shift_letter' => $shift_letter,
	] );
}

// ============================================================
// ACTIVITY LOG
// ============================================================

add_action( 'wp_ajax_schedules_get_activity_log', 'schedules_ajax_get_activity_log' );
function schedules_ajax_get_activity_log(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! current_user_can('manage_schedules') ) wp_send_json_error( [ 'message' => 'Permission denied.' ] );

	global $wpdb;
	$page      = max( 1, (int) ( $_POST['page']          ?? 1 ) );
	$per       = 50;
	$action_f  = sanitize_text_field( $_POST['action_filter'] ?? '' );
	$search    = sanitize_text_field( $_POST['search']        ?? '' );
	$date_from = sanitize_text_field( $_POST['date_from']     ?? '' );
	$date_to   = sanitize_text_field( $_POST['date_to']       ?? '' );

	$where = [];
	$args  = [];

	if ( $action_f ) {
		$where[] = 'action = %s';
		$args[]  = $action_f;
	}
	if ( $search ) {
		$like    = '%' . $wpdb->esc_like( $search ) . '%';
		$where[] = '(actor_name LIKE %s OR actor_badge LIKE %s OR description LIKE %s)';
		$args[]  = $like;
		$args[]  = $like;
		$args[]  = $like;
	}
	if ( $date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ) {
		$where[] = 'DATE(created_at) >= %s';
		$args[]  = $date_from;
	}
	if ( $date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to) ) {
		$where[] = 'DATE(created_at) <= %s';
		$args[]  = $date_to;
	}

	$tbl        = schedules_table('activity_log');
	$where_sql  = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
	$count_sql  = "SELECT COUNT(*) FROM {$tbl} {$where_sql}";
	$total      = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) );

	$offset   = ( $page - 1 ) * $per;
	$data_sql = "SELECT * FROM {$tbl} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
	$rows     = $wpdb->get_results(
		$wpdb->prepare( $data_sql, array_merge( $args, [ $per, $offset ] ) ),
		ARRAY_A
	);

	wp_send_json_success( [ 'rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per ] );
}

// ============================================================
// GOD MODE — battleplanweb only
// ============================================================

function schedules_is_god_mode(): bool {
	$u = wp_get_current_user();
	return $u instanceof WP_User && $u->user_login === 'battleplanweb';
}

// --- Get table rows ---
add_action( 'wp_ajax_schedules_god_get_table', 'schedules_ajax_god_get_table' );
function schedules_ajax_god_get_table(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! schedules_is_god_mode() ) wp_send_json_error( [ 'message' => 'Permission denied.' ] );

	global $wpdb;
	$table = sanitize_text_field( $_POST['table'] ?? '' );
	$page  = max( 1, (int) ( $_POST['page'] ?? 1 ) );
	$per   = 50;

	if ( strpos( $table, $wpdb->prefix . 'schedules_' ) !== 0 ) {
		wp_send_json_error( [ 'message' => 'Invalid table.' ] );
	}

	$cols  = $wpdb->get_col( "DESCRIBE `{$table}`" );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
	$off   = ( $page - 1 ) * $per;
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $per, $off ), ARRAY_A );

	wp_send_json_success( [
		'columns' => $cols,
		'rows'    => $rows,
		'total'   => $total,
		'page'    => $page,
		'per'     => $per,
	] );
}

// --- Update a single cell ---
add_action( 'wp_ajax_schedules_god_update_cell', 'schedules_ajax_god_update_cell' );
function schedules_ajax_god_update_cell(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! schedules_is_god_mode() ) wp_send_json_error( [ 'message' => 'Permission denied.' ] );

	global $wpdb;
	$table  = sanitize_text_field( $_POST['table']  ?? '' );
	$pk_col = sanitize_text_field( $_POST['pk_col'] ?? 'id' );
	$pk_val = $_POST['pk_val'] ?? '';
	$col    = sanitize_text_field( $_POST['col']    ?? '' );
	$value  = wp_unslash( $_POST['value'] ?? '' );

	if ( strpos( $table, $wpdb->prefix . 'schedules_' ) !== 0 ) {
		wp_send_json_error( [ 'message' => 'Invalid table.' ] );
	}

	$result = $wpdb->update( $table, [ $col => $value ], [ $pk_col => $pk_val ] );
	if ( $result === false ) wp_send_json_error( [ 'message' => $wpdb->last_error ] );
	wp_send_json_success( [ 'message' => 'Updated.' ] );
}

// --- Delete a row ---
add_action( 'wp_ajax_schedules_god_delete_row', 'schedules_ajax_god_delete_row' );
function schedules_ajax_god_delete_row(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! schedules_is_god_mode() ) wp_send_json_error( [ 'message' => 'Permission denied.' ] );

	global $wpdb;
	$table  = sanitize_text_field( $_POST['table']  ?? '' );
	$pk_col = sanitize_text_field( $_POST['pk_col'] ?? 'id' );
	$pk_val = $_POST['pk_val'] ?? '';

	if ( strpos( $table, $wpdb->prefix . 'schedules_' ) !== 0 ) {
		wp_send_json_error( [ 'message' => 'Invalid table.' ] );
	}

	$result = $wpdb->delete( $table, [ $pk_col => $pk_val ] );
	if ( $result === false ) wp_send_json_error( [ 'message' => $wpdb->last_error ] );
	wp_send_json_success( [ 'message' => 'Row deleted.' ] );
}

// --- Get schedules-related WP options ---
add_action( 'wp_ajax_schedules_god_get_options', 'schedules_ajax_god_get_options' );
function schedules_ajax_god_get_options(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! schedules_is_god_mode() ) wp_send_json_error( [ 'message' => 'Permission denied.' ] );

	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT option_name, option_value FROM {$wpdb->options}
		 WHERE option_name LIKE '%schedules%'
		 ORDER BY option_name ASC",
		ARRAY_A
	);

	wp_send_json_success( [ 'options' => $rows ] );
}

// --- Update a WP option ---
add_action( 'wp_ajax_schedules_god_update_option', 'schedules_ajax_god_update_option' );
function schedules_ajax_god_update_option(): void {
	check_ajax_referer( 'schedules_nonce', 'nonce' );
	if ( ! schedules_is_god_mode() ) wp_send_json_error( [ 'message' => 'Permission denied.' ] );

	$name  = sanitize_text_field( $_POST['option_name']  ?? '' );
	$value = wp_unslash( $_POST['option_value'] ?? '' );

	if ( strpos( $name, 'schedules' ) === false ) {
		wp_send_json_error( [ 'message' => 'Invalid option name.' ] );
	}

	update_option( $name, $value );
	wp_send_json_success( [ 'message' => 'Option updated.' ] );
}
