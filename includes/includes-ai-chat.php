<?php
require_once get_template_directory() . '/prompts/prompts-ai-chat.php';
require_once get_template_directory() . '/includes/includes-ai-chat-sms.php';
require_once get_template_directory() . '/includes/includes-ai-chat-reviews.php';

/* Battle Plan Web Design — AI Chat (Phase 1: chat widget → text the lead)

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Config Helpers
# Asset Enqueueing + Widget Markup
# REST Endpoint
# Claude Call (with lead-capture tool)
# Lead Handoff (Twilio SMS)
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Config Helpers
--------------------------------------------------------------*/

function bp_chat_config(): array {
	return get_option( 'ai_chat', [] );
}

/**
 * Resolve the company knowledge block. Order of precedence:
 *   1. Inline `company_knowledge` in the site_option (short blurbs).
 *   2. An explicit `knowledge_file` (path relative to the child theme, or absolute).
 *   3. A conventional file in the child theme: ai-chat-knowledge.md / .txt.
 * Lets long, formatted knowledge live in its own file instead of functions-site.php.
 */
function bp_chat_company_knowledge( array $o ): string {
	static $cache = null;
	if ( $cache !== null ) return $cache;

	$inline = trim( (string) ( $o['company_knowledge'] ?? '' ) );
	if ( $inline !== '' ) return $cache = $inline;

	$candidates = [];
	if ( ! empty( $o['knowledge_file'] ) ) {
		$f = (string) $o['knowledge_file'];
		$is_abs = ( $f[0] ?? '' ) === '/' || preg_match( '#^[A-Za-z]:#', $f );
		$candidates[] = $is_abs ? $f : get_stylesheet_directory() . '/' . ltrim( $f, '/' );
	}
	$candidates[] = get_stylesheet_directory() . '/ai-chat-knowledge.md';
	$candidates[] = get_stylesheet_directory() . '/ai-chat-knowledge.txt';

	foreach ( $candidates as $path ) {
		if ( $path && is_readable( $path ) ) {
			$txt = file_get_contents( $path );
			if ( $txt !== false && trim( $txt ) !== '' ) return $cache = trim( $txt );
		}
	}
	return $cache = '';
}

/**
 * Anthropic key — reuse the framework helper if present (functions-ai-alt.php),
 * else read the same constants directly.
 */
function bp_chat_api_key(): string {
	if ( function_exists( 'bp_ai_alt_api_key' ) ) return bp_ai_alt_api_key();
	if ( defined( 'BP_ANTHROPIC_API_KEY' ) && BP_ANTHROPIC_API_KEY ) return BP_ANTHROPIC_API_KEY;
	if ( defined( 'ANTHROPIC_API_KEY' )    && ANTHROPIC_API_KEY )    return ANTHROPIC_API_KEY;
	return '';
}

/**
 * Map the per-site `model` choice to a model ID. Defaults to Opus.
 */
function bp_chat_model(): string {
	$choice = strtolower( trim( (string) ( bp_chat_config()['model'] ?? '' ) ) );
	if ( $choice === 'haiku' ) return 'claude-haiku-4-5-20251001';
	return 'claude-opus-4-8';
}

function bp_chat_is_opus(): bool {
	return strpos( bp_chat_model(), 'opus' ) === 0;
}

/**
 * Resolve a Twilio credential. Prefers a wp-config.php constant (the
 * secure home for the secret Auth Token — out of the DB and out of the
 * theme repo), falling back to the site_option. $key = sid|token|number.
 */
function bp_chat_twilio( string $key ): string {
	$const = [
		'sid'    => 'BP_TWILIO_SID',
		'token'  => 'BP_TWILIO_TOKEN',
		'number' => 'BP_TWILIO_NUMBER',
	][ $key ] ?? '';
	if ( $const && defined( $const ) && constant( $const ) ) return (string) constant( $const );
	return (string) ( bp_chat_config()[ 'twilio_' . $key ] ?? '' );
}

/**
 * The widget can run only when both an Anthropic key and a Twilio
 * destination + credentials are configured.
 */
