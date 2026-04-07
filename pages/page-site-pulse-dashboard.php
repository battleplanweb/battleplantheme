<?php
/* Battle Plan Web Design - Site Pulse Dashboard */

if ( ! is_user_logged_in() ) {
	$printPage = '<script>window.location.href="' . esc_js( home_url('/site-pulse-login/') ) . '";</script>';
	return $printPage;
}

$real_user_id = get_current_user_id();
$is_god       = site_pulse_is_god( $real_user_id );
$impersonating = site_pulse_is_impersonating();
$user_id      = site_pulse_effective_user_id();
$user         = get_userdata( $user_id );
$profile      = site_pulse_get_user_profile( $user_id );
$role         = $profile ? site_pulse_get_role( $profile['role_id'] ) : null;
$location     = $profile ? site_pulse_get_location( (int) $profile['location_id'] ) : null;

$is_wp_admin = in_array( 'administrator', (array) get_userdata( $real_user_id )->roles, true );
if ( $is_god && ! $impersonating ) {
	$caps = [ 'view_all_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'view_ai_insights', 'submit_reports', 'view_own_reports', 'view_team_reports', 'review_reports', 'god_mode' ];
	$role_label = 'God';
} elseif ( $is_wp_admin && ! $role ) {
	$caps = [ 'view_all_reports', 'manage_locations', 'manage_users', 'manage_templates', 'manage_roles', 'view_analytics', 'manage_settings', 'view_ai_insights', 'submit_reports', 'view_own_reports' ];
	$role_label = 'Administrator';
} else {
	$caps = $role ? ( json_decode( $role['capabilities'], true ) ?: [] ) : [];
	$role_label = $role ? esc_html( $role['label'] ) : '';
}

$app_name     = site_pulse_get_setting( 'app_name', 'Site Pulse' );
$company_name = site_pulse_get_setting( 'company_name', '' );
$logo_url     = site_pulse_get_setting( 'login_logo_url', '' );
$display_name = $user ? esc_html( $user->first_name ?: $user->display_name ) : 'User';
$loc_name     = $location ? esc_html( $location['name'] ) : '';

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
];

// Build nav structure with sub-items
$nav = [];

$nav[] = [ 'slug' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'show' => true ];

$nav[] = [
	'slug'  => 'reports',
	'label' => 'Reports',
	'icon'  => 'clipboard',
	'show'  => in_array( 'submit_reports', $caps ) || in_array( 'view_own_reports', $caps ) || in_array( 'view_all_reports', $caps ) || in_array( 'view_team_reports', $caps ),
	'children' => [
		[ 'slug' => 'reports-my',     'label' => 'My Reports',      'show' => in_array( 'submit_reports', $caps ) || in_array( 'view_own_reports', $caps ) ],
		[ 'slug' => 'reports-review', 'label' => 'Review Reports',  'show' => in_array( 'view_team_reports', $caps ) || in_array( 'view_all_reports', $caps ) ],
	],
];

$nav[] = [ 'slug' => 'action-items', 'label' => 'Action Items', 'icon' => 'checklist', 'show' => in_array( 'submit_reports', $caps ) || in_array( 'view_own_reports', $caps ) || in_array( 'view_team_reports', $caps ) || in_array( 'view_all_reports', $caps ) ];

$nav[] = [ 'slug' => 'analytics', 'label' => 'Analytics', 'icon' => 'chart', 'show' => in_array( 'view_analytics', $caps ) ];

$nav[] = [
	'slug'  => 'admin',
	'label' => 'Admin',
	'icon'  => 'settings',
	'show'  => in_array( 'manage_locations', $caps ) || in_array( 'manage_users', $caps ) || in_array( 'manage_settings', $caps ),
	'children' => [
		[ 'slug' => 'admin-users',     'label' => 'Users',            'icon' => 'users',   'show' => in_array( 'manage_users', $caps ) ],
		[ 'slug' => 'admin-locations', 'label' => 'Locations',        'icon' => 'mappin',  'show' => in_array( 'manage_locations', $caps ) ],
		[ 'slug' => 'admin-templates', 'label' => 'Report Templates', 'icon' => 'file',    'show' => in_array( 'manage_templates', $caps ) ],
		[ 'slug' => 'admin-settings',  'label' => 'Settings',         'icon' => 'sliders', 'show' => in_array( 'manage_settings', $caps ) ],
	],
];


