<?php
/* Battle Plan Web Design Functions: Chron A — GBP + CI Sync */

require_once get_template_directory() . '/vendor/autoload.php';

function bp_run_chron_gbp(bool $force = false): void {

	$google_info  = get_option('bp_gbp_update') ?: [];
	$customer_info = customer_info();
	$pid_sync      = filter_var($customer_info['pid-sync'] ?? false, FILTER_VALIDATE_BOOL);
	$placeIDs      = ci_normalize_pids($customer_info['pid'] ?? []);

	if (!empty($placeIDs)) {

// 1) Decide whether to hit the Places API based on review count thresholds
		$today      = strtotime(date("F j, Y"));
		$daysSince  = ($today - (int)($google_info['date'] ?? 0)) / 86400;
		$reviewCount = (int)($google_info['google-reviews'] ?? 0);
		$thresholds  = [1000 => 1, 500 => 2, 250 => 3, 125 => 4, 75 => 5, 50 => 6];
		$days        = 7;

		foreach ($thresholds as $limit => $val) {
			$days = ($reviewCount >= $limit) ? $val : $days;
		}

		if ($force === true || $daysSince > $days) {
			
			update_option('bp_chron_a_api_time', time()); // timestamp of actual API hit

// 2) Fetch GBP data for each Place ID
			$google_rating     = 0.0;
			$google_review_num = 0;

			foreach ($placeIDs as $placeID) {
				if (strlen($placeID) <= 10) continue;

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

				if ($cerr && function_exists('emailMe')) {
					emailMe('Chron A - Places API cURL Error - ' . $customer_info['name'], "PID: $placeID\nError: $cerr $cerrm");
					continue;
				}

				if (($http < 200 || $http >= 300) && function_exists('emailMe')) {
					emailMe('Chron A - Places API HTTP Error - ' . $customer_info['name'], "PID: $placeID\nHTTP: $http\nBody:\n" . $result);
					continue;
				}

				$gbp = json_decode($result, true);

				if (!is_array($gbp) && function_exists('emailMe')) {
					emailMe('Chron A - Places API JSON Error - ' . $customer_info['name'], "PID: $placeID\nBody:\n" . $result);
					continue;
				}

				if (isset($gbp['error']) && function_exists('emailMe')) {
					emailMe('Chron A - Places API Error - ' . $customer_info['name'], print_r($gbp['error'], true) . "\n\nFull response:\n" . $result);
					continue;
				}

				$google_info[$placeID]['utcOffsetMinutes'] = $gbp['utcOffsetMinutes'] ?? null;

				$urc = isset($gbp['userRatingCount']) ? (int) $gbp['userRatingCount'] : 0;
				$rat = isset($gbp['rating']) ? (float) $gbp['rating'] : 0.0;
				$google_info[$placeID]['google-reviews'] = $urc;
				$google_info[$placeID]['google-rating']  = $rat;
				$google_review_num += $urc;
				$google_rating     += ($rat * $urc);

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

			$google_info['google-reviews'] = $google_review_num;
			if ($google_review_num > 0) {
				$google_info['google-rating'] = $google_rating / $google_review_num;
			}
			$google_info['date'] = $today;

// 3) Save GBP data and notify of any differences vs customer_info
			update_option('bp_gbp_update', $google_info, false);
			gbp_diff_vs_ci_and_notify($customer_info, $google_info, $placeIDs);
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
