<?php
/* Battle Plan Web Design — Site Pulse: Passes, Packs & Memberships

/*--------------------------------------------------------------
Three shapes of prepaid entitlement, all on the `passes` table:

  pack        A finite number of credits. Buy 10 sessions, use them, gone.
  unlimited   A membership with no cap.
  allowance   A membership that includes N appointments PER PERIOD, resetting
              every period — "$400 a month, two 30-minute sessions a week".

The third is the interesting one, and two decisions define it.

1. USAGE IS DERIVED, NEVER COUNTED. There is no per-period counter to reset.
   The allowance used in any window is a COUNT of redemptions whose booking
   falls inside that window. Nothing has to run on a timer (this framework's
   cron is traffic-triggered and cannot promise a turnover minute), the figure
   is always correct even if no job has run for a month, and a cancelled
   booking frees its slot automatically because it stops matching — no credit
   refund path to get wrong.

2. USAGE IS COUNTED BY APPOINTMENT DATE, NOT BOOKING DATE. "Two a week" means
   two appointments in that week. If a customer books three of next week's
   slots today, they belong to next week. Counting when the redemption was
   created looks identical until someone books ahead, and then it is silently
   wrong.

The ALLOWANCE period and the BILLING period are independent. Billing lives on
the Stripe subscription (provider_subscription / current_period_end); the
allowance resets on its own weekly or monthly cadence. Conflating them is why
this needed its own columns rather than reusing credits_total.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shape helpers
# The allowance window
# Usage + remaining
# Can this pass cover a booking?
# Redeem / release
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Shape helpers
--------------------------------------------------------------*/

function sp_book_pass_periods(): array {
	return [ 'week' => 'Every week', 'month' => 'Every month' ];
}

function sp_book_pass_overages(): array {
	return [
		'block'  => 'Refuse the booking once the allowance is used up',
		'allow'  => 'Allow it anyway (not counted against the allowance)',
		'charge' => 'Allow it and charge the normal price',
	];
}

/** A recurring allowance, as opposed to a finite pack or an unlimited membership. */
function sp_book_pass_is_allowance( array $pass ): bool {
	return (int) $pass['allowance'] > 0 && ! (int) $pass['unlimited'];
}

function sp_book_get_pass( int $id ): ?array {
	global $wpdb;
	if ( ! $id ) return null;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'passes' ) . " WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

/** A pass is live if it is active and today sits inside its start/expiry dates. */
function sp_book_pass_is_live( array $pass, ?int $at_ts = null ): bool {
	if ( (string) $pass['status'] !== 'active' ) return false;
	$at    = $at_ts ?? time();
	$date  = sp_book_to_local( gmdate( 'Y-m-d H:i:s', $at ), 'Y-m-d' );
	$start = (string) ( $pass['starts_on'] ?? '' );
	$end   = (string) ( $pass['expires_on'] ?? '' );
	if ( $start !== '' && $start !== '0000-00-00' && $date < $start ) return false;
	if ( $end   !== '' && $end   !== '0000-00-00' && $date > $end )   return false;
	return true;
}


/*--------------------------------------------------------------
# The allowance window
--------------------------------------------------------------*/

/**
 * The allowance period containing $at_ts, as [start_ts, end_ts) in UTC.
 *
 * The window is anchored, not calendar-generic: a membership that starts on a
 * Wednesday runs Wednesday-to-Wednesday, because "two a week" from the
 * customer's point of view means two per week OF THEIR MEMBERSHIP, not two per
 * Sunday-to-Saturday block they never agreed to. allowance_anchor overrides;
 * otherwise starts_on is the anchor.
 */
