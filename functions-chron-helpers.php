<?php
/* Battle Plan Web Design Functions: Chron Helpers
 *
 * Shared helper functions used across chron files.
 * Included automatically by functions-chron.php before any chron runs.
 */


/*--------------------------------------------------------------
# Data Normalization
--------------------------------------------------------------*/

function ci_normalize_pids($raw): array {
	$pid = is_array($raw) ? $raw : (($raw === null || $raw === false) ? [] : [$raw]);
	$pid = array_map('strval', $pid);
	$pid = array_map('trim', $pid);
	$pid = array_filter($pid, fn($x) => $x !== '' && strlen($x) > 10);
	return array_values(array_unique($pid));
}


/*--------------------------------------------------------------
# Email Diff Helpers
--------------------------------------------------------------*/

function ci_email_diff(array $old, array $new, string $site_name): void {
	$diffs = [];
	$keys  = array_unique(array_merge(array_keys($old), array_keys($new)));

	foreach ($keys as $k) {
		$ov = $old[$k] ?? null;
		$nv = $new[$k] ?? null;
		if ($ov === $nv) continue;

		$toS = function($x) {
			if (is_array($x)) {
				$j = json_encode($x);
				return strlen($j) > 400 ? substr($j, 0, 400) . '…' : $j;
			}
			return (string)$x;
		};

		$diffs[$k] = ['old' => $toS($ov), 'new' => $toS($nv)];
	}

	if (!$diffs) return;

	$msg = "customer_info changes for {$site_name}:\n\n";
	foreach ($diffs as $k => $d) {
		$msg .= "• {$k}\n  - old: {$d['old']}\n  - new: {$d['new']}\n\n";
	}

	if (function_exists('emailMe')) { emailMe('customer_info updated - ' . $site_name, $msg); }
	else { error_log($msg); }
}

/**
 * Make values readable in diff emails.
 */
function _fmt_email_val($v): string {
	if (is_array($v)) {
		return rtrim(print_r($v, true));
	}

	$s = trim((string)$v);

	// Normalize fancy punctuation
	$s = strtr($s, [
		"\xE2\x80\x89" => ' ',  // thin space
		"\xE2\x80\x93" => '-',  // en dash
		"\xE2\x80\x94" => '-',  // em dash
	]);

	// If this looks like weekday hours, split at pipes to new lines
	if (preg_match('/\b(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/i', $s)) {
		$s = preg_replace('/\s*\|\s*/', "\n            ", $s);
	}

	return $s;
}


/*--------------------------------------------------------------
# GBP vs CI Diff + Notify
--------------------------------------------------------------*/

