<?php
/* Battle Plan Web Design - Site Pulse Shift Scheduling
   The CCSO shift / overtime scheduling app (includes-schedules.php, pages/page-schedules-*.php,
   js/script-schedules.js, style-schedules.css) hosted inside Site Pulse as one panel, driven by a
   "Shift Scheduling" sidebar group. Same data and logic as the standalone /schedules-* pages.

   Module OFF by default. Access is by Site Pulse capability (module `schedules`):
     use_shift_scheduling    — my schedule, claim OT, PDO, coverage, duty (the member view)
     manage_shift_schedules  — supervisor view: members, approvals, duty assignments, OT/activity logs
     manage_shift_settings   — shift / agency settings, disciplines, positions, titles
   No existing tier is given these; the three "Shift …" tiers created here hold them, and only people the
   app's Members screen adds land on those tiers — so until someone is added, only God sees it.

   How the app's own machinery maps onto Site Pulse (the app keeps its WP roles as its source of truth):
   - Its AJAX checks WP caps (manage_schedules / admin_schedules); those are granted from the Site Pulse
     caps above via `user_has_cap`, so a tier can carry them without a WP role.
   - Viewing-as: the app reads get_current_user_id(), so while a god impersonates, the hosted page, its
     localized data and every schedules_* AJAX run AS the impersonated person (see "Act as the effective
     user") — the view and the actions are exactly theirs.
   - Roster: adding / changing / deactivating / deleting a member in the app keeps a matching Site Pulse
     profile (Shift Member / Supervisor / Admin tier) in step — one roster, one login.
   - Notifications: the app's notifications go to the Site Pulse bell; clicking one opens the right view.
   - Activity: the app keeps its own (scheduling-specific) Activity Log; Site Pulse's generic auto-log
     skips schedules_* actions so nothing is recorded twice.

   Styling: style-schedules.css isn't loaded as-is (its :root block rewrites framework variables and
   several rules are unscoped — label, input[type=checkbox], .form-group, #main-content…). A copy wrapped
   in `:is(#sp-panel-schedules, #sp-schedules-float) { … }` (native CSS nesting) is generated on demand,
   so it can only style the panel; style-site-pulse.css ("Shift Scheduling") maps its variables onto the
   Site Pulse tokens.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Load, visibility, capabilities
# Act as the effective user (impersonation)
# Roster → Site Pulse users
# Notifications → Site Pulse bell
# Assets (app JS + scoped CSS)
# Panel markup + sidebar views
# Migrations (Shift tiers + roster backfill)
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Load, visibility, capabilities
--------------------------------------------------------------*/

// Module state, read once per request (the cap filter below runs on every permission check).
function sp_schedules_module_on(): bool {
	static $on = null;
	if ( $on === null ) $on = function_exists( 'site_pulse_module_on' ) && site_pulse_module_on( 'schedules' );
	return $on;
}

// The module's code isn't loaded at all until the Site Pulse module is switched on (Settings → Modules).
// On a site that already runs the standalone app (the `schedules` option, e.g. CCSO) it's loaded already.
add_action( 'after_setup_theme', 'sp_schedules_maybe_load', 5 );
function sp_schedules_maybe_load(): void {
	if ( defined( 'SCHEDULES_DB_VERSION' ) || ! sp_schedules_module_on() ) return;
	require_once get_template_directory() . '/includes/includes-schedules.php';
}

/** The three module caps; a user with any of them can open Shift Scheduling. */
function sp_schedules_caps(): array {
	return [ 'use_shift_scheduling', 'manage_shift_schedules', 'manage_shift_settings' ];
}

/**
 * Site Pulse cap check for a user, cached per request (used inside the WP cap filter). An INACTIVE profile
 * (deactivated in the app) holds nothing — its tier's caps still exist on paper, so check status first.
 * Pure DB reads all the way down (no WP cap checks), so it's safe to call from user_has_cap.
 */
