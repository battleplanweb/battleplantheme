<?php
/**
 * Site Pulse — SMS "unread message" nudges.
 *
 * One-way escalation for the internal Messages chat: if a message goes unread past a delay, we text
 * the recipient a link back to that conversation (NO message content). They tap it and reply IN the
 * app — there is no inbound-SMS path, so no thread-routing, proxy numbers, or group breakage. Reuses
 * the AI-chat Twilio sender (bp_chat_send_sms) and the Messages read pointers (last_read_message_id).
 *
 * Delay / re-notify / quiet-hours each resolve as: USER override -> ORG default -> SYSTEM default.
 * Coalesced: one text per conversation per unread batch (1 or 8 waiting = one link); it only re-texts
 * on the chosen re-notify cadence, or once they've read and a fresh message arrives.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/*--------------------------------------------------------------
# Cron interval
--------------------------------------------------------------*/

// A 5-minute schedule for the sweep (WP ships nothing shorter than hourly). The 10-minute default
// delay tolerates the coarse cadence fine.
add_filter( 'cron_schedules', function ( $s ) {
	if ( ! isset( $s['site_pulse_5min'] ) ) {
		$s['site_pulse_5min'] = [ 'interval' => 300, 'display' => 'Every 5 Minutes (Site Pulse)' ];
	}
	return $s;
} );

/*--------------------------------------------------------------
# Gates
--------------------------------------------------------------*/

// Twilio must be wired up (shares the AI-chat credentials: constants BP_TWILIO_* or the chat config).
function sp_sms_nudge_twilio_ready(): bool {
	return function_exists( 'bp_chat_send_sms' ) && function_exists( 'bp_chat_twilio' )
		&& bp_chat_twilio( 'sid' ) !== '' && bp_chat_twilio( 'token' ) !== '' && bp_chat_twilio( 'number' ) !== '';
}

// Feature is live only when the org has switched it on AND Twilio is configured.
function sp_sms_nudge_enabled(): bool {
	return site_pulse_get_setting( 'sms_nudge_enabled', '0' ) === '1' && sp_sms_nudge_twilio_ready();
}

/*--------------------------------------------------------------
# Setting resolution  (user override -> org default -> system default)
--------------------------------------------------------------*/

// A blank user-meta value means "follow the company default". Numeric strings ('0' included) are an
// explicit user override.
function sp_sms_user_override( int $uid, string $key ) {
	$v = get_user_meta( $uid, 'sp_sms_' . $key, true );
	return ( $v === '' || $v === false ) ? null : $v;
}

function sp_sms_delay_for( int $uid ): int {
	$u = sp_sms_user_override( $uid, 'delay_min' );
	if ( $u !== null && (int) $u > 0 ) return (int) $u;
	$org = (int) site_pulse_get_setting( 'sms_nudge_delay_min', '0' );
	return $org > 0 ? $org : 10; // system default
}

// Re-notify cadence in minutes; 0 = off (text once, never remind).
function sp_sms_renotify_for( int $uid ): int {
	$u = sp_sms_user_override( $uid, 'renotify_min' );
	if ( $u !== null ) return max( 0, (int) $u ); // user may explicitly choose 0 = off
	return max( 0, (int) site_pulse_get_setting( 'sms_nudge_renotify_min', '0' ) );
}

// Event-notification digest window (minutes) — at most one event text per user per window. Same
// user -> org -> system(60) cascade as the chat delay.
function sp_sms_event_window_for( int $uid ): int {
	$u = sp_sms_user_override( $uid, 'event_window_min' );
	if ( $u !== null && (int) $u > 0 ) return (int) $u;
	$org = (int) site_pulse_get_setting( 'sms_event_window_min', '0' );
	return $org > 0 ? $org : 60;
}

// Is the user currently inside their (or the org's) quiet-hours window? Uses the SITE timezone
// (current_time), which suits a single-region org. A user quiet-hours choice ('1'/'0') overrides the
// org; if the user hasn't chosen, the org default applies.
function sp_sms_quiet_active_for( int $uid ): bool {
	$uChoice = get_user_meta( $uid, 'sp_sms_quiet_enabled', true );
	if ( $uChoice === '1' || $uChoice === '0' ) {
		if ( $uChoice !== '1' ) return false;
		$start = (string) get_user_meta( $uid, 'sp_sms_quiet_start', true );
		$end   = (string) get_user_meta( $uid, 'sp_sms_quiet_end', true );
	} else {
		if ( site_pulse_get_setting( 'sms_nudge_quiet_enabled', '0' ) !== '1' ) return false;
		$start = site_pulse_get_setting( 'sms_nudge_quiet_start', '21:00' );
		$end   = site_pulse_get_setting( 'sms_nudge_quiet_end', '07:00' );
	}
	return sp_sms_time_in_window( $start, $end );
}

