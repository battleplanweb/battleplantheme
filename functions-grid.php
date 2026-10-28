<?php
/* Battle Plan Web Design Functions: Grid

/*--------------------------------------------------------------
# Grid Set Up
--------------------------------------------------------------*/

// True when a chunk of rendered HTML has no actual content in it. A shortcode that
// found nothing to show returns '', but wpautop/the_content can leave empty <p>, <br>
// or &nbsp; residue behind it — that residue still counts as blank. Anything with a
// real tag (<img>, <a>, <div>) or visible text does not.
function bp_is_blank_html( $html ) {
	if ( $html === null ) return true;
	$strip = preg_replace( '/<!--.*?-->/s', '', $html );
	$strip = preg_replace( '/<\/?(p|br)\b[^>]*>/i', '', $strip );
	$strip = str_replace( array( '&nbsp;', "\xc2\xa0" ), ' ', $strip );
	return trim( $strip ) === '';
}

// Shared shortcode helpers ------------------------------------

// Is now inside the shortcode's start="" / end="" window? (any strtotime() string; blank = open-ended)
function bp_sc_in_window( $a ) {
	$now   = time();
	$start = strtotime( esc_attr( $a['start'] ?? '' ) );
	$end   = strtotime( esc_attr( $a['end'] ?? '' ) );
	return !( ( $start && $now < $start ) || ( $end && $now > $end ) );
}

// Pass any data-* attributes straight through (shortcode_atts() drops keys it doesn't know)
function bp_sc_data_attrs( $atts ) {
	$out = '';
	foreach ( is_array( $atts ) ? $atts : array() as $k => $v ) {
		if ( strpos( (string) $k, 'data-' ) === 0 ) $out .= ' ' . sanitize_key( $k ) . ( $v !== '' ? '="' . esc_attr( $v ) . '"' : '' );
	}
	return $out;
}

// track="x" → ' data-track="x"', and tags the element with the tracking class
function bp_sc_tracking( $a, &$class ) {
	$track = esc_attr( $a['track'] ?? '' );
	if ( $track === '' ) return '';
	$class .= ' tracking';
	return ' data-track="' . $track . '"';
}

// Inline style for css="" + background="" (+ left/top focal point)
function bp_sc_bg_style( $css, $background, $left, $top ) {
	if ( $background === '' && $css === '' ) return '';
	return ' style="' . $css . ( $background !== '' ? ' background: url(' . $background . ') ' . $left . '% ' . $top . '% no-repeat; background-size:cover;' : '' ) . '"';
}

// [href, link-class] for [img]/[btn]: get-biz="" wins over link=""; link="none" renders unlinked; PDFs get a cache-buster
function bp_sc_link( $a ) {
	$getBiz = esc_attr( $a['get-biz'] );
	if ( $getBiz !== '' ) return array( do_shortcode( '[get-biz info="' . $getBiz . '"]' ), '' );

	$link = esc_attr( $a['link'] );
	if ( in_array( $link, array( '', 'none', 'no' ), true ) ) return array( '', " class='no-link'" );
	if ( strpos( $link, 'pdf' ) ) $link .= '?id=' . time();
	return array( $link, '' );
}

function bp_sc_new_tab( $a ) {
	return in_array( esc_attr( $a['new-tab'] ), array( 'yes', 'true' ), true ) ? 'target="_blank"' : '';
}

// Expire Content
add_shortcode( 'expire', 'battleplan_expireContent' );
function battleplan_expireContent( $atts, $content = null ) {
	$a = shortcode_atts( array( 'start'=>'1980-01-01', 'end'=>'3000-12-31' ), $atts );
	return bp_sc_in_window( $a ) ? do_shortcode( $content ) : null;
}

// Restricted Content
add_shortcode( 'restrict', 'battleplan_restrictContent' );
function battleplan_restrictContent( $atts, $content = null ) {
	$a = shortcode_atts( array( 'max'=>'administrator', 'min'=>'none' ), $atts );

	// "admin" → administrator; anything else is a bp_ role (the prefix is optional)
	$role_name = fn( $r ) => in_array( $r, array( 'admin', 'administrator' ), true ) ? 'administrator' : ( strpos( $r, 'bp_' ) === 0 ? $r : 'bp_' . $r );

	// Highest legacy level_0..level_10 cap the role holds (0 for an unknown role)
	$level = function( $role ) {
		$caps  = get_role( (string) $role )?->capabilities ?? array();
		$level = 0;
		for ( $x = 0; $x <= 10; $x++ ) if ( ! empty( $caps[ 'level_' . $x ] ) ) $level = $x;
		return $level;
	};

	$user_level = $level( battleplan_getUserRole( '', 'name' ) );
	$in_range   = $user_level >= $level( $role_name( esc_attr( $a['min'] ) ) ) && $user_level <= $level( $role_name( esc_attr( $a['max'] ) ) );

	return $in_range ? do_shortcode( $content ) : '';
}

// Section
add_shortcode( 'section', 'battleplan_buildSection' );
function battleplan_buildSection( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'class'=>'', 'style'=>'', 'width'=>'', 'break'=>'', 'valign'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'css'=>'', 'hash'=>'', 'grid'=>'', 'preload'=>'' ), $atts );
	if ( ! bp_sc_in_window( $a ) ) return null;

	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name !== '' ? ' id="'.$name.'"' : '';
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$style = esc_attr($a['style']) !== '' ? ' style-'.esc_attr($a['style']) : '';
	$width = esc_attr($a['width']) !== '' ? ' section-'.esc_attr($a['width']) : '';
	$break = esc_attr($a['break']) !== '' ? ' break-'.esc_attr($a['break']) : '';
	$valign = esc_attr($a['valign']) !== '' ? ' valign-'.esc_attr($a['valign']) : '';
	$tracking = bp_sc_tracking( $a, $class );
	$background = esc_attr($a['background']);
	$hash = esc_attr($a['hash']) !== '' ? 'data-hash="'.esc_attr($a['hash']).'"' : '';

	$buildLayout = esc_attr($a['grid']) !== '' ? '<div class="flex grid-'.esc_attr($a['grid']).$valign.$break.$class.'">'.do_shortcode($content).'</div>' : do_shortcode($content);

	// LCP: when a [section] carries a background IMAGE, register it as the page's high-priority hero
	// preload — the SAME head <link rel="preload" as="image" fetchpriority="high"> that [parallax]
	// injects (via bp_inject_hero_preload). So a hand-built hero — [section background="…"] — is
	// discovered and fetched first, WITHOUT parallax's scroll drift or auto box-resizing (the section
	// keeps whatever height/overlay behaviour its own CSS gives it). Only the FIRST backgrounded
	// section per page claims the slot (the real LCP is above the fold); opt a decorative/lower one out
	// with preload="false". A CSS background can't use srcset, so it's a plain-URL preload of the one
	// image — keep that image reasonably sized since it serves every device.
	if ( $background !== '' && $a['preload'] !== 'false' && preg_match( '/\.(webp|jpe?g|png|avif|gif)(\?.*)?$/i', trim( $a['background'] ) ) ) {
		bp_register_hero_preload( trim( $a['background'] ) );
	}

	return '<section'.$name.' '.bp_sc_data_attrs($atts).' class="section'.$style.$width.$class.'" '.$hash.$tracking.bp_sc_bg_style( esc_attr($a['css']), $background, esc_attr($a['left']), esc_attr($a['top']) ).'>'.$buildLayout.'</section>';
}