function gbp_diff_vs_ci_and_notify(array $ci, array $google_info, array $placeIDs): void {
	$primePID    = $placeIDs[0] ?? null;
	$gbp_primary = $primePID ? ($google_info[$primePID] ?? []) : [];

	$norm_str   = fn($v) => strtolower(trim(preg_replace('/\s+/', ' ', (string)$v)));
	$norm_phone = function($area, $phone) {
		$a = preg_replace('/\D+/', '', (string)$area);
		$p = preg_replace('/\D+/', '', (string)$phone);
		return (strlen($a) === 3 && strlen($p) === 7) ? $a . $p : preg_replace('/\D+/', '', $a . $p);
	};

	$diffs = [];
	$pairs = [
		'name'       => ['ci' => $ci['name']       ?? null, 'gbp' => $gbp_primary['name']       ?? null, 'norm' => 'str'],
		'street'     => ['ci' => $ci['street']     ?? null, 'gbp' => $gbp_primary['street']     ?? null, 'norm' => 'str'],
		'city'       => ['ci' => $ci['city']       ?? null, 'gbp' => $gbp_primary['city']       ?? null, 'norm' => 'str'],
		'state-abbr' => ['ci' => $ci['state-abbr'] ?? null, 'gbp' => $gbp_primary['state-abbr'] ?? null, 'norm' => 'upper'],
		'state-full' => ['ci' => $ci['state-full'] ?? null, 'gbp' => $gbp_primary['state-full'] ?? null, 'norm' => 'str'],
		'zip'        => ['ci' => $ci['zip']        ?? null, 'gbp' => $gbp_primary['zip']        ?? null, 'norm' => 'raw'],
	];

	foreach ($pairs as $label => $pair) {
		$ciV  = $pair['ci'];
		$gbpV = $pair['gbp'];
		$ciN  = $pair['norm'] === 'str'   ? $norm_str($ciV)
			  : ($pair['norm'] === 'upper' ? strtoupper(trim((string)$ciV))
			  : trim((string)$ciV));
		$gbpN = $pair['norm'] === 'str'   ? $norm_str($gbpV)
			  : ($pair['norm'] === 'upper' ? strtoupper(trim((string)$gbpV))
			  : trim((string)$gbpV));
		if ($gbpN !== '' && $ciN !== $gbpN) $diffs[$label] = ['current' => $ciV, 'gbp' => $gbpV];
	}

	$ciPhone  = $norm_phone($ci['area'] ?? '', $ci['phone'] ?? '');
	$gbpPhone = $norm_phone($gbp_primary['area'] ?? '', $gbp_primary['phone'] ?? '');
	if ($gbpPhone !== '' && $ciPhone !== $gbpPhone) {
		$diffs['phone_full'] = [
			'current' => trim(($ci['area'] ?? '') . ' ' . ($ci['phone'] ?? '')),
			'gbp'     => trim(($gbp_primary['area'] ?? '') . ' ' . ($gbp_primary['phone'] ?? '')),
		];
	}

	if (!$diffs) return;

	$hash = md5(json_encode($diffs));
	if ($hash === get_option('bp_diffhash_gbp_vs_ci')) return;
	update_option('bp_diffhash_gbp_vs_ci', $hash, false);

	$NL  = "<br>\n";
	$msg = "GBP vs customer_info differences for " . ($ci['name'] ?? '(site)') . ":" . $NL . $NL;

	foreach ($diffs as $k => $v) {
		$label = strtoupper(str_replace('_', ' ', $k));
		$cur   = _fmt_email_val($v['current'] ?? '');
		$gbp   = _fmt_email_val($v['gbp'] ?? '');
		$msg  .= "• {$label}{$NL}"
			  .  "  current: {$cur}{$NL}"
			  .  "  gbp:     {$gbp}{$NL}{$NL}";
	}

	if (function_exists('emailMe')) emailMe('GBP/customer_info discrepancy - ' . ($ci['name'] ?? ''), $msg);
}


/*--------------------------------------------------------------
# CI Merge + Finalize
--------------------------------------------------------------*/

function ci_merge_gbp_into_ci(array $ci, array $gbp_primary, bool $pid_sync): array {
	$map = [
		'name' => 'name', 'area' => 'area', 'phone' => 'phone',
		'street' => 'street', 'city' => 'city', 'state-abbr' => 'state-abbr',
		'state-full' => 'state-full', 'zip' => 'zip', 'lat' => 'lat', 'long' => 'long',
	];

	$diffs = [];
	$new   = $ci;

	foreach ($map as $ck => $gk) {
		$gv = $gbp_primary[$gk] ?? null;
		if ($gv === null || $gv === '') continue;

		$cv_exists = array_key_exists($ck, $new) && $new[$ck] !== '' && $new[$ck] !== null;
		if (!$cv_exists) { $new[$ck] = $gv; continue; }

		$different = ($ck === 'state-abbr')
			? (strtoupper(trim((string)$new[$ck])) !== strtoupper(trim((string)$gv)))
			: (($ck === 'lat' || $ck === 'long')
				? (abs((float)$new[$ck] - (float)$gv) > 0.001)
				: (strtolower(trim((string)$new[$ck])) !== strtolower(trim((string)$gv)))
			  );

		if ($different) {
			$diffs[$ck] = ['current' => $new[$ck], 'gbp' => $gv];
			if ($pid_sync) $new[$ck] = $gv;
		}
	}

	// Phone as a combined comparison/overlay
	$digits = fn($v) => preg_replace('/\D+/', '', (string)$v);
	$ci10   = (function($a, $p, $d) { $a = $d($a); $p = $d($p); return (strlen($a) === 3 && strlen($p) === 7) ? $a . $p : $d($a . $p); })($new['area'] ?? '', $new['phone'] ?? '', $digits);
	$gbp10  = (function($a, $p, $d) { $a = $d($a); $p = $d($p); return (strlen($a) === 3 && strlen($p) === 7) ? $a . $p : $d($a . $p); })($gbp_primary['area'] ?? '', $gbp_primary['phone'] ?? '', $digits);

	if ($gbp10 !== '' && $ci10 !== $gbp10) {
		$diffs['phone_full'] = [
			'current' => trim(($new['area'] ?? '') . ' ' . ($new['phone'] ?? '')),
			'gbp'     => trim(($gbp_primary['area'] ?? '') . ' ' . ($gbp_primary['phone'] ?? '')),
		];
		if ($pid_sync) { $new['area'] = $gbp_primary['area']; $new['phone'] = $gbp_primary['phone']; }
	}

	// Hours: fill if missing; overwrite if pid_sync and different
	$ciHoursDesc  = is_array($new['current-hours'] ?? null) ? implode(' | ', (array)$new['current-hours']) : (string)($new['current-hours'] ?? '');
	$gbpHoursDesc = !empty($gbp_primary['current-hours']['weekdayDescriptions'])
		? implode(' | ', (array)$gbp_primary['current-hours']['weekdayDescriptions'])
		: '';

	if ($gbpHoursDesc !== '' && trim($ciHoursDesc) !== trim($gbpHoursDesc)) {
		$diffs['hours'] = ['current' => $ciHoursDesc, 'gbp' => $gbpHoursDesc];
		if ($pid_sync) {
			if (!empty($gbp_primary['hours']))         $new['hours'] = $gbp_primary['hours'];
			if (!empty($gbp_primary['current-hours'])) $new['current-hours'] = $gbp_primary['current-hours']['weekdayDescriptions'] ?? $gbp_primary['current-hours'];
		}
	} else {
		if (!isset($new['hours']) && !empty($gbp_primary['hours'])) $new['hours'] = $gbp_primary['hours'];
		if (!isset($new['current-hours']) && !empty($gbp_primary['current-hours']['weekdayDescriptions']))
			$new['current-hours'] = $gbp_primary['current-hours']['weekdayDescriptions'];
	}

	// Ensure hours always get seeded into CI at least once
	if (empty($new['hours']) && !empty($gbp_primary['hours'])) {
		$new['hours'] = $gbp_primary['hours'];
	}

	if (empty($new['current-hours'])) {
		if (!empty($gbp_primary['current-hours']['weekdayDescriptions'])) {
			$new['current-hours'] = $gbp_primary['current-hours']['weekdayDescriptions'];
		} elseif (!empty($gbp_primary['current-hours']) && is_array($gbp_primary['current-hours'])) {
			$new['current-hours'] = $gbp_primary['current-hours'];
		}
	}

	return [$new, $diffs];
}

