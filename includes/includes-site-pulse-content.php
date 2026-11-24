<?php
/* Battle Plan Web Design - Site Pulse Website Content
   Add, edit and delete the website's pages, posts and (per site) other content types from Site Pulse,
   so clients never need wp-admin for everyday edits. wp-admin keeps working exactly as before.

   CONTENT TYPES: posts + pages by default. A site adds more in its functions-site.php:
       add_filter( 'sp_content_types', function ( $types ) {
           $types['testimonials'] = true;          // label from the post type
           $types['artists']      = 'Artists';     // or your own label
           return $types;
       } );

   PERMISSIONS: five levels per content type, generated like the per-report caps, so they show in the
   Roles grid under "Website Content" and can be granted per role or per person:
       content_{type}_write      write new drafts, edit their own drafts, submit them for review
       content_{type}_publish    publish (and edit) their own
       content_{type}_edit_all   edit, publish and approve everyone's
       content_{type}_delete_own delete their own
       content_{type}_delete_all delete everyone's
   Someone who can write but not publish gets "Submit for review"; the people who can edit everyone's
   are notified, and the writer is told when it goes live.

   LAYOUT PAGES: framework pages are built from [section]/[layout]/[col]/[txt] shortcodes. Only the
   inside of each [txt] block is editable; everything else is kept exactly as written. Shortcodes inside
   a text block ([get-biz], [btn] ...) travel as locked chips that carry only an index back to the
   original, so they can be moved or deleted but never broken. A block is rewritten only if it changed,
   and a save is refused if the page changed on the server since it was opened. Page Top / Page Bottom
   (post meta page-top_text / page-bottom_text) are edited the same way when a page has them.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Content Types & Permissions
# Layout Text Blocks (split / chips / rebuild)
# Reading
# AJAX — List / Get
# AJAX — Save / Trash / Upload
# Notifications
# Migrations (caps seed)
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Content Types & Permissions
--------------------------------------------------------------*/

/** Permission levels, in grid order, with their label pattern. */
function sp_content_levels(): array {
	return [
		'write'      => '%s: write drafts &amp; edit their own',
		'publish'    => '%s: publish their own',
		'edit_all'   => '%s: edit, publish &amp; approve everyone&rsquo;s',
		'delete_own' => '%s: delete their own',
		'delete_all' => '%s: delete everyone&rsquo;s',
	];
}

/**
 * The managed content types, type => label. Keys come from the filter whether or not the type is
 * registered right now, so saved permissions survive a type being switched off for a while; screens
 * use sp_content_live_types() instead.
 */
function sp_content_types(): array {
	$raw = (array) apply_filters( 'sp_content_types', [ 'post' => true, 'page' => true ] );
	$out = [];
	foreach ( $raw as $type => $label ) {
		$type = sanitize_key( (string) $type );
		if ( $type === '' || $label === false || in_array( $type, [ 'attachment', 'revision', 'nav_menu_item', 'bp_product' ], true ) ) continue;
		if ( ! is_string( $label ) || trim( $label ) === '' ) {
			$obj   = post_type_exists( $type ) ? get_post_type_object( $type ) : null;
			$label = $obj ? (string) $obj->labels->name : ucwords( str_replace( [ '_', '-' ], ' ', $type ) );
		}
		$out[ $type ] = $label;
	}
	return $out;
}

/** Managed types that are actually registered on this request. */
function sp_content_live_types(): array {
	return array_filter( sp_content_types(), static fn( $l, $t ) => post_type_exists( $t ), ARRAY_FILTER_USE_BOTH );
}

function sp_content_cap( string $type, string $level ): string {
	return 'content_' . $type . '_' . $level;
}

/** cap => label for every type × level (merged into site_pulse_capability_catalog_all()). */
function sp_content_caps_catalog(): array {
	$cat = [];
	foreach ( sp_content_types() as $type => $label ) {
		foreach ( sp_content_levels() as $level => $pattern ) {
			$cat[ sp_content_cap( $type, $level ) ] = sprintf( $pattern, esc_html( $label ) );
		}
	}
	return $cat;
}

/** What one user may do with one type: [ write, publish, edit_all, delete_own, delete_all ] => bool. */
function sp_content_perms( int $user_id, string $type ): array {
	$out = [];
	foreach ( array_keys( sp_content_levels() ) as $level ) {
		$out[ $level ] = site_pulse_user_can( $user_id, sp_content_cap( $type, $level ) );
	}
	return $out;
}

