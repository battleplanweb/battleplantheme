<?php
/* Battle Plan Web Design — Home Base staff dashboard shell

   Rendered into the `home-base-admin` universal page. Staff are WP users, so the
   PHP already knows whether they're authorized: show the branded login when not,
   the dashboard shell when they are. js/script-home-base-admin.js drives it via
   home-base/v1/admin REST (WP cookie + wp_rest nonce). */

if ( ! function_exists( 'hb_admin_can' ) ) return '';

$app_name = hb_app_name();
$company  = hb_company_name();
$accent   = (string) hb_get( 'theme_color', '#0f766e' );
$icon_url = function_exists( 'hb_pwa_source_url' ) ? hb_pwa_source_url() : '';

$printPage  = '<style id="hb-accent">:root{--hb-accent:' . esc_html( $accent ) . ';}</style>';
$printPage .= '<div id="home-base-admin" class="hb-admin">';

if ( ! hb_admin_can() ) {

	// --- Login ---
	$printPage .= '<div class="hb-screen hb-admin-login">';
	$printPage .=   '<div class="hb-auth-card">';
	$printPage .=     '<div class="hb-auth-header">';
	if ( $icon_url ) $printPage .= '<img src="' . esc_url( $icon_url ) . '" alt="" class="hb-auth-icon" width="64" height="64">';
	$printPage .=       '<h1 class="hb-auth-title">' . esc_html( $app_name ) . '</h1>';
	$printPage .=       '<p class="hb-auth-sub">Staff sign-in</p>';
	$printPage .=     '</div>';
	$printPage .=     '<form class="hb-form hb-admin-login-form" novalidate autocomplete="on">';
	$printPage .=       '<label class="hb-field"><span>Username or email</span>';
	$printPage .=         '<input type="text" name="log" autocomplete="username" required></label>';
	$printPage .=       '<label class="hb-field"><span>Password</span>';
	$printPage .=         '<input type="password" name="pwd" autocomplete="current-password" required></label>';
	$printPage .=       '<button type="submit" class="hb-btn hb-btn-primary">Sign in</button>';
	$printPage .=       '<p class="hb-error" role="alert" hidden></p>';
	$printPage .=     '</form>';
	$printPage .=   '</div>';
	$printPage .= '</div>';

} else {

	// --- Dashboard: shared Battle Plan admin shell (.sp-* design system) ---
	$current = wp_get_current_user();
	$logout  = wp_logout_url( home_url( '/' . HOME_BASE_ADMIN_SLUG . '/' ) );

	// Brand the shared admin UI from the 3 colors in the child theme's
	// style-site.css (--hb-primary / --hb-secondary / --hb-accent). Not an AI
	// color engine — the site's own brand CSS is the source of truth.
	$printPage .= hb_admin_color_css();

	// Mobile header (hamburger) + overlay.
	$printPage .= '<header class="sp-mobile-header" id="hb-mobile-header">';
	$printPage .=   '<div class="sp-mobile-header-bar">';
	$printPage .=     '<button type="button" class="sp-hamburger" id="hb-burger" aria-label="Menu">' . hb_admin_icon( 'menu' ) . '</button>';
	$printPage .=     '<span class="sp-mobile-header-title" data-slot="view-title-m">' . esc_html( $app_name ) . '</span>';
	$printPage .=   '</div>';
	$printPage .= '</header>';
	$printPage .= '<div class="sp-overlay" id="hb-overlay"></div>';

	$printPage .= '<div id="sp-app">';

	// Sidebar
	$printPage .=   '<aside class="sp-sidebar" id="hb-sidebar">';
	$printPage .=     '<div class="sp-sidebar-header">';
	$printPage .=       '<div class="sp-sidebar-brand">';
	if ( $icon_url ) $printPage .= '<img src="' . esc_url( $icon_url ) . '" alt="" class="sp-sidebar-logo" style="max-height:52px" width="52" height="52">';
	$printPage .=         '<span class="sp-sidebar-title">' . esc_html( $app_name ) . '</span>';
	$printPage .=         '<span class="sp-sidebar-sub">' . esc_html( $company ) . '</span>';
	$printPage .=       '</div>';
	$printPage .=     '</div>';
	$printPage .=     '<div class="sp-sidebar-actions">';
	$printPage .=       '<a class="sp-topbar-btn" href="' . esc_url( $logout ) . '">' . hb_admin_icon( 'signout' ) . '<span>Sign out · ' . esc_html( $current->display_name ) . '</span></a>';
	$printPage .=     '</div>';
	$printPage .=     '<nav class="sp-sidebar-nav" aria-label="Main">';
	$printPage .=       '<button type="button" class="sp-nav-item active" data-view="send">' . hb_admin_icon( 'send' ) . '<span>Send a notification</span></button>';
	$printPage .=       '<button type="button" class="sp-nav-item" data-view="customers">' . hb_admin_icon( 'customers' ) . '<span>Customers</span></button>';
	$printPage .=     '</nav>';
	$printPage .=   '</aside>';

	// Main
	$printPage .=   '<main class="sp-main" id="hb-main">';
	$printPage .=     '<div class="sp-header-grid sp-panel-header"><h2 data-slot="view-title">Send a notification</h2></div>';
	$printPage .=     '<div data-slot="view"></div>';
	$printPage .=   '</main>';

	$printPage .= '</div>';
}

$printPage .= '</div>';

return $printPage;
