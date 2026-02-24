<?php
/* Battle Plan Web Design: Customer Email Reports */

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Main Runner
# Content Freshness Check
# Stats Analysis
# Email Builders
# Helpers
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Email Target ... switch to false when ready to launch
--------------------------------------------------------------*/
define('BP_EMAIL_TEST_MODE', 'gguttenfelder@gmail.com'); // set to false when ready to automate
define('BP_EMAIL_LIMIT_HIGHLIGHTS', false); // set to true when fully automated

define('BP_EMAIL_BCC', 'gguttenfelder@gmail.com');


/*--------------------------------------------------------------
# Main Runner
--------------------------------------------------------------*/

function bp_run_customer_checkins() {

    $customer_info  = customer_info();
    $site           = str_replace('https://', '', get_bloginfo('url'));
    $name           = $customer_info['name'] ?? get_bloginfo('name') ?? 'Your Website';
    $email          = BP_EMAIL_TEST_MODE ?: ($customer_info['email'] ?? null);

    if (!$email) {
        error_log('bp_run_customer_checkins: no customer email found');
        return;
    }

    // Throttle ... only run email logic once per day max
    if (!BP_EMAIL_TEST_MODE) {
          $lastRun = (int)get_option('bp_customer_email_last_run', 0);
          if ((time() - $lastRun) < 82800) return;
          update_option('bp_customer_email_last_run', time());
     }
    $sent = false;

    // 1) Content freshness check
    $freshness = bp_check_content_freshness();
    if ($freshness['should_email']) {
        bp_send_freshness_email($email, $name, $freshness);
        $sent = true;
    }

    // 2) Jobsite GEO freshness
    $geoFreshness = bp_check_jobsite_geo_freshness();
    if ($geoFreshness['should_email']) {
        bp_send_jobsite_geo_email($email, $name, $geoFreshness);
        $sent = true;
    }

    // 3) Review milestones and surges ... always check,
    //    send immediately regardless of other emails
    $reviewHighlights = bp_analyze_review_highlights();
    if (!empty($reviewHighlights)) {
        bp_send_reviews_email($email, $name, $reviewHighlights);
        $sent = true;
    }

    // 4) Stats highlights ... only if nothing else sent today
    if (!$sent) {
        $highlights = bp_analyze_stats_highlights();
        if (!empty($highlights)) {
            bp_send_stats_email($email, $name, $highlights);
        }
    }
}


/*--------------------------------------------------------------
# Content Freshness Check
--------------------------------------------------------------*/

function bp_check_content_freshness() {
    $result = [
        'should_email'   => false,
        'days_since_any' => 0,
    ];
    $freshness_min = 90;

    if (!BP_EMAIL_TEST_MODE) {
        $lastFreshnessEmail = (int)get_option('bp_freshness_email_last_sent', 0);
        if ((time() - $lastFreshnessEmail) < (86400 * $freshness_min)) return $result;
    }

    $posts = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'numberposts'    => 1, // We only need the most recently modified
    ]);

    if (!$posts) return $result;

    $newestTs = strtotime($posts[0]->post_modified);
    $daysSince = (int)(( time() - $newestTs ) / 86400);

    $result['days_since_any'] = $daysSince;

    if ($daysSince >= $freshness_min) {
        $result['should_email'] = true;
    }

    return $result;
}

function bp_freshness_time_phrase(int $days): string {
    if ($days < 21) {
        $phrases = ['a few days', 'a couple of weeks', 'several days', 'a little while', 'a short while'];
    } elseif ($days < 35) {
        $phrases = ['a few weeks', 'about a month', 'several weeks', 'a while', 'a little while now', 'a few weeks now'];
    } elseif ($days < 65) {
        $phrases = ['a few weeks', 'a couple of months', 'several weeks', 'quite a while', 'a month or two', 'a good few weeks', 'some time now'];
    } elseif ($days < 100) {
        $phrases = ['several weeks', 'a few months', 'a long while', 'a couple of months now', 'quite some time', 'a good few months'];
    } elseif ($days < 130) {
        $phrases = ['a few months', 'several months', 'a few months now', 'quite a few months', 'a good stretch of time'];
    } elseif ($days < 180) {
        $phrases = ['several months now', 'a good while', 'quite a while now', 'a number of months', 'a significant stretch', 'longer than you might think'];
    } elseif ($days < 365) {
        $phrases = ['a long time', 'quite a long time', 'many months', 'the better part of a year', 'a good long while', 'a long stretch', 'longer than we would recommend'];
    } else {
        $phrases = ['a very long time', 'well over a year', 'far too long', 'longer than we\'d like to mention', 'perhaps longer than you realized', 'way too long'];
    }
    return $phrases[array_rand($phrases)];
}


/*--------------------------------------------------------------
# Jobsite GEO Freshness Check
--------------------------------------------------------------*/
function bp_check_jobsite_geo_freshness() {
    $result = [
        'should_email'   => false,
        'days_since_any' => 0,
    ];

    // Only run if Jobsite GEO is installed
    $jobsite_geo = get_option('jobsite_geo');
    if (empty($jobsite_geo['install'])) return $result;

    $freshness_min = 14; // 2 weeks

    if (!BP_EMAIL_TEST_MODE) {
        $lastGeoEmail = (int)get_option('bp_jobsite_geo_email_last_sent', 0);
        if ((time() - $lastGeoEmail) < (86400 * $freshness_min)) return $result;
    }

    $posts = get_posts([
        'post_type'      => 'jobsite_geo',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ]);

    if (!$posts) return $result;

    $newestTs  = strtotime($posts[0]->post_modified);
    $daysSince = (int)((time() - $newestTs) / 86400);

    $result['days_since_any'] = $daysSince;

    if ($daysSince >= $freshness_min) {
        $result['should_email'] = true;
    }

    return $result;
}



/*--------------------------------------------------------------
# Stats Analysis
--------------------------------------------------------------*/

