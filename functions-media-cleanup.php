<?php
/* Battle Plan Web Design Functions: Media Cleanup

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Overview
# Usage Detection (is an image used anywhere?)
# Quarantine (move out / restore / purge)
# Library Hooks (button, hide quarantined, purge cron)
# Clean Up Screen
# AJAX
--------------------------------------------------------------*/

/*
 "Clean Up Library" button on the Media Library. Scan → Review → Quarantine.

 Scan: images that are NOT attached (or whose parent post no longer exists),
 older than N days, then each one is checked against everything that can
 reference an image — post content (all post types, incl. Elements), postmeta
 (ACF, Page Top/Bottom heroes), termmeta, usermeta, options (logo, widgets,
 theme mods), the image-tags / image-categories taxonomies (logo slider,
 row-of-pics, load-images pull images by tag), non-core custom tables, and the
 child theme's files (style-site.css, functions-site.php, page-content .txt).
 Matching is by file stem (catches every size + -scaled) and by ID in
 ID-shaped contexts (wp-image-123, "id":123, img="123", 'logo' => 123, ACF
 serialized lists). Ambiguous = in use. A false "in use" costs some disk; a
 false "unused" breaks a page — so every tie goes to keeping the image.

 Quarantine: selected images have their files MOVED to uploads/bp-quarantine/
 (attachment record kept, hidden from the library). Anything that was secretly
 still using one shows a broken image, and Restore puts it back. After
 BP_MC_QUARANTINE_DAYS a daily cron deletes them for good.

 bp_media_in_use() is the shared "is this image used anywhere" check — the
 Jobsite GEO photo deletion uses it too.

 Loaded unconditionally from functions.php (jobsite deletes and the purge cron
 run outside wp-admin); the screen + AJAX are manage_options only.
*/

if (!defined('ABSPATH')) exit;

if (!defined('BP_MC_PAGE'))             define('BP_MC_PAGE', 'bp-media-cleanup');
if (!defined('BP_MC_DIR'))              define('BP_MC_DIR', 'bp-quarantine');
if (!defined('BP_MC_QUARANTINE_DAYS'))  define('BP_MC_QUARANTINE_DAYS', 30);
if (!defined('BP_MC_BATCH'))            define('BP_MC_BATCH', 20);


/*--------------------------------------------------------------
# Usage Detection (is an image used anywhere?)
--------------------------------------------------------------*/

function bp_media_in_use($aid, $exclude_post = 0) {
	return isset(bp_media_used_ids([(int) $aid], $exclude_post)[(int) $aid]);
}

