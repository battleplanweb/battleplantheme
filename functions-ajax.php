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
		$response = array( 'dashboard' => 'Content tracking cleared'  );	
	else:	
		if ( $type == "site" ) updateOption( $key, $value );
		if ( $type == "user" ) update_user_meta( wp_get_current_user()->ID, $key, $value, false );
		if ( $type == "post" || $type == "page" ) updateMeta( get_the_ID(), $key, $value );	

		$response = array( 'dashboard' => 'Updated {'.$key.'} to new value {'.$value.'}' );
	endif;
	wp_send_json( $response );	
} 

// Determine if user is real or bot
add_action( 'wp_ajax_check_user', 'battleplan_check_user_ajax' );
add_action( 'wp_ajax_nopriv_check_user', 'battleplan_check_user_ajax' );
function battleplan_check_user_ajax() {
	$distance = round($_POST['distance']);
	$location = $_POST['location'];	

	if ( _USER_LOGIN != "battleplanweb" && _IS_BOT != true ) :
		updateOption('last_visitor_time', strtotime(date("F j, Y g:i a"))); 
		$response = 'Counted: visitor from '.$location.', which is '.$distance.' miles from the business.';
	else: 
		$whoIs = _IS_BOT == true ? 'bot' : _USER_LOGIN;
		$response = 'Not Counted: '.$whoIs.' from '.$location.', which is '.$distance.' miles from the business.';
	endif;
	wp_send_json( $response );
}
?>