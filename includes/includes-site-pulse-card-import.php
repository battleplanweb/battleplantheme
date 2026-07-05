<?php
/* Battle Plan Web Design — Site Pulse: Import Comment Cards (GOD-only)

/*--------------------------------------------------------------
Lets the superadmin (god) digitize physical customer comment cards. Each file is
ONE card (image — JPG/PNG/HEIC — or a scanned PDF). Claude reads the card, pulls
the per-dimension ratings (1–5), the customer's contact info, visit date, and
comments; god reviews/corrects the attribution (location, name, date) in a
preview, then saves. Imported cards are written straight into the same
wp_site_pulse_surveys table the public survey forms feed — with **NO "new comment
card" notification** (they're being entered by hand, often after the fact), and
dated to the visit date when one is found.

Mirrors includes-site-pulse-import.php (Import Past Reports) and reuses its
generic helpers (sp_import_gate, the Claude image/document callers,
sp_import_decode_json, sp_import_clean_date, sp_import_match, sp_import_locations)
plus the surveys module's sp_survey_store() insert.

Three god-gated AJAX endpoints:
  - site_pulse_card_import_meta   → rating dimensions + location list (preview dropdowns)
  - site_pulse_card_import_parse  → one file (base64) → AI-extracted card fields + best-guess location
  - site_pulse_card_import_save   → one confirmed card → insert survey row (no notification)
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/* ───────────────────────── AI: read a comment card ───────────────────────── */

// System + user prompt for extracting ONE comment card. $dims is key => label.
function sp_card_import_build_prompt( array $dims ): array {
	$dim_lines = [];
	foreach ( $dims as $key => $label ) $dim_lines[] = "- {$key}: {$label}";

	$system = 'You read a single restaurant customer comment card and output structured JSON. '
		. 'Output ONLY valid JSON — no prose, no code fences.';

	$prompt = "This image/document is ONE customer comment card. Extract it into a JSON object with exactly this shape:\n"
		. "{\n"
		. "  \"location_name\": \"\",   // the restaurant/store named or printed on the card (\"\" if none)\n"
		. "  \"customer_name\": \"\",   // the customer's name (\"\" if blank)\n"
		. "  \"email\": \"\",           // customer email (\"\" if blank)\n"
		. "  \"phone\": \"\",           // customer phone (\"\" if blank)\n"
		. "  \"address\": \"\",         // street address (\"\" if blank)\n"
		. "  \"city\": \"\",\n"
		. "  \"state\": \"\",\n"
		. "  \"zip\": \"\",\n"
		. "  \"experience\": \"\",      // visit type if shown, e.g. Dine In / Take Out / Delivery / Drive Thru (\"\" if none)\n"
		. "  \"visit_date\": \"\",      // date of the visit as YYYY-MM-DD (\"\" if unknown)\n"
		. "  \"referral\": \"\",        // how they heard of us / what brought them in (\"\" if none)\n"
		. "  \"comments\": \"\",        // any written comments, verbatim (\"\" if none)\n"
		. "  \"ratings\": {}            // map of dimension_key => integer 1-5, only for ratings actually marked\n"
		. "}\n\n"
		. "RATINGS RULES: every rating value MUST be an integer from 1 to 5 (5 = best). If the card uses words or "
		. "faces instead of numbers, convert them: Excellent/Great/Very Satisfied/☺ = 5, Good/Satisfied = 4, "
		. "Average/Fair/OK/Neutral = 3, Poor/Below Average/Dissatisfied = 2, Terrible/Very Poor/Very Dissatisfied/☹ = 1. "
		. "If a card uses a different scale (e.g. 1-10), rescale to 1-5. Omit any dimension that isn't marked.\n\n"
		. "Use these EXACT dimension keys:\n"
		. implode( "\n", $dim_lines )
		. "\n\nOutput ONLY the JSON object.";

	return [ $system, $prompt ];
}

// Parse one file (base64) → decoded array or null. Images go through the vision caller; scanned PDFs
// through the document caller. Both live in includes-site-pulse-import.php.
function sp_card_import_parse_file( string $b64, string $mime, array $dims, &$debug = null ) {
	list( $system, $prompt ) = sp_card_import_build_prompt( $dims );

	if ( stripos( $mime, 'pdf' ) !== false ) {
		$raw = site_pulse_call_claude_document( $b64, $prompt, $system, [ 'max_tokens' => 2048 ], $debug );
	} else {
		$media = ( stripos( $mime, 'png' ) !== false ) ? 'image/png' : 'image/jpeg';
		$raw   = site_pulse_call_claude_image( $b64, $media, $prompt, $system, [ 'max_tokens' => 2048 ], $debug );
	}
	if ( ! $raw ) return null;
	return sp_import_decode_json( $raw );
}

// Keep only valid integer 1-5 ratings, keyed by a known dimension. Tolerates word values the model
// may have slipped through (defensive — the prompt asks for integers).
function sp_card_import_clean_ratings( $raw, array $dims ): array {
	$out = [];
	foreach ( (array) $raw as $k => $v ) {
		$key = sanitize_key( $k );
		if ( ! isset( $dims[ $key ] ) ) continue;
		$n = (int) $v;
		if ( $n >= 1 && $n <= 5 ) $out[ $key ] = $n;
	}
	return $out;
}


/* ───────────────────────── AJAX (all god-only) ───────────────────────── */

