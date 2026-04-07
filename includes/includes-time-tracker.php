<?php
/* Battle Plan Web Design: Time Tracker
 *
 * Tracks time spent on each client site while logged in as battleplanweb.
 * Stores sessions locally in wp_options ('bp_time_log') and fires a
 * fire-and-forget report to the central hub at bp-webdev.com.
 *
 * All open tabs share one session via localStorage (multi-tab safe).
 * Timer pauses after 20 minutes of no browser interaction.
 * Returning after inactivity starts a fresh session (gap not billed).
 * Session starts 1 minute in the past (first minute always billed).
 *
 * Local log format (wp_options 'bp_time_log'):
 *   [ ['id'=>'abc123', 'started'=>'2026-03-18 09:06', 'ended'=>'2026-03-18 09:47'], ... ]
 */

/* ---------------------------------------------------------------
# Enqueue tracker JS (front-end + admin) — battleplanweb only
--------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'bp_time_tracker_enqueue' );
add_action( 'admin_enqueue_scripts', 'bp_time_tracker_enqueue' );
function bp_time_tracker_enqueue() {
	if ( _USER_LOGIN !== 'battleplanweb' ) return;

	$host = $_SERVER['HTTP_HOST'] ?? parse_url( get_bloginfo('url'), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', strtolower( $host ) );

	// Front-end uses bp_enqueue_script helper; admin falls back to direct enqueue
	if ( ! is_admin() ) {
		bp_enqueue_script( 'bp-time-tracker', 'script-time-tracker' );
	} else {
		$dir = get_template_directory() . '/js/';
		$uri = get_template_directory_uri() . '/js/';
		$file = file_exists( $dir . 'script-time-tracker.js' )
			? 'script-time-tracker.js'
			: 'script-time-tracker.min.js';
		if ( file_exists( $dir . $file ) ) {
			wp_enqueue_script( 'bp-time-tracker', $uri . $file, [], _BP_VERSION, true );
		}
	}

	wp_localize_script( 'bp-time-tracker', 'bpTimeTracker', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'bp_time_tracker' ),
		'site'    => $host,
	]);
}

/* ---------------------------------------------------------------
# AJAX: receive ping from JS, update local log, report to hub
--------------------------------------------------------------- */

add_action( 'wp_ajax_bp_time_ping', 'bp_time_ping_ajax' );
function bp_time_ping_ajax() {
	check_ajax_referer( 'bp_time_tracker', 'nonce' );

	if ( _USER_LOGIN !== 'battleplanweb' ) {
		wp_send_json_error( 'unauthorized' );
	}

	$id      = sanitize_text_field( $_POST['session_id'] ?? '' );
	$started = sanitize_text_field( $_POST['started']    ?? '' );
	$ended   = sanitize_text_field( $_POST['ended']      ?? '' );

	if ( ! $id || ! $started || ! $ended ) {
		wp_send_json_error( 'missing_params' );
	}

	// Update local wp_options log
	$log   = get_option( 'bp_time_log', [] );
	$found = false;

	foreach ( $log as &$s ) {
		if ( $s['id'] === $id ) {
			$s['ended'] = $ended;
			$found = true;
			break;
		}
	}
	unset( $s );

	if ( ! $found ) {
		$log[] = [ 'id' => $id, 'started' => $started, 'ended' => $ended ];
	}

	// Cap at 500 sessions per site
	if ( count( $log ) > 500 ) {
		$log = array_slice( $log, -500 );
	}

	update_option( 'bp_time_log', $log, false );

	// Fire-and-forget to central hub
	bp_time_report_to_hub( $id, $started, $ended );

	wp_send_json_success();
}

/* ---------------------------------------------------------------
# Report session to central hub (non-blocking)
--------------------------------------------------------------- */

function bp_time_report_to_hub( $id, $started, $ended ) {
	$host = $_SERVER['HTTP_HOST'] ?? parse_url( get_bloginfo('url'), PHP_URL_HOST );
	$site = preg_replace( '/^www\./', '', strtolower( preg_replace( '/:\d+$/', '', $host ) ) );

$ts     = (string) time();
	$secret = 'Vn8qkM2Z4yHsR1jPwA3tLf7bE6uXpD9c';
	$sig    = hash_hmac( 'sha256', $site . '|' . $id . '|' . $ts, $secret );

	wp_remote_post( 'https://bp-webdev.com/wp-content/timelog-checkin.php', [
		'blocking' => false,
		'timeout'  => 1,
		'body'     => [
			'site'    => $site,
			'id'      => $id,
			'started' => $started,
			'ended'   => $ended,
			'ts'      => $ts,
			'sig'     => $sig,
		],
	]);
}
