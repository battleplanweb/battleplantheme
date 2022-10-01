<?php
/* Battle Plan Web Design Functions: Admin-Stats
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Site Stats

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Site Stats
--------------------------------------------------------------*/
// Add new dashboard widgets
add_action( 'wp_dashboard_setup', 'battleplan_add_dashboard_widgets' );
function battleplan_add_dashboard_widgets() {
	if ( _USER_LOGIN == "battleplanweb" ) :
		add_meta_box( 'battleplan_site_stats', 'Site Visitors', 'battleplan_admin_site_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_referrer_stats', 'Referrers', 'battleplan_admin_referrer_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_location_stats', 'Locations', 'battleplan_admin_location_stats', 'dashboard', 'normal', 'high' );
		add_meta_box( 'battleplan_tech_stats', 'Tech Info', 'battleplan_admin_tech_stats', 'dashboard', 'normal', 'high' );	

		add_meta_box( 'battleplan_pages_stats', 'Most Popular Pages', 'battleplan_admin_pages_stats', 'dashboard', 'side', 'high' );
		add_meta_box( 'battleplan_content_stats', 'Content Visibility', 'battleplan_admin_content_stats', 'dashboard', 'side', 'high' );

		add_meta_box( 'battleplan_daily_stats', 'Daily Visitors', 'battleplan_admin_daily_stats', 'dashboard', 'column3', 'high' );		
		add_meta_box( 'battleplan_weekly_stats', 'Weekly Visitor Trends', 'battleplan_admin_weekly_stats', 'dashboard', 'column3', 'high' );		
		add_meta_box( 'battleplan_monthly_stats', 'Monthly Visitor Trends', 'battleplan_admin_monthly_stats', 'dashboard', 'column3', 'high' );		
		add_meta_box( 'battleplan_quarterly_stats', 'Quarterly Visitor Trends', 'battleplan_admin_quarterly_stats', 'dashboard', 'column3', 'high' );
	endif;
}

// Set up dashboard stats review
$GLOBALS['displayPeriods'] = array( 'week'=>7, 'month'=>30, 'quarter'=>90, 'year'=>365 );
$GLOBALS['btn1'] = get_option('bp_admin_btn1') != null ? get_option('bp_admin_btn1') : "month";
$GLOBALS['btn2'] = get_option('bp_admin_btn2') != null ? get_option('bp_admin_btn2') : "sessions";
$GLOBALS['btn3'] = get_option('bp_admin_btn3') != null ? get_option('bp_admin_btn3') : "not-active";

$siteHits = is_array(get_option('bp_site_hits_ga4')) ? get_option('bp_site_hits_ga4') : array();
$siteHitsUA1 = is_array(get_option('bp_site_hits_ua_1')) ? get_option('bp_site_hits_ua_1') : array();
$siteHitsUA2 = is_array(get_option('bp_site_hits_ua_2')) ? get_option('bp_site_hits_ua_2') : array();
$siteHitsUA3 = is_array(get_option('bp_site_hits_ua_3')) ? get_option('bp_site_hits_ua_3') : array();
$siteHitsUA4 = is_array(get_option('bp_site_hits_ua_4')) ? get_option('bp_site_hits_ua_4') : array();
$siteHitsUA5 = is_array(get_option('bp_site_hits_ua_5')) ? get_option('bp_site_hits_ua_5') : array();
$siteHits = array_merge( $siteHits, $siteHitsUA1, $siteHitsUA2, $siteHitsUA3, $siteHitsUA4, $siteHitsUA5);

$today = date( "Y-m-d" );	
$GLOBALS['citiesToExclude'] = array('Orangetree, FL', 'Ashburn, VA', 'Boardman, OR'); // also change in functions-chron-jobs.php

// Set up array accounting for each day, no skips	
$blankDate = strtotime($siteHits[array_key_last($siteHits)]['date']);
$totalDays = (strtotime($today) - $blankDate) / 86400;

for ( $x=0;$x<$totalDays;$x++) :
	$blankDate = $blankDate + 86400;		
	$dailyStats[ date('Y-m-d', $blankDate) ] = array ('location'=>array(), 'source'=>array(), 'medium'=>array(), 'page'=>array(), 'browser'=>array(), 'device'=>array(), 'resolution'=>array(), 'pages-viewed'=>'0', 'sessions'=>'0', 'engaged'=>'0', 'new-users'=>'0' );
