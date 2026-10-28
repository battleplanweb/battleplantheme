<?php
/* Battle Plan Web Design - Site Pulse Dashboard */

if ( ! is_user_logged_in() ) {
	$printPage = '<script>window.location.href="' . esc_js( home_url('/site-pulse-login/') ) . '";</script>';
	return $printPage;
}

$real_user_id = get_current_user_id();

// Anyone still on the shared default password must set a new one before using the app.
// (Skipped while a god is impersonating — that's not their own password.)
if ( ! site_pulse_is_impersonating() && site_pulse_must_change_password( $real_user_id ) ) {
	return site_pulse_render_force_password_screen();
}

$is_god       = site_pulse_is_god( $real_user_id );
$impersonating = site_pulse_is_impersonating();
if ( function_exists( 'site_pulse_touch_last_active' ) ) site_pulse_touch_last_active(); // record real usage
// The Modules screen is for the protected super-admin (battleplanweb) only, and
// never while impersonating — so "view as <someone>" hides it like that user sees.
$is_superadmin = site_pulse_is_superadmin( $real_user_id ) && ! $impersonating;
$user_id      = site_pulse_effective_user_id();
// Is the EFFECTIVE (possibly impersonated) user a god? Gods bypass module gating and see all
// god-capability areas — so "view as <another god>" shows that god's full view, not a gated one.
$eff_is_god   = site_pulse_is_god( $user_id );
$user         = get_userdata( $user_id );
$profile      = site_pulse_get_user_profile( $user_id );
$role         = $profile ? site_pulse_get_role( $profile['role_id'] ) : null;
$location     = $profile ? site_pulse_get_location( (int) $profile['location_id'] ) : null;

$is_wp_admin = in_array( 'administrator', (array) get_userdata( $real_user_id )->roles, true );
if ( $is_god && ! $impersonating ) {
	// God sees EVERYTHING, all the time: the full capability catalog (so new caps are covered
	// automatically) plus god_mode — the UNFILTERED catalog, so a toggled-off module never hides
	// anything from god. The module filter below is skipped for god to preserve this.
	// Preference caps are the exception — UX defaults, not access — so they stay opt-in even for a
	// god. site_pulse_effective_caps() already resolves them from this god's own role/overrides.
	$caps = array_values( array_diff( array_keys( site_pulse_capability_catalog_all() ), site_pulse_preference_caps() ) );
	$caps = array_merge( $caps, array_values( array_intersect( site_pulse_effective_caps( $user_id ), site_pulse_preference_caps() ) ) );
	$caps[] = 'god_mode';
	$role_label = 'Odinson';
} elseif ( $is_wp_admin && ! $role && ! $impersonating ) {   // the REAL user's WP-admin fallback never applies to someone being viewed-as
	$caps = [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'manage_settings', 'manage_notifications', 'manage_api_keys', 'use_ai_assistant', 'view_forms', 'upload_forms', 'submit_reports', 'view_chat', 'view_action_items', 'view_notes', 'view_gm_action_items', 'view_supervisor_action_items', 'manage_mileage', 'submit_mileage', 'view_tolls', 'view_vehicle_expenses', 'view_business_meals', 'view_competitive_shopping', 'view_other_expenses', 'view_travel_expenses', 'view_expense_overview', 'approve_expense_reports', 'view_approved_expense_reports', 'manage_ai_prompts', 'manage_form_repositories' ];
	// The technical settings are God / System Admin only — a WP admin account with no Site Pulse tier
	// doesn't get them through this fallback either.
	$caps = array_values( array_diff( $caps, site_pulse_technical_caps() ) );
	$role_label = 'Administrator';
} else {
	// Effective caps = the role's capabilities with this user's per-user overrides applied, so an
	// individual tweak (e.g. mileage turned off for one supervisor) is reflected in the menu/panels.
	$caps = site_pulse_effective_caps( $user_id );
	$role_label = $role ? esc_html( $role['label'] ) : '';
}

// Drop any capability whose module is off so the menu and panels for a disabled module never
// render — EXCEPT when the EFFECTIVE user is a god. Gods bypass module gating entirely, including
// when a god impersonates ANOTHER god ("view as <god>" shows that god's full, unfiltered view).
// A wp-admin fallback, a client tier, or impersonating a client tier is still module-filtered.
if ( ! $eff_is_god ) {
	$caps = site_pulse_filter_caps_by_module( $caps );
}

$app_name     = site_pulse_get_setting( 'app_name', 'Site Pulse' );
$company_name = site_pulse_get_setting( 'company_name', '' );
$logo_url     = site_pulse_get_setting( 'login_logo_url', '' );
$header_logo  = site_pulse_get_setting( 'header_logo_url', '' ) ?: site_pulse_default_logo();
$full_name    = $user ? trim( $user->first_name . ' ' . $user->last_name ) : '';
// The superadmin (battleplanweb) can be shown under a friendly alias without changing its WP display_name.
$sp_alias     = ( $user && site_pulse_is_superadmin( (int) $user->ID ) ) ? (string) apply_filters( 'site_pulse_superadmin_alias', '' ) : '';
$display_name = $sp_alias !== '' ? esc_html( $sp_alias ) : ( $user ? esc_html( $full_name !== '' ? $full_name : $user->display_name ) : 'User' );
$loc_name     = $location ? esc_html( $location['name'] ) : '';

// Report-access gates, driven by the two report-type caps.
$cap_view_gm            = in_array( 'view_gm_reports', $caps, true );
$cap_view_sup           = in_array( 'view_supervisor_reports', $caps, true );
$cap_view_other_reports = $cap_view_gm || $cap_view_sup;
$cap_reports_access     = in_array( 'submit_reports', $caps, true ) || $cap_view_other_reports;

// Bulk-import tools (historical GM/Supervisor reports + physical comment cards), gated by the
// import_data capability. The Comment Cards importer additionally requires the Surveys module on.
$cap_import       = in_array( 'import_data', $caps, true );
$cap_import_cards = $cap_import && function_exists( 'site_pulse_module_on' ) && site_pulse_module_on( 'surveys' );

// Action-item cross-visibility gates (separate caps; OFF by default = own items only).
$cap_view_other_actions = in_array( 'view_gm_action_items', $caps, true ) || in_array( 'view_supervisor_action_items', $caps, true );

$nonce_field = wp_nonce_field( 'site_pulse_nonce', 'site_pulse_nonce_field', true, false );

$svg = function( $paths ) {
	return '<svg class="sp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
};

$icons = [
	'dashboard' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
	'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>',
	'search'    => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
	'chart'     => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
	'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
	'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
	'mappin'    => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
	'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
	'sliders'   => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
	'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
	'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
	'chevron'   => '<polyline points="6 9 12 15 18 9"/>',
	'chevron-right' => '<polyline points="9 6 15 12 9 18"/>',
	'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
	'menu'      => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
	'x'         => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
	'pulse'     => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
	'checklist' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
	'car'       => '<path d="M5 17h14M3 13l2-6h14l2 6M5 17a2 2 0 0 0 4 0M15 17a2 2 0 0 0 4 0M3 13v4h18v-4"/>',
	'store'       => '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2 2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/>',
	'dollar-sign' => '<line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
	'forms'     => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
	'layers'    => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
	'star'      => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
	'key'       => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
	'grid'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/>',
	'printer'   => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
	'mail'      => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/>',
	'chat'      => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
	// Robot face. Head 14x16 plus the two ears spans 1.4..22.6 wide and 3..21 tall, so it
	// fills more of the box than a bare ring could — a circle has to be drawn oversized to
	// look the same weight as the clipboard or the gear, and even then reads light.
	// Eyes and mouth are FILLED, not stroked: at 20px a stroked 1.4 pupil closes up into a
	// blob anyway, and filling makes the face legible instead of muddy.
	'ai-head'   => '<rect x="5" y="4" width="14" height="16" rx="4.5"/><rect x="2.4" y="9.25" width="2.6" height="5.5" rx="1.3"/><rect x="19" y="9.25" width="2.6" height="5.5" rx="1.3"/><circle cx="9.1" cy="10.6" r="1.4" fill="currentColor" stroke="none"/><circle cx="14.9" cy="10.6" r="1.4" fill="currentColor" stroke="none"/><path d="M9.3 14.1h5.4a2.7 2.7 0 0 1-5.4 0z" fill="currentColor" stroke="none"/>',
	'tasks'     => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
	'notes'     => '<path d="M16 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8z"/><polyline points="16 3 16 8 21 8"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="7" y1="16" x2="13" y2="16"/>',
	'palette'   => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.564C22 6.012 17.5 2 12 2z"/>',
	'home'      => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
	'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
	'card'      => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
];

// Build nav structure with sub-items
$nav = [];

$nav[] = [ 'slug' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'show' => true ];

// Direct messages between users. Gated by view_chat (granted to everyone by default) so access can be
// restricted per-role like any other screen.
$nav[] = [ 'slug' => 'messages', 'label' => 'Chat', 'icon' => 'chat', 'show' => in_array( 'view_chat', $caps, true ) ];

// Action Items — its own top-level item. Gated by view_action_items (granted to everyone by default).
$nav[] = [ 'slug' => 'action-items', 'label' => 'Action Items', 'icon' => 'tasks', 'show' => in_array( 'view_action_items', $caps, true ) ];

// Notes — ongoing notepads (personal or shared). Gated by view_notes (Notes module; granted to everyone by default).
$nav[] = [ 'slug' => 'notes', 'label' => 'Notes', 'icon' => 'notes', 'show' => in_array( 'view_notes', $caps, true ), 'modules' => [ 'notes' ] ];

// Reports are now data-driven: one "{Report} Reports" viewing tab per report this user can view-all, and
// "My Reports" when they can submit any. Gated on the per-report caps (legacy caps honored too).
$sp_report_templates = site_pulse_get_all_report_templates();
$sp_report_children  = [];
$sp_can_submit_any   = in_array( 'submit_reports', $caps, true );
// Nav items pluralize the LAST word of the report's own name: "GM Bi-Weekly Report" → "…Reports",
// "… Scorecard" → "… Scorecards", "Guest Count" → "Guest Counts". A last word that's already plural
// (ends in "s") is left alone. Mirror of spPluralReport() in script-site-pulse.js.
$sp_plural_report = static function ( $name ) {
	$n = trim( (string) $name );
	if ( '' === $n ) return $n;
	$pos  = strrpos( $n, ' ' );
	$head = false === $pos ? '' : substr( $n, 0, $pos + 1 );
	$word = false === $pos ? $n : substr( $n, $pos + 1 );
	if ( preg_match( '/s$/i', $word ) )                       $plural = $word;                              // already plural
	elseif ( preg_match( '/[^aeiouAEIOU]y$/', $word ) )       $plural = preg_replace( '/y$/i', 'ies', $word ); // consonant + y → ies
	elseif ( preg_match( '/(x|z|ch|sh)$/i', $word ) )         $plural = $word . 'es';
	else                                                     $plural = $word . 's';
	return $head . $plural;
};
foreach ( $sp_report_templates as $sp_t ) {
	// Use the same helpers that gate the data (they honor per-report caps, legacy caps, AND the new
	// "Who Submits / Who Views" assignments) so the menu matches what the user can actually open.
	if ( site_pulse_can_view_all_report( $user_id, $sp_t ) ) {
		$sp_report_children[] = [ 'slug' => 'reports-review', 'label' => $sp_plural_report( $sp_t['name'] ), 'action' => 'review-template', 'template_id' => (int) $sp_t['id'], 'show' => true ];
	}
	if ( ! $sp_can_submit_any && site_pulse_can_submit_report( $user_id, $sp_t ) ) $sp_can_submit_any = true;
}
$cap_reports_access = $sp_can_submit_any || ! empty( $sp_report_children );
// Re-derive the "can view someone else's reports" gate from the dynamic per-report view caps (the old
// static view_gm_reports / view_supervisor_reports caps no longer exist), so god + everyone gets the
// panels their nav tabs point at. Panel gates below use $sp_can_submit_any / $cap_view_other_reports.
$cap_view_other_reports = ! empty( $sp_report_children );

$sp_report_nav_children = array_merge(
	[ [ 'slug' => 'reports-my', 'label' => 'My Reports', 'show' => $sp_can_submit_any ] ],
	$sp_report_children,
	[ [ 'slug' => 'import-reports', 'label' => 'Import Reports', 'show' => $cap_import ] ]
);

$nav[] = [
	'slug'  => 'reports',
	'modules' => [ 'reports' ],   // owning module(s) — see the god menu split below
	'label' => 'Reports',
	'icon'  => 'store',
	'show'  => $cap_reports_access || $cap_import,
	'children' => $sp_report_nav_children,
];

// Each expense screen has its OWN capability now, so they can be granted/restricted independently.
// manage_mileage (an admin cap) still reveals everything. The parent shows if ANY expense cap is present.
$cap_mileage_manage = in_array( 'manage_mileage', $caps, true );
$cap_x_mileage  = in_array( 'submit_mileage', $caps, true ) || $cap_mileage_manage;
$cap_x_tolls    = in_array( 'view_tolls', $caps, true ) || $cap_mileage_manage;
$cap_x_vehicle  = in_array( 'view_vehicle_expenses', $caps, true ) || $cap_mileage_manage;
$cap_x_meals    = in_array( 'view_business_meals', $caps, true ) || $cap_mileage_manage;
$cap_x_shop     = in_array( 'view_competitive_shopping', $caps, true ) || $cap_mileage_manage;
$cap_x_other    = in_array( 'view_other_expenses', $caps, true ) || $cap_mileage_manage;
$cap_x_travel   = in_array( 'view_travel_expenses', $caps, true ) || $cap_mileage_manage;
$cap_x_overview = in_array( 'view_expense_overview', $caps, true ) || $cap_mileage_manage;
$cap_x_approve  = in_array( 'approve_expense_reports', $caps, true ) || $cap_mileage_manage;
$cap_x_archive  = in_array( 'view_approved_expense_reports', $caps, true ) || $cap_mileage_manage;
$cap_expense_any = $cap_x_mileage || $cap_x_tolls || $cap_x_vehicle || $cap_x_meals || $cap_x_shop || $cap_x_other || $cap_x_travel || $cap_x_overview || $cap_x_approve || $cap_x_archive;
$nav[] = [
	'slug'  => 'mileage',
	'modules' => [ 'mileage' ],   // owning module(s) — see the god menu split below
	'label' => 'Expense Report',
	'icon'  => 'dollar-sign',
	'show'  => $cap_expense_any,
	'children' => [
		[ 'slug' => 'mileage',           'label' => 'Mileage',          'show' => $cap_x_mileage ],
		[ 'slug' => 'mileage-tolls',     'label' => 'Tolls',            'show' => $cap_x_tolls ],
		[ 'slug' => 'vehicle-expenses',  'label' => 'Vehicle Expenses', 'show' => $cap_x_vehicle ],
		[ 'slug' => 'business-meals',    'label' => 'Business Meals',   'show' => $cap_x_meals ],
		[ 'slug' => 'competitive-shopping', 'label' => 'Competitive Shopping', 'show' => $cap_x_shop ],
		[ 'slug' => 'other-expenses',    'label' => 'Other Expenses',   'show' => $cap_x_other ],
		[ 'slug' => 'travel-expenses',   'label' => 'Travel Expenses',  'show' => $cap_x_travel ],
		[ 'slug' => 'expense-report',    'label' => 'Overview',         'show' => $cap_x_overview ],
		[ 'slug' => 'approve-expense',   'label' => 'Approve Reports',  'show' => $cap_x_approve ],
		[ 'slug' => 'approved-expense',  'label' => 'Accounting',       'show' => $cap_x_archive ],
	],
];

// Reviews area: Google reviews + Customer Surveys as sibling tabs. The parent shows if EITHER
// module's caps are present, so Surveys can stand alone even while Reviews (Google) is dark.
$cap_reviews_area = in_array( 'view_reviews', $caps ) || in_array( 'manage_reviews', $caps );
$cap_surveys_area = in_array( 'view_surveys', $caps ) || in_array( 'manage_surveys', $caps );
$cap_emails_area  = in_array( 'view_emails', $caps ) || in_array( 'manage_emails', $caps );
$cap_emails_manage = in_array( 'manage_emails', $caps );
$cap_cm_area      = in_array( 'view_customer_messages', $caps ) || in_array( 'manage_customer_messages', $caps );
$cap_cm_manage    = in_array( 'manage_customer_messages', $caps );
// Agency "Client Reviews": every mapped client's reviews in one place. Hub-only (this install holds
// the GBP token) and agency-only (manage_reviews / god) — invisible on client sites entirely.
$is_review_hub      = defined( 'BPGBP_REFRESH_TOKEN' ) && BPGBP_REFRESH_TOKEN;
$cap_agency_reviews = $is_review_hub && ( $eff_is_god || in_array( 'manage_reviews', $caps ) );
$nav[] = [
	'slug'  => 'reviews',
	'modules' => [ 'reviews', 'surveys', 'emails' ],   // owning module(s) — see the god menu split below
	'label' => 'Customer Feedback',
	'icon'  => 'star',
	'show'  => $cap_reviews_area || $cap_surveys_area || $cap_agency_reviews || $cap_emails_area || $cap_cm_area || $cap_import_cards,
	'children' => [
		// On the review hub (your agency site) you manage clients via Client Reviews and don't need the
		// site's own Reviews tab; client installs (e.g. Rovin) get Reviews but never Client Reviews.
		[ 'slug' => 'reviews',        'label' => 'Reviews',        'show' => $cap_reviews_area && ! $is_review_hub ],
		[ 'slug' => 'agency-reviews', 'label' => 'Client Reviews', 'show' => $cap_agency_reviews ],
			[ 'slug' => 'customer-messages', 'label' => 'Direct Messages', 'show' => $cap_cm_area ],
		[ 'slug' => 'surveys',        'label' => 'Comment Cards', 'show' => $cap_surveys_area ],
		[ 'slug' => 'import-cards',   'label' => 'Import Comment Cards', 'show' => $cap_import_cards ],
		[ 'slug' => 'emails',         'label' => 'Emails',         'show' => $cap_emails_area ],
		[ 'slug' => 'emails-routed',  'label' => 'Routed Emails',  'show' => in_array( 'view_routed_emails', $caps, true ) || in_array( 'manage_routed_emails', $caps, true ) ],
	],
];

