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
	$a = shortcode_atts( array( 'start'=>'', 'end'=>'' ), $atts );
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
	if ( $min == "admin" || min == "administrator" ) : $min = "administrator"; else: if ( substr($min, 0, 3) !== "bp_" ) : $min = "bp_".$min; endif; endif;
	$role = battleplan_getUserRole( $identifier, 'name' );
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
	$a = shortcode_atts( array( 'name'=>'', 'hash'=>'', 'style'=>'', 'width'=>'', 'grid'=>'', 'break'=>'', 'valign'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'class'=>'', 'start'=>'', 'end'=>'' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	if ( $name ) : $name = " id='".$name."'"; else: $name = ""; endif;
	$hash = esc_attr($a['hash']);
	if ( $hash != '' ) $hash='data-hash="'.$hash.'"';
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$width = esc_attr($a['width']);
	if ( $width != '' ) $width = " section-".$width;
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$style = esc_attr($a['style']);
	if ( $style != '' ) $style = " style-".$style;
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}
	$grid = esc_attr($a['grid']);
	$break = esc_attr($a['break']);
	$valign = esc_attr($a['valign']);
	if ( $valign != '' ) $valign = " valign-".$valign;
	if ( $break != '' ) $break = " break-".$break;
	if ( $grid != '' ) :
		$buildLayout = '<div class="flex grid-'.$grid.$valign.$break.$class.'">'.do_shortcode($content).'</div>';
	else:
		$buildLayout = do_shortcode($content);
	endif;	
	$buildSection = '<section'.$name.' class="section'.$style.$width.$class.'" '.$hash;
	if ( $background != "" ) $buildSection .= ' style="background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;"';	
	$buildSection .= '>'.$buildLayout.'</section>';	
	
	return $buildSection;
}

// Layout (Nested)
add_shortcode( 'nested', 'battleplan_buildNested' );
function battleplan_buildNested( $atts, $content = null ) {
	$a = shortcode_atts( array( 'grid'=>'1', 'break'=>'', 'valign'=>'', 'class'=>'' ), $atts );
	$grid = esc_attr($a['grid']);
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$break = esc_attr($a['break']);
	$valign = esc_attr($a['valign']);
	if ( $valign != '' ) $valign = " valign-".$valign;
	if ( $break != '' ) $break = " break-".$break;

	$buildLayout = '<div class="flex nested grid-'.$grid.$valign.$break.$class.'">'.do_shortcode($content).'</div>';	
	
	return $buildLayout;
}

// Layout
add_shortcode( 'layout', 'battleplan_buildLayout' );
function battleplan_buildLayout( $atts, $content = null ) {
	$a = shortcode_atts( array( 'grid'=>'1', 'break'=>'', 'valign'=>'', 'class'=>'' ), $atts );
	$grid = esc_attr($a['grid']);
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$break = esc_attr($a['break']);
	$valign = esc_attr($a['valign']);
	if ( $valign != '' ) $valign = " valign-".$valign;
	if ( $break != '' ) $break = " break-".$break;

	$buildLayout = '<div class="flex grid-'.$grid.$valign.$break.$class.'">'.do_shortcode($content).'</div>';	
	
	return $buildLayout;
}

// Column
add_shortcode( 'col', 'battleplan_buildColumn' );
function battleplan_buildColumn( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'hash'=>'', 'class'=>'', 'align'=>'', 'valign'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'start'=>'', 'end'=>'' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	if ( $name ) : $name = " id='".$name."'"; else: $name = ""; endif;
	$hash = esc_attr($a['hash']);
	if ( $hash != '' ) $hash='data-hash="'.$hash.'"';
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$align = esc_attr($a['align']);
	if ( $align != '' ) $align = " text-".$align;
	$valign = esc_attr($a['valign']);
	if ( $valign != '' ) $valign = " valign-".$valign;
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}

	$buildCol = '<div'.$name.' class="col '.$class.$align.$valign.'" '.$hash.'><div class="col-inner"';
	if ( $background != "" ) $buildCol .= 'style="background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;"';	
	$buildCol .= '>';
	$buildCol .= do_shortcode($content);
	$buildCol .= '</div></div>';	
	
	return $buildCol;
}

