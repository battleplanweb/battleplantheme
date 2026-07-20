<?php
/**
 * Battle Plan SEO — framework-native replacement for Yoast SEO (free + Premium + Local).
 * ---------------------------------------------------------------------------
 * Loaded from functions.php behind bp_module_on('seo'). Everything it does is
 * DERIVED FROM customer_info() — exactly like the old nightly Yoast-config block
 * in functions-chron-housekeeping.php, but computed live at render time instead
 * of poked into Yoast's options overnight. That whole cron block goes away.
 *
 * SAFETY: while the Yoast plugin is still active on a site, this module defers
 * completely (see bp_seo_defer()) — Yoast keeps owning the <head>, so
 * there is never double output. The day you deactivate Yoast on a site, this
 * module seamlessly takes over. That means it is safe to turn the 'seo' module
 * on fleet-wide immediately; each site cuts over only when Yoast is removed.
 *
 * Per-page overrides live in _bp_seo_* postmeta (populated by the Phase-2
 * importer that copies _yoast_wpseo_*). Global title formats / noindex rules /
 * schema identity are framework conventions here, overridable via filters.
 *
 * Map to the old cron (functions-chron-housekeeping.php ~L78-270):
 *   wpseo_local   -> §4 Schema (LocalBusiness, via ci_build_schema / customer_info['schema'])
 *   wpseo         -> §6 Head hardening + §7 Crawl optimization + §8/§9 archives/category
 *   wpseo_titles  -> §2 Titles + §3 robots/noindex + §4 Organization identity
 *   wpseo_social  -> §4 sameAs[] + §3 default OG image
 */

if ( ! defined('ABSPATH') ) exit;

/*--------------------------------------------------------------
# §0. Config + guards
--------------------------------------------------------------*/

/**
 * Should the module stand down for this request? True when a Yoast plugin is
 * still active (Yoast keeps owning the <head>, so we never double-render) OR
 * when a site opts out via add_filter('bp_seo_enabled','__return_false'). Every
 * front-end + admin surface checks this, so the module is safe to load fleet-wide.
 */
