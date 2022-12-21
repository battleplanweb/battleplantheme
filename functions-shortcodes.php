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
	
	if ( $data == "area" ) return $GLOBALS['customer_info']['area-before'].$GLOBALS['customer_info']['area'].$GLOBALS['customer_info']['area-after'];
	
	if ( strpos($data, 'phone') !== false ) :
		$phoneBasic = strpos($data, 'replace') !== false ? $GLOBALS['customer_info'][$data] : $GLOBALS['customer_info']['area'].'-'.$GLOBALS['customer_info']['phone'];
		$phoneFormat = $GLOBALS['customer_info']['area-before'].$GLOBALS['customer_info']['area'].$GLOBALS['customer_info']['area-after'].$GLOBALS['customer_info']['phone'];		
		if ( strpos($data, 'mm-bar-phone') !== false ) :
			$openMessage = is_biz_open() ? "<span>Call Us, We're Open!</span>" : "";
			$phoneFormat = do_shortcode('<div class="mm-bar-btn mm-bar-phone call-btn" aria-hidden="true">'.$openMessage.'</div><span class="sr-only">Call Us</span>');
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
 	$googleInfo = get_option('bp_gbp_update');
	return esc_attr($a['detail']) == 'rating' ? number_format($googleInfo['google-rating'], 1, '.', ',') : $googleInfo['google-reviews'];
}		
		
// Returns current year
add_shortcode( 'get-year', 'battleplan_getYear' );
function battleplan_getYear() { return date("Y"); }
 
// Returns current month
add_shortcode( 'get-month', 'battleplan_getMonth' );
function battleplan_getMonth($atts, $content = null) {
	$a = shortcode_atts( array( 'style'=>'full', ), $atts );
	$style = esc_attr($a['style']);	
	if ( $style == "abbr" || $style == "short" ) : 
		return date("M");	
	elseif ( $style == "numeric" ) : 
		return date("n");
	else: 
		return date("F"); 
	endif;
}
 
// Returns current day of week
add_shortcode( 'get-day', 'battleplan_getDay' );
function battleplan_getDay($atts, $content = null) {
	$a = shortcode_atts( array( 'style'=>'full', ), $atts );
	$style = esc_attr($a['style']);	
	if ( $style == "abbr" || $style == "short" ) : 
		return date("D");	
	elseif ( $style == "numeric" ) : 
		return date("j");
	elseif ( $style == "suffix" ) : 
		return date("jS");	
	else: 
		return date("l"); 
	endif;
}

// Get the Number of Years in Business
add_shortcode( 'get-years', 'battleplan_getYears' );
function battleplan_getYears($atts, $content = null) {
	$a = shortcode_atts( array( 'start'=>'', 'label'=>'', 'mult'=>'1'  ), $atts );
	$currYear=date("Y"); 
	$years = ( $currYear - (float)esc_attr($a['start']) ) * (float)esc_attr($a['mult']);
	$label = $years == 1 ? "1 year" : $years." years";
	return esc_attr($a['label']) == "no" || esc_attr($a['label']) == "false" ? $years : $label;
}

// Get the Current Season and Print HTML Accordingly 
add_shortcode( 'get-season', 'battleplan_getSeason' );
function battleplan_getSeason($atts, $content = null) {
	$a = shortcode_atts( array( 'spring'=>'', 'summer'=>'', 'fall'=>'', 'winter'=>'',  ), $atts );
	$summer = wp_kses_post($a['summer']);	
	$winter = wp_kses_post($a['winter']);	
	$spring = wp_kses_post($a['spring']) != '' ? wp_kses_post($a['spring']) : $summer;	
	$fall = wp_kses_post($a['fall']) != '' ? wp_kses_post($a['fall']) : $winter;	

	if (date("m")>="03" && date("m")<="05") : 
		return $spring; 
	elseif (date("m")>="06" && date("m")<="09") : 
		return $summer; 
	elseif (date("m")>="10" && date("m")<="11") : 
		return $fall; 
	else: 
		return $winter;
	endif; 
}

// Clear space under a "low-hanging" element 
add_shortcode( 'clear', 'battleplan_clearFix' );
function battleplan_clearFix( $atts, $content = null ) {
	$a = shortcode_atts( array( 'height'=>'0px', 'class'=>'' ), $atts );
	$class = esc_attr($a['class']) != '' ? ' '.esc_attr($a['class']) : '';

	return '<div class="clearfix'.$class.'" style="height:'.esc_attr($a['height']).'"></div>';
}

// Find Number of Days Between Two Dates 
add_shortcode( 'days-ago', 'battleplan_daysAgo' );
function battleplan_daysAgo( $atts, $content = null ) {
	$a = shortcode_atts( array( 'oldest'=>'', 'newest'=>'today' ), $atts );
	$newest = esc_attr($a['newest']) == "today" || esc_attr($a['newest']) == "now" ? time() : strtotime(esc_attr($a['newest']));
	$oldest = strtotime(esc_attr($a['oldest']));	
	
	return abs(round(($newest - $oldest) / 86400));
}

// Load the Featured Image, Title, Excerpt, Content or Permalink from a Separate Post, Page or Custom Post Type
add_shortcode( 'get-wp-page', 'battleplan_getWordPressPage' );
function battleplan_getWordPressPage( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'page', 'id'=>'', 'slug'=>'', 'title'=>'', 'display'=>"content" ), $atts );
	$type = esc_attr($a['type']);
	$slug = esc_attr($a['slug']);
	$title = esc_attr($a['title']);
	$display = esc_attr($a['display']);

	if ( esc_attr($a['id']) != "" ) : $pageID = esc_attr($a['id']); 
	elseif ( $slug != "" ) : 
	 	if ( get_page_by_path( $slug, OBJECT, $type ) ) :
			$pageID = get_page_by_path( $slug, OBJECT, $type )->ID; 
		else:
			$getCPT = get_post_types();  
			foreach ($getCPT as $postType) :
				if ( get_page_by_path($slug, OBJECT, $postType) ) : 
					$pageID = get_page_by_path($slug, OBJECT, $postType)->ID; 
					break; 
				endif;
			endforeach;		
		endif;	
	elseif ( $title != "" ) : 
	 	if ( get_page_by_title( $title, OBJECT, $type ) ) :
			$pageID = get_page_by_title( $title, OBJECT, $type )->ID; 
		else:
			$getCPT = get_post_types();  
			foreach ($getCPT as $postType) :
				if ( get_page_by_title( $title, OBJECT, $postType) ) : 
					$pageID = get_page_by_title( $title, OBJECT, $postType)->ID; 
					break; 
				endif;
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
		if ( get_page_by_path( $page_slug_home, OBJECT, 'elements' ) ) : 
			$page_data = get_page_by_path( $page_slug_home, OBJECT, 'elements' ); 
		endif;
	endif;
	
	if ( !isset($page_data) ) :
		if ( get_page_by_path( $page_slug, OBJECT, 'elements' ) ) : 
			$page_data = get_page_by_path( $page_slug, OBJECT, 'elements' ); 
		endif;
	endif;

	if ( isset($page_data) && get_post_status($page_data->ID) == "publish" ) return apply_filters('the_content', $page_data->post_content); 	
}

