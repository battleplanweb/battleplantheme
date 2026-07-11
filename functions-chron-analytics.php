<?php
/* Battle Plan Web Design: Chron C — Analytics */

use Google\ApiCore\ApiException;

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\FilterExpressionList;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\Filter\InListFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;

function bp_run_chron_analytics(bool $force = false): void {

    $ga4 = bp_ga4_client();
    if (!$ga4) return;

    try {
        bp_ga4_collect_all_clean($ga4['client'], $ga4['property']);
    } catch (\Throwable $e) {
        error_log('GA4 collect failed: ' . $e->getMessage());
    }
}

/*
 * Build a GA4 Data API client + property id from the site's customer_info +
 * the GA4_SERVICE_ACCOUNT_JSON constant. Returns ['client'=>…, 'property'=>…]
 * or null if the site has no GA4 property / credentials are missing. Shared by
 * the nightly cron and any on-demand collectors (e.g. channel-history verify).
 */
function bp_ga4_client(): ?array {

    $customer_info = customer_info();
    $ga4_id        = $customer_info['google-tags']['prop-id'] ?? null;

    if (empty($ga4_id)) return null;
    if (!defined('GA4_SERVICE_ACCOUNT_JSON')) return null;

    $credentials = json_decode(base64_decode(GA4_SERVICE_ACCOUNT_JSON), true);
    if (!is_array($credentials)) return null;

    try {
        $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
    } catch (\Throwable $e) {
        error_log('GA4 client init failed: ' . $e->getMessage());
        return null;
    }

    return ['client' => $client, 'property' => $ga4_id];
}

