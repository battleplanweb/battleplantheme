<?php
/* Battle Plan Web Design — Site Pulse: Module System

/*--------------------------------------------------------------
Turns Site Pulse into a modular platform. Every customer is a Battle Plan
client running their own install of the framework + Site Pulse; the
superadmin (battleplanweb) flips modules on/off PER INSTALL under
Settings → Modules.

A module is defined by three things, all hanging off one registry row:
  - site_pulse_modules()    — label, blurb, and the caps it owns
  - the caps it owns        — gated everywhere via the capability catalog
                              and site_pulse_effective_caps(), so an off
                              module's caps go inert (nav, panels, AJAX all
                              dark together)
  - the nav/panels it owns  — gated in page-site-pulse-dashboard.php

Core platform pieces (dashboard, users, tiers, locations, settings,
notifications, god tools) belong to no module and are always on.

Storage is per-install: the on/off map lives in the site_pulse config table
under the `modules` key, so each client's install is independent.
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Module Registry — single source of truth
--------------------------------------------------------------*/

/**
 * Every toggleable module. `caps` lists the capability-catalog keys the module
 * owns — when the module is off those caps drop out of the catalog and out of
 * every user's effective caps, so its nav/panels/AJAX all go dark together.
 * `default` is the state a brand-new install starts in.
 *
 * To add a future module: add a row here, tag its caps, and gate its nav in
 * the dashboard template. Nothing else needs to change.
 */
function site_pulse_modules(): array {
	return [
		'reports' => [
			'label'   => 'Reports',
			'desc'    => 'GM/Supervisor reports, report templates, and action items.',
			'caps'    => [ 'submit_reports', 'view_gm_reports', 'view_supervisor_reports', 'view_gm_action_items', 'view_supervisor_action_items', 'manage_templates' ],
			'default' => true,
		],
		'notes' => [
			'label'   => 'Notes',
			'desc'    => 'Ongoing notepads — free-form text plus a dated log — kept personal or shared with anyone. Like Action Items, but nothing to complete.',
			'caps'    => [ 'view_notes' ],
			'default' => true,
		],
		'ai' => [
			'label'   => 'AI Assistant &amp; Ask AI',
			'desc'    => 'Ask AI, which answers questions across every module, and the company-voiced AI Assistant.',
			'caps'    => [ 'use_ai_assistant' ],
			'default' => true,
		],
		'mileage' => [
			'label'   => 'Expense Report',
			'desc'    => 'Mileage logging, reimbursement reports, and NTTA toll reconciliation.',
			'caps'    => [ 'submit_mileage', 'view_tolls', 'view_vehicle_expenses', 'view_business_meals', 'view_competitive_shopping', 'view_other_expenses', 'view_travel_expenses', 'view_expense_overview', 'approve_expense_reports', 'view_approved_expense_reports', 'remember_charge_to', 'manage_mileage' ],
			'default' => true,
		],
		'forms' => [
			'label'   => 'Forms',
			'desc'    => 'Shared forms library — upload and organize files by repository (Training, Kitchen, FOH, Misc).',
			'caps'    => [ 'view_forms', 'upload_forms', 'manage_form_repositories' ],
			'default' => true,
		],
		'reviews' => [
			'label'   => 'Reviews',
			'desc'    => 'Google review aggregation, one-click replies, and one-click testimonials.',
			'caps'    => [ 'view_reviews', 'manage_reviews', 'view_customer_messages', 'manage_customer_messages' ],   // + FB/IG Direct Messages (Customer Feedback)
			'default' => false,
		],
		'surveys' => [
			'label'   => 'Comment Cards',
			'desc'    => 'Collects customer comment cards forwarded in from the public restaurant sites — ratings, comments, and per-location breakdowns. Shown as a tab under Customer Feedback.',
			'caps'    => [ 'view_surveys', 'manage_surveys', 'delete_surveys' ],
			'default' => false,
		],
		'directory' => [
			'label'   => 'Company Directory',
			'desc'    => 'A staff directory of everyone in the company — names, photos, contact info, and personal notes. Most people in it are not Site Pulse users.',
			'caps'    => [ 'view_directory', 'manage_directory' ],
			'default' => true,
		],
		'emails' => [
			'label'   => 'Customer Emails',
			'desc'    => 'Flagged customer-service emails (complaints, compliments, comments) forwarded in from the public sites\' contact forms. Shown as a tab under Customer Feedback.',
			'caps'    => [ 'view_emails', 'manage_emails', 'view_routed_emails', 'manage_routed_emails' ],   // + Routed Emails (Customer Feedback)
			'default' => true,
		],
		'fleet' => [
			'label'   => 'Site Fleet',
			'desc'    => 'Agency-only. Every Battle Plan site\'s nightly stats — traffic, last audit, keyword position, reviews, and whether each site\'s own nightly jobs are still running — on one screen. Only does anything on the hub install (bp-webdev.com); a client site can turn this on and still see nothing.',
			'caps'    => [ 'view_fleet', 'manage_fleet' ],
			'default' => false,
		],
		'customer_connect' => [
			'label'   => 'Customer Connect',
			'desc'    => 'The client\'s customers install a branded companion app (PWA) to get notifications, request scheduling, troubleshoot, and track their equipment. This panel is the staff side — the customer roster, push composer, and scheduling requests.',
			'caps'    => [ 'view_customers', 'send_customer_push', 'view_schedule_requests', 'manage_customers', 'manage_schedule_requests' ],
			'default' => false,
		],
		'booking' => [
			'label'   => 'Booking',
			'desc'    => 'Online booking — appointments, classes, and rentals on one calendar. Customers book and pay from the public site or the Customer Connect app; staff get the calendar, manual booking, availability rules, passes and memberships.',
			'caps'    => [ 'view_bookings', 'view_own_bookings', 'manage_bookings', 'manage_booking_services', 'manage_booking_payments', 'manage_booking_settings' ],
			'default' => false,
		],
		'store' => [
			'label'   => 'Store',
			'desc'    => 'Sell products online &mdash; catalog, cart, Stripe checkout, orders and inventory. Replaces WooCommerce. Products are edited in WordPress so they keep the SEO module and the media library; orders, fulfillment and stock live in Site Pulse.',
			'caps'    => [ 'view_orders', 'manage_orders', 'manage_products', 'manage_store_payments', 'manage_store_settings' ],
			'default' => false,
		],
		'schedules' => [
			'label'   => 'Shift Scheduling',
			'desc'    => 'Shift / overtime scheduling — claim OT, PDO and coverage requests, duty assignments, and the member roster. Members added on its Members screen become Site Pulse users on the Shift Member / Supervisor / Admin tiers.',
			'caps'    => [ 'use_shift_scheduling', 'manage_shift_schedules', 'manage_shift_settings' ],
			'default' => false,
		],
		'jobsites' => [
			'label'   => 'Jobsite GEO',
			'desc'    => 'Add and edit Jobsite GEO entries (real completed jobs that build the /service/ landing pages) without going into WordPress — photos, address, description, with AI rewrite and captions. Needs Jobsite GEO installed on the site; switched on automatically where it is.',
			'caps'    => [ 'view_jobsites', 'add_jobsites', 'manage_jobsites' ],
			'default' => false,
		],
	];
}

