<?php
/* Battle Plan Web Design functions and definitions
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shortcodes
# Functions to extend WordPress
# Register Custom Post Types
# Import Advanced Custom Fields
# Set Up Admin Columns
# Basic Theme Set Up
# Custom Hooks
# AJAX Functions
# Grid Set Up

--------------------------------------------------------------*/

if ( ! defined( '_BP_VERSION' ) ) { define( '_BP_VERSION', '6.1' ); }
if ( ! defined( '_BP_COUNT_ALL_VISITS' ) ) { define( '_BP_COUNT_ALL_VISITS', 'false' ); }

/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

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

/* Find Number of Days Between Two Dates */
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
	$pageID = 0;

	$latest_cpt = get_posts("post_type='.$type.'&numberposts=1"); $pageID = $latest_cpt[0]->ID;	
	if ( $id != "" ) : $pageID = $id; endif;	
	if ( $slug != "" ) : $page = get_page_by_path( $slug, OBJECT, $type ); $pageID = $page->ID; endif;	
	if ( $title != "" ) : $page = get_page_by_title( $title, OBJECT, $type ); $pageID = $page->ID; endif;	

	$getPage = get_post( $pageID );
	$title = esc_html( get_the_title($pageID) );
	$excerpt = $getPage->post_excerpt;
	$excerpt = apply_filters('the_excerpt', $excerpt);
	$content = $getPage->post_content;
	$content = apply_filters('the_content', $content);
	$thumbnail = get_the_post_thumbnail( $pageID, 'thumbnail' );
	$link = esc_url(get_permalink( $pageID ));

	if ( $display == "content" ) : $output = $content; endif;
	if ( $display == "title" ) : $output = $title; endif;
	if ( $display == "excerpt" ) : $output = $excerpt; endif;
	if ( $display == "thumbnail" ) : $output = $thumbnail; endif;
	if ( $display == "link" ) : $output = $link; endif;

	return $output;
}

// Returns website address (for privacy policy, etc)
add_shortcode( 'get-domain', 'battleplan_getDomain' );
function battleplan_getDomain( $atts, $content = null ) {
	$a = shortcode_atts( array( 'link'=>'false', ), $atts );
	$link = esc_attr($a['link']);	
	if ( $link == "false" ) : return esc_url(get_site_url());
	else: return '<a href="'.esc_url(get_site_url()).'">'.esc_url(get_site_url()).'</a>'; endif;
}

// Returns url of page (minus domain)
add_shortcode( 'get-url', 'battleplan_getURL' );
function battleplan_getURL() { return $_SERVER['REQUEST_URI']; }

// Add Wells Fargo Ad to Sidebar
add_shortcode( 'get-wells-fargo', 'battleplan_getWellsFargo' );
function battleplan_getWellsFargo($atts, $content = null) {
	$a = shortcode_atts( array( 'graphic1'=>'', 'graphic2'=>'', 'link'=>'', 'class'=>''  ), $atts );
	$graphic1 = esc_attr($a['graphic1']);	
	$graphic2 = esc_attr($a['graphic2']);	
	$link = esc_attr($a['link']);	
	$class = esc_attr($a['class']);	
	if ( $class != '' ) : $class = 'class="'.$class.'"'; endif;
	$rand = rand(1,2);
	if ($rand == "1") : $ad = $graphic1; endif;
	if ($rand == "2") : $ad = $graphic2; endif;
	if ($ad=="Wells-Fargo-A.png" || $ad=="Wells-Fargo-B.png") $alt = "Looking for financing options? Special financing available. This credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more.";
	if ($ad=="Wells-Fargo-C.png" || $ad=="Wells-Fargo-D.png") $alt = "Special financing available. This credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more.";	
	if ($ad=="Wells-Fargo-C.png" || $ad=="Wells-Fargo-D.png") $alt = "Special financing available. This credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more.";		
	if ($ad=="Wells-Fargo-Splash-A.png" || $ad=="Wells-Fargo-Splash-B.png" || $ad=="Wells-Fargo-Splash-C.png") $alt = "Buy today, pay over time. This credit card also brings you revolving line of credit that you can use over and over again, special financing where available, convenient monthly payments to fit your budget, easy-to-use online account management and bill payment options. This credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more.";	
	if ($ad=="Wells-Fargo-Splash-D.png") $alt = "Buy today, pay over time. Your Wells Fargo Home Projects credit card also brings you revolving line of credit that you can use over and over again, special financing where available, convenient monthly payments to fit your budget, easy-to-use online account management and bill payment options. The Wells Fargo Home Projects credit card is issued with approved credit by Wells Fargo Bank, N.A. Equal Housing Lender. Learn more.";	
	$output = '<a href="#" class="financing-link" onclick="trackClicks(\'link\', \'Offsite Link\', \'Wells Fargo\', \''.$link.'\'); return false;"><img src="/wp-content/uploads/'.$ad.'" alt="'.$alt.'" '.$class.'/></a>';
	return $output; 
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
	$a = shortcode_atts( array( 'facebook'=>'false', 'twitter'=>'false', ), $atts );
	$facebook = esc_attr($a['facebook']);
	$twitter = esc_attr($a['twitter']);	
	global $post;
    $postURL = urlencode( esc_url( get_permalink($post->ID) ) );
    $postTitle = urlencode( $post->postTitle );
    $facebookLink = sprintf( 'https://www.facebook.com/sharer/sharer.php?u=%1$s', $postURL );
    $twitterLink = sprintf( 'https://twitter.com/intent/tweet?text=%2$s&url=%1$s', $postURL, $postTitle );

    $output = '<div class="social-share-buttons">';
	if ( $facebook == "true" ) $output .= '<a target="_blank" href="'.$facebookLink.'" class="share-button facebook"><span class="sr-only">Share on Facebook</span></a>';
	if ( $twitter == "true" ) $output .= '<a target="_blank" href="'.$twitterLink.'" class="share-button twitter"><span class="sr-only">Share on Twitter</span></a>';
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
	$cookieParam = array ( 'expires' => time() + (86400 * 7), 'secure' => true );	
	if ( $cookie != "false" ) setcookie('random-text', $rand, $cookieParam);

	return $printText;
}

// Display a random photo from tagged images 
add_shortcode( 'get-random-image', 'battleplan_getRandomImage' );
function battleplan_getRandomImage($atts, $content = null ) {	
	$a = shortcode_atts( array( 'id'=>'', 'tag'=>'', 'size'=>'thumbnail', 'link'=>'no', 'number'=>'1', 'offset'=>'', 'align'=>'left', 'class'=>'', 'order_by'=>'recent', 'order'=>'asc', 'shuffle'=>'no' ), $atts );
	$tag = esc_attr($a['tag']);	
	$tags = explode( ',', $tag );
	$size = esc_attr($a['size']);	
	$link = esc_attr($a['link']);	
	$align = esc_attr($a['align']);	
	$number = esc_attr($a['number']);			
	$offset = esc_attr($a['offset']);		
	$orderBy = esc_attr($a['order_by']);		
	$order = esc_attr($a['order']);		
	$shuffle = esc_attr($a['shuffle']);	
	$id = esc_attr($a['id']);		
	if ( $id == "current" ) $id = get_the_ID();
	$class = esc_attr($a['class']);	
	if ( $class != '' ) $class = " ".$class;
	if ( $align != '' ) $align = "align".$align;
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$number, 'offset'=>$offset, 'order'=>$order);

	if ( $orderBy == 'views-today' ) : $args['meta_key']="post-views-day-1"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="post-views-total-7day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="post-views-total-30day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="post-views-total-all"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="post-tease-time"; $args['orderby']='meta_value_num';	
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
		$full = wp_get_attachment_image_src($post->ID, 'full');
		$image = wp_get_attachment_image_src($post->ID, $size);
	
		$buildImage = "";	
		if ( $link == "yes" ) $buildImage .= '<a href="'.$full[0].'">';		
		$buildImage .= '<img data-id="'.get_the_ID().'"'.getImgMeta($post->ID).' class="wp-image-'.get_the_ID().' random-img '.$tags[0].'-img '.$align.' size-'.$size.$class.'" src="'.$image[0].'" alt="'.get_post_meta(get_the_ID(), '_wp_attachment_image_alt', true).'">';	
		if ( $link == "yes" ) $buildImage .= '</a>';	
		$imageArray[] =  $buildImage;	
	
	endwhile; wp_reset_postdata(); endif;	
	if ( $shuffle != "no" ) : shuffle($imageArray); endif;
	return printArray($imageArray);
}

// Display a row of square pics from tagged images
add_shortcode( 'get-row-of-pics', 'battleplan_getRowOfPics' );
function battleplan_getRowOfPics($atts, $content = null ) {	
	$a = shortcode_atts( array( 'id'=>'', 'tag'=>'row-of-pics', 'link'=>'no', 'col'=>'4', 'size'=>'half-s', 'valign'=>'center', 'class'=>'', 'order_by'=>'recent', 'order'=>'asc', 'shuffle'=>'no' ), $atts );
	$col = esc_attr($a['col']);		
	$size = esc_attr($a['size']);		
	$tag = esc_attr($a['tag']);	
	$tags = explode( ',', $tag );
	$link = esc_attr($a['link']);	
	$orderBy = esc_attr($a['order_by']);		
	$order = esc_attr($a['order']);		
	$valign = esc_attr($a['valign']);		
	$shuffle = esc_attr($a['shuffle']);		
	$class = esc_attr($a['class']);	
	if ( $class != '' ) $class = " ".$class;
	$id = esc_attr($a['id']);	
	if ( $id == "current" ) $id = get_the_ID();
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$col, 'order'=>$order, 'tax_query'=>array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags )));

	if ( $orderBy == 'views-today' ) : $args['meta_key']="post-views-day-1"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="post-views-total-7day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="post-views-total-30day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="post-views-total-all"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="post-tease-time"; $args['orderby']='meta_value_num';	
	else : $args['orderby']=$orderBy; endif;		
	
	if ( $id != '' ) : 
		$args['post_parent']=$id;
	endif;

	$image_query = new WP_Query($args);		
	$imageArray = array();

	if( $image_query->have_posts() ) : while ($image_query->have_posts() ) : $image_query->the_post();
		$image = wp_get_attachment_image_src( get_the_ID(), $size );
	
		$getImage = "";
		if ( $link == "yes" ) $getImage .= '<a href="'.$image[0].'">';
		$getImage .= '<img data-id="'.get_the_ID().'"'.getImgMeta(get_the_ID()).' class="random-img '.$tags[0].'-img '.$align.'" src="'.$image[0].'" alt="'.get_post_meta(get_the_ID(), '_wp_attachment_image_alt', true).'">';
		if ( $link == "yes" ) $getImage .= '</a>';

		$imageArray[] = do_shortcode('[col class="col-row-of-pics'.$class.'"]'.$getImage.'[/col]');			
	endwhile; wp_reset_postdata(); endif;	
	
	if ( $shuffle != "no" ) : shuffle($imageArray); endif;
	$print = do_shortcode('[layout grid="'.$col.'e" valign="'.$valign.'"]'.printArray($imageArray).'[/layout]'); 
	return $print;
}

