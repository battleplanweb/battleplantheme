<?php
/* Battle Plan Web Design — Site Pulse: Import Past Reports (GOD-only)

/*--------------------------------------------------------------
Lets anyone with import_data bulk-import historical reports of ANY report type
(any active report template) from PDF/CSV/image files. The importer picks WHICH
report they're uploading first; each file is ONE past report of that type. Claude
reads it against that template's fields and extracts the person, location, period,
and answers; the importer reviews/corrects the attribution in a preview, then saves.
Imported reports are written straight in as `submitted` dated to their historical
period — with **NO action items** (they're old) and **NO notifications**.

Three import_data-gated AJAX endpoints:
  - site_pulse_import_meta   → active templates (+ fields) + user/location lists (for the preview dropdowns)
  - site_pulse_import_parse  → template_id + one file (base64) → AI-extracted {person, location, period, answers} + best-guess matches
  - site_pulse_import_save   → template_id + one confirmed report → insert report + answers (no action items / notify)
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/* ───────────────────────── helpers ───────────────────────── */

// Gated on the import_data capability (NOT hard-wired to God) so it matches the nav/panel gate and
// can be granted to any role/individual. Resolves the effective user, so impersonation is respected
// (a god viewing-as someone gets exactly that user's access) and gods pass via their all-caps grant.
// Sends a JSON error + returns false if not allowed.
function sp_import_gate(): bool {
	if ( site_pulse_user_can( site_pulse_effective_user_id(), 'import_data' ) ) return true;
	wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	return false;
}

