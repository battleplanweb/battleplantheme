<?php
/* Battle Plan Web Design — Site Pulse: Booking on the Public Site

/*--------------------------------------------------------------
The customer side of SESSION booking: the website calendar, the Book buttons
on event pages, and the booking dialog (js/script-booking.js).

WHERE SESSIONS SHOW
  The framework's one calendar ([get-event-calendar], js/script-events.js)
  draws the global `eventLog`. Events build it (includes-events.php); this file
  adds to it through the bp_event_log / bp_event_log_block_html filters:
    · an event LINKED to a booking gets its button on the first day of each
      date block — the event is shown once, never twice;
    · every other session-mode booking adds one entry per session.
  On a site with Booking but no Events, this file supplies the calendar itself
  (the shortcode and the eventLog printer) — same markup, same script.

SEATS ARE LIVE
  Pages are edge-cached, so seat counts printed into the HTML go stale. The
  markup only carries session ids; script-booking.js asks bp_book_seats for the
  real numbers after load (and after every calendar month change) and flips a
  sold-out date's button to its "full" text.

PAYING VS RESERVING
  A session that takes payment (sp_book_seat_price()['checkout']) is added to
  the Store cart (includes-site-pulse-store-bookings.php). One that doesn't is
  reserved here directly: name, email, phone → a confirmed booking.

Every endpoint is logged-out-callable and nonce-free on purpose (cached pages
can't carry a valid nonce), like the Store cart. They only read public schedule
data or create a booking a visitor is entitled to make; reserving is throttled
per IP and carries a honeypot.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Public session data
# Calendar entries
# Booking-only sites: the calendar itself
# Event pages
# AJAX — seats / session / reserve
# Assets
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

/** Booking is on — the public pieces exist. */
function sp_book_public_on(): bool {
	return function_exists( 'site_pulse_module_on' ) && site_pulse_module_on( 'booking' );
}


/*--------------------------------------------------------------
# Public session data
--------------------------------------------------------------*/

/** The event a session belongs to (bookable events), or 0. */
function sp_book_session_event_id( array $service ): int {
	$eid = sp_book_service_opts( $service )['event_id'];
	return ( $eid && get_post_status( $eid ) === 'publish' ) ? $eid : 0;
}

/**
 * Everything the public dialog needs about one session, or a WP_Error when it can't be booked from
 * the website (gone, cancelled, inactive service, not a session service, or outside the booking window).
 */
function sp_book_public_session( int $session_id ) {
	$s = sp_book_get_session( $session_id );
	if ( ! $s || $s['status'] !== 'scheduled' ) return new WP_Error( 'gone', 'That date is no longer on the schedule.' );
	$svc = sp_book_get_service( (int) $s['service_id'] );
	if ( ! $svc || $svc['status'] !== 'active' || $svc['mode'] !== 'session' ) return new WP_Error( 'gone', 'That isn\'t available to book.' );

	$opts  = sp_book_service_opts( $svc );
	$price = sp_book_seat_price( $svc );
	$eid   = sp_book_session_event_id( $svc );

	// A SERIES is booked as a whole: whichever of its dates was clicked, the answer describes every
	// date, points at the lead (first upcoming) session, and the tightest date decides the seats.
	$series = null;
	if ( $opts['series'] ) {
		$series = sp_book_series_state( $svc );
		if ( ! $series['lead'] ) return new WP_Error( 'gone', 'This class is no longer on the schedule.' );
		$s = $series['lead'];
	}

	[ $earliest, $latest ] = sp_book_policy_bounds( $svc );
	$start = sp_book_utc_ts( (string) $s['starts_at'] );
	$open  = $start >= $earliest && $start <= $latest;
	$left  = max( 0, (int) $s['capacity'] - (int) $s['seats_taken'] );
	if ( $series ) {
		$open = $open && ! $series['started'];
		$left = $series['left'];
	}

	$when = sp_book_session_when( $s );
	if ( $series ) {
		$n     = count( $series['sessions'] );
		$first = $series['sessions'][0];
		$last  = $series['sessions'][ $n - 1 ];
		$times = sp_book_session_times( $first );
		$when  = $n === 1 ? $when : sprintf( '%d classes · %s – %s%s', $n,
			sp_book_to_local( (string) $first['starts_at'], 'D, M j' ),
			sp_book_to_local( (string) $last['starts_at'], 'D, M j, Y' ),
			$times !== '' ? ' · ' . $times : '' );
	}

	return [
		'id'          => (int) $s['id'],
		'title'       => $eid ? html_entity_decode( get_the_title( $eid ), ENT_QUOTES ) : (string) $svc['name'],
		'description' => $eid ? '' : (string) $svc['description'],
		'when'        => $when,
		'left'        => $open ? $left : 0,
		'open'        => $open,
		'max'         => $open ? min( $left, 10 ) : 0,
		'price_cents' => $price['cents'],
		'price'       => $price['cents'] ? '$' . number_format( $price['cents'] / 100, 2 ) : '',
		'deposit'     => (string) $svc['requires_payment'] === 'deposit',
		'checkout'    => $price['checkout'],
		'book_label'  => $opts['book_label'],
		'full_label'  => $opts['full_label'],
		'url'         => $eid ? (string) get_permalink( $eid ) : '',
		// Series only: how many dates the price covers, each date, and why it's closed once it has begun.
		'series'      => $series ? count( $series['sessions'] ) : 0,
		'dates'       => $series ? array_map( 'sp_book_session_when', $series['sessions'] ) : [],
		'closed'      => $series && $series['started'] ? 'This class has already started, so enrollment is closed.' : '',
	];
}

