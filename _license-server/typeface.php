<?php
/*
 * Battle Plan Web Design — Typeface Endpoint
 *
 * HOST THIS FILE ON YOUR OWN SERVER.
 * DO NOT deploy this to any client site.
 *
 * Place at the URL you set for _BP_TF_URL in functions.php.
 * Example: https://yourserver.com/assets/typeface.php
 *
 * Requires: clients.php in the same directory.
 */

require_once __DIR__ . '/clients.php';

header( 'Content-Type: application/json' );

$db   = trim( $_POST['db']   ?? '' );
$host = trim( $_POST['host'] ?? '' );
$ts   = (int) ( $_POST['ts'] ?? 0 );
$sig  = trim( $_POST['sig']  ?? '' );

// Reject missing data
if ( ! $db || ! $host || ! $ts || ! $sig ) {
	echo json_encode( [ 'stack' => _bf_free_pass( $ts ) ] );
	exit;
}

// Reject stale requests (5 minute window prevents replay attacks)
if ( abs( time() - $ts ) > 300 ) {
	echo json_encode( [ 'stack' => _bf_free_pass( $ts ) ] );
	exit;
}

// Verify the request was signed by a legitimate copy of the theme
$expected = hash_hmac( 'sha256', $db . $host . $ts, _BP_TF_SECRET );
if ( ! hash_equals( $expected, $sig ) ) {
	echo json_encode( [ 'stack' => _bf_free_pass( $ts ) ] );
	exit;
}

// Look up this site by its hashed DB_NAME
$client = $clients[ $db ] ?? null;

if ( $client === null ) {
	// Unknown site — one of your own 130+ sites, give a free pass
	echo json_encode( _bf_response( _BP_TF_STACK, $ts ) );
	exit;
}

// Known licensed client — check their active status
// *** THIS IS THE KILL SWITCH — set 'active' => false in clients.php ***
$stack = $client['active'] ? _BP_TF_STACK : '0';
echo json_encode( _bf_response( $stack, $ts ) );
exit;


// --- Helpers ---

function _bf_free_pass( $ts ) {
	return _bf_response( _BP_TF_STACK, $ts );
}

function _bf_response( $stack, $ts ) {
	$sig = hash_hmac( 'sha256', $stack . $ts, _BP_TF_SECRET );
	return [ 'stack' => $stack, 'sig' => $sig ];
}