function sp_book_pass_window( array $pass, int $at_ts ): array {
	$tz     = sp_book_tz();
	$period = (string) $pass['allowance_period'] === 'week' ? 'week' : 'month';

	$anchor_date = (string) ( $pass['allowance_anchor'] ?? '' );
	if ( $anchor_date === '' || $anchor_date === '0000-00-00' ) {
		$anchor_date = (string) ( $pass['starts_on'] ?? '' );
	}
	if ( $anchor_date === '' || $anchor_date === '0000-00-00' ) {
		$anchor_date = sp_book_to_local( (string) $pass['created_at'], 'Y-m-d' );
	}

	try {
		$anchor = new DateTime( $anchor_date . ' 00:00:00', $tz );
		$at     = new DateTime( sp_book_to_local( gmdate( 'Y-m-d H:i:s', $at_ts ), 'Y-m-d' ) . ' 00:00:00', $tz );
	} catch ( Exception $e ) {
		return [ 0, PHP_INT_MAX ];
	}

	if ( $period === 'week' ) {
		// Whole weeks since the anchor, floored — works for dates before the anchor too.
		$days  = (int) floor( ( $at->getTimestamp() - $anchor->getTimestamp() ) / DAY_IN_SECONDS );
		$weeks = (int) floor( $days / 7 );
		$start = ( clone $anchor )->modify( sprintf( '%+d days', $weeks * 7 ) );
		$end   = ( clone $start )->modify( '+7 days' );
	} else {
		$months = ( ( (int) $at->format( 'Y' ) - (int) $anchor->format( 'Y' ) ) * 12 )
		        + ( (int) $at->format( 'n' ) - (int) $anchor->format( 'n' ) );
		if ( (int) $at->format( 'j' ) < (int) $anchor->format( 'j' ) ) $months--;
		$start = ( clone $anchor )->modify( sprintf( '%+d months', $months ) );
		$end   = ( clone $start )->modify( '+1 month' );
	}

	return [ $start->getTimestamp(), $end->getTimestamp() ];
}


/*--------------------------------------------------------------
# Usage + remaining
--------------------------------------------------------------*/

/**
 * How much of the allowance is used inside a window — counted from the
 * redemption ledger, joined to the bookings, and filtered to bookings that are
 * still happening. A cancelled or expired booking stops counting the moment it
 * is released, which is why there is no "give the credit back" step anywhere.
 */
function sp_book_pass_used_in_period( int $pass_id, array $window, int $ignore_booking_id = 0 ): int {
	global $wpdb;
	[ $from, $to ] = $window;

	$statuses = sp_book_active_statuses();
	$ph       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

	$params = array_merge(
		[ $pass_id ],
		$statuses,
		[ gmdate( 'Y-m-d H:i:s', $from ), gmdate( 'Y-m-d H:i:s', $to ), $ignore_booking_id ]
	);

	// NB: the window is applied to b.starts_at (the APPOINTMENT), never r.created_at.
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE( SUM( r.credits ), 0 )
		 FROM " . sp_book_table( 'pass_redemptions' ) . " r
		 INNER JOIN " . sp_book_table( 'bookings' ) . " b ON b.id = r.booking_id
		 WHERE r.pass_id = %d AND r.refunded = 0
		   AND b.status IN ( $ph )
		   AND b.starts_at >= %s AND b.starts_at < %s
		   AND b.id != %d",
		$params
	) );
}

/**
 * The allowance available for the period containing $at_ts, rollover included.
 *
 * Rollover carries the PREVIOUS period's unused balance forward one step only,
 * capped by rollover_max (0 = uncapped). Deliberately not cumulative: a
 * membership that silently banks a year of unused sessions is a liability
 * nobody agreed to, and every client who has asked for rollover has meant
 * "don't punish me for one missed week".
 */
function sp_book_pass_allowance_for( array $pass, int $at_ts ): int {
	$base = max( 0, (int) $pass['allowance'] );
	if ( ! (int) $pass['rollover'] ) return $base;

	$window = sp_book_pass_window( $pass, $at_ts );
	$prev   = sp_book_pass_window( $pass, $window[0] - 1 );

	// Only carry from a period the membership was actually live for.
	if ( ! sp_book_pass_is_live( $pass, $prev[0] ) ) return $base;

	$unused = $base - sp_book_pass_used_in_period( (int) $pass['id'], $prev );
	if ( $unused <= 0 ) return $base;

	$cap = (int) $pass['rollover_max'];
	if ( $cap > 0 ) $unused = min( $unused, $cap );

	return $base + $unused;
}

/**
 * Sessions left on this pass for the period containing $at_ts.
 * PHP_INT_MAX for unlimited; for a pack it is the lifetime remainder.
 */