// Returns website address (for privacy policy, etc)
add_shortcode( 'get-domain', 'battleplan_getDomain' );
function battleplan_getDomain( $atts, $content = null ) {
	$a = shortcode_atts( array( 'link'=>'false', ), $atts );
	return esc_attr($a['link']) == "false" ? esc_url(get_site_url()) : '<a href="'.esc_url(get_site_url()).'">'.esc_url(get_site_url()).'</a>';
}

// Returns website address (minus https and with/without .com)
add_shortcode( 'get-domain-name', 'battleplan_getDomainName' );
function battleplan_getDomainName( $atts, $content = null ) {
	$a = shortcode_atts( array( 'ext'=>'false', ), $atts );
	$parts = explode('.', parse_url(esc_url(get_site_url()), PHP_URL_HOST));	
	return esc_attr($a['ext']) != "false" ? $parts[0].'.'.$parts[1] : $parts[0];
}

// Returns url of page (minus domain, choose whether to include variables)
add_shortcode( 'get-url', 'battleplan_getURL' );
function battleplan_getURL( $atts, $content = null ) {
	$a = shortcode_atts( array( 'var'=>'true', ), $atts );
	return esc_attr($a['var']) == "false" ? parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) : $_SERVER['REQUEST_URI'];
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
	$args = esc_attr($a['tax']) == '' ? array( 'post_type'=>$postType, 'post_status'=>$postStatus, 'posts_per_page'=>-1) :  array( 'post_type'=>$postType, 'post_status'=>$postStatus, 'posts_per_page'=>-1, 'tax_query'=>array( 'relation'=>'AND', array( 'taxonomy'=>esc_attr($a['tax']), 'field'=>'slug', 'terms'=>esc_attr($a['term']) )));

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

// Display Business Hours
add_shortcode( 'get-hours', 'battleplan_addBusinessHours' );
function battleplan_addBusinessHours( $atts, $content = null ) {
	$a = shortcode_atts( array( 'direction'=>'vert', 'start'=>'sun', 'abbr'=>'true' ), $atts );
	$direction = esc_attr($a['direction']) == "vert" ? "vert" : "horz";
	$days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
	if ( esc_attr($a['start']) == "sun" || esc_attr($a['start']) == "sunday" ) :
		array_unshift($days, 'sunday');
	else:
		array_push($days, 'sunday');
	endif;		 
	
	$buildHours = '<div class="office-hours '.$direction.'">';
	
	foreach ( $days as $day ) :
		$buildHours .= '<div class="row row-'.substr($day, 0, 3).'">';
		$printDay = esc_attr($a['abbr']) == "true" ? substr($day, 0, 3) : $day;
		$buildHours .= '<div class="col-day">'.$printDay.'</div>';
		$buildHours .= '<div class="col-all">[get-biz info="hours-'.substr($day, 0, 3).'"]</div>';
		$buildHours .= '</div>';
	endforeach;
	
	$buildHours .= '</div>';
	
	return do_shortcode($buildHours);
}	

// Print text based on whether business is open or not
add_shortcode( 'get-hours-open', 'battleplan_isOpen' );
function battleplan_isOpen($atts, $content = null) {
	$a = shortcode_atts( array( 'open'=>'', 'closed'=>'' ), $atts );
	return is_biz_open() ? esc_attr($a['open']) : esc_attr($a['closed']);
}	

// Choose random text from given choices
add_shortcode( 'get-random-text', 'battleplan_getRandomText' );
function battleplan_getRandomText($atts, $content = null) {
	$a = shortcode_atts( array( 'cookie'=>'true', 'text1'=>'', 'text2'=>'', 'text3'=>'', 'text4'=>'', 'text5'=>'', 'text6'=>'', 'text7'=>'',  ), $atts );
	$textArray = array( wp_kses_post($a['text1']), wp_kses_post($a['text2']), wp_kses_post($a['text3']), wp_kses_post($a['text4']), wp_kses_post($a['text5']), wp_kses_post($a['text6']), wp_kses_post($a['text7']) );	
	$textArray = array_filter($textArray);
	$num = count($textArray) - 1;	
	$rand = esc_attr($a['cookie']) != "false" && $_COOKIE['random-text'] != '' ? $_COOKIE['random-text']: rand(0,$num);
	
	$printText = $textArray[$rand];
	
	if ( $rand == $num ) : 
		$rand = 0; 
	else:
		$rand++; 
	endif;	
	if ( $cookie != "false" ) setcookie('random-text', $rand, time() + (86400 * 7), '/', '', true, false);

	return $printText;
}

// Display a random photo from tagged images 
add_shortcode( 'get-random-image', 'battleplan_getRandomImage' );
function battleplan_getRandomImage($atts, $content = null ) {	
	$a = shortcode_atts( array( 'id'=>'', 'tag'=>'', 'size'=>'thumbnail', 'link'=>'no', 'number'=>'1', 'offset'=>'0', 'align'=>'left', 'class'=>'', 'order_by'=>'recent', 'order'=>'asc', 'shuffle'=>'no', 'lazy'=>'true' ), $atts );
	$tag = esc_attr($a['tag']);	
	$tags = $tag == "page-slug" ? _PAGE_SLUG : explode( ',', $tag );
	$size = esc_attr($a['size']);	
	$link = esc_attr($a['link']);	
	$orderBy = esc_attr($a['order_by']);		
	$lazy = esc_attr($a['lazy']) == "true" ? "lazy" : "eager";
	$id = esc_attr($a['id']) == "current" ? get_the_ID() : esc_attr($a['id']);		
	$class = esc_attr($a['class']) != '' ? " ".esc_attr($a['class']) : "";
	$align = esc_attr($a['align']) != '' ? "align".esc_attr($a['align']) : "";
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg, image/gif, image/jpg, image/png, image/webp', 'posts_per_page'=>esc_attr($a['number']), 'offset'=>esc_attr($a['offset']), 'order'=>esc_attr($a['order']));

	$args['orderby']='meta_value_num';	
	if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; 	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; 	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day";
	elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; 
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; 
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed";
	else : $args['orderby']=$orderBy;
	endif;		
	
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
		$buildImage .= '<img class="wp-image-'.$getID.' random-img '.$tags[0].'-img '.$align.' size-'.$size.$class.'" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';	
		if ( $link == "yes" ) $buildImage .= '</a>';	
		$imageArray[] = $buildImage;
		
		battleplan_countTease( $getID );	
	
	endwhile; wp_reset_postdata(); endif;	
	if ( esc_attr($a['shuffle']) != "no" ) shuffle($imageArray); 
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
	$tags = explode( ',', esc_attr($a['tag']) );
	$link = esc_attr($a['link']);	
	$orderBy = esc_attr($a['order_by']);		
	$shuffle = esc_attr($a['shuffle']);	
	$lazy = esc_attr($a['lazy']) == "true" ? "lazy" : "eager";
	$class = esc_attr($a['class']) != '' ? " ".$class : "";
	$id = esc_attr($a['id']) == "current" ? get_the_ID() : esc_attr($a['id']);
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg, image/gif, image/jpg, image/png, image/webp', 'posts_per_page'=>$num, 'offset'=>esc_attr($a['offset']), 'order'=>esc_attr($a['order']), 'tax_query'=>array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags )));

	$args['orderby']='meta_value_num';	
	if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; 	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; 	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day";
	elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; 
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; 
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed";
	else : $args['orderby']=$orderBy;
	endif;		
	
	if ( $id != '' ) $args['post_parent']=$id;

	$image_query = new WP_Query($args);		
	$imageArray = array();

	if( $image_query->have_posts() ) : 
		while ($image_query->have_posts() ) : 
			$image_query->the_post();
			$getID = get_the_ID();
			$image = wp_get_attachment_image_src( $getID, $size );
			$imgSet = wp_get_attachment_image_srcset( $getID, $size );

			$getImage = "";
			if ( $link == "yes" ) $getImage .= '<a href="'.$image[0].'">';
			$getImage .= '<img class="random-img '.$tags[0].'-img" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';
			if ( $link == "yes" ) $getImage .= '</a>';

			battleplan_countTease( $getID );	

			$imageArray[] = do_shortcode('[col class="col-row-of-pics'.$class.'"]'.$getImage.'[/col]');		
			$ratioArray[] = $image[2] / $image[1];	
		endwhile;
		wp_reset_postdata(); 
	endif;
	
	if ( $shuffle == "yes" || $shuffle == "true" || $shuffle == "random" ) : 
		shuffle($imageArray); 
	elseif ( $shuffle == "peak" || $shuffle == "valley" ) :	
		if ( $shuffle == "peak" ) :	
			array_multisort($ratioArray, SORT_ASC, SORT_NUMERIC, $imageArray, SORT_ASC);
		else:
			array_multisort($ratioArray, SORT_DESC, SORT_NUMERIC, $imageArray, SORT_DESC); 
		endif;
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
		if ( $rand == 1) :
			array_multisort($ratioArray, SORT_ASC, SORT_NUMERIC, $imageArray, SORT_ASC); 
		else: 
			array_multisort($ratioArray, SORT_DESC, SORT_NUMERIC, $imageArray, SORT_DESC); 
		endif;
		$result= array();
		$count = count($imageArray);
		for ($counter=0; $counter * 2 < $count; $counter++) :
			$anticounter = $count - $counter - 1;
			array_push($result, $imageArray[$anticounter]);
			if ($counter != $anticounter) array_push($result, $imageArray[$counter]);
		endfor;
		$left = array_slice($result, 0, count($result)/2);
		$right = array_slice($result, count($result)/2);
		$imageArray = array_merge($right, array_reverse($left));
	endif;
							   
	return do_shortcode('[layout grid="'.$col.'e" valign="'.esc_attr($a['valign']).'"]'.printArray($imageArray).'[/layout]'); 
}

