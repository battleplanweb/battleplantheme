<?php 
/* Battle Plan Web Design Functions: Main
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Set Constants
# Customer Info Globals
# Google Ad Locations
# Icon Globals

/*--------------------------------------------------------------
# Set Constants
--------------------------------------------------------------*/


if ( !defined('_BP_VERSION') ) define( '_BP_VERSION', '2025.32.9' );
update_option( 'battleplan_framework', _BP_VERSION, false );

if ( !defined('_BP_NONCE') ) define( '_BP_NONCE', base64_encode(random_bytes(20)) );
if ( !defined('_HEADER_ID') ) define( '_HEADER_ID', get_page_by_path('site-header', OBJECT, 'elements')->ID ); 

if ( !defined('_PLACES_API') ) define ( '_PLACES_API', get_option('bp_places_key') );
if ( !defined('_JOBSITE_API') ) define ( '_JOBSITE_API', get_option('bp_jobsite_key') );
if ( !defined('_BREVO_API') ) define ( '_BREVO_API', get_option('bp_brevo_key') );

$get_user = wp_get_current_user();
$roles = $get_user->roles;
if ( !defined('_USER_LOGIN') ) define( '_USER_LOGIN', $get_user->user_login );
if ( !defined('_USER_ID') ) define( '_USER_ID', $get_user->ID );
if ( !defined('_USER_ROLES') && $roles ) :
	define( '_USER_ROLES', $roles );
else:
	if ( !defined('_USER_ROLES') ) define( '_USER_ROLES', array() );
endif;

if ( _USER_LOGIN == 'battleplanweb' ) : 
	//showMe(_USER_ROLES,true);
endif;

if ( !defined('_PAGE_SLUG') ) :
	if ( basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ) : 
		define( '_PAGE_SLUG', basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ); 
	else: 
		define( '_PAGE_SLUG', 'home' );
	endif;
endif;
if ( !defined('_PAGE_SLUG_FULL') ) :
	if ( $_SERVER['REQUEST_URI'] ) :
		define( '_PAGE_SLUG_FULL', $_SERVER['REQUEST_URI'] ); 
	else:
		define( '_PAGE_SLUG_FULL', 'home' );
	endif;
endif;

if ( !defined('_RAND_SEED') ) :
	if ( get_option('rand-seed') && (time() - get_option('rand-seed')) > 14000 ) update_option('rand-seed', time());
	define( '_RAND_SEED', get_option('rand-seed') );
endif;

$UA	= $_SERVER['HTTP_USER_AGENT'] ?? '';
$IP	= $_SERVER['REMOTE_ADDR'] ?? '';
$REF= $_SERVER['HTTP_REFERER'] ?? '';

$spam_domains = get_transient('bp_matomo_spam_list');
if ($spam_domains === false) {
	$r = wp_remote_get('https://raw.githubusercontent.com/matomo-org/referrer-spam-list/master/spammers.txt', ['timeout'=>5]);
	if (!is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200) {
		$body = wp_remote_retrieve_body($r);
		$lines = array_map('trim', explode("\n", $body));
		$spam_domains = array_values(array_filter($lines, fn($l)=>$l!=='' && $l[0]!=='#'));
		set_transient('bp_matomo_spam_list', $spam_domains, 12 * HOUR_IN_SECONDS);
	} else {
		$spam_domains = [];
	}
}

$ref_is_spam = false;
if ($REF) {
	$host = strtolower(parse_url($REF, PHP_URL_HOST) ?: '');
	if ($host) {
		foreach ($spam_domains as $d) {
			$d = strtolower($d);
			if ($host === $d || str_ends_with($host, '.'.$d)) { $ref_is_spam = true; break; }
		}
	}
}

$any = fn($hay,$arr)=>array_reduce($arr, fn($c,$s)=>$c||($hay!==''&&stripos($hay,$s)!==false), false);

require_once get_template_directory() . '/_prewp/bot-helpers.php';

