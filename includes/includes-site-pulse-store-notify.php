<?php
/* Battle Plan Web Design — Site Pulse: Store Emails

/*--------------------------------------------------------------
Receipts, shipping notices, refund notices, and the staff new-order alert.

The same shape as Booking's notifications, on purpose — one mental model for
"the system emails people": a registry of templates whose copy the client can
rewrite, a QUEUE rather than an inline send, and a sweep that delivers.

Queued, never sent inline, because a failed wp_mail() must not fail an order.
Taking the money and telling somebody about it are separate, and only the first
is allowed to fail loudly.

But a receipt cannot wait for the five-minute sweep either: a quiet store might
not see another visitor for an hour. So every enqueue also KICKS the queue — it
is drained on shutdown, after the reply has gone (fastcgi_finish_request), so
the webhook answers Stripe at once and the customer's page is never held up by
a slow mail server.

UNIQUE (order_id, template_key, channel) on the queue makes a repeat enqueue a
no-op. A deliberate resend — a corrected tracking number, a second partial
refund, "send the receipt again" — REPLACES the row instead.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Templates + Settings
# Tokens
# Queueing
# Event Hooks
# The Sweep — send what is due
# Settings AJAX
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Templates + Settings
--------------------------------------------------------------*/

/**
 * The messages the store can send. Default copy is plain and short — a client will
 * rewrite it, and copy that reads like the person who runs the business beats copy
 * that reads like a form letter.
 */
function sp_store_notification_templates(): array {
	return [
		'receipt' => [
			'label'   => 'Order receipt',
			'when'    => 'As soon as an order is paid',
			'to'      => 'customer',
			'default' => true,
			'subject' => 'Your {business} order {order_number}',
			'body'    => "Hi {customer_first},\n\nThanks for your order! Here's your receipt.\n\nOrder {order_number} · {order_date}\n\n{items}\n\nSubtotal: {subtotal}\n{discount_line}{shipping_line}{tax_line}Total paid: {total}\n\n{downloads_block}{ship_to_block}{agreements_block}If you have any questions, just reply to this email.\n\n{business}",
		],
		'shipped' => [
			'label'   => 'Shipping notice',
			'when'    => 'When you mark an order as shipped and choose to tell the customer',
			'to'      => 'customer',
			'default' => true,
			'subject' => 'Your {business} order is on its way',
			'body'    => "Hi {customer_first},\n\nGood news, your order {order_number} is on its way.\n\n{tracking_block}{items}\n\n{ship_to_block}{business}",
		],
		'refunded' => [
			'label'   => 'Refund notice',
			'when'    => 'When you refund an order',
			'to'      => 'customer',
			'default' => true,
			'subject' => 'Your refund from {business}',
			'body'    => "Hi {customer_first},\n\nWe've refunded {refund_amount} for order {order_number} to your original payment method. Depending on your bank, it can take 5 to 10 business days to show up.\n\n{business}",
		],
		// Memberships. Shown on the settings screen only while that feature is on.
		'member_welcome' => [
			'label'   => 'Welcome, new member',
			'when'    => 'When a purchase gives a membership to someone with no account yet',
			'to'      => 'customer',
			'default' => true,
			'feature' => 'memberships',
			'subject' => 'Welcome to {business}: your account is ready',
			'body'    => "Hi {customer_first},\n\nThanks for joining! Your purchase includes {plans}.\n\nUse this link to sign in and choose a password. It works once, for the next 7 days:\n{account_link}\n\nAfter that you can sign in any time at {account_page}\n\n{business}",
		],
		'member_access' => [
			'label'   => 'Membership added',
			'when'    => 'When a purchase gives a membership to someone who already has an account',
			'to'      => 'customer',
			'default' => true,
			'feature' => 'memberships',
			'subject' => 'You now have {plans}',
			'body'    => "Hi {customer_first},\n\nYour purchase includes {plans}, and it has been added to your account.\n\nSign in to start: {account_page}\n\n{business}",
		],
		'staff_alert' => [
			'label'   => 'New order alert (staff)',
			'when'    => 'As soon as an order is paid',
			'to'      => 'staff',
			'default' => true,
			'subject' => 'New order {order_number}: {total}',
			'body'    => "{customer} just placed an order.\n\n{items}\n\nTotal paid: {total}\n\n{ship_to_block}{note_block}{email_line}{phone_line}See the order: {orders_link}",
		],
	];
}