// Build an archive
add_shortcode( 'build-archive', 'battleplan_getBuildArchive' );
function battleplan_getBuildArchive($atts, $content = null) {	
	$a = shortcode_atts( array( 'type'=>'', 'count_view'=>'false', 'thumb_only'=>'false', 'show_btn'=>'false', 'btn_text'=>'Read More', 'btn_pos'=>'outside', 'show_title'=>'true', 'title_pos'=>'outside', 'show_date'=>'false', 'show_author'=>'false', 'show_social'=>'false', 'show_excerpt'=>'true', 'show_content'=>'false', 'add_info'=>'', 'show_thumb'=>'true', 'no_pic'=>'', 'size'=>'thumbnail', 'pic_size'=>'1/3', 'text_size'=>'', 'accordion'=>'false', 'link'=>'post', 'truncate'=>'false' ), $atts );
	$type = esc_attr($a['type']);
	$truncate = esc_attr($a['truncate']);
	$showBtn = esc_attr($a['show_btn']);	
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
		$content = $truncate == "true" || $truncate == "yes" ? truncateText($content) : truncateText($content, $truncate);
	endif;
	$showThumb = esc_attr($a['show_thumb']) != "false" ? "true" : esc_attr($a['show_thumb']);
	$size = esc_attr($a['size']);
	$picSize = esc_attr($a['pic_size']);	
	$textSize = esc_attr($a['text_size']);		
	$link = esc_attr($a['link']);	
	if ( strpos($link, 'cf-') === 0 ) : 
		$linkLoc = esc_url(get_field(str_replace('cf-', '', $link)));
	elseif ( $type == "testimonials" ) :
		$linkLoc = "/testimonials/";
	elseif ( $link == "false" || $link == "no" ) : 
		$link = "false"; $linkLoc = "";
	elseif ( $link == "" || $link == "post" ) : 
		$linkLoc = esc_url(get_the_permalink(get_the_ID()));	
	else: 
		$linkLoc = $link;	
	endif;
	$noPic = esc_attr($a['no_pic']) == "" ? "false" : esc_attr($a['no_pic']);	
	$picADA = $titleADA = "";
	if ( $showBtn == "true" ) : 
		$picADA = " ada-hidden='true'"; 
		$titleADA = " aria-hidden='true' tabindex='-1'";
	elseif ( $showTitle != "false" ) : 
		$picADA = " ada-hidden='true'"; 
	endif;
	$archiveMeta = $buildBtn = '';
		
	if ( has_post_thumbnail() && $showThumb == "true" ) : 	
		$meta = wp_get_attachment_metadata( get_post_thumbnail_id( get_the_ID() ) );
		$thumbW = $meta['sizes'][$size]['width'];
		$thumbH = $meta['sizes'][$size]['height'];
	
		$buildImg = do_shortcode('[img size="'.$picSize.'" class="image-'.$type.'" link="'.$linkLoc.'" '.$picADA.']'.get_the_post_thumbnail( get_the_ID(), $size, array( 'class'=>'img-archive img-'.$type, 'style'=>'aspect-ratio:'.$thumbW.'/'.$thumbH )).'[/img]'); 
		if ( $textSize == "" ) : 
			$textSize = getTextSize($picSize); 
		endif;	
	elseif ( $noPic != "false") : 	
		$buildImg = do_shortcode("[img size='".$picSize."' class='image-".$type." block-placeholder placeholder-".$type."' link='".$linkLoc."' ".$picADA."]".wp_get_attachment_image( $noPic, $size, array( 'class'=>'img-archive img-'.$type ))."[/img]"); 
		if ( $textSize == "" ) : 
			$textSize = getTextSize($picSize); 
		endif;		
	else : 
		$buildImg = ""; $textSize = "100";
	endif;
	
	if ( $type == "testimonials" ) {
		$testimonialPhone = esc_attr(get_field( "testimonial_phone" ));
		$testimonialEmail = esc_attr(get_field( "testimonial_email" ));
		$testimonialTitle = esc_attr(get_field( "testimonial_title" ));
		$testimonialWeb = esc_attr(get_field( "testimonial_website" ));
		$testimonialLoc = esc_attr(get_field( "testimonial_location" ));
		$testimonialPlatform = strtolower(esc_attr(get_field( "testimonial_platform" )));
		$testimonialRate = esc_attr(get_field( "testimonial_rating" ));	
		$testimonialMisc1 = esc_attr(get_field( "testimonial_misc1" ));	
		$testimonialMisc2 = esc_attr(get_field( "testimonial_misc2" ));	
		$testimonialMisc3 = esc_attr(get_field( "testimonial_misc3" ));	
		$testimonialMisc4 = esc_attr(get_field( "testimonial_misc4" ));
		
		$buildCredentials = "<div class='testimonials-credential testimonials-name'>".esc_attr(get_field( "testimonial_name" ));
		if ( $testimonialTitle ) $buildCredentials .= "<span class='testimonials-title'>, ".$testimonialTitle."</span>";
		$buildCredentials .= "</div>";
		if ( esc_attr(get_field( "testimonial_biz" )) ) :
			$buildCredentials .= "<div class='testimonials-credential testimonials-business'>";
			if ( $testimonialWeb ) $buildCredentials .= "<a href='https://".$testimonialWeb."' target='_blank'>"; 
			$buildCredentials .= esc_attr(get_field( "testimonial_biz" ));
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
			$content = $truncate == "true" || $truncate == "yes" ? truncateText($content) : truncateText($content, $truncate);
		endif;
		
		$archiveBody = '[txt class="testimonials-quote"][p]'.$content.'[/p][/txt][txt size="11/12" class="testimonials-credentials"]'.$buildCredentials.'[/txt][txt size="1/12" class="testimonials-platform testimonials-platform-'.$testimonialPlatform.'"][/txt]';
	} else {
		if ( esc_attr($a['accordion']) == "true" ) :		
			$archiveBody = '[accordion title="'.esc_html(get_the_title()).'" excerpt="'.wp_kses_post(get_the_excerpt()).'"]'.$content.'[/accordion]';		
		else :		
			$archiveMeta = $archiveBody = "";
			if ( $showTitle != "false" ) :
				$archiveMeta .= "<h3 data-count-view=".esc_attr($a['count_view']).">";
				if ( $showContent != "true" && $link != "false" ) $archiveMeta .= '<a href="'.$linkLoc.'" class="link-archive link-'.get_post_type().'"'.$titleADA.'>';	
				if ( $showTitle == "true" ) :
					$archiveMeta .= esc_html(get_the_title()); 
				else:
					$archiveMeta .= $showTitle; 
				endif;
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
		$ada = $type == "testimonials" ? " testimonials" : ' about '.esc_html(get_the_title()); 	
		$buildBtn = do_shortcode('[btn class="button-'.$type.'" link="'.$linkLoc.'" ada="'.$ada.'"]'.esc_attr($a['btn_text']).'[/btn]'); 	
	endif;
			
	if ( esc_attr($a['thumb_only']) == "true" ) :
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
	$a = shortcode_atts( array( 'num'=>'1', 'offset'=>'0', 'leeway'=>'0', 'type'=>'post', 'tax'=>'', 'terms'=>'', 'field_key'=>'', 'field_value'=>'', 'field_compare'=>'IN', 'orderby'=>'recent', 'sort'=>'asc', 'show_title'=>'true', 'title_pos'=>'outside', 'show_date'=>'false', 'show_author'=>'false', 'show_excerpt'=>'true', 'show_social'=>'false', 'show_btn'=>'true', 'button'=>'Read More', 'btn_pos'=>'inside', 'show_content'=>'false', 'thumb_only'=>'false', 'thumb_col'=>'1', 'thumbnail'=>'force', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'size'=>'thumbnail', 'pic_size'=>'1/3', 'text_size'=>'', 'link'=>'post', 'truncate'=>'true' ), $atts );
	$num = esc_attr($a['num']);	
	$offset = esc_attr($a['offset']) == '0' ? rand(0, esc_attr($a['leeway'])) :	esc_attr($a['offset']);
	$postType = esc_attr($a['type']);	
	$title = esc_attr($a['show_title']);	
	$orderBy = esc_attr($a['orderby']);	
	$titlePos = esc_attr($a['title_pos']);	
	$showDate = esc_attr($a['show_date']);	
	$showExcerpt = esc_attr($a['show_excerpt']);		
	$showContent = esc_attr($a['show_content']);	
	$showAuthor = esc_attr($a['show_author']);	
	$showSocial = esc_attr($a['show_social']);	
	$showBtn = esc_attr($a['show_btn']);	
	$taxonomy = esc_attr($a['tax']);
	$term = esc_attr($a['terms']);
	$terms = explode( ',', $term );
	$fieldKey = esc_attr($a['field_key']);
	$fieldValue = esc_attr($a['field_value']);
	$fieldValues = explode( ',', $fieldValue );
	$exclude = explode(',', esc_attr($a['exclude'])); 
	if ( esc_attr($a['x_current']) == "true" ) :
		global $post; 
		array_push($exclude, $post->ID);
	endif;	
	$picSize = esc_attr($a['pic_size']);	
	$thumbOnly = esc_attr($a['thumb_only']);
	if ( $thumbOnly == "true" ) : 
		$title = $showDate = $showExcerpt = $showContent = $showAuthor = $showSocial = $showBtn = "false";
		$picSize = "100";
		$showThumb = "true";
	endif;
	$combinePosts = '';

	$args = array ('posts_per_page'=>$num, 'offset'=>$offset, 'date_query'=>array( array( 'after'=>esc_attr($a['start']), 'before'=>esc_attr($a['end']), 'inclusive'=>true, ), ), 'order'=>esc_attr($a['sort']), 'post_type'=>$postType, 'post__not_in'=>$exclude);

	$args['orderby']='meta_value_num';	
	if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; 	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; 	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day";
	elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; 
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; 
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed";
	else : $args['orderby']=$orderBy;
	endif;		
	
	if ( $taxonomy && $term ) $args['tax_query'] = array( array('taxonomy'=>$taxonomy, 'field'=>'slug', 'terms'=>$terms ));	
	if ( $fieldKey && $fieldValue ) $args['meta_query'] = array( array('key'=>$fieldKey, 'value'=>$fieldValues, 'compare'=>esc_attr($a['field_compare']) ));

	global $post; 
	$getPosts = new WP_Query( $args );
	if ( $getPosts->have_posts() ) : 
		while ( $getPosts->have_posts() ) : 
			$getPosts->the_post(); 	
			
			$showPost = do_shortcode('[build-archive type="'.$postType.'" count_view="'.$countView.'" thumb_only="'.$thumbOnly.'" show_btn="'.$showBtn.'" btn_text="'.esc_attr($a['button']).'" btn_pos="'.esc_attr($a['btn_pos']).'" show_title="'.$title.'" title_pos="'.$titlePos.'" show_date="'.$showDate.'" show_excerpt="'.$showExcerpt.'" show_social="'.$showSocial.'" show_content="'.$showContent.'" show_author="'.$showAuthor.'" size="'.esc_attr($a['size']).'" pic_size="'.$picSize.'" text_size="'.esc_attr($a['text_size']).'" link="'.esc_attr($a['link']).'" truncate="'.esc_attr($a['truncate']).'"]');	

			if ( $num > 1 ) $showPost = do_shortcode('[col]'.$showPost.'[/col]');	
			if ( has_post_thumbnail() || esc_attr($a['thumbnail']) != "force" ) $combinePosts .= $showPost;
		endwhile; 
		wp_reset_postdata(); 
	endif;
	
	if ( $thumbOnly == "true" ) $combinePosts = '<div class="random-post random-posts thumb-only thumb-col-'.esc_attr($a['thumb_col']).'">'.$combinePosts.'</div>';
	
	return $combinePosts;
}

// Display posts & images in a Bootstrap slider 
add_shortcode( 'get-post-slider', 'battleplan_getPostSlider' );
function battleplan_getPostSlider($atts, $content = null ) {
	wp_enqueue_script( 'battleplan-carousel', get_template_directory_uri().'/js/bootstrap-carousel.js', array('jquery-core'), _BP_VERSION, false );		
	wp_enqueue_script( 'battleplan-carousel-slider', get_template_directory_uri().'/js/script-bootstrap-slider.js', array('battleplan-carousel'), _BP_VERSION, false );	

	$a = shortcode_atts( array( 'type'=>'testimonials', 'auto'=>'yes', 'interval'=>'6000', 'loop'=>'true', 'num'=>'4', 'offset'=>'0', 'pics'=>'yes', 'caption'=>'no', 'controls'=>'yes', 'controls_pos'=>'below', 'indicators'=>'no', 'justify'=>'center', 'pause'=>'true', 'orderby'=>'recent', 'order'=>'asc', 'post_btn'=>'', 'all_btn'=>'View All', 'show_date'=>'false', 'show_author'=>'false', 'show_excerpt'=>'true', 'show_content'=>'false', 'link'=>'', 'pic_size'=>'1/3', 'text_size'=>'', 'slide_type'=>'box', 'slide_effect'=>'fade', 'tax'=>'', 'terms'=>'', 'tag'=>'', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'size'=>'thumbnail', 'id'=>'', 'mult'=>'1', 'class'=>'', 'truncate'=>'true', 'lazy'=>'true', 'blur'=>'false' ), $atts );
	$num = esc_attr($a['num']);	
	$controls = esc_attr($a['controls']);	
	$controlsPos = esc_attr($a['controls_pos']);
	$indicators = esc_attr($a['indicators']);			
	$autoplay = esc_attr($a['auto']);		
	$type = esc_attr($a['type']);		
	$postBtn = esc_attr($a['post_btn']);	
	$allBtn = esc_attr($a['all_btn']);	
	$orderBy = esc_attr($a['orderby']);	
	$order = esc_attr($a['order']);		
	$caption = esc_attr($a['caption']);	
	$slideType = esc_attr($a['slide_type']);		
	$slideEffect = esc_attr($a['slide_effect']);		
	$tag = esc_attr($a['tag']);	
	$tags = explode( ',', $tag );
	$taxonomy = esc_attr($a['tax']);
	$term = esc_attr($a['terms']);
	$size = esc_attr($a['size']);
	$exclude = explode (",", esc_attr($a['exclude'])); 
	if ( esc_attr($a['x_current']) == "true" ) :
		global $post; 
		array_push($exclude, $post->ID);
	endif;	
	$link = esc_attr($a['link']);		
	$id = esc_attr($a['id']);	
	$blur = esc_attr($a['blur']) == "true" ? " slider-blur" : "";	
	$lazy = esc_attr($a['lazy']) == "true" ? "lazy" : "eager";
	$mult = esc_attr($a['mult']);		
	if ( $mult == 1 ) : 
		$multSize = $imgSize = 100; 
	elseif ( $mult == 2 ) :
		$multSize = $imgSize = 50;	
	elseif ( $mult == 3 ) : 
		$multSize = $imgSize = 33;
	elseif ( $mult == 4 ) : 
		$multSize = $imgSize = 25;
	elseif ( $mult == 5 ) : 
		$multSize = $imgSize = 20;	
	else : 
		$multSize = $imgSize = 17; 
	endif;
	if ( $mult > 1 ) $num--;
	$multDisplay = 0;
	$numDisplay = -1;
	$rowDisplay = 0;
	$sliderNum = rand(100,999);
	
	$controlClass = $controls == "yes" && $indicators == "no" ? " only-controls" : "";
	$showBtn = $postBtn == "" ? "false" : "true";	
	$pause = esc_attr($a['pause']) == "true" ? "hover" : "false";			
		
	if ( $link == "" ) : 
		$linkTo = "/".$type."/"; 
	elseif ( $link == "none" || $link == "false" || $link == "no" ) : 
		$link = "none"; 
	endif;		
	
	if ( $type == "image" || $type == "images" ) :
		$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg, image/gif, image/jpg, image/png, image/webp', 'posts_per_page'=>$num, 'order'=>$order);

		$args['orderby']='meta_value_num';	
		if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; 	
		elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; 	
		elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day";
		elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; 
		elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; 
		elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed";
		else : $args['orderby']=$orderBy;
		endif;		
	
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

			$buildIndicators = '<ol class="carousel-indicators" style="justify-content: '.esc_attr($a['justify']).'">';
			$buildInner = '<div class="carousel-inner">';

			while ($image_query->have_posts() ) :
				$image_query->the_post();
				$numDisplay++; 	
				if ( $rowDisplay == 0 ) :
				 	$active = $numDisplay == 0 ? "active" : "";
					$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'" class="'.$active.'"></li>';
					if ( $numDisplay != 0 ) $buildInner .= '</div>';
					$buildInner .= '<div class="'.$active.' carousel-item carousel-item-'.$type.'">';
				endif;	

				$image = wp_get_attachment_image_src(get_the_ID(), $size );
				$imgSet = wp_get_attachment_image_srcset(get_the_ID(), $size );		
		
				$linkTo = $buildImg = '';
				if ( $link == "alt" ) $linkTo = readMeta(get_the_ID(), '_wp_attachment_image_alt', true);				
				if ( $link == "description" ) $linkTo = esc_html(get_post(get_the_ID())->post_content);
				if ( $link != "none" ) $buildImg = "<a href='".$linkTo."' class='link-archive link-".$type."'>"; 	

				$buildImg .= '<img class="img-slider '.$tags[0].'-img" loading="'.$lazy.'" src = "'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta(get_the_ID(), "_wp_attachment_image_alt", true).'">';
	
				battleplan_countTease( get_the_ID() );				 
		
				if ( $caption == "yes" || $caption == "title" ) : 
					$buildImg .= "<div class='caption-holder'><div class='img-caption'>".get_the_title(get_the_ID())."</div></div>";	
				elseif ( $caption == "alt" ) : 
					$buildImg .= "<div class='caption-holder'><div class='img-caption'>".readMeta(get_the_ID(), '_wp_attachment_image_alt', true)."</div></div>";
				endif;
	
				if ( $link != "none" ) $buildImg .= "</a>";	
		
	 			$buildInner .= do_shortcode("[img size='".$imgSize."' class='image-".$type."']".$buildImg."[/img]");
	
				$rowDisplay++;
				if ( $rowDisplay == $mult ) $rowDisplay = 0;	
			endwhile;
			$buildInner .= "<!--div class='clearfix'></div--></div>";
			wp_reset_postdata();
		endif;
	else :
		$args = array ('posts_per_page'=>-1, 'offset'=>esc_attr($a['offset']), 'date_query'=>array( array( 'after'=>esc_attr($a['start']), 'before'=>esc_attr($a['end']), 'inclusive'=>true, ), ), 'order'=>$order, 'post_type'=>$type, 'post__not_in'=>$exclude);

		$args['orderby']='meta_value_num';	
		if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; 	
		elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; 	
		elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day";
		elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; 
		elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; 
		elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed";
		else : $args['orderby']=$orderBy;
		endif;		
	
		if ( $taxonomy && $term ) $args['tax_query']=array( array('taxonomy'=>$taxonomy, 'field'=>'slug', 'terms'=>$terms ));
	
		global $post; 
		$fetchPost = new WP_Query( $args );
		if ( $fetchPost->have_posts() ) :	

			$buildIndicators = '<ol class="carousel-indicators">';
			$buildInner = '<div class="carousel-inner">';

			while ( $fetchPost->have_posts() ) : 
				$fetchPost->the_post();
	
				if ( $numDisplay < $num ) : 
					if ( esc_attr($a['pics']) == "no" || has_post_thumbnail() ) :
					
						$numDisplay++; 
						$multDisplay++;
					
						$buildArchive = do_shortcode('[build-archive type="'.$type.'" show_btn="'.$showBtn.'" btn_text="'.$postBtn.'" show_excerpt="'.esc_attr($a['show_excerpt']).'" show_content="'.esc_attr($a['show_content']).'" show_date="'.esc_attr($a['show_date']).'" show_author="'.esc_attr($a['show_author']).'" size="'.$size.'" pic_size="'.esc_attr($a['pic_size']).'" text_size="'.esc_attr($a['text_size']).'" link="'.$link.'" truncate="'.esc_attr($a['truncate']).'"]');	
						
						if ( $multDisplay == 1 ) :
							$active = $numDisplay == 0 ? "active" : "";
							$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'" class="'.$active.'"></li>';
							$buildInner .= '<div class="'.$active.' carousel-item carousel-item-'.$type.'">';
						endif;

						if ( $mult == 1 ) : 
							$buildInner .= $buildArchive;
						else: 
							$buildInner .= do_shortcode('[group size="'.$multSize.'"]'.$buildArchive.'[/group]');						
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
	
	$slideClass = esc_attr($a['class'])." carousel-".$slideType." effect-".$slideEffect;
	
	$buildSlider = '<div id="'.$type.'Slider'.$sliderNum.'" class="carousel slide slider slider-'.$type.' '.$slideClass.' mult-'.$mult.$blur.'" data-interval="'.esc_attr($a['interval']).'" data-pause="'.$pause.'" data-wrap="'.esc_attr($a['loop']).'" data-touch="true"';	
	
	if ( $autoplay == "yes" || $autoplay == "true" ) $buildSlider .= ' data-auto="true"';
	
	$buildSlider .= '>';	
	
	if ( $controlsPos == "above" || $controlsPos == "before" ) :
		if ( $controls == "yes" ) : 
			$buildSlider .= $buildControls; 
		else: 
			if ( $allBtn != "" ) :
				$buildSlider .= $viewMoreBtn; 
			endif;
		endif;
	endif;

	$buildSlider .= $buildInner;

	if ( $indicators == "yes" ) $buildSlider .= $buildIndicators;	

	if ( $controlsPos != "above" && $controlsPos != "before" ) :
		if ( $controls == "yes" ) : 
			$buildSlider .= $buildControls; 
		else: 
			if ( $allBtn != "" ) : 
				$buildSlider .= $viewMoreBtn; 
			endif; 
		endif;
	endif;
	
	$buildSlider .= '</div>';	

	return $buildSlider;
}

// Display row of logos that slide from left to right 
add_shortcode( 'get-logo-slider', 'battleplan_getLogoSlider' );
function battleplan_getLogoSlider($atts, $content = null ) {
	wp_enqueue_script( 'battleplan-logo-slider', get_template_directory_uri().'/js/script-logo-slider.js', array(), _BP_VERSION, false );	

	$a = shortcode_atts( array( 'num'=>'-1', 'space'=>'10', 'size'=>'full', 'max_w'=>'85', 'tag'=>'', 'package'=>'', 'order_by'=>'rand', 'order'=>'ASC', 'shuffle'=>'false', 'speed'=>'slow', 'delay'=>'0', 'pause'=>'no', 'link'=>'false', 'lazy'=>'true', 'direction'=>'normal'), $atts );
	$tags = explode( ',', esc_attr($a['tag']) );
	$orderBy = esc_attr($a['order_by']);		
	$link = esc_attr($a['link']);		
	$package = esc_attr($a['package']);	
	$lazy = esc_attr($a['lazy']) == "true" ? "lazy" : "eager"; 
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg, image/gif, image/jpg, image/png, image/webp', 'posts_per_page'=>esc_attr($a['num']), 'order'=>esc_attr($a['order']), 'tax_query'=>array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags )));

	$args['orderby']='meta_value_num';	
	if ( $orderBy == 'views-today' ) : $args['meta_key']="log-views-today"; 	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="log-views-total-7day"; 	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="log-views-total-30day";
	elseif ( $orderBy == 'views-90day' ) : $args['meta_key']="log-views-total-90day"; 
	elseif ( $orderBy == 'views-365day' || $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="log-views-total-365day"; 
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="log-last-viewed";
	else : $args['orderby']=$orderBy;
	endif;		

	$image_query = new WP_Query($args);		
	$imageArray = array();
	
	if ( $image_query->have_posts() ) : 
		while ($image_query->have_posts() ) : 
			$image_query->the_post();
			$totalNum = $image_query->post_count;
			$image = wp_get_attachment_image_src( get_the_ID(), esc_attr($a['size']) );
			$getImage = "";
			if ( $link != "false" ) $getImage .= '<a href="'.$image[0].'">';
			$getImage .= '<img class="logo-img '.$tags[0].'-img" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" alt="'.readMeta(get_the_ID(), '_wp_attachment_image_alt', true).'">';
			if ( $link != "false" ) $getImage .= '</a>';
			$imageArray[] = '<span>'.$getImage.'</span>';			
		endwhile; 
		wp_reset_postdata(); 
	endif;	
	
	if ( $package == "hvac" ) :
		$addLogos = array( "amana","american-standard","bryant","carrier","comfortmaker","goodman","heil","honeywell","lennox","rheem","ruud","samsung","tempstar","trane","york" );		
		for ( $i=0; $i < count($addLogos); $i++ ) :	
			$alt = "We service ".ucwords(strtolower(str_replace(" ", "-", $addLogos[$i])))." air conditioners, heaters and other HVAC equipment.";
			$imageURL = "../wp-content/themes/battleplantheme/common/hvac-".$addLogos[$i]."/".$addLogos[$i]."-sidebar-logo.png";
			$imagePath = get_template_directory()."/common/hvac-".$addLogos[$i]."/".$addLogos[$i]."-sidebar-logo.png";			
			list($width, $height) = getimagesize($imagePath);
			
			$getImage = "";
			$getImage .= '<img class="logo-img '.$package.'-logo-img" loading="'.$lazy.'" src="'.$imageURL.'" width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" alt="'.$alt.'">';
			$imageArray[] = '<span>'.$getImage.'</span>';		
		endfor;
	endif;
	
	if ( esc_attr($a['shuffle']) != "false" ) shuffle($imageArray); 
	
	return '<div class="logo-slider" data-speed="'.esc_attr($a['speed']).'" data-direction="'.esc_attr($a['direction']).'" data-delay="'.esc_attr($a['delay']).'" data-pause="'.esc_attr($a['pause']).'" data-maxw="'.esc_attr($a['max_w']).'" data-spacing="'.esc_attr($a['space']).'"><div class="logo-row">'.printArray($imageArray).'</div></div>';
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
	$compare = esc_attr($a['compare']);
	if ( $compare == "greater equal" || $compare == "more equal" ) $compare=">=";
	if ( $compare == "greater" || $compare == "more" ) $compare=">";
	if ( $compare == "less equal" ) $compare="<=";
	if ( $compare == "less" ) $compare="<";
	if ( $compare == "equal" || $compare == "" ) $compare="=";
	if ( $compare == "not equal" ) $compare="!=";
	if ( $field != "" ) :
		$image_attachments = new WP_Query( array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg, image/gif, image/jpg, image/png, image/webp', 'posts_per_page'=>$max, 'meta_query'=>array(array( 'key'=>$field, 'value'=>esc_attr($a['value']), 'type'=>esc_attr($a['type']), 'compare'=>$compare )), 'orderby'=>$orderBy, 'order'=>$order,  ));
	elseif ( $tags == "" ) :
		$tags = get_the_slug();
		$image_attachments = new WP_Query( array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg, image/gif, image/jpg, image/png, image/webp', 'posts_per_page'=>$max, 'tax_query'=>array(array( 'taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags, )), 'orderby'=>$orderBy, 'order'=>$order,  ));
	else:
		$tags = explode(',', $tags);
		$image_attachments = new WP_Query( array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg, image/gif, image/jpg, image/png, image/webp', 'posts_per_page'=>$max, 'tax_query'=>array(array( 'taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>array_values($tags))), 'orderby'=>$orderBy, 'order'=>$order,  ));					
	endif;
	
	$imageIDs = array();
	if ( $image_attachments->have_posts() ) : 
		while ( $image_attachments->have_posts() ) : 
			$image_attachments->the_post(); 
			$imageIDs[] = get_the_ID(); 
		endwhile; 
		wp_reset_postdata();
	endif;	
	update_field('image_number', count($imageIDs));
	return serialize($imageIDs);
}

