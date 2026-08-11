<?php
/* Battle Plan Web Design Functions: Admin */


/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shortcodes
# Admin Columns Set Up
# Admin Interface Set Up
# Admin Page Set Up
# Site Audit Set Up
# Contact Form 7 Set Up
--------------------------------------------------------------*/


//See all data points collected via Google Analytics
//bp_preload_images('Rollups: ' . print_r(get_option('bp_ga4_rollups_clean'), true));


/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

// Remove buttons from WordPress text editor
add_filter( 'quicktags_settings', 'battleplan_delete_quicktags', 10, 2 );
function battleplan_delete_quicktags( $qtInit, $editor_id = 'content' ) {
	$qtInit['buttons'] = 'strong,em,link,ul,ol,more,close';
	return $qtInit;
}

/*--------------------------------------------------------------
# Admin Columns Set Up
--------------------------------------------------------------*/
require_once get_template_directory() . '/functions-admin-columns.php';



/*--------------------------------------------------------------
# Admin Interface Set Up
--------------------------------------------------------------*/
// Disable Gutenburg
add_filter( 'use_block_editor_for_post', '__return_false' );
add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
add_filter( 'wp_use_widgets_block_editor', '__return_false' );

// Force Text tab (disable Visual editor without killing the QuickTags toolbar)
add_filter( 'wp_default_editor', function() { return 'html'; } );

// Allow separate editing of thumbnails in image editor
add_filter( 'image_edit_thumbnails_separately', '__return_true' );

// Load site-icon for admin bar
add_action('admin_head', 'battleplan__admin_bar_icon');
function battleplan__admin_bar_icon() {
	$iconData = get_option('bp_site_icon');
	$iconName = isset($iconData['name']) ? $iconData['name'] : 'site-icon.webp';
	$iconUrl = esc_url(get_site_url() . '/wp-content/uploads/' . $iconName);
	?>
	<style>
		.wp-admin #wpadminbar #wp-admin-bar-site-name > .ab-item::before,
		.logged-in #wpadminbar #wp-admin-bar-site-name > .ab-item::before {
			background-image: url('<?php echo $iconUrl; ?>') !important;
		}
	</style>
	<?php
}

// Add, Remove and Reorder Items in Admin Bar
add_action( 'wp_before_admin_bar_render', 'battleplan_reorderAdminBar');
function battleplan_reorderAdminBar() {
    global $wp_admin_bar;

	$loc = get_bloginfo( 'description' );
	$locMap = 'https://www.google.com/maps/place/'.str_replace(", ", "+", $loc).'/';

	if (get_bloginfo( 'description' )) $wp_admin_bar->add_node( array( 'id' => 'tagline', 'title' => '-&nbsp;&nbsp;'.$loc.'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', 'href'  => $locMap, ) );

    $IDs_sequence = array('site-name', 'tagline', 'suspend' );
    $nodes = $wp_admin_bar->get_nodes();
    foreach ( $IDs_sequence as $id ) {
        	if ( ! isset($nodes[$id]) ) continue;
	    	$wp_admin_bar->remove_menu($id);
	    	$wp_admin_bar->add_node($nodes[$id]);
        	unset($nodes[$id]);
    }
    foreach ( $nodes as $id => &$obj ) {
        	if ( ! empty($obj->parent) ) continue;
        	$wp_admin_bar->remove_menu($id);
        	$wp_admin_bar->add_node($obj);
    }
	$wp_admin_bar->remove_node('wp-logo');
	$wp_admin_bar->remove_node('wphb');
	$wp_admin_bar->remove_node('updates');
	$wp_admin_bar->remove_node('comments');
    $wp_admin_bar->remove_node('new-content');
    $wp_admin_bar->remove_node('wpengine_adminbar');
	$wp_admin_bar->remove_node('view-site');
	$wp_admin_bar->remove_node('wpseo-menu');
	$wp_admin_bar->remove_node('tribe-events');
	$wp_admin_bar->remove_node('wp-mail-smtp-menu');
}

// Create additional admin pages
add_action( 'admin_menu', 'battleplan_admin_menu' );
function battleplan_admin_menu() {
	// Prefer the stamped bp_audit_time; fall back to the newest STORED report so audits run before
	// bp_audit_time existed still show a date instead of "Never". Read each report's own 'date'
	// field (a full 'Y-m-d H:i:s' from current_time('mysql')) and take the max that's a real past
	// time — never the raw array KEY, which on partial/legacy data can be a non-datetime that
	// strtotime() mis-anchors to "today" and fakes a recent time on a site that never audited.
	$auditTs = (int) get_option('bp_audit_time');
	if ( ! $auditTs ) {
		$auditHist = get_option('bp_site_audit_ai_history');
		if ( is_array($auditHist) ) {
			$floor = strtotime('2020-01-01');
			foreach ( $auditHist as $rpt ) {
				$d  = is_array($rpt) ? (string) ( $rpt['date'] ?? '' ) : '';
				$ts = ( strlen($d) >= 10 ) ? (int) strtotime($d) : 0;   // require a full date, not a bare time
				if ( $ts > $floor && $ts <= time() && $ts > $auditTs ) $auditTs = $ts;
			}
		}
	}
	$auditTime          = $auditTs ? timeElapsed($auditTs, 1, 'all', 'full') . ' ago' : 'Never';
	$chronGbpTime       = get_option('bp_chron_a_time')      ? timeElapsed(get_option('bp_chron_a_time'),      1, 'all', 'full') . ' ago' : 'Never';
	$chronGbpApiTime    = get_option('bp_chron_a_api_time')  ? timeElapsed(get_option('bp_chron_a_api_time'),  1, 'all', 'full') . ' ago' : 'Never';
	$chronHouseTime     = get_option('bp_chron_b_time')      ? timeElapsed(get_option('bp_chron_b_time'),      1, 'all', 'full') . ' ago' : 'Never';
	$chronAnalyticsTime = get_option('bp_chron_c_time')      ? timeElapsed(get_option('bp_chron_c_time'),      1, 'all', 'full') . ' ago' : 'Never';

	$siteUpdated = str_replace('-', '', get_option( "site_updated" ));
	//add_menu_page( __( 'Run Chron', 'battleplan' ), __( 'Run Chron', 'battleplan' ), 'manage_options', 'run-chron', 'battleplan_force_run_chron', 'dashicons-performance', 3 );

// Menu registration — no separate Run buttons needed
	if ( _USER_LOGIN === "battleplanweb" ) :
		add_submenu_page( 'index.php', 	'Framework '._BP_VERSION, 	'Framework '._BP_VERSION, 											'manage_options', 	 'themes.php' );
		add_submenu_page( 'index.php',	'Housekeeping', 			'Settings <div class="admin-note">'.$chronHouseTime.'</div>',	'manage_options', 	 'chron-house',     		 'battleplan_chron_housekeeping_status' );
		add_submenu_page( 'index.php',	'GBP Sync',          		'GBP Sync <div class="admin-note">'.$chronGbpApiTime.'</div>',    	'manage_options', 	 'chron-gbp',       		 'battleplan_chron_gbp_status' );
		add_submenu_page( 'index.php',	'Analytics',   				'Stats <div class="admin-note">'.$chronAnalyticsTime.'</div>',	'manage_options', 	 'chron-analytics', 		 'battleplan_chron_analytics_status' );
		add_submenu_page( 'index.php',	'Site Audit',          		'Audit <div class="admin-note">'.$auditTime.'</div>',        	'manage_options', 	 'site-audit',      		 'battleplan_site_audit' );
		// 'Run Audit' submenu removed — the audit page itself now carries the Run/Resume Audit button.
	endif;

	// Analytics dashboard — client-facing (stats viewers + admins), not just battleplanweb
	if ( _USER_LOGIN === 'battleplanweb' || in_array( 'bp_view_stats', (array) _USER_ROLES ) || current_user_can( 'manage_options' ) ) {
		add_submenu_page( 'index.php', 'Analytics', 'Analytics', 'read', 'bp-analytics', 'bp_analytics_page' );
	}

	// Site Pulse — link straight to the front-end dashboard, only when the module is installed on this site
	if ( bp_module_on( get_option('site_pulse') ) ) {
		add_menu_page( 'Site Pulse', 'Site Pulse', 'read', esc_url( home_url('/site-pulse-dashboard/') ), '', 'dashicons-chart-line', 3 );
	}
}

// Menu registration
function battleplan_addSitePage() {
	echo '<h1>Admin Page</h1>';
}


/*
 * Analytics dashboard — per-channel trends over time from bp_ga4_channel_history.
 * Framing: "your work" (Organic Search + GBP) is paid-proof; Paid is quarantined;
 * Direct is shown but flagged noisy (it inflates during ad campaigns).
 */
function bp_an_delta( $pct, string $label ): string {
	if ( $pct === null ) return '<span class="bp-an-delta none">' . esc_html( $label ) . ' —</span>';
	$dir   = $pct > 0 ? 'up' : ( $pct < 0 ? 'down' : 'flat' );
	$arrow = $pct > 0 ? '▲'  : ( $pct < 0 ? '▼'  : '–' );
	return '<span class="bp-an-delta ' . $dir . '">' . esc_html( $label ) . ' ' . $arrow . ' ' . abs( (int) $pct ) . '%</span>';
}

/**
 * Home-town coordinates for the Analytics zoom map. Prefers the business's own saved coords
 * (customer_info lat/long); if those aren't set, geocodes the city/state once via Google
 * (_PLACES_API) and caches it in bp_home_latlng — so the service-area map works for any client
 * with an address, not only those with coordinates on file. Returns [lat, lng] or null.
 */
function bp_an_home_coords( array $ci ) {
	if ( isset( $ci['lat'], $ci['long'] ) && is_numeric( $ci['lat'] ) && is_numeric( $ci['long'] ) ) {
		return [ (float) $ci['lat'], (float) $ci['long'] ];
	}
	$cached = get_option( 'bp_home_latlng' );
	if ( is_array( $cached ) && isset( $cached['lat'], $cached['lng'] ) ) {
		return [ (float) $cached['lat'], (float) $cached['lng'] ];
	}
	$city  = trim( (string) ( $ci['city'] ?? '' ) );
	$state = trim( (string) ( $ci['state-abbr'] ?? '' ) );
	$addr  = trim( $city . ( $state ? ', ' . $state : '' ) );
	if ( $addr === '' || ! defined( '_PLACES_API' ) || ! _PLACES_API || get_transient( 'bp_home_geo_fail' ) ) {
		return null;
	}
	$resp = wp_remote_get(
		'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode( $addr ) . '&components=country:US&key=' . _PLACES_API,
		[ 'timeout' => 8 ]
	);
	if ( is_wp_error( $resp ) ) { set_transient( 'bp_home_geo_fail', true, HOUR_IN_SECONDS * 6 ); return null; }
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( ( $data['status'] ?? '' ) === 'OK' && isset( $data['results'][0]['geometry']['location'] ) ) {
		$loc = $data['results'][0]['geometry']['location'];
		$out = [ 'lat' => round( (float) $loc['lat'], 4 ), 'lng' => round( (float) $loc['lng'], 4 ) ];
		update_option( 'bp_home_latlng', $out, false );
		return [ $out['lat'], $out['lng'] ];
	}
	set_transient( 'bp_home_geo_fail', true, HOUR_IN_SECONDS * 6 );
	return null;
}

/**
 * Payload for the audit-derived metric cards (Search Console, Backlinks, Content, Reviews). Each card is
 * { metrics:[{key,label,unit}], daily/weekly/monthly:{periods, series:{metricKey:[…]}} }. Search Console is
 * dense daily (clicks/impressions summed, CTR derived, position impression-weighted); the rest are 3-day
 * snapshots rolled up as "latest value in the period". Cards with no history are simply omitted.
 */
function bp_an_audit_metrics_payload(): array {
	$out     = [];
	$weekKey = function ( $ymd ) { $ts = strtotime( $ymd ); $dow = (int) date( 'N', $ts ); return date( 'Ymd', strtotime( '-' . ( $dow - 1 ) . ' days', $ts ) ); };
	$keyers  = [ 'daily' => function ( $p ) { return $p; }, 'weekly' => $weekKey, 'monthly' => function ( $p ) { return substr( $p, 0, 6 ); } ];
	$limits  = [ 'daily' => 180, 'weekly' => 104, 'monthly' => 36 ];

	// Snapshot rollup: latest value per period (for slow-moving counts).
	$snap = function ( $optKey, array $mkeys ) use ( $keyers, $limits ) {
		$hist = get_option( $optKey );
		if ( ! is_array( $hist ) || ! $hist ) return null;
		$grains = []; $any = false;
		foreach ( $keyers as $g => $keyer ) {
			$byPeriod = [];
			foreach ( $hist as $ymd => $rec ) {
				if ( strlen( (string) $ymd ) !== 8 || ! is_array( $rec ) ) continue;
				$pk = $keyer( (string) $ymd );
				if ( ! isset( $byPeriod[ $pk ] ) || (string) $ymd > $byPeriod[ $pk ]['d'] ) $byPeriod[ $pk ] = [ 'd' => (string) $ymd, 'v' => $rec ];
			}
			if ( ! $byPeriod ) { $grains[ $g ] = null; continue; }
			ksort( $byPeriod );
			$periods = array_slice( array_keys( $byPeriod ), -$limits[ $g ] );
			$series  = [];
			foreach ( $mkeys as $m ) { $series[ $m ] = []; foreach ( $periods as $pk ) $series[ $m ][] = (float) ( $byPeriod[ $pk ]['v'][ $m ] ?? 0 ); }
			$grains[ $g ] = [ 'periods' => array_map( 'strval', $periods ), 'series' => $series ];
			$any = true;
		}
		return $any ? $grains : null;
	};

	// Sum rollup: total per period (for dense daily count metrics like GBP performance).
	$sum = function ( $optKey, array $mkeys ) use ( $keyers, $limits ) {
		$hist = get_option( $optKey );
		if ( ! is_array( $hist ) || ! $hist ) return null;
		$grains = []; $any = false;
		foreach ( $keyers as $g => $keyer ) {
			$acc = [];
			foreach ( $hist as $ymd => $rec ) {
				if ( strlen( (string) $ymd ) !== 8 || ! is_array( $rec ) ) continue;
				$pk = $keyer( (string) $ymd );
				foreach ( $mkeys as $m ) $acc[ $pk ][ $m ] = ( $acc[ $pk ][ $m ] ?? 0 ) + (float) ( $rec[ $m ] ?? 0 );
			}
			if ( ! $acc ) { $grains[ $g ] = null; continue; }
			ksort( $acc );
			$periods = array_slice( array_keys( $acc ), -$limits[ $g ] );
			$series  = [];
			foreach ( $mkeys as $m ) { $series[ $m ] = []; foreach ( $periods as $pk ) $series[ $m ][] = round( $acc[ $pk ][ $m ] ?? 0 ); }
			$grains[ $g ] = [ 'periods' => array_map( 'strval', $periods ), 'series' => $series ];
			$any = true;
		}
		return $any ? $grains : null;
	};

	// Search Console — dense daily; clicks/impressions sum, CTR derived, position impression-weighted.
	$scHist = get_option( 'bp_gsc_totals_history' );
	if ( is_array( $scHist ) && $scHist ) {
		$grains = []; $any = false;
		foreach ( $keyers as $g => $keyer ) {
			$acc = [];
			foreach ( $scHist as $ymd => $rec ) {
				if ( strlen( (string) $ymd ) !== 8 || ! is_array( $rec ) ) continue;
				$pk = $keyer( (string) $ymd );
				$im = (int) ( $rec['impressions'] ?? 0 );
				$acc[ $pk ]['clicks']  = ( $acc[ $pk ]['clicks']  ?? 0 ) + (int) ( $rec['clicks'] ?? 0 );
				$acc[ $pk ]['impr']    = ( $acc[ $pk ]['impr']    ?? 0 ) + $im;
				$acc[ $pk ]['posImpr'] = ( $acc[ $pk ]['posImpr'] ?? 0 ) + (float) ( $rec['position'] ?? 0 ) * $im;
			}
			if ( ! $acc ) { $grains[ $g ] = null; continue; }
			ksort( $acc );
			$periods = array_slice( array_keys( $acc ), -$limits[ $g ] );
			$series  = [ 'clicks' => [], 'impressions' => [], 'ctr' => [], 'position' => [] ];
			foreach ( $periods as $pk ) {
				$cl = $acc[ $pk ]['clicks']; $im = $acc[ $pk ]['impr']; $pi = $acc[ $pk ]['posImpr'];
				$series['clicks'][]      = $cl;
				$series['impressions'][] = $im;
				$series['ctr'][]         = $im > 0 ? round( $cl / $im * 100, 2 ) : 0;
				$series['position'][]    = $im > 0 ? round( $pi / $im, 1 ) : 0;
			}
			$grains[ $g ] = [ 'periods' => array_map( 'strval', $periods ), 'series' => $series ];
			$any = true;
		}
		if ( $any ) $out['searchConsole'] = array_merge( [ 'metrics' => [
			[ 'key' => 'clicks',      'label' => 'Clicks',       'unit' => 'int' ],
			[ 'key' => 'impressions', 'label' => 'Impressions',  'unit' => 'int' ],
			[ 'key' => 'ctr',         'label' => 'CTR',          'unit' => 'pct' ],
			[ 'key' => 'position',    'label' => 'Avg Position', 'unit' => 'pos' ],
		] ], $grains );
	}

	if ( $bl = $snap( 'bp_gsc_links_history', [ 'backlinks', 'domains' ] ) ) {
		$out['backlinks'] = array_merge( [ 'metrics' => [
			[ 'key' => 'backlinks', 'label' => 'Backlinks',       'unit' => 'int' ],
			[ 'key' => 'domains',   'label' => 'Linking Domains', 'unit' => 'int' ],
		] ], $bl );
	}
	if ( $ct = $snap( 'bp_content_history', [ 'blog', 'pages', 'jobsites', 'galleries', 'testimonials', 'images' ] ) ) {
		$out['content'] = array_merge( [ 'metrics' => [
			[ 'key' => 'blog',         'label' => 'Blog Posts',   'unit' => 'int' ],
			[ 'key' => 'pages',        'label' => 'Pages',        'unit' => 'int' ],
			[ 'key' => 'jobsites',     'label' => 'Jobsites',     'unit' => 'int' ],
			[ 'key' => 'galleries',    'label' => 'Galleries',    'unit' => 'int' ],
			[ 'key' => 'testimonials', 'label' => 'Testimonials', 'unit' => 'int' ],
			[ 'key' => 'images',       'label' => 'Images',       'unit' => 'int' ],
		] ], $ct );
	}
	if ( $rv = $snap( 'bp_gbp_stats_history', [ 'reviews', 'rating' ] ) ) {
		$out['reviews'] = array_merge( [ 'metrics' => [
			[ 'key' => 'reviews', 'label' => 'Reviews', 'unit' => 'int' ],
			[ 'key' => 'rating',  'label' => 'Rating',  'unit' => 'rating' ],
		] ], $rv );
	}
	if ( $gp = $sum( 'bp_gbp_perf_history', [ 'impressions', 'calls', 'website', 'directions' ] ) ) {
		$out['gbpPerf'] = array_merge( [ 'metrics' => [
			[ 'key' => 'impressions', 'label' => 'Impressions',    'unit' => 'int' ],
			[ 'key' => 'calls',       'label' => 'Call Clicks',    'unit' => 'int' ],
			[ 'key' => 'website',     'label' => 'Website Clicks', 'unit' => 'int' ],
			[ 'key' => 'directions',  'label' => 'Directions',     'unit' => 'int' ],
		] ], $gp );
	}

	return $out;
}

/**
 * Payload for the Keyword Rankings stacked-band card. Reads bp_kw_bands_history (3-day snapshots of how
 * many tracked keywords sit in each rank band) and rolls it up per grain as "latest snapshot in the
 * period". Returns { bands:[{key,label,cls}], daily/weekly/monthly:{periods, series:{b1..b4:[…]}} } or null.
 */
function bp_an_keywords_payload(): ?array {
	$hist = get_option( 'bp_kw_bands_history' );
	if ( ! is_array( $hist ) || ! $hist ) return null;
	$mkeys   = [ 'b1', 'b2', 'b3', 'b4' ];
	$weekKey = function ( $ymd ) { $ts = strtotime( $ymd ); $dow = (int) date( 'N', $ts ); return date( 'Ymd', strtotime( '-' . ( $dow - 1 ) . ' days', $ts ) ); };
	$keyers  = [ 'daily' => function ( $p ) { return $p; }, 'weekly' => $weekKey, 'monthly' => function ( $p ) { return substr( $p, 0, 6 ); } ];
	$limits  = [ 'daily' => 180, 'weekly' => 104, 'monthly' => 36 ];

	$grains = []; $any = false;
	foreach ( $keyers as $g => $keyer ) {
		$byPeriod = [];
		foreach ( $hist as $ymd => $rec ) {
			if ( strlen( (string) $ymd ) !== 8 || ! is_array( $rec ) ) continue;
			$pk = $keyer( (string) $ymd );
			if ( ! isset( $byPeriod[ $pk ] ) || (string) $ymd > $byPeriod[ $pk ]['d'] ) $byPeriod[ $pk ] = [ 'd' => (string) $ymd, 'v' => $rec ];
		}
		if ( ! $byPeriod ) { $grains[ $g ] = null; continue; }
		ksort( $byPeriod );
		$periods = array_slice( array_keys( $byPeriod ), -$limits[ $g ] );
		$series  = [];
		foreach ( $mkeys as $m ) { $series[ $m ] = []; foreach ( $periods as $pk ) $series[ $m ][] = (int) ( $byPeriod[ $pk ]['v'][ $m ] ?? 0 ); }
		$grains[ $g ] = [ 'periods' => array_map( 'strval', $periods ), 'series' => $series ];
		$any = true;
	}
	if ( ! $any ) return null;
	return array_merge( [ 'bands' => [
		[ 'key' => 'b1', 'label' => '1–3',   'cls' => 'kw1' ],
		[ 'key' => 'b2', 'label' => '4–10',  'cls' => 'kw2' ],
		[ 'key' => 'b3', 'label' => '11–20', 'cls' => 'kw3' ],
		[ 'key' => 'b4', 'label' => '21+',   'cls' => 'kw4' ],
	] ], $grains );
}