// Build an archive
add_shortcode( 'build-archive', 'battleplan_getBuildArchive' );
function battleplan_getBuildArchive($atts, $content = null) {	
	$a = shortcode_atts( array( 'type'=>'', 'count_tease'=>'false', 'count_view'=>'false', 'show_btn'=>'false', 'btn_text'=>'Read More', 'btn_pos'=>'outside', 'show_title'=>'true', 'title_pos'=>'outside', 'show_date'=>'false', 'show_author'=>'false', 'show_social'=>'false', 'show_excerpt'=>'true', 'show_content'=>'false', 'show_thumb'=>'true', 'no_pic'=>'', 'size'=>'thumbnail', 'pic_size'=>'1/3', 'text_size'=>'', 'accordion'=>'false', 'link'=>'post' ), $atts );
	$type = esc_attr($a['type']);	
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
	if ( $showContent == "true" ) : $showExcerpt = "false"; $showBtn = "false"; endif;
	$showThumb = esc_attr($a['show_thumb']);
	if ( $showThumb != "false" ) $showThumb = "true";
	$size = esc_attr($a['size']);		
	$picSize = esc_attr($a['pic_size']);	
	$textSize = esc_attr($a['text_size']);		
	$accordion = esc_attr($a['accordion']);			
	$format = esc_attr($a['format_text']);
	$link = esc_attr($a['link']);		
	if ( $link == "false" || $link == "no" || $type == "testimonials" ) : $link == "false"; $linkLoc = "";
	elseif ( $link == "post" ) : $linkLoc = esc_url(get_the_permalink(get_the_ID()));
	else: $linkLoc = $link;	endif;
	$noPic = esc_attr($a['no_pic']);	
	if ( $noPic == "" ) $noPic = "false";	
	if ( $showBtn == "true" ) : $picADA = " ada-hidden='true'"; $titleADA = " aria-hidden='true' tabindex='-1'";
	elseif ( $showTitle == "true" ) : $picADA = " ada-hidden='true'"; endif;
		
	if ( has_post_thumbnail() && $showThumb == "true" ) : 	
		$buildImg = do_shortcode("[img size='".$picSize."' class='image-".$type."' link='".$linkLoc."' ".$picADA."]".get_the_post_thumbnail( get_the_ID(), $size, array( 'class'=>'img-archive img-'.$type.$googleTag ))."[/img]"); 
		if ( $textSize == "" ) : $textSize = getTextSize($picSize); endif;
	
		global $setAltText;
		if ( $setAltText == "yes" ) :
			$attachment_id = get_post_thumbnail_id( get_the_ID() );
			if ( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) == "" ) :
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', esc_html(get_the_title()) );
			endif;
		endif;	
	
	elseif ( $noPic != "false") : 	
		$buildImg = do_shortcode("[img size='".$picSize."' class='image-".$type." block-placeholder placeholder-".$type."' link='".$linkLoc."' ".$picADA."]".wp_get_attachment_image( $noPic, $size, array( 'class'=>'img-archive img-'.$type.$googleTag ))."[/img]"); 
		if ( $textSize == "" ) : $textSize = getTextSize($picSize); endif;	
	
	else : $textSize = "100"; endif;
	
	if ( $type == "testimonials" ) {
		$linkLoc = "/testimonials/";
		$testimonialName = esc_attr(get_field( "testimonial_name" ));
		$testimonialPhone = esc_attr(get_field( "testimonial_phone" ));
		$testimonialEmail = esc_attr(get_field( "testimonial_email" ));
		$testimonialTitle = esc_attr(get_field( "testimonial_title" ));
		$testimonialBiz = esc_attr(get_field( "testimonial_biz" ));
		$testimonialWeb = esc_attr(get_field( "testimonial_website" ));
		$testimonialLoc = esc_attr(get_field( "testimonial_location" ));
		$testimonialRate = esc_attr(get_field( "testimonial_rating" ));	
		$testimonialMisc1 = esc_attr(get_field( "testimonial_misc1" ));	
		$testimonialMisc2 = esc_attr(get_field( "testimonial_misc2" ));	
		$testimonialMisc3 = esc_attr(get_field( "testimonial_misc3" ));	
		$testimonialMisc4 = esc_attr(get_field( "testimonial_misc4" ));
		
		$buildCredentials = "<div class='testimonials-credential testimonials-name' data-count-tease='true' data-id=".get_the_ID().">".$testimonialName;
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

		$archiveBody = '[txt class="testimonials-quote"][p]'.apply_filters('the_content', get_the_content()).'[/p][/txt][txt class="testimonials-credentials"]'.$buildCredentials.'[/txt]';
	} else {
		if ( $accordion == "true" ) :		
			$title = esc_html(get_the_title());
			$excerpt = wp_kses_post(get_the_excerpt());	
			$content = apply_filters('the_content', get_the_content()); 
			$archiveBody = '[accordion title="'.$title.'" excerpt="'.$excerpt.'"]'.$content.'[/accordion]';		
		else :		
			$archiveMeta = $archiveBody = "";
			if ( $showTitle == "true" ) :
				$archiveMeta .= "<h3 data-count-tease=".$countTease." data-count-view=".$countView." data-id=".get_the_ID().">";
				if ( $showContent != "true" && $link != "false" ) $archiveMeta .= '<a href="'.$linkLoc.'" class="link-archive link-'.get_post_type().'"'.$titleADA.'>';		
				$archiveMeta .= esc_html(get_the_title());  
				if ( $showContent != "true" && $link != "false" ) $archiveMeta .= '</a>';	
				$archiveMeta .= "</h3>";
			endif;		
			if ( $showDate == "true" || $showAuthor == "true" || $showSocial == "true" ) $archiveMeta .= '<div class="archive-meta">';
			if ( $showDate == "true" ) $archiveMeta .= '<span class="archive-date '.$type.'-date date"><i class="fas fa-calendar-alt"></i>'.get_the_date().'</span>';
			if ( $showAuthor == "true") $archiveMeta .= '<span class="archive-author '.$type.'-author author"><i class="fas fa-user"></i>'.get_the_author().'</span>';
			if ( $showSocial == "true") $archiveMeta .= '<span class="archive-social '.$type.'-social social">'.do_shortcode('[add-share-buttons facebook="true" twitter="true"]').'</span>';
			if ( $showDate == "true" || $showAuthor == "true" || $showSocial == "true" ) $archiveMeta .= '</div>';
			if ( $showExcerpt == "true") $archiveBody .= '[p]'.apply_filters('the_excerpt', get_the_excerpt()).'[/p]';
			if ( $showContent == "true") $archiveBody .= '[p]'.apply_filters('the_content', get_the_content()).'[/p]';
			if ( $type == "galleries" ) :
				if ( has_term( 'auto-generated', 'gallery-type' ) ) :
					$count = esc_attr(get_field("image_number")); 						
				elseif ( has_term( 'shortcode', 'gallery-type' ) ) :
					$count = esc_attr(get_field("image_number")); 						
				else:
					$all_attachments = get_posts( array( 'post_type'=>'attachment', 'post_mime_type'=>'image', 'post_parent'=>get_the_ID(), 'post_status'=>'published', 'numberposts'=>-1 ) );
					$count = count($all_attachments); 						
				endif;	
				$subline = $count." Photos";
				$archiveBody .= '<a href="'.esc_url(get_the_permalink()).'" class="link-archive link-'.get_post_type().'" aria-hidden="true" tabindex="-1"><p class="gallery-subtitle">'.$subline.'</p></a>'; 	
			endif;
		endif;
	}
	
	if ( $showBtn == "true" ) : 
		if ( $type == "testimonials" ) : $ada = " testimonials"; else: $ada = ' about '.esc_html(get_the_title()); endif;	
		$buildBtn = do_shortcode('[btn class="button-'.$type.'" link="'.$linkLoc.'" ada="'.$ada.'"]'.$btnText.'[/btn]'); 	
	endif;
	
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
	
	return $showArchive;
}

// Display randomly selected posts - start/end can be dates or -53 week / -51 week */
add_shortcode( 'get-random-posts', 'battleplan_getRandomPosts' );
function battleplan_getRandomPosts($atts, $content = null) {	
	$a = shortcode_atts( array( 'num'=>'1', 'offset'=>'0', 'type'=>'post', 'tax'=>'', 'terms'=>'', 'orderby'=>'recent', 'sort'=>'asc', 'count_tease'=>'true', 'count_view'=>'false', 'show_title'=>'true', 'title_pos'=>'outside', 'show_date'=>'false', 'show_author'=>'false', 'show_excerpt'=>'true', 'show_social'=>'false', 'show_btn'=>'true', 'button'=>'Read More', 'btn_pos'=>'inside', 'show_content'=>'false', 'thumbnail'=>'force', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'size'=>'thumbnail', 'pic_size'=>'1/3', 'text_size'=>'', 'link'=>'post' ), $atts );
	$num = esc_attr($a['num']);	
	$potentialOffset = esc_attr($a['offset']);
	$offset = rand(0,$potentialOffset);
	$postType = esc_attr($a['type']);	
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
	
	$args = array ('posts_per_page'=>$num, 'offset'=>$offset, 'date_query'=>array( array( 'after'=>$start, 'before'=>$end, 'inclusive'=>true, ), ), 'order'=>$sort, 'post_type'=>$postType, 'post__not_in'=>$exclude);

	if ( $orderBy == 'views-today' ) : $args['meta_key']="post-views-day-1"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="post-views-total-7day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="post-views-total-30day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="post-views-total-all"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="post-tease-time"; $args['orderby']='meta_value_num';	
	else : $args['orderby']=$orderBy; endif;		

	if ( $taxonomy && $term ) : 
		$args['tax_query']=array( array('taxonomy'=>$taxonomy, 'field'=>'slug', 'terms'=>$terms ));
	endif;

	global $post; 
	$getPosts = new WP_Query( $args );
	$combinePosts = "";
	if ( $getPosts->have_posts() ) : while ( $getPosts->have_posts() ) : $getPosts->the_post(); 	
		$showPost = do_shortcode('[build-archive type="'.$postType.'" count_tease="'.$countTease.'" count_view="'.$countView.'" show_btn="'.$showBtn.'" btn_text="'.$button.'" btn_pos="'.$btnPos.'" show_title="'.$title.'" title_pos="'.$titlePos.'" show_date="'.$showDate.'" show_excerpt="'.$showExcerpt.'" show_social="'.$showSocial.'" show_content="'.$showContent.'" show_author="'.$showAuthor.'" size="'.$size.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" link="'.$link.'"]');	
	
		if ( $num > 1 ) $showPost = do_shortcode('[col]'.$showPost.'[/col]');	
		if ( has_post_thumbnail() || $thumbnail != "force" ) $combinePosts .= $showPost;
	endwhile; wp_reset_postdata(); endif;
	return $combinePosts;
}

// Display posts & images in a Bootstrap slider 
add_shortcode( 'get-post-slider', 'battleplan_getPostSlider' );
function battleplan_getPostSlider($atts, $content = null ) {	
	$a = shortcode_atts( array( 'type'=>'testimonials', 'auto'=>'yes', 'interval'=>'6000', 'loop'=>'true', 'num'=>'4', 'offset'=>'0', 'pics'=>'yes', 'caption'=>'no', 'controls'=>'yes', 'controls_pos'=>'below', 'indicators'=>'no', 'pause'=>'true', 'orderby'=>'recent', 'order'=>'asc', 'post_btn'=>'', 'all_btn'=>'View All', 'show_excerpt'=>'true', 'show_content'=>'false', 'link'=>'', 'pic_size'=>'1/3', 'text_size'=>'', 'slide_type'=>'', 'tax'=>'', 'terms'=>'', 'tag'=>'', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'size'=>'thumbnail', 'id'=>'', 'mult'=>'1', 'class'=>'' ), $atts );
	$num = esc_attr($a['num']);	
	$controls = esc_attr($a['controls']);	
	$controlsPos = esc_attr($a['controls_pos']);
	$indicators = esc_attr($a['indicators']);		
	$autoplay = esc_attr($a['auto']);		
	$type = esc_attr($a['type']);		
	$potentialOffset = esc_attr($a['offset']);
	$offset = rand(0,$potentialOffset);
	$postBtn = esc_attr($a['post_btn']);	
	$allBtn = esc_attr($a['all_btn']);	
	$interval = esc_attr($a['interval']);		
	$loop = esc_attr($a['loop']);
	$orderBy = esc_attr($a['orderby']);	
	$order = esc_attr($a['order']);		
	$pics = esc_attr($a['pics']);		
	$caption = esc_attr($a['caption']);	
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

		if ( $orderBy == 'views-today' ) : $args['meta_key']="post-views-day-1"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="post-views-total-7day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="post-views-total-30day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="post-views-total-all"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'recent' ) : $args['meta_key']="post-tease-time"; $args['orderby']='meta_value_num';	
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

			$buildIndicators = '<ol class="carousel-indicators">';
			$buildInner = '<div class="carousel-inner">';

			while ($image_query->have_posts() ) : $image_query->the_post();
				$numDisplay++; 	
				if ( $rowDisplay == 0 ) :
					if ( $numDisplay == 0 ) : 
						$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'" class="active"></li>';
						$buildInner .= '<div class="carousel-item active">';
					else : 
						$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'"></li>'; 
						$buildInner .= '<div class="clearfix"></div></div><div class="carousel-item">';
					endif;	
				endif;	

				$image = wp_get_attachment_image_src(get_the_ID(), $size );
				if ( $link == "alt" ) $linkTo = get_post_meta(get_the_ID(), '_wp_attachment_image_alt', true);				
				if ( $link == "description" ) $linkTo = esc_html(get_post(get_the_ID())->post_content);
				$buildImg = "";
				if ( $link != "none" ) : $buildImg = "<a href='".$linkTo."' class='link-archive link-".$type."'>"; endif;	
				$buildImg .= "<img data-id='".get_the_ID()."' ".getImgMeta(get_the_ID())." class='img-slider ".$tags[0]."-img' src = '".$image[0]."' alt='".get_post_meta(get_the_ID(), '_wp_attachment_image_alt', true)."'>";
		
				if ( $caption == "yes" || $caption == "title" ) : $buildImg .= "<div class='caption-holder'><div class='img-caption'>".get_the_title(get_the_ID())."</div></div>";	
				elseif ( $caption == "alt" ) : $buildImg .= "<div class='caption-holder'><div class='img-caption'>".get_post_meta(get_the_ID(), '_wp_attachment_image_alt', true)."</div></div>";
				endif;
	
				if ( $link != "none" ) : $buildImg .= "</a>"; endif;	
		
	 			$buildInner .= do_shortcode("[img size='".$imgSize."' class='image-".$type."']".$buildImg."[/img]");
	
				$rowDisplay++;
				if ( $rowDisplay == $mult ) $rowDisplay = 0;	
			endwhile;
		$buildInner .= "<div class='clearfix'></div></div>";
		wp_reset_postdata();
		endif;
	else :
		$args = array ('posts_per_page'=>-1, 'offset'=>$offset, 'date_query'=>array( array( 'after'=>$start, 'before'=>$end, 'inclusive'=>true, ), ), 'order'=>$order, 'post_type'=>$type, 'post__not_in'=>$exclude);

		if ( $orderBy == 'views-today' ) : $args['meta_key']="post-views-day-1"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="post-views-total-7day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="post-views-total-30day"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="post-views-total-all"; $args['orderby']='meta_value_num';	
		elseif ( $orderBy == 'recent' ) : $args['meta_key']="post-tease-time"; $args['orderby']='meta_value_num';	
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

						$buildInner .= do_shortcode('[build-archive type="'.$type.'" show_btn="'.$showBtn.'" btn_text="'.$postBtn.'" show_excerpt="'.$showExcerpt.'" show_content="'.$showContent.'" show_date="'.$showDate.'" show_author="'.$showAuthor.'" size="'.$size.'" pic_size="'.$picSize.'" text_size="'.$textSize.'"]');		

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

	$buildControls = "<div class='controls'>";	
	$buildControls .= $controlsPrevBtn;
	if ( $allBtn != "" ) $buildControls .= $viewMoreBtn;
	$buildControls .= $controlsNextBtn;	
	$buildControls .= "</div>";	

	if ( $slideType == "box" ) : $style = "style='margin-left:auto; margin-right:auto;'"; $slideClass="box-slider"; elseif ( $slideType == "screen" ) : $style = "style='width: calc(100vw - 17px); left: 50%; transform: translateX(calc(-50vw + 8px));'"; $slideClass="screen-slider"; elseif ( $slideType == "fade" ) : $slideClass="carousel-fade"; else: $slideClass="carousel-fade"; endif;	
	
	$buildSlider = '<div id="'.$type.'Slider'.$sliderNum.'" class="carousel slide slider slider-'.$type.' '.$slideClass.' '.$class.' mult-'.$mult.'" '.$style.' data-interval="'.$interval.'" data-pause="'.$pause.'" data-wrap="'.$loop.'"';	
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
	$a = shortcode_atts( array( 'num'=>'-1', 'space'=>'80', 'size'=>'full', 'tag'=>'featured', 'order_by'=>'rand', 'order'=>'ASC', 'shuffle'=>'false', 'speed'=>'3', 'delay'=>'0', 'pause'=>'no', 'link'=>'false'), $atts );
	$num = esc_attr($a['num']);			
	$space = esc_attr($a['space']);			
	$space = $space / 2;	
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
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$num, 'order'=>$order, 'tax_query'=>array( array('taxonomy'=>'image-tags', 'field'=>'slug', 'terms'=>$tags )));

	if ( $orderBy == 'views-today' ) : $args['meta_key']="post-views-day-1"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-7day' ) : $args['meta_key']="post-views-total-7day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-30day' ) : $args['meta_key']="post-views-total-30day"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'views-all' || $orderBy == "views" ) : $args['meta_key']="post-views-total-all"; $args['orderby']='meta_value_num';	
	elseif ( $orderBy == 'recent' ) : $args['meta_key']="post-tease-time"; $args['orderby']='meta_value_num';	
	else : $args['orderby']=$orderBy; endif;		
	
	if ( $id != '' ) : 
		$args['post_parent']=$id;
	endif;

	$image_query = new WP_Query($args);		
	$imageArray = array();

	if( $image_query->have_posts() ) : while ($image_query->have_posts() ) : $image_query->the_post();
		$image = wp_get_attachment_image_src( get_the_ID(), $size );
		$getImage = "";
		if ( $link != "false" ) $getImage .= '<a href="'.$image[0].'">';
		$getImage .= '<img data-id="'.get_the_ID().'"'.getImgMeta(get_the_ID()).' class="logo-img '.$tags[0].'-img" src="'.$image[0].'" alt="'.get_post_meta(get_the_ID(), '_wp_attachment_image_alt', true).'">';
		if ( $link != "false" ) $getImage .= '</a>';
		$imageArray[] = '<span>'.$getImage.'</span>';			
	endwhile; wp_reset_postdata(); endif;	
	
	if ( $shuffle != "false" ) : shuffle($imageArray); endif;
	$buildSlider = '<div class="logo-slider" data-speed="'.$speed.'" data-delay="'.$delay.'" data-pause="'.$pause.'" data-padding="'.$space.'"><div class="logo-row">'.printArray($imageArray).'</div></div>';
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
	
	global $imageIDs; $imageIDs = array();
	if ( $image_attachments->have_posts() ) : while ( $image_attachments->have_posts() ) : $image_attachments->the_post(); $imageIDs[] = get_the_ID(); endwhile; endif;	wp_reset_postdata();
	update_field('image_number', count($imageIDs));
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
	do_shortcode('[load-images tags="'.$tags.'" field="'.$field.'" order_by="'.$orderBy.'" order="'.$order.'" value="'.$value.'" type="'.$type.'" compare="'.$compare.'"]');
	$class = esc_attr($a['class']);
	if ( $class != "" ) $class = " ".$class;
	
	$args = array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>$max, 'order'=>$order, 'date_query'=>array( array( 'after'=>$start, 'before'=>$end, 'inclusive'=>'true' )));	
	
	global $imageIDs; 
	if ( $imageIDs ) : $args['post__in']=$imageIDs; $args['orderby']="post__in"; endif;
	if ( $exclude ) : $exclude = explode(',', $exclude); $args['post__not_in']=$exclude; endif;
	if ( $include ) : $include = explode(',', $include); $args['post__in']=$include; $args['orderby']="post__in"; endif;
	if ( !$imageIDs && !$include ) : $args['post_parent']=$id; $args['orderby']=$orderBy; endif;
	
	$gallery = '<div id="gallery-'.$name.'" class="gallery gallery-'.$id.' gallery-column-'.$columns.' gallery-size-'.$size.'">';

	$image_attachments = new WP_Query($args);
	
	if ( $image_attachments->have_posts() ) : while ( $image_attachments->have_posts() ) : $image_attachments->the_post();
		$full = wp_get_attachment_image_src($post->ID, 'full');
		$thumbnail = wp_get_attachment_image_src($post->ID, $size);
		$count++;

		if ( $caption != "false" ) : $captionPrint = '<figcaption><div class="image-caption image-title">'.$post->post_title.'</div></figcaption>'; endif;
		$gallery .= '<dl class="col col-archive col-gallery id-'.$post->ID.'"><dt class="col-inner"><a class="link-archive link-gallery ari-fancybox" href="'.$full[0].'"><img  class="wp-image-'.get_the_ID().'" data-id="'.get_the_ID().'"'.getImgMeta($post->ID).' src="'.$thumbnail[0].'" alt="'.get_post_meta(get_the_ID(), '_wp_attachment_image_alt', true).'"></a>'.$captionPrint.'</dt></dl>';
	endwhile; endif;	
	wp_reset_postdata();
	$gallery .= "</div>";	
	update_field('image_number', $count);
	return $gallery;
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

