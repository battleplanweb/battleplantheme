<?php
/* Battle Plan Web Design Functions: Admin-Stats

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Site Stats
	- Set up dashboard stats review
	- Set up Visitor Trends widget on dashboard
	- Set up Site Visitors widget on dashboard
	- Set up Referrers widget on dashboard
	- Set up Locations widget on dashboard
	- Set up Browsers widget on dashboard
	- Set up Devices widget on dashboard
	- Set up Screen Resolutions widget on dashboard
	- Set up Tech widget on dashboard
	- Set up Content Visibility widget on dashboard
	- Set up Most Popular Pages widget on dashboard
	- Set up Top Queries widget on dashboard


--------------------------------------------------------------*/

$daily = get_option('bp_ga4_daily_clean');

error_log('GSC queries: ' . print_r(get_option('bp_gsc_top_queries'), true));

/*--------------------------------------------------------------
# Site Stats
--------------------------------------------------------------*/
// Add new dashboard widgets
add_action( 'wp_dashboard_setup', 'battleplan_add_dashboard_widgets' );
function battleplan_add_dashboard_widgets() {
	if ( _USER_LOGIN == "battleplanweb" || in_array('bp_view_stats', _USER_ROLES) ) :
		add_meta_box( 'battleplan_site_stats', 'Site Visitors', 'battleplan_admin_site_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_admin_referrer_stats', 'Referrers', 'battleplan_admin_referrer_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_admin_location_stats', 'Locations', 'battleplan_admin_location_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_tech_stats', 'Tech Info', 'battleplan_admin_tech_stats', 'dashboard', 'normal', 'high' );

		add_meta_box( 'battleplan_content_stats', 'Content Visibility', 'battleplan_admin_content_stats', 'dashboard', 'side', 'high' );
		add_meta_box( 'battleplan_pages_stats', 'Most Popular Pages', 'battleplan_admin_pages_stats', 'dashboard', 'side', 'high' );
        add_meta_box( 'battleplan_queries_stats', 'Top Google Queries', 'battleplan_admin_queries_stats', 'dashboard', 'side', 'high' );

        add_meta_box( 'battleplan_weekly_stats', 'Weekly Visitor Trends', 'battleplan_admin_weekly_stats', 'dashboard', 'column3', 'high' );
		add_meta_box( 'battleplan_monthly_stats', 'Monthly Visitor Trends', 'battleplan_admin_monthly_stats', 'dashboard', 'column3', 'high' );
		add_meta_box( 'battleplan_quarterly_stats', 'Quarterly Visitor Trends', 'battleplan_admin_quarterly_stats', 'dashboard', 'column3', 'high' );
		add_meta_box( 'battleplan_daily_stats', 'Daily Visitors', 'battleplan_admin_daily_stats', 'dashboard', 'column3', 'high' );
	endif;
}

//delete_option('bp_ga4_pages_clean');

// Set up dashboard stats review
$GLOBALS['dataTerms'] = array('week'=>7, 'month'=>30, 'quarter'=>90, 'semester'=>180, 'year'=>365);
updateOption('bp_data_terms', $GLOBALS['dataTerms'], false );
$ga4_pages_data        = get_option('bp_ga4_pages_clean');
$ga4_browsers_data     = get_option('bp_ga4_browsers_clean');
$ga4_devices_data      = get_option('bp_ga4_devices_clean');
$ga4_resolution_data   = get_option('bp_ga4_resolution_clean');
$ga4_achievementId_data= get_option('bp_ga4_content_clean');
//https://fullpath.zendesk.com/hc/en-us/articles/360039889971-Facebook-Google-Bot-Traffic