endfor;

// Compile data into daily stats
$dateOfLastHit = $totalPageviews = $totalSessions = $totalEngaged = $totalNewUsers = $allPages = 0;
$allLocations = $allSources = $allMediums = $allPages = $allBrowsers = $allDevices = $allResolutions = array();

foreach ( $siteHits as $siteHit ) :	
	if ( !isset($siteHit['location']) || ( isset($siteHit['location']) && !in_array( $siteHit['location'], $GLOBALS['citiesToExclude'] ) )) :
		if ( $GLOBALS['btn3'] != "active" || ( $siteHit['location'] == get_option('customer_info')['state-full'] || str_contains($siteHit['location'], get_option('customer_info')['state-abbr'] ) )) :
	
			$dateOfHit = strtotime($siteHit['date']);

			if ( $dateOfHit != $dateOfLastHit ) :			
				$dailyStats[ date('Y-m-d', ($dateOfHit + 86400)) ] = array ('location'=>$allLocations, 'source'=>$allSources, 'medium'=>$allMediums, 'page'=>$allPages, 'browser'=>$allBrowsers, 'device'=>$allDevices, 'resolution'=>$allResolutions, 'pages-viewed'=>$totalPageviews, 'sessions'=>$totalSessions, 'engaged'=>$totalEngaged, 'new-users'=>$totalNewUsers );	

				$allLocations = $allSources = $allMediums = $allPages = $allBrowsers = $allDevices = $allResolutions = array();
				$totalPageviews = $totalSessions = $totalEngaged = $totalNewUsers = 0;
			endif;

			$dateOfLastHit = $dateOfHit;

			if ( isset($siteHit['pages-viewed'])) $totalPageviews = $totalPageviews + (int)$siteHit['pages-viewed'];
			if ( isset($siteHit['sessions'])) $totalSessions = $totalSessions + (int)$siteHit['sessions'];
			if ( isset($siteHit['engaged'])) $totalEngaged = $totalEngaged + (int)$siteHit['engaged'];
			if ( isset($siteHit['new-users'])) $totalNewUsers = $totalNewUsers + (int)$siteHit['new-users'];											

			$checkPage = rtrim($siteHit['page'], "/");
			if ( array_key_exists($checkPage, $allPages) ) :
				$allPages[$checkPage] += (int)$siteHit['pages-viewed'];
			else:
				$allPages[$checkPage] = (int)$siteHit['pages-viewed'];
			endif;	

			if ( $siteHit['sessions'] == 1 ) :
				if ( isset($siteHit['location']) ) :
					if ( array_key_exists($siteHit['location'], $allLocations ) ) :
						$allLocations[$siteHit['location']] += 1;
					else:
						$allLocations[$siteHit['location']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['source']) ) :
					if ( array_key_exists($siteHit['source'], $allSources ) ) :
						$allSources[$siteHit['source']] += 1;
					else:
						$allSources[$siteHit['source']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['medium']) ) :
					if ( array_key_exists($siteHit['medium'], $allMediums ) ) :
						$allMediums[$siteHit['medium']] += 1;
					else:
						$allMediums[$siteHit['medium']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['browser']) ) :
					if ( array_key_exists($siteHit['browser'], $allBrowsers ) ) :
						$allBrowsers[$siteHit['browser']] += 1;
					else:
						$allBrowsers[$siteHit['browser']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['device']) ) :
					if ( array_key_exists($siteHit['device'], $allDevices ) ) :
						$allDevices[$siteHit['device']] += 1;
					else:
						$allDevices[$siteHit['device']] = 1;
					endif;									
				endif;									

				if ( isset($siteHit['resolution']) ) :
					if ( array_key_exists($siteHit['resolution'], $allResolutions ) ) :
						$allResolutions[$siteHit['resolution']] += 1;
					else:
						$allResolutions[$siteHit['resolution']] = 1;
					endif;									
				endif;									
			endif;
		endif;
	endif;
endforeach;	

if ( is_array($dailyStats)) :
	krsort($dailyStats);
	array_shift($dailyStats);
	$GLOBALS['dailyStats'] = $dailyStats;
else:
	$GLOBALS['dailyStats'] = array();
endif;

$GLOBALS['dates'] = array_keys($GLOBALS['dailyStats']);

