<?php
/* Battle Plan Web Design — Site Pulse: Store Orders

/*--------------------------------------------------------------
Store → Orders. What staff do with an order once it is paid: ship it, refund
it, note it, and file it away.

EVERY ACTION IS A PLAIN FUNCTION. sp_store_fulfill_order(), sp_store_refund_order()
and the rest take ids and values, never $_POST, and the AJAX handlers at the
bottom are thin wrappers around them. Whichever screen starts an action —
Site Pulse now, a wp-admin page later — it runs through the same function and
lands the same way. That is the same rule the product save follows.

MONEY MOVES AT THE PROVIDER FIRST. A refund is sent to Stripe, and only the
refund Stripe hands back is written down. Refunds issued from the Stripe
dashboard arrive by webhook and go through the SAME recorder, keyed on the
provider's refund id, so a refund can never be counted twice however it came
in. The order's refunded total is always re-added from the payments ledger,
never incremented — an increment is how a redelivered event double-counts.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Reading & Formatting
# Fulfillment
# Refunds
# Notes, Archive, Cancel
# AJAX
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Reading & Formatting
--------------------------------------------------------------*/

/**
 * Money as plain text. sp_store_money() is written for HTML pages and returns
 * entities for £ and €; email bodies and Site Pulse both escape what they are
 * given, so they need the real characters or they print "&pound;".
 */
function sp_store_money_text( int $cents, string $currency = '' ): string {
	return html_entity_decode( sp_store_money( $cents, $currency ), ENT_QUOTES, 'UTF-8' );
}

/** A stored UTC datetime as the site's local time, or '' when there is none. */
function sp_store_local_time( ?string $utc, string $format = 'M j, Y g:i a' ): string {
	if ( ! $utc || $utc === '0000-00-00 00:00:00' ) return '';
	return get_date_from_gmt( $utc, $format );
}

