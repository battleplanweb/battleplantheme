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

--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Site Stats
--------------------------------------------------------------*/
// Add new dashboard widgets
add_action( 'wp_dashboard_setup', 'battleplan_add_dashboard_widgets' );
function battleplan_add_dashboard_widgets() {
	if ( _USER_LOGIN == "battleplanweb" ) :	
		add_meta_box( 'battleplan_site_stats', 'Site Visitors', 'battleplan_admin_site_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_admin_referrer_stats', 'Referrers', 'battleplan_admin_referrer_stats', 'dashboard', 'normal', 'high' ); 
		add_meta_box( 'battleplan_admin_location_stats', 'Locations', 'battleplan_admin_location_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_tech_stats', 'Tech Info', 'battleplan_admin_tech_stats', 'dashboard', 'normal', 'high' );	

		add_meta_box( 'battleplan_content_stats', 'Content Visibility', 'battleplan_admin_content_stats', 'dashboard', 'side', 'high' );
		add_meta_box( 'battleplan_pages_stats', 'Most Popular Pages', 'battleplan_admin_pages_stats', 'dashboard', 'side', 'high' );
	
		add_meta_box( 'battleplan_weekly_stats', 'Weekly Visitor Trends', 'battleplan_admin_weekly_stats', 'dashboard', 'column3', 'high' );		
		add_meta_box( 'battleplan_monthly_stats', 'Monthly Visitor Trends', 'battleplan_admin_monthly_stats', 'dashboard', 'column3', 'high' );		
		add_meta_box( 'battleplan_quarterly_stats', 'Quarterly Visitor Trends', 'battleplan_admin_quarterly_stats', 'dashboard', 'column3', 'high' );
		add_meta_box( 'battleplan_daily_stats', 'Daily Visitors', 'battleplan_admin_daily_stats', 'dashboard', 'column3', 'high' );	
	endif;
}

// Set up dashboard stats review 
$GLOBALS['dataTerms'] = array('week'=>7, 'month'=>30, 'quarter'=>90, 'semester'=>180, 'year'=>365);
updateOption('bp_data_terms', $GLOBALS['dataTerms'], false );
$ga4_trends_data = is_array(get_option('bp_ga4_trends_01')) ? get_option('bp_ga4_trends_01') : array();
$ga4_visitors_data = is_array(get_option('bp_ga4_visitors_01')) ? get_option('bp_ga4_visitors_01') : array();
$ga4_pages_data = is_array(get_option('bp_ga4_pages_01')) ? get_option('bp_ga4_pages_01') : array();
$ga4_referrers_data = is_array(get_option('bp_ga4_referrers_01')) ? get_option('bp_ga4_referrers_01') : array();
$ga4_search_data = is_array(get_option('bp_ga4_search_01')) ? get_option('bp_ga4_search_01') : array();
$ga4_locations_data = is_array(get_option('bp_ga4_locations_01')) ? get_option('bp_ga4_locations_01') : array();
$ga4_browsers_data = is_array(get_option('bp_ga4_browsers_01')) ? get_option('bp_ga4_browsers_01') : array();
$ga4_devices_data = is_array(get_option('bp_ga4_devices_01')) ? get_option('bp_ga4_devices_01') : array();
$ga4_speed_data = is_array(get_option('bp_ga4_speed_01')) ? get_option('bp_ga4_speed_01') : array();
$ga4_resolution_data = is_array(get_option('bp_ga4_resolution_01')) ? get_option('bp_ga4_resolution_01') : array();
$ga4_achievementId_data = is_array(get_option('bp_ga4_achievementId_01')) ? get_option('bp_ga4_achievementId_01') : array();
$excludeCities = array('Orangetree, FL', 'Ashburn, VA', 'Boardman, OR', 'Irvine, CA', 'Prineville, OR', 'Forest City, NC', 'Altoona, IA'); 
//https://fullpath.zendesk.com/hc/en-us/articles/360039889971-Facebook-Google-Bot-Traffic

// Set up Visitor Trends widget on dashboard
$GLOBALS['ga4_date'] = array();
$currDate = -1;
$chkDate = date("Ymd", strtotime($currDate." day"));
$totalUsers = $newUsers = $sessions = $engagedSessions = $sessionDuration = $pageViews = 0;

