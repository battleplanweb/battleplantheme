<?php
/* Battle Plan Web Design: Site Audit */

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Main Runner
# GA4 Stats
# Google Business Profile Performance
# Google Page Speed
# Google Search Console
# Content Freshness
# Helpers
--------------------------------------------------------------*/


/*--------------------------------------------------------------
>>> AUDIT DATA REFERENCE
----------------------------------------------------------------
get_site_option('bp_site_audit')

['ga4']['sessions']['30']               // sessions last 30 days
['ga4']['sessions_ly']['30']            // same period last year
['ga4']['top_pages']                    // slug => views top 10
['ga4']['top_locations']                // city => sessions top 25
['gbp']['total_reviews']
['gbp']['avg_rating']
['gbp_completeness'][$placeID]['score']
['gbp_completeness'][$placeID]['missing']
['pagespeed']['mobile']['performance']
['pagespeed_trend']['mobile_trend']['direction']
['gsc_trends']['top_queries']
['gsc_trends']['position_trend']
['backlinks']['citations']
['backlinks']['missing']
['schema']['score']
['schema']['has_local_business']
['google_ads'][30]['cost']
['freshness']['days_since_any']
['reviews']['total']
['reviews']['avg_rating']
--------------------------------------------------------------*/




// How often to run the site audit
// 0          = every chron run (testing)
// 2592000    = monthly (30 days)
// 7776000    = quarterly (90 days)
define('BP_SITE_AUDIT_INTERVAL', 0);

require_once get_template_directory() . '/functions-chron-helpers.php';


/*--------------------------------------------------------------
# Main Runner
--------------------------------------------------------------*/

