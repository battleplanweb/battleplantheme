<?php
/* Battle Plan Web Design — Site Pulse: Import Past Reports (GOD-only)

/*--------------------------------------------------------------
Lets the superadmin (god) bulk-import historical GM reports from PDF/CSV files.
Each file is ONE past GM report. Claude reads it, extracts the GM, location,
period, and the template's field values; god reviews/corrects the attribution in
a preview, then saves. Imported reports are written straight in as `submitted`
dated to their historical period — with **NO action items** (they're old) and
**NO supervisor notifications**.

Three god-gated AJAX endpoints:
  - site_pulse_import_meta   → GM template fields + user/location lists (for the preview dropdowns)
  - site_pulse_import_parse  → one file (base64) → AI-extracted {gm, location, period, answers} + best-guess matches
  - site_pulse_import_save   → one confirmed report → insert report + answers (no action items / notify)
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/* ───────────────────────── helpers ───────────────────────── */

// GOD-only, never while impersonating. Sends a JSON error + returns false if not allowed.
function sp_import_gate(): bool {
	if ( site_pulse_is_god( get_current_user_id() ) && ! site_pulse_is_impersonating() ) return true;
	wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	return false;
}

// The import target template, by report type ('gm' | 'supervisor').
function sp_import_template( string $type ): ?array {
	$slug = ( $type === 'supervisor' ) ? 'supervisor-biweekly' : 'manager-biweekly';
	return site_pulse_get_template_by_slug( $slug );
}

// Sanitize the requested report type from the AJAX request (default GM).
function sp_import_type(): string {
	$t = sanitize_key( wp_unslash( $_POST['type'] ?? 'gm' ) );
	return $t === 'supervisor' ? 'supervisor' : 'gm';
}

// Supervisor reports aren't tied to a single store (their profile location is 0), so location is
// optional for them and stored as 0; GM reports are per-store and require a location.
function sp_import_needs_location( string $type ): bool {
	return $type !== 'supervisor';
}

// Lean field list for the AI prompt + key validation.
function sp_import_fields( int $template_id ): array {
	$out = [];
	foreach ( site_pulse_get_template_fields( $template_id ) as $f ) {
		$out[] = [
			'key'     => $f['field_key'],
			'label'   => $f['label'],
			'type'    => $f['field_type'],
			'options' => $f['options'] ?: '',
		];
	}
	return $out;
}