/*--------------------------------------------------------------
# Search Console Helpers
--------------------------------------------------------------*/
function bp_gsc_collect_top_queries(): void {

    if (!defined('GA4_SERVICE_ACCOUNT_JSON')) return;

    $credentials = json_decode(base64_decode(GA4_SERVICE_ACCOUNT_JSON), true);
    $token       = bp_get_google_access_token($credentials, [
        'https://www.googleapis.com/auth/webmasters.readonly'
    ]);

    if (!$token) {
        //error_log('bp_gsc_collect_top_queries: failed to get token');
        return;
    }

    $siteUrl = 'sc-domain:' . str_replace(['https://', 'http://'], '', get_bloginfo('url'));
    $result  = [];

    $periods = [
        'week'     => 7,
        'month'    => 30,
        'quarter'  => 90,
        'semester' => 180,
        'year'     => 365,
    ];

    foreach ($periods as $label => $days) {

        $body = json_encode([
            'startDate'             => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'               => date('Y-m-d', strtotime('-1 day')),
            'dimensions'            => ['query'],
            'rowLimit'              => 50,
            'orderBy'               => [['fieldName' => 'clicks', 'sortOrder' => 'DESCENDING']],
            'dimensionFilterGroups' => [[
                'filters' => [[
                    'dimension'  => 'country',
                    'operator'   => 'equals',
                    'expression' => 'usa',
                ]]
            ]],
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://www.googleapis.com/webmasters/v3/sites/'
                                    . rawurlencode($siteUrl) . '/searchAnalytics/query',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $http     = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($http !== 200) {
            //error_log("bp_gsc_collect_top_queries: API error {$http} for {$label}: {$response}");
            continue;
        }

        $data = json_decode($response, true);

        foreach ($data['rows'] ?? [] as $row) {
            $query = $row['keys'][0] ?? '';
            if (!$query) continue;
            $result[$query][$label] = [
                'clicks'      => (int)$row['clicks'],
                'impressions' => (int)$row['impressions'],
                'ctr'         => round($row['ctr'] * 100, 2),
                'position'    => round($row['position'], 1),
            ];
        }
    }

    update_option('bp_gsc_top_queries', $result, false);
}

/*--------------------------------------------------------------
# GA4 Helpers
--------------------------------------------------------------*/

function bp_ga4_years_to_pull(): int {
    return 6;
}

function bp_ga4_excluded_cities(): array {
    return [
        'Orangetree','Ashburn','Boardman','Irvine','Prineville',
        'Forest City','Altoona','Moses Lake','The Dalles',
        'Council Bluffs','Hillsboro','Quincy','Reston',
    ];
}

function bp_ga4_dimension_filter(): FilterExpression {
    return new FilterExpression([
        'and_group' => new FilterExpressionList([
            'expressions' => [
                new FilterExpression([
                    'filter' => new Filter([
                        'field_name'    => 'country',
                        'string_filter' => new StringFilter(['match_type' => MatchType::EXACT, 'value' => 'United States']),
                    ]),
                ]),
                new FilterExpression([
                    'not_expression' => new FilterExpression([
                        'filter' => new Filter([
                            'field_name'     => 'city',
                            'in_list_filter' => new InListFilter(['values' => bp_ga4_excluded_cities(), 'case_sensitive' => false]),
                        ]),
                    ]),
                ]),
            ],
        ]),
    ]);
}

function bp_ga4_run_report_all_rows(BetaAnalyticsDataClient $client, array $request, int $pageSize = 5000): array {
    $allRows = [];
    $offset  = 0;
    $maxOffset   = 2000000;
    $maxRetries  = 3;

    while (true) {
        $request['limit']  = $pageSize;
        $request['offset'] = $offset;
        $attempt  = 0;
        $response = null;

        while ($attempt <= $maxRetries) {
            try {
                $response = $client->runReport($request);
                break; // success
            }

            catch (ApiException $e) {
                $code = $e->getCode();

                if (in_array($code, [13, 14, 4])) { // INTERNAL, UNAVAILABLE, DEADLINE_EXCEEDED
                    $attempt++;

                    if ($attempt > $maxRetries) {
                        error_log('GA4 API failed after retries: ' . $e->getMessage());
                        return $allRows; // graceful partial return
                    }

                    // exponential backoff
                    sleep(pow(2, $attempt));
                    continue;
                }

                // Non-retryable error
                error_log('GA4 API non-retryable error: ' . $e->getMessage());
                return $allRows;
            }
        }

        if (!$response) return $allRows;

        $rows = $response->getRows();
        $rowCount = count($rows);

        if (empty($rows)) break;

        foreach ($rows as $r) {
            $dims = [];
            foreach ($r->getDimensionValues() as $dv) $dims[] = $dv->getValue();
            $mets = [];
            foreach ($r->getMetricValues() as $mv) $mets[] = $mv->getValue();
            $allRows[] = ['d' => $dims, 'm' => $mets];
        }
        unset($response, $rows);

        if ($rowCount < $pageSize) break;

        $offset += $pageSize;

        if ($offset > $maxOffset) break;

        usleep(250000);
    }

    return $allRows;
}

function bp_ga4_year_ranges(int $years): array {
    $todayMinus1 = strtotime('-1 day');
    $ranges      = [];
    for ($i = 0; $i < $years; $i++) {
        $ranges[] = [
            'start' => date('Y-m-d', strtotime('-' . ($i + 1) . ' years', $todayMinus1)),
            'end'   => date('Y-m-d', strtotime("-{$i} years", $todayMinus1)),
        ];
    }
    return $ranges;
}

function bp_ga4_collect_daily_totals(BetaAnalyticsDataClient $client, $propertyId, int $years) {
    $allDaily = [];
    foreach (bp_ga4_year_ranges($years) as $range) {
        $rows = bp_ga4_run_report_all_rows($client, [
            'property'        => 'properties/' . $propertyId,
            'dateRanges'      => [new DateRange(['start_date' => $range['start'], 'end_date' => $range['end']])],
            'dimensions'      => [new Dimension(['name'=>'date'])],
            'metrics'         => [
                new Metric(['name'=>'sessions']),
                new Metric(['name'=>'totalUsers']),
                new Metric(['name'=>'newUsers']),
                new Metric(['name'=>'engagedSessions']),
                new Metric(['name'=>'screenPageViews']),
                new Metric(['name'=>'userEngagementDuration']),
            ],
            'dimensionFilter' => bp_ga4_dimension_filter(),
        ]);
        foreach ($rows as $row) {
            $date = $row['d'][0];
            if (!isset($allDaily[$date])) {
                $allDaily[$date] = ['sessions'=>0,'users'=>0,'newUsers'=>0,'engagedSessions'=>0,'pageviews'=>0,'engagementDuration'=>0.0];
            }
            $allDaily[$date]['sessions']           += (int)$row['m'][0];
            $allDaily[$date]['users']              += (int)$row['m'][1];
            $allDaily[$date]['newUsers']           += (int)$row['m'][2];
            $allDaily[$date]['engagedSessions']    += (int)$row['m'][3];
            $allDaily[$date]['pageviews']          += (int)$row['m'][4];
            $allDaily[$date]['engagementDuration'] += (float)$row['m'][5];
        }
    }
    if (empty($allDaily)) return false;

    // Guard: a healthy pull returns years of days. If a transient GA4 issue collapses
    // the result to a fraction of the stored history, keep the good data (don't wipe
    // the dashboard). New sites (little/no stored history) are unaffected.
    $stored = get_option('bp_ga4_daily_clean');
    if (is_array($stored) && count($stored) >= 60 && count($allDaily) < count($stored) * 0.5) {
        error_log('GA4 daily collapsed: ' . count($allDaily) . ' days vs stored ' . count($stored) . ' — kept stored history.');
        return $stored;
    }

    krsort($allDaily);
    update_option('bp_ga4_daily_clean', $allDaily, false);
    return $allDaily;
}

/*
 * Per-channel MONTHLY history — the append-only time series that makes
 * "how is each source doing over time" graphable. Unlike the rolling-window
 * dimension widgets (bp_ga4_collect_simple_dimension), which store only "last
 * N days" totals and OVERWRITE them every run, this NEVER discards past months:
 * each run refreshes the months GA4 still returns and preserves everything
 * older. Backfills automatically on the first run — GA4 aggregate reports
 * return history well past the user-data retention window.
 *
 * Shape: bp_ga4_channel_history[YYYYMM][channel] = {
 *   sessions, users, newUsers, engagedSessions, pageviews, duration, conversions
 * }
 * channel = grouped from GA4 sessionSourceMedium via bp_ga4_channel_group()
 * (Organic Search, Paid Search, Paid Social, Organic Social, Direct, Referral,
 * Email, …). We deliberately do NOT use sessionDefaultChannelGroup — on some
 * properties its attribution collapses so it returns almost nothing (only
 * Direct/Unassigned, a fraction of real sessions). sessionSourceMedium is the
 * dimension the Referrers widget already uses successfully; grouping it ourselves
 * gives the paid-vs-organic split (the whole point) without depending on GA4's
 * channel attribution.
 */
/*
 * Group a GA4 "source / medium" string into a channel — our own paid/organic
 * split, independent of GA4's (unreliable) sessionDefaultChannelGroup. Returns
 * one of: Direct, Email, Paid Search, Paid Social, Paid Other, Organic Search,
 * Organic Social, Referral, Unassigned.
 */
function bp_ga4_channel_group(string $sourceMedium): string {

    $sm = strtolower(trim($sourceMedium));
    if ($sm === '' || $sm === '(not set)') return 'Unassigned';

    $parts  = array_map('trim', explode('/', $sm, 2));
    $source = $parts[0] ?? '';
    $medium = $parts[1] ?? '';

    if ($source === '(direct)') return 'Direct';

    // Google Business Profile / local — kept as its own channel (GA4's channel-group
    // buries this inside "Organic Search"). Tagged as "gbp" or a maps/business source.
    if ($source === 'gbp'
        || strpos($source, 'business.google') !== false
        || strpos($source, 'maps.google')     !== false
        || strpos($source, 'google.com/maps') !== false) return 'GBP';

    $isSocial = (bool) preg_match('~facebook|instagram|linkedin|lnkd\.in|t\.co|twitter|(^|[^a-z])x\.com|youtube|youtu\.be|pinterest|tiktok|reddit|nextdoor|(^|[^a-z])fb([^a-z]|$)|(^|[^a-z])ig([^a-z]|$)~', $source);
    $isSearch = (bool) preg_match('~google|bing|yahoo|duckduckgo|ecosia|baidu|yandex|ask\.com~', $source);
    $isPaid   = (bool) preg_match('~cpc|ppc|paid|cpm|display|banner|retargeting~', $medium)
                || strpos($source, 'doubleclick') !== false
                || strpos($source, 'googleads')   !== false;
    $isEmail  = (strpos($medium, 'email') !== false || strpos($medium, 'newsletter') !== false || $source === 'email');

    if ($isEmail)                            return 'Email';
    if ($isPaid && $isSocial)                return 'Paid Social';
    if ($isPaid && $isSearch)                return 'Paid Search';
    if ($isPaid)                             return 'Paid Other';
    if ($isSocial)                           return 'Organic Social';
    if ($medium === 'organic' || $isSearch)  return 'Organic Search';
    if ($medium === 'referral')              return 'Referral';
    return 'Referral';
}

// Week key = the Monday (YYYYMMDD) of the week that contains $ymd ("20260629").
function bp_ga4_week_key(string $ymd): string {
    $ts  = strtotime($ymd);
    $dow = (int) date('N', $ts); // 1=Mon .. 7=Sun
    return date('Ymd', strtotime('-' . ($dow - 1) . ' days', $ts));
}

/*
 * Per-channel history at TWO grains — monthly (bp_ga4_channel_history[YYYYMM]) and
 * weekly (bp_ga4_channel_history_weekly[YYYYMMDD-of-Monday]). Both are rolled up in
 * PHP from ONE [date × sessionSourceMedium] query per year-range, so the two grains
 * always agree. Metrics: sessions, users, newUsers, engagedSessions, pageviews,
 * duration (userEngagementDuration). No conversions — GA4 keyEvents counts events,
 * not visits, so it's meaningless here. Append-only + collapse-guarded per grain.
 */
function bp_ga4_collect_channel_history(BetaAnalyticsDataClient $client, $propertyId): bool {

    $years = bp_ga4_years_to_pull();

    $dims = [
        new Dimension(['name' => 'date']),
        new Dimension(['name' => 'sessionSourceMedium']),
    ];
    $coreMetrics = [
        new Metric(['name' => 'sessions']),
        new Metric(['name' => 'totalUsers']),
        new Metric(['name' => 'newUsers']),
        new Metric(['name' => 'engagedSessions']),
        new Metric(['name' => 'screenPageViews']),
        new Metric(['name' => 'userEngagementDuration']),
    ];

    $monthly     = [];
    $weekly      = [];
    $daily       = [];
    $dailyCutoff = date('Ymd', strtotime('-500 days')); // ~16 months: enough for last-90d YoY tile comparisons
    $meta        = ['ran_at' => time(), 'years' => $years, 'ranges' => [], 'core_rows' => 0];

    $blank = function () { return ['sessions'=>0,'users'=>0,'newUsers'=>0,'engagedSessions'=>0,'pageviews'=>0,'duration'=>0.0]; };
    $add   = function (&$store, $key, $ch, $m) use ($blank) {
        if (!isset($store[$key][$ch])) $store[$key][$ch] = $blank();
        $store[$key][$ch]['sessions']        += (int)$m[0];
        $store[$key][$ch]['users']           += (int)$m[1];
        $store[$key][$ch]['newUsers']        += (int)$m[2];
        $store[$key][$ch]['engagedSessions'] += (int)$m[3];
        $store[$key][$ch]['pageviews']       += (int)$m[4];
        $store[$key][$ch]['duration']        += (float)$m[5];
    };

    // One year-range at a time (a single multi-year range comes back nearly empty).
    foreach (bp_ga4_year_ranges($years) as $range) {

        $meta['ranges'][] = $range['start'] . '→' . $range['end'];

        $rows = bp_ga4_run_report_all_rows($client, [
            'property'        => 'properties/' . $propertyId,
            'dateRanges'      => [new DateRange(['start_date' => $range['start'], 'end_date' => $range['end']])],
            'dimensions'      => $dims,
            'metrics'         => $coreMetrics,
            'dimensionFilter' => bp_ga4_dimension_filter(),
        ]);
        $meta['core_rows'] += count($rows);

        foreach ($rows as $row) {
            $ymd = trim($row['d'][0]);          // "20260629"
            if (strlen($ymd) !== 8) continue;
            $ch = bp_ga4_channel_group($row['d'][1]);
            $add($monthly, substr($ymd, 0, 6),      $ch, $row['m']);
            $add($weekly,  bp_ga4_week_key($ymd),   $ch, $row['m']);
            if ($ymd >= $dailyCutoff) $add($daily, $ymd, $ch, $row['m']);
        }
    }

    $meta['months'] = count($monthly);
    $meta['weeks']  = count($weekly);
    $meta['days']   = count($daily);
    update_option('bp_ga4_channel_history_meta', $meta, false);

    if (empty($monthly)) return false;

    // Merge each grain, guarded against a collapsed pull wiping rich history.
    // $pruneDays > 0 caps the store to the most recent N days (used for the daily grain).
    $mergeGuarded = function ($optKey, $fresh, $minStored, $minFresh, $pruneDays = 0) {
        $store = get_option($optKey);
        if (!is_array($store)) $store = [];
        if (count($store) >= $minStored && count($fresh) < max($minFresh, count($store) * 0.3)) {
            error_log("GA4 channel {$optKey} collapsed: " . count($fresh) . ' vs stored ' . count($store) . ' — skipped.');
            return;
        }
        foreach ($fresh as $k => $chs) $store[$k] = $chs;
        krsort($store);
        if ($pruneDays > 0) {
            $cut = date('Ymd', strtotime("-{$pruneDays} days"));
            foreach (array_keys($store) as $k) if ((string) $k < $cut) unset($store[$k]);
        }
        update_option($optKey, $store, false);
    };

    $mergeGuarded('bp_ga4_channel_history',        $monthly, 6,  2);
    $mergeGuarded('bp_ga4_channel_history_weekly', $weekly,  26, 4);
    $mergeGuarded('bp_ga4_channel_history_daily',  $daily,   60, 20, 520);

    return true;
}

/*
 * TEMP diagnostic: isolate why the channel query returns almost no rows. Runs a
 * matrix of last-90-day reports and reports row counts + GA4 response metadata
 * (thresholding / sampling / empty-reason) so we can see whether it's the channel
 * dimension, the yearMonth dimension, or the geo filter. Remove once resolved.
 */
function bp_ga4_channel_diagnose(): array {
    $ga4 = bp_ga4_client();
    if (!$ga4) return ['error' => 'no GA4 client'];

    $client = $ga4['client'];
    $prop   = $ga4['property'];
    $start  = date('Y-m-d', strtotime('-90 days'));
    $end    = date('Y-m-d', strtotime('-1 day'));

    // Use the SAME helper the real collectors use (sets limit, paginates) so counts
    // are trustworthy and directly comparable to bp_ga4_daily_clean's 861 rows.
    $run = function (array $dimNames, bool $withFilter) use ($client, $prop, $start, $end): array {
        $req = [
            'property'   => 'properties/' . $prop,
            'dateRanges' => [new DateRange(['start_date' => $start, 'end_date' => $end])],
            'dimensions' => array_map(fn($n) => new Dimension(['name' => $n]), $dimNames),
            'metrics'    => [new Metric(['name' => 'sessions'])],
        ];
        if ($withFilter) $req['dimensionFilter'] = bp_ga4_dimension_filter();

        $rows = bp_ga4_run_report_all_rows($client, $req);

        $out = ['count' => count($rows), 'sample' => []];
        $i = 0;
        foreach ($rows as $r) {
            if ($i++ >= 8) break;
            $out['sample'][] = implode(' | ', $r['d']) . ' = ' . implode(',', $r['m']);
        }
        return $out;
    };

    // Stored daily data (bp_ga4_daily_clean) for comparison — if this shows rich
    // recent history while the LIVE queries above are near-empty, the property being
    // queried has changed / is wrong (stored data is stale from a prior property).
    $storedDaily   = get_option('bp_ga4_daily_clean');
    $storedSummary = ['days' => 0, 'span' => '', 'recent' => []];
    if (is_array($storedDaily) && $storedDaily) {
        krsort($storedDaily);
        $keys = array_keys($storedDaily);
        $storedSummary['days'] = count($keys);
        $storedSummary['span'] = end($keys) . ' .. ' . reset($keys);
        foreach (array_slice($storedDaily, 0, 12, true) as $d => $m) {
            $storedSummary['recent'][$d] = (int)($m['sessions'] ?? 0);
        }
    }

    return [
        'property_id'                   => (string) $prop,
        'range'                         => "$start -> $end",
        'stored_daily (bp_ga4_daily_clean)' => $storedSummary,
        'A date +filter (control)'      => $run(['date'], true),
        'B yearMonth+channel +filter'   => $run(['yearMonth', 'sessionDefaultChannelGroup'], true),
        'C yearMonth+channel NO filter' => $run(['yearMonth', 'sessionDefaultChannelGroup'], false),
        'D channel only +filter'        => $run(['sessionDefaultChannelGroup'], true),
        'E date+channel +filter'        => $run(['date', 'sessionDefaultChannelGroup'], true),
        'F channel only NO filter'      => $run(['sessionDefaultChannelGroup'], false),
        'G yearMonth+sourceMedium +flt' => $run(['yearMonth', 'sessionSourceMedium'], true),
        'H date+sourceMedium +filter'   => $run(['date', 'sessionSourceMedium'], true),
        'I sourceMedium ALONE +filter'  => $run(['sessionSourceMedium'], true),
        'J sourceMedium ALONE no flt'   => $run(['sessionSourceMedium'], false),
    ];
}

function bp_rollup_totals_from_daily(array $daily): array {
    $anchorTs = bp_ymd_to_ts(array_key_first($daily));
    $periods  = [
        'this_week'    => [1,  7],  'last_week'    => [8,  14],
        'this_month'   => [1, 30],  'last_month'   => [31, 60],
        'this_quarter' => [1, 90],  'last_quarter' => [91, 180],
    ];
    $rollups = [];
    foreach ($periods as $label => [$start, $end]) {
        $acc = ['sessions'=>0,'users'=>0,'newUsers'=>0,'engagedSessions'=>0,'pageviews'=>0,'engagementDuration'=>0.0];
        for ($i = $start; $i <= $end; $i++) {
            $key = date('Ymd', strtotime("-{$i} days", $anchorTs));
            if (!isset($daily[$key])) continue;
            foreach (['sessions','users','newUsers','engagedSessions','pageviews'] as $k) $acc[$k] += (int)$daily[$key][$k];
            $acc['engagementDuration'] += (float)$daily[$key]['engagementDuration'];
        }
        $s = $acc['sessions']; $u = $acc['users']; $e = $acc['engagedSessions'];
        $rollups[$label] = $acc + [
            'engagementRate'     => $s > 0 ? round(($e / $s) * 100, 2) : 0,
            'pagesPerSession'    => $s > 0 ? round($acc['pageviews'] / $s, 2) : 0,
            'avgSessionDuration' => $s > 0 ? round($acc['engagementDuration'] / $s, 2) : 0,
            'newUserPct'         => $u > 0 ? round(($acc['newUsers'] / $u) * 100, 2) : 0,
        ];
    }
    update_option('bp_ga4_rollups_clean', $rollups, false);
    return $rollups;
}

function bp_ga4_collect_simple_dimension(BetaAnalyticsDataClient $client, $propertyId, string $dimensionName, int $days, string $optionKey, int $limit = 50): bool {
    $rows = bp_ga4_run_report_all_rows($client, [
        'property'        => 'properties/' . $propertyId,
        'dateRanges'      => [new DateRange(['start_date' => date('Y-m-d', strtotime("-{$days} days")), 'end_date' => date('Y-m-d', strtotime('-1 day'))])],
        'dimensions'      => [new Dimension(['name' => $dimensionName])],
        'metrics'         => [new Metric(['name' => 'engagedSessions'])],
        'orderBys'        => [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => 'engagedSessions']), 'desc' => true])],
        'dimensionFilter' => bp_ga4_dimension_filter(),
    ]);
    if (!$rows) return false;

    $existing     = get_option($optionKey);
    if (!is_array($existing)) $existing = [];
    $metricPrefix = ($optionKey === 'bp_ga4_pages_clean') ? 'page-views' : 'sessions';

    foreach ($rows as $row) {
        $dimVal = trim($row['d'][0]);
        if (!$dimVal || $dimVal === '(not set)') continue;
        if ($optionKey === 'bp_ga4_pages_clean') {
            $dimVal = trim(preg_replace('/\s+[•|]\s+[^•|]+$/', '', $dimVal));
        }
        if ($optionKey === 'bp_ga4_content_clean') {
            $dimVal = str_replace(' ', '-', $dimVal);
        }
        if (!$dimVal) continue;
        if (!isset($existing[$dimVal])) $existing[$dimVal] = [];
        $existing[$dimVal]["{$metricPrefix}-{$days}"] = (int)$row['m'][0];
    }

    // Merge any legacy space-keyed entries into their hyphenated equivalents
    if ($optionKey === 'bp_ga4_content_clean') {
        foreach ($existing as $key => $metrics) {
            $normalized = str_replace(' ', '-', $key);
            if ($normalized !== $key) {
                foreach ($metrics as $mk => $mv) {
                    $existing[$normalized][$mk] = ($existing[$normalized][$mk] ?? 0) + (int)$mv;
                }
                unset($existing[$key]);
            }
        }
    }

    update_option($optionKey, $existing, false);
    return true;
}

