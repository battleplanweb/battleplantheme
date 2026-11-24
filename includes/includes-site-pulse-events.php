<?php
/* Battle Plan Web Design - Site Pulse Events
   The framework's Events (the `events` CPT from includes-events.php — the public calendar, the event
   pages and [event_teasers]) added, edited and deleted from Site Pulse, so clients never need wp-admin.
   wp-admin keeps working exactly as before: both write the same ACF fields, and every Site Pulse save
   runs the same two derivations the editor's acf/save_post chain runs (recurrence → date blocks, then
   the legacy start/end span the teasers, sorting and admin columns read).

   Caps: view_events (see the list), manage_events (add, edit, delete). The module only does anything
   where Events is loaded (the CPT + its functions exist); it's switched on automatically on installs
   that carry the event_calendar option, and switching the module on loads Events on an install that
   doesn't (functions.php).

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Helpers & Permissions
# Reading an event
# AJAX — List / Get
# AJAX — Save / Trash
# AJAX — Photo Upload
# Migrations (auto-enable + caps seed)
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Helpers & Permissions
--------------------------------------------------------------*/

// Events itself is loaded on this site (includes-events.php only loads when it's on).
function sp_events_ready(): bool {
	return post_type_exists( 'events' ) && function_exists( 'bp_event_regenerate' );
}

function sp_events_can_view( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_user_can( $user_id, 'view_events' ) || site_pulse_user_can( $user_id, 'manage_events' );
}

function sp_events_can_manage( int $user_id = 0 ): bool {
	if ( ! $user_id ) $user_id = site_pulse_effective_user_id();
	return site_pulse_user_can( $user_id, 'manage_events' );
}

// Common gate for every handler: nonce, Events loaded, and the given permission check.
function sp_events_gate( callable $check, string $msg ): int {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	if ( ! sp_events_ready() ) wp_send_json_error( [ 'message' => 'Events are not installed on this site.' ] );
	$me = site_pulse_effective_user_id();
	if ( ! $check( $me ) ) wp_send_json_error( [ 'message' => $msg ] );
	return $me;
}

// The event-tags the framework manages itself. `featured` is the one people choose (it's what
// [event_teasers] shows by default); `upcoming`/`expired` are status bookkeeping, never hand-edited.
function sp_events_system_tags(): array {
	return [ 'upcoming', 'expired', 'featured' ];
}


/*--------------------------------------------------------------
# Reading an event
--------------------------------------------------------------*/

// "Sat, Oct 3, 2026" / "Oct 3 – 5, 2026" / "Oct 30 – Nov 2, 2026" for one date block.
function sp_events_block_label( array $b ): string {
	$s = strtotime( (string) ( $b['s_date'] ?? '' ) );
	if ( ! $s ) return '';
	$e = ! empty( $b['e_date'] ) ? strtotime( (string) $b['e_date'] ) : $s;
	if ( ! $e || $e <= $s ) $label = date( 'D, M j, Y', $s );
	elseif ( date( 'Y-m', $s ) === date( 'Y-m', $e ) ) $label = date( 'M j', $s ) . ' – ' . date( 'j, Y', $e );
	elseif ( date( 'Y', $s ) === date( 'Y', $e ) ) $label = date( 'M j', $s ) . ' – ' . date( 'M j, Y', $e );
	else $label = date( 'M j, Y', $s ) . ' – ' . date( 'M j, Y', $e );

	$st = ! empty( $b['s_time'] ) ? strtotime( (string) $b['s_time'] ) : 0;
	$et = ! empty( $b['e_time'] ) ? strtotime( (string) $b['e_time'] ) : 0;
	if ( $st ) $label .= ' · ' . date( 'g:ia', $st ) . ( $et ? '–' . date( 'g:ia', $et ) : '' );
	return $label;
}

// Blocks normalised to Y-m-d / H:i (legacy events store Ymd / H:i:s via bp_event_date_blocks()).
function sp_events_blocks( int $id ): array {
	$out = [];
	foreach ( (array) bp_event_date_blocks( $id ) as $b ) {
		$s = strtotime( (string) ( $b['s_date'] ?? '' ) );
		if ( ! $s ) continue;
		$e = ! empty( $b['e_date'] ) ? strtotime( (string) $b['e_date'] ) : $s;
		$out[] = [
			's_date' => date( 'Y-m-d', $s ),
			'e_date' => date( 'Y-m-d', $e ?: $s ),
			's_time' => ! empty( $b['s_time'] ) ? date( 'H:i', strtotime( (string) $b['s_time'] ) ) : '',
			'e_time' => ! empty( $b['e_time'] ) ? date( 'H:i', strtotime( (string) $b['e_time'] ) ) : '',
		];
	}
	return $out;
}

