<?php
/* Battle Plan Web Design - Site Pulse Notes
   Ongoing notepads — the Action Items card, minus the checklist. A note is a free-form text body you
   open and edit like notepad.txt, plus an optional log of dated entries underneath it. Personal, or
   shared with anyone (sp_msg_contacts). No "complete" — a note lives until someone deletes it.

   ONE row per note, shared through a members table — deliberately NOT the Action Items model of one
   linked copy per person. Copies are fine for a to-do (the text rarely changes); for a document two
   people type into, copies drift and silently overwrite each other. So there is one body, and every
   body save carries the `version` it was loaded at: a save against a stale version is refused and the
   editor offers "overwrite / load theirs" instead of clobbering someone's work.

   Permissions: gated by the view_notes cap (Notes module). Inside that, a note is visible ONLY to its
   members — supervisor cross-visibility does not apply; notes are shared explicitly or not at all.
     - any member   : edit title/body/labels, add log entries, add/remove photos, remove themselves
     - entry author : edit/delete their own entry (the owner can too — it's their note)
     - owner        : share/unshare people, "Delete for everyone"

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Setup (schema, cap grant, page access)
# Helpers
# AJAX — List / Get
# AJAX — Save / Members / Delete
# AJAX — Log Entries
# AJAX — Photos
# AJAX — Order & Sort
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Setup (schema, cap grant, page access)
--------------------------------------------------------------*/

// Own schema version (same idiom as Store/Booking) so a notes change doesn't re-run dbDelta across
// every core Site Pulse table.
define( 'SITE_PULSE_NOTES_DB_VERSION', '1.0' );

// Runs only while the module is on, so installs that never use Notes stay clean.
// Keep prose OUT of the CREATE TABLE strings — dbDelta parses comments as columns.
add_action( 'init', 'sp_notes_install_db' );
function sp_notes_install_db(): void {
	if ( get_option( 'site_pulse_notes_db_version' ) === SITE_PULSE_NOTES_DB_VERSION ) return;
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'notes' ) ) return;

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE " . site_pulse_table( 'notes' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		owner_id int(11) NOT NULL,
		title varchar(200) NOT NULL DEFAULT '',
		body longtext DEFAULT NULL,
		labels varchar(255) DEFAULT NULL,
		meta longtext DEFAULT NULL,
		version int(11) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		updated_by int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY owner_id (owner_id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table( 'note_members' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		note_id int(11) NOT NULL,
		user_id int(11) NOT NULL,
		display_order int(11) NOT NULL DEFAULT 0,
		added_by int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY note_user (note_id, user_id),
		KEY user_id (user_id)
	) $charset;";

	$sql .= "CREATE TABLE " . site_pulse_table( 'note_entries' ) . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		note_id int(11) NOT NULL,
		user_id int(11) NOT NULL,
		body text NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY note_id (note_id)
	) $charset;";

	dbDelta( $sql );
	update_option( 'site_pulse_notes_db_version', SITE_PULSE_NOTES_DB_VERSION );
}

// One-time: Notes is for everyone, like Chat and Action Items — grant view_notes to every existing
// role so it appears the moment the module ships. A role created later starts without it (tick to grant).
add_action( 'init', 'sp_notes_grant_cap', 27 );
function sp_notes_grant_cap(): void {
	if ( get_option( 'site_pulse_notes_cap_v1' ) ) return;
	global $wpdb;
	$t = site_pulse_table( 'roles' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) return;
	foreach ( ( $wpdb->get_results( "SELECT id, slug, capabilities FROM $t", ARRAY_A ) ?: [] ) as $r ) {
		if ( $r['slug'] === 'god' ) continue; // god resolves dynamically
		$caps = json_decode( (string) $r['capabilities'], true );
		$caps = is_array( $caps ) ? $caps : [];
		if ( in_array( 'view_notes', $caps, true ) ) continue;
		$caps[] = 'view_notes';
		$wpdb->update( $t, [ 'capabilities' => wp_json_encode( array_values( $caps ) ) ], [ 'id' => (int) $r['id'] ] );
	}
	update_option( 'site_pulse_notes_cap_v1', '1' );
}

