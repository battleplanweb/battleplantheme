<?php 
/* Battle Plan Web Design Functions: Grid

/*--------------------------------------------------------------
# Grid Set Up
--------------------------------------------------------------*/

// Format with <p>
add_shortcode( 'p', 'battleplan_add_ptags' );
function battleplan_add_ptags( $atts, $content = null ) {
	return wpautop( $content );
}

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
	$a = shortcode_atts( array( 'name'=>'', 'hash'=>'', 'style'=>'', 'theme'=>'', 'width'=>'', 'grid'=>'', 'break'=>'', 'valign'=>'', 'css'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'class'=>'', 'start'=>'', 'end'=>'', 'track'=>'' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$name = $name ? ' id="'.$name.'"' : '';
	$hash = esc_attr($a['hash']) != '' ? 'data-hash="'.esc_attr($a['hash']).'"' : '';
	$css = esc_attr($a['css']);
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$width = esc_attr($a['width']) != '' ? ' section-'.esc_attr($a['width']) : '';
	$class = esc_attr($a['class']) != '' ? ' '.esc_attr($a['class']) : '';
	$style = esc_attr($a['style']) != '' ? ' style-'.esc_attr($a['style']) : '';
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";

	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}
	$theme = esc_attr($a['theme']) != '' ? ' style-'.esc_attr($a['theme']) : '';
	$valign = esc_attr($a['valign']) != '' ? ' valign-'.esc_attr($a['valign']) : '';
	$break = esc_attr($a['break']) != '' ? ' break-'.esc_attr($a['break']) : '';
	$buildLayout = esc_attr($a['grid']) != '' ? '<div class="flex grid-'.esc_attr($a['grid']).$valign.$break.$class.'">'.do_shortcode($content).'</div>' : do_shortcode($content);
	$buildSection = '<section'.$name.' class="section'.$style.$theme.$width.$class.'" '.$hash.$tracking;
	if ( $background != "" || $css != "" ) :
		$buildSection .= ' style="';
		if ( $css != "" ) $buildSection .= $css;
		if ( $background != "" ) $buildSection .= ' background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;';	
		$buildSection .= '"';
	endif;
	$buildSection .= '>'.$buildLayout.'</section>';	
	
	return $buildSection;
}

// Layout (Nested)
add_shortcode( 'nested', 'battleplan_buildNested' );
function battleplan_buildNested( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'grid'=>'1', 'break'=>'', 'valign'=>'', 'class'=>'', 'track'=>'' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
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

	$buildLayout = '<div'.$name.' class="flex nested grid-'.$grid.$valign.$break.$class.'" '.$tracking.'">'.do_shortcode($content).'</div>';	
	
	return $buildLayout;
}

// Layout
add_shortcode( 'layout', 'battleplan_buildLayout' );
function battleplan_buildLayout( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'grid'=>'1', 'break'=>'', 'valign'=>'', 'class'=>'', 'track'=>'' ), $atts );
	$grid = esc_attr($a['grid']);
	
	if ( strpos($grid,'px') !== false || strpos($grid,'em') !== false || strpos($grid,'fr') !== false ) :
		$custom_grid = 'style="grid-template-columns: '.$grid.'" ';
		$grid = 'custom';
	else: $custom_grid = '';
	endif;	
	
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$name = $name ? ' id="'.$name.'"' : '';	
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";
	$break = esc_attr($a['break']);
	$valign = esc_attr($a['valign']);
	if ( $valign != '' ) $valign = " valign-".$valign;
	if ( $break != '' ) $break = " break-".$break;

	$buildLayout = '<div'.$name.' class="flex grid-'.$grid.$valign.$break.$class.'" '.$tracking.$custom_grid.'>'.do_shortcode($content).'</div>';	
	
	return $buildLayout;
}