foreach ( $ga4_trends_data as $ga4_point ) :
	$location = $ga4_point['location'];

	if ( !in_array($location, $excludeCities) ) :
		$date = $ga4_point['date'];
		if ( $date == $chkDate ) : 
			$totalUsers += $ga4_point['total-users'];
			$newUsers += $ga4_point['new-users'];
			$sessions += $ga4_point['sessions'];
			$engagedSessions += $ga4_point['engaged-sessions'];
			$sessionDuration += $ga4_point['session-duration'];
			$pageViews += $ga4_point['page-views'];
		else:
			$GLOBALS['ga4_date'][$chkDate] = array('total-users'=>$totalUsers, 'new-users'=>$newUsers, 'sessions'=>$sessions, 'engaged-sessions'=>$engagedSessions, 'session-duration'=>$sessionDuration, 'page-views'=>$pageViews );	

			while ($date !== $chkDate) :
				$currDate--;
				$chkDate = date("Ymd", strtotime($currDate." days"));

				if ( $date != $chkDate ) : 
					$GLOBALS['ga4_date'][$chkDate] = array('total-users'=>0, 'new-users'=>0, 'sessions'=>0, 'engaged-sessions'=>0, 'session-duration'=>0, 'page-views'=>0 );
				endif;
			endwhile;

			$chkDate = $date;
			$totalUsers = $ga4_point['total-users'];
			$newUsers = $ga4_point['new-users'];
			$sessions = $ga4_point['sessions'];
			$engagedSessions = $ga4_point['engaged-sessions'];
			$sessionDuration = $ga4_point['session-duration'];
			$pageViews = $ga4_point['page-views'];
		endif;
	endif;
endforeach;

krsort($GLOBALS['ga4_date']);

function battleplan_visitor_trends($time, $minDays, $maxDays, $colEnd) {
	$totalUsers = $newUsers = $sessions = $engagedSessions = $sessionDuration = $pageViews = $pagesPerSession = $engagementPct = $avgSessionDuration = $avgEngagedDuration = $newUserPct = 0;
	$day = $col = $row = 1;
	$term = $minDays;	
	$termEnd = date("M j, Y", strtotime("-1 day"));
	
	echo "<table class='trends trends-".$time." trends-col-".$col."'><tr><td class='header'>".ucfirst($time)."</td><td class='span page sessions'></td></tr>";
	
	foreach ( $GLOBALS['ga4_date'] as $ga4_date=>$dailyData ) :
		$theDate = date("M j, Y", strtotime($ga4_date)); 
		
		$totalUsers += $dailyData['total-users'];
		$newUsers += $dailyData['new-users'];
		$sessions += $dailyData['sessions'];
		$engagedSessions += $dailyData['engaged-sessions'];
		$sessionDuration += $dailyData['session-duration'];
		$pageViews += $dailyData['page-views'];

		if ( $totalUsers > 0 ) $pagesPerSession = number_format( (round(($pageViews / $totalUsers), 3)) , 1, '.', '');
		if ( $sessions > 0 ) $engagementPct = number_format( ((round(($engagedSessions / $sessions), 3)) * 100), 1, '.', '');		
		if ( $sessions > 0 ) $avgSessionDuration = number_format( (round(($sessionDuration / $sessions), 3)) , 1, '.', '');	
		if ( $engagedSessions > 0 ) $avgEngagedDuration = number_format( (round(($sessionDuration / $engagedSessions), 3)) , 1, '.', '');	
		if ( $totalUsers > 0 ) $newUserPct = number_format( ((round(($newUsers / $totalUsers), 3)) * 100), 1, '.', '');

		$day++;

		if ( $day > $term ) :		
		 	echo "<tr class='coloration trends sessions active' data-count='".$sessions."'><td>".$termEnd."</td><td><b>".number_format($sessions)."</b><td><b>".number_format($totalUsers)."</b></td></tr>";		
		 	echo "<tr class='coloration trends new' data-count='".$newUsers."'><td>".$termEnd."</td><td><b>".number_format($newUsers)."</b></td><td>".number_format($newUserPct,1)."%</td></tr>";
			echo "<tr class='coloration trends engagement' data-count='".$engagedSessions."'><td>".$termEnd."</td><td><b>".$engagedSessions."</b></td><td>".$engagementPct."%</td></tr>";
			echo "<tr class='coloration trends pageviews' data-count='".$pageViews."'><td>".$termEnd."</td><td><b>".$pageViews.'</b></td><td>'.number_format($pagesPerSession,1)."</td></tr>";
			echo "<tr class='coloration trends duration' data-count='".$avgEngagedDuration."'><td>".$termEnd."</td><td><b>".floor($avgEngagedDuration / 60)."m ".number_format($avgEngagedDuration % 60) . "s</b></td><td><b>".floor($avgSessionDuration / 60)."m ".number_format($avgSessionDuration % 60) . "s</b></td></tr>";
	
			$totalUsers = $newUsers = $sessions = $engagedSessions = $sessionDuration = $pageViews = $pagesPerSession = $engagementPct = $avgSessionDuration = $avgEngagedDuration = $newUserPct = 0;
			$day = 1;
			$row++;
			$term = $maxDays == 1 || $term == $maxDays ? $minDays : $maxDays;	
			$termEnd = date("M j, Y", strtotime("-1 day", strtotime($theDate)));
		endif;
		
		if ( $row > $colEnd ) :
			$col++;
			$row = 1;

			echo "</table><table class='trends trends-".$time." trends-col-".$col."'><tr><td class='header'>".ucfirst($time)."</td><td class='span page sessions'></td></tr>";
		endif;
	endforeach;
	
	echo "</table>";	
}