/** The carriers offered on the ship form, with how to build a tracking link for each. */
function sp_store_carriers(): array {
	return [
		'ups'   => [ 'label' => 'UPS',   'url' => 'https://www.ups.com/track?tracknum=%s' ],
		'usps'  => [ 'label' => 'USPS',  'url' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=%s' ],
		'fedex' => [ 'label' => 'FedEx', 'url' => 'https://www.fedex.com/fedextrack/?trknbr=%s' ],
		'dhl'   => [ 'label' => 'DHL',   'url' => 'https://www.dhl.com/us-en/home/tracking.html?tracking-id=%s' ],
		'other' => [ 'label' => 'Other', 'url' => '' ],
	];
}

/**
 * The tracking link for a shipment. A link typed by staff wins — freight carriers
 * (R+L, Estes) have no URL pattern worth guessing — otherwise it is built from the
 * carrier's pattern, and is '' when neither applies.
 */
function sp_store_tracking_link( string $carrier, string $number, string $typed = '' ): string {
	if ( $typed !== '' ) return esc_url_raw( $typed );
	$pattern = sp_store_carriers()[ $carrier ]['url'] ?? '';
	if ( $pattern === '' || $number === '' ) return '';
	return sprintf( $pattern, rawurlencode( $number ) );
}

/** Store → Orders in Site Pulse, as a link — the same ?sp_panel= deep link Messages uses. */
function sp_store_orders_panel_url(): string {
	return home_url( '/site-pulse-dashboard/?sp_panel=store-orders' );
}

/** Does anything on this order actually have to be posted? */
function sp_store_order_ships( int $order_id ): bool {
	foreach ( sp_store_order_items( $order_id ) as $item ) {
		if ( (int) $item['requires_shipping'] ) return true;
	}
	return false;
}

/** How much of the money taken can still be given back, in cents. */
function sp_store_refundable_cents( array $order ): int {
	return max( 0, (int) $order['paid_cents'] - (int) $order['refunded_cents'] );
}

/**
 * The two badges an order wears, in plain words, each with a tone the screen colours.
 *
 * Three status columns are the right STORAGE, but nobody running a shop thinks in
 * them. This folds them into the two questions staff actually ask — has it been
 * paid, and what is left to do — and is the one place that wording lives.
 */
function sp_store_order_badges( array $o, bool $ships ): array {
	$payment = [
		'paid'               => [ 'Paid', 'success' ],
		'partially_refunded' => [ 'Part refunded', 'warning' ],
		'refunded'           => [ 'Refunded', 'muted' ],
		'failed'             => [ 'Payment failed', 'danger' ],
		'authorized'         => [ 'Authorized', 'warning' ],
	][ $o['payment_status'] ] ?? [ 'Unpaid', 'muted' ];

	if ( $o['status'] === 'pending' )                    $progress = [ 'Unfinished checkout', 'muted' ];
	elseif ( $o['status'] === 'cancelled' )              $progress = [ 'Cancelled', 'danger' ];
	elseif ( ! $ships )                                  $progress = [ 'Complete', 'success' ];
	elseif ( $o['fulfillment_status'] === 'fulfilled' )  $progress = [ 'Shipped', 'success' ];
	elseif ( $o['fulfillment_status'] === 'partially_fulfilled' ) $progress = [ 'Partly shipped', 'warning' ];
	elseif ( $o['fulfillment_status'] === 'returned' )   $progress = [ 'Returned', 'muted' ];
	else                                                 $progress = [ 'Needs shipping', 'warning' ];

	return [
		'payment'  => [ 'label' => $payment[0],  'tone' => $payment[1] ],
		'progress' => [ 'label' => $progress[0], 'tone' => $progress[1] ],
	];
}

/**
 * One line in the orders list.
 *
 * @param bool|null $ships whether anything on it is posted; null looks it up. The list
 *                         passes it in, from one query for the whole page.
 */
function sp_store_order_row( array $o, ?bool $ships = null ): array {
	$when  = $o['placed_at'] ?: $o['created_at'];
	$ships = $ships ?? sp_store_order_ships( (int) $o['id'] );
	return sp_store_order_badges( $o, $ships ) + [
		'ships'              => $ships,
		'archived'           => $o['status'] === 'archived',
		'id'                 => (int) $o['id'],
		'number'             => (string) $o['number'],
		'placed'             => sp_store_local_time( $when, 'M j, Y' ),
		'placed_time'        => sp_store_local_time( $when, 'g:i a' ),
		'customer'           => $o['customer_name'] !== '' ? (string) $o['customer_name'] : (string) $o['customer_email'],
		'email'              => (string) $o['customer_email'],
		'item_count'         => (int) $o['item_count'],
		'total'              => sp_store_money_text( (int) $o['total_cents'], (string) $o['currency'] ),
		'status'             => (string) $o['status'],
		'payment_status'     => (string) $o['payment_status'],
		'fulfillment_status' => (string) $o['fulfillment_status'],
		'status_label'       => sp_store_order_statuses()[ $o['status'] ] ?? $o['status'],
		'payment_label'      => sp_store_payment_statuses()[ $o['payment_status'] ] ?? $o['payment_status'],
		'fulfillment_label'  => sp_store_fulfillment_statuses()[ $o['fulfillment_status'] ] ?? $o['fulfillment_status'],
	];
}

/** Everything the order window shows, already formatted — the browser does no money maths. */
function sp_store_order_detail( array $o ): array {
	global $wpdb;
	$cur = (string) $o['currency'];

	$items = [];
	foreach ( sp_store_order_items( (int) $o['id'] ) as $it ) {
		$items[] = [
			'name'          => (string) $it['name'],
			'variant'       => ( $it['variant_title'] !== '' && $it['variant_title'] !== 'Default' ) ? (string) $it['variant_title'] : '',
			'sku'           => (string) $it['sku'],
			'qty'           => (int) $it['qty'],
			'qty_refunded'  => (int) $it['qty_refunded'],
			'unit'          => sp_store_money_text( (int) $it['unit_price_cents'], $cur ),
			'line'          => sp_store_money_text( (int) $it['total_cents'], $cur ),
			'ships'         => (int) $it['requires_shipping'] === 1,
		];
	}

	$payments = $wpdb->get_results( $wpdb->prepare(
		"SELECT kind, amount_cents, status, description, reason, created_at FROM " . sp_store_table( 'payments' ) . "
		  WHERE order_id = %d ORDER BY id ASC",
		(int) $o['id']
	), ARRAY_A ) ?: [];
	$ledger = [];
	foreach ( $payments as $p ) {
		$ledger[] = [
			'kind'   => [ 'refund' => 'Refund', 'renewal' => 'Renewal' ][ $p['kind'] ] ?? 'Payment',
			'amount' => sp_store_money_text( abs( (int) $p['amount_cents'] ), $cur ),
			'status' => (string) $p['status'],
			'note'   => (string) ( $p['reason'] ?: $p['description'] ),
			'when'   => sp_store_local_time( $p['created_at'] ),
		];
	}

	$ship_lines = array_values( array_filter( [
		(string) $o['ship_name'],
		(string) $o['ship_address1'],
		(string) $o['ship_address2'],
		trim( $o['ship_city'] . ( $o['ship_city'] !== '' && $o['ship_state'] !== '' ? ', ' : '' ) . $o['ship_state'] . ' ' . $o['ship_zip'] ),
	], static fn( $l ) => trim( $l ) !== '' ) );

	$carrier = (string) $o['carrier'];

	return sp_store_order_row( $o ) + [
		'is_pending'     => $o['status'] === 'pending',
		'is_live'        => in_array( $o['status'], [ 'open', 'archived' ], true ),
		'is_paid'        => in_array( $o['payment_status'], [ 'paid', 'partially_refunded' ], true ),
		'is_shipped'     => $o['fulfillment_status'] === 'fulfilled',
		'phone'          => (string) $o['customer_phone'],
		'placed_full'    => sp_store_local_time( $o['placed_at'] ?: $o['created_at'] ),
		'items'          => $items,
		'subtotal'       => sp_store_money_text( (int) $o['subtotal_cents'], $cur ),
		'discount'       => (int) $o['discount_cents'] ? sp_store_money_text( (int) $o['discount_cents'], $cur ) : '',
		'discount_code'  => (string) $o['discount_code'],
		'shipping'       => sp_store_money_text( (int) $o['shipping_cents'], $cur ),
		'shipping_method'=> (string) $o['shipping_method'],
		'tax'            => (int) $o['tax_cents'] ? sp_store_money_text( (int) $o['tax_cents'], $cur ) : '',
		'paid'           => sp_store_money_text( (int) $o['paid_cents'], $cur ),
		'refunded'       => (int) $o['refunded_cents'] ? sp_store_money_text( (int) $o['refunded_cents'], $cur ) : '',
		'refundable'     => number_format( sp_store_refundable_cents( $o ) / 100, 2, '.', '' ),
		'ship_to'        => $ship_lines,
		'ships'          => sp_store_order_ships( (int) $o['id'] ),
		'carrier'        => $carrier,
		'carrier_label'  => sp_store_carriers()[ $carrier ]['label'] ?? '',
		'tracking_number'=> (string) $o['tracking_number'],
		'tracking_url'   => (string) $o['tracking_url'],
		'shipped_at'     => sp_store_local_time( $o['shipped_at'] ),
		'customer_note'  => (string) $o['customer_note'],
		'staff_notes'    => (string) $o['staff_notes'],
		'cancel_reason'  => (string) $o['cancel_reason'],
		'payments'       => $ledger,
		'agreements'     => function_exists( 'sp_store_order_agreements_display' ) ? sp_store_order_agreements_display( (int) $o['id'] ) : [],
		'downloads'      => function_exists( 'sp_store_order_downloads' ) ? array_map( static fn( $d ) => $d['name'], sp_store_order_downloads( $o ) ) : [],
		'download_count' => (int) ( $o['downloads'] ?? 0 ),
		'can_refund'     => in_array( $o['payment_status'], [ 'paid', 'partially_refunded' ], true )
			&& sp_store_refundable_cents( $o ) > 0
			&& sp_store_provider_can_refund( (string) $o['provider'] ),
	];
}

/**
 * A page of orders for the list.
 *
 * `status` '' means every order EXCEPT pending ones. A pending order is a checkout
 * somebody started and walked away from; mixing those into "All" buries the real
 * orders under abandoned carts. They are one filter choice away when wanted.
 */
function sp_store_query_orders( array $args ): array {
	global $wpdb;
	$table    = sp_store_table( 'orders' );
	$per_page = 25;
	$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
	$where    = [ '1=1' ];
	$params   = [];

	$status = (string) ( $args['status'] ?? 'open' );
	if ( $status === '' ) {
		$where[] = "status <> 'pending'";
	} elseif ( isset( sp_store_order_statuses()[ $status ] ) ) {
		$where[]  = 'status = %s';
		$params[] = $status;
	}

	$fulfillment = (string) ( $args['fulfillment'] ?? '' );
	if ( isset( sp_store_fulfillment_statuses()[ $fulfillment ] ) ) {
		$where[]  = 'fulfillment_status = %s';
		$params[] = $fulfillment;
	}

	$search = trim( (string) ( $args['search'] ?? '' ) );
	if ( $search !== '' ) {
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$where[]  = '( number LIKE %s OR customer_email LIKE %s OR customer_name LIKE %s OR ship_name LIKE %s )';
		array_push( $params, $like, $like, $like, $like );
	}

	$sql_where = implode( ' AND ', $where );
	$count_sql = "SELECT COUNT(*) FROM $table WHERE $sql_where";
	$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

	$list_sql = "SELECT * FROM $table WHERE $sql_where ORDER BY COALESCE(placed_at, created_at) DESC, id DESC LIMIT %d OFFSET %d";
	$rows     = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, [ $per_page, ( $page - 1 ) * $per_page ] ) ), ARRAY_A ) ?: [];

	// Which of this page's orders have something to post — one query for the page,
	// not one per row.
	$shipping = [];
	if ( $rows ) {
		$ids = array_map( 'intval', array_column( $rows, 'id' ) );
		$shipping = array_flip( array_map( 'intval', $wpdb->get_col(
			"SELECT DISTINCT order_id FROM " . sp_store_table( 'order_items' ) . "
			  WHERE requires_shipping = 1 AND order_id IN (" . implode( ',', $ids ) . ")"
		) ?: [] ) );
	}

	return [
		'orders' => array_map( static fn( $r ) => sp_store_order_row( $r, isset( $shipping[ (int) $r['id'] ] ) ), $rows ),
		'page'   => $page,
		'pages'  => max( 1, (int) ceil( $total / $per_page ) ),
		'total'  => $total,
	];
}