// "HH:MM" window test against the current local time; handles overnight wrap (e.g. 21:00 -> 07:00).
function sp_sms_time_in_window( string $start, string $end ): bool {
	$start = trim( $start ); $end = trim( $end );
	if ( $start === '' || $end === '' || $start === $end ) return false;
	$now = (int) current_time( 'Hi' );                 // local HHMM, e.g. 2130 or 715
	$s   = (int) str_replace( ':', '', $start );
	$e   = (int) str_replace( ':', '', $end );
	return ( $s < $e ) ? ( $now >= $s && $now < $e ) : ( $now >= $s || $now < $e );
}

/*--------------------------------------------------------------
# Phone resolution
--------------------------------------------------------------*/

// The number the user CONSENTED on — never the Directory fallback. A2P consent is tied to the number
// shown on the opt-in screen, so texts only ever go to a number the user typed/confirmed there. (The
// Directory cell phone is used only to prefill that screen.)
function sp_sms_phone_for( int $uid ): string {
	$p = trim( (string) get_user_meta( $uid, 'sp_sms_phone', true ) );
	if ( $p === '' ) return '';
	return function_exists( 'bp_chat_normalize_phone' ) ? bp_chat_normalize_phone( $p ) : $p;
}

function sp_sms_directory_phone( int $uid ): string {
	if ( ! $uid ) return '';
	global $wpdb;
	$t = site_pulse_table( 'employees' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) return ''; // directory module unused
	$row = $wpdb->get_var( $wpdb->prepare(
		"SELECT cell_phone FROM $t WHERE user_id = %d AND cell_phone IS NOT NULL AND cell_phone <> '' ORDER BY id LIMIT 1",
		$uid
	) );
	return (string) ( $row ?: '' );
}

// Opted in = the flag AND a recorded consent (the A2P opt-in screen). A legacy opt-in from the old bare
// checkbox has no consent record, so it doesn't count — those users are asked to confirm again.
function sp_sms_user_opted_in( int $uid ): bool {
	return get_user_meta( $uid, 'sp_sms_optin', true ) === '1' && ! empty( get_user_meta( $uid, 'sp_sms_consent', true ) );
}

/*--------------------------------------------------------------
# A2P consent — disclosure, confirmation, audit trail
--------------------------------------------------------------
The opt-in screen must show (Twilio / CTIA): who is texting, what about, that it's optional, message
frequency, msg & data rates, HELP + STOP, and Privacy/Terms links — beside an UNCHECKED checkbox. The
server owns that wording: the screen renders sp_sms_consent_text() and the opt-in handler stores the
same server-built text with the timestamp, IP and number, so the record proves what the user saw.*/

function sp_sms_program_name(): string {
	return site_pulse_get_setting( 'app_name', 'Site Pulse' );
}

