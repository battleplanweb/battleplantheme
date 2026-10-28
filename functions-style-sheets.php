<?php
/* Battle Plan Web Design Functions: Style Sheets

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Layer style sheets

*/

/*--------------------------------------------------------------
# Layer style sheets
--------------------------------------------------------------*/

if (!function_exists('is_plugin_active')) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$GLOBALS['bp_inline_css_buffer'] ??= [];

function bp_get_css_sources() {
    $deferred = ['/style-testimonials.css', '/style-forms.css'];

    if (bp_module_on(get_option('event_calendar'))) $deferred[] = '/style-events.css';
    if (bp_module_on(get_option('timeline'))) $deferred[] = '/style-timeline.css';
    if (is_plugin_active('woocommerce/woocommerce.php')) $deferred[] = '/style-woocommerce.css';
    if (bp_module_on(get_option('site-pulse'))) $deferred[] = '/style-site-pulse.css';
    if (is_plugin_active('cue/cue.php')) $deferred[] = '/style-cue.css';

    // December 1st through the 29th
    if ((customer_info()['cancel-holiday'] ?? 'false') === 'false' && date('n') == 12 && date('j') < 30) {
        $deferred[] = '/style-holiday.css';
    }

    return [
        'critical' => ['/style-normalize.css', '/style.css', '/style-grid.css', '/style-navigation.css'],
        'deferred' => $deferred,
    ];
}

function bp_minify_css($css) {
	$css = preg_replace('!/\*.*?\*/!s', '', $css);
	$css = preg_replace('/\s+/', ' ', $css);

	// Space BEFORE a colon is kept: in a selector it's a descendant combinator (".foo :is(h1)" is not ".foo:is(h1)")
	$css = preg_replace(['/\s*([{};,])\s*/', '/:\s+/'], ['$1', ':'], $css);

	return trim(str_replace(';}', '}', $css));
}

// Write via temp file + rename so a concurrent request never inlines a half-written file
function bp_write_atomic($file, $contents) {
	$tmp = $file . '.' . uniqid('', true) . '.tmp';
	if (file_put_contents($tmp, $contents) === false) return false;
	if (@rename($tmp, $file)) return true;
	@unlink($tmp);
	return false;
}

// Bump when bp_minify_css() output changes, so every site rebuilds its dist/ files once on deploy
const BP_CSS_BUILD_VER = 2;

// Write a compiled css + min pair, then its signature (last, so a failed build retries next request)
function bp_write_css_build($css_file, $min_file, $sig_file, $sig, $css) {
	bp_write_atomic($css_file, $css);
	bp_write_atomic($min_file, bp_minify_css($css));
	bp_write_atomic($sig_file, $sig . '|' . BP_CSS_BUILD_VER);
}

function bp_css_build_needed($css_file, $min_file, $sig_file, $sig) {
	return !file_exists($css_file) || !file_exists($min_file) || !file_exists($sig_file) || file_get_contents($sig_file) !== $sig . '|' . BP_CSS_BUILD_VER;
}

function bp_inline_minified_css($css_file) {
    if (!$css_file || !is_string($css_file) || !file_exists($css_file)) return;

    $key = realpath($css_file);
    if (isset($GLOBALS['bp_inline_css_buffer'][$key])) return;

    $raw = file_get_contents($css_file);
    $GLOBALS['bp_inline_css_buffer'][$key] = [
        'raw' => $raw,
        'min' => bp_minify_css($raw),
    ];
}

