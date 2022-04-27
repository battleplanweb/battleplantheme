<?php 
/* Battle Plan Web Design Functions: AJAX

/*--------------------------------------------------------------
# AJAX Functions
--------------------------------------------------------------*/

// Change site option, post meta or user meta with javaScript
add_action( 'wp_ajax_update_meta', 'battleplan_update_meta_ajax' );
add_action( 'wp_ajax_nopriv_update_meta', 'battleplan_update_meta_ajax' );
function battleplan_update_meta_ajax() {
	$type = $_POST['type'];	
	$key = $_POST['key'];	
	$value = $_POST['value'];
	
	if ( $type == "site" ) update_option( $key, $value );
	if ( $type == "user" ) update_user_meta( wp_get_current_user()->ID, $key, $value, false );
	if ( $type == "post" || $type == "page" ) updateMeta( get_the_ID(), $key, $value );	
}

// Log Page Load Speed
add_action( 'wp_ajax_log_page_load_speed', 'battleplan_log_page_load_speed_ajax' );
add_action( 'wp_ajax_nopriv_log_page_load_speed', 'battleplan_log_page_load_speed_ajax' );
function battleplan_log_page_load_speed_ajax() {
	$userValid = $_POST['userValid'];	
	$userLoc = $_POST['userLoc'];	
	$loadTime = $_POST['loadTime'];
	$deviceTime = $_POST['deviceTime'];
	
	if ( _BP_COUNT_ALL_VISITS == "override" || ( _USER_LOGIN != 'battleplanweb' && $userLoc != "Ashburn, VA" && $userLoc != "Boardman, OR" && ( $userValid == "true" || _BP_COUNT_ALL_VISITS == "true" )) ) :
		$desktopCounted = readMeta(_HEADER_ID, "load-number-desktop");
		$desktopSpeed = readMeta(_HEADER_ID, "load-speed-desktop");	
		$mobileCounted = readMeta(_HEADER_ID, "load-number-mobile");
		$mobileSpeed = readMeta(_HEADER_ID, "load-speed-mobile");		
		$lastEmail = readMeta(_HEADER_ID, "last-email");
		$rightNow = strtotime(date("F j, Y, g:i a"));
		$daysSinceEmail = (($rightNow - $lastEmail) / 60 / 60 / 24);
		$totalCounted = $desktopCounted + $mobileCounted;	

		if ( ( $totalCounted > 300 && $daysSinceEmail > 45 ) || $daysSinceEmail > 100 ) :
			$desktopCount = sprintf( _n( '%s pageview', '%s pageviews', $desktopCounted, 'battleplan' ), $desktopCounted );
			$mobileCount = sprintf( _n( '%s pageview', '%s pageviews', $mobileCounted, 'battleplan' ), $mobileCounted );
			$emailTo = "info@battleplanwebdesign.com";
			$emailFrom = "From: Website Administrator <do-not-reply@battleplanwebdesign.com>";
			$subject = "Speed Report: ".$_SERVER['HTTP_HOST'];
			$content = $_SERVER['HTTP_HOST']." Speed Report\n\nDesktop = ".$desktopSpeed."s on ".$desktopCount."\nMobile = ".$mobileSpeed."s on ".$mobileCount."\n";	
			$desktopCounted = $desktopSpeed = $mobileCounted = $mobileSpeed = 0;
			updateMeta( _HEADER_ID, "last-email", $rightNow );	
			mail($emailTo, $subject, $content, $emailFrom);
		endif;

		if ( $deviceTime == "desktop" ) : 	
			$newTime = ($desktopCounted * $desktopSpeed) + $loadTime;
			$desktopCounted++;
			$desktopSpeed = (round($newTime / $desktopCounted, 1)); 
		else: 
			$newTime = ($mobileCounted * $mobileSpeed) + $loadTime;
			$mobileCounted++;
			$mobileSpeed = (round($newTime / $mobileCounted, 1));
		endif;

		updateMeta( _HEADER_ID, "load-number-desktop", $desktopCounted );	
		updateMeta( _HEADER_ID, "load-speed-desktop", $desktopSpeed );		
		updateMeta( _HEADER_ID, "load-number-mobile", $mobileCounted );	
		updateMeta( _HEADER_ID, "load-speed-mobile", $mobileSpeed );		
		$response = array( 'result' => ucfirst($deviceTime.' load speed = '.$loadTime.'s' ));
	else:
		$response = ucfirst($deviceTime.' load speed not counted: ');
		if ( _USER_LOGIN ) $response .= 'user='._USER_LOGIN.'; ';
		$response .= 'user location='.$userLoc.'; user in radius='.$userValid;
		$response = array( 'result' => $response );
	endif;	
	wp_send_json( $response );	
}

