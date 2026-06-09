<?php
require_once get_template_directory() . '/prompts/prompts-site-pulse.php';

/* Battle Plan Web Design - Site Pulse Internal Operations Platform

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Constants & Setup
# Database Schema
# Asset Enqueueing
# User Roles & Capabilities
# Permission Helpers
# Location Helpers
# Report Template Helpers
# Report Submission & Retrieval
# Activity Log
# AJAX Handlers
# Body Class & Auth Guard
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Constants & Setup
--------------------------------------------------------------*/

define( 'SITE_PULSE_DB_VERSION', '1.17' );

function site_pulse_table( string $name ): string {
	return $GLOBALS['wpdb']->prefix . 'site_pulse_' . $name;
}

function site_pulse_config( string $key = '' ) {
	$config = get_option( 'site_pulse', [] );
	if ( $key === '' ) return $config;
	return $config[$key] ?? '';
}

function site_pulse_effective_user_id(): int {
	$real_id = get_current_user_id();
	if ( ! site_pulse_is_god( $real_id ) ) return $real_id;
	$impersonate = (int) get_user_meta( $real_id, '_sp_impersonate', true );
	return $impersonate ?: $real_id;
}

function site_pulse_is_impersonating(): bool {
	$real_id = get_current_user_id();
	if ( ! site_pulse_is_god( $real_id ) ) return false;
	return (bool) get_user_meta( $real_id, '_sp_impersonate', true );
}

function site_pulse_log( string $action, string $description, array $meta = [], int $for_user_id = 0 ): void {
	global $wpdb;
	$actor_id = get_current_user_id();
	$actor    = get_userdata( $actor_id );
	$wpdb->insert(
		site_pulse_table('activity_log'),
		[
			'actor_id'    => $actor_id,
			'actor_name'  => $actor ? $actor->display_name : 'System',
			'for_user_id' => $for_user_id,
			'action'      => $action,
			'description' => $description,
			'meta'        => $meta ? wp_json_encode( $meta ) : null,
			'created_at'  => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%d', '%s', '%s', '%s', '%s' ]
	);
}


/*--------------------------------------------------------------
# Database Schema
--------------------------------------------------------------*/

function site_pulse_install_db(): void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE " . site_pulse_table('locations') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		location_type varchar(100) NOT NULL DEFAULT '',
		address varchar(255) DEFAULT NULL,
		city varchar(100) DEFAULT NULL,
		state varchar(50) DEFAULT NULL,
		zip varchar(20) DEFAULT NULL,
		phone varchar(30) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		display_order tinyint(3) NOT NULL DEFAULT 0,
		meta text DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('roles') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		slug varchar(50) NOT NULL,
		label varchar(100) NOT NULL,
		capabilities text NOT NULL,
		hierarchy_level tinyint(3) NOT NULL DEFAULT 0,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		UNIQUE KEY slug (slug)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('user_profiles') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		role_id int(11) NOT NULL DEFAULT 0,
		location_id int(11) NOT NULL DEFAULT 0,
		supervisor_id int(11) NOT NULL DEFAULT 0,
		mileage_home_location_id int(11) NOT NULL DEFAULT 0,
		employee_id varchar(50) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		meta text DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY user_id (user_id),
		KEY location_id (location_id),
		KEY supervisor_id (supervisor_id),
		KEY role_id (role_id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('report_templates') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		slug varchar(100) NOT NULL,
		name varchar(255) NOT NULL,
		description text DEFAULT NULL,
		frequency varchar(20) NOT NULL DEFAULT 'weekly',
		required_role_slug varchar(50) NOT NULL DEFAULT 'manager',
		is_active tinyint(1) NOT NULL DEFAULT 1,
		display_order tinyint(3) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY slug (slug)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('report_fields') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		template_id int(11) NOT NULL,
		field_key varchar(100) NOT NULL,
		label varchar(255) NOT NULL,
		field_type varchar(50) NOT NULL DEFAULT 'textarea',
		options text DEFAULT NULL,
		placeholder varchar(255) DEFAULT NULL,
		is_required tinyint(1) NOT NULL DEFAULT 0,
		display_order tinyint(3) NOT NULL DEFAULT 0,
		section varchar(100) DEFAULT NULL,
		help_text text DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY template_field (template_id, field_key),
		KEY template_id (template_id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('reports') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		template_id int(11) NOT NULL,
		user_id int(11) NOT NULL,
		location_id int(11) NOT NULL,
		report_period_start date NOT NULL,
		report_period_end date NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'draft',
		submitted_at datetime DEFAULT NULL,
		reviewed_by int(11) DEFAULT NULL,
		reviewed_at datetime DEFAULT NULL,
		review_notes text DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY location_id (location_id),
		KEY template_id (template_id),
		KEY status (status),
		KEY period (report_period_start, report_period_end)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('report_answers') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		report_id int(11) NOT NULL,
		field_id int(11) NOT NULL,
		field_key varchar(100) NOT NULL,
		answer_text text DEFAULT NULL,
		answer_numeric decimal(12,2) DEFAULT NULL,
		answer_json text DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY report_field (report_id, field_id),
		KEY report_id (report_id),
		KEY field_key (field_key)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('config') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		config_key varchar(100) NOT NULL,
		config_value text DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY config_key (config_key)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('activity_log') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		actor_id int(11) NOT NULL,
		actor_name varchar(255) NOT NULL,
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

	$sql .= "CREATE TABLE " . site_pulse_table('notifications') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		type varchar(50) NOT NULL,
		related_id int(11) DEFAULT NULL,
		related_type varchar(50) DEFAULT NULL,
		message text NOT NULL,
		is_read tinyint(1) NOT NULL DEFAULT 0,
		is_archived tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_notifications (user_id, is_read, is_archived)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('action_items') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		report_id int(11) NOT NULL,
		user_id int(11) NOT NULL,
		location_id int(11) NOT NULL DEFAULT 0,
		category varchar(100) DEFAULT NULL,
		description text NOT NULL,
		priority varchar(20) NOT NULL DEFAULT 'medium',
		status varchar(20) NOT NULL DEFAULT 'open',
		due_date date DEFAULT NULL,
		resolved_at datetime DEFAULT NULL,
		resolved_by int(11) DEFAULT NULL,
		resolution_note text DEFAULT NULL,
		meta text DEFAULT NULL,
		display_order int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY report_id (report_id),
		KEY status (status),
		KEY location_id (location_id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('embeddings') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		entity_type varchar(50) NOT NULL,
		entity_id int(11) NOT NULL,
		content_hash varchar(64) NOT NULL,
		embedding longtext NOT NULL,
		model varchar(100) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY entity (entity_type, entity_id),
		KEY content_hash (content_hash)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('mileage_locations') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		address varchar(500) DEFAULT NULL,
		lat decimal(10,7) DEFAULT NULL,
		lng decimal(10,7) DEFAULT NULL,
		location_type varchar(50) NOT NULL DEFAULT 'vendor',
		is_private tinyint(1) NOT NULL DEFAULT 0,
		category varchar(50) DEFAULT NULL,
		is_business tinyint(1) NOT NULL DEFAULT 1,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		notes text DEFAULT NULL,
		pinned_purposes text DEFAULT NULL,
		marker_icon varchar(500) DEFAULT NULL,
		site_pulse_location_id int(11) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		created_by int(11) NOT NULL DEFAULT 0,
		approved_by int(11) DEFAULT NULL,
		approved_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY status (status),
		KEY created_by (created_by)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('mileage_distances') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		from_id int(11) NOT NULL,
		to_id int(11) NOT NULL,
		miles decimal(8,2) NOT NULL,
		source varchar(20) NOT NULL DEFAULT 'api',
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY pair (from_id, to_id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('mileage_entries') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		entry_date date NOT NULL,
		total_miles decimal(10,2) NOT NULL DEFAULT 0,
		reimbursement_amount decimal(10,2) NOT NULL DEFAULT 0,
		total_tolls decimal(10,2) NOT NULL DEFAULT 0,
		total_trailer decimal(10,2) NOT NULL DEFAULT 0,
		rate_used decimal(6,4) DEFAULT NULL,
		auto_return_home tinyint(1) NOT NULL DEFAULT 1,
		notes text DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_date (user_id, entry_date)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('mileage_legs') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		entry_id int(11) NOT NULL,
		leg_order tinyint(3) NOT NULL DEFAULT 0,
		from_location_id int(11) NOT NULL,
		to_location_id int(11) NOT NULL,
		miles decimal(8,2) DEFAULT NULL,
		purpose varchar(255) DEFAULT NULL,
		has_toll tinyint(1) NOT NULL DEFAULT 0,
		toll_cost decimal(8,2) DEFAULT NULL,
		has_trailer tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY entry_id (entry_id),
		KEY from_loc (from_location_id),
		KEY to_loc (to_location_id)
	) $charset;";

	// Toll reconciliation — directional route/plaza matrix (lazy TollGuru/Routes cache).
	// One row per (from → to) direction. Distances live in mileage_distances (symmetric);
	// polylines/plaza sequences are directional, so they live here instead.
	$sql .= "CREATE TABLE " . site_pulse_table('mileage_toll_routes') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		from_location_id int(11) NOT NULL,
		to_location_id int(11) NOT NULL,
		variant_index tinyint(3) NOT NULL DEFAULT 1,
		variant_label varchar(255) DEFAULT NULL,
		is_primary tinyint(1) NOT NULL DEFAULT 1,
		polyline longtext DEFAULT NULL,
		plaza_sequence longtext DEFAULT NULL,
		total_typical_cost decimal(8,2) DEFAULT NULL,
		tag_cost decimal(8,2) DEFAULT NULL,
		cash_cost decimal(8,2) DEFAULT NULL,
		toll_computed_at datetime DEFAULT NULL,
		plaza_count tinyint(3) NOT NULL DEFAULT 0,
		source varchar(20) NOT NULL DEFAULT 'google',
		use_count int(11) NOT NULL DEFAULT 0,
		last_used_date date DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY direction (from_location_id, to_location_id)
	) $charset;";

	// Toll reconciliation — one imported NTTA bill per user per billing period.
	$sql .= "CREATE TABLE " . site_pulse_table('mileage_toll_bills') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		period_start date DEFAULT NULL,
		period_end date DEFAULT NULL,
		plate varchar(50) DEFAULT NULL,
		toll_tag_id varchar(50) DEFAULT NULL,
		file_name varchar(255) DEFAULT NULL,
		txn_count int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'pending_review',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_period (user_id, period_start)
	) $charset;";

	// Toll reconciliation — one row per NTTA transaction line, with its allocation decision.
	$sql .= "CREATE TABLE " . site_pulse_table('mileage_toll_transactions') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		bill_id int(11) NOT NULL,
		user_id int(11) NOT NULL,
		txn_external_id varchar(50) DEFAULT NULL,
		txn_datetime datetime DEFAULT NULL,
		road varchar(255) DEFAULT NULL,
		gantry varchar(255) DEFAULT NULL,
		internal_code_prefix varchar(100) DEFAULT NULL,
		amount decimal(8,2) NOT NULL DEFAULT 0,
		plaza_lat decimal(10,7) DEFAULT NULL,
		plaza_lng decimal(10,7) DEFAULT NULL,
		allocation_status varchar(20) NOT NULL DEFAULT 'unprocessed',
		allocation_entry_id int(11) DEFAULT NULL,
		allocation_confidence decimal(4,3) DEFAULT NULL,
		allocation_note varchar(255) DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY bill_id (bill_id),
		KEY user_id (user_id),
		KEY allocation_status (allocation_status)
	) $charset;";

	dbDelta( $sql );
	update_option( 'site_pulse_db_version', SITE_PULSE_DB_VERSION );
}

add_action( 'init', function() {
	$stored = get_option( 'site_pulse_db_version' );
	if ( $stored !== SITE_PULSE_DB_VERSION ) {
		site_pulse_install_db();
		site_pulse_seed_roles();
	}

	if ( ! get_option( 'site_pulse_seeded' ) ) {
		site_pulse_seed_initial_data();
		update_option( 'site_pulse_seeded', '1' );
	}

	if ( ! get_option( 'site_pulse_mileage_seeded' ) ) {
		site_pulse_seed_mileage_locations();
		update_option( 'site_pulse_mileage_seeded', '1' );
	}

	// One-time: stand up the Supervisor report (independent copy of the GM report) and bring
	// the existing Supervisor role onto the new report-visibility capabilities. Both self-guard
	// with their own option flag, so deleting either later won't resurrect it.
	if ( ! get_option( 'site_pulse_supervisor_report_seeded' ) ) {
		site_pulse_ensure_supervisor_template();
		update_option( 'site_pulse_supervisor_report_seeded', '1' );
	}
	site_pulse_migrate_supervisor_report_caps();
	site_pulse_migrate_retire_legacy_report_caps();

	// One-time: the original store "Manager" tier is now the "GM" (and its report likewise). The
	// seed/template seeders are insert-only, so existing installs need this nudge. Each row is only
	// touched if it still holds the old default text, so a deliberate admin rename is never
	// clobbered. Runs once.
	if ( ! get_option( 'site_pulse_role_gm_relabel' ) ) {
		global $wpdb;
		$wpdb->update( site_pulse_table('roles'), [ 'label' => 'GM' ], [ 'slug' => 'manager', 'label' => 'Manager' ] );
		$wpdb->update( site_pulse_table('report_templates'), [ 'name' => 'GM Bi-Weekly Report' ], [ 'slug' => 'manager-biweekly', 'name' => 'Manager Bi-Weekly Report' ] );
		update_option( 'site_pulse_role_gm_relabel', '1' );
	}

	// Make sure the daily mileage-reminder cron exists when enabled (survives restarts).
	if ( site_pulse_get_setting( 'mileage_reminders_enabled', '0' ) === '1' && ! wp_next_scheduled( 'site_pulse_mileage_reminder' ) ) {
		site_pulse_reschedule_mileage_reminder();
	}
} );

function site_pulse_seed_mileage_locations(): void {
	global $wpdb;
	$existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . site_pulse_table('mileage_locations') );
	if ( $existing ) return;

	$restaurants = $wpdb->get_results(
		"SELECT id, name, city, state, address FROM " . site_pulse_table('locations') . " WHERE status = 'active' ORDER BY display_order, name",
		ARRAY_A
	) ?: [];

	$now = current_time( 'mysql' );
	foreach ( $restaurants as $r ) {
		$address_parts = array_filter( [ $r['address'], $r['city'], $r['state'] ] );
		$wpdb->insert( site_pulse_table('mileage_locations'), [
			'name'                   => $r['name'],
			'address'                => implode( ', ', $address_parts ),
			'location_type'          => 'restaurant',
			'site_pulse_location_id' => (int) $r['id'],
			'status'                 => 'approved',
			'created_by'             => 0,
			'approved_at'            => $now,
			'created_at'             => $now,
			'updated_at'             => $now,
		] );
	}
}

function site_pulse_seed_initial_data(): void {
	$config = get_option( 'site_pulse', [] );
	$locations = $config['seed_locations'] ?? [];

	if ( ! empty( $locations ) ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		foreach ( $locations as $i => $loc ) {
			$name = is_array( $loc ) ? $loc['name'] : $loc;
			$type = is_array( $loc ) ? ( $loc['type'] ?? '' ) : '';
			$city = is_array( $loc ) ? ( $loc['city'] ?? '' ) : '';

			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . site_pulse_table('locations') . " WHERE name = %s", $name
			) );
			if ( ! $exists ) {
				$wpdb->insert( site_pulse_table('locations'), [
					'name'          => $name,
					'location_type' => $type,
					'city'          => $city,
					'state'         => 'TX',
					'status'        => 'active',
					'display_order' => $i,
					'created_at'    => $now,
					'updated_at'    => $now,
				] );
			}
		}
	}

	$bp_user = get_user_by( 'login', 'battleplanweb' );
	if ( $bp_user ) {
		$existing = site_pulse_get_user_profile( $bp_user->ID );
		if ( ! $existing ) {
			$role = site_pulse_get_role_by_slug( 'god' );
			if ( $role ) {
				global $wpdb;
				$now = current_time( 'mysql' );
				$wpdb->insert( site_pulse_table('user_profiles'), [
					'user_id'       => $bp_user->ID,
					'role_id'       => (int) $role['id'],
					'location_id'   => 0,
					'supervisor_id' => 0,
					'status'        => 'active',
					'created_at'    => $now,
					'updated_at'    => $now,
				], [ '%d', '%d', '%d', '%d', '%s', '%s', '%s' ] );
			}
		}
	}

	$templates = $config['seed_report_templates'] ?? [];
	if ( ! empty( $templates ) ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		foreach ( $templates as $t_index => $tpl ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . site_pulse_table('report_templates') . " WHERE slug = %s", $tpl['slug']
			) );
			if ( ! $exists ) {
				$wpdb->insert( site_pulse_table('report_templates'), [
					'slug'               => sanitize_title( $tpl['slug'] ),
					'name'               => sanitize_text_field( $tpl['name'] ),
					'description'        => sanitize_text_field( $tpl['description'] ?? '' ),
					'frequency'          => sanitize_text_field( $tpl['frequency'] ?? 'weekly' ),
					'required_role_slug' => sanitize_text_field( $tpl['role'] ?? 'manager' ),
					'is_active'          => 1,
					'display_order'      => $t_index,
					'created_at'         => $now,
					'updated_at'         => $now,
				] );
				$template_id = (int) $wpdb->insert_id;

				if ( $template_id && ! empty( $tpl['fields'] ) ) {
					foreach ( $tpl['fields'] as $f_index => $field ) {
						$wpdb->insert( site_pulse_table('report_fields'), [
							'template_id'   => $template_id,
							'field_key'     => sanitize_key( $field['key'] ),
							'label'         => sanitize_text_field( $field['label'] ),
							'field_type'    => sanitize_text_field( $field['type'] ?? 'textarea' ),
							'options'       => isset( $field['options'] ) ? wp_json_encode( $field['options'] ) : null,
							'placeholder'   => sanitize_text_field( $field['placeholder'] ?? '' ),
							'is_required'   => (int) ( $field['required'] ?? 0 ),
							'display_order' => $f_index,
							'section'       => sanitize_text_field( $field['section'] ?? '' ),
							'help_text'     => sanitize_text_field( $field['help_text'] ?? '' ),
						] );
					}
				}
			}
		}
	}
}

/**
 * Create the "Supervisor Bi-Weekly Report" as an independent copy of the GM report — the
 * template row plus every field as its own new record, so the two diverge freely from here.
 * Idempotent (no-ops if the supervisor template already exists or the GM one doesn't).
 */
function site_pulse_ensure_supervisor_template(): void {
	global $wpdb;
	$tpl_tbl = site_pulse_table('report_templates');
	$fld_tbl = site_pulse_table('report_fields');

	if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tpl_tbl WHERE slug = %s", 'supervisor-biweekly' ) ) ) return;

	$gm = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tpl_tbl WHERE slug = %s", 'manager-biweekly' ), ARRAY_A );
	if ( ! $gm ) return; // nothing to clone yet

	$now   = current_time( 'mysql' );
	$order = (int) $wpdb->get_var( "SELECT COALESCE(MAX(display_order), -1) + 1 FROM $tpl_tbl" );
	$wpdb->insert( $tpl_tbl, [
		'slug'               => 'supervisor-biweekly',
		'name'               => 'Supervisor Bi-Weekly Report',
		'description'        => $gm['description'],
		'frequency'          => $gm['frequency'],
		'required_role_slug' => 'supervisor',
		'is_active'          => 1,
		'display_order'      => $order,
		'created_at'         => $now,
		'updated_at'         => $now,
	] );
	$new_id = (int) $wpdb->insert_id;
	if ( ! $new_id ) return;

	$fields = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $fld_tbl WHERE template_id = %d ORDER BY display_order, id", (int) $gm['id']
	), ARRAY_A ) ?: [];
	foreach ( $fields as $f ) {
		$wpdb->insert( $fld_tbl, [
			'template_id'   => $new_id,
			'field_key'     => $f['field_key'],
			'label'         => $f['label'],
			'field_type'    => $f['field_type'],
			'options'       => $f['options'],
			'placeholder'   => $f['placeholder'],
			'is_required'   => $f['is_required'],
			'display_order' => $f['display_order'],
			'section'       => $f['section'],
			'help_text'     => $f['help_text'],
		] );
	}
}

/**
 * One-time: move an EXISTING Supervisor role off the legacy view_team_reports model onto the
 * new caps — see all GM reports + submit/view their own Supervisor report. Guarded by an option
 * so a later admin customization of the role isn't repeatedly clobbered. (New sites already get
 * these caps from seed_roles; this only fixes already-seeded sites.)
 */
function site_pulse_migrate_supervisor_report_caps(): void {
	if ( get_option( 'site_pulse_supervisor_caps_migrated' ) ) return;
	update_option( 'site_pulse_supervisor_caps_migrated', '1' );

	global $wpdb;
	$tbl = site_pulse_table('roles');
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, capabilities FROM $tbl WHERE slug = %s", 'supervisor' ), ARRAY_A );
	if ( ! $row ) return;

	$caps = json_decode( $row['capabilities'], true ) ?: [];
	$caps = array_values( array_diff( $caps, [ 'view_team_reports' ] ) );
	foreach ( [ 'view_gm_reports', 'submit_reports', 'view_own_reports' ] as $c ) {
		if ( ! in_array( $c, $caps, true ) ) $caps[] = $c;
	}
	$wpdb->update( $tbl, [ 'capabilities' => wp_json_encode( $caps ), 'updated_at' => current_time( 'mysql' ) ], [ 'id' => (int) $row['id'] ] );
}

/**
 * One-time cap cleanup: retire view_all_reports / view_team_reports / review_reports across ALL
 * tiers. A tier that had "View all reports" is expanded to the two granular type caps so its
 * visibility is unchanged (it just shows the individual boxes checked). Guarded by an option so
 * a later admin customization of a tier isn't repeatedly clobbered.
 */
function site_pulse_migrate_retire_legacy_report_caps(): void {
	if ( get_option( 'site_pulse_report_caps_retired' ) ) return;
	update_option( 'site_pulse_report_caps_retired', '1' );

	global $wpdb;
	$tbl  = site_pulse_table('roles');
	$rows = $wpdb->get_results( "SELECT id, capabilities FROM $tbl", ARRAY_A ) ?: [];
	foreach ( $rows as $row ) {
		$caps = json_decode( $row['capabilities'], true ) ?: [];
		if ( in_array( 'view_all_reports', $caps, true ) ) {
			foreach ( [ 'view_gm_reports', 'view_supervisor_reports' ] as $c ) {
				if ( ! in_array( $c, $caps, true ) ) $caps[] = $c;
			}
		}
		$caps = array_values( array_diff( $caps, [ 'view_all_reports', 'view_team_reports', 'review_reports' ] ) );
		$wpdb->update( $tbl, [ 'capabilities' => wp_json_encode( $caps ), 'updated_at' => current_time( 'mysql' ) ], [ 'id' => (int) $row['id'] ] );
	}
}

function site_pulse_seed_roles(): void {
	global $wpdb;
	$now = current_time( 'mysql' );
	$roles = [
		[
			'slug'            => 'god',
			'label'           => 'Odinson',
			'capabilities'    => wp_json_encode( [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'view_ai_insights', 'submit_reports', 'view_own_reports', 'god_mode', 'manage_mileage', 'submit_mileage' ] ),
			'hierarchy_level' => 255,
		],
		[
			'slug'            => 'owner',
			'label'           => 'Owner',
			'capabilities'    => wp_json_encode( [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'view_ai_insights', 'manage_mileage', 'submit_mileage' ] ),
			'hierarchy_level' => 100,
		],
		[
			'slug'            => 'admin',
			'label'           => 'Administrator',
			'capabilities'    => wp_json_encode( [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'view_analytics', 'manage_settings', 'view_ai_insights', 'manage_mileage', 'submit_mileage' ] ),
			'hierarchy_level' => 90,
		],
		[
			'slug'            => 'supervisor',
			'label'           => 'Supervisor',
			'capabilities'    => wp_json_encode( [ 'view_gm_reports', 'submit_reports', 'view_own_reports', 'view_analytics', 'submit_mileage' ] ),
			'hierarchy_level' => 50,
		],
		[
			'slug'            => 'manager',
			'label'           => 'GM',
			'capabilities'    => wp_json_encode( [ 'submit_reports', 'view_own_reports', 'view_analytics', 'submit_mileage' ] ),
			'hierarchy_level' => 20,
		],
		[
			'slug'            => 'non-store-manager',
			'label'           => 'Manager',
			'capabilities'    => wp_json_encode( [ 'submit_reports', 'view_own_reports', 'view_analytics', 'submit_mileage' ] ),
			'hierarchy_level' => 20,
		],
	];

	// Insert-only: seed defaults on first run, but never overwrite a tier an admin has since
	// renamed or re-permissioned in the Tiers editor. (The God tier is force-synced so the
	// superuser can never be locked out by a stale/edited capability set.)
	foreach ( $roles as $role ) {
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . site_pulse_table('roles') . " WHERE slug = %s", $role['slug']
		) );
		if ( ! $exists ) {
			$wpdb->insert( site_pulse_table('roles'), $role );
		} elseif ( $role['slug'] === 'god' ) {
			$wpdb->update( site_pulse_table('roles'), $role, [ 'slug' => 'god' ] );
		}
	}
}

function site_pulse_is_god( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = get_current_user_id();
	$profile = site_pulse_get_user_profile( $user_id );
	if ( ! $profile ) return false;
	$role = site_pulse_get_role( $profile['role_id'] );
	return $role && $role['slug'] === 'god';
}

// The protected super-admin: the `battleplanweb` account. It alone manages the God tier
// (grant/revoke/delete Gods) and is the only account hidden from the user list. Other Gods are
// ordinary visible users that happen to hold the God role.
function site_pulse_is_superadmin( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = get_current_user_id();
	$u = get_user_by( 'id', $user_id );
	return $u && $u->user_login === 'battleplanweb';
}


/*--------------------------------------------------------------
# Page Detection & Caching
--------------------------------------------------------------*/