function bp_chat_ready(): bool {
	return bp_chat_api_key() !== ''
		&& bp_chat_twilio( 'sid' ) !== '' && bp_chat_twilio( 'token' ) !== ''
		&& bp_chat_twilio( 'number' ) !== ''
		&& ! empty( bp_chat_config()['contractor_sms'] );
}


/*--------------------------------------------------------------
# Asset Enqueueing + Widget Markup
--------------------------------------------------------------*/

add_action( 'wp_enqueue_scripts', 'bp_chat_enqueue_assets' );
function bp_chat_enqueue_assets(): void {
	if ( is_admin() || ! bp_chat_ready() ) return;

	$o        = bp_chat_config();
	$customer = function_exists( 'customer_info' ) ? customer_info() : [];

	$script_file = file_exists( get_template_directory() . '/js/script-ai-chat.min.js' )
		? '/js/script-ai-chat.min.js'
		: '/js/script-ai-chat.js';

	wp_enqueue_script(
		'bp-ai-chat',
		get_template_directory_uri() . $script_file,
		[],
		filemtime( get_template_directory() . $script_file ),
		true
	);

	$style_path = get_template_directory() . '/style-ai-chat.css';
	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'bp-ai-chat',
			get_template_directory_uri() . '/style-ai-chat.css',
			[],
			filemtime( $style_path )
		);
	}

	$company = $customer['name'] ?? get_bloginfo( 'name' );
	$default_greeting = "Hi! 👋 Have a question or need service? Ask me anything and I'll help.";

	$default_text_consent = "{$company} can text you about your request. By tapping Yes, you agree to receive "
		. "SMS messages from {$company} about your inquiry and appointment. Message frequency varies. "
		. "Message & data rates may apply. Reply STOP to unsubscribe or HELP for help.";

	wp_localize_script( 'bp-ai-chat', 'bpChat', [
		'restUrl'     => esc_url_raw( rest_url( 'bp-chat/v1/message' ) ),
		'consentUrl'  => esc_url_raw( rest_url( 'bp-chat/v1/consent' ) ),
		'company'     => $company,
		'greeting'    => trim( (string) ( $o['greeting'] ?? '' ) ) ?: $default_greeting,
		'launcher'    => trim( (string) ( $o['launcher'] ?? '' ) ) ?: 'Chat with us',
		// Passive notice shown once under the input.
		'consent'     => trim( (string) ( $o['consent'] ?? '' ) )
			?: "By chatting you agree to be contacted about your request.",
		// The explicit SMS opt-in disclosure shown on the consent card before any text is sent.
		'textConsent' => trim( (string) ( $o['text_consent'] ?? '' ) ) ?: $default_text_consent,
		'privacyUrl'  => trim( (string) ( $o['privacy_url'] ?? '' ) ) ?: '/privacy-policy/',
		'termsUrl'    => trim( (string) ( $o['terms_url'] ?? '' ) ),
		'consentYes'  => 'Yes, text me',
		'consentNo'   => 'No thanks',
	] );
}

// Mount point — the widget builds its own DOM inside this.
add_action( 'wp_footer', function () {
	if ( is_admin() || ! bp_chat_ready() ) return;
	echo '<div id="bp-chat-root"></div>';
} );