function sp_schedules_user_can( int $uid, string $cap ): bool {
	static $cache = [];
	if ( ! $uid || ! function_exists( 'site_pulse_user_can' ) ) return false;
	if ( ! isset( $cache[ $uid ][ $cap ] ) ) {
		$p = site_pulse_get_user_profile( $uid );
		$cache[ $uid ][ $cap ] = $p && ( $p['status'] ?? 'active' ) === 'active' && site_pulse_user_can( $uid, $cap );
	}
	return $cache[ $uid ][ $cap ];
}

/** Can the effective user open Shift Scheduling, and as which view? '' | 'member' | 'supervisor'. */
function sp_schedules_view_for( int $uid = 0 ): string {
	if ( ! defined( 'SCHEDULES_DB_VERSION' ) || ! sp_schedules_module_on() ) return '';
	if ( ! $uid ) $uid = site_pulse_effective_user_id();
	if ( sp_schedules_user_can( $uid, 'manage_shift_schedules' ) || sp_schedules_user_can( $uid, 'manage_shift_settings' ) ) return 'supervisor';
	return sp_schedules_user_can( $uid, 'use_shift_scheduling' ) ? 'member' : '';
}

function sp_schedules_visible(): bool {
	return sp_schedules_view_for() !== '';
}

// Bridge: the app gates its AJAX on WP caps. A Site Pulse tier holding the scheduling caps grants them —
// supervise → manage_schedules, settings → admin_schedules (+ manage_schedules, as the app's admin role
// has both). Only while the module is on; the app's own WP roles keep working alongside.
add_filter( 'user_has_cap', function ( $allcaps, $caps, $args, $user ) {
	if ( ! ( $user instanceof WP_User ) || ! sp_schedules_module_on() ) return $allcaps;
	if ( ! array_intersect( (array) $caps, [ 'manage_schedules', 'admin_schedules' ] ) ) return $allcaps;   // only resolve what's asked
	if ( sp_schedules_user_can( (int) $user->ID, 'manage_shift_settings' ) ) {
		$allcaps['manage_schedules'] = true;
		$allcaps['admin_schedules']  = true;
	} elseif ( sp_schedules_user_can( (int) $user->ID, 'manage_shift_schedules' ) ) {
		$allcaps['manage_schedules'] = true;
	}
	return $allcaps;
}, 10, 4 );

// Site Pulse's generic write auto-log skips the app's actions — the app's own Activity Log already
// records them with proper descriptions, so they'd otherwise be logged twice.
add_filter( 'site_pulse_activity_prefixes', function ( $prefixes ) {
	return sp_schedules_module_on() ? array_values( array_diff( (array) $prefixes, [ 'schedules_' ] ) ) : $prefixes;
} );


/*--------------------------------------------------------------
# Act as the effective user (impersonation)
--------------------------------------------------------------*/

/**
 * Run $fn as the effective (impersonated) user, then restore the real one. The app reads
 * get_current_user_id() / current_user_can() throughout, so this is what makes "view as" show — and do —
 * exactly what that person would. No-op when not impersonating.
 */
function sp_schedules_as_effective( callable $fn ) {
	if ( ! function_exists( 'site_pulse_is_impersonating' ) || ! site_pulse_is_impersonating() ) return $fn();
	$real = get_current_user_id();
	wp_set_current_user( site_pulse_effective_user_id() );
	try {
		return $fn();
	} finally {
		wp_set_current_user( $real );
	}
}

// Every schedules_* AJAX request from the hosted app: run as the effective user (so a god viewing-as acts
// as that person), and — on a Site Pulse-hosted install — require a scheduling cap. Login is the one
// public action; the standalone app (the `schedules` option on) keeps its own rules.
add_action( 'admin_init', 'sp_schedules_ajax_context', 0 );
function sp_schedules_ajax_context(): void {
	if ( ! wp_doing_ajax() || ! sp_schedules_module_on() || ! defined( 'SCHEDULES_DB_VERSION' ) ) return;
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( strpos( $action, 'schedules_' ) !== 0 || $action === 'schedules_login' ) return;

	if ( function_exists( 'site_pulse_is_impersonating' ) && site_pulse_is_impersonating() ) {
		wp_set_current_user( site_pulse_effective_user_id() );
	}
	$standalone = function_exists( 'bp_module_on' ) && bp_module_on( get_option( 'schedules' ) );
	if ( ! $standalone && sp_schedules_view_for( get_current_user_id() ) === '' ) {
		wp_send_json_error( [ 'message' => 'You do not have access to shift scheduling.' ] );
	}
}


