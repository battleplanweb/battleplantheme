<?php
/* Battle Plan Web Design — Site Pulse: Booking Notifications

/*--------------------------------------------------------------
Confirmations, reminders, change notices and follow-ups.

Everything is QUEUED, never sent inline. Two reasons, and both are structural:

  1. This framework's cron is traffic-triggered — it fires when a bot happens to
     hit the site. "Send at 8am the day before" is a promise it cannot keep, so
     the queue stores WHEN a message is due and a sweep sends whatever is owed
     the next time anything runs. A late sweep sends a late reminder; a missed
     schedule sends nothing at all.
  2. A failed wp_mail() must not fail the booking. Taking the booking and
     telling the customer about it are separate concerns, and only the first one
     is allowed to fail loudly.

The queue's UNIQUE key (booking_id, template_key, channel) is what makes the
sweep safe to re-run: a duplicate enqueue is rejected by the database rather
than sending a second confirmation.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Templates + Settings
# Token Rendering
# Queueing
# Event Hooks (called by the booking handlers)
# The Sweep — send what is due
# Settings AJAX
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Templates + Settings
--------------------------------------------------------------*/

/**
 * The messages this module can send. `when` is documentation for the settings
 * screen; the actual scheduling lives in sp_book_notify_created() / _rescheduled() / _cancelled().
 *
 * Defaults are deliberately plain text — a client will rewrite them, and copy
 * that reads like a form letter is worse than copy that reads like the person
 * who runs the business.
 */
function sp_book_notification_templates(): array {
	return [
		'confirmation' => [
			'label'   => 'Booking confirmation',
			'when'    => 'Immediately, when a booking is made',
			'to'      => 'customer',
			'default' => true,
			'subject' => 'Your {service} is booked for {date}',
			'body'    => "Hi {customer_first},\n\nYou're booked in for {service} on {date} at {time}.\n\n{with_line}{address_line}Reference: {code}\n\nIf you need to change anything, just reply to this message.\n\n{business}",
		],
		'reminder' => [
			'label'   => 'Reminder',
			'when'    => 'A set number of hours before the appointment',
			'to'      => 'customer',
			'default' => true,
			'subject' => 'Reminder: {service} on {date}',
			'body'    => "Hi {customer_first},\n\nJust a reminder about your {service} on {date} at {time}.\n\n{with_line}{address_line}See you then.\n\n{business}",
		],
		'reschedule' => [
			'label'   => 'Changed time',
			'when'    => 'When a booking is moved',
			'to'      => 'customer',
			'default' => true,
			'subject' => 'Your {service} has moved to {date}',
			'body'    => "Hi {customer_first},\n\nYour {service} has been moved to {date} at {time}.\n\n{with_line}{address_line}Reference: {code}\n\n{business}",
		],
		'cancellation' => [
			'label'   => 'Cancellation notice',
			'when'    => 'When a booking is cancelled',
			'to'      => 'customer',
			'default' => true,
			'subject' => 'Your {service} on {date} has been cancelled',
			'body'    => "Hi {customer_first},\n\nYour {service} on {date} at {time} has been cancelled.\n\nIf that wasn't expected, get in touch and we'll sort it out.\n\n{business}",
		],
		'followup' => [
			'label'   => 'Follow-up',
			'when'    => 'A set number of hours after the appointment',
			'to'      => 'customer',
			'default' => false,
			'subject' => 'How was your {service}?',
			'body'    => "Hi {customer_first},\n\nThanks for coming in for {service}. We'd love to hear how it went.\n\n{business}",
		],
		'staff_alert' => [
			'label'   => 'New booking alert (staff)',
			'when'    => 'Immediately, when a booking is made',
			'to'      => 'staff',
			'default' => true,
			'subject' => 'New booking: {customer} — {date} {time}',
			'body'    => "{customer} booked {service} on {date} at {time}.\n\n{with_line}{address_line}{phone_line}{email_line}Reference: {code}",
		],
	];
}

/** Is a template switched on? Falls back to the registry default. */
function sp_book_notify_on( string $key ): bool {
	$tpl = sp_book_notification_templates()[ $key ] ?? null;
	if ( ! $tpl ) return false;
	$saved = sp_book_get_setting( 'notify_' . $key, '' );
	if ( $saved === '' ) return ! empty( $tpl['default'] );
	return $saved === '1';
}