// Image Block
add_shortcode( 'img', 'battleplan_buildImg' );
function battleplan_buildImg( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'order'=>'', 'link'=>'', 'new-tab'=>'', 'ada-hidden'=>'false', 'class'=>'', 'start'=>'', 'end'=>'' ), $atts );
	$order = esc_attr($a['order']);	
	if ( $order != '' ) $style = " style='order: ".$order." !important'";
	$link = esc_attr($a['link']);	
	$size = esc_attr($a['size']);	
	$size = convertSize($size);
	$class = esc_attr($a['class']);
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

	$buildImg = '<div class="block block-image span-'.$size.$class.'" '.$style.'>';
	if ( $link != '' ) : $buildImg .= '<a '.$target.' href="'.$link.'"'.$hidden.'>'; endif;
	$buildImg .= do_shortcode($content);
	if ( $link != '' ) : $buildImg .= '</a>'; endif; 
	$buildImg .= '</div>';

	return $buildImg;
}

// Video Block
add_shortcode( 'vid', 'battleplan_buildVid' );
function battleplan_buildVid( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'order'=>'', 'link'=>'', 'thumb'=>'', 'preload'=>'false', 'class'=>'', 'related'=>'false', 'start'=>'', 'end'=>'' ), $atts );
	$related = esc_attr($a['related']);	
	$order = esc_attr($a['order']);	
	if ( $order != '' ) $style = " order: ".$order;
	$link = esc_attr($a['link']);	
	$thumb = esc_attr($a['thumb']);	
	$preload = esc_attr($a['preload']);	
	$size = esc_attr($a['size']);	
	$size = convertSize($size);	
	$height = 56.25 * ($size/12);	
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}
	
	if ( ( strpos($link, 'youtube') !== false || strpos($link, 'vimeo') !== false ) && $preload == "false" ) :
		if ( strpos($link, 'youtube') !== false ) :
			$id = str_replace('https://www.youtube.com/embed/', '', $link);
			$link .= "?autoplay=1";
			if ( $thumb == '' ) : $thumb = '//i.ytimg.com/vi/'.$id.'/hqdefault.jpg'; endif;		
			if ( $related == "false" ) : $link .= "&rel=0";	endif;	
		else:
			$id = str_replace('https://player.vimeo.com/video/', '', $link);
			$link .= "?autoplay=1&title=0&byline=0&portrait=0";
			if ( $thumb == '' ) :			
				$data = file_get_contents('https://vimeo.com/api/v2/video/'.$id.'.json');
				$data = json_decode($data);
				$thumb = str_replace('http:', '', $data[0]->thumbnail_large.'.jpg');
			endif;
		endif;
		
		return '<div class="block block-video span-'.$size.$class.' video-player" style="'.$style.' padding-top:'.$height.'%" data-thumb="'.$thumb.'" data-link="'.$link.'" data-id="'.$id.'"></div>';

	else:
		return '<div class="block block-video span-'.$size.$class.'" style="'.$style.' padding-top:'.$height.'%"><iframe src="" data-src="'.$link.'" data-loading="delay" allowfullscreen></iframe></div>';
	endif;	
}

// Group Block
add_shortcode( 'group', 'battleplan_buildGroup' );
function battleplan_buildGroup( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'order'=>'', 'class'=>'', 'start'=>'', 'end'=>'' ), $atts );
	$size = esc_attr($a['size']);	
	$size = convertSize($size);
	$order = esc_attr($a['order']);	
	if ( $order != '' ) $style = " style='order: ".$order." !important'";
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}

	return '<div class="block block-group span-'.$size.$class.'" '.$style.'>'.do_shortcode($content).'</div>';
}

// Text Block
add_shortcode( 'txt', 'battleplan_buildText' );
function battleplan_buildText( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'order'=>'', 'class'=>'', 'start'=>'', 'end'=>'' ), $atts );
	$size = esc_attr($a['size']);	
	$size = convertSize($size);
	$order = esc_attr($a['order']);	
	if ( $order != '' ) $style = " style='order: ".$order." !important'";
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}

	return '<div class="block block-text span-'.$size.$class.'" '.$style.'>'.do_shortcode($content).'</div>';
}

