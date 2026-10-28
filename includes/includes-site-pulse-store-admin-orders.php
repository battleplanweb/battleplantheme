<?php
/* Battle Plan Web Design — Site Pulse: Store Orders in wp-admin

/*--------------------------------------------------------------
Products → Orders in wp-admin: the same orders Site Pulse shows, for whoever
runs the site from WordPress.

NOTHING IS DECIDED HERE. Every action posts to admin-post.php and calls the
same function the Site Pulse buttons call — sp_store_fulfill_order(),
sp_store_refund_order() and the rest in includes-site-pulse-store-orders.php —
so an order shipped or refunded from either screen lands identically. This
file only reads a form and draws a page.

Gated on WordPress capabilities, because this is wp-admin: editing orders needs
edit_others_pages (Editors and Administrators), sending money back needs
manage_options. Site Pulse has its own, finer permissions for its own screen.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Menu
# Page
# Actions
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Menu
--------------------------------------------------------------*/

add_action( 'admin_menu', 'sp_store_admin_orders_menu' );
function sp_store_admin_orders_menu(): void {
	if ( ! sp_store_active() ) return;

	add_submenu_page(
		'edit.php?post_type=' . SITE_PULSE_STORE_CPT,
		'Orders',
		'Orders',
		'edit_others_pages',
		'sp-store-orders',
		'sp_store_admin_orders_page'
	);

	// The count beside the menu item is the same "waiting to ship" number Site Pulse leads with.
	$n = sp_store_count_to_ship();
	if ( $n ) {
		global $submenu;
		$parent = 'edit.php?post_type=' . SITE_PULSE_STORE_CPT;
		foreach ( $submenu[ $parent ] ?? [] as $i => $item ) {
			if ( ( $item[2] ?? '' ) === 'sp-store-orders' ) {
				$submenu[ $parent ][ $i ][0] .= ' <span class="awaiting-mod">' . (int) $n . '</span>';
			}
		}
	}
}

function sp_store_admin_orders_url( array $args = [] ): string {
	return add_query_arg( array_merge( [ 'post_type' => SITE_PULSE_STORE_CPT, 'page' => 'sp-store-orders' ], $args ), admin_url( 'edit.php' ) );
}


/*--------------------------------------------------------------
# Page
--------------------------------------------------------------*/

function sp_store_admin_orders_page(): void {
	if ( ! current_user_can( 'edit_others_pages' ) ) wp_die( 'You do not have permission to see orders.' );

	echo '<div class="wrap sp-store-admin-orders">';

	// Result of the last action, carried through the redirect.
	$notice = get_transient( 'sp_store_admin_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'sp_store_admin_notice_' . get_current_user_id() );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $notice['ok'] ? 'success' : 'error', esc_html( $notice['text'] ) );
	}

	$id = (int) ( $_GET['order'] ?? 0 );
	if ( $id && ( $order = sp_store_get_order( $id ) ) ) {
		sp_store_admin_order_detail( $order );
	} else {
		sp_store_admin_order_list();
	}
	echo '</div>';
}

/** Badge markup for wp-admin, coloured by the same tones Site Pulse uses. */
function sp_store_admin_badge( array $b ): string {
	$colors = [ 'success' => '#00a32a', 'warning' => '#dba617', 'danger' => '#d63638', 'muted' => '#8c8f94' ];
	return sprintf(
		'<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;color:#fff;background:%s">%s</span>',
		esc_attr( $colors[ $b['tone'] ] ?? '#8c8f94' ),
		esc_html( $b['label'] )
	);
}