function bp_seo_defer(): bool {
	if ( ! apply_filters('bp_seo_enabled', true) ) return true;     // per-site kill switch
	if ( ! function_exists('is_plugin_active') ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active('wordpress-seo-premium/wp-seo-premium.php')
	    || is_plugin_active('wordpress-seo/wp-seo.php');
}

/** Separator glyph. Old cron used 'sc-bull' (Yoast's bullet). Filterable. */
function bp_seo_sep(): string {
	return apply_filters('bp_seo_separator', '•');
}

/**
 * Post types that get title/meta/OG treatment + an editor meta box.
 * Every PUBLIC post type qualifies automatically (so a new CPT needs zero SEO
 * wiring); a handful of WP/ACF system types are never content and are excluded.
 * Filter `bp_seo_content_types` to add/remove per site.
 */
function bp_seo_content_types(): array {
	$exclude = [
		'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
		'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part',
		'wp_navigation', 'wp_global_styles', 'acf-field-group', 'acf-field', 'elements',
	];
	$always = ['post', 'page', 'universal', 'products', 'landing', 'events']; // present even if not flagged public
	$public = array_diff( get_post_types(['public' => true], 'names'), $exclude );
	$types  = array_values( array_unique( array_merge($always, $public) ) );
	return apply_filters('bp_seo_content_types', $types);
}

/** Post types where the editor meta box shows (old cron hid it on these via display-metabox-pt-*=0). */
function bp_seo_metabox_types(): array {
	$hide  = ['testimonials', 'elements', 'products', 'universal'];
	$types = array_values(array_diff(bp_seo_content_types(), $hide));
	return apply_filters('bp_seo_metabox_types', $types);
}

// Per-post meta keys (clean-break _bp_seo_* namespace; importer copies _yoast_wpseo_* into these).
const BP_SEO_TITLE     = '_bp_seo_title';
const BP_SEO_DESC      = '_bp_seo_desc';
const BP_SEO_CANONICAL = '_bp_seo_canonical';
const BP_SEO_NOINDEX   = '_bp_seo_robots_noindex';   // '1' = noindex
const BP_SEO_NOFOLLOW  = '_bp_seo_robots_nofollow';  // '1' = nofollow
const BP_SEO_OG_TITLE  = '_bp_seo_og_title';
const BP_SEO_OG_DESC   = '_bp_seo_og_desc';
const BP_SEO_OG_IMAGE  = '_bp_seo_og_image';
const BP_SEO_PRIMARYCAT= '_bp_seo_primary_cat';

/*--------------------------------------------------------------
# §1. Replacement-variable resolver
# Yoast %%vars%% used by the old cron's templates.
--------------------------------------------------------------*/

function bp_seo_resolve_vars( string $template, array $ctx = [] ): string {
	$queried = get_queried_object();
	$sep     = bp_seo_sep();

	// %%page%% -> "Page X of Y" only when paginated, else empty (Yoast behavior).
	$paged = max( (int) get_query_var('paged'), (int) get_query_var('page') );
	$pages = (int) ( $ctx['max_pages'] ?? ( $GLOBALS['wp_query']->max_num_pages ?? 0 ) );
	$pageStr = ( $paged > 1 && $pages > 1 )
		? sprintf( /* translators: 1: current page, 2: total pages */ 'Page %1$d of %2$d', $paged, $pages )
		: '';

	$title = $ctx['title'] ?? ( is_singular() && $queried instanceof WP_Post ? get_the_title($queried) : '' );

	$name = '';
	if ( $queried instanceof WP_User )            $name = $queried->display_name;
	elseif ( is_author() )                        $name = get_the_author_meta('display_name', (int) get_query_var('author'));

	$date = '';
	if ( is_year() )       $date = get_query_var('year');
	elseif ( is_month() )  $date = single_month_title(' ', false);
	elseif ( is_day() )    $date = get_the_date();

	$primaryCat = $ctx['primary_cat'] ?? '';
	if ( $primaryCat === '' && is_singular() && $queried instanceof WP_Post ) {
		$pc = (int) get_post_meta($queried->ID, BP_SEO_PRIMARYCAT, true);
		if ( $pc )                              $primaryCat = get_cat_name($pc);
		else {
			$cats = get_the_category($queried->ID);
			if ( $cats )                        $primaryCat = $cats[0]->name;
		}
	}

	$ptObj    = is_post_type_archive() ? get_post_type_object( get_query_var('post_type') ) : null;
	$ptPlural = $ptObj ? $ptObj->labels->name          : '';
	$ptSingle = $ptObj ? $ptObj->labels->singular_name : '';

	$map = [
		'%%title%%'          => $title,
		'%%sitename%%'       => get_bloginfo('name'),
		'%%sitedesc%%'       => get_bloginfo('description'),
		'%%sep%%'            => $sep,
		'%%page%%'           => $pageStr,
		'%%pagenumber%%'     => $paged > 1 ? (string) $paged : '',
		'%%pagetotal%%'      => $pages > 1 ? (string) $pages : '',
		'%%name%%'           => $name,
		'%%date%%'           => $date,
		'%%searchphrase%%'   => esc_html( get_search_query() ),
		'%%primary_category%%' => $primaryCat,
		'%%category%%'       => $primaryCat,
		'%%pt_plural%%'      => $ptPlural,
		'%%pt_single%%'      => $ptSingle,
		'%%currentyear%%'    => gmdate('Y'),
		'%%currentdate%%'    => get_the_date(),
	];
	$map = apply_filters('bp_seo_vars', $map, $ctx);

	$out = strtr( $template, $map );

	// Collapse artifacts from empty vars: doubled separators, leading/trailing sep, extra spaces.
	$q = preg_quote($sep, '/');
	$out = preg_replace('/\s+/', ' ', $out);
	$out = preg_replace('/(?:\s*' . $q . '\s*){2,}/', ' ' . $sep . ' ', $out); // •  • -> •
	$out = preg_replace('/^\s*(?:' . $q . '\s*)+/', '', $out);                  // strip leading sep
	$out = preg_replace('/(?:\s*' . $q . ')+\s*$/', '', $out);                  // strip trailing sep
	return trim($out);
}

/*--------------------------------------------------------------
# §2. Title
# Templates mirror the old cron's wpseo_titles exactly.
--------------------------------------------------------------*/

/** Raw title template for the current query context (before %%var%% resolution). */
function bp_seo_title_template(): string {
	$tail = ' %%page%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';

	if ( is_front_page() || is_home() ) {
		$tpl = '%%page%% %%sep%% %%sitename%% %%sep%% %%sitedesc%%';
	} elseif ( is_search() ) {
		$tpl = 'You searched for %%searchphrase%% %%sep%% %%sitename%%';
	} elseif ( is_404() ) {
		$tpl = 'Page Not Found %%sep%% %%sitename%%';
	} elseif ( is_author() ) {
		$tpl = '%%name%%, Author at %%sitename%% %%page%%';
	} elseif ( is_date() || is_archive() && ! is_post_type_archive() && ! is_category() && ! is_tag() && ! is_tax() ) {
		$tpl = 'Archive %%sep%% %%sitename%% %%sep%% %%date%%';
	} elseif ( is_post_type_archive() ) {
		$tpl = '%%pt_plural%%' . $tail;
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$tpl = '%%title%%' . $tail; // %%title%% resolves to the term name via ctx below
	} elseif ( is_singular() ) {
		$pt      = get_post_type();
		$tpl     = in_array($pt, bp_seo_content_types(), true)
			? '%%title%%' . $tail
			: ucfirst($pt) . $tail;
	} else {
		$tpl = '%%title%%' . $tail;
	}

	return apply_filters('bp_seo_title_template', $tpl);
}

add_filter('pre_get_document_title', 'bp_seo_document_title', 20);
function bp_seo_document_title( $title ) {
	if ( bp_seo_defer() ) return $title;

	// Per-page override wins.
	if ( is_singular() ) {
		$override = get_post_meta( get_queried_object_id(), BP_SEO_TITLE, true );
		if ( $override !== '' ) {
			return bp_seo_resolve_vars( $override );
		}
	}

	$ctx = [];
	if ( ( is_category() || is_tag() || is_tax() ) && ( $term = get_queried_object() ) instanceof WP_Term ) {
		$ctx['title'] = $term->name;
		$tOverride    = get_term_meta( $term->term_id, BP_SEO_TITLE, true ); // imported term SEO title
		if ( $tOverride !== '' ) return bp_seo_resolve_vars( $tOverride, $ctx );
	}

	$built = bp_seo_resolve_vars( bp_seo_title_template(), $ctx );
	return $built !== '' ? $built : $title;
}

/*--------------------------------------------------------------
# §3. Meta description, robots, canonical, OpenGraph/Twitter
--------------------------------------------------------------*/

/**
 * Meta description for the current view, or '' when none (parity with Yoast: blank
 * unless set). Stored values may carry %%vars%% (Yoast allowed them), so we resolve.
 */
function bp_seo_description(): string {
	if ( is_singular() ) {
		$d = get_post_meta( get_queried_object_id(), BP_SEO_DESC, true );
		if ( $d !== '' ) return bp_seo_resolve_vars( wp_strip_all_tags( $d ) );
	}
	if ( ( is_category() || is_tag() || is_tax() ) && ( $t = get_queried_object() ) instanceof WP_Term ) {
		$td = get_term_meta( $t->term_id, BP_SEO_DESC, true );          // imported term SEO desc
		if ( $td !== '' )                 return bp_seo_resolve_vars( wp_strip_all_tags( $td ), ['title' => $t->name] );
		if ( ! empty($t->description) )   return wp_strip_all_tags( $t->description );
	}
	if ( is_front_page() || is_home() ) {
		return wp_strip_all_tags( get_bloginfo('description') );
	}
	return '';
}

/** Noindex decision for the current query — mirrors the old cron's noindex-* map. */
function bp_seo_is_noindex(): bool {
	$noindex = false;

	if ( is_singular() ) {
		$id = get_queried_object_id();
		if ( get_post_meta($id, BP_SEO_NOINDEX, true) === '1' ) $noindex = true;
		// CPTs the old cron flagged noindex-{pt}=1 — plus jobsite_geo raw records (/jobsites/…), which
		// exist only to feed the /service/ pages; we don't want them found or landed on directly.
		// products = shared HVAC brand partials, identical across the whole dealer fleet → noindex.
		if ( in_array( get_post_type($id), ['testimonials', 'elements', 'products', 'universal', 'jobsite_geo'], true ) ) $noindex = true;
	}
	if ( is_author() || is_date() )              $noindex = true;   // noindex-author / disable-date archives
	if ( is_search() || is_404() )               $noindex = true;

	if ( is_post_type_archive( ['testimonials', 'products', 'galleries', 'optimized'] ) ) $noindex = true;

	if ( is_tax( ['gallery-type', 'gallery-tags', 'image-categories', 'image-tags',
	              'jobsite_geo-service-types', 'jobsite_geo-service-areas', 'jobsite_geo-techs',
	              'product-brand', 'product-type', 'product-class'] ) ) $noindex = true;

	return (bool) apply_filters('bp_seo_noindex', $noindex, get_queried_object());
}

/** Feed the robots meta via core's wp_robots() (WP 5.7+). */
add_filter('wp_robots', 'bp_seo_wp_robots', 20);
function bp_seo_wp_robots( array $robots ) {
	if ( bp_seo_defer() ) return $robots;

	if ( bp_seo_is_noindex() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset($robots['index'], $robots['follow']);
	} else {
		$robots['index']  = true;
		$robots['follow'] = true;
		// max-image-preview:large so Google can show rich thumbnails (Yoast default).
		$robots['max-image-preview'] = 'large';
	}
	if ( is_singular() && get_post_meta( get_queried_object_id(), BP_SEO_NOFOLLOW, true ) === '1' ) {
		$robots['nofollow'] = true;
		unset($robots['follow']);
	}
	return $robots;
}

/** Canonical URL for the current view (honoring per-page override). Core only does singular; we do all. */
function bp_seo_canonical(): string {
	if ( is_singular() ) {
		$id  = get_queried_object_id();
		$ovr = get_post_meta($id, BP_SEO_CANONICAL, true);
		if ( $ovr !== '' ) return esc_url( $ovr );
		return get_permalink( $id );
	}
	if ( is_front_page() )                 return home_url('/');
	if ( is_home() )                       return get_permalink( (int) get_option('page_for_posts') ) ?: home_url('/');
	if ( ( is_category() || is_tag() || is_tax() ) && ( $t = get_queried_object() ) instanceof WP_Term ) {
		$link = get_term_link($t);
		if ( ! is_wp_error($link) ) return $link;
	}
	if ( is_post_type_archive() )          return get_post_type_archive_link( get_query_var('post_type') ) ?: '';
	if ( is_author() )                     return get_author_posts_url( (int) get_query_var('author') );

	$req = home_url( add_query_arg( [], $GLOBALS['wp']->request ? '/' . $GLOBALS['wp']->request . '/' : '/' ) );
	return $req;
}

// Replace core's singular-only canonical with our all-context one.
remove_action('wp_head', 'rel_canonical');

/** The OG image URL for the current view: per-post override → featured image → site logo/default. */
function bp_seo_og_image(): string {
	if ( is_singular() ) {
		$id  = get_queried_object_id();
		$ovr = get_post_meta($id, BP_SEO_OG_IMAGE, true);
		if ( $ovr !== '' ) return esc_url($ovr);
		if ( has_post_thumbnail($id) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id($id), 'large' );
			if ( $src ) return $src[0];
		}
	}
	$ci = customer_info();
	if ( ! empty($ci['logo']) ) {
		return is_array($ci['logo']) ? ( $ci['logo']['url'] ?? '' ) : $ci['logo'];
	}
	// uploads-folder logo detection (same order as the old cron)
	foreach ( ['logo.webp','logo.png','logo.jpg','site-icon.webp','site-icon.png','site-icon.jpg'] as $f ) {
		if ( is_file( WP_CONTENT_DIR . '/uploads/' . $f ) ) return content_url('/uploads/' . $f);
	}
	return '';
}