/** Any access to a type at all (it appears in the type tabs). */
function sp_content_can_use( int $user_id, string $type ): bool {
	return in_array( true, sp_content_perms( $user_id, $type ), true );
}

function sp_content_can_create( int $user_id, string $type ): bool {
	$p = sp_content_perms( $user_id, $type );
	return $p['write'] || $p['publish'] || $p['edit_all'];
}

/**
 * Can this user open a post for editing? Everyone's with edit_all; their own otherwise — but a
 * writer who can't publish can't change their own post once it's live (that would be publishing).
 */
function sp_content_can_edit( int $user_id, WP_Post $post ): bool {
	$p = sp_content_perms( $user_id, $post->post_type );
	if ( $p['edit_all'] ) return true;
	if ( (int) $post->post_author !== $user_id ) return false;
	if ( $p['publish'] ) return true;
	return $p['write'] && in_array( $post->post_status, [ 'draft', 'pending', 'auto-draft' ], true );
}

/** Can this user make $post (or a new one when null) live? */
function sp_content_can_publish( int $user_id, string $type, ?WP_Post $post = null ): bool {
	$p = sp_content_perms( $user_id, $type );
	if ( $p['edit_all'] ) return true;
	return $p['publish'] && ( ! $post || (int) $post->post_author === $user_id );
}

function sp_content_can_delete( int $user_id, WP_Post $post ): bool {
	$p = sp_content_perms( $user_id, $post->post_type );
	return $p['delete_all'] || ( $p['delete_own'] && (int) $post->post_author === $user_id );
}

/** Any type usable at all — the nav item and panel. */
function sp_content_visible( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'content' ) ) return false;
	foreach ( array_keys( sp_content_live_types() ) as $type ) {
		if ( sp_content_can_use( $user_id, $type ) ) return true;
	}
	return false;
}

// Nonce + module on + a managed, registered type the user can use. Returns [ me, type ].
function sp_content_gate( string $type ): array {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_module_on( 'content' ) ) wp_send_json_error( [ 'message' => 'Website Content is switched off.' ] );
	$me = site_pulse_effective_user_id();
	if ( ! isset( sp_content_live_types()[ $type ] ) || ! sp_content_can_use( $me, $type ) ) {
		wp_send_json_error( [ 'message' => 'You do not have access to that content.' ] );
	}
	return [ $me, $type ];
}

// The Grant/Restrict bar on the Website Content screen: each type's Write and Edit-everyone's rows.
add_filter( 'site_pulse_page_access_map', 'sp_content_page_access' );
function sp_content_page_access( array $map ): array {
	$caps = [];
	foreach ( array_keys( sp_content_types() ) as $type ) {
		$caps[] = sp_content_cap( $type, 'write' );
		$caps[] = sp_content_cap( $type, 'edit_all' );
	}
	if ( $caps ) $map['content'] = $caps;
	return $map;
}


/*--------------------------------------------------------------
# Layout Text Blocks (split / chips / rebuild)
--------------------------------------------------------------*/

/** Does this content use the framework layout shortcodes? */
function sp_content_is_layout( string $content ): bool {
	return (bool) preg_match( '/\[(section|layout|col|txt)[\s\]]/', $content );
}

/**
 * Split one area's raw content into segments: [ 'raw' => string ] kept verbatim, and
 * [ 'text' => inner, 'label' => where it is ] for each [txt] block. Plain content is one text segment.
 */
function sp_content_split( string $content ): array {
	if ( ! sp_content_is_layout( $content ) ) return [ [ 'text' => $content, 'label' => '' ] ];

	$segs = [];
	$pos  = 0;
	preg_match_all( '#\[txt(?:\s[^\]]*)?\](.*?)\[/txt\]#s', $content, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );
	foreach ( $m as $hit ) {
		$open_len  = strpos( $hit[0][0], ']' ) + 1;
		$inner_at  = $hit[0][1] + $open_len;
		$segs[]    = [ 'raw' => substr( $content, $pos, $inner_at - $pos ) ];
		$segs[]    = [ 'text' => $hit[1][0], 'label' => sp_content_block_label( substr( $content, 0, $hit[0][1] ) ) ];
		$pos       = $inner_at + strlen( $hit[1][0] );
	}
	$segs[] = [ 'raw' => substr( $content, $pos ) ];
	return $segs;
}

