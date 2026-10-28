<?php
/* Battle Plan Web Design — Site Pulse: Store Customers & Members

/*--------------------------------------------------------------
Everyone who buys is a CUSTOMER. A customer who holds a membership is also a
MEMBER, and a member has a login so the site can show them what they paid for.
A shop that sells shirts never switches memberships on and never sees any of
this beyond a customer list; a shop that sells a curriculum does.

CUSTOMERS NEED NO NEW TABLE. Every order already carries the name and email
that were entered, so the customer list is the orders grouped by email, with
members folded in. Nothing has to be kept in step.

MEMBERS ARE WORDPRESS USERS, role bp_member. A locked page is rendered on the
server, so the server has to know who is asking, and the WordPress login
cookie is the thing it can read (WP Engine also bypasses its page cache on it).
Customer Connect's one-time-code login keeps its token in a request header,
which a plain page view never sends, so it cannot do this job.

A MEMBER IS NEVER STAFF. The role can only read; wp-admin redirects it away;
Site Pulse refuses it a nonce (see site_pulse_ajax_refresh_nonce) and its login
already refuses anyone without a Site Pulse profile; the private-site gate does
not let it through. sp_member_only() is the one test all of those use.

MEMBERSHIP LENGTH IS WORKED OUT WHEN ASKED, NOT BY A CRON. A membership has an
ends_at (or none, for forever), and "is it active" compares that to now. A
traffic-triggered cron cannot promise to run at midnight; arithmetic can.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Switch, Role & Guards
# Plans
# Memberships
# Accounts & Sign-in Links
# Purchases That Grant Access
# Renewing Plans (Stripe subscriptions)
# Protecting Content
# My Account Page
# Customers (the Site Pulse list)
# AJAX
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Switch, Role & Guards
--------------------------------------------------------------*/

/** Is the Memberships feature switched on for this store? Off by default. */
function sp_store_members_on(): bool {
	return function_exists( 'sp_store_active' ) && sp_store_active() && sp_store_get_setting( 'feature_memberships', '' ) === '1';
}

/** The member role: reading only. Created on demand, never by a migration that might not run. */
add_action( 'init', 'sp_member_register_role', 4 );
function sp_member_register_role(): void {
	// Only where Memberships is on — the Store loads on every Site Pulse site in the fleet,
	// and a role nobody can use is clutter in every Users screen. (The Woo importer also
	// switches Memberships on before it assigns the role.)
	if ( ! sp_store_members_on() ) return;
	if ( ! wp_roles()->is_role( 'bp_member' ) ) {
		add_role( 'bp_member', __( 'Member', 'battleplan' ), [ 'read' => true ] );
	}
}

/**
 * Is this user ONLY a member — no other role, no Site Pulse profile? The single
 * test every staff-only door uses to keep members out. A staff member who also
 * bought a membership is staff, and keeps everything staff has.
 */
function sp_member_only( int $user_id = 0 ): bool {
	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) return false;
	$user = get_userdata( $user_id );
	if ( ! $user ) return false;

	$roles = (array) $user->roles;
	if ( ! $roles || array_diff( $roles, [ 'bp_member' ] ) ) return false;
	if ( function_exists( 'site_pulse_get_user_profile' ) && site_pulse_get_user_profile( $user_id ) ) return false;
	return true;
}

/** Members never see wp-admin: anywhere but admin-ajax/admin-post sends them to My Account. */
add_action( 'admin_init', 'sp_member_keep_out_of_admin', 1 );
function sp_member_keep_out_of_admin(): void {
	if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) return;
	if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'admin-post.php' ) return;
	if ( ! sp_member_only() ) return;
	wp_safe_redirect( sp_member_account_url() );
	exit;
}

/**
 * On a private site, members still have to be able to reach their own door: the
 * My Account page and a sign-in link. Everything else stays behind the gate.
 */
add_filter( 'bp_private_allow_request', 'sp_member_private_allow' );
function sp_member_private_allow( $allow ) {
	if ( $allow || ! sp_store_members_on() ) return $allow;
	if ( ! empty( $_GET['sp_member_link'] ) || ! empty( $_POST['sp_member_act'] ) ) return true;
	$id = (int) get_option( 'sp_member_account_page', 0 );
	return $id && is_page( $id );
}

add_filter( 'show_admin_bar', 'sp_member_hide_admin_bar' );
function sp_member_hide_admin_bar( $show ) {
	return sp_member_only() ? false : $show;
}


/*--------------------------------------------------------------
# Plans
--------------------------------------------------------------*/

/** How long a plan lasts, in plain words. */
function sp_member_length_units(): array {
	return [
		'forever' => 'Never expires',
		'days'    => 'Days',
		'months'  => 'Months',
		'years'   => 'Years',
	];
}

/**
 * Every plan, active first. Memoised per request: the content filter asks for the
 * plans once per post on the page, and an archive of thirty lessons must not be
 * thirty identical queries. sp_member_save_plan() flushes it.
 */
function sp_member_plans( bool $active_only = false, bool $flush = false ): array {
	static $all = null;
	if ( $flush ) { $all = null; return []; }
	if ( $all === null ) {
		global $wpdb;
		$all = $wpdb->get_results(
			"SELECT * FROM " . sp_store_table( 'member_plans' ) . " ORDER BY status = 'active' DESC, display_order ASC, name ASC",
			ARRAY_A
		) ?: [];
		foreach ( $all as &$r ) {
			$r['sections'] = json_decode( (string) $r['sections'], true ) ?: [];
		}
		unset( $r );
	}
	return $active_only ? array_values( array_filter( $all, static fn( $p ) => $p['status'] === 'active' ) ) : $all;
}

function sp_member_get_plan( int $id ): ?array {
	foreach ( sp_member_plans() as $p ) {
		if ( (int) $p['id'] === $id ) return $p;
	}
	return null;
}

/** How a plan is paid for. A renewing plan's length IS its billing period. */
function sp_member_billings(): array {
	return [
		'once'  => 'One payment',
		'month' => 'Renews every month',
		'year'  => 'Renews every year',
	];
}

/** 'month' / 'year' for a renewing plan, '' for a one-payment plan. */
function sp_member_plan_interval( ?array $plan ): string {
	$b = (string) ( $plan['billing'] ?? 'once' );
	return in_array( $b, [ 'month', 'year' ], true ) ? $b : '';
}

/** The renewal interval a product is sold on, or '' when it is an ordinary one-off purchase. */
function sp_member_product_interval( int $product_id ): string {
	if ( ! sp_store_members_on() ) return '';
	$plan = sp_member_product_plan( $product_id );
	return $plan ? sp_member_plan_interval( sp_member_get_plan( $plan ) ) : '';
}

/**
 * Why this cart cannot be checked out as it is, or '' when it can.
 *
 * Stripe takes a renewing payment through a SUBSCRIPTION checkout, and that kind of
 * checkout cannot offer shipping choices. So a renewing membership goes through on
 * its own: no posted items alongside it, and one at a time. Saying so here, in plain
 * words, beats a refusal from Stripe after the customer has typed their address.
 */
function sp_member_checkout_problem( array $lines ): string {
	$renewing = [];
	$ships    = false;
	foreach ( $lines as $line ) {
		if ( sp_member_product_interval( (int) $line['product_id'] ) !== '' ) {
			$renewing[] = $line;
		} else {
			$variant = sp_store_get_variant( (int) $line['variant_id'] );
			if ( $variant && (int) $variant['requires_shipping'] ) $ships = true;
		}
	}
	if ( ! $renewing ) return '';

	$name = sp_store_plain_text( (string) $renewing[0]['title'] );
	if ( count( $renewing ) > 1 ) return 'Memberships that renew have to be bought one at a time. Please check out with just one of them in your cart.';
	if ( (int) $renewing[0]['qty'] > 1 ) return sprintf( 'You only need one "%s". Please set the quantity to 1.', $name );
	if ( $ships ) return sprintf( '"%s" renews automatically, so it has to be checked out on its own. Please buy the other items in a separate order.', $name );
	return '';
}

/** "Never expires", "12 months", "30 days", or "Renews monthly". */
function sp_member_plan_length_label( array $plan ): string {
	$interval = sp_member_plan_interval( $plan );
	if ( $interval !== '' ) return $interval === 'year' ? 'Renews yearly' : 'Renews monthly';
	if ( $plan['length_unit'] === 'forever' || (int) $plan['length_n'] <= 0 ) return 'Never expires';
	$n    = (int) $plan['length_n'];
	$unit = rtrim( strtolower( sp_member_length_units()[ $plan['length_unit'] ] ?? 'days' ), 's' );
	return $n . ' ' . $unit . ( $n === 1 ? '' : 's' );
}