function battleplan_admin_daily_stats() {
	echo '<div class="last-visitors-buttons">';	
		foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :
			$active = 'month' == $termTitle ? " active" : "";
			echo do_shortcode('[btn size="1/5" class="'.$termTitle.$active.'"]'.ucwords($termTitle).'[/btn]');	
		endforeach;	
	echo '</div>';
	
	$metrics = array('sessions', 'new', 'engagement', 'pageviews', 'duration');
	echo '<div class="trend-buttons">';
		foreach ( $metrics as $metric ) :
			$active = 'sessions' == $metric ? " active" : "";
			echo do_shortcode('[btn size="1/6" class="'.$metric.$active.'"]'.ucwords($metric).'[/btn]');	
		endforeach;
	echo '</div>';

	battleplan_visitor_trends('daily',1,1,365);
}

function battleplan_admin_weekly_stats() { battleplan_visitor_trends('weekly',7,7,52); }
function battleplan_admin_monthly_stats() { battleplan_visitor_trends('monthly',30,31,12); }
function battleplan_admin_quarterly_stats() { battleplan_visitor_trends('quarterly',91,92,4); }


// Set up Site Visitors widget on dashboard
$GLOBALS['ga4_visitor'] = array();
$chkLocation = '';
foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $visitorSessions[$termDays] = 0;

foreach ( $ga4_visitors_data as $visitorLocation=>$locationData ) :
	foreach ( $locationData as $termLength=>$totalSessions ) :
		if ( !in_array($visitorLocation, $excludeCities) ) :
			$GLOBALS['ga4_visitor'][$termLength] += $totalSessions;
		endif;
	endforeach;

endforeach;

function battleplan_admin_site_stats() {
	$lastVisitTime = timeElapsed( get_option('last_visitor_time'), 2);

	echo "<table><tr><td class='label'>Last Visit</td><td class='last-visit'>".$lastVisitTime." ago</td></tr>";	
	echo "<tr><td>&nbsp;</td></tr>";		
	echo "<tr><td class='label'>Yesterday</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', number_format($GLOBALS['ga4_visitor']['page-views-1']), 'battleplan' ), number_format($GLOBALS['ga4_visitor']['page-views-1']))."</td></tr>";	
	echo "<tr><td class='label'>This Week</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $GLOBALS['ga4_visitor']['page-views-7'], 'battleplan' ), number_format($GLOBALS['ga4_visitor']['page-views-7']) )."</td><td><b>".number_format(($GLOBALS['ga4_visitor']['page-views-7'])/7,1)."</b> /day</td></tr>";
	
	if ( $GLOBALS['ga4_visitor']['page-views-30'] != $GLOBALS['ga4_visitor']['page-views-7']) echo "<tr><td class='label'>This Month</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $GLOBALS['ga4_visitor']['page-views-30'], 'battleplan' ), number_format($GLOBALS['ga4_visitor']['page-views-30']) )."</td><td><b>".number_format(($GLOBALS['ga4_visitor']['page-views-30'])/30,1)."</b> /day</td></tr>";
	
	if ( $GLOBALS['ga4_visitor']['page-views-90'] != $GLOBALS['ga4_visitor']['page-views-30']) echo "<tr><td class='label'>3 Months</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $GLOBALS['ga4_visitor']['page-views-90'], 'battleplan' ), number_format($GLOBALS['ga4_visitor']['page-views-90']) )."</td><td><b>".number_format(($GLOBALS['ga4_visitor']['page-views-90'])/90,1)."</b> /day</td></tr>";
	
	if ( $GLOBALS['ga4_visitor']['page-views-180'] != $GLOBALS['ga4_visitor']['page-views-90']) echo "<tr><td class='label'>6 Months</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $GLOBALS['ga4_visitor']['page-views-180'], 'battleplan' ), number_format($GLOBALS['ga4_visitor']['page-views-180']) )."</td><td><b>".number_format(($GLOBALS['ga4_visitor']['page-views-180'])/180,1)."</b> /day</td></tr>";
	
	if ( $GLOBALS['ga4_visitor']['page-views-365'] != $GLOBALS['ga4_visitor']['page-views-180']) echo "<tr><td class='label'>1 Year</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $GLOBALS['ga4_visitor']['page-views-365'], 'battleplan' ), number_format($GLOBALS['ga4_visitor']['page-views-365']) )."</td><td><b>".number_format(($GLOBALS['ga4_visitor']['page-views-365'])/365,1)."</b> /day</td></tr>";
		
	echo '<tr><td>&nbsp;</td></tr></table>';
}
	

