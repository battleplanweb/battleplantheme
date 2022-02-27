<?php session_start();
/* Battle Plan Web Design functions and definitions
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shortcodes
# Functions to extend WordPress
# Register Custom Post Types
# Import Advanced Custom Fields
# Basic Theme Set Up
# User Roles
# Chron Jobs
# Universal Pages
# Custom Hooks
# Custom Actions
# AJAX Functions
# Grid Set Up

--------------------------------------------------------------*/

if ( !defined('_BP_VERSION') ) define( '_BP_VERSION', '10.17.1' );
if ( !defined('_SET_ALT_TEXT_TO_TITLE') ) define( '_SET_ALT_TEXT_TO_TITLE', 'false' );
if ( !defined('_BP_COUNT_ALL_VISITS') ) define( '_BP_COUNT_ALL_VISITS', 'false' );

if ( !defined('_HEADER_ID') ) define( '_HEADER_ID', get_page_by_path('site-header', OBJECT, 'elements')->ID ); 
if ( !defined('_USER_LOGIN') ) define( '_USER_LOGIN', wp_get_current_user()->user_login );
if ( !defined('_PAGE_SLUG') ) :
	if ( basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ) : define( '_PAGE_SLUG', basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ); 
	else: define( '_PAGE_SLUG', 'home' );
	endif;
endif;


$GLOBALS['customer_info'] = get_option('customer_info');

add_action('after_setup_theme', 'battleplan_setLoc');
function battleplan_setLoc() { 
	if ( isset($_COOKIE['site-location']) && $_COOKIE['site-location'] != '1' ) :
		$GLOBALS['customer_info'] = get_option('customer_info_'.$_COOKIE['site-location']);
	endif;
}

/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

// Returns Business Information for site-wide changes
add_shortcode( 'get-biz', 'battleplan_getBizInfo' );
function battleplan_getBizInfo($atts, $content = null ) {
	$a = shortcode_atts( array( 'info' => 'name', ), $atts );
	$data = esc_attr($a['info']);
	
	if ( $data == "copyright" ) :
		$currYear=date("Y"); 
		$startYear = $GLOBALS['customer_info']['year'];
		if ( $startYear == $currYear ) : return "© ".$currYear;
		else: return "© ".$startYear."-".$currYear; 
		endif;
	endif;
	
	if ( $data == "area" ) return $GLOBALS['customer_info']['area-before'].$GLOBALS['customer_info']['area'].$GLOBALS['customer_info']['area-after'];
	
	if ( $data == "area-phone" || $data == "phone-link" || strpos($data, 'phone-alt') !== false || $data == "mm-bar-phone" ) :
		$phoneFull = $GLOBALS['customer_info']['area'].'-'.$GLOBALS['customer_info']['phone'];
		$phoneFormat = $GLOBALS['customer_info'][$data];	
		if ( $data == "mm-bar-phone" ) :
			$phoneFormat = '<div class="mm-bar-btn mm-bar-phone call-btn" aria-hidden="true"></div><span class="sr-only">Call Us</span>';	
		elseif ( $data == "area-phone" || $data == "phone-link" ) :
			$phoneFormat = $GLOBALS['customer_info']['area-before'].$GLOBALS['customer_info']['area'].$GLOBALS['customer_info']['area-after'].$GLOBALS['customer_info']['phone'];	
		endif;		
		return '<a href="#" class="phone-link track-clicks" data-action="phone call" data-url="tel:1-'.$phoneFull.'">'.$phoneFormat.'</a>';
	endif;
	
	return $GLOBALS['customer_info'][$data];
}

// Returns current year
add_shortcode( 'get-year', 'battleplan_getYear' );
function battleplan_getYear() { return date("Y"); }

// Get the Number of Years in Business
add_shortcode( 'get-years', 'battleplan_getYears' );
function battleplan_getYears($atts, $content = null) {
	$a = shortcode_atts( array( 'start'=>'', 'label'=>'', 'mult'=>'1'  ), $atts );
	$startYear = esc_attr($a['start']);	
	$label = esc_attr($a['label']);	
	$multiplier = esc_attr($a['mult']);	
	$currYear=date("Y"); 
	$years = ( $currYear - $startYear ) * $multiplier;
	if ( $label == "no" || $label == "false" ) : return $years;
	else: if ( $years == 1 ) : return "1 year"; else: return $years." years"; endif; endif;
}

// Get the Current Season and Print HTML Accordingly 
add_shortcode( 'get-season', 'battleplan_getSeason' );
function battleplan_getSeason($atts, $content = null) {
	$a = shortcode_atts( array( 'spring'=>'', 'summer'=>'', 'fall'=>'', 'winter'=>'',  ), $atts );
	$summer = wp_kses_post($a['summer']);	
	$winter = wp_kses_post($a['winter']);	
	$spring = wp_kses_post($a['spring']);	
	$fall = wp_kses_post($a['fall']);	
	if ( $spring == '' ) $spring = $summer;
	if ( $fall == '' ) $fall = $winter;
	if (date("m")>="03" && date("m")<="05") : return $spring; 
	elseif (date("m")>="06" && date("m")<="08") : return $summer; 
	elseif (date("m")>="09" && date("m")<="11") : return $fall; 
	else: return $winter; endif; 
}

// Find Number of Days Between Two Dates 
add_shortcode( 'days-ago', 'battleplan_daysAgo' );
function battleplan_daysAgo( $atts, $content = null ) {
	$a = shortcode_atts( array( 'oldest'=>'', 'newest'=>'today' ), $atts );
	$oldest = esc_attr($a['oldest']);
	$newest = esc_attr($a['newest']);
	
	if ( $newest == "today" || $newest == "now" ) : $newest = time(); else: $newest = strtotime($newest); endif;
	$oldest = strtotime($oldest);	
	$datediff = $newest - $oldest; 
	return abs(round($datediff / 86400));
}

// Load the Featured Image, Title, Excerpt, Content or Permalink from a Separate Post, Page or Custom Post Type
add_shortcode( 'get-wp-page', 'battleplan_getWordPressPage' );
function battleplan_getWordPressPage( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'page', 'id'=>'', 'slug'=>'', 'title'=>'', 'display'=>"content" ), $atts );
	$type = esc_attr($a['type']);
	$id = esc_attr($a['id']);
	$slug = esc_attr($a['slug']);
	$title = esc_attr($a['title']);
	$display = esc_attr($a['display']);

	if ( $id != "" ) : $pageID = $id; 
	elseif ( $slug != "" ) : 
	 	if ( get_page_by_path( $slug, OBJECT, $type ) ) :
			$pageID = get_page_by_path( $slug, OBJECT, $type )->ID; 
		else:
			$getCPT = get_post_types();  
			foreach ($getCPT as $postType) :
				if ( get_page_by_path($slug, OBJECT, $postType) ) : $pageID = get_page_by_path($slug, OBJECT, $postType)->ID; break; endif;
			endforeach;		
		endif;	
	elseif ( $title != "" ) : 
	 	if ( get_page_by_title( $title, OBJECT, $type ) ) :
			$pageID = get_page_by_title( $title, OBJECT, $type )->ID; 
		else:
			$getCPT = get_post_types();  
			foreach ($getCPT as $postType) :
				if ( get_page_by_title( $title, OBJECT, $postType) ) : $pageID = get_page_by_title( $title, OBJECT, $postType)->ID; break; endif;
			endforeach;		
		endif;	
	else:
		return;
	endif;

	$getPage = get_post($pageID);
	if ( $display == "content" && get_post_status($getPage->ID) == "publish" ) return apply_filters('the_content', $getPage->post_content);
	if ( $display == "title" && get_post_status($getPage->ID) == "publish" ) return esc_html( get_the_title($pageID));
	if ( $display == "excerpt" && get_post_status($getPage->ID) == "publish" ) return apply_filters('the_excerpt', $getPage->post_excerpt);
	if ( $display == "thumbnail" && get_post_status($getPage->ID) == "publish" ) return get_the_post_thumbnail($pageID, 'thumbnail');
	if ( $display == "link" && get_post_status($getPage->ID) == "publish" ) return esc_url(get_permalink($pageID));
}

// Checks to see if slug exists, and if so prints it
add_shortcode( 'get-element', 'battleplan_getElement' );
function battleplan_getElement( $atts, $content = null ) {
	$a = shortcode_atts( array( 'slug'=>'' ), $atts );
	$page_slug = esc_attr($a['slug']);	
	$page_slug_home = $page_slug."-home";	

	if ( is_front_page() ) :
		if ( get_page_by_path( $page_slug_home, OBJECT, 'elements' ) ) : $page_data = get_page_by_path( $page_slug_home, OBJECT, 'elements' ); endif;
	endif;
	
	if ( !$page_data ) :
		if ( get_page_by_path( $page_slug, OBJECT, 'elements' ) ) : $page_data = get_page_by_path( $page_slug, OBJECT, 'elements' ); endif;
	endif;

	if ( $page_data && get_post_status($page_data->ID) == "publish" ) return apply_filters('the_content', $page_data->post_content); 
}

// Returns website address (for privacy policy, etc)
add_shortcode( 'get-domain', 'battleplan_getDomain' );
function battleplan_getDomain( $atts, $content = null ) {
	$a = shortcode_atts( array( 'link'=>'false', ), $atts );
	$link = esc_attr($a['link']);	
	if ( $link == "false" ) : return esc_url(get_site_url());
	else: return '<a href="'.esc_url(get_site_url()).'">'.esc_url(get_site_url()).'</a>'; endif;
}

// Returns website address (minus https and with/without .com)
add_shortcode( 'get-domain-name', 'battleplan_getDomainName' );
function battleplan_getDomainName( $atts, $content = null ) {
	$a = shortcode_atts( array( 'ext'=>'false', ), $atts );
	$ext = esc_attr($a['ext']);	
	$url = esc_url(get_site_url());
	$parts = explode('.', parse_url($url, PHP_URL_HOST));
	$printDomain = $parts[0];
	if ( $ext != "false" ) $printDomain .= '.'.$parts[1];
	return $printDomain;
}

// Returns url of page (minus domain, choose whether to include variables)
add_shortcode( 'get-url', 'battleplan_getURL' );
function battleplan_getURL( $atts, $content = null ) {
	$a = shortcode_atts( array( 'var'=>'true', ), $atts );
	$var = esc_attr($a['var']);	
	
	if ( $var == "false" ) :
		return strtok($_SERVER['REQUEST_URI'],'?');
	else:
		return $_SERVER['REQUEST_URI'];
	endif;
}

// Returns url variable
add_shortcode( 'get-url-var', 'battleplan_getURLVar' );
function battleplan_getURLVar($atts, $content = null) {
	$a = shortcode_atts( array( 'var'=>'', ), $atts );
	$var = esc_attr($a['var']);	
	$getVars = array();
	foreach( $_GET as $key => $value ) $getVars[$key] = $value;
	
	return $getVars[$var]; 
}

// Show count of posts, images, etc.
add_shortcode( 'get-count', 'battleplan_getCount' );
function battleplan_getCount($atts, $content = null) {
	$a = shortcode_atts( array( 'type'=>'post', 'status'=>'publish', 'tax'=>'', 'term'=>'',  ), $atts );
	$postType = esc_attr($a['type']);	
	$postStatus = esc_attr($a['status']);	
	$tax = esc_attr($a['tax']);		
	$term = esc_attr($a['term']);	

	if ( $tax == '' ) :	$args = array( 'post_type'=>$postType, 'post_status'=>$postStatus, 'posts_per_page'=>-1);
	else: $args = array( 'post_type'=>$postType, 'post_status'=>$postStatus, 'posts_per_page'=>-1, 'tax_query'=>array( 'relation'=>'AND', array( 'taxonomy'=>$tax, 'field'=>'slug', 'terms'=>$term )));
	endif;

	$query = new WP_Query($args);
	return number_format((int)$query->post_count);
}

// Add Social Media Share Buttons
add_shortcode( 'add-share-buttons', 'battleplan_addShareButtons' );
function battleplan_addShareButtons( $atts, $content = null ) {
	$a = shortcode_atts( array( 'facebook'=>'false', 'twitter'=>'false', 'pinterest'=>'false' ), $atts );
	$facebook = esc_attr($a['facebook']);
	$twitter = esc_attr($a['twitter']);	
	$pinterest = esc_attr($a['pinterest']);	
	global $post;
    $postURL = urlencode( esc_url( get_permalink($post->ID) ));
    $postTitle = urlencode( $post->postTitle );
    $postImg = urlencode(get_the_post_thumbnail_url( $post->ID, 'thumbnail' ));
    $facebookLink = sprintf( 'https://www.facebook.com/sharer/sharer.php?u=%1$s', $postURL );
    $twitterLink = sprintf( 'https://twitter.com/intent/tweet?text=%2$s&url=%1$s', $postURL, $postTitle );
	$pinterestLink = sprintf( 'https://pinterest.com/pin/create/button/?url=%1$s&media=%2$s&description=%3$s', $postURL, $postImg, $postTitle );

    $output = '<div class="social-share-buttons">';
	if ( $facebook == "true" ) $output .= '<a tooltip="Click to share on Facebook" target="_blank" href="'.$facebookLink.'" class="share-button facebook"><i class="fab fa-facebook-f" aria-hidden="true"></i><span class="sr-only">Share on Facebook</span></a>';
	if ( $twitter == "true" ) $output .= '<a tooltip="Click to share on Twitter" target="_blank" href="'.$twitterLink.'" class="share-button twitter"><i class="fab fa-twitter" aria-hidden="true"></i><span class="sr-only">Share on Twitter</span></a>';
	if ( $pinterest == "true" ) $output .= '<a tooltip="Click to share on Pinterest" target="_blank" href="'.$pinterestLink.'" class="share-button pinterest"><i class="fab fa-pinterest-p" aria-hidden="true"></i><span class="sr-only">Share on Pinterest</span></a>';
    $output .= '</div><!-- .social-share-buttons -->';
 
    return $output;	
};

// Choose random text from given choices
add_shortcode( 'get-random-text', 'battleplan_getRandomText' );
function battleplan_getRandomText($atts, $content = null) {
	$a = shortcode_atts( array( 'cookie'=>'true', 'text1'=>'', 'text2'=>'', 'text3'=>'', 'text4'=>'', 'text5'=>'', 'text6'=>'', 'text7'=>'',  ), $atts );
	$cookie = esc_attr($a['cookie']);	
	$textArray = array( wp_kses_post($a['text1']), wp_kses_post($a['text2']), wp_kses_post($a['text3']), wp_kses_post($a['text4']), wp_kses_post($a['text5']), wp_kses_post($a['text6']), wp_kses_post($a['text7']) );	
	$textArray = array_filter($textArray);
	$num = count($textArray) - 1;	
	
	if ( $cookie != "false" && $_COOKIE['random-text'] != '' ) : $rand = $_COOKIE['random-text'];
	else : $rand=rand(0,$num); endif;
	
	$printText = $textArray[$rand];
	
	if ( $rand == $num ) : $rand = 0; else: $rand++; endif;	
	//if ( $cookie != "false" ) setcookie('random-text', $rand, time() + (86400 * 7), '/', '', true, false);
	if ( $cookie != "false" ) writeCookie('random-text', $rand, 7);

	return $printText;
}

// Display a random photo from tagged images 
add_shortcode( 'get-random-image', 'battleplan_getRandomImage' );
function battleplan_getRandomImage($atts, $content = null ) {	
	$a = shortcode_atts( array( 'id'=>'', 'tag'=>'', 'size'=>'thumbnail', 'link'=>'no', 'number'=>'1', 'offset'=>'0', 'align'=>'left', 'class'=>'', 'order_by'=>'recent', 'order'=>'asc', 'shuffle'=>'no', 'lazy'=>'true' ), $atts );
	$tag = esc_attr($a['tag']);	
	$tags = explode( ',', $tag );
	if ( $tag == "page-slug" ) $tags = _PAGE_SLUG; 
	$size = esc_attr($a['size']);	
	$link = esc_attr($a['link']);	
	$align = esc_attr($a['align']);	
	$number = esc_attr($a['number']);			
	$offset = esc_attr($a['offset']);		
	$orderBy = esc_attr($a['order_by']);		
	$order = esc_attr($a['order']);		
	$shuffle = esc_attr($a['shuffle']);	
	$lazy = esc_attr($a['lazy']);	
	if ( $lazy == "true" ) : $lazy = "lazy"; else: $lazy = "eager"; endif;
	$id = esc_attr($a['id']);		
	if ( $id == "current" ) $id = get_the_ID();
	$class = esc_attr($a['class']);	
	if ( $class != '' ) $class = " ".$class;
	if ( $align != '' ) $align = "align".$align;
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$number, 'offset'=>$offset, 'order'=>$order);

	if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-180day' ) : $args['meta_key']="log-views-total-180day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-tease-time"; $args['orderby']='meta_value_num';	
	else : $args['orderby']=$orderBy; endif;		
	
	if ( $id == '' ) : 
		$args['tax_query']=array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags ));
	elseif ( $tag != '' ) :
		$args['post_parent']=$id;
		$args['tax_query']=array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags ));
	else :
		$args['post_parent']=$id;
	endif;

	$image_query = new WP_Query($args);		
	$imageArray = array();

	if( $image_query->have_posts() ) : while ($image_query->have_posts() ) : $image_query->the_post();
		$getID = get_the_ID();
		$full = wp_get_attachment_image_src($getID, 'full');
		$image = wp_get_attachment_image_src($getID, $size);
		$imgSet = wp_get_attachment_image_srcset($getID, $size );
	
		$buildImage = "";	
		if ( $link == "yes" ) $buildImage .= '<a href="'.$full[0].'">';		
		$buildImage .= '<img data-id="'.$getID.'"'.getImgMeta($getID).' data-count-tease="true" data-count-view="true" class="wp-image-'.$getID.' random-img '.$tags[0].'-img '.$align.' size-'.$size.$class.'" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';	
		if ( $link == "yes" ) $buildImage .= '</a>';	
		$imageArray[] = $buildImage;	
	
	endwhile; wp_reset_postdata(); endif;	
	if ( $shuffle != "no" ) : shuffle($imageArray); endif;
	return printArray($imageArray);
}

// Display a row of square pics from tagged images
add_shortcode( 'get-row-of-pics', 'battleplan_getRowOfPics' );
function battleplan_getRowOfPics($atts, $content = null ) {	
	$a = shortcode_atts( array( 'id'=>'', 'tag'=>'row-of-pics', 'link'=>'no', 'col'=>'4', 'row'=>'1', 'offset'=>'0', 'size'=>'half-s', 'valign'=>'center', 'class'=>'', 'order_by'=>'recent', 'order'=>'asc', 'shuffle'=>'no', 'lazy'=>'true' ), $atts );
	$col = esc_attr($a['col']);		
	$row = esc_attr($a['row']);		
	$size = esc_attr($a['size']);
	$num = $row * $col;
	$offset = esc_attr($a['offset']);
	$tag = esc_attr($a['tag']);	
	$tags = explode( ',', $tag );
	$link = esc_attr($a['link']);	
	$orderBy = esc_attr($a['order_by']);		
	$order = esc_attr($a['order']);		
	$valign = esc_attr($a['valign']);		
	$shuffle = esc_attr($a['shuffle']);	
	$lazy = esc_attr($a['lazy']);	
	if ( $lazy == "true" ) : $lazy = "lazy"; else: $lazy = "eager"; endif;
	$class = esc_attr($a['class']);	
	if ( $class != '' ) $class = " ".$class;
	$id = esc_attr($a['id']);	
	if ( $id == "current" ) $id = get_the_ID();
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$num, 'offset'=>$offset, 'order'=>$order, 'tax_query'=>array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags )));

	if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-180day' ) : $args['meta_key']="log-views-total-180day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-tease-time"; $args['orderby']='meta_value_num';	
	else : $args['orderby']=$orderBy; endif;		
	
	if ( $id != '' ) : 
		$args['post_parent']=$id;
	endif;

	$image_query = new WP_Query($args);		
	$imageArray = array();

	if( $image_query->have_posts() ) : while ($image_query->have_posts() ) : $image_query->the_post();
		$getID = get_the_ID();
		$image = wp_get_attachment_image_src( $getID, $size );
		$imgSet = wp_get_attachment_image_srcset( $getID, $size );
	
		$getImage = "";
		if ( $link == "yes" ) $getImage .= '<a href="'.$image[0].'">';
		$getImage .= '<img data-id="'.$getID.'"'.getImgMeta($getID).' data-count-tease="true" data-count-view="true" class="random-img '.$tags[0].'-img '.$align.'" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';
		if ( $link == "yes" ) $getImage .= '</a>';

		$imageArray[] = do_shortcode('[col class="col-row-of-pics'.$class.'"]'.$getImage.'[/col]');		
		$ratioArray[] = $image[2] / $image[1];	
	endwhile; wp_reset_postdata(); endif;
	
	if ( $shuffle == "yes" || $shuffle == "true" || $shuffle == "random" ) : shuffle($imageArray); 
	elseif ( $shuffle == "peak" || $shuffle == "valley" ) :	
		if ( $shuffle == "peak" ) :	array_multisort($ratioArray, SORT_ASC, SORT_NUMERIC, $imageArray, SORT_ASC);
		else: array_multisort($ratioArray, SORT_DESC, SORT_NUMERIC, $imageArray, SORT_DESC); endif;
	 	$result = array();
		$count = count($imageArray);
		for ($counter=0; $counter < $count; $counter++) :			
			if ($counter % 2 == 0) :
				array_push($result, $imageArray[$counter]);
				unset($imageArray[$counter]);
			endif;	
		endfor;
		$imageArray = array_merge($imageArray, array_reverse($result));
	elseif ( $shuffle == "alternate" ) :	
		$rand = rand(1,2);
		if ( $rand == 1) : array_multisort($ratioArray, SORT_ASC, SORT_NUMERIC, $imageArray, SORT_ASC); else: array_multisort($ratioArray, SORT_DESC, SORT_NUMERIC, $imageArray, SORT_DESC); endif;
		$result= array();
		$count = count($imageArray);
		for ($counter=0; $counter * 2 < $count; $counter++) :
			$anticounter = $count - $counter - 1;
			array_push($result, $imageArray[$anticounter]);
			if ($counter != $anticounter) : array_push($result, $imageArray[$counter]); endif;
		endfor;
		$left = array_slice($result, 0, count($result)/2);
		$right = array_slice($result, count($result)/2);
		$imageArray = array_merge($right, array_reverse($left));
	endif;
							   
	$print .= do_shortcode('[layout grid="'.$col.'e" valign="'.$valign.'"]'.printArray($imageArray).'[/layout]'); 
	return $print;
}