// One post -> the row the list renders. "When" is the next block that hasn't finished (or the last
// one, for a past event) plus how many more dates follow it.
function sp_events_format_row( WP_Post $p ): array {
	$id     = (int) $p->ID;
	$blocks = sp_events_blocks( $id );
	$today  = date( 'Y-m-d', current_time( 'timestamp' ) );
	$next   = null; $after = 0;
	foreach ( $blocks as $b ) {
		if ( $b['e_date'] < $today ) continue;
		if ( $next === null ) $next = $b; else $after++;
	}
	$past = ( $next === null );
	if ( $past && $blocks ) $next = end( $blocks );

	$tags = wp_get_post_terms( $id, 'event-tags', [ 'fields' => 'slugs' ] );
	$tags = is_wp_error( $tags ) ? [] : $tags;
	$live = $p->post_status === 'publish';
	$thumb = (string) get_the_post_thumbnail_url( $id, 'thumbnail' );

	return [
		'id'        => $id,
		'title'     => get_the_title( $id ),
		'status'    => $live ? 'publish' : 'draft',
		'when'      => $next ? sp_events_block_label( $next ) : '',
		'more'      => $after,
		'next'      => $next['s_date'] ?? '',
		'past'      => $past && (bool) $blocks,
		'no_dates'  => ! $blocks,
		'recurring' => get_post_meta( $id, 'date_type', true ) === 'recurring',
		'venue'     => (string) get_post_meta( $id, 'venue', true ),
		'location'  => (string) get_post_meta( $id, 'location', true ),
		'featured'  => in_array( 'featured', $tags, true ),
		'expired'   => in_array( 'expired', $tags, true ),
		'thumb'     => $thumb,
		'link'      => $live ? get_permalink( $id ) : '',
		'booking'   => function_exists( 'sp_book_event_summary' ) ? sp_book_event_summary( $id ) : null,
	];
}

// Categories for the editor's picker: [ { id, name, parent } ].
function sp_events_categories(): array {
	$terms = get_terms( [ 'taxonomy' => 'event-cats', 'hide_empty' => false ] );
	if ( is_wp_error( $terms ) ) return [];
	return array_map( static fn( $t ) => [ 'id' => (int) $t->term_id, 'name' => html_entity_decode( $t->name, ENT_QUOTES ), 'parent' => (int) $t->parent ], $terms );
}