// Column
add_shortcode( 'col', 'battleplan_buildColumn' );
function battleplan_buildColumn( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'hash'=>'', 'order'=>'', 'class'=>'', 'align'=>'', 'valign'=>'', 'h-span'=>'', 'v-span'=>'', 'css'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'start'=>'', 'end'=>'', 'track'=>'' ), $atts );
	$name = preg_replace("/[\s_]/", "-", strtolower(esc_attr($a['name'])));
	$name = $name ? " id='".$name."'" : '';
	$hash = esc_attr($a['hash']) != '' ? 'data-hash="'.esc_attr($a['hash']).'"' : '';
	$class = esc_attr($a['class']) != '' ? " ".esc_attr($a['class']) : '';
	$align = esc_attr($a['align']) != '' ? " text-".esc_attr($a['align']) : '';
	$valign = esc_attr($a['valign']) != '' ? " valign-".esc_attr($a['valign']) : '';
	$css = esc_attr($a['css']);
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";
	$order = esc_attr($a['order']) != '' ? 'order: '.esc_attr($a['order']).' !important;' : '';
	$hSpan = esc_attr($a['h-span']) != '' ? 'grid-column: span '.esc_attr($a['h-span']).' !important;' : '';
	$vSpan = esc_attr($a['v-span']) != '' ? 'grid-row: span '.esc_attr($a['v-span']).' !important;' : '';	
	$style = $order || $hSpan || $vSpan ? " style='".$order.$hSpan.$vSpan."'" : '';
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}
	$buildCol = '<div'.$name.' class="col '.$class.$align.$valign.'" '.$tracking.$hash.$style.'><div class="col-inner"';
	if ( $background != "" || $css != "" ) :
		$buildCol .= ' style="';
		if ( $css != "" ) $buildCol .= $css;
		if ( $background != "" ) $buildCol .= ' background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;';	
		$buildCol .= '"';
	endif;
	$buildCol .= '>';
	$buildCol .= do_shortcode($content);
	$buildCol .= '</div></div>';	
	
	return $buildCol;
}

// Image Block
add_shortcode( 'img', 'battleplan_buildImg' );
function battleplan_buildImg( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'order'=>'', 'link'=>'', 'new-tab'=>'', 'ada-hidden'=>'false', 'class'=>'', 'start'=>'', 'end'=>'', 'track'=>'' ), $atts );
	$order = esc_attr($a['order']);	
	if ( $order != '' ) : $style = " style='order: ".$order." !important'"; else: $style = ""; endif;
	$link = esc_attr($a['link']);	
	$size = esc_attr($a['size']);	
	$size = convertSize($size);
	$class = esc_attr($a['class']);
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";
	$hidden = esc_attr($a['ada-hidden']);
	if ( $hidden == "true" ) : $hidden = " aria-hidden='true' tabindex='-1'"; else: $hidden = ""; endif;
	$target = esc_attr($a['new-tab']);
	if ( $target == 'yes' || $target == "true" ) $target = 'target="_blank"';
	if ( $class != '' ) $class = " ".$class;
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}

	$buildImg = '<div class="block block-image span-'.$size.$class.'" '.$tracking.$style.'>';
	if ( $link != '' ) : $buildImg .= '<a '.$target.' href="'.$link.'"'.$hidden.'>'; endif;
	$buildImg .= do_shortcode($content);
	if ( $link != '' ) : $buildImg .= '</a>'; endif; 
	$buildImg .= '</div>';

	return $buildImg;
}

