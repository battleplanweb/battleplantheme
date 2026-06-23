<?php
/* Battle Plan Web Design — GBP Hub: Client Registry (DB-backed)

   The hub's list of client sites (secret + GBP location(s) + metadata) used to live ONLY in the flat
   _wpeprivate/bpgbp-sites.json file, hand-edited per site. At 120+ sites that's the bottleneck. This
   moves the registry into a DB table with a wp-admin screen (Tools → GBP Clients): add a client, pick
   its location, auto-generate the secret — no file editing.

   It plugs in through the EXISTING `bpgbp_sites` filter that BPGBP_Hub::get_sites() already applies, so
   none of the auth / location-resolution core changes. The JSON file still works: it's the one-time
   import source and a fallback. DB entries win over file entries on a key collision.

   Hub-only: everything here is gated on BPGBP_REFRESH_TOKEN (the constant that makes a site the hub).

   PHASE 2 (planned): a /pair endpoint + shared bootstrap key so a client receives its secret
   automatically (stored as an option, no wp-config edit), bulk-push provisioning, client self-connect,
   and auto-matching a GBP location to a site by its websiteUri.
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;

const BPGBP_REGISTRY_DB_VERSION = '1.0';

/* The shared agency bootstrap key — the trust anchor for auto-pairing. It ships with the theme so every
 * client site has it automatically (no per-site config). It is intentionally LOW-POWER: on its own it can
 * only put a site into the hub's "pending" queue, which YOU approve; it can never read reviews or activate
 * a secret by itself. Override in wp-config to rotate. BPGBP_PAIR_HUB is the hub a client pairs to. */
if ( ! defined( 'BPGBP_BOOTSTRAP_KEY' ) ) {
	define( 'BPGBP_BOOTSTRAP_KEY', 'bpgbp-bootstrap-7f3a9c2e8d145b6079e1a4c3f5028b9d-6e7a1c4f8b2d5e9a0c3f6b1d4e7a2c5f' );
}
if ( ! defined( 'BPGBP_PAIR_HUB' ) ) {
	define( 'BPGBP_PAIR_HUB', 'https://bp-webdev.com' );
}

function bpgbp_registry_active(): bool {
	return defined( 'BPGBP_REFRESH_TOKEN' ) && BPGBP_REFRESH_TOKEN;
}

function bpgbp_registry_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'bpgbp_sites';
}

/** The flat-file path BPGBP_Hub uses (mirrors its private default_sites_path), for the one-time import. */
function bpgbp_registry_file_path(): string {
	if ( defined( 'BPGBP_SITES_FILE' ) ) return (string) BPGBP_SITES_FILE;
	$wpe = ABSPATH . '_wpeprivate';
	if ( is_dir( $wpe ) ) return $wpe . '/bpgbp-sites.json';
	return WP_CONTENT_DIR . '/bpgbp-sites.json';
}


/*--------------------------------------------------------------
# Schema + one-time import
--------------------------------------------------------------*/

add_action( 'admin_init', 'bpgbp_registry_install' );
function bpgbp_registry_install(): void {
	if ( ! bpgbp_registry_active() ) return;
	if ( get_option( 'bpgbp_registry_db_version' ) === BPGBP_REGISTRY_DB_VERSION ) return;

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$table   = bpgbp_registry_table();

	dbDelta( "CREATE TABLE $table (
		id int(11) NOT NULL AUTO_INCREMENT,
		site_key varchar(100) NOT NULL,
		label varchar(190) DEFAULT NULL,
		site_url varchar(190) DEFAULT NULL,
		secret varchar(128) NOT NULL,
		agency tinyint(1) NOT NULL DEFAULT 0,
		locations text DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY site_key (site_key)
	) $charset;" );

	update_option( 'bpgbp_registry_db_version', BPGBP_REGISTRY_DB_VERSION );

	bpgbp_registry_import_file();
}

