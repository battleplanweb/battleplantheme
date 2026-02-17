<?php
/* Battle Plan Web Design Functions: Shortcodes

/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

// Returns Business Information for site-wide changes
add_shortcode( 'get-biz', 'battleplan_getBizInfo' );
function battleplan_getBizInfo($atts, $content = null ) {
	$customer_info = customer_info();
	$a = shortcode_atts( array( 'info' => 'name', 'icon' => '', 'left'=>'0', 'top'=>'0' ), $atts );
	$data = esc_attr($a['info']);
	$icon = esc_attr($a['icon']) != '' ? do_shortcode('[get-icon type="'.esc_attr($a['icon']).'" class="biz-info" top="'.esc_attr($a['top']).'" left="'.esc_attr($a['left']).'"]').' ' : '';
	$left = esc_attr($a['left']);
	$top = esc_attr($a['top']);

	if ( $data == "area" ) return $icon.$customer_info['area-before'].esc_html($customer_info['area']).esc_html($customer_info['area-after']);

	if ( strpos($data, 'phone') !== false ) :
		$phoneBasic = strpos($data, 'replace') !== false ? $customer_info[$data] : $customer_info['area'].'-'.$customer_info['phone'];
		$phoneFormat = $customer_info['area-before'].esc_html($customer_info['area']).esc_html($customer_info['area-after']).esc_html($customer_info['phone'])	;

		if ( strpos($data, 'mm-bar-phone') !== false ) :
			$openMessage = is_biz_open() ? "<span>Call Us, We're Open!</span>" : "";
			$phoneFormat = do_shortcode('<div class="mm-bar-btn mm-bar-phone call-btn" aria-hidden="true">[get-icon type="phone"]'.$openMessage.'</div><span class="sr-only">Call Us</span>');
		elseif ( strpos($data, 'alt') !== false ) :
			if ( isset($customer_info[$data]) ) :
				$phoneFormat = $customer_info[$data];
				$phoneBasic = str_replace(array('(', ')', '-', '.', ' '), '', $phoneFormat);
			endif;
		endif;
		if ( $customer_info['phone'] === '' ) return;
		if ( strpos($data, '-notrack') !== false ):
			return '<a class="phone-link" href="tel:1-'.$phoneBasic.'">'.$icon.$phoneFormat.'</a>';
		else:
			return '<a href="#" class="phone-link track-clicks" data-action="phone call" data-url="tel:1-'.$phoneBasic.'">'.$icon.$phoneFormat.'</a>';
		endif;
	endif;

	if ( isset($customer_info[$data]) ) return $icon.esc_html($customer_info[$data]);
}

// Use Google ratings in content
add_shortcode( 'get-google-rating', 'battleplan_displayGoogleRating' );
function battleplan_displayGoogleRating($atts, $content = null) {
	$a = shortcode_atts( array( 'detail'=>'rating', ), $atts );
 	$googleInfo = get_option('bp_gbp_update');
	return esc_attr($a['detail']) == 'rating' ? number_format($googleInfo['google-rating']?? 0.0, 1, '.', ',') : $googleInfo['google-reviews'];
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

// Retrieves and displays a Facebook post
add_shortcode( 'get-fb-posts', 'battleplan_getFBPosts' );
function battleplan_getFBPosts($atts, $content = null ) {
	$a = shortcode_atts( array( 'prefix'=>'', 'links'=>'', 'width'=>'', 'lazy'=>'false', 'text'=>'true', 'max'=>1, 'shuffle'=>'true' ), $atts );
	$prefix = esc_attr($a['prefix']);
	$links = explode( ',', str_replace(' ', '', esc_attr($a['links'])) );
	$width = esc_attr($a['width']);
	$lazy = esc_attr($a['lazy']);
	$text = esc_attr($a['text']);
	$max = esc_attr($a['max']);
	$shuffle = esc_attr($a['shuffle']);

	if ( $shuffle == 'true' ) shuffle($links);
	if ( $max > 1 ) $links = array_slice($links, 0, $max);

	$display = '[col class="span-all hide-desktop hide-mobile"]<script defer nonce="'._BP_NONCE.'">window.fbAsyncInit = function() { FB.init({ xfbml : true, version : "v18.0" }); }; </script>';
	$display .= '<script async defer nonce="'._BP_NONCE.'" src="https://connect.facebook.net/en_US/sdk.js"></script>[/col]';

	foreach ( $links as $link ) :
		$display .= '[col]<div style="background: #fff;" class="fb-post" data-href="'.esc_url($prefix.$link).'" data-width="'.$width.'" data-lazy="'.$lazy.'" data-show-text="'.$text.'"></div>[/col]';
	endforeach;

	return do_shortcode($display);
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

	if ( esc_attr($a['id']) != '' ) :
		$pageID = esc_attr($a['id']);
	elseif ( $slug != '' ) :
		$pageID = getID($slug, $type);
	elseif ( $title != '' ) :
		$pageID = getID($title, $type);
	endif;

	$getPage = get_post($pageID);
	if ( empty($pageID) ) return '';

	if ( $display == "content" && get_post_status($getPage->ID) == "publish" ) return apply_filters('the_content', $getPage->post_content);
	if ( $display == "title" && get_post_status($getPage->ID) == "publish" ) return esc_html( get_the_title($pageID));
	if ( $display == "excerpt" && get_post_status($getPage->ID) == "publish" ) return apply_filters('the_excerpt', $getPage->post_excerpt);
	if ( $display == "thumbnail" && get_post_status($getPage->ID) == "publish" ) return get_the_post_thumbnail($pageID, 'thumbnail');
	if ( $display == "link" && get_post_status($getPage->ID) == "publish" ) return esc_url(get_permalink($pageID));
}

// Display the page or post's featured image
add_shortcode( 'get-featured-image', 'battleplan_getFeaturedImg' );
function battleplan_getFeaturedImg( $atts, $content = null ) {
	$a = shortcode_atts( array( 'size'=>'full' ), $atts );
	global $post;
    	$featured_image = '';
    	if (has_post_thumbnail($post->ID)) return get_the_post_thumbnail($post->ID, esc_attr($a['size']));
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
	return esc_attr($a['link']) == "false" ? get_site_url() : '<a href="'.get_site_url().'">'.get_site_url().'</a>';
}

// Returns website address (minus https and with/without .com)
add_shortcode( 'get-domain-name', 'battleplan_getDomainName' );
function battleplan_getDomainName( $atts, $content = null ) {
	$a = shortcode_atts( array( 'ext'=>'false', ), $atts );

	$host = parse_url(get_site_url(), PHP_URL_HOST);
	if (!$host) return '';

	$parts = explode('.', $host);
	$count = count($parts);

	// domain without extension
	$domain = $parts[$count - 2] ?? $host;

	// domain with extension
	$domain_ext = ($count >= 2)
		? $parts[$count - 2] . '.' . $parts[$count - 1]
		: $host;

	return ($a['ext'] === 'true')
		? esc_html($domain_ext)
		: esc_html($domain);
}

// Returns url of page (minus domain, choose whether to include variables)
add_shortcode( 'get-url', 'battleplan_getURL' );
function battleplan_getURL( $atts, $content = null ) {
	$a = shortcode_atts( array( 'var'=>'true', ), $atts );
	return esc_attr($a['var']) == "false" ? parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) : esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
}

// Returns url variable
add_shortcode( 'get-url-var', 'battleplan_getURLVar' );
function battleplan_getURLVar($atts, $content = null) {
	$a = shortcode_atts( array( 'var'=>'', ), $atts );
	$var = esc_attr($a['var']);
	$getVars = array();
	foreach( $_GET as $key => $value ) $getVars[$key] = $value;

	if ( isset($getVars[$var]) ) return esc_html($getVars[$var]);
}

// Show count of posts, images, etc.
add_shortcode( 'get-count', 'battleplan_getCount' );
function battleplan_getCount($atts, $content = null) {
	$a = shortcode_atts( array( 'type'=>'post', 'status'=>'publish', 'tax'=>'', 'term'=>'',  ), $atts );
	$postType = esc_attr($a['type']);
	$postStatus = esc_attr($a['status']);
	$exclude = [];

	$query = bp_WP_Query($postType, [
		'post_status'     => $postStatus,
		'posts_per_page'  => -1,
		'offset'          => esc_attr($a['offset']),
		'start'           => esc_attr($a['start']),
		'end'             => esc_attr($a['end']),
		'order'           => ($postType == 'testimonials') ? 'desc' : esc_attr($a['order']),
		'orderby'         => ($postType == 'testimonials') ? 'post_date' : esc_attr($a['order_by']),
		'post__not_in'    => $exclude,
		'tax'             => esc_attr($a['tax']),
		'term'            => esc_attr($a['term'])
	]);

	return number_format((int)$query->post_count?? 0.0);
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
	if ( $facebook == "true" ) $output .= '<a tooltip="Click to share on Facebook" target="_blank" href="'.esc_url($facebookLink).'" class="share-button facebook">[get-icon type="facebook" sr="Share on Facebook"]</a>';
	if ( $twitter == "true" ) $output .= '<a tooltip="Click to share on Twitter" target="_blank" href="'.esc_url($twitterLink).'" class="share-button twitter">[get-icon type="twitter" sr="Share on Twitter"]</a>';
	if ( $pinterest == "true" ) $output .= '<a tooltip="Click to share on Pinterest" target="_blank" href="'.esc_url($pinterestLink).'" class="share-button pinterest">[get-icon type="pinterest" sr="Share on Pinterest"]</a>';
    $output .= '</div><!-- .social-share-buttons -->';

    return $output;
};

add_shortcode( 'social-btn', 'battleplan_socialBtn' );
function battleplan_socialBtn( $atts, $content = null ) {
	$a = shortcode_atts( array( 'type'=>'', 'img'=>'', 'link'=>'' ), $atts );
	$type = esc_attr($a['type']);
	$link = esc_attr($a['link']) === '' ? do_shortcode('[get-biz info="'.$type.'"]') : esc_attr($a['link']);
	$prefix = $type === "email" ? "mailto:" : "";
	$alt = $type === "email" ? "Email us" : "Visit us on ".$type;
	if ( esc_attr($a['img']) === '' ) :
		return do_shortcode('[get-icon type="'.$type.'" class="social-btn '.$type.'-btn" link="'.$prefix.$link.'" new-tab="yes"]');
	else:
		return '<a class="social-btn '.$type.'-btn" href="'.esc_url($prefix.$link).'" target="_blank" rel="noopener noreferrer"><img loading="lazy" src = "'.esc_attr($a['img']).'" alt="'.$alt.'"/</a>';
	endif;
}

// Display Business Hours
add_shortcode( 'get-hours', 'battleplan_addBusinessHours' );
function battleplan_addBusinessHours( $atts, $content = null ) {
	bp_inline_minified_css( get_template_directory() . '/style-hours.css' );

	$a = shortcode_atts( array( 'direction'=>'vert', 'start'=>'sun', 'abbr'=>'true', 'wkday1'=>'', 'wkday2'=>'', 'mon1'=>'', 'mon2'=>'', 'tue1'=>'', 'tue2'=>'', 'wed1'=>'', 'wed2'=>'', 'thu1'=>'', 'thu2'=>'', 'fri1'=>'', 'fri2'=>'', 'sat1'=>'', 'sat2'=>'', 'sun1'=>'', 'sun2'=>'',  ), $atts );
	$direction = esc_attr($a['direction']) == "vert" ? "vert" : "horz";
	$days = esc_attr($a['wkday1']) !== "" ? array('weekdays', 'saturday') : array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
	if ( esc_attr($a['start']) == "sun" || esc_attr($a['start']) == "sunday" ) :
		array_unshift($days, 'sunday');
	else:
		array_push($days, 'sunday');
	endif;

	if ( esc_attr($a['mon1']) === "" && esc_attr($a['wkday1']) === "" ) :
		$customer_info = customer_info();
		$weekdayDescriptions = $customer_info['current-hours'] ?? [];
		$stripLabel = fn($str) => trim(substr($str, strpos($str, ':') + 1));

		$getHours = [
			'monday' => $stripLabel($weekdayDescriptions[0] ?? ''),
			'tuesday' => $stripLabel($weekdayDescriptions[1] ?? ''),
			'wednesday' => $stripLabel($weekdayDescriptions[2] ?? ''),
			'thursday' => $stripLabel($weekdayDescriptions[3] ?? ''),
			'friday' => $stripLabel($weekdayDescriptions[4] ?? ''),
			'saturday' => $stripLabel($weekdayDescriptions[5] ?? ''),
			'sunday' => $stripLabel($weekdayDescriptions[6] ?? '')
		];
	else:
		if ( esc_attr($a['wkday1']) !== "" ) :
			$getHours = array( 'weekdays'=>esc_attr($a['wkday1']), 'saturday'=>esc_attr($a['sat1']), 'sunday'=>esc_attr($a['sun1']) );
			$getHours2 = array( 'weekdays'=>esc_attr($a['wkday2']), 'saturday'=>esc_attr($a['sat2']), 'sunday'=>esc_attr($a['sun2']) );
	else:
			$getHours = array( 'monday'=>esc_attr($a['mon1']), 'tuesday'=>esc_attr($a['tue1']), 'wednesday'=>esc_attr($a['wed1']), 'thursday'=>esc_attr($a['thu1']), 'friday'=>esc_attr($a['fri1']), 'saturday'=>esc_attr($a['sat1']), 'sunday'=>esc_attr($a['sun1']) );
			$getHours2 = array( 'monday'=>esc_attr($a['mon2']), 'tuesday'=>esc_attr($a['tue2']), 'wednesday'=>esc_attr($a['wed2']), 'thursday'=>esc_attr($a['thu2']), 'friday'=>esc_attr($a['fri2']), 'saturday'=>esc_attr($a['sat2']), 'sunday'=>esc_attr($a['sun2']) );
		endif;
	endif;

	$buildHours = '<div class="office-hours '.$direction.'">';

	foreach ( $days as $day ) :

		$removeDay  = strpos( $getHours[$day],  ': ' );
		$removeDay2 = strpos( $getHours2[$day], ': ' );

		$hours  = $removeDay  !== false ? substr( $getHours[$day],  $removeDay  + 2 ) : $getHours[$day];
		$hours2 = $removeDay2 !== false ? substr( $getHours2[$day], $removeDay2 + 2 ) : $getHours2[$day];

		if ( esc_attr( $a['wkday1'] ) !== '' && $day === 'weekdays' ) :
			$buildHours .= '<div class="row row-mon row-tue row-wed row-thu row-fri">';
			$printDay = esc_attr( $a['abbr'] ) === 'true' ? 'Mon-Fri' : 'Monday - Friday';
		else :
			$buildHours .= '<div class="row row-' . substr( $day, 0, 3 ) . '">';
			$printDay = esc_attr( $a['abbr'] ) === 'true' ? substr( $day, 0, 3 ) : $day;
		endif;

		$buildHours .= ( $hours && $hours2 )
			? '<div class="col-day row-2">' . esc_html( $printDay ) . '</div>'
			: '<div class="col-day row-1">' . esc_html( $printDay ) . '</div>';

		if ( $hours2 ) :
			if ( $hours ) :
				$buildHours .= '<div class="col-morning col-first">' . esc_html( $hours ) . '</div>';
				$buildHours .= '<div class="col-afternoon col-second">' . esc_html( $hours2 ) . '</div>';
			else :
				$buildHours .= '<div class="col-all col-second">' . esc_html( $hours2 ) . '</div>';
			endif;
		else :
			$buildHours .= '<div class="col-all col-first">' . esc_html( $hours ) . '</div>';
		endif;

		$buildHours .= '</div>';

	endforeach;


	$buildHours .= '</div>';

	return do_shortcode($buildHours);
}

// Print text based on whether business is open or not
add_shortcode('get-service-areas', 'battleplan_get_service_areas');
function battleplan_get_service_areas() {

	$customer_info = customer_info();

	  // ----------------------------------------
	  // 1. Load all taxonomy cities
	  // ----------------------------------------
	$city_terms = get_terms([
		'taxonomy'   => 'jobsite_geo-service-areas',
		'hide_empty' => false
	]);

	if (is_wp_error($city_terms)) return '';

	$cities = [];  // city-slug => [display, slug]

	foreach ($city_terms as $city_term) {

		$citySlug = $city_term->slug;  // friendswood-tx

		  // Convert city slug to readable format
		$parts = explode('-', $citySlug);
		$state = strtoupper(array_pop($parts));               // TX
		$city  = implode(' ', array_map('ucwords', $parts));  // Friendswood

		$displayCity = "$city, $state";

		$cities[$citySlug] = [
			'display'      => $displayCity,
			'has_services' => true
		];
	}

	  // ----------------------------------------
	  // 2. Insert customer_info service-areas
	  // ----------------------------------------
	$customer_info['service-areas'] = $customer_info['service-areas'] ?? [];

	foreach ($customer_info['service-areas'] as $cityArray) {

		$cityName  = $cityArray[0] ?? '';
		$stateName = strtolower($cityArray[1] ?? '');

		if (!$cityName || !$stateName) continue;

		  // Convert state name to abbreviation
		$states = [
			'alabama'              => 'AL', 'arizona'     => 'AZ', 'arkansas'     => 'AR', 'california'     => 'CA', 'colorado'       => 'CO', 'connecticut'   => 'CT', 'delaware'      => 'DE',
			'district of columbia' => 'DC', 'florida'     => 'FL', 'georgia'      => 'GA', 'idaho'          => 'ID', 'illinois'       => 'IL', 'indiana'       => 'IN', 'iowa'          => 'IA',
			'kansas'               => 'KS', 'kentucky'    => 'KY', 'louisiana'    => 'LA', 'maine'          => 'ME', 'maryland'       => 'MD', 'massachusetts' => 'MA', 'michigan'      => 'MI',
			'minnesota'            => 'MN', 'mississippi' => 'MS', 'missouri'     => 'MO', 'montana'        => 'MT', 'nebraska'       => 'NE', 'nevada'        => 'NV', 'new hampshire' => 'NH',
			'new jersey'           => 'NJ', 'new mexico'  => 'NM', 'new york'     => 'NY', 'north carolina' => 'NC', 'north dakota'   => 'ND', 'ohio'          => 'OH',
			'oklahoma'             => 'OK', 'oregon'      => 'OR', 'pennsylvania' => 'PA', 'rhode island'   => 'RI', 'south carolina' => 'SC', 'south dakota'  => 'SD',
			'tennessee'            => 'TN', 'texas'       => 'TX', 'utah'         => 'UT', 'vermont'        => 'VT', 'virginia'       => 'VA', 'washington'    => 'WA', 'west virginia' => 'WV',
			'wisconsin'            => 'WI', 'wyoming'     => 'WY'
		];

		if (!isset($states[$stateName])) continue;

		$abbr = $states[$stateName];

		$citySlug    = sanitize_title("$cityName $abbr");              // e.g. allen-tx
		$displayCity = ucwords($cityName) . ", " . strtoupper($abbr);

		  // Add ONLY if not already in taxonomy
		if (!isset($cities[$citySlug])) {
			$cities[$citySlug] = [
				'display'      => $displayCity,
				'has_services' => false
			];
		}
	}

	  // ----------------------------------------
	  // 3. Sort alphabetically by display
	  // ----------------------------------------
	uasort($cities, function($a, $b) {
		return strcmp($a['display'], $b['display']);
	});

	// ----------------------------------------
	// 4. Build services list from taxonomy jobsite_geo-services
	// ----------------------------------------
	$service_terms = get_terms([
		'taxonomy'   => 'jobsite_geo-services',
		'hide_empty' => false
	]);

	// ----------------------------------------
	// 5. Build final HTML
	// ----------------------------------------
	$buildLinks = '';

	foreach ($cities as $citySlug => $cityData) {

		$buildLinks .= "<li>{$cityData['display']}";

		if ($cityData['has_services']) {

			$buildLinks .= "<ul>";

			foreach ($service_terms as $service_term) {

				$slug = $service_term->slug;

				// Match ending with this city slug
				if (!str_ends_with($slug, $citySlug)) continue;

				// Extract service part
				$service_part = substr($slug, 0, strlen($slug) - strlen($citySlug) - 1);
				$service_label = ucwords(str_replace('-', ' ', $service_part));
				$url = "/service/$slug";

				$buildLinks .= '<li><a href="' . esc_url($url) . '">' .
				                esc_html($service_label) .
				                '</a></li>';
			}

			$buildLinks .= "</ul>";
		}

		$buildLinks .= "</li>";
	}

	// ----------------------------------------
	// Keep rest of your output the same
	// ----------------------------------------
	$columns = ( count($cities) < 21 ) ? 'two-col' : 'three-col';

	$buildLinks .= '<li>Surrounding Areas</li>';

	$serviceType = $customer_info['service-type'] ?? [];
	$type1       = $serviceType[0] ?? 'HVAC solutions';
	$type2       = $serviceType[1] ?? 'expert installations to routine maintenance and emergency repairs';

	$buildPage  = "<h1>Areas We Serve</h1>";

	$buildPage .= "<p>At <b>".esc_html($customer_info['name'])."</b>, we take pride in providing dependable ".$type1." to homeowners and businesses across our service region. From ".$type2.", our experienced technicians deliver quality workmanship and trusted expertise wherever you are.</p><p>We're continually expanding to meet the needs of nearby communities, ensuring every customer receives the same level of professional care. Explore the list below to find your community and contact us to schedule fast, local service today.</p>";

	$buildPage  .= '<ul class="'.$columns.' areas-we-serve">'.$buildLinks.'</ul>';

	return $buildPage;
}



  // Choose random text from given choices
add_shortcode( 'get-random-text', 'battleplan_getRandomText' );
function battleplan_getRandomText($atts, $content = null) {
	$a         = shortcode_atts( array( 'cookie'=>'true', 'text1'=>'', 'text2'=>'', 'text3'=>'', 'text4'=>'', 'text5'=>'', 'text6'=>'', 'text7'=>'',  ), $atts );
	$textArray = array_filter( array(
		wp_kses_post($a['text1']),
		wp_kses_post($a['text2']),
		wp_kses_post($a['text3']),
		wp_kses_post($a['text4']),
		wp_kses_post($a['text5']),
		wp_kses_post($a['text6']),
		wp_kses_post($a['text7'])
	) );

	if ( empty($textArray) ) return '';
	$textArray = array_values($textArray);
	$num       = count($textArray) - 1;
	$cookie 	= esc_attr($a['cookie']);
	$rand_cookie = isset($_COOKIE['random-text']) ? (int) $_COOKIE['random-text'] : null;
	$rand = ($cookie !== 'false' && $rand_cookie !== null && $rand_cookie <= $num) ? $rand_cookie : rand(0, $num);

	$printText = $textArray[$rand];

	$next = ($rand >= $num) ? 0 : $rand + 1;

	if ( $cookie !== 'false' ) {
		setcookie('random-text', $next, time() + WEEK_IN_SECONDS, '/', '', true, false);
	}

	return $printText;
}





  // Display a random photo from tagged images
add_shortcode( 'get-random-image', 'battleplan_getRandomImage' );
function battleplan_getRandomImage($atts, $content = null ) {
	$a       = shortcode_atts( array( 'id'=>'', 'tag'=>'', 'size'=>'thumbnail', 'col'=>'1', 'row'=>'1', 'gap'=>'10', 'number'=>'', 'link'=>'no', 'offset'=>'0', 'align'=>'left', 'class'=>'', 'order_by'=>'rand', 'order'=>'asc', 'shuffle'=>'no', 'lazy'=>'true' ), $atts );
	$tag     = esc_attr($a['tag']);
	$tags    = $tag                  == "page-slug" ? _PAGE_SLUG : explode( ',', $tag );
	$size    = esc_attr($a['size']);
	$number  = esc_attr($a['number']);

	if ($number !== '') {
		$col = '';
		$row = '';
		$num = $number;
	} else {
		$col  = esc_attr($a['col']);
		$row  = esc_attr($a['row']);
		$num = $col * $row;
	}
	$gap    = esc_attr($a['gap']);

	$buildGrid = $col !== '' ? ' style="display:grid; grid-template-columns:repeat(' . $col . ', 1fr); gap:'.$gap.'px"' : '';

	$link    = esc_attr($a['link']);
	$lazy    = esc_attr($a['lazy'])  == "true" ? "lazy" : "eager";
	$id      = esc_attr($a['id'])    == "current" ? get_the_ID() : esc_attr($a['id']);
	$class   = esc_attr($a['class']) != '' ? " ".esc_attr($a['class']) : "";
	$align   = esc_attr($a['align']) != '' ? "align".esc_attr($a['align']) : "";
	$exclude = $GLOBALS['do_not_repeat'];

	$query = bp_WP_Query('attachment', [
		'post_status'    => 'any',
		'mime_type'      => 'image/jpeg,image/gif,image/jpg,image/png,image/webp',
		'posts_per_page' => $num,
		'offset'         => esc_attr($a['offset']),
		'post__not_in'   => $exclude,
		'order'          => esc_attr($a['order']),
		'orderby'        => esc_attr($a['order_by']),
		'post_parent'     => ($id != '') ? $id : null,
		'taxonomy'        => ($id == '' || $tag != '') ? 'image-tags' : null,
		'terms'           => ($id == '' || $tag != '') ? $tags : null
	]);
	$imageArray = array();

	if( $query->have_posts() ) : while ($query->have_posts() ) : $query->the_post();
		$getID = get_the_ID();
		$full = wp_get_attachment_image_src($getID, 'full');
		$image = wp_get_attachment_image_src($getID, $size);
		$imgSet = wp_get_attachment_image_srcset($getID, $size );

		$buildImage = "";
		if ( $link == "yes" ) $buildImage .= '<a href="'.esc_url($full[0]).'">';
		$buildImage .= '<img class="wp-image-'.$getID.' random-img '.$tags[0].'-img '.$align.' size-'.$size.$class.'" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';
		if ( $link == "yes" ) $buildImage .= '</a>';
		$imageArray[] = $buildImage;

		battleplan_countTease( $getID );
		array_push( $GLOBALS['do_not_repeat'], get_the_ID() );
	endwhile; wp_reset_postdata(); endif;
	if ( esc_attr($a['shuffle']) != "no" ) shuffle($imageArray);
	return '<div '.$buildGrid.'>'.printArray($imageArray).'</div>';
}

// Display a row of square pics from tagged images
add_shortcode( 'get-row-of-pics', 'battleplan_getRowOfPics' );
function battleplan_getRowOfPics($atts, $content = null ) {
	$a = shortcode_atts( array( 'id'=>'', 'tag'=>'row-of-pics', 'link'=>'no', 'col'=>'4', 'row'=>'1', 'offset'=>'0', 'size'=>'half-s', 'valign'=>'center', 'class'=>'', 'order_by'=>'rand', 'order'=>'asc', 'shuffle'=>'no', 'lazy'=>'true' ), $atts );
	$col = esc_attr($a['col']);
	$row = esc_attr($a['row']);
	$size = esc_attr($a['size']);
	$num = $row * $col;
	$tags = explode( ',', esc_attr($a['tag']) );
	$link = esc_attr($a['link']);
	$shuffle = esc_attr($a['shuffle']);
	$lazy = esc_attr($a['lazy']) == "true" ? "lazy" : "eager";
	$class = esc_attr($a['class']) != '' ? " ".esc_attr($a['class']) : "";
	$id = esc_attr($a['id']) == "current" ? get_the_ID() : esc_attr($a['id']);
	$exclude = $GLOBALS['do_not_repeat'];

	$query = bp_WP_Query('attachment', [
		'post_status'     => 'any',
		'mime_type'       => 'image/jpeg,image/gif,image/jpg,image/png,image/webp',
		'posts_per_page'  => $num,
		'offset'          => esc_attr($a['offset']),
		'order'           => esc_attr($a['order']),
		'orderby'         => esc_attr($a['order_by']),
		'taxonomy'        => 'image-tags',
		'terms'           => $tags,
		'post_parent'     => ($id != '') ? $id : null,
		'no_found_rows'   => true
	]);
	$imageArray = array();
	$ratioArray = array();

	if( $query->have_posts() ) :

		while ($query->have_posts() ) :
			$query->the_post();
			$getID = get_the_ID();
			$image = wp_get_attachment_image_src( $getID, $size );
			$imgSet = wp_get_attachment_image_srcset( $getID, $size );

			$linkTo = '';
			if ( $link === "alt" ) $linkTo = readMeta(get_the_ID(), '_wp_attachment_image_alt', true);
			elseif ( $link === "description" ) $linkTo = esc_html(get_post(get_the_ID())->post_content);
			elseif ( $link !== "none" && $link !== "no" ) $linkTo = $image[0];

			$getImage = "";
			if ( $linkTo !== "" ) $getImage .= '<a href="'.esc_url($linkTo).'">';
			$getImage .= '<img class="random-img '.$tags[0].'-img" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta($getID, '_wp_attachment_image_alt', true).'">';
			if ( $linkTo !== "" ) $getImage .= '</a>';

			battleplan_countTease( $getID );

			$imageArray[] = do_shortcode('[col class="col-row-of-pics'.$class.'"]'.$getImage.'[/col]');
			$ratioArray[] = $image[2] / $image[1];
			array_push( $GLOBALS['do_not_repeat'], get_the_ID() );
		endwhile;
		wp_reset_postdata();
	endif;

	if ( $shuffle == "yes" || $shuffle == "true" || $shuffle == "random" || !is_array($ratioArray) ) :
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

	return do_shortcode('[layout class="flex-row-of-pics" grid="'.$col.'e" valign="'.esc_attr($a['valign']).'"]'.printArray($imageArray).'[/layout]');
}

add_filter('posts_request', function($sql, $query) {
	if (is_admin() || wp_doing_ajax() || (defined('WP_CLI') && WP_CLI)) return $sql;
	if (!$query->is_main_query()) return $sql;
	return ($query->get('orderby') === 'rand') ? ($sql . ' /* ' . uniqid('', true) . ' */') : $sql;
}, 10, 2);

