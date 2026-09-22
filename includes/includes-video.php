<?php
/* Battle Plan Web Design YouTube Channels

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# What this is
# API key
# Reference parsing
# API transport
# Channel lookup
# Video lists (API)
# Video lists (RSS fallback)
# Cache
# Formatting helpers
# Rendering
# Schema
# Shortcode
# AJAX: load more
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# What this is
#
# [vid] embeds ONE video. This puts a whole CHANNEL on a page — the job people
# reach for Smash Balloon / Embed Plus / YotuWP to do.
#
#   [yt-channel channel="@handle"]                     grid of the newest uploads
#   [yt-channel channel="@handle" layout="feature"]    big player + thumbnail strip
#   [yt-channel channel="@handle" layout="scroll"]     swipe rail on phones
#   [yt-channel playlist="PLxxxx" max="8"]             one playlist instead
#
# Two rules it inherits from [vid], and they are the whole reason this is worth
# doing in-framework instead of with a plugin:
#
#   1. NO IFRAMES until a click. Every tile is a facade — a thumbnail off
#      Google's CDN plus a play button. A 12-video grid of real embeds is ~10MB
#      of YouTube JS and murders LCP; this is 12 images. The iframe gets built
#      in script-video.js at click time, and it carries .video-player so
#      stopAllVideos() still finds and pauses it.
#
#   2. NOTHING is fetched on the render path. Everything comes out of a
#      transient. See "# Cache" — a cold cache refreshes behind a lock, and a
#      dead or over-quota API keeps serving the last good payload instead of
#      collapsing the section to an empty div.
#
# Data source, in order of preference:
#
#   A. YouTube Data API v3, when a key is available. Handles, playlists,
#      durations, view counts, subscriber count, pagination. Quota cost is
#      close to nothing: channels.list = 1 unit, playlistItems.list = 1 unit
#      per 50 videos, videos.list = 1 unit per 50. A cached channel page runs
#      ~6 units/day against a 10,000/day allowance.
#
#      IMPORTANT: never search.list — that one costs 100 units a call and would
#      drain the day's quota in 100 page loads. The uploads playlist handed
#      back by channels.list gets the same videos for 1 unit.
#
#   B. The keyless RSS feed, when no key is set. Costs nothing and needs no
#      setup, but YouTube caps it at the 15 newest videos and it carries no
#      durations, no playlists and no channel stats. The shortcode degrades to
#      it rather than rendering nothing on a site that was never configured.
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# API key
#
# Its own constant, but it falls back to the Places key: a Google Cloud project
# with YouTube Data API v3 switched on issues one key that answers both, so
# enabling the API on the existing project lights this up fleet-wide with no
# per-site config. Both are called server-side, so neither can be HTTP-referrer
# restricted anyway.
--------------------------------------------------------------*/

if ( ! defined('_YOUTUBE_API') ) define( '_YOUTUBE_API', defined('BP_YOUTUBE_KEY') ? BP_YOUTUBE_KEY : '' );

function bp_yt_api_key() {
	$key = defined('_YOUTUBE_API') ? (string) _YOUTUBE_API : '';
	if ( $key === '' && defined('_PLACES_API') ) $key = (string) _PLACES_API;
	return (string) apply_filters( 'bp_yt_api_key', $key );
}


/*--------------------------------------------------------------
# Reference parsing
#
# Take whatever the shortcode was handed — a bare id, an @handle, or any
# flavour of channel URL someone copied out of the address bar — and say what
# kind of thing it is. channels.list takes a different parameter for each.
--------------------------------------------------------------*/

function bp_yt_parse_ref( $ref ) {
	$ref = trim( (string) $ref );
	if ( $ref === '' ) return null;

	// Full URL: keep only the path, then read the first meaningful segment.
	if ( strpos( $ref, 'youtu' ) !== false && strpos( $ref, '/' ) !== false ) {
		$path = parse_url( $ref, PHP_URL_PATH );
		$bits = array_values( array_filter( explode( '/', (string) $path ) ) );

		if ( isset( $bits[0] ) ) {
			if ( $bits[0] === 'channel' && isset( $bits[1] ) ) return [ 'type'=>'id',     'value'=>$bits[1] ];
			if ( $bits[0] === 'user'    && isset( $bits[1] ) ) return [ 'type'=>'user',   'value'=>$bits[1] ];
			// /c/Custom is the retired vanity form. Google folded those into handles,
			// so forHandle is the best shot we have at resolving one.
			if ( $bits[0] === 'c'       && isset( $bits[1] ) ) return [ 'type'=>'handle', 'value'=>'@'.$bits[1] ];
			if ( strpos( $bits[0], '@' ) === 0 )               return [ 'type'=>'handle', 'value'=>$bits[0] ];
		}
		return null;
	}

	if ( strpos( $ref, '@' ) === 0 )                            return [ 'type'=>'handle',   'value'=>$ref ];
	if ( preg_match( '/^UC[\w-]{20,}$/', $ref ) )               return [ 'type'=>'id',       'value'=>$ref ];
	if ( preg_match( '/^(PL|UU|LL|FL|OL)[\w-]{10,}$/', $ref ) ) return [ 'type'=>'playlist', 'value'=>$ref ];

	// Anything else is a legacy /user/ name.
	return [ 'type'=>'user', 'value'=>$ref ];
}


/*--------------------------------------------------------------
# API transport
--------------------------------------------------------------*/