add_action( 'template_redirect', function() {
	global $post;
	// The entire Site Pulse app is per-user, auth-gated, nonce-bearing content with no caching
	// upside (no heavy media). Never cache ANY of it — a cached page can serve one user another's
	// dashboard or hand out a stale nonce that silently 403s every request. Match the whole
	// `site-pulse-*` slug family so future Site Pulse pages are covered automatically.
	if ( ! $post || strpos( (string) $post->post_name, 'site-pulse' ) !== 0 ) return;

	// DONOTCACHEPAGE → WP Engine EverCache; nocache_headers() + the explicit header below →
	// browsers AND Cloudflare (a separate layer that obeys Cache-Control, not DONOTCACHEPAGE).
	if ( ! defined('DONOTCACHEPAGE') ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();
	if ( ! headers_sent() ) header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );

	add_filter( 'body_class', function( $classes ) {
		$classes[] = 'has-site-pulse';
		$eff_id  = site_pulse_effective_user_id();
		$profile = site_pulse_get_user_profile( $eff_id );
		if ( $profile ) {
			$role = site_pulse_get_role( $profile['role_id'] );
			if ( $role ) $classes[] = 'sp-role-' . $role['slug'];
		}
		return $classes;
	} );
} );


/*--------------------------------------------------------------
# Asset Enqueueing
--------------------------------------------------------------*/

add_action( 'wp_enqueue_scripts', 'site_pulse_enqueue_assets' );
function site_pulse_enqueue_assets(): void {
	$sp_slugs = [ 'site-pulse-login', 'site-pulse-dashboard' ];

	global $post;
	if ( ! $post || ! in_array( $post->post_name, $sp_slugs, true ) ) return;

	$script_file = file_exists( get_template_directory() . '/js/script-site-pulse.min.js' )
		? '/js/script-site-pulse.min.js'
		: '/js/script-site-pulse.js';

	$script_deps = [];

	// jsPDF + autoTable power the polished PDF/CSV mileage report (dashboard only).
	if ( $post->post_name === 'site-pulse-dashboard' ) {
		wp_enqueue_script( 'jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], '2.5.1', true );
		wp_enqueue_script( 'jspdf-autotable', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js', [ 'jspdf' ], '3.5.28', true );
		$script_deps = [ 'jspdf', 'jspdf-autotable' ];
	}

	wp_enqueue_script(
		'site-pulse-script',
		get_template_directory_uri() . $script_file,
		$script_deps,
		filemtime( get_template_directory() . $script_file ),
		true
	);

	$eff_user_id = site_pulse_effective_user_id();
	$real_user_id = get_current_user_id();
	$profile   = site_pulse_get_user_profile( $eff_user_id );
	$role_data = $profile ? site_pulse_get_role( $profile['role_id'] ) : null;
	$is_god    = site_pulse_is_god( $real_user_id );

	$sp_config          = get_option( 'site_pulse', [] );
	$header_fields      = $sp_config['report_header_fields'] ?? [];
	$location_name      = '';
	if ( $profile && $profile['location_id'] ) {
		$loc = site_pulse_get_location( (int) $profile['location_id'] );
		$location_name = $loc ? $loc['name'] : '';
	}

	wp_localize_script( 'site-pulse-script', 'sitePulseData', [
		'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
		'nonce'              => wp_create_nonce( 'site_pulse_nonce' ),
		'userId'             => $eff_user_id,
		'userRole'           => $role_data ? $role_data['slug'] : '',
		'userCaps'           => $role_data ? json_decode( $role_data['capabilities'], true ) : [],
		'locationId'         => $profile ? (int) $profile['location_id'] : 0,
		'locationName'       => $location_name,
		'appName'            => site_pulse_get_setting( 'app_name', 'Site Pulse' ),
		'companyName'        => site_pulse_get_setting( 'company_name', '' ),
		'reportHeaderFields' => $header_fields,
		'isGod'              => $is_god,
		// Only true for the real battleplanweb session AND not while impersonating — so "view as
		// <someone>" hides the super-admin-only controls (Grant/Revoke God) like that user sees.
		'isSuperadmin'       => site_pulse_is_superadmin( $real_user_id ) && ! site_pulse_is_impersonating(),
		'impersonating'      => site_pulse_is_impersonating(),
		// Google Maps JS key for the (client-side) mileage/toll maps. Prefers a dedicated
		// browser key if set, else the main key. NOTE: this key is exposed to the browser,
		// so it MUST be HTTP-referrer restricted in the Google Cloud Console.
		'mapsKey'            => $post->post_name === 'site-pulse-dashboard'
			? ( site_pulse_get_setting( 'maps_js_api_key', '' ) ?: site_pulse_mileage_google_key() )
			: '',
	] );
}


/*--------------------------------------------------------------
# User Roles & Capabilities
--------------------------------------------------------------*/

function site_pulse_register_wp_roles(): void {
	add_role( 'site_pulse_user', 'Site Pulse User', [ 'read' => true ] );
	add_role( 'site_pulse_admin', 'Site Pulse Admin', [ 'read' => true, 'manage_site_pulse' => true ] );
}

function site_pulse_remove_wp_roles(): void {
	remove_role( 'site_pulse_user' );
	remove_role( 'site_pulse_admin' );
}

add_action( 'admin_init', function() {
	if ( get_option('site_pulse_roles_registered') !== '1' ) {
		site_pulse_register_wp_roles();
		update_option( 'site_pulse_roles_registered', '1' );
	}
} );


/*--------------------------------------------------------------
# Permission Helpers
--------------------------------------------------------------*/

function site_pulse_get_role( int $role_id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('roles') . " WHERE id = %d",
		$role_id
	), ARRAY_A );
	return $row ?: null;
}

function site_pulse_get_role_by_slug( string $slug ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('roles') . " WHERE slug = %s",
		$slug
	), ARRAY_A );
	return $row ?: null;
}

function site_pulse_get_all_roles( bool $include_god = false ): array {
	global $wpdb;
	$where = $include_god ? "WHERE is_active = 1" : "WHERE is_active = 1 AND slug != 'god'";
	return $wpdb->get_results(
		"SELECT * FROM " . site_pulse_table('roles') . " $where ORDER BY hierarchy_level DESC",
		ARRAY_A
	) ?: [];
}

/**
 * The catalog of assignable capabilities (cap => friendly label). Single source of truth
 * for the Tiers editor's checkbox grid and for validating saved capabilities. `god_mode` is
 * intentionally excluded — it is internal and only the (hidden) God tier carries it.
 */
function site_pulse_capability_catalog(): array {
	return [
		'view_own_reports'        => 'View own reports',
		'submit_reports'          => 'Submit reports',
		'view_gm_reports'         => 'View all GM reports',
		'view_supervisor_reports' => 'View all supervisor reports',
		'view_analytics'    => 'View analytics',
		'view_ai_insights'  => 'View AI insights',
		'submit_mileage'    => 'Submit mileage',
		'manage_mileage'    => 'Manage mileage settings',
		'manage_locations'  => 'Manage home bases',
		'manage_users'      => 'Manage users',
		'manage_templates'  => 'Manage report templates',
		'manage_settings'   => 'Manage site settings',
		'manage_roles'      => 'Manage tiers',
	];
}

function site_pulse_get_user_profile( int $user_id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('user_profiles') . " WHERE user_id = %d",
		$user_id
	), ARRAY_A );
	return $row ?: null;
}

function site_pulse_user_can( int $user_id, string $capability ): bool {
	$profile = site_pulse_get_user_profile( $user_id );
	if ( ! $profile ) return false;

	$role = site_pulse_get_role( $profile['role_id'] );
	if ( ! $role ) return false;

	$caps = json_decode( $role['capabilities'], true ) ?: [];
	return in_array( $capability, $caps, true );
}

function site_pulse_user_hierarchy( int $user_id ): int {
	$profile = site_pulse_get_user_profile( $user_id );
	if ( ! $profile ) return 0;

	$role = site_pulse_get_role( $profile['role_id'] );
	return $role ? (int) $role['hierarchy_level'] : 0;
}

function site_pulse_get_team_user_ids( int $supervisor_id ): array {
	global $wpdb;
	return $wpdb->get_col( $wpdb->prepare(
		"SELECT user_id FROM " . site_pulse_table('user_profiles') . " WHERE supervisor_id = %d AND status = 'active'",
		$supervisor_id
	) ) ?: [];
}

// Active user IDs whose Site Pulse role has the given slug (e.g. 'manager' = GMs, 'supervisor').
function site_pulse_user_ids_with_role( string $role_slug ): array {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT up.user_id
		 FROM " . site_pulse_table('user_profiles') . " up
		 INNER JOIN " . site_pulse_table('roles') . " r ON r.id = up.role_id
		 WHERE r.slug = %s AND up.status = 'active'",
		$role_slug
	) ) ?: [] );
}

/**
 * The set of submitter user IDs whose reports/action items $viewer may see, driven by the
 * report-visibility capabilities (keyed on the submitter's role = the report type):
 *   - view_gm_reports         → all GMs (role 'manager')
 *   - view_supervisor_reports → all supervisors (role 'supervisor')
 * The viewer always sees their own. Returns the (always non-empty) list of user IDs — to see
 * every report a role simply holds both type caps.
 */
function site_pulse_visible_report_user_ids( int $viewer_id ): array {
	$ids = [ $viewer_id ]; // own
	if ( site_pulse_user_can( $viewer_id, 'view_gm_reports' ) ) {
		$ids = array_merge( $ids, site_pulse_user_ids_with_role( 'manager' ) );
	}
	if ( site_pulse_user_can( $viewer_id, 'view_supervisor_reports' ) ) {
		$ids = array_merge( $ids, site_pulse_user_ids_with_role( 'supervisor' ) );
	}
	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

function site_pulse_can_view_report( int $viewer_id, array $report ): bool {
	if ( (int) $report['user_id'] === $viewer_id ) return true;
	return in_array( (int) $report['user_id'], site_pulse_visible_report_user_ids( $viewer_id ), true );
}


/*--------------------------------------------------------------
# Settings Helpers
--------------------------------------------------------------*/

function site_pulse_get_setting( string $key, string $default = '' ): string {
	global $wpdb;
	$val = $wpdb->get_var( $wpdb->prepare(
		"SELECT config_value FROM " . site_pulse_table('config') . " WHERE config_key = %s",
		$key
	) );
	if ( $val !== null ) return $val;

	$option = get_option( 'site_pulse', [] );
	if ( isset( $option[ $key ] ) ) return $option[ $key ];

	return $default;
}

function site_pulse_set_setting( string $key, string $value ): void {
	global $wpdb;
	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . site_pulse_table('config') . " WHERE config_key = %s",
		$key
	) );
	if ( $exists ) {
		$wpdb->update( site_pulse_table('config'), [ 'config_value' => $value ], [ 'config_key' => $key ] );
	} else {
		$wpdb->insert( site_pulse_table('config'), [ 'config_key' => $key, 'config_value' => $value ] );
	}
}


/*--------------------------------------------------------------
# Location Helpers
--------------------------------------------------------------*/

function site_pulse_get_location( int $id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('locations') . " WHERE id = %d",
		$id
	), ARRAY_A );
	return $row ?: null;
}

function site_pulse_get_all_locations( bool $active_only = true ): array {
	global $wpdb;
	$where = $active_only ? "WHERE status = 'active'" : "";
	return $wpdb->get_results(
		"SELECT * FROM " . site_pulse_table('locations') . " $where ORDER BY display_order, name",
		ARRAY_A
	) ?: [];
}


/*--------------------------------------------------------------
# Report Template Helpers
--------------------------------------------------------------*/

function site_pulse_get_template( int $id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('report_templates') . " WHERE id = %d",
		$id
	), ARRAY_A );
	return $row ?: null;
}

function site_pulse_get_template_by_slug( string $slug ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('report_templates') . " WHERE slug = %s",
		$slug
	), ARRAY_A );
	return $row ?: null;
}

function site_pulse_get_template_fields( int $template_id ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('report_fields') . " WHERE template_id = %d ORDER BY display_order",
		$template_id
	), ARRAY_A ) ?: [];
}


/*--------------------------------------------------------------
# Report Submission & Retrieval
--------------------------------------------------------------*/

function site_pulse_create_report( int $template_id, int $user_id, int $location_id, string $period_start, string $period_end ): int {
	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert(
		site_pulse_table('reports'),
		[
			'template_id'         => $template_id,
			'user_id'             => $user_id,
			'location_id'         => $location_id,
			'report_period_start' => $period_start,
			'report_period_end'   => $period_end,
			'status'              => 'draft',
			'created_at'          => $now,
			'updated_at'          => $now,
		],
		[ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
	);
	return (int) $wpdb->insert_id;
}

function site_pulse_save_answer( int $report_id, int $field_id, string $field_key, $value ): void {
	global $wpdb;
	$data = [
		'report_id' => $report_id,
		'field_id'  => $field_id,
		'field_key' => $field_key,
	];

	if ( is_numeric( $value ) && ! is_string( $value ) ) {
		$data['answer_numeric'] = $value;
		$data['answer_text']    = (string) $value;
	} elseif ( is_array( $value ) ) {
		$data['answer_json'] = wp_json_encode( $value );
		$data['answer_text'] = wp_json_encode( $value );
	} else {
		$data['answer_text'] = (string) $value;
	}

	$wpdb->replace( site_pulse_table('report_answers'), $data );
}

function site_pulse_submit_report( int $report_id ): bool {
	global $wpdb;
	$now = current_time( 'mysql' );
	$updated = $wpdb->update(
		site_pulse_table('reports'),
		[ 'status' => 'submitted', 'submitted_at' => $now, 'updated_at' => $now ],
		[ 'id' => $report_id ],
		[ '%s', '%s', '%s' ],
		[ '%d' ]
	);

	if ( $updated ) {
		$report = site_pulse_get_report( $report_id );
		if ( $report ) {
			$user    = get_userdata( $report['user_id'] );
			$loc     = site_pulse_get_location( $report['location_id'] );
			$profile = site_pulse_get_user_profile( $report['user_id'] );
			$msg     = sprintf( '%s submitted a report for %s (%s – %s)',
				$user ? $user->display_name : 'Unknown',
				$loc ? $loc['name'] : 'Unknown Location',
				$report['report_period_start'],
				$report['report_period_end']
			);

			site_pulse_log( 'report_submitted', $msg, [ 'report_id' => $report_id ] );

			if ( $profile && $profile['supervisor_id'] ) {
				site_pulse_notify( (int) $profile['supervisor_id'], 'report_submitted', $msg, $report_id, 'report' );
			}
		}
	}

	return (bool) $updated;
}

function site_pulse_get_report( int $id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('reports') . " WHERE id = %d",
		$id
	), ARRAY_A );
	return $row ?: null;
}

function site_pulse_get_report_answers( int $report_id ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('report_answers') . " WHERE report_id = %d",
		$report_id
	), ARRAY_A ) ?: [];
}

function site_pulse_get_previous_report( int $template_id, int $location_id, int $exclude_report_id = 0 ): ?array {
	if ( ! $template_id || ! $location_id ) return null;

	global $wpdb;
	$where  = "template_id = %d AND location_id = %d AND status = 'submitted'";
	$values = [ $template_id, $location_id ];

	if ( $exclude_report_id ) {
		$where    .= " AND id != %d";
		$values[]  = $exclude_report_id;
	}

	$prev = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, report_period_start, report_period_end FROM " . site_pulse_table('reports') . "
		 WHERE $where
		 ORDER BY report_period_end DESC, submitted_at DESC
		 LIMIT 1",
		...$values
	), ARRAY_A );
	if ( ! $prev ) return null;

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT field_key, answer_text FROM " . site_pulse_table('report_answers') . " WHERE report_id = %d",
		(int) $prev['id']
	), ARRAY_A ) ?: [];

	$map = [];
	foreach ( $rows as $row ) {
		if ( ! empty( $row['field_key'] ) && trim( (string) $row['answer_text'] ) !== '' ) {
			$map[ $row['field_key'] ] = $row['answer_text'];
		}
	}

	return [
		'date'    => $prev['report_period_start'],
		'answers' => $map,
	];
}

function site_pulse_get_reports( array $args = [] ): array {
	global $wpdb;
	$where  = [ "1=1" ];
	$values = [];

	if ( ! empty( $args['user_id'] ) ) {
		$where[]  = "r.user_id = %d";
		$values[] = (int) $args['user_id'];
	}
	if ( ! empty( $args['location_id'] ) ) {
		$where[]  = "r.location_id = %d";
		$values[] = (int) $args['location_id'];
	}
	if ( ! empty( $args['template_id'] ) ) {
		$where[]  = "r.template_id = %d";
		$values[] = (int) $args['template_id'];
	}
	// Filter by report TYPE = the template's required_role_slug ('manager' = GM reports,
	// 'supervisor' = Supervisor reports). Lets the GM/Supervisor review tabs scope by type.
	if ( ! empty( $args['template_role'] ) ) {
		$where[]  = "r.template_id IN ( SELECT id FROM " . site_pulse_table('report_templates') . " WHERE required_role_slug = %s )";
		$values[] = $args['template_role'];
	}
	if ( ! empty( $args['status'] ) ) {
		$where[]  = "r.status = %s";
		$values[] = $args['status'];
	}
	if ( ! empty( $args['period_start'] ) ) {
		$where[]  = "r.report_period_start >= %s";
		$values[] = $args['period_start'];
	}
	if ( ! empty( $args['period_end'] ) ) {
		$where[]  = "r.report_period_end <= %s";
		$values[] = $args['period_end'];
	}
	if ( ! empty( $args['user_ids'] ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $args['user_ids'] ), '%d' ) );
		$where[]      = "r.user_id IN ($placeholders)";
		$values       = array_merge( $values, array_map( 'intval', $args['user_ids'] ) );
	}

	$order_by = $args['order_by'] ?? 'r.report_period_end DESC, r.created_at DESC';
	$limit    = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
	$offset   = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

	$sql = "SELECT r.*, l.name AS location_name, u.display_name AS author_name
			FROM " . site_pulse_table('reports') . " r
			LEFT JOIN " . site_pulse_table('locations') . " l ON l.id = r.location_id
			LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY $order_by
			LIMIT $limit OFFSET $offset";

	if ( $values ) {
		$sql = $wpdb->prepare( $sql, ...$values );
	}

	return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
}


/*--------------------------------------------------------------
# User Management
--------------------------------------------------------------*/

function site_pulse_create_user( array $data ): array {
	$username = sanitize_user( $data['username'] );
	$email    = sanitize_email( $data['email'] ?? $username . '@placeholder.local' );
	$password = $data['password'] ?? wp_generate_password( 12, true );

	if ( username_exists( $username ) ) {
		return [ 'success' => false, 'error' => 'Username already exists.' ];
	}

	$wp_user_id = wp_create_user( $username, $password, $email );
	if ( is_wp_error( $wp_user_id ) ) {
		return [ 'success' => false, 'error' => $wp_user_id->get_error_message() ];
	}

	wp_update_user( [
		'ID'           => $wp_user_id,
		'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
		'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
		'display_name' => trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) ) ?: $username,
		'role'         => 'site_pulse_user',
	] );

	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert(
		site_pulse_table('user_profiles'),
		[
			'user_id'                  => $wp_user_id,
			'role_id'                  => (int) ( $data['role_id'] ?? 0 ),
			'location_id'              => (int) ( $data['location_id'] ?? 0 ),
			'supervisor_id'            => (int) ( $data['supervisor_id'] ?? 0 ),
			'mileage_home_location_id' => (int) ( $data['mileage_home_location_id'] ?? 0 ),
			'employee_id'              => sanitize_text_field( $data['employee_id'] ?? '' ),
			'status'                   => 'active',
			'created_at'               => $now,
			'updated_at'               => $now,
		],
		[ '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
	);

	site_pulse_log( 'user_created', sprintf( 'Created user %s', $username ), [ 'wp_user_id' => $wp_user_id ] );

	return [ 'success' => true, 'user_id' => $wp_user_id, 'password' => $password ];
}

function site_pulse_get_all_users( bool $active_only = true, bool $include_god = false ): array {
	global $wpdb;
	$conditions = [];
	if ( $active_only ) $conditions[] = "up.status = 'active'";
	// Hide only the protected super-admin (battleplanweb), not the whole God role — other Gods
	// stay visible. NULL-safe so orphaned profiles (no WP user) still appear for cleanup.
	if ( ! $include_god ) $conditions[] = "( u.user_login IS NULL OR u.user_login != 'battleplanweb' )";
	$where = $conditions ? "WHERE " . implode( ' AND ', $conditions ) : "";
	return $wpdb->get_results(
		"SELECT up.*, u.user_login, u.user_email, u.display_name, r.slug AS role_slug, r.label AS role_label, l.name AS location_name,
		        hb.address AS home_address, hb.is_private AS home_is_private
		 FROM " . site_pulse_table('user_profiles') . " up
		 LEFT JOIN {$wpdb->users} u ON u.ID = up.user_id
		 LEFT JOIN " . site_pulse_table('roles') . " r ON r.id = up.role_id
		 LEFT JOIN " . site_pulse_table('locations') . " l ON l.id = up.location_id
		 LEFT JOIN " . site_pulse_table('mileage_locations') . " hb ON hb.id = up.mileage_home_location_id
		 $where
		 ORDER BY u.display_name",
		ARRAY_A
	) ?: [];
}


