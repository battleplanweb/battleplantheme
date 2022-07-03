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
	$clear = $_POST['clear'];
	$keyArray = explode(", ", $key);	
	
	if ( $clear == "true" ) :
		foreach ($keyArray as $key) delete_option( $key );
		$response = array( 'dashboard' => 'Content Tracking deleted.'  );	
	else:	
		if ( $type == "site" ) update_option( $key, $value );
		if ( $type == "user" ) update_user_meta( wp_get_current_user()->ID, $key, $value, false );
		if ( $type == "post" || $type == "page" ) updateMeta( get_the_ID(), $key, $value );	

		$response = array( 'dashboard' => 'Saved '.$value. ' to '.$key.'.'  );	
	endif;
	wp_send_json( $response );	
}

// Tracking logic for page scrolls & element views
add_action( 'wp_ajax_track_interaction', 'battleplan_track_interaction_ajax' );
add_action( 'wp_ajax_nopriv_track_interaction', 'battleplan_track_interaction_ajax' );
function battleplan_track_interaction_ajax() {
	$key = $_POST['key'];	
	$scroll = $_POST['scroll'];
	$viewed = $_POST['viewed'];
	$track = $_POST['track'];
	$uniqueID = $_POST['uniqueID'];	
	$page = get_the_title($_POST['page']);
	if ( $page == "" ) $page = "Home";
	
	$tracking = get_option( $key );
	if ( !is_array($tracking) ) $tracking = array();
	
	if ( _USER_LOGIN != 'battleplanweb' ) :
		if ( $scroll ) :	
			if ( $scroll > $tracking[$uniqueID] ) :
				unset($tracking[$uniqueID]);
				$tracking[$uniqueID] = $scroll;
				update_option( $key, $tracking );
				$response = array( 'dashboard' => $uniqueID . ' scrolled '.round(($scroll*100),1). '% of content.' );
			endif;
		elseif ( $viewed ) :
			$tracking[$uniqueID][] = ucwords($page).' - '.$viewed;
			update_option( $key, $tracking );
			$response = array( 'dashboard' => $uniqueID . ' viewed '.ucwords($page).' - '.$viewed );
		else:
			$tracking[$uniqueID][$track] = "true";
			update_option( $key, $tracking );
			$response = array( 'dashboard' => $uniqueID . ' tracked '.$track.'.' );
		endif;
	 endif;	
	
	wp_send_json( $response );	
}

// Log Page Load Speed
add_action( 'wp_ajax_log_page_load_speed', 'battleplan_log_page_load_speed_ajax' );
add_action( 'wp_ajax_nopriv_log_page_load_speed', 'battleplan_log_page_load_speed_ajax' );
function battleplan_log_page_load_speed_ajax() {
	$loadTime = $_POST['loadTime'];
	$deviceTime = $_POST['deviceTime'];
	$postID = $_POST['id'];
	
	if ( _USER_LOGIN != 'battleplanweb' ) :
	
		update_option( 'last_visitor_time', time() );
		updateMeta( $postID, 'log-last-viewed', time() );
		
		if ( $deviceTime == "desktop" ) :
			$timeDesktop = get_option('load_time_desktop');
			if ( is_array($timeDesktop) ) : array_unshift($timeDesktop, $loadTime);
			else: $timeDesktop = array($loadTime); endif;
			update_option('load_time_desktop', $timeDesktop);	
		else:
			$timeMobile = get_option('load_time_mobile');
			if ( is_array($timeMobile) ) : array_unshift($timeMobile, $loadTime);
			else: $timeMobile = array($loadTime); endif;
			update_option('load_time_mobile', $timeMobile);			
		endif;
				
		$response = array( 'dashboard' => 'Logging '.$deviceTime.' load speed = '.number_format($loadTime, 2).'s' );
	else:
		$response = array( 'dashboard' => ucfirst($deviceTime.' load speed = '.number_format($loadTime, 2).'s' ));
	endif;	
	
	wp_send_json( $response );	
}

// Count Views of testimonials, random images, etc.
add_action( 'wp_ajax_count_view', 'battleplan_count_view_ajax' );
add_action( 'wp_ajax_nopriv_count_view', 'battleplan_count_view_ajax' );
function battleplan_count_view_ajax() {
	$theID = intval( $_POST['id'] );
	$lastViewed = strtotime(readMeta($theID, 'log-last-viewed'));
	$rightNow = strtotime(date("F j, Y g:i a")) - 14400;	
	$today = strtotime(date("F j, Y"));		
	$dateDiff = (($today - $lastViewed) / 60 / 60 / 24);	
	
	if ( _USER_LOGIN != 'battleplanweb' ) :	
		$getViews = readMeta($theID, 'log-views');
		$getViews = maybe_unserialize( $getViews );
		if ( !is_array($getViews) ) $getViews = array();
		$viewsToday = $views7Day = $views30Day = $views90Day = $views180Day = $views365Day = intval(0); 

		if ( $dateDiff != 0 ) : // day has passed, move 29 to 30, and so on	
			for ($i = 1; $i <= $dateDiff; $i++) {	
				$figureTime = $today - ( ($dateDiff - $i) * 86400);	
				array_unshift($getViews, array ('date'=>date("F j, Y", $figureTime), 'views'=>$viewsToday));
			}	
		else:
			$viewsToday = intval($getViews[0]['views']); 
		endif;

		$viewsToday++;

		array_shift($getViews);	
		array_unshift($getViews, array ('date'=>date('F j, Y', $today), 'views'=>$viewsToday));	
		$newViews = maybe_serialize( $getViews );
		updateMeta($theID, 'log-views', $newViews);	
		updateMeta($theID, 'log-last-viewed', $rightNow);
		
		for ($x = 0; $x < 7; $x++) { $views7Day = $views7Day + intval($getViews[$x]['views']); } 					
		for ($x = 0; $x < 30; $x++) { $views30Day = $views30Day + intval($getViews[$x]['views']); } 						
		for ($x = 0; $x < 90; $x++) { $views90Day = $views90Day + intval($getViews[$x]['views']); } 		
		for ($x = 0; $x < 365; $x++) { $views365Day = $views365Day + intval($getViews[$x]['views']); } 		
		updateMeta($theID, 'log-views-today', $viewsToday);					
		updateMeta($theID, 'log-views-total-7day', $views7Day);			
		updateMeta($theID, 'log-views-total-30day', $views30Day);			 
		updateMeta($theID, 'log-views-total-90day', $views90Day);	
		updateMeta($theID, 'log-views-total-365day', $views365Day);	

		$response = array( 'dashboard' => 'Logging ID #'.$theID.' as viewed. Previous view = '.date("F j, Y g:i a", $lastViewed ));
	else:
		$response = array( 'dashboard' => ucfirst('ID #'.$theID.' last viewed = '.$lastViewed) );
	endif;	
	wp_send_json( $response );	
}

?>