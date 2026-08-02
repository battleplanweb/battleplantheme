<?php
/* Battle Plan Web Design Functions: Chron A — GBP + CI Sync */

require_once get_template_directory() . '/vendor/autoload.php';

function bp_run_chron_gbp(bool $force = false): void {

	$google_info  = get_option('bp_gbp_update') ?: [];
	$customer_info = customer_info();
	$pid_sync      = (bool) filter_var($customer_info['pid-sync'] ?? false, FILTER_VALIDATE_BOOLEAN);
	$placeIDs      = ci_normalize_pids($customer_info['pid'] ?? []);

	if (!empty($placeIDs)) {

// 1) Decide whether to hit the Places API based on review count thresholds
		$today      = strtotime(date("F j, Y"));
		$daysSince  = ($today - (int)($google_info['date'] ?? 0)) / 86400;
		$reviewCount = (int)($google_info['google-reviews'] ?? 0);

		// Sync cadence in days, by review count — highest matching threshold wins.
		// The loop used to fall through every row without breaking, so the SMALLEST
		// matching threshold always won and every site with 50+ reviews synced on the
		// same 6-day cadence no matter its size. Values are floored at 6 days on
		// purpose: each Place ID fetched is one Place Details *Enterprise* call
		// ($20/1,000 — the rating/userRatingCount/phone fields force that tier), so
		// cadence is the only real cost lever in this function. Do not lower these
		// without checking the Places API spend first.
		$thresholds = [1000 => 6, 500 => 6, 250 => 7, 125 => 7, 75 => 7, 50 => 7];
		$days       = 7;

		foreach ($thresholds as $limit => $val) {
			if ($reviewCount >= $limit) { $days = $val; break; }
		}

		// Back-off after a fully-failed run. Because a failed run deliberately no longer
		// stamps $google_info['date'], $daysSince stays huge forever once a site breaks —
		// which would re-hit (and re-bill) the API every single night. This gate keeps a
		// broken site on the same cadence a healthy one gets. Cleared on the first success.
		$retryAfter = (int) get_option('bp_chron_a_retry_after', 0);

		if ($force === true || ($daysSince > $days && time() >= $retryAfter)) {
			
			update_option('bp_chron_a_api_time', time()); // timestamp of actual API hit

// 2) Fetch GBP data for each Place ID
			$google_rating     = 0.0;
			$google_review_num = 0;
			$fetched_ok        = 0;                                        // Place IDs that fetched cleanly this run
			$fetch_errors      = [];                                       // collected errors → one deduped alert, not one-per-PID
			$old_review_total  = (int) ($google_info['google-reviews'] ?? 0); // last-known-good total, for drop detection

			foreach ($placeIDs as $placeID) {
				if (strlen($placeID) <= 10) {
					$fetch_errors[] = "PID '$placeID' — skipped (too short / not a valid Place ID)";
					continue;
				}

				$fields = 'displayName,formattedAddress,addressComponents,location,regularOpeningHours,currentOpeningHours,internationalPhoneNumber,rating,userRatingCount,utcOffsetMinutes';
				$url    = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeID) . '?fields=' . urlencode($fields) . '&key=' . _PLACES_API;

				$ch = curl_init();
				curl_setopt_array($ch, [
					CURLOPT_URL            => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_CONNECTTIMEOUT => 5,
					CURLOPT_TIMEOUT        => 12,
					CURLOPT_HTTPHEADER     => ['Accept: application/json'],
				]);
				$result = curl_exec($ch);
				$http   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
				$cerr   = curl_errno($ch);
				$cerrm  = curl_error($ch);
				curl_close($ch);

				// Collect errors instead of emailing per-PID-per-run (that flood is what got
				// spam-filtered). One deduped alert is sent after the loop, and the `continue`
				// no longer depends on emailMe() existing (it always must skip a bad response).
				if ($cerr) {
					$fetch_errors[] = "PID $placeID — cURL error $cerr: $cerrm";
					continue;
				}

				if ($http < 200 || $http >= 300) {
					$fetch_errors[] = "PID $placeID — HTTP $http\n" . $result;
					continue;
				}

				$gbp = json_decode($result, true);

				if (!is_array($gbp)) {
					$fetch_errors[] = "PID $placeID — invalid JSON response\n" . $result;
					continue;
				}

				if (isset($gbp['error'])) {
					$fetch_errors[] = "PID $placeID — API error:\n" . print_r($gbp['error'], true);
					continue;
				}

				$google_info[$placeID]['utcOffsetMinutes'] = $gbp['utcOffsetMinutes'] ?? null;

				$urc = isset($gbp['userRatingCount']) ? (int) $gbp['userRatingCount'] : 0;
				$rat = isset($gbp['rating']) ? (float) $gbp['rating'] : 0.0;
				$google_info[$placeID]['google-reviews'] = $urc;
				$google_info[$placeID]['google-rating']  = $rat;
				$google_review_num += $urc;
				$google_rating     += ($rat * $urc);
				$fetched_ok++;

				$phone = $gbp['internationalPhoneNumber'] ?? '';
				if (preg_match('/^\+1[\s\-\.]?(\d{3})[\s\-\.]?(\d{3})[\s\-\.]?(\d{4})$/', $phone, $m)) {
					$areaDigits  = $m[1];
					$localDigits = $m[2] . '-' . $m[3];
					$google_info[$placeID]['area']  = $areaDigits;
					$google_info[$placeID]['phone'] = $localDigits;
					if (str_contains((string)$customer_info['area-after'], '.')) {
						$google_info[$placeID]['phone'] = str_replace('-', '.', $google_info[$placeID]['phone']);
					}
					$google_info[$placeID]['phone-format'] =
						($customer_info['area-before'] ?? '') . $areaDigits .
						($customer_info['area-after']  ?? '') .
						$google_info[$placeID]['phone'];
				} else {
					$google_info[$placeID]['area']         = '';
					$google_info[$placeID]['phone']        = '';
					$google_info[$placeID]['phone-format'] = '';
				}

				$nm = strtolower($gbp['displayName']['text'] ?? '');
				$nm = str_replace(
					['llc','hvac','a/c','inc','mcm','a-ale','hph','gps plumbing','lecornu','ss&l','ag heat'],
					['LLC','HVAC','A/C','INC','MCM','A-Ale','HPH','GPS Plumbing','LeCornu','SS&L','AG Heat'],
					$nm
				);
				$google_info[$placeID]['name'] = ucwords($nm);

				$google_info[$placeID]['adr_address']        = $gbp['formattedAddress'] ?? '';
				$google_info[$placeID]['address_components'] = $gbp['addressComponents'] ?? [];

				$comp = [
					'street_num'   => '', 'route'        => '', 'premise'      => '',
					'subpremise'   => '', 'floor'        => '', 'city'         => '',
					'state_abbr'   => '', 'state_full'   => '', 'zip'          => '',
					'county'       => '', 'country_abbr' => '', 'country_full' => '',
				];

				foreach (($google_info[$placeID]['address_components'] ?? []) as $c) {
					$types = $c['types'] ?? [];
					$long  = $c['longText']  ?? '';
					$short = $c['shortText'] ?? '';

					if (in_array('street_number', $types, true))                 $comp['street_num'] = $long ?: $short;
					if (in_array('route', $types, true))                         $comp['route']      = $short ?: $long;
					if (in_array('premise', $types, true))                       $comp['premise']    = $long ?: $short;
					if (in_array('subpremise', $types, true))                    $comp['subpremise'] = $long ?: $short;
					if (in_array('floor', $types, true))                         $comp['floor']      = $long ?: $short;
					if (in_array('locality', $types, true))                      $comp['city']       = $long ?: $short;
					if (in_array('administrative_area_level_1', $types, true)) { $comp['state_abbr'] = $short; $comp['state_full'] = $long; }
					if (in_array('administrative_area_level_2', $types, true))   $comp['county']     = $long ?: $short;
					if (in_array('country', $types, true)) { $comp['country_full'] = $long ?: $short; $comp['country_abbr'] = $short ?: $long; }
					if (in_array('postal_code', $types, true))                   $comp['zip']        = $long ?: $short;
				}

				$base  = trim($comp['street_num'] . ' ' . $comp['route']);
				$sub   = trim((string)$comp['subpremise']);
				$prem  = trim((string)$comp['premise']);
				$floor = trim((string)$comp['floor']);

				$normalizeSubpremise = function(string $s): string {
					$s = preg_replace('/\s+/', ' ', trim($s));
					if ($s === '') return '';
					if (preg_match('/^#\s*\S+$/', $s)) return '#' . preg_replace('/^#\s*/', '', $s);
					if (preg_match('/^[0-9]+[A-Za-z]?$/', $s)) return '#' . $s;
					if (preg_match('/^(suite|ste|unit|apt|apartment|bldg)\b[\s\-#]*([\w\-# ]+)$/i', $s, $m)) {
						$label = ucfirst(strtolower($m[1]));
						$rest  = preg_replace('/\s+/', ' ', trim($m[2]));
						if (preg_match('/^[0-9]+[A-Za-z]?$/', $rest)) $rest = '#' . $rest;
						return $label . ' ' . $rest;
					}
					return $s;
				};

				$subNorm = $normalizeSubpremise($sub);
				$line1   = $base;
				if ($subNorm !== '') $line1 .= ' ' . $subNorm;
				if ($prem !== '')    $line1 .= ', ' . $prem;
				if ($floor !== '')   $line1 .= ', ' . $floor;
				$line1 = preg_replace('/\s+/', ' ', trim($line1));

				$google_info[$placeID]['street']       = $line1;
				$google_info[$placeID]['street_line1'] = $base;
				$google_info[$placeID]['street_line2'] = trim(($prem ? $prem : '') . ($floor ? ($prem ? ', ' : '') . $floor : ''));
				$google_info[$placeID]['suite']        = $subNorm;
				$google_info[$placeID]['city']         = $comp['city'];
				$google_info[$placeID]['state-abbr']   = $comp['state_abbr'];
				$google_info[$placeID]['state-full']   = $comp['state_full'];
				$google_info[$placeID]['zip']          = $comp['zip'];
				$google_info[$placeID]['county']       = $comp['county'];
				$google_info[$placeID]['country']      = $comp['country_abbr'] ?: $comp['country_full'];
				$google_info[$placeID]['lat']          = isset($gbp['location']['latitude'])  ? (float)$gbp['location']['latitude']  : null;
				$google_info[$placeID]['long']         = isset($gbp['location']['longitude']) ? (float)$gbp['location']['longitude'] : null;
				$google_info[$placeID]['hours']        = $gbp['regularOpeningHours'] ?? null;
				$google_info[$placeID]['current-hours'] = $gbp['currentOpeningHours'] ?? null;
			}

// 3) Only commit + advance the sync date when at least one Place ID fetched cleanly.
//    A fully-failed run must NOT zero the counts or stamp the date — doing exactly
//    that is what hid a year-long API-key outage by making frozen data look fresh.
			if ($fetched_ok > 0) {

				// On a fully-clean run, flag a large unexpected drop before overwriting the
				// stored total (the wrong/merged Place ID signature). Skipped on partial
				// failures, where a lower sum just means some PIDs didn't answer this run.
				if (empty($fetch_errors) && $old_review_total > 0 && $google_review_num > 0
					&& ($old_review_total - $google_review_num) >= 10
					&& ($old_review_total - $google_review_num) >= $old_review_total * 0.10) {
					bp_chron_a_notify_drop($customer_info, $old_review_total, $google_review_num);
				}

				$google_info['google-reviews'] = $google_review_num;
				if ($google_review_num > 0) {
					$google_info['google-rating'] = $google_rating / $google_review_num;
				}
				$google_info['date'] = $today;

				update_option('bp_gbp_update', $google_info, false);
				gbp_diff_vs_ci_and_notify($customer_info, $google_info, $placeIDs);

				if (empty($fetch_errors)) {
					bp_chron_a_clear_failure_state();                 // healthy run — reset the failure streak
				} else {
					bp_chron_a_notify_failure($customer_info, $fetch_errors, false); // partial: deduped heads-up
				}

			} else {
				// Every Place ID failed — leave the last-known-good data untouched and alert (deduped).
				// Hold off the next attempt for a full cadence so a permanently-broken Place ID (or a
				// dead key) can't hammer the billable API nightly. Cleared by the first clean run.
				update_option('bp_chron_a_retry_after', time() + ($days * DAY_IN_SECONDS), false);
				bp_chron_a_notify_failure($customer_info, $fetch_errors, true);
			}
		}
	}

