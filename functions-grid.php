<?php
/* Battle Plan Web Design Functions: Grid

/*--------------------------------------------------------------
# Grid Set Up
--------------------------------------------------------------*/

// Expire Content
add_shortcode( 'expire', 'battleplan_expireContent' );
function battleplan_expireContent( $atts, $content = null ) {
	$a = shortcode_atts( array( 'start'=>'1980-01-01', 'end'=>'3000-12-31' ), $atts );
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	$now = time();
	if ( $now > $start && $now < $end ) { return do_shortcode($content); } else { return null; }
}

// Restricted Content
add_shortcode( 'restrict', 'battleplan_restrictContent' );
function battleplan_restrictContent( $atts, $content = null ) {
	$a = shortcode_atts( array( 'max'=>'administrator', 'min'=>'none' ), $atts );
	$max = esc_attr($a['max']);
	if ( $max == "admin" || $max == "administrator" ) : $max = "administrator"; else: if ( substr($min, 0, 3) !== "bp_" ) : $max = "bp_".$max; endif; endif;
	$min = esc_attr($a['min']);
	if ( $min == "admin" || $min == "administrator" ) : $min = "administrator"; else: if ( substr($min, 0, 3) !== "bp_" ) : $min = "bp_".$min; endif; endif;
	$role = battleplan_getUserRole( '', 'name' );
	$user_caps = get_role( $role )->capabilities;
	$max_caps = get_role( $max )->capabilities;
	$min_caps = get_role( $min )->capabilities;
	$max_level = $min_level = $user_level = 0;

	for ($x = 0; $x <= 10; $x++) {
		if ( $max_caps['level_'.$x] == 1 || $max_caps['level_'.$x] == true ) $max_level = $x;
		if ( $min_caps['level_'.$x] == 1 || $min_caps['level_'.$x] == true ) $min_level = $x;
		if ( $user_caps['level_'.$x] == 1 || $user_caps['level_'.$x] == true ) $user_level = $x;
	}

	if ( $user_level >= $min_level && $user_level <= $max_level ) : return do_shortcode($content);
	else: return "";
	endif;
}

// Section
add_shortcode( 'section', 'battleplan_buildSection' );
function battleplan_buildSection( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'class'=>'', 'style'=>'', 'width'=>'', 'break'=>'', 'valign'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'css'=>'', 'hash'=>'', 'grid'=>'' ), $atts );
	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name !== '' ? ' id="'.$name.'"' : '';
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$style = esc_attr($a['style']) !== '' ? ' style-'.esc_attr($a['style']) : '';
	$width = esc_attr($a['width']) !== '' ? ' section-'.esc_attr($a['width']) : '';
	$break = esc_attr($a['break']) !== '' ? ' break-'.esc_attr($a['break']) : '';
	$valign = esc_attr($a['valign']) !== '' ? ' valign-'.esc_attr($a['valign']) : '';
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	if ( $start || $end ) {
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;
	}
	$tracking = esc_attr($a['track']) !== '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking !== '' ) $class .= " tracking";
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$css = esc_attr($a['css']);
	$hash = esc_attr($a['hash']) !== '' ? 'data-hash="'.esc_attr($a['hash']).'"' : '';

	$data_attrs = '';
	foreach ( $atts as $k => $v ) {
		if ( strpos($k, 'data-') === 0 ) {
			$k = sanitize_key($k);
			$data_attrs .= $v !== '' ? ' ' . $k . '="' . esc_attr($v) . '"' : ' ' . $k;
		}
	}

	$buildLayout = esc_attr($a['grid']) !== '' ? '<div class="flex grid-'.esc_attr($a['grid']).$valign.$break.$class.'">'.do_shortcode($content).'</div>' : do_shortcode($content);

	$buildSection = '<section'.$name.' '.$data_attrs.' class="section'.$style.$width.$class.'" '.$hash.$tracking;
	if ( $background !== '' || $css !== '' ) {
		$buildSection .= ' style="';
		if ( $css !== '' ) $buildSection .= $css;
		if ( $background !== '' ) $buildSection .= ' background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;';
		$buildSection .= '"';
	}
	$buildSection .= '>'.$buildLayout.'</section>';

	return $buildSection;
}

// Layout (Nested)
add_shortcode( 'nested', 'battleplan_buildNested' );
function battleplan_buildNested( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'grid'=>'1', 'break'=>'', 'valign'=>'', 'class'=>'', 'track'=>'' ), $atts );
	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name ? ' id="'.$name.'"' : '';
	$grid = esc_attr($a['grid']);
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";
	$break = esc_attr($a['break']);
	$valign = esc_attr($a['valign']);
	if ( $valign != '' ) $valign = " valign-".$valign;
	if ( $break != '' ) $break = " break-".$break;

	return '<div'.$name.' class="flex nested grid-'.$grid.$valign.$break.$class.'" '.$tracking.'">'.do_shortcode($content).'</div>';
}

