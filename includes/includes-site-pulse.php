<?php
require_once get_template_directory() . '/prompts/prompts-site-pulse.php';
require_once get_template_directory() . '/includes/includes-site-pulse-modules.php';
require_once get_template_directory() . '/includes/includes-site-pulse-reviews.php';
require_once get_template_directory() . '/includes/includes-site-pulse-surveys.php';
require_once get_template_directory() . '/includes/includes-site-pulse-import.php';
require_once get_template_directory() . '/includes/includes-site-pulse-messages.php';
require_once get_template_directory() . '/includes/includes-site-pulse-push.php';
require_once get_template_directory() . '/includes/includes-site-pulse-pwa.php';

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

define( 'SITE_PULSE_DB_VERSION', '1.42' );

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
		is_store tinyint(1) NOT NULL DEFAULT 1,
		address varchar(255) DEFAULT NULL,
		city varchar(100) DEFAULT NULL,
		state varchar(50) DEFAULT NULL,
		zip varchar(20) DEFAULT NULL,
		phone varchar(30) DEFAULT NULL,
		location_number varchar(50) DEFAULT NULL,
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
		capability_overrides text DEFAULT NULL,
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
		on_dashboard tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_notifications (user_id, is_read, is_archived)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('messages') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		conversation_id int(11) NOT NULL DEFAULT 0,
		sender_id int(11) NOT NULL,
		recipient_id int(11) NOT NULL,
		body text NOT NULL,
		is_read tinyint(1) NOT NULL DEFAULT 0,
		edited tinyint(1) NOT NULL DEFAULT 0,
		attach_url varchar(255) DEFAULT NULL,
		attach_name varchar(255) DEFAULT NULL,
		attach_mime varchar(100) DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY conversation (sender_id, recipient_id),
		KEY conv (conversation_id),
		KEY inbox (recipient_id, is_read)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('conversations') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		is_group tinyint(1) NOT NULL DEFAULT 0,
		title varchar(255) DEFAULT NULL,
		created_by int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('conversation_participants') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		conversation_id int(11) NOT NULL,
		user_id int(11) NOT NULL,
		last_read_message_id int(11) NOT NULL DEFAULT 0,
		seen_message_id int(11) NOT NULL DEFAULT 0,
		seen_at datetime DEFAULT NULL,
		joined_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY convo_user (conversation_id, user_id),
		KEY user_convos (user_id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('push_subscriptions') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		endpoint varchar(500) NOT NULL,
		endpoint_hash char(64) NOT NULL,
		p256dh varchar(255) DEFAULT NULL,
		auth varchar(255) DEFAULT NULL,
		ua varchar(255) DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY endpoint_hash (endpoint_hash),
		KEY user_id (user_id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table('action_items') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		report_id int(11) NOT NULL,
		user_id int(11) NOT NULL,
		created_by int(11) NOT NULL DEFAULT 0,
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
		code varchar(50) DEFAULT NULL,
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
		miles_adjust decimal(8,2) DEFAULT NULL,
		miles_adjust_reason varchar(255) DEFAULT NULL,
		purpose varchar(255) DEFAULT NULL,
		charge_to varchar(50) DEFAULT NULL,
		note text DEFAULT NULL,
		has_toll tinyint(1) NOT NULL DEFAULT 0,
		toll_cost decimal(8,2) DEFAULT NULL,
		toll_estimate decimal(8,2) DEFAULT NULL,
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
		allocation_leg_id int(11) DEFAULT NULL,
		allocation_confidence decimal(4,3) DEFAULT NULL,
		allocation_note varchar(255) DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY bill_id (bill_id),
		KEY user_id (user_id),
		KEY allocation_status (allocation_status)
	) $charset;";

	// Expense report — general line items for sections B–F of the reimbursement form. One row
	// per line item; the `section` column decides which other columns are meaningful (e.g.
	// section B uses category/description/amount; C/D add place/business_purpose/attendees).
	$sql .= "CREATE TABLE " . site_pulse_table('expenses') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		section varchar(2) NOT NULL,
		expense_date date NOT NULL,
		category varchar(40) DEFAULT NULL,
		description text DEFAULT NULL,
		place varchar(255) DEFAULT NULL,
		business_purpose varchar(255) DEFAULT NULL,
		attendees varchar(255) DEFAULT NULL,
		store_number varchar(40) DEFAULT NULL,
		account_code varchar(20) DEFAULT NULL,
		amount decimal(10,2) NOT NULL DEFAULT 0,
		receipt_path varchar(255) DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_section (user_id, section),
		KEY user_date (user_id, expense_date)
	) $charset;";

	// Uploaded Forms library — files shared company-wide, organized into repositories
	// (training / kitchen / foh / misc). `file_format` is the auto-detected family (PDF, Word,
	// Excel, Image…); `type_label` is the uploader's manual label (Checklist, Policy…).
	$sql .= "CREATE TABLE " . site_pulse_table('forms') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(200) NOT NULL,
		category varchar(20) NOT NULL,
		sub_category varchar(40) DEFAULT NULL,
		type_label varchar(80) DEFAULT NULL,
		file_path varchar(255) NOT NULL,
		file_name varchar(255) DEFAULT NULL,
		file_format varchar(20) DEFAULT NULL,
		file_size int(11) NOT NULL DEFAULT 0,
		uploaded_by int(11) NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY category (category),
		KEY uploaded_by (uploaded_by)
	) $charset;";

	// Recoverable deletions — powers the header "Undo" button. One row per deleted record
	// (mileage day or expense line), holding a JSON snapshot to re-insert on undo.
	$sql .= "CREATE TABLE " . site_pulse_table('trash') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		kind varchar(20) NOT NULL,
		label varchar(255) DEFAULT NULL,
		payload longtext NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_created (user_id, created_at)
	) $charset;";

	// Customer satisfaction surveys forwarded in from the public restaurant sites (cross-site,
	// HMAC-signed — see includes-site-pulse-surveys.php). One row per submission. `ratings` is a
	// JSON map of dimension => 1-5 (dimensions can differ per brand); `avg_rating`/`overall` are
	// denormalized for fast sorting + summary cards. `location` is the customer-picked restaurant.
	$sql .= "CREATE TABLE " . site_pulse_table('surveys') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		location varchar(150) DEFAULT NULL,
		source_site varchar(150) DEFAULT NULL,
		customer_name varchar(150) DEFAULT NULL,
		email varchar(190) DEFAULT NULL,
		phone varchar(40) DEFAULT NULL,
		address varchar(255) DEFAULT NULL,
		city varchar(100) DEFAULT NULL,
		state varchar(50) DEFAULT NULL,
		zip varchar(20) DEFAULT NULL,
		experience varchar(40) DEFAULT NULL,
		visit_date date DEFAULT NULL,
		referral varchar(80) DEFAULT NULL,
		ratings longtext DEFAULT NULL,
		avg_rating decimal(3,2) DEFAULT NULL,
		overall tinyint(4) DEFAULT NULL,
		comments text DEFAULT NULL,
		source_ip varchar(60) DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY location (location),
		KEY visit_date (visit_date),
		KEY created_at (created_at)
	) $charset;";

	// PER-USER survey archiving — archiving hides a survey from THAT user's list only; everyone
	// else still sees it until they archive it themselves. One row per (user, survey) archived.
	$sql .= "CREATE TABLE " . site_pulse_table('survey_archives') . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		user_id int(11) NOT NULL,
		survey_id int(11) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY user_survey (user_id, survey_id),
		KEY survey_id (survey_id)
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
	site_pulse_migrate_survey_caps();

	// One-time: fold legacy 1:1 messages into the conversation model (group threads). Self-guarded.
	if ( ! get_option( 'site_pulse_msg_convos_migrated' ) && function_exists( 'sp_msg_migrate_to_conversations' ) ) {
		sp_msg_migrate_to_conversations();
		update_option( 'site_pulse_msg_convos_migrated', '1' );
	}

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

	// Daily expense-period reminder cron (always scheduled; it self-gates on period config + the
	// Notifications matrix + pending-user list, so it's a no-op until the submit flow exists).
	if ( ! wp_next_scheduled( 'site_pulse_expense_period_reminder' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 7:00am' ), 'daily', 'site_pulse_expense_period_reminder' );
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

/**
 * One-time: grant the Surveys caps to existing tiers that already manage Reviews (the Surveys
 * caps were added after Reviews shipped). Any tier with view_reviews gains view_surveys; any with
 * manage_reviews gains manage_surveys. Insert-only + option-guarded, so a later admin tweak sticks.
 * (New sites already get these from seed_roles.) They stay inert until the Surveys module is on.
 */
function site_pulse_migrate_survey_caps(): void {
	if ( get_option( 'site_pulse_survey_caps_seeded' ) ) return;
	update_option( 'site_pulse_survey_caps_seeded', '1' );

	global $wpdb;
	$tbl  = site_pulse_table('roles');
	$rows = $wpdb->get_results( "SELECT id, capabilities FROM $tbl", ARRAY_A ) ?: [];
	foreach ( $rows as $row ) {
		$caps    = json_decode( $row['capabilities'], true ) ?: [];
		$changed = false;
		if ( in_array( 'view_reviews', $caps, true ) && ! in_array( 'view_surveys', $caps, true ) )     { $caps[] = 'view_surveys';   $changed = true; }
		if ( in_array( 'manage_reviews', $caps, true ) && ! in_array( 'manage_surveys', $caps, true ) ) { $caps[] = 'manage_surveys'; $changed = true; }
		if ( $changed ) $wpdb->update( $tbl, [ 'capabilities' => wp_json_encode( array_values( $caps ) ), 'updated_at' => current_time( 'mysql' ) ], [ 'id' => (int) $row['id'] ] );
	}
}

function site_pulse_seed_roles(): void {
	global $wpdb;
	$now = current_time( 'mysql' );
	$roles = [
		[
			'slug'            => 'god',
			'label'           => 'Odinson',
			'capabilities'    => wp_json_encode( [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'view_ai_insights', 'submit_reports', 'view_own_reports', 'god_mode', 'manage_mileage', 'submit_mileage', 'view_reviews', 'manage_reviews', 'view_surveys', 'manage_surveys' ] ),
			'hierarchy_level' => 255,
		],
		[
			'slug'            => 'owner',
			'label'           => 'Owner',
			'capabilities'    => wp_json_encode( [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'view_ai_insights', 'manage_mileage', 'submit_mileage', 'view_reviews', 'manage_reviews', 'view_surveys', 'manage_surveys' ] ),
			'hierarchy_level' => 100,
		],
		[
			'slug'            => 'admin',
			'label'           => 'Administrator',
			'capabilities'    => wp_json_encode( [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'view_analytics', 'manage_settings', 'view_ai_insights', 'manage_mileage', 'submit_mileage', 'view_reviews', 'manage_reviews', 'view_surveys', 'manage_surveys' ] ),
			'hierarchy_level' => 90,
		],
		[
			'slug'            => 'supervisor',
			'label'           => 'Supervisor',
			'capabilities'    => wp_json_encode( [ 'view_gm_reports', 'submit_reports', 'view_own_reports', 'view_analytics', 'submit_mileage', 'view_reviews', 'view_surveys' ] ),
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

	// The full-screen Site Pulse app has its own header + sidebar nav, so the WP admin bar is
	// just clutter here — and on mobile it reserves a 46px margin at the top of the page (the
	// gap). Suppress it on every Site Pulse page; that also stops admin-bar.css from loading,
	// so the reserved space disappears at the source (no CSS override needed).
	add_filter( 'show_admin_bar', '__return_false' );

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

// Site Pulse is internal staff tooling, not customer-facing, so it shouldn't inherit the
// client website's branding. Strip the site-specific style + script (compiled style-site.css
// and script-site.js) on every Site Pulse page. Priority 99999 so it runs AFTER both are
// enqueued — the style goes on at 20, but the script rides battleplan_enqueue_footer_scripts
// at 9998, so anything earlier re-enqueues it. Core framework CSS (normalize/grid/navigation)
// is left intact — the Site Pulse UI relies on those structural styles; style-site-pulse.css
// supplies its own look.
add_action( 'wp_enqueue_scripts', function() {
	global $post;
	if ( ! $post || strpos( (string) $post->post_name, 'site-pulse' ) !== 0 ) return;
	wp_dequeue_style(  'battleplan-site' );        // dist/site.min.css (compiled style-site.css)
	wp_dequeue_script( 'battleplan-script-site' ); // script-site.js
}, 99999 );

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

	// Site Pulse styles load here — scoped to the SP pages (login/dashboard), alongside the
	// SP script — rather than via the global deferred CSS bundle. The SP UI only exists on
	// these slugs, so this is both leaner (off every other page) and reliable (no dependence
	// on the combined-bundle signature/cache that previously left it missing).
	$style_path = get_template_directory() . '/style-site-pulse.css';
	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'site-pulse-style',
			get_template_directory_uri() . '/style-site-pulse.css',
			[],
			filemtime( $style_path )
		);
	}

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
		'userCaps'           => site_pulse_effective_caps( $eff_user_id ),
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
		// Whether the REAL user has ever opened the installed (home-screen) app — used to stop
		// nagging installers with the dashboard install card.
		'appInstalled'       => (bool) get_user_meta( $real_user_id, '_sp_app_installed', true ),
		// Google Maps JS key for the (client-side) mileage/toll maps. Prefers a dedicated
		// browser key if set, else the main key. NOTE: this key is exposed to the browser,
		// so it MUST be HTTP-referrer restricted in the Google Cloud Console.
		'mapsKey'            => $post->post_name === 'site-pulse-dashboard'
			? ( site_pulse_get_setting( 'maps_js_api_key', '' ) ?: site_pulse_mileage_google_key() )
			: '',
		// Custom icons registered through the framework `battleplan_icon_map` filter (512-viewBox,
		// fill-based). Exposed to the JS so stat cards etc. can use any registered icon by name
		// (e.g. 'speedometer'). Value = the inner SVG path string for that icon.
		'icons'              => array_map(
			fn( $i ) => is_array( $i ) ? (string) ( $i[0] ?? '' ) : (string) $i,
			apply_filters( 'battleplan_icon_map', [] )
		),
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
function site_pulse_capability_catalog_all(): array {
	return [
		'view_own_reports'        => 'View own reports',
		'submit_reports'          => 'Submit reports',
		'view_gm_reports'         => 'View all GM reports',
		'view_supervisor_reports' => 'View all supervisor reports',
		'view_analytics'    => 'View analytics',
		'view_ai_insights'  => 'View AI insights',
		'view_forms'        => 'View Forms',
		'upload_forms'      => 'Upload Forms',
		'view_reviews'      => 'View reviews',
		'manage_reviews'    => 'Manage reviews &amp; replies',
		'view_surveys'      => 'View customer surveys',
		'manage_surveys'    => 'Manage customer surveys',
		'submit_mileage'    => 'Submit mileage',
		'manage_mileage'    => 'Manage mileage settings',
		'manage_locations'  => 'Manage home bases',
		'manage_users'      => 'Manage users',
		'manage_templates'  => 'Manage report templates',
		'manage_settings'   => 'Manage site settings',
		'manage_roles'      => 'Manage roles',
	];
}

/**
 * The catalog as SHOWN to admins (Tiers editor, per-user override grid) and as
 * granted to god — the full set minus any capability whose module is currently
 * off. Stored role/override caps are always validated against
 * site_pulse_capability_catalog_all() (not this), so toggling a module off never
 * strips a saved capability; it just sleeps until the module is re-enabled.
 */
function site_pulse_capability_catalog(): array {
	$all  = site_pulse_capability_catalog_all();
	$keep = array_flip( site_pulse_filter_caps_by_module( array_keys( $all ) ) );
	return array_intersect_key( $all, $keep );
}

function site_pulse_get_user_profile( int $user_id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('user_profiles') . " WHERE user_id = %d",
		$user_id
	), ARRAY_A );
	return $row ?: null;
}

/**
 * Parse a stored per-user capability-override blob into a clean cap => bool map. Overrides are
 * sparse — only the capabilities that differ from the role default are present. Anything not in
 * the catalog is dropped, so a renamed/removed capability can never linger.
 */
function site_pulse_parse_overrides( $raw ): array {
	if ( empty( $raw ) ) return [];
	$decoded = json_decode( (string) $raw, true );
	if ( ! is_array( $decoded ) ) return [];
	// Validate against the FULL catalog (not the module-filtered one) so a stored
	// override for an off module is preserved, not silently dropped on the next save.
	$catalog = site_pulse_capability_catalog_all();
	$out = [];
	foreach ( $decoded as $cap => $on ) {
		if ( isset( $catalog[ $cap ] ) ) $out[ $cap ] = (bool) $on;
	}
	return $out;
}

/**
 * A user's effective capabilities: their role's capabilities, then per-user overrides applied
 * on top (an override of true grants a cap the role lacks; false revokes one the role grants).
 * This is THE single source of truth — used by site_pulse_user_can() for server-side AJAX gating,
 * by the dashboard template, and by the JS localization — so an override holds everywhere.
 */
function site_pulse_effective_caps( int $user_id ): array {
	// A god sees and can do everything, all the time — the FULL capability catalog, never
	// module-filtered. (Impersonation passes the impersonated user's id here, so this only
	// applies when a god is acting as themselves; an impersonated god gets the user's real caps.)
	if ( site_pulse_is_god( $user_id ) ) {
		return array_merge( array_keys( site_pulse_capability_catalog_all() ), [ 'god_mode' ] );
	}

	$profile = site_pulse_get_user_profile( $user_id );
	if ( ! $profile ) return [];

	$role = site_pulse_get_role( (int) $profile['role_id'] );
	$caps = $role ? ( json_decode( $role['capabilities'], true ) ?: [] ) : [];

	foreach ( site_pulse_parse_overrides( $profile['capability_overrides'] ?? null ) as $cap => $on ) {
		if ( $on ) {
			if ( ! in_array( $cap, $caps, true ) ) $caps[] = $cap;
		} else {
			$caps = array_values( array_diff( $caps, [ $cap ] ) );
		}
	}
	// Caps owned by an off module go inert here — the single runtime chokepoint for
	// site_pulse_user_can() and the dashboard's per-user nav. Stored caps are untouched.
	return site_pulse_filter_caps_by_module( $caps );
}

function site_pulse_user_can( int $user_id, string $capability ): bool {
	return in_array( $capability, site_pulse_effective_caps( $user_id ), true );
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

// Every user BELOW a viewer in the supervisor tree (their whole org chain, not just direct reports).
// Walks supervisor_id downward breadth-first: Joel → Ed & Patrick → Ed's GMs, Patrick's GMs, … A
// $seen set guards against misconfigured cycles. Returns the flat list of descendant user IDs.
function site_pulse_get_descendant_user_ids( int $viewer_id ): array {
	$descendants = [];
	$seen        = [ $viewer_id => true ];
	$queue       = array_map( 'intval', site_pulse_get_team_user_ids( $viewer_id ) );
	while ( $queue ) {
		$uid = (int) array_shift( $queue );
		if ( isset( $seen[ $uid ] ) ) continue;
		$seen[ $uid ]   = true;
		$descendants[]  = $uid;
		foreach ( site_pulse_get_team_user_ids( $uid ) as $child ) {
			$child = (int) $child;
			if ( ! isset( $seen[ $child ] ) ) $queue[] = $child;
		}
	}
	return array_values( array_unique( $descendants ) );
}

// The store IDs a viewer "manages" = the stores of EVERY user beneath them in the supervisor tree
// (recursive, so a regional manager over several supervisors sees all of their stores). Drives the
// "My Stores" default filter in GM Reports. Empty for viewers with no one under them.
function site_pulse_managed_location_ids( int $viewer_id ): array {
	$loc_ids = [];
	foreach ( site_pulse_get_descendant_user_ids( $viewer_id ) as $tid ) {
		$p = site_pulse_get_user_profile( (int) $tid );
		if ( $p && (int) $p['location_id'] ) $loc_ids[] = (int) $p['location_id'];
	}
	return array_values( array_unique( $loc_ids ) );
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

function site_pulse_get_all_locations( bool $active_only = true, bool $stores_only = false ): array {
	global $wpdb;
	$conds = [];
	if ( $active_only ) $conds[] = "status = 'active'";
	if ( $stores_only ) $conds[] = "is_store = 1";
	$where = $conds ? 'WHERE ' . implode( ' AND ', $conds ) : '';
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

			// GM vs Supervisor report — driven by the template's required role — get separate events.
			$tpl   = site_pulse_get_template( (int) $report['template_id'] );
			$event = ( $tpl && ( $tpl['required_role_slug'] ?? '' ) === 'supervisor' )
				? 'supervisor_report_submitted'
				: 'gm_report_submitted';
			site_pulse_dispatch_notification( $event, (int) $report['user_id'], $msg, $report_id, 'report' );
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
	if ( ! empty( $args['location_ids'] ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $args['location_ids'] ), '%d' ) );
		$where[]      = "r.location_id IN ($placeholders)";
		$values       = array_merge( $values, array_map( 'intval', $args['location_ids'] ) );
	}

	$order_by = $args['order_by'] ?? 'r.report_period_end DESC, r.created_at DESC';
	$limit    = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
	$offset   = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

	$sql = "SELECT r.*, l.name AS location_name, u.display_name AS author_name, t.required_role_slug AS template_role
			FROM " . site_pulse_table('reports') . " r
			LEFT JOIN " . site_pulse_table('locations') . " l ON l.id = r.location_id
			LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
			LEFT JOIN " . site_pulse_table('report_templates') . " t ON t.id = r.template_id
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
		"SELECT up.*, u.user_login, u.user_email, u.display_name, r.slug AS role_slug, r.label AS role_label, r.hierarchy_level AS role_rank, l.name AS location_name,
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
# Activity tracking (last-active + app-installed)
--------------------------------------------------------------*/

// Stamp the REAL logged-in user's last-active time (throttled to once / 2 min to avoid write spam).
// "Real" not effective, so a god impersonating someone doesn't mark that person active.
function site_pulse_touch_last_active(): void {
	// Always stamp the REAL logged-in user, never the effective/impersonated one. So a god
	// impersonating Bill marks the GOD active (it's the god actually using the app), and Bill is
	// only marked active when Bill's own session uses it. (Impersonation is a meta flag, not a login
	// switch, so get_current_user_id() stays the god throughout.)
	$uid = get_current_user_id();
	if ( ! $uid ) return;
	$last = (int) get_user_meta( $uid, '_sp_last_active', true );
	if ( time() - $last >= 120 ) update_user_meta( $uid, '_sp_last_active', time() );
}

// Fires on every Site Pulse AJAX call (the SPA polls regularly), so "last active" reflects real use.
add_action( 'init', function () {
	if ( ! is_user_logged_in() || ! wp_doing_ajax() ) return;
	$action = isset( $_REQUEST['action'] ) ? (string) $_REQUEST['action'] : '';
	if ( strpos( $action, 'site_pulse' ) !== 0 && strpos( $action, 'sp_' ) !== 0 ) return;
	site_pulse_touch_last_active();
}, 1 );

// The installed-PWA client pings this when it detects it's running standalone (home-screen app), so
// we know which users have actually installed it (adoption) and can stop nudging them.
add_action( 'wp_ajax_site_pulse_record_app_open', 'site_pulse_ajax_record_app_open' );
function site_pulse_ajax_record_app_open(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid = get_current_user_id();
	if ( $uid ) {
		update_user_meta( $uid, '_sp_app_installed', 1 );
		update_user_meta( $uid, '_sp_app_open_at', time() );
	}
	wp_send_json_success();
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

	// If they signed in with the shared default password, flag them so the dashboard forces
	// a password change before they can use the app. A non-default login clears the flag.
	$default = site_pulse_default_password();
	if ( $default !== '' ) {
		update_user_meta( $user->ID, 'site_pulse_force_pw', hash_equals( $default, (string) $password ) ? '1' : '0' );
	}

	site_pulse_log( 'login', sprintf( '%s logged in', $user->display_name ) );

	wp_send_json_success( [ 'redirect' => home_url( '/site-pulse-dashboard/' ) ] );
}

add_action( 'wp_ajax_site_pulse_logout', 'site_pulse_ajax_logout' );
function site_pulse_ajax_logout(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	wp_logout();
	wp_send_json_success( [ 'redirect' => home_url( '/site-pulse-login/' ) ] );
}

/*--------------------------------------------------------------
# Forced Password Change (shared default password)
--------------------------------------------------------------*/

// The shared temporary password handed out to new users. Anyone whose password is still
// this exact string is forced to pick a new one before they can use the app. Filterable so
// a deployment can change the default, or disable the gate entirely by returning ''.
function site_pulse_default_password(): string {
	return (string) apply_filters( 'site_pulse_default_password', 'Pa55w0rd@2026!' );
}

// Whether $user_id is still on the default password and must change it. The verdict is
// cached in user meta so we only run the (relatively expensive) bcrypt check once: after
// that it's a plain meta read. The cache is reset to '1'/'0' whenever the password changes
// through a Site Pulse path (login, admin reset, or the forced-change screen below).
function site_pulse_must_change_password( int $user_id ): bool {
	if ( ! $user_id ) return false;
	$default = site_pulse_default_password();
	if ( $default === '' ) return false;

	$flag = get_user_meta( $user_id, 'site_pulse_force_pw', true );
	if ( $flag === '' ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return false;
		$flag = wp_check_password( $default, $user->user_pass, $user_id ) ? '1' : '0';
		update_user_meta( $user_id, 'site_pulse_force_pw', $flag );
	}
	return $flag === '1';
}

// Process the new password from the forced-change screen. Runs on template_redirect — before
// any output — so we can reset the auth cookie (wp_set_password invalidates it) and redirect.
// On a validation error we stash a message and let the dashboard re-render the form with it.
add_action( 'template_redirect', 'site_pulse_force_password_change_handler' );
function site_pulse_force_password_change_handler(): void {
	if ( empty( $_POST['sp_force_pw_submit'] ) ) return;
	if ( ! is_user_logged_in() ) return;
	if ( site_pulse_is_impersonating() ) return; // god is just viewing — not their own gate

	$user_id = get_current_user_id();
	if ( ! site_pulse_must_change_password( $user_id ) ) return;

	if ( ! isset( $_POST['sp_force_pw_nonce'] ) || ! wp_verify_nonce( $_POST['sp_force_pw_nonce'], 'sp_force_pw_' . $user_id ) ) {
		$GLOBALS['sp_force_pw_error'] = 'Your session expired. Please try again.';
		return;
	}

	// Field names dodge `password` (WP Engine's WAF strips it) and arrive slash-escaped.
	$new  = (string) wp_unslash( $_POST['sp_new_pass']  ?? '' );
	$new2 = (string) wp_unslash( $_POST['sp_new_pass2'] ?? '' );

	if ( strlen( $new ) < 8 ) {
		$GLOBALS['sp_force_pw_error'] = 'Your new password must be at least 8 characters.';
		return;
	}
	if ( $new !== $new2 ) {
		$GLOBALS['sp_force_pw_error'] = "The two passwords don't match.";
		return;
	}
	if ( hash_equals( site_pulse_default_password(), $new ) ) {
		$GLOBALS['sp_force_pw_error'] = 'Please choose a password different from the temporary one.';
		return;
	}

	// wp_set_password() invalidates the current auth cookie, so re-issue one to stay logged in.
	wp_set_password( $new, $user_id );
	update_user_meta( $user_id, 'site_pulse_force_pw', '0' );
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
	site_pulse_log( 'password_changed', 'Replaced the temporary password with a new one' );

	wp_safe_redirect( home_url( '/site-pulse-dashboard/' ) );
	exit;
}

// The blocking "choose a new password" screen, styled like the Site Pulse login. Rendered by
// the dashboard page (returns its HTML) whenever the logged-in user is still on the default.
function site_pulse_render_force_password_screen(): string {
	$app_name = site_pulse_get_setting( 'app_name', 'Site Pulse' );
	$logo_url = site_pulse_get_setting( 'login_logo_url', '' );
	$error    = $GLOBALS['sp_force_pw_error'] ?? '';
	$nonce    = wp_create_nonce( 'sp_force_pw_' . get_current_user_id() );
	$logout   = wp_logout_url( home_url( '/site-pulse-login/' ) );

	$html  = '<div id="sp-login-wrap"><div class="sp-login-box">';
	$html .=   '<div class="sp-login-header">';
	if ( $logo_url ) {
		$html .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $app_name ) . '" class="sp-login-logo">';
	}
	$html .=     '<h1>Choose a New Password</h1>';
	$html .=     '<p>For your security, please replace the temporary password before you continue.</p>';
	$html .=   '</div>';

	$html .=   '<form id="sp-force-pw-form" method="post" action="" autocomplete="off" novalidate>';
	$html .=     '<input type="hidden" name="sp_force_pw_submit" value="1">';
	$html .=     '<input type="hidden" name="sp_force_pw_nonce" value="' . esc_attr( $nonce ) . '">';
	$html .=     '<div class="sp-form-group">';
	$html .=       '<label for="sp-new-pass">New Password</label>';
	$html .=       '<input type="password" id="sp-new-pass" name="sp_new_pass" autocomplete="new-password" minlength="8" required placeholder="At least 8 characters">';
	$html .=     '</div>';
	$html .=     '<div class="sp-form-group">';
	$html .=       '<label for="sp-new-pass2">Confirm New Password</label>';
	$html .=       '<input type="password" id="sp-new-pass2" name="sp_new_pass2" autocomplete="new-password" minlength="8" required placeholder="Re-enter your new password">';
	$html .=     '</div>';
	if ( $error ) {
		$html .= '<div class="sp-form-error" role="alert" style="display:block;">' . esc_html( $error ) . '</div>';
	}
	$html .=     '<button type="submit" class="sp-btn sp-btn-primary sp-btn-full">Save New Password</button>';
	$html .=   '</form>';
	$html .=   '<p class="sp-login-foot"><a href="' . esc_url( $logout ) . '">Log out</a></p>';
	$html .= '</div></div>';

	return $html;
}

add_action( 'wp_ajax_site_pulse_save_report', 'site_pulse_ajax_save_report' );
function site_pulse_ajax_save_report(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id     = site_pulse_effective_user_id();
	$template_id = (int) ( $_POST['template_id'] ?? 0 );
	$report_id   = (int) ( $_POST['report_id'] ?? 0 );
	// WordPress slash-escapes all superglobals, so an apostrophe arrives as \'.
	// Unslash before sanitizing/saving or the backslash gets stored (HGTV\'s).
	$answers     = wp_unslash( $_POST['answers'] ?? [] );
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

	// "My Stores" filter — limit to the stores the viewer manages (their team's stores). Resolved
	// server-side so the client can't widen it. No managed stores → match nothing (id 0).
	if ( ! empty( $_POST['mine'] ) ) {
		$managed = site_pulse_managed_location_ids( $user_id );
		$args['location_ids'] = $managed ?: [ 0 ];
	}

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

	// Attach activity + app-install state for the "Last Active" / adoption columns.
	foreach ( $users as &$u ) {
		$uid = (int) ( $u['user_id'] ?? 0 );
		$u['last_active']   = $uid ? (int) get_user_meta( $uid, '_sp_last_active', true ) : 0;
		$u['app_installed'] = $uid ? ( get_user_meta( $uid, '_sp_app_installed', true ) ? 1 : 0 ) : 0;
	}
	unset( $u );

	global $wpdb;
	// Shared (non-private) approved locations for the Home Base picker. Private
	// work-from-home residences are set via the separate address field, not this list.
	$mileage_locations = $wpdb->get_results(
		"SELECT id, name, location_type FROM " . site_pulse_table('mileage_locations') . "
		 WHERE status = 'approved' AND is_private = 0 ORDER BY location_type, name",
		ARRAY_A
	) ?: [];

	wp_send_json_success( [ 'users' => $users, 'roles' => $roles, 'locations' => $locations, 'mileage_locations' => $mileage_locations, 'catalog' => site_pulse_capability_catalog() ] );
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

	// Per-user capability overrides — a sparse {cap: bool} map of only the caps that differ from
	// the role default. Validated against the catalog; an empty map clears all overrides (NULL).
	if ( isset( $_POST['capability_overrides'] ) ) {
		$raw     = json_decode( (string) wp_unslash( $_POST['capability_overrides'] ), true );
		// Full catalog so overrides for an off module are kept, not wiped on save.
		$catalog = site_pulse_capability_catalog_all();
		$clean   = [];
		if ( is_array( $raw ) ) {
			foreach ( $raw as $cap => $on ) {
				if ( isset( $catalog[ $cap ] ) ) $clean[ $cap ] = (bool) $on;
			}
		}
		$updates['capability_overrides'] = $clean ? wp_json_encode( $clean ) : null;
	}

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
		// If an admin (re)sets the shared default, force a change at next login; otherwise clear.
		$default = site_pulse_default_password();
		if ( $default !== '' ) {
			update_user_meta( $user_id, 'site_pulse_force_pw', hash_equals( $default, (string) wp_unslash( $new_pass ) ) ? '1' : '0' );
		}
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
	// WordPress slash-escapes every superglobal, so an apostrophe arrives as \' — wp_unslash()
	// before sanitizing or names like "Babe's Chicken" get stored as "Babe\'s Chicken".
	$data = [
		'name'          => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
		'location_type' => sanitize_text_field( wp_unslash( $_POST['location_type'] ?? '' ) ),
		// Whether this is a store (restaurant). Stores appear in the report location filters; non-store
		// locations (accounting office, storage, vendor) are kept off those lists. New locations default
		// to store unless the checkbox is cleared.
		'is_store'      => ( isset( $_POST['is_store'] ) ? ( (int) $_POST['is_store'] ? 1 : 0 ) : 1 ),
		'address'       => sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) ),
		'city'          => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
		'state'         => sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) ),
		'zip'           => sanitize_text_field( wp_unslash( $_POST['zip'] ?? '' ) ),
		'phone'         => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
		// Store/accounting number used to charge mileage to this location ("Charge To" on entries).
		'location_number' => sanitize_text_field( wp_unslash( $_POST['location_number'] ?? '' ) ),
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
		'description'        => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ),
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
		wp_send_json_error( [ 'message' => 'Role name is required.' ] );
	}

	// Only ever store known capabilities; silently drop anything not in the catalog.
	// Validate against the FULL catalog so a role keeps caps for a module that
	// happens to be off right now (they'd otherwise be stripped on every save).
	$posted = (array) ( $_POST['capabilities'] ?? [] );
	$caps   = array_values( array_intersect( array_map( 'strval', $posted ), array_keys( site_pulse_capability_catalog_all() ) ) );

	if ( $id ) {
		$role = site_pulse_get_role( $id );
		if ( ! $role || $role['slug'] === 'god' ) {
			wp_send_json_error( [ 'message' => 'That role cannot be edited.' ] );
		}
		// Label + capabilities only — slug and rank are stable identity, untouched.
		$wpdb->update( site_pulse_table('roles'),
			[ 'label' => $label, 'capabilities' => wp_json_encode( $caps ) ],
			[ 'id' => $id ]
		);
		site_pulse_log( 'tier_updated', sprintf( 'Updated role "%s" (#%d)', $label, $id ) );
		wp_send_json_success( [ 'message' => 'Role saved.' ] );
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
	site_pulse_log( 'tier_created', sprintf( 'Created role "%s" (%s)', $label, $slug ) );
	wp_send_json_success( [ 'message' => 'Role created.', 'id' => (int) $wpdb->insert_id ] );
}