/** The public post types a plan can lock as a whole — everything except the store's own and the system types. */
function sp_member_section_choices(): array {
	$out = [];
	foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
		if ( in_array( $type->name, [ 'attachment', SITE_PULSE_STORE_CPT, 'page', 'post', 'elements', 'universal' ], true ) ) continue;
		$out[ $type->name ] = (string) $type->labels->name;
	}
	return $out;
}

/**
 * Create or update a plan.
 * @return int|WP_Error
 */
function sp_member_save_plan( array $data ) {
	global $wpdb;
	$name = trim( sanitize_text_field( (string) ( $data['name'] ?? '' ) ) );
	if ( $name === '' ) return new WP_Error( 'sp_member', 'Give the plan a name.' );

	$unit = (string) ( $data['length_unit'] ?? 'forever' );
	if ( ! isset( sp_member_length_units()[ $unit ] ) ) $unit = 'forever';
	$n = $unit === 'forever' ? 0 : max( 1, (int) ( $data['length_n'] ?? 1 ) );

	$sections = array_values( array_intersect(
		array_map( 'sanitize_key', (array) ( $data['sections'] ?? [] ) ),
		array_keys( sp_member_section_choices() )
	) );

	$billing = (string) ( $data['billing'] ?? 'once' );
	if ( ! isset( sp_member_billings()[ $billing ] ) ) $billing = 'once';

	$now    = current_time( 'mysql', true );
	$fields = [
		'name'        => mb_substr( $name, 0, 120 ),
		'description' => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
		'billing'     => $billing,
		'length_unit' => $unit,
		'length_n'    => $n,
		'sections'    => wp_json_encode( $sections ),
		'status'      => ( $data['status'] ?? 'active' ) === 'archived' ? 'archived' : 'active',
		'updated_at'  => $now,
	];

	$id = (int) ( $data['id'] ?? 0 );
	if ( $id ) {
		$wpdb->update( sp_store_table( 'member_plans' ), $fields, [ 'id' => $id ] );
	} else {
		$fields['created_at'] = $now;
		$wpdb->insert( sp_store_table( 'member_plans' ), $fields );
		$id = (int) $wpdb->insert_id;
	}
	sp_member_plans( false, true );
	return $id ?: new WP_Error( 'sp_member', 'Could not save the plan.' );
}

/** The posts locked to a plan one by one (as opposed to a whole section). */
function sp_member_plan_pages( int $plan_id ): array {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sp_member_plan' AND meta_value = %s",
		(string) $plan_id
	) ) ?: [];
	$out = [];
	foreach ( array_map( 'intval', $ids ) as $pid ) {
		$post = get_post( $pid );
		if ( ! $post || $post->post_status === 'trash' ) continue;
		$out[] = [ 'id' => $pid, 'title' => get_the_title( $post ) ?: '(no title)', 'type' => (string) get_post_type_object( $post->post_type )->labels->singular_name, 'url' => (string) get_permalink( $pid ) ];
	}
	return $out;
}

/**
 * Lock or unlock one post for one plan. Stored as one meta ROW per plan
 * (_sp_member_plan), not a serialized array, so "which pages does this plan
 * unlock" is an indexed lookup rather than a scan of every post.
 */
function sp_member_set_post_plan( int $post_id, int $plan_id, bool $locked ): void {
	if ( $locked ) {
		$have = array_map( 'intval', get_post_meta( $post_id, '_sp_member_plan', false ) );
		if ( ! in_array( $plan_id, $have, true ) ) add_post_meta( $post_id, '_sp_member_plan', (string) $plan_id );
	} else {
		delete_post_meta( $post_id, '_sp_member_plan', (string) $plan_id );
	}
}


/*--------------------------------------------------------------
# Memberships
--------------------------------------------------------------*/

/** Is this membership row live right now? */
function sp_member_is_live( array $m ): bool {
	if ( $m['status'] !== 'active' ) return false;
	if ( empty( $m['ends_at'] ) ) return true;
	return strtotime( $m['ends_at'] . ' UTC' ) > time();
}

/** The status a person would use: Active, Expired, Cancelled. */
function sp_member_status_label( array $m ): array {
	if ( $m['status'] === 'cancelled' ) return [ 'label' => 'Cancelled', 'tone' => 'muted' ];
	if ( ! sp_member_is_live( $m ) )    return [ 'label' => 'Expired', 'tone' => 'warning' ];
	return [ 'label' => 'Active', 'tone' => 'success' ];
}

/** Every membership a user holds, with its plan name. */
function sp_member_user_memberships( int $user_id ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT m.*, p.name AS plan_name FROM " . sp_store_table( 'memberships' ) . " m
		   LEFT JOIN " . sp_store_table( 'member_plans' ) . " p ON p.id = m.plan_id
		  WHERE m.user_id = %d ORDER BY m.status = 'active' DESC, m.ends_at IS NULL DESC, m.ends_at DESC",
		$user_id
	), ARRAY_A ) ?: [];
}

/** The plan ids this user can use right now. */
function sp_member_live_plan_ids( int $user_id ): array {
	static $cache = [];
	if ( isset( $cache[ $user_id ] ) ) return $cache[ $user_id ];
	$ids = [];
	foreach ( sp_member_user_memberships( $user_id ) as $m ) {
		if ( sp_member_is_live( $m ) ) $ids[] = (int) $m['plan_id'];
	}
	return $cache[ $user_id ] = array_values( array_unique( $ids ) );
}

/** A plan's end date counted from a starting moment, or null for forever. */
function sp_member_end_from( array $plan, int $from_ts ): ?string {
	if ( $plan['length_unit'] === 'forever' || (int) $plan['length_n'] <= 0 ) return null;
	$n = (int) $plan['length_n'];
	$ts = strtotime( '+' . $n . ' ' . $plan['length_unit'], $from_ts );
	return gmdate( 'Y-m-d H:i:s', $ts ?: $from_ts );
}

/**
 * Give someone a plan, or more of it.
 *
 * One row per person per plan (UNIQUE user_id, plan_id). Buying again EXTENDS it:
 * the new length counts from whenever the current one would have ended, if that
 * is still in the future, so nobody loses the days they already paid for.
 *
 * A RENEWING plan passes $opts — `subscription` (Stripe's id) and `ends_at`, the
 * paid-up-to date Stripe reports — because its end is set by the billing period,
 * not by adding a length.
 *
 * @return int|WP_Error the membership id
 */
function sp_member_grant( int $user_id, int $plan_id, string $source = 'staff', int $order_id = 0, string $note = '', int $by = 0, array $opts = [] ) {
	global $wpdb;
	$plan = sp_member_get_plan( $plan_id );
	if ( ! $plan )    return new WP_Error( 'sp_member', 'That plan no longer exists.' );
	if ( ! $user_id ) return new WP_Error( 'sp_member', 'There is no account to give it to.' );

	$table = sp_store_table( 'memberships' );
	$now   = current_time( 'mysql', true );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d AND plan_id = %d", $user_id, $plan_id ), ARRAY_A );
	$user  = get_userdata( $user_id );
	$fixed = array_key_exists( 'ends_at', $opts );
	$sub   = (string) ( $opts['subscription'] ?? '' );

	if ( $row ) {
		$from = time();
		if ( sp_member_is_live( $row ) && ! empty( $row['ends_at'] ) ) $from = max( $from, (int) strtotime( $row['ends_at'] . ' UTC' ) );
		$was_forever = sp_member_is_live( $row ) && empty( $row['ends_at'] );
		$wpdb->update( $table, [
			'status'     => 'active',
			'ends_at'    => $fixed ? $opts['ends_at'] : ( $was_forever ? null : sp_member_end_from( $plan, $from ) ),
			'provider_subscription' => $sub !== '' ? $sub : (string) $row['provider_subscription'],
			'starts_at'  => sp_member_is_live( $row ) ? $row['starts_at'] : $now,
			'order_id'   => $order_id ?: (int) $row['order_id'],
			'source'     => $source,
			'note'       => mb_substr( $note !== '' ? $note : (string) $row['note'], 0, 255 ),
			'updated_at' => $now,
		], [ 'id' => (int) $row['id'] ] );
		$id = (int) $row['id'];
	} else {
		$wpdb->insert( $table, [
			'plan_id'    => $plan_id,
			'user_id'    => $user_id,
			'email'      => $user ? (string) $user->user_email : '',
			'status'     => 'active',
			'starts_at'  => $now,
			'ends_at'    => $fixed ? $opts['ends_at'] : sp_member_end_from( $plan, time() ),
			'provider_subscription' => $sub,
			'source'     => $source,
			'order_id'   => $order_id,
			'note'       => mb_substr( $note, 0, 255 ),
			'created_by' => $by,
			'created_at' => $now,
			'updated_at' => $now,
		] );
		$id = (int) $wpdb->insert_id;
	}

	if ( function_exists( 'site_pulse_log' ) ) {
		site_pulse_log( 'store_member', 'Gave "' . $plan['name'] . '" to ' . ( $user ? $user->user_email : '#' . $user_id ) . '.', [ 'membership_id' => $id, 'source' => $source, 'user_id' => $by ] );
	}
	return $id ?: new WP_Error( 'sp_member', 'Could not save the membership.' );
}

