<?php
/* Battle Plan Web Design: Keyword Rankings */

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Guards & Constants
# Settings
# Storage Helpers
# Keyword Discovery
# DataForSEO API
# Cron Runner
# Dashboard Widget
# Admin Management Page
--------------------------------------------------------------*/

if (defined('BP_KW_RANKINGS_LOADED')) return;
define('BP_KW_RANKINGS_LOADED', true);


/*--------------------------------------------------------------
# Settings
--------------------------------------------------------------*/

function bp_kw_api_creds(): array {
    return [
        'login'    => defined('DATAFORSEO_LOGIN')    ? DATAFORSEO_LOGIN    : get_option('bp_dataforseo_login', ''),
        'password' => defined('DATAFORSEO_PASSWORD') ? DATAFORSEO_PASSWORD : get_option('bp_dataforseo_password', ''),
    ];
}

function bp_kw_location_code(): int {
    return (int) get_option('bp_kw_location_code', 2840);
}

function bp_kw_site_domain(): string {
    return str_replace(['https://', 'http://'], '', rtrim(get_bloginfo('url'), '/'));
}


/*--------------------------------------------------------------
# Storage Helpers
--------------------------------------------------------------*/

/*
bp_kw_tracked  — option storing all tracked keywords:
    [ md5(keyword) => [
        'keyword'  => 'plumber springfield oh',
        'url'      => 'https://...',
        'page_id'  => 123,
        'source'   => 'auto' | 'manual',
        'active'   => true,
        'added_at' => timestamp,
    ], ... ]

bp_kw_history  — rank history per keyword:
    [ md5(keyword) => [ 'YYYY-MM-DD' => rank_int, ... ], ... ]
    rank = 0 means checked but not found in top 100

bp_kw_pending_tasks — DataForSEO Standard Queue in-flight:
    [ task_id => [ 'keyword', 'url', 'kw_key', 'submitted_at' ], ... ]

bp_kw_rejected — blocklist: keywords auto-discovery will never re-add:
    [ md5(keyword) => keyword_string, ... ]
*/

function bp_kw_tracked(): array {
    return get_option('bp_kw_tracked', []) ?: [];
}

function bp_kw_history(): array {
    return get_option('bp_kw_history', []) ?: [];
}

function bp_kw_rejected(): array {
    return get_option('bp_kw_rejected', []) ?: [];
}

function bp_kw_save_tracked(array $tracked): void {
    update_option('bp_kw_tracked', $tracked, false);
}

function bp_kw_save_history(array $history): void {
    update_option('bp_kw_history', $history, false);
}

function bp_kw_save_rejected(array $rejected): void {
    update_option('bp_kw_rejected', $rejected, false);
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

// Last N days of ranks for sparkline, returns plain array of ints
function bp_kw_sparkline(string $kw_key, array $history, int $days = 14): array {
    if (empty($history[$kw_key])) return [];
    $cutoff = date('Y-m-d', strtotime("-{$days} days"));
    $slice  = array_filter($history[$kw_key], fn($d) => $d >= $cutoff, ARRAY_FILTER_USE_KEY);
    ksort($slice);
    return array_values($slice);
}


/*--------------------------------------------------------------
# Keyword Discovery
--------------------------------------------------------------*/

// Extract H1/H2 text from an HTML string
function bp_kw_extract_headings(string $html): array {
    $headings = [];
    if (preg_match_all('/<h[12][^>]*>(.*?)<\/h[12]>/is', $html, $m)) {
        foreach ($m[1] as $h) {
            $text = trim(wp_strip_all_tags($h));
            if ($text) $headings[] = $text;
        }
    }
    return $headings;
}

// Discover keywords from standard pages & posts via WP_Query
function bp_kw_discover_from_posts(): array {
    $suggestions = [];

    $post_types = ['page', 'post'];
    foreach (get_post_types(['public' => true, '_builtin' => false]) as $pt) {
        if (!in_array($pt, ['jobsite_geo', 'attachment', 'testimonials', 'elementor_library'])) {
            $post_types[] = $pt;
        }
    }

    $posts = get_posts([
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => 200,
        'fields'         => 'ids',
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post_id) {
        $post    = get_post($post_id);
        $title   = get_the_title($post_id);
        $url     = get_permalink($post_id);
        $content = $post->post_content ?? '';

        // Gather candidates: title, headings in content, taxonomy terms
        $candidates = [strtolower(wp_strip_all_tags($title))];

        foreach (bp_kw_extract_headings($content) as $h) {
            $candidates[] = strtolower(wp_strip_all_tags($h));
        }

        foreach (get_object_taxonomies($post->post_type) as $tax) {
            $terms = get_the_terms($post_id, $tax);
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $candidates[] = strtolower($term->name);
                }
            }
        }

        foreach ($candidates as $kw) {
            $kw = trim($kw);
            if (strlen($kw) >= 4 && strlen($kw) <= 80) {
                $suggestions[] = [
                    'keyword' => $kw,
                    'url'     => $url,
                    'page_id' => $post_id,
                    'source'  => 'auto',
                ];
            }
        }
    }

    return $suggestions;
}