/** Import the flat JSON file into the table once (only if the table is still empty). */
function bpgbp_registry_import_file(): void {
	global $wpdb;
	$table = bpgbp_registry_table();
	if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) > 0 ) return;

	$path = bpgbp_registry_file_path();
	if ( ! $path || ! is_readable( $path ) ) return;
	$decoded = json_decode( (string) file_get_contents( $path ), true );
	if ( ! is_array( $decoded ) ) return;

	$now = current_time( 'mysql' );
	foreach ( $decoded as $key => $entry ) {
		if ( ! is_array( $entry ) ) continue;
		$wpdb->insert( $table, [
			'site_key'   => sanitize_text_field( (string) $key ),
			'label'      => sanitize_text_field( (string) ( $entry['label'] ?? '' ) ) ?: null,
			'site_url'   => esc_url_raw( (string) ( $entry['site_url'] ?? '' ) ) ?: null,
			'secret'     => (string) ( $entry['secret'] ?? '' ),
			'agency'     => ! empty( $entry['agency'] ) ? 1 : 0,
			'locations'  => wp_json_encode( bpgbp_registry_locations_from_entry( $entry ) ),
			'status'     => 'active',
			'created_at' => $now,
			'updated_at' => $now,
		] );
	}
}

/** Normalize either a `locations` array or a single `location` string into [{id,label,brand}, …]. */
function bpgbp_registry_locations_from_entry( array $entry ): array {
	$out = [];
	if ( ! empty( $entry['locations'] ) && is_array( $entry['locations'] ) ) {
		foreach ( $entry['locations'] as $l ) {
			$id = is_array( $l ) ? (string) ( $l['id'] ?? '' ) : (string) $l;
			if ( '' === $id ) continue;
			$out[] = [
				'id'    => $id,
				'label' => is_array( $l ) ? (string) ( $l['label'] ?? '' ) : '',
				'brand' => is_array( $l ) ? (string) ( $l['brand'] ?? '' ) : '',
			];
		}
	} elseif ( ! empty( $entry['location'] ) ) {
		$out[] = [
			'id'    => (string) $entry['location'],
			'label' => (string) ( $entry['label'] ?? '' ),
			'brand' => (string) ( $entry['brand'] ?? '' ),
		];
	}
	return $out;
}


/*--------------------------------------------------------------
# Feed the registry into the hub via the existing bpgbp_sites filter
--------------------------------------------------------------*/

add_filter( 'bpgbp_sites', 'bpgbp_registry_merge' );
function bpgbp_registry_merge( $sites ) {
	if ( ! bpgbp_registry_active() ) return $sites;
	if ( ! is_array( $sites ) ) $sites = [];

	global $wpdb;
	$table = bpgbp_registry_table();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) return $sites;

	$rows = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'active'", ARRAY_A );
	foreach ( $rows ?: [] as $r ) {
		$key = (string) $r['site_key'];
		if ( '' === $key ) continue;
		$locs = json_decode( (string) $r['locations'], true );
		$locs = is_array( $locs ) ? $locs : [];

		$entry = [
			'secret'    => (string) $r['secret'],
			'label'     => (string) ( $r['label'] ?? '' ),
			'site_url'  => (string) ( $r['site_url'] ?? '' ),
			'agency'    => (bool) $r['agency'],
			'locations' => $locs,
		];
		if ( ! empty( $locs[0]['id'] ) ) $entry['location'] = $locs[0]['id']; // primary, for single-location consumers
		$sites[ $key ] = $entry; // DB wins over the file on a key collision
	}
	return $sites;
}


/*--------------------------------------------------------------
# Admin screen — Tools → GBP Clients
--------------------------------------------------------------*/

add_action( 'admin_menu', 'bpgbp_registry_admin_menu' );
function bpgbp_registry_admin_menu(): void {
	if ( ! bpgbp_registry_active() ) return;
	add_management_page( 'GBP Clients', 'GBP Clients', 'manage_options', 'bpgbp-clients', 'bpgbp_registry_render_page' );
}

function bpgbp_registry_locations_to_text( array $locs ): string {
	$lines = [];
	foreach ( $locs as $l ) {
		$lines[] = trim( ( $l['id'] ?? '' ) . ' | ' . ( $l['label'] ?? '' ) . ' | ' . ( $l['brand'] ?? '' ), " |" );
	}
	return implode( "\n", $lines );
}

