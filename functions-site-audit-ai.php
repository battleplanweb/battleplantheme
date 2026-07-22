<?php
/* Battle Plan Web Design: AI Site Audit
 *
 * Takes everything the NIGHTLY CHRON already collects (GA4, Search Console, PageSpeed/CWV, keyword
 * rankings, Google Business, backlinks, content counts, Clarity) PLUS a structural crawl of the
 * site's main pages, and asks Claude for a plain-English audit: what the site does well and
 * where it can improve — across UX, conversion potential, design/layout, content and SEO.
 *
 * The audit collects NO metrics of its own — it only reads the chron's stores.
 *
 * Entry point:  bp_audit_ai_report()   — called by bp_run_site_audit()
 * History:      get_option('bp_site_audit_ai_history')  — date => report (capped at 40)
 *               report = { date, model, summary, scores{}, doing_well[], improve[], pages_analyzed[] }
 * Helpers:      bp_audit_ai_latest() · bp_audit_ai_history()
 *
 * Requires an Anthropic key (BP_ANTHROPIC_API_KEY / ANTHROPIC_API_KEY) — same key the rest of
 * the framework's AI uses. With no key the audit simply skips the AI step (data still collects).
 */

if ( ! defined( 'BP_AUDIT_AI_MODEL' ) )     define( 'BP_AUDIT_AI_MODEL', 'claude-sonnet-4-6' );
if ( ! defined( 'BP_AUDIT_AI_MAX_PAGES' ) ) define( 'BP_AUDIT_AI_MAX_PAGES', 8 );
// Screenshots: the home page and ONE secondary page, each in mobile AND desktop = 4 shots.
// These sites are built from two templates — the home page looks one way, every secondary page
// looks another — so a second secondary page would just show Claude the same layout twice.
// Each shot is a PageSpeed call (~10-30s) and ~1.5k tokens.
if ( ! defined( 'BP_AUDIT_AI_MAX_SHOTS' ) ) define( 'BP_AUDIT_AI_MAX_SHOTS', 4 );
// How many past audits keep their screenshot files on disk.
if ( ! defined( 'BP_AUDIT_AI_KEEP_SHOTS' ) ) define( 'BP_AUDIT_AI_KEEP_SHOTS', 6 );


/*--------------------------------------------------------------
# Screenshots — reusing the headless Chrome we already pay for
# PageSpeed Insights runs Lighthouse (real headless Chrome) and hands back the rendered frame. We
# already call PSI for CWV in the chron, so grabbing a picture costs nothing extra.
--------------------------------------------------------------*/

/**
 * Above-the-fold render of a URL via PageSpeed/Lighthouse. Returns
 * [ mime, data(base64), url, strategy ] or null. Never throws.
 */
function bp_audit_screenshot( string $url, string $strategy = 'mobile' ): ?array {
	if ( ! defined( '_PLACES_API' ) || ! _PLACES_API ) return null;

	$api  = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . rawurlencode( $url )
		  . '&strategy=' . rawurlencode( $strategy )
		  . '&category=performance'
		  . '&key=' . _PLACES_API;
	// 45s, not 90: a single wedged PageSpeed call must not eat the whole run's budget.
	$resp = wp_remote_get( $api, [ 'timeout' => 45 ] );
	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) return null;

	$data   = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
	$audits = $data['lighthouseResult']['audits'] ?? [];

	// final-screenshot = the fully-loaded viewport, i.e. what a visitor sees above the fold — the
	// highest-signal frame for design critique. full-page-screenshot is a fallback; tall pages get
	// downsampled into mush once the vision API resizes them.
	$raw = $audits['final-screenshot']['details']['data']
		?? ( $audits['full-page-screenshot']['details']['screenshot']['data'] ?? '' );
	if ( ! is_string( $raw ) || '' === $raw ) return null;
	if ( ! preg_match( '#^data:(image/[a-z]+);base64,(.+)$#i', $raw, $m ) ) return null;

	return [ 'mime' => strtolower( $m[1] ), 'data' => $m[2], 'url' => $url, 'strategy' => $strategy ];
}

/** Write a screenshot into uploads/bp-audit/<stamp>/ so the report can show it. Returns the URL. */
function bp_audit_save_screenshot( array $shot, string $stamp ): string {
	$up = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) return '';

	$rel = 'bp-audit/' . $stamp;
	$dir = trailingslashit( $up['basedir'] ) . $rel;
	if ( ! wp_mkdir_p( $dir ) ) return '';

	$bin = base64_decode( $shot['data'] );
	if ( ! $bin ) return '';

	$slug = trim( (string) parse_url( $shot['url'], PHP_URL_PATH ), '/' );
	$slug = $slug ? sanitize_title( $slug ) : 'home';
	$ext  = ( false !== strpos( $shot['mime'], 'png' ) ) ? 'png' : 'jpg';
	$name = $slug . '-' . $shot['strategy'] . '.' . $ext;

	if ( false === file_put_contents( $dir . '/' . $name, $bin ) ) return '';
	return trailingslashit( $up['baseurl'] ) . $rel . '/' . $name;
}

/** Keep only the newest N screenshot folders so uploads can't grow forever. */
function bp_audit_prune_screenshots( int $keep = 0 ): void {
	$keep = $keep ?: (int) BP_AUDIT_AI_KEEP_SHOTS;
	$up   = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) return;

	$base = trailingslashit( $up['basedir'] ) . 'bp-audit';
	if ( ! is_dir( $base ) ) return;

	$dirs = array_values( array_filter( (array) glob( $base . '/*' ), 'is_dir' ) );
	if ( count( $dirs ) <= $keep ) return;

	sort( $dirs ); // folder names are timestamps, so this is oldest-first
	foreach ( array_slice( $dirs, 0, count( $dirs ) - $keep ) as $d ) {
		foreach ( (array) glob( $d . '/*' ) as $f ) { @unlink( $f ); }
		@rmdir( $d );
	}
}


/*--------------------------------------------------------------
# Page crawl — the structural signals an auditor would eyeball
--------------------------------------------------------------*/

/**
 * Which pages to analyse: the home page first, then the best-performing pages from GA4 (so the
 * audit talks about pages that actually get traffic), topped up with published pages in menu order.
 */
function bp_audit_pick_pages( int $max = 0 ): array {
	$max  = $max ?: (int) BP_AUDIT_AI_MAX_PAGES;
	$home = trailingslashit( home_url( '/' ) );
	$urls = [ $home ];

	// Best-performing pages come from the chron's GA4 page history (newest day-keyed entry).
	$top = [];
	$hist = get_option( 'bp_ga4_pages_history' );
	if ( is_array( $hist ) && $hist ) { ksort( $hist ); $last = end( $hist ); if ( is_array( $last ) ) $top = $last; }
	if ( is_array( $top ) ) {
		foreach ( array_keys( $top ) as $slug ) {
			$slug = trim( (string) $slug );
			if ( '' === $slug || '/' === $slug ) continue;
			$u = home_url( '/' . ltrim( $slug, '/' ) );
			if ( ! in_array( $u, $urls, true ) ) $urls[] = $u;
			if ( count( $urls ) >= $max ) break;
		}
	}

	if ( count( $urls ) < $max ) {
		$pages = get_posts( [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => $max * 2,
			'orderby'     => 'menu_order title',
			'order'       => 'ASC',
		] );
		foreach ( $pages as $p ) {
			$u = get_permalink( $p );
			if ( $u && ! in_array( $u, $urls, true ) ) $urls[] = $u;
			if ( count( $urls ) >= $max ) break;
		}
	}

	return array_slice( $urls, 0, $max );
}