/** The stored subject/body for a template, falling back to the registry copy. */
function sp_book_notify_copy( string $key ): array {
	$tpl = sp_book_notification_templates()[ $key ] ?? [ 'subject' => '', 'body' => '' ];
	$subject = sp_book_get_setting( 'notify_subject_' . $key, '' );
	$body    = sp_book_get_setting( 'notify_body_' . $key, '' );
	return [
		'subject' => $subject !== '' ? $subject : (string) $tpl['subject'],
		'body'    => $body !== '' ? $body : (string) $tpl['body'],
	];
}

/** Hours before the appointment that the reminder is due. */
function sp_book_reminder_hours(): int {
	return max( 1, (int) sp_book_get_setting( 'reminder_hours', '24' ) );
}

/** Hours after the appointment ends that the follow-up is due. */
function sp_book_followup_hours(): int {
	return max( 1, (int) sp_book_get_setting( 'followup_hours', '24' ) );
}

/** Where staff alerts go. Falls back to the resource's email, then the site admin. */
function sp_book_staff_email( ?array $resource ): string {
	$set = sp_book_get_setting( 'staff_email', '' );
	if ( is_email( $set ) ) return $set;
	if ( $resource && is_email( (string) $resource['email'] ) ) return (string) $resource['email'];
	return (string) get_option( 'admin_email' );
}

/** SMS is opt-in AND requires the AI Chat module's Twilio config to be present. */
function sp_book_sms_available(): bool {
	return function_exists( 'bp_chat_send_sms' ) && sp_book_get_setting( 'notify_sms', '0' ) === '1';
}


/*--------------------------------------------------------------
# Token Rendering
--------------------------------------------------------------*/

/**
 * The token map for one booking. The `*_line` tokens resolve to a whole line
 * (with its trailing newline) or to an empty string — so a booking with no
 * address doesn't leave "Address:" hanging over a blank, which is the usual way
 * templated copy looks broken.
 */
function sp_book_tokens( array $booking ): array {
	$service  = sp_book_get_service( (int) $booking['service_id'] );
	$resource = sp_book_get_resource( (int) $booking['resource_id'] );

	$name  = trim( (string) $booking['customer_name'] );
	$first = $name !== '' ? preg_split( '/\s+/', $name )[0] : 'there';

	$address = trim( implode( ' ', array_filter( [
		(string) $booking['address'], (string) $booking['city'],
		trim( (string) $booking['state'] . ' ' . (string) $booking['zip'] ),
	] ) ) );

	$business = bp_business_name();
	$with     = $resource ? (string) $resource['name'] : '';

	return [
		'{customer}'       => $name,
		'{customer_first}' => $first,
		'{service}'        => $service ? (string) $service['name'] : 'your appointment',
		'{date}'           => sp_book_to_local( (string) $booking['starts_at'], 'l, F j' ),
		'{date_short}'     => sp_book_to_local( (string) $booking['starts_at'], 'n/j/Y' ),
		'{time}'           => sp_book_to_local( (string) $booking['starts_at'], 'g:i A' ),
		'{end_time}'       => sp_book_to_local( (string) $booking['ends_at'], 'g:i A' ),
		'{resource}'       => $with,
		'{business}'       => $business,
		'{code}'           => (string) $booking['code'],
		'{address}'        => $address,
		'{phone}'          => (string) $booking['customer_phone'],
		'{email}'          => (string) $booking['customer_email'],
		'{with_line}'      => $with !== '' ? 'With: ' . $with . "\n" : '',
		'{address_line}'   => $address !== '' ? 'Where: ' . $address . "\n" : '',
		'{phone_line}'     => $booking['customer_phone'] !== '' ? 'Phone: ' . $booking['customer_phone'] . "\n" : '',
		'{email_line}'     => $booking['customer_email'] !== '' ? 'Email: ' . $booking['customer_email'] . "\n" : '',
	];
}

function sp_book_render( string $text, array $tokens ): string {
	return strtr( $text, $tokens );
}


/*--------------------------------------------------------------
# Queueing
--------------------------------------------------------------*/

/**
 * Put one message on the queue. Silently does nothing when the template is off,
 * there is no recipient, or the row already exists — the UNIQUE key on
 * (booking, template, channel) makes a repeat enqueue a no-op rather than a
 * second send.
 */
