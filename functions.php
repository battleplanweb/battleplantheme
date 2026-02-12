<?php
/* Battle Plan Web Design Functions: Main

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Functions to extend WordPress
# Basic Theme Set Up
# User Roles
# Custom Hooks
# Custom Actions

*/

require_once get_template_directory().'/functions-global.php';

/*--------------------------------------------------------------
# Functions to extend WordPress
--------------------------------------------------------------*/

// Handle WP_Queries properly
function bp_WP_Query($post_type, $args = []) {
	$defaults = [
		'post_type'              => $post_type,
		'post_status'            => 'publish',
		'posts_per_page'         => 4,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'cache_results'          => false,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false
	];

	$args = array_merge($defaults, $args);

	if (!empty($args['orderby']) && ($args['orderby'] === 'recent' || stripos($args['orderby'], 'view') !== false || $args['orderby'] === 'rand')) {
		$ids = get_posts([
			'post_type'      => $post_type,
			'post_status'    => $args['post_status'],
			'posts_per_page' => -1,
			'fields'         => 'ids'
		]);

		if (!empty($ids)) {
			shuffle($ids);
			$args['post__in'] = $ids; // Use full list
			$args['orderby'] = 'post__in';
		}
	}

	$tax = $args['taxonomy'] ?? $args['tax'] ?? null;
	$terms = $args['terms'] ?? $args['term'] ?? null;

	if (!empty($tax) && !empty($terms)) {
		$terms = is_string($terms) ? array_map('trim', explode(',', $terms)) : (array) $terms;
		$operator = strtolower($args['tax_operator'] ?? 'and') === 'or' ? 'OR' : 'AND';
		$args['tax_query'] = ['relation' => $operator];
		foreach ($terms as $term) {
			$args['tax_query'][] = [
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => [$term]
			];
		}
		unset($args['taxonomy'], $args['terms'], $args['tax'], $args['term'], $args['tax_operator']);
	}

	if (!empty($args['tax_multi']) && is_array($args['tax_multi'])) {
		$tax_query = ['relation' => $args['tax_relation'] ?? 'OR'];
		foreach ($args['tax_multi'] as $tax => $terms) {
			foreach ((array) $terms as $term) {
				$tax_query[] = [
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => [$term]
				];
			}
		}
		$args['tax_query'] = $tax_query;
		unset($args['tax_multi'], $args['tax_relation']);
	}

	if (!empty($args['start']) || !empty($args['end'])) {
		$args['date_query'] = [[
			'after'     => $args['start'] ?? null,
			'before'    => $args['end'] ?? null,
			'inclusive' => true
		]];
		unset($args['start'], $args['end']);
	}

	if (!empty($args['mime_type'])) {
		$args['post_mime_type'] = $args['mime_type'];
		unset($args['mime_type']);
	}

	return new WP_Query($args);
}


// Print variable or array for debugging
function showMe($something, $die = false, $echo = true) {
	$print = '';

	if (is_array($something)) {
		$something = array_slice($something, 0, 250);
		$print = print_r($something, true);
	} else {
		$print = (string) $something;
	}

	if ($die) {
		wp_die($print);
	}

	$print =  '<details style="margin-top:50px"><summary style="cursor:pointer">Customer Info (debug)</summary><pre style="width: 100%; max-width: 1000px; max-height:220px; overflow:auto; margin:10px 0 0;">'.$print.'</pre></details>';

	if ($echo) {
		echo $print;
	}

	return $print;
}


// Send an email to myself
function emailMe($subject, $htmlMessage, $replyTo = null) {
    $to   = 'info@battleplanwebdesign.com';
    $from = 'no-reply@battleplanwebdesign.com';

    $subject = trim(preg_replace('/\r|\n/', '', (string) $subject));

	if (function_exists('mb_encode_mimeheader')) {
        $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    }

	$replyTo = filter_var($replyTo, FILTER_VALIDATE_EMAIL) ?: $from;

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Battle Plan Web Design <' . $from . '>',
        'Reply-To: ' . $replyTo,
        'X-Mailer: PHP/' . phpversion(),
    ];
    $headersStr = implode("\r\n", $headers);

    $additionalParams = '-f ' . escapeshellarg($from);

    return mail($to, $subject, (string) $htmlMessage, $headersStr, $additionalParams);
}


// Check if current page is log in screen
function is_wplogin() {
    $ABSPATH_MY = str_replace(array('\\','/'), DIRECTORY_SEPARATOR, ABSPATH);
    return ((in_array($ABSPATH_MY.'wp-login.php', get_included_files()) || in_array($ABSPATH_MY.'wp-register.php', get_included_files()) ) || (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') || $_SERVER['PHP_SELF']== '/wp-login.php');
}

// Check if user is on a mobile device
function is_mobile() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

// Check if business is currently open
function bp_build_open_intervals(array $periods, string $tz, int $horizonDays=14): array {
	$tzobj=new DateTimeZone($tz);
	$parseDate=function($d) use($tzobj){
		if ($d instanceof DateTimeInterface) return ['year'=>(int)$d->format('Y'),'month'=>(int)$d->format('n'),'day'=>(int)$d->format('j')];
		if (is_array($d) && isset($d['year'],$d['month'],$d['day'])) return ['year'=>(int)$d['year'],'month'=>(int)$d['month'],'day'=>(int)$d['day']];
		if (!is_string($d)) return null; $s=trim($d);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$s)) { [$y,$m,$da]=array_map('intval',explode('-',$s)); return ['year'=>$y,'month'=>$m,'day'=>$da]; }
		if (preg_match('/^\d{8}$/',$s)) { $y=(int)substr($s,0,4); $m=(int)substr($s,4,2); $da=(int)substr($s,6,2); return ['year'=>$y,'month'=>$m,'day'=>$da]; }
		if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/',$s)) { $dt=DateTimeImmutable::createFromFormat('n/j/Y',$s,$tzobj); return $dt?['year'=>(int)$dt->format('Y'),'month'=>(int)$dt->format('n'),'day'=>(int)$dt->format('j')]:null; }
		if (ctype_digit($s)) { $dt=(new DateTimeImmutable('@'.$s))->setTimezone($tzobj); return ['year'=>(int)$dt->format('Y'),'month'=>(int)$dt->format('n'),'day'=>(int)$dt->format('j')]; }
		$ts=strtotime($s); if ($ts!==false) { $dt=(new DateTimeImmutable('@'.$ts))->setTimezone($tzobj); return ['year'=>(int)$dt->format('Y'),'month'=>(int)$dt->format('n'),'day'=>(int)$dt->format('j')]; }
		return null;
	};
	$norm=function($x) use (&$norm,$parseDate){
		if (is_string($x)) {
			$s=trim($x); $sl=strtolower($s);
			if ($sl===''||$sl==='closed') return null;
			if (in_array($sl,['24/7','24x7','247'],true)) return ['open'=>['time'=>'0000'],'close'=>['time'=>'2359']];
			if (strpos($s,'-')!==false) { [$o,$c]=array_map('trim',explode('-',$s,2)); $o=preg_replace('/[^0-9]/','',$o); $c=preg_replace('/[^0-9]/','',$c); $o=str_pad((string)$o,4,'0',STR_PAD_LEFT); $c=str_pad((string)$c,4,'0',STR_PAD_LEFT); return ['open'=>['time'=>$o],'close'=>['time'=>$c]]; }
			return null;
		}
		if (is_array($x)) {
			if (isset($x['open'])&&is_string($x['open'])) { $o=$norm($x['open']); $x['open']=$o['open']??[]; }
			if (isset($x['close'])&&is_string($x['close'])) { $c=$norm($x['close']); $x['close']=$c['close']??[]; }
			if (isset($x['open']['date']))  $x['open']['date']=$parseDate($x['open']['date']);
			if (isset($x['close']['date'])) $x['close']['date']=$parseDate($x['close']['date']);
			return $x;
		}
		return null;
	};
	$_tmp=[]; foreach($periods as $p){ $p=$norm($p); $p&&$_tmp[]=$p; } $periods=$_tmp;

	$now=new DateTimeImmutable('now',$tzobj);
	$out=[];

	$dated=[]; $weekly=[];
	foreach($periods as $p) (!empty($p['open']['date'])||!empty($p['close']['date'])) ? $dated[]=$p : $weekly[]=$p;

	// DATED
	foreach($dated as $p){
		$o=$p['open']??[]; $c=$p['close']??[];
		if (empty($o['date'])) continue;
		$ot=$o['time']??null;
		$oh=isset($o['hour'])?(int)$o['hour']:(isset($ot)&&strlen($ot)>=2?(int)substr($ot,0,2):null);
		$om=isset($o['minute'])?(int)$o['minute']:(isset($ot)&&strlen($ot)>=4?(int)substr($ot,2,2):0);
		if ($oh===null) continue;
		$od=is_array($o['date'])?$o['date']:$parseDate($o['date']); if(!$od) continue;
		$start=new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:00',$od['year'],$od['month'],$od['day'],$oh,$om),$tzobj);

		if ($c) {
			$ct=$c['time']??null;
			if (!empty($c['date'])) { $cd=is_array($c['date'])?$c['date']:$parseDate($c['date']); if(!$cd) $cd=['year'=>(int)$start->format('Y'),'month'=>(int)$start->format('m'),'day'=>(int)$start->format('d')];
				$ch=isset($c['hour'])?(int)$c['hour']:(isset($ct)&&strlen($ct)>=2?(int)substr($ct,0,2):0);
				$cm=isset($c['minute'])?(int)$c['minute']:(isset($ct)&&strlen($ct)>=4?(int)substr($ct,2,2):0);
				$end=new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:00',$cd['year'],$cd['month'],$cd['day'],$ch,$cm),$tzobj);
			} else {
				$ch=isset($c['hour'])?(int)$c['hour']:(isset($ct)&&strlen($ct)>=2?(int)substr($ct,0,2):0);
				$cm=isset($c['minute'])?(int)$c['minute']:(isset($ct)&&strlen($ct)>=4?(int)substr($ct,2,2):0);
				$end=$start->setTime($ch,$cm);
			}
		} else $end=$start->modify('+1 day');

		($end <= $start) && ($end=$end->modify('+1 day'));
		$out[]=['start'=>$start->getTimestamp(),'end'=>$end->getTimestamp()];
	}

	// WEEKLY → project next N days
	$today0=$now->setTime(0,0);
	for($d=0;$d<$horizonDays;$d++){
		$dayDate=$today0->modify("+$d days");
		$dow=(int)$dayDate->format('N'); // 1..7 Mon..Sun
		foreach($weekly as $p){
			$o=$p['open']??[]; $c=$p['close']??[];
			if (!isset($o['day'])) continue;
			$openDay=(int)$o['day']; $openDay===0 && ($openDay=7);
			if ($openDay!==$dow) continue;

			$ot=$o['time']??null;
			$oh=isset($o['hour'])?(int)$o['hour']:(isset($ot)&&strlen($ot)>=2?(int)substr($ot,0,2):null);
			$om=isset($o['minute'])?(int)$o['minute']:(isset($ot)&&strlen($ot)>=4?(int)substr($ot,2,2):0);
			if ($oh===null) continue;
			$start=$dayDate->setTime($oh,$om);

			if ($c) {
				$ct=$c['time']??null;
				$ch=isset($c['hour'])?(int)$c['hour']:(isset($ct)&&strlen($ct)>=2?(int)substr($ct,0,2):null);
				$cm=isset($c['minute'])?(int)$c['minute']:(isset($ct)&&strlen($ct)>=4?(int)substr($ct,2,2):0);
				if (isset($c['day'])) {
					$closeDay=(int)$c['day']; $closeDay===0 && ($closeDay=7);
					$delta=$closeDay-$openDay; $delta<0 && ($delta+=7);
					$end=$start->modify("+$delta days")->setTime($ch??0,$cm??0);
				} else $end=$start->setTime($ch??0,$cm??0);
			} else $end=$start->modify('+1 day');

			($end <= $start) && ($end=$end->modify('+1 day'));
			$out[]=['start'=>$start->getTimestamp(),'end'=>$end->getTimestamp()];
		}
	}

	return $out;
}



function bp_is_open_at(array $periods,string $tz,?int $ts=null): bool {
    $now = $ts ?? (new DateTimeImmutable('now', new DateTimeZone($tz)))->getTimestamp();
    foreach (bp_build_open_intervals($periods, $tz) as $iv) {
        if ($iv['start'] <= $now && $now < $iv['end']) return true;
    }
    return false;
}

function bp_next_change_at(array $periods,string $tz,?int $ts=null): ?array {
	$now=$ts ?? (new DateTimeImmutable('now',new DateTimeZone($tz)))->getTimestamp();
	foreach (bp_build_open_intervals($periods,$tz) as $iv) {
		if ($iv['start']<=$now && $now<$iv['end']) return ['state'=>'close','at'=>$iv['end']];
		if ($now<$iv['start']) return ['state'=>'open','at'=>$iv['start']];
	}
	return null;
}

function is_biz_open(string $businessTz=null): bool {
	$customer_info = customer_info();

	if (empty($customer_info['pid-sync']) || $customer_info['pid-sync'] === false || $customer_info['pid-sync'] === "false") {
		return false;
	}

	$info=get_option('bp_gbp_update') ?: [];
	$ids=$customer_info['pid'] ?? 0; $ids=is_array($ids)?$ids:[$ids];
	$pid=$ids[0] ?? 0; if (!$pid) return false;

	$tz=$businessTz ?: wp_timezone_string(); // ← prefer per-location TZ if you have it
	$hours=$info[$pid]['current-hours'] ?? ($info[$pid]['hours'] ?? []);
	$periods=$hours['periods'] ?? [];

	return $periods ? bp_is_open_at($periods,$tz) : false;
}

// Get slug of current page --- can also use _PAGE_SLUG (slug only) and _PAGE_SLUG_FULL (slug + preceding directories)
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
function getID($slug, $type=null) {
	$getCPT = $type ? array($type) : getCPT();
	$id = url_to_postid($slug);

	if ( $id != 0 ) return $id;

	foreach ($getCPT as $postType) :
		$page = get_page_by_path($slug, OBJECT, $postType);
		if ( $page ) return $page->ID;
	endforeach;

	foreach ($getCPT as $postType) :
		$query = bp_WP_Query($postType, [
			'name'           => $slug,
			'post_status'    => 'all',
			'posts_per_page' => 1
		]);

		if ( !empty( $query->posts ) ) :
			$page = $query->posts[0];
			return $page->ID;
		endif;
	endforeach;

	//getID($slug);
}

