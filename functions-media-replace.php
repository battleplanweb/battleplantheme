<?php
/* Battle Plan Web Design Functions: Media Replace

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Overview
# Entry Points (media row action, edit modal, meta box)
# Replace Screen (form + POST handler)
# Core Replace (delete old, move new, regenerate)
# URL Replacer (search/replace links across the database)
# Post-Replace Notice & Cache Busting
# Helpers
--------------------------------------------------------------*/

/*
 Condensed, front-loaded replacement for the "Enable Media Replace" plugin.
 Keeps only the one workflow we use: click an image in the Media Library,
 upload a new file, the system deletes EVERY size of the old file and puts the
 new one in its place, then rewrites every link to it across the database to
 the new filename (only when the name actually changes).

 Decisions baked in:
   - Human metadata (Title, Alt, Caption, Description) is always preserved.
     Only the file, the filename, the guid, and on-page links change.
   - Link rewrite scans the whole DB (posts incl. Elements CPT, postmeta,
     termmeta, commentmeta, usermeta, options) and is serialize/JSON safe.

 No admin settings, no page-builder modules, no background-removal/upsell — all
 of that lived in the plugin and is intentionally dropped. Pure procedural PHP,
 admin-only, loaded from functions.php.
*/

if (!defined('ABSPATH')) exit;

if (!defined('BP_MR_PAGE')) define('BP_MR_PAGE', 'bp-media-replace');


/*--------------------------------------------------------------
# Entry Points (media row action, edit modal, meta box)
--------------------------------------------------------------*/

// Can the current user replace this particular attachment?
function bp_mr_can_replace($post) {
	if (!is_object($post) || $post->post_type !== 'attachment') return false;
	return current_user_can('edit_post', $post->ID);
}

// Nonced URL to the replace screen for an attachment.
function bp_mr_url($attach_id) {
	$attach_id = (int) $attach_id;
	$url = add_query_arg([
		'page'          => BP_MR_PAGE,
		'attachment_id' => $attach_id,
	], admin_url('upload.php'));
	return wp_nonce_url($url, 'bp_mr_open_' . $attach_id);
}

// Register a hidden page under Media to host the form + handle the upload.
add_action('admin_menu', function () {
	add_submenu_page('upload.php', __('Replace Media', 'battleplan'), __('Replace Media', 'battleplan'), 'upload_files', BP_MR_PAGE, 'bp_mr_render_page');
});
add_action('admin_head', function () {
	remove_submenu_page('upload.php', BP_MR_PAGE); // keep it out of the menu, still reachable by link
});

// "Replace media" link in the Media Library list-view row actions.
add_filter('media_row_actions', function ($actions, $post) {
	if (bp_mr_can_replace($post)) {
		$actions['bp_media_replace'] = '<a href="' . esc_url(bp_mr_url($post->ID)) . '" aria-label="' . esc_attr__('Replace media', 'battleplan') . '">' . esc_html__('Replace media', 'battleplan') . '</a>';
	}
	return $actions;
}, 10, 2);

// "Replace media" button in the attachment-details modal (grid view / inserter).
add_filter('attachment_fields_to_edit', function ($fields, $post) {
	if (!bp_mr_can_replace($post)) return $fields;

	// On the full edit-attachment screen we show the meta box instead, so skip here.
	if (function_exists('get_current_screen')) {
		$screen = get_current_screen();
		if ($screen && $screen->id === 'attachment') return $fields;
	}

	$fields['bp_media_replace'] = [
		'label' => __('Replace media', 'battleplan'),
		'input' => 'html',
		'html'  => '<a class="button-secondary" href="' . esc_url(bp_mr_url($post->ID)) . '">' . esc_html__('Upload a new file', 'battleplan') . '</a>',
		'helps' => __('Replace this file site-wide and update every link to it.', 'battleplan'),
	];
	return $fields;
}, 10, 2);