// Set up Visitor Trends widget on dashboard
function battleplan_visitor_trends($time, $daysPerPeriod, $colEnd) {
    $daily = get_option('bp_ga4_daily_clean');

    if (!$daily || !is_array($daily)) {
        echo "<p>No GA4 data available.</p>";
        return;
    }

    krsort($daily); // newest first

    $dates = array_keys($daily);
    $today = strtotime($dates[0]);

    $col = $row = 1;
    $periodSize = $daysPerPeriod;

    echo "<table class='trends trends-{$time} trends-col-{$col}'>
            <tr>
                <td class='header'>" . ucfirst($time) . "</td>
                <td class='span page sessions'></td>
            </tr>";

    $periodIndex = 0;

    while (true) {

	$startOffset = $periodIndex * $periodSize;
	$endOffset   = ($periodIndex + 1) * $periodSize - 1;

        if ($startOffset > count($daily)) break;

        $sessions = 0;
        $users = 0;
        $newUsers = 0;
        $engaged = 0;
        $pageviews = 0;
        $duration = 0;

        for ($i = $startOffset; $i <= $endOffset; $i++) {

            $dateKey = date('Ymd', strtotime("-$i days", $today));

            if (!isset($daily[$dateKey])) continue;

            $sessions  += $daily[$dateKey]['sessions'];
            $users     += $daily[$dateKey]['users'];
            $newUsers  += $daily[$dateKey]['newUsers'];
            $engaged   += $daily[$dateKey]['engagedSessions'];
            $pageviews += $daily[$dateKey]['pageviews'];
            $duration  += $daily[$dateKey]['engagementDuration'];
        }

        if ($sessions == 0) {
            $periodIndex++;
            continue;
        }

        $engagementPct = $sessions > 0 ? round(($engaged / $sessions) * 100, 1) : 0;
        $pagesPerSession = $sessions > 0 ? round($pageviews / $sessions, 1) : 0;
        $avgSessionDuration = $sessions > 0 ? round($duration / $sessions) : 0;
        $avgEngagedDuration = $engaged > 0 ? round($duration / $engaged) : 0;
        $newUserPct = $users > 0 ? round(($newUsers / $users) * 100, 1) : 0;

        $labelEnd = date("M j, Y", strtotime("-$startOffset days", $today));

        echo "<tr class='coloration trends sessions active' data-count='{$sessions}'>
                <td>{$labelEnd}</td>
                <td><b>" . number_format($sessions) . "</b></td>
                <td><b>" . number_format($users) . "</b></td>
              </tr>";

        echo "<tr class='coloration trends new' data-count='{$newUsers}'>
                <td>{$labelEnd}</td>
                <td><b>" . number_format($newUsers) . "</b></td>
                <td>{$newUserPct}%</td>
              </tr>";

        echo "<tr class='coloration trends engagement' data-count='{$engaged}'>
                <td>{$labelEnd}</td>
                <td><b>" . number_format($engaged) . "</b></td>
                <td>{$engagementPct}%</td>
              </tr>";

        echo "<tr class='coloration trends pageviews' data-count='{$pageviews}'>
                <td>{$labelEnd}</td>
                <td><b>" . number_format($pageviews) . "</b></td>
                <td>{$pagesPerSession}</td>
              </tr>";

        echo "<tr class='coloration trends duration' data-count='{$avgEngagedDuration}'>
                <td>{$labelEnd}</td>
                <td><b>" . floor($avgEngagedDuration / 60) . "m " . ($avgEngagedDuration % 60) . "s</b></td>
                <td><b>" . floor($avgSessionDuration / 60) . "m " . ($avgSessionDuration % 60) . "s</b></td>
              </tr>";

        $row++;
        $periodIndex++;

        if ($row > $colEnd) {
            $col++;
            $row = 1;

            echo "</table>
                  <table class='trends trends-{$time} trends-col-{$col}'>
                  <tr>
                      <td class='header'>" . ucfirst($time) . "</td>
                      <td class='span page sessions'></td>
                  </tr>";
        }
    }

    echo "</table>";
}

function battleplan_admin_daily_stats() {

    // PERIOD BUTTONS (Week / Month / Quarter / etc.)
    echo '<div class="last-visitors-buttons">';
        foreach ($GLOBALS['dataTerms'] as $termTitle => $termDays) {

            $active = ($termTitle == 'month') ? " active" : "";

            echo do_shortcode(
                '[btn size="1/5" class="period-btn '.$termTitle.$active.'" data-period="'.$termTitle.'"]'
                . ucwords($termTitle) .
                '[/btn]'
            );
        }
    echo '</div>';


    // METRIC BUTTONS (Sessions / New / Engagement / etc.)
    $metrics = ['sessions', 'new', 'engagement', 'pageviews', 'duration'];

    echo '<div class="trend-buttons">';
        foreach ($metrics as $metric) {

            $active = ($metric == 'sessions') ? " active" : "";

            echo do_shortcode(
                '[btn size="1/6" class="metric-btn '.$metric.$active.'" data-metric="'.$metric.'"]'
                . ucwords($metric) .
                '[/btn]'
            );
        }
    echo '</div>';


    // DEFAULT VIEW (Daily)
    battleplan_visitor_trends('daily', 1, 365);
}

function battleplan_admin_weekly_stats() {
    battleplan_visitor_trends('weekly', 7, 52);
}

function battleplan_admin_monthly_stats() {
    battleplan_visitor_trends('monthly', 30, 12);
}

function battleplan_admin_quarterly_stats() {
    battleplan_visitor_trends('quarterly', 90, 4);
}


// Set up Site Visitors widget on dashboard
$GLOBALS['ga4_visitor'] = [];
$daily = get_option('bp_ga4_daily_clean');