// "Send Check-in" test button (Analytics footer) — force-runs the customer check-in and emails it to
// you now, bypassing the send throttle. The check-in file only loads during the analytics cron, so we
// require it here.
add_action('admin_post_bp_send_checkin', 'bp_send_checkin_now');
function bp_send_checkin_now(): void {
	if (!current_user_can('manage_options')) wp_die('Not allowed.');
	check_admin_referer('bp_send_checkin');
	$f = get_template_directory() . '/functions-customer-checkins.php';
	if (file_exists($f)) require_once $f;
	if (function_exists('bp_customer_email_force'))  bp_customer_email_force();   // clear throttles
	if (function_exists('bp_run_customer_checkins')) bp_run_customer_checkins();
	wp_safe_redirect(add_query_arg('bp_checkin', 'sent', admin_url('index.php?page=bp-analytics')));
	exit;
}
add_action('admin_notices', 'bp_send_checkin_notice');
function bp_send_checkin_notice(): void {
	if (($_GET['bp_checkin'] ?? '') !== 'sent' || !current_user_can('manage_options')) return;
	$to = defined('BP_EMAIL_RECIPIENT') ? BP_EMAIL_RECIPIENT : 'your inbox';
	echo '<div class="notice notice-success is-dismissible"><p><strong>Check-in generated.</strong> If there were wins to report, an email is on its way to <strong>' . esc_html($to) . '</strong> (the AI progress summary can take a few seconds). Nothing sends if there was nothing positive to say yet.</p></div>';
}

