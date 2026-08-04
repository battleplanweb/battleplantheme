<?php
/* Battle Plan Web Design — AI Chat: SMS + Conversation Store

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Database Schema
# Conversation Store Helpers
# Phone + Text Helpers
# Inbound SMS Webhook
# Twilio Request Validation
# SMS-side Conversation Runner
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Database Schema
----------------------------------------------------------------
Two tables: one row per conversation, one row per visible message.
This is what lets a chat that began in the browser continue over
SMS after the visitor leaves — the transcript lives server-side,
keyed by the visitor's phone number once captured.
--------------------------------------------------------------*/

if ( ! defined( 'BP_CHAT_DB_VERSION' ) ) define( 'BP_CHAT_DB_VERSION', '1.2' );

function bp_chat_table( string $name ): string {
	return $GLOBALS['wpdb']->prefix . 'bp_chat_' . $name;
}

function bp_chat_install_db(): void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	$sql  = "CREATE TABLE " . bp_chat_table('conversations') . " (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		cid varchar(64) DEFAULT NULL,
		phone varchar(20) DEFAULT NULL,
		name varchar(190) DEFAULT NULL,
		email varchar(190) DEFAULT NULL,
		channel varchar(10) NOT NULL DEFAULT 'web',
		status varchar(20) NOT NULL DEFAULT 'active',
		opted_out tinyint(1) NOT NULL DEFAULT 0,
		consent_at datetime DEFAULT NULL,
		consent_ip varchar(45) DEFAULT NULL,
		human_at datetime DEFAULT NULL,
		lead_sent_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY cid (cid),
		KEY phone (phone)
	) $charset;";

	$sql .= "CREATE TABLE " . bp_chat_table('messages') . " (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		conversation_id bigint(20) NOT NULL,
		role varchar(16) NOT NULL,
		channel varchar(10) NOT NULL DEFAULT 'web',
		body text NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY conversation_id (conversation_id)
	) $charset;";

	dbDelta( $sql );
	update_option( 'bp_chat_db_version', BP_CHAT_DB_VERSION );
}

add_action( 'init', function () {
	if ( get_option( 'bp_chat_db_version' ) !== BP_CHAT_DB_VERSION ) {
		bp_chat_install_db();
	}
} );


/*--------------------------------------------------------------
# Conversation Store Helpers
--------------------------------------------------------------*/

function bp_chat_conv_get_or_create_by_cid( string $cid ): array {
	global $wpdb;
	$now = current_time( 'mysql' );

	if ( $cid !== '' ) {
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . bp_chat_table('conversations') . " WHERE cid = %s ORDER BY id DESC LIMIT 1", $cid
		), ARRAY_A );
		if ( $row ) return $row;
	}

	$wpdb->insert( bp_chat_table('conversations'), [
		'cid'        => $cid ?: null,
		'channel'    => 'web',
		'status'     => 'active',
		'created_at' => $now,
		'updated_at' => $now,
	] );
	return bp_chat_conv_get( (int) $wpdb->insert_id );
}

function bp_chat_conv_get( int $id ): array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . bp_chat_table('conversations') . " WHERE id = %d", $id
	), ARRAY_A );
	return $row ?: [];
}

/** Most recent non-opted-out conversation for a phone number. */
function bp_chat_conv_get_by_phone( string $phone ): array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . bp_chat_table('conversations') . "
		 WHERE phone = %s ORDER BY id DESC LIMIT 1", $phone
	), ARRAY_A );
	return $row ?: [];
}

function bp_chat_conv_update( int $id, array $fields ): void {
	global $wpdb;
	$fields['updated_at'] = current_time( 'mysql' );
	$wpdb->update( bp_chat_table('conversations'), $fields, [ 'id' => $id ] );
}

function bp_chat_msg_add( int $conv_id, string $role, string $body, string $channel ): void {
	global $wpdb;
	$wpdb->insert( bp_chat_table('messages'), [
		'conversation_id' => $conv_id,
		'role'            => $role,
		'channel'         => $channel,
		'body'            => $body,
		'created_at'      => current_time( 'mysql' ),
	] );
	bp_chat_conv_update( $conv_id, [] ); // touch updated_at
}