/*--------------------------------------------------------------
# Roster → Site Pulse users
--------------------------------------------------------------*/

/** App WP role → Site Pulse tier slug. */
function sp_schedules_tier_map(): array {
	return [ 'schedules_member' => 'shift-member', 'schedules_supervisor' => 'shift-supervisor', 'schedules_admin' => 'shift-admin' ];
}

// The app changes every member's role through set_role(): create, edit, deactivate (→ subscriber) and
// reactivate. Mirror each onto their Site Pulse profile: a scheduling role links them (or moves them to
// the matching Shift tier); deactivation turns their Site Pulse login off. A profile on some OTHER tier
// (an owner who's also on the schedule, a god) is never re-tiered — only activated/deactivated.
add_action( 'set_user_role', 'sp_schedules_sync_member', 20, 3 );
function sp_schedules_sync_member( $user_id, $role, $old_roles ): void {
	if ( ! sp_schedules_module_on() || ! function_exists( 'site_pulse_get_user_profile' ) ) return;
	$user_id = (int) $user_id;
	$map     = sp_schedules_tier_map();
	$profile = site_pulse_get_user_profile( $user_id );
	global $wpdb;
	$pt = site_pulse_table( 'user_profiles' );

	if ( isset( $map[ $role ] ) ) {
		$tier = site_pulse_get_role_by_slug( $map[ $role ] );
		if ( ! $tier ) return;                                         // tiers not created yet — backfill catches up
		if ( ! $profile ) {
			site_pulse_link_existing_user( $user_id, [ 'role_id' => (int) $tier['id'] ] );
			return;
		}
		$cur_slug = ( $r = site_pulse_get_role( (int) $profile['role_id'] ) ) ? (string) $r['slug'] : '';
		$upd      = [ 'status' => 'active', 'updated_at' => current_time( 'mysql' ) ];
		if ( $cur_slug === '' || in_array( $cur_slug, $map, true ) ) $upd['role_id'] = (int) $tier['id'];
		$wpdb->update( $pt, $upd, [ 'user_id' => $user_id ] );
		// set_role() replaced every WP role, including the Site Pulse marker role — put it back.
		$u = get_userdata( $user_id );
		if ( $u && ! in_array( 'site_pulse_user', (array) $u->roles, true ) ) $u->add_role( 'site_pulse_user' );
		return;
	}

	// Deactivated in the app (role → subscriber from a scheduling role): Site Pulse login off too.
	if ( $profile && array_intersect( (array) $old_roles, array_keys( $map ) ) ) {
		$wpdb->update( $pt, [ 'status' => 'inactive', 'updated_at' => current_time( 'mysql' ) ], [ 'user_id' => $user_id ] );
	}
}

// Purged in the app (wp_delete_user): drop their Site Pulse profile with them.
add_action( 'delete_user', function ( $user_id ) {
	if ( ! sp_schedules_module_on() || ! function_exists( 'site_pulse_table' ) ) return;
	global $wpdb;
	$wpdb->delete( site_pulse_table( 'user_profiles' ), [ 'user_id' => (int) $user_id ] );
} );


/*--------------------------------------------------------------
# Notifications → Site Pulse bell
--------------------------------------------------------------*/

