<?php
/* Battle Plan Web Design — Site Pulse: Booking Types (templates) + Offerings

/*--------------------------------------------------------------
TWO LAYERS, like Store's product setup:

  Booking Type   (Settings → Booking → Booking Types) a TEMPLATE: Class, Class Series,
                 Appointment, House Call… Its kind fixes the shape (a class with seats on
                 dates, a time-slot appointment, a rental), and its rules are the defaults:
                 length, seats, price, notice, payment, button wording, who delivers it.
  Offering       (Booking → Offerings) one row of the services table — the actual thing
                 customers book. services.type_id points at its type.

LIVE-LINKED (chosen 2026-11-21): an offering follows its type for every rule it hasn't
changed. services.meta.overrides lists the rule keys the offering set for itself; saving a
type pushes its rules into every offering's OTHER keys (sp_book_type_apply). The engine
keeps reading plain service columns — inheritance is materialised on write, never resolved
at read time.

An offering saved with a value EQUAL to its type's goes back to following the type for that
key (sp_book_ajax_save_service computes overrides by comparison). type_id 0 = no type: a
standalone offering that owns every value.

Rules are stored NORMALISED (sp_book_rules_normalize) in the same units as the service
columns (minutes, cents), so type and offering compare key by key.

Seeds: Class / Class Series / Appointment / House Call, once per site (option
sp_book_types_seeded). Existing services are put on the matching starter with overrides =
every key that differs, so no value changes. Customers can edit or delete any type; deleting
one leaves its offerings standalone with their current values.
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Vocabulary
--------------------------------------------------------------*/

/** The shapes a type can have. The kind fixes the engine mode and the series flag. */
function sp_book_type_kinds(): array {
	// slot / address: values the kind FIXES (a service call is always an arrival window at the
	// customer's address; an appointment is always an exact start time). See sp_book_kind_force().
	return [
		'appointment' => [ 'label' => 'Appointment — a set start time (a haircut, a consult, a lesson)', 'short' => 'Appointment',  'mode' => 'appointment', 'series' => false, 'slot' => 'exact' ],
		'call'        => [ 'label' => 'Service call — an arrival window at the customer’s address (HVAC, plumbing, cleaning)', 'short' => 'Service call', 'mode' => 'appointment', 'series' => false, 'slot' => 'window', 'address' => 1 ],
		'class'       => [ 'label' => 'Class — seats on a date; people pick one date', 'short' => 'Class',        'mode' => 'session',     'series' => false ],
		'series'      => [ 'label' => 'Class series — one price buys a seat in every date', 'short' => 'Class series', 'mode' => 'session',     'series' => true ],
		'rental'      => [ 'label' => 'Rental — a range of dates (a room, a cabin, equipment)', 'short' => 'Rental',       'mode' => 'stay',        'series' => false ],
	];
}

/** The rules a kind fixes, applied over any set of rules. */
function sp_book_kind_force( array $rules, string $kind ): array {
	$k = sp_book_type_kinds()[ $kind ] ?? null;
	if ( ! $k ) return $rules;
	if ( isset( $k['slot'] ) )    $rules['slot_mode'] = $k['slot'];
	if ( isset( $k['address'] ) ) $rules['requires_address'] = (int) $k['address'];
	if ( $kind === 'call' )       $rules['travel_buffer'] = 1;
	return $rules;
}

/** The kind an existing service row amounts to. */
function sp_book_service_kind( array $svc ): string {
	$mode = (string) ( $svc['mode'] ?? 'appointment' );
	if ( $mode === 'session' ) return sp_book_service_opts( $svc )['series'] ? 'series' : 'class';
	if ( $mode === 'stay' ) return 'rental';
	return (string) ( $svc['slot_mode'] ?? 'exact' ) === 'window' ? 'call' : 'appointment';
}

/** Every inheritable rule with its default (= the services column default). */
function sp_book_rule_defaults(): array {
	return [
		'duration_min'          => 60,
		'slot_interval_min'     => 30,
		'buffer_min'            => 0,
		'lead_time_min'         => 720,
		'max_advance_days'      => 60,
		'cancel_window_min'     => 720,
		'capacity'              => 1,
		'price_cents'           => 0,
		'deposit_cents'         => 0,
		'requires_payment'      => 'none',
		'allow_pass'            => 1,
		'requires_address'      => 0,
		'travel_buffer'         => 0,
		'slot_mode'             => 'exact',
		'windows'               => '',
		'window_max_jobs'       => 0,
		'dispatch_mode'         => 'order',
		'out_of_area_min'       => 45,
		'out_of_area_policy'    => 'allow',
		'out_of_area_fee_cents' => 0,
		'service_area_miles'    => '',
		'min_units'             => 1,
		'max_units'             => 1,
		'book_label'            => 'Book Now',
		'full_label'            => 'Class Full',
		'taxable'               => 0,
		'resource_ids'          => [],
	];
}