// Set up Referrers widget on dashboard
$GLOBALS['ga4_referrer'] = $referSessions = array();
$chkReferrer = '';
foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $referSessions[$termDays] = 0;

foreach ( $ga4_referrers_data as $referrer=>$referrerData ) :
	foreach ( $referrerData as $referrerLocation=>$totalSessions ) :
		if ( !in_array($referrerLocation, $excludeCities) ) :
			if ( $referrer == $chkReferrer ) : 
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $referSessions[$termDays] += $totalSessions['sessions-'.$termDays];
			else:
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $GLOBALS['ga4_referrer'][$chkReferrer]['sessions-'.$termDays] = $referSessions[$termDays];	
				$chkReferrer = $referrer;
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $referSessions[$termDays] = $totalSessions['sessions-'.$termDays];
			endif;
		endif;
	endforeach; 
endforeach;

function battleplan_admin_referrer_stats() {
	$ga4_referrer = array();

	foreach ($GLOBALS['ga4_referrer'] as $referrerTitle => $referrerMetrics) :
	    foreach ($referrerMetrics as $metricKey => $sessions) $ga4_referrer[$metricKey][$referrerTitle] = $sessions;
	endforeach;

	foreach ($ga4_referrer as $metricKey => &$referrerAndSessions) :
    		arsort($referrerAndSessions);
	endforeach;
	
	unset($referrerAndSessions);

	foreach ($ga4_referrer as $metricKey => $referrerAndSessions) :
		$active = 'sessions-30' == $metricKey ? " active" : "";

		echo '<div class="handle-label handle-label-'.(int)substr($metricKey, strrpos($metricKey, '-') + 1).$active.'"><ul>';	
		echo '<li class="sub-label" style="column-span: all">Last '.number_format(array_sum($referrerAndSessions)).' Engaged Sessions</li>';		

		foreach ($referrerAndSessions as $referrerTitle => $referSessions) :
			$search = array( '(direct) / (none)' , ' / referral', ' / organic', ' / cpc', ' / display', 'google', 'GMB', 'bing', 'yahoo', 'duckduckgo' );
			$replace = array( 'Direct' , '', ' (organic)', ' (paid)', ' (display)', 'Google', 'GBP', 'Bing', 'Yahoo', 'DuckDuckGo' );
			$referrerTitle = str_replace( $search, $replace, $referrerTitle);
	
			if ( $referSessions > 0 ) echo "<li><div class='value'><b>".number_format($referSessions)."</b></div><div class='label'>".$referrerTitle."</div></li>";
		endforeach;

		echo '</ul></div>';	
	endforeach;
}
	

// Set up Locations widget on dashboard
$GLOBALS['ga4_location'] = $locationSessions = array();
$chkLocation = '';
foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $locationSessions[$termDays] = 0;

foreach ( $ga4_locations_data as $location=>$totalSessions ) :
	if ( !in_array($location, $excludeCities) ) :
		if ( $location == $chkLocation ) : 
			foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $locationSessions[$termDays] += $totalSessions['sessions-'.$termDays];
		else:
			foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $GLOBALS['ga4_location'][$chkLocation]['sessions-'.$termDays] = $locationSessions[$termDays];	
			$chkLocation = $location;
			foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $locationSessions[$termDays] = $totalSessions['sessions-'.$termDays];
		endif;
	endif;
endforeach; 

