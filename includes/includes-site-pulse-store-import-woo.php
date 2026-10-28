<?php
/* Battle Plan Web Design — Site Pulse: Store Import from WooCommerce

/*--------------------------------------------------------------
Moves a WooCommerce shop into the Store: products, membership plans, orders and
memberships. Customers come along for free — they are already WordPress users,
with their passwords, and keep them.

READS WOOCOMMERCE'S TABLES DIRECTLY. Nothing here calls a Woo function, so it runs
with WooCommerce switched OFF, which it has to be: Woo and the Store both claim
/shop/. Woo's data is never written to; deleting Woo is a separate, later step.

RE-RUNNABLE. Everything it creates remembers the Woo id it came from (product meta
_sp_store_woo_id, order number "WC-{id}", the map option sp_store_woo_import_map),
so a second run fills gaps and changes nothing it already made.

QUIET. Imported orders do not fire sp_store_order_paid — no receipts, no staff
alerts, no stock movement, no membership grants (memberships are imported as they
stand). The Stripe PaymentIntent id is carried over, so a refund issued from Site
Pulse on an imported order still reaches Stripe.

Run it over SSH on the site, dry run first:
  wp eval 'print_r( sp_store_import_woo( [ "dry_run" => true ] ) );'
  wp eval 'print_r( sp_store_import_woo() );'

Supports simple and variable products (attributes become options, variations
become variants). Skips failed / pending / cancelled / draft orders: no money moved.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Entry Point
# Helpers
# Products
# Membership Plans
# Orders
# Memberships & Accounts
# Settings
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Entry Point
--------------------------------------------------------------*/

/**
 * @param array $args dry_run (bool) — count and report, write nothing.
 *                    order_statuses — Woo statuses to import (without "wc-").
 * @return array a report: what was found, made, skipped, and anything to look at.
 */
function sp_store_import_woo( array $args = [] ): array {
	$dry = ! empty( $args['dry_run'] );
	$r   = [ 'dry_run' => $dry, 'products' => [], 'plans' => [], 'orders' => [ 'imported' => 0, 'skipped' => 0, 'existing' => 0 ], 'memberships' => [ 'imported' => 0, 'existing' => 0 ], 'accounts' => 0, 'settings' => [], 'notes' => [] ];

	if ( ! function_exists( 'sp_store_active' ) || ! sp_store_active() ) {
		$r['notes'][] = 'The Store module is not switched on, so nothing can be imported yet.';
		return $r;
	}
	sp_store_install_db();

	$map = get_option( 'sp_store_woo_import_map', [] );
	$map = is_array( $map ) ? $map + [ 'products' => [], 'plans' => [], 'orders' => [] ] : [ 'products' => [], 'plans' => [], 'orders' => [] ];

	sp_store_import_woo_products( $dry, $map, $r );
	sp_store_import_woo_plans( $dry, $map, $r );
	sp_store_import_woo_orders( $dry, $map, $r, (array) ( $args['order_statuses'] ?? [ 'completed', 'processing', 'on-hold', 'refunded' ] ) );
	sp_store_import_woo_memberships( $dry, $map, $r );
	sp_store_import_woo_settings( $dry, $r );

	if ( ! $dry ) update_option( 'sp_store_woo_import_map', $map, false );
	return $r;
}


/*--------------------------------------------------------------
# Helpers
--------------------------------------------------------------*/

function sp_store_woo_meta( int $post_id, string $key ) {
	global $wpdb;
	$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post_id, $key ) );
	return $v === null ? null : maybe_unserialize( $v );
}

function sp_store_woo_order_meta( int $order_id, string $key ): string {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s LIMIT 1", $order_id, $key ) );
}

function sp_store_woo_item_meta( int $item_id ): array {
	global $wpdb;
	$out = [];
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d", $item_id ), ARRAY_A ) ?: [] as $m ) {
		$out[ $m['meta_key'] ] = $m['meta_value'];
	}
	return $out;
}

/** A Woo product's category names, straight from the term tables (no taxonomy registration needed). */
function sp_store_woo_product_cats( int $product_id ): array {
	global $wpdb;
	return $wpdb->get_col( $wpdb->prepare(
		"SELECT t.name FROM {$wpdb->term_relationships} tr
		   JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		   JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		  WHERE tr.object_id = %d AND tt.taxonomy = 'product_cat'",
		$product_id
	) ) ?: [];
}