$printPage  = '';
$printPage .= $nonce_field;
$printPage .= '<div id="sp-app">';

// God Mode — Impersonation Bar
if ( $is_god ) {
	$all_users = site_pulse_get_all_users( true, false );
	$printPage .= '<div class="sp-god-bar" id="sp-god-bar">';
	$printPage .=   '<span class="sp-god-label">God Mode</span>';
	$printPage .=   '<span class="sp-god-viewing">Viewing as:</span>';
	$printPage .=   '<select class="sp-god-select" id="sp-god-user-select">';
	$printPage .=     '<option value="0"' . ( ! $impersonating ? ' selected' : '' ) . '>Myself (God)</option>';
	foreach ( $all_users as $u ) {
		$selected = $impersonating && (int) $u['user_id'] === $user_id ? ' selected' : '';
		$printPage .= '<option value="' . (int) $u['user_id'] . '"' . $selected . '>' . esc_html( $u['display_name'] ) . ' — ' . esc_html( $u['role_label'] ?? '' ) . '</option>';
	}
	$printPage .=   '</select>';
	if ( $impersonating ) {
		$printPage .= '<button type="button" class="unique sp-btn sp-btn-ghost sp-god-reset" id="sp-god-reset">Reset</button>';
	}
	$printPage .= '<button type="button" class="unique sp-btn sp-btn-ghost sp-god-nuke" id="sp-god-nuke" style="color:#f87171;">Clear Test Data</button>';
	$printPage .= '</div>';
}

// Sidebar
$printPage .= '<aside class="sp-sidebar" id="sp-sidebar">';

