<?php
/* Battle Plan Web Design: Keyword Rankings — DataForSEO Labs */

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Guards & Settings
# Storage Helpers
# Keyword Classification
# DataForSEO Labs API
# Cron Runner
# Dashboard Widget
# Admin Management Page
--------------------------------------------------------------*/

if (defined('BP_KW_RANKINGS_LOADED')) return;
define('BP_KW_RANKINGS_LOADED', true);


/*--------------------------------------------------------------
# Guards & Settings
--------------------------------------------------------------*/

function bp_kw_api_creds(): array {
	return [
		'login'    => defined('DATAFORSEO_LOGIN')    ? DATAFORSEO_LOGIN    : get_option('bp_dataforseo_login', ''),
		'password' => defined('DATAFORSEO_PASSWORD') ? DATAFORSEO_PASSWORD : get_option('bp_dataforseo_password', ''),
	];
}


function bp_kw_site_domain(): string {
	return str_replace(['https://', 'http://'], '', rtrim(get_bloginfo('url'), '/'));
}

function bp_kw_site_brand(): string {
	$domain = preg_replace('/^www\./', '', bp_kw_site_domain());
	return strtolower(explode('.', $domain)[0]);
}


/*--------------------------------------------------------------
# Storage Helpers

bp_kw_tracked — keywords seen in the most recent Labs snapshot:
	[ md5(keyword) => [
		'keyword'    => 'ac repair allen tx',
		'url'        => 'https://...',
		'search_vol' => 170,
		'group'      => 'geo' | 'jobsite' | 'blog' | 'main',
		'first_seen' => 'YYYY-MM-DD',
		'last_seen'  => 'YYYY-MM-DD',
	], ... ]

bp_kw_history — rank history per keyword:
	[ md5(keyword) => [ 'YYYY-MM-DD' => rank_int, ... ], ... ]

bp_kw_rejected — permanent hide list:
	[ md5(keyword) => keyword_string, ... ]
--------------------------------------------------------------*/

function bp_kw_tracked(): array {
	return get_option('bp_kw_tracked', []) ?: [];
}

function bp_kw_history(): array {
	return get_option('bp_kw_history', []) ?: [];
}

function bp_kw_rejected(): array {
	return get_option('bp_kw_rejected', []) ?: [];
}

function bp_kw_save_tracked(array $data): void {
	update_option('bp_kw_tracked', $data, false);
}

function bp_kw_save_history(array $data): void {
	update_option('bp_kw_history', $data, false);
}

function bp_kw_save_rejected(array $data): void {
	update_option('bp_kw_rejected', $data, false);
}

// Positive = improved (moved up), negative = dropped
function bp_kw_rank_change(string $kw_key, array $history): ?int {
	if (empty($history[$kw_key])) return null;
	$kh = $history[$kw_key];
	krsort($kh);
	$dates = array_keys($kh);
	if (count($dates) < 2) return null;
	$cur  = $kh[$dates[0]];
	$prev = $kh[$dates[1]];
	if (!$cur || !$prev) return null;
	return $prev - $cur;
}

// Returns plain array of rank ints for the sparkline (last N weeks of snapshots)
function bp_kw_sparkline(string $kw_key, array $history, int $weeks = 8): array {
	if (empty($history[$kw_key])) return [];
	$cutoff = date('Y-m-d', strtotime("-{$weeks} weeks"));
	$slice  = array_filter($history[$kw_key], function($d) use ($cutoff) { return $d >= $cutoff; }, ARRAY_FILTER_USE_KEY);
	ksort($slice);
	return array_values($slice);
}


/*--------------------------------------------------------------
# Keyword Classification
--------------------------------------------------------------*/

// Extract city (and city+state) patterns from service-area slugs.
// Slugs may be bare cities ('frisco-tx') or prefixed ('air-conditioner-repair-humble-tx').
// In all cases the last word is the 2-letter state and the word before it is the city.
function bp_kw_get_area_patterns(): array {
	static $patterns = null;
	if ($patterns !== null) return $patterns;
	$terms = get_terms(['taxonomy' => 'jobsite_geo-service-areas', 'hide_empty' => false, 'fields' => 'slugs']);
	$patterns = [];
	foreach (is_wp_error($terms) ? [] : (array) $terms as $slug) {
		$words = explode(' ', str_replace('-', ' ', $slug));
		// Must have at least state + city (2 words)
		if (count($words) < 2) continue;
		$state = end($words);
		if (strlen($state) !== 2) continue; // last word isn't a state code, skip
		$city = $words[count($words) - 2];
		if (!$city) continue;
		$patterns[] = $city . ' ' . $state; // e.g. 'humble tx'
		$patterns[] = $city;                // e.g. 'humble'
	}
	$patterns = array_unique($patterns);
	return $patterns;
}