/** Fetch one page and pull out the structure/UX signals worth auditing. */
function bp_audit_analyze_page( string $url ): array {
	$res = wp_remote_get( $url, [
		'timeout'     => 12,
		'redirection' => 3,
		'user-agent'  => 'BattlePlan-SiteAudit/1.0',
	] );
	if ( is_wp_error( $res ) ) return [ 'url' => $url, 'error' => $res->get_error_message() ];

	$code = (int) wp_remote_retrieve_response_code( $res );
	$html = (string) wp_remote_retrieve_body( $res );
	if ( 200 !== $code || '' === $html ) return [ 'url' => $url, 'error' => 'HTTP ' . $code ];

	$prev = libxml_use_internal_errors( true );
	$doc  = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$xp = new DOMXPath( $doc );

	$clean = static function ( $node ) {
		return $node ? trim( preg_replace( '/\s+/', ' ', $node->textContent ) ) : '';
	};

	$title = $clean( $xp->query( '//title' )->item( 0 ) );

	$meta_desc = '';
	foreach ( $xp->query( '//meta[@name="description"]/@content' ) as $a ) { $meta_desc = trim( $a->nodeValue ); break; }

	$h1 = [];
	foreach ( $xp->query( '//h1' ) as $n ) { $t = $clean( $n ); if ( '' !== $t ) $h1[] = $t; }
	$h2 = [];
	foreach ( $xp->query( '//h2' ) as $n ) { $t = $clean( $n ); if ( '' !== $t ) $h2[] = $t; if ( count( $h2 ) >= 12 ) break; }

	// Visible-ish word count (drop script/style/noscript so nav+content is what's measured).
	foreach ( $xp->query( '//script | //style | //noscript' ) as $junk ) { $junk->parentNode->removeChild( $junk ); }
	$body_node = $xp->query( '//body' )->item( 0 );
	$body_text = $body_node ? preg_replace( '/\s+/', ' ', $body_node->textContent ) : '';
	$words     = $body_text ? count( preg_split( '/\s+/', trim( $body_text ) ) ) : 0;

	$imgs = $xp->query( '//img' );
	$img_total = $imgs->length;
	$img_noalt = 0;
	foreach ( $imgs as $img ) { if ( '' === trim( $img->getAttribute( 'alt' ) ) ) $img_noalt++; }

	// Calls to action — buttons/links whose wording asks for the next step.
	$ctas = [];
	foreach ( $xp->query( '//a | //button' ) as $n ) {
		$t = $clean( $n );
		if ( '' === $t || mb_strlen( $t ) > 40 ) continue;
		if ( preg_match( '/\b(call|contact|quote|estimate|book|schedule|appointment|get started|request|apply|buy|order|shop|sign ?up|subscribe|learn more|free|today)\b/i', $t ) ) {
			$ctas[ $t ] = true;
			if ( count( $ctas ) >= 12 ) break;
		}
	}

	// Structured data types present. Detection has to be bulletproof — a false "no schema" is worse
	// than useless, it makes the whole audit look wrong. Walk JSON-LD (incl. nested @graph and any
	// depth of nested @type), and fall back to microdata (itemtype) so a plugin that emits schema.org
	// markup the non-JSON way still counts.
	$schema      = [];
	$jsonld_seen = 0;
	$collect_type = static function ( $node ) use ( &$collect_type, &$schema ) {
		if ( ! is_array( $node ) ) return;
		if ( ! empty( $node['@type'] ) ) {
			foreach ( (array) $node['@type'] as $t ) {
				$t = trim( (string) $t );
				if ( '' !== $t ) $schema[ $t ] = true;
			}
		}
		foreach ( $node as $v ) { if ( is_array( $v ) ) $collect_type( $v ); }
	};
	foreach ( $xp->query( '//script[@type="application/ld+json"]' ) as $s ) {
		$jsonld_seen++;
		$raw = trim( (string) $s->textContent );
		$j   = json_decode( $raw, true );
		if ( ! is_array( $j ) ) {
			// DOMDocument can HTML-decode entities inside the script; retry on the decoded string.
			$j = json_decode( html_entity_decode( $raw, ENT_QUOTES ), true );
		}
		if ( is_array( $j ) ) $collect_type( $j );
	}
	// Microdata fallback (itemscope + itemtype="https://schema.org/LocalBusiness" …).
	foreach ( $xp->query( '//*[@itemtype]/@itemtype' ) as $a ) {
		$t = trim( basename( (string) $a->nodeValue ) );
		if ( '' !== $t ) $schema[ $t ] = true;
	}

	$host = parse_url( home_url(), PHP_URL_HOST );
	$in = 0; $out = 0;
	foreach ( $xp->query( '//a/@href' ) as $a ) {
		$h = (string) $a->nodeValue;
		if ( '' === $h || 0 === strpos( $h, '#' ) || 0 === strpos( $h, 'tel:' ) || 0 === strpos( $h, 'mailto:' ) ) continue;
		$hh = parse_url( $h, PHP_URL_HOST );
		if ( ! $hh || $hh === $host ) $in++; else $out++;
	}

	return [
		'url'                => $url,
		'title'              => $title,
		'title_len'          => mb_strlen( $title ),
		'meta_description'   => $meta_desc,
		'meta_len'           => mb_strlen( $meta_desc ),
		'has_viewport'       => $xp->query( '//meta[@name="viewport"]' )->length > 0,
		'h1'                 => $h1,
		'h1_count'           => count( $h1 ),
		'h2'                 => $h2,
		'word_count'         => $words,
		'images'             => $img_total,
		'images_missing_alt' => $img_noalt,
		'forms'              => $xp->query( '//form' )->length,
		'tel_links'          => $xp->query( '//a[starts-with(@href,"tel:")]' )->length,
		'mailto_links'       => $xp->query( '//a[starts-with(@href,"mailto:")]' )->length,
		'ctas'               => array_slice( array_keys( $ctas ), 0, 12 ),
		'schema_types'       => array_slice( array_keys( $schema ), 0, 12 ),
		'has_schema'         => ! empty( $schema ),
		'links_internal'     => $in,
		'links_external'     => $out,
		'html_kb'            => (int) round( strlen( $html ) / 1024 ),
	];
}

/** Crawl the chosen pages. Bounded + tolerant — a failed page is reported, never fatal. */
function bp_audit_page_analysis(): array {
	$out = [];
	foreach ( bp_audit_pick_pages() as $url ) {
		$out[] = bp_audit_analyze_page( $url );
	}
	return $out;
}


/*--------------------------------------------------------------
# Claude
--------------------------------------------------------------*/

function bp_audit_ai_key(): string {
	if ( function_exists( 'bp_ai_alt_api_key' ) ) { $k = bp_ai_alt_api_key(); if ( $k ) return (string) $k; }
	if ( defined( 'BP_ANTHROPIC_API_KEY' ) && BP_ANTHROPIC_API_KEY ) return (string) BP_ANTHROPIC_API_KEY;
	if ( defined( 'ANTHROPIC_API_KEY' ) && ANTHROPIC_API_KEY ) return (string) ANTHROPIC_API_KEY;
	return '';
}

/**
 * One Claude messages call. $images = [ [mime,data,url,strategy], … ] are sent as real image blocks
 * (each labelled with its page) so the model can critique the actual design. Returns text or WP_Error.
 */
function bp_audit_ai_call( string $system, string $user, array $images = [], int $max_tokens = 5000 ) {
	$key = bp_audit_ai_key();
	if ( '' === $key ) return new WP_Error( 'bp_audit_no_key', 'No Anthropic API key configured.' );

	$content = [];
	foreach ( $images as $img ) {
		if ( empty( $img['data'] ) || empty( $img['mime'] ) ) continue;
		$content[] = [
			'type' => 'text',
			'text' => sprintf(
				'Screenshot — %s, above the fold: %s',
				$img['label'] ?: ( $img['strategy'] ?? 'mobile' ),
				$img['url'] ?? ''
			),
		];
		$content[] = [
			'type'   => 'image',
			'source' => [ 'type' => 'base64', 'media_type' => $img['mime'], 'data' => $img['data'] ],
		];
	}
	$content[] = [ 'type' => 'text', 'text' => $user ];

	$res = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 180,
		'headers' => [
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		],
		'body' => wp_json_encode( [
			'model'      => BP_AUDIT_AI_MODEL,
			'max_tokens' => $max_tokens,
			'system'     => $system,
			'messages'   => [ [ 'role' => 'user', 'content' => $content ] ],
		] ),
	] );
	if ( is_wp_error( $res ) ) return $res;

	$status = (int) wp_remote_retrieve_response_code( $res );
	$body   = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $status ) {
		if ( function_exists( 'bp_ai_model_alert' ) ) bp_ai_model_alert( $status, $body, BP_AUDIT_AI_MODEL, 'Site audit' );
		return new WP_Error( 'bp_audit_api', 'Claude HTTP ' . $status . ' ' . ( $body['error']['message'] ?? '' ) );
	}

	$text = '';
	foreach ( (array) ( $body['content'] ?? [] ) as $c ) { if ( ( $c['type'] ?? '' ) === 'text' ) $text .= (string) $c['text']; }
	return trim( $text );
}


/*--------------------------------------------------------------
# The audit
--------------------------------------------------------------*/

/**
 * The audit's fact sheet — a DECISION-READY digest of what the nightly chron already collected.
 *
 * The model is bad at arithmetic buried in a big JSON blob and good at judgement, so we do ALL the
 * maths here: every metric arrives normalised (percentages, not raw counts), over the WIDEST window
 * available, with a trend vs the previous period and a plain-words sample-size verdict. The raw
 * option blobs are deliberately NOT passed through — the model should read conclusions, not go
 * digging and re-derive numbers it gets wrong.
 */
