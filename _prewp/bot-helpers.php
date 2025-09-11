<?php
// _prewp/bot-helpers.php
// Shared helper to verify if an IP+UA is a real search engine crawler (Google, Bing, etc.)

if (!function_exists('bp_is_verified_serp_bot')) {
	function bp_is_verified_serp_bot(string $ip, string $ua=''): bool {
		if ($ip==='' || $ua==='') return false;
		$ua = strtolower($ua);

		// only continue if UA claims to be one of the known search bots
		if (
			strpos($ua,'google')===false &&
			strpos($ua,'bing')===false &&
			strpos($ua,'yahoo')===false &&
			strpos($ua,'duckduck')===false &&
			strpos($ua,'yandex')===false &&
			strpos($ua,'baiduspider')===false &&
			strpos($ua,'applebot')===false &&
			strpos($ua,'qwantify')===false &&
			strpos($ua,'petalbot')===false
		) return false;

		$ptr = gethostbyaddr($ip);
		if (!$ptr) return false;

		// valid rDNS suffixes for major search engines
		$valid = preg_match('~
			\.(googlebot\.com
			|google\.com
			|bc\.googleusercontent\.com
			|search\.msn\.com
			|bing\.com
			|yahoo\.com
			|duckduckgo\.com
			|yandex\.ru
			|baidu\.com
			|applebot\.apple\.com
			|qwant\.com
			|petalsearch\.com)$
		~ix', $ptr);

		if (!$valid) return false;

		// Forward-confirm the PTR hostname resolves back to same IP
		$fwds = gethostbynamel($ptr) ?: [];
		return in_array($ip, $fwds, true);
	}
}