/*--------------------------------------------------------------
# REST Endpoint
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'bp-chat/v1', '/message', [
		'methods'             => 'POST',
		'callback'            => 'bp_chat_handle_message',
		'permission_callback' => '__return_true', // public widget; abuse guards below
	] );
	register_rest_route( 'bp-chat/v1', '/consent', [
		'methods'             => 'POST',
		'callback'            => 'bp_chat_handle_consent',
		'permission_callback' => '__return_true', // public widget; phone comes from server-side pending only
	] );
} );

function bp_chat_handle_message( WP_REST_Request $req ) {
	if ( ! bp_chat_ready() ) {
		return new WP_REST_Response( [ 'error' => 'Chat is not configured.' ], 503 );
	}

	// ---- Light abuse guards (public, unauthenticated, paid downstream) ----
	$ip      = bp_chat_client_ip();
	$rl_key  = 'bp_chat_rl_' . md5( $ip );
	$hits    = (int) get_transient( $rl_key );
	if ( $hits >= 40 ) { // ~40 messages / hour / IP
		return new WP_REST_Response( [ 'error' => 'Too many messages. Please try again later.' ], 429 );
	}
	set_transient( $rl_key, $hits + 1, HOUR_IN_SECONDS );

	$params = $req->get_json_params();
	$cid    = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $params['cid'] ?? '' ) );
	$cid    = substr( $cid, 0, 64 );
	$raw    = $params['messages'] ?? [];

	if ( ! is_array( $raw ) || empty( $raw ) ) {
		return new WP_REST_Response( [ 'error' => 'No messages.' ], 400 );
	}
	if ( count( $raw ) > 40 ) {
		$raw = array_slice( $raw, -40 ); // keep the tail; conversations this long are rare
	}

	// Normalize to text-only user/assistant turns. The model only needs the
	// visible transcript; any tool exchange completed inside a prior request.
	$messages = [];
	foreach ( $raw as $m ) {
		$role = ( ( $m['role'] ?? '' ) === 'assistant' ) ? 'assistant' : 'user';
		$text = trim( (string) ( $m['content'] ?? '' ) );
		if ( $text === '' ) continue;
		$messages[] = [ 'role' => $role, 'content' => mb_substr( $text, 0, 2000 ) ];
	}
	if ( empty( $messages ) || $messages[0]['role'] !== 'user' ) {
		return new WP_REST_Response( [ 'error' => 'Invalid conversation.' ], 400 );
	}

	$result = bp_chat_run( $messages, $cid );
	if ( is_wp_error( $result ) ) {
		error_log( 'BP CHAT error: ' . $result->get_error_message() );
		return new WP_REST_Response( [ 'error' => 'Sorry — something went wrong. Please call us instead.' ], 502 );
	}

	return new WP_REST_Response( $result, 200 );
}

/**
 * The visitor's Yes/No on the consent card. "yes" is the verifiable opt-in:
 * we only act on a phone number we stashed server-side when the AI called
 * request_text_consent — the client never supplies the number here, so this
 * endpoint can't be used to text an arbitrary number. On "yes" we record the
 * consent audit trail (timestamp + IP) and send the opening SMS.
 */
function bp_chat_handle_consent( WP_REST_Request $req ) {
	if ( ! bp_chat_ready() ) {
		return new WP_REST_Response( [ 'error' => 'Chat is not configured.' ], 503 );
	}

	$params = $req->get_json_params();
	$cid    = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $params['cid'] ?? '' ) );
	$cid    = substr( $cid, 0, 64 );
	$choice = strtolower( trim( (string) ( $params['choice'] ?? '' ) ) );
	if ( $cid === '' ) return new WP_REST_Response( [ 'error' => 'Missing conversation.' ], 400 );

	$pending = get_transient( 'bp_chat_pending_' . $cid );
	// No pending opt-in → nothing to do (idempotent; blocks replay/abuse).
	if ( ! is_array( $pending ) || empty( $pending['phone'] ) ) {
		return new WP_REST_Response( [ 'ok' => false, 'reply' => '' ], 200 );
	}

	$o        = bp_chat_config();
	$customer = function_exists( 'customer_info' ) ? customer_info() : [];
	$conv     = bp_chat_conv_get_or_create_by_cid( $cid );

	if ( $choice !== 'yes' ) {
		delete_transient( 'bp_chat_pending_' . $cid );
		bp_chat_msg_add( (int) $conv['id'], 'user', '[declined text updates]', 'web' );
		$reply = 'No problem — we can keep chatting right here.';
		bp_chat_msg_add( (int) $conv['id'], 'assistant', $reply, 'web' );
		return new WP_REST_Response( [ 'ok' => true, 'consent' => 'declined', 'reply' => $reply ], 200 );
	}

	// Consent granted — write the audit trail BEFORE sending anything.
	delete_transient( 'bp_chat_pending_' . $cid );
	bp_chat_conv_update( (int) $conv['id'], [
		'name'       => (string) ( $pending['name'] ?? ( $conv['name'] ?? '' ) ),
		'email'      => (string) ( $pending['email'] ?? ( $conv['email'] ?? '' ) ),
		'consent_at' => current_time( 'mysql' ),
		'consent_ip' => bp_chat_client_ip(),
	] );
	$conv = bp_chat_conv_get( (int) $conv['id'] );
	bp_chat_msg_add( (int) $conv['id'], 'user', '[agreed to receive text messages]', 'web' );

	$ok      = bp_chat_begin_text_thread( $conv, $customer, (string) $pending['phone'], (string) ( $pending['name'] ?? '' ) );
	$company = $customer['name'] ?? 'us';
	$reply   = $ok
		? "Great — I just texted you from {$company}. Reply there anytime and we'll keep helping."
		: "Thanks! We'll be in touch. If our text doesn't come through, just give us a call.";
	bp_chat_msg_add( (int) $conv['id'], 'assistant', $reply, 'web' );

	return new WP_REST_Response( [ 'ok' => $ok, 'consent' => 'granted', 'reply' => $reply ], 200 );
}