function bp_yt_api( $endpoint, array $params ) {
	$key = bp_yt_api_key();
	if ( $key === '' ) return null;

	$params['key'] = $key;
	$url = 'https://www.googleapis.com/youtube/v3/' . $endpoint . '?' . http_build_query( $params );

	$resp = wp_remote_get( $url, [ 'timeout' => 8, 'headers' => [ 'Accept' => 'application/json' ] ] );
	if ( is_wp_error( $resp ) ) {
		bp_yt_note_failure( $endpoint, 'transport', $resp->get_error_message() );
		bp_yt_log_failure( $endpoint, 'transport' );
		return null;
	}

	$code = (int) wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );

	if ( $code !== 200 || ! is_array( $body ) ) {
		// 403 here is almost always quotaExceeded or an API that was never enabled on
		// the project. Leave a breadcrumb — the section quietly keeps serving its
		// stale cache, so without this there is nothing to tell you the feed went dark.
		$reason = $body['error']['errors'][0]['reason'] ?? ( 'http_' . $code );
		bp_yt_note_failure( $endpoint, $reason, $body['error']['message'] ?? '', $code );
		bp_yt_log_failure( $endpoint, $reason );
		return null;
	}

	return $body;
}

// The last failure, verbatim and UNTHROTTLED, for the editor-only empty-state
// message to read back. Google's error.message is the genuinely useful part —
// it names the project and says exactly which API is off — and throwing it away
// is the difference between "no videos" and a diagnosis.
function bp_yt_note_failure( $endpoint, $reason, $message = '', $code = 0 ) {
	set_transient( 'bp_yt_last_error', [
		'endpoint' => $endpoint,
		'reason'   => $reason,
		'message'  => $message,
		'code'     => (int) $code,
		'when'     => time(),
	], DAY_IN_SECONDS );
}

// One alert per reason per week, so a hard-down API can't mail on every cache miss.
function bp_yt_log_failure( $endpoint, $reason ) {
	$key = 'bp_yt_fail_' . md5( $endpoint . '|' . $reason );
	if ( get_transient( $key ) ) return;
	set_transient( $key, 1, WEEK_IN_SECONDS );

	error_log( '[BP YouTube] ' . $endpoint . ' failed: ' . $reason );

	if ( in_array( $reason, [ 'quotaExceeded', 'accessNotConfigured', 'keyInvalid', 'forbidden' ], true ) ) {
		wp_mail(
			'glendon@bp-webdev.com',
			'[Battle Plan] YouTube API failing on ' . parse_url( home_url(), PHP_URL_HOST ),
			"Endpoint: {$endpoint}\nReason:   {$reason}\n\nThe channel feed is serving its last cached payload until this clears."
		);
	}
}


/*--------------------------------------------------------------
# Channel lookup
#
# One channels.list call (1 unit) answers everything the header needs AND hands
# back the uploads playlist id, which is what the video list actually queries.
# Cached a day, so it costs a unit a day per channel.
--------------------------------------------------------------*/

function bp_yt_channel( $ref ) {
	$parsed = bp_yt_parse_ref( $ref );
	if ( ! $parsed || $parsed['type'] === 'playlist' ) return null;

	$cache_key = 'bp_yt_ch_' . md5( $parsed['type'] . '|' . $parsed['value'] );
	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) ) return $cached ?: null;

	$params = [ 'part' => 'snippet,contentDetails,statistics' ];
	if ( $parsed['type'] === 'id' )     $params['id']          = $parsed['value'];
	if ( $parsed['type'] === 'handle' ) $params['forHandle']   = $parsed['value'];
	if ( $parsed['type'] === 'user' )   $params['forUsername'] = $parsed['value'];

	$body = bp_yt_api( 'channels', $params );

	// An API-level failure is NOT "this channel does not exist" — bp_yt_api returns
	// null for a 403/quota/transport error, and caching that as a miss would keep
	// the block dark for six hours after the key is fixed. Only cache a genuine
	// answer of "no such channel".
	if ( $body === null ) return null;

	$item = $body['items'][0] ?? null;

	if ( ! $item ) {
		// A bad handle shouldn't cost an API call on every page view for the next day.
		set_transient( $cache_key, [], 6 * HOUR_IN_SECONDS );
		return null;
	}

	$channel = [
		'id'          => $item['id'] ?? '',
		'title'       => $item['snippet']['title'] ?? '',
		'description' => $item['snippet']['description'] ?? '',
		'avatar'      => $item['snippet']['thumbnails']['medium']['url'] ?? ( $item['snippet']['thumbnails']['default']['url'] ?? '' ),
		'handle'      => $item['snippet']['customUrl'] ?? '',
		'uploads'     => $item['contentDetails']['relatedPlaylists']['uploads'] ?? '',
		'subscribers' => isset( $item['statistics']['subscriberCount'] ) ? (int) $item['statistics']['subscriberCount'] : 0,
		'hidden_subs' => ! empty( $item['statistics']['hiddenSubscriberCount'] ),
		'videos'      => isset( $item['statistics']['videoCount'] ) ? (int) $item['statistics']['videoCount'] : 0,
	];

	set_transient( $cache_key, $channel, DAY_IN_SECONDS );
	return $channel;
}


/*--------------------------------------------------------------
# Video lists (API)
#
# playlistItems gives ids + titles + thumbnails (1 unit per 50). Durations and
# view counts need a second videos.list pass (another 1 unit per 50), so that
# one only runs when the layout actually asks for them.
--------------------------------------------------------------*/