// Identify user based on id, email or slug
function battleplan_identifyUser( $identifier='' ) {
	if ( $identifier == null || $identifier == "" ) : return wp_get_current_user();
	elseif ( is_numeric($identifier) ) : return get_user_by('id', $identifier);
	elseif ( strpos($identifier, '@') !== false ) : return get_user_by('email', $identifier);
	else: return get_user_by('slug', $identifier);
	endif;
}

function battleplan_getUserRole( $identifier='', $info='' ) {
	$user = battleplan_identifyUser( $identifier );
	$userMeta = get_userdata($user->ID);
	if ( $userMeta ) :
		$userRoles = $userMeta->roles;
		global $wp_roles;
		$userRoleName = $userRoleDisplay = $userRoleCaps = "";
		if ( is_array($userRoles) ) :
			foreach ($userRoles as $userRole) :
				$userRoleName .= $userRole;
				$userRoleDisplay .= $wp_roles->roles[$userRole]['name'];
				$userRoleCaps .= print_r($user->get_role_caps(), true);
			endforeach;
		else:
				$userRoleName = $userRoles;
				$userRoleDisplay = $wp_roles->roles[$userRoles]['name'];
				$userRoleCaps = $user->get_role_caps();
		endif;

		if ( $info == "" || $info == "name" ) return $userRoleName;
		if ( $info == "display" ) return $userRoleDisplay;
		if ( $info == "caps" || $info == "capabilities" ) return $userRoleCaps;
	endif;
}

// Add data-{key}="{value}" to an image based on its custom fields
function getImgMeta($id) {
	$custom = get_post_custom( $id );
	if ( ! is_array( $custom ) ) return;
	if ( $keys = array_keys( $custom ) ) :
		$addMeta = "";
		foreach ($keys as $key) :
			if ( $key != "log-views" ) :
				$value = esc_attr(get_field( $key, $id));
				if ( substr($value, 0, 5) != "field" && !is_array($value) && $value != "" && $value != null && $value != "Array" ) :
					$key = ltrim($key, '_');
					$key = ltrim($key, '-');
					$addMeta .= ' data-'.$key.' = "'.$value.'"';
				endif;
			endif;
		endforeach;
		return $addMeta;
	endif;
}

// Read meta in custom field
function readMeta($id, $key, $single=true) {
	return get_post_meta( $id, $key, $single );
}

// Update meta in custom field
function updateMeta($id, $key, $value) {
	if ( !add_post_meta( $id, $key, $value, true ) ) update_post_meta( $id, $key, $value );
}

// Delete custom field
function deleteMeta($id, $key) {
	delete_post_meta( $id, $key );
}

// Delete site option, then update it -- to ensure it won't be cached
function updateOption($option, $value, $autoload=null) {
	delete_option($option);
	update_option($option, $value, $autoload);
}

function getCPT() {
	$getCPT = get_post_types();
	$removeCPT = array('acf-field', 'acf-field-group', 'asp_coupons', 'asp-products', 'attachment', 'customize_changeset', 'custom_css', 'nav_menu_item', 'oembed_cache', 'revision', 'stripe_order', 'user_request', 'wpcf7_contact_form', 'wp_block', 'wp_global_styles', 'wphb_minify_group', 'wp_navigation', 'wp_template', 'wp_template_part');
	$moveCPTs = array ('landing', 'page', 'universal', 'elements');

	foreach ($removeCPT as $remove) unset($getCPT[$remove]);

	foreach ( $moveCPTs as $moveCPT ) :
		unset($getCPT[$moveCPT]);
		array_unshift($getCPT, $moveCPT);
	endforeach;

	return $getCPT;
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

// Display time elapsed since a UNIX stamp
function timeElapsed($time, $precision = 5, $display="all", $abbr="none") { // precision= 5, 4, 3, 2, 1, 0 (only use 0 if NOT using display=all)   // display=all, months, days, hours, minutes, seconds    // abbr=none, short (min sec hr), full (m s h)
	$time = time() - intval($time);
	$buildTime = '';

	if ( $abbr == "full" ) :
		$month = $months = 'M';
		$day = $days = 'd';
		$hour = $hours = 'h';
		$minute = $minutes = 'm';
		$second = $seconds = 's';
	elseif ( $abbr == "short" ) :
		$month = $months = 'mo';
		$day = $days = 'day';
		$hour = $hours = 'hr';
		$minute = $minutes = 'min';
		$second = $seconds = 'sec';
	else:
		$month = 'month'; $months = 'months';
		$day = 'day'; $days = 'days';
		$hour = 'hour'; $hours = 'hours';
		$minute = 'minute'; $minutes = 'minutes';
		$second = 'second'; $seconds = 'seconds';
	endif;

	if ( $display == "month" || $display == "months" ) :
		return number_format(($time/2592000)?? 0.0, $precision).' '.(number_format(($time/2592000)?? 0.0, $precision) == 1 ? $month : $months);
	elseif ( $display == "day" || $display == "days" ) :
		return number_format(($time/86400)?? 0.0, $precision).' '.(number_format(($time/86400)?? 0.0, $precision) == 1 ? $day : $days);
	elseif ( $display == "hour" || $display == "hours" ) :
		return number_format(($time/3600)?? 0.0, $precision).' '.(number_format(($time/3600)?? 0.0, $precision) == 1 ? $hour : $hours);
	elseif ( $display == "minute" || $display == "minutes" ) :
		return number_format(($time/60)?? 0.0, $precision).' '.(number_format(($time/60)?? 0.0, $precision) == 1 ? $minute : $minutes);
	elseif ( $display == "second" || $display == "seconds" ) :
		return number_format($time?? 0.0, $precision).' '.(number_format($time?? 0.0, $precision) == 1 ? $second : $seconds);
	else:
		$s = $time%60;
		$m = floor(($time%3600)/60);
		$h = floor(($time%86400)/3600);
		$d = floor(($time%2592000)/86400);
		$M = floor($time/2592000);

		$timeElapsed = array( 'month'=>'', 'day'=>'', 'hour'=>'', 'minute'=>'', 'second'=>'' );

		if ( $M > 0 ) $timeElapsed['month'] = $M.' '.$month;
		if ( $d > 0 ) $timeElapsed['day'] = $d.' '.$day;
		if ( $h > 0 ) $timeElapsed['hour'] = $h.' '.$hour;
		if ( $m > 0 ) $timeElapsed['minute'] = $m.' '.$minute;
		if ( $s > 0 ) $timeElapsed['second'] = $s.' '.$second;

		if ( $M > 1 ) $timeElapsed['month'] = $M.' '.$months;
		if ( $d > 1 ) $timeElapsed['day'] = $d.' '.$days;
		if ( $h > 1 ) $timeElapsed['hour'] = $h.' '.$hours;
		if ( $m > 1 ) $timeElapsed['minute'] = $m.' '.$minutes;
		if ( $s > 1 ) $timeElapsed['second'] = $s.' '.$seconds;

		$timeElapsed = array_filter($timeElapsed);
		return implode(', ', array_slice($timeElapsed, 0, $precision));
	endif;
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
	else : return "100";
	endif;
}

// Set up function to print contents of an array
function printArray($array) {
	$print = "";
	for ($i = 0; $i < count($array); $i++) $print .= $array[$i];
	return $print;
}

// Set up function to add / remove terms on post in front end
function adjustTerms( $post_id, $term, $taxonomy, $add_or_remove ) {
	if ( ! is_numeric( $term ) ) :
		$term = get_term( $term, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) return false;
		$term_id = $term->term_id;
	else :
		$term_id = $term;
	endif;
	$new_terms = array();
	$today_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
	foreach ( $today_terms as $today_term ) :
		if ( $today_term != $term_id ) $new_terms[] = intval( $today_term );
	endforeach;
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
	if ( isset( $_POST['menu_restrict_max'][$item_id]  ) ) :
		$sanitized_data = sanitize_text_field( $_POST['menu_restrict_max'][$item_id] );
		updateMeta( $item_id, 'bp_menu_restrict_max', $sanitized_data );
	else:
		deleteMeta( $item_id, 'bp_menu_restrict_max' );
	endif;
	if ( isset( $_POST['menu_restrict_min'][$item_id]  ) ) :
		$sanitized_data = sanitize_text_field( $_POST['menu_restrict_min'][$item_id] );
		updateMeta( $item_id, 'bp_menu_restrict_min', $sanitized_data );
	else:
		deleteMeta( $item_id, 'bp_menu_restrict_min' );
	endif;
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

			foreach ( $items as $item ) :
				if ( is_array($item->classes) && in_array($type, $item->classes) ) $parent_item_id = $item->ID;
			endforeach;

			$args = array ( 'numberposts'=>$max, 'offset'=>0, 'category'=>'', 'orderby'=>$orderby, 'order'=>$seq, 'post_type'=>$type, 'suppress_filters'=>true, );

			foreach ( get_posts( $args ) as $post ) :
				$post->menu_item_parent = $parent_item_id;
				$post->post_type = 'nav_menu_item';
				$post->object = 'custom';
				$post->type = 'custom';
				$post->menu_order = ++$menu_order;
				$post->title = $post->post_title;
				$post->url = get_permalink( $post->ID );
				array_push($child_items, $post);
			endforeach;
			return array_merge( $items, $child_items );
		}, 10, 3);
	endforeach;
}

// Truncate text
function truncateText($string, $limit="250", $break=" ", $pad="...") {
	if (strlen($string) <= $limit) return $string;
  	if (($breakpoint = strpos($string, $break, $limit)) !== false ) :
    	if ($breakpoint < strlen($string) - 1) :
      		$string = substr($string, 0, $breakpoint).$pad;
   		endif;
  	endif;
  	return $string;
}

// Remove sidebar from specific pages
function battleplan_remove_sidebar( $classes ) {
	$classes = str_replace(array('sidebar-line', 'sidebar-right', 'sidebar-left'), 'sidebar-none', $classes);
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
	// Always remove sidebar on /debug page
	if ( is_page('debug') || trim($_SERVER['REQUEST_URI'], '/') === 'debug' || readMeta( get_the_ID(), '_bp_remove_sidebar', true ) ) {
		return battleplan_remove_sidebar( $classes );
	}

	return $classes;
}

// If post is a "landing" page, add .home to body class for CSS purposes
add_filter( 'body_class', 'battleplan_addHomeBodyClassToOptimized', 70 );
function battleplan_addHomeBodyClassToOptimized( $classes ) {
	$jobsite_geo = get_option('jobsite_geo');
	if ( is_array($jobsite_geo) && ($jobsite_geo['install'] ?? null) === 'true' )  return $classes;
	if ( get_post_type() == "landing" && preg_match ('/, [A-Z]{2}$/', get_the_title() ) === 1 ) array_push($classes, 'home', 'alt-home');
	return $classes;
}