// Insert Espanol (Spanish Translation) Button
add_shortcode( 'get-translator', 'battleplan_translator' );
function battleplan_translator($atts, $content = null) {
	bp_enqueue_script( 'battleplan-translator', 'script-translator', ['jquery'] );
	bp_inline_minified_css( get_template_directory() . '/style-translator.css' );

	?><script async nonce="<?php echo _BP_NONCE; ?>" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script><?php

	$a = shortcode_atts( array( 'language'=>'spanish', 'text'=>'Haga clic para el sitio en español', 'class'=>'', 'align'=>'center', ), $atts );
	$language = esc_attr($a['language']);
	$text = esc_attr($a['text']);
	$class = esc_attr($a['class']);
	$align = esc_attr($a['align']);

	return "<div id='hablamos-espanol' class='".$class."' style='text-align:".$align."'>".$text."</span></div>";
}

// Build an archive
add_shortcode( 'build-archive', 'battleplan_getBuildArchive' );
function battleplan_getBuildArchive($atts, $content = null) {
	$a = shortcode_atts( array( 'id'=>get_the_ID(), 'type'=>'', 'count_view'=>'false', 'thumb_only'=>'false', 'show_btn'=>'false', 'btn_text'=>'Read More', 'btn_pos'=>'outside', 'show_title'=>'true', 'title_pos'=>'outside', 'meta_pos'=>'below', 'show_date'=>'false', 'show_author'=>'false', 'show_social'=>'false', 'show_excerpt'=>'true', 'show_content'=>'false', 'add_info'=>'', 'show_thumb'=>'true', 'no_pic'=>'', 'size'=>'thumbnail', 'lazy'=>'true', 'pic_size'=>'1/3', 'text_size'=>'', 'accordion'=>'false', 'link'=>'post', 'truncate'=>'false' ), $atts );
	$postID = esc_attr($a['id']);
	$type = esc_attr($a['type']);
	$truncate = esc_attr($a['truncate']);
	$showBtn = esc_attr($a['show_btn']);
	$btnPos = esc_attr($a['btn_pos']);
	$showTitle = esc_attr($a['show_title']);
	$titlePos = esc_attr($a['title_pos']);
	$metaPos = esc_attr($a['meta_pos']);
	$lazy = esc_attr($a['lazy']) === "true" || esc_attr($a['lazy']) === "lazy" ? "lazy" : "eager";
	$showDate = esc_attr($a['show_date']);
	$showAuthor = esc_attr($a['show_author']);
	$showSocial = esc_attr($a['show_social']);
	$showExcerpt = esc_attr($a['show_excerpt']);
	$showContent = esc_attr($a['show_content']);
	if ( $showContent == "true" ) :
		$showExcerpt = "false";
		$content = apply_filters('the_content', get_the_content($postID));
	else:
		if ( $showExcerpt == "true" ) :
			$content = apply_filters('the_excerpt', get_the_excerpt($postID));
		else:
			$content = "";
		endif;
	endif;
	$content .= wp_kses_post($a['add_info']);
	if ( $truncate != "false" && $truncate != "no" ) :
		$content = $truncate == "true" || $truncate == "yes" ? truncateText($content) : truncateText($content, $truncate);
	endif;
	$showThumb = esc_attr($a['show_thumb']);
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
		$linkLoc = esc_url(get_the_permalink($postID));
	else:
		$linkLoc = $link;
	endif;
	$noPic = trim($a['no_pic'] ?? '') === '' ? false : (int) $a['no_pic'];
	$picADA = $titleADA = "";
	if ( $showBtn == "true" ) :
		$picADA = " ada-hidden='true'";
		$titleADA = " aria-hidden='true' tabindex='-1'";
	elseif ( $showTitle != "false" ) :
		$picADA = " ada-hidden='true'";
	endif;
	$archiveTitle = $archiveMeta = $archiveBody = $archiveBtn = "";

	if ( $showThumb != "true" && $showThumb != "false" ) :
		$query = bp_WP_Query('attachment', [
			'post_status'     => 'any',
			'mime_type'       => 'image/jpeg,image/gif,image/jpg,image/png,image/webp',
			'posts_per_page'  => 1,
			'post_parent'     => $postID,
			'orderby'         => 'rand',
			'taxonomy'        => 'image-tags',
			'terms'           => $showThumb
		]);
		$imageArray = array();

		if( $query->have_posts() ) : while ($query->have_posts() ) : $query->the_post();
			$picID = get_the_ID();
			$full = wp_get_attachment_image_src($picID, 'full');
			$image = wp_get_attachment_image_src($picID, $size);
			$imgSet = wp_get_attachment_image_srcset($picID, $size );
			$width = $image[1];
			$height = $image[2];

			$archiveImg = do_shortcode('[img size="'.$picSize.'" class="image-'.$type.'" link="'.$linkLoc.'" '.$picADA.']<img class="image-'.$type.' img-archive '.$tags[0].'-img" src = "'.$image[0].'" loading="'.$lazy.'" width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" srcset="'.$imgSet.'" sizes="'.get_srcset($width).'" alt="'.readMeta(get_the_ID($picID), "_wp_attachment_image_alt", true).'">[/img]');
		endwhile; wp_reset_postdata(); endif;

		if ( $textSize == "" ) :
			$textSize = getTextSize($picSize);
		endif;

	elseif ( has_post_thumbnail() && $showThumb == "true" ) :
		$meta = wp_get_attachment_metadata( get_post_thumbnail_id( $postID ) );
		$thumbW = $meta['sizes'][$size]['width'];
		$thumbH = $meta['sizes'][$size]['height'];

		$archiveImg = do_shortcode('[img size="'.$picSize.'" class="image-'.$type.'" link="'.$linkLoc.'" '.$picADA.']'.get_the_post_thumbnail( $postID, $size, array( 'loading' => $lazy, 'class'=>'img-archive img-'.$type, 'style'=>'aspect-ratio:'.$thumbW.'/'.$thumbH )).'[/img]');
		if ( $textSize == "" ) :
			$textSize = getTextSize($picSize);
		endif;
	elseif ( $noPic != false ) :
		$archiveImg = do_shortcode("[img size='".$picSize."' class='image-".$type." block-placeholder placeholder-".$type."' link='".$linkLoc."' ".$picADA."]".wp_get_attachment_image( $noPic, $size, false, ['class' => 'img-archive img-' . $type])."[/img]");
		if ( $textSize == "" ) :
			$textSize = getTextSize($picSize);
		endif;
	elseif ( $type == "testimonials" ) :
		$words = explode(' ', esc_attr( get_the_title() ) );
		$initials = '';
		foreach ($words as $word) {
			$initials .= substr($word, 0, 1);
		}
		$archiveImg = do_shortcode("[img size='".$picSize."' class='image-".$type." testimonials-generic-icon']<svg version='1.1' class='anonymous-icon' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' x='0px' y='0px' viewBox='0 0 400 400' xml:space='preserve'><g><path class='user-icon' d='M332,319c-34.9,30-80.2,48.2-129.8,48.4h-1.7c-49.7-0.2-95.2-18.5-130.1-48.7c12.6-69,51.6-123.1,100.6-139c-27.6-11.8-46.9-39.1-46.9-71c0-42.6,34.5-77.1,77-77.1s77.1,34.5,77.1,77.1c0,31.9-19.3,59.2-46.9,71C276.7,195,315.7,249,332,319z'/></g></svg>
        <div class='testimonial-initials'>".$initials."</div>[/img]");
		if ( $textSize == "" ) :
			$textSize = getTextSize($picSize);
		endif;
	else :
		$archiveImg = ""; $textSize = "100";
	endif;

	$archiveImg = apply_filters( 'bp_archive_filter_img', $archiveImg );

	if ( $type === "testimonials" ) {
		$testimonialPhone = esc_attr(get_field( "testimonial_phone" ));
		$testimonialEmail = esc_attr(get_field( "testimonial_email" ));
		$testimonialTitle = esc_attr(get_field( "testimonial_title" ));
		$testimonialWeb = esc_attr(get_field( "testimonial_website" ));
		$testimonialLoc = esc_attr(get_field( "testimonial_location" ));
		$testimonialPlatform = strtolower(esc_attr(get_field( "testimonial_platform" )));
		$testimonialMisc1 = esc_attr(get_field( "testimonial_misc1" ));
		$testimonialMisc2 = esc_attr(get_field( "testimonial_misc2" ));
		$testimonialMisc3 = esc_attr(get_field( "testimonial_misc3" ));
		$testimonialMisc4 = esc_attr(get_field( "testimonial_misc4" ));
		$testimonialRate = floatval(get_field('testimonial_rating'));

		$testimonialStars = '';
		for ($i = 1; $i <= 5; $i++) {
			$testimonialStars .= $testimonialRate >= $i ? '[get-icon type="star"]' : ($testimonialRate >= $i - 0.5 ? '[get-icon type="star-half"]' : '[get-icon type="star-empty"]');
		}

		$addNewTag = get_the_date('Y-m-d') > date('Y-m-d', strtotime('-3 months')) ? '<img class="noFX new" loading="'.$lazy.'" src="/wp-content/themes/battleplantheme/common/logos/new-1.webp" width="58" height="52" alt="New customer review, posted within the last 2 months" style="aspect-ratio:58/52" />' : '';

		$buildCredentials = "<div class='testimonials-credential testimonials-name'>".$addNewTag.get_the_title();
		if ( $testimonialTitle ) $buildCredentials .= "<span class='testimonials-title'>, ".$testimonialTitle."</span>";
		$buildCredentials .= "</div>";
		if ( esc_attr(get_field( "testimonial_biz" )) ) :
			$buildCredentials .= "<div class='testimonials-credential testimonials-business'>";
			if ( $testimonialWeb ) $buildCredentials .= "<a href='".esc_url("https://".$testimonialWeb)."' target='_blank'>";
			$buildCredentials .= esc_attr(get_field( "testimonial_biz" ));
			if ( $testimonialWeb ) $buildCredentials .= "</a>";
			$buildCredentials .= "</div>";
		endif;
		if ( $testimonialLoc ) $buildCredentials .= "<div class='testimonials-credential testimonials-location'>".$testimonialLoc."</div>";
		if ( $testimonialPhone ) $buildCredentials .= "<div class='testimonials-credential testimonials-phone'>".$testimonialPhone."</div>";
		if ( $testimonialEmail ) $buildCredentials .= "<div class='testimonials-credential testimonials-email'><a href='mailto:".esc_url($testimonialEmail)."'>".$testimonialEmail."</a></div>";
		if ( $testimonialMisc1 ) $buildCredentials .= "<div class='testimonials-credential testimonials-misc1'>".$testimonialMisc1."</div>";
		if ( $testimonialMisc2 ) $buildCredentials .= "<div class='testimonials-credential testimonials-misc2'>".$testimonialMisc2."</div>";
		if ( $testimonialMisc3 ) $buildCredentials .= "<div class='testimonials-credential testimonials-misc3'>".$testimonialMisc3."</div>";
		if ( $testimonialMisc4 ) $buildCredentials .= "<div class='testimonials-credential testimonials-misc4'>".$testimonialMisc4."</div>";
		if ( $testimonialRate ) $buildCredentials .= "<div class='testimonials-credential testimonials-rating'>".$testimonialStars."</div>";

		$content = apply_filters('the_content', get_the_content($postID));
		if ( $truncate != "false" && $truncate != "no" ) :
			$content = $truncate == "true" || $truncate == "yes" ? truncateText($content) : truncateText($content, $truncate);
		endif;

		$archiveBody = '[txt class="testimonials-quote"][p][get-icon type="quote"]'.$content.'[/p][/txt][txt size="11/12" class="testimonials-credentials"]'.$buildCredentials.'[/txt][txt size="1/12" class="testimonials-platform testimonials-platform-'.$testimonialPlatform.'"][/txt]';

		// --- Inject review schema into global array for Yoast ---
		$GLOBALS['bp_reviews'][] = [
			'author' => get_the_title(),
			'date'   => get_the_date('c'),
			'text'   => wp_strip_all_tags(get_the_content()),
			'rating' => floatval(get_field('testimonial_rating')),
		];

	} else {
		if ( esc_attr($a['accordion']) == "true" ) :
			$archiveBody = '[accordion title="'.esc_html(get_the_title($postID)).'" excerpt="'.wp_kses_post(get_the_excerpt($postID)).'"]'.$content.'[/accordion]';
		else :
			if ( $showTitle != "false" ) :
				$archiveLink = '<a href="'.esc_url($linkLoc).'" class="link-archive link-'.get_post_type($postID).'"'.$titleADA.'>';
				$archiveTitle .= '<h3 data-count-view="'.esc_attr($a['count_view']).'">';
				if ( $showContent != "true" && $link != "false" ) $archiveTitle .= $archiveLink;
				if ( $showTitle == "true" ) :
					$archiveTitle .= esc_html(get_the_title($postID));
				else:
					$archiveTitle .= $showTitle;
				endif;
				if ( $showContent != "true" && $link != "false" ) $archiveTitle .= '</a>';
				$archiveTitle .= "</h3>";

				$archiveTitle = apply_filters( 'bp_archive_filter_title', $archiveTitle, $archiveLink );
			endif;
			if ( $showDate == "true" || $showAuthor == "true" || $showSocial == "true" ) $archiveMeta .= '<div class="archive-meta">';
			if ( function_exists( 'overrideArchiveMeta' ) ) : $archiveMeta .= overrideArchiveMeta( $type );
			else :
				if ( $showDate == "true" && is_post_type_archive('events') ) $archiveMeta .= include('wp-content/themes/battleplantheme/elements/element-events-meta.php');
				if ( $showDate == "true" && !is_post_type_archive('events') ) $archiveMeta .= '<span class="archive-date '.$type.'-date date">[get-icon type="calendar"]'.get_the_date().'</span>';
				if ( $showAuthor == "profile") $archiveMeta .= '<a href="/profile/?user='.get_the_author().'">';
				if ( $showAuthor != "false") $archiveMeta .= '<span class="archive-author '.$type.'-author author">[get-icon type="user"]'.get_the_author().'</span>';
				if ( $showAuthor == "profile") $archiveMeta .= '</a>';
				if ( $showSocial == "true") $archiveMeta .= '<span class="archive-social '.$type.'-social social">'.do_shortcode('[add-share-buttons facebook="true" twitter="true"]').'</span>';
			endif;
			if ( $showDate == "true" || $showAuthor == "true" || $showSocial == "true" ) $archiveMeta .= '</div>';
			$archiveMeta = apply_filters( 'bp_archive_filter_meta', $archiveMeta );

			$archiveBody .= '[p]'.$content.'[/p]';

			if ( $type === "galleries" ) :
				if ( has_term( 'auto-generated', 'gallery-type' ) ) :
					$count = esc_attr(get_field("image_number"));
				elseif ( has_term( 'shortcode', 'gallery-type' ) ) :
					$count = esc_attr(get_field("image_number"));
				else:
					$all_attachments = get_posts( array( 'post_type'=>'attachment', 'post_mime_type'=>'image', 'post_parent'=>$postID, 'post_status'=>'published', 'numberposts'=>-1 ) );
					$count = count($all_attachments);
				endif;
				if ( $count != "" ) :
					$subline = sprintf( _n( '%s Photo', '%s Photos', $count, 'battleplan' ), number_format($count?? 0.0) );
					$archiveBody .= '<div class="photo-count">';
					if ( $link != "false" ) $archiveBody .= '<a href="'.esc_url(get_the_permalink($postID)).'" class="link-archive link-'.get_post_type($postID).'" aria-hidden="true" tabindex="-1">';
					$archiveBody .= '<p class="gallery-subtitle">'.$subline.'</p>';
					if ( $link != "false" ) $archiveBody .= '</a>';
					$archiveBody .= '</div>';
				endif;
			endif;

			$archiveBody = apply_filters( 'bp_archive_filter_body', $archiveBody );
		endif;
	}

	if ( $showBtn == "true" ) :
		$ada = $type == "testimonials" ? " testimonials" : ' about '.esc_html(get_the_title($postID));
		$archiveBtn = do_shortcode('[btn class="button-'.$type.'" link="'.$linkLoc.'" ada="'.$ada.'"]'.esc_attr($a['btn_text']).'[/btn]');
		$archiveBtn = apply_filters( 'bp_archive_filter_btn', $archiveBtn );
	endif;

	if ( esc_attr($a['thumb_only']) == "true" ) :
		$showArchive = $archiveImg;
	else:
		$buildBody = "";
		if ( $titlePos == "inside" || $btnPos == "inside" || $type == "testimonials" ) : $groupSize = $textSize; $textSize = "100"; $buildBody .= "[group size='".$groupSize."' class='group-".$type."']"; endif;
		if ( $type != "testimonials" ) $buildBody .= "[txt size='".$textSize."' class='text-".$type."']";
		if ( $titlePos == "inside" ) {
			$buildBody .= $metaPos == "below" || $metaPos == "under" ? $archiveTitle.$archiveMeta : $archiveMeta.$archiveTitle;
		}
		$buildBody .= do_shortcode($archiveBody);
		if ( $type != "testimonials" ) $buildBody .= "[/txt]";
		if ( $btnPos == "inside" ) $buildBody .= $archiveBtn;
		if ( $titlePos == "inside" || $btnPos == "inside" || $type == "testimonials" ) $buildBody .= "[/group]";

		$showArchive = "";
		if ( $titlePos != "inside" ) {
			$showArchive .= $metaPos == "below"|| $metaPos == "under" ? $archiveTitle.$archiveMeta : $archiveMeta.$archiveTitle;
		}
		$showArchive .= $archiveImg.do_shortcode($buildBody);
		if ( $btnPos != "inside" ) $showArchive .= $archiveBtn;

		battleplan_countTease( $postID );

	endif;

	return $showArchive;
}

// Display randomly selected posts - start/end can be dates or -53 week / -51 week */
add_shortcode( 'get-random-posts', 'battleplan_getRandomPosts' );
function battleplan_getRandomPosts($atts, $content = null) {
	$a = shortcode_atts( array( 'num'=>'1', 'offset'=>'0', 'leeway'=>'0', 'type'=>'post', 'tax'=>'', 'terms'=>'', 'field_key'=>'', 'field_value'=>'', 'field_compare'=>'IN', 'orderby'=>'rand', 'sort'=>'asc', 'show_title'=>'true', 'title_pos'=>'outside', 'count_view'=>'false', 'show_date'=>'false', 'show_author'=>'false', 'show_excerpt'=>'true', 'show_social'=>'false', 'show_btn'=>'true', 'button'=>'Read More', 'btn_pos'=>'inside', 'show_content'=>'false', 'thumb_only'=>'false', 'show_thumb'=>'true', 'thumb_col'=>'1', 'thumbnail'=>'.carousel-screen .carousel-item {
  padding: 0 50px;
}carou', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'lazy'=>'true', 'size'=>'thumbnail', 'pic_size'=>'1/3', 'text_size'=>'', 'link'=>'post', 'truncate'=>'true' ), $atts );
	$num = esc_attr($a['num']);
	$leeway = (int) esc_attr($a['leeway']);
	$offset = (int) esc_attr($a['offset']);
	$offset = ($offset === 0) ? ($leeway > 0 ? rand(0, $leeway) : 0) : $offset;
	$postType = esc_attr($a['type']);
	$title = esc_attr($a['show_title']);
	$titlePos = esc_attr($a['title_pos']);
	$showDate = esc_attr($a['show_date']);
	$showExcerpt = esc_attr($a['show_excerpt']);
	$showContent = esc_attr($a['show_content']);
	$lazy = esc_attr($a['show_content']) === "true" ? 'lazy' : 'eager';
	$showAuthor = esc_attr($a['show_author']);
	$showSocial = esc_attr($a['show_social']);
	$showBtn = esc_attr($a['show_btn']);
	$taxonomy = esc_attr($a['tax']);
	$term = esc_attr($a['terms']);
	$terms = explode( ',', $term );
	$fieldKey = esc_attr($a['field_key']);
	$fieldValue = esc_attr($a['field_value']);
	$fieldValues = explode( ',', $fieldValue );
	$requireThumb = (esc_attr($a['thumbnail']) === 'force');
	$thumbOnly = (esc_attr($a['thumb_only']) === 'true');
	$meta = [];

	if ($fieldKey && $fieldValue) {
		$meta[] = [
			'key'     => $fieldKey,
			'value'   => $fieldValues,
			'compare' => esc_attr($a['field_compare']),
		];
	}

	if ($requireThumb || $thumbOnly) {
		$meta[] = [
			'key'     => '_thumbnail_id',
			'compare' => 'EXISTS',
		];
	}

	$exclude = array_merge( explode(',', esc_attr($a['exclude'])), $GLOBALS['do_not_repeat'] );
	if ( esc_attr($a['x_current']) === "true" ) :
		global $post;
		array_push($exclude, $post->ID);
	endif;

	$picSize = esc_attr($a['pic_size']);
	if ( $thumbOnly ) :
		$title = $showDate = $showExcerpt = $showContent = $showAuthor = $showSocial = $showBtn = "false";
		$picSize = "100";
	endif;

	$combinePosts = '';
	$meta_query = null;
	if (count($meta) === 1) {
		$meta_query = $meta;
	} elseif (count($meta) > 1) {
		$meta_query = array_merge(['relation' => 'AND'], $meta);
	}

	$query = bp_WP_Query($postType, [
		'posts_per_page' => $num,
		'offset'         => $offset,
		'start'          => esc_attr($a['start']),
		'end'            => esc_attr($a['end']),
		'order'          => esc_attr($a['sort']),
		'orderby'        => esc_attr($a['order_by']),
		'post__not_in'   => $exclude,
		'taxonomy'       => $taxonomy,
		'terms'          => $terms,
		'meta_query'     => $meta_query,
	]);

	if ( $query->have_posts() ) :
		while ( $query->have_posts() ) :
			$query->the_post();

			$showPost = do_shortcode('[build-archive type="'.$postType.'" count_view="'.esc_attr($a['count_view']).'" show_thumb="'.esc_attr($a['show_thumb']).'" thumb_only="'.$thumbOnly.'" show_btn="'.$showBtn.'" btn_text="'.esc_attr($a['button']).'" btn_pos="'.esc_attr($a['btn_pos']).'" show_title="'.$title.'" title_pos="'.$titlePos.'" lazy="'.$lazy.'" show_date="'.$showDate.'" show_excerpt="'.$showExcerpt.'" show_social="'.$showSocial.'" show_content="'.$showContent.'" show_author="'.$showAuthor.'" size="'.esc_attr($a['size']).'" pic_size="'.$picSize.'" text_size="'.esc_attr($a['text_size']).'" link="'.esc_attr($a['link']).'" truncate="'.esc_attr($a['truncate']).'"]');

			if ( $num > 1 ) $showPost = do_shortcode('[col]'.$showPost.'[/col]');
			if ( has_post_thumbnail() || !$requireThumb ) $combinePosts .= $showPost;

			array_push( $GLOBALS['do_not_repeat'], get_the_ID() );
		endwhile;
		wp_reset_postdata();
	endif;

	if ( $thumbOnly === "true" ) $combinePosts = '<div class="random-post random-posts thumb-only thumb-col-'.esc_attr($a['thumb_col']).'">'.$combinePosts.'</div>';

	return do_shortcode($combinePosts);
}

// Display posts & images in a Bootstrap slider
add_shortcode( 'get-post-slider', 'battleplan_getPostSlider' );
function battleplan_getPostSlider($atts, $content = null ) {
	bp_enqueue_script( 'battleplan-carousel', 'script-carousel', ['battleplan-script-pages'] );
	bp_inline_minified_css( get_template_directory() . '/style-carousel.css' );

	$a = shortcode_atts( array( 'type'=>'testimonials', 'auto'=>'yes', 'interval'=>'6000', 'loop'=>'true', 'num'=>'4', 'offset'=>'0', 'pics'=>'yes', 'caption'=>'no', 'controls'=>'yes', 'controls_pos'=>'below', 'indicators'=>'no', 'justify'=>'center', 'pause'=>'true', 'speed'=>'fast', 'orderby'=>'rand', 'order'=>'asc', 'post_btn'=>'', 'show_thumb'=>'true', 'all_btn'=>'View All', 'show_date'=>'false', 'show_author'=>'false', 'show_excerpt'=>'true', 'show_content'=>'false', 'title_pos'=>'', 'link'=>'', 'pic_size'=>'1/3', 'text_size'=>'', 'slide_type'=>'box', 'slide_effect'=>'fade', 'tax'=>'', 'terms'=>'', 'tag'=>'', 'start'=>'', 'end'=>'', 'exclude'=>'', 'x_current'=>'true', 'size'=>'thumbnail', 'id'=>'', 'mult'=>'1', 'class'=>'', 'truncate'=>'true', 'lazy'=>'true', 'blur'=>'false', 'mask'=>'false', 'rand_start'=>'', 'content_type'=>'image' ), $atts );
	$num = esc_attr($a['num']);
	$controls = esc_attr($a['controls']);
	$controlsPos = esc_attr($a['controls_pos']);
	$indicators = esc_attr($a['indicators']);
	$autoplay = esc_attr($a['auto']);
	$type = esc_attr($a['type']);
	$postBtn = esc_attr($a['post_btn']);
	$allBtn = esc_attr($a['all_btn']);
	$allBtn = $allBtn == "false" || $allBtn == "no" || $allBtn == "" ? "false" : $allBtn;
	$order = esc_attr($a['order']);
	$caption = esc_attr($a['caption']);
	$contentType = esc_attr($a['content_type']);
	$contentType = $type === "testimonials" || $caption !== 'no' ? 'text' : $contentType;
	$slideType = esc_attr($a['slide_type']) !== "fade" ? esc_attr($a['slide_type']) : "box";
	$slideEffect = esc_attr($a['slide_effect']);
	$speed = esc_attr($a['speed']);
	$tag = esc_attr($a['tag']);
	$tags = explode( ',', $tag );
	$taxonomy = esc_attr($a['tax']);
	$term = esc_attr($a['terms']);
	$size = esc_attr($a['size']);
	$randStart = esc_attr($a['rand_start']) != '' ? esc_attr($a['rand_start']) : ($indicators == "yes" ? "false" : "true");
	$exclude = array_merge( explode(',', esc_attr($a['exclude'])), $GLOBALS['do_not_repeat'] );

	if ( esc_attr($a['x_current']) == "true" ) :
		global $post;
		array_push($exclude, $post->ID);
	endif;
	$link = esc_attr($a['link']);
	$id = esc_attr($a['id']);
	$blur = esc_attr($a['blur']) === "true" ? " slider-blur" : "";
	$mask = esc_attr($a['mask']);
	$lazy = esc_attr($a['lazy']) === "true" && $slideEffect !== "dissolve" ? "lazy" : "eager";
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
	$multDisplay = $rowDisplay = 0;
	$numDisplay = -1;
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
		$query = bp_WP_Query('attachment', [
			'post_status'     => 'any',
			'mime_type'       => 'image/jpeg,image/gif,image/jpg,image/png,image/webp',
			'posts_per_page'  => $num,
			'order'           => $order,
			'offset'          => esc_attr($a['offset']),
			'post__not_in'    => $exclude,
			'post_parent'     => ($id != '') ? $id : null,
			'taxonomy'        => ($id == '' || $tag != '') ? 'image-tags' : null,
			'terms'           => ($id == '' || $tag != '') ? $tags : null,
			'orderby'         => esc_attr($a['order_by'])
		]);
		if( $query->have_posts() ) :

			$buildIndicators = '<ol class="carousel-indicators" style="justify-content: '.esc_attr($a['justify']).'">';
			$buildInner = '<div class="carousel-inner">';

			while ($query->have_posts() ) :
				$query->the_post();
				$numDisplay++;
				if ( $rowDisplay == 0 ) :
				 	$active = $numDisplay == 0 ? " active" : "";
					$buildIndicators .= '<li data-target="#'.$type.'Slider'.$sliderNum.'" data-slide-to="'.$numDisplay.'" class="carousel-icon'.$active.'"></li>';
					if ( $numDisplay != 0 ) $buildInner .= '</div>';
					$buildInner .= '<div class="'.$active.' carousel-item carousel-item-'.$type.'">';

					if ($mask !== "false") $buildInner .= "<div class='slider-mask'></div>";
				endif;

				$image = wp_get_attachment_image_src(get_the_ID(), $size );
				$imgSet = wp_get_attachment_image_srcset(get_the_ID(), $size );

				$linkTo = $buildImg = '';
				$attachment = get_post(get_the_ID());
				$headline = esc_html($attachment->post_excerpt);
				$description = esc_html($attachment->post_content);

				if ( $link == "alt" ) $linkTo = readMeta(get_the_ID(), '_wp_attachment_image_alt', true);
				if ( $link == "description" ) $linkTo = esc_html($description);
				if ( $link != "none" ) $buildImg = "<a href='".esc_url($linkTo)."' class='link-archive link-".$type."'>";

				$buildImg .= '<img class="img-slider '.$tags[0].'-img" loading="'.$lazy.'" src = "'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.readMeta(get_the_ID(), "_wp_attachment_image_alt", true).'">';

	// 9/11/25 - removed  style="aspect-ratio:'.$image[1].'/'.$image[2].'" from $buildImg to allow for captions to drop below pic on mobile

				battleplan_countTease( get_the_ID() );

				if ( $caption === "yes" || $caption === "true" || $caption === "title" ) :
					$buildImg .= "<div class='caption-holder'><div class='img-caption'>".get_the_title(get_the_ID())."</div></div>";
				elseif ( $caption === "alt" ) :
					$buildImg .= "<div class='caption-holder'><div class='img-caption'>".readMeta(get_the_ID(), '_wp_attachment_image_alt', true)."</div></div>";
				elseif ( $caption === "description" ) :
					if ($headline || $description) {
						$buildImg.= "<div class='caption-holder'><div class='img-caption'>";
						if ($headline) $buildImg.= "<h2>".$headline."</h2>";
						if ($description) $buildImg.= "<p>".$description."</p>";
						$buildImg.= "</div></div>";
					}
				endif;

				if ( $link != "none" ) $buildImg .= "</a>";

	 			$buildInner .= do_shortcode("[img size='".$imgSize."' class='image-".$type."']".$buildImg."[/img]");

				$rowDisplay++;
				if ( $rowDisplay == $mult ) $rowDisplay = 0;

				array_push( $GLOBALS['do_not_repeat'], get_the_ID() );
			endwhile;
			$buildInner .= "<!--div class='clearfix'></div--></div>";
			wp_reset_postdata();
		endif;
	else :
		$query = bp_WP_Query($type, [
			'posts_per_page' => -1,
			'offset'         => esc_attr($a['offset']),
			'start'          => esc_attr($a['start']),
			'end'            => esc_attr($a['end']),
			'order'          => ($type == 'testimonials') ? 'desc' : $order,
			'orderby'        => ($type == 'testimonials') ? 'post_date' : esc_attr($a['order_by']),
			'post__not_in'   => $exclude,
			'taxonomy'       => $taxonomy,
			'terms'          => $term
		]);
		$thumbs = $no_thumbs = $all_posts = array();

		if ( $query->have_posts() ) :
       		while ( $query->have_posts() ) : $query->the_post();
				if ( has_post_thumbnail() ) :
					$thumbs[] = get_post();
				else:
					$no_thumbs[] = get_post();
				endif;
			endwhile;

			if ( $type == "testimonials" ) :
				if ( count($thumbs) > 1) :
					$first_el = array_shift($thumbs);
					shuffle($thumbs);
					array_unshift($thumbs, $first_el);
				endif;
				if ( count($no_thumbs) > 1) :
					$first_el = array_shift($no_thumbs);
					shuffle($no_thumbs);
					array_unshift($no_thumbs, $first_el);
				endif;
			endif;

			$all_posts = array_merge($thumbs, $no_thumbs);

			$buildIndicators = '<ol class="carousel-indicators">';
			$buildInner = '<div class="carousel-inner">';

			foreach ( $all_posts as $post_object ) :
				if ( $numDisplay < $num ) :
					if ( esc_attr($a['pics']) == "no" || has_post_thumbnail() || $type == "testimonials" ) :

						global $post;
						$post = $post_object;
						setup_postdata($post);

						$numDisplay++;
						$multDisplay++;

						$buildArchive = do_shortcode('[build-archive type="'.$type.'" show_btn="'.$showBtn.'" btn_text="'.$postBtn.'" show_thumb="'.esc_attr($a['show_thumb']).'" show_excerpt="'.esc_attr($a['show_excerpt']).'" show_content="'.esc_attr($a['show_content']).'" show_date="'.esc_attr($a['show_date']).'" show_author="'.esc_attr($a['show_author']).'" title_pos="'.esc_attr($a['title_pos']).'" lazy="'.$lazy.'" size="'.$size.'" pic_size="'.esc_attr($a['pic_size']).'" text_size="'.esc_attr($a['text_size']).'" link="'.$link.'" truncate="'.esc_attr($a['truncate']).'"]');

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

						array_push( $GLOBALS['do_not_repeat'], get_the_ID() );
						wp_reset_postdata();
					endif;
				endif;
			endforeach;
		wp_reset_postdata();
		endif;
	endif;

	$buildIndicators .= '</ol>';
	$buildInner .= '</div>';

	$controlsPrevBtn = '<div class="block block-button button-prev"><a class="button carousel-control-prev'.$controlClass.'" href="#'.$type.'Slider'.$sliderNum.'" data-slide="prev" aria-label="Previous Slide"><span class="carousel-control-prev-icon">[get-icon type="chevron-left"]<span class="sr-only">Previous Slide</span></span></a></div>';
	$controlsNextBtn = '<div class="block block-button button-next"><a class="button carousel-control-next'.$controlClass.'" href="#'.$type.'Slider'.$sliderNum.'" data-slide="next" aria-label="Next Slide"><span class="carousel-control-next-icon">[get-icon type="chevron-right"]<span class="sr-only">Next Slide</span></span></a></div>';
	$viewMoreBtn = do_shortcode('[btn link="'.$linkTo.'" class="button-all"]'.$allBtn.'[/btn]');

	$buildControls = "<div class='controls controls-".$controlsPos."'>";
	$buildControls .= $controlsPrevBtn;
	if ( $allBtn != "false" ) $buildControls .= $viewMoreBtn;
	$buildControls .= $controlsNextBtn;
	$buildControls .= "</div>";

	$slideClass = esc_attr($a['class'])." carousel-".$slideType." effect-".$slideEffect;

	$buildSlider = '<div id="'.$type.'Slider'.$sliderNum.'" class="carousel slide slider slider-'.$type.' content-'.$contentType.' '.$slideClass.' mult-'.$mult.$blur.'" data-interval="'.esc_attr($a['interval']).'" data-pause="'.$pause.'" data-speed="'.$speed.'" data-random="'.$randStart.'"';

	if ( $autoplay == "yes" || $autoplay == "true" ) $buildSlider .= ' data-auto="true"';

	$buildSlider .= '>';

	if ( $controlsPos == "above" || $controlsPos == "before" ) :
		if ( $controls == "yes" ) :
			$buildSlider .= $buildControls;
		else:
			if ( $allBtn != "false" ) :
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
			if ( $allBtn != "false" ) :
				$buildSlider .= $viewMoreBtn;
			endif;
		endif;
	endif;

	$buildSlider .= '</div>';

	return do_shortcode($buildSlider);
}

// Display row of logos that slide from left to right
add_shortcode( 'get-logo-slider', 'battleplan_getLogoSlider' );
function battleplan_getLogoSlider($atts, $content = null ) {
	bp_enqueue_script( 'battleplan-logo-slider', 'script-logo-slider' );
	bp_inline_minified_css( get_template_directory() . '/style-carousel.css' );
	bp_inline_minified_css( get_template_directory() . '/style-logo-slider.css' );

	$a = shortcode_atts( array( 'num'=>'-1', 'space'=>'15', 'size'=>'full', 'max_w'=>'33', 'tag'=>'', 'package'=>'', 'order_by'=>'rand', 'order'=>'ASC', 'shuffle'=>'false', 'speed'=>'slow', 'pause'=>'no', 'link'=>'false', 'lazy'=>'false', 'direction'=>'normal'), $atts );
	$tags = explode( ',', esc_attr($a['tag']) );
	$orderBy = esc_attr($a['order_by']);
	$link = esc_attr($a['link']);
	$package = esc_attr($a['package']);
	$lazy = esc_attr($a['lazy']) === "true" ? "lazy" : "eager";
	$direction = esc_attr($a['direction']) === "normal" ? "normal" : "reverse";

	$query = bp_WP_Query('attachment', [
		'post_status'     => 'any',
		'mime_type'       => 'image/jpeg,image/gif,image/jpg,image/png,image/webp',
		'posts_per_page'  => esc_attr($a['num']),
		'order'           => esc_attr($a['order']),
		'taxonomy'        => 'image-tags',
		'terms'           => $tags
	]);
	$imageArray = array();

	if ( $query->have_posts() ) :
		while ($query->have_posts() ) :
			$query->the_post();
			$totalNum = $query->post_count;
			$image = wp_get_attachment_image_src( get_the_ID(), esc_attr($a['size']) );
			$imgLink = $link == 'desc' || $link == 'description' ? get_the_content(get_the_ID()) : $image[0];
			$getImage = "";
			if ( $link != "false" && $imgLink != '' ) $getImage .= '<a href="'.esc_url($imgLink).'">';
			$getImage .= '<img class="logo-img '.$tags[0].'-img" loading="'.$lazy.'" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" alt="'.readMeta(get_the_ID(), '_wp_attachment_image_alt', true).'">';
			if ( $link != "false" && $imgLink != '' ) $getImage .= '</a>';
			$imageArray[] = '<div>'.$getImage.'</div>';
		endwhile;
		wp_reset_postdata();
	endif;

	if ( $package == "hvac" ) :
		$addLogos = array( "amana", "american-standard", "bosch", "bryant", "carrier", "comfortmaker", "goodman", "heil", "honeywell", "lennox", "rheem", "ruud", "samsung", "tempstar", "trane", "york" );
		for ( $i = 0; $i < count($addLogos); $i++ ) :

			$alt = "We service ".ucwords(str_replace('-', ' ', $addLogos[$i]))." air conditioners, heaters and other HVAC equipment.";

			$imageURL  = get_template_directory_uri()."/common/hvac-{$addLogos[$i]}/{$addLogos[$i]}-sidebar-logo.webp";
			$imagePath = get_template_directory()."/common/hvac-{$addLogos[$i]}/{$addLogos[$i]}-sidebar-logo.webp";

			if ( ! file_exists($imagePath) ) continue;

			list($width, $height) = getimagesize($imagePath);

			$getImage  = '<img class="logo-img hvac-logo-img" loading="'.$lazy.'" src="'.esc_url($imageURL).'" width="'.$width.'" height="'.$height.'" alt="'.esc_attr($alt).'">';

			$imageArray[] = '<div>'.$getImage.'</div>';

		endfor;

	endif;

	if ( esc_attr($a['shuffle']) != "false" ) shuffle($imageArray);

	return '<div class="logo-slider" data-speed="'.esc_attr($a['speed']).'" data-direction="'.$direction.'" data-pause="'.esc_attr($a['pause']).'" data-maxw="'.esc_attr($a['max_w']).'" data-spacing="'.esc_attr($a['space']).'"><div class="logo-row">'.printArray($imageArray).'</div></div>';
}

// Generate an array of IDs for images, filtered by image-tags
add_shortcode( 'load-images', 'battleplan_loadImagesByTag' );
function battleplan_loadImagesByTag( $atts, $content = null ) {
	$a = shortcode_atts( array( 'max'=>'-1', 'tags'=>'', 'operator'=>'', 'field'=>'', 'order_by'=>'meta_value_num', 'order'=>'ASC', 'value'=>'', 'type'=>'', 'compare'=>'', ), $atts );
	$max = esc_attr($a['max']);
	$tags = esc_attr($a['tags']);
	$operator = esc_attr($a['operator']);
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
		$query = bp_WP_Query('attachment', [
			'post_status'     => 'any',
			'mime_type'       => 'image/jpeg,image/gif,image/jpg,image/png,image/webp',
			'posts_per_page'  => $max,
			'meta_query'      => [[
				'key'     => $field,
				'value'   => esc_attr($a['value']),
				'type'    => esc_attr($a['type']),
				'compare' => $compare
			]],
			'orderby'         => $orderBy,
			'order'           => $order
		]);
	elseif ( $tags == "" ) :
		$tags = get_the_slug();
		$query = bp_WP_Query('attachment', [
			'post_status'     => 'any',
			'mime_type'       => 'image/jpeg,image/gif,image/jpg,image/png,image/webp',
			'posts_per_page'  => $max,
			'taxonomy'        => 'image-tags',
			'terms'           => $tags,
			'orderby'         => $orderBy,
			'order'           => $order
		]);
	else:
		$query = bp_WP_Query('attachment', [
			'post_status'     => 'any',
			'mime_type'       => 'image/jpeg,image/gif,image/jpg,image/png,image/webp',
			'posts_per_page'  => $max,
			'tax_multi'       => ['image-tags' => explode(',', $tags)],
			'tax_relation'    => $operator,
			'orderby'         => $orderBy,
			'order'           => $order
		]);
	endif;

	$imageIDs = array();
	if ( $query->have_posts() ) :
		while ( $query->have_posts() ) :
			$query->the_post();
			$imageIDs[] = get_the_ID();
		endwhile;
		wp_reset_postdata();
	endif;
	update_field('image_number', count($imageIDs));
	return wp_json_encode($imageIDs);
}

// Generate a WordPress gallery and filter
add_shortcode( 'get-gallery', 'battleplan_setUpWPGallery' );
function battleplan_setUpWPGallery( $atts, $content = null ) {
	bp_enqueue_script( 'battleplan-script-lightbox', 'script-lightbox' );
	bp_inline_minified_css( get_template_directory() . '/style-lightbox.css' );

	$a = shortcode_atts( array( 'name'=>'', 'size'=>'thumbnail', 'id'=>'', 'columns'=>'5', 'max'=>'-1', 'offset'=>'0', 'caption'=>'false', 'start'=>'', 'end'=>'', 'order_by'=>'menu_order', 'order'=>'ASC', 'tags'=>'', 'field'=>'', 'operator'=>'any', 'class'=>'', 'include'=>'', 'exclude'=>'', 'unique'=>'true', 'value'=>'', 'type'=>'', 'compare'=>'' ), $atts );
	$id = esc_attr($a['id']);
	if ( $id == '' ) global $post; $id = intval( $post->ID );
	$name = esc_attr($a['name']) == '' ? $id : esc_attr($a['name']);
	$size = esc_attr($a['size']);
	$caption = esc_attr($a['caption']);
	$orderBy = esc_attr($a['order_by']);
	$exclude = esc_attr($a['unique']) == 'true' ? array_merge( explode(',', esc_attr($a['exclude'])), $GLOBALS['do_not_repeat'] ) : explode(',', esc_attr($a['exclude']));
	$operator = esc_attr($a['operator']) == 'any' || esc_attr($a['operator']) == 'or' ? 'OR' : 'AND';
	$include = esc_attr($a['include']);
	$imageIDs = do_shortcode('[load-images tags="'.esc_attr($a['tags']).'" operator="'.$operator.'" field="'.esc_attr($a['field']).'" order_by="'.$orderBy.'" order="'.esc_attr($a['order']).'" value="'.esc_attr($a['value']).'" type="'.esc_attr($a['type']).'" compare="'.esc_attr($a['compare']).'"]');
	$imageIDs = json_decode($imageIDs, true);
	$class = esc_attr($a['class']);
	if ( $class != "" ) $class = " ".$class;

	$gallery = '<div id="gallery-'.$name.'" class="gallery gallery-'.$id.' gallery-column-'.esc_attr($a['columns']).' gallery-size-'.$size.' lightbox">';

	$query = bp_WP_Query('attachment', [
		'post_status'     => 'any',
		'mime_type'       => 'image/jpeg,image/gif,image/jpg,image/png,image/webp',
		'posts_per_page'  => esc_attr($a['max']),
		'order'           => esc_attr($a['order']),
		'offset'          => esc_attr($a['offset']),
		'start'           => esc_attr($a['start']),
		'end'             => esc_attr($a['end']),
		'post__in'        => !empty($imageIDs) ? $imageIDs : null,
		'post_parent'     => (empty($imageIDs) && empty($include)) ? $id : null,
		'orderby'         => !empty($imageIDs) ? 'post__in' : $orderBy
	]);

	$count = 0;

	if ( $query->have_posts() ) :
		while ( $query->have_posts() ) :
			$query->the_post();
			$getID = get_the_ID();
			$full = wp_get_attachment_image_src($getID, 'full');
			$image = wp_get_attachment_image_src($getID, $size);
			$imgSet = wp_get_attachment_image_srcset($getID, $size );
			$picAlt = get_post_meta($getID , '_wp_attachment_image_alt', true);
			$picDesc = wp_get_attachment_caption() ? wp_get_attachment_caption() : $picAlt;
			$addCaption = $caption !== "false" ? 'data-title="'.get_the_title().'" data-description="'.$picDesc.'" data-desc-position="'.$caption.'" ' : '';
			$count++;

			$gallery .= '<div class="col col-archive col-gallery id-'.$getID.'"><figure class="col-inner">';

			if ( $caption === "above" || $caption === "top" ) $gallery .= '<figcaption class="gallery-caption">'.$picDesc.'</figcaption>';

			$gallery .= '<a class="link-archive link-gallery" data-gallery="'.$name.'" href="'.esc_url($full[0]).'" '.$addCaption.'data-effect="fade" data-zoomable="true" data-draggable="true"><img class="img-gallery wp-image-'.get_the_ID().'" loading="lazy" src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'" style="aspect-ratio:'.$image[1].'/'.$image[2].'" srcset="'.$imgSet.'" sizes="'.get_srcset($image[1]).'" alt="'.$picAlt.'"></a>';

			if ( $caption === "below" || $caption === "bottom" ) $gallery .= '<figcaption class="gallery-caption">'.$picDesc.'</figcaption>';

			$gallery .= '</figure></div>';

			array_push( $GLOBALS['do_not_repeat'], get_the_ID() );
		endwhile;
		wp_reset_postdata();
	endif;
	$gallery .= "</div>";

	$gallery .= "<div class='lightbox-overlay'>";
	$gallery .= "<img class='lightbox-image' src='' alt=''>";
	$gallery .= "<div class='lightbox-counter'></div>";
	//$gallery .= "<div class='closeBtn' aria-label='close' aria-hidden='false' tabindex='0'><span class='icon x-large'></span></div>";
	/* WP3 validation 12/11/24 */
	$gallery .= "<button class='closeBtn' aria-label='close'>[get-icon type='x-large']<span class='sr-only'>Close Gallery</span></button>";
	$gallery .= "<div class='block block-button button-prev'><button aria-label='Previous Photo'>[get-icon type='chevron-left']<span class='sr-only'>Previous Photo</span></button></div>";
	$gallery .= "<div class='block block-button button-next'><button aria-label='Next Photo'>[get-icon type='chevron-right']<span class='sr-only'>Next Photo</span></button></div>";
	$gallery .= "</div>";

	update_field('image_number', $count);
	return do_shortcode($gallery);
}

// Generate a WordPress gallery and filter
add_shortcode( 'get-video-gallery', 'battleplan_setUpVidGallery' );
function battleplan_setUpVidGallery( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'type'=>'videos', 'class'=>'', 'valign'=>'stretch', 'id'=>'', 'columns'=>'4', 'max'=>'-1', 'offset'=>'0', 'start'=>'', 'end'=>'', 'order_by'=>'date', 'order'=>'DESC', 'tax'=>'video-tags', 'terms'=>'', 'operator'=>'and', 'show_title'=>'true', 'show_date'=>'true'), $atts );
	$id = esc_attr($a['id']);
	if ( $id == '' ) global $post; $id = intval( $post->ID );
	$name = esc_attr($a['name']) == '' ? $id : esc_attr($a['name']);
	$postType = esc_attr($a['type']);
	$orderBy = esc_attr($a['order_by']);
	$showDate = esc_attr($a['show_date']);
	$showTitle = esc_attr($a['show_title']);
	$classes = esc_attr($a['class']) !== '' ? ' '.esc_attr($a['class']) : '';
	$buildArchive = '';
	$num = 1;

	$query = bp_WP_Query($postType, [
		'post_status'     => 'publish',
		'posts_per_page'  => esc_attr($a['max']),
		'order'           => esc_attr($a['order']),
		'offset'          => esc_attr($a['offset']),
		'start'           => esc_attr($a['start']),
		'end'             => esc_attr($a['end']),
		'tax' 			  => esc_attr($a['tax']),
		'terms'			  => esc_attr($a['terms']),
		'tax_operator'	  => esc_attr($a['operator']),
		'orderby'         => $orderBy
	]);

	if ( $query->have_posts() ) :
		while ( $query->have_posts() ) :
			$query->the_post();
			$thumb_url = '';
			if (has_post_thumbnail()) $thumb_url = 'thumb="'.get_the_post_thumbnail_url(get_the_ID(), 'full').'"';
			$link_url = esc_url(get_field( "link_url" ));
			if ($showDate === 'true' || $showTitle === 'true') {
				$printMeta = "<p>";
				if ($showDate === "true") $printMeta .= get_the_date().'<br>';
				if ($showTitle === "true") $printMeta .= '<b>'.get_the_title().'</b><br>';
				$printMeta .= "</p>";
			}

			$add_video = '[txt][vid link="'.$link_url.'" '.$thumb_url.']'.$printMeta.'[/txt]';
			$buildArchive .= do_shortcode('[col class="'.$postType.' '.$postType.'-'.$num.$classes.'"][build-archive type="'.get_post_type().'" show_thumb="false" show_btn="false" show_title="false" show_excerpt="false" pic_size="100" text_size="100"]'.$add_video.'[/col]');
			$num++;
		endwhile;
		wp_reset_postdata();
	endif;

	return do_shortcode('[section width="inline" class="archive-content archive-'.$postType.'"][layout grid="'.esc_attr($a['columns']).'e" valign="'.esc_attr($a['valign']).'"]'.$buildArchive.'[/layout][/section]');
}