// Meta box on the full edit-attachment screen.
add_action('add_meta_boxes_attachment', function ($post) {
	if (!bp_mr_can_replace($post)) return;
	add_meta_box('bp-media-replace', __('Replace Media', 'battleplan'), 'bp_mr_meta_box', 'attachment', 'side', 'low');
});
function bp_mr_meta_box($post) {
	echo '<p><a class="button-secondary" href="' . esc_url(bp_mr_url($post->ID)) . '">' . esc_html__('Upload a new file', 'battleplan') . '</a></p>';
	echo '<p>' . esc_html__('Deletes every size of the current file, puts the new file in its place, and rewrites all links across the site to the new filename.', 'battleplan') . '</p>';
}


/*--------------------------------------------------------------
# Replace Screen (form + POST handler)
--------------------------------------------------------------*/

function bp_mr_render_page() {
	if (!current_user_can('upload_files')) {
		wp_die(esc_html__('You do not have permission to replace media.', 'battleplan'));
	}

	$attach_id = isset($_REQUEST['attachment_id']) ? (int) $_REQUEST['attachment_id'] : 0;
	$post      = $attach_id ? get_post($attach_id) : null;

	if (!bp_mr_can_replace($post)) {
		wp_die(esc_html__('That attachment cannot be replaced.', 'battleplan'));
	}

	$error = '';

	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
		check_admin_referer('bp_mr_upload_' . $attach_id);
		$result = bp_mr_process($attach_id);
		if (is_wp_error($result)) {
			$error = $result->get_error_message();
		} else {
			$redirect = add_query_arg([
				'action'          => 'edit',
				'post'            => $attach_id,
				'bp_mr_replaced'  => 1,
				'bp_mr_updated'   => (int) $result,
			], admin_url('post.php'));
			wp_safe_redirect($redirect);
			exit;
		}
	} else {
		check_admin_referer('bp_mr_open_' . $attach_id);
	}

	bp_mr_render_form($attach_id, $error);
}

