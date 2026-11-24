<?php
/* Battle Plan Web Design — Site Pulse: Store ↔ Booking (seats sold through checkout)

/*--------------------------------------------------------------
A paid booking-session seat is just another line in the Store cart, so a
customer buys a retreat weekend and a t-shirt in ONE checkout and ONE payment.

A booking line is a cart_items / order_items row with kind = 'booking',
variant_id = 0 and the session in session_id. Everything product-shaped
(stock reservation, shipping, the inventory ledger) skips it; everything else
— totals, tax, the Stripe/Clover handoff, receipts, the orders screen — treats
it like any line with a name, a quantity and a price.

THE LIFECYCLE, and the hook each step hangs off:

  add to cart      nothing is held (same as products — a cart reserves nothing).
                   The line is re-checked and re-priced on every read.
  place order      sp_store_bookings_hold(): each line claims its seats as a
                   PENDING booking (the engine's atomic seat UPDATE) with a hold
                   long enough to outlast checkout; booking_id goes on the order
                   line. A date that filled since it was added fails the whole
                   order here, before anyone pays.
  paid             sp_store_order_paid → confirmed, contact details copied from
                   the order, booking confirmation + reminders queued.
  never paid       sp_store_order_cancelled (Stripe session expired, the sweep,
                   a failed handoff) → the held seats go back.
  refunded in full sp_store_refunds_recounted → the bookings are cancelled
                   (and the customer told), freeing the seats.

A paid order whose hold had already lapsed is re-claimed; if the seats went
to someone else meanwhile, staff get an email — the money is in, so a person
has to decide (refund, or squeeze them in).

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Readiness
# Cart lines
# Checkout: hold → confirm / release / cancel
# AJAX
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

/** How long a checkout holds seats. Outlasts the Stripe session (35 min) and the pending-order sweep (2 h). */
const SP_STORE_BOOKING_HOLD_MIN = 125;


/*--------------------------------------------------------------
# Readiness
--------------------------------------------------------------*/

/** Can a booking seat be sold through checkout on this site right now? */
function sp_store_bookings_ready(): bool {
	return function_exists( 'sp_book_public_on' ) && sp_book_public_on()
		&& function_exists( 'sp_store_active' ) && sp_store_active()
		&& function_exists( 'sp_store_provider_ready' ) && sp_store_provider_ready();
}


/*--------------------------------------------------------------
# Cart lines
--------------------------------------------------------------*/

/** Add seats in a session to the current cart (or raise the seats on its existing line). */
function sp_store_cart_add_booking( int $session_id, int $seats = 1 ) {
	if ( ! sp_store_bookings_ready() ) return new WP_Error( 'sp_store_book', 'Online booking checkout isn\'t available right now.' );
	$info = sp_book_public_session( $session_id );
	if ( is_wp_error( $info ) ) return $info;
	if ( ! $info['open'] )     return new WP_Error( 'sp_store_book', 'That date can\'t be booked online any more.' );
	if ( ! $info['checkout'] ) return new WP_Error( 'sp_store_book', 'That one doesn\'t need checkout — book it from the calendar.' );
	if ( $info['left'] <= 0 )  return new WP_Error( 'sp_store_book', 'Sorry — that date is full.' );
	// A series is always carted on its lead session, whichever date was clicked — one line per class.
	$session_id = (int) $info['id'];

	$cart = sp_store_current_cart( true );
	if ( ! $cart ) return new WP_Error( 'sp_store_no_cart', 'Could not start a cart.' );

	global $wpdb;
	$table = sp_store_table( 'cart_items' );
	$now   = current_time( 'mysql', true );
	$max   = min( 10, (int) $info['left'] );
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO $table (cart_id, variant_id, product_id, qty, unit_price_cents, kind, session_id, created_at, updated_at)
		 VALUES (%d, 0, 0, %d, %d, 'booking', %d, %s, %s)
		 ON DUPLICATE KEY UPDATE qty = LEAST(qty + VALUES(qty), %d), unit_price_cents = VALUES(unit_price_cents), updated_at = VALUES(updated_at)",
		(int) $cart['id'], min( max( 1, $seats ), $max ), (int) $info['price_cents'], $session_id, $now, $now, $max
	) );

	sp_store_cart_touch( (int) $cart['id'] );
	return sp_store_cart_recalc( (int) $cart['id'] );
}

