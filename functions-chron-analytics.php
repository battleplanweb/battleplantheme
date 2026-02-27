<?php
/* Battle Plan Web Design: Chron C — Analytics */

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

    $customer_info = customer_info();
    $ga4_id        = $customer_info['google-tags']['prop-id'] ?? null;

    try {
        if (!defined('GA4_SERVICE_ACCOUNT_JSON')) throw new \Exception('GA4 credentials missing');
        $credentials = json_decode(base64_decode(GA4_SERVICE_ACCOUNT_JSON), true);
        if (!is_array($credentials)) throw new \Exception('GA4 credentials invalid');
        $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
    } catch (\Throwable $e) {
        ('GA4 client init failed: ' . $e->getMessage());
        return;
    }

    bp_ga4_collect_all_clean($client, $ga4_id);
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
    while (true) {
        $request['limit']  = $pageSize;
        $request['offset'] = $offset;
        $response = $client->runReport($request);
        $rows     = $response->getRows();
        if (empty($rows)) break;
        foreach ($rows as $r) $allRows[] = $r;
        if (count($rows) < $pageSize) break;
        $offset += $pageSize;
        if ($offset > 2000000) break;
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

function bp_ga4_collect_daily_totals(BetaAnalyticsDataClient $client, $propertyId, int $years): array|false {
    $allDaily = [];
    foreach (bp_ga4_year_ranges($years) as $range) {
        $rows = bp_ga4_run_report_all_rows($client, [
            'property'        => 'properties/' . $propertyId,
            'dateRanges'      => [new DateRange(['start_date' => $range['start'], 'end_date' => $range['end']])],
            'dimensions'      => [new Dimension(['name'=>'date']), new Dimension(['name'=>'city']), new Dimension(['name'=>'country'])],
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
            $date = $row->getDimensionValues()[0]->getValue();
            if (!isset($allDaily[$date])) {
                $allDaily[$date] = ['sessions'=>0,'users'=>0,'newUsers'=>0,'engagedSessions'=>0,'pageviews'=>0,'engagementDuration'=>0.0];
            }
            $allDaily[$date]['sessions']           += (int)$row->getMetricValues()[0]->getValue();
            $allDaily[$date]['users']              += (int)$row->getMetricValues()[1]->getValue();
            $allDaily[$date]['newUsers']           += (int)$row->getMetricValues()[2]->getValue();
            $allDaily[$date]['engagedSessions']    += (int)$row->getMetricValues()[3]->getValue();
            $allDaily[$date]['pageviews']          += (int)$row->getMetricValues()[4]->getValue();
            $allDaily[$date]['engagementDuration'] += (float)$row->getMetricValues()[5]->getValue();
        }
    }
    if (empty($allDaily)) return false;
    krsort($allDaily);
    update_option('bp_ga4_daily_clean', $allDaily, false);
    return $allDaily;
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
        $dimVal = trim($row->getDimensionValues()[0]->getValue());
        if (!$dimVal || $dimVal === '(not set)') continue;
        if ($optionKey === 'bp_ga4_pages_clean') {
            $dimVal = trim(preg_replace('/\s+[•|]\s+[^•|]+$/', '', $dimVal));
        }
        if (!$dimVal) continue;
        if (!isset($existing[$dimVal])) $existing[$dimVal] = [];
        $existing[$dimVal]["{$metricPrefix}-{$days}"] = (int)$row->getMetricValues()[0]->getValue();
    }

    update_option($optionKey, $existing, false);
    return true;
}

function bp_ga4_collect_speed_data(BetaAnalyticsDataClient $client, $propertyId): array|false {
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
        $groupId    = trim($row->getDimensionValues()[0]->getValue());
        $date       = $row->getDimensionValues()[1]->getValue();
        $eventCount = (int)$row->getMetricValues()[0]->getValue();
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