/* Layout (Nested) — @deprecated 2026-08-02. DO NOT USE ON NEW BUILDS.
   Kept registered ONLY for back-compat with existing page content on
   Bubba's Cooks Country (#section-menu, #section-choose-location),
   Goot Bros Music and GuttenBoy, whose sections wrap [layout]s in a
   [nested]. Delete this whole block once those three are re-authored.

   Why it is deprecated: it was documented as "a sub-grid inside a [col]",
   which WordPress CANNOT parse — a shortcode may not be nested inside
   another shortcode of the SAME name, so get_shortcode_regex() closes the
   outer [col] on the first inner [/col]. The later cols escape as loose
   markup and a literal "[/nested] [/col]" prints on the page. It only ever
   worked as a direct child of [layout] (a grid ROW, not a sub-grid).

   Instead: flatten. One [layout] of [col]s, using h-span / v-span and the
   custom track notation (grid="1fr 1fr 1fr 3fr") for uneven shares. */
add_shortcode( 'nested', 'battleplan_buildNested' );
function battleplan_buildNested( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'grid'=>'1', 'break'=>'', 'valign'=>'', 'class'=>'', 'track'=>'' ), $atts );
	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name ? ' id="'.$name.'"' : '';
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$tracking = bp_sc_tracking( $a, $class );
	$valign = esc_attr($a['valign']) !== '' ? ' valign-'.esc_attr($a['valign']) : '';
	$break = esc_attr($a['break']) !== '' ? ' break-'.esc_attr($a['break']) : '';

	return '<div'.$name.' class="flex nested grid-'.esc_attr($a['grid']).$valign.$break.$class.'"'.$tracking.'>'.do_shortcode($content).'</div>';
}

// Layout
add_shortcode( 'layout', 'battleplan_buildLayout' );
function battleplan_buildLayout( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'grid'=>'1', 'gap'=>'', 'break'=>'', 'scroll'=>'', 'valign'=>'', 'align'=>'', 'class'=>'', 'track'=>'' ), $atts );
	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name ? ' id="'.$name.'"' : '';
	$grid = esc_attr($a['grid']);
	$custom_grid = '';
	if ( preg_match('/px|em|fr/', $grid) ) {
		$custom_grid = 'grid-template-columns: '.$grid.';';
		$grid = 'custom';
	}
	$gap = !empty($a['gap']) ? 'gap:' . esc_attr($a['gap']) . '; ' : '';
	$break = !empty($a['break']) ? ' break-' . esc_attr($a['break']) : '';
	$valign = !empty($a['valign']) ? ' valign-' . esc_attr($a['valign']) : '';
	$align = !empty($a['align']) ? ' align-' . esc_attr($a['align']) : '';
	$class = !empty($a['class']) ? ' ' . esc_attr($a['class']) : '';
	$tracking = bp_sc_tracking( $a, $class );

	// Swipe scroller — at its break point the row scrolls sideways instead of stacking, so a
	// row of cards stays card-sized on a phone (style-scroller.css + script-scroller.js).
	// scroll="true" uses the default slide width; scroll="65%" / scroll="18em" sets your own.
	// Needs break="1-4" — that class is what tells the CSS which break point to scroll at.
	$scroll = strtolower(trim(esc_attr($a['scroll'])));
	$swipe = $slide = '';
	if ( !in_array($scroll, ['', 'false', 'no'], true) && in_array(esc_attr($a['break']), ['1','2','3','4'], true) ) {
		$swipe = ' swipe';
		if ( !in_array($scroll, ['true', 'yes', '1'], true) && preg_match('/^[0-9.]+(px|em|rem|%|vw)$/', $scroll) ) {
			$slide = '--bp-slide-width:'.$scroll.'; ';
		}
		bp_enqueue_script( 'battleplan-script-scroller', 'script-scroller', ['battleplan-script-pages'] );
		bp_inline_minified_css( get_template_directory() . '/style-scroller.css' );
		bp_inline_minified_css( get_template_directory() . '/style-indicators.css' );
	}

	$style = $gap !== '' || $custom_grid !== '' || $slide !== '' ? ' style="'.$gap.' '.$custom_grid.' '.$slide.'"' : '';

	return '<div'.$name.' '.bp_sc_data_attrs($atts).' class="flex grid-'.$grid.$valign.$align.$break.$swipe.$class.'"'.$style.$tracking.'>'.do_shortcode($content).'</div>';
}

// Column
add_shortcode( 'col', 'battleplan_buildColumn' );
function battleplan_buildColumn( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'class'=>'', 'order'=>'', 'break'=>'', 'align'=>'', 'valign'=>'', 'h-span'=>'', 'v-span'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'css'=>'', 'hash'=>'', 'gap'=>'' ), $atts );
	if ( ! bp_sc_in_window( $a ) ) return null;

	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name !== '' ? ' id="'.$name.'"' : '';
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$style = esc_attr($a['order']) !== '' ? " style='order: ".esc_attr($a['order'])." !important;'" : '';
	$break = esc_attr($a['break']) !== '' ? ' break-'.esc_attr($a['break']) : '';
	$align = esc_attr($a['align']) !== '' ? " text-".esc_attr($a['align']) : '';
	$valign = esc_attr($a['valign']) !== '' ? " valign-".esc_attr($a['valign']) : '';
	$hSpan = esc_attr($a['h-span']) !== '' ? ' h-span-'.esc_attr($a['h-span']) : '';
	$vSpan = esc_attr($a['v-span']) !== '' ? ' v-span-'.esc_attr($a['v-span']) : '';
	$tracking = bp_sc_tracking( $a, $class );
	$hash = esc_attr($a['hash']) !== '' ? 'data-hash="'.esc_attr($a['hash']).'"' : '';

	// gap + css + background share ONE style attribute (a second style="" on the same tag is ignored)
	$innerCss = trim( ( esc_attr($a['gap']) !== '' ? 'gap:'.esc_attr($a['gap']).'; ' : '' ) . esc_attr($a['css']) );
	$innerStyle = bp_sc_bg_style( $innerCss, esc_attr($a['background']), esc_attr($a['left']), esc_attr($a['top']) );

	return '<div'.$name.' '.bp_sc_data_attrs($atts).' class="col '.$class.$align.$valign.$break.$hSpan.$vSpan.'" '.$tracking.$hash.$style.'><div class="col-inner"'.$innerStyle.'>'.do_shortcode($content).'</div></div>';
}

// Group & Text Blocks
add_shortcode('group', 'battleplan_buildGroup');
add_shortcode('txt',   'battleplan_buildGroup');
function battleplan_buildGroup($atts, $content=null, $tag=''){
	$a = shortcode_atts(['size'=>'100', 'class'=>'', 'start'=>'', 'end'=>'', 'order'=>'', 'track'=>''], $atts);
	if ( ! bp_sc_in_window( $a ) ) return null;

	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$tracking = bp_sc_tracking( $a, $class );
	$block = ($tag==='group') ? 'group' : 'text';

	return '<div class="block block-'.$block.' span-'.convertSize(esc_attr($a['size'])).$class.'"'.$tracking.'>'.do_shortcode($content).'</div>';
}

// Image Block
add_shortcode( 'img', 'battleplan_buildImg' );
function battleplan_buildImg( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'class'=>'', 'order'=>'', 'link'=>'', 'get-biz'=>'', 'new-tab'=>'', 'ada-hidden'=>'false', 'start'=>'', 'end'=>'', 'track'=>'' ), $atts );
	if ( ! bp_sc_in_window( $a ) ) return null;

	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$style = esc_attr($a['order']) !== '' ? " style='order: ".esc_attr($a['order'])." !important'" : '';
	[ $link, $linkClass ] = bp_sc_link( $a );
	$hidden = esc_attr($a['ada-hidden']) === "true" ? " aria-hidden='true' tabindex='-1'" : "";
	$tracking = bp_sc_tracking( $a, $class );

	$inner = do_shortcode($content);
	if ( $link !== '' ) $inner = '<a '.bp_sc_new_tab($a).$linkClass.' href="'.$link.'"'.$hidden.'>'.$inner.'</a>';

	return '<div class="block block-image span-'.convertSize(esc_attr($a['size'])).$class.'" '.$tracking.$style.'>'.$inner.'</div>';
}