// Company Directory — staff directory (names, photos, contact info). Most people in it are NOT
// users; access is gated purely by role + per-user override via the directory caps. God always sees.
$cap_directory        = in_array( 'view_directory', $caps, true ) || in_array( 'manage_directory', $caps, true );
$cap_directory_manage = in_array( 'manage_directory', $caps, true );
$nav[] = [ 'slug' => 'directory', 'label' => 'Directory', 'icon' => 'users', 'show' => $cap_directory, 'modules' => [ 'directory' ] ];

// Jobsites — Jobsite GEO entries (the jobs that build the /service/ pages), added/edited here instead of
// wp-admin. Only where Jobsite GEO itself is installed; caps decide view / add-own / manage-all.
$cap_jobsites_ready  = function_exists( 'sp_jobsites_ready' ) && sp_jobsites_ready();
$cap_jobsites        = $cap_jobsites_ready && ( in_array( 'view_jobsites', $caps, true ) || in_array( 'add_jobsites', $caps, true ) || in_array( 'manage_jobsites', $caps, true ) );
$cap_jobsites_add    = $cap_jobsites && ( in_array( 'add_jobsites', $caps, true ) || in_array( 'manage_jobsites', $caps, true ) );
$nav[] = [ 'slug' => 'jobsites', 'label' => 'Jobsite GEO', 'icon' => 'mappin', 'show' => $cap_jobsites, 'modules' => [ 'jobsites' ] ];

// Shift Scheduling — the scheduling app hosted in one panel (module off by default; access by the
// use_shift_scheduling / manage_shift_* caps — see includes-site-pulse-schedules.php). Each child opens
// the same panel on one of the app's views (slug schedules-{view}); members and supervisors get different
// child lists.
$cap_schedules = function_exists( 'sp_schedules_visible' ) && sp_schedules_visible();
$nav[] = [
	'slug'     => 'schedules',
	'label'    => 'Shift Scheduling',
	'icon'     => 'calendar',
	'show'     => $cap_schedules,
	'modules'  => [ 'schedules' ],
	'children' => $cap_schedules ? array_map( function ( $view, $label ) {
		return [ 'slug' => 'schedules-' . $view, 'label' => $label, 'show' => true ];
	}, array_keys( sp_schedules_nav_views() ), sp_schedules_nav_views() ) : [],
];

// Site Fleet — every Battle Plan site's nightly snapshot on one screen. Hub-only (this install
// receives the fleet's pushes) and agency-only, exactly like Client Reviews above: a client install
// with the module switched on still sees nothing, because it isn't the hub.
$cap_fleet = function_exists( 'sp_fleet_is_hub' ) && sp_fleet_is_hub()
	&& ( $eff_is_god || in_array( 'view_fleet', $caps, true ) || in_array( 'manage_fleet', $caps, true ) );
$nav[] = [ 'slug' => 'fleet', 'label' => 'Site Fleet', 'icon' => 'chart', 'show' => $cap_fleet, 'modules' => [ 'fleet' ] ];

// Home Base — the customer-app module's staff side: the customer roster, the push composer, and
// the scheduling-request inbox. Gated by the Home Base module caps (god always sees). The customer
// PWA itself lives outside Site Pulse; this is only the company-facing surface.
$cap_cc_customers = in_array( 'view_customers', $caps, true ) || in_array( 'manage_customers', $caps, true );
$cap_cc_send      = in_array( 'send_customer_push', $caps, true );
$cap_cc_schedule  = in_array( 'view_schedule_requests', $caps, true ) || in_array( 'manage_schedule_requests', $caps, true );
$nav[] = [
	'slug'  => 'customer-connect',
	'modules' => [ 'customer_connect' ],   // owning module(s) — see the god menu split below
	'label' => 'Customer Connect',
	'icon'  => 'home',
	'show'  => $cap_cc_customers || $cap_cc_send || $cap_cc_schedule,
	'children' => [
		[ 'slug' => 'customer-connect-customers', 'label' => 'Customers',  'show' => $cap_cc_customers ],
		[ 'slug' => 'customer-connect-send',      'label' => 'Send',       'show' => $cap_cc_send ],
		[ 'slug' => 'customer-connect-schedule',  'label' => 'Scheduling', 'show' => $cap_cc_schedule ],
	],
];

// Booking — the staff side of the booking engine. `view_own_bookings` is the scoped
// version (a trainer or tech sees only the resources they're linked to); everything
// else here needs the full view or a manage cap. Services & availability live under
// Settings, not here, so day-to-day staff never see the setup screens.
$cap_book_view     = in_array( 'view_bookings', $caps, true ) || in_array( 'manage_bookings', $caps, true );
$cap_book_own      = in_array( 'view_own_bookings', $caps, true );
$cap_book_manage   = in_array( 'manage_bookings', $caps, true );
$cap_book_passes   = in_array( 'manage_booking_payments', $caps, true );
$cap_book_services = in_array( 'manage_booking_services', $caps, true );
$cap_book_area     = $cap_book_view || $cap_book_own || $cap_book_manage || $cap_book_passes;
$nav[] = [
	'slug'  => 'booking',
	'modules' => [ 'booking' ],   // owning module(s) — see the god menu split below
	'label' => 'Booking',
	'icon'  => 'calendar',
	'show'  => $cap_book_area,
	'children' => [
		[ 'slug' => 'booking-calendar', 'label' => 'Calendar', 'show' => $cap_book_view || $cap_book_own ],
		[ 'slug' => 'booking-list',     'label' => 'Bookings', 'show' => $cap_book_view || $cap_book_own ],
		[ 'slug' => 'booking-passes',   'label' => 'Passes &amp; Memberships', 'show' => $cap_book_passes ],
	],
];

// Store — the staff side of the store, so a client runs it from their Site Pulse login without
// ever seeing wp-admin. Products here are the same bp_product posts wp-admin edits, saved through
// the same function (sp_store_save_product_data), so they keep the SEO module, the sitemap and the
// media library whichever screen they were edited on. Store settings (Stripe, tax, shipping
// rates, emails) live under Settings, not here.
$cap_store_orders   = in_array( 'view_orders', $caps, true ) || in_array( 'manage_orders', $caps, true );
$cap_store_manage   = in_array( 'manage_orders', $caps, true );
$cap_store_products = in_array( 'manage_products', $caps, true );
$cap_store_payments = in_array( 'manage_store_payments', $caps, true );
$cap_store_settings = in_array( 'manage_store_settings', $caps, true );
$cap_store_area     = $cap_store_orders || $cap_store_manage || $cap_store_products || $cap_store_payments;
$nav[] = [
	'slug'  => 'store',
	'modules' => [ 'store' ],   // owning module(s) — see the god menu split below
	'label' => 'Store',
	'icon'  => 'store',
	'show'  => $cap_store_area,
	'children' => [
		[ 'slug' => 'store-orders',    'label' => 'Orders',    'show' => $cap_store_orders ],
		[ 'slug' => 'store-customers', 'label' => 'Customers', 'show' => $cap_store_orders ],
		[ 'slug' => 'store-products',  'label' => 'Products',  'show' => $cap_store_products ],
		[ 'slug' => 'store-inventory', 'label' => 'Inventory', 'show' => $cap_store_products ],
	],
];

// Forms is gated on the 'view_forms' capability — and ALWAYS visible in Odin (god) mode,
// independent of the module toggle / capability catalog, so god never loses it. 'upload_forms'
// is a separate capability that reveals the per-repository upload controls (and, on its own,
// the Forms area). The AJAX endpoints mirror this god bypass.
$god_forms        = $is_god && ! $impersonating;
$cap_view_forms   = in_array( 'view_forms', $caps ) || $god_forms;
$cap_upload_forms = in_array( 'upload_forms', $caps ) || $god_forms;
$cap_forms_area   = $cap_view_forms || $cap_upload_forms;
// A repository toggled "not visible" is hidden from the Forms menu for everyone EXCEPT those who can
// manage settings (and god) — so admins can still reach it; staff don't see it. Forms aren't deleted.
$sp_sees_hidden_forms = $god_forms || in_array( 'manage_settings', $caps );
$forms_children = [];
foreach ( site_pulse_form_category_tree() as $sp_cat_key => $sp_cat ) {
	if ( empty( $sp_cat['visible'] ) && ! $sp_sees_hidden_forms ) continue;
	$child = [ 'slug' => 'forms-' . $sp_cat_key, 'label' => $sp_cat['label'], 'show' => $cap_forms_area ];
	if ( ! empty( $sp_cat['children'] ) ) {
		$subs = [];
		foreach ( $sp_cat['children'] as $sub_key => $sub_label ) {
			// Sub-folder items open the category's panel ('forms-{cat}') filtered to this sub-folder.
			$subs[] = [ 'slug' => 'forms-' . $sp_cat_key, 'sub' => $sub_key, 'label' => $sub_label, 'show' => $cap_forms_area ];
		}
		$child['subchildren'] = $subs;
	}
	$forms_children[] = $child;
}
// "All" sits at the bottom — a cross-repository, searchable view to locate a form.
$forms_children[] = [ 'slug' => 'forms-all', 'label' => 'All', 'show' => $cap_forms_area ];
$nav[] = [
	'slug'  => 'forms',
	'modules' => [ 'forms' ],   // owning module(s) — see the god menu split below
	'label' => 'Forms',
	'icon'  => 'forms',
	'show'  => $cap_forms_area,
	'children' => $forms_children,
];


// AI Assistant — company-voiced AI chat. Gated by the use_ai_assistant capability (AI module).
// Label is the company-set assistant name (default "AI Assistant"; e.g. "bAIbes").
$sp_ai_name = function_exists( 'site_pulse_ai_assistant_name' ) ? site_pulse_ai_assistant_name() : 'AI Assistant';
$nav[] = [ 'slug' => 'ai-assistant', 'label' => $sp_ai_name, 'icon' => 'ai-head', 'show' => in_array( 'use_ai_assistant', $caps ), 'modules' => [ 'ai' ] ];

// Ask AI — type a question about the company's data, get a report. In private testing: shown only to
// the battleplanweb login (sp_ask_visible), not a grantable capability yet.
$sp_ask_visible = function_exists( 'sp_ask_visible' ) && sp_ask_visible();
$nav[] = [ 'slug' => 'ask-ai', 'label' => 'Ask AI', 'icon' => 'search', 'show' => $sp_ask_visible, 'modules' => [ 'ai' ] ];

// Import tools now live inside the sections they feed: "Import Reports" under Stores and "Import
// Comment Cards" under Customer Feedback. Both are gated by the import_data capability (see above).

$nav[] = [
	'slug'  => 'admin',
	'label' => 'Settings',
	'icon'  => 'settings',
	'show'  => in_array( 'manage_locations', $caps ) || in_array( 'manage_users', $caps ) || in_array( 'manage_settings', $caps ) || in_array( 'manage_roles', $caps ) || in_array( 'manage_ai_prompts', $caps ) || in_array( 'manage_form_repositories', $caps ) || in_array( 'manage_mileage', $caps ) || in_array( 'manage_templates', $caps ) || in_array( 'manage_reviews', $caps ) || in_array( 'manage_notifications', $caps ) || in_array( 'manage_api_keys', $caps ) || $cap_book_services || in_array( 'manage_booking_settings', $caps ) || $cap_store_settings || $cap_store_products,
	'children' => [
		[ 'slug' => 'admin-users',     'label' => 'Users',            'icon' => 'users',   'show' => in_array( 'manage_users', $caps ) ],
		[ 'slug' => 'admin-tiers',     'label' => 'Roles',            'icon' => 'layers',  'show' => in_array( 'manage_roles', $caps ) ],
		[ 'slug' => 'admin-locations', 'label' => 'Home Bases',       'icon' => 'mappin',  'show' => in_array( 'manage_locations', $caps ) ],
		[ 'slug' => 'admin-templates', 'label' => 'Reports',        'icon' => 'file',    'show' => in_array( 'manage_templates', $caps ), 'modules' => [ 'reports' ] ],
		[ 'slug' => 'review-settings', 'label' => 'Reviews',        'icon' => 'star',    'show' => in_array( 'manage_reviews', $caps ) && ! $is_review_hub, 'modules' => [ 'reviews' ] ],
		[ 'slug' => 'admin-ai-prompts','label' => 'AI Prompts',     'icon' => 'chat',    'show' => in_array( 'manage_ai_prompts', $caps ) ],
		[ 'slug' => 'admin-mileage',   'label' => 'Expense Reports', 'icon' => 'car',     'show' => in_array( 'manage_mileage', $caps ), 'modules' => [ 'mileage' ] ],
		[ 'slug' => 'admin-forms',     'label' => 'Forms',          'icon' => 'forms',   'show' => in_array( 'manage_form_repositories', $caps ), 'modules' => [ 'forms' ] ],
		[ 'slug' => 'admin-customer-connect', 'label' => 'Customer Connect', 'icon' => 'home', 'show' => in_array( 'manage_settings', $caps ) && ( $cap_cc_customers || $cap_cc_send || $cap_cc_schedule ), 'modules' => [ 'customer_connect' ] ],
		[ 'slug' => 'admin-booking',   'label' => 'Booking',        'icon' => 'calendar', 'show' => $cap_book_services || in_array( 'manage_booking_settings', $caps ), 'modules' => [ 'booking' ] ],
		[ 'slug' => 'admin-store',     'label' => 'Store',          'icon' => 'store',    'show' => $cap_store_settings || $cap_store_products, 'modules' => [ 'store' ] ],
		[ 'slug' => 'admin-settings',  'label' => 'Site Defaults',  'icon' => 'palette', 'show' => in_array( 'manage_settings', $caps ) ],
		[ 'slug' => 'admin-notifications', 'label' => 'Notifications', 'icon' => 'bell',   'show' => in_array( 'manage_notifications', $caps ) ],
		[ 'slug' => 'admin-apikeys',   'label' => 'API Keys',         'icon' => 'key',     'show' => in_array( 'manage_api_keys', $caps ) ],
		[ 'slug' => 'admin-activity',  'label' => 'Activity Log',     'icon' => 'clipboard',   'show' => in_array( 'view_activity_log', $caps ) ],
		[ 'slug' => 'admin-modules',   'label' => 'Modules',          'icon' => 'layers',  'show' => $is_superadmin ],
	],
];


$printPage  = '';
$printPage .= $nonce_field;
$printPage .= site_pulse_color_scheme_css();
$printPage .= '<div id="sp-app" class="sp-app">';

// God Mode — Impersonation Bar
if ( $is_god ) {
	$all_users = site_pulse_get_all_users( true, false );
	$printPage .= '<div class="sp-god-bar" id="sp-god-bar">';
	$printPage .=   '<span class="sp-god-label">Odin Mode</span>';
	$printPage .=   '<span class="sp-god-viewing">Viewing as:</span>';
	$printPage .=   '<select class="sp-god-select" id="sp-god-user-select">';
	$printPage .=     '<option value="0"' . ( ! $impersonating ? ' selected' : '' ) . '>Myself (Odinson)</option>';

	// Group the impersonation targets by role into <optgroup>s, highest-ranked role first —
	// matching the role-grouped user pickers elsewhere. Users arrive alphabetical by name.
	$god_by_role = [];
	$god_role_rank = [];
	foreach ( $all_users as $u ) {
		$label = $u['role_label'] ?? 'No role';
		$god_by_role[ $label ][] = $u;
		$god_role_rank[ $label ] = (int) ( $u['role_rank'] ?? 0 );
	}
	uksort( $god_by_role, function ( $a, $b ) use ( $god_role_rank ) {
		return ( $god_role_rank[ $b ] <=> $god_role_rank[ $a ] ) ?: strcasecmp( $a, $b );
	} );
	foreach ( $god_by_role as $label => $group ) {
		$printPage .= '<optgroup label="' . esc_attr( $label ) . '">';
		foreach ( $group as $u ) {
			$selected = $impersonating && (int) $u['user_id'] === $user_id ? ' selected' : '';
			$printPage .= '<option value="' . (int) $u['user_id'] . '"' . $selected . '>' . esc_html( $u['display_name'] ) . '</option>';
		}
		$printPage .= '</optgroup>';
	}
	$printPage .=   '</select>';
	$printPage .= '</div>';
}

// Sidebar
$printPage .= '<aside class="sp-sidebar" id="sp-sidebar">';

// Sidebar Header — logo image (header_logo_url) OR icon + app name text, then a "powered by" attribution line
$printPage .=   '<div class="sp-header-grid sp-sidebar-header">';
$printPage .=     '<div class="sp-sidebar-brand">';
if ( $header_logo ) {
	$printPage .=     '<img src="' . esc_url( $header_logo ) . '" alt="' . esc_attr( $app_name ) . '" class="sp-sidebar-logo">';
} else {
	$printPage .=     '<div class="sp-sidebar-brand-main">';
	$printPage .=       $svg( $icons['pulse'] );
	$printPage .=       '<span class="sp-sidebar-title">' . esc_html( $app_name ) . '</span>';
	$printPage .=     '</div>';
}
$printPage .=       '<span class="sp-sidebar-powered">Powered by Battle Plan Site Pulse</span>';
$printPage .=     '</div>';
$printPage .=     '<button type="button" class="unique sp-sidebar-close" id="sp-sidebar-close" aria-label="Close menu">' . $svg( $icons['x'] ) . '</button>';
$printPage .=   '</div>';