// 4) Merge GBP into CI
	$primePID                   = $placeIDs[0] ?? null;
	$gbp_primary                = $primePID ? ($google_info[$primePID] ?? []) : [];
	list($ci_new, $merge_diffs) = ci_merge_gbp_into_ci($customer_info, $gbp_primary, $pid_sync);

// 5) Finalize derived fields (phone-format, default-loc) and prune trivial empties
	ci_finalize_fields($ci_new);

// 6) Build schema from final CI
	$schema = ci_build_schema($ci_new, $gbp_primary, $google_info, [
		'include_aggregate_rating' => true,
		'min_rating_value'         => 4.0,
	]);
	$ci_new['schema'] = $schema;

// 7) Save CI if changed
	if ($ci_new !== $customer_info) {
		update_customer_info($ci_new);
	}
}


/*--------------------------------------------------------------
# Chron A alerting — deduped, delivered via emailMe() (Brevo)
--------------------------------------------------------------*/

/**
 * Alert that the GBP sync is failing. Deduped to at most one email per site per
 * 3 days for as long as the failure streak lasts — so a fleet-wide outage sends a
 * handful of emails, not one per Place ID per run (the flood that got spam-filtered
 * and let a year-long outage go unnoticed). $critical = every Place ID failed and the
 * data is now frozen; false = a partial failure (some Place IDs still synced).
 */