/** End a membership now. Kept as a row (cancelled), never deleted — it is part of someone's history. */
function sp_member_cancel( int $membership_id, int $by = 0 ) {
	global $wpdb;
	$table = sp_store_table( 'memberships' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $membership_id ), ARRAY_A );
	if ( ! $row ) return new WP_Error( 'sp_member', 'That membership no longer exists.' );

	// A renewing membership is stopped at Stripe FIRST — cancelling only here would
	// leave the card being charged for access the member no longer has.
	if ( $row['provider_subscription'] !== '' ) {
		$path = 'subscriptions/' . rawurlencode( (string) $row['provider_subscription'] );
		$sub  = sp_store_stripe( 'GET', $path );
		$over = ! is_wp_error( $sub ) && in_array( (string) ( $sub['status'] ?? '' ), [ 'canceled', 'incomplete_expired' ], true );
		$gone = is_wp_error( $sub ) && (int) ( $sub->get_error_data()['status'] ?? 0 ) === 404;
		if ( ! $over && ! $gone ) {
			$res = sp_store_stripe( 'DELETE', $path );
			if ( is_wp_error( $res ) ) {
				return new WP_Error( 'sp_member', 'Stripe would not stop the payments, so nothing was cancelled: ' . $res->get_error_message() );
			}
		}
	}

	$wpdb->update( $table, [ 'status' => 'cancelled', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $membership_id ] );
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'store_member', 'Cancelled membership #' . $membership_id . ' for ' . $row['email'] . '.', [ 'user_id' => $by ] );
	return true;
}


/*--------------------------------------------------------------
# Accounts & Sign-in Links
--------------------------------------------------------------*/

/** Where members sign in and see their things. Created once when the feature is first on. */
function sp_member_account_url( array $args = [] ): string {
	$id  = (int) get_option( 'sp_member_account_page', 0 );
	$url = $id && get_post_status( $id ) === 'publish' ? get_permalink( $id ) : home_url( '/my-account/' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * The My Account page — a real page holding [store-account], so the client can
 * rename it, move it in the menu, or add words above the form like any other page.
 */
add_action( 'init', 'sp_member_ensure_account_page', 30 );
function sp_member_ensure_account_page(): void {
	if ( ! sp_store_members_on() ) return;
	$id = (int) get_option( 'sp_member_account_page', 0 );
	if ( $id && get_post( $id ) ) return;

	global $wpdb;

	// A page already holding the form (preferably at /my-account/) is used, not duplicated.
	$found = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[store-account%' ORDER BY ( post_name = 'my-account' ) DESC, ID ASC LIMIT 1" );
	if ( $found ) {
		update_option( 'sp_member_account_page', $found, false );
		return;
	}

	// The first page loads after the feature is switched on can arrive together; each would
	// make its own page. INSERT IGNORE is atomic, so only one request wins and creates it.
	// A lock older than a minute is from a request that died, and is taken over.
	$lock = 'sp_member_account_page_lock';
	if ( ! $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )", $lock, (string) time() ) ) ) {
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $lock ) );
		if ( time() - $since < 60 ) return;
		$wpdb->update( $wpdb->options, [ 'option_value' => (string) time() ], [ 'option_name' => $lock ] );
	}

	$existing = get_page_by_path( 'my-account' );
	$new = wp_insert_post( [
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'My Account',
		'post_name'    => $existing ? 'member-account' : 'my-account',
		'post_content' => '[store-account]',
	] );
	if ( $new && ! is_wp_error( $new ) ) update_option( 'sp_member_account_page', (int) $new, false );
	$wpdb->delete( $wpdb->options, [ 'option_name' => $lock ] );
}

/**
 * The account for an email address, made if there is none.
 *
 * An existing account is used as it is — including a staff account, which stays
 * staff. A new one gets the member role and a random password nobody knows; the
 * welcome email's link is how its owner first gets in.
 *
 * @return array|WP_Error [ 'id' => int, 'created' => bool ]
 */
function sp_member_find_or_create_user( string $email, string $name = '' ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) return new WP_Error( 'sp_member', 'That is not an email address.' );

	$user = get_user_by( 'email', $email );
	if ( $user ) {
		// A member, no other role, whose role was lost somehow: put it back.
		if ( ! $user->roles ) $user->set_role( 'bp_member' );
		return [ 'id' => (int) $user->ID, 'created' => false ];
	}

	$base  = sanitize_user( strstr( $email, '@', true ) ?: 'member', true ) ?: 'member';
	$login = $base;
	for ( $i = 2; username_exists( $login ); $i++ ) $login = $base . $i;

	$name  = trim( sanitize_text_field( $name ) );
	$parts = $name !== '' ? preg_split( '/\s+/', $name, 2 ) : [ '' ];
	$id    = wp_insert_user( [
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 32, true, true ),
		'display_name' => $name !== '' ? $name : $email,
		'first_name'   => $parts[0] ?? '',
		'last_name'    => $parts[1] ?? '',
		'role'         => 'bp_member',
	] );
	if ( is_wp_error( $id ) ) return $id;
	return [ 'id' => (int) $id, 'created' => true ];
}

/**
 * A one-time sign-in link for a MEMBER account.
 *
 * Only the hash is stored, it works once, and it expires. Deliberately refused
 * for any account that is more than a member: an emailed link that signs in a
 * staff account would turn anyone's inbox into a way round their password.
 */
function sp_member_make_link( int $user_id, string $purpose = 'login', int $ttl = 0 ): string {
	if ( ! sp_member_only( $user_id ) ) return '';
	$ttl   = $ttl ?: ( $purpose === 'setpw' ? 7 * DAY_IN_SECONDS : 30 * MINUTE_IN_SECONDS );
	$token = bin2hex( random_bytes( 24 ) );
	update_user_meta( $user_id, '_sp_member_link', [
		'hash'    => hash( 'sha256', $token ),
		'expires' => time() + $ttl,
		'purpose' => $purpose === 'setpw' ? 'setpw' : 'login',
	] );
	return add_query_arg( [ 'sp_member_link' => $token, 'u' => $user_id ], sp_member_account_url() );
}

/** Spend a sign-in link: verify, burn it, sign the member in, send them to My Account. */
add_action( 'template_redirect', 'sp_member_use_link', 2 );
function sp_member_use_link(): void {
	if ( empty( $_GET['sp_member_link'] ) || empty( $_GET['u'] ) ) return;
	if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();

	$uid   = (int) $_GET['u'];
	$token = sanitize_text_field( wp_unslash( (string) $_GET['sp_member_link'] ) );
	$data  = get_user_meta( $uid, '_sp_member_link', true );

	$valid = is_array( $data )
		&& sp_member_only( $uid )
		&& (int) ( $data['expires'] ?? 0 ) > time()
		&& hash_equals( (string) ( $data['hash'] ?? '' ), hash( 'sha256', $token ) );

	if ( ! $valid ) {
		wp_safe_redirect( sp_member_account_url( [ 'sp_member_msg' => 'expired' ] ) );
		exit;
	}

	delete_user_meta( $uid, '_sp_member_link' );
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true );
	update_user_meta( $uid, '_sp_member_last_login', time() );

	wp_safe_redirect( sp_member_account_url( ( $data['purpose'] ?? '' ) === 'setpw' ? [ 'sp_member_msg' => 'setpw' ] : [] ) );
	exit;
}