function bp_analytics_page() {

	if ( ! ( _USER_LOGIN === 'battleplanweb' || in_array( 'bp_view_stats', (array) _USER_ROLES ) || current_user_can( 'manage_options' ) ) ) {
		wp_die( 'You do not have permission to view analytics.' );
	}

	$historyM = get_option( 'bp_ga4_channel_history' );
	$historyW = get_option( 'bp_ga4_channel_history_weekly' );
	$historyD = get_option( 'bp_ga4_channel_history_daily' );

	echo '<div class="wrap bp-analytics">';
	echo '<h1>Analytics <span class="bp-an-sub">Channel &amp; visitor trends</span></h1>';

	if ( ! is_array( $historyM ) || ! $historyM ) {
		echo '<p>No channel history yet — it builds on the nightly analytics run, or '
		   . '<a href="' . esc_url( admin_url( 'index.php?page=chron-analytics' ) ) . '">refresh stats now</a>.</p></div>';
		return;
	}

	krsort( $historyM ); // newest month first
	$monthKeys = array_keys( $historyM );

	$CLEAN    = [ 'Organic Search', 'GBP' ];
	$PAID     = [ 'Paid Search', 'Paid Social', 'Paid Other' ];  // Paid Social stays under Paid
	$DIRECT   = [ 'Direct' ];
	$SOCIAL   = [ 'Organic Social' ];
	$REFERRAL = [ 'Referral' ];
	$OTHER    = [ 'Email', 'Unassigned' ];

	$sum = function ( $ym, $channels, $metric ) use ( $historyM ) {
		$t = 0.0;
		foreach ( $channels as $ch ) $t += (float) ( $historyM[ $ym ][ $ch ][ $metric ] ?? 0 );
		return $t;
	};

	// Hero tiles — shells only; JS fills the numbers so they FOLLOW the active range
	// control (30d/60d/… /custom), with "vs prev" (previous equal window) and YoY deltas.
	$tiles = [
		[ 'Your Work', 'Organic Search + GBP',   'Organic Search,GBP', 'good' ],
		[ 'Paid',      'Ad-driven',               'Paid',               'paid' ],
		[ 'Direct',    'Noisy · paid-influenced', 'Direct',             'muted' ],
	];

	echo '<div class="bp-an-tiles">';
	foreach ( $tiles as [ $label, $subLabel, $chs, $tone ] ) {
		echo '<div class="bp-an-tile tone-' . esc_attr( $tone ) . '" data-chs="' . esc_attr( $chs ) . '">';
		echo   '<div class="bp-an-tile-label">' . esc_html( $label ) . '</div>';
		echo   '<div class="bp-an-tile-sub">' . esc_html( $subLabel ) . '</div>';
		echo   '<div class="bp-an-tile-value"></div>';
		echo   '<div class="bp-an-tile-deltas"></div>';
		echo '</div>';
	}
	echo '</div>';
	echo '<p class="bp-an-period" id="bp-an-period-lbl"></p>';

	// Both-grain payload for the charts (weekly + monthly). One shared <script> element.
	$buildGrain = function ( $hist, $limit ) use ( $PAID, $DIRECT, $SOCIAL, $REFERRAL, $OTHER ) {
		if ( ! is_array( $hist ) || ! $hist ) return null;
		krsort( $hist );
		$keys = array_reverse( array_slice( array_keys( $hist ), 0, $limit ) ); // ascending
		$chanSessions = function ( $channels ) use ( $keys, $hist ) {
			$o = [];
			foreach ( $keys as $k ) {
				$t = 0.0;
				foreach ( $channels as $ch ) $t += (float) ( $hist[ $k ][ $ch ]['sessions'] ?? 0 );
				$o[] = $t;
			}
			return $o;
		};
		$siteMetric = function ( $metric ) use ( $keys, $hist ) {
			$o = [];
			foreach ( $keys as $k ) {
				$t = 0.0;
				if ( isset( $hist[ $k ] ) && is_array( $hist[ $k ] ) ) foreach ( $hist[ $k ] as $mm ) $t += (float) ( $mm[ $metric ] ?? 0 );
				$o[] = $t;
			}
			return $o;
		};
		return [
			'periods'  => array_map( 'strval', $keys ),
			'channels' => [
				'Organic Search' => $chanSessions( [ 'Organic Search' ] ),
				'GBP'            => $chanSessions( [ 'GBP' ] ),
				'Paid'           => $chanSessions( $PAID ),
				'Direct'         => $chanSessions( $DIRECT ),
				'Social'         => $chanSessions( $SOCIAL ),
				'Referral'       => $chanSessions( $REFERRAL ),
				'Other'          => $chanSessions( $OTHER ),
			],
			'site' => [
				'sessions'        => $siteMetric( 'sessions' ),
				'users'           => $siteMetric( 'users' ),
				'newUsers'        => $siteMetric( 'newUsers' ),
				'engagedSessions' => $siteMetric( 'engagedSessions' ),
				'pageviews'       => $siteMetric( 'pageviews' ),
				'duration'        => $siteMetric( 'duration' ),
			],
		];
	};

	// Tech breakdowns (browsers / devices / screen widths) — per-window snapshots (7/30/90/180/365d),
	// top N + "Other", for the pie row. These are rolling snapshots, not a time series.
	$techPie = function ( $optKey, $topN, $colorMap = [] ) {
		$data = get_option( $optKey );
		$out  = [];
		if ( ! is_array( $data ) ) return $out;
		foreach ( [ 7, 30, 90, 180, 365 ] as $w ) {
			$items = [];
			foreach ( $data as $name => $metrics ) {
				if ( ! is_array( $metrics ) ) continue;
				$v = (int) ( $metrics[ "sessions-{$w}" ] ?? 0 );
				if ( $v > 0 ) $items[ (string) $name ] = $v;
			}
			arsort( $items );
			$slices = []; $i = 0; $other = 0;
			foreach ( $items as $name => $v ) {
				if ( $i < $topN ) {
					$slice = [ 'l' => $name, 'v' => $v ];
					if ( isset( $colorMap[ $name ] ) ) $slice['c'] = $colorMap[ $name ];  // fixed per-entity color
					$slices[] = $slice;
				} else {
					$other += $v;
				}
				$i++;
			}
			if ( $other > 0 ) $slices[] = [ 'l' => 'Other', 'v' => $other ];
			$out[ $w ] = $slices;
		}
		return $out;
	};

	// GA4 device category, relabeled (mobile → Phone) for the Device-type donut.
	$deviceTypePie = function () {
		// fixed order + fixed colors: Desktop=blue, Phone=green, Tablet=orange.
		$map  = [ 'desktop' => [ 'Desktop', 'blue' ], 'mobile' => [ 'Phone', 'green' ], 'tablet' => [ 'Tablet', 'orange' ] ];
		$data = get_option( 'bp_ga4_devices_clean' );
		$out  = [];
		if ( ! is_array( $data ) ) return $out;
		foreach ( [ 7, 30, 90, 180, 365 ] as $w ) {
			$slices = [];
			foreach ( $map as $cat => $info ) {
				$v = (int) ( $data[ $cat ][ "sessions-{$w}" ] ?? 0 );
				if ( $v > 0 ) $slices[] = [ 'l' => $info[0], 'v' => $v, 'c' => $info[1] ];
			}
			$out[ $w ] = $slices;
		}
		return $out;
	};
	// Screen-width donut bands — sessions per band, ALL devices, height ignored. 1920 (FHD) is its
	// own exact slice; ">1920" is everything above it. Labels are the band's low edge (per request).
	$widthBands = [
		[    0,  576, '<576',  'green'   ],  // 575 and smaller
		[  576,  860, '576',   'teal'    ],  // 576–859
		[  860, 1024, '860',   'red'     ],  // 860–1023
		[ 1024, 1280, '1024',  'red'     ],  // 1024–1279
		[ 1280, 1440, '1280',  'orange'  ],  // 1280–1439
		[ 1440, 1920, '1440',  'ltblue'  ],  // 1440–1919
		[ 1920, 1921, '1920',  'blue'    ],  // exactly 1920 (FHD desktops + FHD Android physical panels)
		[ 1921, PHP_INT_MAX, '>1920', 'dviolet' ],
	];
	$widthPie = function () use ( $widthBands ) {
		$data = get_option( 'bp_ga4_device_width_clean' );
		$out  = [];
		if ( ! is_array( $data ) ) return $out;
		foreach ( [ 7, 30, 90, 180, 365 ] as $w ) {
			$totals = array_fill( 0, count( $widthBands ), 0 );
			foreach ( $data as $key => $metrics ) {
				if ( ! is_array( $metrics ) ) continue;
				$parts = explode( '|', $key, 2 );
				if ( count( $parts ) !== 2 ) continue;
				$width = (int) explode( 'x', $parts[1] )[0];
				if ( $width <= 0 ) continue;
				$v = (int) ( $metrics[ "sessions-{$w}" ] ?? 0 );
				if ( $v <= 0 ) continue;
				foreach ( $widthBands as $bi => $b ) {
					if ( $width >= $b[0] && $width < $b[1] ) { $totals[ $bi ] += $v; break; }
				}
			}
			$slices = [];
			foreach ( $widthBands as $bi => $b ) {  // fixed order: smallest → largest viewport
				if ( $totals[ $bi ] > 0 ) $slices[] = [ 'l' => $b[2], 'v' => $totals[ $bi ], 'c' => $b[3] ];
			}
			$out[ $w ] = $slices;
		}
		return $out;
	};

	// Site-speed time series (each metric: {periods:[YYYYMMDD], mobile:[], desktop:[]}).
	// REAL-USER (load, %-target) = dense DAILY history from GA4 (bp_ga4_speed_history — our own
	// tracking, actual devices/networks). LAB (LCP, score) = the site audit's Lighthouse snapshots
	// (bp_site_audit_details) — a throttled yardstick, only captured ~quarterly, so sparse.
	$speedSeries = function () {
		$out = [];

		// --- Real-user: dense daily. Store shape [YYYYMMDD => ml/mt/dl/dt]. ---
		$rum = get_option( 'bp_ga4_speed_history' );
		if ( ! is_array( $rum ) ) $rum = [];
		ksort( $rum );
		$load = [ 'periods' => [], 'mobile' => [], 'desktop' => [] ];
		$targ = [ 'periods' => [], 'mobile' => [], 'desktop' => [] ];
		foreach ( $rum as $ymd => $r ) {
			$ymd = (string) $ymd;
			if ( strlen( $ymd ) !== 8 || ! is_array( $r ) ) continue;
			if ( isset( $r['ml'] ) || isset( $r['dl'] ) ) {
				$load['periods'][] = $ymd;
				$load['mobile'][]  = isset( $r['ml'] ) ? (float) $r['ml'] : 0;
				$load['desktop'][] = isset( $r['dl'] ) ? (float) $r['dl'] : 0;
			}
			if ( isset( $r['mt'] ) || isset( $r['dt'] ) ) {
				$targ['periods'][] = $ymd;
				$targ['mobile'][]  = isset( $r['mt'] ) ? (float) $r['mt'] : 0;
				$targ['desktop'][] = isset( $r['dt'] ) ? (float) $r['dt'] : 0;
			}
		}
		$out['load']   = $load;
		$out['target'] = $targ;

		// --- Lab: sparse Lighthouse snapshots from the audit. "2.5 s"/"150 ms"/92 → float. ---
		$hist = get_option( 'bp_site_audit_details' );
		if ( ! is_array( $hist ) ) $hist = [];
		$toNum = function ( $val, $asSeconds ) {
			if ( $val === null || $val === '' ) return null;
			$s = (string) $val;
			if ( ! preg_match( '/-?\d[\d,]*\.?\d*/', $s, $m ) ) return null;
			$n = (float) str_replace( ',', '', $m[0] );
			if ( $asSeconds && stripos( $s, 'ms' ) !== false ) $n = $n / 1000;
			return $n;
		};
		$lab = [
			'lcp'   => [ 'lighthouse-mobile-lcp',   'lighthouse-desktop-lcp',   true  ],
			'score' => [ 'lighthouse-mobile-score', 'lighthouse-desktop-score', false ],
		];
		$dates = array_keys( $hist );
		sort( $dates );
		foreach ( $lab as $key => $cfg ) {
			list( $mKey, $dKey, $asSeconds ) = $cfg;
			$periods = []; $mob = []; $desk = [];
			foreach ( $dates as $date ) {
				$e = $hist[ $date ];
				if ( ! is_array( $e ) ) continue;
				$mv = $toNum( $e[ $mKey ] ?? '', $asSeconds );
				$dv = $toNum( $e[ $dKey ] ?? '', $asSeconds );
				if ( $mv === null && $dv === null ) continue;
				$periods[] = str_replace( '-', '', (string) $date );
				$mob[]  = $mv === null ? 0 : round( $mv, 2 );
				$desk[] = $dv === null ? 0 : round( $dv, 2 );
			}
			$out[ $key ] = [ 'periods' => $periods, 'mobile' => $mob, 'desktop' => $desk ];
		}

		return $out;
	};

	// Visitor locations for the map — per-window (7/30/90/180/365d) geocoded city bubbles.
	// Cities lacking coords (not yet geocoded, or a geocoding miss) are summed into `uncoded` so
	// the map can note how many sessions aren't plotted. Mirrors the tech pies' snapshot shape.
	$locationsMap = function () {
		$cities = get_option( 'bp_ga4_locations_clean' );
		$coords = get_option( 'bp_ga4_city_latlng' );
		$states = get_option( 'bp_ga4_city_state_map' );
		$out    = [];
		if ( ! is_array( $cities ) ) return $out;
		if ( ! is_array( $coords ) ) $coords = [];
		if ( ! is_array( $states ) ) $states = [];
		foreach ( [ 7, 30, 90, 180, 365 ] as $w ) {
			$pts = []; $uncoded = 0;
			foreach ( $cities as $city => $metrics ) {
				if ( ! is_array( $metrics ) ) continue;
				$v = (int) ( $metrics[ "sessions-{$w}" ] ?? 0 );
				if ( $v <= 0 ) continue;
				$c = $coords[ $city ] ?? null;
				if ( is_array( $c ) && isset( $c['lat'], $c['lng'] ) && $c['lat'] !== null ) {
					$label = isset( $states[ $city ] ) && $states[ $city ] ? $city . ', ' . $states[ $city ] : $city;
					$pts[] = [ 'l' => $label, 'lat' => (float) $c['lat'], 'lng' => (float) $c['lng'], 'v' => $v ];
				} else {
					$uncoded += $v;
				}
			}
			usort( $pts, function ( $a, $b ) { return $b['v'] - $a['v']; } );
			if ( count( $pts ) > 60 ) $pts = array_slice( $pts, 0, 60 );
			$out[ $w ] = [ 'pts' => $pts, 'uncoded' => $uncoded ];
		}
		return $out;
	};

	// Animated-map time series — monthly per-city sessions with coords. Pivoted to frames the JS can
	// play through. Null until the nightly run has built bp_ga4_locations_history + geocoded cities.
	$locationsTimeline = function () {
		$hist   = get_option( 'bp_ga4_locations_history' );
		$coords = get_option( 'bp_ga4_city_latlng' );
		$states = get_option( 'bp_ga4_city_state_map' );
		if ( ! is_array( $hist ) || ! $hist ) return null;
		if ( ! is_array( $coords ) ) $coords = [];
		if ( ! is_array( $states ) ) $states = [];

		$monthsSet = [];
		foreach ( $hist as $series ) {
			if ( is_array( $series ) ) foreach ( $series as $ym => $v ) $monthsSet[ (string) $ym ] = true;
		}
		if ( ! $monthsSet ) return null;
		$months = array_keys( $monthsSet );
		sort( $months );  // ascending YYYYMM

		$cities = []; $maxV = 0;
		foreach ( $hist as $city => $series ) {
			if ( ! is_array( $series ) ) continue;
			$c = $coords[ $city ] ?? null;
			if ( ! is_array( $c ) || ! isset( $c['lat'], $c['lng'] ) || $c['lat'] === null ) continue;  // not (yet) geocoded
			$vals = [];
			foreach ( $months as $ym ) { $v = (int) ( $series[ $ym ] ?? 0 ); $vals[] = $v; if ( $v > $maxV ) $maxV = $v; }
			$label = isset( $states[ $city ] ) && $states[ $city ] ? $city . ', ' . $states[ $city ] : $city;
			$cities[] = [ 'l' => $label, 'lat' => (float) $c['lat'], 'lng' => (float) $c['lng'], 'v' => $vals ];
		}
		if ( ! $cities ) return null;

		$labels = array_map( function ( $ym ) { return date( 'M Y', strtotime( $ym . '01' ) ); }, $months );
		return [ 'frames' => $months, 'labels' => $labels, 'cities' => $cities, 'maxV' => $maxV ];
	};

	// Page-trend time series — per-page pageviews at three grains (daily/weekly/monthly), so the chart
	// follows the Daily/Weekly/Monthly toggle like the traffic graph. Pages sorted by total (top 10),
	// each grain giving {periods, series:{path:[values]}} aligned to that grain's period axis.
	$pagesTimeline = function () {
		$M = get_option( 'bp_ga4_pages_history' );
		if ( ! is_array( $M ) || ! $M ) return null;
		// Guard the shape change (was [path][ym], now [ym][path]) — if the old option is still cached,
		// wait for a fresh Stats run rather than mislabelling. New keys are YYYYMM period keys.
		if ( ! preg_match( '/^\d{6}$/', (string) array_key_first( $M ) ) ) return null;
		$W = get_option( 'bp_ga4_pages_history_weekly' ); if ( ! is_array( $W ) ) $W = [];
		$D = get_option( 'bp_ga4_pages_history_daily' );  if ( ! is_array( $D ) ) $D = [];

		$totals = [];
		foreach ( $M as $paths ) {
			if ( is_array( $paths ) ) foreach ( $paths as $p => $v ) $totals[ (string) $p ] = ( $totals[ (string) $p ] ?? 0 ) + (int) $v;
		}
		if ( ! $totals ) return null;
		arsort( $totals );
		$top  = array_slice( array_keys( $totals ), 0, 10 );
		$list = array_map( function ( $p ) { return [ 'key' => (string) $p, 'label' => bp_ga4_path_to_label( (string) $p ) ]; }, $top );

		$grain = function ( $hist, $limit ) use ( $top ) {
			if ( ! is_array( $hist ) || ! $hist ) return null;
			krsort( $hist );
			$keys = array_reverse( array_slice( array_keys( $hist ), 0, $limit ) ); // ascending period keys
			$series = [];
			foreach ( $top as $p ) {
				$arr = [];
				foreach ( $keys as $k ) $arr[] = (int) ( $hist[ $k ][ $p ] ?? 0 );
				$series[ (string) $p ] = $arr;
			}
			return [ 'periods' => array_map( 'strval', $keys ), 'series' => $series ];
		};

		return [
			'list'    => $list,
			'daily'   => $grain( $D, 520 ),
			'weekly'  => $grain( $W, 260 ),
			'monthly' => $grain( $M, 72 ),
		];
	};

	// Microsoft Clarity UX-health time series — our own daily accumulation (bp_clarity_history) rolled
	// up to daily/weekly/monthly, so the chart follows the grain toggle. Metrics are the frustration
	// signals GA4 can't give: rage/dead/error clicks, quick-backs, script errors, excessive scroll.
	$clarityTimeline = function () {
		$hist = get_option( 'bp_clarity_history' );
		if ( ! is_array( $hist ) || ! $hist ) return null;
		ksort( $hist );  // ascending YYYYMMDD

		$metrics = [
			[ 'key' => 'rage',        'label' => 'Rage clicks' ],
			[ 'key' => 'dead',        'label' => 'Dead clicks' ],
			[ 'key' => 'quickback',   'label' => 'Quick backs' ],
			[ 'key' => 'errClick',    'label' => 'Error clicks' ],
			[ 'key' => 'scriptErr',   'label' => 'Script errors' ],
			[ 'key' => 'excessScrl',  'label' => 'Excessive scroll' ],
			[ 'key' => 'scrollDepth', 'label' => 'Avg scroll depth' ],
			[ 'key' => 'activeTime',  'label' => 'Engagement time' ],
			[ 'key' => 'sessions',    'label' => 'Sessions' ],
			[ 'key' => 'bots',        'label' => 'Bot sessions' ],
		];
		$keysOf  = array_column( $metrics, 'key' );
		$avgKeys = [ 'scrollDepth' ];   // session-weighted average across the period; everything else sums

		$weekKey = function ( $ymd ) {
			$ts = strtotime( $ymd ); $dow = (int) date( 'N', $ts );
			return date( 'Ymd', strtotime( '-' . ( $dow - 1 ) . ' days', $ts ) );
		};

		// Roll the daily rows up into a grain. Counts sum; rate metrics (scroll depth) are averaged,
		// weighted by that day's sessions. $keyer maps YYYYMMDD → period key.
		$rollup = function ( $keyer, $limit ) use ( $hist, $keysOf, $avgKeys ) {
			$sum = []; $wsum = []; $wt = [];
			foreach ( $hist as $ymd => $row ) {
				if ( ! is_array( $row ) ) continue;
				$pk   = $keyer( (string) $ymd );
				$sess = (int) ( $row['sessions'] ?? 0 );
				foreach ( $keysOf as $mk ) {
					$v = (float) ( $row[ $mk ] ?? 0 );
					$sum[ $pk ][ $mk ]  = ( $sum[ $pk ][ $mk ]  ?? 0 ) + $v;
					$wsum[ $pk ][ $mk ] = ( $wsum[ $pk ][ $mk ] ?? 0 ) + $v * $sess;
				}
				$wt[ $pk ] = ( $wt[ $pk ] ?? 0 ) + $sess;
			}
			if ( ! $sum ) return null;
			ksort( $sum );
			$periods = array_slice( array_keys( $sum ), -$limit );
			$series  = [];
			foreach ( $keysOf as $mk ) {
				$series[ $mk ] = [];
				foreach ( $periods as $pk ) {
					if ( in_array( $mk, $avgKeys, true ) ) {
						$tot = $wt[ $pk ] ?? 0;
						$series[ $mk ][] = $tot > 0 ? (int) round( ( $wsum[ $pk ][ $mk ] ?? 0 ) / $tot ) : 0;
					} else {
						$series[ $mk ][] = (int) round( $sum[ $pk ][ $mk ] ?? 0 );
					}
				}
			}
			return [ 'periods' => array_map( 'strval', $periods ), 'series' => $series ];
		};

		return [
			'metrics' => $metrics,
			'daily'   => $rollup( function ( $ymd ) { return $ymd; }, 520 ),
			'weekly'  => $rollup( $weekKey, 260 ),
			'monthly' => $rollup( function ( $ymd ) { return substr( $ymd, 0, 6 ); }, 72 ),
		];
	};

	// Content Visibility — scroll-depth reach over time. Each threshold's value is the CUMULATIVE
	// "% of pageviews that reached at least this depth" = engagedSessions at that threshold ÷ init
	// (pageview loads). Monotonic (20% ≥ 30% ≥ … ≥ 100%), so the JS can derive per-band slices too.
	$scrollTimeline = function () {
		$M = get_option( 'bp_ga4_scroll_history' );        if ( ! is_array( $M ) ) $M = [];
		$W = get_option( 'bp_ga4_scroll_history_weekly' );  if ( ! is_array( $W ) ) $W = [];
		$D = get_option( 'bp_ga4_scroll_history_daily' );   if ( ! is_array( $D ) ) $D = [];
		if ( ! $M && ! $W && ! $D ) return null;

		$thresholds = [ '20', '30', '40', '50', '60', '70', '80', '90', '100' ];

		$grain = function ( $hist, $limit ) use ( $thresholds ) {
			if ( ! is_array( $hist ) || ! $hist ) return null;
			krsort( $hist );
			$keys   = array_reverse( array_slice( array_keys( $hist ), 0, $limit ) );  // ascending period keys
			$series = [];
			foreach ( $thresholds as $t ) $series[ $t ] = [];
			foreach ( $keys as $k ) {
				$init = (float) ( $hist[ $k ]['init'] ?? 0 );
				foreach ( $thresholds as $t ) {
					$c = (float) ( $hist[ $k ][ $t ] ?? 0 );
					$series[ $t ][] = $init > 0 ? round( min( 100, $c / $init * 100 ), 1 ) : 0;
				}
			}
			return [ 'periods' => array_map( 'strval', $keys ), 'series' => $series ];
		};

		return [
			'thresholds' => $thresholds,
			'daily'      => $grain( $D, 180 ),
			'weekly'     => $grain( $W, 104 ),
			'monthly'    => $grain( $M, 36 ),
		];
	};

	// Tracked Components & Events over time — the track-*/conversion-* achievement events. Component
	// interactions (Financing, Testimonials…) are reported as % of engaged sessions (matching the
	// dashboard widget); conversions (Emails, Phone Calls…) as raw counts. Each item is one pill.
	$eventsTimeline = function () {
		$M = get_option( 'bp_ga4_events_history' );         if ( ! is_array( $M ) ) $M = [];
		$W = get_option( 'bp_ga4_events_history_weekly' );  if ( ! is_array( $W ) ) $W = [];
		$D = get_option( 'bp_ga4_events_history_daily' );   if ( ! is_array( $D ) ) $D = [];
		if ( ! $M && ! $W && ! $D ) return null;

		// Discover every tracked key + its lifetime total (for ordering). '_engaged' is the denominator.
		$totals = [];
		foreach ( [ $M, $W, $D ] as $hist ) {
			foreach ( $hist as $row ) {
				if ( ! is_array( $row ) ) continue;
				foreach ( $row as $k => $v ) {
					if ( $k === '_engaged' ) continue;
					$totals[ (string) $k ] = ( $totals[ (string) $k ] ?? 0 ) + (int) $v;
				}
			}
		}
		if ( ! $totals ) return null;

		$label = function ( $raw ) {
			$name = preg_replace( '/^(track|conversion)-/', '', (string) $raw );
			return ucwords( trim( str_replace( '-', ' ', $name ) ) );
		};

		// Components (track-*) first, then conversions (conversion-*); each group sorted by total desc.
		$comp = []; $conv = [];
		foreach ( $totals as $k => $t ) {
			if ( strncmp( $k, 'track-', 6 ) === 0 )            $comp[ $k ] = $t;
			elseif ( strncmp( $k, 'conversion-', 11 ) === 0 )  $conv[ $k ] = $t;
		}
		arsort( $comp ); arsort( $conv );
		$items = []; $typeOf = [];
		foreach ( $comp as $k => $t ) { $items[] = [ 'key' => (string) $k, 'label' => $label( $k ), 'type' => 'pct'   ]; $typeOf[ $k ] = 'pct';   }
		foreach ( $conv as $k => $t ) { $items[] = [ 'key' => (string) $k, 'label' => $label( $k ), 'type' => 'count' ]; $typeOf[ $k ] = 'count'; }
		if ( ! $items ) return null;
		$keys = array_column( $items, 'key' );

		$grain = function ( $hist, $limit ) use ( $keys, $typeOf ) {
			if ( ! is_array( $hist ) || ! $hist ) return null;
			krsort( $hist );
			$pk     = array_reverse( array_slice( array_keys( $hist ), 0, $limit ) ); // ascending period keys
			$series = [];
			foreach ( $keys as $k ) {
				$arr = [];
				foreach ( $pk as $p ) {
					$row = is_array( $hist[ $p ] ?? null ) ? $hist[ $p ] : [];
					$c   = (float) ( $row[ $k ] ?? 0 );
					if ( $typeOf[ $k ] === 'pct' ) {
						$eng   = (float) ( $row['_engaged'] ?? 0 );
						$arr[] = $eng > 0 ? round( min( 100, $c / $eng * 100 ), 1 ) : 0;
					} else {
						$arr[] = (int) $c;
					}
				}
				$series[ $k ] = $arr;
			}
			return [ 'periods' => array_map( 'strval', $pk ), 'series' => $series ];
		};

		return [
			'items'   => $items,
			'daily'   => $grain( $D, 180 ),
			'weekly'  => $grain( $W, 104 ),
			'monthly' => $grain( $M, 36 ),
		];
	};

	// Lighthouse (lab) over time — from bp_cwv_lab_history, PSI pulled every ~3 days. Each metric plots
	// Mobile + Desktop. Nine metrics: four category scores (Performance, Accessibility, Best Practices,
	// SEO) + five performance metrics (FCP, Speed Index, LCP, TBT, CLS). Sparse daily snapshots are
	// mean-bucketed into each grain (a point estimate averaged over the period is fine for lab).
	$cwvTimeline = function () {
		$metrics = [
			[ 'key' => 'perf', 'label' => 'Performance' ],
			[ 'key' => 'fcp',  'label' => 'First Contentful Paint' ],
			[ 'key' => 'tbt',  'label' => 'Total Blocking Time' ],
			[ 'key' => 'si',   'label' => 'Speed Index' ],
			[ 'key' => 'lcp',  'label' => 'Largest Contentful Paint' ],
			[ 'key' => 'cls',  'label' => 'Cumulative Layout Shift' ],
			[ 'key' => 'acc',  'label' => 'Accessibility' ],
			[ 'key' => 'best', 'label' => 'Best Practices' ],
			[ 'key' => 'seo',  'label' => 'SEO' ],
		];
		$mkeys   = array_column( $metrics, 'key' );
		$labHist = get_option( 'bp_cwv_lab_history' );
		if ( ! is_array( $labHist ) || ! $labHist ) return null;
		$scoreKeys = [ 'perf' => 1, 'acc' => 1, 'best' => 1, 'seo' => 1, 'tbt' => 1 ];   // rounded to integers

		$weekKey = function ( $ymd ) { $ts = strtotime( $ymd ); $dow = (int) date( 'N', $ts ); return date( 'Ymd', strtotime( '-' . ( $dow - 1 ) . ' days', $ts ) ); };
		$grain   = function ( callable $keyer, $limit ) use ( $labHist, $mkeys, $scoreKeys ) {
			$acc = [];   // [period][metric][dev] = ['sum'=>, 'cnt'=>]
			foreach ( $labHist as $ymd => $rec ) {
				if ( strlen( (string) $ymd ) !== 8 || ! is_array( $rec ) ) continue;
				$pk = $keyer( (string) $ymd );
				foreach ( $mkeys as $mk ) {
					foreach ( [ 'm', 'd' ] as $dev ) {
						if ( ! isset( $rec[ $mk ][ $dev ] ) ) continue;
						$acc[ $pk ][ $mk ][ $dev ]['sum'] = ( $acc[ $pk ][ $mk ][ $dev ]['sum'] ?? 0 ) + (float) $rec[ $mk ][ $dev ];
						$acc[ $pk ][ $mk ][ $dev ]['cnt'] = ( $acc[ $pk ][ $mk ][ $dev ]['cnt'] ?? 0 ) + 1;
					}
				}
			}
			if ( ! $acc ) return null;
			ksort( $acc );
			$periods = array_slice( array_keys( $acc ), -$limit );
			$series  = [];
			foreach ( $mkeys as $mk ) $series[ $mk ] = [ 'm' => [], 'd' => [] ];
			foreach ( $periods as $pk ) {
				foreach ( $mkeys as $mk ) {
					foreach ( [ 'm', 'd' ] as $dev ) {
						if ( isset( $acc[ $pk ][ $mk ][ $dev ] ) && $acc[ $pk ][ $mk ][ $dev ]['cnt'] > 0 ) {
							$v = $acc[ $pk ][ $mk ][ $dev ]['sum'] / $acc[ $pk ][ $mk ][ $dev ]['cnt'];
							$series[ $mk ][ $dev ][] = isset( $scoreKeys[ $mk ] ) ? round( $v ) : round( $v, $mk === 'cls' ? 3 : 2 );
						} else {
							$series[ $mk ][ $dev ][] = 0;
						}
					}
				}
			}
			return [ 'periods' => array_map( 'strval', $periods ), 'series' => $series ];
		};

		$daily   = $grain( function ( $p ) { return $p; }, 180 );
		$weekly  = $grain( $weekKey, 104 );
		$monthly = $grain( function ( $p ) { return substr( $p, 0, 6 ); }, 36 );
		if ( ! $daily && ! $weekly && ! $monthly ) return null;
		return [ 'metrics' => $metrics, 'daily' => $daily, 'weekly' => $weekly, 'monthly' => $monthly ];
	};

	// Client's home town — center of the regional (100-mi) zoom map. From customer_info (its own
	// business address coords). Null if unset, in which case the zoom panel is omitted.
	$ci        = customer_info();
	$anHomeLbl = trim( (string) ( $ci['city'] ?? '' ) . ( ! empty( $ci['state-abbr'] ) ? ', ' . $ci['state-abbr'] : '' ), ', ' );
	$anHomeCo  = bp_an_home_coords( $ci );  // customer_info lat/long, else a cached geocode of city/state
	$anHome    = $anHomeCo
	           ? [ 'lat' => $anHomeCo[0], 'lng' => $anHomeCo[1], 'label' => ( $anHomeLbl !== '' ? $anHomeLbl : 'home' ), 'radius' => 50 ]
	           : null;

	$hasWeekly = is_array( $historyW ) && $historyW;
	$hasDaily  = is_array( $historyD ) && $historyD;
	$payload   = [
		'daily'   => $buildGrain( $hasDaily  ? $historyD : [], 520 ),
		'weekly'  => $buildGrain( $hasWeekly ? $historyW : [], 260 ),
		'monthly' => $buildGrain( $historyM, 72 ),
		'speed'   => $speedSeries(),
		'tech'    => [
			'browsers'   => $techPie( 'bp_ga4_browsers_clean', 6, [
				'Chrome' => 'red', 'Safari' => 'blue', 'Safari (in-app)' => 'teal', 'Edge' => 'green', 'Firefox' => 'orange',
			] ),
			'width'      => $widthPie(),
			'deviceType' => $deviceTypePie(),
		],
		'locations'         => $locationsMap(),
		'locationsTimeline' => $locationsTimeline(),
		'pages'             => $pagesTimeline(),
		'clarity'           => $clarityTimeline(),
		'scroll'            => $scrollTimeline(),
		'events'            => $eventsTimeline(),
		'cwv'               => $cwvTimeline(),
		'auditMetrics'      => bp_an_audit_metrics_payload(),
		'keywords'          => bp_an_keywords_payload(),
		'home'              => $anHome,
	];
	$anPages   = ! empty( $payload['pages'] );
	$anClarity = ! empty( $payload['clarity'] );
	$anScroll  = ! empty( $payload['scroll'] );
	$anEvents  = ! empty( $payload['events'] );
	$anCwv     = ! empty( $payload['cwv'] );
	$anKw      = ! empty( $payload['keywords'] );

	echo '<script type="application/json" id="bp-an-payload">' . wp_json_encode( $payload ) . '</script>';

	// Controls (shared by both charts): granularity · range presets · custom date picker.
	// Default view = Daily · 30d. Fall back to the finest grain we actually have data for.
	$defGrain = $hasDaily ? 'daily' : ( $hasWeekly ? 'weekly' : 'monthly' );
	echo '<div class="bp-an-controls">';
	echo   '<div class="bp-an-ctrl-grp"><span class="bp-an-ctrl-lbl">View</span>'
	     .   '<a href="#" class="bp-an-gbtn' . ( $hasDaily  ? '' : ' disabled' ) . ( $defGrain === 'daily'   ? ' active' : '' ) . '" data-grain="daily">Daily</a>'
	     .   '<a href="#" class="bp-an-gbtn' . ( $hasWeekly ? '' : ' disabled' ) . ( $defGrain === 'weekly'  ? ' active' : '' ) . '" data-grain="weekly">Weekly</a>'
	     .   '<a href="#" class="bp-an-gbtn' . ( $defGrain === 'monthly' ? ' active' : '' ) . '" data-grain="monthly">Monthly</a>'
	     . '</div>';
	echo   '<div class="bp-an-ctrl-grp"><span class="bp-an-ctrl-lbl">Range</span>'
	     .   '<a href="#" class="bp-an-rbtn active" data-days="30">30d</a>'
	     .   '<a href="#" class="bp-an-rbtn" data-days="60">60d</a>'
	     .   '<a href="#" class="bp-an-rbtn" data-days="90">90d</a>'
	     .   '<a href="#" class="bp-an-rbtn" data-days="365">1y</a>'
	     .   '<a href="#" class="bp-an-rbtn" data-days="730">2y</a>'
	     .   '<a href="#" class="bp-an-rbtn" data-days="1095">3y</a>'
	     .   '<a href="#" class="bp-an-rbtn" data-days="all">All</a>'
	     . '</div>';
	echo   '<div class="bp-an-ctrl-grp"><input type="date" class="bp-an-date" id="bp-an-start" aria-label="Start date">'
	     .   '<span class="bp-an-dash">–</span>'
	     .   '<input type="date" class="bp-an-date" id="bp-an-end" aria-label="End date"></div>';
	echo '</div>';

	// Chart 1 — traffic by channel.
	echo '<div class="bp-an-card">';
	echo   '<h2 class="bp-an-h2">Traffic by channel</h2>';
	echo   '<div class="bp-an-cardnote">Your work (Organic Search + GBP) vs paid &amp; direct. Click a channel in the legend to isolate it. Click a y-axis number to cap the scale (again to reset) — handy when a spike flattens everything else. Click the plot itself to hide the hover tooltip (crosshair stays) for a clean screenshot — click again to bring it back.</div>';
	echo   '<div class="bp-an-chartrow"><div class="bp-analytics-chart"></div><div class="bp-analytics-pie" data-pie="channel"></div></div>';
	echo '</div>';

	// Chart 2 — visitor behavior.
	echo '<div class="bp-an-card">';
	echo   '<div class="bp-an-toolbar"><h2 class="bp-an-h2">Visitor behavior</h2><div class="bp-an-metric-btns">'
	     .   '<a href="#" class="bp-an-mbtn active" data-metric="sessions_users">Sessions · Users</a>'
	     .   '<a href="#" class="bp-an-mbtn" data-metric="new_returning">New · Returning</a>'
	     .   '<a href="#" class="bp-an-mbtn" data-metric="engaged">Engaged · Non-engaged</a>'
	     .   '<a href="#" class="bp-an-mbtn" data-metric="pageviews">Pageviews</a>'
	     .   '<a href="#" class="bp-an-mbtn" data-metric="duration">Duration</a>'
	     . '</div></div>';
	echo   '<div class="bp-an-cardnote">Click a y-axis number to cap the scale (again to reset) — handy when a spike flattens everything else. Click the plot itself to hide the hover tooltip (crosshair stays) for a clean screenshot — click again to bring it back.</div>';
	echo   '<div class="bp-an-chartrow"><div class="bp-analytics-behavior"></div><div class="bp-analytics-pie" data-pie="behavior"></div></div>';
	echo '</div>';

	// Page trends — monthly pageviews for the top pages; each page is a single-select pill.
	// The card always renders; the pills + line fill in once the nightly run (or a Stats refresh)
	// has built bp_ga4_pages_history — otherwise the chart area shows a "building…" message.
	echo '<div class="bp-an-card">';
	echo   '<div class="bp-an-toolbar"><h2 class="bp-an-h2">Page trends</h2><div class="bp-an-metric-btns">';
	if ( $anPages ) {
		foreach ( $payload['pages']['list'] as $i => $pg ) {
			echo '<a href="#" class="bp-an-pbtn' . ( $i === 0 ? ' active' : '' ) . '" data-pkey="' . esc_attr( $pg['key'] ) . '">' . esc_html( $pg['label'] ) . '</a>';
		}
	}
	echo   '</div></div>';
	echo   '<div class="bp-an-cardnote">Monthly pageviews for your top pages — pick one. Follows the range control above. Click a y-axis number to cap the scale.</div>';
	echo   '<div class="bp-an-chartrow"><div class="bp-analytics-pagetrend"></div><div class="bp-analytics-pie" data-pie="pages"></div></div>';
	echo '</div>';

	// Site speed — lab (Lighthouse) vs real-user (our tracking) over time, Mobile vs Desktop.
	echo '<div class="bp-an-card">';
	echo   '<div class="bp-an-toolbar"><h2 class="bp-an-h2">Site speed</h2><div class="bp-an-metric-btns">'
	     .   '<a href="#" class="bp-an-sbtn active" data-smetric="load">Real-user load</a>'
	     .   '<a href="#" class="bp-an-sbtn" data-smetric="target">% meeting target</a>'
	     .   '<a href="#" class="bp-an-sbtn" data-smetric="lcp">LCP (lab)</a>'
	     .   '<a href="#" class="bp-an-sbtn" data-smetric="score">Score (lab)</a>'
	     . '</div></div>';
	echo   '<div class="bp-an-cardnote"><b>Real-user</b> (load &amp; % on-target) = actual visitors\' devices &amp; networks from our own tracking — a daily trend. <b>Lab</b> (LCP &amp; score) = throttled Lighthouse snapshots from the site audit (a fixed regression yardstick), captured only ~quarterly, so those two are sparse. Dashed line = the "good" threshold. Click a y-axis number to cap the scale.</div>';
	echo   '<div class="bp-analytics-speed"></div>';
	echo '</div>';

	// Lighthouse — lab scores/metrics over time (PSI pulled every ~3 days). Pills pick the metric; each
	// shows Mobile + Desktop lines + the "good" threshold. Four category scores + five perf metrics.
	if ( $anCwv ) {
		echo '<div class="bp-an-card">';
		echo   '<div class="bp-an-toolbar"><h2 class="bp-an-h2">Lighthouse</h2><div class="bp-an-metric-btns">';
		foreach ( $payload['cwv']['metrics'] as $i => $mt ) {
			echo '<a href="#" class="bp-an-vbtn' . ( $i === 0 ? ' active' : '' ) . '" data-vmetric="' . esc_attr( $mt['key'] ) . '">' . esc_html( $mt['label'] ) . '</a>';
		}
		echo   '</div></div>';
		echo   '<div class="bp-an-cardnote">Lab scores from Google Lighthouse (PageSpeed Insights), pulled every ~3 days — <b>Performance / Accessibility / Best&nbsp;Practices / SEO</b> (0–100, higher is better) plus the performance metrics <b>FCP / Speed Index / LCP</b> (seconds), <b>TBT</b> (ms) and <b>CLS</b>. Mobile &amp; Desktop; dashed line = the "good" threshold. Follows the range &amp; grain controls above.</div>';
		echo   '<div class="bp-analytics-cwv"></div>';
		echo '</div>';
	}

	// UX health (Microsoft Clarity) — frustration + engagement signals over time; single-select pills.
	if ( $anClarity ) {
		echo '<div class="bp-an-card">';
		echo   '<div class="bp-an-toolbar"><h2 class="bp-an-h2">UX health</h2><div class="bp-an-metric-btns">';
		foreach ( $payload['clarity']['metrics'] as $i => $mt ) {
			echo '<a href="#" class="bp-an-cbtn' . ( $i === 0 ? ' active' : '' ) . '" data-cmetric="' . esc_attr( $mt['key'] ) . '">' . esc_html( $mt['label'] ) . '</a>';
		}
		echo   '</div></div>';
		echo   '<div class="bp-an-cardnote">Behavior signals from Microsoft Clarity that GA4 can\'t give — rage/dead/error clicks, quick-backs, script errors, excessive scroll, plus avg scroll depth &amp; engagement time. Follows the range &amp; grain controls above.</div>';
		echo   '<div class="bp-analytics-clarity"></div>';
		echo '</div>';
	}

	// Content Visibility — scroll-depth distribution over time (stacked to 100%; deepest = dark, bottom).
	if ( $anScroll ) {
		echo '<div class="bp-an-card">';
		echo   '<h2 class="bp-an-h2">Content Visibility</h2>';
		echo   '<div class="bp-an-cardnote">The distribution of how far visitors scroll, over time — stacked to 100%. The dark band at the bottom reached the whole page; the light band on top barely scrolled. Hover for the cumulative reach at each depth. Follows the range &amp; grain controls above.</div>';
		echo   '<div class="bp-analytics-scroll"></div>';
		echo '</div>';
	}

	// Tracked Components & Events — track-*/conversion-* achievement events over time; single-select
	// pills. Components (Financing, Testimonials…) plot as % of engaged sessions; conversions
	// (Emails, Phone Calls…) plot as counts. The y-axis + tooltip adapt per item's unit.
	if ( $anEvents ) {
		echo '<div class="bp-an-card">';
		echo   '<div class="bp-an-toolbar"><h2 class="bp-an-h2">Tracked events</h2><div class="bp-an-metric-btns">';
		foreach ( $payload['events']['items'] as $i => $it ) {
			echo '<a href="#" class="bp-an-ebtn' . ( $i === 0 ? ' active' : '' ) . '" data-ekey="' . esc_attr( $it['key'] ) . '" data-etype="' . esc_attr( $it['type'] ) . '">' . esc_html( $it['label'] ) . '</a>';
		}
		echo   '</div></div>';
		echo   '<div class="bp-an-cardnote"><b>Components</b> (Financing, Testimonials…) show the <b>% of engaged sessions</b> that interacted with them; <b>conversions</b> (Emails, Phone Calls…) show the <b>count</b>. Pick one. Follows the range &amp; grain controls above. Click a y-axis number to cap the scale.</div>';
		echo   '<div class="bp-analytics-events"></div>';
		echo '</div>';
	}

	// Tech row — browser / screen-width / device breakdowns (nearest snapshot to the range).
	echo '<div class="bp-an-card">';
	echo   '<h2 class="bp-an-h2">Audience tech</h2>';
	echo   '<div class="bp-an-cardnote">Browser, screen width (all devices, height ignored) &amp; device type for the closest available window to your selected range.</div>';
	echo   '<div class="bp-an-techrow">';
	foreach ( [ 'browsers' => 'Browsers', 'width' => 'Viewports', 'deviceType' => 'Device type' ] as $key => $title ) {
		echo '<div class="bp-an-techcol"><div class="bp-an-techtitle">' . esc_html( $title ) . '</div>'
		   . '<div class="bp-analytics-pie" data-pie="tech" data-tech="' . esc_attr( $key ) . '"></div></div>';
	}
	echo   '</div>';
	echo '</div>';

	// Visitor map — geocoded city bubbles over a US basemap, following the same range control as the
	// rest of the page (monthly time series summed over the window). Left: national. Right (when the
	// client's home town is known): a 100-mi service-area zoom.
	echo '<div class="bp-an-card">';
	echo   '<h2 class="bp-an-h2">Where visitors are</h2>';
	echo   '<div class="bp-an-cardnote">Engaged sessions by city for the selected range (30d / 60d / … / All, up top). Bubble size = sessions; hover for the count.'
	     . ( $anHome ? ' The right map auto-zooms to your local service area around ' . esc_html( $anHome['label'] ) . ' (fit to where visitors actually are).' : '' )
	     . '</div>';
	echo   '<div class="bp-an-maprow' . ( $anHome ? '' : ' solo' ) . '">';
	echo     '<div class="bp-an-mapcol"><div class="bp-an-maptitle">United States</div><div class="bp-analytics-locations"></div></div>';
	if ( $anHome ) {
		echo   '<div class="bp-an-mapcol"><div class="bp-an-maptitle">Around ' . esc_html( $anHome['label'] ) . '</div><div class="bp-analytics-locations-zoom"></div></div>';
	}
	echo   '</div>';
	echo '</div>';

	// Audit-derived metric cards — Search Console, Backlinks, Content, Reviews. Single-select pills (one
	// metric = one line); each is fed by a 3-day collector. Any card whose history is empty is omitted.
	foreach ( [
		[ 'searchConsole', 'Search Console', 'Google Search <b>clicks, impressions, CTR</b> &amp; <b>average position</b> (US), per day — from the Search Analytics API, refreshed every ~3 days. Follows the range &amp; grain controls above.' ],
		[ 'backlinks',     'Backlinks',      'Total <b>backlinks</b> and distinct <b>linking domains</b> from Search Console. Google refreshes link data slowly, so the line steps rather than wiggles.' ],
		[ 'content',       'Content',        'Published <b>Blog posts, Jobsites, Galleries &amp; Testimonials</b> over time.' ],
		[ 'reviews',       'Reviews',        'Google <b>review count</b> &amp; average <b>star rating</b> over time.' ],
		[ 'gbpPerf',       'Google Business Profile', 'GBP <b>impressions, call clicks, website clicks</b> &amp; <b>direction requests</b> per day, pulled via the GBP hub (refreshed every ~3 days). Google reports these with a ~3-day lag.' ],
	] as $ac ) {
		if ( empty( $payload['auditMetrics'][ $ac[0] ] ) ) continue;
		$card = $payload['auditMetrics'][ $ac[0] ];
		echo '<div class="bp-an-card">';
		echo   '<div class="bp-an-toolbar"><h2 class="bp-an-h2">' . esc_html( $ac[1] ) . '</h2><div class="bp-an-metric-btns">';
		foreach ( $card['metrics'] as $i => $mt ) {
			echo '<a href="#" class="bp-an-abtn' . ( $i === 0 ? ' active' : '' ) . '" data-ametric="' . esc_attr( $mt['key'] ) . '">' . esc_html( $mt['label'] ) . '</a>';
		}
		echo   '</div></div>';
		echo   '<div class="bp-an-cardnote">' . $ac[2] . '</div>';
		echo   '<div class="bp-analytics-audit" data-audit="' . esc_attr( $ac[0] ) . '"></div>';
		echo '</div>';
	}

	// Keyword Rankings — stacked rank-band distribution over time (DataForSEO). Count / Share toggle.
	if ( $anKw ) {
		echo '<div class="bp-an-card">';
		echo   '<div class="bp-an-toolbar"><h2 class="bp-an-h2">Keyword Rankings</h2><div class="bp-an-metric-btns">'
		     .   '<a href="#" class="bp-an-kmbtn active" data-kmode="count">Count</a>'
		     .   '<a href="#" class="bp-an-kmbtn" data-kmode="share">Share</a>'
		     . '</div></div>';
		echo   '<div class="bp-an-cardnote">How many tracked keywords sit in each Google rank band — <b>1–3</b> (bottom, darkest) through <b>21+</b> (top). <b>Count</b> stacks the totals (band height = # of keywords); <b>Share</b> normalizes to 100% to show the mix. As rankings improve you\'ll watch the dark 1–3 band grow. Follows the range &amp; grain controls above.</div>';
		echo   '<div class="bp-analytics-keywords"></div>';
		echo '</div>';
	}

	echo '</div>';
}