// Layout
add_shortcode( 'layout', 'battleplan_buildLayout' );
function battleplan_buildLayout( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'grid'=>'1', 'gap'=>'', 'break'=>'', 'valign'=>'', 'class'=>'', 'track'=>'' ), $atts );
	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name ? ' id="'.$name.'"' : '';
	$grid = esc_attr($a['grid']);
	if ( strpos($grid,'px') !== false || strpos($grid,'em') !== false || strpos($grid,'fr') !== false ) {
		$custom_grid = 'grid-template-columns: '.$grid.';';
		$grid = 'custom';
	} else {
		$custom_grid = '';
	}
	$gap = !empty($a['gap']) ? 'gap:' . esc_attr($a['gap']) . '; ' : '';
	$break = !empty($a['break']) ? ' break-' . esc_attr($a['break']) : '';
	$valign = !empty($a['valign']) ? ' valign-' . esc_attr($a['valign']) : '';
	$class = !empty($a['class']) ? ' ' . esc_attr($a['class']) : '';
	$data = !empty($a['data']) ? ' data-' . esc_attr($a['data']) : '';
	$tracking = !empty($a['track']) ? ' data-track="' . esc_attr($a['track']) . '"'	: '';
	if ( $tracking !== '' ) $class .= " tracking";

	$style = $gap !== '' || $custom_grid !== '' ? 'style="'.$gap.' '.$custom_grid.'"' : '';

	$data_attrs = '';
	foreach ( $atts as $k => $v ) {
		if ( strpos($k, 'data-') === 0 ) {
			$k = sanitize_key($k);
			$data_attrs .= $v !== '' ? ' ' . $k . '="' . esc_attr($v) . '"' : ' ' . $k;
		}
	}

	return '<div'.$name.' '.$data_attrs.' class="flex grid-'.$grid.$valign.$break.$class.'"'.$style.$tracking.'>'.do_shortcode($content).'</div>';
}

// Column
add_shortcode( 'col', 'battleplan_buildColumn' );
function battleplan_buildColumn( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'class'=>'', 'order'=>'', 'break'=>'', 'align'=>'', 'valign'=>'', 'h-span'=>'', 'v-span'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'css'=>'', 'hash'=>'', 'gap'=>'' ), $atts );
	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name !== '' ? ' id="'.$name.'"' : '';
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$order = esc_attr($a['order']) !== '' ? 'order: '.esc_attr($a['order']).' !important;' : '';
	$break = esc_attr($a['break']) !== '' ? ' break-'.esc_attr($a['break']) : '';
	$align = esc_attr($a['align']) !== '' ? " text-".esc_attr($a['align']) : '';
	$valign = esc_attr($a['valign']) !== '' ? " valign-".esc_attr($a['valign']) : '';
	$hSpan = esc_attr($a['h-span']) !== '' ? ' h-span-'.esc_attr($a['h-span']) : '';
	$vSpan = esc_attr($a['v-span']) !== '' ? ' v-span-'.esc_attr($a['v-span']) : '';
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	if ( $start || $end ) {
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;
	}
	$tracking = esc_attr($a['track']) !== '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking !== '' ) $class .= " tracking";
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$css = esc_attr($a['css']);
	$hash = esc_attr($a['hash']) !== '' ? 'data-hash="'.esc_attr($a['hash']).'"' : '';
	$gap = esc_attr($a['gap']) !== '' ? ' style="gap:'.esc_attr($a['gap']).'"' : '';
	$style = $order ? " style='".$order."'" : '';

	$data_attrs = '';
	foreach ( $atts as $k => $v ) {
		if ( strpos($k, 'data-') === 0 ) {
			$k = sanitize_key($k);
			$data_attrs .= $v !== '' ? ' ' . $k . '="' . esc_attr($v) . '"' : ' ' . $k;
		}
	}

	$buildCol = '<div'.$name.' '.$data_attrs.' class="col '.$class.$align.$valign.$break.$hSpan.$vSpan.'" '.$tracking.$hash.$style.'><div class="col-inner"'.$gap;
	if ( $background !== "" || $css !== "" ) {
		$buildCol .= ' style="';
		if ( $css !== "" ) $buildCol .= $css;
		if ( $background !== "" ) $buildCol .= ' background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;';
		$buildCol .= '"';
	}
	$buildCol .= '>';
	$buildCol .= do_shortcode($content);
	$buildCol .= '</div></div>';

	return $buildCol;
}

// Group & Text Blocks
add_shortcode('group', 'battleplan_buildGroup');
add_shortcode('txt',   'battleplan_buildGroup');
function battleplan_buildGroup($atts, $content=null, $tag=''){
	$a = shortcode_atts(['size'=>'100', 'class'=>'', 'start'=>'', 'end'=>'', 'order'=>'', 'track'=>''], $atts);
	$size = convertSize(esc_attr($a['size']));
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	if ( $start || $end ) {
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;
	}
	$order = esc_attr($a['order']) !== '' ? ' style="order: '.esc_attr($a['order']).' !important"' : '';
	$tracking = esc_attr($a['track']) !== '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking !== '' ) $class .= " tracking";

	$block = ($tag==='group') ? 'group' : 'text';
	return '<div class="block block-'.$block.' span-'.$size.$class.'"'.$tracking.'>'.do_shortcode($content).'</div>';
}