// Genearate a WordPress gallery and filter
add_shortcode( 'get-gallery', 'battleplan_setUpWPGallery' );
function battleplan_setUpWPGallery( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'size'=>'thumbnail', 'id'=>'', 'columns'=>'5', 'max'=>'-1', 'caption'=>'false', 'start'=>'', 'end'=>'', 'order_by'=>'menu_order', 'order'=>'ASC', 'tags'=>'', 'field'=>'', 'class'=>'', 'include'=>'', 'exclude'=>'', 'value'=>'', 'type'=>'', 'compare'=>'' ), $atts );
	$id = esc_attr($a['id']);	
	if ( $id == '' ) global $post; $id = intval( $post->ID );  
	$name = esc_attr($a['name']) == '' ? $id : esc_attr($a['name']);
	$size = esc_attr($a['size']);
	$orderBy = esc_attr($a['order_by']);
	$exclude = esc_attr($a['exclude']);
	$include = esc_attr($a['include']);	
	$imageIDs = do_shortcode('[load-images tags="'.esc_attr($a['tags']).'" field="'.esc_attr($a['field']).'" order_by="'.$orderBy.'" order="'.esc_attr($a['order']).'" value="'.esc_attr($a['value']).'" type="'.esc_attr($a['type']).'" compare="'.esc_attr($a['compare']).'"]');
	$imageIDs = unserialize($imageIDs);
	$class = esc_attr($a['class']);
	if ( $class != "" ) $class = " ".$class;
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg, image/gif, image/jpg, image/png, image/webp', 'posts_per_page'=>esc_attr($a['max']), 'order'=>esc_attr($a['order']), 'date_query'=>array( array( 'after'=>esc_attr($a['start']), 'before'=>esc_attr($a['end']), 'inclusive'=>'true' )));	
	
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
	if ( $imageIDs ) $args['post__in']=$imageIDs; $args['orderby']="post__in"; 
	if ( !$imageIDs && !$include ) $args['post_parent']=$id; $args['orderby']=$orderBy;
	
	$gallery = '<div id="gallery-'.$name.'" class="gallery gallery-'.$id.' gallery-column-'.esc_attr($a['columns']).' gallery-size-'.$size.'">';

	$image_attachments = new WP_Query($args);
	
	if ( $image_attachments->have_posts() ) : 
		while ( $image_attachments->have_posts() ) :
			$image_attachments->the_post();
			$getID = get_the_ID();
			$full = wp_get_attachment_image_src($getID, 'full');
			$image = wp_get_attachment_image_src($getID, $size);
			$imgSet = wp_get_attachment_image_srcset($getID, $size );
			$count++;
			
			$captionPrint = '';
			if ( esc_attr($a['caption']) != "false" ) $captionPrint = '<figcaption><div class="image-caption image-title">'.$post->post_title.'</div></figcaption>'; 

			$gallery .= '<dl class="col col-archive col-gallery id-'.$getID.'"><dt class="col-inner"><a class="link-archive link-gallery glightbox" href="'.$full[0].'"><img class="img-gallery wp-image-'.get_the_ID().'" loading="lazy" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta(get_the_ID(), '_wp_attachment_image_alt', true).'"></a>'.$captionPrint.'</dt></dl>';
		endwhile; 
		wp_reset_postdata();
	endif;	
	$gallery .= "</div>";	
	update_field('image_number', $count);
	return $gallery;
}