// So the Notes screen gets the per-page Grant/Restrict bar like every other screen.
add_filter( 'site_pulse_page_access_map', function ( $map ) {
	$map['notes'] = 'view_notes';
	return $map;
} );


/*--------------------------------------------------------------
# Helpers
--------------------------------------------------------------*/

// Nonce + signed in + view_notes. Returns the effective user id (impersonation-aware) or ends the request.
function sp_notes_gate(): int {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! $me ) wp_send_json_error( [ 'message' => 'Not signed in.' ] );
	if ( ! site_pulse_user_can( $me, 'view_notes' ) ) wp_send_json_error( [ 'message' => 'You do not have access to Notes.' ] );
	return $me;
}

function sp_note_row( int $note_id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . site_pulse_table( 'notes' ) . " WHERE id = %d", $note_id ), ARRAY_A );
	return $row ?: null;
}

function sp_note_member_ids( int $note_id ): array {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT user_id FROM " . site_pulse_table( 'note_members' ) . " WHERE note_id = %d", $note_id
	) ) ?: [] );
}

// The note, if $me is a member — otherwise the request ends. Every note endpoint goes through this.
function sp_note_for_member( int $note_id, int $me ): array {
	$note = $note_id ? sp_note_row( $note_id ) : null;
	if ( ! $note ) wp_send_json_error( [ 'message' => 'That note no longer exists.', 'gone' => 1 ] );
	if ( ! in_array( $me, sp_note_member_ids( $note_id ), true ) ) wp_send_json_error( [ 'message' => 'That note isn’t shared with you.', 'gone' => 1 ] );
	return $note;
}

// People $me may share with — the same set the New Message picker offers.
function sp_notes_allowed_people( int $me, array $ids ): array {
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	if ( ! $ids || ! function_exists( 'sp_msg_contacts' ) ) return [];
	$allowed = array_map( 'intval', array_column( sp_msg_contacts( $me ), 'id' ) );
	return array_values( array_filter( array_intersect( $ids, $allowed ), fn( $u ) => $u !== $me ) );
}

// Note text is stored as typed. sanitize_textarea_field() is wrong for a notepad: it turns a bare "<"
// into a literal "&lt;", deletes anything shaped like "%20" (so pasted URLs break) and trims indentation.
// Nothing here is ever output unescaped — the client esc()s every string and SQL is prepared — so the
// only cleanup needed is valid UTF-8, no NUL bytes, one newline style, and a size cap.
function sp_notes_clean_text( $raw, int $max, bool $multiline = true ): string {
	$s = wp_check_invalid_utf8( (string) wp_unslash( $raw ) );
	$s = str_replace( [ "\0", "\r\n", "\r" ], [ '', "\n", "\n" ], $s );
	if ( ! $multiline ) $s = trim( preg_replace( '/\s+/u', ' ', $s ) );
	return mb_substr( $s, 0, $max );
}

// Labels arrive comma-separated (the shared label picker). Trim, cap each at 50, dedupe, fit the column.
function sp_notes_clean_labels( string $csv ): string {
	$seen = [];
	foreach ( explode( ',', $csv ) as $l ) {
		$l = mb_substr( trim( sanitize_text_field( $l ) ), 0, 50 );
		if ( '' !== $l && ! isset( $seen[ strtolower( $l ) ] ) ) $seen[ strtolower( $l ) ] = $l;
	}
	return mb_substr( implode( ', ', $seen ), 0, 255 );
}

function sp_notes_labels( $csv ): array {
	return array_values( array_filter( array_map( 'trim', explode( ',', (string) $csv ) ), 'strlen' ) );
}

// A stored site-local datetime → Unix timestamp, so the browser's timeAgo() is right in any timezone.
function sp_notes_ts( $mysql ): int {
	if ( ! $mysql || '0000-00-00 00:00:00' === $mysql ) return 0;
	try { return ( new DateTime( (string) $mysql, wp_timezone() ) )->getTimestamp(); } catch ( Exception $e ) { return 0; }
}

function sp_notes_name( int $uid ): string {
	$u = $uid ? get_userdata( $uid ) : null;
	return $u ? site_pulse_display_name( $u ) : 'Someone';
}

