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

if (!isset($GLOBALS['bp_inline_css_buffer'])) {
	$GLOBALS['bp_inline_css_buffer'] = [];
}

function bp_get_css_sources() {

	$customer_info = customer_info();
	$sources = [];

	$sources[] = "/style-normalize.css";
	$sources[] = '/style.css';

	$sources[] = '/style-grid.css';
	$sources[] = '/style-navigation.css';
	$sources[] = '/style-testimonials.css';

	$event_calendar = get_option('event_calendar');
	if (is_array($event_calendar) && ($event_calendar['install'] ?? null) === 'true') {
		$sources[] = '/style-events.css';
	}

	$timeline = get_option('timeline');
	if (is_array($timeline) && ($timeline['install'] ?? null) === 'true') {
		$sources[] = '/style-timeline.css';
	}

	if ( is_plugin_active('woocommerce/woocommerce.php') ) {
		$sources[] = '/style-woocommerce.css';
	}

	if ( is_plugin_active('stripe-payments/accept-stripe-payments.php') ) {
		//$sources[] = '/style-stripe-payments.css';
	}

	if ( is_plugin_active('cue/cue.php') ) {
		$sources[] = '/style-cue.css';
	}

	if (
		isset($customer_info['site-type']) &&
		in_array($customer_info['site-type'], ['profile','profiles'], true)
	) {
		$sources[] = '/style-user-profiles.css';
	}

	$start = strtotime(date("Y").'-12-01');
	$end   = strtotime(date("Y").'-12-30');

	if (
		($customer_info['cancel-holiday'] ?? 'false') === 'false' &&
		time() > $start && time() < $end
	) {
		$sources[] = '/style-holiday.css';
	}

	$sources[] = '/style-forms.css';

	return $sources;
}


function bp_minify_css($css) {

	// Remove comments
	$css = preg_replace('!/\*.*?\*/!s', '', $css);

	// Remove whitespace
	$css = preg_replace('/\s+/', ' ', $css);

	// Remove space around symbols
	$css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);

	// Remove trailing semicolons
	$css = str_replace(';}', '}', $css);

	// Trim
	return trim($css);
}

function bp_inline_minified_css($css_file) {

	if (!$css_file || !is_string($css_file) || !file_exists($css_file)) return;

	$css_raw = file_get_contents($css_file);

	// Use file identity, not contents
	$key = realpath($css_file);

	$GLOBALS['bp_inline_css_buffer'][$key] = [
		'raw' => $css_raw,
		'min' => bp_minify_css($css_raw),
	];
}



function bp_build_css_core() {
	$dist = get_stylesheet_directory() . '/dist';
	$core     = $dist . '/core.css';
	$core_min = $dist . '/core.min.css';

	$missing = !file_exists($core) || !file_exists($core_min);
	if ( !$missing && ( (is_admin() && !wp_doing_ajax()) || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI))	) return;

	$sources = bp_get_css_sources();

	if (!is_dir($dist)) wp_mkdir_p($dist);

	$core_mtime = max(
		file_exists($core)     ? filemtime($core)     : 0,
		file_exists($core_min) ? filemtime($core_min) : 0
	);

	$latest_src = 0;
	foreach ($sources as $rel) {
		$path = get_template_directory() . $rel;
		if (file_exists($path)) {
			$latest_src = max($latest_src, filemtime($path));
		}
	}

	if (!$missing && $latest_src <= $core_mtime) {
		return;
	}

	$out  = "/* Battle Plan Core CSS */\n";
	$out .= "/* Built: " . date('c') . " */\n\n";

	foreach ($sources as $rel) {
		$path = get_template_directory() . $rel;
		if (!file_exists($path)) {
			$out .= "/* MISSING: {$path} */\n\n";
			continue;
		}

		$out .= "/* ===== " . basename($path) . " ===== */\n";
		$out .= file_get_contents($path) . "\n\n";
	}

	file_put_contents($core, $out);
	file_put_contents($core_min, bp_minify_css($out));
}

function bp_build_site_css() {

	$src  = get_stylesheet_directory() . '/style-site.css';
	if (!file_exists($src)) return;

	$dist = get_stylesheet_directory() . '/dist';
	$site     = $dist . '/site.css';
	$site_min = $dist . '/site.min.css';

	if (!is_dir($dist)) {
		wp_mkdir_p($dist);
	}

	$missing = !file_exists($site) || !file_exists($site_min);

	$src_mtime = filemtime($src);
	$out_mtime = max(
		file_exists($site)     ? filemtime($site)     : 0,
		file_exists($site_min) ? filemtime($site_min) : 0
	);

	if (!$missing && $src_mtime <= $out_mtime) {
		return;
	}

	$css = file_get_contents($src);

	file_put_contents($site, $css);
	file_put_contents($site_min, bp_minify_css($css));
}


add_action('after_setup_theme', 'bp_build_css_core');
add_action('after_setup_theme', 'bp_build_site_css');

add_action('wp_enqueue_scripts', function () {

	$file = _USER_LOGIN === 'battleplanweb' ? 'core.css' : 'core.min.css';

	$path = get_stylesheet_directory() . '/dist/' . $file;
	$url  = get_stylesheet_directory_uri() . '/dist/' . $file;

	if (!file_exists($path)) return;

	wp_enqueue_style( 'battleplan-core', $url, [], _BP_VERSION );
}, 1);

add_action('wp_enqueue_scripts', function () {

	$file = _USER_LOGIN === 'battleplanweb' ? 'site.css' : 'site.min.css';

	$path = get_stylesheet_directory() . '/dist/' . $file;
	$url  = get_stylesheet_directory_uri() . '/dist/' . $file;

	if (!file_exists($path)) return;

	wp_enqueue_style( 'battleplan-site', $url, ['battleplan-core'], _BP_VERSION );

}, 20);

add_filter('style_loader_tag', function ($html, $handle) {

	if ($handle !== 'battleplan-site') {
		return $html;
	}

	// Already deferred? leave it
	if (strpos($html, "onload=\"this.media='all'\"") !== false) {
		return $html;
	}

	// Replace ANY existing media attr with print + onload
	if (preg_match('/\smedia=/', $html)) {
		$html = preg_replace(
			'/\smedia=(["\']).*?\1/',
			" media='print' onload=\"this.media='all'\"",
			$html,
			1
		);
	} else {
		// No media attribute at all
		$html = str_replace(
			'<link ',
			"<link media='print' onload=\"this.media='all'\" ",
			$html
		);
	}

	// Noscript fallback
	$html .= "<noscript>" . preg_replace(
		'/\smedia=(["\'])print\1/',
		'',
		$html,
		1
	) . "</noscript>";

	return $html;

}, 10, 2);



add_action('wp_footer', function () {

	if (is_admin() || empty($GLOBALS['bp_inline_css_buffer'])) return;

	echo "<style id='bp-shortcode-css'>\n";

	foreach ($GLOBALS['bp_inline_css_buffer'] as $css) {
		error_log(_USER_LOGIN);
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
            wp_enqueue_script( $handle, $uri . $file, $deps, _BP_VERSION, $args );
            return;
        }
    }
}
