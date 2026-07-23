<?php
/**
 * Home Base — Staff dashboard (client-side, for the contractor's team)
 * ---------------------------------------------------------------------------
 * A separate full-screen app at /home-base-admin/ where the client composes and
 * sends push notifications and manages their customers. Unlike the customer app
 * (custom table + OTP), staff are REAL WordPress users (the same login they use
 * to add jobsites), so this uses standard WP cookie auth + a capability gate +
 * the wp_rest nonce — the dominant Site Pulse admin pattern. Still fully
 * independent of Site Pulse. @package battleplan
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Capability required to use the staff dashboard. Filterable per site. */
function hb_admin_cap(): string {
	return (string) apply_filters( 'home_base_admin_cap', 'manage_options' );
}

/** Inline nav icons for the sidebar (currentColor-filled). */
function hb_admin_icon( string $name ): string {
	$paths = [
		'send'      => '<path d="M3 11.5 21 3l-7 18-3.5-7.5L3 11.5z"/>',
		'customers' => '<path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm0 2c-2.7 0-8 1.3-8 4v3h9v-3c0-1 .4-1.9 1-2.6C9.2 13.1 8.5 13 8 13zm8 0c-.3 0-.7 0-1.1.1 1.3.9 2.1 2.1 2.1 3.4V20h8v-3c0-2.7-5.3-4-9-4z"/>',
		'menu'      => '<path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/>',
		'signout'   => '<path d="M16 17v-2H9v-2h7V9l4 4-4 4zM14 2a2 2 0 0 1 2 2v2h-2V4H5v16h9v-2h2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9z"/>',
	];
	$d = $paths[ $name ] ?? '';
	return '<svg class="hb-nav-ico" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">' . $d . '</svg>';
}

/** True on the staff dashboard page. */
function hb_is_admin_page(): bool {
	global $post;
	return $post && $post->post_name === HOME_BASE_ADMIN_SLUG;
}


/*--------------------------------------------------------------
# Brand colors (pulled from the child theme's style-site.css)
--------------------------------------------------------------*/

/** Parse "#rgb"/"#rrggbb"/"rgb[a](...)" → [r,g,b], or null. */
function hb_parse_rgb( string $c ): ?array {
	$c = trim( $c );
	if ( preg_match( '/^#([0-9a-f]{3})$/i', $c, $m ) ) { $h = $m[1]; return [ hexdec( $h[0].$h[0] ), hexdec( $h[1].$h[1] ), hexdec( $h[2].$h[2] ) ]; }
	if ( preg_match( '/^#([0-9a-f]{6})$/i', $c, $m ) ) { $h = $m[1]; return [ hexdec( substr($h,0,2) ), hexdec( substr($h,2,2) ), hexdec( substr($h,4,2) ) ]; }
	if ( preg_match( '/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/i', $c, $m ) ) return [ (int)$m[1], (int)$m[2], (int)$m[3] ];
	return null;
}

/** Legible text color (#fff or near-black) for a given background color. */
function hb_contrast_color( string $bg ): string {
	$rgb = hb_parse_rgb( $bg );
	if ( ! $rgb ) return '#ffffff';
	$lum = ( 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2] ) / 255;
	return $lum > 0.62 ? '#1f2430' : '#ffffff';
}

/**
 * The 3 admin brand colors, read from the child theme's style-site.css :root —
 * NOT an AI/settings color engine. Convention (set these in style-site.css):
 *     --hb-primary    → sidebar / dark
 *     --hb-secondary  → light accents / borders
 *     --hb-accent     → active nav / CTAs
 * Falls back to common brand vars (--main-*), then the module theme_color, then
 * the shared defaults. Resolves one+ levels of var() indirection.
 */