$__serp = bp_is_verified_serp_bot($IP, $UA);
!defined('_IS_SERP_BOT') && define('_IS_SERP_BOT', $__serp);
!defined('_IS_BOT') && define('_IS_BOT', _IS_SERP_BOT || $ref_is_spam);

// Block only if spam-referrer AND not a verified SERP bot
$should_block = (!_IS_SERP_BOT && $ref_is_spam);
if ($should_block) {
	status_header(403);
	header('Content-Type: text/plain; charset=UTF-8');
	exit;
}

/*--------------------------------------------------------------
# Customer Info Globals
--------------------------------------------------------------*/

$customer_info = customer_info();
$currYear=date("Y"); 
$startYear = $customer_info['year'] ? $customer_info['year'] : 0;
$customer_info['copyright'] = $startYear == $currYear ? "© ".$currYear : "© ".$startYear."-".$currYear; 
$GLOBALS['do_not_repeat'] = array(); 	


/*--------------------------------------------------------------
# Set up site based on user's location (if necessary)
--------------------------------------------------------------*/

function findCity($userCity) {
	$customer_info = customer_info();
	foreach ($customer_info['service-areas'] as $area) :
		if (in_array($userCity, $area)) return true;
	endforeach;
	return false;
}

if ( !is_admin() ) :
	$common = array('am', 'an', 'as', 'at', 'be', 'by', 'do', 'if', 'is', 'it', 'me', 'my', 'no', 'of', 'on', 'or', 'so', 'to', 'up', 'us', 'we');
	$page_slug = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'); 
	$userLoc = '';