// Build a coupon
add_shortcode( 'coupon', 'battleplan_coupon' );
function battleplan_coupon( $atts, $content = null ) {
	$a = shortcode_atts( array( 'action'=>'Mention Our Website For', 'discount'=>'$20 OFF', 'service'=>'Service Call', 'disclaimer'=>'First time customers only.  Limited time offer.  Not valid with any other offer.  Must mention coupon at time of appointment.  During regular business hours only.  Limit one coupon per system.', 'img'=>'', 'img-pos'=>'align-right', 'img-class'=>'' ), $atts );

	$image = esc_attr($a['img']);

	$coupon = '[txt class="coupon"]<div class="coupon-inner">';
	if ( $image != '' )	$coupon .= '<img src="'.wp_get_attachment_url( $image ).'" class="size-third-s '.esc_attr($a['img-pos']).' '.esc_attr($a['img-class']).'" />';
	$coupon .= '<h2 class="action">'.esc_attr($a['action']).'</h2>';
	$coupon .= '<h2 class="discount">'.esc_attr($a['discount']).'</h2>';
	$coupon .= '<h2 class="service">'.esc_attr($a['service']).'</h2>';
	$coupon .= '<p class="disclaimer">'.esc_attr($a['disclaimer']).'</p>';
	$coupon .= '</div>[/txt]';

	return do_shortcode($coupon);
}