/** Change a booking line's seats (0 removes it). Addressed by line id — it has no variant. */
function sp_store_cart_booking_qty( int $line_id, int $qty ) {
	$cart = sp_store_current_cart();
	if ( ! $cart ) return new WP_Error( 'sp_store_no_cart', 'Your cart has expired.' );
	global $wpdb;
	$table = sp_store_table( 'cart_items' );
	$where = [ 'id' => $line_id, 'cart_id' => (int) $cart['id'], 'kind' => 'booking' ];

	if ( $qty <= 0 ) {
		$wpdb->delete( $table, $where );
	} else {
		$line = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d AND cart_id = %d AND kind = 'booking'", $line_id, (int) $cart['id'] ), ARRAY_A );
		if ( $line ) {
			$info = sp_book_public_session( (int) $line['session_id'] );
			$max  = is_wp_error( $info ) ? 0 : min( 10, (int) $info['left'] );
			if ( $max <= 0 ) return new WP_Error( 'sp_store_book', 'Sorry — that date is full now.' );
			$wpdb->update( $table, [ 'qty' => min( $qty, $max ), 'updated_at' => current_time( 'mysql', true ) ], $where );
		}
	}
	sp_store_cart_touch( (int) $cart['id'] );
	return sp_store_cart_recalc( (int) $cart['id'] );
}

/**
 * The cart's booking lines, in sp_store_cart_lines()'s shape plus kind/line_id/session_id/taxable.
 * Priced live from the service. A line whose date is gone, past its booking window, or no longer
 * paid is dropped (the same rule as a product that was unpublished).
 */
function sp_store_booking_cart_lines( int $cart_id ): array {
	if ( ! function_exists( 'sp_book_public_session' ) ) return [];
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'cart_items' ) . " WHERE cart_id = %d AND kind = 'booking' ORDER BY id ASC", $cart_id
	), ARRAY_A ) ?: [];

	$lines = [];
	$stale = [];
	foreach ( $rows as $row ) {
		$info = sp_book_public_session( (int) $row['session_id'] );
		if ( is_wp_error( $info ) || ! $info['open'] || ! $info['checkout'] ) { $stale[] = (int) $row['id']; continue; }

		$svc   = sp_book_get_service( (int) sp_book_get_session( (int) $row['session_id'] )['service_id'] );
		$opts  = sp_book_service_opts( $svc );
		$unit  = (int) $info['price_cents'];
		$qty   = max( 1, (int) $row['qty'] );
		$image = $opts['event_id'] ? (string) ( get_the_post_thumbnail_url( $opts['event_id'], 'thumbnail' ) ?: '' ) : '';

		$lines[] = [
			'kind'       => 'booking',
			'line_id'    => (int) $row['id'],
			'session_id' => (int) $row['session_id'],
			'variant_id' => 0,
			'product_id' => 0,
			'title'      => sp_store_plain_text( $info['title'] ),
			'variant'    => $info['when'] . ( $info['deposit'] ? ' (deposit)' : '' ),
			'sku'        => '',
			'url'        => $info['url'],
			'image'      => $image,
			'qty'        => $qty,
			'max'        => min( 10, (int) $info['left'] ),
			'unit_cents' => $unit,
			'unit'       => sp_store_money( $unit ),
			'line_cents' => $unit * $qty,
			'line'       => sp_store_money( $unit * $qty ),
			'taxable'    => $opts['taxable'],
		];
	}
	if ( $stale ) {
		$wpdb->query( "DELETE FROM " . sp_store_table( 'cart_items' ) . " WHERE id IN (" . implode( ',', $stale ) . ")" );
	}
	return $lines;
}


/*--------------------------------------------------------------
# Checkout: hold → confirm / release / cancel
--------------------------------------------------------------*/

/** An order's booking lines. */
function sp_store_order_booking_items( int $order_id ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'order_items' ) . " WHERE order_id = %d AND kind = 'booking' ORDER BY id ASC", $order_id
	), ARRAY_A ) ?: [];
}

/**
 * Place order: claim each booking line's seats as a pending booking tied to the order. All or
 * nothing — on any failure the caller cancels the order, and sp_store_order_cancelled releases
 * whatever this already held. Returns true or a WP_Error with a message for the customer.
 */
