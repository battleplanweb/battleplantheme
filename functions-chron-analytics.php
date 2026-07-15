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

    // Microsoft Clarity runs independently of GA4 (own token, own API) — collect it either way.
    try {
        bp_clarity_collect_daily();
    } catch (\Throwable $e) {
        error_log('Clarity collect failed: ' . $e->getMessage());
    }

    $ga4 = bp_ga4_client();
    if (!$ga4) return;

    try {
        bp_ga4_collect_all_clean($ga4['client'], $ga4['property']);
    } catch (\Throwable $e) {
        error_log('GA4 collect failed: ' . $e->getMessage());
    }
}

/**
 * Microsoft Clarity — daily UX-metrics collector. The Data Export API only serves the last 1–3 days
 * (rolling, UTC) and caps at 10 calls/project/day, so — exactly like bp_ga4_channel_history — we poll
 * ONCE nightly (numOfDays=1, no dimension breakdown = site totals) and APPEND yesterday's row to the
 * bp_clarity_history[YYYYMMDD] time series we build ourselves. Token = per-project JWT stored in
 * customer_info['clarity-tags']['token'] (['clarity-tags']['id'] is the tracking ID — a different thing).
 *
 * NOTE: only Traffic's field names are documented; the click/error metrics are extracted best-effort
 * (first numeric value in the metric's row). The one-time raw-response log lets us lock the exact
 * field names — check the error log after the first run and tighten bp_clarity_metric_value() if off.
 */
function bp_clarity_collect_daily(): bool {
    $ci    = customer_info();
    $token = trim((string) ($ci['clarity-tags']['token'] ?? $ci['google-tags']['clarity-token'] ?? ''));
    if ($token === '') return false;

    $resp = wp_remote_get(
        'https://www.clarity.ms/export-data/api/v1/project-live-insights?numOfDays=1',
        ['timeout' => 20, 'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json']]
    );
    if (is_wp_error($resp)) { error_log('Clarity fetch: ' . $resp->get_error_message()); return false; }

    $code = (int) wp_remote_retrieve_response_code($resp);
    $body = (string) wp_remote_retrieve_body($resp);
    if ($code !== 200) { error_log("Clarity HTTP {$code}: {$body}"); return false; }   // 401/403/400/429

    $data = json_decode($body, true);
    if (!is_array($data)) return false;

    // One-time: dump the raw response so we can confirm the exact per-metric field names.
    if (!get_option('bp_clarity_raw_logged')) {
        error_log('Clarity raw response (confirm field names): ' . $body);
        update_option('bp_clarity_raw_logged', 1, false);
    }

    // Index by metricName; a no-dimension call returns one summary row per metric.
    $byMetric = [];
    foreach ($data as $block) {
        if (isset($block['metricName'], $block['information'][0]) && is_array($block['information'][0])) {
            $byMetric[(string) $block['metricName']] = $block['information'][0];
        }
    }
    if (!$byMetric) return false;

    $traffic = $byMetric['Traffic'] ?? [];
    $row = [
        'sessions'    => bp_clarity_metric_value($traffic, ['totalSessionCount']),
        'bots'        => bp_clarity_metric_value($traffic, ['totalBotSessionCount']),
        'users'       => bp_clarity_metric_value($traffic, ['distantUserCount']),
        'rage'        => bp_clarity_metric_value($byMetric['Rage Click Count']   ?? []),
        'dead'        => bp_clarity_metric_value($byMetric['Dead Click Count']   ?? []),
        'quickback'   => bp_clarity_metric_value($byMetric['Quickback Click']    ?? []),
        'scriptErr'   => bp_clarity_metric_value($byMetric['Script Error Count'] ?? []),
        'errClick'    => bp_clarity_metric_value($byMetric['Error Click Count']  ?? []),
        'excessScrl'  => bp_clarity_metric_value($byMetric['Excessive Scroll']   ?? []),
    ];

    $ymd  = gmdate('Ymd', strtotime('-1 day'));           // the window numOfDays=1 covers (UTC)
    $hist = get_option('bp_clarity_history');
    if (!is_array($hist)) $hist = [];
    $hist[$ymd] = $row;
    krsort($hist);
    $cut = gmdate('Ymd', strtotime('-520 days'));
    foreach (array_keys($hist) as $k) if ((string) $k < $cut) unset($hist[$k]);
    update_option('bp_clarity_history', $hist, false);

    return true;
}

/**
 * Pull a scalar from a Clarity metric's information row. Tries the given known keys first (Traffic is
 * the only metric with documented field names), then falls back to the first numeric value — good
 * enough for the single-count click/error metrics until we confirm their exact keys from the raw log.
 */
