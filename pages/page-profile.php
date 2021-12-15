<?php
/* Battle Plan Web Design - User Profile Page */

$profileVar = do_shortcode('[get-url-var var="user"]');
$profileID = do_shortcode('[get-user user="'.$profileVar.'" info="id"]');
$currUserID = wp_get_current_user()->ID;

if ( !$profileVar || $profileID == $currUserID ) : $currUser = true; 
else: $currUser = false; endif;
$profileFirst = do_shortcode('[get-user user="'.$profileVar.'" info="first"]');
$profileLast = do_shortcode('[get-user user="'.$profileVar.'" info="last"]');
$profileDisplay = do_shortcode('[get-user user="'.$profileVar.'" info="display"]');
$profileNickname = do_shortcode('[get-user user="'.$profileVar.'" info="nickname"]');
$profileUsername = do_shortcode('[get-user user="'.$profileVar.'" info="username"]');
$profileAvatar = do_shortcode('[get-user user="'.$profileVar.'" info="avatar"]');
$profileDesc = get_the_author_meta( 'description', $profileID );
$profileEmail = get_the_author_meta( 'user_email', $profileID );
$profileFacebook = get_the_author_meta( 'facebook', $profileID );
$profileTwitter = get_the_author_meta( 'twitter', $profileID );
$profileInstagram = get_the_author_meta( 'instagram', $profileID );
$profileLinkedIn = get_the_author_meta( 'linkedin', $profileID );
$profilePinterest = get_the_author_meta( 'pinterest', $profileID );
$profileYouTube = get_the_author_meta( 'youtube', $profileID );
if ( $profileEmail ) $profileEmailBtn = do_shortcode('[social-btn type="email" link="'.$profileEmail.'"]');
if ( $profileFacebook ) $profileFacebookBtn = do_shortcode('[social-btn type="facebook" link="'.$profileFacebook.'"]');
if ( $profileTwitter ) $profileTwitterBtn = do_shortcode('[social-btn type="twitter" link="'.$profileTwitter.'"]');
if ( $profileInstagram ) $profileInstagramBtn = do_shortcode('[social-btn type="instagram" link="'.$profileInstagram.'"]');
if ( $profileLinkedIn ) $profileLinkedInBtn = do_shortcode('[social-btn type="linkedin" link="'.$profileLinkedIn.'"]');
if ( $profilePinterest ) $profilePinterestBtn = do_shortcode('[social-btn type="pinterest" link="'.$profilePinterest.'"]');
if ( $profileYouTube ) $profileYouTubeBtn = do_shortcode('[social-btn type="youtube" link="'.$profileYouTube.'"]');


if ( $currUser == true ) : $printPage = '<h1>Your Profile</h1>';	
else: $printPage = '<h1>User Profile</h1>'; endif;
 
$printPage .= '[section name="'.$profileUsername.'-user-info" class="user-profile-section" grid="1-auto"]';

if (function_exists('battleplan_userProfileAvatar')) : $printPage .= battleplan_userProfileAvatar($profileID, $currUser);
else :
	$printPage .= '[col class="avatar"]'.$profileAvatar.'[/col]';
endif;

if (function_exists('battleplan_userProfileInfo')) : $printPage .= battleplan_userProfileInfo($profileID, $currUser);
else :
	$printPage .= '[col class="user-info" valign="center"][txt]';
	$printPage .= '<p class="user-name">';
	if ( $profileDisplay ) : $printPage .= $profileDisplay;
	elseif ( $profileFirst || $profileLast ) :
		if ( $profileFirst ) $printPage .= $profileFirst;
		if ( $profileLast ) $printPage .= ' '.$profileLast;
	endif;
	$printPage .= '<br>';
	if ( $profileUsername ) $printPage .= '<span class="user-username"><i class="fas fa-user fa"></i> '.$profileUsername.'</span></p>';
	if ( $profileDesc ) $printPage .= '<p>'.$profileDesc.'</p>';
	if ( $currUser == true ) $printPage .= '<p>[get-upload-btn text="Change Avatar: "]</p>';
	$printPage .= '[/txt][/col]';
endif;

$printPage .= '[/section]';