function bp_run_site_audit() {

    if (BP_SITE_AUDIT_INTERVAL > 0) {
        $lastRun = (int)get_site_option('bp_site_audit_last_run', 0);
        if ((time() - $lastRun) < BP_SITE_AUDIT_INTERVAL) return;
    }

    update_site_option('bp_site_audit_last_run', time());

    $audit = get_site_option('bp_site_audit') ?: [];
    $audit['generated']        = date('Y-m-d H:i:s');
    $audit['site']             = get_bloginfo('url');

    $audit['ga4']              = bp_audit_ga4_stats();
    $audit['gbp']              = bp_audit_gbp_performance();
    $audit['pagespeed']        = bp_audit_pagespeed();
    $audit['pagespeed_trend']  = bp_audit_pagespeed_trend();
    $audit['gsc']              = bp_audit_search_console();
    $audit['gsc_trends']       = bp_audit_gsc_trends();
    $audit['backlinks']        = bp_audit_backlinks();
    $audit['google_ads']       = bp_audit_google_ads();
    $audit['freshness']        = bp_audit_content_freshness();
    $audit['reviews']          = bp_audit_reviews();

    update_site_option('bp_site_audit', $audit);

    error_log('bp_run_site_audit complete for ' . get_bloginfo('url'));

    // Save today's entry to bp_site_audit_details
    $today     = date('Y-m-d');
    $history   = get_option('bp_site_audit_details') ?: [];
    $ga4       = $audit['ga4']        ?? [];
    $pagespeed = $audit['pagespeed']  ?? [];
    $gsc       = $audit['gsc']        ?? [];
    $backlinks = $audit['backlinks']  ?? [];
    $gbp       = $audit['gbp']        ?? [];
    $gbpComplete = $audit['gbp_completeness'] ?? [];
    $googleAds = $audit['google_ads'] ?? [];
    $freshness = $audit['freshness']  ?? [];
    $speed     = get_option('bp_ga4_speed_clean') ?: [];
    $launchDate      = get_option('bp_launch_date');
    $launchTs        = $launchDate ? strtotime($launchDate) : null;
    $daysSinceLaunch = $launchTs ? (int)((time() - $launchTs) / 86400) : 9999;

    $entry = [];

    // PageSpeed
    if (!empty($pagespeed['mobile'])) {
        $m = $pagespeed['mobile'];
        $entry['lighthouse-mobile-score'] = $m['performance']   ?? '';
        $entry['lighthouse-mobile-fcp']   = $m['fcp']           ?? '';
        $entry['lighthouse-mobile-lcp']   = $m['lcp']           ?? '';
        $entry['lighthouse-mobile-tbt']   = $m['tbt']           ?? '';
        $entry['lighthouse-mobile-si']    = $m['speed_index']   ?? '';
        $entry['lighthouse-mobile-cls']   = $m['cls']           ?? '';
        $entry['lighthouse-mobile-acc']   = $m['accessibility'] ?? '';
        $entry['lighthouse-mobile-seo']   = $m['seo']           ?? '';
    }
    if (!empty($pagespeed['desktop'])) {
        $d = $pagespeed['desktop'];
        $entry['lighthouse-desktop-score'] = $d['performance']   ?? '';
        $entry['lighthouse-desktop-fcp']   = $d['fcp']           ?? '';
        $entry['lighthouse-desktop-lcp']   = $d['lcp']           ?? '';
        $entry['lighthouse-desktop-tbt']   = $d['tbt']           ?? '';
        $entry['lighthouse-desktop-si']    = $d['speed_index']   ?? '';
        $entry['lighthouse-desktop-cls']   = $d['cls']           ?? '';
        $entry['lighthouse-desktop-acc']   = $d['accessibility'] ?? '';
        $entry['lighthouse-desktop-seo']   = $d['seo']           ?? '';
    }

    // Search Console
    $entry['console-impressions-30'] = number_format($gsc['totals']['30']['impressions']);
    $entry['console-clicks-30']      = number_format($gsc['totals']['30']['clicks']);
    $entry['console-ctr-30']         = $gsc['totals']['30']['ctr'] . '%';
    $entry['console-position-30']    = $gsc['totals']['30']['position'];

    $entry['console-impressions-90'] = $daysSinceLaunch >= 90 && !empty($gsc['totals']['90']) ? number_format($gsc['totals']['90']['impressions']) : 'n/a';
    $entry['console-clicks-90']      = $daysSinceLaunch >= 90 && !empty($gsc['totals']['90']) ? number_format($gsc['totals']['90']['clicks'])      : 'n/a';
    $entry['console-ctr-90']         = $daysSinceLaunch >= 90 && !empty($gsc['totals']['90']) ? $gsc['totals']['90']['ctr'] . '%'                  : 'n/a';
    $entry['console-position-90']    = $daysSinceLaunch >= 90 && !empty($gsc['totals']['90']) ? $gsc['totals']['90']['position']                   : 'n/a';

    $entry['console-impressions-180'] = $daysSinceLaunch >= 180 && !empty($gsc['totals']['180']) ? number_format($gsc['totals']['180']['impressions']) : 'n/a';
    $entry['console-clicks-180']      = $daysSinceLaunch >= 180 && !empty($gsc['totals']['180']) ? number_format($gsc['totals']['180']['clicks'])      : 'n/a';
    $entry['console-ctr-180']         = $daysSinceLaunch >= 180 && !empty($gsc['totals']['180']) ? $gsc['totals']['180']['ctr'] . '%'                  : 'n/a';
    $entry['console-position-180']    = $daysSinceLaunch >= 180 && !empty($gsc['totals']['180']) ? $gsc['totals']['180']['position']                   : 'n/a';

    $entry['console-impressions-365'] = $daysSinceLaunch >= 365 && !empty($gsc['totals']['365']) ? number_format($gsc['totals']['365']['impressions']) : 'n/a';
    $entry['console-clicks-365']      = $daysSinceLaunch >= 365 && !empty($gsc['totals']['365']) ? number_format($gsc['totals']['365']['clicks'])      : 'n/a';
    $entry['console-ctr-365']         = $daysSinceLaunch >= 365 && !empty($gsc['totals']['365']) ? $gsc['totals']['365']['ctr'] . '%'                  : 'n/a';
    $entry['console-position-365']    = $daysSinceLaunch >= 365 && !empty($gsc['totals']['365']) ? $gsc['totals']['365']['position']                   : 'n/a';

    // Backlinks
    if (!empty($backlinks['linking_sites'])) {
        $entry['back-total-links'] = number_format(array_sum(array_column($backlinks['linking_sites'], 'count')));
        $entry['back-domains']     = number_format(count($backlinks['linking_sites']));
    }
    if (!empty($backlinks['citations'])) {
        $entry['cite-citations']      = count($backlinks['linking_sites']);
        $entry['cite-key-citations']  = count($backlinks['citations']);
        $entry['cite-citation-score'] = round(
            (count($backlinks['citations']) / max(1, count($backlinks['citations']) + count($backlinks['missing']))) * 100
        ) . '%';
    }

    // GBP
    if (!empty($gbp)) {
        $firstPlaceID = array_key_first($gbp['locations'] ?? []);

        // 90-day performance
        $perf90 = $firstPlaceID ? ($gbp['locations'][$firstPlaceID]['performance']['90'] ?? []) : [];
        if (!empty($perf90)) {
            $entry['gmb-impressions-90']     = number_format($perf90['impressions_total'] ?? 0);
            $entry['gmb-calls-90']           = number_format($perf90['call_clicks']       ?? 0);
            $entry['gmb-website-clicks-90']  = number_format($perf90['website_clicks']    ?? 0);
        }

        // 180-day performance
        $perf180 = $firstPlaceID ? ($gbp['locations'][$firstPlaceID]['performance']['180'] ?? []) : [];
        if (!empty($perf180)) {
            $entry['gmb-impressions-180']    = number_format($perf180['impressions_total'] ?? 0);
            $entry['gmb-calls-180']          = number_format($perf180['call_clicks']       ?? 0);
            $entry['gmb-website-clicks-180'] = number_format($perf180['website_clicks']    ?? 0);
        }

        $entry['google-reviews'] = number_format($gbp['total_reviews'] ?? '');
        $entry['google-rating']  = $gbp['avg_rating'] ?? '';
    }

    // Speed
    if (!empty($speed)) {
        $entry['load_time_mobile']     = $speed['mobile']['avg-30']   ?? '';
        $entry['load_time_desktop']    = $speed['desktop']['avg-30']  ?? '';
        $entry['speed-mobile-target']  = ($speed['mobile']['target-30']  ?? '') . '%';
        $entry['speed-desktop-target'] = ($speed['desktop']['target-30'] ?? '') . '%';
    }

    // GA4
    if (!empty($ga4['sessions'])) {
        if ($daysSinceLaunch >= 7)   $entry['ga4-sessions-7']   = number_format($ga4['sessions']['7']   ?? 'n/a');
        if ($daysSinceLaunch >= 30)  $entry['ga4-sessions-30']  = number_format($ga4['sessions']['30']  ?? 'n/a');
        if ($daysSinceLaunch >= 90)  $entry['ga4-sessions-90']  = number_format($ga4['sessions']['90']  ?? 'n/a');
        if ($daysSinceLaunch >= 180)  $entry['ga4-sessions-180']  = number_format($ga4['sessions']['180']  ?? 'n/a');
        if ($daysSinceLaunch >= 365) $entry['ga4-sessions-365'] = number_format($ga4['sessions']['365'] ?? 'n/a');
        if ($daysSinceLaunch >= 7)   $entry['ga4-engagement-7']   = ($ga4['engagement_rate']['7']   ?? 'n/a') . '%';
        if ($daysSinceLaunch >= 30)  $entry['ga4-engagement-30']  = ($ga4['engagement_rate']['30']  ?? 'n/a') . '%';
        if ($daysSinceLaunch >= 90)  $entry['ga4-engagement-90']  = ($ga4['engagement_rate']['90']  ?? 'n/a') . '%';
        if ($daysSinceLaunch >= 90)  $entry['ga4-engagement-180']  = ($ga4['engagement_rate']['180']  ?? 'n/a') . '%';
        if ($daysSinceLaunch >= 365) $entry['ga4-engagement-365'] = ($ga4['engagement_rate']['365'] ?? 'n/a') . '%';
    }
    if (!empty($ga4['pageviews'])) {
        if ($daysSinceLaunch >= 7)   $entry['ga4-pageviews-7']   = number_format($ga4['pageviews']['7']  ?? 0);
        if ($daysSinceLaunch >= 30)  $entry['ga4-pageviews-30']  = number_format($ga4['pageviews']['30'] ?? 0);
        if ($daysSinceLaunch >= 90)  $entry['ga4-pageviews-90']  = number_format($ga4['pageviews']['90'] ?? 0);
        if ($daysSinceLaunch >= 180)  $entry['ga4-pageviews-180']  = number_format($ga4['pageviews']['180'] ?? 0);
        if ($daysSinceLaunch >= 365) $entry['ga4-pageviews-365'] = number_format($ga4['pageviews']['365'] ?? 0);
    }
    if (!empty($ga4['conversions'])) {
        if ($daysSinceLaunch >= 30) {
            $entry['ga4-phone-30'] = $ga4['conversions']['phone_30'] ?? '';
            $entry['ga4-email-30'] = $ga4['conversions']['email_30'] ?? '';
        }
    }

    // Google Ads
    if (!empty($googleAds[30])) {
        $entry['ads-spend-30']       = '$' . number_format($googleAds[30]['cost'], 2);
        $entry['ads-clicks-30']      = number_format($googleAds[30]['clicks']);
        $entry['ads-conversions-30'] = number_format($googleAds[30]['conversions'], 1);
        $entry['ads-cpa-30']         = '$' . number_format($googleAds[30]['cost_per_conversion'], 2);
    }

    // Content freshness
    if (!empty($freshness)) {
        $entry['content-freshness'] = ($freshness['days_since_any'] ?? 0) . ' days';
    }

    // Post type counts
    foreach ([
        'blog'         => 'post',
        'landing'      => 'landing',
        'testimonials' => 'testimonials',
        'galleries'    => 'galleries',
        'jobsites'     => 'jobsite_geo',
    ] as $key => $type) {
        $count = wp_count_posts($type)->publish ?? 0;
        $entry[$key] = $count > 0 ? $count : '—';
    }

    $tracked = get_site_option('bp_ga4_tracked_elements') ?: [];

    if (!empty($tracked['testimonials']['sessions_30'])) $entry['testimonials-pct-30'] = $tracked['testimonials']['pct_30'] . '%';
    if (!empty($tracked['coupon']['sessions_30']))        $entry['coupon-pct-30']       = $tracked['coupon']['pct_30'] . '%';
    if (!empty($tracked['financing']['sessions_30']))     $entry['finance-pct-30']      = $tracked['financing']['pct_30'] . '%';
    if (!empty($tracked['testimonials']['sessions_90'])) $entry['testimonials-pct-90'] = $tracked['testimonials']['pct_90'] . '%';
    if (!empty($tracked['coupon']['sessions_90']))        $entry['coupon-pct-90']       = $tracked['coupon']['pct_90'] . '%';
    if (!empty($tracked['financing']['sessions_90']))     $entry['finance-pct-90']      = $tracked['financing']['pct_90'] . '%';


    // Write to history — overwrite if same date, otherwise append
    $existing = $history[$today] ?? [];
    $history[$today] = array_merge($existing, $entry);
    update_option('bp_site_audit_details', $history, false);

    error_log('bp_run_site_audit: saved entry for ' . $today);

    return $audit;
}