/** Email a sign-in link to whoever owns this address — and say the same thing either way. */
function sp_member_send_login_link( string $email ): void {
	$user = get_user_by( 'email', sanitize_email( $email ) );
	if ( ! $user || ! sp_member_only( (int) $user->ID ) ) return;

	$link = sp_member_make_link( (int) $user->ID, 'login' );
	if ( $link === '' ) return;

	$site = bp_business_name();
	$body = "Hi " . ( $user->first_name ?: 'there' ) . ",\n\nHere is your link to sign in to " . $site . ". It works once, for the next 30 minutes.\n\n" . $link . "\n\nIf you did not ask for this, you can ignore this email.\n\n" . $site;
	wp_mail( $user->user_email, 'Your sign-in link for ' . $site, wpautop( esc_html( $body ) ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/** A small rate limit for anything a stranger can hammer: keyed on IP + what they typed. */
function sp_member_rate_ok( string $bucket, int $max, int $window ): bool {
	$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'sp_member_rl_' . md5( $bucket . '|' . $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) return false;
	set_transient( $key, $n + 1, $window );
	return true;
}


/*--------------------------------------------------------------
# Purchases That Grant Access
--------------------------------------------------------------*/

/** The plan a product gives when bought, or 0. */
function sp_member_product_plan( int $product_id ): int {
	return (int) get_post_meta( $product_id, '_sp_store_grants_plan', true );
}

/**
 * A paid order: anything on it that grants a plan is granted, to an account made
 * from the order's email if there is not one already, and the order is tied to it.
 * Priority 15 — after auto-fulfilment (5), before the receipt is queued (20), so
 * the welcome email and the receipt arrive together.
 */
add_action( 'sp_store_order_paid', 'sp_member_grant_from_order', 15 );
function sp_member_grant_from_order( int $order_id ): void {
	if ( ! sp_store_members_on() ) return;
	$order = sp_store_get_order( $order_id );
	if ( ! $order || ! is_email( (string) $order['customer_email'] ) ) return;

	$plans = [];
	foreach ( sp_store_order_items( $order_id ) as $item ) {
		$pid = sp_member_product_plan( (int) $item['product_id'] );
		if ( $pid && sp_member_get_plan( $pid ) ) $plans[ $pid ] = true;
	}
	if ( ! $plans ) return;

	$account = sp_member_find_or_create_user( (string) $order['customer_email'], (string) $order['customer_name'] );
	if ( is_wp_error( $account ) ) {
		error_log( 'sp_member_grant_from_order: ' . $account->get_error_message() );
		return;
	}

	$names = [];
	foreach ( array_keys( $plans ) as $pid ) {
		$opts = [];
		$sub  = (string) ( $order['provider_subscription'] ?? '' );
		if ( sp_member_plan_interval( sp_member_get_plan( (int) $pid ) ) !== '' && $sub !== '' ) {
			$opts = [ 'subscription' => $sub, 'ends_at' => sp_member_stripe_paid_until( $sub ) ];
		}
		$res = sp_member_grant( $account['id'], (int) $pid, 'purchase', $order_id, '', 0, $opts );
		if ( ! is_wp_error( $res ) ) $names[] = sp_member_get_plan( (int) $pid )['name'];
	}

	global $wpdb;
	if ( ! (int) $order['user_id'] ) {
		$wpdb->update( sp_store_table( 'orders' ), [ 'user_id' => $account['id'] ], [ 'id' => $order_id ] );
	}

	if ( $names && function_exists( 'sp_store_queue' ) ) {
		sp_store_queue( $order_id, $account['created'] ? 'member_welcome' : 'member_access', (string) $order['customer_email'], [
			'plans' => implode( ', ', $names ),
		] );
	}
}


/*--------------------------------------------------------------
# Renewing Plans (Stripe subscriptions)
--------------------------------------------------------------*/

/**
 * Stripe owns the renewing: it charges the card each period, retries a failed one,
 * and emails receipts if the client switches that on in their Stripe settings. What
 * happens HERE is only ever "move this membership's paid-up-to date", driven by
 * Stripe's events — so the membership can never claim more than Stripe has been paid.
 *
 * A renewing membership's ends_at is the end of the period Stripe has been paid for,
 * PLUS A SHORT GRACE. A renewal is charged at the very end of a period and confirmed
 * by webhook a moment later; without the grace, a slow webhook would lock a paying
 * member out of their lessons for a few minutes every month.
 */
function sp_member_grace(): int {
	return 3 * DAY_IN_SECONDS;
}

/** A Stripe period-end timestamp as the membership's ends_at, grace included. */
function sp_member_until( int $period_end ): string {
	return gmdate( 'Y-m-d H:i:s', $period_end + sp_member_grace() );
}

/** Ask Stripe how far this subscription is paid up. Falls back to a month from now. */
function sp_member_stripe_paid_until( string $subscription ): string {
	$sub = sp_store_stripe( 'GET', 'subscriptions/' . rawurlencode( $subscription ) );
	$end = is_wp_error( $sub ) ? 0 : (int) ( $sub['current_period_end'] ?? 0 );
	return sp_member_until( $end ?: time() + 31 * DAY_IN_SECONDS );
}

function sp_member_by_subscription( string $subscription ): ?array {
	if ( $subscription === '' ) return null;
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'memberships' ) . " WHERE provider_subscription = %s LIMIT 1", $subscription
	), ARRAY_A );
	return $row ?: null;
}

/**
 * The subscription events, called from the Store's Stripe webhook.
 *
 *   invoice.paid                   a period was paid for: move the date on, and note the
 *                                  renewal on the original order's money history
 *   customer.subscription.updated  active → follow the period; set to cancel at period end
 *                                  → end exactly then; past_due → leave the date alone,
 *                                  so Stripe's retries run inside the paid-up period
 *   customer.subscription.deleted  it is over: access ends now (or when Stripe says it did)
 *
 * The first period is NOT handled here — the membership does not exist until the
 * order settles, which reads the subscription itself. An event for a subscription
 * with no membership is simply ignored.
 */
function sp_member_handle_stripe_event( string $type, array $object ): void {
	global $wpdb;
	$table = sp_store_table( 'memberships' );
	$now   = current_time( 'mysql', true );

	if ( $type === 'invoice.paid' ) {
		$m = sp_member_by_subscription( (string) ( $object['subscription'] ?? '' ) );
		if ( ! $m ) return;
		$end = (int) ( $object['lines']['data'][0]['period']['end'] ?? 0 );
		if ( $end ) $wpdb->update( $table, [ 'status' => 'active', 'ends_at' => sp_member_until( $end ), 'updated_at' => $now ], [ 'id' => (int) $m['id'] ] );

		// A renewal is money in: it appears on the original order's history, where staff
		// look. UNIQUE (provider, provider_object) on the invoice id makes a redelivered
		// event a no-op. The first invoice is the order's own payment, so it is skipped.
		if ( ( $object['billing_reason'] ?? '' ) !== 'subscription_create' && (int) $m['order_id'] ) {
			$suppress = $wpdb->suppress_errors( true );
			$wpdb->insert( sp_store_table( 'payments' ), [
				'order_id'        => (int) $m['order_id'],
				'provider'        => 'stripe',
				'kind'            => 'renewal',
				'amount_cents'    => (int) ( $object['amount_paid'] ?? 0 ),
				'currency'        => strtoupper( (string) ( $object['currency'] ?? sp_store_currency() ) ),
				'status'          => 'succeeded',
				'provider_object' => (string) ( $object['id'] ?? '' ),
				'description'     => 'Membership renewal',
				'created_at'      => $now,
				'updated_at'      => $now,
			] );
			$wpdb->suppress_errors( $suppress );
		}
		return;
	}

	if ( $type === 'customer.subscription.updated' || $type === 'customer.subscription.deleted' ) {
		$m = sp_member_by_subscription( (string) ( $object['id'] ?? '' ) );
		if ( ! $m ) return;
		$status = (string) ( $object['status'] ?? '' );
		$end    = (int) ( $object['current_period_end'] ?? 0 );

		if ( $type === 'customer.subscription.deleted' || in_array( $status, [ 'canceled', 'unpaid', 'incomplete_expired' ], true ) ) {
			$ended = (int) ( $object['ended_at'] ?? 0 ) ?: time();
			$wpdb->update( $table, [ 'ends_at' => gmdate( 'Y-m-d H:i:s', $ended ), 'note' => 'Subscription ended', 'updated_at' => $now ], [ 'id' => (int) $m['id'] ] );
		} elseif ( ! empty( $object['cancel_at_period_end'] ) && $end ) {
			// Cancelled by the member, but paid up: keep access to the last paid day, no grace.
			$wpdb->update( $table, [ 'status' => 'active', 'ends_at' => gmdate( 'Y-m-d H:i:s', $end ), 'note' => 'Cancels at the end of the period', 'updated_at' => $now ], [ 'id' => (int) $m['id'] ] );
		} elseif ( in_array( $status, [ 'active', 'trialing' ], true ) && $end ) {
			$wpdb->update( $table, [ 'status' => 'active', 'ends_at' => sp_member_until( $end ), 'updated_at' => $now ], [ 'id' => (int) $m['id'] ] );
		}
		// past_due / incomplete: leave the date alone while Stripe retries.
	}
}