function bp_mr_render_form($attach_id, $error = '') {
	// Largest available size for the current-file preview; CSS caps it to its column.
	$preview = wp_get_attachment_image($attach_id, 'full', false, ['style' => 'max-width:100%;height:auto;display:block']);
	if (!$preview) {
		$preview = '<p><em>' . esc_html__('(no preview available)', 'battleplan') . '</em></p>';
	}
	$filename   = wp_basename(get_attached_file($attach_id));
	$max_upload = size_format(wp_max_upload_size());

	// Current file's largest-version dimensions + size, for before-upload comparison.
	$meta      = wp_get_attachment_metadata($attach_id);
	$cur_path  = get_attached_file($attach_id);
	$cur_w     = isset($meta['width'])  ? (int) $meta['width']  : 0;
	$cur_h     = isset($meta['height']) ? (int) $meta['height'] : 0;
	$cur_bytes = ($cur_path && file_exists($cur_path)) ? filesize($cur_path) : 0;
	$cur_info  = trim(
		($cur_w && $cur_h ? $cur_w . ' &times; ' . $cur_h . ' px' : '')
		. ($cur_w && $cur_h && $cur_bytes ? ' &middot; ' : '')
		. ($cur_bytes ? bp_mr_fmt_size($cur_bytes) : '')
	);

	$action_url = add_query_arg([
		'page'          => BP_MR_PAGE,
		'attachment_id' => (int) $attach_id,
	], admin_url('upload.php'));
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Replace Media', 'battleplan'); ?></h1>

		<?php if ($error) : ?>
			<div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
		<?php endif; ?>

		<p><?php printf(esc_html__('Replacing %s', 'battleplan'), '<strong>' . esc_html($filename) . '</strong>'); ?></p>

		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url($action_url); ?>">
			<?php wp_nonce_field('bp_mr_upload_' . (int) $attach_id); ?>
			<input type="hidden" name="attachment_id" value="<?php echo (int) $attach_id; ?>">

			<div style="display:flex;gap:3%;flex-wrap:wrap;align-items:flex-start;margin-top:1em">

				<div style="flex:1 1 45%;max-width:50%;min-width:280px;box-sizing:border-box">
					<h2 style="font-size:14px;margin-bottom:.25em"><?php esc_html_e('Current file', 'battleplan'); ?></h2>
					<?php if ($cur_info) : ?><p class="description" style="margin:0 0 .75em"><?php echo $cur_info; // safe: built from ints + size_format ?></p><?php endif; ?>
					<?php echo $preview; // escaped by core ?>
				</div>

				<div style="flex:1 1 45%;max-width:50%;min-width:280px;box-sizing:border-box">
					<h2 style="font-size:14px;margin-bottom:.25em"><?php esc_html_e('New file', 'battleplan'); ?></h2>

					<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
						<input type="file" name="userfile" id="bp_mr_file" required>
						<span style="white-space:nowrap">
							<button type="submit" class="button button-primary button-large"><?php esc_html_e('Upload &amp; Replace', 'battleplan'); ?></button>
							<a class="button button-large" href="<?php echo esc_url(admin_url('upload.php')); ?>"><?php esc_html_e('Cancel', 'battleplan'); ?></a>
						</span>
					</div>

					<p class="description" id="bp_mr_newinfo" style="display:none;margin:.75em 0 0"></p>

					<div id="bp_mr_preview_wrap" style="display:none;margin:.75em 0">
						<img id="bp_mr_preview" alt="" style="max-width:100%;height:auto;display:block">
					</div>

					<p class="description" style="max-width:32em">
						<?php esc_html_e('Every existing size of the current file is deleted and replaced. If the new file has a different name, every link to it across the site is updated, and the attachment title + permalink follow the new filename. Your Alt text and Caption are kept.', 'battleplan'); ?>
						<br><?php printf(esc_html__('Maximum upload size: %s', 'battleplan'), esc_html($max_upload)); ?>
					</p>
				</div>
			</div>
		</form>
	</div>

	<script>
	(function(){
		var input = document.getElementById('bp_mr_file'),
		    wrap  = document.getElementById('bp_mr_preview_wrap'),
		    img   = document.getElementById('bp_mr_preview'),
		    info  = document.getElementById('bp_mr_newinfo'),
		    lastUrl = null;
		if (!input || !wrap || !img) return;

		// Match the PHP bp_mr_fmt_size() formatting for an apples-to-apples comparison.
		function fmtSize(b){
			if (!b) return '';
			return (b < 1048576) ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB';
		}
		function showInfo(text){
			if (!info) return;
			info.innerHTML = text;
			info.style.display = text ? '' : 'none';
		}

		input.addEventListener('change', function(){
			if (lastUrl) { URL.revokeObjectURL(lastUrl); lastUrl = null; }
			showInfo('');
			var file = input.files && input.files[0];
			if (file && /^image\//.test(file.type)) {
				lastUrl = URL.createObjectURL(file);
				img.onload = function(){
					showInfo(img.naturalWidth + ' &times; ' + img.naturalHeight + ' px &middot; ' + fmtSize(file.size));
				};
				img.src = lastUrl;
				wrap.style.display = 'block';
			} else if (file) {
				showInfo(fmtSize(file.size)); // non-image: size only, no preview
				img.removeAttribute('src');
				wrap.style.display = 'none';
			} else {
				img.removeAttribute('src');
				wrap.style.display = 'none';
			}
		});
	})();
	</script>
	<?php
}


/*--------------------------------------------------------------
# Core Replace (delete old, move new, regenerate)
--------------------------------------------------------------*/