// Video Block
add_shortcode( 'vid', 'battleplan_buildVid' );
function battleplan_buildVid( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'mobile'=>'100', 'order'=>'', 'link'=>'', 'thumb'=>'', 'preload'=>'false', 'class'=>'', 'related'=>'false', 'start'=>'', 'end'=>'', 'fullscreen'=>'false', 'controls'=>'true', 'autoplay'=>'false', 'loop'=>'false', 'muted'=>'false', 'begin'=>'', 'track'=>'' ), $atts );
	$related = esc_attr($a['related']);	
	$order = esc_attr($a['order']);	
	if ( $order != '' ) : $style = " style='order: ".$order." !important'"; else: $style = ""; endif;
	$link = esc_attr($a['link']);	
	$thumb = esc_attr($a['thumb']);	
	$preload = esc_attr($a['preload']);	
	$size = convertSize(esc_attr($a['size']));	
	$height = 56.25 * ($size/12);	
	$mobile = esc_attr($a['mobile']);	
	$controls = esc_attr($a['controls']);
	$autoplay = esc_attr($a['autoplay']);
	$loop = esc_attr($a['loop']);
	$muted = esc_attr($a['muted']);
	$begin = esc_attr($a['begin']);
	$fullscreen = esc_attr($a['fullscreen']);
	if ( $fullscreen == 'true' ) $style .= "margin: 0; ";
	$class = esc_attr($a['class']) == '' ? '' : ' '.esc_attr($a["class"]);
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";
	
	if ( ( strpos($link, 'youtube') !== false || strpos($link, 'vimeo') !== false ) && $preload == "false" ) :
		if ( strpos($link, 'youtube') !== false ) :
			$link = str_replace('/shorts/', '/embed/', $link);
			$id = str_replace('https://www.youtube.com/embed/', '', $link);
			$link .= "?autoplay=1&enablejsapi=1&version=3&playerapiid=ytplayer";
			if ( $thumb == '' ) : $thumb = '//i.ytimg.com/vi/'.$id.'/hqdefault.jpg'; endif;		
			if ( $related == "false" ) : $link .= "&rel=0";	endif;	
			if ( $begin != "" ) : $link .= "&start=".$begin; endif;	
		else:
			$id = str_replace('https://player.vimeo.com/video/', '', $link);
			$link .= "?autoplay=1&title=0&byline=0&portrait=0";
			if ( $thumb == '' ) :			
				$data = file_get_contents('https://vimeo.com/api/v2/video/'.$id.'.json');
				$data = json_decode($data);
				$thumb = str_replace('http:', '', $data[0]->thumbnail_large.'.jpg');
			endif;
		endif;
		
		return '<div class="block block-video span-'.$size.$class.' video-player"'.$tracking.' style="'.$style.'padding-top:'.$height.'%" data-thumb="'.$thumb.'" data-link="'.$link.'" data-id="'.$id.'"></div>';

	else:
		//return '<div class="block block-video span-'.$size.$class.'" style="'.$style.' padding-top:'.$height.'%"><iframe src="" data-src="'.$link.'" data-loading="delay" allowfullscreen></iframe></div>';
		$extension = substr($link, strpos($link, '.') + 1);
		$buildVid = '<div class="block block-video span-'.$size.$class.'"'.$tracking.'" style="'.$style.'">';
		$buildVid .= '<video ';
		if ( $controls == 'true' ) $buildVid .= 'controls ';
		if ( $autoplay == 'true' ) $buildVid .= 'autoplay ';
		if ( $loop == 'true' ) $buildVid .= 'loop ';
		if ( $muted == 'true' ) $buildVid .= 'muted ';
		$add_data = $mobile != '100' ? ' data-mobile-w="'.$mobile.'"' : '';
	
		$buildVid .= 'poster="'.$thumb.'" style="position:relative; top:0; left:0; width:100%; height:100%"'.$add_data.'>';
		$buildVid .= '<source src="'.$link.'" type="video/'.$extension.'">';
		$buildVid .= '<img loading="lazy" src="'.$thumb.'">';
		$buildVid .= '</video></div>';
		
		return $buildVid;
	endif;	
}

// Group Block
add_shortcode( 'group', 'battleplan_buildGroup' );
function battleplan_buildGroup( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'order'=>'', 'class'=>'', 'start'=>'', 'end'=>'', 'track'=>'' ), $atts );
	$size = esc_attr($a['size']);	
	$size = convertSize($size);
	$order = esc_attr($a['order']);	
	if ( $order != '' ) : $style = " style='order: ".$order." !important'"; else: $style = ""; endif;
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";

	return '<div class="block block-group span-'.$size.$class.'" '.$tracking.$style.'>'.do_shortcode($content).'</div>';
}

// Text Block
add_shortcode( 'txt', 'battleplan_buildText' );
function battleplan_buildText( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'order'=>'', 'class'=>'', 'start'=>'', 'end'=>'', 'track'=>'' ), $atts );
	$size = esc_attr($a['size']);	
	$size = convertSize($size);
	$order = esc_attr($a['order']);	
	if ( $order != '' ) : $style = " style='order: ".$order." !important'"; else: $style = ""; endif;
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}

	return '<div class="block block-text span-'.$size.$class.'" '.$tracking.$style.'>'.do_shortcode($content).'</div>';
}