function bp_analyze_stats_highlights() {

    $highlights = [];

    $daily   = get_option('bp_ga4_daily_clean');
    $rollups = get_option('bp_ga4_rollups_clean');
    $freshness_min = 90;

    if (!$daily || !is_array($daily)) return $highlights;

    // Only send stats email max once every few months
    if (!BP_EMAIL_TEST_MODE) {
          $lastStatsEmail = (int)get_option('bp_stats_email_last_sent', 0);
          if ((time() - $lastStatsEmail) < (86400 * 30)) return $highlights;
     }
    $dates    = array_keys($daily);
    $anchorTs = bp_ymd_to_ts($dates[0]);

    // ---- Helper: sum a metric over a day range ----
    $sumMetric = function(int $startOffset, int $endOffset, string $metric) use ($daily, $anchorTs): int {
        $total = 0;
        for ($i = $startOffset; $i <= $endOffset; $i++) {
            $key = date('Ymd', strtotime("-{$i} days", $anchorTs));
            $total += (int)($daily[$key][$metric] ?? 0);
        }
        return $total;
    };

    // ---- This month vs last month ----
    $lastMonthSessions = $sumMetric(30, 59, 'sessions');

    if ($lastMonthSessions > 0) {
        $momChange = (($thisMonthSessions - $lastMonthSessions) / $lastMonthSessions) * 100;

        if ($momChange >= 15) {
            $highlights[] = [
                'type'    => 'mom_sessions_up',
                'change'  => round($momChange, 1),
                'current' => $thisMonthSessions,
                'prior'   => $lastMonthSessions,
                'label'   => "Traffic is up " . round($momChange, 1) . "% from last month",
            ];
        }
    }

    // ---- This month vs same month last year (seasonal comparison) ----
    $thisMonthSessions    = $sumMetric(0, 29, 'sessions');
    $sameMonthLastYear    = $sumMetric(365, 394, 'sessions');

    if ($sameMonthLastYear > 0) {
        $smlyChange = (($thisMonthSessions - $sameMonthLastYear) / $sameMonthLastYear) * 100;
        if ($smlyChange >= 10) {
            $highlights[] = [
                'type'    => 'smly_sessions_up',
                'change'  => round($smlyChange, 1),
                'current' => $thisMonthSessions,
                'prior'   => $sameMonthLastYear,
                'label'   => "Traffic this " . date('F') . " is up " . round($smlyChange, 1) . "% compared to " . date('F', strtotime('-1 year')) . " last year",
            ];
        }
    }

     // ---- This quarter vs last quarter ----
     $thisQuarterSessions = $sumMetric(0, 89, 'sessions');
     $lastQuarterSessions = $sumMetric(90, 179, 'sessions');

     if ($lastQuarterSessions > 0) {
          $qoqChange = (($thisQuarterSessions - $lastQuarterSessions) / $lastQuarterSessions) * 100;
          if ($qoqChange >= 10) {
               $highlights[] = [
                    'type'    => 'qoq_sessions_up',
                    'change'  => round($qoqChange, 1),
                    'current' => $thisQuarterSessions,
                    'prior'   => $lastQuarterSessions,
                    'label'   => "Traffic is up " . round($qoqChange, 1) . "% compared to last quarter",
               ];
          }
     }

     // ---- This quarter vs same quarter last year ----
     $thisQuarterSessions  = $sumMetric(0, 89, 'sessions');
     $sameQuarterLastYear  = $sumMetric(365, 454, 'sessions');

     if ($sameQuarterLastYear > 0) {
          $sqlyChange = (($thisQuarterSessions - $sameQuarterLastYear) / $sameQuarterLastYear) * 100;
          if ($sqlyChange >= 10) {
               $highlights[] = [
                    'type'    => 'sqly_sessions_up',
                    'change'  => round($sqlyChange, 1),
                    'current' => $thisQuarterSessions,
                    'prior'   => $sameQuarterLastYear,
                    'label'   => "Traffic this quarter is up " . round($sqlyChange, 1) . "% compared to the same quarter last year",
               ];
          }
     }

     // ---- This year vs last year ----
     $thisYearSessions = $sumMetric(0, 364, 'sessions');
     $lastYearSessions = $sumMetric(365, 729, 'sessions');

     if ($lastYearSessions > 0) {
          $yoyChange = (($thisYearSessions - $lastYearSessions) / $lastYearSessions) * 100;
          if ($yoyChange >= 10) {
               $highlights[] = [
                    'type'    => 'yoy_sessions_up',
                    'change'  => round($yoyChange, 1),
                    'current' => $thisYearSessions,
                    'prior'   => $lastYearSessions,
                    'label'   => "Traffic is up " . round($yoyChange, 1) . "% compared to last year",
               ];
          }
     }

     // ---- This month compared to same month in prior years ----
     $currentMonth     = (int)date('n'); // 1-12
     $currentMonthName = date('F');
     $monthHistogram   = [];

     for ($y = 0; $y < 6; $y++) {

          // Calculate the offset to the same month in prior years
          // Each year back is ~365 days, but we want the same calendar month
          $yearStart = strtotime("-{$y} years", $anchorTs);
          $monthStart = mktime(0, 0, 0, $currentMonth, 1, (int)date('Y', $yearStart));
          $daysInMonth = (int)date('t', $monthStart);

          // Convert to day offsets from anchor
          $startOffset = (int)(($anchorTs - $monthStart) / 86400);
          $endOffset   = $startOffset + $daysInMonth - 1;

          // Skip if offsets are negative (future) or beyond our data
          if ($startOffset < 0) continue;

          $total = $sumMetric($startOffset, $endOffset, 'sessions');

          if ($total > 0) {
               $monthHistogram[] = [
                    'year'     => date('Y', $monthStart),
                    'sessions' => $total,
               ];
          }
     }

     // Need at least 3 years of same-month data
     if (count($monthHistogram) >= 3) {

          // Sort oldest to newest
          usort($monthHistogram, fn($a, $b) => $a['year'] <=> $b['year']);

          // Check for consistent growth in this month across years
          $monthGrowthRates  = [];
          $monthGrowthCount  = 0;
          $monthTrendLines   = [];

          for ($i = 0; $i < count($monthHistogram); $i++) {

               $entry = $monthHistogram[$i];
               $line  = $entry['year'] . ': ' . number_format($entry['sessions']) . ' sessions';

               if ($i > 0) {
                    $prior  = $monthHistogram[$i - 1]['sessions'];
                    $change = $prior > 0
                         ? round((($entry['sessions'] - $prior) / $prior) * 100, 1)
                         : 0;

                    $monthGrowthRates[] = $change;
                    $arrow = $change >= 0 ? '↑' : '↓';
                    $line .= ' (' . $arrow . ' ' . abs($change) . '% vs ' . $monthHistogram[$i - 1]['year'] . ')';

                    if ($change > 0) $monthGrowthCount++;
               }

               $monthTrendLines[] = $line;
          }

          $totalComparisons = count($monthGrowthRates);
          $avgMonthGrowth   = $totalComparisons > 0
               ? round(array_sum($monthGrowthRates) / $totalComparisons, 1)
               : 0;

          // Only highlight if mostly positive
          if ($monthGrowthCount >= 2) {

               $label = $monthGrowthCount === $totalComparisons
                    ? "{$currentMonthName} has grown every year on record, averaging {$avgMonthGrowth}% growth"
                    : "{$currentMonthName} has grown in {$monthGrowthCount} of the last {$totalComparisons} years";

               $highlights[] = [
                    'type'        => 'month_historical',
                    'month'       => $currentMonthName,
                    'years'       => $monthGrowthCount,
                    'avg_growth'  => $avgMonthGrowth,
                    'trend_lines' => $monthTrendLines,
                    'label'       => $label,
               ];
          }
     }

     // ---- Multi-year trend analysis ----
     $yearTotals = [];

     for ($y = 0; $y < 6; $y++) {
          $startOffset = $y * 365;
          $endOffset   = ($y + 1) * 365 - 1;
          $total       = $sumMetric($startOffset, $endOffset, 'sessions');

          if ($total > 0) {
               $yearTotals[$y] = [
                    'year'     => date('Y', strtotime("-{$y} years")),
                    'sessions' => $total,
               ];
          }
     }

     // Need at least 3 years to identify a trend
     if (count($yearTotals) >= 3) {

          // Check for consistent year-over-year growth
          // $yearTotals[0] = most recent year, $yearTotals[1] = year before, etc.
          $growthRates    = [];
          $consistentGrow = true;
          $yearsOfGrowth  = 0;

          for ($i = 0; $i < count($yearTotals) - 1; $i++) {
               $current = $yearTotals[$i]['sessions'];
               $prior   = $yearTotals[$i + 1]['sessions'];

               if ($prior <= 0) break;

               $change = (($current - $prior) / $prior) * 100;
               $growthRates[$i] = round($change, 1);

               if ($change > 0) {
                    $yearsOfGrowth++;
               } else {
                    $consistentGrow = false;
               }
          }

          // Build a readable trend summary regardless of consistency
          $trendLines = [];
          for ($i = count($yearTotals) - 1; $i >= 0; $i--) {
               $entry = $yearTotals[$i];
               $line  = $entry['year'] . ': ' . number_format($entry['sessions']) . ' sessions';

               if (isset($growthRates[$i])) {
                    $arrow = $growthRates[$i] >= 0 ? '↑' : '↓';
                    $line .= ' (' . $arrow . ' ' . abs($growthRates[$i]) . '% vs prior year)';
               }

               $trendLines[] = $line;
          }

          // Only highlight if we have consistent growth across at least 3 years
          if ($consistentGrow && $yearsOfGrowth >= 3) {

               $avgGrowth = round(array_sum($growthRates) / count($growthRates), 1);

               $highlights[] = [
                    'type'        => 'multi_year_trend',
                    'years'       => $yearsOfGrowth,
                    'avg_growth'  => $avgGrowth,
                    'trend_lines' => $trendLines,
                    'label'       => "Your website has grown consistently for {$yearsOfGrowth} years in a row, averaging {$avgGrowth}% growth per year",
               ];

          } elseif ($yearsOfGrowth >= 2) {

               // Partial trend ... still worth mentioning
               $highlights[] = [
                    'type'        => 'multi_year_trend',
                    'years'       => $yearsOfGrowth,
                    'avg_growth'  => round(array_sum(array_slice($growthRates, 0, $yearsOfGrowth)) / $yearsOfGrowth, 1),
                    'trend_lines' => $trendLines,
                    'label'       => "Your website has grown in {$yearsOfGrowth} of the last " . count($growthRates) . " years",
               ];
          }
     }

    // ---- Engagement rate this month ----
    $thisMonthEngaged  = $sumMetric(0, 29, 'engagedSessions');
    $engagementRate    = $thisMonthSessions > 0
        ? round(($thisMonthEngaged / $thisMonthSessions) * 100, 1)
        : 0;

    if ($engagementRate >= 60) {
        $highlights[] = [
            'type'  => 'high_engagement',
            'value' => $engagementRate,
            'label' => "Engagement rate is strong at {$engagementRate}% this month",
        ];
    }

    // ---- Phone/email conversions ----
    $content_data = get_option('bp_ga4_content_clean');
    if ($content_data && is_array($content_data)) {

        $convPhoneKey = 'conversion-phone-call';
        $convEmailKey = 'conversion-email';

        $phoneCalls = (int)($content_data[$convPhoneKey]['sessions-30'] ?? 0);
        $emails     = (int)($content_data[$convEmailKey]['sessions-30'] ?? 0);

        if ($phoneCalls > 0) {
            $highlights[] = [
                'type'  => 'conversions',
                'phone' => $phoneCalls,
                'email' => $emails,
                'label' => number_format($phoneCalls) . " phone " .
                           _n('click', 'clicks', $phoneCalls) .
                           ($emails > 0 ? " and " . number_format($emails) . " email " .
                           _n('click', 'clicks', $emails) : '') .
                           " tracked this month",
            ];
        }
    }

    // ---- Top referring source ----
    $referrers = get_option('bp_ga4_referrers_clean');
    if ($referrers && is_array($referrers)) {

        $period = [];
        foreach ($referrers as $ref => $metrics) {
            if (!is_array($metrics)) continue;
            $v = (int)($metrics['sessions-30'] ?? 0);
            if ($v > 0) $period[$ref] = $v;
        }

        arsort($period);
        $topRef = array_key_first($period);

        if ($topRef && $topRef !== '(direct) / (none)') {
            $search  = ['(direct) / (none)', ' / referral', ' / organic', ' / cpc', 'google', 'bing'];
            $replace = ['Direct', '', ' (organic)', ' (paid)', 'Google', 'Bing'];
            $topLabel = str_replace($search, $replace, $topRef);

            $highlights[] = [
                'type'  => 'top_referrer',
                'ref'   => $topLabel,
                'count' => $period[$topRef],
                'label' => "Top traffic source this month: {$topLabel} with " .
                           number_format($period[$topRef]) . " engaged sessions",
            ];
        }
    }

    // ---- Top 3 pages (excluding home & contact) ----
     $pages_data = get_option('bp_ga4_pages_clean');
     if ($pages_data && is_array($pages_data)) {

          $period = [];

          foreach ($pages_data as $slug => $metrics) {
               if (!is_array($metrics)) continue;

               // Skip home page
               if ($slug === '/' || $slug === '' || $slug === "contact" || $slug === "contact-us") continue;

               $v = (int)($metrics['page-views-30'] ?? 0);
               if ($v > 0) $period[$slug] = $v;
          }

          arsort($period);
          $topPages = array_slice($period, 0, 3, true);

          if (!empty($topPages)) {

               $pageLines = [];
               foreach ($topPages as $slug => $views) {
                    $label = bp_ga4_path_to_label($slug);
                    $pageLines[] = $label . ' ... ' . number_format($views) . ' ' . _n('visit', 'visits', $views);
               }

               $highlights[] = [
                    'type'  => 'top_pages',
                    'pages' => $topPages,
                    'label' => 'Most visited pages this month: ' . implode(', ', $pageLines),
               ];
          }
     }

     // ---- Top service area towns by traffic ----
     $customer_info = customer_info();
     $serviceAreas  = $customer_info['service-areas'] ?? [];
     $locations     = get_option('bp_ga4_locations_clean');

     if (!empty($serviceAreas) && is_array($serviceAreas) && $locations && is_array($locations)) {

          // Build a simple lookup of service area city names (lowercase for comparison)
          $serviceAreaCities = [];
          foreach ($serviceAreas as $area) {
               if (!empty($area[0])) {
                    $serviceAreaCities[strtolower(trim($area[0]))] = trim($area[0]);
               }
          }

          // Build period totals for locations
          $locationPeriod = [];
          foreach ($locations as $city => $metrics) {
               if (!is_array($metrics)) continue;
               $v = (int)($metrics['sessions-30'] ?? 0);
               if ($v > 0) $locationPeriod[$city] = $v;
          }

          arsort($locationPeriod);

          // Find top 3 that are in service areas
          $matchedTowns = [];
          foreach ($locationPeriod as $city => $visits) {
               if (isset($serviceAreaCities[strtolower($city)])) {
                    $matchedTowns[$serviceAreaCities[strtolower($city)]] = $visits;
               }
               if (count($matchedTowns) >= 3) break;
          }

          if (!empty($matchedTowns)) {
               $highlights[] = [
                    'type'   => 'top_towns',
                    'towns'  => $matchedTowns,
                    'label'  => 'Top service area towns this month: ' . implode(', ', array_map(
                         fn($city, $visits) => "{$city} (" . number_format($visits) . " visits)",
                         array_keys($matchedTowns),
                         $matchedTowns
                    )),
               ];
          }
     }

    return $highlights;
}


