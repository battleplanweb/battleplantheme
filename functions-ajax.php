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
	$failed  = false;

	// PHP bytecode FIRST. A deployed .php file that OPcache is still holding is invisible to
	// every purge below — the page regenerates, but from the OLD code, so the button appears to
	// do nothing. Worse: an SFTP upload that preserves the source mtime gives OPcache nothing
	// newer to revalidate against, so it never recompiles on its own and the stale copy sticks
	// indefinitely. opcache_invalidate($file, true) forces it regardless of timestamp.
	if ( function_exists( 'opcache_reset' ) && @opcache_reset() ) {
		$results[] = 'opcache: reset';
	} elseif ( function_exists( 'opcache_invalidate' ) ) {
		$invalidated = 0;
		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $theme_dir ) {
			foreach ( bp_theme_php_files( $theme_dir ) as $php_file ) {
				if ( @opcache_invalidate( $php_file, true ) ) $invalidated++;
			}
		}
		$results[] = 'opcache: invalidated ' . $invalidated;
	} else {
		$results[] = 'opcache: not available';
	}

	// Site config. functions-site.php is a DB seeder, not runtime config — its values only reach
	// wp_options through battleplan_updateSiteOptions(), which otherwise runs ONLY inside Chron B
	// (nightly, and only on a bot pageview). Without this, a freshly deployed functions-site.php
	// can sit unapplied for a full day no matter how many caches you clear.
	if ( function_exists( 'battleplan_updateSiteOptions' ) ) {
		battleplan_updateSiteOptions();
		if ( function_exists( 'customer_info' ) ) customer_info( true );
		$results[] = 'site options: synced';
	} else {
		$results[] = 'site options: no sync fn';
	}

	// WP Engine
	if ( class_exists( 'WpeCommon' ) ) {
		WpeCommon::purge_memcached();
		WpeCommon::purge_varnish_cache();
		$results[] = 'WPE: cleared';
	} else {
		$failed    = true;
		$results[] = 'WPE: WpeCommon not available';
	}

	// Cloudflare
	$api_key = _CF_CACHE_KEY;

	if ( $api_key ) {
		$domain = parse_url( get_site_url(), PHP_URL_HOST );

		// Resolve zone ID from site domain — cached so the lookup only runs once per site
		$zone_id = get_transient( 'bp_cf_zone_id' );
		if ( ! $zone_id ) {
			$zone_id = bp_cf_resolve_zone_id( $api_key, $domain );
			if ( $zone_id ) set_transient( 'bp_cf_zone_id', $zone_id, WEEK_IN_SECONDS );
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
				$failed    = true;
				$results[] = 'CF: ' . $cf_response->get_error_message();
			} else {
				$cf_body = json_decode( wp_remote_retrieve_body( $cf_response ), true );
				if ( ! empty( $cf_body['success'] ) ) {
					$results[] = 'CF: cleared';
				} else {
					// A cached zone ID that the API now rejects is poison for a full week —
					// drop it so the next click re-resolves instead of failing identically.
					delete_transient( 'bp_cf_zone_id' );
					$failed    = true;
					$results[] = 'CF: ' . ( $cf_body['errors'][0]['message'] ?? 'unknown error' );
				}
			}
		} else {
			$failed    = true;
			$results[] = 'CF: zone not found for ' . ( $domain ?: 'unknown domain' );
		}
	} else {
		$failed    = true;
		$results[] = 'CF: not configured (BP_CLOUDFLARE_KEY)';
	}

	// Compiled CSS — wipe the child theme's /dist folder so nothing stale survives.
	$dist = get_stylesheet_directory() . '/dist';
	if ( is_dir( $dist ) ) {
		$removed = bp_rrmdir( $dist );
		$results[] = $removed ? 'dist: deleted' : 'dist: delete failed';
	} else {
		$results[] = 'dist: none';
	}

	// Re-warm immediately: rebuild the compiled CSS now, in this same request, instead of
	// leaving /dist empty for the next pageload. An empty dist is the missing-CSS window that
	// makes a freshly-versioned CSS URL 404 — the very thing this button should PREVENT, not
	// cause. The just-deleted .sig files guarantee a full, clean recompile from source.
	$warmed = array();
	if ( function_exists( 'bp_build_css_core' ) ) { bp_build_css_core(); $warmed[] = 'core'; }
	if ( function_exists( 'bp_build_site_css' ) ) { bp_build_site_css(); $warmed[] = 'site'; }
	$results[] = $warmed ? 'dist: rebuilt (' . implode( '+', $warmed ) . ')' : 'dist: no rebuild fn';

	// Report honestly. This used to always return success, so a silently skipped Cloudflare purge
	// looked identical to a clean run and the button stayed green for months while doing half a job.
	$payload = array( 'message' => implode( ' · ', $results ) );
	if ( $failed ) wp_send_json_error( $payload );
	wp_send_json_success( $payload );
}