function bp_yt_api_videos( $playlist_id, $want, $need_details = true ) {
	$per_call = min( 50, max( 1, (int) $want ) );
	$videos   = [];
	$next     = '';

	// Loop only far enough to satisfy $want — each lap is another quota unit.
	do {
		$params = [
			'part'       => 'snippet,contentDetails',
			'playlistId' => $playlist_id,
			'maxResults' => $per_call,
		];
		if ( $next !== '' ) $params['pageToken'] = $next;

		$body = bp_yt_api( 'playlistItems', $params );
		if ( ! $body ) break;

		foreach ( (array) ( $body['items'] ?? [] ) as $item ) {
			$id = $item['contentDetails']['videoId'] ?? ( $item['snippet']['resourceId']['videoId'] ?? '' );
			if ( $id === '' ) continue;

			// Deleted and private videos stay in the playlist as husks with no usable
			// thumbnail. Drop them, or the grid renders grey holes.
			$title = $item['snippet']['title'] ?? '';
			if ( $title === 'Private video' || $title === 'Deleted video' ) continue;

			$videos[ $id ] = [
				'id'          => $id,
				'title'       => $title,
				'description' => $item['snippet']['description'] ?? '',
				'published'   => $item['contentDetails']['videoPublishedAt'] ?? ( $item['snippet']['publishedAt'] ?? '' ),
				'thumb'       => bp_yt_best_thumb( $item['snippet']['thumbnails'] ?? [], $id ),
				'duration'    => 0,
				'views'       => 0,
				'live'        => false,
			];
		}

		$next = (string) ( $body['nextPageToken'] ?? '' );
	} while ( $next !== '' && count( $videos ) < $want );

	if ( $need_details && $videos ) $videos = bp_yt_attach_details( $videos );

	return [ 'videos' => array_values( $videos ), 'next' => $next ];
}

// Durations, view counts and live status for up to 50 ids in one unit.
function bp_yt_attach_details( array $videos ) {
	foreach ( array_chunk( array_keys( $videos ), 50 ) as $chunk ) {
		$body = bp_yt_api( 'videos', [
			'part' => 'contentDetails,statistics,snippet',
			'id'   => implode( ',', $chunk ),
		] );
		if ( ! $body ) continue;

		foreach ( (array) ( $body['items'] ?? [] ) as $item ) {
			$id = $item['id'] ?? '';
			if ( ! isset( $videos[ $id ] ) ) continue;

			$videos[ $id ]['duration'] = bp_yt_duration_seconds( $item['contentDetails']['duration'] ?? '' );
			$videos[ $id ]['views']    = isset( $item['statistics']['viewCount'] ) ? (int) $item['statistics']['viewCount'] : 0;
			$videos[ $id ]['live']     = ( ( $item['snippet']['liveBroadcastContent'] ?? 'none' ) !== 'none' );
		}
	}
	return $videos;
}

// maxres only exists for videos uploaded with a big enough thumbnail, and the API
// omits it rather than 404ing, so walk down. i.ytimg.com is the CDN [vid] already uses.
function bp_yt_best_thumb( array $thumbs, $id ) {
	foreach ( [ 'maxres', 'standard', 'high', 'medium', 'default' ] as $size ) {
		if ( ! empty( $thumbs[ $size ]['url'] ) ) return $thumbs[ $size ]['url'];
	}
	return 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
}


/*--------------------------------------------------------------
# Video lists (RSS fallback)
#
# https://www.youtube.com/feeds/videos.xml — undocumented but long-lived, and
# it needs no key at all. Hard capped at the 15 newest items, and it carries no
# duration, so anything duration-driven (the badge, shorts filtering, sorting
# by length) is simply absent rather than wrong.
--------------------------------------------------------------*/

