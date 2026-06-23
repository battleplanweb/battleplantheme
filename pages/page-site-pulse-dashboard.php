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
	$caps = array_keys( site_pulse_capability_catalog_all() );
	$caps[] = 'god_mode';
	$role_label = 'Odinson';
} elseif ( $is_wp_admin && ! $role ) {
	$caps = [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'manage_notifications', 'manage_api_keys', 'view_ai_insights', 'view_forms', 'upload_forms', 'submit_reports', 'view_gm_action_items', 'view_supervisor_action_items', 'manage_mileage', 'submit_mileage' ];
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
$display_name = $user ? esc_html( $full_name !== '' ? $full_name : $user->display_name ) : 'User';
$loc_name     = $location ? esc_html( $location['name'] ) : '';

// Report-access gates, driven by the two report-type caps.
$cap_view_gm            = in_array( 'view_gm_reports', $caps, true );
$cap_view_sup           = in_array( 'view_supervisor_reports', $caps, true );
$cap_view_other_reports = $cap_view_gm || $cap_view_sup;
$cap_reports_access     = in_array( 'submit_reports', $caps, true ) || $cap_view_other_reports;

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
	'palette'   => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.564C22 6.012 17.5 2 12 2z"/>',
];

// Build nav structure with sub-items
$nav = [];

$nav[] = [ 'slug' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'show' => true ];

// Direct messages between users. Available to every signed-in Site Pulse user.
$nav[] = [ 'slug' => 'messages', 'label' => 'Messages', 'icon' => 'chat', 'show' => true ];

// Action Items — its own top-level item (sits under Messages). Shown to everyone: items can now be
// created from messages for any user, not just report-access roles.
$nav[] = [ 'slug' => 'action-items', 'label' => 'Action Items', 'icon' => 'tasks', 'show' => true ];

$nav[] = [
	'slug'  => 'reports',
	'label' => 'Stores',
	'icon'  => 'store',
	'show'  => $cap_reports_access,
	'children' => [
		[ 'slug' => 'reports-my',     'label' => 'My Reports',          'show' => in_array( 'submit_reports', $caps ) ],
		[ 'slug' => 'reports-review', 'label' => 'GM Reports',          'action' => 'review-gm',  'show' => $cap_view_gm ],
		[ 'slug' => 'reports-review', 'label' => 'Supervisor Reports',  'action' => 'review-sup', 'show' => $cap_view_sup ],
	],
];

$nav[] = [
	'slug'  => 'mileage',
	'label' => 'Expenses',
	'icon'  => 'dollar-sign',
	'show'  => in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ),
	'children' => [
		[ 'slug' => 'expense-report',    'label' => 'Expense Report',   'show' => in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ],
		[ 'slug' => 'mileage',           'label' => 'Mileage',          'show' => in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ],
		[ 'slug' => 'mileage-tolls',     'label' => 'Tolls',            'show' => in_array( 'submit_mileage', $caps ) ],
		[ 'slug' => 'vehicle-expenses',  'label' => 'Vehicle Expenses', 'show' => in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ],
		[ 'slug' => 'business-meals',    'label' => 'Business Meals',   'show' => in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ],
		[ 'slug' => 'competitive-shopping', 'label' => 'Competitive Shopping', 'show' => in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ],
		[ 'slug' => 'other-expenses',    'label' => 'Other Expenses',   'show' => in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ],
	],
];

// Reviews area: Google reviews + Customer Surveys as sibling tabs. The parent shows if EITHER
// module's caps are present, so Surveys can stand alone even while Reviews (Google) is dark.
$cap_reviews_area = in_array( 'view_reviews', $caps ) || in_array( 'manage_reviews', $caps );
$cap_surveys_area = in_array( 'view_surveys', $caps ) || in_array( 'manage_surveys', $caps );
$cap_emails_area  = in_array( 'view_emails', $caps ) || in_array( 'manage_emails', $caps );
$cap_emails_manage = in_array( 'manage_emails', $caps );
// Agency "Client Reviews": every mapped client's reviews in one place. Hub-only (this install holds
// the GBP token) and agency-only (manage_reviews / god) — invisible on client sites entirely.
$is_review_hub      = defined( 'BPGBP_REFRESH_TOKEN' ) && BPGBP_REFRESH_TOKEN;
$cap_agency_reviews = $is_review_hub && ( $eff_is_god || in_array( 'manage_reviews', $caps ) );
$nav[] = [
	'slug'  => 'reviews',
	'label' => 'Customer Feedback',
	'icon'  => 'star',
	'show'  => $cap_reviews_area || $cap_surveys_area || $cap_agency_reviews || $cap_emails_area,
	'children' => [
		[ 'slug' => 'reviews',        'label' => 'Reviews',        'show' => $cap_reviews_area ],
		[ 'slug' => 'agency-reviews', 'label' => 'Client Reviews', 'show' => $cap_agency_reviews ],
		[ 'slug' => 'surveys',        'label' => 'Surveys',        'show' => $cap_surveys_area ],
		[ 'slug' => 'emails',         'label' => 'Emails',         'show' => $cap_emails_area ],
	],
];

