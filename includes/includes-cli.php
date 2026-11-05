<?php
/**
 * Battle Plan WP-CLI — `wp bp …`
 * ---------------------------------------------------------------------------
 * Lets Claude Code (or anyone with SSH) read and edit a site's pages, SEO fields
 * and Page Top/Bottom over WP Engine SSH without knowing where each value lives.
 * Loaded from functions.php only when WP_CLI is defined, so it costs nothing on
 * a normal request.
 *
 *   wp bp pages [--type=<pt>] [--status=<s>] [--format=table|json|csv]
 *   wp bp get <post> [--field=<f>]
 *   wp bp seo <post> [--title=] [--desc=] [--og-title=] [--og-desc=] [--og-image=]
 *                    [--canonical=] [--noindex=0|1] [--nofollow=0|1] [--dry-run]
 *   wp bp content <post> [--field=content|top|bottom] [--dry-run]   < new.html
 *   wp bp audit [--render-limit=<n>] [--format=json|table]
 *   wp bp images <post> [--all] [--stdin] [--format=table|json]
 *   wp bp alt <attachment> [--alt=<text> | --ai [--post=<post>]] [--dry-run]
 *   wp bp log [--count=<n>]
 *   wp bp undo <entry>
 *   wp bp purge [<post>…]
 *
 * <post> = ID, slug, path (services/ac-repair), full URL, or "home".
 * Every write is logged with its before/after (option bp_cli_log, last 100) so
 * `wp bp undo` can put meta back — revisions only cover post_content.
 */

if ( ! defined('ABSPATH') || ! defined('WP_CLI') || ! WP_CLI ) return;

/*--------------------------------------------------------------
# Helpers
--------------------------------------------------------------*/

// --flag name → postmeta key. Order is the order `get` prints them in.
const BP_CLI_SEO_FIELDS = [
	'title'     => '_bp_seo_title',
	'desc'      => '_bp_seo_desc',
	'og-title'  => '_bp_seo_og_title',
	'og-desc'   => '_bp_seo_og_desc',
	'og-image'  => '_bp_seo_og_image',
	'canonical' => '_bp_seo_canonical',
	'noindex'   => '_bp_seo_robots_noindex',
	'nofollow'  => '_bp_seo_robots_nofollow',
];

// --field name for `content` → where it is stored ('' = post_content).
const BP_CLI_CONTENT_FIELDS = [
	'content' => '',
	'top'     => 'page-top_text',
	'bottom'  => 'page-bottom_text',
];

const BP_CLI_LOG = 'bp_cli_log';

/** ID, slug, path, URL or "home" → WP_Post, or a CLI error. */
function bp_cli_find_post( string $ref ): WP_Post {
	$ref = trim($ref);
	$id  = 0;

	$path = trim( (string) parse_url($ref, PHP_URL_PATH), '/' );

	if ( ctype_digit($ref) )                       $id = (int) $ref;
	elseif ( $ref === 'home' || $path === '' )     $id = (int) get_option('page_on_front');
	elseif ( preg_match('#^https?://#i', $ref) )   $id = url_to_postid($ref);

	if ( ! $id && ( $p = get_page_by_path( $path, OBJECT, bp_seo_content_types() ) ) ) $id = $p->ID;

	// Elements (site header, Widgets, footer) aren't SEO content but do hold site-wide markup.
	if ( ! $id && ( $p = get_page_by_path( $path, OBJECT, 'elements' ) ) ) $id = $p->ID;

	$post = $id ? get_post($id) : null;
	if ( ! $post || ! in_array($post->post_type, array_merge( bp_seo_content_types(), ['elements'] ), true) ) {
		WP_CLI::error("No page found for '{$ref}'. Try `wp bp pages` for the list.");
	}
	return $post;
}

/**
 * The title + description + robots this post actually renders on the front end.
 * Builds the main query for the post so every is_singular()/is_front_page() check
 * inside the SEO module answers exactly as it would on a real page view.
 */
function bp_cli_rendered( WP_Post $post ): array {
	global $wp_query, $wp_the_query;
	$saved = [ $wp_query, $wp_the_query, $GLOBALS['post'] ?? null ];

	$args = $post->post_type === 'page'
		? [ 'page_id' => $post->ID ]
		: [ 'p' => $post->ID, 'post_type' => $post->post_type ];
	$wp_query = $wp_the_query = new WP_Query( $args + [ 'post_status' => 'any' ] );
	$GLOBALS['post'] = $post;
	setup_postdata($post);

	$out = [
		'title'   => html_entity_decode( (string) bp_seo_document_title(''), ENT_QUOTES, 'UTF-8' ),
		'desc'    => html_entity_decode( bp_seo_description(), ENT_QUOTES, 'UTF-8' ),
		'noindex' => bp_seo_is_noindex(),
	];

	[ $wp_query, $wp_the_query, $GLOBALS['post'] ] = $saved;
	wp_reset_postdata();
	return $out;
}

/** Current stored value of one editable field. */
function bp_cli_read( WP_Post $post, string $key ): string {
	return $key === '' ? (string) $post->post_content : (string) get_post_meta($post->ID, $key, true);
}