add_action( 'wp_ajax_site_pulse_admin_delete_role', 'site_pulse_ajax_admin_delete_role' );
function site_pulse_ajax_admin_delete_role(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	global $wpdb;
	$id   = (int) ( $_POST['id'] ?? 0 );
	$role = $id ? site_pulse_get_role( $id ) : null;
	if ( ! $role || $role['slug'] === 'god' ) {
		wp_send_json_error( [ 'message' => 'That role cannot be deleted.' ] );
	}

	$members = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . site_pulse_table('user_profiles') . " WHERE role_id = %d", $id
	) );
	if ( $members ) {
		wp_send_json_error( [ 'message' => sprintf( 'Reassign this role\'s %d member%s to another role first.', $members, $members === 1 ? '' : 's' ) ] );
	}

	$wpdb->delete( site_pulse_table('roles'), [ 'id' => $id ] );
	site_pulse_log( 'tier_deleted', sprintf( 'Deleted role "%s" (%s)', $role['label'], $role['slug'] ) );
	wp_send_json_success( [ 'message' => 'Role deleted.' ] );
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
	wp_send_json_success( [ 'message' => 'Roles reordered.' ] );
}


/*--------------------------------------------------------------
# Review Filters AJAX
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_review_filters', 'site_pulse_ajax_get_review_filters' );
function site_pulse_ajax_get_review_filters(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id = site_pulse_effective_user_id();
	// The full store list — every viewer can drill into any store. Reports are filed for stores, so
	// this excludes non-store locations (accounting office, storage yard, vendor) via stores_only.
	$locations = site_pulse_get_all_locations( true, true );

	// The stores this viewer manages (their team's stores). Non-empty → the GM Reports filter shows a
	// "My Stores" option as the default. Org-wide viewers (god/owner/admin) have no team and just get
	// the normal "All Locations" default.
	$org_wide = site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_users' );
	$my_location_ids = $org_wide ? [] : site_pulse_managed_location_ids( $user_id );

	// Submitters the viewer is allowed to filter by (GMs and/or supervisors, per their caps).
	$users = [];
	foreach ( site_pulse_visible_report_user_ids( $user_id ) as $tid ) {
		if ( (int) $tid === (int) $user_id ) continue; // self isn't a "filter by" option
		$u = get_userdata( $tid );
		if ( $u ) $users[] = [ 'user_id' => $tid, 'display_name' => $u->display_name ];
	}

	wp_send_json_success( [ 'locations' => $locations, 'users' => $users, 'my_location_ids' => $my_location_ids ] );
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

// Clicking a notification dismisses it — read AND archived, so it leaves the bell (the panel only
// lists is_archived=0). Scoped to the owner.
add_action( 'wp_ajax_site_pulse_mark_notification_read', 'site_pulse_ajax_mark_notification_read' );
function site_pulse_ajax_mark_notification_read(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( $id ) {
		global $wpdb;
		$wpdb->update(
			site_pulse_table('notifications'),
			[ 'is_read' => 1, 'is_archived' => 1 ],
			[ 'id' => $id, 'user_id' => site_pulse_effective_user_id() ]
		);
	}
	wp_send_json_success();
}

// "Mark all read" clears the bell — archives every still-showing notification for the user.
add_action( 'wp_ajax_site_pulse_mark_notifications_read', 'site_pulse_ajax_mark_notifications_read' );
function site_pulse_ajax_mark_notifications_read(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	global $wpdb;
	$wpdb->update(
		site_pulse_table('notifications'),
		[ 'is_read' => 1, 'is_archived' => 1 ],
		[ 'user_id' => $user_id, 'is_archived' => 0 ]
	);
	wp_send_json_success();
}

function site_pulse_notify( int $user_id, string $type, string $message, int $related_id = 0, string $related_type = '', bool $on_dashboard = false ): void {
	global $wpdb;
	$wpdb->insert( site_pulse_table('notifications'), [
		'user_id'      => $user_id,
		'type'         => $type,
		'message'      => $message,
		'related_id'   => $related_id,
		'related_type' => $related_type,
		'is_read'      => 0,
		'is_archived'  => 0,
		'on_dashboard' => $on_dashboard ? 1 : 0,
		'created_at'   => current_time( 'mysql' ),
	] );
}

/*--------------------------------------------------------------
# Notification routing (Settings → Notifications matrix)
--------------------------------------------------------------*/

// The notifiable situations (rows of the Settings → Notifications matrix).
function site_pulse_notification_events(): array {
	return [
		'gm_report_submitted'         => 'GM report submitted',
		'supervisor_report_submitted' => 'Supervisor report submitted',
		'survey_received'             => 'New customer survey submitted',
		'action_pending'              => 'Action items created from a new report',
		'action_followup'             => 'Action item needs follow-up (not fully resolved)',
		'action_resolved'             => 'Action item resolved',
		'action_urgent'               => 'High-priority action item approved',
		'period_ending_2days'         => 'Expense period ends in 2 days (not yet submitted)',
		'period_ending_tomorrow'      => 'Expense period ends tomorrow (not yet submitted)',
		'period_ends_today'           => 'Expense period ends today (not yet submitted)',
		'mileage_pending'             => 'New mileage location proposed (needs approval)',
		'mileage_approved'            => 'Mileage location approved',
		'mileage_rejected'            => 'Mileage location rejected',
	];
}

// The matrix columns. 'dashboard' is a DELIVERY channel (not a recipient) — when on, the bell
// notifications for that event ALSO surface as a dismissible banner on the dashboard. The rest are
// recipients: 'gm' = the event's subject person; 'supervisor' = that person's direct supervisor;
// the others broadcast to everyone holding that Site Pulse role.
function site_pulse_notification_columns(): array {
	return [
		'dashboard'       => 'Dashboard',
		'push'            => 'Push',
		'gm'              => 'GM',
		'all_gms'         => 'All GMs',
		'supervisor'      => 'Supervisor',
		'all_supervisors' => 'All Supervisors',
		'admin'           => 'Admin',
		'managers'        => 'Managers',
		'owners'          => 'Owners',
	];
}

// Defaults mirror the original hard-coded recipients, so behavior is unchanged until an admin edits.
function site_pulse_notification_defaults(): array {
	return [
		'gm_report_submitted'         => [ 'supervisor' => 1 ],
		'supervisor_report_submitted' => [ 'supervisor' => 1 ],
		'survey_received'             => [ 'gm' => 1, 'supervisor' => 1 ],
		'action_pending'              => [ 'gm' => 1 ],
		'action_followup'             => [ 'gm' => 1, 'supervisor' => 1 ],
		'action_resolved'             => [ 'supervisor' => 1 ],
		'action_urgent'               => [ 'supervisor' => 1 ],
		'period_ending_2days'         => [ 'gm' => 1 ],
		'period_ending_tomorrow'      => [ 'gm' => 1 ],
		'period_ends_today'           => [ 'gm' => 1 ],
		'mileage_pending'             => [ 'admin' => 1, 'owners' => 1 ],
		'mileage_approved'            => [ 'gm' => 1 ],
		'mileage_rejected'            => [ 'gm' => 1 ],
	];
}

// The effective matrix: saved overrides per event, falling back to defaults for any event not saved.
function site_pulse_get_notification_routing(): array {
	$raw   = site_pulse_get_setting( 'notification_routing', '' );
	$saved = $raw !== '' ? json_decode( $raw, true ) : null;
	$defaults = site_pulse_notification_defaults();
	$out = [];
	foreach ( site_pulse_notification_events() as $ev => $label ) {
		$out[ $ev ] = ( is_array( $saved ) && isset( $saved[ $ev ] ) && is_array( $saved[ $ev ] ) )
			? $saved[ $ev ]
			: ( $defaults[ $ev ] ?? [] );
	}
	return $out;
}

// Fan a notification out to whichever recipient groups the matrix enables for this event.
// $subject_user_id is the person the event is "about" (report author, action-item owner, the
// proposer/creator of a mileage location) — it drives the contextual 'gm' and 'supervisor' columns.
function site_pulse_dispatch_notification( string $event, int $subject_user_id, string $message, int $related_id = 0, string $related_type = '' ): void {
	$cols = site_pulse_get_notification_routing()[ $event ] ?? [];
	if ( empty( $cols ) ) return;

	// 'dashboard' and 'push' are channel flags, not recipients. Dashboard elevates the bell entry to a
	// banner; push fires a Web Push wake. The recipient resolution below only reads role/contextual keys.
	$on_dashboard = ! empty( $cols['dashboard'] );
	$do_push      = ! empty( $cols['push'] );

	$recipients = [];
	if ( ! empty( $cols['gm'] ) && $subject_user_id ) {
		$recipients[] = $subject_user_id;
	}
	if ( ! empty( $cols['supervisor'] ) && $subject_user_id ) {
		$p = site_pulse_get_user_profile( $subject_user_id );
		if ( $p && (int) $p['supervisor_id'] ) $recipients[] = (int) $p['supervisor_id'];
	}
	if ( ! empty( $cols['all_gms'] ) )         $recipients = array_merge( $recipients, site_pulse_user_ids_with_role( 'manager' ) );
	if ( ! empty( $cols['all_supervisors'] ) ) $recipients = array_merge( $recipients, site_pulse_user_ids_with_role( 'supervisor' ) );
	if ( ! empty( $cols['admin'] ) )           $recipients = array_merge( $recipients, site_pulse_user_ids_with_role( 'admin' ) );
	if ( ! empty( $cols['managers'] ) )        $recipients = array_merge( $recipients, site_pulse_user_ids_with_role( 'non-store-manager' ) );
	if ( ! empty( $cols['owners'] ) )          $recipients = array_merge( $recipients, site_pulse_user_ids_with_role( 'owner' ) );

	$recipients = array_values( array_unique( array_map( 'intval', array_filter( $recipients ) ) ) );
	foreach ( $recipients as $rid ) {
		site_pulse_notify( $rid, $event, $message, $related_id, $related_type, $on_dashboard );
		if ( $do_push && function_exists( 'site_pulse_push_send' ) ) site_pulse_push_send( $rid );
	}
}

// The current user's un-acknowledged dashboard banner messages (notifications flagged on_dashboard
// via the matrix' "Dashboard" column), newest first.
function site_pulse_get_dashboard_messages( int $user_id ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT id, message, related_id, related_type, created_at
		 FROM " . site_pulse_table('notifications') . "
		 WHERE user_id = %d AND on_dashboard = 1 AND is_archived = 0
		 ORDER BY created_at DESC",
		$user_id
	), ARRAY_A ) ?: [];
}

// Acknowledge (X) a dashboard banner — clears its dashboard flag and marks it read. Owner-only.
add_action( 'wp_ajax_site_pulse_ack_dashboard_message', 'site_pulse_ajax_ack_dashboard_message' );
function site_pulse_ajax_ack_dashboard_message(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$id      = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid id.' ] );
	global $wpdb;
	$wpdb->update(
		site_pulse_table('notifications'),
		[ 'on_dashboard' => 0, 'is_read' => 1 ],
		[ 'id' => $id, 'user_id' => $user_id ]
	);
	wp_send_json_success( [ 'message' => 'Acknowledged.' ] );
}

add_action( 'wp_ajax_site_pulse_get_notification_settings', 'site_pulse_ajax_get_notification_settings' );
function site_pulse_ajax_get_notification_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;
	wp_send_json_success( [
		'events'         => site_pulse_notification_events(),
		'columns'        => site_pulse_notification_columns(),
		'routing'        => site_pulse_get_notification_routing(),
		'messages_email' => site_pulse_get_setting( 'messages_email_enabled', '0' ),
		'push_enabled'   => site_pulse_get_setting( 'push_enabled', '0' ),
	] );
}

add_action( 'wp_ajax_site_pulse_save_notification_settings', 'site_pulse_ajax_save_notification_settings' );
function site_pulse_ajax_save_notification_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	$in = json_decode( wp_unslash( $_POST['routing'] ?? '' ), true );
	if ( ! is_array( $in ) ) wp_send_json_error( [ 'message' => 'Invalid data.' ] );

	$clean = [];
	foreach ( site_pulse_notification_events() as $ev => $label ) {
		$clean[ $ev ] = [];
		$row = is_array( $in[ $ev ] ?? null ) ? $in[ $ev ] : [];
		foreach ( site_pulse_notification_columns() as $ck => $cl ) {
			if ( ! empty( $row[ $ck ] ) ) $clean[ $ev ][ $ck ] = 1;
		}
	}
	site_pulse_set_setting( 'notification_routing', wp_json_encode( $clean ) );
	wp_send_json_success( [ 'message' => 'Saved.' ] );
}

// Current expense/mileage reporting period [start,end] from the configured anchor + length (the
// Mileage Settings period), or null if not configured. Periods tile forward from the anchor date.
function site_pulse_current_expense_period(): ?array {
	$length = (int) site_pulse_get_setting( 'mileage_period_length', '0' );
	$anchor = site_pulse_get_setting( 'mileage_period_anchor', '' );
	if ( $length < 1 || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $anchor ) ) return null;

	$day       = 86400;
	$anchor_ts = strtotime( $anchor . ' 00:00:00' );
	$today_ts  = strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' );
	if ( $anchor_ts === false || $today_ts === false ) return null;

	$idx      = (int) floor( ( $today_ts - $anchor_ts ) / ( $day * $length ) );
	$start_ts = $anchor_ts + $idx * $length * $day;
	$end_ts   = $start_ts + ( $length - 1 ) * $day;
	return [ 'start' => gmdate( 'Y-m-d', $start_ts ), 'end' => gmdate( 'Y-m-d', $end_ts ) ];
}

// PLACEHOLDER — the user IDs who still owe an expense report for the given period. The expense-report
// SUBMIT flow doesn't exist yet, so this returns [] (no reminders fire). When that flow is built,
// return the people who CAN submit (e.g. submit_mileage cap) MINUS those who already submitted an
// expense report whose period matches [$period_start,$period_end].
function site_pulse_users_pending_expense_report( string $period_start, string $period_end ): array {
	return [];
}

// Daily cron — fires the period-ending reminders to whoever still owes an expense report. Wired now
// (events + matrix + dispatch); no-ops until the placeholder above returns real pending users.
add_action( 'site_pulse_expense_period_reminder', 'site_pulse_run_expense_period_reminders' );
function site_pulse_run_expense_period_reminders(): void {
	$period = site_pulse_current_expense_period();
	if ( ! $period ) return;

	$today_ts   = strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' );
	$end_ts     = strtotime( $period['end'] . ' 00:00:00' );
	$days_until  = (int) round( ( $end_ts - $today_ts ) / 86400 );

	$event = '';
	if     ( $days_until === 2 ) $event = 'period_ending_2days';
	elseif ( $days_until === 1 ) $event = 'period_ending_tomorrow';
	elseif ( $days_until === 0 ) $event = 'period_ends_today';
	if ( ! $event ) return;

	$pending = site_pulse_users_pending_expense_report( $period['start'], $period['end'] );
	if ( empty( $pending ) ) return;

	$when = [ 'period_ending_2days' => 'in 2 days', 'period_ending_tomorrow' => 'tomorrow', 'period_ends_today' => 'today' ][ $event ];
	$msg  = sprintf( 'Reminder: the expense report period ends %s and you haven\'t submitted yet.', $when );
	foreach ( $pending as $uid ) {
		site_pulse_dispatch_notification( $event, (int) $uid, $msg, 0, 'expense_period' );
	}
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

function site_pulse_call_claude( string $prompt, string $system = '', array $opts = [], &$debug = null ): ?string {
	$debug = null;
	$api_key = site_pulse_get_api_key();
	if ( ! $api_key ) { $debug = 'No API key configured.'; return null; }

	$messages = [ [ 'role' => 'user', 'content' => $prompt ] ];

	// Defaults preserved for existing callers; toll reconciliation passes a larger max_tokens.
	// Model: claude-sonnet-4-6 (the prior claude-sonnet-4-20250514 now 404s — retired on this account).
	$body = [
		'model'      => $opts['model']      ?? 'claude-sonnet-4-6',
		'max_tokens' => $opts['max_tokens'] ?? 2048,
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
		'timeout' => $opts['timeout'] ?? 30,
	] );

	if ( is_wp_error( $response ) ) {
		$debug = 'Request failed: ' . $response->get_error_message();
		site_pulse_log( 'ai_error', 'Claude API error: ' . $response->get_error_message() );
		return null;
	}

	$status = wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $status !== 200 || empty( $data['content'][0]['text'] ) ) {
		$api_msg = $data['error']['message'] ?? wp_remote_retrieve_response_message( $response );
		$debug   = "HTTP $status" . ( $api_msg ? ": $api_msg" : '' );
		site_pulse_log( 'ai_error', 'Claude API returned status ' . $status, [ 'response' => $data ] );
		bp_ai_model_alert( (int) $status, $data, $body['model'], 'Site Pulse AI' );
		return null;
	}

	return $data['content'][0]['text'];
}

/**
 * Vision sibling of site_pulse_call_claude: one image (base64) + a text prompt → model reply.
 * Used by the reusable receipt scanner; reused by any future "read this photo" feature.
 */
function site_pulse_call_claude_vision( string $image_b64, string $media_type, string $prompt, string $system = '', array $opts = [], &$debug = null ): ?string {
	$debug   = null;
	$api_key = site_pulse_get_api_key();
	if ( ! $api_key ) { $debug = 'No API key configured.'; return null; }

	$body = [
		'model'      => $opts['model']      ?? 'claude-sonnet-4-6',
		'max_tokens' => $opts['max_tokens'] ?? 1024,
		'messages'   => [ [
			'role'    => 'user',
			'content' => [
				[ 'type' => 'image', 'source' => [ 'type' => 'base64', 'media_type' => $media_type, 'data' => $image_b64 ] ],
				[ 'type' => 'text', 'text' => $prompt ],
			],
		] ],
	];
	if ( $system ) $body['system'] = $system;

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		],
		'body'    => wp_json_encode( $body ),
		'timeout' => $opts['timeout'] ?? 45,
	] );

	if ( is_wp_error( $response ) ) {
		$debug = 'Request failed: ' . $response->get_error_message();
		site_pulse_log( 'ai_error', 'Claude vision error: ' . $response->get_error_message() );
		return null;
	}

	$status = wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $status !== 200 || empty( $data['content'][0]['text'] ) ) {
		$api_msg = $data['error']['message'] ?? wp_remote_retrieve_response_message( $response );
		$debug   = "HTTP $status" . ( $api_msg ? ": $api_msg" : '' );
		site_pulse_log( 'ai_error', 'Claude vision returned status ' . $status, [ 'response' => $data ] );
		bp_ai_model_alert( (int) $status, $data, $body['model'], 'Site Pulse AI' );
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
			$from = ! empty( $item['created_at'] ) ? date_i18n( 'M j, Y', strtotime( (string) $item['created_at'] ) ) : 'earlier';
			$report_text .= "- [{$item['category']}] {$item['description']} (from {$from})\n";
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

		site_pulse_dispatch_notification( 'action_pending', (int) $report['user_id'], $msg, $report_id, 'action_item' );
		site_pulse_log( 'action_items_pending', $msg, [ 'report_id' => $report_id, 'count' => $count ] );
	}

	return $count;
}


/**
 * One-time recovery: recently-submitted reports that ended up with ZERO action items (e.g. the
 * reports submitted while the generator was crashing). Returns up to $limit report ids that still
 * need processing, plus the total still pending — skipping any already handled by a prior batch
 * (tracked in the `site_pulse_action_backfill_seen` option) so a report that legitimately yields
 * no items isn't retried forever.
 */
function site_pulse_backfill_candidates( int $limit ): array {
	global $wpdb;
	$seen = get_option( 'site_pulse_action_backfill_seen', [] );
	if ( ! is_array( $seen ) ) $seen = [];

	// Look back a year, and fall back to created_at when submitted_at was never stamped, so older
	// (but still recent) reports that missed generation aren't excluded.
	$cutoff  = date( 'Y-m-d H:i:s', strtotime( '-365 days' ) );
	$reports = $wpdb->get_results( $wpdb->prepare(
		"SELECT r.id FROM " . site_pulse_table('reports') . " r
		 LEFT JOIN " . site_pulse_table('action_items') . " ai ON ai.report_id = r.id
		 WHERE r.status = 'submitted' AND COALESCE( r.submitted_at, r.created_at ) >= %s
		 GROUP BY r.id
		 HAVING COUNT( ai.id ) = 0
		 ORDER BY COALESCE( r.submitted_at, r.created_at ) DESC",
		$cutoff
	), ARRAY_A ) ?: [];

	$ids = [];
	foreach ( $reports as $r ) {
		if ( ! in_array( (int) $r['id'], $seen, true ) ) $ids[] = (int) $r['id'];
	}
	return [ 'ids' => array_slice( $ids, 0, max( 1, $limit ) ), 'total' => count( $ids ) ];
}

// God-only, batched backfill. Each call processes a few reports (one Claude request each) and
// reports how many remain, so the client loops until done without tripping request timeouts.
add_action( 'wp_ajax_site_pulse_backfill_action_items', 'site_pulse_ajax_backfill_action_items' );
function site_pulse_ajax_backfill_action_items(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god( get_current_user_id() ) ) wp_send_json_error( [ 'message' => 'Only an Odinson can run the backfill.' ] );

	@set_time_limit( 120 );
	$batch = site_pulse_backfill_candidates( 4 );

	$seen = get_option( 'site_pulse_action_backfill_seen', [] );
	if ( ! is_array( $seen ) ) $seen = [];

	$created = 0; $processed = 0;
	foreach ( $batch['ids'] as $rid ) {
		$created += site_pulse_create_action_items_from_report( $rid );
		$seen[]   = $rid;
		$processed++;
	}
	update_option( 'site_pulse_action_backfill_seen', array_values( array_unique( array_map( 'intval', $seen ) ) ), false );

	$remaining = max( 0, (int) $batch['total'] - $processed );
	wp_send_json_success( [
		'processed' => $processed,
		'created'   => $created,
		'remaining' => $remaining,
		'done'      => $remaining === 0,
	] );
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

	$sql = "SELECT ai.*, l.name AS location_name, u.display_name AS user_name, cu.display_name AS creator_name
			FROM " . site_pulse_table('action_items') . " ai
			LEFT JOIN " . site_pulse_table('locations') . " l ON l.id = ai.location_id
			LEFT JOIN {$wpdb->users} u ON u.ID = ai.user_id
			LEFT JOIN {$wpdb->users} cu ON cu.ID = ai.created_by
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
	if ( ! empty( $_POST['mine'] ) ) {
		// Default to-do view: just my own items.
		$args['user_id'] = $user_id;
	} elseif ( ! empty( $_POST['user_id'] ) && in_array( (int) $_POST['user_id'], $visible, true ) ) {
		$args['user_id'] = (int) $_POST['user_id'];
	} else {
		$args['user_ids'] = $visible;
		if ( ! empty( $_POST['location_id'] ) ) $args['location_id'] = (int) $_POST['location_id'];
	}

	if ( ! empty( $_POST['status'] ) ) $args['status'] = sanitize_text_field( $_POST['status'] );

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
	$note    = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

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
		site_pulse_dispatch_notification( 'action_followup', (int) $item['user_id'], $msg, $item_id, 'action_item' );

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

	site_pulse_dispatch_notification( 'action_resolved', (int) $item['user_id'], $msg, $item_id, 'action_item' );

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

// Shared: can $me touch this action item? (their own, or one belonging to someone they can see).
function sp_action_item_editable_by( array $item, int $me ): bool {
	if ( (int) $item['user_id'] === $me ) return true;
	return in_array( (int) $item['user_id'], site_pulse_visible_report_user_ids( $me ), true );
}

// Add Note: append a note, keep a running notes list (seeded with the original item), and let the AI
// rewrite the item description to reflect the latest state. The item STAYS open.
add_action( 'wp_ajax_site_pulse_add_action_note', 'site_pulse_ajax_add_action_note' );
function site_pulse_ajax_add_action_note(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me      = site_pulse_effective_user_id();
	$item_id = (int) ( $_POST['item_id'] ?? 0 );
	$note    = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
	if ( ! $item_id )       wp_send_json_error( [ 'message' => 'Invalid item.' ] );
	if ( $note === '' )     wp_send_json_error( [ 'message' => 'Please enter a note.' ] );

	global $wpdb;
	$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . site_pulse_table( 'action_items' ) . " WHERE id = %d", $item_id ), ARRAY_A );
	if ( ! $item )                              wp_send_json_error( [ 'message' => 'Item not found.' ] );
	if ( ! sp_action_item_editable_by( $item, $me ) ) wp_send_json_error( [ 'message' => 'Not allowed.' ] );

	$meta  = $item['meta'] ? json_decode( $item['meta'], true ) : [];
	if ( ! is_array( $meta ) ) $meta = [];
	$notes = ( isset( $meta['notes'] ) && is_array( $meta['notes'] ) ) ? $meta['notes'] : [];
	if ( ! $notes ) $notes[] = (string) $item['description']; // seed list with the original item
	$notes[] = $note;
	$meta['notes'] = $notes;

	// Rewrite the item from the original + all notes (falls back to the existing text if AI is off).
	$new_desc = $item['description'];
	if ( site_pulse_get_api_key() ) {
		$prompt  = "Action item: " . $notes[0] . "\n\nNotes added since (oldest first):\n";
		foreach ( array_slice( $notes, 1 ) as $n ) $prompt .= "- " . $n . "\n";
		$prompt .= "\nRewrite the action item to reflect the latest state.";
		$ai = site_pulse_call_claude( $prompt, site_pulse_prompt_rewrite_action_item() );
		if ( $ai ) {
			$ai = trim( wp_strip_all_tags( $ai ) );
			$ai = trim( $ai, " \t\n\r\0\x0B\"'" );
			if ( $ai !== '' ) $new_desc = $ai;
		}
	}

	$wpdb->update( site_pulse_table( 'action_items' ), [
		'description' => sanitize_text_field( $new_desc ),
		'meta'        => wp_json_encode( $meta ),
		'updated_at'  => current_time( 'mysql' ),
	], [ 'id' => $item_id ] );

	wp_send_json_success();
}

