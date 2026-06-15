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
// The Modules screen is for the protected super-admin (battleplanweb) only, and
// never while impersonating — so "view as <someone>" hides it like that user sees.
$is_superadmin = site_pulse_is_superadmin( $real_user_id ) && ! $impersonating;
$user_id      = site_pulse_effective_user_id();
$user         = get_userdata( $user_id );
$profile      = site_pulse_get_user_profile( $user_id );
$role         = $profile ? site_pulse_get_role( $profile['role_id'] ) : null;
$location     = $profile ? site_pulse_get_location( (int) $profile['location_id'] ) : null;

$is_wp_admin = in_array( 'administrator', (array) get_userdata( $real_user_id )->roles, true );
if ( $is_god && ! $impersonating ) {
	// God sees everything: every capability in the catalog (so new caps are covered automatically)
	// plus god_mode. Never a hand-maintained subset that can drift out of date.
	$caps = array_keys( site_pulse_capability_catalog() );
	$caps[] = 'god_mode';
	$role_label = 'Odinson';
} elseif ( $is_wp_admin && ! $role ) {
	$caps = [ 'view_gm_reports', 'view_supervisor_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'view_ai_insights', 'view_forms', 'submit_reports', 'view_own_reports', 'manage_mileage', 'submit_mileage' ];
	$role_label = 'Administrator';
} else {
	// Effective caps = the role's capabilities with this user's per-user overrides applied, so an
	// individual tweak (e.g. mileage turned off for one supervisor) is reflected in the menu/panels.
	$caps = site_pulse_effective_caps( $user_id );
	$role_label = $role ? esc_html( $role['label'] ) : '';
}

// Whichever path set $caps above (god's full catalog, the wp-admin fallback list, or
// per-user effective caps), drop any capability whose module is off so the menu and
// panels for a disabled module never render — for anyone, god included.
$caps = site_pulse_filter_caps_by_module( $caps );

$app_name     = site_pulse_get_setting( 'app_name', 'Site Pulse' );
$company_name = site_pulse_get_setting( 'company_name', '' );
$logo_url     = site_pulse_get_setting( 'login_logo_url', '' );
$header_logo  = site_pulse_get_setting( 'header_logo_url', '' );
$display_name = $user ? esc_html( $user->first_name ?: $user->display_name ) : 'User';
$loc_name     = $location ? esc_html( $location['name'] ) : '';

// Report-access gates, driven by the two report-type caps.
$cap_view_gm            = in_array( 'view_gm_reports', $caps, true );
$cap_view_sup           = in_array( 'view_supervisor_reports', $caps, true );
$cap_view_other_reports = $cap_view_gm || $cap_view_sup;
$cap_reports_access     = in_array( 'submit_reports', $caps, true ) || in_array( 'view_own_reports', $caps, true ) || $cap_view_other_reports;

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
	'forms'     => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
	'layers'    => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
	'star'      => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
	'key'       => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
	'grid'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/>',
	'printer'   => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
	'mail'      => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/>',
	'palette'   => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.564C22 6.012 17.5 2 12 2z"/>',
];

// Build nav structure with sub-items
$nav = [];

$nav[] = [ 'slug' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'show' => true ];

$nav[] = [
	'slug'  => 'reports',
	'label' => 'Stores',
	'icon'  => 'clipboard',
	'show'  => $cap_reports_access,
	'children' => [
		[ 'slug' => 'reports-my',     'label' => 'My Reports',          'show' => in_array( 'submit_reports', $caps ) || in_array( 'view_own_reports', $caps ) ],
		[ 'slug' => 'reports-review', 'label' => 'GM Reports',          'action' => 'review-gm',  'show' => $cap_view_gm ],
		[ 'slug' => 'reports-review', 'label' => 'Supervisor Reports',  'action' => 'review-sup', 'show' => $cap_view_sup ],
		[ 'slug' => 'action-items',  'label' => 'Action Items',        'show' => $cap_reports_access ],
	],
];