/** Dollars-as-string (Woo stores "2995" or "19.175") to integer cents. */
function sp_store_woo_cents( $v ): int {
	return (int) round( (float) $v * 100 );
}

/** Woo's weight unit to grams. */
function sp_store_woo_grams( $weight ): int {
	$w = (float) $weight;
	if ( $w <= 0 ) return 0;
	$unit = (string) get_option( 'woocommerce_weight_unit', 'lbs' );
	$factor = [ 'lbs' => 453.592, 'kg' => 1000, 'oz' => 28.3495, 'g' => 1 ][ $unit ] ?? 453.592;
	return (int) round( $w * $factor );
}

/** A local uploads URL to its file path, or '' when it is not on this site. */
function sp_store_woo_url_to_path( string $url ): string {
	$up = wp_get_upload_dir();
	$base = preg_replace( '#^https?:#', '', $up['baseurl'] );
	$u    = preg_replace( '#^https?:#', '', $url );
	if ( strpos( $u, $base ) !== 0 ) return '';
	$path = $up['basedir'] . substr( $u, strlen( $base ) );
	return is_file( $path ) ? $path : '';
}


/*--------------------------------------------------------------
# Products
--------------------------------------------------------------*/

function sp_store_import_woo_products( bool $dry, array &$map, array &$r ): void {
	global $wpdb;
	$woo = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status NOT IN ('trash','auto-draft') ORDER BY menu_order, ID", ARRAY_A ) ?: [];

	foreach ( $woo as $p ) {
		$wid = (int) $p['ID'];
		$existing = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sp_store_woo_id' AND meta_value = %s LIMIT 1", (string) $wid
		) );
		if ( $existing ) {
			$map['products'][ $wid ] = $existing;
			$r['products'][] = [ 'woo' => $wid, 'title' => $p['post_title'], 'store' => $existing, 'status' => 'already imported' ];
			continue;
		}

		$type = (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT t.slug FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			   JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tr.object_id = %d AND tt.taxonomy = 'product_type' LIMIT 1", $wid
		) ) ?: 'simple';

		$virtual = sp_store_woo_meta( $wid, '_virtual' ) === 'yes';
		$row = [ 'woo' => $wid, 'title' => $p['post_title'], 'type' => $type, 'ships' => ! $virtual, 'status' => $p['post_status'] ];

		if ( $dry ) { $r['products'][] = $row + [ 'store' => 0, 'action' => 'would import' ]; continue; }

		// ---- The post. Woo themes usually put the selling copy in the SHORT description
		// (excerpt) and leave the long one empty; whichever has text becomes the body. When the
		// excerpt BECOMES the body it is not also kept as the excerpt — the product page shows
		// the excerpt beside the buy button and the body below it, so it would print twice.
		$has_body = trim( (string) $p['post_content'] ) !== '';
		$content  = $has_body ? $p['post_content'] : $p['post_excerpt'];
		$excerpt  = $has_body ? $p['post_excerpt'] : '';
		$new = wp_insert_post( wp_slash( [
			'post_type'    => SITE_PULSE_STORE_CPT,
			'post_status'  => $p['post_status'] === 'publish' ? 'publish' : 'draft',
			'post_title'   => $p['post_title'],
			'post_name'    => $p['post_name'],
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'menu_order'   => (int) $p['menu_order'],
			'post_date'    => $p['post_date'],
			'post_date_gmt'=> $p['post_date_gmt'],
			'post_author'  => (int) $p['post_author'],
		] ), true );
		if ( is_wp_error( $new ) ) { $r['notes'][] = 'Product "' . $p['post_title'] . '": ' . $new->get_error_message(); continue; }
		$new = (int) $new;
		update_post_meta( $new, '_sp_store_woo_id', (string) $wid );
		$map['products'][ $wid ] = $new;

		// SEO fields the framework already kept on the Woo product.
		foreach ( [ '_bp_seo_title', '_bp_seo_desc' ] as $k ) {
			$v = sp_store_woo_meta( $wid, $k );
			if ( is_string( $v ) && $v !== '' ) update_post_meta( $new, $k, $v );
		}

		// ---- Categories, by name, created if missing. Read from the term tables directly:
		// with Woo switched off its product_cat taxonomy is not registered, and
		// wp_get_object_terms() then returns an error — which silently dropped every
		// category on the first real run (Aerial Spiral).
		$cats = sp_store_woo_product_cats( $wid );
		if ( taxonomy_exists( 'bp_product_cat' ) ) {
			$ids = [];
			foreach ( $cats as $name ) {
				if ( $name === 'Uncategorized' ) continue;
				$t = term_exists( $name, 'bp_product_cat' ) ?: wp_insert_term( $name, 'bp_product_cat' );
				if ( ! is_wp_error( $t ) ) $ids[] = (int) ( is_array( $t ) ? $t['term_id'] : $t );
			}
			if ( $ids ) wp_set_object_terms( $new, $ids, 'bp_product_cat' );
		}

		// ---- Photos: featured image first, then the gallery.
		$gallery = [];
		$thumb   = (int) sp_store_woo_meta( $wid, '_thumbnail_id' );
		if ( $thumb ) $gallery[] = [ 'id' => $thumb, 'tag' => '' ];
		foreach ( array_filter( array_map( 'intval', explode( ',', (string) sp_store_woo_meta( $wid, '_product_image_gallery' ) ) ) ) as $gid ) {
			$gallery[] = [ 'id' => $gid, 'tag' => '' ];
		}

		// ---- Options + variants.
		$variants = [];
		$options  = [];
		if ( $type === 'variable' ) {
			$attrs = (array) sp_store_woo_meta( $wid, '_product_attributes' );
			$keys  = [];
			foreach ( $attrs as $key => $a ) {
				if ( empty( $a['is_variation'] ) ) continue;
				$label = ! empty( $a['is_taxonomy'] )
					? (string) ( $wpdb->get_var( $wpdb->prepare( "SELECT attribute_label FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s", substr( $key, 3 ) ) ) ?: ucfirst( substr( $key, 3 ) ) )
					: (string) $a['name'];
				$keys[]    = [ 'key' => 'attribute_' . sanitize_title( $key ), 'tax' => ! empty( $a['is_taxonomy'] ) ? $key : '', 'name' => $label, 'values' => [] ];
				if ( count( $keys ) === 3 ) break;
			}
			$vars = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_parent = %d AND post_status <> 'trash' ORDER BY menu_order, ID", $wid ), ARRAY_A ) ?: [];
			foreach ( $vars as $v ) {
				$vid = (int) $v['ID'];
				$row_v = sp_store_import_woo_variant_row( $vid );
				foreach ( $keys as $i => $k ) {
					$val = (string) sp_store_woo_meta( $vid, $k['key'] );
					if ( $k['tax'] && $val !== '' ) {
						$term = get_term_by( 'slug', $val, $k['tax'] );
						$val  = $term ? $term->name : $val;
					}
					$row_v[ 'option' . ( $i + 1 ) ] = $val;
					if ( $val !== '' && ! in_array( $val, $keys[ $i ]['values'], true ) ) $keys[ $i ]['values'][] = $val;
				}
				$variants[] = $row_v;
			}
			foreach ( $keys as $k ) $options[] = [ 'name' => $k['name'], 'values' => $k['values'] ];
		} else {
			$variants[] = sp_store_import_woo_variant_row( $wid );
		}

		$warn = sp_store_save_product_data( $new, [
			'options'  => $options,
			'gallery'  => $gallery,
			'variants' => $variants,
			'ships'    => ! $virtual,
		] );
		if ( $gallery ) set_post_thumbnail( $new, (int) $gallery[0]['id'] );
		foreach ( $warn as $w ) $r['notes'][] = 'Product "' . $p['post_title'] . '": ' . $w;

		// ---- Downloadable files, copied into the private folder.
		$files = sp_store_woo_meta( $wid, '_downloadable_files' );
		foreach ( is_array( $files ) ? $files : [] as $f ) {
			$path = sp_store_woo_url_to_path( (string) ( $f['file'] ?? '' ) );
			if ( $path === '' ) { $r['notes'][] = 'Product "' . $p['post_title'] . '": download "' . ( $f['name'] ?? '' ) . '" is not a file on this site; add it by hand.'; continue; }
			$name = (string) ( $f['name'] ?? '' );
			$res  = function_exists( 'sp_store_add_product_download_from_path' )
				? sp_store_add_product_download_from_path( $new, $path, $name !== '' ? $name . '.' . pathinfo( $path, PATHINFO_EXTENSION ) : '' )
				: new WP_Error( 'x', 'downloads module missing' );
			if ( is_wp_error( $res ) ) $r['notes'][] = 'Product "' . $p['post_title'] . '": ' . $res->get_error_message();
			else $r['notes'][] = 'Copied "' . basename( $path ) . '" into private downloads. The ORIGINAL is still public in /uploads — delete it once checked.';
		}

		$r['products'][] = $row + [ 'store' => $new, 'action' => 'imported' ];
	}
}