// Replace WordPress copyright message at bottom of admin page
add_action('in_admin_footer', 'battleplan_admin_footer_text');
function battleplan_admin_footer_text() {
	wp_cache_delete('customer_info', 'options');

	$customer_info = customer_info();

	$printFooter  = '<section><div class="flex" style="grid-template-columns:80px 300px 1fr; gap:20px">';
	$printFooter .= '<div style="grid-row:span 2; align-self:center;">';
	$printFooter .= '<img src="' . esc_url('https://bp-webdev.com/wp-content/uploads/site-icon-80x80.webp') . '" />';
	$printFooter .= '</div>';

	$printFooter .= '<div style="grid-row:span 2; align-self:center;">';
	$printFooter .= 'Powered by <a href="' . esc_url('https://bp-webdev.com') . '" target="_blank" rel="noopener">Battle Plan Web Design</a><br>';
	$printFooter .= 'Launched ' . esc_html( date('F Y', strtotime(get_option('bp_launch_date'))) ) . '<br>';
	$printFooter .= 'Framework ' . esc_html(_BP_VERSION) . '<br>';
	$printFooter .= 'WP ' . esc_html( get_bloginfo('version') ) . '<br>';
	$printFooter .= 'Local Time: ' . esc_html( wp_date('g:i a', null, new DateTimeZone( wp_timezone_string() )) ) . '<br>';
	if ( defined('_USER_LOGIN') && _USER_LOGIN === 'battleplanweb' ) {
		$printFooter .= '<button id="bp-time-toggle" title="Click to pause/resume timer" style="background:none;border:none;padding:0;cursor:pointer;font-size:inherit;vertical-align:middle;">⏱</button> <span id="bp-time-display" style="font-weight:600;color:#2563eb;">--</span> this session<br>';
	}
	$printFooter .= '</div>';

	$printFooter .= '<div style="justify-self:end; margin-right:50px;">';

	$email = $customer_info['email'] ?? '';
	if ($email) {
		$printFooter .= '<a class="button" href="mailto:' . esc_attr($email) . '">Contact Email</a>';
	}

	$owner_email = $customer_info['owner-email'] ?? '';
	if ($owner_email) {
		$printFooter .= '<a class="button" href="mailto:' . esc_attr($owner_email) . '">Owner Email</a>';
	}

	$socials = ['facebook','twitter','instagram','pinterest','yelp','tiktok','youtube'];
	foreach ($socials as $key) {
		if (!empty($customer_info[$key])) {
			$printFooter .= '<a class="button" href="' . esc_url($customer_info[$key]) . '" target="_blank" rel="noopener">' . esc_html(ucfirst($key)) . '</a>';
		}
	}

	if (!empty($customer_info['google-tags']['prop-id'])) {
		$prop_id = (int)$customer_info['google-tags']['prop-id'];
		$printFooter .= '<a class="button" href="' . esc_url('https://analytics.google.com/analytics/web/#/p'.$prop_id) . '" target="_blank" rel="noopener">Analytics</a>';
	}

	if (!empty($customer_info['serpfox'])) {
		$printFooter .= '<a class="button" href="' . esc_url('//app.serpfox.com/shared/'.$customer_info['serpfox']) . '" target="_blank" rel="noopener">Keywords</a>';
	}

	$printFooter .= '<a class="button" href="' . esc_url( wp_nonce_url( admin_url('admin-post.php?action=bp_send_checkin'), 'bp_send_checkin' ) ) . '" title="Generate the customer check-in now and email it to yourself (bypasses the send throttle)">Send Check-in</a>';

	$printFooter .= '<button type="button" id="bp-clear-cache-btn" class="button" data-nonce="' . esc_attr( wp_create_nonce('bp_clear_wpe_cache') ) . '">Clear Cache</button>';

	$printFooter .= '</div><div style="justify-self:end; margin-bottom:15px;">';

	$placeIDs   = $customer_info['pid'] ?? null;
	$googleInfo = get_option('bp_gbp_update');

	if ($placeIDs) {
		if (!is_array($placeIDs)) $placeIDs = [$placeIDs];

		foreach ($placeIDs as $placeID) {
			$placeID = esc_attr($placeID);
			$info = $googleInfo[$placeID] ?? [];

			$printFooter .= '<div style="float:left; margin-right:50px;">';

			if (strlen($placeID) > 10 && !empty($info['city'])) {
				$printFooter .= '<a class="button" style="margin:0 0 10px -5px" href="' .
					esc_url('https://search.google.com/local/writereview?placeid='.$placeID) .
					'" target="_blank" rel="noopener">GBP: ' .
					esc_html($info['city'] . ', ' . ($info['state-abbr'] ?? '')) .
					'</a><br>';
			}

			$printFooter .= esc_html(($customer_info['area-before'] ?? '') . ($customer_info['area'] ?? '') . ($customer_info['area-after'] ?? '') . ($customer_info['phone'] ?? '')) . '<br>';
			$printFooter .= esc_html($customer_info['street'] ?? '') . '<br>';
			$printFooter .= esc_html(($customer_info['city'] ?? '') . ', ' . ($customer_info['state-abbr'] ?? '') . ' ' . ($customer_info['zip'] ?? '')) . '<br>';

			if (!empty($customer_info['lat']) && !empty($customer_info['long'])) {
				$printFooter .= esc_html($customer_info['lat'] . ', ' . $customer_info['long']) . '<br>';
			}

			$printFooter .= '</div>';
		}
	}

	$printFooter .= "<div style='margin-top: 140px'>";
	if ( ! is_array($customer_info) ) {
		$customer_info = [];
		$printFooter .= "No customer info detected.";
	} else {
		$printFooter .= showMe($customer_info, false, false);
	}
	$printFooter .= "</div>";

	$printFooter .= '</div></div></section>';

	echo do_shortcode($printFooter);
}

// Change Howdy text
add_filter( 'admin_bar_menu', 'battleplan_replace_howdy', 9992 );
function battleplan_replace_howdy( $wp_admin_bar ) {
	$my_account = $wp_admin_bar->get_node('my-account');
	$newtitle = str_replace('Howdy,', '', $my_account->title);
	$wp_admin_bar->add_node( array(
		'id'    => 'my-account',
		'title' => $newtitle,
	));
}

// Re-build <img> tag in WordPress editor
add_filter( 'image_send_to_editor', 'battleplan_remove_junk_from_image', 10, 8 );
function battleplan_remove_junk_from_image( $html, $id, $caption, $title, $align, $url, $size, $alt ) {

	$size_full      = wp_get_attachment_image_src( $id, 'full' );
	$size_requested = wp_get_attachment_image_src( $id, $size );

	if ( ! $size_requested ) {
		return $html;
	}

	$size_slug = ( $size === 'full' ) ? 'orig' : $size;

	$data_orig = '';
	if ( $size_slug !== 'orig' && $size_full ) {
		$data_orig = ' data-orig="' . esc_attr( $size_full[1] . 'x' . $size_full[2] ) . '"';
	}

	$src = str_replace( get_site_url(), '', $size_requested[0] );
	$src = esc_url( $src );

	$alt = ( $alt === get_the_title( $id ) ) ? '' : $alt;

	$width  = (int) $size_requested[1];
	$height = (int) $size_requested[2];

	$class = sprintf(
		'align%s size-%s wp-image-%d',
		sanitize_html_class( $align ),
		sanitize_html_class( $size_slug ),
		(int) $id
	);

	$style = sprintf(
		'aspect-ratio:%d/%d',
		$width,
		$height
	);

	return sprintf(
		'<img src="%s"%s width="%d" height="%d" style="%s" class="%s" alt="%s">',
		$src,
		$data_orig,
		$width,
		$height,
		esc_attr( $style ),
		esc_attr( $class ),
		esc_attr( $alt )
	);
}


// Set the quality of compression on various WordPress generated image sizes
function av_return_100(){ return 67; }
add_filter('jpeg_quality', 'av_return_100', 9999);
add_filter('wp_editor_set_quality', 'av_return_100', 9999);

// Display custom fields in WordPress admin edit screen
//add_filter('acf/settings/remove_wp_meta_box', '__return_false');

