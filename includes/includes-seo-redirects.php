<?php
/**
 * Battle Plan SEO — Redirects
 * ---------------------------------------------------------------------------
 * Replaces Yoast Premium's redirect manager. Storage is a single non-autoloaded
 * option `bp_seo_redirects` (array of rows). The template_redirect engine runs on
 * the front end; the admin UI (Tools → SEO Redirects) self-gates with is_admin().
 *
 * Row shape:
 *   [ 'from' => '/old-path/', 'to' => '/new-path/', 'type' => 301, 'regex' => false ]
 *
 * Import from Yoast: reads `wpseo-premium-redirects-base` (plain) and
 * `wpseo-premium-redirects-export-regex` (regex) — the two options Yoast Premium
 * stores its redirects in — via the "Import from Yoast" button on the admin page.
 */

if ( ! defined('ABSPATH') ) exit;

const BP_SEO_REDIRECTS_OPT = 'bp_seo_redirects';

/*--------------------------------------------------------------
# Storage helpers
--------------------------------------------------------------*/

function bp_seo_redirects_all(): array {
	$rows = get_option( BP_SEO_REDIRECTS_OPT, [] );
	return is_array($rows) ? $rows : [];
}

function bp_seo_redirects_save( array $rows ): bool {
	// Normalize + de-dupe on `from`.
	$clean = [];
	$seen  = [];
	foreach ( $rows as $r ) {
		$from = bp_seo_norm_path( (string) ( $r['from'] ?? '' ) );
		$to   = trim( (string) ( $r['to'] ?? '' ) );
		if ( $from === '' || $to === '' ) continue;
		if ( isset($seen[$from]) ) continue;
		$seen[$from] = 1;
		$clean[] = [
			'from'  => $from,
			'to'    => $to,
			'type'  => in_array( (int) ( $r['type'] ?? 301 ), [301, 302, 307, 410], true ) ? (int) $r['type'] : 301,
			'regex' => ! empty($r['regex']),
		];
	}
	return update_option( BP_SEO_REDIRECTS_OPT, $clean, false ); // autoload off
}

/** Normalize a path for matching: strip scheme/host, ensure leading slash, drop query for the key. */
function bp_seo_norm_path( string $path ): string {
	$path = trim($path);
	if ( $path === '' ) return '';
	if ( preg_match('#^https?://#i', $path) ) {
		$path = (string) wp_parse_url($path, PHP_URL_PATH);
	}
	$path = strtok($path, '?');                 // ignore query in the stored key
	if ( $path === false ) $path = '';
	if ( $path !== '' && $path[0] !== '/' ) $path = '/' . $path;
	return $path;
}

/*--------------------------------------------------------------
# The engine
--------------------------------------------------------------*/
add_action('template_redirect', 'bp_seo_run_redirects', 1);
function bp_seo_run_redirects() {
	if ( function_exists('bp_seo_defer') && bp_seo_defer() ) return; // Yoast still owns redirects
	if ( is_admin() ) return;

	$rows = bp_seo_redirects_all();
	if ( ! $rows ) return;

	$reqPath = bp_seo_norm_path( $_SERVER['REQUEST_URI'] ?? '' );
	$reqFull = ( $_SERVER['REQUEST_URI'] ?? '' );
	if ( $reqPath === '' ) return;

	foreach ( $rows as $r ) {
		$type = (int) ( $r['type'] ?? 301 );

		if ( ! empty($r['regex']) ) {
			$pattern = '#' . str_replace('#', '\#', (string) $r['from']) . '#';
			if ( @preg_match($pattern, $reqFull) === 1 ) {
				if ( $type === 410 ) { status_header(410); nocache_headers(); exit; }
				$target = preg_replace($pattern, (string) $r['to'], $reqFull);
				bp_seo_do_redirect( $target, $type, $reqPath );
			}
			continue;
		}

		// Exact path match (trailing slash tolerant).
		if ( untrailingslashit($reqPath) === untrailingslashit( (string) $r['from'] ) ) {
			if ( $type === 410 ) { status_header(410); nocache_headers(); exit; }
			bp_seo_do_redirect( (string) $r['to'], $type, $reqPath );
		}
	}
}

function bp_seo_do_redirect( string $target, int $type, string $reqPath ) {
	$target = trim($target);
	if ( $target === '' ) return;
	// Resolve root-relative targets against the site; leave absolute URLs alone.
	$dest = preg_match('#^https?://#i', $target) ? $target : home_url($target);

	// Loop guard: never redirect a path to itself.
	if ( bp_seo_norm_path($dest) === $reqPath ) return;

	wp_redirect( esc_url_raw($dest), in_array($type, [301,302,307], true) ? $type : 302 );
	exit;
}

/*--------------------------------------------------------------
# Auto-301 when a published post's slug/permalink changes
# (Yoast Premium's most useful feature.) Filterable off.
--------------------------------------------------------------*/
add_action('post_updated', 'bp_seo_auto_redirect_on_slug_change', 10, 3);
function bp_seo_auto_redirect_on_slug_change( $post_id, $post_after, $post_before ) {
	if ( ! apply_filters('bp_seo_auto_redirect', true) ) return;
	if ( wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) ) return;
	if ( $post_before->post_status !== 'publish' || $post_after->post_status !== 'publish' ) return;
	if ( $post_before->post_name === $post_after->post_name && $post_before->post_parent === $post_after->post_parent ) return;

	$oldUrl = get_permalink( $post_before );
	$newUrl = get_permalink( $post_after );
	$oldPath = bp_seo_norm_path( (string) $oldUrl );
	$newPath = bp_seo_norm_path( (string) $newUrl );
	if ( $oldPath === '' || $oldPath === $newPath ) return;

	$rows = bp_seo_redirects_all();
	$rows[] = [ 'from' => $oldPath, 'to' => $newPath, 'type' => 301, 'regex' => false ];
	bp_seo_redirects_save( $rows );
}

