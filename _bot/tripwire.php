<?php
// --- bail if already blocked (uses local cache; no network) ---
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($ip) {
	$cache = __DIR__.'/../_prewp/cache/blocked_ips.txt';
	$in_cidr = function (string $ip, string $cidr): bool {
		if (strpos($cidr,'/')===false) return false;
		[$sub,$mask]=explode('/',$cidr,2);
		if (!filter_var($sub,FILTER_VALIDATE_IP) || !is_numeric($mask)) return false;
		$mask=(int)$mask; $ipl=ip2long($ip); $sbl=ip2long($sub);
		if ($ipl===false||$sbl===false||$mask<0||$mask>32) return false;
		$ml = $mask===0 ? 0 : ((~0) << (32 - $mask)) & 0xFFFFFFFF;
		return (($ipl & $ml) === ($sbl & $ml));
	};
	if (is_file($cache) && is_readable($cache)) {
		$blocked=false;
		foreach (file($cache, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
			$line=trim($line); if ($line===''||$line[0]==='#') continue;
			$blocked = (filter_var($line, FILTER_VALIDATE_IP) ? hash_equals($ip,$line) : $in_cidr($ip,$line));
			if ($blocked) break;
		}
		if ($blocked) { http_response_code(204); exit; } // already blocked → do nothing
	}
}

// throttle repeats per (ip, hour) so we don't re-report constantly
$nd = __DIR__ . '/notify'; @is_dir($nd) ?: @mkdir($nd, 0775, true);
$key = $nd . '/' . md5($ip.'|'.gmdate('Y-m-d-H')) . '.lock';
if (is_file($key)) { http_response_code(204); exit; }
@touch($key);

$ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
$uri  = $_SERVER['REQUEST_URI'] ?? '';
$ref  = $_SERVER['HTTP_REFERER'] ?? '';
$ts   = (string)time();

if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
	$central = 'https://battleplanwebdesign.com/wp-content/add-ip.php'; // central writer
	$secret  = 'Vn8qkM2Z4yHsR1jPwA3tLf7bE6uXpD9c'; // shared secret
	$payload = $ip.'|'.$host.'|'.$uri.'|'.$ua.'|'.$ref.'|'.$ts;
	$sig     = hash_hmac('sha256', $payload, $secret);

	$ctx = stream_context_create(['http'=>[
		'method'=>'POST',
		'header'=>"Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
		'content'=>http_build_query(['ip'=>$ip,'site'=>$host,'uri'=>$uri,'ua'=>$ua,'ref'=>$ref,'ts'=>$ts,'sig'=>$sig]),
		'timeout'=>1.5
	]]);
	@file_get_contents($central, false, $ctx);

	// Optional breadcrumb
	@file_put_contents(__DIR__ . '/local_hits.txt', "$ts\t$ip\t$host\t$uri\n", FILE_APPEND|LOCK_EX);
}

http_response_code(204);
exit;