// Get ID from page slug
function getID($slug) { return get_page_by_path($slug)->ID; } 

// Get the Role of Current Logged in User
function getUserRole() {
	if ( is_user_logged_in() ) : 
		global $current_user;
		$user_roles = $current_user->roles;
		$user_role = array_shift($user_roles);
		return $user_role;
	endif;
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
function readMeta($id, $key) {
	return get_post_meta( $id, $key, true );
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
	$current_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
	foreach ( $current_terms as $current_term ) {
		if ( $current_term != $term_id ) $new_terms[] = intval( $current_term );
	}
	if ( $add_or_remove == "add" ) $new_terms[] = intval( $term_id );	
	return wp_set_object_terms( $post_id, $new_terms, $taxonomy );
}

// Populate a secondary menu or sub-menu with posts/pages from any custom post type
function fillMenu($cpt, $max = "-1", $orderby = "title", $seq = "asc") { 
	global $cpt, $max, $orderby, $seq;
	add_filter( 'wp_get_nav_menu_items', 'WebsiteGO_buildCPTsubmenu', 10, 3 );
	function WebsiteGO_buildCPTsubmenu( $items, $menu, $args ) {
		global $cpt, $max, $orderby, $seq;
		$child_items = array(); 
		$menu_order = count($items); 
		$parent_item_id = NULL;
		
		foreach ( $items as $item ) {
			if ( in_array($cpt, $item->classes) ) { $parent_item_id = $item->ID; }
		}
		
		$args = array ( 'numberposts'=>$max, 'offset'=>0, 'category'=>'', 'orderby'=>$orderby, 'order'=>$seq, 'post_type'=>$cpt, 'suppress_filters'=>true, );
		
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
	}
}

// Remove buttons from WordPress text editor
add_filter( 'quicktags_settings', 'battleplan_delete_quicktags', 10, 2 );
function battleplan_delete_quicktags( $qtInit, $editor_id = 'content' ) {
	//$qtInit['buttons'] = 'strong,em,link,block,del,ins,img,ul,ol,code,more,close';
	$qtInit['buttons'] = 'strong,em,link,ul,ol,more,close';
	return $qtInit;
}

// Add new buttons to WordPress text editor
add_action( 'admin_print_footer_scripts', 'battleplan_add_quicktags' );
function battleplan_add_quicktags() {
	if ( wp_script_is( 'quicktags' ) ) { ?>
		<script type="text/javascript">
			QTags.addButton( 'bp_paragraph', 'p', '<p>', '</p>\n\n', 'p', 'Paragraph Tag', 1 );
			QTags.addButton( 'bp_li', 'li', ' <li>', '</li>', 'li', 'List Item', 100 );

			QTags.addButton( 'bp_section', 'section', '[section name="becomes id attribute" style="corresponds to css" width="default, stretch, full, edge, inline" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/section]\n\n', 'section', 'Section', 1000 );		
			QTags.addButton( 'bp_layout', 'layout', ' [layout grid="1-auto, 1-1-1-1, 5e, content" break="3, 4" valign="start, stretch, center, end" class=""]\n\n', ' [/layout]\n', 'layout', 'Layout', 1000 );
			QTags.addButton( 'bp_column', 'column', '  [col name="becomes id attribute" align="center, left, right" valign="start, stretch, center, end" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '  [/col]\n\n', 'column', 'Column', 1000 );
			QTags.addButton( 'bp_image', 'image', '   [img size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url to link to" new-tab="false, true" ada-hidden="false, true" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/img]\n', 'image', 'Image', 1000 );
			QTags.addButton( 'bp_video', 'video', '   [vid size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url of video" class="" related="false, true" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/vid]\n', 'video', 'Video', 1000 );
			QTags.addButton( 'bp_group', 'group', '   [group size = "100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/group]\n\n', 'group', 'Group', 1000 );	
			QTags.addButton( 'bp_text', 'text', '   [txt size="100 1/2 1/3 1/4 1/6 1/12" order="2, 1, 3" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '   [/txt]\n', 'text', 'Text', 1000 );
			QTags.addButton( 'bp_button', 'button', '   [btn size="100 1/2 1/3 1/4 1/6 1/12" order="3, 1, 2" align="center, left, right" link="url to link to" get-biz="link in functions.php" new-tab="false, true" class="" ada="text for ada button" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/btn]\n', 'button', 'Button', 1000 );	
			QTags.addButton( 'bp_social', 'social', '   [social-btn type="email, facebook, twitter" img="none, link"]', '', 'social', 'Social', 1000 );	
			QTags.addButton( 'bp_accordion', 'accordion', '   [accordion title="clickable title" excerpt="false, true" class="" icon="true, false" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/accordion]\n\n', 'accordion', 'Accordion', 1000 );
			QTags.addButton( 'bp_expire-content', 'expire', '[expire start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/expire]\n\n', 'expire', 'Expire', 1000 );			
			QTags.addButton( 'bp_lock-section', 'lock', '[lock name="becomes id attribute" style="(lock) corresponds to css" width="edge, default, stretch, full, inline" position="bottom, top, modal" delay="3000" cookie="session, never, always, # days" background="url" left="50" top="50" class="" start="YYYY-MM-DD" end="YYYY-MM-DD"]\n', '[/lock]\n\n', 'lock', 'Lock', 1000 );		
			QTags.addButton( 'bp_random-image', 'random image', '   [get-random-image id="" tag="random" size="thumbnail, third-s" link="no, yes" number="1" offset="" align="left, right, center" order_by="recent, rand, menu_order, title, id, post_date, modified, views" order="asc, desc" shuffle="no, yes"]\n', '', 'random image', 'Random Image', 1000 );
			QTags.addButton( 'bp_random-post', 'random post', '   [get-random-posts num="1" offset="0" type="post" tax="" terms="" orderby="recent, rand, views-today, views-7day, views-30day, views-all" sort="asc, desc" count_tease="true, false" count_view="true, false" show_title="true, false" title_pos="outside, inside" show_date="false, true" show_author="false, true" show_excerpt="true, false" show_social="false, true" show_btn="true, false" button="Read More" btn_pos="inside, outside" thumbnail="force, false" link="post, false, /link-destination/" start="" end="" exclude="" x_current="true, false" size="thumbnail, size-third-s" pic_size="1/3" text_size=""]\n', '', 'random post', 'Random Post', 1000 );
			QTags.addButton( 'bp_random-text', 'random text', '   [get-random-text cookie="true, false" text1="" text2="" text3="" text4="" text5="" text6="" text7=""]\n', '', 'random text', 'Random Text', 1000 );
			QTags.addButton( 'bp_row-of-pics', 'row of pics', '   [get-row-of-pics id="" tag="row-of-pics" col="4" size="half-s, thumbnail" valign="center, start, stretch, end" link="no, yes" order_by="recent, rand, menu_order, title, id, post_date, modified, views" order="asc, desc" shuffle="no, yes" class=""]\n', '', 'row of pics', 'Row Of Pics', 1000 );
			QTags.addButton( 'bp_post-slider', 'post slider', '   [get-post-slider type="" auto="yes, no" interval="6000" loop="true, false" num="4" offset="0" pics="yes, no" controls="yes, no" controls_pos="below, above" indicators="no, yes" pause="true, false" tax="" terms="" orderby="recent, rand, id, author, title, name, type, date, modified, parent, comment_count, relevance, menu_order, (images) views, (posts) views-today, views-7day, views-30day, views-all" order="asc, desc" post_btn="" all_btn="View All" link="" start="" end="" excluse="" x_current="true, false" show_excerpt="true, false" show_content="false, true" size="thumbnail" pic_size="1/3" text_size="" class="" (images) slide_type="box, screen, fade" tag="" caption="no, yes" id="" size="thumbnail, half-s" mult="1"]\n', '', 'post slider', 'Post Slider', 1000 );

			QTags.addButton( 'bp_images-slider', 'Images Slider', '<div class="alignright size-half-s">[get-post-slider type="images" num="6" size="half-s" controls="no" indicators="yes" tag="featured" all_btn="" link="none, alt, description, blank" slide_type="box, screen, fade" orderby="recent"]</div>\n\n', '', 'images-slider', 'Images Slider', 1000 );	
			QTags.addButton( 'bp_testimonial-slider', 'Testimonial Slider', '  [col]\n   <h2>What Our Customers Say...</h2>\n   [get-post-slider type="testimonials" num="6" pic_size="1/3"]\n  [/col]\n\n', '', 'testimonial-slider', 'Testimonial Slider', 1000 );
			QTags.addButton( 'bp_random-product', 'Random Product', '  [col]\n   <h2>Featured Product</h2>\n   [get-random-posts type="products" offset="1" button="Learn More" orderby="views-30day" sort="desc"]\n  [/col]\n\n', '', 'random-product', 'Random Product', 1000 );
		</script>
	<?php }
}

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
# Set up Admin Columns
--------------------------------------------------------------*/
add_action( 'ac/ready', 'battleplan_column_settings' );
function battleplan_column_settings() {
	ac_register_columns( 'testimonials', array(
		array(
			'columns'=>array(
				'featured-image'=>array(
					'type'=>'column-featured_image',
					'label'=>'',
					'width'=>'5',
					'width_unit'=>'%',
					'featured_image_display'=>'image',
					'image_size'=>'cpac-custom',
					'image_size_w'=>'60',
					'image_size_h'=>'60',
					'edit'=>'off',
					'sort'=>'off',
					'filter'=>'off',
					'filter_label'=>'',
					'name'=>'featured-image',
					'label_type'=>'',
					'search'=>'on'
				),
				'title'=>array(
					'type'=>'title',
					'label'=>'Name',
					'width'=>'',
					'width_unit'=>'%',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'title',
					'label_type'=>'',
					'search'=>'on'
				),		
				'date-published'=>array(
					'type'=>'column-date_published',
					'label'=>'Date Published',
					'width'=>'',
					'width_unit'=>'%',
					'date_format'=>'wp_default',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'date-published',
					'label_type'=>'',
					'search'=>'on'
				),		
				'rating'=>array(
					'type'=>'column-meta',
					'label'=>'Rating',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'testimonial_rating',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'rating',
					'label_type'=>'',
					'editable_type'=>'textarea',
					'search'=>'on'
				),
				'last_viewed'=>array(
					'type'=>'column-meta',
					'label'=>'Last Viewed',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-tease-time',
					'field_type'=>'date',
					'date_format'=>'wp_default',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last_viewed',
					'label_type'=>'',
					'search'=>'on'
				),	
				'views_week'=>array(
					'type'=>'column-meta',
					'label'=>'This Week',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-7day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_week',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_month'=>array(
					'type'=>'column-meta',
					'label'=>'This Month',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-30day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_month',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_total'=>array(
					'type'=>'column-meta',
					'label'=>'Views Total',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-all',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_total',
					'label_type'=>'',
					'search'=>'on'
				),
				'location'=>array(
					'type'=>'column-meta',
					'label'=>'Location',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'testimonial_location',
					'field_type'=>'checkmark',
					'before'=>'',
					'after'=>'',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'location',
					'label_type'=>'',
					'search'=>'on'
				),
				'business'=>array(
					'type'=>'column-meta',
					'label'=>'Business',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'testimonial_biz',
					'field_type'=>'checkmark',
					'before'=>'',
					'after'=>'',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'business',
					'label_type'=>'',
					'search'=>'on'
				),
				'website'=>array(
					'type'=>'column-meta',
					'label'=>'Website',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'testimonial_website',
					'field_type'=>'checkmark',
					'before'=>'',
					'after'=>'',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'website',
					'label_type'=>'',
					'search'=>'on'
				)
			),
			'layout'=>array(
				'id'=>'5cbb315787688',
				'name'=>'battleplan',
				'roles'=>false,
				'users'=>false,
				'read_only'=>false
			)			
		)
	) );
	ac_register_columns( 'galleries', array(
		array(
			'columns'=>array(
				'featured-image'=>array(
					'type'=>'column-featured_image',
					'label'=>'',
					'width'=>'80',
					'width_unit'=>'px',
					'featured_image_display'=>'image',
					'image_size'=>'cpac-custom',
					'image_size_w'=>'60',
					'image_size_h'=>'60',
					'edit'=>'off',
					'sort'=>'off',
					'filter'=>'off',
					'filter_label'=>'',
					'name'=>'featured-image',
					'label_type'=>'',
					'search'=>'on'
				),
				'title'=>array(
					'type'=>'title',
					'label'=>'Title',
					'width'=>'',
					'width_unit'=>'%',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'title',
					'label_type'=>'',
					'search'=>'on'
				),
				'slug'=>array(
					'type'=>'column-slug',
					'label'=>'Slug',
					'width'=>'15',
					'width_unit'=>'%',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'slug',
					'label_type'=>'',
					'search'=>'on'
				),
				'gallery-type'=>array(
					'type'=>'column-taxonomy',
					'label'=>'Type',
					'width'=>'',
					'width_unit'=>'%',
					'taxonomy'=>'gallery-type',
					'edit'=>'on',
					'enable_term_creation'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'gallery-type',
					'label_type'=>'',
					'search'=>'on'
				),
				'last-modified'=>array(
					'type'=>'column-modified',
					'label'=>'Last Modified',
					'width'=>'',
					'width_unit'=>'%',
					'date_format'=>'diff',
					'edit'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last-modified',
					'label_type'=>'',
					'search'=>'on'
				),
				'date-published'=>array(
					'type'=>'column-date_published',
					'label'=>'Date Published',
					'width'=>'',
					'width_unit'=>'%',
					'date_format'=>'wp_default',
					'edit'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'date-published',
					'label_type'=>'',
					'search'=>'on'
				),
				'gallery-tags'=>array(
					'type'=>'column-taxonomy',
					'label'=>'Gallery Tags',
					'width'=>'',
					'width_unit'=>'%',
					'taxonomy'=>'gallery-tags',
					'edit'=>'on',
					'enable_term_creation'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'gallery-tags',
					'label_type'=>'',
					'search'=>'on'
				)
			),
			'layout'=>array(
				'id'=>'5cbb31578ee04',
				'name'=>'battleplan',
				'roles'=>false,
				'users'=>false,
				'read_only'=>false
			)			
		)
	) );
	ac_register_columns( 'post', array(
		array(
			'columns'=>array(
				'featured-image'=>array(
					'type'=>'column-featured_image',
					'label'=>'',
					'width'=>'80',
					'width_unit'=>'px',
					'featured_image_display'=>'image',
					'image_size'=>'cpac-custom',
					'image_size_w'=>'60',
					'image_size_h'=>'60',
					'edit'=>'off',
					'sort'=>'off',
					'filter'=>'off',
					'filter_label'=>'',
					'name'=>'featured-image',
					'label_type'=>'',
					'search'=>'on'
				),
				'title'=>array(
					'type'=>'title',
					'label'=>'Title',
					'width'=>'',
					'width_unit'=>'%',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'title',
					'label_type'=>'',
					'search'=>'on'
				),
				'column-slug'=>array(
					'type'=>'column-slug',
					'label'=>'Slug',
					'width'=>'',
					'width_unit'=>'%',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'column-slug',
					'label_type'=>'',
					'search'=>'on'
				),
				'last-modified'=>array(
					'type'=>'column-modified',
					'label'=>'Last Modified',
					'width'=>'130',
					'width_unit'=>'px',
					'date_format'=>'diff',
					'edit'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last-modified',
					'label_type'=>'',
					'search'=>'on'
				),
				'date-published'=>array(
					'type'=>'column-date_published',
					'label'=>'Date Published',
					'width'=>'130',
					'width_unit'=>'px',
					'date_format'=>'wp_default',
					'edit'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'date-published',
					'label_type'=>'',
					'search'=>'on'
				),
				'last_viewed'=>array(
					'type'=>'column-meta',
					'label'=>'Last Viewed',
					'width'=>'130',
					'width_unit'=>'px',
					'field'=>'post-tease-time',
					'field_type'=>'date',
					'date_format'=>'wp_default',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last_viewed',
					'label_type'=>'',
					'search'=>'on'
				),	
				'views_week'=>array(
					'type'=>'column-meta',
					'label'=>'This Week',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-7day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_week',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_month'=>array(
					'type'=>'column-meta',
					'label'=>'This Month',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-30day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_month',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_total'=>array(
					'type'=>'column-meta',
					'label'=>'Views Total',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-all',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_total',
					'label_type'=>'',
					'search'=>'on'
				),
				'categories'=>array(
					'type'=>'categories',
					'label'=>'Categories',
					'width'=>'100',
					'width_unit'=>'px',
					'edit'=>'on',
					'enable_term_creation'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'name'=>'categories',
					'label_type'=>'',
					'filter_label'=>'',
					'search'=>'on'
				),
				'tags'=>array(
					'type'=>'tags',
					'label'=>'Tags',
					'width'=>'100',
					'width_unit'=>'px',
					'edit'=>'on',
					'enable_term_creation'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'tags',
					'label_type'=>'',
					'search'=>'on'
				),
				'author'=>array(
					'type'=>'author',
					'label'=>'Author',
					'width'=>'',
					'width_unit'=>'%',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'author',
					'label_type'=>'',
					'search'=>'on'
				)
			),
			'layout'=>array(
				'id'=>'5cbb31579092a',
				'name'=>'battleplan',
				'roles'=>false,
				'users'=>false,
				'read_only'=>false
			)			
		)
	) );
	ac_register_columns( 'page', array(
		array(
			'columns'=>array(
				'title'=>array(
					'type'=>'title',
					'label'=>'Page',
					'width'=>'',
					'width_unit'=>'%',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'title',
					'label_type'=>'',
					'search'=>'on'
				),
				'slug'=>array(
					'type'=>'column-slug',
					'label'=>'Slug',
					'width'=>'130',
					'width_unit'=>'px',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'slug',
					'label_type'=>'',
					'search'=>'on'
				),
				'post-id'=>array(
					'type'=>'column-postid',
					'label'=>'ID',
					'width'=>'60',
					'width_unit'=>'px',
					'before'=>'',
					'after'=>'',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'post-id',
					'label_type'=>'',
					'search'=>'on'
				),
				'last-modified'=>array(
					'type'=>'column-modified',
					'label'=>'Last Modified',
					'width'=>'130',
					'width_unit'=>'px',
					'date_format'=>'diff',
					'edit'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last-modified',
					'label_type'=>'',
					'search'=>'on'
				),
				'date-published'=>array(
					'type'=>'column-date_published',
					'label'=>'Date Published',
					'width'=>'130',
					'width_unit'=>'px',
					'date_format'=>'wp_default',
					'edit'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'date-published',
					'label_type'=>'',
					'search'=>'on'
				),
				'last_viewed'=>array(
					'type'=>'column-meta',
					'label'=>'Last Viewed',
					'width'=>'130',
					'width_unit'=>'px',
					'field'=>'post-tease-time',
					'field_type'=>'date',
					'date_format'=>'wp_default',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last_viewed',
					'label_type'=>'',
					'search'=>'on'
				),	
				'views_week'=>array(
					'type'=>'column-meta',
					'label'=>'This Week',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-7day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_week',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_month'=>array(
					'type'=>'column-meta',
					'label'=>'This Month',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-30day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_month',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_total'=>array(
					'type'=>'column-meta',
					'label'=>'Views Total',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-all',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_total',
					'label_type'=>'',
					'search'=>'on'
				),
				'attachments'=>array(
					'type'=>'column-attachment',
					'label'=>'Attachments',
					'width'=>'400',
					'width_unit'=>'px',
					'attachment_display'=>'thumbnail',
					'image_size'=>'cpac-custom',
					'image_size_w'=>'60',
					'image_size_h'=>'60',
					'number_of_items'=>'10',
					'edit'=>'on',
					'sort'=>'on',
					'name'=>'attachments',
					'label_type'=>''
				)
			),
			'layout'=>array(
				'id'=>'5cbb31579168e',
				'name'=>'battleplan',
				'roles'=>false,
				'users'=>false,
				'read_only'=>false
			)			
		)
	) );
	ac_register_columns( 'wp-media', array(
		array(
			'columns'=>array(
				'image'=>array(
					'type'=>'column-image',
					'label'=>'Image',
					'width'=>'140',
					'width_unit'=>'px',
					'image_size'=>'cpac-custom',
					'image_size_w'=>'130',
					'image_size_h'=>'130',
					'name'=>'image',
					'label_type'=>''
				),
				'filename'=>array(
					'type'=>'column-file_name',
					'label'=>'Filename',
					'width'=>'',
					'width_unit'=>'%',
					'sort'=>'on',
					'name'=>'filename',
					'label_type'=>''
				),
				'alt-text' => array(
					'type' => 'column-alternate_text',
					'label' => 'Alt Text',
					'width' => '150',
					'width_unit' => 'px',
					'use_icons' => '',
					'name' => 'column-alternate_text',
					'label_type' => '',
					'edit' => 'on',
					'sort' => 'on',
					'filter'=>'on',
					'filter_label'=>'',
					'bulk-editing' => '',
					'export' => '',
					'search' => ''
				),
				'date'=>array(
					'type'=>'date',
					'label'=>'Date',
					'width'=>'100',
					'width_unit'=>'px',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'date',
					'label_type'=>'',
					'search'=>'on'
				),
				'image-id'=>array(
					'type'=>'column-mediaid',
					'label'=>'ID',
					'width'=>'',
					'width_unit'=>'%',
					'sort'=>'on',
					'name'=>'image-id',
					'label_type'=>'',
					'search'=>'on'
				),
				'last_viewed'=>array(
					'type'=>'column-meta',
					'label'=>'Last Viewed',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-tease-time',
					'field_type'=>'date',
					'date_format'=>'wp_default',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'filter_format'=>'monthly',
					'name'=>'last_viewed',
					'label_type'=>'',
					'search'=>'on'
				),	
				'views_week'=>array(
					'type'=>'column-meta',
					'label'=>'This Week',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-7day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_week',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_month'=>array(
					'type'=>'column-meta',
					'label'=>'This Month',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-30day',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_month',
					'label_type'=>'',
					'search'=>'on'
				),
				'views_total'=>array(
					'type'=>'column-meta',
					'label'=>'Views Total',
					'width'=>'',
					'width_unit'=>'%',
					'field'=>'post-views-total-all',
					'field_type'=>'numeric',
					'before'=>'',
					'after'=>'',
					'edit'=>'off',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'views_total',
					'label_type'=>'',
					'search'=>'on'
				),
				'taxonomy-image-categories'=>array(
					'type'=>'taxonomy-image-categories',
					'label'=>'Image Categories',
					'width'=>'',
					'width_unit'=>'%',
					'edit'=>'on',
					'enable_term_creation'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'taxonomy-image-categories',
					'label_type'=>'',
					'search'=>'on'
				),
				'taxonomy-image-tags'=>array(
					'type'=>'taxonomy-image-tags',
					'label'=>'Image Tags',
					'width'=>'',
					'width_unit'=>'%',
					'edit'=>'on',
					'enable_term_creation'=>'on',
					'sort'=>'on',
					'filter'=>'on',
					'filter_label'=>'',
					'name'=>'taxonomy-image-tags',
					'label_type'=>'',
					'search'=>'on'
				),
				'sizes'=>array(
					'type'=>'column-available_sizes',
					'label'=>'Sizes',
					'width'=>'200',
					'width_unit'=>'px',
					'include_missing_sizes'=>'',
					'sort'=>'on',
					'name'=>'sizes',
					'label_type'=>''
				)
			),
			'layout'=>array(
				'id'=>'5cbb3157923d6',
				'name'=>'battleplan',
				'roles'=>false,
				'users'=>false,
				'read_only'=>false
			)			
		)
	) );
}

/*--------------------------------------------------------------
# Basic Theme Set Up
--------------------------------------------------------------*/

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
	if ( is_admin() || !is_main_query() || $orderby != "RAND()") return $orderby;
		
	session_start();
	
	if( ! get_query_var( 'paged' ) || get_query_var( 'paged' ) == 0 || get_query_var( 'paged' ) == 1 ) {
		if( isset( $_SESSION['seed'] ) ) { unset( $_SESSION['seed'] ); }
	}
	$seed = false;
	if( isset( $_SESSION['seed'] ) ) { $seed = $_SESSION['seed']; }
	if ( !$seed ) {
		$seed = rand();
		$_SESSION['seed'] = $seed;
	}
	$orderby = 'rand('.$seed.')';

    return $orderby;
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
            $current_term_link  = $before . $taxonomy_object->labels->name . ': ' . $term_name . $after;
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
                $breadcrumb_trail = $parent_term_string . $delimiter . $current_term_link;
            else :
                $breadcrumb_trail = $current_term_link;
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
        $current_page = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' );
        $page_addon   = $before . sprintf( __( ' ( Page %s )' ), number_format_i18n( $current_page ) ) . $after;
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

// Set up post meta date
function battleplan_meta_date() {
	$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
	if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) : $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="entry-date updated" datetime="%3$s">%4$s</time>'; endif;

	$time_string = sprintf ( $time_string, esc_attr( get_the_date( DATE_W3C ) ), esc_html( get_the_date() ), esc_attr( get_the_modified_date( DATE_W3C ) ), esc_html( get_the_modified_date() ) );
	$posted_on = sprintf ( esc_html_x( '%s', 'post date', 'battleplan' ), $time_string );

	return '<span class="meta-date"><i class="fas fa-calendar-alt"></i>'.$posted_on.'</span>';
}

// Set up post meta author
function battleplan_meta_author() {
	$byline = sprintf ( esc_html_x( '%s', 'post author', 'battleplan' ), '<span class="author vcard"><a class="url fn n" href="'.esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) )).'">'.esc_html( get_the_author() ).'</a></span>' );

	return '<span class="meta-author"><i class="fas fa-user"></i>'.$byline.'</span>';
}

// Set up post meta comments
function battleplan_meta_comments() {		
	return '<span class="meta-comments"><i class="fas fa-comments"></i>'.get_comments_number().'</span>';
}

// Set up footer social media box
function battleplan_footer_social_box() {	
	$buildLeft = "<div class='social-box'>";
		if ( do_shortcode('[get-biz info="facebook"]') ) $buildLeft .= do_shortcode('[social-btn type="facebook"]'); 							
		if ( do_shortcode('[get-biz info="twitter"]') ) $buildLeft .= do_shortcode('[social-btn type="twitter"]');						
		if ( do_shortcode('[get-biz info="instagram"]') ) $buildLeft .= do_shortcode('[social-btn type="instagram"]');							
		if ( do_shortcode('[get-biz info="linkedin"]') ) $buildLeft .= do_shortcode('[social-btn type="linkedin"]');							
		if ( do_shortcode('[get-biz info="yelp"]') ) $buildLeft .= do_shortcode('[social-btn type="yelp"]');							
		if ( do_shortcode('[get-biz info="email"]') ) $buildLeft .= do_shortcode('[social-btn type="email"]');
	$buildLeft .= "</div>";
	return $buildLeft;
}

//Disable Gutenburg
add_filter('use_block_editor_for_post', '__return_false');

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
		register_nav_menus( array( 'footer-menu' => esc_html__( 'Footer Menu', 'battleplan' ), ) );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', ) );
		add_theme_support( 'custom-background', apply_filters( 'battleplan_custom_background_args', array( 'default-color' => 'ffffff', 'default-image' => '', ) ) );
		add_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support( 'custom-logo', array( 'height' => 250, 'width' => 250, 'flex-width'  => true, 'flex-height' => true, ) );
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

// Load and enqueue styles & scripts
add_action( 'wp_enqueue_scripts', 'battleplan_scripts', 20 );
function battleplan_scripts() {
	wp_enqueue_style( 'battleplan-animate', get_template_directory_uri().'/animate.css', array(), _BP_VERSION );	
	wp_enqueue_style( 'battleplan-ie', get_template_directory_uri()."/style-ie.css", array(), _BP_VERSION );
	wp_enqueue_style( 'battleplan-fontawesome', get_template_directory_uri()."/fontawesome.css", array(), _BP_VERSION );
	if ( is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) ) { wp_enqueue_style( 'battleplan-events', get_template_directory_uri()."/style-events.css", array(), _BP_VERSION ); } 	
	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { wp_enqueue_style( 'battleplan-woocommerce', get_template_directory_uri()."/style-woocommerce.css", array(), _BP_VERSION ); } 
	
	wp_enqueue_script( 'battleplan-bootstrap', get_template_directory_uri().'/js/bootstrap.js', array(), _BP_VERSION, true );
	wp_enqueue_script( 'battleplan-parallax', get_template_directory_uri().'/js/parallax.js', array(), _BP_VERSION, true );
	wp_enqueue_script( 'battleplan-waypoints', get_template_directory_uri().'/js/waypoints.js', array(), _BP_VERSION, true );
	wp_enqueue_script( 'battleplan-script', get_template_directory_uri().'/js/script.js', array(), _BP_VERSION, true );
	if ( is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) ) { wp_enqueue_script( 'battleplan-events', get_template_directory_uri().'/js/events.js', array(), _BP_VERSION, true ); } 
	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { wp_enqueue_script( 'battleplan-woocommerce', get_template_directory_uri().'/js/woocommerce.js', array(), _BP_VERSION, true ); } 
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) { wp_enqueue_script( 'comment-reply' ); }
	
	$getUploadDir = wp_upload_dir();
	$getThemeDir = get_stylesheet_directory_uri();
	$saveDir = array( 'theme_dir_uri'=>$getThemeDir, 'upload_dir_uri'=>$getUploadDir['baseurl'] );
	wp_localize_script( 'battleplan-script-site', 'theme_dir', $saveDir );
}

add_action( 'admin_enqueue_scripts', 'battleplan_admin_scripts' );
function battleplan_admin_scripts() {
	wp_enqueue_style( 'battleplan-admin', get_template_directory_uri().'/style-admin.css', array(), _BP_VERSION );		
}

require get_template_directory() . '/includes/includes-universal.php';
if ( is_plugin_active( 'the-events-calendar/the-events-calendar.php' ) ) { require get_template_directory() . '/includes/includes-events.php'; } 
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { require get_template_directory() . '/includes/includes-woocommerce.php'; } 

// Dequeue unneccesary styles & scripts
add_action( 'wp_print_styles', 'battleplan_dequeue_unwanted_stuff', 99 );
function battleplan_dequeue_unwanted_stuff() {
	wp_dequeue_style( 'wp-block-library' );  wp_deregister_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );  wp_deregister_style( 'wp-block-library-theme' );	
	wp_dequeue_style( 'css-animate' );  wp_deregister_style( 'css-animate' );
	wp_dequeue_style( 'select2' );  wp_deregister_style( 'select2' );
	wp_dequeue_style( 'fontawesome' ); wp_deregister_style( 'fontawesome' );
	
	wp_dequeue_script( 'select2'); wp_deregister_script('select2');	
	wp_dequeue_script( 'wphb-global' ); wp_deregister_script( 'wphb-global' );
	wp_dequeue_script( 'wp-embed' ); wp_deregister_script( 'wp-embed' );
	wp_dequeue_script( 'modernizr' ); wp_deregister_script( 'modernizr' );
	if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) { wp_dequeue_script( 'underscore' ); wp_deregister_script( 'underscore' ); } 
}

//Brand log-in screen with BP Knight
add_action( 'login_enqueue_scripts', 'battleplan_login_logo' );
function battleplan_login_logo() { ?><style type="text/css">body.login div#login h1 a { background-image: url(https://battleplanassets.com/images/logo-knight.png); padding-bottom: 120px; width: 100%;	background-size: 50%} #login {padding-top:70px !important} </style> <?php } 

// Add Location (site tagline) to Admin Bar
add_action( 'admin_bar_menu', 'battleplan_addTaglineToAdminBar', 999 );
function battleplan_addTaglineToAdminBar( $wp_admin_bar ) {
	$args = array( 'id' => 'tagline', 'title' => '-&nbsp;&nbsp;'.get_bloginfo( 'description' ).'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', );
	$wp_admin_bar->add_node( $args );
}

add_action( 'wp_before_admin_bar_render', 'battleplan_reorderAdminBar');
function battleplan_reorderAdminBar() {
    global $wp_admin_bar;
    $IDs_sequence = array('wp-logo', 'site-name', 'tagline', 'updates', 'comments', 'wphb', 'new-content' );
    $nodes = $wp_admin_bar->get_nodes();
    foreach ( $IDs_sequence as $id ) {
        if ( ! isset($nodes[$id]) ) continue;
        $wp_admin_bar->remove_menu($id);
        $wp_admin_bar->add_node($nodes[$id]);
        unset($nodes[$id]);
    }
    foreach ( $nodes as $id => &$obj ) {
        if ( ! empty($obj->parent) ) continue;
        $wp_admin_bar->remove_menu($id);
        $wp_admin_bar->add_node($obj);
    }
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

		$output .= sprintf( '%s<li%s%s%s>',
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
				$value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
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

		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}

}

// Set up spam filter for Contact Form 7 emails
add_filter( 'wpcf7_validate_textarea', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_text', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_text*', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_email', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_email*', 'battleplan_contact_form_spam_blocker', 20, 2 );
function battleplan_contact_form_spam_blocker( $result, $tag ) {
    if ( "user-message" == $tag->name ) {
		$check = isset( $_POST["user-message"] ) ? trim( $_POST["user-message"] ) : ''; 
		$name = isset( $_POST["user-name"] ) ? trim( $_POST["user-name"] ) : ''; 
		$badwords = array('Pandemic Recovery','bitcoin','mаlwаre','antivirus','marketing','SEO','website','web-site','web site','web design','Wordpress','Chiirp','@Getreviews','Cost Estimation','Guarantee Estimation','World Wide Estimating','Postmates delivery','loans for small businesses','New Hire HVAC Employee','и','д','б','й','л','ы','З','у','Я');
		$webwords = array('.com','http://','https://','.net','.org','www.','.buzz');
		if ( $check == $name ) $result->invalidate( $tag, 'Message cannot be sent.' );
		foreach($badwords as $badword) {
			if (stripos($check,$badword) !== false) $result->invalidate( $tag, 'We do not accept messages containing the word(s) "'.$badword.'".' );
		}
		foreach($webwords as $webword) {
			if (stripos($check,$webword) !== false) $result->invalidate( $tag, 'We do not accept messages containing website addresses.' );
		}		
	}
    if ( "user-email" == $tag->name ) {
		$badwords = array('testing.com', 'test@', 'b2blistbuilding.com', 'amy.wilsonmkt@gmail.com', '@agency.leads.fish', 'landrygeorge8@gmail.com', '@digitalconciergeservice.com', '@themerchantlendr.com', '@fluidbusinessresources.com', '@focal-pointcoaching.net', '@zionps.com', '@rddesignsllc.com');
        $check = isset( $_POST["user-email"] ) ? trim( $_POST["user-email"] ) : ''; 
		foreach($badwords as $badword) {
			if (stripos($check,$badword) !== false) $result->invalidate( $tag, 'We do not accept messages from this email address.');
		}
	}
    if ( 'user-email-confirm' == $tag->name ) {
        $user_email = isset( $_POST['user-email'] ) ? trim( $_POST['user-email'] ) : '';
        $user_email_confirm = isset( $_POST['user-email-confirm'] ) ? trim( $_POST['user-email-confirm'] ) : '';
        if ( $user_email != $user_email_confirm ) $result->invalidate( $tag, "Are you sure this is the correct email?" );
    } 
    return $result;
}

// Block loading of refill file (Contact Form 7) to help speed up sites
add_action('wp_footer', 'battleplan_no_contact_form_refill', 99); 
function battleplan_no_contact_form_refill() { ?><script>wpcf7.cached = 0;</script><?php }

// Replace WordPress copyright message at bottom of admin page
add_filter('admin_footer_text', 'battleplan_remove_footer_admin');
function battleplan_remove_footer_admin () { echo 'Powered by <a href="https://battleplanwebdesign.com" target="_blank">Battle Plan Web Design</a></p>'; }

// Change Howdy text
add_filter( 'admin_bar_menu', 'battleplan_replace_howdy', 25 );
function battleplan_replace_howdy( $wp_admin_bar ) {
	 $my_account=$wp_admin_bar->get_node('my-account');
	 $newtitle = str_replace( 'Howdy,', 'Welcome,', $my_account->title );
	 $wp_admin_bar->add_node( array( 'id'=>'my-account', 'title'=>$newtitle, ) );
 }

// Hide the Wordpress admin bar
show_admin_bar( false );

// Remove auto <p> from around images 
add_filter('the_content', 'battleplan_remove_ptags_on_images', 9999);
function battleplan_remove_ptags_on_images($content){
   return preg_replace('/<p>\s*(<a .*>)?\s*(<img .* \/>)\s*(<\/a>)?\s*<\/p>/iU', '\1\2\3', $content);
}

// Remove auto <p> from inside widgets 
remove_filter('widget_text_content', 'wpautop'); 

// Enable Shortcodes in Text Widgets
add_filter('widget_text','do_shortcode');

// Turn off WP image smusher
add_filter( 'jpeg_quality', 'battleplan_smashing_jpeg_quality' );
function battleplan_smashing_jpeg_quality() { return 100; }

/* Set up sizes for srcset */
function get_srcset( $size ) {	
	$ratio1280 = ($size / 1280) * 100; 
	if ( $ratio1280 <= 40 ) : $ratio1280 = 40;
	elseif ( $ratio1280 <= 75 ) : $ratio1280 = 60;
	else: $ratio1280 = 100; endif;
	
	$ratio1024 = ($size / 1024) * 100; 
	if ( $ratio1024 <= 40 ) : $ratio1024 = 33;
	elseif ( $ratio1024 <= 75 ) : $ratio1024 = 50;
	else: $ratio1024 = 100; endif;
	
	$ratio860 = ($size / 1024) * 100; 
	if ( $ratio860 <= 33 ) : $ratio860 = 50;
	else: $ratio860 = 100; endif;
	
	$ratio575 = ($size / 1024) * 100; 
	if ( $ratio575 <= 25 ) : $ratio575 = 50;
	else: $ratio575 = 100; endif;

	return '(max-width: 575px) '.$ratio575.'vw, (max-width: 860px) '.$ratio860.'vw, (max-width: 1024px) '.$ratio1024.'vw, (max-width: 1280px) '.$ratio1280.'vw, '.$size.'px';
}

/* Establish default image sizes */
if ( function_exists( 'add_image_size' ) ) {	
	add_image_size( 'icon', 80, 80, false ); 
	add_image_size( 'quarter-s', 240, 99999, false ); 
	add_image_size( 'third-s', 320, 99999, false ); 	
	add_image_size( 'half-s', 480, 99999, false ); 
	add_image_size( 'full-s', 960, 99999, false ); 
	add_image_size( 'quarter-f', 320, 99999, false ); 
	add_image_size( 'third-f', 430, 99999, false ); 
	add_image_size( 'half-f', 640, 99999, false ); 
	add_image_size( 'full-f', 1280, 99999, false ); 
	add_image_size( 'max', 1920, 99999, false ); 
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

/* Set new max srcset image */
add_filter( 'max_srcset_image_width', 'battleplan_remove_max_srcset_image_width' );
function battleplan_remove_max_srcset_image_width( $max_width ) {
	$max_width = 2000;
	return $max_width;
}

/* Set the size param in srcset image */
add_filter('wp_calculate_image_sizes', 'battleplan_content_image_sizes_attr', 10 , 2);
function battleplan_content_image_sizes_attr($sizes, $size) {
	return get_srcset($size[0]);
}

/* Remove https://domain.com, width & height params from the <img> inserted by WordPress */
add_filter( 'image_send_to_editor', 'battleplan_remove_junk_from_image', 10 );
function battleplan_remove_junk_from_image( $html ) {
   $html = preg_replace( '/(width|height)="\d*"\s/', "", $html );
   $html = str_replace( get_site_url(), "", $html );
   return $html;
}

// Add attachment ID to attached images as 'data-id'
add_filter( 'wp_get_attachment_image_attributes', 'battleplan_attachment_id_on_images', 20, 2 );
function battleplan_attachment_id_on_images( $attr, $attachment ) {
	$attr['data-id'] = $attachment->ID;
	return $attr;
}

// Automatically set the image Title, Alt-Text, Caption & Description upon upload
add_action( 'add_attachment', 'battleplan_setImageMetaUponUpload' );
function battleplan_setImageMetaUponUpload( $post_ID ) {
	if ( wp_attachment_is_image( $post_ID ) ) {
		$imageTitle = get_post( $post_ID )->post_title;
		$imageTitle = ucwords( preg_replace( '%\s*[-_\s]+\s*%', ' ', $imageTitle )); // remove hyphens, underscores & extra spaces and capitalize
		$imageMeta = array ( 'ID' => $post_ID, 'post_title' => $imageTitle ) /* post title */;			 
		update_post_meta( $post_ID, '_wp_attachment_image_alt', $imageTitle ) /* alt text */;
		wp_update_post( $imageMeta );
	} 
}

// Do not resize animated .gif 
add_filter('intermediate_image_sizes_advanced', 'battleplan_disable_upload_sizes', 10, 2); 
function battleplan_disable_upload_sizes( $sizes, $metadata ) {
    $filetype = wp_check_filetype($metadata['file']);
    if($filetype['type'] == 'image/gif') { $sizes = array(); }
    return $sizes;
}   

// Highlights menu option based on the post type of the current page and the title attribute given to the menu button in Appearance->Menus
add_filter('nav_menu_css_class', 'battleplan_current_type_nav_class', 10, 2 );
function battleplan_current_type_nav_class($classes, $item) {
	$post_type = get_post_type();
	if ( $post_type != 'post' ) :
		$classes = str_replace( 'current_page_parent', '', $classes );
		if ( $item->url == '/'.$post_type ) : $classes = str_replace( 'menu-item', 'menu-item current_page_parent', $classes ); endif;
	endif;
	if ($item->attr_title != '' && $item->attr_title == $post_type) { array_push($classes, 'current-menu-item'); };
	
	// Support for The Events Calendar PRO - plug-in
	if ( ($item->attr_title == "tribe_events" || $item->attr_title == "events" ) && (strpos(battleplan_getURL(), '/event/') !== false || strpos(battleplan_getURL(), '/events/') !== false) ) {
		array_push($classes, 'current-menu-item');		
	}
	return $classes;
}

// Rename "Uncategorized" posts to "Blog"
wp_update_term(1, 'category', array( 'name'=>'Blog', 'slug'=>'blog' ));

// Display custom fields in WordPress admin edit screen
//add_filter('acf/settings/remove_wp_meta_box', '__return_false');

// Add 'post-views-*' fields to an image when it is uploaded
add_action( 'add_attachment', 'battleplan_addWidgetPicViewsToImg', 10, 9 );
function battleplan_addWidgetPicViewsToImg( $post_ID ) {
	if ( wp_attachment_is_image( $post_ID ) ) {		
		updateMeta( $post_ID, 'post-views-time', '--' );			
		updateMeta( $post_ID, 'post-tease-time', '--' );			
		updateMeta( $post_ID, 'post-views-total-all', '0' );			
		updateMeta( $post_ID, 'post-views-total-7day', '0' );		
		updateMeta( $post_ID, 'post-views-total-30day', '0' );
		updateMeta( $post_ID, 'post-views-record', '0' );					
		updateMeta( $post_ID, 'post-views-record-date', '--' );
		for ($x = 1; $x <= 30; $x++) {
			updateMeta( $post_ID, 'post-views-day-'.$x, array('date'=>'--', 'views'=>'0') );
		} 
	} 
}

// Add 'post-views-*' fields to posts/pages when published 
add_action( 'save_post', 'battleplan_addViewsToPost', 10, 3 );
function battleplan_addViewsToPost() {
	global $post; $post_ID = $post->ID;	
	if ( readMeta( $post_ID, 'post-views-total-all') == '' ) {
		updateMeta( $post_ID, 'post-views-time', '--' );			
		updateMeta( $post_ID, 'post-tease-time', '--' );			
		updateMeta( $post_ID, 'post-views-total-all', '0' );			
		updateMeta( $post_ID, 'post-views-total-7day', '0' );		
		updateMeta( $post_ID, 'post-views-total-30day', '0' );
		updateMeta( $post_ID, 'post-views-record', '0' );					
		updateMeta( $post_ID, 'post-views-record-date', '--' );
		for ($x = 1; $x <= 30; $x++) {
			updateMeta( $post_ID, 'post-views-day-'.$x, array('date'=>'--', 'views'=>'0') );
		} 
	}
}

// Clear views when post/page is cloned
add_action( 'dp_duplicate_post', 'battleplan_clearViews', 99, 2 );
add_action( 'dp_duplicate_page', 'battleplan_clearViews', 99, 2 );
function battleplan_clearViews($new_post_id) {
	$post_ID = $new_post_id;
	deleteMeta( $post_ID, 'post-bot-names');
	deleteMeta( $post_ID, 'post-bots');
	updateMeta( $post_ID, 'post-views-time', '--' );			
	updateMeta( $post_ID, 'post-tease-time', '--' );			
	updateMeta( $post_ID, 'post-views-total-all', '0' );			
	updateMeta( $post_ID, 'post-views-total-7day', '0' );		
	updateMeta( $post_ID, 'post-views-total-30day', '0' );
	updateMeta( $post_ID, 'post-views-record', '0' );					
	updateMeta( $post_ID, 'post-views-record-date', '--' );
	for ($x = 1; $x <= 30; $x++) {
		updateMeta( $post_ID, 'post-views-day-'.$x, array('date'=>'--', 'views'=>'0') );
	} 
}

// Force clear all views for posts/pages - run this from functions.php within a site's child theme
function battleplan_clearViewFields() {
	// clear image views
	$image_query = new WP_Query( array( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg,image/gif,image/jpg,image/png', 'posts_per_page'=>-1 ));
	if( $image_query->have_posts() ) : while ($image_query->have_posts() ) : $image_query->the_post();
		updateMeta( get_the_ID(), 'post-views-time', '--' );			
		updateMeta( get_the_ID(), 'post-tease-time', '--' );			
		updateMeta( get_the_ID(), 'post-views-total-all', '0' );			
		updateMeta( get_the_ID(), 'post-views-total-7day', '0' );		
		updateMeta( get_the_ID(), 'post-views-total-30day', '0' );
		updateMeta( get_the_ID(), 'post-views-record', '0' );					
		updateMeta( get_the_ID(), 'post-views-record-date', '--' );
		for ($x = 1; $x <= 30; $x++) {
			updateMeta( get_the_ID(), 'post-views-day-'.$x, array('date'=>'--', 'views'=>'0') );
		} 

	endwhile; wp_reset_postdata(); endif;
	
	// clear site load speed logs
	$siteHeader = getID('site-header');
	updateMeta( $siteHeader, 'load-number-desktop', '0' );			
	updateMeta( $siteHeader, 'load-speed-desktop', '0' );			
	updateMeta( $siteHeader, 'load-number-mobile', '0' );			
	updateMeta( $siteHeader, 'load-speed-mobile', '0' );			

	// clear posts/pages views
	$getCPT = get_post_types();  
	unset($getCPT['attachment'], $getCPT['revision'], $getCPT['nav_menu_item'], $getCPT['custom_css'], $getCPT['customize_changeset'], $getCPT['oembed_cache'], $getCPT['user_request'], $getCPT['wp_block'], $getCPT['acf-field-group'], $getCPT['acf-field'], $getCPT['wpcf7_contact_form'], $getCPT['wphb_minify_group']); 	
	foreach ($getCPT as $postType) {
		$getPosts = new WP_Query( array ('posts_per_page'=>-1, 'post_type'=>$postType ));
		if ( $getPosts->have_posts() ) : while ( $getPosts->have_posts() ) : $getPosts->the_post(); 
			deleteMeta( get_the_ID(), '_wp_page_template');
			deleteMeta( get_the_ID(), '_responsive_layout');
			deleteMeta( get_the_ID(), 'post-bot-names');
			deleteMeta( get_the_ID(), 'post-bots');
			deleteMeta( get_the_ID(), 'add-view-fields');
			deleteMeta( get_the_ID(), 'check-pics-for-views');
			updateMeta( get_the_ID(), 'post-views-time', '--' );			
			updateMeta( get_the_ID(), 'post-tease-time', '--' );			
			updateMeta( get_the_ID(), 'post-views-total-all', '0' );			
			updateMeta( get_the_ID(), 'post-views-total-7day', '0' );		
			updateMeta( get_the_ID(), 'post-views-total-30day', '0' );
			updateMeta( get_the_ID(), 'post-views-record', '0' );					
			updateMeta( get_the_ID(), 'post-views-record-date', '--' );
			for ($x = 1; $x <= 30; $x++) {
				updateMeta( get_the_ID(), 'post-views-day-'.$x, array('date'=>'--', 'views'=>'0') );
			} 
		endwhile; wp_reset_postdata(); endif;
	}	
} 

// Set excerpt length
add_filter( 'excerpt_length', 'battleplan_excerpt_length', 999 );
function battleplan_excerpt_length( $length ) { 
	return 20; 
} 

// Remove unwanted dashboard widgets
add_action('wp_dashboard_setup', 'battleplan_remove_dashboard_widgets');
function battleplan_remove_dashboard_widgets () {
	remove_action('welcome_panel','wp_welcome_panel'); // Welcome to WordPress!
	remove_meta_box('dashboard_primary','dashboard','side'); //WordPress.com Blog
	remove_meta_box('dashboard_right_now','dashboard','side');
	remove_meta_box('dashboard_quick_press','dashboard','side'); //Quick Press widget
	remove_meta_box('tribe_dashboard_widget', 'dashboard', 'normal'); // News From Modern Tribe
	remove_meta_box('wpe_dify_news_feed','dashboard','side'); //WP Engine
}

// Add new dashboard widgets
add_action( 'wp_dashboard_setup', 'battleplan_add_dashboard_widgets' );
function battleplan_add_dashboard_widgets() {
    wp_add_dashboard_widget( 'battleplan_site_stats', 'Site Stats', 'battleplan_admin_site_stats' );
}

// Set up Site Stats widget on dashboard
function battleplan_admin_site_stats() {
	$siteHeader = getID('site-header');
	$frontPage = get_option('page_on_front');
	$desktopCounted = readMeta($siteHeader, "load-number-desktop");
	$desktopSpeed = readMeta($siteHeader, "load-speed-desktop");	
	$mobileCounted = readMeta($siteHeader, "load-number-mobile");
	$mobileSpeed = readMeta($siteHeader, "load-speed-mobile");
	$lastEmail = readMeta($siteHeader, "last-email");
	$today = date("F j, Y, g:i a");	
	$current = strtotime($today);
	$daysSinceEmail = number_format((($current - $lastEmail) / 60 / 60 / 24));
	$totalCounted = $desktopCounted + $mobileCounted;		
	$lastViewed = readMeta($frontPage, 'post-views-time');
	$dateDiff = (($current - $lastViewed) / 60 / 60 / 24); $howLong = "day";
	if ( $dateDiff < 1 ) : $dateDiff = (($current - $lastViewed) / 60 / 60); $howLong = "hour"; endif;	
	if ( $dateDiff < 1 ) : $dateDiff = (($current - $lastViewed) / 60); $howLong = "minute"; endif;
	if ( $dateDiff != 1 ) $howLong = $howLong."s";	
	$dateDiff = number_format($dateDiff, 1);
	$totalViews = number_format(readMeta($frontPage, "post-views-total-all"));	
	$dailyMeta = readMeta($frontPage, 'post-views-day-1'); 
	$viewsToday = number_format($dailyMeta['views']);
	$last7Views = number_format(readMeta($frontPage, "post-views-total-7day"));
	$last30Views = number_format(readMeta($frontPage, "post-views-total-30day"));
	$recordViews = number_format(readMeta($frontPage, "post-views-record"));
	
	echo "<table><tr><td><b><u>Framework</u></b></td><td><b>"._BP_VERSION."</b></td></tr>";		
		
	echo "<tr><td>&nbsp;</td></tr>";	
	
	echo "<tr><td><b><u>Last Email</u></b></td><td>".sprintf( _n( '<b>%s</b> day ago', '<b>%s</b> days ago', $daysSinceEmail, 'battleplan' ), $daysSinceEmail )."</td></tr>";
	
	echo "<tr><td>&nbsp;</td></tr>";
	
	echo "<tr><td><b><u>Site Speed</u></b></td></tr>";
	echo "<tr><td><b>Desktop</b></td><td><b>".$desktopSpeed."s</b> on ".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $desktopCounted, 'battleplan' ), $desktopCounted )."</td></tr>";
	echo "<tr><td><b>Mobile</b></td><td><b>".$mobileSpeed."s</b> on ".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $mobileCounted, 'battleplan' ), $mobileCounted )."</td></tr>";
	
	echo "<tr><td>&nbsp;</td></tr>";
	
	echo "<tr><td><b><u>Home Page Visits</u></b></td></tr>";
	echo "<tr><td><b>Today</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), $viewsToday )."</td></tr>";	
	echo "<tr><td><b>Last 7 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), $last7Views )."</td></tr>";
	echo "<tr><td><b>Last 30 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), $last30Views )."</td></tr>";
	echo "<tr><td><b>Total</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $totalViews, 'battleplan' ), $totalViews )."</td></tr>";
	echo "<tr><td><b>Daily Record</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $recordViews, 'battleplan' ), $recordViews )."</td></tr>";
	echo "<tr><td><b>Last Viewed</b></td><td><b>".$dateDiff."</b> ".$howLong." ago</td></tr>";	
		
	echo "<tr><td>&nbsp;</td></tr>";
	
	for ($x = 29; $x >= 1; $x--) {
		$dailyMeta = readMeta($frontPage, 'post-views-day-'.$x); 
		$dailyTime[$x] = date("D, M jS", strtotime($dailyMeta['date'])); 				
		$dailyViews[$x] = intval($dailyMeta['views']); 
		echo "<tr><td><b>".$dailyTime[$x]."</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $dailyViews[$x], 'battleplan' ), $dailyViews[$x] )."</td></tr>";
	} 		
	echo '</table>';
}

// Add custom meta boxes to posts & pages
add_action("add_meta_boxes", "battleplan_add_custom_meta_boxes");
function battleplan_add_custom_meta_boxes() {
    add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", "page", "side", "default", null);
    add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", "post", "side", "default", null);
	add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", "products", "side", "default", null);	
	add_meta_box("page-stats-box", "Page Stats", "battleplan_page_stats", "testimonials", "side", "default", null);
}

// Set up Page Stats widget on posts & pages
function battleplan_page_stats() {
	global $post;
	$totalViews = number_format(readMeta($post->ID, "post-views-total-all"));
	$dailyMeta = readMeta($post->ID, 'post-views-day-1'); 
	$viewsToday = number_format($dailyMeta['views']);
	$last7Views = number_format(readMeta($post->ID, "post-views-total-7day"));
	$last30Views = number_format(readMeta($post->ID, "post-views-total-30day"));
	$recordViews = number_format(readMeta($post->ID, "post-views-record"));
	$lastViewed = readMeta($post->ID, 'post-views-time');
	$today = date("F j, Y, g:i a");	
	$current = strtotime($today);
	$dateDiff = (($current - $lastViewed) / 60 / 60 / 24); $howLong = "day";
	if ( $dateDiff < 1 ) : $dateDiff = (($current - $lastViewed) / 60 / 60); $howLong = "hour"; endif;	
	if ( $dateDiff < 1 ) : $dateDiff =(($current - $lastViewed) / 60); $howLong = "minute"; endif;
	if ( $dateDiff != 1 ) $howLong = $howLong."s";
	$dateDiff = number_format($dateDiff, 1);
	
	echo "<table><tr><td><b>Today</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $viewsToday, 'battleplan' ), $viewsToday )."</td></tr>";
	echo "<tr><td><b>Last 7 Days</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last7Views, 'battleplan' ), $last7Views )."</td></tr>";
	echo "<tr><td><b>Last 30 Days&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $last30Views, 'battleplan' ), $last30Views )."</tr>";
	echo "<tr><td><b>Total</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $totalViews, 'battleplan' ), $totalViews )."</tr>";
	echo "<tr><td><b>Daily Record</b></td><td>".sprintf( _n( '<b>%s</b> visit', '<b>%s</b> visits', $recordViews, 'battleplan' ), $recordViews )."</tr>";	
	echo "<tr><td><b>Last Viewed</b></td><td><b>".$dateDiff."</b> ".$howLong." ago</td></tr></table>";	
}

// Add "Remove Sidebar" checkbox to Page Attributes meta box
add_action( 'page_attributes_misc_attributes', 'battleplan_remove_sidebar_checkbox', 10, 1 );
function battleplan_remove_sidebar_checkbox($post) { 
	echo '<p class="post-attributes-label-wrapper">';
	$getRemoveSidebar = get_post_meta($post->ID, "_bp_remove_sidebar", true);

	if ( $getRemoveSidebar == "" ) : echo '<input name="remove_sidebar" type="checkbox" value="true">';
	else: echo '<input name="remove_sidebar" type="checkbox" value="true" checked>';
	endif;	
	
	echo '<label class="post-attributes-label" for="remove_sidebar">Remove Sidebar</label>';
} 
	 
add_action("save_post", "battleplan_save_remove_sidebar", 10, 3);
function battleplan_save_remove_sidebar($post_id, $post, $update) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
	if ( defined('DOING_AJAX') && DOING_AJAX ) return $post_id;
    if ( !current_user_can("edit_post", $post_id) ) return $post_id;

    $updateRemoveSidebar = "";
    if ( isset($_POST["remove_sidebar"]) ) $updateRemoveSidebar = $_POST["remove_sidebar"];   
    update_post_meta($post_id, "_bp_remove_sidebar", $updateRemoveSidebar);
}