function bp_mr_process($attach_id) {
	$attach_id = (int) $attach_id;

	if (!current_user_can('edit_post', $attach_id)) {
		return new WP_Error('bp_mr_perm', __('You do not have permission to replace this file.', 'battleplan'));
	}

	if (empty($_FILES['userfile']) || empty($_FILES['userfile']['tmp_name']) || !is_uploaded_file($_FILES['userfile']['tmp_name'])) {
		return new WP_Error('bp_mr_nofile', __('No file was uploaded. Please choose a file.', 'battleplan'));
	}
	if (!empty($_FILES['userfile']['error'])) {
		return new WP_Error('bp_mr_uploaderr', __('The upload failed (the file may be too large). Please try again.', 'battleplan'));
	}

	$tmp       = $_FILES['userfile']['tmp_name'];
	$orig_name = $_FILES['userfile']['name'];

	// Validate by sniffing the real file, the WordPress way.
	$filedata = wp_check_filetype_and_ext($tmp, $orig_name);
	if (empty($filedata['ext']) && !current_user_can('unfiltered_upload')) {
		return new WP_Error('bp_mr_filetype', __('Sorry, that file type is not allowed.', 'battleplan'));
	}

	// --- Source: always operate on the ORIGINAL (never the -scaled derivative),
	//     otherwise the filename recurses (image-scaled-scaled.jpg). ---
	$source_path = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($attach_id) : false;
	if (!$source_path) $source_path = get_attached_file($attach_id);

	$source_url = function_exists('wp_get_original_image_url') ? wp_get_original_image_url($attach_id) : false;
	if (!$source_url) $source_url = wp_get_attachment_url($attach_id);

	$source_meta  = wp_get_attachment_metadata($attach_id);
	if (!is_array($source_meta)) $source_meta = [];
	$source_perms = (is_string($source_path) && file_exists($source_path)) ? (fileperms($source_path) & 0777) : 0;

	$dir = trailingslashit(dirname($source_path));

	// --- Target filename: keep the uploaded file's own name. Only when the name
	//     differs from the original do we make it unique + trigger a link rewrite. ---
	$new_name    = sanitize_file_name($orig_name);
	$target_path = $dir . $new_name;
	$same_name   = ($target_path === $source_path);
	if (!$same_name) {
		$new_name    = wp_unique_filename($dir, $new_name);
		$target_path = $dir . $new_name;
	}

	// --- Delete every existing file: thumbnails, -scaled, original, backups. ---
	$backup_sizes = get_post_meta($attach_id, '_wp_attachment_backup_sizes', true);
	if (function_exists('wp_delete_attachment_files')) {
		wp_delete_attachment_files($attach_id, $source_meta, $backup_sizes, $source_path);
	}
	$attached = get_attached_file($attach_id);
	if ($attached && file_exists($attached)) @unlink($attached);     // the -scaled main, if any
	if (file_exists($source_path)) @unlink($source_path);            // the untouched original

	// --- Put the new file in place. ---
	if (!@move_uploaded_file($tmp, $target_path)) {
		if (!@copy($tmp, $target_path)) {
			return new WP_Error('bp_mr_move', __('Could not save the uploaded file to the uploads folder.', 'battleplan'));
		}
		@unlink($tmp);
	}
	@chmod($target_path, $source_perms ? $source_perms : 0644);

	// --- Re-point the attachment and regenerate all sizes. ---
	update_attached_file($attach_id, $target_path);

	// Fix the post mime type if it changed (some plugins choke on a stale value).
	$new_mime = !empty($filedata['type']) ? $filedata['type'] : get_post_mime_type($attach_id);
	if ($new_mime && $new_mime !== get_post_mime_type($attach_id)) {
		wp_update_post(['ID' => $attach_id, 'post_mime_type' => $new_mime]);
	}

	if (!function_exists('wp_generate_attachment_metadata')) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$target_meta = wp_generate_attachment_metadata($attach_id, $target_path);
	if (!is_array($target_meta)) $target_meta = [];
	wp_update_attachment_metadata($attach_id, $target_meta);

	// Original URL of the new file (NOT the -scaled), to mirror the source side.
	$target_url = bp_mr_url_for_path($target_path);

	// --- Alt text and Caption are preserved. On a rename, retitle + re-slug the
	//     attachment to the new filename so its permalink follows. Sync the guid. ---
	global $wpdb;
	$guid = wp_get_attachment_url($attach_id);
	if ($guid) {
		$wpdb->update($wpdb->posts, ['guid' => $guid], ['ID' => $attach_id]);
	}

	$now = current_time('mysql');
	$post_update = [
		'ID'                => $attach_id,
		'post_modified'     => $now,
		'post_modified_gmt' => get_gmt_from_date($now),
	];
	if (!$same_name) {
		$title_base = pathinfo($new_name, PATHINFO_FILENAME);     // new filename, no extension
		$post_update['post_title'] = $title_base;
		$post_update['post_name']  = sanitize_title($title_base);  // wp_update_post makes it unique
	}
	wp_update_post($post_update);

	// --- Rewrite links across the database. Safely no-ops when nothing changed
	//     (same name + same generated sizes produce zero search/replace pairs). ---
	$updated = bp_mr_replace_urls($source_url, $target_url, $source_meta, $target_meta);

	// --- Clear caches. ---
	clean_post_cache($attach_id);
	wp_cache_delete($attach_id, 'posts');

	/**
	 * Fires after a media file has been replaced in place.
	 * @param int    $attach_id
	 * @param string $source_url  old original URL
	 * @param string $target_url  new original URL
	 */
	do_action('bp_media_replaced', $attach_id, $source_url, $target_url);

	return (int) $updated; // number of DB rows whose links were rewritten
}