// The app's notifications go to the Site Pulse bell (its own bell is hidden in the panel). related_type
// carries the view to open — `schedules:{view}` — so a click lands where the Approve / Accept lives.
add_filter( 'schedules_route_notification', function ( $handled, $row ) {
	if ( $handled || ! sp_schedules_module_on() || ! function_exists( 'site_pulse_notify' ) ) return $handled;
	$type = (string) ( $row['type'] ?? '' );
	if ( strpos( $type, 'timeoff_request' ) === 0 )  $view = 'coverage';   // supervisor: PDO Requests
	elseif ( strpos( $type, 'timeoff_' ) === 0 )     $view = 'schedule';   // member: their request decided
	elseif ( strpos( $type, 'sick_' ) === 0 )        $view = 'members';
	else                                             $view = 'coverage';   // every cover_* step
	site_pulse_notify( (int) ( $row['user_id'] ?? 0 ), 'schedules_' . $type, (string) ( $row['message'] ?? '' ), (int) ( $row['related_id'] ?? 0 ), 'schedules:' . $view );
	return true;
}, 10, 2 );


/*--------------------------------------------------------------
# Assets (app JS + scoped CSS)
--------------------------------------------------------------*/

add_action( 'wp_enqueue_scripts', 'sp_schedules_enqueue', 30 );
function sp_schedules_enqueue(): void {
	global $post;
	if ( ! $post || $post->post_name !== 'site-pulse-dashboard' || ! sp_schedules_visible() ) return;
	if ( ! function_exists( 'schedules_enqueue_app_script' ) ) return;

	$css = sp_schedules_scoped_css();
	if ( $css ) wp_enqueue_style( 'sp-schedules-scoped', $css['url'], [], $css['ver'] );
	// Localized schedulesData (userId, shift, nonce, isSupervisor…) must describe the effective user.
	sp_schedules_as_effective( 'schedules_enqueue_app_script' );
}

/**
 * style-schedules.css wrapped in `:is(#sp-panel-schedules, #sp-schedules-float) { … }` (native CSS
 * nesting), written once per source mtime to uploads/site-pulse/. Nesting does the scoping: every rule
 * matches only inside the panel, and the standalone-only parts neutralize themselves (`:root` →
 * `#sp-panel-schedules :root`, `body.has-schedules-app …` → never matches). @keyframes can't be nested, so
 * they're lifted to the top. Returns [ 'url', 'ver' ] or null.
 */
function sp_schedules_scoped_css(): ?array {
	$src = get_template_directory() . '/style-schedules.css';
	if ( ! is_readable( $src ) ) return null;
	// Generator version first: bump it when THIS function's output changes, so sites regenerate even
	// though style-schedules.css itself didn't.
	$ver  = '1-' . filemtime( $src );
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'site-pulse';
	$name = "schedules-scoped-{$ver}.css";
	$file = "{$dir}/{$name}";

	if ( ! file_exists( $file ) ) {
		if ( ! wp_mkdir_p( $dir ) ) return null;
		$css       = (string) file_get_contents( $src );
		$keyframes = '';
		$css = preg_replace_callback( '/@keyframes[^{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', function ( $m ) use ( &$keyframes ) {
			$keyframes .= $m[0] . "\n";
			return '';
		}, $css );
		// #sp-schedules-float = the <body>-level holder script-schedules.js uses for toasts + the print clone.
		$out = "/* GENERATED from style-schedules.css for the Site Pulse panel — do not edit; regenerates when the source changes. */\n"
			. $keyframes . "\n:is(#sp-panel-schedules, #sp-schedules-float) {\n" . $css . "\n}\n";
		foreach ( glob( "{$dir}/schedules-scoped-*.css" ) ?: [] as $old ) @unlink( $old );
		if ( false === file_put_contents( $file, $out ) ) return null;
	}
	return [ 'url' => trailingslashit( $up['baseurl'] ) . "site-pulse/{$name}", 'ver' => $ver ];
}


/*--------------------------------------------------------------
# Panel markup + sidebar views
--------------------------------------------------------------*/

