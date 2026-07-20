<?php
/* Battle Plan Web Design: Site Audit
 *
 * The audit is now ONE job: crawl the site's main pages, hand Claude everything the nightly chron
 * already collects (GA4, Search Console, PageSpeed/CWV, keywords, Google Business, backlinks,
 * content, Clarity), and store a DATED report so we build a history over time.
 *
 * It no longer collects metrics itself — that all lives in the chron
 * (functions-chron-analytics.php → bp_collect_audit_metrics(), functions-chron-gbp.php, etc.).
 * The old per-metric collectors and the bp_site_audit_details table were removed as duplication.
 *
 * MANUAL ONLY. The audit takes minutes (crawl + PageSpeed renders + a Claude vision call), so the
 * Run Audit button walks it one step at a time from the browser — see the stepped runner in
 * functions-site-audit-ai.php. Nothing schedules it; Chron E was removed.
 *
 * Entry point:  bp_run_site_audit()      — runs every step in one process. Safe ONLY where there
 *                                          is no request timeout (i.e. WP-CLI), never in a request.
 * Report(s):    get_option('bp_site_audit_ai_history')  — date => report
 * Latest:       bp_audit_ai_latest()
 */

// How often the audit may run. 0 = every invocation (testing).
// 2592000 = monthly · 7776000 = quarterly
if ( ! defined( 'BP_SITE_AUDIT_INTERVAL' ) ) define( 'BP_SITE_AUDIT_INTERVAL', 0 );

// Guarded: functions-site-audit-ai.php is a NEW file, so a partial/selective deploy can leave it
// missing while this one is present. A bare require_once would fatal the request that loaded it.
$bp_audit_ai_file = get_template_directory() . '/functions-site-audit-ai.php';
if ( file_exists( $bp_audit_ai_file ) ) require_once $bp_audit_ai_file;
unset( $bp_audit_ai_file );


/**
 * Run the audit: page crawl + Claude analysis, stored with today's date.
 * Returns the report array, or WP_Error / null when throttled.
 */
function bp_run_site_audit() {

	if ( BP_SITE_AUDIT_INTERVAL > 0 ) {
		$last = (int) get_site_option( 'bp_site_audit_last_run', 0 );
		if ( ( time() - $last ) < BP_SITE_AUDIT_INTERVAL ) return null;
	}
	if ( ! function_exists( 'bp_audit_ai_report' ) ) {
		error_log( 'bp_run_site_audit: functions-site-audit-ai.php is missing — nothing to run.' );
		return new WP_Error( 'bp_audit_missing', 'The audit module (functions-site-audit-ai.php) is not installed.' );
	}

	update_site_option( 'bp_site_audit_last_run', time() );

	$report = bp_audit_ai_report();

	if ( is_wp_error( $report ) ) {
		error_log( 'bp_run_site_audit failed for ' . get_bloginfo( 'url' ) . ': ' . $report->get_error_message() );
		return $report;
	}

	error_log( 'bp_run_site_audit complete for ' . get_bloginfo( 'url' ) );
	return $report;
}
