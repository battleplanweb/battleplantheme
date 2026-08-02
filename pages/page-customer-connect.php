<?php
/* Battle Plan Web Design — Customer Connect app shell (public customer PWA)

   Returned into the `customer-connect` universal page. This is only the SHELL: a root
   node + screen scaffolding that js/script-customer-connect.js drives as a client-side
   SPA against the customer-connect/v1 REST API. Auth is a device-stored bearer token
   (localStorage), so the page itself is public — the JS decides whether to show
   the sign-in screen or the app.

   White-label: Customer Connect keeps the client's site branding (fonts + palette from
   the child theme's style-site.css). The accent below comes from the `customer_connect`
   config `theme_color`, so each client tints the app without any code change.
*/

if ( ! function_exists( 'cc_app_name' ) ) return ''; // module off — nothing to render

$app_name = cc_app_name();
$company  = cc_company_name();
$accent   = (string) cc_get( 'theme_color', '#0f766e' );
$icon_url = function_exists( 'cc_pwa_source_url' ) ? cc_pwa_source_url() : '';

$printPage  = '';

// Per-client accent injected inline so the compiled CSS stays generic.
$printPage .= '<style id="cc-accent">:root{--cc-accent:' . esc_html( $accent ) . ';}</style>';

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

// Step 1: identifier
$printPage .=       '<form class="cc-form" data-step="identify" novalidate>';
$printPage .=         '<p class="cc-form-lead">Enter your mobile number to sign in. We\'ll text you a one-time code.</p>';
$printPage .=         '<label class="cc-field"><span>Mobile number</span>';
$printPage .=           '<input type="tel" name="phone" inputmode="tel" autocomplete="tel" placeholder="(555) 555-5555">';
$printPage .=         '</label>';
$printPage .=         '<button type="button" class="cc-btn cc-alt-toggle" data-target="email">Use email instead</button>';
$printPage .=         '<label class="cc-field" data-field="email" hidden><span>Email</span>';
$printPage .=           '<input type="email" name="email" autocomplete="email" placeholder="you@example.com">';
$printPage .=         '</label>';
$printPage .=         '<button type="submit" class="cc-btn cc-btn-primary">Send code</button>';
$printPage .=         '<p class="cc-error" role="alert" hidden></p>';
$printPage .=       '</form>';

// Step 2: code
$printPage .=       '<form class="cc-form" data-step="code" hidden novalidate>';
$printPage .=         '<p class="cc-form-lead">Enter the 6-digit code we sent to <strong data-slot="sent-to"></strong>.</p>';
$printPage .=         '<label class="cc-field"><span>Verification code</span>';
$printPage .=           '<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000">';
$printPage .=         '</label>';
$printPage .=         '<button type="submit" class="cc-btn cc-btn-primary">Verify &amp; continue</button>';
$printPage .=         '<button type="button" class="cc-btn cc-link" data-action="resend">Resend code</button>';
$printPage .=         '<button type="button" class="cc-btn cc-link" data-action="back">Use a different number</button>';
$printPage .=         '<p class="cc-error" role="alert" hidden></p>';
$printPage .=       '</form>';

$printPage .=     '</div>'; // .cc-auth-card
$printPage .=   '</div>'; // signin

// --- App screen (tab bar + views; JS renders view content) ---
$printPage .=   '<div class="cc-screen cc-app" data-screen="app" hidden>';
$printPage .=     '<header class="cc-appbar">';
$printPage .=       '<span class="cc-appbar-title" data-slot="view-title">Home</span>';
$printPage .=       '<div class="cc-appbar-actions">';
$printPage .=         '<button type="button" class="cc-appbar-bell" data-action="notifications" aria-label="Notifications"><span class="cc-badge" data-slot="unread" hidden>0</span></button>';
$printPage .=         '<button type="button" class="cc-appbar-menu" data-action="account" aria-label="Account"></button>';
$printPage .=       '</div>';
$printPage .=     '</header>';
$printPage .=     '<main class="cc-view" data-slot="view"></main>';
$printPage .=     '<nav class="cc-tabbar" aria-label="Main">';
$printPage .=       '<button type="button" class="cc-tab is-active" data-view="home"><span class="cc-tab-label">Home</span></button>';
$printPage .=       '<button type="button" class="cc-tab" data-view="equipment"><span class="cc-tab-label">My Home</span></button>';
$printPage .=       '<button type="button" class="cc-tab" data-view="schedule"><span class="cc-tab-label">Schedule</span></button>';
$printPage .=       '<button type="button" class="cc-tab" data-view="help"><span class="cc-tab-label">Help</span></button>';
$printPage .=     '</nav>';
$printPage .=   '</div>'; // app

$printPage .= '</div>'; // #customer-connect-app

return $printPage;