// Button Block
add_shortcode( 'btn', 'battleplan_buildButton' );
function battleplan_buildButton( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'100', 'align'=>'center', 'order'=>'', 'link'=>'', 'get-biz'=>'', 'new-tab'=>'', 'class'=>'', 'fancy'=>'', 'icon'=>'false', 'ada'=>'', 'start'=>'', 'end'=>'' ), $atts );
	$getBiz = esc_attr($a['get-biz']);
	if ( $getBiz == "" ) :
		$link = esc_attr($a['link']);
		if ( $link == "" || $link == "none" || $link == "no" ) : $link = "#"; endif;
	else:
		$link = do_shortcode( '[get-biz info="'.$getBiz.'"]' );
	endif;
	$size = esc_attr($a['size']);	
	$size = convertSize($size);
	$align = esc_attr($a['align']);	
	if ( $align != "center" ) : $align = " button-".$align; else: $align = ""; endif;
	$order = esc_attr($a['order']);	
	if ( $order != '' ) $style = " style='order: ".$order." !important'";
	$class = esc_attr($a['class']);
	$ada = esc_attr($a['ada']);
	if ( $ada != '' ) $ada = ' <span class="screen-reader-text">'.$ada.'</span>';
	$target = esc_attr($a['new-tab']);
	if ( $target == 'yes' || $target == "true" ) $target = ' target="_blank"';
	if ( $class != '' ) $class = " ".$class;
	$fancy = esc_attr($a['fancy']);	
	$icon = esc_attr($a['icon']);	
	if ( $icon == "true" ) $icon = "fas fa-chevron-right";	
	if ( $fancy != "" ) $fancy = "-".$fancy;
	if ( $icon != "false" ) : $class .= " fancy".$fancy; $content = '<span class="fancy-text">'.$content.'</span><span class="fancy-icon"><i class="'.$icon.'"></i></span>'; endif;
	
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}

	return '<div class="block block-button span-'.$size.$class.$align.'"'.$style.'><a'.$target.' href="'.$link.'" class="button'.$class.'">'.$content.$ada.'</a></div>';
}  

// Accordion Block 
add_shortcode( 'accordion', 'battleplan_buildAccordion' );
function battleplan_buildAccordion( $atts, $content = null ) {
	wp_enqueue_script( 'battleplan-accordion', get_template_directory_uri().'/js/script-accordion.js', array(), _BP_VERSION, false );	
	
	$a = shortcode_atts( array( 'title'=>'', 'excerpt'=>'', 'class'=>'', 'active'=>'false', 'btn'=>'false', 'btn_collapse'=>'false', 'icon'=>'true', 'start'=>'', 'end'=>'' ), $atts );
	$excerpt = esc_attr($a['excerpt']);
	if ( $excerpt != '' ) $excerpt = '<div class="accordion-excerpt"><div class="accordion-box"><p>'.$excerpt.'</p></div></div>';
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$title = esc_attr($a['title']);	
	$icon = esc_attr($a['icon']); 
	$btnCollapse = esc_attr($a['btn_collapse']);
	$btn = esc_attr($a['btn']);
	$addBtn = $thumb = '';
	
	if ( $icon == 'false' ) : $icon = '';
	elseif ( $icon == 'true' ) : $icon = '<span class="accordion-icon" aria-hidden="true"></span>'; 
	else: $thumb = '<img src="'.$icon.'" alt="'.$title.'" />'; $icon=''; 
	endif;
	
	if ( $title ) $printTitle = '<h2 role="button" tabindex="0" class="accordion-title accordion-button">'.$icon.$title.'</h2>';
	
	if ( $btn != "false" ) :	
		if ( $btnCollapse == "false" ) $btnCollapse = "hide"; 
		if ( $btn == "true" ) :
			$printTitle = '<div class="block block-button"><button role="button" tabindex="0" class="accordion-title accordion-button" data-text="'.$title.'" data-collapse="'.$btnCollapse.'">'.$title.'</button></div>';
		else:
			if ( $title ) $printTitle = '<h2 class="accordion-title">'.$icon.$title.'</h2>';
			$addBtn = '<div class="block block-button"><button role="button" tabindex="0" class="accordion-button" data-text="'.$btn.'" data-collapse="'.$btnCollapse.'">'.$btn.'</button></div>';
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

	return '<div class="block block-accordion'.$class.'">'.$thumb.$printTitle.$excerpt.'<div class="accordion-content"><div class="accordion-box">'.do_shortcode($content).'</div></div>'.$addBtn.'</div>';	
}

