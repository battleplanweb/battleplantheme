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

// Returns the brand exclude terms from customer_info as a lowercase array
function bp_kw_brand_terms(): array {
	static $terms = null;
	if ($terms !== null) return $terms;
	$ci  = customer_info();
	$raw = $ci['keyword-exclude'] ?? '';
	if (!$raw) { $terms = []; return $terms; }
	$terms = array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
	return $terms;
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
	$ok = update_option('bp_kw_history', $data, false);
	if (!$ok) {
		error_log('bp_kw_save_history: update_option returned false — history may not have saved. Serialized size: ' . strlen(serialize($data)) . ' bytes.');
	}
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

// Compact history to weekly resolution — drops daily duplicates while keeping the earliest entry,
// weekly anchor points (>=7 days apart), and the most recent entry. Returns the modified history array
// and the count of entries removed.
function bp_kw_compact_history_weekly(array $history): array {
	$removed = 0;
	foreach ($history as $kw_key => $kh) {
		if (!is_array($kh) || count($kh) <= 2) continue;
		ksort($kh);
		$dates       = array_keys($kh);
		$last_date   = end($dates);
		$kept        = [];
		$last_ts     = null;
		foreach ($dates as $date) {
			$ts      = strtotime($date . 'T12:00:00');
			$is_last = ($date === $last_date);
			if ($last_ts === null || ($ts - $last_ts) >= 7 * DAY_IN_SECONDS || $is_last) {
				$kept[$date] = $kh[$date];
				$last_ts     = $ts;
			} else {
				$removed++;
			}
		}
		$history[$kw_key] = $kept;
	}
	return ['history' => $history, 'removed' => $removed];
}

// Friendly label for SERP element types
function bp_kw_type_label(string $type): string {
	$map = [
		'organic'          => 'organic',
		'local_pack'       => 'local pack',
		'featured_snippet' => 'snippet',
		'knowledge_graph'  => 'knowledge',
		'people_also_ask'  => 'PAA',
		'video'            => 'video',
		'images'           => 'images',
		'shopping'         => 'shopping',
		'twitter'          => 'twitter',
		'top_stories'      => 'news',
	];
	return $map[$type] ?? str_replace('_', ' ', $type);
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

// Returns 'brand' | 'geo' | 'jobsite' | 'blog' | 'main'
function bp_kw_classify_keyword(string $keyword, string $url = ''): string {
	$lower = strtolower($keyword);
	foreach (bp_kw_brand_terms() as $term) {
		if ($term && strpos($lower, $term) !== false) return 'brand';
	}
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
# Target Keyword List (vertical defaults × geo)

Two-stage model:
  1. bp_kw_base_templates()  — per-vertical templates with synonym map.
     Templates use {token} placeholders that expand to multiple keywords
     when the synonym list has 2+ variants. First variant in each synonym
     list = "primary" (gets monthly cadence); rest = "secondary" (quarterly).
  2. bp_kw_target_records() — cross-products expanded templates with the
     home town and each jobsite-geo service term, emitting enriched records
     with source/city/state/is_primary/term_id/heat for the cron to consume.

The old bp_kw_vertical_defaults() and bp_kw_target_list() functions are
kept as flat-string shims for backward compat (used by the admin diagnostic
panel) — they derive from the new functions.
--------------------------------------------------------------*/

// Per-vertical templates and synonym maps. Filterable via 'bp_kw_base_templates'
// so a one-off site can register a vertical we don't ship in the framework.
//
// Synonym ordering matters: synonyms[0] is the "primary" variant for that token
// (national search volume leader — gets monthly cadence). Subsequent variants
// are tracked at quarterly cadence. Verify ordering against DataForSEO Search
// Volume nationally if a new vertical is added.
function bp_kw_base_templates(): array {
	$templates = [
		'hvac' => [
			'templates' => [
				'{ac} repair',
				'{ac} installation',
				'{ac} replacement',
				'{ac} service',
				'{heating} repair',
				'{heating} installation',
				'{heating} replacement',
				'hvac repair',
				'hvac installation',
				'hvac service',
				'hvac maintenance',
				'hvac contractor',
				'hvac company',
				'heat pump repair',
				'heat pump installation',
				'mini split installation',
				'ductless mini split',
				'emergency hvac',
				'heating and air',
			],
			'synonyms' => [
				'{ac}'      => ['ac', 'air conditioner', 'air conditioning'],
				'{heating}' => ['heating', 'furnace', 'heater'],
			],
		],
		'plumber' => [
			'templates' => [
				'plumber',
				'plumbing repair',
				'plumbing service',
				'plumbing company',
				'plumbing contractor',
				'{water heater} repair',
				'{water heater} installation',
				'{water heater} replacement',
				'drain cleaning',
				'leak detection',
				'leak repair',
				'sewer repair',
				'sewer line repair',
				'pipe repair',
				'emergency plumber',
				'emergency plumbing',
				'clogged drain',
				'toilet repair',
				'toilet installation',
			],
			'synonyms' => [
				'{water heater}' => ['water heater', 'hot water heater'],
			],
		],
		'electrician' => [
			'templates' => [
				'electrician',
				'electrical repair',
				'electrical service',
				'electrical contractor',
				'electrical company',
				'panel upgrade',
				'electrical panel replacement',
				'circuit breaker repair',
				'outlet installation',
				'lighting installation',
				'ceiling fan installation',
				'generator installation',
				'ev charger installation',
				'whole house wiring',
				'emergency electrician',
			],
			'synonyms' => [],
		],
		'tattoo' => [
			'templates' => [
				'tattoo shop',
				'tattoo artist',
				'tattoo parlor',
				'tattoo studio',
				'custom tattoo',
				'cover up tattoo',
				'fine line tattoo',
				'black and grey tattoo',
			],
			'synonyms' => [],
		],
	];

	return apply_filters('bp_kw_base_templates', $templates);
}

// Expand a single template against a synonym map.
// '{ac} repair' + ['{ac}' => ['ac', 'air conditioner']] → ['ac repair', 'air conditioner repair']
// The result preserves synonym ordering — index 0 = primary variant, rest = secondary.
// Templates with no matching tokens return as a single-element array.
function bp_kw_expand_synonyms(string $template, array $synonyms): array {
	$results = [$template];
	foreach ($synonyms as $token => $variants) {
		if (!$variants || strpos($template, $token) === false) continue;
		$expanded = [];
		foreach ($results as $r) {
			if (strpos($r, $token) === false) { $expanded[] = $r; continue; }
			foreach ($variants as $v) {
				$expanded[] = trim(preg_replace('/\s+/', ' ', str_replace($token, $v, $r)));
			}
		}
		$results = $expanded;
	}
	return $results;
}

// Backward-compat shim — flattens the new structured templates to a simple
// list of keywords for the current vertical. Used by bp_kw_target_list().
// Deprecated for new code; prefer bp_kw_target_records().
function bp_kw_vertical_defaults(): array {
	$ci   = customer_info();
	$type = strtolower($ci['business-type'] ?? $ci['site-type'] ?? '');

	// Old filter name preserved for any existing site overrides — though we
	// recommend migrating to 'bp_kw_base_templates' for the structured form.
	$templates = apply_filters('bp_kw_vertical_defaults_map', bp_kw_base_templates());

	$vertical = $templates[$type] ?? null;
	if (!$vertical) return apply_filters('bp_kw_vertical_defaults', [], $type);

	$tpls = $vertical['templates'] ?? [];
	$syn  = $vertical['synonyms']  ?? [];

	$flat = [];
	foreach ($tpls as $t) {
		foreach (bp_kw_expand_synonyms($t, $syn) as $k) $flat[] = $k;
	}

	return apply_filters('bp_kw_vertical_defaults', array_values(array_unique($flat)), $type);
}

// US state map: lowercased full name → 2-letter abbr. Single source of truth shared by
// bp_kw_normalize_area() (full→abbr) and bp_kw_state_full() (abbr→full inverse).
function bp_kw_state_map(): array {
	static $map = null;
	if ($map === null) {
		$map = [
			'alabama'=>'al','alaska'=>'ak','arizona'=>'az','arkansas'=>'ar','california'=>'ca',
			'colorado'=>'co','connecticut'=>'ct','delaware'=>'de','florida'=>'fl','georgia'=>'ga',
			'hawaii'=>'hi','idaho'=>'id','illinois'=>'il','indiana'=>'in','iowa'=>'ia',
			'kansas'=>'ks','kentucky'=>'ky','louisiana'=>'la','maine'=>'me','maryland'=>'md',
			'massachusetts'=>'ma','michigan'=>'mi','minnesota'=>'mn','mississippi'=>'ms',
			'missouri'=>'mo','montana'=>'mt','nebraska'=>'ne','nevada'=>'nv','new hampshire'=>'nh',
			'new jersey'=>'nj','new mexico'=>'nm','new york'=>'ny','north carolina'=>'nc',
			'north dakota'=>'nd','ohio'=>'oh','oklahoma'=>'ok','oregon'=>'or','pennsylvania'=>'pa',
			'rhode island'=>'ri','south carolina'=>'sc','south dakota'=>'sd','tennessee'=>'tn',
			'texas'=>'tx','utah'=>'ut','vermont'=>'vt','virginia'=>'va','washington'=>'wa',
			'west virginia'=>'wv','wisconsin'=>'wi','wyoming'=>'wy','district of columbia'=>'dc',
		];
	}
	return $map;
}

// Resolve a state input (abbr or full name) to its title-cased full name.
// "tn" → "Tennessee", "TN" → "Tennessee", "Tennessee" → "Tennessee". Empty if unrecognized.
function bp_kw_state_full(string $input): string {
	$key = strtolower(trim($input));
	if (!$key) return '';
	// Already a full name? Return title-cased.
	$abbr_to_full = array_flip(bp_kw_state_map());
	if (isset(bp_kw_state_map()[$key])) return ucwords($key);
	// Two-letter abbr → lookup.
	if (isset($abbr_to_full[$key])) return ucwords($abbr_to_full[$key]);
	return '';
}

// Format a city/state pair as a DataForSEO location_name string.
// "huntingdon" + "tn" → "Huntingdon,Tennessee,United States"
// Returns empty string if state can't be resolved (caller should fall back to location_code 2840).
function bp_kw_dfs_location_name(string $city, string $state): string {
	$city  = trim($city);
	$state = trim($state);
	if (!$city || !$state) return '';
	$state_full = bp_kw_state_full($state);
	if (!$state_full) return '';
	return ucwords($city) . ',' . $state_full . ',United States';
}

// Canonical form for any tracked keyword — used everywhere md5(keyword) is computed.
//   - lowercase
//   - dashes and commas replaced with spaces (so "paris-tn" and "paris tn" collide)
//   - whitespace collapsed
// Without this, "heating and air paris-tn" (legacy from Labs-era taxonomy names) and
// "heating and air paris tn" (new from target_records) live as duplicate entries.
function bp_kw_normalize_keyword(string $kw): string {
	$kw = strtolower(trim($kw));
	$kw = str_replace(['-', ','], ' ', $kw);
	$kw = preg_replace('/\s+/', ' ', $kw);
	return trim($kw);
}

// Normalize a city/state string for use in keyword construction.
// Handles formats: "Huntingdon-TN", "Huntingdon, TN", "Huntingdon TN", "Titusville Florida".
// Converts full state names to 2-letter abbreviations to match how people search Google.
function bp_kw_normalize_area(string $area): string {
	$state_map = bp_kw_state_map();

	$area  = strtolower(trim(str_replace([',', '-'], ' ', $area)));
	$area  = preg_replace('/\s+/', ' ', $area);
	$words = explode(' ', $area);
	$n     = count($words);

	// Try trailing 2 words first (multi-word states: "new york", "north carolina", etc.)
	if ($n >= 2) {
		$last_two = $words[$n - 2] . ' ' . $words[$n - 1];
		if (isset($state_map[$last_two])) {
			array_splice($words, -2, 2, [$state_map[$last_two]]);
			return implode(' ', $words);
		}
	}
	if ($n >= 1 && isset($state_map[$words[$n - 1]])) {
		$words[$n - 1] = $state_map[$words[$n - 1]];
		return implode(' ', $words);
	}

	return $area;
}

// Geographic suffixes to cross-product with vertical defaults.
// Combines: customer_info primary city + customer_info service-areas + jobsite-geo terms (if installed).
// Dedupes so jobsite-geo terms that overlap with service-areas don't double-count.
function bp_kw_target_areas(): array {
	$areas = [];
	$ci    = customer_info();

	// 1. Primary city from customer_info (always — this is the home base)
	$primary_city  = $ci['city'] ?? '';
	$primary_state = $ci['state-abbr'] ?? '';
	if ($primary_city && $primary_state) {
		$clean = bp_kw_normalize_area("$primary_city $primary_state");
		if ($clean) $areas[] = $clean;
	}

	// 2. service-areas from customer_info (manually curated list of cities the business serves)
	$svc = $ci['service-areas'] ?? [];
	if (is_array($svc)) {
		foreach ($svc as $a) {
			if (is_array($a) && count($a) >= 2) {
				$clean = bp_kw_normalize_area($a[0] . ' ' . $a[1]);
				if ($clean) $areas[] = $clean;
			}
		}
	}

	// 3. jobsite-geo taxonomy terms (auto-grows as new jobs are posted) — augments, doesn't replace
	$jobsite_geo = get_option('jobsite_geo', []);
	$has_jobsite = bp_module_on($jobsite_geo);
	if ($has_jobsite) {
		$terms = get_terms(['taxonomy' => 'jobsite_geo-service-areas', 'hide_empty' => false]);
		foreach (is_wp_error($terms) ? [] : (array) $terms as $term) {
			$clean = bp_kw_normalize_area($term->name);
			if ($clean) $areas[] = $clean;
		}
	}

	return array_values(array_unique(array_filter($areas)));
}

// Apply the vertical's synonym map to an arbitrary service phrase (used when
// reverse-mapping a jobsite-services term slug like 'air-conditioner-repair' to
// its synonym variants 'ac repair' / 'air conditioner repair' / 'air conditioning repair').
// Word-boundary match so 'ac' doesn't match inside 'macaroni'. Returns variants
// in synonym order — index 0 = primary. Returns single-element array if no synonym matches.
function bp_kw_apply_synonyms_to_service(string $service, array $synonyms): array {
	foreach ($synonyms as $token => $variants) {
		if (!$variants) continue;
		foreach ($variants as $v) {
			$pattern = '/\b' . preg_quote($v, '/') . '\b/i';
			if (!preg_match($pattern, $service)) continue;
			$out = [];
			foreach ($variants as $rv) {
				$out[] = trim(preg_replace('/\s+/', ' ', preg_replace($pattern, $rv, $service, 1)));
			}
			return array_values(array_unique($out));
		}
	}
	return [$service];
}

// Parse a jobsite-services term slug: '{service-slug}--{city-slug}-{state}'.
// Example: 'air-conditioner-repair--st-louis-mo' → ['service' => 'air conditioner repair', 'city' => 'st louis', 'state' => 'mo']
function bp_kw_parse_service_term_slug(string $slug): array {
	$blank = ['service' => '', 'city' => '', 'state' => ''];
	if (strpos($slug, '--') === false) return $blank;
	[$service_slug, $location_slug] = array_pad(explode('--', $slug, 2), 2, '');
	$service = trim(str_replace('-', ' ', $service_slug));
	$words   = array_values(array_filter(explode('-', $location_slug)));
	if (!$words) return $blank;
	$state = strtolower(array_pop($words));
	$city  = trim(implode(' ', $words));
	return ['service' => $service, 'city' => $city, 'state' => $state];
}

// One query for all jobsite-services term stats — returns map of term_id → ['count', 'last_post'].
// Used by bp_kw_term_heat() to avoid N+1 queries when scoring all terms.
function bp_kw_jobsite_service_term_stats(): array {
	global $wpdb;
	$rows = $wpdb->get_results($wpdb->prepare("
		SELECT tt.term_id, tt.count, MAX(p.post_date) AS last_post_date
		FROM {$wpdb->term_taxonomy} tt
		LEFT JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
		LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_status = %s AND p.post_type = %s
		WHERE tt.taxonomy = %s
		GROUP BY tt.term_id
	", 'publish', 'jobsite_geo', 'jobsite_geo-services'));

	$stats = [];
	foreach ((array) $rows as $r) {
		$stats[(int) $r->term_id] = [
			'count'     => (int) $r->count,
			'last_post' => $r->last_post_date ?: '',
		];
	}
	return $stats;
}

// Heat score for a jobsite-services term — drives cadence.
//   hot  = 10+ entries AND a new entry in the last 60 days
//   warm = 3+ entries, OR cold-but-fresh (any entry in last 30 days)
//   cold = otherwise
function bp_kw_term_heat(int $term_id, ?array $stats = null): string {
	if ($stats === null) $stats = bp_kw_jobsite_service_term_stats();
	$s = $stats[$term_id] ?? null;
	if (!$s) return 'cold';

	$count      = (int) $s['count'];
	$last_ts    = $s['last_post'] ? strtotime($s['last_post']) : 0;
	$days_since = $last_ts ? (time() - $last_ts) / DAY_IN_SECONDS : 9999;

	if ($count >= 10 && $days_since <= 60) return 'hot';
	if ($count >= 3) return 'warm';
	if ($days_since <= 30) return 'warm';
	return 'cold';
}

// Build the canonical list of tracked keywords as enriched records.
// Source of truth for the SERP cron. Records carry everything the scheduler needs:
//   keyword, source, city, state, is_home_town, is_primary, term_id, heat, template.
function bp_kw_target_records(): array {
	$ci       = customer_info();
	$type     = strtolower($ci['business-type'] ?? $ci['site-type'] ?? '');
	$tpl_map  = bp_kw_base_templates();
	$vertical = $tpl_map[$type] ?? null;
	$synonyms = $vertical['synonyms']  ?? [];
	$tpls     = $vertical['templates'] ?? [];

	$records      = [];
	$home_city    = strtolower(trim($ci['city'] ?? ''));
	$home_state   = strtolower(trim($ci['state-abbr'] ?? ''));
	$home_has_geo = $home_city && $home_state;

	// 1. HOME TOWN base keywords — Live Advanced (map pack) eligible
	if ($tpls && $home_has_geo) {
		foreach ($tpls as $tpl) {
			$expanded = bp_kw_expand_synonyms($tpl, $synonyms);
			foreach ($expanded as $idx => $base_kw) {
				$records[] = [
					'keyword'      => trim("$base_kw $home_city $home_state"),
					'source'       => 'base',
					'city'         => $home_city,
					'state'        => $home_state,
					'is_home_town' => true,
					'is_primary'   => ($idx === 0),
					'term_id'      => 0,
					'heat'         => '',
					'template'     => $tpl,
				];
			}
		}
	}

	// 2. Jobsite GEO services taxonomy — heat-driven cadence per term
	$jobsite_geo = get_option('jobsite_geo', []);
	$has_jobsite = bp_module_on($jobsite_geo);
	if ($has_jobsite) {
		$stats = bp_kw_jobsite_service_term_stats();
		$terms = get_terms(['taxonomy' => 'jobsite_geo-services', 'hide_empty' => false]);
		foreach (is_wp_error($terms) ? [] : (array) $terms as $term) {
			$parsed = bp_kw_parse_service_term_slug($term->slug);
			if (!$parsed['service'] || !$parsed['city'] || !$parsed['state']) continue;
			$heat     = bp_kw_term_heat((int) $term->term_id, $stats);
			$variants = bp_kw_apply_synonyms_to_service($parsed['service'], $synonyms);
			foreach ($variants as $idx => $v) {
				$records[] = [
					'keyword'      => trim("$v {$parsed['city']} {$parsed['state']}"),
					'source'       => 'jobsite',
					'city'         => $parsed['city'],
					'state'        => $parsed['state'],
					'is_home_town' => ($parsed['city'] === $home_city && $parsed['state'] === $home_state),
					'is_primary'   => ($idx === 0),
					'term_id'      => (int) $term->term_id,
					'heat'         => $heat,
					'template'     => $parsed['service'],
				];
			}
		}
	}

	// 4. Brand keyword — annual sanity check
	$name = strtolower(trim($ci['name'] ?? ''));
	if ($name) {
		$records[] = [
			'keyword'      => $name,
			'source'       => 'brand',
			'city'         => '',
			'state'        => '',
			'is_home_town' => true,
			'is_primary'   => true,
			'term_id'      => 0,
			'heat'         => '',
			'template'     => '',
		];
	}

	// Per-site filter for tweaks/additions
	$records = apply_filters('bp_kw_target_records', $records);

	// Dedupe by normalized keyword — first occurrence wins (base > service_area > jobsite > brand by insertion order)
	$deduped = [];
	foreach ($records as $r) {
		$kw = bp_kw_normalize_keyword($r['keyword'] ?? '');
		if (!$kw || isset($deduped[$kw])) continue;
		$r['keyword'] = $kw;
		$deduped[$kw] = $r;
	}
	return array_values($deduped);
}

// Backward-compat shim — flat list of keyword strings derived from records.
// Used by the admin diagnostic panel. New code should consume bp_kw_target_records() directly.
function bp_kw_target_list(): array {
	$records  = bp_kw_target_records();
	$keywords = array_values(array_filter(array_map(fn($r) => $r['keyword'] ?? '', $records)));
	return apply_filters('bp_kw_target_list', $keywords);
}

// Resolve a record's check cadence and SERP-call parameters.
// Cadence comes from {source, heat, is_primary}; depth comes from last_seen_rank
// (so we only pay for the depth we need to confirm a known rank).
//
// Endpoint is uniformly Live Regular for now — its response structure has organic
// results as flat top-level items keyed by `type=organic`, which is what our parser
// handles cleanly. Live Advanced (needed for map pack tracking on home town) wraps
// organic results in a nested `type=organic_results` container that the parser misses.
// TODO: map pack tracking requires a dedicated Live Advanced parsing path or a
// separate per-keyword "advanced query" pass on a quarterly cadence.
//
// Returns: ['endpoint' => 'live_regular', 'depth' => 20|50|100, 'cadence_days' => int]
function bp_kw_cadence_for_record(array $record, ?int $last_seen_rank = null): array {
	$endpoint = 'live_regular';

	// Depth — pay only for what's needed to confirm the last known position
	if ($last_seen_rank === null || $last_seen_rank <= 0) $depth = 100; // new/unranked
	elseif ($last_seen_rank <= 20) $depth = 20;
	elseif ($last_seen_rank <= 50) $depth = 50;
	else $depth = 100;

	// Cadence — by source × heat × is_primary
	$source  = $record['source']  ?? 'base';
	$heat    = $record['heat']    ?? '';
	$primary = !empty($record['is_primary']);

	if ($source === 'jobsite') {
		if ($heat === 'hot')        $cadence = 14;                    // biweekly
		elseif ($heat === 'warm')   $cadence = $primary ? 30 : 90;    // monthly / quarterly
		else                        $cadence = 90;                    // cold — quarterly
	} elseif ($source === 'brand')  $cadence = 365;                   // annual sanity check
	else                            $cadence = $primary ? 30 : 90;    // base, service_area

	return ['endpoint' => $endpoint, 'depth' => $depth, 'cadence_days' => $cadence];
}

// Mobile vs desktop as the "primary" device for SERP tracking. Driven by GA4 90-day
// device split (bp_ga4_devices_clean) with hysteresis to prevent flap:
//   - mobile share ≥ 55%  → switch to mobile (if not already)
//   - mobile share < 45%  → switch to desktop (if not already)
//   - 45–55% range        → stay on the current setting
// Re-evaluated at most every 90 days. The "secondary" device gets a quarterly
// spot-check in the cron — caller derives it inline: $primary === 'mobile' ? 'desktop' : 'mobile'.
function bp_kw_primary_device(): string {
	$cached    = (string) get_option('bp_kw_primary_device', '');
	$cached_ts = (int)    get_option('bp_kw_primary_device_ts', 0);
	$age_days  = $cached_ts ? (time() - $cached_ts) / DAY_IN_SECONDS : 9999;

	if ($cached && $age_days < 90) return $cached;

	$devices = get_option('bp_ga4_devices_clean', []);
	$mobile  = is_array($devices) ? (int) ($devices['mobile']['sessions-90']  ?? 0) : 0;
	$desktop = is_array($devices) ? (int) ($devices['desktop']['sessions-90'] ?? 0) : 0;
	$tablet  = is_array($devices) ? (int) ($devices['tablet']['sessions-90']  ?? 0) : 0;
	$total   = $mobile + $desktop + $tablet;

	if ($total <= 0) {
		$new = $cached ?: 'desktop';
	} else {
		$mobile_pct = ($mobile / $total) * 100;
		if ($cached === 'mobile') {
			$new = $mobile_pct < 45 ? 'desktop' : 'mobile';
		} elseif ($cached === 'desktop') {
			$new = $mobile_pct >= 55 ? 'mobile' : 'desktop';
		} else {
			$new = $mobile_pct >= 50 ? 'mobile' : 'desktop';
		}
	}

	update_option('bp_kw_primary_device',    $new,  false);
	update_option('bp_kw_primary_device_ts', time(), false);
	return $new;
}

// Whether to also run DataForSEO Labs for keyword discovery — surfaces keywords the site
// ranks for that aren't in our targeted list (e.g. blog posts that picked up organic traffic).
// Default ON. Opt out per-site by setting customer_info['kw-discovery'] = 'off'.
function bp_kw_discovery_enabled(): bool {
	$ci      = customer_info();
	$setting = strtolower(trim($ci['kw-discovery'] ?? ''));
	return apply_filters('bp_kw_use_discovery', $setting !== 'off');
}


/*--------------------------------------------------------------
# DataForSEO API
--------------------------------------------------------------*/

function bp_kw_dfs_request(string $endpoint, array $payload): ?array {
	$creds = bp_kw_api_creds();
	if (!$creds['login'] || !$creds['password']) return null;

	$response = wp_remote_post('https://api.dataforseo.com' . $endpoint, [
		'timeout'   => 25,
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

// Fire N HTTP requests in parallel via curl_multi. Each $payloads entry is keyed so the
// caller can map responses back to their original record. Wall time = slowest single
// request, not the sum. Critical when DataForSEO is taking 10-15s per Live Regular call —
// sequential would blow past PHP timeout. Returns map of same keys => decoded JSON (or null).
function bp_kw_dfs_request_parallel(string $endpoint, array $payloads): array {
	$creds = bp_kw_api_creds();
	if (!$creds['login'] || !$creds['password'] || !$payloads) return [];

	$url  = 'https://api.dataforseo.com' . $endpoint;
	$auth = 'Basic ' . base64_encode("{$creds['login']}:{$creds['password']}");

	$mh      = curl_multi_init();
	$handles = [];
	foreach ($payloads as $key => $payload) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode($payload),
			CURLOPT_HTTPHEADER     => [
				'Authorization: ' . $auth,
				'Content-Type: application/json',
			],
			CURLOPT_TIMEOUT        => 45,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true,
		]);
		curl_multi_add_handle($mh, $ch);
		$handles[$key] = $ch;
	}

	$active = null;
	do {
		$mrc = curl_multi_exec($mh, $active);
		if ($active) curl_multi_select($mh, 1.0);
	} while ($active && $mrc === CURLM_OK);

	$responses = [];
	foreach ($handles as $key => $ch) {
		$body      = curl_multi_getcontent($ch);
		$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$decoded   = json_decode($body ?: '', true);
		$responses[$key] = ($http_code === 200 && is_array($decoded)) ? $decoded : null;
		curl_multi_remove_handle($mh, $ch);
		curl_close($ch);
	}
	curl_multi_close($mh);

	return $responses;
}


// Run a single SERP query and return the raw items array — for the admin test-keyword diagnostic.
// Uses the same device the cron's batch would use, so the diagnostic matches reality.
function bp_kw_test_serp(string $keyword): array {
	$device   = bp_kw_primary_device();
	$payload = [[
		'keyword'       => $keyword,
		'location_code' => 2840,
		'language_code' => 'en',
		'device'        => $device,
		'depth'         => 100,
	]];
	$response = bp_kw_dfs_request('/v3/serp/google/organic/live/regular', $payload);
	$task     = $response['tasks'][0] ?? null;
	$result   = $task['result'][0] ?? null;
	return [
		'status_code'    => $response['status_code'] ?? 0,
		'status_message' => $response['status_message'] ?? '',
		'task_status'    => $task['status_code'] ?? 0,
		'task_message'   => $task['status_message'] ?? '',
		'device'         => $device,
		'items'          => $result['items'] ?? [],
	];
}

// Parse a single DataForSEO SERP response. Returns:
//   'rank'     => best organic rank for our domain (0 if not ranked)
//   'url'      => URL of the best-ranking page
//   'type'     => SERP element type for the best rank (e.g. 'organic', 'featured_snippet')
//   'map_rank' => position (1-indexed) within the local pack if our place_id/cid matches; 0 if not
//
// $place_id can be a string, an array (multi-location sites), or null (no map pack tracking).
function bp_kw_parse_serp_response(?array $response, string $domain, $place_id = null): array {
	$result = ['rank' => 0, 'url' => '', 'type' => '', 'map_rank' => 0];

	if (!$response || ($response['status_code'] ?? 0) !== 20000) return $result;
	$task = $response['tasks'][0] ?? null;
	if (!$task || ($task['status_code'] ?? 0) !== 20000) return $result;
	$task_result = $task['result'][0] ?? null;
	if (!$task_result) return $result;

	// Normalize the place_id list for matching — accepts string, array, or null
	$place_ids = array_values(array_filter(array_map('strval', (array) ($place_id ?? []))));

	foreach (($task_result['items'] ?? []) as $item) {
		$item_type = (string) ($item['type'] ?? '');

		// Map pack — DataForSEO may emit a single 'local_pack' item with nested 'items',
		// OR several top-level 'local_pack' items each representing one business listing.
		// Handle both shapes.
		if ($item_type === 'local_pack' && $place_ids) {
			$pack_items = (!empty($item['items']) && is_array($item['items'])) ? $item['items'] : [$item];
			foreach ($pack_items as $idx => $li) {
				$li_place = (string) ($li['place_id'] ?? '');
				$li_cid   = (string) ($li['cid'] ?? '');
				if (!$li_place && !$li_cid) continue;
				$matched = ($li_place && in_array($li_place, $place_ids, true))
				        || ($li_cid   && in_array($li_cid,   $place_ids, true));
				if (!$matched) continue;
				// Position: explicit rank_absolute on the nested item, or its index in the pack
				$pos = (int) ($li['rank_absolute'] ?? ($idx + 1));
				if ($pos > 0 && ($result['map_rank'] === 0 || $pos < $result['map_rank'])) {
					$result['map_rank'] = $pos;
				}
			}
			continue;
		}

		// Organic — match by domain
		$item_domain = strtolower($item['domain'] ?? '');
		if (!$item_domain) {
			$host        = parse_url($item['url'] ?? '', PHP_URL_HOST);
			$item_domain = strtolower($host ?: '');
		}
		$item_domain = preg_replace('/^www\./', '', $item_domain);
		if (!$item_domain || $item_domain !== $domain) continue;

		$item_rank = (int) ($item['rank_absolute'] ?? 0);
		if ($item_rank > 0 && ($result['rank'] === 0 || $item_rank < $result['rank'])) {
			$result['rank'] = $item_rank;
			$result['url']  = $item['url'] ?? '';
			$result['type'] = $item_type;
		}
	}
	return $result;
}

// Process due records in PARALLEL chunks via curl_multi. With DataForSEO Live taking
// 10-15s per task on standard tiers, sequential is too slow. Parallel collapses wall
// time: N concurrent requests finish in roughly the slowest single request's time, not
// the sum. Saves tracked + history after each parallel chunk so timeouts are non-destructive.
//
// Returns counts: ['processed', 'ok', 'fail', 'items_0', 'matched', 'status_codes', 'aborted_on_time']
function bp_kw_run_serp_batch(array $records, string $endpoint, string $device, string $mode, array &$tracked, array &$history, string $today, float $budget_start, float $budget_seconds): array {
	$counts = ['processed' => 0, 'ok' => 0, 'fail' => 0, 'items_0' => 0, 'matched' => 0, 'status_codes' => [], 'aborted_on_time' => false];
	if (!$records) return $counts;

	$domain   = preg_replace('/^www\./', '', bp_kw_site_domain());
	$place_id = customer_info()['pid'] ?? null;
	$path     = ($endpoint === 'live_advanced')
		? '/v3/serp/google/organic/live/advanced'
		: '/v3/serp/google/organic/live/regular';

	$concurrency = (int) apply_filters('bp_kw_concurrency', 20);

	foreach (array_chunk($records, $concurrency) as $chunk) {
		if ((microtime(true) - $budget_start) > $budget_seconds) {
			$counts['aborted_on_time'] = true;
			break;
		}

		// Build keyed payloads + record map so we can stitch responses back to records
		$payloads = $rec_map = [];
		foreach ($chunk as $r) {
			$key = md5(bp_kw_normalize_keyword($r['keyword']));
			$payloads[$key] = [[
				'keyword'       => $r['keyword'],
				'location_code' => 2840,
				'language_code' => 'en',
				'device'        => $device,
				'depth'         => (int) ($r['depth'] ?? 100),
			]];
			$rec_map[$key] = $r;
		}

		$responses = bp_kw_dfs_request_parallel($path, $payloads);

		foreach ($responses as $pre_key => $response) {
			$r           = $rec_map[$pre_key];
			$envelope_ok = $response && ($response['status_code'] ?? 0) === 20000;
			$task        = $envelope_ok ? ($response['tasks'][0] ?? null) : null;
			$task_status = (int) ($task['status_code'] ?? 0);

			if (!$envelope_ok || $task_status !== 20000) {
				$counts['fail']++;
				$code = !$envelope_ok ? ($response['status_code'] ?? 0) : $task_status;
				$counts['status_codes'][$code] = ($counts['status_codes'][$code] ?? 0) + 1;
				if (isset($tracked[$pre_key])) {
					if ($mode === 'primary') {
						$tracked[$pre_key]['last_check'] = $today;
						$tracked[$pre_key]['next_check'] = date('Y-m-d', strtotime('+1 day'));
					} else {
						$tracked[$pre_key]['last_secondary_check'] = $today;
					}
				}
				$counts['processed']++;
				continue;
			}

			$task_items = isset($task['result'][0]['items']) && is_array($task['result'][0]['items']) ? count($task['result'][0]['items']) : 0;
			$task_kw    = (string) ($task['data']['keyword'] ?? $r['keyword']);
			if ($task_items === 0) $counts['items_0']++;
			$counts['ok']++;

			$parsed = bp_kw_parse_serp_response($response, $domain, $place_id);
			if ($parsed['rank'] > 0) $counts['matched']++;

			// Re-key on the keyword DataForSEO echoes back (more robust than index alignment)
			$apply_key = md5(bp_kw_normalize_keyword($task_kw));
			if (isset($tracked[$apply_key])) {
				$result = array_merge($parsed, ['keyword' => $task_kw, 'device' => $device]);
				if ($mode === 'primary') bp_kw_apply_serp_primary($tracked[$apply_key], $history, $apply_key, $result, $today);
				else                     bp_kw_apply_serp_secondary($tracked[$apply_key], $result, $today);
			}
			$counts['processed']++;
		}

		// Save once per parallel chunk so a timeout mid-run preserves what's done
		bp_kw_save_tracked($tracked);
		bp_kw_save_history($history);
	}

	return $counts;
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

Two-tier execution model:
  - bp_kw_run_serp_chron — primary engine. Walks target_records, picks keywords
    due for a check (next_check <= today), runs SERP queries for them, applies
    tentative/confirmation logic, re-tiers next_check based on observed rank.
  - bp_kw_run_labs_chron — optional discovery layer. Pulls EVERY keyword the
    domain ranks for via DataForSEO Labs ranked_keywords. Gated behind
    bp_kw_discovery_enabled() since most sites only need the targeted SERP runs.
  - bp_kw_run_chron — wrapper kept as the entry point (functions-chron.php
    triggers it weekly when a non-SERP bot hits the site).
--------------------------------------------------------------*/

// True if the new reading should be flagged as "tentative" rather than committed —
// catches SERP glitches and one-off Google fluctuations. Triggers a 7-day re-check
// before the value is treated as real.
function bp_kw_detect_anomaly(int $new_rank, int $last_rank): bool {
	// Was in top 20, now absent from depth=20 check
	if ($last_rank > 0 && $last_rank <= 20 && $new_rank === 0) return true;
	// ±20 spots within top-50 range
	if ($last_rank > 0 && $last_rank <= 50 && $new_rank > 0 && abs($new_rank - $last_rank) >= 20) return true;
	// Fell out of top 3 — top-3 is where clicks live, even a small drop matters here
	if ($last_rank > 0 && $last_rank <= 3 && $new_rank > 3) return true;
	// Jumped INTO top 10 from below — confirm before celebrating
	if ($last_rank > 10 && $new_rank > 0 && $new_rank <= 10) return true;
	// Unranked → top 10 — same logic
	if ($last_rank === 0 && $new_rank > 0 && $new_rank <= 10) return true;
	return false;
}

// Are two rank readings close enough to call them "the same" for confirmation purposes?
// Used when a tentative reading is followed up by a confirmation check.
function bp_kw_ranks_similar(int $a, int $b): bool {
	if ($a === 0 && $b === 0) return true;
	if ($a === 0 || $b === 0) return false;
	if (abs($a - $b) <= 5) return true;
	return abs($a - $b) / max($a, $b) < 0.2;
}

// Apply a SERP result from the PRIMARY device to a tracked entry. Mutates $entry and $history.
// Handles tentative/confirmation flow:
//   - If a tentative is pending, this run is the confirmation — commit and clear tentative
//   - Otherwise, detect anomaly; if found, set tentative + schedule 7-day re-check, hold history
//   - If no anomaly, commit normally; re-tier next_check from the new rank
//
// Anomaly detection only runs when there IS a baseline (prior history). On a first check
// (no prior history), the new reading is the baseline — nothing to be anomalous against.
// Otherwise bootstrap would tentative-flag every site that genuinely ranks top-10 since
// the "unranked → top 10" rule fires against the placeholder last_rank=0.
function bp_kw_apply_serp_primary(array &$entry, array &$history, string $kw_key, array $result, string $today): void {
	$new_rank = (int) ($result['rank']     ?? 0);
	$new_map  = (int) ($result['map_rank'] ?? 0);
	$last_rank      = (int) ($entry['last_rank']      ?? 0);
	$flap           = (int) ($entry['flap_count']     ?? 0);
	$tentative_rank = $entry['tentative_rank']        ?? null;
	$is_confirming  = ($tentative_rank !== null);
	$has_baseline   = !empty($history[$kw_key]);

	// Always update meta
	$entry['last_check']  = $today;
	$entry['last_seen']   = $today;
	$entry['last_device'] = $result['device'] ?? '';
	$entry['last_type']   = $result['type']   ?? '';
	if (!empty($result['url'])) $entry['url'] = $result['url'];

	if ($is_confirming) {
		// Confirmation pass — was the tentative reading real, or a glitch?
		$similar = bp_kw_ranks_similar($new_rank, (int) $tentative_rank);
		$entry['flap_count'] = $similar ? 0 : ($flap + 1);
		$entry['tentative_rank']     = null;
		$entry['tentative_map_rank'] = null;
		$entry['tentative_at']       = null;
		$entry['last_rank']     = $new_rank;
		$entry['last_map_rank'] = $new_map;
	} else {
		// Normal reading — only flag tentative when we have a baseline AND haven't hit anti-flap cap
		$anomaly = $has_baseline && ($flap < 3) && bp_kw_detect_anomaly($new_rank, $last_rank);
		if ($anomaly) {
			$entry['tentative_rank']     = $new_rank;
			$entry['tentative_map_rank'] = $new_map;
			$entry['tentative_at']       = $today;
			$entry['next_check']         = date('Y-m-d', strtotime('+7 days'));
			return; // Hold off on history until confirmed
		}
		$entry['last_rank']     = $new_rank;
		$entry['last_map_rank'] = $new_map;
		$entry['flap_count']    = 0;
	}

	// Commit to history and schedule the next check
	if (!isset($history[$kw_key])) $history[$kw_key] = [];
	$history[$kw_key][$today] = $new_rank;

	$cadence = bp_kw_cadence_for_record($entry, $new_rank);
	$entry['next_check'] = date('Y-m-d', strtotime('+' . (int) $cadence['cadence_days'] . ' days'));
}

// Apply a SERP result from the SECONDARY (spot-check) device. No anomaly logic, no history —
// the secondary reading lives separately so the dashboard can show "mobile shows #3, desktop #2"
// without polluting the trend chart.
function bp_kw_apply_serp_secondary(array &$entry, array $result, string $today): void {
	$entry['last_secondary_rank']     = (int) ($result['rank']     ?? 0);
	$entry['last_secondary_map_rank'] = (int) ($result['map_rank'] ?? 0);
	$entry['last_secondary_check']    = $today;
	$entry['last_secondary_device']   = $result['device'] ?? '';
}

// Main SERP cron — walks target_records, queries due keywords, applies results.
// Caps work per invocation to fit WP Engine's ~60s PHP timeout. Bootstrap (first runs
// with no last_check yet) prioritizes primary device only and skips secondary spot-check;
// once everything has at least one reading, secondary kicks in on later runs.
// Returns counts so the admin handler can show progress and tell the user when to click again.
function bp_kw_run_serp_chron(bool $force = false): array {
	$out = ['primary' => 0, 'secondary' => 0, 'remaining' => 0, 'bootstrapping' => false];
	$creds = bp_kw_api_creds();
	if (!$creds['login'] || !$creds['password']) return $out;

	@ini_set('max_execution_time', 300);
	@ignore_user_abort(true);

	// Reset per-run diagnostics — each batch will append a line to this option
	update_option('bp_kw_last_run_diags', [], false);

	$today    = date('Y-m-d');
	$records  = bp_kw_target_records();
	$tracked  = bp_kw_tracked();
	$history  = bp_kw_history();
	$rejected = bp_kw_rejected();

	// MIGRATION — re-key legacy entries whose md5(keyword) doesn't match the canonical form
	// (e.g. "heating and air paris-tn" with a dash → "heating and air paris tn").
	// Merges duplicates: keeps earliest first_seen, newest last_seen, longest history.
	$migrated_tracked = $migrated_history = [];
	foreach ($tracked as $old_key => $entry) {
		$kw = bp_kw_normalize_keyword($entry['keyword'] ?? '');
		if (!$kw) continue;
		$new_key = md5($kw);
		$entry['keyword'] = $kw;

		if (isset($migrated_tracked[$new_key])) {
			$existing = $migrated_tracked[$new_key];
			if (($entry['first_seen'] ?? '9999-12-31') < ($existing['first_seen'] ?? '9999-12-31')) $existing['first_seen'] = $entry['first_seen'];
			if (($entry['last_seen']  ?? '') > ($existing['last_seen']  ?? '')) $existing['last_seen']  = $entry['last_seen'];
			if (!isset($existing['source']) && isset($entry['source'])) $existing = array_merge($existing, $entry);
			$migrated_tracked[$new_key] = $existing;
		} else {
			$migrated_tracked[$new_key] = $entry;
		}
		if (!empty($history[$old_key])) {
			$prev = $migrated_history[$new_key] ?? [];
			$migrated_history[$new_key] = array_replace($prev, $history[$old_key]); // newest dates win on collision
			ksort($migrated_history[$new_key]);
		}
	}
	$tracked = $migrated_tracked;
	$history = $migrated_history;

	// One-time recovery — entries that got stuck in "tentative" state before the anomaly fix
	// (the old code flagged every first-check top-10 result as suspicious). For any tentative
	// entry that has NO history yet, the tentative reading IS the first reading — promote it
	// to a committed rank so the keyword appears in the dashboard.
	foreach ($tracked as $kw_key => $entry) {
		$tentative = $entry['tentative_rank'] ?? null;
		if ($tentative === null) continue;
		if (!empty($history[$kw_key])) continue; // Has history, real confirmation flow applies
		$tentative_date = $entry['tentative_at'] ?? $today;
		$tracked[$kw_key]['last_rank']     = (int) $tentative;
		$tracked[$kw_key]['last_map_rank'] = (int) ($entry['tentative_map_rank'] ?? 0);
		$tracked[$kw_key]['flap_count']    = 0;
		$tracked[$kw_key]['tentative_rank']     = null;
		$tracked[$kw_key]['tentative_map_rank'] = null;
		$tracked[$kw_key]['tentative_at']       = null;
		// Backdate history to when the tentative was observed
		if (!isset($history[$kw_key])) $history[$kw_key] = [];
		$history[$kw_key][$tentative_date] = (int) $tentative;
		// Re-tier next_check on the now-confirmed rank
		$cadence = bp_kw_cadence_for_record($tracked[$kw_key], (int) $tentative);
		$tracked[$kw_key]['next_check'] = date('Y-m-d', strtotime('+' . (int) $cadence['cadence_days'] . ' days'));
	}

	// A. Sync tracked with current target_records — add new, refresh metadata, prune orphans
	$valid_keys = [];
	foreach ($records as $r) {
		$kw = bp_kw_normalize_keyword($r['keyword'] ?? '');
		if (!$kw) continue;
		$kw_key = md5($kw);
		if (isset($rejected[$kw_key])) continue;
		$valid_keys[$kw_key] = true;

		if (!isset($tracked[$kw_key])) {
			$tracked[$kw_key] = [
				'keyword'       => $kw,
				'url'           => '',
				'search_vol'    => 0,
				'first_seen'    => $today,
				'last_seen'     => $today,
				'last_check'    => '',
				'last_device'   => '',
				'last_type'     => '',
				'last_rank'     => 0,
				'last_map_rank' => 0,
				'next_check'    => $today,
				'flap_count'    => 0,
			];
		}
		$tracked[$kw_key] = array_merge($tracked[$kw_key], [
			'source'       => $r['source'],
			'city'         => $r['city'],
			'state'        => $r['state'],
			'is_home_town' => $r['is_home_town'],
			'is_primary'   => $r['is_primary'],
			'term_id'      => $r['term_id'],
			'heat'         => $r['heat'],
			'template'     => $r['template'],
		]);
	}

	// B. Orphan cleanup — drop tracked entries no longer in records, preserve legacy (Labs) entries
	foreach (array_keys($tracked) as $kw_key) {
		if (isset($valid_keys[$kw_key]))         continue;
		if (!isset($tracked[$kw_key]['source'])) continue;
		unset($tracked[$kw_key], $history[$kw_key]);
	}

	// C. Devices + bootstrap detection
	$primary   = bp_kw_primary_device();
	$secondary = $primary === 'mobile' ? 'desktop' : 'mobile';

	$bootstrapping = false;
	foreach ($tracked as $entry) {
		if (!isset($entry['source'])) continue;
		if (empty($entry['last_check'])) { $bootstrapping = true; break; }
	}
	$out['bootstrapping'] = $bootstrapping;

	// D. Build due-lists, grouped by endpoint
	$primary_advanced   = $primary_regular   = [];
	$secondary_advanced = $secondary_regular = [];

	// $force bypasses the weekly cron gate (in functions-chron.php), not per-keyword cadence —
	// once a keyword's been checked today its next_check is in the future, and we shouldn't
	// waste API calls re-querying it just because the user clicked Fetch Now again.
	foreach ($tracked as $kw_key => $entry) {
		if (!isset($entry['source'])) continue;
		$next_check  = $entry['next_check'] ?? $today;
		$primary_due = $next_check <= $today;

		$last_secondary = $entry['last_secondary_check'] ?? '';
		$secondary_due  = !$last_secondary || strtotime($last_secondary) < strtotime('-90 days');

		if (!$primary_due && !$secondary_due) continue;

		$cadence   = bp_kw_cadence_for_record($entry, (int) ($entry['last_rank'] ?? 0));
		$batch_rec = $entry + [
			'depth'           => $cadence['depth'],
			'_next_check'     => $next_check,
			'_last_secondary' => $last_secondary,
		];

		if ($primary_due) {
			if ($cadence['endpoint'] === 'live_advanced') $primary_advanced[] = $batch_rec;
			else                                          $primary_regular[]  = $batch_rec;
		}
		// Skip secondary entirely while bootstrapping — all budget goes to primary
		if ($secondary_due && !$bootstrapping) {
			if ($cadence['endpoint'] === 'live_advanced') $secondary_advanced[] = $batch_rec;
			else                                          $secondary_regular[]  = $batch_rec;
		}
	}

	// Sort each list — most overdue first
	$sort_primary   = fn($a, $b) => ($a['_next_check']     ?? '') <=> ($b['_next_check']     ?? '');
	$sort_secondary = fn($a, $b) => ($a['_last_secondary'] ?? '') <=> ($b['_last_secondary'] ?? '');
	usort($primary_advanced,   $sort_primary);
	usort($primary_regular,    $sort_primary);
	usort($secondary_advanced, $sort_secondary);
	usort($secondary_regular,  $sort_secondary);

	$total_due = count($primary_advanced) + count($primary_regular) + count($secondary_advanced) + count($secondary_regular);

	// E. Process due records under a time budget — stop before hitting PHP timeout.
	// DataForSEO Live submitted one task per HTTP call (multi-task batches silently fail
	// most tasks on standard account tiers). Per-task time varies 3-15s, so a fixed task
	// count is a poor budget — time is what we actually need to manage.
	$budget_start   = microtime(true);
	$budget_seconds = (float) apply_filters('bp_kw_max_seconds_per_run', 50);

	$batches = [
		['records' => $primary_advanced,   'endpoint' => 'live_advanced', 'device' => $primary,   'mode' => 'primary'],
		['records' => $primary_regular,    'endpoint' => 'live_regular',  'device' => $primary,   'mode' => 'primary'],
		['records' => $secondary_advanced, 'endpoint' => 'live_advanced', 'device' => $secondary, 'mode' => 'secondary'],
		['records' => $secondary_regular,  'endpoint' => 'live_regular',  'device' => $secondary, 'mode' => 'secondary'],
	];

	$all = ['ok' => 0, 'fail' => 0, 'items_0' => 0, 'matched' => 0, 'status_codes' => [], 'aborted_on_time' => false];

	foreach ($batches as $b) {
		if (!$b['records']) continue;
		if ((microtime(true) - $budget_start) > $budget_seconds) { $all['aborted_on_time'] = true; break; }

		$counts = bp_kw_run_serp_batch(
			$b['records'], $b['endpoint'], $b['device'], $b['mode'],
			$tracked, $history, $today, $budget_start, $budget_seconds
		);

		if ($b['mode'] === 'primary') $out['primary']   += $counts['processed'];
		else                          $out['secondary'] += $counts['processed'];

		$all['ok']      += $counts['ok'];
		$all['fail']    += $counts['fail'];
		$all['items_0'] += $counts['items_0'];
		$all['matched'] += $counts['matched'];
		foreach ($counts['status_codes'] as $code => $n) {
			$all['status_codes'][$code] = ($all['status_codes'][$code] ?? 0) + $n;
		}
		if ($counts['aborted_on_time']) { $all['aborted_on_time'] = true; break; }
	}

	update_option('bp_kw_chron_last_run', time(), false);
	update_option('bp_kw_snapshot_date',  $today, false);

	$processed = $out['primary'] + $out['secondary'];
	$out['remaining'] = max(0, $total_due - $processed);

	$elapsed   = microtime(true) - $budget_start;
	$domain    = preg_replace('/^www\./', '', bp_kw_site_domain());
	$codes_str = $all['status_codes'] ? ' · fail codes: ' . json_encode($all['status_codes']) : '';
	$abort_str = $all['aborted_on_time'] ? ' · stopped on time budget' : '';
	$diag_msg  = sprintf(
		'tasks: %d ok, %d failed · %d had 0 items · %d matched %s%s · %.1fs%s',
		$all['ok'], $all['fail'], $all['items_0'], $all['matched'], $domain, $codes_str, $elapsed, $abort_str
	);
	error_log('bp_kw_run_serp_chron: ' . $diag_msg . ($bootstrapping ? ' [bootstrap]' : ''));
	$diag_log = (array) get_option('bp_kw_last_run_diags', []);
	$diag_log[] = $diag_msg;
	update_option('bp_kw_last_run_diags', $diag_log, false);

	return $out;
}

// Optional discovery — the legacy Labs ranked_keywords fetcher. Pulls every keyword
// the domain is ranking for (one paginated API call). Useful for blog-heavy sites
// where targeted SERP queries miss long-tail organic surface area.
function bp_kw_run_labs_chron(bool $force = false): void {
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
		$keyword = bp_kw_normalize_keyword($item['keyword_data']['keyword'] ?? '');
		if (!$keyword) continue;

		$kw_key = md5($keyword);
		if (isset($rejected[$kw_key])) continue;

		$rank  = (int) ($item['ranked_serp_element']['serp_item']['rank_absolute'] ?? 0);
		$url   = $item['ranked_serp_element']['serp_item']['url'] ?? '';
		$vol   = (int) ($item['keyword_data']['keyword_info']['search_volume']    ?? 0);
		$group = bp_kw_classify_keyword($keyword, $url);

		$existing        = $tracked[$kw_key] ?? null;
		$is_serp_tracked = $existing && isset($existing['source']);

		if (!$existing) {
			// New discovery — keyword the site ranks for that we weren't already tracking
			$tracked[$kw_key] = [
				'keyword'    => $keyword,
				'url'        => $url,
				'search_vol' => $vol,
				'group'      => $group,
				'first_seen' => $today,
				'last_seen'  => $today,
			];
		} else {
			// Existing entry — refresh search_vol + last_seen; preserve everything else
			$tracked[$kw_key]['search_vol'] = $vol;
			$tracked[$kw_key]['last_seen']  = $today;
			if (empty($existing['url']) && $url) $tracked[$kw_key]['url'] = $url;
		}

		// Only write history for entries Labs is the authoritative source for. For SERP-tracked
		// entries (those with a 'source' field), the SERP cron's reading is more accurate —
		// don't let Labs' data overwrite it on the same date.
		if (!$is_serp_tracked) {
			if (!isset($history[$kw_key])) $history[$kw_key] = [];
			$history[$kw_key][$today] = $rank;
		}

		$seen++;
	}

	bp_kw_save_tracked($tracked);
	bp_kw_save_history($history);
	update_option('bp_kw_chron_last_run', time(), false);
	update_option('bp_kw_snapshot_date',  $today, false);

	error_log('bp_kw_run_labs_chron: ' . $seen . ' keywords from Labs snapshot on ' . $today);
}

// Entry point called by functions-chron.php — runs SERP cron always, plus Labs if opted in.
// Returns the SERP run summary so the admin handler can display progress.
//
// If the run finishes with keywords still due (bootstrap in progress, or time budget
// cut a run short), shorten the next cron tick from +6 days to the next nightly window
// so bootstrap can catch up across consecutive nights instead of waiting a full week.
function bp_kw_run_chron(bool $force = false): array {
	$out = bp_kw_run_serp_chron($force);
	if (bp_kw_discovery_enabled()) bp_kw_run_labs_chron($force);

	if (($out['remaining'] ?? 0) > 0 && function_exists('bp_next_nightly_window')) {
		update_option('bp_chron_d_next', bp_next_nightly_window());
	}

	return $out;
}


/*--------------------------------------------------------------
# Dashboard Widget
--------------------------------------------------------------*/

add_action('wp_dashboard_setup', 'bp_kw_register_dashboard_widget');
function bp_kw_register_dashboard_widget(): void {
	if (_USER_LOGIN !== 'battleplanweb') return;
	add_meta_box('bp_keyword_rankings', 'Keyword Rankings', 'bp_kw_render_dashboard_widget', 'dashboard', 'column3', 'default');
}

// Force keyword rankings widget into column3 by writing directly to user meta
add_action('load-index.php', function() {
	if (_USER_LOGIN !== 'battleplanweb') return;

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
		echo '<p><strong>DataForSEO not configured.</strong> <a href="' . esc_url(menu_page_url('kw-rankings', false)) . '">Add credentials →</a></p>';
		return;
	}

	$tracked      = bp_kw_tracked();
	$history      = bp_kw_history();
	$stale_cutoff = date('Y-m-d', strtotime('-100 days'));

	if (!$tracked) {
		echo '<p>No data yet — rankings will appear after the first weekly snapshot. <a href="' . esc_url(menu_page_url('kw-rankings', false)) . '">Fetch now →</a></p>';
		return;
	}

	$rows = [];
	foreach ($tracked as $kw_key => $item) {
		$kh = $history[$kw_key] ?? [];
		krsort($kh);
		$dates  = array_keys($kh);
		$rank   = $kh[$dates[0] ?? ''] ?? null;
		// Hide unranked or long-stale entries
		if ($rank === null || $rank <= 0 || $rank > 100) continue;
		if (($item['last_seen'] ?? '') < $stale_cutoff) continue;
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
	$srcs    = ['base' => 0, 'service_area' => 0, 'jobsite' => 0, 'brand' => 0, 'labs' => 0];
	foreach ($rows as $r) {
		$rk = $r['rank'];
		if ($rk) {
			if      ($rk <= 3)  $buckets['1-3']++;
			elseif  ($rk <= 10) $buckets['4-10']++;
			elseif  ($rk <= 20) $buckets['11-20']++;
			else                $buckets['21+']++;
		}
		// Prefer explicit source from the new model; fall back to URL classification for legacy entries
		$src = $r['item']['source'] ?? '';
		if (!$src) {
			$g = bp_kw_classify_keyword($r['item']['keyword'], $r['item']['url']);
			$src = ($g === 'geo') ? 'base' : (($g === 'blog' || $g === 'main') ? 'labs' : $g);
		}
		if (isset($srcs[$src])) $srcs[$src]++;
		else $srcs['labs']++;
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
	#bp_keyword_rankings ul.kwt .kw-trend{width:250px;text-align:center;flex-shrink:0}
	#bp_keyword_rankings ul.kwt .kw-vol{width:36px;text-align:right;flex-shrink:0}
	#bp_keyword_rankings .rn{font-weight:600;font-size:14px}
	#bp_keyword_rankings .cu{color:#28a745;font-size:11px}
	#bp_keyword_rankings .cd{color:#dc3545;font-size:11px}
	#bp_keyword_rankings .kwfoot{margin-top:10px;font-size:11px;color:#888;display:flex;justify-content:space-between}
	#bp_keyword_rankings .tag-geo{background:#e8f4fd;color:#1a73e8;font-size:10px;padding:1px 5px;border-radius:8px}
	#bp_keyword_rankings .tag-jobsite{background:#e8f5e9;color:#2e7d32;font-size:10px;padding:1px 5px;border-radius:8px}
	#bp_keyword_rankings .tag-blog{background:#fef9e7;color:#b06000;font-size:10px;padding:1px 5px;border-radius:8px}
	#bp_keyword_rankings .tag-brand{background:#f3e8fd;color:#6f42c1;font-size:10px;padding:1px 5px;border-radius:8px}
	</style>

	<div class="kw-buckets">
		<div class="kw-bucket b13"><strong><?php echo (int)$buckets['1-3']; ?></strong><span>Pos 1–3</span></div>
		<div class="kw-bucket b410"><strong><?php echo (int)$buckets['4-10']; ?></strong><span>Pos 4–10</span></div>
		<div class="kw-bucket b1120"><strong><?php echo (int)$buckets['11-20']; ?></strong><span>Pos 11–20</span></div>
		<div class="kw-bucket b21"><strong><?php echo (int)$buckets['21+']; ?></strong><span>Pos 21+</span></div>
	</div>

	<div class="kw-groups">
		<?php if ($srcs['base']) : ?><span class="gg">&#127968; <?php echo (int) $srcs['base']; ?> base</span><?php endif; ?>
		<?php if ($srcs['service_area']) : ?><span style="background:#fff8e1;color:#a36a00;">&#128205; <?php echo (int) $srcs['service_area']; ?> svc area</span><?php endif; ?>
		<?php if ($srcs['jobsite']) : ?><span style="background:#e8f5e9;color:#2e7d32;">&#127963; <?php echo (int) $srcs['jobsite']; ?> jobsite</span><?php endif; ?>
		<?php if ($srcs['brand']) : ?><span style="background:#f3e8fd;color:#6f42c1;">&#127775; <?php echo (int) $srcs['brand']; ?> brand</span><?php endif; ?>
		<?php if ($srcs['labs']) : ?><span class="gl">&#128196; <?php echo (int) $srcs['labs']; ?> discovered</span><?php endif; ?>
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
			$rk           = $row['rank'];
			$ch           = $row['change'];
			$src          = $row['item']['source'] ?? '';
			$map_rank     = (int) ($row['item']['last_map_rank'] ?? 0);
			$is_tentative = !empty($row['item']['tentative_rank']) || !empty($row['item']['tentative_at']);
			// Source pill — entries without a source field are Labs-discovered
			$tag = $src ?: 'labs';
			if ($tag === 'service_area') $tag = 'service';
			if ($rk <= 3)       $color = 'color:#28a745';
			elseif ($rk <= 10)  $color = 'color:#856404';
			elseif ($rk <= 20)  $color = 'color:#cc7000';
			else                $color = 'color:#dc3545';
		?>
		<li<?php if ($is_tentative) echo ' style="border-left:3px solid #f0c000;padding-left:4px;"'; ?>>
			<div class="kw-keyword"><a href="https://www.google.com/search?q=<?php echo urlencode($row['item']['keyword']); ?>" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;"><?php echo esc_html($row['item']['keyword']); ?></a><?php
				$tag_pills = [
					'base'    => ['tag-geo',     'base'],
					'service' => ['tag-geo',     'service'],
					'jobsite' => ['tag-jobsite', 'jobsite'],
					'brand'   => ['tag-brand',   'brand'],
					'labs'    => ['tag-blog',    'discovered'],
				];
				if (isset($tag_pills[$tag])) {
					echo ' <span class="' . esc_attr($tag_pills[$tag][0]) . '">' . esc_html($tag_pills[$tag][1]) . '</span>';
				}
			?></div>
			<div class="kw-rank">
				<span class="rn" style="<?php echo esc_attr($color); ?>"><?php echo $rk ? '#' . $rk : '—'; ?></span>
				<?php if ($map_rank > 0) : ?><div style="font-size:9px;color:#2e7d32;line-height:1;">&#9678;#<?php echo (int) $map_rank; ?></div><?php endif; ?>
			</div>
			<div class="kw-change"><?php if ($ch === null) : ?><span style="color:#aaa">—</span><?php elseif ($ch > 0) : ?><span class="cu">&#9650;<?php echo (int)$ch; ?></span><?php elseif ($ch < 0) : ?><span class="cd">&#9660;<?php echo abs((int)$ch); ?></span><?php else : ?><span style="color:#aaa">—</span><?php endif; ?></div>
			<div class="kw-trend"><canvas id="kws-<?php echo $i; ?>" width="238" height="24"></canvas></div>
			<div class="kw-vol" style="color:#888;font-size:11px;"><?php echo $row['item']['search_vol'] ? number_format((int)$row['item']['search_vol']) : '—'; ?></div>
		</li>
		<?php endforeach; ?>
	</ul>

	<div class="kwfoot">
		<span><?php echo $total; ?> keywords · Snapshot: <?php echo esc_html($snapshot_date ?: 'None'); ?> · Fetched: <?php echo esc_html($last_label); ?></span>
		<a href="<?php echo esc_url(menu_page_url('kw-rankings', false)); ?>">Manage →</a>
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
				var y = v > 0 ? 2 + ((v - mn) / (mx - mn)) * (h - 6) : h - 2;
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
	if (_USER_LOGIN !== 'battleplanweb') return;   // Keywords is battleplanweb-only
	add_submenu_page('tools.php', 'Keyword Rankings', 'Keywords', 'manage_options', 'kw-rankings', 'bp_kw_render_admin_page');
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

		if ($action === 'check_keyword') {
			$kw_key = sanitize_key($_POST['kw_key'] ?? '');
			if (isset($tracked[$kw_key])) {
				$entry   = $tracked[$kw_key];
				$label   = $entry['keyword'] ?? '';
				$device  = bp_kw_primary_device();
				$cadence = bp_kw_cadence_for_record($entry, (int) ($entry['last_rank'] ?? 0));
				$path    = ($cadence['endpoint'] === 'live_advanced')
					? '/v3/serp/google/organic/live/advanced'
					: '/v3/serp/google/organic/live/regular';
				$payload = [[
					'keyword'       => $entry['keyword'],
					'location_code' => 2840,
					'language_code' => 'en',
					'device'        => $device,
					'depth'         => (int) $cadence['depth'],
				]];
				$response = bp_kw_dfs_request($path, $payload);
				$ok       = $response && ($response['status_code'] ?? 0) === 20000;
				$task     = $ok ? ($response['tasks'][0] ?? null) : null;
				$task_ok  = $task && ($task['status_code'] ?? 0) === 20000;

				if ($task_ok) {
					$domain   = preg_replace('/^www\./', '', bp_kw_site_domain());
					$place_id = customer_info()['pid'] ?? null;
					$parsed   = bp_kw_parse_serp_response($response, $domain, $place_id);
					$today    = date('Y-m-d');
					$new_rank = (int) ($parsed['rank']     ?? 0);
					$new_map  = (int) ($parsed['map_rank'] ?? 0);

					// Manual check — commit directly, no anomaly/tentative
					$tracked[$kw_key]['last_rank']           = $new_rank;
					$tracked[$kw_key]['last_map_rank']       = $new_map;
					$tracked[$kw_key]['last_check']          = $today;
					$tracked[$kw_key]['last_seen']           = $today;
					$tracked[$kw_key]['last_device']         = $device;
					$tracked[$kw_key]['last_type']           = $parsed['type'] ?? '';
					$tracked[$kw_key]['tentative_rank']      = null;
					$tracked[$kw_key]['tentative_map_rank']  = null;
					$tracked[$kw_key]['tentative_at']        = null;
					$tracked[$kw_key]['flap_count']          = 0;
					if (!empty($parsed['url'])) $tracked[$kw_key]['url'] = $parsed['url'];

					$history = bp_kw_history();
					if (!isset($history[$kw_key])) $history[$kw_key] = [];
					$history[$kw_key][$today] = $new_rank;

					$cadence_after = bp_kw_cadence_for_record($tracked[$kw_key], $new_rank);
					$tracked[$kw_key]['next_check'] = date('Y-m-d', strtotime('+' . (int) $cadence_after['cadence_days'] . ' days'));

					bp_kw_save_tracked($tracked);
					bp_kw_save_history($history);

					$rank_text = $new_rank > 0 ? '#' . $new_rank : 'not in top ' . (int) $cadence['depth'];
					$next      = $tracked[$kw_key]['next_check'];
					$class     = $new_rank > 0 ? 'notice-success' : 'notice-warning';
					echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>Checked <strong>' . esc_html($label) . '</strong> — <strong>' . esc_html($rank_text) . '</strong>. Next check: <strong>' . esc_html($next) . '</strong>.</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>SERP query failed for <strong>' . esc_html($label) . '</strong>.</p></div>';
				}
			}
		}

		if ($action === 'fetch_now') {
			$result   = bp_kw_run_chron(true);
			$tracked  = bp_kw_tracked();
			$primary  = (int) ($result['primary']   ?? 0);
			$secondary= (int) ($result['secondary'] ?? 0);
			$remaining= (int) ($result['remaining'] ?? 0);
			$bootstrap= !empty($result['bootstrapping']);
			$msg      = "Run complete — <strong>{$primary}</strong> primary + <strong>{$secondary}</strong> secondary keywords checked.";
			if ($remaining > 0) {
				$msg .= " <strong>{$remaining}</strong> still due — click <em>Fetch Rankings Now</em> again to continue.";
			}
			if ($bootstrap) {
				$msg .= ' <em>(Bootstrap mode — primary device only; secondary spot-check resumes once every keyword has been checked at least once.)</em>';
			}
			$diag_lines = (array) get_option('bp_kw_last_run_diags', []);
			if ($diag_lines) {
				$msg .= '<br><strong>Batch diagnostics:</strong><br><code style="font-size:11px;display:block;background:#f0f0f0;padding:6px;margin-top:4px;">' . esc_html(implode("\n", $diag_lines)) . '</code>';
			}
			$class = $remaining > 0 ? 'notice-warning' : 'notice-success';
			echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . $msg . '</p></div>';
		}

		if ($action === 'clear_all') {
			bp_kw_save_tracked([]);
			bp_kw_save_history([]);
			delete_option('bp_kw_snapshot_date');
			delete_option('bp_kw_last_total_count');
			$tracked = [];
			echo '<div class="notice notice-warning is-dismissible"><p>All keyword data and history cleared.</p></div>';
		}

		if ($action === 'compact_history') {
			$result = bp_kw_compact_history_weekly(bp_kw_history());
			bp_kw_save_history($result['history']);
			echo '<div class="notice notice-success is-dismissible"><p>History compacted — <strong>' . (int) $result['removed'] . '</strong> intra-week duplicate entries removed.</p></div>';
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

	// Cost estimate — walk target_records and sum per-record annual SERP cost based on
	// cadence (source × heat × is_primary) + quarterly secondary-device spot-check.
	// Live Regular at ~$0.0006/call for all queries (map pack tracking is deferred —
	// see bp_kw_cadence_for_record() for the rationale).
	$cost_records = bp_kw_target_records();
	$kw_count     = count($cost_records);
	$cost_annual  = 0.0;
	$src_counts   = ['base' => 0, 'service_area' => 0, 'jobsite' => 0, 'brand' => 0];
	$heat_counts  = ['cold' => 0, 'warm' => 0, 'hot' => 0];

	foreach ($cost_records as $r) {
		$rate       = 0.0006;
		$src        = $r['source']  ?? 'base';
		$heat       = $r['heat']    ?? '';
		$is_primary = !empty($r['is_primary']);

		if ($src === 'jobsite') {
			if      ($heat === 'hot')  $primary_per_year = 26;                  // biweekly
			elseif  ($heat === 'warm') $primary_per_year = $is_primary ? 12 : 4; // monthly / quarterly
			else                       $primary_per_year = 4;                    // cold — quarterly
		} elseif ($src === 'brand') {
			$primary_per_year = 1;                                                // annual sanity check
		} else {
			$primary_per_year = $is_primary ? 12 : 4;
		}
		$cost_annual += ($primary_per_year + 4) * $rate;  // +4 = quarterly secondary-device spot-check

		if (isset($src_counts[$src])) $src_counts[$src]++;
		if ($src === 'jobsite' && isset($heat_counts[$heat])) $heat_counts[$heat]++;
	}
	$cost_annual  = round($cost_annual, 2);
	$cost_monthly = round($cost_annual / 12, 2);

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
	// Only show keywords the site actually ranks for — rank 1-100 with history. Unranked
	// entries (rank=0) are tracked but hidden from the table since they're noise.
	$rows = array_values(array_filter($rows, function($r) use ($history) {
		return !empty($history[$r['kw_key']]) && $r['rank'] !== null && $r['rank'] > 0 && $r['rank'] <= 100;
	}));

	usort($rows, function($a, $b) {
		if ($a['rank'] && $b['rank']) return $a['rank'] <=> $b['rank'];
		if ($a['rank']) return -1;
		if ($b['rank']) return 1;
		return strcmp($a['item']['keyword'], $b['item']['keyword']);
	});

	// Source-based grouping. Entries from target_records have a 'source' field
	// (base/jobsite/brand). Anything else came from Labs discovery → 'labs'.
	foreach ($rows as &$row) {
		$src = $row['item']['source'] ?? '';
		if (!$src) $src = 'labs';
		$row['group']         = $src;
		$row['item']['group'] = $src;
	}
	unset($row);

	// Source counts
	$gcounts = ['base' => 0, 'service_area' => 0, 'jobsite' => 0, 'brand' => 0, 'labs' => 0];
	// Page-type counts (home page vs everything else)
	$pcounts = ['home' => 0, 'secondary' => 0];
	foreach ($rows as $r) {
		$g = $r['group'];
		if (isset($gcounts[$g])) $gcounts[$g]++;
		else $gcounts['labs']++;
		$path = parse_url($r['item']['url'] ?? '', PHP_URL_PATH) ?: '/';
		if ($path === '/' || $path === '') $pcounts['home']++;
		else                                $pcounts['secondary']++;
	}

	$brand_terms_js = json_encode(bp_kw_brand_terms());

	?>
	<div class="wrap">
	<h1>Keyword Rankings</h1>

	<form method="post" style="display:inline-block;margin-right:8px;">
		<?php wp_nonce_field('bp_kw_manage'); ?>
		<input type="hidden" name="bp_kw_action" value="fetch_now">
		<input type="submit" class="button button-primary" value="&#9654; Fetch Rankings Now">
	</form>
	<form method="post" style="display:inline-block;margin-right:8px;" onsubmit="return confirm('Compact history to weekly resolution? Drops intra-week duplicates but keeps the first, weekly anchors, and most recent entry.');">
		<?php wp_nonce_field('bp_kw_manage'); ?>
		<input type="hidden" name="bp_kw_action" value="compact_history">
		<input type="submit" class="button" value="Compact History">
	</form>
	<form method="post" style="display:inline-block;" onsubmit="return confirm('Clear ALL keyword data and history? This cannot be undone.');">
		<?php wp_nonce_field('bp_kw_manage'); ?>
		<input type="hidden" name="bp_kw_action" value="clear_all">
		<input type="submit" class="button button-link-delete" value="&#10005; Clear All">
	</form>
<p class="description" style="margin-top:8px;">
		Last run: <strong><?php echo esc_html($last_label); ?></strong> &nbsp;·&nbsp;
		Snapshot: <strong><?php echo esc_html($snapshot_date ?: 'None'); ?></strong> &nbsp;·&nbsp;
		Tracked: <strong><?php echo count($tracked); ?></strong> · Targeted: <strong><?php echo (int) $kw_count; ?></strong>
		<?php if ($kw_count) : ?>
		&nbsp;·&nbsp; Est. cost: <strong>$<?php echo number_format($cost_monthly, 2); ?>/mo</strong> &nbsp;·&nbsp; <strong>$<?php echo number_format($cost_annual, 2); ?>/yr</strong>
		<?php endif; ?>
	</p>

	<?php
	// --- Diagnostics panel ---
	$ci_for_diag     = customer_info();
	$detected_type   = strtolower($ci_for_diag['business-type'] ?? $ci_for_diag['site-type'] ?? '');
	$detected_areas  = bp_kw_target_areas();
	$detected_dom    = preg_replace('/^www\./', '', bp_kw_site_domain());
	$target_keywords = bp_kw_target_list();
	$target_count    = count($target_keywords);
	$primary_device  = bp_kw_primary_device();
	$ga4_devices     = get_option('bp_ga4_devices_clean', []);
	$ga4_mobile      = is_array($ga4_devices) ? (int) ($ga4_devices['mobile']['sessions-90']  ?? 0) : 0;
	$ga4_desktop     = is_array($ga4_devices) ? (int) ($ga4_devices['desktop']['sessions-90'] ?? 0) : 0;
	$ga4_tablet      = is_array($ga4_devices) ? (int) ($ga4_devices['tablet']['sessions-90']  ?? 0) : 0;
	$ga4_total       = $ga4_mobile + $ga4_desktop + $ga4_tablet;
	$ga4_mobile_pct  = $ga4_total > 0 ? round($ga4_mobile / $ga4_total * 100) : 0;
	$discovery_on    = bp_kw_discovery_enabled();
	$test_kw         = isset($_POST['bp_kw_test_keyword']) ? sanitize_text_field(wp_unslash($_POST['bp_kw_test_keyword'])) : '';
	$test_run        = ($test_kw && isset($_POST['bp_kw_action']) && $_POST['bp_kw_action'] === 'test_keyword' && check_admin_referer('bp_kw_manage'));
	$test_result     = $test_run ? bp_kw_test_serp(strtolower(trim($test_kw))) : null;
	?>
	<details style="margin:12px 0;background:#f6f7f7;padding:10px 14px;border-left:4px solid #2271b1;">
		<summary style="cursor:pointer;font-weight:600;">Diagnostics — what's being tracked &amp; why</summary>
		<div style="margin-top:10px;font-size:12px;line-height:1.6;">
			<p>
				<strong>Detected domain:</strong> <code><?php echo esc_html($detected_dom ?: '(none)'); ?></code><br>
				<strong>Business type:</strong> <code><?php echo esc_html($detected_type ?: '(unset — vertical defaults will be empty)'); ?></code><br>
				<strong>Fetch mode:</strong> <code>SERP per-keyword (Live Regular, location_code=2840)<?php if ($discovery_on) echo ' + Labs discovery'; ?></code><br>
				<strong>Primary device:</strong> <code><?php echo esc_html($primary_device); ?></code>
				<?php if ($ga4_total > 0) : ?>
					&nbsp;<em>(<?php echo $ga4_mobile_pct; ?>% mobile over last 90 days · <?php echo number_format($ga4_total); ?> sessions)</em>
				<?php else : ?>
					&nbsp;<em>(no GA4 device data yet — defaulting to desktop)</em>
				<?php endif; ?>
				<br>
				<strong>Areas (<?php echo count($detected_areas); ?>):</strong>
				<?php if ($detected_areas) : ?>
					<code><?php echo esc_html(implode(' · ', array_slice($detected_areas, 0, 30))); ?><?php if (count($detected_areas) > 30) echo ' …'; ?></code>
				<?php else : ?>
					<em>(none — set <code>service-areas</code> in <code>customer_info</code> or activate Jobsite GEO)</em>
				<?php endif; ?>
				<br>
				<strong>Target keyword count:</strong> <?php echo (int) $target_count; ?>
				<?php if ($target_count) : ?>
					&nbsp; <em>(first few:</em> <code><?php echo esc_html(implode(' · ', array_slice($target_keywords, 0, 6))); ?></code><em>)</em>
				<?php endif; ?>
				<br>
				<strong>By source:</strong>
				<span style="background:#e8f4fd;color:#1a73e8;padding:1px 6px;border-radius:8px;"><?php echo (int) $src_counts['base']; ?> base (home town)</span>
				<span style="background:#fff8e1;color:#a36a00;padding:1px 6px;border-radius:8px;margin-left:4px;"><?php echo (int) $src_counts['service_area']; ?> service area</span>
				<span style="background:#e8f5e9;color:#2e7d32;padding:1px 6px;border-radius:8px;margin-left:4px;"><?php echo (int) $src_counts['jobsite']; ?> jobsite</span>
				<span style="background:#f3e8fd;color:#6f42c1;padding:1px 6px;border-radius:8px;margin-left:4px;"><?php echo (int) $src_counts['brand']; ?> brand</span>
				<?php if ($src_counts['jobsite']) : ?>
					<br>
					<strong>Jobsite heat:</strong>
					<span style="background:#fde2e2;color:#c0392b;padding:1px 6px;border-radius:8px;">&#128293; <?php echo (int) $heat_counts['hot']; ?> hot</span>
					<span style="background:#fff3cd;color:#856404;padding:1px 6px;border-radius:8px;margin-left:4px;">&#9728; <?php echo (int) $heat_counts['warm']; ?> warm</span>
					<span style="background:#e9ecef;color:#5a6268;padding:1px 6px;border-radius:8px;margin-left:4px;">&#10052; <?php echo (int) $heat_counts['cold']; ?> cold</span>
				<?php endif; ?>
			</p>

			<form method="post" style="margin-top:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
				<?php wp_nonce_field('bp_kw_manage'); ?>
				<input type="hidden" name="bp_kw_action" value="test_keyword">
				<label><strong>Test SERP for keyword:</strong></label>
				<input type="text" name="bp_kw_test_keyword" value="<?php echo esc_attr($test_kw); ?>" placeholder="hvac repair huntingdon tn" style="width:280px;">
				<button type="submit" class="button button-secondary">Run test query</button>
				<span style="color:#888;">~$0.0006 per click</span>
			</form>

			<?php if ($test_run && $test_result) :
				$test_norm     = strtolower(trim($test_kw));
				$test_kw_key   = md5($test_norm);
				$test_stored   = $tracked[$test_kw_key] ?? null;
				$test_history  = $history[$test_kw_key] ?? [];
				krsort($test_history);
				$test_in_target = in_array($test_norm, array_map(fn($k) => strtolower(trim($k)), $target_keywords), true);
			?>
				<div style="margin-top:10px;background:#fff;padding:10px 14px;border:1px solid #ddd;">
					<p style="margin:0 0 8px;padding:6px 10px;background:#fffbe6;border-left:3px solid #f0c000;font-size:12px;">
						<strong>Stored state for this keyword:</strong><br>
						In current target list: <strong style="color:<?php echo $test_in_target ? '#28a745' : '#dc3545'; ?>"><?php echo $test_in_target ? 'YES' : 'NO'; ?></strong>
						&nbsp;|&nbsp; In tracked storage: <strong style="color:<?php echo $test_stored ? '#28a745' : '#dc3545'; ?>"><?php echo $test_stored ? 'YES' : 'NO'; ?></strong>
						<?php if ($test_stored) : ?>
							<br>Source: <code><?php echo esc_html($test_stored['source'] ?? '—'); ?></code>
							&nbsp;|&nbsp; Last type: <code><?php echo esc_html($test_stored['last_type'] ?? '—'); ?></code>
							&nbsp;|&nbsp; Last seen: <code><?php echo esc_html($test_stored['last_seen'] ?? '—'); ?></code>
							&nbsp;|&nbsp; Next check: <code><?php echo esc_html($test_stored['next_check'] ?? '—'); ?></code>
						<?php endif; ?>
						<?php if (!empty($test_history)) :
							$latest_date = array_key_first($test_history);
							$latest_rank = $test_history[$latest_date];
						?>
							<br>Latest history entry: <code><?php echo esc_html($latest_date); ?></code> = rank <code><?php echo (int) $latest_rank; ?></code>
							<?php if (count($test_history) > 1) : ?>
								&nbsp;(<?php echo count($test_history); ?> total history entries)
							<?php endif; ?>
						<?php endif; ?>
					</p>
					<p><strong>Query:</strong> <code><?php echo esc_html($test_kw); ?></code><br>
					<strong>API status:</strong> <?php echo (int) $test_result['status_code']; ?>
					<?php if ($test_result['status_message']) echo ' — ' . esc_html($test_result['status_message']); ?>
					&nbsp;|&nbsp; <strong>Task status:</strong> <?php echo (int) $test_result['task_status']; ?>
					<?php if ($test_result['task_message']) echo ' — ' . esc_html($test_result['task_message']); ?>
					&nbsp;|&nbsp; <strong>Items returned:</strong> <?php echo count($test_result['items']); ?></p>

					<?php if (!empty($test_result['items'])) : ?>
						<table class="widefat striped" style="font-size:11px;margin-top:6px;">
							<thead><tr><th>#</th><th>Type</th><th>Rank</th><th>Domain</th><th>URL</th><th>Match?</th></tr></thead>
							<tbody>
								<?php foreach (array_slice($test_result['items'], 0, 30) as $i => $it) :
									$it_dom = strtolower($it['domain'] ?? '');
									if (!$it_dom) {
										$h = parse_url($it['url'] ?? '', PHP_URL_HOST);
										$it_dom = strtolower($h ?: '');
									}
									$it_dom_clean = preg_replace('/^www\./', '', $it_dom);
									$is_match = $it_dom_clean && $it_dom_clean === $detected_dom;
								?>
									<tr <?php if ($is_match) echo 'style="background:#d4edda;"'; ?>>
										<td><?php echo $i + 1; ?></td>
										<td><code><?php echo esc_html($it['type'] ?? ''); ?></code></td>
										<td><?php echo (int) ($it['rank_absolute'] ?? 0); ?></td>
										<td><code><?php echo esc_html($it_dom_clean ?: '—'); ?></code></td>
										<td style="word-break:break-all;"><?php echo esc_html(substr($it['url'] ?? '', 0, 80)); ?></td>
										<td><?php echo $is_match ? '<strong style="color:#28a745;">YES</strong>' : '—'; ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p style="font-size:11px;color:#888;margin-top:6px;">
							Showing first 30 of <?php echo count($test_result['items']); ?> SERP items. Green row = matches our domain (<code><?php echo esc_html($detected_dom); ?></code>). If your business is on Google but no row matches, the issue is the domain comparison — DataForSEO is reporting a different domain than we're using to look it up.
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</details>

	<hr>

	<h2 class="title">Ranked Keywords</h2>

	<?php if (!$rows) : ?>
		<p>No data yet. Click <strong>Fetch Rankings Now</strong> above to pull the latest snapshot from DataForSEO Labs.</p>
	<?php else : ?>

	<!-- Filter bar -->
	<div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;flex-wrap:wrap;">
		<input type="search" id="kw-search" placeholder="Filter keywords…" style="width:200px;padding:4px 8px;font-size:13px;">
		<label style="font-size:12px;color:#555;display:flex;align-items:center;gap:6px;">
			Exclude:
			<input type="text" id="kw-exclude" placeholder="jackson, jackson's" style="width:200px;padding:4px 8px;font-size:13px;" title="Comma-separated terms — any keyword containing these will be hidden">
		</label>
		<span id="kw-visible-count" style="font-size:12px;color:#888;"><?php echo count($rows); ?> keywords</span>
	</div>
	<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap;">
		<div id="kw-pos-filters" style="display:flex;gap:4px;">
			<button type="button" class="button button-small kw-pos-btn active" data-pos="all">All positions</button>
			<button type="button" class="button button-small kw-pos-btn" data-pos="1-3" style="background:#d4edda;">1–3</button>
			<button type="button" class="button button-small kw-pos-btn" data-pos="4-10" style="background:#fff3cd;">4–10</button>
			<button type="button" class="button button-small kw-pos-btn" data-pos="11-20" style="background:#fde8cc;">11–20</button>
			<button type="button" class="button button-small kw-pos-btn" data-pos="21+" style="background:#f8d7da;">21+</button>
		</div>
		<div id="kw-group-filters" style="display:flex;gap:4px;">
			<button type="button" class="button button-small kw-grp-btn active" data-grp="all">All sources</button>
			<button type="button" class="button button-small kw-grp-btn" data-grp="base"         style="background:#e8f4fd;">Base (<?php echo (int)$gcounts['base']; ?>)</button>
			<?php if ($gcounts['service_area']) : ?><button type="button" class="button button-small kw-grp-btn" data-grp="service_area" style="background:#fff8e1;color:#a36a00;">Service Area (<?php echo (int)$gcounts['service_area']; ?>)</button><?php endif; ?>
			<button type="button" class="button button-small kw-grp-btn" data-grp="jobsite"      style="background:#e8f5e9;">Jobsite (<?php echo (int)$gcounts['jobsite']; ?>)</button>
			<button type="button" class="button button-small kw-grp-btn" data-grp="brand"        style="background:#f3e8fd;color:#6f42c1;">Brand (<?php echo (int)$gcounts['brand']; ?>)</button>
			<?php if ($gcounts['labs']) : ?><button type="button" class="button button-small kw-grp-btn" data-grp="labs" style="background:#fef9e7;color:#b06000;">Discovered (<?php echo (int)$gcounts['labs']; ?>)</button><?php endif; ?>
		</div>
		<div id="kw-page-filters" style="display:flex;gap:4px;">
			<button type="button" class="button button-small kw-page-btn active" data-page="all">All pages</button>
			<button type="button" class="button button-small kw-page-btn" data-page="home"      style="background:#e0f7fa;">Home Page (<?php echo (int)$pcounts['home']; ?>)</button>
			<button type="button" class="button button-small kw-page-btn" data-page="secondary" style="background:#f3e5f5;">Secondary (<?php echo (int)$pcounts['secondary']; ?>)</button>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped" id="kw-table" style="font-size:13px;">
		<thead>
			<tr>
				<th style="width:26%">Keyword</th>
				<th style="width:6%">Rank</th>
				<th style="width:6%">Change</th>
				<th style="width:5%">Vol</th>
				<th style="width:6%">Type</th>
				<th style="width:22%">URL</th>
				<th style="width:8%">First Seen</th>
				<th style="width:8%">Next Check</th>
				<th style="width:13%">Actions</th>
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

			// New-model fields (may be missing on legacy/Labs-only entries)
			$src          = $item['source']         ?? '';
			$heat         = $item['heat']           ?? '';
			$map_rank     = (int) ($item['last_map_rank'] ?? 0);
			$is_tentative = !empty($item['tentative_rank']) || !empty($item['tentative_at']);

			// Page type — home page (/) vs secondary (any subpath the keyword ranks for)
			$path      = parse_url($item['url'] ?? '', PHP_URL_PATH) ?: '/';
			$page_type = ($path === '/' || $path === '') ? 'home' : 'secondary';

			// Spark data for expandable row — all available history
			$kh_spark = $history[$kw_key] ?? [];
			ksort($kh_spark);
			$spark_dates = array_keys($kh_spark);
			$spark_ranks = array_values($kh_spark);
			?>
			<tr class="kw-data-row<?php if ($is_tentative) echo ' kw-tentative'; ?>"
			    data-keyword="<?php echo esc_attr(strtolower($item['keyword'])); ?>"
			    data-pos="<?php echo esc_attr($pos_bucket); ?>"
			    data-grp="<?php echo esc_attr($group); ?>"
			    data-page="<?php echo esc_attr($page_type); ?>"
			    data-spark-dates="<?php echo esc_attr(json_encode($spark_dates)); ?>"
			    data-spark-ranks="<?php echo esc_attr(json_encode($spark_ranks)); ?>">
				<td>
					<a href="https://www.google.com/search?q=<?php echo urlencode($item['keyword']); ?>" target="_blank" rel="noopener"><?php echo esc_html($item['keyword']); ?></a>
					<?php if ($is_tentative) : ?>
						<span title="Awaiting confirmation — tentative reading was &num;<?php echo (int) ($item['tentative_rank'] ?? 0); ?> on <?php echo esc_attr($item['tentative_at'] ?? ''); ?>" style="background:#fff3cd;color:#856404;font-size:10px;padding:1px 5px;border-radius:8px;margin-left:4px;">tentative</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ($rank) : ?>
						<strong style="color:<?php echo $rank_color; ?>">#<?php echo (int)$rank; ?></strong>
						<?php if (!empty($item['last_type'])) : ?>
							<div style="font-size:10px;color:#888;line-height:1.1;margin-top:1px;"><?php echo esc_html(bp_kw_type_label($item['last_type'])); ?></div>
						<?php endif; ?>
					<?php else : ?>
						<span style="color:#aaa">—</span>
					<?php endif; ?>
					<?php if ($map_rank > 0) : ?>
						<div style="font-size:10px;color:#2e7d32;line-height:1.1;margin-top:2px;" title="Position in Google's local 3-pack">&#9678; pack #<?php echo (int) $map_rank; ?></div>
					<?php endif; ?>
				</td>
				<td>
					<?php if ($change === null) : ?>
						<span style="color:#aaa">—</span>
					<?php elseif ($change > 0) : ?>
						<span style="color:#28a745">&#9650;<?php echo (int)$change; ?></span>
					<?php elseif ($change < 0) : ?>
						<span style="color:#dc3545">&#9660;<?php echo abs((int)$change); ?></span>
					<?php else : ?>
						<span style="color:#aaa">—</span>
					<?php endif; ?>
				</td>
				<td style="color:#888;font-size:11px;"><?php echo $item['search_vol'] ? number_format((int)$item['search_vol']) : '—'; ?></td>
				<td>
					<?php
					// Source pill — uses the unified $group computed above (always populated).
					// 'labs' covers anything Labs surfaced that wasn't already SERP-tracked.
					$src_for_pill = $group ?: 'labs';
					$src_labels   = ['base' => 'base', 'service_area' => 'service', 'jobsite' => 'jobsite', 'brand' => 'brand', 'labs' => 'discovered'];
					$src_styles   = [
						'base'         => 'background:#e8f4fd;color:#1a73e8;',
						'service_area' => 'background:#fff8e1;color:#a36a00;',
						'jobsite'      => 'background:#e8f5e9;color:#2e7d32;',
						'brand'        => 'background:#f3e8fd;color:#6f42c1;',
						'labs'         => 'background:#fef9e7;color:#b06000;',
					];
					$pill_label = $src_labels[$src_for_pill] ?? $src_for_pill;
					$pill_style = $src_styles[$src_for_pill] ?? 'background:#f0f0f0;color:#555;';
					?>
					<span style="<?php echo $pill_style; ?>font-size:11px;padding:2px 6px;border-radius:8px;"><?php echo esc_html($pill_label); ?></span>
					<?php if ($src_for_pill === 'jobsite' && $heat) :
						$heat_icon  = $heat === 'hot' ? '&#128293;' : ($heat === 'warm' ? '&#9728;' : '&#10052;');
						$heat_style = $heat === 'hot' ? 'background:#fde2e2;color:#c0392b;' : ($heat === 'warm' ? 'background:#fff3cd;color:#856404;' : 'background:#e9ecef;color:#5a6268;');
						?>
						<span style="<?php echo $heat_style; ?>font-size:10px;padding:1px 5px;border-radius:8px;margin-left:2px;" title="Heat: <?php echo esc_attr($heat); ?>"><?php echo $heat_icon; ?></span>
					<?php endif; ?>
				</td>
				<td style="font-size:11px;">
					<a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener">
						<?php echo esc_html(parse_url($item['url'], PHP_URL_PATH) ?: '/'); ?>
					</a>
				</td>
				<td style="color:#888;font-size:11px;"><?php echo esc_html($item['first_seen'] ?? '—'); ?></td>
				<td style="color:#888;font-size:11px;"><?php echo esc_html($item['next_check'] ?? '—'); ?></td>
				<td>
					<form method="post" style="display:inline;">
						<?php wp_nonce_field('bp_kw_manage'); ?>
						<input type="hidden" name="bp_kw_action" value="check_keyword">
						<input type="hidden" name="kw_key" value="<?php echo esc_attr($kw_key); ?>">
						<button type="submit" class="button button-small button-primary" title="Check this keyword's rank now">Check</button>
					</form>
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
			<tr class="kw-spark-row">
				<td colspan="8" style="padding:10px 16px 12px;background:#f6f7f7;border-top:none;">
					<canvas class="kw-spark-canvas" height="60" style="display:block;width:100%;"></canvas>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<style>
	#kw-table tbody tr.kw-data-row { cursor: pointer; }
	#kw-table tbody tr.kw-data-row:hover td { background: #f0f6ff !important; }
	#kw-table tbody tr.kw-tentative td:first-child { border-left: 3px solid #f0c000; }
	#kw-table tbody tr.kw-tentative td { background: #fffbe6; }
	#kw-table tbody .kw-spark-row { display: none; }
	#kw-table tbody .kw-spark-row.open { display: table-row; }
	</style>

	<script>
	(function(){
		var search       = document.getElementById('kw-search');
		var excludeInput = document.getElementById('kw-exclude');
		var table        = document.getElementById('kw-table');
		var countEl      = document.getElementById('kw-visible-count');
		var activePos    = 'all';
		var activeGrp    = 'all';
		var activePage   = 'all';
		var excludeTerms = [];

		// Pre-populate exclude box with brand terms from customer_info
		var brandTerms = <?php echo $brand_terms_js; ?>;
		if (brandTerms.length) excludeInput.value = brandTerms.join(', ');
		excludeTerms = brandTerms.slice();

		function parseExclude() {
			var val = excludeInput.value.toLowerCase().trim();
			excludeTerms = val ? val.split(',').map(function(t){ return t.trim(); }).filter(Boolean) : [];
		}

		function applyFilters() {
			var term = search.value.toLowerCase().trim();
			var rows = table.querySelectorAll('tbody tr');
			var vis  = 0;
			rows.forEach(function(tr) {
				var kw   = tr.dataset.keyword || '';
				var pos  = tr.dataset.pos     || '';
				var grp  = tr.dataset.grp     || '';
				var page = tr.dataset.page    || '';
				// Exclude filter hides rows unless we're specifically browsing the Brand group
				var excluded = activeGrp !== 'brand' && excludeTerms.some(function(t){ return t && kw.indexOf(t) !== -1; });
				var show = (
					!excluded &&
					(!term           || kw.indexOf(term) !== -1) &&
					(activePos === 'all'  || pos  === activePos) &&
					(activeGrp === 'all'  || grp  === activeGrp) &&
					(activePage === 'all' || page === activePage)
				);
				tr.style.display = show ? '' : 'none';
				if (show) vis++;
			});
			countEl.textContent = vis + ' keyword' + (vis !== 1 ? 's' : '');
		}

		search.addEventListener('input', applyFilters);
		excludeInput.addEventListener('input', function(){ parseExclude(); applyFilters(); });

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

		document.getElementById('kw-page-filters').addEventListener('click', function(e) {
			var btn = e.target.closest('.kw-page-btn');
			if (!btn) return;
			document.querySelectorAll('.kw-page-btn').forEach(function(b){ b.classList.remove('active'); });
			btn.classList.add('active');
			activePage = btn.dataset.page;
			applyFilters();
		});

		// Apply on load so brand terms are pre-filtered from default "All types" view
		applyFilters();

		// Sparkline expand/collapse on row click
		document.querySelectorAll('#kw-table tbody tr.kw-data-row').forEach(function(tr) {
			tr.addEventListener('click', function(e) {
				if (e.target.closest('button, a, form, input')) return;
				var sparkRow = tr.nextElementSibling;
				if (!sparkRow || !sparkRow.classList.contains('kw-spark-row')) return;
				var isOpen = sparkRow.classList.toggle('open');
				if (isOpen) {
					var canvas = sparkRow.querySelector('.kw-spark-canvas');
					var dates  = JSON.parse(tr.dataset.sparkDates || '[]');
					var ranks  = JSON.parse(tr.dataset.sparkRanks || '[]');
					drawAdminSparkline(canvas, dates, ranks);
				}
			});
		});

		function drawAdminSparkline(canvas, dates, ranks) {
			canvas.width = canvas.offsetWidth;
			var ctx  = canvas.getContext('2d');
			var w    = canvas.width, h = canvas.height;
			var padT = 16, padB = 18, padL = 8, padR = 8;
			var drawW = w - padL - padR;
			var drawH = h - padT - padB;

			ctx.clearRect(0, 0, w, h);

			var valid = ranks.filter(function(v) { return v > 0; });
			if (!valid.length) {
				ctx.fillStyle = '#aaa';
				ctx.font = '12px sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText('No history data', w / 2, h / 2);
				return;
			}

			var mn = Math.min.apply(null, valid);
			var mx = Math.max.apply(null, valid);
			if (mn === mx) { mn = Math.max(1, mn - 1); mx = mn + 2; }

			var n = ranks.length;
			function xPos(j) { return padL + (n > 1 ? (j / (n - 1)) * drawW : drawW / 2); }
			function yPos(v) { return padT + ((v - mn) / (mx - mn)) * drawH; }

			// Draw line
			ctx.beginPath();
			ranks.forEach(function(v, j) {
				var x = xPos(j), y = v > 0 ? yPos(v) : padT + drawH;
				j === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
			});
			ctx.strokeStyle = '#4a90d9';
			ctx.lineWidth = 2;
			ctx.stroke();

			// Draw dots, rank labels, date labels
			ranks.forEach(function(v, j) {
				if (v <= 0) return;
				var x = xPos(j), y = yPos(v);

				ctx.beginPath();
				ctx.arc(x, y, 3.5, 0, Math.PI * 2);
				ctx.fillStyle = '#4a90d9';
				ctx.fill();

				// Rank label above dot
				ctx.font = 'bold 10px sans-serif';
				ctx.textAlign = 'center';
				ctx.fillStyle = '#333';
				ctx.fillText('#' + v, x, Math.max(y - 6, padT - 2));

				// Date label below canvas
				if (dates[j]) {
					var d = new Date(dates[j] + 'T12:00:00');
					var label = (d.getMonth() + 1) + '/' + d.getDate();
					ctx.font = '10px sans-serif';
					ctx.fillStyle = '#999';
					ctx.fillText(label, x, h - 3);
				}
			});
		}
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