/*--------------------------------------------------------------
# GA4 Stats
--------------------------------------------------------------*/

function bp_audit_ga4_stats() {

    $daily = get_option('bp_ga4_daily_clean') ?: [];
    $result  = [];

    if (!$daily || !is_array($daily)) return $result;

    $dates    = array_keys($daily);
    $anchorTs = bp_ymd_to_ts($dates[0]);

    $sumMetric = function(int $start, int $end, string $metric) use ($daily, $anchorTs): int {
        $total = 0;
        for ($i = $start; $i <= $end; $i++) {
            $key = date('Ymd', strtotime("-{$i} days", $anchorTs));
            $total += (int)($daily[$key][$metric] ?? 0);
        }
        return $total;
    };

    // Sessions by period
    $result['sessions'] = [
        '7'   => $sumMetric(0, 6,   'sessions'),
        '30'  => $sumMetric(0, 29,  'sessions'),
        '90'  => $sumMetric(0, 89,  'sessions'),
        '180' => $sumMetric(0, 179, 'sessions'),
        '365' => $sumMetric(0, 364, 'sessions'),
    ];

    // Same periods last year
    $result['sessions_ly'] = [
        '7'   => $sumMetric(365, 371, 'sessions'),
        '30'  => $sumMetric(365, 394, 'sessions'),
        '90'  => $sumMetric(365, 454, 'sessions'),
        '180' => $sumMetric(365, 544, 'sessions'),
        '365' => $sumMetric(365, 729, 'sessions'),
    ];

    // Engaged sessions
    $result['engaged'] = [
        '7'   => $sumMetric(0, 6,   'engagedSessions'),
        '30'  => $sumMetric(0, 29,  'engagedSessions'),
        '90'  => $sumMetric(0, 89,  'engagedSessions'),
        '180' => $sumMetric(0, 179, 'engagedSessions'),
        '365' => $sumMetric(0, 364, 'engagedSessions'),
    ];

    // Engagement rates
    foreach (['7', '30', '180', '90', '365'] as $p) {
        $s = $result['sessions'][$p];
        $e = $result['engaged'][$p];
        $result['engagement_rate'][$p] = $s > 0 ? round(($e / $s) * 100, 1) : 0;
    }

    // Prior year same period sessions for each period
    $result['sessions_prior_quarter'] = $sumMetric(90, 179, 'sessions');
    $result['sessions_prior_month']   = $sumMetric(30, 59,  'sessions');

    // Multi-year annual totals
    $result['annual'] = [];
    for ($y = 0; $y < 6; $y++) {
        $start = $y * 365;
        $end   = ($y + 1) * 365 - 1;
        $total = $sumMetric($start, $end, 'sessions');
        if ($total > 0) {
            $result['annual'][] = [
                'year'     => date('Y', strtotime("-{$y} years")),
                'sessions' => $total,
            ];
        }
    }

    // This month historically
    $currentMonth     = (int)date('n');
    $result['this_month_history'] = [];

    for ($y = 0; $y < 6; $y++) {
        $yearStart   = strtotime("-{$y} years", $anchorTs);
        $monthStart  = mktime(0, 0, 0, $currentMonth, 1, (int)date('Y', $yearStart));
        $daysInMonth = (int)date('t', $monthStart);
        $startOffset = (int)(($anchorTs - $monthStart) / 86400);
        $endOffset   = $startOffset + $daysInMonth - 1;

        if ($startOffset < 0) continue;

        $total = $sumMetric($startOffset, $endOffset, 'sessions');
        if ($total > 0) {
            $result['this_month_history'][] = [
                'year'     => date('Y', $monthStart),
                'sessions' => $total,
            ];
        }
    }

    // Conversions
    $content_data = get_option('bp_ga4_content_clean');
    $result['conversions'] = [
    'phone_30' => (int)($content_data['conversion-phone-call']['sessions-30']
                ?? $content_data['conversion-phone call']['sessions-30']
                ?? 0),
    'phone_7'  => (int)($content_data['conversion-phone-call']['sessions-7']
                ?? $content_data['conversion-phone call']['sessions-7']
                ?? 0),
    'email_30' => (int)($content_data['conversion-email']['sessions-30'] ?? 0),
    'email_7'  => (int)($content_data['conversion-email']['sessions-7']  ?? 0),
];

    // Top pages
    $pages_data = get_option('bp_ga4_pages_clean');
    $result['top_pages'] = [];
    if ($pages_data && is_array($pages_data)) {
        $flat = [];
        foreach ($pages_data as $slug => $metrics) {
            if (!is_array($metrics)) continue;
            if ($slug === '/' || $slug === '') continue;
            $v = (int)($metrics['page-views-30'] ?? 0);
            if ($v > 0) $flat[$slug] = $v;
        }
        arsort($flat);
        $result['top_pages'] = array_slice($flat, 0, 10, true);
    }

    // Top towns
    $locations = get_option('bp_ga4_locations_clean');
    $result['top_locations'] = [];
    if ($locations && is_array($locations)) {
        $flat = [];
        foreach ($locations as $city => $metrics) {
            if (!is_array($metrics)) continue;
            $v = (int)($metrics['sessions-30'] ?? 0);
            if ($v > 0) $flat[$city] = $v;
        }
        arsort($flat);
        $result['top_locations'] = array_slice($flat, 0, 25, true);
    }

    // Top referrers
    $referrers = get_option('bp_ga4_referrers_clean');
    $result['top_referrers'] = [];
    if ($referrers && is_array($referrers)) {
        $flat = [];
        foreach ($referrers as $ref => $metrics) {
            if (!is_array($metrics)) continue;
            $v = (int)($metrics['sessions-30'] ?? 0);
            if ($v > 0) $flat[$ref] = $v;
        }
        arsort($flat);
        $result['top_referrers'] = array_slice($flat, 0, 15, true);
    }

    // Pageviews — read from daily data
    $pageviews7  = 0;
    $pageviews30 = 0;
    $pageviews90 = 0;
    $pageviews180 = 0;
    $pageviews365 = 0;

    $cutoff7  = date('Ymd', strtotime('-7 days'));
    $cutoff30 = date('Ymd', strtotime('-30 days'));
    $cutoff90 = date('Ymd', strtotime('-90 days'));
    $cutoff180 = date('Ymd', strtotime('-180 days'));
    $cutoff365 = date('Ymd', strtotime('-365 days'));

    foreach ($daily as $date => $data) {
        $pv = (int)($data['pageviews'] ?? 0);
        if ($date >= $cutoff7)  $pageviews7  += $pv;
        if ($date >= $cutoff30) $pageviews30 += $pv;
        if ($date >= $cutoff90) $pageviews90 += $pv;
        if ($date >= $cutoff180) $pageviews180 += $pv;
        if ($date >= $cutoff365) $pageviews365 += $pv;
    }

    $result['pageviews'] = [
        '7'  => $pageviews7,
        '30' => $pageviews30,
        '90' => $pageviews90,
        '180' => $pageviews180,
        '365' => $pageviews365,
    ];

    return $result;
}