function bp_yt_rss_videos( $channel_id, $playlist_id = '' ) {
	$url = $playlist_id !== ''
		? 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode( $playlist_id )
		: 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode( $channel_id );

	$resp = wp_remote_get( $url, [ 'timeout' => 8 ] );
	if ( is_wp_error( $resp ) || (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) return [ 'videos'=>[], 'next'=>'' ];

	$xml = @simplexml_load_string( wp_remote_retrieve_body( $resp ) );
	if ( ! $xml ) return [ 'videos'=>[], 'next'=>'' ];

	$videos = [];
	foreach ( $xml->entry as $entry ) {
		$yt    = $entry->children( 'http://www.youtube.com/xml/schemas/2015' );
		$media = $entry->children( 'http://search.yahoo.com/mrss/' );
		$id    = (string) $yt->videoId;
		if ( $id === '' ) continue;

		$group = $media->group;
		$views = 0;
		if ( isset( $group->community->statistics ) ) {
			$attrs = $group->community->statistics->attributes();
			$views = isset( $attrs['views'] ) ? (int) $attrs['views'] : 0;
		}

		$videos[] = [
			'id'          => $id,
			'title'       => (string) $entry->title,
			'description' => isset( $group->description ) ? (string) $group->description : '',
			'published'   => (string) $entry->published,
			'thumb'       => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
			'duration'    => 0,
			'views'       => $views,
			'live'        => false,
		];
	}

	return [ 'videos' => $videos, 'next' => '' ];
}


/*--------------------------------------------------------------
# Cache
#
# Two expiries on purpose. The transient itself lives 30 days; the payload
# carries its own logical expiry (12h by default). Past that we try to refresh,
# but if the API is down or over quota the stale payload is still there to
# serve — a channel section that quietly shows yesterday's videos beats one
# that renders an empty div the day you hit the quota ceiling.
#
# The lock stops a cache stampede: when a busy page goes stale, the first
# request refreshes and everyone else gets the stale copy for those two minutes
# instead of forty concurrent requests all hammering the API.
--------------------------------------------------------------*/

function bp_yt_get_feed( array $args ) {
	$ttl   = (int) apply_filters( 'bp_yt_cache_seconds', 12 * HOUR_IN_SECONDS, $args );
	$key   = 'bp_yt_f_' . md5( wp_json_encode( $args ) );
	$lock  = $key . '_lock';
	$cache = get_transient( $key );
	$held  = ( is_array( $cache ) && isset( $cache['data'] ) ) ? $cache['data'] : null;
	$fresh = $held && ! empty( $cache['expires'] ) && $cache['expires'] > time();

	if ( $fresh ) return $held;
	if ( $held && get_transient( $lock ) ) return $held;

	set_transient( $lock, 1, 2 * MINUTE_IN_SECONDS );
	$data = bp_yt_fetch_feed( $args );
	delete_transient( $lock );

	if ( ! empty( $data['videos'] ) ) {
		set_transient( $key, [ 'data' => $data, 'expires' => time() + $ttl ], 30 * DAY_IN_SECONDS );
		return $data;
	}

	// Fetch failed. Hand back whatever we had, however old.
	return $held ?: [ 'videos'=>[], 'next'=>'', 'channel'=>null ];
}

function bp_yt_fetch_feed( array $args ) {
	$channel  = null;
	$playlist = $args['playlist'];

	if ( $args['channel'] !== '' ) {
		$parsed = bp_yt_parse_ref( $args['channel'] );
		// Someone put a playlist id in channel="" — take it as one.
		if ( $parsed && $parsed['type'] === 'playlist' && $playlist === '' ) $playlist = $parsed['value'];
		else $channel = bp_yt_channel( $args['channel'] );
	}

	if ( bp_yt_api_key() !== '' ) {
		$source = $playlist !== '' ? $playlist : ( $channel['uploads'] ?? '' );
		if ( $source !== '' ) {
			// Fetch enough to cover everything rendered so far plus one more page.
			//
			// Load More pages by OFFSET, not by YouTube's page token, and it has to:
			// when shorts or search thin the list we deliberately over-fetch, so the
			// token after the last fetched page points PAST videos we filtered out but
			// never rendered. Paging by token would silently skip them. Paging by
			// offset into the filtered list can't.
			$need     = (int) $args['offset'] + (int) $args['max'];
			$filtered = ( $args['shorts'] !== 'all' || ( $args['search'] ?? '' ) !== '' );
			$want     = min( (int) apply_filters( 'bp_yt_max_fetch', 200 ), $filtered ? $need * 3 : $need );

			$feed = bp_yt_api_videos( $source, $want, $args['details'] );
			$feed['channel'] = $channel;
			return $feed;
		}
	}

	// No key (or the channel never resolved) — RSS needs a raw UC id.
	$channel_id = $channel['id'] ?? '';
	if ( $channel_id === '' && $args['channel'] !== '' ) {
		$parsed = bp_yt_parse_ref( $args['channel'] );
		if ( $parsed && $parsed['type'] === 'id' ) $channel_id = $parsed['value'];
	}
	if ( $channel_id === '' && $playlist === '' ) return [ 'videos'=>[], 'next'=>'', 'channel'=>null ];

	$feed = bp_yt_rss_videos( $channel_id, $playlist );
	$feed['channel'] = $channel;
	return $feed;
}


/*--------------------------------------------------------------
# Formatting helpers
--------------------------------------------------------------*/

// PT1H2M10S -> 3730
function bp_yt_duration_seconds( $iso ) {
	if ( ! preg_match( '/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', (string) $iso, $m ) ) return 0;
	return ( (int) ( $m[1] ?? 0 ) * 86400 ) + ( (int) ( $m[2] ?? 0 ) * 3600 ) + ( (int) ( $m[3] ?? 0 ) * 60 ) + (int) ( $m[4] ?? 0 );
}

function bp_yt_duration_label( $seconds ) {
	$seconds = (int) $seconds;
	if ( $seconds < 1 ) return '';
	$h = floor( $seconds / 3600 );
	$m = floor( ( $seconds % 3600 ) / 60 );
	$s = $seconds % 60;
	return $h ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s );
}

// 1240 -> 1.2K. Matches how YouTube itself writes counts.
function bp_yt_count_label( $n ) {
	$n = (int) $n;
	if ( $n >= 1000000 ) return rtrim( rtrim( number_format( $n / 1000000, 1 ), '0' ), '.' ) . 'M';
	if ( $n >= 1000 )    return rtrim( rtrim( number_format( $n / 1000, 1 ), '0' ), '.' ) . 'K';
	return (string) $n;
}

function bp_yt_embed_url( $id, array $args ) {
	// autoplay=1 because the iframe is only ever built on a click — the visitor has
	// already asked for it. enablejsapi=1 keeps stopAllVideos() able to pause it.
	$url = 'https://www.youtube.com/embed/' . $id . '?autoplay=1&enablejsapi=1&version=3&playerapiid=ytplayer';
	if ( ( $args['related'] ?? 'false' ) !== 'true' ) $url .= '&rel=0';
	if ( ( $args['begin'] ?? '' ) !== '' )            $url .= '&start=' . (int) $args['begin'];
	return $url;
}


/*--------------------------------------------------------------
# Rendering
#
# Every tile is a <button>, not a div with a click handler — that buys keyboard
# operability, a focus ring and Enter/Space for free.
--------------------------------------------------------------*/

