<?php
/* ---------- storage outside the theme ---------- */
$BP_STORE   = dirname(__DIR__, 3) . '/bp-guard';
$CACHE_DIR  = $BP_STORE . '/cache';
$CACHE_FILE = $CACHE_DIR . '/blocked_ips.txt';

@is_dir($BP_STORE)   ?: @mkdir($BP_STORE,  0775, true);
@is_dir($CACHE_DIR)  ?: @mkdir($CACHE_DIR, 0775, true);





/* ---------- remove unused diag/notify (one-shot) ---------- */
$__bp_rm = function(string $dir) use ($BP_STORE) {
	$real = realpath($dir) ?: $dir;
	if (!is_dir($real)) return;
	// safety: must be inside $BP_STORE
	$root = realpath($BP_STORE) ?: $BP_STORE;
	if (strpos($real, $root)!==0) return;

	$it = @scandir($real) ?: [];
	foreach ($it as $f) {
		if ($f==='.' || $f==='..') continue;
		$p = $real . DIRECTORY_SEPARATOR . $f;
		is_dir($p) ? $__bp_rm($p) : @unlink($p);
	}
	@rmdir($real);
};

	$__bp_rm($BP_STORE . '/diag');
	$__bp_rm($BP_STORE . '/notify');





$STATE_FILE = $BP_STORE . '/state.json';
$STATE = [];
if (is_file($STATE_FILE) && is_readable($STATE_FILE)) {
	$STATE = @json_decode((string)@file_get_contents($STATE_FILE), true) ?: [];
}
$NAME = isset($STATE['name']) ? $STATE['name'] : 'n/a';
$BP_VER = isset($STATE['framework']) ? $STATE['framework'] : 'n/a';
$HITS_WEEK = isset($STATE['hits_week']) ? $STATE['hits_week'] : 'n/a';
$HITS_MONTH = isset($STATE['hits_month']) ? $STATE['hits_month'] : 'n/a';
$HITS_QUARTER = isset($STATE['hits_quarter']) ? $STATE['hits_quarter'] : 'n/a';
$HITS_YEAR = isset($STATE['hits_year']) ? $STATE['hits_year'] : 'n/a';
$MOBILE_SPEED = isset($STATE['mobile_speed']) ? $STATE['mobile_speed'] : 'n/a';
$DESKTOP_SPEED = isset($STATE['desktop_speed']) ? $STATE['desktop_speed'] : 'n/a';

/* ---------- skip CLI/tests ---------- */
if (PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI) || getenv('WP_PHPUNIT__TESTS_CONFIG')) return;

/* ---------- config ---------- */
$CENTRAL_READ_URL    = 'https://battleplanwebdesign.com/wp-content/master_blocked_ips.txt';
$CENTRAL_CHECKIN_URL = 'https://battleplanwebdesign.com/wp-content/checkin.php';
$CENTRAL_BLOCKED_URL = 'https://battleplanwebdesign.com/wp-content/blocked-notify.php';
$BP_SECRET           = 'Vn8qkM2Z4yHsR1jPwA3tLf7bE6uXpD9c';

$CACHE_MAX_AGE  = 300;           // seconds
$CACHE_MAX_SIZE = 5*1024*1024;