/*--------------------------------------------------------------
# Google Reviews Analysis (separate from stats highlights
# because it has its own throttle logic per milestone)
--------------------------------------------------------------*/

function bp_analyze_review_highlights() {

    $customer_info = customer_info();
    $placeIDs      = ci_normalize_pids($customer_info['pid'] ?? []);
    $google_info   = get_option('bp_gbp_update') ?: [];

    if (empty($placeIDs)) return [];

    $highlights = [];

    // Aggregate across all locations
    $totalReviews     = 0;
    $totalRating      = 0.0;
    $weightedRating   = 0.0;
    $weekReviews      = 0;
    $monthReviews     = 0;

    foreach ($placeIDs as $placeID) {
        $info    = $google_info[$placeID] ?? [];
        $reviews = (int)($info['google-reviews'] ?? 0);
        $rating  = (float)($info['google-rating'] ?? 0);

        $totalReviews   += $reviews;
        $weightedRating += $rating * $reviews;
    }

    $avgRating = $totalReviews > 0 ? round($weightedRating / $totalReviews, 1) : 0;

    // ---- Milestone check ----
    // Milestones: 50, 100, 150, 200, then every 100 after that
    $milestones = [50, 100, 150, 200];
    for ($m = 300; $m <= 10000; $m += 100) {
        $milestones[] = $m;
    }

    $lastMilestone = get_option('bp_reviews_last_milestone');

     // First time running ... initialize to current count so we
     // don't retroactively fire milestones already passed
     if ($lastMilestone === false) {
     // Find the highest milestone already passed
     $alreadyPassed = 0;
     foreach ($milestones as $milestone) {
          if ($totalReviews >= $milestone) $alreadyPassed = $milestone;
     }
     update_option('bp_reviews_last_milestone', $alreadyPassed);
     error_log("bp_reviews: initialized last milestone to {$alreadyPassed} (current reviews: {$totalReviews})");
     return []; // Don't send anything on first run
     }

$lastMilestone = (int)$lastMilestone;

    foreach ($milestones as $milestone) {

        if ($totalReviews >= $milestone && $lastMilestone < $milestone) {

            update_option('bp_reviews_last_milestone', $milestone);

            $highlights[] = [
                'type'      => 'review_milestone',
                'milestone' => $milestone,
                'total'     => $totalReviews,
                'rating'    => $avgRating,
                'label'     => "You've reached <strong>{$milestone} Google reviews</strong> with an average rating of {$avgRating} ⭐",
            ];

            break; // Only one milestone per run
        }
    }

    // ---- Weekly surge check ----
    // Store last known review count and compare
    $lastWeekCount  = (int)get_option('bp_reviews_count_last_week', 0);
    $lastMonthCount = (int)get_option('bp_reviews_count_last_month', 0);
    $today          = date('N'); // 1=Mon, 7=Sun

    // Update weekly snapshot on Mondays
    if ($today == 1) {
        $weekGain = $totalReviews - $lastWeekCount;

        if ($weekGain >= 5) {
            $highlights[] = [
                'type'  => 'review_week_surge',
                'gain'  => $weekGain,
                'total' => $totalReviews,
                'label' => "You received <strong>{$weekGain} new Google reviews</strong> this past week ... keep it up!",
            ];
        }

        update_option('bp_reviews_count_last_week', $totalReviews);
    }

    // Update monthly snapshot on the 1st
    if (date('j') == 1) {
        $monthGain = $totalReviews - $lastMonthCount;

        if ($monthGain >= 10) {
            $highlights[] = [
                'type'  => 'review_month_surge',
                'gain'  => $monthGain,
                'total' => $totalReviews,
                'label' => "You received <strong>{$monthGain} new Google reviews</strong> this past month ... great work!",
            ];
        }

        update_option('bp_reviews_count_last_month', $totalReviews);
    }

    return $highlights;
}


