<?php
/* Battle Plan Web Design — Site Pulse: Booking

/*--------------------------------------------------------------
A proprietary booking engine for Battle Plan clients — HVAC service calls,
personal-training appointments, class/session studios, and rental stays — all
on ONE schema. The three shapes differ only in where availability comes from:

  appointment  slots are GENERATED from a resource's availability rules
               (HVAC service calls, Unscripted Fitness in-home training)
  session      slots are EXPLICIT ROWS generated ahead from a recurring class
               schedule, and customers claim a seat (studio classes)
  stay         a date RANGE over a unit, priced per night (the Smoobu shape)

Everything else — customers, holds, payment, passes, reminders, cancellation
policy — is shared. Adding a vertical means adding an availability resolver,
not a second booking system.

Audience: the STAFF side lives in Site Pulse (calendar, manual booking,
services & availability admin). The CUSTOMER side is a public booking widget on
the client's own website, plus the same flow inside the Customer Connect PWA
when the customer already has an account. Customers are NOT wp_users — a
booking either links to a `cc_customers` row (Customer Connect installed) or
carries its own name/email/phone snapshot, so booking works on a site that runs
Site Pulse alone.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Constants & Table Helper
# Vocabulary (modes, statuses)
# Permission Helpers
# Settings & Secret Storage
# Time Helpers (everything is stored UTC)
# Database Schema
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

require_once get_template_directory() . '/includes/includes-site-pulse-booking-engine.php';
require_once get_template_directory() . '/includes/includes-site-pulse-booking-admin.php';
require_once get_template_directory() . '/includes/includes-site-pulse-booking-calendar.php';
require_once get_template_directory() . '/includes/includes-site-pulse-booking-notify.php';
require_once get_template_directory() . '/includes/includes-site-pulse-booking-passes.php';
require_once get_template_directory() . '/includes/includes-site-pulse-booking-travel.php';

/*--------------------------------------------------------------
# Constants & Table Helper
--------------------------------------------------------------*/

// Booking owns its own schema version, separate from SITE_PULSE_DB_VERSION, so a
// booking schema change doesn't re-run dbDelta across all ~40 core Site Pulse
// tables on every install in the fleet (and vice versa). Same idiom as Fleet.
define( 'SITE_PULSE_BOOKING_DB_VERSION', '1.8' );

/**
 * All booking tables live under the site_pulse_booking_ prefix, so the whole
 * module is one `SHOW TABLES LIKE '%site_pulse_booking%'` away from being
 * audited, dumped, or dropped.
 */
function sp_book_table( string $name ): string {
	return $GLOBALS['wpdb']->prefix . 'site_pulse_booking_' . $name;
}


/*--------------------------------------------------------------
# Vocabulary (modes, statuses)
--------------------------------------------------------------*/

/** The three availability shapes. A service picks exactly one. */
function sp_book_modes(): array {
	return [
		'appointment' => 'Appointment — slots generated from availability rules',
		'session'     => 'Session — customers claim a seat in a scheduled class',
		'stay'        => 'Stay — a date range over a unit, priced per night',
	];
}

/**
 * Booking lifecycle.
 *   pending    a HOLD — the slot is reserved while the customer is in checkout.
 *              Expires at hold_expires_at and is swept back to `expired`.
 *   confirmed  paid (or payment not required, or a pass was redeemed).
 *   completed  the appointment happened.
 *   no_show    the customer didn't show — the trigger for a late-cancel fee.
 *   cancelled  cancelled by customer or staff.
 *   expired    a hold that was never paid for.
 * Only pending + confirmed occupy a slot; see sp_book_active_statuses().
 */
function sp_book_statuses(): array {
	return [
		'pending'   => 'Pending',
		'confirmed' => 'Confirmed',
		'completed' => 'Completed',
		'no_show'   => 'No Show',
		'cancelled' => 'Cancelled',
		'expired'   => 'Expired',
	];
}

/** The statuses that OCCUPY a slot — the ones availability must subtract. */
function sp_book_active_statuses(): array {
	return [ 'pending', 'confirmed', 'completed', 'no_show' ];
}

/** Where a booking came from. */
function sp_book_sources(): array {
	return [
		'web'   => 'Website',
		'pwa'   => 'Customer App',
		'staff' => 'Booked by staff',
		'ical'  => 'Imported calendar',
		'crm'   => 'CRM sync',
	];
}


/*--------------------------------------------------------------
# Permission Helpers
--------------------------------------------------------------*/

/** Can this user see the booking calendar at all (all bookings, or just their own)? */
function sp_book_can_view( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id )
		|| site_pulse_user_can( $user_id, 'view_bookings' )
		|| site_pulse_user_can( $user_id, 'view_own_bookings' )
		|| site_pulse_user_can( $user_id, 'manage_bookings' );
}