// All ACTIVE Site Pulse users (id+name+type) — `type` is the role-based report type ('supervisor'
// for the supervisor role, else 'gm'), used to auto-detect a report's type from who wrote it.
function sp_import_users(): array {
	static $cache = null;
	if ( $cache !== null ) return $cache;
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT user_id, role_id FROM " . site_pulse_table( 'user_profiles' ) . " WHERE status = 'active'", ARRAY_A ) ?: [];
	$out  = [];
	foreach ( $rows as $r ) {
		$u = get_userdata( (int) $r['user_id'] );
		if ( ! $u ) continue;
		$role = site_pulse_get_role( (int) $r['role_id'] );
		$slug = $role ? $role['slug'] : '';
		$out[] = [ 'id' => (int) $r['user_id'], 'name' => $u->display_name, 'type' => ( $slug === 'supervisor' ? 'supervisor' : 'gm' ) ];
	}
	usort( $out, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
	$cache = $out;
	return $out;
}

// Both report templates, keyed by type.
function sp_import_templates(): array {
	return [
		'gm'         => site_pulse_get_template_by_slug( 'manager-biweekly' ),
		'supervisor' => site_pulse_get_template_by_slug( 'supervisor-biweekly' ),
	];
}

// Union of BOTH templates' fields by key — parse against this so extraction works for either type
// even if the templates have diverged; save maps answers back by key under the chosen type.
function sp_import_union_fields(): array {
	$seen = []; $out = [];
	foreach ( sp_import_templates() as $tpl ) {
		if ( ! $tpl ) continue;
		foreach ( site_pulse_get_template_fields( (int) $tpl['id'] ) as $f ) {
			if ( isset( $seen[ $f['field_key'] ] ) ) continue;
			$seen[ $f['field_key'] ] = true;
			$out[] = [ 'key' => $f['field_key'], 'label' => $f['label'], 'type' => $f['field_type'], 'options' => $f['options'] ?: '' ];
		}
	}
	return $out;
}

// Role-based report type for a matched user id ('supervisor' | 'gm').
function sp_import_user_type( int $user_id, array $users ): string {
	foreach ( $users as $u ) if ( (int) $u['id'] === $user_id ) return $u['type'];
	return 'gm';
}

function sp_import_locations(): array {
	$out = [];
	foreach ( site_pulse_get_all_locations( true ) as $l ) {
		$out[] = [ 'id' => (int) $l['id'], 'name' => $l['name'] ];
	}
	return $out;
}

// Fuzzy-match a name against a [{id,name}] list → best id, or 0 if nothing's close enough.
function sp_import_match( string $name, array $list ): int {
	$name = strtolower( trim( $name ) );
	if ( $name === '' ) return 0;
	$best = 0; $best_score = 0;
	foreach ( $list as $item ) {
		$cand = strtolower( trim( $item['name'] ) );
		if ( $cand === $name ) return (int) $item['id'];                 // exact wins immediately
		$score = ( strpos( $cand, $name ) !== false || strpos( $name, $cand ) !== false ) ? 90 : 0;
		if ( ! $score ) { similar_text( $name, $cand, $pct ); $score = $pct; }
		if ( $score > $best_score ) { $best_score = $score; $best = (int) $item['id']; }
	}
	return $best_score >= 75 ? $best : 0;
}

function sp_import_clean_date( $d ): string {
	$d = trim( (string) $d );
	if ( $d === '' ) return '';
	$ts = strtotime( $d );
	return $ts ? gmdate( 'Y-m-d', $ts ) : '';
}

// Pull the first JSON object out of a model reply (tolerates ``` fences / stray prose).
function sp_import_decode_json( string $text ) {
	$text = trim( $text );
	$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
	$text = preg_replace( '/\s*```$/', '', $text );
	if ( $text === '' ) return null;
	if ( $text[0] !== '{' ) {
		$s = strpos( $text, '{' ); $e = strrpos( $text, '}' );
		if ( $s !== false && $e !== false && $e > $s ) $text = substr( $text, $s, $e - $s + 1 );
	}
	$d = json_decode( $text, true );
	return is_array( $d ) ? $d : null;
}


/* ───────────────────────── AI: read a report file ───────────────────────── */

/**
 * Document sibling of site_pulse_call_claude: a base64 PDF + a text prompt → model reply.
 * (PDF support is GA on the messages API; Claude reads text AND scanned/OCR PDFs.)
 */
function site_pulse_call_claude_document( string $pdf_b64, string $prompt, string $system = '', array $opts = [], &$debug = null ): ?string {
	$debug   = null;
	$api_key = site_pulse_get_api_key();
	if ( ! $api_key ) { $debug = 'No API key configured.'; return null; }

	$body = [
		'model'      => $opts['model']      ?? 'claude-sonnet-4-6',
		'max_tokens' => $opts['max_tokens'] ?? 4096,
		'messages'   => [ [
			'role'    => 'user',
			'content' => [
				[ 'type' => 'document', 'source' => [ 'type' => 'base64', 'media_type' => 'application/pdf', 'data' => $pdf_b64 ] ],
				[ 'type' => 'text', 'text' => $prompt ],
			],
		] ],
	];
	if ( $system ) $body['system'] = $system;

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		],
		'body'    => wp_json_encode( $body ),
		'timeout' => $opts['timeout'] ?? 60,
	] );

	if ( is_wp_error( $response ) ) { $debug = 'Request failed: ' . $response->get_error_message(); return null; }

	$status = wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $status !== 200 || empty( $data['content'][0]['text'] ) ) {
		$api_msg = $data['error']['message'] ?? wp_remote_retrieve_response_message( $response );
		$debug   = "HTTP $status" . ( $api_msg ? ": $api_msg" : '' );
		site_pulse_log( 'ai_error', 'Claude (document) returned status ' . $status, [ 'response' => $data ] );
		if ( function_exists( 'bp_ai_model_alert' ) ) bp_ai_model_alert( (int) $status, $data, $body['model'], 'Site Pulse Import' );
		return null;
	}
	return $data['content'][0]['text'];
}