/*--------------------------------------------------------------
# Email Builders
--------------------------------------------------------------*/

function bp_send_freshness_email($email, $name, $freshness) {
    $timePhrase = bp_freshness_time_phrase($freshness['days_since_any']);

    $subject_options = [
        "It's been {$timePhrase} ... time to freshen up your site",
        "Your website could use a refresh ... it's been {$timePhrase}",
        "We haven't updated your site in {$timePhrase}",,
        "Checking in... we haven't added to your site in {$timePhrase}",
        "Website update?",
    ];
    $subject = $subject_options[array_rand($subject_options)];

    $opening_options_1 = [
        "It looks like we haven't updated your website content in {$timePhrase}.",
        "Just a heads up ... it's been {$timePhrase} since we last updated your website.",
        "I noticed it's been {$timePhrase} since we made any changes to the website.",
        "I was doing some maintenance on the website, and noticed it's been {$timePhrase} since we made updates.",
        "While doing some routine work on the website code, it occurred to me that we haven't added any photos in {$timePhrase}.",
        "The pictures on your website haven't been changed in {$timePhrase}.",
        "It's been {$timePhrase} since we updated the photos and content on the website.",
        "I was reviewing your website today and realised it's been {$timePhrase} since anything was updated.",
        "Quick note ... it's been {$timePhrase} since we last made any changes to your site.",
        "While I had your website open today, I noticed the content hasn't been touched in {$timePhrase}.",
        "I was doing a routine check on your site and it looks like it's been {$timePhrase} since the last update.",
        "We haven't added any new photos to the website in {$timePhrase}, so I wanted to reach out.",
        "I don't think we've added anything new to your website in {$timePhrase}, which is worth addressing.",
        "It's been {$timePhrase} since we last refreshed any of the content or photos on your site.",
        "Looking at your site today, it appears the last update was {$timePhrase} ago.",
        "I was checking in on your website and noticed it's been {$timePhrase} without any new content or images.",
        "Your site is looking good, but it hasn't had any fresh content or photos added in {$timePhrase}.",
    ];

    $opening_options_2= [
        "I wanted to give you a friendly nudge, because keeping your content and images fresh matters more than you might think.",
        "It's one of those things that's easy to let slide, but it's worth paying attention to.",
        "I know you're busy, so consider this a gentle reminder.",
        "Keeping the information and pictures fresh on the site is more important than most people realize.",
        "There may not be a lot of changes to your services, but I'm sure you've got some impressive photos we could add.",
        "Even if there aren't a lot of changes to your business, we can always freshen up the pictures.",
        "Most business owners don't think about their website until something goes wrong ... this is just a nudge to get ahead of it.",
        "It's easy to set your website up and forget about it, but a little regular attention goes a long way.",
        "Your website is often the first impression someone gets of your business ... it's worth making sure it still reflects where you're at today.",
        "Think of it like a shopfront. You wouldn't leave the same window display up for a year, and your website is no different.",
        "Even if your business hasn't changed much, your website showing some recent activity sends a positive signal to both Google and potential customers.",
        "A lot can change in a few months ... new work you're proud of, updated pricing, a better photo. It's worth taking stock of what could be refreshed.",
        "You've probably done some great work lately that isn't reflected on your site yet. That's worth fixing.",
        "Sometimes it's not about big changes ... just making sure what's there still feels current and represents you well.",
        "Your website works for you around the clock. Giving it a little attention every so often helps make sure it's doing that job as well as it can.",
        "We're not talking about a redesign ... just a freshen up. The kind of small changes that keep things feeling current and relevant.",
    ];

    $seo_options = [
        "Google likes to see websites that evolve and change, because it signals you're business is healthy.",
        "Search engines like Google favour websites that show signs of life. Fresh content signals that your site is active and relevant, which can help you rank higher and get found by more potential customers.",
        "Google pays attention to how often your site is updated. Sites that stay fresh tend to rank better ... meaning more people find you instead of your competitors.",
        "One of the quieter ranking factors search engines use is how regularly a site is updated. Keeping your content current sends a signal that your site is alive, active, and worth showing to people.",
        "Fresh content is one of the signals Google uses to decide how relevant your site is. The more regularly your site gets updated, the more likely it is to show up when someone nearby searches for what you offer.",
        "Search engines are essentially asking one question: is this site worth sending people to? A site that hasn't changed in a while is harder to say yes to. Regular updates help tip that answer in your favour.",
        "Google crawls websites regularly looking for new and updated content. When it finds it, it tends to reward those sites with better visibility. When it doesn't, those sites can quietly slip down the rankings.",
        "Ranking well on Google isn't just about what your site says ... it's also about showing that your site is maintained and current. Regular updates, even small ones, help demonstrate that your business is active and engaged.",
        "Google tends to trust websites that are kept up to date. A site that hasn't changed in a while can start to lose ground to competitors who are making regular updates.",
        "Think of Google as a librarian ... it wants to recommend the most current and relevant resources. Websites that get updated regularly are easier to recommend.",
        "One thing Google looks for is evidence that a website is actively maintained. Fresh content and updated photos are a simple way to provide that evidence.",
        "A regularly updated website tells Google that there's someone behind it paying attention ... and that's the kind of site it prefers to send people to.",
        "Search engines reward consistency. You don't need to post new content every week, but showing some activity every couple of months makes a real difference to where you show up.",
        "Your competitors are likely updating their sites. Google notices that kind of thing, and over time it can affect who shows up first when someone searches for your services.",
        "Websites that stay fresh tend to hold their rankings. Websites that go quiet for long stretches can find themselves slowly sliding down the results page without any obvious reason why.",
        "Google's job is to connect people with the most relevant and trustworthy results. A well-maintained, up-to-date website is a much easier case for Google to make on your behalf.",
        "It's not just about adding new pages or blog posts. Even updating existing content ... refreshing a description, swapping a photo ... tells search engines your site is worth another look.",
        "The sites that tend to rank well locally are the ones that show consistent upkeep. It doesn't take much, but doing it regularly adds up over time.",
    ];

    $trust_options = [
        "Visitors notice too. Outdated photos, old pricing, or stale service descriptions can quietly erode trust ... people want to know they're looking at what you actually offer today, not a few years ago.",
        "It's not just about search engines either. Real visitors pick up on outdated content. Old photos, prices that don't match, or descriptions that feel dated can make people wonder if you're still in business.",
        "There's also the human side of it. When someone lands on your site and the content feels old, it raises doubt ... even subconsciously. Fresh content builds confidence that you're active, professional, and on top of things.",
        "People make snap judgments about businesses based on their website. If yours looks like it hasn't been touched in a while, some of those judgments won't be favourable ... even if your actual work is excellent.",
        "When a potential customer visits your site, they're trying to decide if they can trust you. An outdated website can quietly work against you before you've even had a chance to speak to them.",
        "First impressions happen fast online. If someone lands on your site and the photos look dated or the content feels old, they may move on before they've given you a real chance.",
        "A website that feels current and well looked after tells visitors that you take your business seriously. It's a small thing that carries more weight than most people realise.",
        "Think about how you'd feel walking into a shop where nothing had changed in two years. Websites give people that same feeling ... and they're just as likely to walk back out.",
        "Potential customers are often comparing you against others in the area. A fresh, up-to-date website can be the difference between someone choosing you or clicking on the next result.",
        "If someone visits your site and then visits a competitor's that looks more current, you've already lost ground ... even if your services are better. Perception matters.",
        "An outdated website doesn't just fail to impress ... it can actively put people off. Most visitors won't tell you that's why they didn't get in touch. They'll just quietly go elsewhere.",
        "People expect businesses they trust to have websites that reflect where they're at today. Outdated content creates a gap between who you are now and what your site is saying about you.",
        "It's easy to underestimate how much a website influences buying decisions. For a lot of people, it's the first place they go to decide whether to pick up the phone ... and what they find there matters.",
    ];

    $effort_options = [
        "Even small updates help. Refreshing a photo, rewriting an intro paragraph, or updating a service description sends the right signals and keeps your site feeling current.",
        "The good news is it doesn't have to be a big undertaking. Even swapping out a photo or tweaking a few sentences on your main pages can go a long way.",
        "You don't need to overhaul anything. Sometimes just updating a few images or refreshing how you describe your services is enough to make a meaningful difference.",
        "It doesn't take much to make a noticeable difference. A new photo here, an updated description there ... small things that add up to a site that feels alive and current.",
        "The bar isn't as high as you might think. Even minor changes ... a refreshed image, a tweaked headline, an updated service ... are enough to signal that your site is being looked after.",
        "We're not talking about starting from scratch. Just a handful of small updates can breathe new life into the site and send the right signals to both visitors and search engines.",
        "A quick review of your main pages is usually all it takes. Update a photo, tighten up a description, make sure everything still reflects what you actually offer ... it's simpler than it sounds.",
        "Sometimes the most effective updates are the smallest ones. A recent photo, a sentence that reflects how your business has grown, a service you've added ... these things matter more than you'd expect.",
        "It could be as simple as adding a few photos from a recent job, or updating a couple of lines on your services page. That kind of small effort goes further than most people realise.",
        "You don't need new content every week. Just making sure your site gets a little attention every few months is enough to keep things feeling fresh and relevant.",
        "Think of it less like a project and more like a quick tidy up. A little attention every now and then keeps things in good shape without ever becoming a big job.",
        "Even one or two new photos from recent work can make a real difference. It shows visitors ... and Google ... that your business is active and moving forward.",
        "The updates that matter most are often the quickest to make. A fresher photo, a more accurate description, a new example of your work ... none of it needs to take long.",
    ];

    $cta_options = [
        "It doesn't have to be a big project. Even a quick pass through your key pages can make a real difference. If you'd like a hand with it, we're always here to help.",
        "If you're not sure where to start, your homepage and main service pages are usually the best place. And if you'd like us to take a look, just say the word.",
        "Even setting aside an hour to review your key pages is worth it. If you'd rather hand it off, we're happy to help ... just reach out.",
        "If you've got some recent photos sitting on your phone, that's a great place to start. Send them through and we can take it from there.",
        "Your home page and services pages are usually the best place to begin. Have a read through and see what still feels accurate ... you might be surprised what stands out. And if you'd like us to handle it, we're happy to.",
        "Sometimes the hardest part is just deciding to do it. If you want to hand it over to us, we can take a look and suggest what's worth updating.",
        "Even just sending us a few new photos from recent jobs would be a great start. We can work with whatever you've got.",
        "If you'd like to get the ball rolling, just reply to this email and we'll take it from there. No need to have it all figured out beforehand.",
        "We're happy to do a quick review of your site and flag anything that looks like it could do with a refresh. Just let us know and we'll get onto it.",
        "If it's been sitting on the to-do list for a while, this is a good excuse to finally tick it off. We can make it as easy as possible ... just reach out.",
        "You don't need to come to us with a plan. Just get in touch and we'll figure out together what's worth updating and what can stay as is.",
        "A good starting point is just reading through your site as if you were a potential customer seeing it for the first time. Note anything that feels off, and we can go from there ... or leave it with us entirely.",
        "If time is the issue, that's what we're here for. Send us a message and we'll handle the updates so you don't have to think about it.",
    ];

    $sign_off_options = [
        "Thanks,\nGlendon",
        "Talk soon,\nGlendon",
        "Have a great week,\nGlendon",
        "Call if you need me!\nGlendon",
        "Let me know if you'd like a call.\nGlendon",
        "I appreciate your business!\nGlendon",
    ];

    $body = "Hi {$name},\n\n"
        . $opening_options_1[array_rand($opening_options)] . " "
        . $opening_options_2[array_rand($opening_options)] . "\n\n"
        . $seo_options[array_rand($seo_options)] . "\n\n"
        . $trust_options[array_rand($trust_options)] . "\n\n"
        . $effort_options[array_rand($effort_options)] . "\n\n"
        . $cta_options[array_rand($cta_options)] . "\n\n"
        . $sign_off_options[array_rand($sign_off_options)];
    wp_mail($email, $subject, $body);
    update_option('bp_freshness_email_last_sent', time());
}




