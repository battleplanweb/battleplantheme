<?php
/*
 * Battle Plan Web Design — Licensed Client Registry
 *
 * HOST THIS FILE ON YOUR OWN SERVER alongside typeface.php.
 * DO NOT deploy this to any client site.
 *
 * ---------------------------------------------------------------
 * HOW TO ADD A NEW CLIENT
 * ---------------------------------------------------------------
 * 1. Deploy the theme to their site.
 * 2. Visit https://clientsite.com/?bp_tf=setup while logged in
 *    as admin. Copy the hash it shows you.
 * 3. Add an entry to $clients below using that hash as the key.
 * 4. Set 'active' => true.
 *
 * KILL SWITCH: Set 'active' => false for any client.
 * Their site will show a maintenance page within 24 hours.
 * To restore, set 'active' => true — recovers on next cron run.
 * ---------------------------------------------------------------
 *
 * SHARED SECRET — must match _BP_TF_SECRET in functions.php.
 * Change this to a long random string. Keep it private.
 */

define( '_BP_TF_SECRET', 'REPLACE_WITH_A_LONG_RANDOM_STRING' );

/*
 * The font stack returned to licensed sites (looks like a real Google Fonts request).
 * You can leave this as-is.
 */
define( '_BP_TF_STACK', 'Open+Sans:ital,wght@0,400;0,600;0,700' );

/*
 * Client registry.
 * Key   = SHA-256 hash of the client's DB_NAME (shown at ?bp_tf=setup).
 * Value = client details and kill switch.
 */
$clients = [

	// --- EXAMPLE (replace with real client entries) ---
	// 'a1b2c3d4e5f6...' => [
	//     'client' => 'ABC Heating & Cooling',
	//     'domain' => 'abcheating.com',
	//     'since'  => '2026-03-15',
	//     'active' => true,   // ← KILL SWITCH: set to false to take site down
	// ],

];