/** Print description, canonical, and OG/Twitter tags. */
add_action('wp_head', 'bp_seo_head_meta', 1);
function bp_seo_head_meta() {
	if ( bp_seo_defer() ) return;

	$title = wp_get_document_title();
	$desc  = bp_seo_description();
	$canon = bp_seo_canonical();
	$img   = bp_seo_og_image();
	$ci    = customer_info();

	// Per-page OG title/description overrides fall back to the doc title / meta description.
	$ogTitle = $title;
	$ogDesc  = $desc;
	if ( is_singular() ) {
		$id = get_queried_object_id();
		if ( ( $v = get_post_meta($id, BP_SEO_OG_TITLE, true) ) !== '' ) $ogTitle = bp_seo_resolve_vars($v);
		if ( ( $v = get_post_meta($id, BP_SEO_OG_DESC,  true) ) !== '' ) $ogDesc  = wp_strip_all_tags($v);
	}

	echo "\n<!-- Battle Plan SEO -->\n";

	if ( $desc !== '' ) {
		echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
	}
	if ( $canon !== '' ) {
		echo '<link rel="canonical" href="' . esc_url($canon) . '">' . "\n";
	}

	// OpenGraph
	echo '<meta property="og:locale" content="' . esc_attr( get_bloginfo('language') ) . '">' . "\n";
	echo '<meta property="og:type" content="' . ( is_singular('post') ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr($ogTitle) . '">' . "\n";
	if ( $ogDesc !== '' ) echo '<meta property="og:description" content="' . esc_attr($ogDesc) . '">' . "\n";
	if ( $canon !== '' )  echo '<meta property="og:url" content="' . esc_url($canon) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo('name') ) . '">' . "\n";
	if ( $img !== '' )    echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n";

	// Twitter
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr($ogTitle) . '">' . "\n";
	if ( $ogDesc !== '' ) echo '<meta name="twitter:description" content="' . esc_attr($ogDesc) . '">' . "\n";
	if ( $img !== '' )    echo '<meta name="twitter:image" content="' . esc_url($img) . '">' . "\n";
	if ( ! empty($ci['twitter']) ) {
		$handle = '@' . ltrim( basename( rtrim( (string) $ci['twitter'], '/' ) ), '@' );
		echo '<meta name="twitter:site" content="' . esc_attr($handle) . '">' . "\n";
	}
}

