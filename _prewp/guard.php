<?php
/**
 * BP Pre-WP Guard (central list + local cache)
 * - Uses /wp-content/bp-guard/... so theme updates never overwrite runtime files
 * - Sets _IS_SERP_BOT early and lets verified search engines through
 */

/* ---------- storage outside the theme (survives updates) ---------- */
$BP_STORE   = dirname(__DIR__, 3) . '/bp-guard';     // /wp-content/bp-guard
$CACHE_DIR  = $BP_STORE . '/cache';
$CACHE_FILE = $CACHE_DIR . '/blocked_ips.txt';
$NOTIFY_DIR = $BP_STORE . '/notify';

@is_dir($BP_STORE)  ?: @mkdir($BP_STORE, 0775, true);
@is_dir($CACHE_DIR) ?: @mkdir($CACHE_DIR, 0775, true);
@is_dir($NOTIFY_DIR)?: @mkdir($NOTIFY_DIR, 0775, true);

/* one-time migration from old in-theme cache (if it existed) */
$OLD_CACHE_FILE = __DIR__ . '/cache/blocked_ips.txt';
if (!is_file($CACHE_FILE) && is_file($OLD_CACHE_FILE) && is_readable($OLD_CACHE_FILE)) {
	$body = file_get_contents($OLD_CACHE_FILE);
	if ($body !== '' && $body !== false) {
		$tmp = $CACHE_FILE . '.tmp';
		if (file_put_contents($tmp, $body, LOCK_EX) !== false) { @chmod($tmp, 0664); @rename($tmp, $CACHE_FILE); }
	}
}

/* skip in CLI/tests */
if (PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI) || getenv('WP_PHPUNIT__TESTS_CONFIG')) return;

/* ---------- config (do NOT reassign $CACHE_DIR/$CACHE_FILE) ---------- */
$CENTRAL_READ_URL = 'https://battleplanwebdesign.com/wp-content/master_blocked_ips.txt';
$CACHE_MAX_AGE    = 300;           // 5 min
$CACHE_MAX_SIZE   = 5*1024*1024;   // 5 MB

/* ---------- resolve client IP ---------- */
$ip = !empty($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP']
	: (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
	: ($_SERVER['REMOTE_ADDR'] ?? ''));
$ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '';
if ($ip === '') return;

/* ---------- set _IS_SERP_BOT early and allow ---------- */
$UA = $_SERVER['HTTP_USER_AGENT'] ?? '';
require_once __DIR__ . '/bot-helpers.php';
$__serp = bp_is_verified_serp_bot($ip, $UA);

!defined('_IS_SERP_BOT') && define('_IS_SERP_BOT', $__serp);

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

	@is_dir($NOTIFY_DIR) ?: @mkdir($NOTIFY_DIR, 0775, true);

	// per (site, ip) counter state
	$key       = $NOTIFY_DIR . '/' . md5($site.'|'.$ip) . '.json';
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


// Determine outcome + stats for check-in
$site   = $_SERVER['HTTP_HOST'] ?? '';
$ok     = isset($lines) && is_array($lines) && count($lines) > 0; // wrote non-empty list
$ips_ct = 0;
if (is_file($CACHE_FILE) && is_readable($CACHE_FILE)) {
	$ips_ct = count(file($CACHE_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
}
$cache_ts = is_file($CACHE_FILE) ? (int)@filemtime($CACHE_FILE) : 0;

// Only check-in when we actually tried (stale true)
if (!empty($site) && $stale) {
	$ts  = (string)time();
	$msg = $ok ? 'refresh-ok' : 'refresh-fail'; // you can include HTTP code text if you capture it
	$payload = $site.'|'.($ok?'ok':'fail').'|'.$ips_ct.'|'.$cache_ts.'|'.$ts;
	$sig = hash_hmac('sha256', $payload, defined('BP_HONEYPOT_SECRET') ? BP_HONEYPOT_SECRET : 'Vn8qkM2Z4yHsR1jPwA3tLf7bE6uXpD9c');

	$check = 'https://battleplanwebdesign.com/wp-content/checkin.php';
	$ctx = stream_context_create(['http'=>[
		'method'=>'POST',
		'header'=>"Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
		'content'=>http_build_query([
			'site'=>$site,
			'status'=>$ok?'ok':'fail',
			'ips'=>$ips_ct,
			'cache_ts'=>$cache_ts,
			'ts'=>$ts,
			'sig'=>$sig,
			'msg'=>$msg
		]),
		'timeout'=>0.5
	]]);
	@file_get_contents($check, false, $ctx); // fire-and-forget
}