function bp_audit_ai_facts(): array {

	$opt = static fn( string $k ) => get_option( $k );

	// A YYYYMMDD day-stamp N days before "today" (site time). Strings compare correctly by date.
	$ymd = static fn( int $back = 0 ) => date( 'Ymd', (int) current_time( 'timestamp' ) - $back * DAY_IN_SECONDS );

	// Percentage-change label. Sub-3% reads "flat" so noise isn't dressed up as a trend; a rise
	// from zero reads "new".
	$trend = static function ( $cur, $prev ) {
		$cur = (float) $cur; $prev = (float) $prev;
		if ( $prev <= 0 ) return $cur > 0 ? 'new' : '—';
		$d = (int) round( ( $cur - $prev ) / $prev * 100 );
		if ( abs( $d ) < 3 ) return 'flat';
		return ( $d > 0 ? '+' : '' ) . $d . '%';
	};

	// Plain-words sample verdict. Every metric that rests on a count carries one so the model can't
	// build a conclusion on "1 session in 7 days".
	$sample = static function ( int $n, int $ok = 100, int $thin = 30 ) {
		if ( $n >= $ok )   return 'ok';
		if ( $n >= $thin ) return "modest ({$n}) — directional only";
		return "TOO FEW ({$n}) — not enough to conclude anything";
	};

	// Sum FLOW fields (per-day counts: clicks, calls…) over the last $days and the preceding equal
	// window, from a YYYYMMDD-keyed history. Returns cur/prev sums + how many days were present.
	$flow = static function ( $hist, int $days, array $fields ) use ( $ymd ) {
		$cur = array_fill_keys( $fields, 0.0 ); $prev = array_fill_keys( $fields, 0.0 );
		$curN = 0; $prevN = 0;
		if ( is_array( $hist ) ) {
			$c0 = $ymd( $days ); $p0 = $ymd( $days * 2 ); $t = $ymd( 0 );
			foreach ( $hist as $k => $rec ) {
				$key = preg_replace( '/\D/', '', (string) $k );
				if ( strlen( $key ) < 8 || ! is_array( $rec ) ) continue;
				$key = substr( $key, 0, 8 );
				if ( $key > $t ) continue;
				if ( $key >= $c0 )     { foreach ( $fields as $f ) $cur[ $f ]  += (float) ( $rec[ $f ] ?? 0 ); $curN++; }
				elseif ( $key >= $p0 ) { foreach ( $fields as $f ) $prev[ $f ] += (float) ( $rec[ $f ] ?? 0 ); $prevN++; }
			}
		}
		return [ 'cur' => $cur, 'prev' => $prev, 'cur_days' => $curN, 'prev_days' => $prevN ];
	};

	// Latest snapshot of a STOCK history (cumulative totals: backlinks, review count, band counts,
	// content counts) plus the entry nearest $days ago, for a delta.
	$stock = static function ( $hist, int $days ) use ( $ymd ) {
		if ( ! is_array( $hist ) || ! $hist ) return null;
		$norm = [];
		foreach ( $hist as $k => $rec ) {
			$key = preg_replace( '/\D/', '', (string) $k );
			if ( strlen( $key ) >= 8 && is_array( $rec ) ) $norm[ substr( $key, 0, 8 ) ] = $rec;
		}
		if ( ! $norm ) return null;
		ksort( $norm );
		$keys   = array_keys( $norm );
		$latest = end( $keys );
		$target = $ymd( $days ); $prevKey = null;
		foreach ( $keys as $key ) { if ( $key <= $target ) $prevKey = $key; }
		return [ 'now' => $norm[ $latest ], 'then' => $prevKey !== null ? $norm[ $prevKey ] : null ];
	};

	// Newest period of a period-keyed [period => [k=>v]] option (monthly YYYYMM, etc.).
	$latest_period = static function ( $hist ) {
		if ( ! is_array( $hist ) || ! $hist ) return null;
		$keys = array_keys( $hist ); sort( $keys );
		$k = end( $keys );
		return is_array( $hist[ $k ] ?? null ) ? $hist[ $k ] : null;
	};

	$drop = static fn( array $a ) => array_filter( $a, static fn( $v ) => ! is_null( $v ) );

	$s = [];

	/* ---- Traffic & engagement (GA4 rollups) ------------------------------- */
	$engaged30 = 0.0;
	$roll = $opt( 'bp_ga4_rollups_clean' );
	if ( is_array( $roll ) && is_array( $roll['this_month'] ?? null ) ) {
		$tm = $roll['this_month']; $lm = $roll['last_month'] ?? null; $tq = $roll['this_quarter'] ?? null;
		$sess      = (int) ( $tm['sessions'] ?? 0 );
		$engaged30 = (float) ( $tm['engagedSessions'] ?? 0 );
		$s['traffic'] = $drop( [
			'window'            => 'last 30 days (vs previous 30)',
			'sessions'          => $sess,
			'sessions_trend'    => is_array( $lm ) ? $trend( $sess, $lm['sessions'] ?? 0 ) : null,
			'sessions_last_90d' => is_array( $tq ) ? (int) ( $tq['sessions'] ?? 0 ) : null,
			'engaged_rate'      => round( (float) ( $tm['engagementRate'] ?? 0 ) ) . '%',
			'avg_session_sec'   => round( (float) ( $tm['avgSessionDuration'] ?? 0 ) ),
			'pages_per_session' => round( (float) ( $tm['pagesPerSession'] ?? 0 ), 1 ),
			'new_visitor_pct'   => round( (float) ( $tm['newUserPct'] ?? 0 ) ) . '%',
			'sample'            => $sample( $sess ),
		] );
	}

	/* ---- Traffic sources (channel mix, latest month) ---------------------- */
	$chan = $latest_period( $opt( 'bp_ga4_channel_history' ) );
	if ( is_array( $chan ) ) {
		$by = []; $tot = 0;
		foreach ( $chan as $name => $rec ) { $c = (int) ( $rec['sessions'] ?? 0 ); if ( $c > 0 ) { $by[ $name ] = $c; $tot += $c; } }
		if ( $tot > 0 ) {
			arsort( $by );
			$mix = [];
			foreach ( array_slice( $by, 0, 6, true ) as $name => $c ) $mix[ $name ] = round( $c / $tot * 100 ) . '%';
			$s['traffic_sources'] = [ 'window' => 'latest month, share of sessions', 'mix' => $mix ];
		}
	}

	/* ---- Leads & tracked components (GA4 achievement events) -------------- */
	$content = $opt( 'bp_ga4_content_clean' );
	if ( is_array( $content ) ) {
		$leads = []; $comps = []; $lead_total_30 = 0;
		foreach ( $content as $id => $w ) {
			if ( ! is_array( $w ) ) continue;
			$s30 = (int) ( $w['sessions-30'] ?? 0 ); $s90 = (int) ( $w['sessions-90'] ?? 0 );
			if ( 0 === strpos( (string) $id, 'conversion-' ) ) {
				$leads[ ucwords( str_replace( '-', ' ', substr( $id, 11 ) ) ) ] = [ 'last_30d' => $s30, 'last_90d' => $s90 ];
				$lead_total_30 += $s30;
			} elseif ( 0 === strpos( (string) $id, 'track-' ) ) {
				$reach = $engaged30 > 0 ? round( $s30 / $engaged30 * 100 ) . '% of engaged visitors' : null;
				$comps[ ucwords( str_replace( '-', ' ', substr( $id, 6 ) ) ) ] = $drop( [ 'last_30d' => $s30, 'last_90d' => $s90, 'reach' => $reach ] );
			}
		}
		if ( $leads ) {
			$s['leads'] = [
				'note'      => 'phone / email / form conversions — the numbers that matter most to the owner',
				'total_30d' => $lead_total_30,
				'per_week'  => round( $lead_total_30 / 4.3, 1 ),
				'by_type'   => $leads,
				'sample'    => $sample( $lead_total_30, 20, 5 ),
			];
		}
		if ( $comps ) $s['tracked_components'] = [ 'note' => 'how many visitors reached these on-page elements', 'by_element' => $comps ];
	}

	/* ---- Search Console (28-day flows, impression-weighted position) ------ */
	$gsc = $opt( 'bp_gsc_totals_history' );
	if ( is_array( $gsc ) ) {
		$aggr = static function ( int $startBack, int $endBack ) use ( $gsc, $ymd ) {
			$older = $ymd( $startBack ); $newer = $ymd( $endBack );
			$c = 0; $i = 0; $pw = 0.0;
			foreach ( $gsc as $k => $r ) {
				$key = preg_replace( '/\D/', '', (string) $k );
				if ( strlen( $key ) < 8 || ! is_array( $r ) ) continue;
				$key = substr( $key, 0, 8 );
				if ( $key >= $older && $key < $newer ) {
					$c += (int) ( $r['clicks'] ?? 0 ); $i += (int) ( $r['impressions'] ?? 0 );
					$pw += (float) ( $r['position'] ?? 0 ) * (int) ( $r['impressions'] ?? 0 );
				}
			}
			return [ 'clicks' => $c, 'impr' => $i, 'pos' => $i > 0 ? round( $pw / $i, 1 ) : null ];
		};
		$cur = $aggr( 28, 0 ); $prev = $aggr( 56, 28 );
		if ( $cur['impr'] > 0 ) {
			$s['search_console'] = $drop( [
				'window'         => 'last 28 days (vs previous 28)',
				'clicks'         => $cur['clicks'],
				'clicks_trend'   => $trend( $cur['clicks'], $prev['clicks'] ),
				'impressions'    => $cur['impr'],
				'ctr'            => round( $cur['clicks'] / max( 1, $cur['impr'] ) * 100, 1 ) . '%',
				'avg_position'   => $cur['pos'],
				'position_trend' => ( $cur['pos'] && $prev['pos'] ) ? ( $cur['pos'] < $prev['pos'] ? 'improving' : ( $cur['pos'] > $prev['pos'] ? 'slipping' : 'flat' ) ) : null,
				'sample'         => $sample( $cur['clicks'], 30, 5 ),
			] );
		}
	}

	/* ---- Keyword rankings (band snapshot + ~30-day move) ------------------ */
	$kb = $stock( $opt( 'bp_kw_bands_history' ), 30 );
	if ( $kb && is_array( $kb['now'] ) ) {
		$now = $kb['now']; $then = is_array( $kb['then'] ) ? $kb['then'] : [];
		$page1 = (int) ( $now['b1'] ?? 0 ) + (int) ( $now['b2'] ?? 0 );
		$s['keyword_rankings'] = $drop( [
			'window'           => 'tracked keywords, now (vs ~30 days ago)',
			'top_3'            => (int) ( $now['b1'] ?? 0 ),
			'first_page'       => $page1,
			'pos_11_20'        => (int) ( $now['b3'] ?? 0 ),
			'pos_21_plus'      => (int) ( $now['b4'] ?? 0 ),
			'first_page_trend' => $then ? $trend( $page1, (int) ( $then['b1'] ?? 0 ) + (int) ( $then['b2'] ?? 0 ) ) : null,
		] );
	}
	if ( function_exists( 'bp_kw_tracked' ) ) {
		$kw = bp_kw_tracked();
		if ( is_array( $kw ) && $kw ) {
			if ( ! isset( $s['keyword_rankings'] ) ) $s['keyword_rankings'] = [];
			$s['keyword_rankings']['total_tracked'] = count( $kw );
		}
	}

	/* ---- Backlinks -------------------------------------------------------- */
	$bl = $stock( $opt( 'bp_gsc_links_history' ), 30 );
	if ( $bl && is_array( $bl['now'] ) ) {
		$s['backlinks'] = $drop( [
			'window'          => 'now (vs ~30 days ago)',
			'total_backlinks' => (int) ( $bl['now']['backlinks'] ?? 0 ),
			'linking_domains' => (int) ( $bl['now']['domains'] ?? 0 ),
			'backlinks_trend' => is_array( $bl['then'] ) ? $trend( $bl['now']['backlinks'] ?? 0, $bl['then']['backlinks'] ?? 0 ) : null,
		] );
	}

	/* ---- Google Business Profile (performance flow + reviews stock) ------- */
	$perf = $flow( $opt( 'bp_gbp_perf_history' ), 30, [ 'calls', 'website', 'directions', 'impressions' ] );
	$gbp  = [];
	if ( $perf['cur_days'] > 0 ) {
		$gbp = [
			'window'             => 'last 30 days (vs previous 30)',
			'phone_calls'        => (int) $perf['cur']['calls'],
			'calls_trend'        => $trend( $perf['cur']['calls'], $perf['prev']['calls'] ),
			'website_clicks'     => (int) $perf['cur']['website'],
			'direction_requests' => (int) $perf['cur']['directions'],
			'profile_views'      => (int) $perf['cur']['impressions'],
		];
	}
	$rev = $stock( $opt( 'bp_gbp_stats_history' ), 30 );
	if ( $rev && is_array( $rev['now'] ) ) {
		$gbp['review_count'] = (int) ( $rev['now']['reviews'] ?? 0 );
		$gbp['avg_rating']   = round( (float) ( $rev['now']['rating'] ?? 0 ), 1 );
		if ( is_array( $rev['then'] ) ) {
			$gained = (int) ( $rev['now']['reviews'] ?? 0 ) - (int) ( $rev['then']['reviews'] ?? 0 );
			if ( 0 !== $gained ) $gbp['new_reviews_30d'] = $gained;
		}
	} elseif ( is_array( $gu = $opt( 'bp_gbp_update' ) ) ) {
		if ( isset( $gu['google-reviews'] ) ) $gbp['review_count'] = (int) $gu['google-reviews'];
		if ( isset( $gu['google-rating'] ) )  $gbp['avg_rating']   = round( (float) $gu['google-rating'], 1 );
	}
	if ( $gbp ) $s['google_business'] = $gbp;

	/* ---- Site speed (Lighthouse lab, latest run, MOBILE, pre-judged) ------ */
	$labh = $opt( 'bp_cwv_lab_history' );
	if ( is_array( $labh ) && $labh ) {
		ksort( $labh );
		$latest = end( $labh );
		if ( is_array( $latest ) ) {
			$mo    = static fn( $k ) => isset( $latest[ $k ]['m'] ) ? $latest[ $k ]['m'] : null;
			$judge = static function ( $v, $good, $poor, $higher_better = false ) {
				if ( $v === null ) return null;
				if ( $higher_better ) return $v >= $good ? 'good' : ( $v >= $poor ? 'needs work' : 'poor' );
				return $v <= $good ? 'good' : ( $v <= $poor ? 'needs work' : 'poor' );
			};
			$row = static fn( $v, $verdict, $unit = '' ) => $v === null ? null : [ 'value' => $v . $unit, 'rating' => $verdict ];
			$lab = $drop( [
				'note'           => 'Google Lighthouse, MOBILE (the experience most visitors get)',
				'performance'    => $row( $mo( 'perf' ), $judge( $mo( 'perf' ), 90, 50, true ) ),
				'largest_paint'  => $row( $mo( 'lcp' ),  $judge( $mo( 'lcp' ),  2.5, 4.0 ), 's' ),
				'layout_shift'   => $row( $mo( 'cls' ),  $judge( $mo( 'cls' ),  0.1, 0.25 ) ),
				'blocking_time'  => $row( $mo( 'tbt' ),  $judge( $mo( 'tbt' ),  200, 600 ), 'ms' ),
				'first_paint'    => $row( $mo( 'fcp' ),  $judge( $mo( 'fcp' ),  1.8, 3.0 ), 's' ),
				'speed_index'    => $row( $mo( 'si' ),   $judge( $mo( 'si' ),   3.4, 5.8 ), 's' ),
				'accessibility'  => $row( $mo( 'acc' ),  $judge( $mo( 'acc' ),  90, 50, true ) ),
				'best_practices' => $row( $mo( 'best' ), $judge( $mo( 'best' ), 90, 50, true ) ),
				'seo'            => $row( $mo( 'seo' ),  $judge( $mo( 'seo' ),  90, 50, true ) ),
			] );
			if ( count( $lab ) > 1 ) $s['site_speed_lab'] = $lab;
		}
	}

	/* ---- Content inventory (+ ~90-day growth) ----------------------------- */
	$ch = $stock( $opt( 'bp_content_history' ), 90 );
	if ( $ch && is_array( $ch['now'] ) ) {
		$now = $ch['now']; $then = is_array( $ch['then'] ) ? $ch['then'] : [];
		$inv = [ 'window' => 'now (vs ~90 days ago)' ];
		foreach ( [ 'pages' => 'pages', 'blog' => 'blog_posts', 'jobsites' => 'jobsite_pages', 'galleries' => 'galleries', 'testimonials' => 'testimonials', 'images' => 'images' ] as $k => $label ) {
			if ( ! isset( $now[ $k ] ) ) continue;
			$inv[ $label ] = (int) $now[ $k ];
			if ( isset( $then[ $k ] ) && (int) $then[ $k ] !== (int) $now[ $k ] ) {
				$diff = (int) $now[ $k ] - (int) $then[ $k ];
				$inv[ $label . '_change' ] = ( $diff > 0 ? '+' : '' ) . $diff;
			}
		}
		$s['content_inventory'] = $inv;
	}

	/* ---- Behaviour signals (Microsoft Clarity, 30-day rates) -------------- */
	$cl = $flow( $opt( 'bp_clarity_history' ), 30, [ 'sessions', 'rage', 'dead', 'quickback', 'scriptErr', 'scrollDepth' ] );
	if ( $cl['cur_days'] > 0 && $cl['cur']['sessions'] > 0 ) {
		$ses  = $cl['cur']['sessions'];
		$rate = static fn( $n ) => round( $n / $ses * 100, 1 ) . '%';
		$s['user_frustration'] = $drop( [
			'window'            => 'last 30 days',
			'sessions_measured' => (int) $ses,
			'rage_clicks'       => $rate( $cl['cur']['rage'] ),
			'dead_clicks'       => $rate( $cl['cur']['dead'] ),
			'quick_backs'       => $rate( $cl['cur']['quickback'] ),
			'js_errors'         => $rate( $cl['cur']['scriptErr'] ),
			'avg_scroll_depth'  => round( $cl['cur']['scrollDepth'] / $cl['cur_days'] ) . '%',
			'sample'            => $sample( (int) $ses ),
			'guide'             => 'rage / dead / quick-back under ~3% is healthy; higher points to a real UX problem',
		] );
	}

	/* ---- Device mix (which render to judge the design on) ----------------- */
	$dev = $opt( 'bp_ga4_devices_clean' );
	if ( is_array( $dev ) && $dev ) {
		$counts = []; $tot = 0;
		foreach ( $dev as $d => $m ) {
			if ( ! is_array( $m ) ) continue;
			$c = (int) ( $m['sessions-90'] ?? 0 );
			if ( $c > 0 ) { $counts[ strtolower( (string) $d ) ] = $c; $tot += $c; }
		}
		if ( $tot > 0 ) {
			arsort( $counts );
			$pct = [];
			foreach ( $counts as $d => $c ) $pct[ $d ] = round( $c / $tot * 100 ) . '%';
			$lead = array_key_first( $pct );
			$s['device_mix'] = [
				'window'   => 'last 90 days',
				'split'    => $pct,
				'dominant' => $lead . ' at ' . $pct[ $lead ],
				'total'    => $tot,
				'guide'    => $tot < 300
					? "SMALL SAMPLE ({$tot}) — directional, but still judge the design on the {$lead} render most visitors see."
					: "Judge the design first on {$lead} — that is what most visitors actually see.",
			];
		}
	}

	/* ---- Named context (qualitative — names only, no maths) --------------- */
	$pages_month = $latest_period( $opt( 'bp_ga4_pages_history' ) );
	if ( is_array( $pages_month ) ) {
		arsort( $pages_month );
		$s['top_pages'] = array_slice( array_keys( $pages_month ), 0, 10 );
	}
	$tq = $opt( 'bp_gsc_top_queries' );
	if ( is_array( $tq ) ) {
		$q = [];
		foreach ( $tq as $query => $per ) {
			$m = $per['quarter'] ?? ( $per['month'] ?? null );
			if ( is_array( $m ) ) $q[] = $drop( [ 'query' => $query, 'clicks' => (int) ( $m['clicks'] ?? 0 ), 'position' => $m['position'] ?? null ] );
			if ( count( $q ) >= 15 ) break;
		}
		if ( $q ) $s['top_search_queries'] = $q;
	}

	return array_filter( $s, static fn( $v ) => ! is_null( $v ) && $v !== [] && $v !== '' );
}

