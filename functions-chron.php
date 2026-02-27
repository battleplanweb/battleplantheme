<?php
/* Battle Plan Web Design Functions: Chron Orchestrator */

require_once get_template_directory() . '/functions-chron-helpers.php';


$current_user = wp_get_current_user();
$is_override  = ($current_user && $current_user->user_login === 'battleplanweb');

if (get_transient('bp_chron_jobs_lock') && !$is_override) {
    //error_log('BP Chron Jobs locked');
    return;
}

set_transient('bp_chron_jobs_lock', 1, 3);

if (!is_admin() && isset($_SERVER['REQUEST_URI']) && preg_match('#sitemap(_index)?\.xml|/wp-sitemap\.xml|/feed/#i', $_SERVER['REQUEST_URI'])) return;

require_once get_template_directory() . '/vendor/autoload.php';


/*--------------------------------------------------------------
# Helpers
--------------------------------------------------------------*/

/**
 * Returns a Unix timestamp landing in the 10pm-5am overnight window
 * in the site's local timezone, with up to 7 hours of random spread.
 * 22 = 10pm (start of window); rand(0, 25200) adds up to 7 hours.
 */
function bp_next_nightly_window(int $startHour = 22): int {
    $tzString = get_option('timezone_string') ?: 'America/New_York';

    try {
        $tz = new DateTimeZone($tzString);
    } catch (Exception $e) {
        // If the stored value is a UTC offset format rather than a named timezone,
        // fall back to Eastern
        $tz = new DateTimeZone('America/New_York');
    }

    $now    = new DateTime('now', $tz);
    $target = clone $now;
    $target->setTime($startHour, 0, 0);

    if ($target <= $now) {
        $target->modify('+1 day');
    }

    // Spread randomly across the full 7-hour window (10pm–5am = 25200 seconds)
    return $target->getTimestamp() + rand(0, 25200);
}


/*--------------------------------------------------------------
# One-time cleanup (plugin/theme)
--------------------------------------------------------------*/

add_action('init', function () {
    $key = 'bp_theme_plugin_cleanup_2026-02-14';
    if (add_site_option($key, current_time('mysql'))) {

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        $plugins_remove = [
            'huzzaz-video-gallery/huzzaz.php',
            'admin-columns-pro/admin-columns-pro.php',
            'wp-crontrol/wp-crontrol.php',
            'blackhole-bad-bots/blackhole.php',
        ];
        $plugins_deactivate = [
            'better-search-replace/better-search-replace.php',
            'query-monitor/query-monitor.php',
        ];

        foreach ($plugins_deactivate as $plugin) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $plugin) && is_plugin_active($plugin)) {
                deactivate_plugins($plugin, true);
            }
        }

        foreach ($plugins_remove as $plugin) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
                if (is_plugin_active($plugin)) deactivate_plugins($plugin, true);
                delete_plugins([$plugin]);
            }
        }

        $themes = [
            'twentytwentyone', 'twentytwentytwo', 'twentytwentythree',
            'twentytwentyfour', 'twentytwentyfive',
        ];

        foreach ($themes as $theme) {
            if ($theme === get_stylesheet() || $theme === get_template()) continue;
            $theme_obj = wp_get_theme($theme);
            if ($theme_obj->exists()) delete_theme($theme);
        }

        $bp_guard_dir = WP_CONTENT_DIR . '/themes/bp-guard';
        if (is_dir($bp_guard_dir)) {
            foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($bp_guard_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            ) as $file) {
                $file->isDir() ? rmdir($file) : unlink($file);
            }
            rmdir($bp_guard_dir);
        }
    }
});

require_once get_template_directory() . '/functions-chron-helpers.php';

battleplan_delete_prefixed_options('bp_setup_');
battleplan_delete_prefixed_options('bp_product_upload_');


/*--------------------------------------------------------------
# Chron A — GBP + CI Sync
--------------------------------------------------------------*/

$forceA   = filter_var(get_option('bp_force_chron_a', false), FILTER_VALIDATE_BOOLEAN);
$lastRunA = (int) get_option('bp_chron_a_time', 0);
$nextA    = (int) get_option('bp_chron_a_next', 0);
$neverA   = $lastRunA === 0;

// Interval from customer_info audit_delay, default 90 days
$customerInfo  = customer_info();
$auditInterval = isset($customerInfo['audit_delay'])
    ? (int) $customerInfo['audit_delay']
    : (86400 * 90);

$staleA = !$neverA && (time() - $lastRunA) > ($auditInterval * 1.5);

if ($nextA <= 0) {
    $nextA = bp_next_nightly_window();
    update_option('bp_chron_a_next', $nextA);
}

$dueA         = time() >= $nextA;
$autoTriggerA = _IS_BOT && !_IS_SERP_BOT && ($neverA || $staleA || $dueA);

if ($forceA || $autoTriggerA) {
    delete_option('bp_force_chron_a');
    update_option('bp_chron_a_time', time());
    update_option('bp_chron_a_next', bp_next_nightly_window());
    require_once get_template_directory() . '/functions-chron-gbp.php';
    bp_run_chron_gbp($forceA);
}


/*--------------------------------------------------------------
# Chron B — Housekeeping
--------------------------------------------------------------*/

$forceB   = filter_var(get_option('bp_force_chron_b', false), FILTER_VALIDATE_BOOLEAN);
$lastRunB = (int) get_option('bp_chron_b_time', 0);
$nextB    = (int) get_option('bp_chron_b_next', 0);
$neverB   = $lastRunB === 0;

$staleB = !$neverB && (time() - $lastRunB) > (86400 * 3);

if ($nextB <= 0) {
    $nextB = bp_next_nightly_window();
    update_option('bp_chron_b_next', $nextB);
}

$dueB         = time() >= $nextB;
$autoTriggerB = _IS_BOT && !_IS_SERP_BOT && ($neverB || $staleB || $dueB);

if ($forceB || $autoTriggerB) {
    delete_option('bp_force_chron_b');
    update_option('bp_chron_b_time', time());
    update_option('bp_chron_b_next', bp_next_nightly_window());
    require_once get_template_directory() . '/functions-chron-housekeeping.php';
    bp_run_chron_housekeeping($forceB);
}


/*--------------------------------------------------------------
# Chron C — Analytics
--------------------------------------------------------------*/

$forceC   = filter_var(get_option('bp_force_chron_c', false), FILTER_VALIDATE_BOOLEAN);
$lastRunC = (int) get_option('bp_chron_c_time', 0);
$nextC    = (int) get_option('bp_chron_c_next', 0);
$neverC   = $lastRunC === 0;

$staleC = !$neverC && (time() - $lastRunC) > (86400 * 3);

if ($nextC <= 0) {
    $nextC = bp_next_nightly_window();
    update_option('bp_chron_c_next', $nextC);
}

$dueC         = time() >= $nextC;
$autoTriggerC = _IS_BOT && !_IS_SERP_BOT && ($neverC || $staleC || $dueC);

if ($forceC || $autoTriggerC) {
    delete_option('bp_force_chron_c');
    update_option('bp_chron_c_time', time());
    update_option('bp_chron_c_next', bp_next_nightly_window());
    require_once get_template_directory() . '/functions-chron-analytics.php';
    bp_run_chron_analytics($forceC);
}


/*--------------------------------------------------------------
# Shared helpers (used across multiple chron files)
--------------------------------------------------------------*/

function battleplan_delete_prefixed_options(string $prefix): void {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'");
}