// Image Block
add_shortcode( 'img', 'battleplan_buildImg' );
function battleplan_buildImg( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'class'=>'', 'order'=>'', 'link'=>'', 'get-biz'=>'', 'new-tab'=>'', 'ada-hidden'=>'false', 'start'=>'', 'end'=>'', 'track'=>'' ), $atts );
	$size = convertSize(esc_attr($a['size']));
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$style = esc_attr($a['order']) !== '' ? " style='order: ".esc_attr($a['order'])." !important'" : '';
	$getBiz = esc_attr($a['get-biz']);
	if ( $getBiz === "" ) {
		$linkClass = "";
		$link = esc_attr($a['link']);
		if ( $link === "" || $link === "none" || $link === "no" ) {
			$linkClass=" class='no-link'";
			$link = '';
		}
		if ( strpos($link, 'pdf') ) $link .= "?id=".time();
	} else {
		$link = do_shortcode( '[get-biz info="'.$getBiz.'"]' );
	};
	$target = esc_attr($a['new-tab']) === "yes" || esc_attr($a['new-tab']) === "true" ? 'target="_blank"' : '';
	$hidden = esc_attr($a['ada-hidden']) === "true" ? " aria-hidden='true' tabindex='-1'" : "";
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	if ( $start || $end ) {
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;
	}
	$tracking = esc_attr($a['track']) !== '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking !== '' ) $class .= " tracking";

	$buildImg = '<div class="block block-image span-'.$size.$class.'" '.$tracking.$style.'>';
	if ( $link !== '' ) $buildImg .= '<a '.$target.$linkClass.' href="'.$link.'"'.$hidden.'>';
	$buildImg .= do_shortcode($content);
	if ( $link !== '' )  $buildImg .= '</a>';
	$buildImg .= '</div>';

	return $buildImg;
}

// Video Block
add_shortcode( 'vid', 'battleplan_buildVid' );
function battleplan_buildVid( $atts, $content = null ) {
	wp_enqueue_style( 'battleplan-video', get_template_directory_uri()."/style-video.css", [], _BP_VERSION, 'print' );

	$a = shortcode_atts( array( 'size'=>'100', 'mobile'=>'100', 'class'=>'', 'order'=>'', 'link'=>'', 'thumb'=>'', 'start'=>'', 'end'=>'', 'preload'=>'false', 'related'=>'false', 'fullscreen'=>'false', 'controls'=>'true', 'autoplay'=>'false', 'loop'=>'false', 'muted'=>'false', 'begin'=>'', 'track'=>'' ), $atts );
	$size = convertSize(esc_attr($a['size']));
	$mobile = esc_attr($a['mobile']) !== '100' ? ' data-mobile-w="'.$mobile.'"' : '';
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$link = esc_attr($a['link']);
	$thumb = esc_attr($a['thumb']);
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	if ( $start || $end ) {
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;
	}
	$preload = esc_attr($a['preload']);
	$related = esc_attr($a['related']);
	$order = esc_attr($a['order']);
	$fullscreen = esc_attr($a['fullscreen']);
	if ( $order !== '' || $fullscreen !== "false" ) {
		$style = ' style="';
		if ( $order !== '' ) $style .= 'order: '.$order.'  !important; ';
		if ( $fullscreen !== 'false' ) $style .= 'margin: 0; ';
		$style = '"';
	}
	$controls = esc_attr($a['controls']);
	$autoplay = esc_attr($a['autoplay']);
	$loop = esc_attr($a['loop']);
	$muted = esc_attr($a['muted']);
	$begin = esc_attr($a['begin']);
	$tracking = esc_attr($a['track']) !== '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking !== '' ) $class .= " tracking";

	if ( ( strpos($link, 'youtube') !== false || strpos($link, 'youtu.be') !== false || strpos($link, 'vimeo') !== false ) && $preload == "false" ) :
		if ( strpos($link, 'youtube') !== false || strpos($link, 'youtu.be') !== false ) :
			$link = (strpos($link, '?si=') !== false) ? strstr($link, '?si=', true) : $link;
			$link = str_replace('www.', '', $link);
			$link = str_replace('https://youtu.be/', '', $link);
			$link = str_replace('https://youtube.com/shorts/', '', $link);
			$link = str_replace('https://youtube.com/embed/', '', $link);
			$link = str_replace('https://youtube.com/watch?v=', '', $link);

			preg_match('/^[^?]+/', $link, $matches);
			$vid_id = $matches[0];

			$link = 'https://www.youtube.com/embed/'.$vid_id.'?autoplay=1&enablejsapi=1&version=3&playerapiid=ytplayer';

			if ( $thumb === '' ) : $thumb = '//i.ytimg.com/vi/'.$vid_id.'/hqdefault.jpg'; endif;
			if ( $related === "false" ) : $link .= "&rel=0";	endif;
			if ( $begin !== "" ) : $link .= "&start=".$begin; endif;
		else:
			$id = str_replace(['https://player.vimeo.com/video/', 'https://vimeo.com/'], '', $link);
			$link .= "?autoplay=1&title=0&byline=0&portrait=0";
			if ( $thumb === '' ) :
				$data = file_get_contents('https://vimeo.com/api/v2/video/'.$id.'.json');
				$data = json_decode($data);
				$thumb = str_replace('http:', '', $data[0]->thumbnail_large.'.jpg');
			endif;
		endif;

		return '<div class="block block-video span-'.$size.$class.' video-player"'.$tracking.' style="'.$style.'" data-thumb="'.$thumb.'" data-link="'.$link.'" data-id="'.$id.'"></div>';

	else:
		$dotPos = strrpos($link, '.');
		$extension = substr($link, $dotPos + 1);
		$file = substr($link, 0, $dotPos);
		$buildVid = '<div class="block block-video span-'.$size.$class.'"'.$tracking.'" style="'.$style.'">';
		$buildVid .= '<video playsinline ';
		if ( $controls == 'true' ) $buildVid .= 'controls ';
		if ( $autoplay == 'true' ) $buildVid .= 'autoplay ';
		if ( $loop == 'true' ) $buildVid .= 'loop ';
		if ( $muted == 'true' ) $buildVid .= 'muted ';

		$buildVid .= 'poster="'.$thumb.'" style="position:relative; top:0; left:0; width:100%; height:100%"'.$mobile.'>';
		$buildVid .= '<source src="'.$link.'" type="video/'.$extension.'">';
		if ( $extension === 'webm' ) $buildVid .= '<source src="'.$file.'-ios.mp4" type="video/mp4">';	// fallback for iOS
		$buildVid .= '<img loading="lazy" src="'.$thumb.'">';
		$buildVid .= '</video></div>';

		return $buildVid;
	endif;
}