/**
 * Image sibling of site_pulse_call_claude_document: a base64 image + a text prompt → model reply.
 * `$media_type` is the exact image MIME the API expects (image/jpeg | image/png).
 */
function site_pulse_call_claude_image( string $img_b64, string $media_type, string $prompt, string $system = '', array $opts = [], &$debug = null ): ?string {
	$debug   = null;
	$api_key = site_pulse_get_api_key();
	if ( ! $api_key ) { $debug = 'No API key configured.'; return null; }

	$body = [
		'model'      => $opts['model']      ?? 'claude-sonnet-4-6',
		'max_tokens' => $opts['max_tokens'] ?? 4096,
		'messages'   => [ [
			'role'    => 'user',
			'content' => [
				[ 'type' => 'image', 'source' => [ 'type' => 'base64', 'media_type' => $media_type, 'data' => $img_b64 ] ],
				[ 'type' => 'text', 'text' => $prompt ],
			],
		] ],
	];
	if ( $system ) $body['system'] = $system;

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		],
		'body'    => wp_json_encode( $body ),
		'timeout' => $opts['timeout'] ?? 60,
	] );

	if ( is_wp_error( $response ) ) { $debug = 'Request failed: ' . $response->get_error_message(); return null; }

	$status = wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $status !== 200 || empty( $data['content'][0]['text'] ) ) {
		$api_msg = $data['error']['message'] ?? wp_remote_retrieve_response_message( $response );
		$debug   = "HTTP $status" . ( $api_msg ? ": $api_msg" : '' );
		site_pulse_log( 'ai_error', 'Claude (image) returned status ' . $status, [ 'response' => $data ] );
		if ( function_exists( 'bp_ai_model_alert' ) ) bp_ai_model_alert( (int) $status, $data, $body['model'], 'Site Pulse Import' );
		return null;
	}
	return $data['content'][0]['text'];
}

function sp_import_build_prompt( array $fields ): array {
	$field_lines = array_map(
		fn( $f ) => "- {$f['key']} ({$f['type']}): {$f['label']}" . ( $f['options'] ? " [options: {$f['options']}]" : '' ),
		$fields
	);
	$system = 'You extract data from a single restaurant General Manager (GM) bi-weekly report into structured JSON. Output ONLY valid JSON — no prose, no code fences.';
	$prompt = "This document is ONE GM report. Extract it into a JSON object with exactly this shape:\n"
		. "{\n"
		. "  \"gm_name\": \"\",         // the GM/manager who wrote it (\"\" if unknown)\n"
		. "  \"location_name\": \"\",   // the restaurant/store (\"\" if unknown)\n"
		. "  \"period_start\": \"\",    // report period start as YYYY-MM-DD (\"\" if unknown)\n"
		. "  \"period_end\": \"\",      // report period end as YYYY-MM-DD (\"\" if unknown)\n"
		. "  \"answers\": {}            // map of field_key => value, only for fields you actually find\n"
		. "}\n\n"
		. "Use these EXACT field keys. For select/radio return one option value; for checkbox/multi return an array of values; for number fields return a number (not a string). Omit any field you can't find.\n\nFields:\n"
		. implode( "\n", $field_lines )
		. "\n\nOutput ONLY the JSON object.";
	return [ $system, $prompt ];
}