// Vimeo poster frame, cached (the API call used to run on every render, with no timeout)
function bp_vimeo_thumb( $id ) {
	$key   = 'bp_vimeo_thumb_' . md5( $id );
	$thumb = get_transient( $key );
	if ( $thumb !== false ) return $thumb;

	$r     = wp_remote_get( 'https://vimeo.com/api/v2/video/' . rawurlencode( $id ) . '.json', array( 'timeout' => 5 ) );
	$data  = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ) );
	$thumb = ! empty( $data[0]->thumbnail_large ) ? str_replace( 'http:', '', $data[0]->thumbnail_large . '.jpg' ) : '';

	set_transient( $key, $thumb, $thumb !== '' ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
	return $thumb;
}

// Video Block
add_shortcode( 'vid', 'battleplan_buildVid' );
function battleplan_buildVid( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'mobile'=>'100', 'class'=>'', 'order'=>'', 'link'=>'', 'thumb'=>'', 'start'=>'', 'end'=>'', 'preload'=>'false', 'related'=>'false', 'fullscreen'=>'false', 'controls'=>'true', 'autoplay'=>'false', 'loop'=>'false', 'muted'=>'false', 'begin'=>'', 'track'=>'' ), $atts );
	if ( ! bp_sc_in_window( $a ) ) return null;

	bp_inline_minified_css( get_template_directory() . '/style-video.css' );
	// The facade that swaps these thumbnails for real iframes lives in
	// script-video.js (shared with [yt-channel] in includes-video.php). It used to
	// ride along in script-pages.js on every page of every site; it is enqueued on
	// demand now, so [vid] has to ask for it.
	bp_enqueue_script( 'battleplan-script-video', 'script-video', ['battleplan-script-pages'] );

	$size = convertSize(esc_attr($a['size']));
	$mobile = esc_attr($a['mobile']) !== '100' ? ' data-mobile-w="'.esc_attr($a['mobile']).'"' : '';
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$link = esc_attr($a['link']);
	$thumb = esc_attr($a['thumb']);
	$order = esc_attr($a['order']);
	$fullscreen = esc_attr($a['fullscreen']);
	$style = '';
	if ( $order !== '' || $fullscreen !== "false" ) {
		$style = ' style="'.( $order !== '' ? 'order: '.$order.'  !important; ' : '' ).( $fullscreen !== 'false' ? 'margin: 0; ' : '' ).'"';
	}
	$autoplay = esc_attr($a['autoplay']);
	$muted = $autoplay === 'true' ? 'true' : esc_attr($a['muted']);		// browsers block autoplay unless the video is muted
	$tracking = bp_sc_tracking( $a, $class );

	$isYouTube = strpos($link, 'youtube') !== false || strpos($link, 'youtu.be') !== false;

	if ( ( $isYouTube || strpos($link, 'vimeo') !== false ) && esc_attr($a['preload']) == "false" ) :
		if ( $isYouTube ) :
			$link = (strpos($link, '?si=') !== false) ? strstr($link, '?si=', true) : $link;
			$link = str_replace(['www.', 'https://youtu.be/', 'https://youtube.com/shorts/', 'https://youtube.com/embed/', 'https://youtube.com/watch?v='], '', $link);

			preg_match('/^[^?]+/', $link, $matches);
			$id = $matches[0] ?? '';
			$embed = 'https://www.youtube.com/embed/'.$id;

			$link = $embed.'?autoplay=1&enablejsapi=1&version=3&playerapiid=ytplayer';
			if ( esc_attr($a['related']) === "false" ) $link .= "&rel=0";
			if ( esc_attr($a['begin']) !== "" ) $link .= "&start=".esc_attr($a['begin']);

			$thumb = $thumb !== '' ? $thumb : '//i.ytimg.com/vi/'.$id.'/hqdefault.jpg';
		else:
			$id = str_replace(['https://player.vimeo.com/video/', 'https://vimeo.com/'], '', $link);
			$embed = 'https://player.vimeo.com/video/'.$id;
			$link .= "?autoplay=1&title=0&byline=0&portrait=0";
			if ( $thumb === '' ) $thumb = bp_vimeo_thumb( $id );
		endif;

		// schema.org wants an absolute URL. The auto-generated thumbs are
		// protocol-relative (//i.ytimg.com/...) and a hand-passed thumb="" is usually
		// a site-relative upload path — neither is valid as a thumbnailUrl.
		if ( strpos($thumb, '//') === 0 )     $schemaThumb = 'https:'.$thumb;
		elseif ( strpos($thumb, '/') === 0 )  $schemaThumb = home_url($thumb);
		else                                  $schemaThumb = $thumb;

		$schema = [
			'@context'     => 'https://schema.org',
			'@type'        => 'VideoObject',
			'name'         => get_the_title(),
			'description'  => get_the_excerpt() ?: get_the_title(),
			'thumbnailUrl' => $schemaThumb,
			'uploadDate'   => get_the_date('c'),
			'embedUrl'     => $embed,
		];

		$schemaBlock = '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

		return $schemaBlock . '<div class="block block-video span-'.$size.$class.' video-player"'.$tracking.$style.' data-thumb="'.$thumb.'" data-link="'.$link.'" data-id="'.$id.'"></div>';
	endif;

	$dotPos = strrpos($link, '.');
	$extension = substr($link, $dotPos + 1);

	$flags = '';
	foreach ( ['controls' => esc_attr($a['controls']), 'autoplay' => $autoplay, 'loop' => esc_attr($a['loop']), 'muted' => $muted] as $flag => $on ) {
		if ( $on == 'true' ) $flags .= $flag.' ';
	}

	$buildVid = '<div class="block block-video span-'.$size.$class.'"'.$tracking.$style.'>';
	$buildVid .= '<video playsinline '.$flags.'poster="'.$thumb.'" style="position:relative; top:0; left:0; width:100%; height:100%"'.$mobile.'>';
	$buildVid .= '<source src="'.$link.'" type="video/'.$extension.'">';
	if ( $extension === 'webm' ) $buildVid .= '<source src="'.substr($link, 0, $dotPos).'-ios.mp4" type="video/mp4">';	// fallback for iOS
	$buildVid .= '<img loading="lazy" src="'.$thumb.'">';
	$buildVid .= '</video></div>';

	return $buildVid;
}