/* ---------- resolve IP + SERP exemption ---------- */
/* Resolve client IP (trust CF/XFF) */
$ip = !empty($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP']
	: (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
	: ($_SERVER['REMOTE_ADDR'] ?? ''));
$ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '';
if ($ip === '') { $__bp_noip = true; } else { $__bp_noip = false; }

$UA = $_SERVER['HTTP_USER_AGENT'] ?? '';
require_once __DIR__ . '/bot-helpers.php';
$__serp = (!$__bp_noip) ? bp_is_verified_serp_bot($ip, $UA) : false;
!defined('_IS_SERP_BOT') && define('_IS_SERP_BOT', $__serp);
if (!_IS_SERP_BOT && $__bp_noip) { /* no IP, just skip SERP allow and continue to refresh */ }
if (_IS_SERP_BOT) return;

/* ---------- local cache state ---------- */
$exists  = is_file($CACHE_FILE) && is_readable($CACHE_FILE) && filesize($CACHE_FILE) > 0 && filesize($CACHE_FILE) <= $CACHE_MAX_SIZE;
$age     = $exists ? (time() - @filemtime($CACHE_FILE)) : -1;
$stale   = !$exists || ($age > $CACHE_MAX_AGE);
$writable_dir = is_writable($CACHE_DIR);

/* ---------- fast-match blocklist (only if we have a non-empty cache) ---------- */
$matched = false;
if (!$__bp_noip && $exists) {
	$in_cidr = function(string $ip, string $cidr): bool {
		if (!str_contains($cidr,'/')) return false;
		[$sub,$mask]=explode('/',$cidr,2);
		if (!filter_var($sub,FILTER_VALIDATE_IP) || !is_numeric($mask)) return false;
		$mask=(int)$mask; $ipl=ip2long($ip); $sbl=ip2long($sub);
		if ($ipl===false||$sbl===false||$mask<0||$mask>32) return false;
		$ml = $mask===0 ? 0 : ((~0) << (32-$mask)) & 0xFFFFFFFF;
		return (($ipl & $ml) === ($sbl & $ml));
	};
	foreach (@file($CACHE_FILE, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
		$line = trim($line);
		if ($line==='' || $line[0]==='#') continue;
		$matched = str_contains($line,'/') ? $in_cidr($ip,$line) : hash_equals($line,$ip);
		if ($matched) break;
	}
	if ($matched) {
		$CENTRAL_BLOCKED_URL = 'https://battleplanwebdesign.com/wp-content/blocked-notify.php';
		$site = $_SERVER['HTTP_HOST'] ?? '';
		$ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$ref  = $_SERVER['HTTP_REFERER'] ?? '';
		$ptr  = gethostbyaddr($ip) ?: '';
		$ts   = (string)time();

		// HMAC over ip|site|ts (must match central)
		$payload = $ip.'|'.$site.'|'.$ts;
		$sig     = hash_hmac('sha256', $payload, $BP_SECRET);

		$ctx = stream_context_create(['http'=>[
			'method'=>'POST',
			'header'=>"Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
			'timeout'=>0.6,
			'content'=>http_build_query([
				'ip'=>$ip,'site'=>$site,'ts'=>$ts,'sig'=>$sig,
				'ua'=>$ua,'ref'=>$ref,'ptr'=>$ptr,'msg'=>'blocked'
			])
		]]);
		@file_get_contents($CENTRAL_BLOCKED_URL, false, $ctx);

		if (!headers_sent()) {
			header('Content-Type: text/plain; charset=UTF-8');
			header('Cache-Control: no-store');
			header('X-BP-Guard: blocked');
			http_response_code(403);
		}
		exit;
	}
}

/* ---------- refresh + single check-in (deduped) ---------- */
$stale = !$exists || (time() - @filemtime($CACHE_FILE) > $CACHE_MAX_AGE);
if ($stale && $CENTRAL_READ_URL) {
	$ok = false; $count = 0; $cache_ts = 0;

	// fetch latest list
	$ctx  = stream_context_create(['http'=>[
		'method'=>'GET','timeout'=>0.8,'follow_location'=>1,'max_redirects'=>2,
		'header'=>"Connection: close\r\nUser-Agent: BP-Guard/1.0\r\n"
	]]);
	$body = @file_get_contents($CENTRAL_READ_URL, false, $ctx);
	$code = 0; if (isset($http_response_header[0]) && preg_match('~\s(\d{3})\s~',$http_response_header[0],$m)) $code=(int)$m[1];

	// normalize + atomic write
	if ($writable_dir && is_string($body) && $body!=='') {
		$lines = array_values(array_unique(array_filter(array_map('trim', explode("\n",$body)), fn($l)=>$l!=='' && $l[0]!=='#')));
		$count = count($lines);
		if ($count>0) {
			$tmp = $CACHE_FILE.'.tmp';
			$ok  = (@file_put_contents($tmp, implode("\n",$lines)."\n", LOCK_EX)!==false) && (filesize($tmp)>0);
			if ($ok) { @chmod($tmp,0664); @rename($tmp,$CACHE_FILE); }
		}
	} 

	$cache_ts = is_file($CACHE_FILE) ? (int)@filemtime($CACHE_FILE) : 0;

	// single check-in
	$site = $_SERVER['HTTP_HOST'] ?? '';
	if ($site && $CENTRAL_CHECKIN_URL) {
		$ips_ct = (is_file($CACHE_FILE) && is_readable($CACHE_FILE)) ? count(file($CACHE_FILE, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: []) : 0;
		$ts     = (string)time();
		$payload= $site.'|'.($ok?'ok':'fail').'|'.$ips_ct.'|'.$cache_ts.'|'.$ts;
		$sig    = hash_hmac('sha256', $payload, $BP_SECRET);

		$ctx = stream_context_create(['http'=>[
			'method'=>'POST',
			'header'=>"Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
			'timeout'=>0.6,
			'content'=>http_build_query([
				'name'=>$NAME,'site'=>$site,'status'=>$ok?'ok':'fail','ips'=>$ips_ct,
				'cache_ts'=>$cache_ts,'ts'=>$ts,'sig'=>$sig,'ver'=>$BP_VER,
				'hits_week'=>$HITS_WEEK,'hits_month'=>$HITS_MONTH,'hits_quarter'=>$HITS_QUARTER,
				'hits_year'=>$HITS_YEAR,'mobile_speed'=>$MOBILE_SPEED,'desktop_speed'=>$DESKTOP_SPEED
			])
		]]);
		@file_get_contents($CENTRAL_CHECKIN_URL, false, $ctx);
	}
}


/* -----------------------------------------------------------
 * Heartbeat check-in (keeps “Updated (UTC)” fresh even when the
 * cache is not stale). Does NOT fetch central list again.
 * ----------------------------------------------------------- */
$HEARTBEAT_SECS = ($CACHE_MAX_AGE * 3);
$beat_file = $BP_STORE . '/checkin.touch';
$need_beat = !is_file($beat_file) || (time() - @filemtime($beat_file) >= $HEARTBEAT_SECS);

if ($need_beat && $CENTRAL_CHECKIN_URL) {
	$site = $_SERVER['HTTP_HOST'] ?? '';
	// Optional: normalize so mathisair.com and www.mathisair.com collapse
	$site = strtolower($site);
	$site = preg_replace('/^www\./', '', $site);

	$ips_ct   = 0;
	$cache_ts = is_file($CACHE_FILE) ? (int)@filemtime($CACHE_FILE) : 0;
	if (is_file($CACHE_FILE) && is_readable($CACHE_FILE)) {
		$ips_ct = count(file($CACHE_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
	}

	$ts   = (string)time();
	$payload = $site.'|heartbeat|'.$ips_ct.'|'.$cache_ts.'|'.$ts;
	$sig  = hash_hmac('sha256', $payload, $BP_SECRET);


	$ctx = stream_context_create(['http'=>[
		'method'=>'POST',
		'header'=>"Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
		'content'=>http_build_query([
			'name'=>$NAME,
			'site'=>$site,
			'status'=>'heartbeat',
			'ips'=>$ips_ct,
			'cache_ts'=>$cache_ts,
			'ts'=>$ts,
			'sig'=>$sig,
			'ver'=>$BP_VER,
			'hits_week'     => $HITS_WEEK, 
			'hits_month'     => $HITS_MONTH, 
			'hits_quarter'     => $HITS_QUARTER, 
			'hits_year'     => $HITS_YEAR, 
			'mobile_speed'     => $MOBILE_SPEED, 
			'desktop_speed'     => $DESKTOP_SPEED, 
		]),
		'timeout'=>0.6
	]]);
	@file_get_contents($CENTRAL_CHECKIN_URL, false, $ctx);
	@touch($beat_file);
}


/* ---------- done (no blocking) ---------- */
// If not matched above, we simply return to let WP continue.
return;
