<?php /* Battle Plan Web Design: Per-Version Migrations

One-time tasks that run once per framework version bump. Detected via the
`bp_framework_last_seen_version` option vs `_BP_VERSION`. Each migration runs
on the first request after the new framework arrives (admin or front-end);
the option is bumped immediately so the work doesn't repeat. Failures don't
re-queue the work — fix forward in the next release.

Hook is registered at `init` priority 99 so other plugins/themes have settled
their own init work first. The short-circuit on matching version is a single
get_option call, so the cost on already-migrated sites is negligible.
*/

add_action('init', 'bp_run_framework_migrations', 99);

function bp_run_framework_migrations() {
	if (!defined('_BP_VERSION')) return;

	$last_seen = (string) get_option('bp_framework_last_seen_version', '');
	if ($last_seen === _BP_VERSION) return;

	// Bump immediately so partial failures don't re-fire on every request.
	update_option('bp_framework_last_seen_version', _BP_VERSION, false);

	$removed_plugins = bp_migrate_remove_legacy_plugins();
	$cf7_result      = bp_migrate_scan_cf7_references();

	if (!empty($removed_plugins) || !empty($cf7_result['rewritten']) || !empty($cf7_result['unhandled'])) {
		bp_migrate_send_report($last_seen, $removed_plugins, $cf7_result);
	}
}