// Does this user come from a Google Ad?
	if (!empty($_GET)) :
		foreach( $_GET as $key => $value ) :		
			if ( $key === "loc" || $key === "int" ) :
				if ( $customer_info['google-ad-loc'] == "DFW" ) :
					$cities = array('1026339'=>'Dallas, TX', '9026903'=>'Dallas, TX', '9026904'=>'Dallas, TX', '9026905'=>'Dallas, TX', '9026906'=>'Dallas, TX', '9026907'=>'Dallas, TX', '9026908'=>'Dallas, TX', '9026909'=>'Dallas, TX', '9026910'=>'Dallas, TX', '9026911'=>'Dallas, TX', '9026912'=>'Dallas, TX', '9026913'=>'Dallas, TX', '9026914'=>'Dallas, TX', '9026915'=>'Dallas, TX', '9026916'=>'Dallas, TX', '9026917'=>'Dallas, TX', '9026918'=>'Dallas, TX', '9026919'=>'Dallas, TX', '9026920'=>'Dallas, TX', '9026921'=>'Dallas, TX', '9026922'=>'Dallas, TX', '9026923'=>'Dallas, TX', '9026924'=>'Dallas, TX', '9026925'=>'Dallas, TX', '9026922'=>'Dallas, TX', '9026955'=>'Dallas, TX', '9026938'=>'Dallas, TX', '9026926'=>'Dallas, TX', '9026914'=>'Dallas, TX', '9026946'=>'Dallas, TX', '9026945'=>'Dallas, TX', '9026926'=>'Dallas, TX', '9026927'=>'Dallas, TX', '9026928'=>'Dallas, TX', '9026929'=>'Dallas, TX', '9026930'=>'Dallas, TX', '9026931'=>'Dallas, TX', '9026932'=>'Dallas, TX', '9026933'=>'Dallas, TX', '9026934'=>'Dallas, TX', '9026935'=>'Dallas, TX', '9026936'=>'Dallas, TX', '9026937'=>'Dallas, TX', '9026938'=>'Dallas, TX', '9026939'=>'Dallas, TX', '9026940'=>'Dallas, TX', '9026941'=>'Dallas, TX', '9026942'=>'Dallas, TX', '9026943'=>'Dallas, TX', '9026944'=>'Dallas, TX', '9026945'=>'Dallas, TX', '9026946'=>'Dallas, TX', '9026947'=>'Dallas, TX', '9026948'=>'Dallas, TX', '9026949'=>'Dallas, TX', '9026950'=>'Dallas, TX', '9026951'=>'Dallas, TX', '9026952'=>'Dallas, TX', '9026953'=>'Dallas, TX', '9026954'=>'Dallas, TX', '9026955'=>'Dallas, TX', '9041376'=>'Dallas, TX', '9059462'=>'Dallas County, TX', '1026411'=>'Fort Worth, TX', '9027250'=>'Fort Worth, TX', '9027277'=>'Fort Worth, TX', '9027251'=>'Fort Worth, TX', '9027258'=>'Fort Worth, TX', '9027265'=>'Fort Worth, TX', '9027284'=>'Fort Worth, TX', '9027252'=>'Fort Worth, TX', '9027285'=>'Fort Worth, TX', '9027266'=>'Fort Worth, TX', '9027253'=>'Fort Worth, TX', '9027264'=>'Fort Worth, TX', '9027273'=>'Fort Worth, TX', '9027249'=>'Fort Worth, TX', '9027282'=>'Fort Worth, TX', '9027257'=>'Fort Worth, TX', '9027279'=>'Fort Worth, TX', '9027281'=>'Fort Worth, TX', '1026171'=>'Addison, TX', '9026791'=>'Addison, TX', '9041344'=>'Addison, TX', '1026178'=>'Allen, TX', '9026797'=>'Allen, TX', '9027193'=>'Alvarado, TX', '1026187'=>'Anna, TX', '9026959'=>'Anna, TX', '1026193'=>'Argyle, TX', '9027199'=>'Arlington, TX', '9027201'=>'Arlington, TX', '9027200'=>'Arlington, TX', '9027198'=>'Arlington, TX', '9027189'=>'Arlington, TX', '9027194'=>'Arlington, TX', '9027202'=>'Arlington, TX', '9027188'=>'Arlington, TX', '9027196'=>'Arlington, TX', '1026200'=>'Aubrey, TX', '9027296'=>'Aubrey, TX', '9027206'=>'Bedford, TX', '9027205'=>'Bedford, TX', '9026964'=>'Bells, TX', '9027272'=>'Benbrook, TX', '9027261'=>'Benbrook, TX', '9027255'=>'Benbrook, TX', '1026229'=>'Blue Ridge, TX', '9026973'=>'Blue Ridge, TX', '9026968'=>'Bonham, TX', '9027208'=>'Burleson, TX', '1026271'=>'Carrollton, TX', '1020242'=>'Carrollton, TX', '9026793'=>'Carrollton, TX', '9026796'=>'Carrollton, TX', '9026794'=>'Carrollton, TX', '9026855'=>'Cedar Hill, TX', '9026972'=>'Celeste, TX', '1026278'=>'Celina, TX', '9027209'=>'Cleburne, TX', '9027517'=>'Coleman, TX', '9027211'=>'Colleyville, TX', '9059448'=>'Collin County, TX', '9026975'=>'Commerce, TX', '9053755'=>'Copeville, TX', '1026317'=>'Coppell, TX', '9026798'=>'Coppell, TX', '9026851'=>'Coppell, TX', '9051771'=>'Corinth, TX', '9027293'=>'Corinth, TX', '9026859'=>'Corsicana, TX', '9026860'=>'Crandall, TX', '9027213'=>'Crowley, TX', '9027197'=>'Dalworthington Gardens, TX', '9027300'=>'Decatur, TX', '1026349'=>'Denison, TX', '9026800'=>'Denison, TX', '9026799'=>'Denison, TX', '1026350'=>'Denton, TX', '9027286'=>'Denton, TX', '9027292'=>'Denton, TX', '9027289'=>'Denton, TX', '9027290'=>'Denton, TX', '9026861'=>'DeSoto, TX', '1026364'=>'Duncanville, TX', '9026871'=>'Duncanville, TX', '9026862'=>'Duncanville, TX', '9027006'=>'East Tawakoni, TX', '9027006'=>'East Tawakoni, TX', '9026864'=>'Ennis, TX', '1026385'=>'Euless, TX', '9027215'=>'Euless, TX', '9027214'=>'Euless, TX', '9051926'=>'Fairview, TX', '9026831'=>'Fairview, TX', '9059479'=>'Fannin County, TX', '9051933'=>'Farmers Branch, TX', '9026987'=>'Farmersville, TX', '9026902'=>'Fate, TX', '9026866'=>'Ferris, TX', '9026801'=>'Flower Mound, TX', '9026805'=>'Flower Mound, TX', '9028405'=>'Friona, TX', '1026407'=>'Frisco, TX', '9026807'=>'Frisco, TX', '9026808'=>'Frisco, TX', '1026419'=>'Garland, TX', '9026812'=>'Garland, TX', '9026814'=>'Garland, TX', '9026811'=>'Garland, TX', '9026813'=>'Garland, TX', '9026815'=>'Garland, TX', '1026439'=>'Grand Prairie, TX', '9026817'=>'Grand Prairie, TX', '9026818'=>'Grand Prairie, TX', '9026820'=>'Grand Prairie, TX', '9026819'=>'Grand Prairie, TX', '1026441'=>'Grapevine, TX', '9027222'=>'Grapevine, TX', '9027246'=>'Grapevine, TX', '1026442'=>'Greenville, TX', '9026957'=>'Greenville, TX', '9026956'=>'Greenville, TX', '1026451'=>'Haltom City, TX', '1026461'=>'Haslet, TX', '9026867'=>'Heath, TX', '9026828'=>'Hickory Creek, TX', '9027107'=>'Hideaway, TX', '9052131'=>'Highland Park, TX', '1026482'=>'Howe, TX', '9027244'=>'Hudson Oaks, TX', '9027225'=>'Hurst, TX', '9027224'=>'Hurst, TX', '9026873'=>'Hutchins, TX', '1026497'=>'Irving, TX', '9026827'=>'Irving, TX', '9026810'=>'Irving, TX', '9026826'=>'Irving, TX', '9026824'=>'Irving, TX', '9026825'=>'Irving, TX', '9026809'=>'Irving, TX', '9027227'=>'Joshua, TX', '9027306'=>'Justin, TX', '9026874'=>'Kaufman, TX', '1026518'=>'Keller, TX', '9027307'=>'Keller, TX', '9026877'=>'Lancaster, TX', '9026869'=>'Lancaster, TX',  '1019935'=>'Lewisville, TX', '9026829'=>'Lewisville, TX', '9026821'=>'Lewisville, TX', '1026562'=>'Little Elm, TX', '9027055'=>'Longview, TX', '9052357'=>'Lucas, TX', '9026792'=>'Lucas, TX', '9027912'=>'Madisonville, TX', '9027230'=>'Mansfield, TX', '1026607'=>'McKinney, TX', '9026833'=>'McKinney, TX', '9026832'=>'McKinney, TX', '1026611'=>'Melissa, TX', '9026996'=>'Melissa, TX', '1026619'=>'Mesquite, TX', '1022561'=>'Mesquite, TX', '9026880'=>'Mesquite, TX', '9026900'=>'Mesquite, TX', '9026901'=>'Mesquite, TX', '9026899'=>'Mesquite, TX', '9026881'=>'Mesquite, TX', '9027479'=>'Mexia, TX', '9027260'=>'Mexia, TX', '1026622'=>'Midlothian, TX', '9027232'=>'Midlothian, TX', '9026997'=>'Mt. Pleasant, TX', '9052495'=>'Murphy, TX', '9026848'=>'Murphy, TX', '9026898'=>'Nevada, TX', '9026830'=>'Oak Point, TX', '9027394'=>'Palo Pinto, TX', '9027237'=>'Paradise, TX', '9027314'=>'Pilot Point, TX', '1016775'=>'Plano, TX', '1026695'=>'Plano, TX', '9026804'=>'Plano, TX', '9026834'=>'Plano, TX', '9026802'=>'Plano, TX', '9026803'=>'Plano, TX', '9026847'=>'Plano, TX', '9026835'=>'Plano, TX', '9026795'=>'Plano, TX','1026710'=>'Pottsboro, TX', '9026836'=>'Pottsboro, TX', '9026958'=>'Princeton, TX', '9027315'=>'Ponder, TX', '9026838'=>'Prosper, TX', '1026726'=>'Red Oak, TX', '9026884'=>'Red Oak, TX', '1026729'=>'Richardson, TX', '9026840'=>'Richardson, TX', '9026839'=>'Richardson', '9026841'=>'Richardson, TX', '9060114'=>'Richland College, TX', '1026734'=>'Roanoke, TX', '9027317'=>'Roanoke, TX', '1026741'=>'Rockwall, TX', '9026806'=>'Rockwall, TX', '9026842'=>'Rockwall, TX', '1026750'=>'Rowlett, TX', '9026843'=>'Rowlett, TX', '9026844'=>'Rowlett, TX', '1026751'=>'Royse City, TX', '1026755'=>'Sachse, TX', '9026816'=>'Sachse, TX', '9027283'=>'Saginaw, TX', '9027204'=>'Sanctuary, TX', '9026889'=>'Seagoville, TX', '9026875'=>'Seven Points, TX', '9027291'=>'Shady Shores, TX', '1026788'=>'Sherman, TX', '9026846'=>'Sherman, TX', '9027240'=>'Springtown, TX', '1026836'=>'The Colony, TX', '9026890'=>'Terrell, TX', '9026845'=>'Tom Bean, TX', '9053028'=>'University Park, TX', '9027025'=>'Van Alstyne, TX', '9027241'=>'Venus, TX', '1026867'=>'Waco, TX', '9027503'=>'Waco, TX', '9027276'=>'Watauga, TX', '9027278'=>'Watauga, TX', '1026873'=>'Waxahachie, TX', '9026895'=>'Waxahachie, TX', '9026893'=>'Waxahachie, TX', '9053756'=>'Westminster, TX', '9026849'=>'Weston, TX', '1026885'=>'Whitesboro, TX', '9027021'=>'Whitewright', '9026896'=>'Wills Point, TX', '9027186'=>'Woodville, TX', '1026899'=>'Wylie, TX', '9026850'=>'Wylie, TX', '9027027'=>'Yantis, TX');

				elseif ( $customer_info['google-ad-loc'] == "East TX" ) :
					$cities = array('9027058'=>'Carthage, TX', '9027113'=>'Quitman, TX', '1026855'=>'Tyler, TX', '9027086'=>'Tyler, TX', '9027087'=>'Tyler, TX');

				elseif ( $customer_info['google-ad-loc'] == "Houston" ) :
					$cities = array('9027599'=>'Houston, TX', '9027649'=>'Houston, TX', '9027667'=>'Houston, TX', '9027602'=>'Houston, TX', '9027662'=>'Houston, TX', '9027677'=>'Conroe, TX', '9027792'=>'Katy, TX', '9027786'=>'Prairie View, TX', '9027730'=>'Richmond, TX');

				elseif ( $customer_info['google-ad-loc'] == "OKC" ) :
					$cities = array('1024290'=>'Oklahoma City, OK', '9026250'=>'Oklahoma City, OK', '9026287'=>'Oklahoma City, OK', '9026254'=>'Oklahoma City, OK', '9026253'=>'Oklahoma City, OK', '9026270'=>'Oklahoma City, OK', '9026281'=>'Oklahoma City, OK', '9026259'=>'Oklahoma City, OK', '9026288'=>'Oklahoma City, OK', '9026267'=>'Oklahoma City, OK', '9026273'=>'Oklahoma City, OK', '9026274'=>'Oklahoma City, OK', '9026262'=>'Oklahoma City, OK', '9026248'=>'Oklahoma City, OK', '9026255'=>'Oklahoma City, OK', '9026278'=>'Oklahoma City, OK', '9026268'=>'Oklahoma City, OK', '9026261'=>'Oklahoma City, OK', '9026286'=>'Oklahoma City, OK', '9026276'=>'Oklahoma City, OK', '9026246'=>'Oklahoma City, OK', '9026271'=>'Oklahoma City, OK', '9026283'=>'Oklahoma City, OK', '9026284'=>'Oklahoma City, OK', '9026245'=>'Oklahoma City, OK', '9026260'=>'Oklahoma City, OK', '9026249'=>'Oklahoma City, OK', '9026170'=>'Arcadia, OK', '9059026'=>'Blaine County, OK', '9026183'=>'Choctaw, OK', '9026181'=>'Chickasha, OK', '1024115'=>'Crescent, OK', '9026189'=>'Altus, OK', '9026256'=>'Del City, OK', '9026418'=>'Dover, OK', '1024188'=>'Edmond, OK', '9026176'=>'Edmond, OK', '9026193'=>'Edmond, OK', '9026166'=>'Edmond, OK', '1024188'=>'Edmond, OK', '9026186'=>'Edmond, OK', '9026194'=>'El Reno, OK', '1024215'=>'Guthrie, OK', '9026425'=>'Hennessey, OK', '9026204'=>'Jones, OK', '9026298'=>'Kingston, OK', '1024252'=>'Langston, OK', '9026206'=>'Lexington, OK', '9026200'=>'Logan County, OK', '9026252'=>'Midwest City, OK', '9052435'=>'Midwest City, OK', '9026269'=>'Midwest City, OK', '9052474'=>'Moore, OK', '9026282'=>'Moore, OK', '9026218'=>'Mustang, OK', '9026219'=>'Newcastle, OK', '9026222'=>'Norman, OK', '9026223'=>'Norman, OK', '9026224'=>'Norman, OK', '9026514'=>'Pawnee County, OK', '9026229'=>'Piedmont, OK', '1024306'=>'Ponca City, OK', '9026716'=>'Pontotoc, OK', '1024330'=>'Shawnee, OK', '9026535'=>'Stroud, OK', '9026235'=>'Stroud, OK', '9026234'=>'Sulphur, OK', '9026564'=>'Tulsa, OK', '9026448'=>'Woodward, OK', '9026243'=>'Yukon, OK');
				endif;

				if ( is_array($cities) && array_key_exists($value, $cities)) :
					$userLoc = $cities[$value];
				else:
					$saveLocInfo = get_option('bp_loc_info') ? get_option('bp_loc_info') : array();
					if ( !in_array( $value, $saveLocInfo )) :
						array_push($saveLocInfo, $value);
						updateOption('bp_loc_info', $saveLocInfo, false);
					endif;
				endif;		    
			 endif;
		endforeach;

