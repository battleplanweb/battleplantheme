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


// Clear WP Engine network cache + Cloudflare zone cache
add_action( 'wp_ajax_bp_clear_wpe_cache', 'battleplan_clear_wpe_cache_ajax' );
function battleplan_clear_wpe_cache_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );
	check_ajax_referer( 'bp_clear_wpe_cache' );

	$results = array();

	// WP Engine
	if ( class_exists( 'WpeCommon' ) ) {
		WpeCommon::purge_memcached();
		WpeCommon::purge_varnish_cache();
		$results[] = 'WPE: cleared';
	} else {
		$results[] = 'WPE: WpeCommon not available';
	}

	// Cloudflare
	$api_key = _CF_CACHE_KEY;

	if ( $api_key ) {
		// Resolve zone ID from site domain — cached so the lookup only runs once per site
		$zone_id = get_transient( 'bp_cf_zone_id' );
		if ( ! $zone_id ) {
			$domain   = parse_url( get_site_url(), PHP_URL_HOST );
			$lookup   = wp_remote_get(
				'https://api.cloudflare.com/client/v4/zones?name=' . rawurlencode( $domain ) . '&status=active',
				array(
					'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
					'timeout' => 15,
				)
			);
			if ( ! is_wp_error( $lookup ) ) {
				$lookup_body = json_decode( wp_remote_retrieve_body( $lookup ), true );
				$zone_id     = $lookup_body['result'][0]['id'] ?? '';
				if ( $zone_id ) set_transient( 'bp_cf_zone_id', $zone_id, WEEK_IN_SECONDS );
			}
		}

		if ( $zone_id ) {
			$cf_response = wp_remote_request(
				'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/purge_cache',
				array(
					'method'  => 'POST',
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => '{"purge_everything":true}',
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $cf_response ) ) {
				$results[] = 'CF: ' . $cf_response->get_error_message();
			} else {
				$cf_body   = json_decode( wp_remote_retrieve_body( $cf_response ), true );
				$results[] = ! empty( $cf_body['success'] ) ? 'CF: cleared' : 'CF: ' . ( $cf_body['errors'][0]['message'] ?? 'unknown error' );
			}
		} else {
			$results[] = 'CF: zone not found for ' . ( $domain ?? 'unknown domain' );
		}
	} else {
		$results[] = 'CF: not configured';
	}

	wp_send_json_success( array( 'message' => implode( ' · ', $results ) ) );
} 