// Sidebar Header — Logo / App Name
$printPage .=   '<div class="sp-sidebar-header">';
$printPage .=     '<div class="sp-sidebar-brand">';
$printPage .=       $svg( $icons['pulse'] );
$printPage .=       '<span class="sp-sidebar-title">' . esc_html( $app_name ) . '</span>';
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
			$printPage .= '<button type="button" class="unique sp-nav-child" data-nav="' . esc_attr( $child['slug'] ) . '">';
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
$printPage .=   '<span class="sp-mobile-title">' . esc_html( $app_name ) . '</span>';
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
if ( $role_label && $role_label !== 'God' ) {
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

// Dashboard Panel
$printPage .= '<section class="sp-panel active" id="sp-panel-dashboard">';
$printPage .=   '<div class="sp-panel-header"><h2>Dashboard</h2></div>';
$printPage .=   '<div class="sp-dashboard-widgets">';

// Reports Widget
if ( in_array( 'submit_reports', $caps ) || in_array( 'view_own_reports', $caps ) || in_array( 'view_team_reports', $caps ) || in_array( 'view_all_reports', $caps ) ) {
	$printPage .= '<div class="sp-widget" id="sp-widget-reports">';
	$printPage .=   '<div class="sp-widget-header">';
	$printPage .=     '<h3>Recent Reports</h3>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="' . ( in_array( 'submit_reports', $caps ) ? 'reports-my' : 'reports-review' ) . '">View All &rarr;</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-widget-body" id="sp-widget-reports-body"><div class="sp-loading"></div></div>';
	$printPage .= '</div>';
}

// Action Items Widget
if ( in_array( 'submit_reports', $caps ) || in_array( 'view_own_reports', $caps ) || in_array( 'view_team_reports', $caps ) || in_array( 'view_all_reports', $caps ) ) {
	$printPage .= '<div class="sp-widget" id="sp-widget-actions">';
	$printPage .=   '<div class="sp-widget-header">';
	$printPage .=     '<h3>Open Action Items</h3>';
	$printPage .=     '<button type="button" class="unique sp-btn sp-btn-ghost sp-widget-link" data-nav="action-items">View All &rarr;</button>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-widget-body" id="sp-widget-actions-body"><div class="sp-loading"></div></div>';
	$printPage .= '</div>';
}

$printPage .=   '</div>';
$printPage .= '</section>';

// My Reports Panel
if ( in_array( 'submit_reports', $caps ) || in_array( 'view_own_reports', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-my">';
	$printPage .=   '<div class="sp-panel-header">';
	$printPage .=     '<h2>My Reports</h2>';
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
	$printPage .=     '<input type="date" id="sp-filter-date-start" class="sp-input" placeholder="From">';
	$printPage .=     '<input type="date" id="sp-filter-date-end" class="sp-input" placeholder="To">';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reports-list" id="sp-reports-list"></div>';
	$printPage .=   '<div class="sp-report-form-wrap" id="sp-report-form-wrap" hidden></div>';
	$printPage .=   '<div class="sp-report-detail-wrap" id="sp-report-detail-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Review Reports Panel
if ( in_array( 'view_team_reports', $caps ) || in_array( 'view_all_reports', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-reports-review">';
	$printPage .=   '<div class="sp-panel-header"><h2>Review Reports</h2></div>';
	$printPage .=   '<div class="sp-report-filters">';
	$printPage .= '<select id="sp-review-filter-location" class="sp-select"><option value="">All Locations</option></select>';
	$printPage .=     '<select id="sp-review-filter-user" class="sp-select"><option value="">All Managers</option></select>';
	$printPage .=     '<select id="sp-review-filter-status" class="sp-select">';
	$printPage .=       '<option value="">All Statuses</option>';
	$printPage .=       '<option value="submitted">Submitted</option>';
	$printPage .=       '<option value="reviewed">Reviewed</option>';
	$printPage .=     '</select>';
	$printPage .=     '<input type="date" id="sp-review-filter-start" class="sp-input" placeholder="From">';
	$printPage .=     '<input type="date" id="sp-review-filter-end" class="sp-input" placeholder="To">';
	$printPage .=   '</div>';
	$printPage .=   '<div class="sp-reports-list" id="sp-review-list"></div>';
	$printPage .=   '<div class="sp-report-detail-wrap" id="sp-review-detail-wrap" hidden></div>';
	$printPage .= '</section>';
}

// Action Items Panel
if ( in_array( 'submit_reports', $caps ) || in_array( 'view_own_reports', $caps ) || in_array( 'view_team_reports', $caps ) || in_array( 'view_all_reports', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-action-items">';
	$printPage .=   '<div class="sp-panel-header"><h2>Action Items</h2></div>';
	$printPage .=   '<div class="sp-report-filters">';
	if ( in_array( 'view_team_reports', $caps ) || in_array( 'view_all_reports', $caps ) ) {
		$printPage .= '<select id="sp-action-filter-location" class="sp-select"><option value="">All Locations</option></select>';
		$printPage .= '<select id="sp-action-filter-user" class="sp-select"><option value="">All Managers</option></select>';
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
	if ( in_array( 'view_team_reports', $caps ) || in_array( 'view_all_reports', $caps ) ) {
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

	$printPage .=   '</div>';
	$printPage .= '</section>';
}

// Admin Panels
if ( in_array( 'manage_users', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-users">';
	$printPage .=   '<div class="sp-panel-header"><h2>Users</h2></div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-users-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_locations', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-locations">';
	$printPage .=   '<div class="sp-panel-header"><h2>Locations</h2></div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-locations-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_templates', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-templates">';
	$printPage .=   '<div class="sp-panel-header"><h2>Report Templates</h2></div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-templates-content"></div>';
	$printPage .= '</section>';
}
if ( in_array( 'manage_settings', $caps ) ) {
	$printPage .= '<section class="sp-panel" id="sp-panel-admin-settings">';
	$printPage .=   '<div class="sp-panel-header"><h2>Settings</h2></div>';
	$printPage .=   '<div class="sp-admin-content" id="sp-admin-settings-content"></div>';
	$printPage .= '</section>';
}

$printPage .= '</main>';
$printPage .= '</div>';

return $printPage;
