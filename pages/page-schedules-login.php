<?php
/* Battle Plan Web Design - CCSO Schedules Login Page */

// If already logged in with a schedules role, redirect appropriately
if ( is_user_logged_in() ) {
	$current_user = wp_get_current_user();
	$roles        = (array) $current_user->roles;

	if ( in_array('schedules_supervisor', $roles, true) ) {
		$printPage = '<script>window.location.href="' . esc_js( home_url('/schedules-supervisor/') ) . '";</script>';
		return $printPage;
	}

	if ( in_array('schedules_member', $roles, true) ) {
		$printPage = '<script>window.location.href="' . esc_js( home_url('/schedules-member/') ) . '";</script>';
		return $printPage;
	}
}

$nonce_field = wp_nonce_field( 'schedules_nonce', 'schedules_nonce_field', true, false );

$printPage  = '';
$printPage .= '<div id="schedules-login-wrap">';
$printPage .=   '<div class="schedules-login-box">';

// Header
$printPage .=     '<div class="schedules-login-header">';
$printPage .=       '<div class="schedules-badge-icon">';
$printPage .=         '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64" aria-hidden="true" focusable="false">';
$printPage .=           '<path fill="#c9a227" d="M32 4L8 16v16c0 13.3 10.3 25.8 24 29 13.7-3.2 24-15.7 24-29V16L32 4z"/>';
$printPage .=           '<path fill="#1a2744" d="M32 12l-18 9v11c0 9.8 7.6 19 18 21.4C42.4 51 50 41.8 50 32V21L32 12z"/>';
$printPage .=           '<text x="32" y="38" text-anchor="middle" fill="#c9a227" font-size="16" font-weight="bold" font-family="sans-serif">CCSO</text>';
$printPage .=         '</svg>';
$printPage .=       '</div>';
$printPage .=       '<h1>CCSO Scheduling</h1>';
$printPage .=       '<p>Collier County Sheriff\'s Office</p>';
$printPage .=     '</div>';

// Login Form
$printPage .=     '<form id="schedules-login-form" novalidate autocomplete="on">';
$printPage .=       $nonce_field;

$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="schedules-badge">Badge Number</label>';
$printPage .=         '<input type="text" id="schedules-badge" name="badge" autocomplete="username" inputmode="numeric" required placeholder="Enter your badge #">';
$printPage .=       '</div>';

$printPage .=       '<div class="form-group form-group-password">';
$printPage .=         '<label for="schedules-password">Password</label>';
$printPage .=         '<div class="password-input-wrap">';
$printPage .=           '<input type="password" id="schedules-password" name="password" autocomplete="current-password" required placeholder="Enter your password">';
$printPage .=           '<button type="button" class="toggle-password" aria-label="Show password" tabindex="0">';
$printPage .=             '<svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
$printPage .=               '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>';
$printPage .=               '<circle cx="12" cy="12" r="3"/>';
$printPage .=             '</svg>';
$printPage .=             '<svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>';
$printPage .=               '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>';
$printPage .=               '<line x1="1" y1="1" x2="23" y2="23"/>';
$printPage .=             '</svg>';
$printPage .=           '</button>';
$printPage .=         '</div>';
$printPage .=       '</div>';

$printPage .=       '<div class="form-error" id="schedules-login-error" role="alert" aria-live="polite"></div>';

$printPage .=       '<button type="submit" class="schedules-btn schedules-btn-primary schedules-btn-full" id="schedules-login-submit">';
$printPage .=         '<span class="btn-text">Sign In</span>';
$printPage .=         '<span class="btn-loading" hidden aria-hidden="true">';
$printPage .=           '<svg class="spin-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">';
$printPage .=             '<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>';
$printPage .=           '</svg>';
$printPage .=           ' Signing in...';
$printPage .=         '</span>';
$printPage .=       '</button>';

$printPage .=     '</form>';
$printPage .=   '</div>'; // .schedules-login-box
$printPage .= '</div>'; // #schedules-login-wrap

return do_shortcode( $printPage );