// Returns [attachment_id => where it was found] for every ID that is in use.
// $exclude_post — ignore references from this post (the jobsite being edited/deleted).
function bp_media_used_ids(array $ids, $exclude_post = 0) {
	global $wpdb;
	$ids          = array_values(array_unique(array_filter(array_map('intval', $ids))));
	$exclude_post = (int) $exclude_post;
	$used         = [];
	if (!$ids) return $used;

	$needles = [];
	foreach ($ids as $id) {
		$stem = bp_mc_stem($id);
		if ($stem === '') { $used[$id] = 'no file path'; continue; }
		$needles[$id] = array_values(array_unique([$stem, str_replace('/', '\/', $stem)]));
	}

	// Tagged images are pulled dynamically by the image shortcodes.
	if ($needles) {
		$in     = implode(',', array_keys($needles));
		$tagged = $wpdb->get_col(
			"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr
			 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 WHERE tt.taxonomy IN ('image-tags','image-categories') AND tr.object_id IN ($in)"
		);
		foreach ($tagged as $id) { $used[(int) $id] = 'image tag/category'; unset($needles[(int) $id]); }
	}

	$check = function ($rows, $where, $loose_fn) use (&$needles, &$used) {
		foreach ((array) $rows as $row) {
			$loose = $loose_fn($row);
			foreach ($needles as $id => $n) {
				if (bp_mc_value_refs($row->val, $id, $n, $loose)) { $used[$id] = $where; unset($needles[$id]); }
			}
			if (!$needles) return;
		}
	};

	if ($needles) {
		$check($wpdb->get_results(
			"SELECT CONCAT_WS(' ', post_content, post_excerpt) AS val FROM {$wpdb->posts}
			 WHERE post_type NOT IN ('revision','attachment') AND post_status <> 'auto-draft' AND ID <> {$exclude_post}
			 AND " . bp_mc_where("CONCAT_WS(' ', post_content, post_excerpt)", $needles)
		), 'post content', '__return_false');
	}

	if ($needles) {
		$self = implode(',', array_merge($ids, [$exclude_post]));
		$check($wpdb->get_results(
			"SELECT pm.meta_key AS k, pm.meta_value AS val FROM {$wpdb->postmeta} pm
			 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type <> 'revision' AND (p.post_type <> 'attachment' OR pm.meta_key = '_thumbnail_id')
			 AND pm.post_id NOT IN ($self) AND pm.meta_key NOT IN ('_edit_lock','_edit_last')
			 AND " . bp_mc_where('pm.meta_value', $needles)
		), 'post meta', '__return_true');
	}

	$key_loose = function ($row) { return bp_mc_key_ish($row->k); };

	if ($needles) {
		$check($wpdb->get_results(
			"SELECT option_name AS k, option_value AS val FROM {$wpdb->options}
			 WHERE option_name NOT LIKE '\\_transient%' AND option_name NOT LIKE '\\_site\\_transient%'
			 AND option_name NOT IN ('cron','rewrite_rules') AND " . bp_mc_where('option_value', $needles)
		), 'options', $key_loose);
	}

	if ($needles) {
		$check($wpdb->get_results("SELECT meta_key AS k, meta_value AS val FROM {$wpdb->termmeta} WHERE " . bp_mc_where('meta_value', $needles)), 'term meta', $key_loose);
	}

	if ($needles) {
		$check($wpdb->get_results("SELECT meta_key AS k, meta_value AS val FROM {$wpdb->usermeta} WHERE meta_key <> 'session_tokens' AND " . bp_mc_where('meta_value', $needles)), 'user meta', $key_loose);
	}

	if ($needles) {
		$check(array_map(function ($text) { return (object) ['val' => $text]; }, bp_mc_theme_files()), 'theme files', '__return_false');
	}

	foreach (bp_mc_custom_tables() as $table => $cols) {
		if (!$needles) break;
		$where = [];
		foreach ($cols['text'] as $c) $where[] = bp_mc_where("`{$c}`", $needles);
		foreach ($cols['nums'] as $c) $where[] = "`{$c}` IN (" . implode(',', array_keys($needles)) . ')';
		$select = implode(',', array_map(function ($c) { return "`{$c}`"; }, array_merge($cols['text'], $cols['nums'])));
		$rows   = $wpdb->get_results("SELECT {$select} FROM `{$table}` WHERE " . implode(' OR ', $where), ARRAY_A);
		foreach ((array) $rows as $row) {
			foreach ($needles as $id => $n) {
				foreach ($cols['nums'] as $c) {
					if ((int) $row[$c] === $id) { $used[$id] = $table; unset($needles[$id]); continue 2; }
				}
				foreach ($cols['text'] as $c) {
					if (bp_mc_value_refs((string) $row[$c], $id, $n, bp_mc_key_ish($c))) { $used[$id] = $table; unset($needles[$id]); continue 2; }
				}
			}
		}
	}

	return $used;
}

// Uploads-relative path of the original file minus extension ("2024/05/ac-unit"):
// a prefix of every generated size, -scaled, -rotated and edited (-e123…) copy.
function bp_mc_stem($aid) {
	$file = (string) get_post_meta($aid, '_wp_attached_file', true);
	if ($file === '') return '';
	$meta = wp_get_attachment_metadata($aid);
	$name = !empty($meta['original_image']) ? $meta['original_image'] : wp_basename($file);
	$name = preg_replace('/\.[^.]+$/', '', $name);
	$name = preg_replace('/-(scaled|rotated)$/', '', $name);
	$name = preg_replace('/-e\d{13}$/', '', $name);
	if ($name === '') return '';
	$dir = dirname($file);
	return ($dir && $dir !== '.') ? $dir . '/' . $name : $name;
}

// SQL prefilter: the column mentions any stem, or any of the IDs as a whole number.
// Rows it returns are only candidates — bp_mc_value_refs() makes the real call.
function bp_mc_where($col, array $needles) {
	global $wpdb;
	$or = [];
	foreach ($needles as $list) {
		foreach ($list as $n) $or[] = $wpdb->prepare("{$col} LIKE %s", '%' . $wpdb->esc_like($n) . '%');
	}
	$or[] = $wpdb->prepare("{$col} REGEXP %s", '(^|[^0-9])(' . implode('|', array_keys($needles)) . ')([^0-9]|$)');
	return '(' . implode(' OR ', $or) . ')';
}

// A key/attribute/column name that points at an image (logo_id, imageId, img, "id").
function bp_mc_key_ish($key) {
	$key = (string) $key;
	return (bool) preg_match('/^ids?$|image|img|logo|photo|pic|icon|bg|background|thumb|media|attachment|gallery|banner|hero|avatar|slide/i', $key);
}