/** Paid orders still waiting to go out — the number the list leads with. */
function sp_store_count_to_ship(): int {
	global $wpdb;
	return (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM " . sp_store_table( 'orders' ) . "
		  WHERE status = 'open' AND payment_status IN ('paid','partially_refunded')
		    AND fulfillment_status IN ('unfulfilled','partially_fulfilled')"
	);
}


/*--------------------------------------------------------------
# Fulfillment
--------------------------------------------------------------*/

/**
 * An order with nothing to post is fulfilled the moment it is paid.
 *
 * Otherwise a download or a membership would sit in "waiting to ship" forever,
 * and the count staff watch would stop meaning anything. Priority 5 so it lands
 * before the receipt is queued.
 */
add_action( 'sp_store_order_paid', 'sp_store_autofulfill_unshipped', 5 );
function sp_store_autofulfill_unshipped( int $order_id ): void {
	if ( sp_store_order_ships( $order_id ) ) return;

	global $wpdb;
	$wpdb->update( sp_store_table( 'orders' ), [
		'fulfillment_status' => 'fulfilled',
		'updated_at'         => current_time( 'mysql', true ),
	], [ 'id' => $order_id ] );
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . sp_store_table( 'order_items' ) . " SET qty_fulfilled = qty WHERE order_id = %d", $order_id
	) );
}