/** "6:30pm–9:30pm" for a session, or '' when it runs all day. */
function sp_book_session_times( array $s ): string {
	$st = sp_book_to_local( (string) $s['starts_at'], 'H:i' );
	$et = sp_book_to_local( (string) $s['ends_at'], 'H:i' );
	if ( $st === '00:00' && $et === '23:59' ) return '';
	return sp_book_to_local( (string) $s['starts_at'], 'g:ia' ) . '–' . sp_book_to_local( (string) $s['ends_at'], 'g:ia' );
}

/** One session's date + time, the way customers read it. */
function sp_book_session_when( array $s ): string {
	$sd = sp_book_to_local( (string) $s['starts_at'], 'Y-m-d' );
	$ed = sp_book_to_local( (string) $s['ends_at'], 'Y-m-d' );
	$st = sp_book_to_local( (string) $s['starts_at'], 'H:i' );
	$et = sp_book_to_local( (string) $s['ends_at'], 'H:i' );
	$all_day = $st === '00:00' && $et === '23:59';
	if ( $sd === $ed ) {
		$when = sp_book_to_local( (string) $s['starts_at'], 'l, F j, Y' ) . ( $all_day ? '' : ' · ' . sp_book_to_local( (string) $s['starts_at'], 'g:ia' ) . '–' . sp_book_to_local( (string) $s['ends_at'], 'g:ia' ) );
	} else {
		$when = sp_book_to_local( (string) $s['starts_at'], 'D, M j' ) . ( $all_day ? '' : ', ' . sp_book_to_local( (string) $s['starts_at'], 'g:ia' ) )
			. ' – ' . sp_book_to_local( (string) $s['ends_at'], 'D, M j, Y' ) . ( $all_day ? '' : ', ' . sp_book_to_local( (string) $s['ends_at'], 'g:ia' ) );
	}
	return $when;
}

/** The Book button for one session. Starts as bookable; script-booking.js corrects it from live seats. */
function sp_book_button_html( array $session, array $service, string $extra_class = '' ): string {
	$opts = sp_book_service_opts( $service );
	$left = max( 0, (int) $session['capacity'] - (int) $session['seats_taken'] );
	return sprintf(
		'<button type="button" class="event-book%s%s" data-book-session="%d" data-book-full="%s"%s>%s</button>',
		$extra_class ? ' ' . esc_attr( $extra_class ) : '',
		$left ? '' : ' is-full',
		(int) $session['id'],
		esc_attr( $opts['full_label'] ),
		$left ? '' : ' disabled',
		esc_html( $left ? $opts['book_label'] : $opts['full_label'] )
	);
}


/*--------------------------------------------------------------
# Calendar entries
--------------------------------------------------------------*/

/** Only the calendar page draws the calendar, so only it pays for the session queries. */
function sp_book_calendar_page(): bool {
	return sp_book_public_on() && ( is_page( 'calendar' ) || ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'get-event-calendar' ) ) );
}

/**
 * A bookable event's day: its usual calendar entry with the Book button added — on the FIRST day of
 * the block only, so a Fri–Sun retreat shows one button, not three.
 */
