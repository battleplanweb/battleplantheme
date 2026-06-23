<?php
/* Battle Plan Web Design — Site Pulse: Reports AI

/*--------------------------------------------------------------
AI over the GM/Supervisor report corpus. Approach (Option B): condense each report ONCE into a compact
structured digest (summary + per-category 1-5 scores + wins/issues/keywords) cached on the report row.
That digest is the searchable "index" — a question routes to the relevant categories + time window +
keywords, we pull just those reports, and Sonnet analyzes only that subset.

Phase 1 (this file, for now): the digest/condensation engine + backfill + cron. Search + Ask-AI build
on these digests next.

Digesting uses Haiku (cheap, one-time per report); the live Q&A synthesis will use Sonnet.
Everything here is GOD-only to start (per rollout decision).
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

function sp_reports_table(): string {
	return site_pulse_table( 'reports' );
}

/** Canonical digest categories (the consistent axis trends aggregate on). Site-overridable. */
function sp_reports_digest_categories(): array {
	return apply_filters( 'site_pulse_report_digest_categories', [
		'Guest Service', 'Food Quality', 'Cleanliness', 'Training', 'Staffing',
		'Sales & Revenue', 'Operations', 'Facilities & Maintenance', 'Team Morale', 'Local Marketing',
	] );
}

/** Resolve a range key (90/180/365/730 days) to a UTC "on or after" date; '' = no lower bound. */
function sp_reports_range_start( string $range ): string {
	$days = [ '90' => 90, '180' => 180, '365' => 365, '730' => 730 ];
	if ( ! isset( $days[ $range ] ) ) return '';
	return gmdate( 'Y-m-d', current_time( 'timestamp', true ) - $days[ $range ] * DAY_IN_SECONDS );
}

/** Submitted/reviewed reports not yet condensed. (Drafts are skipped — nothing final to index.) */
function sp_reports_undigested_count(): int {
	global $wpdb;
	$t = sp_reports_table();
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE ai_digest IS NULL AND status IN ('submitted','reviewed')" );
}


/*--------------------------------------------------------------
# Condense one report → structured digest
--------------------------------------------------------------*/

/** Render a report (metadata + the manager's answers) as plain text for the model to condense. */
function sp_reports_build_text( array $report ): string {
	global $wpdb;
	$answers = site_pulse_get_report_answers( (int) $report['id'] );
	$fields  = site_pulse_get_template_fields( (int) $report['template_id'] );

	$by_key = [];
	foreach ( $answers as $a ) { $by_key[ $a['field_key'] ] = $a; }

	$tpl  = $wpdb->get_row( $wpdb->prepare(
		'SELECT name, required_role_slug FROM ' . site_pulse_table( 'report_templates' ) . ' WHERE id = %d',
		(int) $report['template_id']
	), ARRAY_A );
	$type = ( $tpl && ( $tpl['required_role_slug'] ?? '' ) === 'supervisor' ) ? 'Supervisor' : 'GM/Manager';

	$loc = (int) $report['location_id']
		? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . site_pulse_table( 'locations' ) . ' WHERE id = %d', (int) $report['location_id'] ) )
		: '';
	$author = get_userdata( (int) $report['user_id'] );

	$out  = "Report type: {$type} bi-weekly\n";
	if ( $loc )    $out .= "Location: {$loc}\n";
	if ( $author ) $out .= 'Author: ' . $author->display_name . "\n";
	$out .= 'Period: ' . $report['report_period_start'] . ' to ' . $report['report_period_end'] . "\n\n";

	foreach ( $fields as $f ) {
		$a = $by_key[ $f['field_key'] ] ?? null;
		if ( ! $a ) continue;
		$val = '';
		if ( $a['answer_text'] !== null && $a['answer_text'] !== '' ) {
			$val = (string) $a['answer_text'];
		} elseif ( $a['answer_numeric'] !== null ) {
			$val = (string) ( 0 + $a['answer_numeric'] );
		} elseif ( ! empty( $a['answer_json'] ) ) {
			$j   = json_decode( (string) $a['answer_json'], true );
			$val = is_array( $j ) ? implode( ', ', $j ) : (string) $a['answer_json'];
		}
		if ( trim( $val ) === '' ) continue;
		$out .= $f['label'] . ":\n" . $val . "\n\n";
	}
	return trim( $out );
}