function bp_chat_client_ip(): string {
	foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			$ip = explode( ',', $_SERVER[ $k ] )[0];
			return trim( $ip );
		}
	}
	return '0.0.0.0';
}


/*--------------------------------------------------------------
# Claude Call (with lead-capture tool)
----------------------------------------------------------------
Runs the short agentic loop: call the model; if it decides it has
enough to hand off, it calls send_lead_to_contractor. We execute
that (text the contractor), feed the result back, and let the model
write its closing reply. Returns ['reply' => str, 'lead_sent' => bool].
--------------------------------------------------------------*/

function bp_chat_run( array $messages, string $cid ) {
	$o        = bp_chat_config();
	$customer = function_exists( 'customer_info' ) ? customer_info() : [];

	// Pull the (possibly file-based) knowledge into the option before building the prompt.
	$o['company_knowledge'] = bp_chat_company_knowledge( $o );

	$system = bp_chat_system_prompt( $o, $customer );
	$tools  = bp_chat_tools();

	// Persist the browser transcript so a handoff to SMS continues seamlessly.
	$conv = bp_chat_conv_get_or_create_by_cid( $cid );
	bp_chat_sync_web_history( (int) $conv['id'], $messages );

	$lead_sent         = false;
	$consent_requested = false;

	$resp = bp_chat_call_claude( $system, $messages, $tools );
	if ( is_wp_error( $resp ) ) return $resp;

	// Tool loop — capped at one round.
	if ( ( $resp['stop_reason'] ?? '' ) === 'tool_use' ) {
		$results = [];
		foreach ( (array) ( $resp['content'] ?? [] ) as $block ) {
			if ( ( $block['type'] ?? '' ) !== 'tool_use' ) continue;
			$results[] = [
				'type'        => 'tool_result',
				'tool_use_id' => $block['id'] ?? '',
				'content'     => bp_chat_handle_tool_block( $block, $customer, $o, $conv, 'web', $lead_sent, $consent_requested ),
			];
		}
		$messages[] = [ 'role' => 'assistant', 'content' => $resp['content'] ];
		$messages[] = [ 'role' => 'user', 'content' => $results ];
		$resp = bp_chat_call_claude( $system, $messages, $tools );
		if ( is_wp_error( $resp ) ) return $resp;
	}

	// Extract the final visible text.
	$reply = '';
	foreach ( (array) ( $resp['content'] ?? [] ) as $block ) {
		if ( ( $block['type'] ?? '' ) === 'text' ) $reply .= $block['text'];
	}
	$reply = trim( $reply );
	if ( $reply === '' ) $reply = "Thanks! Someone from " . ( $customer['name'] ?? 'our team' ) . " will follow up shortly.";

	bp_chat_msg_add( (int) $conv['id'], 'assistant', $reply, 'web' );

	return [ 'reply' => $reply, 'lead_sent' => $lead_sent, 'request_consent' => $consent_requested ];
}