function sp_book_pass_remaining( array $pass, int $at_ts, int $ignore_booking_id = 0 ): int {
	if ( (int) $pass['unlimited'] ) return PHP_INT_MAX;

	if ( sp_book_pass_is_allowance( $pass ) ) {
		$window = sp_book_pass_window( $pass, $at_ts );
		$used   = sp_book_pass_used_in_period( (int) $pass['id'], $window, $ignore_booking_id );
		return max( 0, sp_book_pass_allowance_for( $pass, $at_ts ) - $used );
	}

	// Pack: a lifetime pool, so the window is the whole life of the pass.
	$used = sp_book_pass_used_in_period( (int) $pass['id'], [ 0, PHP_INT_MAX ], $ignore_booking_id );
	return max( 0, (int) $pass['credits_total'] - $used );
}


/*--------------------------------------------------------------
# Can this pass cover a booking?
--------------------------------------------------------------*/

/**
 * Whether $pass can pay for an appointment starting at $start_ts.
 * Returns [ bool $ok, string $reason, int $remaining ].
 *
 * The check is made against the period the APPOINTMENT falls in, so booking
 * ahead consumes next week's allowance rather than this week's.
 */
function sp_book_pass_can_cover( array $pass, int $service_id, int $start_ts, int $ignore_booking_id = 0 ): array {
	if ( ! sp_book_pass_is_live( $pass, $start_ts ) ) {
		return [ false, 'That membership is not active for that date.', 0 ];
	}
	if ( (int) $pass['service_id'] && (int) $pass['service_id'] !== $service_id ) {
		return [ false, 'That membership does not cover this service.', 0 ];
	}

	$remaining = sp_book_pass_remaining( $pass, $start_ts, $ignore_booking_id );
	if ( $remaining > 0 ) return [ true, '', $remaining ];

	// Out of allowance — what happens next is the client's policy, not a hard rule.
	$overage = (string) $pass['overage'];
	if ( $overage === 'allow' || $overage === 'charge' ) {
		return [ true, '', 0 ];
	}

	$period = (string) $pass['allowance_period'] === 'week' ? 'week' : 'month';
	return [ false, sprintf( 'This membership\'s %d appointments for that %s are already booked.',
		(int) $pass['allowance'], $period ), 0 ];
}

/** Does this booking consume allowance, or is it an over-limit extra? */
function sp_book_pass_counts_against( array $pass, int $service_id, int $start_ts, int $ignore_booking_id = 0 ): bool {
	if ( (int) $pass['unlimited'] ) return false;
	return sp_book_pass_remaining( $pass, $start_ts, $ignore_booking_id ) > 0;
}


/*--------------------------------------------------------------
# Redeem / release
--------------------------------------------------------------*/

/**
 * Record that a booking was paid for with a pass.
 *
 * The UNIQUE key on pass_redemptions.booking_id is what makes this safe to call
 * twice: a repeat insert is rejected by the database rather than spending a
 * second session. Nothing decrements a counter, so there is nothing to
 * un-decrement if the booking is later cancelled.
 */
function sp_book_pass_redeem( int $pass_id, int $booking_id, int $credits = 1 ): bool {
	global $wpdb;
	if ( ! $pass_id || ! $booking_id ) return false;

	$suppress = $wpdb->suppress_errors( true );
	$ok = $wpdb->insert( sp_book_table( 'pass_redemptions' ), [
		'pass_id'    => $pass_id,
		'booking_id' => $booking_id,
		'credits'    => max( 1, $credits ),
		'created_at' => sp_book_now(),
	] );
	$wpdb->suppress_errors( $suppress );

	if ( $ok ) $wpdb->update( sp_book_table( 'bookings' ), [ 'pass_id' => $pass_id ], [ 'id' => $booking_id ] );
	return (bool) $ok;
}

/**
 * The passes a customer could pay with for a given service and time, best first
 * (a pass with allowance left before one without). Used by the booking flow to
 * offer "use your membership" instead of asking for payment.
 */
