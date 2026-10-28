<?php
/* Battle Plan Web Design — Site Pulse: Store Products & Inventory

/*--------------------------------------------------------------
Store → Products and Store → Inventory: the client edits their own catalogue
from their Site Pulse login, without ever seeing wp-admin.

SAME RECORDS, SAME SAVE. A product is still a bp_product post with its variants
in the variants table. This file writes the post with wp_insert_post() /
wp_update_post() and hands everything else to sp_store_save_product_data() —
the one function the wp-admin metaboxes also end in. Edit a product in either
place and it lands identically: permalink, sitemap, schema, revisions, SEO.

PERMISSIONS ARE SITE PULSE'S, DELIBERATELY. A store manager is often a
Subscriber in WordPress terms, with no edit_posts at all. Every endpoint here
gates on the Site Pulse `manage_products` permission and then writes in a
trusted context — it does NOT defer to WordPress capabilities. That is the
design, not an oversight: Site Pulse's permission model is the one the client
actually manages. (wp_insert_post() checks no capability itself; kses still
runs on content for a user without unfiltered_html, which is the right outcome.)

PHOTOS ARE REAL ATTACHMENTS — wp_handle_upload() + wp_insert_attachment(), the
Jobsite GEO route — so media-replace, alt-sync and the framework's WebP and
rename filters work on product images exactly as on uploads made in wp-admin.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Reading & Formatting
# Saving
# Inventory
# AJAX — Products
# AJAX — Inventory
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Reading & Formatting
--------------------------------------------------------------*/

/** The post statuses the editor offers, in the words a shop owner uses. */
function sp_store_product_statuses(): array {
	return [
		'publish' => 'For sale',
		'draft'   => 'Hidden (draft)',
	];
}

/**
 * How a product's stock reads at a glance, across every variant:
 * "Not tracked", "24 in stock", "2 left", "Sold out", "1 of 3 sold out".
 */
function sp_store_stock_summary( int $product_id ): array {
	$tracked = 0;
	$units   = 0;
	$out     = 0;
	$low     = 0;
	$active  = sp_store_get_variants( $product_id, true );

	foreach ( $active as $v ) {
		if ( ! (int) $v['track_stock'] ) continue;
		$tracked++;
		$avail = (int) sp_store_available( $v );
		$units += max( 0, $avail );
		if ( $avail <= 0 && ! (int) $v['allow_backorder'] ) $out++;
		elseif ( $avail > 0 && $avail <= sp_store_low_threshold( $v ) ) $low++;
	}

	if ( ! $tracked )                return [ 'label' => 'Not tracked', 'tone' => 'muted' ];
	if ( $out && $out === $tracked ) return [ 'label' => 'Sold out', 'tone' => 'danger' ];
	if ( $out )                      return [ 'label' => $out . ' of ' . count( $active ) . ' sold out', 'tone' => 'warning' ];
	if ( $low )                      return [ 'label' => $units . ' left', 'tone' => 'warning' ];
	return [ 'label' => $units . ' in stock', 'tone' => 'success' ];
}

/**
 * When a variant counts as "running low". Its own setting when one is saved,
 * otherwise 3 — a warning nobody configured is still better than none.
 */
function sp_store_low_threshold( array $variant ): int {
	$set = (int) ( $variant['low_stock_at'] ?? 0 );
	return $set > 0 ? $set : 3;
}

/** One line in the products list. */
function sp_store_product_row( WP_Post $post ): array {
	$id    = (int) $post->ID;
	$terms = get_the_terms( $id, 'bp_product_cat' );
	$thumb = 0;
	$gallery = sp_store_gallery( $id );
	if ( $gallery ) $thumb = (int) $gallery[0]['id'];
	elseif ( has_post_thumbnail( $id ) ) $thumb = (int) get_post_thumbnail_id( $id );

	return [
		'id'         => $id,
		'title'      => get_the_title( $post ) ?: '(no name)',
		'status'     => (string) $post->post_status,
		'for_sale'   => $post->post_status === 'publish',
		'thumb'      => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'thumbnail' ) : '',
		'price'      => html_entity_decode( wp_strip_all_tags( sp_store_price_range_label( $id ) ), ENT_QUOTES, 'UTF-8' ),
		'on_sale'    => ! empty( sp_store_price_range( $id )['on_sale'] ),
		'stock'      => sp_store_stock_summary( $id ),
		'variants'   => count( sp_store_get_variants( $id, true ) ),
		'categories' => ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'name' ) : [],
		'url'        => (string) get_permalink( $id ),
	];
}

