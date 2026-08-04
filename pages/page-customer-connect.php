<?php
/* Battle Plan Web Design — Customer Connect app shell (public customer PWA)

   Returned into the `customer-connect` universal page. This is only the SHELL: a root
   node + screen scaffolding that js/script-customer-connect.js drives as a client-side
   SPA against the customer-connect/v1 REST API. Auth is a device-stored bearer token
   (localStorage), so the page itself is public — the JS decides whether to show
   the sign-in screen or the app.

   White-label: Customer Connect is a standalone, standardized app (native OS fonts, white
   surfaces, greyscale text) tinted only by the SITE PULSE color scheme. Its brand tones come
   from cc_brand_css() (--cc-brand / --cc-brand-2), emitted as inline :root CSS on the
   'customer-connect' style handle — there is no per-app color config.
*/

if ( ! function_exists( 'cc_app_name' ) ) return ''; // module off — nothing to render

$app_name = cc_app_name();
$company  = cc_company_name();
$icon_url = function_exists( 'cc_pwa_source_url' ) ? cc_pwa_source_url() : '';

$printPage  = '';

$printPage .= '<div id="customer-connect-app" data-app-name="' . esc_attr( $app_name ) . '" aria-live="polite">';

// Boot splash (JS swaps this out once /me resolves).
$printPage .=   '<div class="cc-screen cc-boot" data-screen="boot">';
$printPage .=     '<div class="cc-boot-inner">';
if ( $icon_url ) {
	$printPage .=   '<img src="' . esc_url( $icon_url ) . '" alt="" class="cc-boot-icon" width="72" height="72">';
}
$printPage .=       '<div class="cc-spinner" role="status" aria-label="Loading"></div>';
$printPage .=     '</div>';
$printPage .=   '</div>';

// --- Sign-in screen (enter phone/email → OTP) ---
$printPage .=   '<div class="cc-screen" data-screen="signin" hidden>';
$printPage .=     '<div class="cc-auth-card">';
$printPage .=       '<div class="cc-auth-header">';
if ( $icon_url ) {
	$printPage .=     '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $app_name ) . '" class="cc-auth-icon" width="64" height="64">';
}
$printPage .=         '<h1 class="cc-auth-title">' . esc_html( $app_name ) . '</h1>';
if ( $company ) $printPage .= '<p class="cc-auth-sub">' . esc_html( $company ) . '</p>';
$printPage .=       '</div>';

// Step 1: identifier — EMAIL only for now (SMS/mobile sign-in deferred until Twilio is wired per client).
$printPage .=       '<form class="cc-form" data-step="identify" novalidate>';
$printPage .=         '<p class="cc-form-lead">Enter your email to sign in. We\'ll send you a one-time code.</p>';
$printPage .=         '<label class="cc-field"><span>Email</span>';
$printPage .=           '<input type="email" name="email" inputmode="email" autocomplete="email" placeholder="you@example.com" required>';
$printPage .=         '</label>';
$printPage .=         '<button type="submit" class="cc-btn cc-btn-primary cc-btn-lg">Send code</button>';
$printPage .=         '<p class="cc-error" role="alert" hidden></p>';
// Mobile/SMS sign-in — re-enable once Twilio is wired for the client:
// $printPage .=      '<button type="button" class="cc-btn cc-alt-toggle" data-target="phone">Use mobile number instead</button>';
// $printPage .=      '<label class="cc-field" data-field="phone" hidden><span>Mobile number</span><input type="tel" name="phone" inputmode="tel" autocomplete="tel" placeholder="(555) 555-5555"></label>';
$printPage .=       '</form>';

