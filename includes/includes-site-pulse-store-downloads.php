<?php
/* Battle Plan Web Design — Site Pulse: Store Downloads

/*--------------------------------------------------------------
Files a buyer receives: a curriculum PDF, a manual, an audio course. An Extras
feature — off until switched on, invisible everywhere while off.

FILES BELONG TO THE PRODUCT, NOT THE ORDER. A buyer is entitled to whatever is
attached to what they bought, so a corrected curriculum uploaded next year
reaches everyone who already bought it, with no re-send.

THE FILE'S REAL ADDRESS IS NEVER GIVEN OUT. A buyer gets a link to THIS SITE —
?sp_store_dl=… signed with an HMAC over the order and the file — and the site
checks the signature and that the order is still paid (a refund revokes it)
before streaming the file itself. The file sits in a private folder under
uploads with a random, unguessable path, and deliberately NOT in the media
library, so it is in no sitemap, gallery or attachment page.

ON WP ENGINE THAT FOLDER IS NOT LOCKED BY .htaccess. Static files are served by
Nginx before PHP or Apache rules are consulted. The random path is what keeps a
file private by default; for a hard lock, ask WP Engine support to deny
/wp-content/uploads/sp-store-private/ at the server — a one-line request. The
.htaccess written here still protects any host that honours it.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Switch & Storage
# A Product's Files
# Who May Download
# Delivering a File
# Where Buyers Find Them
# AJAX — Product Editor
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# Switch & Storage
--------------------------------------------------------------*/

function sp_store_downloads_on(): bool {
	return function_exists( 'sp_store_active' ) && sp_store_active() && sp_store_get_setting( 'feature_downloads', '' ) === '1';
}

/** The file types a product may carry. Documents, archives, audio, video, e-books. */
function sp_store_download_types(): array {
	return [ 'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'epub', 'mp3', 'm4a', 'wav', 'mp4', 'mov', 'jpg', 'jpeg', 'png' ];
}

/**
 * The private folder, created on first use with every lock this host might honour:
 * a deny-all .htaccess (Apache), an empty index.php (no listing), and a random
 * directory name under it that is generated once and never shown to anyone.
 */
function sp_store_private_dir(): string {
	$uploads = wp_get_upload_dir();
	$base    = trailingslashit( $uploads['basedir'] ) . 'sp-store-private';
	$secret  = get_option( 'sp_store_private_slug' );
	if ( ! $secret ) {
		$secret = bin2hex( random_bytes( 16 ) );
		update_option( 'sp_store_private_slug', $secret, false );
	}
	$dir = $base . '/' . $secret;

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $base . '/.htaccess', "Require all denied\nDeny from all\n" );
		@file_put_contents( $base . '/index.php', "<?php // Silence.\n" );
		@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" );
	}
	return $dir;
}


/*--------------------------------------------------------------
# A Product's Files
--------------------------------------------------------------*/

/** A product's files: [ [ key, name, file, size, added ], … ]. `file` is relative to the private dir. */
function sp_store_product_downloads( int $product_id ): array {
	$raw = get_post_meta( $product_id, '_sp_store_downloads', true );
	return is_array( $raw ) ? array_values( array_filter( $raw, static fn( $d ) => ! empty( $d['key'] ) && ! empty( $d['file'] ) ) ) : [];
}

function sp_store_set_product_downloads( int $product_id, array $files ): void {
	if ( $files ) update_post_meta( $product_id, '_sp_store_downloads', array_values( $files ) );
	else          delete_post_meta( $product_id, '_sp_store_downloads' );
}

/**
 * Take an uploaded file into a product's private files.
 * @return array|WP_Error the new file entry
 */