// Company Directory — staff directory (names, photos, contact info). Most people in it are NOT
// users; access is gated purely by role + per-user override via the directory caps. God always sees.
$cap_directory        = in_array( 'view_directory', $caps, true ) || in_array( 'manage_directory', $caps, true );
$cap_directory_manage = in_array( 'manage_directory', $caps, true );
$nav[] = [ 'slug' => 'directory', 'label' => 'Directory', 'icon' => 'users', 'show' => $cap_directory ];

// Forms is gated on the 'view_forms' capability — and ALWAYS visible in Odin (god) mode,
// independent of the module toggle / capability catalog, so god never loses it. 'upload_forms'
// is a separate capability that reveals the per-repository upload controls (and, on its own,
// the Forms area). The AJAX endpoints mirror this god bypass.
$god_forms        = $is_god && ! $impersonating;
$cap_view_forms   = in_array( 'view_forms', $caps ) || $god_forms;
$cap_upload_forms = in_array( 'upload_forms', $caps ) || $god_forms;
$cap_forms_area   = $cap_view_forms || $cap_upload_forms;
$forms_children = [];
foreach ( site_pulse_form_category_tree() as $sp_cat_key => $sp_cat ) {
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
	'label' => 'Forms',
	'icon'  => 'forms',
	'show'  => $cap_forms_area,
	'children' => $forms_children,
];

$nav[] = [ 'slug' => 'analytics', 'label' => 'Analytics', 'icon' => 'chart', 'show' => in_array( 'view_analytics', $caps ) ];

// Import Past Reports — GOD-only tool to bulk-import historical GM reports from PDF/CSV.
$nav[] = [ 'slug' => 'import-reports', 'label' => 'Import Reports', 'icon' => 'forms', 'show' => $eff_is_god ];