/**
 * Pre-digest the page crawl so the model reads conclusions instead of scanning 8 raw page objects.
 * Schema especially must be stated up front — a false "no schema" is the kind of error that makes
 * the whole audit look untrustworthy.
 */
function bp_audit_pages_digest( array $pages ): array {
	$n = 0; $with_schema = 0; $types = []; $issues = [];
	foreach ( $pages as $p ) {
		if ( ! is_array( $p ) || empty( $p['url'] ) || ! empty( $p['error'] ) ) continue;
		$n++;
		if ( ! empty( $p['has_schema'] ) ) {
			$with_schema++;
			foreach ( (array) ( $p['schema_types'] ?? [] ) as $t ) $types[ $t ] = true;
		}
		$flags = [];
		if ( empty( $p['meta_description'] ) )            $flags[] = 'no meta description';
		if ( 0 === (int) ( $p['h1_count'] ?? 0 ) )        $flags[] = 'no H1';
		elseif ( (int) $p['h1_count'] > 1 )               $flags[] = (int) $p['h1_count'] . ' H1s';
		if ( (int) ( $p['word_count'] ?? 0 ) < 300 )      $flags[] = 'thin copy (' . (int) ( $p['word_count'] ?? 0 ) . ' words)';
		if ( (int) ( $p['images_missing_alt'] ?? 0 ) > 0 )$flags[] = (int) $p['images_missing_alt'] . ' images missing alt';
		if ( empty( $p['has_schema'] ) )                  $flags[] = 'no structured data';
		if ( empty( $p['has_viewport'] ) )                $flags[] = 'no mobile viewport tag';
		if ( $flags ) $issues[ $p['url'] ] = $flags;
	}
	$schema_line = $with_schema > 0
		? "Structured data IS present on {$with_schema} of {$n} crawled pages. Types found: "
			. implode( ', ', array_slice( array_keys( $types ), 0, 12 ) )
			. '. Do NOT claim the site lacks schema.'
		: "No structured data (JSON-LD or microdata) was found on any of the {$n} crawled pages.";
	return [
		'pages_crawled'   => $n,
		'schema'          => $schema_line,
		'per_page_issues' => $issues ?: 'none flagged',
	];
}


/*--------------------------------------------------------------
# History
--------------------------------------------------------------*/

/** All stored audits, oldest → newest, keyed by the run date. */
function bp_audit_ai_history(): array {
	$h = get_option( 'bp_site_audit_ai_history' );
	if ( ! is_array( $h ) ) return [];
	ksort( $h );
	return $h;
}

/** The most recent audit, or null. */
function bp_audit_ai_latest(): ?array {
	$h = bp_audit_ai_history();
	if ( ! $h ) return null;
	$last = end( $h );
	return is_array( $last ) ? $last : null;
}