// Every .php file under a theme, skipping vendor/ and node_modules/ — the OPcache invalidation
// fallback's file list. Only used when opcache_reset() is unavailable or restricted.
function bp_theme_php_files( $dir ) {
	if ( ! is_dir( $dir ) ) return array();

	$files = array();
	$it    = new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS );
	$walk  = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator( $it, function ( $current ) {
			if ( ! $current->isDir() ) return true;
			return ! in_array( $current->getFilename(), array( 'vendor', 'node_modules', 'dist' ), true );
		} ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ( $walk as $file ) {
		if ( $file->isFile() && strtolower( $file->getExtension() ) === 'php' ) $files[] = $file->getPathname();
	}

	return $files;
}

// Cloudflare zone names are the registrable apex, so an exact ?name= lookup on a site whose WP
// Address carries a www. (or any subdomain) matches nothing and the purge silently no-ops. Walk
// the host down label by label, then fall back to suffix-matching the account's zone list.
function bp_cf_resolve_zone_id( $api_key, $host ) {
	if ( ! $host ) return '';

	$headers = array( 'Authorization' => 'Bearer ' . $api_key );
	$labels  = explode( '.', $host );

	for ( $i = 0; $i <= count( $labels ) - 2; $i++ ) {
		$candidate = implode( '.', array_slice( $labels, $i ) );
		$lookup    = wp_remote_get(
			'https://api.cloudflare.com/client/v4/zones?name=' . rawurlencode( $candidate ) . '&status=active',
			array( 'headers' => $headers, 'timeout' => 15 )
		);
		if ( is_wp_error( $lookup ) ) continue;
		$body    = json_decode( wp_remote_retrieve_body( $lookup ), true );
		$zone_id = $body['result'][0]['id'] ?? '';
		if ( $zone_id ) return $zone_id;
	}

	// Last resort — covers multi-part TLDs (.co.uk) that the label walk overshoots.
	$list = wp_remote_get(
		'https://api.cloudflare.com/client/v4/zones?per_page=50&status=active',
		array( 'headers' => $headers, 'timeout' => 15 )
	);
	if ( is_wp_error( $list ) ) return '';

	$body = json_decode( wp_remote_retrieve_body( $list ), true );
	$best = '';
	$len  = 0;

	foreach ( $body['result'] ?? array() as $zone ) {
		$name = $zone['name'] ?? '';
		if ( ! $name ) continue;
		$match = $host === $name || substr( $host, - ( strlen( $name ) + 1 ) ) === '.' . $name;
		if ( $match && strlen( $name ) > $len ) {
			$best = $zone['id'];
			$len  = strlen( $name );
		}
	}

	return $best;
}

// Recursively delete a directory and everything inside it. Returns true on full removal.
function bp_rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) return false;
	$items = array_diff( scandir( $dir ), array( '.', '..' ) );
	foreach ( $items as $item ) {
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			bp_rrmdir( $path );
		} else {
			@unlink( $path );
		}
	}
	return @rmdir( $dir );
}