/** Store one value. '' deletes meta (same as the editor box leaving a field blank). */
function bp_cli_write( WP_Post $post, string $key, string $value ): void {
	if ( $key === '' ) {
		// wp_update_post expects slashed input and saves a revision of the old content.
		$r = wp_update_post( wp_slash([ 'ID' => $post->ID, 'post_content' => $value ]), true );
		if ( is_wp_error($r) ) WP_CLI::error( $r->get_error_message() );
		return;
	}
	$value === '' ? delete_post_meta($post->ID, $key) : update_post_meta($post->ID, $key, wp_slash($value));
}

/**
 * Append a change to the log; returns the entry number. $related = other fields the
 * change rewrote as a side effect (an alt fix syncs into every page using the image),
 * as [post, key, before, after] — undo puts those back too.
 */
function bp_cli_log_change( WP_Post $post, string $cmd, array $changes, array $related = [] ): int {
	$log  = get_option(BP_CLI_LOG, []);
	$log  = is_array($log) ? $log : [];
	$next = $log ? max( array_column($log, 'n') ) + 1 : 1;
	$log[] = [
		'n'       => $next,
		'time'    => current_time('mysql'),
		'post'    => $post->ID,
		'url'     => get_permalink($post),
		'cmd'     => $cmd,
		'changes' => $changes,   // key => [before, after]
		'related' => $related,
	];
	update_option( BP_CLI_LOG, array_slice($log, -100), false );
	return $next;
}

/** Short before → after diff for the terminal. Long values get a line diff. */
function bp_cli_print_diff( string $label, string $before, string $after ): void {
	if ( $before === $after ) {
		WP_CLI::line("  {$label}: unchanged");
		return;
	}
	if ( strlen($before) < 300 && strlen($after) < 300 && strpos($before . $after, "\n") === false ) {
		WP_CLI::line("  {$label}:");
		WP_CLI::line('    - ' . ( $before === '' ? '(empty)' : $before ));
		WP_CLI::line('    + ' . ( $after  === '' ? '(empty)' : $after ));
		return;
	}
	$a   = explode("\n", $before);
	$b   = explode("\n", $after);
	$del = array_diff($a, $b);
	$add = array_diff($b, $a);
	WP_CLI::line("  {$label}: " . count($del) . ' line(s) removed, ' . count($add) . ' added');
	foreach ( array_slice($del, 0, 40) as $l ) WP_CLI::line('    - ' . mb_strimwidth($l, 0, 160, '…'));
	foreach ( array_slice($add, 0, 40) as $l ) WP_CLI::line('    + ' . mb_strimwidth($l, 0, 160, '…'));
}

/**
 * Purge the given posts from WP Engine's page cache and Cloudflare. The admin
 * "Clear Cache" button purges everything; a content edit only needs its own URLs.
 * $everything = whole site, for changes that show on every page (an image's library
 * alt renders in sliders, headers and widgets site-wide).
 */
function bp_cli_purge( array $posts, bool $everything = false ): string {
	$urls = array_values( array_filter( array_map('get_permalink', $posts) ) );
	$res  = [];

	if ( class_exists('WpeCommon') ) {
		if ( $everything ) WpeCommon::purge_varnish_cache();
		else foreach ( $posts as $p ) WpeCommon::purge_varnish_cache( $p->ID );
		WpeCommon::purge_memcached();
		$res[] = 'WPE: cleared';
	} else {
		$res[] = 'WPE: not available';
	}

	if ( ! _CF_CACHE_KEY ) return implode(' · ', array_merge($res, ['CF: not configured']));

	$zone = get_transient('bp_cf_zone_id');
	if ( ! $zone ) {
		$zone = bp_cf_resolve_zone_id( _CF_CACHE_KEY, parse_url(get_site_url(), PHP_URL_HOST) );
		if ( $zone ) set_transient('bp_cf_zone_id', $zone, WEEK_IN_SECONDS);
	}
	if ( ! $zone ) return implode(' · ', array_merge($res, ['CF: zone not found']));

	$r = wp_remote_post( "https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", [
		'headers' => [ 'Authorization' => 'Bearer ' . _CF_CACHE_KEY, 'Content-Type' => 'application/json' ],
		'body'    => wp_json_encode( $everything ? [ 'purge_everything' => true ] : [ 'files' => $urls ] ),
		'timeout' => 15,
	]);
	$ok = ! is_wp_error($r) && ! empty( json_decode(wp_remote_retrieve_body($r), true)['success'] );
	if ( ! $ok ) delete_transient('bp_cf_zone_id');   // a stale zone id would fail the same way all week
	$res[] = ! $ok ? 'CF: purge failed' : ( $everything ? 'CF: cleared everything' : 'CF: cleared ' . count($urls) . ' URL(s)' );
	return implode(' · ', $res);
}

/** Every <img> in $html as [src, alt (null = no attribute), class, data-id, decorative]. */
function bp_cli_parse_imgs( string $html ): array {
	preg_match_all('/<img\b[^>]*>/i', $html, $m);
	$out = [];
	foreach ( $m[0] as $tag ) {
		$attr = fn($name) => preg_match('/\b' . $name . '\s*=\s*("|\')(.*?)\1/is', $tag, $a) ? html_entity_decode($a[2], ENT_QUOTES, 'UTF-8') : null;
		$out[] = [
			'src'        => (string) $attr('src'),
			'alt'        => $attr('alt'),
			'class'      => (string) $attr('class'),
			'data-id'    => (int) $attr('data-id'),
			'decorative' => $attr('aria-hidden') === 'true' || $attr('role') === 'presentation',
		];
	}
	return $out;
}