// Button Block
add_shortcode( 'btn', 'battleplan_buildButton' );
function battleplan_buildButton( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'align'=>'center', 'order'=>'', 'link'=>'', 'get-biz'=>'', 'new-tab'=>'false', 'class'=>'', 'fancy'=>'false', 'icon'=>'false', 'top'=>0, 'left'=>0, 'before'=>'false', 'graphic'=>'false', 'graphic-w'=>'40', 'ada'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'onclick'=>'' ), $atts );
	$size = convertSize(esc_attr($a['size']));
	$align = esc_attr($a['align']) !== 'center' ? " button-".esc_attr($a['align']) : '';
	$style = esc_attr($a['order']) !== '' ? " style='order: ".esc_attr($a['order'])." !important'" : '';
	$getBiz = esc_attr($a['get-biz']);
	if ( $getBiz === "" ) {
		$linkClass = "";
		$link = esc_attr($a['link']);
		if ( $link === "" || $link === "none" || $link === "no" ) {
			$linkClass=" class='no-link'";
			$link = '';
		}
		if ( strpos($link, 'pdf') ) $link .= "?id=".time();
	} else {
		$link = do_shortcode( '[get-biz info="'.$getBiz.'"]' );
	};
	$target = esc_attr($a['new-tab']) === "yes" || esc_attr($a['new-tab']) === "true" ? 'target="_blank"' : '';
	$class = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);

	$fancy_val = isset($a['fancy']) ? trim($a['fancy']) : '';
	$fancy = $fancy_val !== '' ? '-' . esc_attr($fancy_val) : '';

	$icon = esc_attr($a['icon']) === 'false' ? '' : esc_attr($a['icon']);
	if ( $icon !== '' ) :
		wp_enqueue_style( 'battleplan-fancy-btn', get_template_directory_uri()."/style-fancy-btn.css", [], _BP_VERSION, 'print' );
		$class .= " fancy".$fancy;
		$icon = $icon === 'true' ? 'chevron-right' : $icon;
		$content = esc_attr($a['before']) === 'false'
			? '<span class="fancy-text">'.$content.'</span><span class="fancy-icon">[get-icon type="'.$icon.'" top="'.$top.'" left="'.$left.'"]</span>'
			: '<span class="fancy-icon">[get-icon type="'.$icon.'" top="'.$top.'" left="'.$left.'"]</span><span class="fancy-text">'.$content.'</span>';
	endif;
	$graphicW = esc_attr($a['graphic-w']);
	if ( esc_attr($a['graphic']) !== "false" ) {
		$class .= " graphic-icon";
		$content = '<img src="/wp-content/uploads/'.esc_attr($a['graphic']).'" width="'.$graphicW.'" height="'.$graphicW.'" style="aspect-ratio:'.$graphicW.'/'.$graphicW.'" alt="" /><span class="unique">'.$content.'</span>';
	}
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	if ( $start || $end ) {
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;
	}
	$ada = esc_attr($a['ada']) !== '' ? ' <span class="screen-reader-text">'.esc_attr($a['ada']).'</span>' : '';
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";
	$onclick = esc_attr($a['onclick']);
	$inlineBtnID = 'btn_'.str_replace('/', '', $link);

	$script = $onclick !== '' ?
        '<script nonce="'._BP_NONCE.'">
            document.addEventListener("DOMContentLoaded", function() {
                const '.$inlineBtnID.' = document.getElementById("'.$inlineBtnID.'");
                if ('.$inlineBtnID.') {
                    '.$inlineBtnID.'.addEventListener("click", function(event) {
                        {'.$onclick.'}
                    });
                }
            });
        </script>' :
	'';

	return $script.'<div class="block block-button span-'.$size.$class.$align.'"'.$tracking.$style.'><a id="'.$inlineBtnID.'" '.$target.' href="'.$link.'" class="button'.$class.'">'.do_shortcode($content).$ada.'</a></div>';
}