/*--------------------------------------------------------------
# AJAX — List / Get
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_events_list', 'sp_events_ajax_list' );
function sp_events_ajax_list(): void {
	$me     = sp_events_gate( 'sp_events_can_view', 'You do not have access to events.' );
	$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
	$view   = sanitize_key( $_POST['view'] ?? 'upcoming' );           // upcoming | past | draft | all
	$sort   = sanitize_key( $_POST['sort'] ?? 'soon' );

	// Everything in one pass — an install has dozens of events, not thousands — then split by view in
	// PHP, because "upcoming vs past" is a property of the date blocks, which SQL can't see.
	$q = new WP_Query( [
		'post_type'      => 'events',
		'post_status'    => [ 'publish', 'draft', 'pending', 'future' ],
		'posts_per_page' => 500,
		's'              => $search,
		'no_found_rows'  => true,
	] );

	$rows   = array_map( 'sp_events_format_row', $q->posts );
	$counts = [ 'upcoming' => 0, 'past' => 0, 'draft' => 0, 'all' => count( $rows ) ];
	foreach ( $rows as $r ) {
		if ( $r['status'] !== 'publish' ) $counts['draft']++;
		elseif ( $r['past'] ) $counts['past']++;
		else $counts['upcoming']++;
	}
	$rows = array_values( array_filter( $rows, static function ( $r ) use ( $view ) {
		if ( $view === 'draft' )    return $r['status'] !== 'publish';
		if ( $view === 'past' )     return $r['status'] === 'publish' && $r['past'];
		if ( $view === 'upcoming' ) return $r['status'] === 'publish' && ! $r['past'];
		return true;
	} ) );

	usort( $rows, static function ( $a, $b ) use ( $sort ) {
		if ( $sort === 'az' ) return strcasecmp( $a['title'], $b['title'] );
		if ( $sort === 'za' ) return strcasecmp( $b['title'], $a['title'] );
		// Undated events sink to the bottom either way.
		$x = $a['next'] ?: '9999'; $y = $b['next'] ?: '9999';
		return $sort === 'late' ? strcmp( $y, $x ) : strcmp( $x, $y );
	} );

	wp_send_json_success( [
		'events'     => $rows,
		'counts'     => $counts,
		'total'      => count( $rows ),
		'can_manage' => sp_events_can_manage( $me ),
		'categories' => sp_events_categories(),
		// Linking an event to a booking: offered only where Booking is on. The dropdown lists every
		// class / session booking; editing a booking's dates needs the booking-settings permission.
		'booking_on'    => function_exists( 'sp_book_events_ready' ) && sp_book_events_ready(),
		'book_services' => function_exists( 'sp_book_linkable_services' ) && function_exists( 'sp_book_events_ready' ) && sp_book_events_ready() ? sp_book_linkable_services() : [],
		'book_admin'    => sp_events_can_edit_bookings(),
	] );
}

/** Can the current person edit booking settings (a linked booking's dates)? Never sends a reply. */
function sp_events_can_edit_bookings(): bool {
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'booking' ) ) return false;
	if ( ! site_pulse_is_impersonating() && in_array( 'administrator', (array) wp_get_current_user()->roles, true ) ) return true;
	return site_pulse_user_can( site_pulse_effective_user_id(), 'manage_booking_services' );
}

add_action( 'wp_ajax_site_pulse_events_get', 'sp_events_ajax_get' );
function sp_events_ajax_get(): void {
	sp_events_gate( 'sp_events_can_view', 'You do not have access to events.' );
	$id = (int) ( $_POST['id'] ?? 0 );
	$p  = get_post( $id );
	if ( ! $p || $p->post_type !== 'events' || $p->post_status === 'trash' ) wp_send_json_error( [ 'message' => 'That event no longer exists.' ] );

	$rule = get_field( 'event_recurrence', $id );
	$cats = wp_get_post_terms( $id, 'event-cats', [ 'fields' => 'ids' ] );
	$thumb_id = (int) get_post_thumbnail_id( $id );

	wp_send_json_success( [ 'event' => array_merge( sp_events_format_row( $p ), [
		'content'     => (string) $p->post_content,
		'excerpt'     => (string) $p->post_excerpt,
		'date_type'   => in_array( get_post_meta( $id, 'date_type', true ), [ 'recurring', 'period' ], true ) ? get_post_meta( $id, 'date_type', true ) : 'specific',
		'blocks'      => sp_events_blocks( $id ),
		'recurrence'  => is_array( $rule ) ? $rule : null,
		'venue_link'  => (string) get_post_meta( $id, 'venue_link', true ),
		'categories'  => is_wp_error( $cats ) ? [] : array_map( 'intval', $cats ),
		'photo'       => $thumb_id ? [ 'id' => $thumb_id, 'url' => (string) wp_get_attachment_image_url( $thumb_id, 'medium' ) ] : null,
		'wp_edit_url' => (string) get_edit_post_link( $id, 'raw' ),
		'book_service'=> function_exists( 'sp_book_event_service' ) && ( $svc = sp_book_event_service( $id ) ) ? (int) $svc['id'] : 0,
		'book_dates'  => function_exists( 'sp_book_event_dates' ) ? sp_book_event_dates( $id ) : [],
	] ) ] );
}


/*--------------------------------------------------------------
# AJAX — Save / Trash
--------------------------------------------------------------*/

/**
 * The one save path for an event written from outside the WordPress editor. Fields go through
 * update_field() BY KEY so the custom field types' own update_value() sanitisers run (the date-blocks
 * and recurrence fields store one JSON string each), then the two derivations the editor's
 * acf/save_post chain would have run. wp_update_post() alone never fires acf/save_post.
 *
 * Returns the post id or a WP_Error.
 */