/*--------------------------------------------------------------
# Admin page  (Tools → SEO Redirects)
--------------------------------------------------------------*/
add_action('admin_menu', function() {
	// Page stays available even while Yoast is active so you can import/prepare
	// redirects before cutover; only the front-end ENGINE defers (see above).
	add_management_page( 'SEO Redirects', 'SEO Redirects', 'manage_options', 'bp-seo-redirects', 'bp_seo_redirects_admin_page' );
});

function bp_seo_redirects_admin_page() {
	if ( ! current_user_can('manage_options') ) return;

	$notice = '';

	// --- Handle "Import from Yoast" ---
	if ( isset($_POST['bp_seo_redir_import']) && check_admin_referer('bp_seo_redir') ) {
		$added = bp_seo_import_yoast_redirects();
		$notice = sprintf('Imported %d redirect(s) from Yoast.', $added);
	}

	// --- Handle save (bulk table submit) ---
	if ( isset($_POST['bp_seo_redir_save']) && check_admin_referer('bp_seo_redir') ) {
		$froms = (array) ( $_POST['from']  ?? [] );
		$tos   = (array) ( $_POST['to']    ?? [] );
		$types = (array) ( $_POST['type']  ?? [] );
		$regex = (array) ( $_POST['regex'] ?? [] );
		$rows  = [];
		foreach ( $froms as $i => $f ) {
			$f = sanitize_text_field( wp_unslash($f) );
			$t = sanitize_text_field( wp_unslash($tos[$i] ?? '') );
			if ( trim($f) === '' && trim($t) === '' ) continue;
			$rows[] = [
				'from'  => $f,
				'to'    => $t,
				'type'  => (int) ( $types[$i] ?? 301 ),
				'regex' => ! empty($regex[$i]),
			];
		}
		bp_seo_redirects_save( $rows );
		$notice = 'Redirects saved.';
	}

	$rows = bp_seo_redirects_all();
	// One blank row to add a new entry.
	$rows[] = [ 'from' => '', 'to' => '', 'type' => 301, 'regex' => false ];
	?>
	<div class="wrap">
		<h1>SEO Redirects</h1>
		<?php if ( $notice ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>'; ?>
		<p>Old URL → New URL. Paths are root-relative (e.g. <code>/old-page/</code> → <code>/new-page/</code>). Leave the last blank row to add a new one; clear both fields of a row to delete it.</p>

		<form method="post">
			<?php wp_nonce_field('bp_seo_redir'); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:38%">Old URL (from)</th>
						<th style="width:38%">New URL (to)</th>
						<th style="width:10%">Type</th>
						<th style="width:8%">Regex</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><input type="text" name="from[]"  value="<?php echo esc_attr($r['from']); ?>" style="width:100%" placeholder="/old-path/"></td>
						<td><input type="text" name="to[]"    value="<?php echo esc_attr($r['to']); ?>"   style="width:100%" placeholder="/new-path/  or  https://…"></td>
						<td>
							<select name="type[]">
								<?php foreach ( [301 => '301 Permanent', 302 => '302 Temporary', 307 => '307 Temporary', 410 => '410 Gone'] as $code => $lbl ) : ?>
									<option value="<?php echo $code; ?>" <?php selected( (int) $r['type'], $code ); ?>><?php echo esc_html($lbl); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td style="text-align:center"><input type="checkbox" name="regex[]" value="1" <?php checked( ! empty($r['regex']) ); ?>></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="submit" name="bp_seo_redir_save" class="button button-primary">Save Redirects</button>
				&nbsp;
				<button type="submit" name="bp_seo_redir_import" class="button" onclick="return confirm('Import all redirects from Yoast Premium? Existing rows are kept; duplicates are skipped.');">Import from Yoast</button>
			</p>
		</form>
	</div>
	<?php
}

/**
 * Import Yoast Premium redirects into our store. Returns the count added.
 * Yoast stores plain redirects in `wpseo-premium-redirects-base` keyed by origin,
 * and regex redirects in `wpseo-premium-redirects-export-regex`. Both map to
 * rows of [ origin => ['url','type'] ] (type is the HTTP code).
 */
function bp_seo_import_yoast_redirects(): int {
	$existing = bp_seo_redirects_all();
	$have     = [];
	foreach ( $existing as $r ) $have[ bp_seo_norm_path((string) $r['from']) ] = 1;

	$added = 0;
	$sources = [
		[ 'opt' => 'wpseo-premium-redirects-base',         'regex' => false ],
		[ 'opt' => 'wpseo-premium-redirects-export-regex', 'regex' => true  ],
	];

	foreach ( $sources as $src ) {
		$data = get_option( $src['opt'], [] );
		if ( ! is_array($data) ) continue;
		foreach ( $data as $origin => $r ) {
			$from = $src['regex'] ? (string) $origin : bp_seo_norm_path( (string) $origin );
			$to   = (string) ( $r['url'] ?? '' );
			$type = (int) ( $r['type'] ?? 301 );
			if ( $from === '' ) continue;
			if ( ! $src['regex'] && isset($have[$from]) ) continue; // skip dupes (exact only)
			$existing[] = [ 'from' => $from, 'to' => $to, 'type' => $type, 'regex' => $src['regex'] ];
			$have[$from] = 1;
			$added++;
		}
	}

	if ( $added ) bp_seo_redirects_save( $existing );
	return $added;
}