if ($daily && is_array($daily)) {
    foreach ([1, 7, 30, 90, 180, 365] as $days) {
        $sumSessions  = 0;
        $sumPageviews = 0;
        $sumUsers     = 0;
        $sumEngaged   = 0;

        for ($i = 0; $i < $days; $i++) {
            $key = date('Ymd', strtotime("-$i days"));
            if (!isset($daily[$key])) continue;
            $sumSessions  += $daily[$key]['sessions'];
            $sumPageviews += $daily[$key]['pageviews'];
            $sumUsers     += $daily[$key]['users'];
            $sumEngaged   += $daily[$key]['engagedSessions'];
        }

        $GLOBALS['ga4_visitor']["sessions-$days"]   = $sumSessions;
        $GLOBALS['ga4_visitor']["page-views-$days"] = $sumPageviews;
        $GLOBALS['ga4_visitor']["users-$days"]      = $sumUsers;
        $GLOBALS['ga4_visitor']["engaged-sessions-$days"]  = $sumEngaged;
    }
}


function battleplan_admin_site_stats() {
	$lastVisitTime = timeElapsed(get_option('last_visitor_time'), 2);

	echo "<table>";
	echo "<tr><td class='label'>Last Visit</td><td class='last-visit'>{$lastVisitTime} ago</td></tr>";
	echo "<tr><td>&nbsp;</td></tr>";

	// Yesterday
	$yesterday = (int)($GLOBALS['ga4_visitor']['sessions-1']   ?? 0);
	echo "<tr><td class='label'>Yesterday</td><td>".
		sprintf(
			_n('<b>%s</b> visit', '<b>%s</b> visits', $yesterday, 'battleplan'),
			number_format($yesterday)
		).
	"</td></tr>";

	// This Week
	$week      = (int)($GLOBALS['ga4_visitor']['sessions-7']   ?? 0);
	echo "<tr><td class='label'>This Week</td><td>".
		sprintf(
			_n('<b>%s</b> visit', '<b>%s</b> visits', $week, 'battleplan'),
			number_format($week)
		).
	"</td><td><b>".number_format($week / 7, 1)."</b> /day</td></tr>";

	// This Month
	$month     = (int)($GLOBALS['ga4_visitor']['sessions-30']  ?? 0);
	if ($month !== $week)
		echo "<tr><td class='label'>This Month</td><td>".
			sprintf(
				_n('<b>%s</b> visit', '<b>%s</b> visits', $month, 'battleplan'),
				number_format($month)
			).
		"</td><td><b>".number_format($month / 30, 1)."</b> /day</td></tr>";

	// 3 Months
	$qtr       = (int)($GLOBALS['ga4_visitor']['sessions-90']  ?? 0);
	if ($qtr !== $month)
		echo "<tr><td class='label'>3 Months</td><td>".
			sprintf(
				_n('<b>%s</b> visit', '<b>%s</b> visits', $qtr, 'battleplan'),
				number_format($qtr)
			).
		"</td><td><b>".number_format($qtr / 90, 1)."</b> /day</td></tr>";

	// 6 Months
	$half      = (int)($GLOBALS['ga4_visitor']['sessions-180'] ?? 0);
	if ($half !== $qtr)
		echo "<tr><td class='label'>6 Months</td><td>".
			sprintf(
				_n('<b>%s</b> visit', '<b>%s</b> visits', $half, 'battleplan'),
				number_format($half)
			).
		"</td><td><b>".number_format($half / 180, 1)."</b> /day</td></tr>";

	// 1 Year
	$year      = (int)($GLOBALS['ga4_visitor']['sessions-365'] ?? 0);
	if ($year !== $half)
		echo "<tr><td class='label'>1 Year</td><td>".
			sprintf(
				_n('<b>%s</b> visit', '<b>%s</b> visits', $year, 'battleplan'),
				number_format($year)
			).
		"</td><td><b>".number_format($year / 365, 1)."</b> /day</td></tr>";

	echo "<tr><td>&nbsp;</td></tr>";
	echo "</table>";
}