/**
 * The tool set offered to the model — shared by the web and SMS runners so
 * both behave identically.
 */
function bp_chat_tools(): array {
	return [
		[
			'name'        => 'send_lead_to_contractor',
			'description' => "Text the contractor the visitor's details so they can follow up. "
				. "Call this once you have the visitor's name, email, a phone number, and a clear "
				. "description of what they need. Do not call it for casual browsers.",
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_name'  => [ 'type' => 'string', 'description' => "Visitor's name." ],
					'customer_email' => [ 'type' => 'string', 'description' => "Visitor's email address." ],
					'customer_phone' => [ 'type' => 'string', 'description' => "Best callback phone number." ],
					'problem_summary'=> [ 'type' => 'string', 'description' => "Plain summary of what they need." ],
					'urgency'        => [ 'type' => 'string', 'description' => "How urgent (e.g. emergency, soon, flexible)." ],
					'service_area'   => [ 'type' => 'string', 'description' => "City/area or address, if given." ],
				],
				'required' => [ 'customer_name', 'customer_phone', 'problem_summary' ],
			],
		],
		[
			'name'        => 'request_text_consent',
			'description' => "Ask the visitor to opt in to SMS text messages. Call this ONLY after the visitor "
				. "has agreed to be texted AND you have their name, email, and a real mobile number. This does NOT "
				. "send a text — it shows the visitor a consent form they must accept first. Do not tell them they "
				. "are signed up until they accept it.",
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_name'  => [ 'type' => 'string', 'description' => "Visitor's name." ],
					'customer_email' => [ 'type' => 'string', 'description' => "Visitor's email address." ],
					'customer_phone' => [ 'type' => 'string', 'description' => "The visitor's mobile number." ],
				],
				'required' => [ 'customer_name', 'customer_email', 'customer_phone' ],
			],
		],
	];
}

/**
 * Execute one tool_use block and return the tool_result content string.
 * Shared by the web and SMS runners. $conv is updated in place; $lead_sent
 * and $consent_requested are set when those actions fire.
 */
function bp_chat_handle_tool_block( array $block, array $customer, array $o, array &$conv, string $channel, bool &$lead_sent, bool &$consent_requested ): string {
	$name  = $block['name'] ?? '';
	$input = (array) ( $block['input'] ?? [] );

	if ( $name === 'send_lead_to_contractor' ) {
		if ( ! empty( $input['customer_email'] ) && ! empty( $conv['id'] ) ) {
			bp_chat_conv_update( (int) $conv['id'], [ 'email' => sanitize_email( (string) $input['customer_email'] ) ] );
		}
		$ok = bp_chat_deliver_lead( $input, (string) ( $conv['cid'] ?? '' ), $customer, $o );
		if ( $ok && ! empty( $conv['id'] ) ) bp_chat_conv_update( (int) $conv['id'], [ 'lead_sent_at' => current_time( 'mysql' ) ] );
		$lead_sent = $lead_sent || $ok;
		if ( ! $ok ) {
			return 'Could not deliver the lead right now; ask the visitor to call the listed phone number.';
		}

		// Compliance safety net: once we have a lead with a usable mobile number,
		// ALWAYS surface the SMS consent card (web only) — don't depend on the
		// model choosing to call request_text_consent. Guarded so it offers once
		// per conversation and never after the visitor has already opted in.
		$offered = bp_chat_offer_text_consent( $conv, $input, $channel, $consent_requested );
		return $offered
			? 'Lead delivered. An SMS opt-in form is now being shown to the visitor — invite them to accept it if they want updates by text. Do NOT say they are signed up for texts until they accept it.'
			: 'Lead delivered to the contractor.';
	}

	if ( $name === 'request_text_consent' ) {
		$phone = bp_chat_normalize_phone( (string) ( $input['customer_phone'] ?? '' ) );
		if ( $phone === '' ) return 'That phone number was not valid — ask for a 10-digit US mobile number.';
		if ( ( $conv['channel'] ?? 'web' ) === 'sms' ) return 'Already connected by text with this visitor.';

		if ( ! empty( $input['customer_email'] ) && ! empty( $conv['id'] ) ) {
			bp_chat_conv_update( (int) $conv['id'], [ 'email' => sanitize_email( (string) $input['customer_email'] ) ] );
		}
		// Visitor explicitly asked to be texted — force the card even if the lead
		// path already auto-offered it earlier in this conversation.
		bp_chat_offer_text_consent( $conv, $input, $channel, $consent_requested, true );
		return 'Consent form shown to the visitor. Wait for them to accept it — do NOT say they are signed up or that you have texted them yet. Briefly let them know a quick confirmation will pop up to approve.';
	}

	return 'Unknown tool.';
}

