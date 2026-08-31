<?php
/**
 * Home Base — Equipment profiles (customer's heating & cooling systems)
 * ---------------------------------------------------------------------------
 * A lightweight per-customer inventory of HVAC equipment (type, brand, install
 * year, filter size + change tracking). Powers filter-change reminders and gives
 * the AI troubleshooter real context. Self-entered now; a CRM connector can
 * auto-fill later. REST under home-base/v1/equipment, token-authed per customer.
 *
 * The equipment TABLE is created in includes-home-base.php (hb_install_db); this
 * file adds the API + derived filter status. @package battleplan
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Allowed equipment types (the app's picker). 'Other' catches the rest. */
function hb_equipment_types(): array {
	return [ 'Central AC', 'Heat Pump', 'Furnace', 'Air Handler', 'Mini-Split', 'Boiler', 'Water Heater', 'Thermostat', 'Other' ];
}

/**
 * Shape an equipment row for the app, with derived filter + age info so the UI
 * and (phase c) reminders share one source of truth.
 */
function hb_equipment_public( array $r ): array {
	$interval = (int) ( $r['filter_interval_days'] ?: 90 );
	$filter   = [ 'status' => 'none', 'days_left' => null, 'next_due' => null ];

	if ( ! empty( $r['filter_changed_at'] ) && $r['filter_changed_at'] !== '0000-00-00' ) {
		$changed   = strtotime( $r['filter_changed_at'] . ' 00:00:00' );
		$next      = $changed + ( $interval * DAY_IN_SECONDS );
		$today     = strtotime( gmdate( 'Y-m-d' ) . ' 00:00:00' );
		$days_left = (int) floor( ( $next - $today ) / DAY_IN_SECONDS );
		$filter = [
			'status'    => $days_left <= 0 ? 'due' : ( $days_left <= 14 ? 'soon' : 'ok' ),
			'days_left' => $days_left,
			'next_due'  => gmdate( 'Y-m-d', $next ),
		];
	}

	$age = ! empty( $r['install_year'] ) ? max( 0, (int) gmdate( 'Y' ) - (int) $r['install_year'] ) : null;

	return [
		'id'                   => (int) $r['id'],
		'type'                 => $r['type'],
		'brand'                => $r['brand'],
		'model'                => $r['model'],
		'serial'               => $r['serial'],
		'install_year'         => $r['install_year'] ? (int) $r['install_year'] : null,
		'age_years'            => $age,
		'filter_size'          => $r['filter_size'],
		'filter_changed_at'    => ( $r['filter_changed_at'] && $r['filter_changed_at'] !== '0000-00-00' ) ? $r['filter_changed_at'] : '',
		'filter_interval_days' => $interval,
		'filter'               => $filter,
		'location_label'       => $r['location_label'],
		'notes'                => $r['notes'],
	];
}