// Parse one file (base64) → decoded array or null.
function sp_import_parse_file( string $b64, string $mime, array $fields, &$debug = null ) {
	list( $system, $prompt ) = sp_import_build_prompt( $fields );

	if ( stripos( $mime, 'pdf' ) !== false ) {
		$raw = site_pulse_call_claude_document( $b64, $prompt, $system, [ 'max_tokens' => 4096 ], $debug );
	} elseif ( stripos( $mime, 'image' ) !== false || preg_match( '#(jpe?g|jfif|png)$#i', $mime ) ) {
		$media = ( stripos( $mime, 'png' ) !== false ) ? 'image/png' : 'image/jpeg';
		$raw   = site_pulse_call_claude_image( $b64, $media, $prompt, $system, [ 'max_tokens' => 4096 ], $debug );
	} else {
		$text = base64_decode( $b64 );
		if ( strlen( $text ) > 200000 ) $text = substr( $text, 0, 200000 ); // keep CSV prompts sane
		$raw  = site_pulse_call_claude( $prompt . "\n\n--- REPORT CONTENT ---\n" . $text, $system, [ 'max_tokens' => 4096 ], $debug );
	}
	if ( ! $raw ) return null;
	return sp_import_decode_json( $raw );
}


/* ───────────────────────── AJAX (all god-only) ───────────────────────── */

add_action( 'wp_ajax_site_pulse_import_meta', 'site_pulse_ajax_import_meta' );
function site_pulse_ajax_import_meta(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_import_gate() ) return;

	$tpls = sp_import_templates();
	if ( ! $tpls['gm'] && ! $tpls['supervisor'] ) wp_send_json_error( [ 'message' => 'No GM/Supervisor report template found on this install.' ] );

	wp_send_json_success( [
		'gm_template_id'         => $tpls['gm'] ? (int) $tpls['gm']['id'] : 0,
		'supervisor_template_id' => $tpls['supervisor'] ? (int) $tpls['supervisor']['id'] : 0,
		'fields'                 => sp_import_union_fields(),  // canonical set for the value preview
		'users'                  => sp_import_users(),         // each carries its role-based `type`
		'locations'              => sp_import_locations(),
		'has_api_key'            => (bool) site_pulse_get_api_key(),
	] );
}

add_action( 'wp_ajax_site_pulse_import_parse', 'site_pulse_ajax_import_parse' );
function site_pulse_ajax_import_parse(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_import_gate() ) return;

	$fields = sp_import_union_fields();
	if ( ! $fields ) wp_send_json_error( [ 'message' => 'No report template fields found.' ] );

	$file = (string) ( $_POST['file'] ?? '' );           // data URL or raw base64
	$mime = sanitize_text_field( wp_unslash( $_POST['mime'] ?? '' ) );
	if ( strpos( $file, 'base64,' ) !== false ) {
		if ( ! $mime && preg_match( '#data:([^;]+);#', $file, $m ) ) $mime = $m[1];
		$file = substr( $file, strpos( $file, 'base64,' ) + 7 );
	}
	$b64 = preg_replace( '/\s+/', '', $file );
	if ( $b64 === '' ) wp_send_json_error( [ 'message' => 'No file received.' ] );
	if ( strlen( $b64 ) > 28000000 ) wp_send_json_error( [ 'message' => 'File too large (max ~20MB).' ] );

	$debug  = null;
	$parsed = sp_import_parse_file( $b64, $mime, $fields, $debug );
	if ( ! is_array( $parsed ) ) wp_send_json_error( [ 'message' => 'AI could not read this file. ' . ( $debug ?: '' ) ] );

	$valid   = array_column( $fields, 'key' );
	$answers = [];
	foreach ( (array) ( $parsed['answers'] ?? [] ) as $k => $v ) {
		if ( in_array( $k, $valid, true ) ) $answers[ $k ] = $v;
	}

	$gm    = sanitize_text_field( (string) ( $parsed['gm_name'] ?? '' ) );
	$loc   = sanitize_text_field( (string) ( $parsed['location_name'] ?? '' ) );
	$users = sp_import_users();
	$muid  = sp_import_match( $gm, $users );
	$dtype = sp_import_user_type( $muid, $users );   // type follows the matched person's role

	wp_send_json_success( [
		'gm_name'             => $gm,
		'location_name'       => $loc,
		'period_start'        => sp_import_clean_date( $parsed['period_start'] ?? '' ),
		'period_end'          => sp_import_clean_date( $parsed['period_end'] ?? '' ),
		'answers'             => (object) $answers,
		'matched_user_id'     => $muid,
		'detected_type'       => $dtype,
		'matched_location_id' => ( $dtype === 'gm' ) ? sp_import_match( $loc, sp_import_locations() ) : 0,
	] );
}