/** "Services" / "Home Hero" — the nearest [section name=""] (or col class) before a block. */
function sp_content_block_label( string $before ): string {
	$name = '';
	if ( preg_match_all( '/\[section\s[^\]]*name="([^"]+)"/', $before, $s ) ) $name = end( $s[1] );
	$col = '';
	if ( preg_match_all( '/\[col(\s[^\]]*)?\]/', $before, $c ) && preg_match( '/class="([^"]+)"/', (string) end( $c[1] ), $cc ) ) {
		$col = trim( str_replace( [ 'span-all' ], '', $cc[1] ) );
	}
	$nice = static fn( $s ) => ucwords( trim( preg_replace( '/[-_\s]+/', ' ', $s ) ) );
	return trim( $nice( $name ) . ( $col !== '' ? ' · ' . $nice( $col ) : '' ), ' ·' );
}

/** Every registered shortcode inside a text block, in order (enclosing ones whole). */
function sp_content_shortcodes( string $text ): array {
	if ( strpos( $text, '[' ) === false ) return [];
	preg_match_all( '/' . get_shortcode_regex() . '/s', $text, $m );
	return $m[0] ?? [];
}

/**
 * A text block as editor HTML: each shortcode becomes a locked chip carrying only its index. Text
 * with no block-level markup gets paragraphs, so its line breaks survive a visual editor.
 */
function sp_content_to_editor( string $text ): string {
	$codes = sp_content_shortcodes( $text );
	$i = 0;
	$html = $codes ? preg_replace_callback( '/' . get_shortcode_regex() . '/s', static function ( $m ) use ( &$i ) {
		$label = sp_content_chip_label( $m );
		// <code>, not <span>: the dashboard's markUniqueSpans() rewrites spans.
		return '<code class="sp-wc-chip" contenteditable="false" data-sc="' . ( $i++ ) . '">' . esc_html( $label ) . '</code>';
	}, $text ) : $text;

	if ( ! preg_match( '#<(p|div|h[1-6]|ul|ol|table|blockquote|figure)[\s>]#i', $html ) && trim( $html ) !== '' ) {
		$html = wpautop( trim( $html ) );
	}
	return $html;
}

/** "Button: Call Us" / "get-biz: phone" — what a chip shows. */
function sp_content_chip_label( array $m ): string {
	$tag   = $m[2];
	$atts  = shortcode_parse_atts( $m[3] );
	$inner = trim( wp_strip_all_tags( (string) ( $m[5] ?? '' ) ) );
	$names = [ 'btn' => 'Button', 'get-icon' => 'Icon', 'get-biz' => 'Business info', 'img' => 'Image', 'social-btn' => 'Social button' ];
	$label = $names[ $tag ] ?? $tag;
	$detail = $inner !== '' ? $inner : ( is_array( $atts ) ? (string) ( $atts['info'] ?? $atts['type'] ?? '' ) : '' );
	return $label . ( $detail !== '' ? ': ' . mb_substr( $detail, 0, 40 ) : '' );
}

/** Editor HTML back to stored text: clean it, then put each chip's original shortcode back. */
function sp_content_from_editor( string $html, array $codes ): string {
	// Chips → private-use placeholders, so kses can't touch the shortcode text.
	$html = preg_replace_callback( '#<code[^>]*\bdata-sc="(\d+)"[^>]*>.*?</code>#s', static function ( $m ) use ( $codes ) {
		return isset( $codes[ (int) $m[1] ] ) ? "\u{E000}" . (int) $m[1] . "\u{E001}" : '';
	}, $html );
	$html = wp_kses_post( $html );
	$html = preg_replace_callback( "/\u{E000}(\d+)\u{E001}/u", static fn( $m ) => $codes[ (int) $m[1] ] ?? '', $html );
	return trim( str_replace( '&nbsp;', ' ', $html ) );
}

/** The editable areas of a post: content, plus Page Top / Bottom when the post has them. */
function sp_content_areas( ?WP_Post $post ): array {
	$areas = [ 'content' => $post ? (string) $post->post_content : '' ];
	if ( $post ) {
		foreach ( [ 'top' => 'page-top_text', 'bottom' => 'page-bottom_text' ] as $key => $meta ) {
			$v = (string) get_post_meta( $post->ID, $meta, true );
			if ( trim( $v ) !== '' ) $areas[ $key ] = $v;
		}
	}
	return $areas;
}

/** Fingerprint of everything editable — a save against a stale copy is refused. */
function sp_content_fingerprint( array $areas ): string {
	ksort( $areas );
	return md5( wp_json_encode( $areas ) );
}