function sp_book_queue( int $booking_id, string $template_key, string $send_at_utc, string $channel, string $recipient ): void {
	global $wpdb;
	if ( ! $booking_id || $recipient === '' ) return;
	if ( ! sp_book_notify_on( $template_key ) ) return;

	$suppress = $wpdb->suppress_errors( true );
	$wpdb->insert( sp_book_table( 'notifications' ), [
		'booking_id'   => $booking_id,
		'template_key' => $template_key,
		'channel'      => $channel,
		'recipient'    => $recipient,
		'send_at'      => $send_at_utc,
		'status'       => 'queued',
		'created_at'   => sp_book_now(),
	] );
	$wpdb->suppress_errors( $suppress );
}

/** Drop a booking's still-unsent messages — used when its time changes. */
function sp_book_dequeue_pending( int $booking_id, array $keys = [] ): void {
	global $wpdb;
	$table = sp_book_table( 'notifications' );
	if ( $keys ) {
		$ph = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM $table WHERE booking_id = %d AND status = 'queued' AND template_key IN ( $ph )",
			array_merge( [ $booking_id ], $keys )
		) );
	} else {
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE booking_id = %d AND status = 'queued'", $booking_id ) );
	}
}


/*--------------------------------------------------------------
# Event Hooks (called by the booking handlers)
--------------------------------------------------------------*/

/**
 * Queue everything a NEW booking should trigger: the confirmation now, the
 * reminder before it, the follow-up after, and the staff alert.
 *
 * A reminder whose due time has already passed is simply not queued — a
 * booking made two hours out shouldn't fire a "tomorrow" reminder immediately.
 */
function sp_book_notify_created( array $booking ): void {
	$id    = (int) $booking['id'];
	$now   = time();
	$start = sp_book_utc_ts( (string) $booking['starts_at'] );
	$end   = sp_book_utc_ts( (string) $booking['ends_at'] );
	$email = (string) $booking['customer_email'];
	$phone = (string) $booking['customer_phone'];

	if ( is_email( $email ) ) {
		sp_book_queue( $id, 'confirmation', sp_book_now(), 'email', $email );

		$due = $start - sp_book_reminder_hours() * HOUR_IN_SECONDS;
		if ( $due > $now ) sp_book_queue( $id, 'reminder', gmdate( 'Y-m-d H:i:s', $due ), 'email', $email );

		$follow = $end + sp_book_followup_hours() * HOUR_IN_SECONDS;
		sp_book_queue( $id, 'followup', gmdate( 'Y-m-d H:i:s', $follow ), 'email', $email );
	}

	if ( $phone !== '' && sp_book_sms_available() ) {
		$due = $start - sp_book_reminder_hours() * HOUR_IN_SECONDS;
		if ( $due > $now ) sp_book_queue( $id, 'reminder', gmdate( 'Y-m-d H:i:s', $due ), 'sms', $phone );
	}

	$staff = sp_book_staff_email( sp_book_get_resource( (int) $booking['resource_id'] ) );
	if ( is_email( $staff ) ) sp_book_queue( $id, 'staff_alert', sp_book_now(), 'email', $staff );
}

/**
 * A moved booking: tell the customer, and re-time the reminder. The old
 * reminder row is DELETED rather than updated — its send_at belonged to a time
 * that no longer exists, and deleting frees the UNIQUE slot so the new one can
 * take its place.
 */
function sp_book_notify_rescheduled( array $booking ): void {
	$id    = (int) $booking['id'];
	$start = sp_book_utc_ts( (string) $booking['starts_at'] );
	$end   = sp_book_utc_ts( (string) $booking['ends_at'] );
	$email = (string) $booking['customer_email'];
	$phone = (string) $booking['customer_phone'];

	sp_book_dequeue_pending( $id, [ 'reminder', 'followup', 'reschedule' ] );

	if ( is_email( $email ) ) {
		sp_book_queue( $id, 'reschedule', sp_book_now(), 'email', $email );

		$due = $start - sp_book_reminder_hours() * HOUR_IN_SECONDS;
		if ( $due > time() ) sp_book_queue( $id, 'reminder', gmdate( 'Y-m-d H:i:s', $due ), 'email', $email );

		sp_book_queue( $id, 'followup', gmdate( 'Y-m-d H:i:s', $end + sp_book_followup_hours() * HOUR_IN_SECONDS ), 'email', $email );
	}

	if ( $phone !== '' && sp_book_sms_available() ) {
		$due = $start - sp_book_reminder_hours() * HOUR_IN_SECONDS;
		if ( $due > time() ) sp_book_queue( $id, 'reminder', gmdate( 'Y-m-d H:i:s', $due ), 'sms', $phone );
	}
}

