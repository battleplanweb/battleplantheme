<?php
/* Battle Plan Web Design — Site Pulse: personal notification preferences

Settings → Notifications is now for EVERYONE. Each person picks, per kind of notification, how they
want to hear about it: App (the in-app bell), Push, Email, Text. Only channels the app-wide switches
allow are offered. Defaults: App + Push on, Email off, Text on only once they've opted in to texts.

WHO gets notified is still the admin routing (site_pulse_dispatch_notification + the "Who gets
notified" grid, god / System Admin only). HOW each recipient hears is this file: every notification
goes through site_pulse_deliver(), which reads the recipient's preferences.

Stored per user in user meta `sp_notif_prefs` = { category: { app, push, email, sms } } — only what
they've changed; everything else falls back to the defaults.
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/** God or System Admin (the effective user, so "view as" respects the person being viewed). */
function site_pulse_can_manage_global_notifications( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	if ( ! $user_id ) return false;
	if ( site_pulse_is_god( $user_id ) ) return true;
	$p = site_pulse_get_user_profile( $user_id );
	$r = $p ? site_pulse_get_role( (int) $p['role_id'] ) : null;
	return $r && ( $r['slug'] ?? '' ) === 'system-admin';
}

/** The kinds of notification a person can set, in display order. 'channels' limits what applies. */
function site_pulse_notif_categories(): array {
	$mod = fn( $slug ) => ! function_exists( 'site_pulse_module_on' ) || site_pulse_module_on( $slug );
	$all = [
		'messages'     => [ 'label' => 'Chat messages',      'desc' => 'Someone sends you a message or posts in your group.', 'on' => true,
		                    'channels' => [ 'push', 'email', 'sms' ] ],   // unread messages already badge the Messages tab
		'action_items' => [ 'label' => 'Action items',       'desc' => 'An action item is assigned to you, needs follow-up, or is resolved.', 'on' => true ],
		'reports'      => [ 'label' => 'Reports',            'desc' => 'A report is submitted or one of yours is due.', 'on' => $mod( 'reports' ) ],
		'surveys'      => [ 'label' => 'Comment cards',      'desc' => 'A customer comment card comes in.', 'on' => $mod( 'surveys' ) ],
		'expenses'     => [ 'label' => 'Mileage & expenses', 'desc' => 'Expense reports, approvals and period deadlines.', 'on' => $mod( 'mileage' ) ],
		'notes'        => [ 'label' => 'Shared notes',       'desc' => 'A note shared with you is changed or deleted.', 'on' => $mod( 'notes' ) ],
		'content'      => [ 'label' => 'Website content',    'desc' => 'A page or post is sent to you for review, or one of yours is published.', 'on' => $mod( 'content' ) ],
		'schedules'    => [ 'label' => 'Schedules',          'desc' => 'Time-off requests, coverage and shift changes.', 'on' => $mod( 'schedules' ) ],
		'other'        => [ 'label' => 'Everything else',    'desc' => 'Any other alert from the app.', 'on' => true ],
	];
	foreach ( $all as $k => &$c ) $c['channels'] = $c['channels'] ?? [ 'app', 'push', 'email', 'sms' ];
	unset( $c );
	return $all;
}

/** Which category a notification type / routing event belongs to. */
function site_pulse_notif_category_for( string $type ): string {
	if ( $type === 'message' || 0 === strpos( $type, 'message' ) )       return 'messages';
	if ( 0 === strpos( $type, 'action' ) )                                return 'action_items';
	if ( 0 === strpos( $type, 'report' ) )                                return 'reports';
	if ( 0 === strpos( $type, 'survey' ) )                                return 'surveys';
	if ( preg_match( '/^(period_|mileage_|expense_)/', $type ) )         return 'expenses';
	if ( 0 === strpos( $type, 'note_' ) )                                 return 'notes';
	if ( 0 === strpos( $type, 'schedules_' ) )                            return 'schedules';
	if ( 0 === strpos( $type, 'content_' ) )                              return 'content';
	return 'other';
}

/** App-wide switches (Settings → Notifications → Global, god / System Admin). App is always available. */
function site_pulse_notif_globals(): array {
	return [
		'app'   => true,
		'push'  => site_pulse_get_setting( 'push_enabled', '0' ) === '1',
		'email' => site_pulse_get_setting( 'notifications_email_enabled', '0' ) === '1',
		'sms'   => site_pulse_get_setting( 'sms_nudge_enabled', '0' ) === '1'
		           && function_exists( 'sp_sms_nudge_twilio_ready' ) && sp_sms_nudge_twilio_ready(),
	];
}