// Add & Remove WP Admin Menu items
add_action('admin_menu', 'battleplan_customize_admin_menus', PHP_INT_MAX);
function battleplan_customize_admin_menus() {
	remove_menu_page( 'link-manager.php' );       							// Links
	remove_menu_page( 'edit-comments.php' );       							// Comments
	remove_menu_page( 'edit.php?post_type=acf-field-group' );       				// Custom Fields
	remove_menu_page( 'themes.php' );       								// Appearance
	remove_menu_page( 'wpengine-common' );   								// WP Engine
	remove_menu_page( 'wp-mail-smtp' );   									// WP Mail SMTP
	remove_menu_page( 'wpseo_dashboard' );   								// Yoast SEO
	remove_menu_page( 'wpseo_workouts' );   								// Yoast SEO
	remove_menu_page( 'post_to_google_my_business');							// Post to GMB

	remove_submenu_page( 'plugins.php', 'plugin-editor.php' );        			// Plugins => Plugin Editor
	remove_submenu_page( 'options-general.php', 'options-writing.php' );   		// Settings => Writing
	remove_submenu_page( 'options-general.php', 'options-reading.php' );   		// Settings => Reading
	remove_submenu_page( 'options-general.php', 'options-media.php' );   			// Settings => Media
	remove_submenu_page( 'options-general.php', 'options-privacy.php' );   		// Settings => Privacy
	remove_submenu_page( 'options-general.php', 'akismet-key-config' );   		// Settings => Akismet
	remove_submenu_page( 'options-general.php', 'git-updater' );   				// Settings => Git Updater
	remove_submenu_page( 'options-general.php', 'git-updater-account' );   		// Settings => Git Updater Account
	remove_submenu_page( 'options-general.php', 'git-updater-contact' );   		// Settings => Git Updater Contact Us
	remove_submenu_page( 'options-general.php', 'codepress-admin-columns' );   	// Settings => Admin Columns
	remove_submenu_page( 'tools.php', 'export-personal-data.php' );   			// Tools => Export Personal Data
	remove_submenu_page( 'tools.php', 'erase-personal-data.php' );   			// Tools => Erase Personal Data

	remove_submenu_page( 'wpseo_dashboard', 'wpseo_workouts' );   				// Yoast SEO => Workouts
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_licenses' );   				// Yoast SEO => Premium
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_page_academy' );   			// Yoast SEO => Academy
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_tools' );   					// Yoast SEO => Tools
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_integrations' );   			// Yoast SEO => Integrations
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_dashboard' );   				// Yoast SEO => General
	remove_submenu_page( 'wp-mail-smtp', 'wp-mail-smtp-logs' );   				// WP Mail SMTP => Email Log
	remove_submenu_page( 'wp-mail-smtp', 'wp-mail-smtp-reports' );   			// WP Mail SMTP => Email Reports
	remove_submenu_page( 'wp-mail-smtp', 'wp-mail-smtp-about' );   				// WP Mail SMTP => About Us

	add_submenu_page( 'upload.php', 'Favicon', 'Favicon', 'manage_options', 'customize.php' );


	if ( _USER_LOGIN !== "battleplanweb" && !in_array('administrator', _USER_ROLES) ) remove_menu_page( 'edit.php?post_type=elements');
	if ( _USER_LOGIN !== "battleplanweb" && !in_array('administrator', _USER_ROLES) ) remove_menu_page( 'edit.php?post_type=landing');

	if ( _USER_LOGIN !== "battleplanweb" ) remove_menu_page( 'edit.php?post_type=universal');
	if ( _USER_LOGIN !== "battleplanweb" ) remove_menu_page( 'tools.php');
	if ( _USER_LOGIN !== "battleplanweb" ) remove_menu_page( 'edit.php?post_type=stripe_order');


	$query = bp_WP_Query('elements', [
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'asc'
	]);

    if ( $query->have_posts() ) :
        while ( $query->have_posts() ) :
            $query->the_post();
            add_submenu_page( 'edit.php?post_type=elements', get_the_title(), get_the_title(), 'manage_options', '/post.php?post='.get_the_ID().'&action=edit' );
        endwhile;
        wp_reset_postdata();
    endif;

	if ( is_null(get_page_by_path('widgets', OBJECT, 'elements')) ) add_submenu_page( 'edit.php?post_type=elements', 'Widgets', 'Widgets', 'manage_options', 'widgets.php' );

	add_submenu_page( 'edit.php?post_type=elements', 'Menus', 'Menus', 'manage_options', 'nav-menus.php' );

	add_submenu_page( 'edit.php?post_type=elements', 'Comments', 'Comments', 'manage_options', 'edit-comments.php' );
	if ( _USER_LOGIN === "battleplanweb" ) add_submenu_page( 'edit.php?post_type=elements', 'Custom Fields', 'Custom Fields', 'manage_options', 'edit.php?post_type=acf-field-group' );
	if ( _USER_LOGIN === "battleplanweb" ) add_submenu_page( 'options-general.php', 'Site Options', 'Site Options', 'manage_options', 'options.php' );
	// Removed from Tools menu — WP Engine. Re-enable by uncommenting.
	// add_submenu_page( 'tools.php', 'WP Engine', 'WP Engine', 'manage_options', 'options-general.php?page=wpengine-common' );

	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'git-updater/git-updater.php' ) ) add_submenu_page( 'tools.php', 'Git Updater', 'Git Updater', 'manage_options', 'options-general.php?page=git-updater' );
	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'admin-columns-pro/admin-columns-pro.php' ) ) add_submenu_page( 'tools.php', 'Admin Columns', 'Admin Columns', 'manage_options', 'options-general.php?page=codepress-admin-columns' );
	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' ) ) add_submenu_page( 'tools.php', 'Mail SMTP', 'Mail SMTP', 'manage_options', 'options-general.php?page=wp-mail-smtp' );

	// Microsoft Clarity is battleplanweb-only. Remove the plugin's top-level Clarity menu for EVERYONE,
	// then re-add it for battleplanweb under Tools → "Clarity". Non-battleplanweb users (incl. other
	// admins and bp_view_stats clients) lose it entirely. Only acts when the plugin registered its menu.
	global $menu;
	$bp_has_clarity = false;
	if ( is_array( $menu ) ) foreach ( $menu as $bp_m ) { if ( isset( $bp_m[2] ) && $bp_m[2] === 'microsoft-clarity' ) { $bp_has_clarity = true; break; } }
	if ( $bp_has_clarity ) {
		remove_menu_page( 'microsoft-clarity' );
		if ( _USER_LOGIN === "battleplanweb" ) {
			add_submenu_page( 'tools.php', 'Microsoft Clarity', 'Clarity', 'manage_options', 'admin.php?page=microsoft-clarity' );
		}
	}

	// Rename the Yoast Premium "Redirects" entry shown under Tools → "URL Redirects".
	// (Registered by the Yoast plugin, not the framework, so it's relabeled here by its visible text.)
	global $submenu;
	if ( ! empty( $submenu['tools.php'] ) && is_array( $submenu['tools.php'] ) ) {
		foreach ( $submenu['tools.php'] as $bp_i => $bp_item ) {
			if ( trim( wp_strip_all_tags( html_entity_decode( $bp_item[0] ) ) ) === 'Yoast Redirects' ) {
				$submenu['tools.php'][$bp_i][0] = 'URL Redirects';
			}
		}
	}

	if ( _USER_LOGIN === "battleplanweb" ) :
		add_submenu_page( 'tools.php', '⚙️ Clear ALL',   '⚙️ Clear ALL',   'manage_options', 'clear-all',   'battleplan_clear_all' );
		add_submenu_page( 'tools.php', '⚙️ Clear HVAC',  '⚙️ Clear HVAC',  'manage_options', 'clear-hvac',  'battleplan_clear_hvac' );
		add_submenu_page( 'tools.php', '⚙️ Launch Site', '⚙️ Launch Site', 'manage_options', 'launch-site', 'battleplan_launch_site' );
	endif;

	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) add_submenu_page( 'options-general.php', 'Yoast Settings', 'Yoast Settings', 'manage_options', 'admin.php?page=wpseo_page_settings' );
	// Removed from Settings menu — Yoast Local (└ Local). Re-enable by uncommenting.
	// if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'wpseo-local/local-seo.php' ) ) add_submenu_page( 'options-general.php', 'Yoast Local', '&nbsp;└&nbsp;Local', 'manage_options', 'admin.php?page=wpseo_local' );
	// Removed from Settings menu — Yoast Redirects (└ Redirects). Re-enable by uncommenting.
	// if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) add_submenu_page( 'options-general.php', 'Yoast Redirects', '&nbsp;└&nbsp;Redirects', 'manage_options', 'admin.php?page=wpseo_redirects' );

	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'options-general.php', 'GBP Settings', 'GBP Settings', 'manage_options', 'admin.php?page=pgmb_settings' );
	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'options-general.php', 'GBP Templates', '&nbsp;└&nbsp;Templates', 'manage_options', 'edit.php?post_type=pgmb_templates' );
	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'options-general.php', 'GBP Calendar', '&nbsp;└&nbsp;Calendar', 'manage_options', 'admin.php?page=post_to_google_my_business' );
	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'options-general.php', 'GBP Account', '&nbsp;└&nbsp;Account', 'manage_options', 'admin.php?page=post_to_google_my_business-account' );

	if (defined('_USER_LOGIN') && _USER_LOGIN === 'battleplanweb') {
		add_submenu_page(
			'edit.php?post_type=jobsite_geo',
			'Jobsite Tools',
			'⚙️ Jobsite Tools',
			'manage_options',
			'jobsite-taxonomy-cleanup',
			'bp_geo_taxonomy_cleanup_page'
		);
	}

	// Tools submenu order: what we use most first, WordPress's own utilities after, the ⚙️ site-ops
	// group last. WordPress orders submenus by registration, so this runs here — at the end of the
	// PHP_INT_MAX admin_menu callback — where every item above (and every plugin's) is already in.
	// Matched on SLUG, not label, so relabelling an item doesn't quietly drop it back to the bottom.
	$bp_tools_order = [
		'bp-seo-redirects',                          // SEO Redirects
		'bp-search-replace',                         // Search & Replace (BP_SR_PAGE)
		'kw-rankings',                               // Keywords
		'admin.php?page=microsoft-clarity',          // Clarity
		'site-health.php',
		'import.php',
		'export.php',
		'options-general.php?page=wp-mail-smtp',     // Mail SMTP
		'clear-all',                                 // ⚙️ site-ops group, battleplanweb only
		'clear-hvac',
		'launch-site',
	];
	if ( ! empty( $submenu['tools.php'] ) && is_array( $submenu['tools.php'] ) ) {
		// Ranks are doubled so an odd number can sit BETWEEN two listed items: anything not in the list
		// (Git Updater, Admin Columns, a plugin's own page) lands just above the ⚙️ group, keeping its
		// relative order, rather than being dropped or interleaved into that group.
		$bp_rank     = [];
		foreach ( $bp_tools_order as $bp_n => $bp_slug ) $bp_rank[ $bp_slug ] = $bp_n * 2;
		$bp_unlisted = ( (int) array_search( 'clear-all', $bp_tools_order, true ) * 2 ) - 1;

		$bp_i = 0;
		$bp_keyed = [];
		foreach ( $submenu['tools.php'] as $bp_item ) {
			$bp_keyed[] = [ $bp_rank[ $bp_item[2] ] ?? $bp_unlisted, $bp_i++, $bp_item ];   // $bp_i keeps ties in registration order
		}
		usort( $bp_keyed, function( $a, $b ) { return ( $a[0] <=> $b[0] ) ?: ( $a[1] <=> $b[1] ); } );
		$submenu['tools.php'] = array_column( $bp_keyed, 2 );
	}
}

// Reorder WP Admin Menu Items
add_filter( 'custom_menu_order', 'battleplan_custom_menu_order', 10, 1 );
add_filter( 'menu_order', 'battleplan_custom_menu_order', 10, 1 );
function battleplan_custom_menu_order( $menu_ord ) {
    if ( !$menu_ord ) return true;
	$displayTypes = array('index.php', 'separator1', 'upload.php', 'edit.php?post_type=elements', 'edit.php?post_type=page');
	$getCPT = getCPT();
	foreach ($getCPT as $postType) array_push($displayTypes, 'edit.php?post_type='.$postType);
	array_push($displayTypes, 'edit.php', 'separator2', 'plugins.php', 'options-general.php', 'tools.php', 'users.php', 'separator-last', 'wpengine-common', 'wpseo_dashboard', 'edit.php?post_type=asp-products');
	return $displayTypes;
}

// Reorder WP Admin Sub-Menu Items
add_filter( 'custom_menu_order', 'battleplan_submenu_order' );
function battleplan_submenu_order($menu_ord) {
	global $submenu;

	if (empty($submenu['options-general.php']) || !is_array($submenu['options-general.php'])) {
		return $menu_ord;
	}

	// Relabel / remove plugin-injected Settings submenu items by their visible label.
	// (These aren't registered by the framework, so they're handled here after the plugins add them.)
	foreach ($submenu['options-general.php'] as $idx => $item) {
		$label = trim( wp_strip_all_tags( html_entity_decode( $item[0] ) ) );
		if ( $label === 'Update Source' ) {
			unset( $submenu['options-general.php'][$idx] );      // remove "Update Source"
		} elseif ( $label === 'Connectors' ) {
			$submenu['options-general.php'][$idx][0] = 'AI Connections';   // rename "Connectors" → "AI Connections"
		}
	}

	$wanted = [10,15,20,25,30,40,45,49,46,48,47];
	$arr = [];

	foreach ($wanted as $idx) {
		if (isset($submenu['options-general.php'][$idx])) {
			$arr[] = $submenu['options-general.php'][$idx];
		}
	}

	// append anything not captured so you don't lose entries
	foreach ($submenu['options-general.php'] as $idx => $item) {
		if (!in_array($item, $arr, true)) {
			$arr[] = $item;
		}
	}

	$submenu['options-general.php'] = $arr;
	return $menu_ord;
}

// Count number of each post type and add an admin note to the menu button
add_action('admin_menu', 'battleplan_custom_post_type_counts');
function battleplan_custom_post_type_counts() {
	$getCPT = array_diff( get_post_types(), array('elements', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'acf-field-group', 'acf-field', 'user_request' ) );

	foreach ($getCPT as $postType) :
		$count_posts = wp_count_posts($postType);
		$num_posts = $count_posts->publish > 0 ? $count_posts->publish : 0;
		global $menu;

		foreach ($menu as $key => $value) :
			if ( $menu[$key][2] === 'edit.php?post_type=' . $postType || ( $menu[$key][2] === 'edit.php' && $postType == 'post') ) :
		 		$menu[$key][0] = $menu[$key][0].' <span class="admin-badge-holder count-'.$num_posts.'"><span class="admin-badge">'.$num_posts.'</span></span>';
			 	break;
		  	endif;
		endforeach;
	endforeach;
}

// The main Dashboard IS the graphic Analytics screen now. Bounce the bare dashboard (index.php with
// no subpage) straight to the Analytics page for anyone who can view stats — the old numeric widgets
// are retired (see battleplan_add_dashboard_widgets in functions-admin-stats.php). Escape hatch for the
// native WordPress dashboard (Site Health, At-a-Glance, etc.): add ?core=1 to the index.php URL.
add_action('admin_init', 'bp_dashboard_to_analytics');
function bp_dashboard_to_analytics() {
	global $pagenow;
	if ( $pagenow !== 'index.php' ) return;                                  // dashboard home only
	if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) return;         // never intercept POSTs
	if ( ! empty( $_GET['page'] ) || ! empty( $_GET['core'] ) ) return;      // already a subpage / escape hatch
	if ( wp_doing_ajax() ) return;
	if ( ! ( _USER_LOGIN === 'battleplanweb' || in_array( 'bp_view_stats', (array) _USER_ROLES ) || current_user_can( 'manage_options' ) ) ) return;
	wp_safe_redirect( admin_url( 'index.php?page=bp-analytics' ) );
	exit;
}

// Remove unwanted dashboard widgets
add_action('wp_dashboard_setup', 'battleplan_remove_dashboard_widgets');
function battleplan_remove_dashboard_widgets () {
	/*
	remove_action('welcome_panel','wp_welcome_panel'); 								//Welcome to WordPress!
	remove_meta_box('wpe_dify_news_feed','dashboard','normal'); 					//WP Engine
	remove_meta_box('wpe_dify_news_feed','dashboard','side'); 						//WP Engine
	*/
	remove_meta_box('dashboard_activity','dashboard','normal');						// Activity
	remove_meta_box('dashboard_activity','dashboard','side');						// Activity
	remove_meta_box('dashboard_right_now','dashboard','normal');					// At A Glance
	remove_meta_box('dashboard_right_now','dashboard','side');						// At A Glance
	remove_meta_box('dashboard_quick_press','dashboard','normal'); 					// Quick Draft
	remove_meta_box('dashboard_quick_press','dashboard','side'); 					// Quick Draft
	remove_meta_box('dashboard_site_health','dashboard','normal');					// Site Health
	remove_meta_box('dashboard_site_health','dashboard','side');					// Site Health
	remove_meta_box('woocommerce_dashboard_status','dashboard','normal');			// Woocommerce
	remove_meta_box('woocommerce_dashboard_status','dashboard','side');				// Woocommerce
	remove_meta_box('dashboard_primary','dashboard','normal'); 						// WordPress Events and News
	remove_meta_box('dashboard_primary','dashboard','side'); 						// WordPress Events and News
	remove_meta_box('wp_mail_smtp_reports_widget_lite','dashboard','normal');		// WP Mail SMTP
	remove_meta_box('wp_mail_smtp_reports_widget_lite','dashboard','side');			// WP Mail SMTP
	remove_meta_box('wp_mail_smtp_reports_widget_pro','dashboard','normal');		// WP Mail SMTP Pro
	remove_meta_box('wp_mail_smtp_reports_widget_pro','dashboard','side');			// WP Mail SMTP Pro
	remove_meta_box('wpseo-dashboard-overview','dashboard','normal');				// Yoast SEO Posts Overview
	remove_meta_box('wpseo-dashboard-overview','dashboard','side');					// Yoast SEO Posts Overview
	remove_meta_box('wpseo-wincher-dashboard-overview','dashboard','normal');		// Yoast SEO / Wincher Top Keyphrases
	remove_meta_box('wpseo-wincher-dashboard-overview','dashboard','side');			// Yoast SEO / Wincher Top Keyphrases
}

// Disable dashboard widget drag-and-drop (positions are controlled server-side)
// Force 3-column layout so column3 is always visible (default is 2, which hides column3 via CSS)
add_filter('get_user_option_screen_layout_dashboard', function() { return 3; });

// Reposition widgets after wp_dashboard_setup() has finished applying saved order.
// admin_head fires after wp_dashboard_setup() but before do_meta_boxes() renders the page.
add_action('admin_head', function() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'dashboard' ) return;
	global $wp_meta_boxes;
	if ( empty( $wp_meta_boxes['dashboard'] ) ) return;

	foreach ( [
		'bp_keyword_rankings'      => [ 'column3', 'default' ],
		'battleplan_queries_stats' => [ 'side',    'low'     ],
	] as $id => [ $target_ctx, $target_pri ] ) {
		foreach ( $wp_meta_boxes['dashboard'] as $ctx => $priorities ) {
			foreach ( $priorities as $pri => $boxes ) {
				if ( isset( $boxes[ $id ] ) ) {
					$box = $wp_meta_boxes['dashboard'][ $ctx ][ $pri ][ $id ];
					unset( $wp_meta_boxes['dashboard'][ $ctx ][ $pri ][ $id ] );
					$wp_meta_boxes['dashboard'][ $target_ctx ][ $target_pri ][ $id ] = $box;
					break 2;
				}
			}
		}
	}
});

// admin_print_footer_scripts fires AFTER wp_print_footer_scripts so our JS runs
// after WordPress's postboxes/dashboard scripts have initialized sortable.
add_action('admin_print_footer_scripts', function() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'dashboard' ) return;
	?>
	<script>
	jQuery(function($) {
		// Reposition widgets into correct columns
		$('#postbox-container-3 .meta-box-sortables').append($('#bp_keyword_rankings'));
		$('#postbox-container-2 .meta-box-sortables').append($('#battleplan_queries_stats'));

		// Disable sortable — only on containers where WP has already initialized it
		$('.meta-box-sortables').filter(function() {
			return $(this).data('ui-sortable');
		}).sortable('disable');

		// Prevent any drag from saving positions to the DB
		if ( window.postboxes ) postboxes.save_order = function() {};
	});
	</script>
	<style>
	#dashboard-widgets .postbox .hndle { cursor: default !important; pointer-events: none !important; }
	#dashboard-widgets .postbox .handle-actions .handle-order-higher,
	#dashboard-widgets .postbox .handle-actions .handle-order-lower { display: none !important; }
	</style>
	<?php
});

// Microsoft Clarity logo-link beside the Dashboard heading. battleplanweb only, and only
// when a Clarity project ID is set in customer_info['clarity-tags']['id'] (legacy: google-tags['clarity']).
// Just the logo (no button or label); clicking it opens the in-admin Clarity page.
add_action('admin_print_footer_scripts', function() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'dashboard' ) return;

	$customer_info = get_option('customer_info');
	$clarity = is_array($customer_info)
		? ( $customer_info['clarity-tags']['id'] ?? $customer_info['google-tags']['clarity'] ?? '' ) : '';
	if ( ! $clarity ) return;

	if ( _USER_LOGIN !== 'battleplanweb' ) return;   // Clarity link is battleplanweb-only

	$url  = esc_url_raw( admin_url( 'admin.php?page=microsoft-clarity' ) );
	$logo = esc_url_raw( get_template_directory_uri() . '/common/logos/microsoft-clarity.webp' );
	?>
	<script>
	jQuery(function($) {
		var $h1 = $('.wrap > h1').first();
		if ( ! $h1.length || $h1.next('.bp-clarity-link').length ) return;
		$h1.addClass('wp-heading-inline');
		var $a = $('<a/>', {
			'class': 'bp-clarity-link',
			href:  <?php echo json_encode($url); ?>,
			title: 'Open Microsoft Clarity'
		});
		$('<img/>', { src: <?php echo json_encode($logo); ?>, alt: 'Microsoft Clarity' }).appendTo($a);
		$h1.after($a);
	});
	</script>
	<style>
	.wrap > h1.wp-heading-inline + .bp-clarity-link { margin-left: 10px; }
	.bp-clarity-link { display: inline-flex; align-items: center; vertical-align: middle; line-height: 0; }
	.bp-clarity-link img { height: 28px; width: auto; display: block; }
	</style>
	<?php
});

// Load site stats if hooked to Google Analytics
$customer_info = get_option('customer_info');
$prop_id = (is_array($customer_info) && isset($customer_info['google-tags']['prop-id'])) ? (int)$customer_info['google-tags']['prop-id'] : 0;

if ( $prop_id > 1 && is_admin() && (_USER_LOGIN === "battleplanweb" || in_array('bp_view_stats', _USER_ROLES)) ) {
	require_once get_template_directory() . '/functions-admin-stats.php';
}

// Keyword rankings dashboard widget + admin page
if ( is_admin() && (_USER_LOGIN === "battleplanweb" || in_array('bp_view_stats', _USER_ROLES)) ) {
    require_once get_template_directory() . '/functions-keyword-rankings.php';
}

// Adjust the number of of posts listed on admin pages
add_filter( 'edit_posts_per_page', 'custom_posts_per_page_based_on_type_in_admin', 10, 2 );
function custom_posts_per_page_based_on_type_in_admin( $per_page, $post_type ) {
	/*
		if ( _USER_LOGIN == 'battleplanweb' ) :
			$last_logins = is_array(get_option('bp_last_login')) ? get_option('bp_last_login') : array();
			define( '_LAST_LOGIN', $last_logins);
			$last_logins[$post_type] = time();
			update_option( 'bp_last_login', $last_logins, false);
		endif;

		if ( defined('_LAST_LOGIN') && _LAST_LOGIN[$post_type] < (time() - 30000) ) :
			if( $post_type == 'post' || $post_type == 'page' || $post_type == 'landing' || $post_type == 'galleries' || $post_type == 'attachment' ) : return 30;
			elseif( $post_type == 'testimonials' || $post_type == 'products' || $post_type == 'product' ) : return 30;
			else : return 50;
			endif;
		endif;
	*/

	if ( $post_type == 'testimonials' || $post_type == 'attachment' )  return 30;
    return $per_page;
}

/*--------------------------------------------------------------
# Admin Page Set Up
--------------------------------------------------------------*/
// Add important info as body classes
// Define a function to add the option value to body class
add_filter('admin_body_class', 'battleplan_add_body_classes');
function battleplan_add_body_classes($classes) {
	$customer_info = customer_info();
	$siteType = $customer_info['site-type'] ?? null;
	$bizTypeRaw = $customer_info['business-type'] ?? null;

    if ( $siteType ) $classes .= ' site-type-'.strtolower($siteType);

	if (is_array($bizTypeRaw)) {
		foreach ($bizTypeRaw as $bizType) {
			$bizType = preg_replace('/[^a-zA-Z0-9\s]/', '', $bizType);
			$bizType = preg_replace('/\s+/', '-', trim($bizType));
			if ($bizType) {
				$classes .= ' business-type-' . strtolower($bizType);
			}
		}
	} elseif ($bizTypeRaw) {
		$bizType = preg_replace('/[^a-zA-Z0-9\s]/', '', $bizTypeRaw);
		$bizType = preg_replace('/\s+/', '-', trim($bizType));
		$classes .= ' business-type-' . strtolower($bizType);
	}

	$user = wp_get_current_user();
	if ( $user->exists() ) $classes .= ' user-'.$user->user_login;

    return $classes;
}

// Add "Remove Sidebar" checkbox to Page Attributes meta box
add_action( 'page_attributes_misc_attributes', 'battleplan_remove_sidebar_checkbox', 10, 1 );
function battleplan_remove_sidebar_checkbox($post) {
	echo '<p class="post-attributes-label-wrapper">';
	$getRemoveSidebar = get_post_meta($post->ID, "_bp_remove_sidebar", true);

	if ( $getRemoveSidebar == "" ) :
		echo '<input name="remove_sidebar" type="checkbox" value="true">';
	else:
		echo '<input name="remove_sidebar" type="checkbox" value="true" checked>';
	endif;

	echo '<label class="post-attributes-label" for="remove_sidebar">Remove Sidebar</label>';
}

add_action('save_post', 'battleplan_save_remove_sidebar', 10, 3);
function battleplan_save_remove_sidebar($post_id, $post, $update) {
	if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || ( defined('DOING_AJAX') && DOING_AJAX ) || !current_user_can('edit_post', $post_id) ) return;

	$lastViewed = readMeta( $post_id, 'log-last-viewed' );
	if ( !$lastViewed ) updateMeta( $post_id, 'log-last-viewed', strtotime("-2 days"));

    $updateRemoveSidebar = "";
    if ( isset($_POST["remove_sidebar"]) ) $updateRemoveSidebar = $_POST["remove_sidebar"];
    update_post_meta($post_id, "_bp_remove_sidebar", $updateRemoveSidebar);

	// check for duplicate before posting a new testimonial
	if ( $post->post_type == 'testimonials') :
		$new_post_title = $post->post_title;
		$query = bp_WP_Query('testimonials', [
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__not_in'   => [$post_id]
		]);

		$found_duplicate = false;

		if ($query->have_posts()) :	while ($query->have_posts()) : $query->the_post();
				if ( strtolower(get_the_title()) == strtolower($new_post_title)) :
					$found_duplicate = true;
					$existing_post_id = get_the_ID();
					break;
				endif;
			endwhile;
		endif;

		wp_reset_postdata();

		if ($found_duplicate) :
			wp_delete_post($post_id, true);
			$edit_post_url = get_edit_post_link($existing_post_id, 'raw');
			wp_redirect($edit_post_url);
			exit;
		endif;
	endif;
}