/**
 * A cancelled booking. Order matters: sp_book_release() has already marked this
 * booking's queued messages `cancelled` (so no reminder goes out for something
 * that isn't happening), so the cancellation notice is queued AFTER that — it
 * would otherwise be cancelled by the very act that created it.
 */
function sp_book_notify_cancelled( int $booking_id ): void {
	$booking = sp_book_get_booking( $booking_id );
	if ( ! $booking ) return;
	$email = (string) $booking['customer_email'];
	if ( is_email( $email ) ) {
		sp_book_queue( $booking_id, 'cancellation', sp_book_now(), 'email', $email );
	}
}


/*--------------------------------------------------------------
# The Sweep — send what is due
--------------------------------------------------------------*/

/**
 * Send every queued message that is due, oldest first. Returns how many were
 * sent.
 *
 * Each row is claimed with a conditional UPDATE before it is sent, so two
 * overlapping sweeps (perfectly possible on a busy site) can't both send the
 * same message: the second one's UPDATE affects zero rows and it moves on.
 */
function sp_book_send_due( int $limit = 25 ): int {
	global $wpdb;
	$table = sp_book_table( 'notifications' );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $table WHERE status = 'queued' AND send_at <= %s ORDER BY send_at ASC LIMIT %d",
		sp_book_now(), $limit
	), ARRAY_A ) ?: [];

	$sent = 0;
	foreach ( $rows as $row ) {
		$id = (int) $row['id'];

		// Claim it. Zero affected rows means another sweep got there first.
		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE $table SET status = 'sending', attempts = attempts + 1 WHERE id = %d AND status = 'queued'",
			$id
		) );
		if ( ! $claimed ) continue;

		$booking = sp_book_get_booking( (int) $row['booking_id'] );
		if ( ! $booking ) {
			$wpdb->update( $table, [ 'status' => 'cancelled', 'error' => 'Booking no longer exists' ], [ 'id' => $id ] );
			continue;
		}

		$ok = sp_book_deliver( $booking, (string) $row['template_key'], (string) $row['channel'], (string) $row['recipient'] );

		if ( $ok ) {
			$wpdb->update( $table, [ 'status' => 'sent', 'sent_at' => sp_book_now(), 'error' => '' ], [ 'id' => $id ] );
			$sent++;
		} else {
			// Three tries, then stop — a permanently bad address shouldn't be retried forever.
			$final = ( (int) $row['attempts'] + 1 ) >= 3;
			$wpdb->update( $table, [
				'status' => $final ? 'failed' : 'queued',
				'error'  => 'Delivery failed',
			], [ 'id' => $id ] );
		}
	}

	return $sent;
}

/** Render and deliver one message. Returns false on any delivery failure. */
function sp_book_deliver( array $booking, string $template_key, string $channel, string $recipient ): bool {
	$copy   = sp_book_notify_copy( $template_key );
	$tokens = sp_book_tokens( $booking );

	$subject = sp_book_render( $copy['subject'], $tokens );
	$body    = sp_book_render( $copy['body'], $tokens );

	if ( $channel === 'sms' ) {
		if ( ! sp_book_sms_available() ) return false;
		// SMS gets the body only — a subject line in a text message reads as noise.
		return (bool) bp_chat_send_sms( $recipient, $body );
	}

	if ( ! is_email( $recipient ) ) return false;

	$html    = wpautop( esc_html( $body ) );
	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
	$reply   = sp_book_get_setting( 'reply_to', '' );
	if ( is_email( $reply ) ) $headers[] = 'Reply-To: ' . $reply;

	return (bool) wp_mail( $recipient, $subject, $html, $headers );
}