if ( $currUser == true ) :
	$printPage .= '[section name="'.$profileUsername.'-update-info" class="user-profile-section" grid="1"]';

	$printPage .= '<h2>Update Your Info</h2>';
	$printPage .= '<form id="userinfo-update" method="post" enctype="multipart/form-data">';
	$printPage .= '<input type="hidden" name="user_info_upload" value="1" />';

	if (function_exists('battleplan_userUpdateInfo')) : $printPage .= battleplan_userUpdateInfo($profileID, $currUser);
	else :
		$printPage .= '[layout grid="1-1"]';
		$printPage .= '[col]<p><label for="first_name">First Name: <input type="text" name="first_name" id="first_name" value="'.$profileFirst.'" class="regular-text"></label></p>';
		$printPage .= '<p><label for="last_name">Last Name: <input type="text" name="last_name" id="last_name" value="'.$profileLast.'" class="regular-text"></label></p>';
		$printPage .= '<p><label for="email">Email: <input type="text" name="email" id="email" value="'.$profileEmail.'" class="regular-text"></label></p>[/col]';	
		$printPage .= '[col]<p><label for="bio">Bio: <textarea type="text" name="bio" id="bio" class="regular-text">'.$profileDesc.'</textarea></label></p>[/col]';
		$printPage .= '[/layout]';
	endif;
	
	if (function_exists('battleplan_userUpdateAfterInfo')) $printPage .= battleplan_userUpdateAfterInfo($profileID, $currUser);
		
	if (function_exists('battleplan_userUpdateSocial')) : $printPage .= battleplan_userUpdateSocial($profileID, $currUser);
	else :
		$printPage .= '[layout grid="1-1"]';
		$printPage .= '[col]<p><label for="facebook">Facebook: <input type="text" name="facebook" id="facebook" value="'.$profileFacebook.'" class="regular-text"></label></p>';
		$printPage .= '<p><label for="twitter">Twitter: <input type="text" name="twitter" id="twitter" value="'.$profileTwitter.'" class="regular-text"></label></p>';
		$printPage .= '<p><label for="instagram">Instagram: <input type="text" name="instagram" id="instagram" value="'.$profileInstagram.'" class="regular-text"></label></p>[/col]';
		$printPage .= '[col]<p><label for="linkedin">LinkedIn: <input type="text" name="linkedin" id="linkedin" value="'.$profileLinkedIn.'" class="regular-text"></label></p>';
		$printPage .= '<p><label for="pinterest">Pinterest: <input type="text" name="pinterest" id="pinterest" value="'.$profilePinterest.'" class="regular-text"></label></p>';
		$printPage .= '<p><label for="youtube">YouTube: <input type="text" name="youtube" id="youtube" value="'.$profileYouTube.'" class="regular-text"></label></p>[/col]';
		$printPage .= '[/layout]';
	endif;		
	
	if (function_exists('battleplan_userUpdateAfterSocial')) $printPage .= battleplan_userUpdateAfterSocial($profileID, $currUser);
		
	$printPage .= '[layout grid="1"][col]<input class="info-submit-btn" type="submit" name="submit" value="Update Info" />[/col][/layout]';
	$printPage .= '</form>[/section]';
endif;

if (function_exists('battleplan_userProfileMisc1')) $printPage .= battleplan_userProfileMisc1($profileID, $currUser);

if ( $currUser == true ) :
	$printPage .= '[section name="'.$profileUsername.'-update-status" class="user-profile-section" grid="1"]';

	$printPage .= '<h2>Post To The Wall</h2>';
	$printPage .= '<form id="wall-update" method="post" enctype="multipart/form-data">';
	$printPage .= '<input type="hidden" name="user_post_update" value="1" />';
	$printPage .= '[layout grid="1"][col]';
	$printPage .= '<label for="title">Title<input type="text" class="regular-text" name="title" /></label><br/>';
	$printPage .= '<label for="content">What\'s on your mind?<textarea class="regular-text" rows="8" name="content"></textarea></label><br/>';
	//$printPage .= '<label class="control-label">Choose Category</label>';
	//$printPage .= '<select name="category" class="form-control">';
	//$catList = get_categories();
	//foreach($catList as $listval) $printPage .= '<option value="'.$listval->term_id.'">'.$listval->name.'</option>';
	//$printPage .= '</select>';
	//$printPage .= '<label class="control-label">Upload Post Image</label>';
	//$printPage .= '<input type="file" name="sample_image" class="form-control" />';
	$printPage .= '[/col][/layout]';

	$printPage .= '[layout grid="1"][col]<input class="info-submit-btn" type="submit" name="submit" value="Submit" />[/col][/layout]';	
	$printPage .= '[/section]';

endif;

if (function_exists('battleplan_userProfileMisc2')) $printPage .= battleplan_userProfileMisc2($profileID, $currUser);

$restrictedMsg = '<h1>Log In</h1><h3>To Access Profile Pages</h3>'.do_shortcode('[get-login]');
$restrictCode = do_shortcode('[restrict max="none"]'.$restrictedMsg.'[/restrict]');	
$pageCode = do_shortcode('[restrict max="administrator" min="member"]'.$printPage.'[/restrict]');	
	
	//$buildTest .= '<u>Code Used</u>: '.get_user_meta( $profileID, 'invite_code_used', true).'<br>';
	//$buildTest .= '<u>Sponsor</u>: '.do_shortcode('[get-user user="'.get_user_meta( $profileID, 'sponsor-member', true).'" info="username"]').'<br><br>';

return $restrictCode.$pageCode;
?>