// Set up Referrers widget on dashboard
function battleplan_admin_referrer_stats() {

    $raw = get_option('bp_ga4_referrers_clean');

    if (!$raw || !is_array($raw)) {
        echo "<p>No referrer data available.</p>";
        return;
    }

    $periods = [7, 30, 90, 180, 365];

    foreach ($periods as $days) {

        $period = "sessions-{$days}";
        $active = ($days === 30) ? ' active' : '';

        $refRollups = [];

        foreach ($raw as $ref => $metrics) {
            if (!is_array($metrics)) continue;
            $value = (int)($metrics[$period] ?? 0);
            if ($value > 0) $refRollups[$ref] = $value;
        }

        if (!$refRollups) continue;

        arsort($refRollups);

        $totalEngaged = array_sum($refRollups);

        $search  = ['(direct) / (none)', ' / referral', ' / organic', ' / cpc', 'Googleads.g.doubleclick.net', 'syndicatedsearch.goog', ' / display', 'google', 'GMB', 'bing', 'yahoo', 'duckduckgo', 'fb / paid'];
        $replace = ['Direct', '', ' (organic)', ' (paid)', 'Google (paid)', 'Google Partners (paid)', ' (display)', 'Google', 'GBP', 'Bing', 'Yahoo', 'DuckDuckGo', 'Facebook (paid)'];

        echo "<div class='handle-label handle-label-{$days}{$active}'><ul>";
        echo "<li class='sub-label' style='column-span: all'>Last " . number_format($totalEngaged) . " Engaged Sessions</li>";

        foreach ($refRollups as $referrerTitle => $count) {
            $referrerTitle = str_replace($search, $replace, $referrerTitle);
            echo "<li>
                    <div class='value'><b>" . number_format($count) . "</b></div>
                    <div class='label'>" . esc_html($referrerTitle) . "</div>
                  </li>";
        }

        echo '</ul></div>';
    }
}

if (!function_exists('bp_append_state_to_city')) {

    function bp_append_state_to_city(string $city): string {

        static $map = null;

        if ($map === null) {

            $map = get_option('bp_ga4_city_state_map');

            if (!is_array($map)) {
                $map = [];
            }
        }

        if (isset($map[$city]) && $map[$city]) {

            return $city . ', ' . $map[$city];
        }

        return $city;
    }
}




// Set up Locations widget on dashboard
function battleplan_admin_location_stats() {

    $cities = get_option('bp_ga4_locations_clean');

    if (!$cities || !is_array($cities)) {
        echo "<p>No location data available.</p>";
        return;
    }

    $periods = [7, 30, 90, 180, 365];

    foreach ($periods as $days) {

        $period = "sessions-{$days}";
        $active = ($days === 30) ? ' active' : '';

        $flat = [];

        foreach ($cities as $city => $metrics) {
            if (!is_array($metrics)) continue;
            $value = (int)($metrics[$period] ?? 0);
            if ($value > 0) $flat[$city] = $value;
        }

        if (!$flat) continue;

        arsort($flat);

        $total = array_sum($flat);

        echo "<div class='handle-label handle-label-{$days}{$active}'><ul>";
        echo "<li class='sub-label' style='column-span: all'>Last " . number_format($total) . " Engaged Sessions</li>";
        echo "<div style='column-count:2'>";

        foreach (array_slice($flat, 0, 60, true) as $city => $count) {
            $label = bp_append_state_to_city($city);
            echo "<li>
                    <div class='value'><b>" . number_format($count) . "</b></div>
                    <div class='label'>" . esc_html($label) . "</div>
                  </li>";
        }

        echo '</div></ul></div>';
    }
}



// Set up Browsers widget on dashboard
$GLOBALS['ga4_browser'] = [];

if ($ga4_browsers_data && is_array($ga4_browsers_data)) {

    foreach ($ga4_browsers_data as $browser=>$metrics) {

        if (!is_array($metrics)) continue;

        foreach ($metrics as $metricKey=>$value) {

            if ($value <= 0) continue;

            $GLOBALS['ga4_browser'][$browser][$metricKey] = (int)$value;
        }
    }
}





// Set up Devices widget on dashboard
$GLOBALS['ga4_device'] = [];

if ($ga4_devices_data && is_array($ga4_devices_data)) {

    foreach ($ga4_devices_data as $device=>$metrics) {

        if (!is_array($metrics)) continue;

        foreach ($metrics as $metricKey=>$value) {

            if ($value <= 0) continue;

            $GLOBALS['ga4_device'][$device][$metricKey] = (int)$value;
        }
    }
}






// Set up Speed widget on dashboard
$GLOBALS['fastSessions'] = $GLOBALS['speedSessions'] = $GLOBALS['speedTotal'] = array();
$mobileTarget = 3;
$desktopTarget = 2;