add_filter( 'body_class', 'battleplan_add_class_to_body' );
function battleplan_add_class_to_body( array $classes ) {
	$checkRemoveSidebar = get_post_meta( get_the_ID(), '_bp_remove_sidebar', true );
	if ( $checkRemoveSidebar ) $classes[] = "remove-sidebar";
	return $classes;
}

/*--------------------------------------------------------------
# Custom Hooks
--------------------------------------------------------------*/
function bp_loader() { do_action('bp_loader'); }
function bp_font_loader() { do_action('bp_font_loader'); }
function bp_google_analytics() { do_action('bp_google_analytics'); }
function bp_mobile_menu_bar_items() { do_action('bp_mobile_menu_bar_items'); }


/*--------------------------------------------------------------
# AJAX Functions
--------------------------------------------------------------*/

// Log Page Load Speed
add_action( 'wp_ajax_log_page_load_speed', 'battleplan_log_page_load_speed_ajax' );
add_action( 'wp_ajax_nopriv_log_page_load_speed', 'battleplan_log_page_load_speed_ajax' );
function battleplan_log_page_load_speed_ajax() {
	$theID = intval( $_POST['id'] );
	$timezone = $_POST['timezone'];	
	$loadTime = $_POST['loadTime'];
	$deviceTime = $_POST['deviceTime'];
	$user = wp_get_current_user();
	$userLogin = $user->user_login;
		
	if ( ($userLogin != 'battleplanweb' && $timezone == get_option('timezone_string') ) || _BP_COUNT_ALL_VISITS == "true" ) :
		$siteHeader = getID('site-header');
		$desktopCounted = readMeta($siteHeader, "load-number-desktop");
		$desktopSpeed = readMeta($siteHeader, "load-speed-desktop");	
		$mobileCounted = readMeta($siteHeader, "load-number-mobile");
		$mobileSpeed = readMeta($siteHeader, "load-speed-mobile");		
		$lastEmail = readMeta($siteHeader, "last-email");
		$daysSinceEmail = (($current - $lastEmail) / 60 / 60 / 24);
		$totalCounted = $desktopCounted + $mobileCounted;	

		if ( ($totalCounted > 100 && $daysSinceEmail > 30) || $daysSinceEmail > 90 ) :
			$desktopCount = sprintf( _n( '%s visit', '%s visits', $desktopCounted, 'battleplan' ), $desktopCounted );
			$mobileCount = sprintf( _n( '%s visit', '%s visits', $mobileCounted, 'battleplan' ), $mobileCounted );
			$emailTo = "info@battleplanwebdesign.com";
			$emailFrom = "From: Website Administrator <do-not-reply@battleplanwebdesign.com>";
			$subject = $_SERVER['HTTP_HOST']." Speed Report";
			$content = $_SERVER['HTTP_HOST']." Speed Report\n\nDesktop = ".$desktopSpeed."s on ".$desktopCount."\nMobile = ".$mobileSpeed."s on ".$mobileCount."\n";	
			$desktopCounted = $desktopSpeed = $mobileCounted = $mobileSpeed = 0;
			updateMeta( $siteHeader, "last-email", $current );	
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

		updateMeta( $siteHeader, "load-number-desktop", $desktopCounted );	
		updateMeta( $siteHeader, "load-speed-desktop", $desktopSpeed );		
		updateMeta( $siteHeader, "load-number-mobile", $mobileCounted );	
		updateMeta( $siteHeader, "load-speed-mobile", $mobileSpeed );		
		$response = array( 'result' => ucfirst($deviceTime.' load speed = '.$loadTime.'s' ));
	else:
		$response = array( 'result' => ucfirst($deviceTime.' load speed not counted' ));
	endif;	
	wp_send_json( $response );	
}