// Blank title → the body's first non-empty line, so a note is never an untitled card.
function sp_notes_title( string $title, string $body ): string {
	$title = trim( $title );
	if ( '' === $title ) {
		foreach ( preg_split( '/\R/', $body ) as $line ) { if ( '' !== trim( $line ) ) { $title = trim( $line ); break; } }
	}
	return mb_substr( '' !== $title ? $title : 'Untitled note', 0, 200 );
}

// Bump updated_at/updated_by without touching `version` — log entries and photos can't conflict with a
// body edit, so they must not force the person typing in the body to resolve a "conflict".
function sp_note_touch( int $note_id, int $me ): void {
	global $wpdb;
	$wpdb->update( site_pulse_table( 'notes' ), [ 'updated_at' => current_time( 'mysql' ), 'updated_by' => $me ], [ 'id' => $note_id ] );
}

// Tell the other members a note changed. At most ONE unread "updated" notice per person per note, so an
// hour of autosaves is one bell, not sixty.
function sp_note_notify_update( array $note, int $me ): void {
	global $wpdb;
	$nt   = site_pulse_table( 'notifications' );
	$name = sp_notes_name( $me );
	foreach ( sp_note_member_ids( (int) $note['id'] ) as $uid ) {
		if ( $uid === $me ) continue;
		$open = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $nt WHERE user_id = %d AND type = 'note_updated' AND related_type = 'note' AND related_id = %d AND is_read = 0 AND is_archived = 0 LIMIT 1",
			$uid, (int) $note['id']
		) );
		if ( $open ) continue;
		site_pulse_notify( $uid, 'note_updated', sprintf( '%s updated the note: %s', $name, $note['title'] ), (int) $note['id'], 'note' );
		if ( function_exists( 'site_pulse_push_send' ) ) site_pulse_push_send( $uid );
	}
}

function sp_note_notify_shared( array $note, int $me, array $uids ): void {
	$name = sp_notes_name( $me );
	foreach ( $uids as $uid ) {
		site_pulse_notify( (int) $uid, 'note_shared', sprintf( '%s shared a note with you: %s', $name, $note['title'] ), (int) $note['id'], 'note' );
		if ( function_exists( 'site_pulse_push_send' ) ) site_pulse_push_send( (int) $uid );
	}
}

function sp_note_add_members( int $note_id, int $me, array $uids ): void {
	global $wpdb;
	$now = current_time( 'mysql' );
	foreach ( $uids as $uid ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO " . site_pulse_table( 'note_members' ) . " (note_id, user_id, display_order, added_by, created_at) VALUES (%d, %d, 0, %d, %s)",
			$note_id, (int) $uid, $me, $now
		) );
	}
}