function bp_build_css_core() {
    $dist     = get_stylesheet_directory() . '/dist';
    $sources  = bp_get_css_sources();

    $files = [
        'critical' => [
            'css'     => $dist . '/core-critical.css',
            'min'     => $dist . '/core-critical.min.css',
            'sources' => $sources['critical'],
        ],
        'deferred' => [
            'css'     => $dist . '/core-deferred.css',
            'min'     => $dist . '/core-deferred.min.css',
            'sources' => $sources['deferred'],
        ],
    ];

    wp_mkdir_p($dist);

    foreach ( $files as $type => $config ) :
        // Gate on a CONTENT signature, not mtime. mtime is not deploy-stable: git /
        // WP Engine checkouts stamp every file with the same checkout time, so a
        // content-updated source and a stale compiled dist tie on mtime and the old
        // logic ($src_mtime <= $out_mtime) wrongly skipped the rebuild. Hashing the
        // source set + each source's contents rebuilds iff something actually changed,
        // regardless of what mtimes the deploy assigned.
        $sig_parts = [];
        foreach ($config['sources'] as $rel) :
            $path = get_template_directory() . $rel;
            $sig_parts[] = $rel . ':' . (file_exists($path) ? md5_file($path) : 'missing');
        endforeach;
        $sig      = md5(implode('|', $sig_parts));
        $sig_file = $config['css'] . '.sig';

        if (!bp_css_build_needed($config['css'], $config['min'], $sig_file, $sig)) continue;

        $out  = "@charset \"UTF-8\";\n";
        $out .= "/* Battle Plan Core CSS - " . ucfirst($type) . " */\n";
        $out .= "/* Built: " . date('c') . " */\n\n";

        foreach ($config['sources'] as $rel) :
            $path = get_template_directory() . $rel;
            if (!file_exists($path)) :
                $out .= "/* MISSING: {$path} */\n\n";
                continue;
            endif;
            $contents = preg_replace('/@charset\s+["\'][^"\']*["\']\s*;/i', '', file_get_contents($path));
            $out .= "/* ===== " . basename($path) . " ===== */\n";
            $out .= $contents . "\n\n";
        endforeach;

        bp_write_css_build($config['css'], $config['min'], $sig_file, $sig, $out);
    endforeach;
}

function bp_build_site_css() {
	$src = get_stylesheet_directory() . '/style-site.css';
	if (!file_exists($src)) return;

	$dist = get_stylesheet_directory() . '/dist';
	wp_mkdir_p($dist);

	// Gate on a CONTENT signature, not mtime — see bp_build_css_core() for why mtime
	// is unreliable across deploys. md5 of the source content rebuilds iff style-site.css
	// actually changed, no matter what mtime the deploy gave it relative to dist/.
	$css = file_get_contents($src);
	$sig = md5($css);

	if (!bp_css_build_needed($dist . '/site.css', $dist . '/site.min.css', $dist . '/site.css.sig', $sig)) return;

	bp_write_css_build($dist . '/site.css', $dist . '/site.min.css', $dist . '/site.css.sig', $sig, $css);
}

add_action('after_setup_theme', 'bp_build_css_core');
add_action('after_setup_theme', 'bp_build_site_css');

// Path to a compiled dist/ file: unminified for battleplanweb, .min for everyone else ('' if not built)
function bp_dist_css_path($name) {
	$path = get_stylesheet_directory() . '/dist/' . $name . (_USER_LOGIN === 'battleplanweb' ? '.css' : '.min.css');
	return file_exists($path) ? $path : '';
}

add_action('wp_head', function() {
    if (is_admin() || !($path = bp_dist_css_path('core-critical'))) return;

    echo '<style id="battleplan-core-inline">' . file_get_contents($path) . '</style>';
}, 1);

add_action('wp_enqueue_scripts', function () {
    if (!($path = bp_dist_css_path('core-deferred'))) return;

    wp_enqueue_style('battleplan-deferred', get_stylesheet_directory_uri() . '/dist/' . basename($path), [], _BP_VERSION . '.' . filemtime($path));
}, 10);

// The compiled site CSS holds all the above-the-fold hero styling — the :root font/color
// variables, the hero/.headline layout + font-size, and the site @font-face rules. Loading it
// async (media=print/onload, as it used to be) meant the hero could not paint styled until the
// file landed, so on a throttled mobile connection the LCP headline restyled seconds after FCP
// (unstyled → big Blu headline). Inline it in the head — exactly like core-critical above — so
// first paint is already fully styled: no async restyle, no extra render-path request.
// Emitted at priority 11, AFTER the async core-deferred link prints (~9), so site rules still
// win the cascade over the deferred component styles (matching their original enqueue order).
add_action('wp_head', function() {
    if (is_admin()) return;

    // Modules can suppress the inline site CSS on their own screens (e.g. Site Pulse, which is
    // internal staff tooling that shouldn't inherit the client site's branding). This replaces the
    // old wp_dequeue_style('battleplan-site') hook, which no longer works now the CSS is inlined.
    if (!apply_filters('bp_inline_site_css', true) || !($path = bp_dist_css_path('site'))) return;

    echo '<style id="battleplan-site-inline">' . file_get_contents($path) . '</style>' . "\n";
}, 11);

