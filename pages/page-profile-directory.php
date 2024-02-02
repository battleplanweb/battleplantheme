<?php
/* Battle Plan Web Design - User Profile List Page */

$search = ( isset($_GET["as"]) ) ? sanitize_text_field($_GET["as"]) : false ;
$page = (get_query_var('paged')) ? get_query_var('paged') : 1;
$offset = ($page - 1) * $number;
$GLOBALS['roles'] = array();
$GLOBALS['number'] = -1;
$GLOBALS['grid'] = "6e"; 
$GLOBALS['displayInfo'] = array ( 'name', 'username', 'role' ); // 'name', 'nickname', 'username', 'login', 'first name', 'last name', 'email', 'role'
$GLOBALS['valign'] = "stretch";
$GLOBALS['size'] = "thumbnail";
$GLOBALS['picSize'] = "100";
$GLOBALS['textSize'] = "100";
$GLOBALS['countTease'] = "false";
$GLOBALS['countView'] = "false";
$GLOBALS['addClass'] = "";
$GLOBALS['archiveHeadline'] = "Member Directory";
$GLOBALS['archiveIntro'] = "";

if ( function_exists( 'overrideArchive' ) ) { overrideArchive( 'profiles' ); }

$args = array( 'role__in' => $GLOBALS['roles'], 'offset' => $offset, 'number' => $GLOBALS['number'] );	

$sortBoxChoices = array( array( 'last-login-when', 'Recent Activity', 'meta_value_num', 'desc' ), array( 'last_name', 'Last Name', 'meta_value', 'asc'), array ( 'first_name', 'First Name', 'meta_value', 'asc') );

$sort = get_user_meta( _USER_ID, 'profile-sort', true );

foreach ($sortBoxChoices as $sortBoxChoice ) :
	if ( $sort == $sortBoxChoice[0] ) : $args['meta_key']=$sortBoxChoice[0]; $args['orderby']=$sortBoxChoice[3]; $args['order'] = $sortBoxChoice[4]; endif;
endforeach;

$allProfiles = new WP_User_Query($args);
$profiles = $allProfiles->get_results();
$buildList = "";

if ( !empty($profiles) ) :
  	foreach($profiles as $user) :
		$profileID = $user->ID;	
		$num = 1;
		$displays = $GLOBALS['displayInfo'];
		
		if ( $user->user_login != "battleplanweb" ) :		
			$buildList .= '<a href="/profile?user='.$profileID.'" class="link-archive link-profiles" data-id="'.$profileID.'" data-user="'.$user->display_name.'">';
			$buildList .= '[col]';
			$buildList .= '[get-user user="'.$profileID.'" info="avatar"]';			
			$buildList .= '<div class="directory-user-info">';			
			foreach ($displays as $display) :
				$buildList .= '<span class="user-'.$display.'">[display-user user="'.$profileID.'" info="'.$display.'" link="false"]</span>';
			endforeach;			
			$buildList .= '</div>[/col]</a>'; 
		endif; 
	endforeach;
else:
 	$buildList .= '[col]<p>No profiles found.</p>[/col]';
endif; 

$displayArchive = '<header class="archive-header">';
	$displayArchive .= '<h1 class="page-headline archive-headline profiles-headline">'.$GLOBALS["archiveHeadline"].'</h1>';
	$displayArchive .= '<div class="archive-description archive-intro profiles-intro">'.$GLOBALS["archiveIntro"].'</div>'; 
$displayArchive .= '</header><!-- .archive-header-->';

$sortBox = '<div class="profile-bar"><span class="icon sort"></span><select name="sort" id="sort-box">';
foreach ($sortBoxChoices as $sortBoxChoice) :
	$sortBox .= '<option ';
	if ( $sort == $sortBoxChoice[0] ) $sortBox .= 'selected="selected"';
	$sortBox .= 'value="'.$sortBoxChoice[0].'">'.$sortBoxChoice[1].'</option>';
endforeach;
$sortBox .= '</select></div>';

$searchBox = '<div class="profile-bar"><span class="icon sort"></span><input type="text" id="search-box" /></div>';

$displayArchive .= do_shortcode('[section width="inline" class="sort-box search-box"][layout grid="1-1"][col]'.$sortBox.'[/col][col]'.$searchBox.'[/col][/layout][/section]');

$displayArchive .= do_shortcode('[section width="inline" class="archive-content archive-profiles"][layout grid="'.$GLOBALS["grid"].'" valign="'.$GLOBALS["valign"].'"]'.$buildList.'[/layout][/section]');
		
$displayArchive .= '<footer class="archive-footer">';
	$displayArchive .= get_the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => _x( '<span class="icon chevron-left" aria-hidden="true"></span><span class="sr-only">Previous set of posts</span>', 'Previous set of posts' ), 'next_text' => _x( '<span class="icon chevron-right" aria-hidden="true"></span><span class="sr-only">Next set of posts</span>', 'Next set of posts' ), ));
$displayArchive .= '</footer><!-- .archive-footer-->'; 
 
$restrictedMsg = '<h1>Log In</h1><h3>To Access The Directory</h3>'.do_shortcode('[get-login]');
$restrictCode = do_shortcode('[restrict max="none"]'.$restrictedMsg.'[/restrict]');	
$pageCode = do_shortcode('[restrict max="administrator" min="member"]'.$displayArchive.'[/restrict]');	

return $restrictCode.$pageCode;
?>