function sp_book_customer_passes( int $customer_id, int $service_id, int $start_ts, string $email = '' ): array {
	global $wpdb;
	$email = strtolower( trim( $email ) );
	if ( ! $customer_id && $email === '' ) return [];

	// Match on the account when there is one, otherwise on email — the same dual identity the
	// bookings table uses, so memberships work on a site running Site Pulse without Customer Connect.
	$who    = $customer_id ? 'p.customer_id = %d' : 'LOWER( p.customer_email ) = %s';
	$who_val = $customer_id ?: $email;

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.* FROM " . sp_book_table( 'passes' ) . " p
		 WHERE $who AND p.status = 'active' AND ( p.service_id = 0 OR p.service_id = %d )
		 ORDER BY p.id ASC",
		$who_val, $service_id
	), ARRAY_A ) ?: [];

	$out = [];
	foreach ( $rows as $pass ) {
		[ $ok, $why, $left ] = sp_book_pass_can_cover( $pass, $service_id, $start_ts );
		if ( ! $ok ) continue;
		$out[] = [
			'id'        => (int) $pass['id'],
			'name'      => (string) $pass['name'],
			'remaining' => $left,
			'unlimited' => (int) $pass['unlimited'] === 1,
			'allowance' => sp_book_pass_is_allowance( $pass ),
			'period'    => (string) $pass['allowance_period'],
		];
	}

	// Most constrained first, so an allowance is spent before an unlimited pass absorbs it.
	usort( $out, fn( $a, $b ) => ( $a['unlimited'] <=> $b['unlimited'] ) ?: ( $a['remaining'] <=> $b['remaining'] ) );
	return $out;
}


/*--------------------------------------------------------------
# Admin CRUD (Booking → Passes & Memberships)
--------------------------------------------------------------*/

/** Issuing and adjusting entitlements is a money decision, so it has its own capability. */
function sp_book_passes_gate(): bool {
	if ( ! site_pulse_module_on( 'booking' ) ) {
		wp_send_json_error( [ 'message' => 'The Booking module is switched off for this site.' ] );
		return false;
	}
	if ( sp_book_can_manage_payments() ) return true;
	wp_send_json_error( [ 'message' => 'You do not have permission to manage passes and memberships.' ] );
	return false;
}

/** A pass shaped for the list, with its live remaining balance computed. */
function sp_book_pass_shape( array $p, array $services ): array {
	$now = time();

	if ( (int) $p['unlimited'] ) {
		$balance = 'Unlimited';
	} elseif ( sp_book_pass_is_allowance( $p ) ) {
		$left    = sp_book_pass_remaining( $p, $now );
		$period  = (string) $p['allowance_period'] === 'week' ? 'week' : 'month';
		$balance = sprintf( '%d of %d left this %s', $left, sp_book_pass_allowance_for( $p, $now ), $period );
	} else {
		$balance = sprintf( '%d of %d left', sp_book_pass_remaining( $p, $now ), (int) $p['credits_total'] );
	}

	$window = sp_book_pass_is_allowance( $p ) ? sp_book_pass_window( $p, $now ) : null;

	return [
		'id'             => (int) $p['id'],
		'name'           => (string) $p['name'],
		'customer'       => (string) $p['customer_name'],
		'customer_email' => (string) $p['customer_email'],
		'customer_phone' => (string) $p['customer_phone'],
		'customer_id'    => (int) $p['customer_id'],
		'service_id'     => (int) $p['service_id'],
		'service'        => $services[ (int) $p['service_id'] ] ?? 'Any service',
		'pass_type'      => (string) $p['pass_type'],
		'unlimited'      => (int) $p['unlimited'],
		'credits_total'  => (int) $p['credits_total'],
		'allowance'      => (int) $p['allowance'],
		'period'         => (string) $p['allowance_period'],
		'anchor'         => (string) ( $p['allowance_anchor'] ?: '' ),
		'rollover'       => (int) $p['rollover'],
		'rollover_max'   => (int) $p['rollover_max'],
		'overage'        => (string) $p['overage'],
		'starts_on'      => (string) ( $p['starts_on'] ?: '' ),
		'expires_on'     => (string) ( $p['expires_on'] ?: '' ),
		'status'         => (string) $p['status'],
		'price'          => (int) $p['price_cents'] ? number_format( $p['price_cents'] / 100, 2 ) : '',
		'note'           => (string) $p['note'],
		'balance'        => $balance,
		'period_label'   => $window
			? sp_book_to_local( gmdate( 'Y-m-d H:i:s', $window[0] ), 'M j' ) . ' – '
			  . sp_book_to_local( gmdate( 'Y-m-d H:i:s', $window[1] - 1 ), 'M j' )
			: '',
	];
}