/** Append a report to the history (capped so the option can't grow forever). */
function bp_audit_ai_store( array $report, int $keep = 40 ): void {
	$h = get_option( 'bp_site_audit_ai_history' );
	if ( ! is_array( $h ) ) $h = [];
	$h[ $report['date'] ] = $report;
	ksort( $h );
	if ( count( $h ) > $keep ) $h = array_slice( $h, -$keep, null, true );
	update_option( 'bp_site_audit_ai_history', $h, false );

	// Stamp the last-audit time the Dashboard menu reads (functions-admin.php shows
	// "Audit  {elapsed} ago"). Without this it always read "Never" — bp_audit_time was
	// referenced by the menu but never written anywhere.
	update_option( 'bp_audit_time', time(), false );
}

/**
 * Record how far the run got. The audit dies silently when the process is killed (gateway/CLI
 * timeout) — no exception, no shutdown hook — so the ONLY way to know where it stopped is to
 * write a breadcrumb before each slow step. The admin notice reads this back.
 */
function bp_audit_stage( string $stage ): void {
	update_option( 'bp_site_audit_stage', [ 'stage' => $stage, 'at' => time() ], false );
	error_log( 'bp_audit: ' . $stage );
}

/**
 * Build the Claude prompt. Split out of the runner so the all-in-one path and the stepped
 * browser-driven path can't drift apart.  Returns [ system, user ].
 */