if (!empty($ga4_speed_data) && is_array($ga4_speed_data)) :
	foreach ( $ga4_speed_data as $speedLocation=>$speedData ) :
		if ( !in_array($speedLocation, $excludeCities) ) :
			foreach ( $speedData as $term=>$speeds ) :
				foreach ( $speeds as $speed ):
					if (strpos($speed, 'desktop') !== false ) :
						$speed = (float)substr(substr($speed, strpos($speed, "«") + 1), 1);
						if ( $speed < ($desktopTarget * 10) ) :
							$GLOBALS['speedSessions'][$term]['desktop'] = isset($GLOBALS['speedSessions'][$term]['desktop'])
							? $GLOBALS['speedSessions'][$term]['desktop'] + 1
							: 1;

						if ($speed <= $desktopTarget) {
							$GLOBALS['fastSessions'][$term]['desktop'] = isset($GLOBALS['fastSessions'][$term]['desktop'])
							? $GLOBALS['fastSessions'][$term]['desktop'] + 1
							: 1;
						}

						$GLOBALS['speedTotal'][$term]['desktop'] = isset($GLOBALS['speedTotal'][$term]['desktop'])
							? $GLOBALS['speedTotal'][$term]['desktop'] + $speed
							: $speed;
											endif;
					endif;
					if (strpos($speed, 'mobile') !== false ) :
						$speed = (float)substr(substr($speed, strpos($speed, "«") + 1), 1);
						if ( $speed < ($mobileTarget * 10) ) :
							$GLOBALS['speedSessions'][$term]['mobile'] = isset($GLOBALS['speedSessions'][$term]['mobile'])
							? $GLOBALS['speedSessions'][$term]['mobile'] + 1
							: 1;

						if ($speed <= $mobileTarget) {
							$GLOBALS['fastSessions'][$term]['mobile'] = isset($GLOBALS['fastSessions'][$term]['mobile'])
							? $GLOBALS['fastSessions'][$term]['mobile'] + 1
							: 1;
						}

						$GLOBALS['speedTotal'][$term]['mobile'] = isset($GLOBALS['speedTotal'][$term]['mobile'])
							? $GLOBALS['speedTotal'][$term]['mobile'] + $speed
							: $speed;
											endif;
					endif;
				endforeach;
			endforeach;
		endif;
	endforeach;
endif;

// Send stats to Site Checkin
$mobileSessions = (int) ($GLOBALS['speedSessions']['sessions-30']['mobile'] ?? 0);
$desktopSessions = (int) ($GLOBALS['speedSessions']['sessions-30']['desktop'] ?? 0);

$GLOBALS['ga4_visitor']['ck_mobile_speed'] = $mobileSessions > 0
    ? number_format(($GLOBALS['speedTotal']['sessions-30']['mobile'] / $mobileSessions)?? 0.0, 1)
    : 0;

$GLOBALS['ga4_visitor']['ck_desktop_speed'] = $desktopSessions > 0
    ? number_format(($GLOBALS['speedTotal']['sessions-30']['desktop'] / $desktopSessions)?? 0.0, 1)
    : 0;


// Set up Screen Resolutions widget on dashboard
$GLOBALS['ga4_resolution'] = [];

if ($ga4_resolution_data && is_array($ga4_resolution_data)) {

    foreach ($ga4_resolution_data as $resolution=>$metrics) {

        if (!is_array($metrics)) continue;

        foreach ($metrics as $metricKey=>$value) {

            if ($value <= 0) continue;

            $GLOBALS['ga4_resolution'][$resolution][$metricKey] = (int)$value;
        }
    }
}