// Deactivate + delete the now-redundant CF7 and Akismet plugins. The framework's
// `[contact-form-7]` shortcode interceptor still handles legacy CF7 references in
// post content after the plugin files are gone, so existing pages keep rendering.
function bp_migrate_remove_legacy_plugins() {
	if (!function_exists('deactivate_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';

	$targets = [
		'contact-form-7/wp-contact-form-7.php',
		'akismet/akismet.php',
	];

	$report = [];
	foreach ($targets as $plugin) {
		$status = [];
		if (is_plugin_active($plugin) || is_plugin_active_for_network($plugin)) {
			deactivate_plugins($plugin, true);
			$status[] = 'deactivated';
		}
		if (file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
			$result = delete_plugins([$plugin]);
			$status[] = (is_wp_error($result) || $result === false)
				? 'delete failed (' . (is_wp_error($result) ? $result->get_error_message() : 'unknown') . ')'
				: 'deleted';
		}
		if (!empty($status)) {
			$report[$plugin] = implode(', ', $status);
		}
	}
	return $report;
}

// Walk published post content for any `[contact-form-7 id="X" title="Y"]`
// shortcodes. For each, decide a target framework shortcode by checking site
// `bp_form_mapping` filters and the standard title routing. If a target is
// known, rewrite the post content in place (str_replace of the exact CF7
// shortcode found). If no target is known, record the reference so the
// dispatch email tells you which page still needs manual attention.
function bp_migrate_scan_cf7_references() {
	global $wpdb;

	$rows = $wpdb->get_results(
		"SELECT ID, post_title, post_type, post_content
		 FROM {$wpdb->posts}
		 WHERE post_status = 'publish'
		   AND post_content LIKE '%[contact-form-7%'"
	);
	if (empty($rows)) return ['rewritten' => [], 'unhandled' => []];

	$rewritten = [];
	$unhandled = [];

	foreach ($rows as $row) {
		if (!preg_match_all('/\[contact-form-7\s+([^\]]+)\]/i', $row->post_content, $matches, PREG_SET_ORDER)) continue;

		$new_content = $row->post_content;
		$post_rewrites = [];

		foreach ($matches as $match) {
			$full_shortcode = $match[0];
			$attrs_str      = $match[1];
			$atts           = shortcode_parse_atts($attrs_str) ?: [];
			$id             = (string) ($atts['id']    ?? '');
			$title          = (string) ($atts['title'] ?? '');

			$target = bp_migrate_cf7_target($id, $title);

			if ($target) {
				if (strpos($new_content, $full_shortcode) !== false) {
					$new_content = str_replace($full_shortcode, $target, $new_content);
					$post_rewrites[] = ['from' => $full_shortcode, 'to' => $target];
				}
			} else {
				$unhandled[] = [
					'post_id'   => (int) $row->ID,
					'post_type' => $row->post_type,
					'page'      => $row->post_title ?: '(untitled)',
					'permalink' => get_permalink($row->ID),
					'edit_link' => get_edit_post_link($row->ID, ''),
					'id'        => $id,
					'title'     => $title,
				];
			}
		}

		// Persist post content changes via raw $wpdb so we don't trip
		// wp_insert_post_data filters and don't bump post_modified (which
		// would falsely show the page as freshly edited in content-freshness
		// checks). Cache flush keeps frontend consistent.
		if ($new_content !== $row->post_content) {
			$ok = $wpdb->update(
				$wpdb->posts,
				['post_content' => $new_content],
				['ID' => $row->ID]
			);
			if ($ok !== false) {
				clean_post_cache($row->ID);
				foreach ($post_rewrites as $r) {
					$rewritten[] = [
						'post_id'   => (int) $row->ID,
						'post_type' => $row->post_type,
						'page'      => $row->post_title ?: '(untitled)',
						'permalink' => get_permalink($row->ID),
						'edit_link' => get_edit_post_link($row->ID, ''),
						'from'      => $r['from'],
						'to'        => $r['to'],
					];
				}
			}
		}
	}

	return ['rewritten' => $rewritten, 'unhandled' => $unhandled];
}

// Mirrors the classification logic in functions-forms.php's [contact-form-7]
// intercept. Returns the framework shortcode string to swap in, or null if
// the CF7 reference is custom and we can't safely auto-rewrite it.
function bp_migrate_cf7_target($id, $title) {
	$mapped = apply_filters('bp_form_mapping', null, $id, $title);
	if (is_string($mapped) && $mapped !== '') return $mapped;

	return bp_cf7_title_to_shortcode($title);
}

function bp_migrate_send_report($previous_version, $removed_plugins, $cf7_result) {
	$customer_info = customer_info();
	$site_name     = $customer_info['name'] ?? get_bloginfo('name');
	$site_url      = home_url();

	$subject = 'Framework update on ' . $site_name . ' (' . _BP_VERSION . ')';

	$body  = '<p><strong>Site:</strong> <a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a></p>';
	$body .= '<p><strong>Framework:</strong> ' . esc_html($previous_version ?: '(first install)') . ' → ' . esc_html(_BP_VERSION) . '</p>';

	if (!empty($removed_plugins)) {
		$body .= '<h3 style="color:#2e7d32;">Plugins removed</h3><ul>';
		foreach ($removed_plugins as $plugin => $status) {
			$body .= '<li>' . esc_html($plugin) . ' — ' . esc_html($status) . '</li>';
		}
		$body .= '</ul>';
	}

	if (!empty($cf7_result['rewritten'])) {
		$body .= '<h3 style="color:#2e7d32;">Pages auto-rewritten</h3>';
		$body .= '<p>These pages had their <code>[contact-form-7]</code> shortcode swapped for the framework equivalent. No action needed — just listed for the record.</p>';
		$body .= '<ul>';
		foreach ($cf7_result['rewritten'] as $r) {
			$line  = '<strong>' . esc_html($r['page']) . '</strong>';
			$line .= ' <em style="color:#888;">(' . esc_html($r['post_type']) . ')</em>';
			$line .= '<br>';
			$line .= '&nbsp;&nbsp;<a href="' . esc_url($r['permalink']) . '">View</a>';
			if ($r['edit_link']) $line .= ' · <a href="' . esc_url($r['edit_link']) . '">Edit</a>';
			$line .= '<br>';
			$line .= '&nbsp;&nbsp;<code>' . esc_html($r['from']) . '</code> → <code>' . esc_html($r['to']) . '</code>';
			$body .= '<li>' . $line . '</li>';
		}
		$body .= '</ul>';
	}

	if (!empty($cf7_result['unhandled'])) {
		$body .= '<h3 style="color:#c62828;">Custom CF7 references — manual action needed</h3>';
		$body .= '<p>These pages reference Contact Form 7 IDs that aren\'t covered by the framework\'s standard title routing or a <code>bp_form_mapping</code> filter. Each one currently falls back to <code>[bp-contact-form]</code> — replace the shortcode on the page (or add a mapping in <code>functions-site.php</code>) to restore the customized form.</p>';
		$body .= '<ul>';
		foreach ($cf7_result['unhandled'] as $f) {
			$line  = '<strong>' . esc_html($f['page']) . '</strong>';
			$line .= ' <em style="color:#888;">(' . esc_html($f['post_type']) . ')</em>';
			$line .= '<br>';
			$line .= '&nbsp;&nbsp;<a href="' . esc_url($f['permalink']) . '">View</a>';
			if ($f['edit_link']) $line .= ' · <a href="' . esc_url($f['edit_link']) . '">Edit</a>';
			$line .= '<br>';
			$line .= '&nbsp;&nbsp;<code>[contact-form-7';
			if ($f['id']    !== '') $line .= ' id="'    . esc_html($f['id'])    . '"';
			if ($f['title'] !== '') $line .= ' title="' . esc_html($f['title']) . '"';
			$line .= ']</code>';
			$body .= '<li>' . $line . '</li>';
		}
		$body .= '</ul>';
	}

	$body .= '<p style="color:#999;font-size:12px;">This message was sent once for the ' . esc_html(_BP_VERSION) . ' upgrade. Subsequent page loads on this site will not re-send.</p>';

	wp_mail(
		'glendon@bp-webdev.com',
		$subject,
		$body,
		['Content-Type: text/html; charset=UTF-8']
	);
}