/**
 * Is a module on for THIS install? Reads the saved `modules` map (slug => '1'|'0');
 * an unset module falls back to its registry default. Unknown slugs are off.
 */
function site_pulse_module_on( string $slug ): bool {
	$mods = site_pulse_modules();
	if ( ! isset( $mods[ $slug ] ) ) return false;

	$state = json_decode( site_pulse_get_setting( 'modules', '{}' ), true );
	if ( is_array( $state ) && array_key_exists( $slug, $state ) ) {
		return (string) $state[ $slug ] === '1';
	}
	return ! empty( $mods[ $slug ]['default'] );
}

/**
 * cap => owning-module-slug, for every cap any module claims. A cap not in this
 * map is "core" (owned by no module) and is never gated.
 */
function site_pulse_cap_module_map(): array {
	static $map = null;
	if ( $map !== null ) return $map;

	$map = [];
	foreach ( site_pulse_modules() as $slug => $mod ) {
		foreach ( $mod['caps'] as $cap ) {
			$map[ $cap ] = $slug;
		}
	}
	// The dynamic per-report caps (submit_report_{slug} / view_report_{slug}) belong to the Reports module,
	// so turning Reports off correctly hides them. (Called at runtime, after the report helpers are loaded.)
	if ( function_exists( 'site_pulse_get_all_report_templates' ) ) {
		foreach ( site_pulse_get_all_report_templates() as $t ) {
			$map[ site_pulse_report_submit_cap( (string) $t['slug'] ) ] = 'reports';
			$map[ site_pulse_report_view_cap( (string) $t['slug'] ) ]   = 'reports';
		}
	}
	return $map;
}

/**
 * Drop any capability whose owning module is off. Core caps (owned by no module)
 * always pass. This is the runtime chokepoint that makes an off module inert —
 * it feeds site_pulse_effective_caps() and the (display) capability catalog.
 *
 * NOTE: this filters caps for ACCESS/DISPLAY only. Stored role/override caps are
 * always validated against site_pulse_capability_catalog_all() so toggling a
 * module off never strips a saved capability — it just sleeps until re-enabled.
 */
function site_pulse_filter_caps_by_module( array $caps ): array {
	$owner = site_pulse_cap_module_map();
	$out   = [];
	foreach ( $caps as $cap ) {
		$mod = $owner[ $cap ] ?? null;            // null = core, always keep
		if ( $mod === null || site_pulse_module_on( $mod ) ) {
			$out[] = $cap;
		}
	}
	return array_values( $out );
}