function hb_brand_colors(): array {
	static $cache = null;
	if ( $cache !== null ) return $cache;

	$css  = '';
	$file = get_stylesheet_directory() . '/style-site.css';
	if ( is_readable( $file ) ) $css = (string) file_get_contents( $file );

	$vars = [];
	if ( preg_match_all( '/--([a-z0-9\-]+)\s*:\s*([^;]+);/i', $css, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $row ) $vars[ strtolower( $row[1] ) ] = trim( $row[2] );
	}
	$resolve = function ( $name, $depth = 0 ) use ( &$resolve, $vars ) {
		$name = strtolower( ltrim( $name, '-' ) );
		if ( ! isset( $vars[ $name ] ) || $depth > 5 ) return '';
		$val = $vars[ $name ];
		if ( preg_match( '/^var\(\s*--([a-z0-9\-]+)/i', $val, $vm ) ) return $resolve( $vm[1], $depth + 1 );
		return $val;
	};

	$default = (string) hb_get( 'theme_color', '#3e434a' );

	$primary = $resolve( 'hb-primary' );
	if ( $primary === '' ) foreach ( [ 'main-color', 'main-red', 'main-blue', 'main-green', 'brand', 'primary-color', 'main' ] as $k ) { $primary = $resolve( $k ); if ( $primary !== '' ) break; }
	if ( $primary === '' ) $primary = $default;

	$secondary = $resolve( 'hb-secondary' );
	if ( $secondary === '' ) $secondary = '#dae9de';

	// Accent falls back to a distinct highlight (not the primary) so the active
	// nav item stays legible on a brand-colored sidebar — mirrors Site Pulse's
	// gold-on-brand look until the site defines its own --hb-accent.
	$accent = $resolve( 'hb-accent' );
	if ( $accent === '' ) $accent = '#ec9a3c';

	return $cache = apply_filters( 'home_base_brand_colors', [ 'primary' => $primary, 'secondary' => $secondary, 'accent' => $accent ] );
}

/**
 * Inline <style> mapping the 3 brand colors onto the shared --sp-* tokens, with
 * color-mix deriving the light/dark ramp shades. Emitted on the admin page so
 * the shared design system takes the client's brand.
 */
function hb_admin_color_css(): string {
	$c  = hb_brand_colors();
	$p  = $c['primary']; $s = $c['secondary']; $a = $c['accent'];
	$pc = hb_contrast_color( $p );
	$ac = hb_contrast_color( $a );
	// esc for a CSS context: allow #, (), commas, %, letters/digits, spaces, dots, dashes.
	$clean = function ( $v ) { return preg_replace( '/[^#a-z0-9(),.%\-\s]/i', '', (string) $v ); };
	$p = $clean( $p ); $s = $clean( $s ); $a = $clean( $a );

	return '<style id="hb-admin-colors">:root{'
		. "--sp-primary-color:$p;"
		. "--sp-primary-dark:color-mix(in srgb,$p 82%,#000);"
		. "--sp-primary-darkest:color-mix(in srgb,$p 60%,#000);"
		. "--sp-primary-contrast:$pc;"
		. "--sp-secondary-color:$s;"
		. "--sp-secondary-lightest:color-mix(in srgb,$s 45%,#fff);"
		. "--sp-secondary-darkest:color-mix(in srgb,$s 68%,#000);"
		. "--sp-accent-color:$a;"
		. "--sp-accent-contrast:$ac;"
		. "--sp-accent-light:color-mix(in srgb,$a 55%,#fff);"
		. "--sp-accent-lightest:color-mix(in srgb,$a 28%,#fff);"
		. "--sp-accent-dark:color-mix(in srgb,$a 78%,#000);"
		. "--sp-accent-darkest:color-mix(in srgb,$a 62%,#000);"
		. '}</style>';
}

/** True when the current WP user may manage Home Base. */
function hb_admin_can(): bool {
	return is_user_logged_in() && current_user_can( hb_admin_cap() );
}


/*--------------------------------------------------------------
# Page: nocache + chrome strip + assets
--------------------------------------------------------------*/

add_action( 'template_redirect', function () {
	if ( ! hb_is_admin_page() ) return;
	if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();
	if ( ! headers_sent() ) header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	add_filter( 'show_admin_bar', '__return_false' );
	add_filter( 'body_class', function ( $c ) { $c[] = 'has-home-base'; $c[] = 'has-home-base-admin'; return $c; } );
} );