/** Is a template switched on? Falls back to the registry default. */
function sp_store_notify_on( string $key ): bool {
	$tpl = sp_store_notification_templates()[ $key ] ?? null;
	if ( ! $tpl ) return false;
	$saved = sp_store_get_setting( 'notify_' . $key, '' );
	if ( $saved === '' ) return ! empty( $tpl['default'] );
	return $saved === '1';
}

/** The stored subject/body for a template, falling back to the registry copy. */
function sp_store_notify_copy( string $key ): array {
	$tpl     = sp_store_notification_templates()[ $key ] ?? [ 'subject' => '', 'body' => '' ];
	$subject = sp_store_get_setting( 'notify_subject_' . $key, '' );
	$body    = sp_store_get_setting( 'notify_body_' . $key, '' );
	return [
		'subject' => $subject !== '' ? $subject : (string) $tpl['subject'],
		'body'    => $body !== '' ? $body : (string) $tpl['body'],
	];
}

/**
 * Where staff alerts go: the saved list, else the business email on file, else the
 * site admin. Several addresses are allowed, comma-separated — a two-person shop
 * usually wants both of them told.
 */
function sp_store_staff_emails(): string {
	$set = array_filter( array_map( 'trim', explode( ',', sp_store_get_setting( 'staff_email', '' ) ) ), 'is_email' );
	if ( $set ) return implode( ',', $set );

	$info = get_option( 'customer_info' );
	if ( is_array( $info ) && is_email( (string) ( $info['email'] ?? '' ) ) ) return (string) $info['email'];
	return (string) get_option( 'admin_email' );
}


/*--------------------------------------------------------------
# Tokens
--------------------------------------------------------------*/

/**
 * The token map for one order. The *_line and *_block tokens resolve to whole lines
 * (trailing newline included) or to nothing — so an order with no tax doesn't print
 * "Tax: $0.00", and a download doesn't print an empty "Shipping to:".
 */