/**
 * A Stripe billing-portal link for one of this person's renewing memberships, where
 * they change their card or cancel. Stripe hosts the whole page, so no card detail
 * ever comes near this site. The portal has to have been saved once in the Stripe
 * dashboard (Settings → Billing → Customer portal) or Stripe refuses to open it.
 *
 * @return string|WP_Error
 */
function sp_member_billing_portal_url( int $membership_id, int $user_id ) {
	global $wpdb;
	$m = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'memberships' ) . " WHERE id = %d AND user_id = %d", $membership_id, $user_id
	), ARRAY_A );
	if ( ! $m || $m['provider_subscription'] === '' ) return new WP_Error( 'sp_member', 'There is no billing to manage for that membership.' );

	$sub = sp_store_stripe( 'GET', 'subscriptions/' . rawurlencode( (string) $m['provider_subscription'] ) );
	if ( is_wp_error( $sub ) || empty( $sub['customer'] ) ) return new WP_Error( 'sp_member', 'Billing could not be opened right now. Please try again shortly.' );

	$portal = sp_store_stripe( 'POST', 'billing_portal/sessions', [
		'customer'   => (string) $sub['customer'],
		'return_url' => sp_member_account_url(),
	] );
	if ( is_wp_error( $portal ) || empty( $portal['url'] ) ) return new WP_Error( 'sp_member', 'Billing could not be opened right now. Please get in touch and we will help.' );
	return (string) $portal['url'];
}


/*--------------------------------------------------------------
# Protecting Content
--------------------------------------------------------------*/

/** Which plans open this post: the ones it is locked to, plus any plan that locks its whole section. */
function sp_member_required_plans( int $post_id ): array {
	static $cache = [];
	if ( isset( $cache[ $post_id ] ) ) return $cache[ $post_id ];

	$ids  = array_map( 'intval', get_post_meta( $post_id, '_sp_member_plan', false ) );
	$type = get_post_type( $post_id );
	foreach ( sp_member_plans( true ) as $plan ) {
		if ( in_array( $type, $plan['sections'], true ) ) $ids[] = (int) $plan['id'];
	}
	// A plan that was archived no longer locks anything it was attached to.
	$active = array_map( 'intval', array_column( sp_member_plans( true ), 'id' ) );
	return $cache[ $post_id ] = array_values( array_unique( array_intersect( $ids, $active ) ) );
}

/** Can this person see this post's content? Staff always can. */
function sp_member_can_view( int $post_id, int $user_id = 0 ): bool {
	$need = sp_member_required_plans( $post_id );
	if ( ! $need ) return true;

	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) return false;
	if ( user_can( $user_id, 'edit_others_pages' ) ) return true;
	if ( function_exists( 'site_pulse_get_user_profile' ) && site_pulse_get_user_profile( $user_id ) ) return true;

	return (bool) array_intersect( $need, sp_member_live_plan_ids( $user_id ) );
}

/**
 * A locked page is never cached, for anyone. A cached copy is either the teaser
 * shown to a member who should see everything, or — far worse — the full page
 * shown to a stranger.
 */
add_action( 'template_redirect', 'sp_member_nocache_locked', 3 );
function sp_member_nocache_locked(): void {
	if ( ! sp_store_members_on() || ! is_singular() ) return;
	if ( ! sp_member_required_plans( (int) get_queried_object_id() ) ) return;
	if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
}

/**
 * Swap a locked post's content for a short preview and a way in.
 *
 * Priority 1, so the real content — and every shortcode in it — never runs at all
 * for someone without access. The REST API and feeds render through this same
 * filter, so they get the preview too.
 */
add_filter( 'the_content', 'sp_member_filter_content', 1 );
function sp_member_filter_content( $content ) {
	if ( ! sp_store_members_on() ) return $content;
	$post = get_post();
	if ( ! $post || sp_member_can_view( (int) $post->ID ) ) return $content;
	return sp_member_locked_box( $post );
}

/** The preview + "Members only" box a locked post shows instead of itself. */
function sp_member_locked_box( WP_Post $post ): string {
	$teaser = trim( (string) $post->post_excerpt );
	if ( $teaser === '' ) $teaser = wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 40 );

	$plans = array_filter( array_map( 'sp_member_get_plan', sp_member_required_plans( (int) $post->ID ) ) );
	$names = wp_list_pluck( $plans, 'name' );

	// Somewhere to buy it: a product that grants one of these plans.
	$buy = '';
	if ( $plans ) {
		$products = get_posts( [
			'post_type'   => SITE_PULSE_STORE_CPT,
			'post_status' => 'publish',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_query'  => [ [ 'key' => '_sp_store_grants_plan', 'value' => array_map( 'strval', array_keys( wp_list_pluck( $plans, 'id', 'id' ) ) ), 'compare' => 'IN' ] ],
		] );
		if ( $products ) $buy = get_permalink( (int) $products[0] );
	}

	$here = (string) get_permalink( $post );
	$out  =$teaser !== '' ? '<p class="store-locked-teaser">' . esc_html( $teaser ) . '</p>' : '';
	$out .= '<div class="store-locked">';
	$out .= '<p class="store-locked-title"><strong>This is for members.</strong></p>';
	if ( $names ) $out .= '<p>It is included with ' . esc_html( implode( ' or ', $names ) ) . '.</p>';
	$out .= '<p class="store-locked-actions">';
	if ( is_user_logged_in() ) {
		$out .= '<a class="store-btn" href="' . esc_url( $buy ?: sp_store_shop_url() ) . '">Get Access</a>';
	} else {
		$out .= '<a class="store-btn" href="' . esc_url( sp_member_account_url( [ 'redirect_to' => $here ] ) ) . '">Sign In</a> ';
		if ( $buy ) $out .= '<a class="store-btn store-btn-secondary" href="' . esc_url( $buy ) . '">Join</a>';
	}
	$out .= '</p></div>';
	return $out;
}

/** wp-admin: a "Members only" box on every page and post type a plan could lock. */
add_action( 'add_meta_boxes', 'sp_member_add_lock_box' );
function sp_member_add_lock_box(): void {
	if ( ! sp_store_members_on() || ! sp_member_plans( true ) ) return;
	$types = array_diff( get_post_types( [ 'public' => true ] ), [ 'attachment', SITE_PULSE_STORE_CPT ] );
	add_meta_box( 'sp_member_lock', 'Members Only', 'sp_member_render_lock_box', array_values( $types ), 'side', 'default' );
}

function sp_member_render_lock_box( WP_Post $post ): void {
	wp_nonce_field( 'sp_member_lock', 'sp_member_lock_nonce' );
	$mine = array_map( 'intval', get_post_meta( $post->ID, '_sp_member_plan', false ) );
	echo '<p class="description">Only people with one of these plans can see this. Everyone else sees a short preview and a sign-in button.</p>';
	foreach ( sp_member_plans( true ) as $plan ) {
		$whole = in_array( $post->post_type, $plan['sections'], true );
		printf(
			'<label style="display:block;margin:4px 0"><input type="checkbox" name="sp_member_plans[]" value="%d"%s%s> %s%s</label>',
			(int) $plan['id'],
			checked( $whole || in_array( (int) $plan['id'], $mine, true ), true, false ),
			disabled( $whole, true, false ),
			esc_html( $plan['name'] ),
			$whole ? ' <span class="description">(locks this whole section)</span>' : ''
		);
	}
}

add_action( 'save_post', 'sp_member_save_lock_box', 10, 2 );
function sp_member_save_lock_box( int $post_id, WP_Post $post ): void {
	if ( ! isset( $_POST['sp_member_lock_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sp_member_lock_nonce'] ) ), 'sp_member_lock' ) ) return;
	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;
	$want = array_map( 'intval', (array) ( $_POST['sp_member_plans'] ?? [] ) );
	foreach ( sp_member_plans() as $plan ) {
		sp_member_set_post_plan( $post_id, (int) $plan['id'], in_array( (int) $plan['id'], $want, true ) );
	}
}


/*--------------------------------------------------------------
# My Account Page
--------------------------------------------------------------*/