function bp_clarity_metric_value(array $row, array $keys = []): int {
    foreach ($keys as $k) if (isset($row[$k]) && is_numeric($row[$k])) return (int) round((float) $row[$k]);
    foreach ($row as $v) if (is_numeric($v)) return (int) round((float) $v);
    return 0;
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

/**
 * Daily REAL-USER speed history (append-only). Our front-end fires a join_group event per visit
 * tagged "pageID»device«seconds" (seconds = the later of load or LCP). This queries GA4 by
 * [date, groupId], parses device + speed, and stores a per-day per-device average load + % of
 * loads meeting target — the dense RUM time series the Site speed chart plots. Backfills over
 * GA4's event retention so history appears immediately, then accrues nightly.
 * Store: bp_ga4_speed_history[YYYYMMDD] = ['ml'=>mobile avg s, 'mt'=>mobile %target,
 *                                          'dl'=>desktop avg s, 'dt'=>desktop %target]. Tablet folds into mobile.
 */
function bp_ga4_collect_speed_history(BetaAnalyticsDataClient $client, $propertyId): bool {

    $years   = bp_ga4_years_to_pull();
    $targets = ['desktop' => 2.0, 'mobile' => 3.0, 'tablet' => 3.0];
    $cutoff  = date('Ymd', strtotime('-500 days'));

    $acc = [];  // $acc[$ymd][$device] = ['sum'=>float, 'cnt'=>int, 'fast'=>int]

    foreach (bp_ga4_year_ranges($years) as $range) {
        $rows = bp_ga4_run_report_all_rows($client, [
            'property'        => 'properties/' . $propertyId,
            'dateRanges'      => [new DateRange(['start_date' => $range['start'], 'end_date' => $range['end']])],
            'dimensions'      => [new Dimension(['name' => 'date']), new Dimension(['name' => 'groupId'])],
            'metrics'         => [new Metric(['name' => 'eventCount'])],
            'dimensionFilter' => bp_ga4_dimension_filter(),
        ]);
        foreach ($rows as $row) {
            $ymd = trim($row['d'][0]);
            if (strlen($ymd) !== 8 || $ymd < $cutoff) continue;
            if (!preg_match('/»(desktop|mobile|tablet)«([\d.]+)$/i', trim($row['d'][1]), $mm)) continue;
            $device = strtolower($mm[1]);
            $speed  = (float) $mm[2];
            if ($speed <= 0 || $speed > 30) continue;
            $cnt = (int) $row['m'][0];
            if ($cnt <= 0) continue;
            if (!isset($acc[$ymd][$device])) $acc[$ymd][$device] = ['sum' => 0.0, 'cnt' => 0, 'fast' => 0];
            $acc[$ymd][$device]['sum'] += $speed * $cnt;
            $acc[$ymd][$device]['cnt'] += $cnt;
            if ($speed <= ($targets[$device] ?? 3.0)) $acc[$ymd][$device]['fast'] += $cnt;
        }
    }

    if (empty($acc)) return false;

    $daily = [];
    foreach ($acc as $ymd => $devs) {
        $mob = ['sum' => 0.0, 'cnt' => 0, 'fast' => 0];   // mobile + tablet = touch
        foreach (['mobile', 'tablet'] as $d) {
            if (isset($devs[$d])) { $mob['sum'] += $devs[$d]['sum']; $mob['cnt'] += $devs[$d]['cnt']; $mob['fast'] += $devs[$d]['fast']; }
        }
        $desk = $devs['desktop'] ?? ['sum' => 0.0, 'cnt' => 0, 'fast' => 0];
        $rec  = [];
        if ($mob['cnt']  > 0) { $rec['ml'] = round($mob['sum']  / $mob['cnt'],  2); $rec['mt'] = (int) round($mob['fast']  / $mob['cnt']  * 100); }
        if ($desk['cnt'] > 0) { $rec['dl'] = round($desk['sum'] / $desk['cnt'], 2); $rec['dt'] = (int) round($desk['fast'] / $desk['cnt'] * 100); }
        if ($rec) $daily[$ymd] = $rec;
    }

    if (empty($daily)) return false;

    // Merge-guarded append (mirror the channel-history daily merge): don't let a collapsed pull wipe history.
    $store = get_option('bp_ga4_speed_history');
    if (!is_array($store)) $store = [];
    if (count($store) >= 60 && count($daily) < max(20, count($store) * 0.3)) {
        error_log('GA4 speed history collapsed: ' . count($daily) . ' vs stored ' . count($store) . ' — skipped.');
        return false;
    }
    foreach ($daily as $k => $v) $store[$k] = $v;
    krsort($store);
    $cut = date('Ymd', strtotime('-520 days'));
    foreach (array_keys($store) as $k) if ((string) $k < $cut) unset($store[$k]);
    update_option('bp_ga4_speed_history', $store, false);

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

/**
 * City → state map. GA4's `city` dimension is just a name ("Allen"), which is ambiguous for both
 * labels and geocoding. Pull city × region (365d), keep the dominant region per city, store the
 * 2-letter abbreviation in bp_ga4_city_state_map. Feeds bp_append_state_to_city() (Locations widget
 * labels) AND disambiguates geocoding below.
 */
function bp_ga4_collect_city_regions(BetaAnalyticsDataClient $client, $propertyId): bool {
    $rows = bp_ga4_run_report_all_rows($client, [
        'property'        => 'properties/' . $propertyId,
        'dateRanges'      => [new DateRange(['start_date' => date('Y-m-d', strtotime('-365 days')), 'end_date' => date('Y-m-d', strtotime('-1 day'))])],
        'dimensions'      => [new Dimension(['name' => 'city']), new Dimension(['name' => 'region'])],
        'metrics'         => [new Metric(['name' => 'engagedSessions'])],
        'orderBys'        => [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => 'engagedSessions']), 'desc' => true])],
        'dimensionFilter' => bp_ga4_dimension_filter(),
    ]);
    if (!$rows) return false;

    static $abbr = [
        'Alabama'=>'AL','Alaska'=>'AK','Arizona'=>'AZ','Arkansas'=>'AR','California'=>'CA','Colorado'=>'CO',
        'Connecticut'=>'CT','Delaware'=>'DE','District of Columbia'=>'DC','Florida'=>'FL','Georgia'=>'GA',
        'Hawaii'=>'HI','Idaho'=>'ID','Illinois'=>'IL','Indiana'=>'IN','Iowa'=>'IA','Kansas'=>'KS','Kentucky'=>'KY',
        'Louisiana'=>'LA','Maine'=>'ME','Maryland'=>'MD','Massachusetts'=>'MA','Michigan'=>'MI','Minnesota'=>'MN',
        'Mississippi'=>'MS','Missouri'=>'MO','Montana'=>'MT','Nebraska'=>'NE','Nevada'=>'NV','New Hampshire'=>'NH',
        'New Jersey'=>'NJ','New Mexico'=>'NM','New York'=>'NY','North Carolina'=>'NC','North Dakota'=>'ND','Ohio'=>'OH',
        'Oklahoma'=>'OK','Oregon'=>'OR','Pennsylvania'=>'PA','Rhode Island'=>'RI','South Carolina'=>'SC',
        'South Dakota'=>'SD','Tennessee'=>'TN','Texas'=>'TX','Utah'=>'UT','Vermont'=>'VT','Virginia'=>'VA',
        'Washington'=>'WA','West Virginia'=>'WV','Wisconsin'=>'WI','Wyoming'=>'WY',
    ];

    $best = []; // city => [region, sessions]  (rows are desc, so first seen wins the tie)
    foreach ($rows as $row) {
        $city   = trim($row['d'][0]);
        $region = trim($row['d'][1]);
        $val    = (int)$row['m'][0];
        if (!$city || $city === '(not set)' || !$region || $region === '(not set)') continue;
        if (isset($best[$city]) && $best[$city][1] >= $val) continue;
        $best[$city] = [$region, $val];
    }
    if (!$best) return false;

    $map = [];
    foreach ($best as $city => [$region, $_]) {
        $map[$city] = $abbr[$region] ?? $region;
    }
    update_option('bp_ga4_city_state_map', $map, false);
    return true;
}