function battleplan_admin_location_stats() {
	$ga4_location = array();

	foreach ($GLOBALS['ga4_location'] as $locationTitle => $locationMetrics) :
	    foreach ($locationMetrics as $metricKey => $sessions) $ga4_location[$metricKey][$locationTitle] = $sessions;
	endforeach;

	foreach ($ga4_location as $metricKey => &$locationAndSessions) :
    		arsort($locationAndSessions);
	endforeach;	
	
	unset($locationAndSessions);

	foreach ($ga4_location as $metricKey => $locationAndSessions) :
		$active = 'sessions-30' == $metricKey ? " active" : "";
		$locationTotalSessions = array_sum($locationAndSessions);

		echo '<div class="handle-label handle-label-'.(int)substr($metricKey, strrpos($metricKey, '-') + 1).$active.'"><ul>';	
		echo '<li class="sub-label" style="column-span: all">Last '.number_format($locationTotalSessions).' Engaged Sessions</li>';		
		echo '<div style="column-count:2">';		
	
		foreach ($locationAndSessions as $locationTitle =>$locationSessions) :
		   	if ( $locationSessions > ($locationTotalSessions * 0.005) ) echo "<li><div class='value'><b>".number_format($locationSessions)."</b></div><div class='label'>".$locationTitle."</div></li>";
		endforeach;

		echo '</div></ul></div>';	
	endforeach;
}
	

// Set up Browsers widget on dashboard
$GLOBALS['ga4_browser'] = $browserSessions = array();
$chkBrowser = '';
foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $browserSessions[$termDays] = 0;

foreach ( $ga4_browsers_data as $browser=>$browserData ) :
	foreach ( $browserData as $browserLocation=>$totalSessions ) :
		if ( !in_array($browserLocation, $excludeCities) ) :
			if ( $browser == $chkBrowser ) : 
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $browserSessions[$termDays] += $totalSessions['sessions-'.$termDays];
			else:
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $GLOBALS['ga4_browser'][$chkBrowser]['sessions-'.$termDays] = $browserSessions[$termDays];	
				$chkBrowser = $browser;
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $browserSessions[$termDays] = $totalSessions['sessions-'.$termDays];
			endif;
		endif;
	endforeach; 
endforeach;

// Set up Devices widget on dashboard
$GLOBALS['ga4_device'] = $deviceSessions = array();
$chkDevice = '';
foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $deviceSessions[$termDays] = 0;

foreach ( $ga4_devices_data as $device=>$deviceData ) :
	foreach ( $deviceData as $deviceLocation=>$totalSessions ) :
		if ( !in_array($deviceLocation, $excludeCities) ) :
			if ( $device == $chkDevice ) : 
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $deviceSessions[$termDays] += $totalSessions['sessions-'.$termDays];
			else:
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $GLOBALS['ga4_device'][$chkDevice]['sessions-'.$termDays] = $deviceSessions[$termDays];	
				$chkDevice = $device;
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $deviceSessions[$termDays] = $totalSessions['sessions-'.$termDays];
			endif;
		endif;
	endforeach; 
endforeach;


// Set up Speed widget on dashboard
$GLOBALS['fastSessions'] = $GLOBALS['speedSessions'] = $GLOBALS['speedTotal'] = array();
$mobileTarget = 3;
$desktopTarget = $mobileTarget / 2;

foreach ( $ga4_speed_data as $speedLocation=>$speedData ) :
	if ( !in_array($speedLocation, $excludeCities) ) :
		foreach ( $speedData as $term=>$speeds ) :
			foreach ( $speeds as $speed ):
				if (strpos($speed, 'desktop') !== false ) :	
					$speed = (float)substr(substr($speed, strpos($speed, "«") + 1), 1);
					if ( $speed < ($desktopTarget * 10) ) :
						$GLOBALS['speedSessions'][$term]['desktop'] += 1;
						if ( $speed <= $desktopTarget ) $GLOBALS['fastSessions'][$term]['desktop'] += 1;
						$GLOBALS['speedTotal'][$term]['desktop'] += $speed;		
					endif;
				endif;
				if (strpos($speed, 'mobile') !== false ) :			
					$speed = (float)substr(substr($speed, strpos($speed, "«") + 1), 1);
					if ( $speed < ($mobileTarget * 10) ) :
						$GLOBALS['speedSessions'][$term]['mobile'] += 1;
						if ( $speed <= $mobileTarget ) $GLOBALS['fastSessions'][$term]['mobile'] += 1;
						$GLOBALS['speedTotal'][$term]['mobile'] += $speed;
					endif;
				endif;
			endforeach;
		endforeach;
	endif;