// Count Post Views
add_action( 'wp_ajax_count_post_views', 'battleplan_count_post_views_ajax' );
add_action( 'wp_ajax_nopriv_count_post_views', 'battleplan_count_post_views_ajax' );
function battleplan_count_post_views_ajax() {
	$theID = intval( $_POST['id'] );
	$postType = get_post_type($theID);
	$timezone = $_POST['timezone'];	
	$lastViewed = readMeta($theID, 'post-views-time');
	$today = date("F j, Y");	
	$current = strtotime($today);
	$dateDiff = (($current - $lastViewed) / 60 / 60 / 24);
	$user = wp_get_current_user();
	$userLogin = $user->user_login;
	
	if ( ($userLogin != 'battleplanweb' && $timezone == get_option('timezone_string') ) || _BP_COUNT_ALL_VISITS == "true" ) :
		if ( $dateDiff != 0 ) : // day has passed, move 29 to 30, and so on	
			if ( $dateDiff > 30 ) $dateDiff = 30;
			for ($i = 1; $i <= $dateDiff; $i++) {	
				$viewsToday = $views7Day = $views30Day = 1;
				$dailyTime = $dailyViews = array();
				for ($x = 29; $x >= 1; $x--) {
					$dailyMeta = readMeta($theID, 'post-views-day-'.$x); 
					$dailyTime[$x] = $dailyMeta['date']; 				
					$dailyViews[$x] = intval($dailyMeta['views']); 
					$y = $x+1;
					updateMeta( $theID, 'post-views-day-'.$y, array ('date'=>$dailyTime[$x], 'views'=>$dailyViews[$x]));					
				} 	
				$figureTime = $current - ( ($dateDiff - $i) * 86400);	
				updateMeta( $theID, 'post-views-day-1', array ('date'=>date("F j, Y", $figureTime), 'views'=>0));
				for ($x = 1; $x < 7; $x++) { $views7Day = $views7Day + $dailyViews[$x]; } 					
				for ($x = 1; $x < 30; $x++) { $views30Day = $views30Day + $dailyViews[$x]; } 		
				$viewsTotal = intval(readMeta($theID, 'post-views-total-all')); $viewsTotal++;		
			}	
		else:
			// same day, just update today
			$dailyMeta = readMeta($theID, 'post-views-day-1'); 
			$viewsToday = intval($dailyMeta['views']); $viewsToday++; 
			$views7Day = intval(readMeta($theID, 'post-views-total-7day')); $views7Day++; 
			$views30Day = intval(readMeta($theID, 'post-views-total-30day')); $views30Day++;
			$viewsTotal = intval(readMeta($theID, 'post-views-total-all')); $viewsTotal++; 			
			$recordViews = intval(readMeta($theID, 'post-views-record'));
			if ( $viewsToday > $recordViews ) : updateMeta( $theID, 'post-views-record', $viewsToday); updateMeta( $theID, 'post-views-record-date', $current); endif;
		endif;	
		updateMeta($theID, 'post-views-time', $current);
		updateMeta( $theID, 'post-views-day-1', array ('date'=>date('F j, Y', $current), 'views'=>$viewsToday));			
		updateMeta( $theID, 'post-views-total-7day', $views7Day);			
		updateMeta( $theID, 'post-views-total-30day', $views30Day);			 
		updateMeta( $theID, 'post-views-total-all', $viewsTotal);	
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' VIEW counted: Total='.$viewsTotal.', Today='.$viewsToday.', Week='.$views7Day.', Month='.$views30Day) );
	else:
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' view NOT counted: user='.$userLogin.', user timezone='.$timezone.', site timezone='.get_option('timezone_string')) );
	endif;	
	wp_send_json( $response );	
}