/**
 * Sees EVERY booking. Someone with only `view_own_bookings` is scoped to the
 * resources they're linked to (a tech seeing their own route, a trainer their
 * own day) — see sp_book_visible_resource_ids().
 */
function sp_book_can_view_all( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id )
		|| site_pulse_user_can( $user_id, 'view_bookings' )
		|| site_pulse_user_can( $user_id, 'manage_bookings' );
}

/** Can book / reschedule / cancel on a customer's behalf. */
function sp_book_can_manage( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_bookings' );
}

/** Can edit services, resources, and availability rules. */
function sp_book_can_manage_services( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_booking_services' );
}

/** Can issue/adjust passes and memberships, and refund payments. */
function sp_book_can_manage_payments( int $user_id = 0 ): bool {
	$user_id = $user_id ?: site_pulse_effective_user_id();
	return site_pulse_is_god( $user_id ) || site_pulse_user_can( $user_id, 'manage_booking_payments' );
}

/**
 * The resource ids a user may see bookings for. `view_bookings` (or god, or
 * manage) = all, signalled by an empty array meaning "no restriction". Someone
 * with only `view_own_bookings` gets the resources whose user_id is theirs —
 * and an empty result there must be treated as "nothing", not "everything", so
 * callers use sp_book_can_view_all() to tell the two cases apart.
 */
function sp_book_visible_resource_ids( int $user_id = 0 ): array {
	global $wpdb;
	$user_id = $user_id ?: site_pulse_effective_user_id();
	if ( sp_book_can_view_all( $user_id ) ) return [];

	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT id FROM " . sp_book_table( 'resources' ) . " WHERE user_id = %d",
		$user_id
	) ) ?: [];
	return array_map( 'intval', $ids );
}


/*--------------------------------------------------------------
# Settings & Secret Storage
--------------------------------------------------------------*/

/** A booking setting, namespaced in the shared site_pulse config table. */
function sp_book_get_setting( string $key, string $default = '' ): string {
	return site_pulse_get_setting( 'booking_' . $key, $default );
}

function sp_book_set_setting( string $key, string $value ): void {
	site_pulse_set_setting( 'booking_' . $key, $value );
}

/**
 * The key used to encrypt payment secrets at rest. Derived from the install's
 * own WP salts, so it is unique per site and never stored in the database that
 * holds the ciphertext. If the salts are ever rotated the stored secrets become
 * unreadable — which is the correct failure mode: re-enter the keys.
 */
function sp_book_secret_key(): string {
	$salt = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' );
	return hash( 'sha256', 'sp_booking|' . $salt, true );
}

/**
 * Store a payment secret ENCRYPTED, not base64'd. The Claude/Google keys
 * elsewhere in Site Pulse are only obfuscated, which is a defensible call for a
 * metered read API — it is not defensible for a Stripe secret key, where read
 * access to the database is read access to the money. AES-256-GCM with a random
 * IV, the auth tag stored alongside; storing '' clears the key.
 */
function sp_book_set_secret( string $key, string $value ): void {
	$value = trim( $value );
	if ( $value === '' ) { sp_book_set_setting( $key, '' ); return; }
	if ( ! function_exists( 'openssl_encrypt' ) ) {
		// No OpenSSL: refuse to silently downgrade a payment secret to plaintext.
		sp_book_set_setting( $key, '' );
		return;
	}
	$iv  = random_bytes( 12 );
	$tag = '';
	$ct  = openssl_encrypt( $value, 'aes-256-gcm', sp_book_secret_key(), OPENSSL_RAW_DATA, $iv, $tag );
	if ( $ct === false ) { sp_book_set_setting( $key, '' ); return; }
	sp_book_set_setting( $key, 'v1:' . base64_encode( $iv . $tag . $ct ) );
}

/** Read back a secret stored by sp_book_set_secret(). '' when unset or undecryptable. */
function sp_book_get_secret( string $key ): string {
	$raw = sp_book_get_setting( $key, '' );
	if ( $raw === '' || strpos( $raw, 'v1:' ) !== 0 ) return '';
	$bin = base64_decode( substr( $raw, 3 ), true );
	if ( $bin === false || strlen( $bin ) < 29 ) return '';
	$iv  = substr( $bin, 0, 12 );
	$tag = substr( $bin, 12, 16 );
	$ct  = substr( $bin, 28 );
	$out = openssl_decrypt( $ct, 'aes-256-gcm', sp_book_secret_key(), OPENSSL_RAW_DATA, $iv, $tag );
	return $out === false ? '' : $out;
}