// Staff dashboard is internal tooling — like Site Pulse, drop the client's public
// site styling so the admin UI is clean (its brand button/typography rules were
// bleeding onto the dashboard). Suppress the INLINE site CSS AND dequeue the
// enqueued compiled copy + site script — mirrors Site Pulse exactly. (The
// customer app keeps site branding; this dashboard does not.)
add_filter( 'bp_inline_site_css', function ( $inline ) {
	return hb_is_admin_page() ? false : $inline;
} );
add_action( 'wp_enqueue_scripts', function () {
	if ( ! hb_is_admin_page() ) return;
	wp_dequeue_style(  'battleplan-site' );
	wp_dequeue_script( 'battleplan-script-site' );
}, 99999 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! hb_is_admin_page() ) return;

	// Shared Battle Plan admin design system (same file Site Pulse uses). Handle is
	// deduped by WP, so if both modules enqueue it, it loads once. Loaded FIRST so
	// the module's own sheet can layer specifics on top.
	if ( file_exists( get_template_directory() . '/style-bp-admin.css' ) ) {
		wp_enqueue_style( 'bp-admin-ui', get_template_directory_uri() . '/style-bp-admin.css', [], _BP_VERSION );
	}
	$css = file_exists( get_template_directory() . '/style-home-base.css' ) ? '/style-home-base.css' : '';
	if ( $css ) wp_enqueue_style( 'home-base', get_template_directory_uri() . $css, [ 'bp-admin-ui' ], _BP_VERSION );

	$js = file_exists( get_template_directory() . '/js/script-home-base-admin.min.js' )
		? '/js/script-home-base-admin.min.js'
		: ( file_exists( get_template_directory() . '/js/script-home-base-admin.js' ) ? '/js/script-home-base-admin.js' : '' );
	if ( $js ) {
		wp_enqueue_script( 'home-base-admin', get_template_directory_uri() . $js, [], _BP_VERSION, true );
		wp_localize_script( 'home-base-admin', 'homeBaseAdmin', [
			'restBase'  => esc_url_raw( rest_url( 'home-base/v1/admin' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'loggedIn'  => hb_admin_can(),
			'appName'   => hb_app_name(),
			'company'   => hb_company_name(),
			'pushReady' => function_exists( 'hb_push_ready' ) ? hb_push_ready() : false,
		] );
	}
}, 20 );


/*--------------------------------------------------------------
# REST (staff) — WP cookie auth + capability + wp_rest nonce
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	$ns  = 'home-base/v1/admin';
	$gate = function () { return hb_admin_can(); };

	// Login is public (it establishes the session); everything else is gated.
	register_rest_route( $ns, '/login', [ 'methods' => 'POST', 'callback' => 'hb_admin_rest_login', 'permission_callback' => '__return_true' ] );
	register_rest_route( $ns, '/customers', [ 'methods' => 'GET',  'callback' => 'hb_admin_rest_customers', 'permission_callback' => $gate ] );
	register_rest_route( $ns, '/segments',  [ 'methods' => 'GET',  'callback' => 'hb_admin_rest_segments',  'permission_callback' => $gate ] );
	register_rest_route( $ns, '/send',      [ 'methods' => 'POST', 'callback' => 'hb_admin_rest_send',      'permission_callback' => $gate ] );
} );

/**
 * POST /admin/login — sign a WP user in and set the auth cookie. Field names are
 * `log`/`pwd` (NOT `password` — WP Engine's WAF strips a POST field literally
 * named `password` on non-login endpoints). Returns ok; the client then reloads.
 */
function hb_admin_rest_login( WP_REST_Request $request ) {
	$body = json_decode( $request->get_body(), true ) ?: [];
	$creds = [
		'user_login'    => sanitize_text_field( (string) ( $body['log'] ?? '' ) ),
		'user_password' => (string) ( $body['pwd'] ?? '' ),
		'remember'      => true,
	];
	if ( $creds['user_login'] === '' || $creds['user_password'] === '' ) {
		return new WP_Error( 'hb_missing', 'Enter your username and password.', [ 'status' => 400 ] );
	}
	$user = wp_signon( $creds, is_ssl() );
	if ( is_wp_error( $user ) ) {
		return new WP_Error( 'hb_login', 'Invalid username or password.', [ 'status' => 401 ] );
	}
	if ( ! user_can( $user, hb_admin_cap() ) ) {
		return new WP_Error( 'hb_perm', 'This account cannot manage Home Base.', [ 'status' => 403 ] );
	}
	wp_set_current_user( $user->ID );
	return rest_ensure_response( [ 'ok' => true ] );
}