add_action( 'wp_ajax_site_pulse_card_import_meta', 'site_pulse_ajax_card_import_meta' );
function site_pulse_ajax_card_import_meta(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_import_gate() ) return;

	wp_send_json_success( [
		'dimensions' => sp_survey_dimensions(),   // key => label, canonical display order
		'locations'  => sp_import_locations(),     // [{id,name}] for the location dropdown
		'has_api_key' => (bool) site_pulse_get_api_key(),
	] );
}

add_action( 'wp_ajax_site_pulse_card_import_parse', 'site_pulse_ajax_card_import_parse' );
function site_pulse_ajax_card_import_parse(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_import_gate() ) return;

	$dims = sp_survey_dimensions();

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
	$parsed = sp_card_import_parse_file( $b64, $mime, $dims, $debug );
	if ( ! is_array( $parsed ) ) wp_send_json_error( [ 'message' => 'AI could not read this card. ' . ( $debug ?: '' ) ] );

	$loc = sanitize_text_field( (string) ( $parsed['location_name'] ?? '' ) );
	$mid = sp_import_match( $loc, sp_import_locations() );   // 0 if nothing close
	$matched_name = '';
	if ( $mid ) {
		foreach ( sp_import_locations() as $l ) if ( (int) $l['id'] === $mid ) { $matched_name = $l['name']; break; }
	}

	wp_send_json_success( [
		'location_name'  => $loc,
		'matched_location' => $matched_name,                                   // canonical name to preselect
		'customer_name'  => sanitize_text_field( (string) ( $parsed['customer_name'] ?? '' ) ),
		'email'          => sanitize_email(      (string) ( $parsed['email']    ?? '' ) ),
		'phone'          => sanitize_text_field( (string) ( $parsed['phone']    ?? '' ) ),
		'address'        => sanitize_text_field( (string) ( $parsed['address']  ?? '' ) ),
		'city'           => sanitize_text_field( (string) ( $parsed['city']     ?? '' ) ),
		'state'          => sanitize_text_field( (string) ( $parsed['state']    ?? '' ) ),
		'zip'            => sanitize_text_field( (string) ( $parsed['zip']      ?? '' ) ),
		'experience'     => sanitize_text_field( (string) ( $parsed['experience'] ?? '' ) ),
		'visit_date'     => sp_import_clean_date( $parsed['visit_date'] ?? '' ),
		'referral'       => sanitize_text_field( (string) ( $parsed['referral'] ?? '' ) ),
		'comments'       => sanitize_textarea_field( (string) ( $parsed['comments'] ?? '' ) ),
		'ratings'        => (object) sp_card_import_clean_ratings( $parsed['ratings'] ?? [], $dims ),
	] );
}

add_action( 'wp_ajax_site_pulse_card_import_save', 'site_pulse_ajax_card_import_save' );
function site_pulse_ajax_card_import_save(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_import_gate() ) return;

	// The full AI extraction (JSON) is the base; the explicit POST fields are god's edits/overrides.
	$card_raw = $_POST['card'] ?? '';
	if ( is_string( $card_raw ) ) $card_raw = json_decode( wp_unslash( $card_raw ), true );
	$card = is_array( $card_raw ) ? $card_raw : [];

	$dims    = sp_survey_dimensions();
	$ratings = sp_card_import_clean_ratings( $card['ratings'] ?? [], $dims );

	$visit = sp_import_clean_date( $_POST['visit_date'] ?? ( $card['visit_date'] ?? '' ) );

	$data = [
		'location'    => sanitize_text_field( wp_unslash( $_POST['location'] ?? ( $card['location_name'] ?? '' ) ) ),
		'name'        => sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? ( $card['customer_name'] ?? '' ) ) ),
		'email'       => sanitize_email(      wp_unslash( $_POST['email'] ?? ( $card['email'] ?? '' ) ) ),
		'phone'       => sanitize_text_field( wp_unslash( $_POST['phone'] ?? ( $card['phone'] ?? '' ) ) ),
		'address'     => sanitize_text_field( (string) ( $card['address'] ?? '' ) ),
		'city'        => sanitize_text_field( (string) ( $card['city']    ?? '' ) ),
		'state'       => sanitize_text_field( (string) ( $card['state']   ?? '' ) ),
		'zip'         => sanitize_text_field( (string) ( $card['zip']     ?? '' ) ),
		'experience'  => sanitize_text_field( (string) ( $card['experience'] ?? '' ) ),
		'visit_date'  => $visit,
		'referral'    => sanitize_text_field( (string) ( $card['referral'] ?? '' ) ),
		'comments'    => sanitize_textarea_field( (string) ( $card['comments'] ?? '' ) ),
		'ratings'     => $ratings,
		'site'        => 'Imported comment card',                          // source_site marker
		// Date the row to the visit when known (drives the Surveys list/analytics date ranges);
		// otherwise sp_survey_store falls back to now.
		'created_at'  => $visit ? ( $visit . ' 12:00:00' ) : '',
	];

	if ( ! $ratings && '' === trim( (string) $data['comments'] ) ) {
		wp_send_json_error( [ 'message' => 'Nothing to save — no ratings or comments were read from this card.' ] );
	}

	$id = sp_survey_store( $data, false );   // false = do NOT fire the "new comment card" notification
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Could not save the comment card.' ] );

	site_pulse_log( 'survey_imported', 'Imported a customer comment card (no notification)', [
		'survey_id' => $id, 'location' => $data['location'], 'visit_date' => $visit,
	] );

	wp_send_json_success( [ 'survey_id' => $id ] );
}