/** One variant row for sp_store_save_product_data() from a Woo product or variation post. */
function sp_store_import_woo_variant_row( int $woo_id ): array {
	$manage = sp_store_woo_meta( $woo_id, '_manage_stock' ) === 'yes';
	$stock  = sp_store_woo_meta( $woo_id, '_stock' );
	return [
		'id'              => 0,
		'sku'             => (string) sp_store_woo_meta( $woo_id, '_sku' ),
		'price'           => (string) sp_store_woo_meta( $woo_id, '_regular_price' ),
		'sale_price'      => (string) sp_store_woo_meta( $woo_id, '_sale_price' ),
		'track_stock'     => $manage,
		'allow_backorder' => in_array( (string) sp_store_woo_meta( $woo_id, '_backorders' ), [ 'yes', 'notify' ], true ),
		'stock'           => $manage && $stock !== null && $stock !== '' ? (string) (int) $stock : '',
		'weight_grams'    => sp_store_woo_grams( sp_store_woo_meta( $woo_id, '_weight' ) ),
	];
}


/*--------------------------------------------------------------
# Membership Plans (WooCommerce Memberships)
--------------------------------------------------------------*/

function sp_store_import_woo_plans( bool $dry, array &$map, array &$r ): void {
	global $wpdb;
	if ( ! function_exists( 'sp_member_save_plan' ) ) return;
	$plans = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_type = 'wc_membership_plan' AND post_status = 'publish'", ARRAY_A ) ?: [];
	if ( ! $plans ) return;

	if ( ! $dry && sp_store_get_setting( 'feature_memberships', '' ) !== '1' ) {
		sp_store_set_setting( 'feature_memberships', '1' );
		$r['settings'][] = 'Switched on Memberships (Extras), because the shop sells them.';
	}

	$rules = maybe_unserialize( get_option( 'wc_memberships_rules', [] ) );
	foreach ( $plans as $p ) {
		$wid = (int) $p['ID'];

		// What it unlocks: a content_restriction rule with no object ids locks a whole post
		// type (a section); one with ids locks those posts.
		$sections = [];
		$pages    = [];
		foreach ( is_array( $rules ) ? $rules : [] as $rule ) {
			if ( (int) ( $rule['membership_plan_id'] ?? 0 ) !== $wid || ( $rule['rule_type'] ?? '' ) !== 'content_restriction' ) continue;
			if ( ( $rule['content_type'] ?? '' ) !== 'post_type' ) continue;
			$ids = array_map( 'intval', (array) ( $rule['object_ids'] ?? [] ) );
			if ( $ids ) $pages = array_merge( $pages, $ids );
			else        $sections[] = (string) $rule['content_type_name'];
		}

		// Length: Woo keeps "unlimited" as no _access_length, else e.g. "12 months".
		$len   = (string) sp_store_woo_meta( $wid, '_access_length' );
		$unit  = 'forever';
		$n     = 0;
		if ( preg_match( '/^(\d+)\s*(day|week|month|year)s?$/', $len, $m ) ) {
			$n    = (int) $m[1] * ( $m[2] === 'week' ? 7 : 1 );
			$unit = $m[2] === 'week' ? 'days' : $m[2] . 's';
		}

		$row = [ 'woo' => $wid, 'name' => $p['post_title'], 'sections' => $sections, 'pages' => count( $pages ), 'length' => $len ?: 'unlimited' ];

		if ( isset( $map['plans'][ $wid ] ) && sp_member_get_plan( (int) $map['plans'][ $wid ] ) ) {
			$r['plans'][] = $row + [ 'store' => (int) $map['plans'][ $wid ], 'action' => 'already imported' ];
			continue;
		}
		if ( $dry ) { $r['plans'][] = $row + [ 'action' => 'would import' ]; continue; }

		$id = sp_member_save_plan( [ 'name' => $p['post_title'], 'length_unit' => $unit, 'length_n' => $n, 'sections' => $sections, 'billing' => 'once' ] );
		if ( is_wp_error( $id ) ) { $r['notes'][] = 'Plan "' . $p['post_title'] . '": ' . $id->get_error_message(); continue; }
		$map['plans'][ $wid ] = (int) $id;
		foreach ( array_unique( $pages ) as $pid ) {
			if ( get_post( $pid ) ) sp_member_set_post_plan( $pid, (int) $id, true );
		}

		// The products that sell it.
		foreach ( (array) sp_store_woo_meta( $wid, '_product_ids' ) as $wpid ) {
			$sp = (int) ( $map['products'][ (int) $wpid ] ?? 0 );
			if ( ! $sp ) continue;
			if ( (int) get_post_meta( $sp, '_sp_store_grants_plan', true ) ) {
				$r['notes'][] = 'Product #' . $sp . ' sold more than one Woo plan; it keeps the first. Check it in the product\'s Extras.';
				continue;
			}
			update_post_meta( $sp, '_sp_store_grants_plan', (int) $id );
		}

		$r['plans'][] = $row + [ 'store' => (int) $id, 'action' => 'imported' ];
	}
}