/*--------------------------------------------------------------
# AJAX Handlers
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_login', 'site_pulse_ajax_login' );
add_action( 'wp_ajax_nopriv_site_pulse_login', 'site_pulse_ajax_login' );
function site_pulse_ajax_login(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$username = sanitize_user( $_POST['username'] ?? '' );
	// Field is `sp_pass`, not `password`: WP Engine's WAF strips a POST field named
	// `password` on non-login endpoints (admin-ajax.php), which would blank the password
	// here and break every login. Accept the old name as a fallback for safety.
	$password = $_POST['sp_pass'] ?? ( $_POST['password'] ?? '' );

	if ( empty( $username ) || empty( $password ) ) {
		wp_send_json_error( [ 'message' => 'Please enter your username and password.' ] );
	}

	$user = wp_authenticate( $username, $password );
	if ( is_wp_error( $user ) ) {
		wp_send_json_error( [ 'message' => 'Invalid username or password.' ] );
	}

	$profile = site_pulse_get_user_profile( $user->ID );
	$is_wp_admin = in_array( 'administrator', (array) $user->roles, true );

	if ( ! $is_wp_admin && ( ! $profile || $profile['status'] !== 'active' ) ) {
		wp_send_json_error( [ 'message' => 'Your account is not active. Please contact an administrator.' ] );
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );

	site_pulse_log( 'login', sprintf( '%s logged in', $user->display_name ) );

	wp_send_json_success( [ 'redirect' => home_url( '/site-pulse-dashboard/' ) ] );
}

add_action( 'wp_ajax_site_pulse_logout', 'site_pulse_ajax_logout' );
function site_pulse_ajax_logout(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	wp_logout();
	wp_send_json_success( [ 'redirect' => home_url( '/site-pulse-login/' ) ] );
}

add_action( 'wp_ajax_site_pulse_save_report', 'site_pulse_ajax_save_report' );
function site_pulse_ajax_save_report(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id     = site_pulse_effective_user_id();
	$template_id = (int) ( $_POST['template_id'] ?? 0 );
	$report_id   = (int) ( $_POST['report_id'] ?? 0 );
	$answers     = $_POST['answers'] ?? [];
	$action_type = sanitize_text_field( $_POST['action_type'] ?? 'save' );

	if ( ! $user_id || ! site_pulse_user_can( $user_id, 'submit_reports' ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to submit reports.' ] );
	}

	$profile = site_pulse_get_user_profile( $user_id );
	if ( ! $profile ) {
		wp_send_json_error( [ 'message' => 'User profile not found.' ] );
	}

	if ( ! $report_id ) {
		$period_start = sanitize_text_field( $_POST['period_start'] ?? '' );
		$period_end   = sanitize_text_field( $_POST['period_end'] ?? '' );

		if ( ! $period_start || ! $period_end ) {
			wp_send_json_error( [ 'message' => 'Report period is required.' ] );
		}

		$report_id = site_pulse_create_report( $template_id, $user_id, (int) $profile['location_id'], $period_start, $period_end );
	}

	if ( ! $report_id ) {
		wp_send_json_error( [ 'message' => 'Failed to create report.' ] );
	}

	$report = site_pulse_get_report( $report_id );
	if ( (int) $report['user_id'] !== $user_id ) {
		wp_send_json_error( [ 'message' => 'You cannot edit this report.' ] );
	}

	$fields = site_pulse_get_template_fields( $template_id );
	$field_map = [];
	foreach ( $fields as $f ) {
		$field_map[ $f['field_key'] ] = $f;
	}

	foreach ( $answers as $key => $value ) {
		$key = sanitize_key( $key );
		if ( isset( $field_map[ $key ] ) ) {
			site_pulse_save_answer( $report_id, (int) $field_map[ $key ]['id'], $key, sanitize_textarea_field( $value ) );
		}
	}

	$pending_count = 0;
	if ( $action_type === 'submit' ) {
		site_pulse_submit_report( $report_id );
		$pending_count = site_pulse_create_action_items_from_report( $report_id );
	}

	global $wpdb;
	$wpdb->update(
		site_pulse_table('reports'),
		[ 'updated_at' => current_time( 'mysql' ) ],
		[ 'id' => $report_id ]
	);

	wp_send_json_success( [
		'report_id'     => $report_id,
		'status'        => $action_type === 'submit' ? 'submitted' : 'draft',
		'message'       => $action_type === 'submit' ? 'Report submitted successfully.' : 'Report saved as draft.',
		'pending_count' => $pending_count,
	] );
}

add_action( 'wp_ajax_site_pulse_get_reports', 'site_pulse_ajax_get_reports' );
function site_pulse_ajax_get_reports(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	$args    = [];

	// scope=own → strictly the viewer's own reports (the "My Reports" panel). Otherwise the
	// visible set is driven by the report-visibility capabilities (see visible_report_user_ids).
	$scope = sanitize_text_field( $_POST['scope'] ?? '' );
	if ( $scope === 'own' ) {
		$args['user_id'] = $user_id;
	} else {
		$visible = site_pulse_visible_report_user_ids( $user_id );
		if ( ! empty( $_POST['user_id'] ) && in_array( (int) $_POST['user_id'], $visible, true ) ) {
			$args['user_id'] = (int) $_POST['user_id'];
		} else {
			$args['user_ids'] = $visible;
		}
	}

	if ( ! empty( $_POST['location_id'] ) ) $args['location_id'] = (int) $_POST['location_id'];

	if ( ! empty( $_POST['template_id'] ) )    $args['template_id']    = (int) $_POST['template_id'];
	if ( ! empty( $_POST['template_role'] ) )  $args['template_role']  = sanitize_text_field( $_POST['template_role'] );
	if ( ! empty( $_POST['status'] ) )       $args['status']       = sanitize_text_field( $_POST['status'] );
	if ( ! empty( $_POST['period_start'] ) ) $args['period_start'] = sanitize_text_field( $_POST['period_start'] );
	if ( ! empty( $_POST['period_end'] ) )   $args['period_end']   = sanitize_text_field( $_POST['period_end'] );
	if ( isset( $_POST['limit'] ) )          $args['limit']        = (int) $_POST['limit'];
	if ( isset( $_POST['offset'] ) )         $args['offset']       = (int) $_POST['offset'];

	$reports = site_pulse_get_reports( $args );

	wp_send_json_success( [ 'reports' => $reports ] );
}

add_action( 'wp_ajax_site_pulse_get_report_detail', 'site_pulse_ajax_get_report_detail' );
function site_pulse_ajax_get_report_detail(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id   = site_pulse_effective_user_id();
	$report_id = (int) ( $_POST['report_id'] ?? 0 );

	$report = site_pulse_get_report( $report_id );
	if ( ! $report ) {
		wp_send_json_error( [ 'message' => 'Report not found.' ] );
	}

	if ( ! site_pulse_can_view_report( $user_id, $report ) ) {
		wp_send_json_error( [ 'message' => 'You do not have permission to view this report.' ] );
	}

	$answers  = site_pulse_get_report_answers( $report_id );
	$template = site_pulse_get_template( (int) $report['template_id'] );
	$fields   = $template ? site_pulse_get_template_fields( $template['id'] ) : [];
	$location = site_pulse_get_location( (int) $report['location_id'] );
	$author   = get_userdata( (int) $report['user_id'] );

	$previous_report = site_pulse_get_previous_report(
		(int) $report['template_id'],
		(int) $report['location_id'],
		(int) $report['id']
	);

	wp_send_json_success( [
		'report'          => $report,
		'answers'         => $answers,
		'template'        => $template,
		'fields'          => $fields,
		'location'        => $location,
		'author'          => $author ? [ 'id' => $author->ID, 'name' => $author->display_name ] : null,
		'previous_report' => $previous_report,
	] );
}

add_action( 'wp_ajax_site_pulse_get_dashboard', 'site_pulse_ajax_get_dashboard' );
function site_pulse_ajax_get_dashboard(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	$profile = site_pulse_get_user_profile( $user_id );
	$role    = $profile ? site_pulse_get_role( $profile['role_id'] ) : null;

	$data = [
		'user'      => [
			'id'       => $user_id,
			'name'     => get_userdata( $user_id )->display_name ?? '',
			'role'     => $role ? $role['label'] : '',
			'location' => $profile ? site_pulse_get_location( (int) $profile['location_id'] ) : null,
		],
		'locations' => site_pulse_get_all_locations(),
		'templates' => site_pulse_get_active_templates(),
	];

	wp_send_json_success( $data );
}

function site_pulse_get_active_templates(): array {
	global $wpdb;
	return $wpdb->get_results(
		"SELECT * FROM " . site_pulse_table('report_templates') . " WHERE is_active = 1 ORDER BY display_order",
		ARRAY_A
	) ?: [];
}


/*--------------------------------------------------------------
# Admin AJAX — Users
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_admin_get_users', 'site_pulse_ajax_admin_get_users' );
function site_pulse_ajax_admin_get_users(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_users' ) ) return;

	$users     = site_pulse_get_all_users( false, false );
	$roles     = site_pulse_get_all_roles( false );
	$locations = site_pulse_get_all_locations();

	global $wpdb;
	// Shared (non-private) approved locations for the Home Base picker. Private
	// work-from-home residences are set via the separate address field, not this list.
	$mileage_locations = $wpdb->get_results(
		"SELECT id, name, location_type FROM " . site_pulse_table('mileage_locations') . "
		 WHERE status = 'approved' AND is_private = 0 ORDER BY location_type, name",
		ARRAY_A
	) ?: [];

	wp_send_json_success( [ 'users' => $users, 'roles' => $roles, 'locations' => $locations, 'mileage_locations' => $mileage_locations ] );
}

add_action( 'wp_ajax_site_pulse_admin_create_user', 'site_pulse_ajax_admin_create_user' );
function site_pulse_ajax_admin_create_user(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_users' ) ) return;

	$result = site_pulse_create_user( [
		'username'                 => $_POST['username'] ?? '',
		'email'                    => $_POST['email'] ?? '',
		'password'                 => $_POST['user_pass'] ?? ( $_POST['password'] ?? '' ),
		'first_name'               => $_POST['first_name'] ?? '',
		'last_name'                => $_POST['last_name'] ?? '',
		'role_id'                  => (int) ( $_POST['role_id'] ?? 0 ),
		'location_id'              => (int) ( $_POST['location_id'] ?? 0 ),
		'supervisor_id'            => (int) ( $_POST['supervisor_id'] ?? 0 ),
		'mileage_home_location_id' => (int) ( $_POST['mileage_home_location_id'] ?? 0 ),
		'employee_id'              => $_POST['employee_id'] ?? '',
	] );

	if ( $result['success'] ) {
		// Work-from-home (no store location): set a private home as the mileage home base.
		if ( (int) ( $_POST['location_id'] ?? 0 ) === 0 && isset( $_POST['home_private_address'] ) && trim( $_POST['home_private_address'] ) !== '' ) {
			global $wpdb;
			$hid = site_pulse_mileage_set_private_home( (int) $result['user_id'], sanitize_text_field( $_POST['home_private_address'] ) );
			$wpdb->update( site_pulse_table('user_profiles'), [ 'mileage_home_location_id' => $hid ], [ 'user_id' => (int) $result['user_id'] ] );
		}
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( [ 'message' => $result['error'] ] );
	}
}

add_action( 'wp_ajax_site_pulse_admin_update_user', 'site_pulse_ajax_admin_update_user' );
function site_pulse_ajax_admin_update_user(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_users' ) ) return;

	global $wpdb;
	$user_id = (int) ( $_POST['user_id'] ?? 0 );
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Invalid user.' ] );
	}

	$profile = site_pulse_get_user_profile( $user_id );
	if ( ! $profile ) {
		wp_send_json_error( [ 'message' => 'User profile not found.' ] );
	}

	$updates = [];
	if ( isset( $_POST['role_id'] ) )       $updates['role_id']       = (int) $_POST['role_id'];

	// God membership is managed ONLY via the super-admin's Grant/Revoke actions — never through
	// this form. Drop any role change that would move a user into or out of God.
	if ( isset( $updates['role_id'] ) ) {
		$cur_role = site_pulse_get_role( (int) $profile['role_id'] );
		$new_role = site_pulse_get_role( (int) $updates['role_id'] );
		$cur_god  = $cur_role && $cur_role['slug'] === 'god';
		$new_god  = $new_role && $new_role['slug'] === 'god';
		if ( $cur_god || $new_god ) unset( $updates['role_id'] );
	}
	if ( isset( $_POST['location_id'] ) )   $updates['location_id']   = (int) $_POST['location_id'];
	if ( isset( $_POST['supervisor_id'] ) ) $updates['supervisor_id'] = (int) $_POST['supervisor_id'];
	// Home base is derived from the store Location when one is set. Only a work-from-home
	// user (no location) needs an explicit private home address.
	$posted_loc = isset( $_POST['location_id'] ) ? (int) $_POST['location_id'] : (int) $profile['location_id'];
	if ( $posted_loc === 0 && isset( $_POST['home_private_address'] ) && trim( $_POST['home_private_address'] ) !== '' ) {
		$updates['mileage_home_location_id'] = site_pulse_mileage_set_private_home( $user_id, sanitize_text_field( $_POST['home_private_address'] ) );
	}
	if ( isset( $_POST['employee_id'] ) )   $updates['employee_id']   = sanitize_text_field( $_POST['employee_id'] );
	if ( isset( $_POST['status'] ) )        $updates['status']        = sanitize_text_field( $_POST['status'] );
	$updates['updated_at'] = current_time( 'mysql' );

	$wpdb->update( site_pulse_table('user_profiles'), $updates, [ 'user_id' => $user_id ] );

	if ( isset( $_POST['first_name'] ) || isset( $_POST['last_name'] ) ) {
		$wp_updates = [ 'ID' => $user_id ];
		if ( isset( $_POST['first_name'] ) ) $wp_updates['first_name'] = sanitize_text_field( $_POST['first_name'] );
		if ( isset( $_POST['last_name'] ) )  $wp_updates['last_name']  = sanitize_text_field( $_POST['last_name'] );
		$wp_updates['display_name'] = trim( ( $_POST['first_name'] ?? '' ) . ' ' . ( $_POST['last_name'] ?? '' ) );
		wp_update_user( $wp_updates );
	}

	$new_pass = $_POST['new_user_pass'] ?? ( $_POST['new_password'] ?? '' );
	if ( ! empty( $new_pass ) ) {
		wp_set_password( $new_pass, $user_id );
	}

	site_pulse_log( 'user_updated', sprintf( 'Updated user #%d', $user_id ), [], $user_id );
	wp_send_json_success( [ 'message' => 'User updated.' ] );
}

add_action( 'wp_ajax_site_pulse_admin_create_profile', 'site_pulse_ajax_admin_create_profile' );
function site_pulse_ajax_admin_create_profile(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_users' ) ) return;

	global $wpdb;
	$user_id = (int) ( $_POST['user_id'] ?? 0 );
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Invalid user ID.' ] );
	}

	$existing = site_pulse_get_user_profile( $user_id );
	if ( $existing ) {
		wp_send_json_error( [ 'message' => 'Profile already exists for this user.' ] );
	}

	$now = current_time( 'mysql' );
	$wpdb->insert(
		site_pulse_table('user_profiles'),
		[
			'user_id'       => $user_id,
			'role_id'       => (int) ( $_POST['role_id'] ?? 0 ),
			'location_id'   => (int) ( $_POST['location_id'] ?? 0 ),
			'supervisor_id' => (int) ( $_POST['supervisor_id'] ?? 0 ),
			'employee_id'   => sanitize_text_field( $_POST['employee_id'] ?? '' ),
			'status'        => 'active',
			'created_at'    => $now,
			'updated_at'    => $now,
		],
		[ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
	);

	site_pulse_log( 'profile_created', sprintf( 'Created profile for user #%d', $user_id ), [], $user_id );
	wp_send_json_success( [ 'message' => 'Profile created.' ] );
}


/*--------------------------------------------------------------
# Admin AJAX — Locations
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_admin_get_locations', 'site_pulse_ajax_admin_get_locations' );
function site_pulse_ajax_admin_get_locations(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_locations' ) ) return;

	wp_send_json_success( [ 'locations' => site_pulse_get_all_locations( false ) ] );
}

add_action( 'wp_ajax_site_pulse_admin_save_location', 'site_pulse_ajax_admin_save_location' );
function site_pulse_ajax_admin_save_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_locations' ) ) return;

	global $wpdb;
	$id   = (int) ( $_POST['id'] ?? 0 );
	$now  = current_time( 'mysql' );
	$data = [
		'name'          => sanitize_text_field( $_POST['name'] ?? '' ),
		'location_type' => sanitize_text_field( $_POST['location_type'] ?? '' ),
		'address'       => sanitize_text_field( $_POST['address'] ?? '' ),
		'city'          => sanitize_text_field( $_POST['city'] ?? '' ),
		'state'         => sanitize_text_field( $_POST['state'] ?? '' ),
		'zip'           => sanitize_text_field( $_POST['zip'] ?? '' ),
		'phone'         => sanitize_text_field( $_POST['phone'] ?? '' ),
		'status'        => sanitize_text_field( $_POST['status'] ?? 'active' ),
		'display_order' => (int) ( $_POST['display_order'] ?? 0 ),
		'updated_at'    => $now,
	];

	if ( empty( $data['name'] ) ) {
		wp_send_json_error( [ 'message' => 'Location name is required.' ] );
	}

	if ( $id ) {
		$wpdb->update( site_pulse_table('locations'), $data, [ 'id' => $id ] );
		site_pulse_log( 'location_updated', sprintf( 'Updated location: %s', $data['name'] ) );
		wp_send_json_success( [ 'message' => 'Location updated.', 'id' => $id ] );
	} else {
		$data['created_at'] = $now;
		$wpdb->insert( site_pulse_table('locations'), $data );
		$new_id = (int) $wpdb->insert_id;
		site_pulse_log( 'location_created', sprintf( 'Created location: %s', $data['name'] ) );
		wp_send_json_success( [ 'message' => 'Location created.', 'id' => $new_id ] );
	}
}

add_action( 'wp_ajax_site_pulse_admin_delete_location', 'site_pulse_ajax_admin_delete_location' );
function site_pulse_ajax_admin_delete_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_locations' ) ) return;

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) {
		wp_send_json_error( [ 'message' => 'Invalid location.' ] );
	}

	$wpdb->update( site_pulse_table('locations'), [ 'status' => 'inactive' ], [ 'id' => $id ] );
	site_pulse_log( 'location_deactivated', sprintf( 'Deactivated location #%d', $id ) );
	wp_send_json_success( [ 'message' => 'Location deactivated.' ] );
}

/*--------------------------------------------------------------
# Admin AJAX — Report Templates
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_admin_get_templates', 'site_pulse_ajax_admin_get_templates' );
function site_pulse_ajax_admin_get_templates(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_templates' ) ) return;

	global $wpdb;
	$templates = $wpdb->get_results(
		"SELECT * FROM " . site_pulse_table('report_templates') . " ORDER BY display_order",
		ARRAY_A
	) ?: [];

	foreach ( $templates as &$t ) {
		$t['fields'] = site_pulse_get_template_fields( (int) $t['id'] );
	}
	unset( $t );

	// Roles (excluding God) so the template form's "Required Role" picker shows real labels.
	wp_send_json_success( [ 'templates' => $templates, 'roles' => site_pulse_get_all_roles() ] );
}

add_action( 'wp_ajax_site_pulse_admin_save_template', 'site_pulse_ajax_admin_save_template' );
function site_pulse_ajax_admin_save_template(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_templates' ) ) return;

	global $wpdb;
	$id   = (int) ( $_POST['id'] ?? 0 );
	$now  = current_time( 'mysql' );
	$data = [
		'name'               => sanitize_text_field( $_POST['name'] ?? '' ),
		'slug'               => sanitize_title( $_POST['slug'] ?? $_POST['name'] ?? '' ),
		'description'        => sanitize_text_field( $_POST['description'] ?? '' ),
		'frequency'          => sanitize_text_field( $_POST['frequency'] ?? 'weekly' ),
		'required_role_slug' => sanitize_text_field( $_POST['required_role_slug'] ?? 'manager' ),
		'is_active'          => (int) ( $_POST['is_active'] ?? 1 ),
		'updated_at'         => $now,
	];

	if ( empty( $data['name'] ) ) {
		wp_send_json_error( [ 'message' => 'Template name is required.' ] );
	}

	if ( $id ) {
		$wpdb->update( site_pulse_table('report_templates'), $data, [ 'id' => $id ] );
	} else {
		$data['display_order'] = (int) $wpdb->get_var( "SELECT COALESCE(MAX(display_order), -1) + 1 FROM " . site_pulse_table('report_templates') );
		$data['created_at'] = $now;
		$wpdb->insert( site_pulse_table('report_templates'), $data );
		$id = (int) $wpdb->insert_id;
	}

	site_pulse_log( 'template_saved', sprintf( 'Saved report template: %s', $data['name'] ) );
	wp_send_json_success( [ 'message' => 'Template saved.', 'id' => $id ] );
}

add_action( 'wp_ajax_site_pulse_admin_save_field', 'site_pulse_ajax_admin_save_field' );
function site_pulse_ajax_admin_save_field(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_templates' ) ) return;

	global $wpdb;
	$id          = (int) ( $_POST['id'] ?? 0 );
	$template_id = (int) ( $_POST['template_id'] ?? 0 );

	if ( ! $template_id ) {
		wp_send_json_error( [ 'message' => 'Template ID is required.' ] );
	}

	$data = [
		'template_id'   => $template_id,
		'field_key'     => sanitize_key( $_POST['field_key'] ?? '' ),
		'label'         => sanitize_text_field( $_POST['label'] ?? '' ),
		'field_type'    => sanitize_text_field( $_POST['field_type'] ?? 'textarea' ),
		'options'       => ! empty( $_POST['options'] ) ? sanitize_text_field( $_POST['options'] ) : null,
		'placeholder'   => sanitize_text_field( $_POST['placeholder'] ?? '' ),
		'is_required'   => (int) ( $_POST['is_required'] ?? 0 ),
		'section'       => sanitize_text_field( $_POST['section'] ?? '' ),
		'help_text'     => sanitize_text_field( $_POST['help_text'] ?? '' ),
	];

	if ( empty( $data['label'] ) ) {
		wp_send_json_error( [ 'message' => 'Field label is required.' ] );
	}

	if ( empty( $data['field_key'] ) ) {
		$data['field_key'] = sanitize_key( str_replace( ' ', '_', strtolower( $data['label'] ) ) );
	}

	if ( $id ) {
		$wpdb->update( site_pulse_table('report_fields'), $data, [ 'id' => $id ] );
	} else {
		$data['display_order'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(display_order), -1) + 1 FROM " . site_pulse_table('report_fields') . " WHERE template_id = %d",
			$template_id
		) );
		$wpdb->insert( site_pulse_table('report_fields'), $data );
		$id = (int) $wpdb->insert_id;
	}

	wp_send_json_success( [ 'message' => 'Field saved.', 'id' => $id ] );
}

add_action( 'wp_ajax_site_pulse_admin_delete_field', 'site_pulse_ajax_admin_delete_field' );
function site_pulse_ajax_admin_delete_field(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_templates' ) ) return;

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) {
		wp_send_json_error( [ 'message' => 'Invalid field.' ] );
	}

	$has_answers = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . site_pulse_table('report_answers') . " WHERE field_id = %d", $id
	) );

	if ( $has_answers ) {
		$wpdb->update( site_pulse_table('report_fields'), [ 'is_required' => 0, 'display_order' => 999 ], [ 'id' => $id ] );
		wp_send_json_success( [ 'message' => 'Field archived (has existing answers).' ] );
	} else {
		$wpdb->delete( site_pulse_table('report_fields'), [ 'id' => $id ] );
		wp_send_json_success( [ 'message' => 'Field deleted.' ] );
	}
}

add_action( 'wp_ajax_site_pulse_admin_reorder_fields', 'site_pulse_ajax_admin_reorder_fields' );
function site_pulse_ajax_admin_reorder_fields(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_templates' ) ) return;

	global $wpdb;
	$order = $_POST['order'] ?? [];
	if ( ! is_array( $order ) ) {
		wp_send_json_error( [ 'message' => 'Invalid order data.' ] );
	}

	foreach ( $order as $position => $field_id ) {
		$wpdb->update(
			site_pulse_table('report_fields'),
			[ 'display_order' => (int) $position ],
			[ 'id' => (int) $field_id ]
		);
	}

	wp_send_json_success( [ 'message' => 'Fields reordered.' ] );
}

add_action( 'wp_ajax_site_pulse_get_template_fields', 'site_pulse_ajax_get_template_fields' );
function site_pulse_ajax_get_template_fields(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$template_id = (int) ( $_POST['template_id'] ?? 0 );
	if ( ! $template_id ) {
		wp_send_json_error( [ 'message' => 'Template ID required.' ] );
	}

	$user_id     = site_pulse_effective_user_id();
	$profile     = site_pulse_get_user_profile( $user_id );
	$location_id = $profile ? (int) $profile['location_id'] : 0;

	$fields          = site_pulse_get_template_fields( $template_id );
	$previous_report = site_pulse_get_previous_report( $template_id, $location_id );

	wp_send_json_success( [ 'fields' => $fields, 'previous_report' => $previous_report ] );
}


/*--------------------------------------------------------------
# Admin AJAX — Tiers (Roles)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_admin_get_roles', 'site_pulse_ajax_admin_get_roles' );
function site_pulse_ajax_admin_get_roles(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	global $wpdb;
	$counts = [];
	$rows = $wpdb->get_results(
		"SELECT role_id, COUNT(*) AS n FROM " . site_pulse_table('user_profiles') . " GROUP BY role_id",
		ARRAY_A
	) ?: [];
	foreach ( $rows as $r ) $counts[ (int) $r['role_id'] ] = (int) $r['n'];

	wp_send_json_success( [
		'roles'       => site_pulse_get_all_roles(),          // excludes the hidden God tier
		'catalog'     => site_pulse_capability_catalog(),
		'user_counts' => $counts,
	] );
}

add_action( 'wp_ajax_site_pulse_admin_save_role', 'site_pulse_ajax_admin_save_role' );
function site_pulse_ajax_admin_save_role(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	global $wpdb;
	$id    = (int) ( $_POST['id'] ?? 0 );
	$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
	if ( $label === '' ) {
		wp_send_json_error( [ 'message' => 'Tier name is required.' ] );
	}

	// Only ever store known capabilities; silently drop anything not in the catalog.
	$posted = (array) ( $_POST['capabilities'] ?? [] );
	$caps   = array_values( array_intersect( array_map( 'strval', $posted ), array_keys( site_pulse_capability_catalog() ) ) );

	if ( $id ) {
		$role = site_pulse_get_role( $id );
		if ( ! $role || $role['slug'] === 'god' ) {
			wp_send_json_error( [ 'message' => 'That tier cannot be edited.' ] );
		}
		// Label + capabilities only — slug and rank are stable identity, untouched.
		$wpdb->update( site_pulse_table('roles'),
			[ 'label' => $label, 'capabilities' => wp_json_encode( $caps ) ],
			[ 'id' => $id ]
		);
		site_pulse_log( 'tier_updated', sprintf( 'Updated tier "%s" (#%d)', $label, $id ) );
		wp_send_json_success( [ 'message' => 'Tier saved.' ] );
	}

	// Create — derive a unique, stable slug from the name; never reuse the reserved 'god'.
	$base = sanitize_title( $label ) ?: 'tier';
	$slug = $base;
	$i    = 2;
	while ( $slug === 'god' || site_pulse_get_role_by_slug( $slug ) ) {
		$slug = $base . '-' . $i++;
	}

	$min = (int) $wpdb->get_var(
		"SELECT MIN(hierarchy_level) FROM " . site_pulse_table('roles') . " WHERE slug != 'god'"
	);
	$new_level = max( 1, $min - 10 ); // appended at the bottom (least senior)

	$wpdb->insert( site_pulse_table('roles'), [
		'slug'            => $slug,
		'label'           => $label,
		'capabilities'    => wp_json_encode( $caps ),
		'hierarchy_level' => $new_level,
		'is_active'       => 1,
	] );
	site_pulse_log( 'tier_created', sprintf( 'Created tier "%s" (%s)', $label, $slug ) );
	wp_send_json_success( [ 'message' => 'Tier created.', 'id' => (int) $wpdb->insert_id ] );
}

add_action( 'wp_ajax_site_pulse_admin_delete_role', 'site_pulse_ajax_admin_delete_role' );
function site_pulse_ajax_admin_delete_role(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	global $wpdb;
	$id   = (int) ( $_POST['id'] ?? 0 );
	$role = $id ? site_pulse_get_role( $id ) : null;
	if ( ! $role || $role['slug'] === 'god' ) {
		wp_send_json_error( [ 'message' => 'That tier cannot be deleted.' ] );
	}

	$members = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . site_pulse_table('user_profiles') . " WHERE role_id = %d", $id
	) );
	if ( $members ) {
		wp_send_json_error( [ 'message' => sprintf( 'Reassign this tier\'s %d member%s to another tier first.', $members, $members === 1 ? '' : 's' ) ] );
	}

	$wpdb->delete( site_pulse_table('roles'), [ 'id' => $id ] );
	site_pulse_log( 'tier_deleted', sprintf( 'Deleted tier "%s" (%s)', $role['label'], $role['slug'] ) );
	wp_send_json_success( [ 'message' => 'Tier deleted.' ] );
}

add_action( 'wp_ajax_site_pulse_admin_reorder_roles', 'site_pulse_ajax_admin_reorder_roles' );
function site_pulse_ajax_admin_reorder_roles(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	global $wpdb;
	$order = $_POST['order'] ?? []; // role IDs, most-senior first (God excluded)
	if ( ! is_array( $order ) || ! $order ) {
		wp_send_json_error( [ 'message' => 'Invalid order data.' ] );
	}

	// Even gaps below God's fixed 255 — tie-free and leaves room to insert between later.
	$n = count( $order );
	foreach ( $order as $i => $role_id ) {
		$role = site_pulse_get_role( (int) $role_id );
		if ( ! $role || $role['slug'] === 'god' ) continue;
		$wpdb->update( site_pulse_table('roles'),
			[ 'hierarchy_level' => ( $n - (int) $i ) * 10 ],
			[ 'id' => (int) $role_id ]
		);
	}
	wp_send_json_success( [ 'message' => 'Tiers reordered.' ] );
}


/*--------------------------------------------------------------
# Review Filters AJAX
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_review_filters', 'site_pulse_ajax_get_review_filters' );
function site_pulse_ajax_get_review_filters(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	$locations = site_pulse_get_all_locations();

	// Submitters the viewer is allowed to filter by (GMs and/or supervisors, per their caps).
	$users = [];
	foreach ( site_pulse_visible_report_user_ids( $user_id ) as $tid ) {
		if ( (int) $tid === (int) $user_id ) continue; // self isn't a "filter by" option
		$u = get_userdata( $tid );
		if ( $u ) $users[] = [ 'user_id' => $tid, 'display_name' => $u->display_name ];
	}

	wp_send_json_success( [ 'locations' => $locations, 'users' => $users ] );
}


/*--------------------------------------------------------------
# Notifications AJAX
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_unread_count', 'site_pulse_ajax_get_unread_count' );
function site_pulse_ajax_get_unread_count(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	global $wpdb;
	$count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . site_pulse_table('notifications') . " WHERE user_id = %d AND is_read = 0 AND is_archived = 0",
		$user_id
	) );
	wp_send_json_success( [ 'count' => $count ] );
}

add_action( 'wp_ajax_site_pulse_get_notifications', 'site_pulse_ajax_get_notifications' );
function site_pulse_ajax_get_notifications(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	global $wpdb;
	$notifications = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('notifications') . " WHERE user_id = %d AND is_archived = 0 ORDER BY created_at DESC LIMIT 50",
		$user_id
	), ARRAY_A ) ?: [];
	wp_send_json_success( [ 'notifications' => $notifications ] );
}

add_action( 'wp_ajax_site_pulse_mark_notification_read', 'site_pulse_ajax_mark_notification_read' );
function site_pulse_ajax_mark_notification_read(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( $id ) {
		global $wpdb;
		$wpdb->update( site_pulse_table('notifications'), [ 'is_read' => 1 ], [ 'id' => $id ] );
	}
	wp_send_json_success();
}

add_action( 'wp_ajax_site_pulse_mark_notifications_read', 'site_pulse_ajax_mark_notifications_read' );
function site_pulse_ajax_mark_notifications_read(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	global $wpdb;
	$wpdb->update(
		site_pulse_table('notifications'),
		[ 'is_read' => 1 ],
		[ 'user_id' => $user_id, 'is_read' => 0 ]
	);
	wp_send_json_success();
}

function site_pulse_notify( int $user_id, string $type, string $message, int $related_id = 0, string $related_type = '' ): void {
	global $wpdb;
	$wpdb->insert( site_pulse_table('notifications'), [
		'user_id'      => $user_id,
		'type'         => $type,
		'message'      => $message,
		'related_id'   => $related_id,
		'related_type' => $related_type,
		'is_read'      => 0,
		'is_archived'  => 0,
		'created_at'   => current_time( 'mysql' ),
	] );
}


/*--------------------------------------------------------------
# AI — Claude API
--------------------------------------------------------------*/