endforeach;


// Set up Screen Resolutions widget on dashboard
$GLOBALS['ga4_resolution'] = $resolutionSessions = array();
$chkDevice = '';
foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $resolutionSessions[$termDays] = 0;

foreach ( $ga4_resolution_data as $resolution=>$resolutionData ) :
	foreach ( $resolutionData as $resolutionLocation=>$totalSessions ) :
		if ( !in_array($resolutionLocation, $excludeCities) ) :

			$resolution = substr($resolution, 0, strpos($resolution, 'x'));
			if ( $resolution == $chkDevice ) : 
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $resolutionSessions[$termDays] += $totalSessions['sessions-'.$termDays];
			else:
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $GLOBALS['ga4_resolution'][$chkDevice]['sessions-'.$termDays] = $resolutionSessions[$termDays];	
				$chkDevice = $resolution;
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $resolutionSessions[$termDays] = $totalSessions['sessions-'.$termDays];
			endif;
		endif;
	endforeach; 
endforeach;

// Set up Tech widget on dashboard
function battleplan_admin_tech_stats() {
	$ga4_browser = array();

	foreach ($GLOBALS['ga4_browser'] as $browserTitle => $browserMetrics) :
	    foreach ($browserMetrics as $metricKey => $sessions) $ga4_browser[$metricKey][$browserTitle] = $sessions;
	endforeach;

	foreach ($ga4_browser as $metricKey => &$browserAndSessions) :
    		arsort($browserAndSessions);
	endforeach;
	
	unset($browserAndSessions);

	foreach ($ga4_browser as $metricKey => $browserAndSessions) :
		$active = 'sessions-30' == $metricKey ? " active" : "";
		$browserTotalSessions = array_sum($browserAndSessions);

		echo '<div class="handle-label handle-label-'.(int)substr($metricKey, strrpos($metricKey, '-') + 1).$active.'"><ul>';	
		echo '<li class="sub-label" style="column-span: all">Browsers</li>';		

		foreach ($browserAndSessions as $browserTitle =>$browserSessions) :			
			if ( $browserSessions > ($browserTotalSessions * 0.001) ) echo "<li><div class='value'><b>".number_format(($browserSessions / $browserTotalSessions)*100,1)."%</b></div><div class='label'>".$browserTitle."</div></li>";
		endforeach;

		echo '</ul></div>';	
	endforeach;
	
	$ga4_device = array();

	foreach ($GLOBALS['ga4_device'] as $deviceTitle => $deviceMetrics) :
	    foreach ($deviceMetrics as $metricKey => $sessions) $ga4_device[$metricKey][$deviceTitle] = $sessions;
	endforeach;

	foreach ($ga4_device as $metricKey => &$deviceAndSessions) :
    		arsort($deviceAndSessions);
	endforeach;
	
	unset($deviceAndSessions);

	foreach ($ga4_device as $metricKey => $deviceAndSessions) :
		$active = 'sessions-30' == $metricKey ? " active" : "";
		$deviceTotalSessions = array_sum($deviceAndSessions);

		echo '<div class="handle-label handle-label-'.(int)substr($metricKey, strrpos($metricKey, '-') + 1).$active.'"><ul>';	
		echo '<li class="sub-label" style="column-span: all">Devices</li>';	
	
		foreach ($deviceAndSessions as $deviceTitle =>$deviceSessions) :			
			$total = $GLOBALS['speedTotal'][$metricKey][$deviceTitle];
			$sessions = $GLOBALS['speedSessions'][$metricKey][$deviceTitle];			
			$fastSessions = $GLOBALS['fastSessions'][$metricKey][$deviceTitle];	
	
			if ( $deviceTotalSessions > 0 && $sessions > 0 ) echo "<li><div class='value'><b>".number_format(($deviceSessions / $deviceTotalSessions)*100,1)."%</b></div><div class='label-half' style='width:calc(35% - 35px)'>".ucwords($deviceTitle)."</div><div class='label-half style='width:calc(65% - 35px)'>".number_format($total / $sessions, 1)."s&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;".number_format(($fastSessions / $sessions)*100, 1)."% above target</div></li>";
		endforeach;

		echo '</ul></div>';	
	endforeach;
	
	$ga4_resolution = array();

	foreach ($GLOBALS['ga4_resolution'] as $resolutionTitle => $resolutionMetrics) :
	    foreach ($resolutionMetrics as $metricKey => $sessions) $ga4_resolution[$metricKey][$resolutionTitle] = $sessions;
	endforeach;

	foreach ($ga4_resolution as $metricKey => &$resolutionAndSessions) :
    		arsort($resolutionAndSessions);
	endforeach;
	
	unset($resolutionAndSessions);

	foreach ($ga4_resolution as $metricKey => $resolutionAndSessions) :
		$active = 'sessions-30' == $metricKey ? " active" : "";
		$resolutionTotalSessions = array_sum($resolutionAndSessions);

		echo '<div class="handle-label handle-label-'.(int)substr($metricKey, strrpos($metricKey, '-') + 1).$active.'"><ul>';	
		echo '<li class="sub-label" style="column-span: all">Screen Widths</li>';		
		echo '<div style="column-count:2">';		

		foreach ($resolutionAndSessions as $resolutionTitle =>$resolutionSessions) :			
			if ( $resolutionSessions > ($resolutionTotalSessions * 0.001) ) echo "<li><div class='value'><b>".number_format(($resolutionSessions / $resolutionTotalSessions)*100,1)."%</b></div><div class='label'>".ucwords($resolutionTitle)." px</div></li>";
		endforeach;

		echo '</div></ul></div>';	
	endforeach;
}
	