/** Sign-in, sign-in-link request and set-password posts, handled before the page renders. */
add_action( 'template_redirect', 'sp_member_handle_account_post', 4 );
function sp_member_handle_account_post(): void {
	if ( empty( $_POST['sp_member_act'] ) ) return;
	if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();

	$act  = sanitize_key( (string) $_POST['sp_member_act'] );
	$back = sp_member_account_url();
	if ( ! isset( $_POST['_spm'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_spm'] ) ), 'sp_member_' . $act ) ) {
		wp_safe_redirect( add_query_arg( 'sp_member_msg', 'stale', $back ) );
		exit;
	}

	if ( $act === 'login' ) {
		$email = sanitize_text_field( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );
		if ( ! sp_member_rate_ok( 'login|' . strtolower( $email ), 8, 15 * MINUTE_IN_SECONDS ) ) {
			wp_safe_redirect( add_query_arg( 'sp_member_msg', 'slow', $back ) );
			exit;
		}
		$user = is_email( $email ) ? get_user_by( 'email', $email ) : get_user_by( 'login', $email );
		$signed = $user ? wp_signon( [
			'user_login'    => $user->user_login,
			'user_password' => (string) wp_unslash( $_POST['password'] ?? '' ),
			'remember'      => true,
		], is_ssl() ) : new WP_Error( 'nope', '' );
		if ( is_wp_error( $signed ) ) {
			wp_safe_redirect( add_query_arg( 'sp_member_msg', 'bad', $back ) );
			exit;
		}
		$to = wp_validate_redirect( wp_unslash( (string) ( $_POST['redirect_to'] ?? '' ) ), $back );
		wp_safe_redirect( $to );
		exit;
	}

	if ( $act === 'link' ) {
		$email = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );
		if ( is_email( $email ) && sp_member_rate_ok( 'link|' . strtolower( $email ), 3, 15 * MINUTE_IN_SECONDS ) ) {
			sp_member_send_login_link( $email );
		}
		// The same answer whether or not the address has an account: this form must not
		// become a way to find out who is a member.
		wp_safe_redirect( add_query_arg( 'sp_member_msg', 'sent', $back ) );
		exit;
	}

	if ( $act === 'billing' && is_user_logged_in() ) {
		$url = sp_member_billing_portal_url( (int) ( $_POST['membership'] ?? 0 ), get_current_user_id() );
		if ( is_wp_error( $url ) ) {
			wp_safe_redirect( add_query_arg( 'sp_member_msg', 'billing', $back ) );
			exit;
		}
		// Stripe's own domain, so wp_safe_redirect (same-host only) would refuse it.
		wp_redirect( $url );
		exit;
	}

	if ( $act === 'password' && is_user_logged_in() ) {
		$pw = (string) wp_unslash( $_POST['password'] ?? '' );
		if ( strlen( $pw ) < 8 ) {
			wp_safe_redirect( add_query_arg( 'sp_member_msg', 'short', $back ) );
			exit;
		}
		$uid = get_current_user_id();
		wp_set_password( $pw, $uid );       // this logs the user out…
		wp_set_auth_cookie( $uid, true );   // …so sign them straight back in
		wp_safe_redirect( add_query_arg( 'sp_member_msg', 'pwset', $back ) );
		exit;
	}
}

/** The account page is personal: never cached. */
add_action( 'template_redirect', 'sp_member_nocache_account', 3 );
function sp_member_nocache_account(): void {
	$id = (int) get_option( 'sp_member_account_page', 0 );
	if ( ! $id || ! is_page( $id ) ) return;
	if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
	nocache_headers();
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
}

/** [store-account] — sign in when signed out; memberships, orders and password when signed in. */
add_shortcode( 'store-account', 'sp_member_account_shortcode' );
function sp_member_account_shortcode(): string {
	if ( ! sp_store_members_on() ) return '';

	$messages = [
		'bad'     => [ 'error', 'That email and password do not match. Try again, or have a sign-in link emailed to you.' ],
		'slow'    => [ 'error', 'Too many tries. Wait a few minutes, or use the emailed sign-in link instead.' ],
		'sent'    => [ 'ok', 'If that email has an account here, a sign-in link is on its way. It works for 30 minutes.' ],
		'expired' => [ 'error', 'That sign-in link has expired or was already used. Ask for a new one below.' ],
		'stale'   => [ 'error', 'That page had been open a long time. Please try again.' ],
		'setpw'   => [ 'ok', 'You are signed in. Choose a password below so you can sign in next time without a link.' ],
		'pwset'   => [ 'ok', 'Your password is saved.' ],
		'short'   => [ 'error', 'Use at least 8 characters for your password.' ],
		'billing' => [ 'error', 'Billing could not be opened right now. Please try again shortly, or get in touch and we will help.' ],
	];
	$msg = $messages[ sanitize_key( (string) ( $_GET['sp_member_msg'] ?? '' ) ) ] ?? null;
	$out = '<div class="store-account">';
	if ( $msg ) $out .= '<p class="store-account-msg store-account-msg-' . esc_attr( $msg[0] ) . '">' . esc_html( $msg[1] ) . '</p>';

	if ( ! is_user_logged_in() ) {
		$redirect = esc_url_raw( wp_unslash( (string) ( $_GET['redirect_to'] ?? '' ) ) );
		$out .= '<div class="store-account-grid">';
		$out .= '<form method="post" class="store-account-card"><h2>Sign In</h2>'
			. wp_nonce_field( 'sp_member_login', '_spm', false, false )
			. '<input type="hidden" name="sp_member_act" value="login"><input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '">'
			. '<p><label>Email<br><input type="email" name="email" autocomplete="email" required></label></p>'
			. '<p><label>Password<br><input type="password" name="password" autocomplete="current-password" required></label></p>'
			. '<p><button type="submit" class="store-btn unique">Sign In</button></p></form>';
		$out .= '<form method="post" class="store-account-card"><h2>No password?</h2>'
			. '<p>Forgot it, or never set one? We will email you a link that signs you straight in.</p>'
			. wp_nonce_field( 'sp_member_link', '_spm', false, false )
			. '<input type="hidden" name="sp_member_act" value="link">'
			. '<p><label>Email<br><input type="email" name="email" autocomplete="email" required></label></p>'
			. '<p><button type="submit" class="store-btn store-btn-secondary unique">Email Me a Sign-In Link</button></p></form>';
		$out .= '</div></div>';
		return $out;
	}

	$user = wp_get_current_user();
	$out .= '<p class="store-account-hello">Signed in as <strong>' . esc_html( $user->display_name ?: $user->user_email ) . '</strong> &middot; <a href="' . esc_url( wp_logout_url( sp_member_account_url() ) ) . '">Sign out</a></p>';

	// ---- Memberships, with a way into what each one unlocks.
	$memberships = sp_member_user_memberships( (int) $user->ID );
	if ( $memberships ) {
		$out .= '<section class="store-account-card"><h2>Your Memberships</h2><ul class="store-account-list">';
		foreach ( $memberships as $m ) {
			$state = sp_member_status_label( $m );
			$plan  = sp_member_get_plan( (int) $m['plan_id'] );
			$out .= '<li><strong>' . esc_html( (string) $m['plan_name'] ) . '</strong> <span class="store-account-state store-account-state-' . esc_attr( $state['tone'] ) . '">' . esc_html( $state['label'] ) . '</span>';
			if ( sp_member_is_live( $m ) ) {
				$out .= '<br><span class="store-account-sub">' . ( $m['ends_at'] ? 'Until ' . esc_html( sp_store_local_time( $m['ends_at'], 'F j, Y' ) ) : 'Never expires' ) . '</span>';
				$links = [];
				foreach ( (array) ( $plan['sections'] ?? [] ) as $type ) {
					$obj = get_post_type_object( $type );
					$url = get_post_type_archive_link( $type );
					if ( $obj && $url ) $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $obj->labels->name ) . '</a>';
				}
				foreach ( $plan ? sp_member_plan_pages( (int) $plan['id'] ) : [] as $p ) {
					$links[] = '<a href="' . esc_url( $p['url'] ) . '">' . esc_html( $p['title'] ) . '</a>';
				}
				if ( $links ) $out .= '<br><span class="store-account-sub">Open: ' . implode( ' &middot; ', $links ) . '</span>';
			}
			// A renewing membership: Stripe's own page for the card and for cancelling.
			if ( $m['provider_subscription'] !== '' && $m['status'] === 'active' ) {
				$out .= '<form method="post" class="store-account-inline">'
					. wp_nonce_field( 'sp_member_billing', '_spm', false, false )
					. '<input type="hidden" name="sp_member_act" value="billing"><input type="hidden" name="membership" value="' . (int) $m['id'] . '">'
					. '<button type="submit" class="store-btn store-btn-secondary unique">Manage Billing</button>'
					. '<span class="store-account-sub"> Change your card or cancel.</span></form>';
			}
			$out .= '</li>';
		}
		$out .= '</ul></section>';
	}

	// ---- Orders placed with this account or this email.
	global $wpdb;
	$orders = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'orders' ) . "
		  WHERE ( user_id = %d OR LOWER(customer_email) = %s ) AND payment_status <> 'unpaid'
		  ORDER BY COALESCE(placed_at, created_at) DESC LIMIT 50",
		(int) $user->ID, strtolower( $user->user_email )
	), ARRAY_A ) ?: [];
	if ( $orders ) {
		$out .= '<section class="store-account-card"><h2>Your Orders</h2><table class="store-account-orders"><thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody>';
		foreach ( $orders as $o ) {
			$row = sp_store_order_row( $o );
			$track = $o['tracking_url'] !== '' ? ' &middot; <a href="' . esc_url( $o['tracking_url'] ) . '" target="_blank" rel="noopener">Track</a>' : '';
			$out .= '<tr><td>' . esc_html( $row['number'] ) . '</td><td>' . esc_html( $row['placed'] ) . '</td><td>' . esc_html( $row['total'] ) . '</td><td>' . esc_html( $row['progress']['label'] ) . $track . '</td></tr>';
		}
		$out .= '</tbody></table></section>';

		// Every file their orders give, gathered in one place — the reason most people
		// with a download ever come back to this page.
		if ( function_exists( 'sp_store_downloads_html' ) ) {
			$dl = '';
			foreach ( $orders as $o ) $dl .= sp_store_downloads_html( $o, 'Downloads from order ' . $o['number'] );
			if ( $dl !== '' ) $out .= '<section class="store-account-card">' . $dl . '</section>';
		}
	}

	if ( ! $memberships && ! $orders ) {
		$out .= '<p>Nothing here yet. Anything you buy will show up on this page.</p>';
	}

	// ---- Password
	$out .= '<form method="post" class="store-account-card"><h2>' . ( isset( $_GET['sp_member_msg'] ) && $_GET['sp_member_msg'] === 'setpw' ? 'Choose a Password' : 'Change Password' ) . '</h2>'
		. wp_nonce_field( 'sp_member_password', '_spm', false, false )
		. '<input type="hidden" name="sp_member_act" value="password">'
		. '<p><label>New password<br><input type="password" name="password" autocomplete="new-password" minlength="8" required></label></p>'
		. '<p><button type="submit" class="store-btn unique">Save Password</button></p></form>';

	$out .= '</div>';
	return $out;
}