// Add "duplicate post/page" function to WP core
add_action( 'admin_action_battleplan_duplicate_post_as_draft', 'battleplan_duplicate_post_as_draft' );
function battleplan_duplicate_post_as_draft(){
	global $wpdb;

	if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'battleplan_duplicate_post_as_draft' == $_REQUEST['action'] ) ) ) wp_die('No post to duplicate has been supplied!');
	if ( !isset( $_GET['duplicate_nonce'] ) || !wp_verify_nonce( $_GET['duplicate_nonce'], basename( __FILE__ ) ) )	return;

	$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
	$post = get_post( $post_id );
	$current_user = wp_get_current_user();
	$new_post_author = $current_user->ID;
	if (isset( $post ) && $post != null) :
		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);
		$new_post_id = wp_insert_post( $args );
		$taxonomies = get_object_taxonomies($post->post_type);
		foreach ($taxonomies as $taxonomy) :
			$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
			wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
		endforeach;

		$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
		if (count($post_meta_infos)!=0) :
			$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
			foreach ($post_meta_infos as $meta_info) :
				$meta_key = $meta_info->meta_key;
				if( $meta_key == '_wp_old_slug' ) continue;
				$meta_value = addslashes($meta_info->meta_value);
				$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
			endforeach;
			$sql_query.= implode(" UNION ALL ", $sql_query_sel);
			$wpdb->query($sql_query);
		endif;

		updateMeta( $new_post_id, 'log-last-viewed', strtotime("-2 days"));
		updateMeta( $new_post_id, 'log-views-today', '0' );
		updateMeta( $new_post_id, 'log-views-total-7day', '0' );
		updateMeta( $new_post_id, 'log-views-total-30day', '0' );
		updateMeta( $new_post_id, 'log-views-total-90day', '0' );
		updateMeta( $new_post_id, 'log-views-total-365day', '0' );
		updateMeta( $new_post_id, 'log-views', array( 'date'=> strtotime(date("F j, Y")), 'views' => 0 ));

		wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		exit;
	else :
		wp_die('Post creation failed, could not find original post: '.$post_id);
	endif;
}

// Replace Page & Post links with icons
add_filter( 'post_row_actions', 'battleplan_post_row_actions', 90, 2 );
add_filter( 'page_row_actions', 'battleplan_post_row_actions', 90, 2 );
function battleplan_post_row_actions( $actions, $post ) {
	$out = [];

	if (isset($actions['edit'])) {
		$edit = str_replace("Edit", "<i class='dashicons-edit'></i>", $actions['edit']);
		$out['edit'] = str_replace("<a href", "<a title='Edit' target='_blank' rel='noopener' href", $edit);
	}

	$out['inline hide-if-no-js'] = '<button type="button" class="button-link editinline" aria-label="Quick edit" aria-expanded="false"><i class="dashicons-quick-edit"></i></button>';

	$out['duplicate'] = '<a target="_blank" rel="noopener" href="' .
		wp_nonce_url('admin.php?action=battleplan_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce') .
		'" title="Clone" rel="permalink"><i class="dashicons-clone"></i></a>';

	if (isset($actions['view'])) {
		$view = str_replace(["View","Preview"], "<i class='dashicons-view'></i>", $actions['view']);
		$out['view'] = str_replace("<a href", "<a title='View' target='_blank' rel='noopener' href", $view);
	}

	$is_published   = $post->post_status === 'publish';
	$toggle_icon    = $is_published ? 'dashicons-hidden' : 'dashicons-visibility';
	$toggle_title   = $is_published ? 'Send to Draft' : 'Publish';
	$toggle_url     = wp_nonce_url(
		admin_url('admin.php?action=battleplan_toggle_post_status&post=' . $post->ID),
		'battleplan_toggle_status_' . $post->ID
	);
	$out['toggle_status'] = '<a href="' . esc_url($toggle_url) . '" title="' . $toggle_title . '"><i class="' . $toggle_icon . '"></i></a>';

	if (isset($actions['trash'])) {
		$delete = str_replace("Trash", "<i class='dashicons-trash'></i>", $actions['trash']);
		$out['delete'] = str_replace("<a href", "<a title='Delete' href", $delete);
	}

	return $out;
}


// Replace Media Library image links with icons
add_filter('media_row_actions', 'battleplan_media_row_actions', 90, 2);
function battleplan_media_row_actions( $actions, $post ) {
	$out = [];

	if (isset($actions['edit'])) {
		$edit = str_replace("Edit", "<i class='dashicons-edit'></i>", $actions['edit']);
		$out['edit'] = str_replace("<a href", "<a title='Edit Media' target='_blank' rel='noopener' href", $edit);
	}

	if (isset($actions['view'])) {
		$view = str_replace("View", "<i class='dashicons-view'></i>", $actions['view']);
		$out['view'] = str_replace("<a href", "<a title='View Media' target='_blank' rel='noopener' href", $view);
	}

	if (isset($actions['media_replace'])) {
		$rep = str_replace("Replace media", "<i class='dashicons-replace'></i>", $actions['media_replace']);
		$out['media_replace'] = str_replace("<a href", "<a title='Replace Media' target='_blank' rel='noopener' href", $rep);
	}

	if (isset($actions['delete'])) {
		$del = str_replace("Delete Permanently", "<i class='dashicons-trash'></i>", $actions['delete']);
		$out['delete'] = str_replace("<a href", "<a title='Delete Media' href", $del);
	}

	return $out;
}


// Replace Users links with icons
add_filter( 'user_row_actions', 'battleplan_user_row_actions', 90, 2 );
function battleplan_user_row_actions($actions, $user_object) {
	$out = [];

	if (isset($actions['edit'])) {
		$out['edit'] = str_replace("Edit", "<i class='dashicons-edit'></i>", $actions['edit']);
	}
	if (isset($actions['delete'])) {
		$out['delete'] = str_replace("Delete", "<i class='dashicons-trash'></i>", $actions['delete']);
	}
	if (isset($actions['switch_to_user'])) {
		$out['switch_to_user'] = str_replace("Switch&nbsp;To", "<i class='dashicons-randomize'></i>", $actions['switch_to_user']);
	}

	return $out;
}

// Toggle post status between publish and draft
add_action('admin_action_battleplan_toggle_post_status', function() {
	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	if (!$post_id) wp_die('Invalid post.');

	check_admin_referer('battleplan_toggle_status_' . $post_id);

	if (!current_user_can('edit_post', $post_id)) wp_die('Insufficient permissions.');

	$post = get_post($post_id);
	if (!$post) wp_die('Post not found.');

	$new_status = $post->post_status === 'publish' ? 'draft' : 'publish';
	wp_update_post(['ID' => $post_id, 'post_status' => $new_status]);

	$redirect = wp_get_referer() ?: admin_url('edit.php?post_type=' . $post->post_type);
	wp_safe_redirect($redirect);
	exit;
});

// Automatically set the image Title, Alt-Text, Caption & Description upon upload
add_action( 'add_attachment', 'battleplan_setImageMetaUponUpload' );
function battleplan_setImageMetaUponUpload( $post_ID ) {
	if ( wp_attachment_is_image( $post_ID ) ) :
		$imageTitle = get_post( $post_ID )->post_title;
		$imageTitle = ucwords( preg_replace( '%\s*[-_\s]+\s*%', ' ', $imageTitle )); // remove hyphens, underscores & extra spaces and capitalize
		$imageMeta = array ( 'ID' => $post_ID, 'post_title' => $imageTitle ) /* post title */;
		update_post_meta( $post_ID, '_wp_attachment_image_alt', $imageTitle ) /* alt text */;
		wp_update_post( $imageMeta );
	endif;
}

// Add 'log-views' fields to an image when it is uploaded
add_action( 'add_attachment', 'battleplan_addWidgetPicViewsToImg' );
function battleplan_addWidgetPicViewsToImg( $post_ID ) {
	if ( wp_attachment_is_image( $post_ID ) ) :
		updateMeta( $post_ID, 'log-last-viewed', strtotime("-2 days"));
		updateMeta( $post_ID, 'log-views-today', '0' );
		updateMeta( $post_ID, 'log-views-total-7day', '0' );
		updateMeta( $post_ID, 'log-views-total-30day', '0' );
		updateMeta( $post_ID, 'log-views-total-90day', '0' );
		updateMeta( $post_ID, 'log-views-total-365day', '0' );
		updateMeta( $post_ID, 'log-views', array( 'date'=> strtotime(date("F j, Y")), 'views' => 0 ));
	endif;
}

// Add 'image-category' to testimonials and jobsite geo posts
add_action('add_attachment', 'battleplan_auto_add_image_category');
function battleplan_auto_add_image_category($attachment_id) {
    $parent_post_id = get_post($attachment_id)->post_parent;
    $parent_post = get_post($parent_post_id);

    if ($parent_post && $parent_post->post_type === 'jobsite_geo') {
        $term = 'Jobsite GEO';
        if (!term_exists($term, 'image-categories')) wp_insert_term($term, 'image-categories');
        wp_set_object_terms($attachment_id, $term, 'image-categories', true);
    }

    if ($parent_post && $parent_post->post_type === 'testimonials') {
        $term = 'Testimonials';
        if (!term_exists($term, 'image-categories')) wp_insert_term($term, 'image-categories');
        wp_set_object_terms($attachment_id, $term, 'image-categories', true);
    }
}

// The audit can't run inside a single request (crawl + PageSpeed renders + a Claude call blow the
// gateway timeout — that was the 502). It's now driven step-by-step from the Site Audit screen, so
// this action just sends you there; the Run Audit button on that page does the work.
// Orphaned when the '└ Run Audit' submenu was removed (the Run/Resume button lives on the audit page
// now). Kept commented for reference in case a direct redirect entry point is ever wanted again.
// function battleplan_force_run_audit() {
//     wp_safe_redirect(admin_url('index.php?page=site-audit'));
//     exit;
// }

function battleplan_chron_gbp_status() {
    update_option('bp_chron_a_time', time());
    update_option('bp_chron_a_next', bp_next_nightly_window());
    require_once get_template_directory() . '/functions-chron-gbp.php';
    bp_run_chron_gbp(true);
    wp_safe_redirect(admin_url());
    exit;
}

function battleplan_chron_housekeeping_status() {
    update_option('bp_chron_b_time', time());
    update_option('bp_chron_b_next', bp_next_nightly_window());
    require_once get_template_directory() . '/functions-chron-housekeeping.php';
    bp_run_chron_housekeeping(true);
    wp_safe_redirect(admin_url());
    exit;
}

function battleplan_chron_analytics_status() {
    update_option('bp_chron_c_time', time());
    update_option('bp_chron_c_next', bp_next_nightly_window());
    require_once get_template_directory() . '/functions-chron-analytics.php';
    bp_run_chron_analytics(true);
    wp_safe_redirect(admin_url());
    exit;
}


/*
 * TEMP diagnostic: per-channel monthly history readout (battleplanweb only).
 * Verifies the new bp_ga4_channel_history time series before the Analytics page
 * is built. Hidden unless the URL carries ?bp_ga4_channels=1. Remove once the
 * real Analytics page ships.
 */

// TEMP diagnostic (battleplanweb + ?bp_audit=1): why aren't the audit-metric cards populating? Shows each
// history's row count + the precondition behind each collector, and can force a run / test the GBP hub call.
add_action('admin_notices', 'bp_audit_metrics_debug');
function bp_audit_metrics_debug() {
    if (!defined('_USER_LOGIN') || _USER_LOGIN !== 'battleplanweb' || empty($_GET['bp_audit'])) return;
    $t = get_template_directory();
    foreach (['functions-chron-helpers.php', 'functions-chron-analytics.php'] as $f) { if (file_exists("$t/$f")) require_once "$t/$f"; }

    if (!empty($_GET['run'])) {
        delete_option('bp_audit_metrics_next');
        if (function_exists('bp_collect_audit_metrics')) bp_collect_audit_metrics();
        echo '<div class="notice notice-info"><p>Ran audit-metrics collectors.</p></div>';
    }

    $cnt = function ($k) { $v = get_option($k); return is_array($v) ? count($v) : 0; };
    $svc = defined('GA4_SERVICE_ACCOUNT_JSON') ? 'defined' : 'MISSING';
    $tok = function_exists('bp_gsc_token') ? (bp_gsc_token() ? 'OK' : 'NULL') : 'fn-missing';
    $ci  = function_exists('customer_info') ? customer_info() : [];
    $pids = function_exists('ci_normalize_pids') ? ci_normalize_pids($ci['pid'] ?? []) : array_filter((array) ($ci['pid'] ?? []));
    $gbpUpd = get_option('bp_gbp_update');
    $revSum = 0; if (is_array($gbpUpd)) foreach ($pids as $p) $revSum += (int) ($gbpUpd[$p]['google-reviews'] ?? 0);
    $hubCfg = function_exists('bpgbp_client_is_configured') ? (bpgbp_client_is_configured() ? 'yes' : 'no') : 'fn-missing';
    $perf = '(click test)';
    if (!empty($_GET['perf']) && function_exists('bpgbp_client_get')) {
        $r = bpgbp_client_get('performance');
        $perf = is_wp_error($r) ? ('ERROR: ' . $r->get_error_message())
              : (empty($r['ok']) ? ('no-ok: ' . wp_json_encode($r)) : ('OK — days=' . (is_array($r['metrics'] ?? null) ? count($r['metrics']) : 0)));
    }

    echo '<div class="notice notice-warning"><p><b>Audit-metrics diagnostic</b><br>';
    echo 'history rows — searchConsole: ' . $cnt('bp_gsc_totals_history') . ' &nbsp; backlinks: ' . $cnt('bp_gsc_links_history')
       . ' &nbsp; content: ' . $cnt('bp_content_history') . ' &nbsp; reviews: ' . $cnt('bp_gbp_stats_history') . ' &nbsp; gbpPerf: ' . $cnt('bp_gbp_perf_history') . '<br>';
    echo '<b>GSC</b>: service-account=' . $svc . ', token=' . $tok . '<br>';
    echo '<b>Reviews</b>: pids=[' . esc_html(implode(', ', (array) $pids)) . '] &nbsp; bp_gbp_update entries=' . (is_array($gbpUpd) ? count($gbpUpd) : 0) . ' &nbsp; reviews sum=' . $revSum . '<br>';
    echo '<b>GBP perf</b>: hub configured=' . $hubCfg . ' &nbsp; perf call=' . esc_html($perf) . '<br>';
    echo '<a class="button" href="' . esc_url(add_query_arg(['bp_audit' => 1, 'run' => 1])) . '">▶ Run collectors now</a> &nbsp; ';
    echo '<a class="button" href="' . esc_url(add_query_arg(['bp_audit' => 1, 'perf' => 1])) . '">Test GBP perf hub call</a>';
    echo '</p></div>';
}

