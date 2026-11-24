<?php
/* Battle Plan Web Design - Site Pulse: My Profile (Settings → Profile)

   Every Site Pulse user can edit their OWN account — the fields WordPress's own Profile screen edits
   (first/last name, nickname, the name shown publicly, email, website, bio) and their password —
   without going near wp-admin. Writes go straight to the WordPress user, so the login, the author
   name on posts and every other WP surface change with it.

   Safety rules:
   - Always the REAL logged-in user. While a god is impersonating ("viewing as"), the screen shows
     that person's details read-only and nothing saves — impersonation must never change someone's
     email or password.
   - A password change needs the CURRENT password (a phone left logged in can't be used to lock its
     owner out), and wrong guesses are throttled. wp_set_password() logs every session out, so a
     fresh auth cookie is issued to keep this one signed in.
   - Field names avoid "password" — WP Engine's WAF strips POST fields with that name.
   - An email change goes through wp_update_user(), so WordPress emails the OLD address about it.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Read
# Save details
# Change password
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Read
--------------------------------------------------------------*/

/** The "Display name publicly as" choices WordPress offers for this user, de-duplicated. */
function sp_profile_display_choices( WP_User $u ): array {
	$first = trim( (string) $u->first_name );
	$last  = trim( (string) $u->last_name );
	$opts  = [ $u->user_login, (string) $u->nickname ];
	if ( $first !== '' ) $opts[] = $first;
	if ( $last !== '' ) $opts[] = $last;
	if ( $first !== '' && $last !== '' ) { $opts[] = $first . ' ' . $last; $opts[] = $last . ' ' . $first; }
	$opts[] = (string) $u->display_name;
	return array_values( array_unique( array_filter( array_map( 'trim', $opts ), 'strlen' ) ) );
}

add_action( 'wp_ajax_site_pulse_profile_get', 'sp_profile_ajax_get' );
function sp_profile_ajax_get(): void {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	// Shows the effective user (so a god viewing-as sees what that person sees), but locked.
	$uid = site_pulse_effective_user_id();
	$u   = get_userdata( $uid );
	if ( ! $u ) wp_send_json_error( [ 'message' => 'Your account could not be found.' ] );

	wp_send_json_success( [
		'username'     => $u->user_login,
		'first_name'   => (string) $u->first_name,
		'last_name'    => (string) $u->last_name,
		'nickname'     => (string) $u->nickname,
		'display_name' => (string) $u->display_name,
		'display_opts' => sp_profile_display_choices( $u ),
		'email'        => (string) $u->user_email,
		'url'          => (string) $u->user_url,
		'bio'          => (string) $u->description,
		'photo'        => sp_profile_photo_url( $uid ),
		'dir_photo'    => function_exists( 'sp_directory_user_photo' ) ? sp_directory_user_photo( $uid ) : '',
		'read_only'    => site_pulse_is_impersonating(),
	] );
}


/*--------------------------------------------------------------
# Profile photo
--------------------------------------------------------------
The person's own picture (user meta sp_profile_photo = URL, sp_profile_photo_file = path). It wins
everywhere a face is shown — chat, pickers, action items AND the Company Directory card linked to
them. With none set, the Directory photo is used exactly as before (site_pulse_user_photo()).
*/

function sp_profile_photo_url( int $uid ): string {
	return $uid ? (string) get_user_meta( $uid, 'sp_profile_photo', true ) : '';
}

/** Every user with a profile photo: [ user_id => url ] — one query per request. */
function sp_profile_photos_all(): array {
	static $all = null;
	if ( null !== $all ) return $all;
	global $wpdb;
	$all = [];
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> ''", 'sp_profile_photo' ), ARRAY_A ) ?: [] as $r ) {
		$all[ (int) $r['user_id'] ] = (string) $r['meta_value'];
	}
	return $all;
}

function sp_profile_photo_delete_file( int $uid ): void {
	$old = (string) get_user_meta( $uid, 'sp_profile_photo_file', true );
	$up  = wp_upload_dir();
	if ( $old && strpos( wp_normalize_path( $old ), wp_normalize_path( $up['basedir'] ) . '/site-pulse-profile/' ) === 0 && file_exists( $old ) ) @unlink( $old );
}

