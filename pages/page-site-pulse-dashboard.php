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
} elseif ( $is_wp_admin && ! $role ) {
	$caps = [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'manage_notifications', 'manage_api_keys', 'view_ai_insights', 'use_ai_assistant', 'view_forms', 'upload_forms', 'submit_reports', 'view_chat', 'view_action_items', 'view_notes', 'view_gm_action_items', 'view_supervisor_action_items', 'manage_mileage', 'submit_mileage', 'view_tolls', 'view_vehicle_expenses', 'view_business_meals', 'view_competitive_shopping', 'view_other_expenses', 'view_travel_expenses', 'view_expense_overview', 'approve_expense_reports', 'view_approved_expense_reports' ];
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
$header_logo  = site_pulse_get_setting( 'header_logo_url', '' );
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
		[ 'slug' => 'review-trends',  'label' => 'Trends',         'show' => $cap_reviews_area && ! $is_review_hub ],
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

// Store — the staff side of the store. Products are edited in WordPress (the bp_product post
// type) so they keep the SEO module, the media library and the block editor; what lives here is
// what WordPress is bad at — orders, fulfillment, and a stock number that has to be right.
// Store settings (Stripe, tax, shipping rates) live under Settings, not here.
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

$nav[] = [ 'slug' => 'analytics', 'label' => 'Analytics', 'icon' => 'chart', 'show' => in_array( 'view_analytics', $caps ), 'modules' => [ 'ai' ] ];

// AI Assistant — company-voiced AI chat. Gated by the use_ai_assistant capability (AI module).
// Label is the company-set assistant name (default "AI Assistant"; e.g. "bAIbes").
$sp_ai_name = function_exists( 'site_pulse_ai_assistant_name' ) ? site_pulse_ai_assistant_name() : 'AI Assistant';
$nav[] = [ 'slug' => 'ai-assistant', 'label' => $sp_ai_name, 'icon' => 'chat', 'show' => in_array( 'use_ai_assistant', $caps ), 'modules' => [ 'ai' ] ];

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
	'show'  => in_array( 'manage_locations', $caps ) || in_array( 'manage_users', $caps ) || in_array( 'manage_settings', $caps ) || in_array( 'manage_mileage', $caps ) || in_array( 'manage_templates', $caps ) || in_array( 'manage_reviews', $caps ) || in_array( 'manage_notifications', $caps ) || in_array( 'manage_api_keys', $caps ) || $cap_book_services || in_array( 'manage_booking_settings', $caps ) || $cap_store_settings || $cap_store_products,
	'children' => [
		[ 'slug' => 'admin-users',     'label' => 'Users',            'icon' => 'users',   'show' => in_array( 'manage_users', $caps ) ],
		[ 'slug' => 'admin-tiers',     'label' => 'Roles',            'icon' => 'layers',  'show' => in_array( 'manage_roles', $caps ) || in_array( 'manage_settings', $caps ) ],
		[ 'slug' => 'admin-locations', 'label' => 'Home Bases',       'icon' => 'mappin',  'show' => in_array( 'manage_locations', $caps ) ],
		[ 'slug' => 'admin-templates', 'label' => 'Reports',        'icon' => 'file',    'show' => in_array( 'manage_templates', $caps ) ],
		[ 'slug' => 'review-settings', 'label' => 'Reviews',        'icon' => 'star',    'show' => in_array( 'manage_reviews', $caps ) && ! $is_review_hub ],
		[ 'slug' => 'admin-ai-prompts','label' => 'AI Prompts',     'icon' => 'chat',    'show' => in_array( 'manage_settings', $caps ) ],
		[ 'slug' => 'admin-mileage',   'label' => 'Expense Reports', 'icon' => 'car',     'show' => in_array( 'manage_mileage', $caps ) ],
		[ 'slug' => 'admin-forms',     'label' => 'Forms',          'icon' => 'forms',   'show' => in_array( 'manage_settings', $caps ) ],
		[ 'slug' => 'admin-customer-connect', 'label' => 'Customer Connect', 'icon' => 'home', 'show' => in_array( 'manage_settings', $caps ) && ( $cap_cc_customers || $cap_cc_send || $cap_cc_schedule ) ],
		[ 'slug' => 'admin-booking',   'label' => 'Booking',        'icon' => 'calendar', 'show' => $cap_book_services || in_array( 'manage_booking_settings', $caps ) ],
		[ 'slug' => 'admin-store',     'label' => 'Store',          'icon' => 'store',    'show' => $cap_store_settings || $cap_store_products ],
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
$printPage .= '<div id="sp-app">';

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
	$nav_live = $nav_settings = [];
	foreach ( $nav as $sp_nav_item ) {
		if ( $sp_nav_item['slug'] === 'admin' ) { $nav_settings[] = $sp_nav_item; continue; }

		// An item is inactive only if it is owned by module(s) and NONE of them is on. Untagged
		// items (Dashboard, Chat, Action Items) are core — they belong to no module and always stay put.
		$sp_mods = $sp_nav_item['modules'] ?? [];
		$sp_off  = ! empty( $sp_mods );
		foreach ( $sp_mods as $sp_mod ) {
			if ( site_pulse_module_on( $sp_mod ) ) { $sp_off = false; break; }
		}

		if ( $sp_off ) $nav_inactive[] = $sp_nav_item;
		else           $nav_live[]     = $sp_nav_item;
	}
	$nav = array_merge( $nav_live, $nav_settings );
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
		$sp_inactive_shown++;
	}

	if ( $sp_inactive_shown ) {
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
$sp_crumb = function ( $parent, $current ) {
	return '<div class="sp-crumb"><div class="sp-crumb-parent">' . esc_html( $parent )
		. '</div><h2 class="sp-crumb-current">' . esc_html( $current ) . '</h2></div>';
};

// Dashboard Panel
$printPage .= '<section class="sp-panel active" id="sp-panel-dashboard">';
$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Dashboard</h2></div>';

// "Install the app" reminder card — filled + shown by JS only when NOT running as the installed app
// and the user hasn't installed it yet. (Separate from the floating install banner, which stays.)
$printPage .=   '<div class="sp-card sp-install-card" id="sp-install-card" hidden></div>';

// Important-message banners — notifications flagged "Dashboard" in the Notifications matrix, shown
// at the top until the user X's them. Styled like the In-the-News bar.
$sp_dash_msgs = site_pulse_get_dashboard_messages( site_pulse_effective_user_id() );
if ( $sp_dash_msgs ) {
	$printPage .= '<div class="sp-dash-messages" id="sp-dash-messages">';
	foreach ( $sp_dash_msgs as $sp_m ) {
		$printPage .= '<div class="sp-dash-message" data-id="' . (int) $sp_m['id'] . '">';
		$printPage .=   '<span class="sp-dash-message-text">' . esc_html( $sp_m['message'] ) . '</span>';
		$printPage .=   '<button type="button" class="unique sp-dash-message-close" data-id="' . (int) $sp_m['id'] . '" aria-label="Acknowledge and dismiss">&times;</button>';
		$printPage .= '</div>';
	}
	$printPage .= '</div>';
}

// ---- Dashboard widgets — user-customizable: drag to reorder, remove, add back via the picker. The
// per-user layout (order + hidden) lives in user meta; new widgets we add later appear automatically.
$sp_dash_uid = site_pulse_effective_user_id();
$sp_layout   = json_decode( (string) get_user_meta( $sp_dash_uid, '_sp_dashboard_layout', true ), true );
$sp_l_order  = ( is_array( $sp_layout ) && ! empty( $sp_layout['order'] ) )  ? array_map( 'strval', (array) $sp_layout['order'] )  : [];
$sp_l_hidden = ( is_array( $sp_layout ) && ! empty( $sp_layout['hidden'] ) ) ? array_map( 'strval', (array) $sp_layout['hidden'] ) : [];

// Shared widget header: drag handle + title + optional "View All" + remove button.
$sp_widget_head = function ( $title, $link_btn, $id, $icon = '' ) use ( $svg, $icons ) {
	$icon_html = ( $icon && isset( $icons[ $icon ] ) ) ? $svg( $icons[ $icon ] ) : '';
	return '<div class="sp-card-header sp-widget-header">'
		. '<span class="sp-card-drag sp-widget-drag" title="Drag to reorder" aria-label="Drag to reorder">&#9776;</span>'
		. '<h3>' . $icon_html . esc_html( $title ) . '</h3>'
		. '<div class="sp-widget-head-actions">'
		. $link_btn
		. '<button type="button" class="unique sp-widget-remove" data-widget="' . esc_attr( $id ) . '" title="Remove from dashboard" aria-label="Remove from dashboard">&times;</button>'
		. '</div></div>';
};

$sp_widgets = []; // id => [ title, full?, default_hidden?, html ] in default order. Each widget's gate
                  // mirrors its nav tab, so a user can never add a widget for an area they can't access.

// News Feed Widget (full width) — Google Alerts feed; only when switched on + keywords configured.
$sp_alert_kw = trim( (string) site_pulse_get_setting( 'dashboard_alert_keywords', '' ) );
$sp_news_on  = (string) site_pulse_get_setting( 'dashboard_news_enabled', '1' ) !== '0';
if ( $sp_news_on && $sp_alert_kw !== '' ) {
	if ( shortcode_exists( 'get-google-alerts' ) ) {
		$sp_alert_kw_safe = str_replace( [ '"', ']' ], '', $sp_alert_kw );
		$sp_alert_body = do_shortcode( '[get-google-alerts keywords="' . $sp_alert_kw_safe . '" max="8"]' );
	} else {
		$sp_alert_body = '<p class="sp-widget-empty">The “get-google-alerts” feature isn’t available on this site yet.</p>';
	}
	$sp_widgets['alerts'] = [
		'title' => 'In the News',
		'full'  => true,
		'html'  => $sp_widget_head( 'In the News', '', 'alerts', 'chart' )
			. '<div class="sp-card-body sp-widget-body sp-alert-feed-body">' . $sp_alert_body . '</div>',
	];
}

// Chat — 5 most recent threads + unread badges. Always (matches the Chat tab, shown to everyone).
$sp_widgets['chat'] = [
	'title' => 'Chat',
	'html'  => $sp_widget_head( 'Chat', '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="messages">View All &rarr;</button>', 'chat', 'chat' )
		. '<div class="sp-card-body sp-widget-body" id="sp-widget-chat-body"><div class="sp-loading"></div></div>',
];

// My Action Items — the viewer's own OPEN items. Always (matches the Action Items tab, shown to everyone).
$sp_widgets['actions'] = [
	'title' => 'My Action Items',
	'html'  => $sp_widget_head( 'My Action Items', '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="action-items">View All &rarr;</button>', 'actions', 'tasks' )
		. '<div class="sp-card-body sp-widget-body" id="sp-widget-actions-body"><div class="sp-loading"></div></div>',
];

// My Recent Reports — the viewer's own reports. Needs reports access; off by default for anyone who
// can't submit reports (they have no own reports), but still addable from the picker.
if ( $cap_reports_access ) {
	$sp_widgets['reports'] = [
		'title'          => 'My Recent Reports',
		'default_hidden' => ! $sp_can_submit_any,
		'html'           => $sp_widget_head( 'My Recent Reports', '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="reports-my">View All &rarr;</button>', 'reports', 'file' )
			. '<div class="sp-card-body sp-widget-body" id="sp-widget-reports-body"><div class="sp-loading"></div></div>',
	];
}

// Past-Due Reports — GMs/Supervisors overdue on their reports (last submission older than their
// frequency, or never submitted). Visibility is set in Settings → Notifications ("Reports past due"
// row, role-group columns). Default-hidden so it starts in the Add-widget picker.
if ( site_pulse_user_sees_pastdue_widget( site_pulse_effective_user_id() ) ) {
	$sp_widgets['pastdue'] = [
		'title'          => 'Past-Due Reports',
		'default_hidden' => true,
		'html'           => $sp_widget_head( 'Past-Due Reports', '', 'pastdue', 'clipboard' )
			. '<div class="sp-card-body sp-widget-body" id="sp-widget-pastdue-body"><div class="sp-loading"></div></div>',
	];
}

// My Mileage This Month — needs mileage access. (Expense-report rebuild with per-category lines pending.)
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$sp_widgets['mileage'] = [
		'title' => 'My Mileage This Month',
		'html'  => $sp_widget_head( 'My Mileage This Month', '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="mileage">View All &rarr;</button>', 'mileage', 'car' )
			. '<div class="sp-card-body sp-widget-body" id="sp-widget-mileage-body"><div class="sp-loading"></div></div>',
	];
}

// Customer Feedback — a graphical per-channel summary (counts) for every feedback area the user can
// access; each tile opens that panel. Off by default; available whenever the user has ANY feedback area.
if ( $cap_reviews_area || $cap_surveys_area || $cap_cm_area || $cap_emails_area ) {
	$sp_widgets['feedback'] = [
		'title'          => 'Customer Feedback',
		'default_hidden' => true,
		'html'           => $sp_widget_head( 'Customer Feedback', '', 'feedback', 'star' )
			. '<div class="sp-card-body sp-widget-body" id="sp-widget-feedback-body"><div class="sp-loading"></div></div>',
	];
}

// Company Directory — the directory's own filters + search; using any of them opens the filtered
// directory panel. Needs directory access. Off by default; addable from the picker.
if ( $cap_directory ) {
	$sp_widgets['directory'] = [
		'title'          => 'Company Directory',
		'default_hidden' => true,
		'html'           => $sp_widget_head( 'Company Directory', '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="directory">Open &rarr;</button>', 'directory', 'users' )
			. '<div class="sp-card-body sp-widget-body">'
			.   '<div class="sp-widgetdir-filters">'
			.     '<select id="sp-dashw-dir-brand" class="sp-select"><option value="">All brands</option></select>'
			.     '<select id="sp-dashw-dir-location" class="sp-select"><option value="">All locations</option></select>'
			.     '<select id="sp-dashw-dir-position" class="sp-select"><option value="">All positions</option></select>'
			.     '<select id="sp-dashw-dir-status" class="sp-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option></select>'
			.   '</div>'
			.   '<div class="sp-widgetdir-searchrow">'
			.     '<input type="search" id="sp-dashw-dir-search" class="sp-input sp-widgetdir-search" placeholder="Search name, email, or phone…">'
			.     '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-dashw-dir-clear">Clear</button>'
			.   '</div>'
			. '</div>',
	];
}

// Display order: saved order first (available widgets only), then any new ones appended.
$sp_order = [];
foreach ( $sp_l_order as $id ) if ( isset( $sp_widgets[ $id ] ) && ! in_array( $id, $sp_order, true ) ) $sp_order[] = $id;
foreach ( array_keys( $sp_widgets ) as $id ) if ( ! in_array( $id, $sp_order, true ) ) $sp_order[] = $id;

$sp_titles = [];
foreach ( $sp_widgets as $id => $w ) $sp_titles[ $id ] = $w['title'];

// "Add widget" toolbar — JS fills the dropdown with whatever is currently hidden (and hides the bar when nothing to add).
$printPage .= '<div class="sp-dashboard-toolbar" id="sp-dashboard-toolbar" style="display:none">';
$printPage .=   '<select id="sp-dash-add" class="sp-select" aria-label="Add a widget"><option value="">+ Add widget…</option></select>';
$printPage .= '</div>';

$printPage .= '<div class="sp-dashboard-widgets" id="sp-dashboard-widgets" data-widgets="' . esc_attr( wp_json_encode( $sp_titles ) ) . '">';
foreach ( $sp_order as $id ) {
	$w         = $sp_widgets[ $id ];
	$is_full   = ! empty( $w['full'] );
	// Hidden if the user hid it, OR it's default-hidden and they've never placed it (so default-off
	// widgets — and future ones — start in the "Add" picker rather than on the dashboard).
	$is_hidden = in_array( $id, $sp_l_hidden, true )
		|| ( ! empty( $w['default_hidden'] ) && ! in_array( $id, $sp_l_order, true ) );
	$printPage .= '<div class="sp-card sp-widget' . ( $is_full ? ' sp-widget-full' : '' ) . '" id="sp-widget-' . esc_attr( $id ) . '" data-widget="' . esc_attr( $id ) . '"' . ( $is_hidden ? ' hidden' : '' ) . '>';
	$printPage .=   $w['html'];
	$printPage .= '</div>';
}
$printPage .= '</div>';
$printPage .= '</section>';

// Messages Panel — direct messages between users. Two-pane: conversation list + active thread.
if ( in_array( 'view_chat', $caps, true ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-messages">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Chat</h2>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-msg-new-btn">+ New Message</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-messenger" id="sp-messenger">';
	$printPage .=     '<div class="sp-card sp-msg-list" id="sp-msg-list"></div>';
	$printPage .=     '<div class="sp-card sp-msg-thread" id="sp-msg-thread"></div>';
	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// My Reports Panel
if ( $sp_can_submit_any ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-my">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     $sp_crumb( 'Reports', 'My Reports' );
	$printPage .= '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-new-report-btn">+ New Report</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-report-filters">';
	$printPage .=     '<select id="sp-filter-template" class="sp-select"><option value="">All Report Types</option></select>';
	$printPage .=     '<div class="sp-date-range"><input type="date" id="sp-filter-date-start" class="sp-input" placeholder="From">';
	$printPage .=     '<input type="date" id="sp-filter-date-end" class="sp-input" placeholder="To"></div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reports-list" id="sp-reports-list"></div>';
	$printPage .=   '<div class="sp-card sp-report-form-wrap" id="sp-report-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-card sp-report-detail-wrap" id="sp-report-detail-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Review Reports Panel
if ( $cap_view_other_reports ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-review">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><div class="sp-crumb"><div class="sp-crumb-parent">Reports</div><h2 class="sp-crumb-current" id="sp-review-title">GM Reports</h2></div></div>';
	$printPage .=   '<div class="sp-report-filters">';
	$printPage .= '<select id="sp-review-filter-location" class="sp-select"><option value="">All Locations</option></select>';
	$printPage .=     '<div class="sp-date-range"><input type="date" id="sp-review-filter-start" class="sp-input" placeholder="From">';
	$printPage .=     '<input type="date" id="sp-review-filter-end" class="sp-input" placeholder="To"></div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reports-list" id="sp-review-list"></div>';
	$printPage .=   '<div class="sp-card sp-report-detail-wrap" id="sp-review-detail-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Action Items Panel — gated by view_action_items (granted to everyone by default).
if ( in_array( 'view_action_items', $caps, true ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-action-items">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Action Items</h2>';
	$printPage .=     '<div class="sp-panel-header-actions">';
	// Summarize needs the Claude key — hide it rather than offer a button that can only fail.
	if ( site_pulse_get_api_key() ) {
		$printPage .=   '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-summarize-actions-btn">Summarize</button>';
	}
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-action-item-btn">+ Add Action Item</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-report-filters">';
	$printPage .=     '<input type="search" id="sp-action-search" class="sp-input sp-action-search" placeholder="Search items &amp; notes…" autocomplete="off">';
	if ( $cap_view_other_actions ) {
		$printPage .= '<select id="sp-action-filter-location" class="sp-select"><option value="mine">My Action Items</option></select>';
	}
	$printPage .=     '<select id="sp-action-filter-status" class="sp-select">';
	$printPage .=       '<option value="open">Open</option>';
	$printPage .=       '<option value="">All</option>';
	$printPage .=       '<option value="resolved">Completed</option>';
	$printPage .=     '</select>';
	$printPage .=     '<select id="sp-action-filter-category" class="sp-select"><option value="">All labels</option></select>';
	// Due-date filter — "Due" is anything still dated ahead, "Past Due" anything already overdue.
	$printPage .=     '<select id="sp-action-filter-due" class="sp-select">';
	$printPage .=       '<option value="">Any due date</option>';
	$printPage .=       '<option value="due">Due</option>';
	$printPage .=       '<option value="overdue">Past Due</option>';
	$printPage .=     '</select>';
	$printPage .=     '<select id="sp-action-sort" class="sp-select">';
	$printPage .=       '<option value="importance">Order by Importance</option>';
	$printPage .=       '<option value="duedate">Order by Due Date</option>';
	$printPage .=       '<option value="custom">Order by Custom</option>';
	$printPage .=     '</select>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-action-items-list" id="sp-action-items-list"></div>';
	$printPage .= '</section>';
}

// Notes Panel — ongoing notepads. The list, filters and the note editor are all built client-side
// (initNotes / loadNotes in script-site-pulse.js); the cards reuse the Action Items card styling.
if ( in_array( 'view_notes', $caps, true ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-notes">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Notes</h2>';
	$printPage .=     '<div class="sp-panel-header-actions">';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-note-btn">+ New Note</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-report-filters">';
	$printPage .=     '<input type="search" id="sp-notes-search" class="sp-input sp-action-search" placeholder="Search notes &amp; log entries…" autocomplete="off">';
	$printPage .=     '<select id="sp-notes-filter-show" class="sp-select">';
	$printPage .=       '<option value="">All notes</option>';
	$printPage .=       '<option value="personal">Just me</option>';
	$printPage .=       '<option value="shared">Shared</option>';
	$printPage .=     '</select>';
	$printPage .=     '<select id="sp-notes-filter-label" class="sp-select"><option value="">All labels</option></select>';
	$printPage .=     '<select id="sp-notes-sort" class="sp-select">';
	$printPage .=       '<option value="edited">Order by Last Edited</option>';
	$printPage .=       '<option value="created">Order by Newest</option>';
	$printPage .=       '<option value="title">Order by Title</option>';
	$printPage .=       '<option value="custom">Order by Custom</option>';
	$printPage .=     '</select>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-action-items-list sp-notes-list" id="sp-notes-list"></div>';
	$printPage .= '</section>';
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

// Analytics Panel
if ( in_array( 'view_analytics', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-analytics">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header"><h2>Analytics</h2></div>';

	// Report AI search — grantable via the view_ai_insights capability (god always passes). Users
	// without it still see the "Coming Soon" placeholder.
	if ( $eff_is_god || in_array( 'view_ai_insights', $caps, true ) ) {
		$printPage .=   '<div class="sp-reports-ai" id="sp-reports-ai">';
		$printPage .=     '<div class="sp-reports-ai-bar">';
		$printPage .=       '<input type="search" id="sp-reports-ai-q" class="sp-input sp-reports-ai-q" placeholder="Search by keyword, or ask a question…">';
		$printPage .=       '<select id="sp-reports-ai-type" class="sp-select"><option value="">All reports</option><option value="manager">GM</option><option value="supervisor">Supervisor</option></select>';
		$printPage .=       '<select id="sp-reports-ai-location" class="sp-select"><option value="">All locations</option></select>';
		$printPage .=       '<select id="sp-reports-ai-range" class="sp-select"><option value="">All time</option><option value="90">Last 90 days</option><option value="180">Last 6 months</option><option value="365">Last 12 months</option><option value="730">Last 2 years</option></select>';
		$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-reports-ai-search-btn">Search</button>';
		$printPage .=       '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-reports-ai-ask-btn">Ask AI</button>';
		$printPage .=     '</div>';
		$printPage .=     '<div class="sp-reports-ai-tools">';
		$printPage .=       '<span class="sp-reports-aiprep-status" id="sp-reports-digest-status"></span>';
		$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-reports-digest-btn">Index reports for AI</button>';
		$printPage .=     '</div>';
		$printPage .=     '<div class="sp-reports-ai-results" id="sp-reports-ai-results"></div>';
		$printPage .=   '</div>';
	} else {
		$printPage .=   '<div class="sp-analytics-search sp-coming-soon">';
		$printPage .=     '<div class="sp-coming-soon-badge"><span class="unique">Coming Soon</span></div>';
		$printPage .=     '<div class="sp-analytics-search-inner">';
		$printPage .=       '<input type="text" class="sp-input" placeholder="Ask a question about your reports..." disabled>';
		$printPage .=       '<button type="button" class="unique sp-btn sp-btn-primary" disabled>Submit</button>';
		$printPage .=     '</div>';
		$printPage .=     '<p class="sp-coming-soon-text">Natural language search across all reports</p>';
		$printPage .=   '</div>';
	}

	// Analytics Filters
	if ( $cap_view_other_reports ) {
		$printPage .= '<div class="sp-report-filters" style="margin-bottom:20px;">';
		$printPage .=   '<select id="sp-analytics-filter-location" class="sp-select"><option value="">All Locations</option></select>';
		$printPage .= '</div>';
	}

	// Analytics Dashboard
	$printPage .=   '<div class="sp-analytics-grid">';

	// Action Items by Priority
	$printPage .=     '<div class="sp-card sp-analytics-card" id="sp-analytics-priority">';
	$printPage .=       '<h4>Action Items by Priority</h4>';
	$printPage .=       '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
	$printPage .=     '</div>';

	// Action Items by Category
	$printPage .=     '<div class="sp-card sp-analytics-card" id="sp-analytics-category">';
	$printPage .=       '<h4>Action Items by Category</h4>';
	$printPage .=       '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
	$printPage .=     '</div>';

	// Reports by Location
	$printPage .=     '<div class="sp-card sp-analytics-card" id="sp-analytics-locations">';
	$printPage .=       '<h4>Reports by Location</h4>';
	$printPage .=       '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
	$printPage .=     '</div>';

	// Resolution Rate
	$printPage .=     '<div class="sp-card sp-analytics-card" id="sp-analytics-resolution">';
	$printPage .=       '<h4>Resolution Rate</h4>';
	$printPage .=       '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
	$printPage .=     '</div>';

	// Survey Results by Location (only for users who can view surveys). Stores down the side,
	// each with a 1-5 distribution bar chart; the dropdown toggles which question is shown.
	if ( in_array( 'view_surveys', $caps ) ) {
		$printPage .=   '<div class="sp-card sp-analytics-card" id="sp-analytics-surveys">';
		$printPage .=     '<div class="sp-card-header sp-header sp-analytics-card-head">';
		$printPage .=       '<h4>Comment Card Results by Location</h4>';
		$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-analytics-survey-link">View Comment Cards</button>';
		$printPage .=     '</div>';
		$printPage .=     '<div class="sp-analytics-card-toolbar"><select id="sp-analytics-survey-dim" class="sp-select"></select></div>';
		$printPage .=     '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
		$printPage .=   '</div>';
	}

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
	$printPage .= '<section class="sp-panel" id="sp-panel-expense-report">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Expense Report','Overview' ) . '</div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-rep-intro">Everything from every section, totalled for the period — the same layout as your printed expense report. Use the period filter to pick a pay period, then export to PDF.</div></div>';
	$printPage .=   '<div class="sp-period-toolbar-wrap" id="sp-rep-toolbar-wrap"></div>';
	$printPage .=   '<div class="sp-rep-content" id="sp-rep-content"></div>';
	$printPage .= '</section>';
}

// Mileage Panel
if ( $cap_x_mileage ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-mileage">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expense Report','Mileage' );
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-mileage-summary" id="sp-mileage-summary"></div>';
	$printPage .=   '<div class="sp-toolbar sp-period-toolbar" id="sp-mileage-toolbar">';
	$printPage .=     '<div class="sp-mileage-period-controls">';
	$printPage .=       '<div class="sp-toolbar-group sp-mileage-quickranges">';
	$printPage .=         '<button type="button" class="unique sp-btn sp-btn-secondary sp-mileage-range" data-range="period" id="sp-mileage-range-period">This Period</button>';
	$printPage .=         '<button type="button" class="unique sp-btn sp-btn-secondary sp-mileage-range" data-range="ytd">Year to Date</button>';
	$printPage .=         '<button type="button" class="unique sp-btn sp-btn-secondary sp-mileage-range" data-range="last30">Last 30 Days</button>';
	$printPage .=         '<button type="button" class="unique sp-btn sp-btn-secondary sp-mileage-range" data-range="last90">Last 90 Days</button>';
	$printPage .=       '</div>';
	$printPage .=       '<div class="sp-toolbar-group">';
	$printPage .=         '<select id="sp-mileage-month-picker" class="sp-select sp-mileage-month-picker"></select>';
	$printPage .=         '<input type="date" id="sp-mileage-filter-start" class="sp-input" placeholder="From">';
	$printPage .=         '<input type="date" id="sp-mileage-filter-end" class="sp-input" placeholder="To">';
	$printPage .=         '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-filter-clear">Clear</button>';
	$printPage .=       '</div>';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-mileage-actions sp-toolbar-actions">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-pdf-btn">' . $svg( $icons['file'] ) . 'PDF</button>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-map-btn">' . $svg( $icons['mappin'] ) . 'Map</button>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-mileage-add-btn">+ Add a Day</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-mileage-intro">Log each day\'s business trips — your route &amp; miles are calculated for you. These feed Section A of your expense report.</div></div>';
	$printPage .=   '<div class="sp-mileage-list" id="sp-mileage-list"></div>';
	$printPage .=   '<div class="sp-card sp-mileage-form-wrap" id="sp-mileage-form-wrap" hidden></div>';
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

// Vehicle Expenses Panel (Section B of the expense report). Data loads lazily on activation.
if ( $cap_x_vehicle ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-vehicle-expenses">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expense Report','Vehicle Expenses' );
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-vexp-add-btn">+ Add Expense</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-vexp-summary" id="sp-vexp-summary"></div>';
	$printPage .=   '<div class="sp-period-toolbar-wrap" id="sp-vexp-toolbar-wrap"></div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-vexp-intro">Company-driven fuel, washes, parking and repairs, plus personal tolls &amp; trailer costs. These feed Section B of your expense report.</div></div>';
	$printPage .=   '<div class="sp-vexp-form-wrap" id="sp-vexp-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-vexp-content" id="sp-vexp-content"></div>';
	$printPage .= '</section>';
}

// Business Meals Panel (Section C of the expense report). Data loads lazily on activation.
if ( $cap_x_meals ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-business-meals">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expense Report','Business Meals' );
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-meal-add-btn">+ Add Meal</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-meal-summary" id="sp-meal-summary"></div>';
	$printPage .=   '<div class="sp-period-toolbar-wrap" id="sp-meal-toolbar-wrap"></div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-meal-intro">Business meals, entertainment and meeting expenses. Note where you were, the business purpose, and who attended. These feed Section C of your expense report.</div></div>';
	$printPage .=   '<div class="sp-meal-form-wrap" id="sp-meal-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-meal-content" id="sp-meal-content"></div>';
	$printPage .= '</section>';
}

// Competitive Shopping Panel (Section D of the expense report). Data loads lazily on activation.
if ( $cap_x_shop ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-competitive-shopping">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expense Report','Competitive Shopping' );
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-shop-add-btn">+ Add Visit</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-shop-summary" id="sp-shop-summary"></div>';
	$printPage .=   '<div class="sp-period-toolbar-wrap" id="sp-shop-toolbar-wrap"></div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-shop-intro">Competitive shopping visits — meals bought at other restaurants for research. Note where you went, the business purpose, and the store you shopped for. These feed Section D of your expense report.</div></div>';
	$printPage .=   '<div class="sp-shop-form-wrap" id="sp-shop-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-shop-content" id="sp-shop-content"></div>';
	$printPage .= '</section>';
}

// Other Expenses Panel (Section E of the expense report). Data loads lazily on activation.
if ( $cap_x_other ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-other-expenses">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expense Report','Other Expenses' );
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-oexp-add-btn">+ Add Expense</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-oexp-summary" id="sp-oexp-summary"></div>';
	$printPage .=   '<div class="sp-period-toolbar-wrap" id="sp-oexp-toolbar-wrap"></div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-oexp-intro">Anything that doesn\'t fit the other sections — food R&amp;D, home office, postage and the like. Enter the GL account for each line. These feed Section E of your expense report.</div></div>';
	$printPage .=   '<div class="sp-oexp-form-wrap" id="sp-oexp-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-oexp-content" id="sp-oexp-content"></div>';
	$printPage .= '</section>';
}

// Travel Expenses Panel (Section F of the expense report). Data loads lazily on activation.
if ( $cap_x_travel ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-travel-expenses">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expense Report','Travel Expenses' );
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-trav-add-btn">+ Add Expense</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-trav-summary" id="sp-trav-summary"></div>';
	$printPage .=   '<div class="sp-period-toolbar-wrap" id="sp-trav-toolbar-wrap"></div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-trav-intro">Airfare, hotels, rental cars and taxis, and meals while traveling. These feed Section F of your expense report.</div></div>';
	$printPage .=   '<div class="sp-trav-form-wrap" id="sp-trav-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-trav-content" id="sp-trav-content"></div>';
	$printPage .= '</section>';
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
	$printPage .= '<section class="sp-panel" id="sp-panel-reviews">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     '<h2>Reviews</h2>';
	$printPage .=     '<div class="sp-reviews-actions">';
	$printPage .=       '<span class="sp-reviews-analyze-status" id="sp-reviews-analyze-status"></span>';
	if ( in_array( 'manage_reviews', $caps ) || $eff_is_god ) {
		$printPage .=   '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-reviews-analyze-btn">Analyze reviews</button>';
	}
	// God-only CSV of the stored history (every platform, reply, AI topic and internal note) for
	// offline analysis. Exports whatever the current filters match — All time + no filters = everything.
	if ( site_pulse_god_can_override() ) {
		$printPage .=   '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-reviews-export-btn" title="Download the reviews matching your current filters as a CSV">Export CSV</button>';
	}
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-reviews-refresh-btn">Refresh</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	// Analytics scope: restaurant / brand / time range. Drives the sentiment stat cards (and the list).
	$printPage .=   '<div class="sp-reviews-statbar" id="sp-reviews-statbar">';
	$printPage .=     '<select id="sp-reviews-stat-restaurant" class="sp-select"><option value="">All restaurants</option></select>';
	$printPage .=     '<select id="sp-reviews-stat-brand" class="sp-select"><option value="">All brands</option></select>';
	$printPage .=     '<select id="sp-reviews-stat-range" class="sp-select">';
	$printPage .=       '<option value="30" selected>Last 30 days</option>';
	$printPage .=       '<option value="90">Last 90 days</option>';
	$printPage .=       '<option value="365">Last 12 months</option>';
	$printPage .=       '<option value="ytd">Year to date</option>';
	$printPage .=       '<option value="all">All time</option>';
	$printPage .=     '</select>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reviews-stats" id="sp-reviews-stats"></div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-reviews-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<select id="sp-reviews-filter-stars" class="sp-select">';
	$printPage .=         '<option value="">All ratings</option>';
	$printPage .=         '<option value="5">5 stars</option>';
	$printPage .=         '<option value="4">4 stars</option>';
	$printPage .=         '<option value="3">3 stars</option>';
	$printPage .=         '<option value="2">2 stars</option>';
	$printPage .=         '<option value="1">1 star</option>';
	$printPage .=       '</select>';
	$printPage .=       '<select id="sp-reviews-filter-reply" class="sp-select">';
	$printPage .=         '<option value="">All reviews</option>';
	$printPage .=         '<option value="unreplied">Needs reply</option>';
	$printPage .=         '<option value="replied">Replied</option>';
	$printPage .=       '</select>';
	// Platforms filter — narrow the list to one source (e.g. only Facebook recommendations).
	// Options come from the platforms actually present in the reviews table (sp_review_sources()),
	// so an install pulling only Google + Facebook shows exactly those two and nothing appears
	// until real rows from another platform land.
	$printPage .=       '<select id="sp-reviews-filter-platform" class="sp-select">';
	// Guarded: if a partial deploy / stale OPcache leaves the reviews include out of sync, fall back to a
	// single option instead of fataling the whole dashboard.
	$printPage .=         ( function_exists( 'sp_review_source_options_html' ) ? sp_review_source_options_html() : '<option value="">All platforms</option>' );
	$printPage .=       '</select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reviews-list" id="sp-reviews-list"><div class="sp-loading"></div></div>';
	$printPage .=   '<div class="sp-reviews-more">';
	$printPage .=     '<div class="sp-reviews-count" id="sp-reviews-count" hidden></div>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-reviews-load-older" hidden>Load older reviews</button>';
	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// Trends Panel — each store's overall + per-category averages vs the company baseline. Loads lazily.
if ( ( in_array( 'view_reviews', $caps ) || in_array( 'manage_reviews', $caps ) ) && ! $is_review_hub ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-review-trends">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Customer Feedback', 'Trends' ) . '</div>';
	$printPage .=   '<div class="sp-reviews-statbar">';
	$printPage .=     '<select id="sp-trends-group" class="sp-select"><option value="store">By store</option><option value="supervisor">By supervisor</option></select>';
	$printPage .=     '<select id="sp-trends-supervisor" class="sp-select"><option value="">All supervisors</option></select>';
	$printPage .=     '<select id="sp-trends-brand" class="sp-select"><option value="">All brands</option></select>';
	$printPage .=     '<select id="sp-trends-range" class="sp-select">';
	$printPage .=       '<option value="90">Last 90 days</option>';
	$printPage .=       '<option value="365" selected>Last 12 months</option>';
	$printPage .=       '<option value="ytd">Year to date</option>';
	$printPage .=       '<option value="all">All time</option>';
	$printPage .=     '</select>';
	$printPage .=   '</div>';
	$printPage .=   '<div id="sp-trends-content"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

// Client Reviews Panel (agency / hub only). Every mapped client's Google reviews grouped by
// client, with reply + one-click push of a review to that client's site as a Testimonial CPT.
if ( ( defined( 'BPGBP_REFRESH_TOKEN' ) && BPGBP_REFRESH_TOKEN ) && ( $eff_is_god || in_array( 'manage_reviews', $caps ) ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-agency-reviews">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     '<h2>Client Reviews</h2>';
	$printPage .=     '<div class="sp-reviews-actions">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-agency-reviews-refresh-btn">Refresh</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
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
	$printPage .=   '<div class="sp-reviews-list" id="sp-agency-reviews-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

// Site Fleet Panel (agency / hub only). Every Battle Plan site's nightly snapshot in one sortable
// list, problem sites first. Same double gate as the nav item: hub install + agency capability.
if ( function_exists( 'sp_fleet_is_hub' ) && sp_fleet_is_hub()
	&& ( $eff_is_god || in_array( 'view_fleet', $caps, true ) || in_array( 'manage_fleet', $caps, true ) ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-fleet">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     '<h2>Site Fleet</h2>';
	$printPage .=     '<div class="sp-reviews-actions">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-fleet-refresh-btn">Refresh</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-stat-grid" id="sp-fleet-stats"></div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-fleet-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<input type="search" id="sp-fleet-search" class="sp-input" placeholder="Search site…" autocomplete="off">';
	$printPage .=       '<select id="sp-fleet-filter" class="sp-select">';
	$printPage .=         '<option value="">All sites</option>';
	$printPage .=         '<option value="alerts">Needs attention</option>';
	$printPage .=         '<option value="silent">Not reporting</option>';
	$printPage .=         '<option value="audit">Audit due</option>';
	$printPage .=       '</select>';
	$printPage .=       '<select id="sp-fleet-sort" class="sp-select">';
	$printPage .=         '<option value="severity" selected>Problems first</option>';
	$printPage .=         '<option value="name">Name</option>';
	$printPage .=         '<option value="traffic">Traffic</option>';
	$printPage .=         '<option value="change">Biggest change</option>';
	$printPage .=         '<option value="audit">Oldest audit</option>';
	$printPage .=       '</select>';
	$printPage .=       '<select id="sp-fleet-sort-dir" class="sp-select" aria-label="Sort direction">';
	$printPage .=         '<option value="" selected>Normal order</option>';
	$printPage .=         '<option value="reverse">Reverse order</option>';
	$printPage .=       '</select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-fleet-list" id="sp-fleet-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

// Customer Surveys Panel — submissions forwarded in from the public restaurant sites.
// Sibling tab of Reviews; gated on the surveys module's caps (independent of Google reviews).
if ( in_array( 'view_surveys', $caps ) || in_array( 'manage_surveys', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-surveys">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     '<h2>Comment Cards</h2>';
	$printPage .=     '<div class="sp-reviews-actions">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-survey-archive-toggle">View Archived Comment Cards</button>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-survey-refresh-btn">Refresh</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-survey-summary" id="sp-survey-summary"></div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-survey-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<input type="search" id="sp-survey-search" class="sp-input sp-survey-search" placeholder="Search name, phone, or address…">';
	$printPage .=       '<select id="sp-survey-filter-location" class="sp-select"><option value="">All locations</option></select>';
	$printPage .=       '<select id="sp-survey-filter-range" class="sp-select">';
	$printPage .=         '<option value="">All time</option>';
	$printPage .=         '<option value="30">Last 30 days</option>';
	$printPage .=         '<option value="90">Last 90 days</option>';
	$printPage .=         '<option value="365">Last 12 months</option>';
	$printPage .=         '<option value="ytd">Year to date</option>';
	$printPage .=       '</select>';
	$printPage .=       '<select id="sp-survey-filter-source" class="sp-select">';
	$printPage .=         '<option value="">All sources</option>';
	$printPage .=         '<option value="web">Web</option>';
	$printPage .=         '<option value="imported">Imported</option>';
	$printPage .=       '</select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-survey-list" id="sp-survey-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

// Customer Emails Panel — flagged customer-service emails forwarded in from the public sites. List of
// message cards with status / type / brand / location filters + search; mark-handled + delete for managers.
if ( $cap_emails_area ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-emails">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Customer Feedback', 'Emails' ) . '</div>';
	$printPage .=   '<div class="sp-toolbar sp-emails-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<select id="sp-emails-filter-status" class="sp-select"><option value="new">New</option><option value="handled">Handled</option><option value="all">All</option></select>';
	$printPage .=       '<select id="sp-emails-filter-category" class="sp-select"><option value="">All types</option></select>';
	$printPage .=       '<select id="sp-emails-filter-brand" class="sp-select"><option value="">All brands</option></select>';
	$printPage .=       '<select id="sp-emails-filter-location" class="sp-select"><option value="">All locations</option></select>';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-toolbar-group sp-emails-search-group">';
	$printPage .=       '<input type="search" id="sp-emails-search" class="sp-input" placeholder="Search name, email, phone, or message…">';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-emails-count" id="sp-emails-count"></div>';
	$printPage .=   '<div class="sp-emails-list" id="sp-emails-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

// Routed Emails Panel — a generic retention center for forwarded emails addressed to a specific person.
// A viewer sees only the ones addressed to their account email; a manager (manage_routed_emails) sees all.
if ( in_array( 'view_routed_emails', $caps, true ) || in_array( 'manage_routed_emails', $caps, true ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-emails-routed">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Customer Feedback', 'Routed Emails' ) . '</div>';
	$printPage .=   '<div class="sp-toolbar sp-emails-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<select id="sp-remails-filter-status" class="sp-select"><option value="new">New</option><option value="handled">Handled</option><option value="all">All</option></select>';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-toolbar-group sp-emails-search-group">';
	$printPage .=       '<input type="search" id="sp-remails-search" class="sp-input" placeholder="Search subject, sender, or message…">';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-emails-count" id="sp-remails-count"></div>';
	$printPage .=   '<div class="sp-emails-list" id="sp-remails-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

// Customer Messages Panel — Facebook / Instagram DMs. Two-pane inbox reusing the Messages styling
// (conversation list + thread). Inbound arrives via the hub relay; replies go back out through the hub.
if ( $cap_cm_area ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-customer-messages">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Customer Feedback', 'Direct Messages' ) . '</div>';
	$printPage .=   '<div class="sp-messenger" id="sp-cm-messenger">';
	$printPage .=     '<div class="sp-card sp-msg-list" id="sp-cm-list"><div class="sp-loading"></div></div>';
	$printPage .=     '<div class="sp-card sp-msg-thread" id="sp-cm-thread"></div>';
	$printPage .=   '</div>';
	$printPage .= '</section>';
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

	$printPage .= '<section class="sp-panel" id="sp-panel-directory">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">';
	$printPage .=     '<h2>Directory</h2>';
	if ( $cap_directory_manage ) {
		$printPage .=   '<div class="sp-reviews-actions">';
		$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-dir-add-btn">+ Add Employee</button>';
		$printPage .=   '</div>';
	}
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-dir-filters">';
	$printPage .=     '<select id="sp-dir-filter-brand" class="sp-select"><option value="">All brands</option>' . $dir_select_opts( $dir_opts['brands'] ) . '</select>';
	$printPage .=     '<select id="sp-dir-filter-location" class="sp-select"><option value="">All locations</option>' . $dir_select_opts( $dir_opts['locations'] ) . '</select>';
	$printPage .=     '<select id="sp-dir-filter-position" class="sp-select"><option value="">All positions</option>' . $dir_select_opts( $dir_opts['positions'] ) . '</select>';
	$printPage .=     '<select id="sp-dir-filter-status" class="sp-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option></select>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-dir-searchrow">';
	$printPage .=     '<input type="search" id="sp-dir-search" class="sp-input sp-dir-search" placeholder="Search name, email, or phone…">';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-dir-clear">Clear</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-dir-count" id="sp-dir-count"></div>';
	$printPage .=   '<div class="sp-directory-grid" id="sp-directory-grid"><div class="sp-loading"></div></div>';
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
if ( $cap_store_orders ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-store-orders">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Store', 'Orders' );
	if ( $cap_store_manage ) {
		$printPage .= '<div class="sp-panel-actions"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-store-order-new">New Order</button></div>';
	}
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-store-order-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<input type="search" id="sp-store-order-search" class="sp-input" placeholder="Search name, email, or order number&hellip;">';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<span class="sp-toolbar-label">Status</span>';
	$printPage .=       '<select id="sp-store-order-status" class="sp-select">';
	foreach ( sp_store_order_statuses() as $sp_so_status => $sp_so_label ) {
		$printPage .=     '<option value="' . esc_attr( $sp_so_status ) . '"' . ( 'open' === $sp_so_status ? ' selected' : '' ) . '>' . esc_html( $sp_so_label ) . '</option>';
	}
	$printPage .=       '<option value="">All</option></select>';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<span class="sp-toolbar-label">Fulfillment</span>';
	$printPage .=       '<select id="sp-store-order-fulfillment" class="sp-select"><option value="">Any</option>';
	foreach ( sp_store_fulfillment_statuses() as $sp_sf_status => $sp_sf_label ) {
		$printPage .=     '<option value="' . esc_attr( $sp_sf_status ) . '">' . esc_html( $sp_sf_label ) . '</option>';
	}
	$printPage .=       '</select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   $sp_book_tripwire;
	$printPage .=   '<div class="sp-table-card" id="sp-store-orders"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}
if ( $cap_store_products ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-store-inventory">';
	$printPage .=   '<div class="sp-header-grid sp-panel-header">' . $sp_crumb( 'Store', 'Inventory' ) . '</div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-store-inventory-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<input type="search" id="sp-store-inventory-search" class="sp-input" placeholder="Search product or SKU&hellip;">';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<span class="sp-toolbar-label">Show</span>';
	$printPage .=       '<select id="sp-store-inventory-filter" class="sp-select">';
	$printPage .=         '<option value="">All tracked items</option>';
	$printPage .=         '<option value="low">Low stock</option>';
	$printPage .=         '<option value="out">Out of stock</option>';
	$printPage .=       '</select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   $sp_book_tripwire;
	$printPage .=   '<div class="sp-table-card" id="sp-store-inventory"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
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
if ( in_array( 'manage_settings', $caps ) ) {
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
if ( in_array( 'manage_roles', $caps ) || in_array( 'manage_settings', $caps ) ) {
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
if ( in_array( 'manage_settings', $caps ) ) {
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
$printPage .= '</div>';

return $printPage;