// Discover keywords from jobsite_geo — uses service × location taxonomy combinations
// Also optionally fetches live archive URL to parse rendered H1/H2
function bp_kw_discover_from_jobsite_geo(): array {
    $suggestions = [];

    // jobsite_geo-services terms → service keywords
    $service_terms = get_terms([
        'taxonomy'   => 'jobsite_geo-services',
        'hide_empty' => true,
    ]);

    // jobsite_geo-service-areas terms → location keywords
    $area_terms = get_terms([
        'taxonomy'   => 'jobsite_geo-service-areas',
        'hide_empty' => true,
    ]);

    if (is_wp_error($service_terms) || !$service_terms) return $suggestions;

    // Build service-only keywords (e.g., "plumbing services", "hvac repair")
    foreach ($service_terms as $st) {
        $service_name = strtolower($st->name);
        $service_url  = get_term_link($st);
        if (is_wp_error($service_url)) continue;

        $suggestions[] = [
            'keyword' => $service_name,
            'url'     => $service_url,
            'page_id' => 0,
            'source'  => 'auto',
        ];

        // Parse H1/H2 from live archive page for this service term
        $headings = bp_kw_fetch_url_headings($service_url);
        foreach ($headings as $h) {
            $kw = strtolower(wp_strip_all_tags($h));
            if (strlen($kw) >= 4 && strlen($kw) <= 80) {
                $suggestions[] = [
                    'keyword' => $kw,
                    'url'     => $service_url,
                    'page_id' => 0,
                    'source'  => 'auto',
                ];
            }
        }

        // Build service × location combos (e.g., "plumbing springfield oh")
        if (!is_wp_error($area_terms) && $area_terms) {
            foreach ($area_terms as $at) {
                $loc_formatted = function_exists('bp_format_location')
                    ? bp_format_location($at->slug)
                    : $at->name;
                $loc_str = strtolower(str_replace(', ', ' ', $loc_formatted));

                $area_url = get_term_link($at);
                if (is_wp_error($area_url)) $area_url = $service_url;

                $combos = [
                    $service_name . ' ' . $loc_str,
                    $service_name . ' near ' . $loc_str,
                ];
                foreach ($combos as $kw) {
                    if (strlen($kw) >= 4 && strlen($kw) <= 80) {
                        $suggestions[] = [
                            'keyword' => $kw,
                            'url'     => $service_url,
                            'page_id' => 0,
                            'source'  => 'auto',
                        ];
                    }
                }
            }
        }
    }

    return $suggestions;
}