/**
 * Monthly city session history — the time series behind the animated Analytics map. Unlike the
 * rolling snapshots (bp_ga4_locations_clean), this is a real per-month breakdown so the map can play
 * through time. One report (yearMonth × city, engagedSessions) over the last N months; we keep the
 * top cities overall (by total) and their monthly values. Stored as [city => ['YYYYMM' => sessions]].
 */
function bp_ga4_collect_locations_history(BetaAnalyticsDataClient $client, $propertyId, int $months = 36): bool {
    $start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
    $rows  = bp_ga4_run_report_all_rows($client, [
        'property'        => 'properties/' . $propertyId,
        'dateRanges'      => [new DateRange(['start_date' => $start, 'end_date' => date('Y-m-d', strtotime('-1 day'))])],
        'dimensions'      => [new Dimension(['name' => 'yearMonth']), new Dimension(['name' => 'city'])],
        'metrics'         => [new Metric(['name' => 'engagedSessions'])],
        'dimensionFilter' => bp_ga4_dimension_filter(),
    ]);
    if (!$rows) return false;

    $byCity = []; $cityTotal = [];
    foreach ($rows as $row) {
        $ym   = trim($row['d'][0]);
        $city = trim($row['d'][1]);
        $v    = (int) $row['m'][0];
        if (!$city || $city === '(not set)' || !preg_match('/^\d{6}$/', $ym) || $v <= 0) continue;
        $byCity[$city][$ym]  = ($byCity[$city][$ym] ?? 0) + $v;
        $cityTotal[$city]    = ($cityTotal[$city] ?? 0) + $v;
    }
    if (!$cityTotal) return false;

    arsort($cityTotal);
    $top = array_slice(array_keys($cityTotal), 0, 60);   // bound the payload to the busiest cities
    $out = [];
    foreach ($top as $city) $out[$city] = $byCity[$city];

    update_option('bp_ga4_locations_history', $out, false);
    return true;
}