function ci_finalize_fields(array &$ci): void {
	$a = preg_replace('/\D+/', '', (string)($ci['area'] ?? ''));
	$p = preg_replace('/\D+/', '', (string)($ci['phone'] ?? ''));

	if (strlen($a) === 3 && strlen($p) === 7) {
		$ci['phone-format'] = sprintf('(%s) %s-%s', $a, substr($p, 0, 3), substr($p, 3));
	} else {
		unset($ci['phone-format']);
	}

	$city = trim((string)($ci['city'] ?? ''));
	$st   = trim((string)($ci['state-abbr'] ?? ''));
	if ($city !== '' && $st !== '') $ci['default-loc'] = "$city, $st";
	else unset($ci['default-loc']);

	// Prune trivial empties
	foreach ($ci as $k => $v) {
		if ($v === null) { unset($ci[$k]); continue; }
		if (is_string($v) && trim($v) === '') { unset($ci[$k]); continue; }
		if (is_array($v)) {
			$trimmed = array_filter(
				array_map(fn($x) => is_string($x) ? trim($x) : $x, $v),
				fn($x) => $x !== '' && $x !== null && $x !== []
			);
			if ($trimmed === []) unset($ci[$k]);
		}
	}
}


/*--------------------------------------------------------------
# Hours Builder
--------------------------------------------------------------*/