// Button Block
add_shortcode( 'btn', 'battleplan_buildButton' );
function battleplan_buildButton( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'align'=>'center', 'order'=>'', 'link'=>'', 'get-biz'=>'', 'new-tab'=>'false', 'class'=>'', 'fancy'=>'false', 'icon'=>'false', 'top'=>0, 'left'=>0, 'before'=>'false', 'graphic'=>'false', 'graphic-w'=>'40', 'ada'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'click'=>'', 'onclick'=>'' ), $atts );
	if ( ! bp_sc_in_window( $a ) ) return null;

	$align = esc_attr($a['align']) !== 'center' ? " button-".esc_attr($a['align']) : '';
	$style = esc_attr($a['order']) !== '' ? " style='order: ".esc_attr($a['order'])." !important'" : '';
	[ $link ] = bp_sc_link( $a );
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';

	$icon = esc_attr($a['icon']) === 'false' ? '' : esc_attr($a['icon']);
	if ( $icon !== '' ) :
		bp_inline_minified_css( get_template_directory() . '/style-fancy-btn.css' );

		$fancy = trim($a['fancy']);
		$class .= " fancy".( $fancy !== '' ? '-'.esc_attr($fancy) : '' );
		$iconSpan = '<span class="fancy-icon">[get-icon type="'.( $icon === 'true' ? 'chevron-right' : $icon ).'" top="'.esc_attr($a['top']).'" left="'.esc_attr($a['left']).'"]</span>';
		$textSpan = '<span class="fancy-text">'.$content.'</span>';
		$content = esc_attr($a['before']) === 'false' ? $textSpan.$iconSpan : $iconSpan.$textSpan;
	endif;

	if ( esc_attr($a['graphic']) !== "false" ) {
		$graphicW = esc_attr($a['graphic-w']);
		$class .= " graphic-icon";
		$content = '<img src="/wp-content/uploads/'.esc_attr($a['graphic']).'" width="'.$graphicW.'" height="'.$graphicW.'" style="aspect-ratio:'.$graphicW.'/'.$graphicW.'" alt="" ><span class="unique">'.$content.'</span>';
	}

	$ada = esc_attr($a['ada']) !== '' ? ' <span class="screen-reader-text">'.esc_attr($a['ada']).'</span>' : '';

	// Auto-describe generic buttons ("Learn More", "Read More", …) that have no explicit
	// ada text, so screen readers + crawlers know where the link goes. The visible label is
	// unchanged; we append screen-reader-only context AFTER it (WCAG 2.5.3 "Label in Name" —
	// the visible words stay at the start of the accessible name). Skips if the builder set ada.
	if ( $ada === '' && $link !== '' ) {
		$visibleText = strtolower( trim( wp_strip_all_tags( strip_shortcodes( $content ) ) ) );
		$genericBtnText = array( 'learn more', 'read more', 'more', 'more info', 'details', 'view details', 'see details', 'view more', 'see more', 'click here', 'get started', 'find out more', 'discover more', 'shop now', 'explore' );
		if ( in_array( $visibleText, $genericBtnText, true ) ) {
			$destName = bp_btn_link_label( $link );
			if ( $destName !== '' ) $ada = ' <span class="screen-reader-text">: '.esc_html($destName).'</span>';
		}
	}
	$tracking = bp_sc_tracking( $a, $class );

	// click="x" → a tracked conversion click (fires conversion-x via script-tracking.js).
	// Lands on the <a> ONLY — $class goes on the wrapper div too, and a .track-clicks
	// wrapper would catch the bubbled click and double-count it.
	$clickTrack = esc_attr($a['click']) !== '' ? ' data-action="'.esc_attr($a['click']).'"' : '';
	$clickClass = $clickTrack !== '' ? ' track-clicks' : '';

	$onclick = esc_attr($a['onclick']);
	$inlineBtnID = 'btn_'.str_replace('/', '', $link);

	// The element is looked up by id but held in a plain local — the id itself (built from the link)
	// is rarely a valid JS identifier, so declaring it as the variable name was a syntax error.
	$script = $onclick !== ''
		? '<script nonce="'._BP_NONCE.'">document.addEventListener("DOMContentLoaded", function() { const btn = document.getElementById("'.$inlineBtnID.'"); if (btn) btn.addEventListener("click", function(event) { '.$onclick.' }); });</script>'
		: '';

	return $script.'<div class="block block-button span-'.convertSize(esc_attr($a['size'])).$class.$align.'"'.$tracking.$style.'><a id="'.$inlineBtnID.'" '.bp_sc_new_tab($a).' href="'.$link.'" class="button'.$class.$clickClass.'"'.$clickTrack.'>'.do_shortcode($content).$ada.'</a></div>';
}

// Resolve a button link to a human-readable destination name for the auto-ada label on
// generic buttons. Internal links use the target page's real title (keeps acronyms like
// "HVAC"/"AC" correct); anything unresolved falls back to the title-cased last path segment.
// Same-request static cache so repeated links (e.g. a row of "Learn More"s) resolve once.
function bp_btn_link_label( $link ) {
	static $cache = array();
	$link = trim( $link );
	if ( $link === '' ) return '';
	if ( isset( $cache[$link] ) ) return $cache[$link];

	foreach ( array( '#', 'tel:', 'mailto:', 'javascript:' ) as $skip ) {
		if ( stripos( $link, $skip ) === 0 ) return $cache[$link] = '';
	}

	$abs = ( stripos( $link, 'http' ) === 0 ) ? $link : home_url( '/' . ltrim( $link, '/' ) );

	// Internal page? Use its real title.
	$id = url_to_postid( $abs );
	if ( $id ) {
		$title = get_the_title( $id );
		if ( $title !== '' ) return $cache[$link] = $title;
	}

	// Fallback: title-case the last path segment of the slug.
	$path = wp_parse_url( $abs, PHP_URL_PATH );
	$seg  = $path ? basename( trim( $path, '/' ) ) : '';
	$seg  = str_replace( array( '-', '_' ), ' ', $seg );

	return $cache[$link] = ( $seg !== '' ? ucwords( $seg ) : '' );
}

// Strip srcset candidates whose physical files don't exist, so a stale size still listed in an
// image's attachment metadata (e.g. a crop from a removed add_image_size) never gets advertised
// in a <link rel="preload"> imagesrcset and 404s — wasting a high-priority fetch. Maps each
// candidate URL back to its filesystem path (handles year/month upload subdirs) and keeps only
// files that exist. Cheap: only runs on the handful of LCP/hero preload images per pageload.
function bp_prune_missing_srcset( $srcset ) {
	if ( ! is_string($srcset) || $srcset === '' ) return '';

	$up      = wp_upload_dir();
	$basedir = $up['basedir'];
	$baseurl = $up['baseurl'];
	$kept    = array();

	foreach ( explode( ',', $srcset ) as $cand ) {
		$cand = trim( $cand );
		if ( $cand === '' ) continue;

		$url  = preg_split( '/\s+/', $cand )[0];   // "https://…/img-640x296.webp 640w" → the URL
		$path = ( strpos( $url, $baseurl ) === 0 )
			? $basedir . substr( $url, strlen( $baseurl ) )
			: $basedir . '/' . basename( wp_parse_url( $url, PHP_URL_PATH ) );

		if ( is_file( $path ) ) $kept[] = $cand;
	}

	return implode( ', ', $kept );
}

// Accordion Block
add_shortcode( 'accordion', 'battleplan_buildAccordion' );
function battleplan_buildAccordion( $atts, $content = null ) {
	$a = shortcode_atts( array( 'title'=>'', 'excerpt'=>'', 'class'=>'', 'active'=>'false', 'btn'=>'false', 'btn_collapse'=>'false', 'icon'=>'true', 'start'=>'', 'end'=>'', 'scroll'=>'true', 'track'=>'', 'multiple'=>'true' ), $atts );
	if ( ! bp_sc_in_window( $a ) ) return null;

	bp_enqueue_script( 'battleplan-accordion', 'script-accordion' );
	bp_inline_minified_css( get_template_directory() . '/style-accordion.css' );

	$excerpt = esc_attr($a['excerpt']);
	if ( $excerpt !== '' ) $excerpt = '<div class="accordion-excerpt"><div class="accordion-box"><p>'.$excerpt.'</p></div></div>';
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$title = esc_attr($a['title']);
	$btnCollapse = esc_attr($a['btn_collapse']);
	$btn = esc_attr($a['btn']);
	$scroll = esc_attr($a['scroll']) == "true" ? "" : " no-scroll";
	$multiple = esc_attr($a['multiple']) === 'true' ? '' : ' data-multiple="false"';
	$tracking = bp_sc_tracking( $a, $class );
	$icon = ($v = esc_attr($a['icon'])) === 'true'
		? '<span class="accordion-icon" aria-hidden="true">[get-icon type="chevron-right"]</span>'
		: ($v === 'false' ? '' : '<span class="accordion-icon" aria-hidden="true">[get-icon type="'.$v.'"]</span>');

	$printTitle = $title ? '<h2 role="button" tabindex="0" class="accordion-title accordion-button'.$scroll.'">'.$icon.$title.'</h2>' : '';
	$addBtn = '';

	if ( $btn !== "false" ) :
		if ( $btnCollapse === "false" ) $btnCollapse = "hide";
		if ( $btn == "true" ) :
			$printTitle = '<div class="block block-button"><button role="button" tabindex="0" class="accordion-title accordion-button'.$scroll.'" data-text="'.$title.'" data-collapse="'.$btnCollapse.'">'.$title.'</button></div>';
		else:
			if ( $title ) $printTitle = '<h2 class="accordion-title">'.$icon.$title.'</h2>';
			$addBtn = '<div class="block block-button"><button role="button" tabindex="0" class="accordion-button'.$scroll.'" data-text="'.$btn.'" data-collapse="'.$btnCollapse.'">'.$btn.'</button></div>';
		endif;
	endif;

	if ( esc_attr($a['active']) != "false" ) $class .= " start-active";

	// Rendered once and reused for both the FAQ schema and the markup (it used to render twice)
	$body = do_shortcode($content);

	// FAQPage schema: a question-titled accordion is a Q&A pair. bp_output_faq_schema() emits them.
	if ( substr(trim($a['title']), -1) === '?' && bp_faq_schema_on() ) :
		$answer = trim( preg_replace('/\s+/', ' ', wp_strip_all_tags( $body ) ) );
		if ( $answer !== '' ) $GLOBALS['bp_faq_schema'][] = [ 'q' => bp_decode_text( trim($a['title']) ), 'a' => bp_decode_text($answer) ];
	endif;

	return '<div class="block block-accordion'.$class.$scroll.'"'.$multiple.$tracking.'>'.do_shortcode($printTitle.$excerpt).'<div class="accordion-content'.$scroll.'"><div class="accordion-box'.$scroll.'">'.$body.'</div></div>'.do_shortcode($addBtn).'</div>';
}