function sp_store_admin_order_list(): void {
	$status      = sanitize_key( (string) ( $_GET['status'] ?? 'open' ) );
	$fulfillment = sanitize_key( (string) ( $_GET['fulfillment'] ?? '' ) );
	$search      = sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) );
	$page        = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	if ( isset( $_GET['status'] ) && $_GET['status'] === 'all' ) $status = '';

	$result = sp_store_query_orders( compact( 'status', 'fulfillment', 'search', 'page' ) );

	echo '<h1 class="wp-heading-inline">Orders</h1>';
	echo ' <a class="page-title-action" href="' . esc_url( sp_store_orders_panel_url() ) . '">Open in Site Pulse</a>';
	echo '<hr class="wp-header-end">';

	$to_ship = sp_store_count_to_ship();
	if ( $to_ship && $fulfillment !== 'unfulfilled' ) {
		printf(
			'<div class="notice notice-warning inline"><p><strong>%d %s waiting to ship.</strong> <a href="%s">Show them</a></p></div>',
			$to_ship, $to_ship === 1 ? 'order is' : 'orders are',
			esc_url( sp_store_admin_orders_url( [ 'status' => 'open', 'fulfillment' => 'unfulfilled' ] ) )
		);
	}

	// Filters: a plain GET form, so every view is a bookmarkable URL.
	echo '<form method="get" style="margin:12px 0">';
	echo '<input type="hidden" name="post_type" value="' . esc_attr( SITE_PULSE_STORE_CPT ) . '"><input type="hidden" name="page" value="sp-store-orders">';
	echo '<select name="status">';
	foreach ( sp_store_order_statuses() as $k => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $status, $k, false ), esc_html( $label ) );
	}
	printf( '<option value="all"%s>All</option></select> ', selected( $status, '', false ) );
	echo '<select name="fulfillment"><option value="">Any progress</option>';
	foreach ( sp_store_fulfillment_statuses() as $k => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $fulfillment, $k, false ), esc_html( $label ) );
	}
	echo '</select> ';
	printf( '<input type="search" name="s" value="%s" placeholder="Name, email, or order number"> ', esc_attr( $search ) );
	submit_button( 'Filter', 'secondary', '', false );
	echo '</form>';

	echo '<table class="widefat striped"><thead><tr><th>Order</th><th>Placed</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Progress</th></tr></thead><tbody>';
	if ( ! $result['orders'] ) {
		echo '<tr><td colspan="7">No orders match.</td></tr>';
	}
	foreach ( $result['orders'] as $o ) {
		printf(
			'<tr><td><a href="%s"><strong>%s</strong></a></td><td>%s %s</td><td>%s<br><span class="description">%s</span></td><td>%d</td><td>%s</td><td>%s</td><td>%s%s</td></tr>',
			esc_url( sp_store_admin_orders_url( [ 'order' => $o['id'] ] ) ),
			esc_html( $o['number'] ),
			esc_html( $o['placed'] ), esc_html( $o['placed_time'] ),
			esc_html( $o['customer'] ), esc_html( $o['customer'] !== $o['email'] ? $o['email'] : '' ),
			(int) $o['item_count'],
			esc_html( $o['total'] ),
			sp_store_admin_badge( $o['payment'] ),
			sp_store_admin_badge( $o['progress'] ),
			$o['archived'] ? ' <span class="description">Archived</span>' : ''
		);
	}
	echo '</tbody></table>';

	if ( $result['pages'] > 1 ) {
		echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( [
			'base'    => add_query_arg( 'paged', '%#%' ),
			'format'  => '',
			'current' => $result['page'],
			'total'   => $result['pages'],
		] ) . '</div></div>';
	}
}

/** One action form, posting to admin-post.php with its own nonce. */
function sp_store_admin_order_form( int $order_id, string $act, string $inner, string $button, string $class = 'button', string $confirm = '' ): string {
	$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 6px 6px 0"' . ( $confirm !== '' ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' ) . '>';
	$out .= '<input type="hidden" name="action" value="sp_store_order_action">';
	$out .= '<input type="hidden" name="act" value="' . esc_attr( $act ) . '">';
	$out .= '<input type="hidden" name="order" value="' . (int) $order_id . '">';
	$out .= wp_nonce_field( 'sp_store_order_' . $act . '_' . $order_id, '_wpnonce', true, false );
	$out .= $inner;
	$out .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $button ) . '</button>';
	$out .= '</form>';
	return $out;
}

