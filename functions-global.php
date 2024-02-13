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

if ( !defined('_BP_VERSION') ) define( '_BP_VERSION', '23.6' );
update_option( 'battleplan_framework', _BP_VERSION, false );

if ( !defined('_BP_NONCE') ) define( '_BP_NONCE', base64_encode(random_bytes(20)) );
if ( !defined('_HEADER_ID') ) define( '_HEADER_ID', get_page_by_path('site-header', OBJECT, 'elements')->ID ); 
if ( !defined('_USER_LOGIN') ) define( '_USER_LOGIN', wp_get_current_user()->user_login );
if ( !defined('_USER_ID') ) define( '_USER_ID', wp_get_current_user()->ID );

if ( _USER_LOGIN == 'battleplanweb' ) :
	//if ( !defined('WP_DEBUG' )) define('WP_DEBUG', true);
    //@ini_set('display_errors', 1);
   	//@error_reporting(E_ALL);
endif;

$googlebots = array( 'google', 'lighthouse' );
$bots = array_merge(array('adbeat', 'addthis', 'admantx', 'audit', 'barkrowler', 'bing', 'bot', 'crawler', 'dataprovider', 'daum', 'docomo', 'duckduck', 'facebook', 'fetcher', 'gigablast', 'linkedin', 'majestic', 'netcraft', 'newspaper', 'okhttp', 'panscient', 'qwantify', 'riddler', 'wayback', 'slurp', 'spider', 'wordpress', 'yahoo', 'yeti', 'zgrab'), $googlebots);
$spamIPs = get_option('bp_bad_ips') ? get_option('bp_bad_ips') : array();
$spamURLs = explode("\n", file_get_contents( get_template_directory().'/spammers.txt' ));
//https://github.com/matomo-org/referrer-spam-list/blob/master/spammers.txt

foreach ( $googlebots as $googlebot ) if ( isset($_SERVER["HTTP_USER_AGENT"]) && stripos( $_SERVER["HTTP_USER_AGENT"], $googlebot) !== false && !defined('_IS_GOOGLEBOT') ) define( '_IS_GOOGLEBOT', true );
foreach ( $bots as $bot ) if ( isset($_SERVER["HTTP_USER_AGENT"]) && stripos( $_SERVER["HTTP_USER_AGENT"], $bot) !== false && !defined('_IS_BOT') ) define( '_IS_BOT', true );
foreach ( $spamIPs as $spamIP ) if ( isset($_SERVER["REMOTE_ADDR"]) && stripos( $_SERVER["REMOTE_ADDR"], $spamIP) !== false && !defined('_IS_BOT') ) define( '_IS_BOT', true );


//temporary -> to log spammers + IPs -> when finished, delete this code and remove bp_log_spammers from options
	foreach ( $spamURLs as $spamURL ) :
		if ( isset($_SERVER["HTTP_REFERER"]) && $spamURL != '' && $spamURL != null && stripos( $_SERVER["HTTP_REFERER"], $spamURL) !== false && !defined('_IS_BOT') ) :
			$getList = get_option('bp_log_spammers') ? get_option('bp_log_spammers') : array();
			$getList[$spamURL] = $_SERVER["REMOTE_ADDR"] ;
			updateOption('bp_log_spammers', $getList);
		endif;
	endforeach;
 
if ( !defined('_IS_BOT') ) define( '_IS_BOT', false );
if ( !defined('_IS_GOOGLEBOT') ) define( '_IS_GOOGLEBOT', false );

//if ( _IS_BOT == true ) remove_shortcode('contact-form-7');			

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


/*--------------------------------------------------------------
# Customer Info Globals
--------------------------------------------------------------*/

