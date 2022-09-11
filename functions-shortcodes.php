<?php 
/* Battle Plan Web Design Functions: Shortcodes

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
	
	if ( strpos($data, 'phone') !== false ) :
		$phoneBasic = $GLOBALS['customer_info']['area'].'-'.$GLOBALS['customer_info']['phone'];
		$phoneFormat = $GLOBALS['customer_info']['area-before'].$GLOBALS['customer_info']['area'].$GLOBALS['customer_info']['area-after'].$GLOBALS['customer_info']['phone'];		
		if ( strpos($data, 'mm-bar-phone') !== false ) :
			$phoneFormat = '<div class="mm-bar-btn mm-bar-phone call-btn" aria-hidden="true"></div><span class="sr-only">Call Us</span>';	
		elseif ( strpos($data, 'alt') !== false ) :
			if ( isset($GLOBALS['customer_info'][$data]) ) $phoneFormat = $GLOBALS['customer_info'][$data];	
		endif;
		if ( strpos($data, '-notrack') !== false ):
			return '<a class="phone-link" href="tel:1-'.$phoneBasic.'">'.$phoneFormat.'</a>';
		else:
			return '<a href="#" class="phone-link track-clicks" data-action="phone call" data-url="tel:1-'.$phoneBasic.'">'.$phoneFormat.'</a>';
		endif;
	endif;

	if ( isset($GLOBALS['customer_info'][$data]) ) return $GLOBALS['customer_info'][$data];
}
 
// Use Google ratings in content
add_shortcode( 'get-google-rating', 'battleplan_displayGoogleRating' );
function battleplan_displayGoogleRating($atts, $content = null) {
	$a = shortcode_atts( array( 'detail'=>'rating', ), $atts );  
	$detail = esc_attr($a['detail']);	
 	$googleInfo = get_option('bp_google_reviews');
	if ( $detail == 'rating' ) return number_format($googleInfo['rating'], 1, '.', ',');
	return $googleInfo['number'];
}		
		
// Returns current year
add_shortcode( 'get-year', 'battleplan_getYear' );
function battleplan_getYear() { return date("Y"); }
 
// Returns current month
add_shortcode( 'get-month', 'battleplan_getMonth' );
function battleplan_getMonth($atts, $content = null) {
	$a = shortcode_atts( array( 'style'=>'full', ), $atts );
	$style = esc_attr($a['style']);	
	if ( $style == "abbr" || $style == "short" ) : return date("M");	
	elseif ( $style == "numeric" ) : return date("n");
	else: return date("F"); endif;
}
 
// Returns current day of week
add_shortcode( 'get-day', 'battleplan_getDay' );
function battleplan_getDay($atts, $content = null) {
	$a = shortcode_atts( array( 'style'=>'full', ), $atts );
	$style = esc_attr($a['style']);	
	if ( $style == "abbr" || $style == "short" ) : return date("D");	
	elseif ( $style == "numeric" ) : return date("j");
	elseif ( $style == "suffix" ) : return date("jS");	
	else: return date("l"); endif;
}

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
	elseif (date("m")>="06" && date("m")<="09") : return $summer; 
	elseif (date("m")>="10" && date("m")<="11") : return $fall; 
	else: return $winter; endif; 
}

// Clear space under a "low-hanging" element 
add_shortcode( 'clear', 'battleplan_clearFix' );
function battleplan_clearFix( $atts, $content = null ) {
	$a = shortcode_atts( array( 'height'=>'0px', 'class'=>'' ), $atts );
	$height = esc_attr($a['height']);
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	
	return '<div class="clearfix'.$class.'" style="height:'.$height.'"></div>';
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
	
	if ( !isset($page_data) ) :
		if ( get_page_by_path( $page_slug, OBJECT, 'elements' ) ) : $page_data = get_page_by_path( $page_slug, OBJECT, 'elements' ); endif;
	endif;

	if ( isset($page_data) && get_post_status($page_data->ID) == "publish" ) return apply_filters('the_content', $page_data->post_content); 	
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
		return parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
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
	
	if ( isset($getVars[$var]) ) return $getVars[$var]; 
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
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed"; $args['orderby']='meta_value_num';	
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
		//$buildImage .= '<img data-id="'.$getID.'"'.getImgMeta($getID).' data-count-view="true" class="wp-image-'.$getID.' random-img '.$tags[0].'-img '.$align.' size-'.$size.$class.'" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';	
		$buildImage .= '<img class="wp-image-'.$getID.' random-img '.$tags[0].'-img '.$align.' size-'.$size.$class.'" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';	
		if ( $link == "yes" ) $buildImage .= '</a>';	
		$imageArray[] = $buildImage;
		
		battleplan_countTease( $getID );	
	
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
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed"; $args['orderby']='meta_value_num';	
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
		//$getImage .= '<img data-id="'.$getID.'"'.getImgMeta($getID).' data-count-view="true" class="random-img '.$tags[0].'-img '.$align.'" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';
		$getImage .= '<img class="random-img '.$tags[0].'-img" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';
		if ( $link == "yes" ) $getImage .= '</a>';
		
		battleplan_countTease( $getID );	

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
							   
	$print = do_shortcode('[layout grid="'.$col.'e" valign="'.$valign.'"]'.printArray($imageArray).'[/layout]'); 
	return $print;
}

// Build an archive
add_shortcode( 'build-archive', 'battleplan_getBuildArchive' );
function battleplan_getBuildArchive($atts, $content = null) {	
	$a = shortcode_atts( array( 'type'=>'', 'count_view'=>'false', 'thumb_only'=>'false', 'show_btn'=>'false', 'btn_text'=>'Read More', 'btn_pos'=>'outside', 'show_title'=>'true', 'title_pos'=>'outside', 'show_date'=>'false', 'show_author'=>'false', 'show_social'=>'false', 'show_excerpt'=>'true', 'show_content'=>'false', 'add_info'=>'', 'show_thumb'=>'true', 'no_pic'=>'', 'size'=>'thumbnail', 'pic_size'=>'1/3', 'text_size'=>'', 'accordion'=>'false', 'link'=>'post', 'truncate'=>'false' ), $atts );
	$type = esc_attr($a['type']);
	$truncate = esc_attr($a['truncate']);
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
	$link = esc_attr($a['link']);	
	if ( strpos($link, 'cf-') === 0 ) : $linkLoc = esc_url(get_field(str_replace('cf-', '', $link)));
	elseif ( $type == "testimonials" ) : $linkLoc = "/testimonials/";
	elseif ( $link == "false" || $link == "no" ) : $link = "false"; $linkLoc = "";
	elseif ( $link == "" || $link == "post" ) : $linkLoc = esc_url(get_the_permalink(get_the_ID()));	
	else: $linkLoc = $link;	endif;
	$noPic = esc_attr($a['no_pic']);	
	if ( $noPic == "" ) $noPic = "false";	
	$picADA = $titleADA = "";
	if ( $showBtn == "true" ) : $picADA = " ada-hidden='true'"; $titleADA = " aria-hidden='true' tabindex='-1'";
	elseif ( $showTitle != "false" ) : $picADA = " ada-hidden='true'"; endif;
	$thumbOnly = esc_attr($a['thumb_only']);
	$archiveMeta = $buildBtn = '';
		
	if ( has_post_thumbnail() && $showThumb == "true" ) : 	
		$meta = wp_get_attachment_metadata( get_post_thumbnail_id( get_the_ID() ) );
		$thumbW = $meta['sizes'][$size]['width'];
		$thumbH = $meta['sizes'][$size]['height'];
	
		$buildImg = do_shortcode('[img size="'.$picSize.'" class="image-'.$type.'" link="'.$linkLoc.'" '.$picADA.']'.get_the_post_thumbnail( get_the_ID(), $size, array( 'class'=>'img-archive img-'.$type, 'style'=>'aspect-ratio:'.$thumbW.'/'.$thumbH )).'[/img]'); 
		if ( $textSize == "" ) : $textSize = getTextSize($picSize); endif;
	
	elseif ( $noPic != "false") : 	
		$buildImg = do_shortcode("[img size='".$picSize."' class='image-".$type." block-placeholder placeholder-".$type."' link='".$linkLoc."' ".$picADA."]".wp_get_attachment_image( $noPic, $size, array( 'class'=>'img-archive img-'.$type ))."[/img]"); 
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
		
		$buildCredentials = "<div class='testimonials-credential testimonials-name'>".$testimonialName;
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
				//$archiveMeta .= "<h3 data-count-view=".$countView." data-id=".get_the_ID().">";
				$archiveMeta .= "<h3 data-count-view=".$countView.">";
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
		
		battleplan_countTease( get_the_ID() );	

	endif;	
	
	return $showArchive;
}

// Display randomly selected posts - start/end can be dates or -53 week / -51 week */
add_shortcode( 'get-random-posts', 'battleplan_getRandomPosts' );
function battleplan_getRandomPosts($atts, $content = null) {	
	$a = shortcode_atts( array( 'num'=>'1', 'offset'=>'0', 'leeway'=>'0', 'type'=>'post', 'tax'=>'', 'terms'=>'', 'field_key'=>'', 'field_value'=>'', 'field_compare'=>'IN', 'orderby'=>'recent', 'sort'=>'asc', 'count_view'=>'true', 'show_title'=>'true', 'title_pos'=>'outside', 'show_date'=>'false', 'show_author'=>'false', 'show_excerpt'=>'true', 'show_social'=>'false', 'show_btn'=>'true', 'button'=>'Read More', 'btn_pos'=>'inside', 'show_content'=>'false', 'thumb_only'=>'false', 'thumb_col'=>'1', 'thumbnail'=>'force', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'size'=>'thumbnail', 'pic_size'=>'1/3', 'text_size'=>'', 'link'=>'post', 'truncate'=>'true' ), $atts );
	$num = esc_attr($a['num']);	
	$offset = esc_attr($a['offset']);
	if ( $offset == '0' ) $offset = rand(0, esc_attr($a['leeway']));	
	$postType = esc_attr($a['type']);	
	$truncate = esc_attr($a['truncate']);	
	$title = esc_attr($a['show_title']);	
	$orderBy = esc_attr($a['orderby']);	
	$sort = esc_attr($a['sort']);		
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
	$combinePosts = '';

	$args = array ('posts_per_page'=>$num, 'offset'=>$offset, 'date_query'=>array( array( 'after'=>$start, 'before'=>$end, 'inclusive'=>true, ), ), 'order'=>$sort, 'post_type'=>$postType, 'post__not_in'=>$exclude);

	if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-365day' ||  $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed"; $args['orderby']='meta_value_num';	
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
		$showPost = do_shortcode('[build-archive type="'.$postType.'" count_view="'.$countView.'" thumb_only="'.$thumbOnly.'" show_btn="'.$showBtn.'" btn_text="'.$button.'" btn_pos="'.$btnPos.'" show_title="'.$title.'" title_pos="'.$titlePos.'" show_date="'.$showDate.'" show_excerpt="'.$showExcerpt.'" show_social="'.$showSocial.'" show_content="'.$showContent.'" show_author="'.$showAuthor.'" size="'.$size.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" link="'.$link.'" truncate="'.$truncate.'"]');	
	
		if ( $num > 1 ) $showPost = do_shortcode('[col]'.$showPost.'[/col]');	
		if ( has_post_thumbnail() || $thumbnail != "force" ) $combinePosts .= $showPost;
	endwhile; wp_reset_postdata(); endif;
	
	if ( $thumbOnly == "true" ) $combinePosts = '<div class="random-post random-posts thumb-only thumb-col-'.$thumbCol.'">'.$combinePosts.'</div>';
	
	return $combinePosts;
}

// Display posts & images in a Bootstrap slider 
add_shortcode( 'get-post-slider', 'battleplan_getPostSlider' );
function battleplan_getPostSlider($atts, $content = null ) {
	wp_enqueue_script( 'battleplan-carousel', get_template_directory_uri().'/js/bootstrap-carousel.js', array('jquery-core'), _BP_VERSION, false );		
	wp_enqueue_script( 'battleplan-carousel-slider', get_template_directory_uri().'/js/script-bootstrap-slider.js', array('battleplan-carousel'), _BP_VERSION, false );	

	$a = shortcode_atts( array( 'type'=>'testimonials', 'auto'=>'yes', 'interval'=>'6000', 'loop'=>'true', 'num'=>'4', 'offset'=>'0', 'pics'=>'yes', 'caption'=>'no', 'controls'=>'yes', 'controls_pos'=>'below', 'indicators'=>'no', 'justify'=>'center', 'pause'=>'true', 'orderby'=>'recent', 'order'=>'asc', 'post_btn'=>'', 'all_btn'=>'View All', 'show_date'=>'false', 'show_author'=>'false', 'show_excerpt'=>'true', 'show_content'=>'false', 'link'=>'', 'pic_size'=>'1/3', 'text_size'=>'', 'slide_type'=>'fade', 'tax'=>'', 'terms'=>'', 'tag'=>'', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'size'=>'thumbnail', 'id'=>'', 'mult'=>'1', 'class'=>'', 'truncate'=>'true', 'lazy'=>'true', 'blur'=>'false' ), $atts );
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
	$showDate = esc_attr($a['show_date']);	
	$showAuthor = esc_attr($a['show_author']);	
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
	$blur = esc_attr($a['blur']) == "true" ? " slider-blur" : "";	
	$lazy = esc_attr($a['lazy']);	
	if ( $lazy == "true" ) : $lazy = "lazy"; else: $lazy = "eager"; endif;
	$mult = esc_attr($a['mult']);		
	if ( $mult == 1 ) : $multSize = $imgSize = 100; 
	elseif ( $mult == 2 ) : $multSize = $imgSize = 50;	
	elseif ( $mult == 3 ) : $multSize = $imgSize = 33;
	elseif ( $mult == 4 ) : $multSize = $imgSize = 25;
	elseif ( $mult == 5 ) : $multSize = $imgSize = 20;	
	else : $multSize = $imgSize = 17; endif;
	if ( $mult > 1 ) $num--;
	$multDisplay = 0;
	$numDisplay = -1;
	$rowDisplay = 0;
	$sliderNum = rand(100,999);
	
	$controlClass = "";
	//if ( $controls == "yes" && $btnText == "no" && $indicators == "no" ) $controlClass = " only-controls";  $btnText doesn't exist		
	if ( $controls == "yes" && $indicators == "no" ) $controlClass = " only-controls";	
	if ( $postBtn == "" ) : $showBtn = "false"; else: $showBtn = "true"; endif;
	if ( $pause == "true" ) : $pause = "hover"; else: $pause = "false"; endif;
	if ( $link == "" ) : $linkTo = "/".$type."/"; elseif ( $link == "none" || $link == "false" || $link == "no" ) : $link = "none"; endif;		
	
	if ( $type == "image" || $type == "images" ) :
		$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$num, 'order'=>$order);

		if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day"; $args['orderby']='meta_value_num';			
		elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed"; $args['orderby']='meta_value_num';	
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
					if ( $numDisplay == 0 ) : $active = "active"; else: $active = ""; endif;
					$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'" class="'.$active.'"></li>';
					if ( $numDisplay != 0 ) $buildInner .= '</div>';
					//$buildInner .= '<div class="'.$active.' carousel-item carousel-item-'.$type.'" data-id="'.get_the_ID().'">';
					$buildInner .= '<div class="'.$active.' carousel-item carousel-item-'.$type.'">';
				endif;	

				$image = wp_get_attachment_image_src(get_the_ID(), $size );
				$imgSet = wp_get_attachment_image_srcset(get_the_ID(), $size );		
		
				$linkTo = $buildImg = '';
				if ( $link == "alt" ) $linkTo = readMeta(get_the_ID(), '_wp_attachment_image_alt', true);				
				if ( $link == "description" ) $linkTo = esc_html(get_post(get_the_ID())->post_content);
				if ( $link != "none" ) : $buildImg = "<a href='".$linkTo."' class='link-archive link-".$type."'>"; endif;	
				//$buildImg .= "<img data-id='".get_the_ID()."' ".getImgMeta(get_the_ID())." data-count-view='true' data-count-view='true' class='img-slider ".$tags[0]."-img' loading='lazy' src = '".$image[0]."' width='".$image[1]."' height='".$image[2]."' style='aspect-ratio:".$image[1]."/".$image[2]."' alt='".readMeta(get_the_ID(), '_wp_attachment_image_alt', true)."'>";

				$buildImg .= '<img class="img-slider '.$tags[0].'-img" loading="'.$lazy.'" src = "'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta(get_the_ID(), "_wp_attachment_image_alt", true).'">';
	
				battleplan_countTease( get_the_ID() );				 
		
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
		elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed"; $args['orderby']='meta_value_num';	
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
						$multDisplay++;
					
						$buildArchive = do_shortcode('[build-archive type="'.$type.'" show_btn="'.$showBtn.'" btn_text="'.$postBtn.'" show_excerpt="'.$showExcerpt.'" show_content="'.$showContent.'" show_date="'.$showDate.'" show_author="'.$showAuthor.'" size="'.$size.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" link="'.$link.'" truncate="'.$truncate.'"]');	
						
						if ( $multDisplay == 1 ) :
							if ( $numDisplay == 0 ) : $active = "active"; else: $active = ""; endif;
							$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'" class="'.$active.'"></li>';
							/*$buildInner .= '<div class="'.$active.' carousel-item carousel-item-'.$type.'" data-id="'.get_the_ID().'">';*/
							$buildInner .= '<div class="'.$active.' carousel-item carousel-item-'.$type.'">';
						endif;

						if ( $mult == 1 ) : $buildInner .= $buildArchive;
						else: $buildInner .= do_shortcode('[group size="'.$multSize.'"]'.$buildArchive.'[/group]');						
						endif;						

						if ( $multDisplay == $mult ) :
							$buildInner .= "</div>";	
							$multDisplay = 0;
						endif;
					endif;
				endif;
			endwhile; 
		wp_reset_postdata(); 
		endif;		
	endif;

	$buildIndicators .= '</ol>';
	$buildInner .= '</div>';

	$controlsPrevBtn = '<div class="block block-button"><a class="button carousel-control-prev'.$controlClass.'" href="#'.$type.'Slider'.$sliderNum.'" data-slide="prev"><span class="carousel-control-prev-icon" aria-label="Previous Slide"><span class="sr-only">Previous Slide</span></span></a></div>';
	$controlsNextBtn = '<div class="block block-button"><a class="button carousel-control-next'.$controlClass.'" href="#'.$type.'Slider'.$sliderNum.'" data-slide="next"><span class="carousel-control-next-icon" aria-label="Next Slide"><span class="sr-only">Next Slide</span></span></a></div>';
	$viewMoreBtn = do_shortcode('[btn link="'.$linkTo.'"]'.$allBtn.'[/btn]');	

	$buildControls = "<div class='controls controls-".$controlsPos."'>";	
	$buildControls .= $controlsPrevBtn;
	if ( $allBtn != "" ) $buildControls .= $viewMoreBtn;
	$buildControls .= $controlsNextBtn;	
	$buildControls .= "</div>";	
	
	$style = '';

	if ( $slideType == "box" ) : $style = "style='margin-left:auto; margin-right:auto;'"; $slideClass="box-slider"; elseif ( $slideType == "screen" ) : $style = "style='width: calc(100vw - 17px); left: 50%; transform: translateX(calc(-50vw + 8px));'"; $slideClass="screen-slider"; elseif ( $slideType == "fade" ) : $slideClass="carousel-fade"; else: $slideClass="carousel-fade"; endif;	
	
	$buildSlider = '<div id="'.$type.'Slider'.$sliderNum.'" class="carousel slide slider slider-'.$type.' '.$slideClass.' '.$class.' mult-'.$mult.$blur.'" '.$style.' data-interval="'.$interval.'" data-pause="'.$pause.'" data-wrap="'.$loop.'" data-touch="true"';	
	if ( $autoplay == "yes" || $autoplay == "true" ) $buildSlider .= ' data-auto="true"';
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
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed"; $args['orderby']='meta_value_num';	
	else : $args['orderby']=$orderBy; endif;		

	$image_query = new WP_Query($args);		
	$imageArray = array();
	
	if ( $image_query->have_posts() ) : while ($image_query->have_posts() ) : $image_query->the_post();
		$totalNum = $image_query->post_count;
		$image = wp_get_attachment_image_src( get_the_ID(), $size );
		$getImage = "";
		if ( $link != "false" ) $getImage .= '<a href="'.$image[0].'">';
		//$getImage .= '<img data-id="'.get_the_ID().'"'.getImgMeta(get_the_ID()).' data-count-view="true" data-count-view="true" class="logo-img '.$tags[0].'-img" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" alt="'.readMeta(get_the_ID(), '_wp_attachment_image_alt', true).'">';
		$getImage .= '<img class="logo-img '.$tags[0].'-img" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" alt="'.readMeta(get_the_ID(), '_wp_attachment_image_alt', true).'">';
		if ( $link != "false" ) $getImage .= '</a>';
		$imageArray[] = '<span>'.$getImage.'</span>';			
	endwhile; wp_reset_postdata(); endif;	
	
	if ( $package == "hvac" ) :
		$addLogos = array( "amana","american-standard","bryant","carrier","comfortmaker","goodman","heil","honeywell","lennox","rheem","ruud","samsung","tempstar","trane","york" );		
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
		//$gallery .= '<dl class="col col-archive col-gallery id-'.$getID.'"><dt class="col-inner"><a class="link-archive link-gallery ari-fancybox" href="'.$full[0].'"><img class="img-gallery wp-image-'.get_the_ID().'" data-id="'.get_the_ID().'"'.getImgMeta($getID).' loading="lazy" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta(get_the_ID(), '_wp_attachment_image_alt', true).'"></a>'.$captionPrint.'</dt></dl>';
		$gallery .= '<dl class="col col-archive col-gallery id-'.$getID.'"><dt class="col-inner"><a class="link-archive link-gallery ari-fancybox" href="'.$full[0].'"><img class="img-gallery wp-image-'.get_the_ID().'" loading="lazy" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta(get_the_ID(), '_wp_attachment_image_alt', true).'"></a>'.$captionPrint.'</dt></dl>';
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
	
	$imagePath = get_template_directory().'/common/logos/24-hr-service-'.$graphic.'.png';			
	list($width, $height) = getimagesize($imagePath);
	
	return '<img class="noFX" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/24-hr-service-'.$graphic.'.png" alt="We provide 24/7 emergency service." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';
}

// Add Google Guaranteed widget to Sidebar
add_shortcode( 'get-google-guaranteed', 'battleplan_getGoogleGuaranteed' );
function battleplan_getGoogleGuaranteed( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'graphic'=>'1' ), $atts );
	$graphic = esc_attr($a['graphic']);
	
	$imagePath = get_template_directory().'/common/logos/google-guaranteed.png';			
	list($width, $height) = getimagesize($imagePath);
	
	return '<img class="noFX" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/google-guaranteed.png" alt="We are proud to be Google Guaranteed." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';
}