// Account actions at the TOP of the main menu (under the logo), where they're seen immediately:
// notifications + Sign Out, white icons on the sidebar's primary background. JS moves them into the
// mobile header on phones (wiring + badge stay intact); this row then collapses (:empty) on mobile.
$printPage .= '<div class="sp-sidebar-actions" id="sp-sidebar-actions">';
$printPage .=   '<button type="button" class="unique sp-topbar-btn sp-notification-btn" id="sp-notification-btn" aria-label="Notifications">';
$printPage .=     '<svg class="sp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
$printPage .=     '<span class="sp-notification-badge" id="sp-notification-badge" hidden>0</span>';
$printPage .=   '</button>';
$printPage .=   '<button type="button" class="unique sp-topbar-btn sp-logout-btn-top" id="sp-logout-btn-top" aria-label="Sign Out">';
$printPage .=     $svg( $icons['logout'] );
$printPage .=     '<span>Sign Out</span>';
$printPage .=   '</button>';
$printPage .= '</div>';

// Sidebar Nav
//
// A god's caps are NEVER module-filtered (see site_pulse_effective_caps), so god's sidebar lists
// every module whether or not this install runs it — which makes the menu a poor map of what is
// actually loaded here. For god ONLY, split it: everything live first, then Settings, then the
// not-installed modules in a section that starts collapsed and stays out of the way.
//
// Nobody else's menu moves. A non-god's caps are already module-filtered, so their $nav simply
// contains no inactive modules to partition — the loop below sees one flat list exactly as before.
$nav_inactive = [];
if ( $eff_is_god ) {
	// An item is inactive only if it is owned by module(s) and NONE of them is on. Untagged
	// items (Dashboard, Chat, Action Items) are core — they belong to no module and always stay put.
	$sp_nav_off = function( array $item ): bool {
		$sp_mods = $item['modules'] ?? [];
		foreach ( $sp_mods as $sp_mod ) {
			if ( site_pulse_module_on( $sp_mod ) ) return false;
		}
		return ! empty( $sp_mods );
	};

	$nav_live = $nav_settings = [];
	$sp_settings_off = [];
	foreach ( $nav as $sp_nav_item ) {
		if ( $sp_nav_item['slug'] === 'admin' ) {
			// Settings is split the same way, child by child: a not-installed module's settings
			// page moves to a Settings group inside the Not Installed shelf, so the main Settings
			// menu lists only what this site actually runs.
			$sp_settings_live = [];
			foreach ( $sp_nav_item['children'] as $sp_child ) {
				if ( $sp_nav_off( $sp_child ) ) $sp_settings_off[]  = $sp_child;
				else                            $sp_settings_live[] = $sp_child;
			}
			$sp_nav_item['children'] = $sp_settings_live;
			$nav_settings[] = $sp_nav_item;
			continue;
		}

		if ( $sp_nav_off( $sp_nav_item ) ) $nav_inactive[] = $sp_nav_item;
		else                               $nav_live[]     = $sp_nav_item;
	}
	$nav = array_merge( $nav_live, $nav_settings );

	// Its own group slug, so it opens and closes independently of the main Settings group. The
	// children keep their real slugs, so they open the same panels as before.
	if ( $sp_settings_off ) {
		$nav_inactive[] = [
			'slug'     => 'admin-inactive',
			'label'    => 'Settings',
			'icon'     => 'settings',
			'show'     => (bool) array_filter( array_column( $sp_settings_off, 'show' ) ),
			'children' => $sp_settings_off,
		];
	}
}

// One item's markup, so the live list and the not-installed list render identically.
$sp_render_nav_item = function( array $item ) use ( $icons, $svg ): string {
	if ( empty( $item['show'] ) ) return '';

	$has_children = ! empty( $item['children'] );
	$is_first     = $item['slug'] === 'dashboard';

	$out  = '<div class="sp-nav-group' . ( $is_first ? ' active' : '' ) . '" data-group="' . esc_attr( $item['slug'] ) . '">';
	$out .= '<button type="button" class="unique sp-nav-item' . ( $is_first ? ' active' : '' ) . '" data-nav="' . esc_attr( $item['slug'] ) . '">';
	$out .=   $svg( $icons[ $item['icon'] ] );
	$out .=   '<span class="sp-nav-label">' . esc_html( $item['label'] ) . '</span>';
	if ( $has_children ) {
		$out .= '<span class="sp-nav-arrow">' . $svg( $icons['chevron'] ) . '</span>';
	}
	$out .= '</button>';

	if ( $has_children ) {
		// Inner wrapper so the submenu can animate open/closed via grid-template-rows (0fr↔1fr).
		$out .= '<div class="sp-nav-children"><div class="sp-nav-children-inner">';
		foreach ( $item['children'] as $child ) {
			if ( empty( $child['show'] ) ) continue;
			$child_action = ! empty( $child['action'] ) ? ' data-action="' . esc_attr( $child['action'] ) . '"' : '';
			if ( ! empty( $child['template_id'] ) ) $child_action .= ' data-template-id="' . (int) $child['template_id'] . '"';
			$out .= '<button type="button" class="unique sp-nav-child" data-nav="' . esc_attr( $child['slug'] ) . '"' . $child_action . '>';
			$out .=   '<span>' . esc_html( $child['label'] ) . '</span>';
			$out .= '</button>';
			// Optional third level: sub-folders that deep-link to the parent panel with a filter.
			if ( ! empty( $child['subchildren'] ) ) {
				$out .= '<div class="sp-nav-subchildren">';
				foreach ( $child['subchildren'] as $sub ) {
					if ( empty( $sub['show'] ) ) continue;
					$out .= '<button type="button" class="unique sp-nav-child sp-nav-subchild" data-nav="' . esc_attr( $sub['slug'] ) . '" data-sub="' . esc_attr( $sub['sub'] ) . '">';
					$out .=   '<span>' . esc_html( $sub['label'] ) . '</span>';
					$out .= '</button>';
				}
				$out .= '</div>';
			}
		}
		$out .= '</div></div>';
	}

	$out .= '</div>';
	return $out;
};

$printPage .= '<nav class="sp-sidebar-nav">';
foreach ( $nav as $item ) {
	$printPage .= $sp_render_nav_item( $item );
}

// Not installed on this site (god only). Collapsed by default so it reads as a reference list
// rather than menu clutter; the items inside stay fully functional if god wants to look.
if ( $nav_inactive ) {
	$sp_inactive_shown = 0;
	$sp_inactive_html  = '';
	foreach ( $nav_inactive as $item ) {
		$sp_item_html = $sp_render_nav_item( $item );
		if ( $sp_item_html === '' ) continue;
		$sp_inactive_html .= $sp_item_html;
		// The badge counts modules. The Settings group is just those modules' settings pages,
		// so it renders here without adding to the count.
		if ( $item['slug'] !== 'admin-inactive' ) $sp_inactive_shown++;
	}

	if ( $sp_inactive_html !== '' ) {
		$printPage .= '<div class="sp-nav-inactive-wrap" id="sp-nav-inactive">';
		$printPage .=   '<button type="button" class="unique sp-nav-inactive-toggle" id="sp-nav-inactive-toggle" aria-expanded="false" aria-controls="sp-nav-inactive-items">';
		$printPage .=     '<span class="sp-nav-inactive-label">Not Installed</span>';
		$printPage .=     '<span class="sp-nav-badge sp-nav-inactive-count">' . (int) $sp_inactive_shown . '</span>';
		$printPage .=     '<span class="sp-nav-arrow">' . $svg( $icons['chevron'] ) . '</span>';
		$printPage .=   '</button>';
		$printPage .=   '<div class="sp-nav-inactive-items" id="sp-nav-inactive-items"><div class="sp-nav-inactive-inner">';
		$printPage .=     $sp_inactive_html;
		$printPage .=   '</div></div>';
		$printPage .= '</div>';
	}
}
$printPage .= '</nav>';

$printPage .= '</aside>';

// Mobile Top Bar
$printPage .= '<header class="sp-mobile-header" id="sp-mobile-header">';
// Top row: hamburger left, notification bell + Sign Out right (the bell/logout buttons are MOVED here
// from the topbar by JS on mobile, so their wiring + badge stay intact). Icons are white via CSS.
$printPage .=   '<div class="sp-mobile-header-bar">';
$printPage .=     '<button type="button" class="unique sp-hamburger" id="sp-hamburger" aria-label="Open menu">' . $svg( $icons['menu'] ) . '</button>';
$printPage .=     '<div class="sp-mobile-header-actions" id="sp-mobile-header-actions"></div>';
$printPage .=   '</div>';
// Second row: the LOGO at the top of the page; once scrolled (JS adds .sp-scrolled) it swaps to the
// current PAGE TITLE so you always know where you are. Title text is kept in sync by JS.
$printPage .=   '<div class="sp-mobile-headline">';
$printPage .=     '<div class="sp-mobile-brand">';
if ( $header_logo ) {
	$printPage .=   '<img src="' . esc_url( $header_logo ) . '" alt="' . esc_attr( $app_name ) . '" class="sp-mobile-logo">';
} else {
	$printPage .=   '<span class="sp-mobile-title">' . esc_html( $app_name ) . '</span>';
}
$printPage .=       '<span class="sp-mobile-powered">Powered by Battle Plan Site Pulse</span>';
$printPage .=     '</div>';
// Scrolled state: the small site icon above the page title — keeps the branding and fills the space.
$printPage .=     '<div class="sp-mobile-title-wrap">';
$sp_title_icon = function_exists( 'site_pulse_pwa_preview_url' ) ? site_pulse_pwa_preview_url() : '';
if ( $sp_title_icon ) {
	$printPage .= '<img src="' . esc_url( $sp_title_icon ) . '" alt="" aria-hidden="true" class="sp-mobile-title-icon">';
}
$printPage .=       '<h2 class="sp-mobile-page-title" id="sp-mobile-page-title">Dashboard</h2>';
$printPage .=     '</div>';
$printPage .=   '</div>';
$printPage .= '</header>';

// Overlay for mobile sidebar
$printPage .= '<div class="sp-overlay" id="sp-overlay"></div>';

// Main Content
$printPage .= '<main class="sp-main" id="sp-main">';

// Top Bar — User info, notifications, logout
// Top bar removed — account actions live in the main menu now; role/identity context isn't needed
// here (the god bar still shows impersonation context).

// Notification Panel
$printPage .= '<div class="sp-notification-panel" id="sp-notification-panel" hidden>';
$printPage .=   '<div class="sp-notification-header">';
$printPage .=     '<h3>Notifications</h3>';
$printPage .=   '</div>';
$printPage .=   '<div class="sp-push-prompt" id="sp-push-prompt" hidden></div>'; // per-device "turn on push", filled by JS
$printPage .=   '<div class="sp-sms-alerts" id="sp-sms-alerts" hidden></div>'; // "text me unread chats" opt-in + settings, filled by JS
$printPage .=   '<div class="sp-notification-actions"><button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mark-all-read">Mark all read</button></div>';
$printPage .=   '<div class="sp-notification-list" id="sp-notification-list"></div>';
$printPage .= '</div>';

// Breadcrumb panel title: a small parent-section eyebrow stacked ABOVE the main page headline,
// each on its own line, with a rule above the headline (CSS) separating them. Top-level pages with
// no parent section keep a plain <h2>. Mirrors the .sp-crumb CSS.
// A bar-only button: shows in the bottom bar (never on the page). $order sets its place among the
// screen's buttons; $does is what it does — 'nav:mileage' (open a screen), 'action:new-report' (open a
// form), 'focus:#sp-x-search' (put the cursor in a field), or 'nav:other-expenses click:#sp-oexp-add-btn'.
// A real on-page button joins the bar instead with just data-sp-bar="N" (it then leaves the page).
$sp_bar_btn = function ( $order, $icon, $label, $does ) {
	$attrs = '';
	foreach ( preg_split( '/\s+/', trim( $does ) ) as $part ) {
		[ $k, $v ] = array_pad( explode( ':', $part, 2 ), 2, '' );
		if ( in_array( $k, [ 'nav', 'action', 'focus', 'click' ], true ) ) $attrs .= ' data-sp-' . $k . '="' . esc_attr( $v ) . '"';
	}
	return '<button type="button" class="unique" hidden data-sp-bar="' . (int) $order . '" data-sp-extra data-sp-icon="' . esc_attr( $icon ) . '"' . $attrs . '>' . esc_html( $label ) . '</button>';
};

$sp_crumb = function ( $parent, $current ) {
	return '<div class="sp-crumb"><div class="sp-crumb-parent">' . esc_html( $parent )
		. '</div><h2 class="sp-crumb-current">' . esc_html( $current ) . '</h2></div>';
};

// Dashboard Panel — "Your day": a summary (next up, counts, shortcuts) above the user's sections.
// The sections are user-customizable under "Customize your Dashboard" (show/hide + order). The per-user
// layout (order + hidden) lives in user meta; new sections we add later appear automatically.
$printPage .= '<section class="sp-panel active" id="sp-panel-dashboard"><div class="sp-page">';
$printPage .=   '<div class="sp-page-head" data-sp-title="Dashboard"><div class="sp-page-head__row sp-page-head__row--top">'
	.             '<div><div class="sp-page-head__eyebrow">' . esc_html( wp_date( 'l, F j' ) ) . '</div>'
	.             '<h1 class="sp-page-head__title sp-page-head__title--display">Your day, in focus.</h1></div>'
	.             '<div class="sp-page-head__actions"><button type="button" class="unique sp-link" data-sp-customize aria-label="Customize your Dashboard">' . $svg( $icons['settings'] ) . '<span class="sp-page-head__action-text">Customize your Dashboard</span></button></div>'
	.           '</div></div>';
// Bottom bar for this screen (each shows only if this person has that screen).
$printPage .= $sp_bar_btn( 1, 'doc', 'New report', 'action:new-report' )
	. $sp_bar_btn( 2, 'car', 'Log mileage', 'action:mileage-add' )
	. $sp_bar_btn( 3, 'chat', 'Chat', 'nav:messages' );

// The summary block (install reminder, messages, next up, counts, shortcuts). It's the first item in
// #sp-dashboard-widgets, so on wide screens it heads the first of the two columns the sections flow
// into. It isn't an .sp-section, so Customize never moves or hides it.
$sp_dash_lead = '';

// "Install the app" reminder card — filled + shown by JS only when NOT running as the installed app
// and the user hasn't installed it yet. (Separate from the floating install banner, which stays.)
$sp_dash_lead .= '<div class="sp-card sp-install-card" id="sp-install-card" hidden></div>';

// Important-message banners — notifications flagged "Dashboard" in the Notifications matrix, shown
// at the top until the user dismisses them.
$sp_dash_msgs = site_pulse_get_dashboard_messages( site_pulse_effective_user_id() );
if ( $sp_dash_msgs ) {
	$sp_dash_lead .= '<div class="sp-stack sp-stack--sm" id="sp-dash-messages">';
	foreach ( $sp_dash_msgs as $sp_m ) {
		$sp_dash_lead .= '<div class="sp-notice sp-notice--warning" data-sp-dash-message="' . (int) $sp_m['id'] . '">';
		$sp_dash_lead .=   '<span class="sp-notice__text">' . esc_html( $sp_m['message'] ) . '</span>';
		$sp_dash_lead .=   '<button type="button" class="unique sp-sheet__close" data-sp-dismiss="' . (int) $sp_m['id'] . '" aria-label="Acknowledge and dismiss">' . $svg( $icons['x'] ) . '</button>';
		$sp_dash_lead .= '</div>';
	}
	$sp_dash_lead .= '</div>';
}

// The summary: filled by JS from the user's open action items and their reports. Order: next up, the
// shortcuts, then the counts — last, so "Due today / Overdue" sits right above the items they count.
$sp_dash_lead .= '<div class="sp-stack" data-sp-day-hero hidden></div>';
$sp_dash_stats = '<div class="sp-stats" data-sp-day-stats hidden>'
	.             '<button type="button" class="unique sp-stat" data-nav="action-items"><span class="sp-stat__value" data-sp-stat="today">0</span><span class="sp-stat__label">Due today</span></button>'
	.             '<button type="button" class="unique sp-stat sp-stat--danger" data-nav="action-items"><span class="sp-stat__value" data-sp-stat="overdue">0</span><span class="sp-stat__label">Overdue</span></button>'
	.             ( $sp_can_submit_any ? '<button type="button" class="unique sp-stat sp-stat--warning" data-nav="reports-my"><span class="sp-stat__value" data-sp-stat="drafts">0</span><span class="sp-stat__label">Drafts</span></button>' : '' )
	.           '</div>';

$sp_dash_shortcuts = '';
if ( $sp_can_submit_any ) {
	$sp_dash_shortcuts .= '<button type="button" class="unique sp-shortcut" data-sp-action="new-report">' . $svg( $icons['file'] )
		. '<span class="sp-shortcut__main"><span class="sp-shortcut__title">New report</span><span class="sp-shortcut__sub">Start a report</span></span>' . $svg( $icons['chevron-right'] ) . '</button>';
}
if ( in_array( 'submit_mileage', $caps, true ) ) {
	$sp_dash_shortcuts .= '<button type="button" class="unique sp-shortcut" data-sp-action="mileage-add">' . $svg( $icons['car'] )
		. '<span class="sp-shortcut__main"><span class="sp-shortcut__title">Log mileage</span><span class="sp-shortcut__sub">Add today\'s stops</span></span>' . $svg( $icons['chevron-right'] ) . '</button>';
}
if ( $sp_dash_shortcuts ) $sp_dash_lead .= '<div class="sp-grid">' . $sp_dash_shortcuts . '</div>';
$sp_dash_lead .= $sp_dash_stats;