/**
 * Device × screen-width cross-tab. The ONLY reliable way to separate a small laptop from a
 * large tablet at the same pixel width: GA4 classifies the device (deviceCategory); width alone
 * can't. Stored as "device|WxH" => [engaged sessions per rolling window], rebuilt fresh each run
 * so resolutions that drop out don't linger. Feeds the per-device width donuts on the Analytics page.
 */
function bp_ga4_collect_device_width(BetaAnalyticsDataClient $client, $propertyId): bool {
    $fresh = [];
    foreach ([7, 30, 90, 180, 365] as $days) {
        $rows = bp_ga4_run_report_all_rows($client, [
            'property'        => 'properties/' . $propertyId,
            'dateRanges'      => [new DateRange(['start_date' => date('Y-m-d', strtotime("-{$days} days")), 'end_date' => date('Y-m-d', strtotime('-1 day'))])],
            'dimensions'      => [new Dimension(['name' => 'deviceCategory']), new Dimension(['name' => 'screenResolution'])],
            'metrics'         => [new Metric(['name' => 'engagedSessions'])],
            'dimensionFilter' => bp_ga4_dimension_filter(),
        ]);
        if (!$rows) continue;
        foreach ($rows as $row) {
            $device = strtolower(trim($row['d'][0]));
            $res    = trim($row['d'][1]);
            if (!$device || $device === '(not set)') continue;
            if (!preg_match('/^\d+x\d+$/', $res)) continue;   // skip "(not set)" / malformed
            $val = (int)$row['m'][0];
            if ($val <= 0) continue;
            $key = $device . '|' . $res;
            if (!isset($fresh[$key])) $fresh[$key] = [];
            $fresh[$key]["sessions-{$days}"] = $val;
        }
    }
    if (empty($fresh)) return false;
    update_option('bp_ga4_device_width_clean', $fresh, false);
    return true;
}

