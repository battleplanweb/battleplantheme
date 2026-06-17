<?php
/**
 * Site Pulse — Messages (1:1 DMs + group threads).
 *
 * Conversation model: a `conversations` row (is_group, title) has N `conversation_participants`
 * and N `messages`. A 1:1 DM is just a 2-person conversation. Read state is per-participant via
 * `last_read_message_id`. Poll-based delivery (no push). Anyone-to-anyone; anyone can create a
 * group; the group's creator can add/remove members and any member can leave.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---- Helpers ---------------------------------------------------------------

// Active users the current user can message (everyone except themselves), with name + role·store.
function sp_msg_contacts( int $exclude_user_id ): array {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT user_id, role_id, location_id FROM " . site_pulse_table( 'user_profiles' ) . " WHERE status = 'active'", ARRAY_A ) ?: [];
	$out  = [];
	foreach ( $rows as $r ) {
		$uid = (int) $r['user_id'];
		if ( $uid === $exclude_user_id ) continue;
		$u = get_userdata( $uid );
		if ( ! $u ) continue;
		$role = site_pulse_get_role( (int) $r['role_id'] );
		$loc  = (int) $r['location_id'] ? site_pulse_get_location( (int) $r['location_id'] ) : null;
		$meta = trim( ( $role ? $role['label'] : '' ) . ( $loc ? ' · ' . $loc['name'] : '' ) );
		$out[] = [ 'id' => $uid, 'name' => $u->display_name, 'meta' => $meta ];
	}
	usort( $out, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
	return $out;
}

function sp_msg_user( int $uid ): array {
	$u = get_userdata( $uid );
	return [ 'id' => $uid, 'name' => $u ? $u->display_name : 'Unknown user' ];
}

// Participant user IDs of a conversation.
function sp_msg_participant_ids( int $conv_id ): array {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT user_id FROM " . site_pulse_table( 'conversation_participants' ) . " WHERE conversation_id = %d",
		$conv_id
	) ) ?: [] );
}

function sp_msg_is_participant( int $conv_id, int $user_id ): bool {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . site_pulse_table( 'conversation_participants' ) . " WHERE conversation_id = %d AND user_id = %d",
		$conv_id, $user_id
	) );
}

function sp_msg_add_participant( int $conv_id, int $user_id, int $last_read = 0 ): void {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO " . site_pulse_table( 'conversation_participants' ) . "
		 (conversation_id, user_id, last_read_message_id, joined_at) VALUES (%d, %d, %d, %s)",
		$conv_id, $user_id, $last_read, current_time( 'mysql' )
	) );
}

// The latest message id in a conversation (for read pointers).
function sp_msg_max_message_id( int $conv_id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT MAX(id) FROM " . site_pulse_table( 'messages' ) . " WHERE conversation_id = %d", $conv_id
	) );
}

// Find (or create) the 1:1 conversation between two users.
function sp_msg_find_or_create_dm( int $a, int $b ): int {
	global $wpdb;
	$cp = site_pulse_table( 'conversation_participants' );
	$c  = site_pulse_table( 'conversations' );
	$id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT c.id FROM $c c
		 JOIN $cp p1 ON p1.conversation_id = c.id AND p1.user_id = %d
		 JOIN $cp p2 ON p2.conversation_id = c.id AND p2.user_id = %d
		 WHERE c.is_group = 0 LIMIT 1",
		$a, $b
	) );
	if ( $id ) return $id;

	$now = current_time( 'mysql' );
	$wpdb->insert( $c, [ 'is_group' => 0, 'title' => null, 'created_by' => $a, 'created_at' => $now, 'updated_at' => $now ] );
	$id = (int) $wpdb->insert_id;
	sp_msg_add_participant( $id, $a );
	sp_msg_add_participant( $id, $b );
	return $id;
}

// The display title + participant list for a conversation, from a viewer's perspective.
function sp_msg_conversation_meta( int $conv_id, int $viewer ): array {
	global $wpdb;
	$conv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . site_pulse_table( 'conversations' ) . " WHERE id = %d", $conv_id ), ARRAY_A );
	$pids = sp_msg_participant_ids( $conv_id );
	$people = array_map( 'sp_msg_user', $pids );

	if ( $conv && (int) $conv['is_group'] ) {
		$title = $conv['title'] !== null && $conv['title'] !== ''
			? $conv['title']
			: implode( ', ', array_map( fn( $p ) => $p['name'], array_filter( $people, fn( $p ) => $p['id'] !== $viewer ) ) );
	} else {
		$other = array_values( array_filter( $people, fn( $p ) => $p['id'] !== $viewer ) );
		$title = $other ? $other[0]['name'] : 'Conversation';
	}
	return [
		'id'           => $conv_id,
		'is_group'     => $conv ? (int) $conv['is_group'] : 0,
		'created_by'   => $conv ? (int) $conv['created_by'] : 0,
		'title'        => $title,
		'participants' => $people,
	];
}

// One-time migration: fold legacy 1:1 messages (conversation_id = 0) into conversations.
function sp_msg_migrate_to_conversations(): void {
	global $wpdb;
	$mtable = site_pulse_table( 'messages' );
	$pairs  = $wpdb->get_results( "SELECT DISTINCT sender_id, recipient_id FROM $mtable WHERE conversation_id = 0", ARRAY_A ) ?: [];
	$done   = [];
	foreach ( $pairs as $p ) {
		$a = (int) $p['sender_id'];
		$b = (int) $p['recipient_id'];
		if ( ! $a || ! $b ) continue;
		$key = min( $a, $b ) . '-' . max( $a, $b );
		if ( isset( $done[ $key ] ) ) continue;
		$done[ $key ] = true;

		$conv_id = sp_msg_find_or_create_dm( $a, $b );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $mtable SET conversation_id = %d
			 WHERE conversation_id = 0 AND ( (sender_id=%d AND recipient_id=%d) OR (sender_id=%d AND recipient_id=%d) )",
			$conv_id, $a, $b, $b, $a
		) );
		// updated_at = last message; mark everyone read up to the latest (one-time reset).
		$last = sp_msg_max_message_id( $conv_id );
		$lastat = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(created_at) FROM $mtable WHERE conversation_id = %d", $conv_id ) );
		if ( $lastat ) $wpdb->update( site_pulse_table( 'conversations' ), [ 'updated_at' => $lastat ], [ 'id' => $conv_id ] );
		$wpdb->update( site_pulse_table( 'conversation_participants' ), [ 'last_read_message_id' => $last ], [ 'conversation_id' => $conv_id ] );
	}
}

// ---- AJAX ------------------------------------------------------------------

add_action( 'wp_ajax_site_pulse_messages_contacts', 'site_pulse_ajax_messages_contacts' );
function site_pulse_ajax_messages_contacts(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! $me ) wp_send_json_error( [ 'message' => 'Not signed in.' ] );
	wp_send_json_success( [ 'contacts' => sp_msg_contacts( $me ) ] );
}

// The viewer's conversations: title, last preview, unread count — most-recent first.
add_action( 'wp_ajax_site_pulse_messages_conversations', 'site_pulse_ajax_messages_conversations' );
function site_pulse_ajax_messages_conversations(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! $me ) wp_send_json_error( [ 'message' => 'Not signed in.' ] );

	global $wpdb;
	$c  = site_pulse_table( 'conversations' );
	$cp = site_pulse_table( 'conversation_participants' );
	$m  = site_pulse_table( 'messages' );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT c.id, c.is_group, c.title, c.updated_at, p.last_read_message_id
		 FROM $c c JOIN $cp p ON p.conversation_id = c.id AND p.user_id = %d
		 ORDER BY c.updated_at DESC LIMIT 200",
		$me
	), ARRAY_A ) ?: [];

	$out = [];
	foreach ( $rows as $r ) {
		$cid  = (int) $r['id'];
		$meta = sp_msg_conversation_meta( $cid, $me );
		$last = $wpdb->get_row( $wpdb->prepare(
			"SELECT sender_id, body FROM $m WHERE conversation_id = %d ORDER BY id DESC LIMIT 1", $cid
		), ARRAY_A );
		$unread = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $m WHERE conversation_id = %d AND id > %d AND sender_id != %d",
			$cid, (int) $r['last_read_message_id'], $me
		) );
		$out[] = [
			'id'        => $cid,
			'is_group'  => $meta['is_group'],
			'name'      => $meta['title'],
			'last'      => $last ? $last['body'] : '',
			'last_mine' => $last ? ( (int) $last['sender_id'] === $me ) : false,
			'unread'    => $unread,
		];
	}
	wp_send_json_success( [ 'conversations' => $out ] );
}

// A conversation's messages (ascending) + header meta. Marks it read for the viewer.
add_action( 'wp_ajax_site_pulse_messages_thread', 'site_pulse_ajax_messages_thread' );
function site_pulse_ajax_messages_thread(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me  = site_pulse_effective_user_id();
	$cid = (int) ( $_POST['conversation_id'] ?? 0 );
	if ( ! $me || ! $cid || ! sp_msg_is_participant( $cid, $me ) ) wp_send_json_error( [ 'message' => 'Conversation not found.' ] );

	global $wpdb;
	$m = site_pulse_table( 'messages' );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, sender_id, body, edited, attach_url, attach_name, attach_mime, created_at FROM $m WHERE conversation_id = %d ORDER BY created_at ASC, id ASC LIMIT 500",
		$cid
	), ARRAY_A ) ?: [];

	$messages = array_map( fn( $r ) => [
		'id'          => (int) $r['id'],
		'mine'        => (int) $r['sender_id'] === $me,
		'sender'      => sp_msg_user( (int) $r['sender_id'] )['name'],
		'body'        => $r['body'],
		'edited'      => (int) $r['edited'],
		'attach_url'  => $r['attach_url'],
		'attach_name' => $r['attach_name'],
		'attach_mime' => $r['attach_mime'],
		'at'          => $r['created_at'],
	], $rows );

	// Mark read up to the latest message + clear this conversation's bell notification — but NOT while
	// a god is impersonating: viewing as someone must not consume their unread state. The god is just
	// looking; only the real person opening it should mark it read.
	if ( ! site_pulse_is_impersonating() ) {
		$last = sp_msg_max_message_id( $cid );
		$wpdb->update( site_pulse_table( 'conversation_participants' ), [ 'last_read_message_id' => $last ], [ 'conversation_id' => $cid, 'user_id' => $me ] );
		$wpdb->query( $wpdb->prepare(
			"UPDATE " . site_pulse_table( 'notifications' ) . " SET is_read = 1
			 WHERE user_id = %d AND type = 'message' AND related_type = 'conversation' AND related_id = %d AND is_read = 0",
			$me, $cid
		) );
	}

	// Other participants' "seen" pointers, so the viewer can show read receipts on their own messages.
	$seen  = [];
	$prows = $wpdb->get_results( $wpdb->prepare(
		"SELECT user_id, seen_message_id, seen_at FROM " . site_pulse_table( 'conversation_participants' ) . "
		 WHERE conversation_id = %d AND user_id != %d", $cid, $me
	), ARRAY_A ) ?: [];
	foreach ( $prows as $p ) {
		$seen[] = [
			'user_id'         => (int) $p['user_id'],
			'name'            => sp_msg_user( (int) $p['user_id'] )['name'],
			'seen_message_id' => (int) $p['seen_message_id'],
			'seen_at'         => $p['seen_at'],
		];
	}

	$meta = sp_msg_conversation_meta( $cid, $me );
	wp_send_json_success( [ 'conversation' => $meta, 'messages' => $messages, 'seen' => $seen, 'me' => $me ] );
}

// Mark the conversation "seen" for the current user up to its latest message. Called by the client
// only after the thread has been displayed for 3+ seconds (genuine read receipt, not just opened).
add_action( 'wp_ajax_site_pulse_messages_seen', 'site_pulse_ajax_messages_seen' );
function site_pulse_ajax_messages_seen(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me  = site_pulse_effective_user_id();
	$cid = (int) ( $_POST['conversation_id'] ?? 0 );
	if ( ! $me || ! $cid || ! sp_msg_is_participant( $cid, $me ) ) wp_send_json_error( [ 'message' => 'Conversation not found.' ] );

	// Don't record "seen" while a god is impersonating — only the real person actually viewing the
	// message should mark it seen for themselves.
	if ( site_pulse_is_impersonating() ) { wp_send_json_success(); return; }

	$last = sp_msg_max_message_id( $cid );
	global $wpdb;
	$wpdb->update(
		site_pulse_table( 'conversation_participants' ),
		[ 'seen_message_id' => $last, 'seen_at' => current_time( 'mysql' ) ],
		[ 'conversation_id' => $cid, 'user_id' => $me ]
	);
	wp_send_json_success();
}

// Start (or reuse) a 1:1 DM with one user → returns the conversation id.
add_action( 'wp_ajax_site_pulse_messages_start_dm', 'site_pulse_ajax_messages_start_dm' );
function site_pulse_ajax_messages_start_dm(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me    = site_pulse_effective_user_id();
	$other = (int) ( $_POST['user_id'] ?? 0 );
	if ( ! $me || ! $other || $other === $me ) wp_send_json_error( [ 'message' => 'Invalid recipient.' ] );
	if ( ! site_pulse_get_user_profile( $other ) ) wp_send_json_error( [ 'message' => 'That user can’t receive messages.' ] );
	wp_send_json_success( [ 'conversation_id' => sp_msg_find_or_create_dm( $me, $other ) ] );
}

// Create a group thread with a title + members (creator included) → returns the conversation id.
add_action( 'wp_ajax_site_pulse_messages_create_group', 'site_pulse_ajax_messages_create_group' );
function site_pulse_ajax_messages_create_group(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me     = site_pulse_effective_user_id();
	$title  = trim( (string) wp_unslash( $_POST['title'] ?? '' ) );
	$ids    = json_decode( (string) wp_unslash( $_POST['user_ids'] ?? '[]' ), true );
	$ids    = is_array( $ids ) ? array_unique( array_map( 'intval', $ids ) ) : [];
	$ids    = array_values( array_filter( $ids, fn( $id ) => $id && $id !== $me && site_pulse_get_user_profile( $id ) ) );
	if ( ! $me )            wp_send_json_error( [ 'message' => 'Not signed in.' ] );
	if ( count( $ids ) < 2 ) wp_send_json_error( [ 'message' => 'Pick at least two people for a group.' ] );

	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert( site_pulse_table( 'conversations' ), [
		'is_group'   => 1,
		'title'      => $title !== '' ? sanitize_text_field( $title ) : null,
		'created_by' => $me,
		'created_at' => $now,
		'updated_at' => $now,
	] );
	$cid = (int) $wpdb->insert_id;
	sp_msg_add_participant( $cid, $me );
	foreach ( $ids as $id ) sp_msg_add_participant( $cid, $id );

	wp_send_json_success( [ 'conversation_id' => $cid ] );
}

// Add a member (group creator only). New member sees history but starts with a clean unread count.
add_action( 'wp_ajax_site_pulse_messages_add_member', 'site_pulse_ajax_messages_add_member' );
function site_pulse_ajax_messages_add_member(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me  = site_pulse_effective_user_id();
	$cid = (int) ( $_POST['conversation_id'] ?? 0 );
	$uid = (int) ( $_POST['user_id'] ?? 0 );
	$conv = sp_msg_require_group_creator( $me, $cid );
	if ( ! $uid || ! site_pulse_get_user_profile( $uid ) ) wp_send_json_error( [ 'message' => 'Invalid user.' ] );
	sp_msg_add_participant( $cid, $uid, sp_msg_max_message_id( $cid ) );
	wp_send_json_success( [ 'conversation' => sp_msg_conversation_meta( $cid, $me ) ] );
}

// Remove a member (group creator only; can't remove the creator).
add_action( 'wp_ajax_site_pulse_messages_remove_member', 'site_pulse_ajax_messages_remove_member' );
function site_pulse_ajax_messages_remove_member(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me  = site_pulse_effective_user_id();
	$cid = (int) ( $_POST['conversation_id'] ?? 0 );
	$uid = (int) ( $_POST['user_id'] ?? 0 );
	$conv = sp_msg_require_group_creator( $me, $cid );
	if ( $uid === (int) $conv['created_by'] ) wp_send_json_error( [ 'message' => 'The creator can’t be removed.' ] );
	global $wpdb;
	$wpdb->delete( site_pulse_table( 'conversation_participants' ), [ 'conversation_id' => $cid, 'user_id' => $uid ] );
	wp_send_json_success( [ 'conversation' => sp_msg_conversation_meta( $cid, $me ) ] );
}

// Leave a group (any member).
add_action( 'wp_ajax_site_pulse_messages_leave', 'site_pulse_ajax_messages_leave' );
function site_pulse_ajax_messages_leave(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me  = site_pulse_effective_user_id();
	$cid = (int) ( $_POST['conversation_id'] ?? 0 );
	if ( ! $me || ! $cid || ! sp_msg_is_participant( $cid, $me ) ) wp_send_json_error( [ 'message' => 'Not a member.' ] );
	global $wpdb;
	$wpdb->delete( site_pulse_table( 'conversation_participants' ), [ 'conversation_id' => $cid, 'user_id' => $me ] );
	wp_send_json_success( [ 'left' => true ] );
}

// Shared guard: returns the conversation row if it's a group and $me created it, else errors out.
function sp_msg_require_group_creator( int $me, int $cid ): array {
	global $wpdb;
	$conv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . site_pulse_table( 'conversations' ) . " WHERE id = %d", $cid ), ARRAY_A );
	if ( ! $conv || ! (int) $conv['is_group'] )       wp_send_json_error( [ 'message' => 'Not a group.' ] );
	if ( (int) $conv['created_by'] !== $me )          wp_send_json_error( [ 'message' => 'Only the group creator can manage members.' ] );
	return $conv;
}

// Send a message to a conversation.
add_action( 'wp_ajax_site_pulse_messages_send', 'site_pulse_ajax_messages_send' );
function site_pulse_ajax_messages_send(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me   = site_pulse_effective_user_id();
	$cid  = (int) ( $_POST['conversation_id'] ?? 0 );
	$body = trim( (string) wp_unslash( $_POST['body'] ?? '' ) );

	if ( ! $me || ! $cid || ! sp_msg_is_participant( $cid, $me ) ) wp_send_json_error( [ 'message' => 'Conversation not found.' ] );
	if ( $body === '' ) wp_send_json_error( [ 'message' => 'Message is empty.' ] );

	$body = sanitize_textarea_field( $body );
	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert( site_pulse_table( 'messages' ), [
		'conversation_id' => $cid,
		'sender_id'       => $me,
		'recipient_id'    => 0,
		'body'            => $body,
		'is_read'         => 0,
		'created_at'      => $now,
	] );
	$msg_id = (int) $wpdb->insert_id;

	$wpdb->update( site_pulse_table( 'conversations' ), [ 'updated_at' => $now ], [ 'id' => $cid ] );
	// Sender has implicitly read their own message.
	$wpdb->update( site_pulse_table( 'conversation_participants' ), [ 'last_read_message_id' => $msg_id ], [ 'conversation_id' => $cid, 'user_id' => $me ] );

	sp_msg_notify_participants( $cid, $me, $body );

	wp_send_json_success( [ 'message' => [
		'id'          => $msg_id,
		'mine'        => true,
		'sender'      => sp_msg_user( $me )['name'],
		'body'        => $body,
		'edited'      => 0,
		'attach_url'  => null,
		'attach_name' => null,
		'attach_mime' => null,
		'at'          => $now,
	] ] );
}

// Upload a file and post it as a message (optionally with a caption from the composer). Multipart;
// validated server-side (extension allow/block + size + real MIME sniff). Stored under a randomized
// name in uploads/site-pulse-msg/Y/m so URLs aren't guessable.
add_action( 'wp_ajax_site_pulse_messages_upload_send', 'site_pulse_ajax_messages_upload_send' );
function site_pulse_ajax_messages_upload_send(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me   = site_pulse_effective_user_id();
	$cid  = (int) ( $_POST['conversation_id'] ?? 0 );
	$body = trim( (string) wp_unslash( $_POST['body'] ?? '' ) );
	if ( ! $me || ! $cid || ! sp_msg_is_participant( $cid, $me ) ) wp_send_json_error( [ 'message' => 'Conversation not found.' ] );
	if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ?? '' ) ) wp_send_json_error( [ 'message' => 'No file received.' ] );

	$file = $_FILES['file'];
	$max  = 15 * 1024 * 1024; // 15 MB
	if ( (int) $file['size'] > $max ) wp_send_json_error( [ 'message' => 'File is too large (max 15 MB).' ] );

	$name = $file['name'];
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$blocked = [ 'php','phtml','phar','php3','php4','php5','pl','py','sh','cgi','exe','bat','cmd','com','msi','js','mjs','html','htm','svg','htaccess' ];
	$allowed = [ 'jpg','jpeg','png','gif','webp','heic','heif','bmp','tif','tiff','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','rtf','zip' ];
	if ( in_array( $ext, $blocked, true ) || ! in_array( $ext, $allowed, true ) ) {
		wp_send_json_error( [ 'message' => 'That file type isn’t allowed.' ] );
	}
	// Real-content type check (not the client-claimed type).
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $name );
	if ( empty( $check['ext'] ) || empty( $check['type'] ) ) wp_send_json_error( [ 'message' => 'That file type isn’t allowed.' ] );

	// Route into a dedicated subfolder with a randomized basename.
	$subdir = 'site-pulse-msg';
	$redir  = function ( $dirs ) use ( $subdir ) {
		$dirs['subdir'] = '/' . $subdir . $dirs['subdir'];
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	};
	add_filter( 'upload_dir', $redir );
	$file['name'] = 'spmsg-' . wp_generate_password( 16, false, false ) . '.' . $check['ext'];
	$moved = wp_handle_upload( $file, [ 'test_form' => false ] );
	remove_filter( 'upload_dir', $redir );

	if ( empty( $moved['url'] ) || ! empty( $moved['error'] ) ) {
		wp_send_json_error( [ 'message' => $moved['error'] ?? 'Upload failed.' ] );
	}

	if ( $body !== '' ) $body = sanitize_textarea_field( $body );

	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert( site_pulse_table( 'messages' ), [
		'conversation_id' => $cid,
		'sender_id'       => $me,
		'recipient_id'    => 0,
		'body'            => $body,
		'is_read'         => 0,
		'attach_url'      => esc_url_raw( $moved['url'] ),
		'attach_name'     => sanitize_text_field( $name ),
		'attach_mime'     => sanitize_text_field( $moved['type'] ),
		'created_at'      => $now,
	] );
	$msg_id = (int) $wpdb->insert_id;

	$wpdb->update( site_pulse_table( 'conversations' ), [ 'updated_at' => $now ], [ 'id' => $cid ] );
	$wpdb->update( site_pulse_table( 'conversation_participants' ), [ 'last_read_message_id' => $msg_id ], [ 'conversation_id' => $cid, 'user_id' => $me ] );
	sp_msg_notify_participants( $cid, $me, $body !== '' ? $body : ( '📎 ' . $name ) );

	wp_send_json_success( [ 'message' => [
		'id'          => $msg_id,
		'mine'        => true,
		'sender'      => sp_msg_user( $me )['name'],
		'body'        => $body,
		'edited'      => 0,
		'attach_url'  => $moved['url'],
		'attach_name' => $name,
		'attach_mime' => $moved['type'],
		'at'          => $now,
	] ] );
}

// Bell (+ optional email) for every OTHER participant. Deduped per conversation per recipient.
function sp_msg_notify_participants( int $conv_id, int $sender_id, string $body ): void {
	global $wpdb;
	$sender  = get_userdata( $sender_id );
	$name    = $sender ? $sender->display_name : 'Someone';
	$meta    = sp_msg_conversation_meta( $conv_id, $sender_id );
	$preview = mb_substr( trim( preg_replace( '/\s+/', ' ', $body ) ), 0, 80 );
	$prefix  = $meta['is_group'] ? sprintf( '%s in %s', $name, $meta['title'] ) : $name;
	$msg     = sprintf( 'New message from %s: %s', $prefix, $preview );
	$ntable  = site_pulse_table( 'notifications' );

	foreach ( sp_msg_participant_ids( $conv_id ) as $uid ) {
		if ( $uid === $sender_id ) continue;
		$existing = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $ntable WHERE user_id = %d AND type = 'message' AND related_type = 'conversation'
			 AND related_id = %d AND is_read = 0 AND is_archived = 0 LIMIT 1",
			$uid, $conv_id
		) );
		if ( $existing ) {
			$wpdb->update( $ntable, [ 'message' => $msg, 'created_at' => current_time( 'mysql' ) ], [ 'id' => $existing ] );
			if ( function_exists( 'site_pulse_push_send' ) ) site_pulse_push_send( $uid ); // wake their devices for each new message
			continue;
		}
		site_pulse_notify( $uid, 'message', $msg, $conv_id, 'conversation' );
		if ( function_exists( 'site_pulse_push_send' ) ) site_pulse_push_send( $uid );
		if ( site_pulse_get_setting( 'messages_email_enabled', '0' ) === '1' ) sp_msg_email_recipient( $uid, $prefix );
	}
}

function sp_msg_email_recipient( int $recipient_id, string $sender_label ): void {
	$u = get_userdata( $recipient_id );
	if ( ! $u || ! is_email( $u->user_email ) ) return;
	$app  = site_pulse_get_setting( 'app_name', 'Site Pulse' );
	$link = home_url( '/site-pulse-dashboard/?sp_panel=messages' );
	$subject = sprintf( 'New message from %s · %s', $sender_label, $app );
	$body  = '<p>Hi ' . esc_html( $u->display_name ) . ',</p>';
	$body .= '<p><strong>' . esc_html( $sender_label ) . '</strong> sent you a message in ' . esc_html( $app ) . '.</p>';
	$body .= '<p><a href="' . esc_url( $link ) . '">Open ' . esc_html( $app ) . ' to read and reply</a></p>';
	wp_mail( $u->user_email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
}

// Edit one of your OWN messages (sender-only). Marks it edited.
add_action( 'wp_ajax_site_pulse_messages_edit', 'site_pulse_ajax_messages_edit' );
function site_pulse_ajax_messages_edit(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me   = site_pulse_effective_user_id();
	$id   = (int) ( $_POST['id'] ?? 0 );
	$body = trim( (string) wp_unslash( $_POST['body'] ?? '' ) );
	if ( ! $me || ! $id ) wp_send_json_error( [ 'message' => 'Invalid message.' ] );
	if ( $body === '' )   wp_send_json_error( [ 'message' => 'Message is empty.' ] );

	global $wpdb;
	$table  = site_pulse_table( 'messages' );
	$sender = (int) $wpdb->get_var( $wpdb->prepare( "SELECT sender_id FROM $table WHERE id = %d", $id ) );
	if ( $sender !== $me ) wp_send_json_error( [ 'message' => 'You can only edit your own messages.' ] );

	$body = sanitize_textarea_field( $body );
	$wpdb->update( $table, [ 'body' => $body, 'edited' => 1 ], [ 'id' => $id ] );
	wp_send_json_success( [ 'id' => $id, 'body' => $body ] );
}

// Delete one of your OWN messages (sender-only).
add_action( 'wp_ajax_site_pulse_messages_delete', 'site_pulse_ajax_messages_delete' );
function site_pulse_ajax_messages_delete(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $me || ! $id ) wp_send_json_error( [ 'message' => 'Invalid message.' ] );

	global $wpdb;
	$table  = site_pulse_table( 'messages' );
	$sender = (int) $wpdb->get_var( $wpdb->prepare( "SELECT sender_id FROM $table WHERE id = %d", $id ) );
	if ( $sender !== $me ) wp_send_json_error( [ 'message' => 'You can only delete your own messages.' ] );

	$wpdb->delete( $table, [ 'id' => $id ] );
	wp_send_json_success( [ 'id' => $id ] );
}

// ---- Action items from a message -------------------------------------------

// Build an AI excerpt: the focus message + up to 2 before and 2 after, with the focus marked.
function sp_msg_action_excerpt( int $message_id, int $me ): ?array {
	global $wpdb;
	$m   = site_pulse_table( 'messages' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, conversation_id FROM $m WHERE id = %d", $message_id ), ARRAY_A );
	if ( ! $row ) return null;
	$cid = (int) $row['conversation_id'];
	if ( ! sp_msg_is_participant( $cid, $me ) ) return null;

	$before = $wpdb->get_results( $wpdb->prepare( "SELECT id, sender_id, body FROM $m WHERE conversation_id = %d AND id < %d ORDER BY id DESC LIMIT 2", $cid, $message_id ), ARRAY_A ) ?: [];
	$after  = $wpdb->get_results( $wpdb->prepare( "SELECT id, sender_id, body FROM $m WHERE conversation_id = %d AND id > %d ORDER BY id ASC LIMIT 2", $cid, $message_id ), ARRAY_A ) ?: [];
	$focus  = $wpdb->get_row( $wpdb->prepare( "SELECT id, sender_id, body FROM $m WHERE id = %d", $message_id ), ARRAY_A );
	$seq    = array_merge( array_reverse( $before ), [ $focus ], $after );

	$text = "Conversation excerpt (the FOCUS message is marked with >>):\n\n";
	foreach ( $seq as $r ) {
		if ( ! $r ) continue;
		$who  = sp_msg_user( (int) $r['sender_id'] )['name'];
		$mark = ( (int) $r['id'] === $message_id ) ? '>> ' : '';
		$text .= $mark . $who . ': ' . trim( (string) $r['body'] ) . "\n";
	}
	$text .= "\nExtract any action items implied by the FOCUS message; use the surrounding messages only as context.";
	return [ 'conversation_id' => $cid, 'text' => $text ];
}

// Same AI call/parse as GM reports, but with a GENERAL-PURPOSE prompt (the report prompt is tuned
// for restaurant ops and returns nothing for ordinary chat).
function sp_msg_generate_action_items_from_text( string $text, &$debug = null ): array {
	$debug  = null;
	$system = function_exists( 'site_pulse_prompt_message_action_items' )
		? site_pulse_prompt_message_action_items()
		: site_pulse_prompt_extract_action_items();
	$ai_debug = null;
	$result   = site_pulse_call_claude( $text, $system, [], $ai_debug );
	if ( $result === null ) { $debug = 'AI request failed' . ( $ai_debug ? ': ' . $ai_debug : '.' ); return []; }
	$result = trim( $result );
	if ( strpos( $result, '```' ) !== false ) {
		$result = preg_replace( '/```(?:json)?\s*/', '', $result );
		$result = preg_replace( '/```\s*$/', '', $result );
		$result = trim( $result );
	}
	$items = json_decode( $result, true );
	if ( ! is_array( $items ) ) { $debug = 'AI returned an unreadable response: ' . substr( $result, 0, 160 ); return []; }
	return $items;
}