function sp_events_save( array $d, int $id = 0, int $author = 0 ) {
	$title = trim( (string) ( $d['title'] ?? '' ) );
	if ( $title === '' ) return new WP_Error( 'title', 'Give the event a title.' );

	$date_type = in_array( $d['date_type'] ?? '', [ 'recurring', 'period' ], true ) ? $d['date_type'] : 'specific';
	$blocks    = is_array( $d['blocks'] ?? null ) ? $d['blocks'] : [];
	$rule      = is_array( $d['recurrence'] ?? null ) ? $d['recurrence'] : [];
	// Linked to a booking: the dates come from the booking (copied after the save), not the form.
	$linked    = ! empty( $d['book_service'] );
	if ( $linked ) { $date_type = 'specific'; $blocks = []; }
	if ( ! $linked && $date_type !== 'recurring' && ! array_filter( $blocks, static fn( $b ) => ! empty( $b['s_date'] ) ) ) {
		return new WP_Error( 'dates', 'Add at least one date.' );
	}
	if ( $date_type === 'recurring' && empty( $rule['start'] ) ) return new WP_Error( 'dates', 'Pick the date the event starts repeating.' );
	if ( $date_type === 'recurring' && ( $rule['freq'] ?? 'weekly' ) === 'weekly' && empty( $rule['weekdays'] ) ) {
		return new WP_Error( 'dates', 'Pick at least one day of the week it repeats on.' );
	}

	$post = [
		'post_type'    => 'events',
		'post_title'   => $title,
		'post_status'  => ( $d['status'] ?? 'publish' ) === 'draft' ? 'draft' : 'publish',
	];
	// Only what was sent — a caller that leaves the description out must not blank it.
	if ( array_key_exists( 'content', $d ) ) $post['post_content'] = wp_kses_post( (string) $d['content'] );
	if ( array_key_exists( 'excerpt', $d ) ) $post['post_excerpt'] = sanitize_textarea_field( (string) $d['excerpt'] );
	if ( $id ) {
		$post['ID'] = $id;
		$res = wp_update_post( wp_slash( $post ), true );
	} else {
		$post['post_author'] = $author ?: get_current_user_id();
		$res = wp_insert_post( wp_slash( $post ), true );
	}
	if ( is_wp_error( $res ) ) return $res;
	$id = (int) $res;

	update_field( 'field_bp_date_type', $date_type, $id );
	// The dates field is written either way: a repeating event's dates are generated into it just below,
	// but ACF only knows it's a date_blocks field (and decodes it on read) once its reference meta exists.
	// A linked event's dates are left for the booking to fill (sp_events_saved → sp_book_event_link).
	if ( ! $linked ) update_field( 'field_bp_event_dates', $date_type === 'recurring' ? [] : $blocks, $id );
	if ( $date_type === 'recurring' ) {
		update_field( 'field_bp_event_recurrence', $rule, $id );
	}
	update_field( 'field_6478d5ds03gwk', sanitize_text_field( (string) ( $d['venue'] ?? '' ) ), $id );
	update_field( 'field_6478d5eg35acx', sanitize_text_field( (string) ( $d['location'] ?? '' ) ), $id );
	update_field( 'field_6478d5hr36erd', esc_url_raw( (string) ( $d['venue_link'] ?? '' ) ), $id );

	// The editor's acf/save_post chain: regenerate a recurring event's blocks, then the legacy span.
	bp_event_regenerate( $id );
	bp_event_sync_span( $id );

	// Tags: only `featured` is chosen here. `expired` comes off once the event has a date still to come
	// (the front end re-adds it — and drafts the post — the day the last one passes). Every other tag
	// someone added in wp-admin is kept.
	$tags = wp_get_post_terms( $id, 'event-tags', [ 'fields' => 'slugs' ] );
	$tags = is_wp_error( $tags ) ? [] : $tags;
	$tags = array_values( array_diff( $tags, [ 'featured' ] ) );
	if ( ! empty( $d['featured'] ) ) $tags[] = 'featured';
	$row = sp_events_format_row( get_post( $id ) );
	if ( ! $row['past'] ) $tags = array_values( array_diff( $tags, [ 'expired' ] ) );
	wp_set_object_terms( $id, $tags, 'event-tags', false );

	if ( isset( $d['categories'] ) ) {
		wp_set_object_terms( $id, array_map( 'intval', (array) $d['categories'] ), 'event-cats', false );
	}

	// Featured image: parent a fresh upload to the event, then set (or clear) the thumbnail.
	if ( array_key_exists( 'photo_id', $d ) ) {
		$aid = (int) $d['photo_id'];
		if ( $aid ) {
			if ( ! (int) get_post_field( 'post_parent', $aid ) ) wp_update_post( [ 'ID' => $aid, 'post_parent' => $id ] );
			set_post_thumbnail( $id, $aid );
		} else {
			delete_post_thumbnail( $id );
		}
	}

	/** Fires after an event is saved from Site Pulse (wp-admin saves fire acf/save_post instead). */
	do_action( 'sp_events_saved', $id, $d );
	return $id;
}

