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

define( 'SITE_PULSE_DB_VERSION', '1.5' );

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
} );

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

function site_pulse_seed_roles(): void {
	global $wpdb;
	$now = current_time( 'mysql' );
	$roles = [
		[
			'slug'            => 'god',
			'label'           => 'God',
			'capabilities'    => wp_json_encode( [ 'view_all_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'view_ai_insights', 'submit_reports', 'view_own_reports', 'view_team_reports', 'review_reports', 'god_mode' ] ),
			'hierarchy_level' => 255,
		],
		[
			'slug'            => 'owner',
			'label'           => 'Owner',
			'capabilities'    => wp_json_encode( [ 'view_all_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'view_ai_insights' ] ),
			'hierarchy_level' => 100,
		],
		[
			'slug'            => 'admin',
			'label'           => 'Administrator',
			'capabilities'    => wp_json_encode( [ 'view_all_reports', 'manage_locations', 'manage_users', 'manage_templates', 'view_analytics', 'manage_settings', 'view_ai_insights' ] ),
			'hierarchy_level' => 90,
		],
		[
			'slug'            => 'supervisor',
			'label'           => 'Supervisor',
			'capabilities'    => wp_json_encode( [ 'view_team_reports', 'review_reports', 'view_analytics' ] ),
			'hierarchy_level' => 50,
		],
		[
			'slug'            => 'manager',
			'label'           => 'Manager',
			'capabilities'    => wp_json_encode( [ 'submit_reports', 'view_own_reports', 'view_analytics' ] ),
			'hierarchy_level' => 20,
		],
	];

	foreach ( $roles as $role ) {
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . site_pulse_table('roles') . " WHERE slug = %s", $role['slug']
		) );
		if ( $exists ) {
			$wpdb->update( site_pulse_table('roles'), $role, [ 'slug' => $role['slug'] ] );
		} else {
			$wpdb->insert( site_pulse_table('roles'), $role );
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


/*--------------------------------------------------------------
# Page Detection & Caching
--------------------------------------------------------------*/

add_action( 'template_redirect', function() {
	$sp_slugs = [ 'site-pulse-login', 'site-pulse-dashboard' ];
	global $post;
	if ( ! $post || ! in_array( $post->post_name, $sp_slugs, true ) ) return;

	if ( ! defined('DONOTCACHEPAGE') ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();

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

	wp_enqueue_script(
		'site-pulse-script',
		get_template_directory_uri() . $script_file,
		[],
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
		'impersonating'      => site_pulse_is_impersonating(),
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

function site_pulse_can_view_report( int $viewer_id, array $report ): bool {
	if ( (int) $report['user_id'] === $viewer_id ) return true;
	if ( site_pulse_user_can( $viewer_id, 'view_all_reports' ) ) return true;
	if ( site_pulse_user_can( $viewer_id, 'view_team_reports' ) ) {
		$team = site_pulse_get_team_user_ids( $viewer_id );
		return in_array( (int) $report['user_id'], array_map( 'intval', $team ), true );
	}
	return false;
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
			'user_id'       => $wp_user_id,
			'role_id'       => (int) ( $data['role_id'] ?? 0 ),
			'location_id'   => (int) ( $data['location_id'] ?? 0 ),
			'supervisor_id' => (int) ( $data['supervisor_id'] ?? 0 ),
			'employee_id'   => sanitize_text_field( $data['employee_id'] ?? '' ),
			'status'        => 'active',
			'created_at'    => $now,
			'updated_at'    => $now,
		],
		[ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
	);

	site_pulse_log( 'user_created', sprintf( 'Created user %s', $username ), [ 'wp_user_id' => $wp_user_id ] );

	return [ 'success' => true, 'user_id' => $wp_user_id, 'password' => $password ];
}

function site_pulse_get_all_users( bool $active_only = true, bool $include_god = false ): array {
	global $wpdb;
	$conditions = [];
	if ( $active_only ) $conditions[] = "up.status = 'active'";
	if ( ! $include_god ) $conditions[] = "r.slug != 'god'";
	$where = $conditions ? "WHERE " . implode( ' AND ', $conditions ) : "";
	return $wpdb->get_results(
		"SELECT up.*, u.user_login, u.user_email, u.display_name, r.slug AS role_slug, r.label AS role_label, l.name AS location_name
		 FROM " . site_pulse_table('user_profiles') . " up
		 LEFT JOIN {$wpdb->users} u ON u.ID = up.user_id
		 LEFT JOIN " . site_pulse_table('roles') . " r ON r.id = up.role_id
		 LEFT JOIN " . site_pulse_table('locations') . " l ON l.id = up.location_id
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
	$password = $_POST['password'] ?? '';

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

	if ( $action_type === 'submit' ) {
		site_pulse_submit_report( $report_id );
		site_pulse_create_action_items_from_report( $report_id );
	}

	global $wpdb;
	$wpdb->update(
		site_pulse_table('reports'),
		[ 'updated_at' => current_time( 'mysql' ) ],
		[ 'id' => $report_id ]
	);

	wp_send_json_success( [
		'report_id' => $report_id,
		'status'    => $action_type === 'submit' ? 'submitted' : 'draft',
		'message'   => $action_type === 'submit' ? 'Report submitted successfully.' : 'Report saved as draft.',
	] );
}

add_action( 'wp_ajax_site_pulse_get_reports', 'site_pulse_ajax_get_reports' );
function site_pulse_ajax_get_reports(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	$args    = [];

	if ( site_pulse_user_can( $user_id, 'view_all_reports' ) ) {
		if ( ! empty( $_POST['user_id'] ) ) $args['user_id'] = (int) $_POST['user_id'];
	} elseif ( site_pulse_user_can( $user_id, 'view_team_reports' ) ) {
		$team_ids   = site_pulse_get_team_user_ids( $user_id );
		$team_ids[] = $user_id;
		if ( ! empty( $_POST['user_id'] ) && in_array( (int) $_POST['user_id'], array_map( 'intval', $team_ids ), true ) ) {
			$args['user_id'] = (int) $_POST['user_id'];
		} else {
			$args['user_ids'] = $team_ids;
		}
	} else {
		$args['user_id'] = $user_id;
	}

	if ( ! empty( $_POST['location_id'] ) ) $args['location_id'] = (int) $_POST['location_id'];

	if ( ! empty( $_POST['template_id'] ) )  $args['template_id']  = (int) $_POST['template_id'];
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

	wp_send_json_success( [
		'report'   => $report,
		'answers'  => $answers,
		'template' => $template,
		'fields'   => $fields,
		'location' => $location,
		'author'   => $author ? [ 'id' => $author->ID, 'name' => $author->display_name ] : null,
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

	wp_send_json_success( [ 'users' => $users, 'roles' => $roles, 'locations' => $locations ] );
}

add_action( 'wp_ajax_site_pulse_admin_create_user', 'site_pulse_ajax_admin_create_user' );
function site_pulse_ajax_admin_create_user(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_users' ) ) return;

	$result = site_pulse_create_user( [
		'username'      => $_POST['username'] ?? '',
		'email'         => $_POST['email'] ?? '',
		'password'      => $_POST['password'] ?? '',
		'first_name'    => $_POST['first_name'] ?? '',
		'last_name'     => $_POST['last_name'] ?? '',
		'role_id'       => (int) ( $_POST['role_id'] ?? 0 ),
		'location_id'   => (int) ( $_POST['location_id'] ?? 0 ),
		'supervisor_id' => (int) ( $_POST['supervisor_id'] ?? 0 ),
		'employee_id'   => $_POST['employee_id'] ?? '',
	] );

	if ( $result['success'] ) {
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
	if ( isset( $_POST['location_id'] ) )   $updates['location_id']   = (int) $_POST['location_id'];
	if ( isset( $_POST['supervisor_id'] ) ) $updates['supervisor_id'] = (int) $_POST['supervisor_id'];
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

	if ( ! empty( $_POST['new_password'] ) ) {
		wp_set_password( $_POST['new_password'], $user_id );
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

	wp_send_json_success( [ 'templates' => $templates ] );
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

	$fields = site_pulse_get_template_fields( $template_id );
	wp_send_json_success( [ 'fields' => $fields ] );
}


/*--------------------------------------------------------------
# Review Filters AJAX
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_review_filters', 'site_pulse_ajax_get_review_filters' );
function site_pulse_ajax_get_review_filters(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	$locations = site_pulse_get_all_locations();

	if ( site_pulse_user_can( $user_id, 'view_all_reports' ) ) {
		$users = site_pulse_get_all_users( true, false );
	} elseif ( site_pulse_user_can( $user_id, 'view_team_reports' ) ) {
		$team_ids = site_pulse_get_team_user_ids( $user_id );
		$users = [];
		foreach ( $team_ids as $tid ) {
			$u = get_userdata( $tid );
			if ( $u ) {
				$users[] = [ 'user_id' => $tid, 'display_name' => $u->display_name ];
			}
		}
	} else {
		$users = [];
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
			'status'      => 'open',
			'due_date'    => $due_date,
			'created_at'  => $now,
			'updated_at'  => $now,
		] );
		$count++;

		if ( $priority === 'high' ) {
			$high_items[] = $item['description'];
		}
	}

	if ( $count ) {
		$profile = site_pulse_get_user_profile( $report['user_id'] );
		$user    = get_userdata( $report['user_id'] );
		$loc     = site_pulse_get_location( $report['location_id'] );

		// Summary notification
		$msg = sprintf( '%d action item%s generated from %s\'s report for %s',
			$count,
			$count > 1 ? 's' : '',
			$user ? $user->display_name : 'Unknown',
			$loc ? $loc['name'] : 'Unknown'
		);

		site_pulse_notify( (int) $report['user_id'], 'action_items', $msg, $report_id, 'action_item' );

		if ( $profile && $profile['supervisor_id'] ) {
			site_pulse_notify( (int) $profile['supervisor_id'], 'action_items', $msg, $report_id, 'action_item' );
		}

		// Separate urgent notification for high-priority items only
		if ( ! empty( $high_items ) ) {
			$urgent_msg = sprintf( 'URGENT — %d high-priority item%s from %s (%s): %s',
				count( $high_items ),
				count( $high_items ) > 1 ? 's' : '',
				$user ? $user->display_name : 'Unknown',
				$loc ? $loc['name'] : 'Unknown',
				implode( '; ', $high_items )
			);

			site_pulse_notify( (int) $report['user_id'], 'action_urgent', $urgent_msg, $report_id, 'action_item' );

			if ( $profile && $profile['supervisor_id'] ) {
				site_pulse_notify( (int) $profile['supervisor_id'], 'action_urgent', $urgent_msg, $report_id, 'action_item' );
			}
		}

		site_pulse_log( 'action_items_generated', $msg, [ 'report_id' => $report_id, 'count' => $count, 'high' => count( $high_items ) ] );
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

	if ( site_pulse_user_can( $user_id, 'view_all_reports' ) ) {
		if ( ! empty( $_POST['user_id'] ) ) $args['user_id'] = (int) $_POST['user_id'];
	} elseif ( site_pulse_user_can( $user_id, 'view_team_reports' ) ) {
		$team_ids   = site_pulse_get_team_user_ids( $user_id );
		$team_ids[] = $user_id;
		if ( ! empty( $_POST['user_id'] ) && in_array( (int) $_POST['user_id'], array_map( 'intval', $team_ids ), true ) ) {
			$args['user_id'] = (int) $_POST['user_id'];
		}
	} else {
		$args['user_id'] = $user_id;
	}

	if ( ! empty( $_POST['location_id'] ) ) $args['location_id'] = (int) $_POST['location_id'];
	if ( ! empty( $_POST['status'] ) )      $args['status']      = sanitize_text_field( $_POST['status'] );

	wp_send_json_success( [ 'items' => site_pulse_get_action_items( $args ) ] );
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

	wp_send_json_success( [
		'claude_api_key_masked' => $masked,
		'claude_api_key_set'    => ! empty( $api_key ),
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