// Item Complete: mark done (moves it to the completed/archived view). Urgent items fire the
// configured notification.
add_action( 'wp_ajax_site_pulse_complete_action_item', 'site_pulse_ajax_complete_action_item' );
function site_pulse_ajax_complete_action_item(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me      = site_pulse_effective_user_id();
	$item_id = (int) ( $_POST['item_id'] ?? 0 );
	if ( ! $item_id ) wp_send_json_error( [ 'message' => 'Invalid item.' ] );

	global $wpdb;
	$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . site_pulse_table( 'action_items' ) . " WHERE id = %d", $item_id ), ARRAY_A );
	if ( ! $item )                              wp_send_json_error( [ 'message' => 'Item not found.' ] );
	if ( ! sp_action_item_editable_by( $item, $me ) ) wp_send_json_error( [ 'message' => 'Not allowed.' ] );

	$now = current_time( 'mysql' );
	$wpdb->update( site_pulse_table( 'action_items' ), [
		'status'      => 'resolved',
		'resolved_at' => $now,
		'resolved_by' => $me,
		'updated_at'  => $now,
	], [ 'id' => $item_id ] );

	if ( ( $item['priority'] ?? '' ) === 'high' ) {
		$user = get_userdata( $me );
		$msg  = sprintf( '%s completed an urgent action item: %s', $user ? $user->display_name : 'Someone', $item['description'] );
		site_pulse_dispatch_notification( 'action_resolved', (int) $item['user_id'], $msg, $item_id, 'action_item' );
	}

	site_pulse_log( 'action_item_completed', 'Action item completed', [ 'item_id' => $item_id ] );
	wp_send_json_success();
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
		$user = get_userdata( (int) $item['user_id'] );
		$loc  = site_pulse_get_location( (int) $item['location_id'] );

		$urgent_msg = sprintf( 'URGENT — high-priority item from %s (%s): %s',
			$user ? $user->display_name : 'Unknown',
			$loc ? $loc['name'] : 'Unknown',
			$item['description']
		);

		site_pulse_dispatch_notification( 'action_urgent', (int) $item['user_id'], $urgent_msg, $item_id, 'action_item' );
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
# Color Scheme — per-install theming of the CSS variables
--------------------------------------------------------------*/

/**
 * The curated set of "company colors" a site admin can change. Each role is ONE base color
 * the user picks; saving it derives every dependent CSS variable (hover/light/glow/contrast),
 * so the app stays readable without the user hand-picking 20 shades. Mirrored in
 * script-site-pulse.js (spColorRoleVars) for instant live preview before save.
 */
function site_pulse_color_roles(): array {
	return [
		'primary' => [ 'label' => 'Brand / Accent', 'desc' => 'Buttons, the active menu item, links and highlights throughout the app.', 'default' => '#ec9a3c' ],
		'sidebar' => [ 'label' => 'Navigation Bar', 'desc' => 'The side menu and mobile top-bar background.', 'default' => '#474c54' ],
		'bg'      => [ 'label' => 'Page Background', 'desc' => 'The canvas behind cards and content.', 'default' => '#f4eee3' ],
		'text'    => [ 'label' => 'Body Text', 'desc' => 'Primary text color for headings and copy.', 'default' => '#2c2a27' ],
		'success' => [ 'label' => 'Success', 'desc' => 'Positive states — resolved items, confirmations, "Active" badges.', 'default' => '#2f8f57' ],
		'warning' => [ 'label' => 'Warning', 'desc' => 'Caution states — medium priority, pending notices.', 'default' => '#c77d18' ],
		'danger'  => [ 'label' => 'Danger', 'desc' => 'Errors, deletions, high-priority / urgent items.', 'default' => '#d0432f' ],
	];
}

function site_pulse_sanitize_hex( string $val ): string {
	$val = trim( $val );
	if ( preg_match( '/^#?([0-9a-fA-F]{6})$/', $val, $m ) ) return '#' . strtolower( $m[1] );
	if ( preg_match( '/^#?([0-9a-fA-F]{3})$/', $val, $m ) ) {
		$c = $m[1];
		return '#' . strtolower( $c[0].$c[0].$c[1].$c[1].$c[2].$c[2] );
	}
	return '';
}

/** Stored base for a role, or null if the admin has never set it (so the stylesheet default stands). */
function site_pulse_color_role_raw( string $key ): ?string {
	global $wpdb;
	$val = $wpdb->get_var( $wpdb->prepare(
		"SELECT config_value FROM " . site_pulse_table('config') . " WHERE config_key = %s",
		'color_' . $key
	) );
	if ( $val !== null && $val !== '' ) return $val;
	$option = get_option( 'site_pulse', [] );
	if ( isset( $option[ 'color_' . $key ] ) && $option[ 'color_' . $key ] !== '' ) return $option[ 'color_' . $key ];
	return null;
}

function site_pulse_hex_to_rgb( string $hex ): array {
	$hex = ltrim( site_pulse_sanitize_hex( $hex ) ?: '#000000', '#' );
	return [ hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ];
}
function site_pulse_rgb_to_hex( int $r, int $g, int $b ): string {
	$clamp = fn( $n ) => max( 0, min( 255, (int) round( $n ) ) );
	return sprintf( '#%02x%02x%02x', $clamp( $r ), $clamp( $g ), $clamp( $b ) );
}
function site_pulse_color_darken( string $hex, float $pct ): string {
	[ $r, $g, $b ] = site_pulse_hex_to_rgb( $hex );
	$f = 1 - ( $pct / 100 );
	return site_pulse_rgb_to_hex( $r * $f, $g * $f, $b * $f );
}
function site_pulse_color_mix_white( string $hex, float $w ): string {
	[ $r, $g, $b ] = site_pulse_hex_to_rgb( $hex );
	return site_pulse_rgb_to_hex( $r + ( 255 - $r ) * $w, $g + ( 255 - $g ) * $w, $b + ( 255 - $b ) * $w );
}
function site_pulse_color_rgba( string $hex, float $a ): string {
	[ $r, $g, $b ] = site_pulse_hex_to_rgb( $hex );
	return "rgba($r,$g,$b,$a)";
}
function site_pulse_color_is_dark( string $hex ): bool {
	[ $r, $g, $b ] = site_pulse_hex_to_rgb( $hex );
	return ( ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255 ) < 0.6;
}
function site_pulse_color_contrast( string $hex ): string {
	return site_pulse_color_is_dark( $hex ) ? '#ffffff' : '#1f2430';
}
function site_pulse_color_contrast_muted( string $hex ): string {
	return site_pulse_color_is_dark( $hex ) ? '#c8ccd3' : '#4b5563';
}

/** WCAG relative luminance (0–1) of a hex color. */
function site_pulse_luminance( string $hex ): float {
	[ $r, $g, $b ] = site_pulse_hex_to_rgb( $hex );
	$lin = function ( $c ) {
		$c = $c / 255;
		return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	};
	return 0.2126 * $lin( $r ) + 0.7152 * $lin( $g ) + 0.0722 * $lin( $b );
}

/** WCAG contrast ratio (1–21) between two hex colors. */
function site_pulse_contrast_ratio( string $a, string $b ): float {
	$la = site_pulse_luminance( $a );
	$lb = site_pulse_luminance( $b );
	$hi = max( $la, $lb );
	$lo = min( $la, $lb );
	return ( $hi + 0.05 ) / ( $lo + 0.05 );
}

/**
 * Pick the foreground that reads best across ALL the given backgrounds: the candidate whose
 * WORST contrast (over every background) is highest. Defaults to white vs near-black — so a
 * text color forced to read on two very different backgrounds (e.g. the active menu item, which
 * shows on both --sp-sidebar-bg-active and --sp-sidebar-bg-hover) gets the best compromise.
 */
function site_pulse_best_foreground( array $bgs, array $candidates = [ '#ffffff', '#16181d' ] ): string {
	$best      = $candidates[0];
	$bestScore = -1.0;
	foreach ( $candidates as $cand ) {
		$worst = INF;
		foreach ( $bgs as $bg ) $worst = min( $worst, site_pulse_contrast_ratio( $cand, $bg ) );
		if ( $worst > $bestScore ) { $bestScore = $worst; $best = $cand; }
	}
	return $best;
}

/**
 * Deterministic legibility guard. The AI chooses every color for LOOKS, but text-on-background
 * contrast is pure math the model can't be trusted with, so we verify the critical pairs here:
 * if a text color doesn't clear its minimum WCAG ratio against the background(s) it actually sits
 * on, replace it with whichever of white / near-black reads best on those backgrounds. Everything
 * that already passes is left exactly as the AI chose it. Idempotent — safe to run at render too.
 */
function site_pulse_enforce_contrast( array $v ): array {
	// text var => [ [ background vars it must read on ], minimum ratio ]
	$rules = [
		'--sp-sidebar-text'        => [ [ '--sp-sidebar-bg' ], 4.5 ],
		// text-hover covers both hovered items AND active sub-items (both sit on bg-hover).
		'--sp-sidebar-text-hover'  => [ [ '--sp-sidebar-bg-hover' ], 4.5 ],
		// text-active is reserved for the active TOP-level item, which sits only on bg-active.
		'--sp-sidebar-text-active' => [ [ '--sp-sidebar-bg-active' ], 4.5 ],
		'--sp-primary-contrast'    => [ [ '--sp-primary' ], 4.5 ],
		'--sp-text'                => [ [ '--sp-bg-white', '--sp-bg' ], 4.5 ],
	];
	foreach ( $rules as $textVar => $rule ) {
		[ $bgVars, $minRatio ] = $rule;
		$fg = site_pulse_sanitize_hex( (string) ( $v[ $textVar ] ?? '' ) );
		if ( $fg === '' ) continue;
		$bgs = [];
		foreach ( $bgVars as $bv ) {
			$bg = site_pulse_sanitize_hex( (string) ( $v[ $bv ] ?? '' ) );
			if ( $bg !== '' ) $bgs[] = $bg;
		}
		if ( ! $bgs ) continue;
		$worst = INF;
		foreach ( $bgs as $bg ) $worst = min( $worst, site_pulse_contrast_ratio( $fg, $bg ) );
		if ( $worst < $minRatio ) $v[ $textVar ] = site_pulse_best_foreground( $bgs );
	}
	return $v;
}

/** All CSS vars a single role drives. Keep in lock-step with spColorRoleVars() in the JS. */
function site_pulse_color_role_vars( string $key, string $base ): array {
	switch ( $key ) {
		case 'primary': return [
			'--sp-primary'              => $base,
			'--sp-primary-hover'        => site_pulse_color_darken( $base, 12 ),
			'--sp-primary-light'        => site_pulse_color_mix_white( $base, 0.88 ),
			'--sp-primary-contrast'     => site_pulse_color_contrast( $base ),
			'--sp-sidebar-bg-active'    => $base,
			'--sp-sidebar-accent'       => $base,
			'--sp-sidebar-accent-glow'  => site_pulse_color_rgba( $base, 0.18 ),
			'--sp-sidebar-text-active'  => site_pulse_color_contrast( $base ),
			'--sp-border-focus'         => site_pulse_color_mix_white( $base, 0.45 ),
		];
		case 'sidebar': return [
			'--sp-sidebar-bg'          => $base,
			'--sp-sidebar-bg-hover'    => site_pulse_color_is_dark( $base ) ? site_pulse_color_mix_white( $base, 0.10 ) : site_pulse_color_darken( $base, 8 ),
			'--sp-sidebar-text'        => site_pulse_color_contrast_muted( $base ),
			'--sp-sidebar-text-hover'  => site_pulse_color_contrast( $base ),
			'--sp-sidebar-border'      => site_pulse_color_rgba( site_pulse_color_is_dark( $base ) ? '#ffffff' : '#000000', 0.08 ),
		];
		case 'bg':   return [ '--sp-bg' => $base ];
		case 'text': return [
			'--sp-text'           => $base,
			'--sp-text-secondary' => site_pulse_color_mix_white( $base, 0.35 ),
			'--sp-text-light'     => site_pulse_color_mix_white( $base, 0.58 ),
		];
		case 'success': return [ '--sp-success' => $base, '--sp-success-light' => site_pulse_color_mix_white( $base, 0.90 ) ];
		case 'warning': return [ '--sp-warning' => $base, '--sp-warning-light' => site_pulse_color_mix_white( $base, 0.90 ) ];
		case 'danger':  return [ '--sp-danger'  => $base, '--sp-danger-light'  => site_pulse_color_mix_white( $base, 0.90 ) ];
	}
	return [];
}

/**
 * The full set of themeable UI colors. AI returns a hex for EACH of these keys directly —
 * every navigation state, text level, button, border — so legibility is decided by the model,
 * not by lossy derivation. `default` mirrors the stylesheet's stock scheme (also used as the
 * AI reference example). site_pulse_color_ai_to_vars() maps these to the actual CSS variables.
 */
function site_pulse_ai_color_fields(): array {
	return [
		'primary'             => [ 'desc' => 'Main action color — primary buttons, links, key highlights.', 'default' => '#ec9a3c' ],
		'primary_hover'       => [ 'desc' => 'A slightly darker primary, shown when hovering a primary button.', 'default' => '#db8927' ],
		'primary_contrast'    => [ 'desc' => 'Text/icon color shown ON primary buttons — must be highly legible on "primary".', 'default' => '#ffffff' ],
		'accent'              => [ 'desc' => 'Small accent details: the active menu edge-bar and menu icons (often equals primary).', 'default' => '#ec9a3c' ],
		'sidebar_bg'          => [ 'desc' => 'Navigation sidebar (and mobile top bar) background.', 'default' => '#474c54' ],
		'sidebar_text'        => [ 'desc' => 'Default menu-item text — legible on sidebar_bg, slightly muted.', 'default' => '#c8ccd3' ],
		'sidebar_text_hover'  => [ 'desc' => 'Menu-item text on hover — legible on sidebar_bg_hover.', 'default' => '#ffffff' ],
		'sidebar_bg_hover'    => [ 'desc' => 'Background of a hovered menu item AND of the active SUB-item.', 'default' => '#545a64' ],
		'sidebar_active_bg'   => [ 'desc' => 'Background of the active TOP-LEVEL menu item.', 'default' => '#ec9a3c' ],
		'sidebar_active_text' => [ 'desc' => 'Text of the active menu item — MUST be clearly legible on BOTH sidebar_active_bg AND sidebar_bg_hover.', 'default' => '#ffffff' ],
		'bg'                  => [ 'desc' => 'A SUBTLE ACCENT TINT used for table headers, input fills and hover rows (the page itself is white). Keep it a very light, low-saturation tint that dark text reads on.', 'default' => '#f4eee3' ],
		'surface'             => [ 'desc' => 'The main app + card background. Keep this white or very near-white for a clean interface; body text must be legible on it.', 'default' => '#ffffff' ],
		'text'                => [ 'desc' => 'Primary body text — legible on both bg and surface.', 'default' => '#2c2a27' ],
		'text_secondary'      => [ 'desc' => 'Muted secondary text (descriptions, meta).', 'default' => '#6e6a63' ],
		'text_light'          => [ 'desc' => 'Faint tertiary text and placeholders.', 'default' => '#a39c90' ],
		'border'              => [ 'desc' => 'Subtle borders and dividers on light surfaces.', 'default' => '#e7ded0' ],
		'border_focus'        => [ 'desc' => 'Input border when focused — usually a primary tint.', 'default' => '#eebb78' ],
		'success'             => [ 'desc' => 'Positive/confirmation color — recognizably green.', 'default' => '#2f8f57' ],
		'warning'             => [ 'desc' => 'Caution color — recognizably amber/orange.', 'default' => '#c77d18' ],
		'danger'              => [ 'desc' => 'Error/destructive color — recognizably red.', 'default' => '#d0432f' ],
	];
}

/** Map the AI's field set to the actual CSS variables, deriving the few pure tints/alphas. */
function site_pulse_color_ai_to_vars( array $ai ): array {
	$fields = site_pulse_ai_color_fields();
	$v = [];
	foreach ( $fields as $key => $f ) {
		$hex = site_pulse_sanitize_hex( (string) ( $ai[ $key ] ?? '' ) );
		$v[ $key ] = $hex !== '' ? $hex : $f['default'];
	}
	return [
		'--sp-sidebar-bg'          => $v['sidebar_bg'],
		'--sp-sidebar-bg-hover'    => $v['sidebar_bg_hover'],
		'--sp-sidebar-bg-active'   => $v['sidebar_active_bg'],
		'--sp-sidebar-text'        => $v['sidebar_text'],
		'--sp-sidebar-text-hover'  => $v['sidebar_text_hover'],
		'--sp-sidebar-text-active' => $v['sidebar_active_text'],
		'--sp-sidebar-accent'      => $v['accent'],
		'--sp-sidebar-accent-glow' => site_pulse_color_rgba( $v['accent'], 0.18 ),
		'--sp-sidebar-border'      => site_pulse_color_rgba( $v['sidebar_text'], 0.14 ),
		'--sp-primary'             => $v['primary'],
		'--sp-primary-hover'       => $v['primary_hover'],
		'--sp-primary-light'       => site_pulse_color_mix_white( $v['primary'], 0.88 ),
		'--sp-primary-contrast'    => $v['primary_contrast'],
		'--sp-text'                => $v['text'],
		'--sp-text-secondary'      => $v['text_secondary'],
		'--sp-text-light'          => $v['text_light'],
		'--sp-bg'                  => $v['bg'],
		'--sp-bg-white'            => $v['surface'],
		'--sp-border'              => $v['border'],
		'--sp-border-focus'        => $v['border_focus'],
		'--sp-success'             => $v['success'],
		'--sp-success-light'       => site_pulse_color_mix_white( $v['success'], 0.90 ),
		'--sp-warning'             => $v['warning'],
		'--sp-warning-light'       => site_pulse_color_mix_white( $v['warning'], 0.90 ),
		'--sp-danger'              => $v['danger'],
		'--sp-danger-light'        => site_pulse_color_mix_white( $v['danger'], 0.90 ),
	];
}

/** Accept a #hex or rgb()/rgba() string for inline output; '' if it isn't a safe color literal. */
function site_pulse_sanitize_css_color( string $v ): string {
	$v = trim( $v );
	if ( preg_match( '/^#[0-9a-fA-F]{3}$/', $v ) || preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ) return strtolower( $v );
	if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $v ) ) return $v;
	return '';
}

/** The three brand-color slots, in order. Each maps to --sp-{slot}-color + the variants below. */
function site_pulse_brand_slots(): array {
	return [ 'dominant', 'secondary', 'accent' ];
}

/** The four tonal variants every brand slot exposes, lightest → darkest. */
function site_pulse_brand_variants(): array {
	return [ 'lightest', 'light', 'dark', 'darkest' ];
}

/**
 * Brand palette: for each company color the admin enters (up to 3), expose the base color plus
 * FOUR tonal variants — --sp-{slot}-lightest / -light / -dark / -darkest — giving a real range:
 *   lightest = very pale tint (card / panel backgrounds; dark text reads on it)
 *   light    = light highlight (hover fills, highlight borders)
 *   dark     = shadow tone (borders, dividers, shadows)
 *   darkest  = deepest tone (strong borders, deep fills; light text reads on it)
 *
 * This is the COMPUTED FALLBACK only, and uses fixed mix/darken amounts that won't suit every
 * color. When a scheme is generated, the AI TUNES all four variants per color (see
 * site_pulse_ajax_admin_ai_color_scheme) and those stored values override these.
 */
function site_pulse_brand_palette( array $brand ): array {
	$slots = site_pulse_brand_slots();
	$vars  = [];
	foreach ( array_values( $brand ) as $i => $raw ) {
		if ( $i > 2 ) break;
		$hex = site_pulse_sanitize_hex( (string) $raw );
		if ( $hex === '' ) continue;
		$slot = $slots[ $i ];
		$vars[ "--sp-{$slot}-color" ]    = $hex;
		$vars[ "--sp-{$slot}-lightest" ] = site_pulse_color_mix_white( $hex, 0.86 ); // pale tint
		$vars[ "--sp-{$slot}-light" ]    = site_pulse_color_mix_white( $hex, 0.42 ); // highlight
		$vars[ "--sp-{$slot}-dark" ]     = site_pulse_color_darken( $hex, 28 );      // shadow
		$vars[ "--sp-{$slot}-darkest" ]  = site_pulse_color_darken( $hex, 52 );      // deepest
	}
	return $vars;
}

/** The saved company-color inputs (sanitized hex, max 3). */
function site_pulse_saved_brand_colors(): array {
	$raw  = (string) site_pulse_get_setting( 'color_brand', '' );
	$list = $raw !== '' ? json_decode( $raw, true ) : [];
	if ( ! is_array( $list ) ) $list = [];
	$out = [];
	foreach ( $list as $c ) {
		$h = site_pulse_sanitize_hex( (string) $c );
		if ( $h !== '' ) $out[] = $h;
	}
	return array_slice( $out, 0, 3 );
}

/** The active CSS-variable map: the saved AI scheme, else a legacy per-role scheme, else none. */
function site_pulse_color_active_vars(): array {
	$vars = [];
	$raw  = (string) site_pulse_get_setting( 'color_vars', '' );
	if ( $raw !== '' ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) && $decoded ) $vars = $decoded;
	}
	if ( ! $vars ) {
		// Back-compat: derive from older per-role keys if an install saved one before this build.
		foreach ( site_pulse_color_roles() as $key => $role ) {
			$rawr = site_pulse_color_role_raw( $key );
			if ( $rawr === null ) continue;
			$base = site_pulse_sanitize_hex( $rawr );
			if ( $base === '' ) continue;
			$vars += site_pulse_color_role_vars( $key, $base );
		}
	}
	// Lay the computed brand palette down FIRST as a fallback, then let the saved scheme ($vars)
	// override it — so the AI-tuned high/low stored in color_vars win, but a slot the AI didn't
	// set still gets a sensible computed value. Entering company colors alone (no AI run) emits
	// just the fallback palette, overriding the stylesheet's default brand slots. With neither
	// saved, this is empty and the stylesheet defaults stand.
	return site_pulse_enforce_contrast( array_merge( site_pulse_brand_palette( site_pulse_saved_brand_colors() ), $vars ) );
}

/**
 * A <style> block overriding the :root CSS variables from the saved scheme. Emitted near the
 * top of the dashboard/login markup; later source order beats the external stylesheet. Returns
 * '' when no custom scheme is set, so the stylesheet defaults stand.
 */
function site_pulse_color_scheme_css(): string {
	$vars = site_pulse_color_active_vars();
	if ( ! $vars ) return '';

	$css = ':root{';
	foreach ( $vars as $k => $v ) {
		if ( ! preg_match( '/^--sp-[a-z0-9-]+$/', (string) $k ) ) continue;
		$val = site_pulse_sanitize_css_color( (string) $v );
		if ( $val === '' ) continue;
		$css .= $k . ':' . $val . ';';
	}
	$css .= '}';
	return '<style id="sp-color-scheme">' . $css . '</style>';
}

add_action( 'wp_ajax_site_pulse_admin_get_color_scheme', 'site_pulse_ajax_admin_get_color_scheme' );
function site_pulse_ajax_admin_get_color_scheme(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	$brandRaw = (string) site_pulse_get_setting( 'color_brand', '' );
	$brand    = $brandRaw !== '' ? json_decode( $brandRaw, true ) : [];
	if ( ! is_array( $brand ) ) $brand = [];
	$brand = array_values( array_filter( array_map( fn( $c ) => site_pulse_sanitize_hex( (string) $c ), $brand ) ) );

	wp_send_json_success( [
		'brand'          => $brand,
		'mood'           => (string) site_pulse_get_setting( 'color_mood', '' ),
		'active'         => (bool) site_pulse_color_active_vars(),
		'alert_keywords' => (string) site_pulse_get_setting( 'dashboard_alert_keywords', '' ),
		'news_enabled'   => (string) site_pulse_get_setting( 'dashboard_news_enabled', '1' ),
		'app_icon'       => site_pulse_pwa_icon_setting_url(),
		'app_icon_built' => function_exists( 'site_pulse_pwa_preview_url' ) ? site_pulse_pwa_preview_url() : '',
	] );
}

add_action( 'wp_ajax_site_pulse_admin_save_color_scheme', 'site_pulse_ajax_admin_save_color_scheme' );
function site_pulse_ajax_admin_save_color_scheme(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	$raw  = $_POST['vars'] ?? '';
	$vars = is_string( $raw ) ? json_decode( wp_unslash( $raw ), true ) : ( is_array( $raw ) ? $raw : [] );
	if ( ! is_array( $vars ) || ! $vars ) wp_send_json_error( [ 'message' => 'No colors to save — generate a scheme first.' ] );

	$allowed = array_keys( site_pulse_color_ai_to_vars( [] ) ); // canonical role --sp-* var names
	foreach ( site_pulse_brand_slots() as $slot ) {            // + the brand palette vars (AI-tuned tones)
		$allowed[] = "--sp-{$slot}-color";
		foreach ( site_pulse_brand_variants() as $variant ) $allowed[] = "--sp-{$slot}-{$variant}";
	}
	$clean   = [];
	foreach ( $vars as $k => $v ) {
		if ( ! in_array( $k, $allowed, true ) ) continue;
		$val = site_pulse_sanitize_css_color( (string) $v );
		if ( $val === '' ) continue;
		$clean[ $k ] = $val;
	}
	if ( ! $clean ) wp_send_json_error( [ 'message' => 'No valid colors to save.' ] );

	site_pulse_set_setting( 'color_vars', wp_json_encode( $clean ) );

	// Remember the brand inputs + mood so the form can repopulate them next time.
	$brandRaw = $_POST['brand_colors'] ?? '';
	$blist    = is_string( $brandRaw ) ? json_decode( wp_unslash( $brandRaw ), true ) : ( is_array( $brandRaw ) ? $brandRaw : [] );
	if ( is_array( $blist ) ) {
		$bclean = array_values( array_filter( array_map( fn( $c ) => site_pulse_sanitize_hex( (string) $c ), $blist ) ) );
		site_pulse_set_setting( 'color_brand', wp_json_encode( array_slice( $bclean, 0, 3 ) ) );
	}
	site_pulse_set_setting( 'color_mood', sanitize_text_field( wp_unslash( $_POST['mood'] ?? '' ) ) );

	wp_send_json_success( [ 'message' => 'Color scheme saved.' ] );
}

add_action( 'wp_ajax_site_pulse_admin_reset_color_scheme', 'site_pulse_ajax_admin_reset_color_scheme' );
function site_pulse_ajax_admin_reset_color_scheme(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	global $wpdb;
	$t = site_pulse_table( 'config' );
	foreach ( [ 'color_vars', 'color_brand', 'color_mood' ] as $k ) $wpdb->delete( $t, [ 'config_key' => $k ] );
	foreach ( array_keys( site_pulse_color_roles() ) as $key ) $wpdb->delete( $t, [ 'config_key' => 'color_' . $key ] ); // legacy keys
	wp_send_json_success( [ 'message' => 'Colors reset to defaults.' ] );
}

/**
 * AI scheme builder: the admin gives 2–4 brand colors + an optional mood; Claude returns a
 * COMPLETE color scheme (every nav state, text level, button, border) as hex per field. The
 * result is mapped to CSS variables and returned for live preview — NOT saved until Save.
 */