function site_pulse_get_api_key(): string {
	$key = site_pulse_get_setting( 'claude_api_key', '' );
	return $key ? base64_decode( $key ) : '';
}

function site_pulse_set_api_key( string $key ): void {
	site_pulse_set_setting( 'claude_api_key', base64_encode( $key ) );
}

function site_pulse_call_claude( string $prompt, string $system = '' ): ?string {
	$api_key = site_pulse_get_api_key();
	if ( ! $api_key ) return null;

	$messages = [ [ 'role' => 'user', 'content' => $prompt ] ];

	$body = [
		'model'      => 'claude-sonnet-4-20250514',
		'max_tokens' => 2048,
		'messages'   => $messages,
	];

	if ( $system ) {
		$body['system'] = $system;
	}

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		],
		'body'    => wp_json_encode( $body ),
		'timeout' => 30,
	] );

	if ( is_wp_error( $response ) ) {
		site_pulse_log( 'ai_error', 'Claude API error: ' . $response->get_error_message() );
		return null;
	}

	$status = wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $status !== 200 || empty( $data['content'][0]['text'] ) ) {
		site_pulse_log( 'ai_error', 'Claude API returned status ' . $status, [ 'response' => $data ] );
		return null;
	}

	return $data['content'][0]['text'];
}


/*--------------------------------------------------------------
# AI — Action Items from Report
--------------------------------------------------------------*/

function site_pulse_generate_action_items( int $report_id ): array {
	$report  = site_pulse_get_report( $report_id );
	if ( ! $report ) return [];

	$answers  = site_pulse_get_report_answers( $report_id );
	$template = site_pulse_get_template( (int) $report['template_id'] );
	$fields   = $template ? site_pulse_get_template_fields( $template['id'] ) : [];
	$location = site_pulse_get_location( (int) $report['location_id'] );
	$user     = get_userdata( $report['user_id'] );

	$field_map = [];
	foreach ( $fields as $f ) $field_map[ $f['field_key'] ] = $f['label'];

	$report_text = "Manager Report\n";
	$report_text .= "Location: " . ( $location ? $location['name'] : 'Unknown' ) . "\n";
	$report_text .= "Manager: " . ( $user ? $user->display_name : 'Unknown' ) . "\n";
	$report_text .= "Date: " . $report['report_period_start'] . "\n\n";

	foreach ( $answers as $a ) {
		$label = $field_map[ $a['field_key'] ] ?? $a['field_key'];
		$text  = $a['answer_text'] ?? '';
		if ( trim( $text ) ) {
			$report_text .= "## {$label}\n{$text}\n\n";
		}
	}

	// Include unresolved items from previous reports
	$open_items = site_pulse_get_action_items( [
		'user_id' => $report['user_id'],
		'status'  => 'open',
	] );

	if ( $open_items ) {
		$report_text .= "\n## Previously Unresolved Action Items\n";
		foreach ( $open_items as $item ) {
			$report_text .= "- [{$item['category']}] {$item['description']} (from " . formatDate( $item['created_at'] ) . ")\n";
		}
	}

	$system = site_pulse_prompt_extract_action_items();

	$result = site_pulse_call_claude( $report_text, $system );
	if ( ! $result ) return [];

	$result = trim( $result );
	if ( strpos( $result, '```' ) !== false ) {
		$result = preg_replace( '/```(?:json)?\s*/', '', $result );
		$result = preg_replace( '/```\s*$/', '', $result );
	}

	$items = json_decode( $result, true );
	if ( ! is_array( $items ) ) {
		site_pulse_log( 'ai_error', 'Failed to parse action items JSON', [ 'raw' => $result ] );
		return [];
	}

	return $items;
}

function site_pulse_create_action_items_from_report( int $report_id ): int {
	$report = site_pulse_get_report( $report_id );
	if ( ! $report ) return 0;

	$items = site_pulse_generate_action_items( $report_id );
	if ( empty( $items ) ) return 0;

	global $wpdb;
	$now       = current_time( 'mysql' );
	$due_date  = date( 'Y-m-d', strtotime( '+14 days' ) );
	$count     = 0;
	$high_items = [];

	foreach ( $items as $item ) {
		if ( empty( $item['description'] ) ) continue;

		$priority = in_array( $item['priority'] ?? '', [ 'high', 'medium', 'low' ] ) ? $item['priority'] : 'medium';

		$wpdb->insert( site_pulse_table('action_items'), [
			'report_id'   => $report_id,
			'user_id'     => (int) $report['user_id'],
			'location_id' => (int) $report['location_id'],
			'category'    => sanitize_text_field( $item['category'] ?? '' ),
			'description' => sanitize_text_field( $item['description'] ),
			'priority'    => $priority,
			'status'      => 'pending',
			'due_date'    => $due_date,
			'created_at'  => $now,
			'updated_at'  => $now,
		] );
		$count++;
	}

	if ( $count ) {
		$loc = site_pulse_get_location( $report['location_id'] );
		$msg = sprintf( '%d action item%s need your review (%s)',
			$count,
			$count > 1 ? 's' : '',
			$loc ? $loc['name'] : 'Unknown'
		);

		site_pulse_notify( (int) $report['user_id'], 'action_pending', $msg, $report_id, 'action_item' );
		site_pulse_log( 'action_items_pending', $msg, [ 'report_id' => $report_id, 'count' => $count ] );
	}

	return $count;
}


/*--------------------------------------------------------------
# Action Items — Queries & AJAX
--------------------------------------------------------------*/

function site_pulse_get_action_items( array $args = [] ): array {
	global $wpdb;
	$where  = [ "1=1" ];
	$values = [];

	if ( ! empty( $args['user_id'] ) ) {
		$where[]  = "ai.user_id = %d";
		$values[] = (int) $args['user_id'];
	}
	if ( ! empty( $args['user_ids'] ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $args['user_ids'] ), '%d' ) );
		$where[]      = "ai.user_id IN ($placeholders)";
		$values       = array_merge( $values, array_map( 'intval', $args['user_ids'] ) );
	}
	if ( ! empty( $args['location_id'] ) ) {
		$where[]  = "ai.location_id = %d";
		$values[] = (int) $args['location_id'];
	}
	if ( ! empty( $args['report_id'] ) ) {
		$where[]  = "ai.report_id = %d";
		$values[] = (int) $args['report_id'];
	}
	if ( ! empty( $args['status'] ) ) {
		$where[]  = "ai.status = %s";
		$values[] = $args['status'];
	} else {
		$where[] = "ai.status != 'pending'";
	}

	$sql = "SELECT ai.*, l.name AS location_name, u.display_name AS user_name
			FROM " . site_pulse_table('action_items') . " ai
			LEFT JOIN " . site_pulse_table('locations') . " l ON l.id = ai.location_id
			LEFT JOIN {$wpdb->users} u ON u.ID = ai.user_id
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY ai.display_order ASC, FIELD(ai.priority, 'high', 'medium', 'low'), ai.due_date ASC";

	if ( $values ) {
		$sql = $wpdb->prepare( $sql, ...$values );
	}

	return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
}

add_action( 'wp_ajax_site_pulse_get_action_items', 'site_pulse_ajax_get_action_items' );
function site_pulse_ajax_get_action_items(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	$args = [];

	$visible = site_pulse_visible_report_user_ids( $user_id );
	if ( ! empty( $_POST['user_id'] ) && in_array( (int) $_POST['user_id'], $visible, true ) ) {
		$args['user_id'] = (int) $_POST['user_id'];
	} else {
		$args['user_ids'] = $visible;
	}

	if ( ! empty( $_POST['location_id'] ) ) $args['location_id'] = (int) $_POST['location_id'];
	if ( ! empty( $_POST['status'] ) )      $args['status']      = sanitize_text_field( $_POST['status'] );

	$pending = site_pulse_get_action_items( [ 'user_id' => $user_id, 'status' => 'pending' ] );

	wp_send_json_success( [
		'items'   => site_pulse_get_action_items( $args ),
		'pending' => $pending,
	] );
}

add_action( 'wp_ajax_site_pulse_resolve_action_item', 'site_pulse_ajax_resolve_action_item' );
function site_pulse_ajax_resolve_action_item(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	$item_id = (int) ( $_POST['item_id'] ?? 0 );
	$note    = sanitize_textarea_field( $_POST['note'] ?? '' );

	if ( ! $item_id ) {
		wp_send_json_error( [ 'message' => 'Invalid item.' ] );
	}

	if ( empty( $note ) ) {
		wp_send_json_error( [ 'message' => 'Please describe what you did to resolve this item.' ] );
	}

	global $wpdb;
	$item = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('action_items') . " WHERE id = %d", $item_id
	), ARRAY_A );

	if ( ! $item ) {
		wp_send_json_error( [ 'message' => 'Item not found.' ] );
	}

	$now     = current_time( 'mysql' );
	$user    = get_userdata( $user_id );
	$profile = site_pulse_get_user_profile( (int) $item['user_id'] );

	// Ask Claude to evaluate the resolution
	$ai_verdict = site_pulse_evaluate_resolution( $item, $note );

	if ( $ai_verdict && $ai_verdict['resolved'] === false ) {
		// Not truly resolved — save the attempt and create follow-up
		$wpdb->update( site_pulse_table('action_items'), [
			'resolution_note' => $note,
			'status'          => 'resolved',
			'resolved_at'     => $now,
			'resolved_by'     => $user_id,
			'updated_at'      => $now,
		], [ 'id' => $item_id ] );

		$follow_up = sanitize_text_field( $ai_verdict['follow_up'] ?? $item['description'] );

		// Build history chain — carry forward any previous history
		$prev_history = ! empty( $item['meta'] ) ? json_decode( $item['meta'], true ) : [];
		if ( ! is_array( $prev_history ) ) $prev_history = [];
		$history = $prev_history;
		$history[] = [
			'original'    => $item['description'],
			'response'    => $note,
			'ai_reason'   => $ai_verdict['reason'] ?? '',
			'date'        => $now,
		];

		$wpdb->insert( site_pulse_table('action_items'), [
			'report_id'     => (int) $item['report_id'],
			'user_id'       => (int) $item['user_id'],
			'location_id'   => (int) $item['location_id'],
			'category'      => $item['category'],
			'description'   => $follow_up,
			'priority'      => $item['priority'],
			'status'        => 'open',
			'due_date'      => date( 'Y-m-d', strtotime( '+7 days' ) ),
			'meta'          => wp_json_encode( $history ),
			'display_order' => 0,
			'created_at'    => $now,
			'updated_at'    => $now,
		] );

		$msg = sprintf( 'Action item needs follow-up: %s', $follow_up );
		site_pulse_notify( (int) $item['user_id'], 'action_followup', $msg, $item_id, 'action_item' );

		if ( $profile && $profile['supervisor_id'] ) {
			site_pulse_notify( (int) $profile['supervisor_id'], 'action_followup', $msg, $item_id, 'action_item' );
		}

		site_pulse_log( 'action_item_followup', $msg, [ 'item_id' => $item_id, 'ai_reason' => $ai_verdict['reason'] ?? '' ] );

		wp_send_json_success( [
			'resolved'  => false,
			'message'   => 'This doesn\'t fully resolve the issue. A follow-up item has been created.',
			'follow_up' => $follow_up,
			'reason'    => $ai_verdict['reason'] ?? '',
		] );
		return;
	}

	// Truly resolved
	$wpdb->update( site_pulse_table('action_items'), [
		'status'          => 'resolved',
		'resolved_at'     => $now,
		'resolved_by'     => $user_id,
		'resolution_note' => $note,
		'updated_at'      => $now,
	], [ 'id' => $item_id ] );

	$msg = sprintf( '%s resolved action item: %s', $user ? $user->display_name : 'Unknown', $item['description'] );

	site_pulse_log( 'action_item_resolved', $msg, [ 'item_id' => $item_id ] );

	if ( $profile && $profile['supervisor_id'] ) {
		site_pulse_notify( (int) $profile['supervisor_id'], 'action_resolved', $msg, $item_id, 'action_item' );
	}

	wp_send_json_success( [ 'resolved' => true, 'message' => 'Item resolved.' ] );
}

function site_pulse_evaluate_resolution( array $item, string $note ): ?array {
	$system = site_pulse_prompt_evaluate_resolution();

	$prompt = '';

	// Include history for context
	$history = ! empty( $item['meta'] ) ? json_decode( $item['meta'], true ) : [];
	if ( is_array( $history ) && ! empty( $history ) ) {
		$prompt .= "Previous History:\n";
		foreach ( $history as $i => $h ) {
			$prompt .= "--- Attempt " . ( $i + 1 ) . " ---\n";
			$prompt .= "Original Item: {$h['original']}\n";
			$prompt .= "Manager's Response: {$h['response']}\n";
			if ( ! empty( $h['ai_reason'] ) ) {
				$prompt .= "Why it wasn't resolved: {$h['ai_reason']}\n";
			}
			$prompt .= "\n";
		}
	}

	$prompt .= "Current Action Item: {$item['description']}\n";
	$prompt .= "Category: {$item['category']}\n\n";
	$prompt .= "Manager's Resolution: {$note}";

	$result = site_pulse_call_claude( $prompt, $system );
	if ( ! $result ) return null;

	$result = trim( $result );
	if ( strpos( $result, '```' ) !== false ) {
		$result = preg_replace( '/```(?:json)?\s*/', '', $result );
		$result = preg_replace( '/```\s*$/', '', $result );
	}

	$data = json_decode( $result, true );
	if ( ! is_array( $data ) || ! isset( $data['resolved'] ) ) {
		site_pulse_log( 'ai_error', 'Failed to parse resolution evaluation', [ 'raw' => $result ] );
		return null;
	}

	return $data;
}

add_action( 'wp_ajax_site_pulse_review_action_item', 'site_pulse_ajax_review_action_item' );
function site_pulse_ajax_review_action_item(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id  = site_pulse_effective_user_id();
	$item_id  = (int) ( $_POST['item_id'] ?? 0 );
	$decision = sanitize_text_field( $_POST['decision'] ?? '' );

	if ( ! $item_id || ! in_array( $decision, [ 'approve', 'reject' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid request.' ] );
	}

	global $wpdb;
	$item = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('action_items') . " WHERE id = %d", $item_id
	), ARRAY_A );

	if ( ! $item ) {
		wp_send_json_error( [ 'message' => 'Item not found.' ] );
	}

	if ( (int) $item['user_id'] !== $user_id && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'You can only review your own action items.' ] );
	}

	if ( $item['status'] !== 'pending' ) {
		wp_send_json_error( [ 'message' => 'This item has already been reviewed.' ] );
	}

	if ( $decision === 'reject' ) {
		$wpdb->delete( site_pulse_table('action_items'), [ 'id' => $item_id ] );
		site_pulse_log( 'action_item_rejected',
			sprintf( 'Rejected action item: %s', $item['description'] ),
			[ 'item_id' => $item_id, 'priority' => $item['priority'] ]
		);
		wp_send_json_success( [ 'decision' => 'reject' ] );
	}

	// approve
	$now = current_time( 'mysql' );
	$wpdb->update(
		site_pulse_table('action_items'),
		[ 'status' => 'open', 'updated_at' => $now ],
		[ 'id' => $item_id ]
	);

	site_pulse_log( 'action_item_approved',
		sprintf( 'Approved action item: %s', $item['description'] ),
		[ 'item_id' => $item_id, 'priority' => $item['priority'] ]
	);

	if ( $item['priority'] === 'high' ) {
		$profile = site_pulse_get_user_profile( (int) $item['user_id'] );
		$user    = get_userdata( (int) $item['user_id'] );
		$loc     = site_pulse_get_location( (int) $item['location_id'] );

		$urgent_msg = sprintf( 'URGENT — high-priority item from %s (%s): %s',
			$user ? $user->display_name : 'Unknown',
			$loc ? $loc['name'] : 'Unknown',
			$item['description']
		);

		if ( $profile && $profile['supervisor_id'] ) {
			site_pulse_notify( (int) $profile['supervisor_id'], 'action_urgent', $urgent_msg, $item_id, 'action_item' );
		}
	}

	wp_send_json_success( [ 'decision' => 'approve' ] );
}

add_action( 'wp_ajax_site_pulse_reorder_action_items', 'site_pulse_ajax_reorder_action_items' );
function site_pulse_ajax_reorder_action_items(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$order = $_POST['order'] ?? [];
	if ( ! is_array( $order ) ) {
		wp_send_json_error( [ 'message' => 'Invalid order data.' ] );
	}

	global $wpdb;
	foreach ( $order as $position => $item_id ) {
		$wpdb->update(
			site_pulse_table('action_items'),
			[ 'display_order' => (int) $position, 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => (int) $item_id ]
		);
	}

	wp_send_json_success( [ 'message' => 'Items reordered.' ] );
}

add_action( 'wp_ajax_site_pulse_admin_save_setting', 'site_pulse_ajax_admin_save_setting' );
function site_pulse_ajax_admin_save_setting(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	$key   = sanitize_key( $_POST['key'] ?? '' );
	$value = $_POST['value'] ?? '';

	if ( ! $key ) {
		wp_send_json_error( [ 'message' => 'Setting key is required.' ] );
	}

	if ( $key === 'claude_api_key' ) {
		site_pulse_set_api_key( $value );
	} else {
		site_pulse_set_setting( $key, sanitize_text_field( $value ) );
	}

	wp_send_json_success( [ 'message' => 'Setting saved.' ] );
}

add_action( 'wp_ajax_site_pulse_admin_get_settings', 'site_pulse_ajax_admin_get_settings' );
function site_pulse_ajax_admin_get_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	$api_key = site_pulse_get_api_key();
	$masked  = $api_key ? substr( $api_key, 0, 8 ) . '...' . substr( $api_key, -4 ) : '';

	// Server-side key (IP-restricted): geocoding + routes/distance matrix.
	$google_key    = (string) site_pulse_get_setting( 'google_api_key', '' );
	$google_masked = $google_key ? substr( $google_key, 0, 8 ) . '...' . substr( $google_key, -4 ) : '';

	// Browser key (website/referrer-restricted): Maps JavaScript + Places autocomplete.
	$maps_key    = (string) site_pulse_get_setting( 'maps_js_api_key', '' );
	$maps_masked = $maps_key ? substr( $maps_key, 0, 8 ) . '...' . substr( $maps_key, -4 ) : '';

	$toll_key    = (string) site_pulse_get_setting( 'tollguru_api_key', '' );
	$toll_masked = $toll_key ? substr( $toll_key, 0, 6 ) . '...' . substr( $toll_key, -4 ) : '';

	wp_send_json_success( [
		'claude_api_key_masked'    => $masked,
		'claude_api_key_set'       => ! empty( $api_key ),
		'google_api_key_masked'    => $google_masked,
		'google_api_key_set'       => ! empty( $google_key ),
		'maps_js_api_key_masked'   => $maps_masked,
		'maps_js_api_key_set'      => ! empty( $maps_key ),
		'tollguru_api_key_masked'  => $toll_masked,
		'tollguru_api_key_set'     => ! empty( $toll_key ),
	] );
}