// Does a stored value reference this image? Stem anywhere = yes. A bare ID only
// counts in an ID-shaped context. $loose — the value's own key looks like an
// image field (or it's postmeta, where ACF field names are arbitrary), so a plain
// list of IDs counts too (ACF gallery: a:2:{i:0;s:3:"123";…}).
function bp_mc_value_refs($val, $id, array $needles, $loose = false) {
	$val = (string) $val;
	foreach ($needles as $n) if (strpos($val, $n) !== false) return true;

	$sid = (string) $id;
	if (strpos($val, $sid) === false) return false;
	if (trim($val) === $sid) return true;
	if (preg_match('/wp-image-' . $sid . '(?!\d)/', $val)) return true;

	// img="123"  ids="4,123"  "id":123  "mediaId":"123"  'logo' => 123  wp_get_attachment_image(123
	if (preg_match_all('/["\']?([A-Za-z_][\w\-]*)["\']?\s*(?:=>|=|:|\()\s*\[?\s*["\']?((?:\d+["\']?\s*,\s*["\']?)*\d+)/', $val, $m, PREG_SET_ORDER)) {
		foreach ($m as $hit) {
			if (bp_mc_key_ish($hit[1]) && in_array($sid, preg_split('/["\'\s,]+/', $hit[2], -1, PREG_SPLIT_NO_EMPTY), true)) return true;
		}
	}

	// Serialized key => value:  s:11:"custom_logo";i:123;
	if (preg_match_all('/s:\d+:"([^"]*)";(?:i:(\d+);|s:\d+:"(\d+)";)/', $val, $m, PREG_SET_ORDER)) {
		foreach ($m as $hit) {
			if (bp_mc_key_ish($hit[1]) && (($hit[2] ?? '') === $sid || ($hit[3] ?? '') === $sid)) return true;
		}
	}

	if ($loose) {
		if (preg_match('/i:\d+;(?:i:' . $sid . ';|s:\d+:"' . $sid . '";)/', $val)) return true;
		if (preg_match('/^[\d,\s]+$/', $val) && in_array($sid, preg_split('/[\s,]+/', trim($val)), true)) return true;
		if (preg_match('/\[\s*(?:"?\d+"?\s*,\s*)*"?' . $sid . '"?\s*[,\]]/', $val)) return true;
	}

	return false;
}

// Child theme text files, read once per request.
function bp_mc_theme_files() {
	static $files = null;
	if ($files !== null) return $files;
	$files = [];
	$dir   = get_stylesheet_directory();
	if (!is_dir($dir)) return $files;

	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f) {
		if (!$f->isFile() || $f->getSize() > 3 * MB_IN_BYTES) continue;
		if (!preg_match('/\.(php|css|js|txt|html?|json|svg)$/i', $f->getFilename())) continue;
		if (strpos(wp_normalize_path($f->getPathname()), '/node_modules/') !== false) continue;
		$files[] = (string) file_get_contents($f->getPathname());
	}
	return $files;
}

// Non-core tables worth checking: text columns (stem/ID search) and int columns
// named like an image field (exact ID). Logs, caches, stats and derived indexes
// are skipped — they're big and a reference there isn't a real use.
function bp_mc_custom_tables() {
	static $map = null;
	if ($map !== null) return $map;
	global $wpdb;
	$map  = [];
	$core = array_values($wpdb->tables('all'));
	$skip = '/log|session|cache|transient|stat|visit|hit|audit|queue|actionscheduler|yoast|wfls|^wf|redirection|indexable|lookup|search/i';

	foreach ((array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix) . '%')) as $table) {
		if (in_array($table, $core, true)) continue;
		if (preg_match($skip, substr($table, strlen($wpdb->prefix)))) continue;
		$text = $nums = [];
		foreach ((array) $wpdb->get_results("SHOW COLUMNS FROM `{$table}`") as $col) {
			if (preg_match('/char|text|json/i', $col->Type)) $text[] = $col->Field;
			elseif (preg_match('/int/i', $col->Type) && preg_match('/attachment|image|img|photo|logo|media|file|thumb|icon|avatar|pic/i', $col->Field)) $nums[] = $col->Field;
		}
		if ($text || $nums) $map[$table] = ['text' => $text, 'nums' => $nums];
	}
	return $map;
}