/*--------------------------------------------------------------
# Google Business Profile Performance
--------------------------------------------------------------*/

function bp_audit_gbp_performance() {

    $customer_info = customer_info();
    $placeIDs      = ci_normalize_pids($customer_info['pid'] ?? []);
    $google_info   = get_option('bp_gbp_update') ?: [];

    if (empty($placeIDs)) return [];

    $result = [
        'total_reviews' => 0,
        'avg_rating'    => 0.0,
        'locations'     => [],
    ];

    $weightedRating = 0.0;

    foreach ($placeIDs as $placeID) {
        $info    = $google_info[$placeID] ?? [];
        $reviews = (int)($info['google-reviews'] ?? 0);
        $rating  = (float)($info['google-rating']  ?? 0);

        $result['total_reviews'] += $reviews;
        $weightedRating          += $rating * $reviews;

        $result['locations'][$placeID] = [
            'name'    => $info['name']    ?? '',
            'reviews' => $reviews,
            'rating'  => $rating,
        ];
    }

    if ($result['total_reviews'] > 0) {
        $result['avg_rating'] = round($weightedRating / $result['total_reviews'], 1);
    }

    // GBP Performance API — calls, impressions, website clicks
    // Requires Business Profile Performance API enabled on same service account as GA4
    /*
    if (defined('GA4_SERVICE_ACCOUNT_JSON')) {
        try {
            $credentials = json_decode(base64_decode(GA4_SERVICE_ACCOUNT_JSON), true);
            $token       = bp_get_google_access_token($credentials, [
                'https://www.googleapis.com/auth/business.manage'
            ]);

            if ($token) {
                foreach ($placeIDs as $placeID) {

                    foreach ([90, 180] as $days) {

                        $startDate = date('Y-m-d', strtotime("-{$days} days"));
                        $endDate   = date('Y-m-d', strtotime('-1 day'));

                        [$sy, $sm, $sd] = explode('-', $startDate);
                        [$ey, $em, $ed] = explode('-', $endDate);

                        $params = http_build_query([
                            'dailyMetrics'               => [
                                'CALL_CLICKS',
                                'WEBSITE_CLICKS',
                                'BUSINESS_IMPRESSIONS_DESKTOP_MAPS',
                                'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH',
                                'BUSINESS_IMPRESSIONS_MOBILE_MAPS',
                                'BUSINESS_IMPRESSIONS_MOBILE_SEARCH',
                                'BUSINESS_DIRECTION_REQUESTS',
                            ],
                            'dailyRange.start_date.year'  => $sy,
                            'dailyRange.start_date.month' => $sm,
                            'dailyRange.start_date.day'   => $sd,
                            'dailyRange.end_date.year'    => $ey,
                            'dailyRange.end_date.month'   => $em,
                            'dailyRange.end_date.day'     => $ed,
                        ]);

                        $url = 'https://businessprofileperformance.googleapis.com/v1/locations/'
                             . rawurlencode($placeID)
                             . ':fetchMultiDailyMetricsTimeSeries?' . $params;

                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL            => $url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT        => 15,
                            CURLOPT_HTTPHEADER     => [
                                'Authorization: Bearer ' . $token,
                                'Accept: application/json',
                            ],
                        ]);
                        $response = curl_exec($ch);
                        $http     = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                        curl_close($ch);

                        if ($http === 200) {
                            $data = json_decode($response, true);
                            $result['locations'][$placeID]['performance'][$days] =
                                bp_parse_gbp_performance($data);
                        } else {
                            error_log("GBP Performance API error {$http} for {$placeID} ({$days}d): {$response}");
                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            error_log('bp_audit_gbp_performance failed: ' . $e->getMessage());
        }
    }
    */


    return $result;
}