// Set up Site Visitors widget on dashboard
function battleplan_admin_site_stats() {
	$lastVisitTime = timeElapsed( get_option('last_visitor_time'), 2);	
	
	$count = $users = $search = $pagesViewed = $sessions = $engaged = $engagement = $endOfCol = $viewsToday = $last7Views = $last30Views = $last90Views = $last180Views = $lastYearViews = $last2YearViews = $last3YearViews = 0;
		
	for ($x = 0; $x < 1096; $x++) {	
		if ( !isset($GLOBALS['dates'][$x])) break;
		$theDate = $GLOBALS['dates'][$x];
		$dailyUsers = isset($GLOBALS['dailyStats'][$theDate]['new-users']) ? intval($GLOBALS['dailyStats'][$theDate]['new-users']) : 0;
		$users = $users + $dailyUsers;			
				
		if ( $x == 0 ) $viewsToday = $users; 
		if ( $x == 6 ) $last7Views = $users; $last7Avg = number_format(($last7Views / 7),1);
		if ( $x == 29 ) $last30Views = $users; $last30Avg = number_format(($last30Views / 30),1);
		if ( $x == 89 ) $last90Views = $users; $last90Avg = number_format(($last90Views / 90),1);
		if ( $x == 179 ) $last180Views = $users; $last180Avg = number_format(($last180Views / 180),1);		
		if ( $x == 364 ) $lastYearViews = $users; $lastYearAvg = number_format(($lastYearViews / 365),1);		
		if ( $x == 729 ) $last2YearViews = $users; $last2YearAvg = number_format(($last2YearViews / 730),1);		
		if ( $x == 1095 ) $last3YearViews = $users; $last3YearAvg = number_format(($last3YearViews / 1095),1);
	} 		 
		
	echo "<table><tr><td class='label'>Last Visit</td><td class='last-visit'>".$lastVisitTime." ago</td></tr>";	
	echo "<tr><td>&nbsp;</td></tr>";		
	echo "<tr><td class='label'>Yesterday</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), number_format($viewsToday))."</td></tr>";	
	echo "<tr><td class='label'>This Week</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), number_format($last7Views) )."</td><td><b>".$last7Avg."</b> /day</td></tr>";
	
	if ( $last30Views != $last7Views) echo "<tr><td class='label'>This Month</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), number_format($last30Views) )."</td><td><b>".$last30Avg."</b> /day</td></tr>";
	
	if ( $last90Views != $last30Views) echo "<tr><td class='label'>3 Months</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last90Views, 'battleplan' ), number_format($last90Views) )."</td><td><b>".$last90Avg."</b> /day</td></tr>";
	
	if ( $last180Views != $last90Views) echo "<tr><td class='label'>6 Months</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last180Views, 'battleplan' ), number_format($last180Views) )."</td><td><b>".$last180Avg."</b> /day</td></tr>";
	
	if ( $lastYearViews != $last180Views) echo "<tr><td class='label'>1 Year</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $lastYearViews, 'battleplan' ), number_format($lastYearViews) )."</td><td><b>".$lastYearAvg."</b> /day</td></tr>";
	
	if ( $last2YearViews != $lastYearViews) echo "<tr><td class='label'>2 Years</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last2YearViews, 'battleplan' ), number_format($last2YearViews) )."</td><td><b>".$last2YearAvg."</b> /day</td></tr>";
	
	if ( $last3YearViews != $last2YearViews) echo "<tr><td class='label'>3 Years</td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last3YearViews, 'battleplan' ), number_format($last3YearViews) )."</td><td><b>".$last3YearAvg."</b> /day</td></tr>";
	
	echo '<tr><td>&nbsp;</td></tr></table>';
}