add_action( 'wp_ajax_site_pulse_profile_photo_upload', 'sp_profile_ajax_photo_upload' );
function sp_profile_ajax_photo_upload(): void {
	$uid = sp_profile_save_gate();
	if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ?? '' ) ) wp_send_json_error( [ 'message' => 'No file received.' ] );
	$file = $_FILES['file'];
	if ( (int) $file['size'] > 10 * 1024 * 1024 ) wp_send_json_error( [ 'message' => 'That picture is too large (max 10 MB).' ] );
	$check = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'] );
	if ( empty( $check['ext'] ) || ! in_array( $check['ext'], [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Please choose a JPG, PNG, GIF or WebP picture.' ] );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$redir = function ( $dirs ) {
		$dirs['subdir'] = '/site-pulse-profile';
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	};
	add_filter( 'upload_dir', $redir );
	$file['name'] = 'user-' . $uid . '-' . wp_generate_password( 10, false, false ) . '.' . $check['ext'];
	$moved = wp_handle_upload( $file, [ 'test_form' => false ] );
	remove_filter( 'upload_dir', $redir );
	if ( empty( $moved['file'] ) || ! empty( $moved['error'] ) ) wp_send_json_error( [ 'message' => $moved['error'] ?? 'Upload failed.' ] );

	// Square-crop to 400×400 (faces are shown in circles) and fix phone-photo rotation.
	$path = $moved['file'];
	$url  = $moved['url'];
	$ed   = wp_get_image_editor( $path );
	if ( ! is_wp_error( $ed ) ) {
		if ( method_exists( $ed, 'maybe_exif_rotate' ) ) $ed->maybe_exif_rotate();
		$ed->resize( 400, 400, true );
		$saved = $ed->save( $path );
		if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) && $saved['path'] !== $path ) {
			@unlink( $path );
			$url  = dirname( $url ) . '/' . basename( $saved['path'] );
			$path = $saved['path'];
		}
	}

	sp_profile_photo_delete_file( $uid );
	update_user_meta( $uid, 'sp_profile_photo', esc_url_raw( $url . '?v=' . time() ) );
	update_user_meta( $uid, 'sp_profile_photo_file', $path );
	wp_send_json_success( [ 'photo' => sp_profile_photo_url( $uid ) ] );
}

add_action( 'wp_ajax_site_pulse_profile_photo_remove', 'sp_profile_ajax_photo_remove' );
function sp_profile_ajax_photo_remove(): void {
	$uid = sp_profile_save_gate();
	sp_profile_photo_delete_file( $uid );
	delete_user_meta( $uid, 'sp_profile_photo' );
	delete_user_meta( $uid, 'sp_profile_photo_file' );
	wp_send_json_success( [ 'photo' => '', 'dir_photo' => function_exists( 'sp_directory_user_photo' ) ? sp_directory_user_photo( $uid ) : '' ] );
}


/*--------------------------------------------------------------
# Save details
--------------------------------------------------------------*/

/** Common gate for the two saves: nonce, logged in, and never while impersonating. Returns the user id. */
function sp_profile_save_gate(): int {
	check_ajax_referer( 'site_pulse_nonce', 'nonce' );
	$uid = get_current_user_id();
	if ( ! $uid ) wp_send_json_error( [ 'message' => 'Please sign in again.' ] );
	if ( site_pulse_is_impersonating() ) {
		wp_send_json_error( [ 'message' => 'You\'re viewing as someone else. Stop viewing as them to change your own profile — their profile can\'t be changed from here.' ] );
	}
	return $uid;
}