/**
 * Mark an order shipped, optionally with tracking, and optionally tell the customer.
 *
 * Calling it again on a shipped order UPDATES the tracking — the usual reason is a
 * typo in the number — and re-sends the shipping email only if asked to.
 *
 * @param array $args carrier, tracking_number, tracking_url, notify (bool)
 * @return true|WP_Error
 */
function sp_store_fulfill_order( int $order_id, array $args, int $user_id ) {
	global $wpdb;

	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return new WP_Error( 'sp_store_order', 'That order no longer exists.' );
	if ( $order['status'] !== 'open' && $order['status'] !== 'archived' ) {
		return new WP_Error( 'sp_store_order', 'Only a paid, live order can be marked as shipped.' );
	}
	if ( ! in_array( $order['payment_status'], [ 'paid', 'partially_refunded' ], true ) ) {
		return new WP_Error( 'sp_store_order', 'This order has not been paid, so it should not be shipped.' );
	}

	$carrier = sanitize_key( (string) ( $args['carrier'] ?? '' ) );
	if ( ! isset( sp_store_carriers()[ $carrier ] ) ) $carrier = '';
	$number  = sanitize_text_field( (string) ( $args['tracking_number'] ?? '' ) );
	$link    = sp_store_tracking_link( $carrier, $number, trim( (string) ( $args['tracking_url'] ?? '' ) ) );
	$now     = current_time( 'mysql', true );
	$first   = $order['fulfillment_status'] !== 'fulfilled';

	$wpdb->update( sp_store_table( 'orders' ), [
		'fulfillment_status' => 'fulfilled',
		'carrier'            => $carrier,
		'tracking_number'    => mb_substr( $number, 0, 120 ),
		'tracking_url'       => mb_substr( $link, 0, 255 ),
		'shipped_at'         => $first ? $now : ( $order['shipped_at'] ?: $now ),
		'updated_at'         => $now,
	], [ 'id' => $order_id ] );
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . sp_store_table( 'order_items' ) . " SET qty_fulfilled = qty WHERE order_id = %d", $order_id
	) );

	if ( ! empty( $args['notify'] ) && function_exists( 'sp_store_queue' ) ) {
		// Replace, not add: a corrected tracking number must be able to go out again,
		// and the queue's UNIQUE key would otherwise reject the second send.
		sp_store_queue( $order_id, 'shipped', (string) $order['customer_email'], [], true );
	}

	sp_store_order_log( $order, $first ? 'Marked as shipped.' : 'Updated tracking.', $user_id );
	return true;
}