function bp_send_jobsite_geo_email($email, $name, $freshness) {
    $timePhrase = bp_freshness_time_phrase($freshness['days_since_any']);

    // --- Subject ---
    $subject_options = [
        "Your jobsite posts haven't been updated in {$timePhrase}",
        "It's been {$timePhrase} ... time to add some new jobsite content",
        "A reminder to freshen up your jobsite posts ... it's been {$timePhrase}",
        "New jobsite posts are overdue ... it's been {$timePhrase}",
    ];
    $subject = $subject_options[array_rand($subject_options)];

    // --- Opening ---
    $opening_options = [
        "It's been {$timePhrase} since a new jobsite post was added to your website.",
        "Just a quick heads up ... your jobsite posts haven't been updated in {$timePhrase}.",
        "I was checking your website today and noticed it's been {$timePhrase} since the last jobsite post.",
        "Your jobsite content hasn't had anything new added in {$timePhrase}, so I wanted to reach out.",
        "I noticed while reviewing your site that the jobsite posts haven't had anything new in {$timePhrase}.",
        "It looks like it's been {$timePhrase} since we added a new jobsite post to your site.",
        "While doing some routine checks on your website, I noticed the jobsite posts haven't been updated in {$timePhrase}.",
        "Your last jobsite post was {$timePhrase} ago ... time to get some fresh work up there.",
        "I was having a look at your website today and realised we haven't added a jobsite post in {$timePhrase}.",
        "Just flagging that it's been {$timePhrase} since the last jobsite post went up on your site.",
        "It's been {$timePhrase} since your website showed any new jobsite activity.",
        "I wanted to reach out because it's been {$timePhrase} since your last jobsite post, and it's worth getting something new up there.",
        "A quick note to let you know it's been {$timePhrase} since we last added any jobsite content to your site.",
        "Your jobsite posts are looking a little quiet ... nothing new has gone up in {$timePhrase}.",
        "It's been {$timePhrase} since your site has shown any new jobsite work, which is worth addressing.",
        "I was doing a routine check and noticed your jobsite posts have been sitting unchanged for {$timePhrase} now.",
    ];

    // --- Why it matters ---
    $why_options = [
        "Jobsite posts are one of the most effective ways to show Google that your business is active and working in the local area. Each post is essentially a signal that says ... we're busy, we're local, and we're doing great work.",
        "Fresh jobsite content helps Google connect your business to the areas you're working in. The more regularly you add posts, the stronger that local relevance becomes.",
        "Jobsite posts do double duty ... they show potential customers the quality of your work, and they show Google exactly where you're operating. Both of those things help you rank better locally.",
        "Google loves geographic signals, and jobsite posts are one of the best ways to provide them. Regular posts help establish your presence in the areas where you want to be found.",
        "Each jobsite post you add is essentially a local SEO signal. Over time, a steady stream of posts tells Google that you're consistently active across your service area.",
        "When Google sees regular jobsite posts coming from your site, it builds a picture of where you work and how active you are. That picture directly influences how often your business shows up in local searches.",
        "Jobsite posts are one of the few things that help with both trust and rankings at the same time. Potential customers see real work, and Google sees a business that's active and local.",
        "Every jobsite post you add strengthens your footprint in the local area. Over time, that adds up to better visibility in the suburbs and towns where you actually want to be found.",
        "Google uses location signals to decide which businesses to show for local searches. Jobsite posts are one of the most direct ways to provide those signals ... they tell Google exactly where you've been working.",
        "Think of jobsite posts as a trail of breadcrumbs for Google. Each one reinforces where your business operates, which makes it easier for Google to recommend you to people searching in those areas.",
        "Consistent jobsite posts help Google build confidence in your local relevance. The more it sees you active in an area, the more likely it is to show your business to people searching there.",
        "A steady stream of jobsite content tells a story ... not just to potential customers, but to Google. It shows an active business doing real work in real places, which is exactly what local search rewards.",
        "Jobsite posts are particularly powerful for trade businesses because they're geographically specific. Each post ties your business to a location, and that kind of local relevance is hard to build any other way.",
        "Without regular jobsite posts, Google has less reason to associate your business with the areas you're working in. Keeping them consistent helps protect and grow your local search presence.",
        "For businesses that work across multiple suburbs or towns, jobsite posts are one of the best tools available. They help Google understand the full scope of your service area in a way that a services page simply can't.",
    ];

    // --- What to do ---
    $action_options = [
        "If you've completed any jobs lately, even a photo or two with a brief description is enough to make a solid post.",
        "Even a quick post with a couple of photos from a recent job is plenty. It doesn't need to be elaborate ... just current.",
        "A few photos from a recent job and a short description of the work is all it takes. Simple posts are just as effective as detailed ones.",
        "If you've got photos on your phone from recent jobs, that's all we need to put a post together. Send them through and we'll handle the rest.",
        "Recent job photos are the easiest place to start. Even one or two good images with a short note about the work makes for a great post.",
        "You don't need anything fancy ... just a few photos from a job you're proud of and a rough idea of what the work involved. We'll take it from there.",
        "If you've been out on jobs lately, chances are you've got photos sitting on your phone that would make great posts. Send them through whenever you get a chance.",
        "The best jobsite posts come straight from the field. A quick photo before you pack up, or a shot of the finished result, is all we really need.",
        "It doesn't matter how big or small the job was. Any recent work with a photo or two is worth posting ... it all adds up.",
        "Even a single before-and-after photo makes for a compelling post. If you've got anything like that from recent work, it's perfect.",
        "You don't need to write anything. Just send us the photos and a rough note about what the job was, and we'll put the post together for you.",
        "If you make a habit of snapping a photo or two at the end of each job, you'll never be short of content. It takes seconds on site and goes a long way online.",
        "Any job you've finished recently is fair game ... big projects, small ones, before and afters, finished results. Whatever you've got photos of works.",
        "The photos don't need to be professional. Clear shots taken on a phone are absolutely fine and make for authentic, relatable posts.",
        "Think about the jobs you've done lately that you're most proud of. A couple of photos from one of those is the perfect starting point.",
        "If you can set aside two minutes at the end of your next job to take a few photos, that's really all the raw material we need.",
        "Sometimes the most effective posts are the simplest ones ... a clean finished result, a quick description of the work, and the suburb it was done in. That's it.",
    ];

    // --- CTA ---
    $cta_options = [
        "If you can send through some recent job photos, we'll take care of the rest.",
        "Just reply to this email with a few photos and any details you'd like included, and we'll get it posted.",
        "Send us whatever you've got ... even just the photos ... and we'll put something together for you.",
        "If you'd like us to handle it, just get in touch and we'll sort out a new post from whatever recent work you have to share.",
        "Even a quick message with a few photos attached is enough for us to work with. We'll do the rest.",
        "Reply to this email with a few photos from a recent job and we'll handle everything from there.",
        "If you've got photos on your phone from recent work, just send them through and leave the rest to us.",
        "A quick reply with a couple of photos is all we need to get a new post up for you.",
        "Just shoot us a message with whatever photos you have and we'll take it from there ... no need to prepare anything.",
        "If it's easier, you can text or email us a few photos from your phone and we'll sort out the post.",
        "Even if you're not sure which photos to use, send a few through and we'll pick the best ones and put something together.",
        "Drop us a line whenever you're ready and we'll make it as easy as possible to get some fresh content up.",
        "If you've got a job on at the moment, a quick photo before you finish up is all we'd need. Send it through when you're done.",
        "We can work with whatever you've got ... a few photos, a job address, a rough description. Just get in touch and we'll figure it out.",
        "No need to overthink it. Reply here, attach a few photos, and we'll have a new post up before you know it.",
        "If time is tight, just forward us a few photos from your camera roll and we'll handle the writing and posting.",
        "Whenever you're ready to get something posted, just reach out. We'll keep it simple and take as much off your plate as we can.",
    ];

    // --- Help ---
    $help_options = [
        "If you need a refresher on exactly how to add jobsites to the website, please reach out so I can help.",
        "If you'd like a hand getting started with jobsite posts, just reach out and I'll walk you through it.",
        "If you're not sure how the jobsite posting works, I'm happy to run you through it ... just get in touch.",
        "If it's been a while and you can't quite remember the process, just give me a shout and I'll help you get sorted.",
        "If you'd like a quick refresher on how to add jobsite posts yourself, I'm only an email away.",
        "Not sure where to start? Just reach out and I'll guide you through the process from the beginning.",
        "If you've forgotten how the jobsite section works, don't worry ... just get in touch and I'll walk you through it.",
        "If you'd prefer to add the posts yourself but need a reminder of how it works, just say the word and I'll help.",
        "Feel free to reach out if you'd like me to show you how to get jobsite posts added quickly and easily.",
        "If the process feels unfamiliar, I'm happy to jump on a call or send through some instructions ... just let me know.",
        "If you'd like a quick rundown on how to add jobsite posts, just reply to this email and I'll help you get going.",
        "Don't hesitate to get in touch if you need any help with the jobsite posting process ... I'm happy to walk you through it.",
        "If you want me to take care of the posts on your behalf instead, that's no problem at all ... just reach out and we'll get it sorted.",
    ];

    // --- Sign off ---
    $sign_off_options = [
        "Thanks,\nGlendon",
        "As always, reach out if you need anything!\nGlendon",
        "Have a great week,\nGlendon",
        "I'm here if you need me, don't hesitate to call!\nGlendon",
        "I appreciate your business!\nGlendon",
    ];

    $body = "Hi {$name},\n\n"
        . $opening_options[array_rand($opening_options)] . "\n\n"
        . $why_options[array_rand($why_options)] . "\n\n"
        . $action_options[array_rand($action_options)] . "\n\n"
        . $cta_options[array_rand($cta_options)] . "\n\n"
        . $help_options[array_rand($help_options)] . "\n\n"
        . $signoff_options[array_rand($signoff_options)];

    wp_mail($email, $subject, $body);
    update_option('bp_jobsite_geo_email_last_sent', time());
}