add_action('admin_notices', 'bp_ga4_channel_history_debug');
function bp_ga4_channel_history_debug() {

    if (!defined('_USER_LOGIN') || _USER_LOGIN !== 'battleplanweb') return;
    if (!isset($_GET['bp_ga4_channels'])) return;

    // Optional: run JUST the channel collector (faster than the full Stats re-collect).
    if (isset($_GET['run']) && check_admin_referer('bp_ga4_channels_run')) {
        require_once get_template_directory() . '/functions-chron-analytics.php';
        $ga4 = function_exists('bp_ga4_client') ? bp_ga4_client() : null;
        $ok  = $ga4 ? bp_ga4_collect_channel_history($ga4['client'], $ga4['property']) : false;
        echo '<div class="notice notice-' . ($ok ? 'success' : 'error') . '"><p>Channel collection: '
           . ($ok ? 'OK' : 'FAILED (no client or no data)') . '</p></div>';
    }

    $history = get_option('bp_ga4_channel_history');
    $runUrl  = wp_nonce_url(add_query_arg(['bp_ga4_channels' => 1, 'run' => 1]), 'bp_ga4_channels_run');
    $diagUrl = add_query_arg(['bp_ga4_channels' => 1, 'diag' => 1]);

    echo '<div class="notice notice-info"><p><strong>GA4 Channel History (debug)</strong> &nbsp; '
       . '<a class="button button-small" href="' . esc_url($runUrl) . '">Run channel collection now</a> &nbsp; '
       . '<a class="button button-small" href="' . esc_url($diagUrl) . '">Diagnose (query matrix)</a></p>';

    // Diagnostic matrix — pinpoints whether the channel dimension, yearMonth, or the
    // geo filter is suppressing rows (and whether GA4 thresholding is the cause).
    if (isset($_GET['diag'])) {
        require_once get_template_directory() . '/functions-chron-analytics.php';
        $diag = function_exists('bp_ga4_channel_diagnose') ? bp_ga4_channel_diagnose() : ['error' => 'fn missing'];
        echo '<pre style="font-size:11px;background:#fff;border:1px solid #ccd;padding:8px;overflow:auto;max-height:420px">'
           . esc_html(print_r($diag, true)) . '</pre>';
    }

    if (!is_array($history) || !$history) {
        echo '<p>No channel history stored yet — click “Run channel collection now”, or Dashboard → Stats.</p></div>';
        return;
    }

    // Channel columns ordered by total sessions across all months.
    $totals = [];
    foreach ($history as $channels) {
        if (!is_array($channels)) continue;
        foreach ($channels as $ch => $m) $totals[$ch] = ($totals[$ch] ?? 0) + (int)($m['sessions'] ?? 0);
    }
    arsort($totals);
    $cols = array_keys($totals);

    krsort($history);
    $months = array_slice(array_keys($history), 0, 36);

    $render = function ($metric, $title) use ($history, $cols, $months) {
        echo '<p style="margin:10px 0 2px"><strong>' . esc_html($title) . '</strong></p>';
        echo '<table class="widefat striped" style="max-width:100%;font-size:11px"><thead><tr><th>Month</th>';
        foreach ($cols as $ch) echo '<th>' . esc_html($ch) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($months as $ym) {
            echo '<tr><td>' . esc_html(substr($ym, 0, 4) . '-' . substr($ym, 4, 2)) . '</td>';
            foreach ($cols as $ch) {
                $v = $history[$ym][$ch][$metric] ?? 0;
                echo '<td>' . ($v ? number_format((float)$v, $metric === 'conversions' ? 1 : 0) : '·') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    };

    echo '<p style="margin:6px 0"><em>' . count($history) . ' months, ' . count($cols) . ' channels stored.</em></p>';

    $meta = get_option('bp_ga4_channel_history_meta');
    if (is_array($meta)) {
        echo '<p style="margin:6px 0;font-size:11px;color:#666"><em>Last run — '
           . 'years=' . esc_html((string)($meta['years'] ?? '?'))
           . ', core rows=' . esc_html((string)($meta['core_rows'] ?? '?'))
           . ', conv rows=' . esc_html((string)($meta['conv_rows'] ?? '?'))
           . ' (' . esc_html((string)($meta['conv_metric'] ?? '—')) . ')'
           . ', months built=' . esc_html((string)($meta['months'] ?? '?'))
           . '<br>ranges: ' . esc_html(implode('   ', (array)($meta['ranges'] ?? [])))
           . '</em></p>';
    }

    $render('sessions', 'Sessions by channel');
    $render('conversions', 'Conversions (key events) by channel');

    // Quality signals — is a Direct spike junk (bots / lost attribution: low engagement,
    // high new%) or real ad-driven return traffic (normal engagement)? Computed from the
    // already-stored history — no GA4 call. Compare Direct against clean channels.
    $quality = function ($m) {
        $s = (int) ($m['sessions'] ?? 0);
        $u = (int) ($m['users'] ?? 0);
        return [
            's'   => $s,
            'new' => $u ? round(((int) ($m['newUsers'] ?? 0)        / $u) * 100) : 0,
            'eng' => $s ? round(((int) ($m['engagedSessions'] ?? 0) / $s) * 100) : 0,
            'dur' => $s ? round((float) ($m['duration'] ?? 0)       / $s)        : 0,
        ];
    };

    echo '<p style="margin:14px 0 2px"><strong>Quality signals — Direct vs clean channels</strong><br>'
       . '<span style="font-size:11px;color:#666">Junk / bot / lost-attribution traffic = <b>low eng%</b> + <b>low avg&nbsp;s</b> + <b>high new%</b>. '
       . 'Real return visits engage normally. Watch Direct during the ad months vs Organic Search / GBP.</span></p>';
    echo '<table class="widefat striped" style="max-width:100%;font-size:11px"><thead><tr>'
       . '<th>Month</th><th>Direct sess</th><th>Direct new%</th><th>Direct eng%</th><th>Direct avg&nbsp;s</th>'
       . '<th>Organic eng%</th><th>Organic avg&nbsp;s</th><th>GBP eng%</th><th>GBP avg&nbsp;s</th></tr></thead><tbody>';
    foreach ($months as $ym) {
        $d = isset($history[$ym]['Direct'])         ? $quality($history[$ym]['Direct'])         : null;
        $o = isset($history[$ym]['Organic Search']) ? $quality($history[$ym]['Organic Search']) : null;
        $g = isset($history[$ym]['GBP'])            ? $quality($history[$ym]['GBP'])             : null;
        echo '<tr><td>' . esc_html(substr($ym, 0, 4) . '-' . substr($ym, 4, 2)) . '</td>'
           . '<td>' . ($d ? number_format($d['s']) : '·') . '</td>'
           . '<td>' . ($d ? $d['new'] . '%' : '·') . '</td>'
           . '<td>' . ($d ? $d['eng'] . '%' : '·') . '</td>'
           . '<td>' . ($d ? $d['dur'] : '·') . '</td>'
           . '<td>' . ($o ? $o['eng'] . '%' : '·') . '</td>'
           . '<td>' . ($o ? $o['dur'] : '·') . '</td>'
           . '<td>' . ($g ? $g['eng'] . '%' : '·') . '</td>'
           . '<td>' . ($g ? $g['dur'] : '·') . '</td>'
           . '</tr>';
    }
    echo '</tbody></table>';

    echo '</div>';
}


function bp_format_metric_label(string $label): string {
    return preg_replace('/(\(\w+\))/', '<span class="disclaimer">$1</span>', $label);
}

// Add dialog boxes to shortcode helpers in text editor
add_action('admin_enqueue_scripts', 'battleplan_setupTextEditorDialogBoxes');
function battleplan_setupTextEditorDialogBoxes($hook) {
	$screen_ok = ($hook === 'post.php' || $hook === 'post-new.php');
	if(!$screen_ok) return;

	// script-admin is enqueued globally via battleplan_admin_scripts — just add the QTags config here
	$bp_qtags_cfg = [
		'section' => [
			'label' => 'Section',
			'wrap' => true,
			'defaults' => [ 'name'=>'', 'class'=>'', 'style'=>'', 'width'=>'default', 'break'=>'', 'valign'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'background'=>'/wp-content/uploads/', 'left'=>'50', 'top'=>'50', 'css'=>'', 'hash'=>'', 'grid'=>'', 'data'=>'' ],
			'fields' => [
				[ 'name'=>'name', 'type'=>'text', 'label'=>'Name (id)' ],
				[ 'name'=>'style', 'type'=>'select-custom', 'label'=>'Style',
					'choices' => [ '' => 'none', '_1' => '1', '_2' => '2', '_3' => '3', '_4' => '4', 'lock' => 'lock', 'custom' => 'custom' ] ],
				[ 'name'=>'width', 'type'=>'select', 'label'=>'Width',
					'choices'=>[ ''=>'default', 'stretch'=>'stretch', 'full'=>'full', 'edge'=>'edge', 'inline'=>'inline' ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'break', 'type'=>'select', 'label'=>'Break',
					'choices' => [ '' => 'none', '_4' => '4', '_3' => '3', '_2' => '2', '_1' => '1' ] ],
				[ 'name'=>'valign', 'type'=>'select', 'label'=>'V-Align',
					'choices'=>[ ''=>'none', 'center'=>'center', 'stretch'=>'stretch', 'start'=>'start', 'end'=>'end' ] ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'id' => 'name (id)', 'custom' => 'custom' ] ],
				[ 'name'=>'background', 'type'=>'text', 'label'=>'Background' ],
				[ 'name'=>'left', 'type'=>'text', 'label'=>'Left %' ],
				[ 'name'=>'top', 'type'=>'text', 'label'=>'Top %' ],
				[ 'name'=>'css', 'type'=>'text', 'label'=>'CSS (i.e. width="100px"; height="100px")' ],
				[ 'name'=>'hash', 'type'=>'text', 'label'=>'Compensation for scroll on one-page site' ],
				[ 'name'=>'grid', 'type'=>'text', 'label'=>'Grid (eliminates layout)' ],
				[ 'name'=>'data', 'type'=>'text', 'label'=>'data-field' ],
			],
			'content_placeholder' => "\n\n"
		],
		'layout' => [
			'label' => 'Layout',
			'wrap' => true,
			'defaults' => [ 'name'=>'', 'grid'=>'1', 'gap'=>'', 'break'=>'', 'valign'=>'', 'class'=>'', 'track'=>'', 'data'=>'' ],
			'fields' => [
				[ 'name'=>'name', 'type'=>'text', 'label'=>'Name (id)' ],
				[ 'name'=>'grid', 'type'=>'text', 'label'=>'Grid' ],
				[ 'name'=>'gap', 'type'=>'text', 'label'=>'Gap' ],
				[ 'name'=>'break', 'type'=>'select', 'label'=>'Break',
					'choices' => [ '' => 'none', '_4' => '4', '_3' => '3', '_2' => '2', '_1' => '1' ] ],
				[ 'name'=>'valign', 'type'=>'select', 'label'=>'V-Align',
					'choices'=>[ ''=>'none', 'center'=>'center', 'stretch'=>'stretch', 'start'=>'start', 'end'=>'end' ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking' ],
				[ 'name'=>'data', 'type'=>'text', 'label'=>'data-field' ],
			],
			'content_placeholder' => "\n\n"
		],
		'column' => [
			'label' => 'Column',
			'wrap' => true,
			'defaults' => [ 'name'=>'', 'class'=>'', 'order'=>'', 'break'=>'', 'align'=>'', 'valign'=>'', 'h-span'=>'', 'v-span'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'background'=>'/wp-content/uploads/', 'left'=>'50', 'top'=>'50', 'css'=>'', 'hash'=>'', 'gap'=>'', 'data'=>'' ],
			'fields' => [
				[ 'name'=>'name', 'type'=>'text', 'label'=>'Name (id)' ],
				[ 'name'=>'align', 'type'=>'select', 'label'=>'Align',
					'choices'=>[ ''=>'none', 'left'=>'left', 'right'=>'right', 'center'=>'center' ] ],
				[ 'name'=>'valign', 'type'=>'select', 'label'=>'V-Align',
					'choices'=>[ ''=>'none', 'center'=>'center', 'stretch'=>'stretch', 'start'=>'start', 'end'=>'end' ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'h-span', 'type'=>'text', 'label'=>'H-Span' ],
				[ 'name'=>'v-span', 'type'=>'text', 'label'=>'V-Span' ],
				[ 'name'=>'break', 'type'=>'select', 'label'=>'Break',
					'choices' => [ '' => 'none', '_4' => '4', '_3' => '3', '_2' => '2', '_1' => '1' ] ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'id' => 'name (id)', 'custom' => 'custom' ] ],
				[ 'name'=>'gap', 'type'=>'text', 'label'=>'Gap' ],
				[ 'name'=>'background', 'type'=>'text', 'label'=>'Background' ],
				[ 'name'=>'left', 'type'=>'text', 'label'=>'Left %' ],
				[ 'name'=>'top', 'type'=>'text', 'label'=>'Top %' ],
				[ 'name'=>'css', 'type'=>'text', 'label'=>'CSS (i.e. width="100px"; height="100px")' ],
				[ 'name'=>'hash', 'type'=>'text', 'label'=>'Compensation for scroll on one-page site' ],
				[ 'name'=>'data', 'type'=>'text', 'label'=>'data-field' ],
			],
			'content_placeholder' => "\n\n"
		],
		'group' => [
			'label' => 'Group',
			'wrap' => true,
			'defaults' => [ 'size'=>'100', 'class'=>'', 'order'=>'', 'start'=>'', 'end'=>'','track'=>'' ],
			'fields' => [
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => "\n\n"
		],
		'text' => [
			'label' => 'Text',
			'wrap' => true,
			'defaults' => [ 'size'=>'100', 'class'=>'', 'order'=>'', 'start'=>'', 'end'=>'','track'=>'' ],
			'fields' => [
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => "\n\n"
		],
		'image' => [
			'label' => 'Image',
			'wrap' => true,
			'defaults' => [ 'size'=>'100', 'class'=>'', 'order'=>'', 'link'=>'', 'get-biz'=>'', 'new-tab'=>'', 'ada-hidden'=>'false', 'start'=>'', 'end'=>'', 'track'=>'' ],
			'fields' => [
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'link', 'type'=>'text', 'label'=>'URL image links to' ],
				[ 'name'=>'get-biz', 'type'=>'text', 'label'=>'[get-biz info="..."]' ],
				[ 'name'=>'new-tab', 'type'=>'select', 'label'=>'New Tab',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'ada-hidden', 'type'=>'select', 'label'=>'ADA Hidden',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => ""
		],
		'video' => [
			'label' => 'Video',
			'wrap' => false,
			'defaults' => [ 'size'=>'100', 'mobile'=>'100', 'class'=>'', 'order'=>'', 'link'=>'', 'thumb'=>'/wp-content/uploads/', 'start'=>'', 'end'=>'', 'preload'=>'false', 'related'=>'false', 'fullscreen'=>'false', 'controls'=>'true', 'autoplay'=>'false', 'loop'=>'false', 'muted'=>'false', 'begin'=>'', 'track'=>'' ],
			'fields' => [
				[ 'name'=>'link', 'type'=>'text', 'label'=>'URL of video' ],
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Desktop Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'mobile', 'type'=>'select', 'label'=>'Mobile Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'thumb', 'type'=>'text', 'label'=>'Thumbnail' ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'preload', 'type'=>'select', 'label'=>'Preload',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'related', 'type'=>'select', 'label'=>'Show Related',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'fullscreen', 'type'=>'select', 'label'=>'Fullscreen',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'controls', 'type'=>'select', 'label'=>'Show Controls',
					'choices'=>[ 'true'=>'yes', 'false'=>'no'   ] ],
				[ 'name'=>'autoplay', 'type'=>'select', 'label'=>'Autoplay',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'loop', 'type'=>'select', 'label'=>'Loop',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'muted', 'type'=>'select', 'label'=>'Muted',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'begin', 'type'=>'text', 'label'=>'Begin at' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => "\n"
		],
		'button' => [
			'label' => 'Button',
			'wrap' => true,
			'defaults' => [ 'link'=>'', 'size'=>'100', 'align'=>'center', 'class'=>'', 'order'=>'', 'get-biz'=>'', 'new-tab'=>'false', 'fancy'=>'false', 'icon'=>'false', 'top'=>0, 'left'=>0, 'graphic'=>'false', 'graphic-w'=>'40', 'start'=>'', 'end'=>'', 'ada'=>'', 'track'=>'', 'onclick'=>'' ],
			'fields' => [
				[ 'name'=>'link', 'type'=>'text', 'label'=>'URL button links to' ],
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Desktop Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'align', 'type'=>'select', 'label'=>'Align',
					'choices'=>[ ''=>'none', 'left'=>'left', 'right'=>'right', 'center'=>'center' ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'get-biz', 'type'=>'text', 'label'=>'[get-biz info="..."]' ],
				[ 'name'=>'new-tab', 'type'=>'select', 'label'=>'New Tab',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'fancy', 'type'=>'select', 'label'=>'Fancy Button',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'icon', 'type'=>'select', 'label'=>'Icon',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'left', 'type'=>'text', 'label'=>'Left px' ],
				[ 'name'=>'top', 'type'=>'text', 'label'=>'Top px' ],
				[ 'name'=>'graphic', 'type'=>'select', 'label'=>'Graphic',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'graphic-w', 'type'=>'text', 'label'=>'Graphic width' ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end', 'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'ada', 'type'=>'text', 'label'=>'ADA Text', ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => ""
		],
	];

	/*

	$a = shortcode_atts( array(  ), $atts );

		QTags.addButton( 'bp_video', 'video', '   [vid size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url of video" thumb="url of thumb, if not using auto" preload="false, true" class="" related="false, true" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/vid]\n', 'video', 'Video', 1000 );


	*/

	wp_localize_script('battleplan-admin-script', 'BP_QTAGS_CFG', $bp_qtags_cfg);
}



// Set up brand new site
function battleplan_clear_all() {
	battleplan_clear_hvac(true);
}

function battleplan_clear_hvac($all=false) {
	$deleteImgs = array ('testimonials', 'photos', 'graphics', 'logos', 'jobsite-geo');
	$keepPages = array ('home', 'contact-us', 'product-overview');
	$keepElements = array ('site-header', 'widgets');

	$users = get_users( array('fields' => array('ID', 'user_login'),));

	if ( $all == true ) :
		array_push($deleteImgs, 'products');
		if (in_array('product-overview', $keepPages)) unset($keepPages[array_search('product-overview', $keepPages)]);
	endif;

	// Pages + Elements: keep only the core ones, delete everything else (ANY status, incl. drafts).
	foreach ( get_posts( array('post_type'=>'page', 'numberposts'=>-1, 'post_status'=>'any') ) as $post )
		if ( !in_array( $post->post_name, $keepPages) ) wp_delete_post( $post->ID, true );
	foreach ( get_posts( array('post_type'=>'elements', 'numberposts'=>-1, 'post_status'=>'any') ) as $post )
		if ( !in_array( $post->post_name, $keepElements) ) wp_delete_post( $post->ID, true );

	// Every OTHER post type — sweep ALL custom post types, not a hardcoded few, so a copied site
	// can't carry over jobsites, testimonials, galleries, landing pages, posts, WooCommerce data,
	// or any CPT a previous build registered. WP-core infrastructure types are skipped; 'page' and
	// 'elements' are handled above; the HVAC 'products' library survives CLEAR HVAC (CLEAR ALL removes it).
	$keepTypes = apply_filters('bp_clear_keep_post_types', array(
		'page','elements','attachment','nav_menu_item','wp_block','wp_template','wp_template_part',
		'wp_navigation','wp_global_styles','wp_font_family','wp_font_face','revision','customize_changeset',
		'oembed_cache','user_request','custom_css','acf-field-group','acf-field'
	), $all);
	foreach ( get_post_types( array(), 'names' ) as $ptype ) {
		if ( in_array( $ptype, $keepTypes, true ) ) continue;
		if ( $ptype === 'products' && !$all ) continue;   // HVAC product library kept unless CLEAR ALL
		foreach ( get_posts( array('post_type'=>$ptype, 'numberposts'=>-1, 'post_status'=>'any') ) as $post )
			wp_delete_post( $post->ID, true );
	}

	foreach ($users as $user) :
	 	if ($user->user_login !== 'battleplanweb') :
			require_once(ABSPATH.'wp-admin/includes/user.php' );
        		wp_delete_user($user->ID);
		endif;
	endforeach;

	$query = bp_WP_Query('attachment', [
		'post_status'     => 'inherit',
		'posts_per_page'  => -1,
		'mime_type'       => 'image',
		'tax_query'       => [
			'relation' => 'OR',
			[
				'taxonomy' => 'image-categories',
				'terms'    => $deleteImgs,
				'field'    => 'slug'
			],
			[
				'taxonomy' => 'image-categories',
				'operator' => 'NOT EXISTS'
			]
		]
	]);

	if ( $query->have_posts() ) :
		while ( $query->have_posts() ) :
			$query->the_post();
			$keepImg = array( 'logo.png', 'logo.webp', 'site-icon.png', 'site-icon.webp', 'favicon.png', 'favicon.webp');
			if ( !in_array( basename( get_attached_file( get_the_ID() )), $keepImg) ) wp_delete_attachment( get_the_ID(), true );
		endwhile;
		wp_reset_postdata();
	endif;

	// Saved Site Audits (reports, notes, run state, screenshots) — so a copied site starts clean.
	bp_clear_audit_data();

	header("Location: /wp-admin/");
	exit();
}

// Wipe all saved Site-Audit data: the report history, last-run stamp, reviewer notes, any
// in-progress run/stage state, and the on-disk screenshot folders (uploads/bp-audit/*). Self-
// contained (deletes options + files directly) so it works whether or not the audit module file
// is loaded. Called by Clear HVAC/ALL and Launch Site so a copied or freshly-launched site never
// inherits the source site's audits.
function bp_clear_audit_data() {
	foreach ( array(
		'bp_site_audit_ai_history', 'bp_audit_time', 'bp_audit_notes',
		'bp_site_audit_run', 'bp_site_audit_stage', 'bp_site_audit_details'
	) as $opt ) {
		delete_option( $opt );
	}

	$up = wp_upload_dir();
	if ( empty( $up['error'] ) ) {
		$base = trailingslashit( $up['basedir'] ) . 'bp-audit';
		if ( is_dir( $base ) ) {
			foreach ( (array) glob( $base . '/*' ) as $entry ) {
				if ( is_dir( $entry ) ) {
					foreach ( (array) glob( $entry . '/*' ) as $f ) @unlink( $f );
					@rmdir( $entry );
				} else {
					@unlink( $entry );
				}
			}
			@rmdir( $base );
		}
	}
}

function battleplan_launch_site() {
	delete_option('bp_gbp_update');

	// Clear any audits carried over from the site this was copied from (also removes the legacy
	// bp_site_audit_details key this used to delete on its own).
	bp_clear_audit_data();

	updateOption('bp_chron_time', 0);
	updateOption('bp_launch_date', date('Y-m-d'));

	header("Location: /wp-admin/");
	exit();
}


// Read an image's EXIF orientation (1–8; 1 = normal). Handles JPEG via the PHP
// exif extension and WebP by parsing its embedded EXIF chunk directly — Imagick's
// getImageOrientation() does NOT surface WebP orientation.
function bp_image_orientation( $file ) {
	$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

	if ( in_array( $ext, [ 'jpg', 'jpeg', 'tif', 'tiff' ], true ) && function_exists( 'exif_read_data' ) ) {
		$exif = @exif_read_data( $file );
		return ( ! empty( $exif['Orientation'] ) ) ? (int) $exif['Orientation'] : 1;
	}

	if ( $ext === 'webp' ) {
		return bp_webp_orientation( $file );
	}

	return 1;
}

/**
 * Bake an image's EXIF/container orientation into its actual pixels and strip the (now-stale) flag, so
 * it shows upright EVERYWHERE — regardless of whether the viewing browser honors the orientation tag.
 * This is the reliable cross-device fix for iPhone/Android photos that arrive sideways (the phone stores
 * rotated pixels + an orientation flag; not every surface applies the flag).
 *
 * Safe no-op when: the file's missing, it's already upright (orientation 1), or Imagick isn't available.
 * Returns true only when the file was actually rewritten. Rotations mirror the admin "Fix Photo
 * Orientation" tool (values 2–8 per the EXIF spec).
 */
function bp_bake_image_orientation( $file ) {
	if ( ! $file || ! file_exists( $file ) || ! class_exists( 'Imagick' ) ) return false;
	$o = bp_image_orientation( $file );
	if ( $o < 2 ) return false; // 1 = upright, nothing to do
	try {
		$im = new Imagick( $file );
		// Imagick doesn't auto-rotate on load — the buffer holds the raw stored pixels, so rotate/flip
		// explicitly to match the recorded orientation.
		switch ( $o ) {
			case 2: $im->flopImage(); break;
			case 3: $im->rotateImage( 'none', 180 ); break;
			case 4: $im->flipImage(); break;
			case 5: $im->flopImage(); $im->rotateImage( 'none', 270 ); break;
			case 6: $im->rotateImage( 'none', 90 ); break;
			case 7: $im->flopImage(); $im->rotateImage( 'none', 90 ); break;
			case 8: $im->rotateImage( 'none', 270 ); break;
		}
		$im->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
		$im->stripImage();          // drop the now-stale flag + other metadata
		$im->writeImage( $file );
		$im->clear();
		$im->destroy();
		return true;
	} catch ( Exception $e ) {
		return false;
	}
}

// bp_rotate_image_file() lives in includes-site-pulse-messages.php (always loaded, incl. admin-ajax for
// non-admin users) — functions-admin.php only loads for admins, so the rotate endpoint couldn't see it here.

// Walk a WebP file's RIFF chunks, find the EXIF chunk, return its Orientation.
function bp_webp_orientation( $file ) {
	$data = @file_get_contents( $file );
	if ( $data === false || strlen( $data ) < 16 ) return 1;
	if ( substr( $data, 0, 4 ) !== 'RIFF' || substr( $data, 8, 4 ) !== 'WEBP' ) return 1;

	$len = strlen( $data );
	$pos = 12;
	while ( $pos + 8 <= $len ) {
		$fourcc = substr( $data, $pos, 4 );
		$size   = unpack( 'V', substr( $data, $pos + 4, 4 ) )[1];
		$start  = $pos + 8;
		if ( $fourcc === 'EXIF' ) {
			$exif = substr( $data, $start, $size );
			if ( substr( $exif, 0, 6 ) === "Exif\0\0" ) $exif = substr( $exif, 6 );
			return bp_tiff_orientation( $exif );
		}
		$pos = $start + $size + ( $size & 1 ); // chunks are padded to an even length
	}
	return 1;
}

// Parse a TIFF/EXIF block for the Orientation tag (0x0112).
function bp_tiff_orientation( $tiff ) {
	if ( strlen( $tiff ) < 8 ) return 1;
	$bo = substr( $tiff, 0, 2 );
	if ( $bo === 'II' )     $le = true;
	elseif ( $bo === 'MM' ) $le = false;
	else return 1;

	$u16 = function( $off ) use ( $tiff, $le ) {
		if ( $off + 2 > strlen( $tiff ) ) return 0;
		return $le ? unpack( 'v', substr( $tiff, $off, 2 ) )[1] : unpack( 'n', substr( $tiff, $off, 2 ) )[1];
	};
	$u32 = function( $off ) use ( $tiff, $le ) {
		if ( $off + 4 > strlen( $tiff ) ) return 0;
		return $le ? unpack( 'V', substr( $tiff, $off, 4 ) )[1] : unpack( 'N', substr( $tiff, $off, 4 ) )[1];
	};

	$ifd = $u32( 4 );
	if ( $ifd < 8 || $ifd + 2 > strlen( $tiff ) ) return 1;
	$count = $u16( $ifd );
	for ( $i = 0; $i < $count; $i++ ) {
		$entry = $ifd + 2 + $i * 12;
		if ( $entry + 12 > strlen( $tiff ) ) break;
		if ( $u16( $entry ) === 0x0112 ) {
			$val = $u16( $entry + 8 );
			return ( $val >= 1 && $val <= 8 ) ? $val : 1;
		}
	}
	return 1;
}

function bp_geo_taxonomy_cleanup_page() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have permission to access this page.'));
	}

	echo '<div class="wrap"><h1>Jobsite Tools</h1>';

	echo '<div class="card" style="margin-top:20px;padding:20px;">';
	echo '<h2 style="margin-top:0">Taxonomy Cleanup</h2>';
	echo '<p>One sweep to bring a site up to date: refreshes all jobsite tags, rewrites <code>jobsite_geo-services</code> slugs to the canonical <code>service--city-st</code> format (merging duplicates), strips embedded city/state from <code>jobsite_geo-service-types</code> (merging duplicates), and sets each <code>jobsite_geo-service-areas</code> term name to match its slug.<br>New jobsites are created in canonical form automatically, so this is normally a one-time migration.</p>';

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bp_run_taxonomy_cleanup'])) {
		check_admin_referer('bp_taxonomy_cleanup_nonce');
		$dry = isset($_POST['cleanup_dry_run']);

		$sections = bp_geo_run_taxonomy_cleanup($dry);

		$label = $dry ? ' (preview — no changes made)' : '';
		echo '<div class="notice notice-' . ($dry ? 'info' : 'success') . '"><p><strong>Taxonomy cleanup complete' . $label . '.</strong></p></div>';

		foreach ($sections as $sec) {
			echo '<h3 style="margin:18px 0 2px;">' . esc_html($sec['title']) . '</h3>';
			echo '<p style="margin:0 0 6px;color:#555;">' . esc_html($sec['summary']) . '</p>';
			if (!empty($sec['lines'])) {
				echo '<ul style="font-family:monospace;font-size:12px;margin:0;">';
				foreach ($sec['lines'] as $line) echo '<li>' . $line . '</li>';
				echo '</ul>';
			}
		}
	}
	?>
	<form method="post" onsubmit="return this.cleanup_dry_run.checked || confirm('Run the full taxonomy cleanup? Slug rewrites and merges cannot be undone.');">
		<?php wp_nonce_field('bp_taxonomy_cleanup_nonce'); ?>
		<p>
			<label>
				<input type="checkbox" name="cleanup_dry_run" value="1" checked>
				&nbsp;Preview only (dry run) — uncheck to apply changes
			</label>
		</p>
		<?php submit_button('Run Taxonomy Cleanup', 'primary', 'bp_run_taxonomy_cleanup'); ?>
	</form>
	</div>

	<div class="card" style="margin-top:20px;padding:20px;">
	<h2 style="margin-top:0">Audit Review Links</h2>
	<p>Scans all published <code>jobsite_geo</code> posts and checks whether the linked testimonial title matches the jobsite title (using the same key used during matching). Mismatched or broken links are unlinked. Use the dry-run checkbox to preview before applying.</p>
	<?php
	if ( isset( $_POST['bp_audit_review_links'] ) && check_admin_referer( 'bp_audit_review_links_nonce' ) ) {
		$arl_dry = ! empty( $_POST['arl_dry_run'] );
		$arl_log = [];
		$unlinked = 0;
		$checked  = 0;

		$jobsites = get_posts( [
			'post_type'      => 'jobsite_geo',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		foreach ( $jobsites as $jid ) {
			$review_id = get_post_meta( $jid, 'review', true );
			if ( ! $review_id ) continue;
			$checked++;

			$jobsite_title = get_the_title( $jid );
			$testimonial   = get_post( (int) $review_id );

			// Unlink if testimonial no longer exists or isn't published
			if ( ! $testimonial || $testimonial->post_status !== 'publish' ) {
				$arl_log[] = '<span style="color:#c62828;">Broken link:</span> &ldquo;' . esc_html( $jobsite_title ) . '&rdquo; (ID: ' . $jid . ') — review ID ' . $review_id . ' not found or unpublished';
				if ( ! $arl_dry ) delete_post_meta( $jid, 'review' );
				$unlinked++;
				continue;
			}

			// Unlink if sanitized titles don't match
			$j_key = function_exists( 'bp_match_key_from_title' ) ? bp_match_key_from_title( $jobsite_title ) : sanitize_title( $jobsite_title );
			$t_key = function_exists( 'bp_match_key_from_title' ) ? bp_match_key_from_title( $testimonial->post_title ) : sanitize_title( $testimonial->post_title );

			if ( $j_key !== $t_key ) {
				$arl_log[] = '<span style="color:#c62828;">Mismatch:</span> &ldquo;' . esc_html( $jobsite_title ) . '&rdquo; (ID: ' . $jid . ') was linked to &ldquo;' . esc_html( $testimonial->post_title ) . '&rdquo; (ID: ' . $review_id . ')';
				if ( ! $arl_dry ) delete_post_meta( $jid, 'review' );
				$unlinked++;
			}
		}

		if ( $arl_log ) {
			$label = $arl_dry ? 'Would unlink' : 'Unlinked';
			echo '<div class="notice notice-' . ( $arl_dry ? 'warning' : 'success' ) . '"><p><strong>' . $label . ' ' . $unlinked . ' of ' . $checked . ' linked posts:</strong></p><ul style="font-family:monospace;font-size:12px;margin-top:6px;">';
			foreach ( $arl_log as $line ) echo '<li style="margin-bottom:4px;">' . $line . '</li>';
			echo '</ul></div>';
		} elseif ( $checked > 0 ) {
			echo '<div class="notice notice-success"><p>All ' . $checked . ' linked posts have matching titles — nothing to unlink.</p></div>';
		} else {
			echo '<div class="notice notice-info"><p>No jobsite posts with linked reviews found.</p></div>';
		}
	}
	?>
	<form method="post" onsubmit="return this.arl_dry_run.checked || confirm('Unlink all mismatched review connections? This cannot be undone.');">
		<?php wp_nonce_field( 'bp_audit_review_links_nonce' ); ?>
		<p>
			<label>
				<input type="checkbox" name="arl_dry_run" value="1" checked>
				&nbsp;Preview only (dry run) — uncheck to apply changes
			</label>
		</p>
		<?php submit_button( 'Audit Review Links', 'secondary', 'bp_audit_review_links' ); ?>
	</form>
	</div>

	<div class="card" style="margin-top:20px;padding:20px;">
	<h2 style="margin-top:0">Generate Service Intros</h2>
	<p>Uses AI to write a unique, locally-targeted intro for each <code>jobsite_geo-services</code> term and saves it to the term description. Each intro is then used automatically as the page intro on the archive page for that term. Terms that already have a description are skipped unless &ldquo;Force regenerate&rdquo; is checked.</p>
	<?php
	if ( isset( $_POST['bp_generate_service_intros'] ) && check_admin_referer( 'bp_generate_intros_nonce' ) ) {
		$gi_force   = ! empty( $_POST['gi_force'] );
		$gi_batch   = 7;
		$gi_results = [];
		$gi_errors  = [];
		$gi_skipped = 0;

		$all_terms = get_terms( [ 'taxonomy' => 'jobsite_geo-services', 'hide_empty' => false ] );
		if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
			echo '<div class="notice notice-warning"><p>No <code>jobsite_geo-services</code> terms found.</p></div>';
		} else {
			$to_process = [];
			foreach ( $all_terms as $t ) {
				if ( ! $gi_force && get_term_meta( $t->term_id, 'bp_geo_service_intro', true ) && get_term_meta( $t->term_id, 'bp_geo_map_caption', true ) ) {
					$gi_skipped++;
					continue;
				}
				$to_process[] = $t;
			}

			$batch     = array_slice( $to_process, 0, $gi_batch );
			$remaining = max( 0, count( $to_process ) - count( $batch ) );

			@set_time_limit( count( $batch ) * 40 );

			foreach ( $batch as $t ) {
				$result = bp_geo_generate_term_intro( $t->term_id );
				if ( is_wp_error( $result ) ) {
					$gi_errors[] = '<strong>' . esc_html( $t->slug ) . ':</strong> ' . esc_html( $result->get_error_message() );
				} else {
					$gi_results[] = 'Generated: ' . esc_html( $t->slug );
				}
			}

			if ( $gi_results ) {
				echo '<div class="notice notice-success"><p>' . implode( '<br>', $gi_results ) . '</p></div>';
			}
			if ( $gi_errors ) {
				echo '<div class="notice notice-error"><p>' . implode( '<br>', $gi_errors ) . '</p></div>';
			}
			if ( $gi_skipped ) {
				echo '<div class="notice notice-info"><p>Skipped ' . $gi_skipped . ' terms that already have descriptions.</p></div>';
			}
			if ( $remaining > 0 ) {
				echo '<div class="notice notice-warning"><p>' . $remaining . ' terms remaining — submit again to continue.</p></div>';
			} elseif ( empty( $gi_errors ) && count( $batch ) > 0 ) {
				echo '<div class="notice notice-success"><p>All terms processed.</p></div>';
			} elseif ( count( $batch ) === 0 ) {
				echo '<div class="notice notice-info"><p>Nothing to process — all terms already have descriptions. Use &ldquo;Force regenerate&rdquo; to overwrite.</p></div>';
			}
		}
	}
	?>
	<form method="post" onsubmit="return confirm('Generate AI intros for up to 7 service terms? This may take a minute.');">
		<?php wp_nonce_field( 'bp_generate_intros_nonce' ); ?>
		<p>
			<label>
				<input type="checkbox" name="gi_force" value="1">
				&nbsp;Force regenerate &mdash; overwrite existing descriptions
			</label>
		</p>
		<?php submit_button( 'Generate Service Intros', 'secondary', 'bp_generate_service_intros' ); ?>
	</form>
	</div>

	<div class="card" style="margin-top:20px;padding:20px;">
	<h2 style="margin-top:0">Fix Photo Orientation</h2>
	<p>Scans image attachments for a leftover EXIF/container orientation flag — the cause of iPhone/HEIC photos showing sideways on some devices — and bakes the rotation into the pixels, strips the flag, and regenerates thumbnails. <strong>Only images that still carry an active rotation flag are touched</strong>; already-correct images are skipped, so this is safe to re-run. Processes up to 25 fixes per click (submit again to continue). Requires Imagick.</p>
	<?php
	if ( isset( $_POST['bp_fix_orientation'] ) && check_admin_referer( 'bp_fix_orientation_nonce' ) ) {

		if ( ! class_exists( 'Imagick' ) ) {
			echo '<div class="notice notice-error"><p>Imagick is not available on this server — cannot fix orientation.</p></div>';
		} else {
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$fo_dry     = ! empty( $_POST['fo_dry_run'] );
			$fo_batch   = 25;
			$fo_fixed   = 0;
			$fo_flagged = 0;
			$fo_log     = [];

			$ids = get_posts( [
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			] );

			@set_time_limit( 0 );

			foreach ( $ids as $id ) {
				$file = get_attached_file( $id );
				if ( ! $file || ! file_exists( $file ) ) continue;

				// Read the real orientation from the file bytes (works for WebP, whose
				// EXIF chunk Imagick's getImageOrientation() does not surface).
				$o = bp_image_orientation( $file );
				if ( $o < 2 ) continue;

				$fo_flagged++;
				if ( $fo_fixed >= $fo_batch ) continue;

				$fo_log[] = esc_html( basename( $file ) ) . ' <span style="color:#888;">(orientation ' . (int) $o . ')</span>';

				if ( ! $fo_dry ) {
					try {
						$im = new Imagick( $file );
						// Imagick doesn't auto-rotate on load, so the buffer holds the raw
						// stored pixels — rotate explicitly to match the EXIF orientation.
						switch ( $o ) {
							case 2: $im->flopImage(); break;
							case 3: $im->rotateImage( 'none', 180 ); break;
							case 4: $im->flipImage(); break;
							case 5: $im->flopImage(); $im->rotateImage( 'none', 270 ); break;
							case 6: $im->rotateImage( 'none', 90 ); break;
							case 7: $im->flopImage(); $im->rotateImage( 'none', 90 ); break;
							case 8: $im->rotateImage( 'none', 270 ); break;
						}
						$im->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
						$im->stripImage();   // drop the now-stale flag + other metadata
						$im->writeImage( $file );
						$im->clear();
						$im->destroy();

						// Rebuild all subsizes from the corrected full-size file
						$meta = wp_generate_attachment_metadata( $id, $file );
						if ( ! is_wp_error( $meta ) ) wp_update_attachment_metadata( $id, $meta );
					} catch ( Exception $e ) {
						continue;
					}
				}
				$fo_fixed++;
			}

			$remaining = max( 0, $fo_flagged - $fo_fixed );
			$count     = $fo_dry ? $fo_flagged : $fo_fixed;
			$verb      = $fo_dry ? 'Found' : 'Fixed';
			$label     = $fo_dry ? ' (preview — no changes made)' : '';

			if ( $fo_flagged ) {
				echo '<div class="notice notice-' . ( $fo_dry ? 'info' : 'success' ) . '"><p><strong>' . $verb . ' ' . $count . ' image(s) with a rotation flag' . $label . '.</strong>'
					. ( $remaining ? ' ' . $remaining . ' more remaining — submit again to continue.' : '' ) . '</p></div>';
				if ( $fo_log ) {
					echo '<ul style="font-family:monospace;font-size:12px;margin-top:8px;">';
					foreach ( $fo_log as $line ) echo '<li>' . $line . '</li>';
					echo '</ul>';
				}
			} else {
				echo '<div class="notice notice-success"><p>No images carry a rotation flag — nothing to fix.</p></div>';
			}
		}
	}
	?>
	<form method="post" onsubmit="return this.fo_dry_run.checked || confirm('Bake orientation into flagged images and regenerate their thumbnails? This rewrites image files.');">
		<?php wp_nonce_field( 'bp_fix_orientation_nonce' ); ?>
		<p>
			<label>
				<input type="checkbox" name="fo_dry_run" value="1" checked>
				&nbsp;Preview only (dry run) — uncheck to apply changes
			</label>
		</p>
		<?php submit_button( 'Fix Photo Orientation', 'secondary', 'bp_fix_orientation' ); ?>
	</form>
	</div>

	<div class="card" style="margin-top:20px;padding:20px;">
	<h2 style="margin-top:0">Re-caption Photos &amp; Detect Crop Focus</h2>
	<p>Runs AI vision on every <code>jobsite_geo</code> photo to (re)write its caption <em>and</em> detect the subject's focus point, so square thumbnails crop around the subject instead of the center. <strong>Overwrites existing captions.</strong> Processes a batch per click (submit again to continue); already-processed photos are skipped unless &ldquo;Force&rdquo; is checked. Requires an Anthropic API key.</p>
	<?php
	if ( isset( $_POST['bp_recaption_photos'] ) && check_admin_referer( 'bp_recaption_photos_nonce' ) ) {

		if ( ! function_exists( 'bp_ai_alt_available' ) || ! bp_ai_alt_available() ) {
			echo '<div class="notice notice-error"><p>No Anthropic API key configured — cannot generate captions.</p></div>';
		} else {
			$rc_force = ! empty( $_POST['rc_force'] );
			$rc_batch = 8;
			$rc_pairs = [
				'jobsite_photo_1' => 'jobsite_photo_1_alt',
				'jobsite_photo_2' => 'jobsite_photo_2_alt',
				'jobsite_photo_3' => 'jobsite_photo_3_alt',
				'jobsite_photo_4' => 'jobsite_photo_4_alt',
			];

			$jobs = get_posts( [
				'post_type'      => 'jobsite_geo',
				'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page' => -1,
				'fields'         => 'ids',
			] );

			// Flat list of every photo slot that still needs processing.
			$todo = [];
			foreach ( $jobs as $jid ) {
				foreach ( $rc_pairs as $img => $cap ) {
					$aid = (int) get_field( $img, $jid );
					if ( ! $aid ) continue;
					if ( ! $rc_force && get_post_meta( $aid, '_bp_caption_backfilled', true ) ) continue;
					$todo[] = [ 'post' => $jid, 'aid' => $aid, 'cap' => $cap ];
				}
			}

			$batch     = array_slice( $todo, 0, $rc_batch );
			$remaining = max( 0, count( $todo ) - count( $batch ) );

			@set_time_limit( count( $batch ) * 40 );

			$rc_done = [];
			$rc_err  = [];
			foreach ( $batch as $item ) {
				$alt = bp_ai_generate_alt_text( $item['aid'] ); // also stores _bp_focus_position
				if ( is_wp_error( $alt ) || $alt === '' ) {
					$rc_err[] = 'Attachment ' . $item['aid'] . ' (job ' . $item['post'] . '): ' . ( is_wp_error( $alt ) ? esc_html( $alt->get_error_message() ) : 'empty response' );
					continue;
				}
				update_field( $item['cap'], $alt, $item['post'] );                 // visible caption (ACF)
				update_post_meta( $item['aid'], '_wp_attachment_image_alt', $alt ); // attachment alt
				update_post_meta( $item['aid'], '_bp_caption_backfilled', current_time( 'mysql' ) );
				$focus = get_post_meta( $item['aid'], '_bp_focus_position', true );
				$rc_done[] = '&ldquo;' . esc_html( $alt ) . '&rdquo;' . ( $focus ? ' <span style="color:#888;">[focus ' . esc_html( $focus ) . ']</span>' : '' );
			}

			if ( $rc_done ) {
				echo '<div class="notice notice-success"><p><strong>Re-captioned ' . count( $rc_done ) . ' photo(s)' . ( $remaining ? ' — ' . $remaining . ' remaining, submit again to continue.' : '. All done!' ) . '</strong></p><ul style="font-family:monospace;font-size:12px;margin-top:8px;">';
				foreach ( $rc_done as $line ) echo '<li style="margin-bottom:4px;">' . $line . '</li>';
				echo '</ul></div>';
			}
			if ( $rc_err ) {
				echo '<div class="notice notice-error"><p><strong>Errors (' . count( $rc_err ) . '):</strong></p><ul style="font-family:monospace;font-size:12px;">';
				foreach ( $rc_err as $line ) echo '<li>' . $line . '</li>';
				echo '</ul></div>';
			}
			if ( ! $rc_done && ! $rc_err ) {
				echo '<div class="notice notice-info"><p>No photos to process — everything is already captioned. Check &ldquo;Force&rdquo; to redo them all.</p></div>';
			}
		}
	}
	?>
	<form method="post" onsubmit="return confirm('Run AI captioning on this batch of jobsite photos? This overwrites existing captions.');">
		<?php wp_nonce_field( 'bp_recaption_photos_nonce' ); ?>
		<p>
			<label>
				<input type="checkbox" name="rc_force" value="1">
				&nbsp;Force &mdash; re-process photos already done
			</label>
		</p>
		<?php submit_button( 'Re-caption Photos', 'secondary', 'bp_recaption_photos' ); ?>
	</form>
	</div>
	</div>
	<?php
}

add_action('admin_head', function(){
	$logo = get_option('bp_site_logo');
	echo '<style>
		div[data-slug="battleplantheme"] div.theme-screenshot {
			background: url("/wp-content/themes/battleplantheme/screenshot.png") no-repeat 50% 50% !important;
			background-size: contain !important;
		}
		.theme-browser .theme .theme-screenshot.blank,
		div[data-slug="battleplantheme-site"] div.theme-screenshot {
			background: url("/wp-content/uploads/'.$logo.'") no-repeat 50% 50% !important;
			background-size: contain !important;
		}
	</style>';
});



/*--------------------------------------------------------------
# Site Audit — Claude's read of the site
# The audit no longer collects metrics (the chron does that). It crawls the main pages, sends the
# chron's data + page structure to Claude, and stores a DATED report.
# History: get_option('bp_site_audit_ai_history')  — see functions-site-audit-ai.php
--------------------------------------------------------------*/

// Load at top level, not inside the page function: the audit's step endpoint registers
// wp_ajax_bp_audit_step in here, and admin-ajax.php never calls battleplan_site_audit(). Without
// this the Run Audit button's requests would come back "0". file_exists-guarded so a partial
// deploy can't fatal the admin.
$bp_audit_ai_file = get_template_directory() . '/functions-site-audit-ai.php';
if ( file_exists( $bp_audit_ai_file ) ) require_once $bp_audit_ai_file;
unset( $bp_audit_ai_file );

function battleplan_site_audit() {

	echo '<div class="wrap">';
	echo '<h1 style="font-size:28px;font-weight:bold;">Site Audit</h1>';
	echo '<p style="font-size:15px;margin-top:-6px;color:#888;max-width:840px;">Claude reviews the site itself &mdash; the main pages&rsquo; structure, headings, calls to action and copy &mdash; alongside everything the nightly chron already collects (traffic, Search Console, page speed, keywords, Google Business, backlinks, content and Clarity), and reports what&rsquo;s working and what to fix. Every run is kept below, so you can see how the site changes over time.</p>';

	if ( function_exists( 'bp_audit_run_step' ) ) bp_audit_render_runner();

	if ( function_exists( 'bp_audit_ai_render' ) ) {
		bp_audit_ai_render();
	} else {
		echo '<p>The audit module (functions-site-audit-ai.php) is missing.</p>';
	}

	echo '</div>';
}


add_action('wp_ajax_bp_save_audit_field', 'bp_ajax_save_audit_field');
function bp_ajax_save_audit_field() {

    check_ajax_referer('bp_audit_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $key   = sanitize_text_field($_POST['key']   ?? '');
    $date  = sanitize_text_field($_POST['date']  ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');

    if (!$key || !$date) {
        wp_send_json_error('Missing fields');
    }

    $history = get_option('bp_site_audit_details') ?: [];
    $history[$date][$key] = $value;
    update_option('bp_site_audit_details', $history, false);

    wp_send_json_success();
}

add_action('wp_ajax_bp_delete_audit_date', 'bp_ajax_delete_audit_date');
function bp_ajax_delete_audit_date() {
    check_ajax_referer('bp_audit_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $date = sanitize_text_field($_POST['date'] ?? '');
    if (!$date) wp_send_json_error('Missing date');
    $history = get_option('bp_site_audit_details') ?: [];
    unset($history[$date]);
    update_option('bp_site_audit_details', $history, false);
    wp_send_json_success();
}

add_action('wp_ajax_bp_save_audit_note', 'bp_ajax_save_audit_note');
function bp_ajax_save_audit_note() {
    check_ajax_referer('bp_audit_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    $text = sanitize_textarea_field($_POST['note'] ?? '');
    update_option('bp_audit_notes', $text, false);
    wp_send_json_success();
}