function bp_ga4_collect_speed_data(BetaAnalyticsDataClient $client, $propertyId) {
    $rows = bp_ga4_run_report_all_rows($client, [
        'property'        => 'properties/' . $propertyId,
        'dateRanges'      => [new DateRange(['start_date' => date('Y-m-d', strtotime('-365 days')), 'end_date' => date('Y-m-d', strtotime('-1 day'))])],
        'dimensions'      => [new Dimension(['name'=>'groupId']), new Dimension(['name'=>'date'])],
        'metrics'         => [new Metric(['name'=>'eventCount'])],
        'dimensionFilter' => bp_ga4_dimension_filter(),
    ]);

    $targets  = ['desktop'=>2.0,'mobile'=>3.0,'tablet'=>3.0];
    $periods  = [7, 30, 90, 180, 365];
    $cutoffs  = [];
    foreach ($periods as $p) $cutoffs[$p] = date('Ymd', strtotime("-{$p} days"));

    $byPeriod = [];
    foreach ($periods as $p) {
        $byPeriod[$p] = ['desktop'=>['total'=>0.0,'count'=>0,'fast'=>0],'mobile'=>['total'=>0.0,'count'=>0,'fast'=>0],'tablet'=>['total'=>0.0,'count'=>0,'fast'=>0]];
    }

    foreach ($rows as $row) {
        $groupId    = trim($row['d'][0]);
        $date       = $row['d'][1];
        $eventCount = (int)$row['m'][0];
        if (!$groupId || $groupId === '(not set)') continue;
        if (!preg_match('/»(desktop|mobile|tablet)«([\d.]+)$/i', $groupId, $m)) continue;
        $device = strtolower($m[1]);
        $speed  = (float)$m[2];
        if ($speed <= 0 || $speed > 30) continue;
        foreach ($periods as $p) {
            if ($date >= $cutoffs[$p]) {
                $byPeriod[$p][$device]['total'] += $speed * $eventCount;
                $byPeriod[$p][$device]['count'] += $eventCount;
                if ($speed <= ($targets[$device] ?? 3.0)) $byPeriod[$p][$device]['fast'] += $eventCount;
            }
        }
    }

    $existing = get_option('bp_ga4_speed_clean');
    if (!is_array($existing)) $existing = [];
    foreach ($periods as $p) {
        foreach ($byPeriod[$p] as $device => $data) {
            if ($data['count'] === 0) continue;
            if (!isset($existing[$device])) $existing[$device] = [];
            $existing[$device]["avg-{$p}"]    = round($data['total'] / $data['count'], 2);
            $existing[$device]["target-{$p}"] = round(($data['fast'] / $data['count']) * 100, 1);
            $existing[$device]["count-{$p}"]  = $data['count'];
            $existing[$device]['target']      = $targets[$device];
        }
    }
    update_option('bp_ga4_speed_clean', $existing, false);
    return $existing;
}