add_action( 'wp_ajax_site_pulse_import_save', 'site_pulse_ajax_import_save' );
function site_pulse_ajax_import_save(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_import_gate() ) return;

	$type = sp_import_type();
	$tpl  = sp_import_template( $type );
	if ( ! $tpl ) wp_send_json_error( [ 'message' => ucfirst( $type ) . ' report template not found.' ] );
	$template_id = (int) $tpl['id'];
	$needs_loc   = sp_import_needs_location( $type );

	$user_id     = (int) ( $_POST['user_id'] ?? 0 );
	$location_id = (int) ( $_POST['location_id'] ?? 0 );
	$start       = sp_import_clean_date( $_POST['period_start'] ?? '' );
	$end         = sp_import_clean_date( $_POST['period_end'] ?? '' );

	$answers_raw = $_POST['answers'] ?? '';
	if ( is_string( $answers_raw ) ) $answers_raw = json_decode( wp_unslash( $answers_raw ), true );
	$answers = is_array( $answers_raw ) ? $answers_raw : [];

	if ( $user_id <= 0 ) wp_send_json_error( [ 'message' => 'Pick the ' . ( $type === 'supervisor' ? 'supervisor' : 'GM' ) . ' first.' ] );
	if ( $needs_loc && $location_id <= 0 ) wp_send_json_error( [ 'message' => 'Pick a location first.' ] );
	if ( ! $needs_loc ) $location_id = 0; // supervisor reports aren't tied to a store (matches the normal flow)
	if ( ! $start ) $start = $end ?: gmdate( 'Y-m-d' );
	if ( ! $end )   $end   = $start;

	$fmap = [];
	foreach ( site_pulse_get_template_fields( $template_id ) as $f ) $fmap[ $f['field_key'] ] = (int) $f['id'];

	$report_id = site_pulse_create_report( $template_id, $user_id, $location_id, $start, $end );
	if ( ! $report_id ) wp_send_json_error( [ 'message' => 'Could not create the report.' ] );

	foreach ( $answers as $key => $val ) {
		$key = (string) $key;
		if ( ! isset( $fmap[ $key ] ) ) continue;
		if ( is_array( $val ) ) {
			$val = array_map( 'sanitize_text_field', array_map( 'strval', $val ) );
		} elseif ( is_string( $val ) ) {
			$val = sanitize_textarea_field( $val );
		}
		// numbers pass through untouched so save_answer stores them as answer_numeric
		site_pulse_save_answer( $report_id, $fmap[ $key ], $key, $val );
	}

	// Mark submitted as of the historical period end — NO action items, NO supervisor notification.
	global $wpdb;
	$wpdb->update(
		site_pulse_table( 'reports' ),
		[ 'status' => 'submitted', 'submitted_at' => $end . ' 12:00:00', 'updated_at' => current_time( 'mysql' ) ],
		[ 'id' => $report_id ],
		[ '%s', '%s', '%s' ],
		[ '%d' ]
	);

	site_pulse_log( 'report_imported', 'Imported a past ' . ( $type === 'supervisor' ? 'supervisor' : 'GM' ) . ' report (no action items generated)', [
		'report_id' => $report_id, 'user_id' => $user_id, 'location_id' => $location_id, 'type' => $type, 'period' => "$start..$end",
	] );

	wp_send_json_success( [ 'report_id' => $report_id ] );
}