// Build a coupon
add_shortcode( 'coupon', 'battleplan_coupon' );
function battleplan_coupon( $atts, $content = null ) {
	$a = shortcode_atts( array( 'action'=>'Mention Our Website For', 'discount'=>'$20 OFF', 'service'=>'Service Call', 'disclaimer'=>'First time customers only.  Limited time offer.  Not valid with any other offer.  Must mention coupon at time of appointment.  During regular business hours only.  Limit one coupon per system.' ), $atts );
	
	return do_shortcode('
		[txt class="coupon"]
			<div class="coupon-inner">
				<h2 class="action">'.esc_attr($a['action']).'</h2>
				<h2 class="discount">'.esc_attr($a['discount']).'</h2>
				<h2 class="service">'.esc_attr($a['service']).'</h2>
				<p class="disclaimer">'.esc_attr($a['disclaimer']).'</p>
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
	
	$imagePath = get_template_directory().'/common/logos/now-hiring-'.$graphic.'.png';			
	list($width, $height) = getimagesize($imagePath);
	
	return '<a href="/'.esc_attr($a['link']).'"><img class="noFX" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/now-hiring-'.$graphic.'.png" alt="We are hiring! Join our team." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" /></a>';
}

// Add BBB widget to Sidebar
add_shortcode( 'get-bbb', 'battleplan_getBBB' );
function battleplan_getBBB( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'link'=>'', 'graphic'=>'1' ), $atts );
	$graphic = esc_attr($a['graphic']);

	$imagePath = get_template_directory().'/common/logos/bbb-'.$graphic.'.png';			
	list($width, $height) = getimagesize($imagePath);
	
	return '<a href="'.esc_attr($a['link']).'" title="Click here to view our profile page on the Better Business Bureau website."><img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/bbb-'.$graphic.'.png" alt="We are accredited with the BBB and are proud of our A+ rating." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';
}