/** One canonical form per value, so a type and an offering compare key by key. */
function sp_book_rules_normalize( array $r ): array {
	$out = [];
	foreach ( sp_book_rule_defaults() as $k => $def ) {
		$v = array_key_exists( $k, $r ) ? $r[ $k ] : $def;
		if ( $k === 'resource_ids' ) {
			$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $v ) ) ) );
			sort( $ids );
			$out[ $k ] = $ids;
		} elseif ( $k === 'windows' ) {
			$list = is_array( $v ) ? $v : json_decode( (string) $v, true );
			$clean = [];
			foreach ( is_array( $list ) ? $list : [] as $w ) {
				$s = sp_book_time_value( (string) ( $w['start'] ?? '' ) );
				$e = sp_book_time_value( (string) ( $w['end'] ?? '' ) );
				if ( $s === '' || $e === '' ) continue;
				$clean[] = [ 'label' => (string) ( $w['label'] ?? '' ), 'start' => $s, 'end' => $e ];
			}
			$out[ $k ] = $clean ? wp_json_encode( $clean ) : '';
		} elseif ( $k === 'service_area_miles' ) {
			$out[ $k ] = ( $v === '' || $v === null ) ? '' : number_format( (float) $v, 1, '.', '' );
		} elseif ( is_int( $def ) ) {
			$out[ $k ] = (int) $v;
		} else {
			$out[ $k ] = (string) $v;
		}
	}
	// An exact-time offering has no windows; keeping stale ones would read as a difference.
	if ( $out['slot_mode'] !== 'window' ) $out['windows'] = '';
	return $out;
}

/** A service row → its rules. */
function sp_book_service_rules( array $svc ): array {
	global $wpdb;
	$o = sp_book_service_opts( $svc );
	$r = array_intersect_key( $svc, sp_book_rule_defaults() );
	$r['buffer_min']   = max( (int) ( $svc['buffer_before_min'] ?? 0 ), (int) ( $svc['buffer_after_min'] ?? 0 ) );
	$r['book_label']   = $o['book_label'];
	$r['full_label']   = $o['full_label'];
	$r['taxable']      = $o['taxable'] ? 1 : 0;
	$r['windows']      = (string) ( $svc['windows'] ?? '' );
	$r['service_area_miles'] = $svc['service_area_miles'] ?? '';
	$r['resource_ids'] = ! empty( $svc['id'] ) ? $wpdb->get_col( $wpdb->prepare(
		"SELECT resource_id FROM " . sp_book_table( 'service_resources' ) . " WHERE service_id = %d", (int) $svc['id']
	) ) : [];
	return sp_book_rules_normalize( $r );
}

/** The rule keys an offering has set for itself. */
function sp_book_service_overrides( array $svc ): array {
	$meta = json_decode( (string) ( $svc['meta'] ?? '' ), true );
	$over = is_array( $meta ) && isset( $meta['overrides'] ) ? (array) $meta['overrides'] : [];
	return array_values( array_intersect( $over, array_keys( sp_book_rule_defaults() ) ) );
}

/**
 * The rule keys present in this request, parsed and validated (sends an error and exits on a bad
 * arrival window). Keys the form didn't send are absent, so a save leaves them alone.
 */
function sp_book_rules_from_post(): array {
	$r   = [];
	$has = static fn( $k ) => isset( $_POST[ $k ] );
	$int = static fn( $k, $min ) => max( $min, (int) $_POST[ $k ] );

	foreach ( [ 'duration_min' => 5, 'slot_interval_min' => 5, 'max_advance_days' => 1, 'capacity' => 1,
		'window_max_jobs' => 0, 'out_of_area_min' => 0, 'min_units' => 1, 'max_units' => 1 ] as $k => $min ) {
		if ( $has( $k ) ) $r[ $k ] = $int( $k, $min );
	}
	// Older builds sent the two buffer halves; take the larger so nothing silently shrinks.
	if ( $has( 'buffer_min' ) ) $r['buffer_min'] = $int( 'buffer_min', 0 );
	elseif ( $has( 'buffer_before_min' ) || $has( 'buffer_after_min' ) ) $r['buffer_min'] = max( (int) ( $_POST['buffer_before_min'] ?? 0 ), (int) ( $_POST['buffer_after_min'] ?? 0 ) );
	if ( $has( 'lead_time_hours' ) )     $r['lead_time_min']     = max( 0, (int) round( (float) $_POST['lead_time_hours'] * 60 ) );
	if ( $has( 'cancel_window_hours' ) ) $r['cancel_window_min'] = max( 0, (int) round( (float) $_POST['cancel_window_hours'] * 60 ) );
	if ( $has( 'price' ) )           $r['price_cents']           = sp_book_cents( sp_book_post( 'price' ) );
	if ( $has( 'deposit' ) )         $r['deposit_cents']         = sp_book_cents( sp_book_post( 'deposit' ) );
	if ( $has( 'out_of_area_fee' ) ) $r['out_of_area_fee_cents'] = sp_book_cents( sp_book_post( 'out_of_area_fee' ) );
	if ( $has( 'requires_payment' ) ) {
		$v = sp_book_post( 'requires_payment', 'none' );
		$r['requires_payment'] = in_array( $v, [ 'none', 'deposit', 'full' ], true ) ? $v : 'none';
	}
	if ( $has( 'dispatch_mode' ) ) $r['dispatch_mode'] = sp_book_post( 'dispatch_mode' ) === 'nearest' ? 'nearest' : 'order';
	if ( $has( 'out_of_area_policy' ) ) {
		$v = sp_book_post( 'out_of_area_policy', 'allow' );
		$r['out_of_area_policy'] = in_array( $v, [ 'allow', 'block', 'surcharge' ], true ) ? $v : 'allow';
	}
	foreach ( [ 'allow_pass', 'requires_address', 'travel_buffer', 'taxable' ] as $k ) {
		if ( $has( $k ) ) $r[ $k ] = empty( $_POST[ $k ] ) ? 0 : 1;
	}
	if ( $has( 'service_area_miles' ) ) $r['service_area_miles'] = sp_book_post( 'service_area_miles' );
	if ( $has( 'book_label' ) ) $r['book_label'] = mb_substr( sp_book_post( 'book_label' ), 0, 40 );
	if ( $has( 'full_label' ) ) $r['full_label'] = mb_substr( sp_book_post( 'full_label' ), 0, 40 );

	// Exact start time, or an arrival window. Windows are validated rather than trusted: a malformed
	// entry would otherwise sit in the JSON and silently produce no availability.
	if ( $has( 'slot_mode' ) ) {
		$r['slot_mode'] = sp_book_post( 'slot_mode', 'exact' ) === 'window' ? 'window' : 'exact';
		$windows = [];
		if ( $r['slot_mode'] === 'window' ) {
			$posted = json_decode( wp_unslash( (string) ( $_POST['windows'] ?? '[]' ) ), true );
			foreach ( is_array( $posted ) ? $posted : [] as $w ) {
				$start = sp_book_time_value( (string) ( $w['start'] ?? '' ) );
				$end   = sp_book_time_value( (string) ( $w['end'] ?? '' ) );
				if ( $start === '' || $end === '' ) continue;
				if ( $end <= $start ) wp_send_json_error( [ 'message' => 'An arrival window ends before it starts. Overnight windows are not supported.' ] );
				$windows[] = [ 'label' => sanitize_text_field( (string) ( $w['label'] ?? '' ) ), 'start' => $start, 'end' => $end ];
			}
			if ( ! $windows ) wp_send_json_error( [ 'message' => 'Add at least one arrival window, or set this back to an exact start time.' ] );
		}
		$r['windows'] = $windows;
	}

	// An EMPTY selection is meaningful ("anyone"), and an empty array doesn't post — hence the marker.
	if ( $has( 'resource_ids' ) || $has( 'resource_ids_sent' ) ) $r['resource_ids'] = (array) ( $_POST['resource_ids'] ?? [] );
	return $r;
}

