<?php
/* Battle Plan Web Design - User Profile List Page */

$search = ( isset($_GET["as"]) ) ? sanitize_text_field($_GET["as"]) : false ;
$page = (get_query_var('paged')) ? get_query_var('paged') : 1;
$offset = ($page - 1) * $number;
$roles = array();
$number = -1;
$grid = "6e"; 
$order = "asc";
$orderby = "display_name"; // display_name, name, login, email, registered (date), post_count, ID
$displayInfo = array ( 'display name', 'role' ); // 'display name', 'nickname', 'username', 'login', 'first name', 'last name', 'email', 'role'
$valign = "stretch";
$size = "thumbnail";
$picSize = "100";
$textSize = "100";
$countTease = "false";
$countView = "false";
$addClass = "";
$archiveHeadline = "Member Directory";
$archiveIntro = "";

if ( function_exists( 'overrideArchive' ) ) { overrideArchive( 'profiles' ); }

if ($search) : $allProfiles = new WP_User_Query( array( 'role__in' => $roles, 'orderby' => $orderby, 'order' => $order, 'search' => '*'. $search.'*' ));
else : $allProfiles = new WP_User_Query( array( 'role__in' => $roles, 'orderby' => $orderby, 'order' => $order, 'offset' => $offset, 'number' => $number ));	
endif;

$profiles = $allProfiles->get_results();
$buildList = "";

/*
?>

  <div class="author-search">
  <h2>Search authors by name</h2>
    <form method="get" id="sul-searchform" action="<?php the_permalink() ?>">
      <label for="as" class="assistive-text">Search</label>
      <input type="text" class="field" name="as" id="sul-s" placeholder="Search Authors" />
      <input type="submit" class="submit" name="submit" id="sul-searchsubmit" value="Search Authors" />
    </form>
  <?php
  if($search){ ?>
    <h2 >Search Results for: <em><?php echo $search; ?></em></h2>
    <a href="<?php the_permalink(); ?>">Back To Author Listing</a>
  <?php } ?>

  </div><!-- .author-search -->
  
  */ 
  
if ( !empty($profiles) ) :
  	foreach($profiles as $user) :
		$profileID = $user->ID;	
		$num = 1;
		
		if ( $user->user_login != "battleplanweb" ) :		
			$buildList .= '[col]';
			$buildList .= '<a href="/profile?user='.$profileID.'" class="link-archive link-profiles" ada-hidden="true"  tabindex="-1">[get-user user="'.$profileID.'" info="avatar"]</a>';	
			$buildList .= '<div class="directory-user-info">';
			
			foreach ($displayInfo as $display) :
				if ( $num == 1 ) :
					$buildList .= do_shortcode('[display-user user="'.$profileID.'"]');					
				else : $buildList .= '<span class="display-info display-'.$display.'">[get-user user="'.$profileID.'" info="'.$display.'"]</span><br/>';
				endif;
				$num++;
			endforeach;
			
			$buildList .= '</div>[/col]'; 
		endif;
		
		/*
      <p><?php echo $userInfo->description; ?></p>
      <?php $latest_post = new WP_Query( "author=$profileID&post_count=1" );
      if (!empty($latest_post->post)){ ?>
      <p><strong>Latest Article:</strong>
      <a href="<?php echo get_permalink($latest_post->post->ID) ?>">
        <?php echo get_the_title($latest_post->post->ID) ;?>
      </a></p>
      <?php } //endif ?>
      <p><a href="<?php echo get_author_posts_url($profileID); ?> ">Read <?php echo $userInfo->display_name; ?> posts</a></p>
    </li>
    <?php
	*/
	endforeach;
else:
 	$buildList .= '[col]<p>No profiles found.</p>[/col]';
endif; 

$displayArchive = '<header class="archive-header">';
	$displayArchive .= '<h1 class="page-headline archive-headline profiles-headline">'.$archiveHeadline.'</h1>';
	$displayArchive .= '<div class="archive-description archive-intro profiles-intro">'.$archiveIntro.'</div>'; 
$displayArchive .= '</header><!-- .archive-header-->';
		
$displayArchive .= do_shortcode('[section width="inline" class="archive-content archive-profiles"][layout grid="'.$grid.'" valign="'.$valign.'"]'.$buildList.'[/layout][/section]');
		
$displayArchive .= '<footer class="archive-footer">';
	$displayArchive .= get_the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => _x( '<i class="fa fa-chevron-left"></i><span class="sr-only">Previous set of posts</span>', 'Previous set of posts' ), 'next_text' => _x( '<i class="fa fa-chevron-right"></i><span class="sr-only">Next set of posts</span>', 'Next set of posts' ), ));
$displayArchive .= '</footer><!-- .archive-footer-->'; 
 
$restrictedMsg = '<h1>Log In</h1><h3>To Access The Directory</h3>'.do_shortcode('[get-login]');
$restrictCode = do_shortcode('[restrict max="none"]'.$restrictedMsg.'[/restrict]');	
$pageCode = do_shortcode('[restrict max="administrator" min="member"]'.$displayArchive.'[/restrict]');	

return $restrictCode.$pageCode;
?>