add_action( 'wp_ajax_site_pulse_admin_ai_color_scheme', 'site_pulse_ajax_admin_ai_color_scheme' );
function site_pulse_ajax_admin_ai_color_scheme(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	$raw  = $_POST['brand_colors'] ?? '';
	$list = is_string( $raw ) ? json_decode( wp_unslash( $raw ), true ) : ( is_array( $raw ) ? $raw : [] );
	$brand = [];
	if ( is_array( $list ) ) {
		foreach ( $list as $c ) {
			$hex = site_pulse_sanitize_hex( (string) $c );
			if ( $hex !== '' ) $brand[] = $hex;
		}
	}
	$brand = array_slice( array_values( array_unique( $brand ) ), 0, 3 );
	if ( count( $brand ) < 1 ) wp_send_json_error( [ 'message' => 'Enter at least one brand color.' ] );

	if ( ! site_pulse_get_api_key() ) {
		wp_send_json_error( [ 'message' => 'No Claude API key set. Add one under Settings → API Keys first.' ] );
	}

	$note   = sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) );
	$fields = site_pulse_ai_color_fields();
	$slots  = site_pulse_brand_slots();

	// Each brand color, labelled by its role. The AI is asked to TUNE four tonal variants for each.
	$brand_lines   = '';
	$present_slots = [];
	foreach ( $brand as $i => $hex ) {
		if ( ! isset( $slots[ $i ] ) ) break;
		$present_slots[] = $slots[ $i ];
		$brand_lines    .= "- {$slots[ $i ]}: {$hex}\n";
	}

	$field_lines = '';
	foreach ( $fields as $key => $f ) $field_lines .= "- \"$key\": {$f['desc']}\n";
	$example = wp_json_encode( array_map( fn( $f ) => $f['default'], $fields ) );

	// JSON shape: every role color + a "palette" object of four tuned variants per present slot.
	$palette_shape = [];
	foreach ( $present_slots as $slot ) $palette_shape[] = "\"$slot\":{\"lightest\":\"#rrggbb\",\"light\":\"#rrggbb\",\"dark\":\"#rrggbb\",\"darkest\":\"#rrggbb\"}";
	$shape = '{'
		. implode( ',', array_map( fn( $k ) => "\"$k\":\"#rrggbb\"", array_keys( $fields ) ) )
		. ',"palette":{' . implode( ',', $palette_shape ) . '}'
		. ',"rationale":"one short sentence"}';

	$system = 'You are a senior brand and UI designer producing complete, WCAG-AA-accessible color schemes for a web-app dashboard. Reply with ONLY a single JSON object — no prose, no markdown code fences.';

	$prompt  = "Design a COMPLETE color scheme for a business's internal web-app dashboard, themed around their brand colors. You choose EVERY color in the UI — backgrounds, text, navigation states, buttons, borders — so guarantee that every text color is clearly legible on whatever background it sits on.\n\n";
	$prompt .= "The brand colors, by role:\n" . $brand_lines;
	if ( $note !== '' ) $prompt .= "\nDesired mood / feel: $note\n";
	$prompt .= "\nSTEP 1 — For EACH brand color above, derive FOUR tonal variants tuned to THAT specific color. Tune the amounts to the color itself — do NOT apply a fixed offset; a dark, saturated color needs a much bigger lift to its light tones than a pale color does, and vice-versa for the dark tones:\n";
	$prompt .= "- \"lightest\": a VERY pale tint — used as a card / panel background. Near-black body text MUST read on it (≥4.5:1 vs #1a1a1a).\n";
	$prompt .= "- \"light\": a light highlight — hover fills, highlight borders.\n";
	$prompt .= "- \"dark\": a shadow tone — borders, dividers, shadows.\n";
	$prompt .= "- \"darkest\": the deepest tone — strong borders and deep fills. White text MUST read on it (≥4.5:1 vs #ffffff).\n";
	$prompt .= "Order them strictly by lightness: lightest > light > base color > dark > darkest, each clearly distinct from its neighbour.\n";
	$prompt .= "\nSTEP 2 — Build the whole UI scheme, leaning on these brand colors and the variants you just derived, plus tasteful neutrals. Sensible defaults: 'dominant' for primary/accent; a brand color or its 'dark'/'darkest' for the sidebar and for card/table borders; 'lightest' for card/panel fills; 'light' for hover/highlights; keep the page surface white. You may copy a brand value verbatim or nudge it for contrast. Keep success green-ish, warning amber-ish, danger red-ish.\n";
	$prompt .= "\nProvide a #rrggbb hex for EVERY one of these role keys:\n" . $field_lines;
	$prompt .= "\nCritical legibility rules:\n";
	$prompt .= "- Every *text* color MUST have strong contrast against the background it appears on — sidebar_text on sidebar_bg; sidebar_active_text on BOTH sidebar_active_bg AND sidebar_bg_hover; text/text_secondary on both bg and surface; primary_contrast on primary.\n";
	$prompt .= "- Make sidebar_active_bg clearly distinct from sidebar_bg so the active menu item stands out.\n";
	$prompt .= "- For ANY text color, if no brand-derived tone can clear 4.5:1 on the background it sits on, use white or near-black instead — legibility beats brand-matching. (Notably: sidebar_active_text sits on sidebar_active_bg, and sidebar_text_hover sits on sidebar_bg_hover and must read there since active sub-items reuse it.)\n";
	$prompt .= "- A light, mostly-neutral bg + surface with dark text is safest; only go dark if the mood calls for it, and then keep every text color light enough to read.\n";
	$prompt .= "\nFor reference, this EXAMPLE role scheme (cream + orange + slate) is well-balanced and accessible — match this level of polish and contrast, but built from the brand colors above:\n";
	$prompt .= $example . "\n";
	$prompt .= "\nReturn ONLY this JSON shape (all color values #rrggbb hex):\n" . $shape;

	$debug = null;
	$resp  = site_pulse_call_claude( $prompt, $system, [ 'max_tokens' => 1500, 'timeout' => 45 ], $debug );
	if ( $resp === null ) wp_send_json_error( [ 'message' => 'AI request failed' . ( $debug ? ": $debug" : '.' ) ] );

	$json = trim( $resp );
	if ( preg_match( '/\{.*\}/s', $json, $m ) ) $json = $m[0];
	$parsed = json_decode( $json, true );
	if ( ! is_array( $parsed ) ) wp_send_json_error( [ 'message' => 'AI returned an unreadable response. Please try again.' ] );

	// Brand palette vars: base = the user's color; the four variants = the AI's tuned tones,
	// falling back to the computed tints when the AI omitted or returned an invalid value.
	$palette = site_pulse_brand_palette( $brand ); // base + computed-fallback variants
	$ai_pal  = ( isset( $parsed['palette'] ) && is_array( $parsed['palette'] ) ) ? $parsed['palette'] : [];
	foreach ( $present_slots as $slot ) {
		$tones = ( isset( $ai_pal[ $slot ] ) && is_array( $ai_pal[ $slot ] ) ) ? $ai_pal[ $slot ] : [];
		foreach ( site_pulse_brand_variants() as $variant ) {
			$cand = site_pulse_sanitize_hex( (string) ( $tones[ $variant ] ?? '' ) );
			if ( $cand !== '' ) $palette[ "--sp-{$slot}-{$variant}" ] = $cand;
		}
	}

	// AI role colors + the brand palette vars (so the live preview shows both and they persist),
	// then the deterministic contrast guard so any text the AI left illegible gets auto-corrected.
	$vars      = array_merge( site_pulse_color_ai_to_vars( $parsed ), $palette );
	$vars      = site_pulse_enforce_contrast( $vars );
	$rationale = isset( $parsed['rationale'] ) ? sanitize_text_field( (string) $parsed['rationale'] ) : '';

	wp_send_json_success( [ 'vars' => $vars, 'rationale' => $rationale ] );
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
# One-time maintenance — strip legacy WordPress over-escaping
# Text saved before the save handlers were fixed to wp_unslash()
# their input kept the slashes WP adds to $_POST (HGTV\'s, O\"Brien).
# Visit, logged in as a God account:   /?sp_fix_slashes=run
# Idempotent: only rows whose value actually changes are rewritten,
# so it is safe to run more than once and a no-op once everything is
# clean. stripslashes() is the exact inverse of the addslashes() WP
# applied, so user-typed backslashes (stored doubled) restore intact.
--------------------------------------------------------------*/
add_action( 'init', 'site_pulse_maybe_fix_legacy_slashes', 99 );
function site_pulse_maybe_fix_legacy_slashes(): void {
	if ( ( $_GET['sp_fix_slashes'] ?? '' ) !== 'run' ) return;
	if ( ! is_user_logged_in() || ! site_pulse_is_god() ) wp_die( 'Not authorized.' );
	nocache_headers();

	global $wpdb;
	// Every column users type prose into — the same fields whose save paths now wp_unslash().
	$targets = [
		[ site_pulse_table('report_answers'),    'answer_text' ],
		[ site_pulse_table('reports'),           'review_notes' ],
		[ site_pulse_table('action_items'),      'description' ],
		[ site_pulse_table('action_items'),      'resolution_note' ],
		[ site_pulse_table('mileage_entries'),   'notes' ],
		[ site_pulse_table('mileage_locations'), 'name' ],
		[ site_pulse_table('mileage_locations'), 'address' ],
		[ site_pulse_table('mileage_locations'), 'notes' ],
		[ site_pulse_table('mileage_legs'),      'purpose' ],
		[ site_pulse_table('locations'),         'name' ],
		[ site_pulse_table('locations'),         'location_type' ],
		[ site_pulse_table('locations'),         'address' ],
		[ site_pulse_table('locations'),         'city' ],
	];

	$lines = [];
	$grand = 0;
	foreach ( $targets as [ $table, $col ] ) {
		// Skip NULL/empty — stripslashes(null) would blank the cell. Column names are
		// hardcoded (not user input), so the interpolation is safe.
		$rows  = $wpdb->get_results( "SELECT id, `$col` AS val FROM `$table` WHERE `$col` IS NOT NULL AND `$col` <> ''" );
		$fixed = 0;
		foreach ( $rows as $row ) {
			$clean = stripslashes( $row->val );
			if ( $clean !== $row->val ) {
				$wpdb->update( $table, [ $col => $clean ], [ 'id' => (int) $row->id ] );
				$fixed++;
			}
		}
		$lines[] = sprintf( '%s.%s — %d scanned, %d fixed', $table, $col, count( $rows ), $fixed );
		$grand  += $fixed;
	}

	if ( function_exists( 'site_pulse_log' ) ) {
		site_pulse_log( 'maintenance', sprintf( 'Legacy slash cleanup: %d rows fixed', $grand ) );
	}

	wp_die(
		'<h2>Site Pulse — legacy slash cleanup</h2><p><strong>' . (int) $grand
		. '</strong> rows fixed.</p><pre>' . esc_html( implode( "\n", $lines ) )
		. '</pre><p>Idempotent — refresh to re-run (it will report 0 once clean).</p>',
		'Slash cleanup',
		[ 'response' => 200 ]
	);
}


/*--------------------------------------------------------------
# God Mode — Nuclear Reset
--------------------------------------------------------------*/

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

// GOD only: the option lists for the Reassign dialog — every active Site Pulse user (so god can pick
// the correct author when the AI matched the wrong person) plus the store list.
add_action( 'wp_ajax_site_pulse_god_report_options', 'site_pulse_ajax_god_report_options' );
function site_pulse_ajax_god_report_options(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god() ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	global $wpdb;
	$rows  = $wpdb->get_results( "SELECT user_id, role_id, location_id FROM " . site_pulse_table('user_profiles') . " WHERE status = 'active'", ARRAY_A ) ?: [];
	$users = [];
	foreach ( $rows as $r ) {
		$u = get_userdata( (int) $r['user_id'] );
		if ( ! $u ) continue;
		$role = site_pulse_get_role( (int) $r['role_id'] );
		$loc  = (int) $r['location_id'] ? site_pulse_get_location( (int) $r['location_id'] ) : null;
		$meta = trim( ( $role ? $role['label'] : '' ) . ( $loc ? ' · ' . $loc['name'] : '' ) );
		$users[] = [ 'id' => (int) $r['user_id'], 'name' => $u->display_name, 'meta' => $meta ];
	}
	usort( $users, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

	$locations = array_map(
		fn( $l ) => [ 'id' => (int) $l['id'], 'name' => $l['name'] ],
		site_pulse_get_all_locations( true, true )
	);

	wp_send_json_success( [ 'users' => $users, 'locations' => $locations ] );
}

// GOD only: fix a report's attribution — reassign its submitter (and store). Used when an imported
// report was matched to the wrong person (e.g. the wrong "Grant"). Action items created from the
// report inherit the new author/location so they stay correctly attributed.
add_action( 'wp_ajax_site_pulse_god_reassign_report', 'site_pulse_ajax_god_reassign_report' );
function site_pulse_ajax_god_reassign_report(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god() ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$report_id   = (int) ( $_POST['report_id'] ?? 0 );
	$new_user_id = (int) ( $_POST['user_id'] ?? 0 );
	$new_loc_id  = (int) ( $_POST['location_id'] ?? 0 );

	$report = site_pulse_get_report( $report_id );
	if ( ! $report )      wp_send_json_error( [ 'message' => 'Report not found.' ] );
	if ( ! $new_user_id ) wp_send_json_error( [ 'message' => 'Choose a submitter.' ] );
	if ( ! site_pulse_get_user_profile( $new_user_id ) ) {
		wp_send_json_error( [ 'message' => 'That user has no Site Pulse profile.' ] );
	}

	global $wpdb;
	$wpdb->update(
		site_pulse_table('reports'),
		[ 'user_id' => $new_user_id, 'location_id' => $new_loc_id, 'updated_at' => current_time( 'mysql' ) ],
		[ 'id' => $report_id ]
	);
	$wpdb->update(
		site_pulse_table('action_items'),
		[ 'user_id' => $new_user_id, 'location_id' => $new_loc_id ],
		[ 'report_id' => $report_id ]
	);

	$u = get_userdata( $new_user_id );
	site_pulse_log( 'god_reassign_report', sprintf( 'GOD reassigned report #%d to %s', $report_id, $u ? $u->display_name : 'user ' . $new_user_id ) );
	wp_send_json_success( [ 'message' => 'Report reassigned.' ] );
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

// God-only HARD delete of a Home Base (store) — the regular admin button only deactivates.
add_action( 'wp_ajax_site_pulse_god_delete_location', 'site_pulse_ajax_god_delete_location' );
function site_pulse_ajax_god_delete_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god() ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Invalid location.' ] );

	$loc = $wpdb->get_row( $wpdb->prepare(
		"SELECT name FROM " . site_pulse_table('locations') . " WHERE id = %d", $id
	), ARRAY_A );
	if ( ! $loc ) wp_send_json_error( [ 'message' => 'Location not found.' ] );

	// Detach any driver whose Home Base pointed here so no dangling reference is left behind.
	// (Mileage legs reference mileage_locations, a separate table, so nothing there to clean.)
	$wpdb->update( site_pulse_table('user_profiles'), [ 'location_id' => 0 ], [ 'location_id' => $id ] );
	$wpdb->delete( site_pulse_table('locations'), [ 'id' => $id ] );

	site_pulse_log( 'god_delete', sprintf( 'GOD deleted location "%s" (#%d)', $loc['name'], $id ) );
	wp_send_json_success( [ 'message' => 'Location deleted.' ] );
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
 * Stored as a JSON array in config; falls back to a sensible default set. Each purpose is
 * returned as { label, requires_note } — requires_note flags purposes (e.g. "Meeting") that
 * prompt the driver for the additional-notes line. Legacy bare-string entries normalize to
 * requires_note = false, so older saved libraries keep working.
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
	$out = [];
	foreach ( $list as $item ) {
		if ( is_array( $item ) ) {
			$label = sanitize_text_field( (string) ( $item['label'] ?? '' ) );
			$req   = ! empty( $item['requires_note'] ) && $item['requires_note'] !== 'false';
		} else {
			$label = sanitize_text_field( (string) $item );
			$req   = false;
		}
		if ( $label === '' ) continue;
		$out[] = [ 'label' => $label, 'requires_note' => (bool) $req ];
	}
	return $out;
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
		"SELECT miles, miles_adjust, toll_cost, has_trailer FROM " . site_pulse_table('mileage_legs') . " WHERE entry_id = %d",
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
			// A driver can nudge a leg's miles for a detour/closure; the adjustment is stored
			// separately so the original computed distance stays visible. Effective = base + adjust.
			$eff = (float) $leg['miles'] + (float) ( $leg['miles_adjust'] ?? 0 );
			$total += $eff;
			if ( ! empty( $leg['has_trailer'] ) ) $trailer_miles += $eff;
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
# Toll Reconciliation — CSV import + AI matching
#
# The driver uploads their toll-authority statement (NTTA etc.). We parse it,
# keep only the charges on dates the driver actually logged a trip, then have
# Claude cross-reference each day's charges against that day's route — matching
# each toll to a leg, ignoring off-path charges as personal detours, and
# flagging anything that doesn't map cleanly. Review-then-apply: the AI result
# is staged on the transaction rows; nothing hits a leg's reimbursable toll_cost
# until the driver confirms. TollGuru is still priced per matched leg purely as
# an accuracy comparison (toll_estimate), pending its eventual removal.
--------------------------------------------------------------*/

// Strip the Excel formula wrappers NTTA exports wrap cells in — ="..." or
// =Text("01/05/2026 08:15","mm/dd/yyyy"). Returns the first quoted token, which
// is the underlying value in both shapes; otherwise the raw trimmed string.
function site_pulse_toll_csv_clean( string $v ): string {
	$v = trim( $v );
	if ( $v !== '' && $v[0] === '=' ) {
		if ( preg_match( '/"([^"]*)"/', $v, $m ) ) return trim( $m[1] );
		return trim( ltrim( $v, '=' ) );
	}
	return $v;
}

// Parse a toll-authority CSV into normalized transactions. Header-keyword driven
// so other authorities degrade gracefully; tuned for NTTA's column names.
// Returns rows: [ external_id, datetime (Y-m-d H:i:s|null), date (Y-m-d|null),
// road, gantry, amount (abs float), acct ].
function site_pulse_parse_toll_csv( string $csv, ?int &$skipped = null, ?int &$dupes = null ): array {
	$skipped = 0; // count of obviously-bogus rows auto-expelled (see ceiling check below)
	$dupes   = 0; // count of exact-duplicate rows collapsed
	$csv = str_replace( [ "\r\n", "\r" ], "\n", $csv );

	// CRITICAL: NTTA/Excel exports wrap cells in spreadsheet formulas that CONTAIN COMMAS —
	// =Text("01/07/2026 10:28:35","mm/dd/yyyy hh:mm:ss") and ="0003826043". If we tokenized
	// on commas first, those internal commas split one cell into several and shifted every
	// later column, so the amount slot landed on the wrong value (a tag/reference number like
	// 598602) and one transaction smeared across multiple junk rows. Neutralize the wrappers
	// to plain quoted fields BEFORE parsing so the commas inside them are protected.
	$csv = preg_replace( '/=Text\(\s*"((?:[^"]|"")*)"\s*(?:,\s*"(?:[^"]|"")*"\s*)?\)/i', '"$1"', $csv ); // =Text("X","fmt") | =Text("X") → "X"
	$csv = preg_replace( '/="((?:[^"]|"")*)"/', '"$1"', $csv );                                          // ="X" → "X"

	// Parse the WHOLE blob with fgetcsv (in-memory stream) so quoted commas and any embedded
	// newlines inside quotes are handled correctly — naive line-by-line splitting is what
	// fragmented records and shifted columns.
	$records = [];
	$fh = fopen( 'php://temp', 'r+' );
	if ( $fh ) {
		fwrite( $fh, $csv );
		rewind( $fh );
		while ( ( $rec = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) {
			if ( $rec === [ null ] ) continue; // blank line
			$records[] = $rec;
		}
		fclose( $fh );
	}
	if ( count( $records ) < 2 ) return [];

	// Header row = first record that names an amount column plus a date/transaction column.
	$header_idx = 0;
	foreach ( $records as $i => $rec ) {
		$low = strtolower( implode( ' ', array_map( 'strval', $rec ) ) );
		if ( strpos( $low, 'amount' ) !== false && ( strpos( $low, 'date' ) !== false || strpos( $low, 'time' ) !== false || strpos( $low, 'transaction' ) !== false ) ) {
			$header_idx = $i;
			break;
		}
	}

	$headers = array_map( fn( $h ) => strtolower( site_pulse_toll_csv_clean( (string) $h ) ), $records[ $header_idx ] );

	// Find a column by header text. $needles = substrings that qualify a column;
	// $exclude = substrings that DISqualify it (checked first), so a look-alike like
	// "Toll Tag ID" or "Account Balance" can never win a slot it shouldn't.
	$find = function( array $needles, array $exclude = [] ) use ( $headers ): int {
		foreach ( $headers as $idx => $h ) {
			foreach ( $exclude as $x ) { if ( $x !== '' && strpos( $h, $x ) !== false ) continue 2; }
			foreach ( $needles as $n ) { if ( strpos( $h, $n ) !== false ) return $idx; }
		}
		return -1;
	};
	// Match an exact header name (after normalize) — used where a substring match is risky.
	$find_exact = function( array $names ) use ( $headers ): int {
		foreach ( $headers as $idx => $h ) { if ( in_array( $h, $names, true ) ) return $idx; }
		return -1;
	};

	// Prefer the "Entry" date/time over "Posted".
	$col_dt = -1;
	foreach ( $headers as $idx => $h ) { if ( strpos( $h, 'entry' ) !== false && ( strpos( $h, 'date' ) !== false || strpos( $h, 'time' ) !== false ) ) { $col_dt = $idx; break; } }
	if ( $col_dt < 0 ) $col_dt = $find( [ 'date', 'time' ] );

	// Amount: the column the toll dollar value lives in. This is the one that bit us —
	// "Toll Tag ID" contains "toll", so keyword-matching "toll" grabbed the wrong column.
	// Match explicit money-column names first; on the fallback, exclude tag/account/plate/
	// balance/number/id columns so only a real amount column qualifies.
	$col_amt = $find_exact( [ 'transaction amount', 'toll amount', 'amount', 'amount ($)', 'charge amount', 'fee', 'toll', 'charge' ] );
	if ( $col_amt < 0 ) $col_amt = $find( [ 'amount', 'amt' ], [ 'tag', 'account', 'plate', 'license', 'balance', 'number', ' id', 'id ' ] );

	$col_id    = $find( [ 'transaction id', 'transaction number', 'reference', 'txn', 'trip id' ] );
	$col_road  = $find( [ 'road', 'highway', 'tollway', 'facility', 'route' ], [ 'date', 'time' ] );
	// Location/gantry: exclude any date/time column. NTTA has "Transaction Exit Date/Time",
	// which contains "exit" — a generic 'exit' keyword wrongly grabbed that timestamp column
	// and showed a date where the gantry should be. "location"/"gantry"/"plaza" + the
	// date/time exclusion pins it to the real Location column.
	$col_plaza = $find( [ 'location', 'gantry', 'plaza' ], [ 'date', 'time' ] );
	$col_acct  = $find( [ 'plate', 'license', 'tag', 'account' ], [ 'date', 'time' ] );

	// A single toll is a few dollars. Some exports interleave auxiliary/summary rows
	// (e.g. the tag/account number landing in the amount column), which surface as a
	// constant nonsense charge like $598,602 with no real plaza. Auto-expel anything
	// non-positive or above this ceiling so drivers never have to reject it by hand.
	$max_sane_toll = (float) site_pulse_get_setting( 'toll_max_sane', '100' );
	if ( $max_sane_toll <= 0 ) $max_sane_toll = 100.0;

	$rows = [];
	$seen = []; // dedupe keys of rows already kept, so exact repeats are dropped
	for ( $i = $header_idx + 1; $i < count( $records ); $i++ ) {
		$cells = $records[ $i ];
		$get   = fn( $c ) => ( $c >= 0 && isset( $cells[ $c ] ) ) ? site_pulse_toll_csv_clean( (string) $cells[ $c ] ) : '';

		$dt_raw = $get( $col_dt );
		$ts     = $dt_raw !== '' ? strtotime( $dt_raw ) : false;
		if ( $ts === false ) continue; // no usable date → can't bucket it to a day

		$amt_raw = $get( $col_amt );
		$amount  = abs( (float) preg_replace( '/[^0-9.\-]/', '', $amt_raw ) );
		if ( $amount <= 0 || $amount > $max_sane_toll ) { $skipped++; continue; } // obviously bogus → drop

		$external_id = $get( $col_id );
		$road        = $get( $col_road );
		$gantry      = $get( $col_plaza );
		$acct        = $get( $col_acct );

		// Exact duplicate? Same transaction id (if the export gives one), else identical
		// timestamp + road + gantry + amount + account. You can't pass one gantry twice in
		// the same second, so these are export artifacts — collapse to one, silently.
		$key = $external_id !== ''
			? 'id:' . $external_id
			: implode( '|', [ date( 'Y-m-d H:i:s', $ts ), $road, $gantry, number_format( $amount, 2, '.', '' ), $acct ] );
		if ( isset( $seen[ $key ] ) ) { $dupes++; continue; }
		$seen[ $key ] = true;

		// Preserve the statement's wall-clock date/time exactly as written (no timezone
		// conversion) — date() round-trips the strtotime() result in the same TZ, so a
		// late-night toll never slips to the wrong calendar day the way gmdate() could.
		$rows[] = [
			'external_id' => $external_id,
			'datetime'    => date( 'Y-m-d H:i:s', $ts ),
			'date'        => date( 'Y-m-d', $ts ),
			'road'        => $road,
			'gantry'      => $gantry,
			'amount'      => $amount,
			'acct'        => $acct,
		];
	}
	return $rows;
}

// Build the review payload for one day's entry directly from the staged transaction
// rows + legs, so the review screen and the apply step always agree. Includes the
// live TollGuru estimate per matched leg (cached) for the accuracy comparison.
function site_pulse_toll_day_proposal( int $entry_id, int $user_id ): array {
	global $wpdb;

	$entry = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, entry_date FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d AND user_id = %d",
		$entry_id, $user_id
	), ARRAY_A );
	if ( ! $entry ) return [];

	$legs = $wpdb->get_results( $wpdb->prepare(
		"SELECT l.id, l.leg_order, l.miles, lf.name AS from_name, lt.name AS to_name,
		        l.from_location_id, l.to_location_id
		 FROM " . site_pulse_table('mileage_legs') . " l
		 LEFT JOIN " . site_pulse_table('mileage_locations') . " lf ON lf.id = l.from_location_id
		 LEFT JOIN " . site_pulse_table('mileage_locations') . " lt ON lt.id = l.to_location_id
		 WHERE l.entry_id = %d ORDER BY l.leg_order ASC",
		$entry_id
	), ARRAY_A ) ?: [];

	$txns = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, txn_external_id, txn_datetime, road, gantry, amount, allocation_status, allocation_leg_id, allocation_note
		 FROM " . site_pulse_table('mileage_toll_transactions') . "
		 WHERE user_id = %d AND allocation_entry_id = %d
		   AND allocation_status != 'no_trip'
		 ORDER BY txn_datetime ASC, id ASC",
		$user_id, $entry_id
	), ARRAY_A ) ?: [];

	$by_leg = [];
	foreach ( $txns as $t ) {
		if ( $t['allocation_status'] === 'proposed_matched' || $t['allocation_status'] === 'matched' ) {
			$by_leg[ (int) $t['allocation_leg_id'] ][] = $t;
		}
	}

	$leg_out = [];
	$matched_total = 0.0;
	foreach ( $legs as $leg ) {
		$lid    = (int) $leg['id'];
		$mtxns  = $by_leg[ $lid ] ?? [];
		if ( ! $mtxns ) continue; // only show legs that got a toll
		$ltotal = 0.0;
		foreach ( $mtxns as $mt ) $ltotal += (float) $mt['amount'];
		$matched_total += $ltotal;
		$estimate = site_pulse_mileage_ensure_toll( (int) $leg['from_location_id'], (int) $leg['to_location_id'] );
		$leg_out[] = [
			'leg_id'       => $lid,
			'leg_order'    => (int) $leg['leg_order'],
			'from_name'    => $leg['from_name'],
			'to_name'      => $leg['to_name'],
			'miles'        => $leg['miles'] !== null ? (float) $leg['miles'] : null,
			'transactions' => array_map( fn( $t ) => [
				'id'       => (int) $t['id'],
				'datetime' => $t['txn_datetime'],
				'road'     => $t['road'],
				'gantry'   => $t['gantry'],
				'amount'   => (float) $t['amount'],
			], $mtxns ),
			'leg_total'    => round( $ltotal, 2 ),
			'estimate'     => $estimate === null ? null : round( $estimate, 2 ),
		];
	}

	$excluded = $ambiguous = [];
	foreach ( $txns as $t ) {
		$status = $t['allocation_status'];
		if ( $status === 'proposed_matched' || $status === 'matched' ) continue;
		$row = [
			'id'       => (int) $t['id'],
			'datetime' => $t['txn_datetime'],
			'road'     => $t['road'],
			'gantry'   => $t['gantry'],
			'amount'   => (float) $t['amount'],
			'reason'   => $t['allocation_note'],
		];
		if ( $status === 'proposed_ambiguous' ) $ambiguous[] = $row;
		else $excluded[] = $row; // proposed_excluded / unprocessed / excluded
	}

	// Every leg, for the review screen's "assign this charge to…" dropdown.
	$all_legs = array_map( fn( $l ) => [
		'leg_id'    => (int) $l['id'],
		'leg_order' => (int) $l['leg_order'],
		'label'     => $l['from_name'] . ' → ' . $l['to_name'],
	], $legs );

	return [
		'entry_id'      => $entry_id,
		'date'          => $entry['entry_date'],
		'legs'          => $leg_out,
		'all_legs'      => $all_legs,
		'excluded'      => $excluded,
		'ambiguous'     => $ambiguous,
		'matched_total' => round( $matched_total, 2 ),
	];
}

add_action( 'wp_ajax_site_pulse_upload_toll_csv', 'site_pulse_ajax_upload_toll_csv' );
function site_pulse_ajax_upload_toll_csv(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! site_pulse_user_can( $user_id, 'submit_mileage' ) && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$csv      = (string) wp_unslash( $_POST['csv'] ?? '' );
	$filename = sanitize_text_field( wp_unslash( $_POST['filename'] ?? '' ) );
	if ( trim( $csv ) === '' ) wp_send_json_error( [ 'message' => 'No file received.' ] );

	// CSV only. Enforce server-side too (the client accept= and JS check are bypassable):
	// require a .csv name and reject anything that looks binary (xlsx/pdf/etc. carry NUL bytes).
	if ( ! preg_match( '/\.csv$/i', $filename ) ) {
		wp_send_json_error( [ 'message' => 'Only .csv files are accepted. Export your toll statement as CSV and try again.' ] );
	}
	if ( strpos( $csv, "\0" ) !== false ) {
		wp_send_json_error( [ 'message' => "That file isn't a plain CSV (it looks like a spreadsheet or PDF). Export as CSV and try again." ] );
	}

	$skipped_invalid = 0;
	$skipped_dupes   = 0;
	$rows = site_pulse_parse_toll_csv( $csv, $skipped_invalid, $skipped_dupes );
	if ( ! $rows ) wp_send_json_error( [ 'message' => "Couldn't find any toll transactions in that file. Make sure it's the CSV export from your toll account." ] );

	$dates = array_values( array_unique( array_column( $rows, 'date' ) ) );
	sort( $dates );

	global $wpdb;

	// Which of those dates does this driver actually have a logged trip for?
	$ph      = implode( ',', array_fill( 0, count( $dates ), '%s' ) );
	$entries = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, entry_date FROM " . site_pulse_table('mileage_entries') . "
		 WHERE user_id = %d AND entry_date IN ( $ph )",
		array_merge( [ $user_id ], $dates )
	), ARRAY_A ) ?: [];
	$entry_by_date = [];
	foreach ( $entries as $e ) $entry_by_date[ $e['entry_date'] ] = (int) $e['id'];

	$now = current_time( 'mysql' );

	// Fresh start: clear this driver's prior UN-APPLIED toll rows before importing the new
	// file, so re-uploading is a clean reset instead of piling new transactions on top of old
	// ones. (Reconcile/"Re-run" only re-reads stored rows — it never re-parses the CSV — so
	// without this, garbage from an earlier broken-parser upload lingers forever.) Rows already
	// 'matched' (applied to a leg) are kept so we don't wipe reconciled history.
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM " . site_pulse_table('mileage_toll_transactions') . "
		 WHERE user_id = %d AND allocation_status != 'matched'",
		$user_id
	) );

	$wpdb->insert( site_pulse_table('mileage_toll_bills'), [
		'user_id'      => $user_id,
		'period_start' => $dates[0],
		'period_end'   => end( $dates ),
		'file_name'    => $filename,
		'txn_count'    => count( $rows ),
		'status'       => 'pending_review',
		'created_at'   => $now,
		'updated_at'   => $now,
	] );
	$bill_id = (int) $wpdb->insert_id;

	// Which of these logged days were ALREADY reconciled + applied in a prior session?
	// 'matched' rows survive the delete above, so a day with any matched toll is "done".
	// We keep that result and DON'T re-import its charges (no duplicates, no asking the
	// driver to redo it) — it surfaces as a done day with Review / Re-analyze.
	$applied = [];
	if ( $entry_by_date ) {
		$eids = array_values( $entry_by_date );
		$ph2  = implode( ',', array_fill( 0, count( $eids ), '%d' ) );
		$applied_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT allocation_entry_id FROM " . site_pulse_table('mileage_toll_transactions') . "
			 WHERE user_id = %d AND allocation_status = 'matched' AND allocation_entry_id IN ( $ph2 )",
			array_merge( [ $user_id ], $eids )
		) );
		foreach ( $applied_ids as $aid ) $applied[ (int) $aid ] = true;
	}

	$ignored = 0;
	foreach ( $rows as $r ) {
		$eid = $entry_by_date[ $r['date'] ] ?? 0;
		if ( $eid && isset( $applied[ $eid ] ) ) continue; // day already applied — keep it, don't duplicate
		$status = $eid ? 'unprocessed' : 'no_trip';
		if ( ! $eid ) $ignored++;
		$wpdb->insert( site_pulse_table('mileage_toll_transactions'), [
			'bill_id'             => $bill_id,
			'user_id'             => $user_id,
			'txn_external_id'     => $r['external_id'],
			'txn_datetime'        => $r['datetime'],
			'road'                => $r['road'],
			'gantry'              => $r['gantry'],
			'internal_code_prefix'=> $r['acct'],
			'amount'              => $r['amount'],
			'allocation_status'   => $status,
			'allocation_entry_id' => $eid ?: null,
			'created_at'          => $now,
			'updated_at'          => $now,
		] );
	}

	// Day list: already-applied days are marked done (with their stored total); the rest
	// are pending analysis.
	$days = [];
	foreach ( $entry_by_date as $date => $eid ) {
		if ( isset( $applied[ $eid ] ) ) {
			$tt = (float) $wpdb->get_var( $wpdb->prepare(
				"SELECT total_tolls FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d", $eid ) );
			$mc = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM " . site_pulse_table('mileage_toll_transactions') . " WHERE allocation_entry_id = %d AND allocation_status = 'matched'", $eid ) );
			$days[] = [ 'entry_id' => $eid, 'date' => $date, 'txn_count' => $mc, 'applied' => true, 'total_tolls' => round( $tt, 2 ) ];
		} else {
			$cnt = 0;
			foreach ( $rows as $r ) if ( $r['date'] === $date ) $cnt++;
			if ( $cnt > 0 ) $days[] = [ 'entry_id' => $eid, 'date' => $date, 'txn_count' => $cnt, 'applied' => false, 'total_tolls' => 0 ];
		}
	}
	usort( $days, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

	wp_send_json_success( [
		'bill_id'         => $bill_id,
		'days'            => $days,
		'ignored_count'   => $ignored,
		'skipped_invalid' => $skipped_invalid,
		'skipped_dupes'   => $skipped_dupes,
		'total_txns'      => count( $rows ),
	] );
}