/** One person's effective preferences (saved choices over defaults), every category × channel. */
function site_pulse_user_notif_prefs( int $user_id ): array {
	$saved   = get_user_meta( $user_id, 'sp_notif_prefs', true );
	$saved   = is_array( $saved ) ? $saved : [];
	$texting = function_exists( 'sp_sms_user_opted_in' ) && sp_sms_user_opted_in( $user_id );
	$out = [];
	foreach ( site_pulse_notif_categories() as $cat => $c ) {
		$def = [ 'app' => 1, 'push' => 1, 'email' => 0, 'sms' => $texting ? 1 : 0 ];
		$row = is_array( $saved[ $cat ] ?? null ) ? $saved[ $cat ] : [];
		foreach ( $def as $ch => $d ) $out[ $cat ][ $ch ] = array_key_exists( $ch, $row ) ? (int) ! empty( $row[ $ch ] ) : $d;
	}
	return $out;
}

/** Should $user_id get $type over $channel? Combines the app switch, the category's channels and their choice. */
function site_pulse_user_wants( int $user_id, string $type, string $channel ): bool {
	$g = site_pulse_notif_globals();
	if ( empty( $g[ $channel ] ) ) return false;
	$cat  = site_pulse_notif_category_for( $type );
	$cats = site_pulse_notif_categories();
	if ( ! in_array( $channel, $cats[ $cat ]['channels'] ?? [], true ) ) return false;
	return ! empty( site_pulse_user_notif_prefs( $user_id )[ $cat ][ $channel ] );
}

/**
 * The one way to notify a person: App (bell row), Push, Email and Text, each per their preferences.
 * $on_dashboard (from the admin routing) always creates the row, since the banner IS a flagged bell row.
 */
function site_pulse_deliver( int $user_id, string $type, string $message, int $related_id = 0, string $related_type = '', bool $on_dashboard = false ): void {
	if ( ! $user_id ) return;
	if ( $on_dashboard || site_pulse_user_wants( $user_id, $type, 'app' ) ) site_pulse_notify( $user_id, $type, $message, $related_id, $related_type, $on_dashboard );
	if ( site_pulse_user_wants( $user_id, $type, 'push' ) && function_exists( 'site_pulse_push_send' ) ) site_pulse_push_send( $user_id );
	if ( site_pulse_user_wants( $user_id, $type, 'email' ) && function_exists( 'site_pulse_notify_email' ) ) site_pulse_notify_email( $user_id, $message );
	if ( site_pulse_user_wants( $user_id, $type, 'sms' ) && function_exists( 'sp_sms_enqueue_event' ) ) sp_sms_enqueue_event( $user_id, $type, $message, $related_id, $related_type );
}


/*--------------------------------------------------------------
# AJAX — my preferences
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_get_my_notif_prefs', 'site_pulse_ajax_get_my_notif_prefs' );
function site_pulse_ajax_get_my_notif_prefs(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid = site_pulse_effective_user_id();
	if ( ! $uid ) wp_send_json_error( [ 'message' => 'Not signed in.' ] );

	$cats = [];
	foreach ( site_pulse_notif_categories() as $k => $c ) {
		if ( empty( $c['on'] ) ) continue;
		$cats[] = [ 'key' => $k, 'label' => $c['label'], 'desc' => $c['desc'], 'channels' => $c['channels'] ];
	}
	wp_send_json_success( [
		'categories' => $cats,
		'prefs'      => site_pulse_user_notif_prefs( $uid ),
		'globals'    => site_pulse_notif_globals(),
		'texting'    => function_exists( 'sp_sms_user_opted_in' ) && sp_sms_user_opted_in( $uid ),
		'is_admin'   => site_pulse_can_manage_global_notifications( $uid ),
		'read_only'  => site_pulse_is_impersonating(),
	] );
}

add_action( 'wp_ajax_site_pulse_save_my_notif_prefs', 'site_pulse_ajax_save_my_notif_prefs' );
function site_pulse_ajax_save_my_notif_prefs(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( site_pulse_is_impersonating() ) wp_send_json_error( [ 'message' => 'Viewing as someone else — their preferences are read-only.' ] );
	$uid = get_current_user_id();
	if ( ! $uid ) wp_send_json_error( [ 'message' => 'Not signed in.' ] );

	$in = json_decode( wp_unslash( (string) ( $_POST['prefs'] ?? '' ) ), true );
	if ( ! is_array( $in ) ) wp_send_json_error( [ 'message' => 'Invalid data.' ] );

	$saved = get_user_meta( $uid, 'sp_notif_prefs', true );
	$saved = is_array( $saved ) ? $saved : [];
	foreach ( site_pulse_notif_categories() as $cat => $c ) {
		if ( ! is_array( $in[ $cat ] ?? null ) ) continue;              // not posted (hidden category) → keep
		foreach ( [ 'app', 'push', 'email', 'sms' ] as $ch ) {
			if ( array_key_exists( $ch, $in[ $cat ] ) ) $saved[ $cat ][ $ch ] = empty( $in[ $cat ][ $ch ] ) ? 0 : 1;
		}
	}
	update_user_meta( $uid, 'sp_notif_prefs', $saved );
	wp_send_json_success( [ 'prefs' => site_pulse_user_notif_prefs( $uid ) ] );
}
