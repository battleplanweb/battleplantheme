<?php
/* Battle Plan Web Design Functions: Database Search & Replace

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Overview
# Menu Registration
# Target Registry (which tables/columns are searchable)
# Screen (form + preview + commit handler)
# Core Scan / Replace (reuses the media-replace serialize engine)
# Snippet Preview
# Helpers
--------------------------------------------------------------*/

/*
 An in-dashboard "Search & Replace" for the whole database — swap a string (a
 renamed company, an old phone number, a moved domain) everywhere it appears,
 in one pass, without SSH or a plugin.

 The dangerous part of any DB search/replace is WordPress's serialized data:
 wp_options / wp_postmeta (ACF, Site Pulse settings, widgets) store PHP-serialized
 strings whose byte-length is encoded inline (s:11:"Acme Paving";). A naive SQL
 REPLACE() changes the text but not the length prefix and SILENTLY CORRUPTS the row.
 So we do NOT run SQL REPLACE — we reuse the framework's own serialize/JSON-aware
 recursive replacer, bp_mr_replace_content() (functions-media-replace.php), which
 unserializes, replaces inside the live structure, and re-serializes safely.

 Safety model, baked in:
   - DRY-RUN FIRST. "Preview" scans and reports match counts + before/after
     snippets per table and writes NOTHING.
   - COMMIT is a separate, nonce-protected step behind a confirmation checkbox,
     and re-scans authoritatively (it does not trust the preview).
   - Case-SENSITIVE, exact-substring match (correct for names/domains).
   - Table picker: safe core-content tables are checked by default; comments,
     users and the meta tables are opt-in; custom (non-core) tables are
     auto-discovered and opt-in, text columns only.
   - Every table/column identifier is whitelisted against the real database;
     every value is LIKE-filtered and prepared.

 Admin-only, capability-gated (manage_options), no settings, no module toggle —
 an always-available agency tool, loaded from functions.php the same way
 functions-media-replace.php is. Pure procedural PHP.

 NOTE: this rewrites DATABASE references only. A string hardcoded in child-theme
 files (style-site.css background image, functions-site.php) is on the filesystem,
 not in the DB, and is not touched — same caveat as Media Replace.
*/

if (!defined('ABSPATH')) exit;

if (!defined('BP_SR_PAGE'))    define('BP_SR_PAGE', 'bp-search-replace');
if (!defined('BP_SR_CAP'))     define('BP_SR_CAP', 'manage_options');
if (!defined('BP_SR_MIN_LEN')) define('BP_SR_MIN_LEN', 2);   // reject a search term shorter than this
if (!defined('BP_SR_SAMPLES')) define('BP_SR_SAMPLES', 8);   // max before/after snippets shown in a preview


/*--------------------------------------------------------------
# Menu Registration
--------------------------------------------------------------*/

// Lives under Tools, where WordPress keeps database utilities (Export, etc.).
add_action('admin_menu', function () {
	add_management_page(
		__('Search & Replace', 'battleplan'),
		__('Search & Replace', 'battleplan'),
		BP_SR_CAP,
		BP_SR_PAGE,
		'bp_sr_render_page'
	);
});