function bp_parse_gbp_performance(array $data): array {

    $totals = [
        'call_clicks'          => 0,
        'website_clicks'       => 0,
        'direction_requests'   => 0,
        'impressions_maps'     => 0,
        'impressions_search'   => 0,
    ];

    $metricMap = [
        'CALL_CLICKS'                            => 'call_clicks',
        'WEBSITE_CLICKS'                         => 'website_clicks',
        'BUSINESS_DIRECTION_REQUESTS'            => 'direction_requests',
        'BUSINESS_IMPRESSIONS_DESKTOP_MAPS'      => 'impressions_maps',
        'BUSINESS_IMPRESSIONS_MOBILE_MAPS'       => 'impressions_maps',
        'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH'    => 'impressions_search',
        'BUSINESS_IMPRESSIONS_MOBILE_SEARCH'     => 'impressions_search',
    ];

    foreach ($data['multiDailyMetricTimeSeries'] ?? [] as $series) {

        $metricType = $series['dailyMetric'] ?? '';
        $key        = $metricMap[$metricType] ?? null;

        if (!$key) continue;

        foreach ($series['timeSeries']['datedValues'] ?? [] as $dv) {
            $totals[$key] += (int)($dv['value'] ?? 0);
        }
    }

    $totals['impressions_total'] = $totals['impressions_maps'] + $totals['impressions_search'];

    return $totals;
}


/*--------------------------------------------------------------
# Google Page Speed
--------------------------------------------------------------*/

function bp_audit_pagespeed() {

    $url    = get_bloginfo('url');
    $result = [];

    foreach (['mobile', 'desktop'] as $strategy) {

        $apiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
        . '?url='      . rawurlencode($url)
        . '&strategy=' . $strategy
        . '&category=performance'
        . '&category=accessibility'
        . '&category=seo'
        . '&category=best-practices'
        . '&key='      . _PLACES_API;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $response = curl_exec($ch);
        $http     = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $cerr     = curl_errno($ch);
        $cerrm    = curl_error($ch);
        curl_close($ch);

        if ($http !== 200) {
            error_log("PageSpeed API error {$http} for {$strategy}");
            continue;
        }

        $data = json_decode($response, true);
        $lhr  = $data['lighthouseResult'] ?? [];
        $cats = $lhr['categories']        ?? [];
        $aud  = $lhr['audits']            ?? [];

        $result[$strategy] = [
            'performance'   => isset($cats['performance']['score'])   ? round($cats['performance']['score']   * 100) : null,
            'accessibility' => isset($cats['accessibility']['score']) ? round($cats['accessibility']['score'] * 100) : null,
            'seo'           => isset($cats['seo']['score'])           ? round($cats['seo']['score']           * 100) : null,
            'best_practices'=> isset($cats['best-practices']['score'])? round($cats['best-practices']['score']* 100) : null,
            'lcp'           => $aud['largest-contentful-paint']['displayValue']    ?? null,
            'cls'           => $aud['cumulative-layout-shift']['displayValue']     ?? null,
            'tbt'           => $aud['total-blocking-time']['displayValue']         ?? null,
            'fcp'           => $aud['first-contentful-paint']['displayValue']      ?? null,
            'speed_index'   => $aud['speed-index']['displayValue']                 ?? null,
        ];
    }

    // Store history so we can trend scores over time
    $history = get_option('bp_pagespeed_history') ?: [];
    $history[date('Y-m-d')] = $result;

    /* Keep 90 days of history
    $cutoff = date('Y-m-d', strtotime('-90 days'));
    foreach ($history as $date => $scores) {
        if ($date < $cutoff) unset($history[$date]);
    }
     */

    update_option('bp_pagespeed_history', $history, false);

    return $result;
}


/*--------------------------------------------------------------
# Google Search Console
--------------------------------------------------------------*/