function bp_chat_msg_count( int $conv_id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . bp_chat_table('messages') . " WHERE conversation_id = %d", $conv_id
	) );
}

/**
 * Conversation history as Claude messages. Consecutive same-role turns are
 * merged (a customer can fire two texts before the AI replies, which would
 * otherwise break the API's required user/assistant alternation).
 */
function bp_chat_history( int $conv_id, int $limit = 40 ): array {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT role, body FROM " . bp_chat_table('messages') . "
		 WHERE conversation_id = %d ORDER BY id ASC", $conv_id
	), ARRAY_A ) ?: [];

	$out = [];
	foreach ( $rows as $r ) {
		$role = $r['role'] === 'assistant' ? 'assistant' : 'user';
		$body = (string) $r['body'];
		if ( $out && $out[ count( $out ) - 1 ]['role'] === $role ) {
			$out[ count( $out ) - 1 ]['content'] .= "\n" . $body;
		} else {
			$out[] = [ 'role' => $role, 'content' => $body ];
		}
	}

	// Drop any leading assistant turn so the array starts with 'user'.
	while ( $out && $out[0]['role'] !== 'user' ) array_shift( $out );

	if ( count( $out ) > $limit ) $out = array_slice( $out, -$limit );
	return $out;
}

/**
 * Append any client-sent web turns not yet stored, keeping the server
 * transcript in sync with the browser so an SMS handoff has full context.
 */
function bp_chat_sync_web_history( int $conv_id, array $messages ): void {
	$stored = bp_chat_msg_count( $conv_id );
	for ( $i = $stored; $i < count( $messages ); $i++ ) {
		$m = $messages[ $i ];
		$role = ( ( $m['role'] ?? '' ) === 'assistant' ) ? 'assistant' : 'user';
		$body = trim( (string) ( $m['content'] ?? '' ) );
		if ( $body !== '' ) bp_chat_msg_add( $conv_id, $role, $body, 'web' );
	}
}


/*--------------------------------------------------------------
# Phone + Text Helpers
--------------------------------------------------------------*/

/**
 * Best-effort normalize a US number the visitor typed into E.164.
 * Returns '' if it doesn't look like a usable number.
 */
function bp_chat_normalize_phone( string $raw ): string {
	$digits = preg_replace( '/[^0-9]/', '', $raw );
	if ( $digits === '' ) return '';
	if ( strlen( $digits ) === 10 ) return '+1' . $digits;
	if ( strlen( $digits ) === 11 && $digits[0] === '1' ) return '+' . $digits;
	if ( strpos( $raw, '+' ) === 0 && strlen( $digits ) >= 11 ) return '+' . $digits;
	return '';
}

/** The opening SMS that moves a web chat onto text. */
function bp_chat_text_opening( array $customer, string $name ): string {
	$company = $customer['name'] ?? get_bloginfo( 'name' );
	$hi      = $name !== '' ? "Hi {$name}, " : 'Hi, ';
	return $hi . "it's {$company} - continuing our chat by text so we can help even if you step away. "
		. "Reply anytime. Msg frequency varies; msg & data rates may apply. Reply STOP to unsubscribe, HELP for help.";
}