/** A page of products for the list. */
function sp_store_query_products( array $args ): array {
	$per_page = 25;
	$status   = (string) ( $args['status'] ?? '' );

	$q = [
		'post_type'      => SITE_PULSE_STORE_CPT,
		'post_status'    => isset( sp_store_product_statuses()[ $status ] ) ? $status : [ 'publish', 'draft', 'pending', 'private', 'future' ],
		'posts_per_page' => $per_page,
		'paged'          => max( 1, (int) ( $args['page'] ?? 1 ) ),
		'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
		'no_found_rows'  => false,
	];

	$search = trim( (string) ( $args['search'] ?? '' ) );
	if ( $search !== '' ) $q['s'] = $search;

	$cat = (int) ( $args['category'] ?? 0 );
	if ( $cat ) $q['tax_query'] = [ [ 'taxonomy' => 'bp_product_cat', 'field' => 'term_id', 'terms' => $cat ] ];

	$query = new WP_Query( $q );
	return [
		'products' => array_map( 'sp_store_product_row', $query->posts ),
		'page'     => (int) $q['paged'],
		'pages'    => max( 1, (int) $query->max_num_pages ),
		'total'    => (int) $query->found_posts,
	];
}

/** Every category, for the picker. Flat, with depth for indenting. */
function sp_store_category_choices(): array {
	$terms = get_terms( [ 'taxonomy' => 'bp_product_cat', 'hide_empty' => false, 'orderby' => 'name' ] );
	if ( is_wp_error( $terms ) ) return [];

	$by_parent = [];
	foreach ( $terms as $t ) $by_parent[ (int) $t->parent ][] = $t;

	$out  = [];
	$walk = static function ( int $parent, int $depth ) use ( &$walk, &$out, $by_parent ) {
		foreach ( $by_parent[ $parent ] ?? [] as $t ) {
			$out[] = [ 'id' => (int) $t->term_id, 'name' => (string) $t->name, 'depth' => $depth ];
			$walk( (int) $t->term_id, $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $out;
}

/**
 * Everything the editor needs for one product. $id 0 is a blank new product, so the
 * editor has one shape to render whether it is creating or editing.
 */
function sp_store_product_for_editor( int $id ): array {
	$post = $id ? get_post( $id ) : null;
	if ( $id && ( ! $post || $post->post_type !== SITE_PULSE_STORE_CPT ) ) return [];

	$variants = [];
	foreach ( $id ? sp_store_get_variants( $id ) : [] as $v ) {
		$variants[] = [
			'id'              => (int) $v['id'],
			'option1'         => (string) $v['option1'],
			'option2'         => (string) $v['option2'],
			'option3'         => (string) $v['option3'],
			'sku'             => (string) $v['sku'],
			'price'           => sp_store_price_input( (int) $v['price_cents'] ),
			'sale_price'      => sp_store_price_input( (int) $v['sale_price_cents'] ),
			'stock'           => (int) $v['stock'],
			'committed'       => (int) $v['stock_committed'],
			'track_stock'     => (int) $v['track_stock'] === 1,
			'allow_backorder' => (int) $v['allow_backorder'] === 1,
			'weight_grams'    => (int) $v['weight_grams'],
			'archived'        => $v['status'] === 'archived',
		];
	}

	$gallery = [];
	foreach ( $id ? sp_store_gallery( $id ) : [] as $item ) {
		$gallery[] = [
			'id'  => (int) $item['id'],
			'tag' => (string) $item['tag'],
			'url' => (string) wp_get_attachment_image_url( (int) $item['id'], 'medium' ),
		];
	}
	// An older product with only a Featured Image: show it as the first photo, so
	// saving from here does not appear to have thrown the picture away.
	if ( ! $gallery && $id && has_post_thumbnail( $id ) ) {
		$tid = (int) get_post_thumbnail_id( $id );
		$gallery[] = [ 'id' => $tid, 'tag' => '', 'url' => (string) wp_get_attachment_image_url( $tid, 'medium' ) ];
	}

	$terms = $id ? wp_get_object_terms( $id, 'bp_product_cat', [ 'fields' => 'ids' ] ) : [];

	return [
		'id'          => $id,
		'title'       => $post ? (string) $post->post_title : '',
		'status'      => $post ? ( $post->post_status === 'publish' ? 'publish' : 'draft' ) : 'draft',
		'content'     => $post ? (string) $post->post_content : '',
		'menu_order'  => $post ? (int) $post->menu_order : 0,
		'categories'  => is_wp_error( $terms ) ? [] : array_map( 'intval', $terms ),
		'options'     => $id ? sp_store_options( $id ) : [],
		'variants'    => $variants,
		'gallery'     => $gallery,
		'ships'       => $id ? sp_store_product_ships( $id ) : true,
		'seo_title'   => $id && defined( 'BP_SEO_TITLE' ) ? (string) get_post_meta( $id, BP_SEO_TITLE, true ) : '',
		'seo_desc'    => $id && defined( 'BP_SEO_DESC' ) ? (string) get_post_meta( $id, BP_SEO_DESC, true ) : '',
		'url'         => $id ? (string) get_permalink( $id ) : '',
		// The block editor is still there for the rare product that needs more than
		// this screen offers; staff who can use wp-admin get a way in.
		'wp_edit_url' => $id && current_user_can( 'edit_post', $id ) ? (string) get_edit_post_link( $id, 'raw' ) : '',
		// Content written in the block editor carries block markup this editor would
		// flatten. Say so instead of quietly stripping it on the next save.
		'has_blocks'  => $post ? has_blocks( $post->post_content ) : false,
		'currency'    => sp_store_currency(),
		// Extras, each present only while its feature is on — the editor draws an Extras
		// section only when something is here.
		'extras'      => sp_store_product_extras( $id ),
	];
}

/** The Extras the editor should offer for this product, keyed by feature. Empty = no Extras section. */
function sp_store_product_extras( int $id ): array {
	$out = [];
	if ( function_exists( 'sp_store_members_on' ) && sp_store_members_on() ) {
		$out['membership'] = [
			'plan'  => $id ? sp_member_product_plan( $id ) : 0,
			'plans' => array_map( static fn( $p ) => [ 'id' => (int) $p['id'], 'name' => $p['name'] . ' (' . sp_member_plan_length_label( $p ) . ')' ], sp_member_plans( true ) ),
		];
	}
	if ( function_exists( 'sp_store_downloads_on' ) && sp_store_downloads_on() ) {
		$out['downloads'] = [
			'files' => $id ? sp_store_downloads_for_editor( $id ) : [],
			'types' => '.' . implode( ',.', sp_store_download_types() ),
			'max'   => size_format( wp_max_upload_size() ),
		];
	}
	if ( function_exists( 'sp_store_agreements_on' ) && sp_store_agreements_on() ) {
		$out['agreements'] = [
			'chosen' => $id ? sp_store_product_agreements( $id ) : [],
			'all'    => array_values( array_map( static fn( $a ) => [ 'id' => $a['id'], 'title' => $a['title'], 'signature' => $a['signature'] ], sp_store_agreements() ) ),
		];
	}
	return $out;
}


/*--------------------------------------------------------------
# Saving
--------------------------------------------------------------*/

/**
 * Create or update a product from the Site Pulse editor.
 *
 * The post fields are written here; everything commercial goes to
 * sp_store_save_product_data(), the same function wp-admin's metaboxes call.
 *
 * @param array $data id, title, status, content, menu_order, categories[], seo_title,
 *                    seo_desc, ships, options[], variants[], gallery[]
 * @return array|WP_Error [ 'id' => int, 'warnings' => string[] ]
 */
function sp_store_save_product_from_site_pulse( array $data, int $user_id ) {
	$id    = (int) ( $data['id'] ?? 0 );
	$title = trim( sanitize_text_field( (string) ( $data['title'] ?? '' ) ) );
	if ( $title === '' ) return new WP_Error( 'sp_store_product', 'Give the product a name.' );

	if ( $id ) {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== SITE_PULSE_STORE_CPT ) return new WP_Error( 'sp_store_product', 'That product no longer exists.' );
	}

	$status = ( $data['status'] ?? '' ) === 'publish' ? 'publish' : 'draft';
	$fields = [
		'post_type'    => SITE_PULSE_STORE_CPT,
		'post_title'   => $title,
		'post_content' => wp_kses_post( (string) ( $data['content'] ?? '' ) ),
		'post_status'  => $status,
		'menu_order'   => (int) ( $data['menu_order'] ?? 0 ),
	];

	if ( $id ) {
		$fields['ID'] = $id;
		$result = wp_update_post( wp_slash( $fields ), true );
	} else {
		$fields['post_author'] = $user_id;
		$result = wp_insert_post( wp_slash( $fields ), true );
	}
	if ( is_wp_error( $result ) ) return $result;
	$id = (int) $result;

	// ---- Categories
	$cats = array_values( array_filter( array_map( 'intval', (array) ( $data['categories'] ?? [] ) ) ) );
	wp_set_object_terms( $id, $cats, 'bp_product_cat', false );

	// ---- SEO: a product made here must not ship with default meta, which would undo
	// the whole reason products are a post type.
	if ( defined( 'BP_SEO_TITLE' ) && defined( 'BP_SEO_DESC' ) ) {
		foreach ( [ BP_SEO_TITLE => 'seo_title', BP_SEO_DESC => 'seo_desc' ] as $meta => $key ) {
			$value = trim( sanitize_text_field( (string) ( $data[ $key ] ?? '' ) ) );
			if ( $value === '' ) delete_post_meta( $id, $meta );
			else                 update_post_meta( $id, $meta, $value );
		}
	}

	// ---- Photos. Only ones this product already had, or that this person just uploaded
	// and nobody has attached anywhere yet — never an arbitrary attachment id from the
	// browser, which could otherwise pull another post's image into this product.
	$had     = array_column( sp_store_gallery( $id ), 'id' );
	if ( has_post_thumbnail( $id ) ) $had[] = (int) get_post_thumbnail_id( $id );
	$gallery = [];
	foreach ( (array) ( $data['gallery'] ?? [] ) as $item ) {
		$aid = (int) ( $item['id'] ?? 0 );
		if ( ! $aid || get_post_type( $aid ) !== 'attachment' ) continue;
		$mine_new = (int) get_post_field( 'post_parent', $aid ) === 0 && (int) get_post_field( 'post_author', $aid ) === $user_id;
		if ( ! in_array( $aid, $had, true ) && ! $mine_new && (int) get_post_field( 'post_parent', $aid ) !== $id ) continue;

		if ( (int) get_post_field( 'post_parent', $aid ) === 0 ) {
			wp_update_post( [ 'ID' => $aid, 'post_parent' => $id ] );
		}
		$gallery[] = [ 'id' => $aid, 'tag' => (string) ( $item['tag'] ?? '' ) ];
	}

	$commerce = [
		'options'  => (array) ( $data['options'] ?? [] ),
		'gallery'  => $gallery,
		'variants' => (array) ( $data['variants'] ?? [] ),
		'ships'    => ! empty( $data['ships'] ),
	];
	if ( array_key_exists( 'grants_plan', $data ) ) $commerce['grants_plan'] = (int) $data['grants_plan'];
	if ( array_key_exists( 'agreements', $data ) )  $commerce['agreements']  = (array) $data['agreements'];
	$warnings = sp_store_save_product_data( $id, $commerce );

	// The first photo doubles as the Featured Image, so cards, search results and
	// social shares all show the same picture as the product page.
	if ( $gallery ) set_post_thumbnail( $id, (int) $gallery[0]['id'] );
	else            delete_post_thumbnail( $id );

	if ( function_exists( 'site_pulse_log' ) ) {
		site_pulse_log( 'store_product', ( (int) ( $data['id'] ?? 0 ) ? 'Updated' : 'Added' ) . ' product "' . $title . '".', [ 'product_id' => $id, 'user_id' => $user_id ] );
	}

	return [ 'id' => $id, 'warnings' => $warnings ];
}


/*--------------------------------------------------------------
# Inventory
--------------------------------------------------------------*/

/** Tracked variants across the catalogue, lowest stock first. */
function sp_store_query_inventory( array $args ): array {
	global $wpdb;
	$v = sp_store_table( 'variants' );

	$where  = [ "v.track_stock = 1", "v.status = 'active'", "p.post_status NOT IN ('trash','auto-draft')" ];
	$params = [];

	$search = trim( (string) ( $args['search'] ?? '' ) );
	if ( $search !== '' ) {
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$where[]  = '( p.post_title LIKE %s OR v.sku LIKE %s OR v.option1 LIKE %s OR v.option2 LIKE %s )';
		array_push( $params, $like, $like, $like, $like );
	}

	$avail  = '( v.stock - v.stock_committed )';
	$filter = (string) ( $args['filter'] ?? '' );
	if ( $filter === 'out' ) $where[] = "$avail <= 0";
	if ( $filter === 'low' ) $where[] = "$avail > 0 AND $avail <= ( CASE WHEN v.low_stock_at > 0 THEN v.low_stock_at ELSE 3 END )";

	$sql = "SELECT v.*, p.post_title FROM $v v JOIN {$wpdb->posts} p ON p.ID = v.product_id
	         WHERE " . implode( ' AND ', $where ) . " ORDER BY $avail ASC, p.post_title ASC, v.position ASC LIMIT 300";
	$rows = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A ) ?: [];

	return array_map( 'sp_store_inventory_row', $rows );
}

function sp_store_inventory_row( array $v ): array {
	$avail = (int) $v['stock'] - (int) $v['stock_committed'];
	if ( $avail <= 0 && ! (int) $v['allow_backorder'] ) $tone = [ 'Sold out', 'danger' ];
	elseif ( $avail <= 0 )                               $tone = [ 'Backordered', 'warning' ];
	elseif ( $avail <= sp_store_low_threshold( $v ) )    $tone = [ 'Running low', 'warning' ];
	else                                                 $tone = [ 'In stock', 'success' ];

	$label = sp_store_variant_label( $v );
	return [
		'variant_id'   => (int) $v['id'],
		'product_id'   => (int) $v['product_id'],
		'product'      => (string) ( $v['post_title'] ?? get_the_title( (int) $v['product_id'] ) ),
		'variant'      => $label === 'Default' ? '' : $label,
		'sku'          => (string) $v['sku'],
		'stock'        => (int) $v['stock'],
		'committed'    => (int) $v['stock_committed'],
		'available'    => $avail,
		'low_stock_at' => (int) $v['low_stock_at'],
		'state'        => [ 'label' => $tone[0], 'tone' => $tone[1] ],
	];
}

/**
 * Change one variant's stock the way staff think about it:
 *   received  N more arrived            (+N, "Restocked")
 *   counted   there are N on the shelf  (set to N, "Manual adjustment")
 *   lost      N damaged, lost or stolen (-N, "Damaged or lost")
 * Every one is a ledger row, through the same two functions the rest of the store uses.
 */
function sp_store_inventory_adjust( int $variant_id, string $mode, int $qty, string $note ) {
	$variant = sp_store_get_variant( $variant_id );
	if ( ! $variant ) return new WP_Error( 'sp_store_inventory', 'That item no longer exists.' );
	if ( $qty < 0 )   return new WP_Error( 'sp_store_inventory', 'Use a number of zero or more.' );

	switch ( $mode ) {
		case 'received':
			if ( ! $qty ) return new WP_Error( 'sp_store_inventory', 'Enter how many arrived.' );
			$ok = sp_store_adjust_stock( $variant_id, $qty, 'restock', 0, $note ?: 'Stock received' );
			break;
		case 'lost':
			if ( ! $qty ) return new WP_Error( 'sp_store_inventory', 'Enter how many were lost or damaged.' );
			$ok = sp_store_adjust_stock( $variant_id, -$qty, 'shrink', 0, $note ?: 'Damaged or lost' );
			break;
		case 'counted':
			$ok = sp_store_set_stock( $variant_id, $qty, 'manual', $note ?: 'Stock count' );
			break;
		default:
			return new WP_Error( 'sp_store_inventory', 'Choose what happened.' );
	}

	if ( ! $ok ) return new WP_Error( 'sp_store_inventory', 'Could not update the stock. Please try again.' );
	sp_store_flush_variant_cache( (int) $variant['product_id'] );
	sp_store_refresh_sort_meta( (int) $variant['product_id'] );
	return true;
}


/*--------------------------------------------------------------
# AJAX — Products
--------------------------------------------------------------*/

/** Nonce, module, then manage_products. Returns the acting (possibly impersonated) user. */
function sp_store_products_gate(): int {
	return sp_store_orders_gate( 'sp_store_can_manage_products', 'Editing products needs the "Manage products" permission.' );
}

add_action( 'wp_ajax_site_pulse_store_products', 'sp_store_ajax_products' );
function sp_store_ajax_products(): void {
	sp_store_products_gate();
	$out = sp_store_query_products( [
		'search'   => sanitize_text_field( wp_unslash( (string) ( $_POST['search'] ?? '' ) ) ),
		'status'   => sanitize_key( (string) ( $_POST['status'] ?? '' ) ),
		'category' => (int) ( $_POST['category'] ?? 0 ),
		'page'     => (int) ( $_POST['page'] ?? 1 ),
	] );
	$out['categories'] = sp_store_category_choices();
	$out['shop_url']   = sp_store_shop_url();
	wp_send_json_success( $out );
}

add_action( 'wp_ajax_site_pulse_store_product_get', 'sp_store_ajax_product_get' );
function sp_store_ajax_product_get(): void {
	sp_store_products_gate();
	$product = sp_store_product_for_editor( (int) ( $_POST['id'] ?? 0 ) );
	if ( ! $product ) wp_send_json_error( [ 'message' => 'That product no longer exists.' ] );
	wp_send_json_success( [
		'product'    => $product,
		'categories' => sp_store_category_choices(),
		'statuses'   => sp_store_product_statuses(),
	] );
}

add_action( 'wp_ajax_site_pulse_store_product_save', 'sp_store_ajax_product_save' );
function sp_store_ajax_product_save(): void {
	$me = sp_store_products_gate();

	// The editor posts its structured parts as JSON strings — nested rows survive that
	// intact, where form-encoding three levels deep is where values go missing.
	$json = static function ( string $key ): array {
		$v = json_decode( wp_unslash( (string) ( $_POST[ $key ] ?? '' ) ), true );
		return is_array( $v ) ? $v : [];
	};

	$extras = [];
	if ( isset( $_POST['grants_plan'] ) && function_exists( 'sp_store_members_on' ) && sp_store_members_on() ) {
		$extras['grants_plan'] = (int) $_POST['grants_plan'];
	}
	if ( isset( $_POST['agreements'] ) && function_exists( 'sp_store_agreements_on' ) && sp_store_agreements_on() ) {
		$extras['agreements'] = array_map( 'intval', (array) ( json_decode( wp_unslash( (string) $_POST['agreements'] ), true ) ?: [] ) );
	}

	$result = sp_store_save_product_from_site_pulse( $extras + [
		'id'         => (int) ( $_POST['id'] ?? 0 ),
		'title'      => wp_unslash( (string) ( $_POST['title'] ?? '' ) ),
		'status'     => sanitize_key( (string) ( $_POST['status'] ?? 'draft' ) ),
		'content'    => wp_unslash( (string) ( $_POST['content'] ?? '' ) ),
		'menu_order' => (int) ( $_POST['menu_order'] ?? 0 ),
		'categories' => $json( 'categories' ),
		'seo_title'  => wp_unslash( (string) ( $_POST['seo_title'] ?? '' ) ),
		'seo_desc'   => wp_unslash( (string) ( $_POST['seo_desc'] ?? '' ) ),
		'ships'      => ! empty( $_POST['ships'] ),
		'options'    => $json( 'options' ),
		'variants'   => $json( 'variants' ),
		'gallery'    => $json( 'gallery' ),
	], $me );

	if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );

	wp_send_json_success( [
		'message'  => $result['warnings'] ? 'Saved, with something to check.' : 'Product saved.',
		'warnings' => $result['warnings'],
		'product'  => sp_store_product_for_editor( (int) $result['id'] ),
	] );
}