// Set up Visitor Referrers widget on dashboard
function battleplan_admin_referrer_stats() {
	echo '<div class="last-visitors-buttons">';	
		foreach ( $GLOBALS['displayPeriods'] as $display=>$days ) :
			if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
			echo do_shortcode('[btn size="1/5" class="'.$display.$active.'"]'.ucwords($display).'[/btn]');	
		endforeach;	
	echo '</div>';
				
	echo '<div class="local-visitors-buttons">'.do_shortcode('[btn size="1/5" class="local '.$GLOBALS['btn3'].'"]Local[/btn]').'</div>';	
	
	foreach ( $GLOBALS['displayPeriods'] as $display=>$days ) :
		$allSources = array();
		for ($x = 0; $x < $days; $x++) :	
		 	if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			$sources = isset($GLOBALS['dailyStats'][$theDate]['source']) ? $GLOBALS['dailyStats'][$theDate]['source'] : array();	
			
			foreach ( $sources as $source=>$counts ) :			
				$switchRef = array ('(direct)'=>'Direct', 'google'=>'Google', 'facebook'=>'Facebook', 'yelp'=>'Yelp', 'yahoo'=>'Yahoo', 'bing'=>'Bing', 'duckduckgo'=>'DuckDuckGo', 'youtube'=>'YouTube', 'instagram'=>'Instagram');
				foreach ( $switchRef as $find=>$replace ) :
					if ( strpos( $source, $find ) !== false ) $source = $replace;
				endforeach;
				
				if ( array_key_exists($source, $allSources ) ) :
					$allSources[$source] += $counts;
				else:
					$allSources[$source] = $counts;
				endif;		
			endforeach;		
		endfor;		
		
		arsort($allSources);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul>';		
		echo '<li class="sub-label" style="column-span: all">Last '.number_format(array_sum($allSources)).' Sessions</li>';	
		
		foreach ( $allSources as $source=>$count ) :
			echo "<li><div class='value'><b>".number_format($count)."</b></div><div class='label'>".$source."</div></li>";
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;			
}

// Set up Visitor Locations widget on dashboard
function battleplan_admin_location_stats() {
	foreach ( $GLOBALS['displayPeriods'] as $display=>$days ) :
		$allLocations = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			$locations = isset($GLOBALS['dailyStats'][$theDate]['location']) ? $GLOBALS['dailyStats'][$theDate]['location'] : array();	
			
			foreach ( $locations as $location=>$counts ) :			
				if ( array_key_exists($location, $allLocations ) ) :
					$allLocations[$location] += $counts;
				else:
					$allLocations[$location] = $counts;
				endif;		
			endforeach;		
		endfor;		
		
		arsort($allLocations);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul>';		
		echo '<li class="sub-label" style="column-span: all">Last '.number_format(array_sum($allLocations)).' Sessions</li>';	
		
		foreach ( $allLocations as $location=>$count ) :
			echo "<li><div class='value'><b>".number_format($count)."</b></div><div class='label'>".$location."</div></li>";
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;			
}

