<?php
/* Battle Plan Web Design - Site Pulse Login Page */

if ( is_user_logged_in() ) {
	$current_user = wp_get_current_user();
	$is_wp_admin  = in_array( 'administrator', (array) $current_user->roles, true );
	$profile      = site_pulse_get_user_profile( get_current_user_id() );
	if ( $is_wp_admin || ( $profile && $profile['status'] === 'active' ) ) {
		$printPage = '<script>window.location.href="' . esc_js( home_url('/site-pulse-dashboard/') ) . '";</script>';
		return $printPage;
	}
}

$nonce_field = wp_nonce_field( 'site_pulse_nonce', 'site_pulse_nonce_field', true, false );
$app_name    = site_pulse_get_setting( 'app_name', 'Site Pulse' );
$company     = site_pulse_get_setting( 'company_name', '' );
$logo_url    = site_pulse_get_setting( 'login_logo_url', '' );

$printPage  = '';
$printPage .= '<div id="sp-login-wrap">';
$printPage .=   '<div class="sp-login-box">';

$printPage .=     '<div class="sp-login-header">';
if ( $logo_url ) {
	$printPage .=     '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $app_name ) . '" class="sp-login-logo">';
} else {
	$printPage .=     '<div class="sp-login-icon">';
	$printPage .=       '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64" aria-hidden="true" focusable="false">';
	$printPage .=         '<circle cx="32" cy="32" r="28" fill="var(--sp-primary, #2563eb)" opacity="0.1"/>';
	$printPage .=         '<path fill="var(--sp-primary, #2563eb)" d="M32 18c-2.2 0-4 1.8-4 4v2h-2c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V26c0-1.1-.9-2-2-2h-2v-2c0-2.2-1.8-4-4-4zm0 3c.6 0 1 .4 1 1v2h-2v-2c0-.6.4-1 1-1zm0 9c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>';
	$printPage .=       '</svg>';
	$printPage .=     '</div>';
}
$printPage .=     '<h1>' . esc_html( $app_name ) . '</h1>';
if ( $company ) {
	$printPage .=   '<p>' . esc_html( $company ) . '</p>';
}
$printPage .=     '</div>';

$printPage .=     '<form id="sp-login-form" novalidate autocomplete="on">';
$printPage .=       $nonce_field;

$printPage .=       '<div class="sp-form-group">';
$printPage .=         '<label for="sp-username">Username</label>';
$printPage .=         '<input type="text" id="sp-username" name="username" autocomplete="username" required placeholder="Enter your username">';
$printPage .=       '</div>';

$printPage .=       '<div class="sp-form-group sp-form-group-password">';
$printPage .=         '<label for="sp-password">Password</label>';
$printPage .=         '<div class="sp-password-input-wrap">';
$printPage .=           '<input type="password" id="sp-password" name="password" autocomplete="current-password" required placeholder="Enter your password">';
$printPage .=           '<button type="button" class="unique sp-toggle-password" aria-label="Show password" tabindex="0">';
$printPage .=             '<svg class="sp-eye-icon sp-eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
$printPage .=               '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>';
$printPage .=               '<circle cx="12" cy="12" r="3"/>';
$printPage .=             '</svg>';
$printPage .=             '<svg class="sp-eye-icon sp-eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>';
$printPage .=               '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>';
$printPage .=               '<line x1="1" y1="1" x2="23" y2="23"/>';
$printPage .=             '</svg>';
$printPage .=           '</button>';
$printPage .=         '</div>';
$printPage .=       '</div>';

$printPage .=       '<div class="sp-form-error" id="sp-login-error" role="alert" aria-live="polite"></div>';

$printPage .=       '<button type="submit" class="unique sp-btn sp-btn-primary sp-btn-full" id="sp-login-submit">';
$printPage .=         '<span class="btn-text">Sign In</span>';
$printPage .=         '<span class="btn-loading" hidden aria-hidden="true">';
$printPage .=           '<svg class="sp-spin-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">';
$printPage .=             '<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>';
$printPage .=           '</svg>';
$printPage .=           ' Signing in...';
$printPage .=         '</span>';
$printPage .=       '</button>';

$printPage .=     '</form>';
$printPage .=   '</div>';
$printPage .= '</div>';

return $printPage;