// Build an archive
add_shortcode( 'build-archive', 'battleplan_getBuildArchive' );
function battleplan_getBuildArchive($atts, $content = null) {	
	$a = shortcode_atts( array( 'type'=>'', 'count_tease'=>'false', 'count_view'=>'false', 'thumb_only'=>'false', 'show_btn'=>'false', 'btn_text'=>'Read More', 'btn_pos'=>'outside', 'show_title'=>'true', 'title_pos'=>'outside', 'show_date'=>'false', 'show_author'=>'false', 'show_social'=>'false', 'show_excerpt'=>'true', 'show_content'=>'false', 'add_info'=>'', 'show_thumb'=>'true', 'no_pic'=>'', 'size'=>'thumbnail', 'pic_size'=>'1/3', 'text_size'=>'', 'accordion'=>'false', 'link'=>'post', 'truncate'=>'false' ), $atts );
	$type = esc_attr($a['type']);
	$truncate = esc_attr($a['truncate']);
	$countTease = esc_attr($a['count_tease']);	
	$countView = esc_attr($a['count_view']);	
	$showBtn = esc_attr($a['show_btn']);	
	$btnText = esc_attr($a['btn_text']);		
	$btnPos = esc_attr($a['btn_pos']);		
	$showTitle = esc_attr($a['show_title']);		 
	$titlePos = esc_attr($a['title_pos']);		
	$showDate = esc_attr($a['show_date']);		
	$showAuthor = esc_attr($a['show_author']);		
	$showSocial = esc_attr($a['show_social']);		
	$showExcerpt = esc_attr($a['show_excerpt']);		
	$showContent = esc_attr($a['show_content']);	
	if ( $showContent == "true" ) : 
		$showExcerpt = "false"; 
		$content = apply_filters('the_content', get_the_content()); 
	else:
		if ( $showExcerpt == "true" ) :
			$content = apply_filters('the_excerpt', get_the_excerpt());
		else:
			$content = "";
		endif;
	endif;
	$content .= wp_kses_post($a['add_info']);
	if ( $truncate != "false" && $truncate != "no" ) : 
		if ($truncate == "true" || $truncate == "yes" ) : $content = truncateText($content); else: $content = truncateText($content, $truncate); endif;
	endif;
	$showThumb = esc_attr($a['show_thumb']);
	if ( $showThumb != "false" ) $showThumb = "true";
	$size = esc_attr($a['size']);	
	if ( $size == "" ) $size = "thumbnail";
	$picSize = esc_attr($a['pic_size']);	
	$textSize = esc_attr($a['text_size']);		
	$accordion = esc_attr($a['accordion']);			
	$format = esc_attr($a['format_text']);
	$link = esc_attr($a['link']);	
	if ( strpos($link, 'cf-') === 0 ) : $linkLoc = esc_url(get_field(str_replace('cf-', '', $link)));
	elseif ( $type == "testimonials" ) : $linkLoc = "/testimonials/";
	elseif ( $link == "false" || $link == "no" ) : $link = "false"; $linkLoc = "";
	elseif ( $link == "" || $link == "post" ) : $linkLoc = esc_url(get_the_permalink(get_the_ID()));	
	else: $linkLoc = $link;	endif;
	$noPic = esc_attr($a['no_pic']);	
	if ( $noPic == "" ) $noPic = "false";	
	if ( $showBtn == "true" ) : $picADA = " ada-hidden='true'"; $titleADA = " aria-hidden='true' tabindex='-1'";
	elseif ( $showTitle != "false" ) : $picADA = " ada-hidden='true'"; endif;
	$thumbOnly = esc_attr($a['thumb_only']);
		
	if ( has_post_thumbnail() && $showThumb == "true" ) : 	
		$meta = wp_get_attachment_metadata( get_post_thumbnail_id( get_the_ID() ) );
		$thumbW = $meta['sizes'][$size]['width'];
		$thumbH = $meta['sizes'][$size]['height'];
	
		$buildImg = do_shortcode('[img size="'.$picSize.'" class="image-'.$type.'" link="'.$linkLoc.'" '.$picADA.']'.get_the_post_thumbnail( get_the_ID(), $size, array( 'class'=>'img-archive img-'.$type.$googleTag, 'style'=>'aspect-ratio:'.$thumbW.'/'.$thumbH )).'[/img]'); 
		if ( $textSize == "" ) : $textSize = getTextSize($picSize); endif;
	
		if ( _SET_ALT_TEXT_TO_TITLE == "yes" ) :
			$attachment_id = get_post_thumbnail_id( get_the_ID() );
			if ( readMeta( $attachment_id, '_wp_attachment_image_alt', true ) == "" ) :
				updateMeta( $attachment_id, '_wp_attachment_image_alt', esc_html(get_the_title()) );
			endif;
		endif;	
	
	elseif ( $noPic != "false") : 	
		$buildImg = do_shortcode("[img size='".$picSize."' class='image-".$type." block-placeholder placeholder-".$type."' link='".$linkLoc."' ".$picADA."]".wp_get_attachment_image( $noPic, $size, array( 'class'=>'img-archive img-'.$type.$googleTag ))."[/img]"); 
		if ( $textSize == "" ) : $textSize = getTextSize($picSize); endif;	
	
	else : $textSize = "100"; endif;
	
	if ( $type == "testimonials" ) {
		$testimonialName = esc_attr(get_field( "testimonial_name" ));
		$testimonialPhone = esc_attr(get_field( "testimonial_phone" ));
		$testimonialEmail = esc_attr(get_field( "testimonial_email" ));
		$testimonialTitle = esc_attr(get_field( "testimonial_title" ));
		$testimonialBiz = esc_attr(get_field( "testimonial_biz" ));
		$testimonialWeb = esc_attr(get_field( "testimonial_website" ));
		$testimonialLoc = esc_attr(get_field( "testimonial_location" ));
		$testimonialPlatform = esc_attr(get_field( "testimonial_platform" ));
		$testimonialPlatform = strtolower($testimonialPlatform);
		$testimonialRate = esc_attr(get_field( "testimonial_rating" ));	
		$testimonialMisc1 = esc_attr(get_field( "testimonial_misc1" ));	
		$testimonialMisc2 = esc_attr(get_field( "testimonial_misc2" ));	
		$testimonialMisc3 = esc_attr(get_field( "testimonial_misc3" ));	
		$testimonialMisc4 = esc_attr(get_field( "testimonial_misc4" ));
		
		$buildCredentials = "<div class='testimonials-credential testimonials-name' data-count-tease='true' data-count-view='true' data-id=".get_the_ID().">".$testimonialName;
		if ( $testimonialTitle ) $buildCredentials .= "<span class='testimonials-title'>, ".$testimonialTitle."</span>";
		$buildCredentials .= "</div>";
		if ( $testimonialBiz ) :
			$buildCredentials .= "<div class='testimonials-credential testimonials-business'>";
			if ( $testimonialWeb ) $buildCredentials .= "<a href='https://".$testimonialWeb."' target='_blank'>"; 
			$buildCredentials .= $testimonialBiz;
			if ( $testimonialWeb ) $buildCredentials .= "</a>"; 
			$buildCredentials .= "</div>";
		endif; 
		if ( $testimonialLoc ) $buildCredentials .= "<div class='testimonials-credential testimonials-location'>".$testimonialLoc."</div>"; 
		if ( $testimonialPhone ) $buildCredentials .= "<div class='testimonials-credential testimonials-phone'>".$testimonialPhone."</div>";
		if ( $testimonialEmail ) $buildCredentials .= "<div class='testimonials-credential testimonials-email'><a href='mailto:".$testimonialEmail."'>".$testimonialEmail."</a></div>";			
		if ( $testimonialMisc1 ) $buildCredentials .= "<div class='testimonials-credential testimonials-misc1'>".$testimonialMisc1."</div>";
		if ( $testimonialMisc2 ) $buildCredentials .= "<div class='testimonials-credential testimonials-misc2'>".$testimonialMisc2."</div>";
		if ( $testimonialMisc3 ) $buildCredentials .= "<div class='testimonials-credential testimonials-misc3'>".$testimonialMisc3."</div>";
		if ( $testimonialMisc4 ) $buildCredentials .= "<div class='testimonials-credential testimonials-misc4'>".$testimonialMisc4."</div>";
		if ( $testimonialRate ) $buildCredentials .= "<div class='testimonials-credential testimonials-rating'>".$testimonialRate."</div>";
		
		$content = apply_filters('the_content', get_the_content()); 
		if ( $truncate != "false" && $truncate != "no" ) : 
			if ($truncate == "true" || $truncate == "yes" ) : $content = truncateText($content); else: $content = truncateText($content, $truncate); endif;
		endif;
		
		$archiveBody = '[txt class="testimonials-quote"][p]'.$content.'[/p][/txt][txt size="11/12" class="testimonials-credentials"]'.$buildCredentials.'[/txt][txt size="1/12" class="testimonials-platform testimonials-platform-'.$testimonialPlatform.'"][/txt]';
	} else {
		if ( $accordion == "true" ) :		
			$title = esc_html(get_the_title());
			$excerpt = wp_kses_post(get_the_excerpt());	
			$archiveBody = '[accordion title="'.$title.'" excerpt="'.$excerpt.'"]'.$content.'[/accordion]';		
		else :		
			$archiveMeta = $archiveBody = "";
			if ( $showTitle != "false" ) :
				$archiveMeta .= "<h3 data-count-tease=".$countTease." data-count-view=".$countView." data-id=".get_the_ID().">";
				if ( $showContent != "true" && $link != "false" ) $archiveMeta .= '<a href="'.$linkLoc.'" class="link-archive link-'.get_post_type().'"'.$titleADA.'>';	
				if ( $showTitle == "true" ) : $archiveMeta .= esc_html(get_the_title()); else: $archiveMeta .= $showTitle; endif;
				if ( $showContent != "true" && $link != "false" ) $archiveMeta .= '</a>';	
				$archiveMeta .= "</h3>";
			endif;		
			if ( $showDate == "true" || $showAuthor == "true" || $showSocial == "true" ) $archiveMeta .= '<div class="archive-meta">';			
				if ( function_exists( 'overrideArchiveMeta' ) ) : $archiveMeta .= overrideArchiveMeta( $type );
				else :			
					if ( $showDate == "true" ) $archiveMeta .= '<span class="archive-date '.$type.'-date date"><i class="fas fa-calendar-alt"></i>'.get_the_date().'</span>';
					if ( $showAuthor == "profile") $archiveMeta .= '<a href="/profile/?user='.get_the_author().'">';			
					if ( $showAuthor != "false") $archiveMeta .= '<span class="archive-author '.$type.'-author author"><i class="fas fa-user"></i>'.get_the_author().'</span>';
					if ( $showAuthor == "profile") $archiveMeta .= '</a>';
					if ( $showSocial == "true") $archiveMeta .= '<span class="archive-social '.$type.'-social social">'.do_shortcode('[add-share-buttons facebook="true" twitter="true"]').'</span>';
				endif;
			if ( $showDate == "true" || $showAuthor == "true" || $showSocial == "true" ) $archiveMeta .= '</div>';
			$archiveBody .= '[p]'.$content.'[/p]';
			if ( $type == "galleries" ) :
				if ( has_term( 'auto-generated', 'gallery-type' ) ) :
					$count = esc_attr(get_field("image_number")); 						
				elseif ( has_term( 'shortcode', 'gallery-type' ) ) :
					$count = esc_attr(get_field("image_number")); 						
				else:
					$all_attachments = get_posts( array( 'post_type'=>'attachment', 'post_mime_type'=>'image', 'post_parent'=>get_the_ID(), 'post_status'=>'published', 'numberposts'=>-1 ) );
					$count = count($all_attachments); 						
				endif;	
				if ( $count != "" ) :
					$subline = sprintf( _n( '%s Photo', '%s Photos', $count, 'battleplan' ), number_format($count) );
					$archiveBody .= '<div class="photo-count">';
					if ( $link != "false" ) $archiveBody .= '<a href="'.esc_url(get_the_permalink()).'" class="link-archive link-'.get_post_type().'" aria-hidden="true" tabindex="-1">';
					$archiveBody .= '<p class="gallery-subtitle">'.$subline.'</p>';
					if ( $link != "false" ) $archiveBody .= '</a>';
					$archiveBody .= '</div>';
				endif;
			endif;
		endif;
	}
	
	if ( $showBtn == "true" ) : 
		if ( $type == "testimonials" ) : $ada = " testimonials"; else: $ada = ' about '.esc_html(get_the_title()); endif;	
		$buildBtn = do_shortcode('[btn class="button-'.$type.'" link="'.$linkLoc.'" ada="'.$ada.'"]'.$btnText.'[/btn]'); 	
	endif;
			
	if ( $thumbOnly == "true" ) :
		$showArchive = $buildImg;
	else:
		$buildBody = "";
		if ( $titlePos == "inside" || $btnPos == "inside" || $type == "testimonials" ) : $groupSize = $textSize; $textSize = "100"; $buildBody .= "[group size='".$groupSize."' class='group-".$type."']"; endif;	
		if ( $type != "testimonials" ) $buildBody .= "[txt size='".$textSize."' class='text-".$type."']";
		if ( $titlePos == "inside" ) $buildBody .= $archiveMeta;	
		$buildBody .= do_shortcode($archiveBody);
		if ( $type != "testimonials" ) $buildBody .= "[/txt]";	
		if ( $btnPos == "inside" ) $buildBody .= $buildBtn;	
		if ( $titlePos == "inside" || $btnPos == "inside" || $type == "testimonials" ) $buildBody .= "[/group]";

		$showArchive = "";
		if ( $titlePos != "inside" ) $showArchive .= $archiveMeta;	
		$showArchive .= $buildImg.do_shortcode($buildBody);
		if ( $btnPos != "inside" ) $showArchive .= $buildBtn;
	endif;	
	
	return $showArchive;
}

// Display randomly selected posts - start/end can be dates or -53 week / -51 week */
add_shortcode( 'get-random-posts', 'battleplan_getRandomPosts' );
function battleplan_getRandomPosts($atts, $content = null) {	
	$a = shortcode_atts( array( 'num'=>'1', 'offset'=>'0', 'leeway'=>'0', 'type'=>'post', 'tax'=>'', 'terms'=>'', 'field_key'=>'', 'field_value'=>'', 'field_compare'=>'IN', 'orderby'=>'recent', 'sort'=>'asc', 'count_tease'=>'true', 'count_view'=>'false', 'show_title'=>'true', 'title_pos'=>'outside', 'show_date'=>'false', 'show_author'=>'false', 'show_excerpt'=>'true', 'show_social'=>'false', 'show_btn'=>'true', 'button'=>'Read More', 'btn_pos'=>'inside', 'show_content'=>'false', 'thumb_only'=>'false', 'thumb_col'=>'1', 'thumbnail'=>'force', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'size'=>'thumbnail', 'pic_size'=>'1/3', 'text_size'=>'', 'link'=>'post', 'truncate'=>'true' ), $atts );
	$num = esc_attr($a['num']);	
	$offset = esc_attr($a['offset']);
	if ( $offset == '0' ) $offset = rand(0, esc_attr($a['leeway']));	
	$postType = esc_attr($a['type']);	
	$truncate = esc_attr($a['truncate']);	
	$title = esc_attr($a['show_title']);	
	$orderBy = esc_attr($a['orderby']);	
	$sort = esc_attr($a['sort']);		
	$countTease = esc_attr($a['count_tease']);	
	$countView = esc_attr($a['count_view']);	
	$titlePos = esc_attr($a['title_pos']);	
	$showDate = esc_attr($a['show_date']);	
	$showExcerpt = esc_attr($a['show_excerpt']);		
	$showContent = esc_attr($a['show_content']);	
	$showAuthor = esc_attr($a['show_author']);	
	$showSocial = esc_attr($a['show_social']);	
	$showBtn = esc_attr($a['show_btn']);	
	$button = esc_attr($a['button']);	
	$btnPos = esc_attr($a['btn_pos']);	
	$content = esc_attr($a['show_content']);	
	$thumbnail = esc_attr($a['thumbnail']);	
	$start = esc_attr($a['start']);	
	$end = esc_attr($a['end']);	
	$taxonomy = esc_attr($a['tax']);
	$term = esc_attr($a['terms']);
	$terms = explode( ',', $term );
	$fieldKey = esc_attr($a['field_key']);
	$fieldValue = esc_attr($a['field_value']);
	$fieldValues = explode( ',', $fieldValue );
	$fieldCompare = esc_attr($a['field_compare']);
	$excludeRaw = esc_attr($a['exclude']);
	$exclude = explode(',', $excludeRaw); 
	$xCurrent = esc_attr($a['x_current']);
	if ( $xCurrent == "true" ) :
		global $post; $excludeThis = $post->ID;
		array_push($exclude, $excludeThis);
	endif;	
	$size = esc_attr($a['size']);		
	$picSize = esc_attr($a['pic_size']);	
	$textSize = esc_attr($a['text_size']);		
	$link = esc_attr($a['link']);
	$thumbOnly = esc_attr($a['thumb_only']);
	if ( $thumbOnly == "true" ) : 
		$title = $showDate = $showExcerpt = $showContent = $showAuthor = $showSocial = $showBtn = "false";
		$picSize = "100";
		$showThumb = "true";
	endif;
	$thumbCol = esc_attr($a['thumb_col']);

	$args = array ('posts_per_page'=>$num, 'offset'=>$offset, 'date_query'=>array( array( 'after'=>$start, 'before'=>$end, 'inclusive'=>true, ), ), 'order'=>$sort, 'post_type'=>$postType, 'post__not_in'=>$exclude);

	if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-180day' ) : $args['meta_key']="log-views-total-180day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-365day' ||  $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-tease-time"; $args['orderby']='meta_value_num';	
	else : $args['orderby']=$orderBy; endif;		

	if ( $taxonomy && $term ) : 
		$args['tax_query']=array( array('taxonomy'=>$taxonomy, 'field'=>'slug', 'terms'=>$terms ));
	endif;
	
	if ( $fieldKey && $fieldValue ) : 
		$args['meta_query']=array( array('key'=>$fieldKey, 'value'=>$fieldValues, 'compare'=>$fieldCompare ));
	endif;

	global $post; 
	$getPosts = new WP_Query( $args );
	if ( $getPosts->have_posts() ) : while ( $getPosts->have_posts() ) : $getPosts->the_post(); 	
		$showPost = do_shortcode('[build-archive type="'.$postType.'" count_tease="'.$countTease.'" count_view="'.$countView.'" thumb_only="'.$thumbOnly.'" show_btn="'.$showBtn.'" btn_text="'.$button.'" btn_pos="'.$btnPos.'" show_title="'.$title.'" title_pos="'.$titlePos.'" show_date="'.$showDate.'" show_excerpt="'.$showExcerpt.'" show_social="'.$showSocial.'" show_content="'.$showContent.'" show_author="'.$showAuthor.'" size="'.$size.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" link="'.$link.'" truncate="'.$truncate.'"]');	
	
		if ( $num > 1 ) $showPost = do_shortcode('[col]'.$showPost.'[/col]');	
		if ( has_post_thumbnail() || $thumbnail != "force" ) $combinePosts .= $showPost;
	endwhile; wp_reset_postdata(); endif;
	
	if ( $thumbOnly == "true" ) $combinePosts = '<div class="random-post random-posts thumb-only thumb-col-'.$thumbCol.'">'.$combinePosts.'</div>';
	
	return $combinePosts;
}

// Display posts & images in a Bootstrap slider 
add_shortcode( 'get-post-slider', 'battleplan_getPostSlider' );
function battleplan_getPostSlider($atts, $content = null ) {
	wp_enqueue_script( 'battleplan-carousel', get_template_directory_uri().'/js/bootstrap-carousel.js', array(), _BP_VERSION, false );		
	wp_enqueue_script( 'battleplan-carousel-slider', get_template_directory_uri().'/js/script-bootstrap-slider.js', array(), _BP_VERSION, false );	

	$a = shortcode_atts( array( 'type'=>'testimonials', 'auto'=>'yes', 'interval'=>'6000', 'loop'=>'true', 'num'=>'4', 'offset'=>'0', 'pics'=>'yes', 'caption'=>'no', 'controls'=>'yes', 'controls_pos'=>'below', 'indicators'=>'no', 'justify'=>'space-around', 'pause'=>'true', 'orderby'=>'recent', 'order'=>'asc', 'post_btn'=>'', 'all_btn'=>'View All', 'show_excerpt'=>'true', 'show_content'=>'false', 'link'=>'', 'pic_size'=>'1/3', 'text_size'=>'', 'slide_type'=>'fade', 'tax'=>'', 'terms'=>'', 'tag'=>'', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'size'=>'thumbnail', 'id'=>'', 'mult'=>'1', 'class'=>'', 'truncate'=>'true', 'lazy'=>'true' ), $atts );
	$num = esc_attr($a['num']);	
	$controls = esc_attr($a['controls']);	
	$controlsPos = esc_attr($a['controls_pos']);
	$indicators = esc_attr($a['indicators']);			
	$pause = esc_attr($a['pause']);		
	$autoplay = esc_attr($a['auto']);		
	$type = esc_attr($a['type']);		
	$truncate = esc_attr($a['truncate']);		
	$offset = esc_attr($a['offset']);
	$postBtn = esc_attr($a['post_btn']);	
	$allBtn = esc_attr($a['all_btn']);	
	$interval = esc_attr($a['interval']);		
	$loop = esc_attr($a['loop']);
	$orderBy = esc_attr($a['orderby']);	
	$order = esc_attr($a['order']);		
	$pics = esc_attr($a['pics']);		
	$caption = esc_attr($a['caption']);	
	$justify = esc_attr($a['justify']);	
	$showExcerpt = esc_attr($a['show_excerpt']);
	$showContent = esc_attr($a['show_content']);
	$picSize = esc_attr($a['pic_size']); 
	$textSize = esc_attr($a['text_size']);
	$slideType = esc_attr($a['slide_type']);		
	$tag = esc_attr($a['tag']);	
	$tags = explode( ',', $tag );
	$taxonomy = esc_attr($a['tax']);
	$term = esc_attr($a['terms']);
	$size = esc_attr($a['size']);
	$start = esc_attr($a['start']);	
	$end = esc_attr($a['end']);	
	$excludeRaw = esc_attr($a['exclude']);
	$exclude = explode (",", $excludeRaw); 
	$xCurrent = esc_attr($a['x_current']);
	if ( $xCurrent == "true" ) :
		global $post; $excludeThis = $post->ID;
		array_push($exclude, $excludeThis);
	endif;	
	$link = esc_attr($a['link']);		
	$id = esc_attr($a['id']);	
	$class = esc_attr($a['class']);	
	$lazy = esc_attr($a['lazy']);	
	if ( $lazy == "true" ) : $lazy = "lazy"; else: $lazy = "eager"; endif;
	$mult = esc_attr($a['mult']);		
	if ( $mult == 1 ) $imgSize = 100; 
	if ( $mult == 2 ) $imgSize = 50;	
	if ( $mult == 3 ) $imgSize = 33;
	if ( $mult == 4 ) $imgSize = 25;
	if ( $mult == 5 ) $imgSize = 20;	
	if ( $mult == 6 ) $imgSize = 17;
	$numDisplay = -1;
	$rowDisplay = 0;
	$sliderNum = rand(100,999);

	if ( $controls == "yes" && $btnText == "no" && $indicators == "no" ) $controlClass = " only-controls";	
	if ( $postBtn == "" ) : $showBtn = "false"; else: $showBtn = "true"; endif;
	if ( $pause == "true" ) : $pause = "hover"; else: $pause = "false"; endif;
	if ( $link == "" ) : $linkTo = "/".$type."/"; elseif ( $link == "none" || $link == "false" || $link == "no" ) : $link = "none"; endif;		
	
	if ( $type == "image" || $type == "images" ) :
		$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$num, 'order'=>$order);

		if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day"; $args['orderby']='meta_value_num';			
		elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; $args['orderby']='meta_value_num';			
		elseif ( $orderBy == 'views-180day' ) : $args['meta_key']="log-views-total-180day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-tease-time"; $args['orderby']='meta_value_num';	
		else : $args['orderby']=$orderBy; endif;		

		if ( $id == '' ) : 
			$args['tax_query']=array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags ));
		elseif ( $tag != '' ) :
			$args['post_parent']=$id;
			$args['tax_query']=array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags ));
		else :
			$args['post_parent']=$id;
		endif;

		$image_query = new WP_Query($args);		
		if( $image_query->have_posts() ) :

			$buildIndicators = '<ol class="carousel-indicators" style="justify-content: '.$justify.'">';
			$buildInner = '<div class="carousel-inner">';

			while ($image_query->have_posts() ) : $image_query->the_post();
				$numDisplay++; 	
				if ( $rowDisplay == 0 ) :
					if ( $numDisplay == 0 ) : 
						$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'" class="active"></li>';
						$buildInner .= '<div class="carousel-item active">';
					else : 
						$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'"></li>'; 
						$buildInner .= '<!--div class="clearfix"></div--></div><div class="carousel-item">';
					endif;	
				endif;	

				$image = wp_get_attachment_image_src(get_the_ID(), $size );
				$imgSet = wp_get_attachment_image_srcset(get_the_ID(), $size );		
		
				if ( $link == "alt" ) $linkTo = readMeta(get_the_ID(), '_wp_attachment_image_alt', true);				
				if ( $link == "description" ) $linkTo = esc_html(get_post(get_the_ID())->post_content);
				$buildImg = "";
				if ( $link != "none" ) : $buildImg = "<a href='".$linkTo."' class='link-archive link-".$type."'>"; endif;	
				//$buildImg .= "<img data-id='".get_the_ID()."' ".getImgMeta(get_the_ID())." data-count-tease='true' data-count-view='true' class='img-slider ".$tags[0]."-img' loading='lazy' src = '".$image[0]."' width='".$image[1]."' height='".$image[2]."' style='aspect-ratio:".$image[1]."/".$image[2]."' alt='".readMeta(get_the_ID(), '_wp_attachment_image_alt', true)."'>";

				$buildImg .= '<img data-id="'.get_the_ID().'" '.getImgMeta(get_the_ID()).' data-count-tease="true" data-count-view="true" class="img-slider '.$tags[0].'-img" loading="'.$lazy.'" src = "'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta(get_the_ID(), "_wp_attachment_image_alt", true).'">';
				
				/* Added srcset BACK into the formula for https://okairpro.com/ slider at top on 9/13/21 */
				 
		
				if ( $caption == "yes" || $caption == "title" ) : $buildImg .= "<div class='caption-holder'><div class='img-caption'>".get_the_title(get_the_ID())."</div></div>";	
				elseif ( $caption == "alt" ) : $buildImg .= "<div class='caption-holder'><div class='img-caption'>".readMeta(get_the_ID(), '_wp_attachment_image_alt', true)."</div></div>";
				endif;
	
				if ( $link != "none" ) : $buildImg .= "</a>"; endif;	
		
	 			$buildInner .= do_shortcode("[img size='".$imgSize."' class='image-".$type."']".$buildImg."[/img]");
	
				$rowDisplay++;
				if ( $rowDisplay == $mult ) $rowDisplay = 0;	
			endwhile;
		$buildInner .= "<!--div class='clearfix'></div--></div>";
		wp_reset_postdata();
		endif;
	else :
		$args = array ('posts_per_page'=>-1, 'offset'=>$offset, 'date_query'=>array( array( 'after'=>$start, 'before'=>$end, 'inclusive'=>true, ), ), 'order'=>$order, 'post_type'=>$type, 'post__not_in'=>$exclude);

		if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-180day' ) : $args['meta_key']="log-views-total-180day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-tease-time"; $args['orderby']='meta_value_num';	
		else : $args['orderby']=$orderBy; endif;		

		if ( $taxonomy && $term ) : 
			$args['tax_query']=array( array('taxonomy'=>$taxonomy, 'field'=>'slug', 'terms'=>$terms ));
		endif;
	
		global $post; 
		$fetchPost = new WP_Query( $args );
		if ( $fetchPost->have_posts() ) :	

			$buildIndicators = '<ol class="carousel-indicators">';
			$buildInner = '<div class="carousel-inner">';

			while ( $fetchPost->have_posts() ) : 
				$fetchPost->the_post();
	
				if ( $numDisplay < $num ) : 
					if ( $pics == "no" || has_post_thumbnail() ) :
						$numDisplay++; 
						if ( $numDisplay == 0 ) : 
							$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'" class="active"></li>';
							$buildInner .= '<div class="active carousel-item carousel-item-'.$type.'" data-id="'.get_the_ID().'">';
						else : 
							$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'"></li>'; 
							$buildInner .= '<div class="carousel-item carousel-item-'.$type.'" data-id="'.get_the_ID().'">';
						endif;	

						$buildInner .= do_shortcode('[build-archive type="'.$type.'" show_btn="'.$showBtn.'" btn_text="'.$postBtn.'" show_excerpt="'.$showExcerpt.'" show_content="'.$showContent.'" show_date="'.$showDate.'" show_author="'.$showAuthor.'" size="'.$size.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" link="'.$link.'" truncate="'.$truncate.'"]');	

						$buildInner .= "</div>";	
					endif;
				endif;
			endwhile; 
		wp_reset_postdata(); 
		endif;		
	endif;

	$buildIndicators .= '</ol>';
	$buildInner .= '</div>';

	$controlsPrevBtn = '<div class="block block-button"><a class="button carousel-control-prev'.$controlClass.'" href="#'.$type.'Slider'.$sliderNum.'" data-slide="prev"><span class="carousel-control-prev-icon" aria-label="Previous Slide"><span class="sr-only">Previous Slide</span></span></a></div>';
	$controlsNextBtn .= '<div class="block block-button"><a class="button carousel-control-next'.$controlClass.'" href="#'.$type.'Slider'.$sliderNum.'" data-slide="next"><span class="carousel-control-next-icon" aria-label="Next Slide"><span class="sr-only">Next Slide</span></span></a></div>';
	$viewMoreBtn = do_shortcode('[btn link="'.$linkTo.'"]'.$allBtn.'[/btn]');	

	$buildControls = "<div class='controls controls-".$controlsPos."'>";	
	$buildControls .= $controlsPrevBtn;
	if ( $allBtn != "" ) $buildControls .= $viewMoreBtn;
	$buildControls .= $controlsNextBtn;	
	$buildControls .= "</div>";	

	if ( $slideType == "box" ) : $style = "style='margin-left:auto; margin-right:auto;'"; $slideClass="box-slider"; elseif ( $slideType == "screen" ) : $style = "style='width: calc(100vw - 17px); left: 50%; transform: translateX(calc(-50vw + 8px));'"; $slideClass="screen-slider"; elseif ( $slideType == "fade" ) : $slideClass="carousel-fade"; else: $slideClass="carousel-fade"; endif;	
	
	$buildSlider = '<div id="'.$type.'Slider'.$sliderNum.'" class="carousel slide slider slider-'.$type.' '.$slideClass.' '.$class.' mult-'.$mult.'" '.$style.' data-interval="'.$interval.'" data-pause="'.$pause.'" data-wrap="'.$loop.'" data-touch="true"';	
	if ( $autoplay == "yes" ) $buildSlider .= ' data-ride="carousel"';
	$buildSlider .= '>';	
	
	if ( $controlsPos == "above" || $controlsPos == "before" ) :
		if ( $controls == "yes" ) : $buildSlider .= $buildControls; else: if ( $allBtn != "" ) : $buildSlider .= $viewMoreBtn; endif; endif;
	endif;

	$buildSlider .= $buildInner;

	if ( $indicators == "yes" ) $buildSlider .= $buildIndicators;	

	if ( $controlsPos != "above" && $controlsPos != "before" ) :
		if ( $controls == "yes" ) : $buildSlider .= $buildControls; else: if ( $allBtn != "" ) : $buildSlider .= $viewMoreBtn; endif; endif;
	endif;
	
	$buildSlider .= '</div>';	

	return $buildSlider;
}