add_action( 'wp_ajax_site_pulse_events_save', 'sp_events_ajax_save' );
function sp_events_ajax_save(): void {
	$me = sp_events_gate( 'sp_events_can_manage', 'You do not have permission to change events.' );
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( $id && get_post_type( $id ) !== 'events' ) wp_send_json_error( [ 'message' => 'That event no longer exists.' ] );

	$json = static function ( string $key ) {
		$v = json_decode( wp_unslash( (string) ( $_POST[ $key ] ?? '' ) ), true );
		return is_array( $v ) ? $v : [];
	};

	// A photo id from the browser counts only if it's already this event's, or an unattached upload
	// the same person just made — never someone else's media.
	$photo = (int) ( $_POST['photo_id'] ?? 0 );
	if ( $photo ) {
		$att = get_post( $photo );
		$ok  = $att && $att->post_type === 'attachment' && (
			( $id && (int) $att->post_parent === $id ) ||
			( (int) $att->post_parent === 0 && (int) $att->post_author === $me ) ||
			( $id && (int) get_post_thumbnail_id( $id ) === $photo )
		);
		if ( ! $ok ) $photo = 0;
	}

	$data = [
		'title'      => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
		'content'    => wp_unslash( (string) ( $_POST['content'] ?? '' ) ),
		'excerpt'    => wp_unslash( (string) ( $_POST['excerpt'] ?? '' ) ),
		'status'     => sanitize_key( $_POST['status'] ?? 'publish' ),
		'date_type'  => sanitize_key( $_POST['date_type'] ?? 'specific' ),
		'blocks'     => $json( 'blocks' ),
		'recurrence' => $json( 'recurrence' ),
		'venue'      => wp_unslash( (string) ( $_POST['venue'] ?? '' ) ),
		'location'   => wp_unslash( (string) ( $_POST['location'] ?? '' ) ),
		'venue_link' => wp_unslash( (string) ( $_POST['venue_link'] ?? '' ) ),
		'featured'   => ! empty( $_POST['featured'] ),
		'categories' => $json( 'categories' ),
		'photo_id'   => $photo,
		'book_service' => (int) ( $_POST['book_service'] ?? 0 ),   // the linked booking (includes-site-pulse-booking-events.php)
	];
	// A booking already linked to another event is refused BEFORE anything is saved.
	if ( $data['book_service'] && function_exists( 'sp_book_get_service' ) && ( $svc = sp_book_get_service( $data['book_service'] ) ) ) {
		$other = sp_book_service_event( $svc );
		if ( $other && $other !== $id ) wp_send_json_error( [ 'message' => sprintf( '"%s" is already linked to the event "%s". A booking can be linked to one event.', $svc['name'], html_entity_decode( get_the_title( $other ), ENT_QUOTES ) ) ] );
	}

	$res = sp_events_save( $data, $id, $me );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );

	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( $id ? 'event_update' : 'event_add', ( $id ? 'Updated' : 'Added' ) . ' event: ' . $data['title'] );
	wp_send_json_success( [
		'event'   => sp_events_format_row( get_post( $res ) ),
		'message' => $id ? 'Event updated.' : ( $data['status'] === 'draft' ? 'Event saved as a draft.' : 'Event added — it\'s on the calendar.' ),
	] );
}

/**
 * Duplicate an event — the next run of a class. The copy is a DRAFT with the same description, photo,
 * venue and categories. A linked event's booking is copied too (settings only: price, seats, wording —
 * no dates, no sign-ups) and linked to the copy, so each run keeps its own sign-ups. An unlinked
 * event's own dates are copied as they are. Returns the new id or a WP_Error.
 */