// Accordion Block
add_shortcode( 'accordion', 'battleplan_buildAccordion' );
function battleplan_buildAccordion( $atts, $content = null ) {
	wp_enqueue_script( 'battleplan-accordion', get_template_directory_uri().'/js/script-accordion.js', array(), _BP_VERSION, false );
	wp_enqueue_style( 'battleplan-accordion', get_template_directory_uri()."/style-accordion.css", [], _BP_VERSION, 'print' );

	$a = shortcode_atts( array( 'title'=>'', 'excerpt'=>'', 'class'=>'', 'active'=>'false', 'btn'=>'false', 'btn_collapse'=>'false', 'icon'=>'true', 'start'=>'', 'end'=>'', 'scroll'=>'true', 'track'=>'', 'multiple'=>'true' ), $atts );
	$excerpt = esc_attr($a['excerpt']);
	if ( $excerpt !== '' ) $excerpt = '<div class="accordion-excerpt"><div class="accordion-box"><p>'.$excerpt.'</p></div></div>';
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$title = esc_attr($a['title']);
	$btnCollapse = esc_attr($a['btn_collapse']);
	$btn = esc_attr($a['btn']);
	$scroll = esc_attr($a['scroll']) == "true" ? "" : " no-scroll";
	$addBtn = '';
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	$multiple = esc_attr($a['multiple']) === 'true' ? '' : ' data-multiple="false"';
	if ( $tracking != '' ) $class .= " tracking";
	$icon = ($v = esc_attr($a['icon'])) === 'true'
		? '<span class="accordion-icon" aria-hidden="true">[get-icon type="chevron-right"]</span>'
		: ($v === 'false' ? '' : '<span class="accordion-icon" aria-hidden="true">[get-icon type="'.$v.'"]</span>');

	if ( $title ) $printTitle = '<h2 role="button" tabindex="0" class="accordion-title accordion-button'.$scroll.'">'.$icon.$title.'</h2>';

	if ( $btn !== "false" ) :
		if ( $btnCollapse === "false" ) $btnCollapse = "hide";
		if ( $btn == "true" ) :
			$printTitle = '<div class="block block-button"><button role="button" tabindex="0" class="accordion-title accordion-button'.$scroll.'" data-text="'.$title.'" data-collapse="'.$btnCollapse.'">'.$title.'</button></div>';
		else:
			if ( $title ) $printTitle = '<h2 class="accordion-title">'.$icon.$title.'</h2>';
			$addBtn = '<div class="block block-button"><button role="button" tabindex="0" class="accordion-button'.$scroll.'" data-text="'.$btn.'" data-collapse="'.$btnCollapse.'">'.$btn.'</button></div>';
		endif;
	endif;

	$active = esc_attr($a['active']);
	if ( $active != "false" ) $class .= " start-active";
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	if ( $start || $end ) :
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;
	endif;

	return do_shortcode('<div class="block block-accordion'.$class.$scroll.'"'.$multiple.$tracking.'>'.$printTitle.$excerpt.'<div class="accordion-content'.$scroll.'"><div class="accordion-box'.$scroll.'">'.$content.'</div></div>'.$addBtn.'</div>');
}