// Set up Tech widget on dashboard
function battleplan_admin_tech_stats() {

    /*
    |--------------------------------------------------------------------------
    | Browsers
    |--------------------------------------------------------------------------
    */

    $ga4_browser = [];

    if (!empty($GLOBALS['ga4_browser']) && is_array($GLOBALS['ga4_browser'])) {
        foreach ($GLOBALS['ga4_browser'] as $browserTitle => $browserMetrics) {
            if (!is_array($browserMetrics)) continue;
            foreach ($browserMetrics as $metricKey => $sessions) {
                if (!is_numeric($sessions)) continue;
                $ga4_browser[$metricKey][$browserTitle] = (int)$sessions;
            }
        }
    }

    foreach ($ga4_browser as &$b) { arsort($b); }
    unset($b);

    foreach ($ga4_browser as $metricKey => $browserAndSessions) {

        $active = ($metricKey === 'sessions-30') ? ' active' : '';
        $days   = (int)substr($metricKey, strrpos($metricKey, '-') + 1);
        $total  = array_sum($browserAndSessions);

        echo "<div class='handle-label handle-label-{$days}{$active}'><ul>";
        echo "<li class='sub-label' style='column-span: all'>Browsers</li>";

        foreach ($browserAndSessions as $browserTitle => $browserSessions) {
            if (!is_numeric($browserSessions)) continue;
            if ($browserSessions <= ($total * 0.001)) continue;
            $pct = ($browserSessions / $total) * 100;
            echo "<li>
                    <div class='value'><b>" . number_format($pct, 1) . "%</b></div>
                    <div class='label'>" . esc_html($browserTitle) . "</div>
                  </li>";
        }

        echo '</ul></div>';
    }


    /*
    |--------------------------------------------------------------------------
    | Devices
    |--------------------------------------------------------------------------
    */

    // Fetch and pivot directly — don't rely on global
    $ga4_device = [];
    $devices_raw = get_option('bp_ga4_devices_clean') ?: [];

    foreach ($devices_raw as $deviceTitle => $deviceMetrics) {
        if (!is_array($deviceMetrics)) continue;
        foreach ($deviceMetrics as $metricKey => $sessions) {
            if (!is_numeric($sessions)) continue;
            $ga4_device[$metricKey][$deviceTitle] = (int)$sessions;
        }
    }

    foreach ($ga4_device as &$d) { arsort($d); }
    unset($d);

    $speed_data = get_option('bp_ga4_speed_clean') ?: [];

    foreach ($ga4_device as $metricKey => $deviceAndSessions) {

        $active = ($metricKey === 'sessions-30') ? ' active' : '';
        $days   = (int)substr($metricKey, strrpos($metricKey, '-') + 1);
        $total  = array_sum($deviceAndSessions);

        echo "<div class='handle-label handle-label-{$days}{$active}'><ul>";
        echo "<li class='sub-label' style='column-span: all'>Devices</li>";

        foreach ($deviceAndSessions as $deviceTitle => $deviceSessions) {
            if (!is_numeric($deviceSessions)) continue;

            $pct       = ($deviceSessions / $total) * 100;
            $deviceKey = strtolower($deviceTitle);
            $avgSpeed  = $speed_data[$deviceKey]["avg-{$days}"]    ?? null;
            $targetPct = $speed_data[$deviceKey]["target-{$days}"] ?? null;

            $speedStr = '';
            if ($avgSpeed !== null && $targetPct !== null) {
                $speedStr = '<span>'.number_format($avgSpeed, 1).'s</span>';
            }

            echo "<li class='device'>
                    <div class='value'><b>" . number_format($pct, 1) . "%</b></div>
                    <div class='label'>" . esc_html(ucwords($deviceTitle)) . "</div>
                    <div>" . $speedStr . "</div>
                    <div>" . number_format($targetPct, 1) . "% on target</div>
                  </li>";
        }

        echo '</ul></div>';
    }


    /*
    |--------------------------------------------------------------------------
    | Screen Resolution
    |--------------------------------------------------------------------------
    */

    $ga4_resolution = [];

    if (!empty($GLOBALS['ga4_resolution']) && is_array($GLOBALS['ga4_resolution'])) {
        foreach ($GLOBALS['ga4_resolution'] as $resolutionTitle => $resolutionMetrics) {
            if (!is_array($resolutionMetrics)) continue;
            foreach ($resolutionMetrics as $metricKey => $sessions) {
                if (!is_numeric($sessions)) continue;
                $ga4_resolution[$metricKey][$resolutionTitle] = (int)$sessions;
            }
        }
    }

    foreach ($ga4_resolution as &$r) { arsort($r); }
    unset($r);

    foreach ($ga4_resolution as $metricKey => $resolutionAndSessions) {

        $active = ($metricKey === 'sessions-30') ? ' active' : '';
        $days   = (int)substr($metricKey, strrpos($metricKey, '-') + 1);
        $total  = array_sum($resolutionAndSessions);

        echo "<div class='handle-label handle-label-{$days}{$active}'><ul>";
        echo "<li class='sub-label' style='column-span: all'>Screen Widths</li>";
        echo "<div style='column-count:2'>";

        foreach ($resolutionAndSessions as $resolutionTitle => $resolutionSessions) {
            if (!is_numeric($resolutionSessions)) continue;
            if ($resolutionSessions <= ($total * 0.001)) continue;
            $pct = ($resolutionSessions / $total) * 100;
            echo "<li>
                    <div class='value'><b>" . number_format($pct, 1) . "%</b></div>
                    <div class='label'>" . esc_html($resolutionTitle) . " px</div>
                  </li>";
        }

        echo '</div></ul></div>';
    }
}