function sp_events_duplicate( int $id, int $author = 0 ) {
	$p = get_post( $id );
	if ( ! $p || $p->post_type !== 'events' ) return new WP_Error( 'gone', 'That event no longer exists.' );
	$new = wp_insert_post( wp_slash( [
		'post_type'    => 'events',
		'post_status'  => 'draft',
		'post_title'   => $p->post_title . ' (copy)',
		'post_content' => $p->post_content,
		'post_excerpt' => $p->post_excerpt,
		'post_author'  => $author ?: get_current_user_id(),
	] ), true );
	if ( is_wp_error( $new ) ) return $new;

	// Venue fields + (for an event with its own dates) the date fields, with their ACF reference keys so
	// get_field() reads them. A linked event's dates come from its copied booking instead.
	$linked = function_exists( 'sp_book_event_service' ) && sp_book_event_service( $id );
	$keys   = [ 'venue', 'location', 'venue_link' ];
	if ( ! $linked ) $keys = array_merge( $keys, [ 'date_type', 'event_dates', 'event_recurrence', 'start_date', 'end_date', 'start_time', 'end_time' ] );
	foreach ( $keys as $k ) {
		$v = get_post_meta( $id, $k, true );
		if ( $v !== '' ) update_post_meta( $new, $k, wp_slash( $v ) );
		$ref = get_post_meta( $id, '_' . $k, true );
		if ( $ref !== '' ) update_post_meta( $new, '_' . $k, $ref );
	}
	$cats = wp_get_post_terms( $id, 'event-cats', [ 'fields' => 'ids' ] );
	if ( ! is_wp_error( $cats ) && $cats ) wp_set_object_terms( $new, array_map( 'intval', $cats ), 'event-cats', false );
	$thumb = (int) get_post_thumbnail_id( $id );
	if ( $thumb ) set_post_thumbnail( $new, $thumb );

	if ( function_exists( 'sp_book_event_service' ) && ( $svc = sp_book_event_service( $id ) ) ) {
		$copy = sp_book_duplicate_service( (int) $svc['id'] );
		if ( $copy ) {
			sp_book_event_link( (int) $new, $copy );
			update_post_meta( $new, 'book_service', $copy );
		}
	}
	return (int) $new;
}

add_action( 'wp_ajax_site_pulse_events_duplicate', 'sp_events_ajax_duplicate' );
function sp_events_ajax_duplicate(): void {
	$me  = sp_events_gate( 'sp_events_can_manage', 'You do not have permission to change events.' );
	$res = sp_events_duplicate( (int) ( $_POST['id'] ?? 0 ), $me );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'event_add', 'Duplicated event: ' . get_the_title( $res ) );
	wp_send_json_success( [ 'id' => $res ] );
}

// Delete = the WordPress trash (recoverable there).
add_action( 'wp_ajax_site_pulse_events_trash', 'sp_events_ajax_trash' );
function sp_events_ajax_trash(): void {
	sp_events_gate( 'sp_events_can_manage', 'You do not have permission to delete events.' );
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $id || get_post_type( $id ) !== 'events' ) wp_send_json_error( [ 'message' => 'That event no longer exists.' ] );

	$title = get_the_title( $id );
	if ( ! wp_trash_post( $id ) ) wp_send_json_error( [ 'message' => 'Could not delete that event.' ] );
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'event_delete', 'Deleted event: ' . $title );
	wp_send_json_success( [ 'id' => $id ] );
}


/*--------------------------------------------------------------
# AJAX — Photo Upload
--------------------------------------------------------------*/