/** The attachment an <img> shows, from its wp-image class, data-id, or file URL (any size). 0 = not in the library. */
function bp_cli_img_attachment( array $img ): int {
	if ( preg_match('/\bwp-image-(\d+)\b/', $img['class'], $m) && get_post_type( (int) $m[1] ) === 'attachment' ) return (int) $m[1];
	if ( $img['data-id'] && get_post_type($img['data-id']) === 'attachment' ) return $img['data-id'];

	$path = (string) parse_url($img['src'], PHP_URL_PATH);
	if ( ! preg_match('#/wp-content/uploads/(.+)$#', $path, $m) ) return 0;
	$rel  = $m[1];
	$base = preg_replace('/(?:-scaled)?(?:-\d+x\d+)?(\.[a-z0-9]+)$/i', '$1', $rel);
	$up   = wp_get_upload_dir()['baseurl'];
	$tries = [ $rel, $base, preg_replace('/(\.[a-z0-9]+)$/i', '-scaled$1', $base) ];
	foreach ( ['jpg', 'jpeg', 'png', 'webp'] as $ext ) $tries[] = preg_replace('/\.[a-z0-9]+$/i', ".{$ext}", $base);   // .webp shown for a .jpg upload
	foreach ( array_unique($tries) as $t ) {
		if ( $id = attachment_url_to_postid( "{$up}/{$t}" ) ) return $id;
	}
	return 0;
}

/**
 * The page's HTML as a visitor gets it — so header, sidebar, widgets, sliders and
 * shortcode output are all scanned, not just the stored fields. A browser UA because
 * the framework/WPE treat bot UAs differently; a throwaway query arg to miss the cache.
 */
function bp_cli_fetch_rendered( WP_Post $post ) {
	$r = wp_remote_get( add_query_arg( 'bp_scan', time(), get_permalink($post) ), [
		'timeout'    => 30,
		'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
	]);
	if ( is_wp_error($r) ) return $r;
	$code = wp_remote_retrieve_response_code($r);
	return $code === 200 ? wp_remote_retrieve_body($r) : new WP_Error('http', "HTTP {$code}");
}

/**
 * Post types worth auditing: the ones in the sitemap (same filter, so per-site exclusions
 * apply — jobsite records etc. are noindexed/301'd and would bury the real pages), minus
 * unregistered ones (bp_seo_content_types() always lists 'landing', but jobsite_geo sites
 * unregister it; those posts are orphans with no public URL).
 */
function bp_cli_page_types(): array {
	$types = array_keys( apply_filters('wp_sitemaps_post_types', array_flip( bp_seo_metabox_types() )) );
	return array_values( array_filter( $types, 'post_type_exists' ) );
}

/**
 * Every image on a page with its alt verdict. $html = the rendered page (header, sidebar,
 * sliders included); null scans only the page's stored fields. Rows carry full values.
 */
function bp_cli_scan_images( WP_Post $post, ?string $html, bool $all = false ): array {
	$fields = [];
	foreach ( BP_CLI_CONTENT_FIELDS as $f => $key ) $fields[$f] = bp_cli_read($post, $key);
	if ( $html === null ) $html = implode("\n", $fields);

	$rows = [];
	$seen = [];
	foreach ( bp_cli_parse_imgs($html) as $img ) {
		if ( $img['src'] === '' || strpos($img['src'], 'data:') === 0 ) continue;
		$key = $img['src'] . '|' . $img['alt'];
		if ( isset($seen[$key]) ) continue;
		$seen[$key] = true;

		$id   = bp_cli_img_attachment($img);
		$file = basename( (string) parse_url($img['src'], PHP_URL_PATH) );
		if ( strpos($img['src'], '/wp-content/themes/') !== false ) {
			$source = 'theme';
		} else {
			$source = 'site';
			foreach ( $fields as $f => $v ) if ( $file !== '' && strpos($v, $file) !== false ) { $source = $f; break; }
		}

		$issue = ( $img['decorative'] && (string) $img['alt'] === '' ) ? '' : bp_alt_weak_reason( (string) $img['alt'], $id, $img['src'] );
		// A testimonial headshot named after the customer is correct alt, not a lazy filename.
		if ( $issue !== 'missing' && strpos($img['class'], 'img-testimonials') !== false ) $issue = '';
		if ( $issue === '' && ! $all ) continue;

		$lib = $id ? (string) get_post_meta($id, '_wp_attachment_image_alt', true) : '';
		$rows[] = [
			'id'          => $id ?: '',
			'file'        => $file,
			'source'      => $source,
			'issue'       => $issue ?: 'ok',
			'alt'         => $img['alt'] === null ? '(none)' : $img['alt'],
			'library_alt' => $id ? ( $lib === '' ? '(empty)' : $lib ) : '(not in library)',
			'library_ok'  => $id ? ( bp_alt_weak_reason($lib, $id) === '' ? 'yes' : 'no' ) : '',
		];
	}
	return $rows;
}