// Count Site Views
add_action( 'wp_ajax_count_site_views', 'battleplan_count_site_views_ajax' );
add_action( 'wp_ajax_nopriv_count_site_views', 'battleplan_count_site_views_ajax' );
function battleplan_count_site_views_ajax() {
	$userValid = $_POST['userValid'];		
	$userLoc = $_POST['userLoc'];	
	$userRefer = $_POST['userRefer'];	
	$userRefer = parse_url($userRefer);
	$userRefer = $userRefer['host'];
	$userRefer = str_replace(array("www.", "http://", "https://"), "", $userRefer);	
	$userIP = $_SERVER["REMOTE_ADDR"];
	$lastViewed = readMeta(_HEADER_ID, 'log-views-time');
	$rightNow = strtotime(date("F j, Y g:i a"));	
	$today = strtotime(date("F j, Y"));
	$dateDiff = (($today - $lastViewed) / 60 / 60 / 24);
	$getViews = readMeta(_HEADER_ID, 'log-views');
	$getViews = maybe_unserialize( $getViews );
	if ( !is_array($getViews) ) $getViews = array();
	$viewsToday = $views7Day = $views30Day = $views90Day = $views180Day = $views365Day = $searchToday = intval(0); 
	
			/*$userIP = 'Time: '.$rightNow.' Site: '.battleplan_getDomainName().' Location: '.$userLoc.' IP: <a href="https://whatismyipaddress.com/ip/'.$_SERVER["REMOTE_ADDR"].'">'.$_SERVER["REMOTE_ADDR"].'</a><br/>';
			$getIPs = readMeta(_HEADER_ID, 'log-views-ips');
			$getIPs = maybe_unserialize( $getIPs );
			if ( !is_array($getIPs) ) $getIPs = array();
			array_unshift($getIPs, $userIP);
			$newIPs = maybe_serialize( $getIPs );
			updateMeta(_HEADER_ID, 'log-views-ips', $newIPs);		*/	
		
	if ( _BP_COUNT_ALL_VISITS == "override" || ( _USER_LOGIN != 'battleplanweb' && $userLoc != "Ashburn, VA" && $userLoc != "Boardman, OR" && ( $userValid == "true" || _BP_COUNT_ALL_VISITS == "true" )) ) :
		if ( $dateDiff != 0 ) : // day has passed
			for ($i = 1; $i <= $dateDiff; $i++) {	
				$figureTime = $today - ( ($dateDiff - $i) * 86400);	
				array_unshift($getViews, array ('date'=>date("F j, Y", $figureTime), 'views'=>$viewsToday, 'search'=>$searchToday));
			}	
		else:
			$viewsToday = intval($getViews[0]['views']); 
			$searchToday = intval($getViews[0]['search']); 
		endif;	
		updateMeta(_HEADER_ID, 'log-views-now', $rightNow);
		updateMeta(_HEADER_ID, 'log-views-time', $today);	
		$viewsToday++;
		if ( strpos($userRefer, "google") !== false || strpos($userRefer, "yahoo") !== false || strpos($userRefer, "bing") !== false || strpos($userRefer, "duckduckgo") !== false ) $searchToday++;	
		array_shift($getViews);	
		array_unshift($getViews, array ('date'=>date('F j, Y', $today), 'views'=>$viewsToday, 'search'=>$searchToday));	
		$newViews = maybe_serialize( $getViews );
		updateMeta(_HEADER_ID, 'log-views', $newViews);

		for ($x = 0; $x < 7; $x++) { $views7Day = $views7Day + intval($getViews[$x]['views']); } 					
		for ($x = 0; $x < 30; $x++) { $views30Day = $views30Day + intval($getViews[$x]['views']); } 						
		for ($x = 0; $x < 90; $x++) { $views90Day = $views90Day + intval($getViews[$x]['views']); } 		
		for ($x = 0; $x < 180; $x++) { $views180Day = $views180Day + intval($getViews[$x]['views']); } 		
		for ($x = 0; $x < 365; $x++) { $views365Day = $views365Day + intval($getViews[$x]['views']); } 		
		updateMeta(_HEADER_ID, 'log-views-total-7day', $views7Day);			
		updateMeta(_HEADER_ID, 'log-views-total-30day', $views30Day);			 
		updateMeta(_HEADER_ID, 'log-views-total-90day', $views90Day);	
		updateMeta(_HEADER_ID, 'log-views-total-180day', $views180Day);	
		updateMeta(_HEADER_ID, 'log-views-total-365day', $views365Day);	

		$minimumCount = $views90Day < 250 ? 250 : $views90Day;

		$getReferrers = readMeta(_HEADER_ID, 'log-views-referrers');
		$getReferrers = maybe_unserialize( $getReferrers );
		if ( !is_array($getReferrers) ) $getReferrers = array();
		array_unshift($getReferrers, $userRefer);
		$limitReferrerCount = count($getReferrers) - $minimumCount;
		if ( $limitReferrerCount > 0 ) :
			for ($i=0; $i < $limitReferrerCount; $i++) :
				array_pop($getReferrers);
			endfor;
		endif;
		$newReferrers = maybe_serialize( $getReferrers );
		updateMeta(_HEADER_ID, 'log-views-referrers', $newReferrers);

		$getLocations = readMeta(_HEADER_ID, 'log-views-cities');
		$getLocations = maybe_unserialize( $getLocations );
		if ( !is_array($getLocations) ) $getLocations = array();
		array_unshift($getLocations, $userLoc);
		$limitLocationCount = count($getLocations) - $minimumCount;
		if ( $limitLocationCount > 0 ) :
			for ($i=0; $i < $limitLocationCount; $i++) :
				array_pop($getLocations);
			endfor;
		endif;
		$newLocations = maybe_serialize( $getLocations );
		updateMeta(_HEADER_ID, 'log-views-cities', $newLocations);

		$response = array( 'result' => 'Site View counted: Today='.$viewsToday.', Week='.$views7Day.', Month='.$views30Day.', Quarter='.$views90Day.', Year= '.$views365Day);
	else:
		$response = 'Site View NOT counted: ';
		if ( _USER_LOGIN ) $response .= 'user='._USER_LOGIN.'; ';
		$response .= 'user location='.$userLoc.'; user in radius='.$userValid;
		$response = array( 'result' => $response );
	endif;	
	wp_send_json( $response );	
}