/** GET /admin/customers?search= — customer roster with equipment + subscription counts. */
function hb_admin_rest_customers( WP_REST_Request $request ) {
	global $wpdb;
	$c = hb_table( 'customers' );
	$e = hb_table( 'equipment' );
	$p = hb_table( 'push_subscriptions' );

	$search = trim( (string) $request->get_param( 'search' ) );
	$where  = "WHERE c.status = 'active'";
	$params = [];
	if ( $search !== '' ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= " AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.phone LIKE %s OR c.email LIKE %s)";
		array_push( $params, $like, $like, $like, $like );
	}

	$sql = "SELECT c.id, c.first_name, c.last_name, c.phone, c.email, c.city, c.state,
				(SELECT COUNT(*) FROM $e e WHERE e.customer_id = c.id) AS equipment_count,
				(SELECT COUNT(*) FROM $p p WHERE p.customer_id = c.id) AS device_count
			FROM $c c $where ORDER BY c.last_login_at DESC, c.id DESC LIMIT 200";
	$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	$rows = $rows ?: [];

	$items = array_map( function ( $r ) {
		$name = trim( $r['first_name'] . ' ' . $r['last_name'] );
		return [
			'id'         => (int) $r['id'],
			'name'       => $name !== '' ? $name : '(no name)',
			'contact'    => $r['phone'] ?: $r['email'],
			'location'   => trim( trim( $r['city'] . ', ' . $r['state'], ', ' ) ),
			'equipment'  => (int) $r['equipment_count'],
			'subscribed' => (int) $r['device_count'] > 0,
		];
	}, $rows );

	return rest_ensure_response( [ 'ok' => true, 'items' => $items ] );
}

/** GET /admin/segments — audience sizes for the compose screen. */
function hb_admin_rest_segments( WP_REST_Request $request ) {
	$segs = [
		[ 'key' => 'all',        'label' => 'All subscribers',       'count' => count( hb_push_segment_ids( 'all' ) ) ],
		[ 'key' => 'filter_due', 'label' => 'Filter due / overdue',  'count' => count( hb_push_segment_ids( 'filter_due' ) ) ],
	];
	return rest_ensure_response( [ 'ok' => true, 'segments' => $segs, 'pushReady' => hb_push_ready() ] );
}

/**
 * POST /admin/send — dispatch a notification. Body:
 *   { target:'customer', customer_id, title, body, url? }
 *   { target:'segment',  segment,     title, body, url? }
 */
function hb_admin_rest_send( WP_REST_Request $request ) {
	$body   = json_decode( $request->get_body(), true ) ?: [];
	$title  = trim( (string) ( $body['title'] ?? '' ) );
	$text   = trim( (string) ( $body['body'] ?? '' ) );
	$url    = esc_url_raw( (string) ( $body['url'] ?? '' ) );
	$target = (string) ( $body['target'] ?? '' );

	if ( $title === '' ) return new WP_Error( 'hb_bad', 'Add a title.', [ 'status' => 400 ] );
	if ( ! hb_push_ready() ) return new WP_Error( 'hb_push', 'Push is not configured on this site yet.', [ 'status' => 400 ] );

	$note = [ 'title' => $title, 'body' => $text ];
	if ( $url !== '' ) $note['url'] = $url;
	$by = get_current_user_id();

	if ( $target === 'customer' ) {
		$cid = (int) ( $body['customer_id'] ?? 0 );
		if ( $cid <= 0 ) return new WP_Error( 'hb_bad', 'Pick a customer.', [ 'status' => 400 ] );
		$delivered = hb_push_send_to_customer( $cid, $note, $by );
		return rest_ensure_response( [ 'ok' => true, 'recipients' => 1, 'delivered' => $delivered ] );
	}

	if ( $target === 'segment' ) {
		$segment = sanitize_key( (string) ( $body['segment'] ?? '' ) );
		$ids = hb_push_segment_ids( $segment );
		if ( ! $ids ) return new WP_Error( 'hb_empty', 'That audience has no subscribers right now.', [ 'status' => 400 ] );
		$res = hb_push_broadcast( $ids, $note, $by );
		return rest_ensure_response( array_merge( [ 'ok' => true ], $res ) );
	}

	return new WP_Error( 'hb_bad', 'Choose who to send to.', [ 'status' => 400 ] );
}