/** Write rules onto a service: its columns, its meta options, its resource links. */
function sp_book_write_rules( int $id, array $rules, string $mode, bool $series ): void {
	global $wpdb;
	$r    = sp_book_rules_normalize( $rules );
	$skip = [ 'buffer_min', 'book_label', 'full_label', 'taxable', 'resource_ids', 'windows', 'service_area_miles' ];
	$data = array_diff_key( $r, array_flip( $skip ) );
	$data['buffer_before_min']  = $r['buffer_min'];
	$data['buffer_after_min']   = $r['buffer_min'];
	$data['windows']            = $r['windows'] !== '' ? $r['windows'] : null;
	$data['service_area_miles'] = ( $r['requires_address'] && $r['service_area_miles'] !== '' ) ? (float) $r['service_area_miles'] : null;
	$data['mode']               = $mode;
	$data['updated_at']         = sp_book_now();
	$wpdb->update( sp_book_table( 'services' ), $data, [ 'id' => $id ] );

	sp_book_service_set_opts( $id, [
		'book_label' => $r['book_label'],
		'full_label' => $r['full_label'],
		'taxable'    => (bool) $r['taxable'],
		'series'     => $mode === 'session' && $series,
	] );

	$wpdb->delete( sp_book_table( 'service_resources' ), [ 'service_id' => $id ] );
	foreach ( $r['resource_ids'] as $i => $rid ) {
		$wpdb->insert( sp_book_table( 'service_resources' ), [ 'service_id' => $id, 'resource_id' => $rid, 'display_order' => $i ] );
	}
}


/*--------------------------------------------------------------
# Types: read · apply · seed
--------------------------------------------------------------*/

function sp_book_get_types(): array {
	global $wpdb;
	return $wpdb->get_results( "SELECT * FROM " . sp_book_table( 'types' ) . " ORDER BY display_order ASC, name ASC", ARRAY_A ) ?: [];
}

function sp_book_get_type( int $id ): ?array {
	global $wpdb;
	if ( ! $id ) return null;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'types' ) . " WHERE id = %d", $id ), ARRAY_A );
	return $row ?: null;
}

function sp_book_type_rules( array $type ): array {
	$r = json_decode( (string) ( $type['rules'] ?? '' ), true );
	return sp_book_rules_normalize( is_array( $r ) ? $r : [] );
}

/** A type for the screens: its row, decoded rules, kind label, and how many offerings follow it. */
function sp_book_type_shape( array $type, array $counts = [] ): array {
	$kinds = sp_book_type_kinds();
	return [
		'id'          => (int) $type['id'],
		'name'        => (string) $type['name'],
		'description' => (string) $type['description'],
		'kind'        => (string) $type['kind'],
		'kind_label'  => $kinds[ $type['kind'] ]['short'] ?? $type['kind'],
		'mode'        => $kinds[ $type['kind'] ]['mode'] ?? 'appointment',
		'series'      => ! empty( $kinds[ $type['kind'] ]['series'] ),
		'rules'       => sp_book_type_rules( $type ),
		'offerings'   => (int) ( $counts[ (int) $type['id'] ] ?? 0 ),
	];
}