function sp_store_tokens( array $order, array $payload = [] ): array {
	$cur   = (string) $order['currency'];
	$name  = trim( (string) $order['customer_name'] );
	$first = $name !== '' ? preg_split( '/\s+/', $name )[0] : 'there';

	$items = [];
	foreach ( sp_store_order_items( (int) $order['id'] ) as $it ) {
		$label = (string) $it['name'];
		if ( $it['variant_title'] !== '' && $it['variant_title'] !== 'Default' ) $label .= ' (' . $it['variant_title'] . ')';
		$items[] = (int) $it['qty'] . ' × ' . $label . ': ' . sp_store_money_text( (int) $it['total_cents'], $cur );
	}

	$ship = array_values( array_filter( [
		(string) $order['ship_name'],
		(string) $order['ship_address1'],
		(string) $order['ship_address2'],
		trim( $order['ship_city'] . ( $order['ship_city'] !== '' && $order['ship_state'] !== '' ? ', ' : '' ) . $order['ship_state'] . ' ' . $order['ship_zip'] ),
	], static fn( $l ) => trim( $l ) !== '' ) );

	$carrier  = sp_store_carriers()[ (string) $order['carrier'] ]['label'] ?? '';
	$tracking = '';
	if ( $order['tracking_number'] !== '' ) {
		$tracking  = 'Tracking: ' . trim( ( $carrier !== 'Other' ? $carrier : '' ) . ' ' . $order['tracking_number'] ) . "\n";
		$tracking .= $order['tracking_url'] !== '' ? $order['tracking_url'] . "\n" : '';
		$tracking .= "\n";
	}

	$money = static fn( string $col ) => sp_store_money_text( (int) $order[ $col ], $cur );

	return [
		'{customer}'        => $name !== '' ? $name : (string) $order['customer_email'],
		'{customer_first}'  => $first,
		'{business}'        => bp_business_name(),
		'{order_number}'    => (string) $order['number'],
		'{order_date}'      => sp_store_local_time( $order['placed_at'] ?: $order['created_at'], 'F j, Y' ),
		'{items}'           => implode( "\n", $items ),
		'{subtotal}'        => $money( 'subtotal_cents' ),
		'{shipping}'        => $money( 'shipping_cents' ),
		'{tax}'             => $money( 'tax_cents' ),
		'{total}'           => $money( 'total_cents' ),
		'{discount_line}'   => (int) $order['discount_cents'] ? 'Discount: -' . $money( 'discount_cents' ) . "\n" : '',
		'{shipping_line}'   => (int) $order['shipping_cents'] ? 'Shipping: ' . $money( 'shipping_cents' ) . "\n" : '',
		'{tax_line}'        => (int) $order['tax_cents'] ? 'Tax: ' . $money( 'tax_cents' ) . "\n" : '',
		'{ship_to_block}'   => $ship ? "Shipping to:\n" . implode( "\n", $ship ) . "\n\n" : '',
		'{tracking_block}'  => $tracking,
		'{tracking_number}' => (string) $order['tracking_number'],
		'{refund_amount}'   => sp_store_money_text( (int) ( $payload['amount_cents'] ?? $order['refunded_cents'] ), $cur ),
		'{email}'           => (string) $order['customer_email'],
		'{email_line}'      => $order['customer_email'] !== '' ? 'Email: ' . $order['customer_email'] . "\n" : '',
		'{phone_line}'      => $order['customer_phone'] !== '' ? 'Phone: ' . $order['customer_phone'] . "\n\n" : '',
		'{orders_link}'     => sp_store_orders_panel_url(),
		'{note_block}'      => trim( (string) $order['customer_note'] ) !== '' ? "Note from the customer:\n" . trim( (string) $order['customer_note'] ) . "\n\n" : '',
		'{agreements_block}'=> function_exists( 'sp_store_agreements_email_block' ) ? sp_store_agreements_email_block( (int) $order['id'] ) : '',
		'{downloads_block}' => function_exists( 'sp_store_downloads_email_block' ) ? sp_store_downloads_email_block( $order ) : '',
		'{plans}'           => (string) ( $payload['plans'] ?? '' ),
		'{account_page}'    => function_exists( 'sp_member_account_url' ) ? sp_member_account_url() : '',
	];
}

/** The token names, for the help line on the settings screen. */
function sp_store_token_names(): array {
	return array_merge( [
		'{customer}', '{customer_first}', '{business}', '{order_number}', '{order_date}', '{items}',
		'{subtotal}', '{shipping}', '{tax}', '{total}', '{discount_line}', '{shipping_line}', '{tax_line}',
		'{ship_to_block}', '{tracking_block}', '{tracking_number}', '{refund_amount}', '{email}',
		'{email_line}', '{phone_line}', '{note_block}', '{orders_link}',
	], ( function_exists( 'sp_store_members_on' ) && sp_store_members_on() ) ? [ '{plans}', '{account_page}', '{account_link}' ] : [],
	   ( function_exists( 'sp_store_agreements_on' ) && sp_store_agreements_on() ) ? [ '{agreements_block}' ] : [],
	   ( function_exists( 'sp_store_downloads_on' ) && sp_store_downloads_on() ) ? [ '{downloads_block}' ] : [] );
}


/*--------------------------------------------------------------
# Queueing
--------------------------------------------------------------*/