/*--------------------------------------------------------------
# §4. Schema JSON-LD @graph
# Emits the SAME LocalBusiness node ci_build_schema() already computes into
# customer_info['schema'] (old cron fed it to Yoast; here we output it directly),
# plus WebSite + WebPage, and re-applies the two areaServed narrowings that used
# to hang off the wpseo_schema_organization filter.
--------------------------------------------------------------*/

/** Narrow areaServed on /service/{service}-{city}-{state}/ pages (was functions.php wpseo_schema_organization). */
function bp_seo_narrow_area_service( array $node ): array {
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	if ( strpos($uri, '/service/') !== 0 || empty($node['areaServed']) ) return $node;

	$slug  = trim( str_replace('/service/', '', $uri), '/' );
	$parts = explode('-', strtolower($slug));
	if ( count($parts) < 2 ) return $node;

	array_pop($parts);                       // drop trailing state token
	$noState = implode('-', $parts);

	$filtered = array_values( array_filter( (array) $node['areaServed'], function($a) use ($noState) {
		if ( empty($a['name']) ) return false;
		$city = trim( explode(',', strtolower($a['name']), 2)[0] ?? '' );
		if ( $city === '' ) return false;
		$citySlug = str_replace(' ', '-', $city);
		$lc = strlen($citySlug); $ls = strlen($noState);
		if ( $lc === 0 || $ls < $lc ) return false;
		return substr($noState, $ls - $lc) === $citySlug;
	} ) );

	if ( $filtered ) $node['areaServed'] = $filtered;
	return $node;
}

/** Narrow areaServed on jobsite_geo city pages (was includes-jobsite-geo.php wpseo_schema_organization). */
function bp_seo_narrow_area_jobsite( array $node ): array {
	$city = $GLOBALS['jobsite_geo-city'] ?? '';
	if ( ! $city || empty($node['areaServed']) ) return $node;
	$parts = explode('-', sanitize_title($city));
	$node['areaServed'] = array_values( array_filter( (array) $node['areaServed'], function($a) use ($parts) {
		if ( empty($a['name']) ) return false;
		return isset($parts[0]) && strpos( sanitize_title($a['name']), $parts[0] ) !== false;
	} ) );
	return $node;
}