/** Push a type's rules into every offering that follows it, key by key (overrides stay). */
function sp_book_type_apply( int $type_id ): int {
	global $wpdb;
	$type = sp_book_get_type( $type_id );
	if ( ! $type ) return 0;
	$kind   = sp_book_type_kinds()[ $type['kind'] ] ?? null;
	if ( ! $kind ) return 0;
	$trules = sp_book_kind_force( sp_book_type_rules( $type ), $type['kind'] );
	$n = 0;
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . sp_book_table( 'services' ) . " WHERE type_id = %d", $type_id ), ARRAY_A ) ?: [] as $svc ) {
		$rules = sp_book_service_rules( $svc );
		$over  = sp_book_service_overrides( $svc );
		foreach ( $trules as $k => $v ) if ( ! in_array( $k, $over, true ) ) $rules[ $k ] = $v;
		sp_book_write_rules( (int) $svc['id'], sp_book_kind_force( $rules, $type['kind'] ), $kind['mode'], $kind['series'] );
		$n++;
	}
	if ( $n ) sp_book_cache_bust();
	return $n;
}

/** The starter set. Rules not listed take the defaults. */
function sp_book_type_starters(): array {
	return [
		[ 'name' => 'Class (single)', 'kind' => 'class', 'description' => 'A class or workshop with a set number of seats. Each date is booked and paid for on its own.',
			'rules' => [ 'duration_min' => 120, 'capacity' => 12, 'lead_time_min' => 0, 'max_advance_days' => 180, 'cancel_window_min' => 1440, 'requires_payment' => 'full', 'book_label' => 'Sign Up', 'full_label' => 'Class Full' ] ],
		[ 'name' => 'Class (multiple)', 'kind' => 'series', 'description' => 'A multi-week class: one price buys a seat in every date. Enrollment closes when the first date starts.',
			'rules' => [ 'duration_min' => 120, 'capacity' => 12, 'lead_time_min' => 0, 'max_advance_days' => 180, 'cancel_window_min' => 1440, 'requires_payment' => 'full', 'book_label' => 'Enroll', 'full_label' => 'Class Full' ] ],
		[ 'name' => 'Appointment (one-on-one)', 'kind' => 'appointment', 'description' => 'A one-on-one appointment at a set start time, from your working hours.',
			'rules' => [ 'duration_min' => 60, 'slot_interval_min' => 30, 'lead_time_min' => 720, 'max_advance_days' => 60, 'cancel_window_min' => 720 ] ],
		[ 'name' => 'House Call (service call)', 'kind' => 'call', 'description' => 'A visit to the customer’s address. They pick an arrival window; the route accounts for driving between jobs.',
			'rules' => [ 'duration_min' => 90, 'slot_mode' => 'window', 'windows' => [ [ 'label' => 'Morning', 'start' => '08:00', 'end' => '12:00' ], [ 'label' => 'Afternoon', 'start' => '12:00', 'end' => '17:00' ] ],
				'requires_address' => 1, 'travel_buffer' => 1, 'buffer_min' => 15, 'lead_time_min' => 1440, 'max_advance_days' => 30, 'cancel_window_min' => 1440 ] ],
	];
}

/**
 * Once per site: add the starter types, then put every existing service on the starter matching its
 * kind (a session service with arrival windows doesn't exist, so kind alone decides — an appointment
 * with windows goes on House Call). Overrides = every key that differs, so no value changes.
 */
add_action( 'init', 'sp_book_types_seed', 45 );
function sp_book_types_seed(): void {
	if ( get_option( 'sp_book_types_seeded' ) ) return;
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'booking' ) ) return;
	if ( get_option( 'site_pulse_booking_db_version' ) !== SITE_PULSE_BOOKING_DB_VERSION ) return;
	update_option( 'sp_book_types_seeded', 1, false );

	global $wpdb;
	$now = sp_book_now();
	$ids = [];
	foreach ( sp_book_type_starters() as $i => $t ) {
		$wpdb->insert( sp_book_table( 'types' ), [
			'name' => $t['name'], 'kind' => $t['kind'], 'description' => $t['description'],
			'rules' => wp_json_encode( sp_book_rules_normalize( $t['rules'] ) ),
			'display_order' => $i, 'created_at' => $now, 'updated_at' => $now,
		] );
		$ids[ $t['name'] ] = (int) $wpdb->insert_id;
	}

	foreach ( $wpdb->get_results( "SELECT * FROM " . sp_book_table( 'services' ) . " WHERE type_id = 0", ARRAY_A ) ?: [] as $svc ) {
		$kind = sp_book_service_kind( $svc );
		$name = [ 'class' => 'Class (single)', 'series' => 'Class (multiple)', 'call' => 'House Call (service call)', 'appointment' => 'Appointment (one-on-one)' ][ $kind ] ?? '';
		if ( empty( $ids[ $name ] ) ) continue;   // rentals stay standalone — there's no starter for them
		$trules = sp_book_type_rules( sp_book_get_type( $ids[ $name ] ) );
		$rules  = sp_book_service_rules( $svc );
		$over   = array_keys( array_filter( $rules, static fn( $v, $k ) => $v !== $trules[ $k ], ARRAY_FILTER_USE_BOTH ) );
		$wpdb->update( sp_book_table( 'services' ), [ 'type_id' => $ids[ $name ] ], [ 'id' => (int) $svc['id'] ] );
		sp_book_service_set_opts( (int) $svc['id'], [ 'overrides' => $over ] );
	}
	sp_book_cache_bust();
}


/**
 * Once: sites seeded with the first starter names get the clearer ones (2026-11-21). Only a type
 * still carrying its original starter name is renamed — a name the customer chose is left alone.
 */