function sp_store_bookings_hold( int $order_id, string $email, string $name = '' ) {
	$items = sp_store_order_booking_items( $order_id );
	if ( ! $items ) return true;
	if ( ! function_exists( 'sp_book_claim' ) ) return new WP_Error( 'sp_store_book', 'Booking isn\'t available right now.' );

	global $wpdb;
	$order = sp_store_get_order( $order_id );
	foreach ( $items as $item ) {
		$session = sp_book_get_session( (int) $item['session_id'] );
		if ( ! $session ) return new WP_Error( 'sp_store_book', sprintf( 'Sorry — "%s" is no longer on the schedule. Please remove it from your cart.', $item['name'] ) );

		$booking = sp_book_claim( [
			'service_id'     => (int) $session['service_id'],
			'session_id'     => (int) $session['id'],
			'party_size'     => (int) $item['qty'],
			'status'         => 'pending',
			'hold_minutes'   => SP_STORE_BOOKING_HOLD_MIN,
			'source'         => 'web',
			'customer_name'  => $name !== '' ? $name : $email,
			'customer_email' => $email,
			'price_cents'    => (int) $item['total_cents'],
			'deposit_cents'  => 0,
			'notes'          => 'Store order ' . ( $order['number'] ?? $order_id ),
		] );
		if ( is_wp_error( $booking ) ) {
			return new WP_Error( 'sp_store_book', $booking->get_error_code() === 'session_full'
				? sprintf( 'Sorry — "%s" (%s) doesn\'t have %d spot%s left any more. Please change it in your cart.', $item['name'], $item['variant_title'], (int) $item['qty'], (int) $item['qty'] === 1 ? '' : 's' )
				: $booking->get_error_message() );
		}
		$wpdb->update( sp_store_table( 'order_items' ), [ 'booking_id' => (int) $booking['id'] ], [ 'id' => (int) $item['id'] ] );
		// A series enrollment returns its lead; the order number goes on every date of it.
		$link = [ 'external_source' => 'store', 'external_id' => (string) ( $order['number'] ?? $order_id ) ];
		$wpdb->update( sp_book_table( 'bookings' ), $link, [ 'id' => (int) $booking['id'] ] );
		if ( (int) ( $booking['group_id'] ?? 0 ) ) $wpdb->update( sp_book_table( 'bookings' ), $link, [ 'group_id' => (int) $booking['group_id'] ] );
	}
	return true;
}

/** Never paid: give the held seats back. */
add_action( 'sp_store_order_cancelled', 'sp_store_bookings_release', 10, 2 );
function sp_store_bookings_release( int $order_id, string $reason = '' ): void {
	if ( ! function_exists( 'sp_book_release' ) ) return;
	foreach ( sp_store_order_booking_items( $order_id ) as $item ) {
		$b = (int) $item['booking_id'] ? sp_book_get_booking( (int) $item['booking_id'] ) : null;
		if ( $b && $b['status'] === 'pending' ) sp_book_release( (int) $b['id'], 'expired', 0, $reason !== '' ? mb_substr( $reason, 0, 200 ) : 'Checkout not completed' );
	}
}

/**
 * Paid: confirm each booking (before the receipt goes out at priority 20), copy the contact details
 * the processor collected, and queue the booking's own confirmation + reminders.
 */