/** Undo "shipped" — for the order that was marked by mistake. Tracking is kept, in case it is put back. */
function sp_store_unfulfill_order( int $order_id, int $user_id ) {
	global $wpdb;

	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return new WP_Error( 'sp_store_order', 'That order no longer exists.' );
	if ( $order['fulfillment_status'] !== 'fulfilled' ) return true;

	$wpdb->update( sp_store_table( 'orders' ), [
		'fulfillment_status' => 'unfulfilled',
		'updated_at'         => current_time( 'mysql', true ),
	], [ 'id' => $order_id ] );
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . sp_store_table( 'order_items' ) . " SET qty_fulfilled = 0 WHERE order_id = %d", $order_id
	) );

	sp_store_order_log( $order, 'Marked as not shipped.', $user_id );
	return true;
}


/*--------------------------------------------------------------
# Refunds
--------------------------------------------------------------*/

/** Can this order's provider send money back from here? */
function sp_store_provider_can_refund( string $provider ): bool {
	$fn = sp_store_provider_def( $provider ?: 'stripe' )['refund'] ?? '';
	return is_callable( $fn );
}

/**
 * Give money back.
 *
 * @param int    $cents   How much. Must be > 0 and no more than is still refundable.
 * @param bool   $restock Put every not-yet-refunded unit back on the shelf.
 * @param string $note    Why, in staff's words. Kept on the ledger row.
 * @return true|WP_Error
 */
function sp_store_refund_order( int $order_id, int $cents, bool $restock, string $note, int $user_id ) {
	global $wpdb;

	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return new WP_Error( 'sp_store_order', 'That order no longer exists.' );
	if ( ! in_array( $order['payment_status'], [ 'paid', 'partially_refunded' ], true ) ) {
		return new WP_Error( 'sp_store_order', 'There is no payment on this order to refund.' );
	}

	$left = sp_store_refundable_cents( $order );
	if ( $cents <= 0 )   return new WP_Error( 'sp_store_order', 'Enter an amount to refund.' );
	if ( $cents > $left ) {
		return new WP_Error( 'sp_store_order', 'That is more than was paid. At most ' . sp_store_money_text( $left, (string) $order['currency'] ) . ' can be refunded.' );
	}

	$fn = sp_store_provider_def( (string) $order['provider'] ?: 'stripe' )['refund'] ?? '';
	if ( ! is_callable( $fn ) ) return new WP_Error( 'sp_store_order', 'Refunds for this payment processor have to be done in its own dashboard.' );

	// The money first. Nothing is written here until the provider confirms it moved.
	$refund = call_user_func( $fn, $order, $cents, $note );
	if ( is_wp_error( $refund ) ) return $refund;

	sp_store_record_refund( $order_id, $refund, $note, $user_id );

	if ( $restock ) {
		foreach ( sp_store_order_items( $order_id ) as $item ) {
			$back = (int) $item['qty'] - (int) $item['qty_refunded'];
			if ( $back <= 0 || ! (int) $item['variant_id'] ) continue;
			sp_store_adjust_stock( (int) $item['variant_id'], $back, 'refund', $order_id, 'Refund on ' . $order['number'] );
			$wpdb->update( sp_store_table( 'order_items' ), [ 'qty_refunded' => (int) $item['qty'] ], [ 'id' => (int) $item['id'] ] );
		}
	}

	// A full refund on an order that never shipped means the sale did not happen.
	$fresh = sp_store_get_order( $order_id );
	if ( $fresh && $fresh['payment_status'] === 'refunded' && $fresh['fulfillment_status'] === 'unfulfilled' ) {
		$wpdb->update( sp_store_table( 'orders' ), [
			'status'        => 'cancelled',
			'cancelled_at'  => current_time( 'mysql', true ),
			'cancelled_by'  => $user_id,
			'cancel_reason' => mb_substr( $note !== '' ? $note : 'Refunded in full before shipping.', 0, 255 ),
			'updated_at'    => current_time( 'mysql', true ),
		], [ 'id' => $order_id ] );
	}

	// A full refund of a renewing membership must also stop the renewals — otherwise the
	// customer is refunded today and charged again next month.
	if ( $fresh && $fresh['payment_status'] === 'refunded' && (string) ( $fresh['provider_subscription'] ?? '' ) !== '' && function_exists( 'sp_member_by_subscription' ) ) {
		$m = sp_member_by_subscription( (string) $fresh['provider_subscription'] );
		if ( $m && $m['status'] === 'active' ) sp_member_cancel( (int) $m['id'], $user_id );
	}

	if ( function_exists( 'sp_store_queue' ) ) {
		sp_store_queue( $order_id, 'refunded', (string) $order['customer_email'], [ 'amount_cents' => $cents ], true );
	}

	sp_store_order_log( $order, 'Refunded ' . sp_store_money_text( $cents, (string) $order['currency'] ) . ( $restock ? ' and restocked.' : '.' ), $user_id );
	return true;
}