/** Once: a type that was an appointment with arrival windows becomes the Service call kind (2026-11-21). */
add_action( 'init', 'sp_book_types_kind_call', 47 );
function sp_book_types_kind_call(): void {
	if ( get_option( 'sp_book_types_kind_call' ) || ! get_option( 'sp_book_types_seeded' ) ) return;
	if ( get_option( 'site_pulse_booking_db_version' ) !== SITE_PULSE_BOOKING_DB_VERSION ) return;
	update_option( 'sp_book_types_kind_call', 1, false );
	global $wpdb;
	foreach ( sp_book_get_types() as $t ) {
		if ( $t['kind'] === 'appointment' && sp_book_type_rules( $t )['slot_mode'] === 'window' ) {
			$wpdb->update( sp_book_table( 'types' ), [ 'kind' => 'call' ], [ 'id' => (int) $t['id'] ] );
		}
	}
}

add_action( 'init', 'sp_book_types_rename_starters', 46 );
function sp_book_types_rename_starters(): void {
	if ( get_option( 'sp_book_types_renamed_v2' ) || ! get_option( 'sp_book_types_seeded' ) ) return;
	if ( get_option( 'site_pulse_booking_db_version' ) !== SITE_PULSE_BOOKING_DB_VERSION ) return;
	update_option( 'sp_book_types_renamed_v2', 1, false );
	global $wpdb;
	$map = [ 'Class' => 'Class (single)', 'Class Series' => 'Class (multiple)', 'Appointment' => 'Appointment (one-on-one)', 'House Call' => 'House Call (service call)' ];
	foreach ( $map as $old => $new ) {
		$wpdb->update( sp_book_table( 'types' ), [ 'name' => $new, 'updated_at' => sp_book_now() ], [ 'name' => $old ] );
	}
}


/*--------------------------------------------------------------
# AJAX: types
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_booking_save_type', 'sp_book_ajax_save_type' );
function sp_book_ajax_save_type(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;
	global $wpdb;

	$id   = (int) ( $_POST['id'] ?? 0 );
	$name = sp_book_post( 'name' );
	if ( $name === '' ) wp_send_json_error( [ 'message' => 'Give the template a name.' ] );
	$type = $id ? sp_book_get_type( $id ) : null;
	if ( $id && ! $type ) wp_send_json_error( [ 'message' => 'That template no longer exists.' ] );

	$kind = sp_book_post( 'kind', $type['kind'] ?? 'appointment' );
	if ( ! isset( sp_book_type_kinds()[ $kind ] ) ) $kind = 'appointment';
	// Offerings can't change shape under their sign-ups, so a type in use keeps its kind.
	if ( $type && $kind !== $type['kind'] ) {
		$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . sp_book_table( 'services' ) . " WHERE type_id = %d", $id ) );
		if ( $used ) wp_send_json_error( [ 'message' => sprintf( '%d service%s use%s this template, so its kind can’t change. Make a new template instead.', $used, $used === 1 ? '' : 's', $used === 1 ? 's' : '' ) ] );
	}

	$rules = sp_book_rules_normalize( sp_book_kind_force( sp_book_rules_from_post() + ( $type ? sp_book_type_rules( $type ) : sp_book_rule_defaults() ), $kind ) );
	$data  = [
		'name'        => mb_substr( $name, 0, 150 ),
		'kind'        => $kind,
		'description' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['description'] ?? '' ) ) ),
		'rules'       => wp_json_encode( $rules ),
		'updated_at'  => sp_book_now(),
	];
	if ( $type ) {
		$wpdb->update( sp_book_table( 'types' ), $data, [ 'id' => $id ] );
	} else {
		$data['created_at']    = $data['updated_at'];
		$data['display_order'] = (int) $wpdb->get_var( "SELECT COALESCE(MAX(display_order),0)+1 FROM " . sp_book_table( 'types' ) );
		$wpdb->insert( sp_book_table( 'types' ), $data );
		$id = (int) $wpdb->insert_id;
	}
	$n = $type ? sp_book_type_apply( $id ) : 0;
	site_pulse_log( 'booking_service_saved', sprintf( 'Saved booking type: %s', $name ) );
	wp_send_json_success( [ 'id' => $id, 'message' => $n ? sprintf( 'Template saved. %d service%s updated.', $n, $n === 1 ? '' : 's' ) : 'Template saved.' ] );
}

/** Delete a type. Its offerings keep their current values and stop following it. */
add_action( 'wp_ajax_site_pulse_booking_delete_type', 'sp_book_ajax_delete_type' );
function sp_book_ajax_delete_type(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_admin_gate() ) return;
	global $wpdb;
	$id   = (int) ( $_POST['id'] ?? 0 );
	$type = sp_book_get_type( $id );
	if ( ! $type ) wp_send_json_error( [ 'message' => 'That template no longer exists.' ] );
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . sp_book_table( 'services' ) . " WHERE type_id = %d", $id ) ) as $sid ) {
		$wpdb->update( sp_book_table( 'services' ), [ 'type_id' => 0 ], [ 'id' => (int) $sid ] );
		sp_book_service_set_opts( (int) $sid, [ 'overrides' => [] ] );
	}
	$wpdb->delete( sp_book_table( 'types' ), [ 'id' => $id ] );
	sp_book_cache_bust();
	site_pulse_log( 'booking_service_saved', sprintf( 'Deleted booking type: %s', $type['name'] ) );
	wp_send_json_success( [ 'message' => 'Template deleted.' ] );
}