add_filter( 'bp_event_log_block_html', 'sp_book_event_block_html', 10, 3 );
function sp_book_event_block_html( string $html, int $post_id, array $block ): string {
	if ( ! sp_book_calendar_page() || ! function_exists( 'sp_book_event_service' ) ) return $html;
	$svc = sp_book_event_service( $post_id );
	if ( ! $svc || $svc['status'] !== 'active' ) return $html;

	$sd = strtotime( (string) ( $block['s_date'] ?? '' ) );
	if ( ! $sd ) return $html;
	$st    = ! empty( $block['s_time'] ) ? date( 'H:i:s', strtotime( (string) $block['s_time'] ) ) : '00:00:00';
	$start = sp_book_to_utc( date( 'Y-m-d', $sd ) . ' ' . $st );

	global $wpdb;
	$s = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . sp_book_table( 'sessions' ) . " WHERE service_id = %d AND starts_at = %s AND status = 'scheduled' AND starts_at > %s",
		(int) $svc['id'], $start, sp_book_now()
	), ARRAY_A );
	if ( ! $s ) return $html;

	// A series: every date says which class it is and books the WHOLE series (the lead session);
	// once the first class has begun nothing more can be booked, so the dates just show their number.
	if ( sp_book_service_opts( $svc )['series'] ) {
		$st  = sp_book_series_state( $svc );
		$pos = array_search( (int) $s['id'], array_map( 'intval', array_column( $st['all'], 'id' ) ), true );
		$btn = '<span class="event-series-label">Class ' . ( (int) $pos + 1 ) . ' of ' . count( $st['all'] ) . '</span>';
		if ( ! $st['started'] && $st['lead'] ) $btn .= sp_book_button_html( $st['lead'], $svc );
		$at  = strrpos( $html, '</div>' );
		return $at === false ? $html . $btn : substr( $html, 0, $at ) . $btn . substr( $html, $at );
	}

	$btn = sp_book_button_html( $s, $svc );
	$pos = strrpos( $html, '</div>' );
	return $pos === false ? $html . $btn : substr( $html, 0, $pos ) . $btn . substr( $html, $pos );
}

/**
 * Every other session-mode service: one entry per upcoming session (this month → a year out).
 * The whole entry opens the dialog (the button alone is too small on a phone's calendar grid).
 */
add_filter( 'bp_event_log', 'sp_book_calendar_sessions' );
function sp_book_calendar_sessions( array $log ): array {
	if ( ! sp_book_calendar_page() ) return $log;
	global $wpdb;

	$services = [];
	foreach ( $wpdb->get_results( "SELECT * FROM " . sp_book_table( 'services' ) . " WHERE mode = 'session' AND status = 'active'", ARRAY_A ) ?: [] as $svc ) {
		// Linked to an event (even a draft one): it shows through that event, never on its own.
		if ( function_exists( 'sp_book_service_event' ) ? sp_book_service_event( $svc ) : sp_book_service_opts( $svc )['event_id'] ) continue;
		$services[ (int) $svc['id'] ] = $svc;
	}
	if ( ! $services ) return $log;

	$from = sp_book_to_utc( ( new DateTime( 'first day of this month', sp_book_tz() ) )->format( 'Y-m-d' ) . ' 00:00:00' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_book_table( 'sessions' ) . "
		 WHERE status = 'scheduled' AND starts_at >= %s AND starts_at <= %s
		   AND service_id IN (" . implode( ',', array_map( 'intval', array_keys( $services ) ) ) . ")
		 ORDER BY starts_at ASC LIMIT 1500",
		$from, gmdate( 'Y-m-d H:i:s', time() + 400 * DAY_IN_SECONDS )
	), ARRAY_A ) ?: [];

	// ONE calendar entry per day, one row per session. The calendar draws each entry as a full-cell
	// layer stacked on the others, so separate entries for a 9am and a 6pm class would sit on top of
	// each other with their buttons overlapping; rows inside one entry stack cleanly.
	$now  = sp_book_now();
	$days = [];
	foreach ( $rows as $s ) {
		$svc  = $services[ (int) $s['service_id'] ];
		$past = $s['starts_at'] <= $now;
		$day  = sp_book_to_local( (string) $s['starts_at'], 'j, n, Y' );
		$days[ $day ][] = '<div class="event-booking-slot' . ( $past ? ' event-booking-past' : '' ) . '"' . ( $past ? '' : ' data-book-session="' . (int) $s['id'] . '"' ) . '>'
			. '<span class="event-booking-label"><span class="event-booking-time">' . esc_html( sp_book_to_local( (string) $s['starts_at'], 'g:ia' ) ) . '</span> '
			. '<span class="event-booking-title">' . esc_html( (string) $svc['name'] ) . '</span></span>'
			. ( $past ? '' : sp_book_button_html( $s, $svc ) )
			. '</div>';
	}
	foreach ( $days as $day => $slots ) {
		$log[] = [ 'date' => $day, 'event' => '<div class="event event-booking' . ( count( $slots ) > 1 ? ' event-booking-multi' : '' ) . '">' . implode( '', $slots ) . '</div>' ];
	}
	return $log;
}