function bp_chron_a_notify_failure(array $customer_info, array $errors, bool $critical): void {
	$now       = time();
	$failSince = (int) get_option('bp_chron_a_fail_since', 0);
	if ($failSince === 0) {
		update_option('bp_chron_a_fail_since', $now, false);
		$failSince = $now;
	}

	$lastAlert = (int) get_option('bp_chron_a_last_alert', 0);
	if ($now - $lastAlert < 3 * DAY_IN_SECONDS) return;   // already alerted within the window
	update_option('bp_chron_a_last_alert', $now, false);

	$site = str_replace('https://', '', get_bloginfo('url'));
	$days = max(1, (int) floor(($now - $failSince) / DAY_IN_SECONDS));

	$body  = '<p><strong>' . ($critical ? 'GBP sync is DOWN' : 'GBP sync partially failing')
	       . '</strong> on ' . esc_html($site) . '.</p>';
	if ($critical) {
		$body .= '<p>Every Place ID failed to fetch. Reviews, hours and NAP are frozen at the last good '
		       . 'values and the sync date was NOT advanced. Failing for ~' . $days . ' day(s).</p>';
	}
	$body .= '<pre style="white-space:pre-wrap">' . esc_html(implode("\n\n", $errors)) . '</pre>';

	emailMe(($critical ? '⚠️ GBP sync DOWN · ' : 'GBP sync partial-fail · ') . ($customer_info['name'] ?? $site), $body);
}

/**
 * Alert that the Google review count dropped unexpectedly (a large drop is the
 * classic wrong/merged Place ID signature, or a review purge). One-shot: once the
 * lower value is stored the next run's baseline matches it, so it won't re-fire
 * unless the count drops again.
 */
function bp_chron_a_notify_drop(array $customer_info, int $oldTotal, int $newTotal): void {
	$site  = str_replace('https://', '', get_bloginfo('url'));
	$body  = '<p>Google review count on <strong>' . esc_html($site) . '</strong> dropped from '
	       . $oldTotal . ' to ' . $newTotal . '.</p>';
	$body .= '<p>Small drops are normal (Google removes reviews); a large one can mean the stored Place ID '
	       . 'now points at a wrong/merged listing, or a review purge. Worth a quick check of the live listing.</p>';

	emailMe('GBP review count dropped · ' . ($customer_info['name'] ?? $site), $body);
}

/**
 * Clear the failure streak after a clean run. delete_option() is a safe no-op when
 * the options aren't set.
 */
function bp_chron_a_clear_failure_state(): void {
	delete_option('bp_chron_a_fail_since');
	delete_option('bp_chron_a_last_alert');
	delete_option('bp_chron_a_retry_after');
}