/** Mask a secret for display — never send the real value back to the browser. */
function sp_book_mask_secret( string $value ): string {
	if ( $value === '' ) return '';
	return substr( $value, 0, 7 ) . '…' . substr( $value, -4 );
}


/*--------------------------------------------------------------
# Time Helpers (everything is stored UTC)
--------------------------------------------------------------*/

/**
 * The booking timezone. Bookings are stored in UTC and rendered local; this is
 * the "local" that means. Falls back through an explicit override (a resource's
 * own timezone — stay mode can span them) → the booking setting → WordPress →
 * Eastern.
 */
function sp_book_tz( string $override = '' ): DateTimeZone {
	$candidates = [ $override, sp_book_get_setting( 'timezone', '' ), (string) get_option( 'timezone_string' ) ];
	foreach ( $candidates as $tz ) {
		if ( $tz === '' ) continue;
		try { return new DateTimeZone( $tz ); } catch ( Exception $e ) { /* try the next candidate */ }
	}
	return new DateTimeZone( 'America/New_York' );
}

/** Right now, as a UTC 'Y-m-d H:i:s' string — what every datetime column stores. */
function sp_book_now(): string {
	return gmdate( 'Y-m-d H:i:s' );
}

/** A local wall-clock string ('2026-09-02 14:30') → the UTC string to store. */
function sp_book_to_utc( string $local, string $tz_override = '' ): string {
	if ( trim( $local ) === '' ) return '';
	try {
		$dt = new DateTime( $local, sp_book_tz( $tz_override ) );
	} catch ( Exception $e ) {
		return '';
	}
	$dt->setTimezone( new DateTimeZone( 'UTC' ) );
	return $dt->format( 'Y-m-d H:i:s' );
}

/** A stored UTC string → a local wall-clock string for display. */
function sp_book_to_local( string $utc, string $format = 'Y-m-d H:i:s', string $tz_override = '' ): string {
	if ( $utc === '' || $utc === '0000-00-00 00:00:00' ) return '';
	try {
		$dt = new DateTime( $utc, new DateTimeZone( 'UTC' ) );
	} catch ( Exception $e ) {
		return '';
	}
	$dt->setTimezone( sp_book_tz( $tz_override ) );
	return $dt->format( $format );
}

/**
 * The guard value written into bookings.slot_guard, which carries a UNIQUE
 * index: two concurrent requests for the same START TIME on the same exclusive
 * resource cannot both commit, no matter how the application layer is racing.
 *
 * Read the scope precisely. MySQL has no exclusion constraint, so this cannot
 * express "overlaps" — a 9:00–10:30 and a 10:00–11:00 booking on one resource
 * have different start times and both satisfy the index. Preventing THAT is the
 * job of the transaction in sp_book_claim_timed(), which locks the overlapping
 * range with SELECT … FOR UPDATE before it re-verifies and writes. The guard is
 * the backstop that makes the common case — two people taking the same gridded
 * slot — a hard database error rather than a matter of timing.
 *
 * NULL is returned (and stored) for anything that must NOT be guarded:
 *   - cancelled/expired bookings, so a freed slot can be rebooked
 *   - shared-capacity resources (a class of 12), where the seat count is
 *     enforced by an atomic UPDATE on sessions.seats_taken instead
 * MySQL unique indexes ignore NULLs, which is exactly the "partial index"
 * behaviour needed here.
 */
function sp_book_slot_guard( int $resource_id, string $starts_at_utc, string $status, bool $exclusive = true ): ?string {
	if ( ! $exclusive ) return null;
	if ( ! in_array( $status, sp_book_active_statuses(), true ) ) return null;
	if ( ! $resource_id || $starts_at_utc === '' ) return null;
	return $resource_id . ':' . $starts_at_utc;
}

/** A short, human-quotable booking reference. Unambiguous alphabet (no O/0/I/1). */
function sp_book_generate_code(): string {
	$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$prefix   = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', sp_book_get_setting( 'code_prefix', 'BK' ) ) . 'BK', 0, 2 ) );
	$code     = '';
	for ( $i = 0; $i < 6; $i++ ) $code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
	return $prefix . '-' . $code;
}


/*--------------------------------------------------------------
# Database Schema
--------------------------------------------------------------*/

/**
 * Create/upgrade the booking tables. Runs only when the module is ON, so the
 * fleet sites that never book anything stay clean. Bump
 * SITE_PULSE_BOOKING_DB_VERSION to ship a schema change.
 */