// Step 2: code
$printPage .=       '<form class="cc-form" data-step="code" hidden novalidate>';
$printPage .=         '<p class="cc-form-lead">Enter the 6-digit code we sent to <strong data-slot="sent-to"></strong>.</p>';
$printPage .=         '<div class="cc-code-boxes" data-slot="code-boxes" role="group" aria-label="Verification code">';
for ( $cc_i = 0; $cc_i < 6; $cc_i++ ) {
	$printPage .=       '<input class="cc-code-box" type="text" inputmode="numeric" autocomplete="' . ( 0 === $cc_i ? 'one-time-code' : 'off' ) . '" maxlength="1" aria-label="Digit ' . ( $cc_i + 1 ) . '">';
}
$printPage .=         '</div>';
$printPage .=         '<p class="cc-code-timer" data-slot="timer" hidden>Code expires in <strong data-slot="countdown">10:00</strong></p>';
$printPage .=         '<button type="submit" class="cc-btn cc-btn-primary cc-btn-lg">Verify code</button>';
$printPage .=         '<button type="button" class="cc-btn cc-link" data-action="resend">Resend code</button>';
$printPage .=         '<button type="button" class="cc-btn cc-link" data-action="back">Use a different email</button>';
$printPage .=         '<p class="cc-error" role="alert" hidden></p>';
$printPage .=       '</form>';

$printPage .=     '</div>'; // .cc-auth-card
$printPage .=   '</div>'; // signin

// Inline SVG icon (Feather-style, currentColor stroke) — the app is isolated from the site's icon
// system, so icons ship here directly.
$ccIcon = function ( $body ) {
	return '<svg class="cc-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
};

// --- App screen (left drawer menu + views; JS renders view content) ---
$printPage .=   '<div class="cc-screen cc-app" data-screen="app" hidden>';
$printPage .=     '<header class="cc-appbar">';
$printPage .=       '<button type="button" class="cc-appbar-btn cc-appbar-hamburger" data-action="menu" aria-label="Menu">' . $ccIcon( '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>' ) . '</button>';
$printPage .=       '<span class="cc-appbar-title" data-slot="view-title">Home</span>';
$printPage .=       '<div class="cc-appbar-actions">';
$printPage .=         '<button type="button" class="cc-appbar-btn cc-appbar-bell" data-action="notifications" aria-label="Notifications">' . $ccIcon( '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>' ) . '<span class="cc-badge" data-slot="unread" hidden>0</span></button>';
$printPage .=       '</div>';
$printPage .=     '</header>';
$printPage .=     '<div class="cc-scrim" data-action="close-menu"></div>';
$printPage .=     '<nav class="cc-sidebar" aria-label="Main">';
$printPage .=       '<div class="cc-sidebar-head">';
if ( $icon_url ) {
	$printPage .=     '<img src="' . esc_url( $icon_url ) . '" alt="" class="cc-sidebar-logo" width="30" height="30">';
}
$printPage .=         '<span class="cc-sidebar-app">' . esc_html( $app_name ) . '</span>';
$printPage .=       '</div>';
$printPage .=       '<button type="button" class="cc-tab is-active" data-view="home">' . $ccIcon( '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>' ) . '<span class="cc-tab-label">Home</span></button>';
$printPage .=       '<button type="button" class="cc-tab" data-view="equipment">' . $ccIcon( '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.1-3.1a6 6 0 0 1-7.9 7.9l-6.3 6.3a2.1 2.1 0 0 1-3-3l6.3-6.3a6 6 0 0 1 7.9-7.9z"/>' ) . '<span class="cc-tab-label">My Home</span></button>';
$printPage .=       '<button type="button" class="cc-tab" data-view="schedule">' . $ccIcon( '<rect x="3" y="4" width="18" height="17" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>' ) . '<span class="cc-tab-label">Schedule</span></button>';
$printPage .=       '<button type="button" class="cc-tab" data-view="help">' . $ccIcon( '<circle cx="12" cy="12" r="10"/><path d="M9.1 9.2a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4"/><line x1="12" y1="17.5" x2="12" y2="17.5"/>' ) . '<span class="cc-tab-label">Help</span></button>';
$printPage .=       '<span class="cc-tab-spacer"></span>';
$printPage .=       '<button type="button" class="cc-tab cc-tab-account" data-view="account">' . $ccIcon( '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>' ) . '<span class="cc-tab-label">Account</span></button>';
$printPage .=     '</nav>';
$printPage .=     '<main class="cc-view" data-slot="view"></main>';
$printPage .=   '</div>'; // app

$printPage .= '</div>'; // #customer-connect-app

return $printPage;
