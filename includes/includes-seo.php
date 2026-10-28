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
		// Left ENCODED on purpose: this feeds <title>, which core echoes unescaped.
		// Consumers that escape for their own context (og:, JSON-LD) decode instead —
		// see bp_seo_head_meta() / bp_seo_schema_graph().
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

/**
 * Prepare a stored SEO value for output: resolve %%vars%%, then run content shortcodes
 * (so [get-biz …] etc. work in the SEO title/description — no hard-coded phone numbers),
 * strip any HTML they emit (titles + meta must be plain text; this reduces the tracked
 * phone link to just its number) and collapse whitespace.
 */
function bp_seo_render_value( string $raw, array $ctx = [] ): string {
	if ( $raw === '' ) return '';
	$out = bp_seo_resolve_vars( $raw, $ctx );
	if ( strpos( $out, '[' ) !== false ) $out = do_shortcode( $out );
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $out ) ) );
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
			return bp_seo_render_value( $override );
		}
	}

	$ctx = [];
	if ( ( is_category() || is_tag() || is_tax() ) && ( $term = get_queried_object() ) instanceof WP_Term ) {
		$ctx['title'] = $term->name;
		$tOverride    = get_term_meta( $term->term_id, BP_SEO_TITLE, true ); // imported term SEO title
		if ( $tOverride !== '' ) return bp_seo_render_value( $tOverride, $ctx );
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
	// Short-circuit, mirroring pre_get_document_title — lets a template that already
	// built a better description for the view (e.g. jobsite_geo /service/ archives)
	// supply it without having to store term meta.
	$pre = apply_filters('bp_seo_pre_description', '');
	if ( $pre !== '' ) return $pre;

	if ( is_singular() ) {
		$d = get_post_meta( get_queried_object_id(), BP_SEO_DESC, true );
		if ( $d !== '' ) return bp_seo_render_value( $d );
	}
	if ( ( is_category() || is_tag() || is_tax() ) && ( $t = get_queried_object() ) instanceof WP_Term ) {
		$td = get_term_meta( $t->term_id, BP_SEO_DESC, true );          // imported term SEO desc
		if ( $td !== '' )                 return bp_seo_render_value( $td, ['title' => $t->name] );
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
	// Tag archives are bare post listings with no unique content (thin/duplicate). Categories stay
	// indexed (/blog/). noindex,follow so crawlers still reach the posts. Sitemap drops post_tag too.
	if ( is_tag() )                              $noindex = true;

	if ( is_post_type_archive( ['testimonials', 'products', 'galleries', 'optimized'] ) ) $noindex = true;

	if ( is_tax( ['gallery-type', 'gallery-tags', 'image-categories', 'image-tags',
	              'jobsite_geo-service-types', 'jobsite_geo-service-areas', 'jobsite_geo-techs',
	              'product-brand', 'product-type', 'product-class'] ) ) $noindex = true;

	return (bool) apply_filters('bp_seo_noindex', $noindex, get_queried_object());
}

/**
 * /tech/ (jobsite_geo-techs) renders (unlike /service-area/ & /service-type/, which 301 to
 * /service/). Policy is that ONLY /service/ is a crawl surface, so nofollow the tech archives:
 * their links funnel to /jobsites/ singles that 301 to /service/, and /areas-we-serve/ already
 * gives crawlers a clean path to those /service/ pages. Everything else stays noindex,follow.
 */
add_filter('bp_seo_nofollow', function ($nofollow, $obj) {
	if ( is_tax('jobsite_geo-techs') ) return true;
	return $nofollow;
}, 10, 2);

/** Feed the robots meta via core's wp_robots() (WP 5.7+). */
add_filter('wp_robots', 'bp_seo_wp_robots', 20);
function bp_seo_wp_robots( array $robots ) {
	if ( bp_seo_defer() ) return $robots;

	if ( bp_seo_is_noindex() ) {
		$robots['noindex'] = true;
		unset($robots['index']);

		// noindex does NOT imply nofollow. Several noindexed views exist precisely to route
		// crawlers to content that SHOULD rank — /areas-we-serve/ is how Google discovers the
		// /service/{service--city-st}/ jobsite pages, and the noindexed jobsite_geo taxonomies
		// link onward to them too. Blanket-nofollowing every noindex view cut those paths.
		// Default to follow; opt a specific view back into nofollow via the filter.
		if ( apply_filters('bp_seo_nofollow', false, get_queried_object()) ) {
			$robots['nofollow'] = true;
			unset($robots['follow']);
		} else {
			$robots['follow'] = true;
			unset($robots['nofollow']);
		}
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

/*--------------------------------------------------------------
# §3b. Default meta for framework universal / utility pages
--------------------------------------------------------------*/
/**
 * Sensible SEO title + description defaults for the framework's shared universal pages
 * (FAQ, symptom-checker, maintenance-tips …). These pages are rendered from framework templates and
 * usually have NO editable SEO field, so without this they ship with a blank meta description and
 * Google auto-generates a poor snippet with no phone/city. Values are shortcode-driven ([get-biz],
 * %%sep%%) so they stay accurate per site, and a per-page BP_SEO_TITLE/BP_SEO_DESC override ALWAYS
 * wins. Keyed by the page's WP slug; filter bp_seo_universal_meta to add/adjust slugs per site.
 */
function bp_seo_universal_meta(): array {
	return apply_filters( 'bp_seo_universal_meta', array(
		'faq' => array(
			'title' => 'HVAC FAQ %%sep%% [get-biz info="name"] %%sep%% [get-biz info="city"], [get-biz info="state-abbr"]',
			'desc'  => 'Common HVAC questions answered for [get-biz info="city"], [get-biz info="state-abbr"] homeowners — repairs, replacements, maintenance & costs. Call [get-biz info="name"] at [get-biz info="area-phone"].',
		),
		'symptom-checker' => array(
			'title' => 'HVAC Symptom Checker %%sep%% [get-biz info="name"] %%sep%% [get-biz info="city"], [get-biz info="state-abbr"]',
			'desc'  => 'Heating or cooling acting up? Use the [get-biz info="name"] symptom checker to spot the issue, then call our [get-biz info="city"], [get-biz info="state-abbr"] team at [get-biz info="area-phone"].',
		),
		'maintenance-tips' => array(
			'title' => 'HVAC Maintenance Tips %%sep%% [get-biz info="name"]',
			'desc'  => 'HVAC maintenance tips from [get-biz info="name"] to keep your [get-biz info="city"], [get-biz info="state-abbr"] home comfortable and efficient year-round. Call [get-biz info="area-phone"].',
		),
	) );
}

/** Description fallback for a universal page — only when the page has no manual override. */
add_filter( 'bp_seo_pre_description', function ( $pre ) {
	if ( $pre !== '' || bp_seo_defer() || ! is_singular() ) return $pre;
	$id = get_queried_object_id();
	if ( get_post_meta( $id, BP_SEO_DESC, true ) !== '' ) return $pre;   // manual override wins
	$map  = bp_seo_universal_meta();
	$slug = get_post_field( 'post_name', $id );
	return isset( $map[ $slug ]['desc'] ) ? bp_seo_render_value( $map[ $slug ]['desc'] ) : $pre;
} );

/** Title fallback for a universal page — runs after bp_seo_document_title (20); override still wins. */
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( bp_seo_defer() || ! is_singular() ) return $title;
	$id = get_queried_object_id();
	if ( get_post_meta( $id, BP_SEO_TITLE, true ) !== '' ) return $title; // override already applied at 20
	$map  = bp_seo_universal_meta();
	$slug = get_post_field( 'post_name', $id );
	return isset( $map[ $slug ]['title'] ) ? bp_seo_render_value( $map[ $slug ]['title'] ) : $title;
}, 25 );

/** Form thank-you / utility slugs — noindexed below and dropped from the sitemap (§10). */
function bp_seo_noindex_slugs(): array {
	return array( 'email-received', 'message-received', 'thank-you', 'thanks' );
}

/** Keep form thank-you / utility pages out of the index (no snippet needed — they shouldn't rank). */
add_filter( 'bp_seo_noindex', function ( $noindex, $obj ) {
	if ( is_singular() ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		if ( in_array( $slug, bp_seo_noindex_slugs(), true ) ) return true;
	}
	return $noindex;
}, 10, 2 );

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

	// Values here all land inside attributes (esc_attr) or JSON, so they need to
	// arrive DECODED. get_bloginfo('name') is stored esc_html'd by WordPress, so a
	// company like "R & S" would otherwise reach the tag as "R &amp;amp; S" and
	// show up as "R &amp; S" in link previews. <title> is untouched on purpose —
	// core echoes it unescaped, so the pre-encoded name is already right there.
	$title = bp_decode_text( wp_get_document_title() );
	$desc  = bp_decode_text( bp_seo_description() );
	$canon = bp_seo_canonical();
	$img   = bp_seo_og_image();
	$ci    = customer_info();

	// Per-page OG title/description overrides fall back to the doc title / meta description.
	$ogTitle = $title;
	$ogDesc  = $desc;
	if ( is_singular() ) {
		$id = get_queried_object_id();
		if ( ( $v = get_post_meta($id, BP_SEO_OG_TITLE, true) ) !== '' ) $ogTitle = bp_decode_text( bp_seo_render_value($v) );
		if ( ( $v = get_post_meta($id, BP_SEO_OG_DESC,  true) ) !== '' ) $ogDesc  = bp_decode_text( bp_seo_render_value($v) );
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
	echo '<meta property="og:site_name" content="' . esc_attr( bp_business_name() ) . '">' . "\n";
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

/**
 * Org-node fields the stored schema blob doesn't carry, filled live so they land without
 * waiting on the GBP cron:
 *   logo / image  — the uploads-folder logo (bp_site_logo: logo.webp → .png → .jpg)
 *   foundingDate  — customer_info['founded'] (YYYY, YYYY-MM or YYYY-MM-DD)
 *   founder       — customer_info['founder'], a name or an array of names
 */
function bp_seo_org_extras( array $org, array $ci ): array {
	if ( empty($org['logo']) && function_exists('battleplan_fetch_site_logo') && ( $file = battleplan_fetch_site_logo() ) ) {
		$org['logo'] = [ '@type' => 'ImageObject', 'url' => content_url('/uploads/' . $file) ];
	}
	if ( empty($org['image']) ) {
		if ( ! empty($org['logo']['url']) )                         $org['image'] = $org['logo']['url'];
		elseif ( ! empty($org['logo']) && is_string($org['logo']) ) $org['image'] = $org['logo'];
	}

	// NOT customer_info['year'] — that's the year they became a Battle Plan customer.
	$founded = trim((string) ( $ci['founded'] ?? '' ));
	if ( empty($org['foundingDate']) && preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $founded) ) {
		$org['foundingDate'] = $founded;
	}

	if ( empty($org['founder']) && ! empty($ci['founder']) ) {
		$people = [];
		foreach ( (array) $ci['founder'] as $n ) {
			$n = bp_decode_text( trim((string) $n) );
			if ( $n !== '' ) $people[] = [ '@type' => 'Person', 'name' => $n ];
		}
		if ( $people ) $org['founder'] = count($people) === 1 ? $people[0] : $people;
	}

	return $org;
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
		// customer_info['schema'] is a STORED blob (built by the GBP cron), so a site whose
		// schema predates the type normaliser still carries a raw business-type label as
		// @type. Coerce on the way out so the fix lands immediately, not on the next cron.
		if ( isset($org['@type']) && function_exists('bp_schema_normalize_type') ) {
			$org['@type'] = bp_schema_normalize_type($org['@type']);
		}
		// Same reasoning as @type above: an install whose stored blob was built from the
		// esc_html'd blogname carries "R &amp; S" as its name. Decode on the way out.
		if ( isset($org['name']) ) $org['name'] = bp_decode_text( $org['name'] );
		$org = bp_seo_org_extras($org, $ci);
		$org['@id'] = home_url('#organization');
		$org = bp_seo_narrow_area_service($org);
		$org = bp_seo_narrow_area_jobsite($org);
	}

	$site = [
		'@type' => 'WebSite',
		'@id'   => home_url('#website'),
		'url'   => home_url('/'),
		'name'  => bp_business_name(),
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
		'name'     => bp_decode_text( wp_get_document_title() ),
		'isPartOf' => [ '@id' => home_url('#website') ],
		'about'    => [ '@id' => home_url('#organization') ],
	];
	if ( ( $d = bp_decode_text( bp_seo_description() ) ) !== '' ) $page['description'] = $d;

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
	// NEVER filter 'emoji_svg_url' to false. If anything still prints the detection
	// script (block editor, a plugin, a core hook we didn't catch), the JS builds
	// src = svgUrl + codepoint + '.svg' → "false2b50.svg", which 404s — so every
	// emoji renders as a BROKEN IMAGE followed by its alt text (the original glyph).
	// That was showing up as broken images in front of the ⭐ rating column and the
	// ✨ AI-alt button. Filtering it also broke battleplan_disable_emojis_remove_dns_prefetch(),
	// which resolves the same filter to find the URL it's supposed to strip.
	// Remove the hooks instead of poisoning the URL.
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('embed_head', 'print_emoji_detection_script');
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'print_emoji_styles');
	remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
	remove_action('admin_print_styles', 'wp_enqueue_emoji_styles');
	remove_filter('the_content_feed', 'wp_staticize_emoji');
	remove_filter('comment_text_rss', 'wp_staticize_emoji');
	remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
	add_filter('wp_resource_hints', 'bp_seo_drop_emoji_prefetch', 10, 2);
}