// Build the client payload for a set of notes in two batched queries (members + entries), not N+1.
function sp_notes_payload( array $notes, int $me ): array {
	if ( ! $notes ) return [];
	global $wpdb;
	$ids = array_map( fn( $n ) => (int) $n['id'], $notes );
	$ph  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

	$members = [];
	$order   = [];
	$mrows   = $wpdb->get_results( $wpdb->prepare(
		"SELECT note_id, user_id, display_order FROM " . site_pulse_table( 'note_members' ) . " WHERE note_id IN ($ph) ORDER BY id", ...$ids
	), ARRAY_A ) ?: [];
	foreach ( $mrows as $m ) {
		$nid = (int) $m['note_id']; $uid = (int) $m['user_id'];
		// photo: the directory picture chat uses (cached per request, so each person is looked up once).
		$members[ $nid ][] = [ 'id' => $uid, 'name' => sp_notes_name( $uid ), 'photo' => function_exists( 'site_pulse_user_photo' ) ? site_pulse_user_photo( $uid ) : '' ];
		if ( $uid === $me ) $order[ $nid ] = (int) $m['display_order'];
	}

	$entries = [];
	$erows   = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . site_pulse_table( 'note_entries' ) . " WHERE note_id IN ($ph) ORDER BY created_at DESC, id DESC", ...$ids
	), ARRAY_A ) ?: [];

	$out = [];
	foreach ( $notes as $n ) {
		$nid   = (int) $n['id'];
		$owner = (int) $n['owner_id'];
		$meta  = $n['meta'] ? json_decode( $n['meta'], true ) : [];
		$out[ $nid ] = [
			'id'          => $nid,
			'title'       => (string) $n['title'],
			'body'        => (string) $n['body'],
			'labels'      => (string) $n['labels'],
			'version'     => (int) $n['version'],
			'owner_id'    => $owner,
			'owner_name'  => sp_notes_name( $owner ),
			'mine'        => $owner === $me ? 1 : 0,
			'members'     => $members[ $nid ] ?? [],
			'order'       => $order[ $nid ] ?? 0,
			'attachments' => ( is_array( $meta ) && isset( $meta['attachments'] ) && is_array( $meta['attachments'] ) ) ? array_values( $meta['attachments'] ) : [],
			'entries'     => [],
			'created_ts'  => sp_notes_ts( $n['created_at'] ),
			'updated_ts'  => sp_notes_ts( $n['updated_at'] ),
			'updated_by'  => (int) $n['updated_by'],
			'updated_by_name' => sp_notes_name( (int) $n['updated_by'] ),
		];
	}
	foreach ( $erows as $e ) {
		$nid = (int) $e['note_id'];
		if ( ! isset( $out[ $nid ] ) ) continue;
		$uid = (int) $e['user_id'];
		$out[ $nid ]['entries'][] = [
			'id'       => (int) $e['id'],
			'user_id'  => $uid,
			'name'     => sp_notes_name( $uid ),
			'body'     => (string) $e['body'],
			'ts'       => sp_notes_ts( $e['created_at'] ),
			'edited'   => $e['updated_at'] !== $e['created_at'] ? 1 : 0,
			'can_edit' => ( $uid === $me || $out[ $nid ]['owner_id'] === $me ) ? 1 : 0,
		];
	}
	return array_values( $out );
}

function sp_note_payload( int $note_id, int $me ): array {
	$row = sp_note_row( $note_id );
	return $row ? ( sp_notes_payload( [ $row ], $me )[0] ?? [] ) : [];
}

// Remove a note and everything hanging off it, including its uploaded photos.
function sp_note_destroy( array $note ): void {
	global $wpdb;
	$nid  = (int) $note['id'];
	$meta = $note['meta'] ? json_decode( $note['meta'], true ) : [];
	$up   = wp_get_upload_dir();
	foreach ( ( is_array( $meta ) ? ( $meta['attachments'] ?? [] ) : [] ) as $a ) {
		$url = (string) ( $a['url'] ?? '' );
		if ( '' !== $url && 0 === strpos( $url, $up['baseurl'] . '/site-pulse-notes/' ) ) {
			wp_delete_file( $up['basedir'] . substr( $url, strlen( $up['baseurl'] ) ) );
		}
	}
	$wpdb->delete( site_pulse_table( 'note_entries' ), [ 'note_id' => $nid ] );
	$wpdb->delete( site_pulse_table( 'note_members' ), [ 'note_id' => $nid ] );
	$wpdb->delete( site_pulse_table( 'notes' ), [ 'id' => $nid ] );
}


/*--------------------------------------------------------------
# AJAX — List / Get
--------------------------------------------------------------*/

// Every note the viewer is a member of. Filtering, search and sort are client-side — a person's notes
// are a small set, and it keeps search instant across bodies AND log entries.
add_action( 'wp_ajax_site_pulse_notes_list', 'sp_notes_ajax_list' );
function sp_notes_ajax_list(): void {
	$me = sp_notes_gate();
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT n.* FROM " . site_pulse_table( 'notes' ) . " n
		 INNER JOIN " . site_pulse_table( 'note_members' ) . " m ON m.note_id = n.id AND m.user_id = %d
		 ORDER BY n.updated_at DESC", $me
	), ARRAY_A ) ?: [];

	$notes  = sp_notes_payload( $rows, $me );
	$labels = [];
	foreach ( $notes as $n ) { foreach ( sp_notes_labels( $n['labels'] ) as $l ) $labels[ $l ] = true; }
	$labels = array_keys( $labels );
	natcasesort( $labels );

	$sort = get_user_meta( site_pulse_effective_user_id(), '_sp_notes_sort', true );   // view-as shows their order
	wp_send_json_success( [
		'notes'  => $notes,
		'labels' => array_values( $labels ),
		'sort'   => in_array( $sort, [ 'edited', 'created', 'title', 'custom' ], true ) ? $sort : 'edited',
	] );
}