/*--------------------------------------------------------------
# Settings AJAX
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_booking_notify_settings', 'sp_book_ajax_notify_settings' );
function sp_book_ajax_notify_settings(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	$out = [];
	foreach ( sp_book_notification_templates() as $key => $tpl ) {
		$copy  = sp_book_notify_copy( $key );
		$out[] = [
			'key'     => $key,
			'label'   => $tpl['label'],
			'when'    => $tpl['when'],
			'to'      => $tpl['to'],
			'enabled' => sp_book_notify_on( $key ),
			'subject' => $copy['subject'],
			'body'    => $copy['body'],
		];
	}

	wp_send_json_success( [
		'templates'      => $out,
		'reminder_hours' => sp_book_reminder_hours(),
		'followup_hours' => sp_book_followup_hours(),
		'staff_email'    => sp_book_get_setting( 'staff_email', '' ),
		'reply_to'       => sp_book_get_setting( 'reply_to', '' ),
		'sms_enabled'    => sp_book_get_setting( 'notify_sms', '0' ) === '1',
		'sms_available'  => function_exists( 'bp_chat_send_sms' ),
		'tokens'         => array_keys( sp_book_tokens( [
			'customer_name' => '', 'service_id' => 0, 'resource_id' => 0, 'starts_at' => sp_book_now(),
			'ends_at' => sp_book_now(), 'code' => '', 'address' => '', 'city' => '', 'state' => '',
			'zip' => '', 'customer_phone' => '', 'customer_email' => '',
		] ) ),
	] );
}

add_action( 'wp_ajax_site_pulse_booking_save_notify', 'sp_book_ajax_save_notify' );
function sp_book_ajax_save_notify(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	sp_book_set_setting( 'reminder_hours', (string) max( 1, (int) ( $_POST['reminder_hours'] ?? 24 ) ) );
	sp_book_set_setting( 'followup_hours', (string) max( 1, (int) ( $_POST['followup_hours'] ?? 24 ) ) );
	sp_book_set_setting( 'staff_email', sanitize_email( wp_unslash( (string) ( $_POST['staff_email'] ?? '' ) ) ) );
	sp_book_set_setting( 'reply_to', sanitize_email( wp_unslash( (string) ( $_POST['reply_to'] ?? '' ) ) ) );
	sp_book_set_setting( 'notify_sms', empty( $_POST['sms_enabled'] ) ? '0' : '1' );

	$posted = json_decode( wp_unslash( (string) ( $_POST['templates'] ?? '[]' ) ), true );
	if ( is_array( $posted ) ) {
		$known = sp_book_notification_templates();
		foreach ( $posted as $t ) {
			$key = sanitize_key( $t['key'] ?? '' );
			if ( ! isset( $known[ $key ] ) ) continue;
			sp_book_set_setting( 'notify_' . $key, empty( $t['enabled'] ) ? '0' : '1' );
			sp_book_set_setting( 'notify_subject_' . $key, sanitize_text_field( (string) ( $t['subject'] ?? '' ) ) );
			sp_book_set_setting( 'notify_body_' . $key, sanitize_textarea_field( (string) ( $t['body'] ?? '' ) ) );
		}
	}

	wp_send_json_success( [ 'message' => 'Notification settings saved.' ] );
}

/**
 * Send one template to the logged-in user, rendered against the most recent
 * real booking. Using a real row rather than dummy text is the point: it proves
 * the tokens resolve and the mail actually leaves the server.
 */
add_action( 'wp_ajax_site_pulse_booking_test_notify', 'sp_book_ajax_test_notify' );
function sp_book_ajax_test_notify(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;

	global $wpdb;
	$key = sanitize_key( $_POST['key'] ?? '' );
	if ( ! isset( sp_book_notification_templates()[ $key ] ) ) {
		wp_send_json_error( [ 'message' => 'Unknown template.' ] );
	}

	$row = $wpdb->get_row( "SELECT * FROM " . sp_book_table( 'bookings' ) . " ORDER BY id DESC LIMIT 1", ARRAY_A );
	if ( ! $row ) {
		wp_send_json_error( [ 'message' => 'Make one booking first — the test renders against a real booking so you can see the real wording.' ] );
	}

	$user = get_userdata( get_current_user_id() );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		wp_send_json_error( [ 'message' => 'Your user account has no email address.' ] );
	}

	$ok = sp_book_deliver( $row, $key, 'email', $user->user_email );
	if ( ! $ok ) wp_send_json_error( [ 'message' => 'wp_mail() refused to send. Check the site\'s mail configuration.' ] );

	wp_send_json_success( [ 'message' => 'Test sent to ' . $user->user_email ] );
}