// script-site.js fires its animateDiv/animateDivs/animateGrid calls on window load, so their targets
// paint visible, then blink out when the JS adds .animation-queued, then fade in. Pull the selectors
// out of script-site.js and pre-hide them in the head so they're already at opacity 0 on first paint.
// :not([class*="animation-"]) hands off to .animation-queued once the JS takes over. The hide only
// applies while html.bp-anim-hide is set (JS on), and is lifted 4s after load as a failsafe for
// anything the JS never reaches (conditional calls, script errors).
add_action('wp_head', function() {
    if (is_admin()) return;
    if (!apply_filters('bp_inline_site_css', true)) return;

    $path = get_stylesheet_directory() . '/script-site.js';
    if (!file_exists($path)) return;

    $js = preg_replace('#/\*.*?\*/|(?<![:\'"\\\\])//[^\n]*#s', '', file_get_contents($path));
    if (!preg_match_all('/\b(animateDivs?|animateGrid)\s*\(\s*([\'"])(.+?)\2/', $js, $calls, PREG_SET_ORDER)) return;

    $sels = [];
    foreach ($calls as $call) {
        $sel = trim(str_replace(['<', '{', '}', ';'], '', $call[3]));
        if ($sel === '' || strpos($sel, '${') !== false) continue;
        $sels[] = $call[1] === 'animateGrid' ? ":is($sel):first-child, :is($sel):first-child ~ *" : ":is($sel)";
    }
    if (!$sels) return;

    echo '<style id="bp-animate-prehide">html.bp-anim-hide :is(' . implode(', ', array_unique($sels)) . '):not([class*="animation-"]){opacity:0}</style>' . "\n";
    echo '<script nonce="' . esc_attr(_BP_NONCE) . '" data-cfasync="false">document.documentElement.classList.add("bp-anim-hide");addEventListener("load",function(){setTimeout(function(){document.documentElement.classList.remove("bp-anim-hide")},4000)});</script>' . "\n";
}, 12);

add_filter('style_loader_tag', function($tag, $handle) {
    if ( is_admin() || $handle !== 'battleplan-deferred' ) return $tag;

    $async = "media='print' onload=\"this.onload=null;this.media='all'\"";
    $html  = str_replace([' />', "media='all'", 'media="all"'], ['>', $async, $async], $tag);

    $noscript = preg_replace(['/\smedia=(["\'])print\1/', '/\sid=(["\'])[^"\']*\1/', '/ onload="[^"]*"/'], '', $html, 1);

    return $html . "<noscript>" . $noscript . "</noscript>\n";
}, 10, 2);

add_action('wp_footer', function () {
	if (is_admin() || empty($GLOBALS['bp_inline_css_buffer'])) return;

	echo "<style id='bp-shortcode-css'>\n";

	foreach ($GLOBALS['bp_inline_css_buffer'] as $css) {
		echo _USER_LOGIN === 'battleplanweb' ? $css['raw'] : $css['min'];
		echo "\n";
	}

	echo "</style>\n";
}, 20);


function bp_enqueue_script( $handle, $base, $deps = [], $args = [] ) {

    $args = array_merge([
        'in_footer' => true,
        'scope'     => 'framework', // framework | site
    ], $args);

    // Resolve directories
    if ($args['scope'] === 'site') {
        $dir = get_stylesheet_directory() . '/';
        $uri = get_stylesheet_directory_uri() . '/';
    } else {
        $dir = get_template_directory() . '/js/';
        $uri = get_template_directory_uri() . '/js/';
    }

    unset($args['scope']);

    $dev = "{$base}.js";
    $min = "{$base}.min.js";

    // Prefer dev for you, min for everyone else
    $files = (_USER_LOGIN === 'battleplanweb') ? [$dev, $min] : [$min, $dev];

    foreach ($files as $file) {
        $path = $dir . $file;
        if (file_exists($path)) {
            // Cache-bust on the file's own mtime (like the CSS build does), so an updated
            // .min.js gets a new ?ver on every deploy — otherwise browsers + Cloudflare keep
            // serving the old file even after a purge, because the URL never changed.
            wp_enqueue_script( $handle, $uri . $file, $deps, _BP_VERSION . '.' . filemtime($path), $args );
            return;
        }
    }
}