add_action( 'wp_ajax_site_pulse_booking_passes', 'sp_book_ajax_passes' );
function sp_book_ajax_passes(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_passes_gate() ) return;

	global $wpdb;
	$services = sp_book_service_names();

	$rows = $wpdb->get_results(
		"SELECT * FROM " . sp_book_table( 'passes' ) . " ORDER BY status = 'active' DESC, id DESC LIMIT 300",
		ARRAY_A
	) ?: [];

	$out = [];
	foreach ( $rows as $p ) $out[] = sp_book_pass_shape( $p, $services );

	// Customer suggestions come from people who have actually booked — which is the normal
	// order of events (book first, buy a membership after) and saves retyping their details.
	$people = $wpdb->get_results(
		"SELECT customer_name, customer_email, customer_phone, MAX(id) AS last_id
		 FROM " . sp_book_table( 'bookings' ) . "
		 WHERE customer_name != ''
		 GROUP BY customer_name, customer_email, customer_phone
		 ORDER BY last_id DESC LIMIT 200",
		ARRAY_A
	) ?: [];

	$svc_list = [];
	foreach ( $services as $id => $name ) $svc_list[] = [ 'id' => $id, 'name' => $name ];

	$plans = [];
	foreach ( sp_book_active_plans() as $pl ) {
		$plans[] = [
			'id'      => (int) $pl['id'],
			'name'    => (string) $pl['name'],
			'summary' => sp_book_plan_summary( $pl ),
		];
	}

	wp_send_json_success( [
		'passes'   => $out,
		'plans'    => $plans,
		'services' => $svc_list,
		'people'   => $people,
		'periods'  => sp_book_pass_periods(),
		'overages' => sp_book_pass_overages(),
		'today'    => sp_book_to_local( sp_book_now(), 'Y-m-d' ),
	] );
}