// Count Teaser Views
add_action( 'wp_ajax_count_teaser_views', 'battleplan_count_teaser_views_ajax' );
add_action( 'wp_ajax_nopriv_count_teaser_views', 'battleplan_count_teaser_views_ajax' );
function battleplan_count_teaser_views_ajax() {
	$theID = intval( $_POST['id'] );
	$postType = get_post_type($theID);
	$timezone = $_POST['timezone'];			
	$lastTeased = date("F j, Y g:i a", readMeta($theID, 'post-tease-time'));
	$today = date("F j, Y  g:i a");	
	$current = strtotime($today);
	$user = wp_get_current_user();
	$userLogin = $user->user_login;
	
	if ( ($userLogin != 'battleplanweb' && $timezone == get_option('timezone_string') ) || _BP_COUNT_ALL_VISITS == "true" ) :
		updateMeta($theID, 'post-tease-time', $current);
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' TEASER counted: Prior tease = '.$lastTeased) );
	else:
		$response = array( 'result' => ucfirst($postType.' ID #'.$theID.' teaser NOT counted: user='.$userLogin.', user timezone='.$timezone.', site timezone='.get_option('timezone_string')) );
	endif;	
	wp_send_json( $response );	
}

// Send email to self when website fails
add_action( 'wp_ajax_sendServerEmail', 'battleplan_sendServerEmail_ajax' );
add_action( 'wp_ajax_nopriv_sendServerEmail', 'battleplan_sendServerEmail_ajax' );
function battleplan_sendServerEmail_ajax() {		
	$emailTo = "info@battleplanwebdesign.com";
	$emailFrom = "From: Website Administrator <do-not-reply@battleplanwebdesign.com>";
	$subject = $_POST['theSite']." needs attention!";
	$content = $_POST['theSite']." failed at ".$_POST['failCheck'];	
	mail($emailTo, $subject, $content, $emailFrom);
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

// Section
add_shortcode( 'section', 'battleplan_buildSection' );
function battleplan_buildSection( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'', 'width'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'class'=>'', 'start'=>'', 'end'=>'' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$width = esc_attr($a['width']);
	if ( $width != '' ) $width = " section-".$width;
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
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
	
	$buildSection = '<section'.$name.' class="section'.$style.$width.$class.'"';
	if ( $background != "" ) $buildSection .= ' style="background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;"';	
	$buildSection .= '>'.do_shortcode($content).'</section>';	
	
	return $buildSection;
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
	$a = shortcode_atts( array( 'class'=>'', 'align'=>'', 'valign'=>'', 'background'=>'', 'left'=>'50', 'top'=>'50', 'start'=>'', 'end'=>'' ), $atts );
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

	$buildCol = '<div class="col '.$class.$align.$valign.'"><div class="col-inner"';
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
	if ( $hidden == "true" ) $hidden = " aria-hidden='true' tabindex='-1'";
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

	return '<div class="block block-video span-'.$size.$class.'" style="'.$style.' padding-top:'.$height.'%"><iframe src="" data-src="'.$link.'" allowfullscreen></iframe></div>';
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
add_shortcode( 'text', 'battleplan_buildText' );
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
	$a = shortcode_atts( array( 'size'=>'100', 'align'=>'center', 'order'=>'', 'link'=>'', 'get-biz'=>'', 'new-tab'=>'', 'class'=>'', 'icon'=>'false', 'ada'=>'', 'start'=>'', 'end'=>'' ), $atts );
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
	$icon = esc_attr($a['icon']);	
	if ( $icon == "true" ) $icon = "fas fa-chevron-right";	
	if ( $icon != "false" ) : $class .= " fancy"; $content = '<span class="fancy-text">'.$content.'</span><span class="fancy-icon"><i class="'.$icon.'"></i></span>'; endif;
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}

	return '<div class="block block-button span-'.$size.$class.$align.'"'.$style.'><a'.$target.' href="'.$link.'" class="button'.$class.'">'.$content.$ada.'</a></div>';
}