// Read-only: return the already-stored allocation for a day (no AI call) so the driver can
// "Review" what was found/applied without spending an API call re-analyzing.
add_action( 'wp_ajax_site_pulse_get_toll_day', 'site_pulse_ajax_get_toll_day' );
function site_pulse_ajax_get_toll_day(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$entry_id = (int) ( $_POST['entry_id'] ?? 0 );
	if ( ! site_pulse_user_can( $user_id, 'submit_mileage' ) && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}
	$proposal = site_pulse_toll_day_proposal( $entry_id, $user_id );
	if ( ! $proposal ) wp_send_json_error( [ 'message' => 'Trip not found.' ] );
	wp_send_json_success( $proposal );
}

add_action( 'wp_ajax_site_pulse_reconcile_toll_day', 'site_pulse_ajax_reconcile_toll_day' );
function site_pulse_ajax_reconcile_toll_day(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$entry_id = (int) ( $_POST['entry_id'] ?? 0 );
	if ( ! site_pulse_user_can( $user_id, 'submit_mileage' ) && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}
	if ( ! site_pulse_get_api_key() ) {
		wp_send_json_error( [ 'message' => 'No Claude API key is configured for this site, so AI toll matching is unavailable. Add one in Site Settings.' ] );
	}

	global $wpdb;
	$entry = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, entry_date FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d AND user_id = %d",
		$entry_id, $user_id
	), ARRAY_A );
	if ( ! $entry ) wp_send_json_error( [ 'message' => 'Trip not found.' ] );

	$legs = $wpdb->get_results( $wpdb->prepare(
		"SELECT l.id, l.leg_order, l.miles, lf.name AS from_name, lf.address AS from_addr, lf.lat AS from_lat, lf.lng AS from_lng,
		        lt.name AS to_name, lt.address AS to_addr, lt.lat AS to_lat, lt.lng AS to_lng
		 FROM " . site_pulse_table('mileage_legs') . " l
		 LEFT JOIN " . site_pulse_table('mileage_locations') . " lf ON lf.id = l.from_location_id
		 LEFT JOIN " . site_pulse_table('mileage_locations') . " lt ON lt.id = l.to_location_id
		 WHERE l.entry_id = %d ORDER BY l.leg_order ASC",
		$entry_id
	), ARRAY_A ) ?: [];
	if ( ! $legs ) wp_send_json_error( [ 'message' => 'This trip has no route legs to match against.' ] );

	// Re-runnable: reconcile considers every charge for this day (even ones a previous
	// pass matched or excluded), so "Review again" after applying works.
	$txns = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, txn_datetime, road, gantry, amount, internal_code_prefix
		 FROM " . site_pulse_table('mileage_toll_transactions') . "
		 WHERE user_id = %d AND allocation_entry_id = %d AND allocation_status != 'no_trip'
		 ORDER BY txn_datetime ASC, id ASC",
		$user_id, $entry_id
	), ARRAY_A ) ?: [];
	if ( ! $txns ) wp_send_json_error( [ 'message' => 'No toll charges for this day.' ] );

	// Build the route + charges text for the model.
	$leg_by_order = [];
	$route_txt = '';
	foreach ( $legs as $leg ) {
		$leg_by_order[ (int) $leg['leg_order'] ] = (int) $leg['id'];
		$from = $leg['from_name'] . ( $leg['from_addr'] ? ' — ' . $leg['from_addr'] : '' );
		$to   = $leg['to_name']   . ( $leg['to_addr']   ? ' — ' . $leg['to_addr']   : '' );
		$flat = ( $leg['from_lat'] !== null && $leg['from_lng'] !== null ) ? " [{$leg['from_lat']},{$leg['from_lng']}]" : '';
		$tlat = ( $leg['to_lat']   !== null && $leg['to_lng']   !== null ) ? " [{$leg['to_lat']},{$leg['to_lng']}]"   : '';
		$miles = $leg['miles'] !== null ? ' (' . (float) $leg['miles'] . ' mi)' : '';
		$route_txt .= "Leg {$leg['leg_order']}: {$from}{$flat}  ->  {$to}{$tlat}{$miles}\n";
	}
	$txn_txt = '';
	foreach ( $txns as $t ) {
		$txn_txt .= "ID {$t['id']} | {$t['txn_datetime']} | road: {$t['road']} | gantry: {$t['gantry']} | \$" . number_format( (float) $t['amount'], 2 ) . " | acct: {$t['internal_code_prefix']}\n";
	}

	$system = "You are reconciling a driver's toll charges against the route they drove that day. Cross-reference each toll charge against the route.\n"
		. "1. Match each toll charge to a specific leg of the trip based on the gantry location, tollway/road, and timestamp.\n"
		. "2. Do not assume a trip uses tolls in both directions. A driver may take a tolled route one way and a non-tolled (free) route the other, so a leg with no tolls is normal — do not treat a missing outbound or missing return as a problem to explain, and do not flag charges as out-of-sequence merely because the opposite leg has no tolls to mirror them against.\n"
		. "3. Flag any charge that doesn't map cleanly to the route — anything off-geography, out of sequence in the timeline, sitting in an unexplained time gap, or carrying a different transaction/account ID than the rest.\n"
		. "4. Total only the tolls that belong to this trip. Report excluded charges separately with the reason each was excluded.\n"
		. "5. Do not force a charge to fit by inventing an explanation for it. Equally, do not invent a problem where none exists — a one-way-only toll pattern is expected, not suspect. If something is genuinely ambiguous, list it as ambiguous rather than including or excluding it silently.\n\n"
		. "Respond with ONLY a JSON object — no prose, no markdown code fences — in exactly this shape:\n"
		. '{"legs":[{"leg_order":<int>,"transaction_ids":[<int>,...]}],"excluded":[{"transaction_id":<int>,"reason":"<short reason>"}],"ambiguous":[{"transaction_id":<int>,"reason":"<short reason>"}]}' . "\n"
		. "Only include legs that have at least one matched charge. Use the exact ID numbers given. Every charge must appear exactly once across legs, excluded, or ambiguous.";

	$prompt = "ROUTE FOR " . $entry['entry_date'] . ":\n" . $route_txt . "\nTOLL CHARGES:\n" . $txn_txt;

	$ai_debug = null;
	$raw = site_pulse_call_claude( $prompt, $system, [ 'max_tokens' => 4096, 'timeout' => 60 ], $ai_debug );
	if ( $raw === null ) {
		$detail = $ai_debug ? ' (' . $ai_debug . ')' : '';
		wp_send_json_error( [ 'message' => 'The AI matching service did not respond' . $detail . '. Try again in a moment.' ] );
	}

	// Tolerant JSON parse: strip code fences / surrounding prose.
	$json = trim( $raw );
	$json = preg_replace( '/^```(?:json)?|```$/m', '', $json );
	if ( preg_match( '/\{.*\}/s', $json, $m ) ) $json = $m[0];
	$result = json_decode( $json, true );
	if ( ! is_array( $result ) ) {
		site_pulse_log( 'toll_error', 'Toll reconcile: unparseable AI response', [ 'raw' => $raw ] );
		wp_send_json_error( [ 'message' => 'The AI returned an unexpected response. Try again.' ] );
	}

	$valid_ids = array_map( fn( $t ) => (int) $t['id'], $txns );
	$now       = current_time( 'mysql' );

	// Reset this day's staging so a re-run starts clean (matched/excluded included).
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . site_pulse_table('mileage_toll_transactions') . "
		 SET allocation_status = 'unprocessed', allocation_leg_id = NULL, allocation_note = NULL, updated_at = %s
		 WHERE user_id = %d AND allocation_entry_id = %d AND allocation_status != 'no_trip'",
		$now, $user_id, $entry_id
	) );

	$stage = function( int $txn_id, string $status, ?int $leg_id, string $note ) use ( $wpdb, $user_id, $entry_id, $valid_ids, $now ) {
		if ( ! in_array( $txn_id, $valid_ids, true ) ) return;
		$wpdb->update( site_pulse_table('mileage_toll_transactions'),
			[ 'allocation_status' => $status, 'allocation_leg_id' => $leg_id, 'allocation_note' => $note, 'updated_at' => $now ],
			[ 'id' => $txn_id, 'user_id' => $user_id, 'allocation_entry_id' => $entry_id ]
		);
	};

	foreach ( $result['legs'] ?? [] as $leg ) {
		$lid = $leg_by_order[ (int) ( $leg['leg_order'] ?? 0 ) ] ?? null;
		if ( ! $lid ) continue;
		foreach ( (array) ( $leg['transaction_ids'] ?? [] ) as $tid ) {
			$stage( (int) $tid, 'proposed_matched', $lid, '' );
		}
	}
	foreach ( $result['excluded'] ?? [] as $ex ) {
		$stage( (int) ( $ex['transaction_id'] ?? 0 ), 'proposed_excluded', null, sanitize_text_field( $ex['reason'] ?? '' ) );
	}
	foreach ( $result['ambiguous'] ?? [] as $am ) {
		$stage( (int) ( $am['transaction_id'] ?? 0 ), 'proposed_ambiguous', null, sanitize_text_field( $am['reason'] ?? '' ) );
	}
	// Any charge the model didn't address → surface it rather than silently dropping it.
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . site_pulse_table('mileage_toll_transactions') . "
		 SET allocation_status = 'proposed_ambiguous', allocation_note = 'Not addressed by AI — review manually', updated_at = %s
		 WHERE user_id = %d AND allocation_entry_id = %d AND allocation_status = 'unprocessed'",
		$now, $user_id, $entry_id
	) );

	wp_send_json_success( site_pulse_toll_day_proposal( $entry_id, $user_id ) );
}

add_action( 'wp_ajax_site_pulse_apply_toll_day', 'site_pulse_ajax_apply_toll_day' );
function site_pulse_ajax_apply_toll_day(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$entry_id = (int) ( $_POST['entry_id'] ?? 0 );
	if ( ! site_pulse_user_can( $user_id, 'submit_mileage' ) && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	global $wpdb;
	$entry = $wpdb->get_row( $wpdb->prepare(
		"SELECT id FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d AND user_id = %d",
		$entry_id, $user_id
	), ARRAY_A );
	if ( ! $entry ) wp_send_json_error( [ 'message' => 'Trip not found.' ] );

	$now = current_time( 'mysql' );

	// Optional driver overrides from the review screen: a list of { id, status, leg_id }.
	// status is 'matched' or 'excluded'; leg_id required when matched.
	$decisions_raw = wp_unslash( $_POST['decisions'] ?? '' );
	$decisions     = is_string( $decisions_raw ) && $decisions_raw !== '' ? json_decode( $decisions_raw, true ) : [];
	if ( is_array( $decisions ) ) {
		foreach ( $decisions as $d ) {
			$tid = (int) ( $d['id'] ?? 0 );
			if ( ! $tid ) continue;
			$status = ( ( $d['status'] ?? '' ) === 'matched' ) ? 'proposed_matched' : 'proposed_excluded';
			$leg_id = $status === 'proposed_matched' ? ( (int) ( $d['leg_id'] ?? 0 ) ?: null ) : null;
			$wpdb->update( site_pulse_table('mileage_toll_transactions'),
				[ 'allocation_status' => $status, 'allocation_leg_id' => $leg_id, 'updated_at' => $now ],
				[ 'id' => $tid, 'user_id' => $user_id, 'allocation_entry_id' => $entry_id ]
			);
		}
	}

	// Finalize: proposed_matched → matched, everything else for this day → excluded.
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . site_pulse_table('mileage_toll_transactions') . "
		 SET allocation_status = 'matched', updated_at = %s
		 WHERE user_id = %d AND allocation_entry_id = %d AND allocation_status = 'proposed_matched'",
		$now, $user_id, $entry_id
	) );
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . site_pulse_table('mileage_toll_transactions') . "
		 SET allocation_status = 'excluded', allocation_leg_id = NULL, updated_at = %s
		 WHERE user_id = %d AND allocation_entry_id = %d AND allocation_status IN ( 'proposed_excluded', 'proposed_ambiguous', 'unprocessed' )",
		$now, $user_id, $entry_id
	) );

	// Write the reimbursable toll per leg (CSV actual) + the TollGuru comparison estimate.
	$legs = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, from_location_id, to_location_id FROM " . site_pulse_table('mileage_legs') . " WHERE entry_id = %d",
		$entry_id
	), ARRAY_A ) ?: [];
	foreach ( $legs as $leg ) {
		$lid = (int) $leg['id'];
		$sum = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(amount) FROM " . site_pulse_table('mileage_toll_transactions') . "
			 WHERE allocation_leg_id = %d AND allocation_status = 'matched'",
			$lid
		) );
		if ( $sum !== null ) {
			$estimate = site_pulse_mileage_ensure_toll( (int) $leg['from_location_id'], (int) $leg['to_location_id'] );
			$wpdb->update( site_pulse_table('mileage_legs'),
				[ 'has_toll' => 1, 'toll_cost' => round( (float) $sum, 2 ), 'toll_estimate' => $estimate === null ? null : round( $estimate, 2 ) ],
				[ 'id' => $lid ]
			);
		} else {
			$wpdb->update( site_pulse_table('mileage_legs'),
				[ 'has_toll' => 0, 'toll_cost' => null, 'toll_estimate' => null ],
				[ 'id' => $lid ]
			);
		}
	}

	site_pulse_mileage_recalc_entry( $entry_id );

	$total_tolls = (float) $wpdb->get_var( $wpdb->prepare(
		"SELECT total_tolls FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d", $entry_id
	) );

	wp_send_json_success( [ 'entry_id' => $entry_id, 'total_tolls' => round( $total_tolls, 2 ) ] );
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
		"SELECT id, name, address, lat, lng, location_type, is_private, category, code, is_business, is_active, pinned_purposes, marker_icon, status, created_by
		 FROM " . site_pulse_table('mileage_locations') . "
		 WHERE ( status = 'approved' OR ( status = 'pending' AND created_by = %d ) )
		   AND is_active = 1
		   AND ( is_private = 0 OR id = %d OR created_by = %d )
		 ORDER BY status DESC, location_type, name",
		$user_id, $home_id, $user_id
	), ARRAY_A ) ?: [];

	// "Charge To" options: every Home Base (store) that has a Location # set, so a driver can
	// bill each leg to a specific store. The client also appends a fixed "Misc (#99)" option.
	$charge_options = $wpdb->get_results(
		"SELECT location_number AS number, name FROM " . site_pulse_table('locations') . "
		 WHERE status = 'active' AND location_number IS NOT NULL AND location_number != ''
		 ORDER BY name",
		ARRAY_A
	) ?: [];

	wp_send_json_success( [
		'locations'        => $rows,
		'charge_options'   => $charge_options,
		'rate'             => site_pulse_mileage_rate(),
		'home_location_id' => $home_id,
		'require_approval' => site_pulse_get_setting( 'mileage_require_approval', '1' ) === '1',
		'purposes'         => site_pulse_mileage_purposes(),
		'marker_icons'     => site_pulse_mileage_marker_icons(),
		'period_length'    => (int) site_pulse_get_setting( 'mileage_period_length', '0' ),
		'period_anchor'    => site_pulse_get_setting( 'mileage_period_anchor', '' ),
	] );
}

add_action( 'wp_ajax_site_pulse_add_mileage_location', 'site_pulse_ajax_add_mileage_location' );
function site_pulse_ajax_add_mileage_location(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );

	$user_id     = site_pulse_effective_user_id();
	// wp_unslash() before sanitizing so an apostrophe (arriving as \') isn't stored as Babe\'s.
	$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$address     = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );
	$type        = sanitize_text_field( wp_unslash( $_POST['location_type'] ?? 'vendor' ) );
	$category    = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
	$is_business = isset( $_POST['is_business'] ) ? ( (int) $_POST['is_business'] ? 1 : 0 ) : 1;
	$notes       = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

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

	$msg = sprintf( 'New mileage location pending approval: %s', $name );
	site_pulse_dispatch_notification( 'mileage_pending', $user_id, $msg, $id, 'mileage_location' );

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

	// A day is "pending" (not Final) when any leg is incomplete: no computed distance (an
	// unapproved destination), OR no "Charge To" on any leg, OR no Business Purpose on any leg
	// that arrives at a real stop (the drive-home leg needs a charge but carries no purpose).
	// Resolve the user's ACTUAL home base — for store-based drivers it's derived from their
	// store Location, not the raw mileage_home_location_id (which is 0 for them); using the raw
	// value left every home leg looking purpose-less and kept fully-filled days stuck "Pending".
	$home_id = site_pulse_user_home_location_id( $user_id );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.*, ( SELECT COUNT(*) FROM " . site_pulse_table('mileage_legs') . " l
		      WHERE l.entry_id = e.id
		        AND ( l.miles IS NULL
		           OR l.charge_to IS NULL OR l.charge_to = ''
		           OR ( ( l.purpose IS NULL OR l.purpose = '' ) AND l.to_location_id <> %d ) )
		    ) AS pending_legs
		 FROM " . site_pulse_table('mileage_entries') . " e
		 $where
		 ORDER BY e.entry_date DESC, e.id DESC
		 LIMIT 100",
		...array_merge( [ $home_id ], $values )
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
			"SELECT l.purpose, l.miles, l.miles_adjust, l.toll_cost, l.charge_to, lf.name AS from_name, lt.name AS to_name
			 FROM " . site_pulse_table('mileage_legs') . " l
			 LEFT JOIN " . site_pulse_table('mileage_locations') . " lf ON lf.id = l.from_location_id
			 LEFT JOIN " . site_pulse_table('mileage_locations') . " lt ON lt.id = l.to_location_id
			 WHERE l.entry_id = %d
			 ORDER BY l.leg_order, l.id",
			(int) $e['id']
		), ARRAY_A ) ?: [];
		$route       = '';
		// [{ name, purpose, miles, toll }] — the leg's own effective miles + matched toll, so the
		// PDF/CSV can break each leg out (origin stop carries no miles/toll). purpose = the reason
		// for arriving at that stop.
		$route_stops = [];
		$purposes    = [];
		if ( $legs ) {
			$route         = $legs[0]['from_name'] ?? '?';
			$route_stops[] = [ 'name' => $legs[0]['from_name'] ?? '?', 'purpose' => '', 'miles' => null, 'toll' => null, 'charge_to' => '' ];
			foreach ( $legs as $lg ) {
				$route        .= ' → ' . ( $lg['to_name'] ?? '?' );
				$p = trim( (string) ( $lg['purpose'] ?? '' ) );
				$miles = ( $lg['miles'] === null ) ? null : round( (float) $lg['miles'] + (float) ( $lg['miles_adjust'] ?? 0 ), 2 );
				$toll  = ( $lg['toll_cost'] === null ) ? null : (float) $lg['toll_cost'];
				$route_stops[] = [ 'name' => $lg['to_name'] ?? '?', 'purpose' => $p, 'miles' => $miles, 'toll' => $toll, 'charge_to' => (string) ( $lg['charge_to'] ?? '' ) ];
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

	// Home base / store, shown under the employee name. Prefer the assigned store (location);
	// fall back to the mileage home base, masking a private home address as "Home Office".
	$home_label = (string) $wpdb->get_var( $wpdb->prepare(
		"SELECT l.name FROM " . site_pulse_table('user_profiles') . " up
		 LEFT JOIN " . site_pulse_table('locations') . " l ON l.id = up.location_id
		 WHERE up.user_id = %d",
		$user_id
	) );
	if ( $home_label === '' ) {
		$hb = $wpdb->get_row( $wpdb->prepare(
			"SELECT name, is_private FROM " . site_pulse_table('mileage_locations') . " WHERE id = %d",
			site_pulse_user_home_location_id( $user_id )
		), ARRAY_A );
		if ( $hb ) $home_label = (int) $hb['is_private'] ? 'Home Office' : (string) $hb['name'];
	}

	// Section B — vehicle expenses for the same period, so the composed PDF has everything
	// in one round-trip. (Sections C–F will join here as they're built.)
	$bwhere  = "WHERE user_id = %d AND section = 'B'";
	$bvalues = [ $user_id ];
	if ( $start ) { $bwhere .= " AND expense_date >= %s"; $bvalues[] = $start; }
	if ( $end )   { $bwhere .= " AND expense_date <= %s"; $bvalues[] = $end; }
	$vehicle = $wpdb->get_results( $wpdb->prepare(
		"SELECT expense_date, category, description, amount, receipt_path FROM " . site_pulse_table('expenses') . "
		 $bwhere ORDER BY expense_date ASC, id ASC LIMIT 500",
		...$bvalues
	), ARRAY_A ) ?: [];

	// Section C — business meals for the same period (Date · Place · Purpose · Attendees · Store · Amount).
	$cwhere  = "WHERE user_id = %d AND section = 'C'";
	$cvalues = [ $user_id ];
	if ( $start ) { $cwhere .= " AND expense_date >= %s"; $cvalues[] = $start; }
	if ( $end )   { $cwhere .= " AND expense_date <= %s"; $cvalues[] = $end; }
	$meals = $wpdb->get_results( $wpdb->prepare(
		"SELECT expense_date, place, business_purpose, attendees, store_number, amount, receipt_path FROM " . site_pulse_table('expenses') . "
		 $cwhere ORDER BY expense_date ASC, id ASC LIMIT 500",
		...$cvalues
	), ARRAY_A ) ?: [];

	// Section D — competitive shopping for the same period (Date · Place · Purpose · Store · Amount).
	$dwhere  = "WHERE user_id = %d AND section = 'D'";
	$dvalues = [ $user_id ];
	if ( $start ) { $dwhere .= " AND expense_date >= %s"; $dvalues[] = $start; }
	if ( $end )   { $dwhere .= " AND expense_date <= %s"; $dvalues[] = $end; }
	$shopping = $wpdb->get_results( $wpdb->prepare(
		"SELECT expense_date, place, business_purpose, store_number, amount, receipt_path FROM " . site_pulse_table('expenses') . "
		 $dwhere ORDER BY expense_date ASC, id ASC LIMIT 500",
		...$dvalues
	), ARRAY_A ) ?: [];

	// Section E — other expenses for the same period (Date · Description · Account · Store · Amount).
	$ewhere  = "WHERE user_id = %d AND section = 'E'";
	$evalues = [ $user_id ];
	if ( $start ) { $ewhere .= " AND expense_date >= %s"; $evalues[] = $start; }
	if ( $end )   { $ewhere .= " AND expense_date <= %s"; $evalues[] = $end; }
	$other = $wpdb->get_results( $wpdb->prepare(
		"SELECT expense_date, description, account_code, store_number, amount, receipt_path FROM " . site_pulse_table('expenses') . "
		 $ewhere ORDER BY expense_date ASC, id ASC LIMIT 500",
		...$evalues
	), ARRAY_A ) ?: [];

	// Surface a public receipt URL per expense line so the PDF can append the photos.
	foreach ( [ &$vehicle, &$meals, &$shopping, &$other ] as &$_set ) {
		foreach ( $_set as &$_row ) { $_row['receipt_url'] = site_pulse_receipt_url( (string) ( $_row['receipt_path'] ?? '' ) ); }
		unset( $_row );
	}
	unset( $_set );

	wp_send_json_success( [
		'entries'             => $entries,
		'rate'                => site_pulse_mileage_rate(),
		'user_name'           => $user ? $user->display_name : '',
		'home_label'          => $home_label,
		'app_name'            => site_pulse_get_setting( 'app_name', 'Site Pulse' ),
		'company_name'        => site_pulse_get_setting( 'company_name', '' ),
		'vehicle_expenses'    => $vehicle,
		'vehicle_categories'  => site_pulse_expense_sections()['B']['categories'],
		'business_meals'      => $meals,
		'competitive_shopping'=> $shopping,
		'other_expenses'      => $other,
	] );
}

/*--------------------------------------------------------------
# Expense line items (sections B–F of the reimbursement form)
--------------------------------------------------------------*/

/**
 * The expense-report sections and their categories. Each category carries the GL account code
 * the company's Summary of Expenses keys on. Sections C–F get added here as they're built.
 */
function site_pulse_expense_sections(): array {
	return [
		'B' => [
			'label'      => 'Vehicle Expenses',
			'categories' => [
				'fuel'     => [ 'label' => 'Fuel',                   'account' => '91300' ],
				'wash'     => [ 'label' => 'Wash',                   'account' => '91300' ],
				'parking'  => [ 'label' => 'Parking',               'account' => '91300' ],
				'repairs'  => [ 'label' => 'Company Driven Repairs', 'account' => '91310' ],
				'trailers' => [ 'label' => 'Trailers',              'account' => '91310' ],
			],
		],
		'C' => [
			'label'      => 'Business Meals',
			// One implicit category — the paper form has no category column for C; everything
			// posts to GL 91110 (Meals & Entertainment). The UI auto-sends category 'meals'.
			'categories' => [
				'meals' => [ 'label' => 'Meals & Entertainment', 'account' => '91110' ],
			],
		],
		'D' => [
			'label'      => 'Competitive Shopping',
			// One implicit category — like C, no category column on the paper form. Everything
			// posts to GL 81095 (R&D-Food; the Summary labels it "D - R&D-Food"). UI sends 'shopping'.
			'categories' => [
				'shopping' => [ 'label' => 'R&D-Food', 'account' => '81095' ],
			],
		],
		'E' => [
			'label'      => 'Other Expenses',
			// NO fixed categories — the paper form's Account column is free-entry per line (Food
			// R&D, Home Office, Postage, etc. each carry their own GL account, which is why the
			// Summary of Expenses just reads "See Section E"). The UI sends a per-row account_code.
			'categories' => [],
		],
	];
}

function site_pulse_expense_account( string $section, string $category ): string {
	return site_pulse_expense_sections()[ $section ]['categories'][ $category ]['account'] ?? '';
}

add_action( 'wp_ajax_site_pulse_get_expenses', 'site_pulse_ajax_get_expenses' );
function site_pulse_ajax_get_expenses(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! site_pulse_user_can( $user_id, 'submit_mileage' ) && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	$section  = sanitize_text_field( $_POST['section'] ?? 'B' );
	$sections = site_pulse_expense_sections();
	if ( ! isset( $sections[ $section ] ) ) wp_send_json_error( [ 'message' => 'Unknown expense section.' ] );

	$start = sanitize_text_field( $_POST['start'] ?? '' );
	$end   = sanitize_text_field( $_POST['end']   ?? '' );

	global $wpdb;
	$where  = "WHERE user_id = %d AND section = %s";
	$values = [ $user_id, $section ];
	if ( $start ) { $where .= " AND expense_date >= %s"; $values[] = $start; }
	if ( $end )   { $where .= " AND expense_date <= %s"; $values[] = $end; }

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('expenses') . " $where ORDER BY expense_date ASC, id ASC LIMIT 500",
		...$values
	), ARRAY_A ) ?: [];
	foreach ( $rows as &$r ) { $r['receipt_url'] = site_pulse_receipt_url( (string) ( $r['receipt_path'] ?? '' ) ); }
	unset( $r );

	wp_send_json_success( [ 'expenses' => $rows, 'categories' => $sections[ $section ]['categories'] ] );
}

