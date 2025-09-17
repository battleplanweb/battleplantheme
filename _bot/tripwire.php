<?php
require_once __DIR__ . '/../_prewp/bot-helpers.php';

$ip   = $_SERVER['REMOTE_ADDR']      ?? '';
$ua   = $_SERVER['HTTP_USER_AGENT']  ?? '';
$host = $_SERVER['HTTP_HOST']        ?? '';
$uri  = $_SERVER['REQUEST_URI']      ?? '';
$ref  = $_SERVER['HTTP_REFERER']     ?? '';
$ts   = (string) time();

// 1) allow verified search engine bots
if ($ip && bp_is_verified_serp_bot($ip, $ua)) { http_response_code(204); exit; }

// 2) shared storage outside the theme (survives updates)
$BP_STORE   = dirname(__DIR__, 2) . '/bp-guard';   // /wp-content/bp-guard
$CACHE_DIR  = $BP_STORE . '/cache';
$CACHE_FILE = $CACHE_DIR . '/blocked_ips.txt';
$LOG_DIR    = $BP_STORE . '/logs';

@is_dir($BP_STORE)  ?: @mkdir($BP_STORE, 0775, true);
@is_dir($CACHE_DIR) ?: @mkdir($CACHE_DIR, 0775, true);
@is_dir($LOG_DIR)   ?: @mkdir($LOG_DIR, 0775, true);

// 3) bail early if already blocked (check shared cache)
if ($ip) {
	$in_cidr = function (string $ip, string $cidr): bool {
		if (!str_contains($cidr,'/')) return false;
		[$sub,$mask] = explode('/',$cidr,2);
		if (!filter_var($sub, FILTER_VALIDATE_IP) || !is_numeric($mask)) return false;
		$mask=(int)$mask; $ipl=ip2long($ip); $sbl=ip2long($sub);
		if ($ipl===false || $sbl===false || $mask<0 || $mask>32) return false;
		$ml = $mask===0 ? 0 : ((~0) << (32 - $mask)) & 0xFFFFFFFF;
		return (($ipl & $ml) === ($sbl & $ml));
	};
	// If already in local cache → notify central + fast exit
	if (is_file($CACHE_FILE) && is_readable($CACHE_FILE)) {
		$blocked=false;
		foreach (file($CACHE_FILE, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
			$line=trim($line); if ($line===''||$line[0]==='#') continue;
			$hit = filter_var($line, FILTER_VALIDATE_IP) ? hash_equals($ip,$line) : $in_cidr($ip,$line);
			if ($hit) { $blocked=true; break; }
		}
		if ($blocked) {
			// notify central just like guard does
			$CENTRAL_BLOCKED_URL = 'https://battleplanwebdesign.com/wp-content/blocked-notify.php';
			$BP_SECRET           = 'Vn8qkM2Z4yHsR1jPwA3tLf7bE6uXpD9c'; // same secret
			$site = $_SERVER['HTTP_HOST'] ?? '';
			$ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
			$ref  = $_SERVER['HTTP_REFERER'] ?? '';
			$ptr  = gethostbyaddr($ip) ?: '';
			$ts   = (string)time();
			$payload = $ip.'|'.$site.'|'.$ts;                // must match central
			$sig     = hash_hmac('sha256', $payload, $BP_SECRET);

			$ctx = stream_context_create(['http'=>[
				'method'=>'POST',
				'header'=>"Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
				'content'=>http_build_query([
					'ip'=>$ip,'site'=>$site,'ts'=>$ts,'sig'=>$sig,
					'ua'=>$ua,'ref'=>$ref,'ptr'=>$ptr,'msg'=>'blocked'
				]),
				'timeout'=>0.6
			]]);
			@file_get_contents($CENTRAL_BLOCKED_URL, false, $ctx);

			http_response_code(204); // honeypot can stay silent
			exit;
		}
	}
}

// 4) report to central (fire-and-forget)
if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
	$central = 'https://battleplanwebdesign.com/wp-content/add-ip.php';
	$secret  = defined('BP_HONEYPOT_SECRET') ? BP_HONEYPOT_SECRET : 'Vn8qkM2Z4yHsR1jPwA3tLf7bE6uXpD9c';
	$payload = $ip.'|'.$host.'|'.$uri.'|'.$ua.'|'.$ref.'|'.$ts;
	$sig     = hash_hmac('sha256', $payload, $secret);

	$ctx = stream_context_create(['http'=>[
		'method'  => 'POST',
		'header'  => "Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
		'content' => http_build_query(['ip'=>$ip,'site'=>$host,'uri'=>$uri,'ua'=>$ua,'ref'=>$ref,'ts'=>$ts,'sig'=>$sig]),
		'timeout' => 1.5
	]]);
	@file_get_contents($central, false, $ctx);

	// optional local breadcrumb (in shared logs dir)
	@file_put_contents($LOG_DIR . '/tripwire_hits.log', "$ts\t$ip\t$host\t$uri\n", FILE_APPEND | LOCK_EX);
}

// 5) local write-through so this site blocks instantly
if ($ip && is_dir($CACHE_DIR) && is_writable($CACHE_DIR)) {
	$existing = [];
	if (is_file($CACHE_FILE) && is_readable($CACHE_FILE)) {
		$existing = file($CACHE_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
	}
	if (!in_array($ip, $existing, true)) {
		array_unshift($existing, $ip);
		$body = implode("\n", $existing) . "\n";
		$tmp  = $CACHE_FILE . '.tmp';
		if (file_put_contents($tmp, $body, LOCK_EX) !== false && filesize($tmp) > 0) {
			@chmod($tmp, 0664);
			@rename($tmp, $CACHE_FILE);
		}
	}
}

// 6) always end fast
http_response_code(204);
exit;