// Add Veteran Owned widget to Sidebar
add_shortcode( 'get-veteran-owned', 'battleplan_getVeteranOwned' );
function battleplan_getVeteranOwned( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'link'=>'', 'graphic'=>'1' ), $atts );
	$graphic = esc_attr($a['graphic']);
	
	$imagePath = get_template_directory().'/common/logos/veteran-owned-'.$graphic.'.png';			
	list($width, $height) = getimagesize($imagePath);
	
	return '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/veteran-owned-'.$graphic.'.png" alt="We are proud to be a Veteran Owned business." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';
}

// Add Credit Cards widget to Sidebar
add_shortcode( 'get-credit-cards', 'battleplan_getCreditCards' );
function battleplan_getCreditCards( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'mc'=>'yes', 'visa'=>'yes', 'discover'=>'yes', 'amex'=>'yes' ), $atts );

	$buildCards = '<div id="credit-cards" class="currency">';
	if ( esc_attr($a['mc']) == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-mc.png" loading="lazy" alt="We accept Mastercard" width="100" height="62" style="aspect-ratio:100/62" />';
	if ( esc_attr($a['visa']) == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-visa.png" loading="lazy" alt="We accept Visa width="100" height="62" style="aspect-ratio:100/62" />';
	if ( esc_attr($a['discover']) == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-discover.png" loading="lazy" alt="We accept Discover width="100" height="62" style="aspect-ratio:100/62" />';
	if ( esc_attr($a['amex']) == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-amex.png" loading="lazy" alt="We accept American Express width="100" height="62" style="aspect-ratio:100x62" />';
	$buildCards .= '</div>';  					  
													  
	return $buildCards;
}

// Add Crypto Currency widget to Sidebar
add_shortcode( 'get-crypto', 'battleplan_getCrypto' );
function battleplan_getCrypto( $atts, $content = null ) {	
	$a = shortcode_atts( array( 'bitcoin'=>'yes', 'cardano'=>'yes', 'chainlink'=>'yes', 'dogecoin'=>'yes', 'monero'=>'yes', 'polygon'=>'yes', 'stellar'=>'yes' ), $atts );

	$buildCrypto = '<div id="crypto" class="currency">';
	if ( esc_attr($a['bitcoin']) == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-bitcoin.png" alt="We accept Bitcoin crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( esc_attr($a['cardano']) == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-cardano.png" alt="We accept Cardano crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( esc_attr($a['chainlink']) == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-chainlink.png" alt="We accept Chainlink crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( esc_attr($a['dogecoin']) == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-dogecoin.png" alt="We accept Dogecoin crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( esc_attr($a['monero']) == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-monero.png" alt="We accept Monero crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( esc_attr($a['polygon']) == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-polygon.png" alt="We accept Polygon crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	if ( esc_attr($a['stellar']) == "yes" ) $buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-stellar.png" alt="We accept Stellar crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
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
	
	if ( $field ) :	
		$buildFilter .= '<form name="filter-form" action=/results method="get">';
		$buildFilter .= '<ul class="'.esc_attr($a['ul']).'">';
			foreach( $field['choices'] as $k => $v ) :
				$buildFilter .= '<li class="filter-choice"><input type="checkbox" name="choice" value="'.$k.'"><div class="checkbox-label">'.$v.'</div></li>';
			endforeach;
		$buildFilter .= "</ul>";

		$buildFilter .= '<div class="block block-button"><input type="button" class="filter-btn" data-url="'.$field_key.'" value="'.esc_attr($a['btn_search']).'"></div>';
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
	$break = esc_attr($a['break']) == "none" ? ' break-none' : esc_attr($a['break']);
	$align = "align".esc_attr($a['align']);
	$images = explode(',', esc_attr($a['img']));
	
	$buildFlex = '<ul class="side-by-side '.$align.$break.'">';
	for ($i = 0; $i < count($images); $i++) :
		$img = wp_get_attachment_image_src( $images[$i], $size );

		list ($src, $width, $height ) = $img;
		$class = $images[$i] == esc_attr($a['full']) ? ' class="full-'.esc_attr($a['pos']).'" ' : '';
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
	return do_shortcode(include get_template_directory().'/pages/'.esc_attr($a['slug']).'.php');	
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
	return bp_display_menu_search(esc_attr($a['text']), '', esc_attr($a['reveal']));
}

// Display RSS feed
add_shortcode( 'get-rss', 'battleplan_getRSS' );
function battleplan_getRSS( $atts, $content = null ) {
	$a = shortcode_atts( array( 'link'=>'', 'num'=>'5', 'menu'=>'true' ), $atts );
	$rss = fetch_feed( esc_attr($a['link']) );
	include_once( ABSPATH . WPINC . '/feed.php' );
	
	$buildHeader = '<h1>Recent Blog Posts</h1>';
	$buildHeader .= do_shortcode('[clear height="15px"]');  
  
	if ( ! is_wp_error( $rss ) ) :
		$maxitems = $rss->get_item_quantity( esc_attr($a['num']) ); 
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
	if ( esc_attr($a['menu']) == "true" ) $displayArchive .= $buildMenu;
	$displayArchive .= $buildArchive;	
	return $displayArchive;
}

// Display Count-Up widget
 add_shortcode("get-countup", "battleplan_countUp");
 function battleplan_countUp($atts, $content) {
 	 wp_enqueue_script( 'battleplan-count-up', get_template_directory_uri().'/js/count-up.js', array('jquery'), _BP_VERSION, false );	

	 $a = shortcode_atts( array( 'name'=>'', 'start'=>'0', 'end'=>'0', 'decimals'=>'0', 'duration'=>'5', 'delay'=>'0', 'waypoint'=>'85%', 'easing'=>'easeOutExpo', 'grouping'=>'true', 'separator'=>',', 'decimal'=>'.', 'prefix'=>'', 'suffix'=>'' ), $atts );
	 $id = strtolower(esc_attr($a['name']));	 
	 $id = str_replace('-', '_', $id);
	 $id = str_replace(' ', '_', $id);	
	 $delay = esc_attr($a['delay']) * 1000;	  
	 $start = esc_attr($a['start']);
	 if (substr($start, 0, 1) === '{') $start = do_shortcode(str_replace( array("{","}","&#039;","&quot;"), array("[","]","'","'"), $start ));
	 
	 $end = esc_attr($a['end']);
	 if (substr($end, 0, 1) === '{') $end = do_shortcode(str_replace( array("{","}","&#039;","&quot;"), array("[","]","'","'"), $end ));

	 $buildCountUp = '<div class="count-up">';
	 $buildCountUp .= '<script nonce='._BP_NONCE.'>document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {';
	 $buildCountUp .= 'var options = { useEasing : "'.esc_attr($a['easing']).'", useGrouping : '.esc_attr($a['grouping']).', separator : "'.esc_attr($a['separator']).'", decimal : "'.esc_attr($a['decimal']).'", prefix : "'.esc_attr($a['prefix']).'", suffix : "'.esc_attr($a['suffix']).'" };';
	 $buildCountUp .= 'var '.$id.' = new CountUp("'.$id.'", '.$start.', '.$end.', '.esc_attr($a['decimals']).', '.esc_attr($a['duration']).', options);';
	 $buildCountUp .= '$("#'.$id.'").waypoint(function() { setTimeout(function() { '.$id.'.start(); }, '.$delay.'); this.destroy(); }, { offset: "'.esc_attr($a['waypoint']).'" });';	
	 $buildCountUp .= '})(jQuery); }); </script>';
	 $buildCountUp .= '<span id="'.$id.'" style="white-space:pre;"></span></div>';
	 	 
	 return $buildCountUp;
}
?>