// Add Emergency Service widget to Sidebar
add_shortcode( 'get-emergency-service', 'battleplan_getEmergencyService' );
function battleplan_getEmergencyService( $atts, $content = null ) {
	$a = shortcode_atts( array( 'graphic'=>'1' ), $atts );
	$graphic = esc_attr($a['graphic']);

	$imagePath = get_template_directory().'/common/logos/24-hr-service-'.$graphic.'.webp';
	list($width, $height) = getimagesize($imagePath);

	return '<img class="noFX" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/24-hr-service-'.$graphic.'.webp" alt="We provide 24/7 emergency service." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';
}

// Add Google Guaranteed widget to Sidebar
add_shortcode( 'get-google-guaranteed', 'battleplan_getGoogleGuaranteed' );
function battleplan_getGoogleGuaranteed( $atts, $content = null ) {
	$a = shortcode_atts( array( 'graphic'=>'1' ), $atts );
	$graphic = esc_attr($a['graphic']);

	$imagePath = get_template_directory().'/common/logos/google-guaranteed.webp';
	list($width, $height) = getimagesize($imagePath);

	return '<img class="noFX" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/google-guaranteed.webp" alt="We are proud to be Google Guaranteed." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';
}

// Add Now Hiring widget to Sidebar
add_shortcode( 'now-hiring', 'battleplan_getNowHiring' );
function battleplan_getNowHiring( $atts, $content = null ) {
	$a = shortcode_atts( array( 'graphic'=>'1', 'link'=>'career-opportunities' ), $atts );
	$graphic = esc_attr($a['graphic']);

	$imagePath = get_template_directory().'/common/logos/now-hiring-'.$graphic.'.webp';
	list($width, $height) = getimagesize($imagePath);

	return '<a href="/'.esc_url($a['link']).'"><img class="noFX" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/now-hiring-'.$graphic.'.webp" alt="We are hiring! Join our team." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" /></a>';
}

