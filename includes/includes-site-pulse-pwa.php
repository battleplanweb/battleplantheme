<?php
/**
 * Site Pulse — Progressive Web App layer
 * ---------------------------------------------------------------------------
 * Turns the WordPress-served Site Pulse dashboard into an installable, real
 * app on phones and desktops: a dynamic web manifest, a service worker
 * (offline shell + instant repeat loads via cached static assets), iOS meta,
 * app icons generated server-side from a source image, and an in-app install
 * prompt.
 *
 * No rewrite rules and no static files: both endpoints are served dynamically
 * by intercepting two root-scoped URLs early on `init`:
 *     /site-pulse-app.webmanifest
 *     /site-pulse-sw.js
 * Root scope lets a single service worker control /site-pulse-login/ and
 * /site-pulse-dashboard/ alike.
 *
 * This module loads only when the `site_pulse` option is on (it's required from
 * includes-site-pulse.php), so it costs nothing on the 130+ public sites.
 *
 * Config (all read via site_pulse_get_setting → DB config, then the site_pulse
 * option, then default — so a site can override in functions-site.php):
 *     app_name              → manifest name              (default "Site Pulse")
 *     pwa_short_name        → home-screen label          (default: first word of app_name)
 *     pwa_theme_color       → Android status-bar / theme (default "#2d4270")
 *     pwa_background_color  → splash background          (default "#ffffff")
 *     pwa_icon_id           → attachment ID of icon source image (optional)
 *     pwa_icon_path         → path to icon source (uploads-rel / theme-rel / absolute)
 * Icon source resolution order: pwa_icon_id → pwa_icon_path → child-theme
 * images/app-icon.(png|webp|jpg) → WP Site Icon → header_logo_url → none.
 *
 * @package battleplan
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Bump on any structural change to force every installed client to fetch a
// fresh service worker and drop its old cache.
if ( ! defined( 'SITE_PULSE_PWA_VERSION' ) ) define( 'SITE_PULSE_PWA_VERSION', '1.0.0' );

// The two virtual files. Matched by basename so a subdirectory install still works.
if ( ! defined( 'SITE_PULSE_PWA_MANIFEST_FILE' ) ) define( 'SITE_PULSE_PWA_MANIFEST_FILE', 'site-pulse-app.webmanifest' );
if ( ! defined( 'SITE_PULSE_PWA_SW_FILE' ) )       define( 'SITE_PULSE_PWA_SW_FILE', 'site-pulse-sw.js' );


/*--------------------------------------------------------------
# Config
--------------------------------------------------------------*/

/**
 * Resolved PWA config for this site. Filterable via `site_pulse_pwa_config`.
 */
function site_pulse_pwa_config(): array {
	$app_name = site_pulse_get_setting( 'app_name', 'Site Pulse' );

	$short = site_pulse_get_setting( 'pwa_short_name', '' );
	if ( $short === '' ) {
		$first = trim( (string) ( explode( ' ', trim( $app_name ) )[0] ?? '' ) );
		$short = $first !== '' ? $first : $app_name;
	}
	// Android truncates long home-screen labels; keep it tight.
	$short = mb_substr( $short, 0, 12 );

	$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$scope     = $home_path !== '' ? trailingslashit( $home_path ) : '/';

	$cfg = [
		'name'        => $app_name,
		'short_name'  => $short,
		'theme_color' => site_pulse_get_setting( 'pwa_theme_color', '#2d4270' ),
		'bg_color'    => site_pulse_get_setting( 'pwa_background_color', '#ffffff' ),
		'start_url'   => home_url( '/site-pulse-dashboard/' ),
		'scope_path'  => $scope,
	];

	return apply_filters( 'site_pulse_pwa_config', $cfg );
}


/*--------------------------------------------------------------
# Virtual endpoints (manifest + service worker)
--------------------------------------------------------------*/

/**
 * Intercept the two virtual files before WordPress resolves a 404 (and before
 * the site_private guard runs on template_redirect), so they're reachable even
 * when logged out — the login screen needs to be installable too.
 */
add_action( 'init', function () {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;

	$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( $path === '' ) return;
	$base = basename( $path );

	if ( $base === SITE_PULSE_PWA_MANIFEST_FILE ) site_pulse_pwa_render_manifest();
	if ( $base === SITE_PULSE_PWA_SW_FILE )       site_pulse_pwa_render_sw();
}, 0 );