// Count Page / Post Views
add_action( 'wp_ajax_count_post_views', 'battleplan_count_post_views_ajax' );
add_action( 'wp_ajax_nopriv_count_post_views', 'battleplan_count_post_views_ajax' );
function battleplan_count_post_views_ajax() {
	$uniqueID = $_POST['uniqueID'];
	$pagesViewed = intval( $_POST['pagesViewed']);
	$theID = intval( $_POST['id'] );
	$postType = get_post_type($theID);
	$userValid = $_POST['userValid'];	
	$userLoc = $_POST['userLoc'];	
	$lastViewed = readMeta($theID, 'log-views-time');
	$rightNow = strtotime(date("F j, Y g:i a"));	
	$today = strtotime(date("F j, Y"));
	$dateDiff = (($today - $lastViewed) / 60 / 60 / 24);
	$getPageviews = readMeta(_HEADER_ID, 'pages-viewed');
	$getPageviews = maybe_unserialize( $getPageviews );
	if ( !is_array($getPageviews) ) $getPageviews = array();
	$getViews = readMeta($theID, 'log-views');
	$getViews = maybe_unserialize( $getViews );
	if ( !is_array($getViews) ) $getViews = array();
	$viewsToday = $views7Day = $views30Day = $views90Day = $views180Day = $views365Day = intval(0); 
	
	if ( _BP_COUNT_ALL_VISITS == "override" || ( _USER_LOGIN != 'battleplanweb' && $userLoc != "Ashburn, VA" && $userLoc != "Boardman, OR" && ( $userValid == "true" || _BP_COUNT_ALL_VISITS == "true" )) ) :
		$visitCutoff = readMeta(_HEADER_ID, 'log-views-total-90day');
		$getPageviews[$uniqueID] = $pagesViewed;
		if ( count($getPageviews) > $visitCutoff ) array_shift($getPageviews);	
		$newPageviews = maybe_serialize( $getPageviews );
		updateMeta(_HEADER_ID, 'pages-viewed', $newPageviews);	
	
		if ( $dateDiff != 0 ) : // day has passed, move 29 to 30, and so on	
			for ($i = 1; $i <= $dateDiff; $i++) {	
				$figureTime = $today - ( ($dateDiff - $i) * 86400);	
				array_unshift($getViews, array ('date'=>date("F j, Y", $figureTime), 'views'=>$viewsToday));
			}	
		else:
			$viewsToday = intval($getViews[0]['views']); 
		endif;
	
		updateMeta($theID, 'log-views-now', $rightNow);
		updateMeta($theID, 'log-views-time', $today);	
		$viewsToday++;
		array_shift($getViews);	
		array_unshift($getViews, array ('date'=>date('F j, Y', $today), 'views'=>$viewsToday));	
		$newViews = maybe_serialize( $getViews );
		updateMeta($theID, 'log-views', $newViews);

		for ($x = 0; $x < 7; $x++) { $views7Day = $views7Day + intval($getViews[$x]['views']); } 					
		for ($x = 0; $x < 30; $x++) { $views30Day = $views30Day + intval($getViews[$x]['views']); } 						
		for ($x = 0; $x < 90; $x++) { $views90Day = $views90Day + intval($getViews[$x]['views']); } 		
		for ($x = 0; $x < 180; $x++) { $views180Day = $views180Day + intval($getViews[$x]['views']); } 		
		for ($x = 0; $x < 365; $x++) { $views365Day = $views365Day + intval($getViews[$x]['views']); } 		
		updateMeta($theID, 'log-views-today', $viewsToday);					
		updateMeta($theID, 'log-views-total-7day', $views7Day);			
		updateMeta($theID, 'log-views-total-30day', $views30Day);			 
		updateMeta($theID, 'log-views-total-90day', $views90Day);	
		updateMeta($theID, 'log-views-total-180day', $views180Day);	
		updateMeta($theID, 'log-views-total-365day', $views365Day);	
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' VIEW counted: Today='.$viewsToday.', Week='.$views7Day.', Month='.$views30Day.', Quarter='.$views90Day.', Year='.$views365Day.', User ID='.$uniqueID.', Pages Viewed='.$pagesViewed) );
	else:
		$response = ucfirst($postType.' ID #'.$theID.' view NOT counted: ');
		if ( _USER_LOGIN ) $response .= 'user='._USER_LOGIN.'; ';
		$response .= 'user location='.$userLoc.'; user in radius='.$userValid;
		$response = array( 'result' => $response );
	endif;	
	wp_send_json( $response );	
}