$nav[] = [
	'slug'  => 'admin',
	'label' => 'Settings',
	'icon'  => 'settings',
	'show'  => in_array( 'manage_locations', $caps ) || in_array( 'manage_users', $caps ) || in_array( 'manage_settings', $caps ) || in_array( 'manage_mileage', $caps ) || in_array( 'manage_templates', $caps ) || in_array( 'manage_notifications', $caps ) || in_array( 'manage_api_keys', $caps ),
	'children' => [
		[ 'slug' => 'admin-users',     'label' => 'Users',            'icon' => 'users',   'show' => in_array( 'manage_users', $caps ) ],
		[ 'slug' => 'admin-tiers',     'label' => 'Roles',            'icon' => 'layers',  'show' => in_array( 'manage_settings', $caps ) ],
		[ 'slug' => 'admin-locations', 'label' => 'Home Bases',       'icon' => 'mappin',  'show' => in_array( 'manage_locations', $caps ) ],
		[ 'slug' => 'admin-templates', 'label' => 'Reports',        'icon' => 'file',    'show' => in_array( 'manage_templates', $caps ) ],
		[ 'slug' => 'admin-mileage',   'label' => 'Mileage',        'icon' => 'car',     'show' => in_array( 'manage_mileage', $caps ) ],
		[ 'slug' => 'admin-forms',     'label' => 'Forms',          'icon' => 'forms',   'show' => in_array( 'manage_settings', $caps ) ],
		[ 'slug' => 'admin-settings',  'label' => 'Site Defaults',  'icon' => 'palette', 'show' => in_array( 'manage_settings', $caps ) ],
		[ 'slug' => 'admin-notifications', 'label' => 'Notifications', 'icon' => 'bell',   'show' => in_array( 'manage_notifications', $caps ) ],
		[ 'slug' => 'admin-apikeys',   'label' => 'API Keys',         'icon' => 'key',     'show' => in_array( 'manage_api_keys', $caps ) ],
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
$printPage .=   '<div class="sp-sidebar-header">';
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

// Sidebar Nav
$printPage .= '<nav class="sp-sidebar-nav">';
foreach ( $nav as $item ) {
	if ( empty( $item['show'] ) ) continue;
	$has_children = ! empty( $item['children'] );
	$is_first = $item['slug'] === 'dashboard';

	$printPage .= '<div class="sp-nav-group' . ( $is_first ? ' active' : '' ) . '" data-group="' . esc_attr( $item['slug'] ) . '">';

	$tag = 'button type="button"';
	$printPage .= '<' . $tag . ' class="unique sp-nav-item' . ( $is_first ? ' active' : '' ) . '" data-nav="' . esc_attr( $item['slug'] ) . '">';
	$printPage .=   $svg( $icons[ $item['icon'] ] );
	$printPage .=   '<span class="sp-nav-label">' . esc_html( $item['label'] ) . '</span>';
	if ( $has_children ) {
		$printPage .= '<span class="sp-nav-arrow">' . $svg( $icons['chevron'] ) . '</span>';
	}
	$printPage .= '</button>';

	if ( $has_children ) {
		// Inner wrapper so the submenu can animate open/closed via grid-template-rows (0fr↔1fr).
		$printPage .= '<div class="sp-nav-children"><div class="sp-nav-children-inner">';
		foreach ( $item['children'] as $child ) {
			if ( empty( $child['show'] ) ) continue;
			$child_action = ! empty( $child['action'] ) ? ' data-action="' . esc_attr( $child['action'] ) . '"' : '';
			$printPage .= '<button type="button" class="unique sp-nav-child" data-nav="' . esc_attr( $child['slug'] ) . '"' . $child_action . '>';
			$printPage .=   '<span>' . esc_html( $child['label'] ) . '</span>';
			$printPage .= '</button>';
			// Optional third level: sub-folders that deep-link to the parent panel with a filter.
			if ( ! empty( $child['subchildren'] ) ) {
				$printPage .= '<div class="sp-nav-subchildren">';
				foreach ( $child['subchildren'] as $sub ) {
					if ( empty( $sub['show'] ) ) continue;
					$printPage .= '<button type="button" class="unique sp-nav-child sp-nav-subchild" data-nav="' . esc_attr( $sub['slug'] ) . '" data-sub="' . esc_attr( $sub['sub'] ) . '">';
					$printPage .=   '<span>' . esc_html( $sub['label'] ) . '</span>';
					$printPage .= '</button>';
				}
				$printPage .= '</div>';
			}
		}
		$printPage .= '</div></div>';
	}

	$printPage .= '</div>';
}
$printPage .= '</nav>';

$printPage .= '</aside>';

// Mobile Top Bar
$printPage .= '<header class="sp-mobile-header" id="sp-mobile-header">';
$printPage .=   '<button type="button" class="unique sp-hamburger" id="sp-hamburger" aria-label="Open menu">' . $svg( $icons['menu'] ) . '</button>';
$printPage .=   '<div class="sp-mobile-brand">';
if ( $header_logo ) {
	$printPage .=   '<img src="' . esc_url( $header_logo ) . '" alt="' . esc_attr( $app_name ) . '" class="sp-mobile-logo">';
} else {
	$printPage .=   '<span class="sp-mobile-title">' . esc_html( $app_name ) . '</span>';
}
$printPage .=     '<span class="sp-mobile-powered">Powered by Battle Plan Site Pulse</span>';
$printPage .=   '</div>';
$printPage .= '</header>';

// Overlay for mobile sidebar
$printPage .= '<div class="sp-overlay" id="sp-overlay"></div>';

// Main Content
$printPage .= '<main class="sp-main" id="sp-main">';

// Top Bar — User info, notifications, logout
$printPage .= '<header class="sp-topbar" id="sp-topbar">';
$printPage .=   '<div class="sp-topbar-left">';
$printPage .=     $svg( $icons['user'] );
$printPage .=     '<span class="sp-topbar-name">' . $display_name . '</span>';
if ( $role_label ) {
	$printPage .= '<span class="sp-topbar-divider">&#9642;</span>';
	$printPage .= '<span class="sp-topbar-role">' . $role_label . '</span>';
}
if ( $loc_name ) {
	$printPage .= '<span class="sp-topbar-divider">&#9642;</span>';
	$printPage .= '<span class="sp-topbar-location">' . $loc_name . '</span>';
}
$printPage .=   '</div>';
$printPage .=     '<button type="button" class="unique sp-topbar-btn sp-notification-btn" id="sp-notification-btn" aria-label="Notifications">';
$printPage .=       '<svg class="sp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
$printPage .=       '<span class="sp-notification-badge" id="sp-notification-badge" hidden>0</span>';
$printPage .=     '</button>';
$printPage .=     '<button type="button" class="unique sp-topbar-btn sp-logout-btn-top" id="sp-logout-btn-top" aria-label="Sign Out">';
$printPage .=       $svg( $icons['logout'] );
$printPage .=       '<span>Sign Out</span>';
$printPage .=     '</button>';
$printPage .= '</header>';

// Notification Panel
$printPage .= '<div class="sp-notification-panel" id="sp-notification-panel" hidden>';
$printPage .=   '<div class="sp-notification-header">';
$printPage .=     '<h3>Notifications</h3>';
$printPage .=     '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mark-all-read">Mark all read</button>';
$printPage .=   '</div>';
$printPage .=   '<div class="sp-push-prompt" id="sp-push-prompt" hidden></div>'; // per-device "turn on push", filled by JS
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
$printPage .=   '<div class="sp-panel-header"><h2>Dashboard</h2></div>';

// "Install the app" reminder card — filled + shown by JS only when NOT running as the installed app
// and the user hasn't installed it yet. (Separate from the floating install banner, which stays.)
$printPage .=   '<div class="sp-install-card" id="sp-install-card" hidden></div>';

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

$printPage .=   '<div class="sp-dashboard-widgets">';

// News Feed Widget (full width) — a Google Alerts feed, shown only when the feed is switched on
// (Settings → Site Settings) AND keywords are configured. The toggle removes it from every
// dashboard regardless of keywords; the keyword is per-install, so nothing is hardcoded.
$sp_alert_kw = trim( (string) site_pulse_get_setting( 'dashboard_alert_keywords', '' ) );
$sp_news_on  = (string) site_pulse_get_setting( 'dashboard_news_enabled', '1' ) !== '0';
if ( $sp_news_on && $sp_alert_kw !== '' ) {
	$printPage .= '<div class="sp-widget sp-widget-full" id="sp-widget-alerts">';
	$printPage .=   '<div class="sp-widget-header"><h3>In the News</h3></div>';
	$printPage .=   '<div class="sp-widget-body sp-alert-feed-body">';
	if ( shortcode_exists( 'get-google-alerts' ) ) {
		$sp_alert_kw_safe = str_replace( [ '"', ']' ], '', $sp_alert_kw );
		$printPage .= do_shortcode( '[get-google-alerts keywords="' . $sp_alert_kw_safe . '" max="8"]' );
	} else {
		$printPage .= '<p class="sp-widget-empty">The “get-google-alerts” feature isn’t available on this site yet.</p>';
	}
	$printPage .=   '</div>';
	$printPage .= '</div>';
}

// Reports Widget
if ( $cap_reports_access ) {
	$printPage .= '<div class="sp-widget" id="sp-widget-reports">';
	$printPage .=   '<div class="sp-widget-header">';
	$printPage .=     '<h3>Recent Reports</h3>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="' . ( in_array( 'submit_reports', $caps ) ? 'reports-my' : 'reports-review' ) . '">View All &rarr;</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-widget-body" id="sp-widget-reports-body"><div class="sp-loading"></div></div>';
	$printPage .= '</div>';
}

// Action Items Widget
if ( $cap_reports_access ) {
	$printPage .= '<div class="sp-widget" id="sp-widget-actions">';
	$printPage .=   '<div class="sp-widget-header">';
	$printPage .=     '<h3>Open Action Items</h3>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="action-items">View All &rarr;</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-widget-body" id="sp-widget-actions-body"><div class="sp-loading"></div></div>';
	$printPage .= '</div>';
}

// Mileage Widget (this month, for the logged-in user)
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<div class="sp-widget" id="sp-widget-mileage">';
	$printPage .=   '<div class="sp-widget-header">';
	$printPage .=     '<h3>My Mileage This Month</h3>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="mileage">View All &rarr;</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-widget-body" id="sp-widget-mileage-body"><div class="sp-loading"></div></div>';
	$printPage .= '</div>';
}

$printPage .=   '</div>';
$printPage .= '</section>';

// Messages Panel — direct messages between users. Two-pane: conversation list + active thread.
$printPage .= '<section class="sp-panel" id="sp-panel-messages">';
$printPage .=   '<div class="sp-panel-header"><h2>Messages</h2>';
$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-msg-new-btn">+ New Message</button>';
$printPage .=   '</div>';
$printPage .=   '<div class="sp-messenger" id="sp-messenger">';
$printPage .=     '<div class="sp-msg-list" id="sp-msg-list"></div>';
$printPage .=     '<div class="sp-msg-thread" id="sp-msg-thread"></div>';
$printPage .=   '</div>';
$printPage .= '</section>';

// My Reports Panel
if ( in_array( 'submit_reports', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-my">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     $sp_crumb( 'Stores', 'My Reports' );
	if ( in_array( 'submit_reports', $caps ) ) {
		$printPage .= '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-new-report-btn">+ New Report</button>';
	}
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-report-filters">';
	$printPage .=     '<select id="sp-filter-template" class="sp-select"><option value="">All Report Types</option></select>';
	$printPage .=     '<div class="sp-date-range"><input type="date" id="sp-filter-date-start" class="sp-input" placeholder="From">';
	$printPage .=     '<input type="date" id="sp-filter-date-end" class="sp-input" placeholder="To"></div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reports-list" id="sp-reports-list"></div>';
	$printPage .=   '<div class="sp-report-form-wrap" id="sp-report-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-report-detail-wrap" id="sp-report-detail-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Review Reports Panel
if ( $cap_view_other_reports ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-review">';
	$printPage .=   '<div class="sp-panel-header"><div class="sp-crumb"><div class="sp-crumb-parent">Stores</div><h2 class="sp-crumb-current" id="sp-review-title">GM Reports</h2></div></div>';
	$printPage .=   '<div class="sp-report-filters">';
	$printPage .= '<select id="sp-review-filter-location" class="sp-select"><option value="">All Locations</option></select>';
	$printPage .=     '<div class="sp-date-range"><input type="date" id="sp-review-filter-start" class="sp-input" placeholder="From">';
	$printPage .=     '<input type="date" id="sp-review-filter-end" class="sp-input" placeholder="To"></div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reports-list" id="sp-review-list"></div>';
	$printPage .=   '<div class="sp-report-detail-wrap" id="sp-review-detail-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Action Items Panel — top-level, visible to every signed-in user (the data endpoint always scopes
// to at least the viewer's own items).
{
	$printPage .= '<section class="sp-panel" id="sp-panel-action-items">';
	$printPage .=   '<div class="sp-panel-header"><h2>Action Items</h2>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-action-item-btn">+ Add Action Item</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-report-filters">';
	if ( $cap_view_other_actions ) {
		$printPage .= '<select id="sp-action-filter-location" class="sp-select"><option value="mine">My Action Items</option></select>';
	}
	$printPage .=     '<select id="sp-action-filter-status" class="sp-select">';
	$printPage .=       '<option value="open">Open</option>';
	$printPage .=       '<option value="">All</option>';
	$printPage .=       '<option value="resolved">Completed</option>';
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

// Analytics Panel
if ( in_array( 'view_analytics', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-analytics">';
	$printPage .=   '<div class="sp-panel-header"><h2>Analytics</h2></div>';

	// Report AI search (GOD only during rollout). Non-god still sees the "Coming Soon" placeholder.
	if ( $eff_is_god ) {
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
	$printPage .=     '<div class="sp-analytics-card" id="sp-analytics-priority">';
	$printPage .=       '<h4>Action Items by Priority</h4>';
	$printPage .=       '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
	$printPage .=     '</div>';

	// Action Items by Category
	$printPage .=     '<div class="sp-analytics-card" id="sp-analytics-category">';
	$printPage .=       '<h4>Action Items by Category</h4>';
	$printPage .=       '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
	$printPage .=     '</div>';

	// Reports by Location
	$printPage .=     '<div class="sp-analytics-card" id="sp-analytics-locations">';
	$printPage .=       '<h4>Reports by Location</h4>';
	$printPage .=       '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
	$printPage .=     '</div>';

	// Resolution Rate
	$printPage .=     '<div class="sp-analytics-card" id="sp-analytics-resolution">';
	$printPage .=       '<h4>Resolution Rate</h4>';
	$printPage .=       '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
	$printPage .=     '</div>';

	// Survey Results by Location (only for users who can view surveys). Stores down the side,
	// each with a 1-5 distribution bar chart; the dropdown toggles which question is shown.
	if ( in_array( 'view_surveys', $caps ) ) {
		$printPage .=   '<div class="sp-analytics-card" id="sp-analytics-surveys">';
		$printPage .=     '<div class="sp-analytics-card-head">';
		$printPage .=       '<h4>Survey Results by Location</h4>';
		$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-analytics-survey-link">View Surveys</button>';
		$printPage .=     '</div>';
		$printPage .=     '<div class="sp-analytics-card-toolbar"><select id="sp-analytics-survey-dim" class="sp-select"></select></div>';
		$printPage .=     '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
		$printPage .=   '</div>';
	}

	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// Import Past Reports Panel (GOD-only) — upload PDFs/CSVs of historical GM reports, AI-parse,
// review/confirm attribution, and save. No action items are generated for imported reports.
if ( $eff_is_god ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-import-reports">';
	$printPage .=   '<div class="sp-panel-header"><h2>Import Past Reports</h2></div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-import-intro">Upload past <strong>GM or Supervisor reports</strong> (PDF, CSV, or image — JPG/PNG, one report per file). Claude reads each file and fills in the matching fields; the report <strong>type is auto-detected from who wrote it</strong> (and editable). Review the detected info below, correct anything, then save. <strong>No action items are created</strong> for imported reports. <em>Supervisor reports aren\'t tied to a location.</em></div></div>';
	$printPage .=   '<div class="sp-import-drop" id="sp-import-drop">';
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

// Expense Report cover page — composes Sections A–F into one report with the Summary of
// Expenses (totals by GL account) and Total Due to Employee. Data loads lazily on activation.
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-expense-report">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Expenses', 'Expense Report' ) . '</div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-rep-intro">Everything from every section, totalled for the period — the same layout as your printed expense report. Use the period filter to pick a pay period, then export to PDF.</div></div>';
	$printPage .=   '<div class="sp-period-toolbar-wrap" id="sp-rep-toolbar-wrap"></div>';
	$printPage .=   '<div class="sp-rep-content" id="sp-rep-content"></div>';
	$printPage .= '</section>';
}

// Mileage Panel
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-mileage">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expenses', 'Mileage' );
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-mileage-summary" id="sp-mileage-summary"></div>';
	$printPage .=   '<div class="sp-toolbar sp-period-toolbar" id="sp-mileage-toolbar">';
	$printPage .=     '<span class="sp-toolbar-label">Period</span>';
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
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-csv-btn">' . $svg( $icons['grid'] ) . 'CSV</button>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-map-btn">' . $svg( $icons['mappin'] ) . 'Map</button>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-mileage-add-btn">+ Add a Day</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-mileage-intro">Log each day\'s business trips — your route, miles and tolls are calculated for you. These feed Section A of your expense report.</div></div>';
	$printPage .=   '<div class="sp-mileage-list" id="sp-mileage-list"></div>';
	$printPage .=   '<div class="sp-mileage-form-wrap" id="sp-mileage-form-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Reconcile Tolls Panel — upload a toll-authority CSV, then AI matches each charge
// to the legs of the days you logged trips. Review the matches, then apply.
if ( in_array( 'submit_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-mileage-tolls">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Expenses', 'Tolls' ) . '</div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-toll-intro">Upload the CSV export from your toll account (e.g. NTTA). We only look at the days you logged a trip — charges on any other date are ignored. AI matches each toll to a leg of that day\'s route; you review before anything is added.</div></div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-toll-toolbar">';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<input type="file" id="sp-toll-file" accept=".csv,text/csv" class="sp-toll-file">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-toll-upload-btn" disabled>Upload CSV</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-toll-status" id="sp-toll-status" hidden></div>';
	$printPage .=   '<div class="sp-toll-days" id="sp-toll-days"></div>';
	$printPage .= '</section>';
}

// Mileage Map Panel
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-mileage-map">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Expenses', 'Mileage Map' ) . '</div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-mileage-map-toolbar">';
	$printPage .=     '<span class="sp-toolbar-label">Filter</span>';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<select id="sp-mileage-map-category" class="sp-select"><option value="">All categories</option></select>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-map-fit">Fit All</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-mileage-map-notice" id="sp-mileage-map-notice" hidden>Add a Google Maps API key to enable the map. The mileage location key is reused; make sure it has the <strong>Maps JavaScript API</strong> enabled and is restricted to this site.</div>';
	$printPage .=   '<div class="sp-table-card"><div id="sp-mileage-map-canvas" class="sp-mileage-map-canvas"></div></div>';
	$printPage .= '</section>';
}

// Vehicle Expenses Panel (Section B of the expense report). Data loads lazily on activation.
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-vehicle-expenses">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expenses', 'Vehicle Expenses' );
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
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-business-meals">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expenses', 'Business Meals' );
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
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-competitive-shopping">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expenses', 'Competitive Shopping' );
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
if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-other-expenses">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     $sp_crumb( 'Expenses', 'Other Expenses' );
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-oexp-add-btn">+ Add Expense</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-oexp-summary" id="sp-oexp-summary"></div>';
	$printPage .=   '<div class="sp-period-toolbar-wrap" id="sp-oexp-toolbar-wrap"></div>';
	$printPage .=   '<div class="sp-meta-bar"><div class="sp-oexp-intro">Anything that doesn\'t fit the other sections — food R&amp;D, home office, postage and the like. Enter the GL account for each line. These feed Section E of your expense report.</div></div>';
	$printPage .=   '<div class="sp-oexp-form-wrap" id="sp-oexp-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-oexp-content" id="sp-oexp-content"></div>';
	$printPage .= '</section>';
}

// Reviews Panel (Google Business Profile). Data loads lazily on first activation.
if ( in_array( 'view_reviews', $caps ) || in_array( 'manage_reviews', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reviews">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     '<h2>Reviews</h2>';
	$printPage .=     '<div class="sp-reviews-actions">';
	$printPage .=       '<span class="sp-reviews-analyze-status" id="sp-reviews-analyze-status"></span>';
	if ( in_array( 'manage_reviews', $caps ) || $eff_is_god ) {
		$printPage .=   '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-reviews-analyze-btn">Analyze reviews</button>';
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
	$printPage .=     '<span class="sp-toolbar-label">Filter</span>';
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
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reviews-list" id="sp-reviews-list"><div class="sp-loading"></div></div>';
	$printPage .=   '<div class="sp-reviews-more">';
	$printPage .=     '<div class="sp-reviews-count" id="sp-reviews-count" hidden></div>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-reviews-load-older" hidden>Load older reviews</button>';
	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// Client Reviews Panel (agency / hub only). Every mapped client's Google reviews grouped by
// client, with reply + one-click push of a review to that client's site as a Testimonial CPT.
if ( ( defined( 'BPGBP_REFRESH_TOKEN' ) && BPGBP_REFRESH_TOKEN ) && ( $eff_is_god || in_array( 'manage_reviews', $caps ) ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-agency-reviews">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     '<h2>Client Reviews</h2>';
	$printPage .=     '<div class="sp-reviews-actions">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-agency-reviews-refresh-btn">Refresh</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-agency-reviews-toolbar">';
	$printPage .=     '<span class="sp-toolbar-label">Filter</span>';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<select id="sp-agency-filter-client" class="sp-select"><option value="">All clients</option></select>';
	$printPage .=       '<select id="sp-agency-filter-stars" class="sp-select">';
	$printPage .=         '<option value="">All ratings</option>';
	$printPage .=         '<option value="5">5 stars</option>';
	$printPage .=         '<option value="4">4 stars</option>';
	$printPage .=         '<option value="3">3 stars</option>';
	$printPage .=         '<option value="2">2 stars</option>';
	$printPage .=         '<option value="1">1 star</option>';
	$printPage .=       '</select>';
	$printPage .=       '<select id="sp-agency-filter-reply" class="sp-select">';
	$printPage .=         '<option value="">All reviews</option>';
	$printPage .=         '<option value="unreplied">Needs reply</option>';
	$printPage .=         '<option value="replied">Replied</option>';
	$printPage .=       '</select>';
	$printPage .=       '<select id="sp-agency-filter-sort" class="sp-select">';
	$printPage .=         '<option value="newest">Newest first</option>';
	$printPage .=         '<option value="oldest">Oldest first</option>';
	$printPage .=       '</select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reviews-list" id="sp-agency-reviews-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

// Customer Surveys Panel — submissions forwarded in from the public restaurant sites.
// Sibling tab of Reviews; gated on the surveys module's caps (independent of Google reviews).
if ( in_array( 'view_surveys', $caps ) || in_array( 'manage_surveys', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-surveys">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     '<h2>Customer Surveys</h2>';
	$printPage .=     '<div class="sp-reviews-actions">';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-survey-archive-toggle">View Archived Surveys</button>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-survey-refresh-btn">Refresh</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-survey-summary" id="sp-survey-summary"></div>';
	$printPage .=   '<div class="sp-toolbar" id="sp-survey-toolbar">';
	$printPage .=     '<span class="sp-toolbar-label">Filter</span>';
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
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-survey-list" id="sp-survey-list"><div class="sp-loading"></div></div>';
	$printPage .= '</section>';
}

// Customer Emails Panel — flagged customer-service emails forwarded in from the public sites. List of
// message cards with status / type / brand / location filters + search; mark-handled + delete for managers.
if ( $cap_emails_area ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-emails">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Customer Feedback', 'Emails' ) . '</div>';
	$printPage .=   '<div class="sp-toolbar">';
	$printPage .=     '<span class="sp-toolbar-label">Filter</span>';
	$printPage .=     '<div class="sp-toolbar-group">';
	$printPage .=       '<input type="search" id="sp-emails-search" class="sp-input" placeholder="Search name, email, phone, or message…">';
	$printPage .=       '<select id="sp-emails-filter-status" class="sp-select"><option value="new">New</option><option value="handled">Handled</option><option value="all">All</option></select>';
	$printPage .=       '<select id="sp-emails-filter-category" class="sp-select"><option value="">All types</option></select>';
	$printPage .=       '<select id="sp-emails-filter-brand" class="sp-select"><option value="">All brands</option></select>';
	$printPage .=       '<select id="sp-emails-filter-location" class="sp-select"><option value="">All locations</option></select>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-emails-count" id="sp-emails-count"></div>';
	$printPage .=   '<div class="sp-emails-list" id="sp-emails-list"><div class="sp-loading"></div></div>';
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
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     '<h2>Directory</h2>';
	if ( $cap_directory_manage ) {
		$printPage .=   '<div class="sp-reviews-actions">';
		$printPage .=     '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-dir-add-btn">Add Employee</button>';
		$printPage .=   '</div>';
	}
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-dir-filters">';
	$printPage .=     '<select id="sp-dir-filter-brand" class="sp-select"><option value="">All brands</option>' . $dir_select_opts( $dir_opts['brands'] ) . '</select>';
	$printPage .=     '<select id="sp-dir-filter-location" class="sp-select"><option value="">All locations</option>' . $dir_select_opts( $dir_opts['locations'] ) . '</select>';
	$printPage .=     '<select id="sp-dir-filter-position" class="sp-select"><option value="">All positions</option>' . $dir_select_opts( $dir_opts['positions'] ) . '</select>';
	$printPage .=     '<select id="sp-dir-filter-status" class="sp-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option></select>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-dir-count" id="sp-dir-count"></div>';
	$printPage .=   '<input type="search" id="sp-dir-search" class="sp-input sp-dir-search" placeholder="Search name, email, or phone…">';
	$printPage .=   '<div class="sp-directory-grid" id="sp-directory-grid"><div class="sp-loading"></div></div>';
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
		$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Forms', $sp_form_label );
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
		$printPage .=   '<div class="sp-table-card">';
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
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Forms', 'All' ) . '</div>';
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
	$printPage .=   '<div class="sp-table-card">';
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
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Forms' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-forms-content"></div>';
	$printPage .= '</section>';
}

// Admin Panels
if ( in_array( 'manage_users', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-users">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Users' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-users-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_settings', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-tiers">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Roles' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-tiers-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_locations', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-locations">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Home Bases' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-locations-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_templates', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-templates">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Reports' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-templates-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_settings', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-settings">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Site Defaults' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-settings-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_notifications', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-notifications">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Notifications' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-notifications-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_api_keys', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-apikeys">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'API Keys' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-apikeys-content"></div>';
	$printPage .= '</section>';
}
if ( $is_superadmin ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-modules">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Modules' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-modules-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_mileage', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-mileage">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Mileage' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-mileage-content"></div>';
	$printPage .= '</section>';
}

$printPage .= '</main>';
$printPage .= '</div>';

return $printPage;