/** Editor payload for an area: [ { i, label, html } ] for the text blocks, plus whether it's a layout. */
function sp_content_area_for_editor( string $raw ): array {
	$blocks = [];
	foreach ( sp_content_split( $raw ) as $i => $seg ) {
		if ( ! isset( $seg['text'] ) ) continue;
		$blocks[] = [ 'i' => $i, 'label' => $seg['label'], 'html' => sp_content_to_editor( $seg['text'] ) ];
	}
	return [ 'layout' => sp_content_is_layout( $raw ), 'blocks' => $blocks ];
}

/** Rebuild an area from its original and the changed blocks ( segment index => editor html ). */
function sp_content_rebuild( string $raw, array $changed ): string {
	$out = '';
	foreach ( sp_content_split( $raw ) as $i => $seg ) {
		if ( isset( $seg['raw'] ) ) { $out .= $seg['raw']; continue; }
		if ( ! array_key_exists( $i, $changed ) ) { $out .= $seg['text']; continue; }
		$new = sp_content_from_editor( (string) $changed[ $i ], sp_content_shortcodes( $seg['text'] ) );
		// Keep the block's own leading/trailing whitespace so the markup stays laid out as written.
		preg_match( '/^\s*/', $seg['text'], $lead );
		preg_match( '/\s*$/', $seg['text'], $trail );
		$out .= ( $lead[0] ?? '' ) . $new . ( $trail[0] ?? '' );
	}
	return $out;
}


/*--------------------------------------------------------------
# Reading
--------------------------------------------------------------*/

function sp_content_status_label( string $status ): string {
	return [ 'publish' => 'Published', 'draft' => 'Draft', 'pending' => 'Waiting for review', 'future' => 'Scheduled', 'private' => 'Private' ][ $status ] ?? ucfirst( $status );
}

function sp_content_row( WP_Post $p, int $me ): array {
	$thumb = has_post_thumbnail( $p ) ? (string) get_the_post_thumbnail_url( $p, 'thumbnail' ) : '';
	$author = get_userdata( (int) $p->post_author );
	return [
		'id'           => (int) $p->ID,
		'title'        => $p->post_title !== '' ? html_entity_decode( get_the_title( $p ), ENT_QUOTES ) : '(no title)',
		'status'       => $p->post_status,
		'status_label' => sp_content_status_label( $p->post_status ),
		'author'       => $author ? $author->display_name : '',
		'mine'         => (int) $p->post_author === $me,
		'modified'     => mysql2date( get_option( 'date_format' ), $p->post_modified ),
		'link'         => $p->post_status === 'publish' ? (string) get_permalink( $p ) : '',
		'thumb'        => $thumb,
		'can_edit'     => sp_content_can_edit( $me, $p ),
		'can_delete'   => sp_content_can_delete( $me, $p ),
	];
}