/*--------------------------------------------------------------
# URL Replacer (search/replace links across the database)
--------------------------------------------------------------*/
/*
 Ported (and trimmed) from Enable Media Replace's Replacer. Builds the relative
 URL maps for the old + new files (main file plus every thumbnail size), pairs
 them up — filling any size missing in the new image with its nearest match —
 then runs a serialize/JSON-aware search & replace across the content tables.
*/

function bp_mr_replace_urls($source_url, $target_url, $source_meta, $target_meta) {
	if (!$source_url || !$target_url) return 0;

	// Extension-stripped relative path: the cheap LIKE key that finds candidate rows.
	$base_url = parse_url($source_url, PHP_URL_PATH);
	if (!$base_url) return 0;
	$ext = pathinfo($base_url, PATHINFO_EXTENSION);
	if ($ext !== '') $base_url = substr($base_url, 0, -(strlen($ext) + 1));
	if (trim($base_url) === '') return 0;

	$urls    = bp_mr_relative_urls($source_url, $target_url, $source_meta, $target_meta);
	$search  = $urls['source'];
	$replace = $urls['target'];

	// For any old size missing from the new image, map it to the nearest new size.
	foreach ($search as $size => $u) {
		if (!isset($replace[$size])) {
			$closest = bp_mr_nearest_size($size, $search, $source_meta, $target_meta, $target_url);
			if ($closest !== false) $replace[$size] = $closest;
		}
	}

	// Build index-aligned URL pairs, dropping identical or unmatched entries.
	$final_search = $final_replace = [];
	foreach ($search as $size => $u) {
		$r = isset($replace[$size]) ? $replace[$size] : null;
		if ($r === null || $r === $u) continue;
		$final_search[]  = $u;
		$final_replace[] = $r;
	}

	// Map of new URL (relative path) => [width, height], used to correct the
	// width/height/aspect-ratio on <img> tags in content when a size changed.
	$dim_map = bp_mr_dimension_map($urls['target'], $target_meta);

	// Nothing to do if neither any filename nor any dimension changed.
	if (empty($final_search) && !bp_mr_dimensions_changed($source_meta, $target_meta)) return 0;

	return bp_mr_replace_query($base_url, $final_search, $final_replace, $dim_map);
}

// Relative URL maps for both files: 'base' (main), 'original_image', 'file', and each size.
function bp_mr_relative_urls($source_url, $target_url, $source_meta, $target_meta) {
	$sides = [
		'source' => ['url' => $source_url, 'files' => bp_mr_files_from_meta($source_meta)],
		'target' => ['url' => $target_url, 'files' => bp_mr_files_from_meta($target_meta)],
	];
	$result = [];
	foreach ($sides as $side => $item) {
		$result[$side] = [];
		$path = parse_url($item['url'], PHP_URL_PATH);
		$result[$side]['base'] = $path;
		$baseurl = trailingslashit(str_replace(wp_basename($item['url']), '', $path));
		foreach ($item['files'] as $name => $filename) {
			$result[$side][$name] = $baseurl . wp_basename($filename);
		}
	}
	return $result;
}

// Collect every filename referenced by an attachment's metadata.
function bp_mr_files_from_meta($meta) {
	$out = [];
	if (isset($meta['file']))           $out['file'] = $meta['file'];
	if (isset($meta['original_image'])) $out['original_image'] = $meta['original_image'];
	if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
		foreach ($meta['sizes'] as $name => $data) {
			if (isset($data['file'])) {
				$out[$name] = is_array($data['file']) ? ($data['file'][0] ?? '') : $data['file'];
			}
		}
	}
	return array_filter($out);
}