/*--------------------------------------------------------------
# Analytics AJAX
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_analytics', 'site_pulse_ajax_get_analytics' );
function site_pulse_ajax_get_analytics(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	if ( ! site_pulse_user_can( $user_id, 'view_analytics' ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	global $wpdb;

	$loc_id    = (int) ( $_POST['location_id'] ?? 0 );
	$loc_where = $loc_id ? $wpdb->prepare( " AND location_id = %d", $loc_id ) : '';
	$loc_where_r = $loc_id ? $wpdb->prepare( " AND r.location_id = %d", $loc_id ) : '';

	// Action items by priority
	$priority_counts = $wpdb->get_results(
		"SELECT priority, COUNT(*) AS count FROM " . site_pulse_table('action_items') . " WHERE status = 'open'" . $loc_where . " GROUP BY priority ORDER BY FIELD(priority, 'high', 'medium', 'low')",
		ARRAY_A
	) ?: [];

	// Action items by category
	$category_counts = $wpdb->get_results(
		"SELECT category, COUNT(*) AS count FROM " . site_pulse_table('action_items') . " WHERE status = 'open' AND category != ''" . $loc_where . " GROUP BY category ORDER BY count DESC LIMIT 10",
		ARRAY_A
	) ?: [];

	// Reports by location
	$location_counts = $wpdb->get_results(
		"SELECT l.name, COUNT(r.id) AS count FROM " . site_pulse_table('reports') . " r LEFT JOIN " . site_pulse_table('locations') . " l ON l.id = r.location_id WHERE r.status = 'submitted'" . $loc_where_r . " GROUP BY r.location_id ORDER BY count DESC",
		ARRAY_A
	) ?: [];

	// Resolution rate
	$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . site_pulse_table('action_items') . " WHERE 1=1" . $loc_where );
	$resolved_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . site_pulse_table('action_items') . " WHERE status = 'resolved'" . $loc_where );
	$open_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . site_pulse_table('action_items') . " WHERE status = 'open'" . $loc_where );

	// Avg resolution time (days)
	$avg_resolution = $wpdb->get_var(
		"SELECT AVG(DATEDIFF(resolved_at, created_at)) FROM " . site_pulse_table('action_items') . " WHERE status = 'resolved' AND resolved_at IS NOT NULL" . $loc_where
	);

	// --- Mileage analytics (MTD + YTD totals, top destinations) ---
	$today       = current_time( 'Y-m-d' );
	$month_start = substr( $today, 0, 7 ) . '-01';
	$year_start  = substr( $today, 0, 4 ) . '-01-01';
	$me  = site_pulse_table('mileage_entries');
	$ml  = site_pulse_table('mileage_locations');
	$mlg = site_pulse_table('mileage_legs');
	// When a location filter is set, scope to drivers based at that location.
	$m_where = $loc_id
		? $wpdb->prepare( " AND e.user_id IN (SELECT user_id FROM " . site_pulse_table('user_profiles') . " WHERE location_id = %d)", $loc_id )
		: '';

	$mtd = $wpdb->get_row( $wpdb->prepare(
		"SELECT COALESCE(SUM(total_miles),0) miles, COALESCE(SUM(reimbursement_amount),0) reimb, COALESCE(SUM(total_tolls),0) tolls, COALESCE(SUM(total_trailer),0) trailer, COUNT(*) entries, COUNT(DISTINCT user_id) drivers
		 FROM $me e WHERE entry_date >= %s AND entry_date <= %s" . $m_where,
		$month_start, $today
	), ARRAY_A );
	$ytd = $wpdb->get_row( $wpdb->prepare(
		"SELECT COALESCE(SUM(total_miles),0) miles, COALESCE(SUM(reimbursement_amount),0) reimb, COALESCE(SUM(total_tolls),0) tolls, COALESCE(SUM(total_trailer),0) trailer
		 FROM $me e WHERE entry_date >= %s AND entry_date <= %s" . $m_where,
		$year_start, $today
	), ARRAY_A );
	$top_dest = $wpdb->get_results( $wpdb->prepare(
		"SELECT ml.name, COUNT(*) count
		 FROM $mlg l
		 INNER JOIN $me e ON e.id = l.entry_id
		 INNER JOIN $ml ml ON ml.id = l.to_location_id
		 WHERE e.entry_date >= %s AND e.entry_date <= %s AND ml.is_private = 0" . $m_where . "
		 GROUP BY l.to_location_id ORDER BY count DESC LIMIT 6",
		$year_start, $today
	), ARRAY_A ) ?: [];

	wp_send_json_success( [
		'priority'    => $priority_counts,
		'categories'  => $category_counts,
		'locations'   => $location_counts,
		'resolution'  => [
			'total'          => $total_items,
			'resolved'       => $resolved_items,
			'open'           => $open_items,
			'rate'           => $total_items > 0 ? round( ( $resolved_items / $total_items ) * 100 ) : 0,
			'avg_days'       => $avg_resolution !== null ? round( (float) $avg_resolution, 1 ) : null,
		],
		'mileage'     => [
			'month'            => $mtd ?: [ 'miles' => 0, 'reimb' => 0, 'tolls' => 0, 'trailer' => 0, 'entries' => 0, 'drivers' => 0 ],
			'ytd'              => $ytd ?: [ 'miles' => 0, 'reimb' => 0, 'tolls' => 0, 'trailer' => 0 ],
			'top_destinations' => $top_dest,
			'rate'             => site_pulse_mileage_rate(),
		],
	] );
}


/*--------------------------------------------------------------
# God Mode — Nuclear Reset
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_god_nuke', 'site_pulse_ajax_god_nuke' );
function site_pulse_ajax_god_nuke(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	if ( ! site_pulse_is_god() ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	global $wpdb;
	$wpdb->query( "TRUNCATE TABLE " . site_pulse_table('reports') );
	$wpdb->query( "TRUNCATE TABLE " . site_pulse_table('report_answers') );
	$wpdb->query( "TRUNCATE TABLE " . site_pulse_table('action_items') );
	$wpdb->query( "TRUNCATE TABLE " . site_pulse_table('notifications') );
	$wpdb->query( "TRUNCATE TABLE " . site_pulse_table('activity_log') );

	wp_send_json_success( [ 'message' => 'All reports, action items, notifications, and activity logs cleared.' ] );
}

// God-only single-record deletes. GOD alone (not owner/admin/supervisor) can permanently blank
// out user-entered data that otherwise has no delete path. Each verifies the god role explicitly
// — these are not gated by manage_* capabilities, so a non-god admin can never reach them.
add_action( 'wp_ajax_site_pulse_god_delete_report', 'site_pulse_ajax_god_delete_report' );
function site_pulse_ajax_god_delete_report(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god() ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$id = (int) ( $_POST['report_id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid report id.' ] );

	global $wpdb;
	// Cascade: the report's answers and any action items it generated go with it.
	$wpdb->delete( site_pulse_table('report_answers'), [ 'report_id' => $id ] );
	$wpdb->delete( site_pulse_table('action_items'),   [ 'report_id' => $id ] );
	$deleted = $wpdb->delete( site_pulse_table('reports'), [ 'id' => $id ] );

	if ( ! $deleted ) wp_send_json_error( [ 'message' => 'Report not found.' ] );

	site_pulse_log( 'god_delete', sprintf( 'GOD deleted report #%d (and its answers + action items)', $id ) );
	wp_send_json_success( [ 'message' => 'Report deleted.' ] );
}

add_action( 'wp_ajax_site_pulse_god_delete_action_item', 'site_pulse_ajax_god_delete_action_item' );
function site_pulse_ajax_god_delete_action_item(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god() ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$id = (int) ( $_POST['item_id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid item id.' ] );

	global $wpdb;
	$deleted = $wpdb->delete( site_pulse_table('action_items'), [ 'id' => $id ] );
	if ( ! $deleted ) wp_send_json_error( [ 'message' => 'Action item not found.' ] );

	site_pulse_log( 'god_delete', sprintf( 'GOD deleted action item #%d', $id ) );
	wp_send_json_success( [ 'message' => 'Action item deleted.' ] );
}

// Wipe one user's Site Pulse data + their profile row. Shared by the single-user delete and the
// orphan purge. Does NOT touch the WordPress account — callers decide whether to remove that.
function site_pulse_purge_user_data( int $uid ): void {
	global $wpdb;

	// Reports + their answers.
	$report_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . site_pulse_table('reports') . " WHERE user_id = %d", $uid ) );
	if ( $report_ids ) {
		$in = implode( ',', array_map( 'intval', $report_ids ) );
		$wpdb->query( "DELETE FROM " . site_pulse_table('report_answers') . " WHERE report_id IN ($in)" );
	}
	$wpdb->delete( site_pulse_table('reports'), [ 'user_id' => $uid ] );
	$wpdb->delete( site_pulse_table('action_items'), [ 'user_id' => $uid ] );

	// Mileage entries + their legs.
	$entry_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . site_pulse_table('mileage_entries') . " WHERE user_id = %d", $uid ) );
	if ( $entry_ids ) {
		$in = implode( ',', array_map( 'intval', $entry_ids ) );
		$wpdb->query( "DELETE FROM " . site_pulse_table('mileage_legs') . " WHERE entry_id IN ($in)" );
	}
	$wpdb->delete( site_pulse_table('mileage_entries'), [ 'user_id' => $uid ] );

	// Their private/home destinations (never shared global ones), notifications, supervisor links.
	$wpdb->query( $wpdb->prepare( "DELETE FROM " . site_pulse_table('mileage_locations') . " WHERE created_by = %d AND is_private = 1", $uid ) );
	$wpdb->delete( site_pulse_table('notifications'), [ 'user_id' => $uid ] );
	$wpdb->update( site_pulse_table('user_profiles'), [ 'supervisor_id' => 0 ], [ 'supervisor_id' => $uid ] );
	$wpdb->delete( site_pulse_table('user_profiles'), [ 'user_id' => $uid ] );
}

// God-only HARD delete of a user + all their Site Pulse data + the WordPress account itself.
// In normal use a user is set Inactive, never deleted — this exists purely so God can clean up
// test/mistake accounts. Guarded so it can never nuke yourself, another God, or a WP admin.
// Handles an ALREADY-orphaned profile too (WP account gone but the profile row lingers).
add_action( 'wp_ajax_site_pulse_god_delete_user', 'site_pulse_ajax_god_delete_user' );
function site_pulse_ajax_god_delete_user(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god() ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$uid = (int) ( $_POST['user_id'] ?? 0 );
	if ( ! $uid ) wp_send_json_error( [ 'message' => 'Invalid user id.' ] );
	if ( $uid === get_current_user_id() ) wp_send_json_error( [ 'message' => 'You cannot delete your own account.' ] );
	if ( site_pulse_is_superadmin( $uid ) ) wp_send_json_error( [ 'message' => 'The super-admin account cannot be deleted.' ] );
	// A God account can only be deleted by the super-admin (battleplanweb); regular Gods can't.
	if ( site_pulse_is_god( $uid ) && ! site_pulse_is_superadmin() ) {
		wp_send_json_error( [ 'message' => 'Only the super-admin can delete an Odinson account.' ] );
	}

	// $target may be false for an orphaned profile — that's fine, we still purge the profile.
	$target = get_user_by( 'id', $uid );
	if ( $target && in_array( 'administrator', (array) $target->roles, true ) ) {
		wp_send_json_error( [ 'message' => 'WordPress administrators cannot be deleted here.' ] );
	}

	site_pulse_purge_user_data( $uid );

	if ( $target ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $uid );
	}

	site_pulse_log( 'god_delete', sprintf( 'GOD deleted user #%d (%s) and all their data', $uid, $target ? $target->user_login : 'orphaned profile' ) );
	wp_send_json_success( [ 'message' => $target ? 'User and all their data deleted.' : 'Orphaned profile purged.' ] );
}

// God-only bulk cleanup: purge every user_profiles row whose WordPress account no longer exists
// (blank-name "ghost" users from earlier test deletes). God's own row and the WP-user JOIN keep
// real accounts safe — only genuinely orphaned profiles match.
add_action( 'wp_ajax_site_pulse_god_purge_orphans', 'site_pulse_ajax_god_purge_orphans' );
function site_pulse_ajax_god_purge_orphans(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god() ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	global $wpdb;
	$orphans = $wpdb->get_col(
		"SELECT up.user_id FROM " . site_pulse_table('user_profiles') . " up
		 LEFT JOIN {$wpdb->users} u ON u.ID = up.user_id
		 WHERE u.ID IS NULL"
	) ?: [];

	foreach ( $orphans as $oid ) {
		site_pulse_purge_user_data( (int) $oid );
	}

	site_pulse_log( 'god_delete', sprintf( 'GOD purged %d orphaned profile(s)', count( $orphans ) ) );
	wp_send_json_success( [ 'message' => count( $orphans ) . ' orphaned profile(s) purged.', 'count' => count( $orphans ) ] );
}

// Super-admin only: promote another user into God mode. The God role is intentionally absent from
// the Users role dropdown, so this is the sanctioned way to add a God. ONLY battleplanweb may do
// it — other Gods cannot. Creates a profile if the user doesn't have one yet.
add_action( 'wp_ajax_site_pulse_god_grant', 'site_pulse_ajax_god_grant' );
function site_pulse_ajax_god_grant(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_superadmin() ) {
		wp_send_json_error( [ 'message' => 'Only the super-admin can grant Odin access.' ] );
	}

	$uid = (int) ( $_POST['user_id'] ?? 0 );
	if ( ! $uid ) wp_send_json_error( [ 'message' => 'Invalid user id.' ] );
	if ( ! get_user_by( 'id', $uid ) ) wp_send_json_error( [ 'message' => 'User not found.' ] );

	$god = site_pulse_get_role_by_slug( 'god' );
	if ( ! $god ) wp_send_json_error( [ 'message' => 'Odinson role is missing.' ] );

	global $wpdb;
	$now = current_time( 'mysql' );
	if ( site_pulse_get_user_profile( $uid ) ) {
		$wpdb->update( site_pulse_table('user_profiles'),
			[ 'role_id' => (int) $god['id'], 'status' => 'active', 'updated_at' => $now ],
			[ 'user_id' => $uid ]
		);
	} else {
		$wpdb->insert( site_pulse_table('user_profiles'), [
			'user_id'       => $uid,
			'role_id'       => (int) $god['id'],
			'location_id'   => 0,
			'supervisor_id' => 0,
			'status'        => 'active',
			'created_at'    => $now,
			'updated_at'    => $now,
		] );
	}

	site_pulse_log( 'god_grant', sprintf( 'Super-admin granted Odin access to user #%d', $uid ) );
	wp_send_json_success( [ 'message' => 'User now has Odin access.' ] );
}

// Super-admin only: revoke God from a user, demoting them to the lowest-privilege role. The account
// and its data stay intact — only the God role is removed. battleplanweb can never be revoked here.
add_action( 'wp_ajax_site_pulse_god_revoke', 'site_pulse_ajax_god_revoke' );
function site_pulse_ajax_god_revoke(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_superadmin() ) {
		wp_send_json_error( [ 'message' => 'Only the super-admin can revoke Odin access.' ] );
	}

	$uid = (int) ( $_POST['user_id'] ?? 0 );
	if ( ! $uid ) wp_send_json_error( [ 'message' => 'Invalid user id.' ] );
	if ( site_pulse_is_superadmin( $uid ) ) wp_send_json_error( [ 'message' => 'The super-admin cannot be revoked.' ] );
	if ( ! site_pulse_is_god( $uid ) ) wp_send_json_error( [ 'message' => 'That user is not an Odinson.' ] );

	global $wpdb;
	$fallback = $wpdb->get_row(
		"SELECT id FROM " . site_pulse_table('roles') . " WHERE slug != 'god' AND is_active = 1 ORDER BY hierarchy_level ASC LIMIT 1"
	);
	if ( ! $fallback ) wp_send_json_error( [ 'message' => 'No role to demote to.' ] );

	$wpdb->update( site_pulse_table('user_profiles'),
		[ 'role_id' => (int) $fallback->id, 'updated_at' => current_time( 'mysql' ) ],
		[ 'user_id' => $uid ]
	);

	site_pulse_log( 'god_revoke', sprintf( 'Super-admin revoked Odin from user #%d', $uid ) );
	wp_send_json_success( [ 'message' => 'Odin access revoked. Edit the user to set their proper role.' ] );
}


/*--------------------------------------------------------------
# Admin Helper
--------------------------------------------------------------*/

function site_pulse_admin_check( string $capability ): bool {
	$user_id = get_current_user_id();
	$is_wp_admin = in_array( 'administrator', (array) wp_get_current_user()->roles, true );
	if ( $is_wp_admin ) return true;
	if ( site_pulse_user_can( $user_id, $capability ) ) return true;
	wp_send_json_error( [ 'message' => 'You do not have permission to perform this action.' ] );
	return false;
}


/*--------------------------------------------------------------
# God Mode — Impersonation
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_impersonate', 'site_pulse_ajax_impersonate' );
function site_pulse_ajax_impersonate(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$real_id = get_current_user_id();
	if ( ! site_pulse_is_god( $real_id ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$target_id = (int) ( $_POST['user_id'] ?? 0 );
	if ( $target_id ) {
		update_user_meta( $real_id, '_sp_impersonate', $target_id );
	} else {
		delete_user_meta( $real_id, '_sp_impersonate' );
	}

	wp_send_json_success( [ 'redirect' => home_url( '/site-pulse-dashboard/' ) ] );
}


/*--------------------------------------------------------------
# Mileage — Helpers
--------------------------------------------------------------*/

function site_pulse_mileage_rate(): float {
	$rate = (float) site_pulse_get_setting( 'mileage_rate', '0.67' );
	return $rate > 0 ? $rate : 0.67;
}

/**
 * Extra $/mile paid on top of the base rate for legs the driver pulls a trailer on.
 * Default $0.10. Applies only to legs flagged has_trailer.
 */
function site_pulse_mileage_trailer_rate(): float {
	$rate = (float) site_pulse_get_setting( 'mileage_trailer_rate', '0.10' );
	return $rate >= 0 ? $rate : 0.10;
}

/**
 * The editable library of common business purposes (mirrors the prototype's PurposeLib).
 * Stored as a JSON array in config; falls back to a sensible default set.
 */
function site_pulse_mileage_purposes(): array {
	$raw  = site_pulse_get_setting( 'mileage_purposes', '' );
	$list = $raw ? json_decode( $raw, true ) : null;
	if ( ! is_array( $list ) || empty( $list ) ) {
		$list = [
			'Store visit', 'Manager meeting', 'Inspection', 'Deliver supplies',
			'Pick up supplies', 'Cash deposit / bank', 'Vendor visit', 'Training',
			'Maintenance / repair', 'Catering / event',
		];
	}
	return array_values( array_filter( array_map( 'strval', $list ) ) );
}

/**
 * Create or update a PRIVATE home-base location for a work-from-home driver, geocode it,
 * and return its id. Reuses the user's existing private home row if they already have one,
 * so editing the address doesn't spawn duplicates. Private homes are hidden from other
 * drivers' location pickers (PII); store-based home bases don't need this.
 */
function site_pulse_mileage_set_private_home( int $user_id, string $address ): int {
	global $wpdb;
	$now  = current_time( 'mysql' );
	$user = get_userdata( $user_id );
	$name = ( $user ? $user->display_name : 'Driver' ) . ' (home)';

	$geo = site_pulse_mileage_geocode( $address );
	$lat = $geo['lat'] ?? null;
	$lng = $geo['lng'] ?? null;

	// Reuse an existing private home already assigned to this user.
	$current = site_pulse_user_home_location_id( $user_id );
	if ( $current ) {
		$is_priv = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT is_private FROM " . site_pulse_table('mileage_locations') . " WHERE id = %d", $current
		) );
		if ( $is_priv ) {
			$wpdb->update( site_pulse_table('mileage_locations'),
				[ 'name' => $name, 'address' => $address, 'lat' => $lat, 'lng' => $lng, 'updated_at' => $now ],
				[ 'id' => $current ]
			);
			return $current;
		}
	}

	$wpdb->insert( site_pulse_table('mileage_locations'), [
		'name'          => $name,
		'address'       => $address,
		'lat'           => $lat,
		'lng'           => $lng,
		'location_type' => 'home',
		'is_private'    => 1,
		'is_business'   => 0,
		'is_active'     => 1,
		'status'        => 'approved',
		'created_by'    => get_current_user_id(),
		'approved_by'   => get_current_user_id(),
		'approved_at'   => $now,
		'created_at'    => $now,
		'updated_at'    => $now,
	] );
	return (int) $wpdb->insert_id;
}

/**
 * (Re)schedule the daily reminder WP-Cron event to match the current settings.
 * Called on settings save and ensured on init. Clears the event when disabled.
 */
function site_pulse_reschedule_mileage_reminder(): void {
	$existing = wp_next_scheduled( 'site_pulse_mileage_reminder' );
	if ( $existing ) wp_unschedule_event( $existing, 'site_pulse_mileage_reminder' );

	if ( site_pulse_get_setting( 'mileage_reminders_enabled', '0' ) !== '1' ) return;

	$hour       = max( 0, min( 23, (int) site_pulse_get_setting( 'mileage_reminder_hour', '7' ) ) );
	$now_local  = current_time( 'timestamp' );
	$target     = strtotime( date( 'Y-m-d', $now_local ) . sprintf( ' %02d:00:00', $hour ) );
	if ( $target <= $now_local ) $target = strtotime( '+1 day', $target );
	$gmt_offset = current_time( 'timestamp' ) - time(); // local − UTC, in seconds
	wp_schedule_event( $target - $gmt_offset, 'daily', 'site_pulse_mileage_reminder' );
}

add_action( 'site_pulse_mileage_reminder', 'site_pulse_send_mileage_reminders' );
function site_pulse_send_mileage_reminders(): void {
	if ( site_pulse_get_setting( 'mileage_reminders_enabled', '0' ) !== '1' ) return;

	global $wpdb;
	$target     = date( 'Y-m-d', strtotime( '-1 day', current_time( 'timestamp' ) ) ); // yesterday (local)
	$date_label = date( 'F j, Y', strtotime( $target ) );
	$dash       = home_url( '/site-pulse-dashboard/?sp_panel=mileage' );
	$app_name   = site_pulse_get_setting( 'app_name', 'Site Pulse' );

	// Active drivers (submit_mileage capability).
	$drivers = $wpdb->get_results(
		"SELECT up.user_id, u.user_email, u.display_name
		 FROM " . site_pulse_table('user_profiles') . " up
		 INNER JOIN " . site_pulse_table('roles') . " r ON r.id = up.role_id
		 INNER JOIN {$wpdb->users} u ON u.ID = up.user_id
		 WHERE up.status = 'active' AND r.capabilities LIKE '%submit_mileage%'",
		ARRAY_A
	) ?: [];

	$sent = 0;
	foreach ( $drivers as $d ) {
		if ( ! is_email( $d['user_email'] ) ) continue;
		// Skip anyone who already logged that day.
		$logged = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . site_pulse_table('mileage_entries') . " WHERE user_id = %d AND entry_date = %s",
			(int) $d['user_id'], $target
		) );
		if ( $logged ) continue;

		$first   = $d['display_name'] ? ' ' . esc_html( explode( ' ', $d['display_name'] )[0] ) : '';
		$subject = 'Mileage reminder — ' . $date_label;
		$body  = '<div style="font-family:sans-serif;color:#1e293b;max-width:520px;">';
		$body .= '<h2 style="margin:0 0 12px;">Don\'t forget your mileage</h2>';
		$body .= '<p style="color:#475569;line-height:1.5;">Hi' . $first . ', this is a quick reminder to log any business miles for <strong>' . esc_html( $date_label ) . '</strong>.</p>';
		$body .= '<p style="margin:20px 0;"><a href="' . esc_url( $dash ) . '" style="background:#15243a;color:#fff;text-decoration:none;padding:11px 20px;border-radius:8px;display:inline-block;font-weight:600;">Log my mileage</a></p>';
		$body .= '<p style="color:#94a3b8;font-size:13px;line-height:1.5;">Tip: you can type or speak your stops (e.g. "Carrollton, Garland") and we\'ll fill in the rest. Nothing to log that day? You can ignore this email.</p>';
		$body .= '<p style="color:#94a3b8;font-size:12px;margin-top:18px;">' . esc_html( $app_name ) . '</p>';
		$body .= '</div>';
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		if ( wp_mail( $d['user_email'], $subject, $body, $headers ) ) $sent++;
	}

	site_pulse_log( 'mileage_reminder', sprintf( 'Sent %d mileage reminder(s) for %s', $sent, $target ) );
}

/**
 * This user's mileage home base (start/end bookend), or 0 if none.
 *
 * A user assigned to a store (profile location_id) uses that store as their home base —
 * resolved to the mileage_location seeded from that store. Work-from-home users (no
 * location) fall back to an explicitly-set private home (mileage_home_location_id).
 */
function site_pulse_user_home_location_id( int $user_id ): int {
	global $wpdb;
	$profile = $wpdb->get_row( $wpdb->prepare(
		"SELECT location_id, mileage_home_location_id FROM " . site_pulse_table('user_profiles') . " WHERE user_id = %d",
		$user_id
	), ARRAY_A );
	if ( ! $profile ) return 0;

	$loc_id = (int) $profile['location_id'];
	if ( $loc_id ) {
		$home = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . site_pulse_table('mileage_locations') . "
			 WHERE site_pulse_location_id = %d AND status = 'approved' ORDER BY id LIMIT 1",
			$loc_id
		) );
		if ( $home ) return $home;
	}
	return (int) $profile['mileage_home_location_id'];
}

function site_pulse_mileage_normalize_pair( int $a, int $b ): array {
	return $a <= $b ? [ $a, $b ] : [ $b, $a ];
}

function site_pulse_mileage_get_distance( int $a, int $b ): ?float {
	if ( $a === $b ) return 0.0;
	[ $lo, $hi ] = site_pulse_mileage_normalize_pair( $a, $b );
	global $wpdb;
	$miles = $wpdb->get_var( $wpdb->prepare(
		"SELECT miles FROM " . site_pulse_table('mileage_distances') . " WHERE from_id = %d AND to_id = %d",
		$lo, $hi
	) );
	return $miles === null ? null : (float) $miles;
}

/**
 * Returns miles for a pair, computing on the fly via API if both endpoints
 * are approved but the cache miss. Returns null if either endpoint is pending,
 * the pair has same id, or the API call fails.
 */
function site_pulse_mileage_ensure_distance( int $from, int $to ): ?float {
	if ( $from === $to ) return 0.0;

	$cached = site_pulse_mileage_get_distance( $from, $to );
	if ( $cached !== null ) return $cached;

	global $wpdb;
	$statuses = $wpdb->get_col( $wpdb->prepare(
		"SELECT status FROM " . site_pulse_table('mileage_locations') . " WHERE id IN (%d, %d)",
		$from, $to
	) );
	if ( count( $statuses ) !== 2 ) return null;
	foreach ( $statuses as $s ) {
		if ( $s !== 'approved' ) return null;
	}

	// JIT — compute distances from $from to every other approved location
	// (that's one Distance Matrix call out, one back, and caches them all).
	site_pulse_mileage_compute_distances_for( $from );
	return site_pulse_mileage_get_distance( $from, $to );
}

function site_pulse_mileage_save_distance( int $a, int $b, float $miles, string $source = 'api' ): void {
	if ( $a === $b ) return;
	[ $lo, $hi ] = site_pulse_mileage_normalize_pair( $a, $b );
	global $wpdb;
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT miles FROM " . site_pulse_table('mileage_distances') . " WHERE from_id = %d AND to_id = %d",
		$lo, $hi
	) );
	if ( $existing === null ) {
		$wpdb->insert( site_pulse_table('mileage_distances'), [
			'from_id'    => $lo,
			'to_id'      => $hi,
			'miles'      => round( $miles, 2 ),
			'source'     => $source,
			'created_at' => current_time( 'mysql' ),
		] );
	} elseif ( (float) $existing < $miles ) {
		// Keep the larger distance per agreement
		$wpdb->update( site_pulse_table('mileage_distances'),
			[ 'miles' => round( $miles, 2 ), 'source' => $source ],
			[ 'from_id' => $lo, 'to_id' => $hi ]
		);
	}
}

function site_pulse_mileage_google_key(): string {
	// Site Pulse's own Google key (Site Settings) wins — it's the single key wired into the
	// distance matrix, geocoding, the route map, and place search. Fall back to the framework
	// constant/option so existing installs keep working until a key is entered here.
	$k = (string) site_pulse_get_setting( 'google_api_key', '' );
	if ( $k ) return $k;
	if ( defined( '_PLACES_API' ) && _PLACES_API ) return (string) _PLACES_API;
	return (string) get_option( 'bp_places_api', '' );
}

function site_pulse_mileage_geocode( string $address ): ?array {
	$key = site_pulse_mileage_google_key();
	if ( ! $key || ! $address ) return null;

	$url = add_query_arg( [
		'address' => $address,
		'key'     => $key,
	], 'https://maps.googleapis.com/maps/api/geocode/json' );

	$response = wp_remote_get( $url, [ 'timeout' => 15 ] );
	if ( is_wp_error( $response ) ) {
		site_pulse_log( 'mileage_error', 'Geocode failed: ' . $response->get_error_message() );
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $data['results'][0]['geometry']['location'] ) ) {
		site_pulse_log( 'mileage_error', 'Geocode no results', [ 'address' => $address, 'status' => $data['status'] ?? '' ] );
		return null;
	}
	return [
		'lat' => (float) $data['results'][0]['geometry']['location']['lat'],
		'lng' => (float) $data['results'][0]['geometry']['location']['lng'],
	];
}

/**
 * Calls Distance Matrix once per direction. Returns map of [other_id => miles_max_of_both_directions].
 */
function site_pulse_mileage_compute_distances_for( int $location_id ): array {
	$key = site_pulse_mileage_google_key();
	if ( ! $key ) {
		site_pulse_log( 'mileage_error', 'Distance Matrix skipped — no Google API key', [ 'location_id' => $location_id ] );
		return [];
	}

	global $wpdb;
	$loc = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, name, address, lat, lng FROM " . site_pulse_table('mileage_locations') . " WHERE id = %d",
		$location_id
	), ARRAY_A );
	if ( ! $loc ) return [];

	$others = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, name, address, lat, lng FROM " . site_pulse_table('mileage_locations') . " WHERE status = 'approved' AND id != %d",
		$location_id
	), ARRAY_A ) ?: [];

	if ( empty( $others ) ) return [];

	$loc_point = ( $loc['lat'] !== null && $loc['lng'] !== null )
		? $loc['lat'] . ',' . $loc['lng']
		: $loc['address'];

	$other_points = [];
	$other_ids    = [];
	foreach ( $others as $o ) {
		$other_ids[]    = (int) $o['id'];
		$other_points[] = ( $o['lat'] !== null && $o['lng'] !== null )
			? $o['lat'] . ',' . $o['lng']
			: $o['address'];
	}

	$miles_outbound = site_pulse_mileage_distance_matrix_call( [ $loc_point ], $other_points, $key );
	$miles_inbound  = site_pulse_mileage_distance_matrix_call( $other_points, [ $loc_point ], $key );
	if ( $miles_outbound === null && $miles_inbound === null ) return [];

	$result = [];
	foreach ( $other_ids as $idx => $oid ) {
		$out = $miles_outbound[0][ $idx ] ?? null;
		$in  = $miles_inbound[ $idx ][0] ?? null;
		$candidates = array_filter( [ $out, $in ], fn( $v ) => $v !== null );
		if ( empty( $candidates ) ) continue;
		$miles = max( $candidates );
		site_pulse_mileage_save_distance( $location_id, $oid, $miles, 'api' );
		$result[ $oid ] = $miles;
	}
	return $result;
}

function site_pulse_mileage_routes_waypoint( string $point ): array {
	if ( preg_match( '/^-?\d+\.\d+,-?\d+\.\d+$/', $point ) ) {
		[ $lat, $lng ] = array_map( 'floatval', explode( ',', $point ) );
		return [ 'waypoint' => [ 'location' => [ 'latLng' => [ 'latitude' => $lat, 'longitude' => $lng ] ] ] ];
	}
	return [ 'waypoint' => [ 'address' => $point ] ];
}