function sp_store_add_product_download( int $product_id, array $upload ) {
	if ( get_post_type( $product_id ) !== SITE_PULSE_STORE_CPT ) return new WP_Error( 'sp_store_dl', 'Save the product first, then add its files.' );
	if ( empty( $upload['tmp_name'] ) || ! is_uploaded_file( $upload['tmp_name'] ) ) return new WP_Error( 'sp_store_dl', 'No file received.' );

	$name = sanitize_file_name( (string) $upload['name'] );
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, sp_store_download_types(), true ) ) {
		return new WP_Error( 'sp_store_dl', 'That type of file cannot be sold as a download (.' . $ext . ').' );
	}

	// A random prefix: the stored name is never guessable even if the folder is found.
	$stored = bin2hex( random_bytes( 12 ) ) . '-' . $name;
	if ( ! @move_uploaded_file( $upload['tmp_name'], sp_store_private_dir() . '/' . $stored ) ) {
		return new WP_Error( 'sp_store_dl', 'The file could not be saved. It may be larger than this server allows.' );
	}

	$entry = [
		'key'   => bin2hex( random_bytes( 8 ) ),
		'name'  => $name,
		'file'  => $stored,
		'size'  => (int) $upload['size'],
		'added' => current_time( 'mysql', true ),
	];
	$files   = sp_store_product_downloads( $product_id );
	$files[] = $entry;
	sp_store_set_product_downloads( $product_id, $files );
	return $entry;
}

/**
 * Copy a file that is already on this server (a migration's old upload) into a
 * product's private files. COPIES rather than moves: the original stays until the
 * migration has been checked, and deleting it is a deliberate later step.
 * @return array|WP_Error
 */
function sp_store_add_product_download_from_path( int $product_id, string $path, string $name = '' ) {
	if ( ! is_file( $path ) || ! is_readable( $path ) ) return new WP_Error( 'sp_store_dl', 'File not found: ' . basename( $path ) );
	$name = sanitize_file_name( $name !== '' ? $name : basename( $path ) );
	if ( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) === '' ) $name .= '.' . strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	$stored = bin2hex( random_bytes( 12 ) ) . '-' . $name;
	if ( ! @copy( $path, sp_store_private_dir() . '/' . $stored ) ) return new WP_Error( 'sp_store_dl', 'Could not copy ' . basename( $path ) );

	$entry = [ 'key' => bin2hex( random_bytes( 8 ) ), 'name' => $name, 'file' => $stored, 'size' => (int) filesize( $path ), 'added' => current_time( 'mysql', true ) ];
	$files   = sp_store_product_downloads( $product_id );
	$files[] = $entry;
	sp_store_set_product_downloads( $product_id, $files );
	return $entry;
}

/** Take a file off a product, and off the disk. Buyers' links to it stop working. */
function sp_store_remove_product_download( int $product_id, string $key ): bool {
	$files = sp_store_product_downloads( $product_id );
	$keep  = [];
	$found = false;
	foreach ( $files as $f ) {
		if ( $f['key'] === $key ) {
			$found = true;
			$path  = sp_store_private_dir() . '/' . basename( (string) $f['file'] );
			if ( is_file( $path ) ) @unlink( $path );
			continue;
		}
		$keep[] = $f;
	}
	if ( $found ) sp_store_set_product_downloads( $product_id, $keep );
	return $found;
}

/** "2.4 MB" */
function sp_store_file_size_label( int $bytes ): string {
	return $bytes ? size_format( $bytes, $bytes >= 1048576 ? 1 : 0 ) : '';
}


/*--------------------------------------------------------------
# Who May Download
--------------------------------------------------------------*/

/** The signature on a download link. Bound to the order, the file and the buyer's email. */
function sp_store_download_sig( array $order, string $key ): string {
	return substr( hash_hmac( 'sha256', $order['id'] . '|' . $key . '|' . strtolower( (string) $order['customer_email'] ), wp_salt( 'secure_auth' ) . 'sp_store_dl' ), 0, 32 );
}

/** The link a buyer is given for one file on one order. */
function sp_store_download_url( array $order, string $key ): string {
	return add_query_arg( [
		'sp_store_dl' => $order['number'],
		'f'           => $key,
		'k'           => sp_store_download_sig( $order, $key ),
	], home_url( '/' ) );
}

/** Is this order still entitled to its files? Paid (even partly refunded) — a full refund revokes. */
function sp_store_order_can_download( array $order ): bool {
	return in_array( $order['payment_status'], [ 'paid', 'partially_refunded' ], true ) && $order['status'] !== 'cancelled';
}