function bp_send_stats_email(string $toEmail, ?string $name, array $highlights) {

    if (empty($highlights)) return;

    $name    = $name ?? get_bloginfo('name') ?? 'Your Website';
    $subject = bp_customer_email_subject("Your website stats this month ... {$name}");

    // Prioritize and cap highlights when fully automated
    if (BP_EMAIL_LIMIT_HIGHLIGHTS) {
        $priority = [
            'multi_year_trend' => 1,
            'month_historical' => 2,
            'smly_sessions_up' => 3,
            'sqly_sessions_up' => 4,
            'yoy_sessions_up'  => 5,
            'qoq_sessions_up'  => 6,
            'mom_sessions_up'  => 7,
            'conversions'      => 8,
            'high_engagement'  => 9,
            'top_towns'        => 10,
            'top_pages'        => 11,
            'top_referrer'     => 12,
        ];
        usort($highlights, fn($a, $b) =>
            ($priority[$a['type']] ?? 99) <=> ($priority[$b['type']] ?? 99)
        );
        $highlights = array_slice($highlights, 0, 3);
    }

    // Build body FIRST, then send
    $body  = "<p>Hi there,</p>";
    $body .= "<p>Here's a quick look at how your website performed this month:</p>";
    $body .= "<ul>";

    foreach ($highlights as $h) {

        if ($h['type'] === 'top_pages' && !empty($h['pages'])) {
            $body .= "<li>Most visited pages this month:<ul>";
            foreach ($h['pages'] as $slug => $views) {
                $label = bp_ga4_path_to_label($slug);
                $url   = get_bloginfo('url') . '/' . ltrim($slug, '/');
                $body .= "<li><a href='" . esc_url($url) . "'>" . esc_html($label) . "</a> ... " . number_format($views) . " " . _n('visit', 'visits', $views) . "</li>";
            }
            $body .= "</ul></li>";

        } elseif ($h['type'] === 'top_towns' && !empty($h['towns'])) {
            $body .= "<li>Top service area towns visiting your site this month:<ul>";
            foreach ($h['towns'] as $city => $visits) {
                $body .= "<li>" . esc_html($city) . " ... " . number_format($visits) . " " . _n('visit', 'visits', $visits) . "</li>";
            }
            $body .= "</ul></li>";

        } elseif ($h['type'] === 'month_historical' && !empty($h['trend_lines'])) {
            $body .= "<li>" . esc_html($h['label']) . ":<ul>";
            foreach ($h['trend_lines'] as $line) {
                $body .= "<li>" . esc_html($line) . "</li>";
            }
            $body .= "</ul></li>";

        } elseif ($h['type'] === 'multi_year_trend' && !empty($h['trend_lines'])) {
            $body .= "<li>" . esc_html($h['label']) . ":<ul>";
            foreach ($h['trend_lines'] as $line) {
                $body .= "<li>" . esc_html($line) . "</li>";
            }
            $body .= "</ul></li>";

        } else {
            $body .= "<li>{$h['label']}</li>";
        }
    }

    $body .= "</ul>";
    $body .= "<p>We're always working to keep your site performing well. ";
    $body .= "If you have any questions about these numbers or want to discuss ways to improve, just reply to this email.</p>";
    $body .= "<p>... The Battle Plan Web Design Team</p>";

    wp_mail(
        $toEmail,
        $subject,
        $body,
        [
            'Content-Type: text/html; charset=UTF-8',
            'Bcc: ' . BP_EMAIL_BCC,
        ]
    );

    update_option('bp_stats_email_last_sent', time());
    error_log("bp_send_stats_email sent to {$toEmail} with " . count($highlights) . " highlights");
}