// Parallax Section
add_shortcode( 'parallax', 'battleplan_buildParallax' );
function battleplan_buildParallax( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'', 'type'=>'section', 'width'=>'edge', 'img-w'=>'2000', 'img-h'=>'1333', 'height'=>'800', 'padding'=>'50', 'pos-x'=>'50%', 'top-y'=>0, 'bottom-y'=>0, 'image'=>'', 'class'=>'', 'fixed'=>'false', 'scroll-btn'=>'false', 'scroll-loc'=>'#page', 'scroll-icon'=>'chevron-down', 'z-index'=>'2', 'track'=>'' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$style = esc_attr($a['style']);
	if ( $style != '' ) $style = " style-".$style;
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
	$padding = esc_attr($a['padding']);
	$fixed = esc_attr($a['fixed']);
	$image = esc_attr($a['image']);
	$hasImage = !empty($image);
	$class = esc_attr($a['class']);
	$zIndex = esc_attr($a['z-index']);
	if ( $class != '' ) $class = " ".$class;
	$scrollBtn = esc_attr($a['scroll-btn']);
	$scrollLoc = esc_attr($a['scroll-loc']);
	$scrollIcon = '[get-icon type="'.esc_attr($a['scroll-icon']).'" sr="Scroll Down"]';
	if ( $scrollBtn != "false" ) : $buildScrollBtn = '<div class="scroll-down"><a href="'.$scrollLoc.'">'.$scrollIcon.'</a></div>'; else: $buildScrollBtn = ''; endif;
	if ( !$name ) $name = "section-".rand(10000,99999);
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";

	$attachment_id = $hasImage ? attachment_url_to_postid(site_url().$image) : 0;
	$alt_text = $attachment_id ? get_post_meta($attachment_id, '_wp_attachment_image_alt', true) : '';

	if ( $type === "col" ) : $div = "div"; else: $div = $type; endif;

	if ( is_mobile() ) {

		if ( $hasImage ) {
			$mobileSrc = explode('.', $image);
			if ( strpos($mobileSrc[0], "-1920x") !== false ) {
				$mobileSrc[0] = substr($mobileSrc[0], 0, strpos($mobileSrc[0], "-1920x"));
			}
			$imgBase = $mobileSrc[0];
			$imgExt  = $mobileSrc[1];
			$placeholder = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAn8B9FXC5VwAAAAASUVORK5CYII=';
			$ratio = $imgW && $imgH ? $imgW / $imgH : 2;
			$initialH = round(480 / $ratio);

			$styleAttr = 'padding-top:'.$padding.'px; padding-bottom:'.$padding.'px; height:'.$initialH.'px; background-image:url('.$placeholder.'); background-size:cover; background-position:'.$posY.' '.$posX;

			$dataAttrs = ' data-img-base="'.$imgBase.'" data-img-ext="'.$imgExt.'" data-img-width="'.$imgW.'" data-img-height="'.$imgH.'"';

		} else {
			$styleAttr = 'padding-top:'.$padding.'px; padding-bottom:'.$padding.'px';
			$dataAttrs = '';
		}

		return do_shortcode('<'.$div.' id="'.$name.'" class="load-bg-img '.$type.$style.' '.$type.'-'.$width.($hasImage ? ' '.$type.'-parallax-disabled' : '').$class.'"'.$tracking.' style="'.$styleAttr.'"'.$dataAttrs.'>'.$content.'</'.$div.'>');

	} else {
		if ( $hasImage ) :
			$dataAttrs = ' data-parallax="scroll" data-img-width="'.$imgW.'" data-img-height="'.$imgH.'" data-pos-x="'.$posX.'" data-top-y="'.$topY.'" data-bottom-y="'.$botY.'" data-fixed="'.$fixed.'" data-image-src="'.$image.'"';

			$parallaxClass = ' '.$type.'-parallax';
		else :
			$dataAttrs = '';
			$parallaxClass = '';
		endif;

		return do_shortcode(
			'<'.$div.' id="'.$name.'" class="'.$type.$style.' '.$type.'-'.$width.$parallaxClass.$class.'"'.$tracking.' style="height:'.$height.'"'.$dataAttrs.'>'.$content.($hasImage ? $buildScrollBtn : '').'</'.$div.'>'
		);
	}
}

