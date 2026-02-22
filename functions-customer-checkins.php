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
# Email Target — switch to false when ready to launch
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

    // Throttle — only run email logic once per day max
    if (!BP_EMAIL_TEST_MODE) {
          $lastRun = (int)get_option('bp_customer_email_last_run', 0);
          if ((time() - $lastRun) < 82800) return;
          update_option('bp_customer_email_last_run', time());
     }
    $sent = false;

    // 1) Content freshness check — highest priority
    $freshness = bp_check_content_freshness();
    if ($freshness['should_email']) {
        bp_send_freshness_email($email, $name, $freshness);
        $sent = true;
    }

    // 2) Review milestones and surges — always check,
    //    send immediately regardless of other emails
    $reviewHighlights = bp_analyze_review_highlights();
    if (!empty($reviewHighlights)) {
        bp_send_reviews_email($email, $name, $reviewHighlights);
        $sent = true;
    }

    // 3) Stats highlights — only if nothing else sent today
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
        'stale_pages'    => [],
        'days_since_any' => 0,
        'last_update'    => null,
    ];

    $freshness_min = 90;
    // Only email about freshness every few months
    if (!BP_EMAIL_TEST_MODE) {
          $lastFreshnessEmail = (int)get_option('bp_freshness_email_last_sent', 0);
          if ((time() - $lastFreshnessEmail) < (86400 * 30)) return $result;
     }
    $posts = get_posts([
          'post_type'      => 'page',
          'post_status'    => 'publish',
          'posts_per_page' => -1,
          'orderby'        => 'modified',
          'order'          => 'DESC',
     ]);

    if (!$posts) return $result;

    $now        = time();
    $stalePosts = [];
    $newestTs   = 0;

    foreach ($posts as $post) {

       $modifiedTs = strtotime($post->post_modified);

        if ($modifiedTs > $newestTs) {
            $newestTs = $modifiedTs;
            $result['last_update'] = $post->post_modified;
        }

        $daysSince = ($now - $modifiedTs) / 86400;

        if ($daysSince >= $freshness_min) {
            $stalePosts[] = [
                'title'      => $post->post_title,
                'url'        => get_permalink($post->ID),
                'days_since' => (int)$daysSince,
                'type'       => $post->post_type,
            ];
        }
    }

    $result['days_since_any'] = $newestTs ? (int)(($now - $newestTs) / 86400) : 999;
    $result['stale_pages']    = $stalePosts;

    // Trigger email if the pages haven't been updated since last email
    if ($result['days_since_any'] >= $freshness_min) {
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

    // ---- Engagement rate this month ----
    $thisMonthEngaged  = $sumMetric(0, 29, 'engagedSessions');
    $engagementRate    = $thisMonthSessions > 0
        ? round(($thisMonthEngaged / $thisMonthSessions) * 100, 1)
        : 0;

    if ($engagementRate >= 70) {
        $highlights[] = [
            'type'  => 'high_engagement',
            'value' => $engagementRate,
            'label' => "Engagement rate is strong at {$engagementRate}% this month",
        ];
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

               // Partial trend — still worth mentioning
               $highlights[] = [
                    'type'        => 'multi_year_trend',
                    'years'       => $yearsOfGrowth,
                    'avg_growth'  => round(array_sum(array_slice($growthRates, 0, $yearsOfGrowth)) / $yearsOfGrowth, 1),
                    'trend_lines' => $trendLines,
                    'label'       => "Your website has grown in {$yearsOfGrowth} of the last " . count($growthRates) . " years",
               ];
          }
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

    // ---- Top 3 pages (excluding home) ----
     $pages_data = get_option('bp_ga4_pages_clean');
     if ($pages_data && is_array($pages_data)) {

          $period = [];

          foreach ($pages_data as $slug => $metrics) {
               if (!is_array($metrics)) continue;

               // Skip home page
               if ($slug === '/' || $slug === '') continue;

               $v = (int)($metrics['page-views-30'] ?? 0);
               if ($v > 0) $period[$slug] = $v;
          }

          arsort($period);
          $topPages = array_slice($period, 0, 3, true);

          if (!empty($topPages)) {

               $pageLines = [];
               foreach ($topPages as $slug => $views) {
                    $label = bp_ga4_path_to_label($slug);
                    $pageLines[] = $label . ' — ' . number_format($views) . ' ' . _n('visit', 'visits', $views);
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

     // First time running — initialize to current count so we
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
                'label' => "You received <strong>{$weekGain} new Google reviews</strong> this past week — keep it up!",
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
                'label' => "You received <strong>{$monthGain} new Google reviews</strong> this past month — great work!",
            ];
        }

        update_option('bp_reviews_count_last_month', $totalReviews);
    }

    return $highlights;
}