/*--------------------------------------------------------------
# Inbound SMS Webhook
----------------------------------------------------------------
Twilio POSTs here when someone texts the business number. Handles
compliance keywords, distinguishes the contractor from a customer,
and lets the AI continue a customer's conversation over SMS.
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'bp-chat/v1', '/sms-inbound', [
		'methods'             => 'POST',
		'callback'            => 'bp_chat_handle_inbound_sms',
		'permission_callback' => '__return_true', // verified via Twilio signature below
	] );
} );

function bp_chat_handle_inbound_sms( WP_REST_Request $req ) {
	// Reject anything not actually signed by Twilio.
	if ( ! bp_chat_validate_twilio_signature( $req ) ) {
		return bp_chat_twiml_response( '', 403 );
	}

	$from = bp_chat_normalize_phone( (string) $req->get_param( 'From' ) );
	$body = trim( (string) $req->get_param( 'Body' ) );
	if ( $from === '' || $body === '' ) return bp_chat_twiml_response();

	$o        = bp_chat_config();
	$customer = function_exists( 'customer_info' ) ? customer_info() : [];

	// A text FROM the contractor's own phone is a reply to the customer they're
	// currently handling — relay it. Handled BEFORE keyword parsing so the
	// contractor's words are never swallowed as STOP/HELP.
	if ( $from === bp_chat_reply_number( $o ) ) {
		return bp_chat_handle_contractor_reply( $body, $customer, $o );
	}
	// A notification-only staff number texting in is not a customer lead — ignore
	// it (only the reply line drives the two-way relay).
	if ( in_array( $from, bp_chat_notify_numbers( $o ), true ) ) {
		return bp_chat_twiml_response();
	}

	// Compliance keywords (Twilio also enforces STOP at the carrier level; we
	// mirror it so we never try to reply to an opted-out number).
	$kw = strtoupper( $body );
	if ( in_array( $kw, [ 'STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT' ], true ) ) {
		$conv = bp_chat_conv_get_by_phone( $from );
		if ( $conv ) bp_chat_conv_update( (int) $conv['id'], [ 'opted_out' => 1, 'status' => 'closed' ] );
		return bp_chat_twiml_response(); // let Twilio send its standard opt-out confirmation
	}
	if ( in_array( $kw, [ 'START', 'UNSTOP', 'YES' ], true ) ) {
		$conv = bp_chat_conv_get_by_phone( $from );
		if ( $conv ) bp_chat_conv_update( (int) $conv['id'], [ 'opted_out' => 0, 'status' => 'active' ] );
		return bp_chat_twiml_response();
	}
	if ( $kw === 'HELP' || $kw === 'INFO' ) {
		$company = $customer['name'] ?? get_bloginfo( 'name' );
		bp_chat_send_sms( $from, "{$company}: reply with your question and we'll help. Reply STOP to opt out." );
		return bp_chat_twiml_response();
	}

	// Find the customer's conversation (started on the web), or open a fresh
	// SMS-only one for a cold inbound text.
	$conv = bp_chat_conv_get_by_phone( $from );
	if ( ! $conv ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert( bp_chat_table('conversations'), [
			'phone' => $from, 'channel' => 'sms', 'status' => 'active',
			'created_at' => $now, 'updated_at' => $now,
		] );
		$conv = bp_chat_conv_get( (int) $wpdb->insert_id );
	}
	if ( ! empty( $conv['opted_out'] ) ) return bp_chat_twiml_response();

	bp_chat_msg_add( (int) $conv['id'], 'user', $body, 'sms' );

	// This customer is now the one the contractor's replies will route to.
	bp_chat_active_conv_set( (int) $conv['id'] );

	// Forward the customer's message to the contractor so they can jump in.
	$label = bp_chat_conv_label( $conv );
	bp_chat_forward_to_contractor( "{$label}: {$body}", $o );

	// If the contractor has already taken this conversation over, stay hands-off
	// — their reply will come back through the contractor branch above. Until
	// then, the AI covers so the customer is never left hanging.
	if ( ! empty( $conv['human_at'] ) ) {
		return bp_chat_twiml_response();
	}

	$reply = bp_chat_run_sms( $conv, $customer, $o );
	if ( $reply !== '' ) bp_chat_send_sms( $from, $reply );

	return bp_chat_twiml_response(); // we already replied via the REST API
}

/*--------------------------------------------------------------
# Human Takeover — SMS relay between contractor and customer
----------------------------------------------------------------
Single-number relay: the contractor texts the business number and we
route it to the customer they're currently handling (the most recent
active hand-off). This reliably handles one live customer at a time —
the planned upgrade to per-customer dedicated numbers removes that
limit. The "active conversation" pointer is a single site option
because each site has one contractor.
--------------------------------------------------------------*/