/*--------------------------------------------------------------
# Target Registry (which tables/columns are searchable)
--------------------------------------------------------------*/
/*
 Every searchable location is described as a "target": one physical table, its
 primary key, and the text columns to scan. Targets are grouped so the UI can
 default the safe content locations ON and leave the rest opt-in. Custom (non-core)
 tables are discovered from the live schema and appended as opt-in targets.

 Each target: [
   'key'    => stable id used in the form,
   'label'  => human label,
   'group'  => 'content' | 'extended' | 'custom',
   'table'  => real table name (already prefixed),
   'pk'     => single primary-key column,
   'cols'   => [ text columns to search/replace ],
   'default'=> bool (checked by default),
   'strict' => bool (unserialize in strict mode — objects not instantiated; for post_content),
   'cache'  => '' | 'post' | 'options' | 'term'   (how to bust WP caches after a commit),
   'cache_col' => optional column holding the id to bust (e.g. postmeta.post_id),
 ]
*/
function bp_sr_targets() {
	global $wpdb;
	static $targets = null;
	if ($targets !== null) return $targets;

	$targets = [];

	// --- Core content (default ON) ---
	$targets['posts'] = [
		'key' => 'posts', 'label' => __('Posts & pages content (post_content, title, excerpt) — incl. Elements', 'battleplan'),
		'group' => 'content', 'table' => $wpdb->posts, 'pk' => 'ID',
		'cols' => ['post_content', 'post_title', 'post_excerpt'],
		'default' => true, 'strict' => true, 'cache' => 'post',
	];
	$targets['postmeta'] = [
		'key' => 'postmeta', 'label' => __('Post meta (ACF fields, Page Top / Page Bottom, etc.)', 'battleplan'),
		'group' => 'content', 'table' => $wpdb->postmeta, 'pk' => 'meta_id',
		'cols' => ['meta_value'],
		'default' => true, 'strict' => false, 'cache' => 'post', 'cache_col' => 'post_id',
	];
	$targets['options'] = [
		'key' => 'options', 'label' => __('Site options (settings; serialize-safe)', 'battleplan'),
		'group' => 'content', 'table' => $wpdb->options, 'pk' => 'option_id',
		'cols' => ['option_value'],
		'default' => true, 'strict' => false, 'cache' => 'options',
	];
	$targets['terms'] = [
		'key' => 'terms', 'label' => __('Category / tag names', 'battleplan'),
		'group' => 'content', 'table' => $wpdb->terms, 'pk' => 'term_id',
		'cols' => ['name'],
		'default' => true, 'strict' => false, 'cache' => 'term',
	];
	$targets['term_taxonomy'] = [
		'key' => 'term_taxonomy', 'label' => __('Category / tag descriptions', 'battleplan'),
		'group' => 'content', 'table' => $wpdb->term_taxonomy, 'pk' => 'term_taxonomy_id',
		'cols' => ['description'],
		'default' => true, 'strict' => false, 'cache' => 'term',
	];

	// --- Extended (opt-in) ---
	$targets['comments'] = [
		'key' => 'comments', 'label' => __('Comments (content, author name, email, URL)', 'battleplan'),
		'group' => 'extended', 'table' => $wpdb->comments, 'pk' => 'comment_ID',
		'cols' => ['comment_content', 'comment_author', 'comment_author_email', 'comment_author_url'],
		'default' => false, 'strict' => false, 'cache' => '',
	];
	$targets['commentmeta'] = [
		'key' => 'commentmeta', 'label' => __('Comment meta', 'battleplan'),
		'group' => 'extended', 'table' => $wpdb->commentmeta, 'pk' => 'meta_id',
		'cols' => ['meta_value'],
		'default' => false, 'strict' => false, 'cache' => '',
	];
	$targets['termmeta'] = [
		'key' => 'termmeta', 'label' => __('Term meta', 'battleplan'),
		'group' => 'extended', 'table' => $wpdb->termmeta, 'pk' => 'meta_id',
		'cols' => ['meta_value'],
		'default' => false, 'strict' => false, 'cache' => 'term',
	];
	$targets['usermeta'] = [
		'key' => 'usermeta', 'label' => __('User meta', 'battleplan'),
		'group' => 'extended', 'table' => $wpdb->usermeta, 'pk' => 'umeta_id',
		'cols' => ['meta_value'],
		'default' => false, 'strict' => false, 'cache' => '',
	];
	$targets['users'] = [
		'key' => 'users', 'label' => __('Users (display name, URL) — leave off unless renaming a person', 'battleplan'),
		'group' => 'extended', 'table' => $wpdb->users, 'pk' => 'ID',
		'cols' => ['display_name', 'user_url'],
		'default' => false, 'strict' => false, 'cache' => '',
	];

	// --- Custom / non-core tables (opt-in, auto-discovered) ---
	foreach (bp_sr_custom_tables() as $tbl => $info) {
		$targets['custom::' . $tbl] = [
			'key' => 'custom::' . $tbl,
			'label' => sprintf(__('%1$s — columns: %2$s', 'battleplan'), $tbl, implode(', ', $info['cols'])),
			'group' => 'custom', 'table' => $tbl, 'pk' => $info['pk'],
			'cols' => $info['cols'],
			'default' => false, 'strict' => false, 'cache' => '',
		];
	}

	return $targets;
}