// Display row of logos that slide from left to right 
add_shortcode( 'get-logo-slider', 'battleplan_getLogoSlider' );
function battleplan_getLogoSlider($atts, $content = null ) {
	wp_enqueue_script( 'battleplan-logo-slider', get_template_directory_uri().'/js/script-logo-slider.js', array(), _BP_VERSION, false );	

	$a = shortcode_atts( array( 'num'=>'-1', 'space'=>'10', 'size'=>'full', 'max_w'=>'85', 'tag'=>'', 'package'=>'', 'order_by'=>'rand', 'order'=>'ASC', 'shuffle'=>'false', 'speed'=>'slow', 'delay'=>'0', 'pause'=>'no', 'link'=>'false', 'lazy'=>'true'), $atts );
	$num = esc_attr($a['num']);			
	$space = esc_attr($a['space']);			
	$tag = esc_attr($a['tag']);	
	$tags = explode( ',', $tag );
	$orderBy = esc_attr($a['order_by']);		
	$order = esc_attr($a['order']);		
	$shuffle = esc_attr($a['shuffle']);		
	$speed = esc_attr($a['speed']);		
	$delay = esc_attr($a['delay']);			
	$pause = esc_attr($a['pause']);			
	$link = esc_attr($a['link']);		
	$size = esc_attr($a['size']);			
	$maxW = esc_attr($a['max_w']);		
	$package = esc_attr($a['package']);	
	$lazy = esc_attr($a['lazy']);	
	if ( $lazy == "true" ) : $lazy = "lazy"; else: $lazy = "eager"; endif;
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$num, 'order'=>$order, 'tax_query'=>array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags )));

	if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-180day' ) : $args['meta_key']="log-views-total-180day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-tease-time"; $args['orderby']='meta_value_num';	
	else : $args['orderby']=$orderBy; endif;		
	
	if ( $id != '' ) : 
		$args['post_parent']=$id;
	endif;

	$image_query = new WP_Query($args);		
	$imageArray = array();
	
	if ( $image_query->have_posts() ) : while ($image_query->have_posts() ) : $image_query->the_post();
		$totalNum = $image_query->post_count;
		$image = wp_get_attachment_image_src( get_the_ID(), $size );
		$getImage = "";
		if ( $link != "false" ) $getImage .= '<a href="'.$image[0].'">';
		$getImage .= '<img data-id="'.get_the_ID().'"'.getImgMeta(get_the_ID()).' data-count-tease="true" data-count-view="true" class="logo-img '.$tags[0].'-img" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" alt="'.readMeta(get_the_ID(), '_wp_attachment_image_alt', true).'">';
		if ( $link != "false" ) $getImage .= '</a>';
		$imageArray[] = '<span>'.$getImage.'</span>';			
	endwhile; wp_reset_postdata(); endif;	
	
	if ( $package == "hvac" ) :
		$addLogos = array( "amana","american-standard","bryant","carrier","goodman","heil","lennox","rheem","ruud","samsung","trane","york" );		
		for ( $i=0; $i < count($addLogos); $i++ ) :	
			$alt = strtolower(str_replace(" ", "-", $addLogos[$i]));
			$alt = "We service ".ucwords($alt)." air conditioners, heaters and other HVAC equipment.";
			$imageURL = "../wp-content/themes/battleplantheme/common/hvac-".$addLogos[$i]."/".$addLogos[$i]."-sidebar-logo.png";
			$imagePath = get_template_directory()."/common/hvac-".$addLogos[$i]."/".$addLogos[$i]."-sidebar-logo.png";			
			list($width, $height) = getimagesize($imagePath);
			
			$getImage = "";
			$getImage .= '<img class="logo-img '.$package.'-logo-img" loading="'.$lazy.'" src="'.$imageURL.'" width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" alt="'.$alt.'">';
			$imageArray[] = '<span>'.$getImage.'</span>';		
		endfor;
	endif;
	
	if ( $shuffle != "false" ) : shuffle($imageArray); endif;
	$buildSlider = '<div class="logo-slider" data-speed="'.$speed.'" data-delay="'.$delay.'" data-pause="'.$pause.'" data-maxw="'.$maxW.'" data-spacing="'.$space.'"><div class="logo-row">'.printArray($imageArray).'</div></div>';
	return $buildSlider;
}

// Generate an array of IDs for images, filtered by image-tags
add_shortcode( 'load-images', 'battleplan_loadImagesByTag' );
function battleplan_loadImagesByTag( $atts, $content = null ) {
	$a = shortcode_atts( array( 'max'=>'-1', 'tags'=>'', 'field'=>'', 'order_by'=>'meta_value_num', 'order'=>'ASC', 'value'=>'', 'type'=>'', 'compare'=>'', ), $atts );
	$max = esc_attr($a['max']);	
	$tags = esc_attr($a['tags']);	
	$field = esc_attr($a['field']);
	$orderBy = esc_attr($a['order_by']);	
	$order = esc_attr($a['order']);
	$value = esc_attr($a['value']);
	$type = esc_attr($a['type']);
	$compare = esc_attr($a['compare']);
	if ( $compare == "greater equal" || $compare == "more equal" ) $compare=">=";
	if ( $compare == "greater" || $compare == "more" ) $compare=">";
	if ( $compare == "less equal" ) $compare="<=";
	if ( $compare == "less" ) $compare="<";
	if ( $compare == "equal" || $compare == "" ) $compare="=";
	if ( $compare == "not equal" ) $compare="!=";
	if ( $field != "" ) :
		$image_attachments = new WP_Query( array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$max, 'meta_query'=>array(array( 'key'=>$field, 'value'=>$value, 'type'=>$type, 'compare'=>$compare )), 'orderby'=>$orderBy, 'order'=>$order,  ));
	elseif ( $tags == "" ) :
		$tags = get_the_slug();
		$image_attachments = new WP_Query( array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$max, 'tax_query'=>array(array( 'taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags, )), 'orderby'=>$orderBy, 'order'=>$order,  ));
	else:
		$tags = explode(',', $tags);
		$image_attachments = new WP_Query( array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$max, 'tax_query'=>array(array( 'taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>array_values($tags))), 'orderby'=>$orderBy, 'order'=>$order,  ));					
	endif;
	
	$imageIDs = array();
	if ( $image_attachments->have_posts() ) : while ( $image_attachments->have_posts() ) : $image_attachments->the_post(); $imageIDs[] = get_the_ID(); endwhile; endif;	wp_reset_postdata();
	update_field('image_number', count($imageIDs));
	return serialize($imageIDs);
}

// Genearate a WordPress gallery and filter
add_shortcode( 'get-gallery', 'battleplan_setUpWPGallery' );
function battleplan_setUpWPGallery( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'size'=>'thumbnail', 'id'=>'', 'columns'=>'5', 'max'=>'-1', 'caption'=>'false', 'start'=>'', 'end'=>'', 'order_by'=>'menu_order', 'order'=>'ASC', 'tags'=>'', 'field'=>'', 'class'=>'', 'include'=>'', 'exclude'=>'', 'value'=>'', 'type'=>'', 'compare'=>'' ), $atts );
	$id = esc_attr($a['id']);	
	if ( $id == '' ) global $post; $id = intval( $post->ID );  
	$name = esc_attr($a['name']);
	if ( $name == '' ) $name = $id;
	$size = esc_attr($a['size']);
	$columns = esc_attr($a['columns']);
	$order = esc_attr($a['order']);
	$orderBy = esc_attr($a['order_by']);
	$max = esc_attr($a['max']);
	$caption = esc_attr($a['caption']);	
	$start = esc_attr($a['start']);	
	$end = esc_attr($a['end']);
	$exclude = esc_attr($a['exclude']);
	$include = esc_attr($a['include']);	
	$value = esc_attr($a['value']);
	$type = esc_attr($a['type']); 
	$compare = esc_attr($a['compare']);
	$field = esc_attr($a['field']);	
	$tags = esc_attr($a['tags']);
	$imageIDs = do_shortcode('[load-images tags="'.$tags.'" field="'.$field.'" order_by="'.$orderBy.'" order="'.$order.'" value="'.$value.'" type="'.$type.'" compare="'.$compare.'"]');
	$imageIDs = unserialize($imageIDs);
	$class = esc_attr($a['class']);
	if ( $class != "" ) $class = " ".$class;
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$max, 'order'=>$order, 'date_query'=>array( array( 'after'=>$start, 'before'=>$end, 'inclusive'=>'true' )));	
	
	if ( $exclude ) : 
		$exclude = explode(',', $exclude); 
		foreach ($exclude as $exclusion) :
			if (($key = array_search($exclusion, $imageIDs)) !== false) unset($imageIDs[$key]);
		endforeach;
	endif;
	if ( $include ) : 
		$include = explode(',', $include); 
		foreach ($include as $inclusion) :
			array_push($imageIDs, $inclusion);
		endforeach;
	endif;
	if ( $imageIDs ) : $args['post__in']=$imageIDs; $args['orderby']="post__in"; endif;
	if ( !$imageIDs && !$include ) : $args['post_parent']=$id; $args['orderby']=$orderBy; endif;
	
	$gallery = '<div id="gallery-'.$name.'" class="gallery gallery-'.$id.' gallery-column-'.$columns.' gallery-size-'.$size.'">';

	$image_attachments = new WP_Query($args);
	
	if ( $image_attachments->have_posts() ) : while ( $image_attachments->have_posts() ) : $image_attachments->the_post();
		$getID = get_the_ID();
		$full = wp_get_attachment_image_src($getID, 'full');
		$image = wp_get_attachment_image_src($getID, $size);
		$imgSet = wp_get_attachment_image_srcset($getID, $size );
		$count++;

		if ( $caption != "false" ) : $captionPrint = '<figcaption><div class="image-caption image-title">'.$post->post_title.'</div></figcaption>'; endif;
		$gallery .= '<dl class="col col-archive col-gallery id-'.$getID.'"><dt class="col-inner"><a class="link-archive link-gallery ari-fancybox" href="'.$full[0].'"><img class="img-gallery wp-image-'.get_the_ID().'" data-id="'.get_the_ID().'"'.getImgMeta($getID).' loading="lazy" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta(get_the_ID(), '_wp_attachment_image_alt', true).'"></a>'.$captionPrint.'</dt></dl>';
	endwhile; endif;	
	wp_reset_postdata();
	$gallery .= "</div>";	
	update_field('image_number', $count);
	return $gallery;
}

// Build a coupon
add_shortcode( 'coupon', 'battleplan_coupon' );
function battleplan_coupon( $atts, $content = null ) {
	$a = shortcode_atts( array( 'action'=>'Mention Our Website For', 'discount'=>'$20 OFF', 'service'=>'Service Call', 'disclaimer'=>'First time customers only.  Limited time offer.  Not valid with any other offer.  Must mention coupon at time of appointment.  During regular business hours only.  Limit one coupon per system.' ), $atts );
	$action = esc_attr($a['action']);
	$discount = esc_attr($a['discount']);
	$service = esc_attr($a['service']);
	$disclaimer = esc_attr($a['disclaimer']);
	
	return do_shortcode('
		[txt class="coupon"]
			<div class="coupon-inner">
				<h2 class="action">'.$action.'</h2>
				<h2 class="discount">'.$discount.'</h2>
				<h2 class="service">'.$service.'</h2>
				<p class="disclaimer">'.$disclaimer.'</p>
			</div>
		[/txt]
	');
}

// Add Emergency Service widget to Sidebar
add_shortcode( 'get-emergency-service', 'battleplan_getEmergencyService' );
function battleplan_getEmergencyService( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'graphic'=>'1' ), $atts );
	$graphic = esc_attr($a['graphic']);
	if ( $graphic == 1 ) : $height = 177;
	elseif ( $graphic == 2 || $graphic == 3 ) : $height = 237;
	elseif ( $graphic == 4 ) : $height = 418;
	else : $height = 320; endif;
	return '<img class="noFX" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/24-hr-service-'.$graphic.'.png" alt="We provide 24/7 emergency service" width="320" height="'.$height.'" />';
}

// Add BBB widget to Sidebar
add_shortcode( 'get-bbb', 'battleplan_getBBB' );
function battleplan_getBBB( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'link'=>'', 'graphic'=>'1' ), $atts );
	$link = esc_attr($a['link']);
	$graphic = esc_attr($a['graphic']);
	if ( $graphic == 1 ) : $height = 221;
	else : $height = 94; endif;
	return '<a href="'.$link.'" title="Click here to view our profile page on the Better Business Bureau website."><img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/bbb-'.$graphic.'.png" alt="We are accredited with the BBB and are proud of our A+ rating"  width="320" height="'.$height.'" style="aspect-ratio:320/'.$height.'" /></a>';
}

// Add Veteran Owned widget to Sidebar
add_shortcode( 'get-veteran-owned', 'battleplan_getVeteranOwned' );
function battleplan_getVeteranOwned( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'link'=>'', 'graphic'=>'1' ), $atts );
	$link = esc_attr($a['link']);
	$graphic = esc_attr($a['graphic']);
	if ( $graphic == 2 ) : $width = 216; $height = 300;	
	elseif ( $graphic == 3 ) : $width = 320; $height = 128;
	elseif ( $graphic == 4 ) : $width = 175; $height = 200;
	else : $width = 320; $height = 80; endif;
	return '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/veteran-owned-'.$graphic.'.png" alt="We are proud to be a Veteran Owned business."  width="'.$width.'" height="'.$height.'" style="aspect-ratio:"'.$width.'"/'.$height.'" />';
}

// Add Credit Cards widget to Sidebar
add_shortcode( 'get-credit-cards', 'battleplan_getCreditCards' );
function battleplan_getCreditCards( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'mc'=>'yes', 'visa'=>'yes', 'discover'=>'yes', 'amex'=>'yes' ), $atts );
	$mc = esc_attr($a['mc']);
	$visa = esc_attr($a['visa']);
	$discover = esc_attr($a['discover']);
	$amex = esc_attr($a['amex']);

	$buildCards = '<div id="credit-cards" class="currency">';
	if ( $mc == "yes" ) $buildCards .= '<img src="/wp-content/themes/battleplantheme/common/logos/cc-mc.png" loading="lazy" alt="We accept Mastercard" width="100" height="62" style="aspect-ratio:100/62" />';
	if ( $visa == "yes" ) $buildCards .= '<img src="/wp-content/themes/battleplantheme/common/logos/cc-visa.png" loading="lazy" alt="We accept Visa width="100" height="62" style="aspect-ratio:100/62" />';
	if ( $discover == "yes" ) $buildCards .= '<img src="/wp-content/themes/battleplantheme/common/logos/cc-discover.png" loading="lazy" alt="We accept Discover width="100" height="62" style="aspect-ratio:100/62" />';
	if ( $amex == "yes" ) $buildCards .= '<img src="/wp-content/themes/battleplantheme/common/logos/cc-amex.png" loading="lazy" alt="We accept American Express width="100" height="62" style="aspect-ratio:100x62" />';
	$buildCards .= '</div>';  					  
													  
	return $buildCards;
}