// Unattached (or orphaned — parent post gone) images older than $days, not tagged,
// not already quarantined.
function bp_mc_candidates($days) {
	global $wpdb;
	$cutoff = gmdate('Y-m-d H:i:s', time() - max(0, (int) $days) * DAY_IN_SECONDS);
	return array_map('intval', $wpdb->get_col($wpdb->prepare(
		"SELECT a.ID FROM {$wpdb->posts} a
		 LEFT JOIN {$wpdb->posts} parent ON parent.ID = a.post_parent
		 LEFT JOIN {$wpdb->postmeta} q ON q.post_id = a.ID AND q.meta_key = '_bp_quarantined'
		 WHERE a.post_type = 'attachment' AND a.post_mime_type LIKE 'image/%%'
		 AND a.post_date_gmt < %s AND (a.post_parent = 0 OR parent.ID IS NULL) AND q.meta_id IS NULL
		 AND NOT EXISTS (
			SELECT 1 FROM {$wpdb->term_relationships} tr
			JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			WHERE tr.object_id = a.ID AND tt.taxonomy IN ('image-tags','image-categories')
		 )
		 ORDER BY a.ID ASC",
		$cutoff
	)));
}


/*--------------------------------------------------------------
# Quarantine (move out / restore / purge)
--------------------------------------------------------------*/

function bp_mc_base() {
	return wp_normalize_path(wp_get_upload_dir()['basedir']);
}

// Every file on disk for an attachment: original, -scaled, each generated size,
// plus .webp/.avif twins.
function bp_mc_attachment_files($aid) {
	$path = get_attached_file($aid, true);
	if (!$path) return [];
	$dir   = dirname($path);
	$meta  = wp_get_attachment_metadata($aid);
	$names = [wp_basename($path)];
	if (!empty($meta['original_image'])) $names[] = $meta['original_image'];
	foreach ((array) ($meta['sizes'] ?? []) as $s) if (!empty($s['file'])) $names[] = $s['file'];

	$files = [];
	foreach (array_unique($names) as $name) {
		$stem = preg_replace('/\.[^.]+$/', '', $name);
		foreach ([$name, $name . '.webp', $stem . '.webp', $stem . '.avif'] as $n) {
			$p = wp_normalize_path($dir . '/' . $n);
			if (is_file($p)) $files[$p] = $p;
		}
	}
	return array_values($files);
}

function bp_mc_quarantine($aid) {
	global $wpdb;
	$aid = (int) $aid;
	if (get_post_type($aid) !== 'attachment' || get_post_meta($aid, '_bp_quarantined', true)) return new WP_Error('skip', 'Not available.');

	// Re-check at the moment of moving — the scan may be stale.
	$used = bp_media_used_ids([$aid]);
	if (isset($used[$aid])) return new WP_Error('in_use', 'Now in use (' . $used[$aid] . ').');

	$file = (string) get_post_meta($aid, '_wp_attached_file', true);
	if ($file === '' || $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s AND post_id <> %d", $file, $aid
	))) return new WP_Error('shared', 'File is shared with another attachment.');

	$base  = bp_mc_base();
	$moved = [];
	foreach (bp_mc_attachment_files($aid) as $src) {
		if (strpos($src, $base . '/') !== 0) continue;
		$rel  = substr($src, strlen($base) + 1);
		$dest = $base . '/' . BP_MC_DIR . '/' . $rel;
		wp_mkdir_p(dirname($dest));
		if (!@rename($src, $dest)) {
			bp_mc_move_back($moved);
			return new WP_Error('move_failed', 'Could not move ' . $rel . '.');
		}
		$moved[] = $rel;
	}

	update_post_meta($aid, '_bp_quarantine_files', $moved);
	update_post_meta($aid, '_bp_quarantined', time());
	return true;
}

function bp_mc_move_back(array $rels) {
	$base = bp_mc_base();
	foreach ($rels as $rel) {
		$src = $base . '/' . BP_MC_DIR . '/' . $rel;
		if (!is_file($src)) continue;
		wp_mkdir_p(dirname($base . '/' . $rel));
		@rename($src, $base . '/' . $rel);
	}
}

function bp_mc_restore($aid) {
	$aid = (int) $aid;
	if (!get_post_meta($aid, '_bp_quarantined', true)) return new WP_Error('skip', 'Not in quarantine.');
	$base = bp_mc_base();
	$rels = (array) get_post_meta($aid, '_bp_quarantine_files', true);

	// A new upload may have taken the name since — never overwrite it.
	foreach ($rels as $rel) {
		if (is_file($base . '/' . $rel)) return new WP_Error('exists', wp_basename($rel) . ' already exists again in uploads.');
	}

	bp_mc_move_back($rels);
	delete_post_meta($aid, '_bp_quarantine_files');
	delete_post_meta($aid, '_bp_quarantined');
	return true;
}