// When an old size has no new counterpart, find the closest new size (by width).
function bp_mr_nearest_size($size, $search_urls, $source_meta, $target_meta, $target_url) {
	if (!isset($source_meta['sizes'][$size]['width']) || !isset($target_meta['width'])) return false;
	if (!isset($search_urls[$size])) return false;

	$src_size_url = $search_urls[$size];
	$baseurl = trailingslashit(str_replace(wp_basename($src_size_url), '', $src_size_url));

	// SVGs have no thumbnails — point every size at the single file.
	if (strpos($target_url, '.svg') !== false) {
		return $baseurl . wp_basename($target_url);
	}

	$old_width = (int) $source_meta['sizes'][$size]['width'];
	$diff      = abs($old_width - (int) $target_meta['width']);
	$closest   = isset($target_meta['file']) ? wp_basename($target_meta['file']) : '';

	if (!empty($target_meta['sizes']) && is_array($target_meta['sizes'])) {
		foreach ($target_meta['sizes'] as $data) {
			if (!isset($data['width'], $data['file'])) continue;
			$thisdiff = abs($old_width - (int) $data['width']);
			if ($thisdiff < $diff) {
				$f = is_array($data['file']) ? ($data['file'][0] ?? '') : $data['file'];
				if ($f !== '') { $closest = $f; $diff = $thisdiff; }
			}
		}
	}

	if ($closest === '') return false;
	return $baseurl . wp_basename($closest);
}

// Run the search/replace across post_content and all metadata/option tables.
function bp_mr_replace_query($base_url, $search, $replace, $dim_map = []) {
	global $wpdb;
	$count = 0;

	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT ID, post_content FROM {$wpdb->posts}
		 WHERE post_status IN ('publish','future','draft','pending','private')
		 AND post_content LIKE %s",
		'%' . $wpdb->esc_like($base_url) . '%'
	), ARRAY_A);

	if ($rows) {
		foreach ($rows as $row) {
			$new = bp_mr_replace_content($row['post_content'], $search, $replace, false, true);
			// Correct <img> dimensions — plain HTML only, never inside a serialized
			// blob (rewriting a string there would break its byte-length prefix).
			if (!empty($dim_map) && !is_serialized($row['post_content'])) {
				$new = bp_mr_fix_img_dimensions($new, $dim_map);
			}
			if ($new !== $row['post_content']) {
				$wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = %s WHERE ID = %d", $new, $row['ID']));
				clean_post_cache($row['ID']); // raw UPDATE bypasses the object cache — bust it
				$count++;
			}
		}
	}

	// URL replacements also live in metadata/options (dimensions aren't touched there).
	// Skip entirely when only dimensions changed (same filename → nothing to find).
	if (!empty($search)) {
		$count += bp_mr_replace_meta($base_url, $search, $replace);
	}
	return $count;
}

// Search/replace across postmeta (content posts only), commentmeta, termmeta, usermeta, options.
function bp_mr_replace_meta($base_url, $search, $replace) {
	global $wpdb;
	$count = 0;
	$like  = '%' . $wpdb->esc_like($base_url) . '%';

	// postmeta — restricted to real content posts (this is where Page Top / Page
	// Bottom sections live, so it must run for the framework's hero/band images).
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta}
		 WHERE post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','future','draft','pending','private'))
		 AND meta_value LIKE %s",
		$like
	), ARRAY_A);
	foreach ((array) $rows as $row) {
		$new = bp_mr_replace_content($row['meta_value'], $search, $replace);
		if ($new !== $row['meta_value']) {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d", $new, $row['meta_id']));
			clean_post_cache($row['post_id']); // bust the owning post's object cache
			$count++;
		}
	}

	// Remaining tables: [table => [id column, value column]].
	$tables = [
		$wpdb->commentmeta => ['meta_id', 'meta_value'],
		$wpdb->termmeta    => ['meta_id', 'meta_value'],
		$wpdb->usermeta    => ['umeta_id', 'meta_value'],
		$wpdb->options     => ['option_id', 'option_value'],
	];
	$options_changed = false;
	foreach ($tables as $table => $cols) {
		list($id_col, $val_col) = $cols;
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT {$id_col} AS id, {$val_col} AS val FROM {$table} WHERE {$val_col} LIKE %s",
			$like
		), ARRAY_A);
		foreach ((array) $rows as $row) {
			$new = bp_mr_replace_content($row['val'], $search, $replace);
			if ($new !== $row['val']) {
				$wpdb->query($wpdb->prepare("UPDATE {$table} SET {$val_col} = %s WHERE {$id_col} = %d", $new, $row['id']));
				if ($table === $wpdb->options) $options_changed = true;
				$count++;
			}
		}
	}
	if ($options_changed) wp_cache_delete('alloptions', 'options'); // raw UPDATE leaves the autoload cache stale

	return $count;
}