// Parallax Section
add_shortcode( 'parallax', 'battleplan_buildParallax' );
function battleplan_buildParallax( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'', 'type'=>'section', 'width'=>'edge', 'img-w'=>'2000', 'img-h'=>'1333', 'height'=>'800', 'padding'=>'50', 'pos-x'=>'50%', 'pos-x-mobile'=>'', 'top-y'=>0, 'bottom-y'=>0, 'image'=>'', 'image-mobile'=>'', 'mobile-breakpoint'=>'1024', 'stack'=>'false', 'class'=>'', 'fixed'=>'false', 'scroll-btn'=>'false', 'scroll-loc'=>'#page', 'scroll-icon'=>'chevron-down', 'z-index'=>'2', 'track'=>'' ), $atts );
	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name']))) ?: "section-".rand(10000,99999);
	$style = esc_attr($a['style']) !== '' ? " style-".esc_attr($a['style']) : '';
	$type = esc_attr($a['type']);
	$width = esc_attr($a['width']);
	$imgW = esc_attr($a['img-w']);
	$imgH = esc_attr($a['img-h']);
	$topY = esc_attr($a['top-y']);
	$botY = esc_attr($a['bottom-y']);
	$height = esc_attr($a['height']);
	if ( $height === "full" ) :
		$height = "100vh";
	elseif ( strpos($height, "calc") === false && $height !== "auto" ) :
		$height = $height."px";
	endif;
	$posX = esc_attr($a['pos-x']);
	$posXM = esc_attr($a['pos-x-mobile']);
	$padding = esc_attr($a['padding']);
	$image = esc_attr($a['image']);
	$hasImage = !empty($image);
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$buildScrollBtn = esc_attr($a['scroll-btn']) != "false" ? '<div class="scroll-down"><a href="'.esc_attr($a['scroll-loc']).'">[get-icon type="'.esc_attr($a['scroll-icon']).'" sr="Scroll Down"]</a></div>' : '';
	$tracking = bp_sc_tracking( $a, $class );

	$attachment_id = $hasImage ? attachment_url_to_postid(site_url().$image) : 0;
	$alt_text = $attachment_id ? get_post_meta($attachment_id, '_wp_attachment_image_alt', true) : '';

	// Optional art-directed mobile hero. srcset alone can't do this: it picks a different SIZE of the
	// same photo, never a different crop, so a wide hero whose subject sits off-centre has no way to
	// become a portrait-friendly frame on a phone. <picture> + <source media> does it in ONE cached
	// document - the browser decides, not the server - which is the only approach compatible with
	// EverCache (no Vary: User-Agent; see the is_mobile() notes in functions.php).
	//
	// Deliberately keyed to the SAME 1024px boundary where the parallax drift bails
	// (script-pages.js) and where --hero-x-m takes over (style-grid.css): below it the hero is a
	// plain static image, which is exactly the point at which swapping the file is safe and useful.
	$imageM        = esc_attr($a['image-mobile']);
	$hasImageM     = $hasImage && ! empty($imageM);
	$mobileBP      = (int) $a['mobile-breakpoint'] ?: 1024;
	$attachment_mid = $hasImageM ? attachment_url_to_postid(site_url().$imageM) : 0;

	// stack="true": below the breakpoint the hero stops being a backdrop and becomes a plain photo
	// with the copy UNDER it. Opt-in per hero — every existing hero keeps its overlay untouched.
	// A media query can't read mobile-breakpoint, so the rules ship as one class per standard
	// framework breakpoint (576/860/1024/1280) and the attribute snaps to the nearest. Swap and
	// stack MUST land on the same boundary: split them and there is a band where the phone-shaped
	// crop is still being cover-fitted as a backdrop behind overlay text, which is the worst of both.
	$stack = ! in_array( strtolower(trim(esc_attr($a['stack']))), array('', 'false', 'no', '0'), true );
	$stackBP = 576;
	foreach ( array(576, 860, 1024, 1280) as $bp ) {
		if ( abs($mobileBP - $bp) < abs($mobileBP - $stackBP) ) $stackBP = $bp;
	}

	$div = $type === "col" ? "div" : $type;

	// ONE markup path for every device. There is deliberately no is_mobile() branch here: EverCache
	// stores a single HTML entry per URL with no Vary: User-Agent, so whichever device warms the cache
	// is served to every other one (see the is_mobile() notes in functions.php). This function used to
	// return two completely different documents — CSS-background + PHP-picked 1280px file + pixel height
	// on mobile, responsive <img> + min-height on desktop — which meant a phone could warm the cache and
	// hand desktop visitors an upscaled 1280px backdrop, or vice versa.
	//
	// Everything that legitimately differs by device is now a viewport rule in style-grid.css, keyed off
	// the custom properties emitted below (--hero-h / --hero-pad / --hero-ar / --hero-x / --hero-x-m).
	// Same cached HTML everywhere; the breakpoint decides. The image size is no longer picked in PHP at
	// all — srcset/sizes lets the browser choose on width AND DPR, which it does better than a glob().
	$heroImg = $dataAttrs = $parallaxClass = '';
	if ( $hasImage ) {
		// Responsive <img> hero backdrop: the browser picks the file via srcset/sizes (native, width +
		// DPR aware); the parallax drift is a compositor transform on that same <img> (.hero-bg in
		// style-grid.css). Replaces the old CSS-background + .load-bg-img JS sizer (which double-loaded:
		// preload the original, then JS-fetch a responsive size).
		$srcset  = $attachment_id ? bp_prune_missing_srcset( wp_get_attachment_image_srcset( $attachment_id, 'full' ) ) : '';
		$srcsetM = $attachment_mid ? bp_prune_missing_srcset( wp_get_attachment_image_srcset( $attachment_mid, 'full' ) ) : '';

		// Preload the SAME candidate set the <img> resolves so the LCP image is fetched high-priority
		// and correctly sized - one download, no downgrade.
		//
		// With two art-directed sources each preload MUST carry the matching media query. A single
		// unconditional preload of the desktop file would make every phone fetch both: the preloaded
		// desktop one at high priority, then the mobile one that <source> actually matches. That is
		// strictly worse than having no mobile image at all, and on the LCP element.
		if ( $hasImageM ) {
			bp_register_hero_preload( $imageM, $srcsetM ?: '', '100vw', '(max-width:' . $mobileBP . 'px)' );
			bp_register_hero_preload( $image,  $srcset,       '100vw', '(min-width:' . ( $mobileBP + 1 ) . 'px)' );
		} else {
			bp_register_hero_preload( $image, $srcset, '100vw' );
		}

		$heroImg = '<img class="hero-bg" src="' . esc_url( $image ) . '"'
			. ( $srcset ? ' srcset="' . esc_attr( $srcset ) . '" sizes="100vw"' : '' )
			. ' width="' . esc_attr( $imgW ) . '" height="' . esc_attr( $imgH ) . '"'
			. ' alt="' . esc_attr( $alt_text ) . '" fetchpriority="high" decoding="async">';

		// The <img> keeps class="hero-bg" and every attribute it had, so the drift JS and the
		// .hero-bg CSS bind exactly as before; <picture> only intercepts which FILE that <img>
		// loads. No image-mobile = byte-identical markup to before this attribute existed.
		if ( $hasImageM ) {
			$heroImg = '<picture class="hero-picture">'
				. '<source media="(max-width:' . $mobileBP . 'px)" srcset="'
				. esc_attr( $srcsetM ?: $imageM ) . '"' . ( $srcsetM ? ' sizes="100vw"' : '' ) . '>'
				. $heroImg
				. '</picture>';
		}

		// No data-parallax="scroll": that triggers the old parallaxBG auto-loop in script-desktop.js
		// (which reads data-image-src etc.). The <img class="hero-bg"> hero uses the CSS view() drift
		// / .hero-bg JS fallback instead.
		$dataAttrs     = ' data-img-height="' . $imgH . '" data-pos-x="' . $posX . '" data-top-y="' . $topY . '" data-bottom-y="' . $botY . '"';
		// hero-empty reproduces the old mobile branch's "no content, so fall back to the image's own
		// proportions" height; with content the hero grows instead, same as it always did.
		$parallaxClass = ' ' . $type . '-parallax' . ( trim($content) === '' ? ' hero-empty' : '' )
			. ( $stack ? ' hero-stack hero-stack-' . $stackBP : '' );
	}

	// Box metrics as custom properties, NOT as concrete inline declarations: an inline declaration
	// outranks every stylesheet rule, so an inline min-height/object-position would win at all widths
	// and the mobile media query could never take over.
	// --hero-ar comes from the attachment's REAL dimensions where we can get them, not from img-w/img-h:
	// those are author-supplied and default to 2000/1333, so a hero that omits them would size its
	// no-content mobile box to a 3:2 image it isn't. The old mobile branch read the generated file's
	// height, so it self-corrected; this keeps that property. Falls back to the declared attrs.
	$heroAR = $imgW . '/' . $imgH;
	if ( $hasImage && $attachment_id ) {
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty($meta['width']) && ! empty($meta['height']) ) $heroAR = (int) $meta['width'] . '/' . (int) $meta['height'];
	}

	// A mobile image with different proportions needs its own ratio, or an empty hero on a phone
	// sizes its box to the DESKTOP image's shape and the swapped-in file is letterboxed or cropped.
	$heroARM = '';
	if ( $hasImageM && $attachment_mid ) {
		$metaM = wp_get_attachment_metadata( $attachment_mid );
		if ( ! empty($metaM['width']) && ! empty($metaM['height']) ) $heroARM = (int) $metaM['width'] . '/' . (int) $metaM['height'];
	}

	$heroVars = '--hero-h:' . $height . ';--hero-pad:' . $padding . 'px;--hero-x:' . $posX . ';'
		. ( $posXM !== '' ? '--hero-x-m:' . $posXM . ';' : '' )
		. ( $hasImage ? '--hero-ar:' . $heroAR . ';' : '' )
		. ( $heroARM !== '' ? '--hero-ar-m:' . $heroARM . ';' : '' );

	// min-height (not height) so a hero with tall content grows instead of clipping under
	// overflow:hidden; the absolutely-positioned .hero-bg still fills whatever height results.
	return do_shortcode( '<' . $div . ' id="' . $name . '" class="hero-box ' . $type . $style . ' ' . $type . '-' . $width . $parallaxClass . $class . '"' . $tracking . ' style="' . esc_attr( $heroVars ) . '"' . $dataAttrs . '>' . $heroImg . $content . ( $hasImage ? $buildScrollBtn : '' ) . '</' . $div . '>'  );
}