add_action( 'wp_ajax_site_pulse_profile_save', 'sp_profile_ajax_save' );
function sp_profile_ajax_save(): void {
	$uid = sp_profile_save_gate();
	$u   = get_userdata( $uid );

	$first    = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$last     = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
	$nickname = sanitize_text_field( wp_unslash( $_POST['nickname'] ?? '' ) );
	$display  = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
	$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$url      = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
	$bio      = sanitize_textarea_field( wp_unslash( $_POST['bio'] ?? '' ) );

	if ( $nickname === '' ) wp_send_json_error( [ 'message' => 'Nickname can\'t be blank (WordPress needs one) — use your first name if you like.' ] );
	if ( ! is_email( $email ) ) wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
	$owner = email_exists( $email );
	if ( $owner && (int) $owner !== $uid ) wp_send_json_error( [ 'message' => 'That email address is already used by another account.' ] );

	// The public name must be one of the choices WordPress would offer for the NEW names.
	$probe = clone $u;
	$probe->first_name = $first; $probe->last_name = $last; $probe->nickname = $nickname;
	if ( ! in_array( $display, sp_profile_display_choices( $probe ), true ) ) $display = $nickname;

	$res = wp_update_user( [
		'ID'           => $uid,
		'first_name'   => $first,
		'last_name'    => $last,
		'nickname'     => $nickname,
		'display_name' => $display,
		'user_email'   => $email,
		'user_url'     => $url,
		'description'  => $bio,
	] );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );

	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'profile_updated', 'Updated their own profile' );
	$fresh = get_userdata( $uid );
	wp_send_json_success( [
		'message'      => $email !== $u->user_email ? 'Profile saved. A note about the email change was sent to your old address.' : 'Profile saved.',
		'display_name' => (string) $fresh->display_name,
		'display_opts' => sp_profile_display_choices( $fresh ),
	] );
}


/*--------------------------------------------------------------
# Change password
--------------------------------------------------------------*/

add_action( 'wp_ajax_site_pulse_profile_password', 'sp_profile_ajax_password' );
function sp_profile_ajax_password(): void {
	$uid = sp_profile_save_gate();
	$u   = get_userdata( $uid );

	// Five wrong current-password tries per 15 minutes, per account.
	$key   = 'sp_profile_pw_tries_' . $uid;
	$tries = (int) get_transient( $key );
	if ( $tries >= 5 ) wp_send_json_error( [ 'message' => 'Too many tries. Wait 15 minutes, or use "Forgot password" on the sign-in screen.' ] );

	$current = (string) wp_unslash( $_POST['sp_cur_pass'] ?? '' );
	$new     = (string) wp_unslash( $_POST['sp_new_pass'] ?? '' );
	$new2    = (string) wp_unslash( $_POST['sp_new_pass2'] ?? '' );

	if ( ! wp_check_password( $current, $u->user_pass, $uid ) ) {
		set_transient( $key, $tries + 1, 15 * MINUTE_IN_SECONDS );
		wp_send_json_error( [ 'message' => 'Your current password isn\'t right.' ] );
	}
	if ( strlen( $new ) < 8 )   wp_send_json_error( [ 'message' => 'Your new password must be at least 8 characters.' ] );
	if ( $new !== $new2 )       wp_send_json_error( [ 'message' => 'The two new passwords don\'t match.' ] );
	if ( hash_equals( $current, $new ) ) wp_send_json_error( [ 'message' => 'That\'s the password you already have — choose a new one.' ] );
	if ( function_exists( 'site_pulse_default_password' ) && site_pulse_default_password() !== '' && hash_equals( site_pulse_default_password(), $new ) ) {
		wp_send_json_error( [ 'message' => 'Please choose a password of your own, not the temporary one.' ] );
	}

	delete_transient( $key );
	// wp_set_password() ends every session (good: other devices are signed out); re-issue this one.
	wp_set_password( $new, $uid );
	update_user_meta( $uid, 'site_pulse_force_pw', '0' );
	update_user_meta( $uid, 'site_pulse_first_login_done', '1' );
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true );
	if ( function_exists( 'site_pulse_log' ) ) site_pulse_log( 'password_changed', 'Changed their password from Settings → Profile' );

	wp_send_json_success( [ 'message' => 'Password changed. You\'re still signed in here; any other devices will need the new password.' ] );
}