// Add BBB widget to Sidebar
add_shortcode( 'get-bbb', 'battleplan_getBBB' );
function battleplan_getBBB( $atts, $content = null ) {
	$a = shortcode_atts( array( 'link'=>'', 'graphic'=>'1' ), $atts );
	$link = esc_attr($a['link']);
	$graphic = esc_attr($a['graphic']);
	$imagePath = get_template_directory().'/common/logos/bbb-'.$graphic.'.webp';
	list($width, $height) = getimagesize($imagePath);
	$buildBBB = '';

	if ( $link !== '' ) $buildBBB = '<a href="'.esc_url($link).'" title="Click here to view our profile page on the Better Business Bureau website.">';

	$buildBBB = '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/bbb-'.$graphic.'.webp" alt="We are accredited with the BBB and are proud of our A+ rating." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';

	if ( $link !== '' ) $buildBBB = '</a>';

	return $buildBBB;
}

// Add Veteran Owned widget to Sidebar
add_shortcode( 'get-veteran-owned', 'battleplan_getVeteranOwned' );
function battleplan_getVeteranOwned( $atts, $content = null ) {
	$a = shortcode_atts( array( 'link'=>'', 'graphic'=>'1' ), $atts );
	$graphic = esc_attr($a['graphic']);

	$imagePath = get_template_directory().'/common/logos/veteran-owned-'.$graphic.'.webp';
	list($width, $height) = getimagesize($imagePath);

	return '<img loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/veteran-owned-'.$graphic.'.webp" alt="We are proud to be a Veteran Owned business." width="'.$width.'" height="'.$height.'" style="aspect-ratio:'.$width.'/'.$height.'" />';
}