add_action( 'wp_ajax_site_pulse_notes_get', 'sp_notes_ajax_get' );
function sp_notes_ajax_get(): void {
	$me   = sp_notes_gate();
	$note = sp_note_for_member( (int) ( $_POST['note_id'] ?? 0 ), $me );
	wp_send_json_success( [ 'note' => sp_note_payload( (int) $note['id'], $me ) ] );
}


/*--------------------------------------------------------------
# AJAX — Save / Members / Delete
--------------------------------------------------------------*/

// Create (note_id 0) or save the title/body/labels. A save must carry the `base_version` it was loaded
// at; if someone else saved in between, nothing is written and the current note comes back flagged
// `conflict` so the editor can ask what to do rather than overwrite them.
add_action( 'wp_ajax_site_pulse_notes_save', 'sp_notes_ajax_save' );
function sp_notes_ajax_save(): void {
	$me      = sp_notes_gate();
	$note_id = (int) ( $_POST['note_id'] ?? 0 );
	$body    = sp_notes_clean_text( $_POST['body'] ?? '', 200000 );
	$raw_t   = sp_notes_clean_text( $_POST['title'] ?? '', 200, false );
	$labels  = sp_notes_clean_labels( (string) wp_unslash( $_POST['labels'] ?? '' ) );
	$now     = current_time( 'mysql' );
	global $wpdb;
	$t = site_pulse_table( 'notes' );

	if ( ! $note_id ) {
		if ( '' === trim( $raw_t ) && '' === trim( $body ) ) wp_send_json_error( [ 'message' => 'Give the note a title or some text first.' ] );
		$wpdb->insert( $t, [
			'owner_id'   => $me,
			'title'      => sp_notes_title( $raw_t, $body ),
			'body'       => $body,
			'labels'     => $labels,
			'version'    => 1,
			'created_at' => $now,
			'updated_at' => $now,
			'updated_by' => $me,
		] );
		$note_id = (int) $wpdb->insert_id;
		if ( ! $note_id ) wp_send_json_error( [ 'message' => 'Could not create the note.' ] );

		// New notes land at the TOP of a custom-ordered list.
		$top = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MIN(display_order) FROM " . site_pulse_table( 'note_members' ) . " WHERE user_id = %d", $me ) );
		$wpdb->insert( site_pulse_table( 'note_members' ), [ 'note_id' => $note_id, 'user_id' => $me, 'display_order' => $top - 1, 'added_by' => $me, 'created_at' => $now ] );

		$people = sp_notes_allowed_people( $me, (array) json_decode( (string) wp_unslash( $_POST['members'] ?? '[]' ), true ) );
		if ( $people ) {
			sp_note_add_members( $note_id, $me, $people );
			sp_note_notify_shared( sp_note_row( $note_id ), $me, $people );
		}
		site_pulse_log( 'note_created', 'Note created', [ 'note_id' => $note_id ] );
		wp_send_json_success( [ 'note' => sp_note_payload( $note_id, $me ) ] );
	}

	$note = sp_note_for_member( $note_id, $me );
	$base = (int) ( $_POST['base_version'] ?? 0 );
	$done = $wpdb->query( $wpdb->prepare(
		"UPDATE $t SET title = %s, body = %s, labels = %s, version = version + 1, updated_at = %s, updated_by = %d WHERE id = %d AND version = %d",
		sp_notes_title( $raw_t, $body ), $body, $labels, $now, $me, $note_id, $base
	) );
	if ( ! $done ) {
		wp_send_json_error( [ 'conflict' => 1, 'message' => 'Someone else changed this note.', 'note' => sp_note_payload( $note_id, $me ) ] );
	}
	sp_note_notify_update( sp_note_row( $note_id ) ?: $note, $me );
	wp_send_json_success( [ 'note' => sp_note_payload( $note_id, $me ) ] );
}