/*--------------------------------------------------------------
# Booking-only sites: the calendar itself
--------------------------------------------------------------*/

// Events not loaded → this supplies the same calendar shortcode and the eventLog the script reads.
add_action( 'init', 'sp_book_calendar_fallback', 20 );
function sp_book_calendar_fallback(): void {
	if ( ! sp_book_public_on() || ( function_exists( 'bp_events_on' ) && bp_events_on() ) ) return;
	add_shortcode( 'get-event-calendar', 'sp_book_calendar_shortcode' );
	add_action( 'wp_head', 'sp_book_calendar_log', 0 );
}

function sp_book_calendar_shortcode( $atts, $content = null ) {
	return bp_calendar_markup( 'Upcoming Classes' );
}

function sp_book_calendar_log(): void {
	if ( ! sp_book_calendar_page() ) return;
	$log = apply_filters( 'bp_event_log', [] );
	?><script nonce="<?php echo defined( '_BP_NONCE' ) ? _BP_NONCE : ''; ?>">var eventLog = <?php echo wp_json_encode( $log ); ?>; var abbr_days = false;</script><?php
}


/*--------------------------------------------------------------
# Event pages
--------------------------------------------------------------*/

/**
 * A bookable event's page lists its upcoming dates, each with its Book button, under the description.
 * Event pages are built by template-parts/content-events.php from get_the_content() — the_content
 * never runs there — so this hangs off that template's own bp_event_page_after_content filter.
 */
add_filter( 'bp_event_page_after_content', 'sp_book_event_page_dates', 10, 2 );
function sp_book_event_page_dates( $content, $post_id = 0 ) {
	if ( ! sp_book_public_on() || ! function_exists( 'sp_book_event_service' ) ) return $content;
	$post_id = (int) ( $post_id ?: get_the_ID() );
	$svc     = sp_book_event_service( $post_id );
	if ( ! $svc || $svc['status'] !== 'active' ) return $content;

	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_book_table( 'sessions' ) . " WHERE service_id = %d AND status = 'scheduled' AND starts_at > %s ORDER BY starts_at LIMIT 60",
		(int) $svc['id'], sp_book_now()
	), ARRAY_A ) ?: [];
	if ( ! $rows ) return $content;

	$price = sp_book_seat_price( $svc );
	if ( sp_book_service_opts( $svc )['series'] ) return $content . sp_book_series_page_html( $svc, $price );
	$out   = '<div class="event-booking-dates"><h2 class="event-booking-head">' . esc_html( count( $rows ) === 1 ? 'Reserve Your Spot' : 'Choose A Date' ) . '</h2>';
	if ( $price['cents'] ) $out .= '<p class="event-booking-price">$' . esc_html( number_format( $price['cents'] / 100, 2 ) ) . ' per person</p>';
	$out .= '<ul class="event-booking-list">';
	foreach ( $rows as $s ) {
		$info = sp_book_public_session( (int) $s['id'] );
		if ( is_wp_error( $info ) ) continue;
		$out .= '<li class="event-booking-row"><span class="event-booking-when">' . esc_html( $info['when'] ) . '</span>'
			. '<span class="event-booking-left" data-book-left="' . (int) $s['id'] . '"></span>'
			. sp_book_button_html( $s, $svc ) . '</li>';
	}
	$out .= '</ul></div>';
	return $content . $out;
}