/** Pull the digest JSON out of the model reply and normalize it; null on a true parse failure. */
function sp_reports_parse_digest( string $reply ): ?array {
	$s     = preg_replace( '/```(?:json)?/i', '', trim( $reply ) );
	$start = strpos( $s, '{' );
	$end   = strrpos( $s, '}' );
	if ( false === $start || false === $end || $end <= $start ) return null;
	$d = json_decode( substr( $s, $start, $end - $start + 1 ), true );
	if ( ! is_array( $d ) ) return null;

	$cats = [];
	foreach ( (array) ( $d['categories'] ?? [] ) as $c ) {
		if ( ! is_array( $c ) ) continue;
		$label = isset( $c['label'] ) ? trim( (string) $c['label'] ) : '';
		if ( '' === $label ) continue;
		$score = (int) ( $c['score'] ?? 0 );
		$score = max( 1, min( 5, $score ) );
		$sent  = strtolower( (string) ( $c['sentiment'] ?? '' ) );
		if ( ! in_array( $sent, [ 'positive', 'neutral', 'negative' ], true ) ) $sent = 'neutral';
		$cats[] = [ 'label' => $label, 'score' => $score, 'sentiment' => $sent, 'note' => isset( $c['note'] ) ? trim( (string) $c['note'] ) : '' ];
		if ( count( $cats ) >= 12 ) break;
	}

	$clean = function ( $arr, $n ) {
		$out = array_filter( array_map( fn( $x ) => trim( (string) $x ), (array) $arr ), fn( $x ) => '' !== $x );
		return array_slice( array_values( $out ), 0, $n );
	};

	return [
		'summary'    => isset( $d['summary'] ) ? trim( (string) $d['summary'] ) : '',
		'categories' => $cats,
		'wins'       => $clean( $d['wins'] ?? [], 6 ),
		'issues'     => $clean( $d['issues'] ?? [], 6 ),
		'keywords'   => array_map( 'strtolower', $clean( $d['keywords'] ?? [], 12 ) ),
	];
}

/**
 * Condense one report with Haiku and cache the digest on its row. Returns the digest array, or a
 * WP_Error ONLY on a transient API failure (so the caller can retry). A parse miss stores an empty
 * digest so the report isn't retried forever.
 */
function sp_reports_generate_digest( int $report_id ) {
	$report = site_pulse_get_report( $report_id );
	if ( ! $report ) return new WP_Error( 'sp_no_report', 'Report not found.' );

	$text = sp_reports_build_text( $report );
	$cats = implode( ', ', sp_reports_digest_categories() );

	$debug = null;
	$reply = ( '' === $text )
		? '{}' // nothing written in the report — store an empty digest rather than spend a call
		: site_pulse_call_claude(
			"Categories: {$cats}\n\nReport:\n{$text}",
			site_pulse_prompt_report_digest( $cats ),
			[ 'model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 1200 ],
			$debug
		);

	if ( null === $reply ) return new WP_Error( 'sp_digest_ai', $debug ?: 'AI request failed.' );

	$digest = sp_reports_parse_digest( $reply );
	if ( null === $digest ) $digest = [ 'summary' => '', 'categories' => [], 'wins' => [], 'issues' => [], 'keywords' => [] ];

	global $wpdb;
	$wpdb->update( sp_reports_table(), [
		'ai_digest'    => wp_json_encode( $digest ),
		'ai_digest_at' => current_time( 'mysql' ),
	], [ 'id' => $report_id ] );

	return $digest;
}

/**
 * Condense up to $max not-yet-digested reports (newest first), bounded by a wall-clock budget.
 * Returns [ 'done' => int, 'remaining' => int, 'error' => ?string ].
 */
function sp_reports_digest_batch( int $max = 20 ): array {
	if ( ! site_pulse_get_api_key() ) {
		return [ 'done' => 0, 'remaining' => sp_reports_undigested_count(), 'error' => 'No AI API key configured.' ];
	}

	global $wpdb;
	$t        = sp_reports_table();
	$done     = 0;
	$deadline = time() + 25; // keeps one request under the host PHP timeout; the client loops
	if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 60 );

	while ( $done < $max && time() < $deadline ) {
		$id = (int) $wpdb->get_var(
			"SELECT id FROM $t WHERE ai_digest IS NULL AND status IN ('submitted','reviewed') ORDER BY report_period_end DESC, id DESC LIMIT 1"
		);
		if ( ! $id ) break;

		$res = sp_reports_generate_digest( $id );
		if ( is_wp_error( $res ) ) {
			// Transient API failure — stop and let the caller/cron retry these reports later.
			return [ 'done' => $done, 'remaining' => sp_reports_undigested_count(), 'error' => $res->get_error_message() ];
		}
		$done++;
	}

	return [ 'done' => $done, 'remaining' => sp_reports_undigested_count(), 'error' => null ];
}

/** Hourly cron: condense any reports still missing a digest (newly submitted ones, backlog). */
add_action( 'site_pulse_digest_reports', 'sp_reports_digest_cron' );
function sp_reports_digest_cron(): void {
	if ( sp_reports_undigested_count() < 1 ) return;
	sp_reports_digest_batch( 30 );
}