/**
 * The app rendered inside the panel, AS the effective user: the supervisor dashboard for supervisors /
 * admins, the member page for everyone else. The app's own logo / name / Sign Out / bell and its tab bars
 * are hidden by style-site-pulse.css — the sidebar group drives the views — but the "Acting as" member
 * picker and Mark Absent stay.
 */
function sp_schedules_panel_html(): string {
	$view = sp_schedules_view_for();
	if ( $view === '' ) return '';
	$tpl = get_template_directory() . '/pages/page-schedules-' . ( $view === 'supervisor' ? 'supervisor' : 'member' ) . '.php';
	if ( ! is_readable( $tpl ) ) return '';
	return (string) sp_schedules_as_effective( function () use ( $tpl ) {
		$printPage = '';
		$html      = include $tpl;   // the template builds and returns $printPage
		return is_string( $html ) ? $html : $printPage;
	} );
}

/** Sidebar children for the effective user: [ view slug in the app => label ]. Order follows the app's tabs. */
function sp_schedules_nav_views(): array {
	if ( sp_schedules_view_for() === 'supervisor' ) {
		$views = [
			'calendar' => 'Claim OT',
			'schedule' => 'Calendar',
			'pdo'      => 'Request PDO',
			'duty'     => 'Duty Assignments',
			'members'  => 'Members',
			'coverage' => 'PDO Requests',
			'claims'   => 'OT Log',
			'actlog'   => 'Activity Log',
			'settings' => 'Settings',
		];
		// The app's raw-table God Mode tab exists only for the battleplanweb login (as the effective user).
		$u = get_userdata( site_pulse_effective_user_id() );
		if ( $u && $u->user_login === 'battleplanweb' ) $views['godmode'] = 'God Mode';
		return $views;
	}
	return [
		'calendar' => 'Claim OT',
		'schedule' => 'My Schedule',
		'pdo'      => 'PDO Calendar',
		'coverage' => 'Coverage',
		'duty'     => 'Duty Assignments',
		'settings' => 'Settings',
	];
}


/*--------------------------------------------------------------
# Migrations (Shift tiers + roster backfill)
--------------------------------------------------------------*/

/**
 * Once the module is on: create the three Shift tiers (insert-only, never overwrites an edited tier), then
 * link everyone the app already knows (WP users holding a scheduling role) onto the matching tier. Each
 * tier also gets the Site Pulse basics every staff tier has — Chat, Action Items, Notes.
 */
add_action( 'init', 'sp_schedules_migrate', 30 );
function sp_schedules_migrate(): void {
	if ( get_option( 'site_pulse_schedules_tiers_v1' ) ) return;
	if ( ! sp_schedules_module_on() || ! function_exists( 'site_pulse_get_role_by_slug' ) ) return;
	global $wpdb;
	$base  = [ 'view_chat', 'view_action_items', 'view_notes' ];
	$tiers = [
		'shift-member'     => [ 'Shift Member',     10, [ 'use_shift_scheduling' ] ],
		'shift-supervisor' => [ 'Shift Supervisor', 50, [ 'use_shift_scheduling', 'manage_shift_schedules' ] ],
		'shift-admin'      => [ 'Shift Admin',      90, [ 'use_shift_scheduling', 'manage_shift_schedules', 'manage_shift_settings' ] ],
	];
	foreach ( $tiers as $slug => [ $label, $level, $caps ] ) {
		if ( site_pulse_get_role_by_slug( $slug ) ) continue;
		$wpdb->insert( site_pulse_table( 'roles' ), [
			'slug'            => $slug,
			'label'           => $label,
			'capabilities'    => wp_json_encode( array_merge( $base, $caps ) ),
			'hierarchy_level' => $level,
		] );
	}
	foreach ( sp_schedules_tier_map() as $wp_role => $slug ) {
		foreach ( get_users( [ 'role' => $wp_role, 'fields' => 'ID' ] ) as $uid ) {
			sp_schedules_sync_member( (int) $uid, $wp_role, [] );
		}
	}
	update_option( 'site_pulse_schedules_tiers_v1', '1' );
}
