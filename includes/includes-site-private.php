<?php
/**
 * Battle Plan — Private Site Guard
 * -------------------------------------------------------------------------
 * Makes an entire site invisible to logged-out visitors. Every front-end
 * request is redirected to the site's login page until the visitor signs in.
 *
 * OPT-IN PER SITE — never enable this on a public marketing site. Turn it on
 * in the child theme's functions-site.php:
 *
 *   update_option( 'site_private', array(
 *       'install'       => 'true',
 *       'login_slug'    => 'site-pulse-login',     // logged-out visitors go here
 *       'home_redirect' => 'site-pulse-dashboard', // logged-in visitors hitting "/" go here (optional)
 *       'allow_slugs'   => array(),                // extra public slugs (optional)
 *   ) );
 *
 * Loaded from functions.php ONLY when that option's 'install' is set, so the
 * 130+ public sites that never set it pay zero cost and stay fully public.
 *
 * Scope: this guard runs on `template_redirect`, which fires for front-end
 * page views only. It deliberately does NOT touch wp-admin, admin-ajax.php,
 * the REST API, wp-cron, or direct asset requests — so the (nopriv) AJAX
 * login handshake and all login plumbing keep working while logged out.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Is private mode active for this site?
 */
function bp_site_is_private(): bool {
	$opts = get_option( 'site_private', [] );
	return ! empty( $opts['install'] );
}

/**
 * The slug logged-out visitors are sent to. Falls back to wp-login.php when
 * no custom login page is configured.
 */
function bp_private_login_slug(): string {
	$opts = get_option( 'site_private', [] );
	return isset( $opts['login_slug'] ) ? trim( (string) $opts['login_slug'], '/' ) : '';
}

/**
 * Whether the current logged-out request is allowed through the gate.
 * Allowed: the configured login page, any explicitly whitelisted slug, and
 * (defensively) the core login screen. A `bp_private_allow_request` filter
 * lets site code open up additional paths.
 */
function bp_private_is_allowed_request(): bool {
	$opts       = get_option( 'site_private', [] );
	$login_slug = bp_private_login_slug();
	$allow      = array_values( array_filter( array_merge(
		[ $login_slug ],
		array_map( fn( $s ) => trim( (string) $s, '/' ), (array) ( $opts['allow_slugs'] ?? [] ) )
	) ) );

	// Defensive: never gate the core login/registration screen.
	if ( ( $GLOBALS['pagenow'] ?? '' ) === 'wp-login.php' ) return true;

	// Match the resolved post slug (handles pretty permalinks for real posts).
	global $post;
	if ( $post && in_array( $post->post_name, $allow, true ) ) return true;

	// Match the raw request path (handles non-post / unresolved URLs).
	$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
	if ( $path !== '' && in_array( $path, $allow, true ) ) return true;

	/**
	 * Allow site code to open up additional requests while private mode is on.
	 *
	 * @param bool  $allowed       Default false (blocked).
	 * @param array $allow_slugs   The resolved whitelist of slugs.
	 */
	return (bool) apply_filters( 'bp_private_allow_request', false, $allow );
}

/**
 * Is this the bare home URL ("/")? Uses the raw path rather than is_front_page()
 * so it still resolves correctly when the front page is missing (a 404 query).
 */
function bp_private_is_home_request(): bool {
	$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
	return $path === '';
}

/**
 * The gate. Bounces logged-out visitors to the login page, and (optionally)
 * sends logged-in visitors who land on the bare home URL into the app.
 */
add_action( 'template_redirect', 'bp_private_site_guard', 1 );
function bp_private_site_guard(): void {
	$opts = get_option( 'site_private', [] );

	if ( is_user_logged_in() ) {
		// Logged-in staff pass through — except the home URL, which on an app
		// site is usually empty; send them to the configured landing page.
		$home_redirect = isset( $opts['home_redirect'] ) ? trim( (string) $opts['home_redirect'], '/' ) : '';
		if ( $home_redirect && bp_private_is_home_request() ) {
			nocache_headers();
			wp_safe_redirect( home_url( '/' . $home_redirect . '/' ), 302 );
			exit;
		}
		return;
	}

	if ( bp_private_is_allowed_request() ) return; // login page + whitelist

	$login_slug = bp_private_login_slug();
	$dest       = $login_slug
		? home_url( '/' . $login_slug . '/' )
		: wp_login_url( home_url( $_SERVER['REQUEST_URI'] ?? '/' ) );

	if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();
	wp_safe_redirect( $dest, 302 );
	exit;
}