/*--------------------------------------------------------------
# Cache-safe LCP hero preload
--------------------------------------------------------------*/
// The hero's exact image URL is only known when the [parallax]/[section] hero renders in the BODY —
// which is AFTER header.php has already emitted the <head>. So the bp_preload_images accumulator can
// never get the hero image into the <head> on a first (cacheable) render, and under full-page cache
// (EverCache) the hero is therefore never preloaded — it starts late, at default priority, and on a
// throttled mobile connection it becomes the slow LCP. Fix: the first hero records its URL here, and
// the framework's existing whole-page `final_output` filter (functions.php) injects a high-priority
// <link rel=preload> right after <head> — so it lands in the cached HTML, discovered before anything.
function bp_register_hero_preload( $url, $srcset = '', $sizes = '', $media = '' ) {
	if ( empty($url) ) return;

	// First hero on the page still wins. The one exception: an art-directed hero registers TWO
	// entries (one per media query) that both describe that same first hero, so exactly one
	// follow-up call is accepted to complete the pair. A later hero, or a stray third call, is
	// ignored - otherwise a page with two art-directed heroes would preload four images.
	if ( empty($GLOBALS['bp_hero_preload']) ) {
		$GLOBALS['bp_hero_preload'] = array( 'items' => array(), 'media_scoped' => ( $media !== '' ) );
	} else {
		$store     = $GLOBALS['bp_hero_preload'];
		$completes = $media !== '' && ! empty($store['media_scoped']) && count( $store['items'] ?? array() ) < 2;
		if ( ! $completes ) return;
	}

	$GLOBALS['bp_hero_preload']['items'][] = array(
		'url' => $url, 'srcset' => $srcset, 'sizes' => $sizes, 'media' => $media,
	);
}

add_filter('final_output', 'bp_inject_hero_preload', 5);
function bp_inject_hero_preload( $html ) {
	if ( empty($GLOBALS['bp_hero_preload']) || ! is_string($html) || stripos($html, '</head>') === false ) return $html;

	$hero = $GLOBALS['bp_hero_preload'];

	// Three shapes over time: a plain string (legacy callers), a single assoc array, and the
	// current {items:[...]} list that an art-directed hero needs. Normalise, don't branch downstream.
	if ( is_string($hero) ) {
		$items = array( array( 'url' => $hero, 'srcset' => '', 'sizes' => '', 'media' => '' ) );
	} elseif ( isset($hero['items']) ) {
		$items = $hero['items'];
	} else {
		$items = array( $hero + array( 'srcset' => '', 'sizes' => '', 'media' => '' ) );
	}

	$link = '';
	foreach ( $items as $item ) {
		$url = $item['url'] ?? '';
		if ( ! $url ) continue;
		$srcset = $item['srcset'] ?? '';
		$sizes  = $item['sizes']  ?? '';
		$media  = $item['media']  ?? '';

		// Responsive preload when a srcset exists so the preloader fetches the SAME candidate the
		// <img srcset> resolves; href stays as fallback for browsers ignoring imagesrcset.
		$attr = $srcset
			? 'imagesrcset="' . esc_attr($srcset) . '"' . ( $sizes ? ' imagesizes="' . esc_attr($sizes) . '"' : '' ) . ' href="' . esc_url($url) . '"'
			: 'href="' . esc_url($url) . '"';

		// media= on a preload link is what stops a phone downloading the desktop hero too.
		if ( $media !== '' ) $attr .= ' media="' . esc_attr($media) . '"';

		$link .= '<link rel="preload" as="image" ' . $attr . ' fetchpriority="high">' . "\n";
	}
	if ( $link === '' ) return $html;

	// Inject at the END of <head>, after the self-hosted font preloads, so fonts keep priority on the
	// throttled pipe; the hero preload is still discovered during head parse regardless of position.
	return str_ireplace('</head>', $link . '</head>', $html);
}