// If search page, remove .home from body class
add_filter( 'body_class', 'battleplan_removeHomeBodyClassOnSearch', 80 );
function battleplan_removeHomeBodyClassOnSearch( $classes ) {
	if ( get_post_type() == "search" ) :
		$home = array_search('home', $classes);
		if( $home !== FALSE ) unset($classes[$home]);
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

// Stamp images and teasers with date and figure counts
function battleplan_countTease( $id, $override=false ) {
	if ( $override==true || ( _USER_LOGIN != "battleplanweb" && _IS_BOT != true ) ) :
		$getViews = readMeta($id, 'log-views');
		if ( !is_array($getViews) ) $getViews = array();
		$viewsToday = $views7Day = $views30Day = $views90Day = $views180Day = $views365Day = intval(0);

		//$rightNow = strtotime(date("F j, Y g:i a")) - 14450;
		$rightNow = strtotime(date("F j, Y g:i a"));
		$today = strtotime(date("F j, Y"));
		$lastViewed = strtotime($getViews[0]['date']);
		$dateDiff = (int)(($today - $lastViewed) / 60 / 60 / 24);

		if ( $dateDiff != 0 ) : // day has passed, move 29 to 30, and so on
			for ($i = 1; $i <= $dateDiff; $i++) {
				$figureTime = $today - ( ($dateDiff - $i) * 86400);
				array_unshift($getViews, array ('date'=>date("F j, Y", $figureTime), 'views'=>$viewsToday));
			}
		else:
			$viewsToday = (int)$getViews[0]['views'];
		endif;

		updateMeta($id, 'log-last-viewed', $rightNow);

		$viewsToday++;
		array_shift($getViews);
		array_unshift($getViews, array ('date'=>date('F j, Y', $today), 'views'=>$viewsToday));
		updateMeta($id, 'log-views', $getViews);

		for ($x = 0; $x < 7; $x++) { if ( isset($getViews[$x]['views'])) $views7Day = $views7Day + (int)$getViews[$x]['views']; }
		for ($x = 0; $x < 30; $x++) { if ( isset($getViews[$x]['views'])) $views30Day = $views30Day + (int)$getViews[$x]['views']; }
		for ($x = 0; $x < 90; $x++) { if ( isset($getViews[$x]['views'])) $views90Day = $views90Day + (int)$getViews[$x]['views']; }
		for ($x = 0; $x < 180; $x++) { if ( isset($getViews[$x]['views'])) $views180Day = $views180Day + (int)$getViews[$x]['views']; }
		for ($x = 0; $x < 365; $x++) { if ( isset($getViews[$x]['views'])) $views365Day = $views365Day + (int)$getViews[$x]['views']; }
		updateMeta($id, 'log-views-today', $viewsToday);
		updateMeta($id, 'log-views-total-7day', $views7Day);
		updateMeta($id, 'log-views-total-30day', $views30Day);
		updateMeta($id, 'log-views-total-90day', $views90Day);
		updateMeta($id, 'log-views-total-180day', $views180Day);
		updateMeta($id, 'log-views-total-365day', $views365Day);
	endif;
}

/*--------------------------------------------------------------
# Basic Theme Set Up
--------------------------------------------------------------*/
// Enable auto-updates on plugins and themes
add_filter( 'auto_update_theme', '__return_true' );
add_filter( 'auto_update_plugin', '__return_true' );

// Allow Git Updater to work despite WP Engine problems
//add_filter( 'gu_ignore_dot_org', '__return_true' );

// Disable update emails from WordPress
add_filter('auto_plugin_update_send_email', '__return_false');
add_filter('auto_theme_update_send_email', '__return_false');
add_filter('auto_core_update_send_email', 'battleplan_disable_core_update_emails', 10, 4 );
function battleplan_disable_core_update_emails( $send, $type, $core_update, $result ) {
	if ( !empty($type) && $type == 'success' ) return false;
  	return true;
}

// Determine how to sort custom post types
add_action( 'pre_get_posts', 'battleplan_handle_main_query', 1 );
function battleplan_handle_main_query( $query ) {
	if (!is_admin() && $query->is_main_query()) :
		if ( is_post_type_archive('testimonials') ) :
			$query->set( 'post_type','testimonials');
			$query->set( 'posts_per_page',10);
			$query->set( 'orderby','rand');
			/*
			$query->set( 'meta_key', 'log-views-total-30day' );
        	$query->set( 'orderby', 'meta_value_num' );
        	$query->set( 'order', 'ASC');
			*/
		endif;
		if ( is_post_type_archive('galleries') ) :
			$query->set( 'post_type','galleries');
			$query->set( 'posts_per_page',-1);
			$query->set( 'orderby','rand');
		endif;
		if ( is_post_type_archive('events') ) :
			$query->set( 'post_type','events');
			$query->set( 'posts_per_page',-1);
			$query->set( 'meta_key', 'start_date' );
        		$query->set( 'orderby', 'meta_value_num' );
        		$query->set( 'order', 'ASC');
		endif;
	endif;
}

// Determine RAND() with the seed from site option
add_filter('posts_orderby', 'battleplan_random_seed');
function battleplan_random_seed($orderby_statement) {
	if ( strpos( $orderby_statement, 'RAND()' ) !== FALSE ) $orderby_statement = 'RAND('._RAND_SEED.')';
    return $orderby_statement;
}

// Preload site-background.jpg or site-background.webp if it exists
//add_action( 'wp_footer', 'battleplan_preload_bg' );
function battleplan_preload_bg() {
	$file = '';
	if (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-background.jpg' ) ) :
		$file = "site-background.jpg";
	elseif (is_file( $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-background.webp' ) ) :
		$file = "site-background.webp";
	endif;
	/*
	if ( $file != '' ) : ?>
		<script nonce="<?php echo _BP_NONCE; ?>">var preloadBG = new Image(); preloadBG.onload = function() { animateDiv( ".parallax-mirror", "fadeIn", 0, "", 200 ); }; preloadBG.src = "<?php echo wp_upload_dir()['baseurl']; ?>/<?php echo $file ?>";</script>
	<?php endif;
	*/
}

// Add some defining classes to body
add_filter( 'body_class', 'battleplan_addBodyClasses', 30 );
function battleplan_addBodyClasses( $classes ) {
	$customer_info = customer_info();
	$classes[] = "slug-"._PAGE_SLUG;
	$classes[] = is_mobile() ? "screen-mobile" : "screen-desktop";

	$siteType = $customer_info['site-type'] ?? null;
	$bizTypeRaw = $customer_info['business-type'] ?? null;
	$bizType    = is_array($bizTypeRaw) ? ($bizTypeRaw[0] ?? null) : $bizTypeRaw;

    if ( $siteType ) $classes[] = 'site-type-'.strtolower($siteType);
    if ( $bizType )  $classes[] = 'business-type-'.strtolower($bizType);

	return $classes;
}

// Add Breadcrumbs
function battleplan_breadcrumbs() {
    $home_link = home_url('/');
    $home_text = __( 'Home' );
    $link_before = '<span typeof="v:Breadcrumb">';
    $link_after = '</span>';
    $link_attr = ' rel="v:url" property="v:title"';
    $link = $link_before.'<a'.$link_attr.' href="%1$s">%2$s</a>'.$link_after;
    $delimiter = ' &raquo; ';
    $before = '<span class="current">';
    $after = '</span>';
    $page_addon = $breadcrumb_trail = $category_links = '';
    $wp_the_query   = $GLOBALS['wp_the_query'];
    $queried_object = $wp_the_query->get_queried_object();

    if ( is_singular() ) :
        $post_object = sanitize_post( $queried_object );
        $title = apply_filters('the_title', $post_object->post_title, $post_object->ID ?? 0);
        $parent = $post_object->post_parent;
        $post_type = $post_object->post_type;
        $post_id = $post_object->ID;
        $post_link = $before.$title.$after;
        $parent_string = $post_type_link = '';

        if ( $post_type === 'post' ) :
            $categories = get_the_category( $post_id );
            if ( $categories ) :
                $category  = $categories[0];
                $category_links = get_category_parents( $category, true, $delimiter );
                $category_links = str_replace( '<a',   $link_before.'<a'.$link_attr, $category_links );
                $category_links = str_replace( '</a>', '</a>'.$link_after, $category_links );
            endif;
       endif;

        if ( !in_array( $post_type, ['post', 'page', 'attachment'] ) ) :
            $post_type_object = get_post_type_object( $post_type );
            $archive_link = esc_url( get_post_type_archive_link( $post_type ) );
			if ($achive_link) $post_type_link = sprintf( $link, $archive_link, $post_type_object->labels->name );
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
            $breadcrumb_trail = $parent_string.$delimiter.$post_link;
        else :
            $breadcrumb_trail = $post_link;
        endif;

        if ( $post_type_link ) $breadcrumb_trail = $post_type_link.$delimiter.$breadcrumb_trail;

        if ( $category_links ) $breadcrumb_trail = $category_links.$breadcrumb_trail;
    endif;

    if( is_archive() ) :
        if ( is_category() || is_tag() || is_tax() ) :
            $term_object = get_term( $queried_object );
            $taxonomy = $term_object->taxonomy;
            $term_id = $term_object->term_id;
            $term_name = $term_object->name;
            $term_parent = $term_object->parent;
            $taxonomy_object = get_taxonomy( $taxonomy );
            $today_term_link = $before.$taxonomy_object->labels->name.': '.$term_name.$after;
            $parent_term_string = '';

            if ( $term_parent !== 0 ) :
                $parent_term_links = [];
                while ( $term_parent ) :
                    $term = get_term( $term_parent, $taxonomy );
                    $parent_term_links[] = sprintf( $link, esc_url( get_term_link( $term ) ), $term->name );
                    $term_parent = $term->parent;
                endwhile;
                $parent_term_links = array_reverse( $parent_term_links );
                $parent_term_string = implode( $delimiter, $parent_term_links );
            endif;

            if ( $parent_term_string ) :
                $breadcrumb_trail = $parent_term_string.$delimiter.$today_term_link;
            else :
                $breadcrumb_trail = $today_term_link;
            endif;

      	elseif ( is_author() ) :
            $breadcrumb_trail = __( 'Author archive for ').$before.$queried_object->data->display_name.$after;

        elseif ( is_date() ) :
            $year = $wp_the_query->query_vars['year'];
            $monthnum = $wp_the_query->query_vars['monthnum'];
            $day = $wp_the_query->query_vars['day'];

            if ( $monthnum ) :
                $date_time = DateTime::createFromFormat( '!m', $monthnum );
                $month_name = $date_time->format( 'F' );
            endif;

            if ( is_year() ) : $breadcrumb_trail = $before.$year.$after;

            elseif ( is_month() ) :
                $year_link = sprintf( $link, esc_url( get_year_link( $year ) ), $year );
                $breadcrumb_trail = $year_link.$delimiter.$before.$month_name.$after;

            elseif ( is_day() ) :
                $year_link = sprintf( $link, esc_url( get_year_link( $year ) ), $year );
                $month_link = sprintf( $link, esc_url( get_month_link( $year, $monthnum ) ), $month_name );
                $breadcrumb_trail = $year_link.$delimiter.$month_link.$delimiter.$before.$day.$after;
            endif;

        elseif ( is_post_type_archive() ) :
            $post_type = $wp_the_query->query_vars['post_type'];
            $post_type_object = get_post_type_object( $post_type );
            $breadcrumb_trail = $before.$post_type_object->labels->name.$after;
        endif;
    endif;

    if ( is_search() ) $breadcrumb_trail = __( 'Search query for: ' ).$before.get_search_query().$after;

    if ( is_404() ) $breadcrumb_trail = $before.__( 'Error 404' ).$after;

    if ( is_paged() ) :
        $today_page = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' );
        $page_addon   = $before.sprintf( __( ' ( Page %s )' ), number_format_i18n( $today_page?? 0.0 ) ).$after;
    endif;

    $breadcrumb_output_link = '<div class="breadcrumbs">';
    if ( is_home() || is_front_page() ) :
        if ( is_paged() ) :
            $breadcrumb_output_link .= '<a href="'.$home_link.'">'.$home_text.'</a>';
            $breadcrumb_output_link .= $page_addon;
        endif;
    else :
        $breadcrumb_output_link .= '<a href="'.$home_link.'" rel="v:url" property="v:title">'.$home_text.'</a>';
        $breadcrumb_output_link .= $delimiter;
        $breadcrumb_output_link .= ucwords($breadcrumb_trail);
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

// Install promo on blog
add_action( 'bp_after_the_content', 'battleplan_promo' );
function battleplan_promo() {
	if ( is_single() && get_post_type() == "post" ) :
		$current_ad = do_shortcode('[get-element slug="coupon"]');
		if ( $current_ad ) echo '<div class="place-ad">'.$current_ad.'</div>';
	endif;
}

add_shortcode( 'insert-promo', 'battleplan_GetPromo' );
function battleplan_GetPromo($atts, $content = null ) {
	return '<div class="insert-promo"></div>';
}

// Set up post meta date
function battleplan_meta_date() {
	$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
	if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="entry-date updated" datetime="%3$s">%4$s</time>';
	$time_string = sprintf ( $time_string, esc_attr( get_the_date( DATE_W3C ) ), esc_html( get_the_date() ), esc_attr( get_the_modified_date( DATE_W3C ) ), esc_html( get_the_modified_date() ) );
	$posted_on = sprintf ( esc_html_x( '%s', 'post date', 'battleplan' ), $time_string );

	return '<span class="meta-date">[get-icon type="calendar"]'.$posted_on.'</span>';
}

// Set up post meta author
function battleplan_meta_author($link='false') {
	$byline = sprintf ( esc_html_x( '%s', 'post author', 'battleplan' ), '<span class="author vcard">'.esc_html( get_the_author() ).'</span>' );
	$printByline = '<span class="meta-author">';
	if ( $link == 'profile' ) $printByline .= '<a class="author-link" href="/profile/?user='.esc_html( get_the_author() ).'">';
	$printByline .= '[get-icon type="user"]'.$byline;
	if ( $link == 'profile' ) $printByline .= '</a>';
	$printByline .= '</span>';

	return $printByline;
}

// Set up post meta comments
function battleplan_meta_comments() {
	return '<span class="meta-comments">[get-icon type="comments"]'.get_comments_number().'</span>';
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

// Eliminate taxonomy.php template (go directly to archive.php)
add_filter('template_include', function ($template) {
	if ( is_tax() ) {
		$taxonomy = get_queried_object()->taxonomy;
		$taxonomy_object = get_taxonomy($taxonomy);
		$post_types = $taxonomy_object->object_type;
		$post_type = !empty($post_types) ? $post_types[0] : null;

		$tax_temp = locate_template("taxonomy-{$taxonomy}.php");
		if ( !$tax_temp ) $tax_temp = locate_template("archive-{$taxonomy}.php");
		if ( !$tax_temp ) $tax_temp = locate_template("archive-{$post_type}.php");
		if ( !$tax_temp ) $tax_temp = locate_template("archive.php");

		return $tax_temp ?: $template;
	}
	return $template;
});

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
		if ( do_shortcode('[get-biz info="location"]') ) $buildLeft .= do_shortcode('[social-btn type="location"]');
		if ( do_shortcode('[get-biz info="email"]') ) $buildLeft .= do_shortcode('[social-btn type="email"]');
		if ( do_shortcode('[get-biz info="user"]') ) $buildLeft .= do_shortcode('[social-btn type="user"]');
	$buildLeft .= "</div>";
	return $buildLeft;
}

// Stop adding line breaks to content
remove_filter( 'the_content', 'wpautop' );
remove_filter( 'the_excerpt', 'wpautop' );

function bp_wpautop($content, $sanitize = false) {
	$content = $sanitize ? wp_kses_post($content) : $content;

	$content = do_shortcode($content);

	$no_wpautop_blocks = [];
	$content = preg_replace_callback(
		'#<!--no-wpautop-->(.*?)<!--/no-wpautop-->#s',
		function ($matches) use (&$no_wpautop_blocks) {
			$placeholder = '__NOWPAUTOP_BLOCK_' . count($no_wpautop_blocks) . '__';
			$no_wpautop_blocks[$placeholder] = $matches[1];
			return $placeholder;
		},
		$content
	);

	$content = wpautop(apply_filters('the_content', $content), false);

	foreach ($no_wpautop_blocks as $placeholder => $original) {
		$content = str_replace($placeholder, $original, $content);
	}

	$content = preg_replace('/<p>\s*(<a .*?>)?\s*(<img .*? \/?>)\s*(<\/a>)?\s*<\/p>/iU', '\1\2\3', $content);

	return $content;
}

// Format with <p>
add_shortcode( 'p', 'battleplan_add_ptags' );
function battleplan_add_ptags( $atts, $content = null ) {
	return wpautop(do_shortcode($content), false);
}

// Format without <p>
add_shortcode('raw', function ($atts, $content = null) {
	return ($content !== null) ? '<!--no-wpautop-->' . do_shortcode($content) . '<!--/no-wpautop-->' : '';
});

// Necessary housekeeping items
add_action( 'after_setup_theme', 'battleplan_setup' );
if ( ! function_exists( 'battleplan_setup' ) ) :
	function battleplan_setup() {
		load_theme_textdomain( 'battleplan', get_template_directory().'/languages' );
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

// Remove margin on html (admin bar)
add_action('get_header', function() {
    remove_action('wp_head', 'wp_admin_bar_header');
    remove_action('wp_head', '_admin_bar_bump_cb');
}, 9);

// Set content width param
add_action( 'after_setup_theme', 'battleplan_content_width', 0 );
function battleplan_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'battleplan_content_width', 640 );
}

// Set up sidebar
add_action( 'widgets_init', 'battleplan_widgets_init' );
function battleplan_widgets_init() {
	register_sidebar ( array ( 'name' => esc_html__( 'Sidebar', 'battleplan' ), 'id' => 'sidebar-1', 'description' => esc_html__( 'Add widgets here.', 'battleplan' ), 'before_widget' => '<div id="%1$s" class="widget %2$s">', 'after_widget' => '</div>', 'before_title' => '<h3 class="widget-title">', 'after_title' => '</h3>', ) );
}

// Disable emojis
add_action( 'init', 'battleplan_disable_emojis' );
function battleplan_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_enqueue_scripts', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'tiny_mce_plugins', 'battleplan_disable_emojis_tinymce' );
	add_filter( 'wp_resource_hints', 'battleplan_disable_emojis_remove_dns_prefetch', 10, 2 );
}

function battleplan_disable_emojis_tinymce( $plugins ) {
	if ( is_array( $plugins ) ) :
		return array_diff( $plugins, array( 'wpemoji' ) );
	else:
		return array();
	endif;
}

function battleplan_disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
	if ( 'dns-prefetch' == $relation_type ) :
		$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
		$urls = array_diff( $urls, array( $emoji_svg_url ) );
	endif;
	return $urls;
}

add_filter('init', 'battleplan_filter_localized_scripts', 100);
function battleplan_filter_localized_scripts() {
    $GLOBALS['wp_scripts'] = new WP_Filterable_Scripts;
}

add_filter( 'battleplan_csp_localized_scripts', 'battleplan_add_nonce_to_localized_scripts', 0, 2 );
function battleplan_add_nonce_to_localized_scripts( $tag, $handle ) {
	if ( !is_admin() && strpos($GLOBALS['pagenow'], 'wp-login.php') === false ) $tag = str_replace( '<script ', '<script nonce="'._BP_NONCE.'" ', $tag );
    return $tag;
}

// remove Gutenburg crap
add_action('after_setup_theme', function() {
  	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
  	remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
	remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
	remove_filter('render_block', 'wp_render_duotone_support');
  	remove_filter('render_block', 'wp_restore_group_inner_container');
  	remove_filter('render_block', 'wp_render_layout_support_flag');
});

require_once get_template_directory() . '/functions-style-sheets.php';

// Dequeue and deregister styles that are not necessary or can be delayed to footer
add_action( 'wp_enqueue_scripts', 'battleplan_dequeue_unwanted_stuff', 9997 );
function battleplan_dequeue_unwanted_stuff() {
	wp_dequeue_style( 'classic-theme-styles' );  wp_deregister_style( 'classic-theme-styles' );
	wp_dequeue_style( 'wp-block-library' );  wp_deregister_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );  wp_deregister_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'select2' );  wp_deregister_style( 'select2' );
	wp_dequeue_style( 'asp-default-style' ); wp_deregister_style( 'asp-default-style' );
	wp_dequeue_style( 'contact-form-7' ); wp_deregister_style( 'contact-form-7' );
	if ( is_plugin_active( 'animated-typing-effect/typingeffect.php' ) ) :
		wp_dequeue_style( 'typed-cursor' );
		wp_deregister_style( 'typed-cursor' );
	endif;
	if ( is_plugin_active( 'stripe-payments/accept-stripe-payments.php' ) ) :
		wp_dequeue_style( 'stripe-handler-ng-style' );
		wp_deregister_style( 'stripe-handler-ng-style' );
	endif;
	if ( is_plugin_active( 'cue/cue.php' ) ) :
		wp_dequeue_style( 'cue' );
		wp_deregister_style( 'cue' );
	endif;

}