/**
 * Drop the s.w.org DNS-prefetch hint left behind once the emoji script is gone.
 * Resolves the URL through the same filter core does, so it keeps matching if core
 * bumps the emoji version — which is exactly why 'emoji_svg_url' must stay truthy.
 */
function bp_seo_drop_emoji_prefetch( $urls, $relation_type ) {
	if ( 'dns-prefetch' === $relation_type ) {
		/** This filter is documented in wp-includes/formatting.php */
		$svg = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
		$urls = array_diff( $urls, array( $svg, 'https://s.w.org' ) );
	}
	return $urls;
}

/**
 * Belt-and-braces: re-run the admin removal late, after every plugin has had its
 * chance to (re-)register on 'init'. 'admin_print_scripts' fires well after this,
 * so anything that re-added the detection script still gets stripped.
 */
add_action('admin_init', function() {
	if ( bp_seo_defer() ) return;
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('admin_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'wp_enqueue_emoji_styles');
}, 999);

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
	// Core root-level entry points. None of these render a page, but Google probes them on every
	// WP site and files the results in Search Console as 404/4xx errors. Listed one-by-one rather
	// than as "Disallow: /wp-*.php" because robots.txt wildcards cross slashes — that pattern
	// would also swallow /wp-content/**/*.php and undo the render-blocking note below.
	$lines .= "Disallow: /wp-login.php\n";
	$lines .= "Disallow: /wp-signup.php\n";
	$lines .= "Disallow: /wp-activate.php\n";
	$lines .= "Disallow: /wp-trackback.php\n";
	$lines .= "Disallow: /wp-comments-post.php\n";
	$lines .= "Disallow: /wp-links-opml.php\n";
	$lines .= "Disallow: /wp-mail.php\n";
	$lines .= "Disallow: /wp-cron.php\n";
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
		'post_tag',      // tag archives are noindexed (§3) — thin post listings
	]);
	foreach ( (array) $drop as $tax ) unset( $taxonomies[$tax] );
	return $taxonomies;   // keeps jobsite_geo-services (/service/), category
});
// Per-post noindex: drop anything ticked "noindex" in the SEO box, plus the thank-you/utility slugs.
// Core builds both the URL list and the page count from these args, so the index stays consistent.
// (A per-site bp_seo_noindex filter on singulars can't be seen here — pair it with this filter.)
add_filter('wp_sitemaps_posts_query_args', function($args, $post_type) {
	if ( bp_seo_defer() ) return $args;
	$not_noindexed = [
		'relation' => 'OR',
		[ 'key' => BP_SEO_NOINDEX, 'compare' => 'NOT EXISTS' ],
		[ 'key' => BP_SEO_NOINDEX, 'value' => '1', 'compare' => '!=' ],
	];
	$args['meta_query'] = empty($args['meta_query'])
		? $not_noindexed
		: [ 'relation' => 'AND', $args['meta_query'], $not_noindexed ];
	// WP_Query has post_name__in but no post_name__not_in — resolve the slugs to ids.
	$utility = get_posts([
		'post_type'      => $post_type,
		'post_name__in'  => bp_seo_noindex_slugs(),
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	]);
	if ( $utility ) $args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? [] ), $utility );
	return $args;
}, 10, 2);
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
		if ( ! is_array($order) ) return $order;   // no saved order at all → priority 'low' already lands it last

		// Strip bp-seo out of EVERY column first. do_meta_boxes() re-adds each id named in the saved
		// order with that column's context, and add_meta_box() then MOVES the box out of wherever it
		// was registered - so one stale 'bp-seo' left in side/advanced silently outranks anything we
		// do to 'normal'. Only looking at $order['normal'] was the bug: the box floated back up.
		$cols = [];
		foreach ( $order as $ctx => $ids ) {
			$cols[$ctx] = array_values( array_diff( array_filter( array_map('trim', explode(',', (string) $ids)) ), ['bp-seo'] ) );
		}

		// Re-insert directly after Page Bottom, in whichever column the user actually dragged it to.
		foreach ( $cols as $ctx => $ids ) {
			$pos = array_search( "{$pt}-bottom", $ids, true );
			if ( $pos === false ) continue;
			array_splice( $ids, $pos + 1, 0, 'bp-seo' );
			$cols[$ctx] = $ids;
			break;
		}

		// If Page Bottom appears in no column it's still on its registered 'high' priority, and bp-seo
		// is now absent from the saved order entirely - which drops it back to its own 'low' bucket.
		// do_meta_boxes() walks high → sorted → core → default → low, so that is last either way.
		foreach ( $cols as $ctx => $ids ) $order[$ctx] = implode( ',', $ids );
		return $order;
	} );
});