function bp_mc_purge($aid) {
	$aid = (int) $aid;
	if (!get_post_meta($aid, '_bp_quarantined', true)) return new WP_Error('skip', 'Not in quarantine.');
	$base = bp_mc_base();
	foreach ((array) get_post_meta($aid, '_bp_quarantine_files', true) as $rel) {
		$p = $base . '/' . BP_MC_DIR . '/' . $rel;
		if (is_file($p)) @unlink($p);
	}
	delete_post_meta($aid, '_bp_quarantine_files');
	delete_post_meta($aid, '_bp_quarantined');
	wp_delete_attachment($aid, true);
	return true;
}

function bp_mc_quarantined_ids($older_than = 0, $limit = -1) {
	$args = [
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'fields'      => 'ids',
		'numberposts' => $limit,
		'orderby'     => 'meta_value_num',
		'meta_key'    => '_bp_quarantined',
		'order'       => 'ASC',
	];
	if ($older_than) {
		$args['meta_query'] = [['key' => '_bp_quarantined', 'value' => (int) $older_than, 'compare' => '<', 'type' => 'NUMERIC']];
	}
	return array_map('intval', get_posts($args));
}


/*--------------------------------------------------------------
# Library Hooks (button, hide quarantined, purge cron)
--------------------------------------------------------------*/

function bp_mc_hide_quarantined($meta_query) {
	$hide = ['key' => '_bp_quarantined', 'compare' => 'NOT EXISTS'];
	return empty($meta_query) ? [$hide] : ['relation' => 'AND', $meta_query, $hide];
}

add_filter('ajax_query_attachments_args', function ($q) {
	$q['meta_query'] = bp_mc_hide_quarantined($q['meta_query'] ?? []);
	return $q;
});

add_action('pre_get_posts', function ($q) {
	global $pagenow;
	if (!is_admin() || !$q->is_main_query() || $pagenow !== 'upload.php') return;
	$q->set('meta_query', bp_mc_hide_quarantined($q->get('meta_query')));
});

add_action('admin_menu', function () {
	add_submenu_page('upload.php', 'Clean Up Library', 'Clean Up Library', 'manage_options', BP_MC_PAGE, 'bp_mc_render_page');
});
add_action('admin_head', function () {
	remove_submenu_page('upload.php', BP_MC_PAGE);
});

add_action('admin_footer-upload.php', function () {
	if (!current_user_can('manage_options')) return;
	$url = admin_url('upload.php?page=' . BP_MC_PAGE);
	?>
	<script>
	(function(){
		var add = document.querySelector('.wrap .page-title-action');
		if (!add) return;
		var a = document.createElement('a');
		a.href = <?php echo wp_json_encode($url); ?>;
		a.className = 'page-title-action';
		a.textContent = 'Clean Up Library';
		add.insertAdjacentElement('afterend', a);
	})();
	</script>
	<?php
});

add_action('admin_init', function () {
	if (!wp_next_scheduled('bp_mc_purge_cron')) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'bp_mc_purge_cron');
});

add_action('bp_mc_purge_cron', function () {
	foreach (bp_mc_quarantined_ids(time() - BP_MC_QUARANTINE_DAYS * DAY_IN_SECONDS, 100) as $aid) bp_mc_purge($aid);
});


/*--------------------------------------------------------------
# Clean Up Screen
--------------------------------------------------------------*/

// Card data for the grid. Quarantined images are served from the quarantine folder.
function bp_mc_card($aid) {
	$base  = bp_mc_base();
	$q     = (int) get_post_meta($aid, '_bp_quarantined', true);
	$bytes = 0;
	$thumb = '';

	if ($q) {
		$rels = (array) get_post_meta($aid, '_bp_quarantine_files', true);
		$meta = wp_get_attachment_metadata($aid);
		$want = !empty($meta['sizes']['thumbnail']['file']) ? $meta['sizes']['thumbnail']['file'] : '';
		foreach ($rels as $rel) {
			$p = $base . '/' . BP_MC_DIR . '/' . $rel;
			if (is_file($p)) $bytes += filesize($p);
			if (!$thumb || ($want && wp_basename($rel) === $want)) $thumb = wp_get_upload_dir()['baseurl'] . '/' . BP_MC_DIR . '/' . $rel;
		}
	} else {
		foreach (bp_mc_attachment_files($aid) as $p) $bytes += filesize($p);
		$thumb = (string) wp_get_attachment_image_url($aid, 'thumbnail');
	}

	$card = [
		'id'    => (int) $aid,
		'thumb' => $thumb,
		'name'  => wp_basename((string) get_post_meta($aid, '_wp_attached_file', true)),
		'size'  => $bytes,
		'date'  => get_the_date('M j, Y', $aid),
	];
	if ($q) $card['left'] = max(0, BP_MC_QUARANTINE_DAYS - (int) floor((time() - $q) / DAY_IN_SECONDS));
	return $card;
}