// Parallax Section 
add_shortcode( 'parallax', 'battleplan_buildParallax' );
function battleplan_buildParallax( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'', 'type'=>'section', 'width'=>'edge', 'img-w'=>'2000', 'img-h'=>'1333', 'height'=>'800', 'padding'=>'50', 'pos-x'=>'center', 'pos-y'=>'top', 'bleed'=>'10', 'speed'=>'0.7', 'image'=>'', 'class'=>'', 'scroll-btn'=>'false', 'scroll-loc'=>'#page', 'scroll-icon'=>'fa-chevron-down' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$style = esc_attr($a['style']);
	if ( $style != '' ) $style = " style-".$style;
	$type = esc_attr($a['type']);
	$width = esc_attr($a['width']); 
	$imgW = esc_attr($a['img-w']);
	$imgH = esc_attr($a['img-h']);
	$height = esc_attr($a['height']);
	if ( $height == "full" ) : $height = "100vh"; elseif ( $height != "auto" ) : $height = $height."px"; endif;
	$posX = esc_attr($a['pos-x']);
	$posY = esc_attr($a['pos-y']);
	$bleed = esc_attr($a['bleed']); 
	$padding = esc_attr($a['padding']); 
	$speed = esc_attr($a['speed']);
	$image = esc_attr($a['image']);	
	$class = esc_attr($a['class']); 
	if ( $class != '' ) $class = " ".$class;
	$scrollBtn = esc_attr($a['scroll-btn']); 
	$scrollLoc = esc_attr($a['scroll-loc']); 
	$scrollIcon = esc_attr($a['scroll-icon']); 
	if ( $scrollBtn != "false" ) $buildScrollBtn = '<div class="scroll-down"><a href="'.$scrollLoc.'"><i class="fas '.$scrollIcon.' aria-hidden="true"></i><span class="sr-only">Scroll Down</span></a></div>';
	if ( !$name ) $name = "section-".rand(10000,99999);	
		
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
				$setUpElement .= do_shortcode('<'.$div.' id="'.$name.'" class="'.$type.$style.' '.$type.'-'.$width.' '.$type.'-parallax-disabled'.$class.' screen-'.$realW[$i].'" style="height: auto; padding-top: '.$padding.'px; padding-bottom: '.$padding.'px; background-image: url(../../..'.$mobileSrc[0].'-mobile'.'.'.$mobileSrc[1].'); background-size: cover; background-position: '.$posY." ".$posX.'">'.$content.'</'.$div.'>');
			else:		
				for ($i = 0; $i < count($realW); $i++) :			
					$setUpElement .= do_shortcode('<'.$div.' id="'.$name.'" class="'.$type.$style.' '.$type.'-'.$width.' '.$type.'-parallax-disabled'.$class.' screen-'.$realW[$i].'" style="height: auto; padding-top: '.$padding.'px; padding-bottom: '.$padding.'px; background-image: url(../../..'.$mobileSrc[0].'-'.$realW[$i].'x'.$realH[$i].'.'.$mobileSrc[1].'); background-size: cover; background-position: '.$posY." ".$posX.'">'.$content.'</'.$div.'>');		
				endfor;	
			endif;
		else : 
			for ($i = 0; $i < count($realW); $i++) :			
				$setUpElement .= '<'.$div.' class="'.$type.$style.' '.$type.'-'.$width.' '.$type.'-parallax-disabled'.$class.' screen-'.$realW[$i].'" style="height:'.$useH[$i].'px; background-image: url(../../..'.$mobileSrc[0].'-'.$realW[$i].'x'.$realH[$i].'.'.$mobileSrc[1].'); background-size: cover; background-position: '.$posY." ".$posX.'"></'.$div.'>';		
			endfor;						
		endif;	

		return $setUpElement;
	else:
		return do_shortcode('<'.$div.' id="'.$name.'" class="'.$type.$style.' '.$type.'-'.$width.' '.$type.'-parallax'.$class.'" style="height:'.$height.'" data-parallax="scroll" data-natural-width="'.$imgW.'" data-natural-height="'.$imgH.'" data-position-x="'.$posX.'" data-position-y="'.$posY.'" data-z-index="1" data-bleed="'.$bleed.'" data-speed="'.$speed.'" data-image-src="'.$image.'">'.$content.$buildScrollBtn.'</'.$div.'>');				
	endif;	
}