/**
 * Put one email on the queue, then kick it.
 *
 * Does nothing when the template is off or there is no recipient — unless
 * $ignore_toggle, which is for a staff member deliberately pressing "send the
 * receipt again": an explicit request should not be swallowed by a setting.
 *
 * @param bool $replace drop this order's existing row for the template first, so a
 *                      deliberate resend gets past the UNIQUE key
 */
function sp_store_queue( int $order_id, string $template_key, string $recipient, array $payload = [], bool $replace = false, bool $ignore_toggle = false ): void {
	global $wpdb;
	if ( ! $order_id || $recipient === '' ) return;
	if ( ! $ignore_toggle && ! sp_store_notify_on( $template_key ) ) return;

	$table = sp_store_table( 'notifications' );
	if ( $replace ) {
		$wpdb->delete( $table, [ 'order_id' => $order_id, 'template_key' => $template_key, 'channel' => 'email' ] );
	}

	$now      = current_time( 'mysql', true );
	$suppress = $wpdb->suppress_errors( true );
	$wpdb->insert( $table, [
		'order_id'     => $order_id,
		'template_key' => $template_key,
		'channel'      => 'email',
		'recipient'    => mb_substr( $recipient, 0, 190 ),
		'send_at'      => $now,
		'status'       => 'queued',
		'payload'      => $payload ? wp_json_encode( $payload ) : null,
		'created_at'   => $now,
	] );
	$wpdb->suppress_errors( $suppress );

	sp_store_kick_queue();
}

/** Drain the queue once this request is over. Registering twice is harmless. */
function sp_store_kick_queue(): void {
	static $kicked = false;
	if ( $kicked ) return;
	$kicked = true;
	add_action( 'shutdown', 'sp_store_drain_after_response', 1 );
}

/**
 * Close the connection, THEN send. The reply — JSON to Site Pulse, "ok" to Stripe,
 * the thank-you page to the customer — has already been produced; flushing every
 * buffer and finishing the request lets it go before wp_mail() starts talking to
 * a mail server. Without FPM it simply sends inline, which is the old behaviour.
 */
function sp_store_drain_after_response(): void {
	if ( function_exists( 'fastcgi_finish_request' ) ) {
		ignore_user_abort( true );
		while ( ob_get_level() > 0 ) ob_end_flush();
		fastcgi_finish_request();
	}
	try {
		sp_store_send_due( 10 );
	} catch ( \Throwable $e ) {
		error_log( 'sp_store_drain_after_response: ' . $e->getMessage() );
	}
}


/*--------------------------------------------------------------
# Event Hooks
--------------------------------------------------------------*/

/** A paid order: the customer's receipt and the staff alert. */
add_action( 'sp_store_order_paid', 'sp_store_notify_paid', 20 );
function sp_store_notify_paid( int $order_id ): void {
	$order = sp_store_get_order( $order_id );
	if ( ! $order ) return;

	if ( is_email( (string) $order['customer_email'] ) ) {
		sp_store_queue( $order_id, 'receipt', (string) $order['customer_email'] );
	}
	sp_store_queue( $order_id, 'staff_alert', sp_store_staff_emails() );
}


/*--------------------------------------------------------------
# The Sweep — send what is due
--------------------------------------------------------------*/

/**
 * Send every queued email that is due, oldest first. Each row is CLAIMED with a
 * conditional UPDATE before it is sent, so two overlapping sweeps cannot both send
 * the same message: the loser's UPDATE affects nothing and it moves on.
 */