/*
 Replace URLs inside a value that may be a plain string, JSON, or PHP-serialized
 data (arrays/objects, nested). Mirrors EMR's recursion so page-builder blobs and
 serialized meta survive intact.
   $strict — true for post_content (forbid unserializing objects entirely).
*/
function bp_mr_replace_content($content, $search, $replace, $in_deep = false, $strict = false) {
	$serialized = null;

	if (is_serialized($content)) {
		$serialized = $content; // keep the original so we can bail without corrupting it
		$content = @unserialize($content, ['allowed_classes' => $strict ? false : true]);
		if (bp_mr_is_incomplete($content)) return $serialized;
	}

	$is_json = bp_mr_is_json($content);
	if ($is_json) $content = json_decode($content);

	if (is_string($content)) {
		$content = str_replace($search, $replace, $content);
	} elseif (is_array($content)) {
		foreach ($content as $k => $v) {
			$content[$k] = bp_mr_replace_content($v, $search, $replace, true);
			if (is_string($k)) {
				$nk = bp_mr_replace_content($k, $search, $replace, true);
				if ($nk !== $k) $content = bp_mr_change_key($content, [$k => $nk]);
			}
		}
	} elseif (is_object($content)) {
		if (bp_mr_is_incomplete($content)) return $serialized !== null ? $serialized : $content;
		foreach ($content as $k => $v) {
			$content->{$k} = bp_mr_replace_content($v, $search, $replace, true);
		}
	}

	if ($is_json && $in_deep === false) {
		$content = json_encode($content, JSON_UNESCAPED_SLASHES);
	} elseif ($in_deep === false && (is_array($content) || is_object($content))) {
		$content = maybe_serialize($content);
	} elseif ($in_deep === false && $serialized !== null && is_scalar($content)) {
		$content = serialize($content); // preserve a value that was a serialized scalar
	}

	return $content;
}


/*--------------------------------------------------------------
# Post-Replace Notice & Cache Busting
--------------------------------------------------------------*/