function bp_audit_search_console() {

    if (!defined('GA4_SERVICE_ACCOUNT_JSON')) return [];

    try {

        $credentials = json_decode(base64_decode(GA4_SERVICE_ACCOUNT_JSON), true);
        $token       = bp_get_google_access_token($credentials, [
            'https://www.googleapis.com/auth/webmasters.readonly'
        ]);

        if (!$token) return [];

    } catch (\Throwable $e) {
        error_log('GSC token failed: ' . $e->getMessage());
        return [];
    }

    //$siteUrl = get_bloginfo('url') . '/';

    $siteUrl = 'sc-domain:' . str_replace(['https://', 'http://'], '', get_bloginfo('url'));

    $result   = [];
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $periods = [
        '30'  => [date('Y-m-d', strtotime('-30 days')),  $yesterday],
        '90'  => [date('Y-m-d', strtotime('-90 days')),  $yesterday],
        '180' => [date('Y-m-d', strtotime('-180 days')), $yesterday],
        '365' => [date('Y-m-d', strtotime('-365 days')), $yesterday],
    ];

    foreach ($periods as $period => $dates) {

        $body = json_encode([
            'startDate'             => $dates[0],
            'endDate'               => $dates[1],
            'dimensions'            => [],
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

        if ($http === 200) {
            $data = json_decode($response, true);
            $rows = $data['rows'][0] ?? [];
            $result['totals'][$period] = [
                'clicks'      => (int)($rows['clicks']      ?? 0),
                'impressions' => (int)($rows['impressions'] ?? 0),
                'ctr'         => round((float)($rows['ctr'] ?? 0) * 100, 2),
                'position'    => round((float)($rows['position'] ?? 0), 1),
            ];
        } else {
            error_log("GSC error {$http} for period {$period}");
        }
    }

    // Top queries — also US only
    $body = json_encode([
        'startDate'             => date('Y-m-d', strtotime('-90 days')),
        'endDate'               => date('Y-m-d', strtotime('-1 day')),
        'dimensions'            => ['query'],
        'rowLimit'              => 25,
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
                                . rawurlencode($siteUrl)
                                . '/searchAnalytics/query',
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
    curl_close($ch);

    $data = json_decode($response, true);
    $result['top_queries'] = [];

    foreach ($data['rows'] ?? [] as $row) {
        $result['top_queries'][] = [
            'query'       => $row['keys'][0] ?? '',
            'clicks'      => (int)$row['clicks'],
            'impressions' => (int)$row['impressions'],
            'ctr'         => round($row['ctr'] * 100, 2),
            'position'    => round($row['position'], 1),
        ];
    }

    return $result;
}


/*--------------------------------------------------------------
# Content Freshness
--------------------------------------------------------------*/

function bp_audit_content_freshness() {

    $posts = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ]);

    if (!$posts) return [];

    $now      = time();
    $result   = [
        'newest_update'  => null,
        'days_since_any' => 999,
        'pages'          => [],
    ];

    foreach ($posts as $post) {

        $modifiedTs = strtotime($post->post_modified);
        $daysSince  = (int)(($now - $modifiedTs) / 86400);

        if ($result['newest_update'] === null) {
            $result['newest_update']  = $post->post_modified;
            $result['days_since_any'] = $daysSince;
        }

        $result['pages'][] = [
            'id'         => $post->ID,
            'title'      => $post->post_title,
            'url'        => get_permalink($post->ID),
            'days_since' => $daysSince,
            'modified'   => $post->post_modified,
        ];
    }

    return $result;
}


/*--------------------------------------------------------------
# Reviews Summary
--------------------------------------------------------------*/

function bp_audit_reviews() {

    $customer_info  = customer_info();
    $placeIDs       = ci_normalize_pids($customer_info['pid'] ?? []);
    $google_info    = get_option('bp_gbp_update') ?: [];

    $result = [
        'total'          => 0,
        'avg_rating'     => 0.0,
        'last_milestone' => (int)get_option('bp_reviews_last_milestone', 0),
        'last_week'      => (int)get_option('bp_reviews_count_last_week', 0),
        'last_month'     => (int)get_option('bp_reviews_count_last_month', 0),
    ];

    $weighted = 0.0;

    foreach ($placeIDs as $placeID) {
        $info     = $google_info[$placeID] ?? [];
        $reviews  = (int)($info['google-reviews'] ?? 0);
        $rating   = (float)($info['google-rating'] ?? 0);
        $result['total']  += $reviews;
        $weighted         += $rating * $reviews;
    }

    if ($result['total'] > 0) {
        $result['avg_rating']  = round($weighted / $result['total'], 1);
        $result['week_gain']   = $result['total'] - $result['last_week'];
        $result['month_gain']  = $result['total'] - $result['last_month'];
    }

    return $result;
}


/*--------------------------------------------------------------
# Google Ads
--------------------------------------------------------------*/

function bp_audit_google_ads() {

    if (!defined('GA4_SERVICE_ACCOUNT_JSON')) return [];

    // Google Ads requires a separate OAuth client (Manager Account)
    // and the Google Ads API — different from the service account used for GA4.
    // Requires: Google Ads API access approved, developer token, manager account ID.
    // Store these constants in wp-config.php:
    //   GOOGLE_ADS_DEVELOPER_TOKEN
    //   GOOGLE_ADS_CLIENT_ID
    //   GOOGLE_ADS_CLIENT_SECRET
    //   GOOGLE_ADS_REFRESH_TOKEN
    //   GOOGLE_ADS_CUSTOMER_ID  (the client's account, no dashes)
    //   GOOGLE_ADS_MANAGER_ID   (your manager account, no dashes)

    if (!defined('GOOGLE_ADS_DEVELOPER_TOKEN') ||
        !defined('GOOGLE_ADS_REFRESH_TOKEN')   ||
        !defined('GOOGLE_ADS_CUSTOMER_ID')) {
        return ['status' => 'not_configured'];
    }

    try {

        // Get OAuth access token via refresh token
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://oauth2.googleapis.com/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => GOOGLE_ADS_CLIENT_ID,
                'client_secret' => GOOGLE_ADS_CLIENT_SECRET,
                'refresh_token' => GOOGLE_ADS_REFRESH_TOKEN,
                'grant_type'    => 'refresh_token',
            ]),
        ]);
        $tokenResponse = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $accessToken = $tokenResponse['access_token'] ?? null;
        if (!$accessToken) {
            error_log('Google Ads: failed to get access token');
            return [];
        }

        // GAQL query — last 30 and 90 days
        $results = [];

        foreach ([30, 90] as $days) {

            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            $endDate   = date('Y-m-d', strtotime('-1 day'));

            $query = "SELECT
                campaign.name,
                metrics.impressions,
                metrics.clicks,
                metrics.cost_micros,
                metrics.conversions,
                metrics.ctr,
                metrics.average_cpc
              FROM campaign
              WHERE segments.date BETWEEN '{$startDate}' AND '{$endDate}'
              AND campaign.status = 'ENABLED'";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://googleads.googleapis.com/v16/customers/'
                                        . GOOGLE_ADS_CUSTOMER_ID
                                        . '/googleAds:search',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['query' => $query]),
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'developer-token: '      . GOOGLE_ADS_DEVELOPER_TOKEN,
                    'login-customer-id: '    . GOOGLE_ADS_MANAGER_ID,
                    'Content-Type: application/json',
                ],
            ]);
            $response = curl_exec($ch);
            $http     = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($http !== 200) {
                error_log("Google Ads API error {$http}: {$response}");
                continue;
            }

            $data       = json_decode($response, true);
            $totals     = [
                'impressions'  => 0,
                'clicks'       => 0,
                'cost'         => 0.0,
                'conversions'  => 0.0,
                'campaigns'    => [],
            ];

            foreach ($data['results'] ?? [] as $row) {
                $m = $row['metrics'];
                $totals['impressions'] += (int)($m['impressions']  ?? 0);
                $totals['clicks']      += (int)($m['clicks']       ?? 0);
                $totals['cost']        += (float)($m['costMicros'] ?? 0) / 1000000;
                $totals['conversions'] += (float)($m['conversions'] ?? 0);

                $totals['campaigns'][] = [
                    'name'        => $row['campaign']['name'] ?? '',
                    'impressions' => (int)($m['impressions']  ?? 0),
                    'clicks'      => (int)($m['clicks']       ?? 0),
                    'cost'        => round((float)($m['costMicros'] ?? 0) / 1000000, 2),
                    'conversions' => round((float)($m['conversions'] ?? 0), 1),
                    'ctr'         => round((float)($m['ctr'] ?? 0) * 100, 2),
                    'avg_cpc'     => round((float)($m['averageCpc'] ?? 0) / 1000000, 2),
                ];
            }

            $totals['cost']              = round($totals['cost'], 2);
            $totals['ctr']               = $totals['impressions'] > 0
                ? round(($totals['clicks'] / $totals['impressions']) * 100, 2)
                : 0;
            $totals['cost_per_conversion'] = $totals['conversions'] > 0
                ? round($totals['cost'] / $totals['conversions'], 2)
                : 0;

            $results[$days] = $totals;
        }

        return $results;

    } catch (\Throwable $e) {
        error_log('bp_audit_google_ads failed: ' . $e->getMessage());
        return [];
    }
}