/*--------------------------------------------------------------
# Orders (HPOS — wc_orders)
--------------------------------------------------------------*/

function sp_store_import_woo_orders( bool $dry, array &$map, array &$r, array $statuses ): void {
	global $wpdb;
	$t = $wpdb->prefix;
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t . 'wc_orders' ) ) ) {
		$r['notes'][] = 'This shop keeps orders the old (non-HPOS) way, which this importer does not read yet. Orders were skipped.';
		return;
	}

	$in    = implode( ',', array_map( static fn( $s ) => "'" . esc_sql( 'wc-' . $s ) . "'", $statuses ) );
	$woo   = $wpdb->get_results( "SELECT * FROM {$t}wc_orders WHERE type = 'shop_order' ORDER BY id", ARRAY_A ) ?: [];
	$table = sp_store_table( 'orders' );

	foreach ( $woo as $o ) {
		$wid    = (int) $o['id'];
		$number = 'WC-' . $wid;
		if ( strpos( $in, "'" . $o['status'] . "'" ) === false ) { $r['orders']['skipped']++; continue; }

		$have = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE number = %s", $number ) );
		if ( $have ) { $map['orders'][ $wid ] = $have; $r['orders']['existing']++; continue; }
		if ( $dry ) { $r['orders']['imported']++; continue; }

		$ops   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t}wc_order_operational_data WHERE order_id = %d", $wid ), ARRAY_A ) ?: [];
		$addrs = [];
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t}wc_order_addresses WHERE order_id = %d", $wid ), ARRAY_A ) ?: [] as $a ) $addrs[ $a['address_type'] ] = $a;
		$bill = $addrs['billing'] ?? [];
		$ship = ( ! empty( $addrs['shipping']['address_1'] ) ) ? $addrs['shipping'] : $bill;

		$items    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t}woocommerce_order_items WHERE order_id = %d", $wid ), ARRAY_A ) ?: [];
		$lines    = [];
		$ship_name = '';
		$count    = 0;
		$subtotal = 0;
		$ships    = false;
		foreach ( $items as $it ) {
			$meta = sp_store_woo_item_meta( (int) $it['order_item_id'] );
			if ( $it['order_item_type'] === 'shipping' ) { $ship_name = (string) $it['order_item_name']; continue; }
			if ( $it['order_item_type'] !== 'line_item' ) continue;
			$pid   = (int) ( $map['products'][ (int) ( $meta['_product_id'] ?? 0 ) ] ?? 0 );
			$var   = $pid ? ( sp_store_get_variants( $pid )[0] ?? null ) : null;
			$qty   = max( 1, (int) ( $meta['_qty'] ?? 1 ) );
			$line  = sp_store_woo_cents( $meta['_line_total'] ?? 0 );
			$req   = $var ? (int) $var['requires_shipping'] : 1;
			$ships = $ships || $req;
			$count += $qty;
			$subtotal += sp_store_woo_cents( $meta['_line_subtotal'] ?? $meta['_line_total'] ?? 0 );
			$lines[] = [
				'variant_id' => $var ? (int) $var['id'] : 0, 'product_id' => $pid, 'sku' => $var ? (string) $var['sku'] : '',
				'name' => (string) $it['order_item_name'], 'variant_title' => '', 'qty' => $qty,
				'unit_price_cents' => (int) round( $line / $qty ), 'subtotal_cents' => $line, 'total_cents' => $line,
				'requires_shipping' => $req, 'taxable' => 1,
			];
		}

		// Money. Refunds are child orders of type shop_order_refund with negative totals.
		$total    = sp_store_woo_cents( $o['total_amount'] );
		$refunds  = $wpdb->get_results( $wpdb->prepare( "SELECT id, total_amount, date_created_gmt FROM {$t}wc_orders WHERE type = 'shop_order_refund' AND parent_order_id = %d", $wid ), ARRAY_A ) ?: [];
		$refunded = 0;
		foreach ( $refunds as $rf ) $refunded += abs( sp_store_woo_cents( $rf['total_amount'] ) );
		$pay = $refunded <= 0 ? 'paid' : ( $refunded >= $total ? 'refunded' : 'partially_refunded' );

		$woo_status = substr( (string) $o['status'], 3 );
		$status = $woo_status === 'refunded' ? 'cancelled' : 'open';
		$fulfil = ( $woo_status === 'completed' || ! $ships ) ? 'fulfilled' : 'unfulfilled';
		$placed = (string) ( $ops['date_paid_gmt'] ?? '' ) ?: (string) $o['date_created_gmt'];
		$name   = trim( ( $bill['first_name'] ?? '' ) . ' ' . ( $bill['last_name'] ?? '' ) );

		$pi = sp_store_woo_order_meta( $wid, '_stripe_intent_id' );
		if ( strpos( $pi, 'pi_' ) !== 0 ) $pi = strpos( (string) $o['transaction_id'], 'pi_' ) === 0 ? (string) $o['transaction_id'] : '';

		$ok = $wpdb->insert( $table, [
			'number'             => $number,
			'user_id'            => (int) $o['customer_id'],
			'customer_name'      => $name,
			'customer_email'     => (string) ( $o['billing_email'] ?: ( $bill['email'] ?? '' ) ),
			'customer_phone'     => (string) ( $bill['phone'] ?? '' ),
			'status'             => $status,
			'payment_status'     => $pay,
			'fulfillment_status' => $fulfil,
			'item_count'         => $count,
			'subtotal_cents'     => $subtotal,
			'discount_cents'     => sp_store_woo_cents( $ops['discount_total_amount'] ?? 0 ),
			'shipping_cents'     => sp_store_woo_cents( $ops['shipping_total_amount'] ?? 0 ),
			'tax_cents'          => sp_store_woo_cents( $o['tax_amount'] ),
			'total_cents'        => $total,
			'paid_cents'         => $total,
			'refunded_cents'     => $refunded,
			'currency'           => strtoupper( (string) $o['currency'] ?: 'USD' ),
			'ship_name'          => trim( ( $ship['first_name'] ?? '' ) . ' ' . ( $ship['last_name'] ?? '' ) ),
			'ship_address1'      => (string) ( $ship['address_1'] ?? '' ),
			'ship_address2'      => (string) ( $ship['address_2'] ?? '' ),
			'ship_city'          => (string) ( $ship['city'] ?? '' ),
			'ship_state'         => (string) ( $ship['state'] ?? '' ),
			'ship_zip'           => (string) ( $ship['postcode'] ?? '' ),
			'ship_country'       => substr( (string) ( $ship['country'] ?? 'US' ) ?: 'US', 0, 2 ),
			'bill_name'          => $name,
			'bill_address1'      => (string) ( $bill['address_1'] ?? '' ),
			'bill_city'          => (string) ( $bill['city'] ?? '' ),
			'bill_state'         => (string) ( $bill['state'] ?? '' ),
			'bill_zip'           => (string) ( $bill['postcode'] ?? '' ),
			'shipping_method'    => mb_substr( $ship_name, 0, 120 ),
			'provider'           => 'stripe',
			'provider_checkout'  => null,
			'provider_payment'   => $pi,
			'provider_customer'  => sp_store_woo_order_meta( $wid, '_stripe_customer_id' ),
			'source'             => 'web',
			'customer_note'      => (string) $o['customer_note'],
			'staff_notes'        => 'Imported from WooCommerce order #' . $wid . ' (' . ( $o['payment_method_title'] ?: $o['payment_method'] ) . ').',
			'placed_at'          => $placed,
			'cancelled_at'       => $status === 'cancelled' ? ( $refunds[0]['date_created_gmt'] ?? $placed ) : null,
			'cancel_reason'      => $status === 'cancelled' ? 'Refunded in WooCommerce' : '',
			'shipped_at'         => $woo_status === 'completed' ? ( (string) ( $ops['date_completed_gmt'] ?? '' ) ?: null ) : null,
			'created_at'         => (string) $o['date_created_gmt'],
			'updated_at'         => current_time( 'mysql', true ),
		] );
		if ( ! $ok ) { $r['notes'][] = 'Order #' . $wid . ' could not be written: ' . $wpdb->last_error; continue; }
		$oid = (int) $wpdb->insert_id;
		$map['orders'][ $wid ] = $oid;

		foreach ( $lines as $l ) {
			$wpdb->insert( sp_store_table( 'order_items' ), $l + [ 'order_id' => $oid, 'qty_fulfilled' => $fulfil === 'fulfilled' ? $l['qty'] : 0, 'created_at' => (string) $o['date_created_gmt'] ] );
		}

		// The ledger: the payment, and each refund — keyed so a re-run cannot double them.
		$wpdb->insert( sp_store_table( 'payments' ), [
			'order_id' => $oid, 'provider' => 'stripe', 'kind' => 'payment', 'amount_cents' => $total,
			'currency' => strtoupper( (string) $o['currency'] ?: 'USD' ), 'status' => 'succeeded',
			'provider_object' => $pi !== '' ? $pi : 'woo-' . $wid, 'description' => 'WooCommerce order #' . $wid,
			'created_at' => $placed, 'updated_at' => $placed,
		] );
		foreach ( $refunds as $rf ) {
			$wpdb->insert( sp_store_table( 'payments' ), [
				'order_id' => $oid, 'provider' => 'stripe', 'kind' => 'refund', 'amount_cents' => -abs( sp_store_woo_cents( $rf['total_amount'] ) ),
				'currency' => strtoupper( (string) $o['currency'] ?: 'USD' ), 'status' => 'succeeded',
				'provider_object' => sp_store_woo_order_meta( (int) $rf['id'], '_stripe_refund_id' ) ?: 'woo-refund-' . $rf['id'],
				'description' => 'Refund on WooCommerce order #' . $wid, 'reason' => sp_store_woo_order_meta( (int) $rf['id'], '_refund_reason' ),
				'created_at' => $rf['date_created_gmt'], 'updated_at' => $rf['date_created_gmt'],
			] );
		}
		$r['orders']['imported']++;
	}

	// Top Sellers needs the sold counts the imported orders imply.
	if ( ! $dry ) foreach ( array_unique( $map['products'] ) as $sp ) sp_store_refresh_sold_count( (int) $sp );
}