/*--------------------------------------------------------------
# Email Builders
--------------------------------------------------------------*/

function bp_send_freshness_email(string $toEmail, string $name, array $freshness) {

     $subject = bp_customer_email_subject("A quick note about your website — {$name}");

    $daysSince = $freshness['days_since_any'];
    $staleCount = count($freshness['stale_pages']);

    $body  = "<p>Hi there,</p>";
    $body .= "<p>We noticed it's been about <strong>{$daysSince} days</strong> since anything on your website was updated.</p>";
    $body .= "<p>Fresh content helps with search rankings and keeps visitors engaged. Even small updates make a difference — ";
    $body .= "new photos, updated hours, a recent project, or a short note about what's new with your business.</p>";

    if ($staleCount > 0) {
        $body .= "<p>Here are some pages that could use a refresh:</p><ul>";
        foreach (array_slice($freshness['stale_pages'], 0, 5) as $page) {
            $body .= "<li><a href='{$page['url']}'>{$page['title']}</a> — last updated {$page['days_since']} days ago</li>";
        }
        $body .= "</ul>";
    }

    $body .= "<p>Have new photos, updated information, or anything you'd like added? ";
    $body .= "Just reply to this email and we'll take care of it.</p>";
    $body .= "<p>— The Battle Plan Web Design Team</p>";

    wp_mail(
          $toEmail,
          $subject,
          $body,
          [
              'Content-Type: text/html; charset=UTF-8',
              'Bcc: ' . BP_EMAIL_BCC,
          ]
     );

    update_option('bp_freshness_email_last_sent', time());
    error_log("bp_send_freshness_email sent to {$toEmail}");
}


function bp_send_stats_email(string $toEmail, ?string $name, array $highlights) {

    if (empty($highlights)) return;

    $name    = $name ?? get_bloginfo('name') ?? 'Your Website';
    $subject = bp_customer_email_subject("Your website stats this month — {$name}");

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
                $body .= "<li><a href='" . esc_url($url) . "'>" . esc_html($label) . "</a> — " . number_format($views) . " " . _n('visit', 'visits', $views) . "</li>";
            }
            $body .= "</ul></li>";

        } elseif ($h['type'] === 'top_towns' && !empty($h['towns'])) {
            $body .= "<li>Top service area towns visiting your site this month:<ul>";
            foreach ($h['towns'] as $city => $visits) {
                $body .= "<li>" . esc_html($city) . " — " . number_format($visits) . " " . _n('visit', 'visits', $visits) . "</li>";
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
    $body .= "<p>— The Battle Plan Web Design Team</p>";

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
        ? bp_customer_email_subject("🎉 Congratulations on your Google reviews! — {$name}")
        : bp_customer_email_subject("Your Google reviews are growing — {$name}");

    $body  = "<p>Hi there,</p>";

    foreach ($highlights as $h) {
        if ($h['type'] === 'review_milestone') {
            $body .= "<p>🎉 <strong>Congratulations!</strong> {$h['label']}.</p>";
            $body .= "<p>This is a huge trust signal for potential customers searching for your services. ";
            $body .= "Your reputation online is one of the most valuable assets your business has — and you've earned it.</p>";
        } elseif ($h['type'] === 'review_week_surge') {
            $body .= "<p>⭐ {$h['label']}.</p>";
            $body .= "<p>Momentum like this makes a real difference in how Google ranks your business locally. ";
            $body .= "Whatever you're doing to encourage reviews — keep it up!</p>";
        } elseif ($h['type'] === 'review_month_surge') {
            $body .= "<p>⭐ {$h['label']}.</p>";
            $body .= "<p>A strong month of reviews helps your business stand out in local search results. ";
            $body .= "Great work!</p>";
        }
    }

    $body .= "<p>— The Battle Plan Web Design Team</p>";

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