// AJAX: suggest action item(s) from a message (no DB write) — returns the proposed items + the other
// participant(s) so the client can offer "add to both lists".
add_action( 'wp_ajax_site_pulse_messages_action_suggest', 'site_pulse_ajax_messages_action_suggest' );
function site_pulse_ajax_messages_action_suggest(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me  = site_pulse_effective_user_id();
	$mid = (int) ( $_POST['message_id'] ?? 0 );
	if ( ! $me || ! $mid ) wp_send_json_error( [ 'message' => 'Invalid message.' ] );
	if ( ! site_pulse_get_api_key() ) wp_send_json_error( [ 'message' => 'AI isn’t configured on this site (set the Claude API key in Settings → API Keys).' ] );

	$ctx = sp_msg_action_excerpt( $mid, $me );
	if ( ! $ctx ) wp_send_json_error( [ 'message' => 'Conversation not found.' ] );

	$debug = null;
	$items = sp_msg_generate_action_items_from_text( $ctx['text'], $debug );
	$out   = [];
	foreach ( $items as $it ) {
		$desc = trim( (string) ( $it['description'] ?? '' ) );
		if ( $desc === '' ) continue;
		$out[] = [
			'category'    => sanitize_text_field( (string) ( $it['category'] ?? '' ) ),
			'description' => $desc,
			'priority'    => in_array( $it['priority'] ?? '', [ 'high', 'medium', 'low' ], true ) ? $it['priority'] : 'medium',
		];
	}

	// If the AI errored (vs. genuinely finding nothing), surface why so it's not a silent blank.
	if ( ! $out && $debug ) wp_send_json_error( [ 'message' => $debug ] );

	$others = [];
	foreach ( sp_msg_participant_ids( $ctx['conversation_id'] ) as $uid ) {
		if ( $uid !== $me ) $others[] = sp_msg_user( $uid );
	}

	wp_send_json_success( [ 'items' => $out, 'conversation_id' => $ctx['conversation_id'], 'others' => $others, 'empty' => empty( $out ) ] );
}