$nav[] = [
	'slug'  => 'mileage',
	'label' => 'Expenses',
	'icon'  => 'car',
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

$nav[] = [ 'slug' => 'analytics', 'label' => 'Analytics', 'icon' => 'chart', 'show' => in_array( 'view_analytics', $caps ) ];

$nav[] = [ 'slug' => 'reviews', 'label' => 'Reviews', 'icon' => 'star', 'show' => in_array( 'view_reviews', $caps ) || in_array( 'manage_reviews', $caps ) ];

// Forms is gated on the 'view_forms' capability — and always visible in Odin (god) mode,
// independent of the capability catalog, so god never loses it.
$cap_view_forms = in_array( 'view_forms', $caps ) || ( $is_god && ! $impersonating && site_pulse_module_on( 'forms' ) );
$nav[] = [
	'slug'  => 'forms',
	'label' => 'Forms',
	'icon'  => 'forms',
	'show'  => $cap_view_forms,
	'children' => [
		[ 'slug' => 'forms-training', 'label' => 'Training', 'show' => $cap_view_forms ],
		[ 'slug' => 'forms-kitchen',  'label' => 'Kitchen',  'show' => $cap_view_forms ],
		[ 'slug' => 'forms-foh',      'label' => 'FOH',      'show' => $cap_view_forms ],
		[ 'slug' => 'forms-misc',     'label' => 'Misc',     'show' => $cap_view_forms ],
	],
];

$nav[] = [
	'slug'  => 'admin',
	'label' => 'Settings',
	'icon'  => 'settings',
	'show'  => in_array( 'manage_locations', $caps ) || in_array( 'manage_users', $caps ) || in_array( 'manage_settings', $caps ) || in_array( 'manage_mileage', $caps ),
	'children' => [
		[ 'slug' => 'admin-users',     'label' => 'Users',            'icon' => 'users',   'show' => in_array( 'manage_users', $caps ) ],
		[ 'slug' => 'admin-tiers',     'label' => 'Roles',            'icon' => 'layers',  'show' => in_array( 'manage_settings', $caps ) ],
		[ 'slug' => 'admin-locations', 'label' => 'Home Bases',       'icon' => 'mappin',  'show' => in_array( 'manage_locations', $caps ) ],
		[ 'slug' => 'admin-templates', 'label' => 'Report Settings',   'icon' => 'file',    'show' => in_array( 'manage_templates', $caps ) ],
		[ 'slug' => 'admin-mileage',   'label' => 'Mileage Settings', 'icon' => 'car',     'show' => in_array( 'manage_mileage', $caps ) ],
		[ 'slug' => 'admin-settings',  'label' => 'Site Settings',    'icon' => 'palette', 'show' => in_array( 'manage_settings', $caps ) ],
		[ 'slug' => 'admin-apikeys',   'label' => 'API Keys',         'icon' => 'key',     'show' => in_array( 'manage_settings', $caps ) ],
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
		$printPage .= '<div class="sp-nav-children">';
		foreach ( $item['children'] as $child ) {
			if ( empty( $child['show'] ) ) continue;
			$child_action = ! empty( $child['action'] ) ? ' data-action="' . esc_attr( $child['action'] ) . '"' : '';
			$printPage .= '<button type="button" class="unique sp-nav-child" data-nav="' . esc_attr( $child['slug'] ) . '"' . $child_action . '>';
			$printPage .=   '<span>' . esc_html( $child['label'] ) . '</span>';
			$printPage .= '</button>';
		}
		$printPage .= '</div>';
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
$printPage .=   '<div class="sp-mobile-user">' . $svg( $icons['user'] ) . '</div>';
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
	$printPage .= '<span class="sp-topbar-divider">&middot;</span>';
	$printPage .= '<span class="sp-topbar-role">' . $role_label . '</span>';
}
if ( $loc_name ) {
	$printPage .= '<span class="sp-topbar-divider">&middot;</span>';
	$printPage .= '<span class="sp-topbar-location">' . $loc_name . '</span>';
}
$printPage .=   '</div>';
$printPage .=   '<div class="sp-topbar-right">';
$printPage .=     '<button type="button" class="unique sp-topbar-btn sp-notification-btn" id="sp-notification-btn" aria-label="Notifications">';
$printPage .=       '<svg class="sp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
$printPage .=       '<span class="sp-notification-badge" id="sp-notification-badge" hidden>0</span>';
$printPage .=     '</button>';
$printPage .=     '<button type="button" class="unique sp-topbar-btn sp-undo-btn" id="sp-undo-btn" aria-label="Undo last delete" title="Undo last delete">';
$printPage .=       '<svg class="sp-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>';
$printPage .=       '<span>Undo</span>';
$printPage .=     '</button>';
$printPage .=     '<button type="button" class="unique sp-topbar-btn sp-logout-btn-top" id="sp-logout-btn-top">';
$printPage .=       $svg( $icons['logout'] );
$printPage .=       '<span>Sign Out</span>';
$printPage .=     '</button>';
$printPage .=   '</div>';
$printPage .= '</header>';

// Notification Panel
$printPage .= '<div class="sp-notification-panel" id="sp-notification-panel" hidden>';
$printPage .=   '<div class="sp-notification-header">';
$printPage .=     '<h3>Notifications</h3>';
$printPage .=     '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mark-all-read">Mark all read</button>';
$printPage .=   '</div>';
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
$printPage .=   '<div class="sp-dashboard-widgets">';

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

// My Reports Panel
if ( in_array( 'submit_reports', $caps ) || in_array( 'view_own_reports', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-my">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     $sp_crumb( 'Stores', 'My Reports' );
	if ( in_array( 'submit_reports', $caps ) ) {
		$printPage .= '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-new-report-btn">+ New Report</button>';
	}
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-report-filters">';
	$printPage .=     '<select id="sp-filter-template" class="sp-select"><option value="">All Report Types</option></select>';
	$printPage .=     '<select id="sp-filter-status" class="sp-select">';
	$printPage .=       '<option value="">All Statuses</option>';
	$printPage .=       '<option value="draft">Draft</option>';
	$printPage .=       '<option value="submitted">Submitted</option>';
	$printPage .=       '<option value="reviewed">Reviewed</option>';
	$printPage .=     '</select>';
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
	$printPage .=     '<select id="sp-review-filter-user" class="sp-select"><option value="">All Submitters</option></select>';
	$printPage .=     '<select id="sp-review-filter-status" class="sp-select">';
	$printPage .=       '<option value="">All Statuses</option>';
	$printPage .=       '<option value="submitted">Submitted</option>';
	$printPage .=       '<option value="reviewed">Reviewed</option>';
	$printPage .=     '</select>';
	$printPage .=     '<div class="sp-date-range"><input type="date" id="sp-review-filter-start" class="sp-input" placeholder="From">';
	$printPage .=     '<input type="date" id="sp-review-filter-end" class="sp-input" placeholder="To"></div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reports-list" id="sp-review-list"></div>';
	$printPage .=   '<div class="sp-report-detail-wrap" id="sp-review-detail-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Action Items Panel
if ( $cap_reports_access ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-action-items">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Stores', 'Action Items' ) . '</div>';
	$printPage .=   '<div class="sp-report-filters">';
	if ( $cap_view_other_reports ) {
		$printPage .= '<select id="sp-action-filter-location" class="sp-select"><option value="">All Locations</option></select>';
		$printPage .= '<select id="sp-action-filter-user" class="sp-select"><option value="">All Submitters</option></select>';
	}
	$printPage .=     '<select id="sp-action-filter-status" class="sp-select">';
	$printPage .=       '<option value="open">Open</option>';
	$printPage .=       '<option value="">All</option>';
	$printPage .=       '<option value="resolved">Resolved</option>';
	$printPage .=     '</select>';
	$printPage .=     '<select id="sp-action-sort" class="sp-select">';
	$printPage .=       '<option value="importance">Sort by Importance</option>';
	$printPage .=       '<option value="custom">Sort by Custom</option>';
	$printPage .=     '</select>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-action-items-list" id="sp-action-items-list"></div>';
	$printPage .= '</section>';
}

// Analytics Panel
if ( in_array( 'view_analytics', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-analytics">';
	$printPage .=   '<div class="sp-panel-header"><h2>Analytics</h2></div>';

	// AI Search — Coming Soon
	$printPage .=   '<div class="sp-analytics-search sp-coming-soon">';
	$printPage .=     '<div class="sp-coming-soon-badge"><span class="unique">Coming Soon</span></div>';
	$printPage .=     '<div class="sp-analytics-search-inner">';
	$printPage .=       '<input type="text" class="sp-input" placeholder="Ask a question about your reports..." disabled>';
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-primary" disabled>Submit</button>';
	$printPage .=     '</div>';
	$printPage .=     '<p class="sp-coming-soon-text">Natural language search across all reports</p>';
	$printPage .=   '</div>';

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

	// Mileage analytics (shown to mileage users)
	if ( in_array( 'submit_mileage', $caps ) || in_array( 'manage_mileage', $caps ) ) {
		$printPage .=   '<div class="sp-analytics-card" id="sp-analytics-mileage">';
		$printPage .=     '<h4>Business Mileage</h4>';
		$printPage .=     '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
		$printPage .=   '</div>';
		$printPage .=   '<div class="sp-analytics-card" id="sp-analytics-mileage-dest">';
		$printPage .=     '<h4>Top Destinations (YTD)</h4>';
		$printPage .=     '<div class="sp-analytics-card-body"><div class="sp-loading"></div></div>';
		$printPage .=   '</div>';
	}

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
	$printPage .=       '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-reviews-refresh-btn">Refresh</button>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reviews-summary" id="sp-reviews-summary"></div>';
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
	$printPage .= '</section>';
}

// Forms Panels — placeholders until each form set is built out. Gated on the same
// 'view_forms' capability as the Forms menu, so they're not reachable by direct hash.
$sp_forms_panels = $cap_view_forms ? [
	'forms-training' => 'Training',
	'forms-kitchen'  => 'Kitchen',
	'forms-foh'      => 'FOH',
	'forms-misc'     => 'Misc',
] : [];
foreach ( $sp_forms_panels as $sp_form_slug => $sp_form_label ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-' . esc_attr( $sp_form_slug ) . '">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Forms', $sp_form_label ) . '</div>';
	$printPage .=   '<div class="sp-coming-soon-panel">';
	$printPage .=     $svg( $icons['forms'] );
	$printPage .=     '<h3>Coming Soon</h3>';
	$printPage .=     '<p>' . esc_html( $sp_form_label ) . ' forms are on the way.</p>';
	$printPage .=   '</div>';
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
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'GM Report Settings' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-templates-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_settings', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-settings">';
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Site Settings' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-settings-content"></div>';
	$printPage .= '</section>';

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
	$printPage .=   '<div class="sp-panel-header">' . $sp_crumb( 'Settings', 'Mileage Settings' ) . '</div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-mileage-content"></div>';
	$printPage .= '</section>';
}

$printPage .= '</main>';
$printPage .= '</div>';

return $printPage;