/*--------------------------------------------------------------
# AJAX — List / Get
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_content_list', 'sp_content_ajax_list' );
function sp_content_ajax_list(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();

	// The types this person can use, for the tabs; the requested one (or the first) is listed.
	$types = [];
	foreach ( sp_content_live_types() as $t => $label ) {
		if ( ! sp_content_can_use( $me, $t ) ) continue;
		$obj     = get_post_type_object( $t );
		$types[] = [ 'type' => $t, 'label' => $label, 'singular' => $obj ? (string) $obj->labels->singular_name : $label, 'can_create' => sp_content_can_create( $me, $t ) ];
	}
	if ( ! $types ) wp_send_json_error( [ 'message' => 'You do not have access to any website content.' ] );

	$type = sanitize_key( $_POST['type'] ?? '' );
	if ( ! in_array( $type, array_column( $types, 'type' ), true ) ) $type = $types[0]['type'];
	[ $me, $type ] = sp_content_gate( $type );

	$perms  = sp_content_perms( $me, $type );
	$view   = sanitize_key( $_POST['view'] ?? 'all' );               // all | publish | draft | pending
	$sort   = sanitize_key( $_POST['sort'] ?? 'recent' );
	$page   = max( 1, (int) ( $_POST['page'] ?? 1 ) );
	$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
	$per    = 40;

	$statuses = [ 'publish', 'draft', 'pending', 'future', 'private' ];
	$base = [ 'post_type' => $type, 's' => $search, 'post_status' => $statuses ];
	// Without edit_all, people see only their own.
	if ( ! $perms['edit_all'] ) $base['author'] = $me;

	$counts = [ 'all' => 0, 'publish' => 0, 'draft' => 0, 'pending' => 0 ];
	foreach ( [ 'publish', 'draft', 'pending' ] as $st ) {
		// array_merge, not +: + would keep $base's post_status and count everything each time.
		$counts[ $st ] = (int) ( new WP_Query( array_merge( $base, [ 'post_status' => $st, 'posts_per_page' => 1, 'fields' => 'ids' ] ) ) )->found_posts;
	}
	$counts['all'] = (int) ( new WP_Query( array_merge( $base, [ 'posts_per_page' => 1, 'fields' => 'ids' ] ) ) )->found_posts;

	$order = [ 'recent' => [ 'modified', 'DESC' ], 'oldest' => [ 'modified', 'ASC' ], 'az' => [ 'title', 'ASC' ], 'za' => [ 'title', 'DESC' ] ][ $sort ] ?? [ 'modified', 'DESC' ];
	$q = new WP_Query( array_merge( $base, [
		'post_status'    => in_array( $view, [ 'publish', 'draft', 'pending' ], true ) ? $view : $statuses,
		'posts_per_page' => $per,
		'paged'          => $page,
		'orderby'        => $order[0],
		'order'          => $order[1],
	] ) );

	wp_send_json_success( [
		'types'    => $types,
		'type'     => $type,
		'items'    => array_map( static fn( $p ) => sp_content_row( $p, $me ), $q->posts ),
		'counts'   => $counts,
		'total'    => (int) $q->found_posts,
		'has_more' => $page < (int) $q->max_num_pages,
		'perms'    => $perms,
	] );
}

add_action( 'wp_ajax_site_pulse_content_get', 'sp_content_ajax_get' );
function sp_content_ajax_get(): void {
	$id   = (int) ( $_POST['id'] ?? 0 );
	$post = $id ? get_post( $id ) : null;
	$type = $post ? $post->post_type : sanitize_key( $_POST['type'] ?? '' );
	[ $me, $type ] = sp_content_gate( $type );

	if ( $id && ( ! $post || $post->post_status === 'trash' ) ) wp_send_json_error( [ 'message' => 'That no longer exists.' ] );
	if ( $post && ! sp_content_can_edit( $me, $post ) ) wp_send_json_error( [ 'message' => 'You can&rsquo;t edit this one.' ] );
	if ( ! $post && ! sp_content_can_create( $me, $type ) ) wp_send_json_error( [ 'message' => 'You can&rsquo;t add new ones.' ] );

	$areas = sp_content_areas( $post );
	$edit  = [];
	foreach ( $areas as $key => $raw ) $edit[ $key ] = sp_content_area_for_editor( $raw );

	$obj      = get_post_type_object( $type );
	$thumb_id = $post ? (int) get_post_thumbnail_id( $post ) : 0;
	$cats     = [];
	if ( is_object_in_taxonomy( $type, 'category' ) ) {
		foreach ( get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] ) ?: [] as $t ) $cats[] = [ 'id' => (int) $t->term_id, 'name' => $t->name ];
	}

	wp_send_json_success( [ 'item' => [
		'id'          => $id,
		'type'        => $type,
		'type_label'  => sp_content_types()[ $type ],
		'singular'    => $obj ? (string) $obj->labels->singular_name : 'Item',
		'title'       => $post ? (string) $post->post_title : '',
		'status'      => $post ? $post->post_status : '',   // new: the editor picks Published or Draft by permission
		'mine'        => ! $post || (int) $post->post_author === $me,
		'author'      => $post && ( $u = get_userdata( (int) $post->post_author ) ) ? $u->display_name : '',
		'areas'       => $edit,
		'fingerprint' => sp_content_fingerprint( $areas ),
		'excerpt'     => $post ? (string) $post->post_excerpt : '',
		'has_excerpt' => post_type_supports( $type, 'excerpt' ) && $type === 'post',
		'has_photo'   => post_type_supports( $type, 'thumbnail' ),
		'photo'       => $thumb_id ? [ 'id' => $thumb_id, 'url' => (string) wp_get_attachment_image_url( $thumb_id, 'medium' ) ] : null,
		'categories'  => $cats,
		'cats'        => $post && $cats ? array_map( 'intval', wp_get_post_categories( $post->ID ) ) : [],
		'seo_title'   => $post && defined( 'BP_SEO_TITLE' ) ? (string) get_post_meta( $post->ID, BP_SEO_TITLE, true ) : '',
		'seo_desc'    => $post && defined( 'BP_SEO_DESC' ) ? (string) get_post_meta( $post->ID, BP_SEO_DESC, true ) : '',
		'has_seo'     => defined( 'BP_SEO_TITLE' ) && defined( 'BP_SEO_DESC' ),
		'link'        => $post && $post->post_status === 'publish' ? (string) get_permalink( $post ) : '',
		'preview'     => $post ? (string) get_preview_post_link( $post ) : '',
		'wp_edit_url' => $post && current_user_can( 'edit_post', $post->ID ) ? (string) get_edit_post_link( $post->ID, 'raw' ) : '',
		'can_publish' => sp_content_can_publish( $me, $type, $post ),
		'can_delete'  => $post ? sp_content_can_delete( $me, $post ) : false,
	] ] );
}


/*--------------------------------------------------------------
# AJAX — Save / Trash / Upload
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_content_save', 'sp_content_ajax_save' );
function sp_content_ajax_save(): void {
	$id   = (int) ( $_POST['id'] ?? 0 );
	$post = $id ? get_post( $id ) : null;
	$type = $post ? $post->post_type : sanitize_key( $_POST['type'] ?? '' );
	[ $me, $type ] = sp_content_gate( $type );

	if ( $id && ( ! $post || $post->post_status === 'trash' ) ) wp_send_json_error( [ 'message' => 'That no longer exists.' ] );
	if ( $post && ! sp_content_can_edit( $me, $post ) ) wp_send_json_error( [ 'message' => 'You can&rsquo;t edit this one.' ] );
	if ( ! $post && ! sp_content_can_create( $me, $type ) ) wp_send_json_error( [ 'message' => 'You can&rsquo;t add new ones.' ] );

	$title = trim( sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) );
	if ( $title === '' ) wp_send_json_error( [ 'message' => 'Give it a title.' ] );

	// Status: what they asked for, limited to what they're allowed. Asking to publish without the
	// right becomes "submit for review" rather than an error.
	$want = sanitize_key( $_POST['status'] ?? 'draft' );
	if ( ! in_array( $want, [ 'publish', 'draft', 'pending' ], true ) ) $want = 'draft';
	if ( $want === 'publish' && ! sp_content_can_publish( $me, $type, $post ) ) $want = 'pending';

	// The page must still be what they opened.
	$areas = sp_content_areas( $post );
	if ( $post && (string) ( $_POST['fingerprint'] ?? '' ) !== sp_content_fingerprint( $areas ) ) {
		wp_send_json_error( [ 'message' => 'Someone else changed this since you opened it. Close it and open it again to get the latest version (copy anything you typed first).', 'stale' => true ] );
	}

	// Changed text blocks: { area: { segmentIndex: html } }. Only areas the post has are touched.
	$changed = json_decode( wp_unslash( (string) ( $_POST['blocks'] ?? '' ) ), true );
	$changed = is_array( $changed ) ? $changed : [];
	$new = [];
	foreach ( $areas as $key => $raw ) {
		if ( empty( $changed[ $key ] ) || ! is_array( $changed[ $key ] ) ) continue;
		$new[ $key ] = sp_content_rebuild( $raw, array_combine( array_map( 'intval', array_keys( $changed[ $key ] ) ), array_values( $changed[ $key ] ) ) );
	}

	$fields = [
		'post_type'   => $type,
		'post_title'  => $title,
		'post_status' => $want,
	];
	if ( array_key_exists( 'content', $new ) ) $fields['post_content'] = $new['content'];
	if ( isset( $_POST['excerpt'] ) && post_type_supports( $type, 'excerpt' ) ) $fields['post_excerpt'] = sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ) );

	$was = $post ? $post->post_status : 'new';
	if ( $post ) {
		$fields['ID'] = $id;
		$res = wp_update_post( wp_slash( $fields ), true );
	} else {
		$fields['post_author'] = $me;
		if ( ! isset( $fields['post_content'] ) ) $fields['post_content'] = '';
		$res = wp_insert_post( wp_slash( $fields ), true );
	}
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );
	$id = (int) $res;

	foreach ( [ 'top' => 'page-top_text', 'bottom' => 'page-bottom_text' ] as $key => $meta ) {
		if ( array_key_exists( $key, $new ) ) update_post_meta( $id, $meta, wp_slash( $new[ $key ] ) );
	}

	// Photo: only this post's own, or an unattached upload this person just made.
	if ( isset( $_POST['photo_id'] ) && post_type_supports( $type, 'thumbnail' ) ) {
		$photo = (int) $_POST['photo_id'];
		if ( ! $photo ) {
			delete_post_thumbnail( $id );
		} else {
			$att = get_post( $photo );
			$ok  = $att && $att->post_type === 'attachment' && wp_attachment_is_image( $photo ) && (
				(int) $att->post_parent === $id || (int) get_post_thumbnail_id( $id ) === $photo ||
				( (int) $att->post_parent === 0 && (int) $att->post_author === $me )
			);
			if ( $ok ) {
				if ( (int) $att->post_parent === 0 ) wp_update_post( [ 'ID' => $photo, 'post_parent' => $id ] );
				set_post_thumbnail( $id, $photo );
			}
		}
	}

	if ( isset( $_POST['cats'] ) && is_object_in_taxonomy( $type, 'category' ) ) {
		$cats = array_values( array_filter( array_map( 'intval', (array) json_decode( wp_unslash( (string) $_POST['cats'] ), true ) ) ) );
		wp_set_post_categories( $id, $cats );
	}

	if ( defined( 'BP_SEO_TITLE' ) && defined( 'BP_SEO_DESC' ) && isset( $_POST['seo_title'] ) ) {
		foreach ( [ BP_SEO_TITLE => 'seo_title', BP_SEO_DESC => 'seo_desc' ] as $meta => $key ) {
			$v = trim( sanitize_text_field( wp_unslash( (string) ( $_POST[ $key ] ?? '' ) ) ) );
			if ( $v === '' ) delete_post_meta( $id, $meta );
			else             update_post_meta( $id, $meta, $v );
		}
	}

	sp_content_notify_transition( get_post( $id ), $was, $me );

	$label = sp_content_types()[ $type ];
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( $post ? 'content_update' : 'content_add', ( $post ? 'Updated' : 'Added' ) . ' ' . $label . ': ' . $title );

	$msg = [ 'publish' => 'Saved. It&rsquo;s live on the website.', 'draft' => 'Saved as a draft.', 'pending' => 'Sent for review. You&rsquo;ll be notified when it&rsquo;s published.' ][ $want ];
	if ( $want === 'pending' && sanitize_key( $_POST['status'] ?? '' ) === 'publish' ) $msg = 'You can&rsquo;t publish this yourself, so it was sent for review.';
	wp_send_json_success( [ 'id' => $id, 'status' => $want, 'message' => $msg, 'row' => sp_content_row( get_post( $id ), $me ) ] );
}

// Delete = the WordPress trash (recoverable there).
add_action( 'wp_ajax_site_pulse_content_trash', 'sp_content_ajax_trash' );
function sp_content_ajax_trash(): void {
	$id   = (int) ( $_POST['id'] ?? 0 );
	$post = $id ? get_post( $id ) : null;
	if ( ! $post ) { check_ajax_referer( 'site_pulse_nonce', 'nonce' ); wp_send_json_error( [ 'message' => 'That no longer exists.' ] ); }
	[ $me ] = sp_content_gate( $post->post_type );
	if ( ! sp_content_can_delete( $me, $post ) ) wp_send_json_error( [ 'message' => 'You can&rsquo;t delete this one.' ] );
	if ( (int) get_option( 'page_on_front' ) === $id || (int) get_option( 'page_for_posts' ) === $id ) {
		wp_send_json_error( [ 'message' => 'This is the site&rsquo;s home page (or blog page), so it can&rsquo;t be deleted here.' ] );
	}

	$title = get_the_title( $post );
	if ( ! wp_trash_post( $id ) ) wp_send_json_error( [ 'message' => 'Could not delete it.' ] );
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'content_delete', 'Deleted ' . sp_content_types()[ $post->post_type ] . ': ' . $title );
	wp_send_json_success( [ 'id' => $id ] );
}

// One photo → an unattached media-library image; saving the post makes it the featured image.
add_action( 'wp_ajax_site_pulse_content_upload', 'sp_content_ajax_upload' );
function sp_content_ajax_upload(): void {
	[ $me, $type ] = sp_content_gate( sanitize_key( $_POST['type'] ?? '' ) );
	if ( ! sp_content_can_create( $me, $type ) ) wp_send_json_error( [ 'message' => 'You can&rsquo;t add photos.' ] );

	if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ?? '' ) ) wp_send_json_error( [ 'message' => 'No file received.' ] );
	$file = $_FILES['file'];
	if ( (int) $file['size'] > 25 * 1024 * 1024 ) wp_send_json_error( [ 'message' => 'Photo is too large (max 25 MB).' ] );
	$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif' ], true ) ) wp_send_json_error( [ 'message' => 'Please upload a photo (jpg, png, webp or heic).' ] );
	$check = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'] );
	if ( empty( $check['type'] ) || strpos( (string) $check['type'], 'image/' ) !== 0 ) wp_send_json_error( [ 'message' => 'That file is not a valid image.' ] );

	$_REQUEST['post_id'] = 0;
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$moved = wp_handle_upload( $file, [ 'test_form' => false ] );
	if ( empty( $moved['file'] ) || ! empty( $moved['error'] ) ) wp_send_json_error( [ 'message' => $moved['error'] ?? 'Upload failed.' ] );
	$aid = wp_insert_attachment( [
		'post_mime_type' => $moved['type'],
		'post_title'     => sanitize_text_field( pathinfo( (string) $file['name'], PATHINFO_FILENAME ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_author'    => $me,
	], $moved['file'] );
	if ( is_wp_error( $aid ) || ! $aid ) wp_send_json_error( [ 'message' => 'Could not save the photo.' ] );
	wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $moved['file'] ) );

	wp_send_json_success( [ 'id' => (int) $aid, 'url' => (string) ( wp_get_attachment_image_url( $aid, 'medium' ) ?: $moved['url'] ) ] );
}


/*--------------------------------------------------------------
# Notifications
--------------------------------------------------------------*/