/*--------------------------------------------------------------
# Customers (the Site Pulse list)
--------------------------------------------------------------*/

/**
 * Everyone who has bought, plus every member — one row per email address.
 *
 * Built from the orders and the membership rows each time rather than stored, so
 * there is no customer table to fall out of step with what actually happened.
 * Fine at the size these stores are; the 2,000-row cap is the point to revisit.
 */
function sp_store_customers( array $args ): array {
	global $wpdb;
	$o = sp_store_table( 'orders' );

	$rows = $wpdb->get_results(
		"SELECT LOWER(customer_email) AS email, MAX(customer_name) AS name, MAX(customer_phone) AS phone,
		        COUNT(*) AS orders, SUM(paid_cents - refunded_cents) AS spent, MAX(COALESCE(placed_at, created_at)) AS last_order,
		        MAX(currency) AS currency
		   FROM $o WHERE payment_status <> 'unpaid' AND customer_email <> ''
		  GROUP BY LOWER(customer_email) ORDER BY last_order DESC LIMIT 2000",
		ARRAY_A
	) ?: [];

	$people = [];
	foreach ( $rows as $r ) {
		$people[ $r['email'] ] = [
			'email' => $r['email'], 'name' => (string) $r['name'], 'phone' => (string) $r['phone'],
			'orders' => (int) $r['orders'], 'spent_cents' => (int) $r['spent'], 'currency' => (string) $r['currency'],
			'last' => (string) $r['last_order'], 'user_id' => 0, 'plans' => [],
		];
	}

	if ( sp_store_members_on() ) {
		$m = $wpdb->get_results(
			"SELECT m.*, p.name AS plan_name, u.user_email, u.display_name FROM " . sp_store_table( 'memberships' ) . " m
			   JOIN {$wpdb->users} u ON u.ID = m.user_id
			   LEFT JOIN " . sp_store_table( 'member_plans' ) . " p ON p.id = m.plan_id",
			ARRAY_A
		) ?: [];
		foreach ( $m as $row ) {
			$email = strtolower( (string) $row['user_email'] );
			if ( ! isset( $people[ $email ] ) ) {
				$people[ $email ] = [ 'email' => $email, 'name' => (string) $row['display_name'], 'phone' => '', 'orders' => 0, 'spent_cents' => 0, 'currency' => sp_store_currency(), 'last' => (string) $row['created_at'], 'user_id' => 0, 'plans' => [] ];
			}
			$people[ $email ]['user_id'] = (int) $row['user_id'];
			$people[ $email ]['plans'][] = [ 'name' => (string) $row['plan_name'], 'state' => sp_member_status_label( $row ) ];
		}
	}

	$search = strtolower( trim( (string) ( $args['search'] ?? '' ) ) );
	$filter = (string) ( $args['filter'] ?? '' );
	$people = array_values( array_filter( $people, static function ( $p ) use ( $search, $filter ) {
		if ( $search !== '' && strpos( strtolower( $p['email'] . ' ' . $p['name'] ), $search ) === false ) return false;
		$live = (bool) array_filter( $p['plans'], static fn( $pl ) => $pl['state']['tone'] === 'success' );
		if ( $filter === 'members' && ! $live ) return false;
		if ( $filter === 'customers' && $live ) return false;
		return true;
	} ) );

	$per   = 30;
	$page  = max( 1, (int) ( $args['page'] ?? 1 ) );
	$total = count( $people );
	$slice = array_slice( $people, ( $page - 1 ) * $per, $per );

	foreach ( $slice as &$p ) {
		$p['spent'] = sp_store_money_text( $p['spent_cents'], $p['currency'] );
		$p['last']  = sp_store_local_time( $p['last'], 'M j, Y' );
	}
	return [ 'customers' => $slice, 'page' => $page, 'pages' => max( 1, (int) ceil( $total / $per ) ), 'total' => $total ];
}

/** One customer's orders and memberships, for the detail window. */
function sp_store_customer_detail( string $email ): array {
	global $wpdb;
	$email = strtolower( sanitize_email( $email ) );
	$rows  = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . sp_store_table( 'orders' ) . " WHERE LOWER(customer_email) = %s AND payment_status <> 'unpaid' ORDER BY COALESCE(placed_at, created_at) DESC LIMIT 100",
		$email
	), ARRAY_A ) ?: [];

	$user = get_user_by( 'email', $email );
	$memberships = [];
	if ( $user && sp_store_members_on() ) {
		foreach ( sp_member_user_memberships( (int) $user->ID ) as $m ) {
			$memberships[] = [
				'id'     => (int) $m['id'],
				'plan'   => (string) $m['plan_name'],
				'state'  => sp_member_status_label( $m ),
				'live'   => sp_member_is_live( $m ),
				'since'  => sp_store_local_time( $m['starts_at'], 'M j, Y' ),
				'until'  => $m['ends_at'] ? sp_store_local_time( $m['ends_at'], 'M j, Y' ) : 'Never expires',
				'source' => $m['source'] === 'purchase' ? 'Bought' : ( $m['source'] === 'import' ? 'Moved from the old site' : 'Added by staff' ),
			];
		}
	}

	$name = $rows ? (string) $rows[0]['customer_name'] : ( $user ? (string) $user->display_name : '' );
	return [
		'email'        => $email,
		'name'         => $name,
		'phone'        => $rows ? (string) $rows[0]['customer_phone'] : '',
		'has_account'  => (bool) $user,
		'member_only'  => $user ? sp_member_only( (int) $user->ID ) : false,
		'orders'       => array_map( 'sp_store_order_row', $rows ),
		'memberships'  => $memberships,
	];
}