function sp_store_send_due( int $limit = 25 ): int {
	global $wpdb;
	$table = sp_store_table( 'notifications' );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $table WHERE status = 'queued' AND send_at <= %s ORDER BY send_at ASC LIMIT %d",
		current_time( 'mysql', true ), $limit
	), ARRAY_A ) ?: [];

	$sent = 0;
	foreach ( $rows as $row ) {
		$id = (int) $row['id'];

		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE $table SET status = 'sending', attempts = attempts + 1 WHERE id = %d AND status = 'queued'", $id
		) );
		if ( ! $claimed ) continue;

		$order = sp_store_get_order( (int) $row['order_id'] );
		if ( ! $order ) {
			$wpdb->update( $table, [ 'status' => 'cancelled', 'error' => 'Order no longer exists' ], [ 'id' => $id ] );
			continue;
		}

		$payload = json_decode( (string) $row['payload'], true );
		$ok      = sp_store_deliver( $order, (string) $row['template_key'], (string) $row['recipient'], is_array( $payload ) ? $payload : [] );

		if ( $ok ) {
			$wpdb->update( $table, [ 'status' => 'sent', 'sent_at' => current_time( 'mysql', true ), 'error' => '' ], [ 'id' => $id ] );
			$sent++;
		} else {
			// Three tries, then stop: a permanently bad address should not be retried forever.
			$final = ( (int) $row['attempts'] + 1 ) >= 3;
			$wpdb->update( $table, [ 'status' => $final ? 'failed' : 'queued', 'error' => 'Delivery failed' ], [ 'id' => $id ] );
		}
	}
	return $sent;
}

/** Render and send one email. False on any failure. */
function sp_store_deliver( array $order, string $template_key, string $recipient, array $payload = [] ): bool {
	$to = array_filter( array_map( 'trim', explode( ',', $recipient ) ), 'is_email' );
	if ( ! $to ) return false;

	$copy    = sp_store_notify_copy( $template_key );
	$tokens  = sp_store_tokens( $order, $payload );

	// A sign-in link is minted AT SEND TIME, never stored in the queue: it is a key
	// to someone's account, and it expires, so it should exist only in the email.
	// Only member-only accounts can have one (sp_member_make_link refuses staff).
	$tokens['{account_link}'] = '';
	if ( strpos( $copy['body'], '{account_link}' ) !== false && function_exists( 'sp_member_make_link' ) ) {
		$user = get_user_by( 'email', (string) $order['customer_email'] );
		$link = $user ? sp_member_make_link( (int) $user->ID, 'setpw' ) : '';
		$tokens['{account_link}'] = $link !== '' ? $link : ( function_exists( 'sp_member_account_url' ) ? sp_member_account_url() : '' );
	}
	$subject = strtr( $copy['subject'], $tokens );
	$body    = strtr( $copy['body'], $tokens );

	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
	$reply   = sp_store_get_setting( 'reply_to', '' );
	// A staff alert's reply goes to the customer, which is who staff actually want to answer.
	if ( $template_key === 'staff_alert' && is_email( (string) $order['customer_email'] ) ) {
		$headers[] = 'Reply-To: ' . $order['customer_email'];
	} elseif ( is_email( $reply ) ) {
		$headers[] = 'Reply-To: ' . $reply;
	}

	return (bool) wp_mail( implode( ',', $to ), $subject, wpautop( esc_html( $body ) ), $headers );
}

/**
 * The backstop sweep, at most every five minutes on ordinary traffic — for the
 * email that was queued on a host without FPM, or whose first try failed.
 */
add_action( 'init', 'sp_store_maybe_send_due', 22 );
function sp_store_maybe_send_due(): void {
	if ( ! sp_store_active() || is_admin() ) return;
	if ( get_transient( 'sp_store_notify_sweep' ) ) return;
	set_transient( 'sp_store_notify_sweep', 1, 5 * MINUTE_IN_SECONDS );
	sp_store_send_due();
}