/** Parse the "id | label | brand" textarea (one location per line) back into the array form. */
function bpgbp_registry_text_to_locations( string $text ): array {
	$out = [];
	foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) continue;
		$parts = array_map( 'trim', explode( '|', $line ) );
		if ( '' === ( $parts[0] ?? '' ) ) continue;
		$out[] = [ 'id' => $parts[0], 'label' => $parts[1] ?? '', 'brand' => $parts[2] ?? '' ];
	}
	return $out;
}

// Save / delete handler.
add_action( 'admin_post_bpgbp_save_client', 'bpgbp_registry_handle_save' );
function bpgbp_registry_handle_save(): void {
	if ( ! current_user_can( 'manage_options' ) || ! bpgbp_registry_active() ) wp_die( 'Not allowed.' );
	check_admin_referer( 'bpgbp_save_client' );

	global $wpdb;
	$table = bpgbp_registry_table();
	$now   = current_time( 'mysql' );

	$id       = (int) ( $_POST['id'] ?? 0 );
	$delete   = ! empty( $_POST['delete'] );
	$site_key = sanitize_text_field( wp_unslash( $_POST['site_key'] ?? '' ) );

	if ( $delete && $id ) {
		$wpdb->delete( $table, [ 'id' => $id ] );
		wp_safe_redirect( admin_url( 'tools.php?page=bpgbp-clients&msg=deleted' ) );
		exit;
	}

	if ( '' === $site_key ) {
		wp_safe_redirect( admin_url( 'tools.php?page=bpgbp-clients&msg=nokey' ) );
		exit;
	}

	// Secret: keep existing, generate a new one when blank or "regenerate" is checked.
	$secret = sanitize_text_field( wp_unslash( $_POST['secret'] ?? '' ) );
	if ( '' === $secret || ! empty( $_POST['regenerate'] ) ) {
		$secret = wp_generate_password( 64, false, false );
	}

	// Location: a dropdown pick (single location) wins; otherwise parse the advanced/multi textarea.
	$pick = sanitize_text_field( wp_unslash( $_POST['location_pick'] ?? '' ) );
	if ( '' !== $pick ) {
		$title = '';
		if ( class_exists( 'BPGBP_Hub' ) && method_exists( 'BPGBP_Hub', 'locations_for_match' ) ) {
			foreach ( BPGBP_Hub::locations_for_match() as $gl ) {
				if ( $gl['location'] === $pick ) { $title = $gl['title']; break; }
			}
		}
		$locations_json = wp_json_encode( [ [ 'id' => $pick, 'label' => $title, 'brand' => '' ] ] );
	} else {
		$locations_json = wp_json_encode( bpgbp_registry_text_to_locations( (string) wp_unslash( $_POST['locations'] ?? '' ) ) );
	}

	$data = [
		'site_key'   => $site_key,
		'label'      => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ) ?: null,
		'site_url'   => esc_url_raw( wp_unslash( $_POST['site_url'] ?? '' ) ) ?: null,
		'secret'     => $secret,
		'agency'     => ! empty( $_POST['agency'] ) ? 1 : 0,
		'locations'  => $locations_json,
		'status'     => ( ( $_POST['status'] ?? 'active' ) === 'inactive' ) ? 'inactive' : 'active',
		'updated_at' => $now,
	];

	if ( $id ) {
		$wpdb->update( $table, $data, [ 'id' => $id ] );
	} else {
		$data['created_at'] = $now;
		$wpdb->insert( $table, $data );
	}

	wp_safe_redirect( admin_url( 'tools.php?page=bpgbp-clients&msg=saved' ) );
	exit;
}