// Set up Content Visibility widget on dashboard
$GLOBALS['ga4_contentVis'] = $contentVisSessions = array();
$chkContentVis = '';
foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $contentVisSessions[$termDays] = 0;

foreach ( $ga4_achievementId_data as $contentVis=>$contentVisData ) :
	foreach ( $contentVisData as $contentVisLocation=>$totalSessions ) :
		if ( !in_array($contentVisLocation, $excludeCities) ) :
			if ( $contentVis == $chkContentVis ) : 
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $contentVisSessions[$termDays] += $totalSessions['sessions-'.$termDays];
			else:
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $GLOBALS['ga4_contentVis'][$chkContentVis]['sessions-'.$termDays] = $contentVisSessions[$termDays];	
				$chkContentVis = $contentVis;
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $contentVisSessions[$termDays] = $totalSessions['sessions-'.$termDays];
			endif;
		endif;
	endforeach; 
endforeach;

function battleplan_admin_content_stats() {
	$ga4_contentVis = array();

	foreach ($GLOBALS['ga4_contentVis'] as $contentVisTitle => $contentVisMetrics) :
	    foreach ($contentVisMetrics as $metricKey => $sessions) $ga4_contentVis[$metricKey][$contentVisTitle] = $sessions;
	endforeach;

	foreach ($ga4_contentVis as $metricKey => $contentVisAndSessions) :
		$active = 'sessions-30' == $metricKey ? " active" : "";
		ksort($contentVisAndSessions);
	
		echo '<div class="handle-label handle-label-'.(int)substr($metricKey, strrpos($metricKey, '-') + 1).$active.'"><ul>';
	
		$pctCalc = array('init', '40', '60', '80', '100', '1.1', '1.2', '1.3', '1.4', '2.1', '2.2', '2.3', '2.4');
		$contentCalc = array();
		foreach ($contentVisAndSessions as $key=>$value) :
			foreach ( $pctCalc as $pct ) :
				if (strpos($key, '-'.$pct) !== false && strpos($key, 'track-') === false ) :
					$contentCalc[$pct] += $value;
			    	endif;
			endforeach;
		endforeach;
		
		$init = $contentCalc['init'];
		arsort($contentCalc);
	
		echo '<li class="sub-label" style="column-span: all">Last '.number_format($init).' Pageviews</li>';		
		foreach ($contentCalc as $pct=>$total) :
			if ( $pct != 'init' ) :
				if (strpos($pct, '.') !== false) :
					$label = "viewed position ".$pct;
				else:
					$label = $pct != 100 ? "viewed at least ".$pct."%  of main content" : "<b>viewed ALL of main content</b>";
				endif;
				echo "<li><div class='value'><b>".number_format(($total/$init)*100,1)."%</b></div><div class='label'>".$label."</div></li>";
			endif;
		endforeach;		
	
		echo '</ul><li class="sub-label" style="column-span: all">Tracked Components & Events</li><ul>';	
		$track_init = $contentVisAndSessions['track-init'];	
		foreach ($contentVisAndSessions as $key=>$value) :
		 	if (strpos($key, 'track-') === 0) :
				$trackItem = str_replace('track-', '', $key);
				if ( $trackItem != 'init' ) echo "<li><div class='value'><b>".number_format(($value/$track_init) * 100,1)."%</b></div><div class='label'>".ucwords($trackItem)."</div></li>";
		    endif;
		endforeach;
		echo '</ul></div>';	
	endforeach;
}