// Locked Section
add_shortcode( 'lock', 'battleplan_buildLockedSection' );
function battleplan_buildLockedSection( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'lock', 'width'=>'edge', 'position'=>'bottom', 'delay'=>'3000', 'show'=>'session', 'css'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'class'=>'', 'start'=>'', 'end'=>'', 'btn-activated'=>'no', 'track'=>'', 'content'=>'text' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$delay = esc_attr($a['delay']);
	$show = esc_attr($a['show']);
	$css = esc_attr($a['css']);
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$width = esc_attr($a['width']);
	if ( $width != '' ) $width = " section-".$width;
	$class = esc_attr($a['class']) != '' ? " ".esc_attr($a['class']) : '';
	$pos = esc_attr($a['position']);
	$class .= " position-".$pos;
	$style = esc_attr($a['style']) == "lock" ? "" : " style-".esc_attr($a['style']);
	if ( $name ) : $name = " id='".$name."'"; else: $name = ""; endif;
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	if ( $start || $end ) {
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;
	}
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	$contentType = esc_attr($a['content']) == 'text' ? ' content-text' : ' content-image';
	if ( $tracking != '' ) $class .= " tracking";
	$btnActivated = esc_attr($a['btn-activated']);
	if ($btnActivated == "true" || $btnActivated == "yes" ) $btnActivated = "yes";

	$buildSection = '<section'.$name.' class="section section-lock style-lock'.$style.$width.$contentType.$class.'"'.$tracking.' data-pos="'.$pos.'" data-delay="'.$delay.'" data-show="'.$show.'" data-btn="'.$btnActivated.'"';

	if ( $background != "" || $css != "" ) :
		$buildSection .= ' style="';
		if ( $css != "" ) $buildSection .= $css;
		if ( $background != "" ) $buildSection .= ' background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;';
		$buildSection .= '"';
	endif;

	//$buildSection .= '><div class="closeBtn" aria-label="close" aria-hidden="false" tabindex="0"><span class="icon x-large"></span></div>'.do_shortcode($content).'</section>';	/* WP3 validation 12/11/24 */

	$buildSection .= '><button class="closeBtn" aria-label="close">[get-icon type="x-large"]</button></div>'.$content.'</section>';

	return do_shortcode($buildSection);
}

	add_action( 'wpcf7_init', function() {
	remove_action( 'wpcf7_swv_create_schema', 'wpcf7_swv_add_select_enum_rules', 20, 2 );
	remove_action( 'wpcf7_swv_create_schema', 'wpcf7_swv_add_checkbox_enum_rules', 20, 2 ); // for checkboxes
});

add_shortcode( 'seek', 'battleplan_formField' );
function battleplan_formField( $atts, $content = null ) {
	$a = shortcode_atts( array( 'label'=>'Label', 'show'=>'true', 'label-pos'=>'before', 'label-valign'=>'baseline', 'id'=>'user-input', 'req'=>'false', 'width'=>'default', 'max-w'=>'', 'class'=>'', 'value'=>''), $atts );
	$id = esc_attr($a['id']);
	$label = esc_attr($a['label']);
	$labelPos = esc_attr($a['label-pos']);
	$labelValign = esc_attr($a['label-valign']) === 'bottom' ? 'baseline' : esc_attr($a['label-valign']);
	$req = esc_attr($a['req']);
	$width = 'width-'.esc_attr($a['width']);
	$maxW = esc_attr($a['max-w']);
	$value = esc_attr($a['value']) !== '' ? ' data-value="'.esc_attr($a['value']).'"' : '';
	if ( $maxW != '' ) $maxW = ' style="margin:0 auto; max-width:'.$maxW.'"';
	$class = esc_attr($a['class']) != '' ? " ".esc_attr($a['class']) : '';
	$show = esc_attr($a['show']);
	$aria = $buildInput = '';
	if ( $show != 'true' ) : $width = 'width-none'; $aria = 'aria-label="'.$label.'"'; endif;
	$asterisk = '<span class="required"></span><span class="sr-only">Required Field</span>';

	if ( $label == "button" ) :
		$buildInput .= '<div class="block block-button block-100">'.$content.'</div>';
	else:
	// removed col from classes 5/19 for animation purposes
		$buildInput = '<div class="form-input input-'.strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(" ","-",$label))).' input-'.$id.' label-pos-'.$labelPos.' '.$width.$class.'"'.$value.$maxW.' '.$aria.'>';
		if ( $labelPos == 'after' ) $buildInput .= $content;
		if ( $show == 'true' ) $buildInput .= '<label for="'.$id.'" class="'.$width.' label-'.$labelValign.'">'.$label;
		if ( $show == 'true' && $req != 'false' ) $buildInput .= $asterisk;
		if ( $show == 'true' ) $buildInput .= '</label>';
		if ( $labelPos == 'before' ) $buildInput .= $content;
		if ( $show != 'true' && $req != 'false' ) $buildInput .= $asterisk;
		$buildInput .= '</div>';
	endif;

	return $buildInput;
}