add_action('wp_head', 'bp_seo_schema_graph', 2);
function bp_seo_schema_graph() {
	if ( bp_seo_defer() ) return;

	$ci = customer_info();

	// Organization / LocalBusiness node — reuse the already-built schema, or build live.
	// Guard against a malformed (non-array) stored schema so it can't fatal the whole page.
	$org = $ci['schema'] ?? ( function_exists('ci_build_schema') ? ci_build_schema($ci) : [] );
	if ( ! is_array($org) ) $org = [];
	if ( ! empty($org) ) {
		unset($org['@context']);                    // context lives at graph root
		$org['@id'] = home_url('#organization');
		$org = bp_seo_narrow_area_service($org);
		$org = bp_seo_narrow_area_jobsite($org);
	}

	$site = [
		'@type' => 'WebSite',
		'@id'   => home_url('#website'),
		'url'   => home_url('/'),
		'name'  => get_bloginfo('name'),
		'publisher'      => [ '@id' => home_url('#organization') ],
		'potentialAction'=> [[
			'@type'       => 'SearchAction',
			'target'      => [ '@type' => 'EntryPoint', 'urlTemplate' => home_url('/?s={search_term_string}') ],
			'query-input' => 'required name=search_term_string',
		]],
	];

	$canon = bp_seo_canonical();
	$page  = [
		'@type'    => is_singular('post') ? 'Article' : 'WebPage',
		'@id'      => ( $canon ?: home_url('/') ) . '#webpage',
		'url'      => $canon ?: home_url('/'),
		'name'     => wp_get_document_title(),
		'isPartOf' => [ '@id' => home_url('#website') ],
		'about'    => [ '@id' => home_url('#organization') ],
	];
	if ( ( $d = bp_seo_description() ) !== '' ) $page['description'] = $d;

	$graph = array_values( array_filter([ $org ?: null, $site, $page ]) );
	$graph = apply_filters('bp_seo_schema_graph', $graph, $ci);

	$json = wp_json_encode(
		[ '@context' => 'https://schema.org', '@graph' => $graph ],
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
	echo "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n";
}

/*--------------------------------------------------------------
# §5. (reserved — noindex map lives in §3 bp_seo_is_noindex)
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# §6. Head hardening  (old cron: the wpseo remove_* block)
# Everything Yoast was quietly stripping from <head> / headers.
--------------------------------------------------------------*/
add_action('init', 'bp_seo_head_hardening');
function bp_seo_head_hardening() {
	if ( bp_seo_defer() ) return;

	// Feeds (remove_feed_* + automatic-feed-links the theme turns on)
	remove_theme_support('automatic-feed-links');
	remove_action('wp_head', 'feed_links', 2);
	remove_action('wp_head', 'feed_links_extra', 3);

	// remove_rsd_wlw_links
	remove_action('wp_head', 'rsd_link');
	remove_action('wp_head', 'wlwmanifest_link');

	// remove_generator
	remove_action('wp_head', 'wp_generator');
	add_filter('the_generator', '__return_empty_string');

	// remove_shortlinks
	remove_action('wp_head', 'wp_shortlink_wp_head');
	remove_action('template_redirect', 'wp_shortlink_header', 11);

	// remove_rest_api_links (keep the API itself; just drop the <head> discovery link)
	remove_action('wp_head', 'rest_output_link_wp_head');
	remove_action('template_redirect', 'rest_output_link_header', 11);

	// remove_oembed_links (discovery + host js)
	remove_action('wp_head', 'wp_oembed_add_discovery_links');
	remove_action('wp_head', 'wp_oembed_add_host_js');
	remove_action('rest_api_init', 'wp_oembed_register_route');

	// remove_pingback_header + remove_powered_by_header
	add_filter('wp_headers', function($headers) {
		unset($headers['X-Pingback'], $headers['X-Powered-By']);
		return $headers;
	});

	// remove_emoji_scripts (+ their DNS-prefetch and TinyMCE plugin)
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'print_emoji_styles');
	remove_filter('the_content_feed', 'wp_staticize_emoji');
	remove_filter('comment_text_rss', 'wp_staticize_emoji');
	remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
	add_filter('emoji_svg_url', '__return_false');
}

/*--------------------------------------------------------------
# §7. Crawl optimization  (old cron: deny_*_crawling, search_cleanup, redirect_search_pretty_urls)
--------------------------------------------------------------*/

// deny_wp_json_crawling — X-Robots-Tag: noindex on REST responses (endpoints stay functional).
add_filter('rest_post_dispatch', function($response) {
	if ( ! bp_seo_defer() && $response instanceof WP_REST_Response ) $response->header('X-Robots-Tag', 'noindex');
	return $response;
}, 10, 1);

// robots.txt — deny search + wp-json crawling; keep good bots out of the footer honeypot; keep the
// sitemap reference. Serving this dynamically means NO physical /robots.txt file is needed — a
// physical file silently overrides all of this (and would drop the Sitemap: line).
add_filter('robots_txt', function($output, $public) {
	if ( ! $public || bp_seo_defer() ) return $output;
	$lines  = "User-agent: *\n";
	$lines .= "Disallow: /wp-admin/\n";
	$lines .= "Allow: /wp-admin/admin-ajax.php\n";
	$lines .= "Disallow: /wp-login.php\n";
	$lines .= "Disallow: /xmlrpc.php\n";
	$lines .= "Disallow: /wp-json/\n";
	$lines .= "Disallow: /*?s=\n";
	$lines .= "Disallow: /?s=\n";
	$lines .= "Disallow: /search/\n";
	// NB: intentionally NOT blocking /wp-content/themes|plugins/ — Google needs that CSS/JS to
	// render pages; blocking it (as the old physical template did) can hurt rankings.
	// Spam honeypot: keep well-behaved crawlers out of the tripwire so only bots that IGNORE
	// robots.txt trip it. Path derived from the parent theme so it never drifts from footer.php.
	$bot = wp_parse_url( get_template_directory_uri(), PHP_URL_PATH );
	if ( $bot ) $lines .= 'Disallow: ' . trailingslashit( $bot ) . "_bot/\n";
	$lines .= "\nSitemap: " . home_url('/wp-sitemap.xml') . "\n";
	return $lines;
}, 10, 2);