function bp_send_reviews_email(string $toEmail, string $name, array $highlights) {

    if (empty($highlights)) return;

    $isMilestone = array_filter($highlights, fn($h) => $h['type'] === 'review_milestone');
    $subject     = $isMilestone
        ? bp_customer_email_subject("🎉 Congratulations on your Google reviews! ... {$name}")
        : bp_customer_email_subject("Your Google reviews are growing ... {$name}");

    $body  = "<p>Hi there,</p>";

    foreach ($highlights as $h) {
        if ($h['type'] === 'review_milestone') {
            $body .= "<p>🎉 <strong>Congratulations!</strong> {$h['label']}.</p>";
            $body .= "<p>This is a huge trust signal for potential customers searching for your services. ";
            $body .= "Your reputation online is one of the most valuable assets your business has ... and you've earned it.</p>";
        } elseif ($h['type'] === 'review_week_surge') {
            $body .= "<p>⭐ {$h['label']}.</p>";
            $body .= "<p>Momentum like this makes a real difference in how Google ranks your business locally. ";
            $body .= "Whatever you're doing to encourage reviews ... keep it up!</p>";
        } elseif ($h['type'] === 'review_month_surge') {
            $body .= "<p>⭐ {$h['label']}.</p>";
            $body .= "<p>A strong month of reviews helps your business stand out in local search results. ";
            $body .= "Great work!</p>";
        }
    }

    $body .= "<p>... The Battle Plan Web Design Team</p>";

    wp_mail(
        $toEmail,
        $subject,
        $body,
        [
            'Content-Type: text/html; charset=UTF-8',
            'Bcc: ' . BP_EMAIL_BCC,
        ]
    );

    error_log("bp_send_reviews_email sent to {$toEmail}");
}

/*--------------------------------------------------------------
# Helpers
--------------------------------------------------------------*/

function bp_customer_email_force() {
    delete_option('bp_customer_email_last_run');
    delete_option('bp_freshness_email_last_sent');
    delete_option('bp_stats_email_last_sent');
}

function bp_customer_email_subject(string $subject): string {
    if (BP_EMAIL_TEST_MODE) {
        $site = str_replace('https://', '', get_bloginfo('url'));
        return "[TEST - {$site}] {$subject}";
    }
    return $subject;
}