function bp_yt_tile( array $v, array $args, $index = 0 ) {
	$id       = $v['id'];
	$title    = $v['title'];
	$duration = bp_yt_duration_label( $v['duration'] );

	$meta = [];
	if ( ( $args['date'] ?? 'true' ) === 'true' && $v['published'] !== '' ) {
		$meta[] = '<time datetime="' . esc_attr( $v['published'] ) . '">'
			. esc_html( date_i18n( get_option('date_format'), strtotime( $v['published'] ) ) ) . '</time>';
	}
	if ( ( $args['views'] ?? 'false' ) === 'true' && $v['views'] > 0 ) {
		$meta[] = '<span>' . esc_html( bp_yt_count_label( $v['views'] ) ) . ' views</span>';
	}

	$tile  = '<div class="col col-yt">';
	$tile .= '<div class="col-inner">';
	$tile .= '<div class="yt-tile' . ( $index === 0 ? ' is-current' : '' ) . '">';

	// .unique is the framework's opt-out from the global button styling in
	// style-forms.css (`button:not(.unique)`) — without it every tile inherits the
	// site's button padding, radius, background and shadow, and the facade stops
	// looking like a video. Same escape hatch Site Pulse and [translate] use.
	$tile .= '<button type="button" class="yt-thumb unique"'
		. ' data-id="' . esc_attr( $id ) . '"'
		. ' data-link="' . esc_attr( bp_yt_embed_url( $id, $args ) ) . '"'
		. ' data-title="' . esc_attr( $title ) . '"'
		. ' aria-label="' . esc_attr( 'Play video: ' . $title ) . '">';
	$tile .= '<img loading="lazy" src="' . esc_url( $v['thumb'] ) . '" alt="" width="480" height="270">';
	$tile .= '<span class="yt-play" aria-hidden="true"></span>';
	if ( $v['live'] )           $tile .= '<span class="yt-badge yt-live">Live</span>';
	elseif ( $duration !== '' ) $tile .= '<span class="yt-badge">' . esc_html( $duration ) . '</span>';
	$tile .= '</button>';

	if ( ( $args['titles'] ?? 'true' ) === 'true' || $meta ) {
		$tile .= '<div class="yt-meta">';
		if ( ( $args['titles'] ?? 'true' ) === 'true' ) $tile .= '<p class="yt-title">' . esc_html( $title ) . '</p>';
		if ( $meta )                                    $tile .= '<p class="yt-stats">' . implode( ' <span class="yt-dot">&middot;</span> ', $meta ) . '</p>';
		$tile .= '</div>';
	}

	$tile .= '</div></div></div>';
	return $tile;
}

function bp_yt_header( $channel ) {
	if ( ! $channel || empty( $channel['id'] ) ) return '';

	$sub_url = 'https://www.youtube.com/channel/' . $channel['id'] . '?sub_confirmation=1';

	$html  = '<div class="yt-header">';
	if ( $channel['avatar'] !== '' ) {
		$html .= '<img class="yt-avatar noFX" loading="lazy" src="' . esc_url( $channel['avatar'] ) . '" width="88" height="88" alt="">';
	}
	$html .= '<div class="yt-header-text">';
	$html .= '<p class="yt-channel-name">' . esc_html( $channel['title'] ) . '</p>';

	$stats = [];
	if ( ! $channel['hidden_subs'] && $channel['subscribers'] > 0 ) $stats[] = bp_yt_count_label( $channel['subscribers'] ) . ' subscribers';
	if ( $channel['videos'] > 0 )                                   $stats[] = bp_yt_count_label( $channel['videos'] ) . ' videos';
	if ( $stats ) $html .= '<p class="yt-channel-stats">' . esc_html( implode( ' · ', $stats ) ) . '</p>';

	$html .= '</div>';

	// A plain link, not YouTube's subscribe_embed widget — ?sub_confirmation=1
	// raises the same subscribe prompt with no third-party iframe on the page.
	$html .= '<a class="yt-subscribe" href="' . esc_url( $sub_url ) . '" target="_blank" rel="noopener">Subscribe</a>';
	$html .= '</div>';

	return $html;
}


/*--------------------------------------------------------------
# Schema
#
# An ItemList of VideoObjects using each video's REAL title, description,
# upload date and duration. This is the part no feed plugin does — they render
# client-side, so Google sees an empty div where the channel should be.
--------------------------------------------------------------*/

function bp_yt_schema( array $videos ) {
	$items = [];
	$n = 0;

	foreach ( $videos as $v ) {
		$n++;
		$video = [
			'@type'        => 'VideoObject',
			'name'         => $v['title'],
			'description'  => $v['description'] !== '' ? wp_trim_words( $v['description'], 60 ) : $v['title'],
			'thumbnailUrl' => $v['thumb'],
			'embedUrl'     => 'https://www.youtube.com/embed/' . $v['id'],
			'contentUrl'   => 'https://www.youtube.com/watch?v=' . $v['id'],
		];
		if ( $v['published'] !== '' ) $video['uploadDate'] = gmdate( 'c', strtotime( $v['published'] ) );
		if ( $v['duration'] > 0 )     $video['duration']   = 'PT' . floor( $v['duration'] / 60 ) . 'M' . ( $v['duration'] % 60 ) . 'S';
		if ( $v['views'] > 0 ) {
			$video['interactionStatistic'] = [
				'@type'                => 'InteractionCounter',
				'interactionType'      => [ '@type' => 'WatchAction' ],
				'userInteractionCount' => $v['views'],
			];
		}

		$items[] = [ '@type' => 'ListItem', 'position' => $n, 'item' => $video ];
	}

	if ( ! $items ) return '';

	$schema = [
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'itemListElement' => $items,
	];

	return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
}


/*--------------------------------------------------------------
# Shortcode
--------------------------------------------------------------*/

