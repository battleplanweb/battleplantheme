<?php
/**
 * _prewp/geo.php — Pre-WordPress visitor-geo endpoint.
 *
 * Returns the current visitor's country/city/region/zip as tiny, uncached JSON,
 * read straight from Cloudflare's request headers. The tracking bootstrapper in
 * <head> fetches this to decide whether to load GA4 + Clarity (the US gate) and to
 * set the geo cookies used by the form country-block and "serving your city"
 * personalization.
 *
 * WHY THIS EXISTS (vs. the WP REST route): booting all of WordPress just to read a
 * couple of $_SERVER headers put ~1s on the critical request chain. This file runs
 * WITHOUT WordPress, so it answers in tens of ms. The WP REST route
 * (/wp-json/bp/v1/geo) still exists for back-compat — it require()s this file and
 * calls bp_prewp_geo_data(), so the logic lives in exactly ONE place.
 *
 * Note: CF-* headers are set by Cloudflare and trusted here exactly as the old WP
 * endpoint trusted them. A direct (non-CF) request could spoof them, but the only
 * thing gated is analytics loading + geo personalization — no security decision —
 * and the ~1% non-CF path falls back to a single server-side ipapi.co lookup.
 */

if ( ! function_exists( 'bp_prewp_geo_clean' ) ) {
	// Minimal, WP-free stand-in for sanitize_text_field on short header values.
	function bp_prewp_geo_clean( $v ): string {
		$v = strip_tags( (string) $v );
		$v = preg_replace( '/[\x00-\x1F\x7F]+/', '', $v ); // strip control chars
		$v = preg_replace( '/\s+/', ' ', $v );             // collapse whitespace
		$v = trim( $v );
		return strlen( $v ) > 100 ? substr( $v, 0, 100 ) : $v;
	}
}

if ( ! function_exists( 'bp_prewp_geo_http_get' ) ) {
	// WP-free HTTP GET (curl preferred, stream fallback). Returns body on HTTP 200, else ''.
	function bp_prewp_geo_http_get( string $url, int $timeout = 3 ): string {
		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init( $url );
			curl_setopt_array( $ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => $timeout,
				CURLOPT_CONNECTTIMEOUT => $timeout,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_USERAGENT      => 'BattlePlan-geo',
			] );
			$body = curl_exec( $ch );
			$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );
			return ( $code === 200 && is_string( $body ) ) ? $body : '';
		}

		$ctx = stream_context_create( [ 'http' => [
			'timeout'       => $timeout,
			'ignore_errors' => true,
			'header'        => "User-Agent: BattlePlan-geo\r\n",
		] ] );
		$body = @file_get_contents( $url, false, $ctx );
		$code = 0;
		if ( isset( $http_response_header[0] ) && preg_match( '#\s(\d{3})\s#', $http_response_header[0], $m ) ) {
			$code = (int) $m[1];
		}
		return ( $code === 200 && is_string( $body ) ) ? $body : '';
	}
}

if ( ! function_exists( 'bp_prewp_geo_data' ) ) {
	function bp_prewp_geo_data(): array {
		$grab = function( $key ) {
			return isset( $_SERVER[ $key ] ) ? bp_prewp_geo_clean( $_SERVER[ $key ] ) : '';
		};

		$country = strtoupper( $grab( 'HTTP_CF_IPCOUNTRY' ) );
		// CF sends XX (unknown) and T1/T2 (Tor) — blank these so the client fails open
		if ( in_array( $country, [ 'XX', 'T1', 'T2' ], true ) ) $country = '';

		$city   = $grab( 'HTTP_CF_IPCITY' );
		$region = strtoupper( $grab( 'HTTP_CF_REGION_CODE' ) );
		$zip    = $grab( 'HTTP_CF_POSTAL_CODE' );

		// Non-Cloudflare sites (~1%) have no CF headers → blank country. One server-side
		// ipapi.co lookup keeps the country-block + personalization working there.
		if ( $country === '' ) {
			$ip = $grab( 'HTTP_CF_CONNECTING_IP' );
			if ( $ip === '' && ( $fwd = $grab( 'HTTP_X_FORWARDED_FOR' ) ) !== '' ) {
				$ip = trim( explode( ',', $fwd )[0] );
			}
			if ( $ip === '' ) $ip = $grab( 'REMOTE_ADDR' );

			if ( $ip !== '' && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$body = bp_prewp_geo_http_get( 'https://ipapi.co/' . rawurlencode( $ip ) . '/json/', 3 );
				if ( $body !== '' ) {
					$d = json_decode( $body, true );
					if ( is_array( $d ) ) {
						$country = strtoupper( bp_prewp_geo_clean( $d['country'] ?? '' ) ); // ipapi 'country' = ISO code
						if ( $city   === '' ) $city   = bp_prewp_geo_clean( $d['city'] ?? '' );
						if ( $region === '' ) $region = strtoupper( bp_prewp_geo_clean( $d['region_code'] ?? '' ) );
						if ( $zip    === '' ) $zip    = bp_prewp_geo_clean( $d['postal'] ?? '' );
					}
				}
			}
		}

		return [
			'country' => $country,
			'city'    => $city,
			'region'  => $region,
			'zip'     => $zip,
		];
	}
}

// Direct hit (NOT require()d from within WordPress) → emit the JSON and stop.
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Cache-Control: private, no-store, max-age=0' );
	header( 'X-Content-Type-Options: nosniff' );
	echo json_encode( bp_prewp_geo_data() );
	exit;
}