// Add Now Hiring widget to Sidebar
add_shortcode( 'now-hiring', 'battleplan_getNowHiring' );
function battleplan_getNowHiring( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'graphic'=>'1', 'link'=>'career-opportunities' ), $atts );
	$graphic = esc_attr($a['graphic']);
	$link = esc_attr($a['link']);
	
	$imagePath = get_template_directory().'/common/logos/now-hiring-'.$graphic.'.png';			
	list($width, $height) = getimagesize($imagePath);
	
	return '<a href="/'.$link.'"><img class="noFX" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/now-hiring-'.$graphic.'.png" alt="We are hiring! Join our team." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" /></a>';
}

// Add BBB widget to Sidebar
add_shortcode( 'get-bbb', 'battleplan_getBBB' );
function battleplan_getBBB( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'link'=>'', 'graphic'=>'1' ), $atts );
	$link = esc_attr($a['link']);
	$graphic = esc_attr($a['graphic']);

	$imagePath = get_template_directory().'/common/logos/bbb-'.$graphic.'.png';			
	list($width, $height) = getimagesize($imagePath);
	
	return '<a href="'.$link.'" title="Click here to view our profile page on the Better Business Bureau website."><img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/bbb-'.$graphic.'.png" alt="We are accredited with the BBB and are proud of our A+ rating." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';
}