function bp_ga4_prune_clean_option(string $optionKey): void {
    $data = get_option($optionKey);
    if (!is_array($data)) return;
    foreach ($data as $dimVal => $metrics) {
        if (!is_array($metrics) || array_sum($metrics) === 0) unset($data[$dimVal]);
    }
    update_option($optionKey, $data, false);
}

function bp_ga4_build_tracked_elements(): void {

    $content = get_option('bp_ga4_content_clean');
    $daily   = get_option('bp_ga4_daily_clean');

    if (!is_array($content) || !is_array($daily)) return;

    $periods  = [7, 30, 90, 180, 365];
    $engaged  = [];

    foreach ($periods as $p) {
        $cutoff = date('Ymd', strtotime("-{$p} days"));
        $total  = 0;
        foreach ($daily as $date => $data) {
            if ($date >= $cutoff) $total += (int)($data['engagedSessions'] ?? 0);
        }
        $engaged[$p] = $total;
    }

    $result = [];

    foreach ($content as $key => $metrics) {
        if (!is_array($metrics) || strpos($key, 'track-') !== 0) continue;
        $element = substr($key, strlen('track-'));
        foreach ($periods as $p) {
            $sessions = (int)($metrics["sessions-{$p}"] ?? 0);
            if ($engaged[$p] > 0) {
                $result[$element]["sessions-{$p}"] = number_format(($sessions / $engaged[$p]) * 100, 1) . '%';
            }
        }
    }

    if (!empty($result)) update_site_option('bp_ga4_tracked_elements', $result);
}