// redirect_search_pretty_urls — /?s=term → /search/term/ (pretty permalinks only).
add_action('template_redirect', function() {
	if ( bp_seo_defer() || ! is_search() || ! get_option('permalink_structure') ) return;
	if ( empty($_GET['s']) ) return;
	$term = get_search_query( false );
	if ( $term === '' ) return;
	$pretty = home_url( user_trailingslashit( 'search/' . urlencode($term) ) );
	$current = home_url( add_query_arg([], $_SERVER['REQUEST_URI'] ?? '') );
	if ( strpos($current, '/search/') === false ) {
		wp_safe_redirect( $pretty, 301 );
		exit;
	}
}, 1);

// Register the pretty /search/ rewrite so the redirect target resolves.
add_action('init', function() {
	if ( bp_seo_defer() ) return;
	add_rewrite_rule('^search/(.+)/?$', 'index.php?s=$matches[1]', 'top');
});

/*--------------------------------------------------------------
# §8. Archive disabling  (old cron: disable-author / disable-date / disable-attachment)
--------------------------------------------------------------*/
add_action('template_redirect', 'bp_seo_disable_archives');
function bp_seo_disable_archives() {
	if ( bp_seo_defer() || is_admin() ) return;

	// Attachment pages → parent post, else home.
	if ( is_attachment() ) {
		$parent = wp_get_post_parent_id( get_queried_object_id() );
		wp_safe_redirect( $parent ? get_permalink($parent) : home_url('/'), 301 );
		exit;
	}
	// Author + date archives → home.
	if ( is_author() || is_date() ) {
		wp_safe_redirect( home_url('/'), 301 );
		exit;
	}
}

/*--------------------------------------------------------------
# §9. stripcategorybase  (old cron: wpseo_titles['stripcategorybase'])
# Removes the /category/ base from category permalinks. Requires a rewrite flush,
# handled once on activation via the bp_seo_flushed option below.
--------------------------------------------------------------*/
function bp_seo_strip_category_base(): bool {
	return (bool) apply_filters('bp_seo_strip_category_base', true);
}

add_filter('category_link', function($link) {
	if ( bp_seo_defer() || ! bp_seo_strip_category_base() ) return $link;
	return preg_replace('#/category/#', '/', $link, 1);
}, 20);

add_filter('category_rewrite_rules', function($rules) {
	if ( bp_seo_defer() || ! bp_seo_strip_category_base() ) return $rules;
	$new  = [];
	$cats = get_categories([ 'hide_empty' => false ]);
	foreach ( $cats as $c ) {
		$slug = $c->slug;
		if ( $c->parent ) {
			$anc = get_category_parents($c->parent, false, '/', true);
			$slug = trim($anc, '/') . '/' . $slug;
		}
		$new[ '(' . preg_quote($slug, '#') . ')/?$' ]                                   = 'index.php?category_name=$matches[1]';
		$new[ '(' . preg_quote($slug, '#') . ')/page/?([0-9]{1,})/?$' ]                 = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		$new[ '(' . preg_quote($slug, '#') . ')/feed/(feed|rdf|rss|rss2|atom)/?$' ]     = 'index.php?category_name=$matches[1]&feed=$matches[2]';
	}
	return $new + $rules;
});

/*--------------------------------------------------------------
# §10. Sitemap  (old cron: enable_xml_sitemap)
# WP core ships /wp-sitemap.xml since 5.5 (Yoast merely disabled it). We keep it
# on and 301 the old Yoast URLs to it so Search Console keeps resolving.
--------------------------------------------------------------*/
add_filter('wp_sitemaps_enabled', function($on) {
	return bp_seo_defer() ? $on : true;
});

add_action('template_redirect', function() {
	if ( bp_seo_defer() ) return;
	$path = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
	// 301 the old Yoast sitemap URLs to WP core's, so Search Console + old links keep resolving:
	// the index (sitemap_index.xml / sitemap.xml) and any per-type "{type}-sitemap.xml"
	// (page-sitemap.xml, post-sitemap.xml, …) — but NOT core's own /wp-sitemap*.xml.
	$is_yoast_type = preg_match('#^/[a-z0-9_-]+-sitemap\.xml$#i', $path) && ! preg_match('#^/wp-sitemap#i', $path);
	if ( in_array( $path, ['/sitemap_index.xml', '/sitemap.xml'], true ) || $is_yoast_type ) {
		wp_safe_redirect( home_url('/wp-sitemap.xml'), 301 );
		exit;
	}
}, 0);