// Dequeue and deregister scripts that are not necessary or can be delayed to footer
add_action('wp_enqueue_scripts', 'battleplan_dequeue_scripts', 9997);
add_action('wp_print_footer_scripts', 'battleplan_dequeue_scripts', 9997);
function battleplan_dequeue_scripts() {
	wp_dequeue_script( 'select2'); wp_deregister_script('select2');
	wp_dequeue_script( 'wphb-global' ); wp_deregister_script( 'wphb-global' );
	wp_dequeue_script( 'wp-embed' ); wp_deregister_script( 'wp-embed' );
	wp_dequeue_script( 'modernizr' ); wp_deregister_script( 'modernizr' );
	wp_dequeue_script('customize-support');	wp_deregister_script('customize-support');
	wp_dequeue_script('wp-polyfill'); wp_deregister_script('wp-polyfill');
	//wp_dequeue_script('wp-i18n');	wp_deregister_script('wp-i18n');

	$GLOBALS['requires_jquery'] = [
		'woocommerce/woocommerce.php',
		'table-sorter/table-sorter.php',
		'cue/cue.php',
		'animated-typing-effect/typingeffect.php',
		'stripe-payments/accept-stripe-payments.php'
	];

	$active = false;
	foreach ($GLOBALS['requires_jquery'] as $plugin) {
		if (is_plugin_active($plugin)) {
			$active = true;
			break;
		}
	}

	if (!$active) :
		wp_dequeue_script('jquery'); wp_deregister_script('jquery');
		wp_dequeue_script('jquery-js'); wp_deregister_script('jquery-js');
		wp_dequeue_script('jquery-migrate'); wp_deregister_script('jquery-migrate');
		wp_dequeue_script('underscore'); wp_deregister_script('underscore');
	endif;
}

// Load scripts in header
add_action( 'wp_enqueue_scripts', 'battleplan_enqueue_header_scripts', 1 );
function battleplan_enqueue_header_scripts() {
	bp_enqueue_script( 'battleplan-script-helpers', 'script-helpers', [], [	'in_footer' => false ] );
	bp_enqueue_script( 'battleplan-script-essential', 'script-essential', [], [	'in_footer' => false ] );
};

// Load scripts in footer
add_action( 'wp_enqueue_scripts', 'battleplan_enqueue_footer_scripts', 9998 );
function battleplan_enqueue_footer_scripts() {
	$customer_info = customer_info();

	bp_enqueue_script( 'battleplan-script-pages', 'script-pages' );

	if ( !is_mobile() ) :
		bp_enqueue_script( 'battleplan-script-desktop', 'script-desktop' );

		if ( isset($customer_info['scripts']) && is_array($customer_info['scripts']) && in_array('magic-menu', $customer_info['scripts']) ) :
			bp_enqueue_script( 'battleplan-script-magic-menu', 'script-magic-menu' );
		endif;
	endif;

	bp_enqueue_script( 'battleplan-script-tracking', 'script-tracking' );

	$event_calendar = get_option('event-calendar');
	if ( is_array($event_calendar) && ($event_calendar['install'] ?? null) === 'true' ) bp_enqueue_script( 'battleplan-script-events', 'script-events' );

	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) bp_enqueue_script( 'battleplan-script-woocommerce', 'script-woocommerce', ['jquery'] );

	if ( is_plugin_active( 'cue/cue.php' ) ) {
		bp_enqueue_script( 'battleplan-script-cue', 'script-cue', ['jquery'] );

		$types = ['cue-rewind', 'cue-forward', 'cue-play', 'cue-pause', 'cue-volume-off', 'cue-volume-on'];
        $map = [];
        foreach ($types as $t) {
            $map[$t] = do_shortcode('[get-icon type="' . esc_attr($t) . '"]');
        }
        wp_localize_script('battleplan-script-cue', 'IconMap', $map);
	}

	$siteType = $customer_info['site-type'] ?? null;

	$isProfile =
		$siteType === 'profile'
		|| $siteType === 'profiles'
		|| (is_array($siteType) && in_array('profile', $siteType, true))
		|| (is_array($siteType) && in_array('profiles', $siteType, true));

	if ($isProfile) bp_enqueue_script( 'battleplan-script-user-profiles', 'script-user-profiles' );

	if ( is_admin() && _USER_LOGIN == "battleplanweb" ) {
		wp_enqueue_style( 'battleplan-admin-css', get_template_directory_uri().'/style-admin.css', [], _BP_VERSION );
		wp_enqueue_script( 'battleplan-admin-script', get_template_directory_uri().'/js/script-admin.js', array('quicktags'), _BP_VERSION, false );
	};

	bp_enqueue_script( 'battleplan-script-fire-off', 'script-fire-off' );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) wp_enqueue_script( 'comment-reply' );

	$saveDir = array( 'theme_dir_uri'=>get_stylesheet_directory_uri(), 'upload_dir_uri'=>wp_upload_dir()['baseurl'] );
	//wp_localize_script( 'battleplan-script-essential', 'site_dir', $saveDir );
	wp_localize_script( 'battleplan-script-helpers', 'site_dir', $saveDir );
	wp_localize_script( 'battleplan-script-desktop', 'site_dir', $saveDir );

	$saveOptions = [
		'lat'  => $customer_info['lat']  ?? null,
		'long' => $customer_info['long'] ?? null,
	];

	wp_localize_script('battleplan-script-tracking', 'site_options', $saveOptions);

	bp_enqueue_script( 'battleplan-script-site', 'script-site',	[], ['scope' => 'site']);
	wp_localize_script( 'battleplan-script-site', 'BP_AJAX', [ 'url' => admin_url('admin-ajax.php') ] );
}

// Block jquery, add nonce to inline scripts, and defer to footer when appropriate
add_filter('script_loader_tag', 'battleplan_add_data_attribute', 10, 3);
function battleplan_add_data_attribute($tag, $handle, $src) {
    if ( is_admin() || $GLOBALS['pagenow'] === 'wp-login.php' || strpos( $src, '.js' ) === FALSE ) return $tag;

    $is_jquery_required = false;
    foreach ($GLOBALS['requires_jquery'] as $plugin) {
        if (is_plugin_active($plugin)) {
            $is_jquery_required = true;
            break;
        }
    }

    if (!$is_jquery_required && ($handle === 'jquery' || $handle === 'jquery-js')) return null;

    if ( $handle === 'contact-form-7-js' || $handle === 'swv-js' ) :
		$tag = str_replace('<script ', '<script nonce="' . _BP_NONCE . '" ', $tag);
        return $tag;
	else:
		$tag = str_replace('<script ', '<script nonce="' . _BP_NONCE . '" defer ', $tag);
	    return $tag;
	endif;
}

// Replace Cloudflare email obfuscation
add_action('wp_footer', function () {
?>
<script nonce="<?php echo esc_attr(_BP_NONCE); ?>">
	(function(){
		function decodeCfEmail(hex){
			if (!hex || hex.length < 4) return '';
			var key = parseInt(hex.substr(0,2), 16);
			var out = '';
			for (var i = 2; i < hex.length; i += 2) {
			out += String.fromCharCode(parseInt(hex.substr(i,2), 16) ^ key);
			}
			return out;
		}

		function fixNode(node){
			if (!node || node.nodeType !== 1) return;

			// Variant A/B: any element with data-cfemail (span OR a)
			var el = node.matches && node.matches('[data-cfemail]') ? node : null;
			if (!el && node.querySelectorAll) {
			var list = node.querySelectorAll('[data-cfemail]');
			for (var i=0;i<list.length;i++) fixNode(list[i]);
			}

			if (el) {
			var hex = el.getAttribute('data-cfemail') || '';
			var email = decodeCfEmail(hex);
			if (!email) return;

			// Replace visible text if it's a placeholder
			if (el.classList && el.classList.contains('__cf_email__')) {
				el.textContent = email;
			}

			// If it's inside a link, force mailto
			var a1 = el.closest ? el.closest('a') : null;
			if (a1) a1.setAttribute('href', 'mailto:' + email);

			// If the element itself is the link
			if (el.tagName === 'A') el.setAttribute('href', 'mailto:' + email);
			}

			// Variant C: link-protection href with #hash (even without data-cfemail)
			if (node.tagName === 'A') {
			var href = node.getAttribute('href') || '';
			if (href.indexOf('/cdn-cgi/l/email-protection') === 0) {
				var idx = href.indexOf('#');
				if (idx !== -1) {
				var email2 = decodeCfEmail(href.slice(idx+1));
				if (email2) node.setAttribute('href', 'mailto:' + email2);
				}
			}
			}
		}

		function scan(){
			fixNode(document.documentElement);
		}

		// Run ASAP, and again when DOM is ready (covers defer/async edge cases)
		scan();
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', scan, { once:true });
		}

		// Intercept clicks as a failsafe (prevents CF protection page)
		document.addEventListener('click', function(e){
			var a = e.target && e.target.closest ? e.target.closest('a') : null;
			if (!a) return;

			var href = a.getAttribute('href') || '';
			if (href.indexOf('/cdn-cgi/l/email-protection') !== 0) return;

			// Try decode from hash first
			var idx = href.indexOf('#');
			var email = (idx !== -1) ? decodeCfEmail(href.slice(idx+1)) : '';

			// Or decode from a nested/own data-cfemail
			if (!email) {
			var holder = a.matches('[data-cfemail]') ? a : a.querySelector('[data-cfemail]');
			if (holder) email = decodeCfEmail(holder.getAttribute('data-cfemail') || '');
			}

			if (email) {
			e.preventDefault();
			window.location.href = 'mailto:' + email;
			}
		}, true);

		// Optional: if something inserts/replaces nodes later (rare), keep fixing
		if ('MutationObserver' in window) {
			new MutationObserver(function(muts){
			for (var i=0;i<muts.length;i++){
				var m = muts[i];
				if (m.addedNodes) for (var j=0;j<m.addedNodes.length;j++) fixNode(m.addedNodes[j]);
			}
			}).observe(document.documentElement, { childList:true, subtree:true });
		}
	})();
</script>
<?php
}, 999);


// Load and enqueue admin styles & scripts
add_action( 'admin_enqueue_scripts', 'battleplan_admin_scripts' );

function battleplan_admin_scripts() {
	$customer_info = customer_info();

	wp_enqueue_style( 'battleplan-admin-css', get_template_directory_uri().'/style-admin.css', [], _BP_VERSION );
	bp_enqueue_script( 'battleplan-script-helpers', 'script-helpers', ['jquery'] );
	bp_enqueue_script( 'battleplan-admin-script', 'script-admin', ['jquery'] );

	if ( isset($customer_info['site-type']) && in_array($customer_info['site-type'], ['profile', 'profiles'], true) ) {
		wp_enqueue_style( 'battleplan-user-profiles', get_template_directory_uri().'/style-user-profiles.css', [], _BP_VERSION );
		bp_enqueue_script( 'battleplan-script-user-profiles', 'script-user-profiles', ['jquery'] );
	}
	$jobsite_geo = get_option('jobsite_geo');
	if ( is_array($jobsite_geo) && ($jobsite_geo['install'] ?? null) === 'true' ) wp_enqueue_style( 'battleplan-admin-jobsite-geo-css', get_template_directory_uri()."/style-jobsite-geo-admin.css", [], _BP_VERSION );

}