/*--------------------------------------------------------------
# Backlinks + Citation Matching
--------------------------------------------------------------*/

function bp_audit_backlinks() {

    if (!defined('GA4_SERVICE_ACCOUNT_JSON')) return [];

    try {

        $credentials = json_decode(base64_decode(GA4_SERVICE_ACCOUNT_JSON), true);
        $token       = bp_get_google_access_token($credentials, [
            'https://www.googleapis.com/auth/webmasters.readonly'
        ]);

        if (!$token) return [];

    } catch (\Throwable $e) {
        error_log('Backlinks token failed: ' . $e->getMessage());
        return [];
    }

    $siteUrl = get_bloginfo('url') . '/';
    $result  = [
        'linking_sites'  => [],
        'linked_pages'   => [],
        'citations'      => [],
        'citation_count' => 0,
        'missing'        => [],
    ];

    $common_directories = [
        'yelp.com'               => 'Yelp',
        'yellowpages.com'        => 'Yellow Pages',
        'bbb.org'                => 'Better Business Bureau',
        'angi.com'               => 'Angi',
        'angieslist.com'         => 'Angi (Angie\'s List)',
        'thumbtack.com'          => 'Thumbtack',
        'homeadvisor.com'        => 'HomeAdvisor',
        'houzz.com'              => 'Houzz',
        'manta.com'              => 'Manta',
        'citysearch.com'         => 'CitySearch',
        'superpages.com'         => 'SuperPages',
        'mapquest.com'           => 'MapQuest',
        'foursquare.com'         => 'Foursquare',
        'chamberofcommerce.com'  => 'Chamber of Commerce',
        'expertise.com'          => 'Expertise',
        'bark.com'               => 'Bark',
        'buildzoom.com'          => 'BuildZoom',
        'porch.com'              => 'Porch',
        'networx.com'            => 'Networx',
        'facebook.com'           => 'Facebook',
        'nextdoor.com'           => 'Nextdoor',
        'google.com'             => 'Google',
        'hotfrog.com'            => 'Hotfrog',
        'merchantcircle.com'     => 'MerchantCircle',
        'kudzu.com'              => 'Kudzu',
        'showmelocal.com'        => 'ShowMeLocal',
        'local.com'              => 'Local.com',
        'brownbook.net'          => 'Brownbook',
        'ezlocal.com'            => 'EZlocal',
        'cylex.us.com'           => 'Cylex',
        'n49.com'                => 'N49',
        'opendi.us'              => 'Opendi',
        'tupalo.com'             => 'Tupalo',
        'getfave.com'            => 'GetFave',
        'spoken.ly'              => 'Spoken',
        'yasabe.com'             => 'Yasabe',
        'homeowners.com'         => 'Homeowners.com',
        'servicemagic.com'       => 'ServiceMagic',
        'contractors.com'        => 'Contractors.com',
        'buildingadvisor.com'    => 'Building Advisor',
        'improvenet.com'         => 'ImproveNet',
    ];

    // Top linking sites
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://www.googleapis.com/webmasters/v3/sites/'
                                . rawurlencode($siteUrl)
                                . '/links/topLinkers',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $response = curl_exec($ch);
    $http     = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($http === 200) {
        $data = json_decode($response, true);

        foreach ($data['topLinkers'] ?? [] as $linker) {
            $domain = $linker['domain'] ?? '';
            if (!$domain) continue;

            $result['linking_sites'][] = [
                'domain' => $domain,
                'count'  => (int)($linker['total'] ?? 0),
            ];

            // Check against citation directory list
            foreach ($common_directories as $dirDomain => $dirName) {
                if (str_contains($domain, $dirDomain)) {
                    $result['citations'][] = [
                        'domain' => $domain,
                        'name'   => $dirName,
                        'count'  => (int)($linker['total'] ?? 0),
                    ];
                    break;
                }
            }
        }

        $result['citation_count'] = count($result['citations']);

        // Which directories are NOT in the backlink list
        $foundDomains = array_column($result['citations'], 'domain');
        foreach ($common_directories as $dirDomain => $dirName) {
            $found = false;
            foreach ($foundDomains as $fd) {
                if (str_contains($fd, $dirDomain)) { $found = true; break; }
            }
            if (!$found) {
                $result['missing'][] = [
                    'domain' => $dirDomain,
                    'name'   => $dirName,
                ];
            }
        }
    } else {
        error_log("Backlinks API error {$http}: {$response}");
    }

    // Top linked pages
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://www.googleapis.com/webmasters/v3/sites/'
                                . rawurlencode($siteUrl)
                                . '/links/topLinkedPages',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $response = curl_exec($ch);
    $http     = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($http === 200) {
        $data = json_decode($response, true);
        foreach ($data['topLinkedPages'] ?? [] as $page) {
            $result['linked_pages'][] = [
                'page'  => $page['page']  ?? '',
                'count' => (int)($page['total'] ?? 0),
            ];
        }
    }

    return $result;
}


