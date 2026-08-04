<?php
/**
 * Customer Connect — Progressive Web App layer
 * ---------------------------------------------------------------------------
 * Makes the customer app installable on phones/desktops: a dynamic web
 * manifest, an offline-shell service worker, iOS meta, and an install prompt.
 * Standalone — copied from the Site Pulse PWA pattern, namespaced cc_*, with no
 * dependency on Site Pulse (the two modules are independent by design).
 *
 * Two virtual, file-less endpoints intercepted early on `init` (root-scoped so
 * one worker controls the whole app):
 *     /customer-connect-app.webmanifest
 *     /customer-connect-sw            (extensionless on purpose — nginx on WP Engine
 *                               serves any *.js URL from disk and 404s before
 *                               PHP runs, so a virtual "*.js" can't be caught)
 *
 * @package battleplan
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Bump on any SW change to force every installed client to fetch a fresh worker.
if ( ! defined( 'CUSTOMER_CONNECT_PWA_VERSION' ) ) define( 'CUSTOMER_CONNECT_PWA_VERSION', '1.0.2' );

if ( ! defined( 'CUSTOMER_CONNECT_PWA_MANIFEST_FILE' ) ) define( 'CUSTOMER_CONNECT_PWA_MANIFEST_FILE', 'customer-connect-app.webmanifest' );
if ( ! defined( 'CUSTOMER_CONNECT_PWA_SW_FILE' ) )       define( 'CUSTOMER_CONNECT_PWA_SW_FILE', 'customer-connect-sw' );


/*--------------------------------------------------------------
# Config
--------------------------------------------------------------*/

/** Resolved PWA config for this site. Filterable via `customer_connect_pwa_config`. */
function cc_pwa_config(): array {
	$app_name = function_exists( 'cc_app_name' ) ? cc_app_name() : 'Customer Connect';

	$short = (string) ( function_exists( 'cc_get' ) ? cc_get( 'pwa_short_name', '' ) : '' );
	if ( $short === '' ) {
		$first = trim( (string) ( explode( ' ', trim( $app_name ) )[0] ?? '' ) );
		$short = $first !== '' ? $first : $app_name;
	}
	$short = mb_substr( $short, 0, 12 );

	$start = home_url( '/' . ( defined( 'CUSTOMER_CONNECT_SLUG' ) ? CUSTOMER_CONNECT_SLUG : 'customer-connect' ) . '/' );
	// Scope the PWA to its OWN app path, NOT the site root. A root scope makes the service worker
	// control the ENTIRE site and intercept every request (it was breaking the Site Pulse login and
	// dashboard). Scoped here, the worker only ever governs /customer-connect/.
	$app_path  = (string) wp_parse_url( $start, PHP_URL_PATH );
	$scope     = $app_path !== '' ? trailingslashit( $app_path ) : '/';

	$cfg = [
		'name'        => $app_name,
		'short_name'  => $short,
		'theme_color' => (string) ( function_exists( 'cc_theme_color' ) ? cc_theme_color() : '#0f766e' ),
		'bg_color'    => (string) ( function_exists( 'cc_get' ) ? cc_get( 'pwa_background_color', '#ffffff' ) : '#ffffff' ),
		'start_url'   => $start,
		'scope_path'  => $scope,
	];
	return apply_filters( 'customer_connect_pwa_config', $cfg );
}


/*--------------------------------------------------------------
# Virtual endpoints (manifest + service worker)
--------------------------------------------------------------*/

add_action( 'init', function () {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;
	$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( $path === '' ) return;
	$base = basename( $path );
	if ( $base === CUSTOMER_CONNECT_PWA_MANIFEST_FILE ) cc_pwa_render_manifest();
	if ( $base === CUSTOMER_CONNECT_PWA_SW_FILE )       cc_pwa_render_sw();
}, 0 );