/*--------------------------------------------------------------
# AJAX — backfill the report index (GOD only to start)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_reports_digest_batch', 'site_pulse_ajax_reports_digest_batch' );
function site_pulse_ajax_reports_digest_batch(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god( get_current_user_id() ) ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	$res = sp_reports_digest_batch( 20 );
	if ( $res['error'] && 0 === $res['done'] ) wp_send_json_error( [ 'message' => $res['error'] ] );
	wp_send_json_success( $res );
}


/*--------------------------------------------------------------
# AJAX — keyword search across the report corpus (GOD only to start)
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_reports_search', 'site_pulse_ajax_reports_search' );
function site_pulse_ajax_reports_search(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god( get_current_user_id() ) ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );

	global $wpdb;
	$rt = sp_reports_table();
	$at = site_pulse_table( 'report_answers' );
	$lt = site_pulse_table( 'locations' );
	$tt = site_pulse_table( 'report_templates' );

	$q     = trim( sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) ) );
	$type  = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) ); // 'manager' | 'supervisor' | ''
	$loc   = (int) ( $_POST['location_id'] ?? 0 );
	$start = sp_reports_range_start( sanitize_text_field( wp_unslash( $_POST['range'] ?? '' ) ) );

	$where = [ "r.status IN ('submitted','reviewed')", 'r.ai_digest IS NOT NULL' ];
	$args  = [];
	// Match the searchable digest (summary/keywords/category notes) OR any of the report's written answers.
	if ( '' !== $q ) {
		$like    = '%' . $wpdb->esc_like( $q ) . '%';
		$where[] = "( r.ai_digest LIKE %s OR EXISTS ( SELECT 1 FROM $at a WHERE a.report_id = r.id AND a.answer_text LIKE %s ) )";
		$args[]  = $like; $args[] = $like;
	}
	if ( in_array( $type, [ 'manager', 'supervisor' ], true ) ) { $where[] = 't.required_role_slug = %s'; $args[] = $type; }
	if ( $loc )           { $where[] = 'r.location_id = %d';        $args[] = $loc; }
	if ( '' !== $start )  { $where[] = 'r.report_period_end >= %s'; $args[] = $start; }

	$sql = "SELECT r.id, r.location_id, r.report_period_start, r.report_period_end, r.status, r.ai_digest,
	               l.name AS location_name, u.display_name AS author_name, t.required_role_slug AS role
	        FROM $rt r
	        LEFT JOIN $lt l ON l.id = r.location_id
	        LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
	        LEFT JOIN $tt t ON t.id = r.template_id
	        WHERE " . implode( ' AND ', $where ) . "
	        ORDER BY r.report_period_end DESC, r.id DESC LIMIT 80";
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	$rows = $rows ?: [];

	$results = [];
	foreach ( $rows as $r ) {
		$d    = json_decode( (string) $r['ai_digest'], true );
		$d    = is_array( $d ) ? $d : [];
		$cats = [];
		foreach ( (array) ( $d['categories'] ?? [] ) as $c ) {
			if ( ! is_array( $c ) || empty( $c['label'] ) ) continue;
			$cats[] = [ 'label' => (string) $c['label'], 'score' => (int) ( $c['score'] ?? 0 ), 'sentiment' => (string) ( $c['sentiment'] ?? 'neutral' ) ];
			if ( count( $cats ) >= 6 ) break;
		}
		$results[] = [
			'id'           => (int) $r['id'],
			'type'         => ( ( $r['role'] ?? '' ) === 'supervisor' ) ? 'Supervisor' : 'GM',
			'location'     => (string) ( $r['location_name'] ?? '' ),
			'author'       => (string) ( $r['author_name'] ?? '' ),
			'period_start' => $r['report_period_start'],
			'period_end'   => $r['report_period_end'],
			'status'       => $r['status'],
			'summary'      => (string) ( $d['summary'] ?? '' ),
			'categories'   => $cats,
		];
	}

	// Distinct report locations for the filter dropdown.
	$locations = $wpdb->get_results(
		"SELECT DISTINCT l.id, l.name FROM $rt r JOIN $lt l ON l.id = r.location_id WHERE r.location_id > 0 ORDER BY l.name",
		ARRAY_A
	) ?: [];

	wp_send_json_success( [ 'results' => $results, 'count' => count( $results ), 'locations' => $locations ] );
}


/*--------------------------------------------------------------
# AJAX — Ask AI: natural-language Q&A / trends over the digests (GOD only to start)
--------------------------------------------------------------*/