/** Active Site Pulse users who hold a capability (gods always count). */
function sp_content_user_ids_with_cap( string $cap ): array {
	global $wpdb;
	$ids = array_map( 'intval', $wpdb->get_col( "SELECT user_id FROM " . site_pulse_table( 'user_profiles' ) . " WHERE status = 'active'" ) ?: [] );
	return array_values( array_filter( $ids, static fn( $uid ) => site_pulse_user_can( $uid, $cap ) ) );
}

/**
 * Submitted for review → everyone who can approve it (edit_all) hears about it. Published by someone
 * other than the writer → the writer hears it went live.
 */
function sp_content_notify_transition( ?WP_Post $post, string $was, int $me ): void {
	if ( ! $post || ! function_exists( 'site_pulse_deliver' ) ) return;
	$label  = sp_content_types()[ $post->post_type ] ?? 'Content';
	$obj    = get_post_type_object( $post->post_type );
	$single = $obj ? strtolower( (string) $obj->labels->singular_name ) : 'item';
	$title  = html_entity_decode( get_the_title( $post ), ENT_QUOTES );

	if ( $post->post_status === 'pending' && $was !== 'pending' ) {
		$who = get_userdata( $me );
		foreach ( sp_content_user_ids_with_cap( sp_content_cap( $post->post_type, 'edit_all' ) ) as $uid ) {
			if ( $uid === $me ) continue;
			site_pulse_deliver( $uid, 'content_review', ( $who ? $who->display_name : 'Someone' ) . ' sent a ' . $single . ' for review: “' . $title . '”', (int) $post->ID, 'content' );
		}
	}
	if ( $post->post_status === 'publish' && $was === 'pending' && (int) $post->post_author !== $me ) {
		site_pulse_deliver( (int) $post->post_author, 'content_published', 'Your ' . $single . ' “' . $title . '” was approved and is live on the website.', (int) $post->ID, 'content' );
	}
}