add_action( 'wp_ajax_site_pulse_save_expense', 'site_pulse_ajax_save_expense' );
function site_pulse_ajax_save_expense(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! site_pulse_user_can( $user_id, 'submit_mileage' ) && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	global $wpdb;
	$id       = (int) ( $_POST['id'] ?? 0 );
	$section  = sanitize_text_field( $_POST['section'] ?? 'B' );
	$sections = site_pulse_expense_sections();
	if ( ! isset( $sections[ $section ] ) ) wp_send_json_error( [ 'message' => 'Unknown expense section.' ] );

	$category = sanitize_text_field( $_POST['category'] ?? '' );
	if ( $sections[ $section ]['categories'] && ! isset( $sections[ $section ]['categories'][ $category ] ) ) {
		wp_send_json_error( [ 'message' => 'Please choose a category.' ] );
	}

	$date = sanitize_text_field( $_POST['expense_date'] ?? '' );
	if ( ! $date ) wp_send_json_error( [ 'message' => 'A date is required.' ] );

	$amount = round( (float) ( $_POST['amount'] ?? 0 ), 2 );
	if ( $amount <= 0 ) wp_send_json_error( [ 'message' => 'Enter an amount greater than zero.' ] );

	$now  = current_time( 'mysql' );
	$data = [
		'user_id'          => $user_id,
		'section'          => $section,
		'expense_date'     => $date,
		'category'         => $category,
		// wp_unslash() before sanitizing: WordPress slash-escapes $_POST, so without it an
		// apostrophe ("Wendy's") gets stored with a literal backslash ("Wendy\'s").
		'description'      => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ),
		'place'            => sanitize_text_field( wp_unslash( $_POST['place'] ?? '' ) ),
		'business_purpose' => sanitize_text_field( wp_unslash( $_POST['business_purpose'] ?? '' ) ),
		'attendees'        => sanitize_text_field( wp_unslash( $_POST['attendees'] ?? '' ) ),
		'store_number'     => sanitize_text_field( wp_unslash( $_POST['store_number'] ?? '' ) ),
		// Sections with a fixed category list derive the GL account from the category; sections
		// without one (E) take the per-line account the user entered.
		'account_code'     => $sections[ $section ]['categories']
			? site_pulse_expense_account( $section, $category )
			: sanitize_text_field( wp_unslash( $_POST['account_code'] ?? '' ) ),
		'amount'           => $amount,
		'updated_at'       => $now,
	];

	$is_admin = site_pulse_user_can( $user_id, 'manage_mileage' ) || site_pulse_is_god( get_current_user_id() );
	$old_receipt = '';
	if ( $id ) {
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT user_id, receipt_path FROM " . site_pulse_table('expenses') . " WHERE id = %d", $id ), ARRAY_A );
		if ( ! $existing ) wp_send_json_error( [ 'message' => 'Expense not found.' ] );
		if ( (int) $existing['user_id'] !== $user_id && ! $is_admin ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );
		$old_receipt = (string) ( $existing['receipt_path'] ?? '' );
	}

	// Receipt image: a new base64 photo replaces; receipt_remove clears; otherwise the existing
	// one is left untouched (receipt_path simply omitted from $data on update).
	if ( ! empty( $_POST['receipt'] ) ) {
		$saved = site_pulse_save_receipt_image( $user_id, (string) $_POST['receipt'] );
		if ( $saved !== '' ) {
			$data['receipt_path'] = $saved;
			if ( $old_receipt !== '' && $old_receipt !== $saved ) site_pulse_delete_receipt_file( $old_receipt );
		}
	} elseif ( ! empty( $_POST['receipt_remove'] ) ) {
		$data['receipt_path'] = '';
		if ( $old_receipt !== '' ) site_pulse_delete_receipt_file( $old_receipt );
	}

	if ( $id ) {
		$wpdb->update( site_pulse_table('expenses'), $data, [ 'id' => $id ] );
	} else {
		$data['created_at'] = $now;
		$wpdb->insert( site_pulse_table('expenses'), $data );
		$id = (int) $wpdb->insert_id;
	}

	wp_send_json_success( [ 'id' => $id, 'receipt_url' => site_pulse_receipt_url( $data['receipt_path'] ?? $old_receipt ) ] );
}

/* ============================================================================
   Forms library — upload / list / replace / delete (uploads/sp-forms/<random>.<ext>)
   View gated on 'view_forms'; create gated on 'upload_forms'; delete/replace limited
   to the uploader or a god. Forms are shared company-wide, filed under a repository
   (training / kitchen / foh / misc).
   ============================================================================ */

// The repositories a brand-new install starts with. Editable via Settings → Forms.
function site_pulse_form_default_categories(): array {
	return [ 'training' => 'Training', 'kitchen' => 'Kitchen', 'foh' => 'FOH', 'misc' => 'Misc' ];
}

// The live repositories (key => label), ordered. Stored as a JSON [{key,label},…] setting so
// admins can add / rename / delete them; falls back to the defaults when unset. Per-request cached.
function site_pulse_form_categories(): array {
	static $cache = null;
	if ( $cache !== null ) return $cache;

	$raw = site_pulse_get_setting( 'form_categories', '' );
	if ( $raw !== '' ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$out = [];
			foreach ( $decoded as $c ) {
				if ( ! is_array( $c ) ) continue;
				$key   = sanitize_key( (string) ( $c['key'] ?? '' ) );
				$label = trim( (string) ( $c['label'] ?? '' ) );
				if ( $key !== '' && $label !== '' ) $out[ $key ] = $label;
			}
			if ( $out ) { $cache = $out; return $cache; }
		}
	}
	$cache = site_pulse_form_default_categories();
	return $cache;
}

// The full nested structure: [ catKey => [ 'label' => …, 'children' => [ subKey => subLabel ] ] ].
// Children come from each stored category's optional `children` array; categories without
// sub-folders get an empty children list. Drives the 3-level nav + sub-folder pickers/filters.
function site_pulse_form_category_tree(): array {
	$flat = site_pulse_form_categories();             // top-level key => label (validated, ordered)
	$raw  = site_pulse_get_setting( 'form_categories', '' );
	$kids = [];
	if ( $raw !== '' ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $c ) {
				if ( ! is_array( $c ) ) continue;
				$ckey = sanitize_key( (string) ( $c['key'] ?? '' ) );
				if ( $ckey === '' ) continue;
				$children = [];
				foreach ( (array) ( $c['children'] ?? [] ) as $sub ) {
					if ( ! is_array( $sub ) ) continue;
					$skey   = sanitize_key( (string) ( $sub['key'] ?? '' ) );
					$slabel = trim( (string) ( $sub['label'] ?? '' ) );
					if ( $skey !== '' && $slabel !== '' ) $children[ $skey ] = $slabel;
				}
				$kids[ $ckey ] = $children;
			}
		}
	}
	$tree = [];
	foreach ( $flat as $key => $label ) {
		$tree[ $key ] = [ 'label' => $label, 'children' => $kids[ $key ] ?? [] ];
	}
	return $tree;
}

// Sub-folder map [subKey => subLabel] for one category (empty if it has none).
function site_pulse_form_subfolders( string $category ): array {
	$tree = site_pulse_form_category_tree();
	return $tree[ $category ]['children'] ?? [];
}

// Allowed upload extensions → the friendly "Format" family shown in the list.
function site_pulse_form_formats(): array {
	return [
		'pdf'  => 'PDF',
		'doc'  => 'Word',  'docx' => 'Word',
		'xls'  => 'Excel', 'xlsx' => 'Excel', 'csv' => 'Excel',
		'ppt'  => 'PowerPoint', 'pptx' => 'PowerPoint',
		'txt'  => 'Text',
		'png'  => 'Image', 'jpg' => 'Image', 'jpeg' => 'Image', 'gif' => 'Image', 'webp' => 'Image',
	];
}

function site_pulse_forms_basedir(): array {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'sp-forms';
	if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
	return [ 'dir' => $dir, 'url' => trailingslashit( $up['baseurl'] ) . 'sp-forms' ];
}

function site_pulse_form_url( string $path ): string {
	if ( $path === '' ) return '';
	$up = wp_upload_dir();
	return trailingslashit( $up['baseurl'] ) . ltrim( $path, '/' );
}

function site_pulse_delete_form_file( string $path ): void {
	if ( $path === '' ) return;
	$up   = wp_upload_dir();
	$base = trailingslashit( $up['basedir'] ) . 'sp-forms/';
	$full = trailingslashit( $up['basedir'] ) . ltrim( $path, '/' );
	if ( strpos( $full, $base ) === 0 && is_file( $full ) ) @unlink( $full );
}

// Validate + store an uploaded $_FILES entry. Returns ['path','name','format','size'] on success
// or a string error message on failure.
function site_pulse_save_form_file( int $user_id, array $file ) {
	if ( ! isset( $file['error'] ) || $file['error'] !== UPLOAD_ERR_OK ) return 'The file failed to upload.';
	if ( ! is_uploaded_file( $file['tmp_name'] ) ) return 'Invalid upload.';
	$size = (int) ( $file['size'] ?? 0 );
	if ( $size <= 0 ) return 'The file is empty.';
	if ( $size > 25 * 1024 * 1024 ) return 'The file is too large (25 MB max).';

	$orig = (string) ( $file['name'] ?? 'form' );
	$ext  = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
	$formats = site_pulse_form_formats();
	if ( ! isset( $formats[ $ext ] ) ) return 'Unsupported file type. Allowed: PDF, Word, Excel, PowerPoint, images, text.';

	// Cross-check the real type so a renamed executable can't sneak in.
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $orig );
	if ( empty( $check['ext'] ) && ! in_array( $ext, [ 'csv', 'txt' ], true ) ) return 'The file type could not be verified.';

	$base = site_pulse_forms_basedir();
	$name = 'f' . $user_id . '-' . time() . '-' . wp_generate_password( 10, false, false ) . '.' . $ext;
	$dest = trailingslashit( $base['dir'] ) . $name;
	if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) return 'Could not save the file.';

	return [
		'path'   => 'sp-forms/' . $name,
		'name'   => sanitize_file_name( $orig ),
		'format' => $formats[ $ext ],
		'size'   => $size,
	];
}

// Shape a DB row for the client. `can_edit` = uploader (with upload_forms) or god.
function site_pulse_form_present( array $r, int $user_id, bool $is_god, bool $can_upload ): array {
	$ts       = ! empty( $r['created_at'] ) ? strtotime( $r['created_at'] ) : 0;
	$can_edit = $is_god || ( $can_upload && (int) $r['uploaded_by'] === $user_id );
	$cats     = site_pulse_form_categories();
	$catkey   = (string) $r['category'];
	$subkey   = (string) ( $r['sub_category'] ?? '' );
	$subs     = $subkey !== '' ? site_pulse_form_subfolders( $catkey ) : [];
	return [
		'id'             => (int) $r['id'],
		'name'           => (string) $r['name'],
		'category'       => $catkey,
		'category_label' => $cats[ $catkey ] ?? $catkey,
		'sub_category'   => $subkey,
		'sub_label'      => $subs[ $subkey ] ?? '',
		'type_label' => (string) ( $r['type_label'] ?? '' ),
		'format'     => (string) ( $r['file_format'] ?? '' ),
		'file_name'  => (string) ( $r['file_name'] ?? '' ),
		'size'       => (int) ( $r['file_size'] ?? 0 ),
		'url'        => site_pulse_form_url( (string) $r['file_path'] ),
		'date'       => $ts ? date( 'Y-m-d', $ts ) : '',
		'date_label' => $ts ? date_i18n( 'M j, Y', $ts ) : '',
		'can_edit'   => $can_edit,
	];
}

// Fetch + present one form for the acting user (used after upload/replace).
function site_pulse_form_row( int $id, int $user_id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . site_pulse_table('forms') . " WHERE id = %d", $id ), ARRAY_A );
	if ( ! $row ) return null;
	$is_god     = site_pulse_is_god( get_current_user_id() );
	$can_upload = $is_god || site_pulse_user_can( $user_id, 'upload_forms' );
	return site_pulse_form_present( $row, $user_id, $is_god, $can_upload );
}

add_action( 'wp_ajax_site_pulse_get_forms', 'site_pulse_ajax_get_forms' );
function site_pulse_ajax_get_forms(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$is_god  = site_pulse_is_god( get_current_user_id() );
	if ( ! $user_id || ! ( $is_god || site_pulse_user_can( $user_id, 'view_forms' ) || site_pulse_user_can( $user_id, 'upload_forms' ) ) ) wp_send_json_error( [ 'message' => 'You do not have permission to view forms.' ] );
	$category = sanitize_key( $_POST['category'] ?? '' );
	$is_all   = ( $category === 'all' );   // the "All" view lists every repository's forms
	if ( ! $is_all && ! isset( site_pulse_form_categories()[ $category ] ) ) wp_send_json_error( [ 'message' => 'Unknown repository.' ] );

	global $wpdb;
	$tbl  = site_pulse_table('forms');
	$rows = $is_all
		? ( $wpdb->get_results( "SELECT * FROM $tbl ORDER BY created_at DESC", ARRAY_A ) ?: [] )
		: ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $tbl WHERE category = %s ORDER BY created_at DESC", $category ), ARRAY_A ) ?: [] );
	$can_upload = $is_god || site_pulse_user_can( $user_id, 'upload_forms' );
	$forms = array_map( fn( $r ) => site_pulse_form_present( $r, $user_id, $is_god, $can_upload ), $rows );
	wp_send_json_success( [ 'forms' => $forms, 'can_upload' => $can_upload ] );
}

add_action( 'wp_ajax_site_pulse_upload_form', 'site_pulse_ajax_upload_form' );
function site_pulse_ajax_upload_form(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! $user_id || ! ( site_pulse_is_god( get_current_user_id() ) || site_pulse_user_can( $user_id, 'upload_forms' ) ) ) wp_send_json_error( [ 'message' => 'You do not have permission to upload forms.' ] );

	$name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$category     = sanitize_key( $_POST['category'] ?? '' );
	$sub_category = sanitize_key( $_POST['sub_category'] ?? '' );
	$type_label   = sanitize_text_field( wp_unslash( $_POST['type_label'] ?? '' ) );
	if ( $name === '' ) wp_send_json_error( [ 'message' => 'Please give the form a name.' ] );
	if ( ! isset( site_pulse_form_categories()[ $category ] ) ) wp_send_json_error( [ 'message' => 'Choose a repository.' ] );
	// A sub-folder is optional, but if given it must belong to the chosen repository.
	if ( $sub_category !== '' && ! isset( site_pulse_form_subfolders( $category )[ $sub_category ] ) ) $sub_category = '';
	if ( empty( $_FILES['file'] ) ) wp_send_json_error( [ 'message' => 'Please choose a file to upload.' ] );

	$saved = site_pulse_save_form_file( $user_id, $_FILES['file'] );
	if ( is_string( $saved ) ) wp_send_json_error( [ 'message' => $saved ] );

	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert( site_pulse_table('forms'), [
		'name'         => $name,
		'category'     => $category,
		'sub_category' => $sub_category ?: null,
		'type_label'   => $type_label,
		'file_path'   => $saved['path'],
		'file_name'   => $saved['name'],
		'file_format' => $saved['format'],
		'file_size'   => $saved['size'],
		'uploaded_by' => $user_id,
		'created_at'  => $now,
		'updated_at'  => $now,
	] );
	$id = (int) $wpdb->insert_id;
	if ( ! $id ) { site_pulse_delete_form_file( $saved['path'] ); wp_send_json_error( [ 'message' => 'Could not save the form.' ] ); }
	wp_send_json_success( [ 'form' => site_pulse_form_row( $id, $user_id ) ] );
}

add_action( 'wp_ajax_site_pulse_replace_form', 'site_pulse_ajax_replace_form' );
function site_pulse_ajax_replace_form(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$id      = (int) ( $_POST['id'] ?? 0 );

	global $wpdb;
	$tbl = site_pulse_table('forms');
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tbl WHERE id = %d", $id ), ARRAY_A );
	if ( ! $row ) wp_send_json_error( [ 'message' => 'Form not found.' ] );

	$is_god = site_pulse_is_god( get_current_user_id() );
	$owner  = (int) $row['uploaded_by'];
	if ( ! ( $is_god || ( site_pulse_user_can( $user_id, 'upload_forms' ) && $owner === $user_id ) ) ) wp_send_json_error( [ 'message' => 'You can only replace forms you uploaded.' ] );

	$update = [ 'updated_at' => current_time( 'mysql' ) ];
	if ( isset( $_POST['name'] ) ) {
		$n = sanitize_text_field( wp_unslash( $_POST['name'] ) );
		if ( $n === '' ) wp_send_json_error( [ 'message' => 'The name cannot be empty.' ] );
		$update['name'] = $n;
	}
	if ( isset( $_POST['type_label'] ) ) $update['type_label'] = sanitize_text_field( wp_unslash( $_POST['type_label'] ) );
	// Allow moving the form into / out of a sub-folder of its repository on edit.
	if ( isset( $_POST['sub_category'] ) ) {
		$sub = sanitize_key( $_POST['sub_category'] );
		$update['sub_category'] = ( $sub !== '' && isset( site_pulse_form_subfolders( (string) $row['category'] )[ $sub ] ) ) ? $sub : null;
	}

	// A new file is optional — replace just the name/type if none is sent.
	if ( ! empty( $_FILES['file'] ) && isset( $_FILES['file']['error'] ) && $_FILES['file']['error'] === UPLOAD_ERR_OK ) {
		$saved = site_pulse_save_form_file( $user_id, $_FILES['file'] );
		if ( is_string( $saved ) ) wp_send_json_error( [ 'message' => $saved ] );
		site_pulse_delete_form_file( (string) $row['file_path'] );
		$update['file_path']   = $saved['path'];
		$update['file_name']   = $saved['name'];
		$update['file_format'] = $saved['format'];
		$update['file_size']   = $saved['size'];
	}

	$wpdb->update( $tbl, $update, [ 'id' => $id ] );
	wp_send_json_success( [ 'form' => site_pulse_form_row( $id, $user_id ) ] );
}

add_action( 'wp_ajax_site_pulse_delete_form', 'site_pulse_ajax_delete_form' );
function site_pulse_ajax_delete_form(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$id      = (int) ( $_POST['id'] ?? 0 );

	global $wpdb;
	$tbl = site_pulse_table('forms');
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tbl WHERE id = %d", $id ), ARRAY_A );
	if ( ! $row ) wp_send_json_error( [ 'message' => 'Form not found.' ] );

	$is_god = site_pulse_is_god( get_current_user_id() );
	$owner  = (int) $row['uploaded_by'];
	if ( ! ( $is_god || ( site_pulse_user_can( $user_id, 'upload_forms' ) && $owner === $user_id ) ) ) wp_send_json_error( [ 'message' => 'You can only delete forms you uploaded.' ] );

	site_pulse_delete_form_file( (string) $row['file_path'] );
	$wpdb->delete( $tbl, [ 'id' => $id ] );
	wp_send_json_success( [ 'id' => $id ] );
}

// Read a posted id list that may arrive as ids[] or as a JSON string.
function site_pulse_form_ids_param(): array {
	$raw = $_POST['ids'] ?? '';
	if ( is_string( $raw ) ) $raw = json_decode( wp_unslash( $raw ), true );
	if ( ! is_array( $raw ) ) return [];
	return array_values( array_unique( array_filter( array_map( 'intval', $raw ) ) ) );
}

// Bulk delete selected forms. Needs upload_forms; only deletes forms you own (god deletes any).
add_action( 'wp_ajax_site_pulse_bulk_delete_forms', 'site_pulse_ajax_bulk_delete_forms' );
function site_pulse_ajax_bulk_delete_forms(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$is_god  = site_pulse_is_god( get_current_user_id() );
	if ( ! $user_id || ! ( $is_god || site_pulse_user_can( $user_id, 'upload_forms' ) ) ) wp_send_json_error( [ 'message' => 'You do not have permission to delete forms.' ] );

	$ids = site_pulse_form_ids_param();
	if ( ! $ids ) wp_send_json_error( [ 'message' => 'No forms selected.' ] );

	global $wpdb;
	$tbl = site_pulse_table('forms');
	$deleted = 0; $skipped = 0;
	foreach ( $ids as $id ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, uploaded_by, file_path FROM $tbl WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) continue;
		if ( ! ( $is_god || (int) $row['uploaded_by'] === $user_id ) ) { $skipped++; continue; }
		site_pulse_delete_form_file( (string) $row['file_path'] );
		$wpdb->delete( $tbl, [ 'id' => (int) $id ] );
		$deleted++;
	}
	wp_send_json_success( [ 'deleted' => $deleted, 'skipped' => $skipped ] );
}

// Bulk move selected forms to another repository (optionally a sub-folder of it). Needs
// upload_forms; only moves forms you own (god moves any). Sub-folders don't carry across, so a
// move clears sub_category unless a valid sub-folder of the destination is given.
add_action( 'wp_ajax_site_pulse_move_forms', 'site_pulse_ajax_move_forms' );
function site_pulse_ajax_move_forms(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$is_god  = site_pulse_is_god( get_current_user_id() );
	if ( ! $user_id || ! ( $is_god || site_pulse_user_can( $user_id, 'upload_forms' ) ) ) wp_send_json_error( [ 'message' => 'You do not have permission to move forms.' ] );

	$category = sanitize_key( $_POST['category'] ?? '' );
	if ( ! isset( site_pulse_form_categories()[ $category ] ) ) wp_send_json_error( [ 'message' => 'Choose a destination repository.' ] );
	$sub = sanitize_key( $_POST['sub_category'] ?? '' );
	if ( $sub !== '' && ! isset( site_pulse_form_subfolders( $category )[ $sub ] ) ) $sub = '';

	$ids = site_pulse_form_ids_param();
	if ( ! $ids ) wp_send_json_error( [ 'message' => 'No forms selected.' ] );

	global $wpdb;
	$tbl = site_pulse_table('forms');
	$now = current_time( 'mysql' );
	$moved = 0; $skipped = 0;
	foreach ( $ids as $id ) {
		$owner = $wpdb->get_var( $wpdb->prepare( "SELECT uploaded_by FROM $tbl WHERE id = %d", $id ) );
		if ( $owner === null ) continue;
		if ( ! ( $is_god || (int) $owner === $user_id ) ) { $skipped++; continue; }
		$wpdb->update( $tbl, [ 'category' => $category, 'sub_category' => $sub ?: null, 'updated_at' => $now ], [ 'id' => (int) $id ] );
		$moved++;
	}
	wp_send_json_success( [ 'moved' => $moved, 'skipped' => $skipped ] );
}

// Bulk download — zip the selected forms and stream the archive. Open to any forms viewer.
add_action( 'wp_ajax_site_pulse_download_forms', 'site_pulse_ajax_download_forms' );
function site_pulse_ajax_download_forms(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$is_god  = site_pulse_is_god( get_current_user_id() );
	if ( ! $user_id || ! ( $is_god || site_pulse_user_can( $user_id, 'view_forms' ) || site_pulse_user_can( $user_id, 'upload_forms' ) ) ) wp_send_json_error( [ 'message' => 'You do not have permission to download forms.' ] );

	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_values( array_unique( array_filter( array_map( 'intval', $_POST['ids'] ) ) ) ) : [];
	if ( ! $ids ) wp_send_json_error( [ 'message' => 'No forms selected.' ] );
	if ( ! class_exists( 'ZipArchive' ) ) wp_send_json_error( [ 'message' => 'Zip downloads are not available on this server.' ] );

	global $wpdb;
	$place = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . site_pulse_table('forms') . " WHERE id IN ($place)", $ids ), ARRAY_A ) ?: [];
	if ( ! $rows ) wp_send_json_error( [ 'message' => 'Forms not found.' ] );

	$up   = wp_upload_dir();
	$base = trailingslashit( $up['basedir'] ) . 'sp-forms/';
	$tmp  = wp_tempnam( 'sp-forms-zip' );
	$zip  = new ZipArchive();
	if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) { @unlink( $tmp ); wp_send_json_error( [ 'message' => 'Could not create the archive.' ] ); }

	$used = [];
	$count = 0;
	foreach ( $rows as $r ) {
		$full = trailingslashit( $up['basedir'] ) . ltrim( (string) $r['file_path'], '/' );
		if ( strpos( $full, $base ) !== 0 || ! is_file( $full ) ) continue;   // stay inside sp-forms/
		$name = sanitize_file_name( $r['file_name'] ?: basename( (string) $r['file_path'] ) );
		if ( $name === '' ) $name = 'form-' . (int) $r['id'];
		$ext = strtolower( pathinfo( (string) $r['file_path'], PATHINFO_EXTENSION ) );
		if ( $ext && strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) !== $ext ) $name .= '.' . $ext;
		$entry = $name; $i = 2;
		while ( isset( $used[ strtolower( $entry ) ] ) ) {
			$b = pathinfo( $name, PATHINFO_FILENAME ); $e = pathinfo( $name, PATHINFO_EXTENSION );
			$entry = $b . ' (' . $i . ')' . ( $e ? '.' . $e : '' ); $i++;
		}
		$used[ strtolower( $entry ) ] = true;
		if ( $zip->addFile( $full, $entry ) ) $count++;
	}
	$zip->close();

	if ( ! $count ) { @unlink( $tmp ); wp_send_json_error( [ 'message' => 'None of the selected forms could be downloaded.' ] ); }

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="forms-' . date( 'Y-m-d' ) . '.zip"' );
	header( 'Content-Length: ' . filesize( $tmp ) );
	readfile( $tmp );
	@unlink( $tmp );
	exit;
}

// Settings → Forms: the repository list with each one's form count.
add_action( 'wp_ajax_site_pulse_get_form_settings', 'site_pulse_ajax_get_form_settings' );
function site_pulse_ajax_get_form_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	global $wpdb;
	$counts = [];
	$rows = $wpdb->get_results( "SELECT category, COUNT(*) AS c FROM " . site_pulse_table('forms') . " GROUP BY category", ARRAY_A ) ?: [];
	foreach ( $rows as $r ) $counts[ (string) $r['category'] ] = (int) $r['c'];

	$cats = [];
	foreach ( site_pulse_form_category_tree() as $key => $cat ) {
		$kids = [];
		foreach ( $cat['children'] as $sk => $sl ) $kids[] = [ 'key' => $sk, 'label' => $sl ];
		$cats[] = [ 'key' => $key, 'label' => $cat['label'], 'count' => $counts[ $key ] ?? 0, 'children' => $kids ];
	}
	wp_send_json_success( [ 'categories' => $cats ] );
}

// Settings → Forms: save the repository list (add / rename / delete / reorder). Existing rows keep
// their key (so their forms stay linked); new rows get a slug from the label. Deleting a repository
// reassigns its forms to the first remaining one — nothing is orphaned.
add_action( 'wp_ajax_site_pulse_save_form_categories', 'site_pulse_ajax_save_form_categories' );
function site_pulse_ajax_save_form_categories(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_settings' ) ) return;

	$raw = json_decode( (string) wp_unslash( $_POST['categories'] ?? '' ), true );
	if ( ! is_array( $raw ) ) wp_send_json_error( [ 'message' => 'Invalid data.' ] );

	$old_tree = site_pulse_form_category_tree();   // pre-change state (for sub-folder cleanup)

	$final    = [];   // key => label, in submitted order
	$children = [];   // catKey => [ subKey => subLabel ]
	foreach ( $raw as $c ) {
		if ( ! is_array( $c ) ) continue;
		$label = trim( (string) ( $c['label'] ?? '' ) );
		if ( $label === '' ) continue;
		$key = sanitize_key( (string) ( $c['key'] ?? '' ) );
		if ( $key === '' ) $key = sanitize_title( $label );
		if ( $key === '' ) $key = 'cat';
		$base = $key; $i = 2;
		while ( isset( $final[ $key ] ) ) { $key = $base . '-' . $i; $i++; }
		$final[ $key ] = $label;

		// Optional sub-folders for this repository.
		$subs = [];
		foreach ( (array) ( $c['children'] ?? [] ) as $s ) {
			if ( ! is_array( $s ) ) continue;
			$slabel = trim( (string) ( $s['label'] ?? '' ) );
			if ( $slabel === '' ) continue;
			$skey = sanitize_key( (string) ( $s['key'] ?? '' ) );
			if ( $skey === '' ) $skey = sanitize_title( $slabel );
			if ( $skey === '' ) $skey = 'sub';
			$sbase = $skey; $si = 2;
			while ( isset( $subs[ $skey ] ) ) { $skey = $sbase . '-' . $si; $si++; }
			$subs[ $skey ] = $slabel;
		}
		$children[ $key ] = $subs;
	}
	if ( empty( $final ) ) wp_send_json_error( [ 'message' => 'Keep at least one repository.' ] );

	global $wpdb;
	$ftbl     = site_pulse_table('forms');
	$new_keys = array_keys( $final );

	// Reassign forms from any deleted repository to the first remaining one (sub-folder cleared).
	$deleted = array_diff( array_keys( $old_tree ), $new_keys );
	$moved   = 0;
	if ( $deleted ) {
		$target = $new_keys[0];
		foreach ( $deleted as $dk ) {
			$moved += (int) $wpdb->query( $wpdb->prepare(
				"UPDATE $ftbl SET category = %s, sub_category = NULL WHERE category = %s", $target, $dk
			) );
		}
	}
	// For surviving repositories, move forms out of any REMOVED sub-folder back to the repo root.
	foreach ( $new_keys as $ck ) {
		$gone = array_diff( array_keys( $old_tree[ $ck ]['children'] ?? [] ), array_keys( $children[ $ck ] ?? [] ) );
		foreach ( $gone as $sk ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE $ftbl SET sub_category = NULL WHERE category = %s AND sub_category = %s", $ck, $sk
			) );
		}
	}

	$store = [];
	foreach ( $final as $k => $l ) {
		$kids = [];
		foreach ( $children[ $k ] as $sk => $sl ) $kids[] = [ 'key' => $sk, 'label' => $sl ];
		$store[] = [ 'key' => $k, 'label' => $l, 'children' => $kids ];
	}
	site_pulse_set_setting( 'form_categories', wp_json_encode( $store ) );

	wp_send_json_success( [
		'categories' => $store,
		'moved'      => $moved,
		'moved_to'   => $moved ? $final[ $new_keys[0] ] : '',
	] );
}

/* ---- Receipt image storage (uploads/sp-receipts/<unguessable>.jpg; row keeps the relative path) ---- */
function site_pulse_receipts_basedir(): array {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'sp-receipts';
	if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
	return [ 'dir' => $dir, 'url' => trailingslashit( $up['baseurl'] ) . 'sp-receipts' ];
}

function site_pulse_receipt_url( string $path ): string {
	if ( $path === '' ) return '';
	$up = wp_upload_dir();
	return trailingslashit( $up['baseurl'] ) . ltrim( $path, '/' );
}

// Decode a base64 data-URL receipt photo, save it, return its relative path (or '' on failure).
function site_pulse_save_receipt_image( int $user_id, string $data_url ): string {
	if ( ! preg_match( '#^data:image/(jpe?g|png);base64,#i', $data_url, $m ) ) return '';
	$b64 = substr( $data_url, strpos( $data_url, ',' ) + 1 );
	if ( strlen( $b64 ) > 13 * 1024 * 1024 ) return '';            // ~9MB binary cap
	$bytes = base64_decode( $b64, true );
	if ( $bytes === false || strlen( $bytes ) < 64 ) return '';
	$ext  = ( stripos( $m[1], 'png' ) === 0 ) ? 'png' : 'jpg';
	$base = site_pulse_receipts_basedir();
	$name = 'r' . $user_id . '-' . time() . '-' . wp_generate_password( 10, false, false ) . '.' . $ext;
	if ( ! file_put_contents( trailingslashit( $base['dir'] ) . $name, $bytes ) ) return '';
	return 'sp-receipts/' . $name;
}