/**
 * Output the web app manifest (application/manifest+json) and exit.
 */
function site_pulse_pwa_render_manifest(): void {
	$cfg   = site_pulse_pwa_config();
	$icons = site_pulse_pwa_icons();

	$manifest = [
		'id'               => $cfg['scope_path'],
		'name'             => $cfg['name'],
		'short_name'       => $cfg['short_name'],
		'description'      => $cfg['name'] . ' — staff operations app',
		'start_url'        => $cfg['start_url'],
		'scope'            => $cfg['scope_path'],
		'display'          => 'standalone',
		'orientation'      => 'portrait',
		'background_color' => $cfg['bg_color'],
		'theme_color'      => $cfg['theme_color'],
		'lang'             => 'en-US',
		'dir'              => 'ltr',
		'icons'            => $icons,
	];

	if ( ! headers_sent() ) {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		// Short cache — the manifest rarely changes and an install reads it once.
		header( 'Cache-Control: public, max-age=300' );
	}
	echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	exit;
}

/**
 * Output the service worker JavaScript and exit.
 *
 * Strategy (auth-gated app, so caching is deliberately conservative):
 *   - Navigations (HTML pages): NETWORK-FIRST. Never serve a stale logged-in
 *     page or a stale nonce. Only fall back to a small offline notice when the
 *     network is genuinely unreachable.
 *   - Static assets (css/js/fonts/images, same-origin + known CDNs):
 *     STALE-WHILE-REVALIDATE. This is where the "instant app" feel comes from —
 *     the heavy CSS/JS load from cache, then refresh in the background.
 *   - admin-ajax / wp-json / wp-admin / non-GET: passthrough to network, never
 *     cached (these carry per-user data).
 */
