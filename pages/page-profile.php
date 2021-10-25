<?php
/* Battle Plan Web Design - User Profile Page */

$profileVar = do_shortcode('[get-url-var var="user"]');
$profileID = do_shortcode('[get-user user="'.$profileVar.'" info="id"]');
$currUserID = wp_get_current_user()->ID;

if ( !$profileVar || $profileID == $currUserID ) : $currUser = true; 
else: $currUser = false; endif;
$profileFirst = do_shortcode('[get-user user="'.$profileVar.'" info="first"]');
$profileLast = do_shortcode('[get-user user="'.$profileVar.'" info="last"]');
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

$printPage = '[section name="'.$profileFirst.'-'.$profileLast.'" class="user-profile" grid="1"]';

if ( $currUser == true ) : 
	$printPage .= '[col class="avatar"]'.$profileAvatar;
	$printPage .= '[get-upload-btn][/col]';
	
	$printPage .= '[col class="update"]<h2>Post An Update</h2>';
	$printPage .= '<form id="status-update" method="post" enctype="multipart/form-data">';
	$printPage .= '<input type="hidden" name="user_post_update" value="1" />';
	$printPage .= '<label for="title">Title<input type="text" class="regular-text" name="title" /></label><br/>';
	$printPage .= '<label for="content">What\'s on your mind?<textarea class="regular-text" rows="8" name="content"></textarea></label><br/>';
	//$printPage .= '<label class="control-label">Choose Category</label>';
	//$printPage .= '<select name="category" class="form-control">';
	//$catList = get_categories();
	//foreach($catList as $listval) $printPage .= '<option value="'.$listval->term_id.'">'.$listval->name.'</option>';
	//$printPage .= '</select>';
	//$printPage .= '<label class="control-label">Upload Post Image</label>';
	//$printPage .= '<input type="file" name="sample_image" class="form-control" />';
	$printPage .= '<input type="submit" class="info-submit-btn" name="submit_post" value="Submit Post" />';
	$printPage .= '[/col]';
	
	$printPage .= '[col class="gallery"]<h2>Add Photo To Your Gallery</h2>';
	$printPage .= '[get-upload-btn type="gallery" text="Upload Photo:"]';	
	$printPage .= '[get-gallery order="desc" tags="filler, gallery-'.$profileFirst.'-'.$profileLast.'"]';	
	$printPage .= '[/col]';
	 
	$printPage .= '[col class="info"]<h2>Update Your Info</h2>';
	$printPage .= '<form id="userinfo-update" method="post" enctype="multipart/form-data">';
	$printPage .= '<input type="hidden" name="user_info_upload" value="1" />';
	$printPage .= '<label for="first_name">First Name: <input type="text" name="first_name" id="first_name" value="'.wp_get_current_user()->user_firstname.'" class="regular-text"></label><br/>';
	$printPage .= '<label for="last_name">Last Name: <input type="text" name="last_name" id="last_name" value="'.wp_get_current_user()->user_lastname.'" class="regular-text"></label><br/>';
	$printPage .= '<label for="email">Email: <input type="text" name="email" id="email" value="'.$profileEmail.'" class="regular-text"></label><br/>';	
	$printPage .= '<label for="bio">Bio: <textarea type="text" name="bio" id="bio" value="'.$profileDesc.'" class="regular-text"></label><br/>';
	$printPage .= '<label for="facebook">Facebook: <input type="text" name="facebook" id="facebook" value="'.$profileFacebook.'" class="regular-text"></label><br/>';
	$printPage .= '<label for="twitter">Twitter: <input type="text" name="twitter" id="twitter" value="'.$profileTwitter.'" class="regular-text"></label><br/>';
	$printPage .= '<label for="instagram">Instagram: <input type="text" name="instagram" id="instagram" value="'.$profileInstagram.'" class="regular-text"></label><br/>';
	$printPage .= '<label for="linkedin">LinkedIn: <input type="text" name="linkedin" id="linkedin" value="'.$profileLinkedIn.'" class="regular-text"></label><br/>';
	$printPage .= '<label for="pinterest">Pinterest: <input type="text" name="pinterest" id="pinterest" value="'.$profilePinterest.'" class="regular-text"></label><br/>';
	$printPage .= '<label for="youtube">YouTube: <input type="text" name="youtube" id="youtube" value="'.$profileYouTube.'" class="regular-text"></label><br/>';
	$printPage .= '<input class="info-submit-btn" type="submit" name="submit" value="Update Info" />';
	$printPage .= '</form>[/col]';
else : 
	$printPage .= '[col class="avatar"]'.$profileAvatar.'[/col]';
	$printPage .= '[col class="bio"][txt]';
	$printPage .= '<h1 class="user-name">'.$profileFirst.' '.$profileLast.'<br/>';	
	$printPage .= '<span class="user-roles">[get-user user="'.$profileID.'" info="role"]</span></h1>';
	$printPage .= '<div class="profile-description">'.$profileDesc.'</div>';
	$printPage .= '<div class="profile-social">'.$profileEmailBtn.$profileFacebookBtn.$profileTwitterBtn.$profileInstagramBtn.$profileLinkedInBtn.$profilePinterestBtn.$profileYouTubeBtn.'</div>';
	$printPage .= '[/txt][/col]';
endif;
$printPage .= '[/section]';
	
$restrictedMsg = '<h1>Log In</h1><h3>To Access Profile Pages</h3>'.do_shortcode('[get-login]');
$restrictCode = do_shortcode('[restrict max="none"]'.$restrictedMsg.'[/restrict]');	
$pageCode = do_shortcode('[restrict max="administrator" min="member"]'.$printPage.'[/restrict]');	

return $restrictCode.$pageCode;
?>