/*--------------------------------------------------------------
# Memberships & Accounts
--------------------------------------------------------------*/

function sp_store_import_woo_memberships( bool $dry, array &$map, array &$r ): void {
	global $wpdb;
	if ( ! function_exists( 'sp_member_get_plan' ) ) return;
	$rows  = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_type = 'wc_user_membership'", ARRAY_A ) ?: [];
	$table = sp_store_table( 'memberships' );
	$touch = [];

	foreach ( $rows as $m ) {
		$plan = (int) ( $map['plans'][ (int) $m['post_parent'] ] ?? 0 );
		$uid  = (int) $m['post_author'];
		if ( ! $uid || ( ! $plan && ! $dry ) ) { $r['notes'][] = 'Membership #' . $m['ID'] . ' has no plan or user to attach to; skipped.'; continue; }
		$touch[ $uid ] = true;

		if ( ! $dry && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE user_id = %d AND plan_id = %d", $uid, $plan ) ) ) { $r['memberships']['existing']++; continue; }
		if ( $dry ) { $r['memberships']['imported']++; continue; }

		$start  = (string) sp_store_woo_meta( (int) $m['ID'], '_start_date' ) ?: (string) $m['post_date_gmt'];
		$end    = (string) sp_store_woo_meta( (int) $m['ID'], '_end_date' );
		$status = in_array( $m['post_status'], [ 'wcm-active', 'wcm-complimentary', 'wcm-free_trial', 'wcm-pending' ], true ) ? 'active'
			: ( $m['post_status'] === 'wcm-expired' ? 'active' : 'cancelled' );   // expired = active row with a past end date
		$worder = (int) sp_store_woo_meta( (int) $m['ID'], '_order_id' );
		$user   = get_userdata( $uid );

		$wpdb->insert( $table, [
			'plan_id'    => $plan,
			'user_id'    => $uid,
			'email'      => $user ? (string) $user->user_email : '',
			'status'     => $status,
			'starts_at'  => $start,
			'ends_at'    => $end !== '' ? $end : null,
			'source'     => 'import',
			'order_id'   => (int) ( $map['orders'][ $worder ] ?? 0 ),
			'note'       => 'Moved from WooCommerce Memberships #' . $m['ID'],
			'created_at' => $start,
			'updated_at' => current_time( 'mysql', true ),
		] );
		$r['memberships']['imported']++;
	}

	// Accounts: customer-only users become members, so My Account works for them and the
	// staff doors stay shut. Anyone holding any other role is left exactly as they are.
	if ( ! $dry && ! wp_roles()->is_role( 'bp_member' ) ) add_role( 'bp_member', __( 'Member', 'battleplan' ), [ 'read' => true ] );
	$cap = $wpdb->prefix . 'capabilities';
	$users = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $cap ), ARRAY_A ) ?: [];
	foreach ( $users as $u ) {
		$roles = array_keys( array_filter( (array) maybe_unserialize( $u['meta_value'] ) ) );
		if ( ! $roles || array_diff( $roles, [ 'customer', 'bp_subscriber', 'subscriber', 'bp_member' ] ) ) continue;
		if ( $roles === [ 'bp_member' ] ) continue;
		if ( ! $dry ) ( new WP_User( (int) $u['user_id'] ) )->set_role( 'bp_member' );
		$r['accounts']++;
	}
}