add_action( 'wp_ajax_site_pulse_store_product_trash', 'sp_store_ajax_product_trash' );
function sp_store_ajax_product_trash(): void {
	$me   = sp_store_products_gate();
	$id   = (int) ( $_POST['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || $post->post_type !== SITE_PULSE_STORE_CPT ) wp_send_json_error( [ 'message' => 'That product no longer exists.' ] );

	// Trash, never delete: a product removed by mistake comes back from wp-admin's
	// Trash, and its variants (which past orders point at) are never touched.
	wp_trash_post( $id );
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'store_product', 'Removed product "' . $post->post_title . '".', [ 'product_id' => $id, 'user_id' => $me ] );
	wp_send_json_success( [ 'message' => 'Product removed. It can be brought back from the Trash in WordPress.' ] );
}

add_action( 'wp_ajax_site_pulse_store_product_upload', 'sp_store_ajax_product_upload' );
function sp_store_ajax_product_upload(): void {
	$me = sp_store_products_gate();

	if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ?? '' ) ) {
		wp_send_json_error( [ 'message' => 'No file received.' ] );
	}
	$file = $_FILES['file'];
	if ( (int) $file['size'] > 25 * 1024 * 1024 ) wp_send_json_error( [ 'message' => 'Photo is too large (max 25 MB).' ] );

	$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Please upload a photo (jpg, png, webp or heic).' ] );
	}
	$check = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'] );
	if ( empty( $check['type'] ) || strpos( (string) $check['type'], 'image/' ) !== 0 ) {
		wp_send_json_error( [ 'message' => 'That file is not a valid image.' ] );
	}

	// The framework's rename filter reads $_REQUEST['post_id']; only a real product counts.
	$post_id = (int) ( $_POST['post_id'] ?? 0 );
	if ( $post_id && get_post_type( $post_id ) !== SITE_PULSE_STORE_CPT ) $_REQUEST['post_id'] = 0;

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$moved = wp_handle_upload( $file, [ 'test_form' => false ] );
	if ( empty( $moved['file'] ) || ! empty( $moved['error'] ) ) {
		wp_send_json_error( [ 'message' => $moved['error'] ?? 'Upload failed.' ] );
	}

	// Left unattached until the product is saved, which parents it.
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