/** A series event's page: every date listed, ONE price for all of them, ONE Enroll button. */
function sp_book_series_page_html( array $svc, array $price ): string {
	$st = sp_book_series_state( $svc );
	if ( ! $st['lead'] ) return '';
	$n   = count( $st['sessions'] );
	$out = '<div class="event-booking-dates event-booking-series"><h2 class="event-booking-head">' . esc_html( $n === 1 ? 'Reserve Your Spot' : sprintf( 'Enroll In All %d Classes', $n ) ) . '</h2>';
	if ( $price['cents'] ) {
		$out .= '<p class="event-booking-price">$' . esc_html( number_format( $price['cents'] / 100, 2 ) ) . ' per person' . ( $n > 1 ? esc_html( sprintf( ' for all %d classes', $n ) ) : '' ) . '</p>';
	}
	$out .= '<ul class="event-booking-list">';
	foreach ( $st['sessions'] as $i => $s ) {
		$out .= '<li class="event-booking-row"><span class="event-booking-when">' . esc_html( sp_book_session_when( $s ) ) . '</span>'
			. ( $n > 1 ? '<span class="event-series-label">Class ' . ( $i + 1 ) . ' of ' . $n . '</span>' : '' ) . '</li>';
	}
	$out .= '</ul>';
	if ( $st['started'] ) {
		$out .= '<p class="event-booking-closed">This class has already started, so enrollment is closed.</p>';
	} else {
		$out .= '<div class="event-booking-enroll"><span class="event-booking-left" data-book-left="' . (int) $st['lead']['id'] . '"></span>' . sp_book_button_html( $st['lead'], $svc ) . '</div>';
	}
	return $out . '</div>';
}


/*--------------------------------------------------------------
# AJAX — seats / session / reserve
--------------------------------------------------------------*/

foreach ( [ 'bp_book_seats', 'bp_book_session', 'bp_book_reserve' ] as $sp_book_pub_action ) {
	add_action( 'wp_ajax_' . $sp_book_pub_action, 'sp_book_ajax_' . substr( $sp_book_pub_action, 8 ) . '_public' );
	add_action( 'wp_ajax_nopriv_' . $sp_book_pub_action, 'sp_book_ajax_' . substr( $sp_book_pub_action, 8 ) . '_public' );
}
unset( $sp_book_pub_action );

/** Live seats for every session id on the page: { id: { left, open } }. Never cached. */
function sp_book_ajax_seats_public(): void {
	if ( ! sp_book_public_on() ) wp_send_json_error();
	nocache_headers();
	$ids = array_slice( array_values( array_unique( array_filter( array_map( 'intval', explode( ',', (string) ( $_REQUEST['ids'] ?? '' ) ) ) ) ) ), 0, 400 );
	if ( ! $ids ) wp_send_json_success( [] );

	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT s.id, s.service_id, s.capacity, s.seats_taken, s.status, s.starts_at, v.status AS svc_status, v.lead_time_min, v.max_advance_days, v.meta
		 FROM " . sp_book_table( 'sessions' ) . " s JOIN " . sp_book_table( 'services' ) . " v ON v.id = s.service_id
		 WHERE s.id IN (" . implode( ',', $ids ) . ")",
		ARRAY_A
	) ?: [];
	$out    = [];
	$series = [];   // service id => series state, computed once per series on the page
	foreach ( $rows as $r ) {
		// A series date answers for the whole series: closed once it has started, tightest date's seats.
		if ( sp_book_service_opts( [ 'meta' => $r['meta'] ] )['series'] ) {
			$sid = (int) $r['service_id'];
			if ( ! isset( $series[ $sid ] ) ) $series[ $sid ] = sp_book_series_state( [ 'id' => $sid ] );
			$st   = $series[ $sid ];
			$open = $r['svc_status'] === 'active' && $r['status'] === 'scheduled' && ! $st['started'] && $st['lead'];
			$out[ (int) $r['id'] ] = [ 'left' => $open ? $st['left'] : 0, 'open' => (bool) $open ];
			continue;
		}
		[ $earliest, $latest ] = sp_book_policy_bounds( [ 'lead_time_min' => $r['lead_time_min'], 'max_advance_days' => $r['max_advance_days'] ] );
		$start = sp_book_utc_ts( (string) $r['starts_at'] );
		$open  = $r['status'] === 'scheduled' && $r['svc_status'] === 'active' && $start >= $earliest && $start <= $latest;
		$out[ (int) $r['id'] ] = [ 'left' => $open ? max( 0, (int) $r['capacity'] - (int) $r['seats_taken'] ) : 0, 'open' => $open ];
	}
	wp_send_json_success( $out );
}