add_action( 'wp_ajax_site_pulse_booking_save_pass', 'sp_book_ajax_save_pass' );
function sp_book_ajax_save_pass(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_passes_gate() ) return;

	global $wpdb;
	$id   = (int) ( $_POST['id'] ?? 0 );
	$name = sp_book_post( 'name' );
	$who  = sp_book_post( 'customer_name' );

	if ( $who === '' ) {
		wp_send_json_error( [ 'message' => 'A customer name is required.' ] );
	}
	if ( $name === '' ) $name = 'Membership';

	$starts = sp_book_date_value( sp_book_post( 'starts_on' ) );
	if ( $starts === '' ) $starts = sp_book_to_local( sp_book_now(), 'Y-m-d' );

	// Issuing from a PLAN takes the terms from the plan, SERVER-SIDE. The form only sends which
	// plan was chosen — never the numbers — so a stale form, an edited <select> or a tampered
	// request cannot issue a membership on terms the business does not offer. plan_id 0 is the
	// deliberate "Custom" escape hatch, and only then are the posted terms used.
	$plan_id = (int) ( $_POST['plan_id'] ?? 0 );
	$plan    = $plan_id ? sp_book_get_plan( $plan_id ) : null;
	if ( $plan_id && ! $plan ) {
		wp_send_json_error( [ 'message' => 'That membership plan no longer exists.' ] );
	}

	if ( $plan ) {
		$shape      = (string) $plan['shape'];
		$allowance  = (int) $plan['allowance'];
		$credits    = (int) $plan['credits_total'];
		$period     = (string) $plan['allowance_period'];
		$overage    = (string) $plan['overage'];
		$rollover   = (int) $plan['rollover'];
		$roll_max   = (int) $plan['rollover_max'];
		$service_id = (int) $plan['service_id'];
		$price      = (int) $plan['price_cents'];
		if ( $name === 'Membership' ) $name = (string) $plan['name'];

		// A pack that runs out N days after issue gets its end date computed from the start.
		$expires = sp_book_date_value( sp_book_post( 'expires_on' ) );
		if ( $expires === '' && (int) $plan['term_days'] > 0 ) {
			$expires = ( new DateTime( $starts ) )->modify( '+' . (int) $plan['term_days'] . ' days' )->format( 'Y-m-d' );
		}
	} else {
		// Custom: the three shapes are mutually exclusive and the form posts one of them.
		// Normalising here keeps an "unlimited pack with an allowance" out of the table.
		$shape = sp_book_post( 'shape', 'allowance' );
		if ( ! in_array( $shape, [ 'pack', 'unlimited', 'allowance' ], true ) ) $shape = 'allowance';

		$allowance = $shape === 'allowance' ? max( 1, (int) ( $_POST['allowance'] ?? 1 ) ) : 0;
		$credits   = $shape === 'pack'      ? max( 1, (int) ( $_POST['credits_total'] ?? 1 ) ) : 0;

		$period = sp_book_post( 'allowance_period', 'week' );
		if ( ! array_key_exists( $period, sp_book_pass_periods() ) ) $period = 'week';

		$overage = sp_book_post( 'overage', 'block' );
		if ( ! array_key_exists( $overage, sp_book_pass_overages() ) ) $overage = 'block';

		$rollover   = empty( $_POST['rollover'] ) ? 0 : 1;
		$roll_max   = max( 0, (int) ( $_POST['rollover_max'] ?? 0 ) );
		$service_id = (int) ( $_POST['service_id'] ?? 0 );
		$price      = sp_book_cents( sp_book_post( 'price' ) );
		$expires    = sp_book_date_value( sp_book_post( 'expires_on' ) );
	}

	$now  = sp_book_now();
	$data = [
		'customer_id'      => (int) ( $_POST['customer_id'] ?? 0 ),
		'customer_name'    => $who,
		'customer_email'   => sanitize_email( wp_unslash( (string) ( $_POST['customer_email'] ?? '' ) ) ),
		'customer_phone'   => sp_book_post( 'customer_phone' ),
		'plan_id'          => $plan_id,
		'service_id'       => $service_id,
		'pass_type'        => $shape === 'pack' ? 'pack' : 'membership',
		'name'             => $name,
		'credits_total'    => $credits,
		'unlimited'        => $shape === 'unlimited' ? 1 : 0,
		'allowance'        => $allowance,
		'allowance_period' => $period,
		// The anchor defaults to the start date, which is what makes "two a week" mean two per
		// week OF THIS MEMBERSHIP rather than per calendar week.
		'allowance_anchor' => sp_book_date_value( sp_book_post( 'allowance_anchor' ) ) ?: $starts,
		'rollover'         => $rollover,
		'rollover_max'     => $roll_max,
		'overage'          => $overage,
		'starts_on'        => $starts,
		'expires_on'       => $expires ?: null,
		'price_cents'      => $price,
		'status'           => sp_book_post( 'status', 'active' ) === 'cancelled' ? 'cancelled' : 'active',
		'note'             => sp_book_post( 'note' ),
		'updated_at'       => $now,
	];

	if ( $id ) {
		$wpdb->update( sp_book_table( 'passes' ), $data, [ 'id' => $id ] );
	} else {
		$data['created_at'] = $now;
		$data['created_by'] = site_pulse_effective_user_id();
		$data['source']     = 'staff';
		$wpdb->insert( sp_book_table( 'passes' ), $data );
		$id = (int) $wpdb->insert_id;
	}

	site_pulse_log( 'booking_pass_saved', sprintf( 'Saved %s for %s', $name, $who ) );
	wp_send_json_success( [ 'message' => 'Saved.', 'id' => $id ] );
}

/**
 * Cancel a pass rather than delete it. Its redemptions are the record of which bookings it
 * paid for; deleting the row would orphan them and silently change what past bookings cost.
 * A pass with no redemptions at all is safe to remove outright.
 */
