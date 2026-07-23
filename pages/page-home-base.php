<?php
/* Battle Plan Web Design — Home Base app shell (public customer PWA)

   Returned into the `home-base` universal page. This is only the SHELL: a root
   node + screen scaffolding that js/script-home-base.js drives as a client-side
   SPA against the home-base/v1 REST API. Auth is a device-stored bearer token
   (localStorage), so the page itself is public — the JS decides whether to show
   the sign-in screen or the app.

   White-label: Home Base keeps the client's site branding (fonts + palette from
   the child theme's style-site.css). The accent below comes from the `home_base`
   config `theme_color`, so each client tints the app without any code change.
*/

if ( ! function_exists( 'hb_app_name' ) ) return ''; // module off — nothing to render

$app_name = hb_app_name();
$company  = hb_company_name();
$accent   = (string) hb_get( 'theme_color', '#0f766e' );
$icon_url = function_exists( 'hb_pwa_source_url' ) ? hb_pwa_source_url() : '';

$printPage  = '';

// Per-client accent injected inline so the compiled CSS stays generic.
$printPage .= '<style id="hb-accent">:root{--hb-accent:' . esc_html( $accent ) . ';}</style>';

$printPage .= '<div id="home-base-app" data-app-name="' . esc_attr( $app_name ) . '" aria-live="polite">';

// Boot splash (JS swaps this out once /me resolves).
$printPage .=   '<div class="hb-screen hb-boot" data-screen="boot">';
$printPage .=     '<div class="hb-boot-inner">';
if ( $icon_url ) {
	$printPage .=   '<img src="' . esc_url( $icon_url ) . '" alt="" class="hb-boot-icon" width="72" height="72">';
}
$printPage .=       '<div class="hb-spinner" role="status" aria-label="Loading"></div>';
$printPage .=     '</div>';
$printPage .=   '</div>';

// --- Sign-in screen (enter phone/email → OTP) ---
$printPage .=   '<div class="hb-screen" data-screen="signin" hidden>';
$printPage .=     '<div class="hb-auth-card">';
$printPage .=       '<div class="hb-auth-header">';
if ( $icon_url ) {
	$printPage .=     '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $app_name ) . '" class="hb-auth-icon" width="64" height="64">';
}
$printPage .=         '<h1 class="hb-auth-title">' . esc_html( $app_name ) . '</h1>';
if ( $company ) $printPage .= '<p class="hb-auth-sub">' . esc_html( $company ) . '</p>';
$printPage .=       '</div>';

// Step 1: identifier
$printPage .=       '<form class="hb-form" data-step="identify" novalidate>';
$printPage .=         '<p class="hb-form-lead">Enter your mobile number to sign in. We\'ll text you a one-time code.</p>';
$printPage .=         '<label class="hb-field"><span>Mobile number</span>';
$printPage .=           '<input type="tel" name="phone" inputmode="tel" autocomplete="tel" placeholder="(555) 555-5555">';
$printPage .=         '</label>';
$printPage .=         '<button type="button" class="hb-btn hb-alt-toggle" data-target="email">Use email instead</button>';
$printPage .=         '<label class="hb-field" data-field="email" hidden><span>Email</span>';
$printPage .=           '<input type="email" name="email" autocomplete="email" placeholder="you@example.com">';
$printPage .=         '</label>';
$printPage .=         '<button type="submit" class="hb-btn hb-btn-primary">Send code</button>';
$printPage .=         '<p class="hb-error" role="alert" hidden></p>';
$printPage .=       '</form>';

// Step 2: code
$printPage .=       '<form class="hb-form" data-step="code" hidden novalidate>';
$printPage .=         '<p class="hb-form-lead">Enter the 6-digit code we sent to <strong data-slot="sent-to"></strong>.</p>';
$printPage .=         '<label class="hb-field"><span>Verification code</span>';
$printPage .=           '<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000">';
$printPage .=         '</label>';
$printPage .=         '<button type="submit" class="hb-btn hb-btn-primary">Verify &amp; continue</button>';
$printPage .=         '<button type="button" class="hb-btn hb-link" data-action="resend">Resend code</button>';
$printPage .=         '<button type="button" class="hb-btn hb-link" data-action="back">Use a different number</button>';
$printPage .=         '<p class="hb-error" role="alert" hidden></p>';
$printPage .=       '</form>';

$printPage .=     '</div>'; // .hb-auth-card
$printPage .=   '</div>'; // signin

// --- App screen (tab bar + views; JS renders view content) ---
$printPage .=   '<div class="hb-screen hb-app" data-screen="app" hidden>';
$printPage .=     '<header class="hb-appbar">';
$printPage .=       '<span class="hb-appbar-title" data-slot="view-title">Home</span>';
$printPage .=       '<div class="hb-appbar-actions">';
$printPage .=         '<button type="button" class="hb-appbar-bell" data-action="notifications" aria-label="Notifications"><span class="hb-badge" data-slot="unread" hidden>0</span></button>';
$printPage .=         '<button type="button" class="hb-appbar-menu" data-action="account" aria-label="Account"></button>';
$printPage .=       '</div>';
$printPage .=     '</header>';
$printPage .=     '<main class="hb-view" data-slot="view"></main>';
$printPage .=     '<nav class="hb-tabbar" aria-label="Main">';
$printPage .=       '<button type="button" class="hb-tab is-active" data-view="home"><span class="hb-tab-label">Home</span></button>';
$printPage .=       '<button type="button" class="hb-tab" data-view="equipment"><span class="hb-tab-label">My Home</span></button>';
$printPage .=       '<button type="button" class="hb-tab" data-view="schedule"><span class="hb-tab-label">Schedule</span></button>';
$printPage .=       '<button type="button" class="hb-tab" data-view="help"><span class="hb-tab-label">Help</span></button>';
$printPage .=     '</nav>';
$printPage .=   '</div>'; // app

$printPage .= '</div>'; // #home-base-app

return $printPage;