/*--------------------------------------------------------------
# Settings
--------------------------------------------------------------*/

/** Carry over what can be read for certain: currency, and a single flat tax rate. */
function sp_store_import_woo_settings( bool $dry, array &$r ): void {
	global $wpdb;
	$rates = $wpdb->get_results( "SELECT tax_rate, tax_rate_shipping, tax_rate_state FROM {$wpdb->prefix}woocommerce_tax_rates", ARRAY_A ) ?: [];
	if ( get_option( 'woocommerce_calc_taxes' ) === 'yes' && count( $rates ) === 1 && $rates[0]['tax_rate_state'] === '' ) {
		$r['settings'][] = 'Tax: flat ' . (float) $rates[0]['tax_rate'] . '%' . ( $rates[0]['tax_rate_shipping'] ? ', on shipping too' : ', not on shipping' ) . '.';
		if ( ! $dry ) {
			sp_store_set_setting( 'tax_mode', 'flat' );
			sp_store_set_setting( 'tax_flat_rate', (string) (float) $rates[0]['tax_rate'] );
			sp_store_set_setting( 'tax_shipping', $rates[0]['tax_rate_shipping'] ? '1' : '' );
		}
	} elseif ( $rates ) {
		$r['notes'][] = 'Woo had ' . count( $rates ) . ' tax rates; set tax by hand in Settings → Store.';
	}

	$coupons = $wpdb->get_col( "SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status = 'publish'" ) ?: [];
	if ( $coupons ) $r['notes'][] = 'Woo coupons to re-create as Stripe promotion codes: ' . implode( ', ', $coupons ) . '.';
}