function bp_audit_ai_prompt( array $pages, array $facts, int $shot_count = 0 ): array {
	$ci       = function_exists( 'customer_info' ) ? customer_info() : [];
	$business = [
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
		'tagline'  => get_bloginfo( 'description' ),
		'type'     => is_array( $ci ) ? ( $ci['site-type'] ?? '' ) : '',
		'industry' => is_array( $ci ) ? ( $ci['industry'] ?? ( $ci['business-type'] ?? '' ) ) : '',
		'location' => is_array( $ci ) ? trim( ( $ci['city'] ?? '' ) . ' ' . ( $ci['state'] ?? '' ) ) : '',
	];

	// Be honest about whether we actually captured screenshots this run. Telling the model it was "shown
	// screenshots" when the capture failed is exactly what makes it hallucinate a visual critique (e.g.
	// "the hero buries the phone" on a site whose header shows the phone at the very top).
	if ( $shot_count > 0 ) {
		$screenshot_rules =
			  "- You are shown {$shot_count} REAL SCREENSHOTS (above the fold) — the home page and ONE secondary "
			. "page, each in mobile and desktop. This site is built from two templates: the home page looks one "
			. "way and every secondary page looks another, so the secondary page you see represents all of them. "
			. "Critique what you actually see: visual hierarchy, whether the offer and primary CTA are obvious in "
			. "the first screen, contrast/legibility, spacing/crowding, image quality, brand consistency, and how "
			. "mobile vs desktop compare.\n"
			. "- GROUND EVERY VISUAL CLAIM in what is genuinely visible. Do NOT state that an element is missing, "
			. "buried, or below the fold unless the screenshot plainly shows that. The site header — logo, phone "
			. "number and primary CTA — normally sits at the VERY TOP of the first screen; look there before ever "
			. "claiming the phone or a CTA is hard to find. If you cannot clearly see something, make no claim "
			. "about it either way.\n"
			. "- WEIGHT YOUR DESIGN CRITIQUE BY THE REAL DEVICE MIX (see `device_mix` in the data). Judge the "
			. "render most visitors actually see first, and name the split when it drives a recommendation.\n"
			. "- The screenshots are above-the-fold only; page STRUCTURE (headings, CTAs, copy volume, images/alt, "
			. "schema, links) covers the rest of each page and covers 8 pages, not 2. Don't claim to have seen "
			. "further down a page, and don't assume an unscreenshotted page differs from the secondary template "
			. "you were shown.\n";
	} else {
		$screenshot_rules =
			  "- NO SCREENSHOTS were captured this run — you CANNOT see the pages. Do NOT critique visual layout, "
			. "hierarchy, above-the-fold placement, imagery, colour or spacing; you have no image, and inventing "
			. "such a critique is a serious error. Base UX/Design judgments ONLY on the crawled PAGE STRUCTURE "
			. "(headings, CTA counts, image counts and alt text), and note in the summary that the visual design "
			. "could not be assessed this run.\n";
	}

	$system = "You are a senior web strategist auditing a small-business marketing website. You combine "
		. "three lenses: conversion-rate optimisation, UX/usability, and visual design & layout — always "
		. "grounded in the data provided.\n\n"
		. "Rules:\n"
		. "- Be specific and practical. Name the actual page, heading, or number you're reacting to.\n"
		. "- THE DATA IS ALREADY COMPUTED FOR YOU. Every number in MEASURED DATA is final: the percentages, "
		. "the `_trend` values, the `rating` verdicts and the `sample` notes were all calculated in code. Do "
		. "NOT recompute a ratio from raw counts, do NOT contradict a stated percentage, and never do arithmetic "
		. "of your own — your job is to INTERPRET these figures, not re-derive them. Read a value like "
		. "\"62% mobile\" or \"calls +18%\" as a given fact.\n"
		. "- Never invent metrics. If something isn't in the data, don't claim it; you may note it's missing.\n"
		. "- RESPECT THE SAMPLE VERDICTS. Every count-based metric carries a `sample` field. When it says "
		. "'TOO FEW' or 'directional only', you MUST NOT build a conclusion on that metric — say plainly the "
		. "data is still too thin to judge it. Always prefer the widest window shown (90-day over 30-day); never "
		. "reason from a single day or a handful of sessions.\n"
		. "- STRUCTURED DATA: trust the CRAWL DIGEST `schema` line. If it says schema IS present, treat it as "
		. "present and do not claim the site is missing schema; only raise missing schema if the digest says none "
		. "was found.\n"
		. "- Use the CRAWL DIGEST `per_page_issues` as your ready-made checklist of concrete on-page problems — "
		. "cite the specific page and flag rather than inventing issues.\n"
		. "- Judge like a prospective customer landing on the site, not like a checklist.\n"
		. "- Prioritise by likely revenue impact for a small business, not by ease.\n"
		. "- Write for the business owner: plain English, no jargon dumps.\n"
		. $screenshot_rules
		. "\n"
		. "Return ONLY valid JSON (no markdown fence), shaped exactly:\n"
		. '{"summary":"2-4 sentence executive read","scores":{"ux":0-100,"conversion":0-100,"design":0-100,"content":0-100,"seo":0-100,"performance":0-100},'
		. '"doing_well":[{"area":"UX|Conversion|Design & Layout|Content|SEO|Performance","title":"short","detail":"why it works, cite the evidence"}],'
		. '"improve":[{"area":"UX|Conversion|Design & Layout|Content|SEO|Performance","priority":"high|medium|low","title":"short","detail":"what is wrong and why it costs them","action":"the concrete fix"}]}'
		. "\nGive 4-7 items in doing_well and 6-12 in improve, ordered most important first.";

	// Anchor the scores to the previous run. Without this each audit is judged from scratch, so the
	// history shows the model's run-to-run variance as if it were the site changing.
	$prev   = bp_audit_ai_latest();
	$anchor = '';
	if ( is_array( $prev ) && ! empty( $prev['scores'] ) ) {
		$anchor = "\n\nPREVIOUS AUDIT (" . (string) ( $prev['date'] ?? '' ) . ")\n"
			. "Scores last time: " . wp_json_encode( $prev['scores'] ) . "\n"
			. "Its summary was: " . (string) ( $prev['summary'] ?? '' ) . "\n"
			. "Score on the SAME scale as last time. Only move a score if the evidence actually changed — "
			. "and when you move one by more than 5 points, say what changed in the detail of a "
			. "doing_well/improve item. Do not re-baseline just because you'd have judged it differently.";
	}

	$digest = bp_audit_pages_digest( $pages );

	$user = "BUSINESS\n" . wp_json_encode( $business, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		. "\n\nMEASURED DATA — already computed; the trends, percentages, ratings and sample verdicts are FINAL. "
		. "Interpret them, do not recompute them.\n" . wp_json_encode( $facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		. "\n\nCRAWL DIGEST — conclusions from the page crawl (read this for schema + on-page issues)\n"
		. wp_json_encode( $digest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		. "\n\nPAGE STRUCTURE (per crawled page, supporting detail)\n" . wp_json_encode( $pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		. $anchor
		. "\n\nAudit this site: what is it doing well, and where can it improve?";

	return [ $system, $user ];
}

/**
 * Turn Claude's raw reply into a stored report. Shared by both runners.
 * $shot_urls = [ [page,image], … ] for the report's screenshot strip.
 * Returns the report array or a WP_Error.
 */
function bp_audit_ai_finish( $raw, array $pages, array $shot_urls ) {
	if ( is_wp_error( $raw ) ) {
		error_log( 'bp_audit: Claude call failed — ' . $raw->get_error_message() );
		return $raw;
	}

	// Tolerate a stray code fence / preamble.
	$json = trim( (string) $raw );
	if ( false !== strpos( $json, '```' ) ) {
		$json = preg_replace( '/^.*?```(?:json)?\s*/s', '', $json );
		$json = preg_replace( '/```.*$/s', '', $json );
		$json = trim( $json );
	}
	if ( '' !== $json && '{' !== $json[0] ) {
		$start = strpos( $json, '{' );
		$end   = strrpos( $json, '}' );
		if ( false !== $start && false !== $end && $end > $start ) $json = substr( $json, $start, $end - $start + 1 );
	}

	$parsed = json_decode( $json, true );
	if ( ! is_array( $parsed ) || empty( $parsed['summary'] ) ) {
		error_log( 'bp_audit: could not parse Claude response.' );
		return new WP_Error( 'bp_audit_parse', 'Could not parse the AI response.' );
	}

	$report = [
		'date'           => current_time( 'mysql' ),
		'model'          => BP_AUDIT_AI_MODEL,
		'summary'        => (string) $parsed['summary'],
		'scores'         => is_array( $parsed['scores'] ?? null ) ? $parsed['scores'] : [],
		'doing_well'     => is_array( $parsed['doing_well'] ?? null ) ? array_values( $parsed['doing_well'] ) : [],
		'improve'        => is_array( $parsed['improve'] ?? null ) ? array_values( $parsed['improve'] ) : [],
		'pages_analyzed' => array_values( array_filter( array_map( static fn( $p ) => $p['url'] ?? '', $pages ) ) ),
		'screenshots'    => $shot_urls,
	];

	bp_audit_stage( 'storing report' );
	bp_audit_ai_store( $report );
	bp_audit_prune_screenshots();
	delete_option( 'bp_site_audit_stage' ); // finished cleanly — no breadcrumb to report
	error_log( 'bp_audit: stored audit ' . $report['date'] . ' for ' . home_url( '/' ) );

	return $report;
}


/*--------------------------------------------------------------
# Stepped runner — the audit the "Run Audit" button actually uses
#
# The whole audit (crawl + 3 PageSpeed renders + a Claude vision call) takes minutes, which no
# single web request survives. Rather than hand it to cron and lose all feedback, the BROWSER
# drives it: each AJAX call does ONE chunk that finishes well inside the timeout, and the admin
# page loops until done. Nothing runs unattended, and you watch it work.
#
# Run state lives in the bp_site_audit_run option:
#   { step, stamp, pages, facts, shots:[{page,url,file,mime}], started }
--------------------------------------------------------------*/

/**
 * Which URL gets shot at which viewport: the home page and the single best-performing secondary
 * page, each on mobile and desktop. $pages is the crawl result (home is always first).
 * Returns [ [url, strategy, label], … ].
 */
function bp_audit_shot_targets( array $pages ): array {
	$ok = array_values( array_filter(
		$pages,
		static fn( $p ) => ! empty( $p['url'] ) && empty( $p['error'] )
	) );
	if ( ! $ok ) return [];

	$home      = $ok[0]['url'];
	$secondary = $ok[1]['url'] ?? '';

	$targets = [
		[ $home, 'mobile', 'home page (mobile)' ],
		[ $home, 'desktop', 'home page (desktop)' ],
	];
	if ( $secondary ) {
		$targets[] = [ $secondary, 'mobile', 'secondary page (mobile)' ];
		$targets[] = [ $secondary, 'desktop', 'secondary page (desktop)' ];
	}
	return $targets;
}

/** The ordered steps, and what to tell the user while each one runs. */
function bp_audit_steps(): array {
	$steps  = [ 'crawl' => 'Crawling the main pages' ];
	$labels = [ 'home page, mobile', 'home page, desktop', 'secondary page, mobile', 'secondary page, desktop' ];
	for ( $i = 0; $i < (int) BP_AUDIT_AI_MAX_SHOTS; $i++ ) {
		$steps[ 'shot:' . $i ] = 'Rendering ' . ( $labels[ $i ] ?? 'screenshot ' . ( $i + 1 ) );
	}
	$steps['analyze'] = 'Claude is reviewing the site';
	return $steps;
}

function bp_audit_run_state(): array {
	$s = get_option( 'bp_site_audit_run' );
	return is_array( $s ) ? $s : [];
}

function bp_audit_run_save( array $state ): void {
	update_option( 'bp_site_audit_run', $state, false );
}

/** Begin a fresh run. Returns the starting state. */
function bp_audit_run_start(): array {
	$state = [
		'step'    => 'crawl',
		'stamp'   => gmdate( 'Ymd-His' ),
		'pages'   => [],
		'facts'   => [],
		'shots'   => [],
		'started' => time(),
	];
	bp_audit_run_save( $state );
	return $state;
}

/**
 * Execute the current step and advance. Returns:
 *   [ done, step, label, progress(0-100), error, report? ]
 */
function bp_audit_run_step() {
	$state = bp_audit_run_state();
	if ( ! $state ) $state = bp_audit_run_start();

	$steps = bp_audit_steps();
	$keys  = array_keys( $steps );
	$step  = (string) ( $state['step'] ?? 'crawl' );

	// Give each individual step as much room as the host allows; it's one chunk, not the whole job.
	@set_time_limit( 120 );

	$advance = function ( string $current ) use ( $keys ) {
		$i = array_search( $current, $keys, true );
		return ( false !== $i && isset( $keys[ $i + 1 ] ) ) ? $keys[ $i + 1 ] : 'done';
	};
	$progress = function ( string $current ) use ( $keys ) {
		$i = array_search( $current, $keys, true );
		return (int) round( ( ( false === $i ? 0 : $i ) / max( 1, count( $keys ) ) ) * 100 );
	};

	bp_audit_stage( $steps[ $step ] ?? $step );

	if ( 'crawl' === $step ) {
		$state['pages'] = bp_audit_page_analysis();
		$state['facts'] = bp_audit_ai_facts();
		$state['step']  = $advance( $step );
		bp_audit_run_save( $state );

	} elseif ( 0 === strpos( $step, 'shot:' ) ) {
		$idx     = (int) substr( $step, 5 );
		$targets = bp_audit_shot_targets( (array) ( $state['pages'] ?? [] ) );

		if ( isset( $targets[ $idx ] ) ) {
			[ $url, $strategy, $label ] = $targets[ $idx ];
			$shot = bp_audit_screenshot( $url, $strategy );
			if ( $shot ) {
				// Save to disk now and carry only the PATH — the base64 blob is far too big to
				// round-trip through an option between steps.
				$saved = bp_audit_save_screenshot( $shot, (string) $state['stamp'] );
				if ( $saved ) {
					$state['shots'][] = [
						'page'     => $url,
						'url'      => $saved,
						'file'     => bp_audit_screenshot_path( $saved ),
						'mime'     => $shot['mime'],
						'strategy' => $strategy,
						'label'    => $label,
					];
				}
			}
		}
		$state['step'] = $advance( $step );
		bp_audit_run_save( $state );

	} elseif ( 'analyze' === $step ) {
		$pages = (array) ( $state['pages'] ?? [] );
		$facts = (array) ( $state['facts'] ?? [] );

		// Re-read the saved screenshots off disk for the vision call.
		$images = [];
		foreach ( (array) ( $state['shots'] ?? [] ) as $s ) {
			if ( empty( $s['file'] ) || ! file_exists( $s['file'] ) ) continue;
			$bin = @file_get_contents( $s['file'] );
			if ( false === $bin ) continue;
			$images[] = [
				'mime'     => $s['mime'] ?? 'image/jpeg',
				'data'     => base64_encode( $bin ),
				'url'      => $s['page'] ?? '',
				'strategy' => $s['strategy'] ?? 'mobile',
				'label'    => $s['label'] ?? '',
			];
		}

		[ $system, $user ] = bp_audit_ai_prompt( $pages, $facts, count( $images ) );
		$raw    = bp_audit_ai_call( $system, $user, $images );
		$report = bp_audit_ai_finish(
			$raw,
			$pages,
			array_map(
				static fn( $s ) => [
					'page'  => $s['page'] ?? '',
					'image' => $s['url'] ?? '',
					'label' => $s['label'] ?? '',
				],
				(array) ( $state['shots'] ?? [] )
			)
		);

		delete_option( 'bp_site_audit_run' );

		if ( is_wp_error( $report ) ) {
			return [ 'done' => true, 'error' => $report->get_error_message(), 'progress' => 100 ];
		}
		return [ 'done' => true, 'progress' => 100, 'label' => 'Audit complete', 'report' => true ];
	}

	$next = (string) $state['step'];
	if ( 'done' === $next ) {
		delete_option( 'bp_site_audit_run' );
		return [ 'done' => true, 'progress' => 100, 'label' => 'Audit complete' ];
	}

	return [
		'done'     => false,
		'step'     => $next,
		'label'    => $steps[ $next ] ?? $next,
		'progress' => $progress( $next ),
	];
}

/**
 * Run every step back-to-back in one process. Only safe where there's no request timeout —
 * WP-CLI, basically. The admin button uses the stepped/AJAX path instead.
 */
function bp_audit_ai_report() {
	if ( '' === bp_audit_ai_key() ) {
		error_log( 'bp_audit_ai_report: skipped — no Anthropic API key.' );
		return new WP_Error( 'bp_audit_no_key', 'No Anthropic API key configured.' );
	}

	@set_time_limit( 0 );
	bp_audit_run_start();

	// +2 for the terminal step and a margin; the loop can't outlive the step list either way.
	$guard = count( bp_audit_steps() ) + 2;
	while ( $guard-- > 0 ) {
		$res = bp_audit_run_step();
		if ( ! empty( $res['error'] ) ) return new WP_Error( 'bp_audit_failed', (string) $res['error'] );
		if ( ! empty( $res['done'] ) ) return bp_audit_ai_latest();
	}
	return new WP_Error( 'bp_audit_stuck', 'The audit did not complete.' );
}

/** Map a saved screenshot URL back to its path on disk. */
function bp_audit_screenshot_path( string $url ): string {
	$up = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) return '';
	if ( 0 !== strpos( $url, $up['baseurl'] ) ) return '';
	return trailingslashit( $up['basedir'] ) . ltrim( substr( $url, strlen( $up['baseurl'] ) ), '/' );
}

/**
 * The Run Audit button + live progress. The browser walks the steps one AJAX call at a time, so
 * nothing ever approaches the request timeout and the user can see exactly where it is.
 */
function bp_audit_render_runner(): void {
	$nonce   = wp_create_nonce( 'bp_audit_step' );
	$has_key = '' !== bp_audit_ai_key();
	$resume  = (bool) bp_audit_run_state();
	?>
	<div class="bp-audit-runner" style="margin:0 0 22px;padding:16px 20px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">
		<?php if ( ! $has_key ) : ?>
			<p style="margin:0;color:#b32d2e;"><strong>No Anthropic API key is configured on this site</strong>, so the audit can&rsquo;t run.</p>
		<?php else : ?>
			<button type="button" class="button button-primary" id="bp-audit-run"><?php echo $resume ? 'Resume Audit' : 'Run Audit'; ?></button>
			<span id="bp-audit-msg" style="margin-left:12px;color:#666;"><?php echo $resume ? 'A previous run was interrupted — resume to finish it.' : 'Takes about 1–3 minutes.'; ?></span>
			<div id="bp-audit-bar-wrap" style="display:none;margin-top:12px;height:8px;background:#e7e7ea;border-radius:99px;overflow:hidden;">
				<div id="bp-audit-bar" style="height:100%;width:0;background:#2271b1;border-radius:99px;transition:width .3s;"></div>
			</div>
		<?php endif; ?>
	</div>
	<script>
	(function(){
		var btn = document.getElementById('bp-audit-run');
		if (!btn) return;
		var msg = document.getElementById('bp-audit-msg'),
		    wrap = document.getElementById('bp-audit-bar-wrap'),
		    bar = document.getElementById('bp-audit-bar'),
		    nonce = <?php echo wp_json_encode( $nonce ); ?>,
		    ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

		function step(restart){
			var body = new URLSearchParams();
			body.append('action','bp_audit_step');
			body.append('nonce',nonce);
			if (restart) body.append('restart','1');

			// Each call is ONE chunk, so a normal fetch is fine — no long-poll, no timeout risk.
			fetch(ajax,{method:'POST',credentials:'same-origin',body:body})
				.then(function(r){ return r.json(); })
				.then(function(res){
					var d = res && res.data ? res.data : {};
					if (!res || !res.success) {
						msg.textContent = d.error || 'The audit failed.';
						msg.style.color = '#b32d2e';
						btn.disabled = false;
						btn.textContent = 'Try Again';
						return;
					}
					if (d.progress != null) bar.style.width = d.progress + '%';
					if (d.done) {
						msg.textContent = 'Audit complete — reloading…';
						bar.style.width = '100%';
						setTimeout(function(){ location.reload(); }, 700);
						return;
					}
					msg.textContent = (d.label || 'Working') + '…';
					step(false);
				})
				.catch(function(e){
					msg.textContent = 'Connection error: ' + e.message;
					msg.style.color = '#b32d2e';
					btn.disabled = false;
					btn.textContent = 'Try Again';
				});
		}

		btn.addEventListener('click', function(){
			var restart = btn.textContent.indexOf('Resume') === -1;
			btn.disabled = true;
			btn.textContent = 'Running…';
			msg.style.color = '#666';
			msg.textContent = 'Starting…';
			wrap.style.display = 'block';
			step(restart);
		});
	})();
	</script>
	<?php
}

/** AJAX: run one step. The admin page calls this in a loop. */
add_action( 'wp_ajax_bp_audit_step', 'bp_audit_ajax_step' );
function bp_audit_ajax_step(): void {
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'error' => 'Not allowed.' ], 403 );
	check_ajax_referer( 'bp_audit_step', 'nonce' );

	if ( ! empty( $_POST['restart'] ) ) {
		bp_audit_run_start();
	}

	if ( '' === bp_audit_ai_key() ) {
		wp_send_json_error( [ 'error' => 'No Anthropic API key is configured on this site.' ] );
	}

	$res = bp_audit_run_step();
	if ( ! empty( $res['error'] ) ) wp_send_json_error( $res );
	wp_send_json_success( $res );
}


/*--------------------------------------------------------------
# Fix notes — "here's what I actually did about this", per audit item
--------------------------------------------------------------*/

/** All saved notes: [key => ['text'=>string, 'date'=>'Y-m-d', 'ts'=>int]]. */
function bp_audit_notes(): array {
	$n = get_option( 'bp_audit_notes' );
	return is_array( $n ) ? $n : [];
}

/** Stable per-item key: report date + item title, so it survives re-renders and any reordering. */
function bp_audit_note_key( string $report_date, string $title ): string {
	return substr( md5( $report_date . '|' . $title ), 0, 16 );
}

/** The per-item note UI — a saved, date-stamped note (with Edit/Delete), or an empty box to add one. */
function bp_audit_note_box( string $key, $note ): string {
	$has = is_array( $note ) && ! empty( $note['text'] );
	$txt = $has ? (string) $note['text'] : '';
	$lbl = $has ? date_i18n( 'M j, Y', strtotime( (string) $note['date'] ) ) : '';

	$h  = '<div class="bp-audit-note" data-key="' . esc_attr( $key ) . '" style="margin:8px 0 2px;">';
	$h .= '<div class="bp-an-note-view" style="' . ( $has ? '' : 'display:none;' ) . 'background:#f6f7f7;border-left:3px solid #2271b1;border-radius:0 4px 4px 0;padding:8px 10px;font-size:13px;line-height:1.5;">';
	// Date (and controls) sit on their own line so the note's first line/bullet starts clean beneath it.
	$h .= '<div style="margin-bottom:5px;">';
	$h .= '<strong class="bp-an-note-date">' . esc_html( $lbl ) . '</strong>';
	$h .= ' <button type="button" class="button-link bp-an-note-edit" style="margin-left:8px;">Edit</button>';
	$h .= ' <button type="button" class="button-link bp-an-note-del" style="color:#b32d2e;margin-left:4px;">Delete</button>';
	$h .= '</div>';
	$h .= '<div class="bp-an-note-text">' . nl2br( esc_html( $txt ) ) . '</div>';
	$h .= '</div>';
	$h .= '<div class="bp-an-note-form" style="' . ( $has ? 'display:none;' : '' ) . '">';
	$h .= '<textarea class="bp-an-note-input" rows="2" placeholder="What did you do about this?" style="width:min(100%,800px);height:min(200px,20vh);font-size:13px;">' . esc_textarea( $txt ) . '</textarea>';
	$h .= '<div style="margin-top:4px;"><button type="button" class="button button-small bp-an-note-save">Save note</button>';
	$h .= '<button type="button" class="button-link bp-an-note-cancel" style="margin-left:10px;' . ( $has ? '' : 'display:none;' ) . '">Cancel</button></div>';
	$h .= '</div></div>';
	return $h;
}

add_action( 'wp_ajax_bp_audit_note', 'bp_audit_ajax_note' );
function bp_audit_ajax_note(): void {
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'error' => 'Not allowed.' ], 403 );
	check_ajax_referer( 'bp_audit_note', 'nonce' );

	$key = sanitize_key( (string) ( $_POST['key'] ?? '' ) );
	if ( '' === $key ) wp_send_json_error( [ 'error' => 'Missing key.' ] );

	$notes = bp_audit_notes();

	if ( 'delete' === (string) ( $_POST['mode'] ?? '' ) ) {
		unset( $notes[ $key ] );
		update_option( 'bp_audit_notes', $notes, false );
		wp_send_json_success( [ 'deleted' => true ] );
	}

	$text = trim( sanitize_textarea_field( wp_unslash( (string) ( $_POST['text'] ?? '' ) ) ) );
	if ( '' === $text ) wp_send_json_error( [ 'error' => 'Nothing to save.' ] );

	// Keep the ORIGINAL entry date when editing — it records when the work was actually done.
	$date          = (string) ( $notes[ $key ]['date'] ?? current_time( 'Y-m-d' ) );
	$notes[ $key ] = [ 'text' => $text, 'date' => $date, 'ts' => (int) current_time( 'timestamp' ) ];
	update_option( 'bp_audit_notes', $notes, false );

	wp_send_json_success( [ 'text' => $text, 'date' => $date, 'label' => date_i18n( 'M j, Y', strtotime( $date ) ) ] );
}