add_shortcode( 'yt-channel', 'battleplan_buildYouTubeChannel' );
function battleplan_buildYouTubeChannel( $atts, $content = null ) {

	$a = shortcode_atts( [
		'channel'   => '',        // @handle, UC… id, or any channel URL
		'playlist'  => '',        // PL… — used instead of the channel's uploads
		'layout'    => 'grid',    // grid | feature | scroll
		'max'       => '12',
		'columns'   => '3',
		'break'     => '2',       // where the row stacks / starts scrolling
		'sort'      => 'newest',  // newest | oldest | popular | longest | random
		'shorts'    => 'all',     // all | exclude | only
		'search'    => '',        // keep only videos whose title or description matches
		'header'    => 'false',   // avatar + name + subscriber count + Subscribe
		'titles'    => 'true',
		'date'      => 'true',
		'views'     => 'false',
		'more'      => 'false',   // Load More button
		'schema'    => 'true',
		'related'   => 'false',   // YouTube's end-of-video suggestions
		'begin'     => '',
		'size'      => '100',
		'class'     => '',
		'start'     => '',
		'end'       => '',
		'track'     => '',
	], $atts );

	// Same scheduling window every other block shortcode honours.
	$start = strtotime( esc_attr( $a['start'] ) );
	$end   = strtotime( esc_attr( $a['end'] ) );
	if ( $start || $end ) {
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end   && $now > $end )   return null;
	}

	$layout = in_array( $a['layout'], [ 'grid', 'feature', 'scroll' ], true ) ? $a['layout'] : 'grid';
	$max    = max( 1, min( 100, (int) $a['max'] ) );
	$shorts = in_array( $a['shorts'], [ 'all', 'exclude', 'only' ], true ) ? $a['shorts'] : 'all';

	// Durations and view counts cost a second API call, so only ask for them when
	// something on the page actually consumes them.
	$details = ( $a['views'] === 'true' || $shorts !== 'all' || in_array( $a['sort'], [ 'popular', 'longest' ], true ) || $layout !== 'scroll' );

	$request = [
		'channel'  => trim( $a['channel'] ),
		'playlist' => trim( $a['playlist'] ),
		'max'      => $max,
		'shorts'   => $shorts,
		'search'   => trim( $a['search'] ),
		'offset'   => 0,
		'details'  => (bool) $details,
	];

	$feed   = bp_yt_get_feed( $request );
	$all    = bp_yt_filter( $feed['videos'] ?? [], $a, $shorts );
	$videos = array_slice( $all, 0, $max );

	if ( ! $videos ) {
		// Never leave a half-built shell on the page. An editor previewing the page
		// gets a diagnosis; a visitor gets nothing at all.
		if ( current_user_can( 'edit_posts' ) ) {
			// Inline the CSS here too — this early return skips the enqueue below, and
			// an unstyled diagnosis box is easy to miss entirely.
			bp_inline_minified_css( get_template_directory() . '/style-video.css' );
			return bp_yt_diagnose( $request );
		}
		return '';
	}

	bp_inline_minified_css( get_template_directory() . '/style-video.css' );
	bp_enqueue_script( 'battleplan-script-video', 'script-video', [ 'battleplan-script-pages' ] );
	// Safe to localize from inside a shortcode: the script prints in the footer,
	// which is long after the_content has run.
	wp_localize_script( 'battleplan-script-video', 'bpVideo', [ 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ] );

	$size     = convertSize( esc_attr( $a['size'] ) );
	$class    = $a['class'] !== '' ? ' ' . esc_attr( $a['class'] ) : '';
	$tracking = $a['track'] !== '' ? ' data-track="' . esc_attr( $a['track'] ) . '"' : '';
	if ( $tracking !== '' ) $class .= ' tracking';

	$out  = $a['schema'] === 'true' ? bp_yt_schema( $videos ) : '';
	$out .= '<div class="block block-yt-channel yt-' . $layout . ' span-' . $size . $class . '"' . $tracking . '>';
	if ( $a['header'] === 'true' ) $out .= bp_yt_header( $feed['channel'] ?? null );

	// feature: one big player carrying the newest video, strip of the rest below.
	if ( $layout === 'feature' ) {
		$lead = $videos[0];
		$out .= '<div class="yt-stage">';
		$out .= '<button type="button" class="yt-thumb yt-stage-thumb unique"'
			. ' data-id="' . esc_attr( $lead['id'] ) . '"'
			. ' data-link="' . esc_attr( bp_yt_embed_url( $lead['id'], $a ) ) . '"'
			. ' data-title="' . esc_attr( $lead['title'] ) . '"'
			. ' aria-label="' . esc_attr( 'Play video: ' . $lead['title'] ) . '">';
		// NOT lazy: the stage is usually the biggest image above the fold, so lazing
		// it is handing the page a slower LCP for nothing.
		$out .= '<img src="' . esc_url( $lead['thumb'] ) . '" alt="" width="1280" height="720">';
		$out .= '<span class="yt-play" aria-hidden="true"></span>';
		$out .= '</button></div>';
		$out .= '<p class="yt-stage-title">' . esc_html( $lead['title'] ) . '</p>';
	}

	// Both remaining layouts are a [layout] row — scroll just adds .swipe, which is
	// the same machinery [layout scroll="true"] uses, dots and all.
	$grid  = 'grid-' . max( 1, min( 6, (int) $a['columns'] ) ) . 'e';
	$swipe = '';
	if ( $layout === 'scroll' ) {
		$swipe = ' swipe';
		bp_enqueue_script( 'battleplan-script-scroller', 'script-scroller', [ 'battleplan-script-pages' ] );
		bp_inline_minified_css( get_template_directory() . '/style-scroller.css' );
		bp_inline_minified_css( get_template_directory() . '/style-indicators.css' );
	}

	$out .= '<div class="flex ' . $grid . ' break-' . max( 1, min( 4, (int) $a['break'] ) ) . $swipe . ' yt-row">';
	foreach ( $videos as $i => $v ) $out .= bp_yt_tile( $v, $a, $i );
	$out .= '</div>';

	// Load More. The request is signed so a visitor can't re-point the endpoint at
	// an arbitrary channel and spend our quota walking it.
	//
	// Two ways there is more to show: we filtered more than we rendered, or the
	// channel itself had another page past what we fetched. If the follow-up call
	// turns out to have nothing after all, the AJAX handler returns no HTML and the
	// script removes the button.
	if ( $a['more'] === 'true' && bp_yt_api_key() !== '' && ( count( $all ) > $max || ! empty( $feed['next'] ) ) ) {
		$out .= '<div class="block block-button yt-more-wrap">' . bp_yt_more_button( $request, $a, $max ) . '</div>';
	}

	$out .= '</div>';
	return $out;
}