function ci_build_hours($periods, $wkdesc): array {
	$res   = ['compact' => '', 'openingHoursSpecification' => []];
	$byDay = array_fill(0, 7, []);

	$fmtTime = function(array $node): ?string {
		if (isset($node['time']) && preg_match('/^\d{3,4}$/', $node['time'])) {
			$t = str_pad($node['time'], 4, '0', STR_PAD_LEFT);
			return substr($t, 0, 2) . ':' . substr($t, 2, 2);
		}
		if (isset($node['hour'])) {
			$h = (int)$node['hour'];
			$m = isset($node['minute']) ? (int)$node['minute'] : 0;
			return sprintf('%02d:%02d', $h, $m);
		}
		return null;
	};

	if (is_array($periods)) {
		foreach ($periods as $p) {
			if (!isset($p['open'])) continue;
			$od = isset($p['open']['day']) ? (int)$p['open']['day'] : null;
			$ot = $fmtTime($p['open']);
			if ($od === null || $ot === null) continue;
			if (!isset($p['close'])) { $byDay[$od][] = ['00:00', '23:59']; continue; }
			$cd = isset($p['close']['day']) ? (int)$p['close']['day'] : $od;
			$ct = $fmtTime($p['close']);
			if ($ct === null) continue;
			if ($cd === $od) $byDay[$od][] = [$ot, $ct];
			else { $byDay[$od][] = [$ot, '23:59']; $byDay[$cd][] = ['00:00', $ct]; }
		}
	}

	$any = false;
	foreach ($byDay as $slots) { if (!empty($slots)) { $any = true; break; } }
	if (!$any) return $res;

	$abbrs  = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
	$days   = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
	$order  = [1, 2, 3, 4, 5, 6, 0];
	$groups = [];

	foreach ($order as $i => $gDay) {
		if (!empty($byDay[$gDay])) {
			[$from, $to] = $byDay[$gDay][0];
			$groups["$from-$to"][] = $days[$i];
		}
	}

	foreach ($groups as $range => $dows) {
		[$opens, $closes] = explode('-', $range);
		$res['openingHoursSpecification'][] = [
			'@type'      => 'OpeningHoursSpecification',
			'dayOfWeek'  => $dows,
			'opens'      => $opens,
			'closes'     => $closes,
		];
	}

	$schemaParts = [];
	foreach ($order as $i => $gDay) {
		if (!empty($byDay[$gDay])) {
			[$from, $to] = $byDay[$gDay][0];
			$schemaParts[] = $abbrs[$i] . ' ' . $from . '-' . $to;
		}
	}

	$res['compact'] = implode(' ', $schemaParts);
	return $res;
}


/*--------------------------------------------------------------
# Schema Builder
--------------------------------------------------------------*/