add_action( 'wp_ajax_site_pulse_store_category_add', 'sp_store_ajax_category_add' );
function sp_store_ajax_category_add(): void {
	sp_store_products_gate();
	$name = trim( sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) ) );
	if ( $name === '' ) wp_send_json_error( [ 'message' => 'Type a name for the category.' ] );

	$existing = term_exists( $name, 'bp_product_cat' );
	$term     = $existing ?: wp_insert_term( $name, 'bp_product_cat', [ 'parent' => (int) ( $_POST['parent'] ?? 0 ) ] );
	if ( is_wp_error( $term ) ) wp_send_json_error( [ 'message' => $term->get_error_message() ] );

	wp_send_json_success( [
		'id'         => (int) ( is_array( $term ) ? $term['term_id'] : $term ),
		'categories' => sp_store_category_choices(),
	] );
}


/*--------------------------------------------------------------
# AJAX — Inventory
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_store_inventory', 'sp_store_ajax_inventory' );
function sp_store_ajax_inventory(): void {
	sp_store_products_gate();
	wp_send_json_success( [ 'rows' => sp_store_query_inventory( [
		'search' => sanitize_text_field( wp_unslash( (string) ( $_POST['search'] ?? '' ) ) ),
		'filter' => sanitize_key( (string) ( $_POST['filter'] ?? '' ) ),
	] ) ] );
}

add_action( 'wp_ajax_site_pulse_store_inventory_adjust', 'sp_store_ajax_inventory_adjust' );
function sp_store_ajax_inventory_adjust(): void {
	$me  = sp_store_products_gate();
	$vid = (int) ( $_POST['variant_id'] ?? 0 );

	// The warning level rides along with any adjustment, and can be saved on its own.
	if ( isset( $_POST['low_stock_at'] ) && $_POST['low_stock_at'] !== '' ) {
		global $wpdb;
		$wpdb->update( sp_store_table( 'variants' ), [ 'low_stock_at' => max( 0, (int) $_POST['low_stock_at'] ) ], [ 'id' => $vid ] );
		sp_store_flush_variant_cache();
	}

	$mode = sanitize_key( (string) ( $_POST['mode'] ?? '' ) );
	if ( $mode !== '' ) {
		$res = sp_store_inventory_adjust( $vid, $mode, (int) ( $_POST['qty'] ?? 0 ), sanitize_text_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ) );
		if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );
	}

	$variant = sp_store_get_variant( $vid );
	if ( ! $variant ) wp_send_json_error( [ 'message' => 'That item no longer exists.' ] );
	$variant['post_title'] = get_the_title( (int) $variant['product_id'] );

	if ( function_exists( 'site_pulse_log' ) && $mode !== '' ) {
		site_pulse_log( 'store_inventory', 'Stock ' . $mode . ': ' . $variant['post_title'] . ' (' . sp_store_variant_label( $variant ) . ')', [ 'variant_id' => $vid, 'user_id' => $me ] );
	}
	wp_send_json_success( [ 'message' => 'Stock updated.', 'row' => sp_store_inventory_row( $variant ) ] );
}