// Load and enqueue login styles
add_action( 'login_enqueue_scripts', 'battleplan_login_enqueue' );
function battleplan_login_enqueue() {
	wp_dequeue_style( 'login' );  wp_deregister_style( 'login' );
	wp_enqueue_style( 'parent-style', get_template_directory_uri()."/style.css", [], _BP_VERSION );
	wp_enqueue_style( 'battleplan-login', get_template_directory_uri()."/style-login.css", [], _BP_VERSION );
}

// Load various includes
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) require_once get_template_directory().'/includes/includes-woocommerce.php';
$event_calendar = get_option('event_calendar');
if ( is_array($event_calendar) && ($event_calendar['install'] ?? null) === 'true' ) require_once get_template_directory().'/includes/includes-events.php';
$timeline = get_option('timeline');
if ( is_array($timeline) && ($timeline['install'] ?? null) === 'true' ) require_once get_template_directory().'/includes/includes-timeline.php';
$jobsite_geo = get_option('jobsite_geo');
if ( is_array($jobsite_geo) && ($jobsite_geo['install'] ?? null) === 'true' ) {
	require_once get_template_directory().'/includes/includes-jobsite-geo.php';
	require_once get_template_directory() . '/includes/includes-jobsite-geo-api.php';
}
$customer_info['site-type'] = $customer_info['site-type'] ?? '';

if ( $customer_info['site-type'] === 'hvac' ) require_once get_template_directory().'/includes/includes-hvac.php';
if ( $customer_info['site-type'] === 'pedigree' ) require_once get_template_directory().'/includes/includes-pedigree.php';
if ( $customer_info['site-type'] === 'carte-du-jour' ) require_once get_template_directory().'/includes/includes-carte-du-jour.php';
if ( $customer_info['site-type'] === 'profile' || $customer_info['site-type'] == 'profiles' ) require_once get_template_directory().'/includes/includes-user-profiles.php';

require_once get_template_directory().'/functions-shortcodes.php';
require_once get_template_directory().'/functions-icons.php';
require_once get_template_directory().'/functions-forms.php';
require_once get_template_directory().'/functions-cpt.php';
require_once get_template_directory().'/functions-ajax.php';
require_once get_template_directory().'/functions-grid.php';
require_once get_template_directory().'/functions-public.php';
if (file_exists(get_stylesheet_directory().'/functions-site.php')) require_once get_stylesheet_directory().'/functions-site.php';

require_once get_template_directory().'/functions-chron-jobs.php';
if ( is_admin() || _USER_LOGIN == "battleplanweb" ) require_once get_template_directory().'/functions-admin.php';
if (!empty( get_site_option('bp_rovin_secret'))) { require_once get_template_directory() . '/functions-rovin.php'; }

// Add filter to search & replace final HTML output
ob_start();
add_action('shutdown', function() {
	$final = '';
	$levels = ob_get_level();
	for ($i = 0; $i < $levels; $i++) $final .= ob_get_clean();
	echo apply_filters('final_output', $final);
}, 0);


/* Removed 2/23/25 after this breaks Woocommerce again; the reward does not seem to outweigh the cost for this code
/*
if ( !in_array('slug-spotify', get_body_class(), true) ) : // keeps this from fucking up the Spotify Playlist Generator
	// Delay execution of non-essential scripts  --- && $GLOBALS['pagenow'] !== 'index.php'  had to be removed for CHR Services? WTF3
	if ( !is_admin() && strpos($_SERVER['REQUEST_URI'], 'wp-json') === false && strpos($GLOBALS['pagenow'], 'wp-login.php') === false && strpos($GLOBALS['pagenow'], 'wp-cron.php') === false && strpos($_SERVER['REQUEST_URI'], '.xml') === false && !is_plugin_active( 'woocommerce/woocommerce.php' )) :
		add_filter('final_output', function($html) {
			if ( $html !== '' && $html !== null && $html !== 'undefined') :
				$dom = new DOMDocument();
				libxml_use_internal_errors(true);
				$dom->loadHTML($html);
				$scripts = $dom->getElementsByTagName('script');

				$targets = array('podium', 'xapp', 'chiirp', 'beacon', 'scheduleengine', 'leadconnectorhq', 'voip', 'clickcease', 'paypal', 'embed-player', 'huzzaz', 'fbcdn', 'facebook', 'klaviyo'); //, 'google'
				$exclusions = array('recaptcha');

				foreach ($scripts as $script) :
					foreach ($targets as $target) :
						if (strpos($script->getAttribute("src"), $target) !== FALSE) :
							foreach ($exclusions as $exclusion) :
								if (strpos($script->getAttribute("src"), $exclusion) === FALSE) :
									$script->setAttribute("data-loading", "delay");
									if ($script->getAttribute("src")) :
										$script->setAttribute("data-src", $script->getAttribute("src")); $script->removeAttribute("src");
									else:
										$script->setAttribute("data-src", "data:text/javascript;base64,".base64_encode($script->innertext)); $script->innertext="";
									endif;
								endif;
							endforeach;
						endif;
					endforeach;
				endforeach;

				$html = $dom->saveHTML();
				$html = preg_replace('/<!DOCTYPE.*?<html.*?<body><p>/ims', '', $html);
				$html = str_replace('</p></body></html>', '', $html);
				libxml_clear_errors();
				return $html;
			endif;
		});

		add_action( 'wp_print_footer_scripts', 'battleplan_delay_nonessential_scripts');
		function battleplan_delay_nonessential_scripts() {
			if ( _IS_BOT !== true ) : ?>
				<script nonce="<?php echo _BP_NONCE !== null ? _BP_NONCE : null; ?>" id="delay-scripts">
					const loadScriptsTimer=setTimeout(loadScripts,1500);
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
						setTimeout(function() {
							document.querySelectorAll("[data-loading='delay']").forEach(function(elem) {
								elem.setAttribute("src", elem.getAttribute("data-src"));
								elem.removeAttribute("data-src");
							})
						}, 1500);
					}
				</script><?php
			endif;
		}
	endif;
endif;
*/

// Add nonce to trusted scripts
add_filter('final_output', function($content) {
	if ( !is_admin() ) :
		// customize support code by WP
		$pattern = "/<script>\s*\(\s*function\(\)\s*\{\s*var\s+request,\s+b\s*=\s*document\.body,\s+c\s*=\s*'className',\s+cs\s*=\s*'customize-support',\s+rcs\s*=\s*new\s+RegExp/";
		$replace = '<script nonce="'._BP_NONCE.'">(function() {var request, b = document.body, c = "className", cs = "customize-support", rcs = new RegExp';
		$updatedContent = preg_replace($pattern, $replace, $content);
		if ($updatedContent !== null) $content = $updatedContent;

		// Contact Form 7
		$pattern = '/<script>document\.getElementById\(\s*"ak_js_1"\s*\)\.setAttribute\(\s*"value",\s*\(\s*new\s+Date\(\s*\)\s*\)\.getTime\(\s*\)\s*\);\s*<\/script>/';
		$replace = '<script nonce="'._BP_NONCE.'">document.getElementById( "ak_js_1" ).setAttribute( "value", ( new Date() ).getTime() );</script>';
		$updatedContent = preg_replace($pattern, $replace, $content);
		if ($updatedContent !== null) $content = $updatedContent;

		// Contact Form 7
		$pattern = '/<script>document\.getElementById\(\s*"ak_js_2"\s*\)\.setAttribute\(\s*"value",\s*\(\s*new\s+Date\(\s*\)\s*\)\.getTime\(\s*\)\s*\);\s*<\/script>/';
		$replace = '<script nonce="'._BP_NONCE.'">document.getElementById( "ak_js_2" ).setAttribute( "value", ( new Date() ).getTime() );</script>';
		$updatedContent = preg_replace($pattern, $replace, $content);
		if ($updatedContent !== null) $content = $updatedContent;
	endif;
	return $content;
});

// Hide the Wordpress admin bar
//show_admin_bar( false );

// Set up Main Menu
class Aria_Walker_Nav_Menu extends Walker_Nav_Menu {
	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {

		$classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$mobile = in_array('mobile-only', $classes) ? ' mobile-only' : '';
		$customer_info = customer_info();

		if ( $item->attr_title === "Search Form" ) :
			$buildOutput = bp_display_menu_search($item->title, $mobile);
		else:
			$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';
			$classes[] = 'menu-item-'.$item->ID.' menu-item-'.strtolower(str_replace(" ", "-", $item->title));
			$args = apply_filters( 'nav_menu_item_args', $args, $item, $depth );

			$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );
			$class_names = $class_names ? ' class="'.esc_attr( $class_names ).'"' : '';

			$id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args, $depth );
			$id = $id ? ' id="'.esc_attr( $id ).'"' : '';

		    if (preg_match('/\[get-biz info=["\']([^"\']+)["\']\]/', $item->attr_title, $matches)) {
				$info_key = $matches[1];
				if (isset($customer_info[$info_key])) {
					$item->url = $customer_info[$info_key];
				}
			}

			$buildOutput = "";
			$restrictMax = readMeta( $item->ID, 'bp_menu_restrict_max', true );
			$restrictMin = readMeta( $item->ID, 'bp_menu_restrict_min', true );
			if ( $restrictMax || $restrictMin ) $buildOutput .= '[restrict max="'.$restrictMax.'" min="'.$restrictMin.'"]';

			$has_children = is_array($item->classes) && in_array('menu-item-has-children', $item->classes);

			$buildOutput .= sprintf(
				'%s<li%s%s%s>',
				$indent,
				$id,
				$class_names,
				$has_children ? ' aria-haspopup="true" aria-expanded="false" tabindex="0"' : ''
			);

			$atts = array();
			$atts['title'] = !empty( $item->attr_title ) ? $item->attr_title : '';
			$atts['target'] = !empty( $item->target ) ? $item->target : '';
			$atts['rel'] = !empty( $item->xfn ) ? $item->xfn : '';
			$atts['href'] = !empty( $item->url ) ? $item->url : '';

			$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

			$attributes = '';
			foreach ( $atts as $attr => $value ) :
				if ( ! empty( $value ) ) :
					$value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
					$attributes .= ' '.$attr.'="'.$value.'"';
				endif;
			endforeach;

			$title = apply_filters( 'the_title', $item->title, $item->ID );
			$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

			$item_output  = $args->before;
			$item_output .= '<a' . $attributes . '>';
			$item_output .= $args->link_before . $title . ($has_children ? ' [get-icon type="sub-menu-icon"]' : '') . $args->link_after;
			$item_output .= '</a>';
			$item_output .= $args->after;

			$buildOutput .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
			if ( $restrictMax || $restrictMin ) $buildOutput .= '[/restrict]';
		endif;

		$output .= do_shortcode($buildOutput);
	}
}