// Add Crypto Currency widget to Sidebar
add_shortcode( 'get-crypto', 'battleplan_getCrypto' );
function battleplan_getCrypto( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'bitcoin'=>'yes', 'cardano'=>'yes', 'chainlink'=>'yes', 'dogecoin'=>'yes', 'monero'=>'yes', 'polygon'=>'yes', 'stellar'=>'yes' ), $atts );
	$bitcoin = esc_attr($a['bitcoin']);
	$cardano = esc_attr($a['cardano']);
	$chainlink = esc_attr($a['chainlink']);
	$dogecoin = esc_attr($a['dogecoin']);
	$monero = esc_attr($a['monero']);
	$polygon = esc_attr($a['polygon']);
	$stellar = esc_attr($a['stellar']);

	$buildCrypto = '<div id="crypto" class="currency">';
	if ( $bitcoin == "yes" ) $buildCrypto .= '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-bitcoin.png" alt="We accept Bitcoin crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $cardano == "yes" ) $buildCrypto .= '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-cardano.png" alt="We accept Cardano crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $chainlink == "yes" ) $buildCrypto .= '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-chainlink.png" alt="We accept Chainlink crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $dogecoin == "yes" ) $buildCrypto .= '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-dogecoin.png" alt="We accept Dogecoin crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $monero == "yes" ) $buildCrypto .= '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-monero.png" alt="We accept Monero crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $polygon == "yes" ) $buildCrypto .= '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-polygon.png" alt="We accept Polygon crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $stellar == "yes" ) $buildCrypto .= '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-stellar.png" alt="We accept Stellar crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	$buildCrypto .= '</div>';  					  
										 			  
	return $buildCrypto;
}

// Create filter button for querying posts base on custom fields
add_shortcode( 'get-filter-btn', 'battleplan_getFilterButton' );
function battleplan_getFilterButton( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'btn_reveal'=>'false', 'field'=>'', 'btn_search'=>'Search', 'ul'=>'' ), $atts );
	$btnReveal = esc_attr($a['btn_reveal']);
	$field_key = esc_attr($a['field']);
	$field = get_field_object($field_key);
	$ul = esc_attr($a['ul']);
	$btnSearch = esc_attr($a['btn_search']);
	
	if ( $field ) :	
		$buildFilter .= '<form name="filter-form" action=/results method="get">';
		$buildFilter .= '<ul class="'.$ul.'">';
			foreach( $field['choices'] as $k => $v ) :
				$buildFilter .= '<li class="filter-choice"><input type="checkbox" name="choice" value="'.$k.'"><div class="checkbox-label">'.$v.'</div></li>';
			endforeach;
		$buildFilter .= "</ul>";

		$buildFilter .= '<div class="block block-button"><input type="button" class="filter-btn" data-url="'.$field_key.'" value="'.$btnSearch.'"></div>';
		$buildFilter .= '</form><div class="clearfix"></div>';	
	endif;												  
	
	if ( $btnReveal != "false" ) $buildFilter = do_shortcode('[accordion title="'.$btnReveal.'" btn="true" icon="false"]'.$buildFilter.'[/accordion]');
	
	return $buildFilter;
} 

// Side by side images
add_shortcode( 'side-by-side', 'battleplan_SideBySideImg' );
function battleplan_SideBySideImg( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'img'=>'', 'size'=>'half-s', 'align'=>'center', 'full'=>'', 'pos'=>'bottom' ), $atts );	
	$size = esc_attr($a['size']);
	$full = esc_attr($a['full']);	
	$pos = esc_attr($a['pos']);
	$align = "align".esc_attr($a['align']);
	$images = explode(',', esc_attr($a['img']));
	$num = count($images);
	
	$buildFlex = '<ul class="side-by-side '.$align.'">';
	for ($i=0; $i<$num; $i++) :
		$img = wp_get_attachment_image_src( $images[$i], $size );
		list ($src, $width, $height ) = $img;
		if ( $images[$i] == $full ) : $class=' class="full-'.$pos.'" '; else: $class=''; endif;
		$ratio = $width/$height;	
		$buildFlex .= '<li style="flex: '.$ratio.'"'.$class.'>'.wp_get_attachment_image( $images[$i], $size ).'</li>';	
	endfor;
	$buildFlex .= '</ul>';
	
	return $buildFlex;
} 

// Make the nonce generated in header.php available to WP pages
add_shortcode( 'get-nonce', 'battleplan_get_nonce' );
function battleplan_get_nonce() {	
	$nonce = $GLOBALS['nonce'];
	return 'nonce="'.$nonce.'"';
}

// Display a universal page
add_shortcode( 'get-universal-page', 'battleplan_getUniversalPage' );
function battleplan_getUniversalPage( $atts, $content = null ) {
	$a = shortcode_atts( array( 'slug'=>'' ), $atts );
	$slug = esc_attr($a['slug']);
	return do_shortcode(include get_template_directory().'/pages/'.$slug.'.php');	
}

// Use page template for optimized & universal pages
add_filter('single_template', 'battleplan_usePageTemplate', 10, 1 );
function battleplan_usePageTemplate( $original ) {
	global $post;
	$post_type = $post->post_type;
	if ( $post_type == "optimized" || $post_type == "universal" ) return locate_template('page.php');
	return $original;
}
  
/*--------------------------------------------------------------
# Functions to extend WordPress 
--------------------------------------------------------------*/

