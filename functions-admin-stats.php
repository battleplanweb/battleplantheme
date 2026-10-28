<?php
/* Battle Plan Web Design Functions: Admin-Stats

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Site Stats
	- Site visitor totals (feeds the Site Checkin + analytics XML)
	- Page view totals -> bp_views_* post meta
	- Page Stats meta box on posts & pages


--------------------------------------------------------------*/

$daily = get_option('bp_ga4_daily_clean');

/*--------------------------------------------------------------
# Site Stats
--------------------------------------------------------------*/
// (The numeric dashboard widgets that used to live here are retired — the Analytics page replaced them.)

// Site visitor totals
$GLOBALS['ga4_visitor'] = [];

if ($daily && is_array($daily)) {
    // metric in the daily rows => key prefix in ga4_visitor
    $metrics = ['sessions' => 'sessions', 'pageviews' => 'page-views', 'users' => 'users', 'engagedSessions' => 'engaged-sessions'];

    // Yesterday — specific single-day lookup
    $ydKey = date('Ymd', strtotime('-1 day'));
    foreach ($metrics as $m => $prefix) $GLOBALS['ga4_visitor']["$prefix-yesterday"] = (int)($daily[$ydKey][$m] ?? 0);

    // Rolling 7/30/90/180/365-day totals (today back), in ONE pass over the year instead of a separate walk per window
    $windows = [7, 30, 90, 180, 365];
    $sums    = array_fill_keys(array_keys($metrics), 0);
    $day     = new DateTime();
    for ($i = 1; $i <= 365; $i++, $day->modify('-1 day')) {
        $row = $daily[$day->format('Ymd')] ?? null;
        if (is_array($row)) foreach ($sums as $m => $total) $sums[$m] = $total + (int)($row[$m] ?? 0);
        if (in_array($i, $windows, true)) {
            foreach ($metrics as $m => $prefix) $GLOBALS['ga4_visitor']["$prefix-$i"] = $sums[$m];
        }
    }
}

// Send stats to Site Checkin
$speed_data = get_option('bp_ga4_speed_clean') ?: [];
$GLOBALS['ga4_visitor']['ck_mobile_speed']  = (float)($speed_data['mobile']['avg-30']  ?? 0);
$GLOBALS['ga4_visitor']['ck_desktop_speed'] = (float)($speed_data['desktop']['avg-30'] ?? 0);


// Page view totals per page title
$ga4_pages_data = get_option('bp_ga4_pages_clean');
$GLOBALS['ga4_page'] = [];

if (!empty($ga4_pages_data) && is_array($ga4_pages_data)) {

    foreach ($ga4_pages_data as $pagePath => $metrics) {

        if (!is_array($metrics)) continue;

        foreach ($metrics as $metricKey => $value) {

            if (!is_numeric($value)) continue;

            $GLOBALS['ga4_page'][$pagePath][$metricKey] = (int)$value;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Update Post Meta (after aggregation is complete)
|--------------------------------------------------------------------------
*/
$getCPT = getCPT();
foreach ($GLOBALS['ga4_page'] as $pageTitle => $metrics) {

    // Skip hierarchical titles like "Parent » Child"
    if (strpos($pageTitle, '»') !== false) {
        continue;
    }

    $pageID = 0;

    foreach ($getCPT as $type) {

        $query = bp_WP_Query($type, [
            'title'          => $pageTitle,
            'post_status'    => 'all',
            'posts_per_page' => 1
        ]);

        if (!empty($query->post)) {
            $pageID = $query->post->ID;
            break;
        }
    }

    if (!$pageID) {
        continue;
    }

    foreach ([7, 30, 90, 180, 365] as $termDays) {

        $metaKey = 'bp_views_' . $termDays;
        $value   = (int)($metrics['page-views-' . $termDays] ?? 0);

        updateMeta($pageID, $metaKey, $value);
    }
}

// Add custom meta boxes to posts & pages
add_action("add_meta_boxes", "battleplan_add_custom_meta_boxes");
function battleplan_add_custom_meta_boxes() {
	$getCPT = getCPT();
	foreach ( $getCPT as $postType ) add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", $postType, "side", "default", null);
}

// Set up Page Stats widget on posts & pages
function battleplan_page_stats() {
	global $post;
	$rightNow = strtotime(date("F j, Y g:i a"));
	$today = strtotime(date("F j, Y"));

	$viewsToday = (float)readMeta(get_the_ID(), 'bp_views_today', true);
	$last7Views = (float)readMeta(get_the_ID(), 'bp_views_7', true);
	$last30Views = (float)readMeta(get_the_ID(), 'bp_views_30', true);
	$last90Views = (float)readMeta(get_the_ID(), 'bp_views_90', true);
	$last180Views = (float)readMeta(get_the_ID(), 'bp_views_180', true);
	$last365Views = (float)readMeta(get_the_ID(), 'bp_views_365', true);

	echo "<table>";
		echo "<tr><td><b>Yesterday</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), number_format($viewsToday?? 0.0) )."</td></tr>";
		echo "<tr><td><b>Last 7 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), number_format($last7Views?? 0.0) )."</td></tr>";
		if ( $last30Views != $last7Views) echo "<tr><td><b>Last 30 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), number_format($last30Views?? 0.0) )."</td></tr>";
		if ( $last90Views != $last30Views) echo "<tr><td><b>Last 90 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last90Views, 'battleplan' ), number_format($last90Views?? 0.0) )."</td></tr>";
		if ( $last180Views != $last90Views) echo "<tr><td><b>Last 180 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last180Views, 'battleplan' ), number_format($last180Views?? 0.0) )."</td></tr>";
		if ( $last365Views != $last180Views) echo "<tr><td><b>Last 365 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last365Views, 'battleplan' ), number_format($last365Views?? 0.0) )."</td></tr>";
	echo "</table>";
}