function cc_pwa_render_manifest(): void {
	$cfg = cc_pwa_config();

	$manifest = [
		'id'               => $cfg['scope_path'],
		'name'             => $cfg['name'],
		'short_name'       => $cfg['short_name'],
		'description'      => $cfg['name'] . ' — your home comfort companion',
		'start_url'        => $cfg['start_url'],
		'scope'            => $cfg['scope_path'],
		'display'          => 'standalone',
		'orientation'      => 'portrait',
		'background_color' => $cfg['bg_color'],
		'theme_color'      => $cfg['theme_color'],
		'lang'             => 'en-US',
		'dir'              => 'ltr',
		'icons'            => cc_pwa_icons(),
	];

	if ( ! headers_sent() ) {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=300' );
	}
	echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	exit;
}

/**
 * Manifest icons. Phase (a) points straight at a source image (browsers accept
 * png/webp/jpg manifest icons) — child-theme images/app-icon.* → WP Site Icon.
 * GD-generated padded/maskable sets can be added later (see the SP PWA module).
 */
function cc_pwa_icons(): array {
	$url = cc_pwa_source_url();
	if ( $url === '' ) return [];
	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$type = [ 'png' => 'image/png', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg' ][ $ext ] ?? 'image/png';
	return [
		[ 'src' => $url, 'sizes' => '512x512', 'type' => $type, 'purpose' => 'any' ],
		[ 'src' => $url, 'sizes' => '512x512', 'type' => $type, 'purpose' => 'maskable' ],
	];
}

/** Resolve the app-icon URL: config → child-theme images/app-icon.* → Site Icon. */
function cc_pwa_source_url(): string {
	$explicit = (string) ( function_exists( 'cc_get' ) ? cc_get( 'pwa_icon_url', '' ) : '' );
	if ( $explicit !== '' ) return $explicit;

	foreach ( [ 'png', 'webp', 'jpg', 'jpeg' ] as $ext ) {
		$file = get_stylesheet_directory() . '/images/app-icon.' . $ext;
		if ( file_exists( $file ) ) return get_stylesheet_directory_uri() . '/images/app-icon.' . $ext;
	}
	$sid = (int) get_option( 'site_icon' );
	if ( $sid ) {
		$src = wp_get_attachment_image_url( $sid, 'full' );
		if ( $src ) return $src;
	}
	return '';
}

function cc_pwa_render_sw(): void {
	$cfg       = cc_pwa_config();
	$cache_ver = CUSTOMER_CONNECT_PWA_VERSION;
	$app_name  = $cfg['name'];
	$theme     = $cfg['theme_color'];
	$bg        = $cfg['bg_color'];

	if ( ! headers_sent() ) {
		header( 'Content-Type: text/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . $cfg['scope_path'] );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	}

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
		esc_html( $app_name ), esc_html( $bg ), esc_html( $theme )
	);
	$offline_json = wp_json_encode( $offline_html );
	?>
/* Customer Connect service worker — v<?php echo $cache_ver; ?> */
'use strict';

const CC_CACHE = 'customer-connect-v<?php echo esc_js( $cache_ver ); ?>';
const OFFLINE_HTML = <?php echo $offline_json; ?>;
const CC_HOME = '<?php echo esc_js( $cfg['start_url'] ); ?>';

const ASSET_RE = /\.(?:css|js|mjs|woff2?|ttf|otf|eot|png|jpe?g|webp|avif|svg|gif|ico)(?:\?|$)/i;

function isBypass(url, req) {
	if (req.method !== 'GET') return true;
	const p = url.pathname;
	return p.indexOf('/wp-admin/') === 0
		|| p.indexOf('/wp-json/') === 0
		|| p.indexOf('/wp-login.php') === 0
		|| p.indexOf('admin-ajax.php') !== -1
		|| p.indexOf('<?php echo esc_js( CUSTOMER_CONNECT_PWA_SW_FILE ); ?>') !== -1
		|| p.indexOf('<?php echo esc_js( CUSTOMER_CONNECT_PWA_MANIFEST_FILE ); ?>') !== -1;
}

self.addEventListener('install', () => { self.skipWaiting(); });

self.addEventListener('activate', (e) => {
	e.waitUntil((async () => {
		const keys = await caches.keys();
		await Promise.all(keys.filter((k) => k !== CC_CACHE).map((k) => caches.delete(k)));
		await self.clients.claim();
	})());
});

self.addEventListener('fetch', (event) => {
	const req = event.request;
	let url;
	try { url = new URL(req.url); } catch (_) { return; }
	if (isBypass(url, req)) return;

	// Navigations: network-first, offline fallback.
	if (req.mode === 'navigate') {
		event.respondWith((async () => {
			try { return await fetch(req); }
			catch (_) {
				const cached = await caches.match(req);
				return cached || new Response(OFFLINE_HTML, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
			}
		})());
		return;
	}

	// Same-origin static assets: stale-while-revalidate.
	if (url.origin === self.location.origin && ASSET_RE.test(url.pathname)) {
		event.respondWith((async () => {
			const cache = await caches.open(CC_CACHE);
			const cached = await cache.match(req);
			const network = fetch(req).then((res) => {
				if (res && res.status === 200 && res.type === 'basic') cache.put(req, res.clone());
				return res;
			}).catch(() => null);
			return cached || (await network) || fetch(req);
		})());
	}
});

// Web Push — phase (c) wires client→customer delivery. A payload push shows its
// own title/body; the app-icon badge tracks unread count.
self.addEventListener('push', (event) => {
	let data = { title: '<?php echo esc_js( $app_name ); ?>', body: 'You have a new update.', url: CC_HOME };
	try { if (event.data) data = Object.assign(data, event.data.json()); } catch (_) {}
	event.waitUntil((async () => {
		await self.registration.showNotification(data.title, {
			body: data.body, tag: data.tag || 'customer-connect', renotify: true,
			data: { url: data.url || CC_HOME }
		});
		if (typeof data.count === 'number' && 'setAppBadge' in self.navigator) {
			try { data.count > 0 ? self.navigator.setAppBadge(data.count) : self.navigator.clearAppBadge(); } catch (_) {}
		}
	})());
});

self.addEventListener('notificationclick', (event) => {
	event.notification.close();
	const target = (event.notification.data && event.notification.data.url) || CC_HOME;
	event.waitUntil((async () => {
		const wins = await clients.matchAll({ type: 'window', includeUncontrolled: true });
		for (const w of wins) { if ('focus' in w) { try { await w.navigate(target); } catch (_) {} return w.focus(); } }
		if (clients.openWindow) return clients.openWindow(target);
	})());
});
	<?php
	exit;
}


/*--------------------------------------------------------------
# <head> tags + service-worker registration + install prompt
--------------------------------------------------------------*/

add_action( 'wp_head', 'cc_pwa_head', 5 );

function cc_pwa_head(): void {
	if ( ! function_exists( 'cc_is_app_page' ) || ! cc_is_app_page() ) return;

	$cfg          = cc_pwa_config();
	$manifest_url = home_url( '/' . CUSTOMER_CONNECT_PWA_MANIFEST_FILE );
	$sw_url       = home_url( '/' . CUSTOMER_CONNECT_PWA_SW_FILE );
	$apple_icon   = cc_pwa_source_url();
	$nonce        = defined( '_BP_NONCE' ) ? _BP_NONCE : '';

	echo "\n\t<!-- Customer Connect PWA -->\n";
	printf( "\t<link rel=\"manifest\" href=\"%s\">\n", esc_url( $manifest_url ) );
	printf( "\t<meta name=\"theme-color\" content=\"%s\">\n", esc_attr( $cfg['theme_color'] ) );
	echo   "\t<meta name=\"mobile-web-app-capable\" content=\"yes\">\n";
	echo   "\t<meta name=\"apple-mobile-web-app-capable\" content=\"yes\">\n";
	echo   "\t<meta name=\"apple-mobile-web-app-status-bar-style\" content=\"black-translucent\">\n";
	printf( "\t<meta name=\"apple-mobile-web-app-title\" content=\"%s\">\n", esc_attr( $cfg['short_name'] ) );
	if ( $apple_icon !== '' ) printf( "\t<link rel=\"apple-touch-icon\" href=\"%s\">\n", esc_url( $apple_icon ) );

	$data = wp_json_encode( [ 'sw' => $sw_url, 'scope' => $cfg['scope_path'], 'name' => $cfg['name'] ] );
	?>
	<script<?php echo $nonce ? ' nonce="' . esc_attr( $nonce ) . '"' : ''; ?>>
	(function () {
		var CC = <?php echo $data; ?>;
		if ('serviceWorker' in navigator) {
			window.addEventListener('load', function () {
				navigator.serviceWorker.register(CC.sw, { scope: CC.scope }).catch(function () {});
			});
		}
		var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
		if (standalone) return;

		var DISMISS_KEY = 'cc_pwa_install_dismissed';
		function dismissed() { try { return localStorage.getItem(DISMISS_KEY) === '1'; } catch (e) { return false; } }
		function setDismissed() { try { localStorage.setItem(DISMISS_KEY, '1'); } catch (e) {} }

		function showBanner(html, onAction) {
			if (dismissed() || document.getElementById('cc-install-banner')) return;
			var bar = document.createElement('div');
			bar.id = 'cc-install-banner';
			bar.setAttribute('role', 'dialog');
			bar.innerHTML = html +
				'<div class="cc-ib-actions">' +
				(onAction ? '<button type="button" class="cc-ib-go">Install</button>' : '') +
				'<button type="button" class="cc-ib-x" aria-label="Dismiss">Not now</button></div>';
			var s = document.createElement('style');
			s.textContent =
				'#cc-install-banner{position:fixed;left:12px;right:12px;bottom:12px;z-index:99999;' +
				'background:#fff;border:1px solid #d9dde2;border-radius:14px;padding:14px 16px;' +
				'box-shadow:0 8px 30px rgba(0,0,0,.18);font-family:inherit;max-width:460px;margin:0 auto;' +
				'display:flex;flex-direction:column;gap:10px}' +
				'#cc-install-banner .cc-ib-title{font-weight:700;font-size:15px;color:#1f2933}' +
				'#cc-install-banner .cc-ib-text{font-size:13px;line-height:1.45;color:#52606d}' +
				'#cc-install-banner .cc-ib-actions{display:flex;gap:8px;justify-content:flex-end}' +
				'#cc-install-banner button{font:inherit;font-weight:600;border-radius:9px;padding:9px 16px;border:0;cursor:pointer}' +
				'#cc-install-banner .cc-ib-go{background:<?php echo esc_js( $cfg['theme_color'] ); ?>;color:#fff}' +
				'#cc-install-banner .cc-ib-x{background:#eef1f4;color:#52606d}';
			document.head.appendChild(s);
			document.body.appendChild(bar);
			bar.querySelector('.cc-ib-x').addEventListener('click', function () { setDismissed(); bar.remove(); });
			var go = bar.querySelector('.cc-ib-go');
			if (go && onAction) go.addEventListener('click', function () { onAction(bar); });
		}

		var deferred = null;
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault(); deferred = e;
			showBanner(
				'<div class="cc-ib-title">Install ' + CC.name + '</div>' +
				'<div class="cc-ib-text">Add ' + CC.name + ' to your home screen for one-tap access.</div>',
				function (bar) {
					bar.remove(); deferred.prompt();
					deferred.userChoice.finally(function () { setDismissed(); deferred = null; });
				}
			);
		});

		var ua = window.navigator.userAgent || '';
		var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
		var isSafari = /^((?!chrome|android|crios|fxios|edgios).)*safari/i.test(ua);
		if (isIOS && isSafari) {
			window.addEventListener('load', function () {
				setTimeout(function () {
					showBanner(
						'<div class="cc-ib-title">Install ' + CC.name + '</div>' +
						'<div class="cc-ib-text">Tap the Share button, then <strong>Add to Home Screen</strong>.</div>',
						null
					);
				}, 1500);
			});
		}
	})();
	</script>
	<?php
}