function site_pulse_delete_receipt_file( string $path ): void {
	if ( $path === '' ) return;
	$up   = wp_upload_dir();
	$base = trailingslashit( $up['basedir'] ) . 'sp-receipts/';
	$full = trailingslashit( $up['basedir'] ) . ltrim( $path, '/' );
	if ( strpos( $full, $base ) === 0 && is_file( $full ) ) @unlink( $full );
}

add_action( 'wp_ajax_site_pulse_delete_expense', 'site_pulse_ajax_delete_expense' );
function site_pulse_ajax_delete_expense(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	$id      = (int) ( $_POST['id'] ?? 0 );

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . site_pulse_table('expenses') . " WHERE id = %d", $id ), ARRAY_A );
	if ( ! $row ) wp_send_json_error( [ 'message' => 'Expense not found.' ] );
	$owner = (int) $row['user_id'];

	$is_admin = site_pulse_user_can( $user_id, 'manage_mileage' ) || site_pulse_is_god( get_current_user_id() );
	if ( $owner !== $user_id && ! $is_admin ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	// Snapshot to the trash so the deletion can be undone, then remove.
	$sec_labels = [ 'B' => 'Vehicle expense', 'C' => 'Business meal', 'D' => 'Competitive shopping', 'E' => 'Other expense' ];
	$label = ( $sec_labels[ $row['section'] ] ?? 'Expense' ) . ( ! empty( $row['expense_date'] ) ? ' · ' . date_i18n( 'M j, Y', strtotime( $row['expense_date'] ) ) : '' );
	site_pulse_trash_push( $owner, 'expense', $label, [ 'row' => $row ] );

	$wpdb->delete( site_pulse_table('expenses'), [ 'id' => $id ] );
	wp_send_json_success();
}

/**
 * Reusable receipt scanner: takes a receipt photo (base64 data URL) + a section, asks Claude
 * vision to read it, and returns {category, amount, description, date} pre-filled against THAT
 * section's category list. Every expense module (B–F) reuses this — pass its own section.
 */
add_action( 'wp_ajax_site_pulse_scan_receipt', 'site_pulse_ajax_scan_receipt' );
function site_pulse_ajax_scan_receipt(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();
	if ( ! site_pulse_user_can( $user_id, 'submit_mileage' ) && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}
	if ( ! site_pulse_get_api_key() ) {
		wp_send_json_error( [ 'message' => 'No Claude API key set. Add one under Settings → API Keys first.' ] );
	}

	$section  = sanitize_text_field( $_POST['section'] ?? 'B' );
	$sections = site_pulse_expense_sections();
	if ( ! isset( $sections[ $section ] ) ) wp_send_json_error( [ 'message' => 'Unknown expense section.' ] );

	// Accept a data URL or bare base64; the client always sends a resized JPEG.
	$image      = (string) ( $_POST['image'] ?? '' );
	$media_type = 'image/jpeg';
	if ( preg_match( '#^data:(image/[a-zA-Z0-9.+-]+);base64,#', $image, $m ) ) {
		$media_type = $m[1];
		$image      = substr( $image, strlen( $m[0] ) );
	}
	$image = preg_replace( '/\s+/', '', $image );
	if ( $image === '' || base64_decode( $image, true ) === false ) {
		wp_send_json_error( [ 'message' => 'No valid image was received.' ] );
	}
	if ( strlen( $image ) > 9 * 1024 * 1024 ) {
		wp_send_json_error( [ 'message' => 'That image is too large — try a smaller photo.' ] );
	}

	$cats      = $sections[ $section ]['categories'];
	$cat_lines = '';
	foreach ( $cats as $key => $c ) $cat_lines .= "- \"$key\": {$c['label']}\n";

	$system = 'You read photos of purchase receipts and extract structured expense data. Reply with ONLY a single JSON object — no prose, no markdown code fences.';

	$prompt  = "This is a photo of a receipt for a business " . strtolower( $sections[ $section ]['label'] ) . " expense. Extract:\n";
	$prompt .= "- \"category\": the single best-fit key from this list (closest match):\n" . $cat_lines;
	$prompt .= "- \"amount\": the TOTAL amount paid, as a plain number with no \$ or commas (e.g. 42.18)\n";
	$prompt .= "- \"description\": a short label, ideally \"<merchant> — <item>\" (e.g. \"Shell — fuel\", \"Discount Tire — trailer tire\"), max ~60 characters\n";
	$prompt .= "- \"place\": the merchant / restaurant / store name on its own (e.g. \"Olive Garden\", \"Shell\"), or empty string\n";
	$prompt .= "- \"date\": the receipt date as YYYY-MM-DD if visible, otherwise an empty string\n";
	$prompt .= "- \"corners\": the four corners of the RECEIPT PAPER itself (ignore table/hand/background), as percentages of the image, ordered top-left, top-right, bottom-right, bottom-left. Format each as [x,y] where x and y are 0–100 (x across the width, y down the height). ALWAYS give four corners. If part of the receipt runs off the edge of the photo (cut off), use the spot where its edge meets the image border as that corner — clamp that coordinate to 0 or 100 (the last visible spot). Return an empty array [] ONLY if you truly cannot locate the receipt at all.\n";
	$prompt .= "If a field can't be read, use an empty string (or 0 for amount). Pick the most likely category even if unsure.\n";
	$prompt .= 'Return ONLY: {"category":"<key>","amount":0,"description":"","place":"","date":"","corners":[[x,y],[x,y],[x,y],[x,y]]}';

	$debug = null;
	$resp  = site_pulse_call_claude_vision( $image, $media_type, $prompt, $system, [ 'max_tokens' => 400, 'timeout' => 45 ], $debug );
	if ( $resp === null ) wp_send_json_error( [ 'message' => 'Could not read the receipt' . ( $debug ? ": $debug" : '.' ) ] );

	$json = trim( $resp );
	if ( preg_match( '/\{.*\}/s', $json, $mm ) ) $json = $mm[0];
	$parsed = json_decode( $json, true );
	if ( ! is_array( $parsed ) ) wp_send_json_error( [ 'message' => 'The receipt could not be read clearly — please enter it manually.' ] );

	$cat = sanitize_text_field( (string) ( $parsed['category'] ?? '' ) );
	if ( ! isset( $cats[ $cat ] ) ) $cat = '';
	$date = sanitize_text_field( (string) ( $parsed['date'] ?? '' ) );
	if ( $date !== '' && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) $date = '';

	// Receipt corners (top-left, top-right, bottom-right, bottom-left) as 0–1 fractions, for the
	// client to deskew + crop. Only pass through 4 in-range points; anything else → [] (no warp).
	$corners = [];
	if ( isset( $parsed['corners'] ) && is_array( $parsed['corners'] ) && count( $parsed['corners'] ) === 4 ) {
		$ok = true;
		foreach ( $parsed['corners'] as $pt ) {
			if ( ! is_array( $pt ) || count( $pt ) < 2 ) { $ok = false; break; }
			$x = (float) $pt[0] / 100; $y = (float) $pt[1] / 100;
			if ( $x < -0.1 || $x > 1.1 || $y < -0.1 || $y > 1.1 ) { $ok = false; break; }
			$corners[] = [ max( 0, min( 1, $x ) ), max( 0, min( 1, $y ) ) ];
		}
		if ( ! $ok ) $corners = [];
	}

	wp_send_json_success( [
		'category'    => $cat,
		'amount'      => round( (float) ( $parsed['amount'] ?? 0 ), 2 ),
		'description' => sanitize_text_field( (string) ( $parsed['description'] ?? '' ) ),
		'place'       => sanitize_text_field( (string) ( $parsed['place'] ?? '' ) ),
		'date'        => $date,
		'corners'     => $corners,
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

// Per-leg mileage adjustment. A driver can nudge one leg's miles (detour, road closure,
// accident) without touching the computed base distance — the delta is stored in
// miles_adjust and surfaced as "+7.8" in the report. Recalc cascades to the day + grand total.
add_action( 'wp_ajax_site_pulse_save_mileage_leg_adjust', 'site_pulse_ajax_save_mileage_leg_adjust' );
function site_pulse_ajax_save_mileage_leg_adjust(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$entry_id = (int) ( $_POST['entry_id'] ?? 0 );
	$leg_id   = (int) ( $_POST['leg_id'] ?? 0 );
	$adjust   = max( -2000, min( 2000, round( (float) ( $_POST['adjust'] ?? 0 ), 2 ) ) );
	$reason   = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );

	global $wpdb;
	$entry = $wpdb->get_row( $wpdb->prepare(
		"SELECT user_id FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d", $entry_id
	), ARRAY_A );
	if ( ! $entry ) wp_send_json_error( [ 'message' => 'Entry not found.' ] );

	$is_admin = site_pulse_user_can( $user_id, 'manage_mileage' ) || site_pulse_is_god( get_current_user_id() );
	if ( (int) $entry['user_id'] !== $user_id && ! $is_admin ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	// The leg must belong to this entry and have a computed base distance to adjust.
	$leg = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, miles FROM " . site_pulse_table('mileage_legs') . " WHERE id = %d AND entry_id = %d",
		$leg_id, $entry_id
	), ARRAY_A );
	if ( ! $leg ) wp_send_json_error( [ 'message' => 'Leg not found.' ] );
	if ( $leg['miles'] === null ) wp_send_json_error( [ 'message' => 'This trip has no distance yet.' ] );
	// Don't let an adjustment drive the leg negative.
	if ( (float) $leg['miles'] + $adjust < 0 ) wp_send_json_error( [ 'message' => 'Adjusted miles cannot be negative.' ] );

	// Clearing the adjustment (back to 0) drops the reason with it.
	$wpdb->update( site_pulse_table('mileage_legs'),
		[
			'miles_adjust'        => ( $adjust == 0.0 ? null : $adjust ),
			'miles_adjust_reason' => ( $adjust == 0.0 || $reason === '' ? null : $reason ),
		],
		[ 'id' => $leg_id ]
	);
	site_pulse_mileage_recalc_entry( $entry_id );

	$totals = $wpdb->get_row( $wpdb->prepare(
		"SELECT total_miles, reimbursement_amount, total_tolls, total_trailer FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d", $entry_id
	), ARRAY_A );
	wp_send_json_success( [ 'adjust' => $adjust, 'entry' => $totals ] );
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
	// The form no longer collects a notes/summary; only touch the column when notes are sent, so
	// editing a day never wipes a note saved before the field was removed.
	$has_notes   = isset( $_POST['notes'] );
	$notes       = $has_notes ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
	$auto_return  = isset( $_POST['auto_return_home'] ) ? ( (int) $_POST['auto_return_home'] ? 1 : 0 ) : 1;
	$stops_raw    = $_POST['stops'] ?? [];
	$purposes_raw = wp_unslash( $_POST['purposes'] ?? [] );   // unslash so O'Brien's isn't stored as O\'Brien\'s
	$tolls_raw    = $_POST['tolls'] ?? [];
	$trailers_raw = $_POST['trailers'] ?? [];
	$charge_raw   = $_POST['charge_to'] ?? [];   // per-stop "Charge To" store number (or '99' = Misc)
	$notes_raw    = wp_unslash( $_POST['line_notes'] ?? [] );   // per-stop free-text note (e.g. who you met)
	// Toll / trailer flags + charge-to for the final leg home (the auto-appended END bookend).
	$end_toll     = ! empty( $_POST['end_toll'] ) ? 1 : 0;
	$end_trailer  = ! empty( $_POST['end_trailer'] ) ? 1 : 0;
	$end_charge   = sanitize_text_field( $_POST['end_charge'] ?? '' );
	if ( ! is_array( $stops_raw ) )    $stops_raw = [];
	if ( ! is_array( $purposes_raw ) ) $purposes_raw = [];
	if ( ! is_array( $tolls_raw ) )    $tolls_raw = [];
	if ( ! is_array( $trailers_raw ) ) $trailers_raw = [];
	if ( ! is_array( $charge_raw ) )   $charge_raw = [];
	if ( ! is_array( $notes_raw ) )    $notes_raw = [];

	// Keep stops with their per-stop purposes, toll + trailer flags, charge-to, and note aligned, dropping empty picks.
	$stops          = [];
	$stop_purposes  = [];
	$stop_tolls     = [];
	$stop_trailers  = [];
	$stop_charge    = [];
	$stop_notes     = [];
	foreach ( array_values( $stops_raw ) as $idx => $sid ) {
		$sid = (int) $sid;
		if ( $sid <= 0 ) continue;
		$stops[]         = $sid;
		$stop_purposes[] = sanitize_text_field( $purposes_raw[ $idx ] ?? '' );
		$stop_tolls[]    = ! empty( $tolls_raw[ $idx ] ) ? 1 : 0;
		$stop_trailers[] = ! empty( $trailers_raw[ $idx ] ) ? 1 : 0;
		$stop_charge[]   = sanitize_text_field( $charge_raw[ $idx ] ?? '' );
		$stop_notes[]    = sanitize_textarea_field( $notes_raw[ $idx ] ?? '' );
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
		$seq_charge   = array_merge( [ '' ], $stop_charge );
		$seq_notes    = array_merge( [ '' ], $stop_notes );
		if ( $auto_return ) { $seq[] = $home_id; $seq_purposes[] = ''; $seq_tolls[] = $end_toll; $seq_trailers[] = $end_trailer; $seq_charge[] = $end_charge; $seq_notes[] = ''; }
	} else {
		if ( count( $stops ) < 2 ) wp_send_json_error( [ 'message' => 'At least two stops are required.' ] );
		$seq          = $stops;
		$seq_purposes = $stop_purposes;
		$seq_tolls    = $stop_tolls;
		$seq_trailers = $stop_trailers;
		$seq_charge   = $stop_charge;
		$seq_notes    = $stop_notes;
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
		$entry_update = [
			'entry_date'       => $entry_date,
			'auto_return_home' => $auto_return,
			'updated_at'       => $now,
		];
		if ( $has_notes ) $entry_update['notes'] = $notes;
		$wpdb->update( site_pulse_table('mileage_entries'), $entry_update, [ 'id' => $entry_id ] );
		$wpdb->delete( site_pulse_table('mileage_legs'), [ 'entry_id' => $entry_id ] );
		// Legs are about to be rebuilt with fresh ids, so any toll charges previously matched
		// to this day's legs are now dangling — reset them to unprocessed so the driver can
		// re-reconcile against the new route. ('no_trip' rows are for other days; leave them.)
		$wpdb->query( $wpdb->prepare(
			"UPDATE " . site_pulse_table('mileage_toll_transactions') . "
			 SET allocation_status = 'unprocessed', allocation_leg_id = NULL, allocation_note = NULL, updated_at = %s
			 WHERE user_id = %d AND allocation_entry_id = %d AND allocation_status != 'no_trip'",
			$now, $user_id, $entry_id
		) );
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

		// Tolls are no longer estimated at save. They're filled in later by AI toll
		// reconciliation against the driver's uploaded toll-authority CSV, which sets
		// has_toll / toll_cost / toll_estimate per leg. New legs start with no toll.
		$wpdb->insert( site_pulse_table('mileage_legs'), [
			'entry_id'         => $entry_id,
			'leg_order'        => $i,
			'from_location_id' => $from,
			'to_location_id'   => $to,
			'miles'            => $miles,
			'purpose'          => $seq_purposes[ $i + 1 ] ?? '',
			'charge_to'        => $seq_charge[ $i + 1 ] ?? '',
			'note'             => $seq_notes[ $i + 1 ] ?? '',
			'has_toll'         => 0,
			'toll_cost'        => null,
			'has_trailer'      => ! empty( $seq_trailers[ $i + 1 ] ) ? 1 : 0,
			'created_at'       => $now,
		] );
	}

	site_pulse_mileage_recalc_entry( $entry_id );
	wp_send_json_success( [ 'entry_id' => $entry_id ] );
}

/**
 * Stash a deleted record so the header "Undo" button can bring it back. Holds a JSON snapshot
 * keyed by kind ('mileage' | 'expense'); keeps each user's 50 most recent deletions.
 */
function site_pulse_trash_push( int $user_id, string $kind, string $label, array $payload ): void {
	global $wpdb;
	$wpdb->insert( site_pulse_table('trash'), [
		'user_id'    => $user_id,
		'kind'       => $kind,
		'label'      => $label,
		'payload'    => wp_json_encode( $payload ),
		'created_at' => current_time( 'mysql' ),
	] );
	$old = $wpdb->get_col( $wpdb->prepare(
		"SELECT id FROM " . site_pulse_table('trash') . " WHERE user_id = %d ORDER BY id DESC LIMIT %d, 100000",
		$user_id, 50
	) );
	if ( $old ) {
		$in = implode( ',', array_map( 'intval', $old ) );
		$wpdb->query( "DELETE FROM " . site_pulse_table('trash') . " WHERE id IN ($in)" );
	}
}

// Undo the most recent deletion for the current (effective) user — re-inserts the snapshot.
add_action( 'wp_ajax_site_pulse_undo_delete', 'site_pulse_ajax_undo_delete' );
function site_pulse_ajax_undo_delete(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id = site_pulse_effective_user_id();

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('trash') . " WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id
	), ARRAY_A );
	if ( ! $row ) wp_send_json_error( [ 'message' => 'Nothing to undo.' ] );

	$payload = json_decode( (string) $row['payload'], true );
	$section = '';
	if ( is_array( $payload ) && $row['kind'] === 'mileage' ) {
		$entry = $payload['entry'] ?? [];
		$legs  = $payload['legs'] ?? [];
		unset( $entry['id'] );
		$wpdb->insert( site_pulse_table('mileage_entries'), $entry );
		$new_id = (int) $wpdb->insert_id;
		foreach ( (array) $legs as $lg ) { unset( $lg['id'] ); $lg['entry_id'] = $new_id; $wpdb->insert( site_pulse_table('mileage_legs'), $lg ); }
	} elseif ( is_array( $payload ) && $row['kind'] === 'expense' ) {
		$exp = $payload['row'] ?? [];
		$section = (string) ( $exp['section'] ?? '' );
		unset( $exp['id'] );
		$wpdb->insert( site_pulse_table('expenses'), $exp );
	} else {
		$wpdb->delete( site_pulse_table('trash'), [ 'id' => $row['id'] ] );
		wp_send_json_error( [ 'message' => 'Could not restore that item.' ] );
	}

	$wpdb->delete( site_pulse_table('trash'), [ 'id' => $row['id'] ] );
	wp_send_json_success( [ 'kind' => $row['kind'], 'section' => $section, 'label' => (string) $row['label'] ] );
}

add_action( 'wp_ajax_site_pulse_delete_mileage_entry', 'site_pulse_ajax_delete_mileage_entry' );
function site_pulse_ajax_delete_mileage_entry(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$user_id  = site_pulse_effective_user_id();
	$entry_id = (int) ( $_POST['entry_id'] ?? 0 );

	global $wpdb;
	$entry = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_entries') . " WHERE id = %d", $entry_id
	), ARRAY_A );
	if ( ! $entry ) wp_send_json_error( [ 'message' => 'Entry not found.' ] );
	if ( (int) $entry['user_id'] !== $user_id && ! site_pulse_is_god( get_current_user_id() ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	}

	// Snapshot the day + its legs to the trash so it can be undone, then hard-delete.
	$legs = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table('mileage_legs') . " WHERE entry_id = %d ORDER BY leg_order, id", $entry_id
	), ARRAY_A ) ?: [];
	$label = 'Mileage day' . ( ! empty( $entry['entry_date'] ) ? ' · ' . date_i18n( 'M j, Y', strtotime( $entry['entry_date'] ) ) : '' );
	site_pulse_trash_push( (int) $entry['user_id'], 'mileage', $label, [ 'entry' => $entry, 'legs' => $legs ] );

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
		'period_length'    => (int) site_pulse_get_setting( 'mileage_period_length', '0' ),
		'period_anchor'    => site_pulse_get_setting( 'mileage_period_anchor', '' ),
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