/*--------------------------------------------------------------
# Header/footer chrome: logo priority + logo alt-text fallback
--------------------------------------------------------------*/
// Two fixes that both key off "are we rendering the site chrome (masthead or footer), where the LOGO
// is the only attachment image":
//
//   1. fetchpriority — WordPress core (6.3+) auto-stamps fetchpriority="high" on the first sizeable
//      <img> it renders, guessing it's the LCP. On our sites that's the header LOGO, but the real LCP
//      is the server-rendered hero background, which we already preload high ourselves
//      (bp_inject_hero_preload above). Core's guess hands the single high-priority slot to the logo,
//      so we strip it back while the chrome renders — the hero preload wins.
//
//   2. alt text — the logo is frequently uploaded with a blank alt, so it (and the home link that
//      wraps it) reads as silence to screen readers and gives Google no context. When a chrome image
//      has an empty alt, fall it back to the business name.
//
// Scoped to the masthead + colophon only. Body content images — hero/grid images set their priority
// as raw HTML (not through this filter), and real content images carry their own alt — are untouched,
// so a deliberately decorative alt="" in the page body still stays empty.
add_action( 'bp_before_masthead', function () { $GLOBALS['bp_in_chrome'] = true; },  PHP_INT_MIN );
add_action( 'bp_after_masthead',  function () { $GLOBALS['bp_in_chrome'] = false; }, PHP_INT_MAX );
add_action( 'bp_before_colophon', function () { $GLOBALS['bp_in_chrome'] = true; },  PHP_INT_MIN );
add_action( 'bp_after_colophon',  function () { $GLOBALS['bp_in_chrome'] = false; }, PHP_INT_MAX );
add_filter( 'wp_get_attachment_image_attributes', function ( $attr ) {
	if ( empty( $GLOBALS['bp_in_chrome'] ) ) return $attr;

	// (1) Don't let the logo hold the LCP's high-priority slot.
	if ( ( $attr['fetchpriority'] ?? '' ) === 'high' ) unset( $attr['fetchpriority'] );

	// (2) Give a blank-alt logo the business name so it isn't silent.
	if ( '' === trim( (string) ( $attr['alt'] ?? '' ) ) ) {
		$name = trim( (string) ( customer_info()['name'] ?? '' ) ) ?: get_bloginfo( 'name' );
		if ( '' !== $name ) $attr['alt'] = bp_decode_text( $name );   // WP esc_attr's attributes on output
	}

	return $attr;
}, 99 );

// Locked Section
add_shortcode( 'lock', 'battleplan_buildLockedSection' );
function battleplan_buildLockedSection( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'lock', 'width'=>'edge', 'position'=>'bottom', 'delay'=>'3000', 'show'=>'session', 'css'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'class'=>'', 'start'=>'', 'end'=>'', 'btn-activated'=>'no', 'track'=>'', 'content'=>'text', 'pages'=>'', 'exclude'=>'', 'exit'=>'no', 'scroll'=>'', 'device'=>'all', 'idle'=>'', 'visitor'=>'all', 'visits'=>'' ), $atts );
	if ( ! bp_sc_in_window( $a ) ) return null;

	// Page targeting (server-side): on off-target pages we ship nothing at all.
	//   pages="contact, about"   → show ONLY on these slugs
	//   exclude="checkout"       → show everywhere EXCEPT these slugs
	$slug = strtolower((string)_PAGE_SLUG);
	if ( $slug === '' && is_front_page() ) $slug = 'home';
	$only = array_filter( array_map('trim', explode(',', strtolower((string)$a['pages']))) );
	$not  = array_filter( array_map('trim', explode(',', strtolower((string)$a['exclude']))) );
	if ( $only && ! in_array($slug, $only, true) ) return null;
	if ( $not  &&   in_array($slug, $not,  true) ) return null;

	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name ? " id='".$name."'" : "";
	$width = esc_attr($a['width']) != '' ? " section-".esc_attr($a['width']) : '';
	$pos = esc_attr($a['position']);
	$class = ( esc_attr($a['class']) != '' ? " ".esc_attr($a['class']) : '' )." position-".$pos;
	$style = esc_attr($a['style']) == "lock" ? "" : " style-".esc_attr($a['style']);
	$contentType = esc_attr($a['content']) == 'text' ? ' content-text' : ' content-image';
	$tracking = bp_sc_tracking( $a, $class );
	$btnActivated = in_array(esc_attr($a['btn-activated']), ['true', 'yes'], true) ? 'yes' : esc_attr($a['btn-activated']);

	// Triggers + targeting: exit-intent (desktop only), scroll-depth (percent), idle (seconds),
	// device, new/returning visitor, and a minimum-pageviews gate.
	$exit    = in_array(strtolower((string)$a['exit']), ['yes','true','1'], true) ? 'yes' : 'no';
	$scroll  = preg_replace('/[^0-9]/', '', (string)$a['scroll']);  // percent, e.g. "50"
	$idle    = preg_replace('/[^0-9]/', '', (string)$a['idle']);    // seconds of inactivity
	$device  = in_array(strtolower((string)$a['device']),  ['desktop','mobile'],   true) ? strtolower((string)$a['device'])  : 'all';
	$visitor = in_array(strtolower((string)$a['visitor']), ['new','returning'],    true) ? strtolower((string)$a['visitor']) : 'all';
	$visits  = preg_replace('/[^0-9]/', '', (string)$a['visits']);  // show only on/after Nth pageview

	$buildSection = '<section'.$name.' class="section section-lock style-lock'.$style.$width.$contentType.$class.'"'.$tracking.' data-pos="'.$pos.'" data-delay="'.esc_attr($a['delay']).'" data-show="'.esc_attr($a['show']).'" data-btn="'.$btnActivated.'" data-exit="'.$exit.'" data-scroll="'.$scroll.'" data-idle="'.$idle.'" data-device="'.$device.'" data-visitor="'.$visitor.'" data-visits="'.$visits.'"';
	$buildSection .= bp_sc_bg_style( esc_attr($a['css']), esc_attr($a['background']), esc_attr($a['left']), esc_attr($a['top']) );
	$buildSection .= '><button class="closeBtn" aria-label="close">[get-icon type="x-large"]</button>'.$content.'</section>';

	return do_shortcode($buildSection);
}