function site_pulse_pwa_render_sw(): void {
	$cfg       = site_pulse_pwa_config();
	$cache_ver = SITE_PULSE_PWA_VERSION;
	$app_name  = $cfg['name'];
	$theme     = $cfg['theme_color'];
	$bg        = $cfg['bg_color'];

	if ( ! headers_sent() ) {
		header( 'Content-Type: text/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . $cfg['scope_path'] );
		// The SW file must never be edge- or browser-cached, or updates won't ship.
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	}

	// Offline fallback page (inlined into the SW so it needs no network).
	$offline_html = sprintf(
		'<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>Offline — %1$s</title><style>'
		. 'html,body{height:100%%;margin:0}'
		. 'body{display:flex;align-items:center;justify-content:center;'
		. 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;'
		. 'background:%2$s;color:#1f2933;text-align:center;padding:24px}'
		. '.box{max-width:340px}.dot{width:54px;height:54px;border-radius:50%%;'
		. 'background:%3$s;margin:0 auto 18px;opacity:.15}'
		. 'h1{font-size:20px;margin:0 0 8px;color:%3$s}'
		. 'p{font-size:15px;line-height:1.5;color:#52606d;margin:0 0 20px}'
		. 'button{font:inherit;font-weight:600;background:%3$s;color:#fff;border:0;'
		. 'border-radius:10px;padding:12px 22px}'
		. '</style></head><body><div class="box"><div class="dot"></div>'
		. '<h1>You\'re offline</h1><p>%1$s needs a connection to load. '
		. 'Reconnect and try again.</p>'
		. '<button onclick="location.reload()">Retry</button></div></body></html>',
		esc_html( $app_name ),
		esc_html( $bg ),
		esc_html( $theme )
	);

	$offline_json = wp_json_encode( $offline_html );

	?>
/* Site Pulse service worker — v<?php echo $cache_ver; ?> */
'use strict';

const SP_CACHE   = 'site-pulse-v<?php echo esc_js( $cache_ver ); ?>';
const OFFLINE_HTML = <?php echo $offline_json; ?>;

const ASSET_RE = /\.(?:css|js|mjs|woff2?|ttf|otf|eot|png|jpe?g|webp|avif|svg|gif|ico)(?:\?|$)/i;
const CDN_HOSTS = [
	'cdnjs.cloudflare.com',
	'cdn.jsdelivr.net',
	'fonts.googleapis.com',
	'fonts.gstatic.com'
];

// Never touch these — they carry per-user/auth data.
function isBypass(url, req) {
	if (req.method !== 'GET') return true;
	const p = url.pathname;
	return p.indexOf('/wp-admin/') === 0
		|| p.indexOf('/wp-json/') === 0
		|| p.indexOf('/wp-login.php') === 0
		|| p.indexOf('admin-ajax.php') !== -1
		|| p.indexOf('<?php echo esc_js( SITE_PULSE_PWA_SW_FILE ); ?>') !== -1
		|| p.indexOf('<?php echo esc_js( SITE_PULSE_PWA_MANIFEST_FILE ); ?>') !== -1;
}

function isCacheableAsset(url) {
	if (url.origin === self.location.origin) return ASSET_RE.test(url.pathname);
	return CDN_HOSTS.indexOf(url.hostname) !== -1;
}

self.addEventListener('install', (e) => {
	self.skipWaiting();
});

self.addEventListener('activate', (e) => {
	e.waitUntil((async () => {
		const keys = await caches.keys();
		await Promise.all(keys.filter((k) => k !== SP_CACHE).map((k) => caches.delete(k)));
		await self.clients.claim();
	})());
});

self.addEventListener('fetch', (event) => {
	const req = event.request;
	let url;
	try { url = new URL(req.url); } catch (_) { return; }

	if (isBypass(url, req)) return; // default network handling

	// Page navigations: network-first, offline fallback.
	if (req.mode === 'navigate') {
		event.respondWith((async () => {
			try {
				return await fetch(req);
			} catch (_) {
				const cached = await caches.match(req);
				if (cached) return cached;
				return new Response(OFFLINE_HTML, {
					headers: { 'Content-Type': 'text/html; charset=utf-8' }
				});
			}
		})());
		return;
	}

	// Static assets: stale-while-revalidate.
	if (isCacheableAsset(url)) {
		event.respondWith((async () => {
			const cache  = await caches.open(SP_CACHE);
			const cached = await cache.match(req);
			const network = fetch(req).then((res) => {
				if (res && res.status === 200 &&
					(res.type === 'basic' || res.type === 'cors')) {
					cache.put(req, res.clone());
				}
				return res;
			}).catch(() => null);
			return cached || (await network) || fetch(req);
		})());
	}
});
	<?php
	exit;
}


/*--------------------------------------------------------------
# App icons (generated server-side from a source image)
--------------------------------------------------------------*/

/**
 * Manifest `icons` array, or [] if no usable source image / GD is available.
 * A manifest with no icons is still valid and installable (the browser falls
 * back to a lettermark), so this degrades gracefully.
 */
function site_pulse_pwa_icons(): array {
	$urls = site_pulse_pwa_generate_icons();
	if ( empty( $urls ) ) return [];

	$icons = [];
	if ( ! empty( $urls['192'] ) )      $icons[] = [ 'src' => $urls['192'],      'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ];
	if ( ! empty( $urls['512'] ) )      $icons[] = [ 'src' => $urls['512'],      'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ];
	if ( ! empty( $urls['maskable'] ) ) $icons[] = [ 'src' => $urls['maskable'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ];
	return $icons;
}

/**
 * Apple touch icon URL (opaque, 180px), or '' if none.
 */
function site_pulse_pwa_apple_icon(): string {
	$urls = site_pulse_pwa_generate_icons();
	return $urls['180'] ?? ( $urls['192'] ?? '' );
}

/**
 * Resolve the absolute path of the source image to build icons from.
 */
function site_pulse_pwa_source_image(): string {
	// 1) Explicit attachment ID.
	$id = (int) site_pulse_get_setting( 'pwa_icon_id', '0' );
	if ( $id ) {
		$p = get_attached_file( $id );
		if ( $p && file_exists( $p ) ) return $p;
	}

	// 2) Explicit path — absolute, site-root-relative (/wp-content/...),
	//    uploads-relative, or theme-relative.
	$path = site_pulse_get_setting( 'pwa_icon_path', '' );
	if ( $path !== '' ) {
		$uploads = wp_upload_dir();
		$rel     = ltrim( $path, '/' );
		$cands = [
			$path,                                                  // absolute filesystem path
			trailingslashit( ABSPATH ) . $rel,                      // /wp-content/uploads/foo.webp
			trailingslashit( $uploads['basedir'] ) . $rel,          // uploads-relative
			trailingslashit( get_stylesheet_directory() ) . $rel,   // child-theme-relative
		];
		foreach ( $cands as $c ) {
			if ( file_exists( $c ) ) return $c;
		}
	}

	// 3) Child-theme images/app-icon.*
	foreach ( [ 'png', 'webp', 'jpg', 'jpeg' ] as $ext ) {
		$c = get_stylesheet_directory() . '/images/app-icon.' . $ext;
		if ( file_exists( $c ) ) return $c;
	}

	// 4) WordPress Site Icon.
	$sid = (int) get_option( 'site_icon' );
	if ( $sid ) {
		$p = get_attached_file( $sid );
		if ( $p && file_exists( $p ) ) return $p;
	}

	// 5) Configured header logo (likely lives in uploads).
	$logo = site_pulse_get_setting( 'header_logo_url', '' );
	if ( $logo !== '' ) {
		$rel = ltrim( (string) wp_parse_url( $logo, PHP_URL_PATH ), '/' );
		// header_logo_url is typically /wp-content/uploads/... — map to abspath.
		$abs = trailingslashit( ABSPATH ) . $rel;
		if ( file_exists( $abs ) ) return $abs;
	}

	return (string) apply_filters( 'site_pulse_pwa_icon_source', '' );
}

/**
 * Generate (and cache) the icon PNG set. Returns ['192'=>url,'512'=>url,
 * '180'=>url,'maskable'=>url] for whatever sizes succeeded, or [].
 *
 * Regenerates only when the source file or theme color changes (signature
 * compare), so it's effectively free on every request after the first.
 */
function site_pulse_pwa_generate_icons(): array {
	static $memo = null;
	if ( $memo !== null ) return $memo;

	$uploads   = wp_upload_dir();
	$dir       = trailingslashit( $uploads['basedir'] ) . 'site-pulse-pwa';
	$url_base  = trailingslashit( $uploads['baseurl'] ) . 'site-pulse-pwa';
	$meta_file = $dir . '/icons.json';

	$src = site_pulse_pwa_source_image();
	$cfg = site_pulse_pwa_config();

	// No source (or GD missing) → serve any previously-built set, else nothing.
	if ( $src === '' || ! file_exists( $src ) || ! function_exists( 'imagecreatetruecolor' ) ) {
		if ( file_exists( $meta_file ) ) {
			$meta = json_decode( (string) file_get_contents( $meta_file ), true );
			if ( is_array( $meta ) && ! empty( $meta['urls'] ) ) return $memo = $meta['urls'];
		}
		return $memo = [];
	}

	$sig = md5( $src . '|' . filemtime( $src ) . '|' . $cfg['bg_color'] . '|' . SITE_PULSE_PWA_VERSION );

	// Cache hit.
	if ( file_exists( $meta_file ) ) {
		$meta = json_decode( (string) file_get_contents( $meta_file ), true );
		if ( is_array( $meta ) && ( $meta['sig'] ?? '' ) === $sig && ! empty( $meta['urls'] ) ) {
			return $memo = $meta['urls'];
		}
	}

	if ( ! wp_mkdir_p( $dir ) ) return $memo = [];

	// 'maskable' gets extra safe-zone padding so Android's circle/squircle crop
	// never clips the mark; 'any' sizes get a little breathing room too.
	$specs = [ '192' => 192, '512' => 512, '180' => 180, 'maskable' => 512 ];
	$urls  = [];
	foreach ( $specs as $key => $size ) {
		$out = $dir . '/icon-' . $key . '.png';
		if ( site_pulse_pwa_make_square_png( $src, $out, $size, $cfg['bg_color'], $key === 'maskable' ) ) {
			$urls[ $key ] = $url_base . '/icon-' . $key . '.png?v=' . substr( $sig, 0, 8 );
		}
	}

	if ( $urls ) {
		file_put_contents( $meta_file, wp_json_encode( [ 'sig' => $sig, 'urls' => $urls ] ) );
	}
	return $memo = $urls;
}

/**
 * Render the source image centered on an opaque square canvas of $bg_hex.
 * Returns true on success. Uses GD directly (wp_get_image_editor can't
 * pad-and-center onto a colored canvas).
 */
function site_pulse_pwa_make_square_png( string $src, string $out, int $size, string $bg_hex, bool $maskable ): bool {
	$data = @file_get_contents( $src );
	if ( $data === false ) return false;

	$im = @imagecreatefromstring( $data ); // auto-detects png/jpg/webp/gif if GD supports it
	if ( ! $im ) return false;

	$sw = imagesx( $im );
	$sh = imagesy( $im );
	if ( $sw < 1 || $sh < 1 ) { imagedestroy( $im ); return false; }

	$canvas = imagecreatetruecolor( $size, $size );
	[ $r, $g, $b ] = site_pulse_pwa_hex_to_rgb( $bg_hex );
	$bg = imagecolorallocate( $canvas, $r, $g, $b );
	imagefilledrectangle( $canvas, 0, 0, $size, $size, $bg );

	// Scale the mark to fit within a fraction of the canvas (leaves padding).
	$scale = $maskable ? 0.78 : 0.90;
	$box   = (int) round( $size * $scale );
	$ratio = min( $box / $sw, $box / $sh );
	$nw    = max( 1, (int) round( $sw * $ratio ) );
	$nh    = max( 1, (int) round( $sh * $ratio ) );
	$dx    = (int) round( ( $size - $nw ) / 2 );
	$dy    = (int) round( ( $size - $nh ) / 2 );

	imagealphablending( $canvas, true ); // composite transparent logo over the bg
	imagecopyresampled( $canvas, $im, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh );

	$ok = imagepng( $canvas, $out, 6 );
	imagedestroy( $im );
	imagedestroy( $canvas );
	return (bool) $ok;
}

/**
 * "#rrggbb" / "rrggbb" / "#rgb" → [r,g,b]. Falls back to white on garbage.
 */
function site_pulse_pwa_hex_to_rgb( string $hex ): array {
	$hex = ltrim( trim( $hex ), '#' );
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) return [ 255, 255, 255 ];
	return [ hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ];
}


/*--------------------------------------------------------------
# <head> tags + service-worker registration + install prompt
--------------------------------------------------------------*/

add_action( 'wp_head', 'site_pulse_pwa_head', 5 );

function site_pulse_pwa_head(): void {
	global $post;
	if ( ! $post || strpos( (string) $post->post_name, 'site-pulse' ) !== 0 ) return;

	$cfg          = site_pulse_pwa_config();
	$manifest_url = home_url( '/' . SITE_PULSE_PWA_MANIFEST_FILE );
	$sw_url       = home_url( '/' . SITE_PULSE_PWA_SW_FILE );
	$apple_icon   = site_pulse_pwa_apple_icon();
	$nonce        = defined( '_BP_NONCE' ) ? _BP_NONCE : '';

	echo "\n\t<!-- Site Pulse PWA -->\n";
	printf( "\t<link rel=\"manifest\" href=\"%s\">\n", esc_url( $manifest_url ) );
	printf( "\t<meta name=\"theme-color\" content=\"%s\">\n", esc_attr( $cfg['theme_color'] ) );
	echo   "\t<meta name=\"mobile-web-app-capable\" content=\"yes\">\n";
	echo   "\t<meta name=\"apple-mobile-web-app-capable\" content=\"yes\">\n";
	echo   "\t<meta name=\"apple-mobile-web-app-status-bar-style\" content=\"black-translucent\">\n";
	printf( "\t<meta name=\"apple-mobile-web-app-title\" content=\"%s\">\n", esc_attr( $cfg['short_name'] ) );
	printf( "\t<meta name=\"application-name\" content=\"%s\">\n", esc_attr( $cfg['short_name'] ) );
	if ( $apple_icon !== '' ) {
		printf( "\t<link rel=\"apple-touch-icon\" href=\"%s\">\n", esc_url( $apple_icon ) );
	}

	$data = wp_json_encode( [
		'sw'    => $sw_url,
		'scope' => $cfg['scope_path'],
		'name'  => $cfg['name'],
	] );
	?>
	<script<?php echo $nonce ? ' nonce="' . esc_attr( $nonce ) . '"' : ''; ?>>
	(function () {
		var SP = <?php echo $data; ?>;

		// Register the service worker.
		if ('serviceWorker' in navigator) {
			window.addEventListener('load', function () {
				navigator.serviceWorker.register(SP.sw, { scope: SP.scope }).catch(function () {});
			});
		}

		// Already running as an installed app? Nothing to prompt.
		var standalone = window.matchMedia('(display-mode: standalone)').matches ||
			window.navigator.standalone === true;
		if (standalone) return;

		var DISMISS_KEY = 'sp_pwa_install_dismissed';
		function dismissed() { try { return localStorage.getItem(DISMISS_KEY) === '1'; } catch (e) { return false; } }
		function setDismissed() { try { localStorage.setItem(DISMISS_KEY, '1'); } catch (e) {} }

		function showBanner(html, onAction) {
			if (dismissed() || document.getElementById('sp-install-banner')) return;
			var bar = document.createElement('div');
			bar.id = 'sp-install-banner';
			bar.setAttribute('role', 'dialog');
			bar.innerHTML = html +
				'<div class="sp-ib-actions">' +
				(onAction ? '<button type="button" class="sp-ib-go">Install</button>' : '') +
				'<button type="button" class="sp-ib-x" aria-label="Dismiss">Not now</button>' +
				'</div>';
			var s = document.createElement('style');
			s.textContent =
				'#sp-install-banner{position:fixed;left:12px;right:12px;bottom:12px;z-index:99999;' +
				'background:#fff;border:1px solid #d9dde2;border-radius:14px;padding:14px 16px;' +
				'box-shadow:0 8px 30px rgba(0,0,0,.18);font-family:inherit;max-width:460px;margin:0 auto;' +
				'display:flex;flex-direction:column;gap:10px;animation:spIbUp .25s ease}' +
				'@keyframes spIbUp{from{transform:translateY(12px);opacity:0}to{transform:none;opacity:1}}' +
				'#sp-install-banner .sp-ib-title{font-weight:700;font-size:15px;color:#1f2933}' +
				'#sp-install-banner .sp-ib-text{font-size:13px;line-height:1.45;color:#52606d}' +
				'#sp-install-banner .sp-ib-actions{display:flex;gap:8px;justify-content:flex-end}' +
				'#sp-install-banner button{font:inherit;font-weight:600;border-radius:9px;padding:9px 16px;border:0;cursor:pointer}' +
				'#sp-install-banner .sp-ib-go{background:<?php echo esc_js( $cfg['theme_color'] ); ?>;color:#fff}' +
				'#sp-install-banner .sp-ib-x{background:#eef1f4;color:#52606d}';
			document.head.appendChild(s);
			document.body.appendChild(bar);
			bar.querySelector('.sp-ib-x').addEventListener('click', function () {
				setDismissed(); bar.remove();
			});
			var go = bar.querySelector('.sp-ib-go');
			if (go && onAction) go.addEventListener('click', function () { onAction(bar); });
		}

		// Android / Chromium: capture the native prompt and offer our own button.
		var deferred = null;
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			deferred = e;
			showBanner(
				'<div class="sp-ib-title">Install ' + SP.name + '</div>' +
				'<div class="sp-ib-text">Add ' + SP.name + ' to your home screen for one-tap, full-screen access.</div>',
				function (bar) {
					bar.remove();
					deferred.prompt();
					deferred.userChoice.finally(function () { setDismissed(); deferred = null; });
				}
			);
		});

		// iOS Safari has no prompt event — show manual Add-to-Home-Screen guidance.
		var ua = window.navigator.userAgent || '';
		var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
		var isSafari = /^((?!chrome|android|crios|fxios|edgios).)*safari/i.test(ua);
		if (isIOS && isSafari) {
			window.addEventListener('load', function () {
				setTimeout(function () {
					showBanner(
						'<div class="sp-ib-title">Install ' + SP.name + '</div>' +
						'<div class="sp-ib-text">Tap the Share button, then <strong>Add to Home Screen</strong> to install ' + SP.name + '.</div>',
						null
					);
				}, 1500);
			});
		}
	})();
	</script>
	<?php
}