add_action( 'wp_ajax_site_pulse_booking_delete_pass', 'sp_book_ajax_delete_pass' );
function sp_book_ajax_delete_pass(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_passes_gate() ) return;

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Bad request.' ] );

	$used = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . sp_book_table( 'pass_redemptions' ) . " WHERE pass_id = %d", $id
	) );

	if ( $used ) {
		$wpdb->update( sp_book_table( 'passes' ), [ 'status' => 'cancelled', 'updated_at' => sp_book_now() ], [ 'id' => $id ] );
		wp_send_json_success( [ 'message' => sprintf(
			'Cancelled. It has paid for %d booking%s, so it is kept for the record rather than deleted.',
			$used, $used === 1 ? '' : 's'
		) ] );
	}

	$wpdb->delete( sp_book_table( 'passes' ), [ 'id' => $id ] );
	wp_send_json_success( [ 'message' => 'Deleted.' ] );
}


/*--------------------------------------------------------------
# Membership PLANS — the products, defined once
#
# A plan is a template; a pass is one customer's instance of it. Issuing copies
# the plan's terms ONTO the pass rather than pointing at them, so editing a plan
# later cannot silently change what existing members already pay for.
--------------------------------------------------------------*/

function sp_book_plan_shapes(): array {
	return [
		'allowance' => 'A set number of sessions each week or month',
		'unlimited' => 'Unlimited sessions',
		'pack'      => 'A fixed number of sessions, then it runs out',
	];
}

function sp_book_get_plan( int $id ): ?array {
	global $wpdb;
	if ( ! $id ) return null;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'plans' ) . " WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

function sp_book_active_plans(): array {
	global $wpdb;
	return $wpdb->get_results(
		"SELECT * FROM " . sp_book_table( 'plans' ) . " WHERE status = 'active' ORDER BY display_order ASC, name ASC",
		ARRAY_A
	) ?: [];
}

/** A one-line description of what a plan gives, for pickers and summaries. */
function sp_book_plan_summary( array $plan ): string {
	$shape = (string) $plan['shape'];
	if ( $shape === 'unlimited' ) {
		$what = 'Unlimited sessions';
	} elseif ( $shape === 'pack' ) {
		$what = sprintf( '%d sessions', (int) $plan['credits_total'] );
	} else {
		$what = sprintf( '%d per %s', (int) $plan['allowance'], (string) $plan['allowance_period'] );
	}
	$price = (int) $plan['price_cents']
		? sprintf( ' · $%s/%s', number_format( $plan['price_cents'] / 100, 2 ), (string) $plan['billing_period'] )
		: '';
	return $what . $price;
}

add_action( 'wp_ajax_site_pulse_booking_plans', 'sp_book_ajax_plans' );
function sp_book_ajax_plans(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_passes_gate() ) return;

	global $wpdb;
	$services = sp_book_service_names();
	$rows = $wpdb->get_results(
		"SELECT * FROM " . sp_book_table( 'plans' ) . " ORDER BY status = 'active' DESC, display_order ASC, name ASC",
		ARRAY_A
	) ?: [];

	$out = [];
	foreach ( $rows as $r ) {
		// How many people are on it — the number that makes "edit this plan" feel consequential.
		$members = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . sp_book_table( 'passes' ) . " WHERE plan_id = %d AND status = 'active'",
			(int) $r['id']
		) );
		$r['summary'] = sp_book_plan_summary( $r );
		$r['service'] = $services[ (int) $r['service_id'] ] ?? 'Any service';
		$r['members'] = $members;
		$r['price']   = (int) $r['price_cents'] ? number_format( $r['price_cents'] / 100, 2 ) : '';
		$out[] = $r;
	}

	$svc = [];
	foreach ( $services as $id => $name ) $svc[] = [ 'id' => $id, 'name' => $name ];

	wp_send_json_success( [
		'plans'    => $out,
		'services' => $svc,
		'shapes'   => sp_book_plan_shapes(),
		'periods'  => sp_book_pass_periods(),
		'overages' => sp_book_pass_overages(),
	] );
}