/*--------------------------------------------------------------
# Command
--------------------------------------------------------------*/

/**
 * Read and edit Battle Plan pages, SEO fields and Page Top/Bottom.
 */
class BP_CLI_Command {

	public function __construct() {
		// Edits run as the agency account when it exists, so revisions are attributed and
		// kses doesn't strip markup (an anonymous CLI user lacks unfiltered_html).
		if ( ! get_current_user_id() && ( $u = get_user_by('login', 'battleplanweb') ) ) wp_set_current_user($u->ID);
		kses_remove_filters();
	}

	/**
	 * List the site's pages with their SEO status.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Post type(s), comma-separated. Default: the types that are in the sitemap.
	 *
	 * [--status=<status>]
	 * : Post status. Default: publish.
	 *
	 * [--format=<format>]
	 * : table, json or csv. Default: table.
	 *
	 * @subcommand pages
	 */
	public function pages( $args, $assoc ) {
		// Default = the types that go in the sitemap (same filter, so per-site exclusions apply):
		// jobsite records etc. are noindexed/301'd and would bury the real pages.
		$types = ! empty($assoc['type']) ? explode(',', $assoc['type']) : bp_cli_page_types();
		$posts = get_posts([
			'post_type'   => $types,
			'post_status' => $assoc['status'] ?? 'publish',
			'numberposts' => -1,
			'orderby'     => [ 'type' => 'ASC', 'menu_order' => 'ASC', 'title' => 'ASC' ],
		]);

		$rows = [];
		foreach ( $posts as $p ) {
			$t = (string) get_post_meta($p->ID, BP_SEO_TITLE, true);
			$d = (string) get_post_meta($p->ID, BP_SEO_DESC,  true);
			$rows[] = [
				'id'        => $p->ID,
				'type'      => $p->post_type,
				'path'      => wp_make_link_relative( get_permalink($p) ),
				'title'     => mb_strimwidth( get_the_title($p), 0, 40, '…' ),
				'seo_title' => $t === '' ? '(auto)' : mb_strimwidth($t, 0, 50, '…'),
				'seo_desc'  => $d === '' ? '(none)' : mb_strlen($d) . ' chars',
				'noindex'   => get_post_meta($p->ID, BP_SEO_NOINDEX, true) === '1' ? 'yes' : '',
			];
		}
		WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array_keys($rows[0] ?? ['id' => 0]) );
	}

	/**
	 * Show one page: content, Page Top/Bottom, stored SEO fields and what they render as.
	 *
	 * ## OPTIONS
	 *
	 * <post>
	 * : ID, slug, path, URL or "home".
	 *
	 * [--field=<field>]
	 * : Print one raw value only (content, top, bottom, or an SEO flag such as title / desc).
	 *
	 * @subcommand get
	 */
	public function get( $args, $assoc ) {
		$post = bp_cli_find_post($args[0]);

		if ( ! empty($assoc['field']) ) {
			$f   = $assoc['field'];
			$key = BP_CLI_CONTENT_FIELDS[$f] ?? BP_CLI_SEO_FIELDS[$f] ?? null;
			if ( $key === null ) WP_CLI::error("Unknown field '{$f}'.");
			WP_CLI::line( bp_cli_read($post, $key) );
			return;
		}

		$seo = [];
		foreach ( BP_CLI_SEO_FIELDS as $flag => $key ) $seo[$flag] = (string) get_post_meta($post->ID, $key, true);

		$out = [
			'id'       => $post->ID,
			'type'     => $post->post_type,
			'status'   => $post->post_status,
			'url'      => get_permalink($post),
			'title'    => $post->post_title,
			'seo'      => $seo,
			'rendered' => bp_cli_rendered($post) + [ 'limits' => [ 'title' => bp_seo_ai_limits('title'), 'desc' => bp_seo_ai_limits('desc') ] ],
			'content'  => $post->post_content,
			'top'      => bp_cli_read($post, BP_CLI_CONTENT_FIELDS['top']),
			'bottom'   => bp_cli_read($post, BP_CLI_CONTENT_FIELDS['bottom']),
		];
		WP_CLI::line( wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) );
	}

	/**
	 * Set SEO fields. Pass an empty value (--desc=) to clear one.
	 *
	 * ## OPTIONS
	 *
	 * <post>
	 * : ID, slug, path, URL or "home".
	 *
	 * [--title=<title>]
	 * : SEO title. Use %%sep%% / %%sitename%% / [get-biz] tokens, never hard-coded facts.
	 *
	 * [--desc=<desc>]
	 * : Meta description.
	 *
	 * [--og-title=<og-title>]
	 * : Social share title.
	 *
	 * [--og-desc=<og-desc>]
	 * : Social share description.
	 *
	 * [--og-image=<url>]
	 * : Social share image URL.
	 *
	 * [--canonical=<url>]
	 * : Canonical URL override.
	 *
	 * [--noindex=<0|1>]
	 * : 1 = keep this page out of Google.
	 *
	 * [--nofollow=<0|1>]
	 * : 1 = nofollow this page's links.
	 *
	 * [--dry-run]
	 * : Show the change and the rendered result without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bp seo ac-repair --title='AC Repair in [get-biz info="city"] %%sep%% %%sitename%%' --dry-run
	 *
	 * @subcommand seo
	 */
	public function seo( $args, $assoc ) {
		$post = bp_cli_find_post($args[0]);
		$dry  = ! empty($assoc['dry-run']);
		$set  = array_intersect_key( $assoc, BP_CLI_SEO_FIELDS );
		if ( ! $set ) WP_CLI::error('Nothing to set. Use --title=, --desc=, --og-title=, …');

		$ctx      = bp_seo_post_ctx($post);
		$vals     = bp_seo_token_values($ctx);
		$changes  = [];
		$warnings = [];

		foreach ( $set as $flag => $v ) {
			$v = (string) $v;
			if ( in_array($flag, ['noindex', 'nofollow'], true) ) {
				$v = in_array( strtolower($v), ['1', 'yes', 'true'], true ) ? '1' : '';
			} elseif ( in_array($flag, ['desc', 'og-desc'], true) ) {
				$v = sanitize_textarea_field($v);
			} else {
				$v = sanitize_text_field($v);
			}

			// Separators are always %%sep%% so a site can change its glyph in one place.
			if ( preg_match('/\s\|\s/', $v) ) WP_CLI::error("--{$flag} has a literal '|'. Use %%sep%% instead.");

			if ( in_array($flag, ['title', 'desc'], true) && $v !== '' ) {
				foreach ( bp_seo_ai_problems($flag, $v, $ctx, $vals) as $p ) $warnings[] = "--{$flag}: {$p}";
			}
			$changes[ BP_CLI_SEO_FIELDS[$flag] ] = [ (string) get_post_meta($post->ID, BP_CLI_SEO_FIELDS[$flag], true), $v ];
		}

		$this->apply( $post, 'seo', $changes, $dry, [ 'warnings' => $warnings ] );
	}

	/**
	 * Replace the page body, Page Top or Page Bottom with HTML read from stdin.
	 *
	 * ## OPTIONS
	 *
	 * <post>
	 * : ID, slug, path, URL or "home".
	 *
	 * [--field=<field>]
	 * : content (editor body), top (Page Top) or bottom (Page Bottom). Default: content.
	 *
	 * [--dry-run]
	 * : Show the diff without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     ssh site@site.ssh.wpengine.net 'wp bp content ac-repair --field=bottom' < bottom.html
	 *
	 * @subcommand content
	 */
	public function content( $args, $assoc ) {
		$post  = bp_cli_find_post($args[0]);
		$field = $assoc['field'] ?? 'content';
		if ( ! array_key_exists($field, BP_CLI_CONTENT_FIELDS) ) WP_CLI::error("--field must be content, top or bottom.");

		$new = stream_get_contents(STDIN);
		if ( $new === false || trim($new) === '' ) {
			WP_CLI::error('No content on stdin. Pipe the new HTML in: wp bp content <post> < new.html');
		}
		$new = rtrim( preg_replace("/\r\n?/", "\n", $new) );   // a trailing newline from the file isn't content
		$key = BP_CLI_CONTENT_FIELDS[$field];

		$this->apply( $post, "content --field={$field}", [ $key => [ bp_cli_read($post, $key), $new ] ], ! empty($assoc['dry-run']) );
	}

	/**
	 * Show recent `wp bp` edits on this site.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<n>]
	 * : How many entries. Default: 20.
	 *
	 * @subcommand log
	 */
	public function log( $args, $assoc ) {
		$log  = array_slice( (array) get_option(BP_CLI_LOG, []), -1 * (int) ( $assoc['count'] ?? 20 ) );
		$rows = array_map( fn($e) => [
			'entry'  => $e['n'],
			'time'   => $e['time'],
			'post'   => $e['post'],
			'url'    => wp_make_link_relative($e['url']),
			'cmd'    => $e['cmd'],
			'fields' => implode(', ', array_map( fn($k) => $k === '' ? 'post_content' : $k, array_keys($e['changes']) ))
				. ( empty($e['related']) ? '' : ' (+' . count($e['related']) . ' synced field(s))' ),
		], array_reverse($log) );
		$rows ? WP_CLI\Utils\format_items('table', $rows, array_keys($rows[0])) : WP_CLI::line('No edits logged yet.');
	}

	/**
	 * Put back the values an earlier edit replaced.
	 *
	 * ## OPTIONS
	 *
	 * <entry>
	 * : Entry number from `wp bp log`.
	 *
	 * [--dry-run]
	 * : Show what would be restored without saving.
	 *
	 * @subcommand undo
	 */
	public function undo( $args, $assoc ) {
		$entry = null;
		foreach ( (array) get_option(BP_CLI_LOG, []) as $e ) if ( (int) $e['n'] === (int) $args[0] ) $entry = $e;
		if ( ! $entry ) WP_CLI::error("No log entry #{$args[0]}.");

		$post = get_post( (int) $entry['post'] );
		if ( ! $post ) WP_CLI::error("Post #{$entry['post']} no longer exists.");

		$stale   = fn($key) => WP_CLI::warning( ( $key ?: 'post_content' ) . ' was changed again after entry #' . $entry['n'] . '; undo restores the value from before that entry anyway.' );
		$changes = [];
		foreach ( $entry['changes'] as $key => [ $before, $after ] ) {
			$now = bp_cli_read($post, $key);
			if ( $now !== $after ) $stale($key);
			$changes[$key] = [ $now, $before ];
		}
		$related = [];
		foreach ( $entry['related'] ?? [] as [ $pid, $key, $before, $after ] ) {
			if ( ! ( $p = get_post($pid) ) ) continue;
			$now = bp_cli_read($p, $key);
			if ( $now !== $after ) $stale("#{$pid} " . $key);
			$related[] = [ $pid, $key, $now, $before ];
		}
		$this->apply( $post, "undo #{$entry['n']}", $changes, ! empty($assoc['dry-run']), [ 'related' => $related, 'purge_all' => $post->post_type === 'attachment' ] );
	}

	/**
	 * Purge pages from the WP Engine and Cloudflare caches.
	 *
	 * ## OPTIONS
	 *
	 * [<post>...]
	 * : Pages to purge. Default: the home page.
	 *
	 * @subcommand purge
	 */
	public function purge( $args, $assoc ) {
		$posts = array_map( 'bp_cli_find_post', $args ?: ['home'] );
		WP_CLI::success( bp_cli_purge($posts) );
	}

	/**
	 * Whole-site SEO check: every indexable page's title + description, and every weak
	 * image alt (deduped across pages — a sidebar logo counts once). Read-only. Feeds the
	 * fleet runner (`bp-fleet audit`), so the default output is one JSON object.
	 *
	 * Page issues: title-auto (no custom title), title-long / title-short (rendered),
	 * title-dup (same rendered title as another page), desc-missing / desc-long / desc-short,
	 * pipe (literal |), hardcoded-title / hardcoded-desc (business name/city/phone typed out).
	 *
	 * ## OPTIONS
	 *
	 * [--render-limit=<n>]
	 * : Pages loaded over HTTP for the image scan (pages before posts; the rest use stored content). Default: 25.
	 *
	 * [--format=<format>]
	 * : json (default) or table (page summary only).
	 *
	 * @subcommand audit
	 */
	public function audit( $args, $assoc ) {
		$limit = (int) ( $assoc['render-limit'] ?? 25 );
		$posts = get_posts([ 'post_type' => bp_cli_page_types(), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => [ 'menu_order' => 'ASC', 'title' => 'ASC' ] ]);
		// Pages (and the home page above all) get the HTTP image scans first; blog posts last.
		$front = (int) get_option('page_on_front');
		usort( $posts, fn($a, $b) => [ $a->ID !== $front, $a->post_type === 'post', $a->post_type !== 'page' ] <=> [ $b->ID !== $front, $b->post_type === 'post', $b->post_type !== 'page' ] );

		$pages  = [];
		$titles = [];
		$images = [];
		$fetched = 0;
		$fetchFail = 0;
		foreach ( $posts as $p ) {
			$r = bp_cli_rendered($p);
			if ( $r['noindex'] ) continue;

			$t   = (string) get_post_meta($p->ID, BP_SEO_TITLE, true);
			$d   = (string) get_post_meta($p->ID, BP_SEO_DESC,  true);
			$tl  = mb_strlen($r['title']);
			$dl  = mb_strlen($r['desc']);
			$iss = [];
			if ( $t === '' )                              $iss[] = 'title-auto';
			if ( $tl > 60 )                               $iss[] = 'title-long';
			elseif ( $tl < 30 )                           $iss[] = 'title-short';
			if ( $dl === 0 )                              $iss[] = 'desc-missing';
			elseif ( $dl > 160 )                          $iss[] = 'desc-long';
			elseif ( $dl < 70 )                           $iss[] = 'desc-short';
			if ( preg_match('/\s\|\s/', "$t $d") )        $iss[] = 'pipe';
			if ( $t !== '' || $d !== '' ) {
				$ctx  = bp_seo_post_ctx($p);
				$vals = bp_seo_token_values($ctx);
				foreach ( [ 'title' => $t, 'desc' => $d ] as $f => $v ) {
					if ( $v === '' ) continue;
					foreach ( bp_seo_ai_problems($f, $v, $ctx, $vals) as $m ) if ( strpos($m, 'instead') !== false ) { $iss[] = "hardcoded-{$f}"; break; }
				}
			}

			$path = wp_make_link_relative( get_permalink($p) );
			$titles[ $r['title'] ][] = count($pages);
			$pages[] = [ 'id' => $p->ID, 'type' => $p->post_type, 'path' => $path, 'title' => $r['title'], 'desc' => $r['desc'], 'issues' => $iss ];

			$html = null;
			if ( $fetched < $limit ) {
				$fetched++;
				$h = bp_cli_fetch_rendered($p);
				if ( is_wp_error($h) ) $fetchFail++; else $html = $h;
			}
			foreach ( bp_cli_scan_images($p, $html) as $img ) {
				if ( $img['source'] === 'theme' ) $key = 'theme:' . $img['file'];
				else $key = $img['id'] !== '' ? 'id:' . $img['id'] : 'file:' . $img['file'];
				if ( ! isset($images[$key]) ) $images[$key] = $img + [ 'pages' => [] ];
				if ( count($images[$key]['pages']) < 5 ) $images[$key]['pages'][] = $path;
				$images[$key]['count'] = ( $images[$key]['count'] ?? 0 ) + 1;
			}
		}
		foreach ( $titles as $idx ) if ( count($idx) > 1 ) foreach ( $idx as $i ) $pages[$i]['issues'][] = 'title-dup';

		$need = array_values( array_filter( $pages, fn($p) => $p['issues'] ) );
		$out  = [
			'site'     => home_url('/'),
			'name'     => get_bloginfo('name'),
			'summary'  => [
				'pages'          => count($pages),
				'pages_need'     => count($need),
				'title_auto'     => count( array_filter( $pages, fn($p) => in_array('title-auto', $p['issues'], true) ) ),
				'desc_missing'   => count( array_filter( $pages, fn($p) => in_array('desc-missing', $p['issues'], true) ) ),
				'images_need'    => count( array_filter( $images, fn($i) => $i['source'] !== 'theme' ) ),
				'images_theme'   => count( array_filter( $images, fn($i) => $i['source'] === 'theme' ) ),
				'pages_rendered' => $fetched - $fetchFail,
				'render_failed'  => $fetchFail,
			],
			'pages'    => $need,
			'images'   => array_values($images),
		];

		if ( ( $assoc['format'] ?? 'json' ) === 'table' ) {
			WP_CLI\Utils\format_items( 'table', array_map( fn($p) => [ 'id' => $p['id'], 'path' => $p['path'], 'issues' => implode(' ', $p['issues']), 'title' => mb_strimwidth($p['title'], 0, 60, '…') ], $need ), ['id', 'path', 'issues', 'title'] );
			WP_CLI::line( wp_json_encode($out['summary']) );
			return;
		}
		WP_CLI::line( wp_json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) );
	}

	/**
	 * List the images on a page whose alt text is missing, just the file name, or too thin.
	 *
	 * Scans the page as visitors see it (header, sidebar, sliders included), then says
	 * where each image comes from:
	 *   content / top / bottom — in this page's own fields
	 *   site                   — elsewhere (header, widgets, sliders, other posts)
	 *   theme                  — a framework/theme file; fix it in code, not here
	 *
	 * ## OPTIONS
	 *
	 * <post>
	 * : ID, slug, path, URL or "home".
	 *
	 * [--all]
	 * : List every image, not just the ones that need work.
	 *
	 * [--stdin]
	 * : Read the page HTML from stdin instead of fetching it (when the server can't reach its own URL).
	 *
	 * [--format=<format>]
	 * : table or json. Default: table.
	 *
	 * @subcommand images
	 */
	public function images( $args, $assoc ) {
		$post = bp_cli_find_post($args[0]);

		$html = ! empty($assoc['stdin']) ? stream_get_contents(STDIN) : bp_cli_fetch_rendered($post);
		if ( is_wp_error($html) ) {
			WP_CLI::warning( 'Could not load the live page (' . $html->get_error_message() . '); scanning only its stored content. Pipe the page in with --stdin for a full scan.' );
			$html = null;
		}

		$rows = bp_cli_scan_images( $post, $html, ! empty($assoc['all']) );
		if ( ! $rows ) { WP_CLI::success('Every image on this page has good alt text.'); return; }
		$fmt = $assoc['format'] ?? 'table';
		if ( $fmt === 'table' ) {
			foreach ( $rows as &$r ) { $r['alt'] = mb_strimwidth($r['alt'], 0, 60, '…'); $r['library_alt'] = mb_strimwidth($r['library_alt'], 0, 50, '…'); }
			unset($r);
		}
		WP_CLI\Utils\format_items( $fmt, $rows, array_keys($rows[0]) );
	}

	/**
	 * Set an image's alt text in the Media Library and on every page that shows it.
	 *
	 * The library alt is what sliders, headers and featured images print. It is also
	 * pushed into stored <img> tags: tags with the wp-image-{ID} class are overwritten,
	 * hand-written tags for the same file only when their current alt is weak.
	 * With no --alt / --ai, the current library alt is pushed to the pages as-is.
	 *
	 * ## OPTIONS
	 *
	 * <attachment>
	 * : Attachment ID (the `id` column of `wp bp images`).
	 *
	 * [--alt=<text>]
	 * : The new alt text. Describe what's visible; 80–125 characters reads well.
	 *
	 * [--ai]
	 * : Have the framework's vision model write it (same as the Media Library ✨ button).
	 *
	 * [--post=<post>]
	 * : The page the image appears on, as context for --ai.
	 *
	 * [--dry-run]
	 * : Show the library change and every page tag it would rewrite, without saving.
	 *
	 * @subcommand alt
	 */
	public function alt( $args, $assoc ) {
		$att = get_post( (int) $args[0] );
		if ( ! $att || $att->post_type !== 'attachment' ) WP_CLI::error("#{$args[0]} is not a Media Library item.");

		$key    = '_wp_attachment_image_alt';
		$before = (string) get_post_meta($att->ID, $key, true);
		$dry    = ! empty($assoc['dry-run']);

		if ( ! empty($assoc['ai']) ) {
			if ( ! bp_ai_alt_available() ) WP_CLI::error('No Anthropic API key on this site; pass --alt instead.');
			$ctx   = ! empty($assoc['post']) ? bp_cli_find_post($assoc['post'])->ID : 0;
			$focus = get_post_meta($att->ID, '_bp_focus_position', true);
			$new   = bp_ai_generate_alt_text($att->ID, $ctx);
			if ( $dry ) $focus === '' ? delete_post_meta($att->ID, '_bp_focus_position') : update_post_meta($att->ID, '_bp_focus_position', $focus);   // generation also stores a focus point; a preview shouldn't
			if ( is_wp_error($new) ) WP_CLI::error( $new->get_error_message() );
		} else {
			$new = array_key_exists('alt', $assoc) ? (string) $assoc['alt'] : $before;
		}
		$new = sanitize_text_field($new);

		$warnings = [];
		if ( ( $why = bp_alt_weak_reason($new, $att->ID) ) !== '' ) {
			if ( $new === $before ) WP_CLI::error("The library alt is {$why} (\"{$before}\"). Pass --alt or --ai.");
			$warnings[] = "The new alt is {$why}.";
		}

		// What the sync will rewrite, computed against the NEW alt, before anything is saved.
		bp_alt_sync_to_content($att->ID, $new, true, $touched);

		$this->apply( $att, 'alt', [ $key => [ $before, $new ] ], $dry, [ 'warnings' => $warnings, 'related' => $touched, 'purge_all' => true ] );
	}

	/**
	 * Diff → (save → log → purge). Shared by every write command.
	 * $opts: warnings[], related[] ([post, key, before, after] side-effect fields, written
	 * after the main change), purge_all (whole-site cache purge).
	 */
	private function apply( WP_Post $post, string $cmd, array $changes, bool $dry, array $opts = [] ): void {
		$related = array_values( array_filter( $opts['related'] ?? [], fn($r) => $r[2] !== $r[3] ) );

		WP_CLI::line( ( $dry ? '[dry run] ' : '' ) . "#{$post->ID} " . ( $post->post_type === 'attachment' ? 'media: ' . basename( (string) get_attached_file($post->ID) ) : get_permalink($post) ) );
		foreach ( $changes as $key => [ $before, $after ] ) bp_cli_print_diff( $key ?: 'post_content', $before, $after );
		foreach ( $related as [ $pid, $key, $before, $after ] ) {
			bp_cli_print_diff( "also #{$pid} " . get_the_title($pid) . ' → ' . ( $key ?: 'post_content' ), $before, $after );
		}
		foreach ( $opts['warnings'] ?? [] as $w ) WP_CLI::warning($w);

		$changes = array_filter( $changes, fn($c) => $c[0] !== $c[1] );
		if ( ! $changes && ! $related ) { WP_CLI::success('Nothing to change.'); return; }

		if ( $dry ) {
			$this->print_rendered( $post, $changes, true );
			return;
		}

		foreach ( $changes as $key => [ , $after ] ) bp_cli_write($post, $key, $after);
		// Side-effect fields go in after the main write: a library alt save has already synced
		// most of them by now (the hook), so this only lands what the hook didn't.
		foreach ( $related as [ $pid, $key, , $after ] ) {
			$p = get_post($pid);
			if ( $p && bp_cli_read($p, $key) !== $after ) bp_cli_write($p, $key, $after);
			clean_post_cache($pid);
		}
		clean_post_cache($post->ID);
		$post = get_post($post->ID);

		$n = bp_cli_log_change($post, $cmd, $changes, $related);
		$this->print_rendered( $post, $changes, false );
		$purge = ! empty($opts['purge_all']) ? bp_cli_purge([], true) : bp_cli_purge( array_merge( [$post], array_filter( array_map( fn($r) => get_post($r[0]), $related ) ) ) );
		WP_CLI::success( "Saved (log entry #{$n}, undo with `wp bp undo {$n}`). " . $purge );
	}

	/** After an SEO change, show what Google will see. Dry runs preview via a filter. */
	private function print_rendered( WP_Post $post, array $changes, bool $preview ): void {
		$seoKeys = [ BP_SEO_TITLE, BP_SEO_DESC, BP_SEO_NOINDEX ];
		if ( ! array_intersect( array_keys($changes), $seoKeys ) ) return;

		$hook = null;
		if ( $preview ) {
			$hook = function( $value, $id, $key, $single ) use ( $post, $changes ) {
				if ( (int) $id !== $post->ID || ! isset($changes[$key]) ) return $value;
				return $single ? $changes[$key][1] : [ $changes[$key][1] ];
			};
			add_filter('get_post_metadata', $hook, 10, 4);
		}
		$r = bp_cli_rendered($post);
		if ( $hook ) remove_filter('get_post_metadata', $hook, 10);

		WP_CLI::line( '  renders as:' );
		WP_CLI::line( '    title (' . mb_strlen($r['title']) . '): ' . $r['title'] );
		WP_CLI::line( '    desc  (' . mb_strlen($r['desc'])  . '): ' . ( $r['desc'] === '' ? '(none)' : $r['desc'] ) );
		if ( $r['noindex'] ) WP_CLI::line( '    robots: noindex' );
	}
}

WP_CLI::add_command( 'bp', 'BP_CLI_Command' );