/*--------------------------------------------------------------
# Offerings: list facts + the website page
--------------------------------------------------------------*/

/** Upcoming dates + seats per session service, id => [ dates, next, taken, capacity ]. */
function sp_book_offering_stats(): array {
	global $wpdb;
	$out = [];
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT service_id, COUNT(*) AS dates, MIN(starts_at) AS next_at, COALESCE(SUM(seats_taken),0) AS taken, COALESCE(SUM(capacity),0) AS cap
		 FROM " . sp_book_table( 'sessions' ) . " WHERE status = 'scheduled' AND starts_at > %s GROUP BY service_id",
		sp_book_now()
	), ARRAY_A ) ?: [];
	foreach ( $rows as $r ) {
		$out[ (int) $r['service_id'] ] = [
			'dates'    => (int) $r['dates'],
			'next'     => sp_book_to_local( (string) $r['next_at'], 'D, M j · g:ia' ),
			'next_at'  => (string) $r['next_at'],
			'taken'    => (int) $r['taken'],
			'capacity' => (int) $r['cap'],
		];
	}
	$upcoming = $wpdb->get_results( $wpdb->prepare(
		"SELECT service_id, COUNT(*) AS n FROM " . sp_book_table( 'bookings' ) . " WHERE starts_at > %s AND status IN ('pending','confirmed') AND ( group_id = 0 OR group_id = id ) GROUP BY service_id",
		sp_book_now()
	), ARRAY_A ) ?: [];
	foreach ( $upcoming as $r ) $out[ (int) $r['service_id'] ]['booked'] = (int) $r['n'];
	return $out;
}