function sp_sms_company_name(): string {
	return function_exists( 'bp_business_name' ) ? bp_business_name() : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

// "MyRovin (Rovin Restaurants)" — or just the one name when the app is named after the company.
function sp_sms_sender_label(): string {
	$app = sp_sms_program_name();
	$co  = sp_sms_company_name();
	return ( $co === '' || stripos( $app, $co ) !== false || stripos( $co, $app ) !== false ) ? $app : "{$app} ({$co})";
}

function sp_sms_legal_urls(): array {
	return apply_filters( 'site_pulse_sms_legal_urls', [
		'privacy' => get_privacy_policy_url() ?: home_url( '/privacy-policy/' ),
		'terms'   => home_url( '/terms-conditions/' ),
	] );
}

function sp_sms_consent_text(): string {
	return sprintf(
		'By checking this box, I agree to receive automated text alerts from %s at the mobile number above about activity in my account, such as reports, approvals, reminders and messages. Text alerts are optional; every alert still appears in the app. Message frequency varies. Message & data rates may apply. Reply HELP for help or STOP to cancel at any time.',
		sp_sms_sender_label()
	);
}

function sp_sms_confirmation_text(): string {
	return sprintf( "%s: You're signed up for text alerts. Msg frequency varies. Msg & data rates may apply. Reply HELP for help, STOP to cancel.", sp_sms_program_name() );
}

function sp_sms_help_text(): string {
	$info  = function_exists( 'customer_info' ) ? customer_info() : [];
	$phone = trim( ( $info['area'] ?? '' ) . '-' . ( $info['phone'] ?? '' ), '-' );
	return sprintf( '%s alerts from %s. Manage alerts in the app: %s%s. Msg & data rates may apply. Reply STOP to cancel.',
		sp_sms_program_name(), sp_sms_company_name(), home_url( '/site-pulse-dashboard/' ), $phone !== '' ? " or call {$phone}" : '' );
}

// Everything the opt-in screen needs. Also handed to admins (Settings → Notifications) so they can
// preview/screenshot the screen for the Twilio campaign before texting is live.
function sp_sms_optin_screen_data(): array {
	$urls = sp_sms_legal_urls();
	return [
		'program'      => sp_sms_program_name(),
		'consent_text' => sp_sms_consent_text(),
		'privacy_url'  => $urls['privacy'],
		'terms_url'    => $urls['terms'],
	];
}

// Append-only audit trail (newest last, capped) — opt-ins, opt-outs and STOPs, with how they happened.
function sp_sms_consent_log( int $uid, string $action, string $phone, string $via ): void {
	$log   = get_user_meta( $uid, 'sp_sms_consent_log', true );
	$log   = is_array( $log ) ? $log : [];
	$log[] = [ 'action' => $action, 'phone' => $phone, 'via' => $via, 'at' => current_time( 'mysql' ) ];
	update_user_meta( $uid, 'sp_sms_consent_log', array_slice( $log, -25 ) );
}

function sp_sms_opt_out( int $uid, string $via ): void {
	update_user_meta( $uid, 'sp_sms_optin', '0' );
	delete_user_meta( $uid, 'sp_sms_consent' );
	sp_sms_consent_log( $uid, 'opt_out', (string) get_user_meta( $uid, 'sp_sms_phone', true ), $via );
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . site_pulse_table( 'sms_queue' ) . ' WHERE user_id = %d AND sent_at IS NULL', $uid ) );
}

// Users whose consented number matches (STOP replies and carrier unsubscribes arrive as a number).
function sp_sms_users_by_phone( string $e164 ): array {
	if ( $e164 === '' ) return [];
	return array_map( 'intval', get_users( [ 'meta_key' => 'sp_sms_phone', 'meta_value' => $e164, 'fields' => 'ID' ] ) );
}

/*--------------------------------------------------------------
# Inbound replies from staff (STOP / HELP / anything else)
--------------------------------------------------------------
The alert number shares the AI-chat inbound webhook. A reply from a number that belongs to a Site Pulse
user is handled HERE so it never opens a customer chat or gets an AI answer. Twilio's Advanced Opt-Out
also blocks STOPped numbers at the carrier; mirroring it keeps the app's switch honest.*/