// Set up Tech Info widget on dashboard
function battleplan_admin_tech_stats() {
//Browser
	foreach ( $GLOBALS['displayPeriods'] as $display=>$days ) :
		$allBrowsers = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			$browsers = isset($GLOBALS['dailyStats'][$theDate]['browser']) ? $GLOBALS['dailyStats'][$theDate]['browser'] : array();	
			
			foreach ( $browsers as $browser=>$counts ) :			
				if ( array_key_exists($browser, $allBrowsers ) ) :
					$allBrowsers[$browser] += $counts;
				else:
					$allBrowsers[$browser] = $counts;
				endif;		
			endforeach;		
		endfor;		
		
		arsort($allBrowsers);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul><li class="sub-label">Browsers</li>';	
		
		foreach ( $allBrowsers as $browser=>$count ) :
			$count = ($count / array_sum($allBrowsers)) * 100;	
			if ( $count > 3) echo "<li><div class='value'><b>".number_format($count,1)."%</b></div><div class='label'>".ucwords($browser)."</div></li>";
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;		

// Devices
	$allTracking = is_array(get_option('bp_tracking_content')) ? get_option('bp_tracking_content') : array();
	$allSpeed = array();

	foreach ( $allTracking as $tracking ) :
		$site_speed = $tracking['speed'];
		$location = $tracking['location'];

		if ( !in_array( $location, $GLOBALS['citiesToExclude']) && $site_speed != '' ) :			
			$pageID = strtok($site_speed, '»');
			if ( strpos($site_speed, 'desktop') !== false ) : $device = "desktop"; else: $device = "mobile"; endif;			
			$speed = (float)str_replace($pageID.'»'.$device.'«', '', $site_speed);
			
			if ( $speed < 10 ) :
				if ( array_key_exists($pageID, $allSpeed ) && isset($allSpeed[$pageID]['speed']) ) :
					$allSpeed[$pageID]['speed'] += $speed;
					$allSpeed[$pageID]['hits'] += 1;
				else:
					$allSpeed[$pageID]['speed'] = $speed;
					$allSpeed[$pageID]['hits'] = 1;
				endif;						 		

				if ( $allSpeed[$pageID]['hits'] > 0 ) $allSpeed[$pageID]['avg'] = round($allSpeed[$pageID]['speed'] / $allSpeed[$pageID]['hits'], 2);	

				if ( array_key_exists($device, $allSpeed ) && isset($allSpeed[$device]['speed']) ) :
					$allSpeed[$device]['speed'] += $speed;
					$allSpeed[$device]['hits'] += 1;
				else:
					$allSpeed[$device]['speed'] = $speed;
					$allSpeed[$device]['hits'] = 1;
				endif;	
			endif;

			if ( $allSpeed[$device]['hits'] > 0 ) $allSpeed[$device]['avg'] = round($allSpeed[$device]['speed'] / $allSpeed[$device]['hits'], 2);	
		endif;			
	endforeach;		
	
	foreach ( $GLOBALS['displayPeriods'] as $display=>$days ) :
		$allDevices = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			$devices = isset($GLOBALS['dailyStats'][$theDate]['device']) ? $GLOBALS['dailyStats'][$theDate]['device'] : array();	
		
			foreach ( $devices as $device=>$counts ) :			
				if ( array_key_exists($device, $allDevices ) ) :
					$allDevices[$device] += $counts;
				else:
					$allDevices[$device] = $counts;
				endif;		
			endforeach;		
		endfor;		
		
		arsort($allDevices);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul><li class="sub-label">Devices</li>';	
		
		foreach ( $allDevices as $device=>$count ) :
			$count = ($count / array_sum($allDevices)) * 100;
			if ( isset($allSpeed[$device]['avg'])) :
				echo '<li><div class="value"><b>'.number_format($count,1).'%</b></div><div class="label-half">'.ucwords($device).'</div><div class="label-half">'.number_format($allSpeed[$device]['avg'],1).' sec</div></li>';
				updateOption('load_time_'.$device, number_format($allSpeed[$device]['avg'],1), false );
			endif;
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;		
	
// Screen Resolution	
	foreach ( $GLOBALS['displayPeriods'] as $display=>$days ) :
		$allResolutions = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x])) break;
			$theDate = $GLOBALS['dates'][$x];
			$resolutions = isset($GLOBALS['dailyStats'][$theDate]['resolution']) ? $GLOBALS['dailyStats'][$theDate]['resolution'] : array();	
			
			foreach ( $resolutions as $resolution=>$counts ) :	
				if ( $resolution ) :
					if ( array_key_exists($resolution, $allResolutions ) ) :
						$allResolutions[$resolution] += $counts;
					else:
						$allResolutions[$resolution] = $counts;
					endif;	
				endif;
			endforeach;		
		endfor;		
		
		arsort($allResolutions); 
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul><li class="sub-label">Screen Widths</li><div style="column-count:2">';	
		
		foreach ( $allResolutions as $resolution=>$count ) :
			$resolution = substr($resolution, 0, strpos($resolution, 'x'));
			$count = ($count / array_sum($allResolutions)) * 100;
			if ( $count > 2) echo "<li><div class='value'><b>".number_format($count,1)."%</b></div><div class='label'>".ucwords($resolution)." px</div></li>";
		endforeach;			
			
		echo '</div></ul></div>';		
	endforeach;	
}