function site_pulse_mileage_distance_matrix_call( array $origins, array $destinations, string $key ): ?array {
	$body = [
		'origins'           => array_map( 'site_pulse_mileage_routes_waypoint', $origins ),
		'destinations'      => array_map( 'site_pulse_mileage_routes_waypoint', $destinations ),
		'travelMode'        => 'DRIVE',
		'routingPreference' => 'TRAFFIC_UNAWARE',
	];

	$response = wp_remote_post( 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix', [
		'timeout' => 25,
		'headers' => [
			'Content-Type'     => 'application/json',
			'X-Goog-Api-Key'   => $key,
			'X-Goog-FieldMask' => 'originIndex,destinationIndex,distanceMeters,condition',
		],
		'body'    => wp_json_encode( $body ),
	] );

	if ( is_wp_error( $response ) ) {
		site_pulse_log( 'mileage_error', 'Routes API failed: ' . $response->get_error_message() );
		return null;
	}

	$raw  = wp_remote_retrieve_body( $response );
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		site_pulse_log( 'mileage_error', 'Routes API: invalid response', [ 'body' => substr( $raw, 0, 500 ) ] );
		return null;
	}

	// Routes API can wrap an error either at the top level or in the first array element
	$err_obj = $data['error'] ?? ( isset( $data[0]['error'] ) ? $data[0]['error'] : null );
	if ( $err_obj ) {
		site_pulse_log( 'mileage_error', 'Routes API error: ' . ( $err_obj['message'] ?? 'unknown' ), [ 'status' => $err_obj['status'] ?? '' ] );
		return null;
	}

	// Initialize matrix with nulls so missing pairs stay null
	$matrix = [];
	foreach ( $origins as $i => $_ ) {
		$matrix[ $i ] = [];
		foreach ( $destinations as $j => $__ ) {
			$matrix[ $i ][ $j ] = null;
		}
	}

	foreach ( $data as $element ) {
		$i = $element['originIndex']      ?? null;
		$j = $element['destinationIndex'] ?? null;
		if ( $i === null || $j === null ) continue;
		if ( ( $element['condition'] ?? '' ) === 'ROUTE_EXISTS' && isset( $element['distanceMeters'] ) ) {
			$matrix[ $i ][ $j ] = (float) $element['distanceMeters'] / 1609.344;
		}
	}
	return $matrix;
}

function site_pulse_mileage_recalc_entry( int $entry_id ): void {
	global $wpdb;
	$legs = $wpdb->get_results( $wpdb->prepare(
		"SELECT miles, toll_cost, has_trailer FROM " . site_pulse_table('mileage_legs') . " WHERE entry_id = %d",
		$entry_id
	), ARRAY_A ) ?: [];

	$total         = 0.0;
	$tolls         = 0.0;
	$trailer_miles = 0.0;
	$pending       = false;
	foreach ( $legs as $leg ) {
		if ( $leg['miles'] === null ) {
			$pending = true;
		} else {
			$total += (float) $leg['miles'];
			if ( ! empty( $leg['has_trailer'] ) ) $trailer_miles += (float) $leg['miles'];
		}
		if ( $leg['toll_cost'] !== null ) $tolls += (float) $leg['toll_cost'];
	}

	$rate         = site_pulse_mileage_rate();
	$trailer_rate = site_pulse_mileage_trailer_rate();
	// Mileage reimbursement stays miles × rate (all legs). Tolls and the trailer surcharge
	// (extra $/mile on trailer legs only) are tracked separately so each expense category
	// stays distinct. Grand total = reimbursement_amount + total_tolls + total_trailer.
	$wpdb->update( site_pulse_table('mileage_entries'),
		[
			'total_miles'          => round( $total, 2 ),
			'reimbursement_amount' => round( $total * $rate, 2 ),
			'total_tolls'          => round( $tolls, 2 ),
			'total_trailer'        => round( $trailer_miles * $trailer_rate, 2 ),
			'rate_used'            => $rate,
			'updated_at'           => current_time( 'mysql' ),
		],
		[ 'id' => $entry_id ]
	);
}

function site_pulse_mileage_finalize_legs_for_location( int $location_id ): array {
	global $wpdb;
	$legs = $wpdb->get_results( $wpdb->prepare(
		"SELECT l.id, l.entry_id, l.from_location_id, l.to_location_id
		 FROM " . site_pulse_table('mileage_legs') . " l
		 INNER JOIN " . site_pulse_table('mileage_locations') . " lf ON lf.id = l.from_location_id
		 INNER JOIN " . site_pulse_table('mileage_locations') . " lt ON lt.id = l.to_location_id
		 WHERE l.miles IS NULL
		   AND ( l.from_location_id = %d OR l.to_location_id = %d )
		   AND lf.status = 'approved' AND lt.status = 'approved'",
		$location_id, $location_id
	), ARRAY_A ) ?: [];

	$entries_touched = [];
	foreach ( $legs as $leg ) {
		$miles = site_pulse_mileage_get_distance( (int) $leg['from_location_id'], (int) $leg['to_location_id'] );
		if ( $miles === null ) continue;
		$wpdb->update( site_pulse_table('mileage_legs'),
			[ 'miles' => $miles ],
			[ 'id' => (int) $leg['id'] ]
		);
		$entries_touched[ (int) $leg['entry_id'] ] = true;
	}

	foreach ( array_keys( $entries_touched ) as $eid ) {
		site_pulse_mileage_recalc_entry( $eid );
	}
	return array_keys( $entries_touched );
}


/*--------------------------------------------------------------
# Toll Reconciliation — Helpers
--------------------------------------------------------------*/

function site_pulse_toll_vehicle_type(): string {
	$type = site_pulse_get_setting( 'toll_vehicle_type', '2AxlesAuto' );
	return $type ?: '2AxlesAuto';
}

function site_pulse_tollguru_key(): string {
	return (string) site_pulse_get_setting( 'tollguru_api_key', '' );
}

/**
 * Which TollGuru cost column to reimburse at: 'tag' (transponder rate, what NTTA
 * TollTag holders actually pay) or 'cash' (pay-by-plate, the higher rate). Defaults
 * to 'tag' since drivers run transponders; flip it off in Mileage → Toll Pricing
 * to reimburse at the cash rate instead.
 */
function site_pulse_toll_cost_basis(): string {
	$basis = site_pulse_get_setting( 'toll_cost_basis', 'tag' );
	return $basis === 'cash' ? 'cash' : 'tag';
}

/**
 * Returns the cached directional toll route for a (from → to) pair, fetching the
 * encoded polyline from Google's Routes API on a cache miss. Unlike mileage_distances
 * (symmetric), routes are directional — outbound and return have different geometry.
 *
 * Lazy by design: only called during toll-bill reconciliation, never at entry-save time.
 * Returns the route row (assoc array) or null if it can't be built.
 */
function site_pulse_mileage_ensure_route( int $from, int $to ): ?array {
	if ( $from === $to || ! $from || ! $to ) return null;

	global $wpdb;
	$existing = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_toll_routes') . "
		 WHERE from_location_id = %d AND to_location_id = %d
		 ORDER BY is_primary DESC, use_count DESC, id ASC LIMIT 1",
		$from, $to
	), ARRAY_A );
	if ( $existing && ! empty( $existing['polyline'] ) ) return $existing;

	$polyline = site_pulse_mileage_compute_route( $from, $to );
	if ( $polyline === null ) return $existing ?: null;

	$now = current_time( 'mysql' );
	if ( $existing ) {
		$wpdb->update( site_pulse_table('mileage_toll_routes'),
			[ 'polyline' => $polyline, 'source' => 'google', 'updated_at' => $now ],
			[ 'id' => (int) $existing['id'] ]
		);
		$existing['polyline'] = $polyline;
		return $existing;
	}

	$wpdb->insert( site_pulse_table('mileage_toll_routes'), [
		'from_location_id' => $from,
		'to_location_id'   => $to,
		'variant_index'    => 1,
		'is_primary'       => 1,
		'polyline'         => $polyline,
		'source'           => 'google',
		'created_at'       => $now,
		'updated_at'       => $now,
	] );
	$id = (int) $wpdb->insert_id;
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_toll_routes') . " WHERE id = %d", $id
	), ARRAY_A );
}

/**
 * Calls Google Routes API v2:computeRoutes for a single directional leg and returns
 * the encoded overview polyline (string) or null. This is the polyline that TollGuru
 * needs — note computeRouteMatrix (used for distances) does NOT return geometry, so
 * this is a separate, lazily-made call.
 */
function site_pulse_mileage_compute_route( int $from, int $to ): ?string {
	$key = site_pulse_mileage_google_key();
	if ( ! $key ) {
		site_pulse_log( 'mileage_error', 'computeRoutes skipped — no Google API key', [ 'from' => $from, 'to' => $to ] );
		return null;
	}

	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, address, lat, lng FROM " . site_pulse_table('mileage_locations') . " WHERE id IN (%d, %d)",
		$from, $to
	), ARRAY_A ) ?: [];
	$by_id = [];
	foreach ( $rows as $r ) $by_id[ (int) $r['id'] ] = $r;
	if ( ! isset( $by_id[ $from ], $by_id[ $to ] ) ) return null;

	$point = function( array $loc ): string {
		return ( $loc['lat'] !== null && $loc['lng'] !== null ) ? $loc['lat'] . ',' . $loc['lng'] : (string) $loc['address'];
	};

	// computeRouteMatrix wraps each endpoint as { waypoint: {...} }; computeRoutes wants the
	// Waypoint object directly on origin/destination, so unwrap the shared helper's result.
	$origin_wp = site_pulse_mileage_routes_waypoint( $point( $by_id[ $from ] ) )['waypoint'];
	$dest_wp   = site_pulse_mileage_routes_waypoint( $point( $by_id[ $to ] ) )['waypoint'];

	$body = [
		'origin'            => $origin_wp,
		'destination'       => $dest_wp,
		'travelMode'        => 'DRIVE',
		'routingPreference' => 'TRAFFIC_UNAWARE',
		'polylineEncoding'  => 'ENCODED_POLYLINE',
	];

	$response = wp_remote_post( 'https://routes.googleapis.com/directions/v2:computeRoutes', [
		'timeout' => 25,
		'headers' => [
			'Content-Type'     => 'application/json',
			'X-Goog-Api-Key'   => $key,
			'X-Goog-FieldMask' => 'routes.polyline.encodedPolyline,routes.distanceMeters',
		],
		'body'    => wp_json_encode( $body ),
	] );

	if ( is_wp_error( $response ) ) {
		site_pulse_log( 'mileage_error', 'computeRoutes failed: ' . $response->get_error_message() );
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || empty( $data['routes'][0]['polyline']['encodedPolyline'] ) ) {
		$err = $data['error']['message'] ?? 'no polyline in response';
		site_pulse_log( 'mileage_error', 'computeRoutes error: ' . $err );
		return null;
	}
	return (string) $data['routes'][0]['polyline']['encodedPolyline'];
}

/**
 * Prices the toll between two mileage locations via TollGuru's basic Toll API
 * (origin-destination-waypoints — NOT the premium "TollTally" polyline product, which
 * standard keys aren't authorized for). TollGuru routes internally from the from/to
 * lat-lng, so no Google polyline is needed. Returns
 * [ 'tag' => float, 'cash' => float, 'plaza_sequence' => string|null, 'plaza_count' => int ]
 * or null on any failure. $debug (if passed by ref) collects the raw HTTP status/body.
 */
function site_pulse_tollguru_compute_toll( int $from, int $to, array &$debug = null ): ?array {
	$key = site_pulse_tollguru_key();
	if ( ! $key || ! $from || ! $to ) return null;

	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, address, lat, lng FROM " . site_pulse_table('mileage_locations') . " WHERE id IN (%d, %d)",
		$from, $to
	), ARRAY_A ) ?: [];
	$by_id = [];
	foreach ( $rows as $r ) $by_id[ (int) $r['id'] ] = $r;
	if ( ! isset( $by_id[ $from ], $by_id[ $to ] ) ) return null;

	// Prefer lat/lng (exact); fall back to the address string if a row isn't geocoded.
	$point = function( array $l ): array {
		return ( $l['lat'] !== null && $l['lng'] !== null )
			? [ 'lat' => (float) $l['lat'], 'lng' => (float) $l['lng'] ]
			: [ 'address' => (string) $l['address'] ];
	};

	$body = [
		'from'            => $point( $by_id[ $from ] ),
		'to'              => $point( $by_id[ $to ] ),
		'serviceProvider' => 'here',   // TollGuru's default routing engine for this endpoint
		'vehicle'         => [ 'type' => site_pulse_toll_vehicle_type() ],
	];

	$response = wp_remote_post( 'https://apis.tollguru.com/toll/v2/origin-destination-waypoints', [
		'timeout' => 25,
		'headers' => [ 'Content-Type' => 'application/json', 'x-api-key' => $key ],
		'body'    => wp_json_encode( $body ),
	] );

	if ( is_wp_error( $response ) ) {
		if ( $debug !== null ) $debug = [ 'status' => 'wp_error', 'body' => $response->get_error_message() ];
		site_pulse_log( 'mileage_error', 'TollGuru failed: ' . $response->get_error_message() );
		return null;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$raw  = (string) wp_remote_retrieve_body( $response );

	$data  = json_decode( $raw, true );
	$route = is_array( $data ) ? ( $data['routes'][0] ?? null ) : null;
	if ( $debug !== null ) $debug = [
		'status'      => $code,
		'costs'       => is_array( $route ) ? ( $route['costs'] ?? null ) : null,
		'sample_toll' => is_array( $route ) ? ( $route['tolls'][0] ?? null ) : null,
		'body'        => mb_substr( $raw, 0, 800 ),
	];
	if ( $code !== 200 || ! $route ) {
		$err = is_array( $data ) ? ( $data['message'] ?? ( $data['error'] ?? ( 'HTTP ' . $code ) ) ) : ( 'HTTP ' . $code );
		site_pulse_log( 'mileage_error', 'TollGuru error: ' . ( is_string( $err ) ? $err : wp_json_encode( $err ) ) );
		return null;
	}

	$costs = $route['costs'] ?? [];
	// TollGuru returns null/absent costs when a route has no tolls — treat as $0, not failure.
	$tag  = isset( $costs['tag'] )  ? (float) $costs['tag']  : ( isset( $costs['minimumTollCost'] ) ? (float) $costs['minimumTollCost'] : 0.0 );
	$cash = isset( $costs['cash'] ) ? (float) $costs['cash'] : ( isset( $costs['maximumTollCost'] ) ? (float) $costs['maximumTollCost'] : $tag );

	$plazas = is_array( $route['tolls'] ?? null ) ? $route['tolls'] : [];
	$names  = array_filter( array_map( fn( $t ) => $t['name'] ?? null, $plazas ) );

	// Fallback: some responses leave the route-level costs at 0 but carry per-plaza prices.
	// Sum them (covering the field-name variants TollGuru uses) when the totals look empty.
	if ( $tag <= 0 && $cash <= 0 && $plazas ) {
		$sum_tag = 0.0; $sum_cash = 0.0;
		foreach ( $plazas as $p ) {
			$sum_tag  += (float) ( $p['tagCost']  ?? $p['tagPrimaryCost']  ?? $p['tagPrice']  ?? 0 );
			$sum_cash += (float) ( $p['cashCost'] ?? $p['cashPrice']       ?? 0 );
		}
		if ( $sum_tag > 0 || $sum_cash > 0 ) {
			$tag  = $sum_tag;
			$cash = $sum_cash ?: $sum_tag;
		}
	}

	return [
		'tag'            => round( $tag, 2 ),
		'cash'           => round( $cash, 2 ),
		'plaza_sequence' => $names ? wp_json_encode( array_values( $names ) ) : null,
		'plaza_count'    => count( $plazas ),
	];
}

/**
 * Lazy, cached toll cost for a (from → to) leg — the toll analogue of
 * site_pulse_mileage_ensure_distance(). Prices the pair via TollGuru once and caches
 * tag/cash on a directional mileage_toll_routes row (no polyline needed). Returns the
 * cost at the configured basis (tag|cash), or null if it can't be priced (caller leaves
 * the leg pending). One TollGuru call per directional pair, ever.
 */
function site_pulse_mileage_ensure_toll( int $from, int $to ): ?float {
	if ( $from === $to || ! $from || ! $to ) return null;

	global $wpdb;
	$basis = site_pulse_toll_cost_basis();

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_toll_routes') . "
		 WHERE from_location_id = %d AND to_location_id = %d
		 ORDER BY is_primary DESC, id ASC LIMIT 1",
		$from, $to
	), ARRAY_A );

	// Cache hit: tag/cash already priced for this directional pair.
	if ( $row && ( $row['tag_cost'] !== null || $row['cash_cost'] !== null ) ) {
		$val = $basis === 'tag' ? $row['tag_cost'] : $row['cash_cost'];
		return $val === null ? null : (float) $val;
	}

	$toll = site_pulse_tollguru_compute_toll( $from, $to );
	if ( $toll === null ) return null;

	$now    = current_time( 'mysql' );
	$fields = [
		'tag_cost'           => $toll['tag'],
		'cash_cost'          => $toll['cash'],
		'total_typical_cost' => $toll['cash'],
		'plaza_sequence'     => $toll['plaza_sequence'],
		'plaza_count'        => $toll['plaza_count'],
		'source'             => 'tollguru',
		'toll_computed_at'   => $now,
		'updated_at'         => $now,
	];
	if ( $row ) {
		$wpdb->update( site_pulse_table('mileage_toll_routes'), $fields, [ 'id' => (int) $row['id'] ] );
	} else {
		$wpdb->insert( site_pulse_table('mileage_toll_routes'), array_merge( $fields, [
			'from_location_id' => $from,
			'to_location_id'   => $to,
			'variant_index'    => 1,
			'is_primary'       => 1,
			'created_at'       => $now,
		] ) );
	}

	return $basis === 'tag' ? (float) $toll['tag'] : (float) $toll['cash'];
}


/*--------------------------------------------------------------
# Mileage — Manager AJAX
--------------------------------------------------------------*/

// Optional per-type marker images for the map (empty = fall back to the colored dot). Keyed by
// the same buckets the map colors by: home / restaurant / vendor / office / other.
function site_pulse_mileage_marker_icons(): array {
	$out = [];
	foreach ( [ 'home', 'restaurant', 'vendor', 'office', 'other' ] as $t ) {
		$out[ $t ] = (string) site_pulse_get_setting( 'mileage_marker_' . $t, '' );
	}
	return $out;
}

add_action( 'wp_ajax_site_pulse_get_mileage_locations', 'site_pulse_ajax_get_mileage_locations' );
function site_pulse_ajax_get_mileage_locations(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$home_id = site_pulse_user_home_location_id( $user_id );

	global $wpdb;
	// Most home bases are shared stores (no privacy concern). Locations flagged is_private
	// are restricted: a work-from-home driver's residence, or a personal destination a driver
	// saved without sharing it globally. Show those to their owner only — the driver they're
	// the home base for, or whoever created the personal destination — never to other drivers.
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, name, address, lat, lng, location_type, is_private, category, is_business, is_active, pinned_purposes, marker_icon, status, created_by
		 FROM " . site_pulse_table('mileage_locations') . "
		 WHERE ( status = 'approved' OR ( status = 'pending' AND created_by = %d ) )
		   AND is_active = 1
		   AND ( is_private = 0 OR id = %d OR created_by = %d )
		 ORDER BY status DESC, location_type, name",
		$user_id, $home_id, $user_id
	), ARRAY_A ) ?: [];

	wp_send_json_success( [
		'locations'        => $rows,
		'rate'             => site_pulse_mileage_rate(),
		'home_location_id' => $home_id,
		'require_approval' => site_pulse_get_setting( 'mileage_require_approval', '1' ) === '1',
		'purposes'         => site_pulse_mileage_purposes(),
		'marker_icons'     => site_pulse_mileage_marker_icons(),
	] );
}

add_action( 'wp_ajax_site_pulse_add_mileage_location', 'site_pulse_ajax_add_mileage_location' );
function site_pulse_ajax_add_mileage_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id     = site_pulse_effective_user_id();
	$name        = sanitize_text_field( $_POST['name'] ?? '' );
	$address     = sanitize_text_field( $_POST['address'] ?? '' );
	$type        = sanitize_text_field( $_POST['location_type'] ?? 'vendor' );
	$category    = sanitize_text_field( $_POST['category'] ?? '' );
	$is_business = isset( $_POST['is_business'] ) ? ( (int) $_POST['is_business'] ? 1 : 0 ) : 1;
	$notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );

	if ( ! $name || ! $address ) {
		wp_send_json_error( [ 'message' => 'Name and address are required.' ] );
	}

	// With approval off, the driver chooses whether the destination joins the shared global
	// list (save_global) or stays personal to them (is_private). With approval on, save_global
	// is irrelevant — it goes to the queue as a normal shared location.
	$require_approval = site_pulse_get_setting( 'mileage_require_approval', '1' ) === '1';
	$save_global      = isset( $_POST['save_global'] ) ? ( (int) $_POST['save_global'] ? 1 : 0 ) : 1;
	$personal         = ( ! $require_approval && ! $save_global );

	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert( site_pulse_table('mileage_locations'), [
		'name'          => $name,
		'address'       => $address,
		'location_type' => in_array( $type, [ 'restaurant', 'vendor', 'other' ], true ) ? $type : 'vendor',
		'is_private'    => $personal ? 1 : 0,
		'category'      => $category,
		'is_business'   => $is_business,
		'notes'         => $notes,
		'status'        => 'pending',
		'created_by'    => $user_id,
		'created_at'    => $now,
		'updated_at'    => $now,
	] );
	$id = (int) $wpdb->insert_id;

	// When admin approval is turned off, the destination goes straight into the database —
	// geocoded, distance-priced, and immediately usable on entries. No admin notification.
	// A personal save is geocoded and priced the same way but kept private to its creator.
	if ( ! $require_approval ) {
		site_pulse_mileage_approve_location( $id, $user_id );
		site_pulse_log( 'mileage_location_added', sprintf( 'Added location (%s): %s', $personal ? 'personal' : 'auto-approved', $name ), [ 'location_id' => $id ] );
		wp_send_json_success( [ 'id' => $id, 'name' => $name, 'address' => $address, 'status' => 'approved', 'is_private' => $personal ? 1 : 0 ] );
	}

	site_pulse_log( 'mileage_location_proposed', sprintf( 'Proposed location: %s', $name ), [ 'location_id' => $id ] );

	// Notify all admin/owner/god users
	$admins = $wpdb->get_col(
		"SELECT up.user_id FROM " . site_pulse_table('user_profiles') . " up
		 INNER JOIN " . site_pulse_table('roles') . " r ON r.id = up.role_id
		 WHERE up.status = 'active' AND r.slug IN ('god','owner','admin')"
	) ?: [];
	$msg = sprintf( 'New mileage location pending approval: %s', $name );
	foreach ( $admins as $aid ) {
		site_pulse_notify( (int) $aid, 'mileage_pending', $msg, $id, 'mileage_location' );
	}

	wp_send_json_success( [ 'id' => $id, 'name' => $name, 'address' => $address, 'status' => 'pending' ] );
}

add_action( 'wp_ajax_site_pulse_get_mileage_entries', 'site_pulse_ajax_get_mileage_entries' );
function site_pulse_ajax_get_mileage_entries(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	$start = sanitize_text_field( $_POST['start'] ?? '' );
	$end   = sanitize_text_field( $_POST['end']   ?? '' );

	global $wpdb;
	$where  = "WHERE e.user_id = %d";
	$values = [ $user_id ];
	if ( $start ) { $where .= " AND e.entry_date >= %s"; $values[] = $start; }
	if ( $end )   { $where .= " AND e.entry_date <= %s"; $values[] = $end; }

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.*, ( SELECT COUNT(*) FROM " . site_pulse_table('mileage_legs') . " l WHERE l.entry_id = e.id AND l.miles IS NULL ) AS pending_legs
		 FROM " . site_pulse_table('mileage_entries') . " e
		 $where
		 ORDER BY e.entry_date DESC, e.id DESC
		 LIMIT 100",
		...$values
	), ARRAY_A ) ?: [];

	wp_send_json_success( [ 'entries' => $rows, 'rate' => site_pulse_mileage_rate() ] );
}

// Consolidated dataset for the PDF/CSV report: entries in a date range, each with a
// pre-built route string from its legs. One call feeds the client-side report builder.
add_action( 'wp_ajax_site_pulse_get_mileage_report', 'site_pulse_ajax_get_mileage_report' );
function site_pulse_ajax_get_mileage_report(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	$start = sanitize_text_field( $_POST['start'] ?? '' );
	$end   = sanitize_text_field( $_POST['end']   ?? '' );

	global $wpdb;
	$where  = "WHERE e.user_id = %d";
	$values = [ $user_id ];
	if ( $start ) { $where .= " AND e.entry_date >= %s"; $values[] = $start; }
	if ( $end )   { $where .= " AND e.entry_date <= %s"; $values[] = $end; }

	$entries = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, entry_date, total_miles, reimbursement_amount, total_tolls, total_trailer, rate_used, notes
		 FROM " . site_pulse_table('mileage_entries') . " e
		 $where
		 ORDER BY e.entry_date ASC, e.id ASC
		 LIMIT 500",
		...$values
	), ARRAY_A ) ?: [];

	// Build a "Home → A → B → Home" route string and a distinct-purpose summary per entry.
	foreach ( $entries as &$e ) {
		$legs = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.purpose, lf.name AS from_name, lt.name AS to_name
			 FROM " . site_pulse_table('mileage_legs') . " l
			 LEFT JOIN " . site_pulse_table('mileage_locations') . " lf ON lf.id = l.from_location_id
			 LEFT JOIN " . site_pulse_table('mileage_locations') . " lt ON lt.id = l.to_location_id
			 WHERE l.entry_id = %d
			 ORDER BY l.leg_order, l.id",
			(int) $e['id']
		), ARRAY_A ) ?: [];
		$route       = '';
		$route_stops = [];   // [{ name, purpose }] — purpose = business reason for arriving there
		$purposes    = [];
		if ( $legs ) {
			$route         = $legs[0]['from_name'] ?? '?';
			$route_stops[] = [ 'name' => $legs[0]['from_name'] ?? '?', 'purpose' => '' ];
			foreach ( $legs as $lg ) {
				$route        .= ' → ' . ( $lg['to_name'] ?? '?' );
				$p = trim( (string) ( $lg['purpose'] ?? '' ) );
				$route_stops[] = [ 'name' => $lg['to_name'] ?? '?', 'purpose' => $p ];
				if ( $p !== '' && ! in_array( $p, $purposes, true ) ) $purposes[] = $p;
			}
		}
		$e['route']       = $route;
		$e['route_stops'] = $route_stops;
		// Prefer the per-stop purposes; fall back to the entry notes if none were set.
		$e['purpose'] = $purposes ? implode( ', ', $purposes ) : (string) ( $e['notes'] ?? '' );
	}
	unset( $e );

	$user = get_userdata( $user_id );
	wp_send_json_success( [
		'entries'      => $entries,
		'rate'         => site_pulse_mileage_rate(),
		'user_name'    => $user ? $user->display_name : '',
		'app_name'     => site_pulse_get_setting( 'app_name', 'Site Pulse' ),
		'company_name' => site_pulse_get_setting( 'company_name', '' ),
	] );
}