// Keep WP-core's sitemap in lockstep with our noindex/redirect rules. Yoast used to drop noindexed
// post types/taxonomies from its sitemap automatically; core doesn't know our rules, so without this
// it lists things we've noindexed or 301'd (jobsite raw records at /jobsites/, service-area/-type
// redirects, product/image taxonomies) → "Submitted URL marked noindex" errors in Search Console.
// Excluded = internal / aggregator / redirected types ONLY. Everything else stays in: pages, blog
// posts, products, galleries, landing, /service/ (jobsite_geo-services), and any custom CPT a site
// registers. Both lists are per-site filterable.
add_filter('wp_sitemaps_post_types', function($post_types) {
	if ( bp_seo_defer() ) return $post_types;
	$drop = apply_filters('bp_sitemap_exclude_post_types', [
		'elements',      // reusable page sections (header/widgets), never standalone
		'universal',     // universal templates, embedded via shortcode
		'jobsite_geo',   // raw job records (/jobsites/…) — 301'd to their /service/ page
		'testimonials',  // surfaced via sliders, not as standalone pages
		'products',      // shared HVAC brand partials → identical across the dealer fleet (duplicate content)
		'attachment',    // media attachment pages
	]);
	foreach ( (array) $drop as $pt ) unset( $post_types[$pt] );
	return $post_types;
});
add_filter('wp_sitemaps_taxonomies', function($taxonomies) {
	if ( bp_seo_defer() ) return $taxonomies;
	$drop = apply_filters('bp_sitemap_exclude_taxonomies', [
		'gallery-type', 'gallery-tags', 'image-categories', 'image-tags',
		'jobsite_geo-service-types', 'jobsite_geo-service-areas', 'jobsite_geo-techs',  // vague / 301'd to /service/
		'product-brand', 'product-type', 'product-class',
	]);
	foreach ( (array) $drop as $tax ) unset( $taxonomies[$tax] );
	return $taxonomies;   // keeps jobsite_geo-services (/service/), category, post_tag
});
// Drop the author/users sitemap (/wp-sitemap-users-*.xml). Author archives are noindexed on these
// (effectively single-operator) client sites, so listing them adds nothing.
add_filter('wp_sitemaps_add_provider', function($provider, $name) {
	return ( ! bp_seo_defer() && $name === 'users' ) ? false : $provider;
}, 10, 2);
// Removing that provider orphans the /wp-sitemap-users-*.xml route (WP falls through to a soft 200
// HTML page). Force a clean 404 so the stale Search Console entry dies like the excluded post/tax ones.
add_action('template_redirect', function() {
	if ( ! bp_seo_defer() && get_query_var('sitemap') === 'users' ) {
		global $wp_query;
		$wp_query->set_404();
		status_header(404);
		nocache_headers();
	}
}, 0);

/*--------------------------------------------------------------
# §11. Editor meta box  — per-post _bp_seo_* overrides
--------------------------------------------------------------*/
add_action('add_meta_boxes', function() {
	if ( bp_seo_defer() ) return;
	foreach ( bp_seo_metabox_types() as $pt ) {
		add_meta_box( 'bp-seo', 'Battle Plan SEO', 'bp_seo_metabox_render', $pt, 'normal', 'low' );
	}
});

// Pin the SEO box directly beneath "Page Bottom". Priority 'low' places it there by
// default; this also repositions it for users with a saved meta-box order, where a
// newly-added box would otherwise float to the top of the column.
add_action('current_screen', function( $screen ) {
	if ( ! $screen || $screen->base !== 'post' || bp_seo_defer() ) return;
	$pt = $screen->post_type;
	if ( ! in_array( $pt, bp_seo_metabox_types(), true ) ) return;

	add_filter( "get_user_option_meta-box-order_{$pt}", function( $order ) use ( $pt ) {
		if ( ! is_array($order) || empty($order['normal']) ) return $order; // no saved order → priority 'low' handles it
		$ids = array_values( array_diff( array_filter( array_map('trim', explode(',', $order['normal'])) ), ['bp-seo'] ) );
		$pos = array_search( "{$pt}-bottom", $ids, true );
		if ( $pos === false ) $ids[] = 'bp-seo';
		else array_splice( $ids, $pos + 1, 0, 'bp-seo' );
		$order['normal'] = implode( ',', $ids );
		return $order;
	} );
});