/* Accordion Block */
add_shortcode( 'accordion', 'battleplan_buildAccordion' );
function battleplan_buildAccordion( $atts, $content = null ) {
	$a = shortcode_atts( array( 'title'=>'', 'excerpt'=>'', 'class'=>'', 'icon'=>'true', 'start'=>'', 'end'=>'' ), $atts );
	$excerpt = esc_attr($a['excerpt']);
	if ( $excerpt != '' ) $excerpt = '<div class="accordion-excerpt"><div class="accordion-box">'.$excerpt.'</div></div>';
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
	$icon = esc_attr($a['icon']);
	if ( $icon == 'true' ) $icon = '<span class="accordion-icon"></span>';
	$title = esc_attr($a['title']);	
	if ( $title ) $title = '<h2 role="button" tabindex="0" class="accordion-title">'.$icon.$title.'</h2>';
	$start = strtotime(esc_attr($a['start']));
	$end = strtotime(esc_attr($a['end']));	
	if ( $start || $end ) {
		$now = time(); 
		if ( $start && $now < $start ) return null;
		if ( $end && $now > $end ) return null;		
	}

	return '<div class="block block-accordion'.$class.'">'.$title.$excerpt.'<div class="accordion-content"><div class="accordion-box">'.do_shortcode($content).'</div></div></div>';	
}