// Does this user come from a location specific landing page?
	elseif ( preg_match('/-[a-z]{2}$/', $page_slug) === 1 && !in_array( substr($page_slug, -2), $common) ) :
		$pieces = explode(' ', ucwords(str_replace('-', ' ', $page_slug))); 
		$state = array_pop($pieces);
		$city = implode(' ', $pieces);
		$userLoc = $city.', '.strtoupper($state);

// Does this user's geo location match a service area?
	elseif ( isset($_COOKIE['user-city']) && findCity($_COOKIE['user-city']) ) : 
		$userLoc = $_COOKIE['user-city'].', '.$_COOKIE['user-region'];

// If no location is known, then set to default
	elseif ( isset($_COOKIE['user-display-loc']) ) : 
		$userLoc = $_COOKIE['user-display-loc'];
	endif;					

	if ( $userLoc !== '' ) :
		if ( !defined('_USER_DISPLAY_LOC') ) define( '_USER_DISPLAY_LOC', $userLoc );
		setcookie('user-display-loc', $userLoc, 0, "/");
	endif;
endif;

/*--------------------------------------------------------------
# Anylytics XML
--------------------------------------------------------------*/
if ( _PAGE_SLUG == "wp-cron.php" ) :
	require_once get_template_directory().'/functions-admin-stats.php';
	$siteAudit = get_option('bp_site_audit_details');
	if ( $siteAudit && is_array($siteAudit) ) :
		$recent = array_key_last($siteAudit);
		$data = $siteAudit[$recent]; 

		$file_contents = '<?xml version = "1.0" encoding = "UTF-8" standalone = "yes" ?><audit>';
		$file_contents .= '<detail><email>'.$customer_info['email'].'</email></detail>';	
		$file_contents .= '<detail><version>'._BP_VERSION.'</version></detail>';	
		$file_contents .= '<detail><viewed>'.get_option('last_visitor_time').'</viewed></detail>';	
		$file_contents .= '<detail><week>'.$GLOBALS['ga4_visitor']['page-views-7'].'</week></detail>';	
		$file_contents .= '<detail><month>'.$GLOBALS['ga4_visitor']['page-views-30'].'</month></detail>';	
		$file_contents .= '<detail><quarter>'.$GLOBALS['ga4_visitor']['page-views-90'].'</quarter></detail>';	
		$file_contents .= '<detail><semester>'.$GLOBALS['ga4_visitor']['page-views-180'].'</semester></detail>';	
		$file_contents .= '<detail><year>'.$GLOBALS['ga4_visitor']['page-views-365'].'</year></detail>';	
		$file_contents .= '<detail><date>'.$recent.'</date></detail>';

		foreach($data as $criteria=>$value) :
			if ( $criteria != "notes" ) $file_contents .= '<detail><'.$criteria.'>'.$value.'</'.$criteria.'></detail>';
		endforeach;

		$file_contents .= '</audit>';

		$my_file = fopen("analytics_index.xml", "w");
		fwrite($my_file, $file_contents);
		fclose($my_file); 
	endif;