add_action( 'wp_ajax_site_pulse_booking_save_plan', 'sp_book_ajax_save_plan' );
function sp_book_ajax_save_plan(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_passes_gate() ) return;

	global $wpdb;
	$id   = (int) ( $_POST['id'] ?? 0 );
	$name = sp_book_post( 'name' );
	if ( $name === '' ) wp_send_json_error( [ 'message' => 'A plan name is required.' ] );

	$shape = sp_book_post( 'shape', 'allowance' );
	if ( ! array_key_exists( $shape, sp_book_plan_shapes() ) ) $shape = 'allowance';

	$period = sp_book_post( 'allowance_period', 'week' );
	if ( ! array_key_exists( $period, sp_book_pass_periods() ) ) $period = 'week';

	$overage = sp_book_post( 'overage', 'block' );
	if ( ! array_key_exists( $overage, sp_book_pass_overages() ) ) $overage = 'block';

	$now  = sp_book_now();
	$data = [
		'name'             => $name,
		'description'      => sp_book_post( 'description' ),
		'shape'            => $shape,
		'service_id'       => (int) ( $_POST['service_id'] ?? 0 ),
		'credits_total'    => $shape === 'pack' ? max( 1, (int) ( $_POST['credits_total'] ?? 10 ) ) : 0,
		'allowance'        => $shape === 'allowance' ? max( 1, (int) ( $_POST['allowance'] ?? 1 ) ) : 0,
		'allowance_period' => $period,
		'rollover'         => empty( $_POST['rollover'] ) ? 0 : 1,
		'rollover_max'     => max( 0, (int) ( $_POST['rollover_max'] ?? 0 ) ),
		'overage'          => $overage,
		'price_cents'      => sp_book_cents( sp_book_post( 'price' ) ),
		'billing_period'   => sp_book_post( 'billing_period', 'month' ) === 'week' ? 'week' : 'month',
		'term_days'        => max( 0, (int) ( $_POST['term_days'] ?? 0 ) ),
		'status'           => sp_book_post( 'status', 'active' ) === 'inactive' ? 'inactive' : 'active',
		'display_order'    => (int) ( $_POST['display_order'] ?? 0 ),
		'updated_at'       => $now,
	];

	if ( $id ) {
		$wpdb->update( sp_book_table( 'plans' ), $data, [ 'id' => $id ] );
	} else {
		$data['created_at'] = $now;
		$wpdb->insert( sp_book_table( 'plans' ), $data );
		$id = (int) $wpdb->insert_id;
	}

	site_pulse_log( 'booking_plan_saved', sprintf( 'Saved membership plan: %s', $name ) );

	// Existing members keep the terms they were issued — see the note on the plans table. Say so,
	// because "I fixed the plan, why is she still on two a week" is otherwise a support call.
	$members = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . sp_book_table( 'passes' ) . " WHERE plan_id = %d AND status = 'active'", $id
	) );
	$msg = 'Plan saved.';
	if ( $members ) {
		$msg .= sprintf(
			' %d existing member%s keep the terms they were issued with — edit a person to move them onto the new terms.',
			$members, $members === 1 ? '' : 's'
		);
	}

	wp_send_json_success( [ 'message' => $msg, 'id' => $id ] );
}

add_action( 'wp_ajax_site_pulse_booking_delete_plan', 'sp_book_ajax_delete_plan' );
function sp_book_ajax_delete_plan(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_passes_gate() ) return;

	global $wpdb;
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( [ 'message' => 'Bad request.' ] );

	// Issued passes carry their own copy of the terms, so deleting a plan does not break them —
	// but it does lose the grouping, so an in-use plan is deactivated instead.
	$members = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . sp_book_table( 'passes' ) . " WHERE plan_id = %d", $id
	) );

	if ( $members ) {
		$wpdb->update( sp_book_table( 'plans' ), [ 'status' => 'inactive', 'updated_at' => sp_book_now() ], [ 'id' => $id ] );
		wp_send_json_success( [ 'message' => sprintf(
			'Retired. %d membership%s were issued from it, so it is kept for the record and hidden from new ones.',
			$members, $members === 1 ? '' : 's'
		) ] );
	}

	$wpdb->delete( sp_book_table( 'plans' ), [ 'id' => $id ] );
	wp_send_json_success( [ 'message' => 'Plan deleted.' ] );
}