/*
 Discover non-core tables owned by this install (prefix-matched) that have a single
 primary key and at least one text column. Returns [ table => ['pk'=>..,'cols'=>[..]] ].
 Text-typed columns only (char/varchar/*text) so we never touch numeric/date/blob data.
*/
function bp_sr_custom_tables() {
	global $wpdb;
	static $found = null;
	if ($found !== null) return $found;
	$found = [];

	// Core tables we already cover (or deliberately never touch, e.g. term_relationships).
	$core = [
		$wpdb->posts, $wpdb->postmeta, $wpdb->options, $wpdb->terms, $wpdb->term_taxonomy,
		$wpdb->term_relationships, $wpdb->termmeta, $wpdb->comments, $wpdb->commentmeta,
		$wpdb->users, $wpdb->usermeta, $wpdb->links,
	];
	$core = array_map('strtolower', array_filter($core));

	$prefix = $wpdb->prefix;
	$like   = $wpdb->esc_like($prefix) . '%';
	$tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
	if (!$tables) return $found;

	foreach ($tables as $tbl) {
		if (in_array(strtolower($tbl), $core, true)) continue;

		// Single-column primary key only (composite/none → skip: no safe per-row WHERE).
		$keys = $wpdb->get_results("SHOW KEYS FROM `{$tbl}` WHERE Key_name = 'PRIMARY'");
		if (!$keys || count($keys) !== 1) continue;
		$pk = $keys[0]->Column_name;

		$cols = [];
		$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$tbl}`");
		foreach ((array) $columns as $c) {
			$type = strtolower(preg_replace('/\(.*$/', '', $c->Type)); // varchar(255) -> varchar
			if (in_array($type, ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'], true)) {
				$cols[] = $c->Field;
			}
		}
		if ($cols) $found[$tbl] = ['pk' => $pk, 'cols' => $cols];
	}

	return $found;
}


/*--------------------------------------------------------------
# Screen (form + preview + commit handler)
--------------------------------------------------------------*/

function bp_sr_render_page() {
	if (!current_user_can(BP_SR_CAP)) {
		wp_die(esc_html__('You do not have permission to use Search & Replace.', 'battleplan'));
	}

	$search   = '';
	$replace  = '';
	$selected = [];
	$mode     = '';   // '' | 'preview' | 'done'
	$error    = '';
	$report   = null;

	$targets = bp_sr_targets();

	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
		check_admin_referer('bp_sr_run');

		$search   = isset($_POST['bp_sr_search'])  ? (string) wp_unslash($_POST['bp_sr_search'])  : '';
		$replace  = isset($_POST['bp_sr_replace']) ? (string) wp_unslash($_POST['bp_sr_replace']) : '';
		$selected = isset($_POST['bp_sr_tables']) && is_array($_POST['bp_sr_tables'])
			? array_map('strval', (array) wp_unslash($_POST['bp_sr_tables'])) : [];
		$selected = array_values(array_intersect($selected, array_keys($targets))); // whitelist

		$commit = !empty($_POST['bp_sr_commit']);

		// Validate.
		if ($search === '') {
			$error = __('Enter a string to search for.', 'battleplan');
		} elseif (mb_strlen($search) < BP_SR_MIN_LEN) {
			$error = sprintf(__('The search string must be at least %d characters — a shorter one matches too much.', 'battleplan'), BP_SR_MIN_LEN);
		} elseif ($search === $replace) {
			$error = __('The search and replace strings are identical — nothing would change.', 'battleplan');
		} elseif (empty($selected)) {
			$error = __('Select at least one table to search.', 'battleplan');
		} elseif ($commit && empty($_POST['bp_sr_confirm'])) {
			$error = __('Tick the confirmation box before running the replacement.', 'battleplan');
		}

		if (!$error) {
			$report = bp_sr_run($search, $replace, $selected, $commit);
			$mode   = $commit ? 'done' : 'preview';
		}
	}

	bp_sr_render_form($targets, $search, $replace, $selected, $mode, $error, $report);
}

function bp_sr_render_form($targets, $search, $replace, $selected, $mode, $error, $report) {
	// On first load, default-check the safe content targets.
	if ($mode === '' && empty($selected) && !$error) {
		foreach ($targets as $t) if (!empty($t['default'])) $selected[] = $t['key'];
	}
	$is_checked = function ($key) use ($selected) { return in_array($key, $selected, true); };

	$groups = [
		'content'  => __('Content (recommended)', 'battleplan'),
		'extended' => __('Comments, users & meta (opt in)', 'battleplan'),
		'custom'   => __('Other tables on this database (opt in)', 'battleplan'),
	];
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Search &amp; Replace', 'battleplan'); ?></h1>
		<p class="description" style="max-width:60em">
			<?php esc_html_e('Replace a string everywhere it appears in the database — serialize-safe, so ACF fields and settings survive intact. Always Preview first; nothing is written until you run the replacement.', 'battleplan'); ?>
		</p>

		<?php if ($error) : ?>
			<div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
		<?php endif; ?>

		<?php if ($mode === 'done' && $report) : ?>
			<div class="notice notice-success"><p><strong><?php
				printf(esc_html__('Done. Replaced %1$d occurrence(s) across %2$d row(s) in %3$d table(s).', 'battleplan'),
					(int) $report['occurrences'], (int) $report['rows'], (int) $report['tables_changed']);
			?></strong></p></div>
		<?php elseif ($mode === 'preview' && $report) : ?>
			<div class="notice notice-warning"><p><strong><?php
				if ((int) $report['occurrences'] === 0) {
					esc_html_e('No matches found. Nothing to replace.', 'battleplan');
				} else {
					printf(esc_html__('Preview only — nothing has been changed yet. Found %1$d occurrence(s) across %2$d row(s).', 'battleplan'),
						(int) $report['occurrences'], (int) $report['rows']);
				}
			?></strong></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url(admin_url('tools.php?page=' . BP_SR_PAGE)); ?>">
			<?php wp_nonce_field('bp_sr_run'); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bp_sr_search"><?php esc_html_e('Search for', 'battleplan'); ?></label></th>
					<td><input name="bp_sr_search" id="bp_sr_search" type="text" class="regular-text" value="<?php echo esc_attr($search); ?>" autocomplete="off" spellcheck="false" required>
						<p class="description"><?php esc_html_e('Exact, case-sensitive. e.g. an old company name.', 'battleplan'); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="bp_sr_replace"><?php esc_html_e('Replace with', 'battleplan'); ?></label></th>
					<td><input name="bp_sr_replace" id="bp_sr_replace" type="text" class="regular-text" value="<?php echo esc_attr($replace); ?>" autocomplete="off" spellcheck="false">
						<p class="description"><?php esc_html_e('Leave empty to remove the search string entirely.', 'battleplan'); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Search in', 'battleplan'); ?></th>
					<td>
						<?php foreach ($groups as $gkey => $glabel) :
							$in_group = array_filter($targets, function ($t) use ($gkey) { return $t['group'] === $gkey; });
							if (empty($in_group)) continue; ?>
							<fieldset style="margin:0 0 1em">
								<legend style="font-weight:600;padding:.25em 0"><?php echo esc_html($glabel); ?></legend>
								<?php foreach ($in_group as $t) : ?>
									<label style="display:block;margin:.15em 0">
										<input type="checkbox" name="bp_sr_tables[]" value="<?php echo esc_attr($t['key']); ?>" <?php checked($is_checked($t['key'])); ?>>
										<?php echo esc_html($t['label']); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>

			<?php // After a preview WITH matches, expose the commit control behind a confirmation. ?>
			<?php if ($mode === 'preview' && $report && (int) $report['occurrences'] > 0) : ?>
				<div style="background:#fcf9e8;border:1px solid #dba617;border-radius:4px;padding:.75em 1em;max-width:60em;margin:0 0 1em">
					<label style="font-weight:600">
						<input type="checkbox" name="bp_sr_confirm" value="1">
						<?php esc_html_e('I understand this permanently modifies the database. (WP Engine keeps automatic backups if I need to roll back.)', 'battleplan'); ?>
					</label>
				</div>
				<p class="submit" style="margin-top:0">
					<button type="submit" name="bp_sr_preview" value="1" class="button button-secondary button-large"><?php esc_html_e('Re-run Preview', 'battleplan'); ?></button>
					<button type="submit" name="bp_sr_commit" value="1" class="button button-primary button-large"><?php esc_html_e('Run Replacement', 'battleplan'); ?></button>
				</p>
			<?php else : ?>
				<p class="submit">
					<button type="submit" name="bp_sr_preview" value="1" class="button button-primary button-large"><?php esc_html_e('Preview Changes', 'battleplan'); ?></button>
				</p>
			<?php endif; ?>
		</form>

		<?php if ($report && !empty($report['per_table'])) : ?>
			<h2><?php esc_html_e('Matches by table', 'battleplan'); ?></h2>
			<table class="widefat striped" style="max-width:60em">
				<thead><tr>
					<th><?php esc_html_e('Table', 'battleplan'); ?></th>
					<th style="text-align:right"><?php esc_html_e('Rows', 'battleplan'); ?></th>
					<th style="text-align:right"><?php esc_html_e('Occurrences', 'battleplan'); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ($report['per_table'] as $key => $stat) :
					$label = isset($targets[$key]) ? $targets[$key]['label'] : $key; ?>
					<tr>
						<td><?php echo esc_html($label); ?></td>
						<td style="text-align:right"><?php echo (int) $stat['rows']; ?></td>
						<td style="text-align:right"><?php echo (int) $stat['occurrences']; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if (!empty($report['samples'])) : ?>
				<h2><?php esc_html_e('Sample changes', 'battleplan'); ?></h2>
				<table class="widefat striped" style="max-width:70em">
					<thead><tr>
						<th style="width:20%"><?php esc_html_e('Location', 'battleplan'); ?></th>
						<th><?php esc_html_e('Before → After (context around each match)', 'battleplan'); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ($report['samples'] as $s) : ?>
						<tr>
							<td><code><?php echo esc_html($s['where']); ?></code></td>
							<td style="font-family:monospace;font-size:12px;line-height:1.6">
								<div style="color:#b32d2e">&minus; <?php echo $s['before']; // pre-escaped in bp_sr_snippet() ?></div>
								<div style="color:#1a7f37">+ <?php echo $s['after'];  // pre-escaped ?></div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}


/*--------------------------------------------------------------
# Core Scan / Replace (reuses the media-replace serialize engine)
--------------------------------------------------------------*/
/*
 Walk every selected target. For each candidate row (LIKE-filtered on the raw
 search term), run the framework's serialize/JSON-aware replacer per column.
   $commit === false → dry run: count matches, collect sample snippets, write nothing.
   $commit === true  → write changed columns and bust the relevant WP caches.
 Returns a report array consumed by the form renderer.
*/
function bp_sr_run($search, $replace, $selected, $commit) {
	global $wpdb;

	if (function_exists('set_time_limit')) @set_time_limit(0);

	// The engine we reuse lives in functions-media-replace.php (admin-only, loaded
	// just before this file). Guard in case load order ever changes.
	if (!function_exists('bp_mr_replace_content')) {
		require_once get_template_directory() . '/functions-media-replace.php';
	}

	$targets = bp_sr_targets();
	$like    = '%' . $wpdb->esc_like($search) . '%';

	$report = [
		'occurrences'    => 0,
		'rows'           => 0,
		'tables_changed' => 0,
		'per_table'      => [],
		'samples'        => [],
	];
	$options_dirty = false;
	$term_dirty    = false;

	foreach ($selected as $key) {
		if (!isset($targets[$key])) continue;
		$t     = $targets[$key];
		$table = $t['table'];
		$pk    = $t['pk'];
		$cols  = $t['cols'];

		// Columns to SELECT: pk + searchable cols (+ post_id for postmeta cache, + option_name for options cache).
		$select = array_merge([$pk], $cols);
		if (!empty($t['cache_col'])) $select[] = $t['cache_col'];
		if ($t['cache'] === 'options') $select[] = 'option_name';
		$select = array_values(array_unique($select));
		$select_sql = implode(', ', array_map(function ($c) { return "`{$c}`"; }, $select));

		// WHERE: any searchable column contains the raw term.
		$where = implode(' OR ', array_map(function ($c) { return "`{$c}` LIKE %s"; }, $cols));
		$params = array_fill(0, count($cols), $like);

		$sql  = "SELECT {$select_sql} FROM `{$table}` WHERE {$where}";
		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!$rows) continue;

		$tbl_rows = 0;
		$tbl_occ  = 0;

		foreach ($rows as $row) {
			$row_changed = false;
			$updates     = [];

			foreach ($cols as $col) {
				$val = $row[$col];
				if ($val === null || $val === '') continue;

				// Raw occurrence count for this column (case-sensitive substring).
				$occ = substr_count($val, $search);
				if ($occ === 0) continue;

				$new = bp_mr_replace_content($val, $search, $replace, false, !empty($t['strict']));
				if ($new === $val) continue; // e.g. term only present in a length prefix — engine correctly no-ops

				$tbl_occ    += $occ;
				$row_changed = true;
				$updates[$col] = $new;

				// Collect a few before/after samples for the UI (from the raw value).
				if (count($report['samples']) < BP_SR_SAMPLES) {
					$report['samples'][] = [
						'where'  => $key . ' #' . $row[$pk] . ' · ' . $col,
						'before' => bp_sr_snippet($val, $search, $search),
						'after'  => bp_sr_snippet($val, $search, $replace),
					];
				}
			}

			if (!$row_changed) continue;
			$tbl_rows++;

			if ($commit) {
				// Build one prepare() for the whole statement — never nest prepare()
				// (a stray % in an escaped value would be re-interpreted by an outer pass).
				$set_sql = [];
				$args    = [];
				foreach ($updates as $col => $new) {
					$set_sql[] = "`{$col}` = %s";
					$args[]    = $new;
				}
				$args[] = $row[$pk];
				$wpdb->query($wpdb->prepare(
					"UPDATE `{$table}` SET " . implode(', ', $set_sql) . " WHERE `{$pk}` = %s",
					$args
				));

				// Bust caches so the raw UPDATE isn't masked by stale object cache.
				switch ($t['cache']) {
					case 'post':
						$pid = !empty($t['cache_col']) ? (int) ($row[$t['cache_col']] ?? 0) : (int) $row[$pk];
						if ($pid) clean_post_cache($pid);
						break;
					case 'options':
						$options_dirty = true;
						if (!empty($row['option_name'])) wp_cache_delete($row['option_name'], 'options');
						break;
					case 'term':
						$term_dirty = true;
						break;
				}
			}
		}

		if ($tbl_occ > 0) {
			$report['per_table'][$key] = ['rows' => $tbl_rows, 'occurrences' => $tbl_occ];
			$report['occurrences'] += $tbl_occ;
			$report['rows']        += $tbl_rows;
		}
	}

	$report['tables_changed'] = count($report['per_table']);

	if ($commit) {
		if ($options_dirty) wp_cache_delete('alloptions', 'options'); // autoload cache
		if ($term_dirty && function_exists('clean_term_cache')) clean_term_cache([], '', false);
	}

	return $report;
}


/*--------------------------------------------------------------
# Snippet Preview
--------------------------------------------------------------*/
/*
 Build a short, HTML-escaped context window around the FIRST occurrence of the raw
 search term, with the (post-replace) term highlighted. Escaping happens first, then
 the escaped needle is wrapped in <mark> — so the highlight can't inject markup.
 For "before" pass $needle === $search; for "after" pass $needle === $replace applied
 to a copy.
*/
function bp_sr_snippet($value, $search, $render_as) {
	$pos = strpos($value, $search);
	if ($pos === false) return esc_html(mb_substr($value, 0, 120));

	$pad   = 50;
	$start = max(0, $pos - $pad);
	$len   = strlen($search) + ($pad * 2);
	$chunk = substr($value, $start, $len);

	// Byte-slicing can split a UTF-8 char at either edge; drop any invalid bytes so
	// esc_html() (which blanks invalid UTF-8) still renders the snippet.
	if (function_exists('mb_convert_encoding')) {
		$chunk = mb_convert_encoding($chunk, 'UTF-8', 'UTF-8');
	}

	// Replace the raw needle with the render value inside this window, then escape.
	$shown = ($render_as === $search) ? $chunk : str_replace($search, $render_as, $chunk);
	$shown = esc_html($shown);

	// Highlight the (escaped) rendered term. Empty render (deletion) → nothing to mark.
	$needle_esc = esc_html($render_as);
	if ($needle_esc !== '') {
		$shown = str_replace($needle_esc, '<mark>' . $needle_esc . '</mark>', $shown);
	}

	$prefix = $start > 0 ? '…' : '';
	$suffix = ($start + $len) < strlen($value) ? '…' : '';
	return $prefix . $shown . $suffix;
}


/*--------------------------------------------------------------
# Helpers
--------------------------------------------------------------*/

// (reserved for future helpers)