// One photo → a media-library attachment, left unattached until the event is saved (which parents it
// and makes it the featured image). The framework's upload filters still apply (EXIF rotation, WebP).
add_action( 'wp_ajax_site_pulse_events_upload', 'sp_events_ajax_upload' );
function sp_events_ajax_upload(): void {
	$me = sp_events_gate( 'sp_events_can_manage', 'You do not have permission to change events.' );

	if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ?? '' ) ) {
		wp_send_json_error( [ 'message' => 'No file received.' ] );
	}
	$file = $_FILES['file'];
	if ( (int) $file['size'] > 25 * 1024 * 1024 ) wp_send_json_error( [ 'message' => 'Photo is too large (max 25 MB).' ] );

	$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Please upload a photo (jpg, png, webp or heic).' ] );
	}
	$check = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'] );
	if ( empty( $check['type'] ) || strpos( (string) $check['type'], 'image/' ) !== 0 ) {
		wp_send_json_error( [ 'message' => 'That file is not a valid image.' ] );
	}

	// The framework's rename filter reads $_REQUEST['post_id']; only a real event counts.
	$post_id = (int) ( $_POST['post_id'] ?? 0 );
	if ( $post_id && get_post_type( $post_id ) !== 'events' ) $_REQUEST['post_id'] = 0;

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$moved = wp_handle_upload( $file, [ 'test_form' => false ] );
	if ( empty( $moved['file'] ) || ! empty( $moved['error'] ) ) {
		wp_send_json_error( [ 'message' => $moved['error'] ?? 'Upload failed.' ] );
	}

	$aid = wp_insert_attachment( [
		'post_mime_type' => $moved['type'],
		'post_title'     => sanitize_text_field( pathinfo( (string) $file['name'], PATHINFO_FILENAME ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_author'    => $me,
	], $moved['file'] );
	if ( is_wp_error( $aid ) || ! $aid ) wp_send_json_error( [ 'message' => 'Could not save the photo.' ] );
	wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $moved['file'] ) );

	wp_send_json_success( [ 'id' => (int) $aid, 'url' => (string) ( wp_get_attachment_image_url( $aid, 'medium' ) ?: $moved['url'] ) ] );
}


/*--------------------------------------------------------------
# Migrations (auto-enable + caps seed)
--------------------------------------------------------------*/

/**
 * One-time: switch the Events module on for installs that already run Events (the event_calendar
 * option). Runs after the module seed (init 15) so a fresh install's seed can't overwrite it; never
 * overrides a deliberate off. An install without Events is re-checked on later loads, so installing
 * Events afterwards still flips it on. Mirrors sp_jobsites_migrate_enable().
 */
add_action( 'init', 'sp_events_migrate_enable', 20 );
function sp_events_migrate_enable(): void {
	if ( get_option( 'site_pulse_events_enabled_migrated' ) ) return;
	if ( ! function_exists( 'bp_module_on' ) || ! bp_module_on( get_option( 'event_calendar' ) ) ) return;

	$state = json_decode( site_pulse_get_setting( 'modules', '{}' ), true );
	if ( ! is_array( $state ) ) $state = [];
	if ( ! array_key_exists( 'events', $state ) ) {
		$state['events'] = '1';
		site_pulse_set_setting( 'modules', wp_json_encode( $state ) );
	}
	update_option( 'site_pulse_events_enabled_migrated', '1' );
}

/**
 * One-time, once the module is on: owner/admin/system-admin manage events; the other staff tiers can
 * see them. Insert-only per role; self-guarded. Mirrors sp_jobsites_migrate_caps().
 */
add_action( 'init', 'sp_events_migrate_caps', 25 );
function sp_events_migrate_caps(): void {
	if ( get_option( 'site_pulse_events_caps_seeded' ) ) return;
	if ( ! function_exists( 'site_pulse_module_on' ) || ! site_pulse_module_on( 'events' ) ) return;

	global $wpdb;
	$all    = [ 'view_events', 'manage_events' ];
	$view   = [ 'view_events' ];
	$grants = [
		'owner'             => $all,
		'admin'             => $all,
		'system-admin'      => $all,
		'supervisor'        => $view,
		'manager'           => $view,
		'non-store-manager' => $view,
		'user'              => $view,
	];
	foreach ( $grants as $slug => $caps ) {
		$role = site_pulse_get_role_by_slug( $slug );
		if ( ! $role ) continue;
		$have   = json_decode( $role['capabilities'], true ) ?: [];
		$merged = array_values( array_unique( array_merge( $have, $caps ) ) );
		if ( $merged !== $have ) {
			$wpdb->update( site_pulse_table( 'roles' ), [ 'capabilities' => wp_json_encode( $merged ) ], [ 'id' => (int) $role['id'] ] );
		}
	}
	update_option( 'site_pulse_events_caps_seeded', '1' );
}
