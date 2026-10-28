<?php
/* Battle Plan Web Design — Site Pulse: Store Purchase Agreements

/*--------------------------------------------------------------
Terms a customer must accept before buying a particular product: a waiver, a
warranty agreement, a liability release. An Extras feature — off until switched
on, and invisible everywhere while off.

WRITTEN ONCE, ATTACHED TO PRODUCTS. An agreement is defined in Settings with its
title and text; a product ticks the ones it needs. Checkout shows every
agreement the basket needs — once each, however many products share it — with
an "I agree" box, and a typed-name signature where the agreement asks for one.

THE RECORD IS A SNAPSHOT. What is stored on the order is the exact TEXT the
customer saw, with the name they typed, the moment, and their IP address — in
its own table, one row per agreement per order. Editing an agreement next year
changes what future customers see and never what a past customer signed. That
is the difference between a record and a reference, and only a record is any use
if the agreement is ever disputed.

Accepted at checkout rather than on the product page: it is the one moment the
whole basket is known, it is before any money moves, and the server re-checks
it there rather than trusting a box ticked on some earlier page.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Switch & Library
# Products
# Checkout
# Reading the Record
# AJAX — Settings
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Switch & Library
--------------------------------------------------------------*/

function sp_store_agreements_on(): bool {
	return function_exists( 'sp_store_active' ) && sp_store_active() && sp_store_get_setting( 'feature_agreements', '' ) === '1';
}

/**
 * Every agreement: [ id => [ id, title, body, signature, updated ] ]. Kept as one
 * setting rather than a table: a store has a handful, they are edited as a set, and
 * the legally meaningful copy lives in the snapshot table, not here.
 */
function sp_store_agreements(): array {
	$raw = json_decode( sp_store_get_setting( 'agreements', '' ), true );
	$out = [];
	foreach ( is_array( $raw ) ? $raw : [] as $a ) {
		$id = (int) ( $a['id'] ?? 0 );
		if ( ! $id || trim( (string) ( $a['title'] ?? '' ) ) === '' ) continue;
		$out[ $id ] = [
			'id'        => $id,
			'title'     => (string) $a['title'],
			'body'      => (string) ( $a['body'] ?? '' ),
			'signature' => ! empty( $a['signature'] ),
			'updated'   => (string) ( $a['updated'] ?? '' ),
		];
	}
	return $out;
}

/** Save the whole set. New entries (id 0) get the next free id; ids never get reused. */
function sp_store_save_agreements( array $list ): array {
	$have = sp_store_agreements();
	$next = max( array_merge( [ 0, (int) sp_store_get_setting( 'agreements_next_id', '0' ) ], array_keys( $have ) ) ) + 1;
	$out  = [];
	foreach ( $list as $a ) {
		$title = trim( sanitize_text_field( (string) ( $a['title'] ?? '' ) ) );
		if ( $title === '' ) continue;
		$id   = (int) ( $a['id'] ?? 0 );
		if ( ! $id || ! isset( $have[ $id ] ) ) $id = $next++;
		$body = sanitize_textarea_field( (string) ( $a['body'] ?? '' ) );
		$changed = ! isset( $have[ $id ] ) || $have[ $id ]['body'] !== $body || $have[ $id ]['title'] !== $title;
		$out[] = [
			'id'        => $id,
			'title'     => mb_substr( $title, 0, 160 ),
			'body'      => $body,
			'signature' => ! empty( $a['signature'] ),
			'updated'   => $changed ? current_time( 'mysql', true ) : $have[ $id ]['updated'],
		];
	}
	sp_store_set_setting( 'agreements', wp_json_encode( $out ) );
	sp_store_set_setting( 'agreements_next_id', (string) ( $next - 1 ) );
	return $out;
}


/*--------------------------------------------------------------
# Products
--------------------------------------------------------------*/

/**
 * The agreement ids a product needs. One meta ROW per agreement (_sp_store_agreement),
 * not a serialized list, so "which products use this agreement" is an exact lookup.
 */
function sp_store_product_agreements( int $product_id ): array {
	return array_values( array_unique( array_map( 'intval', get_post_meta( $product_id, '_sp_store_agreement', false ) ) ) );
}

function sp_store_set_product_agreements( int $product_id, array $ids ): void {
	delete_post_meta( $product_id, '_sp_store_agreement' );
	foreach ( array_unique( array_filter( array_map( 'intval', $ids ) ) ) as $id ) {
		add_post_meta( $product_id, '_sp_store_agreement', (string) $id );
	}
}

/** The agreements a basket needs, each once, in library order. Empty when the feature is off. */
function sp_store_agreements_for_lines( array $lines ): array {
	if ( ! sp_store_agreements_on() ) return [];
	$need = [];
	foreach ( $lines as $line ) {
		foreach ( sp_store_product_agreements( (int) $line['product_id'] ) as $id ) $need[ $id ] = true;
	}
	return array_values( array_intersect_key( sp_store_agreements(), $need ) );
}


/*--------------------------------------------------------------
# Checkout
--------------------------------------------------------------*/