/* Parallax Section */
add_shortcode( 'parallax', 'battleplan_buildParallax' );
function battleplan_buildParallax( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'', 'type'=>'section', 'size'=>'100', 'width'=>'edge', 'img-w'=>'2000', 'img-h'=>'1333', 'height'=>'800', 'pos-x'=>'center', 'pos-y'=>'top', 'bleed'=>'10', 'speed'=>'0.7', 'image'=>'', 'class'=>'', 'scroll-btn'=>'false', 'scroll-loc'=>'#page', 'scroll-icon'=>'fa-chevron-down' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$style = esc_attr($a['style']);
	if ( $style != '' ) $style = " style-".$style;
	$type = esc_attr($a['type']);
	$size = esc_attr($a['size']);
	$size = convertSize($size);
	$width = esc_attr($a['width']); 
	$imgW = esc_attr($a['img-w']);
	$imgH = esc_attr($a['img-h']);
	$height = esc_attr($a['height']);
	if ( $height == "full" ) : $height = "100vh"; elseif ( $height != "auto" ) : $height = $height."px"; endif;
	$posX = esc_attr($a['pos-x']);
	$posY = esc_attr($a['pos-y']);
	$bleed = esc_attr($a['bleed']);
	$speed = esc_attr($a['speed']);
	$image = esc_attr($a['image']);	
	$class = esc_attr($a['class']); 
	if ( $class != '' ) $class = " ".$class;
	$scrollBtn = esc_attr($a['scroll-btn']); 
	$scrollLoc = esc_attr($a['scroll-loc']); 
	$scrollIcon = esc_attr($a['scroll-icon']); 
	if ( $scrollBtn != "false" ) $buildScrollBtn = '<div class="scroll-down"><a href="'.$scrollLoc.'"><i class="fas '.$scrollIcon.' aria-hidden="true"></i><span class="sr-only">Scroll Down</span></a></div>';
	if ( !$name ) $name = "section-".rand(10000,99999);
	
	if ( $type == "section" ) :
		return do_shortcode('<section id="'.$name.'" class="section'.$style.' section-'.$width.' section-parallax'.$class.'" style="height:'.$height.'" data-parallax="scroll" data-natural-width="'.$imgW.'" data-natural-height="'.$imgH.'" data-position-x="'.$posX.'" data-position-y="'.$posY.'" data-z-index="1" data-bleed="'.$bleed.'" data-speed="'.$speed.'" data-ios-fix="true" data-android-fix="true" data-image-src="'.$image.'">'.$content.$buildScrollBtn.'</section>');	
	elseif ( $type == "col" ) :
		return do_shortcode('<div id="'.$name.'" class="col col-parallax'.$class.' '.$posX.'" style="height:'.$imgH.'px" data-parallax="scroll" data-natural-width="'.$imgW.'" data-natural-height="'.$imgH.'" data-position-x="'.$posX.'" data-position-y="'.$posY.'" data-z-index="1" data-bleed="'.$bleed.'" data-speed="'.$speed.'" data-ios-fix="true" data-android-fix="true" data-image-src="'.$image.'">'.$content.'</div>');	
	endif;
}

/* Locked Section */
add_shortcode( 'lock', 'battleplan_buildLockedSection' );
function battleplan_buildLockedSection( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'lock', 'width'=>'edge', 'position'=>'bottom', 'delay'=>'3000', 'show'=>'session', 'background'=>'', 'left'=>'50', 'top'=>'50', 'class'=>'', 'start'=>'', 'end'=>'' ), $atts );
	$name = strtolower(esc_attr($a['name']));
	$name = preg_replace("/[\s_]/", "-", $name);
	$pos = esc_attr($a['position']);
	$delay = esc_attr($a['delay']);
	$show = esc_attr($a['show']); 
	$background = esc_attr($a['background']);
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);
	$width = esc_attr($a['width']);
	if ( $width != '' ) $width = " section-".$width;
	$class = esc_attr($a['class']);
	if ( $class != '' ) $class = " ".$class;
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
	
	$buildSection = '<section'.$name.' class="section section-lock'.$style.$width.$class.'" data-pos="'.$pos.'" data-delay="'.$delay.'" data-show="'.$show.'"';
	if ( $background != "" ) $buildSection .= ' style="background: url('.$background.') '.$left.'% '.$top.'% no-repeat; background-size:cover;"';	
	$buildSection .= '>'.do_shortcode($content).'</section>';	
	
	return $buildSection;
}
 
/* Social Media Buttons */
add_shortcode( 'social-btn', 'battleplan_socialBtn' );
function battleplan_socialBtn( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', 'img'=>'' ), $atts );
	$type = $icon = esc_attr($a['type']);
	$link = do_shortcode('[get-biz info="'.$type.'"]');
	$prefix = "";
	$img = esc_attr($a['img']);
	$alt = "Visit us on ".$type;
			
	if ( $type == "email" ) : $prefix = "mailto:"; $icon = "fas fa-envelope"; $alt="Email us";	
	elseif ( $type == "facebook" ) : $icon = "fab fa-facebook-f";	
	elseif ( $type == "pinterest" ) : $icon = "fab fa-pinterest-p";	
	elseif ( $type == "linkedin" ) : $icon = "fab fa-linkedin-in";
	else: $icon = "fab fa-".$type; endif;
	
	if ( $img == '' ) : $iconLoc = '<i class="'.$icon.'" aria-hidden="true"></i><span class="sr-only">'.$type.'</span><span class="social-bg"></span>';
	else: $iconLoc = '<img src = "'.$img.'" alt="'.$alt.'"/>'; endif;

	return '<a class="social-button" href="'.$prefix.$link.'" target="_blank" rel="noopener noreferrer">'.$iconLoc.'</a>';	
}
?>