// AJAX: create the chosen action item(s) for me (and, if 'both', the other participant(s)). Created
// as live 'open' items (no review step — the user explicitly chose to create them).
add_action( 'wp_ajax_site_pulse_messages_action_create', 'site_pulse_ajax_messages_action_create' );
function site_pulse_ajax_messages_action_create(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me     = site_pulse_effective_user_id();
	$cid    = (int) ( $_POST['conversation_id'] ?? 0 );
	$assign = in_array( $_POST['assign'] ?? 'me', [ 'me', 'other', 'both' ], true ) ? $_POST['assign'] : 'me';
	$items  = json_decode( (string) wp_unslash( $_POST['items'] ?? '[]' ), true );
	if ( ! $me || ! $cid || ! sp_msg_is_participant( $cid, $me ) ) wp_send_json_error( [ 'message' => 'Conversation not found.' ] );
	if ( ! is_array( $items ) || ! $items ) wp_send_json_error( [ 'message' => 'No action items selected.' ] );

	// me = just me; other = the other participant(s); both = me + others.
	$assignees = [];
	if ( $assign !== 'other' ) $assignees[] = $me;
	if ( $assign !== 'me' ) {
		foreach ( sp_msg_participant_ids( $cid ) as $uid ) { if ( $uid !== $me ) $assignees[] = $uid; }
	}
	$assignees = array_values( array_unique( $assignees ) );
	if ( ! $assignees ) wp_send_json_error( [ 'message' => 'No one to assign this to.' ] );

	global $wpdb;
	$now     = current_time( 'mysql' );
	$due     = date( 'Y-m-d', strtotime( '+14 days' ) );
	$me_name = sp_msg_user( $me )['name'];
	$created = 0;

	foreach ( $assignees as $uid ) {
		$prof = site_pulse_get_user_profile( $uid );
		$loc  = $prof ? (int) $prof['location_id'] : 0;
		foreach ( array_slice( $items, 0, 10 ) as $it ) {
			$desc = trim( (string) ( $it['description'] ?? '' ) );
			if ( $desc === '' ) continue;
			$priority = in_array( $it['priority'] ?? '', [ 'high', 'medium', 'low' ], true ) ? $it['priority'] : 'medium';
			$wpdb->insert( site_pulse_table( 'action_items' ), [
				'report_id'   => 0,
				'user_id'     => $uid,
				'created_by'  => $me,
				'location_id' => $loc,
				'category'    => sanitize_text_field( (string) ( $it['category'] ?? 'Message' ) ) ?: 'Message',
				'description' => sanitize_text_field( $desc ),
				'priority'    => $priority,
				'status'      => 'open',
				'due_date'    => $due,
				'created_at'  => $now,
				'updated_at'  => $now,
			] );
			$created++;
			if ( $uid !== $me ) {
				site_pulse_notify( $uid, 'action_pending', sprintf( '%s added an action item for you: %s', $me_name, $desc ), (int) $wpdb->insert_id, 'action_item' );
			}
		}
	}

	wp_send_json_success( [ 'created' => $created ] );
}

// Unread message total for the Messages nav badge (across all the viewer's conversations).
add_action( 'wp_ajax_site_pulse_messages_unread', 'site_pulse_ajax_messages_unread' );
function site_pulse_ajax_messages_unread(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$me = site_pulse_effective_user_id();
	if ( ! $me ) wp_send_json_success( [ 'count' => 0 ] );
	global $wpdb;
	$count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM " . site_pulse_table( 'messages' ) . " m
		 JOIN " . site_pulse_table( 'conversation_participants' ) . " p
		   ON p.conversation_id = m.conversation_id AND p.user_id = %d
		 WHERE m.id > p.last_read_message_id AND m.sender_id != %d",
		$me, $me
	) );
	wp_send_json_success( [ 'count' => $count ] );
}