// Widgets
add_shortcode( 'widget', 'battleplan_buildWidget' );
function battleplan_buildWidget( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'basic', 'title'=>'hide', 'lock'=>'none', 'priority'=>'2', 'set'=>'none', 'class'=>'', 'show'=>'', 'hide'=>'', 'start'=>'', 'end'=>'', 'track'=>''), $atts );
	$type = strtolower(preg_replace("/[\s_]/", "-", esc_attr($a['type'])));
	$title = esc_attr($a['title']);
	$lock = esc_attr($a['lock']);
	$priority = esc_attr($a['priority']);
	$set = esc_attr($a['set']);
	$class = esc_attr($a['class']);
	$show = esc_attr($a['show']);
	$hide = esc_attr($a['hide']);
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	if ( $start || $end ) {
		$now = time();
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;
	}
	$addHide = $addClass = $name = '';
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";

	$display = true;
	$brand = customer_info()['site-brand'] ?? '';

	if ( $type == "form" ) : $lock = "top"; $priority = '4'; $addHide = "404, contact, review"; endif;
	if ( $type == "form" && $title == "hide" ) : $title = 'Service Request'; endif;
	if ( $type == "form" && $content == null ) : $content = '[contact-form-7 title="Quote Request Form"]'; endif;

	if ( $type == "brand-logo" ) : $lock = 'top'; $priority = '4'; endif;
	if ( $type == "brand-logo" && $content == null ) : $content = '[get-brand-logo]'; endif;

	if ( $type == "financing" ) : $priority = '3'; endif;

	if ( $type == "customer-care" ) : $addClass = ' widget-image'; $addHide = 'customer-care'; endif;
	if ( $type == "customer-care" && $content == null ) :
		if ( (!is_array( $brand ) && $brand == 'american standard' ) || ( is_array( $brand ) && $brand[0] == 'american standard' )) : $content = '[get-customer-care]';
		elseif ( (!is_array( $brand ) && $brand == 'ruud' ) || ( is_array( $brand ) && $brand[0] == 'ruud' )) : $content = '[ruud-pro-partner]';
		elseif ( (!is_array( $brand ) && $brand == 'comfortmaker' ) || ( is_array( $brand ) && $brand[0] == 'comfortmaker' )) : $content = '[get-comfortmaker-elite-dealer]';
		elseif ( (!is_array( $brand ) && $brand == 'tempstar' ) || ( is_array( $brand ) && $brand[0] == 'tempstar' )) : $content = '[get-tempstar-elite-dealer]';
		endif;
	endif;

	if ( $type == "symptom-checker" ) : $addClass = ' widget-image'; $priority = '1'; $addHide = 'symptom-checker'; endif;
	if ( $type == "symptom-checker" && $content == null ) : $content = '[get-symptom-checker]'; endif;

	if ( $type == "credit-cards" ) : $addClass = ' widget-image'; $lock = 'bottom'; $priority = '3'; endif;
	if ( $type == "credit-cards" && $content == null ) : $content = '[get-credit-cards]'; endif;

	if ( $type == "event" ) : $addClass = ' widget-image'; $addHide = 'event'; endif;

	if ( $type == "topper" ) : $addClass = ' widget-image'; $priority = '5'; $lock = 'top';  endif;

	if ( $type == "filler" ) : $priority = '5'; $content = "&nbsp;"; endif;

	if ( $type == "menu" ) :
		$buildWidget = '<div id="desktop-navigation" class="widget widget-navigation widget-priority-5 lock-to-top widget_nav_menu hide-1 hide-2 hide-2"'.$tracking.' data-priority="5">';
		if ( $title != "hide" ) $buildWidget .= '<h3 class="widget-title">'.$title.'</h3>';
		$buildWidget .= wp_nav_menu ( array ( 'echo' => false, 'container' => 'div', 'container_class' => 'menu-main-menu-container', 'menu_id' => 'main-menu-menu', 'menu_class' => 'menu', 'theme_location' =>'widget-menu', 'walker' => new Aria_Walker_Nav_Menu(), ) );
		$buildWidget .= '</div>';
	else:
		if ( $hide ) : if ( $addHide ) : $hide .= ', '.$addHide; endif;
		else: if ( $addHide ) : $hide = $addHide; endif; endif;

		if ( $title != 'hide' ) $name = ' widget-'.strtolower(preg_replace("/[\s_]/", "-", $title));

		$buildClasses = 'widget widget-'.$type.$addClass.$name.' widget-priority-'.$priority;
		if ( $set != "none" ) $buildClasses .= ' widget-set set-'.$set;
		if ( $lock != "none" ) $buildClasses .= ' lock-to-'.$lock;
		if ( $class != "" ) $buildClasses .= ' '.$class;

		$buildWidget = '<div class="'.$buildClasses.'"'.$tracking.'>';
		if ( $title != "hide" ) $buildWidget .= '<h3 class="widget-title">'.$title.'</h3>';
		$buildWidget .= '<div class="widget-content">'.do_shortcode($content).'</div>';
		$buildWidget .= '</div>';
	endif;

	if ( $show != '' ) :
		$show = str_replace(" ", "", $show);
		$show = explode(",", $show);
		$display = false;

		foreach ( $show as $check ) :
			if ( strpos( _PAGE_SLUG_FULL, $check ) !== false || ( $check == '404' && in_array( 'error404', get_body_class() )) || ( $check == 'home' && in_array( 'home', get_body_class() ))) $display = true;
		endforeach;
	endif;

	if ( $hide != '' ) :
		$hide = str_replace(" ", "", $hide);
		$hide = explode(",", $hide);

		foreach ( $hide as $check ) :
			if ( strpos( _PAGE_SLUG_FULL, $check ) !== false || ( $check == '404' && in_array( 'error404', get_body_class() )) || ( $check == 'home' && in_array( 'home', get_body_class() ))) $display = false;
		endforeach;
	endif;

	if ( $display == true) return $buildWidget;
}