/**
 * Per-page pageview history at THREE grains (monthly / weekly / daily) — the time series behind the
 * Analytics "Page trends" chart, so it follows the Daily/Weekly/Monthly toggle like the traffic
 * graph. We first pick the top pages (from the bp_ga4_pages_clean snapshot), then pull one
 * [date × pagePath] report FILTERED to just those paths (keeps it bounded) and roll it up into the
 * three grains — mirroring bp_ga4_collect_channel_history. Stored as [periodKey][pagePath] => views;
 * daily capped to ~520 days. Path keys match bp_ga4_pages_clean so labels match the widget.
 */
function bp_ga4_collect_pages_history(BetaAnalyticsDataClient $client, $propertyId): bool {
    $clean = get_option('bp_ga4_pages_clean');
    if (!is_array($clean) || !$clean) return false;

    $rank = [];
    foreach ($clean as $path => $m) {
        if (!is_array($m)) continue;
        $rank[(string) $path] = (int) ($m['page-views-365'] ?? $m['page-views-90'] ?? $m['page-views-30'] ?? 0);
    }
    arsort($rank);
    $top = array_values(array_filter(array_slice(array_keys($rank), 0, 10), function ($p) { return $p !== ''; }));
    if (!$top) return false;

    // Existing US/exclude-cities filter AND pagePath IN (top pages).
    $filter = new FilterExpression([
        'and_group' => new FilterExpressionList(['expressions' => [
            bp_ga4_dimension_filter(),
            new FilterExpression(['filter' => new Filter([
                'field_name'     => 'pagePath',
                'in_list_filter' => new InListFilter(['values' => $top, 'case_sensitive' => false]),
            ])]),
        ]]),
    ]);

    $years   = bp_ga4_years_to_pull();
    $monthly = []; $weekly = []; $daily = [];
    $cutoff  = date('Ymd', strtotime('-500 days'));

    foreach (bp_ga4_year_ranges($years) as $range) {
        $rows = bp_ga4_run_report_all_rows($client, [
            'property'        => 'properties/' . $propertyId,
            'dateRanges'      => [new DateRange(['start_date' => $range['start'], 'end_date' => $range['end']])],
            'dimensions'      => [new Dimension(['name' => 'date']), new Dimension(['name' => 'pagePath'])],
            'metrics'         => [new Metric(['name' => 'screenPageViews'])],
            'dimensionFilter' => $filter,
        ]);
        foreach ($rows as $row) {
            $ymd = trim($row['d'][0]);
            if (strlen($ymd) !== 8) continue;
            $path = trim(preg_replace('/\s+[•|]\s+[^•|]+$/', '', $row['d'][1]));
            if ($path === '') continue;
            $v = (int) $row['m'][0];
            if ($v <= 0) continue;
            $mk = substr($ymd, 0, 6); $wk = bp_ga4_week_key($ymd);
            $monthly[$mk][$path] = ($monthly[$mk][$path] ?? 0) + $v;
            $weekly[$wk][$path]  = ($weekly[$wk][$path] ?? 0) + $v;
            if ($ymd >= $cutoff) $daily[$ymd][$path] = ($daily[$ymd][$path] ?? 0) + $v;
        }
    }
    if (!$monthly) return false;

    update_option('bp_ga4_pages_history',        $monthly, false);
    update_option('bp_ga4_pages_history_weekly', $weekly,  false);
    update_option('bp_ga4_pages_history_daily',  $daily,   false);
    return true;
}