// Button Block
add_shortcode( 'btn', 'battleplan_buildButton' );
function battleplan_buildButton( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'align'=>'center', 'order'=>'', 'link'=>'', 'get-biz'=>'', 'new-tab'=>'', 'class'=>'', 'track'=>'', 'fancy'=>'', 'icon'=>'false', 'top'=>0, 'left'=>0, 'graphic'=>'false', 'graphic-w'=>'40', 'ada'=>'', 'start'=>'', 'end'=>'', 'track'=>'' ), $atts );
	$getBiz = esc_attr($a['get-biz']);
	if ( $getBiz == "" ) {
		$link = esc_attr($a['link']);
		if ( $link == "" || $link == "none" || $link == "no" ) $link = "#";	
		if ( strpos($link, 'pdf') ) $link .= "?id=".time();	
	} else {
		$link = do_shortcode( '[get-biz info="'.$getBiz.'"]' );
	};
	$size = esc_attr($a['size']);	
	$size = convertSize($size);
	$align = esc_attr($a['align']);	
	if ( $align != "center" ) : $align = " button-".$align; else: $align = ""; endif;
	$order = esc_attr($a['order']);	
	if ( $order != '' ) : $style = " style='order: ".$order." !important'"; else: $style = ""; endif;
	$class = esc_attr($a['class']);
	$ada = esc_attr($a['ada']);
	if ( $ada != '' ) $ada = ' <span class="screen-reader-text">'.$ada.'</span>';
	$target = esc_attr($a['new-tab']);
	if ( $target == 'yes' || $target == "true" ) $target = ' target="_blank"';
	if ( $class != '' ) $class = " ".$class;
	$fancy = esc_attr($a['fancy']);	
	$icon = esc_attr($a['icon']);	
	$graphic = esc_attr($a['graphic']);	
	$graphicW = esc_attr($a['graphic-w']);		
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$adjust = $left != 0 || $top != 0 ? ' style="transform: translate('.$left.'px, '.$top.'px)"' : '';
	if ( $icon == "true" ) $icon = "chevron-right";	
	if ( $fancy != "" ) $fancy = "-".$fancy;
	if ( $icon != "false" ) : 
		$class .= " fancy".$fancy; 
		$content = '<span class="fancy-text">'.$content.'</span><span class="fancy-icon"><span class="icon '.$icon.'"'.$adjust.'></span></span>'; 
		array_push($GLOBALS['icon-css'], $icon);
	endif;
	if ( $graphic != "false" ) : $class .= " graphic-icon"; $content = '<img src="/wp-content/uploads/'.$graphic.'" width="'.$graphicW.'" height="'.$graphicW.'" style="aspect-ratio:'.$graphicW.'/'.$graphicW.'" alt="" /><span class="unique">'.$content.'</span>'; endif;	

		$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;	
	}
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";

	return '<div class="block block-button span-'.$size.$class.$align.'"'.$tracking.$style.'><a'.$target.' href="'.$link.'" class="button'.$class.'">'.do_shortcode($content).$ada.'</a></div>';
}  

// Accordion Block 
add_shortcode( 'accordion', 'battleplan_buildAccordion' );
function battleplan_buildAccordion( $atts, $content = null ) {
	wp_enqueue_script( 'battleplan-accordion', get_template_directory_uri().'/js/script-accordion.js', array(), _BP_VERSION, false );	
	
	$a = shortcode_atts( array( 'title'=>'', 'excerpt'=>'', 'class'=>'', 'active'=>'false', 'btn'=>'false', 'btn_collapse'=>'false', 'icon'=>'true', 'start'=>'', 'end'=>'', 'scroll'=>'true', 'track'=>'' ), $atts );
	$excerpt = esc_attr($a['excerpt']);
	if ( $excerpt != '' ) $excerpt = '<div class="accordion-excerpt"><div class="accordion-box"><p>'.$excerpt.'</p></div></div>';
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$title = esc_attr($a['title']);	
	$icon = esc_attr($a['icon']); 
	$btnCollapse = esc_attr($a['btn_collapse']);
	$btn = esc_attr($a['btn']);
	$scroll = esc_attr($a['btn_scroll']) == "true" ? "" : " no-scroll";
	$addBtn = $thumb = '';
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking";
	
	if ( $icon == 'false' ) : $icon = '';
	elseif ( $icon == 'true' ) : $icon = '<span class="accordion-icon" aria-hidden="true"></span>'; 
	else: $thumb = '<img src="'.$icon.'" alt="'.$title.'" />'; $icon=''; 
	endif;
	
	if ( $title ) $printTitle = '<h2 role="button" tabindex="0" class="accordion-title accordion-button'.$scroll.'">'.$icon.$title.'</h2>';
	
	if ( $btn != "false" ) :	
		if ( $btnCollapse == "false" ) $btnCollapse = "hide"; 
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

	return '<div class="block block-accordion'.$class.'"'.$tracking.'>'.$thumb.$printTitle.$excerpt.'<div class="accordion-content"><div class="accordion-box">'.do_shortcode($content).'</div></div>'.$addBtn.'</div>';	
}