/*--------------------------------------------------------------
# PageSpeed Trend
--------------------------------------------------------------*/

function bp_audit_pagespeed_trend() {

    $history = get_option('bp_pagespeed_history') ?: [];

    if (count($history) < 2) return [];

    ksort($history); // oldest to newest

    $result = [
        'mobile'  => [],
        'desktop' => [],
    ];

    foreach ($history as $date => $scores) {
        foreach (['mobile', 'desktop'] as $strategy) {
            if (!empty($scores[$strategy]['performance'])) {
                $result[$strategy][] = [
                    'date'          => $date,
                    'performance'   => $scores[$strategy]['performance'],
                    'accessibility' => $scores[$strategy]['accessibility'] ?? null,
                    'seo'           => $scores[$strategy]['seo']           ?? null,
                ];
            }
        }
    }

    // Flag declining trend — compare last 7 days avg vs prior 7 days avg
    foreach (['mobile', 'desktop'] as $strategy) {

        $data = $result[$strategy];
        if (count($data) < 7) continue;

        $recent = array_slice($data, -7);
        $prior  = array_slice($data, -14, 7);

        if (empty($prior)) continue;

        $recentAvg = round(array_sum(array_column($recent, 'performance')) / count($recent), 1);
        $priorAvg  = round(array_sum(array_column($prior,  'performance')) / count($prior),  1);
        $change    = $recentAvg - $priorAvg;

        $result["{$strategy}_trend"] = [
            'recent_avg' => $recentAvg,
            'prior_avg'  => $priorAvg,
            'change'     => round($change, 1),
            'direction'  => $change >= 0 ? 'stable' : 'declining',
        ];
    }

    return $result;
}


/*--------------------------------------------------------------
# GSC Top Queries + Position Trend
--------------------------------------------------------------*/

function bp_audit_gsc_trends() {

    if (!defined('GA4_SERVICE_ACCOUNT_JSON')) return [];

    try {
        $credentials = json_decode(base64_decode(GA4_SERVICE_ACCOUNT_JSON), true);
        $token       = bp_get_google_access_token($credentials, [
            'https://www.googleapis.com/auth/webmasters.readonly'
        ]);
        if (!$token) return [];
    } catch (\Throwable $e) {
        error_log('GSC trends token failed: ' . $e->getMessage());
        return [];
    }

    $siteUrl = get_bloginfo('url') . '/';
    $result  = [
        'top_queries'      => [],
        'top_pages'        => [],
        'position_trend'   => [],
        'impression_trend' => [],
    ];

    // Top queries with full metrics
    $body = json_encode([
        'startDate'  => date('Y-m-d', strtotime('-90 days')),
        'endDate'    => date('Y-m-d', strtotime('-1 day')),
        'dimensions' => ['query'],
        'rowLimit'   => 25,
        'orderBy'    => [['fieldName' => 'clicks', 'sortOrder' => 'DESCENDING']],
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

    if ($http === 200) {
        $data = json_decode($response, true);
        foreach ($data['rows'] ?? [] as $row) {
            $result['top_queries'][] = [
                'query'       => $row['keys'][0] ?? '',
                'clicks'      => (int)$row['clicks'],
                'impressions' => (int)$row['impressions'],
                'ctr'         => round($row['ctr'] * 100, 2),
                'position'    => round($row['position'], 1),
            ];
        }
    }

    // Monthly position trend — compare last 3 months
    $months = [];
    for ($m = 2; $m >= 0; $m--) {
        $months[] = [
            'start' => date('Y-m-d', strtotime("first day of -{$m} month")),
            'end'   => date('Y-m-d', strtotime("last day of -{$m} month")),
            'label' => date('M Y',   strtotime("-{$m} month")),
        ];
    }

    foreach ($months as $month) {

        $body = json_encode([
            'startDate'  => $month['start'],
            'endDate'    => $month['end'],
            'dimensions' => [],
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
        curl_close($ch);

        $data = json_decode($response, true);
        $rows = $data['rows'][0] ?? [];

        $result['position_trend'][] = [
            'month'       => $month['label'],
            'clicks'      => (int)($rows['clicks']      ?? 0),
            'impressions' => (int)($rows['impressions']  ?? 0),
            'ctr'         => round((float)($rows['ctr'] ?? 0) * 100, 2),
            'position'    => round((float)($rows['position'] ?? 0), 1),
        ];
    }

    return $result;
}