/**
 * Remove one item from a stored report — for findings that are irrelevant, or already covered by another
 * fix. Matches by the same date+title key the notes use, searches both lists, and drops the item's note
 * too (it has nothing left to describe). Permanent: the item is deleted from the saved report.
 */
add_action( 'wp_ajax_bp_audit_item_delete', 'bp_audit_ajax_item_delete' );
function bp_audit_ajax_item_delete(): void {
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'error' => 'Not allowed.' ], 403 );
	check_ajax_referer( 'bp_audit_note', 'nonce' );

	$date = trim( (string) wp_unslash( $_POST['date'] ?? '' ) );
	$key  = sanitize_key( (string) ( $_POST['key'] ?? '' ) );
	if ( '' === $date || '' === $key ) wp_send_json_error( [ 'error' => 'Missing item.' ] );

	$history = get_option( 'bp_site_audit_ai_history' );
	if ( ! is_array( $history ) || ! isset( $history[ $date ] ) ) wp_send_json_error( [ 'error' => 'Report not found.' ] );

	$report  = (array) $history[ $date ];
	$removed = false;
	foreach ( [ 'improve', 'doing_well' ] as $list ) {
		if ( empty( $report[ $list ] ) || ! is_array( $report[ $list ] ) ) continue;
		$kept = [];
		foreach ( $report[ $list ] as $it ) {
			if ( bp_audit_note_key( $date, (string) ( $it['title'] ?? '' ) ) === $key ) { $removed = true; continue; }
			$kept[] = $it;
		}
		$report[ $list ] = array_values( $kept );
	}
	if ( ! $removed ) wp_send_json_error( [ 'error' => 'Item not found in that report.' ] );

	$history[ $date ] = $report;
	update_option( 'bp_site_audit_ai_history', $history, false );

	$notes = bp_audit_notes();
	if ( isset( $notes[ $key ] ) ) { unset( $notes[ $key ] ); update_option( 'bp_audit_notes', $notes, false ); }

	wp_send_json_success( [ 'deleted' => true ] );
}