// Pay-period config for the report's "Jump to period" menu. Length 0 (or no anchor)
// falls back to calendar months. The client extrapolates all periods from these two values.
add_action( 'wp_ajax_site_pulse_admin_save_mileage_periods', 'site_pulse_ajax_admin_save_mileage_periods' );
function site_pulse_ajax_admin_save_mileage_periods(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_mileage_admin_check() ) return;

	$length = max( 0, min( 366, (int) ( $_POST['period_length'] ?? 0 ) ) );
	$anchor = sanitize_text_field( $_POST['period_anchor'] ?? '' );
	// Keep only a valid Y-m-d anchor; anything else clears it (falls back to calendar months).
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $anchor ) ) $anchor = '';

	site_pulse_set_setting( 'mileage_period_length', (string) $length );
	site_pulse_set_setting( 'mileage_period_anchor', $anchor );

	wp_send_json_success( [ 'period_length' => $length, 'period_anchor' => $anchor ] );
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
	// wp_unslash() before sanitizing so apostrophes (arriving as \') aren't stored as Babe\'s.
	if ( isset( $_POST['name'] ) )          $fields['name']          = sanitize_text_field( wp_unslash( $_POST['name'] ) );
	if ( isset( $_POST['location_type'] ) ) $fields['location_type'] = sanitize_text_field( wp_unslash( $_POST['location_type'] ) );
	if ( isset( $_POST['category'] ) )      $fields['category']      = sanitize_text_field( wp_unslash( $_POST['category'] ) );
	// Store number / accounting code for this destination (optional).
	if ( isset( $_POST['code'] ) )          $fields['code']          = sanitize_text_field( wp_unslash( $_POST['code'] ) );
	if ( isset( $_POST['is_business'] ) )   $fields['is_business']   = (int) $_POST['is_business'] ? 1 : 0;
	if ( isset( $_POST['is_active'] ) )     $fields['is_active']     = (int) $_POST['is_active'] ? 1 : 0;
	if ( isset( $_POST['notes'] ) )         $fields['notes']         = sanitize_textarea_field( wp_unslash( $_POST['notes'] ) );
	// Per-location marker image override (empty clears it → falls back to the per-type default).
	if ( isset( $_POST['marker_icon'] ) )   $fields['marker_icon']   = esc_url_raw( trim( (string) $_POST['marker_icon'] ) );

	if ( isset( $_POST['pinned_purposes'] ) ) {
		$pp = wp_unslash( $_POST['pinned_purposes'] );
		if ( ! is_array( $pp ) ) $pp = [];
		$pp = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $pp ) ) ) );
		$fields['pinned_purposes'] = wp_json_encode( $pp );
	}

	// Address change → re-geocode so cached distances and the map stay correct.
	if ( isset( $_POST['address'] ) ) {
		$address = sanitize_text_field( wp_unslash( $_POST['address'] ) );
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

	// wp_unslash() before sanitizing so apostrophes (arriving as \') aren't stored as Babe\'s.
	$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$address = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );
	if ( ! $name || ! $address ) wp_send_json_error( [ 'message' => 'Name and address are required.' ] );

	$type        = sanitize_text_field( wp_unslash( $_POST['location_type'] ?? 'vendor' ) );
	$category    = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
	$code        = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
	$is_business = isset( $_POST['is_business'] ) ? ( (int) $_POST['is_business'] ? 1 : 0 ) : 1;
	$notes       = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert( site_pulse_table('mileage_locations'), [
		'name'          => $name,
		'address'       => $address,
		'location_type' => in_array( $type, [ 'restaurant', 'vendor', 'other' ], true ) ? $type : 'vendor',
		'category'      => $category,
		'code'          => ( $code === '' ? null : $code ),
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
		site_pulse_dispatch_notification( 'mileage_approved', (int) $loc['created_by'],
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
		site_pulse_dispatch_notification( 'mileage_rejected', (int) $loc['created_by'],
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

	// Sent as a JSON string of { label, requires_note } objects (spAjax can't serialize an
	// array of objects via FormData). Decode, dedupe by label, and store.
	$raw = json_decode( (string) wp_unslash( $_POST['purposes'] ?? '' ), true );
	if ( ! is_array( $raw ) ) $raw = [];
	$clean = [];
	$seen  = [];
	foreach ( $raw as $item ) {
		if ( is_array( $item ) ) {
			$label = sanitize_text_field( (string) ( $item['label'] ?? '' ) );
			$req   = ! empty( $item['requires_note'] ) && $item['requires_note'] !== 'false';
		} else {
			$label = sanitize_text_field( (string) $item );
			$req   = false;
		}
		if ( $label === '' ) continue;
		$key = strtolower( $label );
		if ( isset( $seen[ $key ] ) ) continue;
		$seen[ $key ] = true;
		$clean[] = [ 'label' => $label, 'requires_note' => (bool) $req ];
	}
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

add_shortcode('get-google-alerts', function($atts){
    $a = shortcode_atts(['keywords' => '', 'max' => 10], $atts);
    if (empty($a['keywords'])) return '';

    $query    = urlencode('"' . $a['keywords'] . '"');   // exact-phrase match
    $feed_url = "https://news.google.com/rss/search?q={$query}&hl=en-US&gl=US&ceid=US:en";

    $feed = fetch_feed($feed_url);
    if (is_wp_error($feed)) return '';

    $items = $feed->get_items(0, (int)$a['max']);
    if (!$items) return '<p>No recent results.</p>';

    $out = '<ul class="ga-feed">';
    foreach ($items as $item) {
        $link = esc_url($item->get_permalink());

        // Google News supplies the publisher in an RSS <source url="...">Name</source>
        // element. SimplePie's get_source() is Atom-only, so read the raw tag instead.
        $pub    = '';
        $domain = '';
        $src_tags = $item->get_item_tags('', 'source');
        if (!empty($src_tags[0])) {
            $pub = isset($src_tags[0]['data']) ? trim($src_tags[0]['data']) : '';
            if (!empty($src_tags[0]['attribs']['']['url'])) {
                $domain = wp_parse_url($src_tags[0]['attribs']['']['url'], PHP_URL_HOST);
            }
        }

        // Google News titles read "Headline - Publication" — drop the trailing source.
        $title = $item->get_title();
        if ($pub && substr($title, -strlen(' - ' . $pub)) === ' - ' . $pub) {
            $title = rtrim(substr($title, 0, -strlen(' - ' . $pub)));
        }

        // Option A: publisher favicon via Google's favicon service (no per-article fetch).
        $favicon = $domain
            ? 'https://www.google.com/s2/favicons?domain=' . rawurlencode($domain) . '&sz=64'
            : '';

        $out .= '<li class="ga-item">';

        // Title
        $out .= '<a class="ga-title" href="' . $link . '" target="_blank" rel="noopener">' . esc_html($title) . '</a>';

        // Meta row: ICON | Publication · Date. (The whole item is the link — see .ga-title::after.)
        $out .= '<div class="ga-meta">';
        if ($favicon) {
            $out .= '<img class="ga-favicon" src="' . esc_url($favicon) . '" width="20" height="20" alt="" loading="lazy">';
        }
        if ($pub) {
            $out .= '<span class="ga-pub">' . esc_html($pub) . '</span>';
            $out .= '<span class="ga-sep">&middot;</span>';
        }
        $out .= '<span class="ga-date">' . esc_html($item->get_date('M j, Y')) . '</span>';
        $out .= '</div>';

        $out .= '</li>';
    }
    return $out . '</ul>';
});




add_filter( 'battleplan_icon_map', 'battleplan_addSitePulseIcons' );
function battleplan_addSitePulseIcons( $icons ) {
	$icons['speedometer'] = [ '<g transform="translate(0 180)"><path d="m260.46 74.85-23.52.14-.22-50.91c-52 2.84-100.31 23.01-138.35 57.43 11.78 13.07 22.73 23.1 35.26 35.13-4.18 6.6-10.12 11.45-16.49 17.31L81.37 98.69c-34.74 38.44-54.72 86.8-57.4 138.22l66.51.02-.09 23.13-90.13.27C-6.26 116.82 108.46.01 248.72 0c140-.01 255.18 116.86 248.38 260.24l-89.35.07c-2.03-7.55-1.22-14.78-.82-23.42l66.48-.18c-2.72-51.1-22.61-99.62-57.36-138.02l-35.8 35.3-16.84-16.69 35.58-35.72c-38.11-34.34-86.17-54.95-138.26-57.33l-.28 50.61Z"/><path d="M273.65 246.11c-4.81 12.15-6.59 22.94-19.71 25.58-11.55 2.32-23.4-4.27-27.49-14.76-3.94-12.55.83-24.49 12.63-30.36l75.29-83.44z"/></g>' ];

	$icons['car'] = [ '<g transform="translate(0 260)"><path d="m352.53 165.24-210.83.5c3.93-22.08-4.87-43.11-21.89-55.05-18.32-12.86-42.17-13.06-60.6-.66-18.16 12.2-27.45 33.55-22.58 56.17l-20.44-.41c-8.13-.16-15.67-8.16-15.9-16.3-.54-19.28-.59-38.3 2.23-56.69 5.72-37.23 44.52-29.44 76.62-45.54l42.62-27.76c45.87-29.88 130.38-20.9 184.64-3.01 30.01 9.89 55.07 26.42 79.47 46.66 31.29 6.05 83.1 9.84 102.13 30.2 13.09 14 14.31 51.82 5.72 72.24l-35.61.04c6.31-34.94-18.87-64.33-52-64.64-32.54-.3-59.05 27.36-53.57 64.27ZM221.44 61.72l-5.29-43.12c-39.9.41-83.85 10.71-108.75 41.7zm132.04 4.65c-6.42-11.32-14.06-13.99-22.12-19.81-28.9-16.75-60.12-25.56-94.76-27.63l5.97 43.73 110.92 3.71Z"/><circle cx="88.96" cy="154.56" r="41.52"/><circle cx="405.45" cy="154.53" r="41.52"/></g>' ];

	$icons['toll'] = [ '<g transform="translate(0 150)"><path d="m333.83 59.73 25.72.05L380.57.35 410.39 0l-21.6 59.7 28.66.34L438.89.23l24.69.88c2.98.11 9.55 4.26 9.65 7.36.48 15.19.65 28.9-.15 43.91-.28 5.24-9.33 9.29-13.98 9.29l-57.04-.04h-53.13l-61.94-.1-222.03-.33c-5.11 0-11.63-3.45-13.64-7.42-.38-15.16-.6-29.28.2-44.27.35-6.53 10.35-9.1 15.39-9.14L99.34.11l54.33-.04 26.03.44-20.82 59.03c9.93.47 18.14.53 28.54.33L208.72.2l29.46.27-21.02 59.11c9.73.5 18.94.55 28.6.06l19.07-52.93c.52-1.44.57-3.94.52-6.25l31.37-.12-21.22 59.27 28.68.33L325.32.43 354.7.37l-20.87 59.35ZM116.07 100.14l.1 182.44c12.17 4.66 18.13 15.97 15.94 28.21H.47c-2.21-12.33 3.38-23.96 15.73-27.82l-.07-178.58c0-11.35 6.51-20.66 18.69-20.78l10.04-.1.1 56.06 38.94-.04.15-55.48c15.21-3.01 32 1.92 32 16.08Z"/><path d="M77.88 102.76c.5 13.7 2.32 31.62-4.65 31.67l-16.36.11c-2.77.02-5.37-2.73-5.89-6.01l.03-55.31c0-2.72 2.73-5.2 4.64-5.2l16.78.02c2.97 0 5.4 2.8 5.41 6.45z"/></g>' ];

	$icons['dollar-bill'] = [ '<g transform="translate(0 200)"><path d="M0 0h484.82v263.66H0zm22.99 23.86v215.98H461.8V23.86z"/><path d="m195.21 225.6-158.08.09-.02-187.71h158.31c-35.46 18.93-57.4 53.93-57.68 93.06-.29 39.85 21.83 75.43 57.47 94.56M447.71 225.6l-157.95-.02c36.48-19.98 57.51-55.28 57.3-94.57-.2-39.07-22.29-74.2-57.63-92.99l158.25-.13.03 187.7Z"/><path d="M332.98 131.84c0 50.03-40.56 90.58-90.58 90.58s-90.58-40.56-90.58-90.58 40.56-90.58 90.58-90.58 90.58 40.56 90.58 90.58m-84.19 61.93c12.18-1.57 22.73-4.92 30.94-13.17 9.79-9.84 12.4-25.68 6.69-38.39-4.02-8.94-13.06-14.8-21.91-17.38l-15.85-4.62-.24-26.73c8.59.32 13.97 2.21 20.13 6.81l16.17-19.9c-10.62-7.93-21.47-10.02-35.08-10.48l-1.54-12.04-10.47-.12-.76 12c-10.64 2-20.13 5.08-27.88 12.37-12.13 11.41-14.54 32.27-3.16 44.94 8.06 8.98 20.11 11.04 31.41 14.7l.12 28.16c-9.51.53-18.3-3.08-23.96-9.97l-17.91 20.11c11.22 10.58 26.4 13.26 41.56 13.96l.43 11.97 10.88-.13.4-12.09Z"/><path d="M261.45 151.94c2.54 2.78 1.39 10.66-1.27 12.89s-6.18 3.76-11.6 5.42l-.07-25.45c5.46 1.77 9.3 3.15 12.94 7.13ZM237.41 94.4l-.05 22.66c-7.56-.03-12.38-5.12-12.27-12.43.09-6.02 6.1-10.61 12.32-10.23"/></g>' ];

	$icons['dollar-sign'] = [ '<g transform="translate(60 200)"><path d="M149.95 476.58h-34.52c-5.3.01-10.31-4.74-10.34-10.17l-.21-43.02c-31.39-3.37-59.13-14.35-80.26-36.22C7.61 369.55 1.19 345.95.02 321.68c-.32-6.74 3.43-11.69 10.29-11.69h50.85c6.68-.01 10.74 4.72 10.98 11.09.83 21.38 12.99 38.58 33.58 44.66 13.25 3.91 27.7 4.23 41.12.66 18.44-4.89 28.53-21.15 29.17-39.58 1.34-38.5-29.61-50.34-64.16-62.71-21.84-7.81-42.16-17.43-60.95-30.65-18.12-12.75-30.6-29.9-35.42-51.68-9.79-44.22 2.45-86.95 41.68-110.94 14.7-8.99 30.69-13.7 47.85-16.58l.02-44.1c0-5.46 5.16-10.15 10.37-10.15l34.53-.02c5.34.03 10.56 4.5 10.56 10.19l.02 45.5c49.27 7.65 84.77 46.01 86.49 95.95.21 6.2-1.12 13.23-9.3 13.24l-51.98.06c-6.72 0-10.11-5.66-10.61-11.69-1.36-16.33-8.55-31.87-23.55-39.41-15.12-7.59-35.18-7.16-49.64 1.69-20.19 12.37-25.81 56.74 1.81 75.27 33.8 22.67 66.93 23.35 107.71 53.71 31.93 23.78 41.21 60.33 35.33 99.23-6.74 44.6-43.1 70.63-86.25 78.46v44.21c0 5.53-5.31 10.17-10.57 10.17Z"/></g>' ];

	$icons['trailer'] = [ '<g transform="translate(0 300)"><path d="m29.1 91.99-.39 44.25c6.25 2.06 8.09 2.64 13.11 5.55-12.45 2.82-18.89 2.59-31.69.93 3.67-4.73 5.76-5.09 12.32-6.39l-.08-44.05L0 90.55l13.07-20.74c5.87-4.05 20.73-5.15 28.25-.78l-19.68 5.54c-1.94.55-3.64 3.83-4.17 7.73l141.28-4.14.19-78.16 308.8.03.22 79.27 8.12 6.04-1.04 23.88c-4.7 1.19-13.45 1.51-15.67-1.1s-2.45-9.25-2.48-16.07l-75.85-.18-1.04 13.24c-.13 1.72-3.72 4.75-5.42 5.22-2.12.59-5.6-4.51-5.42-6.92 2.5-33.38-23.13-59.77-54.56-60.5-32.57-.75-59.79 26.39-56.92 60.42.2 2.4-3.48 7.46-5.69 7.15s-5.48-4.57-5.6-6.92l-.6-11.58-216.71-.02Z"/><path d="M358.46 98.85c0 24.9-20.19 45.09-45.09 45.09s-45.09-20.19-45.09-45.09 20.19-45.09 45.09-45.09 45.09 20.19 45.09 45.09m-24.36.01c0-11.43-9.26-20.69-20.69-20.69s-20.69 9.26-20.69 20.69 9.26 20.69 20.69 20.69 20.69-9.26 20.69-20.69"/><circle cx="313.4" cy="98.75" r="9.75"/></g>' ];

	$icons['warning'] = [ '<g transform="translate(80 110)"><path d="m396.15 384.87-357.89.02c-13.52 0-24.59-5.18-31.63-15.49s-9.31-25.08-2.5-36.85l183.36-317.4C193.41 4.9 206.15.04 216.34 0c10.76-.04 23.82 4.52 29.97 15.15l183.36 317.4c6.6 11.42 4.81 25.64-2.06 36.18s-17.41 16.15-31.45 16.15Zm.51-28.62c3.02 0 5.99-1.35 6.99-2.9 1.18-1.82 1.73-5.62.41-7.91L224.14 33.78c-1.98-3.43-4.23-5.49-7.75-5.31-2.86.15-4.98 2.23-6.75 5.31L29.71 345.44c-1.3 2.26-.75 6.13.42 7.91 1.01 1.53 4 2.9 7 2.9z"/><path d="M226.61 252.4c-.53 5.51-5.13 8.76-9.25 9.07-3.22.24-9.51-3.1-9.96-7.72l-11.3-116.04c-.77-7.87.25-16.58 5.81-22.02 8.05-7.87 22.82-7.74 30.53.6 4.95 5.35 5.97 13.96 5.25 21.42L226.6 252.39Z"/><circle cx="216.47" cy="301.77" r="21.2"/></g>' ];

	$icons['food'] = [ '<g transform="translate(20 160)"><path d="M352.57 315.16c-75.04 10.86-150.19 10.63-224.61-.64-33.06-5.01-75.04-14.29-103.52-30.14-14.7-8.18-29.48-21.93-22.79-38.23 2.51-6.13 6.86-11.01 11.51-15.76 7.09-5.81 14.3-10.73 22.3-15.5C50.1 129.13 116.8 61.02 202.26 44.37 199.73 20.33 218.66.32 241.95 0c23.62-.32 42.96 20.04 40.54 44.36 85.5 16.57 152.02 84.97 166.8 170.67 7.13 3.95 13.5 8.34 19.95 13.39 4.23 3.87 8.11 7.99 11.23 12.7 6.63 9.99 5.08 21.9-3.31 30.36-5.77 5.82-12.32 10.59-19.79 14.43-29.72 15.29-70.47 24.29-104.8 29.26ZM266.54 41.67c.39-14.89-11.27-25.54-24.63-25.39-13.43.16-24.3 11.55-23.7 25.51 16.34-1.85 31.93-1.64 48.33-.13Zm-24.63 47.16c5.36-.06 8.41-3.83 8.38-8.17s-3.35-7.91-8.14-7.9c-12.94.03-25.51 1.5-38.2 4.06-4.92.99-7.72 4.84-6.95 9.8.58 3.71 4.73 7.04 9.27 6.14 11.65-2.33 23.15-3.78 35.65-3.92ZM88.84 218.9c8.6-45.1 34.44-84.53 73.26-108.39 4.26-2.62 6.06-7.16 3.6-11.44-1.98-3.45-7-5.26-11.01-2.87-43.49 25.95-72.36 70.79-81.81 120.32-.9 4.74 2.4 8.67 6.41 9.38 4.41.78 8.53-1.71 9.54-7.01Zm366.1 50.01c4.36-2.44 8.16-5.52 11.42-9.16 6.71-7.5-5.14-17.27-14.31-23.92l.26 20.93c-138.14 34.14-281.96 34.07-419.97 0l.3-20.92c-9.75 7.24-21.12 16.09-13.93 24.51 5.29 5.14 11.41 9.23 18.3 12.46 37.62 17.64 89.38 26.62 131.25 30.61 56.42 5.38 112.68 4.74 168.96-2.32 37.04-4.65 85.89-14.36 117.73-32.18Z"/></g>' ];

	$icons['store'] = [ '<g transform="translate(80 110)"><path d="M26 0h348.95v37.13H26zM107.34 51.27h83.01v36.19h-83.01zM210.2 87.46V51.28h82.96v36.18zM391.29 87.38l-78.3-.03V51.26l70.73-.02c3.76 9.35 9.66 17.58 16.65 25.99 1.55 1.86 1.25 5.07 0 6.91s-5.28 3.13-9.09 3.25ZM10.8 87.46C6.23 87.36 3.39 86.67.32 85c-.75-3.07-.15-5.82 1.6-8.67l15.37-24.99 69.68-.14.11 36.14-76.29.11ZM336.16 165.1l-125.5-.05c-2.45 0-6.25 1.52-7.78 2.68-1.66 1.26-2.46 5.17-2.87 8.18v127.74H12.6l.06-200.22h375.17l.07 200-41.77.27V175.84c0-5.29-3.13-10.74-9.96-10.74ZM155.28 274.09c5.24-.01 9.02-8.05 9.01-12.07l-.19-85.82c0-3.1-2.3-7.45-4.1-9.22-2.12-2.09-6.63-2.18-10.08-2.18l-84.01.15c-6.05.01-12.2 4.36-12.19 11.5l.16 86.53c.01 6.74 4.24 11.32 10.98 11.3l90.42-.21ZM346.1 325.57h41.8v25.74h-41.8z"/><path d="M221.35 351.3V186.06H325.5V351.3zM12.39 323.8h187.77v27.32H12.39zM75.16 186.01h67.45v68.09H75.16z"/></g>' ];

	$icons['items'] = [ '<g transform="translate(80 50)"><path d="m374.71 72.25 10.47 167.55-186.54 198.6c-5.2 5.54-12.66 7.29-18.61 1.7L4.9 275.5c-5.71-5.37-6.82-12.87-1.21-18.85L190.81 57.72l115.59-.17c-11.99 22.32-21.75 44.8-30.74 69.08-16.78 5.26-24.81 22.63-19.11 37.42 6.41 16.61 25.03 22.79 39.27 15.22 15.65-8.31 20.03-27.01 9.81-42.14 10.65-28.1 22.62-53.14 37.66-79.06l17.92-.65c8.57.72 12.92 5.43 13.51 14.83Zm-200.64 69.77-5.98 6.38 129.63 121.62 5.98-6.38zm-39.59 180.81 11.59-10.21c11.61 8.29 24.7 7.41 34.03-.28s11.08-19.07 6.15-30.83l-8.82-21.01c-.77-1.84.36-6.61 1.75-7.99 6.3-6.27 19.3 5.33 26.52 20.69l14.61-8.87-11.98-17.81 7.96-9.06-10.16-9.92-10.23 10.22c-10.7-7.1-22.32-7.97-31.96-.58-8.05 6.16-11.58 17.72-6.81 28.83l9.13 21.28c1.07 2.5-.1 7.85-1.77 9.78-6.62 7.62-23.52-6-29.42-24.57l-15.32 9.81c4.33 7.95 7.95 13.51 13.71 21.27l-8.96 9.54 10.01 9.72Z"/><path d="M388.77 19.92c-9.04-2.17-18.29-1.15-25.18 4.73-32.32 27.59-56.93 89.27-71.29 131.68-1.8 5.32-7.05 8.47-12.31 6.84-4.69-1.45-7.51-7.74-5.63-13.33C291.4 99.07 333.4-11.94 391.43 1.05c23.35 5.23 37.66 25.09 33.44 49.64-4.6 26.75-20.32 49.39-40.89 67.91l-1.57-25.7c12.98-13.98 21.85-29.66 23.84-47.92 1.33-12.26-4.96-22.05-17.47-25.05Z"/></g>' ];

	$icons['fuel'] = [ '<g transform="translate(110 50)"><path d="M280.28 413.16H.08c.04-9.57-.38-18.76.36-28.44.32-4.14 5.91-9.44 9.53-10.8 5.75-2.16 11.39-1.34 17.49-1.57l.27-358.53c0-5.68 6.81-11.29 11.25-13.03l77.81-.74L239.67 0c5.37 2.36 9.05 5.84 12.18 10.92l.81 360.79 11.64.97c8.39.7 15.72 6.85 15.79 15.77zm-66.83-369.5H66.87v83.63h146.58z"/><path d="M256.36 251.94c24.32.78 43.52 12.39 43.33 38.03l-.31 41.27c-.07 9.11 2.54 18.3 9.01 24.82 7.6 7.67 20.17 8.88 29.35 3.47 8.35-4.93 11.47-14.9 9.11-24.12-1.93-7.54-4.69-14.36-7.6-21.64l-21.53-53.87c-11.29-28.25-17.33-57.38-19.59-88.04-7.85 1.36-14.14-3.22-15.44-10.72-1.22-7.05-1.58-14.13-1.95-21.39l-.64-34.86 11.19-27.25-34.42-10.62c-.36-7.38-.49-14.31-.15-21.91l52.35 17.64c5.33 2.01 9.52 6.4 9.42 12.62l-1.1 66.35c-.22 13.27-.92 25.72.43 38.98 2.93 28.63 10.21 55.89 21.03 82.42l17.87 43.79c3.06 7.5 6.6 14.69 7.92 22.81 1.18 7.24 1.76 15.78.14 22.95-3.5 15.52-17.27 25.85-32.45 28.24-19.18 3.02-37-6.39-45.66-23.39-4.5-8.84-5.51-17.84-5.6-27.85l-.36-42.85c-.1-12.48-12.21-14.83-24.33-16.66zm41.34-90.26-3.57-45.11-9.12-.49c.79 14.54 1.53 27.21 3.36 40.96.23 1.71 1.96 4.15 3.14 4.93 1.47.97 4.19.9 6.19-.3Z"/></g>' ];

	$icons['wash'] = [ '<g transform="translate(110 100)"><path d="M326.48 351.3c.25 6.52-3.98 11.84-10.53 11.83l-42.23-.07c-5.01 0-9.18-4.39-9.38-9.09l-.38-8.77H64.54l-.25 8.35c-.14 4.83-4.29 9.5-9.52 9.51l-42.28.07c-5.32 0-10.1-4.29-10.15-9.59l-.28-26.35-1.94-35.9c-.49-9.06.56-17.69 2.56-26.45 3.33-14.57 11.48-27 24.46-35.15-9.16-5.27-20.17-11.41-20.35-21.61 3.24-8.29 14.7-8.6 23.94-8.38l6.62 12.3 15.1-46.29c2-6.14 5.17-11.68 8.92-16.71 5.34-7.16 13.01-10.79 21.89-10.83l25.18-.12 2.95-15.42c.66-3.44 4.02-5.35 7.16-5 2.82.31 5.93 3.34 5.33 6.75l-2.43 13.7 34.12-.03.59-5.9c.33-3.3 2.69-5.25 5.81-5.5 2.43-.19 5.84 1.12 6.43 4.38.37 2.03.2 4.54.07 6.96h41.03l-2.43-12.4c-.63-3.21 1.19-6.12 3.84-7.16s7.05-.4 8.07 2.98c1.65 5.45 2.49 11.12 3.12 16.56l35.31.16c15.81.07 26.55 14.83 30.23 29.41l10.84 42.91 5.95-10.75 9.69.22c3.67.08 7.29 1.35 10.37 3.25 3.73 2.29 4.78 6.81 2.56 10.74-1.99 3.53-5.05 7.01-8.65 9.18l-12.9 7.76c1.78 2.56 3.3 5.76 5.28 7.85 9.52 10.07 15.3 22.01 16.7 36.01 2.65 26.54-1.99 50.34-.98 76.57Zm-43.03-129.9-9.82-41.01c-1.27-5.82-3.3-11.11-6.52-15.97-3.83-5.55-9.15-8.83-16.1-9.77H86.48c-4.97.75-8.95 2.47-12.18 5.99-.65 2.74-2.24 4.8-4.86 5.98-2.8 5.22-4.73 10.46-6.43 16.17l-11.5 38.63h231.93ZM95.79 298.37c2.67-5.88-3.8-18.67-14.54-20.05l-39.2-5.04c-2.18-.28-5.52.61-6.82 2.18-1.43 1.73-1.11 4.98-.36 6.88l4 10.12c2.5 6.32 8.13 9.27 14.73 9.33l19.23.17c8.86.08 20.63 1.57 22.97-3.58Zm178.86 3.42c6.2-.05 12.03-2.27 14.32-8.02l5.14-12.89c.62-1.55-.04-4.91-1.16-5.84-1.02-.85-3.62-2.05-5.26-1.84l-39.95 5c-12.25 1.53-18.22 16.03-14.51 21 .98 1.31 3.91 2.88 6.04 2.87l35.39-.26ZM229.92 68.62H97.94c-2.02 0-3.3-2.08-3.3-3.71V53.83c.08-1.84 1.59-3.43 3.55-3.44l22.12-.1c2.55-21.6 16.03-37.68 36.71-42.36L157-.01h14.51v7.37c22.9 2.93 36.95 20.17 41.87 42.89l17.61.14c1.61.01 3.01 1.86 3 3.35l-.02 11.1c0 2.3-1.57 3.76-4.03 3.76ZM248.67 92.05c1.6 3.28-.64 6.61-3.08 7.99-2.69 1.52-7.06.55-8.5-2.49l-6.19-13.11c-1.37-2.9 1.01-6.34 3.3-7.46 2.67-1.3 6.73-.77 8.25 2.34z"/><path d="M84.44 133.89c-1.3 3.38-5.52 4.3-8.35 3.13-2.58-1.06-4.68-4.5-3.49-7.65l4.76-12.55c1.33-3.52 5.17-4.84 8.3-3.59s4.85 4.84 3.52 8.31l-4.73 12.34ZM98.08 98.07c-1.33 3.32-5.31 4.13-8.04 3.05-2.41-.96-4.89-4.33-3.71-7.46l4.92-13.05c1.21-3.2 5.38-4.32 8.19-3.08 3.04 1.34 4.88 4.93 3.5 8.38l-4.87 12.16ZM214.5 96.67c.81 4.2-1.67 7.65-5.27 8.04-3.97.43-6.68-1.91-7.43-5.98l-2.1-11.45c-.69-3.77 1.61-6.84 5.17-7.45 3.27-.56 6.66 1.5 7.39 5.24zM170.15 107.28c-.21 4.09-3.91 6.53-7.01 6.12-3.73-.49-5.97-3.7-5.75-7.53.26-4.46.44-8.93 1.06-13.16.49-3.31 4.4-4.98 6.88-4.6 2.8.43 5.63 3.01 5.46 6.4l-.65 12.78ZM129.1 98.91c-.77 4.07-3.57 6.17-7.25 5.76-3.13-.35-6.13-3.4-5.45-7.17l2.05-11.4c.74-4.1 3.4-6.57 7.36-6.05 3.66.48 6.15 3.84 5.39 7.86l-2.08 11ZM264.65 124.18c1.9 3.69 1.5 7-1.64 9.4-2.5 1.91-7.24 1.65-8.89-1.79l-6.01-12.48c-1.53-3.17.59-6.55 3.25-7.77 2.45-1.13 6.44-.7 7.95 2.25l5.35 10.39Z"/></g>' ];

	$icons['parking'] = [ '<g transform="translate(80 180)"><path d="M327.46 202.97c-1.4-14-7.18-25.94-16.7-36.01-1.98-2.09-3.5-5.29-5.28-7.85l12.9-7.76c3.6-2.17 6.65-5.64 8.65-9.18 2.22-3.93 1.17-8.45-2.56-10.74-3.08-1.89-6.7-3.16-10.37-3.25l-9.69-.22-5.95 10.75-10.84-42.91c-3.68-14.58-14.42-29.33-30.23-29.41l-174.14.03c-8.88.04-16.55 3.67-21.89 10.83-3.75 5.03-6.92 10.57-8.92 16.71l-15.1 46.29-6.62-12.3c-9.24-.22-20.7.08-23.94 8.38.18 10.2 11.2 16.34 20.35 21.61C14.15 166.09 6 178.52 2.67 193.09c-2 8.76-3.05 17.39-2.56 26.45l1.95 35.89.28 26.35c.06 5.3 4.83 9.6 10.15 9.59l42.28-.07c5.23 0 9.37-4.68 9.52-9.51l.25-8.35h199.42l.38 8.77c.2 4.7 4.37 9.08 9.38 9.09l42.23.07c6.55.01 10.78-5.3 10.53-11.83-1.01-26.23 3.64-50.03.98-76.57M63.02 111.01c1.7-5.71 3.62-10.95 6.43-16.17 2.61-1.18 4.21-3.24 4.86-5.98 3.23-3.51 7.21-5.24 12.18-5.99h164.52c6.95.94 12.27 4.22 16.1 9.77 3.22 4.86 5.26 10.16 6.52 15.97l9.82 41.01H51.52L63.02 111Zm32.77 115.6c-2.34 5.15-14.11 3.66-22.97 3.58l-19.23-.17c-6.6-.06-12.23-3.01-14.73-9.33l-4-10.12c-.75-1.9-1.06-5.15.36-6.88 1.3-1.57 4.64-2.46 6.82-2.18l39.2 5.04c10.74 1.38 17.21 14.18 14.54 20.05Zm198.33-17.5L288.98 222c-2.29 5.75-8.12 7.98-14.32 8.02l-35.39.26c-2.13.02-5.06-1.55-6.04-2.87-3.71-4.96 2.26-19.47 14.51-21l39.95-5c1.65-.21 4.24.99 5.26 1.84 1.12.93 1.78 4.29 1.16 5.84Z"/><path d="m365.3 104.35-.02 153.96c0 3.6-3.99 5.93-6.6 5.87-3.44-.08-6.76-2.75-6.76-6.85V104.3l-24.78.11c-10.29.04-19.01-5.73-20.62-16.27l.16-71.42C306.7 6.78 315.91 0 325.07 0l68.14.07c8.93 0 17.42 7.43 17.44 16.61l.16 71.47c-1.67 11.05-10.99 16.3-21.66 16.27l-23.85-.06Zm-.29-42.79c4.53-.15 10.48-2.67 13.29-6.03 6.69-7.99 6.84-20.41.31-28.64-3.09-3.9-9.66-6.19-14.56-6.25l-26.1-.36c-1.63-.02-3.87 2.3-3.86 4.05l.1 58.64c4.88 1.6 9.09 1.54 13.73.09l.39-20.96z"/><path d="M366.27 47.87c-5.93 2.67-11.2 2.16-17.98 1.93l-.09-17.34c10.7-.68 19.8-1.46 21.05 6.71.34 2.25-.4 7.53-2.98 8.69ZM351.98 200.23v85.55c0 4.1 3.33 6.77 6.77 6.85 2.6.06 6.6-2.27 6.6-5.87v-86.54h-13.37Z"/></g>' ];

	$icons['car-repairs'] = [ '<g transform="translate(60 200)"><path d="M341.98 353.66c5.05-7.19 9.78-13.83 12.2-22.08 2.92-9.95 1.98-20.44-4.29-28.64-7.77-10.15-20.78-13.43-32.36-7.97-12.98 6.11-20.08 19-25.22 32.61-15.04-22.76-12.66-48.24 4.69-68.27l8-7.84 18.58-14.8c6.34-5.05 10.92-11.76 13.02-19.86l23.62-90.95c5-19.24-12.14-42.1-15.49-64.86-4.74-32.21 24.08-58.76 55.26-60.98-9.78 12.3-16.04 24.41-13.38 39.09 1.56 8.63 7.24 15.7 13.82 19.09 8.83 4.54 18.43 3.84 26.21-.73l7.28-5.49c7.35-7.2 11.6-16.02 15.41-26.07 12.63 19.49 13.47 40.9 1.19 60.42-6.53 10.37-15.79 18.28-26.1 24.82-10.84 6.88-17.85 15.65-21.04 28.32l-21.79 86.49c-2.16 8.58-2.11 16.41.93 24.75l9.59 26.3c6.86 18.8 7.35 35.43-4.77 51.97-10.37 14.16-26.78 23.06-45.37 24.7Z"/><path d="M100.13 209.71c-22.67 9.84-33.45 30.64-35.37 54.76-.91 11.46.45 22.61 2.21 34.26l-47.04-.12c-6.76-.02-12.29-3.62-15.2-9.56-2.49-5.07-4.16-10.9-4.41-16.82-.47-11.19-.3-21.99-.15-33.35.06-4.08 2.09-8.66 4.29-11.87 3.28-4.78 8.65-6.51 14.39-7.19-1.8-22.92 9.04-40.23 30.42-48.32 8.12-3.07 16.22-3.88 24.91-5.48 37.69-6.95 75.2-11.49 113.47-12.14l6.62-4.85-19.57-19.18-21.1-20.48-22.75-22.01-20.39-19.92c-15.04-14.7-29.44-9.15-40.23-11.03-8.36-1.46-13.75-9.23-15.71-16.83-.91-3.53-2.66-7-2.01-10.51.93-5.04 5.22-8.53 9.76-10.43 5.85-2.45 12.06-2.65 18.44-3.08 6.39-.43 12.81.38 18.93 2.11 9.5 2.69 16.18 8.84 22.77 15.88l93.19 99.58c28.78-19.62 58.19-36.72 89.51-51.2 7.66-3.55 15.59-5.74 23.67-7.04l1.82 13.69c-7.81 1.21-15.34 3.37-22.66 6.93-15.82 7.7-30.79 16.01-45.59 25.55l-23.77 15.31-9.73 6.82.04 23.16h109.76l-15.29 58.45-10.41 8.71c-22.69 15.08-37.59 37.25-43.68 64.28h-72.41c2.45-11.66 3.8-22.87 2.71-34.46-1.25-12.14-4.54-23.38-11.38-33.46-19.87-27.91-57.78-33.28-88.06-20.13Z"/><path d="M185.51 272.84c0 29.29-23.75 53.04-53.04 53.04s-53.04-23.75-53.04-53.04 23.75-53.04 53.04-53.04 53.04 23.75 53.04 53.04m-59.13-6.18-.3-19.2c-9.98 2.82-16.5 10.1-18.74 19.16zm31.38-.3c-2.81-10.15-9.97-16.4-19.06-18.95l-.03 19.27zm-31.49 12.4-19.14.06c2.41 9.5 9.39 17.04 19.1 19.14l.04-19.19Zm31.45.06-19.1-.08.2 19.28c10.08-2.59 16.61-9.85 18.91-19.19Z"/></g>' ];

	$icons['paper-clip'] = [ '<g transform="translate(0 0)"><path d="m35.34 241.32 187.8-188c4.23-4.23 3.03-11.41-.34-14.44s-9.93-3.9-13.95.12L21.7 226.2c-5.98 5.98-9.43 13.13-13.18 20.16-13.02 24.45-10.87 59.42 4.68 82.73 31.74 47.57 95.29 49.42 127.07 17.69l240.21-239.9c9.26-9.25 12.31-23.71 13.48-35.18 4.09-39.75-28.5-73.92-69.02-71.59-12.89.74-28.73 4.92-38.9 15.1L91.92 209.39c-18.02 18.03-14.07 48.63 2.57 64.77 18.04 17.48 47.33 18.53 65.42.45L303.3 131.33c4.08-4.08 2.02-10.54-1.68-13.32-3.13-2.35-9.51-3.46-13.36.39l-143.9 143.65c-10.06 10.05-34.09 7.32-41.83-10.24-4.07-9.23-3.62-21.08 4-28.68L299.56 30.51c16.78-16.74 45.87-12.77 61.03 2.32 17.55 16.87 18.61 42.55 4.2 62.1L126.72 332.29c-20.46 20.4-54.78 20.22-77.35 5.54-23.98-15.6-35.31-44.98-27.07-72.43 2.65-8.82 5.91-16.92 13.04-24.06Z"/><path d="M35.34 241.32c-7.14 7.14-10.4 15.24-13.04 24.06-8.24 27.45 3.09 56.83 27.07 72.43 22.58 14.69 56.89 14.86 77.35-5.54L364.79 94.91c14.41-19.55 13.35-45.23-4.2-62.1-15.16-15.09-44.26-19.06-61.03-2.32L106.53 223.12c-7.62 7.61-8.07 19.45-4 28.68 7.73 17.56 31.76 20.29 41.83 10.24l143.9-143.65c3.86-3.85 10.23-2.74 13.36-.39 3.7 2.78 5.76 9.24 1.68 13.32L159.91 274.6c-18.09 18.08-47.38 17.03-65.42-.45-16.65-16.13-20.59-46.74-2.57-64.77L286.04 15.21C296.21 5.04 312.05.85 324.94.11c40.52-2.33 73.11 31.84 69.03 71.58-1.18 11.47-4.23 25.94-13.48 35.18l-240.21 239.9c-31.78 31.74-95.32 29.89-127.07-17.69-15.55-23.3-17.7-58.28-4.68-82.73 3.74-7.03 7.19-14.18 13.18-20.16L208.85 39.01c4.02-4.02 10.67-3.06 13.95-.12s4.57 10.2.34 14.44z"/></g>' ];


	return $icons;
}