function bp_display_menu_search( $searchText, $mobile='', $reveal='click' ) {
	bp_inline_minified_css( get_template_directory() . '/style-search.css' );

	$searchForm = '<form role="search" method="get" class="menu-search-form" action="'.home_url( '/' ).'">';
	$searchForm .= '<label><span class="screen-reader-text">'._x( 'Search for:', 'label' ).'</span></label>';
	$searchForm .= '<input type="hidden" value="1" name="sentence" />';
	$searchForm .= '<a class="menu-search-bar reveal-'.$reveal.'"><span class="icon search" aria-hidden="true"></span><input type="search" class="search-field" placeholder="'.esc_attr_x( $searchText, 'placeholder' ).'" value="'.get_search_query().'" name="s" title="'.esc_attr_x( 'Search for:', 'label' ).'" /></a>';
	$searchForm .= '</form>';

	return '<div class="menu-search-box'.$mobile.'" role="none">'.$searchForm.'</div>';
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

// Set up sizes for srcset
function get_srcset( $size ) {
	$ratio1280 = ($size / 1280) * 100;
	if ( $ratio1280 <= 40 ) : $ratio1280 = 40;
	elseif ( $ratio1280 <= 75 ) : $ratio1280 = 60;
	else: $ratio1280 = 100;
	endif;

	$ratio1024 = ($size / 1024) * 100;
	if ( $ratio1024 <= 40 ) : $ratio1024 = 33;
	elseif ( $ratio1024 <= 75 ) : $ratio1024 = 50;
	else: $ratio1024 = 100;
	endif;

	$ratio860 = ($size / 860) * 100;
	if ( $ratio860 <= 33 ) : $ratio860 = 50;
	else: $ratio860 = 100;
	endif;

	$ratio575 = ($size / 575) * 100;
	if ( $ratio575 <= 25 ) : $ratio575 = 50;
	else: $ratio575 = 100;
	endif;

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
		"full"=>__( "Original"),
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
	return 1920;
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

add_filter('intermediate_image_sizes_advanced', 'battleplan_disable_upload_sizes', 10, 2);
function battleplan_disable_upload_sizes( $sizes, $metadata ) {
    $filetype = wp_check_filetype($metadata['file']);
    if ( $filetype['type'] == 'image/gif' ) $sizes = array();
    return $sizes;
}

add_filter('big_image_size_threshold', 'battleplan_limit_non_admin_uploads', 999, 1);
function battleplan_limit_non_admin_uploads( $threshold ) {
    if ( !current_user_can( 'manage_options' ) ) return 1000;
}

// Strip EXIF data from images upon upload
add_action('wp_handle_upload', 'battleplan_strip_EXIF_data' );
function battleplan_strip_EXIF_data($upload) {
    if ($upload['type'] == 'image/jpeg' || $upload['type'] == 'image/jpg') {
        $filename = $upload['file'];

        // Attempt Imagick first; fallback to gd
        if (class_exists('Imagick')) {
            $im = new Imagick($filename);

            if (!$im->valid()) {
                return $upload;
            }

            try {
                $im->stripImage();
                $im->writeImage($filename);
                $im->clear();
                $im->destroy();
            } catch (Exception $e) {
                error_log('Unable to strip EXIF data: ' . $filename);
            }
        } elseif (function_exists('imagecreatefromjpeg')) {
            $image = imagecreatefromjpeg($filename);

            if ($image) {
                imagejpeg($image, $filename, '100');
                imagedestroy($image);
            }
        }
    }

    return $upload;
}

// Highlights menu option based on the post type of the current page and the title attribute given to the menu button in Appearance->Menus
add_filter('nav_menu_css_class', 'battleplan_current_type_nav_class', 10, 2 );
function battleplan_current_type_nav_class($classes, $item) {
	$post_type = get_post_type();
	if ( $post_type != 'post' ) :
		$classes = str_replace( 'current_page_parent', '', $classes );
		if ( $item->url == '/'.$post_type ) $classes = str_replace( 'menu-item', 'menu-item current_page_parent', $classes );
	endif;

	if ($item->attr_title != '' && $item->attr_title == $post_type) array_push($classes, 'current-menu-item');

	// Highlight HOME button if any of the Landing pages are viewed
	if ( $post_type == 'landing' && preg_match ('/, [A-Z]{2}$/', get_the_title() ) === 1 && ( $item->url == get_home_url() || $item->url == get_home_url().'/' )) array_push($classes, 'current-menu-item');

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
		$sentences = preg_split( "/(\.|\!|\?)/", $excerpt, -1, PREG_SPLIT_DELIM_CAPTURE);
		$newExcerpt = implode('', array_slice($sentences, 0, 4));
		if ( strlen($newExcerpt) > 200 ) $newExcerpt = implode('', array_slice($sentences, 0, 2));

		return $newExcerpt;
	else:
		return $excerpt;
	endif;
}

// Add Top & Bottom textareas for pages
$add_top_bottom = ['page', 'post', 'landing', 'service-areas'];

foreach ($add_top_bottom as $type) {
	$top = new Metabox_Constructor([
		'id'       => "{$type}-top",
		'title'    => 'Page Top',
		'screen'   => $type,
		'context'  => 'normal',
		'priority' => 'high'
	]);
	$top->addWysiwyg([
		'id'    => "{$type}-top_text",
		'label' => ''
	]);

	$bottom = new Metabox_Constructor([
		'id'       => "{$type}-bottom",
		'title'    => 'Page Bottom',
		'screen'   => $type,
		'context'  => 'normal',
		'priority' => 'high'
	]);
	$bottom->addWysiwyg([
		'id'    => "{$type}-bottom_text",
		'label' => ''
	]);
}

// Display Google review rating
add_action('wp_footer', 'battleplan_getGoogleRating');
function battleplan_getGoogleRating() {
	$customer_info = customer_info();

	if ( ($customer_info['pid'] ?? null) ) :
		$placeIDs = $customer_info['pid'];
		if ( isset($placeIDs) ) :
			$googleInfo = get_option('bp_gbp_update') ? get_option('bp_gbp_update') : array();

			bp_inline_minified_css( get_template_directory() . '/style-google-reviews.css' );

			$singleLoc = !is_array($placeIDs) ? true : false;
			if ( !is_array($placeIDs) ) $placeIDs = array($placeIDs);

			$buildPanel = '<div class="wp-gr wp-google-badge">';

			foreach ( $placeIDs as $placeID ) :
				if (isset($googleInfo[$placeID]) && is_array($googleInfo[$placeID]) && array_key_exists('google-rating', $googleInfo[$placeID]) && $googleInfo[$placeID]['google-rating'] > 3.99) :

					$buildPanel .= '<a class="wp-google-badge-btn" href="https://search.google.com/local/reviews?placeid='.$placeID.'&hl=en&gl=US" target="_blank">';

					$buildPanel .= '<div class="wp-google-badge-score wp-google-rating">';
					$buildPanel .= '<div class="wp-google-review"><svg role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" height="25" width="25"><title>Google Logo</title><g fill="none" fill-rule="evenodd">';
					$buildPanel .= '<path d="M482.56 261.36c0-16.73-1.5-32.83-4.29-48.27H256v91.29h127.01c-5.47 29.5-22.1 54.49-47.09 71.23v59.21h76.27c44.63-41.09 70.37-101.59 70.37-173.46z" fill="#4285f4"></path>';
					$buildPanel .= '<path d="M256 492c63.72 0 117.14-21.13 156.19-57.18l-76.27-59.21c-21.13 14.16-48.17 22.53-79.92 22.53-61.47 0-113.49-41.51-132.05-97.3H45.1v61.15c38.83 77.13 118.64 130.01 210.9 130.01z" fill="#34a853"></path>';
					$buildPanel .= '<path d="M123.95 300.84c-4.72-14.16-7.4-29.29-7.4-44.84s2.68-30.68 7.4-44.84V150.01H45.1C29.12 181.87 20 217.92 20 256c0 38.08 9.12 74.13 25.1 105.99l78.85-61.15z" fill="#fbbc05"></path>';
					$buildPanel .= '<path d="M256 113.86c34.65 0 65.76 11.91 90.22 35.29l67.69-67.69C373.03 43.39 319.61 20 256 20c-92.25 0-172.07 52.89-210.9 130.01l78.85 61.15c18.56-55.78 70.59-97.3 132.05-97.3z" fill="#ea4335"></path>';
					$buildPanel .= '<path d="M20 20h472v472H20V20z"></path>';
					$buildPanel .= '</g></svg>';
					$buildPanel .= '<div data-as-of="'.date("F j, Y", $googleInfo['date']).'" class="wp-google-value">'.number_format($googleInfo[$placeID]['google-rating']?? 0.0, 1, '.', ',').'</div>';
					$buildPanel .= '<div class="wp-google-stars">';

					if ( $googleInfo[$placeID]['google-rating'] >= 4.7) $buildPanel .= '<span class="rating" aria-hidden="true"><span class="sr-only">Rated '.number_format($googleInfo[$placeID]['google-rating']?? 0.0, 1, '.', ',').' Stars</span>[get-icon type="star"][get-icon type="star"][get-icon type="star"][get-icon type="star"][get-icon type="star"]</span>';
					if ( $googleInfo[$placeID]['google-rating'] >= 4.2 && $googleInfo[$placeID]['google-rating'] <= 4.6 ) $buildPanel .= '<span class="rating" aria-hidden="true"><span class="sr-only">Rated '.number_format($googleInfo[$placeID]['google-rating']?? 0.0, 1, '.', ',').' Stars</span>[get-icon type="star"][get-icon type="star"][get-icon type="star"][get-icon type="star"][get-icon type="star-half"]</span>';
					if ( $googleInfo[$placeID]['google-rating'] >= 3.7 && $googleInfo[$placeID]['google-rating'] <= 4.1 ) $buildPanel .= '<span class="rating" aria-hidden="true"><span class="sr-only">Rated '.number_format($googleInfo[$placeID]['google-rating']?? 0.0, 1, '.', ',').' Stars</span>[get-icon type="star"][get-icon type="star"][get-icon type="star"][get-icon type="star"][get-icon type="star-empty"]</i></span>';
					if ( $googleInfo[$placeID]['google-rating'] >= 3.2 && $googleInfo[$placeID]['google-rating'] <= 3.6 ) $buildPanel .= '<span class="rating" aria-hidden="true"><span class="sr-only">Rated '.number_format($googleInfo[$placeID]['google-rating']?? 0.0, 1, '.', ',').' Stars</span>[get-icon type="star"][get-icon type="star"][get-icon type="star"][get-icon type="star-half"][get-icon type="star-empty"]</span>';

					$buildPanel .= '</div>';
					$buildPanel .= '<div class="wp-google-total">Click to view our ';
					if ( $googleInfo[$placeID]['google-reviews'] > 4 ) :
						$buildPanel .= '<span>'.number_format($googleInfo[$placeID]['google-reviews']?? 0.0).'</span> ';
					endif;
					if ( $singleLoc == true ) :
						$buildPanel .= 'Google reviews</div>';
					else:
						$buildPanel .= 'reviews in '.$googleInfo[$placeID]['city'].'</div>';
					endif;
					$buildPanel .= '</div></div></a>';
				endif;
			endforeach;
			$buildPanel .= '</div>';
			echo do_shortcode($buildPanel);
		endif;
	endif;
}

// Set up URL re-directs
$pid = null;
if (isset($customer_info['pid'])) {
	$pid = is_array($customer_info['pid']) ? ($customer_info['pid'][0] ?? null) : $customer_info['pid'];
}
$goog_rev = $customer_info['google-review'] ?? '';

if ( _PAGE_SLUG == "google" && strlen( $pid ) > 10 ) :
	$pid = is_array($customer_info['pid']) ? $customer_info['pid'][0] : $customer_info['pid'];
	if ( $pid != '' ) {
		wp_redirect( "https://search.google.com/local/reviews?placeid=".$pid."&hl=en&gl=US", 301 );
	} elseif ( $goog_rev != '' ) {
		wp_redirect( $goog_rev, 301 );
	}
	exit;
endif;
if ( _PAGE_SLUG == "facebook" && strlen( $customer_info['facebook'] ) > 10 ) :
	$facebook = do_shortcode('[get-biz info="facebook"]');
	if ( substr($facebook, -1) != '/') $facebook .= "/";
	//wp_redirect( $facebook."reviews/", 301 );
	wp_redirect( $facebook, 301 );
	exit;
endif;
if ( _PAGE_SLUG == "yelp" && strlen( $customer_info['yelp'] ) > 10 ) :
	$yelp = str_replace('https://www.yelp.com/biz/', '', do_shortcode('[get-biz info="yelp"]'));
	wp_redirect( "https://www.yelp.com/writeareview/biz/".$yelp, 301 );
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







function customer_info(bool $force = false): array {
    static $cache = null;
    if ($force || !is_array($cache)) {
        $customer_info = get_option('customer_info');
        $cache = is_array($customer_info) ? $customer_info : [];
    }
    return $cache;
}

function update_customer_info(array $customer_info_new): bool {
    $customer_info_old = customer_info();

    if ($customer_info_old === $customer_info_new) {
        return true;
    }

    $ok = update_option('customer_info', $customer_info_new);

    if ($ok) {
        $customer_info = $customer_info_new; // legacy compat (remove once unused)
        customer_info(true);                 // refresh static cache for this request
    }

    return $ok;
}

// Extend Yoast's schema code
function bp_get_base_schema(): array {
    $customer_info = get_option('customer_info');
    return $customer_info['schema'] ?? [];
}

add_filter('wpseo_schema_organization', function ($data) {
    // Only touch the main org node
    if (($data['@id'] ?? '') !== home_url('#organization')) return $data;

    $customer_info = customer_info();
    $s  = $customer_info['schema'] ?? [];
	if (!$s) return $data;

	// Restrict areaServed on service pages: /service/{service}-{city}-{state}/
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	if (strpos($uri, '/service/') === 0 && !empty($s['areaServed'])) {
		$slug = trim(str_replace('/service/', '', $uri), '/');           // e.g. air-conditioner-installation-brashear-tx
		$parts = explode('-', strtolower($slug));
		$cnt   = count($parts);

		if ($cnt >= 2) {
			// Drop the last token (state) and work with the rest
			$stateSlug   = array_pop($parts);                             // tx
			$noStateSlug = implode('-', $parts);                          // air-conditioner-installation-brashear

			$filtered = array_values(array_filter(
				(array)$s['areaServed'],
				fn($a) => isset($a['name'])
					? (function($name, $noStateSlug) {
							// name like "Brashear, Texas" -> city slug "brashear"
							$city = trim(explode(',', strtolower($name), 2)[0] ?? '');
							if ($city === '') return false;
							$citySlug = str_replace(' ', '-', $city);      // brashear, lone oak -> lone-oak

							$lenCity = strlen($citySlug);
							$lenSlug = strlen($noStateSlug);
							if ($lenCity === 0 || $lenSlug < $lenCity) return false;

							// does noStateSlug end with citySlug?
							return substr($noStateSlug, $lenSlug - $lenCity) === $citySlug;
					   })(
						strtolower($a['name']),
						$noStateSlug
					)
					: false
			));

			// Only replace if we actually found a match; otherwise leave original list alone
			if (!empty($filtered)) $s['areaServed'] = $filtered;
		}
	}

    // Overlay simple props (hours handled below)
    foreach ([
        'priceRange','email','telephone','sameAs','areaServed',
        'hasOfferCatalog','aggregateRating','geo','logo','image',
        'additionalType','hasMap'
    ] as $k) {
        if (!empty($s[$k])) $data[$k] = $s[$k];
    }

    // Hours: set both spec + compact, or remove both if we don't have hours
    if (!empty($s['openingHoursSpecification'])) {
        $data['openingHoursSpecification'] = $s['openingHoursSpecification'];
        if (!empty($s['openingHours'])) {
            $data['openingHours'] = $s['openingHours']; // optional compact string
        }
    } else {
        unset($data['openingHoursSpecification'], $data['openingHours']);
    }

    // Merge @type (keep Yoast’s + your canonical)
    $data['@type'] = array_values(array_unique(
        array_merge((array)($data['@type'] ?? []), (array)($s['@type'] ?? []))
    ));

    // Point Yoast’s address reference at the address node we control
    $data['address'] = ['@id' => home_url('#local-main-place-address')];

    return $data;
}, 99);


add_filter('wpseo_schema_postal_address', function($addr){
    if (($addr['@id'] ?? '') === home_url('#local-main-place-address')) {
        $customer_info = customer_info();
        $abbr = trim((string)($customer_info['state-abbr'] ?? ''));
        $full = trim((string)($customer_info['state-full'] ?? ''));
        $addr['addressRegion']  = $abbr !== '' ? $abbr : ($full !== '' ? $full : '');
        $addr['addressCountry'] = 'US';
    }
    return $addr;
}, 99);


add_action('wp_footer', 'bp_output_test_schema', 999);
function bp_output_test_schema() {
    if (empty($GLOBALS['bp_reviews'])) {
        return;
    }

    echo '<script type="application/ld+json">' .
        wp_json_encode(
            [
                '@context' => 'https://schema.org',
                '@graph' => array_map(function($r) {
                    return [
                        '@type' => 'Review',
                        'author' => [
                            '@type' => 'Person',
                            'name'  => $r['author'] ?? 'Anonymous',
                        ],
                        'datePublished' => $r['date'] ?? date('c'),
                        'reviewBody'    => $r['text'] ?? '',
                        'reviewRating'  => [
                            '@type' => 'Rating',
                            'ratingValue' => $r['rating'] ?? 5,
                            'bestRating'  => 5,
                            'worstRating' => 1,
                        ],
                        'itemReviewed' => [
                            '@type' => 'LocalBusiness',
                            '@id'   => home_url('#organization'),
                        ],
                    ];
                }, $GLOBALS['bp_reviews'])
            ],
            JSON_UNESCAPED_SLASHES
        ) .
        '</script>';
}

// if someone is landing on an individual testimonial, redirect to /testimonials/
add_action('template_redirect', 'battleplan_redirect_testimonials');
function battleplan_redirect_testimonials() {
    if (is_singular('testimonials')) wp_redirect('/testimonials/', 301);
}


// Check & store site background image
function battleplan_fetch_background_image($clear=false) {
	$imgData = get_option('bp_site_bg_img');

	if ( $imgData === false || $imgData === null || $clear ) {
		$imgData = null;

		if (is_file($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-background.webp')) {
			$imgData = 'webp';
		} elseif (is_file($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-background.png')) {
			$imgData = 'png';
		} elseif (is_file($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-background.jpg')) {
			$imgData = 'jpg';
		}

		update_option('bp_site_bg_img', $imgData);
	}

	return $imgData;
}


// Check & store correct site-icon image
function battleplan_fetch_site_icon($clear=false) {
	$iconData = get_option('bp_site_icon');

	if ( $iconData === false || !isset($iconData['name']) || $clear ) {
		$iconData = [
			'name' => null,
			'wh' => '',
		];

		if (is_file($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon-80x80.webp')) {
			$iconData['name'] = 'site-icon-80x80.webp';
			$iconData['wh'] = " width='80' height='80'";
		} elseif (is_file($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon.webp')) {
			$iconData['name'] = 'site-icon.webp';
		} elseif (is_file($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon-80x80.png')) {
			$iconData['name'] = 'site-icon-80x80.png';
			$iconData['wh'] = " width='80' height='80'";
		} elseif (is_file($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon.png')) {
			$iconData['name'] = 'site-icon.png';
		} elseif (is_file($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon-80x80.jpg')) {
			$iconData['name'] = 'site-icon-80x80.jpg';
			$iconData['wh'] = " width='80' height='80'";
		} elseif (is_file($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/site-icon.jpg')) {
			$iconData['name'] = 'site-icon.jpg';
		}

		update_option('bp_site_icon', $iconData);
	}

	return $iconData;
}

// Search & Replace for various html fixes
add_filter('final_output', function($content) {
	if ( !is_admin() ) :
		// Add 'name="description"' to the <meta property="og:description"> tag
		if (strpos($content, 'name="description"') === false) {
			$content = str_replace('property="og:description"', 'name="description" property="og:description"', $content);
		}
		// Remove align- to reduce redundant css
		$content = str_replace (
    		['alignleft', 'alignright', 'aligncenter', 'top-strip', 'divider-strip', 'logo-strip', 'site-info', ' sidebar-box', 'widget-box'],
    		['align-left', 'align-right', 'align-center', 'strip-elem top-strip', 'strip-elem divider-strip', 'strip-elem logo-strip', 'strip-elem site-info', ' secondary-box sidebar-box', 'secondary-box widget-box'],
    		$content);
	endif;
	return $content;
});


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
	if ( wp_roles()->is_role( 'bp_moderator' ) ) remove_role( 'bp_moderator' );
	if ( wp_roles()->is_role( 'bp_contributor' ) ) remove_role( 'bp_moderator' );
	if ( wp_roles()->is_role( 'bp_author' ) ) remove_role( 'bp_author' );
	if ( wp_roles()->is_role( 'bp_super_author' ) ) remove_role( 'bp_super_author' );
	if ( wp_roles()->is_role( 'bp_manager' ) ) remove_role( 'bp_manager' );
	if ( wp_roles()->is_role( 'bp_super_manager' ) ) remove_role( 'bp_super_manager' );
}

function battleplan_create_user_roles() {
	$role = get_role('administrator');
    $role->add_cap( 'publish_jobsites');
    $role->add_cap( 'edit_jobsites' );
    $role->add_cap( 'delete_jobsites' );
    $role->add_cap( 'edit_others_jobsites' );
    $role->add_cap( 'delete_others_jobsites' );
    $role->add_cap( 'edit_published_jobsites' );
    $role->add_cap( 'delete_published_jobsites' );
    $role->add_cap( 'read_private_jobsites' );
    $role->add_cap( 'edit_private_jobsites' );
    $role->add_cap( 'delete_private_jobsites' );
    $role->add_cap( 'copy_jobsites' );

	$caps_admin = array (
		'update_core'					=> true,
		'install_themes'				=> true,
		'update_themes'					=> true,
		'switch_themes' 				=> true,
		'edit_themes'					=> true,
		'edit_theme_options'			=> true,
		'delete_themes'					=> true,
		'manage_options'				=> true,
		'manage_links'					=> true,
		'import'						=> true,
		'export'						=> true,
		'edit_dashboard'				=> true,
		'install_plugins'				=> true,
		'activate_plugins' 				=> true,
		'update_plugins'				=> true,
		'edit_plugins'					=> true,
		'delete_plugins'				=> true,
		'manage_admin_columns'			=> true,
		'manage_snapshots_items'		=> true,
		'manage_snapshots_destinations'	=> true,
		'manage_snapshots_settings'		=> true,
		'manage_snapshots_import'		=> true,
		'wpseo_manage_options'			=> true,
		'wpseo_manage_redirects'		=> true,
		'edit_files'					=> true,
		'moderate_comments' 			=> true,
		'manage_categories' 			=> true,
		'unfiltered_html'				=> true,
		'publish_pages'					=> true,
		'edit_pages'					=> true,
		'delete_pages'					=> true,
		'edit_others_pages'				=> true,
		'delete_others_pages'	 		=> true,
		'edit_published_pages'			=> true,
		'delete_published_pages'		=> true,
		'read_private_pages'			=> true,
		'edit_private_pages'			=> true,
		'delete_private_pages'			=> true,
		'publish_posts'					=> true,
		'edit_posts'					=> true,
		'delete_posts'					=> true,
		'edit_others_posts'				=> true,
		'delete_others_posts'			=> true,
		'edit_published_posts'			=> true,
		'delete_published_posts'		=> true,
		'read_private_posts'			=> true,
		'edit_private_posts'			=> true,
		'delete_private_posts'			=> true,
		'copy_posts'					=> true,
		'list_users'					=> true,
		'create_users'					=> true,
		'edit_users'					=> true,
		'promote_users'					=> true,
		'remove_users'					=> true,
		'delete_users'					=> true,
		'unfiltered_upload'				=> true,
		'upload_files'					=> true,
		'read'							=> true,
	);

	$caps_subscriber = array (
		'read'							=> true,
	);

	$caps_view_stats = array (
		'read'							=> true,
	);

	$caps_manage_plugins = array (
		'install_plugins'				=> true,
		'activate_plugins' 				=> true,
		'update_plugins'				=> true,
		'edit_plugins'					=> true,
		'delete_plugins'				=> true,
		'upload_files'					=> true,
		'read'							=> true,
	);

	$caps_manage_users = array (
		'list_users'					=> true,
		'create_users'					=> true,
		'edit_users'					=> true,
		'promote_users'					=> true,
		'remove_users'					=> true,
		'delete_users'					=> true,
		'upload_files'					=> true,
		'read'							=> true,
	);

	$caps_jobsite_geo = array (
		//'publish_jobsites'				=> true,
		'edit_jobsites'					=> true,
		'delete_jobsites'				=> true,
		//'edit_others_jobsites'			=> true,
		//'delete_others_jobsites'		=> true,
		//'edit_published_jobsites'		=> true,
		//'delete_published_jobsites'		=> true,
		'read_private_jobsites'			=> true,
		//'edit_private_jobsites'			=> true,
		//'delete_private_jobsites'		=> true,
		'copy_jobsites'					=> true,
		'upload_files'					=> true,
		'read'							=> true,
	);

	$caps_jobsite_geo_mgr = array (
		'publish_jobsites'				=> true,
		'edit_jobsites'					=> true,
		'delete_jobsites'				=> true,
		'edit_others_jobsites'			=> true,
		'delete_others_jobsites'		=> true,
		'edit_published_jobsites'		=> true,
		'delete_published_jobsites'		=> true,
		'read_private_jobsites'			=> true,
		'edit_private_jobsites'			=> true,
		'delete_private_jobsites'		=> true,
		'copy_jobsites'					=> true,
		'upload_files'					=> true,
		'edit_posts'					=> true,
		'read'							=> true,
	);

	$caps_post_editor = array (
		'publish_posts'					=> true,
		'edit_posts'					=> true,
		'delete_posts'					=> true,
		'edit_others_posts'				=> true,
		'delete_others_posts'			=> true,
		'edit_published_posts'			=> true,
		'delete_published_posts'		=> true,
		'read_private_posts'			=> true,
		'edit_private_posts'			=> true,
		'delete_private_posts'			=> true,
		'copy_posts'					=> true,
		'manage_categories' 			=> true,
		'manage_terms' 					=> true,
		'upload_files'					=> true,
		'read'							=> true,
	);

	$caps_post_author = array (
		'publish_posts'					=> true,
		'edit_posts'					=> true,
		'delete_posts'					=> true,
		'copy_posts'					=> true,
		'upload_files'					=> true,
		'read'							=> true,
	);

	$caps_page_editor = array (
		'publish_pages'					=> true,
		'edit_pages'					=> true,
		'delete_pages'					=> true,
		'edit_others_pages'				=> true,
		'delete_others_pages'	 		=> true,
		'edit_published_pages'			=> true,
		'delete_published_pages'		=> true,
		'read_private_pages'			=> true,
		'edit_private_pages'			=> true,
		'delete_private_pages'			=> true,
		'copy_posts'					=> true,
		'manage_categories' 			=> true,
		'manage_terms' 					=> true,
		'upload_files'					=> true,
		'read'							=> true,
	);

	$caps_page_author = array (
		'publish_pages'					=> true,
		'edit_pages'					=> true,
		'delete_pages'					=> true,
		'copy_posts'					=> true,
		'upload_files'					=> true,
		'read'							=> true,
	);

	remove_role( 'bp_jobsite_geo_mgr' );
	add_role('bp_jobsite_geo_mgr', __('Jobsite GEO Manager'), array_merge( $caps_subscriber, $caps_jobsite_geo_mgr) );

	remove_role( 'bp_jobsite_geo' );
	add_role('bp_jobsite_geo', __('Jobsite GEO'), array_merge( $caps_subscriber, $caps_jobsite_geo) );

	remove_role( 'bp_view_stats' );
	add_role('bp_view_stats', __('View Stats'), $caps_view_stats );

	remove_role( 'bp_manage_plugins' );
	add_role('bp_manage_plugins', __('Manage Plugins'), $caps_manage_plugins );

	remove_role( 'bp_manage_users' );
	add_role('bp_manage_users', __('Manage Users'), $caps_manage_users );

	remove_role( 'bp_page_editor' );
	add_role('bp_page_editor', __('Page Editor'), $caps_page_editor );

	remove_role( 'bp_page_author' );
	add_role('bp_page_author', __('Page Author'), $caps_page_author );

	remove_role( 'bp_post_editor' );
	add_role('bp_post_editor', __('Post Editor'), $caps_post_editor );

	remove_role( 'bp_post_author' );
	add_role('bp_post_author', __('Post Author'), $caps_post_author );

	remove_role( 'bp_subscriber' );
	add_role('bp_subscriber', __('Subscriber'), $caps_subscriber );
}


//add_action('init', 'battleplan_create_user_roles');

//add_action('init', 'battleplan_getAndDisplayUserRoles');
function battleplan_getAndDisplayUserRoles() {
	$caps = get_option('wp_user_roles') ? get_option('wp_user_roles') : array();
	print_r( $caps );
}

// Hide battleplanweb from any other admin user
add_action('pre_user_query','battleplan_pre_user_query');
function battleplan_pre_user_query($user_search) {
	if (_USER_LOGIN != 'battleplanweb') :
    	global $wpdb;
    	$user_search->query_where = str_replace('WHERE 1=1', "WHERE 1=1 AND {$wpdb->users}.user_login != 'battleplanweb'",$user_search->query_where);
	endif;
}

// Hide certain plug-ins from other admin users
add_action('pre_current_active_plugins', 'battleplan_plugin_hide');
function battleplan_plugin_hide() {
  	if (_USER_LOGIN != 'battleplanweb') :
		global $wp_list_table;
		$hidearr = array('admin-column-pro/admin-columns-pro.php', 'enable-media-replace/enable-media-replace.php', 'git-updater/git-updater.php', 'git-updater/github-updater.php', 'git-updater-pro/git-updater-pro.php');

		$myplugins = $wp_list_table->items;
		foreach ($myplugins as $key => $val) :
			if (in_array($key,$hidearr)) :
				unset($wp_list_table->items[$key]);
			endif;
	  	endforeach;
	endif;
}

/*--------------------------------------------------------------
# Custom Hooks
--------------------------------------------------------------*/
function bp_loader() { do_action('bp_loader'); }
function bp_meta_tags() { do_action('bp_meta_tags'); }
function bp_google_tag_manager() { do_action('bp_google_tag_manager'); }
function bp_mobile_menu_bar_items() { do_action('bp_mobile_menu_bar_items'); }
function bp_mobile_menu_bar_scroll() { do_action('bp_mobile_menu_bar_scroll'); }
function bp_mobile_menu_bar_phone() { do_action('bp_mobile_menu_bar_phone'); }
function bp_mobile_menu_bar_middle() { do_action('bp_mobile_menu_bar_middle'); }
function bp_mobile_menu_bar_contact() { do_action('bp_mobile_menu_bar_contact'); }
function bp_mobile_menu_bar_activate() { do_action('bp_mobile_menu_bar_activate'); }
function bp_before_page() { do_action('bp_before_page'); }
function bp_before_masthead() { do_action('bp_before_masthead'); }
function bp_masthead() { do_action('bp_masthead'); }
function bp_open_banner() { do_action('bp_open_banner'); }
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
function bp_footer_left() { return apply_filters('bp_footer_left', ''); }
function bp_footer_right() { return apply_filters('bp_footer_right', ''); }
function bp_after_colophon() { do_action('bp_after_colophon'); }

/*--------------------------------------------------------------
# Custom Actions
--------------------------------------------------------------*/

// Install Google Global Site Tags
add_action('bp_google_tag_manager', 'battleplan_load_tag_manager');
function battleplan_load_tag_manager() {

	// ❌ Block invalid execution contexts
	if (
		is_admin() ||
		wp_doing_ajax() ||
		wp_doing_cron() ||
		( defined('REST_REQUEST') && REST_REQUEST ) ||
		isset($_SERVER['REQUEST_URI']) && preg_match('#sitemap(_index)?\.xml|/feed/#i', $_SERVER['REQUEST_URI'])
	) {
		return;
	}

	$customer_info = customer_info();

	if (
		empty($customer_info['google-tags']) ||
		!is_array($customer_info['google-tags'])
	) {
		return;
	}

	$buildTags   = '';
	$events      = [];
	$analytics_id = '';
	$ads_id       = '';

	foreach ( $customer_info['google-tags'] as $gtag => $value ) {

		if ( $gtag === 'analytics' && _USER_LOGIN !== 'battleplanweb' ) {
			$analytics_id = esc_js($value);
			$buildTags  .= "gtag('config','{$analytics_id}');";
		}

		elseif ( $gtag === 'ads' ) {
			$ads_id = esc_js($value);
			$buildTags .= "gtag('config','{$ads_id}');";
		}

		elseif ( $gtag === 'event' && is_array($value) ) {
			$events[] = $value;
		}
	}

	if ( empty($analytics_id) ) return;

	foreach ( $events as $event ) {

		if ( count($event) !== 2 ) {
			continue;
		}

		[ $event_label, $event_tag ] = $event;

		if ( $event_label === 'phone_conversion_number' && $ads_id ) {

			$phone_number =
				($customer_info['area-before'] ?? '') .
				($customer_info['area'] ?? '') .
				($customer_info['area-after'] ?? '') .
				($customer_info['phone'] ?? '');

			$phone_number = esc_js($phone_number);
			$event_tag    = esc_js($event_tag);

			$buildTags .= "gtag('config','{$ads_id}/{$event_tag}',{phone_conversion_number:'{$phone_number}'});";
		}
	}

	echo '<script async nonce="' . esc_attr(_BP_NONCE) . '" src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($analytics_id) . '"></script>';
	echo '<script nonce="' . esc_attr(_BP_NONCE) . '">
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag("js", new Date());
		' . $buildTags . '
	</script>';
}

// Build and display desktop navigation menu
function buildNavMenu( $pos ) {
	$printMenu = '<nav id="desktop-navigation" class="main-navigation menu-strip" aria-label="Main Menu">';
	$printMenu .= wp_nav_menu ( array ( 'echo' => false, 'container' => 'div', 'container_class' => 'flex', 'menu_id' => $pos.'-menu', 'menu_class' => 'menu main-menu', 'theme_location' => $pos.'-menu', 'walker' => new Aria_Walker_Nav_Menu(), ) );
	$printMenu .= '</nav>';

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
	$scroll = '<a class="scroll-top" href="#page"><div class="mm-bar-btn mm-bar-scroll scroll-to-top-btn" aria-hidden="true">[get-icon type="chevron-up"]</div><span class="sr-only">Scroll To Top</span></a>';

	echo do_shortcode($scroll);
}

// Display Mobile Menu Bar Item - Phone
add_action('bp_mobile_menu_bar_phone', 'battleplan_mobile_menu_bar_phone', 20);
function battleplan_mobile_menu_bar_phone() {
	echo do_shortcode('[get-biz info="mm-bar-phone"]');
}

// Display Mobile Menu Bar Item - Contact
add_action('bp_mobile_menu_bar_contact', 'battleplan_mobile_menu_bar_contact', 20);
function battleplan_mobile_menu_bar_contact() {
	$query = bp_WP_Query('wpcf7_contact_form', [
		'title'          => 'Quote Request Form',
		'post_status'    => 'all',
		'posts_per_page' => 1
	]);

	if ( ! empty( $query->post ) ) :
		$form = "Quote Request Form";
		$title = "Request A Quote";
		$type = "quote";
	endif;

	$query = bp_WP_Query('wpcf7_contact_form', [
		'title'          => 'Contact Us Form',
		'post_status'    => 'all',
		'posts_per_page' => 1
	]);

	if ( ! empty( $query->post ) ) :
		$form = "Contact Us Form";
		$title = "Send A Message";
		$type = "contact";
	endif;

	$email = '';

	if ( $form && $title ) :
		$email = '<div class="mm-bar-btn mm-bar-'.$type.' modal-btn"><div class="email-btn" aria-hidden="true">[get-icon type="email"]</div><div class="email2-btn" aria-hidden="true">[get-icon type="paper-plane"]</div><span class="sr-only">Contact Us</span></div>';
	else:
		$email = '<div class="mm-bar-btn mm-bar-empty"></div>';
	endif;

	if ($email !== '') echo do_shortcode($email);
}

// Display Request Quote Modal
add_action('bp_before_page', 'battleplan_request_quote_modal', 20);
function battleplan_request_quote_modal() {

	$query = bp_WP_Query('wpcf7_contact_form', [
		'title'          => 'Quote Request Form',
		'post_status'    => 'all',
		'posts_per_page' => 1
	]);

	if ( ! empty( $query->post ) ) :
		$form = "Quote Request Form";
		$title = "Request A Quote";
	endif;

	$query = bp_WP_Query('wpcf7_contact_form', [
		'title'          => 'Contact Us Form',
		'post_status'    => 'all',
		'posts_per_page' => 1
	]);

	if ( ! empty( $query->post ) ) :
		$form = "Contact Us Form";
		$title = "Send A Message";
	endif;

	if ( $form && $title ) echo do_shortcode('[lock name="request-quote-modal" style="lock" position="modal" show="always" btn-activated="yes"][layout]<h3>'.$title.'</h3>[contact-form-7 title="'.$form.'"][/layout][/lock]');
}

// Display Mobile Menu Bar Item - Activate
add_action('bp_mobile_menu_bar_activate', 'battleplan_mobile_menu_bar_activate');
function battleplan_mobile_menu_bar_activate() {
	echo '<div class="mm-bar-btn mm-bar-activate activate-btn"><div></div><div></div><div></div></div> ';
}

// Display locked site-message
add_action('bp_before_page', 'battleplan_printSiteMessage', 20);
function battleplan_printSiteMessage() {
	echo do_shortcode('[get-element slug="site-message"]');
}

// Display the site header
add_action('bp_masthead', 'battleplan_printHeader', 20);
function battleplan_printHeader() {
	$printHeader = '<header id="masthead" aria-label="header">';
	if ( has_nav_menu( 'top-menu', 'battleplan' ) ) $printHeader .= buildNavMenu( 'top' );
	$printHeader .= do_shortcode('[get-element slug="site-header"]');
	if ( has_nav_menu( 'header-menu', 'battleplan' ) ) $printHeader .= buildNavMenu( 'header' );
	$printHeader .= '</header><!-- #masthead -->';

	echo $printHeader;
}

// Display the "we're open" banner on desktop
add_action('bp_before_masthead', 'battleplan_printOpenBanner', 30);
function battleplan_printOpenBanner() {
	echo is_biz_open() && !is_mobile() ? '<div class="currently-open-banner"><p>Call Us Now...<br>We\'re Open!</p></div>' : '';
}

// Display #wrapper-top
add_action('bp_wrapper_top', 'battleplan_printWrapperTop', 20);
function battleplan_printWrapperTop() {
	$current_page = sanitize_post( $GLOBALS['wp_the_query']->get_queried_object() );
	$textarea = get_post_meta( $current_page->ID, 'page-top_text', true );
 	if ( $textarea != "" ) echo "<section id='wrapper-top'>".apply_filters('the_content', $textarea)."</section><!-- #wrapper-top -->";
}

// Display bp_footer_left
add_filter('bp_footer_left', 'battleplan_printFooterLeft', 20, 1);
function battleplan_printFooterLeft($out) {
	return $out.battleplan_footer_social_box();
}

// Display bp_footer_right
add_filter('bp_footer_right', 'battleplan_printFooterRight', 20, 1);
function battleplan_printFooterRight($out) {
	$customer_info = customer_info();
	$printRight = '<div class="block block-text span-10">';

	$printRight .= apply_filters('bp_footer_nav', '', $customer_info);
	$printRight .= apply_filters('bp_footer_misc2', '', $customer_info);

	$printRight .= '<div class="site-info-copyright">';
	$printRight .= apply_filters('bp_footer_copyright', '', $customer_info);
	$printRight .= apply_filters('bp_footer_name', '', $customer_info);
	$printRight .= apply_filters('bp_footer_rights_reserved', '', $customer_info);
	$printRight .= '</div>';

	$printRight .= apply_filters('bp_footer_address', '', $customer_info);

	$printRight .= '<div class="site-info-links">';
	$printRight .= apply_filters('bp_footer_license', '', $customer_info);
	$printRight .= apply_filters('bp_footer_privacy', '', $customer_info);
	$printRight .= apply_filters('bp_footer_terms', '', $customer_info);
	$printRight .= apply_filters('bp_footer_areas', '', $customer_info);
	$printRight .= '</div>';

	$printRight .= apply_filters('bp_footer_battleplan', '', $customer_info);

	$printRight .= '</div>';

	return $out.$printRight;
}

add_filter('bp_footer_nav', function($out, $customer_info) {
	return wp_nav_menu( array( 'theme_location' => 'footer-menu', 'container' => 'div', 'container_id' => 'footer-navigation', 'container_class' => 'secondary-navigation', 'menu_id' => 'footer-menu', 'menu_class' => 'menu secondary-menu', 'fallback_cb' => 'false', 'echo' => false ) );
}, 10, 2);

add_filter('bp_footer_misc2', function($out, $customer_info) {
	if ( isset($customer_info['misc2']) )
		return "<div class='site-info-misc2'>".esc_html($customer_info['misc2'])."</div>";
}, 10, 2);

add_filter('bp_footer_copyright', function($out, $customer_info) {
	$c = date('Y');

	if ( !empty($customer_info['year']) ) {
		$y = (int) $customer_info['year'];
		$printDate = ($y === (int) $c) ? $c : $y . '-' . $c;
	} else {
		$printDate = $c;
	}
	return '© ' . esc_html($printDate) . ' ';
}, 10, 2);


add_filter('bp_footer_name', function($out, $customer_info) {
	if (!empty($customer_info['name']))
		return esc_html($customer_info['name']);
}, 10, 2);

add_filter('bp_footer_rights_reserved', function($out, $customer_info) {
	return ' • All Rights Reserved';
}, 10, 2);

add_filter('bp_footer_address', function($out, $customer_info) {
	$placeIDs = $customer_info['pid'] ?? null;
	$googleInfo = get_option('bp_gbp_update') ? get_option('bp_gbp_update') : array();
	$buildAddress = '<div class="site-info-address">';

	if ( !is_array($placeIDs) )
		$placeIDs = array($placeIDs);

	$primePID = true;
	foreach ( $placeIDs as $placeID ) {
		if ( $primePID === true ) {
			$google_info = customer_info();
			$primePID = false;
		} else {
			$google_info = $googleInfo[$placeID];
		}

		$buildAddress .= apply_filters('bp_footer_street', '', $google_info);
		$buildAddress .= apply_filters('bp_footer_city_state_zip', '', $google_info);
		$buildAddress .= apply_filters('bp_footer_phone', '', $google_info);
	};

	$buildAddress .= "</div>";

	return $buildAddress;
}, 10, 2);

add_filter('bp_footer_street', function($out, $google_info) {
	if ( strlen($google_info['street']) > 5 )
		return trim($google_info['street']).", ";
}, 10, 2);

add_filter('bp_footer_city_state_zip', function($out, $google_info) {
	if ( array_key_exists('city', $google_info) ) :
		return $google_info['city'].", ".$google_info['state-abbr']." ".$google_info['zip'];
	elseif ( array_key_exists('region', $google_info) ) :
		return $google_info['region'];
	endif;
}, 10, 2);

add_filter('bp_footer_phone', function($out, $google_info) {
	if (!empty($google_info['phone-format'])) {
		return " • <a href = 'tel:".esc_html($google_info['area'])."-".esc_html($google_info['phone'])."'>".esc_html($google_info['phone-format'])."</a>";
	} else {
		return " • <a href = 'tel:".esc_html($google_info['area'])."-".esc_html($google_info['phone'])."'>(".esc_html($google_info['area']).') '.esc_html($google_info['phone'])."</a>";
	}
}, 10, 2);

add_filter('bp_footer_license', function($out, $customer_info) {
	if (!empty($customer_info['license']))
		return "License {$customer_info['license']} • ";
}, 10, 2);

add_filter('bp_footer_privacy', function($out, $customer_info) {
	if ($pg = get_page_by_path('privacy-policy', OBJECT, 'universal'))
		return '<a href="'.get_permalink($pg->ID).'">Privacy Policy</a>';
}, 10, 2);

add_filter('bp_footer_terms', function($out, $customer_info) {
	if ($pg = get_page_by_path('terms-conditions', OBJECT, 'universal'))
		return ' • <a href="'.get_permalink($pg->ID).'">Terms & Conditions</a>';
}, 10, 2);

add_filter('bp_footer_areas', function($out, $customer_info) {
	if ($pg = get_page_by_path('areas-we-serve', OBJECT, 'universal'))
		return ' • <a href="'.get_permalink($pg->ID).'">Areas We Serve</a>';
}, 10, 2);

add_filter('bp_footer_battleplan', function($out, $customer_info) {
	return '<div class="site-info-battleplan">Website developed & maintained by <a href="https://battleplanwebdesign.com" target="_blank" rel="noreferrer">Battle Plan Web Design</a></div>';
}, 10, 2);


// Filter site HTML before rendering - basic search / replace for sitewite corrections
add_filter('final_output', function($content) {
	if ( !is_admin() ) :
		$new = do_shortcode('<span class="icon phone"></span>');
		$content = str_replace('<i class="fas fa-phone-alt" aria-hidden="true"></i>',$new, $content);
		$new = do_shortcode('<span class="icon phone"></span>');
		$content = str_replace('<i class="fas fa-phone" aria-hidden="true"></i>',$new, $content);

		$content = str_replace('sizes="auto, (max-width', 'sizes="(max-width', $content);    // 1/20/25 necessary to maintain validation
	endif;
	return $content;
});