// Add Credit Cards widget to Sidebar
add_shortcode( 'get-credit-cards', 'battleplan_getCreditCards' );
function battleplan_getCreditCards( $atts, $content = null ) {
	$a = shortcode_atts( array( 'mc'=>'yes', 'visa'=>'yes', 'discover'=>'yes', 'amex'=>'yes' ), $atts );

	$buildCards = '<div id="credit-cards" class="currency">';
	if ( esc_attr($a['mc']) == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-mc.webp" loading="lazy" alt="We accept Mastercard" width="100" height="62" style="aspect-ratio:100/62" />';
	if ( esc_attr($a['visa']) == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-visa.webp" loading="lazy" alt="We accept Visa" width="100" height="62" style="aspect-ratio:100/62" />';
	if ( esc_attr($a['discover']) == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-discover.webp" loading="lazy" alt="We accept Discover" width="100" height="62" style="aspect-ratio:100/62" />';
	if ( esc_attr($a['amex']) == "yes" ) $buildCards .= '<img class="credit-card-logo" src="/wp-content/themes/battleplantheme/common/logos/cc-amex.webp" loading="lazy" alt="We accept American Express" width="100" height="62" style="aspect-ratio:100/62" />';
	$buildCards .= '</div>';

	return $buildCards;
}

// Add Crypto Currency widget to Sidebar
add_shortcode( 'get-crypto', 'battleplan_getCrypto' );
function battleplan_getCrypto( $atts, $content = null ) {
	$a = shortcode_atts( array( 'include'=>'bitcoin cardano chainlink dogecoin monero polygon stellar ethereum shibainu sand icp xpr' ), $atts );
	$cryptos = explode(" ", esc_attr($a['include']) );

	$buildCrypto = '<div id="crypto" class="currency">';

	foreach ( $cryptos as $crypto ) :
		$buildCrypto .= '<img class="crypto-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-'.$crypto.'.webp" alt="We accept '.$crypto.' crypto currency" width="100" height="100" style="aspect-ratio:100/100" />';
	endforeach;

	$buildCrypto .= '</div>';

	return $buildCrypto;
}

// Add Cash Apps widget to Sidebar
add_shortcode( 'get-cashapps', 'battleplan_getCashApps' );
function battleplan_getCashApps( $atts, $content = null ) {
	$a = shortcode_atts( array( 'include'=>'zelle venmo cashapp' ), $atts );
	$apps = explode(" ", esc_attr($a['include']) );

	$buildApps = '<div id="cashapps" class="currency">';

	foreach ( $apps as $app ) :
		$buildApps .= '<img class="cashapp-logo" loading="lazy" src="/wp-content/themes/battleplantheme/common/logos/cc-'.$app.'.webp" alt="We accept '.$app.' payments" width="100" height="100" style="aspect-ratio:100/100" />';
	endforeach;

	$buildApps .= '</div>';

	return $buildApps;
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
	$a = shortcode_atts( array( 'img'=>'', 'size'=>'half-s', 'gap'=>'', 'align'=>'center', 'full'=>'', 'pos'=>'bottom', 'break'=>'none', 'class'=>''), $atts );
	$size = esc_attr($a['size']);
	$gap = esc_attr($a['gap']);
	$gap = $gap !== '' ? ' style="gap: '.$gap.'"' : '';
	$class = esc_attr($a['class']) == '' ? '' : ' '.esc_attr($a['class']).' ';
	$break = esc_attr($a['break']) == "none" ? ' break-none' : ' break-'.esc_attr($a['break']);
	$align = "align".esc_attr($a['align']);
	$images = explode(',', esc_attr($a['img']));

	$buildFlex = '<ul class="side-by-side '.$class.$align.$break.'"'.$gap.'>';
	for ($i = 0; $i < count($images); $i++) :
		$imgID = trim($images[$i]);
		$img = wp_get_attachment_image_src( $imgID, $size );

		list ($src, $width, $height ) = $img;
		$liClass = $imgID === esc_attr($a['full']) ? ' class="full-'.esc_attr($a['pos']).'" ' : '';
		if ($height > 0) $ratio = $width / $height;
		$buildFlex .= '<li style="flex: '.$ratio.'"'.$liClass.'>'.wp_get_attachment_image( $imgID, $size, false, ["class" => 'wp-image-'.$imgID.$class] ).'</li>';
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

// Use page template for landing & universal pages
add_filter('single_template', 'battleplan_usePageTemplate', 10, 1 );
function battleplan_usePageTemplate( $original ) {
	global $post;
	$post_type = $post->post_type;
	if ( $post_type == "landing" || $post_type == "universal" ) return locate_template('page.php');
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
	   		$buildArchive .= '<div id="'.$hash.'"><h2><a href="'.esc_url($permalink).'" title="'.$title.'">'.$title.'</a></h2><div class="meta-data">'.$date.'</div>';
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
 	 bp_enqueue_script( 'battleplan-count-up', 'script-count-up', ['jquery'] );

	 $a = shortcode_atts( array( 'name'=>'', 'start'=>'0', 'end'=>'0', 'decimals'=>'0', 'duration'=>'5', 'delay'=>'0', 'waypoint'=>'85%', 'easing'=>'easeOutExpo', 'grouping'=>'true', 'separator'=>',', 'decimal'=>'.', 'prefix'=>'', 'suffix'=>'' ), $atts );
	 $id = strtolower(esc_attr($a['name']));
	 $id = str_replace('-', '_', $id);
	 $id = str_replace(' ', '_', $id);
	 $delay = esc_attr($a['delay']) * 1000;
	 $start = esc_attr($a['start']);

	 if ( !ctype_digit($start)) {
		$start = do_shortcode('[get-biz info="' . $start . '"]');
	 }

	 if (substr($start, 0, 1) === '{') $start = do_shortcode(str_replace( array("{","}","&#039;","&quot;"), array("[","]","'","'"), $start ));

	 $end = esc_attr($a['end']);

	 if ( !ctype_digit($end)) {
		$end = do_shortcode('[get-biz info="' . $end . '"]');
	 }

	 if (substr($end, 0, 1) === '{') $end = do_shortcode(str_replace( array("{","}","&#039;","&quot;"), array("[","]","'","'"), $end ));

	 $buildCountUp = '<div class="count-up" data-easing="'.esc_attr($a['easing']).'" data-grouping="'.esc_attr($a['grouping']).'" data-separator="'.esc_attr($a['separator']).'" data-decimal="'.esc_attr($a['decimal']).'" data-prefix="'.esc_attr($a['prefix']).'" data-suffix="'.esc_attr($a['suffix']).'" data-duration="'.esc_attr($a['duration']).'" data-start="'.$start.'" data-end="'.$end.'">';

	 $buildCountUp .= '<span id="'.$id.'" style="white-space:pre;"></span></div>';

	 return $buildCountUp;
}

// Display Count-Down widget
 add_shortcode("get-countdown", "battleplan_countDown");
 function battleplan_countDown($atts, $content) {
	bp_enqueue_script( 'battleplan-count-down', 'script-count-down', ['jquery'] );

	$a = shortcode_atts( array( 'month'=>'', 'date'=>'', 'year'=>'', 'hour'=>'', 'minute'=>'', 'separator'=>', ', 'offset'=>'0', 'class'=>'' ), $atts );

	$buildCountDown = '<div id="countdown">';
	$buildCountDown .= '<span data-separator="'.esc_attr($a['separator']).'" data-offset="'.esc_attr($a['offset']).'" data-year="'.esc_attr($a['year']).'" data-month="'.esc_attr($a['month']).'" data-day="'.esc_attr($a['date']).'" data-hour="'.esc_attr($a['hour']).'" data-minute="'.esc_attr($a['minute']).'" id="bp_countdown_days" class="'.esc_attr($a['class']).'"></span>';
	$buildCountDown .= '<span id="bp_countdown_hours" class="'.esc_attr($a['class']).'"></span>';
	$buildCountDown .= '<span id="bp_countdown_minutes" class="'.esc_attr($a['class']).'"></span>';
	$buildCountDown .= '<span id="bp_countdown_seconds" class="'.esc_attr($a['class']).'"></span>';
	$buildCountDown .= '</div>';

	return $buildCountDown;
}

// Insert the city / state of either company address, or the city-specific landing page
add_shortcode("get-location", "battleplan_getLocation");
function battleplan_getLocation($atts) {
	$a = shortcode_atts(
		array(
			'state'   => 'true',
			'default' => 'blank',
			'before'  => '',
			'after'   => ''
		),
		$atts
	);

	$customer_info = customer_info();
	$userLoc = bp_get_user_display_loc();

	if ( !$userLoc ) {
		$userLoc = ($a['default'] !== 'blank')
			? esc_attr($a['default'])
			: $customer_info['default-loc'];
	}

	if ( $a['state'] === "false" ) {
		$userLoc = preg_replace('/,\s*[A-Z]{2}$/', '', $userLoc);
	}

	return esc_attr($a['before']).esc_html($userLoc).esc_attr($a['after']);
}


// Copy the section from the home page, or any other defined page
 add_shortcode("copy-content", "battleplan_copyContent");
 function battleplan_copyContent($atts, $content) {
	 $a = shortcode_atts( array( 'slug'=>'home', 'section'=>'page-bottom' ), $atts );
	 $slug = esc_attr($a['slug']) == 'home' ? get_option('page_on_front') : url_to_postid(esc_attr($a['slug']));
	 $section = strtolower(esc_attr($a['section']));

	 if ( $section == 'page top' || $section == 'page-top' || $section == 'top' || $section == 'wrapper-top') : $section_content = get_post_meta($slug, 'page-top_text', true);
	 elseif ( $section == 'page bottom' || $section == 'page-bottom' || $section == 'bottom' || $section == 'wrapper-bottom') : $section_content = get_post_meta($slug, 'page-bottom_text', true);
	 else: $section_content = get_post_field('post_content', $slug); endif;

	 return apply_filters('the_content', $section_content);
}

// Add multi-step form functionality
add_shortcode( 'cf7-steps', 'battleplan_cf7Steps' );
function battleplan_cf7Steps($atts, $content = null) {
	$a = shortcode_atts( array( 'title'=>'' ), $atts );
	$title = esc_attr($a['title']);

	if ( $title !== '' ) bp_enqueue_script( 'battleplan-form-steps', 'script-forms', ['jquery'] );

	$buildForm = '<div class="cf7-steps" data-current="0">';
	$buildForm .= '[contact-form-7 title="'.$title.'"]';
	$buildForm .= '</div>';

	return do_shortcode($buildForm);
}

// Debug log viewer shortcode with clear + reload buttons
add_shortcode('show_debug_log', function($atts) {

	// optional: restrict to admins only
	// if (!current_user_can('manage_options')) return '';

	// allow ?admin=true OR shortcode attribute
	$is_admin_log = (
		(isset($_GET['admin']) && $_GET['admin'] === 'true') ||
		(isset($atts['admin']) && $atts['admin'] === 'true')
	);

	$logfile = WP_CONTENT_DIR . '/' . ($is_admin_log ? 'debug-admin.log' : 'debug.log');


	/*--------------------------------------------------------------
	# Handle clear request
	--------------------------------------------------------------*/

	$message = '';

	if (
		isset($_POST['bp_clear_debug_log']) &&
		isset($_POST['bp_debug_nonce']) &&
		wp_verify_nonce($_POST['bp_debug_nonce'], 'bp_clear_debug_log_action')
	) {

		if (file_exists($logfile)) {
			file_put_contents($logfile, '');
		}

		$message = '<div style="color:green;font-weight:bold;margin-bottom:10px;">Log cleared.</div>';

	}


	/*--------------------------------------------------------------
	# Build reload URLs
	--------------------------------------------------------------*/

	$current_url =
		(is_ssl() ? 'https://' : 'http://') .
		$_SERVER['HTTP_HOST'] .
		wp_unslash($_SERVER['REQUEST_URI']);

	$base_url  = remove_query_arg('admin', $current_url);
	$admin_url = add_query_arg('admin', 'true', $base_url);


	/*--------------------------------------------------------------
	# Buttons
	--------------------------------------------------------------*/

	$buttons = $message . '
	<div style="margin-bottom:10px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">

		<form method="post" style="margin:0;">
			<input type="hidden"
				name="bp_debug_nonce"
				value="' . esc_attr(wp_create_nonce('bp_clear_debug_log_action')) . '">
			<button type="submit"
				name="bp_clear_debug_log"
				value="1"
				style="padding:6px 12px;font-size:13px;cursor:pointer;">
				Clear Log
			</button>
		</form>

		<button type="button"
			onclick="window.location.href=\'' . esc_url($base_url) . '\';"
			style="padding:6px 12px;font-size:13px;cursor:pointer;">
			Reload Debug
		</button>

		<button type="button"
			onclick="window.location.href=\'' . esc_url($admin_url) . '\';"
			style="padding:6px 12px;font-size:13px;cursor:pointer;">
			Reload Debug Admin
		</button>

	</div>
	';


	/*--------------------------------------------------------------
	# Load file
	--------------------------------------------------------------*/

	if (!file_exists($logfile)) {
		return $buttons . '<p>No log file found.</p>';
	}

	$contents = file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	if (!$contents) {
		return $buttons . '<p>Log is empty.</p>';
	}


	/*--------------------------------------------------------------
	# Filter lines
	--------------------------------------------------------------*/

	$exclude = [
		'wpseo-local',
		'wordpress-seo-premium',
		'_load_textdomain_just_in_time',
		'auditor:scan=fingerprint',
		'wp-cron.php',
		'wp-json/',
		'wp-includes/'
	];

	$contents = array_filter($contents, function($line) use ($exclude) {
		foreach ($exclude as $word) {
			if (stripos($line, $word) !== false) return false;
		}
		return true;
	});


	/*--------------------------------------------------------------
	# Format output
	--------------------------------------------------------------*/

	$recent = array_slice($contents, -200);
	$recent = array_reverse($recent);

	$recent = array_map(function($line) {

		$line = esc_html($line);

		$line = str_ireplace(
			'PHP Fatal error',
			'<span style="color:red;font-weight:bold;">PHP Fatal error</span>',
			$line
		);

		return $line;

	}, $recent);

	$output = nl2br(implode("\n\n", $recent), false);


	/*--------------------------------------------------------------
	# Return final output
	--------------------------------------------------------------*/

	return $buttons . '
	<div style="
		font-family:monospace;
		font-size:13px;
		line-height:1.5;
		background:#fafafa;
		border:1px solid #ccc;
		padding:10px;
		white-space:normal;
	">'
	. $output .
	'</div>';

});