/** One session, for the dialog. */
function sp_book_ajax_session_public(): void {
	if ( ! sp_book_public_on() ) wp_send_json_error( [ 'message' => 'Booking isn\'t available right now.' ] );
	nocache_headers();
	$info = sp_book_public_session( (int) ( $_REQUEST['id'] ?? 0 ) );
	if ( is_wp_error( $info ) ) wp_send_json_error( [ 'message' => $info->get_error_message() ] );
	$info['store_ready'] = function_exists( 'sp_store_bookings_ready' ) && sp_store_bookings_ready();
	wp_send_json_success( $info );
}

/**
 * Reserve seats in a session that doesn't take payment. Paid sessions are refused here — they go
 * through the Store cart, never around it.
 */
function sp_book_ajax_reserve_public(): void {
	if ( ! sp_book_public_on() ) wp_send_json_error( [ 'message' => 'Booking isn\'t available right now.' ] );
	nocache_headers();

	// Bots fill every field; people never see this one.
	if ( trim( (string) ( $_POST['website'] ?? '' ) ) !== '' ) wp_send_json_error( [ 'message' => 'Something went wrong — please try again.' ] );

	$ip  = preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key = 'sp_book_rsv_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 8 ) wp_send_json_error( [ 'message' => 'Too many bookings from this connection — please try again in an hour, or call us.' ] );

	$id   = (int) ( $_POST['id'] ?? 0 );
	$info = sp_book_public_session( $id );
	if ( is_wp_error( $info ) ) wp_send_json_error( [ 'message' => $info->get_error_message() ] );
	if ( ! $info['open'] ) wp_send_json_error( [ 'message' => 'That date can\'t be booked online any more.' ] );
	if ( $info['checkout'] ) wp_send_json_error( [ 'message' => 'This one is paid at checkout — please add it to your cart.' ] );

	$name  = sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) );
	$email = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );
	$phone = sanitize_text_field( wp_unslash( (string) ( $_POST['phone'] ?? '' ) ) );
	$seats = max( 1, min( 10, (int) ( $_POST['seats'] ?? 1 ) ) );
	if ( $name === '' || ! is_email( $email ) ) wp_send_json_error( [ 'message' => 'Please enter your name and a valid email address.' ] );

	$s = sp_book_get_session( $id );
	$booking = sp_book_claim( [
		'service_id'     => (int) $s['service_id'],
		'session_id'     => $id,
		'party_size'     => $seats,
		'status'         => 'confirmed',
		'source'         => 'web',
		'customer_name'  => $name,
		'customer_email' => $email,
		'customer_phone' => $phone,
		'price_cents'    => 0,
		'deposit_cents'  => 0,
	] );
	if ( is_wp_error( $booking ) ) {
		$msg = $booking->get_error_code() === 'series_started' ? $booking->get_error_message() : ( $booking->get_error_code() === 'session_full'
			? ( $info['left'] > 0 ? sprintf( 'Sorry — only %d spot%s left now.', $info['left'], $info['left'] === 1 ? '' : 's' ) : 'Sorry — that date just filled up.' )
			: $booking->get_error_message() );
		wp_send_json_error( [ 'message' => $msg ] );
	}

	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	if ( function_exists( 'sp_book_notify_created' ) ) sp_book_notify_created( $booking );
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'booking_web_reserved', sprintf( 'Website booking %s: %s × %d (%s)', $booking['code'], $info['title'], $seats, $name ) );

	wp_send_json_success( [
		'code'    => (string) $booking['code'],
		'message' => sprintf( 'You\'re booked! %s — %s. A confirmation is on its way to %s.', $info['title'], $info['when'], $email ),
	] );
}


/*--------------------------------------------------------------
# Assets
--------------------------------------------------------------*/

// The dialog script, wherever a Book button can appear (the calendar and event pages — and anywhere a
// site drops one in). Small and deferred; it does nothing on a page with no [data-book-session].
add_action( 'wp_enqueue_scripts', 'sp_book_public_assets', 20 );
function sp_book_public_assets(): void {
	if ( ! sp_book_public_on() || is_admin() ) return;
	if ( ! function_exists( 'bp_enqueue_script' ) ) return;
	bp_enqueue_script( 'battleplan-script-booking', 'script-booking' );
	wp_localize_script( 'battleplan-script-booking', 'BPBook', [
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'store' => function_exists( 'sp_store_bookings_ready' ) && sp_store_bookings_ready(),
	] );
}