/**
 * Write one provider refund to the ledger, then re-add the order's refunded total.
 *
 * Reached from two directions — a refund staff issued here, and the webhook a
 * dashboard refund produces — and safe from both: UNIQUE (provider, provider_object)
 * turns the second sighting of a refund into a status update instead of a second row.
 *
 * @param array $refund [ 'id' => provider refund id, 'amount' => cents, 'status' => … ]
 * @return bool true when this call recorded a refund nobody had seen before
 */
function sp_store_record_refund( int $order_id, array $refund, string $note = '', int $user_id = 0, string $provider = 'stripe' ): bool {
	global $wpdb;
	$table = sp_store_table( 'payments' );
	$order = sp_store_get_order( $order_id );
	if ( ! $order || empty( $refund['id'] ) ) return false;

	$status = (string) ( $refund['status'] ?? 'succeeded' );
	$now    = current_time( 'mysql', true );

	$suppress = $wpdb->suppress_errors( true );
	$new = (bool) $wpdb->insert( $table, [
		'order_id'        => $order_id,
		'customer_id'     => (int) $order['customer_id'],
		'provider'        => $provider,
		'kind'            => 'refund',
		'amount_cents'    => -abs( (int) ( $refund['amount'] ?? 0 ) ),
		'currency'        => (string) $order['currency'],
		'status'          => $status,
		'provider_object' => (string) $refund['id'],
		'description'     => 'Refund on ' . $order['number'],
		'reason'          => mb_substr( $note, 0, 255 ),
		'created_by'      => $user_id,
		'created_at'      => $now,
		'updated_at'      => $now,
	] );
	$wpdb->suppress_errors( $suppress );

	if ( ! $new ) {
		$wpdb->update( $table, [ 'status' => $status, 'updated_at' => $now ],
			[ 'provider' => $provider, 'provider_object' => (string) $refund['id'] ] );
	}

	sp_store_recount_refunds( $order_id );
	return $new;
}

/**
 * The order's refunded total and payment status, re-derived from the ledger.
 * A failed or cancelled refund does not count — the money never moved.
 */
function sp_store_recount_refunds( int $order_id ): void {
	global $wpdb;
	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return;

	$refunded = (int) -$wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(amount_cents),0) FROM " . sp_store_table( 'payments' ) . "
		  WHERE order_id = %d AND kind = 'refund' AND status IN ('succeeded','pending')",
		$order_id
	) );

	$status = $order['payment_status'];
	if ( in_array( $status, [ 'paid', 'partially_refunded', 'refunded' ], true ) ) {
		if ( $refunded <= 0 )                               $status = 'paid';
		elseif ( $refunded >= (int) $order['paid_cents'] ) $status = 'refunded';
		else                                                $status = 'partially_refunded';
	}

	$wpdb->update( sp_store_table( 'orders' ), [
		'refunded_cents' => max( 0, $refunded ),
		'payment_status' => $status,
		'updated_at'     => current_time( 'mysql', true ),
	], [ 'id' => $order_id ] );
}


/*--------------------------------------------------------------
# Notes, Archive, Cancel
--------------------------------------------------------------*/

function sp_store_save_order_note( int $order_id, string $note, int $user_id ) {
	global $wpdb;
	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return new WP_Error( 'sp_store_order', 'That order no longer exists.' );
	$wpdb->update( sp_store_table( 'orders' ), [
		'staff_notes' => $note,
		'updated_at'  => current_time( 'mysql', true ),
	], [ 'id' => $order_id ] );
	return true;
}

/**
 * File an order away, or bring it back. Archived orders leave the default list but
 * are never deleted — an order is a financial record.
 */