$GLOBALS['customer_info'] = get_option('customer_info') ? get_option('customer_info') : array();
$currYear=date("Y"); 
$startYear = $GLOBALS['customer_info']['year'] ? $GLOBALS['customer_info']['year'] : 0;
$GLOBALS['customer_info']['copyright'] = $startYear == $currYear ? "© ".$currYear : "© ".$startYear."-".$currYear; 
$GLOBALS['do_not_repeat'] = array(); 	
if ( !array_key_exists('copyright', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['copyright'] = '';
if ( !array_key_exists('name', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['name'] = '';
if ( !array_key_exists('area', $GLOBALS['customer_info']) ) $GLOBALS['customer_info']['area'] = '000';
if ( !array_key_exists('phone', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['phone'] = '000-0000';
if ( !array_key_exists('area-before', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['area-before'] = '()';
if ( !array_key_exists('area-after', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['area-after'] = ') ';
if ( !array_key_exists('street', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['street'] = '';
if ( !array_key_exists('city', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['city'] = '';
if ( !array_key_exists('state-abbr', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['state-abbr'] = '';
if ( !array_key_exists('state-full', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['state-full'] = '';
if ( !array_key_exists('zip', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['zip'] = '';
if ( !array_key_exists('email', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['email'] = '';

if ( !array_key_exists('service-areas', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['service-areas'] = array();
if ( !array_key_exists('site-type', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['site-type'] = '';
if ( !array_key_exists('cid', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['cid'] = null;
if ( !array_key_exists('pid', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['pid'] = 'false';
if ( !array_key_exists('pid-sync', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['pid-sync'] = 'false';
if ( !array_key_exists('lat', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['lat'] = null;
if ( !array_key_exists('long', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['long'] = null;
if ( !array_key_exists('schema', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['schema'] = 'false';

if ( !array_key_exists('default-loc', $GLOBALS['customer_info'] ) ) $GLOBALS['customer_info']['default-loc'] = $GLOBALS['customer_info']['city'].', '.$GLOBALS['customer_info']['state-abbr'];


/*--------------------------------------------------------------
# Google Ad Locations
--------------------------------------------------------------*/

if ( !is_admin() && !defined('_USER_LOCATION') ) :
	$cities = array('1026339'=>'Dallas, TX', '9026903'=>'Dallas, TX', '9026904'=>'Dallas, TX', '9026905'=>'Dallas, TX', '9026906'=>'Dallas, TX', '9026907'=>'Dallas, TX', '9026908'=>'Dallas, TX', '9026909'=>'Dallas, TX', '9026910'=>'Dallas, TX', '9026911'=>'Dallas, TX', '9026912'=>'Dallas, TX', '9026913'=>'Dallas, TX', '9026914'=>'Dallas, TX', '9026915'=>'Dallas, TX', '9026916'=>'Dallas, TX', '9026917'=>'Dallas, TX', '9026918'=>'Dallas, TX', '9026919'=>'Dallas, TX', '9026920'=>'Dallas, TX', '9026921'=>'Dallas, TX', '9026922'=>'Dallas, TX', '9026923'=>'Dallas, TX', '9026924'=>'Dallas, TX', '9026925'=>'Dallas, TX', '9026922'=>'Dallas, TX', '9026955'=>'Dallas, TX', '9026938'=>'Dallas, TX', '9026926'=>'Dallas, TX', '9026914'=>'Dallas, TX', '9026946'=>'Dallas, TX', '9026945'=>'Dallas, TX', '9026926'=>'Dallas, TX', '9026927'=>'Dallas, TX', '9026928'=>'Dallas, TX', '9026929'=>'Dallas, TX', '9026930'=>'Dallas, TX', '9026931'=>'Dallas, TX', '9026932'=>'Dallas, TX', '9026933'=>'Dallas, TX', '9026934'=>'Dallas, TX', '9026935'=>'Dallas, TX', '9026936'=>'Dallas, TX', '9026937'=>'Dallas, TX', '9026938'=>'Dallas, TX', '9026939'=>'Dallas, TX', '9026940'=>'Dallas, TX', '9026941'=>'Dallas, TX', '9026942'=>'Dallas, TX', '9026943'=>'Dallas, TX', '9026944'=>'Dallas, TX', '9026945'=>'Dallas, TX', '9026946'=>'Dallas, TX', '9026947'=>'Dallas, TX', '9026948'=>'Dallas, TX', '9026949'=>'Dallas, TX', '9026950'=>'Dallas, TX', '9026951'=>'Dallas, TX', '9026952'=>'Dallas, TX', '9026953'=>'Dallas, TX', '9026954'=>'Dallas, TX', '9026955'=>'Dallas, TX', '9041376'=>'Dallas, TX', '9059462'=>'Dallas County, TX', '1026411'=>'Fort Worth, TX', '9027250'=>'Fort Worth, TX', '9027277'=>'Fort Worth, TX', '9027251'=>'Fort Worth, TX', '9027258'=>'Fort Worth, TX', '9027265'=>'Fort Worth, TX', '9027284'=>'Fort Worth, TX', '9027252'=>'Fort Worth, TX', '9027285'=>'Fort Worth, TX', '9027266'=>'Fort Worth, TX', '9027253'=>'Fort Worth, TX', '9027264'=>'Fort Worth, TX', '9027273'=>'Fort Worth, TX', '1026171'=>'Addison, TX', '9026791'=>'Addison, TX', '9041344'=>'Addison, TX', '1026178'=>'Allen, TX', '9026797'=>'Allen, TX', '9027193'=>'Alvarado, TX', '1026187'=>'Anna, TX', '9026959'=>'Anna, TX', '1026193'=>'Argyle, TX', '9027199'=>'Arlington, TX', '9027200'=>'Arlington, TX', '9027198'=>'Arlington, TX', '9027194'=>'Arlington, TX', '9027202'=>'Arlington, TX', '9027188'=>'Arlington, TX', '1026200'=>'Aubrey, TX', '9027296'=>'Aubrey, TX', '9027206'=>'Bedford, TX', '9027205'=>'Bedford, TX', '9026964'=>'Bells, TX', '9027261'=>'Benbrook, TX', '9027255'=>'Benbrook, TX', '1026229'=>'Blue Ridge, TX', '9026973'=>'Blue Ridge, TX', '9026968'=>'Bonham, TX', '1020242'=>'Carrollton, TX', '9026793'=>'Carrollton, TX', '9026796'=>'Carrollton, TX', '9026794'=>'Carrollton, TX', '9026855'=>'Cedar Hill, TX', '9026972'=>'Celeste, TX', '1026278'=>'Celina, TX', '9027517'=>'Coleman, TX', '9027211'=>'Colleyville, TX', '9059448'=>'Collin County, TX', '9026975'=>'Commerce, TX', '1026317'=>'Coppell, TX', '9026798'=>'Coppell, TX', '9026851'=>'Coppell, TX', '9051771'=>'Corinth, TX', '9027293'=>'Corinth, TX', '9026859'=>'Corsicana, TX', '9026860'=>'Crandall, TX', '9027197'=>'Dalworthington Gardens, TX', '1026349'=>'Denison, TX', '1026350'=>'Denton, TX', '9027286'=>'Denton, TX', '9027292'=>'Denton, TX', '9027289'=>'Denton, TX', '9026861'=>'DeSoto, TX', '9026871'=>'Duncanville, TX', '9027006'=>'East Tawakoni, TX', '9026864'=>'Ennis, TX', '9027215'=>'Euless, TX', '9027214'=>'Euless, TX', '9051926'=>'Fairview, TX', '9026831'=>'Fairview, TX', '9059479'=>'Fannin County, TX', '9051933'=>'Farmers Branch, TX', '9026987'=>'Farmersville, TX', '9026902'=>'Fate, TX', '9026866'=>'Ferris, TX', '9026801'=>'Flower Mound, TX', '9026805'=>'Flower Mound, TX', '9028405'=>'Friona, TX', '1026407'=>'Frisco, TX', '9026807'=>'Frisco, TX', '9026808'=>'Frisco, TX', '1026419'=>'Garland, TX', '9026812'=>'Garland, TX', '9026814'=>'Garland, TX', '9026811'=>'Garland, TX', '9026813'=>'Garland, TX', '9026815'=>'Garland, TX', '1026439'=>'Grand Prairie, TX', '9026817'=>'Grand Prairie, TX', '9026818'=>'Grand Prairie, TX', '9026820'=>'Grand Prairie, TX', '9026819'=>'Grand Prairie, TX', '9027222'=>'Grapevine, TX', '9027246'=>'Grapevine, TX', '1026442'=>'Greenville, TX', '9026956'=>'Greenville, TX', '1026451'=>'Haltom City, TX', '9026867'=>'Heath, TX', '9026828'=>'Hickory Creek, TX', '9027107'=>'Hideaway, TX', '1026482'=>'Howe, TX', '9027244'=>'Hudson Oaks, TX', '9027225'=>'Hurst, TX', '9026873'=>'Hutchins, TX', '1026497'=>'Irving, TX', '9026827'=>'Irving, TX', '9026810'=>'Irving, TX', '9026826'=>'Irving, TX', '9026824'=>'Irving, TX', '9026825'=>'Irving, TX', '9026809'=>'Irving, TX', '1026518'=>'Keller, TX', '9027307'=>'Keller, TX', '9026877'=>'Lancaster, TX', '9026869'=>'Lancaster, TX',  '1019935'=>'Lewisville, TX', '9026829'=>'Lewisville, TX', '9026821'=>'Lewisville, TX', '9027055'=>'Longview, TX', '9052357'=>'Lucas, TX', '9026792'=>'Lucas, TX', '9027912'=>'Madisonville, TX', '9027230'=>'Mansfield, TX', '1026607'=>'McKinney, TX', '9026833'=>'McKinney, TX', '9026832'=>'McKinney, TX', '1026611'=>'Melissa, TX', '9026996'=>'Melissa, TX', '1026619'=>'Mesquite, TX', '1022561'=>'Mesquite, TX', '9026880'=>'Mesquite, TX', '9026900'=>'Mesquite, TX', '9026901'=>'Mesquite, TX', '9026899'=>'Mesquite, TX', '9026881'=>'Mesquite, TX', '9027232'=>'Midlothian, TX', '9026997'=>'Mt. Pleasant, TX', '9052495'=>'Murphy, TX', '9026848'=>'Murphy, TX', '9026898'=>'Nevada, TX', '9026830'=>'Oak Point, TX', '9027394'=>'Palo Pinto, TX', '9027237'=>'Paradise, TX', '1016775'=>'Plano, TX', '1026695'=>'Plano, TX', '9026804'=>'Plano, TX', '9026834'=>'Plano, TX', '9026802'=>'Plano, TX', '9026803'=>'Plano, TX', '9026847'=>'Plano, TX', '9026835'=>'Plano, TX', '9026795'=>'Plano, TX','1026710'=>'Pottsboro, TX', '9026836'=>'Pottsboro, TX', '9026958'=>'Princeton, TX', '9026838'=>'Prosper, TX', '1026726'=>'Red Oak, TX', '9026884'=>'Red Oak, TX', '1026729'=>'Richardson, TX', '9026840'=>'Richardson, TX', '9026839'=>'Richardson', '9026841'=>'Richardson, TX', '9060114'=>'Richland College, TX', '1026734'=>'Roanoke, TX', '1026741'=>'Rockwall, TX', '9026806'=>'Rockwall, TX', '9026842'=>'Rockwall, TX', '1026750'=>'Rowlett, TX', '9026843'=>'Rowlett, TX', '9026844'=>'Rowlett, TX', '1026751'=>'Royse City, TX', '1026755'=>'Sachse, TX', '9026816'=>'Sachse, TX', '9027283'=>'Saginaw, TX', '9026889'=>'Seagoville, TX', '9026875'=>'Seven Points, TX', '1026788'=>'Sherman, TX', '9027240'=>'Springtown, TX', '1026836'=>'The Colony, TX', '9026890'=>'Terrell, TX', '9026845'=>'Tom Bean, TX', '9053028'=>'University Park, TX', '9027025'=>'Van Alstyne, TX', '9027241'=>'Venus, TX', '1026867'=>'Waco, TX', '9027276'=>'Watauga, TX', '9027278'=>'Watauga, TX', '1026873'=>'Waxahachie, TX', '9026895'=>'Waxahachie, TX', '9026893'=>'Waxahachie, TX', '9053756'=>'Westminster, TX', '9026849'=>'Weston, TX', '1026885'=>'Whitesboro, TX', '9027021'=>'Whitewright', '9026896'=>'Wills Point, TX', '9027186'=>'Woodville, TX', '1026899'=>'Wylie, TX', '9026850'=>'Wylie, TX', '9027027'=>'Yantis, TX');

	$common = array('am', 'an', 'as', 'at', 'be', 'by', 'do', 'if', 'is', 'it', 'me', 'my', 'no', 'of', 'on', 'or', 'so', 'to', 'up', 'us', 'we');
	$location = 'none';

	// Google Ads > Location specific landing page > cookie

	// Does this user come from a Google Ad?
	foreach( $_GET as $key => $value ) :		
		if ( $key == "loc" || $key == "int" ) :
			if ( array_key_exists($value, $cities)) :
				$location = $cities[$value];
			else:
				$saveLocInfo = get_option('bp_loc_info') ? get_option('bp_loc_info') : array();
				if ( !in_array( $value, $saveLocInfo )) :
					array_push($saveLocInfo, $value);
					updateOption('bp_loc_info', $saveLocInfo, false);
				endif;
			endif;		    
		 endif;
	endforeach;
	
	$page_slug = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'); 

	if ( $location == "none" && ( ( preg_match('/-[a-z]{2}$/', $page_slug) === 1 && !in_array( substr($page_slug, -2), $common) ) || isset($_COOKIE['site-loc']) ) ) :
		if ( preg_match('/-[a-z]{2}$/', $page_slug) === 1 && !in_array( substr($page_slug, -2), $common) ) :
			$pieces = explode(' ', ucwords(str_replace('-', ' ', $page_slug))); // Is this a location specific landing page?
		else:
			$pieces = explode(' ', ucwords(str_replace('-', ' ', $_COOKIE['site-loc']))); // Has this user already had location set?
		endif;

		$state = strtoupper(substr(end($pieces), -2));
		array_pop($pieces);
		$city = implode(' ', $pieces);
		$location = $city.', '.$state;
	endif;

	define( '_USER_LOCATION', $location );
endif;

if ( !defined('_USER_LOCATION') ) define( '_USER_LOCATION', null );


/*--------------------------------------------------------------
# Icon Globals
--------------------------------------------------------------*/

$GLOBALS['icons'] = $GLOBALS['icon-css'] = array();

$GLOBALS['icons']['award'] = '\e09c';
$GLOBALS['icons']['award-alt'] = '\e09d';
$GLOBALS['icons']['calendar'] = '\e077';
$GLOBALS['icons']['calendar-clock'] = '\e078';
$GLOBALS['icons']['camera'] = '\e194';
$GLOBALS['icons']['camera-security'] = '\e195';
$GLOBALS['icons']['chain-link'] = '\e116';
$GLOBALS['icons']['checkmark-seal'] = '\e0a3';
$GLOBALS['icons']['chevron-down'] = '\e219';
$GLOBALS['icons']['chevron-left'] = '\e216';
$GLOBALS['icons']['chevron-right'] = '\e218';
$GLOBALS['icons']['chevron-up'] = '\e217';
$GLOBALS['icons']['clipboard-check'] = '\e06e';
$GLOBALS['icons']['cog'] = '\e0ea';
$GLOBALS['icons']['construction-concrete-truck'] = '\e0ef';
$GLOBALS['icons']['construction-foreman'] = '\e0f0';
$GLOBALS['icons']['email'] = '\e042';
$GLOBALS['icons']['facebook'] = '\e1c8';
$GLOBALS['icons']['family'] = '\e17a';
$GLOBALS['icons']['finger-1'] = '\e1a1';
$GLOBALS['icons']['finger-2'] = '\e1a2';
$GLOBALS['icons']['finger-3'] = '\e1a3';
$GLOBALS['icons']['finger-4'] = '\e1a4';
$GLOBALS['icons']['finger-5'] = '\e1a5';
$GLOBALS['icons']['handshake'] = '\e1af';
$GLOBALS['icons']['heartbeat'] = '\e12f';
$GLOBALS['icons']['home'] = '\e000';
$GLOBALS['icons']['house-magnify'] = '\e003';
$GLOBALS['icons']['instagram'] = '\e1c9';
$GLOBALS['icons']['leaf'] = '\e111';
$GLOBALS['icons']['lightbulb'] = '\e114';
$GLOBALS['icons']['linkedin'] = '\e1d1';
$GLOBALS['icons']['location'] = '\e0b9';
$GLOBALS['icons']['money-bag'] = '\e16d';
$GLOBALS['icons']['phone'] = '\e027';
$GLOBALS['icons']['pinterest'] = '\e1d0';
$GLOBALS['icons']['rocket'] = '\e105';
$GLOBALS['icons']['search'] = '\e0ce';
$GLOBALS['icons']['search-plus'] = '\e0cf';
$GLOBALS['icons']['sex-both'] = '\e18b';
$GLOBALS['icons']['sex-female'] = '\e189';
$GLOBALS['icons']['sex-male'] = '\e18a';
$GLOBALS['icons']['snapchat'] = '\e1d2';
$GLOBALS['icons']['sort'] = '\e22e';
$GLOBALS['icons']['star'] = '\e01b';
$GLOBALS['icons']['star-o'] = '\e01c';
$GLOBALS['icons']['star-half'] = '\e01d';
$GLOBALS['icons']['strong-arm'] = '\e24c';
$GLOBALS['icons']['thumbs-up'] = '\e1ad';
$GLOBALS['icons']['thumbs-up-alt'] = '\e1a6';
$GLOBALS['icons']['tiktok'] = '\e1cf';
$GLOBALS['icons']['twitter'] = '\e1cb';
$GLOBALS['icons']['twitter-classic'] = '\e1ca';
$GLOBALS['icons']['user'] = '\e0fb';
$GLOBALS['icons']['wind'] = '\e112';
$GLOBALS['icons']['x-large'] = '\e138';
$GLOBALS['icons']['yelp'] = '\e1ce';
$GLOBALS['icons']['youtube'] = '\e1cc';
$GLOBALS['icons']['youtube-words'] = '\e1cd';

?>