function sp_store_admin_order_detail( array $order ): void {
	$o = sp_store_order_detail( $order );
	$can_refund = current_user_can( 'manage_options' ) && $o['can_refund'];

	printf( '<p><a href="%s">&larr; All orders</a></p>', esc_url( sp_store_admin_orders_url() ) );
	printf( '<h1>Order %s %s %s</h1>', esc_html( $o['number'] ), sp_store_admin_badge( $o['payment'] ), sp_store_admin_badge( $o['progress'] ) );
	printf( '<p>Placed %s by <strong>%s</strong>', esc_html( $o['placed_full'] ), esc_html( $o['customer'] ) );
	if ( $o['email'] ) printf( ' &middot; <a href="mailto:%1$s">%1$s</a>', esc_html( $o['email'] ) );
	if ( $o['phone'] ) printf( ' &middot; %s', esc_html( $o['phone'] ) );
	echo '</p>';

	echo '<div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start">';

	// ---- Items and totals
	echo '<div style="flex:2 1 480px"><table class="widefat"><thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>';
	foreach ( $o['items'] as $it ) {
		printf(
			'<tr><td><strong>%s</strong>%s%s</td><td>%d</td><td>%s</td><td>%s</td></tr>',
			esc_html( $it['name'] ),
			$it['variant'] ? '<br><span class="description">' . esc_html( $it['variant'] ) . '</span>' : '',
			$it['sku'] ? '<br><span class="description">SKU ' . esc_html( $it['sku'] ) . '</span>' : '',
			(int) $it['qty'], esc_html( $it['unit'] ), esc_html( $it['line'] )
		);
	}
	$sum = static function ( string $label, string $value ) {
		printf( '<tr><td colspan="3" style="text-align:right">%s</td><td>%s</td></tr>', $label, $value );
	};
	$sum( 'Subtotal', esc_html( $o['subtotal'] ) );
	if ( $o['discount'] ) $sum( 'Discount', '-' . esc_html( $o['discount'] ) );
	if ( $o['ships'] )    $sum( 'Shipping' . ( $o['shipping_method'] ? ' (' . esc_html( $o['shipping_method'] ) . ')' : '' ), esc_html( $o['shipping'] ) );
	if ( $o['tax'] )      $sum( 'Tax', esc_html( $o['tax'] ) );
	$sum( '<strong>Total</strong>', '<strong>' . esc_html( $o['total'] ) . '</strong>' );
	if ( $o['refunded'] ) $sum( 'Refunded', '-' . esc_html( $o['refunded'] ) );
	echo '</tbody></table>';

	if ( $o['payments'] ) {
		echo '<h3>Money</h3><table class="widefat striped"><tbody>';
		foreach ( $o['payments'] as $p ) {
			printf( '<tr><td>%s%s</td><td>%s</td><td>%s</td><td class="description">%s</td></tr>',
				esc_html( $p['kind'] ), $p['status'] !== 'succeeded' ? ' (' . esc_html( $p['status'] ) . ')' : '',
				esc_html( $p['amount'] ), esc_html( $p['note'] ), esc_html( $p['when'] ) );
		}
		echo '</tbody></table>';
	}
	echo '</div>';

	// ---- Address, tracking, notes, actions
	echo '<div style="flex:1 1 300px">';
	if ( $o['ships'] ) {
		echo '<h3 style="margin-top:0">Ship to</h3><p>' . ( $o['ship_to'] ? implode( '<br>', array_map( 'esc_html', $o['ship_to'] ) ) : 'No address on file.' ) . '</p>';
		if ( $o['is_shipped'] ) {
			echo '<p><strong>Shipped</strong> ' . esc_html( $o['shipped_at'] );
			if ( $o['tracking_number'] ) {
				echo '<br>' . esc_html( $o['carrier_label'] !== 'Other' ? $o['carrier_label'] : '' ) . ' ';
				echo $o['tracking_url'] ? '<a href="' . esc_url( $o['tracking_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $o['tracking_number'] ) . '</a>' : esc_html( $o['tracking_number'] );
			}
			echo '</p>';
		}
	}
	if ( $o['customer_note'] ) echo '<h3>Note from the customer</h3><p>' . esc_html( $o['customer_note'] ) . '</p>';

	if ( ! empty( $o['downloads'] ) ) {
		printf( '<h3>Downloads</h3><p>%s<br><span class="description">%s</span></p>',
			implode( '<br>', array_map( 'esc_html', $o['downloads'] ) ),
			$o['download_count'] ? 'Used ' . (int) $o['download_count'] . ' time' . ( $o['download_count'] === 1 ? '' : 's' ) : 'Not used yet' );
	}

	if ( ! empty( $o['agreements'] ) ) {
		echo '<h3>Agreements accepted</h3>';
		foreach ( $o['agreements'] as $ag ) {
			printf( '<details style="margin:0 0 8px"><summary><strong>%s</strong>%s &middot; %s &middot; IP %s</summary><div style="white-space:pre-wrap;max-height:260px;overflow:auto;padding:8px;background:#fff;border:1px solid #dcdcde;margin-top:6px">%s</div></details>',
				esc_html( $ag['title'] ), $ag['name'] !== '' ? ' &middot; signed &ldquo;' . esc_html( $ag['name'] ) . '&rdquo;' : '', esc_html( $ag['when'] ), esc_html( $ag['ip'] ), esc_html( $ag['body'] ) );
		}
	}

	echo '<h3>Actions</h3>';

	if ( $o['ships'] && $o['is_live'] && $o['is_paid'] ) {
		$carriers = '<option value="">No tracking</option>';
		foreach ( sp_store_carriers() as $k => $c ) {
			$carriers .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $o['carrier'], $k, false ), esc_html( $c['label'] ) );
		}
		$inner  = '<p><select name="carrier">' . $carriers . '</select> ';
		$inner .= '<input type="text" name="tracking_number" placeholder="Tracking number" value="' . esc_attr( $o['tracking_number'] ) . '"></p>';
		$inner .= '<p><input type="url" name="tracking_url" placeholder="Tracking link (only for Other)" value="' . esc_attr( $o['carrier'] === 'other' ? $o['tracking_url'] : '' ) . '" style="width:100%"></p>';
		$inner .= '<p><label><input type="checkbox" name="notify" value="1" checked> Email the customer that it is on the way</label></p>';
		echo '<div style="padding:10px 12px;background:#fff;border:1px solid #dcdcde;margin-bottom:10px">';
		echo sp_store_admin_order_form( $o['id'], 'fulfill', $inner, $o['is_shipped'] ? 'Save Tracking' : 'Mark as Shipped', 'button button-primary' );
		if ( $o['is_shipped'] ) echo sp_store_admin_order_form( $o['id'], 'unfulfill', '', 'Mark as Not Shipped', 'button', 'Mark this order as not shipped?' );
		echo '</div>';
	}

	if ( $can_refund ) {
		$inner  = '<p><label>Amount <input type="text" name="amount" value="' . esc_attr( $o['refundable'] ) . '" style="width:7em"></label> ';
		$inner .= '<label><input type="checkbox" name="restock" value="1"' . ( $o['is_shipped'] ? '' : ' checked' ) . '> Put the items back in stock</label></p>';
		$inner .= '<p><input type="text" name="note" placeholder="Reason (optional)" style="width:100%"></p>';
		echo '<div style="padding:10px 12px;background:#fff;border:1px solid #dcdcde;margin-bottom:10px">';
		echo sp_store_admin_order_form( $o['id'], 'refund', $inner, 'Send Refund', 'button', 'Send this refund through Stripe? It cannot be undone.' );
		echo '</div>';
	}

	if ( $o['payment_status'] !== 'unpaid' ) echo sp_store_admin_order_form( $o['id'], 'resend', '', 'Resend Receipt' );
	if ( $o['status'] === 'open' )            echo sp_store_admin_order_form( $o['id'], 'archive', '', 'Archive' );
	if ( $o['status'] === 'archived' )        echo sp_store_admin_order_form( $o['id'], 'restore', '', 'Restore from Archive' );
	if ( $o['is_pending'] )                   echo sp_store_admin_order_form( $o['id'], 'cancel', '', 'Cancel This Checkout', 'button', 'Cancel this unfinished checkout?' );

	echo '<h3>Staff note</h3>';
	echo sp_store_admin_order_form( $o['id'], 'note', '<p><textarea name="staff_notes" rows="3" style="width:100%">' . esc_textarea( $o['staff_notes'] ) . '</textarea></p>', 'Save Note' );

	echo '</div></div>';
}