/**
 * Geocode city names → lat/lng for the Analytics map. Reuses the site's existing Google geocoding
 * (_PLACES_API), restricted to the US. Results cache forever in bp_ga4_city_latlng
 * ([city => ['lat'=>..,'lng'=>..]], or ['lat'=>null] to remember a permanent miss). We geocode only
 * NEW cities, worst-case a handful per nightly run, capped so one run never blows the API budget.
 */
function bp_ga4_geocode_cities(): bool {
    if (!defined('_PLACES_API') || !_PLACES_API) return false;

    $cities = get_option('bp_ga4_locations_clean');
    if (!is_array($cities)) $cities = [];
    $history = get_option('bp_ga4_locations_history');   // animated-map cities need coords too
    if (!is_array($history)) $history = [];
    if (!$cities && !$history) return false;

    $cache = get_option('bp_ga4_city_latlng');
    if (!is_array($cache)) $cache = [];
    $states = get_option('bp_ga4_city_state_map');
    if (!is_array($states)) $states = [];

    // Prioritise by sessions so the busiest cities get coords first — snapshot totals, then any
    // history-only cities (their all-time total) so the timeline isn't missing bubbles.
    $rank = [];
    foreach ($cities as $city => $metrics) {
        if (!is_array($metrics)) continue;
        $rank[$city] = (int)($metrics['sessions-365'] ?? $metrics['sessions-90'] ?? $metrics['sessions-30'] ?? 0);
    }
    foreach ($history as $city => $series) {
        if (isset($rank[$city]) || !is_array($series)) continue;
        $rank[$city] = array_sum($series);
    }
    arsort($rank);

    $MAX_PER_RUN = 60;
    $done = 0;

    foreach (array_keys($rank) as $city) {
        if ($done >= $MAX_PER_RUN) break;
        if (array_key_exists($city, $cache)) continue;               // already resolved (hit or miss)

        $fail_key = 'bp_geo_city_fail_' . md5($city);
        if (get_transient($fail_key)) continue;                       // back off recent transient failures

        $query = $city . (isset($states[$city]) && $states[$city] ? ', ' . $states[$city] : '');
        $url   = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($query)
               . '&components=country:US&key=' . _PLACES_API;

        $response = wp_remote_get($url, ['timeout' => 10]);
        $done++;

        if (is_wp_error($response)) { set_transient($fail_key, true, HOUR_IN_SECONDS * 6); continue; }

        $data   = json_decode(wp_remote_retrieve_body($response), true);
        $status = is_array($data) ? ($data['status'] ?? '') : '';

        if ($status === 'OK' && isset($data['results'][0]['geometry']['location'])) {
            $loc = $data['results'][0]['geometry']['location'];
            $cache[$city] = ['lat' => round((float)$loc['lat'], 4), 'lng' => round((float)$loc['lng'], 4)];
        } elseif ($status === 'ZERO_RESULTS') {
            $cache[$city] = ['lat' => null];                          // remember the miss; don't retry every night
        } else {
            set_transient($fail_key, true, HOUR_IN_SECONDS * 6);      // OVER_QUERY_LIMIT / REQUEST_DENIED etc. — retry later
        }
    }

    update_option('bp_ga4_city_latlng', $cache, false);
    return true;
}

function bp_ga4_collect_all_clean(BetaAnalyticsDataClient $client, $propertyId): bool {

    // 1) Daily totals — 6 years
    $dailyTotals = bp_ga4_collect_daily_totals($client, $propertyId, bp_ga4_years_to_pull());
    if (!$dailyTotals) return false;
    bp_rollup_totals_from_daily($dailyTotals);

    // 1b) Per-channel monthly history (append-only source time series)
    bp_ga4_collect_channel_history($client, $propertyId);

    // 1c) Daily real-user speed history (append-only) — dense RUM load & %-target time series.
    bp_ga4_collect_speed_history($client, $propertyId);

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

    // 4b) City → state map (nicer labels), monthly city history (map), geocoded coords, page history
    bp_ga4_collect_city_regions($client, $propertyId);
    bp_ga4_collect_locations_history($client, $propertyId);
    bp_ga4_geocode_cities();
    bp_ga4_collect_pages_history($client, $propertyId);

    bp_ga4_build_tracked_elements();

    update_option('bp_ga4_last_collect_ts', time(), false);

    // 5) Customer Check-In Emails
    require_once get_template_directory() . '/functions-customer-checkins.php';
    bp_run_customer_checkins();

    // 6) GSC Top Queries
    bp_gsc_collect_top_queries();

    return true;
}