add_action( 'init', 'sp_book_install_db' );
function sp_book_install_db(): void {
	// Cheap autoloaded-option check first, so the settled case costs no query.
	if ( get_option( 'site_pulse_booking_db_version' ) === SITE_PULSE_BOOKING_DB_VERSION ) return;
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'booking' ) ) return;

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	// ---- Resources — the thing that can only be in one place at a time.
	// A trainer, a tech + truck, an instructor + studio room, a rental unit.
	// capacity > 1 means the resource is SHARED (a room that seats 12): those
	// bookings are counted against sessions.seats_taken instead of slot-guarded.
	$sql = "CREATE TABLE " . sp_book_table( 'resources' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		resource_type varchar(40) NOT NULL DEFAULT 'staff',
		user_id int(11) NOT NULL DEFAULT 0,
		location_id int(11) NOT NULL DEFAULT 0,
		capacity int(11) NOT NULL DEFAULT 1,
		timezone varchar(64) NOT NULL DEFAULT '',
		color varchar(20) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		phone varchar(30) NOT NULL DEFAULT '',
		display_order int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'active',
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY status (status)
	) $charset;";

	// slot_mode is 'exact' or 'window'.
	//
	//   exact   The trainer case. A 60-minute appointment starts at 9:00 and ends at 10:00.
	//           One booking owns that wall-clock slot; nothing else may overlap it.
	//
	//   window  The HVAC case. The customer is promised an ARRIVAL WINDOW ("8am-12pm") and the
	//           job might take ten minutes or two hours. duration_min then means the ESTIMATED
	//           job length, used for capacity and travel; the window is the promise.
	//           A window is a CONTAINER, not a slot: one truck can honestly promise 8-12 to
	//           three customers and sequence them on the morning. Window bookings therefore
	//           overlap by design, carry NO slot_guard, and are limited by how much work fits
	//           rather than by whether the clock is free.
	//
	// `windows` is the client's own list, e.g. [{"label":"Morning","start":"08:00","end":"12:00"}].
	// It is intersected with the resource's working hours at render time, so a Saturday that only
	// runs 8-12 drops the afternoon window automatically instead of being configured twice.
	// ---- Services — what the customer actually picks and pays for.
	// `mode` selects the availability resolver; everything else is policy.
	// Dispatch: dispatch_mode 'order' keeps the old behaviour (first free resource in display order);
	// 'nearest' picks by INSERTION COST — how much extra driving a job adds to that person's existing
	// day, not raw distance. out_of_area_min is that insertion cost (minutes) past which a job counts
	// as out of the way; out_of_area_policy is the client's call — block it, 'allow' (the default), or
	// surcharge. (Keep this prose OUT of the CREATE TABLE string — a quote/$ in it breaks the parse.)
	$sql .= "CREATE TABLE " . sp_book_table( 'services' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		slug varchar(100) NOT NULL,
		mode varchar(20) NOT NULL DEFAULT 'appointment',
		description text DEFAULT NULL,
		duration_min int(11) NOT NULL DEFAULT 60,
		slot_mode varchar(10) NOT NULL DEFAULT 'exact',
		dispatch_mode varchar(10) NOT NULL DEFAULT 'order',
		out_of_area_min int(11) NOT NULL DEFAULT 45,
		out_of_area_policy varchar(20) NOT NULL DEFAULT 'allow',
		out_of_area_fee_cents int(11) NOT NULL DEFAULT 0,
		windows longtext DEFAULT NULL,
		window_max_jobs int(11) NOT NULL DEFAULT 0,
		buffer_before_min int(11) NOT NULL DEFAULT 0,
		buffer_after_min int(11) NOT NULL DEFAULT 0,
		travel_buffer tinyint(1) NOT NULL DEFAULT 0,
		slot_interval_min int(11) NOT NULL DEFAULT 30,
		capacity int(11) NOT NULL DEFAULT 1,
		lead_time_min int(11) NOT NULL DEFAULT 720,
		max_advance_days int(11) NOT NULL DEFAULT 60,
		cancel_window_min int(11) NOT NULL DEFAULT 720,
		min_units int(11) NOT NULL DEFAULT 1,
		max_units int(11) NOT NULL DEFAULT 1,
		price_cents int(11) NOT NULL DEFAULT 0,
		deposit_cents int(11) NOT NULL DEFAULT 0,
		price_mode varchar(20) NOT NULL DEFAULT 'flat',
		currency char(3) NOT NULL DEFAULT 'USD',
		requires_payment varchar(20) NOT NULL DEFAULT 'none',
		allow_pass tinyint(1) NOT NULL DEFAULT 1,
		requires_address tinyint(1) NOT NULL DEFAULT 0,
		service_area_miles decimal(6,1) DEFAULT NULL,
		intake_fields longtext DEFAULT NULL,
		color varchar(20) NOT NULL DEFAULT '',
		display_order int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'active',
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY slug (slug),
		KEY status (status)
	) $charset;";

	// ---- Which resources can deliver which service (many-to-many).
	$sql .= "CREATE TABLE " . sp_book_table( 'service_resources' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		service_id int(11) NOT NULL,
		resource_id int(11) NOT NULL,
		display_order int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY service_resource (service_id, resource_id),
		KEY resource_id (resource_id)
	) $charset;";

	// ---- Availability rules — the weekly template plus its exceptions.
	//   hours     recurring weekly working hours (weekday + time range)
	//   open      extra one-off availability outside the weekly template
	//   blackout  time removed (vacation, holidays, an imported iCal busy block)
	// resource_id/service_id 0 = "applies to everything", so a site can set one
	// company-wide schedule and override per resource. `source`/`external_uid`
	// let an imported calendar own its blackouts and re-sync them idempotently.
	$sql .= "CREATE TABLE " . sp_book_table( 'rules' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		resource_id int(11) NOT NULL DEFAULT 0,
		service_id int(11) NOT NULL DEFAULT 0,
		rule_type varchar(20) NOT NULL DEFAULT 'hours',
		weekday tinyint(4) DEFAULT NULL,
		start_time time DEFAULT NULL,
		end_time time DEFAULT NULL,
		start_date date DEFAULT NULL,
		end_date date DEFAULT NULL,
		all_day tinyint(1) NOT NULL DEFAULT 0,
		note varchar(255) NOT NULL DEFAULT '',
		source varchar(20) NOT NULL DEFAULT 'manual',
		external_uid varchar(190) NOT NULL DEFAULT '',
		feed_id int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY resource_rule (resource_id, rule_type),
		KEY weekday (weekday),
		KEY dates (start_date, end_date),
		KEY external_uid (external_uid)
	) $charset;";

	// ---- Recurring class schedule — the generator for session-mode services.
	// `generated_through` records how far ahead concrete sessions already exist,
	// so the roll-forward job is a cheap resume rather than a rebuild.
	$sql .= "CREATE TABLE " . sp_book_table( 'recurrences' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		service_id int(11) NOT NULL,
		resource_id int(11) NOT NULL DEFAULT 0,
		weekday tinyint(4) NOT NULL DEFAULT 0,
		start_time time NOT NULL,
		duration_min int(11) NOT NULL DEFAULT 60,
		capacity int(11) NOT NULL DEFAULT 1,
		start_date date DEFAULT NULL,
		end_date date DEFAULT NULL,
		generated_through date DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY service_id (service_id),
		KEY status (status)
	) $charset;";

	// ---- Concrete bookable instances for session/stay mode.
	// seats_taken is the CONCURRENCY POINT for shared capacity: seats are claimed
	// with an atomic conditional UPDATE (… SET seats_taken = seats_taken + n
	// WHERE seats_taken + n <= capacity) and the affected-row count is the answer.
	$sql .= "CREATE TABLE " . sp_book_table( 'sessions' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		service_id int(11) NOT NULL,
		resource_id int(11) NOT NULL DEFAULT 0,
		recurrence_id int(11) NOT NULL DEFAULT 0,
		starts_at datetime NOT NULL,
		ends_at datetime NOT NULL,
		capacity int(11) NOT NULL DEFAULT 1,
		seats_taken int(11) NOT NULL DEFAULT 0,
		waitlist_count int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'scheduled',
		note varchar(255) NOT NULL DEFAULT '',
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY instance (resource_id, service_id, starts_at),
		KEY starts_at (starts_at),
		KEY status (status)
	) $charset;";

	// ---- Bookings — the core record.
	// Customer identity is DUAL: customer_id points at cc_customers when Customer
	// Connect is installed, and the name/email/phone snapshot always carries the
	// contact details, so a guest booking on a Site-Pulse-only site is complete
	// on its own and a later account link never loses what was actually entered.
	// slot_guard is the hard double-booking guarantee — see sp_book_slot_guard().
	$sql .= "CREATE TABLE " . sp_book_table( 'bookings' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		code varchar(20) NOT NULL,
		customer_id int(11) NOT NULL DEFAULT 0,
		service_id int(11) NOT NULL,
		resource_id int(11) NOT NULL DEFAULT 0,
		session_id int(11) NOT NULL DEFAULT 0,
		starts_at datetime NOT NULL,
		ends_at datetime NOT NULL,
		slot_guard varchar(64) DEFAULT NULL,
		units int(11) NOT NULL DEFAULT 1,
		party_size int(11) NOT NULL DEFAULT 1,
		status varchar(20) NOT NULL DEFAULT 'pending',
		hold_expires_at datetime DEFAULT NULL,
		source varchar(20) NOT NULL DEFAULT 'web',
		customer_name varchar(190) NOT NULL DEFAULT '',
		customer_email varchar(190) NOT NULL DEFAULT '',
		customer_phone varchar(30) NOT NULL DEFAULT '',
		address varchar(255) NOT NULL DEFAULT '',
		city varchar(100) NOT NULL DEFAULT '',
		state varchar(50) NOT NULL DEFAULT '',
		zip varchar(20) NOT NULL DEFAULT '',
		lat decimal(10,7) DEFAULT NULL,
		lng decimal(10,7) DEFAULT NULL,
		travel_miles decimal(7,2) DEFAULT NULL,
		price_cents int(11) NOT NULL DEFAULT 0,
		deposit_cents int(11) NOT NULL DEFAULT 0,
		paid_cents int(11) NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'USD',
		pass_id int(11) NOT NULL DEFAULT 0,
		intake longtext DEFAULT NULL,
		notes text DEFAULT NULL,
		staff_notes text DEFAULT NULL,
		external_source varchar(20) NOT NULL DEFAULT '',
		external_id varchar(190) NOT NULL DEFAULT '',
		created_by int(11) NOT NULL DEFAULT 0,
		cancelled_at datetime DEFAULT NULL,
		cancelled_by int(11) NOT NULL DEFAULT 0,
		cancel_reason varchar(255) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY code (code),
		UNIQUE KEY slot_guard (slot_guard),
		KEY starts_at (starts_at),
		KEY resource_time (resource_id, starts_at),
		KEY session_id (session_id),
		KEY customer_id (customer_id),
		KEY status (status),
		KEY hold_expires_at (hold_expires_at),
		KEY external (external_source, external_id)
	) $charset;";

	// ---- Attendees on a booking (a parent booking two kids into a class;
	// a household on one stay). Also where class check-in is recorded.
	$sql .= "CREATE TABLE " . sp_book_table( 'participants' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		booking_id int(11) NOT NULL,
		customer_id int(11) NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		phone varchar(30) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'booked',
		checked_in_at datetime DEFAULT NULL,
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY booking_id (booking_id),
		KEY customer_id (customer_id)
	) $charset;";

	// ---- Passes — session packs and memberships.
	// A pack has credits and (usually) an expiry; a membership is unlimited and
	// lives or dies with its Stripe subscription, whose status the webhook keeps
	// in sync here. service_id 0 = redeemable against any service.
	// ---- ZIP centroids, resolved once and kept forever.
	//
	// Scheduling works on ZIP centres, not street addresses: a business serves a handful of ZIPs,
	// so this table saturates within days and then never grows. Each ZIP is geocoded ONE time, on
	// first use, and reused for every customer in it from then on — which is why the whole travel
	// feature costs a few lookups in its first week and nothing afterwards.
	//
	// ZIP is also far less to ask of a stranger on a booking form than a street address, and the
	// street address is still collected for the appointment itself — it just isn't what schedules it.
	$sql .= "CREATE TABLE " . sp_book_table( 'zips' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		zip varchar(12) NOT NULL DEFAULT '',
		lat decimal(10,7) DEFAULT NULL,
		lng decimal(10,7) DEFAULT NULL,
		resolved tinyint(1) NOT NULL DEFAULT 0,
		attempts int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY zip (zip)
	) $charset;";

	// ---- Travel-time cache: minutes between two rounded coordinate pairs.
	//
	// Keys are lat/lng rounded to 3 decimals (about 110m), so a street shares one entry and a
	// mobile business working a single metro warms this cache within weeks. Without it, every
	// customer browsing a two-week calendar would bill a matrix call per gap.
	//
	// `source` records where a row came from: 'api' is a real Routes lookup, 'estimate' is the
	// conservative straight-line fallback used when the API is unavailable. Keeping them apart
	// means an estimate can be refreshed later without discarding real answers.
	$sql .= "CREATE TABLE " . sp_book_table( 'travel' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		origin_key varchar(24) NOT NULL DEFAULT '',
		dest_key varchar(24) NOT NULL DEFAULT '',
		minutes int(11) NOT NULL DEFAULT 0,
		meters int(11) NOT NULL DEFAULT 0,
		source varchar(20) NOT NULL DEFAULT 'api',
		fetched_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY pair (origin_key, dest_key),
		KEY fetched_at (fetched_at)
	) $charset;";

	// ---- Membership PLANS: the products the business sells, defined once.
	//
	// A plan is a template; a pass is one customer's instance of it. Splitting them stops every
	// membership being retyped from scratch, which is where the terms drift and two customers on
	// "the same" plan quietly end up with different allowances.
	//
	// Deliberately, issuing a pass COPIES the plan's terms onto the pass rather than pointing at
	// them. plan_id is kept for grouping and reporting, but the pass owns its numbers — so editing
	// a plan later cannot silently change what existing members already pay for. Rolling a change
	// out to current members is an explicit action, not a side effect of fixing a typo.
	$sql .= "CREATE TABLE " . sp_book_table( 'plans' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL DEFAULT '',
		description varchar(255) NOT NULL DEFAULT '',
		shape varchar(20) NOT NULL DEFAULT 'allowance',
		service_id int(11) NOT NULL DEFAULT 0,
		credits_total int(11) NOT NULL DEFAULT 0,
		allowance int(11) NOT NULL DEFAULT 0,
		allowance_period varchar(10) NOT NULL DEFAULT 'week',
		rollover tinyint(1) NOT NULL DEFAULT 0,
		rollover_max int(11) NOT NULL DEFAULT 0,
		overage varchar(20) NOT NULL DEFAULT 'block',
		price_cents int(11) NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'USD',
		billing_period varchar(10) NOT NULL DEFAULT 'month',
		term_days int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'active',
		display_order int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY status (status)
	) $charset;";

	// ---- Passes: packs, memberships, and recurring allowances.
	//
	// Customer identity is DUAL, as on bookings: customer_id points at cc_customers when Customer
	// Connect is installed, and the name/email snapshot always carries the contact details, so a
	// membership can be issued on a site running Site Pulse alone. Matching falls back to email.
	//
	// The ALLOWANCE columns are the third pass shape, distinct from a pack (finite credits) and
	// from unlimited: e.g. 400 dollars a month including two 30-minute appointments per week is
	// allowance = 2, allowance_period = week — while BILLING stays monthly on the subscription.
	// The two cadences are deliberately independent; conflating them is why this needs its own
	// columns rather than reusing credits_total.
	//
	// There is deliberately NO per-period counter. Usage is DERIVED by counting redemptions whose
	// booking falls inside the current window (sp_book_pass_used_in_period), so nothing has to
	// reset it on a timer — this framework's cron is traffic-triggered and cannot promise a
	// turnover minute — and a cancelled booking frees its slot automatically rather than needing
	// a credit refund.
	//
	// NB: keep prose OUT of the CREATE TABLE string. A comment in there is parsed by dbDelta as
	// part of the column list, and a double quote or a $ inside it terminates the PHP string —
	// which is exactly how this shipped broken once.
	$sql .= "CREATE TABLE " . sp_book_table( 'passes' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		plan_id int(11) NOT NULL DEFAULT 0,
		customer_id int(11) NOT NULL DEFAULT 0,
		customer_name varchar(190) NOT NULL DEFAULT '',
		customer_email varchar(190) NOT NULL DEFAULT '',
		customer_phone varchar(30) NOT NULL DEFAULT '',
		service_id int(11) NOT NULL DEFAULT 0,
		pass_type varchar(20) NOT NULL DEFAULT 'pack',
		name varchar(190) NOT NULL DEFAULT '',
		credits_total int(11) NOT NULL DEFAULT 0,
		credits_used int(11) NOT NULL DEFAULT 0,
		unlimited tinyint(1) NOT NULL DEFAULT 0,
		allowance int(11) NOT NULL DEFAULT 0,
		allowance_period varchar(10) NOT NULL DEFAULT 'month',
		allowance_anchor date DEFAULT NULL,
		rollover tinyint(1) NOT NULL DEFAULT 0,
		rollover_max int(11) NOT NULL DEFAULT 0,
		overage varchar(20) NOT NULL DEFAULT 'block',
		starts_on date DEFAULT NULL,
		expires_on date DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		price_cents int(11) NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'USD',
		provider varchar(20) NOT NULL DEFAULT '',
		provider_subscription varchar(100) NOT NULL DEFAULT '',
		current_period_end datetime DEFAULT NULL,
		source varchar(20) NOT NULL DEFAULT 'web',
		note varchar(255) NOT NULL DEFAULT '',
		created_by int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY plan_id (plan_id),
		KEY customer_id (customer_id),
		KEY customer_email (customer_email),
		KEY status (status),
		KEY provider_subscription (provider_subscription)
	) $charset;";

	// ---- Pass redemptions — the ledger behind credits_used.
	// UNIQUE on booking_id makes a double-redeem impossible, so a retried
	// request can never quietly spend a second credit.
	$sql .= "CREATE TABLE " . sp_book_table( 'pass_redemptions' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		pass_id int(11) NOT NULL,
		booking_id int(11) NOT NULL,
		credits int(11) NOT NULL DEFAULT 1,
		refunded tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY booking_id (booking_id),
		KEY pass_id (pass_id)
	) $charset;";

	// ---- Payments — one row per money movement, refunds as negative amounts.
	// UNIQUE (provider, provider_object) is the idempotency guard: a webhook
	// delivered twice (Stripe guarantees at-least-once) can't double-record.
	$sql .= "CREATE TABLE " . sp_book_table( 'payments' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		booking_id int(11) NOT NULL DEFAULT 0,
		pass_id int(11) NOT NULL DEFAULT 0,
		customer_id int(11) NOT NULL DEFAULT 0,
		provider varchar(20) NOT NULL DEFAULT 'stripe',
		kind varchar(20) NOT NULL DEFAULT 'payment',
		amount_cents int(11) NOT NULL DEFAULT 0,
		currency char(3) NOT NULL DEFAULT 'USD',
		status varchar(20) NOT NULL DEFAULT 'pending',
		provider_object varchar(100) NOT NULL DEFAULT '',
		provider_customer varchar(100) NOT NULL DEFAULT '',
		description varchar(255) NOT NULL DEFAULT '',
		meta longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY provider_object (provider, provider_object),
		KEY booking_id (booking_id),
		KEY pass_id (pass_id),
		KEY customer_id (customer_id),
		KEY status (status)
	) $charset;";

	// ---- Webhook events — the replay/idempotency log.
	// Stripe retries until it gets a 2xx, and can deliver out of order. Every
	// event id is recorded BEFORE processing; a duplicate id short-circuits.
	$sql .= "CREATE TABLE " . sp_book_table( 'webhook_events' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		provider varchar(20) NOT NULL DEFAULT 'stripe',
		event_id varchar(100) NOT NULL,
		event_type varchar(80) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'received',
		attempts int(11) NOT NULL DEFAULT 0,
		error varchar(255) NOT NULL DEFAULT '',
		payload longtext DEFAULT NULL,
		received_at datetime NOT NULL,
		processed_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY event_id (provider, event_id),
		KEY status (status)
	) $charset;";

	// ---- Outbound message queue — confirmations, reminders, follow-ups.
	// A QUEUE, not a send-at-the-time cron: this framework's cron is traffic-
	// triggered, so anything due is swept and sent on the next run. UNIQUE on
	// (booking, template, channel) means a re-run can never send twice.
	$sql .= "CREATE TABLE " . sp_book_table( 'notifications' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		booking_id int(11) NOT NULL DEFAULT 0,
		pass_id int(11) NOT NULL DEFAULT 0,
		template_key varchar(60) NOT NULL,
		channel varchar(20) NOT NULL DEFAULT 'email',
		recipient varchar(190) NOT NULL DEFAULT '',
		send_at datetime NOT NULL,
		sent_at datetime DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'queued',
		attempts int(11) NOT NULL DEFAULT 0,
		error varchar(255) NOT NULL DEFAULT '',
		payload longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY booking_template (booking_id, template_key, channel),
		KEY due (status, send_at)
	) $charset;";

	// ---- Imported calendars — the Smoobu-style channel block.
	// An external calendar (Airbnb, Booking.com, a tech's Google calendar) is
	// pulled in and its busy blocks become `blackout` rules owned by feed_id.
	$sql .= "CREATE TABLE " . sp_book_table( 'ical_feeds' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		resource_id int(11) NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		url text NOT NULL,
		direction varchar(20) NOT NULL DEFAULT 'import',
		last_sync_at datetime DEFAULT NULL,
		last_status varchar(255) NOT NULL DEFAULT '',
		event_count int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY resource_id (resource_id),
		KEY status (status)
	) $charset;";

	dbDelta( $sql );

	// dbDelta is unreliable about adding an index to a table that already exists,
	// and a MISSING slot_guard index means silent double-bookings — the one
	// failure this module must never have. Verify it landed, and add it if not.
	$bookings  = sp_book_table( 'bookings' );
	$has_guard = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM information_schema.STATISTICS
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = 'slot_guard'",
		$bookings
	) );
	if ( ! $has_guard ) {
		$wpdb->query( "ALTER TABLE $bookings ADD UNIQUE KEY slot_guard (slot_guard)" );
	}

	// Unresolved ZIP rows written before 1.8 counted API failures against the retry budget, so a
	// spell of a missing or misconfigured key permanently blacklisted real ZIP codes. Those rows
	// are worthless either way — a genuinely bad ZIP simply gets asked about again — so clear them
	// once rather than leaving people with ZIPs that can never resolve.
	$wpdb->query( "DELETE FROM " . sp_book_table( 'zips' ) . " WHERE resolved = 0" );

	update_option( 'site_pulse_booking_db_version', SITE_PULSE_BOOKING_DB_VERSION );
}
