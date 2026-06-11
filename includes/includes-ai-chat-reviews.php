<?php
/* Battle Plan Web Design — AI Chat: Review Requests (off Jobsite GEO)

/*--------------------------------------------------------------
Sends a "leave us a review" text after a new jobsite_geo entry —
but only when the customer hasn't already left a matching review.

Why this lives in the ai-chat module (not jobsite-geo):
  - This file is required only from includes-ai-chat.php, which loads
    only when the `ai_chat` module is ON. A site with Jobsite GEO but
    NOT ai-chat never loads this code, so Jobsite GEO behaves exactly
    as before. Requirement #1, satisfied by load order alone.
  - Even with ai-chat on, everything here is gated by
    bp_chat_reviews_enabled() (the `review_requests` flag + Jobsite GEO
    being active), so it's opt-in per site.

Compliance note: we ask EVERY customer who hasn't already reviewed —
we do NOT screen by sentiment and withhold the review link from
unhappy customers ("review gating"), which violates Google policy.
--------------------------------------------------------------*/

/**
 * Active only when ai-chat's review_requests flag is on AND Jobsite GEO
 * is installed on this site.
 */
function bp_chat_reviews_enabled(): bool {
	$o = bp_chat_config();
	if ( ! bp_module_on( $o, 'review_requests' ) ) return false;
	return bp_module_on( get_option( 'jobsite_geo' ) );
}

/**
 * Add a "Customer Phone" field to jobsite_geo posts so the review text has
 * somewhere to go. Added as its own side metabox (not by editing Jobsite
 * GEO's field group), and only when review requests are enabled.
 */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) return;
	if ( ! bp_chat_reviews_enabled() ) return;

	acf_add_local_field_group( [
		'key'    => 'group_bp_chat_review',
		'title'  => 'Customer Contact (review request)',
		'fields' => [ [
			'key'          => 'field_bp_customer_phone',
			'label'        => 'Customer Phone',
			'name'         => 'customer_phone',
			'type'         => 'text',
			'instructions' => 'Mobile number to text a review request after the job. Leave blank to skip.',
			'required'     => 0,
		] ],
		'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'jobsite_geo' ] ] ],
		'position' => 'side',
		'active'   => true,
	] );
} );

/**
 * On a published jobsite_geo entry, schedule a review-request text (unless one
 * was already requested). Deferred via cron so it doesn't block the save and so
 * the link is sent a little after the job, not the instant data is entered.
 */
add_action( 'save_post_jobsite_geo', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( wp_is_post_revision( $post_id ) ) return;
	if ( ! bp_chat_reviews_enabled() ) return;
	if ( get_post_status( $post_id ) !== 'publish' ) return;
	if ( get_post_meta( $post_id, '_bp_review_requested', true ) ) return;

	$delay = (int) ( bp_chat_config()['review_delay_hours'] ?? 1 ) * HOUR_IN_SECONDS;
	if ( ! wp_next_scheduled( 'bp_chat_send_review_request', [ (int) $post_id ] ) ) {
		wp_schedule_single_event( time() + max( 60, $delay ), 'bp_chat_send_review_request', [ (int) $post_id ] );
	}
}, 20 ); // after Jobsite GEO's own save (10), so its review-link matching has run

add_action( 'bp_chat_send_review_request', 'bp_chat_send_review_request' );
function bp_chat_send_review_request( $post_id ): void {
	$post_id = (int) $post_id;
	if ( ! bp_chat_reviews_enabled() ) return;
	if ( get_post_type( $post_id ) !== 'jobsite_geo' || get_post_status( $post_id ) !== 'publish' ) return;
	if ( get_post_meta( $post_id, '_bp_review_requested', true ) ) return;

	// #2: skip if this customer already has a matching review on the site.
	if ( bp_chat_jobsite_has_review( $post_id ) ) return;

	$phone = bp_chat_normalize_phone( (string) bp_chat_jobsite_phone( $post_id ) );
	if ( $phone === '' ) { error_log( "BP CHAT review: no phone on jobsite {$post_id}, skipping." ); return; }

	$link = bp_chat_review_link();
	if ( $link === '' ) { error_log( 'BP CHAT review: no review link configured (set ai_chat[review_link] or customer pid).' ); return; }

	$customer = function_exists( 'customer_info' ) ? customer_info() : [];
	$company  = $customer['name'] ?? get_bloginfo( 'name' );
	$name     = trim( (string) get_the_title( $post_id ) );
	$first    = $name !== '' ? explode( ' ', $name )[0] : '';
	$hi       = $first !== '' ? "Hi {$first}, " : 'Hi, ';

	$msg = $hi . "thanks for choosing {$company}! If you have a minute, we'd really appreciate a quick review: {$link}  "
		. "Reply STOP to opt out.";

	if ( bp_chat_send_sms( $phone, $msg ) ) {
		update_post_meta( $post_id, '_bp_review_requested', current_time( 'mysql' ) );
	}
}

/**
 * True if a review (testimonial) already exists for this jobsite's customer —
 * either directly linked, or matchable by Jobsite GEO's name key.
 */
function bp_chat_jobsite_has_review( int $post_id ): bool {
	$linked = get_post_meta( $post_id, 'review', true ); // ACF post_object → meta
	if ( ! empty( $linked ) ) return true;

	if ( function_exists( 'bp_match_key_from_title' ) ) {
		$key = bp_match_key_from_title( get_the_title( $post_id ) );
		if ( $key ) {
			$match = get_posts( [
				'post_type'      => 'testimonials',
				'post_status'    => [ 'publish', 'draft' ],
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_bp_match_key',
				'meta_value'     => $key,
			] );
			if ( $match ) return true;
		}
	}
	return false;
}

/** The customer's phone for this jobsite (manual ACF field; FSM mapping later). */
function bp_chat_jobsite_phone( int $post_id ): string {
	if ( function_exists( 'get_field' ) ) {
		$p = (string) get_field( 'customer_phone', $post_id );
		if ( $p !== '' ) return $p;
	}
	return (string) get_post_meta( $post_id, 'customer_phone', true );
}

/** Google "write a review" link — explicit option, else built from the Place ID. */
function bp_chat_review_link(): string {
	$o = bp_chat_config();
	$explicit = trim( (string) ( $o['review_link'] ?? '' ) );
	if ( $explicit !== '' ) return $explicit;

	$customer = function_exists( 'customer_info' ) ? customer_info() : [];
	$pid = $customer['pid'] ?? '';
	if ( is_array( $pid ) ) $pid = $pid[0] ?? '';
	$pid = (string) $pid;

	return $pid !== '' ? 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $pid ) : '';
}