/*--------------------------------------------------------------
# Diagnosis
#
# Shown to logged-in editors only, in place of the block, whenever the feed came
# back empty. "No videos" on its own is useless — nearly every cause is a config
# problem with a specific fix, and the API already told us which one.
--------------------------------------------------------------*/

function bp_yt_diagnose( array $request ) {
	$key    = bp_yt_api_key();
	$err    = get_transient( 'bp_yt_last_error' );
	$parsed = bp_yt_parse_ref( $request['channel'] );

	$lines = [];

	if ( $key === '' ) {
		$lines[] = '<strong>No API key.</strong> Set <code>BP_YOUTUBE_KEY</code> in wp-config.php, or enable <strong>YouTube Data API v3</strong> on the same Google Cloud project that issues the Places key (<code>_PLACES_API</code> is used as the fallback). The keyless RSS fallback also returned nothing, which usually means <code>channel=</code> is not a raw <code>UC…</code> id — RSS cannot resolve an @handle.';
	} elseif ( is_array( $err ) ) {
		$lines[] = '<strong>The API rejected the call.</strong> Endpoint <code>' . esc_html( $err['endpoint'] ) . '</code>'
			. ( $err['code'] ? ' returned HTTP <code>' . (int) $err['code'] . '</code>' : '' )
			. ', reason <code>' . esc_html( $err['reason'] ) . '</code>.';
		if ( $err['message'] !== '' ) $lines[] = 'Google said: <em>' . esc_html( $err['message'] ) . '</em>';

		// These two look alike and have different fixes, so Google's own wording is
		// what tells them apart. "...are blocked" is the key's restriction list;
		// accessNotConfigured is the API being off on the project entirely.
		if ( stripos( $err['message'], 'are blocked' ) !== false || $err['reason'] === 'forbidden' ) {
			$lines[] = '<strong>The key\'s API restriction list is blocking this.</strong> The key works, and the API is reachable — this key just isn\'t allowed to call it. Google Cloud Console → APIs &amp; Services → <strong>Credentials</strong> → click the key → <strong>API restrictions</strong> → add <strong>YouTube Data API v3</strong> → Save. Key changes take up to 5 minutes to propagate.';
		} elseif ( in_array( $err['reason'], [ 'accessNotConfigured', 'keyInvalid' ], true ) ) {
			$lines[] = '<strong>YouTube Data API v3 is not enabled on the project.</strong> Google Cloud Console → APIs &amp; Services → <strong>Library</strong> → search for it → Enable. (It has to be enabled before it can be added to a key\'s API restriction list.)';
		}
		if ( $err['reason'] === 'quotaExceeded' ) {
			$lines[] = 'The project is out of daily quota. It resets at midnight Pacific.';
		}
	} elseif ( $parsed ) {
		$lines[] = '<strong>The API answered, but found no channel.</strong> <code>' . esc_html( $request['channel'] ) . '</code> was read as a <code>' . esc_html( $parsed['type'] ) . '</code> of <code>' . esc_html( $parsed['value'] ) . '</code>. If that is wrong, pass the channel URL exactly as it appears in the address bar, or the raw <code>UC…</code> id from the channel\'s About → Share → Copy channel ID.';
	} elseif ( $request['channel'] === '' && $request['playlist'] === '' ) {
		$lines[] = '<strong>No <code>channel</code> or <code>playlist</code> attribute was given.</strong>';
	} else {
		$lines[] = '<strong>Could not make sense of <code>' . esc_html( $request['channel'] ) . '</code></strong> as a channel reference.';
	}

	// A filter that ate everything looks identical to a broken feed from here.
	if ( $request['shorts'] !== 'all' ) $lines[] = 'Note: <code>shorts="' . esc_html( $request['shorts'] ) . '"</code> is set, so a channel of only the other kind would also come back empty.';
	if ( $request['search'] !== '' )    $lines[] = 'Note: <code>search="' . esc_html( $request['search'] ) . '"</code> is set, so nothing matching means nothing shown.';

	// Which credential is actually in play. "Enabled on the project" and "allowed on
	// THIS key" are different things, and when they disagree it is usually because
	// the key belongs to a different project than the one being looked at.
	if ( $key !== '' ) {
		$source = ( defined('_YOUTUBE_API') && _YOUTUBE_API !== '' ) ? 'BP_YOUTUBE_KEY' : '_PLACES_API (fallback)';
		$lines[] = 'Key in use: <code>' . $source . '</code>, ending <code>…' . esc_html( substr( $key, -6 ) ) . '</code>. Check that against the key on the Credentials page — if the last six characters differ, you are editing a different key than the site is sending.';
	}

	$lines[] = '<a href="' . esc_url( add_query_arg( 'yt-refresh', '1' ) ) . '">Clear the YouTube cache and retry</a> — results are cached, and a failed channel lookup is held for 6 hours, so a fix in Google Cloud will not show here until you do this.';

	$lines[] = '<small>Editors only. Visitors see nothing at all where this box is.</small>';

	return '<div class="yt-channel-empty"><p><strong>[yt-channel]</strong> has nothing to show.</p><p>' . implode( '</p><p>', $lines ) . '</p></div>';
}