function sp_store_archive_order( int $order_id, bool $archive, int $user_id ) {
	global $wpdb;
	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return new WP_Error( 'sp_store_order', 'That order no longer exists.' );

	$from = $archive ? 'open' : 'archived';
	if ( $order['status'] !== $from ) {
		return new WP_Error( 'sp_store_order', $archive ? 'Only a live order can be archived.' : 'That order is not archived.' );
	}

	$wpdb->update( sp_store_table( 'orders' ), [
		'status'     => $archive ? 'archived' : 'open',
		'updated_at' => current_time( 'mysql', true ),
	], [ 'id' => $order_id ] );

	sp_store_order_log( $order, $archive ? 'Archived.' : 'Restored from the archive.', $user_id );
	return true;
}

/**
 * Cancel an abandoned checkout. A PAID order is not cancelled from here — it is
 * refunded, and a full refund of an unshipped order cancels it on the way.
 */
function sp_store_staff_cancel_order( int $order_id, int $user_id ) {
	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return new WP_Error( 'sp_store_order', 'That order no longer exists.' );
	if ( $order['status'] !== 'pending' || $order['payment_status'] !== 'unpaid' ) {
		return new WP_Error( 'sp_store_order', 'A paid order is cancelled by refunding it.' );
	}
	sp_store_cancel_pending_order( $order_id, 'Cancelled by staff.' );

	global $wpdb;
	$wpdb->update( sp_store_table( 'orders' ), [ 'cancelled_by' => $user_id ], [ 'id' => $order_id ] );
	sp_store_order_log( $order, 'Cancelled an unfinished checkout.', $user_id );
	return true;
}

/** One line in the Site Pulse activity log, naming the order. */
function sp_store_order_log( array $order, string $what, int $user_id ): void {
	if ( ! function_exists( 'site_pulse_log' ) ) return;
	site_pulse_log( 'store_order', 'Order ' . $order['number'] . ': ' . $what, [ 'order_id' => (int) $order['id'], 'user_id' => $user_id ] );
}


/*--------------------------------------------------------------
# AJAX
--------------------------------------------------------------*/

/**
 * Every order endpoint starts here: nonce, module, then the permission the action
 * needs. Returns the acting user, which is the IMPERSONATED user when god is viewing
 * as someone — so a permission test from god's seat is a real test.
 */
function sp_store_orders_gate( callable $check, string $denied = 'You do not have permission to do that.' ): int {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_store_active() ) wp_send_json_error( [ 'message' => 'The store is not switched on for this site.' ] );
	$me = site_pulse_effective_user_id();
	if ( ! $check( $me ) ) wp_send_json_error( [ 'message' => $denied ] );
	return $me;
}

/** Send an action's result back, or its error as a sentence. */
function sp_store_orders_reply( $result, int $order_id, string $done ): void {
	if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
	$order = sp_store_get_order( $order_id );
	wp_send_json_success( [
		'message' => $done,
		'order'   => $order ? sp_store_order_detail( $order ) : null,
	] );
}

add_action( 'wp_ajax_site_pulse_store_orders', 'sp_store_ajax_orders' );
function sp_store_ajax_orders(): void {
	$me = sp_store_orders_gate( 'sp_store_can_view' );

	$out = sp_store_query_orders( [
		'status'      => sanitize_key( wp_unslash( (string) ( $_POST['status'] ?? 'open' ) ) ),
		'fulfillment' => sanitize_key( wp_unslash( (string) ( $_POST['fulfillment'] ?? '' ) ) ),
		'search'      => sanitize_text_field( wp_unslash( (string) ( $_POST['search'] ?? '' ) ) ),
		'page'        => (int) ( $_POST['page'] ?? 1 ),
	] );

	$out['to_ship']    = sp_store_count_to_ship();
	$out['can_manage'] = sp_store_can_manage_orders( $me );
	wp_send_json_success( $out );
}

add_action( 'wp_ajax_site_pulse_store_order_get', 'sp_store_ajax_order_get' );
function sp_store_ajax_order_get(): void {
	$me    = sp_store_orders_gate( 'sp_store_can_view' );
	$order = sp_store_get_order( (int) ( $_POST['id'] ?? 0 ) );
	if ( ! $order ) wp_send_json_error( [ 'message' => 'That order no longer exists.' ] );

	wp_send_json_success( [
		'order'       => sp_store_order_detail( $order ),
		'can_manage'  => sp_store_can_manage_orders( $me ),
		'can_refund'  => sp_store_can_manage_payments( $me ),
		'carriers'    => array_map( static fn( $c ) => $c['label'], sp_store_carriers() ),
	] );
}