function battleplan_admin_content_stats() {
	$ga4_contentVis = array();

	foreach ($GLOBALS['ga4_contentVis'] as $contentVisTitle => $contentVisMetrics) :
		foreach ( $contentVisMetrics as $metricKey => $sessions ) $ga4_contentVis[$metricKey][$contentVisTitle] = isset($sessions) ? $sessions : 0;
	endforeach;

	foreach ($ga4_contentVis as $metricKey => $contentVisAndSessions) :
        $active = 'sessions-30' == $metricKey ? " active" : "";
        $days = (int)substr($metricKey, strrpos($metricKey, '-') + 1); // ADDED
        ksort($contentVisAndSessions);

        echo '<div class="handle-label handle-label-'.(int)substr($metricKey, strrpos($metricKey, '-') + 1).$active.'"><ul>';

        $pctCalc = array('init', '20', '30', '40', '50', '60', '70', '80', '90', '100');
        $contentCalc = array();
        foreach ($contentVisAndSessions as $key=>$value) :
            foreach ( $pctCalc as $pct ) :
                if (strpos($key, '-'.$pct) !== false && strpos($key, 'track-') === false ) :
                    if (!isset($contentCalc[$pct])) $contentCalc[$pct] = 0;
                    $contentCalc[$pct] += $value;
                endif;
            endforeach;
        endforeach;

        $init = isset($contentCalc['init']) ? $contentCalc['init'] : 0;
        arsort($contentCalc);

        echo '<li class="sub-label" style="column-span: all">Last '.number_format($init?? 0.0).' Pageviews</li>';
        foreach ($contentCalc as $pct=>$total) :
            if ( $pct !== 'init' ) :
                $label = $pct != 100 ? "viewed at least ".$pct."% of page" : "<b>viewed ALL of page</b>";
                if ( $init > 0 ) echo "<li><div class='value'><b>".number_format(($total / $init) * 100, 1)."%</b></div><div class='label'>".$label."</div></li>";
            endif;
        endforeach;

        echo '</ul><li class="sub-label" style="column-span: all">Tracked Components & Events</li><ul>';

        $track_init = (int)($GLOBALS['ga4_visitor']["engaged-sessions-{$days}"] ?? 0);

        foreach ($contentVisAndSessions as $key=>$value) :
            if (strpos($key, 'track-') !== false) :
                $trackItem = str_replace('track-', '', $key);

                if ($track_init > 0) echo "<li><div class='value'><b>".number_format(($value / $track_init) * 100, 1).'%'."</b></div><div class='label'>".ucwords($trackItem)."</div></li>";

            endif;
        endforeach;

        echo '<br>';

        foreach ($contentVisAndSessions as $key=>$value) :
            if (strpos($key, 'conversion-') !== false) :
                $trackItem = str_replace('conversion-', '', $key);
                if ($track_init > 0) echo "<li><div class='value'><b>".$value."</b></div><div class='label'>".ucwords($trackItem)."s</div></li>";
            endif;
        endforeach;

        echo '</ul></div>';
    endforeach;
}

// Set up Content Visibility widget on dashboard
$GLOBALS['ga4_contentVis'] = [];

if (!empty($ga4_achievementId_data) && is_array($ga4_achievementId_data)) {

    foreach ($ga4_achievementId_data as $event => $metrics) {

        if (!is_array($metrics)) continue;

        foreach ($metrics as $metricKey => $value) {

            if (!is_numeric($value)) continue;

            $GLOBALS['ga4_contentVis'][$event][$metricKey] = (int)$value;
        }
    }
}


// Set up Most Popular Pages widget on dashboard
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