// Check if current page is log in screen 
function is_wplogin() {
    $ABSPATH_MY = str_replace(array('\\','/'), DIRECTORY_SEPARATOR, ABSPATH);
    return ((in_array($ABSPATH_MY.'wp-login.php', get_included_files()) || in_array($ABSPATH_MY.'wp-register.php', get_included_files()) ) || (isset($_GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') || $_SERVER['PHP_SELF']== '/wp-login.php');
}

// Check if user is on a mobile device
function is_mobile() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

// Get slug of current page
function the_slug() {
	$slug = basename(get_permalink());
	do_action('before_slug', $slug);
	$slug = apply_filters('slug_filter', $slug);
	do_action('after_slug', $slug);
	echo $slug;
}

function get_the_slug() {
	$slug = basename(get_permalink());
	do_action('before_slug', $slug);
	$slug = apply_filters('slug_filter', $slug);
	do_action('after_slug', $slug);
	return $slug;
}

// Get ID from page, post or custom post type by entering slug or title
function getID($slug) { 
	$getCPT = get_post_types(); 
	$id = false;
	foreach ($getCPT as $postType) :
		if ( get_page_by_path($slug, OBJECT, $postType) ) : $id = get_page_by_path($slug, OBJECT, $postType)->ID; break; endif;
	endforeach;	
	if ( $id == false ) :
		foreach ($getCPT as $postType) :
			if ( get_page_by_title($slug, OBJECT, $postType) ) : $id = get_page_by_title($slug, OBJECT, $postType)->ID; break; endif;
		endforeach;	
	endif;
	return $id;
} 

// Get the Role of Current Logged in User ---- deprecated
function getUserRole($display="slug") {
	if ( is_user_logged_in() ) : 
		$user = wp_get_current_user();
	 	$roles = ( array ) $user->roles;	
		if ( $display == "name" ) : 
			global $wp_roles;
			return $wp_roles->roles[$roles[0]]['name'];
		else: return $roles[0];	 
		endif;
	 else: return "not_logged_in"; 	 
	 endif;
}

// Identify user based on id, email or slug
function battleplan_identifyUser( $identifier ) {
	if ( $identifier == null || $identifier == "" ) : return wp_get_current_user(); 
	elseif ( is_numeric($identifier) ) : return get_user_by('id', $identifier);
	elseif ( strpos($identifier, '@') !== false ) : return get_user_by('email', $identifier);
	else: return get_user_by('slug', $identifier);
	endif;
}

function battleplan_getUserRole( $identifier, $info ) {
	$user = battleplan_identifyUser( $identifier );		
	$userMeta = get_userdata($user->ID);
	$userRoles = $userMeta->roles;
	global $wp_roles;
	$userRoleName = "";
	$userRoleDisplay = "";	
	foreach ($userRoles as $userRole) :	
		$userRoleName .= $userRole;
		$userRoleDisplay .= $wp_roles->roles[$userRole]['name'];
		$userRoleCaps .= $user->get_role_caps();		
	endforeach;
	
	if ( $info == "" || $info == "name" ) return $userRoleName;
	if ( $info == "display" ) return $userRoleDisplay;
	if ( $info == "caps" || $info == "capabilities" ) return $userRoleCaps;
}

// Add data-{key}="{value}" to an image based on its custom fields 
function getImgMeta($id) {	
	$custom = get_post_custom( $id );
	if ( ! is_array( $custom ) ) {
		return;
	}
	if ( $keys = array_keys( $custom ) ) {		
		$addMeta = "";
		foreach ($keys as $key) :
			$value = esc_attr(get_field( $key, $id));			
			if ( substr($value, 0, 5) != "field" && !is_array($value) && $value != "" && $value != null && $value != "Array" ) :				
				$key = ltrim($key, '_');
				$key = ltrim($key, '-');
				$addMeta .= ' data-'.$key.' = "'.$value.'"';	
			endif;
		endforeach; 		
		return $addMeta;
	}
}

// Read meta in custom field
function readMeta($id, $key, $single=true) {
	return get_post_meta( $id, $key, $single );
}

// Update meta in custom field
function updateMeta($id, $key, $value) {
	if ( !add_post_meta( $id, $key, $value, true ) ) { 
		update_post_meta( $id, $key, $value );
	}
}

// Delete custom field
function deleteMeta($id, $key) {
	delete_post_meta( $id, $key );
}

// Set Cookies in same method as javascript
function writeCookie($cname, $cvalue, $exdays) {
	//$parts = explode('.', parse_url(esc_url(get_site_url()), PHP_URL_HOST));
	//$domain = $parts[0].'.'.$parts[1];	
	//echo "domain: ".$domain;
	if ( $exdays == '' || $exdays == null || $exdays == '0' || $exdays == 0 ) :
		$expires = 0;
	else:
		$expires = time() + (intval($exdays) * 24 * 60 * 60);
	endif;
	
	setcookie($cname, $cvalue, $expires, '/', '', true, false);
}

// Convert time into seconds
function convertTime($howMany, $howMuch) {
	if ( $howMuch == "seconds" || $howMuch == "second" ) $seconds = $howMany * 1;
	if ( $howMuch == "minutes" || $howMuch == "minute" ) $seconds = $howMany * 60;
	if ( $howMuch == "hours" || $howMuch == "hour" ) $seconds = $howMany * 3600;
	if ( $howMuch == "days" || $howMuch == "day" ) $seconds = $howMany * 86400;
	if ( $howMuch == "weeks" || $howMuch == "week" ) $seconds = $howMany * 604800;
	if ( $howMuch == "months" || $howMuch == "month" ) $seconds = $howMany * 2618784;	
	if ( $howMuch == "years" || $howMuch == "year" ) $seconds = $howMany * 31449600;
	return $seconds;
}

// Convert Sizes
function convertSize($size) {
	if ( $size == "100" || $size == "12" || $size == "12/12" || $size == "1/1" ) return 12;	
	if ( $size == "92" || $size == "11" || $size == "11/12" ) return 11;
	if ( $size == "83" || $size == "10" || $size == "10/12" || $size == "5/6" ) return 10;
	if ( $size == "75" || $size == "9" || $size == "9/12" || $size == "3/4" ) return 9;
	if ( $size == "67" || $size == "8" || $size == "8/12" || $size == "2/3" ) return 8;
	if ( $size == "58" || $size == "7" || $size == "7/12" ) return 7;
	if ( $size == "50" || $size == "6" || $size == "6/12" || $size == "1/2" ) return 6;
	if ( $size == "42" || $size == "5" || $size == "5/12" ) return 5;
	if ( $size == "33" || $size == "4" || $size == "4/12" || $size == "1/3" ) return 4;
	if ( $size == "25" || $size == "3" || $size == "3/12" || $size == "1/4" ) return 3;
	if ( $size == "17" || $size == "2" || $size == "2/12" || $size == "1/6" ) return 2;
	if ( $size == "8" || $size == "1" || $size == "1/12" ) return 1;
	if ( $size == "1/5" ) return 2;
	if ( $size == "2/5" ) return 5;
	if ( $size == "3/5" ) return 7;
	if ( $size == "4/5" ) return 10;
}

// Find text width based on picture width 
function getTextSize( $picSize ) {
	if ( $picSize == "11" || $picSize == "11/12" ) : return "1/12";
	elseif ( $picSize == "10" || $picSize == "10/12" || $picSize == "5/6" ) : return "1/6";
	elseif ( $picSize == "9" || $picSize == "9/12" || $picSize == "3/4" ) : return "1/4";
	elseif ( $picSize == "8" || $picSize == "8/12" || $picSize == "2/3" ) : return "1/3";
	elseif ( $picSize == "7" || $picSize == "7/12" ) : return "5/12";
	elseif ( $picSize == "6" || $picSize == "6/12" || $picSize == "1/2" ) : return "1/2";
	elseif ( $picSize == "5" || $picSize == "5/12" ) : return "7/12";
	elseif ( $picSize == "4" || $picSize == "4/12" || $picSize == "1/3" ) : return "2/3";
	elseif ( $picSize == "3" || $picSize == "3/12" || $picSize == "1/4" ) : return "3/4";
	elseif ( $picSize == "2" || $picSize == "2/12" || $picSize == "1/6" ) : return "5/6";
	elseif ( $picSize == "1" || $picSize == "1/12" ) : return "11/12";
	elseif ( $picSize == "1/5" ) : return "4/5";
	elseif ( $picSize == "2/5" ) : return "3/5";
	elseif ( $picSize == "3/5" ) : return "2/5";
	elseif ( $picSize == "4/5" ) : return "1/5";
	else : return "100"; endif;
}

// Set up function to print contents of an array
function printArray($array) {
	$print = "";
	for ($i = 0; $i < count($array); $i++) {
		$print .= $array[$i];
	}
	return $print;
}

// Set up function to add / remove terms on post in front end
function adjustTerms( $post_id, $term, $taxonomy, $add_or_remove ) {
	if ( ! is_numeric( $term ) ) {
		$term = get_term( $term, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) return false;
		$term_id = $term->term_id;
	} else {
		$term_id = $term;
	}
	$new_terms = array();
	$today_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
	foreach ( $today_terms as $today_term ) {
		if ( $today_term != $term_id ) $new_terms[] = intval( $today_term );
	}
	if ( $add_or_remove == "add" ) $new_terms[] = intval( $term_id );	
	return wp_set_object_terms( $post_id, $new_terms, $taxonomy );
}

// Add Restrict Max & Min params in nav-menus
add_action( 'wp_nav_menu_item_custom_fields', 'battleplan_addMenuVisibility', 10, 2 );
function battleplan_addMenuVisibility( $item_id, $item ) {
	$restrictMax = readMeta( $item_id, 'bp_menu_restrict_max', true );
	$restrictMin = readMeta( $item_id, 'bp_menu_restrict_min', true );
	?>
	<div class="clearfix"></div>
	<p class="description description-thin"><?php _e( "Restrict Max", 'menu-restrict-max' ); ?><br />
	    <input type="hidden" class="nav-menu-id" value="<?php echo $item_id ;?>" />
	    <input type="text" name="menu_restrict_max[<?php echo $item_id ;?>]" id="menu-restrict-max-<?php echo $item_id ;?>" value="<?php echo esc_attr( $restrictMax ); ?>" />
	</p>
	<p class="description description-thin"><?php _e( "Restrict Min", 'menu-restrict-min' ); ?><br />
	    <input type="hidden" class="nav-menu-id" value="<?php echo $item_id ;?>" />
	    <input type="text" name="menu_restrict_min[<?php echo $item_id ;?>]" id="menu-restrict-min-<?php echo $item_id ;?>" value="<?php echo esc_attr( $restrictMin ); ?>" />
	</p>
	<?php
}

add_action( 'wp_update_nav_menu_item', 'battleplan_saveMenuVisibility', 10, 2 );
function battleplan_saveMenuVisibility( $menu_id, $item_id ) {
	if ( isset( $_POST['menu_restrict_max'][$item_id]  ) ) {
		$sanitized_data = sanitize_text_field( $_POST['menu_restrict_max'][$item_id] );
		updateMeta( $item_id, 'bp_menu_restrict_max', $sanitized_data );
	} else {
		deleteMeta( $item_id, 'bp_menu_restrict_max' );
	}
	if ( isset( $_POST['menu_restrict_min'][$item_id]  ) ) {
		$sanitized_data = sanitize_text_field( $_POST['menu_restrict_min'][$item_id] );
		updateMeta( $item_id, 'bp_menu_restrict_min', $sanitized_data );
	} else {
		deleteMeta( $item_id, 'bp_menu_restrict_min' );
	}
}

// Populate a secondary menu or sub-menu with posts/pages from any custom post type
function fillMenu($cpt, $max = "-1", $orderby = "title", $seq = "asc") { 
	global $cpt, $max, $orderby, $seq;	
	$types = explode(",", $cpt);	
	foreach ( $types as $type ) :
		add_filter( 'wp_get_nav_menu_items', function ($items, $menu, $args) use ($type) {
			global $max, $orderby, $seq;
			$child_items = array(); 
			$menu_order = count($items); 
			$parent_item_id = NULL;

			foreach ( $items as $item ) {
				if ( in_array($type, $item->classes) ) { $parent_item_id = $item->ID; }
			}

			$args = array ( 'numberposts'=>$max, 'offset'=>0, 'category'=>'', 'orderby'=>$orderby, 'order'=>$seq, 'post_type'=>$type, 'suppress_filters'=>true, );

			foreach ( get_posts( $args ) as $post ) {
				$post->menu_item_parent = $parent_item_id;
				$post->post_type = 'nav_menu_item';
				$post->object = 'custom';
				$post->type = 'custom';
				$post->menu_order = ++$menu_order;
				$post->title = $post->post_title;
				$post->url = get_permalink( $post->ID );
				array_push($child_items, $post);
			}
			return array_merge( $items, $child_items );
		}, 10, 3);
	endforeach;
}

// Truncate text
function truncateText($string, $limit="250", $break=" ", $pad="...") {
	if (strlen($string) <= $limit) return $string;
  	if (($breakpoint = strpos($string, $break, $limit)) !== false ) {
    	if ($breakpoint < strlen($string) - 1) {
      		$string = substr($string, 0, $breakpoint).$pad;
   		}
  	}
  	return $string;
}

// Remove sidebar from specific pages
function battleplan_remove_sidebar( $classes ) {
	$classes = str_replace('sidebar-line', 'sidebar-none', $classes);
	$classes = str_replace('sidebar-right', 'sidebar-none', $classes);
	$classes = str_replace('sidebar-left', 'sidebar-none', $classes);
	return $classes;
}

function removeSidebar($classes, $addClasses, $pages) {
	foreach ($pages as $page) :
		if ( _PAGE_SLUG == $page || in_array($page, $classes) ) return battleplan_remove_sidebar( $addClasses );
	endforeach;		
	return $addClasses;
}

// If post has "remove sidebar" checked, set necessary classes on <body> 
add_filter( 'body_class', 'battleplan_CheckRemoveSidebar', 50 );
function battleplan_CheckRemoveSidebar( $classes ) {
	if ( readMeta( get_the_ID(), '_bp_remove_sidebar', true ) ) :
		return battleplan_remove_sidebar( $classes );
	else:
		return $classes;
	endif;
}

// If post is an "optimized" page, add .home to body class for CSS purposes
add_filter( 'body_class', 'battleplan_addHomeBodyClassToOptimized', 70 );
function battleplan_addHomeBodyClassToOptimized( $classes ) {
	if ( get_post_type() == "optimized" ) :
		array_push($classes, 'home');
	endif;
	return $classes;	
}

// Ensure all classes that have been added to <body> exist as an array
add_filter( 'body_class', 'battleplan_bodyClassArray', 100 );
function battleplan_bodyClassArray( $classes ) {
	$newClasses = array();
	foreach ($classes as $class) :
		$class = explode(" ", $class);
		$newClasses = array_merge( $newClasses, $class );	
	endforeach;
	return $newClasses;
}

// Add shortcode capability to Contact Form 7
add_filter( 'wpcf7_form_elements', 'do_shortcode' );

/*--------------------------------------------------------------
# Register Custom Post Types
--------------------------------------------------------------*/

add_action( 'init', 'battleplan_registerPostTypes', 0 );
function battleplan_registerPostTypes() {
	register_post_type( 'testimonials', array (
		'label'=>__( 'testimonials', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Testimonials', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Testimonial', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-format-quote',
		'has_archive'=>true,
		'capability_type'=>'post',
	));
	register_post_type( 'galleries', array (
		'label'=>__( 'galleries', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Galleries', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Gallery', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields', 'comments' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-images-alt',
		'has_archive'=>true,
		'capability_type'=>'post',
	));
	register_taxonomy( 'gallery-type', array( 'galleries' ), array(
		'labels'=>array(
			'name'=>_x( 'Gallery Type', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Gallery Type', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>true,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
	wp_insert_term( 'Auto Generated', 'gallery-type' );	
	wp_insert_term( 'Shortcode', 'gallery-type' );
	register_taxonomy( 'gallery-tags', array( 'galleries' ), array(
		'labels'=>array(
			'name'=>_x( 'Gallery Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Gallery Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>false,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
	register_taxonomy( 'image-categories', array( 'attachment' ), array(
		'labels'=>array(
			'name'=>_x( 'Image Categories', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Image Category', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>true,
		'show_ui'=>true,        
		'query_var'=>true,
        'rewrite'=>true,
        'show_admin_column'=>true,
	));
	register_taxonomy( 'image-tags', array( 'attachment' ), array(
		'labels'=>array(
			'name'=>_x( 'Image Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Image Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>false,
		'show_ui'=>true,        
		'query_var'=>true,
        'rewrite'=>true,
        'show_admin_column'=>true,
	));
	register_post_type( 'optimized', array (
		'label'=>__( 'optimized', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Optimized', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Optimized', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-edit-page',
		'has_archive'=>true,
		'capability_type'=>'page',
	));
	register_post_type( 'elements', array (
		'label'=>__( 'elements', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Elements', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Element', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'supports'=>array( 'title', 'editor' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-block-default', 
		'has_archive'=>false,
		'capability_type'=>'page',
	));
	register_post_type( 'universal', array (
		'label'=>__( 'universal', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Universal', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Universal', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'supports'=>array( 'title', 'editor' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-admin-site-alt3', 
		'has_archive'=>false,
		'capability_type' => 'page',
		'capabilities' => array(
			'create_posts' => false,
		),
		'map_meta_cap' => true,
	));
}

// Remove 'optimized' from the url so that optimized pages look like regular pages
add_filter( 'post_type_link', 'battleplan_remove_cpt_slug', 10, 2 );
function battleplan_remove_cpt_slug( $post_link, $post ) {
	if ( 'universal' === $post->post_type || 'optimized' === $post->post_type || 'elements' === $post->post_type ) {
 		$post_link = str_replace( '/' . $post->post_type . '/', '/', $post_link );
 	}
 	return $post_link;
}

add_action( 'pre_get_posts', 'battleplan_add_cpt_to_main_query' );
function battleplan_add_cpt_to_main_query( $query ) {
	if ( !$query->is_main_query() ) return;
	if ( !isset( $query->query['page'] ) || 2 !== count( $query->query ) ) return;
	if ( empty( $query->query['name'] ) ) return;
	$query->set( 'post_type', array( 'post', 'page', 'optimized', 'universal' ) );
}

/*--------------------------------------------------------------
# Import Advanced Custom Fields
--------------------------------------------------------------*/
add_action('acf/init', 'battleplan_add_acf_fields');
function battleplan_add_acf_fields() {
	acf_add_local_field_group(array(
		'key' => 'group_5bd6f6743bbfe',
		'title' => 'Testimonials',
		'fields' => array(
			array(
				'key' => 'field_52e95521e0f7e',
				'label' => 'Name',
				'name' => 'testimonial_name',
				'type' => 'text',
				'instructions' => 'Enter the name of the person giving the testimonial.',
				'required' => 1,
				'conditional_logic' => 0,
			),
			array(
				'key' => 'field_580deeb1986b4',
				'label' => 'Business Name',
				'name' => 'testimonial_biz',
				'type' => 'text',
				'instructions' => 'Enter the business name of the person giving the testimonial.',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_580def61986b6',
				'label' => 'Business Website',
				'name' => 'testimonial_website',
				'type' => 'text',
				'instructions' => 'Enter the website of the person giving the testimonial (include http:// or https://).',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_52e9553be0f7f',
				'label' => 'Location',
				'name' => 'testimonial_location',
				'type' => 'text',
				'instructions' => 'Enter the location of the person giving the testimonial.',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_981frha7553v6',
				'label' => 'Platform',
				'name' => 'testimonial_platform',
				'type' => 'radio',
				'instructions' => 'What platform did this testimonial come from?',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'None' => 'None',
					'Facebook' => 'Facebook',
					'Google' => 'Google',
					'Yelp' => 'Yelp',
					'Nextdoor' => 'Nextdoor',
					'YP' => 'YP',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 0,
				'layout' => 'horizontal',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_580deec4986b5',
				'label' => 'Rating',
				'name' => 'testimonial_rating',
				'type' => 'radio',
				'instructions' => 'Enter the testimonial\'s rating on a scale of 1 to 5.',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					0 => 'Unrated',
					1 => '1',
					2 => '2',
					3 => '3',
					4 => '4',
					5 => '5',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 0,
				'layout' => 'horizontal',
				'allow_null' => 0,
				'return_format' => 'value',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'testimonials',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'seamless',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => array(
			0 => 'custom_fields',
			1 => 'discussion',
			2 => 'comments',
			3 => 'revisions',
			4 => 'slug',
			5 => 'author',
			6 => 'format',
			7 => 'categories',
			8 => 'tags',
			9 => 'send-trackbacks',
		),
		'active' => true,
		'description' => '',
	));
}

/*--------------------------------------------------------------
# Basic Theme Set Up
--------------------------------------------------------------*/

// Enable auto-updates on plugins and themes
add_filter( 'auto_update_plugin', '__return_true' );
add_filter( 'auto_update_theme', '__return_true' );

// Determine how to sort custom post types
add_action( 'pre_get_posts', 'battleplan_handle_main_query', 1 );
function battleplan_handle_main_query( $query ) {
	if (!is_admin() && $query->is_main_query()) :		
		if ( is_post_type_archive('testimonials') ) :
			$query->set( 'post_type','testimonials');
			$query->set( 'posts_per_page',10);
			$query->set( 'orderby','rand');
		endif;
		if ( is_post_type_archive('galleries') ) :
			$query->set( 'post_type','galleries');
			$query->set( 'posts_per_page',-1);
			$query->set( 'orderby','rand');
		endif;
	endif; 
}

// Maintain pagination when using orderby=rand
add_filter( 'posts_orderby', 'battleplan_randomize_with_pagination' );
function battleplan_randomize_with_pagination( $orderby ) { 
	if ( $orderby == "RAND()" && isset($_COOKIE['unique-id']) ) :
		$orderby = "RAND(".$_COOKIE['unique-id'].")";
	endif;
	return $orderby;
}

// Add some defining classes to body
add_filter( 'body_class', 'battleplan_addBodyClasses', 30 );
function battleplan_addBodyClasses( $classes ) {	
	$classes[] = "slug-"._PAGE_SLUG; 
	
	if (!isset($_COOKIE['first-page'])) :
		$classes[] = "first-page";
		//setcookie('first-page', 'no', '0', '/', '', true, false);
		writeCookie('first-page', 'no', '0');
	else:
		$classes[] = "not-first-page";
	endif;
	
 	if ( is_mobile() ) : $classes[] = "screen-mobile"; else: $classes[] = "screen-desktop"; endif;
	
	if ( $GLOBALS['customer_info']['site-type'] ) $classes[] = "site-type-".$GLOBALS['customer_info']['site-type'];
	
	return $classes;
}	

// Calculate how many pages user has viewed (exclude page refresh)
add_filter( 'init', 'battleplan_calculatePagesViewed' );
function battleplan_calculatePagesViewed() {
  	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
	if (!isset($_COOKIE['unique-id'])) :
		$uniqueID = time().rand();		
		//setcookie('unique-id', $uniqueID, '0', '/', '', true, false);
		//setcookie('pages-viewed', 1, '0', '/', '', true, false);
		writeCookie('unique-id', $uniqueID, '0');
		writeCookie('pages-viewed', 1, '0');
	else:
		$pageViews = $_COOKIE['pages-viewed'];		
		if ( $_COOKIE['current-page'] != _PAGE_SLUG ) :
			$pageViews++;
			//setcookie('pages-viewed', $pageViews, '0', '/', '', true, false);
			//setcookie('current-page', _PAGE_SLUG, '0', '/', '', true, false);
			writeCookie('pages-viewed', $pageViews, '0');
			writeCookie('current-page', _PAGE_SLUG, '0');
		endif;		
	endif;	
}	

// Add Breadcrumbs
function battleplan_breadcrumbs() {
    $home_link        = home_url('/');
    $home_text        = __( 'Home' );
    $link_before      = '<span typeof="v:Breadcrumb">';
    $link_after       = '</span>';
    $link_attr        = ' rel="v:url" property="v:title"';
    $link             = $link_before . '<a' . $link_attr . ' href="%1$s">%2$s</a>' . $link_after;
    $delimiter        = ' &raquo; ';              // Delimiter between crumbs
    $before           = '<span class="current">'; // Tag before the current crumb
    $after            = '</span>';                // Tag after the current crumb
    $page_addon       = '';                       // Adds the page number if the query is paged
    $breadcrumb_trail = '';
    $category_links   = '';

    $wp_the_query   = $GLOBALS['wp_the_query'];
    $queried_object = $wp_the_query->get_queried_object();

    if ( is_singular() ) :
        $post_object 	= sanitize_post( $queried_object );
        $title          = apply_filters( 'the_title', $post_object->post_title, $post->ID);
        $parent         = $post_object->post_parent;
        $post_type      = $post_object->post_type;
        $post_id        = $post_object->ID;
        $post_link      = $before . $title . $after;
        $parent_string  = '';
        $post_type_link = '';

        if ( $post_type === 'post' ) :
            $categories = get_the_category( $post_id );
            if ( $categories ) :
                $category  = $categories[0];
                $category_links = get_category_parents( $category, true, $delimiter );
                $category_links = str_replace( '<a',   $link_before . '<a' . $link_attr, $category_links );
                $category_links = str_replace( '</a>', '</a>' . $link_after,             $category_links );
            endif;
       endif;

        if ( !in_array( $post_type, ['post', 'page', 'attachment'] ) ) :
            $post_type_object = get_post_type_object( $post_type );
            $archive_link     = esc_url( get_post_type_archive_link( $post_type ) );
            $post_type_link   = sprintf( $link, $archive_link, $post_type_object->labels->name );
       	endif;

        if ( $parent !== 0 ) :
            $parent_links = [];
            while ( $parent ) :
                $post_parent = get_post( $parent );
                $parent_links[] = sprintf( $link, esc_url( get_permalink( $post_parent->ID ) ), get_the_title( $post_parent->ID ) );
                $parent = $post_parent->post_parent;
            endwhile;
            $parent_links = array_reverse( $parent_links );
            $parent_string = implode( $delimiter, $parent_links );
        endif;

        if ( $parent_string ) :
            $breadcrumb_trail = $parent_string . $delimiter . $post_link;
        else :
            $breadcrumb_trail = $post_link;
        endif;

        if ( $post_type_link ) : $breadcrumb_trail = $post_type_link . $delimiter . $breadcrumb_trail; endif;

        if ( $category_links ) : $breadcrumb_trail = $category_links . $breadcrumb_trail; endif;
    endif;

    if( is_archive() ) :
        if ( is_category() || is_tag() || is_tax() ) :
            $term_object        = get_term( $queried_object );
            $taxonomy           = $term_object->taxonomy;
            $term_id            = $term_object->term_id;
            $term_name          = $term_object->name;
            $term_parent        = $term_object->parent;
            $taxonomy_object    = get_taxonomy( $taxonomy );
            $today_term_link  = $before . $taxonomy_object->labels->name . ': ' . $term_name . $after;
            $parent_term_string = '';

            if ( $term_parent !== 0 ) :
                $parent_term_links = [];
                while ( $term_parent ) :
                    $term = get_term( $term_parent, $taxonomy );
                    $parent_term_links[] = sprintf( $link, esc_url( get_term_link( $term ) ), $term->name );
                    $term_parent = $term->parent;
                endwhile;
                $parent_term_links  = array_reverse( $parent_term_links );
                $parent_term_string = implode( $delimiter, $parent_term_links );
            endif;

            if ( $parent_term_string ) :
                $breadcrumb_trail = $parent_term_string . $delimiter . $today_term_link;
            else :
                $breadcrumb_trail = $today_term_link;
            endif;

      	elseif ( is_author() ) :
            $breadcrumb_trail = __( 'Author archive for ') .  $before . $queried_object->data->display_name . $after;

        elseif ( is_date() ) :
            $year     = $wp_the_query->query_vars['year'];
            $monthnum = $wp_the_query->query_vars['monthnum'];
            $day      = $wp_the_query->query_vars['day'];

            if ( $monthnum ) :
                $date_time  = DateTime::createFromFormat( '!m', $monthnum );
                $month_name = $date_time->format( 'F' );
            endif;

            if ( is_year() ) : $breadcrumb_trail = $before . $year . $after; 

            elseif ( is_month() ) :
                $year_link        = sprintf( $link, esc_url( get_year_link( $year ) ), $year );
                $breadcrumb_trail = $year_link . $delimiter . $before . $month_name . $after;

            elseif ( is_day() ) :
                $year_link        = sprintf( $link, esc_url( get_year_link( $year ) ),             $year       );
                $month_link       = sprintf( $link, esc_url( get_month_link( $year, $monthnum ) ), $month_name );
                $breadcrumb_trail = $year_link . $delimiter . $month_link . $delimiter . $before . $day . $after;
            endif;

        elseif ( is_post_type_archive() ) :
            $post_type        = $wp_the_query->query_vars['post_type'];
            $post_type_object = get_post_type_object( $post_type );
            $breadcrumb_trail = $before . $post_type_object->labels->name . $after;
        endif;
    endif;   

    if ( is_search() ) : $breadcrumb_trail = __( 'Search query for: ' ) . $before . get_search_query() . $after; endif;

    if ( is_404() ) : $breadcrumb_trail = $before . __( 'Error 404' ) . $after; endif;

    if ( is_paged() ) :
        $today_page = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' );
        $page_addon   = $before . sprintf( __( ' ( Page %s )' ), number_format_i18n( $today_page ) ) . $after;
    endif;

    $breadcrumb_output_link = '<div class="breadcrumbs">';
    if ( is_home() || is_front_page() ) :
        if ( is_paged() ) :
            $breadcrumb_output_link .= '<a href="' . $home_link . '">' . $home_text . '</a>';
            $breadcrumb_output_link .= $page_addon;
        endif;
    else :
        $breadcrumb_output_link .= '<a href="' . $home_link . '" rel="v:url" property="v:title">' . $home_text . '</a>';
        $breadcrumb_output_link .= $delimiter;
        $breadcrumb_output_link .= $breadcrumb_trail;
        $breadcrumb_output_link .= $page_addon;
    endif;
    $breadcrumb_output_link .= '</div><!-- .breadcrumbs -->';

    return $breadcrumb_output_link;
}

// Deal with sitemaps
add_filter( 'wpseo_sitemap_exclude_post_type', 'battleplan_sitemap_exclude_post_type', 10, 2 );
function battleplan_sitemap_exclude_post_type( $excluded, $post_type ) {
    return $post_type === 'elements';
}

add_filter( 'wpseo_sitemap_exclude_taxonomy', 'battleplan_sitemap_exclude_taxonomy', 10, 2 );
function battleplan_sitemap_exclude_taxonomy( $excluded, $taxonomy ) {
    return $taxonomy === 'image-categories' || $taxonomy === 'image-tags';
}

// https://developer.yoast.com/features/xml-sitemaps/api/#exclude-specific-posts

// Set up post meta date
function battleplan_meta_date() {
	$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
	if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) : $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="entry-date updated" datetime="%3$s">%4$s</time>'; endif;

	$time_string = sprintf ( $time_string, esc_attr( get_the_date( DATE_W3C ) ), esc_html( get_the_date() ), esc_attr( get_the_modified_date( DATE_W3C ) ), esc_html( get_the_modified_date() ) );
	$posted_on = sprintf ( esc_html_x( '%s', 'post date', 'battleplan' ), $time_string );

	return '<span class="meta-date"><i class="fas fa-calendar-alt"></i>'.$posted_on.'</span>';
}

// Set up post meta author
function battleplan_meta_author($link='false') {
	$byline = sprintf ( esc_html_x( '%s', 'post author', 'battleplan' ), '<span class="author vcard">'.esc_html( get_the_author() ).'</span>' );	
	$printByline = '<span class="meta-author">';
	if ( $link == 'profile' ) $printByline .= '<a class="author-link" href="/profile/?user='.esc_html( get_the_author() ).'">';	
	$printByline .= '<i class="fas fa-user"></i>'.$byline;
	if ( $link == 'profile' ) $printByline .= '</a>';	
	$printByline .= '</span>';
	
	return $printByline;
}

// Set up post meta comments
function battleplan_meta_comments() {		
	return '<span class="meta-comments"><i class="fas fa-comments"></i>'.get_comments_number().'</span>';
}

// Set up comment structure
function battleplan_comment_structure($comment, $args, $depth) {
	$GLOBALS['comment'] = $comment; ?>
	<li <?php comment_class(); ?> id="li-comment-<?php comment_ID() ?>">
		<div id="comment-<?php comment_ID(); ?>">
			<div class="comment-author vcard">
				<?php echo get_avatar($comment,$size='64' ); ?>
				<?php printf(__('<cite class="fn"><h3 class="comment-author">%s</h3></cite>'), get_comment_author_link()) ?>
      		</div>
			<?php if ($comment->comment_approved == '0') : ?>
         		<em><?php _e('Your comment is awaiting moderation.') ?></em>
         		<br />
			<?php endif; ?>

      		<div class="comment-meta">
				<?php printf(__('<span class="comment-date">%1$s</span><span class="comment-time"> at %2$s</span>'), get_comment_date(), get_comment_time()) ?>
				<?php edit_comment_link(__('(Edit)'),'  ','') ?>
			</div>

      		<?php comment_text() ?>
			
      		<div class="reply">
         		<?php comment_reply_link(array_merge( $args, array('depth' => $depth, 'max_depth' => $args['max_depth']))) ?>
      		</div>
		</div>
<?php }

// Add .button class to comment reply link
add_filter('comment_reply_link', 'battleplan_comment_reply_link', 99);
function battleplan_comment_reply_link($content) {
    return preg_replace( '/comment-reply-link/', 'button comment-reply-link', $content);
}

// Re-format the 'cancel reply' button
add_filter( 'cancel_comment_reply_link', 'battleplan_cancel_comment_reply_link', 10, 3 );
function battleplan_cancel_comment_reply_link( $formatted_link, $link, $text ) {
	$formatted_link = '<p class="reply"><a id="cancel-comment-reply-link" class="button" rel="nofollow" href="'.$link.'">'.$text.'</a></p>';
	return $formatted_link;
}

// Set up footer social media box
add_shortcode( 'get-social-box', 'battleplan_footer_social_box' );
function battleplan_footer_social_box() {	
	$buildLeft = "<div class='social-box'>";
		if ( do_shortcode('[get-biz info="facebook"]') ) $buildLeft .= do_shortcode('[social-btn type="facebook"]'); 							
		if ( do_shortcode('[get-biz info="twitter"]') ) $buildLeft .= do_shortcode('[social-btn type="twitter"]');						
		if ( do_shortcode('[get-biz info="instagram"]') ) $buildLeft .= do_shortcode('[social-btn type="instagram"]');							
		if ( do_shortcode('[get-biz info="linkedin"]') ) $buildLeft .= do_shortcode('[social-btn type="linkedin"]');							
		if ( do_shortcode('[get-biz info="yelp"]') ) $buildLeft .= do_shortcode('[social-btn type="yelp"]');							
		if ( do_shortcode('[get-biz info="pinterest"]') ) $buildLeft .= do_shortcode('[social-btn type="pinterest"]');								
		if ( do_shortcode('[get-biz info="youtube"]') ) $buildLeft .= do_shortcode('[social-btn type="youtube"]');											
		if ( do_shortcode('[get-biz info="tiktok"]') ) $buildLeft .= do_shortcode('[social-btn type="tiktok"]');							
		if ( do_shortcode('[get-biz info="email"]') ) $buildLeft .= do_shortcode('[social-btn type="email"]');
	$buildLeft .= "</div>";
	return $buildLeft;
}

// Stop adding line breaks to content
remove_filter( 'the_content', 'wpautop' );
remove_filter( 'the_excerpt', 'wpautop' );
add_filter( 'the_content', 'battleplan_wpautop_without_br' , 99);
add_filter( 'the_excerpt', 'battleplan_wpautop_without_br' , 99);
function battleplan_wpautop_without_br( $content ) {
    return wpautop( $content, false );
}

// Necessary housekeeping items
add_action( 'after_setup_theme', 'battleplan_setup' );
if ( ! function_exists( 'battleplan_setup' ) ) :
	function battleplan_setup() {
		load_theme_textdomain( 'battleplan', get_template_directory() . '/languages' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		register_nav_menus( array( 'top-menu' => esc_html__( 'Top Menu', 'battleplan' ), ) );
		register_nav_menus( array( 'header-menu' => esc_html__( 'Header Menu', 'battleplan' ), ) );				
		register_nav_menus( array( 'widget-menu' => esc_html__( 'Widget Menu', 'battleplan' ), ) );		
		register_nav_menus( array( 'footer-menu' => esc_html__( 'Footer Menu', 'battleplan' ), ) );	
		register_nav_menus( array( 'manual-menu' => esc_html__( 'Manual Menu', 'battleplan' ), ) );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', ) );
		add_theme_support( 'customize-selective-refresh-widgets' );
	}
endif;

// Set content width param
add_action( 'after_setup_theme', 'battleplan_content_width', 0 );
function battleplan_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'battleplan_content_width', 640 );
}

// Set up sidebar
add_action( 'widgets_init', 'battleplan_widgets_init' );
function battleplan_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar', 'battleplan' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Add widgets here.', 'battleplan' ),
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
 
// Disable emojis
add_action( 'init', 'battleplan_disable_emojis' );
function battleplan_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' ); 
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' ); 
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'tiny_mce_plugins', 'battleplan_disable_emojis_tinymce' );
	add_filter( 'wp_resource_hints', 'battleplan_disable_emojis_remove_dns_prefetch', 10, 2 );
}

function battleplan_disable_emojis_tinymce( $plugins ) {
	if ( is_array( $plugins ) ) { return array_diff( $plugins, array( 'wpemoji' ) ); } else { return array(); }
}

function battleplan_disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
	if ( 'dns-prefetch' == $relation_type ) {
		$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
		$urls = array_diff( $urls, array( $emoji_svg_url ) );
	}
	return $urls;
}

// Defer jquery and other js to footer & add nonce to inline scripts
add_filter('script_loader_tag', 'battleplan_add_data_attribute', 10, 3);
function battleplan_add_data_attribute($tag, $handle, $src) {
    if ( is_admin() || $GLOBALS['pagenow'] === 'wp-login.php' || strpos( $src, '.js' ) === FALSE ) return $tag;
	$nonce = $GLOBALS['nonce'];
	$tag = '<script nonce="'.$nonce.'" id="'.$handle.'" defer src="'.esc_url( $src ).'"></script>'; 
    return $tag;
}

add_filter('init', 'battleplan_filter_localized_scripts', 100);
function battleplan_filter_localized_scripts() {
    $fscripts              = new WP_Filterable_Scripts;
    $GLOBALS['wp_scripts'] = $fscripts;
}

add_filter( 'battleplan_csp_localized_scripts', 'battleplan_add_nonce_to_localized_scripts', 0, 2 );
function battleplan_add_nonce_to_localized_scripts( $tag, $handle ) {
	$nonce = $GLOBALS['nonce'];
    $attr = "nonce='".$nonce."' ";
    if ( ! is_admin() ) {
        $tag = str_replace( '<script ', '<script ' . $attr, $tag );
    }
    return $tag;
}

// Dequeue unneccesary styles & scripts
add_action( 'wp_print_styles', 'battleplan_dequeue_unwanted_stuff', 9998 );
function battleplan_dequeue_unwanted_stuff() {
	wp_dequeue_style( 'wp-block-library' );  wp_deregister_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );  wp_deregister_style( 'wp-block-library-theme' );	
	wp_dequeue_style( 'select2' );  wp_deregister_style( 'select2' );
	wp_dequeue_style( 'asp-default-style' ); wp_deregister_style( 'asp-default-style' );		
	wp_dequeue_style( 'contact-form-7' ); wp_deregister_style( 'contact-form-7' );	
	//if ( is_plugin_active( 'ari-fancy-lightbox/ari-fancy-lightbox.php' ) ) { wp_dequeue_style( 'ari-fancybox' ); wp_deregister_style( 'ari-fancybox' ); }
	if ( is_plugin_active( 'animated-typing-effect/typingeffect.php' ) ) { wp_dequeue_style( 'typed-cursor' ); wp_deregister_style( 'typed-cursor' ); }

// re-load in header
	if ( is_plugin_active( 'stripe-payments/accept-stripe-payments.php' ) ) { wp_dequeue_style( 'stripe-handler-ng-style' ); wp_deregister_style( 'stripe-handler-ng-style' ); }
	if ( is_plugin_active( 'cue/cue.php' ) ) { wp_dequeue_style( 'cue' ); wp_deregister_style( 'cue' ); }
	
// re-load in footer
	wp_dequeue_style( 'css-animate' );  wp_deregister_style( 'css-animate' );
	wp_dequeue_style( 'fontawesome' ); wp_deregister_style( 'fontawesome' );
	if ( is_plugin_active( 'extended-widget-options/plugin.php' ) ) { wp_dequeue_style( 'widgetopts-styles' ); wp_deregister_style( 'widgetopts-styles' ); }
	
// scripts
	wp_dequeue_script( 'select2'); wp_deregister_script('select2');	
	wp_dequeue_script( 'wphb-global' ); wp_deregister_script( 'wphb-global' );
	wp_dequeue_script( 'wp-embed' ); wp_deregister_script( 'wp-embed' );
	wp_dequeue_script( 'modernizr' ); wp_deregister_script( 'modernizr' );		
	if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) { wp_dequeue_script( 'underscore' ); wp_deregister_script( 'underscore' ); } 
}

// Load and enqueue styles in header
add_action( 'wp_print_styles', 'battleplan_header_styles', 9999 );
function battleplan_header_styles() {
	if ( is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) ) { wp_enqueue_style( 'battleplan-events', get_template_directory_uri()."/style-events.css", array(), _BP_VERSION ); } 	
	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { wp_enqueue_style( 'battleplan-woocommerce', get_template_directory_uri()."/style-woocommerce.css", array(), _BP_VERSION ); } 
	if ( is_plugin_active( 'stripe-payments/accept-stripe-payments.php' ) ) { wp_enqueue_style( 'battleplan-stripe-payments', get_template_directory_uri()."/style-stripe-payments.css", array(), _BP_VERSION ); } 
	if ( is_plugin_active( 'cue/cue.php' ) ) { wp_enqueue_style( 'battleplan-cue', get_template_directory_uri()."/cue.css", array(), _BP_VERSION ); } 	
	
	wp_enqueue_style( 'parent-style', get_template_directory_uri()."/style.css", array(), _BP_VERSION );
	
	if ( $GLOBALS['customer_info']['site-type'] == 'profile' || $GLOBALS['customer_info']['site-type'] == 'profiles' ) { wp_enqueue_style( 'battleplan-user-profiles', get_template_directory_uri().'/style-user-profiles.css', array(), _BP_VERSION ); }		
	
	wp_enqueue_style( 'battleplan-style', get_stylesheet_directory_uri()."/style-site.css", array(), _BP_VERSION );	
}

// Load and enqueue styles in footer
add_action( 'wp_footer', 'battleplan_footer_styles' );
function battleplan_footer_styles() {
	wp_enqueue_style( 'battleplan-animate', get_template_directory_uri().'/animate.css', array(), _BP_VERSION );	
	wp_enqueue_style( 'battleplan-fontawesome', get_template_directory_uri()."/fontawesome.css", array(), _BP_VERSION );
	if ( is_plugin_active( 'extended-widget-options/plugin.php' ) ) { wp_enqueue_style( 'widgetopts-styles', '/wp-content/plugins/extended-widget-options/assets/css/widget-options.css', array(), _BP_VERSION ); }	
	//if ( is_plugin_active( 'ari-fancy-lightbox/ari-fancy-lightbox.php' ) ) { wp_enqueue_style( 'ari-fancybox-styles', '/wp-content/plugins/ari-fancy-lightbox/assets/fancybox/jquery.fancybox.min.css', array(), _BP_VERSION ); }		
}

// Load and enqueue remaining scripts
add_action( 'wp_enqueue_scripts', 'battleplan_scripts', 20 );
function battleplan_scripts() {
	if ( !is_mobile() ) { wp_enqueue_script( 'battleplan-parallax', get_template_directory_uri().'/js/parallax.js', array(), _BP_VERSION, false ); }
	//wp_enqueue_script( 'battleplan-carousel', get_template_directory_uri().'/js/bootstrap-carousel.js', array(), _BP_VERSION, false );	
	wp_enqueue_script( 'battleplan-waypoints', get_template_directory_uri().'/js/waypoints.js', array(), _BP_VERSION, false );	
	
	if ( !is_mobile() ) { wp_enqueue_script( 'battleplan-script-desktop', get_template_directory_uri().'/js/script-desktop.js', array(), _BP_VERSION, false ); }
	wp_enqueue_script( 'battleplan-script-essential', get_template_directory_uri().'/js/script-essential.js', array(), _BP_VERSION, false );				
	wp_enqueue_script( 'battleplan-script-site', get_stylesheet_directory_uri().'/script-site.js', array(), _BP_VERSION, false );	
	wp_enqueue_script( 'battleplan-script-tracking', get_template_directory_uri().'/js/script-tracking.js', array(), _BP_VERSION, false ); 	
	wp_enqueue_script( 'battleplan-script-cloudflare', get_template_directory_uri().'/js/script-cloudflare.js', array(), _BP_VERSION, false );
	
	if ( is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) ) { wp_enqueue_script( 'battleplan-script-events', get_template_directory_uri().'/js/events.js', array(), _BP_VERSION, false ); } 
	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { wp_enqueue_script( 'battleplan-script-woocommerce', get_template_directory_uri().'/js/woocommerce.js', array(), _BP_VERSION, false ); } 
	if ( is_plugin_active( 'cue/cue.php' ) ) { wp_enqueue_script( 'battleplan-script-cue', get_template_directory_uri().'/js/cue.js', array(), _BP_VERSION, false ); } 
	if ( $GLOBALS['customer_info']['site-type'] == 'profile' || $GLOBALS['customer_info']['site-type'] == 'profiles' ) { wp_enqueue_script( 'battleplan-script-user-profiles', get_template_directory_uri().'/js/script-user-profiles.js', array(), _BP_VERSION, false ); } 
	
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) { wp_enqueue_script( 'comment-reply' ); }
	
	$saveDir = array( 'theme_dir_uri'=>get_stylesheet_directory_uri(), 'upload_dir_uri'=>wp_upload_dir()['baseurl'] );
	wp_localize_script( 'battleplan-script-essential', 'site_dir', $saveDir );	
	wp_localize_script( 'battleplan-script-desktop', 'site_dir', $saveDir );	
	
    $saveOptions = array ( 'lat' => $GLOBALS['customer_info']['lat'], 'long' => $GLOBALS['customer_info']['long'], 'radius' => $GLOBALS['customer_info']['radius'] );
    wp_localize_script('battleplan-script-tracking', 'site_options', $saveOptions);
}

// Load and enqueue admin styles & scripts
add_action( 'admin_enqueue_scripts', 'battleplan_admin_scripts' );
function battleplan_admin_scripts() {
	wp_enqueue_style( 'battleplan-admin-css', get_template_directory_uri().'/style-admin.css', array(), _BP_VERSION );	
	wp_enqueue_script( 'battleplan-admin-script', get_template_directory_uri().'/js/script-admin.js', array(), _BP_VERSION, false );	
	if ( $GLOBALS['customer_info']['site-type'] == 'profile' || $GLOBALS['customer_info']['site-type'] == 'profiles' ) { 
		wp_enqueue_style( 'battleplan-user-profiles', get_template_directory_uri().'/style-user-profiles.css', array(), _BP_VERSION ); 		
		wp_enqueue_script( 'battleplan-script-user-profiles', get_template_directory_uri().'/js/script-user-profiles.js', array(), _BP_VERSION, false ); 
	}
}

// Load and enqueue login styles
add_action( 'login_enqueue_scripts', 'battleplan_login_enqueue' );
function battleplan_login_enqueue() {
	wp_dequeue_style( 'login' );  wp_deregister_style( 'login' );
	wp_enqueue_style( 'parent-style', get_template_directory_uri()."/style.css", array(), _BP_VERSION );
	wp_enqueue_style( 'battleplan-style', get_stylesheet_directory_uri()."/style-site.css", array(), _BP_VERSION );	
	wp_enqueue_style( 'battleplan-login', get_template_directory_uri()."/style-login.css", array(), _BP_VERSION );
}

// Load various includes
if ( is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) ) { require_once get_template_directory() . '/includes/includes-events.php'; } 
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { require_once get_template_directory() . '/includes/includes-woocommerce.php'; } 
if ( $GLOBALS['customer_info']['site-type'] == 'hvac' ) { require_once get_template_directory() . '/includes/includes-hvac.php'; } 
if ( $GLOBALS['customer_info']['site-type'] == 'pedigree' ) { require_once get_template_directory() . '/includes/includes-pedigree.php'; } 
if ( $GLOBALS['customer_info']['site-type'] == 'profile' || $GLOBALS['customer_info']['site-type'] == 'profiles' ) { require_once get_template_directory() . '/includes/includes-user-profiles.php'; } 
require_once get_template_directory() . '/functions-public.php';
require_once get_stylesheet_directory() . '/functions-site.php';
if ( is_admin() ) { require_once get_template_directory() . '/functions-admin.php'; } 

// Delay execution of non-essential scripts 
if ( !is_admin() && $GLOBALS['pagenow'] !== 'wp-login.php' && !is_plugin_active( 'woocommerce/woocommerce.php' ) && strpos($_SERVER['REQUEST_URI'], '.xml') == false ) {
	ob_start(); 
	add_action('shutdown', function() { $final = ''; $levels = ob_get_level(); for ($i = 0; $i < $levels; $i++) { $final .= ob_get_clean(); } echo apply_filters('final_output', $final); }, 0);
	add_filter('final_output', function($html) {
		$dom = new DOMDocument();
		$dom->loadHTML($html);
		$script = $dom->getElementsByTagName('script'); 

		$targets = array('podium', 'leadconnectorhq', 'google', 'paypal', 'carousel', 'extended-widget', 'embed-player', 'huzzaz', 'fbcdn', 'facebook', 'klaviyo');

		foreach ($script as $item) :		   
			foreach ($targets as $target) :
				if (strpos($item->getAttribute("src"), $target) !== FALSE) :       
					$item->setAttribute("data-loading", "delay");
					if ($item->getAttribute("src")) : $item->setAttribute("data-src", $item->getAttribute("src")); $item->removeAttribute("src");
					else: $item->setAttribute("data-src", "data:text/javascript;base64,".base64_encode($item->innertext)); $item->innertext=""; 
					endif;
				endif;
			endforeach;
		endforeach;

		$html = $dom->saveHTML();
		$html = preg_replace('/<!DOCTYPE.*?<html>.*?<body><p>/ims', '', $html);
		$html = str_replace('</p></body></html>', '', $html);
		return $html;
	}); 

	add_action( 'wp_print_footer_scripts', 'battleplan_delay_nonessential_scripts');
	function battleplan_delay_nonessential_scripts() { ?>
		<script nonce="<?php echo $GLOBALS['nonce']; ?>" type="text/javascript" id="delay-scripts">
			const loadScriptsTimer=setTimeout(loadScripts,5000);
			const userInteractionEvents=["mouseover","keydown","touchstart","touchmove","wheel"];
			userInteractionEvents.forEach(function(event) {	
				window.addEventListener(event, triggerScriptLoader, {passive:!0})});
				function triggerScriptLoader() {
					loadScripts();
					clearTimeout(loadScriptsTimer);
					userInteractionEvents.forEach(function(event) {
						window.removeEventListener(event, triggerScriptLoader, {passive:!0})
					})
				}
			function loadScripts() {
				setTimeout(function() { document.querySelectorAll("[data-loading='delay']").forEach(function(elem) { elem.setAttribute("src", elem.getAttribute("data-src")) }) }, 4000);
			}
		</script><?php
	}
}

// Hide the Wordpress admin bar
show_admin_bar( false );

// Set cookie for new home page url if user views an 'optimized' page
add_action( 'wp', 'battleplan_setHomeBtnCookie' );
function battleplan_setHomeBtnCookie() {
	if ( get_post_type() == "optimized" ) :
		$homeURL = $_SERVER['REQUEST_URI'];
		//setcookie('home-url', $homeURL, '', '/', '', true, false);
		writeCookie('home-url', $homeURL, '0');
	endif;
}

// Set up Main Menu
class Aria_Walker_Nav_Menu extends Walker_Nav_Menu {
	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		$classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-'.$item->ID.' menu-item-'.strtolower(str_replace(" ", "-", $item->title));

		$args = apply_filters( 'nav_menu_item_args', $args, $item, $depth );

		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );
		$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

		$id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args, $depth );
		$id = $id ? ' id="' . esc_attr( $id ) . '"' : '';
		
		$buildOutput = "";		
		$restrictMax = readMeta( $item->ID, 'bp_menu_restrict_max', true );
		$restrictMin = readMeta( $item->ID, 'bp_menu_restrict_min', true );		
		if ( $restrictMax || $restrictMin ) $buildOutput .= '[restrict max="'.$restrictMax.'" min="'.$restrictMin.'"]';

		$buildOutput .= sprintf( '%s<li%s%s%s>',
			$indent,
			$id,
			$class_names,
			in_array( 'menu-item-has-children', $item->classes ) ? ' aria-haspopup="true" aria-expanded="false" tabindex="0"' : ''
		);
		
		$atts = array();
		$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
		$atts['target'] = ! empty( $item->target )     ? $item->target     : '';
		$atts['rel']    = ! empty( $item->xfn )        ? $item->xfn        : '';
		$atts['href']   = ! empty( $item->url )        ? $item->url        : '';

		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				if ( $attr === 'href' ) :
					if ( ( isset($_COOKIE['home-url']) || $homeURL != '' ) && ( $value == get_home_url() || $value == get_home_url().'/' )) :
						$value = esc_url( str_replace('//', '/', $_COOKIE['home-url']) );
					else :
						$value = esc_url( $value );
					endif;
				else :
					$value = esc_attr( $value );
				endif;

				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

		$item_output = $args->before;		
		$item_output .= '<a'. $attributes .'>';
		$item_output .= $args->link_before . $title . $args->link_after;
		$item_output .= '</a>';
		$item_output .= $args->after;
		
		$buildOutput .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
		if ( $restrictMax || $restrictMin ) $buildOutput .= '[/restrict]';	
		
		$output .= do_shortcode($buildOutput);
	}
}

// Set up spam filter for Contact Form 7 emails
add_filter( 'wpcf7_validate_textarea', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_text', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_text*', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_email', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_email*', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_tel', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_tel*', 'battleplan_contact_form_spam_blocker', 20, 2 );
function battleplan_contact_form_spam_blocker( $result, $tag ) {
    if ( "user-message" == $tag->name ) {
		$check = isset( $_POST["user-message"] ) ? trim( $_POST["user-message"] ) : ''; 
		$name = isset( $_POST["user-name"] ) ? trim( $_POST["user-name"] ) : ''; 
		$badwords = array('Pandemic Recovery','bitcoin','mаlwаre','antivirus','marketing','SEO','Wordpress','Chiirp','@Getreviews','Cost Estimation','Guarantee Estimation','World Wide Estimating','Postmates delivery','health coverage plans','loans for small businesses','New Hire HVAC Employee','SO BE IT','profusa hydrogel','Divine Gatekeeper','witchcraft powers','I will like to make a inquiry','Mark Of The Beast','fuck','dogloverclub.store','Getting a Leg Up','ultimate smashing machine','Get more reviews, Get more customers','We write the reviews','write an article','a free article','relocation checklist','Rony (Steve', 'Your company Owner','We are looking forward to hiring an HVAC contracting company','keyword targeted traffic','downsizing your living space','Roleplay helps develop','rank your google','TRY IT RIGHT NOW FOR FREE','house‌ ‌inspection‌ ‌process', 'write you an article','и','д','б','й','л','ы','З','у','Я');
		$webwords = array('.com','http://','https://','.net','.org','www.','.buzz');
		if ( strtolower($check) == strtolower($name) ) $result->invalidate( $tag, 'Message cannot be sent.' );
		foreach($badwords as $badword) {
			if (stripos(strtolower($check),strtolower($badword)) !== false) $result->invalidate( $tag, 'Message cannot be sent.' );
		}
		foreach($webwords as $webword) {
			if (stripos(strtolower($check),strtolower($webword)) !== false) $result->invalidate( $tag, 'We do not accept messages containing website addresses.' );
		}		
	}
    if ( "user-phone" == $tag->name ) {
        $check = isset( $_POST["user-phone"] ) ? trim( $_POST["user-phone"] ) : ''; 
		$badnumbers = array('1234567');
		foreach($badnumbers as $badnumber) {
			if (stripos($check,$badnumber) !== false) $result->invalidate( $tag, 'Message cannot be sent.');
		}
	}
    if ( "user-email" == $tag->name ) {
        $check = isset( $_POST["user-email"] ) ? trim( $_POST["user-email"] ) : ''; 
		$badwords = array('testing.com', 'test@', 'b2blistbuilding.com', 'amy.wilsonmkt@gmail.com', '@agency.leads.fish', 'landrygeorge8@gmail.com', '@digitalconciergeservice.com', '@themerchantlendr.com', '@fluidbusinessresources.com', '@focal-pointcoaching.net', '@zionps.com', '@rddesignsllc.com', '@domainworld.com', 'marketing.ynsw@gmail.com', 'seoagetechnology@gmail.com', '@excitepreneur.net', '@bullmarket.biz', '@tworld.com', 'garywhi777@gmail.com', 'ronyisthebest16@gmail.com', 'ronythomas611@gmail.com', 'ronythomasrecruiter@gmail.com', '@ideonagency.net', 'axiarobbie20@gmail.com', '@hyper-tidy.com', '@readyjob.org', '@thefranchisecreatornetwork.com');
		foreach($badwords as $badword) {
			if (stripos(strtolower($check),strtolower($badword)) !== false) $result->invalidate( $tag, 'Message cannot be sent.');
		}
	}
    if ( 'user-email-confirm' == $tag->name ) {
        $user_email = isset( $_POST['user-email'] ) ? trim( $_POST['user-email'] ) : '';
        $user_email_confirm = isset( $_POST['user-email-confirm'] ) ? trim( $_POST['user-email-confirm'] ) : '';
        if ( $user_email != $user_email_confirm ) $result->invalidate( $tag, "Are you sure this is the correct email?" );
    } 
    return $result;
}

// Contact Form 7 email - format phone numbers
add_filter( 'wpcf7_posted_data', 'battleplan_formatMail', 10, 1 ); 
function battleplan_formatMail( $posted_data ) { 
	foreach ($posted_data as $key => $value) :
		$types = array ('phone', 'cell', 'mobile', 'fax');
		foreach ($types as $type) :
			if ( strpos( $key, $type ) !== FALSE) :
				$phone = preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $posted_data[$key]). "\n";
				$posted_data[$key] = $phone;
			endif;
		endforeach;
	endforeach;		
    return $posted_data;	
}; 

// Contact Form 7 email - format other fields
add_action( 'wpcf7_before_send_mail', 'battleplan_setupFormEmail', 10, 1 ); 
function battleplan_setupFormEmail( $contact_form ) { 
	$formMail = $contact_form->prop( 'mail' );
	$userLoc = $_COOKIE['user-loc'];
	$userViews = $_COOKIE['pages-viewed'];
	if ( $userViews == 1 ) : $userViews = "1 page"; else: $userViews = $userViews." pages"; endif;
	$userAgent = $_SERVER['HTTP_USER_AGENT'];
	$userDevice = is_mobile() ? "a mobile device" : "a desktop";
	if ( strpos($userAgent, "Mac") ) $userDevice = "a Mac";
	if ( strpos($userAgent, "iPod") ) $userDevice = "an iPod";
	if ( strpos($userAgent, "iPad") ) $userDevice = "an iPad";
	if ( strpos($userAgent, "iPhone") ) $userDevice = "an iPhone";
	if ( strpos($userAgent, "Android") ) $userDevice = "an Android";
	if ( strpos($userAgent, "iOS") ) $userSystem = " running iOS";
	if ( strpos($userAgent, "Windows") ) $userSystem = " running Windows";	
	if ( strpos($userAgent, "Linux") ) $userSystem = " running Linux";

	// filter recipient		
	if ( strpos($formMail['recipient'], "get-biz") !== false ) $formMail['recipient'] = do_shortcode($formMail['recipient']);
	
	// filter body
	$bodyEls = explode("\n", $formMail['body']);	
	$buildEmail = '<div style="line-height:1.5"><p><b style="font-size:130%">'.substr($formMail['subject'], 0, strpos($formMail['subject'], " · ")).'</b></p><p>';	
	
	$maxLength = 0;		
	foreach ( $bodyEls as $bodyEl ) :
		$elParts = explode("[", $bodyEl);
		if ( $elParts[0] && $elParts[1] ) : if ( strlen($elParts[0]) > $maxLength ) : $maxLength = strlen($elParts[0]); endif; endif;
	endforeach;	
	$colWidth = round($maxLength * 8);
	
	foreach ( $bodyEls as $bodyEl ) :
		$elParts = explode("[", $bodyEl);
		if ( $elParts[0] && $elParts[1] ) $buildEmail .= '<span style="display:inline-block; width:'.$colWidth.'px; style="font-size:87%"><em><b>'.$elParts[0].'</b></em></span>';
		if ( $elParts[0] && !$elParts[1] ) $buildEmail .= '<span style="display:inline-block; width:100%; style="font-size:87%"><em><b>'.$elParts[0].'</b></em></span>';
		if ( $elParts[0] && $elParts[1] ) $buildEmail .= '<span>['.$elParts[1].'</span>';		
		if ( !$elParts[0] && $elParts[1] ) $buildEmail .= '<span>['.$elParts[1].'</span>';
		$buildEmail .= '<br/>';
	endforeach;
	
	$buildEmail .= '</p></div><div style="line-height:1.5; border-top: 1px solid #8a8a8a; color: #8a8a8a; margin-top:5em;"><p>Sent from the <em>'.get_the_title(url_to_postid($_SERVER['HTTP_REFERER'])).'</em> page on the website.</p>';	
	
	$buildEmail .= '<p>Sender viewed';
	if ( $_COOKIE['pages-viewed'] ) $buildEmail .= ' '.$userViews;
	$buildEmail .= ' using '.$userDevice.$userSystem;
	if ( $userLoc ) $buildEmail .= ' near '.$userLoc;
	$buildEmail .= '.<br/>';
	$buildEmail .= '<em>Sender IP:</em> <a style="text-decoration:none; color:#8a8a8a;" href="https://whatismyipaddress.com/ip/'.$_SERVER["REMOTE_ADDR"].'">'.$_SERVER["REMOTE_ADDR"].'</a><br/>';
	//$buildEmail .= $_SERVER["HTTP_USER_AGENT"].'</p>';
	
	$formMail['body'] = $buildEmail;
	
	// send email
	$contact_form->set_properties( array( 'mail' => $formMail ) );
}; 	
         
// Block loading of refill file (Contact Form 7) to help speed up sites
add_action('wp_footer', 'battleplan_no_contact_form_refill', 99); 
function battleplan_no_contact_form_refill() { 
	if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {	
		?><script nonce="<?php echo $GLOBALS['nonce']; ?>">wpcf7.cached = 0;</script><?php	
	}
}

// Remove auto <p> from around <img> & <svg>
add_filter('the_content', 'battleplan_remove_ptags_on_images', 9999);
function battleplan_remove_ptags_on_images($content){
   $content = preg_replace('/<p>\s*(<a .*>)?\s*(<img .* \/>)\s*(<\/a>)?\s*<\/p>/iU', '\1\2\3', $content);
   $content = preg_replace('/<p>\s*(<svg .*>)?\s*(<\/svg>)?\s*<\/p>/iU', '\1\2\3', $content);   
   $content = preg_replace('/<p>\s*(<label .*>)?\s*(<\/span>)?\s*<\/p>/iU', '\1\2\3', $content);
   return $content;   
}

// Remove auto <p> from inside widgets 
remove_filter('widget_text_content', 'wpautop'); 

// Remove auto <br> from inside forms 
add_filter('wpcf7_autop_or_not', '__return_false');

// Enable Shortcodes in Text Widgets
add_filter('widget_text','do_shortcode');

// Set up sizes for srcset
function get_srcset( $size ) {	
	$ratio1280 = ($size / 1280) * 100; 
	if ( $ratio1280 <= 40 ) : $ratio1280 = 40;
	elseif ( $ratio1280 <= 75 ) : $ratio1280 = 60;
	else: $ratio1280 = 100; endif;
	
	$ratio1024 = ($size / 1024) * 100; 
	if ( $ratio1024 <= 40 ) : $ratio1024 = 33;
	elseif ( $ratio1024 <= 75 ) : $ratio1024 = 50;
	else: $ratio1024 = 100; endif;
	
	$ratio860 = ($size / 860) * 100; 
	if ( $ratio860 <= 33 ) : $ratio860 = 50;
	else: $ratio860 = 100; endif;
	
	$ratio575 = ($size / 575) * 100; 
	if ( $ratio575 <= 25 ) : $ratio575 = 50;
	else: $ratio575 = 100; endif;

	return '(max-width: 575px) '.$ratio575.'vw, (max-width: 860px) '.$ratio860.'vw, (max-width: 1024px) '.$ratio1024.'vw, (max-width: 1280px) '.$ratio1280.'vw, '.$size.'px';
}

//Establish default thumbnail size
update_option( 'thumbnail_size_w', 320 );
update_option( 'thumbnail_size_h', 320 );
update_option( 'thumbnail_crop', 1 );

// Establish default image sizes
if ( function_exists( 'add_image_size' ) ) {
	add_image_size( 'thumbnail-small', 80, 80, true ); 
	add_image_size( 'icon', 80, 80, false ); 
	add_image_size( 'icon-2x', 160, 160, false ); 
	add_image_size( 'quarter-s', 240, 99999, false ); 
	add_image_size( 'third-s', 320, 99999, false ); 	
	add_image_size( 'half-s', 480, 99999, false ); 
	add_image_size( 'full-s', 960, 99999, false ); 
	add_image_size( 'quarter-f', 320, 99999, false ); 
	add_image_size( 'third-f', 430, 99999, false ); 
	add_image_size( 'half-f', 640, 99999, false ); 
	add_image_size( 'full-f', 1280, 99999, false ); 
	add_image_size( 'max', 1920, 99999, false ); 
	add_image_size( 'third-f-2x', 800, 99999, false ); 
}

add_filter('image_size_names_choose', 'battleplan_image_sizes');
function battleplan_image_sizes($sizes) {
	$new_sizes = array(
		"quarter-s"=>__( "Sidebar 25%"), 
		"third-s"=>__( "Sidebar 33%"),		
		"half-s"=>__( "Sidebar 50%"), 				
		"full-s"=>__( "Sidebar 100%"), 				
		"quarter-f"=>__( "Full 25%"), 		
		"third-f"=>__( "Full 33%"), 		
		"half-f"=>__( "Full 50%"), 		
		"full-f"=>__( "Full 100%"), 		
		"third-f-2x"=>__( "Extra"), 		
		"max"=>__( "Max"), 		
	);
	return $new_sizes;
}

add_action( 'init', 'battleplan_remove_image_sizes', 99999 );
function battleplan_remove_image_sizes() {
	update_option( 'medium_size_h', 0 );
	update_option( 'medium_size_w', 0 );
	update_option( 'medium_large_size_h', 0 );
	update_option( 'medium_large_size_w', 0 );
	update_option( 'large_size_h', 0 );
	update_option( 'large_size_w', 0 );
}

// Set new max "content width" param - which fixes the false image sizes in media attachments
add_action( 'after_setup_theme', 'battleplan_set_max_width_for_img', 11 );
function battleplan_set_max_width_for_img() {
	$GLOBALS['content_width'] = 1920;
}

// Set new max srcset image 
add_filter( 'max_srcset_image_width', 'battleplan_remove_max_srcset_image_width' );
function battleplan_remove_max_srcset_image_width( $max_width ) {
	$max_width = 1920;
	return $max_width;
}

// Set the size param in srcset image
add_filter('wp_calculate_image_sizes', 'battleplan_content_image_sizes_attr', 10 , 2);
function battleplan_content_image_sizes_attr($sizes, $size) {
	return get_srcset($size[0]);
}

// Selective disabling of lazy load parameter
add_filter( 'wp_img_tag_add_loading_attr', 'battleplan_disableLazyLoad', 99, 3 );
function battleplan_disableLazyLoad( $value, $image, $context ) {
	if ( strpos( $image, 'logo' ) !== false ) { return false; }
	return $value;
}

// Add attachment ID to attached images as 'data-id'
add_filter( 'wp_get_attachment_image_attributes', 'battleplan_attachment_id_on_images', 20, 2 );
function battleplan_attachment_id_on_images( $attr, $attachment ) {
	$attr['data-id'] = $attachment->ID;
	return $attr;
}

// Do not resize animated .gif 
add_filter('intermediate_image_sizes_advanced', 'battleplan_disable_upload_sizes', 10, 2); 
function battleplan_disable_upload_sizes( $sizes, $metadata ) {
    $filetype = wp_check_filetype($metadata['file']);
    if($filetype['type'] == 'image/gif') { $sizes = array(); }
    return $sizes;
}   

add_filter('big_image_size_threshold', 'battleplan_limit_non_admin_uploads', 999, 1);
function battleplan_limit_non_admin_uploads( $threshold ) {
    if ( ! current_user_can( 'manage_options' ) ) {
		return 1000;
	}
}

// Highlights menu option based on the post type of the current page and the title attribute given to the menu button in Appearance->Menus
add_filter('nav_menu_css_class', 'battleplan_current_type_nav_class', 10, 2 );
function battleplan_current_type_nav_class($classes, $item) {
	$post_type = get_post_type();
	if ( $post_type != 'post' ) :
		$classes = str_replace( 'current_page_parent', '', $classes );
		if ( $item->url == '/'.$post_type ) : $classes = str_replace( 'menu-item', 'menu-item current_page_parent', $classes ); endif;
	endif;
	
	if ($item->attr_title != '' && $item->attr_title == $post_type) { 
		array_push($classes, 'current-menu-item');
	};
	
	// Highlight HOME button if any of the Optimized pages are viewed
	if ( $post_type == 'optimized' && ( $item->url == get_home_url() || $item->url == get_home_url().'/' )) :
		array_push($classes, 'current-menu-item');		
	endif;
	
	// Support for The Events Calendar PRO - plug-in
	
	if ( ($item->attr_title == "tribe_events" || $item->attr_title == "events" ) && (strpos($_SERVER['REQUEST_URI'], '/event/') !== false || strpos($_SERVER['REQUEST_URI'], '/events/') !== false) ) :
		array_push($classes, 'current-menu-item');		
	endif;
	
	return $classes;
}

// Rename "Uncategorized" posts to "Blog"
wp_update_term(1, 'category', array( 'name'=>'Blog', 'slug'=>'blog' ));

// Cap auto generated excerpts at 1 or 2 sentences, based on length
add_filter( 'excerpt_length', 'battleplan_excerpt_length', 999 );
function battleplan_excerpt_length( $length ) { 
	return 300; 
} 
add_filter('get_the_excerpt', 'end_with_sentence');
function end_with_sentence( $excerpt ) {	
	if ( !has_excerpt() ) :		
		$sentences = preg_split( "/(\.|\!|\?)/", $excerpt, NULL, PREG_SPLIT_DELIM_CAPTURE);
		$newExcerpt = implode('', array_slice($sentences, 0, 4));	
		if ( strlen($newExcerpt) > 200 ) $newExcerpt = implode('', array_slice($sentences, 0, 2));

		return $newExcerpt;
	else: return $excerpt; endif;
}

// Add Top & Bottom textareas for pages
$pageTopMeta = new Metabox_Constructor(array( 'id' => 'page-top', 'title' => 'Page Top', 'screen' => 'page', 'context' => 'normal', 'priority' => 'high' ));
$pageTopMeta->addWysiwyg(array( 'id' => 'page-top_text', 'label' => '' ));
$pageBottomMeta = new Metabox_Constructor(array( 'id' => 'page-bottom', 'title' => 'Page Bottom', 'screen' => 'page', 'context' => 'normal', 'priority' => 'high' ));
$pageBottomMeta->addWysiwyg(array( 'id' => 'page-bottom_text', 'label' => '' ));
$optimizedTopMeta = new Metabox_Constructor(array( 'id' => 'page-top', 'title' => 'Page Top', 'screen' => 'optimized', 'context' => 'normal', 'priority' => 'high' ));
$optimizedTopMeta->addWysiwyg(array( 'id' => 'page-top_text', 'label' => '' ));
$optimizedBottomMeta = new Metabox_Constructor(array( 'id' => 'page-bottom', 'title' => 'Page Bottom', 'screen' => 'optimized', 'context' => 'normal', 'priority' => 'high' ));
$optimizedBottomMeta->addWysiwyg(array( 'id' => 'page-bottom_text', 'label' => '' ));


// Display Google review rating
add_action('wp_footer', 'battleplan_getGoogleRating');
function battleplan_getGoogleRating() {
	$placeID = do_shortcode('[get-biz info="pid"]');	
	if ( $placeID != '' ) :
		$apiKey = "AIzaSyBqf0idxwuOxaG";
		$apiKey .= "-j3eCpef1Bunv";
		$apiKey .= "-YVdVP8";	
		$dateChecked = readMeta(_HEADER_ID, "google-review-date");	
		$today = strtotime(date("F j, Y"));	
		$daysSinceCheck = $today - $dateChecked;

		if ( $daysSinceCheck < 6 ) :
			$rating = readMeta(_HEADER_ID, "google-review-rating");	
			$number = readMeta(_HEADER_ID, "google-review-number");		
		else:	
			$url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=".$placeID."&key=".$apiKey;
			$ch = curl_init();
			curl_setopt ($ch, CURLOPT_URL, $url);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			$result = curl_exec ($ch);
			$res = json_decode($result,true);
			$rating = $res['result']['rating'];	
			$number = $res['result']['user_ratings_total'];	
			updateMeta( _HEADER_ID, "google-review-rating", $rating );	
			updateMeta( _HEADER_ID, "google-review-number", $number );	
			updateMeta( _HEADER_ID, "google-review-date", $today );
			$dateChecked = $today;
		endif;

		if ( $rating > 3.99 ) :
			$buildPanel = '<a class="wp-gr wp-google-badge" href="https://search.google.com/local/reviews?placeid='.$placeID.'&hl=en&gl=US" target="_blank">';
			$buildPanel .= '<div class="wp-google-border"></div>';
			$buildPanel .= '<div class="wp-google-badge-btn">';
			$buildPanel .= '<div class="wp-google-badge-score wp-google-rating" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">';
			$buildPanel .= '<svg role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" height="44" width="44"><title>Google Logo</title><g fill="none" fill-rule="evenodd">';
			$buildPanel .= '<path d="M482.56 261.36c0-16.73-1.5-32.83-4.29-48.27H256v91.29h127.01c-5.47 29.5-22.1 54.49-47.09 71.23v59.21h76.27c44.63-41.09 70.37-101.59 70.37-173.46z" fill="#4285f4"></path>';
			$buildPanel .= '<path d="M256 492c63.72 0 117.14-21.13 156.19-57.18l-76.27-59.21c-21.13 14.16-48.17 22.53-79.92 22.53-61.47 0-113.49-41.51-132.05-97.3H45.1v61.15c38.83 77.13 118.64 130.01 210.9 130.01z" fill="#34a853"></path>';
			$buildPanel .= '<path d="M123.95 300.84c-4.72-14.16-7.4-29.29-7.4-44.84s2.68-30.68 7.4-44.84V150.01H45.1C29.12 181.87 20 217.92 20 256c0 38.08 9.12 74.13 25.1 105.99l78.85-61.15z" fill="#fbbc05"></path>';
			$buildPanel .= '<path d="M256 113.86c34.65 0 65.76 11.91 90.22 35.29l67.69-67.69C373.03 43.39 319.61 20 256 20c-92.25 0-172.07 52.89-210.9 130.01l78.85 61.15c18.56-55.78 70.59-97.3 132.05-97.3z" fill="#ea4335"></path>';
			$buildPanel .= '<path d="M20 20h472v472H20V20z"></path>';
			$buildPanel .= '</g></svg>';
			$buildPanel .= '<div data-as-of="'.$dateChecked.'" class="wp-google-value" itemprop="ratingValue">'.number_format($rating, 1, '.', ',').'</div>';
			$buildPanel .= '<div class="wp-google-stars">';

			if ( $rating >= 4.7) $buildPanel .= '<span class="rating" aria-hidden="true"><span class="sr-only">Rated '.number_format($rating, 1, '.', ',').' Stars</span><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i></span>';	
			if ( $rating >= 4.2 && $rating <= 4.6 ) $buildPanel .= '<span class="rating" aria-hidden="true"><span class="sr-only">Rated '.number_format($rating, 1, '.', ',').' Stars</span><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star-half-alt"></i></span>';
			if ( $rating >= 3.7 && $rating <= 4.1 ) $buildPanel .= '<span class="rating" aria-hidden="true"><span class="sr-only">Rated '.number_format($rating, 1, '.', ',').' Stars</span><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa far fa-star"></i></span>';		
			if ( $rating >= 3.2 && $rating <= 3.6 ) $buildPanel .= '<span class="rating" aria-hidden="true"><span class="sr-only">Rated '.number_format($rating, 1, '.', ',').' Stars</span><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star-half-alt"></i><i class="fa far fa-star"></i></span>';

			$buildPanel .= '</div></div>';	
			$buildPanel .= '<div class="wp-google-total">Click to view our ';			
			$buildPanel .= '<span itemprop="reviewCount">'.number_format($number).'</span>';			
			$buildPanel .= ' Google reviews!</div>';	
			$buildPanel .= '</div></a>';

			echo $buildPanel;
		endif;
	endif;
}

// Set up URL re-directs
if ( _PAGE_SLUG == "facebook" && do_shortcode('[get-biz info="facebook"]') != "" ) : 
	$facebook = do_shortcode('[get-biz info="facebook"]');
	if ( substr($facebook, -1) != '/') $facebook .= "/";
	wp_redirect( $facebook."reviews/", 301 ); 
	exit; 
endif;
if ( _PAGE_SLUG == "google" && do_shortcode('[get-biz info="pid"]') != "" ) : 
	wp_redirect( "https://search.google.com/local/reviews?placeid=".do_shortcode('[get-biz info="pid"]')."&hl=en&gl=US", 301 ); 
	exit; 
endif;
if ( _PAGE_SLUG == "reviews" ) : 
	wp_redirect( "/review/", 301 ); 
	exit; 
endif;

function battleplan_redirect_to_url($url, $redirect) {
	$currPage = str_replace('/', '', $_SERVER['REQUEST_URI']);
	if ( $currPage == $url ) :
		wp_redirect( $redirect, 301 ); 
		exit;
	endif;
}

/*--------------------------------------------------------------
# User Roles
--------------------------------------------------------------*/
function battleplan_remove_user_roles() {
	if ( wp_roles()->is_role( 'editor' ) ) remove_role( 'editor' );
	if ( wp_roles()->is_role( 'contributor' ) ) remove_role( 'contributor' );
	if ( wp_roles()->is_role( 'author' ) ) remove_role( 'author' );
	if ( wp_roles()->is_role( 'subscriber' ) ) remove_role( 'subscriber' );
	if ( wp_roles()->is_role( 'wpseo_manager' ) ) remove_role( 'wpseo_manager' );
	if ( wp_roles()->is_role( 'wpseo_editor' ) ) remove_role( 'wpseo_editor' );	
}

function battleplan_create_user_roles() {	
	remove_role( 'bp_manager' );
	add_role('bp_manager', __('Manager'), array( 'level_8'=>'true', 'level_7'=>'true', 'level_6'=>'true', 'level_5'=>'true', 'level_4'=>'true', 'level_3'=>'true', 'level_2'=>'true', 'level_1'=>'true', 'level_0'=>'true', 'read'=>'true', 'publish_pages'=>'true', 'edit_pages'=>'true', 'edit_others_pages'=>'true', 'edit_published_pages'=>'true', 'read_private_pages'=>'true', 'edit_private_pages'=>'true', 'delete_pages'=>'true', 'delete_others_pages'=>'true', 'delete_published_pages'=>'true', 'delete_private_pages'=>'true', 'publish_posts'=>'true', 'edit_posts'=>'true', 'edit_others_posts'=>'true', 'edit_published_posts'=>'true', 'read_private_posts'=>'true', 'edit_private_posts'=>'true', 'delete_posts'=>'true', 'delete_others_posts'=>'true', 'delete_published_posts'=>'true', 'delete_private_posts'=>'true', 'moderate_comments'=>'true', 'manage_categories'=>'true', 'manage_links'=>'true', 'upload_files'=>'true', 'unfiltered_html'=>'true' ));	
	
	remove_role( 'bp_moderator' );
	add_role('bp_moderator', __('Moderator'), array( 'level_4'=>'true', 'level_3'=>'true', 'level_2'=>'true', 'level_1'=>'true', 'level_0'=>'true', 'read'=>'true', 'publish_posts'=>'true', 'edit_posts'=>'true', 'edit_others_posts'=>'true', 'edit_published_posts'=>'true', 'read_private_posts'=>'true', 'edit_private_posts'=>'true', 'delete_posts'=>'true', 'delete_others_posts'=>'true', 'delete_published_posts'=>'true', 'delete_private_posts'=>'true', 'moderate_comments'=>'true', 'manage_categories'=>'true', 'manage_links'=>'true', 'upload_files'=>'true', 'unfiltered_html'=>'true' ));	
	
	remove_role( 'bp_author' );
	add_role('bp_author', __('Author'), array( 'level_2'=>'true', 'level_1'=>'true', 'level_0'=>'true', 'read'=>'true', 'publish_posts'=>'true', 'edit_posts'=>'true', 'edit_published_posts'=>'true', 'delete_posts'=>'true', 'delete_published_posts'=>'true', 'upload_files'=>'true', 'unfiltered_html'=>'true' ));	
	
	remove_role( 'bp_subscriber' );
	add_role('bp_subscriber', __('Subscriber'), array( 'level_1'=>'true', 'level_0'=>'true', 'read'=>'true' ));	
}

//add_action('init', 'battleplan_getAndDisplayUserRoles');
function battleplan_getAndDisplayUserRoles() {	
	$caps = get_option('wp_user_roles');	
	print_r( $caps );
}

/*--------------------------------------------------------------
# Chron Jobs
--------------------------------------------------------------*/
add_action('init', 'battleplan_doChrons');
function battleplan_doChrons() {
	$chronSpan = 3 * (24 * 60 * 60);
	$bpChrons = get_option( 'bp_chrons_last_run' );	
	$timePast = time() - $bpChrons;
		
	if ( $timePast > $chronSpan || get_option('bp_setup_2021_12_20') != "completed" ) :	
		require_once get_template_directory().'/functions-chron-jobs.php';	
	endif;	
}

/*--------------------------------------------------------------
# Custom Hooks
--------------------------------------------------------------*/
function bp_loader() { do_action('bp_loader'); }
function bp_font_loader() { do_action('bp_font_loader'); }
function bp_google_tag_manager() { do_action('bp_google_tag_manager'); }
function bp_mobile_menu_bar_items() { do_action('bp_mobile_menu_bar_items'); }
function bp_mobile_menu_bar_scroll() { do_action('bp_mobile_menu_bar_scroll'); }
function bp_mobile_menu_bar_phone() { do_action('bp_mobile_menu_bar_phone'); }
function bp_mobile_menu_bar_contact() { do_action('bp_mobile_menu_bar_contact'); }
function bp_mobile_menu_bar_activate() { do_action('bp_mobile_menu_bar_activate'); }
function bp_before_page() { do_action('bp_before_page'); }
function bp_before_masthead() { do_action('bp_before_masthead'); }
function bp_masthead() { do_action('bp_masthead'); }
function bp_after_masthead() { do_action('bp_after_masthead'); }
function bp_wrapper_top() { do_action('bp_wrapper_top'); }
function bp_before_wrapper_content() { do_action('bp_before_wrapper_content'); }
function bp_after_wrapper_content() { do_action('bp_after_wrapper_content'); }
function bp_before_main_content() { do_action('bp_before_main_content'); }
function bp_after_main_content() { do_action('bp_after_main_content'); }
function bp_before_site_main_inner() { do_action('bp_before_site_main_inner'); }
function bp_after_site_main_inner() { do_action('bp_after_site_main_inner'); }
function bp_before_the_content() { do_action('bp_before_the_content'); }
function bp_after_the_content() { do_action('bp_after_the_content'); }
function bp_before_sidebar_inner() { do_action('bp_before_sidebar_inner'); }
function bp_after_sidebar_inner() { do_action('bp_after_sidebar_inner'); }
function bp_before_sidebar_widgets() { do_action('bp_before_sidebar_widgets'); }
function bp_after_sidebar_widgets() { do_action('bp_after_sidebar_widgets'); }
function bp_before_colophon() { do_action('bp_before_colophon'); }
function bp_after_colophon() { do_action('bp_after_colophon'); }

/*--------------------------------------------------------------
# Custom Actions
--------------------------------------------------------------*/

// Preload fonts
add_action('bp_font_loader', 'battleplan_loadFonts');
function battleplan_loadFonts() {
	foreach ( $GLOBALS['customer_info']['site-fonts'] as $siteFont ) :
		if ( $siteFont != "" ) $buildPreload .= '<link rel="preload" as="font" type="font/woff2" href="'.get_site_url().'/wp-content/themes/battleplantheme-site/fonts/'.$siteFont.'.woff2" crossorigin="anonymous">';
	endforeach;
	echo $buildPreload;
}

// Install Google Global Site Tags
add_action('bp_google_tag_manager', 'battleplan_load_tag_manager');
function battleplan_load_tag_manager() { 
	$nonce = $GLOBALS['nonce'];
	foreach ( $GLOBALS['customer_info']['google-tags'] as $gtag=>$value ) :	
		if ( $gtag == "analytics" ) $mainAcct = $value;
		if ( $gtag == "analytics" || $gtag == "ads" ) $buildTags .= 'gtag("config", "'.$value.'");';
		if ( $gtag == "conversions" ) $gtagEvents[] = $value; 
	endforeach;
	
	$buildTagMgr .= '<script nonce="'.$nonce.'" async src="https://www.googletagmanager.com/gtag/js?id='.$mainAcct.'"></script>';
	$buildTagMgr .= '<script nonce="'.$nonce.'" async>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag("js", new Date());';
	$buildTagMgr .= $buildTags;
	$buildTagMgr .= '</script>';
	
	if ( $gtagEvents ) :
		foreach ( $gtagEvents as $gtagEvent ) :	
			$buildEvents .= "gtag('event', 'conversion', { 'send_to': '".$gtagEvent."', 'event_callback': callback });";  
		endforeach;
	
		$buildTagMgr .= '<script nonce="'.$nonce.'">
			function gtag_report_conversion(url) {
  			var callback = function () {
				if (typeof(url) != "undefined") {
					window.location = url;
				}
  			};';
		$buildTagMgr .= $buildEvents;
		$buildTagMgr .= 'return false; }</script>';
	endif;
	
	if (strpos($mainAcct, 'x') === false) echo $buildTagMgr;	
}

// Build and display desktop navigation menu
function buildNavMenu( $pos ) {
	$printMenu .= '<nav id="desktop-navigation" class="main-navigation menu-strip" aria-label="Main Menu">';
	$printMenu .= wp_nav_menu ( array ( 'echo' => false, 'container' => 'div', 'container_class' => 'flex', 'menu_id' => $pos.'-menu', 'menu_class' => 'menu main-menu', 'theme_location' => $pos.'-menu', 'walker' => new Aria_Walker_Nav_Menu(), ) ); 
	$printMenu .= '</nav><!-- #site-navigation -->';
	
	return $printMenu;
}

function placeNavMenu() {
	echo buildNavMenu( 'manual' );
}

add_shortcode( 'get-menu', 'returnNavMenu' );
function returnNavMenu() {
	return buildNavMenu( 'manual' );
}		

// Display Mobile Menu Bar Item - Scroll
add_action('bp_mobile_menu_bar_scroll', 'battleplan_mobile_menu_bar_scroll', 20);
function battleplan_mobile_menu_bar_scroll() { 
	echo '<a class="scroll-top" href="#page"><div class="mm-bar-btn mm-bar-scroll scroll-to-top-btn" aria-hidden="true"></div><span class="sr-only">Scroll To Top</span></a>';
}

// Display Mobile Menu Bar Item - Phone
add_action('bp_mobile_menu_bar_phone', 'battleplan_mobile_menu_bar_phone', 20);
function battleplan_mobile_menu_bar_phone() { 
	echo do_shortcode('[get-biz info="mm-bar-phone"]');	
}

// Display Mobile Menu Bar Item - Contact
add_action('bp_mobile_menu_bar_contact', 'battleplan_mobile_menu_bar_contact', 20);
function battleplan_mobile_menu_bar_contact() { 
	if ( get_page_by_title( 'Quote Request Form', OBJECT, 'wpcf7_contact_form' ) ) : $form = "Quote Request Form"; $title = "Request A Quote"; $type = "quote";
	elseif ( get_page_by_title( 'Contact Us Form', OBJECT, 'wpcf7_contact_form' ) ) : $form = "Contact Us Form"; $title = "Send A Message"; $type = "contact"; endif;	
	if ( $form && $title ) echo '<div class="mm-bar-btn mm-bar-'.$type.' modal-btn"><div class="email-btn" aria-hidden="true"></div><div class="email2-btn" aria-hidden="true"></div><span class="sr-only">Contact Us</span></div>';	
}

// Display Request Quote Modal
add_action('bp_before_page', 'battleplan_request_quote_modal', 20);
function battleplan_request_quote_modal() { 
	if ( get_page_by_title( 'Quote Request Form', OBJECT, 'wpcf7_contact_form' ) ) : $form = "Quote Request Form"; $title = "Request A Quote";
	elseif ( get_page_by_title( 'Contact Us Form', OBJECT, 'wpcf7_contact_form' ) ) : $form = "Contact Us Form"; $title = "Send A Message"; endif;	
	if ( $form && $title ) echo do_shortcode('[lock name="request-quote-modal" style="lock" position="modal" show="always" btn-activated="yes"][layout]<h3>'.$title.'</h3>[contact-form-7 title="'.$form.'"][/layout][/lock]');
}

// Display Mobile Menu Bar Item - Activate
add_action('bp_mobile_menu_bar_activate', 'battleplan_mobile_menu_bar_activate');
function battleplan_mobile_menu_bar_activate() { 
	echo '<div class="mm-bar-btn mm-bar-activate activate-btn"><div></div><div></div><div></div></div> ';	
}
	
// Display the site header
add_action('bp_masthead', 'battleplan_printHeader', 20);
function battleplan_printHeader() { 
	$printHeader = '<header id="masthead" role="banner" aria-label="header">';		
	if ( has_nav_menu( 'top-menu', 'battleplan' ) ) $printHeader .= buildNavMenu( 'top' );		
	$printHeader .= do_shortcode('[get-element slug="site-header"]');		
	if ( has_nav_menu( 'header-menu', 'battleplan' ) ) $printHeader .= buildNavMenu( 'header' ); 		
	$printHeader .= '</header><!-- #masthead -->';
	
	echo $printHeader;
}

// Display locked site-message
add_action('bp_before_wrapper_content', 'battleplan_printSiteMessage', 20);
function battleplan_printSiteMessage() { 
	echo do_shortcode('[get-element slug="site-message"]');
}	

// Display #wrapper-top
add_action('bp_wrapper_top', 'battleplan_printWrapperTop', 20);
function battleplan_printWrapperTop() { 
	$current_page = sanitize_post( $GLOBALS['wp_the_query']->get_queried_object() );
	$textarea = get_post_meta( $current_page->ID, 'page-top_text', true );
 	if ( $textarea != "" ) : echo "<section id='wrapper-top'>".apply_filters('the_content', $textarea)."</section><!-- #wrapper-top -->"; endif;
}	

/*--------------------------------------------------------------
# AJAX Functions
--------------------------------------------------------------*/

// Change site option, post meta or user meta with javaScript
add_action( 'wp_ajax_update_meta', 'battleplan_update_meta_ajax' );
add_action( 'wp_ajax_nopriv_update_meta', 'battleplan_update_meta_ajax' );
function battleplan_update_meta_ajax() {
	$type = $_POST['type'];	
	$key = $_POST['key'];	
	$value = $_POST['value'];
	
	if ( $type == "site" ) update_option( $key, $value );
	if ( $type == "user" ) update_user_meta( wp_get_current_user()->ID, $key, $value, false );
	if ( $type == "post" || $type == "page" ) updateMeta( get_the_ID(), $key, $value );	
	//if ( $type == "cookie" ) setcookie($key, $value, time() + (86400 * 365), '/', '', true, false);
	if ( $type == "cookie" ) writeCookie($key, $value, 365);
}

// Log Page Load Speed
add_action( 'wp_ajax_log_page_load_speed', 'battleplan_log_page_load_speed_ajax' );
add_action( 'wp_ajax_nopriv_log_page_load_speed', 'battleplan_log_page_load_speed_ajax' );
function battleplan_log_page_load_speed_ajax() {
	$userValid = $_POST['userValid'];	
	$userLoc = $_POST['userLoc'];	
	$loadTime = $_POST['loadTime'];
	$deviceTime = $_POST['deviceTime'];
	
	if ( _BP_COUNT_ALL_VISITS == "override" || ( _USER_LOGIN != 'battleplanweb' && $userLoc != "Ashburn, VA" && ( $userValid == "true" || _BP_COUNT_ALL_VISITS == "true" )) ) :
		$desktopCounted = readMeta(_HEADER_ID, "load-number-desktop");
		$desktopSpeed = readMeta(_HEADER_ID, "load-speed-desktop");	
		$mobileCounted = readMeta(_HEADER_ID, "load-number-mobile");
		$mobileSpeed = readMeta(_HEADER_ID, "load-speed-mobile");		
		$lastEmail = readMeta(_HEADER_ID, "last-email");
		$rightNow = strtotime(date("F j, Y, g:i a"));
		$daysSinceEmail = (($rightNow - $lastEmail) / 60 / 60 / 24);
		$totalCounted = $desktopCounted + $mobileCounted;	

		if ( ( $totalCounted > 300 && $daysSinceEmail > 45 ) || $daysSinceEmail > 100 ) :
			$desktopCount = sprintf( _n( '%s pageview', '%s pageviews', $desktopCounted, 'battleplan' ), $desktopCounted );
			$mobileCount = sprintf( _n( '%s pageview', '%s pageviews', $mobileCounted, 'battleplan' ), $mobileCounted );
			$emailTo = "info@battleplanwebdesign.com";
			$emailFrom = "From: Website Administrator <do-not-reply@battleplanwebdesign.com>";
			$subject = "Speed Report: ".$_SERVER['HTTP_HOST'];
			$content = $_SERVER['HTTP_HOST']." Speed Report\n\nDesktop = ".$desktopSpeed."s on ".$desktopCount."\nMobile = ".$mobileSpeed."s on ".$mobileCount."\n";	
			$desktopCounted = $desktopSpeed = $mobileCounted = $mobileSpeed = 0;
			updateMeta( _HEADER_ID, "last-email", $rightNow );	
			mail($emailTo, $subject, $content, $emailFrom);
		endif;

		if ( $deviceTime == "desktop" ) : 	
			$newTime = ($desktopCounted * $desktopSpeed) + $loadTime;
			$desktopCounted++;
			$desktopSpeed = (round($newTime / $desktopCounted, 1)); 
		else: 
			$newTime = ($mobileCounted * $mobileSpeed) + $loadTime;
			$mobileCounted++;
			$mobileSpeed = (round($newTime / $mobileCounted, 1));
		endif;

		updateMeta( _HEADER_ID, "load-number-desktop", $desktopCounted );	
		updateMeta( _HEADER_ID, "load-speed-desktop", $desktopSpeed );		
		updateMeta( _HEADER_ID, "load-number-mobile", $mobileCounted );	
		updateMeta( _HEADER_ID, "load-speed-mobile", $mobileSpeed );		
		$response = array( 'result' => ucfirst($deviceTime.' load speed = '.$loadTime.'s' ));
	else:
		$response = array( 'result' => ucfirst($deviceTime.' load speed not counted' ));
	endif;	
	wp_send_json( $response );	
}

// Count Site Views
add_action( 'wp_ajax_count_site_views', 'battleplan_count_site_views_ajax' );
add_action( 'wp_ajax_nopriv_count_site_views', 'battleplan_count_site_views_ajax' );
function battleplan_count_site_views_ajax() {
	$userValid = $_POST['userValid'];		
	$userLoc = $_POST['userLoc'];	
	$userRefer = $_POST['userRefer'];	
	$userRefer = parse_url($userRefer);
	$userRefer = $userRefer['host'];
	$userRefer = str_replace(array("www.", "http://", "https://"), "", $userRefer);	
	$userIP = $_SERVER["REMOTE_ADDR"];
	$lastViewed = readMeta(_HEADER_ID, 'log-views-time');
	$rightNow = strtotime(date("F j, Y g:i a"));	
	$today = strtotime(date("F j, Y"));
	$dateDiff = (($today - $lastViewed) / 60 / 60 / 24);
	$getViews = readMeta(_HEADER_ID, 'log-views');
	$getViews = maybe_unserialize( $getViews );
	if ( !is_array($getViews) ) $getViews = array();
	$viewsToday = $views7Day = $views30Day = $views90Day = $views180Day = $views365Day = $searchToday = intval(0); 
	
			/*$userIP = 'Time: '.$rightNow.' Site: '.battleplan_getDomainName().' Location: '.$userLoc.' IP: <a href="https://whatismyipaddress.com/ip/'.$_SERVER["REMOTE_ADDR"].'">'.$_SERVER["REMOTE_ADDR"].'</a><br/>';
			$getIPs = readMeta(_HEADER_ID, 'log-views-ips');
			$getIPs = maybe_unserialize( $getIPs );
			if ( !is_array($getIPs) ) $getIPs = array();
			array_unshift($getIPs, $userIP);
			$newIPs = maybe_serialize( $getIPs );
			updateMeta(_HEADER_ID, 'log-views-ips', $newIPs);		*/	
		
	if ( _BP_COUNT_ALL_VISITS == "override" || ( _USER_LOGIN != 'battleplanweb' && $userLoc != "Ashburn, VA" && ( $userValid == "true" || _BP_COUNT_ALL_VISITS == "true" )) ) :
		if(!isset($_COOKIE['countVisit'])) :
			if ( $dateDiff != 0 ) : // day has passed
				for ($i = 1; $i <= $dateDiff; $i++) {	
					$figureTime = $today - ( ($dateDiff - $i) * 86400);	
					array_unshift($getViews, array ('date'=>date("F j, Y", $figureTime), 'views'=>$viewsToday, 'search'=>$searchToday));
				}	
			else:
				$viewsToday = intval($getViews[0]['views']); 
				$searchToday = intval($getViews[0]['search']); 
			endif;	
			updateMeta(_HEADER_ID, 'log-views-now', $rightNow);
			updateMeta(_HEADER_ID, 'log-views-time', $today);	
			$viewsToday++;
			if ( strpos($userRefer, "google") !== false || strpos($userRefer, "yahoo") !== false || strpos($userRefer, "bing") !== false || strpos($userRefer, "duckduckgo") !== false ) $searchToday++;	
			array_shift($getViews);	
			array_unshift($getViews, array ('date'=>date('F j, Y', $today), 'views'=>$viewsToday, 'search'=>$searchToday));	
			$newViews = maybe_serialize( $getViews );
			updateMeta(_HEADER_ID, 'log-views', $newViews);

			for ($x = 0; $x < 7; $x++) { $views7Day = $views7Day + intval($getViews[$x]['views']); } 					
			for ($x = 0; $x < 30; $x++) { $views30Day = $views30Day + intval($getViews[$x]['views']); } 						
			for ($x = 0; $x < 90; $x++) { $views90Day = $views90Day + intval($getViews[$x]['views']); } 		
			for ($x = 0; $x < 180; $x++) { $views180Day = $views180Day + intval($getViews[$x]['views']); } 		
			for ($x = 0; $x < 365; $x++) { $views365Day = $views365Day + intval($getViews[$x]['views']); } 		
			updateMeta(_HEADER_ID, 'log-views-total-7day', $views7Day);			
			updateMeta(_HEADER_ID, 'log-views-total-30day', $views30Day);			 
			updateMeta(_HEADER_ID, 'log-views-total-90day', $views90Day);	
			updateMeta(_HEADER_ID, 'log-views-total-180day', $views180Day);	
			updateMeta(_HEADER_ID, 'log-views-total-365day', $views365Day);	
			
			$minimumCount = $views90Day < 250 ? 250 : $views90Day;
			
			$getReferrers = readMeta(_HEADER_ID, 'log-views-referrers');
			$getReferrers = maybe_unserialize( $getReferrers );
			if ( !is_array($getReferrers) ) $getReferrers = array();
			array_unshift($getReferrers, $userRefer);
			$limitReferrerCount = count($getReferrers) - $minimumCount;
			if ( $limitReferrerCount > 0 ) :
				for ($i=0; $i < $limitReferrerCount; $i++) :
					array_pop($getReferrers);
				endfor;
			endif;
			$newReferrers = maybe_serialize( $getReferrers );
			updateMeta(_HEADER_ID, 'log-views-referrers', $newReferrers);

			$getLocations = readMeta(_HEADER_ID, 'log-views-cities');
			$getLocations = maybe_unserialize( $getLocations );
			if ( !is_array($getLocations) ) $getLocations = array();
			array_unshift($getLocations, $userLoc);
			$limitLocationCount = count($getLocations) - $minimumCount;
			if ( $limitLocationCount > 0 ) :
				for ($i=0; $i < $limitLocationCount; $i++) :
					array_pop($getLocations);
				endfor;
			endif;
			$newLocations = maybe_serialize( $getLocations );
			updateMeta(_HEADER_ID, 'log-views-cities', $newLocations);
	
			//setcookie('countVisit', 'no', time() + 600, "/", '', true, false);
			writeCookie('countVisit', 'no', 0.007);
	
			$response = array( 'result' => 'Site View counted: Today='.$viewsToday.', Week='.$views7Day.', Month='.$views30Day.', Quarter='.$views90Day.', Year= '.$views365Day);
		else:
			$response = array( 'result' => 'Site View NOT counted: viewer already counted');
		endif;
	else:
		$response = array( 'result' => 'Site View NOT counted: user='._USER_LOGIN.', user valid='.$userValid );
	endif;	
	wp_send_json( $response );	
}

// Count Page / Post Views
add_action( 'wp_ajax_count_post_views', 'battleplan_count_post_views_ajax' );
add_action( 'wp_ajax_nopriv_count_post_views', 'battleplan_count_post_views_ajax' );
function battleplan_count_post_views_ajax() {
	$uniqueID = $_POST['uniqueID'];
	$pagesViewed = intval( $_POST['pagesViewed']);
	$theID = intval( $_POST['id'] );
	$postType = get_post_type($theID);
	$userValid = $_POST['userValid'];	
	$userLoc = $_POST['userLoc'];	
	$lastViewed = readMeta($theID, 'log-views-time');
	$rightNow = strtotime(date("F j, Y g:i a"));	
	$today = strtotime(date("F j, Y"));
	$dateDiff = (($today - $lastViewed) / 60 / 60 / 24);
	$getPageviews = readMeta(_HEADER_ID, 'pages-viewed');
	$getPageviews = maybe_unserialize( $getPageviews );
	if ( !is_array($getPageviews) ) $getPageviews = array();
	$getViews = readMeta($theID, 'log-views');
	$getViews = maybe_unserialize( $getViews );
	if ( !is_array($getViews) ) $getViews = array();
	$viewsToday = $views7Day = $views30Day = $views90Day = $views180Day = $views365Day = intval(0); 
	
	if ( _BP_COUNT_ALL_VISITS == "override" || ( _USER_LOGIN != 'battleplanweb' && $userLoc != "Ashburn, VA" && ( $userValid == "true" || _BP_COUNT_ALL_VISITS == "true" )) ) :
		$visitCutoff = readMeta(_HEADER_ID, 'log-views-total-90day');
		$getPageviews[$uniqueID] = $pagesViewed;
		if ( count($getPageviews) > $visitCutoff ) array_shift($getPageviews);	
		$newPageviews = maybe_serialize( $getPageviews );
		updateMeta(_HEADER_ID, 'pages-viewed', $newPageviews);	
	
		if ( $dateDiff != 0 ) : // day has passed, move 29 to 30, and so on	
			for ($i = 1; $i <= $dateDiff; $i++) {	
				$figureTime = $today - ( ($dateDiff - $i) * 86400);	
				array_unshift($getViews, array ('date'=>date("F j, Y", $figureTime), 'views'=>$viewsToday));
			}	
		else:
			$viewsToday = intval($getViews[0]['views']); 
		endif;
	
		updateMeta($theID, 'log-views-now', $rightNow);
		updateMeta($theID, 'log-views-time', $today);	
		$viewsToday++;
		array_shift($getViews);	
		array_unshift($getViews, array ('date'=>date('F j, Y', $today), 'views'=>$viewsToday));	
		$newViews = maybe_serialize( $getViews );
		updateMeta($theID, 'log-views', $newViews);

		for ($x = 0; $x < 7; $x++) { $views7Day = $views7Day + intval($getViews[$x]['views']); } 					
		for ($x = 0; $x < 30; $x++) { $views30Day = $views30Day + intval($getViews[$x]['views']); } 						
		for ($x = 0; $x < 90; $x++) { $views90Day = $views90Day + intval($getViews[$x]['views']); } 		
		for ($x = 0; $x < 180; $x++) { $views180Day = $views180Day + intval($getViews[$x]['views']); } 		
		for ($x = 0; $x < 365; $x++) { $views365Day = $views365Day + intval($getViews[$x]['views']); } 		
		updateMeta($theID, 'log-views-today', $viewsToday);					
		updateMeta($theID, 'log-views-total-7day', $views7Day);			
		updateMeta($theID, 'log-views-total-30day', $views30Day);			 
		updateMeta($theID, 'log-views-total-90day', $views90Day);	
		updateMeta($theID, 'log-views-total-180day', $views180Day);	
		updateMeta($theID, 'log-views-total-365day', $views365Day);	
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' VIEW counted: Today='.$viewsToday.', Week='.$views7Day.', Month='.$views30Day.', Quarter='.$views90Day.', Year='.$views365Day) );
	else:
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' view NOT counted: user='._USER_LOGIN.', user valid='.$userValid ));
	endif;	
	wp_send_json( $response );	
}

// Count Teaser Views
add_action( 'wp_ajax_count_teaser_views', 'battleplan_count_teaser_views_ajax' );
add_action( 'wp_ajax_nopriv_count_teaser_views', 'battleplan_count_teaser_views_ajax' );
function battleplan_count_teaser_views_ajax() {
	$theID = intval( $_POST['id'] );
	$postType = get_post_type($theID);
	$userValid = $_POST['userValid'];	
	$userLoc = $_POST['userLoc'];	
	$lastTeased = date("F j, Y g:i a", readMeta($theID, 'log-tease-time'));
	$today = strtotime(date("F j, Y  g:i a"));
	
	if ( _BP_COUNT_ALL_VISITS == "override" || ( _USER_LOGIN != 'battleplanweb' && $userLoc != "Ashburn, VA" && ( $userValid == "true" || _BP_COUNT_ALL_VISITS == "true" )) ) :
		updateMeta($theID, 'log-tease-time', $today);
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' TEASER counted: Prior tease = '.$lastTeased) );
	else:
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' teaser NOT counted: user='._USER_LOGIN.', user valid='.$userValid ));
	endif;	
	wp_send_json( $response );	
}

// Count Link Clicks
add_action( 'wp_ajax_count_link_clicks', 'battleplan_count_link_clicks_ajax' );
add_action( 'wp_ajax_nopriv_count_link_clicks', 'battleplan_count_link_clicks_ajax' );
function battleplan_count_link_clicks_ajax() {
	$type = $_POST['type'];	
	$thisYear = date("Y");		

	if ( $type == "phone call" ) : $getType = 'call-clicks';
	elseif ( $type == "email" ) : $getType = 'email-clicks';
	endif;

	$getClicks = readMeta(_HEADER_ID, $getType);	
	$getClicks = maybe_unserialize( $getClicks );
	if ( !is_array($getClicks) ) $getClicks = array();

	$recentYear = $getClicks[0]['year'];

	if ( $recentYear == $thisYear ) :
		$numClicks = intval($getClicks[0]['number']);	
		$numClicks++;
		array_shift($getClicks); // remove current value of year, so it can be replaced	
		array_unshift($getClicks, array ('year'=>$thisYear, 'number'=>$numClicks));		
	else:
		array_unshift($getClicks, array ('year'=>$thisYear, 'number'=>1));			
	endif;

	$newClicks = maybe_serialize( $getClicks );
	updateMeta(_HEADER_ID, $getType, $newClicks);

	$response = array( 'result' => $getType.' year = '.$thisYear.' counted = '.$numClicks);
	wp_send_json( $response );	
}

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
	$a = shortcode_atts( array( 'size'=>'100', 'order'=>'', 'link'=>'', 'class'=>'', 'related'=>'false', 'start'=>'', 'end'=>'' ), $atts );
	$related = esc_attr($a['related']);	
	$order = esc_attr($a['order']);	
	if ( $order != '' ) $style = " order: ".$order;
	$link = esc_attr($a['link']);	
	if ( strpos($link, 'youtube') !== false && $related == "false" ) $link .= "?rel=0";
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

	return '<div class="block block-video span-'.$size.$class.'" style="'.$style.' padding-top:'.$height.'%"><iframe src="" data-src="'.$link.'" data-loading="delay" allowfullscreen></iframe></div>';
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
		$buildInput = '<div class="block block-button block-100">'.$content.'</div>';		
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