/*--------------------------------------------------------------
# Actions
--------------------------------------------------------------*/

add_action( 'admin_post_sp_store_order_action', 'sp_store_admin_order_action' );
function sp_store_admin_order_action(): void {
	$id  = (int) ( $_POST['order'] ?? 0 );
	$act = sanitize_key( (string) ( $_POST['act'] ?? '' ) );
	check_admin_referer( 'sp_store_order_' . $act . '_' . $id );

	$need = $act === 'refund' ? 'manage_options' : 'edit_others_pages';
	if ( ! current_user_can( $need ) ) wp_die( 'You do not have permission to do that.' );

	$me    = get_current_user_id();
	$order = sp_store_get_order( $id );
	if ( ! $order ) wp_die( 'That order no longer exists.' );

	switch ( $act ) {
		case 'fulfill':
			$res  = sp_store_fulfill_order( $id, [
				'carrier'         => wp_unslash( (string) ( $_POST['carrier'] ?? '' ) ),
				'tracking_number' => wp_unslash( (string) ( $_POST['tracking_number'] ?? '' ) ),
				'tracking_url'    => wp_unslash( (string) ( $_POST['tracking_url'] ?? '' ) ),
				'notify'          => ! empty( $_POST['notify'] ),
			], $me );
			$done = 'Marked as shipped.';
			break;
		case 'unfulfill':
			$res  = sp_store_unfulfill_order( $id, $me );
			$done = 'Marked as not shipped.';
			break;
		case 'refund':
			$res  = sp_store_refund_order( $id,
				sp_store_parse_money( wp_unslash( (string) ( $_POST['amount'] ?? '' ) ) ),
				! empty( $_POST['restock'] ),
				sanitize_text_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ),
				$me );
			$done = 'Refund sent.';
			break;
		case 'note':
			$res  = sp_store_save_order_note( $id, sanitize_textarea_field( wp_unslash( (string) ( $_POST['staff_notes'] ?? '' ) ) ), $me );
			$done = 'Note saved.';
			break;
		case 'archive':
		case 'restore':
			$res  = sp_store_archive_order( $id, $act === 'archive', $me );
			$done = $act === 'archive' ? 'Order archived.' : 'Order restored.';
			break;
		case 'cancel':
			$res  = sp_store_staff_cancel_order( $id, $me );
			$done = 'Checkout cancelled.';
			break;
		case 'resend':
			if ( is_email( (string) $order['customer_email'] ) ) {
				sp_store_queue( $id, 'receipt', (string) $order['customer_email'], [], true, true );
				sp_store_order_log( $order, 'Re-sent the receipt.', $me );
				$res = true;
			} else {
				$res = new WP_Error( 'sp_store_order', 'This order has no email address to send to.' );
			}
			$done = 'Receipt sent to ' . $order['customer_email'] . '.';
			break;
		default:
			$res  = new WP_Error( 'sp_store_order', 'Unknown action.' );
			$done = '';
	}

	set_transient( 'sp_store_admin_notice_' . $me, [
		'ok'   => ! is_wp_error( $res ),
		'text' => is_wp_error( $res ) ? $res->get_error_message() : $done,
	], 60 );

	wp_safe_redirect( sp_store_admin_orders_url( [ 'order' => $id ] ) );
	exit;
}