/**
 * Stash opt-in details server-side and flag the widget to show the SMS consent
 * card. The visitor's "Yes" tap on that card is the A2P-verifiable opt-in — no
 * text is sent before it. Returns true if the card will be shown.
 *
 * Web channel only (there's no widget on the SMS side). Offers once per
 * conversation and never after the visitor has already opted in, unless $force
 * is set (used when the visitor explicitly asks to be texted).
 */
function bp_chat_offer_text_consent( array $conv, array $input, string $channel, bool &$consent_requested, bool $force = false ): bool {
	if ( $channel !== 'web' ) return false;
	if ( ( $conv['channel'] ?? 'web' ) === 'sms' ) return false;
	if ( ! empty( $conv['consent_at'] ) ) return false; // already opted in

	$cid   = (string) ( $conv['cid'] ?? '' );
	$phone = bp_chat_normalize_phone( (string) ( $input['customer_phone'] ?? '' ) );
	if ( $cid === '' || $phone === '' ) return false;
	if ( ! $force && get_transient( 'bp_chat_offered_' . $cid ) ) return false;

	set_transient( 'bp_chat_pending_' . $cid, [
		'name'  => (string) ( $input['customer_name']  ?? ( $conv['name']  ?? '' ) ),
		'email' => sanitize_email( (string) ( $input['customer_email'] ?? ( $conv['email'] ?? '' ) ) ),
		'phone' => $phone,
	], HOUR_IN_SECONDS );
	set_transient( 'bp_chat_offered_' . $cid, 1, 6 * HOUR_IN_SECONDS );

	$consent_requested = true;
	return true;
}

/**
 * Begin the SMS thread once consent is confirmed: flip the conversation to the
 * SMS channel and send the opening text. Shared by the consent endpoint.
 * Returns true if the opening SMS sent.
 */
function bp_chat_begin_text_thread( array &$conv, array $customer, string $raw_phone, string $name ): bool {
	$phone = bp_chat_normalize_phone( $raw_phone );
	if ( $phone === '' ) return false;
	if ( ( $conv['channel'] ?? 'web' ) === 'sms' ) return true; // already texting

	bp_chat_conv_update( (int) $conv['id'], [
		'phone'   => $phone,
		'name'    => $name !== '' ? $name : (string) ( $conv['name'] ?? '' ),
		'channel' => 'sms',
	] );
	$conv['channel'] = 'sms';
	$conv['phone']   = $phone;

	$opening = bp_chat_text_opening( $customer, $name );
	$ok = bp_chat_send_sms( $phone, $opening );
	if ( $ok ) bp_chat_msg_add( (int) $conv['id'], 'assistant', $opening, 'sms' );
	return $ok;
}

/**
 * One round-trip to the Anthropic Messages API. Returns the decoded
 * response array, or WP_Error. The system prompt is sent as a single
 * cache_control'd block so repeated turns read it from cache.
 */