add_filter( 'bp_chat_inbound_sms_intercept', 'sp_sms_inbound_intercept', 10, 3 );
function sp_sms_inbound_intercept( $handled, string $from, string $body ) {
	if ( $handled !== null ) return $handled;
	$uids = sp_sms_users_by_phone( $from );
	if ( ! $uids ) return null;

	$kw = strtoupper( trim( $body ) );
	if ( in_array( $kw, [ 'STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'OPTOUT', 'REVOKE' ], true ) ) {
		foreach ( $uids as $uid ) sp_sms_opt_out( $uid, 'sms_stop' );
		return bp_chat_twiml_response(); // Twilio sends the standard opt-out confirmation
	}
	if ( in_array( $kw, [ 'START', 'UNSTOP', 'YES' ], true ) ) {
		// Re-subscribing by keyword is honoured by the carrier; in the app they re-confirm on the opt-in
		// screen (so the consent record stays tied to that screen). Point them there.
		bp_chat_send_sms( $from, sprintf( '%s: To turn text alerts back on, open the app and tap Text alerts in your notifications: %s', sp_sms_program_name(), home_url( '/site-pulse-dashboard/' ) ) );
		return bp_chat_twiml_response();
	}
	if ( in_array( $kw, [ 'HELP', 'INFO' ], true ) ) {
		bp_chat_send_sms( $from, sp_sms_help_text() );
		return bp_chat_twiml_response();
	}
	// Any other reply: this line doesn't take replies. Tell them once an hour, not on every text.
	$k = 'sp_sms_noreply_' . md5( $from );
	if ( ! get_transient( $k ) ) {
		set_transient( $k, 1, HOUR_IN_SECONDS );
		bp_chat_send_sms( $from, sprintf( '%s: This number only sends alerts, so replies aren\'t read. Open the app to respond: %s Reply HELP for help, STOP to cancel.', sp_sms_program_name(), home_url( '/site-pulse-dashboard/' ) ) );
	}
	return bp_chat_twiml_response();
}

// Twilio refused a send because the number already replied STOP (error 21610) — e.g. the STOP reached
// Twilio but not our webhook. Switch the app off to match.
add_action( 'bp_sms_recipient_unsubscribed', function ( string $to ) {
	foreach ( sp_sms_users_by_phone( $to ) as $uid ) {
		if ( get_user_meta( $uid, 'sp_sms_optin', true ) === '1' ) sp_sms_opt_out( $uid, 'carrier_21610' );
	}
} );

/*--------------------------------------------------------------
# The sweep
--------------------------------------------------------------*/

add_action( 'site_pulse_sms_nudge_sweep', 'sp_sms_nudge_sweep' );
function sp_sms_nudge_sweep(): void {
	if ( ! sp_sms_nudge_enabled() ) return;
	global $wpdb;
	$cp = site_pulse_table( 'conversation_participants' );
	$m  = site_pulse_table( 'messages' );

	// Every participant with an unread message from someone else (newer than their read pointer), in a
	// conversation they haven't hidden. oldest/newest unread ids + the oldest's timestamp drive the
	// delay check and the coalescing.
	$rows = $wpdb->get_results(
		"SELECT p.conversation_id, p.user_id, p.last_read_message_id,
		        p.sms_nudged_message_id, p.sms_nudged_at,
		        MIN(msg.id) AS oldest_unread_id, MAX(msg.id) AS newest_unread_id,
		        MIN(msg.created_at) AS oldest_unread_at
		 FROM $cp p
		 JOIN $m msg
		   ON msg.conversation_id = p.conversation_id
		  AND msg.id > p.last_read_message_id
		  AND msg.sender_id <> p.user_id
		 WHERE p.hidden_at IS NULL
		 GROUP BY p.conversation_id, p.user_id, p.last_read_message_id, p.sms_nudged_message_id, p.sms_nudged_at",
		ARRAY_A
	) ?: [];
	if ( ! $rows ) return;

	$nowTs = strtotime( current_time( 'mysql' ) );
	$sent  = 0;

	foreach ( $rows as $r ) {
		if ( $sent >= 50 ) break;                            // safety cap per sweep (keeps cron snappy)
		$uid = (int) $r['user_id'];

		if ( ! sp_sms_user_opted_in( $uid ) ) continue;
		$phone = sp_sms_phone_for( $uid );
		if ( $phone === '' ) continue;

		// Old enough? (measured from the OLDEST still-unread message)
		$ageMin = ( $nowTs - strtotime( (string) $r['oldest_unread_at'] ) ) / 60;
		if ( $ageMin < sp_sms_delay_for( $uid ) ) continue;

		// Coalesce: a nudge is "current" while it points past their read pointer. If we've already
		// texted about still-unread messages, don't text again for extra ones — only re-notify on the
		// chosen cadence.
		$nudgedMid  = (int) $r['sms_nudged_message_id'];
		$lastRead   = (int) $r['last_read_message_id'];
		if ( $nudgedMid > $lastRead ) {
			$renotify = sp_sms_renotify_for( $uid );
			if ( $renotify <= 0 ) continue;                  // re-notify off
			$sinceMin = $r['sms_nudged_at'] ? ( $nowTs - strtotime( (string) $r['sms_nudged_at'] ) ) / 60 : PHP_INT_MAX;
			if ( $sinceMin < $renotify ) continue;           // too soon to remind again
		}

		if ( sp_sms_quiet_active_for( $uid ) ) continue;     // inside quiet hours -> defer to a later sweep

		if ( sp_sms_send_nudge( $uid, (int) $r['conversation_id'] ) ) {
			$wpdb->update( $cp,
				[ 'sms_nudged_message_id' => (int) $r['newest_unread_id'], 'sms_nudged_at' => current_time( 'mysql' ) ],
				[ 'conversation_id' => (int) $r['conversation_id'], 'user_id' => $uid ]
			);
			$sent++;
		}
	}
}

// Compose + send the one-way nudge. Content-free: sender/thread label + a deep link to that thread.
function sp_sms_send_nudge( int $uid, int $conv_id ): bool {
	$phone = sp_sms_phone_for( $uid );
	if ( $phone === '' ) return false;

	$app  = site_pulse_get_setting( 'app_name', 'Site Pulse' );
	$who  = '';
	if ( function_exists( 'sp_msg_conversation_meta' ) ) {
		$meta = sp_msg_conversation_meta( $conv_id, $uid );
		$title = trim( (string) ( $meta['title'] ?? '' ) );
		if ( $title !== '' ) $who = ! empty( $meta['is_group'] ) ? ( 'in ' . $title ) : ( 'from ' . $title );
	}
	$link = home_url( '/site-pulse-dashboard/?sp_panel=messages&sp_conv=' . $conv_id );
	$body = trim( sprintf( 'New message %s on %s: %s', $who, $app, $link ) );

	return (bool) bp_chat_send_sms( $phone, $body );
}

/*--------------------------------------------------------------
# AJAX — the viewer's own text-alert settings
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_sms_get_settings', 'site_pulse_ajax_sms_get_settings' );
function site_pulse_ajax_sms_get_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid = site_pulse_effective_user_id();

	$saved   = trim( (string) get_user_meta( $uid, 'sp_sms_phone', true ) );
	$dirPhone = sp_sms_directory_phone( $uid );

	wp_send_json_success( sp_sms_optin_screen_data() + [
		'available'      => sp_sms_nudge_enabled(),                       // org on + Twilio ready
		'optin'          => sp_sms_user_opted_in( $uid ),
		// Old bare-checkbox opt-in with no consent record — asked to confirm on the new screen.
		'legacy'         => get_user_meta( $uid, 'sp_sms_optin', true ) === '1' && ! sp_sms_user_opted_in( $uid ),
		'phone'          => $saved,
		'directory_phone'=> $dirPhone,                                     // prefills the opt-in screen when no saved number
		'delay_min'      => (string) get_user_meta( $uid, 'sp_sms_delay_min', true ),
		'renotify_min'   => (string) get_user_meta( $uid, 'sp_sms_renotify_min', true ),
		'event_window_min' => (string) get_user_meta( $uid, 'sp_sms_event_window_min', true ),
		'quiet_enabled'  => (string) get_user_meta( $uid, 'sp_sms_quiet_enabled', true ), // '', '1', '0'
		'quiet_start'    => (string) get_user_meta( $uid, 'sp_sms_quiet_start', true ),
		'quiet_end'      => (string) get_user_meta( $uid, 'sp_sms_quiet_end', true ),
		// Org defaults, for "Company default (…)" labels.
		'org_delay'      => (int) site_pulse_get_setting( 'sms_nudge_delay_min', '0' ) ?: 10,
		'org_renotify'   => (int) site_pulse_get_setting( 'sms_nudge_renotify_min', '0' ),
		'org_event_window' => (int) site_pulse_get_setting( 'sms_event_window_min', '0' ) ?: 60,
		'org_quiet'      => site_pulse_get_setting( 'sms_nudge_quiet_enabled', '0' ) === '1'
			? [ 'start' => site_pulse_get_setting( 'sms_nudge_quiet_start', '21:00' ), 'end' => site_pulse_get_setting( 'sms_nudge_quiet_end', '07:00' ) ]
			: null,
	] );
}

// The opt-in screen's "Turn on text alerts". Consent is recorded ONLY here, ONLY with the box ticked,
// and only for the user themself — an impersonating god can't consent on someone's behalf.
add_action( 'wp_ajax_site_pulse_sms_optin', 'site_pulse_ajax_sms_optin' );
function site_pulse_ajax_sms_optin(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid = site_pulse_effective_user_id();
	if ( $uid !== (int) get_current_user_id() ) {
		wp_send_json_error( [ 'message' => 'Only the account owner can agree to text alerts.' ] );
	}
	if ( ! sp_sms_nudge_enabled() ) {
		wp_send_json_error( [ 'message' => 'Text alerts aren’t turned on for this app.' ] );
	}
	if ( empty( $_POST['consent'] ) ) {
		wp_send_json_error( [ 'message' => 'Check the box to agree to text alerts.' ] );
	}
	$raw   = trim( (string) wp_unslash( $_POST['phone'] ?? '' ) );
	$phone = function_exists( 'bp_chat_normalize_phone' ) ? bp_chat_normalize_phone( $raw ) : '';
	if ( $phone === '' ) {
		wp_send_json_error( [ 'message' => "That doesn't look like a valid US mobile number." ] );
	}

	// Confirmation text first: it's required by A2P, and a failed send means a bad number — don't
	// record an opt-in we can't deliver to.
	if ( ! sp_sms_send_raw( $phone, sp_sms_confirmation_text() ) ) {
		wp_send_json_error( [ 'message' => "We couldn't send a text to that number. Check it and try again." ] );
	}

	update_user_meta( $uid, 'sp_sms_phone', $phone );
	update_user_meta( $uid, 'sp_sms_optin', '1' );
	update_user_meta( $uid, 'sp_sms_consent', [
		'at'    => current_time( 'mysql' ),
		'phone' => $phone,
		'ip'    => function_exists( 'bp_chat_client_ip' ) ? bp_chat_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ),
		'ua'    => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
		'text'  => sp_sms_consent_text(),   // exactly what the screen showed
		'urls'  => sp_sms_legal_urls(),
	] );
	sp_sms_consent_log( $uid, 'opt_in', $phone, 'optin_screen' );

	wp_send_json_success( [ 'message' => 'Text alerts are on. We just sent a confirmation text.' ] );
}

// "Turn off text alerts" in the app (the same as replying STOP).
add_action( 'wp_ajax_site_pulse_sms_optout', 'site_pulse_ajax_sms_optout' );
function site_pulse_ajax_sms_optout(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	sp_sms_opt_out( site_pulse_effective_user_id(), 'app' );
	wp_send_json_success( [ 'message' => 'Text alerts are off.' ] );
}

// Personal delivery preferences only. Opt-in/out and the number go through the endpoints above, so the
// consent record can't be changed without passing through the opt-in screen.
add_action( 'wp_ajax_site_pulse_sms_save_settings', 'site_pulse_ajax_sms_save_settings' );
function site_pulse_ajax_sms_save_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid = site_pulse_effective_user_id();

	// Personal overrides — blank string clears the override (= follow the company default).
	$delay = isset( $_POST['delay_min'] ) ? trim( (string) $_POST['delay_min'] ) : '';
	update_user_meta( $uid, 'sp_sms_delay_min', $delay === '' ? '' : (string) max( 1, (int) $delay ) );

	$reno = isset( $_POST['renotify_min'] ) ? trim( (string) $_POST['renotify_min'] ) : '';
	update_user_meta( $uid, 'sp_sms_renotify_min', $reno === '' ? '' : (string) max( 0, (int) $reno ) );

	$win = isset( $_POST['event_window_min'] ) ? trim( (string) $_POST['event_window_min'] ) : '';
	update_user_meta( $uid, 'sp_sms_event_window_min', $win === '' ? '' : (string) max( 5, (int) $win ) );

	$q = isset( $_POST['quiet_enabled'] ) ? trim( (string) $_POST['quiet_enabled'] ) : '';
	update_user_meta( $uid, 'sp_sms_quiet_enabled', in_array( $q, [ '0', '1' ], true ) ? $q : '' );
	update_user_meta( $uid, 'sp_sms_quiet_start', sp_sms_clean_hhmm( (string) ( $_POST['quiet_start'] ?? '' ) ) );
	update_user_meta( $uid, 'sp_sms_quiet_end',   sp_sms_clean_hhmm( (string) ( $_POST['quiet_end'] ?? '' ) ) );

	wp_send_json_success( [ 'message' => 'Text alert settings saved.' ] );
}

// "HH:MM" 24h validator; returns '' if not a valid time.
function sp_sms_clean_hhmm( string $v ): string {
	$v = trim( $v );
	return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ? $v : '';
}

/*--------------------------------------------------------------
# Event notifications — SMS as a channel in the notification matrix
--------------------------------------------------------------
When an event's routing has the 'Text' channel ticked for a recipient's group,
site_pulse_dispatch_notification() calls sp_sms_enqueue_event() per recipient. We
QUEUE rather than send, so the digest sweep can coalesce a burst into ONE text
per person per window ("2× new report, 1× expense to approve — open the app").*/

function sp_sms_send_raw( string $phone, string $body ): bool {
	return function_exists( 'bp_chat_send_sms' ) ? (bool) bp_chat_send_sms( $phone, $body ) : false;
}

// Best-effort deep link for an event's digest — routes to the panel that shows it.
function sp_sms_event_link( string $event ): string {
	$base = home_url( '/site-pulse-dashboard/' );
	$panel = '';
	if ( 0 === strpos( $event, 'report' ) )        $panel = 'reports-review';
	elseif ( 0 === strpos( $event, 'mileage' ) )   $panel = 'approve-expense';
	elseif ( 0 === strpos( $event, 'expense' ) )   $panel = 'approve-expense';
	elseif ( 0 === strpos( $event, 'action' ) )    $panel = 'action-items';
	elseif ( 0 === strpos( $event, 'survey' ) )    $panel = 'surveys';
	return $panel ? ( $base . '?sp_panel=' . $panel ) : $base;
}

// Queue one event text for a recipient (only if the org feature + their opt-in + a phone are all set).
function sp_sms_enqueue_event( int $uid, string $event, string $message = '', int $related_id = 0, string $related_type = '' ): void {
	if ( ! $uid || ! sp_sms_nudge_enabled() ) return;
	if ( ! sp_sms_user_opted_in( $uid ) || sp_sms_phone_for( $uid ) === '' ) return;
	global $wpdb;
	$wpdb->insert( site_pulse_table( 'sms_queue' ), [
		'user_id'    => $uid,
		'event'      => substr( $event, 0, 64 ),
		'message'    => $message,
		'link'       => sp_sms_event_link( $event ),
		'created_at' => current_time( 'mysql' ),
	] );
}

// Coalescing digest sweep — runs on the same 5-min cron as the chat nudge. One text per person per
// window, summarising everything queued; respects opt-in, phone, and quiet hours.
add_action( 'site_pulse_sms_nudge_sweep', 'sp_sms_event_digest_sweep' );
function sp_sms_event_digest_sweep(): void {
	if ( ! sp_sms_nudge_enabled() ) return;
	global $wpdb;
	$q = site_pulse_table( 'sms_queue' );

	$wpdb->query( "DELETE FROM $q WHERE sent_at IS NOT NULL AND sent_at < DATE_SUB(NOW(), INTERVAL 7 DAY)" ); // housekeeping

	$uids  = $wpdb->get_col( "SELECT DISTINCT user_id FROM $q WHERE sent_at IS NULL" ) ?: [];
	$nowTs = strtotime( current_time( 'mysql' ) );

	foreach ( $uids as $uid ) {
		$uid = (int) $uid;
		if ( ! sp_sms_user_opted_in( $uid ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $q WHERE user_id = %d AND sent_at IS NULL", $uid ) ); // opted out → drop pending
			continue;
		}
		$phone = sp_sms_phone_for( $uid );
		if ( $phone === '' ) continue;
		if ( sp_sms_quiet_active_for( $uid ) ) continue;                 // inside quiet hours → hold

		$last = get_user_meta( $uid, 'sp_sms_last_event_at', true );      // one text per window
		if ( $last && ( $nowTs - strtotime( (string) $last ) ) / 60 < sp_sms_event_window_for( $uid ) ) continue;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, event, link FROM $q WHERE user_id = %d AND sent_at IS NULL ORDER BY id", $uid ), ARRAY_A );
		if ( ! $rows ) continue;

		if ( sp_sms_send_raw( $phone, sp_sms_compose_digest( $rows ) ) ) {
			$ids = implode( ',', array_map( fn( $r ) => (int) $r['id'], $rows ) );
			$wpdb->query( $wpdb->prepare( "UPDATE $q SET sent_at = %s WHERE id IN ($ids)", current_time( 'mysql' ) ) );
			update_user_meta( $uid, 'sp_sms_last_event_at', current_time( 'mysql' ) );
		}
	}
}

// "MyRovin: 2× New report submitted, 1× Expense ready to approve. Open: <link>" — counts by event,
// links to the single relevant panel when all share one, else the app home.
function sp_sms_compose_digest( array $rows ): string {
	$app    = site_pulse_get_setting( 'app_name', 'Site Pulse' );
	$labels = function_exists( 'site_pulse_notification_events' ) ? site_pulse_notification_events() : [];
	$counts = [];
	$links  = [];
	foreach ( $rows as $r ) {
		$ev = (string) $r['event'];
		$counts[ $ev ] = ( $counts[ $ev ] ?? 0 ) + 1;
		if ( ! empty( $r['link'] ) ) $links[ (string) $r['link'] ] = true;
	}
	$parts = [];
	foreach ( $counts as $ev => $n ) {
		$label   = isset( $labels[ $ev ] ) ? (string) $labels[ $ev ] : ucwords( str_replace( [ '_', ':' ], ' ', $ev ) );
		$parts[] = $n . '× ' . $label;
	}
	$link = ( count( $links ) === 1 ) ? array_key_first( $links ) : home_url( '/site-pulse-dashboard/' );
	return trim( sprintf( '%s: %s. Open: %s', $app, implode( ', ', $parts ), $link ) );
}

/*--------------------------------------------------------------
# AJAX — org defaults (Settings -> Notifications)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_admin_get_sms_nudge', 'site_pulse_ajax_admin_get_sms_nudge' );
function site_pulse_ajax_admin_get_sms_nudge(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_notifications' ) ) return;
	wp_send_json_success( [
		'enabled'       => site_pulse_get_setting( 'sms_nudge_enabled', '0' ) === '1',
		'delay_min'     => (int) site_pulse_get_setting( 'sms_nudge_delay_min', '0' ) ?: 10,
		'renotify_min'  => (int) site_pulse_get_setting( 'sms_nudge_renotify_min', '0' ),
		'event_window_min' => (int) site_pulse_get_setting( 'sms_event_window_min', '0' ) ?: 60,
		'quiet_enabled' => site_pulse_get_setting( 'sms_nudge_quiet_enabled', '0' ) === '1',
		'quiet_start'   => site_pulse_get_setting( 'sms_nudge_quiet_start', '21:00' ),
		'quiet_end'     => site_pulse_get_setting( 'sms_nudge_quiet_end', '07:00' ),
		'twilio_ready'  => sp_sms_nudge_twilio_ready(),
		'optin_preview' => sp_sms_optin_screen_data(),  // "Preview opt-in screen" — for the A2P screenshots
	] );
}

add_action( 'wp_ajax_site_pulse_admin_save_sms_nudge', 'site_pulse_ajax_admin_save_sms_nudge' );
function site_pulse_ajax_admin_save_sms_nudge(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_notifications' ) ) return;

	// The on/off switch lives in Global notifications (saved via site_pulse_admin_save_setting).
	site_pulse_set_setting( 'sms_nudge_delay_min', (string) max( 1, (int) ( $_POST['delay_min'] ?? 10 ) ) );
	site_pulse_set_setting( 'sms_nudge_renotify_min', (string) max( 0, (int) ( $_POST['renotify_min'] ?? 0 ) ) );
	site_pulse_set_setting( 'sms_event_window_min', (string) max( 5, (int) ( $_POST['event_window_min'] ?? 60 ) ) );
	site_pulse_set_setting( 'sms_nudge_quiet_enabled', ! empty( $_POST['quiet_enabled'] ) ? '1' : '0' );
	site_pulse_set_setting( 'sms_nudge_quiet_start', sp_sms_clean_hhmm( (string) ( $_POST['quiet_start'] ?? '' ) ) ?: '21:00' );
	site_pulse_set_setting( 'sms_nudge_quiet_end',   sp_sms_clean_hhmm( (string) ( $_POST['quiet_end'] ?? '' ) ) ?: '07:00' );

	wp_send_json_success( [
		'message'    => 'Text alert settings saved.',
		'twilio_ready' => sp_sms_nudge_twilio_ready(),
	] );
}