// Set up Most Popular Pages widget on dashboard
$GLOBALS['ga4_page'] = $pageViews = array();
$chkPage = '';
foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $pageViews[$termDays] = 0;

foreach ( $ga4_pages_data as $pagePath=>$pageData ) :
	foreach ( $pageData as $pageLocation=>$totalViews ) :
		if ( !in_array($pageLocation, $excludeCities) ) :
			if ( $pagePath == $chkPage ) : 
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $pageViews[$termDays] += $totalViews['page-views-'.$termDays];
			else:
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) :					
					$GLOBALS['ga4_page'][$chkPage]['page-views-'.$termDays] = $pageViews[$termDays];
					
					if (strpos($chkPage, '»') === false ) :	
						foreach ( getCPT() as $type ) :
							$pageID = get_page_by_title($chkPage, OBJECT, $type);
							if ($pageID) :
								updateMeta($pageID->ID, 'bp_views_'.$termDays, $GLOBALS['ga4_page'][$chkPage]['page-views-'.$termDays]);
								break;
							endif;
						endforeach;
					endif;

				endforeach;
				$chkPage = $pagePath;
				foreach ( $GLOBALS['dataTerms'] as $termTitle=>$termDays ) $pageViews[$termDays] = $totalViews['page-views-'.$termDays];
			endif;
		endif;
	endforeach; 
endforeach;

function battleplan_admin_pages_stats() {
	$ga4_page = array();

	foreach ($GLOBALS['ga4_page'] as $pageTitle => $pageMetrics) :
	    foreach ($pageMetrics as $metricKey => $views) $ga4_page[$metricKey][$pageTitle] = $views;
	endforeach;

	foreach ($ga4_page as $metricKey => &$titlesAndViews) :
    		arsort($titlesAndViews);
	endforeach;
	
	unset($titlesAndViews);

	foreach ($ga4_page as $metricKey => $titlesAndViews) :
		$active = 'page-views-30' == $metricKey ? " active" : "";

		echo '<div class="handle-label handle-label-'.(int)substr($metricKey, strrpos($metricKey, '-') + 1).$active.'"><ul>';	
		echo '<li class="sub-label" style="column-span: all">Last '.number_format(array_sum($titlesAndViews)).' Pageviews</li>';		

		foreach ($titlesAndViews as $pageTitle =>$pageViews) :
		   	if ( $pageViews > 0 ) echo "<li><div class='value'><b>".number_format($pageViews)."</b></div><div class='label'>".$pageTitle."</div></li>";
		endforeach;

		echo '</ul></div>';	
	endforeach;
}


// Add custom meta boxes to posts & pages
add_action("add_meta_boxes", "battleplan_add_custom_meta_boxes");
function battleplan_add_custom_meta_boxes() {
	foreach ( getCPT() as $postType ) add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", $postType, "side", "default", null);
}

// Set up Page Stats widget on posts & pages
function battleplan_page_stats() {
	global $post;
	$rightNow = strtotime(date("F j, Y g:i a"));
	$today = strtotime(date("F j, Y"));
	
	$last7Views = (float)readMeta(get_the_ID(), 'bp_views_7', true);
	$last30Views = (float)readMeta(get_the_ID(), 'bp_views_30', true);
	$last90Views = (float)readMeta(get_the_ID(), 'bp_views_90', true);
	$last180Views = (float)readMeta(get_the_ID(), 'bp_views_180', true);
	$last365Views = (float)readMeta(get_the_ID(), 'bp_views_365', true);
	
	echo "<table>";	
		echo "<tr><td><b>Yesterday</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), number_format($viewsToday) )."</td></tr>";	
		echo "<tr><td><b>Last 7 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), number_format($last7Views) )."</td></tr>";
		if ( $last30Views != $last7Views) echo "<tr><td><b>Last 30 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), number_format($last30Views) )."</td></tr>";
		if ( $last90Views != $last30Views) echo "<tr><td><b>Last 90 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last90Views, 'battleplan' ), number_format($last90Views) )."</td></tr>";
		if ( $last180Views != $last90Views) echo "<tr><td><b>Last 180 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last180Views, 'battleplan' ), number_format($last180Views) )."</td></tr>";
		if ( $last365Views != $last180Views) echo "<tr><td><b>Last 365 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last365Views, 'battleplan' ), number_format($last365Views) )."</td></tr>";
	echo "</table>";		

} 



?>