/*--------------------------------------------------------------
# Migration Seed — existing installs lose nothing
--------------------------------------------------------------*/

/**
 * One-time, on a site's FIRST Site Pulse load: write every module's starting state. Guarded by an
 * option flag, so installs that already seeded (Rovin, etc.) are never touched — and because every
 * slug is written explicitly, the registry `default` only matters for modules added to the registry
 * AFTER an install seeded (those fall through to it until a superadmin saves the Modules screen).
 *
 * A new install starts MINIMAL: Dashboard, Chat and Action Items (core, not modules) + Notes. A site can
 * name its own starting set with 'start_modules' => array( 'notes', 'reviews', … ) in its site_pulse
 * option (NOT 'modules' — site_pulse_get_setting() falls back to option keys, and 'modules' is the live
 * JSON state), and Jobsites is added automatically wherever Jobsite GEO is installed.
 */
function site_pulse_new_install_modules(): array {
	$opt = get_option( 'site_pulse' );
	$on  = ( is_array( $opt ) && isset( $opt['start_modules'] ) && is_array( $opt['start_modules'] ) ) ? array_values( $opt['start_modules'] ) : [ 'notes' ];
	if ( function_exists( 'bp_module_on' ) && bp_module_on( get_option( 'jobsite_geo' ) ) ) $on[] = 'jobsites';
	return array_unique( $on );
}

// Priority 15: AFTER the config table is created (site_pulse_install_db runs on init 10, and this file is
// required before that hook is registered, so at 10 this ran first on a brand-new install — the write hit
// a missing table, failed silently, and the seeded flag was set anyway, leaving every module on its
// registry default). The flag is now only set once the state actually reads back.
add_action( 'init', 'site_pulse_seed_modules', 15 );
function site_pulse_seed_modules(): void {
	$seeded = get_option( 'site_pulse_modules_seeded' );
	if ( $seeded && get_option( 'site_pulse_modules_repair_checked' ) ) return;

	// One-time repair: "seeded" but nothing persisted. A real seed — or any save of the Modules screen —
	// writes every slug, and 'reports' was in the registry from the very first seed, so a missing
	// 'reports' means the seed never landed (the bug above). Reseed those installs with the new-install
	// set; any install that seeded properly (Rovin) is left exactly as it is.
	$has = function (): bool {
		$s = json_decode( site_pulse_get_setting( 'modules', '{}' ), true );
		return is_array( $s ) && array_key_exists( 'reports', $s );
	};
	if ( $seeded && $has() ) { update_option( 'site_pulse_modules_repair_checked', '1' ); return; }

	$on    = site_pulse_new_install_modules();
	$state = [];
	foreach ( site_pulse_modules() as $slug => $mod ) {
		$state[ $slug ] = in_array( $slug, $on, true ) ? '1' : '0';
	}
	site_pulse_set_setting( 'modules', wp_json_encode( $state ) );

	if ( $has() ) {                                            // only mark done once it really saved
		update_option( 'site_pulse_modules_seeded', '1' );
		update_option( 'site_pulse_modules_repair_checked', '1' );
	}
}


/*--------------------------------------------------------------
# Modules AJAX — superadmin (battleplanweb) only
--------------------------------------------------------------*/

/** Only the protected super-admin, and never while impersonating, may touch modules. */
function site_pulse_modules_gate(): bool {
	if ( site_pulse_is_superadmin() && ! site_pulse_is_impersonating() ) return true;
	wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	return false;
}

add_action( 'wp_ajax_site_pulse_get_modules', 'site_pulse_ajax_get_modules' );
function site_pulse_ajax_get_modules(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_modules_gate() ) return;

	$modules = [];
	foreach ( site_pulse_modules() as $slug => $mod ) {
		$modules[] = [
			'slug'    => $slug,
			'label'   => $mod['label'],
			'desc'    => $mod['desc'],
			'enabled' => site_pulse_module_on( $slug ),
		];
	}
	wp_send_json_success( [ 'modules' => $modules ] );
}

add_action( 'wp_ajax_site_pulse_save_modules', 'site_pulse_ajax_save_modules' );
function site_pulse_ajax_save_modules(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_modules_gate() ) return;

	// Only known module slugs are written; anything missing from the post is treated as off.
	$posted = (array) ( $_POST['modules'] ?? [] );
	$state  = [];
	foreach ( site_pulse_modules() as $slug => $mod ) {
		$state[ $slug ] = ( (string) ( $posted[ $slug ] ?? '' ) === '1' ) ? '1' : '0';
	}
	site_pulse_set_setting( 'modules', wp_json_encode( $state ) );

	$on = array_keys( array_filter( $state, fn( $v ) => $v === '1' ) );
	site_pulse_log( 'modules_saved', 'Updated active modules: ' . ( $on ? implode( ', ', $on ) : 'none' ), [ 'modules' => $state ] );

	wp_send_json_success( [ 'message' => 'Modules updated.', 'modules' => $state ] );
}