function bp_seo_metabox_render( $post ) {
	wp_nonce_field('bp_seo_save', 'bp_seo_nonce');
	$g = fn($k) => esc_attr( get_post_meta($post->ID, $k, true) );
	$sep  = bp_seo_sep();
	$tTpl = esc_attr( bp_seo_resolve_vars( bp_seo_title_template(), ['title' => get_the_title($post)] ) );
	?>
	<style>
		.bp-seo-field{margin:0 0 14px}
		.bp-seo-field label{display:block;font-weight:600;margin:0 0 4px}
		.bp-seo-field input[type=text],.bp-seo-field textarea{width:100%}
		.bp-seo-field .bp-seo-hint{color:#646970;font-weight:400;font-size:12px}
		.bp-seo-row{display:flex;gap:24px}
	</style>
	<div class="bp-seo-field">
		<label for="bp_seo_title">SEO Title <span class="bp-seo-hint">— blank uses: <?php echo $tTpl; ?></span></label>
		<input type="text" id="bp_seo_title" name="<?php echo BP_SEO_TITLE; ?>" value="<?php echo $g(BP_SEO_TITLE); ?>" placeholder="<?php echo $tTpl; ?>">
		<span class="bp-seo-hint">You can use %%title%%, %%sitename%%, %%sep%% (<?php echo esc_html($sep); ?>), %%primary_category%%.</span>
	</div>
	<div class="bp-seo-field">
		<label for="bp_seo_desc">Meta Description</label>
		<textarea id="bp_seo_desc" name="<?php echo BP_SEO_DESC; ?>" rows="3"><?php echo $g(BP_SEO_DESC); ?></textarea>
	</div>
	<div class="bp-seo-row">
		<div class="bp-seo-field" style="flex:1">
			<label for="bp_seo_canonical">Canonical URL <span class="bp-seo-hint">— blank = this page's permalink</span></label>
			<input type="text" id="bp_seo_canonical" name="<?php echo BP_SEO_CANONICAL; ?>" value="<?php echo $g(BP_SEO_CANONICAL); ?>">
		</div>
		<div class="bp-seo-field">
			<label>Robots</label>
			<label style="font-weight:400"><input type="checkbox" name="<?php echo BP_SEO_NOINDEX; ?>" value="1" <?php checked($g(BP_SEO_NOINDEX), '1'); ?>> noindex</label><br>
			<label style="font-weight:400"><input type="checkbox" name="<?php echo BP_SEO_NOFOLLOW; ?>" value="1" <?php checked($g(BP_SEO_NOFOLLOW), '1'); ?>> nofollow</label>
		</div>
	</div>
	<div class="bp-seo-row">
		<div class="bp-seo-field" style="flex:1">
			<label for="bp_seo_og_title">Social Title <span class="bp-seo-hint">— blank uses SEO title</span></label>
			<input type="text" id="bp_seo_og_title" name="<?php echo BP_SEO_OG_TITLE; ?>" value="<?php echo $g(BP_SEO_OG_TITLE); ?>">
		</div>
		<div class="bp-seo-field" style="flex:1">
			<label for="bp_seo_og_image">Social Image URL <span class="bp-seo-hint">— blank uses featured image</span></label>
			<input type="text" id="bp_seo_og_image" name="<?php echo BP_SEO_OG_IMAGE; ?>" value="<?php echo $g(BP_SEO_OG_IMAGE); ?>">
		</div>
	</div>
	<div class="bp-seo-field">
		<label for="bp_seo_og_desc">Social Description <span class="bp-seo-hint">— blank uses meta description</span></label>
		<textarea id="bp_seo_og_desc" name="<?php echo BP_SEO_OG_DESC; ?>" rows="2"><?php echo $g(BP_SEO_OG_DESC); ?></textarea>
	</div>
	<?php
}

add_action('save_post', 'bp_seo_metabox_save');
function bp_seo_metabox_save( $post_id ) {
	if ( ! isset($_POST['bp_seo_nonce']) || ! wp_verify_nonce($_POST['bp_seo_nonce'], 'bp_seo_save') ) return;
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( ! current_user_can('edit_post', $post_id) ) return;

	$text = [ BP_SEO_TITLE, BP_SEO_CANONICAL, BP_SEO_OG_TITLE, BP_SEO_OG_IMAGE ];
	foreach ( $text as $k ) {
		$v = isset($_POST[$k]) ? sanitize_text_field( wp_unslash($_POST[$k]) ) : '';
		$v === '' ? delete_post_meta($post_id, $k) : update_post_meta($post_id, $k, $v);
	}
	foreach ( [ BP_SEO_DESC, BP_SEO_OG_DESC ] as $k ) {
		$v = isset($_POST[$k]) ? sanitize_textarea_field( wp_unslash($_POST[$k]) ) : '';
		$v === '' ? delete_post_meta($post_id, $k) : update_post_meta($post_id, $k, $v);
	}
	foreach ( [ BP_SEO_NOINDEX, BP_SEO_NOFOLLOW ] as $k ) {
		empty($_POST[$k]) ? delete_post_meta($post_id, $k) : update_post_meta($post_id, $k, '1');
	}
}

/*--------------------------------------------------------------
# §12. Activation flush — rewrite rules for §7 /search/ + §9 category base.
# Runs once per code-version so category permalinks + search pretty-URLs resolve.
--------------------------------------------------------------*/
add_action('init', function() {
	if ( bp_seo_defer() ) return;
	$stamp = defined('_BP_VERSION') ? _BP_VERSION : '1';
	if ( get_option('bp_seo_flushed') !== $stamp ) {
		flush_rewrite_rules(false);
		update_option('bp_seo_flushed', $stamp, false);
	}
}, 99);

/*--------------------------------------------------------------
# §13. Phase-2 companions
--------------------------------------------------------------*/
// Redirects engine + admin (loaded everywhere — the template_redirect engine
// must run on the front end; the admin UI self-gates with is_admin()).
require_once get_template_directory() . '/includes/includes-seo-redirects.php';
// Yoast → _bp_seo_* importer. Loaded everywhere: the admin page self-gates via
// admin_menu, but the run-once housekeeping migration calls bp_seo_import_run()
// from the front-end cron, so the functions must be available outside admin too.
require_once get_template_directory() . '/includes/includes-seo-import.php';
