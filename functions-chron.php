<?php
/* Battle Plan Web Design Functions: Chron Orchestrator */

require_once get_template_directory() . '/functions-chron-helpers.php';


if (get_transient('bp_chron_jobs_lock') && _USER_LOGIN !== 'battleplanweb') return;

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
    try {
        $tz = new DateTimeZone(get_option('timezone_string') ?: 'America/New_York');
    } catch (Exception $e) {
        // If the stored value is a UTC offset format rather than a named timezone, fall back to Eastern
        $tz = new DateTimeZone('America/New_York');
    }

    $now    = new DateTime('now', $tz);
    $target = (clone $now)->setTime($startHour, 0, 0);
    if ($target <= $now) $target->modify('+1 day');

    // Spread randomly across the full 7-hour window (10pm–5am = 25200 seconds)
    return $target->getTimestamp() + rand(0, 25200);
}

/**
 * Should chron slot $slot (a-e) run on this request? Runs when forced (bp_force_chron_{slot}), or on a
 * non-SERP bot hit when it has never run, its scheduled bp_chron_{slot}_next has arrived, or the last run
 * is older than $staleAfter seconds. If it runs, the slot is stamped (time + next window, plus
 * $nextOffset seconds) BEFORE the job starts and the force flag is returned; otherwise null.
 */
function bp_chron_claim(string $slot, int $staleAfter, int $nextOffset = 0): ?bool {
    $force   = filter_var(get_option("bp_force_chron_$slot", false), FILTER_VALIDATE_BOOLEAN);
    $lastRun = (int) get_option("bp_chron_{$slot}_time", 0);
    $next    = (int) get_option("bp_chron_{$slot}_next", 0);

    if ($next <= 0) {
        $next = bp_next_nightly_window();
        update_option("bp_chron_{$slot}_next", $next);
    }

    $due = $lastRun === 0 || (time() - $lastRun) > $staleAfter || time() >= $next;
    if (!$force && !(_IS_BOT && !_IS_SERP_BOT && $due)) return null;

    delete_option("bp_force_chron_$slot");
    update_option("bp_chron_{$slot}_time", time());
    update_option("bp_chron_{$slot}_next", bp_next_nightly_window() + $nextOffset);
    return $force;
}


/*--------------------------------------------------------------
# Chron A — GBP + CI Sync
--------------------------------------------------------------*/

// Stale after 1.5x customer_info audit_delay (default 90 days)
$auditInterval = (int) (customer_info()['audit_delay'] ?? 86400 * 90);

if (($forceA = bp_chron_claim('a', (int) ($auditInterval * 1.5))) !== null) {
    require_once get_template_directory() . '/functions-chron-gbp.php';
    bp_run_chron_gbp($forceA);
}


/*--------------------------------------------------------------
# Chron B — Housekeeping
--------------------------------------------------------------*/

if (($forceB = bp_chron_claim('b', 86400)) !== null) {
    require_once get_template_directory() . '/functions-chron-housekeeping.php';
    bp_run_chron_housekeeping($forceB);   // runs the dated one-off jobs too
}


/*--------------------------------------------------------------
# Chron C — Analytics
--------------------------------------------------------------*/

if (($forceC = bp_chron_claim('c', 86400 * 3)) !== null) {
    require_once get_template_directory() . '/functions-chron-analytics.php';
    bp_run_chron_analytics($forceC);
}


/*--------------------------------------------------------------
# Chron D — Keyword Rankings
--------------------------------------------------------------*/

if (($forceD = bp_chron_claim('d', 86400 * 7, 86400 * 6)) !== null) {
    require_once get_template_directory() . '/functions-keyword-rankings.php';
    bp_kw_run_chron($forceD);
}


/*--------------------------------------------------------------
# Chron E — Fleet Pulse
--------------------------------------------------------------*/

// Deliberately LAST in this file: A-D may have just run in this same request, and the
// snapshot reads the options they write. Reporting before them would ship yesterday's
// numbers and — worse — make every chron look a day staler than it is.
if (bp_chron_claim('e', 86400) !== null) {
    // The hub can't sign a request to itself (bpgbp_client_autopair() skips it), so it
    // stores its own snapshot in-process instead of HTTP-looping back to localhost.
    if (function_exists('sp_fleet_is_hub') && sp_fleet_is_hub()) {
        do_action('bp_fleet_capture_self');
    } else {
        require_once get_template_directory() . '/includes/includes-fleet-pulse-client.php';
        bp_fleet_client_push();
    }
}