/** Events that can become an offering's website page: not linked to any booking yet. */
function sp_book_unlinked_events(): array {
	if ( ! function_exists( 'sp_book_events_ready' ) || ! sp_book_events_ready() ) return [];
	$ids = get_posts( [
		'post_type' => 'events', 'post_status' => [ 'publish', 'draft', 'pending', 'future' ], 'fields' => 'ids', 'posts_per_page' => 300,
		'orderby' => 'date', 'order' => 'DESC',
		'meta_query' => [ [ 'key' => '_bp_book_service_id', 'compare' => 'NOT EXISTS' ] ],
	] );
	return array_map( static fn( $id ) => [ 'id' => (int) $id, 'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES ), 'status' => get_post_status( $id ) ], $ids );
}

/**
 * The offering's website page (session offerings, Events on): 'new' makes an event page for it,
 * an event id links that one, 'none' unlinks. A new page publishes when the offering is active
 * and has dates; otherwise it waits as a draft and publishes when its first dates arrive.
 */
function sp_book_offering_set_page( int $service_id, string $page ) {
	if ( $page === '' || ! function_exists( 'sp_book_events_ready' ) || ! sp_book_events_ready() ) return true;
	$svc = sp_book_get_service( $service_id );
	if ( ! $svc || $svc['mode'] !== 'session' ) return true;
	$current = sp_book_service_event( $svc );

	if ( $page === 'none' ) {
		if ( $current ) return sp_book_event_link( $current, 0 );
		return true;
	}
	if ( $page === 'new' ) {
		if ( $current ) return true;
		$has_dates = (bool) ( sp_book_offering_stats()[ $service_id ]['dates'] ?? 0 );
		$eid = wp_insert_post( wp_slash( [
			'post_type'    => 'events',
			'post_status'  => ( $svc['status'] === 'active' && $has_dates ) ? 'publish' : 'draft',
			'post_title'   => (string) $svc['name'],
			'post_content' => (string) $svc['description'],
			'post_author'  => get_current_user_id(),
		] ), true );
		if ( is_wp_error( $eid ) ) return $eid;
		update_post_meta( (int) $eid, '_bp_book_auto_page', 1 );
		return sp_book_event_link( (int) $eid, $service_id );
	}
	$eid = (int) $page;
	if ( ! $eid || $eid === $current ) return true;
	if ( $current ) sp_book_event_link( $current, 0 );
	return sp_book_event_link( $eid, $service_id );
}

/** A page made by Booking waits as a draft until its offering has dates; then it goes live. */
add_action( 'sp_book_sessions_changed', 'sp_book_offering_publish_page', 20 );
function sp_book_offering_publish_page( $service_id ): void {
	$svc = sp_book_get_service( (int) $service_id );
	if ( ! $svc || $svc['status'] !== 'active' || ! function_exists( 'sp_book_service_event' ) ) return;
	$eid = sp_book_service_event( $svc );
	if ( ! $eid || get_post_status( $eid ) !== 'draft' || ! get_post_meta( $eid, '_bp_book_auto_page', true ) ) return;
	if ( empty( sp_book_offering_stats()[ (int) $service_id ]['dates'] ) ) return;
	delete_post_meta( $eid, '_bp_book_auto_page' );
	wp_update_post( [ 'ID' => $eid, 'post_status' => 'publish' ] );
}

/** Duplicate an offering — and its website page, when it has one (the page's copy links the copied booking). */
function sp_book_offering_duplicate( int $service_id ): int {
	$svc = sp_book_get_service( $service_id );
	if ( ! $svc ) return 0;
	$eid = function_exists( 'sp_book_service_event' ) ? sp_book_service_event( $svc ) : 0;
	if ( $eid && function_exists( 'sp_events_duplicate' ) ) {
		$new_event = sp_events_duplicate( $eid );
		if ( ! is_wp_error( $new_event ) ) {
			$copy = sp_book_event_service( (int) $new_event );
			if ( $copy ) return (int) $copy['id'];
		}
	}
	return sp_book_duplicate_service( $service_id );
}


/*--------------------------------------------------------------
# AJAX: customers (everyone who has booked)
--------------------------------------------------------------*/

/** The key one person is grouped by: email, else phone, else name. */
function sp_book_customer_key_sql(): string {
	return "COALESCE( NULLIF( LOWER( TRIM( b.customer_email ) ), '' ), NULLIF( TRIM( b.customer_phone ), '' ), TRIM( b.customer_name ) )";
}

add_action( 'wp_ajax_site_pulse_booking_customers', 'sp_book_ajax_customers' );
function sp_book_ajax_customers(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_view_gate() ) return;
	global $wpdb;

	$search = sp_book_post( 'search' );
	$sort   = sp_book_post( 'sort', 'recent' );
	$page   = max( 1, (int) ( $_POST['page'] ?? 1 ) );
	$per    = 50;
	$key    = sp_book_customer_key_sql();
	$where  = "b.status <> 'expired'" . sp_book_scope_sql();
	if ( $search !== '' ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= $wpdb->prepare( ' AND ( b.customer_name LIKE %s OR b.customer_email LIKE %s OR b.customer_phone LIKE %s )', $like, $like, $like );
	}
	$order = [
		'recent' => 'last_at DESC',
		'az'     => 'name ASC',
		'za'     => 'name DESC',
		'most'   => 'bookings DESC, last_at DESC',
		'spent'  => 'paid DESC, last_at DESC',
	][ $sort ] ?? 'last_at DESC';

	$t     = sp_book_table( 'bookings' );
	$now   = sp_book_now();
	$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT $key) FROM $t b WHERE $where" );
	$rows  = $wpdb->get_results( $wpdb->prepare(
		"SELECT $key AS ckey,
			MAX( b.id ) AS latest_id,
			MAX( b.customer_name ) AS name,
			MAX( b.customer_phone ) AS any_phone,
			COUNT( DISTINCT CASE WHEN b.group_id > 0 THEN b.group_id ELSE b.id END ) AS bookings,
			COUNT( DISTINCT CASE WHEN b.starts_at > %s AND b.status IN ('pending','confirmed') THEN ( CASE WHEN b.group_id > 0 THEN b.group_id ELSE b.id END ) END ) AS upcoming,
			MAX( b.starts_at ) AS last_at,
			SUM( b.paid_cents ) AS paid
		 FROM $t b WHERE $where GROUP BY ckey ORDER BY $order LIMIT %d OFFSET %d",
		$now, $per, ( $page - 1 ) * $per
	), ARRAY_A ) ?: [];

	// Name / email / phone as they were given on each person's most recent booking.
	$latest = [];
	$ids    = array_filter( array_map( static fn( $r ) => (int) $r['latest_id'], $rows ) );
	if ( $ids ) {
		foreach ( $wpdb->get_results( "SELECT id, customer_name, customer_email, customer_phone FROM $t WHERE id IN (" . implode( ',', $ids ) . ")", ARRAY_A ) ?: [] as $l ) {
			$latest[ (int) $l['id'] ] = $l;
		}
	}

	wp_send_json_success( [
		'customers' => array_map( static fn( $r ) => [
			'key'      => (string) $r['ckey'],
			'name'     => (string) ( $latest[ (int) $r['latest_id'] ]['customer_name'] ?? $r['name'] ),
			'email'    => (string) ( $latest[ (int) $r['latest_id'] ]['customer_email'] ?? '' ),
			'phone'    => (string) ( ( $latest[ (int) $r['latest_id'] ]['customer_phone'] ?? '' ) ?: $r['any_phone'] ),
			'bookings' => (int) $r['bookings'],
			'upcoming' => (int) $r['upcoming'],
			'last'     => $r['last_at'] ? sp_book_to_local( (string) $r['last_at'], 'M j, Y' ) : '',
			'paid'     => (int) $r['paid'] ? '$' . number_format( (int) $r['paid'] / 100, 2 ) : '',
		], $rows ),
		'total' => $total,
		'pages' => max( 1, (int) ceil( $total / $per ) ),
	] );
}

/** One person's bookings, newest first. A series enrollment is one row (its lead) with its date count. */
add_action( 'wp_ajax_site_pulse_booking_customer_get', 'sp_book_ajax_customer_get' );
function sp_book_ajax_customer_get(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_book_view_gate() ) return;
	global $wpdb;
	$ckey = sanitize_text_field( wp_unslash( (string) ( $_POST['key'] ?? '' ) ) );
	if ( $ckey === '' ) wp_send_json_error( [ 'message' => 'Bad request.' ] );
	$t   = sp_book_table( 'bookings' );
	$key = sp_book_customer_key_sql();
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT b.*, s.name AS service_name,
			( SELECT COUNT(*) FROM $t m WHERE b.group_id > 0 AND m.group_id = b.group_id ) AS group_size
		 FROM $t b LEFT JOIN " . sp_book_table( 'services' ) . " s ON s.id = b.service_id
		 WHERE $key = %s AND b.status <> 'expired' AND ( b.group_id = 0 OR b.group_id = b.id )" . sp_book_scope_sql() . "
		 ORDER BY b.starts_at DESC LIMIT 300",
		$ckey
	), ARRAY_A ) ?: [];
	if ( ! $rows ) wp_send_json_error( [ 'message' => 'No bookings found for that customer.' ] );

	$statuses = sp_book_statuses();
	$first    = $rows[0];
	wp_send_json_success( [
		'customer' => [
			'name'  => (string) $first['customer_name'],
			'email' => (string) $first['customer_email'],
			'phone' => (string) $first['customer_phone'],
		],
		'bookings' => array_map( static fn( $b ) => [
			'id'      => (int) $b['id'],
			'code'    => (string) $b['code'],
			'service' => (string) ( $b['service_name'] ?? '' ),
			'when'    => sp_book_to_local( (string) $b['starts_at'], 'D, M j, Y · g:ia' ),
			'future'  => (string) $b['starts_at'] > sp_book_now(),
			'dates'   => (int) $b['group_size'],
			'status'  => (string) $b['status'],
			'status_label' => $statuses[ $b['status'] ] ?? $b['status'],
			'price'   => (int) $b['price_cents'] ? '$' . number_format( (int) $b['price_cents'] / 100, 2 ) : 'Free',
			'paid'    => (int) $b['paid_cents'],
		], $rows ),
	] );
}