// Owner sets who the note is shared with — `members` is the FULL desired list besides the owner.
// New people are notified; anyone dropped from the list loses the note.
add_action( 'wp_ajax_site_pulse_notes_set_members', 'sp_notes_ajax_set_members' );
function sp_notes_ajax_set_members(): void {
	$me   = sp_notes_gate();
	$note = sp_note_for_member( (int) ( $_POST['note_id'] ?? 0 ), $me );
	if ( (int) $note['owner_id'] !== $me ) wp_send_json_error( [ 'message' => 'Only the note’s owner can change who it’s shared with.' ] );

	global $wpdb;
	$nid     = (int) $note['id'];
	$want    = sp_notes_allowed_people( $me, (array) json_decode( (string) wp_unslash( $_POST['members'] ?? '[]' ), true ) );
	$current = array_values( array_filter( sp_note_member_ids( $nid ), fn( $u ) => $u !== $me ) );
	$add     = array_values( array_diff( $want, $current ) );
	$drop    = array_values( array_diff( $current, $want ) );

	if ( $add ) {
		sp_note_add_members( $nid, $me, $add );
		sp_note_notify_shared( $note, $me, $add );
	}
	foreach ( $drop as $uid ) {
		$wpdb->delete( site_pulse_table( 'note_members' ), [ 'note_id' => $nid, 'user_id' => $uid ] );
	}
	wp_send_json_success( [ 'note' => sp_note_payload( $nid, $me ) ] );
}

// Delete, mirroring Action Items: on a shared note the owner picks "Delete for everyone" (scope=all) or
// "Just remove me" (scope=me — ownership passes to the longest-standing other member). Anyone else can
// only remove it from their own list.
add_action( 'wp_ajax_site_pulse_notes_delete', 'sp_notes_ajax_delete' );
function sp_notes_ajax_delete(): void {
	$me    = sp_notes_gate();
	$note  = sp_note_for_member( (int) ( $_POST['note_id'] ?? 0 ), $me );
	$nid   = (int) $note['id'];
	$owner = (int) $note['owner_id'] === $me;
	$scope = ( (string) ( $_POST['scope'] ?? '' ) === 'me' ) ? 'me' : 'all';
	global $wpdb;
	$mt = site_pulse_table( 'note_members' );

	$others = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT user_id FROM $mt WHERE note_id = %d AND user_id <> %d ORDER BY id", $nid, $me
	) ) ?: [] );

	if ( ! $others || ( $owner && 'all' === $scope ) ) {
		sp_note_destroy( $note );
		if ( $others ) {
			$name = sp_notes_name( $me );
			foreach ( $others as $uid ) site_pulse_notify( $uid, 'note_deleted', sprintf( '%s deleted the note: %s', $name, $note['title'] ), $nid, 'note' );
		}
		site_pulse_log( 'note_deleted', 'Note deleted', [ 'note_id' => $nid ] );
		wp_send_json_success( [ 'removed' => 'all' ] );
	}

	// "Just remove me" (always the only option for a non-owner).
	$wpdb->delete( $mt, [ 'note_id' => $nid, 'user_id' => $me ] );
	if ( $owner ) {
		$wpdb->update( site_pulse_table( 'notes' ), [ 'owner_id' => $others[0] ], [ 'id' => $nid ] );
	}
	wp_send_json_success( [ 'removed' => 'me' ] );
}