// Set up Top Queries widget on dashboard
$GLOBALS['gsc_top_queries'] = get_option('bp_gsc_top_queries') ?: [];
$gsc_queries_data = get_option('bp_gsc_top_queries') ?: [];
if (!empty($gsc_queries_data) && is_array($gsc_queries_data)) {
    foreach ($gsc_queries_data as $row) {
        if (!is_array($row)) continue;
        $GLOBALS['gsc_top_queries'][] = [
            'query'       => $row['query']       ?? '',
            'clicks'      => (int)($row['clicks']      ?? 0),
            'impressions' => (int)($row['impressions'] ?? 0),
            'ctr'         => (float)($row['ctr']       ?? 0),
            'position'    => (float)($row['position']  ?? 0),
        ];
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

    foreach ($GLOBALS['dataTerms'] as $termTitle => $termDays) {

        $metaKey = 'bp_views_' . $termDays;
        $value   = (int)($metrics['page-views-' . $termDays] ?? 0);

        updateMeta($pageID, $metaKey, $value);
    }
}

function battleplan_admin_pages_stats() {

    $ga4_page = [];

    if (!empty($GLOBALS['ga4_page']) && is_array($GLOBALS['ga4_page'])) {

        foreach ($GLOBALS['ga4_page'] as $pageTitle => $pageMetrics) {

            if (!is_array($pageMetrics)) continue;

            foreach ($pageMetrics as $metricKey => $views) {

                if (!is_numeric($views)) continue;

                $ga4_page[$metricKey][$pageTitle] = (int)$views;
            }
        }
    }

    foreach ($ga4_page as $metricKey => &$titlesAndViews) {
        arsort($titlesAndViews);
    }
    unset($titlesAndViews);

    foreach ($ga4_page as $metricKey => $titlesAndViews) {

        $active = ($metricKey === 'page-views-30') ? " active" : "";

        $total = array_sum($titlesAndViews);

        echo '<div class="handle-label handle-label-' .
             (int)substr($metricKey, strrpos($metricKey, '-') + 1) .
             $active . '"><ul>';

        echo '<li class="sub-label" style="column-span: all">Last ' .
             number_format($total) . ' Pageviews</li>';

        foreach ($titlesAndViews as $pageTitle => $pageViews) {

            if (!is_numeric($pageViews)) continue;

		  echo "<li>
			<div class='value'><b>" . number_format((int)$pageViews) . "</b></div>
			<div class='label'>" . esc_html(bp_ga4_path_to_label($pageTitle)) . "</div>
			</li>";
        }

        echo '</ul></div>';
    }
}

function battleplan_admin_queries_stats() {

    $queries = $GLOBALS['gsc_top_queries'] ?? [];

    if (empty($queries)) {
        echo '<p>No query data available.</p>';
        return;
    }

    foreach ($GLOBALS['dataTerms'] as $termTitle => $termDays) {

        $active = ($termTitle === 'month') ? ' active' : '';

        // Collect rows that have data for this period
        $rows = [];
        foreach ($queries as $query => $periods) {
            if (!empty($periods[$termTitle])) {
                $rows[$query] = $periods[$termTitle];
            }
        }

        if (empty($rows)) continue;

        // Sort by clicks descending
        uasort($rows, fn($a, $b) => $b['clicks'] <=> $a['clicks']);

        $totalClicks = array_sum(array_column($rows, 'clicks'));

        echo '<div class="handle-label handle-label-' . $termDays . $active . '"><ul>';
        echo '<li class="sub-label" style="column-span: all">Last ' . number_format($totalClicks) . ' Clicks</li>';

        foreach ($rows as $query => $metrics) {
            $imp = number_format($metrics['impressions']);
            $ctr = $metrics['ctr'];
            $rank = $metrics['position'];

            $rank_color =   $rank   <= 4  ? 'color:rgb(0,152,9)' : ( $rank  > 10 ? 'color:rgb(255,0,0)' : 'color:inherit');
            $ctr_color = 'color:inherit';

            // Position 1
            if ($rank <= 1.0) {
                if ($ctr < 20) {
                    $ctr_color = 'color:rgb(255,0,0)';      // below respectable
                } elseif ($ctr >= 35) {
                    $ctr_color = 'color:rgb(0,152,9)';      // strong
                }

            // Position >1 to 2
            } elseif ($rank <= 2.0) {
                if ($ctr < 12) {
                    $ctr_color = 'color:rgb(255,0,0)';
                } elseif ($ctr >= 25) {
                    $ctr_color = 'color:rgb(0,152,9)';
                }

            // Position >2 to 3
            } elseif ($rank <= 3.0) {
                if ($ctr < 8) {
                    $ctr_color = 'color:rgb(255,0,0)';
                } elseif ($ctr >= 18) {
                    $ctr_color = 'color:rgb(0,152,9)';
                }

            // Position >3 to 5
            } elseif ($rank <= 5.0) {
                if ($ctr < 5) {
                    $ctr_color = 'color:rgb(255,0,0)';
                } elseif ($ctr >= 12) {
                    $ctr_color = 'color:rgb(0,152,9)';
                }

            // Position >5 to 10
            } elseif ($rank <= 10.0) {
                if ($ctr < 2) {
                    $ctr_color = 'color:rgb(255,0,0)';
                } elseif ($ctr >= 6) {
                    $ctr_color = 'color:rgb(0,152,9)';
                }

            // Position >10
            } else {
                if ($ctr < 0.5) {
                    $ctr_color = 'color:rgb(255,0,0)';
                } elseif ($ctr >= 3) {
                    $ctr_color = 'color:rgb(0,152,9)';
                }
            }

            echo '<li>
                    <div class="value"><b>' . number_format($metrics['clicks']) . '</b></div>
                    <div class="label"><b>' . esc_html($query) . '</b></div>
                    <div>&nbsp;</div>
                    <div class="sub-label"><em><span style="'.$ctr_color.'">'. $ctr . '%</span> (' . $imp . ' impressions)<span style="float:right; '.$rank_color.'">Rank: <b>' . $rank . '</b></em></div>
                  </li>';
        }

        echo '</ul></div>';
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