/*--------------------------------------------------------------
# One-Off Tasks
#
# Dated, run-once jobs. They're called from bp_run_chron_housekeeping(), so they ride
# Chron B's nightly slot (a bot hit inside the 10pm-5am window, not a real visitor's
# page view) AND can be forced on demand from Dashboard → Settings.
# Each job carries its own option gate, so it fires exactly once per install and
# is a no-op forever after — safe to leave in place, safe to delete once the
# whole fleet has caught up.
#
# Use bp_load_product_file() / bp_one_off_complete() rather than requiring the file and
# writing the option by hand — see their notes below for why.
--------------------------------------------------------------*/

function bp_run_one_off_tasks(): void {

    if (get_option('bp_product_upload_2026-07-27') !== 'completed' && bp_site_sells_brand('american standard')) {
        bp_load_product_file('american-standard');
        bp_load_product_file('samsung');
        bp_one_off_complete('bp_product_upload_2026-07-27');
    }

    if (get_option('bp_product_upload_2026-08-07') !== 'completed' && bp_site_sells_brand('american standard')) {
        if (bp_brand_photos_missing('samsung')) bp_load_product_file('samsung');
        bp_one_off_complete('bp_product_upload_2026-08-07');
    }

    if (get_option('bp_product_upload_2026-08-11') !== 'completed' && bp_site_sells_brand('amana')) {
        bp_load_product_file('amana');
        bp_one_off_complete('bp_product_upload_2026-08-11');
    }


}


/**
 * Load a brand's product include and make sure its importer actually runs.
 *
 * Every includes-{brand}-products.php file ends in add_action('wp_loaded', 'add_{brand}_products'),
 * which is fine when a one-off fires at theme-load time (the nightly path) but a DEAD HOOK when it
 * fires from an admin page callback — wp_loaded is long gone by then, so requiring the file would
 * silently import nothing. Call the importer directly in that case.
 */
function bp_load_product_file(string $brand): void {
    require_once get_template_directory() . '/includes/include-hvac-products/includes-' . $brand . '-products.php';

    $fn = 'add_' . str_replace('-', '_', $brand) . '_products';
    if (did_action('wp_loaded') && function_exists($fn)) $fn();
}


/**
 * Stamp a one-off job complete — immediately if wp_loaded has already fired, otherwise after the
 * importers have had their turn at wp_loaded:10, so a job never marks itself done before it ran.
 */
function bp_one_off_complete(string $option): void {
    if (did_action('wp_loaded')) {
        updateOption($option, 'completed', false);
        return;
    }

    add_action('wp_loaded', function () use ($option) {
        updateOption($option, 'completed', false);
    }, 20);
}


/**
 * Is any of a brand's product photos missing from the media library?
 *
 * Compares the photos the theme ships for the brand against what bp_product_image_id() can find,
 * so a re-import only runs on the sites that actually need one.
 */
function bp_brand_photos_missing(string $brand): bool {
    $photos = glob(get_template_directory() . '/common/hvac-' . $brand . '/products/*');
    if (empty($photos)) return false;

    foreach ($photos as $photo) {
        if (bp_product_image_id(basename($photo)) === false) return true;
    }

    return false;
}


/**
 * Does this install sell the given brand?
 *
 * customer_info['site-brand'] is a bare string on some sites and an array on others,
 * the brand may sit anywhere in that array, and casing/spacing drift across the fleet
 * ('American Standard', 'american standard', 'american-standard'), so normalise both sides.
 */
function bp_site_sells_brand(string $brand): bool {
    $siteBrands = customer_info()['site-brand'] ?? '';
    if (empty($siteBrands)) return false;

    $normalise = fn($value) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower((string) $value)));
    $needle    = $normalise($brand);

    foreach ((array) $siteBrands as $siteBrand) {
        if ($normalise($siteBrand) === $needle) return true;
    }

    return false;
}


/*--------------------------------------------------------------
# Shared helpers (used across multiple chron files)
--------------------------------------------------------------*/

function battleplan_delete_prefixed_options(string $prefix): void {
    global $wpdb;
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($prefix) . '%'));
}