/** The Agreements section of the checkout page, or '' when the basket needs none. */
function sp_store_agreements_checkout_html( array $lines ): string {
	$list = sp_store_agreements_for_lines( $lines );
	if ( ! $list ) return '';

	$out  = '<section class="store-checkout-section store-agreements" data-store-agreements>';
	$out .= '<h2>Agreements</h2>';
	$out .= '<p class="store-field-note">Please read and accept these before paying. A copy is kept with your order and sent with your receipt.</p>';

	$sign = false;
	foreach ( $list as $a ) {
		$sign = $sign || $a['signature'];
		$out .= '<div class="store-agreement">';
		$out .= '<h3>' . esc_html( $a['title'] ) . '</h3>';
		$out .= '<div class="store-agreement-text" tabindex="0" role="document" aria-label="' . esc_attr( $a['title'] ) . '">' . wpautop( esc_html( $a['body'] ) ) . '</div>';
		$out .= '<label class="store-agreement-check"><input type="checkbox" name="sp_agree" value="' . (int) $a['id'] . '" required> '
			. 'I have read and agree to the ' . esc_html( $a['title'] ) . '.</label>';
		$out .= '</div>';
	}

	if ( $sign ) {
		$out .= '<div class="store-field-row">';
		$out .= '<label class="store-field"><span>Type your full name as your signature</span><input type="text" id="sp-co-sign" autocomplete="name" required></label>';
		$out .= '<label class="store-field"><span>Date</span><input type="text" value="' . esc_attr( wp_date( 'F j, Y' ) ) . '" readonly></label>';
		$out .= '</div>';
	}

	return $out . '</section>';
}

/**
 * Check what was posted against what the basket needs. The SERVER decides which
 * agreements are required — a box the browser left out is a box not ticked.
 *
 * @return array|WP_Error [ 'list' => agreements, 'name' => typed signature ]
 */
function sp_store_agreements_check( array $lines, array $posted_ids, string $name ) {
	$list = sp_store_agreements_for_lines( $lines );
	if ( ! $list ) return [ 'list' => [], 'name' => '' ];

	$posted = array_map( 'intval', $posted_ids );
	foreach ( $list as $a ) {
		if ( ! in_array( (int) $a['id'], $posted, true ) ) {
			return new WP_Error( 'sp_store_agree', 'Please read and accept the ' . $a['title'] . ' to continue.' );
		}
	}

	$name = trim( sanitize_text_field( $name ) );
	$needs_sign = (bool) array_filter( $list, static fn( $a ) => $a['signature'] );
	if ( $needs_sign && mb_strlen( $name ) < 2 ) {
		return new WP_Error( 'sp_store_agree', 'Please type your full name as your signature.' );
	}
	return [ 'list' => $list, 'name' => $needs_sign ? $name : '' ];
}

/** Write the snapshot rows for a just-created order. */
function sp_store_agreements_record( int $order_id, array $checked ): void {
	if ( ! $order_id || empty( $checked['list'] ) ) return;
	global $wpdb;
	$now = current_time( 'mysql', true );
	$ip  = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$ua  = mb_substr( sanitize_text_field( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 );

	foreach ( $checked['list'] as $a ) {
		$wpdb->insert( sp_store_table( 'order_agreements' ), [
			'order_id'     => $order_id,
			'agreement_id' => (int) $a['id'],
			'title'        => $a['title'],
			'body'         => $a['body'],
			'version'      => $a['updated'],
			'signed_name'  => $a['signature'] ? $checked['name'] : '',
			'accepted_at'  => $now,
			'ip'           => mb_substr( $ip, 0, 45 ),
			'user_agent'   => $ua,
		] );
	}
}


/*--------------------------------------------------------------
# Reading the Record
--------------------------------------------------------------*/

function sp_store_order_agreements( int $order_id ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'order_agreements' ) . " WHERE order_id = %d ORDER BY id ASC", $order_id
	), ARRAY_A ) ?: [];
}

/** For the order window: what was accepted, by whom, when, with the text as it was. */
function sp_store_order_agreements_display( int $order_id ): array {
	return array_map( static fn( $r ) => [
		'title' => (string) $r['title'],
		'name'  => (string) $r['signed_name'],
		'when'  => sp_store_local_time( $r['accepted_at'] ),
		'ip'    => (string) $r['ip'],
		'body'  => (string) $r['body'],
	], sp_store_order_agreements( $order_id ) );
}

/** The {agreements_block} email token: a short line per agreement, or nothing. */
function sp_store_agreements_email_block( int $order_id ): string {
	$rows = sp_store_order_agreements( $order_id );
	if ( ! $rows ) return '';
	$out = "You agreed to:\n";
	foreach ( $rows as $r ) {
		$when = sp_store_local_time( $r['accepted_at'], 'F j, Y' );
		$out .= '- ' . $r['title'] . ' (' . ( $r['signed_name'] !== '' ? 'signed "' . $r['signed_name'] . '", ' : '' ) . $when . ")\n";
	}
	return $out . "A copy of each is kept with your order. Reply to this email if you would like it sent to you.\n\n";
}


/*--------------------------------------------------------------
# AJAX — Settings
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_store_agreements', 'sp_store_ajax_agreements' );
function sp_store_ajax_agreements(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	global $wpdb;
	$list = [];
	foreach ( sp_store_agreements() as $a ) {
		$a['products'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			  WHERE pm.meta_key = '_sp_store_agreement' AND pm.meta_value = %s AND p.post_status <> 'trash'",
			(string) (int) $a['id']
		) );
		$list[] = $a;
	}
	wp_send_json_success( [ 'on' => sp_store_agreements_on(), 'agreements' => $list ] );
}

add_action( 'wp_ajax_site_pulse_store_save_agreements', 'sp_store_ajax_save_agreements' );
function sp_store_ajax_save_agreements(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;
	$list = json_decode( wp_unslash( (string) ( $_POST['agreements'] ?? '[]' ) ), true );
	sp_store_save_agreements( is_array( $list ) ? $list : [] );
	wp_send_json_success( [ 'message' => 'Agreements saved. Past orders keep the wording their customers agreed to.' ] );
}