function bp_mc_render_page() {
	if (!current_user_can('manage_options')) wp_die('You do not have permission to clean up the media library.');

	$tab   = (($_GET['tab'] ?? '') === 'quarantine') ? 'quarantine' : 'scan';
	$q_ids = bp_mc_quarantined_ids();
	$url   = admin_url('upload.php?page=' . BP_MC_PAGE);
	?>
	<div class="wrap bp-mc">
		<h1>Clean Up Library</h1>

		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url($url); ?>" class="nav-tab<?php echo $tab === 'scan' ? ' nav-tab-active' : ''; ?>">Scan</a>
			<a href="<?php echo esc_url($url . '&tab=quarantine'); ?>" class="nav-tab<?php echo $tab === 'quarantine' ? ' nav-tab-active' : ''; ?>">Quarantine (<?php echo count($q_ids); ?>)</a>
		</nav>

		<?php if ($tab === 'scan') : ?>
			<p class="bp-mc-intro">Finds images that aren't attached to any post and aren't used anywhere — post content, custom fields, page heroes, widgets, theme settings, image tags, custom tables, or the child theme's files. Nothing is deleted from here: selected images go to <strong>quarantine</strong> for <?php echo (int) BP_MC_QUARANTINE_DAYS; ?> days first. Their files are moved aside, so if a page was still using one it will show a broken image and you can restore it.</p>

			<p class="bp-mc-controls">
				<label>Only images older than <input type="number" id="bp-mc-days" value="90" min="0" step="1"> days</label>
				<button type="button" class="button button-primary" id="bp-mc-start">Start Scan</button>
			</p>

			<div class="bp-mc-progress" id="bp-mc-progress" hidden><div class="bp-mc-bar"><span></span></div><p></p></div>

			<div class="bp-mc-toolbar" id="bp-mc-toolbar" hidden>
				<button type="button" class="button" data-select="all">Select All</button>
				<button type="button" class="button" data-select="none">Select None</button>
				<span class="bp-mc-tally"></span>
				<button type="button" class="button button-primary" data-op="quarantine">Quarantine Selected</button>
			</div>

			<div class="bp-mc-grid" id="bp-mc-grid"></div>

		<?php else : ?>
			<p class="bp-mc-intro">These images' files have been moved out of the library. Anything still using one shows a broken image — <strong>Restore</strong> puts it back exactly as it was. Each is deleted for good when its countdown reaches zero.</p>

			<?php if ($q_ids) : ?>
				<div class="bp-mc-toolbar" id="bp-mc-toolbar">
					<button type="button" class="button" data-select="all">Select All</button>
					<button type="button" class="button" data-select="none">Select None</button>
					<span class="bp-mc-tally"></span>
					<button type="button" class="button" data-op="restore">Restore Selected</button>
					<button type="button" class="button bp-mc-danger" data-op="delete">Delete Selected Now</button>
				</div>
			<?php else : ?>
				<p><em>Quarantine is empty.</em></p>
			<?php endif; ?>

			<div class="bp-mc-grid" id="bp-mc-grid"></div>
		<?php endif; ?>

		<div class="bp-mc-result" id="bp-mc-result" hidden></div>
	</div>

	<style>
		.bp-mc-intro { max-width: 760px; }
		.bp-mc-controls { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
		.bp-mc-controls input { width: 70px; }
		.bp-mc-progress { max-width: 560px; margin: 16px 0; }
		.bp-mc-bar { height: 10px; background: #dcdcde; border-radius: 5px; overflow: hidden; }
		.bp-mc-bar span { display: block; height: 100%; width: 0; background: #2271b1; transition: width .3s; }
		.bp-mc-toolbar { position: sticky; top: 32px; z-index: 5; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 0; margin: 12px 0; background: #f0f0f1; border-bottom: 1px solid #dcdcde; }
		.bp-mc-tally { margin-right: auto; color: #50575e; }
		.bp-mc-danger { color: #b32d2e !important; border-color: #b32d2e !important; }
		.bp-mc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
		.bp-mc-card { position: relative; display: block; background: #fff; border: 2px solid #dcdcde; border-radius: 6px; overflow: hidden; cursor: pointer; }
		.bp-mc-card:has(input:checked) { border-color: #2271b1; }
		.bp-mc-card input { position: absolute; top: 8px; left: 8px; margin: 0; }
		.bp-mc-card img { display: block; width: 100%; aspect-ratio: 1; object-fit: cover; background: #f6f7f7; }
		.bp-mc-card span { display: block; padding: 4px 8px; font-size: 12px; line-height: 1.4; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.bp-mc-card .bp-mc-meta { padding-top: 0; color: #646970; }
		.bp-mc-result { max-width: 760px; margin-top: 16px; }
		@media (max-width: 576px) { .bp-mc-toolbar { top: 46px; } .bp-mc-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); } }
	</style>

	<script>
	(function(){
		var ajax   = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
		    nonce  = <?php echo wp_json_encode(wp_create_nonce('bp_mc')); ?>,
		    tab    = <?php echo wp_json_encode($tab); ?>,
		    batch  = <?php echo (int) BP_MC_BATCH; ?>,
		    days   = <?php echo (int) BP_MC_QUARANTINE_DAYS; ?>,
		    grid   = document.getElementById('bp-mc-grid'),
		    bar    = document.getElementById('bp-mc-toolbar'),
		    result = document.getElementById('bp-mc-result');

		function fmtSize(b){
			if (!b) return '0 KB';
			return (b < 1048576) ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB';
		}

		function post(op, data){
			var fd = new FormData();
			fd.append('action', 'bp_mc');
			fd.append('op', op);
			fd.append('nonce', nonce);
			Object.keys(data || {}).forEach(function(k){
				[].concat(data[k]).forEach(function(v){ fd.append(Array.isArray(data[k]) ? k + '[]' : k, v); });
			});
			return fetch(ajax, { method: 'POST', credentials: 'same-origin', body: fd })
				.then(function(r){ return r.json(); })
				.then(function(j){ if (!j.success) throw new Error(j.data || 'Request failed.'); return j.data; });
		}

		function addCard(c){
			var label = document.createElement('label'),
			    box   = document.createElement('input'),
			    img   = document.createElement('img'),
			    name  = document.createElement('span'),
			    meta  = document.createElement('span');
			label.className = 'bp-mc-card';
			label.dataset.size = c.size;
			box.type = 'checkbox';
			box.value = c.id;
			box.checked = tab === 'scan';
			img.src = c.thumb;
			img.alt = '';
			img.loading = 'lazy';
			name.textContent = c.name;
			name.title = c.name;
			meta.className = 'bp-mc-meta';
			meta.textContent = fmtSize(c.size) + ' · ' + (tab === 'scan' ? c.date : c.left + ' day' + (c.left === 1 ? '' : 's') + ' left');
			label.append(box, img, name, meta);
			grid.append(label);
		}

		function checked(){
			return [].slice.call(grid.querySelectorAll('input:checked'));
		}

		function tally(){
			if (!bar) return;
			var boxes = checked(), bytes = 0;
			boxes.forEach(function(b){ bytes += +b.closest('.bp-mc-card').dataset.size; });
			bar.querySelector('.bp-mc-tally').textContent = boxes.length + ' of ' + grid.children.length + ' selected · ' + fmtSize(bytes);
		}

		function say(html){
			result.innerHTML = html;
			result.hidden = !html;
		}

		grid.addEventListener('change', tally);

		if (bar) bar.addEventListener('click', function(e){
			var sel = e.target.closest('[data-select]'), act = e.target.closest('[data-op]');
			if (sel) {
				grid.querySelectorAll('input').forEach(function(b){ b.checked = sel.dataset.select === 'all'; });
				tally();
			}
			if (act) run(act.dataset.op, act);
		});

		// Process the selection in batches so a big library never times out a request.
		function run(op, btn){
			var ids = checked().map(function(b){ return b.value; });
			if (!ids.length) return;
			if (op === 'delete' && !confirm('Permanently delete ' + ids.length + ' image(s)? This cannot be undone.')) return;
			if (op === 'quarantine' && !confirm('Move ' + ids.length + ' image(s) to quarantine? They can be restored for ' + days + ' days.')) return;

			var done = 0, skipped = [], buttons = bar.querySelectorAll('button');
			buttons.forEach(function(b){ b.disabled = true; });
			say('');

			(function next(){
				var chunk = ids.splice(0, batch);
				if (!chunk.length) {
					buttons.forEach(function(b){ b.disabled = false; });
					var verb = { quarantine: 'moved to quarantine', restore: 'restored', delete: 'deleted' }[op];
					var html = '<div class="notice notice-success inline"><p><strong>' + done + ' image(s) ' + verb + '.</strong>'
						+ (op === 'quarantine' && done ? ' They\'ll be deleted for good in ' + days + ' days unless restored from the Quarantine tab.' : '') + '</p></div>';
					if (skipped.length) {
						html += '<div class="notice notice-warning inline"><p><strong>' + skipped.length + ' skipped:</strong></p><ul>'
							+ skipped.map(function(s){ var li = document.createElement('li'); li.textContent = s.name + ' — ' + s.reason; return li.outerHTML; }).join('')
							+ '</ul></div>';
					}
					say(html);
					tally();
					return;
				}
				post(op, { ids: chunk }).then(function(d){
					d.ok.forEach(function(id){
						var box = grid.querySelector('input[value="' + id + '"]');
						if (box) box.closest('.bp-mc-card').remove();
					});
					done += d.ok.length;
					skipped = skipped.concat(d.skipped);
					next();
				}).catch(function(err){
					buttons.forEach(function(b){ b.disabled = false; });
					say('<div class="notice notice-error inline"><p>' + err.message + ' (' + done + ' done before the error.)</p></div>');
					tally();
				});
			})();
		}

		if (tab === 'quarantine') {
			(<?php echo wp_json_encode(array_map('bp_mc_card', $q_ids)); ?>).forEach(addCard);
			tally();
			return;
		}

		var start = document.getElementById('bp-mc-start'),
		    prog  = document.getElementById('bp-mc-progress');

		start.addEventListener('click', function(){
			start.disabled = true;
			grid.innerHTML = '';
			bar.hidden = true;
			say('');
			prog.hidden = false;
			var fill = prog.querySelector('span'), text = prog.querySelector('p');
			fill.style.width = '0';
			text.textContent = 'Finding unattached images…';

			post('init', { days: document.getElementById('bp-mc-days').value }).then(function(d){
				var total = d.total;
				if (!total) {
					text.textContent = 'No unattached images in that age range.';
					start.disabled = false;
					return;
				}
				(function step(offset){
					post('step', { offset: offset }).then(function(s){
						s.found.forEach(addCard);
						fill.style.width = Math.round(s.next / total * 100) + '%';
						text.textContent = 'Checked ' + s.next + ' of ' + total + ' unattached images · ' + grid.children.length + ' unused';
						if (!s.done) return step(s.next);
						start.disabled = false;
						text.textContent = 'Done. Checked ' + total + ' unattached images — ' + grid.children.length + ' are not used anywhere.';
						if (grid.children.length) { bar.hidden = false; tally(); }
					}).catch(function(err){
						start.disabled = false;
						text.textContent = err.message;
					});
				})(0);
			}).catch(function(err){
				start.disabled = false;
				text.textContent = err.message;
			});
		});
	})();
	</script>
	<?php
}


/*--------------------------------------------------------------
# AJAX
--------------------------------------------------------------*/

add_action('wp_ajax_bp_mc', function () {
	if (!current_user_can('manage_options')) wp_send_json_error('Not allowed.');
	check_ajax_referer('bp_mc', 'nonce');

	$op  = sanitize_key($_POST['op'] ?? '');
	$ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
	$key = 'bp_mc_scan_' . get_current_user_id();

	if ($op === 'init') {
		$list = bp_mc_candidates((int) ($_POST['days'] ?? 90));
		set_transient($key, $list, 2 * HOUR_IN_SECONDS);
		wp_send_json_success(['total' => count($list)]);
	}

	if ($op === 'step') {
		$list = get_transient($key);
		if (!is_array($list)) wp_send_json_error('Scan expired — start it again.');
		$offset = max(0, (int) ($_POST['offset'] ?? 0));
		$slice  = array_slice($list, $offset, BP_MC_BATCH);
		$used   = bp_media_used_ids($slice);
		$found  = [];
		foreach ($slice as $aid) if (!isset($used[$aid])) $found[] = bp_mc_card($aid);
		$next = $offset + count($slice);
		wp_send_json_success(['next' => $next, 'done' => $next >= count($list), 'found' => $found]);
	}

	$fn = ['quarantine' => 'bp_mc_quarantine', 'restore' => 'bp_mc_restore', 'delete' => 'bp_mc_purge'][$op] ?? '';
	if (!$fn) wp_send_json_error('Unknown request.');

	$ok = $skipped = [];
	foreach (array_slice($ids, 0, BP_MC_BATCH) as $aid) {
		$r = $fn($aid);
		if (is_wp_error($r)) $skipped[] = ['id' => $aid, 'name' => wp_basename((string) get_post_meta($aid, '_wp_attached_file', true)) ?: '#' . $aid, 'reason' => $r->get_error_message()];
		else $ok[] = $aid;
	}
	wp_send_json_success(['ok' => $ok, 'skipped' => $skipped]);
});