function bp_chat_call_claude( string $system, array $messages, array $tools ) {
	$body = [
		'model'      => bp_chat_model(),
		'max_tokens' => 1024,
		'system'     => [ [
			'type'          => 'text',
			'text'          => $system,
			'cache_control' => [ 'type' => 'ephemeral' ],
		] ],
		'messages' => $messages,
		'tools'    => $tools,
	];

	// `effort` is Opus/Sonnet-only — it errors on Haiku. Low effort keeps the
	// chat snappy and cheap; the task is light qualification, not deep reasoning.
	if ( bp_chat_is_opus() ) {
		$body['output_config'] = [ 'effort' => 'low' ];
		// Thinking is omitted (default) for latency; tell Opus to answer directly
		// so no reasoning leaks into the visible reply.
		$body['system'][0]['text'] .= "\n\nRespond only with your reply to the visitor — no internal reasoning or meta-commentary.";
	}

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 30,
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => bp_chat_api_key(),
			'anthropic-version' => '2023-06-01',
		],
		'body' => wp_json_encode( $body ),
	] );

	if ( is_wp_error( $response ) ) return $response;

	$status  = wp_remote_retrieve_response_code( $response );
	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $status !== 200 || ! is_array( $decoded ) ) {
		$msg = $decoded['error']['message'] ?? ( 'HTTP ' . $status );
		return new WP_Error( 'api_error', "Anthropic API error ($status): $msg" );
	}

	return $decoded;
}


/*--------------------------------------------------------------
# Lead Handoff (Twilio SMS)
--------------------------------------------------------------*/

/**
 * Format + send the lead SMS, guarding against a duplicate send for
 * the same conversation. Returns true on a successful (or already-sent)
 * delivery, false on failure.
 */
function bp_chat_deliver_lead( array $lead, string $cid, array $customer, array $o ): bool {
	// Dedupe: only one lead text per conversation.
	$sent_key = $cid ? ( 'bp_chat_sent_' . $cid ) : '';
	if ( $sent_key && get_transient( $sent_key ) ) return true;

	$company = $customer['name'] ?? get_bloginfo( 'name' );

	$lines   = [];
	$lines[] = "New lead from your {$company} website:";
	$lines[] = trim( ( $lead['customer_name'] ?? 'Customer' ) . ' — ' . ( $lead['customer_phone'] ?? 'no number given' ) );
	if ( ! empty( $lead['customer_email'] ) )  $lines[] = $lead['customer_email'];
	if ( ! empty( $lead['problem_summary'] ) ) $lines[] = $lead['problem_summary'];
	if ( ! empty( $lead['urgency'] ) )         $lines[] = 'Urgency: ' . $lead['urgency'];
	if ( ! empty( $lead['service_area'] ) )    $lines[] = 'Area: ' . $lead['service_area'];
	$lines[] = 'Call them back.';
	$message = implode( "\n", $lines );

	$ok = bp_chat_send_sms( (string) ( $o['contractor_sms'] ?? '' ), $message );

	if ( $ok && $sent_key ) set_transient( $sent_key, 1, 6 * HOUR_IN_SECONDS );
	return $ok;
}

/**
 * Send a single SMS via the site's Twilio subaccount. Credentials are
 * resolved by bp_chat_twilio() (wp-config constant → site_option).
 * Returns true on a 2xx from Twilio.
 */
function bp_chat_send_sms( string $to, string $message ): bool {
	$sid   = bp_chat_twilio( 'sid' );
	$token = bp_chat_twilio( 'token' );
	$from  = bp_chat_twilio( 'number' );
	if ( ! $sid || ! $token || ! $from || ! $to ) return false;

	$response = wp_remote_post(
		'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json',
		[
			'timeout' => 20,
			'headers' => [ 'Authorization' => 'Basic ' . base64_encode( "$sid:$token" ) ],
			'body'    => [ 'From' => $from, 'To' => $to, 'Body' => $message ],
		]
	);

	if ( is_wp_error( $response ) ) {
		error_log( 'BP CHAT Twilio error: ' . $response->get_error_message() );
		return false;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( $status < 200 || $status >= 300 ) {
		error_log( 'BP CHAT Twilio HTTP ' . $status . ': ' . wp_remote_retrieve_body( $response ) );
		return false;
	}
	return true;
}