// Returns 'geo' | 'jobsite' | 'blog' | 'main'
function bp_kw_classify_keyword(string $keyword, string $url = ''): string {
	foreach (bp_kw_get_area_patterns() as $area) {
		if ($area && strpos($keyword, $area) !== false) return 'geo';
	}
	$path = parse_url($url, PHP_URL_PATH) ?: '';
	if (strpos($path, '/service/') !== false) return 'jobsite';
	// Blog: long descriptive slugs OR pro-tips subdirectory
	$slug = trim(basename(rtrim($path, '/')));
	if (strlen($slug) > 30) return 'blog';
	if (strpos($path, '/pro-tips/') !== false) return 'blog';
	// Main: root-level only (e.g. /about-us), not /service-area/frisco-tx
	$segments = array_filter(explode('/', trim($path, '/')));
	if (count($segments) <= 1) return 'main';
	return 'geo';
}


/*--------------------------------------------------------------
# DataForSEO Labs API
--------------------------------------------------------------*/

function bp_kw_dfs_request(string $endpoint, array $payload): ?array {
	$creds = bp_kw_api_creds();
	if (!$creds['login'] || !$creds['password']) return null;

	$response = wp_remote_post('https://api.dataforseo.com' . $endpoint, [
		'timeout'   => 45,
		'sslverify' => true,
		'headers'   => [
			'Authorization' => 'Basic ' . base64_encode("{$creds['login']}:{$creds['password']}"),
			'Content-Type'  => 'application/json',
		],
		'body' => json_encode($payload),
	]);

	if (is_wp_error($response)) {
		error_log('bp_kw DataForSEO error: ' . $response->get_error_message());
		return null;
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	return is_array($body) ? $body : null;
}

// Fetch ALL organic keywords the domain ranks for, paginating through the full result set
function bp_kw_fetch_ranked_keywords(): ?array {
	$all_items   = [];
	$offset      = 0;
	$limit       = 1000;
	$max_items   = 10000; // safety cap — ~$1.01 per call at this size
	$total_count = null;

	do {
		$result = bp_kw_dfs_request('/v3/dataforseo_labs/google/ranked_keywords/live', [[
			'target'        => bp_kw_site_domain(),
			'location_code' => 2840, // United States
			'language_code' => 'en',
			'limit'         => $limit,
			'offset'        => $offset,
			'filters'       => [['ranked_serp_element.serp_item.type', '=', 'organic']],
			'order_by'      => ['ranked_serp_element.serp_item.rank_absolute,asc'],
		]]);

		if (!$result || ($result['status_code'] ?? 0) !== 20000) {
			error_log('bp_kw_fetch_ranked_keywords: API error at offset ' . $offset . ': ' . json_encode($result));
			// Return partial results if we already fetched some pages; null if first call failed
			return $offset > 0 ? $all_items : null;
		}

		$task_result = $result['tasks'][0]['result'][0] ?? null;
		if (!$task_result) {
			return $offset > 0 ? $all_items : null;
		}

		$items       = $task_result['items'] ?? [];
		$total_count = $total_count ?? (int)($task_result['total_count'] ?? 0);

		$all_items = array_merge($all_items, $items);
		$offset   += $limit;

	} while (!empty($items) && count($all_items) < $total_count && count($all_items) < $max_items);

	// Store total_count for cost estimation display
	update_option('bp_kw_last_total_count', $total_count ?? count($all_items), false);

	return $all_items;
}


/*--------------------------------------------------------------
# Cron Runner
--------------------------------------------------------------*/

function bp_kw_run_chron(bool $force = false): void {
	$creds = bp_kw_api_creds();
	if (!$creds['login'] || !$creds['password']) return;

	$items = bp_kw_fetch_ranked_keywords();
	if ($items === null) return;

	$today    = date('Y-m-d');
	$history  = bp_kw_history();
	$tracked  = bp_kw_tracked();
	$rejected = bp_kw_rejected();
	$seen     = 0;

	foreach ($items as $item) {
		$keyword = strtolower(trim($item['keyword_data']['keyword'] ?? ''));
		if (!$keyword) continue;

		$kw_key = md5($keyword);
		if (isset($rejected[$kw_key])) continue;

		$rank  = (int)($item['ranked_serp_element']['serp_item']['rank_absolute'] ?? 0);
		$url   = $item['ranked_serp_element']['serp_item']['url'] ?? '';
		$vol   = (int)($item['keyword_data']['keyword_info']['search_volume'] ?? 0);
		$group = bp_kw_classify_keyword($keyword, $url);

		$tracked[$kw_key] = [
			'keyword'    => $keyword,
			'url'        => $url,
			'search_vol' => $vol,
			'group'      => $group,
			'first_seen' => $tracked[$kw_key]['first_seen'] ?? $today,
			'last_seen'  => $today,
		];

		if (!isset($history[$kw_key])) $history[$kw_key] = [];
		$history[$kw_key][$today] = $rank;

		$seen++;
	}

	bp_kw_save_tracked($tracked);
	bp_kw_save_history($history);
	update_option('bp_kw_chron_last_run', time(), false);
	update_option('bp_kw_snapshot_date', $today, false);

	error_log('bp_kw_run_chron: ' . $seen . ' keywords from Labs snapshot on ' . $today);
}


/*--------------------------------------------------------------
# Dashboard Widget
--------------------------------------------------------------*/

add_action('wp_dashboard_setup', 'bp_kw_register_dashboard_widget');
function bp_kw_register_dashboard_widget(): void {
	if (_USER_LOGIN !== 'battleplanweb' && !in_array('bp_view_stats', _USER_ROLES)) return;
	add_meta_box('bp_keyword_rankings', 'Keyword Rankings', 'bp_kw_render_dashboard_widget', 'dashboard', 'column3', 'default');
}

// Force keyword rankings widget into column3 by writing directly to user meta
add_action('load-index.php', function() {
	if (_USER_LOGIN !== 'battleplanweb' && !in_array('bp_view_stats', _USER_ROLES)) return;

	$user_id = get_current_user_id();
	if (!$user_id) return;

	$order = get_user_meta($user_id, 'metaboxorder_dashboard', true);
	if (!is_array($order)) $order = [];

	// Check if already correctly placed — skip DB write if so
	$col3 = array_filter(explode(',', $order['column3'] ?? ''));
	if (in_array('bp_keyword_rankings', $col3)) return;

	// Remove from wherever it currently lives
	foreach ($order as $col => $list) {
		$widgets     = array_filter(explode(',', $list));
		$widgets     = array_values(array_diff($widgets, ['bp_keyword_rankings']));
		$order[$col] = implode(',', $widgets);
	}

	// Append to column3 and save
	$col3[] = 'bp_keyword_rankings';
	$order['column3'] = implode(',', $col3);
	update_user_meta($user_id, 'metaboxorder_dashboard', $order);
});

function bp_kw_render_dashboard_widget(): void {
	$creds = bp_kw_api_creds();
	if (!$creds['login'] || !$creds['password']) {
		echo '<p><strong>DataForSEO not configured.</strong> <a href="' . esc_url(admin_url('admin.php?page=kw-rankings')) . '">Add credentials →</a></p>';
		return;
	}

	$tracked = bp_kw_tracked();
	$history = bp_kw_history();

	if (!$tracked) {
		echo '<p>No data yet — rankings will appear after the first weekly snapshot. <a href="' . esc_url(admin_url('admin.php?page=kw-rankings')) . '">Fetch now →</a></p>';
		return;
	}

	$rows = [];
	foreach ($tracked as $kw_key => $item) {
		$kh     = $history[$kw_key] ?? [];
		krsort($kh);
		$dates  = array_keys($kh);
		$rank   = $kh[$dates[0] ?? ''] ?? null;
		$change = bp_kw_rank_change($kw_key, $history);
		$spark  = bp_kw_sparkline($kw_key, $history, 8);
		$rows[] = compact('kw_key', 'item', 'rank', 'change', 'spark');
	}

	usort($rows, function($a, $b) {
		if ($a['rank'] && $b['rank']) return $a['rank'] <=> $b['rank'];
		if ($a['rank']) return -1;
		if ($b['rank']) return 1;
		return strcmp($a['item']['keyword'], $b['item']['keyword']);
	});

	$buckets = ['1-3' => 0, '4-10' => 0, '11-20' => 0, '21+' => 0];
	$groups  = ['geo' => 0, 'jobsite' => 0, 'blog' => 0, 'main' => 0];
	foreach ($rows as $r) {
		$rk = $r['rank'];
		if ($rk) {
			if      ($rk <= 3)  $buckets['1-3']++;
			elseif  ($rk <= 10) $buckets['4-10']++;
			elseif  ($rk <= 20) $buckets['11-20']++;
			else                $buckets['21+']++;
		}
		$g = $r['item']['group'] ?? 'main';
		if (isset($groups[$g])) $groups[$g]++;
	}

	$total         = count($rows);
	$last_run      = get_option('bp_kw_chron_last_run');
	$snapshot_date = get_option('bp_kw_snapshot_date', '');
	$last_label    = ($last_run && function_exists('timeElapsed'))
		? timeElapsed($last_run, 1, 'all', 'full') . ' ago'
		: ($last_run ? date('M j', $last_run) : 'Never');

	$top10 = array_slice($rows, 0, 10);
	?>
	<style>
	#bp_keyword_rankings .kw-buckets{display:flex;gap:8px;margin:0 0 8px}
	#bp_keyword_rankings .kw-bucket{flex:1;text-align:center;padding:8px 4px;border-radius:5px}
	#bp_keyword_rankings .kw-bucket strong{display:block;font-size:22px;line-height:1.1}
	#bp_keyword_rankings .kw-bucket span{font-size:11px;color:#555}
	#bp_keyword_rankings .b13{background:#d4edda}
	#bp_keyword_rankings .b410{background:#fff3cd}
	#bp_keyword_rankings .b1120{background:#fde8cc}
	#bp_keyword_rankings .b21{background:#f8d7da}
	#bp_keyword_rankings .kw-groups{display:flex;gap:6px;margin:0 0 14px;font-size:11px;color:#666}
	#bp_keyword_rankings .kw-groups span{padding:2px 8px;border-radius:10px;background:#f0f0f0}
	#bp_keyword_rankings .kw-groups .gg{background:#e8f4fd}
	#bp_keyword_rankings .kw-groups .gb{background:#fef9e7}
	#bp_keyword_rankings ul.kwt{list-style:none;margin:0;padding:0;font-size:12px}
	#bp_keyword_rankings ul.kwt li{display:flex;align-items:center;border-bottom:1px solid #eee;padding:4px 0}
	#bp_keyword_rankings ul.kwt li.kwt-head{border-bottom:2px solid #ddd;padding:5px 0;color:#444;font-size:11px;font-weight:600}
	#bp_keyword_rankings ul.kwt li div{padding:0 6px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
	#bp_keyword_rankings ul.kwt .kw-keyword{flex:1;text-align:left}
	#bp_keyword_rankings ul.kwt .kw-rank{width:40px;text-align:center;flex-shrink:0}
	#bp_keyword_rankings ul.kwt .kw-change{width:44px;text-align:center;flex-shrink:0}
	#bp_keyword_rankings ul.kwt .kw-trend{width:88px;text-align:center;flex-shrink:0}
	#bp_keyword_rankings ul.kwt .kw-vol{width:36px;text-align:right;flex-shrink:0}
	#bp_keyword_rankings .rn{font-weight:600;font-size:14px}
	#bp_keyword_rankings .cu{color:#28a745;font-size:11px}
	#bp_keyword_rankings .cd{color:#dc3545;font-size:11px}
	#bp_keyword_rankings .kwfoot{margin-top:10px;font-size:11px;color:#888;display:flex;justify-content:space-between}
	#bp_keyword_rankings .tag-geo{background:#e8f4fd;color:#1a73e8;font-size:10px;padding:1px 5px;border-radius:8px}
	#bp_keyword_rankings .tag-jobsite{background:#e8f5e9;color:#2e7d32;font-size:10px;padding:1px 5px;border-radius:8px}
	#bp_keyword_rankings .tag-blog{background:#fef9e7;color:#b06000;font-size:10px;padding:1px 5px;border-radius:8px}
	</style>

	<div class="kw-buckets">
		<div class="kw-bucket b13"><strong><?php echo (int)$buckets['1-3']; ?></strong><span>Pos 1–3</span></div>
		<div class="kw-bucket b410"><strong><?php echo (int)$buckets['4-10']; ?></strong><span>Pos 4–10</span></div>
		<div class="kw-bucket b1120"><strong><?php echo (int)$buckets['11-20']; ?></strong><span>Pos 11–20</span></div>
		<div class="kw-bucket b21"><strong><?php echo (int)$buckets['21+']; ?></strong><span>Pos 21+</span></div>
	</div>

	<div class="kw-groups">
		<span class="gg">&#127759; <?php echo (int)$groups['geo']; ?> geo</span>
		<span class="gj">&#127963; <?php echo (int)$groups['jobsite']; ?> jobsites</span>
		<span class="gl">&#128196; <?php echo (int)$groups['blog']; ?> blogs</span>
		<span><?php echo (int)$groups['main']; ?> main</span>
	</div>

	<ul class="kwt">
		<li class="kwt-head">
			<div class="kw-keyword">Keyword</div>
			<div class="kw-rank">Rank</div>
			<div class="kw-change">Change</div>
			<div class="kw-trend">Trend</div>
			<div class="kw-vol">Vol</div>
		</li>
		<?php foreach ($top10 as $i => $row) :
			$rk    = $row['rank'];
			$ch    = $row['change'];
			$group = $row['item']['group'] ?? 'main';
			if ($rk <= 3)       $color = 'color:#28a745';
			elseif ($rk <= 10)  $color = 'color:#856404';
			elseif ($rk <= 20)  $color = 'color:#cc7000';
			else                $color = 'color:#dc3545';
		?>
		<li>
			<div class="kw-keyword"><?php echo esc_html($row['item']['keyword']); ?><?php if ($group === 'geo') : ?> <span class="tag-geo">geo</span><?php elseif ($group === 'jobsite') : ?> <span class="tag-jobsite">jobsite</span><?php elseif ($group === 'blog') : ?> <span class="tag-blog">blog</span><?php endif; ?></div>
			<div class="kw-rank"><span class="rn" style="<?php echo esc_attr($color); ?>"><?php echo $rk ? '#' . $rk : '—'; ?></span></div>
			<div class="kw-change"><?php if ($ch > 0) : ?><span class="cu">&#9650;<?php echo (int)$ch; ?></span><?php elseif ($ch < 0) : ?><span class="cd">&#9660;<?php echo abs((int)$ch); ?></span><?php else : ?><span style="color:#aaa">—</span><?php endif; ?></div>
			<div class="kw-trend"><canvas id="kws-<?php echo $i; ?>" width="80" height="24"></canvas></div>
			<div class="kw-vol" style="color:#888;font-size:11px;"><?php echo $row['item']['search_vol'] ? number_format((int)$row['item']['search_vol']) : '—'; ?></div>
		</li>
		<?php endforeach; ?>
	</ul>

	<div class="kwfoot">
		<span><?php echo $total; ?> keywords · Snapshot: <?php echo esc_html($snapshot_date ?: 'None'); ?> · Fetched: <?php echo esc_html($last_label); ?></span>
		<a href="<?php echo esc_url(admin_url('admin.php?page=kw-rankings')); ?>">Manage →</a>
	</div>

	<script>
	(function(){
		var sparks = <?php echo json_encode(array_map(function($r){ return $r['spark']; }, $top10)); ?>;
		sparks.forEach(function(data, i) {
			if (!data || data.length < 2) return;
			var c = document.getElementById('kws-' + i);
			if (!c) return;
			var ctx = c.getContext('2d'), w = c.width, h = c.height;
			var valid = data.filter(function(v){ return v > 0; });
			if (!valid.length) return;
			var mn = Math.min.apply(null, valid), mx = Math.max.apply(null, valid);
			if (mn === mx) { mn = Math.max(1, mn - 1); mx = mn + 2; }
			ctx.beginPath();
			data.forEach(function(v, j) {
				var x = (j / (data.length - 1)) * w;
				var y = v > 0 ? h - 2 - ((mx - v) / (mx - mn)) * (h - 6) : h - 2;
				j === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
			});
			ctx.strokeStyle = '#4a90d9';
			ctx.lineWidth = 1.5;
			ctx.stroke();
		});
	})();
	</script>
	<?php
}


/*--------------------------------------------------------------
# Admin Management Page
--------------------------------------------------------------*/

add_action('admin_menu', 'bp_kw_register_admin_page');
function bp_kw_register_admin_page(): void {
	if (_USER_LOGIN !== 'battleplanweb' && !in_array('bp_view_stats', _USER_ROLES)) return;
	add_menu_page('Keyword Rankings', 'Keywords', 'manage_options', 'kw-rankings', 'bp_kw_render_admin_page', 'dashicons-chart-line', 72);
}

function bp_kw_render_admin_page(): void {

	if (isset($_POST['bp_kw_action']) && check_admin_referer('bp_kw_manage')) {
		$action  = sanitize_key($_POST['bp_kw_action']);
		$tracked = bp_kw_tracked();

		if ($action === 'delete_keyword') {
			$kw_key = sanitize_key($_POST['kw_key'] ?? '');
			if (isset($tracked[$kw_key])) {
				$label = $tracked[$kw_key]['keyword'];
				unset($tracked[$kw_key]);
				bp_kw_save_tracked($tracked);
				$history = bp_kw_history();
				unset($history[$kw_key]);
				bp_kw_save_history($history);
				echo '<div class="notice notice-success is-dismissible"><p>Removed: <strong>' . esc_html($label) . '</strong> — will reappear on next snapshot unless rejected.</p></div>';
			}
		}

		if ($action === 'reject_keyword') {
			$kw_key   = sanitize_key($_POST['kw_key'] ?? '');
			$rejected = bp_kw_rejected();
			if (isset($tracked[$kw_key])) {
				$label = $tracked[$kw_key]['keyword'];
				$rejected[$kw_key] = $label;
				bp_kw_save_rejected($rejected);
				unset($tracked[$kw_key]);
				bp_kw_save_tracked($tracked);
				$history = bp_kw_history();
				unset($history[$kw_key]);
				bp_kw_save_history($history);
				echo '<div class="notice notice-success is-dismissible"><p>Rejected: <strong>' . esc_html($label) . '</strong> — hidden from all future snapshots.</p></div>';
			}
		}

		if ($action === 'unreject_keyword') {
			$kw_key   = sanitize_key($_POST['kw_key'] ?? '');
			$rejected = bp_kw_rejected();
			if (isset($rejected[$kw_key])) {
				$label = $rejected[$kw_key];
				unset($rejected[$kw_key]);
				bp_kw_save_rejected($rejected);
				echo '<div class="notice notice-success is-dismissible"><p>Un-rejected: <strong>' . esc_html($label) . '</strong> — will reappear on next snapshot.</p></div>';
			}
		}

		if ($action === 'fetch_now') {
			bp_kw_run_chron(true);
			$tracked = bp_kw_tracked();
			echo '<div class="notice notice-success is-dismissible"><p>Snapshot complete — <strong>' . count($tracked) . '</strong> keywords fetched.</p></div>';
		}

		if ($action === 'clear_all') {
			bp_kw_save_tracked([]);
			bp_kw_save_history([]);
			delete_option('bp_kw_snapshot_date');
			delete_option('bp_kw_last_total_count');
			$tracked = [];
			echo '<div class="notice notice-warning is-dismissible"><p>All keyword data and history cleared.</p></div>';
		}
	}

	$tracked  = bp_kw_tracked();
	$history  = bp_kw_history();
	$last_run = get_option('bp_kw_chron_last_run');
	$snapshot_date = get_option('bp_kw_snapshot_date', '');
	$total_count   = (int) get_option('bp_kw_last_total_count', 0);
	$last_label    = ($last_run && function_exists('timeElapsed'))
		? timeElapsed($last_run, 1, 'all', 'full') . ' ago'
		: ($last_run ? date('M j g:ia', $last_run) : 'Never');

	// Cost estimate
	$kw_count      = $total_count ?: count($tracked);
	$pages         = max(1, ceil($kw_count / 1000));
	$cost_snapshot = round(($pages * 0.01) + ($kw_count * 0.0001), 4);
	$cost_annual   = round($cost_snapshot * 52, 2);

	// Build sorted rows
	$rows = [];
	foreach ($tracked as $kw_key => $item) {
		$kh     = $history[$kw_key] ?? [];
		krsort($kh);
		$dates  = array_keys($kh);
		$rank   = !empty($dates) ? ($kh[$dates[0]] ?? null) : null;
		$change = bp_kw_rank_change($kw_key, $history);
		$group  = $item['group'] ?? 'main';
		$rows[] = compact('kw_key', 'item', 'rank', 'change', 'group');
	}
	usort($rows, function($a, $b) {
		if ($a['rank'] && $b['rank']) return $a['rank'] <=> $b['rank'];
		if ($a['rank']) return -1;
		if ($b['rank']) return 1;
		return strcmp($a['item']['keyword'], $b['item']['keyword']);
	});

	// Group counts
	$gcounts = ['geo' => 0, 'jobsite' => 0, 'blog' => 0, 'main' => 0];
	foreach ($rows as $r) {
		$g = $r['group'];
		if (isset($gcounts[$g])) $gcounts[$g]++;
	}

	?>
	<div class="wrap">
	<h1>Keyword Rankings</h1>

	<form method="post" style="display:inline-block;margin-right:8px;">
		<?php wp_nonce_field('bp_kw_manage'); ?>
		<input type="hidden" name="bp_kw_action" value="fetch_now">
		<input type="submit" class="button button-primary" value="&#9654; Fetch Rankings Now">
	</form>
	<form method="post" style="display:inline-block;" onsubmit="return confirm('Clear ALL keyword data and history? This cannot be undone.');">
		<?php wp_nonce_field('bp_kw_manage'); ?>
		<input type="hidden" name="bp_kw_action" value="clear_all">
		<input type="submit" class="button button-link-delete" value="&#10005; Clear All">
	</form>
	<p class="description" style="margin-top:8px;">
		Last fetched: <strong><?php echo esc_html($last_label); ?></strong> &nbsp;·&nbsp;
		Snapshot: <strong><?php echo esc_html($snapshot_date ?: 'None'); ?></strong> &nbsp;·&nbsp;
		Keywords: <strong><?php echo count($tracked); ?></strong>
		<?php if ($kw_count) : ?>
		&nbsp;·&nbsp; Est. cost: <strong>$<?php echo number_format($cost_snapshot, 4); ?>/snapshot</strong> &nbsp;·&nbsp; <strong>$<?php echo number_format($cost_annual, 2); ?>/yr</strong>
		<?php endif; ?>
	</p>

	<hr>

	<h2 class="title">Ranked Keywords</h2>

	<?php if (!$rows) : ?>
		<p>No data yet. Click <strong>Fetch Rankings Now</strong> above to pull the latest snapshot from DataForSEO Labs.</p>
	<?php else : ?>

	<!-- Filter bar -->
	<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap;">
		<input type="search" id="kw-search" placeholder="Filter keywords…" style="width:240px;padding:4px 8px;font-size:13px;">
		<div id="kw-pos-filters" style="display:flex;gap:4px;">
			<button type="button" class="button button-small kw-pos-btn active" data-pos="all">All positions</button>
			<button type="button" class="button button-small kw-pos-btn" data-pos="1-3" style="background:#d4edda;">1–3</button>
			<button type="button" class="button button-small kw-pos-btn" data-pos="4-10" style="background:#fff3cd;">4–10</button>
			<button type="button" class="button button-small kw-pos-btn" data-pos="11-20" style="background:#fde8cc;">11–20</button>
			<button type="button" class="button button-small kw-pos-btn" data-pos="21+" style="background:#f8d7da;">21+</button>
		</div>
		<div id="kw-group-filters" style="display:flex;gap:4px;">
			<button type="button" class="button button-small kw-grp-btn active" data-grp="all">All types</button>
			<button type="button" class="button button-small kw-grp-btn" data-grp="geo"     style="background:#e8f4fd;">Geo (<?php echo (int)$gcounts['geo']; ?>)</button>
			<button type="button" class="button button-small kw-grp-btn" data-grp="jobsite" style="background:#e8f5e9;">Jobsites (<?php echo (int)$gcounts['jobsite']; ?>)</button>
			<button type="button" class="button button-small kw-grp-btn" data-grp="blog"    style="background:#fef9e7;">Blogs (<?php echo (int)$gcounts['blog']; ?>)</button>
			<button type="button" class="button button-small kw-grp-btn" data-grp="main">Main (<?php echo (int)$gcounts['main']; ?>)</button>
		</div>
		<span id="kw-visible-count" style="font-size:12px;color:#888;"><?php echo count($rows); ?> keywords</span>
	</div>

	<table class="wp-list-table widefat fixed striped" id="kw-table" style="font-size:13px;">
		<thead>
			<tr>
				<th style="width:30%">Keyword</th>
				<th style="width:6%">Rank</th>
				<th style="width:6%">Change</th>
				<th style="width:6%">Vol</th>
				<th style="width:6%">Type</th>
				<th style="width:28%">URL</th>
				<th style="width:9%">First Seen</th>
				<th style="width:9%">Actions</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($rows as $row) :
			$kw_key = $row['kw_key'];
			$item   = $row['item'];
			$rank   = $row['rank'];
			$change = $row['change'];
			$group  = $row['group'];

			// Position bucket for filtering
			if (!$rank)           $pos_bucket = '21+';
			elseif ($rank <= 3)   $pos_bucket = '1-3';
			elseif ($rank <= 10)  $pos_bucket = '4-10';
			elseif ($rank <= 20)  $pos_bucket = '11-20';
			else                  $pos_bucket = '21+';

			if ($rank && $rank <= 3)       $rank_color = '#28a745';
			elseif ($rank && $rank <= 10)  $rank_color = '#856404';
			elseif ($rank && $rank <= 20)  $rank_color = '#cc7000';
			else                           $rank_color = '#dc3545';
			?>
			<tr data-keyword="<?php echo esc_attr(strtolower($item['keyword'])); ?>"
			    data-pos="<?php echo esc_attr($pos_bucket); ?>"
			    data-grp="<?php echo esc_attr($group); ?>">
				<td><?php echo esc_html($item['keyword']); ?></td>
				<td>
					<?php if ($rank) : ?>
						<strong style="color:<?php echo $rank_color; ?>">#<?php echo (int)$rank; ?></strong>
					<?php else : ?>
						<span style="color:#aaa">—</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ($change > 0) : ?>
						<span style="color:#28a745">&#9650;<?php echo (int)$change; ?></span>
					<?php elseif ($change < 0) : ?>
						<span style="color:#dc3545">&#9660;<?php echo abs((int)$change); ?></span>
					<?php else : ?>
						<span style="color:#aaa">—</span>
					<?php endif; ?>
				</td>
				<td style="color:#888;font-size:11px;"><?php echo $item['search_vol'] ? number_format((int)$item['search_vol']) : '—'; ?></td>
				<td>
					<?php if ($group === 'geo') : ?>
						<span style="background:#e8f4fd;color:#1a73e8;font-size:11px;padding:2px 6px;border-radius:8px;">geo</span>
					<?php elseif ($group === 'jobsite') : ?>
						<span style="background:#e8f5e9;color:#2e7d32;font-size:11px;padding:2px 6px;border-radius:8px;">jobsite</span>
					<?php elseif ($group === 'blog') : ?>
						<span style="background:#fef9e7;color:#b06000;font-size:11px;padding:2px 6px;border-radius:8px;">blog</span>
					<?php else : ?>
						<span style="color:#999;font-size:11px;">main</span>
					<?php endif; ?>
				</td>
				<td style="font-size:11px;">
					<a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener">
						<?php echo esc_html(parse_url($item['url'], PHP_URL_PATH) ?: '/'); ?>
					</a>
				</td>
				<td style="color:#888;font-size:11px;"><?php echo esc_html($item['first_seen'] ?? '—'); ?></td>
				<td>
					<form method="post" style="display:inline;" onsubmit="return confirm('Remove this keyword? It will reappear on next snapshot unless rejected.')">
						<?php wp_nonce_field('bp_kw_manage'); ?>
						<input type="hidden" name="bp_kw_action" value="delete_keyword">
						<input type="hidden" name="kw_key" value="<?php echo esc_attr($kw_key); ?>">
						<button type="submit" class="button button-small">Remove</button>
					</form>
					<form method="post" style="display:inline;" onsubmit="return confirm('Permanently hide &quot;<?php echo esc_js($item['keyword']); ?>&quot; from all future snapshots?')">
						<?php wp_nonce_field('bp_kw_manage'); ?>
						<input type="hidden" name="bp_kw_action" value="reject_keyword">
						<input type="hidden" name="kw_key" value="<?php echo esc_attr($kw_key); ?>">
						<button type="submit" class="button button-small button-link-delete">Reject</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<script>
	(function(){
		var search    = document.getElementById('kw-search');
		var table     = document.getElementById('kw-table');
		var countEl   = document.getElementById('kw-visible-count');
		var activePos = 'all';
		var activeGrp = 'all';

		function applyFilters() {
			var term = search.value.toLowerCase().trim();
			var rows = table.querySelectorAll('tbody tr');
			var vis  = 0;
			rows.forEach(function(tr) {
				var kw  = tr.dataset.keyword || '';
				var pos = tr.dataset.pos     || '';
				var grp = tr.dataset.grp     || '';
				var show = (
					(!term          || kw.indexOf(term) !== -1) &&
					(activePos === 'all' || pos === activePos) &&
					(activeGrp === 'all' || grp === activeGrp)
				);
				tr.style.display = show ? '' : 'none';
				if (show) vis++;
			});
			countEl.textContent = vis + ' keyword' + (vis !== 1 ? 's' : '');
		}

		search.addEventListener('input', applyFilters);

		document.getElementById('kw-pos-filters').addEventListener('click', function(e) {
			var btn = e.target.closest('.kw-pos-btn');
			if (!btn) return;
			document.querySelectorAll('.kw-pos-btn').forEach(function(b){ b.classList.remove('active'); });
			btn.classList.add('active');
			activePos = btn.dataset.pos;
			applyFilters();
		});

		document.getElementById('kw-group-filters').addEventListener('click', function(e) {
			var btn = e.target.closest('.kw-grp-btn');
			if (!btn) return;
			document.querySelectorAll('.kw-grp-btn').forEach(function(b){ b.classList.remove('active'); });
			btn.classList.add('active');
			activeGrp = btn.dataset.grp;
			applyFilters();
		});
	})();
	</script>

	<?php endif; ?>

	<?php
	$rejected = bp_kw_rejected();
	if ($rejected) :
	?>
	<hr>
	<h2 class="title">Rejected Keywords <span style="font-size:13px;font-weight:400;color:#888">(<?php echo count($rejected); ?>) — hidden from all snapshots</span></h2>
	<table class="wp-list-table widefat fixed striped" style="font-size:13px;">
		<thead><tr><th>Keyword</th><th style="width:120px">Actions</th></tr></thead>
		<tbody>
		<?php foreach ($rejected as $kw_key => $label) : ?>
			<tr>
				<td><?php echo esc_html($label); ?></td>
				<td>
					<form method="post" style="display:inline;">
						<?php wp_nonce_field('bp_kw_manage'); ?>
						<input type="hidden" name="bp_kw_action" value="unreject_keyword">
						<input type="hidden" name="kw_key" value="<?php echo esc_attr($kw_key); ?>">
						<button type="submit" class="button button-small">Un-reject</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	</div>
	<?php
}
