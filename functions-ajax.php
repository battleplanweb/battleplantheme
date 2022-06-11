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
	
	$response = array( 'result' => 'Saved '.$value. ' to '.$key  );
	wp_send_json( $response );	
}

// Tracking logic for page scrolls & element views
add_action( 'wp_ajax_track_interaction', 'battleplan_track_interaction_ajax' );
add_action( 'wp_ajax_nopriv_track_interaction', 'battleplan_track_interaction_ajax' );
function battleplan_track_interaction_ajax() {
	$key = $_POST['key'];	
	$scroll = $_POST['scroll'];
	$viewed = $_POST['viewed'];
	$total = $_POST['total'];
	$uniqueID = $_POST['uniqueID'];
	
	if ( $scroll ) :	
		$tracking = get_option( $key );
		if ( $scroll > $tracking[$unique] ) :
			unset($tracking[$uniqueID]);
			$tracking[$uniqueID] = $scroll;
			update_option( $key, $tracking );
			$response = array( 'result' => $uniqueID . ' scrolled '.$scroll. '% of content' );
		endif;
	else:
		$tracking = get_option( $key );
		if ( $viewed > $tracking[$uniqueID]['viewed'] ) :
			unset($tracking[$uniqueID]);
			$tracking[$uniqueID] = array('viewed'=>$viewed, 'total'=>$total);
			update_option( $key, $tracking );
			$response = array( 'result' => $uniqueID . ' viewed '.$viewed. ' out of '.$total.' columns.' );
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
	
	if ( _USER_LOGIN != 'battleplanweb' ) :
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
				
		$response = array( 'result' => 'Logging '.$deviceTime.' load speed = '.number_format($loadTime, 2).'s' );
	else:
		$response = array( 'result' => ucfirst($deviceTime.' load speed = '.number_format($loadTime, 2).'s' ));
	endif;	
	
	wp_send_json( $response );	
}

// Count Teaser Views
add_action( 'wp_ajax_count_teaser', 'battleplan_count_teaser_ajax' );
add_action( 'wp_ajax_nopriv_count_teaser', 'battleplan_count_teaser_ajax' );
function battleplan_count_teaser_ajax() {
	$theID = intval( $_POST['id'] );
	$postType = get_post_type($theID);
	$lastTeased = date("F j, Y g:i a", readMeta($theID, 'log-tease-time'));
	$today = strtotime(date("F j, Y  g:i a"));
	
	if ( _USER_LOGIN != 'battleplanweb' ) :
		updateMeta($theID, 'log-tease-time', $today);
		$response = array( 'result' => 'Logging '.$postType.' ID #'.$theID.' tease time. Prior tease = '.$lastTeased );
	else:
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' last teased = '.$lastTeased) );
	endif;	
	wp_send_json( $response );	
}

?>