// Locked Section 
add_shortcode( 'lock', 'battleplan_buildLockedSection' );
function battleplan_buildLockedSection( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'lock', 'width'=>'edge', 'position'=>'bottom', 'delay'=>'3000', 'show'=>'session', 'background'=>'', 'left'=>'50', 'top'=>'50', 'class'=>'', 'start'=>'', 'end'=>'', 'btn-activated'=>'no' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$delay = esc_attr($a['delay']);
	$show = esc_attr($a['show']); 
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$width = esc_attr($a['width']);
	if ( $width != '' ) $width = " section-".$width;
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$pos = esc_attr($a['position']);
	$class = " position-".$pos;
	$style = esc_attr($a['style']);
	if ( $style != '' ) $style = " style-".$style;
	if ( $name ) : $name = " id='".$name."'"; else: $name = ""; endif;
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}
	$btnActivated = esc_attr($a['btn-activated']);
	if ($btnActivated == "true" || $btnActivated == "yes" ) $btnActivated = "yes";
	
	$buildSection = '<section'.$name.' class="section section-lock'.$style.$width.$class.'" data-pos="'.$pos.'" data-delay="'.$delay.'" data-show="'.$show.'" data-btn="'.$btnActivated.'"';
	if ( $background != "" ) $buildSection .= ' style="background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;"';	
	$buildSection .= '><div class="closeBtn" aria-label="close" aria-hidden="false" tabindex="0"><i class="fa fa-times"></i></div>'.do_shortcode($content).'</section>';	
	
	return $buildSection;
}
 
// Social Media Buttons 
add_shortcode( 'social-btn', 'battleplan_socialBtn' );
function battleplan_socialBtn( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', 'img'=>'', 'link'=>'' ), $atts );
	$type = $icon = esc_attr($a['type']);
	$link = esc_attr($a['link']);
	if ( $link == '' ) $link = do_shortcode('[get-biz info="'.$type.'"]');
	$prefix = "";
	$img = esc_attr($a['img']);
	$alt = "Visit us on ".$type;
			
	if ( $type == "email" ) : $prefix = "mailto:"; $icon = "fas fa-envelope"; $alt="Email us";	
	elseif ( $type == "facebook" ) : $icon = "fab fa-facebook-f";	
	elseif ( $type == "pinterest" ) : $icon = "fab fa-pinterest-p";	
	elseif ( $type == "linkedin" ) : $icon = "fab fa-linkedin-in";
	else: $icon = "fab fa-".$type; endif;
	
	if ( $img == '' ) : $iconLoc = '<i class="'.$icon.'" aria-hidden="true"></i><span class="sr-only">'.$type.'</span><span class="social-bg"></span>';
	else: $iconLoc = '<img loading="lazy" src = "'.$img.'" alt="'.$alt.'"/>'; endif;

	return '<a class="social-button '.$type.'-button" href="'.$prefix.$link.'" target="_blank" rel="noopener noreferrer">'.$iconLoc.'</a>';	
}
 
add_shortcode( 'seek', 'battleplan_formField' );
function battleplan_formField( $atts, $content = null ) {
	$a = shortcode_atts( array( 'label'=>'Label', 'show'=>'true', 'id'=>'user-input', 'req'=>'false', 'width'=>'default' ), $atts );
	$id = esc_attr($a['id']);
	$label = esc_attr($a['label']);
	$req = esc_attr($a['req']);	
	$width = 'width-'.esc_attr($a['width']);
	$show = esc_attr($a['show']);
	if ( $show != 'true' ) : $width = 'width-none'; $aria = 'aria-label="'.$label.'"'; endif;	
	$asterisk = '<span class="required"></span><span class="sr-only">Required Field</span>';
	
	if ( $label == "button" ) :	
		$buildInput .= '<div class="block block-button block-100">'.$content.'</div>';		
	else:	
		$buildInput = '<div class="form-input input-'.strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(" ","-",$label))).' input-'.$id.' '.$width.'" '.$aria.'>';
		if ( $show == 'true' ) $buildInput .= '<label for="'.$id.'" class="'.$width.' label-baseline">'.$label;
		if ( $show == 'true' && $req != 'false' ) $buildInput .= $asterisk;
		if ( $show == 'true' ) $buildInput .= '</label>';
		$buildInput .= $content;
		if ( $show != 'true' && $req != 'false' ) $buildInput .= $asterisk;
		$buildInput .= '</div>';
	endif;
	
	return $buildInput;
}
?>