// Count Teaser Views
add_action( 'wp_ajax_count_teaser_views', 'battleplan_count_teaser_views_ajax' );
add_action( 'wp_ajax_nopriv_count_teaser_views', 'battleplan_count_teaser_views_ajax' );
function battleplan_count_teaser_views_ajax() {
	$theID = intval( $_POST['id'] );
	$postType = get_post_type($theID);
	$userValid = $_POST['userValid'];	
	$userLoc = $_POST['userLoc'];	
	$lastTeased = date("F j, Y g:i a", readMeta($theID, 'log-tease-time'));
	$today = strtotime(date("F j, Y  g:i a"));
	
	if ( _BP_COUNT_ALL_VISITS == "override" || ( _USER_LOGIN != 'battleplanweb' && $userLoc != "Ashburn, VA" && $userLoc != "Boardman, OR" && ( $userValid == "true" || _BP_COUNT_ALL_VISITS == "true" )) ) :
		updateMeta($theID, 'log-tease-time', $today);
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' TEASER counted: Prior tease = '.$lastTeased) );
	else:
		$response = ucfirst($postType.' ID #'.$theID.' teaser NOT counted: ');
		if ( _USER_LOGIN ) $response .= 'user='._USER_LOGIN.'; ';
		$response .= 'user location='.$userLoc.'; user in radius='.$userValid;
		$response = array( 'result' => $response );
	endif;	
	wp_send_json( $response );	
}

// Count Link Clicks
add_action( 'wp_ajax_count_link_clicks', 'battleplan_count_link_clicks_ajax' );
add_action( 'wp_ajax_nopriv_count_link_clicks', 'battleplan_count_link_clicks_ajax' );
function battleplan_count_link_clicks_ajax() {
	$type = $_POST['type'];	
	$thisYear = date("Y");		

	if ( $type == "phone call" ) : $getType = 'call-clicks';
	elseif ( $type == "email" ) : $getType = 'email-clicks';
	endif;

	$getClicks = readMeta(_HEADER_ID, $getType);	
	$getClicks = maybe_unserialize( $getClicks );
	if ( !is_array($getClicks) ) $getClicks = array();

	$recentYear = $getClicks[0]['year'];

	if ( $recentYear == $thisYear ) :
		$numClicks = intval($getClicks[0]['number']);	
		$numClicks++;
		array_shift($getClicks); // remove current value of year, so it can be replaced	
		array_unshift($getClicks, array ('year'=>$thisYear, 'number'=>$numClicks));		
	else:
		array_unshift($getClicks, array ('year'=>$thisYear, 'number'=>1));			
	endif;

	$newClicks = maybe_serialize( $getClicks );
	updateMeta(_HEADER_ID, $getType, $newClicks);

	$response = array( 'result' => $getType.' year = '.$thisYear.' counted = '.$numClicks);
	wp_send_json( $response );	
}

?>