/** Pull the writable fields from a request body, sanitized. */
function hb_equipment_input( array $body ): array {
	$out = [];
	if ( isset( $body['type'] ) )           $out['type']           = sanitize_text_field( (string) $body['type'] );
	if ( isset( $body['brand'] ) )          $out['brand']          = sanitize_text_field( (string) $body['brand'] );
	if ( isset( $body['model'] ) )          $out['model']          = sanitize_text_field( (string) $body['model'] );
	if ( isset( $body['serial'] ) )         $out['serial']         = sanitize_text_field( (string) $body['serial'] );
	if ( isset( $body['filter_size'] ) )    $out['filter_size']    = sanitize_text_field( (string) $body['filter_size'] );
	if ( isset( $body['location_label'] ) ) $out['location_label'] = sanitize_text_field( (string) $body['location_label'] );
	if ( isset( $body['notes'] ) )          $out['notes']          = sanitize_textarea_field( (string) $body['notes'] );

	if ( isset( $body['install_year'] ) ) {
		$y = (int) $body['install_year'];
		$out['install_year'] = ( $y >= 1950 && $y <= (int) gmdate( 'Y' ) + 1 ) ? $y : null;
	}
	if ( isset( $body['filter_interval_days'] ) ) {
		$d = (int) $body['filter_interval_days'];
		$out['filter_interval_days'] = ( $d >= 15 && $d <= 365 ) ? $d : 90;
	}
	if ( array_key_exists( 'filter_changed_at', $body ) ) {
		$v = trim( (string) $body['filter_changed_at'] );
		$out['filter_changed_at'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : null;
	}
	return $out;
}

/** Load one equipment row IF it belongs to the customer, else null. */
function hb_equipment_owned( int $id, int $customer_id ) {
	global $wpdb;
	$t = hb_table( 'equipment' );
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d AND customer_id = %d", $id, $customer_id ), ARRAY_A );
	return $r ?: null;
}

add_action( 'rest_api_init', function () {
	$ns = 'home-base/v1';

	register_rest_route( $ns, '/equipment', [
		[ 'methods' => 'GET',  'callback' => 'hb_rest_equipment_list',   'permission_callback' => '__return_true' ],
		[ 'methods' => 'POST', 'callback' => 'hb_rest_equipment_create', 'permission_callback' => '__return_true' ],
	] );
	register_rest_route( $ns, '/equipment/(?P<id>\d+)', [
		[ 'methods' => 'POST',   'callback' => 'hb_rest_equipment_update', 'permission_callback' => '__return_true' ],
		[ 'methods' => 'DELETE', 'callback' => 'hb_rest_equipment_delete', 'permission_callback' => '__return_true' ],
	] );
} );

/** GET /equipment — the signed-in customer's units, filter-due first. */
function hb_rest_equipment_list( WP_REST_Request $request ) {
	$customer = hb_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'hb_auth', 'Not signed in.', [ 'status' => 401 ] );

	global $wpdb;
	$t    = hb_table( 'equipment' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $t WHERE customer_id = %d ORDER BY id ASC", (int) $customer['id']
	), ARRAY_A ) ?: [];

	$items = array_map( 'hb_equipment_public', $rows );
	return rest_ensure_response( [ 'ok' => true, 'items' => $items, 'types' => hb_equipment_types() ] );
}

/** POST /equipment — add a unit. Requires at least a type. */
function hb_rest_equipment_create( WP_REST_Request $request ) {
	$customer = hb_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'hb_auth', 'Not signed in.', [ 'status' => 401 ] );

	$body = json_decode( $request->get_body(), true ) ?: [];
	$data = hb_equipment_input( $body );
	if ( empty( $data['type'] ) ) return new WP_Error( 'hb_bad', 'Pick an equipment type.', [ 'status' => 400 ] );

	global $wpdb;
	$now = current_time( 'mysql' );
	$data['customer_id'] = (int) $customer['id'];
	$data['created_at']  = $now;
	$data['updated_at']  = $now;
	$wpdb->insert( hb_table( 'equipment' ), $data );

	$row = hb_equipment_owned( (int) $wpdb->insert_id, (int) $customer['id'] );
	return rest_ensure_response( [ 'ok' => true, 'item' => hb_equipment_public( $row ) ] );
}

/** POST /equipment/{id} — update a unit the customer owns. */
function hb_rest_equipment_update( WP_REST_Request $request ) {
	$customer = hb_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'hb_auth', 'Not signed in.', [ 'status' => 401 ] );

	$id  = (int) $request['id'];
	$own = hb_equipment_owned( $id, (int) $customer['id'] );
	if ( ! $own ) return new WP_Error( 'hb_404', 'Not found.', [ 'status' => 404 ] );

	$body = json_decode( $request->get_body(), true ) ?: [];
	$data = hb_equipment_input( $body );
	if ( $data ) {
		$data['updated_at'] = current_time( 'mysql' );
		global $wpdb;
		$wpdb->update( hb_table( 'equipment' ), $data, [ 'id' => $id ] );
	}
	$row = hb_equipment_owned( $id, (int) $customer['id'] );
	return rest_ensure_response( [ 'ok' => true, 'item' => hb_equipment_public( $row ) ] );
}

/** DELETE /equipment/{id} — remove a unit the customer owns. */
function hb_rest_equipment_delete( WP_REST_Request $request ) {
	$customer = hb_current_customer( $request );
	if ( ! $customer ) return new WP_Error( 'hb_auth', 'Not signed in.', [ 'status' => 401 ] );

	$id  = (int) $request['id'];
	$own = hb_equipment_owned( $id, (int) $customer['id'] );
	if ( ! $own ) return new WP_Error( 'hb_404', 'Not found.', [ 'status' => 404 ] );

	global $wpdb;
	$wpdb->delete( hb_table( 'equipment' ), [ 'id' => $id ] );
	return rest_ensure_response( [ 'ok' => true ] );
}