add_action('admin_notices', function () {
	if (isset($_GET['bp_mr_replaced']) && (int) $_GET['bp_mr_replaced'] === 1) {
		$n   = isset($_GET['bp_mr_updated']) ? (int) $_GET['bp_mr_updated'] : -1;
		$msg = esc_html__('Media replaced. All sizes were regenerated.', 'battleplan');
		if ($n > 0) {
			$msg .= ' ' . esc_html(sprintf(_n('Updated %d place that linked to it.', 'Updated %d places that linked to it.', $n, 'battleplan'), $n));
		} elseif ($n === 0) {
			$msg .= ' ' . esc_html__('No stored links needed updating (the filename was unchanged, or the image is output by a shortcode/ID rather than a hard-coded URL).', 'battleplan');
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . $msg . '</p></div>';
	}
});

// Defeat the browser cache for the freshly replaced image on the edit screen only.
add_filter('wp_get_attachment_image_src', function ($image) {
	if (isset($_GET['bp_mr_replaced']) && (int) $_GET['bp_mr_replaced'] === 1 && is_array($image) && !empty($image[0])) {
		$image[0] = add_query_arg('bpv', time(), $image[0]);
	}
	return $image;
}, 10, 1);


/*--------------------------------------------------------------
# Helpers
--------------------------------------------------------------*/

// Human file size, matching the form's JS formatter (whole KB, one-decimal MB)
// so the current-vs-new comparison reads consistently.
function bp_mr_fmt_size($bytes) {
	$bytes = (int) $bytes;
	if ($bytes <= 0)        return '';
	if ($bytes < 1048576)   return round($bytes / 1024) . ' KB';
	return number_format($bytes / 1048576, 1) . ' MB';
}

// Build the public URL for a file path inside the uploads dir.
function bp_mr_url_for_path($path) {
	$uploads = wp_get_upload_dir();
	if (!empty($uploads['basedir']) && !empty($uploads['baseurl'])) {
		return str_replace($uploads['basedir'], $uploads['baseurl'], $path);
	}
	return $path;
}

function bp_mr_is_incomplete($var) {
	return ($var instanceof __PHP_Incomplete_Class);
}

// Is this string JSON (and not just a bare number/word that happens to decode)?
function bp_mr_is_json($content) {
	if (!is_string($content) || $content === '') return false;
	$decoded = json_decode($content);
	return ($decoded !== null && $decoded != $content);
}

// Rename array keys per a [old => new] map, recursively.
function bp_mr_change_key($arr, $set) {
	if (!is_array($arr) || !is_array($set)) return $arr;
	$new = [];
	foreach ($arr as $k => $v) {
		$key = array_key_exists($k, $set) ? $set[$k] : $k;
		$new[$key] = is_array($v) ? bp_mr_change_key($v, $set) : $v;
	}
	return $new;
}

// Build [ relative-url-path => [width, height] ] for the new file's main image + every size.
function bp_mr_dimension_map($target_urls, $target_meta) {
	$map = [];
	$mw = isset($target_meta['width'])  ? (int) $target_meta['width']  : 0;
	$mh = isset($target_meta['height']) ? (int) $target_meta['height'] : 0;
	if ($mw && $mh) {
		if (isset($target_urls['base'])) $map[$target_urls['base']] = [$mw, $mh];
		if (isset($target_urls['file'])) $map[$target_urls['file']] = [$mw, $mh];
	}
	if (!empty($target_meta['sizes']) && is_array($target_meta['sizes'])) {
		foreach ($target_meta['sizes'] as $name => $data) {
			if (isset($target_urls[$name], $data['width'], $data['height'])) {
				$map[$target_urls[$name]] = [(int) $data['width'], (int) $data['height']];
			}
		}
	}
	return $map;
}

// Did the main image or any shared size change pixel dimensions? (Drives same-name fixups.)
function bp_mr_dimensions_changed($source_meta, $target_meta) {
	if ((int) ($source_meta['width']  ?? 0) !== (int) ($target_meta['width']  ?? 0)) return true;
	if ((int) ($source_meta['height'] ?? 0) !== (int) ($target_meta['height'] ?? 0)) return true;
	if (!empty($source_meta['sizes']) && is_array($source_meta['sizes'])) {
		foreach ($source_meta['sizes'] as $name => $d) {
			$tw = $target_meta['sizes'][$name]['width']  ?? null;
			$th = $target_meta['sizes'][$name]['height'] ?? null;
			if ((int) ($d['width'] ?? 0) !== (int) $tw || (int) ($d['height'] ?? 0) !== (int) $th) return true;
		}
	}
	return false;
}

/*
 Correct width / height / inline aspect-ratio on <img> tags whose src points at one
 of the new file's sizes. Only touches attributes that already exist (the framework
 always emits width + height + style="aspect-ratio:W/H"); srcset/sizes are left alone
 because WordPress recomputes those live from the attachment metadata at render time.
*/
function bp_mr_fix_img_dimensions($html, $dim_map) {
	if (empty($dim_map) || stripos($html, '<img') === false) return $html;

	return preg_replace_callback('/<img\b[^>]*>/i', function ($m) use ($dim_map) {
		$tag = $m[0];

		if (!preg_match('/\bsrc\s*=\s*("|\')(.*?)\1/i', $tag, $s)) return $tag;
		$path = parse_url(html_entity_decode($s[2], ENT_QUOTES), PHP_URL_PATH);
		if (!$path || !isset($dim_map[$path])) return $tag;

		list($w, $h) = $dim_map[$path];
		if (!$w || !$h) return $tag;

		$tag = preg_replace('/\bwidth\s*=\s*("|\')\d+\1/i',  'width="'  . $w . '"', $tag);
		$tag = preg_replace('/\bheight\s*=\s*("|\')\d+\1/i', 'height="' . $h . '"', $tag);
		$tag = preg_replace('/aspect-ratio\s*:\s*\d+\s*\/\s*\d+/i', 'aspect-ratio:' . $w . '/' . $h, $tag);

		return $tag;
	}, $html);
}