function bp_seo_metabox_render( $post ) {
	wp_nonce_field('bp_seo_save', 'bp_seo_nonce');
	$g = fn($k) => esc_attr( get_post_meta($post->ID, $k, true) );
	$sep  = bp_seo_sep();
	$tTpl = esc_attr( bp_seo_resolve_vars( bp_seo_title_template(), ['title' => get_the_title($post)] ) );
	$ai   = bp_seo_ai_available();
	?>
	<style>
		.bp-seo-field{margin:0 0 14px}
		.bp-seo-field label{display:block;font-weight:600;margin:0 0 4px}
		.bp-seo-field input[type=text],.bp-seo-field textarea{width:100%}
		.bp-seo-field .bp-seo-hint{color:#646970;font-weight:400;font-size:12px}
		.bp-seo-row{display:flex;gap:24px}
		.bp-seo-tokens{display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin:5px 0 0}
		.bp-seo-tokens .bp-seo-hint{margin-right:2px}
		.bp-seo-tok{cursor:pointer;font-size:11px;line-height:1.6;font-family:Consolas,Monaco,monospace;color:#2271b1;background:#f0f6fc;border:1px solid #c3d9ed;border-radius:3px;padding:1px 6px}
		.bp-seo-tok:hover{background:#2271b1;border-color:#2271b1;color:#fff}
		.bp-seo-head{display:flex;align-items:center;gap:12px;margin:0 0 4px}
		.bp-seo-head label{flex:1;margin:0}
		.bp-seo-count{color:#646970;font-size:12px;white-space:nowrap}
		.bp-seo-count.is-over{color:#d63638;font-weight:600}
		.bp-seo-ai{cursor:pointer;color:#9b59b6;background:none;border:none;padding:0;font-size:12px;white-space:nowrap}
		.bp-seo-ai:hover{text-decoration:underline}
		.bp-seo-ai:disabled{cursor:wait;color:#646970;text-decoration:none}
		.bp-seo-ai-note{color:#646970;font-size:12px;margin:5px 0 0}
		.bp-seo-ai-note:empty{display:none}
		.bp-seo-ai-note.is-warn{color:#d63638}
	</style>
	<div class="bp-seo-field">
		<div class="bp-seo-head">
			<label for="bp_seo_title">SEO Title <span class="bp-seo-hint">— blank uses: <?php echo $tTpl; ?></span></label>
			<span class="bp-seo-count" data-for="bp_seo_title" data-max="<?php echo bp_seo_ai_limits('title')['max']; ?>"></span>
			<?php if ( $ai ) : ?><button type="button" class="bp-seo-ai" data-field="title" data-target="bp_seo_title" data-other="bp_seo_desc" title="Read this page and write an SEO title">✨ Write with AI</button><?php endif; ?>
		</div>
		<input type="text" id="bp_seo_title" name="<?php echo BP_SEO_TITLE; ?>" value="<?php echo $g(BP_SEO_TITLE); ?>" placeholder="<?php echo $tTpl; ?>">
		<div class="bp-seo-ai-note" id="bp_seo_title_note"></div>
		<div class="bp-seo-ai-note is-warn" id="bp_seo_title_warn"></div>
		<?php bp_seo_token_bar('bp_seo_title', $sep); ?>
	</div>
	<div class="bp-seo-field">
		<div class="bp-seo-head">
			<label for="bp_seo_desc">Meta Description</label>
			<span class="bp-seo-count" data-for="bp_seo_desc" data-max="<?php echo bp_seo_ai_limits('desc')['max']; ?>"></span>
			<?php if ( $ai ) : ?><button type="button" class="bp-seo-ai" data-field="desc" data-target="bp_seo_desc" data-other="bp_seo_title" title="Read this page and write a meta description">✨ Write with AI</button><?php endif; ?>
		</div>
		<textarea id="bp_seo_desc" name="<?php echo BP_SEO_DESC; ?>" rows="3"><?php echo $g(BP_SEO_DESC); ?></textarea>
		<div class="bp-seo-ai-note" id="bp_seo_desc_note"></div>
		<div class="bp-seo-ai-note is-warn" id="bp_seo_desc_warn"></div>
		<?php bp_seo_token_bar('bp_seo_desc', $sep); ?>
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
	<?php bp_seo_token_script(); ?>
	<?php bp_seo_ai_script( $post ); ?>
	<?php
}

// The click-to-insert variables/shortcodes (token => chip note). Also the vocabulary the AI writer may use.
function bp_seo_tokens(): array {
	return [
		'%%title%%'            => '',
		'%%sitename%%'         => '',
		'%%sep%%'              => ' (' . bp_seo_sep() . ')',
		'%%primary_category%%' => '',
		'[get-biz info="name"]'       => '',
		'[get-biz info="city"]'       => '',
		'[get-biz info="state-abbr"]' => '',
		'[get-biz info="area-phone"]' => '',
		'[get-years start="xxxx"]'    => '',
	];
}

// Clickable variable/shortcode chips that insert into $target at the cursor.
function bp_seo_token_bar( $target, $sep ) {
	echo '<div class="bp-seo-tokens"><span class="bp-seo-hint">Click to insert:</span>';
	foreach ( bp_seo_tokens() as $tok => $note ) {
		printf(
			'<button type="button" class="bp-seo-tok" data-target="%s" data-token="%s">%s</button>',
			esc_attr($target), esc_attr($tok), esc_html($tok . $note)
		);
	}
	echo '</div>';
}

function bp_seo_token_script() {
	static $done = false;
	if ( $done ) return;
	$done = true;
	?>
	<script>
	document.addEventListener('click', function(e){
		var b = e.target.closest ? e.target.closest('.bp-seo-tok') : null;
		if ( ! b ) return;
		e.preventDefault();
		var f = document.getElementById(b.dataset.target), t = b.dataset.token;
		if ( ! f ) return;
		var s = f.selectionStart, n = f.selectionEnd;
		if ( s === null || typeof s === 'undefined' ) { s = n = f.value.length; }
		f.value = f.value.slice(0, s) + t + f.value.slice(n);
		var p = t.indexOf('xxxx'); // drop the cursor on the year placeholder, ready to overtype
		f.focus();
		if ( p > -1 ) f.setSelectionRange(s + p, s + p + 4);
		else          f.setSelectionRange(s + t.length, s + t.length);
		f.dispatchEvent(new Event('input', {bubbles:true}));
	});
	</script>
	<?php
}

/*--------------------------------------------------------------
# §11b. AI writer — "✨ Write with AI" on SEO Title + Meta Description
# Reads the page (title, editor body, Page Top/Bottom) and asks Claude for a
# value built from the click-to-insert tokens. The result is measured RENDERED
# (tokens resolved, shortcodes run — what Google actually sees) and sent back
# for a rewrite if it runs long or types out a value a token already provides.
# Only fills the field; nothing is saved until the post is updated.
# Uses the same API key as AI alt text (functions-ai-alt.php).
--------------------------------------------------------------*/
if ( ! defined('BP_SEO_AI_MODEL') ) define('BP_SEO_AI_MODEL', 'claude-sonnet-4-6');

/**
 * Character budget per field. Google truncates by pixel width (~580px title,
 * ~920px description); these counts keep typical copy inside that.
 */
function bp_seo_ai_limits( string $field ): array {
	return $field === 'title' ? ['min' => 45, 'max' => 60] : ['min' => 120, 'max' => 155];
}

function bp_seo_ai_available(): bool {
	return function_exists('bp_ai_alt_available') && bp_ai_alt_available();
}

/**
 * Render context for a post outside the front-end query (editor / AJAX), where
 * %%title%% and %%primary_category%% can't come from get_queried_object().
 */
function bp_seo_post_ctx( WP_Post $post, string $title = '' ): array {
	$pc  = (int) get_post_meta($post->ID, BP_SEO_PRIMARYCAT, true);
	$cat = $pc ? get_cat_name($pc) : '';
	if ( $cat === '' && ( $cats = get_the_category($post->ID) ) ) $cat = $cats[0]->name;
	return [ 'title' => $title !== '' ? $title : get_the_title($post), 'primary_cat' => $cat ];
}

/** A stored SEO value as plain text the way Google sees it, plus its length. */
function bp_seo_plain( string $raw, array $ctx ): array {
	$out = html_entity_decode( bp_seo_render_value($raw, $ctx), ENT_QUOTES, 'UTF-8' );
	return [ $out, mb_strlen($out) ];
}

/**
 * Each token → what it renders as on this post. Feeds the AI (so it can budget
 * characters) and the live counters. [get-years] is left out on purpose: its start
 * year must come from the operator, never be guessed (docs/shortcodes-content.md).
 */
function bp_seo_token_values( array $ctx ): array {
	$vals = [];
	foreach ( array_keys( bp_seo_tokens() ) as $tok ) {
		if ( strpos($tok, '[get-years') === 0 ) continue;
		// A lone %%sep%% resolves to '' (the resolver trims edge separators), so read it directly.
		$vals[$tok] = $tok === '%%sep%%' ? bp_seo_sep() : bp_seo_plain($tok, $ctx)[0];
	}
	return $vals;
}

/** The page's readable copy: editor body (unsaved edits when passed in) + Page Top/Bottom. */
function bp_seo_page_text( WP_Post $post, string $body = '' ): string {
	$text = implode( "\n\n", [
		$body !== '' ? $body : $post->post_content,
		(string) get_post_meta($post->ID, 'page-top_text', true),
		(string) get_post_meta($post->ID, 'page-bottom_text', true),
	] );
	// Resolve business-fact shortcodes so their words survive; drop every other shortcode
	// TAG but keep what it wraps ([txt]…[/txt] holds the copy).
	$text = preg_replace_callback( '/\[get-biz\b[^\]]*\]/', fn($m) => do_shortcode($m[0]), $text );
	$text = preg_replace( '/\[\/?[a-z][^\]]*\]/i', ' ', $text );
	$text = html_entity_decode( wp_strip_all_tags( str_replace('<', ' <', $text) ), ENT_QUOTES, 'UTF-8' );
	return mb_substr( trim( preg_replace('/\s+/', ' ', $text) ), 0, 12000 );
}

function bp_seo_ai_prompt( string $field, WP_Post $post, array $ctx, array $vals, string $text, string $other ): string {
	$ci   = function_exists('customer_info') ? (array) customer_info() : [];
	$lim  = bp_seo_ai_limits($field);
	$type = $ci['site-type'] ?? ( $ci['business-type'] ?? '' );
	$svc  = $ci['service-type'] ?? '';
	$svc  = is_array($svc) ? implode(', ', $svc) : (string) $svc;

	$L = [];
	$L[] = 'You are writing the ' . ( $field === 'title' ? 'SEO title (the <title> tag)' : 'meta description' ) . ' for one page of a local business website.';
	$L[] = '';
	$L[] = 'Business: ' . bp_business_name() . ( $type ? " (a {$type} business)" : '' );
	if ( $svc ) $L[] = "Services: {$svc}";
	$L[] = '';
	$L[] = 'Your output is stored as a TEMPLATE. These tokens render at page load, so the text stays correct if the business details ever change:';
	foreach ( $vals as $tok => $val ) {
		if ( $val === '' ) continue;
		$L[] = "  {$tok}  → \"{$val}\" (" . mb_strlen($val) . ' chars)';
	}
	$L[] = '';
	// No em dashes anywhere in this prompt: models mirror the punctuation they're shown.
	$L[] = 'Token rules:';
	$L[] = '- Never type out a value a token provides (business name, city, state, phone). Use the token.';
	if ( $field === 'title' ) {
		$L[] = '- Separate title segments with %%sep%% (surrounded by spaces). Never type "|", "-" or "•" as a separator.';
	} else {
		$L[] = '- Do NOT use %%sep%% in a description. It is written in sentences, separated by periods.';
	}
	$L[] = '- Do NOT use [get-years] or state a number of years in business.';
	$L[] = '- Length is measured on the RENDERED text (every token replaced by its value above).';
	$L[] = '';
	$L[] = 'Requirements:';
	$L[] = "- {$lim['min']} to {$lim['max']} characters rendered. {$lim['max']} is a hard maximum.";
	$L[] = '- NEVER use em dashes or en dashes. Use a period or a comma instead.';
	if ( $field === 'title' ) {
		$L[] = '- Lead with the page\'s main service or topic: the phrase a searcher would type.';
		$L[] = '- Add the location (city, state) when the page is about local services and it fits.';
		$L[] = '- End with " %%sep%% %%sitename%%" when it fits. Drop that before dropping the keyword or location.';
		$L[] = '- Use %%title%% only if the page title is already the right keyword phrase.';
		$L[] = '- Title Case. No ALL CAPS, exclamation marks, keyword stuffing or clickbait.';
	} else {
		$L[] = '- One or two complete sentences, each ending with a period: what this page offers, where, why choose this business, and a call to action.';
		$L[] = '- Work in the main service and the location naturally. [get-biz info="area-phone"] may go in the call to action if it fits.';
		$L[] = '- Active voice. No keyword stuffing, no quotes, and don\'t open with the business name.';
	}
	$L[] = '- Only claim what the page content supports. No invented prices, guarantees, awards, offers or credentials.';
	$L[] = '';
	$L[] = 'Page title: ' . html_entity_decode( $ctx['title'], ENT_QUOTES, 'UTF-8' );
	$L[] = 'Post type: ' . $post->post_type;
	if ( $post->post_name ) $L[] = 'URL slug: ' . $post->post_name;
	if ( $other !== '' ) $L[] = ( $field === 'title' ? 'Current meta description' : 'Current SEO title' ) . " (keep consistent, don't repeat it word for word): {$other}";
	$L[] = '';
	$L[] = 'Page content:';
	$L[] = $text !== '' ? $text : '(empty, so work from the page title and business details)';
	$L[] = '';
	$L[] = 'Output ONLY the ' . ( $field === 'title' ? 'title' : 'description' ) . ' on one line. No quotes, no label, no explanation.';
	return implode("\n", $L);
}

/** What's wrong with a draft, phrased as revision instructions. Empty = good. */
function bp_seo_ai_problems( string $field, string $raw, array $ctx, array $vals ): array {
	$lim = bp_seo_ai_limits($field);
	$len = bp_seo_plain($raw, $ctx)[1];
	$p   = [];
	if ( $len > $lim['max'] )     $p[] = "It renders to {$len} characters; the maximum is {$lim['max']}.";
	elseif ( $len < $lim['min'] ) $p[] = "It renders to only {$len} characters; aim for {$lim['min']}–{$lim['max']}.";

	// Hard-coded facts: look only at the text OUTSIDE tokens.
	$bare = preg_replace( '/%%[a-z_]+%%|\[[^\]]*\]/i', ' ', $raw );
	foreach ( ['%%sitename%%', '[get-biz info="name"]', '[get-biz info="city"]'] as $tok ) {
		$v = $vals[$tok] ?? '';
		if ( mb_strlen($v) > 2 && stripos($bare, $v) !== false ) $p[] = "It types \"{$v}\" — use {$tok} instead.";
	}
	$digits = preg_replace( '/\D/', '', $vals['[get-biz info="area-phone"]'] ?? '' );
	if ( strlen($digits) >= 7 && strpos( preg_replace('/\D/', '', $bare), $digits ) !== false ) {
		$p[] = 'It types the phone number — use [get-biz info="area-phone"] instead.';
	}
	return array_values( array_unique($p) );
}

/**
 * Strip labels/quotes the model sometimes adds, swap any literal pipe for %%sep%%, and
 * filter out dashes that sneak in despite the prompt.
 */
function bp_seo_ai_clean( string $text, string $field ): string {
	$lines = array_values( array_filter( array_map('trim', explode("\n", $text)) ) );
	$text  = $lines[0] ?? '';
	$text  = preg_replace( '/^(seo title|title|meta description|description)\s*:\s*/i', '', $text );
	$text  = trim( $text, "\"'“”‘’ \t" );
	$text  = preg_replace( '/\s+\|\s+/', ' %%sep%% ', $text );
	$text  = bp_seo_ai_dedash( $text, $field );
	return trim( preg_replace('/\s+/', ' ', $text) );
}

/**
 * Replace em/en dashes (and a spaced " - " used as one) with a period or comma. In a
 * description, %%sep%% is treated the same way, since descriptions are sentences.
 * Title: always a comma. Description: a period when a new sentence follows (capital
 * letter), otherwise a comma. Hyphenated words ("same-day") are left alone.
 */
function bp_seo_ai_dedash( string $text, string $field ): string {
	$desc = $field === 'desc';
	$re   = '/([.,!?;:]?)(?:\s*[—–]\s*|\s+-\s+' . ( $desc ? '|\s*%%sep%%\s*' : '' ) . ')(?=(\S?))/u';
	return preg_replace_callback( $re, function( $m ) use ( $desc ) {
		if ( $m[2] === '' ) return '';                    // trailing dash: just drop it
		if ( $m[1] !== '' ) return $m[1] . ' ';           // already punctuated: keep that mark
		if ( ! $desc ) return ', ';
		return preg_match('/\p{Lu}/u', $m[2]) ? '. ' : ', ';
	}, $text );
}

function bp_seo_ai_request( array $messages ) {
	$response = wp_remote_post('https://api.anthropic.com/v1/messages', [
		'timeout' => 25,
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => bp_ai_alt_api_key(),
			'anthropic-version' => '2023-06-01',
		],
		'body' => wp_json_encode([
			'model'      => BP_SEO_AI_MODEL,
			'max_tokens' => 300,
			'messages'   => $messages,
		]),
	]);
	if ( is_wp_error($response) ) return $response;

	$status  = wp_remote_retrieve_response_code($response);
	$decoded = json_decode( wp_remote_retrieve_body($response), true );
	if ( $status !== 200 ) {
		$msg = $decoded['error']['message'] ?? 'Unknown API error.';
		if ( function_exists('bp_ai_model_alert') ) bp_ai_model_alert( (int) $status, $decoded, BP_SEO_AI_MODEL, 'AI SEO writer' );
		return new WP_Error('api_error', "Anthropic API error ($status): $msg");
	}
	$text = trim( (string) ( $decoded['content'][0]['text'] ?? '' ) );
	return $text !== '' ? $text : new WP_Error('empty', 'Empty response from API.');
}

/**
 * Write an SEO title ('title') or meta description ('desc') for $post.
 * Up to three drafts: each one that runs long / short or hard-codes a fact is sent back
 * with the specific problem. Stops early near WP Engine's 60s request limit and returns
 * the best draft, with a warning if it still isn't clean.
 * Returns [value, preview, length, max, warning] or a WP_Error.
 */
function bp_seo_ai_write( string $field, WP_Post $post, string $title = '', string $body = '', string $other = '' ) {
	if ( ! bp_seo_ai_available() ) return new WP_Error('no_api_key', 'No Anthropic API key configured.');

	$ctx      = bp_seo_post_ctx($post, $title);
	$vals     = bp_seo_token_values($ctx);
	$messages = [[ 'role' => 'user', 'content' => bp_seo_ai_prompt( $field, $post, $ctx, $vals, bp_seo_page_text($post, $body), $other ) ]];
	$start    = time();
	$best     = null;

	for ( $try = 0; $try < 3; $try++ ) {
		$reply = bp_seo_ai_request($messages);
		if ( is_wp_error($reply) ) {
			if ( $best ) break;
			return $reply;
		}
		$raw      = bp_seo_ai_clean($reply, $field);
		$problems = bp_seo_ai_problems($field, $raw, $ctx, $vals);
		[ $plain, $len ] = bp_seo_plain($raw, $ctx);
		$score    = count($problems) * 1000 + max(0, $len - bp_seo_ai_limits($field)['max']);
		if ( ! $best || $score < $best['score'] ) $best = compact('raw', 'plain', 'len', 'problems', 'score');

		if ( ! $problems || time() - $start > 35 ) break;
		$messages[] = [ 'role' => 'assistant', 'content' => $raw ];
		$messages[] = [ 'role' => 'user', 'content' => "Revise it:\n- " . implode("\n- ", $problems) . "\nOutput ONLY the revised line." ];
	}

	return [
		'value'   => $best['raw'],
		'preview' => $best['plain'],
		'length'  => $best['len'],
		'max'     => bp_seo_ai_limits($field)['max'],
		'warning' => implode(' ', $best['problems']),
	];
}

add_action('wp_ajax_bp_seo_ai_generate', function() {
	check_ajax_referer('bp_seo_ai', 'nonce');

	$id   = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	$post = $id ? get_post($id) : null;
	if ( ! $post || ! current_user_can('edit_post', $id) ) {
		wp_send_json_error(['message' => 'Not authorized.'], 403);
	}

	$field = ( $_POST['field'] ?? '' ) === 'desc' ? 'desc' : 'title';
	$out   = bp_seo_ai_write(
		$field,
		$post,
		sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
		(string) wp_unslash( $_POST['content'] ?? '' ),   // editor HTML — only ever stripped to text for the prompt
		sanitize_textarea_field( wp_unslash( $_POST['other'] ?? '' ) )
	);
	if ( is_wp_error($out) ) wp_send_json_error(['message' => $out->get_error_message()], 500);
	wp_send_json_success($out);
});

/**
 * Live "52 / 60" counters (rendered length — tokens swapped for this post's values)
 * and the ✨ button handler. The counters run even without an API key.
 */
function bp_seo_ai_script( $post ) {
	$vals = bp_seo_token_values( bp_seo_post_ctx($post) );
	?>
	<script>
	(function(){
		var VALS = <?php echo wp_json_encode($vals); ?>, NONCE = '<?php echo esc_js( wp_create_nonce('bp_seo_ai') ); ?>', POST_ID = <?php echo (int) $post->ID; ?>;

		function rendered(f){
			var s = f.value || f.placeholder || '', t = document.getElementById('title');
			Object.keys(VALS).forEach(function(k){
				var v = ( k === '%%title%%' && t && t.value ) ? t.value : VALS[k];
				s = s.split(k).join(v);
			});
			return s.replace(/%%[a-z_]+%%/g, '').replace(/\s+/g, ' ').trim();
		}
		function count(){
			document.querySelectorAll('.bp-seo-count').forEach(function(c){
				var f = document.getElementById(c.dataset.for), max = +c.dataset.max;
				if ( ! f ) return;
				var text = rendered(f), n = Array.from(text).length;
				c.textContent = n + ' / ' + max;
				c.title = 'Characters as Google sees it (tokens filled in)';
				c.classList.toggle('is-over', n > max);

				// Live "Google sees:" line under the field (hidden via :empty when there's nothing to show).
				var note = document.getElementById(c.dataset.for + '_note');
				if ( ! note ) return;
				note.textContent = '';
				if ( text ) {
					var strong = document.createElement('b');
					strong.textContent = text;
					note.append('Google sees: ', strong);
				}
			});
		}
		document.addEventListener('input', function(e){
			if ( e.target.id === 'bp_seo_title' || e.target.id === 'bp_seo_desc' || e.target.id === 'title' ) count();
			// An AI warning is about that draft — clear it once the field is hand-edited.
			if ( e.isTrusted && ( e.target.id === 'bp_seo_title' || e.target.id === 'bp_seo_desc' ) ) {
				var w = document.getElementById(e.target.id + '_warn');
				if ( w ) w.textContent = '';
			}
		});
		count();

		function editorBody(){
			var ed = window.tinymce && tinymce.get('content');
			if ( ed && ! ed.isHidden() ) return ed.getContent();
			var ta = document.getElementById('content');
			return ta ? ta.value : '';
		}

		document.addEventListener('click', function(e){
			var b = e.target.closest ? e.target.closest('.bp-seo-ai') : null;
			if ( ! b || b.disabled ) return;
			e.preventDefault();
			var f = document.getElementById(b.dataset.target), o = document.getElementById(b.dataset.other),
				warn = document.getElementById(b.dataset.target + '_warn'), t = document.getElementById('title');

			var form = new FormData();
			form.append('action', 'bp_seo_ai_generate');
			form.append('nonce', NONCE);
			form.append('post_id', POST_ID);
			form.append('field', b.dataset.field);
			form.append('title', t ? t.value : '');
			form.append('content', editorBody());
			form.append('other', o ? o.value : '');

			var label = b.textContent;
			b.textContent = '⏳ Writing…';
			b.disabled = true;
			fetch(ajaxurl, { method: 'POST', body: form, credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(json){
					if ( ! json.success ) throw new Error(json.data && json.data.message ? json.data.message : 'Generation failed');
					f.value = json.data.value;
					f.dispatchEvent(new Event('input', { bubbles: true }));   // refreshes counter + "Google sees"
					warn.textContent = json.data.warning ? '⚠ ' + json.data.warning + ' Edit before saving.' : '';
					note.classList.toggle('is-warn', !! json.data.warning);
				})
				.catch(function(err){ alert('AI SEO writer failed: ' + err.message); })
				.finally(function(){ b.textContent = label; b.disabled = false; });
		});
	})();
	</script>
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
// The Yoast → _bp_seo_* importer (includes-seo-import.php, Tools → Import Yoast SEO)
// was removed once the fleet had migrated. Yoast REDIRECT import is a separate thing
// and still lives in includes-seo-redirects.php as bp_seo_import_yoast_redirects().