/*--------------------------------------------------------------
# AJAX — Log Entries
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_notes_add_entry', 'sp_notes_ajax_add_entry' );
function sp_notes_ajax_add_entry(): void {
	$me   = sp_notes_gate();
	$note = sp_note_for_member( (int) ( $_POST['note_id'] ?? 0 ), $me );
	$body = trim( sp_notes_clean_text( $_POST['body'] ?? '', 20000 ) );
	if ( '' === $body ) wp_send_json_error( [ 'message' => 'Type something to add.' ] );

	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert( site_pulse_table( 'note_entries' ), [ 'note_id' => (int) $note['id'], 'user_id' => $me, 'body' => $body, 'created_at' => $now, 'updated_at' => $now ] );
	sp_note_touch( (int) $note['id'], $me );
	sp_note_notify_update( $note, $me );
	wp_send_json_success( [ 'note' => sp_note_payload( (int) $note['id'], $me ) ] );
}

// Shared by edit + delete: the entry, if $me wrote it or owns the note. Returns [entry, note].
function sp_note_entry_for_editor( int $entry_id, int $me ): array {
	global $wpdb;
	$entry = $entry_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . site_pulse_table( 'note_entries' ) . " WHERE id = %d", $entry_id ), ARRAY_A ) : null;
	if ( ! $entry ) wp_send_json_error( [ 'message' => 'That entry no longer exists.' ] );
	$note = sp_note_for_member( (int) $entry['note_id'], $me );
	if ( (int) $entry['user_id'] !== $me && (int) $note['owner_id'] !== $me ) {
		wp_send_json_error( [ 'message' => 'Only the person who wrote an entry (or the note’s owner) can change it.' ] );
	}
	return [ $entry, $note ];
}

add_action( 'wp_ajax_site_pulse_notes_update_entry', 'sp_notes_ajax_update_entry' );
function sp_notes_ajax_update_entry(): void {
	$me = sp_notes_gate();
	[ $entry, $note ] = sp_note_entry_for_editor( (int) ( $_POST['entry_id'] ?? 0 ), $me );
	$body = trim( sp_notes_clean_text( $_POST['body'] ?? '', 20000 ) );
	if ( '' === $body ) wp_send_json_error( [ 'message' => 'An entry can’t be empty — delete it instead.' ] );

	global $wpdb;
	$wpdb->update( site_pulse_table( 'note_entries' ), [ 'body' => $body, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => (int) $entry['id'] ] );
	sp_note_touch( (int) $note['id'], $me );
	wp_send_json_success( [ 'note' => sp_note_payload( (int) $note['id'], $me ) ] );
}

add_action( 'wp_ajax_site_pulse_notes_delete_entry', 'sp_notes_ajax_delete_entry' );
function sp_notes_ajax_delete_entry(): void {
	$me = sp_notes_gate();
	[ $entry, $note ] = sp_note_entry_for_editor( (int) ( $_POST['entry_id'] ?? 0 ), $me );
	global $wpdb;
	$wpdb->delete( site_pulse_table( 'note_entries' ), [ 'id' => (int) $entry['id'] ] );
	sp_note_touch( (int) $note['id'], $me );
	wp_send_json_success( [ 'note' => sp_note_payload( (int) $note['id'], $me ) ] );
}


/*--------------------------------------------------------------
# AJAX — Photos
--------------------------------------------------------------*/

// Same rules as action-item photos: images only (extension + real MIME sniff), 15 MB, randomized name,
// stored in uploads/site-pulse-notes/ and listed in the note's meta.attachments.
add_action( 'wp_ajax_site_pulse_notes_upload', 'sp_notes_ajax_upload' );
function sp_notes_ajax_upload(): void {
	$me   = sp_notes_gate();
	$note = sp_note_for_member( (int) ( $_POST['note_id'] ?? 0 ), $me );
	if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ?? '' ) ) wp_send_json_error( [ 'message' => 'No file received.' ] );

	$file = $_FILES['file'];
	if ( (int) $file['size'] > 15 * 1024 * 1024 ) wp_send_json_error( [ 'message' => 'Image is too large (max 15 MB).' ] );
	$name = (string) $file['name'];
	if ( ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif' ], true ) ) wp_send_json_error( [ 'message' => 'Please choose an image.' ] );
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $name );
	if ( empty( $check['ext'] ) || empty( $check['type'] ) || strpos( (string) $check['type'], 'image/' ) !== 0 ) {
		wp_send_json_error( [ 'message' => 'That file isn’t an image.' ] );
	}

	$redir = function ( $dirs ) {
		$dirs['subdir'] = '/site-pulse-notes' . $dirs['subdir'];
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	};
	add_filter( 'upload_dir', $redir );
	$file['name'] = 'spn-' . wp_generate_password( 16, false, false ) . '.' . $check['ext'];
	$moved = wp_handle_upload( $file, [ 'test_form' => false ] );
	remove_filter( 'upload_dir', $redir );
	if ( empty( $moved['url'] ) || ! empty( $moved['error'] ) ) wp_send_json_error( [ 'message' => $moved['error'] ?? 'Upload failed.' ] );

	$att  = [ 'url' => esc_url_raw( $moved['url'] ), 'name' => sanitize_text_field( $name ), 'mime' => sanitize_text_field( $moved['type'] ) ];
	$meta = $note['meta'] ? json_decode( $note['meta'], true ) : [];
	if ( ! is_array( $meta ) ) $meta = [];
	$meta['attachments']   = ( isset( $meta['attachments'] ) && is_array( $meta['attachments'] ) ) ? $meta['attachments'] : [];
	$meta['attachments'][] = $att;

	global $wpdb;
	$wpdb->update( site_pulse_table( 'notes' ), [ 'meta' => wp_json_encode( $meta ) ], [ 'id' => (int) $note['id'] ] );
	sp_note_touch( (int) $note['id'], $me );
	sp_note_notify_update( $note, $me );
	wp_send_json_success( [ 'attachment' => $att, 'note' => sp_note_payload( (int) $note['id'], $me ) ] );
}