/** One delegated handler for every note box on the screen (incl. those inside collapsed history). */
function bp_audit_notes_script(): void {
	static $done = false;
	if ( $done ) return;
	$done = true;
	?>
	<script>
	(function(){
		var AJAX = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
		    NONCE = <?php echo wp_json_encode( wp_create_nonce( 'bp_audit_note' ) ); ?>;
		function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
		function post(data, cb){
			var b = new URLSearchParams(data); b.append('nonce', NONCE);
			fetch(AJAX, { method:'POST', credentials:'same-origin', body:b })
				.then(function(r){ return r.json(); })
				.then(cb)
				.catch(function(){ alert('Could not reach the server — please try again.'); });
		}
		document.addEventListener('click', function(ev){
			// Remove an entire audit item (irrelevant, or already covered by another fix).
			var del = ev.target.closest('.bp-audit-item-del');
			if (del) {
				ev.preventDefault();
				var li = del.closest('.bp-audit-item'); if (!li) return;
				if (!confirm('Remove this item from the audit?\n\nThis permanently deletes it from the saved report, along with any note on it.')) return;
				post({ action:'bp_audit_item_delete', date:li.dataset.date, key:li.dataset.key }, function(res){
					if (!res || !res.success) return alert((res && res.data && res.data.error) || 'Could not remove that item.');
					li.style.transition = 'opacity .15s'; li.style.opacity = '0';
					setTimeout(function(){ if (li.parentNode) li.parentNode.removeChild(li); }, 150);
				});
				return;
			}

			var box = ev.target.closest('.bp-audit-note'); if (!box) return;
			var view   = box.querySelector('.bp-an-note-view'),
			    form   = box.querySelector('.bp-an-note-form'),
			    input  = box.querySelector('.bp-an-note-input'),
			    cancel = box.querySelector('.bp-an-note-cancel');

			if (ev.target.closest('.bp-an-note-edit'))   { ev.preventDefault(); view.style.display='none'; form.style.display=''; input.focus(); return; }
			if (ev.target.closest('.bp-an-note-cancel')) { ev.preventDefault(); form.style.display='none'; view.style.display='';  return; }

			if (ev.target.closest('.bp-an-note-del')) {
				ev.preventDefault();
				if (!confirm('Delete this note?')) return;
				post({ action:'bp_audit_note', mode:'delete', key:box.dataset.key }, function(res){
					if (!res || !res.success) return alert('Delete failed.');
					input.value = ''; box.querySelector('.bp-an-note-text').innerHTML = '';
					view.style.display='none'; form.style.display=''; cancel.style.display='none';
				});
				return;
			}
			if (ev.target.closest('.bp-an-note-save')) {
				ev.preventDefault();
				var t = (input.value || '').trim(); if (!t) { input.focus(); return; }
				post({ action:'bp_audit_note', mode:'save', key:box.dataset.key, text:t }, function(res){
					if (!res || !res.success) return alert((res && res.data && res.data.error) || 'Save failed.');
					box.querySelector('.bp-an-note-date').textContent = res.data.label;
					box.querySelector('.bp-an-note-text').innerHTML   = esc(res.data.text).replace(/\n/g,'<br>');
					form.style.display='none'; view.style.display=''; cancel.style.display='';
				});
			}
		});
	})();
	</script>
	<?php
}

/*--------------------------------------------------------------
# Admin render (used by the Site Audit screen)
--------------------------------------------------------------*/

/** Render ONE report's body (scores + both lists). */
function bp_audit_ai_render_report( array $r ): void {
	$pages = (array) ( $r['pages_analyzed'] ?? [] );
	$shots = (array) ( $r['screenshots'] ?? [] );
	printf(
		'<p style="margin:0 0 14px;color:#666;font-size:12px;">%s &middot; %s &middot; %d page%s analysed%s</p>',
		esc_html( (string) ( $r['date'] ?? '' ) ),
		esc_html( (string) ( $r['model'] ?? '' ) ),
		count( $pages ),
		1 === count( $pages ) ? '' : 's',
		$shots ? ' &middot; ' . count( $shots ) . ' screenshot' . ( 1 === count( $shots ) ? '' : 's' ) . ' reviewed' : ''
	);

	echo '<p style="font-size:15px;line-height:1.6;margin:0 0 18px;">' . esc_html( (string) ( $r['summary'] ?? '' ) ) . '</p>';

	// What Claude actually looked at.
	if ( $shots ) {
		echo '<div style="display:flex;flex-wrap:wrap;gap:14px;margin:0 0 20px;">';
		foreach ( $shots as $s ) {
			if ( empty( $s['image'] ) ) continue;
			$path = str_replace( home_url(), '', (string) ( $s['page'] ?? '' ) ) ?: '/';
			printf(
				'<figure style="margin:0;width:190px;"><a href="%s" target="_blank" rel="noopener"><img src="%s" alt="" style="width:190px;border:1px solid #dcdcde;border-radius:6px;display:block;"></a><figcaption style="font-size:11px;color:#667085;margin-top:5px;word-break:break-all;">%s%s</figcaption></figure>',
				esc_url( (string) $s['image'] ),
				esc_url( (string) $s['image'] ),
				esc_html( $path ),
				! empty( $s['label'] ) ? '<br><span style="color:#8b93a1;">' . esc_html( (string) $s['label'] ) . '</span>' : ''
			);
		}
		echo '</div>';
	}

	if ( ! empty( $r['scores'] ) && is_array( $r['scores'] ) ) {
		echo '<div style="display:flex;flex-wrap:wrap;gap:10px;margin:0 0 20px;">';
		foreach ( $r['scores'] as $k => $v ) {
			$v     = (int) $v;
			$color = $v >= 80 ? '#1a7f37' : ( $v >= 60 ? '#9a6700' : '#b42318' );
			printf(
				'<div style="min-width:96px;padding:10px 14px;border:1px solid #dcdcde;border-radius:6px;text-align:center;"><div style="font-size:22px;font-weight:700;color:%s;">%d</div><div style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#666;">%s</div></div>',
				esc_attr( $color ), $v, esc_html( ucfirst( (string) $k ) )
			);
		}
		echo '</div>';
	}

	// Per-item "what I did" notes hang off the report's own date + the item title (see bp_audit_note_key).
	$rdate = (string) ( $r['date'] ?? '' );
	$notes = bp_audit_notes();

	$block = static function ( string $heading, array $items, bool $is_improve ) use ( $rdate, $notes ) {
		if ( ! $items ) return;
		echo '<h3 style="margin:18px 0 8px;">' . esc_html( $heading ) . '</h3>';
		echo '<ul style="margin:0;padding:0;list-style:none;">';
		foreach ( $items as $it ) {
			$pri   = strtolower( (string) ( $it['priority'] ?? '' ) );
			$pcol  = 'high' === $pri ? '#b42318' : ( 'medium' === $pri ? '#9a6700' : '#667085' );
			$badge = ( $is_improve && $pri )
				? sprintf( '<span style="display:inline-block;margin-left:8px;padding:1px 8px;border-radius:999px;background:%s;color:#fff;font-size:10px;text-transform:uppercase;letter-spacing:.05em;">%s</span>', esc_attr( $pcol ), esc_html( $pri ) )
				: '';
			$ikey = bp_audit_note_key( $rdate, (string) ( $it['title'] ?? '' ) );
			printf(
				'<li class="bp-audit-item" data-key="%s" data-date="%s" style="padding:10px 0;border-bottom:1px solid #f0f0f1;">',
				esc_attr( $ikey ), esc_attr( $rdate )
			);
			// Drop an item that's irrelevant, or already handled by another fix.
			echo '<button type="button" class="bp-audit-item-del" title="Remove this item from the audit" aria-label="Remove this item from the audit" style="float:right;margin-left:10px;padding:0 4px;border:0;background:none;color:#b32d2e;font-size:18px;line-height:1.2;cursor:pointer;">&times;</button>';
			printf(
				'<div style="font-weight:600;">%s%s <span style="font-weight:400;color:#667085;font-size:12px;">&middot; %s</span></div>',
				esc_html( (string) ( $it['title'] ?? '' ) ), $badge, esc_html( (string) ( $it['area'] ?? '' ) )
			);
			if ( ! empty( $it['detail'] ) ) echo '<div style="margin-top:3px;line-height:1.55;">' . esc_html( (string) $it['detail'] ) . '</div>';
			if ( ! empty( $it['action'] ) ) echo '<div style="margin-top:4px;color:#1a7f37;"><strong>Fix:</strong> ' . esc_html( (string) $it['action'] ) . '</div>';
			// Log what you actually did about this fix — date-stamped, editable, deletable.
			if ( $is_improve ) echo bp_audit_note_box( $ikey, $notes[ $ikey ] ?? null );
			echo '</li>';
		}
		echo '</ul>';
	};

	$block( 'What the site is doing well', (array) ( $r['doing_well'] ?? [] ), false );
	$block( 'Where it can improve',        (array) ( $r['improve'] ?? [] ),   true );
}

/** Latest audit + the dated history behind it. */
function bp_audit_ai_render(): void {
	$history = bp_audit_ai_history();
	$latest  = $history ? end( $history ) : null;

	// A half-finished run (browser tab closed, connection dropped) leaves its state behind. Say so —
	// the Run Audit button above turns into "Resume Audit" and picks up from the last completed step.
	$run = bp_audit_run_state();
	if ( $run ) {
		$bc    = get_option( 'bp_site_audit_stage' );
		$where = is_array( $bc ) && ! empty( $bc['stage'] ) ? ' It stopped during: <strong>' . esc_html( $bc['stage'] ) . '</strong>.' : '';
		echo '<div class="notice notice-warning" style="margin:0 0 18px;padding:12px 16px;"><p style="margin:0;">'
			. '<strong>A previous audit run didn&rsquo;t finish.</strong>' . $where
			. ' Use <strong>Resume Audit</strong> above to continue it, or start a fresh one.</p></div>';
	}

	echo '<div class="bp-ai-audit" style="margin:0 0 24px;padding:20px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">';

	if ( ! $latest || empty( $latest['summary'] ) ) {
		echo '<p style="margin:0;color:#666;">No audit yet — hit <strong>Run Audit</strong> above.';
		if ( '' === bp_audit_ai_key() ) echo ' <strong>An Anthropic API key is required.</strong>';
		echo '</p></div>';
		return;
	}

	echo '<h2 style="margin:0 0 6px;">Latest audit</h2>';
	bp_audit_ai_render_report( (array) $latest );
	echo '</div>';

	// One delegated script covers every note box on the page, including past reports below.
	bp_audit_notes_script();

	// Everything before the latest, newest first, collapsed.
	$past = $history;
	array_pop( $past );
	if ( ! $past ) return;

	echo '<div style="margin:0 0 28px;">';
	printf( '<h2 style="margin:0 0 10px;">History <span style="font-weight:400;color:#666;font-size:14px;">(%d earlier audit%s)</span></h2>', count( $past ), 1 === count( $past ) ? '' : 's' );
	foreach ( array_reverse( $past, true ) as $date => $r ) {
		$scores = (array) ( $r['scores'] ?? [] );
		$avg    = $scores ? (int) round( array_sum( array_map( 'intval', $scores ) ) / max( 1, count( $scores ) ) ) : null;
		echo '<details style="margin:0 0 8px;padding:12px 16px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">';
		printf(
			'<summary style="cursor:pointer;font-weight:600;">%s%s</summary>',
			esc_html( (string) $date ),
			null !== $avg ? ' <span style="font-weight:400;color:#667085;font-size:12px;">&middot; avg score ' . (int) $avg . '</span>' : ''
		);
		echo '<div style="margin-top:14px;">';
		bp_audit_ai_render_report( (array) $r );
		echo '</div></details>';
	}
	echo '</div>';
}
