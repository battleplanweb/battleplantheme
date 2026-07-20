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

	// Structured data types present.
	$schema = [];
	foreach ( $xp->query( '//script[@type="application/ld+json"]' ) as $s ) {
		$j = json_decode( trim( $s->textContent ), true );
		if ( ! is_array( $j ) ) continue;
		$stack = ( isset( $j['@graph'] ) && is_array( $j['@graph'] ) ) ? $j['@graph'] : [ $j ];
		foreach ( $stack as $item ) {
			if ( empty( $item['@type'] ) ) continue;
			$t = is_array( $item['@type'] ) ? implode( '/', $item['@type'] ) : (string) $item['@type'];
			$schema[ $t ] = true;
		}
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
		'schema_types'       => array_slice( array_keys( $schema ), 0, 10 ),
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
 * Everything the NIGHTLY CHRON already collected, compacted into the facts worth reasoning over.
 * The audit collects none of this itself — it just reads the chron's stores (day-keyed history
 * options), taking the most recent slice of each so the prompt stays tight.
 */
function bp_audit_ai_facts(): array {

	// Newest entry (or newest N entries) of a day-keyed history option.
	$recent = static function ( string $opt, int $n = 1 ) {
		$v = get_option( $opt );
		if ( ! is_array( $v ) || ! $v ) return null;
		ksort( $v );
		return ( 1 === $n ) ? end( $v ) : array_slice( $v, -$n, null, true );
	};
	$plain = static function ( string $opt ) {
		$v = get_option( $opt );
		return ( is_array( $v ) && $v ) ? $v : null;
	};
	$cap = static fn( $v, int $n ) => is_array( $v ) ? array_slice( $v, 0, $n, true ) : $v;

	$f = [];

	// ── Traffic & on-site behaviour (GA4) ──────────────────────────────────
	$f['traffic_rollups']  = $plain( 'bp_ga4_rollups_clean' );
	$f['top_pages']        = $cap( $recent( 'bp_ga4_pages_history' ), 12 );
	$f['top_locations']    = $cap( $recent( 'bp_ga4_locations_history' ), 12 );
	$f['traffic_channels'] = $cap( $recent( 'bp_ga4_channel_history' ), 12 );
	$f['key_events']       = $cap( $recent( 'bp_ga4_events_history' ), 15 );
	$f['scroll_depth']     = $recent( 'bp_ga4_scroll_history' );
	$f['real_user_speed']  = $plain( 'bp_ga4_speed_clean' );

	// ── How they're actually viewing it ───────────────────────────────────
	// Design critique is meaningless without this: the screenshots show a viewport, and only the
	// device mix says whether that viewport is what most visitors really see.
	$f['device_mix']        = $cap( $plain( 'bp_ga4_devices_clean' ), 10 );
	$f['screen_resolutions'] = $cap( $plain( 'bp_ga4_resolution_clean' ), 20 );
	$f['device_by_width']   = $plain( 'bp_ga4_device_width_clean' );
	$f['browsers']          = $cap( $plain( 'bp_ga4_browsers_clean' ), 10 );
	$f['referrers']         = $cap( $plain( 'bp_ga4_referrers_clean' ), 20 );

	// ── Search Console ────────────────────────────────────────────────────
	$f['search_console_last_30_days'] = $recent( 'bp_gsc_totals_history', 30 );
	$f['top_search_queries']          = $cap( $plain( 'bp_gsc_top_queries' ), 20 );
	$f['backlinks']                   = $recent( 'bp_gsc_links_history' );

	// ── Speed (lab / Lighthouse) ──────────────────────────────────────────
	$f['pagespeed_lab_recent'] = $recent( 'bp_cwv_lab_history', 3 );

	// ── Keywords ──────────────────────────────────────────────────────────
	$f['keyword_bands_recent'] = $recent( 'bp_kw_bands_history', 3 );
	if ( function_exists( 'bp_kw_tracked' ) ) {
		$kw = bp_kw_tracked();
		if ( is_array( $kw ) && $kw ) $f['tracked_keywords'] = array_slice( $kw, 0, 25 );
	}

	// ── Google Business Profile ───────────────────────────────────────────
	$f['google_business']       = $plain( 'bp_gbp_update' );
	$f['google_business_stats'] = $recent( 'bp_gbp_stats_history' );

	// ── Content inventory + UX signals (Microsoft Clarity) ────────────────
	$f['content_counts'] = $recent( 'bp_content_history' );
	$f['clarity_ux']     = $recent( 'bp_clarity_history' );

	return array_filter( $f, static fn( $v ) => ! is_null( $v ) && $v !== [] && $v !== '' );
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
function bp_audit_ai_prompt( array $pages, array $facts ): array {
	$ci       = function_exists( 'customer_info' ) ? customer_info() : [];
	$business = [
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
		'tagline'  => get_bloginfo( 'description' ),
		'type'     => is_array( $ci ) ? ( $ci['site-type'] ?? '' ) : '',
		'industry' => is_array( $ci ) ? ( $ci['industry'] ?? ( $ci['business-type'] ?? '' ) ) : '',
		'location' => is_array( $ci ) ? trim( ( $ci['city'] ?? '' ) . ' ' . ( $ci['state'] ?? '' ) ) : '',
	];

	$system = "You are a senior web strategist auditing a small-business marketing website. You combine "
		. "three lenses: conversion-rate optimisation, UX/usability, and visual design & layout — always "
		. "grounded in the data provided.\n\n"
		. "Rules:\n"
		. "- Be specific and practical. Name the actual page, heading, or number you're reacting to.\n"
		. "- Never invent metrics. If something isn't in the data, don't claim it; you may note it's missing.\n"
		. "- Judge like a prospective customer landing on the site, not like a checklist.\n"
		. "- Prioritise by likely revenue impact for a small business, not by ease.\n"
		. "- Write for the business owner: plain English, no jargon dumps.\n"
		. "- You are shown REAL SCREENSHOTS (above the fold) of the home page and ONE secondary page, each "
		. "in BOTH mobile and desktop. This site is built from two templates — the home page looks one way "
		. "and every secondary page looks another — so the secondary page you see represents all of them. "
		. "Critique what you actually see: visual hierarchy, whether the offer and primary CTA are obvious "
		. "in the first screen, contrast and legibility, spacing/crowding, image quality, brand consistency, "
		. "and how the mobile and desktop versions compare.\n"
		. "- WEIGHT YOUR DESIGN CRITIQUE BY THE REAL DEVICE MIX in `device_mix` / `screen_resolutions`. If most "
		. "visitors are on desktop, judge the desktop render first and say so; if most are on mobile, the "
		. "reverse. Name the actual split when it drives a recommendation.\n"
		. "- The screenshots are above-the-fold only; page STRUCTURE (headings, CTAs, copy volume, images/alt, "
		. "schema, links) covers the rest of each page and covers 8 pages, not 2. Don't claim to have seen "
		. "further down the page, and don't assume an unscreenshotted page looks different from the "
		. "secondary template you were shown.\n\n"
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

	$user = "BUSINESS\n" . wp_json_encode( $business, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		. "\n\nMEASURED DATA\n" . wp_json_encode( $facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		. "\n\nPAGE STRUCTURE (crawled)\n" . wp_json_encode( $pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
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

		[ $system, $user ] = bp_audit_ai_prompt( $pages, $facts );
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

	$block = static function ( string $heading, array $items, bool $is_improve ) {
		if ( ! $items ) return;
		echo '<h3 style="margin:18px 0 8px;">' . esc_html( $heading ) . '</h3>';
		echo '<ul style="margin:0;padding:0;list-style:none;">';
		foreach ( $items as $it ) {
			$pri   = strtolower( (string) ( $it['priority'] ?? '' ) );
			$pcol  = 'high' === $pri ? '#b42318' : ( 'medium' === $pri ? '#9a6700' : '#667085' );
			$badge = ( $is_improve && $pri )
				? sprintf( '<span style="display:inline-block;margin-left:8px;padding:1px 8px;border-radius:999px;background:%s;color:#fff;font-size:10px;text-transform:uppercase;letter-spacing:.05em;">%s</span>', esc_attr( $pcol ), esc_html( $pri ) )
				: '';
			echo '<li style="padding:10px 0;border-bottom:1px solid #f0f0f1;">';
			printf(
				'<div style="font-weight:600;">%s%s <span style="font-weight:400;color:#667085;font-size:12px;">&middot; %s</span></div>',
				esc_html( (string) ( $it['title'] ?? '' ) ), $badge, esc_html( (string) ( $it['area'] ?? '' ) )
			);
			if ( ! empty( $it['detail'] ) ) echo '<div style="margin-top:3px;line-height:1.55;">' . esc_html( (string) $it['detail'] ) . '</div>';
			if ( ! empty( $it['action'] ) ) echo '<div style="margin-top:4px;color:#1a7f37;"><strong>Fix:</strong> ' . esc_html( (string) $it['action'] ) . '</div>';
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