add_shortcode( 'seek', 'battleplan_formField' );
function battleplan_formField( $atts, $content = null ) {
	$a = shortcode_atts( array( 'label'=>'Label', 'show'=>'true', 'label-pos'=>'before', 'label-valign'=>'baseline', 'id'=>'user-input', 'req'=>'false', 'width'=>'default', 'max-w'=>'', 'class'=>'', 'value'=>''), $atts );
	$id = esc_attr($a['id']);
	$label = esc_attr($a['label']);
	$labelPos = esc_attr($a['label-pos']);
	$labelValign = esc_attr($a['label-valign']) === 'bottom' ? 'baseline' : esc_attr($a['label-valign']);
	$req = esc_attr($a['req']) != 'false';
	$width = 'width-'.esc_attr($a['width']);
	$maxW = esc_attr($a['max-w']) != '' ? ' style="margin:0 auto; max-width:'.esc_attr($a['max-w']).'"' : '';
	$value = esc_attr($a['value']) !== '' ? ' data-value="'.esc_attr($a['value']).'"' : '';
	$class = esc_attr($a['class']) != '' ? " ".esc_attr($a['class']) : '';
	$show = esc_attr($a['show']) == 'true';
	$aria = '';
	if ( ! $show ) { $width = 'width-none'; $aria = 'aria-label="'.$label.'"'; }
	$asterisk = '<span class="required"></span><span class="sr-only">Required Field</span>';

	$content = do_shortcode((string)$content);

	if ( $label == "button" ) return '<div class="block block-button block-100">'.$content.'</div>';

	// Floating (label-inside-the-field) labels only make sense for text-like
	// controls that have a placeholder state (text, email, tel, textarea, etc.)
	// and for selects. Radios, checkboxes and file inputs have no placeholder,
	// so a floated label would just sit on top of the control (see the
	// # Floating labels block in style-forms.css). Detect those via the
	// data-type the field shortcodes emit on span.bp-control-wrap and auto-add
	// `form-label-outside`, which every :not() in the float CSS already honors —
	// so radio/checkbox fields revert to classic outside labels with no per-field
	// class needed. label-pos="after" (single-checkbox layout) is already excluded.
	if ( $labelPos != 'after' && preg_match('/data-type="(?:radio|checkbox|checkboxes|file)"/', $content) ) {
		$class .= ' form-label-outside';
	}

	$labelHtml = $show ? '<label for="'.$id.'" class="'.$width.' label-'.$labelValign.'">'.$label.( $req ? $asterisk : '' ).'</label>' : '';

	// removed col from classes 5/19 for animation purposes
	$buildInput = '<div class="form-input input-'.strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(" ","-",$label))).' input-'.$id.' label-pos-'.$labelPos.' '.$width.$class.'"'.$value.$maxW.' '.$aria.'>';
	$buildInput .= $labelPos == 'after' ? $content.$labelHtml : $labelHtml.$content;
	if ( ! $show && $req ) $buildInput .= $asterisk;
	$buildInput .= '</div>';

	return $buildInput;
}

// Widgets
add_shortcode( 'widget', 'battleplan_buildWidget' );
function battleplan_buildWidget( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'basic', 'title'=>'hide', 'lock'=>'none', 'priority'=>'2', 'set'=>'none', 'class'=>'', 'show'=>'', 'hide'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'intro'=>'', 'empty'=>'hide'), $atts );
	if ( ! bp_sc_in_window( $a ) ) return null;

	$type = strtolower(preg_replace("/[\s_]/", "-", esc_attr($a['type'])));
	$title = esc_attr($a['title']);
	$lock = esc_attr($a['lock']);
	$priority = esc_attr($a['priority']);
	$set = esc_attr($a['set']);
	$class = esc_attr($a['class']);
	$addHide = $addClass = $name = '';
	$tracking = bp_sc_tracking( $a, $class );

	switch ( $type ) {
		case 'form':
			$lock = 'top'; $priority = '4'; $addHide = '404, contact, review';
			if ( $title == 'hide' ) $title = 'Service Request';
			// Widget form column: the site's primary form when one is declared (bp_set_primary_form),
			// else the standard quote form this column has always defaulted to.
			if ( $content == null ) $content = bp_primary_form_or('[bp-quote-form]');
			break;

		case 'brand-logo':
			$lock = 'top'; $priority = '4';
			if ( $content == null ) $content = '[get-brand-logo]';
			break;

		case 'financing':
			$priority = '3';
			break;

		case 'customer-care':
			$addClass = ' widget-image'; $addHide = 'customer-care';
			if ( $content == null ) {
				$brand = customer_info()['site-brand'] ?? '';
				$care  = array( 'american standard' => '[get-customer-care]', 'ruud' => '[ruud-pro-partner]', 'comfortmaker' => '[get-comfortmaker-elite-dealer]', 'tempstar' => '[get-tempstar-elite-dealer]' );
				$content = $care[ strtolower( trim( is_array( $brand ) ? ( $brand[0] ?? '' ) : (string) $brand ) ) ] ?? $content;
			}
			break;

		case 'symptom-checker':
			$addClass = ' widget-image'; $priority = '1'; $addHide = 'symptom-checker';
			if ( $content == null ) $content = '[get-symptom-checker]';
			break;

		case 'credit-cards':
			$addClass = ' widget-image'; $lock = 'bottom'; $priority = '3';
			if ( $content == null ) $content = '[get-credit-cards]';
			break;

		case 'event':
			$addClass = ' widget-image'; $addHide = 'event';
			break;

		case 'topper':
			$addClass = ' widget-image'; $priority = '5'; $lock = 'top';
			break;

		case 'filler':
			$priority = '5'; $content = "&nbsp;";
			break;
	}

	if ( $type == "menu" ) :
		$buildWidget = '<div id="desktop-navigation" class="widget widget-navigation widget-priority-5 lock-to-top widget_nav_menu hide-1 hide-2"'.$tracking.' data-priority="5">';
		if ( $title != "hide" ) $buildWidget .= '<h3 class="widget-title">'.$title.'</h3>';
		$buildWidget .= wp_nav_menu ( array ( 'echo' => false, 'container' => 'div', 'container_class' => 'menu-main-menu-container', 'menu_id' => 'main-menu-menu', 'menu_class' => 'menu', 'theme_location' =>'widget-menu', 'walker' => new Aria_Walker_Nav_Menu(), ) );
		$buildWidget .= '</div>';
	else:
		if ( $title != 'hide' ) $name = ' widget-'.strtolower(preg_replace("/[\s_]/", "-", $title));

		$buildClasses = 'widget widget-'.$type.$addClass.$name.' widget-priority-'.$priority;
		if ( $set != "none" ) $buildClasses .= ' widget-set set-'.$set;
		if ( $lock != "none" ) $buildClasses .= ' lock-to-'.$lock;
		if ( $class != "" ) $buildClasses .= ' '.$class;

		$widgetContent = do_shortcode($content);

		// Nothing came back (no upcoming events, no testimonials, etc) - drop the whole
		// widget rather than leaving an empty box in the sidebar. Opt out with empty="show".
		if ( esc_attr($a['empty']) != 'show' && $type != 'filler' && bp_is_blank_html($widgetContent) ) return null;

		$buildWidget = '<div class="'.$buildClasses.'"'.$tracking.'>';
		if ( $title != "hide" ) $buildWidget .= '<h3 class="widget-title">'.$title.'</h3>';
		if ( esc_attr($a['intro']) !== '' ) $buildWidget .= '<div class="widget-intro">'.esc_attr($a['intro']).'</div>';
		$buildWidget .= '<div class="widget-content">'.$widgetContent.'</div>';
		$buildWidget .= '</div>';
	endif;

	// show="" / hide="" take comma-separated URL fragments, plus the special "home" and "404"
	$bodyClass = null;
	$on_page = function( $list ) use ( &$bodyClass ) {
		foreach ( explode( ',', str_replace( ' ', '', $list ) ) as $check ) {
			if ( $check === '' ) continue;
			if ( strpos( _PAGE_SLUG_FULL, $check ) !== false ) return true;
			if ( $check === '404' || $check === 'home' ) {
				$bodyClass ??= get_body_class();
				if ( in_array( $check === '404' ? 'error404' : 'home', $bodyClass ) ) return true;
			}
		}
		return false;
	};

	$show = esc_attr($a['show']);
	$hide = implode( ', ', array_filter( array( esc_attr($a['hide']), $type == 'menu' ? '' : $addHide ) ) );

	if ( $show !== '' && ! $on_page( $show ) ) return null;
	if ( $hide !== '' && $on_page( $hide ) ) return null;

	return $buildWidget;
}