$sp_dash_uid = site_pulse_effective_user_id();
$sp_layout   = json_decode( (string) get_user_meta( $sp_dash_uid, '_sp_dashboard_layout', true ), true );
$sp_l_order  = ( is_array( $sp_layout ) && ! empty( $sp_layout['order'] ) )  ? array_map( 'strval', (array) $sp_layout['order'] )  : [];
$sp_l_hidden = ( is_array( $sp_layout ) && ! empty( $sp_layout['hidden'] ) ) ? array_map( 'strval', (array) $sp_layout['hidden'] ) : [];

// id => [ title, nav (its "View all" screen), flush (body is a list), default_hidden, body ] in default
// order. Each section's gate mirrors its nav tab, so a user can never add one for an area they can't
// access. Keep the ids in step with $known in site_pulse_ajax_save_dashboard_layout().
$sp_widgets = [];
$sp_loading = '<div class="sp-row"><span class="sp-row__main"><span class="sp-skeleton"></span><span class="sp-skeleton"></span></span></div>';

// News Feed — Google Alerts feed; only when switched on + keywords configured.
$sp_alert_kw = trim( (string) site_pulse_get_setting( 'dashboard_alert_keywords', '' ) );
$sp_news_on  = (string) site_pulse_get_setting( 'dashboard_news_enabled', '1' ) !== '0';
if ( $sp_news_on && $sp_alert_kw !== '' ) {
	if ( shortcode_exists( 'get-google-alerts' ) ) {
		$sp_alert_kw_safe = str_replace( [ '"', ']' ], '', $sp_alert_kw );
		$sp_alert_body = do_shortcode( '[get-google-alerts keywords="' . $sp_alert_kw_safe . '" max="8"]' );
	} else {
		$sp_alert_body = '<p class="sp-meta"><span>The “get-google-alerts” feature isn’t available on this site yet.</span></p>';
	}
	$sp_widgets['alerts'] = [ 'title' => 'In the News', 'body' => '<div class="sp-alert-feed-body">' . $sp_alert_body . '</div>' ];
}

// Needs your attention — the viewer's own OPEN action items. Always (matches the Action Items tab).
$sp_widgets['actions'] = [ 'title' => 'Needs your attention', 'nav' => 'action-items', 'flush' => true ];

// Chat — most recent threads + unread counts. Always (matches the Chat tab).
$sp_widgets['chat'] = [ 'title' => 'Chat', 'nav' => 'messages', 'flush' => true ];

// My Recent Reports — off by default for anyone who can't submit reports (they have no own reports).
if ( $cap_reports_access ) {
	$sp_widgets['reports'] = [ 'title' => 'Recent reports', 'nav' => 'reports-my', 'flush' => true, 'default_hidden' => ! $sp_can_submit_any ];
}

// Past-Due Reports — GMs/Supervisors overdue on their reports. Visibility is set in Settings →
// Notifications ("Reports past due" row, role-group columns).
if ( site_pulse_user_sees_pastdue_widget( $sp_dash_uid ) ) {
	$sp_widgets['pastdue'] = [ 'title' => 'Past-due reports', 'flush' => true, 'default_hidden' => true ];
}

// Mileage this month — needs mileage access.
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$sp_widgets['mileage'] = [ 'title' => 'Mileage this month', 'nav' => 'mileage' ];
}

// Customer Feedback — a count per feedback area the user can access; each opens that area.
if ( $cap_reviews_area || $cap_surveys_area || $cap_cm_area || $cap_emails_area ) {
	$sp_widgets['feedback'] = [ 'title' => 'Customer feedback', 'default_hidden' => true ];
}

// Company Directory — the directory's own filters + search; using any of them opens the filtered directory.
if ( $cap_directory ) {
	$sp_widgets['directory'] = [
		'title'          => 'Company directory',
		'nav'            => 'directory',
		'default_hidden' => true,
		'body'           => '<div class="sp-grid">'
			. '<select id="sp-dashw-dir-brand" class="sp-select"><option value="">All brands</option></select>'
			. '<select id="sp-dashw-dir-location" class="sp-select"><option value="">All locations</option></select>'
			. '<select id="sp-dashw-dir-position" class="sp-select"><option value="">All positions</option></select>'
			. '<select id="sp-dashw-dir-status" class="sp-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option></select>'
			. '</div>'
			. '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-dashw-dir-search" class="sp-input" placeholder="Search name, email, or phone"></div>',
	];
}

// Display order: saved order first (available sections only), then any new ones appended.
$sp_order = [];
foreach ( $sp_l_order as $id ) if ( isset( $sp_widgets[ $id ] ) && ! in_array( $id, $sp_order, true ) ) $sp_order[] = $id;
foreach ( array_keys( $sp_widgets ) as $id ) if ( ! in_array( $id, $sp_order, true ) ) $sp_order[] = $id;

$printPage .= '<div class="sp-stack sp-stack--lg sp-cols" id="sp-dashboard-widgets">';
$printPage .=   '<div class="sp-stack">' . $sp_dash_lead . '</div>';
foreach ( $sp_order as $id ) {
	$w = $sp_widgets[ $id ];
	// Hidden if the user hid it, OR it's default-hidden and they've never placed it.
	$is_hidden = in_array( $id, $sp_l_hidden, true )
		|| ( ! empty( $w['default_hidden'] ) && ! in_array( $id, $sp_l_order, true ) );
	$printPage .= '<section class="sp-section" id="sp-widget-' . esc_attr( $id ) . '" data-widget="' . esc_attr( $id ) . '" data-sp-title="' . esc_attr( $w['title'] ) . '"' . ( $is_hidden ? ' hidden' : '' ) . '>';
	$printPage .=   '<div class="sp-section__head"><h2 class="sp-section__title">' . esc_html( $w['title'] ) . '</h2>'
		.             ( ! empty( $w['nav'] ) ? '<button type="button" class="unique sp-link" data-nav="' . esc_attr( $w['nav'] ) . '">View all &rarr;</button>' : '' )
		.           '</div>';
	$printPage .=   '<div class="sp-card' . ( ! empty( $w['flush'] ) ? ' sp-card--flush' : '' ) . '" id="sp-widget-' . esc_attr( $id ) . '-body">' . ( $w['body'] ?? $sp_loading ) . '</div>';
	$printPage .= '</section>';
}
$printPage .= '</div>';
$printPage .= '</div></section>';