// Add Veteran Owned widget to Sidebar
add_shortcode( 'get-veteran-owned', 'battleplan_getVeteranOwned' );
function battleplan_getVeteranOwned( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'link'=>'', 'graphic'=>'1' ), $atts );
	$link = esc_attr($a['link']);
	$graphic = esc_attr($a['graphic']);
	
	$imagePath = get_template_directory().'/common/logos/veteran-owned-'.$graphic.'.png';			
	list($width, $height) = getimagesize($imagePath);
	
	return '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/veteran-owned-'.$graphic.'.png" alt="We are proud to be a Veteran Owned business." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';
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
	if ( $mc == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-mc.png" loading="lazy" alt="We accept Mastercard" width="100" height="62" style="aspect-ratio:100/62" />';
	if ( $visa == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-visa.png" loading="lazy" alt="We accept Visa width="100" height="62" style="aspect-ratio:100/62" />';
	if ( $discover == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-discover.png" loading="lazy" alt="We accept Discover width="100" height="62" style="aspect-ratio:100/62" />';
	if ( $amex == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-amex.png" loading="lazy" alt="We accept American Express width="100" height="62" style="aspect-ratio:100x62" />';
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
	if ( $bitcoin == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-bitcoin.png" alt="We accept Bitcoin crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $cardano == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-cardano.png" alt="We accept Cardano crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $chainlink == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-chainlink.png" alt="We accept Chainlink crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $dogecoin == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-dogecoin.png" alt="We accept Dogecoin crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $monero == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-monero.png" alt="We accept Monero crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $polygon == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-polygon.png" alt="We accept Polygon crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( $stellar == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-stellar.png" alt="We accept Stellar crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
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
	$a = shortcode_atts( array( 'img'=>'', 'size'=>'half-s', 'align'=>'center', 'full'=>'', 'pos'=>'bottom', 'break'=>'' ), $atts );	
	$size = esc_attr($a['size']);
	$full = esc_attr($a['full']);	
	$pos = esc_attr($a['pos']);	
	$break = esc_attr($a['break']);
	if ( $break == "none" ) $break = ' break-none';
	$align = "align".esc_attr($a['align']);
	$images = explode(',', esc_attr($a['img']));
	$num = count($images);
	
	$buildFlex = '<ul class="side-by-side '.$align.$break.'">';
	for ($i=0; $i<$num; $i++) :
		$img = wp_get_attachment_image_src( $images[$i], $size );

		list ($src, $width, $height ) = $img;
		if ( $images[$i] == $full ) : $class=' class="full-'.$pos.'" '; else: $class=''; endif;
		if ($height > 0) $ratio = $width/$height;	
		$buildFlex .= '<li style="flex: '.$ratio.'"'.$class.'>'.wp_get_attachment_image( $images[$i], $size ).'</li>';	
	endfor;
	$buildFlex .= '</ul>';
	
	return $buildFlex;
} 

// Make the nonce generated in header.php available to WP pages
add_shortcode( 'get-nonce', 'battleplan_get_nonce' );
function battleplan_get_nonce() {	
	return 'nonce="'._BP_NONCE.'"';
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

// Add search button to menu or other areas
add_shortcode( 'add-search-btn', 'battleplan_addSearchBtn' );
function battleplan_addSearchBtn( $atts, $content = null ) {
	$a = shortcode_atts( array( 'text'=>'Search Site', 'reveal'=>'click' ), $atts );
	$text = esc_attr($a['text']);
	$reveal = esc_attr($a['reveal']);
	$mobile = '';
	return bp_display_menu_search($text, $mobile, $reveal);
}

// Display RSS feed
add_shortcode( 'get-rss', 'battleplan_getRSS' );
function battleplan_getRSS( $atts, $content = null ) {
	$a = shortcode_atts( array( 'link'=>'', 'num'=>'5', 'menu'=>'true' ), $atts );
	$link = esc_attr($a['link']);
	$rss = fetch_feed( $link );
	$num = esc_attr($a['num']);
	$menu = esc_attr($a['menu']);
	include_once( ABSPATH . WPINC . '/feed.php' );
	
	$buildHeader = '<h1>Recent Blog Posts</h1>';
	$buildHeader .= do_shortcode('[clear height="15px"]');  
  
	if ( ! is_wp_error( $rss ) ) :
		$maxitems = $rss->get_item_quantity( $num ); 
    	$rss_items = $rss->get_items( 0, $maxitems );  
	endif;

	$buildMenu = '<ul class="rss-menu two-col">';
	$buildArchive = '<div class="rss-feed">';	
    
	if ( $maxitems == 0 ) :
		$buildArchive .= '<div>No posts found.</div>';
    else :
		foreach ( $rss_items as $item ) : 
	   		$title = esc_html( $item->get_title() );
			$title = str_replace(' [INFOGRAPHIC]', '', $title);
			$hash = str_replace(' ', '-', $title);
			$hash = str_replace(array('.', '?', '[', ']', "’", "'", '"', ';', ':', '(', ')' ), '', $hash);

			$permalink = esc_url( $item->get_permalink() );
			$content = wp_kses_post( $item->get_content() );
			$date = $item->get_date('F j, Y');

	   		$buildMenu .= '<li><div class="link"><a href="#'.$hash.'" title="'.$date.'">'.$title.'</a></div><div class="meta-data">'.$date.'</div></li>';
	   		$buildArchive .= '<div id="'.$hash.'"><h2><a href="'.$permalink.'" title="'.$title.'">'.$title.'</a></h2><div class="meta-data">'.$date.'</div>';
			$buildArchive .= $content.'</div>';
        endforeach; 
	endif;
	
	$buildMenu .= '</ul>';
	$buildArchive .= '</div>';
	
	$displayArchive = $buildHeader;
	if ( $menu == "true" ) $displayArchive .= $buildMenu;
	$displayArchive .= $buildArchive;	
	return $displayArchive;
}


?>