// The chosen import target template (from $_POST['template_id']) — must be an ACTIVE report template.
function sp_import_template(): ?array {
	$tpl = site_pulse_get_template( (int) ( $_POST['template_id'] ?? 0 ) );
	return ( $tpl && (int) $tpl['is_active'] === 1 ) ? $tpl : null;
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

// All ACTIVE Site Pulse users (id+name+profile location) — location_id pre-fills the report's
// location the same way a normal submission stamps it (0 = not tied to a store, e.g. supervisors).
function sp_import_users(): array {
	static $cache = null;
	if ( $cache !== null ) return $cache;
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT user_id, location_id FROM " . site_pulse_table( 'user_profiles' ) . " WHERE status = 'active'", ARRAY_A ) ?: [];
	$out  = [];
	foreach ( $rows as $r ) {
		$u = get_userdata( (int) $r['user_id'] );
		if ( ! $u ) continue;
		$out[] = [ 'id' => (int) $r['user_id'], 'name' => $u->display_name, 'location_id' => (int) $r['location_id'] ];
	}
	usort( $out, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
	$cache = $out;
	return $out;
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
/**
 * Ensure an image is in a format Claude's vision API accepts (JPEG/PNG/GIF/WebP).
 *
 * The card importer sends the browser's raw file (an iPhone photo is HEIC) and forces
 * media_type to image/jpeg for anything non-PNG — so HEIC bytes reach Claude mislabeled
 * as JPEG and the API 400s ("could not read this card"). We sniff the ACTUAL bytes (the
 * caller's declared media_type is unreliable) and, if it isn't a format Claude supports,
 * transcode to JPEG via Imagick. Mutates $b64 + $media_type in place.
 *
 * Returns false only when the image is unsupported AND can't be converted (no Imagick /
 * no HEIC delegate) — the caller then surfaces a clear error instead of a raw API 400.
 */
function site_pulse_prepare_image_for_claude( string &$b64, string &$media_type ): bool {
	$supported = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];

	$bin = base64_decode( $b64, true );
	if ( $bin === false || $bin === '' ) return false;

	// Sniff the real type from the bytes.
	$sniff = '';
	if ( function_exists( 'finfo_open' ) && ( $f = finfo_open( FILEINFO_MIME_TYPE ) ) ) {
		$sniff = (string) finfo_buffer( $f, $bin );
		finfo_close( $f );
	}
	// Older libmagic builds don't recognise HEIC/HEIF — fall back to the ISO-BMFF
	// 'ftyp' brand in the file header (bytes 4–8 = "ftyp", 8–12 = brand).
	if ( $sniff === '' || $sniff === 'application/octet-stream' ) {
		if ( strlen( $bin ) > 12 && substr( $bin, 4, 4 ) === 'ftyp' ) {
			$brand = substr( $bin, 8, 4 );
			if ( in_array( $brand, [ 'heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'mif1', 'msf1', 'heif' ], true ) ) {
				$sniff = 'image/heic';
			}
		}
	}

	// Already a format Claude accepts → just make the declared type honest.
	if ( in_array( $sniff, $supported, true ) ) { $media_type = $sniff; return true; }

	// Couldn't identify it, but the caller claims a supported type → trust the caller
	// (leaves genuine JPEG/PNG that libmagic occasionally mis-sniffs alone).
	if ( $sniff === '' && in_array( $media_type, $supported, true ) ) return true;

	// Anything else (HEIC/HEIF/AVIF/TIFF/BMP…) → transcode to JPEG via Imagick.
	if ( ! class_exists( 'Imagick' ) ) return false;
	try {
		$im = new Imagick();
		$im->readImageBlob( $bin );
		if ( ! $im->valid() ) { $im->destroy(); return false; }
		$im->autoOrient();                       // bake the iPhone rotation flag into pixels
		$w = $im->getImageWidth();
		$h = $im->getImageHeight();
		if ( max( $w, $h ) > 1568 ) {            // Claude downscales anything larger anyway
			$scale = 1568 / max( $w, $h );
			$im->resizeImage( (int) round( $w * $scale ), (int) round( $h * $scale ), Imagick::FILTER_LANCZOS, 1 );
		}
		$im->setImageFormat( 'jpeg' );
		$im->setImageCompressionQuality( 85 );
		$im->stripImage();
		$jpeg = $im->getImageBlob();
		$im->clear();
		$im->destroy();
		if ( $jpeg === '' ) return false;
		$b64        = base64_encode( $jpeg );
		$media_type = 'image/jpeg';
		return true;
	} catch ( Exception $e ) {
		if ( function_exists( 'site_pulse_log' ) ) {
			site_pulse_log( 'ai_error', 'Image transcode for Claude vision failed', [ 'error' => $e->getMessage() ] );
		}
		return false;
	}
}

function site_pulse_call_claude_image( string $img_b64, string $media_type, string $prompt, string $system = '', array $opts = [], &$debug = null ): ?string {
	$debug   = null;
	$api_key = site_pulse_get_api_key();
	if ( ! $api_key ) { $debug = 'No API key configured.'; return null; }

	// iPhone HEIC (and other formats Claude can't read) → transcode to JPEG first.
	if ( ! site_pulse_prepare_image_for_claude( $img_b64, $media_type ) ) {
		$debug = 'Unsupported image format — could not convert it for AI reading (server needs Imagick with HEIC support).';
		return null;
	}

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

function sp_import_build_prompt( array $fields, string $report_name ): array {
	$field_lines = array_map(
		fn( $f ) => "- {$f['key']} ({$f['type']}): {$f['label']}" . ( $f['options'] ? " [options: {$f['options']}]" : '' ),
		$fields
	);
	$system = 'You extract data from a single restaurant report into structured JSON. Output ONLY valid JSON — no prose, no code fences.';
	$prompt = "This document is ONE \"{$report_name}\" report. Extract it into a JSON object with exactly this shape:\n"
		. "{\n"
		. "  \"person_name\": \"\",     // the person who wrote/submitted it (\"\" if unknown)\n"
		. "  \"location_name\": \"\",   // the restaurant/store it's for (\"\" if unknown or not store-specific)\n"
		. "  \"period_start\": \"\",    // report period start as YYYY-MM-DD (\"\" if unknown)\n"
		. "  \"period_end\": \"\",      // report period end as YYYY-MM-DD (\"\" if unknown; same as start for a single-day report)\n"
		. "  \"answers\": {}            // map of field_key => value, only for fields you actually find\n"
		. "}\n\n"
		. "Use these EXACT field keys. For select/radio return one option value; for checkbox/multi return an array of values; for number fields return a number (not a string). Omit any field you can't find.\n\nFields:\n"
		. implode( "\n", $field_lines )
		. "\n\nOutput ONLY the JSON object.";
	return [ $system, $prompt ];
}

// Parse one file (base64) → decoded array or null.
function sp_import_parse_file( string $b64, string $mime, array $fields, string $report_name, &$debug = null ) {
	list( $system, $prompt ) = sp_import_build_prompt( $fields, $report_name );

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


/* ───────────────────────── AJAX (all import_data-gated) ───────────────────────── */

add_action( 'wp_ajax_site_pulse_import_meta', 'site_pulse_ajax_import_meta' );
function site_pulse_ajax_import_meta(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_import_gate() ) return;

	$templates = [];
	foreach ( site_pulse_get_active_templates() as $t ) {
		$templates[] = [ 'id' => (int) $t['id'], 'name' => $t['name'], 'fields' => sp_import_fields( (int) $t['id'] ) ];
	}
	if ( ! $templates ) wp_send_json_error( [ 'message' => 'No active report templates found on this install.' ] );

	wp_send_json_success( [
		'templates'   => $templates,
		'users'       => sp_import_users(),
		'locations'   => sp_import_locations(),
		'has_api_key' => (bool) site_pulse_get_api_key(),
	] );
}

add_action( 'wp_ajax_site_pulse_import_parse', 'site_pulse_ajax_import_parse' );
function site_pulse_ajax_import_parse(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_import_gate() ) return;

	$tpl = sp_import_template();
	if ( ! $tpl ) wp_send_json_error( [ 'message' => 'Choose which report you are importing first.' ] );
	$fields = sp_import_fields( (int) $tpl['id'] );
	if ( ! $fields ) wp_send_json_error( [ 'message' => 'That report has no fields to import into.' ] );

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
	$parsed = sp_import_parse_file( $b64, $mime, $fields, (string) $tpl['name'], $debug );
	if ( ! is_array( $parsed ) ) wp_send_json_error( [ 'message' => 'AI could not read this file. ' . ( $debug ?: '' ) ] );

	$valid   = array_column( $fields, 'key' );
	$answers = [];
	foreach ( (array) ( $parsed['answers'] ?? [] ) as $k => $v ) {
		if ( in_array( $k, $valid, true ) ) $answers[ $k ] = $v;
	}

	$person = sanitize_text_field( (string) ( $parsed['person_name'] ?? '' ) );
	$loc    = sanitize_text_field( (string) ( $parsed['location_name'] ?? '' ) );
	$users  = sp_import_users();
	$muid   = sp_import_match( $person, $users );

	// Location: the store named in the file wins; else the matched person's profile location (what a
	// normal submission would stamp); else none.
	$mloc = sp_import_match( $loc, sp_import_locations() );
	if ( ! $mloc && $muid ) {
		foreach ( $users as $u ) if ( $u['id'] === $muid ) { $mloc = $u['location_id']; break; }
	}

	wp_send_json_success( [
		'person_name'         => $person,
		'location_name'       => $loc,
		'period_start'        => sp_import_clean_date( $parsed['period_start'] ?? '' ),
		'period_end'          => sp_import_clean_date( $parsed['period_end'] ?? '' ),
		'answers'             => (object) $answers,
		'matched_user_id'     => $muid,
		'matched_location_id' => $mloc,
	] );
}

add_action( 'wp_ajax_site_pulse_import_save', 'site_pulse_ajax_import_save' );
function site_pulse_ajax_import_save(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_import_gate() ) return;

	$tpl = sp_import_template();
	if ( ! $tpl ) wp_send_json_error( [ 'message' => 'Report template not found (or no longer active).' ] );
	$template_id = (int) $tpl['id'];

	$user_id     = (int) ( $_POST['user_id'] ?? 0 );
	$location_id = max( 0, (int) ( $_POST['location_id'] ?? 0 ) ); // optional — 0 = not tied to a store
	$start       = sp_import_clean_date( $_POST['period_start'] ?? '' );
	$end         = sp_import_clean_date( $_POST['period_end'] ?? '' );

	$answers_raw = $_POST['answers'] ?? '';
	if ( is_string( $answers_raw ) ) $answers_raw = json_decode( wp_unslash( $answers_raw ), true );
	$answers = is_array( $answers_raw ) ? $answers_raw : [];

	if ( $user_id <= 0 ) wp_send_json_error( [ 'message' => 'Pick who submitted it first.' ] );
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

	// Mark submitted as of the historical period end — NO action items, NO notifications.
	global $wpdb;
	$wpdb->update(
		site_pulse_table( 'reports' ),
		[ 'status' => 'submitted', 'submitted_at' => $end . ' 12:00:00', 'updated_at' => current_time( 'mysql' ) ],
		[ 'id' => $report_id ],
		[ '%s', '%s', '%s' ],
		[ '%d' ]
	);

	site_pulse_log( 'report_imported', 'Imported a past ' . $tpl['name'] . ' (no action items generated)', [
		'report_id' => $report_id, 'user_id' => $user_id, 'location_id' => $location_id, 'template_id' => $template_id, 'period' => "$start..$end",
	] );

	wp_send_json_success( [ 'report_id' => $report_id ] );
}