// Messages Panel — direct messages between users. Two-pane: conversation list + active thread.
if ( in_array( 'view_chat', $caps, true ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-messages"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Chat"><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Chat</h1>'
		.             '<div class="sp-page-head__actions"><button type="button" class="unique sp-btn sp-btn--primary sp-add-btn" id="sp-msg-new-btn" data-sp-bar="1" data-sp-icon="plus">' . $svg( $icons['plus'] ) . ' New Chat</button></div>'
		.           '</div></div>'
		.           $sp_bar_btn( 2, 'search', 'Search', 'focus:#sp-msg-list-search' )
		.           $sp_bar_btn( 3, 'tasks', 'Action items', 'nav:action-items' )
		.           $sp_bar_btn( 4, 'home', 'Dashboard', 'nav:dashboard' );
	// Filters the conversation list by name or last message (client-side).
	$printPage .=   '<div class="sp-control sp-control--lead sp-msg-list-search"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-msg-list-search" class="sp-input" placeholder="Search chats" autocomplete="off"></div>';
	$printPage .=   '<div class="sp-messenger" id="sp-messenger">';
	$printPage .=     '<div class="sp-msg-list" id="sp-msg-list"></div>';
	$printPage .=     '<div class="sp-msg-thread" id="sp-msg-thread"></div>';
	$printPage .=   '</div>';
	$printPage .= '</div></section>';
}

// My Reports Panel
if ( $sp_can_submit_any ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-my">';
	// The list view (hidden as a whole while a report form or detail is open).
	$printPage .= '<div class="sp-page" data-sp-list-view>';
	$printPage .=   '<div class="sp-page-head" data-sp-title="My reports"><div class="sp-page-head__eyebrow">Reports</div><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">My reports</h1>'
		.             '<div class="sp-page-head__actions"><button type="button" class="unique sp-btn sp-btn--primary sp-add-btn" id="sp-new-report-btn" data-sp-bar="2" data-sp-icon="plus">' . $svg( $icons['plus'] ) . ' Add Report</button></div>'
		.           '</div></div>'
		.           $sp_bar_btn( 1, 'doc', 'View Reports', 'nav:reports-all' )
		.           $sp_bar_btn( 3, 'search', 'Search', 'focus:#sp-report-search' )
		.           $sp_bar_btn( 4, 'home', 'Dashboard', 'nav:dashboard' );
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-report-search" class="sp-input" placeholder="Search reports" autocomplete="off"></div>';
	// Search, then tabs, then Filter: the same order as every other list screen.
	$printPage .=     '<div class="sp-tabs" role="tablist">'
		.               '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-report-tab="all">All</button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-report-tab="draft">Drafts <span class="sp-count sp-count--quiet" data-sp-draft-count hidden></span></button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-report-tab="done">Submitted</button>'
		.             '</div>';
	$printPage .=     '<div class="sp-toolbar"><button type="button" class="unique sp-filter" data-sp-report-filter>' . $svg( $icons['sliders'] ) . ' <span data-sp-report-filter-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button></div>';
	$printPage .=   '</div>';
	// Report type + dates. Not shown on the page: the filter button opens them in a sheet.
	$printPage .=   '<div hidden data-sp-report-filters>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Report type</span><select id="sp-filter-template" class="sp-select"><option value="">All reports</option></select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">From</span><input type="date" id="sp-filter-date-start" class="sp-input"></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">To</span><input type="date" id="sp-filter-date-end" class="sp-input"></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack" data-sp-draft-hero hidden></div>';
	$printPage .=   '<div class="sp-stack" id="sp-reports-list"></div>';
	$printPage .= '</div>';
	$printPage .=   '<div class="sp-page" id="sp-report-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-stack sp-report-detail-wrap" id="sp-report-detail-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Review Reports Panel
if ( $cap_view_other_reports ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-review">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><div class="sp-crumb"><div class="sp-crumb-parent">Reports</div><h2 class="sp-crumb-current" id="sp-review-title">GM Reports</h2></div></div>';
	// One Filter button (location + dates in its sheet), like every other screen. The row hides with the
	// list while a report is open (.sp-report-filters).
	$printPage .=   '<div class="sp-toolbar sp-report-filters"><button type="button" class="unique sp-filter" data-sp-review-list-filters>' . $svg( $icons['sliders'] ) . ' <span data-sp-review-list-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button></div>';
	$printPage .=   '<div hidden data-sp-review-list-filter-holder>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Location</span><select id="sp-review-filter-location" class="sp-select"><option value="">All Locations</option></select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">From</span><input type="date" id="sp-review-filter-start" class="sp-input"></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">To</span><input type="date" id="sp-review-filter-end" class="sp-input"></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack sp-reports-list" id="sp-review-list"></div>';
	$printPage .=   '<div class="sp-stack sp-report-detail-wrap" id="sp-review-detail-wrap" hidden></div>';
	$printPage .= '</section>';

	// View Reports — every report type this person can view (the same list as the menu's report
	// children), one row each; a row opens that type's list. Reached from the bottom bar.
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-all"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="View reports"><div class="sp-page-head__eyebrow">Reports</div><h1 class="sp-page-head__title">View reports</h1></div>';
	$printPage .=   '<div class="sp-card sp-card--flush"><div class="sp-list">';
	foreach ( $sp_report_children as $sp_rc ) {
		$printPage .= '<button type="button" class="unique sp-row" data-sp-view-template="' . (int) $sp_rc['template_id'] . '">'
			.           '<span class="sp-row__lead">' . $svg( $icons['file'] ) . '</span>'
			.           '<span class="sp-row__main"><span class="sp-row__title">' . esc_html( $sp_rc['label'] ) . '</span></span>'
			.           '<span class="sp-row__trail">' . $svg( $icons['chevron-right'] ?? '<path d="M9 6l6 6-6 6"/>' ) . '</span>'
			.         '</button>';
	}
	$printPage .=   '</div></div>';
	$printPage .= '</div></section>';
}

// Action Items Panel — gated by view_action_items (granted to everyone by default).
if ( in_array( 'view_action_items', $caps, true ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-action-items"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Action items"><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Action items</h1>'
		.             '<div class="sp-page-head__actions"><button type="button" class="unique sp-btn sp-btn--primary sp-add-btn" id="sp-add-action-item-btn" data-sp-bar="1" data-sp-icon="plus">' . $svg( $icons['plus'] ) . ' Add Item</button></div>'
		.           '</div></div>'
		.           $sp_bar_btn( 3, 'search', 'Search', 'focus:#sp-action-search' );
	// Summarize needs the Claude key — left out rather than offered as a button that can only fail. It's
	// pressed from the bottom bar's Summarize shortcut, so it isn't shown on the page itself.
	if ( site_pulse_get_api_key() ) {
		$printPage .= '<button type="button" class="unique" id="sp-summarize-actions-btn" hidden data-sp-bar="2" data-sp-extra data-sp-icon="sparkle">Summarize</button>';
	}
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-action-search" class="sp-input" placeholder="Search items and notes" autocomplete="off"></div>';
	$printPage .=     '<div class="sp-tabs" role="tablist">'
		.               '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-status="open">Open <span class="sp-count" data-sp-open-count hidden></span></button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-status="resolved">Completed</button>'
		.             '</div>';
	$printPage .=     '<div class="sp-toolbar">'
		.               '<button type="button" class="unique sp-filter" data-sp-filters>' . $svg( $icons['sliders'] ) . ' <span data-sp-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button>'
		// One column or two — desktop only (hidden below 1280px), remembered on this device.
		.               '<div class="sp-seg sp-seg--end" role="group" aria-label="Layout" data-sp-cols="action-items">'
		.                 '<button type="button" class="unique sp-seg__btn" data-n="1" title="One column" aria-label="One column">' . $svg( '<rect x="5" y="4" width="14" height="16" rx="2"/>' ) . '</button>'
		.                 '<button type="button" class="unique sp-seg__btn" data-n="2" title="Two columns: overdue beside current" aria-label="Two columns">' . $svg( '<rect x="3" y="4" width="7.5" height="16" rx="2"/><rect x="13.5" y="4" width="7.5" height="16" rx="2"/>' ) . '</button>'
		.                 '<button type="button" class="unique sp-seg__btn" data-n="cards" title="Cards" aria-label="Cards">' . $svg( '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>' ) . '</button>'
		.               '</div>'
		.             '</div>';
	$printPage .=   '</div>';
	// The filter controls. Not shown on the page: the one Filter button opens them all in a sheet. The
	// tabs drive the status select.
	$printPage .=   '<div hidden data-sp-action-filters>';
	if ( $cap_view_other_actions ) {
		$printPage .= '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Whose items</span><select id="sp-action-filter-location" class="sp-select"><option value="mine">Assigned to me</option></select></label>';
	}
	$printPage .=     '<select id="sp-action-filter-status" class="sp-select"><option value="open">Open</option><option value="">All</option><option value="resolved">Completed</option></select>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Label</span><select id="sp-action-filter-category" class="sp-select"><option value="">All labels</option></select></label>';
	// "Due" is anything still dated ahead, "Past due" anything already overdue.
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Due date</span><select id="sp-action-filter-due" class="sp-select"><option value="">Any due date</option><option value="due">Due</option><option value="overdue">Past due</option></select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field data-sp-summary-skip><span class="sp-field__label">Order</span><select id="sp-action-sort" class="sp-select"><option value="importance">By importance</option><option value="duedate">By due date</option><option value="custom">My own order (drag to arrange)</option></select></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack" id="sp-action-items-list"></div>';
	$printPage .= '</div></section>';
}

// Notes Panel — ongoing notepads. The list, filters and the note editor are all built client-side
// (initNotes / loadNotes in script-site-pulse.js).
if ( in_array( 'view_notes', $caps, true ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-notes"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Notes"><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Notes</h1>'
		.             '<div class="sp-page-head__actions"><button type="button" class="unique sp-btn sp-btn--primary sp-add-btn" id="sp-add-note-btn" data-sp-bar="1" data-sp-icon="plus">' . $svg( $icons['plus'] ) . ' Add Note</button></div>'
		.           '</div></div>'
		.           $sp_bar_btn( 2, 'search', 'Search', 'focus:#sp-notes-search' )
		.           $sp_bar_btn( 3, 'tasks', 'Action items', 'nav:action-items' );
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-notes-search" class="sp-input" placeholder="Search notes and log entries" autocomplete="off"></div>';
	$printPage .=     '<div class="sp-tabs" role="tablist">'
		.               '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-note-show="">All</button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-note-show="personal">Just me</button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-note-show="shared">Shared</button>'
		.             '</div>';
	$printPage .=     '<div class="sp-toolbar"><button type="button" class="unique sp-filter" data-sp-note-filters>' . $svg( $icons['sliders'] ) . ' <span data-sp-note-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button></div>';
	$printPage .=   '</div>';
	// The filter controls. Not shown on the page: the tabs drive the show select, Filter opens the rest in a sheet.
	$printPage .=   '<div hidden data-sp-notes-filter-holder>';
	$printPage .=     '<select id="sp-notes-filter-show" class="sp-select"><option value="">All notes</option><option value="personal">Just me</option><option value="shared">Shared</option></select>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Label</span><select id="sp-notes-filter-label" class="sp-select"><option value="">All labels</option></select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field data-sp-summary-skip><span class="sp-field__label">Order</span><select id="sp-notes-sort" class="sp-select">'
		.               '<option value="edited">Last edited</option>'
		.               '<option value="created">Newest</option>'
		.               '<option value="title">Title</option>'
		.               '<option value="custom">My own order (drag to arrange)</option>'
		.             '</select></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack" id="sp-notes-list"></div>';
	$printPage .= '</div></section>';
}

// AI Assistant Panel — company-voiced AI chat (role + brand voice). Loads lazily on first open.
if ( in_array( 'use_ai_assistant', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-ai-assistant">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>' . esc_html( $sp_ai_name ) . '</h2>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-ai-new">New chat</button>';
	$printPage .=   '</div>';
	$printPage .=   '<p class="sp-help-text" style="margin:0 0 12px">Pick a role, then ask for what you need. Responses are written in your company\'s voice (Settings → AI Prompts).</p>';
	$printPage .=   '<div class="sp-ai-bar">';
	$printPage .=     '<label class="sp-field-label" for="sp-ai-role">Role</label>';
	$printPage .=     '<select id="sp-ai-role" class="sp-select"></select>';
	$printPage .=     '<input type="text" id="sp-ai-role-custom" class="sp-input sp-ai-role-custom" placeholder="Describe the role…" style="display:none">';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-ai-chat" id="sp-ai-chat"></div>';
	$printPage .=   '<form class="sp-ai-composer" id="sp-ai-composer">';
	$printPage .=     '<textarea id="sp-ai-input" class="sp-input sp-ai-input" rows="3" placeholder="Ask for anything — e.g. “Write ad copy for a Mother’s Day special”"></textarea>';
	$printPage .=     '<button type="submit" class="unique sp-btn sp-btn-primary" id="sp-ai-send">Send</button>';
	$printPage .=   '</form>';
	$printPage .= '</section>';
}

// Ask AI Panel — question → AI investigates the permitted data → report. Runs step by step from JS.
if ( $sp_ask_visible ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-ask-ai">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Ask AI</h2>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-ask-new">New question</button>';
	$printPage .=   '</div>';
	$printPage .=   '<p class="sp-help-text sp-ask-intro">Ask anything about your company&#039;s data — reviews, comment cards, customer emails, manager reports, action items, expenses and mileage. The AI reads the actual records, not just keywords, and writes you a report. Bigger questions take a minute or two.</p>';
	$printPage .=   '<div class="sp-ask-layout">';
	$printPage .=     '<div class="sp-ask-main">';
	$printPage .=       '<form class="sp-ask-composer" id="sp-ask-form">';
	$printPage .=         '<textarea id="sp-ask-input" class="sp-input sp-ask-input" rows="3" maxlength="1000" placeholder="e.g. “What are customers saying about our servers over the past 6 months?”"></textarea>';
	$printPage .=         '<button type="submit" class="unique sp-btn sp-btn-primary" id="sp-ask-go">Ask</button>';
	$printPage .=       '</form>';
	$printPage .=       '<div class="sp-ask-examples" id="sp-ask-examples"></div>';
	$printPage .=       '<div class="sp-ask-output" id="sp-ask-output"></div>';
	$printPage .=     '</div>';
	$printPage .=     '<aside class="sp-ask-side">';
	$printPage .=       '<h3 class="sp-ask-side-title">Recent questions</h3>';
	$printPage .=       '<div class="sp-ask-history" id="sp-ask-history"></div>';
	$printPage .=       '<div class="sp-ask-index" id="sp-ask-index"></div>';
	$printPage .=     '</aside>';
	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// Import Past Reports Panel — pick a report type (any active template), upload PDFs/CSVs/images of
// past reports of that type, AI-parse, review/confirm attribution, and save. No action items are
// generated for imported reports. Gated by import_data.
if ( $cap_import ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-import-reports">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Import Past Reports</h2></div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-import-intro">First <strong>choose which report</strong> you\'re importing, then upload past copies of it (PDF, CSV, or image — JPG/PNG, one report per file). Claude reads each file and fills in that report\'s fields. Review the detected person, location and dates below, correct anything, then save. <strong>No action items are created</strong> for imported reports. <em>Location is optional — leave it blank for reports that aren\'t tied to a store.</em></div></div>';
	$printPage .=   '<label class="sp-import-f sp-import-tpl"><span>Which report are you importing?</span><select class="sp-select" id="sp-import-template"><option value="0">Loading…</option></select></label>';
	$printPage .=   '<div class="sp-import-drop is-locked" id="sp-import-drop">';
	$printPage .=     '<input type="file" id="sp-import-files" accept=".pdf,.csv,.jpg,.jpeg,.jpe,.jfif,.png,application/pdf,text/csv,image/jpeg,image/png" multiple hidden>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-import-pick">Choose PDF / CSV / Image files</button>';
	$printPage .=     '<span class="sp-import-hint">…or drop files here</span>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-import-list" id="sp-import-list"></div>';
	$printPage .=   '<div class="sp-import-actions" id="sp-import-actions" hidden>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-import-save-all">Save All Checked Reports</button>';
	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// Import Comment Cards Panel — upload images/PDFs of physical customer comment cards, AI-read the
// ratings + contact info + comments, review/confirm, and save into the Surveys table. No "new comment
// card" notification is fired for imported cards. Gated by import_data + the Surveys module being on.
if ( $cap_import_cards ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-import-cards">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Import Comment Cards</h2></div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-import-intro">Upload photos or scans of <strong>customer comment cards</strong> (JPG, PNG, or PDF — one card per file). Claude reads each card and fills in the <strong>star ratings, contact info, visit date, and comments</strong>. Review the detected info below, set the <strong>location</strong>, correct anything, then save. Cards are added to <strong>Reviews → Surveys</strong>; <strong>no notification</strong> is sent.</div></div>';
	$printPage .=   '<div class="sp-import-drop" id="sp-card-import-drop">';
	$printPage .=     '<input type="file" id="sp-card-import-files" accept=".jpg,.jpeg,.jpe,.jfif,.png,.pdf,image/jpeg,image/png,application/pdf" multiple hidden>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-card-import-pick">Choose card images / PDFs</button>';
	$printPage .=     '<span class="sp-import-hint">…or drop files here</span>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-import-list" id="sp-card-import-list"></div>';
	$printPage .=   '<div class="sp-import-actions" id="sp-card-import-actions" hidden>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-card-import-save-all">Save All Checked Cards</button>';
	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// Expense Report cover page — composes Sections A–F into one report with the Summary of
// Expenses (totals by GL account) and Total Due to Employee. Data loads lazily on activation.
if ( $cap_x_overview ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-expense-report"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Expense report"><div class="sp-page-head__eyebrow">Expense report</div><h1 class="sp-page-head__title">Overview</h1>'
		.           '<p class="sp-page-head__lead">Everything from every section, totalled for the period, in the same layout as your printed expense report. Pick a pay period, then export to PDF.</p></div>'
		.           $sp_bar_btn( 1, 'car', 'Mileage', 'nav:mileage' )
		.           $sp_bar_btn( 2, 'meal', 'Meal', 'nav:business-meals click:#sp-meal-add-btn' )
		.           $sp_bar_btn( 3, 'receipt', 'Receipt', 'nav:other-expenses click:#sp-oexp-add-btn' );
	$printPage .=   '<div id="sp-rep-toolbar-wrap"></div>';
	$printPage .=   '<div class="sp-rep-content" id="sp-rep-content"></div>';
	$printPage .= '</div></section>';
}

// Mileage Panel
if ( $cap_x_mileage ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-mileage">';
	// The list view (hidden as a whole while the Add/Edit-a-day form is open).
	$printPage .= '<div class="sp-page" data-sp-list-view>';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Mileage"><div class="sp-page-head__eyebrow">Expense report</div><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Mileage</h1>'
		.             '<div class="sp-page-head__actions"><button type="button" class="unique sp-btn sp-btn--primary sp-add-btn" id="sp-mileage-add-btn" data-sp-bar="1" data-sp-icon="plus">' . $svg( $icons['plus'] ) . ' Add Day</button></div>'
		.           '</div></div>'
		.           $sp_bar_btn( 2, 'receipt', 'Receipt', 'nav:other-expenses click:#sp-oexp-add-btn' )
		.           $sp_bar_btn( 3, 'meal', 'Meal', 'nav:business-meals click:#sp-meal-add-btn' );
	// Listed under More rather than shown on the page.
	$printPage .=   '<button type="button" class="unique" id="sp-mileage-pdf-btn" hidden data-sp-more-action data-sp-icon="doc" data-sp-sub="A PDF of the days in this period">Export PDF</button>';
	$printPage .=   '<button type="button" class="unique" id="sp-mileage-map-btn" hidden data-sp-more-action data-sp-icon="pin" data-sp-sub="Your routes on a map">Route map</button>';
	$printPage .=   '<button type="button" class="unique sp-filter sp-filter--block" data-sp-period>' . $svg( $icons['calendar'] ) . ' <span data-sp-period-label>This period</span> ' . $svg( $icons['chevron'] ) . '</button>';
	// Custom dates, shown in the period sheet. loadMileageEntries() reads these.
	$printPage .=   '<div hidden data-sp-mileage-dates>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">From</span><input type="date" id="sp-mileage-filter-start" class="sp-input"></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">To</span><input type="date" id="sp-mileage-filter-end" class="sp-input"></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack" id="sp-mileage-summary"></div>';
	$printPage .=   '<section class="sp-section"><h2 class="sp-section__title">Days</h2><div class="sp-stack" id="sp-mileage-list"></div></section>';
	$printPage .= '</div>';
	$printPage .=   '<div class="sp-page" id="sp-mileage-form-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Reconcile Tolls Panel — upload a toll-authority CSV, then AI matches each charge
// to the legs of the days you logged trips. Review the matches, then apply.
if ( $cap_x_tolls ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-mileage-tolls">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Expense Report','Tolls' ) . '</div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-toll-intro">Upload the CSV or Excel (.xlsx) export from your toll account (e.g. NTTA). We only look at the days you logged a trip — charges on any other date are ignored. AI matches each toll to a leg of that day\'s route; you review before anything is added.</div></div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-toll-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	// accept= keeps phone file-pickers on document sources (otherwise Android offers Camera, and can
	// stick there as the remembered default). octet-stream is in the list because NTTA exports
	// sometimes have no extension — without it the picker greys them out. Content is still sniffed
	// on upload, so this is a picker hint only, never the validation.
	$printPage .=       '<input type="file" id="sp-toll-file" class="sp-toll-file" accept=".csv,.xlsx,text/csv,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-toll-upload-btn" disabled>Upload</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-toll-status" id="sp-toll-status" hidden></div>';
	$printPage .=   '<div class="sp-toll-days" id="sp-toll-days"></div>';
	$printPage .= '</section>';
}

// Mileage Map Panel (reached from the Mileage screen's Map button — shares the mileage capability).
if ( $cap_x_mileage ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-mileage-map">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Expense Report','Mileage Map' ) . '</div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-mileage-map-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<select id="sp-mileage-map-category" class="sp-select"><option value="">All categories</option></select>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-map-fit">Fit All</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-mileage-map-notice" id="sp-mileage-map-notice" hidden>Add a Google Maps API key to enable the map. The mileage location key is reused; make sure it has the <strong>Maps JavaScript API</strong> enabled and is restricted to this site.</div>';
	$printPage .=   '<div class="sp-card sp-table-card"><div id="sp-mileage-map-canvas" class="sp-mileage-map-canvas"></div></div>';
	$printPage .= '</section>';
}

// The expense-entry panels (Sections B–F) share one layout: page head with Add, period toolbar,
// summary, the add/edit form (shown in place of the list) and the list. The toolbar, lists and forms
// are built client-side; data loads lazily on activation.
$sp_expense_panel = function ( $panel, $prefix, $title, $add_label, $intro, $bar = '' ) use ( $svg, $icons ) {
	return '<section class="sp-panel" id="sp-panel-' . $panel . '"><div class="sp-page">'
		. '<div class="sp-page-head" id="sp-' . $prefix . '-head" data-sp-title="' . esc_attr( $title ) . '"><div class="sp-page-head__eyebrow">Expense report</div><div class="sp-page-head__row">'
		.   '<h1 class="sp-page-head__title">' . esc_html( $title ) . '</h1>'
		.   '<div class="sp-page-head__actions"><button type="button" class="unique sp-btn sp-btn--primary sp-add-btn" id="sp-' . $prefix . '-add-btn" data-sp-bar="1" data-sp-icon="plus">' . $svg( $icons['plus'] ) . ' ' . esc_html( ucwords( $add_label ) ) . '</button></div>'
		. '</div><p class="sp-page-head__lead">' . $intro . '</p></div>' . $bar
		. '<div id="sp-' . $prefix . '-toolbar-wrap"></div>'
		. '<div class="sp-stack" id="sp-' . $prefix . '-summary"></div>'
		. '<div id="sp-' . $prefix . '-form-wrap" hidden></div>'
		. '<div class="sp-stack" id="sp-' . $prefix . '-content"></div>'
		. '</div></section>';
};
// Bar shortcuts shared by the expense screens (each shows only if this person has that screen).
$sp_mil  = $sp_bar_btn( 2, 'car', 'Mileage', 'nav:mileage' );
$sp_rcpt = $sp_bar_btn( 3, 'receipt', 'Receipt', 'nav:other-expenses click:#sp-oexp-add-btn' );
$sp_meal = $sp_bar_btn( 3, 'meal', 'Meal', 'nav:business-meals click:#sp-meal-add-btn' );
if ( $cap_x_vehicle ) {
	$printPage .= $sp_expense_panel( 'vehicle-expenses', 'vexp', 'Vehicle expenses', 'Add expense', 'Company-driven fuel, washes, parking and repairs, plus personal tolls &amp; trailer costs. These feed Section B of your expense report.', $sp_mil . $sp_rcpt );
}
if ( $cap_x_meals ) {
	$printPage .= $sp_expense_panel( 'business-meals', 'meal', 'Business meals', 'Add meal', 'Business meals, entertainment and meeting expenses. Note where you were, the business purpose, and who attended. These feed Section C of your expense report.', $sp_mil . $sp_rcpt );
}
if ( $cap_x_shop ) {
	$printPage .= $sp_expense_panel( 'competitive-shopping', 'shop', 'Competitive shopping', 'Add visit', 'Meals bought at other restaurants for research. Note where you went, the business purpose, and the store you shopped for. These feed Section D of your expense report.', $sp_mil . $sp_rcpt );
}
if ( $cap_x_other ) {
	$printPage .= $sp_expense_panel( 'other-expenses', 'oexp', 'Other expenses', 'Add expense', 'Anything that doesn\'t fit the other sections: food R&amp;D, home office, postage and the like. Enter the GL account for each line. These feed Section E of your expense report.', $sp_mil . $sp_meal );
}
if ( $cap_x_travel ) {
	$printPage .= $sp_expense_panel( 'travel-expenses', 'trav', 'Travel expenses', 'Add expense', 'Airfare, hotels, rental cars and taxis, and meals while traveling. These feed Section F of your expense report.', $sp_mil . $sp_meal );
}

// Approve Reports queue (supervisors) — submitted expense reports routed to this approver. Data lazy-loads.
if ( $cap_x_approve ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-approve-expense">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Expense Report','Approve Reports' ) . '</div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-rep-intro">Expense reports your team has submitted. Open one to review it, then Approve (emails it to accounting) or send it back.</div></div>';
	$printPage .=   '<div class="sp-approve-content" id="sp-approve-content"></div>';
	$printPage .=   '<div class="sp-approve-detail" id="sp-approve-detail" hidden></div>';
	$printPage .= '</section>';
}

// Approved Reports archive — every approved expense report (special view capability). Data lazy-loads.
if ( $cap_x_archive ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-approved-expense">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Expense Report','Accounting' ) . '</div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-rep-intro">The archive of approved expense reports. Filter by date, then open the frozen PDF that was sent to accounting.</div></div>';
	$printPage .=   '<div class="sp-toolbar sp-period-toolbar" id="sp-approved-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<input type="date" id="sp-approved-start" class="sp-input" placeholder="From">';
	$printPage .=       '<input type="date" id="sp-approved-end" class="sp-input" placeholder="To">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-approved-clear">Clear</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-approved-content" id="sp-approved-content"></div>';
	$printPage .=   '<div class="sp-approve-detail" id="sp-approved-detail" hidden></div>';
	$printPage .= '</section>';
}

// Reviews Settings Panel (Settings → Reviews) — configure the "super categories" (+ sub-categories)
// reviews are ranked into. The editor renders client-side; data loads lazily on activation.
if ( in_array( 'manage_reviews', $caps ) && ! $is_review_hub ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-review-settings">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Reviews' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-review-settings-content"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

// Reviews Panel (Google Business Profile). Data loads lazily on first activation.
if ( in_array( 'view_reviews', $caps ) || in_array( 'manage_reviews', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reviews"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Reviews"><div class="sp-page-head__eyebrow">Customer feedback</div><h1 class="sp-page-head__title">Reviews</h1>'
		.           '<p class="sp-page-head__lead" id="sp-reviews-analyze-status" aria-live="polite"></p></div>';
	// Listed under More rather than shown on the page.
	if ( in_array( 'manage_reviews', $caps ) || $eff_is_god ) {
		$printPage .= '<button type="button" class="unique" id="sp-reviews-analyze-btn" hidden data-sp-bar="3" data-sp-extra data-sp-icon="sparkle" data-sp-sub="Tag new reviews with topics and sentiment">Analyze reviews</button>';
	}
	// God-only CSV of the stored history (every platform, reply, AI topic and internal note) for
	// offline analysis. Exports whatever the current filters match — All time + no filters = everything.
	if ( site_pulse_god_can_override() ) {
		$printPage .= '<button type="button" class="unique" id="sp-reviews-export-btn" hidden data-sp-bar="2" data-sp-extra data-sp-icon="doc" data-sp-sub="A CSV of the reviews these filters match">Export CSV</button>';
	}
	$printPage .=   '<button type="button" class="unique" id="sp-reviews-refresh-btn" hidden data-sp-bar="1" data-sp-extra data-sp-icon="bolt" data-sp-sub="Pull the newest reviews now">Find New Reviews</button>';

	// One Filter sheet holds everything — time range, restaurant, brand, rating, platform; the tabs drive
	// the reply filter. They drive the stat tiles and the list.
	$printPage .=   '<div class="sp-toolbar">'
		.             '<button type="button" class="unique sp-filter" data-sp-review-filters>' . $svg( $icons['sliders'] ) . ' <span data-sp-review-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button>'
		.           '</div>';
	$printPage .=   '<div hidden data-sp-review-filter-holder>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Time range</span><select id="sp-reviews-stat-range" class="sp-select">'
		.               '<option value="30" selected>Last 30 days</option><option value="90">Last 90 days</option><option value="365">Last 12 months</option><option value="ytd">Year to date</option><option value="all">All time</option>'
		.             '</select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Restaurant</span><select id="sp-reviews-stat-restaurant" class="sp-select"><option value="">All restaurants</option></select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Brand</span><select id="sp-reviews-stat-brand" class="sp-select"><option value="">All brands</option></select></label>';
	$printPage .=     '<select id="sp-reviews-filter-reply" class="sp-select"><option value="">All reviews</option><option value="unreplied">Needs reply</option><option value="replied">Replied</option></select>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Rating</span><select id="sp-reviews-filter-stars" class="sp-select">'
		.               '<option value="">All ratings</option><option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option>'
		.             '</select></label>';
	// Platforms filter — narrow the list to one source (e.g. only Facebook recommendations). Options come
	// from the platforms actually present in the reviews table (sp_review_sources()). Guarded: if a partial
	// deploy / stale OPcache leaves the reviews include out of sync, fall back to a single option instead
	// of fataling the whole dashboard.
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Platform</span><select id="sp-reviews-filter-platform" class="sp-select">'
		.               ( function_exists( 'sp_review_source_options_html' ) ? sp_review_source_options_html() : '<option value="">All platforms</option>' )
		.             '</select></label>';
	$printPage .=   '</div>';

	$printPage .=   '<div class="sp-reviews-stats" id="sp-reviews-stats"></div>';
	$printPage .=   '<div class="sp-tabs" role="tablist">'
		.             '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-review-reply="">All</button>'
		.             '<button type="button" class="unique sp-tab" role="tab" data-sp-review-reply="unreplied">Needs reply</button>'
		.             '<button type="button" class="unique sp-tab" role="tab" data-sp-review-reply="replied">Replied</button>'
		.           '</div>';
	$printPage .=   '<div class="sp-stack sp-reviews-list" id="sp-reviews-list"></div>';
	$printPage .=   '<div class="sp-reviews-more">';
	$printPage .=     '<p class="sp-meta" id="sp-reviews-count" hidden></p>';
	$printPage .=     '<button type="button" class="unique sp-btn" id="sp-reviews-load-older" hidden>Load older reviews</button>';
	$printPage .=   '</div>';
	$printPage .= '</div></section>';
}

// Client Reviews Panel (agency / hub only). Every mapped client's Google reviews grouped by
// client, with reply + one-click push of a review to that client's site as a Testimonial CPT.
if ( ( defined( 'BPGBP_REFRESH_TOKEN' ) && BPGBP_REFRESH_TOKEN ) && ( $eff_is_god || in_array( 'manage_reviews', $caps ) ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-agency-reviews"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Client reviews"><div class="sp-page-head__eyebrow">Agency</div><h1 class="sp-page-head__title">Client reviews</h1></div>';
	$printPage .=   '<button type="button" class="unique" id="sp-agency-reviews-refresh-btn" hidden data-sp-more-action data-sp-icon="bolt" data-sp-sub="Pull every client\'s newest reviews">Refresh reviews</button>';
	$printPage .=   '<div class="sp-toolbar" id="sp-agency-reviews-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<input type="search" id="sp-agency-client-search" class="sp-input" list="sp-agency-client-list" placeholder="Search company…" autocomplete="off">';
	$printPage .=       '<datalist id="sp-agency-client-list"></datalist>';
	$printPage .=       '<select id="sp-agency-filter-client" class="sp-select"><option value="">All clients</option></select>';
	$printPage .=       '<select id="sp-agency-filter-stars" class="sp-select">';
	$printPage .=         '<option value="">All ratings</option>';
	$printPage .=         '<option value="5">5 stars</option>';
	$printPage .=         '<option value="4">4 stars</option>';
	$printPage .=         '<option value="3">3 stars</option>';
	$printPage .=         '<option value="2">2 stars</option>';
	$printPage .=         '<option value="1">1 star</option>';
	$printPage .=       '</select>';
	$printPage .=       '<select id="sp-agency-filter-platform" class="sp-select">';
	$printPage .=         '<option value="">All platforms</option>';
	$printPage .=         '<option value="google">Google</option>';
	$printPage .=         '<option value="facebook">Facebook</option>';
	$printPage .=       '</select>';
	$printPage .=       '<select id="sp-agency-filter-reply" class="sp-select">';
	$printPage .=         '<option value="">All reviews</option>';
	$printPage .=         '<option value="unreplied">Needs reply</option>';
	$printPage .=         '<option value="replied">Replied</option>';
	$printPage .=       '</select>';
	$printPage .=       '<select id="sp-agency-filter-sort" class="sp-select">';
	$printPage .=         '<option value="oldest" selected>Oldest first</option>';
	$printPage .=         '<option value="newest">Newest first</option>';
	$printPage .=       '</select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack sp-reviews-list" id="sp-agency-reviews-list"><div class="sp-loading"></div></div>';
	$printPage .= '</div></section>';
}

// Site Fleet Panel (agency / hub only). Every Battle Plan site's nightly snapshot in one sortable
// list, problem sites first. Same double gate as the nav item: hub install + agency capability.
if ( function_exists( 'sp_fleet_is_hub' ) && sp_fleet_is_hub()
	&& ( $eff_is_god || in_array( 'view_fleet', $caps, true ) || in_array( 'manage_fleet', $caps, true ) ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-fleet"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Site fleet"><div class="sp-page-head__eyebrow">Agency</div><h1 class="sp-page-head__title">Site fleet</h1></div>';
	$printPage .=   '<button type="button" class="unique" id="sp-fleet-refresh-btn" hidden data-sp-more-action data-sp-icon="bolt" data-sp-sub="Reload every site\'s latest snapshot">Refresh fleet</button>';
	$printPage .=   '<div class="sp-stack" id="sp-fleet-stats"></div>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-fleet-search" class="sp-input" placeholder="Search sites" autocomplete="off"></div>';
	$printPage .=     '<div class="sp-toolbar"><button type="button" class="unique sp-filter" data-sp-fleet-filters>' . $svg( $icons['sliders'] ) . ' <span data-sp-fleet-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button></div>';
	$printPage .=   '</div>';
	// Show / order / direction, shown in the Filter sheet.
	$printPage .=   '<div hidden data-sp-fleet-filter-holder>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Show</span><select id="sp-fleet-filter" class="sp-select">'
		.               '<option value="">All sites</option><option value="alerts">Needs attention</option><option value="silent">Not reporting</option><option value="audit">Audit due</option>'
		.             '</select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field data-sp-summary-skip><span class="sp-field__label">Order</span><select id="sp-fleet-sort" class="sp-select">'
		.               '<option value="severity" selected>Problems first</option><option value="name">Name</option><option value="traffic">Traffic</option><option value="change">Biggest change</option><option value="audit">Oldest audit</option>'
		.             '</select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Direction</span><select id="sp-fleet-sort-dir" class="sp-select"><option value="" selected>Normal order</option><option value="reverse">Reverse order</option></select></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack" id="sp-fleet-list"></div>';
	$printPage .= '</div></section>';
}

// Customer Surveys Panel — submissions forwarded in from the public restaurant sites.
// Sibling tab of Reviews; gated on the surveys module's caps (independent of Google reviews).
if ( in_array( 'view_surveys', $caps ) || in_array( 'manage_surveys', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-surveys"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Comment cards"><div class="sp-page-head__eyebrow">Customer feedback</div><h1 class="sp-page-head__title">Comment cards</h1></div>';
	$printPage .=   '<button type="button" class="unique" id="sp-survey-refresh-btn" hidden data-sp-bar="1" data-sp-extra data-sp-icon="bolt" data-sp-sub="Load the newest comment cards">Find New Comment Cards</button>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-survey-search" class="sp-input" placeholder="Search name, phone or address" autocomplete="off"></div>';
	$printPage .=     '<div class="sp-toolbar"><button type="button" class="unique sp-filter" data-sp-survey-filters>' . $svg( $icons['sliders'] ) . ' <span data-sp-survey-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button></div>';
	$printPage .=   '</div>';
	// Location, time range and source, shown in the Filter sheet.
	$printPage .=   '<div hidden data-sp-survey-filter-holder>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Location</span><select id="sp-survey-filter-location" class="sp-select"><option value="">All locations</option></select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Time range</span><select id="sp-survey-filter-range" class="sp-select">'
		.               '<option value="">All time</option><option value="30">Last 30 days</option><option value="90">Last 90 days</option><option value="365">Last 12 months</option><option value="ytd">Year to date</option>'
		.             '</select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Source</span><select id="sp-survey-filter-source" class="sp-select"><option value="">All sources</option><option value="web">Web</option><option value="imported">Imported</option></select></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reviews-stats" id="sp-survey-summary"></div>';
	// Active / Archived: which list you're on (the Archived tab carries the count).
	$printPage .=   '<div class="sp-tabs" role="tablist">'
		.             '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-survey-view="active">Active</button>'
		.             '<button type="button" class="unique sp-tab" role="tab" data-sp-survey-view="archived">Archived <span class="sp-count sp-count--quiet" data-sp-survey-archived-count hidden></span></button>'
		.           '</div>';
	$printPage .=   '<div class="sp-stack sp-survey-list" id="sp-survey-list"></div>';
	$printPage .= '</div></section>';
}

// New / Handled / All tabs, shared by both email panels (they drive each panel's status select).
$sp_email_tabs = '<div class="sp-tabs" role="tablist">'
	. '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-email-status="new">New</button>'
	. '<button type="button" class="unique sp-tab" role="tab" data-sp-email-status="handled">Handled</button>'
	. '<button type="button" class="unique sp-tab" role="tab" data-sp-email-status="all">All</button>'
	. '</div>';

// Customer Emails Panel — flagged customer-service emails forwarded in from the public sites. List of
// message cards with status / type / brand / location filters + search; mark-handled + delete for managers.
if ( $cap_emails_area ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-emails"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Emails"><div class="sp-page-head__eyebrow">Customer feedback</div><h1 class="sp-page-head__title">Emails</h1></div>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-emails-search" class="sp-input" placeholder="Search name, email, phone or message" autocomplete="off"></div>';
	$printPage .=     $sp_email_tabs;
	$printPage .=     '<div class="sp-toolbar"><button type="button" class="unique sp-filter" data-sp-emails-filters>' . $svg( $icons['sliders'] ) . ' <span data-sp-emails-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button><span class="sp-meta" id="sp-emails-count"></span></div>';
	$printPage .=   '</div>';
	// Type / brand / location, shown in the Filter sheet; the tabs drive the status select.
	$printPage .=   '<div hidden data-sp-emails-filter-holder>';
	$printPage .=     '<select id="sp-emails-filter-status" class="sp-select"><option value="new">New</option><option value="handled">Handled</option><option value="all">All</option></select>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Type</span><select id="sp-emails-filter-category" class="sp-select"><option value="">All types</option></select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Brand</span><select id="sp-emails-filter-brand" class="sp-select"><option value="">All brands</option></select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Location</span><select id="sp-emails-filter-location" class="sp-select"><option value="">All locations</option></select></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack" id="sp-emails-list"></div>';
	$printPage .= '</div></section>';
}

// Routed Emails Panel — a generic retention center for forwarded emails addressed to a specific person.
// A viewer sees only the ones addressed to their account email; a manager (manage_routed_emails) sees all.
if ( in_array( 'view_routed_emails', $caps, true ) || in_array( 'manage_routed_emails', $caps, true ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-emails-routed"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Routed emails"><div class="sp-page-head__eyebrow">Customer feedback</div><h1 class="sp-page-head__title">Routed emails</h1></div>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-remails-search" class="sp-input" placeholder="Search subject, sender or message" autocomplete="off"></div>';
	$printPage .=     $sp_email_tabs;
	$printPage .=     '<p class="sp-meta" id="sp-remails-count"></p>';
	$printPage .=   '</div>';
	$printPage .=   '<select id="sp-remails-filter-status" class="sp-select" hidden><option value="new">New</option><option value="handled">Handled</option><option value="all">All</option></select>';
	$printPage .=   '<div class="sp-stack" id="sp-remails-list"></div>';
	$printPage .= '</div></section>';
}

// Customer Messages Panel — Facebook / Instagram DMs. Two-pane inbox reusing the Messages styling
// (conversation list + thread). Inbound arrives via the hub relay; replies go back out through the hub.
if ( $cap_cm_area ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-customer-messages"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Direct messages"><div class="sp-page-head__eyebrow">Customer feedback</div><h1 class="sp-page-head__title">Direct messages</h1>'
		.           '<p class="sp-page-head__lead">Facebook and Instagram messages from customers.</p></div>';
	$printPage .=   '<div class="sp-messenger" id="sp-cm-messenger">';
	$printPage .=     '<div class="sp-msg-list" id="sp-cm-list"><div class="sp-msg-empty">Loading…</div></div>';
	$printPage .=     '<div class="sp-msg-thread" id="sp-cm-thread"></div>';
	$printPage .=   '</div>';
	$printPage .= '</div></section>';
}

// Company Directory Panel — list of employee cards with search + brand/location/position/status
// filters. Add/edit happens in a JS modal; the option lists come from the site filters.
if ( $cap_directory ) {
	$dir_opts = function_exists( 'sp_directory_options' ) ? sp_directory_options() : [ 'positions' => [], 'brands' => [], 'locations' => [] ];
	$dir_select_opts = function ( array $list ) {
		$html = '';
		foreach ( $list as $k => $v ) {
			if ( $k === '---' ) continue; // the visual separator in the option lists isn't a real filter value
			$html .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>';
		}
		return $html;
	};

	$printPage .= '<section class="sp-panel" id="sp-panel-directory"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Directory"><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Directory</h1>'
		.             ( $cap_directory_manage ? '<div class="sp-page-head__actions"><button type="button" class="unique sp-btn sp-btn--primary sp-add-btn" id="sp-dir-add-btn" data-sp-bar="1" data-sp-icon="plus">' . $svg( $icons['plus'] ) . ' Add Employee</button></div>' : '' )
		.             $sp_bar_btn( 2, 'search', 'Search', 'focus:#sp-dir-search' )
		.           '</div></div>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-dir-search" class="sp-input" placeholder="Search name, email or phone" autocomplete="off"></div>';
	$printPage .=     '<div class="sp-toolbar"><button type="button" class="unique sp-filter" data-sp-dir-filters>' . $svg( $icons['sliders'] ) . ' <span data-sp-dir-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button><span class="sp-meta" id="sp-dir-count"></span></div>';
	$printPage .=   '</div>';
	// Brand / location / position / status, shown in the Filter sheet. The dashboard's Directory section
	// copies these option lists and writes back into them.
	$printPage .=   '<div hidden data-sp-dir-filter-holder>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Brand</span><select id="sp-dir-filter-brand" class="sp-select"><option value="">All brands</option>' . $dir_select_opts( $dir_opts['brands'] ) . '</select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Location</span><select id="sp-dir-filter-location" class="sp-select"><option value="">All locations</option>' . $dir_select_opts( $dir_opts['locations'] ) . '</select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Position</span><select id="sp-dir-filter-position" class="sp-select"><option value="">All positions</option>' . $dir_select_opts( $dir_opts['positions'] ) . '</select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Status</span><select id="sp-dir-filter-status" class="sp-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option></select></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack" id="sp-directory-grid"></div>';
	$printPage .= '</div></section>';
}

// Jobsites Panel — list of jobs (thumb, address, live/draft, its /service/ page) with search + status
// filters. Add/edit happens in a JS modal (photos, address w/ "Use my location", description).
if ( $cap_jobsites ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-jobsites"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Jobsites"><div class="sp-page-head__eyebrow">Website</div><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Jobsites</h1>'
		.             ( $cap_jobsites_add ? '<div class="sp-page-head__actions"><button type="button" class="unique sp-btn sp-btn--primary sp-add-btn" id="sp-job-add-btn" data-sp-bar="1" data-sp-icon="plus">' . $svg( $icons['plus'] ) . ' Add Jobsite</button></div>' : '' )
		.             $sp_bar_btn( 2, 'search', 'Search', 'focus:#sp-job-search' )
		.           '</div></div>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span><input type="search" id="sp-job-search" class="sp-input" placeholder="Search customer, address or description" autocomplete="off"></div>';
	$printPage .=     '<div class="sp-tabs" role="tablist">'
		.               '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-job-status="all">All</button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-job-status="publish">Live</button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-job-status="draft">Drafts</button>'
		.             '</div>';
	$printPage .=     '<div class="sp-toolbar"><button type="button" class="unique sp-filter" data-sp-job-filters>' . $svg( $icons['sliders'] ) . ' <span data-sp-job-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button><span class="sp-meta" id="sp-job-count"></span></div>';
	$printPage .=   '</div>';
	// Added by + order, shown in the Filter sheet; the tabs drive the status select.
	$printPage .=   '<div hidden data-sp-job-filter-holder>';
	$printPage .=     '<select id="sp-job-filter-status" class="sp-select"><option value="all">All statuses</option><option value="publish">Live</option><option value="draft">Drafts</option></select>';
	// Filled by JS from the list response: All, then the current user, then everyone else who added jobs.
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Added by</span><select id="sp-job-filter-author" class="sp-select"><option value="0">All users</option></select></label>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field data-sp-summary-skip><span class="sp-field__label">Order</span><select id="sp-job-sort" class="sp-select"><option value="date">Newest first</option><option value="date_asc">Oldest first</option><option value="az">A–Z</option><option value="za">Z–A</option></select></label>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stack" id="sp-job-list"></div>';
	$printPage .=   '<div class="sp-reviews-more" id="sp-job-more"></div>';
	$printPage .= '</div></section>';
}

// Shift Scheduling Panel — the hosted scheduling app (supervisor or member view, as the effective user).
// The sidebar children switch its views; its own header/tab bars are hidden in style-site-pulse.css.
if ( $cap_schedules ) {
	// Standard group breadcrumb; the current-view label (.sp-crumb-current) is swapped by JS per child.
	$printPage .= '<section class="sp-panel" id="sp-panel-schedules">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Shift Scheduling', 'Claim OT' ) . '</div>';
	$printPage .=   sp_schedules_panel_html();
	$printPage .= '</section>';
}

/*--------------------------------------------------------------
# Home Base panels — customer roster, push composer, scheduling inbox
# Each gated by its own module cap; content is filled by JS (spAjax).
--------------------------------------------------------------*/
if ( $cap_cc_customers ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-customer-connect-customers">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Customers</h2></div>';
	$printPage .=   '<div class="sp-dir-searchrow">';
	$printPage .=     '<input type="search" id="sp-cc-cust-search" class="sp-input sp-dir-search" placeholder="Search name, phone, or email…">';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-cc-cust-clear">Clear</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-dir-count" id="sp-cc-cust-count"></div>';
	$printPage .=   '<div id="sp-cc-customers-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}
if ( $cap_cc_send ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-customer-connect-send">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Send Notification</h2></div>';
	$printPage .=   '<div id="sp-cc-send-notice"></div>';
	$printPage .=   '<div class="sp-card sp-cc-send-card"><div class="sp-card-body">';
	$printPage .=     '<div class="sp-form-group"><label for="sp-cc-audience">Send to</label>';
	$printPage .=       '<select id="sp-cc-audience" class="sp-select"><option value="">Loading…</option></select></div>';
	$printPage .=     '<div class="sp-form-group"><label for="sp-cc-title">Title</label>';
	$printPage .=       '<input type="text" id="sp-cc-title" class="sp-input" maxlength="80" placeholder="e.g. Time to change your filter"></div>';
	$printPage .=     '<div class="sp-form-group"><label for="sp-cc-body">Message</label>';
	$printPage .=       '<textarea id="sp-cc-body" class="sp-textarea" rows="3" maxlength="200" placeholder="Optional details…"></textarea></div>';
	$printPage .=     '<div class="sp-form-group"><label for="sp-cc-url">Link (optional)</label>';
	$printPage .=       '<input type="url" id="sp-cc-url" class="sp-input" placeholder="https://…"></div>';
	$printPage .=     '<div class="sp-cc-send-actions">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-cc-send-btn">Send Notification</button>';
	$printPage .=       '<span class="sp-cc-send-result" id="sp-cc-send-result"></span>';
	$printPage .=     '</div>';
	$printPage .=   '</div></div>';
	$printPage .= '</section>';
}
if ( $cap_cc_schedule ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-customer-connect-schedule">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Scheduling Requests</h2></div>';
	$printPage .=   '<div id="sp-cc-schedule-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}


// JS tripwire for the Booking panels.
//
// Every list on these screens is filled in by JavaScript, so when the deployed script is
// stale the panel just sits there spinning and says nothing -- which is indistinguishable
// from a bug in the booking code itself. This notice renders VISIBLE and the main script
// removes it on init, so it only survives when the script did not run. It carries the
// server file date, which answers "did the upload actually land?" without opening a
// console. These panels are hidden until navigated to, so nobody sees it flash.
$sp_book_js_file  = get_template_directory() . '/js/script-site-pulse.min.js';
$sp_book_js_stamp = file_exists( $sp_book_js_file )
	? date_i18n( 'M j, Y g:i a', (int) filemtime( $sp_book_js_file ) )
	: 'file not found on the server';
$sp_book_tripwire  = '<div class="sp-js-tripwire">';
$sp_book_tripwire .=   '<strong>The JavaScript for this screen did not run.</strong> ';
$sp_book_tripwire .=   'Everything below is filled in by <code>script-site-pulse.min.js</code>, which the browser either did not load or loaded from an old cached copy. ';
$sp_book_tripwire .=   'The copy on this server was last written <strong>' . esc_html( $sp_book_js_stamp ) . '</strong>. If that is older than your last upload, the file did not land. ';
$sp_book_tripwire .=   'If the date looks right, hard-refresh with Ctrl+Shift+R and purge the CDN cache.';
$sp_book_tripwire .= '</div>';
/*--------------------------------------------------------------
# Store panels - orders, inventory, and the setup screen.
# Structure only; every list is filled by JS (spAjax) once the store
# endpoints land. Slugs match the nav children above. Reuses the booking
# JS tripwire above, which is about the shared script file, not about booking.
--------------------------------------------------------------*/
// A search box with its icon, shared by the four Store lists.
$sp_store_search = function ( $id, $placeholder ) use ( $svg, $icons ) {
	return '<div class="sp-control sp-control--lead"><span class="sp-control__icon">' . $svg( $icons['search'] ) . '</span>'
		. '<input type="search" id="' . esc_attr( $id ) . '" class="sp-input" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off"></div>';
};
// A screen's one Filter button: "Filter · N", the applied choices under it, and the count.
$sp_store_filter = function ( $key, $count_id ) use ( $svg, $icons ) {
	return '<div class="sp-toolbar"><button type="button" class="unique sp-filter" data-sp-' . $key . '-filters>' . $svg( $icons['sliders'] )
		. ' <span data-sp-' . $key . '-filters-label>Filter</span> ' . $svg( $icons['chevron'] ) . '</button><span class="sp-meta" id="' . esc_attr( $count_id ) . '"></span></div>';
};
if ( $cap_store_orders ) {
	// No "New Order" button yet: entering an order by hand needs a product picker, and a button
	// that does nothing is worse than no button.
	$printPage .= '<section class="sp-panel" id="sp-panel-store-orders"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Orders"><div class="sp-page-head__eyebrow">Store</div><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Orders</h1>'
		.             $sp_bar_btn( 1, 'search', 'Search', 'focus:#sp-store-order-search' )
		.             '<button type="button" class="unique" hidden data-sp-bar="2" data-sp-extra data-sp-icon="truck" id="sp-store-toship-btn">To ship</button>'
		.           '</div></div>';
	$printPage .=   '<div id="sp-store-order-summary"></div>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     $sp_store_search( 'sp-store-order-search', 'Search name, email or order number' );
	// The tabs drive the status select below. Open leads: it's where the work is.
	$sp_so_tabs = [ 'open' => 'Open' ] + sp_store_order_statuses() + [ '' => 'All' ];
	$printPage .=     '<div class="sp-tabs" role="tablist">';
	foreach ( $sp_so_tabs as $sp_so_status => $sp_so_label ) {
		$printPage .=   '<button type="button" class="unique sp-tab' . ( 'open' === $sp_so_status ? ' is-active' : '' ) . '" role="tab" data-sp-store-order-status="' . esc_attr( $sp_so_status ) . '">' . esc_html( $sp_so_label ) . '</button>';
	}
	$printPage .=     '</div>';
	$printPage .=     $sp_store_filter( 'store-order', 'sp-store-order-count' );
	$printPage .=   '</div>';
	$printPage .=   '<div hidden data-sp-store-order-filter-holder>';
	$printPage .=     '<select id="sp-store-order-status" class="sp-select">';
	foreach ( $sp_so_tabs as $sp_so_status => $sp_so_label ) {
		$printPage .=   '<option value="' . esc_attr( $sp_so_status ) . '"' . ( 'open' === $sp_so_status ? ' selected' : '' ) . '>' . esc_html( $sp_so_label ) . '</option>';
	}
	$printPage .=     '</select>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Shipping</span><select id="sp-store-order-fulfillment" class="sp-select"><option value="">Shipped or not</option>';
	foreach ( sp_store_fulfillment_statuses() as $sp_sf_status => $sp_sf_label ) {
		$printPage .=   '<option value="' . esc_attr( $sp_sf_status ) . '">' . esc_html( $sp_sf_label ) . '</option>';
	}
	$printPage .=     '</select></label>';
	$printPage .=   '</div>';
	$printPage .=   $sp_book_tripwire;
	$printPage .=   '<div class="sp-stack" id="sp-store-orders"></div>';
	$printPage .=   '<div id="sp-store-order-more"></div>';
	$printPage .= '</div></section>';
}
if ( $cap_store_orders ) {
	// Customers: everyone who has bought, with members folded in. The Everyone / Members tabs and
	// the Give Membership button are shown by JS only while Memberships is switched on.
	$printPage .= '<section class="sp-panel" id="sp-panel-store-customers"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Customers"><div class="sp-page-head__eyebrow">Store</div><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Customers</h1>'
		.             '<div class="sp-page-head__actions" id="sp-store-customer-actions"></div>'
		.             $sp_bar_btn( 2, 'search', 'Search', 'focus:#sp-store-customer-search' )
		.           '</div></div>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     $sp_store_search( 'sp-store-customer-search', 'Search name or email' );
	$printPage .=     '<div class="sp-tabs" role="tablist" id="sp-store-customer-tabs" hidden>'
		.               '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-store-customer-show="">Everyone</button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-store-customer-show="members">Members</button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-store-customer-show="customers">Not members</button>'
		.             '</div>';
	$printPage .=     '<div class="sp-toolbar"><span class="sp-meta" id="sp-store-customer-count"></span></div>';
	$printPage .=   '</div>';
	$printPage .=   '<select id="sp-store-customer-filter" class="sp-select" hidden><option value="">Everyone</option><option value="members">Members</option><option value="customers">Not members</option></select>';
	$printPage .=   $sp_book_tripwire;
	$printPage .=   '<div class="sp-stack" id="sp-store-customers"></div>';
	$printPage .=   '<div id="sp-store-customer-more"></div>';
	$printPage .= '</div></section>';
}
if ( $cap_store_products ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-store-products"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Products"><div class="sp-page-head__eyebrow">Store</div><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Products</h1>'
		.             '<div class="sp-page-head__actions"><button type="button" class="unique sp-btn sp-btn--primary sp-add-btn" id="sp-store-product-new" data-sp-bar="1" data-sp-icon="plus">' . $svg( $icons['plus'] ) . ' Add Product</button></div>'
		.             $sp_bar_btn( 2, 'search', 'Search', 'focus:#sp-store-product-search' )
		.           '</div></div>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     $sp_store_search( 'sp-store-product-search', 'Search products' );
	$printPage .=     '<div class="sp-tabs" role="tablist">'
		.               '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-store-product-status="">All</button>';
	foreach ( sp_store_product_statuses() as $sp_sp_status => $sp_sp_label ) {
		$printPage .=   '<button type="button" class="unique sp-tab" role="tab" data-sp-store-product-status="' . esc_attr( $sp_sp_status ) . '">' . esc_html( 'draft' === $sp_sp_status ? 'Hidden' : $sp_sp_label ) . '</button>';
	}
	$printPage .=     '</div>';
	$printPage .=     $sp_store_filter( 'store-product', 'sp-store-product-count' );
	$printPage .=   '</div>';
	// Category in the Filter sheet; the tabs drive the status select.
	$printPage .=   '<div hidden data-sp-store-product-filter-holder>';
	$printPage .=     '<select id="sp-store-product-status" class="sp-select"><option value="">Everything</option>';
	foreach ( sp_store_product_statuses() as $sp_sp_status => $sp_sp_label ) {
		$printPage .=   '<option value="' . esc_attr( $sp_sp_status ) . '">' . esc_html( $sp_sp_label ) . '</option>';
	}
	$printPage .=     '</select>';
	$printPage .=     '<label class="sp-field" data-sp-filter-field><span class="sp-field__label">Category</span><select id="sp-store-product-cat" class="sp-select"><option value="0">All categories</option></select></label>';
	$printPage .=   '</div>';
	$printPage .=   $sp_book_tripwire;
	$printPage .=   '<div class="sp-stack" id="sp-store-products"></div>';
	$printPage .=   '<div id="sp-store-product-more"></div>';
	$printPage .= '</div></section>';

	$printPage .= '<section class="sp-panel" id="sp-panel-store-inventory"><div class="sp-page">';
	$printPage .=   '<div class="sp-page-head" data-sp-title="Inventory"><div class="sp-page-head__eyebrow">Store</div><div class="sp-page-head__row">'
		.             '<h1 class="sp-page-head__title">Inventory</h1>'
		.             $sp_bar_btn( 1, 'search', 'Search', 'focus:#sp-store-inventory-search' )
		.           '</div></div>';
	$printPage .=   '<div class="sp-stack">';
	$printPage .=     $sp_store_search( 'sp-store-inventory-search', 'Search product or SKU' );
	$printPage .=     '<div class="sp-tabs" role="tablist">'
		.               '<button type="button" class="unique sp-tab is-active" role="tab" data-sp-store-inventory-show="">All</button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-store-inventory-show="low">Low stock</button>'
		.               '<button type="button" class="unique sp-tab" role="tab" data-sp-store-inventory-show="out">Sold out</button>'
		.             '</div>';
	$printPage .=     '<div class="sp-toolbar"><span class="sp-meta" id="sp-store-inventory-count"></span></div>';
	$printPage .=   '</div>';
	$printPage .=   '<select id="sp-store-inventory-filter" class="sp-select" hidden><option value="">All tracked items</option><option value="low">Low stock</option><option value="out">Out of stock</option></select>';
	$printPage .=   $sp_book_tripwire;
	$printPage .=   '<div class="sp-stack" id="sp-store-inventory"></div>';
	$printPage .= '</div></section>';
}
if ( $cap_store_settings || $cap_store_products ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-store">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Store' ) . '</div>';
	$printPage .=   $sp_book_tripwire;
	$printPage .=   '<div id="sp-store-settings"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

/*--------------------------------------------------------------
# Booking panels - calendar, booking list, passes, and the setup screen.
# Structure only; every list is filled by JS (spAjax) once the booking
# endpoints land. Slugs match the nav children above.
--------------------------------------------------------------*/
if ( $cap_book_view || $cap_book_own ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-booking-calendar">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Booking', 'Calendar' );
	if ( $cap_book_manage ) {
		$printPage .= '<div class="sp-panel-actions"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-book-new">New Booking</button></div>';
	}
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-book-cal-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary sp-btn-tiny" id="sp-book-cal-prev" aria-label="Previous">&lsaquo;</button>';
	$printPage .=       '<span class="sp-toolbar-label" id="sp-book-cal-range"></span>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary sp-btn-tiny" id="sp-book-cal-next" aria-label="Next">&rsaquo;</button>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary sp-btn-tiny" id="sp-book-cal-today">Today</button>';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<span class="sp-toolbar-label">View</span>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary sp-book-cal-view active" data-view="day">Day</button>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary sp-book-cal-view" data-view="week">Week</button>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary sp-book-cal-view" data-view="month">Month</button>';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<span class="sp-toolbar-label">Resource</span>';
	$printPage .=       '<select id="sp-book-cal-resource" class="sp-select"><option value="">All</option></select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   $sp_book_tripwire;
	$printPage .=   '<div id="sp-book-calendar"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';

	$printPage .= '<section class="sp-panel" id="sp-panel-booking-list">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Booking', 'Bookings' ) . '</div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-book-list-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<input type="search" id="sp-book-search" class="sp-input" placeholder="Search name, email, phone, or code&hellip;">';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<span class="sp-toolbar-label">Status</span>';
	$printPage .=       '<select id="sp-book-status" class="sp-select"><option value="upcoming">Upcoming</option>';
	foreach ( sp_book_statuses() as $sp_bk_status => $sp_bk_label ) {
		$printPage .=   '<option value="' . esc_attr( $sp_bk_status ) . '">' . esc_html( $sp_bk_label ) . '</option>';
	}
	$printPage .=       '<option value="">All</option></select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-table-card" id="sp-book-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}
if ( $cap_book_passes ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-booking-passes">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Booking', 'Passes & Memberships' );
	$printPage .=     '<div class="sp-panel-actions"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-book-pass-new">Issue Pass</button></div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-table-card" id="sp-book-passes"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}
if ( $cap_book_services || in_array( 'manage_booking_settings', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-booking">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Booking' ) . '</div>';
	$printPage .=   '<div id="sp-book-admin-notice"></div>';


	$printPage .=   $sp_book_tripwire;

	// Each setup area is a settings card, matching Settings -> Notifications: centred h3,
	// a line of help, then the controls. The action button sits in its own right-aligned row
	// rather than beside the heading, so the heading stays centred like every other card.

	// Services -- what a customer picks and pays for.
	$printPage .=   '<div class="sp-card sp-settings-card" id="sp-book-admin-services">';
	$printPage .=     '<h3>Services</h3>';
	$printPage .=     '<p class="sp-help-text">What a customer books. Each one carries its own length, spacing, travel buffer and notice period.</p>';
	$printPage .=     '<div class="sp-book-card-actions"><button type="button" class="unique sp-btn sp-btn-primary sp-btn-tiny" id="sp-book-add-service">Add Service</button></div>';
	$printPage .=     '<div class="sp-table-card" id="sp-book-services-list"><div class="sp-loading"></div></div>';
	$printPage .=   '</div>';

	// Resources -- whatever can only be in one place at a time.
	$printPage .=   '<div class="sp-card sp-settings-card" id="sp-book-admin-resources">';
	$printPage .=     '<h3>Resources</h3>';
	$printPage .=     '<p class="sp-help-text">The person, room or unit that gets booked &mdash; whatever can only be in one place at a time. Link one to a Site Pulse user and that person can be given a calendar limited to their own bookings.</p>';
	$printPage .=     '<div class="sp-book-card-actions"><button type="button" class="unique sp-btn sp-btn-primary sp-btn-tiny" id="sp-book-add-resource">Add Resource</button></div>';
	$printPage .=     '<div class="sp-table-card" id="sp-book-resources-list"><div class="sp-loading"></div></div>';
	$printPage .=   '</div>';

	// Availability -- the weekly template, plus dated exceptions on top of it.
	$printPage .=   '<div class="sp-card sp-settings-card" id="sp-book-admin-availability">';
	$printPage .=     '<h3>Availability</h3>';
	$printPage .=     '<p class="sp-help-text">The hours each resource normally works. Exceptions below are layered on top for holidays, time off, or a one-off extra day.</p>';
	$printPage .=     '<div class="sp-toolbar"><div class="sp-toolbar-group">';
	$printPage .=       '<span class="sp-toolbar-label">Resource</span>';
	$printPage .=       '<select id="sp-book-avail-resource" class="sp-select"></select>';
	$printPage .=     '</div></div>';
	$printPage .=     '<div id="sp-book-hours"><div class="sp-loading"></div></div>';
	$printPage .=     '<h4 class="sp-book-subhead">Date Exceptions</h4>';
	$printPage .=     '<div class="sp-book-card-actions"><button type="button" class="unique sp-btn sp-btn-secondary sp-btn-tiny" id="sp-book-add-exception">Add Exception</button></div>';
	$printPage .=     '<div id="sp-book-exceptions"></div>';
	$printPage .=   '</div>';

	// Travel time -- the straight-line-to-driving-time conversion. No API; this is the arithmetic
	// the scheduler runs on, and every metro needs its own numbers.
	$printPage .=   '<div class="sp-card sp-settings-card" id="sp-book-admin-travel">';
	$printPage .=     '<h3>Travel Time</h3>';
	$printPage .=     '<p class="sp-help-text">How straight-line distance between two ZIP codes is turned into driving minutes. These only ever <strong>raise</strong> a service&rsquo;s buffer, never lower it &mdash; if the minimum you set is already enough, it stands. Defaults are calibrated for Dallas; tune them for your own area.</p>';
	$printPage .=     '<div id="sp-book-travel-out"><div class="sp-loading"></div></div>';
	$printPage .=     '<div class="sp-book-card-actions sp-book-card-actions-end"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-book-travel-save">Save Travel Settings</button></div>';
	$printPage .=   '</div>';

	// Membership plans -- the products she sells, defined once and reused when issuing.
	$printPage .=   '<div class="sp-card sp-settings-card" id="sp-book-admin-plans">';
	$printPage .=     '<h3>Membership Plans</h3>';
	$printPage .=     '<p class="sp-help-text">The memberships and session packs you sell. Define each one here once, then issue it to a customer under Booking &rarr; Passes &amp; Memberships &mdash; the terms are filled in for you. Editing a plan does not change anyone already on it.</p>';
	$printPage .=     '<div class="sp-book-card-actions"><button type="button" class="unique sp-btn sp-btn-primary sp-btn-tiny" id="sp-book-add-plan">Add Plan</button></div>';
	$printPage .=     '<div class="sp-table-card" id="sp-book-plans-list"><div class="sp-loading"></div></div>';
	$printPage .=   '</div>';

	// Preview -- runs the real availability engine against the rules above.
	$printPage .=   '<div class="sp-card sp-settings-card" id="sp-book-admin-preview">';
	$printPage .=     '<h3>Preview Availability</h3>';
	$printPage .=     '<p class="sp-help-text">Runs the live booking engine against the settings above and shows exactly what a customer would be offered &mdash; plus the open hours each day was built from, so an empty day explains itself.</p>';
	$printPage .=     '<div class="sp-toolbar">';
	$printPage .=       '<div class="sp-toolbar-group"><span class="sp-toolbar-label">Service</span>';
	$printPage .=         '<select id="sp-book-preview-service" class="sp-select"></select></div>';
	$printPage .=       '<div class="sp-toolbar-group"><span class="sp-toolbar-label">Resource</span>';
	$printPage .=         '<select id="sp-book-preview-resource" class="sp-select"><option value="0">Any</option></select></div>';
	$printPage .=       '<div class="sp-toolbar-group"><span class="sp-toolbar-label">From</span>';
	$printPage .=         '<input type="date" id="sp-book-preview-from" class="sp-input"></div>';
	$printPage .=       '<div class="sp-toolbar-group"><span class="sp-toolbar-label">To</span>';
	$printPage .=         '<input type="date" id="sp-book-preview-to" class="sp-input"></div>';
	$printPage .=       '<div class="sp-toolbar-group">';
	$printPage .=         '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-book-preview-run">Preview</button></div>';
	$printPage .=     '</div>';
	$printPage .=     '<div id="sp-book-preview-out"></div>';
	$printPage .=   '</div>';

	// Notifications -- confirmations, reminders, change notices. Queued, never sent inline.
	$printPage .=   '<div class="sp-card sp-settings-card" id="sp-book-admin-notify">';
	$printPage .=     '<h3>Notifications</h3>';
	$printPage .=     '<p class="sp-help-text">Confirmations, reminders and change notices sent to the customer. Nothing sends immediately &mdash; messages are queued and delivered by the background sweep, so a reminder still goes out even if the exact minute is missed.</p>';
	$printPage .=     '<div id="sp-book-notify-out"><div class="sp-loading"></div></div>';
	$printPage .=     '<div class="sp-book-card-actions sp-book-card-actions-end"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-book-notify-save">Save Notifications</button></div>';
	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// Forms Panels — one repository each (dynamic; managed under Settings → Forms), plus an "All"
// panel at the end. Lists are populated by JS (get_forms); upload/replace/delete controls render
// only with 'upload_forms'. Gated on the same area capability as the Forms menu.
if ( $cap_forms_area ) {
	// Destination <option>s for the bulk "Move selected" picker — every repository, plus each of
	// its sub-folders (value "cat::sub"; the JS splits on "::" into category + sub_category).
	$sp_move_opts = '';
	foreach ( site_pulse_form_category_tree() as $sp_mk => $sp_mcat ) {
		if ( empty( $sp_mcat['children'] ) ) {
			$sp_move_opts .= '<option value="' . esc_attr( $sp_mk ) . '">' . esc_html( $sp_mcat['label'] ) . '</option>';
		} else {
			$sp_move_opts .= '<optgroup label="' . esc_attr( $sp_mcat['label'] ) . '">';
			$sp_move_opts .=   '<option value="' . esc_attr( $sp_mk ) . '">' . esc_html( $sp_mcat['label'] ) . ' (top level)</option>';
			foreach ( $sp_mcat['children'] as $sp_sk => $sp_sl ) {
				$sp_move_opts .= '<option value="' . esc_attr( $sp_mk . '::' . $sp_sk ) . '">&nbsp;&nbsp;&rsaquo; ' . esc_html( $sp_sl ) . '</option>';
			}
			$sp_move_opts .= '</optgroup>';
		}
	}
	foreach ( site_pulse_form_category_tree() as $sp_cat => $sp_cat_data ) {
		$sp_form_label = $sp_cat_data['label'];
		$sp_subs       = $sp_cat_data['children'];   // [subKey => subLabel] for this repository
		$printPage .= '<section class="sp-panel sp-forms-panel" id="sp-panel-forms-' . esc_attr( $sp_cat ) . '" data-forms-cat="' . esc_attr( $sp_cat ) . '">';
		$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Forms', $sp_form_label );
		if ( $cap_upload_forms ) {
			$printPage .= '<button type="button" class="unique sp-btn sp-btn-primary sp-forms-upload-btn">+ Upload Form</button>';
		}
		$printPage .=   '</div>';
		// Sub-folder filter chips — only when this repository has sub-folders.
		if ( $sp_subs ) {
			$printPage .= '<div class="sp-forms-subchips" data-forms-subchips>';
			$printPage .=   '<button type="button" class="unique sp-chip sp-forms-subchip active" data-sub="">All</button>';
			foreach ( $sp_subs as $sk => $sl ) $printPage .= '<button type="button" class="unique sp-chip sp-forms-subchip" data-sub="' . esc_attr( $sk ) . '">' . esc_html( $sl ) . '</button>';
			$printPage .= '</div>';
		}

		// Upload / replace form (hidden until "Upload Form" or a row's "Replace" is clicked).
		if ( $cap_upload_forms ) {
			$printPage .= '<div class="sp-forms-upload-wrap" hidden>';
			$printPage .=   '<form class="sp-forms-upload-form">';
			$printPage .=     '<h3 class="sp-forms-upload-title unique">Upload Form</h3>';
			$printPage .=     '<input type="hidden" class="sp-forms-edit-id" value="">';
			$printPage .=     '<div class="sp-form-group"><label>Name</label><input type="text" class="sp-input sp-forms-field-name" maxlength="200" placeholder="e.g. Line Check Sheet" required></div>';
			if ( $sp_subs ) {
				$printPage .= '<div class="sp-form-group"><label>Sub-folder</label><select class="sp-select sp-forms-field-sub"><option value="">— None (top level) —</option>';
				foreach ( $sp_subs as $sk => $sl ) $printPage .= '<option value="' . esc_attr( $sk ) . '">' . esc_html( $sl ) . '</option>';
				$printPage .= '</select></div>';
			}
			$printPage .=     '<div class="sp-form-group"><label class="sp-forms-file-label">File</label><input type="file" class="sp-input sp-forms-field-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.gif,.webp"></div>';
			$printPage .=     '<p class="sp-forms-replace-note unique" hidden>Leave the file empty to keep the current file and only rename/retype.</p>';
			$printPage .=     '<div class="sp-forms-upload-status" hidden></div>';
			$printPage .=     '<div class="sp-forms-upload-buttons"><button type="submit" class="unique sp-btn sp-btn-primary sp-forms-save-btn">Upload</button><button type="button" class="unique sp-btn sp-btn-secondary sp-forms-cancel-btn">Cancel</button></div>';
			$printPage .=   '</form>';
			$printPage .= '</div>';
		}

		// Bulk-actions row (the search box sits on its own row directly above the table, below).
		$printPage .=   '<div class="sp-forms-toolbar">';
		$printPage .=     '<button type="button" class="unique sp-btn sp-btn-secondary sp-forms-download-selected" disabled>Download selected</button>';
		if ( $cap_upload_forms ) {
			$printPage .= '<button type="button" class="unique sp-btn sp-btn-secondary sp-forms-move-selected" disabled>Move selected</button>';
			$printPage .= '<button type="button" class="unique sp-btn sp-btn-secondary sp-forms-delete-selected" disabled>Delete selected</button>';
		}
		$printPage .=   '</div>';
		if ( $cap_upload_forms ) {
			$printPage .= '<div class="sp-forms-move-wrap" hidden>';
			$printPage .=   '<span class="unique sp-forms-move-label">Move selected to:</span>';
			$printPage .=   '<select class="sp-select sp-forms-move-target"><option value="">— Choose repository —</option>' . $sp_move_opts . '</select>';
			$printPage .=   '<button type="button" class="unique sp-btn sp-btn-primary sp-forms-move-go">Move</button>';
			$printPage .=   '<button type="button" class="unique sp-btn sp-btn-secondary sp-forms-move-cancel">Cancel</button>';
			$printPage .= '</div>';
		}
		$printPage .=   '<div class="sp-forms-searchbar"><input type="search" class="sp-input sp-forms-search" placeholder="Search names…" aria-label="Search form names"></div>';
		$printPage .=   '<div class="sp-card sp-table-card">';
		$printPage .=     '<table class="sp-table sp-forms-table">';
		$printPage .=       '<thead class="sp-thead"><tr>';
		$printPage .=         '<th class="sp-forms-check-col"><input type="checkbox" class="sp-forms-check-all" aria-label="Select all forms"></th>';
		$printPage .=         '<th class="sp-forms-sort" data-sort="name">Name</th>';
		$printPage .=         '<th class="sp-forms-sort" data-sort="category_label">Repository</th>';
		$printPage .=         '<th class="sp-forms-sort" data-sort="format">Format</th>';
		$printPage .=         '<th class="sp-forms-sort" data-sort="date">Date</th>';
		$printPage .=         '<th class="sp-forms-actions-col"></th>';
		$printPage .=       '</tr></thead>';
		$printPage .=       '<tbody class="sp-forms-tbody"></tbody>';
		$printPage .=     '</table>';
		$printPage .=     '<div class="sp-forms-empty" hidden><p>No forms here yet.</p></div>';
		$printPage .=   '</div>';
		$printPage .= '</section>';
	}

	// "All" — every repository's forms in one searchable list (read-only; locate-and-open). The
	// big search box filters titles live; a Repository column shows where each form lives.
	$printPage .= '<section class="sp-panel sp-forms-panel sp-forms-all" id="sp-panel-forms-all" data-forms-cat="all">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Forms', 'All' ) . '</div>';
	$printPage .=   '<div class="sp-forms-toolbar sp-forms-toolbar-all">';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-secondary sp-forms-download-selected" disabled>Download selected</button>';
	if ( $cap_upload_forms ) {
		$printPage .= '<button type="button" class="unique sp-btn sp-btn-secondary sp-forms-move-selected" disabled>Move selected</button>';
		$printPage .= '<button type="button" class="unique sp-btn sp-btn-secondary sp-forms-delete-selected" disabled>Delete selected</button>';
	}
	$printPage .=   '</div>';
	if ( $cap_upload_forms ) {
		$printPage .= '<div class="sp-forms-move-wrap" hidden>';
		$printPage .=   '<span class="unique sp-forms-move-label">Move selected to:</span>';
		$printPage .=   '<select class="sp-select sp-forms-move-target"><option value="">— Choose repository —</option>' . $sp_move_opts . '</select>';
		$printPage .=   '<button type="button" class="unique sp-btn sp-btn-primary sp-forms-move-go">Move</button>';
		$printPage .=   '<button type="button" class="unique sp-btn sp-btn-secondary sp-forms-move-cancel">Cancel</button>';
		$printPage .= '</div>';
	}
	$printPage .=   '<div class="sp-forms-searchbar"><input type="search" class="sp-input sp-forms-search sp-forms-filter-all" placeholder="Search all forms by name…" aria-label="Search all forms"></div>';
	$printPage .=   '<div class="sp-card sp-table-card">';
	$printPage .=     '<table class="sp-table sp-forms-table sp-forms-table-all">';
	$printPage .=       '<thead class="sp-thead"><tr>';
	$printPage .=         '<th class="sp-forms-check-col"><input type="checkbox" class="sp-forms-check-all" aria-label="Select all forms"></th>';
	$printPage .=         '<th class="sp-forms-sort" data-sort="name">Name</th>';
	$printPage .=         '<th class="sp-forms-sort" data-sort="category_label">Repository</th>';
	$printPage .=         '<th class="sp-forms-sort" data-sort="format">Format</th>';
	$printPage .=         '<th class="sp-forms-sort" data-sort="date">Date</th>';
	$printPage .=         '<th class="sp-forms-actions-col"></th>';
	$printPage .=       '</tr></thead>';
	$printPage .=       '<tbody class="sp-forms-tbody"></tbody>';
	$printPage .=     '</table>';
	$printPage .=     '<div class="sp-forms-empty" hidden><p>No forms here yet.</p></div>';
	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// Settings → Forms — manage the repositories (add / rename / delete). Content built by JS.
if ( in_array( 'manage_form_repositories', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-forms">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Forms' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-forms-content"></div>';
	$printPage .= '</section>';
}

// Admin Panels
if ( in_array( 'manage_users', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-users">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Users' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-users-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_roles', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-tiers">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Roles' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-tiers-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_locations', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-locations">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Home Bases' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-locations-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_templates', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-templates">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Reports' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-templates-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_settings', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-settings">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Site Defaults' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-settings-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_settings', $caps ) && ( $cap_cc_customers || $cap_cc_send || $cap_cc_schedule ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-customer-connect">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Customer Connect' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-customer-connect-content"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_ai_prompts', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-ai-prompts">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'AI Prompts' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-ai-prompts-content"></div>';
	// AI Assistant name + roles live in this same tab (below the review-reply prompts).
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-ai-roles-content"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_notifications', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-notifications">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Notifications' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-notifications-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_api_keys', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-apikeys">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'API Keys' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-apikeys-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'view_activity_log', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-activity">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Activity Log' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-activity-content"></div>';
	$printPage .= '</section>';
}
if ( $is_superadmin ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-modules">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Modules' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-modules-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-mileage">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Settings', 'Expense Reports' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-mileage-content"></div>';
	$printPage .= '</section>';
}

$printPage .= '</main>';
// Bottom bar: shortcuts for the current screen, Ask, and More. Filled by spRenderBar() per screen.
$printPage .= '<nav class="sp-bar" id="sp-bar" aria-label="Shortcuts"><div class="sp-bar__inner" id="sp-bar-inner"></div></nav>';
$printPage .= '</div>';

return $printPage;