/**
 * Every file an order gives, with its link. Empty when the feature is off or the
 * order is no longer entitled — so a refunded order's receipt, re-sent, has none.
 */
function sp_store_order_downloads( array $order ): array {
	if ( ! sp_store_downloads_on() || ! sp_store_order_can_download( $order ) ) return [];
	$out  = [];
	$seen = [];
	foreach ( sp_store_order_items( (int) $order['id'] ) as $item ) {
		$pid = (int) $item['product_id'];
		if ( isset( $seen[ $pid ] ) ) continue;
		$seen[ $pid ] = true;
		foreach ( sp_store_product_downloads( $pid ) as $f ) {
			$out[] = [
				'name'    => (string) $f['name'],
				'size'    => sp_store_file_size_label( (int) $f['size'] ),
				'product' => (string) $item['name'],
				'url'     => sp_store_download_url( $order, (string) $f['key'] ),
			];
		}
	}
	return $out;
}


/*--------------------------------------------------------------
# Delivering a File
--------------------------------------------------------------*/

add_action( 'init', 'sp_store_serve_download', 15 );
function sp_store_serve_download(): void {
	if ( empty( $_GET['sp_store_dl'] ) ) return;
	if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );

	$fail = static function ( string $why, int $code = 403 ) {
		status_header( $code );
		nocache_headers();
		wp_die( esc_html( $why ), 'Download', [ 'response' => $code, 'back_link' => true ] );
	};

	if ( ! sp_store_downloads_on() ) $fail( 'Downloads are not available on this site.', 404 );

	$order = sp_store_get_order_by_number( sanitize_text_field( wp_unslash( (string) $_GET['sp_store_dl'] ) ) );
	$key   = sanitize_key( (string) ( $_GET['f'] ?? '' ) );
	$sig   = sanitize_key( (string) ( $_GET['k'] ?? '' ) );

	if ( ! $order || $key === '' || ! hash_equals( sp_store_download_sig( $order, $key ), $sig ) ) {
		$fail( 'That download link is not valid. Please use the link from your receipt or your account page.' );
	}
	if ( ! sp_store_order_can_download( $order ) ) {
		$fail( 'This order is no longer active, so its downloads are switched off. Please get in touch if that is a mistake.' );
	}

	// The file must still be on something this order bought.
	$file = null;
	foreach ( sp_store_order_items( (int) $order['id'] ) as $item ) {
		foreach ( sp_store_product_downloads( (int) $item['product_id'] ) as $f ) {
			if ( $f['key'] === $key ) { $file = $f; break 2; }
		}
	}
	if ( ! $file ) $fail( 'That file is no longer available. Please get in touch and we will help.', 404 );

	$path = sp_store_private_dir() . '/' . basename( (string) $file['file'] );
	if ( ! is_file( $path ) || ! is_readable( $path ) ) $fail( 'That file could not be found on the server. Please get in touch and we will help.', 404 );

	// A count, for the order window — useful when someone says the link "never worked".
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"UPDATE " . sp_store_table( 'orders' ) . " SET downloads = downloads + 1 WHERE id = %d", (int) $order['id']
	) );

	// Stream it. Every buffer is cleared first, or the framework's own output buffer
	// would hold the whole file in memory before a byte reached the customer.
	while ( ob_get_level() > 0 ) ob_end_clean();
	@set_time_limit( 0 );
	$type = wp_check_filetype( (string) $file['name'] )['type'] ?: 'application/octet-stream';
	nocache_headers();
	header( 'Content-Type: ' . $type );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', (string) $file['name'] ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow' );

	$fh = fopen( $path, 'rb' );
	if ( $fh ) {
		while ( ! feof( $fh ) ) {
			echo fread( $fh, 1048576 );
			flush();
		}
		fclose( $fh );
	}
	exit;
}


/*--------------------------------------------------------------
# Where Buyers Find Them
--------------------------------------------------------------*/