add_action( 'wp_ajax_site_pulse_get_mileage_entry', 'site_pulse_ajax_get_mileage_entry' );
function site_pulse_ajax_get_mileage_entry(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$entry_id = (int) ( $_POST['entry_id'] ?? 0 );

	global $wpdb;
	$entry = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d", $entry_id
	), ARRAY_A );
	if ( ! $entry ) wp_send_json_error( [ 'message' => 'Entry not found.' ] );

	$is_admin = site_pulse_user_can( $user_id, 'manage_mileage' ) || site_pulse_is_god( get_current_user_id() );
	if ( (int) $entry['user_id'] !== $user_id && ! $is_admin ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$legs = $wpdb->get_results( $wpdb->prepare(
		"SELECT l.*, lf.name AS from_name, lf.status AS from_status, lt.name AS to_name, lt.status AS to_status
		 FROM " . site_pulse_table('mileage_legs') . " l
		 LEFT JOIN " . site_pulse_table('mileage_locations') . " lf ON lf.id = l.from_location_id
		 LEFT JOIN " . site_pulse_table('mileage_locations') . " lt ON lt.id = l.to_location_id
		 WHERE l.entry_id = %d
		 ORDER BY l.leg_order, l.id",
		$entry_id
	), ARRAY_A ) ?: [];

	$user = get_userdata( (int) $entry['user_id'] );
	wp_send_json_success( [
		'entry'      => $entry,
		'legs'       => $legs,
		'user_name'  => $user ? $user->display_name : '',
		'user_email' => $user ? $user->user_email : '',
	] );
}

add_action( 'wp_ajax_site_pulse_save_mileage_entry', 'site_pulse_ajax_save_mileage_entry' );
function site_pulse_ajax_save_mileage_entry(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	if ( ! site_pulse_user_can( $user_id, 'submit_mileage' ) && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$entry_id    = (int) ( $_POST['entry_id'] ?? 0 );
	$entry_date  = sanitize_text_field( $_POST['entry_date'] ?? '' );
	$notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );
	$auto_return  = isset( $_POST['auto_return_home'] ) ? ( (int) $_POST['auto_return_home'] ? 1 : 0 ) : 1;
	$stops_raw    = $_POST['stops'] ?? [];
	$purposes_raw = $_POST['purposes'] ?? [];
	$tolls_raw    = $_POST['tolls'] ?? [];
	$trailers_raw = $_POST['trailers'] ?? [];
	// Toll / trailer flags for the final leg home (the auto-appended END bookend).
	$end_toll     = ! empty( $_POST['end_toll'] ) ? 1 : 0;
	$end_trailer  = ! empty( $_POST['end_trailer'] ) ? 1 : 0;
	if ( ! is_array( $stops_raw ) )    $stops_raw = [];
	if ( ! is_array( $purposes_raw ) ) $purposes_raw = [];
	if ( ! is_array( $tolls_raw ) )    $tolls_raw = [];
	if ( ! is_array( $trailers_raw ) ) $trailers_raw = [];

	// Keep stops with their per-stop purposes, toll + trailer flags aligned, dropping empty picks.
	$stops          = [];
	$stop_purposes  = [];
	$stop_tolls     = [];
	$stop_trailers  = [];
	foreach ( array_values( $stops_raw ) as $idx => $sid ) {
		$sid = (int) $sid;
		if ( $sid <= 0 ) continue;
		$stops[]         = $sid;
		$stop_purposes[] = sanitize_text_field( $purposes_raw[ $idx ] ?? '' );
		$stop_tolls[]    = ! empty( $tolls_raw[ $idx ] ) ? 1 : 0;
		$stop_trailers[] = ! empty( $trailers_raw[ $idx ] ) ? 1 : 0;
	}

	if ( ! $entry_date ) wp_send_json_error( [ 'message' => 'Date is required.' ] );

	// Build the full stop sequence. When the driver has a home base configured, the
	// client posts only the MIDDLE stops; the server authoritatively bookends with the
	// home location — locked start, optional return — matching the prototype's
	// ['home', ...stops, 'home'] chain and preventing the client from spoofing the start.
	// $seq_purposes[i] = business purpose for ARRIVING at $seq[i]. The origin and any
	// home bookend carry no purpose; each middle stop carries its own.
	$home_id = site_pulse_user_home_location_id( $user_id );
	if ( $home_id ) {
		if ( count( $stops ) < 1 ) wp_send_json_error( [ 'message' => 'Add at least one stop.' ] );
		$seq          = array_merge( [ $home_id ], $stops );
		$seq_purposes = array_merge( [ '' ], $stop_purposes );
		$seq_tolls    = array_merge( [ 0 ], $stop_tolls );
		$seq_trailers = array_merge( [ 0 ], $stop_trailers );
		if ( $auto_return ) { $seq[] = $home_id; $seq_purposes[] = ''; $seq_tolls[] = $end_toll; $seq_trailers[] = $end_trailer; }
	} else {
		if ( count( $stops ) < 2 ) wp_send_json_error( [ 'message' => 'At least two stops are required.' ] );
		$seq          = $stops;
		$seq_purposes = $stop_purposes;
		$seq_tolls    = $stop_tolls;
		$seq_trailers = $stop_trailers;
		$auto_return  = 0;
	}
	if ( count( $seq ) < 2 ) wp_send_json_error( [ 'message' => 'At least two stops are required.' ] );

	global $wpdb;
	$now = current_time( 'mysql' );

	if ( $entry_id ) {
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d", $entry_id
		), ARRAY_A );
		if ( ! $existing || (int) $existing['user_id'] !== $user_id ) {
			wp_send_json_error( [ 'message' => 'Entry not found or not yours.' ] );
		}
		$wpdb->update( site_pulse_table('mileage_entries'), [
			'entry_date'       => $entry_date,
			'auto_return_home' => $auto_return,
			'notes'            => $notes,
			'updated_at'       => $now,
		], [ 'id' => $entry_id ] );
		$wpdb->delete( site_pulse_table('mileage_legs'), [ 'entry_id' => $entry_id ] );
	} else {
		$wpdb->insert( site_pulse_table('mileage_entries'), [
			'user_id'          => $user_id,
			'entry_date'       => $entry_date,
			'auto_return_home' => $auto_return,
			'notes'            => $notes,
			'created_at'       => $now,
			'updated_at'       => $now,
		] );
		$entry_id = (int) $wpdb->insert_id;
	}

	for ( $i = 0; $i < count( $seq ) - 1; $i++ ) {
		$from = (int) $seq[ $i ];
		$to   = (int) $seq[ $i + 1 ];
		// JIT computes via Distance Matrix if both endpoints are approved
		// but the cache is empty. Returns null if either endpoint is pending.
		$miles = site_pulse_mileage_ensure_distance( $from, $to );

		// Only legs the driver flagged with the Toll box get priced (saves API quota and
		// matches "this drive had tolls"). Lazy + cached, so each O→D pair calls TollGuru once.
		$has_toll  = ! empty( $seq_tolls[ $i + 1 ] ) ? 1 : 0;
		$toll_cost = $has_toll ? site_pulse_mileage_ensure_toll( $from, $to ) : null;

		$wpdb->insert( site_pulse_table('mileage_legs'), [
			'entry_id'         => $entry_id,
			'leg_order'        => $i,
			'from_location_id' => $from,
			'to_location_id'   => $to,
			'miles'            => $miles,
			'purpose'          => $seq_purposes[ $i + 1 ] ?? '',
			'has_toll'         => $has_toll,
			'toll_cost'        => $toll_cost,
			'has_trailer'      => ! empty( $seq_trailers[ $i + 1 ] ) ? 1 : 0,
			'created_at'       => $now,
		] );
	}

	site_pulse_mileage_recalc_entry( $entry_id );
	wp_send_json_success( [ 'entry_id' => $entry_id ] );
}

add_action( 'wp_ajax_site_pulse_delete_mileage_entry', 'site_pulse_ajax_delete_mileage_entry' );
function site_pulse_ajax_delete_mileage_entry(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$entry_id = (int) ( $_POST['entry_id'] ?? 0 );

	global $wpdb;
	$entry = $wpdb->get_row( $wpdb->prepare(
		"SELECT user_id FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d", $entry_id
	), ARRAY_A );
	if ( ! $entry ) wp_send_json_error( [ 'message' => 'Entry not found.' ] );
	if ( (int) $entry['user_id'] !== $user_id && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$wpdb->delete( site_pulse_table('mileage_legs'),    [ 'entry_id' => $entry_id ] );
	$wpdb->delete( site_pulse_table('mileage_entries'), [ 'id'       => $entry_id ] );
	wp_send_json_success();
}

add_action( 'wp_ajax_site_pulse_email_mileage_log', 'site_pulse_ajax_email_mileage_log' );
function site_pulse_ajax_email_mileage_log(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$user    = get_userdata( $user_id );

	$to      = sanitize_email( $_POST['to'] ?? ( $user ? $user->user_email : '' ) );
	$start   = sanitize_text_field( $_POST['start'] ?? '' );
	$end     = sanitize_text_field( $_POST['end']   ?? '' );

	if ( ! is_email( $to ) ) wp_send_json_error( [ 'message' => 'Valid email required.' ] );

	global $wpdb;
	$where  = "WHERE e.user_id = %d";
	$values = [ $user_id ];
	if ( $start ) { $where .= " AND e.entry_date >= %s"; $values[] = $start; }
	if ( $end )   { $where .= " AND e.entry_date <= %s"; $values[] = $end; }

	$entries = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_entries') . " e $where ORDER BY e.entry_date ASC",
		...$values
	), ARRAY_A ) ?: [];

	if ( empty( $entries ) ) wp_send_json_error( [ 'message' => 'No entries to email.' ] );

	$total_miles = 0.0;
	$total_amt   = 0.0;
	$rows_html   = '';
	foreach ( $entries as $e ) {
		$legs = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.miles, lf.name AS from_name, lt.name AS to_name
			 FROM " . site_pulse_table('mileage_legs') . " l
			 LEFT JOIN " . site_pulse_table('mileage_locations') . " lf ON lf.id = l.from_location_id
			 LEFT JOIN " . site_pulse_table('mileage_locations') . " lt ON lt.id = l.to_location_id
			 WHERE l.entry_id = %d ORDER BY l.leg_order, l.id",
			(int) $e['id']
		), ARRAY_A ) ?: [];

		$path = '';
		if ( $legs ) {
			$path .= esc_html( $legs[0]['from_name'] ?? '?' );
			foreach ( $legs as $leg ) $path .= ' &rarr; ' . esc_html( $leg['to_name'] ?? '?' );
		}

		$total_miles += (float) $e['total_miles'];
		$total_amt   += (float) $e['reimbursement_amount'];

		$rows_html .= '<tr>';
		$rows_html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;">' . esc_html( $e['entry_date'] ) . '</td>';
		$rows_html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;">' . $path . '</td>';
		$rows_html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:right;">' . number_format( (float) $e['total_miles'], 2 ) . '</td>';
		$rows_html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:right;">$' . number_format( (float) $e['reimbursement_amount'], 2 ) . '</td>';
		$rows_html .= '</tr>';
	}

	$range_label = ( $start || $end )
		? trim( ( $start ?: '...' ) . ' to ' . ( $end ?: '...' ) )
		: 'All entries';

	$body  = '<h2 style="font-family:sans-serif;">Mileage Log — ' . esc_html( $user ? $user->display_name : '' ) . '</h2>';
	$body .= '<p style="font-family:sans-serif;color:#475569;">' . esc_html( $range_label ) . '</p>';
	$body .= '<table style="border-collapse:collapse;font-family:sans-serif;font-size:14px;width:100%;">';
	$body .= '<thead><tr style="background:#f1f5f9;text-align:left;"><th style="padding:8px 10px;">Date</th><th style="padding:8px 10px;">Path</th><th style="padding:8px 10px;text-align:right;">Miles</th><th style="padding:8px 10px;text-align:right;">$</th></tr></thead><tbody>';
	$body .= $rows_html;
	$body .= '<tr style="font-weight:bold;background:#f8fafc;"><td style="padding:8px 10px;" colspan="2">Total</td>';
	$body .= '<td style="padding:8px 10px;text-align:right;">' . number_format( $total_miles, 2 ) . '</td>';
	$body .= '<td style="padding:8px 10px;text-align:right;">$' . number_format( $total_amt, 2 ) . '</td></tr>';
	$body .= '</tbody></table>';

	$subject = 'Mileage Log — ' . ( $user ? $user->display_name : '' ) . ' — ' . $range_label;
	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

	$sent = wp_mail( $to, $subject, $body, $headers );
	if ( ! $sent ) wp_send_json_error( [ 'message' => 'Email failed to send.' ] );

	wp_send_json_success( [ 'sent' => true, 'to' => $to ] );
}


/*--------------------------------------------------------------
# Mileage — Admin AJAX
--------------------------------------------------------------*/

function site_pulse_mileage_admin_check(): bool {
	$uid = get_current_user_id();
	if ( in_array( 'administrator', (array) wp_get_current_user()->roles, true ) ) return true;
	if ( site_pulse_user_can( $uid, 'manage_mileage' ) ) return true;
	if ( site_pulse_is_god( $uid ) ) return true;
	wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	return false;
}

// Backfill coordinates for approved destinations that have none — typically the seeded
// restaurants, which were inserted without geocoding, so they never appeared on the map.
// Geocode only (fast); leg distances still compute lazily when a day is saved.
add_action( 'wp_ajax_site_pulse_admin_geocode_missing', 'site_pulse_ajax_admin_geocode_missing' );
function site_pulse_ajax_admin_geocode_missing(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	global $wpdb;
	// Cap the batch so a long list can't blow the PHP timeout; the caller can run it again.
	$rows = $wpdb->get_results(
		"SELECT id, address FROM " . site_pulse_table('mileage_locations') . "
		 WHERE status = 'approved' AND ( lat IS NULL OR lng IS NULL )
		 ORDER BY id LIMIT 60",
		ARRAY_A
	) ?: [];

	$now = current_time( 'mysql' );
	$geocoded = 0; $failed = 0;
	foreach ( $rows as $row ) {
		$geo = $row['address'] ? site_pulse_mileage_geocode( $row['address'] ) : null;
		if ( $geo ) {
			$wpdb->update( site_pulse_table('mileage_locations'),
				[ 'lat' => $geo['lat'], 'lng' => $geo['lng'], 'updated_at' => $now ],
				[ 'id' => (int) $row['id'] ]
			);
			$geocoded++;
		} else {
			$failed++;
		}
	}

	$remaining = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM " . site_pulse_table('mileage_locations') . "
		 WHERE status = 'approved' AND ( lat IS NULL OR lng IS NULL )"
	);

	$msg = $geocoded . ' location' . ( $geocoded === 1 ? '' : 's' ) . ' geocoded.';
	if ( $failed )    $msg .= ' ' . $failed . ' could not be geocoded — check the address.';
	if ( $remaining ) $msg .= ' ' . $remaining . ' still need it — run again.';
	if ( ! $geocoded && ! $failed ) $msg = 'All destinations already have map coordinates.';

	site_pulse_log( 'mileage_geocode_backfill', sprintf( 'Geocoded %d, failed %d, remaining %d', $geocoded, $failed, $remaining ) );
	wp_send_json_success( [ 'message' => $msg, 'geocoded' => $geocoded, 'failed' => $failed, 'remaining' => $remaining ] );
}

add_action( 'wp_ajax_site_pulse_admin_get_mileage_locations', 'site_pulse_ajax_admin_get_mileage_locations' );
function site_pulse_ajax_admin_get_mileage_locations(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	global $wpdb;
	// The global destination database. Shared destinations (is_private = 0) and genuine
	// home-base residences belong here; a driver's personal "don't share" destination does
	// not — it's theirs alone, surfaced only on their own entries, never the global list.
	$rows = $wpdb->get_results(
		"SELECT ml.*, u.display_name AS created_by_name
		 FROM " . site_pulse_table('mileage_locations') . " ml
		 LEFT JOIN {$wpdb->users} u ON u.ID = ml.created_by
		 WHERE ml.is_private = 0
		    OR ml.id IN ( SELECT mileage_home_location_id FROM " . site_pulse_table('user_profiles') . " WHERE mileage_home_location_id IS NOT NULL )
		 ORDER BY FIELD(ml.status,'pending','approved'), ml.location_type, ml.name",
		ARRAY_A
	) ?: [];

	wp_send_json_success( [
		'locations'        => $rows,
		'rate'             => site_pulse_mileage_rate(),
		'trailer_rate'     => site_pulse_mileage_trailer_rate(),
		'purposes'         => site_pulse_mileage_purposes(),
		'require_approval' => site_pulse_get_setting( 'mileage_require_approval', '1' ) === '1',
		'marker_icons'     => site_pulse_mileage_marker_icons(),
		'reminders' => [
			'enabled' => site_pulse_get_setting( 'mileage_reminders_enabled', '0' ) === '1',
			'hour'    => (int) site_pulse_get_setting( 'mileage_reminder_hour', '7' ),
		],
		'toll' => [
			'key_set'      => site_pulse_tollguru_key() !== '',
			'vehicle_type' => site_pulse_toll_vehicle_type(),
			'cost_basis'   => site_pulse_toll_cost_basis(),
		],
	] );
}

add_action( 'wp_ajax_site_pulse_admin_save_mileage_approval', 'site_pulse_ajax_admin_save_mileage_approval' );
function site_pulse_ajax_admin_save_mileage_approval(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$require = ! empty( $_POST['require_approval'] ) ? '1' : '0';
	site_pulse_set_setting( 'mileage_require_approval', $require );
	wp_send_json_success( [ 'require_approval' => $require === '1' ] );
}

add_action( 'wp_ajax_site_pulse_admin_save_mileage_reminders', 'site_pulse_ajax_admin_save_mileage_reminders' );
function site_pulse_ajax_admin_save_mileage_reminders(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$enabled = ! empty( $_POST['enabled'] ) ? '1' : '0';
	$hour    = max( 0, min( 23, (int) ( $_POST['hour'] ?? 7 ) ) );
	site_pulse_set_setting( 'mileage_reminders_enabled', $enabled );
	site_pulse_set_setting( 'mileage_reminder_hour', (string) $hour );
	site_pulse_reschedule_mileage_reminder();

	wp_send_json_success( [ 'enabled' => $enabled === '1', 'hour' => $hour, 'next' => wp_next_scheduled( 'site_pulse_mileage_reminder' ) ] );
}

add_action( 'wp_ajax_site_pulse_admin_update_mileage_location', 'site_pulse_ajax_admin_update_mileage_location' );
function site_pulse_ajax_admin_update_mileage_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid id.' ] );

	global $wpdb;
	$fields = [ 'updated_at' => current_time( 'mysql' ) ];
	if ( isset( $_POST['name'] ) )          $fields['name']          = sanitize_text_field( $_POST['name'] );
	if ( isset( $_POST['location_type'] ) ) $fields['location_type'] = sanitize_text_field( $_POST['location_type'] );
	if ( isset( $_POST['category'] ) )      $fields['category']      = sanitize_text_field( $_POST['category'] );
	if ( isset( $_POST['is_business'] ) )   $fields['is_business']   = (int) $_POST['is_business'] ? 1 : 0;
	if ( isset( $_POST['is_active'] ) )     $fields['is_active']     = (int) $_POST['is_active'] ? 1 : 0;
	if ( isset( $_POST['notes'] ) )         $fields['notes']         = sanitize_textarea_field( $_POST['notes'] );
	// Per-location marker image override (empty clears it → falls back to the per-type default).
	if ( isset( $_POST['marker_icon'] ) )   $fields['marker_icon']   = esc_url_raw( trim( (string) $_POST['marker_icon'] ) );

	if ( isset( $_POST['pinned_purposes'] ) ) {
		$pp = $_POST['pinned_purposes'];
		if ( ! is_array( $pp ) ) $pp = [];
		$pp = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $pp ) ) ) );
		$fields['pinned_purposes'] = wp_json_encode( $pp );
	}

	// Address change → re-geocode so cached distances and the map stay correct.
	if ( isset( $_POST['address'] ) ) {
		$address = sanitize_text_field( $_POST['address'] );
		$fields['address'] = $address;
		$geo = site_pulse_mileage_geocode( $address );
		if ( $geo ) { $fields['lat'] = $geo['lat']; $fields['lng'] = $geo['lng']; }
	}

	$wpdb->update( site_pulse_table('mileage_locations'), $fields, [ 'id' => $id ] );
	wp_send_json_success();
}

add_action( 'wp_ajax_site_pulse_admin_add_mileage_location', 'site_pulse_ajax_admin_add_mileage_location' );
function site_pulse_ajax_admin_add_mileage_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$name    = sanitize_text_field( $_POST['name'] ?? '' );
	$address = sanitize_text_field( $_POST['address'] ?? '' );
	if ( ! $name || ! $address ) wp_send_json_error( [ 'message' => 'Name and address are required.' ] );

	$type        = sanitize_text_field( $_POST['location_type'] ?? 'vendor' );
	$category    = sanitize_text_field( $_POST['category'] ?? '' );
	$is_business = isset( $_POST['is_business'] ) ? ( (int) $_POST['is_business'] ? 1 : 0 ) : 1;
	$notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );

	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert( site_pulse_table('mileage_locations'), [
		'name'          => $name,
		'address'       => $address,
		'location_type' => in_array( $type, [ 'restaurant', 'vendor', 'other' ], true ) ? $type : 'vendor',
		'category'      => $category,
		'is_business'   => $is_business,
		'notes'         => $notes,
		'status'        => 'pending',
		'created_by'    => get_current_user_id(),
		'created_at'    => $now,
		'updated_at'    => $now,
	] );
	$id = (int) $wpdb->insert_id;

	// Admin-added destinations skip the approval queue entirely — approved on the spot,
	// geocoded and distance-priced immediately.
	$res = site_pulse_mileage_approve_location( $id, get_current_user_id() );
	site_pulse_log( 'mileage_location_added', sprintf( 'Admin added location: %s', $name ), [ 'location_id' => $id ] );

	wp_send_json_success( [ 'id' => $id, 'distances_added' => $res['distances_added'] ?? 0 ] );
}

add_action( 'wp_ajax_site_pulse_admin_delete_mileage_location', 'site_pulse_ajax_admin_delete_mileage_location' );
function site_pulse_ajax_admin_delete_mileage_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid id.' ] );

	global $wpdb;
	$loc = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_locations') . " WHERE id = %d", $id
	), ARRAY_A );
	if ( ! $loc ) wp_send_json_error( [ 'message' => 'Not found.' ] );

	// Entries whose legs reference this location must be recalculated once it's gone.
	$affected_entries = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT entry_id FROM " . site_pulse_table('mileage_legs') . " WHERE from_location_id = %d OR to_location_id = %d",
		$id, $id
	) ) ?: [];

	$wpdb->delete( site_pulse_table('mileage_legs'), [ 'from_location_id' => $id ] );
	$wpdb->delete( site_pulse_table('mileage_legs'), [ 'to_location_id'   => $id ] );
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM " . site_pulse_table('mileage_distances') . " WHERE from_id = %d OR to_id = %d", $id, $id
	) );
	$wpdb->delete( site_pulse_table('mileage_locations'), [ 'id' => $id ] );

	foreach ( $affected_entries as $eid ) {
		site_pulse_mileage_recalc_entry( (int) $eid );
	}

	site_pulse_log( 'mileage_location_deleted',
		sprintf( 'Deleted location: %s', $loc['name'] ),
		[ 'name' => $loc['name'], 'address' => $loc['address'], 'entries_affected' => count( $affected_entries ) ]
	);

	wp_send_json_success( [ 'entries_affected' => count( $affected_entries ) ] );
}

/**
 * Promote a mileage location to "approved": geocode if needed, flip status, then compute its
 * distances and finalize any pending legs that reference it. Shared by the admin approve action
 * and the auto-approve path used when admin approval is turned off. Returns the location row plus
 * the distance/entry counts, or null if the id doesn't exist.
 */
function site_pulse_mileage_approve_location( int $id, int $approver_id ): ?array {
	global $wpdb;
	$loc = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_locations') . " WHERE id = %d", $id
	), ARRAY_A );
	if ( ! $loc ) return null;

	if ( $loc['lat'] === null || $loc['lng'] === null ) {
		$geo = site_pulse_mileage_geocode( (string) $loc['address'] );
		if ( $geo ) {
			$wpdb->update( site_pulse_table('mileage_locations'),
				[ 'lat' => $geo['lat'], 'lng' => $geo['lng'] ],
				[ 'id' => $id ]
			);
		}
	}

	$now = current_time( 'mysql' );
	$wpdb->update( site_pulse_table('mileage_locations'),
		[
			'status'      => 'approved',
			'approved_by' => $approver_id,
			'approved_at' => $now,
			'updated_at'  => $now,
		],
		[ 'id' => $id ]
	);

	$distances_added = site_pulse_mileage_compute_distances_for( $id );
	$entries_updated = site_pulse_mileage_finalize_legs_for_location( $id );

	return [
		'loc'             => $loc,
		'distances_added' => count( $distances_added ),
		'entries_updated' => count( $entries_updated ),
	];
}

add_action( 'wp_ajax_site_pulse_admin_approve_mileage_location', 'site_pulse_ajax_admin_approve_mileage_location' );
function site_pulse_ajax_admin_approve_mileage_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid id.' ] );

	$res = site_pulse_mileage_approve_location( $id, get_current_user_id() );
	if ( ! $res ) wp_send_json_error( [ 'message' => 'Not found.' ] );

	$loc = $res['loc'];
	if ( (int) $loc['created_by'] ) {
		site_pulse_notify( (int) $loc['created_by'], 'mileage_approved',
			sprintf( 'Your mileage location was approved: %s', $loc['name'] ),
			$id, 'mileage_location'
		);
	}
	site_pulse_log( 'mileage_location_approved',
		sprintf( 'Approved location: %s', $loc['name'] ),
		[ 'location_id' => $id, 'distances_added' => $res['distances_added'], 'entries_updated' => $res['entries_updated'] ]
	);

	wp_send_json_success( [
		'distances_added' => $res['distances_added'],
		'entries_updated' => $res['entries_updated'],
	] );
}