// Set up Content Visibility widget on dashboard
function battleplan_admin_content_stats() {
	$contentTracking = $componentTracking = $colTracking = $totalTracking = array();
	$allTracking = is_array(get_option('bp_tracking_content')) ? get_option('bp_tracking_content') : array();
	
	foreach ( $allTracking as $tracking ) :
		$content_tracking = $tracking['content'];			
		$location = $tracking['location'];

		if ( !in_array( $location, $GLOBALS['citiesToExclude']) && $content_tracking != '' ) :
			$pageID = strtok($content_tracking,  '-');
			$track = str_replace($pageID.'-', '', $content_tracking);

			if ( array_key_exists($pageID, $contentTracking ) && isset($contentTracking[$pageID][$track]) ) :
				$contentTracking[$pageID][$track] += 1;
			else:
				$contentTracking[$pageID][$track] = 1;
			endif;	
		endif;			
	endforeach;
	
	foreach ( $contentTracking as $id=>$content) :
		foreach ( $content as $track=>$count ) :	
			if ( $id == "track" ) :
				if ( array_key_exists($track, $componentTracking ) ) :
					$componentTracking[$track] += $count;
				else:
					$componentTracking[$track] = $count;
				endif;				
			elseif ( strpos($track, '.') !== false ) :
				$track = explode(".", $track);
				$page = ucwords(get_the_title($id));
				$page = (strlen($page) > 17) ? substr($page,0,15).'&hellip;' : $page;			
				$column = $page.' · s'.$track[0].' c'.$track[1];
				if ( array_key_exists($column, $colTracking ) ) :
					$colTracking[$column] += $count;
				else:
					$colTracking[$column] = $count;
				endif;				
			else :
				if ( array_key_exists($track, $totalTracking ) ) :
					$totalTracking[$track] += $count;
				else:
					$totalTracking[$track] = $count;
				endif;				
			endif;
		endforeach;
	endforeach;
	
	echo '<div>';
	
	if ( isset($totalTracking['init'])) : 
		echo '<ul><li class="sub-label">Last '.$totalTracking['init'].' Pageviews</li>';
		if ( $totalTracking['init'] > 0 ) :
			echo "<li><div class='value'><b>".number_format((round($totalTracking['100']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed ALL of main content</b></div></li>";	
			echo "<li><div class='value'><b>".number_format((round($totalTracking['80']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed at least 80% of main content</b></div></li>";	
			echo "<li><div class='value'><b>".number_format((round($totalTracking['60']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed at least 60% of main content</b></div></li>";	
			echo "<li><div class='value'><b>".number_format((round($totalTracking['40']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed at least 40% of main content</b></div></li>";	
			echo "<li><div class='value'><b>".number_format(100-(round($totalTracking['20']/$totalTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>viewed less than 20% of main content</b></div></li>";
		endif;
		echo '</ul>';
	endif;
	
	arsort($componentTracking);
	echo '<ul><li class="sub-label">Components</li>';
	foreach($componentTracking as $track=>$count) :
		if ( $track != "init" && $componentTracking['init'] > 0 ) echo "<li><div class='value'><b>".number_format((round($componentTracking[$track]/$componentTracking['init'],3) * 100),1)."%</b></div><div class='label'><b>".ucwords($track)."</b></div></li>";	

		updateOption('pct-viewed-'.$track, number_format((round($componentTracking[$track]/$componentTracking['init'],3) * 100),1), false );
	endforeach;	
	echo '</ul>';

	arsort($colTracking);
	echo '<ul><li class="sub-label">Best Column Positions</li><div style="column-count:2">';		
	foreach ( $colTracking as $page=>$count) :
		echo "<li><div class='value'><b>".$count."</b></div><div class='label'>".$page."</div></li>";	
	endforeach;
	echo '</div></ul>';
	
	echo '</div>';
}

// Set up Popular Pages widget on dashboard
function battleplan_admin_pages_stats() {
	foreach ( $GLOBALS['displayPeriods'] as $display=>$days ) :
		$allPages = array();
		for ($x = 0; $x < $days; $x++) :	
			if ( !isset($GLOBALS['dates'][$x]) ) break;
			$theDate = $GLOBALS['dates'][$x];
			$pages = isset($GLOBALS['dailyStats'][$theDate]['page']) ? $GLOBALS['dailyStats'][$theDate]['page'] : array();	
			
			foreach ( $pages as $page=>$counts ) :	
				$excludePage = false;
				$excludes = array ( '?fbclid', '?dMe', '?_sm_nck', '?mscl' );
				foreach ( $excludes as $exclude ) :
					if ( strpos( $page, $exclude ) !== false ) $excludePage = true;
				endforeach;		
			
				if ( $excludePage == false ) :
					if ( array_key_exists($page, $allPages ) ) :
						$allPages[$page] += $counts;
					else:
						$allPages[$page] = $counts;
					endif;	
				endif;
			endforeach;		
		endfor;		
		
		arsort($allPages);
		
		if ( $GLOBALS['btn1'] == $display ) : $active = " active"; else: $active = ""; endif;
		echo '<div class="handle-label handle-label-'.$display.$active.'"><ul>';		
		echo '<li class="sub-label" style="column-span: all">Last '.number_format(array_sum($allPages)).' Pageviews</li>';	
		
		foreach ( $allPages as $page=>$count ) :
			if ( $page == "" ) :
				$title = "Home";					
			else:
				$pageID = getID($page);
		
				if ( !$pageID ) :
					$page = str_replace('/', ' » ', $page);				
					$page = str_replace('-', ' ', $page);				
					$title = ucwords($page);
				else:
					$title = get_the_title($pageID);				
				endif;
			endif;	

			echo "<li><div class='value'><b>".number_format($count)."</b></div><div class='label'>".$title."</div></li>";
		endforeach;			
			
		echo '</ul></div>';		
	endforeach;			
}

// Set up Visitor Trends widget on dashboard
function battleplan_admin_stats($time,$minDays,$maxDays,$colEnd) {
	$count = $sessions = $search = $users = $pagesViewed = $engaged = $engagement = $endOfCol = $pagesPerSession = 0;
	$days = $minDays;		
	$colNum = 1;
	
	echo "<table class='trends trends-".$time." trends-col-".$colNum."'><tr><td class='header dates'>".ucfirst($time)."</td><td class='page visits'>".ucwords($GLOBALS['btn2'])."</td></tr>";

	for ($x = 0; $x < 1500; $x++) {	
		if ( !isset($GLOBALS['dates'][$x])) break;
		$theDate = $GLOBALS['dates'][$x];
		$dailyTime = date("M j, Y", strtotime($theDate)); 
		
		$dailySessions = isset($GLOBALS['dailyStats'][$theDate]['sessions']) ? intval($GLOBALS['dailyStats'][$theDate]['sessions']) : 0;
		$dailySearch = isset($GLOBALS['dailyStats'][$theDate]['medium']['organic']) ? intval($GLOBALS['dailyStats'][$theDate]['medium']['organic']) : 0;
		$dailyUsers = isset($GLOBALS['dailyStats'][$theDate]['new-users']) ? intval($GLOBALS['dailyStats'][$theDate]['new-users']) : 0;
		$dailyPageviews = isset($GLOBALS['dailyStats'][$theDate]['pages-viewed']) ? intval($GLOBALS['dailyStats'][$theDate]['pages-viewed']) : 0;
		$dailyEngaged = isset($GLOBALS['dailyStats'][$theDate]['engaged']) ? intval($GLOBALS['dailyStats'][$theDate]['engaged']) : 0;
		
		$count++;		
		$sessions = $sessions + $dailySessions; 		
		$search = $search + $dailySearch; 
		$users = $users + $dailyUsers;			
		$pagesViewed = $pagesViewed + $dailyPageviews; 		
		$engaged = $engaged + $dailyEngaged; 
		
		if ( $sessions > 0 ) $pagesPerSession = number_format( (round(($pagesViewed / $sessions), 3)) , 1, '.', '');
		if ( $sessions > 0 ) $engagement = number_format( ((round(($engaged / $sessions), 3)) * 100), 1, '.', '');		
								
		if ( $count == 1 ) $end = $dailyTime;
		if ( $count == $days ) :
			if ( $endOfCol == $colEnd ) :
				$colNum++;
				echo "</table><table class='trends trends-".$time." trends-col-".$colNum."'><tr><td class='header dates'>".ucfirst($time)."</td><td class='page visits'>".ucwords($GLOBALS['btn2'])."</td></tr>";
				$endOfCol = 0;
			endif;
			$endOfCol++;
			
			$active['sessions'] = $active['search'] = $active['new'] = $active['pages'] = $active['engaged'] = '';
			$active[$GLOBALS['btn2']] = " active";

			$timeClass = strtolower(str_replace(array(" ",","), array("-",""), $dailyTime));
		 	echo "<tr class='coloration trends sessions".$active['sessions']." trends-".$timeClass."' data-count='".$sessions."'><td class='dates'>".$end."</td><td class='visits'><b>".number_format($sessions)."</b></td></tr>";
			echo "<tr class='coloration trends search".$active['search']."' data-count='".$search."'><td class='dates'>".$end."</td><td class='visits'><b>".number_format($search)."</b></td></tr>";			
		 	echo "<tr class='coloration trends new".$active['new']."' data-count='".$users."'><td class='dates'>".$end."</td><td class='visits'><b>".number_format($users)."</b></td></tr>";
			echo "<tr class='coloration trends pages".$active['pages']."' data-count='".$pagesViewed."'><td class='dates'>".$end."</td><td class='visits'><b>".number_format($pagesPerSession,1)."</b></td></tr>";
			echo "<tr class='coloration trends engaged".$active['engaged']."' data-count='".$engagement."'><td class='dates'>".$end."</td><td class='visits'><b>".$engagement."%</b></td></tr>";
			$count = $sessions = $search = $users = $pagesViewed = $engaged = $engagement = $pagesPerSession = 0;
			if ( $days == $maxDays ) : $days = $minDays; else: $days = $maxDays; endif;
		endif;	
	} 		
	echo "</table>";
}

function battleplan_admin_daily_stats() {
	$active['sessions'] = $active['search'] = $active['new'] = $active['pages'] = $active['engaged'] = '';
	$active[$GLOBALS['btn2']] = " active";
	
	echo '<div class="trend-buttons">';
		echo do_shortcode('[btn size="1/5" class="sessions'.$active['sessions'].'"]Sessions[/btn]');
		echo do_shortcode('[btn size="1/5" class="search'.$active['search'].'"]Search[/btn]');
		echo do_shortcode('[btn size="1/5" class="new'.$active['new'].'"]New[/btn]');
		echo do_shortcode('[btn size="1/5" class="pages'.$active['pages'].'"]Pageviews[/btn]');
		echo do_shortcode('[btn size="1/5" class="engaged'.$active['engaged'].'"]Engagement[/btn]');
	echo '</div>';

	battleplan_admin_stats('daily',1,1,365);
}

function battleplan_admin_weekly_stats() { battleplan_admin_stats('weekly',7,7,52); }
function battleplan_admin_monthly_stats() { battleplan_admin_stats('monthly',30,31,12); }
function battleplan_admin_quarterly_stats() { battleplan_admin_stats('quarterly',91,92,4); }

// Add custom meta boxes to posts & pages
add_action("add_meta_boxes", "battleplan_add_custom_meta_boxes");
function battleplan_add_custom_meta_boxes() {
	$getCPT = getCPT();
	foreach ( $getCPT as $postType ) :
		add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", $postType, "side", "default", null);
    endforeach;
}

// Set up Page Stats widget on posts & pages
function battleplan_page_stats() {
	global $post;
	$rightNow = strtotime(date("F j, Y g:i a"));
	$today = strtotime(date("F j, Y"));
	$lastViewed = strtotime(readMeta($post->ID, 'log-views-now'));		
	$getViews = readMeta($post->ID, 'log-views');
	$getViews = maybe_unserialize( $getViews );
	//$viewsToday = $getViews[0]['views'];
	//$firstDate = strtotime($getViews[0]['date']);
	if ( $firstDate != $today ) $viewsToday = 0;
	$last7Views = (int)readMeta($post->ID, "log-views-total-7day");
	$last30Views = (int)readMeta($post->ID, "log-views-total-30day");
	$last90Views = (int)readMeta($post->ID, "log-views-total-90day");	
	$last180Views = (int)readMeta($post->ID, "log-views-total-180day");	
	$last365Views = (int)readMeta($post->ID, "log-views-total-365day");
	$dateDiff = (($rightNow - $lastViewed) / 60 / 60 / 24); $howLong = "day";
	if ( $dateDiff < 1 ) : $dateDiff = (($rightNow - $lastViewed) / 60 / 60); $howLong = "hour"; endif;	
	if ( $dateDiff < 1 ) : $dateDiff = (($rightNow - $lastViewed) / 60); $howLong = "minute"; endif;
	if ( $dateDiff != 1 ) $howLong = $howLong."s";	
	$dateDiff = number_format($dateDiff, 0);	
	
	echo "<table>";		
	echo "<tr><td><b>Yesterday</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), number_format($viewsToday) )."</td></tr>";	
	echo "<tr><td><b>Last 7 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), number_format($last7Views) )."</td></tr>";
	if ( $last30Views != $last7Views) echo "<tr><td><b>Last 30 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), number_format($last30Views) )."</td></tr>";
	if ( $last90Views != $last30Views) echo "<tr><td><b>Last 90 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last90Views, 'battleplan' ), number_format($last90Views) )."</td></tr>";
	if ( $last180Views != $last90Views) echo "<tr><td><b>Last 180 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last180Views, 'battleplan' ), number_format($last180Views) )."</td></tr>";
	if ( $last365Views != $last180Views) echo "<tr><td><b>Last 365 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last365Views, 'battleplan' ), number_format($last365Views) )."</td></tr>";
	echo "</table>";		
    }; 
?>