/*--------------------------------------------------------------
# Booking people in E-Commerce → Customers (Store on)
--------------------------------------------------------------*/

/** Everyone who has booked joins the Store's customer list, matched by email (free RSVPs make no order). */
add_filter( 'sp_store_customer_people', 'sp_book_store_customer_people' );
function sp_book_store_customer_people( array $people ): array {
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'booking' ) ) return $people;
	global $wpdb;
	$t    = sp_book_table( 'bookings' );
	$rows = $wpdb->get_results(
		"SELECT LOWER( TRIM( customer_email ) ) AS email, MAX( id ) AS latest_id, MAX( customer_phone ) AS phone,
			COUNT( DISTINCT CASE WHEN group_id > 0 THEN group_id ELSE id END ) AS bookings, MAX( created_at ) AS last_at
		 FROM $t WHERE status <> 'expired' AND customer_email <> '' GROUP BY LOWER( TRIM( customer_email ) ) LIMIT 2000",
		ARRAY_A
	) ?: [];
	// The name as given on each person's most recent booking.
	$names = [];
	$ids   = array_filter( array_map( static fn( $r ) => (int) $r['latest_id'], $rows ) );
	if ( $ids ) {
		foreach ( $wpdb->get_results( "SELECT id, customer_name FROM $t WHERE id IN (" . implode( ',', $ids ) . ")", ARRAY_A ) ?: [] as $n ) $names[ (int) $n['id'] ] = (string) $n['customer_name'];
	}
	foreach ( $rows as &$r ) $r['name'] = $names[ (int) $r['latest_id'] ] ?? '';
	unset( $r );
	foreach ( $rows as $r ) {
		$e = (string) $r['email'];
		if ( ! isset( $people[ $e ] ) ) {
			$people[ $e ] = [ 'email' => $e, 'name' => (string) $r['name'], 'phone' => (string) $r['phone'], 'orders' => 0, 'spent_cents' => 0,
				'currency' => function_exists( 'sp_store_currency' ) ? sp_store_currency() : 'USD', 'last' => (string) $r['last_at'], 'user_id' => 0, 'plans' => [] ];
		} else {
			if ( $people[ $e ]['phone'] === '' ) $people[ $e ]['phone'] = (string) $r['phone'];
			if ( (string) $r['last_at'] > (string) $people[ $e ]['last'] ) $people[ $e ]['last'] = (string) $r['last_at'];
		}
		$people[ $e ]['bookings'] = (int) $r['bookings'];
	}
	return $people;
}

/** One customer's bookings (a series = one row, its lead) for the Store's customer window. */
add_filter( 'sp_store_customer_detail', 'sp_book_store_customer_detail', 10, 2 );
function sp_book_store_customer_detail( array $c, string $email ): array {
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'booking' ) ) return $c;
	global $wpdb;
	$t    = sp_book_table( 'bookings' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT b.*, s.name AS service_name, ( SELECT COUNT(*) FROM $t m WHERE b.group_id > 0 AND m.group_id = b.group_id ) AS group_size
		 FROM $t b LEFT JOIN " . sp_book_table( 'services' ) . " s ON s.id = b.service_id
		 WHERE LOWER( TRIM( b.customer_email ) ) = %s AND b.status <> 'expired' AND ( b.group_id = 0 OR b.group_id = b.id )
		 ORDER BY b.starts_at DESC LIMIT 100",
		strtolower( trim( $email ) )
	), ARRAY_A ) ?: [];
	$statuses = sp_book_statuses();
	$c['bookings'] = array_map( static fn( $b ) => [
		'id'           => (int) $b['id'],
		'service'      => (string) ( $b['service_name'] ?? '' ),
		'when'         => sp_book_to_local( (string) $b['starts_at'], 'D, M j, Y · g:ia' ),
		'dates'        => (int) $b['group_size'],
		'status'       => (string) $b['status'],
		'status_label' => $statuses[ $b['status'] ] ?? $b['status'],
		'price'        => (int) $b['price_cents'] ? '$' . number_format( (int) $b['price_cents'] / 100, 2 ) : 'Free',
	], $rows );
	if ( $c['name'] === '' && $rows ) $c['name'] = (string) $rows[0]['customer_name'];
	if ( $c['phone'] === '' && $rows ) $c['phone'] = (string) $rows[0]['customer_phone'];
	return $c;
}
