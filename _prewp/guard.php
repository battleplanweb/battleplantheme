<?php
/**
 * BP Pre-WP Guard (central list + local cache)
 */
if (PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI) || getenv('WP_PHPUNIT__TESTS_CONFIG')) return;

/* --- Config --- */
$CENTRAL_READ_URL = 'https://battleplanwebdesign.com/wp-content/master_blocked_ips.txt'; // <— YOUR central read file
$CACHE_DIR        = __DIR__ . '/cache';
$CACHE_FILE       = $CACHE_DIR . '/blocked_ips.txt';
$CACHE_MAX_AGE    = 300;           // 5 min
$CACHE_MAX_SIZE   = 5*1024*1024;   // 5 MB

/* --- Resolve client IP (trust CF / XFF first if present) --- */
$ip = !empty($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP']
	: (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
	: ($_SERVER['REMOTE_ADDR'] ?? ''));
$ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '';
if ($ip === '') return;

$UA = $_SERVER['HTTP_USER_AGENT'] ?? '';

require_once __DIR__ . '/bot-helpers.php';
$__serp = bp_is_verified_serp_bot($ip, $UA);
!defined('_IS_SERP_BOT') && define('_IS_SERP_BOT', $__serp);

// skip all checks for verified search-engine bots
if (_IS_SERP_BOT) return;


$in_cidr = function(string $ip, string $cidr): bool {
	if (strpos($cidr,'/')===false) return false;
	[$sub,$mask]=explode('/',$cidr,2);
	if (!filter_var($sub,FILTER_VALIDATE_IP) || !is_numeric($mask)) return false;
	if (str_contains($ip,':')) { // IPv6
		$ipb=inet_pton($ip); $nb=inet_pton($sub); $mask=(int)$mask;
		if ($ipb===false||$nb===false||$mask<0||$mask>128) return false;
		$bytes=intdiv($mask,8); $bits=$mask%8;
		if ($bytes && substr($ipb,0,$bytes)!==substr($nb,0,$bytes)) return false;
		if ($bits===0) return true;
		$mb=chr(0xFF & (~((1<<(8-$bits))-1)));
		return (ord($ipb[$bytes])&ord($mb))===(ord($nb[$bytes])&ord($mb));
	}
	$ipl=ip2long($ip); $nl=ip2long($sub); $mask=(int)$mask;
	if ($ipl===false||$nl===false||$mask<0||$mask>32) return false;
	$ml = $mask===0 ? 0 : ((~0) << (32-$mask)) & 0xFFFFFFFF;
	return (($ipl & $ml) === ($nl & $ml));
};
foreach ($allow as $a) {
	if ($a === $ip || (str_contains($a,'/') && $in_cidr($ip,$a))) { return; }
}

/* --- Ensure cache; read quickly --- */
@is_dir($CACHE_DIR) ?: @mkdir($CACHE_DIR, 0775, true);
$cache_exists = is_file($CACHE_FILE) && is_readable($CACHE_FILE) && filesize($CACHE_FILE) <= $CACHE_MAX_SIZE;
$lines = $cache_exists ? @file($CACHE_FILE, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) : [];

/* --- Match (CIDR or exact) --- */
$match = false;
if ($lines) {
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line==='' || $line[0]==='#') continue;
		$match = (strpos($line,'/')!==false) ? $in_cidr($ip,$line) : hash_equals($line,$ip);
		if ($match) break;
	}
}

/* --- Block if matched --- */
if ($match) {
	if (!headers_sent()) {
		header('Content-Type: text/plain; charset=UTF-8');
		header('Cache-Control: no-store');
		header('X-BP-Guard: blocked');
		http_response_code(403);
	}			
	
	/* ---- CENTRAL BLOCKED NOTIFY (5 hits → send, then 1h cooldown) ---- */
	$notify_url = 'https://battleplanwebdesign.com/wp-content/blocked-notify.php';
	$secret     = 'Vn8qkM2Z4yHsR1jPwA3tLf7bE6uXpD9c';
	$site       = $_SERVER['HTTP_HOST'] ?? '';
	$ua         = $_SERVER['HTTP_USER_AGENT'] ?? '';
	$uri        = $_SERVER['REQUEST_URI'] ?? '';
	$now        = time();

	$nd = __DIR__ . '/notify';
	@is_dir($nd) ?: @mkdir($nd, 0775, true);

	// per (site, ip) counter state
	$key       = $nd . '/' . md5($site.'|'.$ip) . '.json';
	$state     = ['hits'=>0, 'last'=>0]; // last = last send timestamp
	if (is_file($key) && is_readable($key)) {
		$j = @json_decode((string)@file_get_contents($key), true);
		if (is_array($j) && isset($j['hits'], $j['last'])) $state = ['hits'=>(int)$j['hits'], 'last'=>(int)$j['last']];
	}

	// if cooldown (1h) has passed since last send, reset hit counter
	if ($now - $state['last'] >= 3600) $state['hits'] = 0;

	// increment for this block event
	$state['hits']++;

	// send when 5th hit occurs (then start 1h cooldown)
	if ($state['hits'] >= 5) {
		$ts  = (string)$now;
		$pl  = $ip.'|'.$site.'|'.$uri.'|'.$ua.'|'.$ts;
		$sig = hash_hmac('sha256', $pl, $secret);

		$ctx = stream_context_create(['http'=>[
			'method'=>'POST',
			'header'=>"Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
			'content'=>http_build_query(['ip'=>$ip,'site'=>$site,'uri'=>$uri,'ua'=>$ua,'ts'=>$ts,'sig'=>$sig]),
			'timeout'=>0.3
		]]);
		@file_get_contents($notify_url, false, $ctx);

		// start cooldown window
		$state['last'] = $now;
		$state['hits'] = 0;
	}

	// persist state
	@file_put_contents($key, json_encode($state), LOCK_EX);
	/* ---- /CENTRAL BLOCKED NOTIFY ---- */
	
	exit;
}

/* --- Stale-While-Revalidate (never blocks) --- */
$stale = !$cache_exists || (time() - @filemtime($CACHE_FILE) > $CACHE_MAX_AGE);
if ($stale && $CENTRAL_READ_URL) {
	$ctx = stream_context_create(['http'=>[
		'method'=>'GET','timeout'=>0.3,'follow_location'=>1,'max_redirects'=>2,
		'header'=>"Connection: close\r\nUser-Agent: BP-Guard/1.0\r\n"
	]]);
	$body = @file_get_contents($CENTRAL_READ_URL, false, $ctx);
	if ($body!==false && $body!=='') {
		$raw = array_map('trim', explode("\n",$body));
		$keep = [];
		foreach ($raw as $l) {
			if ($l==='' || $l[0]==='#') continue;
			if (filter_var($l, FILTER_VALIDATE_IP) || strpos($l,'/')!==false) $keep[] = $l;
		}
		$keep = array_values(array_unique($keep));
		$tmp = $CACHE_FILE.'.tmp';
		(@file_put_contents($tmp, implode("\n",$keep)."\n", LOCK_EX)!==false) ? @rename($tmp, $CACHE_FILE) : null;
	}
}