add_action( 'wp_ajax_site_pulse_store_order_fulfill', 'sp_store_ajax_order_fulfill' );
function sp_store_ajax_order_fulfill(): void {
	$me = sp_store_orders_gate( 'sp_store_can_manage_orders' );
	$id = (int) ( $_POST['id'] ?? 0 );
	sp_store_orders_reply( sp_store_fulfill_order( $id, [
		'carrier'         => wp_unslash( (string) ( $_POST['carrier'] ?? '' ) ),
		'tracking_number' => wp_unslash( (string) ( $_POST['tracking_number'] ?? '' ) ),
		'tracking_url'    => wp_unslash( (string) ( $_POST['tracking_url'] ?? '' ) ),
		'notify'          => ! empty( $_POST['notify'] ),
	], $me ), $id, 'Marked as shipped.' );
}

add_action( 'wp_ajax_site_pulse_store_order_unfulfill', 'sp_store_ajax_order_unfulfill' );
function sp_store_ajax_order_unfulfill(): void {
	$me = sp_store_orders_gate( 'sp_store_can_manage_orders' );
	$id = (int) ( $_POST['id'] ?? 0 );
	sp_store_orders_reply( sp_store_unfulfill_order( $id, $me ), $id, 'Marked as not shipped.' );
}

add_action( 'wp_ajax_site_pulse_store_order_refund', 'sp_store_ajax_order_refund' );
function sp_store_ajax_order_refund(): void {
	$me = sp_store_orders_gate( 'sp_store_can_manage_payments', 'Refunds need the "Refund money" permission.' );
	$id = (int) ( $_POST['id'] ?? 0 );
	sp_store_orders_reply( sp_store_refund_order(
		$id,
		sp_store_parse_money( wp_unslash( (string) ( $_POST['amount'] ?? '' ) ) ),
		! empty( $_POST['restock'] ),
		sanitize_text_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ),
		$me
	), $id, 'Refund sent.' );
}

add_action( 'wp_ajax_site_pulse_store_order_note', 'sp_store_ajax_order_note' );
function sp_store_ajax_order_note(): void {
	$me = sp_store_orders_gate( 'sp_store_can_manage_orders' );
	$id = (int) ( $_POST['id'] ?? 0 );
	sp_store_orders_reply( sp_store_save_order_note(
		$id, sanitize_textarea_field( wp_unslash( (string) ( $_POST['staff_notes'] ?? '' ) ) ), $me
	), $id, 'Note saved.' );
}

add_action( 'wp_ajax_site_pulse_store_order_archive', 'sp_store_ajax_order_archive' );
function sp_store_ajax_order_archive(): void {
	$me      = sp_store_orders_gate( 'sp_store_can_manage_orders' );
	$id      = (int) ( $_POST['id'] ?? 0 );
	$archive = ! empty( $_POST['archive'] );
	sp_store_orders_reply( sp_store_archive_order( $id, $archive, $me ), $id, $archive ? 'Order archived.' : 'Order restored.' );
}

add_action( 'wp_ajax_site_pulse_store_order_cancel', 'sp_store_ajax_order_cancel' );
function sp_store_ajax_order_cancel(): void {
	$me = sp_store_orders_gate( 'sp_store_can_manage_orders' );
	$id = (int) ( $_POST['id'] ?? 0 );
	sp_store_orders_reply( sp_store_staff_cancel_order( $id, $me ), $id, 'Checkout cancelled.' );
}

add_action( 'wp_ajax_site_pulse_store_order_resend', 'sp_store_ajax_order_resend' );
function sp_store_ajax_order_resend(): void {
	$me    = sp_store_orders_gate( 'sp_store_can_manage_orders' );
	$id    = (int) ( $_POST['id'] ?? 0 );
	$order = sp_store_get_order( $id );
	if ( ! $order || ! in_array( $order['payment_status'], [ 'paid', 'partially_refunded', 'refunded' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Only a paid order has a receipt to send.' ] );
	}
	if ( ! is_email( (string) $order['customer_email'] ) ) {
		wp_send_json_error( [ 'message' => 'This order has no email address to send to.' ] );
	}
	sp_store_queue( $id, 'receipt', (string) $order['customer_email'], [], true, true );
	sp_store_order_log( $order, 'Re-sent the receipt.', $me );
	sp_store_orders_reply( true, $id, 'Receipt sent to ' . $order['customer_email'] . '.' );
}