function bpgbp_registry_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;
	global $wpdb;
	$table = bpgbp_registry_table();

	$editing = null;
	if ( isset( $_GET['edit'] ) ) {
		$editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $_GET['edit'] ), ARRAY_A );
	}

	echo '<div class="wrap">';
	echo '<h1>GBP Clients</h1>';
	echo '<p>The hub\'s client registry. Add a site, paste its Google location, and a secret is generated automatically — no JSON file to edit. (Copy location ids from <a href="' . esc_url( admin_url( 'tools.php?page=bpgbp-locations' ) ) . '">Tools → GBP Locations</a>.)</p>';

	$msg = sanitize_key( $_GET['msg'] ?? '' );
	$notes = [ 'saved' => 'Client saved.', 'deleted' => 'Client deleted.', 'nokey' => 'A site key is required.', 'approved' => 'Client approved.', 'approved_all' => 'All pending clients approved.' ];
	if ( isset( $notes[ $msg ] ) ) {
		echo '<div class="notice notice-' . ( $msg === 'nokey' ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $notes[ $msg ] ) . '</p></div>';
	}
	if ( 'matched' === $msg ) {
		$n = (int) ( $_GET['n'] ?? 0 );
		echo '<div class="notice notice-success is-dismissible"><p>Auto-matched ' . $n . ' client' . ( 1 === $n ? '' : 's' ) . ' to a Google location.</p></div>';
	}

	// Bulk: fill any client that's missing a location, by website match (e.g. JSON-imported ones).
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 16px">';
	wp_nonce_field( 'bpgbp_automatch_locations' );
	echo '<input type="hidden" name="action" value="bpgbp_automatch_locations">';
	echo '<button class="button">Auto-match missing locations</button> <span class="description">Fills a Google location for any client without one, matched by website.</span>';
	echo '</form>';

	// ---- Pending approvals: sites that auto-registered and are waiting for you to OK them ----
	$pending = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at ASC", ARRAY_A );
	if ( $pending ) {
		echo '<div class="notice notice-warning"><p><strong>' . count( $pending ) . ' site(s) waiting to be approved.</strong> Approving activates the secret the site already generated and auto-fills its Google location from the website match below (you can change it with Edit).</p></div>';
		$match_map = bpgbp_location_match_map();
		echo '<table class="widefat striped" style="max-width:1000px;margin-bottom:10px"><thead><tr><th>Label</th><th>Site URL</th><th>Site key</th><th>Matched location</th><th></th></tr></thead><tbody>';
		foreach ( $pending as $r ) {
			$hit = bpgbp_match_for_row( $r, $match_map );
			echo '<tr>';
			echo '<td>' . esc_html( $r['label'] ?: '—' ) . '</td>';
			echo '<td>' . ( $r['site_url'] ? '<a href="' . esc_url( $r['site_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $r['site_url'] ) . '</a>' : '—' ) . '</td>';
			echo '<td><code>' . esc_html( $r['site_key'] ) . '</code></td>';
			echo '<td>' . ( $hit ? '✓ ' . esc_html( $hit['title'] ?: $hit['location'] ) : '<em>no match — set with Edit</em>' ) . '</td>';
			echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'bpgbp_approve_client' );
			echo '<input type="hidden" name="action" value="bpgbp_approve_client"><input type="hidden" name="id" value="' . (int) $r['id'] . '">';
			echo '<button class="button button-primary button-small">Approve</button></form> ';
			echo '<a class="button button-small" href="' . esc_url( admin_url( 'tools.php?page=bpgbp-clients&edit=' . (int) $r['id'] ) ) . '">Edit / set location</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:24px">';
		wp_nonce_field( 'bpgbp_approve_client' );
		echo '<input type="hidden" name="action" value="bpgbp_approve_client"><input type="hidden" name="approve_all" value="1">';
		echo '<button class="button">Approve all pending</button></form>';
	}

	// ---- Add / Edit form ----
	$e_id   = $editing ? (int) $editing['id'] : 0;
	$e_key  = $editing ? esc_attr( $editing['site_key'] ) : '';
	$e_lbl  = $editing ? esc_attr( $editing['label'] ) : '';
	$e_url  = $editing ? esc_attr( $editing['site_url'] ) : '';
	$e_sec  = $editing ? esc_attr( $editing['secret'] ) : '';
	$e_ag   = $editing && (int) $editing['agency'] ? ' checked' : ( $editing ? '' : ' checked' );
	$e_loc  = $editing ? esc_textarea( bpgbp_registry_locations_to_text( json_decode( (string) $editing['locations'], true ) ?: [] ) ) : '';
	$e_stat = $editing ? (string) $editing['status'] : 'active';

	echo '<h2>' . ( $editing ? 'Edit client' : 'Add client' ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:760px">';
	wp_nonce_field( 'bpgbp_save_client' );
	echo '<input type="hidden" name="action" value="bpgbp_save_client">';
	echo '<input type="hidden" name="id" value="' . $e_id . '">';
	echo '<table class="form-table"><tbody>';
	echo '<tr><th><label>Site key</label></th><td><input name="site_key" class="regular-text" value="' . $e_key . '" placeholder="e.g. 1callheatandair" required>' . ( $editing ? '' : '<p class="description">A short unique slug; the client uses this as its BPGBP_SITE_KEY.</p>' ) . '</td></tr>';
	echo '<tr><th><label>Label</label></th><td><input name="label" class="regular-text" value="' . $e_lbl . '" placeholder="1 Call Heat &amp; Air"></td></tr>';
	echo '<tr><th><label>Site URL</label></th><td><input name="site_url" class="regular-text" value="' . $e_url . '" placeholder="https://1callheatandair.com"></td></tr>';
	// A dropdown of the hub's actual GBP locations — pick instead of paste. (Cached hourly.)
	$gbp_locs = ( class_exists( 'BPGBP_Hub' ) && method_exists( 'BPGBP_Hub', 'locations_for_match' ) ) ? BPGBP_Hub::locations_for_match() : [];
	$pick = '<select name="location_pick" class="regular-text"><option value="">' . ( $e_loc !== '' ? '— keep current —' : '— select a Google location —' ) . '</option>';
	foreach ( $gbp_locs as $gl ) {
		$lbl = ( $gl['title'] !== '' ? $gl['title'] : $gl['location'] );
		if ( ! empty( $gl['website'] ) ) $lbl .= '  (' . preg_replace( '#^https?://#', '', rtrim( $gl['website'], '/' ) ) . ')';
		$pick .= '<option value="' . esc_attr( $gl['location'] ) . '">' . esc_html( $lbl ) . '</option>';
	}
	$pick .= '</select>';
	echo '<tr><th><label>Google location</label></th><td>';
	echo $pick . ( $gbp_locs ? '' : ' <span class="description">(location list unavailable — paste below)</span>' );
	echo '<p class="description">Pick a location to set it (overrides the box below). Single-location clients only need this.</p>';
	echo '<textarea name="locations" rows="2" class="large-text code" placeholder="accounts/123/locations/456 | Roanoke | Babe\'s">' . $e_loc . '</textarea>';
	echo '<p class="description">Advanced / multi-location: one per line — <code>location id | label | brand</code>.</p>';
	echo '</td></tr>';
	echo '<tr><th><label>Agency view</label></th><td><label><input type="checkbox" name="agency" value="1"' . $e_ag . '> Include in the agency “Client Reviews” tab on this hub</label></td></tr>';
	echo '<tr><th><label>Secret</label></th><td>';
	if ( $editing ) {
		echo '<input name="secret" class="regular-text code" value="' . $e_sec . '"> <label style="margin-left:8px"><input type="checkbox" name="regenerate" value="1"> Regenerate</label>';
		echo '<p class="description">The client stores this as BPGBP_SITE_SECRET.</p>';
	} else {
		echo '<input type="hidden" name="secret" value="">';
		echo '<p class="description">Generated automatically on save.</p>';
	}
	echo '</td></tr>';
	echo '<tr><th><label>Status</label></th><td><select name="status"><option value="active"' . selected( $e_stat, 'active', false ) . '>Active</option><option value="inactive"' . selected( $e_stat, 'inactive', false ) . '>Inactive</option></select></td></tr>';
	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . ( $editing ? 'Save client' : 'Add client' ) . '</button>';
	if ( $editing ) echo ' <a class="button" href="' . esc_url( admin_url( 'tools.php?page=bpgbp-clients' ) ) . '">Cancel</a>';
	echo '</p>';
	echo '</form>';

	// ---- List ----
	$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY label ASC, site_key ASC", ARRAY_A );
	echo '<h2 style="margin-top:30px">Clients (' . count( $rows ?: [] ) . ')</h2>';
	echo '<table class="widefat striped"><thead><tr><th>Label</th><th>Site key</th><th>Locations</th><th>Agency</th><th>Status</th><th></th></tr></thead><tbody>';
	if ( $rows ) {
		foreach ( $rows as $r ) {
			$locs  = json_decode( (string) $r['locations'], true ) ?: [];
			$ncount = count( $locs );
			echo '<tr>';
			echo '<td>' . esc_html( $r['label'] ) . '</td>';
			echo '<td><code>' . esc_html( $r['site_key'] ) . '</code></td>';
			echo '<td>' . esc_html( $ncount === 1 ? '1 location' : $ncount . ' locations' ) . '</td>';
			echo '<td>' . ( (int) $r['agency'] ? '✓' : '' ) . '</td>';
			echo '<td>' . esc_html( $r['status'] ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( admin_url( 'tools.php?page=bpgbp-clients&edit=' . (int) $r['id'] ) ) . '">Edit</a></td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="6">No clients yet.</td></tr>';
	}
	echo '</tbody></table>';
	echo '</div>';
}


/*--------------------------------------------------------------
# Pairing — hub endpoint + client auto-pair (no wp-config edit)
--------------------------------------------------------------*/

/** Hub-side: verify a request signed with the shared bootstrap key (timestamp . '.' . rawBody). */
function bpgbp_pair_verify( WP_REST_Request $request ) {
	$ts  = (string) $request->get_header( 'x-bpgbp-timestamp' );
	$sig = (string) $request->get_header( 'x-bpgbp-signature' );
	if ( '' === $ts || '' === $sig ) return new WP_Error( 'bpgbp_pair_auth', 'Missing auth.', [ 'status' => 401 ] );
	if ( abs( time() - (int) $ts ) > 300 ) return new WP_Error( 'bpgbp_pair_stale', 'Stale request.', [ 'status' => 401 ] );
	$expected = hash_hmac( 'sha256', $ts . '.' . $request->get_body(), BPGBP_BOOTSTRAP_KEY );
	if ( ! hash_equals( $expected, $sig ) ) return new WP_Error( 'bpgbp_pair_sig', 'Signature mismatch.', [ 'status' => 403 ] );
	return true;
}

add_action( 'rest_api_init', function () {
	if ( ! bpgbp_registry_active() ) return; // only the hub accepts pairing
	register_rest_route( 'bpgbp/v1', '/pair', [
		'methods'             => 'POST',
		'callback'            => 'bpgbp_pair_handle',
		'permission_callback' => 'bpgbp_pair_verify',
	] );
} );

/**
 * A client registers itself: it sends its own self-generated secret (so a secret never travels FROM the
 * hub), and the entry sits PENDING until an admin approves it in Tools → GBP Clients. Re-polls update the
 * pending row; once active, returns 'active' and the client stops. An active entry is never overwritten.
 */
function bpgbp_pair_handle( WP_REST_Request $request ) {
	global $wpdb;
	$table = bpgbp_registry_table();
	$p     = (array) $request->get_json_params();

	$site_url = esc_url_raw( (string) ( $p['site_url'] ?? '' ) );
	$label    = sanitize_text_field( (string) ( $p['label'] ?? '' ) );
	$secret   = sanitize_text_field( (string) ( $p['secret'] ?? '' ) );
	$site_key = sanitize_title( (string) ( $p['site_key'] ?? '' ) );
	if ( '' === $site_key && $site_url ) $site_key = bpgbp_key_from_url( $site_url );
	if ( '' === $site_key || '' === $secret ) return new WP_Error( 'bpgbp_pair_bad', 'Missing site_key or secret.', [ 'status' => 400 ] );

	$now = current_time( 'mysql' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE site_key = %s", $site_key ), ARRAY_A );

	if ( $row ) {
		if ( 'active' === $row['status'] ) {
			return new WP_REST_Response( [ 'status' => 'active', 'site_key' => $site_key ], 200 );
		}
		$wpdb->update( $table, [
			'site_url'   => $site_url ?: $row['site_url'],
			'label'      => $label ?: $row['label'],
			'secret'     => $secret,
			'updated_at' => $now,
		], [ 'id' => (int) $row['id'] ] );
		return new WP_REST_Response( [ 'status' => 'pending', 'site_key' => $site_key ], 200 );
	}

	$wpdb->insert( $table, [
		'site_key'   => $site_key,
		'label'      => $label ?: null,
		'site_url'   => $site_url ?: null,
		'secret'     => $secret,
		'agency'     => 1,
		'locations'  => wp_json_encode( [] ),
		'status'     => 'pending',
		'created_at' => $now,
		'updated_at' => $now,
	] );
	return new WP_REST_Response( [ 'status' => 'pending', 'site_key' => $site_key ], 200 );
}

/**
 * Client-side: a site that ISN'T the hub and hasn't been configured the old way (no wp-config secret)
 * self-generates a secret, stores it as an option, and registers with the hub. Fires on admin page loads
 * AND on an hourly wp-cron tick (driven by the site's normal traffic — no admin login needed), throttled
 * to one attempt/hour, and stops once the hub reports the pairing 'active'.
 */
add_action( 'admin_init', 'bpgbp_client_autopair' );
add_action( 'bpgbp_pair_cron', 'bpgbp_client_autopair' );

// Self-schedule the hourly pairing tick on every non-hub site (the callback self-gates, so it's a
// harmless no-op once the site is configured/active or if it's the hub).
add_action( 'init', function () {
	if ( bpgbp_registry_active() ) return;
	if ( ! wp_next_scheduled( 'bpgbp_pair_cron' ) ) {
		wp_schedule_event( time() + 120, 'hourly', 'bpgbp_pair_cron' );
	}
} );

function bpgbp_client_autopair(): void {
	if ( bpgbp_registry_active() ) return;                              // the hub never pairs with itself
	if ( defined( 'BPGBP_SITE_SECRET' ) && BPGBP_SITE_SECRET ) return;  // already hand-configured
	if ( defined( 'BPGBP_AUTOPAIR' ) && ! BPGBP_AUTOPAIR ) return;      // explicit opt-out
	if ( 'active' === get_option( 'bpgbp_pair_status' ) ) return;       // done
	if ( get_transient( 'bpgbp_pair_throttle' ) ) return;
	set_transient( 'bpgbp_pair_throttle', 1, HOUR_IN_SECONDS );

	$hub = defined( 'BPGBP_HUB_URL' ) ? BPGBP_HUB_URL : ( get_option( 'bpgbp_hub_url' ) ?: ( defined( 'BPGBP_PAIR_HUB' ) ? BPGBP_PAIR_HUB : '' ) );
	if ( '' === $hub ) return;

	$site_key = get_option( 'bpgbp_site_key' );
	if ( ! $site_key ) { $site_key = bpgbp_key_from_url( home_url() ); update_option( 'bpgbp_site_key', $site_key ); }
	$secret = get_option( 'bpgbp_site_secret' );
	if ( ! $secret ) { $secret = wp_generate_password( 64, false, false ); update_option( 'bpgbp_site_secret', $secret ); }
	if ( ! defined( 'BPGBP_HUB_URL' ) && ! get_option( 'bpgbp_hub_url' ) ) update_option( 'bpgbp_hub_url', $hub );

	$body = wp_json_encode( [ 'site_url' => home_url(), 'site_key' => $site_key, 'secret' => $secret, 'label' => get_bloginfo( 'name' ) ] );
	$ts   = (string) time();
	$sig  = hash_hmac( 'sha256', $ts . '.' . $body, BPGBP_BOOTSTRAP_KEY );

	$res = wp_remote_post( rtrim( $hub, '/' ) . '/?rest_route=/bpgbp/v1/pair', [
		'timeout' => 15,
		'headers' => [ 'Content-Type' => 'application/json', 'X-BPGBP-Timestamp' => $ts, 'X-BPGBP-Signature' => $sig ],
		'body'    => $body,
	] );
	if ( is_wp_error( $res ) ) return;
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( is_array( $data ) && isset( $data['status'] ) ) {
		update_option( 'bpgbp_pair_status', sanitize_key( $data['status'] ) );
	}
}


/*--------------------------------------------------------------
# Approve pending clients (single + bulk)
--------------------------------------------------------------*/

/** Derive a clean site key from a URL: the label before the first dot (no www, no TLD). */
function bpgbp_key_from_url( $url ): string {
	$host  = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	$host  = preg_replace( '/^www\./', '', $host );
	$label = explode( '.', $host )[0] ?? '';
	return sanitize_title( $label );
}

/** Bare host for matching: lowercased, no leading www. */
function bpgbp_match_host( $url ): string {
	$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	return preg_replace( '/^www\./', '', $host );
}

/** host => { location, title, website } from the GBP locations (first match per host wins). */
function bpgbp_location_match_map(): array {
	if ( ! class_exists( 'BPGBP_Hub' ) || ! method_exists( 'BPGBP_Hub', 'locations_for_match' ) ) return [];
	$map = [];
	foreach ( BPGBP_Hub::locations_for_match() as $loc ) {
		$h = bpgbp_match_host( $loc['website'] ?? '' );
		if ( '' !== $h && empty( $map[ $h ] ) ) $map[ $h ] = $loc;
	}
	return $map;
}

/** The GBP location auto-matched to a registry row by website host, or null. */
function bpgbp_match_for_row( array $row, array $map ): ?array {
	$h = bpgbp_match_host( $row['site_url'] ?? '' );
	return ( '' !== $h && isset( $map[ $h ] ) ) ? $map[ $h ] : null;
}

add_action( 'admin_post_bpgbp_approve_client', 'bpgbp_registry_handle_approve' );
function bpgbp_registry_handle_approve(): void {
	if ( ! current_user_can( 'manage_options' ) || ! bpgbp_registry_active() ) wp_die( 'Not allowed.' );
	check_admin_referer( 'bpgbp_approve_client' );

	global $wpdb;
	$table = bpgbp_registry_table();
	$now   = current_time( 'mysql' );
	$map   = bpgbp_location_match_map();

	// Activate one row; auto-fill its location from the websiteUri match when it has none yet.
	$activate = function ( array $row ) use ( $wpdb, $table, $now, $map ) {
		$update = [ 'status' => 'active', 'updated_at' => $now ];
		$locs   = json_decode( (string) $row['locations'], true );
		$locs   = is_array( $locs ) ? $locs : [];
		if ( empty( $locs ) ) {
			$hit = bpgbp_match_for_row( $row, $map );
			if ( $hit ) {
				$update['locations'] = wp_json_encode( [ [ 'id' => $hit['location'], 'label' => $hit['title'], 'brand' => '' ] ] );
			}
		}
		$wpdb->update( $table, $update, [ 'id' => (int) $row['id'] ] );
	};

	if ( ! empty( $_POST['approve_all'] ) ) {
		$rows = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'pending'", ARRAY_A );
		foreach ( $rows ?: [] as $r ) $activate( $r );
		wp_safe_redirect( admin_url( 'tools.php?page=bpgbp-clients&msg=approved_all' ) );
		exit;
	}

	$id = (int) ( $_POST['id'] ?? 0 );
	if ( $id ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
		if ( $row ) $activate( $row );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=bpgbp-clients&msg=approved' ) );
	exit;
}

// Fill in a Google location for every client that's missing one, matched by websiteUri → site URL.
// Useful for entries imported from the old JSON without a location (the auto-match only fires on approve).
add_action( 'admin_post_bpgbp_automatch_locations', 'bpgbp_registry_handle_automatch' );
function bpgbp_registry_handle_automatch(): void {
	if ( ! current_user_can( 'manage_options' ) || ! bpgbp_registry_active() ) wp_die( 'Not allowed.' );
	check_admin_referer( 'bpgbp_automatch_locations' );

	global $wpdb;
	$table = bpgbp_registry_table();
	$now   = current_time( 'mysql' );
	$map   = bpgbp_location_match_map();

	$rows = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
	$n    = 0;
	foreach ( $rows ?: [] as $r ) {
		$locs = json_decode( (string) $r['locations'], true );
		if ( is_array( $locs ) && ! empty( $locs ) ) continue; // already has a location
		$hit = bpgbp_match_for_row( $r, $map );
		if ( ! $hit ) continue;
		$wpdb->update( $table, [
			'locations'  => wp_json_encode( [ [ 'id' => $hit['location'], 'label' => $hit['title'], 'brand' => '' ] ] ),
			'updated_at' => $now,
		], [ 'id' => (int) $r['id'] ] );
		$n++;
	}
	wp_safe_redirect( admin_url( 'tools.php?page=bpgbp-clients&msg=matched&n=' . $n ) );
	exit;
}