/** The conversation the contractor's next reply routes to. */
function bp_chat_active_conv_set( int $id ): void { update_option( 'bp_chat_active_conv', $id, false ); }
function bp_chat_active_conv_get(): int { return (int) get_option( 'bp_chat_active_conv', 0 ); }

/** A short, human label for a conversation (name if known, else last 4 digits). */
function bp_chat_conv_label( array $conv ): string {
	$name = trim( (string) ( $conv['name'] ?? '' ) );
	if ( $name !== '' ) return $name;
	$phone = (string) ( $conv['phone'] ?? '' );
	return $phone !== '' ? 'Customer ' . substr( $phone, -4 ) : 'Customer';
}

/** Text the single reply/takeover line (ongoing relay + replies to the contractor). */
function bp_chat_forward_to_contractor( string $message, array $o ): bool {
	$to = bp_chat_reply_number( $o );
	if ( $to === '' ) return false;
	return bp_chat_send_sms( $to, $message );
}

/** Broadcast to every notification number (lead alerts + one-time handoff notices). */
function bp_chat_broadcast_to_contractors( string $message, array $o ): bool {
	$ok = false;
	foreach ( bp_chat_notify_numbers( $o ) as $num ) {
		if ( bp_chat_send_sms( $num, $message ) ) $ok = true;
	}
	return $ok;
}

/**
 * A compact transcript ("Customer: …" / "AI: …") for the hand-off summary.
 * Long conversations must not overflow the SMS body (carriers cap ~1600 chars
 * and the send fails outright), so each line is trimmed and, when $max_chars is
 * set, only the most recent lines that fit the budget are kept (older ones are
 * summarized as omitted — the full transcript still lives in the DB).
 */
function bp_chat_transcript_text( int $conv_id, int $limit = 20, int $max_chars = 0 ): string {
	$msgs  = bp_chat_history( $conv_id, $limit );
	$lines = [];
	foreach ( $msgs as $m ) {
		$who  = $m['role'] === 'assistant' ? 'AI' : 'Customer';
		$body = trim( (string) $m['content'] );
		if ( strlen( $body ) > 200 ) $body = substr( $body, 0, 200 ) . '...';
		$lines[] = $who . ': ' . $body;
	}

	if ( $max_chars <= 0 ) return implode( "\n", $lines );

	// Keep the most recent lines within the budget (recent context matters most
	// for a takeover); flag that earlier lines were dropped.
	$out = [];
	$len = 0;
	for ( $i = count( $lines ) - 1; $i >= 0; $i-- ) {
		$add = strlen( $lines[ $i ] ) + 1;
		if ( $out && $len + $add > $max_chars ) {
			array_unshift( $out, '...(earlier messages omitted)' );
			break;
		}
		array_unshift( $out, $lines[ $i ] );
		$len += $add;
	}
	return implode( "\n", $out );
}

/**
 * Relay a contractor's SMS reply to the customer they're currently handling.
 * The first such reply flips the conversation to human control, so the AI
 * stops auto-answering from that point on.
 */
function bp_chat_handle_contractor_reply( string $body, array $customer, array $o ) {
	$conv_id = bp_chat_active_conv_get();
	$conv    = $conv_id ? bp_chat_conv_get( $conv_id ) : [];

	if ( ! $conv || empty( $conv['phone'] ) ) {
		bp_chat_forward_to_contractor( "No active text conversation to reply to right now.", $o );
		return bp_chat_twiml_response();
	}
	if ( ! empty( $conv['opted_out'] ) ) {
		bp_chat_forward_to_contractor( bp_chat_conv_label( $conv ) . " has opted out of texts, so I can't send that.", $o );
		return bp_chat_twiml_response();
	}

	// First contractor reply = human takeover; AI goes silent for this convo.
	if ( empty( $conv['human_at'] ) ) {
		bp_chat_conv_update( (int) $conv['id'], [ 'human_at' => current_time( 'mysql' ) ] );
	}

	bp_chat_send_sms( (string) $conv['phone'], $body );
	bp_chat_msg_add( (int) $conv['id'], 'assistant', $body, 'sms' );
	return bp_chat_twiml_response();
}