function ci_build_schema(array $ci, array $gbp_primary = [], array $google_info = [], array $opts = []): array {
	$opts += [
		'include_aggregate_rating' => true,
		'min_rating_value'         => 4.0,
	];

	$url  = get_bloginfo('url');
	$name = $ci['name'] ?? get_bloginfo('name');
	$bt   = $ci['business-type'] ?? '';
	$type = is_array($bt) ? ($bt[0] ?? '') : $bt;
	$type = trim($type) === '' ? 'LocalBusiness' : preg_replace('/\s+/', '', $type);

	if (!empty($ci['site-type']) && strtolower((string)$ci['site-type']) === 'hvac') {
		$type = 'HVACBusiness';
	}

	$email  = $ci['email'] ?? null;
	$digits = fn($v) => preg_replace('/\D+/', '', (string)$v);
	$a      = $digits($ci['area'] ?? '');
	$p      = $digits($ci['phone'] ?? '');
	if ($a === '' && strlen($p) === 10) { $a = substr($p, 0, 3); $p = substr($p, 3); }
	$telephone = (strlen($a) === 3 && strlen($p) === 7) ? ('+1' . $a . $p) : (($a . $p) ? '+1' . $a . $p : null);

	$addr = array_filter([
		'streetAddress'   => $ci['street']     ?? '',
		'addressLocality' => $ci['city']       ?? '',
		'addressRegion'   => $ci['state-abbr'] ?? ($ci['state-full'] ?? ''),
		'postalCode'      => $ci['zip']        ?? '',
		'addressCountry'  => 'US',
	], fn($v) => $v !== '');
	$addressNode = $addr ? (['@type' => 'PostalAddress'] + $addr) : null;

	$geo = (isset($ci['lat'], $ci['long']) && $ci['lat'] !== '' && $ci['long'] !== '')
		? ['@type' => 'GeoCoordinates', 'latitude' => (float)$ci['lat'], 'longitude' => (float)$ci['long']]
		: null;

	$hours = ci_build_hours($ci['hours']['periods'] ?? null, $ci['current-hours'] ?? null);

	$same = [];
	foreach (['facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'pinterest'] as $s) {
		if (!empty($ci[$s])) $same[] = $ci[$s];
	}

	$areaServed = [];
	if (!empty($ci['service-areas']) && is_array($ci['service-areas'])) {
		$seen = [];
		$ci['service-areas'][] = [$ci['city'], $ci['state-full']];
		foreach ($ci['service-areas'] as $city) {
			if (!isset($city[0], $city[1])) continue;
			$c     = preg_replace('/\s+/', ' ', trim((string)$city[0]));
			$st    = preg_replace('/\s+/', ' ', trim((string)$city[1]));
			if ($c === '' || $st === '') continue;
			$label = "$c, $st";
			if (isset($seen[$label])) continue;
			$seen[$label]  = 1;
			$citySlug      = rawurlencode(str_replace(' ', '_', $c));
			$stateSlug     = rawurlencode(str_replace(' ', '_', $st));
			$areaServed[]  = [
				'@type'  => 'AdministrativeArea',
				'name'   => $label,
				'sameAs' => "https://en.wikipedia.org/wiki/{$citySlug},_{$stateSlug}",
			];
		}
	}

	$catalogName = 'Our Services';
	if (!empty($ci['site-type']) && strtolower((string)$ci['site-type']) === 'hvac') {
		$catalogName = 'HVAC Services';
	} elseif (is_array($bt) && !empty($bt[1])) {
		$catalogName = trim((string)$bt[1]);
	}

	$serviceNames = array_values(array_unique(array_filter(array_map('trim', (array)($ci['service-names'] ?? [])))));
	if (empty($serviceNames)) $serviceNames = [$catalogName];

	$offers = [];
	foreach ($serviceNames as $svc) {
		if ($svc === '') continue;
		$offers[] = [
			'@type'       => 'Offer',
			'itemOffered' => [
				'@type'    => 'Service',
				'name'     => $svc,
				'provider' => ['@id' => home_url('#organization')],
			],
		];
	}
	$hasOfferCatalog = $offers ? [
		'@type'           => 'OfferCatalog',
		'@id'             => home_url('#offer-catalog'),
		'name'            => $catalogName,
		'itemListElement' => $offers,
	] : null;

	$logo = null;
	if (!empty($ci['logo'])) {
		$logo = is_array($ci['logo']) ? $ci['logo'] : ['@type' => 'ImageObject', 'url' => $ci['logo']];
	}

	$additionalType = '';
	if (function_exists('ci_additional_type_url')) {
		$friendly       = is_array($bt) ? ($bt[1] ?? '') : (string)$bt;
		$additionalType = ci_additional_type_url($type, $friendly, $ci['site-type'] ?? '');
	}

	if (empty($google_info)) {
		$google_info = get_option('bp_gbp_update') ?: [];
	}
	$aggregateRating = null;
	if ($opts['include_aggregate_rating'] && !empty($ci['pid'])) {
		$pids  = is_array($ci['pid']) ? $ci['pid'] : [$ci['pid']];
		$prime = $pids[0] ?? null;
		if ($prime && isset($google_info[$prime])) {
			$rv = (float)($google_info[$prime]['google-rating']  ?? 0);
			$rc = (int)  ($google_info[$prime]['google-reviews'] ?? 0);
			if ($rc > 0 && $rv >= $opts['min_rating_value']) {
				$aggregateRating = [
					'@type'       => 'AggregateRating',
					'ratingValue' => round($rv, 1),
					'bestRating'  => 5,
					'worstRating' => 1,
					'ratingCount' => $rc,
				];
			}
		}
	}

	$priceRange = $ci['price-range'] ?? '$$';
	$hasMap     = (!empty($ci['cid'])) ? ('https://maps.google.com/?cid=' . rawurlencode((string)$ci['cid'])) : null;

	$schema = [
		'@context'   => 'https://schema.org',
		'@type'      => $type,
		'name'       => $name,
		'url'        => $url,
		'priceRange' => $priceRange,
	];

	if ($email)           $schema['email']     = $email;
	if ($telephone)       $schema['telephone'] = $telephone;
	if ($addressNode)     $schema['address']   = $addressNode;
	if ($geo)             $schema['geo']       = $geo;

	if (!empty($hours['openingHoursSpecification'])) {
		$schema['openingHoursSpecification'] = $hours['openingHoursSpecification'];
		if (!empty($hours['compact'])) $schema['openingHours'] = $hours['compact'];
	}

	if ($same)            $schema['sameAs']          = $same;
	if ($areaServed)      $schema['areaServed']      = $areaServed;
	if ($hasOfferCatalog) $schema['hasOfferCatalog'] = $hasOfferCatalog;
	if ($aggregateRating) $schema['aggregateRating'] = $aggregateRating;
	if ($logo)            $schema['logo']            = $logo;
	if ($additionalType)  $schema['additionalType']  = $additionalType;
	if ($hasMap)          $schema['hasMap']          = $hasMap;

	return $schema;
}

function bp_ymd_to_ts(string $ymd): int {
    $dt = DateTime::createFromFormat('Ymd', $ymd);
    return $dt ? $dt->getTimestamp() : 0;
}