/*--------------------------------------------------------------
# AJAX
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_store_customers', 'sp_store_ajax_customers' );
function sp_store_ajax_customers(): void {
	sp_store_orders_gate( 'sp_store_can_view' );
	$out = sp_store_customers( [
		'search' => sanitize_text_field( wp_unslash( (string) ( $_POST['search'] ?? '' ) ) ),
		'filter' => sanitize_key( (string) ( $_POST['filter'] ?? '' ) ),
		'page'   => (int) ( $_POST['page'] ?? 1 ),
	] );
	$out['members_on'] = sp_store_members_on();
	$out['plans']      = sp_store_members_on() ? array_map( static fn( $p ) => [ 'id' => (int) $p['id'], 'name' => $p['name'] ], sp_member_plans( true ) ) : [];
	wp_send_json_success( $out );
}

add_action( 'wp_ajax_site_pulse_store_customer_get', 'sp_store_ajax_customer_get' );
function sp_store_ajax_customer_get(): void {
	$me = sp_store_orders_gate( 'sp_store_can_view' );
	wp_send_json_success( [
		'customer'   => sp_store_customer_detail( (string) wp_unslash( $_POST['email'] ?? '' ) ),
		'can_manage' => sp_store_can_manage_orders( $me ),
		'members_on' => sp_store_members_on(),
		'plans'      => sp_store_members_on() ? array_map( static fn( $p ) => [ 'id' => (int) $p['id'], 'name' => $p['name'], 'length' => sp_member_plan_length_label( $p ) ], sp_member_plans( true ) ) : [],
	] );
}

/** Give a plan by hand — to an existing customer, or a brand-new person typed in. */
add_action( 'wp_ajax_site_pulse_store_member_grant', 'sp_store_ajax_member_grant' );
function sp_store_ajax_member_grant(): void {
	$me = sp_store_orders_gate( 'sp_store_can_manage_orders' );
	if ( ! sp_store_members_on() ) wp_send_json_error( [ 'message' => 'Memberships are switched off for this store.' ] );

	$email = (string) wp_unslash( $_POST['email'] ?? '' );
	$acct  = sp_member_find_or_create_user( $email, (string) wp_unslash( $_POST['name'] ?? '' ) );
	if ( is_wp_error( $acct ) ) wp_send_json_error( [ 'message' => $acct->get_error_message() ] );

	$res = sp_member_grant( $acct['id'], (int) ( $_POST['plan_id'] ?? 0 ), 'staff', 0, sanitize_text_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ), $me );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );

	// A new account has no way in yet; send the welcome link unless staff said not to.
	$sent = false;
	if ( ! empty( $_POST['notify'] ) && sp_member_only( $acct['id'] ) ) {
		sp_member_send_welcome( $acct['id'], (string) sp_member_get_plan( (int) $_POST['plan_id'] )['name'] );
		$sent = true;
	}

	wp_send_json_success( [
		'message'  => 'Membership given' . ( $sent ? ' and a sign-in link emailed.' : '.' ),
		'customer' => sp_store_customer_detail( $email ),
	] );
}

/** The welcome note for an account staff created by hand (purchases use the order email templates). */
function sp_member_send_welcome( int $user_id, string $plan_name ): void {
	$user = get_userdata( $user_id );
	$link = sp_member_make_link( $user_id, 'setpw' );
	if ( ! $user || $link === '' ) return;
	$site = bp_business_name();
	$body = "Hi " . ( $user->first_name ?: 'there' ) . ",\n\nYou now have " . $plan_name . " at " . $site . ".\n\nUse this link to sign in and choose a password. It works once, for the next 7 days:\n" . $link . "\n\nAfter that you can sign in any time at " . sp_member_account_url() . "\n\n" . $site;
	wp_mail( $user->user_email, 'Your ' . $site . ' membership', wpautop( esc_html( $body ) ), [ 'Content-Type: text/html; charset=UTF-8' ] );
}

add_action( 'wp_ajax_site_pulse_store_member_update', 'sp_store_ajax_member_update' );
function sp_store_ajax_member_update(): void {
	$me  = sp_store_orders_gate( 'sp_store_can_manage_orders' );
	$id  = (int) ( $_POST['membership_id'] ?? 0 );
	$act = sanitize_key( (string) ( $_POST['act'] ?? '' ) );

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . sp_store_table( 'memberships' ) . " WHERE id = %d", $id ), ARRAY_A );
	if ( ! $row ) wp_send_json_error( [ 'message' => 'That membership no longer exists.' ] );

	if ( $act === 'cancel' )      $res = sp_member_cancel( $id, $me );
	elseif ( $act === 'extend' )  $res = sp_member_grant( (int) $row['user_id'], (int) $row['plan_id'], 'staff', 0, 'Extended by staff', $me );
	elseif ( $act === 'link' ) {
		$user = get_userdata( (int) $row['user_id'] );
		if ( $user ) sp_member_send_login_link( $user->user_email );
		$res = $user && sp_member_only( (int) $user->ID ) ? true : new WP_Error( 'sp_member', 'This person signs in with a staff account, so no link was sent.' );
	} else $res = new WP_Error( 'sp_member', 'Unknown action.' );

	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );
	$user = get_userdata( (int) $row['user_id'] );
	wp_send_json_success( [
		'message'  => [ 'cancel' => 'Membership cancelled.', 'extend' => 'Membership extended.', 'link' => 'Sign-in link emailed.' ][ $act ],
		'customer' => sp_store_customer_detail( $user ? $user->user_email : (string) $row['email'] ),
	] );
}

/** Settings → Store → Membership Plans. */
add_action( 'wp_ajax_site_pulse_store_member_plans', 'sp_store_ajax_member_plans' );
function sp_store_ajax_member_plans(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	$plans = [];
	foreach ( sp_member_plans() as $p ) {
		$p['length_label'] = sp_member_plan_length_label( $p );
		$p['pages']        = sp_member_plan_pages( (int) $p['id'] );
		global $wpdb;
		$p['members']      = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . sp_store_table( 'memberships' ) . " WHERE plan_id = %d AND status = 'active' AND ( ends_at IS NULL OR ends_at > UTC_TIMESTAMP() )",
			(int) $p['id']
		) );
		$plans[] = $p;
	}
	wp_send_json_success( [
		'members_on' => sp_store_members_on(),
		'plans'    => $plans,
		'sections' => sp_member_section_choices(),
		'units'    => sp_member_length_units(),
		'billings' => sp_member_billings(),
		'account'  => sp_member_account_url(),
	] );
}

add_action( 'wp_ajax_site_pulse_store_member_plan_save', 'sp_store_ajax_member_plan_save' );
function sp_store_ajax_member_plan_save(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;

	$id = sp_member_save_plan( [
		'id'          => (int) ( $_POST['id'] ?? 0 ),
		'name'        => wp_unslash( (string) ( $_POST['name'] ?? '' ) ),
		'description' => wp_unslash( (string) ( $_POST['description'] ?? '' ) ),
		'billing'     => sanitize_key( (string) ( $_POST['billing'] ?? 'once' ) ),
		'length_unit' => sanitize_key( (string) ( $_POST['length_unit'] ?? 'forever' ) ),
		'length_n'    => (int) ( $_POST['length_n'] ?? 0 ),
		'sections'    => json_decode( wp_unslash( (string) ( $_POST['sections'] ?? '[]' ) ), true ) ?: [],
		'status'      => sanitize_key( (string) ( $_POST['status'] ?? 'active' ) ),
	] );
	if ( is_wp_error( $id ) ) wp_send_json_error( [ 'message' => $id->get_error_message() ] );

	// Individual pages: the posted list is the whole truth for this plan.
	$want = array_map( 'intval', json_decode( wp_unslash( (string) ( $_POST['pages'] ?? '[]' ) ), true ) ?: [] );
	$have = array_column( sp_member_plan_pages( (int) $id ), 'id' );
	foreach ( array_diff( $have, $want ) as $pid ) sp_member_set_post_plan( (int) $pid, (int) $id, false );
	foreach ( array_diff( $want, $have ) as $pid ) {
		if ( get_post( (int) $pid ) ) sp_member_set_post_plan( (int) $pid, (int) $id, true );
	}

	wp_send_json_success( [ 'message' => 'Plan saved.', 'id' => (int) $id ] );
}

/** Page picker for a plan: search every public post by title. */
add_action( 'wp_ajax_site_pulse_store_member_find_pages', 'sp_store_ajax_member_find_pages' );
function sp_store_ajax_member_find_pages(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! site_pulse_admin_check( 'manage_store_settings' ) ) return;
	$q = new WP_Query( [
		'post_type'      => array_values( array_diff( get_post_types( [ 'public' => true ] ), [ 'attachment', SITE_PULSE_STORE_CPT ] ) ),
		'post_status'    => [ 'publish', 'draft', 'private' ],
		's'              => sanitize_text_field( wp_unslash( (string) ( $_POST['q'] ?? '' ) ) ),
		'posts_per_page' => 20,
		'no_found_rows'  => true,
	] );
	wp_send_json_success( [ 'results' => array_map( static fn( $p ) => [
		'id' => (int) $p->ID, 'title' => get_the_title( $p ) ?: '(no title)', 'type' => (string) get_post_type_object( $p->post_type )->labels->singular_name,
	], $q->posts ) ] );
}