/*--------------------------------------------------------------
# Cache flush
#
# Results are cached for 12h and a FAILED channel lookup is held for 6h, which
# is right for visitors and infuriating while you are fixing a key — without
# this, correcting the config in Google Cloud appears to do nothing for hours.
--------------------------------------------------------------*/

add_action( 'template_redirect', 'bp_yt_maybe_flush' );
function bp_yt_maybe_flush() {
	if ( ! isset( $_GET['yt-refresh'] ) || ! current_user_can( 'edit_posts' ) ) return;
	bp_yt_flush();
	wp_safe_redirect( remove_query_arg( 'yt-refresh' ) );
	exit;
}

function bp_yt_flush() {
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\_transient\_bp\_yt\_%'
		    OR option_name LIKE '\_transient\_timeout\_bp\_yt\_%'"
	);
	wp_cache_flush();
}

// The Load More button, and the signed payload that tells the AJAX handler which
// slice to return next. wp_hash() is what makes the nopriv endpoint safe to leave
// open: the only requests it answers are ones this site rendered itself.
function bp_yt_more_button( array $request, array $atts, $rendered ) {
	$request['offset'] = (int) $request['offset'] + (int) $rendered;
	$payload = wp_json_encode( [ 'request' => $request, 'atts' => $atts ] );

	return '<button type="button" class="yt-more"'
		. ' data-payload="' . esc_attr( base64_encode( $payload ) ) . '"'
		. ' data-sig="' . esc_attr( wp_hash( $payload ) ) . '">Load More Videos</button>';
}

// Shorts and title filtering, then ordering. Deliberately does NOT slice — the
// caller needs the full filtered length to know whether Load More has anything
// left to show.
function bp_yt_filter( array $videos, array $a, $shorts ) {
	if ( $shorts !== 'all' ) {
		// There is no Shorts flag in the API. Length is the only signal available
		// without a request per video, so this is a heuristic, not a fact — and it
		// can only be applied when durations were actually fetched.
		$cut = (int) apply_filters( 'bp_yt_shorts_seconds', 60 );
		$videos = array_values( array_filter( $videos, function( $v ) use ( $shorts, $cut ) {
			if ( $v['duration'] < 1 ) return $shorts === 'exclude';   // unknown length: keep it in the main grid
			return $shorts === 'only' ? ( $v['duration'] <= $cut ) : ( $v['duration'] > $cut );
		} ) );
	}

	$search = trim( (string) ( $a['search'] ?? '' ) );
	if ( $search !== '' ) {
		$videos = array_values( array_filter( $videos, function( $v ) use ( $search ) {
			return stripos( $v['title'], $search ) !== false || stripos( $v['description'], $search ) !== false;
		} ) );
	}

	switch ( $a['sort'] ?? 'newest' ) {
		case 'oldest':  $videos = array_reverse( $videos ); break;
		case 'popular': usort( $videos, function( $x, $y ) { return $y['views'] <=> $x['views']; } ); break;
		case 'longest': usort( $videos, function( $x, $y ) { return $y['duration'] <=> $x['duration']; } ); break;
		case 'random':  shuffle( $videos ); break;
	}

	return $videos;
}


/*--------------------------------------------------------------
# AJAX: load more
--------------------------------------------------------------*/

add_action( 'wp_ajax_bp_yt_more',        'battleplan_youtube_more_ajax' );
add_action( 'wp_ajax_nopriv_bp_yt_more', 'battleplan_youtube_more_ajax' );
function battleplan_youtube_more_ajax() {
	$payload = isset( $_POST['payload'] ) ? base64_decode( wp_unslash( $_POST['payload'] ) ) : '';
	$sig     = isset( $_POST['sig'] ) ? wp_unslash( $_POST['sig'] ) : '';

	// The signature is what makes this safe to leave open to nopriv: the only
	// requests we will answer are ones this site rendered itself.
	if ( $payload === '' || ! hash_equals( wp_hash( $payload ), $sig ) ) wp_send_json_error( 'bad request', 400 );

	$data = json_decode( $payload, true );
	if ( ! is_array( $data ) || empty( $data['request'] ) || empty( $data['atts'] ) ) wp_send_json_error( 'bad payload', 400 );

	$request = $data['request'];
	$atts    = $data['atts'];
	$offset  = (int) $request['offset'];
	$max     = (int) $request['max'];

	$feed   = bp_yt_get_feed( $request );
	$all    = bp_yt_filter( $feed['videos'] ?? [], $atts, $request['shorts'] );
	$videos = array_slice( $all, $offset, $max );

	$html = '';
	// Index 1+ so nothing in an appended batch claims .is-current — that belongs to
	// whatever is actually loaded in the feature stage.
	foreach ( $videos as $i => $v ) $html .= bp_yt_tile( $v, $atts, $i + 1 );

	// $videos being empty ends the paging, and has to: a button built from a
	// zero-length batch would advance the offset by nothing and hand the visitor
	// the same payload forever.
	$button = ( $videos && ( count( $all ) > $offset + count( $videos ) || ! empty( $feed['next'] ) ) )
		? bp_yt_more_button( $request, $atts, count( $videos ) )
		: '';

	wp_send_json_success( [ 'html' => $html, 'button' => $button ] );
}
