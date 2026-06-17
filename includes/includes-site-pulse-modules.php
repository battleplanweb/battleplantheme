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
			'desc'    => 'Bi-weekly GM/Supervisor reports, report templates, and action items.',
			'caps'    => [ 'view_own_reports', 'submit_reports', 'view_gm_reports', 'view_supervisor_reports', 'manage_templates' ],
			'default' => true,
		],
		'ai' => [
			'label'   => 'AI Insights &amp; Analytics',
			'desc'    => 'Cross-module analytics dashboards and AI-generated insights — fed by reports, reviews, mileage, and more.',
			'caps'    => [ 'view_analytics', 'view_ai_insights' ],
			'default' => true,
		],
		'mileage' => [
			'label'   => 'Mileage &amp; Tolls',
			'desc'    => 'Mileage logging, reimbursement reports, and NTTA toll reconciliation.',
			'caps'    => [ 'submit_mileage', 'manage_mileage' ],
			'default' => true,
		],
		'forms' => [
			'label'   => 'Forms',
			'desc'    => 'Shared forms library — upload and organize files by repository (Training, Kitchen, FOH, Misc).',
			'caps'    => [ 'view_forms', 'upload_forms' ],
			'default' => true,
		],
		'reviews' => [
			'label'   => 'Reviews',
			'desc'    => 'Google review aggregation, one-click replies, and one-click testimonials.',
			'caps'    => [ 'view_reviews', 'manage_reviews' ],
			'default' => false,
		],
		'surveys' => [
			'label'   => 'Customer Surveys',
			'desc'    => 'Collects customer satisfaction surveys forwarded in from the public restaurant sites — ratings, comments, and per-location breakdowns. Shown as a tab under Reviews.',
			'caps'    => [ 'view_surveys', 'manage_surveys' ],
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
 * One-time: persist the current module states so a live install (Rovin) behaves
 * identically the moment this ships — every already-built module defaults ON,
 * Reviews ships OFF. Guarded by an option flag, matching the existing
 * site_pulse_*_seeded idiom. Modules added to the registry later fall through to
 * their own default until a superadmin saves the Modules screen.
 */
add_action( 'init', 'site_pulse_seed_modules' );
function site_pulse_seed_modules(): void {
	if ( get_option( 'site_pulse_modules_seeded' ) ) return;

	$state = [];
	foreach ( site_pulse_modules() as $slug => $mod ) {
		$state[ $slug ] = ! empty( $mod['default'] ) ? '1' : '0';
	}
	site_pulse_set_setting( 'modules', wp_json_encode( $state ) );
	update_option( 'site_pulse_modules_seeded', '1' );
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