add_action( 'wp_ajax_site_pulse_notes_remove_attachment', 'sp_notes_ajax_remove_attachment' );
function sp_notes_ajax_remove_attachment(): void {
	$me   = sp_notes_gate();
	$note = sp_note_for_member( (int) ( $_POST['note_id'] ?? 0 ), $me );
	$url  = esc_url_raw( (string) wp_unslash( $_POST['url'] ?? '' ) );
	if ( '' === $url ) wp_send_json_error( [ 'message' => 'Invalid request.' ] );

	$meta = $note['meta'] ? json_decode( $note['meta'], true ) : [];
	if ( ! is_array( $meta ) ) $meta = [];
	$atts = ( isset( $meta['attachments'] ) && is_array( $meta['attachments'] ) ) ? $meta['attachments'] : [];
	$meta['attachments'] = array_values( array_filter( $atts, fn( $a ) => (string) ( $a['url'] ?? '' ) !== $url ) );

	global $wpdb;
	$wpdb->update( site_pulse_table( 'notes' ), [ 'meta' => wp_json_encode( $meta ) ], [ 'id' => (int) $note['id'] ] );
	$up = wp_get_upload_dir();
	if ( 0 === strpos( $url, $up['baseurl'] . '/site-pulse-notes/' ) ) wp_delete_file( $up['basedir'] . substr( $url, strlen( $up['baseurl'] ) ) );
	sp_note_touch( (int) $note['id'], $me );
	wp_send_json_success( [ 'note' => sp_note_payload( (int) $note['id'], $me ) ] );
}


/*--------------------------------------------------------------
# AJAX — Order & Sort
--------------------------------------------------------------*/

// Custom (drag) order is PER PERSON — it lives on the member row, so rearranging a shared note on your
// list doesn't move it on anyone else's.
add_action( 'wp_ajax_site_pulse_notes_reorder', 'sp_notes_ajax_reorder' );
function sp_notes_ajax_reorder(): void {
	$me    = sp_notes_gate();
	$order = (array) ( $_POST['order'] ?? [] );
	global $wpdb;
	foreach ( array_values( $order ) as $pos => $nid ) {
		$wpdb->update( site_pulse_table( 'note_members' ), [ 'display_order' => (int) $pos ], [ 'note_id' => (int) $nid, 'user_id' => $me ] );
	}
	wp_send_json_success();
}

// Remembered sort choice — on the REAL user, like the Action Items sort.
add_action( 'wp_ajax_site_pulse_notes_set_sort', 'sp_notes_ajax_set_sort' );
function sp_notes_ajax_set_sort(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid  = get_current_user_id();
	if ( ! $uid ) wp_send_json_error();
	$sort = in_array( $_POST['sort'] ?? '', [ 'edited', 'created', 'title', 'custom' ], true ) ? $_POST['sort'] : 'edited';
	if ( ! site_pulse_is_impersonating() ) update_user_meta( $uid, '_sp_notes_sort', $sort );   // view-as: on-screen only
	wp_send_json_success( [ 'sort' => $sort ] );
}