add_action( 'sp_store_order_paid', 'sp_store_bookings_confirm', 4 );
function sp_store_bookings_confirm( int $order_id ): void {
	$items = sp_store_order_booking_items( $order_id );
	if ( ! $items || ! function_exists( 'sp_book_confirm' ) ) return;
	global $wpdb;
	$order = sp_store_get_order( $order_id );
	$problems = [];

	foreach ( $items as $item ) {
		$b = (int) $item['booking_id'] ? sp_book_get_booking( (int) $item['booking_id'] ) : null;

		// The hold lapsed before the money arrived (a slow bank, an async payment): claim again.
		if ( ! $b || ! in_array( $b['status'], [ 'pending', 'confirmed' ], true ) ) {
			$session = sp_book_get_session( (int) $item['session_id'] );
			$b = $session ? sp_book_claim( [
				'service_id' => (int) $session['service_id'], 'session_id' => (int) $session['id'],
				'party_size' => (int) $item['qty'], 'status' => 'confirmed', 'source' => 'web', 'ignore_policy' => true,
				'customer_email' => (string) $order['customer_email'], 'customer_name' => (string) ( $order['customer_name'] ?: $order['customer_email'] ),
				'price_cents' => (int) $item['total_cents'], 'deposit_cents' => 0, 'notes' => 'Store order ' . $order['number'],
			] ) : new WP_Error( 'gone', 'The date was removed.' );
			if ( is_wp_error( $b ) ) { $problems[] = sprintf( '%s — %s × %d: %s', $item['name'], $item['variant_title'], (int) $item['qty'], $b->get_error_message() ); continue; }
			$wpdb->update( sp_store_table( 'order_items' ), [ 'booking_id' => (int) $b['id'] ], [ 'id' => (int) $item['id'] ] );
		} elseif ( $b['status'] === 'pending' ) {
			sp_book_confirm( (int) $b['id'] );
		}

		$contact = array_filter( [
			'customer_name'   => (string) ( $order['customer_name'] ?: $order['ship_name'] ),
			'customer_email'  => (string) $order['customer_email'],
			'customer_phone'  => (string) $order['customer_phone'],
			'external_source' => 'store',
			'external_id'     => (string) $order['number'],
			'updated_at'      => sp_book_now(),
		], static fn( $v ) => $v !== '' );
		// A series: every date gets the contact details (each date's roster shows who's coming);
		// the money stays on the lead.
		if ( (int) ( $b['group_id'] ?? 0 ) ) $wpdb->update( sp_book_table( 'bookings' ), $contact, [ 'group_id' => (int) $b['group_id'] ] );
		$wpdb->update( sp_book_table( 'bookings' ), $contact + [ 'paid_cents' => (int) $item['total_cents'] ], [ 'id' => (int) $b['id'] ] );

		$fresh = sp_book_get_booking( (int) $b['id'] );
		if ( $fresh && function_exists( 'sp_book_notify_created' ) ) sp_book_notify_created( $fresh );
	}

	if ( $problems ) {
		$to = function_exists( 'sp_book_staff_email' ) ? sp_book_staff_email( null ) : (string) get_option( 'admin_email' );
		if ( is_email( $to ) ) {
			wp_mail( $to, sprintf( 'Paid order %s: booking could not be placed', $order['number'] ), sprintf(
				"Order %s was paid, but these seats could not be booked (the date filled or was removed before payment finished):\n\n%s\n\nCustomer: %s\n\nRefund them from Site Pulse → E-Commerce → Orders, or add them by hand in Booking.",
				$order['number'], implode( "\n", $problems ), $order['customer_email']
			) );
		}
	}
}

/** Refunded in full: the bookings are off, the seats free, the customer told. */
add_action( 'sp_store_refunds_recounted', 'sp_store_bookings_refunded', 10, 2 );
function sp_store_bookings_refunded( int $order_id, string $status ): void {
	if ( $status !== 'refunded' || ! function_exists( 'sp_book_cancel' ) ) return;
	foreach ( sp_store_order_booking_items( $order_id ) as $item ) {
		$b = (int) $item['booking_id'] ? sp_book_get_booking( (int) $item['booking_id'] ) : null;
		if ( ! $b || ! in_array( $b['status'], [ 'pending', 'confirmed' ], true ) ) continue;
		if ( sp_book_cancel( (int) $b['id'], get_current_user_id(), 'Order refunded' ) && function_exists( 'sp_book_notify_cancelled' ) ) {
			sp_book_notify_cancelled( (int) $b['id'] );
		}
	}
}


/*--------------------------------------------------------------
# AJAX
--------------------------------------------------------------*/

add_action( 'wp_ajax_sp_store_cart_add_booking', 'sp_store_ajax_cart_add_booking' );
add_action( 'wp_ajax_nopriv_sp_store_cart_add_booking', 'sp_store_ajax_cart_add_booking' );
function sp_store_ajax_cart_add_booking(): void {
	if ( ! sp_store_cart_guard() ) return;
	sp_store_cart_reply( sp_store_cart_add_booking( (int) ( $_POST['session_id'] ?? 0 ), (int) ( $_POST['qty'] ?? 1 ) ) );
}