/** Empty TwiML (we send replies via the REST API, not via TwiML). */
function bp_chat_twiml_response( string $xml = '', int $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: text/xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?><Response>' . $xml . '</Response>';
	exit;
}


/*--------------------------------------------------------------
# Twilio Request Validation
----------------------------------------------------------------
Verifies the X-Twilio-Signature so a public webhook can't be spoofed.
HMAC-SHA1 of (exact webhook URL + sorted POST params) with the auth
token, base64-encoded. Override the URL with BP_CHAT_WEBHOOK_URL if
the site sits behind a proxy that rewrites host/scheme. Define
BP_CHAT_SKIP_TWILIO_VALIDATION=true only for local debugging.
--------------------------------------------------------------*/

function bp_chat_validate_twilio_signature( WP_REST_Request $req ): bool {
	if ( defined( 'BP_CHAT_SKIP_TWILIO_VALIDATION' ) && BP_CHAT_SKIP_TWILIO_VALIDATION ) return true;

	$token = bp_chat_twilio( 'token' );
	$sig   = $req->get_header( 'x_twilio_signature' );
	if ( ! $token || ! $sig ) return false;

	$url = defined( 'BP_CHAT_WEBHOOK_URL' ) && BP_CHAT_WEBHOOK_URL
		? BP_CHAT_WEBHOOK_URL
		: rest_url( 'bp-chat/v1/sms-inbound' );

	$params = $req->get_body_params();
	ksort( $params );
	$data = $url;
	foreach ( $params as $k => $v ) { $data .= $k . $v; }

	$expected = base64_encode( hash_hmac( 'sha1', $data, $token, true ) );
	return hash_equals( $expected, $sig );
}


/*--------------------------------------------------------------
# SMS-side Conversation Runner
----------------------------------------------------------------
Mirror of bp_chat_run() but driven by the stored transcript instead
of a browser payload. Reuses the shared system prompt, tool set, and
tool handler from includes-ai-chat.php.
--------------------------------------------------------------*/

function bp_chat_run_sms( array $conv, array $customer, array $o ): string {
	$o['company_knowledge'] = bp_chat_company_knowledge( $o );

	$system = bp_chat_system_prompt( $o, $customer )
		. "\n\nYou are now continuing this conversation over SMS text messages. "
		. "Keep every reply to one or two short sentences — texts must be brief.";

	$tools            = bp_chat_tools();
	$messages         = bp_chat_history( (int) $conv['id'] );
	$lead_sent        = false;
	$consent_requested= false;

	$resp = bp_chat_call_claude( $system, $messages, $tools );
	if ( is_wp_error( $resp ) ) { error_log( 'BP CHAT SMS error: ' . $resp->get_error_message() ); return ''; }

	if ( ( $resp['stop_reason'] ?? '' ) === 'tool_use' ) {
		$results = [];
		foreach ( (array) ( $resp['content'] ?? [] ) as $block ) {
			if ( ( $block['type'] ?? '' ) !== 'tool_use' ) continue;
			$results[] = [
				'type'        => 'tool_result',
				'tool_use_id' => $block['id'] ?? '',
				'content'     => bp_chat_handle_tool_block( $block, $customer, $o, $conv, 'sms', $lead_sent, $consent_requested ),
			];
		}
		$messages[] = [ 'role' => 'assistant', 'content' => $resp['content'] ];
		$messages[] = [ 'role' => 'user', 'content' => $results ];
		$resp = bp_chat_call_claude( $system, $messages, $tools );
		if ( is_wp_error( $resp ) ) { error_log( 'BP CHAT SMS error: ' . $resp->get_error_message() ); return ''; }
	}

	$reply = '';
	foreach ( (array) ( $resp['content'] ?? [] ) as $block ) {
		if ( ( $block['type'] ?? '' ) === 'text' ) $reply .= $block['text'];
	}
	$reply = trim( $reply );
	if ( $reply !== '' ) bp_chat_msg_add( (int) $conv['id'], 'assistant', $reply, 'sms' );
	return $reply;
}