// Parallax Section 
add_shortcode( 'parallax', 'battleplan_buildParallax' );
function battleplan_buildParallax( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'', 'type'=>'section', 'width'=>'edge', 'img-w'=>'2000', 'img-h'=>'1333', 'height'=>'800', 'padding'=>'50', 'pos-x'=>'center', 'pos-y'=>'top', 'bleed'=>'10', 'speed'=>'0.7', 'image'=>'', 'class'=>'', 'scroll-btn'=>'false', 'scroll-loc'=>'#page', 'scroll-icon'=>'chevron-down', 'z-index'=>'2', 'track'=>'' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$style = esc_attr($a['style']);
	if ( $style != '' ) $style = " style-".$style;
	$type = esc_attr($a['type']);
	$width = esc_attr($a['width']); 
	$imgW = esc_attr($a['img-w']);
	$imgH = esc_attr($a['img-h']);
	$height = esc_attr($a['height']); 
	if ( $height == "full" ) : 
		$height = "100vh"; 
	elseif ( strpos($height, "calc") === false && $height != "auto" ) : 
		$height = $height."px"; 
	endif; 
	$posX = esc_attr($a['pos-x']);
	$posY = esc_attr($a['pos-y']);
	$bleed = esc_attr($a['bleed']); 
	$padding = esc_attr($a['padding']); 
	$speed = esc_attr($a['speed']);
	$image = esc_attr($a['image']);	
	$class = esc_attr($a['class']);
	$zIndex = esc_attr($a['z-index']); 
	if ( $class != '' ) $class = " ".$class;
	$scrollBtn = esc_attr($a['scroll-btn']); 
	$scrollLoc = esc_attr($a['scroll-loc']); 
	$scrollIcon = esc_attr($a['scroll-icon']); 
	if ( $scrollBtn != "false" ) : $buildScrollBtn = '<div class="scroll-down"><a href="'.$scrollLoc.'"><span class="icon '.$scrollIcon.' aria-hidden="true"></span><span class="sr-only">Scroll Down</span></a></div>'; else: $buildScrollBtn = ''; endif;
	if ( !$name ) $name = "section-".rand(10000,99999);	
	$setUpElement = '';
	$tracking = esc_attr($a['track']) != '' ? ' data-track="'.esc_attr($a['track']).'"' : '';
	if ( $tracking != '' ) $class .= " tracking"; 
	
	$attachment_id = attachment_url_to_postid(site_url().$image);
	$alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);        
		
	if ( $type == "col" ) :	$div = "div"; else: $div = $type; endif;
	
	if ( is_mobile() ) :		
		$mobileSrc = explode('.', $image);			
		if ( strpos($mobileSrc[0], "-1920x") !== false ) { $mobileSrc[0] = substr($mobileSrc[0], 0, strpos($mobileSrc[0], "-1920x")); }	
		$ratio = $imgW / $imgH;
		$useRatio = 2;
		$realW = array(480, 640, 960, 1280);
		$realH = $useH = array(round($realW[0]/$ratio), round($realW[1]/$ratio), round($realW[2]/$ratio), round($realW[3]/$ratio));
		if ( $ratio < $useRatio ) { $useH = array(round($realW[0]/$useRatio), round($realW[1]/$useRatio), round($realW[2]/$useRatio), round($realW[3]/$useRatio)); }

		if ( $content != null ) :		
			if (is_file( $_SERVER['DOCUMENT_ROOT'].$mobileSrc[0].'-mobile'.'.'.$mobileSrc[1] ) ) : 
				$setUpElement .= do_shortcode('<'.$div.' id="'.$name.'" class="'.$type.$style.' '.$type.'-'.$width.' '.$type.'-parallax-disabled'.$class.' screen-'.$realW[$i].'"'.$tracking.' style="height: auto; padding-top: '.$padding.'px; padding-bottom: '.$padding.'px; background-image: url(../../..'.$mobileSrc[0].'-mobile'.'.'.$mobileSrc[1].'); background-size: cover; background-position: '.$posY." ".$posX.'">'.$content.'</'.$div.'>');
			else:		
				for ($i = 0; $i < count($realW); $i++) :			
					$setUpElement .= do_shortcode('<'.$div.' id="'.$name.'" class="'.$type.$style.' '.$type.'-'.$width.' '.$type.'-parallax-disabled'.$class.' screen-'.$realW[$i].'"'.$tracking.' style="height: auto; padding-top: '.$padding.'px; padding-bottom: '.$padding.'px; background-image: url(../../..'.$mobileSrc[0].'-'.$realW[$i].'x'.$realH[$i].'.'.$mobileSrc[1].'); background-size: cover; background-position: '.$posY." ".$posX.'">'.$content.'</'.$div.'>');		
				endfor;	
			endif;
		else : 
			for ($i = 0; $i < count($realW); $i++) :			
				$setUpElement .= '<'.$div.' class="'.$type.$style.' '.$type.'-'.$width.' '.$type.'-parallax-disabled'.$class.' screen-'.$realW[$i].'"'.$tracking.' style="height:'.$useH[$i].'px; background-image: url(../../..'.$mobileSrc[0].'-'.$realW[$i].'x'.$realH[$i].'.'.$mobileSrc[1].'); background-size: cover; background-position: '.$posY." ".$posX.'"></'.$div.'>';		
			endfor;						
		endif;	

		return $setUpElement;
	else:
		return do_shortcode('<'.$div.' id="'.$name.'" class="'.$type.$style.' '.$type.'-'.$width.' '.$type.'-parallax'.$class.'"'.$tracking.' style="height:'.$height.'" data-parallax="scroll" data-id="'.$name.'" data-natural-width="'.$imgW.'" data-natural-height="'.$imgH.'" data-position-x="'.$posX.'" data-position-y="'.$posY.'" data-z-index="'.$zIndex.'" data-bleed="'.$bleed.'" data-speed="'.$speed.'" data-alt-tag="'.$alt_text.'" data-image-src="'.$image.'">'.$content.$buildScrollBtn.'</'.$div.'>');				
	endif;	
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
	
	$buildSection .= '><div class="closeBtn" aria-label="close" aria-hidden="false" tabindex="0"><span class="icon x-large"></span></div>'.do_shortcode($content).'</section>';	
	
	return $buildSection;
}
 
add_shortcode( 'seek', 'battleplan_formField' );
function battleplan_formField( $atts, $content = null ) {
	$a = shortcode_atts( array( 'label'=>'Label', 'show'=>'true', 'id'=>'user-input', 'req'=>'false', 'width'=>'default', 'max-w'=>'', 'class'=>''), $atts );
	$id = esc_attr($a['id']);
	$label = esc_attr($a['label']);
	$req = esc_attr($a['req']);	
	$width = 'width-'.esc_attr($a['width']);
	$maxW = esc_attr($a['max-w']);	
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
		$buildInput = '<div class="form-input input-'.strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(" ","-",$label))).' input-'.$id.' '.$width.$class.'"'.$maxW.' '.$aria.'>';
		if ( $show == 'true' ) $buildInput .= '<label for="'.$id.'" class="'.$width.' label-baseline">'.$label;
		if ( $show == 'true' && $req != 'false' ) $buildInput .= $asterisk;
		if ( $show == 'true' ) $buildInput .= '</label>';
		$buildInput .= $content;
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
	$brand = $GLOBALS['customer_info']['site-brand'];	
	
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