/*--------------------------------------------------------------
# Settings AJAX
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_store_notify_settings', 'sp_store_ajax_notify_settings' );
function sp_store_ajax_notify_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	$out = [];
	foreach ( sp_store_notification_templates() as $key => $tpl ) {
		// An Extras email is only listed while its feature is on.
		if ( ( $tpl['feature'] ?? '' ) === 'memberships' && ! ( function_exists( 'sp_store_members_on' ) && sp_store_members_on() ) ) continue;
		$copy  = sp_store_notify_copy( $key );
		$out[] = [
			'key'     => $key,
			'label'   => $tpl['label'],
			'when'    => $tpl['when'],
			'to'      => $tpl['to'],
			'enabled' => sp_store_notify_on( $key ),
			'subject' => $copy['subject'],
			'body'    => $copy['body'],
		];
	}

	wp_send_json_success( [
		'templates'     => $out,
		'staff_email'   => sp_store_get_setting( 'staff_email', '' ),
		'staff_default' => sp_store_staff_emails(),
		'reply_to'      => sp_store_get_setting( 'reply_to', '' ),
		'tokens'        => sp_store_token_names(),
	] );
}

add_action( 'wp_ajax_site_pulse_store_save_notify', 'sp_store_ajax_save_notify' );
function sp_store_ajax_save_notify(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	$staff = array_filter( array_map( 'trim', explode( ',', wp_unslash( (string) ( $_POST['staff_email'] ?? '' ) ) ) ) );
	$bad   = array_filter( $staff, static fn( $e ) => ! is_email( $e ) );
	if ( $bad ) wp_send_json_error( [ 'message' => 'Not an email address: ' . implode( ', ', $bad ) ] );

	sp_store_set_setting( 'staff_email', implode( ', ', array_map( 'sanitize_email', $staff ) ) );
	sp_store_set_setting( 'reply_to', sanitize_email( wp_unslash( (string) ( $_POST['reply_to'] ?? '' ) ) ) );

	$posted = json_decode( wp_unslash( (string) ( $_POST['templates'] ?? '[]' ) ), true );
	if ( is_array( $posted ) ) {
		$known = sp_store_notification_templates();
		foreach ( $posted as $t ) {
			$key = sanitize_key( $t['key'] ?? '' );
			if ( ! isset( $known[ $key ] ) ) continue;
			sp_store_set_setting( 'notify_' . $key, empty( $t['enabled'] ) ? '0' : '1' );
			sp_store_set_setting( 'notify_subject_' . $key, sanitize_text_field( (string) ( $t['subject'] ?? '' ) ) );
			sp_store_set_setting( 'notify_body_' . $key, sanitize_textarea_field( (string) ( $t['body'] ?? '' ) ) );
		}
	}

	wp_send_json_success( [ 'message' => 'Email settings saved.' ] );
}

/**
 * Send one template to the person pressing the button, rendered against the most
 * recent real paid order. A real order rather than dummy text is the point: it
 * proves the tokens resolve and that mail actually leaves this server.
 */
add_action( 'wp_ajax_site_pulse_store_test_notify', 'sp_store_ajax_test_notify' );
function sp_store_ajax_test_notify(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	global $wpdb;
	$key = sanitize_key( $_POST['key'] ?? '' );
	if ( ! isset( sp_store_notification_templates()[ $key ] ) ) wp_send_json_error( [ 'message' => 'Unknown email.' ] );

	$order = $wpdb->get_row(
		"SELECT * FROM " . sp_store_table( 'orders' ) . " WHERE payment_status <> 'unpaid' ORDER BY id DESC LIMIT 1",
		ARRAY_A
	);
	if ( ! $order ) {
		wp_send_json_error( [ 'message' => 'Place one test order first. The test uses a real order so you see the real wording.' ] );
	}

	$user = get_userdata( get_current_user_id() );
	if ( ! $user || ! is_email( $user->user_email ) ) wp_send_json_error( [ 'message' => 'Your account has no email address.' ] );

	$ok = sp_store_deliver( $order, $key, $user->user_email, [ 'amount_cents' => (int) $order['total_cents'] ] );
	if ( ! $ok ) wp_send_json_error( [ 'message' => 'The site refused to send email. Check its mail setup (WP Mail SMTP).' ] );

	wp_send_json_success( [ 'message' => 'Test sent to ' . $user->user_email . '.' ] );
}