function bp_ga4_collect_all_clean(BetaAnalyticsDataClient $client, $propertyId): bool {

    // 1) Daily totals — 6 years
    $dailyTotals = bp_ga4_collect_daily_totals($client, $propertyId, bp_ga4_years_to_pull());
    if (!$dailyTotals) return false;
    bp_rollup_totals_from_daily($dailyTotals);

    // 1b) Per-channel monthly history (append-only source time series)
    bp_ga4_collect_channel_history($client, $propertyId);

    // 2) Dimension widgets across 5 time periods
    $dimensions = [
        ['sessionSourceMedium', 'bp_ga4_referrers_clean',  40],
        ['city',                'bp_ga4_locations_clean',  75],
        ['browser',             'bp_ga4_browsers_clean',   20],
        ['deviceCategory',      'bp_ga4_devices_clean',    10],
        ['screenResolution',    'bp_ga4_resolution_clean', 40],
        ['pagePath',            'bp_ga4_pages_clean',      50],
        ['achievementId',       'bp_ga4_content_clean',    50],
    ];

    foreach ([7, 30, 90, 180, 365] as $days) {
        foreach ($dimensions as [$dimName, $optKey, $limit]) {
            bp_ga4_collect_simple_dimension($client, $propertyId, $dimName, $days, $optKey, $limit);
        }
    }

    // 2b) Device × width cross-tab (real device type per screen width)
    bp_ga4_collect_device_width($client, $propertyId);

    // 3) Speed data
    bp_ga4_collect_speed_data($client, $propertyId);

    // 4) Prune zero-value entries
    foreach (array_column($dimensions, 1) as $optKey) {
        bp_ga4_prune_clean_option($optKey);
    }

    bp_ga4_build_tracked_elements();

    update_option('bp_ga4_last_collect_ts', time(), false);

    // 5) Customer Check-In Emails
    require_once get_template_directory() . '/functions-customer-checkins.php';
    bp_run_customer_checkins();

    // 6) GSC Top Queries
    bp_gsc_collect_top_queries();

    return true;
}