// Fetch a URL and return its H1/H2 headings (used for jobsite_geo archive pages)
function bp_kw_fetch_url_headings(string $url): array {
    $response = wp_remote_get($url, [
        'timeout'    => 10,
        'user-agent' => 'BattlePlanBot/1.0 (keyword-discovery)',
        'sslverify'  => false,
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return [];
    }
    return bp_kw_extract_headings(wp_remote_retrieve_body($response));
}

// Run discovery: merge new suggestions into tracked list, skip rejected, email on new finds
function bp_kw_run_discovery(): void {
    $suggestions = array_merge(
        bp_kw_discover_from_posts(),
        bp_kw_discover_from_jobsite_geo()
    );

    $tracked  = bp_kw_tracked();
    $rejected = bp_kw_rejected();
    $seen     = []; // dedup within this discovery run
    $new_kws  = []; // for email notification

    foreach ($suggestions as $s) {
        $kw = strtolower(trim($s['keyword']));
        if (!$kw) continue;

        $kw_key = md5($kw);

        // Skip if already tracked, rejected, or seen this run
        if (isset($seen[$kw_key]) || isset($tracked[$kw_key]) || isset($rejected[$kw_key])) continue;
        $seen[$kw_key] = true;

        $tracked[$kw_key] = [
            'keyword'  => $kw,
            'url'      => $s['url'],
            'page_id'  => $s['page_id'] ?? 0,
            'source'   => $s['source'] ?? 'auto',
            'active'   => true,
            'added_at' => time(),
        ];
        $new_kws[$kw_key] = ['keyword' => $kw, 'url' => $s['url']];
    }

    bp_kw_save_tracked($tracked);
    update_option('bp_kw_last_discovery', time(), false);

    if ($new_kws) {
        error_log('bp_kw_run_discovery: +' . count($new_kws) . ' new keyword suggestions');
        bp_kw_notify_new_keywords($new_kws);
    }
}

// Email notification when discovery finds new keywords
function bp_kw_notify_new_keywords(array $new_kws): void {
    $to      = get_option('admin_email');
    $site    = get_bloginfo('name') ?: bp_kw_site_domain();
    $subject = "[{$site}] " . count($new_kws) . " new keyword" . (count($new_kws) === 1 ? '' : 's') . " discovered";

    $manage_url = admin_url('index.php?page=kw-rankings');

    $body  = "Keyword discovery found " . count($new_kws) . " new keyword(s) on {$site}:\n\n";
    foreach ($new_kws as $item) {
        $path  = parse_url($item['url'], PHP_URL_PATH) ?: '/';
        $body .= "  • {$item['keyword']}  ({$path})\n";
    }
    $body .= "\nTo review, pause, or reject any of these:\n{$manage_url}\n";
    $body .= "\nKeywords marked as Rejected will never be re-suggested by auto-discovery.\n";

    if (function_exists('emailMe')) {
        emailMe($subject, $body);
    } else {
        wp_mail($to, $subject, $body);
    }
}


/*--------------------------------------------------------------
# DataForSEO API
--------------------------------------------------------------*/

function bp_kw_dfs_request(string $endpoint, array $payload = [], string $method = 'POST'): ?array {
    $creds = bp_kw_api_creds();
    if (!$creds['login'] || !$creds['password']) return null;

    $auth = base64_encode("{$creds['login']}:{$creds['password']}");
    $url  = 'https://api.dataforseo.com' . $endpoint;

    $args = [
        'method'  => $method,
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Basic ' . $auth,
            'Content-Type'  => 'application/json',
        ],
        'sslverify' => true,
    ];

    if ($method === 'POST' && !empty($payload)) {
        $args['body'] = json_encode($payload);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        error_log('bp_kw DataForSEO error: ' . $response->get_error_message());
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return is_array($body) ? $body : null;
}

// Submit active keywords to DataForSEO Standard Queue (50 per batch)
function bp_kw_submit_tasks(array $keywords_to_check): void {
    if (!$keywords_to_check) return;

    $location_code = bp_kw_location_code();
    $batches       = array_chunk($keywords_to_check, 50, true);
    $pending       = get_option('bp_kw_pending_tasks', []) ?: [];

    foreach ($batches as $batch) {
        $tasks = [];
        foreach ($batch as $kw_key => $item) {
            $tasks[] = [
                'keyword'       => $item['keyword'],
                'location_code' => $location_code,
                'language_code' => 'en',
                'depth'         => 100,
                'tag'           => $kw_key,
            ];
        }

        $result = bp_kw_dfs_request('/v3/serp/google/organic/task_post', $tasks);

        if (!$result || ($result['status_code'] ?? 0) !== 20000) {
            error_log('bp_kw_submit_tasks: API error ' . json_encode($result));
            continue;
        }

        foreach ($result['tasks'] ?? [] as $task) {
            if (($task['status_code'] ?? 0) === 20100) {
                $task_id = $task['id'] ?? '';
                $kw_key  = $task['data']['tag'] ?? '';
                if ($task_id && $kw_key && isset($batch[$kw_key])) {
                    $pending[$task_id] = [
                        'keyword'      => $batch[$kw_key]['keyword'],
                        'url'          => $batch[$kw_key]['url'],
                        'kw_key'       => $kw_key,
                        'submitted_at' => time(),
                    ];
                }
            }
        }
    }

    update_option('bp_kw_pending_tasks', $pending, false);
}

// Collect results for any pending DataForSEO tasks
function bp_kw_collect_results(): void {
    $pending = get_option('bp_kw_pending_tasks', []) ?: [];
    if (!$pending) return;

    // Ask DataForSEO which tasks are ready
    $ready_result = bp_kw_dfs_request('/v3/serp/google/organic/tasks_ready', [], 'GET');
    if (!$ready_result) return;

    $ready_ids = [];
    foreach ($ready_result['tasks'] ?? [] as $task) {
        foreach ($task['result'] ?? [] as $r) {
            $id = $r['id'] ?? '';
            if ($id && isset($pending[$id])) {
                $ready_ids[] = $id;
            }
        }
    }

    if (!$ready_ids) return;

    $history  = bp_kw_history();
    $domain   = bp_kw_site_domain();
    $today    = date('Y-m-d');
    $resolved = [];

    foreach ($ready_ids as $task_id) {
        $info   = $pending[$task_id];
        $result = bp_kw_dfs_request(
            '/v3/serp/google/organic/task_get/regular/' . urlencode($task_id),
            [],
            'GET'
        );

        if (!$result) continue;

        $rank = 0;
        foreach ($result['tasks'] ?? [] as $task) {
            foreach ($task['result'] ?? [] as $r) {
                foreach ($r['items'] ?? [] as $item) {
                    if (stripos($item['url'] ?? '', $domain) !== false) {
                        $rank = (int)($item['rank_absolute'] ?? 0);
                        break 3;
                    }
                }
            }
        }

        $kw_key = $info['kw_key'];
        if (!isset($history[$kw_key])) $history[$kw_key] = [];
        $history[$kw_key][$today] = $rank;

        // Keep all history (no cap)

        $resolved[] = $task_id;
    }

    if ($resolved) {
        bp_kw_save_history($history);
        foreach ($resolved as $id) unset($pending[$id]);
        update_option('bp_kw_pending_tasks', $pending, false);
        error_log('bp_kw_collect_results: resolved ' . count($resolved) . ' tasks');
    }

    // Expire tasks older than 48 hours
    $cutoff = time() - 172800;
    foreach ($pending as $id => $info) {
        if (($info['submitted_at'] ?? 0) < $cutoff) unset($pending[$id]);
    }
    update_option('bp_kw_pending_tasks', $pending, false);
}


/*--------------------------------------------------------------
# Cron Runner
--------------------------------------------------------------*/

function bp_kw_run_chron(bool $force = false): void {

    // Step 1: collect any results from previously submitted tasks
    bp_kw_collect_results();

    // Step 2: re-run discovery if never done or older than 7 days
    $last_discovery = (int) get_option('bp_kw_last_discovery', 0);
    if ($force || (time() - $last_discovery) > 86400 * 7) {
        bp_kw_run_discovery();
    }

    // Step 3: submit active keywords not yet checked today
    $history = bp_kw_history();
    $today   = date('Y-m-d');
    $tracked = bp_kw_tracked();

    $to_check = [];
    foreach ($tracked as $kw_key => $item) {
        if (!($item['active'] ?? true)) continue;
        if (isset($history[$kw_key][$today])) continue;
        $to_check[$kw_key] = $item;
    }

    if ($to_check) {
        bp_kw_submit_tasks($to_check);
        error_log('bp_kw_run_chron: submitted ' . count($to_check) . ' keywords');
    }

    update_option('bp_kw_chron_last_run', time(), false);
}


/*--------------------------------------------------------------
# Dashboard Widget
--------------------------------------------------------------*/

add_action('wp_dashboard_setup', 'bp_kw_register_dashboard_widget');
function bp_kw_register_dashboard_widget(): void {
    if (_USER_LOGIN !== 'battleplanweb' && !in_array('bp_view_stats', _USER_ROLES)) return;
    add_meta_box(
        'bp_keyword_rankings',
        'Keyword Rankings',
        'bp_kw_render_dashboard_widget',
        'dashboard',
        'normal',
        'high'
    );
}

function bp_kw_render_dashboard_widget(): void {
    $creds = bp_kw_api_creds();
    if (!$creds['login'] || !$creds['password']) {
        echo '<p><strong>DataForSEO not configured.</strong> Go to <a href="' . esc_url(admin_url('index.php?page=kw-rankings')) . '">Dashboard → Keywords</a> to add credentials.</p>';
        return;
    }

    $tracked = bp_kw_tracked();
    $history = bp_kw_history();

    if (!$tracked) {
        echo '<p>No keywords tracked yet. <a href="' . esc_url(admin_url('index.php?page=kw-rankings')) . '">Add keywords or run discovery →</a></p>';
        return;
    }

    // Build display rows
    $rows = [];
    foreach ($tracked as $kw_key => $item) {
        if (!($item['active'] ?? true)) continue;
        $kh = $history[$kw_key] ?? [];
        krsort($kh);
        $dates    = array_keys($kh);
        $rank     = $kh[$dates[0] ?? ''] ?? null;
        $change   = bp_kw_rank_change($kw_key, $history);
        $spark    = bp_kw_sparkline($kw_key, $history, 14);
        $rows[]   = compact('kw_key', 'item', 'rank', 'change', 'spark');
    }

    // Sort: best rank first, unranked last
    usort($rows, function($a, $b) {
        if ($a['rank'] && $b['rank']) return $a['rank'] <=> $b['rank'];
        if ($a['rank']) return -1;
        if ($b['rank']) return 1;
        return strcmp($a['item']['keyword'], $b['item']['keyword']);
    });

    // Position buckets
    $buckets = ['1-3' => 0, '4-10' => 0, '11-20' => 0, '21+' => 0, 'unranked' => 0];
    foreach ($rows as $r) {
        $rk = $r['rank'];
        if (!$rk)       $buckets['unranked']++;
        elseif ($rk <= 3)  $buckets['1-3']++;
        elseif ($rk <= 10) $buckets['4-10']++;
        elseif ($rk <= 20) $buckets['11-20']++;
        else               $buckets['21+']++;
    }

    $total         = count($rows);
    $pending_count = count(get_option('bp_kw_pending_tasks', []) ?: []);
    $last_run      = get_option('bp_kw_chron_last_run');
    $last_label    = ($last_run && function_exists('timeElapsed'))
        ? timeElapsed($last_run, 1, 'all', 'full') . ' ago'
        : ($last_run ? date('M j', $last_run) : 'Never');

    $top10 = array_slice($rows, 0, 10);
    ?>
    <style>
    #bp_keyword_rankings .kw-buckets{display:flex;gap:8px;margin:0 0 14px}
    #bp_keyword_rankings .kw-bucket{flex:1;text-align:center;padding:8px 4px;border-radius:5px}
    #bp_keyword_rankings .kw-bucket strong{display:block;font-size:22px;line-height:1.1}
    #bp_keyword_rankings .kw-bucket span{font-size:11px;color:#555}
    #bp_keyword_rankings .b13{background:#d4edda}
    #bp_keyword_rankings .b410{background:#fff3cd}
    #bp_keyword_rankings .b1120{background:#fde8cc}
    #bp_keyword_rankings .b21{background:#f8d7da}
    #bp_keyword_rankings .bnone{background:#f0f0f0}
    #bp_keyword_rankings table.kwt{width:100%;border-collapse:collapse;font-size:12px}
    #bp_keyword_rankings table.kwt th{padding:5px 6px;border-bottom:2px solid #ddd;text-align:left;color:#444;font-size:11px;white-space:nowrap}
    #bp_keyword_rankings table.kwt td{padding:4px 6px;border-bottom:1px solid #eee;vertical-align:middle}
    #bp_keyword_rankings .rn{font-weight:600;font-size:14px}
    #bp_keyword_rankings .cu{color:#28a745;font-size:11px}
    #bp_keyword_rankings .cd{color:#dc3545;font-size:11px}
    #bp_keyword_rankings .kwfoot{margin-top:10px;font-size:11px;color:#888;display:flex;justify-content:space-between}
    </style>

    <div class="kw-buckets">
        <div class="kw-bucket b13"><strong><?php echo (int)$buckets['1-3']; ?></strong><span>Pos 1–3</span></div>
        <div class="kw-bucket b410"><strong><?php echo (int)$buckets['4-10']; ?></strong><span>Pos 4–10</span></div>
        <div class="kw-bucket b1120"><strong><?php echo (int)$buckets['11-20']; ?></strong><span>Pos 11–20</span></div>
        <div class="kw-bucket b21"><strong><?php echo (int)$buckets['21+']; ?></strong><span>Pos 21+</span></div>
        <div class="kw-bucket bnone"><strong><?php echo (int)$buckets['unranked']; ?></strong><span>Not ranking</span></div>
    </div>

    <table class="kwt">
        <tr>
            <th>Keyword</th>
            <th>Rank</th>
            <th>Change</th>
            <th>14-day trend</th>
            <th>URL</th>
        </tr>
        <?php foreach ($top10 as $i => $row) :
            $rk = $row['rank'];
            $ch = $row['change'];
            $color = '';
            if ($rk) {
                if ($rk <= 3)       $color = 'color:#28a745';
                elseif ($rk <= 10)  $color = 'color:#856404';
                elseif ($rk <= 20)  $color = 'color:#cc7000';
                else                $color = 'color:#dc3545';
            }
        ?>
        <tr>
            <td><?php echo esc_html($row['item']['keyword']); ?></td>
            <td><span class="rn" style="<?php echo esc_attr($color); ?>"><?php echo $rk ? '#' . $rk : '—'; ?></span></td>
            <td>
                <?php if ($ch > 0) : ?>
                    <span class="cu">▲<?php echo (int)$ch; ?></span>
                <?php elseif ($ch < 0) : ?>
                    <span class="cd">▼<?php echo abs((int)$ch); ?></span>
                <?php else : ?>
                    <span style="color:#aaa">—</span>
                <?php endif; ?>
            </td>
            <td><canvas id="kws-<?php echo $i; ?>" width="80" height="24"></canvas></td>
            <td style="font-size:11px;color:#888">
                <a href="<?php echo esc_url($row['item']['url']); ?>" target="_blank" rel="noopener">
                    <?php echo esc_html(parse_url($row['item']['url'], PHP_URL_PATH) ?: '/'); ?>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="kwfoot">
        <span><?php echo $total; ?> tracked · <?php echo $pending_count; ?> pending · Last run: <?php echo esc_html($last_label); ?></span>
        <a href="<?php echo esc_url(admin_url('index.php?page=kw-rankings')); ?>">Manage →</a>
    </div>

    <script>
    (function(){
        var sparks = <?php echo json_encode(array_map(fn($r) => $r['spark'], $top10)); ?>;
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
                // invert: lower rank = higher on chart
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
    add_submenu_page(
        'index.php',
        'Keyword Rankings',
        'Keywords',
        'manage_options',
        'kw-rankings',
        'bp_kw_render_admin_page'
    );
}

function bp_kw_render_admin_page(): void {

    // Handle POST actions
    if (isset($_POST['bp_kw_action']) && check_admin_referer('bp_kw_manage')) {
        $action  = sanitize_key($_POST['bp_kw_action']);
        $tracked = bp_kw_tracked();

        if ($action === 'save_settings') {
            update_option('bp_dataforseo_login',    sanitize_email($_POST['dfs_login'] ?? ''),            false);
            update_option('bp_dataforseo_password', sanitize_text_field($_POST['dfs_pass'] ?? ''),        false);
            update_option('bp_kw_location_code',    (int)($_POST['location_code'] ?? 2840),               false);
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        if ($action === 'add_keyword') {
            $kw  = strtolower(trim(sanitize_text_field($_POST['new_keyword'] ?? '')));
            $url = esc_url_raw(trim($_POST['keyword_url'] ?? get_bloginfo('url')));
            if ($kw && strlen($kw) >= 2) {
                $kw_key = md5($kw);
                if (!isset($tracked[$kw_key])) {
                    $tracked[$kw_key] = [
                        'keyword'  => $kw,
                        'url'      => $url ?: get_bloginfo('url'),
                        'page_id'  => 0,
                        'source'   => 'manual',
                        'active'   => true,
                        'added_at' => time(),
                    ];
                    bp_kw_save_tracked($tracked);
                    echo '<div class="notice notice-success is-dismissible"><p>Added: <strong>' . esc_html($kw) . '</strong></p></div>';
                } else {
                    echo '<div class="notice notice-warning is-dismissible"><p>Keyword already tracked.</p></div>';
                }
            }
        }

        if ($action === 'toggle_keyword') {
            $kw_key = sanitize_key($_POST['kw_key'] ?? '');
            if (isset($tracked[$kw_key])) {
                $tracked[$kw_key]['active'] = !($tracked[$kw_key]['active'] ?? true);
                bp_kw_save_tracked($tracked);
            }
        }

        if ($action === 'delete_keyword') {
            $kw_key = sanitize_key($_POST['kw_key'] ?? '');
            if (isset($tracked[$kw_key])) {
                $kw_label = $tracked[$kw_key]['keyword'];
                unset($tracked[$kw_key]);
                bp_kw_save_tracked($tracked);
                $history = bp_kw_history();
                unset($history[$kw_key]);
                bp_kw_save_history($history);
                echo '<div class="notice notice-success is-dismissible"><p>Removed: <strong>' . esc_html($kw_label) . '</strong></p></div>';
            }
        }

        // Reject: remove from tracked + history, add to blocklist so discovery never re-adds it
        if ($action === 'reject_keyword') {
            $kw_key  = sanitize_key($_POST['kw_key'] ?? '');
            $rejected = bp_kw_rejected();
            if (isset($tracked[$kw_key])) {
                $kw_label = $tracked[$kw_key]['keyword'];
                $rejected[$kw_key] = $kw_label;
                bp_kw_save_rejected($rejected);
                unset($tracked[$kw_key]);
                bp_kw_save_tracked($tracked);
                $history = bp_kw_history();
                unset($history[$kw_key]);
                bp_kw_save_history($history);
                echo '<div class="notice notice-success is-dismissible"><p>Rejected: <strong>' . esc_html($kw_label) . '</strong> — auto-discovery will never re-add this.</p></div>';
            }
        }

        // Un-reject: remove from blocklist (keyword won't be re-added automatically, but can be manually added)
        if ($action === 'unreject_keyword') {
            $kw_key  = sanitize_key($_POST['kw_key'] ?? '');
            $rejected = bp_kw_rejected();
            if (isset($rejected[$kw_key])) {
                $kw_label = $rejected[$kw_key];
                unset($rejected[$kw_key]);
                bp_kw_save_rejected($rejected);
                echo '<div class="notice notice-success is-dismissible"><p>Un-rejected: <strong>' . esc_html($kw_label) . '</strong> — it may be re-suggested on the next discovery run.</p></div>';
            }
        }

        if ($action === 'run_now') {
            update_option('bp_force_chron_d', true, false);
            echo '<div class="notice notice-info is-dismissible"><p>Ranking check queued — results will be collected on the next cron trigger.</p></div>';
        }

        if ($action === 'run_discovery') {
            bp_kw_run_discovery();
            $tracked = bp_kw_tracked(); // reload after discovery
            echo '<div class="notice notice-success is-dismissible"><p>Keyword discovery complete. ' . count($tracked) . ' keywords total.</p></div>';
        }

        if ($action === 'collect_now') {
            bp_kw_collect_results();
            echo '<div class="notice notice-info is-dismissible"><p>Result collection attempted.</p></div>';
        }
    }

    $tracked       = bp_kw_tracked();
    $history       = bp_kw_history();
    $creds         = bp_kw_api_creds();
    $location_code = bp_kw_location_code();
    $last_run      = get_option('bp_kw_chron_last_run');
    $last_label    = ($last_run && function_exists('timeElapsed'))
        ? timeElapsed($last_run, 1, 'all', 'full') . ' ago'
        : ($last_run ? date('M j g:ia', $last_run) : 'Never');
    $pending_count = count(get_option('bp_kw_pending_tasks', []) ?: []);
    $discovery_ts  = get_option('bp_kw_last_discovery');
    $discovery_label = $discovery_ts ? date('M j g:ia', $discovery_ts) : 'Never';

    // Build sorted rows for the table
    $rows = [];
    foreach ($tracked as $kw_key => $item) {
        $kh = $history[$kw_key] ?? [];
        krsort($kh);
        $dates   = array_keys($kh);
        $rank    = !empty($dates) ? ($kh[$dates[0]] ?? null) : null;
        $change  = bp_kw_rank_change($kw_key, $history);
        $rows[]  = compact('kw_key', 'item', 'rank', 'change');
    }
    usort($rows, function($a, $b) {
        if ($a['rank'] && $b['rank']) return $a['rank'] <=> $b['rank'];
        if ($a['rank']) return -1;
        if ($b['rank']) return 1;
        return strcmp($a['item']['keyword'], $b['item']['keyword']);
    });

    ?>
    <div class="wrap">
        <h1>Keyword Rankings</h1>

        <h2 class="title">Settings</h2>
        <form method="post">
            <?php wp_nonce_field('bp_kw_manage'); ?>
            <input type="hidden" name="bp_kw_action" value="save_settings">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">DataForSEO Login (email)</th>
                    <td>
                        <?php if (defined('DATAFORSEO_LOGIN')) : ?>
                            <code><?php echo esc_html(DATAFORSEO_LOGIN); ?></code>
                            <p class="description">Set via <code>DATAFORSEO_LOGIN</code> constant in wp-config.php.</p>
                        <?php else : ?>
                            <input type="email" name="dfs_login" value="<?php echo esc_attr($creds['login']); ?>" class="regular-text">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">DataForSEO Password</th>
                    <td>
                        <?php if (defined('DATAFORSEO_PASSWORD')) : ?>
                            <code>••••••••</code>
                            <p class="description">Set via <code>DATAFORSEO_PASSWORD</code> constant in wp-config.php.</p>
                        <?php else : ?>
                            <input type="password" name="dfs_pass" value="<?php echo esc_attr($creds['password']); ?>" class="regular-text">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Location Code</th>
                    <td>
                        <input type="number" name="location_code" value="<?php echo esc_attr($location_code); ?>" class="small-text">
                        <p class="description">2840 = United States. Find codes at <a href="https://api.dataforseo.com/v3/serp/google/locations" target="_blank" rel="noopener">DataForSEO locations endpoint</a>.</p>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" class="button button-primary" value="Save Settings"></p>
        </form>

        <hr>

        <h2 class="title">Add Keyword Manually</h2>
        <form method="post" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
            <?php wp_nonce_field('bp_kw_manage'); ?>
            <input type="hidden" name="bp_kw_action" value="add_keyword">
            <label>Keyword<br><input type="text" name="new_keyword" class="regular-text" placeholder="e.g. plumber springfield oh"></label>
            <label>Target URL<br><input type="url" name="keyword_url" class="regular-text" value="<?php echo esc_attr(get_bloginfo('url')); ?>"></label>
            <input type="submit" class="button" value="Add Keyword">
        </form>

        <hr>

        <h2 class="title">Actions</h2>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <?php wp_nonce_field('bp_kw_manage'); ?>
            <input type="hidden" name="bp_kw_action" value="run_discovery">
            <input type="submit" class="button" value="🔍 Run Keyword Discovery">
        </form>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <?php wp_nonce_field('bp_kw_manage'); ?>
            <input type="hidden" name="bp_kw_action" value="run_now">
            <input type="submit" class="button" value="▶ Submit Rankings Check">
        </form>
        <form method="post" style="display:inline-block;">
            <?php wp_nonce_field('bp_kw_manage'); ?>
            <input type="hidden" name="bp_kw_action" value="collect_now">
            <input type="submit" class="button" value="⬇ Collect Pending Results">
        </form>
        <p class="description" style="margin-top:8px;">
            Last run: <strong><?php echo esc_html($last_label); ?></strong> &nbsp;·&nbsp;
            Pending tasks: <strong><?php echo (int)$pending_count; ?></strong> &nbsp;·&nbsp;
            Keywords tracked: <strong><?php echo count($tracked); ?></strong> &nbsp;·&nbsp;
            Last discovery: <strong><?php echo esc_html($discovery_label); ?></strong>
        </p>

        <hr>

        <h2 class="title">Tracked Keywords</h2>
        <?php if (!$rows) : ?>
            <p>No keywords yet. Run discovery or add one manually above.</p>
        <?php else : ?>
        <table class="wp-list-table widefat fixed striped" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="width:30%">Keyword</th>
                    <th style="width:8%">Rank</th>
                    <th style="width:8%">Change</th>
                    <th style="width:25%">URL</th>
                    <th style="width:8%">Source</th>
                    <th style="width:7%">Status</th>
                    <th style="width:14%">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) :
                $kw_key = $row['kw_key'];
                $item   = $row['item'];
                $rank   = $row['rank'];
                $change = $row['change'];
                $active = $item['active'] ?? true;
                ?>
                <tr style="<?php echo !$active ? 'opacity:0.55;' : ''; ?>">
                    <td><?php echo esc_html($item['keyword']); ?></td>
                    <td>
                        <?php if ($rank) : ?>
                            <strong style="<?php
                                if ($rank <= 3)       echo 'color:#28a745';
                                elseif ($rank <= 10)  echo 'color:#856404';
                                elseif ($rank <= 20)  echo 'color:#cc7000';
                                else                  echo 'color:#dc3545';
                            ?>">#<?php echo (int)$rank; ?></strong>
                        <?php else : ?>
                            <span style="color:#aaa">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($change > 0) : ?>
                            <span style="color:#28a745">▲<?php echo (int)$change; ?></span>
                        <?php elseif ($change < 0) : ?>
                            <span style="color:#dc3545">▼<?php echo abs((int)$change); ?></span>
                        <?php else : ?>
                            <span style="color:#aaa">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:11px;">
                        <a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html(parse_url($item['url'], PHP_URL_PATH) ?: '/'); ?>
                        </a>
                    </td>
                    <td style="color:#888;font-size:11px;"><?php echo esc_html($item['source'] ?? 'auto'); ?></td>
                    <td style="font-size:11px;"><?php echo $active ? 'Active' : '<em>Paused</em>'; ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('bp_kw_manage'); ?>
                            <input type="hidden" name="bp_kw_action" value="toggle_keyword">
                            <input type="hidden" name="kw_key" value="<?php echo esc_attr($kw_key); ?>">
                            <button type="submit" class="button button-small"><?php echo $active ? 'Pause' : 'Resume'; ?></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Remove this keyword and its history?\n\nTip: use Reject if you never want this suggested again.')">
                            <?php wp_nonce_field('bp_kw_manage'); ?>
                            <input type="hidden" name="bp_kw_action" value="delete_keyword">
                            <input type="hidden" name="kw_key" value="<?php echo esc_attr($kw_key); ?>">
                            <button type="submit" class="button button-small">Remove</button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Reject &quot;<?php echo esc_js($item['keyword']); ?>&quot;?\n\nThis removes it and blocks it from ever being auto-discovered again.')">
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
        <?php endif; ?>

        <?php
        $rejected = bp_kw_rejected();
        if ($rejected) :
        ?>
        <hr>
        <h2 class="title">Rejected Keywords <span style="font-size:13px;font-weight:400;color:#888">(<?php echo count($rejected); ?>) — never auto-discovered again</span></h2>
        <table class="wp-list-table widefat fixed striped" style="font-size:13px;">
            <thead>
                <tr>
                    <th>Keyword</th>
                    <th style="width:160px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rejected as $kw_key => $kw_label) : ?>
                <tr>
                    <td><?php echo esc_html($kw_label); ?></td>
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