add_action( 'wp_ajax_site_pulse_admin_reject_mileage_location', 'site_pulse_ajax_admin_reject_mileage_location' );
function site_pulse_ajax_admin_reject_mileage_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid id.' ] );

	global $wpdb;
	$loc = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_locations') . " WHERE id = %d AND status = 'pending'", $id
	), ARRAY_A );
	if ( ! $loc ) wp_send_json_error( [ 'message' => 'Not found or already approved.' ] );

	$affected_entries = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT entry_id FROM " . site_pulse_table('mileage_legs') . " WHERE from_location_id = %d OR to_location_id = %d",
		$id, $id
	) ) ?: [];

	$wpdb->delete( site_pulse_table('mileage_legs'), [ 'from_location_id' => $id ] );
	$wpdb->delete( site_pulse_table('mileage_legs'), [ 'to_location_id'   => $id ] );
	$wpdb->delete( site_pulse_table('mileage_locations'), [ 'id' => $id ] );

	foreach ( $affected_entries as $eid ) {
		site_pulse_mileage_recalc_entry( (int) $eid );
	}

	if ( (int) $loc['created_by'] ) {
		site_pulse_notify( (int) $loc['created_by'], 'mileage_rejected',
			sprintf( 'Your mileage location was rejected: %s — affected legs were removed from your entries.', $loc['name'] ),
			0, 'mileage_location'
		);
	}
	site_pulse_log( 'mileage_location_rejected',
		sprintf( 'Rejected location: %s', $loc['name'] ),
		[ 'name' => $loc['name'], 'address' => $loc['address'], 'entries_affected' => count( $affected_entries ) ]
	);

	wp_send_json_success( [ 'entries_affected' => count( $affected_entries ) ] );
}

add_action( 'wp_ajax_site_pulse_admin_save_mileage_rate', 'site_pulse_ajax_admin_save_mileage_rate' );
function site_pulse_ajax_admin_save_mileage_rate(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$rate = (float) ( $_POST['rate'] ?? 0 );
	if ( $rate <= 0 || $rate > 5 ) wp_send_json_error( [ 'message' => 'Rate must be between 0 and $5/mi.' ] );

	site_pulse_set_setting( 'mileage_rate', (string) $rate );

	// Optional trailer surcharge rate ($/mile added on trailer legs). 0 disables it.
	if ( isset( $_POST['trailer_rate'] ) ) {
		$trailer = (float) $_POST['trailer_rate'];
		if ( $trailer < 0 || $trailer > 5 ) wp_send_json_error( [ 'message' => 'Trailer rate must be between 0 and $5/mi.' ] );
		site_pulse_set_setting( 'mileage_trailer_rate', (string) $trailer );
	}

	wp_send_json_success( [ 'rate' => $rate, 'trailer_rate' => site_pulse_mileage_trailer_rate() ] );
}

add_action( 'wp_ajax_site_pulse_admin_get_mileage_purposes', 'site_pulse_ajax_admin_get_mileage_purposes' );
function site_pulse_ajax_admin_get_mileage_purposes(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;
	wp_send_json_success( [ 'purposes' => site_pulse_mileage_purposes() ] );
}

add_action( 'wp_ajax_site_pulse_admin_save_mileage_purposes', 'site_pulse_ajax_admin_save_mileage_purposes' );
function site_pulse_ajax_admin_save_mileage_purposes(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$raw = $_POST['purposes'] ?? [];
	if ( ! is_array( $raw ) ) $raw = [];
	$clean = array_values( array_unique( array_filter( array_map( function( $p ) {
		return sanitize_text_field( $p );
	}, $raw ) ) ) );
	site_pulse_set_setting( 'mileage_purposes', wp_json_encode( $clean ) );
	wp_send_json_success( [ 'purposes' => $clean ] );
}

// Bulk-add reviewed destination candidates (from a Timeline/MileIQ import) as approved
// locations. `items` is a JSON string of {name, address, lat, lng} objects.
add_action( 'wp_ajax_site_pulse_admin_bulk_add_mileage_locations', 'site_pulse_ajax_admin_bulk_add_mileage_locations' );
function site_pulse_ajax_admin_bulk_add_mileage_locations(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$items = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
	if ( ! is_array( $items ) || ! $items ) wp_send_json_error( [ 'message' => 'Nothing to import.' ] );

	global $wpdb;
	$now = current_time( 'mysql' );
	$uid = get_current_user_id();
	$added = 0;
	foreach ( $items as $it ) {
		$name = sanitize_text_field( $it['name'] ?? '' );
		if ( $name === '' ) continue;
		$address = sanitize_text_field( $it['address'] ?? '' );
		$lat = ( isset( $it['lat'] ) && $it['lat'] !== '' ) ? (float) $it['lat'] : null;
		$lng = ( isset( $it['lng'] ) && $it['lng'] !== '' ) ? (float) $it['lng'] : null;
		if ( ( $lat === null || $lng === null ) && $address ) {
			$geo = site_pulse_mileage_geocode( $address );
			if ( $geo ) { $lat = $geo['lat']; $lng = $geo['lng']; }
		}
		$wpdb->insert( site_pulse_table('mileage_locations'), [
			'name'          => $name,
			'address'       => $address,
			'lat'           => $lat,
			'lng'           => $lng,
			'location_type' => 'vendor',
			'is_business'   => 1,
			'is_active'     => 1,
			'status'        => 'approved',
			'created_by'    => $uid,
			'approved_by'   => $uid,
			'approved_at'   => $now,
			'created_at'    => $now,
			'updated_at'    => $now,
		] );
		$added++;
	}
	wp_send_json_success( [ 'added' => $added ] );
}

// Full distance matrix for the admin grid: every approved location + every stored pair.
add_action( 'wp_ajax_site_pulse_admin_get_mileage_matrix', 'site_pulse_ajax_admin_get_mileage_matrix' );
function site_pulse_ajax_admin_get_mileage_matrix(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	global $wpdb;
	$locs = $wpdb->get_results(
		"SELECT id, name FROM " . site_pulse_table('mileage_locations') . "
		 WHERE status = 'approved' ORDER BY name",
		ARRAY_A
	) ?: [];

	$rows = $wpdb->get_results(
		"SELECT from_id, to_id, miles, source FROM " . site_pulse_table('mileage_distances'),
		ARRAY_A
	) ?: [];

	// Key each pair as "lo-hi" (distances are symmetric / normalized).
	$dist = [];
	foreach ( $rows as $r ) {
		$dist[ (int) $r['from_id'] . '-' . (int) $r['to_id'] ] = [
			'miles'  => (float) $r['miles'],
			'source' => $r['source'],
		];
	}

	wp_send_json_success( [ 'locations' => $locs, 'distances' => $dist ] );
}

// Manual override of a single pair (forces the value, bypassing the keep-larger rule).
add_action( 'wp_ajax_site_pulse_admin_save_mileage_distance', 'site_pulse_ajax_admin_save_mileage_distance' );
function site_pulse_ajax_admin_save_mileage_distance(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$a     = (int) ( $_POST['from_id'] ?? 0 );
	$b     = (int) ( $_POST['to_id'] ?? 0 );
	$miles = round( (float) ( $_POST['miles'] ?? 0 ), 2 );
	if ( ! $a || ! $b || $a === $b ) wp_send_json_error( [ 'message' => 'Invalid pair.' ] );
	if ( $miles < 0 ) wp_send_json_error( [ 'message' => 'Miles must be 0 or more.' ] );

	[ $lo, $hi ] = site_pulse_mileage_normalize_pair( $a, $b );
	global $wpdb;
	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . site_pulse_table('mileage_distances') . " WHERE from_id = %d AND to_id = %d",
		$lo, $hi
	) );
	if ( $exists ) {
		$wpdb->update( site_pulse_table('mileage_distances'),
			[ 'miles' => $miles, 'source' => 'manual' ],
			[ 'from_id' => $lo, 'to_id' => $hi ]
		);
	} else {
		$wpdb->insert( site_pulse_table('mileage_distances'), [
			'from_id'    => $lo,
			'to_id'      => $hi,
			'miles'      => $miles,
			'source'     => 'manual',
			'created_at' => current_time( 'mysql' ),
		] );
	}

	// Fill any pending (uncomputed) legs that were waiting on this pair. Already-finalized
	// legs keep their logged miles — we don't silently rewrite history.
	site_pulse_mileage_finalize_legs_for_location( $lo );
	wp_send_json_success( [ 'miles' => $miles ] );
}

add_action( 'wp_ajax_site_pulse_admin_get_toll_settings', 'site_pulse_ajax_admin_get_toll_settings' );
function site_pulse_ajax_admin_get_toll_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$key = site_pulse_tollguru_key();
	wp_send_json_success( [
		'key_set'      => $key !== '',
		'key_preview'  => $key ? substr( $key, 0, 4 ) . '…' . substr( $key, -4 ) : '',
		'vehicle_type' => site_pulse_toll_vehicle_type(),
		'cost_basis'   => site_pulse_toll_cost_basis(),
	] );
}

add_action( 'wp_ajax_site_pulse_admin_save_toll_settings', 'site_pulse_ajax_admin_save_toll_settings' );
function site_pulse_ajax_admin_save_toll_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	// Only overwrite the key when a non-empty value is posted, so re-saving the
	// settings panel without re-typing the key doesn't wipe it.
	if ( isset( $_POST['tollguru_api_key'] ) && $_POST['tollguru_api_key'] !== '' ) {
		site_pulse_set_setting( 'tollguru_api_key', sanitize_text_field( $_POST['tollguru_api_key'] ) );
	}
	if ( isset( $_POST['vehicle_type'] ) && $_POST['vehicle_type'] !== '' ) {
		site_pulse_set_setting( 'toll_vehicle_type', sanitize_text_field( $_POST['vehicle_type'] ) );
	}
	if ( isset( $_POST['cost_basis'] ) ) {
		site_pulse_set_setting( 'toll_cost_basis', $_POST['cost_basis'] === 'tag' ? 'tag' : 'cash' );
	}
	wp_send_json_success( [
		'vehicle_type' => site_pulse_toll_vehicle_type(),
		'cost_basis'   => site_pulse_toll_cost_basis(),
	] );
}

add_action( 'wp_ajax_site_pulse_admin_test_toll_api', 'site_pulse_ajax_admin_test_toll_api' );
function site_pulse_ajax_admin_test_toll_api(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$key = site_pulse_tollguru_key();
	if ( ! $key ) {
		wp_send_json_success( [ 'error' => 'No TollGuru API key set — enter it in the toll settings above and save first.' ] );
	}

	// Price a real toll via the basic Toll API (origin-destination). $0 is a valid result —
	// it just means that route has no tolls; the key still works. $debug captures TollGuru's
	// raw status/body. Admin can pick a specific From/To pair; otherwise the two lowest-id.
	global $wpdb;
	$from_id = (int) ( $_POST['from'] ?? 0 );
	$to_id   = (int) ( $_POST['to'] ?? 0 );

	if ( $from_id && $to_id ) {
		if ( $from_id === $to_id ) {
			wp_send_json_success( [ 'error' => 'Pick two different locations to test.' ] );
		}
		$found = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, name FROM " . site_pulse_table('mileage_locations') . "
			 WHERE id IN (%d, %d) AND lat IS NOT NULL AND lng IS NOT NULL",
			$from_id, $to_id
		), ARRAY_A ) ?: [];
		$by = [];
		foreach ( $found as $l ) $by[ (int) $l['id'] ] = $l;
		if ( ! isset( $by[ $from_id ], $by[ $to_id ] ) ) {
			wp_send_json_success( [ 'error' => 'Both locations must be geocoded (have map coordinates) to test.' ] );
		}
		$locs = [ $by[ $from_id ], $by[ $to_id ] ];   // preserve From → To direction
	} else {
		$locs = $wpdb->get_results(
			"SELECT id, name FROM " . site_pulse_table('mileage_locations') . "
			 WHERE status = 'approved' AND lat IS NOT NULL AND lng IS NOT NULL
			 ORDER BY id ASC LIMIT 2", ARRAY_A ) ?: [];
		if ( count( $locs ) < 2 ) {
			wp_send_json_success( [ 'error' => 'Need at least two approved, geocoded mileage locations to run a test.' ] );
		}
	}

	$debug = [];
	$toll  = site_pulse_tollguru_compute_toll( (int) $locs[0]['id'], (int) $locs[1]['id'], $debug );

	if ( $toll === null ) {
		wp_send_json_success( [
			'error'        => 'TollGuru rejected the request. See the raw response below.',
			'key_preview'  => substr( $key, 0, 4 ) . '…' . substr( $key, -4 ),
			'vehicle_type' => site_pulse_toll_vehicle_type(),
			'attempts'     => [ [ 'endpoint' => 'origin-destination-waypoints', 'status' => $debug['status'] ?? '?', 'body' => $debug['body'] ?? '' ] ],
		] );
	}

	wp_send_json_success( [
		'ok'           => true,
		'endpoint'     => 'origin-destination-waypoints',
		'key_preview'  => substr( $key, 0, 4 ) . '…' . substr( $key, -4 ),
		'from'         => $locs[0]['name'],
		'to'           => $locs[1]['name'],
		'vehicle_type' => site_pulse_toll_vehicle_type(),
		'tag'          => $toll['tag'],
		'cash'         => $toll['cash'],
		'plaza_count'  => $toll['plaza_count'],
		'plazas'       => $toll['plaza_sequence'] ? ( json_decode( $toll['plaza_sequence'], true ) ?: [] ) : [],
		// Diagnostic: the raw route-level costs + a sample plaza, so we can see exactly
		// where (if anywhere) TollGuru put the dollar amounts.
		'raw_costs'    => $debug['costs'] ?? null,
		'sample_toll'  => $debug['sample_toll'] ?? null,
	] );
}

/**
 * Probe whether this TollGuru key may use the POLYLINE endpoint
 * (complete-polyline-from-mapping-service) — i.e. price tolls along the EXACT Google route
 * instead of letting TollGuru re-route from from/to. Builds the Google polyline for the
 * chosen pair, POSTs it to the polyline endpoint, and reports authorization + result so we
 * can tell, definitively, whether the plan already includes it or an upgrade is needed.
 * God-only, read-only (no caching, no DB writes).
 */
add_action( 'wp_ajax_site_pulse_admin_test_toll_polyline', 'site_pulse_ajax_admin_test_toll_polyline' );
function site_pulse_ajax_admin_test_toll_polyline(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$key = site_pulse_tollguru_key();
	if ( ! $key ) {
		wp_send_json_success( [ 'error' => 'No TollGuru API key set — enter it in Site Settings and save first.' ] );
	}

	global $wpdb;
	$from_id = (int) ( $_POST['from'] ?? 0 );
	$to_id   = (int) ( $_POST['to'] ?? 0 );

	if ( $from_id && $to_id ) {
		if ( $from_id === $to_id ) wp_send_json_success( [ 'error' => 'Pick two different locations to test.' ] );
		$found = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, name FROM " . site_pulse_table('mileage_locations') . "
			 WHERE id IN (%d, %d) AND lat IS NOT NULL AND lng IS NOT NULL",
			$from_id, $to_id
		), ARRAY_A ) ?: [];
		$by = [];
		foreach ( $found as $l ) $by[ (int) $l['id'] ] = $l;
		if ( ! isset( $by[ $from_id ], $by[ $to_id ] ) ) wp_send_json_success( [ 'error' => 'Both locations must be geocoded to test.' ] );
		$locs = [ $by[ $from_id ], $by[ $to_id ] ];
	} else {
		$locs = $wpdb->get_results(
			"SELECT id, name FROM " . site_pulse_table('mileage_locations') . "
			 WHERE status = 'approved' AND lat IS NOT NULL AND lng IS NOT NULL
			 ORDER BY id ASC LIMIT 2", ARRAY_A ) ?: [];
		if ( count( $locs ) < 2 ) wp_send_json_success( [ 'error' => 'Need at least two approved, geocoded locations to run a test.' ] );
	}

	// 1) Build the exact Google route polyline for this pair (this part uses the Google key).
	$polyline = site_pulse_mileage_compute_route( (int) $locs[0]['id'], (int) $locs[1]['id'] );
	if ( ! $polyline ) {
		wp_send_json_success( [ 'error' => 'Could not build a Google route polyline for this pair — check the Google Routes API key / quota (see mileage error log).' ] );
	}

	// 2) Send that exact geometry to TollGuru's polyline endpoint.
	$body = [
		'source'   => 'google',           // the mapping service that produced the polyline
		'polyline' => $polyline,
		'vehicle'  => [ 'type' => site_pulse_toll_vehicle_type() ],
	];
	$response = wp_remote_post( 'https://apis.tollguru.com/toll/v2/complete-polyline-from-mapping-service', [
		'timeout' => 25,
		'headers' => [ 'Content-Type' => 'application/json', 'x-api-key' => $key ],
		'body'    => wp_json_encode( $body ),
	] );

	if ( is_wp_error( $response ) ) {
		wp_send_json_success( [ 'error' => 'Request failed: ' . $response->get_error_message() ] );
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$raw  = (string) wp_remote_retrieve_body( $response );
	$data = json_decode( $raw, true );
	$route = is_array( $data ) ? ( $data['routes'][0] ?? null ) : null;

	// Verdict: 200 with a route = the key already allows the polyline endpoint. 401/403 (or a
	// plan/authorization message) = it's gated and an upgrade is needed.
	$authorized = ( $code === 200 && $route );
	$gated      = in_array( $code, [ 401, 402, 403 ], true );

	$plazas = [];
	$tag = $cash = null;
	if ( $route ) {
		$costs = $route['costs'] ?? [];
		$tag   = isset( $costs['tag'] )  ? (float) $costs['tag']  : null;
		$cash  = isset( $costs['cash'] ) ? (float) $costs['cash'] : null;
		$tolls = is_array( $route['tolls'] ?? null ) ? $route['tolls'] : [];
		$plazas = array_values( array_filter( array_map( fn( $t ) => $t['name'] ?? null, $tolls ) ) );
	}

	wp_send_json_success( [
		'authorized'   => $authorized,
		'gated'        => $gated,
		'status'       => $code,
		'endpoint'     => 'complete-polyline-from-mapping-service',
		'key_preview'  => substr( $key, 0, 4 ) . '…' . substr( $key, -4 ),
		'from'         => $locs[0]['name'],
		'to'           => $locs[1]['name'],
		'vehicle_type' => site_pulse_toll_vehicle_type(),
		'polyline_len' => strlen( $polyline ),
		'tag'          => $tag,
		'cash'         => $cash,
		'plazas'       => $plazas,
		'message'      => is_array( $data ) ? ( $data['message'] ?? ( $data['error'] ?? null ) ) : null,
		'body'         => mb_substr( $raw, 0, 800 ),   // raw response for diagnosis
	] );
}

add_action( 'wp_ajax_site_pulse_admin_test_mileage_api', 'site_pulse_ajax_admin_test_mileage_api' );
function site_pulse_ajax_admin_test_mileage_api(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$key = site_pulse_mileage_google_key();

	$key_source = 'NOT SET';
	if ( site_pulse_get_setting( 'google_api_key', '' ) ) {
		$key_source = 'Site Settings → Google API Key';
	} elseif ( defined( '_PLACES_API' ) && _PLACES_API ) {
		$key_source = '_PLACES_API constant (wp-config: BP_PLACES_KEY)';
	} elseif ( get_option( 'bp_places_api', '' ) ) {
		$key_source = 'wp option: bp_places_api';
	}

	$result = [
		'key_source'      => $key_source,
		'key_preview'     => $key ? substr( $key, 0, 6 ) . '...' . substr( $key, -4 ) : '(empty)',
		'geocode'         => null,
		'distance_matrix' => null,
	];

	if ( ! $key ) {
		$result['error'] = "No API key found. Enter a Google API Key in Site Settings, or define('BP_PLACES_KEY', 'AIza...') in wp-config.php.";
		wp_send_json_success( $result );
	}

	// Geocode test
	$geo_url = add_query_arg( [
		'address' => 'Arlington, TX',
		'key'     => $key,
	], 'https://maps.googleapis.com/maps/api/geocode/json' );

	$resp = wp_remote_get( $geo_url, [ 'timeout' => 15 ] );
	if ( is_wp_error( $resp ) ) {
		$result['geocode'] = [ 'ok' => false, 'error' => 'wp_remote_get: ' . $resp->get_error_message() ];
	} else {
		$body = wp_remote_retrieve_body( $resp );
		$data = json_decode( $body, true );
		$status = $data['status'] ?? 'UNKNOWN';
		$err    = $data['error_message'] ?? '';
		if ( $status === 'OK' && ! empty( $data['results'][0]['geometry']['location'] ) ) {
			$loc = $data['results'][0]['geometry']['location'];
			$result['geocode'] = [
				'ok'     => true,
				'status' => $status,
				'result' => round( $loc['lat'], 4 ) . ',' . round( $loc['lng'], 4 ),
			];
		} else {
			$result['geocode'] = [
				'ok'     => false,
				'status' => $status,
				'error'  => $err ?: 'No geometry in response',
			];
		}
	}

	// Routes API test (Compute Route Matrix — replaces legacy Distance Matrix)
	$routes_body = [
		'origins'           => [ [ 'waypoint' => [ 'address' => 'Arlington, TX' ] ] ],
		'destinations'      => [ [ 'waypoint' => [ 'address' => 'Burleson, TX' ] ] ],
		'travelMode'        => 'DRIVE',
		'routingPreference' => 'TRAFFIC_UNAWARE',
	];

	$resp = wp_remote_post( 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix', [
		'timeout' => 25,
		'headers' => [
			'Content-Type'     => 'application/json',
			'X-Goog-Api-Key'   => $key,
			'X-Goog-FieldMask' => 'originIndex,destinationIndex,distanceMeters,condition',
		],
		'body'    => wp_json_encode( $routes_body ),
	] );

	if ( is_wp_error( $resp ) ) {
		$result['distance_matrix'] = [ 'ok' => false, 'error' => 'wp_remote_post: ' . $resp->get_error_message() ];
	} else {
		$raw     = wp_remote_retrieve_body( $resp );
		$code    = wp_remote_retrieve_response_code( $resp );
		$data    = json_decode( $raw, true );
		$preview = substr( $raw, 0, 600 );

		// Routes API can wrap an error either at the top level or in the first array element
		$err_obj = $data['error'] ?? ( isset( $data[0]['error'] ) ? $data[0]['error'] : null );

		if ( $err_obj ) {
			$result['distance_matrix'] = [
				'ok'             => false,
				'status'         => $err_obj['status'] ?? 'ERROR',
				'element_status' => 'N/A',
				'http_code'      => $code,
				'error'          => $err_obj['message'] ?? 'Unknown error',
				'raw'            => $preview,
			];
		} elseif ( is_array( $data ) && ! empty( $data ) ) {
			// Element may live at $data[0] (array form) or $data itself (single-object form)
			$el = isset( $data[0] ) && is_array( $data[0] ) ? $data[0] : $data;
			$cond  = $el['condition'] ?? '';
			$dist  = $el['distanceMeters'] ?? null;

			if ( $dist !== null ) {
				// Distance present → treat as successful even if condition was omitted
				$miles = (float) $dist / 1609.344;
				$result['distance_matrix'] = [
					'ok'             => true,
					'status'         => 'OK',
					'element_status' => $cond ?: 'ROUTE_EXISTS (assumed)',
					'http_code'      => $code,
					'miles'          => round( $miles, 2 ),
				];
			} else {
				$result['distance_matrix'] = [
					'ok'             => false,
					'status'         => 'OK',
					'element_status' => $cond ?: 'NO_DISTANCE',
					'http_code'      => $code,
					'error'          => 'Response had no distanceMeters. Raw: ' . $preview,
					'raw'            => $preview,
				];
			}
		} else {
			$result['distance_matrix'] = [
				'ok'             => false,
				'status'         => 'EMPTY',
				'element_status' => 'N/A',
				'http_code'      => $code,
				'error'          => 'Empty/non-JSON response',
				'raw'            => $preview,
			];
		}
	}

	wp_send_json_success( $result );
}

add_action( 'wp_ajax_site_pulse_get_pending_mileage_count', 'site_pulse_ajax_get_pending_mileage_count' );
function site_pulse_ajax_get_pending_mileage_count(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid = get_current_user_id();
	if ( ! site_pulse_user_can( $uid, 'manage_mileage' ) && ! site_pulse_is_god( $uid ) ) {
		wp_send_json_success( [ 'count' => 0 ] );
		return;
	}
	global $wpdb;
	$count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM " . site_pulse_table('mileage_locations') . " WHERE status = 'pending'"
	);
	wp_send_json_success( [ 'count' => $count ] );
}

add_action( 'wp_ajax_site_pulse_admin_recompute_mileage_distances', 'site_pulse_ajax_admin_recompute_mileage_distances' );
function site_pulse_ajax_admin_recompute_mileage_distances(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT id FROM " . site_pulse_table('mileage_locations') . " WHERE status = 'approved' ORDER BY id"
	) ?: [];

	$added = 0;
	foreach ( $ids as $id ) {
		$result = site_pulse_mileage_compute_distances_for( (int) $id );
		$added += count( $result );
		site_pulse_mileage_finalize_legs_for_location( (int) $id );
	}

	wp_send_json_success( [ 'distances_added' => $added, 'locations_processed' => count( $ids ) ] );
}


/*--------------------------------------------------------------
# Auth Guard
--------------------------------------------------------------*/

add_action( 'template_redirect', 'site_pulse_auth_guard' );
function site_pulse_auth_guard(): void {
	global $post;
	if ( ! $post || $post->post_name === 'site-pulse-login' ) return;

	$sp_slugs = [ 'site-pulse-dashboard' ];
	if ( ! in_array( $post->post_name, $sp_slugs, true ) ) return;

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/site-pulse-login/' ) );
		exit;
	}

	$current_user = wp_get_current_user();
	$is_wp_admin  = in_array( 'administrator', (array) $current_user->roles, true );
	$profile      = site_pulse_get_user_profile( $current_user->ID );

	if ( ! $is_wp_admin && ( ! $profile || $profile['status'] !== 'active' ) ) {
		wp_logout();
		wp_safe_redirect( home_url( '/site-pulse-login/?error=inactive' ) );
		exit;
	}
}