/** Render one report's digest as a compact block for the Q&A context. */
function sp_reports_digest_block( array $row ): string {
	$d = json_decode( (string) $row['ai_digest'], true );
	$d = is_array( $d ) ? $d : [];

	$type = ( ( $row['role'] ?? '' ) === 'supervisor' ) ? 'Supervisor' : 'GM';
	$loc  = (string) ( $row['location_name'] ?? '' );
	$head = "[{$type}" . ( $loc ? " | {$loc}" : '' ) . " | {$row['report_period_start']} to {$row['report_period_end']}]";

	$lines = [ $head ];
	if ( ! empty( $d['summary'] ) ) $lines[] = 'Summary: ' . $d['summary'];

	$scores = [];
	foreach ( (array) ( $d['categories'] ?? [] ) as $c ) {
		if ( empty( $c['label'] ) ) continue;
		$scores[] = $c['label'] . ' ' . (int) ( $c['score'] ?? 0 ) . '/5 (' . ( $c['sentiment'] ?? 'neutral' ) . ')';
	}
	if ( $scores ) $lines[] = 'Scores: ' . implode( ', ', $scores );

	if ( ! empty( $d['issues'] ) ) $lines[] = 'Issues: ' . implode( '; ', (array) $d['issues'] );
	if ( ! empty( $d['wins'] ) )   $lines[] = 'Wins: ' . implode( '; ', (array) $d['wins'] );
	// (keywords omitted here — they're for search routing, not needed in the Q&A context)

	return implode( "\n", $lines );
}

add_action( 'wp_ajax_site_pulse_reports_ask', 'site_pulse_ajax_reports_ask' );
function site_pulse_ajax_reports_ask(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_is_god( get_current_user_id() ) ) wp_send_json_error( [ 'message' => 'Not authorized.' ] );
	if ( ! site_pulse_get_api_key() ) wp_send_json_error( [ 'message' => 'No AI API key configured.' ] );

	$question = trim( sanitize_textarea_field( wp_unslash( $_POST['q'] ?? '' ) ) );
	if ( '' === $question ) wp_send_json_error( [ 'message' => 'Type a question first.' ] );
	if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 90 ); // analysis can take a while

	global $wpdb;
	$rt = sp_reports_table();
	$lt = site_pulse_table( 'locations' );
	$tt = site_pulse_table( 'report_templates' );

	$type  = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
	$loc   = (int) ( $_POST['location_id'] ?? 0 );
	$start = sp_reports_range_start( sanitize_text_field( wp_unslash( $_POST['range'] ?? '' ) ) );

	$where = [ "r.status IN ('submitted','reviewed')", 'r.ai_digest IS NOT NULL' ];
	$args  = [];
	if ( in_array( $type, [ 'manager', 'supervisor' ], true ) ) { $where[] = 't.required_role_slug = %s'; $args[] = $type; }
	if ( $loc )          { $where[] = 'r.location_id = %d';        $args[] = $loc; }
	if ( '' !== $start ) { $where[] = 'r.report_period_end >= %s'; $args[] = $start; }

	// Oldest-first so the model can reason about trends over time. Cap keeps the context within budget.
	$sql = "SELECT r.report_period_start, r.report_period_end, r.ai_digest, l.name AS location_name, t.required_role_slug AS role
	        FROM $rt r
	        LEFT JOIN $lt l ON l.id = r.location_id
	        LEFT JOIN $tt t ON t.id = r.template_id
	        WHERE " . implode( ' AND ', $where ) . "
	        ORDER BY r.report_period_end ASC, r.id ASC LIMIT 220";
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	$rows = $rows ?: [];

	if ( ! $rows ) wp_send_json_error( [ 'message' => 'No indexed reports in that scope yet — try a wider time range or run "Index reports for AI".' ] );

	$blocks = [];
	foreach ( $rows as $r ) { $blocks[] = sp_reports_digest_block( $r ); }
	$corpus = implode( "\n\n", $blocks );

	$prompt = "Question: {$question}\n\nReport digests (" . count( $rows ) . " reports, oldest first):\n\n" . $corpus;

	$debug = null;
	$answer = site_pulse_call_claude( $prompt, site_pulse_prompt_report_qa(), [
		'model'      => 'claude-sonnet-4-6',
		'max_tokens' => 1200,
		'timeout'    => 55, // Sonnet over a big digest set can run well past the 30s default
	], $debug );
	if ( null === $answer ) wp_send_json_error( [ 'message' => $debug ?: 'The AI request failed.' ] );

	site_pulse_log( 'reports_ask', 'Asked the report AI a question', [ 'reports' => count( $rows ) ] );

	wp_send_json_success( [ 'answer' => trim( $answer ), 'count' => count( $rows ) ] );
}