endif; 

/*--------------------------------------------------------------
# Send data to 'Site Checkin' (change-driven base, timed metrics)
--------------------------------------------------------------*/
if (is_admin() || (defined('WP_CLI') && WP_CLI)) return;
if (defined('_IS_SERP_BOT') && _IS_SERP_BOT) return;

$now = time();
$base_info = [
	'name'      => $customer_info['name'] ?? '',
	'framework' => (defined('_BP_VERSION') ? _BP_VERSION : ''),
];
$base_hash = md5(wp_json_encode($base_info));
$prev_hash = (string)get_option('bp_state_hash', '');
//$metrics_due = ($now - (int)get_option('bp_state_last_metrics', 0)) >= 43200;
$metrics_due = true;

// Only proceed if something changed OR metrics are due
//if ($base_hash === $prev_hash && !$metrics_due) return;

// Start payload with base info
$info = $base_info + ['ts' => $now];

// Enrich metrics every 12h
if ($metrics_due) {
	require_once get_template_directory().'/functions-admin-stats.php';
	$g = $GLOBALS['ga4_visitor'] ?? [];
	$info += [
		'hits_week'     => $g['page-views-7']   ?? 0,
		'hits_month'    => $g['page-views-30']  ?? 0,
		'hits_quarter'  => $g['page-views-90']  ?? 0,
		'hits_year'     => $g['page-views-365'] ?? 0,
		'mobile_speed'  => $g['ck_mobile_speed'].'s',
		'desktop_speed'  => $g['ck_desktop_speed'].'s',
	];
	update_option('bp_state_last_metrics', $now);
}

// Atomic write to /wp-content/bp-guard/state.json
$base = WP_CONTENT_DIR . '/bp-guard';
if (!is_dir($base)) wp_mkdir_p($base);
$file = $base . '/state.json';
$tmp  = $file . '.tmp';

$enc = wp_json_encode($info);
if ($enc !== false) {
	@file_put_contents($tmp, $enc, LOCK_EX);
	@chmod($tmp, 0664);
	@rename($tmp, $file);
	update_option('bp_state_hash', $base_hash);
}