/** The {downloads_block} email token: each file with its link, or nothing. */
function sp_store_downloads_email_block( array $order ): string {
	$files = sp_store_order_downloads( $order );
	if ( ! $files ) return '';
	$out = "Your downloads:\n";
	foreach ( $files as $f ) $out .= '- ' . $f['name'] . ( $f['size'] ? ' (' . $f['size'] . ')' : '' ) . ":\n" . $f['url'] . "\n";
	return $out . "These links keep working, so save this email.\n\n";
}

/** Download links as HTML, for the thank-you page and My Account. */
function sp_store_downloads_html( array $order, string $heading = 'Your downloads' ): string {
	$files = sp_store_order_downloads( $order );
	if ( ! $files ) return '';
	$out = '<div class="store-downloads"><h2>' . esc_html( $heading ) . '</h2><ul>';
	foreach ( $files as $f ) {
		$out .= '<li><a href="' . esc_url( $f['url'] ) . '" rel="nofollow">' . esc_html( $f['name'] ) . '</a>'
			. ( $f['size'] ? ' <span class="store-account-sub">' . esc_html( $f['size'] ) . '</span>' : '' ) . '</li>';
	}
	return $out . '</ul></div>';
}


/*--------------------------------------------------------------
# AJAX — Product Editor
--------------------------------------------------------------*/

/**
 * Files are added and removed immediately, not on the product's Save — an upload can
 * take minutes, and holding it hostage to a Save button is how files get lost. That is
 * also why the product has to exist first.
 */
add_action( 'wp_ajax_site_pulse_store_download_add', 'sp_store_ajax_download_add' );
function sp_store_ajax_download_add(): void {
	sp_store_products_gate();
	if ( ! sp_store_downloads_on() ) wp_send_json_error( [ 'message' => 'Downloads are switched off for this store.' ] );
	$res = sp_store_add_product_download( (int) ( $_POST['product_id'] ?? 0 ), (array) ( $_FILES['file'] ?? [] ) );
	if ( is_wp_error( $res ) ) wp_send_json_error( [ 'message' => $res->get_error_message() ] );
	wp_send_json_success( [ 'files' => sp_store_downloads_for_editor( (int) $_POST['product_id'] ) ] );
}

add_action( 'wp_ajax_site_pulse_store_download_remove', 'sp_store_ajax_download_remove' );
function sp_store_ajax_download_remove(): void {
	sp_store_products_gate();
	$pid = (int) ( $_POST['product_id'] ?? 0 );
	sp_store_remove_product_download( $pid, sanitize_key( (string) ( $_POST['key'] ?? '' ) ) );
	wp_send_json_success( [ 'files' => sp_store_downloads_for_editor( $pid ) ] );
}

function sp_store_downloads_for_editor( int $product_id ): array {
	return array_map( static fn( $f ) => [
		'key'  => (string) $f['key'],
		'name' => (string) $f['name'],
		'size' => sp_store_file_size_label( (int) $f['size'] ),
	], sp_store_product_downloads( $product_id ) );
}

/** wp-admin: the product form has to be able to carry a file. */
add_action( 'post_edit_form_tag', 'sp_store_download_form_enctype' );
function sp_store_download_form_enctype( $post ): void {
	if ( $post && $post->post_type === SITE_PULSE_STORE_CPT && sp_store_downloads_on() ) echo ' enctype="multipart/form-data"';
}

/** wp-admin save: an added file, and any ticked for removal. Runs after the product save. */
add_action( 'save_post_' . SITE_PULSE_STORE_CPT, 'sp_store_download_admin_save', 15 );
function sp_store_download_admin_save( int $post_id ): void {
	if ( ! sp_store_downloads_on() || ! isset( $_POST['sp_store_nonce'] ) ) return;
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sp_store_nonce'] ) ), 'sp_store_save_product' ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	foreach ( (array) ( $_POST['sp_store_dl_remove'] ?? [] ) as $key ) {
		sp_store_remove_product_download( $post_id, sanitize_key( (string) $key ) );
	}
	if ( ! empty( $_FILES['sp_store_dl_file']['name'] ) ) {
		$res = sp_store_add_product_download( $post_id, (array) $_FILES['sp_store_dl_file'] );
		if ( is_wp_error( $res ) ) set_transient( 'sp_store_notice_' . $post_id, [ $res->get_error_message() ], 60 );
	}
}