/*--------------------------------------------------------------
# Migrations (caps seed)
--------------------------------------------------------------*/

/**
 * Once the module is on: owner / admin / system-admin get every level for every managed type.
 * Tracks which types were seeded, so a type a site adds later is seeded too; never re-adds a cap
 * someone removed for a type already seeded. Other roles start with nothing (tick to grant).
 */
add_action( 'init', 'sp_content_migrate_caps', 25 );
function sp_content_migrate_caps(): void {
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'content' ) ) return;
	$done  = (array) get_option( 'site_pulse_content_caps_seeded', [] );
	$todo  = array_diff( array_keys( sp_content_types() ), $done );
	if ( ! $todo ) return;

	global $wpdb;
	$caps = [];
	foreach ( $todo as $type ) foreach ( array_keys( sp_content_levels() ) as $level ) $caps[] = sp_content_cap( $type, $level );
	foreach ( [ 'owner', 'admin', 'system-admin' ] as $slug ) {
		$role = site_pulse_get_role_by_slug( $slug );
		if ( ! $role ) continue;
		$have   = json_decode( $role['capabilities'], true ) ?: [];
		$merged = array_values( array_unique( array_merge( $have, $caps ) ) );
		if ( $merged !== $have ) $wpdb->update( site_pulse_table( 'roles' ), [ 'capabilities' => wp_json_encode( $merged ) ], [ 'id' => (int) $role['id'] ] );
	}
	update_option( 'site_pulse_content_caps_seeded', array_values( array_merge( $done, $todo ) ) );
}
