<?php
/* Battle Plan Web Design Pedigree Includes

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Log In & Registration Forms
# User Profile Pics
# User Info
# Status Updates
# Restriction
# Activity Sign Up Form


/*--------------------------------------------------------------
# Log In & Registration Forms
--------------------------------------------------------------*/

if ( !defined('_USER_LOGIN') ) define( '_USER_LOGIN', wp_get_current_user()->user_login );
if ( !defined('_USER_ID') ) define( '_USER_ID', wp_get_current_user()->ID );

add_shortcode( 'get-login', 'battleplan_getLogInForm' );
function battleplan_getLogInForm( $atts, $content = null ) {
	if ( isset(get_option('site_login')['width']) ) : $width = get_option('site_login')['width']; else: $width = '500'; endif;
	if ( isset(get_option('site_login')['height']) ) : $height = get_option('site_login')['height']; else: $height = '900'; endif;
	
    return '<iframe id="siteLoginForm" title="Site Login" width="'.$width.'" height="'.$height.'" allowfullscreen="false" src="/wp-login.php"></iframe>';
} 
 
add_action( 'register_form', 'battleplan_registration_form_fields' );
function battleplan_registration_form_fields() {
	if ( get_option('site_login')['first_name'] == "true" ) $buildFields = '<p><label for="first_name">'.esc_html__( 'First Name', 'first_name' ).'</label><input type="text" class="regular_text" name="first_name" /></p>';
	if ( get_option('site_login')['last_name'] == "true" ) $buildFields .= '<p><label for="last_name">'.esc_html__( 'Last Name', 'last_name' ).'</label><input type="text" class="regular_text" name="last_name" /></p>';
	if ( isset(get_option('site_login')['invite_code']) ) $buildFields .= '<p><label for="invite_code">'.esc_html__( 'Invite Code', 'invite_code' ).'</label><input type="text" class="regular_text" name="invite_code" /></p>';	
	echo $buildFields;
} 

add_filter( 'registration_errors', 'battleplan_registration_errors', 10, 3 );
function battleplan_registration_errors( $errors, $sanitized_user_login, $user_email ) {
	if ( get_option('site_login')['first_name'] == "true" && empty( $_POST['first_name'] )) $errors->add( 'first_name', __( '<strong>ERROR</strong>: Please type your first name.', 'battleplan' ) );
	
	if ( get_option('site_login')['last_name'] == "true" && empty( $_POST['last_name'] )) $errors->add( 'last_name', __( '<strong>ERROR</strong>: Please type your last name.', 'battleplan' ) );
	
	if ( isset(get_option('site_login')['invite_code']) ) : 
		if ( empty( $_POST['invite_code'] ) || !in_array($_POST['invite_code'], get_option('site_login')['invite_code']) ) :
			$errors->add( 'invite', __( '<strong>ERROR</strong>: Please type a valid invite code.', 'battleplan' ) );
		endif;		
	endif;

	return $errors;
}

// Set user's display_name upon registration
add_filter('pre_user_display_name','default_display_name');
function default_display_name($name) {
	$display = '';
	if ( ! empty( $_POST['first_name'] ) ) $display .= sanitize_text_field(trim( $_POST['first_name'] ));
	if ( ! empty( $_POST['first_name'] ) && ! empty( $_POST['last_name'] ) ) $display .= ' ';
	if ( ! empty( $_POST['last_name'] ) ) $display .= sanitize_text_field(trim( $_POST['last_name'] ));
	
	return $display;
}

add_action( 'user_register', 'battleplan_save_data', 99 );
function battleplan_save_data( $user_id ) {
	if ( ! empty( $_POST['first_name'] ) ) update_user_meta( $user_id, 'first_name', sanitize_text_field(trim( $_POST['first_name'] )) );
	if ( ! empty( $_POST['last_name'] ) ) update_user_meta( $user_id, 'last_name', sanitize_text_field(trim( $_POST['last_name'] )) );
	
	if ( ! empty( $_POST['invite_code'] ) ) :
		$userInvite = trim( $_POST['invite_code'] );
		update_user_meta( $user_id, 'invite_code_used', $userInvite );
		
		if ( get_option('site_login')['assign_new_codes'] != 'false' && get_option('site_login')['assign_new_codes'] != null ) :		
			$siteInviteCodes = get_option('site_login')['invite_code'];
			$userInviteCodes = get_user_meta( $user_id, 'user_invite_codes', true);	
			if ( !is_array($userInviteCodes) ) $userInviteCodes = array();	
			$newCodes = array();
			
			$siteUsers = get_users();
			foreach ( $siteUsers as $siteUser ) :
				$siteUserCodes = get_user_meta( $siteUser->ID, 'user_invite_codes', true);
				if ( in_array( $userInvite, $siteUserCodes ) ) :
					if (($code = array_search( $userInvite, $siteUserCodes )) !== false) unset($siteUserCodes[$code]);
					update_user_meta( $siteUser->ID, 'user_invite_codes', $siteUserCodes, false );	
					update_user_meta( $user_id, 'sponsor-member', $siteUser->ID, false );	
				endif;
			endforeach;
			
			if (($code = array_search( $userInvite, $siteInviteCodes )) !== false) unset($siteInviteCodes[$code]);

			for ( $x=0; $x < get_option('site_login')['assign_new_codes']; $x++ ) :
				array_push($newCodes, base64_encode(random_bytes(3)).$user_id.base64_encode(random_bytes(3)) );			
			endfor;
			
			$siteInviteCodesNew = array_merge($siteInviteCodes, $newCodes);
			$userInviteCodesNew = array_merge($userInviteCodes, $newCodes);
			update_user_meta( $user_id, 'user_invite_codes', $userInviteCodesNew, false );	
			update_option( 'site_login', array ( 'invite_code'=>$siteInviteCodesNew ));
			update_user_meta( $user_id, 'access-allowed', '', false );	
			update_user_meta( $user_id, 'log', '', false );	
		endif;		
	endif;	
}

add_filter("login_redirect", "battleplan_login_redirect", 10, 3);
function battleplan_login_redirect( $redirect_to, $request, $user ) {
	global $user;   
	if ( isset( $user->roles ) && is_array( $user->roles ) ) :
		if ( in_array( "administrator", $user->roles ) || in_array( "bp_manager", $user->roles ) ) : 
		 	return "/wp-admin/"; 
		else:
			if ( isset(get_option('site_login')['redirect']) ) : return get_option('site_login')['redirect'];
			else: return $redirect_to; endif;
		endif;
	else :
		if ( isset(get_option('site_login')['redirect']) ) : return get_option('site_login')['redirect'];
		else: return $redirect_to; endif;			
	endif;
}

add_action( 'login_head', 'battleplan_addBaseToLoginPage' );
function battleplan_addBaseToLoginPage() {
	if ( defined('_BP_NONCE') )	:
		$addScript = '<base target="_parent">'; 
		$findThis = "a[href*='wp-login.php']";
		$addScript .= '<script nonce="'._BP_NONCE.'">setTimeout(function() {var getAll = document.querySelectorAll("'.$findThis.'"); for (var i=0; i<getAll.length; i++) {getAll[i].setAttribute("target", "_self");}}, 1000);</script>';
		echo $addScript;
	endif;
} 

/*--------------------------------------------------------------
# User Profile Pics
--------------------------------------------------------------*/
add_shortcode( 'get-upload-btn', 'battleplan_getUploadBtn' );
function battleplan_getUploadBtn($atts, $content = null) {	
	$a = shortcode_atts( array( 'type'=>'avatar', 'text'=>'Upload Avatar:', 'submit'=>'Upload', 'multiple'=>'false' ), $atts );
	$type = esc_attr($a['type']);
	$text = esc_attr($a['text']);
	$submit = esc_attr($a['submit']);	
	$multiple = esc_attr($a['multiple']);
	if ( $multiple == 'false' ) : $multiple = ""; else: $multiple="multiple"; endif;
	
	if ( $_FILES ) :
		$files = $_FILES["files"];
		
		if ( $type == "gallery" ) :
			$origGalleryName = $_POST["gallery-name"]; 	
			if ( $origGalleryName == '' || $origGalleryName == null ) $origGalleryName = _USER_LOGIN;
			$galleryName = $origGalleryName;
			$aux = 1;
				
			function checkTitle($origGalleryName, $galleryName, $files, $aux) {
				if ( get_page_by_title( $galleryName, OBJECT, 'galleries' ) !== null ) :
					$aux++;
					$galleryName = $origGalleryName." #".$aux;
					checkTitle($origGalleryName, $galleryName, $files, $aux);				
				else :
					$new_post = array ( 'post_title' => $galleryName, 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'galleries', 'post_author' => _USER_ID, 'post_category' => '' );
					
					$pid = wp_insert_post($new_post);
					
					if (function_exists('battleplan_profileGalleryCreated')) battleplan_profileGalleryCreated($pid);
					
					$num=1;
					foreach ($files['name'] as $key => $value) :    
						if ($files['name'][$key]) :
							$picNum = str_pad($num, 3, "0", STR_PAD_LEFT);
							$picKey = base64_encode(random_bytes(30));
							$picExt = strrchr( $files['name'][$key], '.');
							$file = array( 
								'name' => strtolower(_USER_LOGIN.'-'.$galleryName.'-'.$picNum.'-'.$picKey.$picExt),
								'type' => $files['type'][$key], 
								'tmp_name' => $files['tmp_name'][$key], 
								'error' => $files['error'][$key],
								'size' => $files['size'][$key]
							);

							$_FILES = array ("files" => $file); 
							foreach ($_FILES as $file => $array) :
								$newupload = bp_handle_attachment($file, $pid); 
								if ( $num == 1 ) set_post_thumbnail( $pid, $newupload );
								wp_set_object_terms( $newupload, array('User', 'Photos'), 'image-categories', true );
							endforeach;
						endif;
						$num++;
					endforeach;
					$_FILES = null;
					wp_redirect( "/?p=".$pid );
				endif;
			}		
			checkTitle($origGalleryName, $galleryName, $files, $aux);
		else:
			foreach ($files['name'] as $key => $value) :    
				if ($files['name'][$key]) :
					$picKey = base64_encode(random_bytes(30));
					$picExt = strrchr( $files['name'][$key], '.');
					$file = array( 
						'name' => strtolower(_USER_LOGIN.'-avatar-'.$picKey.$picExt),
						'type' => $files['type'][$key], 
						'tmp_name' => $files['tmp_name'][$key], 
						'error' => $files['error'][$key],
						'size' => $files['size'][$key]
					);
					
					if ( $type == "avatar" ) :
						$checkAttachments = get_posts(array( 'post_type' => 'attachment', 'posts_per_page' => -1 ));
						foreach ( $checkAttachments as $image ) :
							if ( strpos ($image->guid, _USER_LOGIN.'-avatar') !== false ) wp_delete_attachment( $image->ID );
						endforeach;
					endif;
					
					$_FILES = array ("files" => $file); 
					foreach ($_FILES as $file => $array) :
						$newupload = bp_handle_attachment($file, 0); 
						wp_set_object_terms( $newupload, array('User', 'Avatar'), 'image-categories', true );
						if ( $type == "avatar" ) update_user_meta( _USER_ID, 'user-avatar', $newupload, false );
					endforeach;
				endif;
			endforeach;
			$_FILES = null;
			wp_redirect( "/profile/" );		
		
		endif;
	endif;

	$printBtn = '<form id="image-upload" class="image-upload" action="" method="post" enctype="multipart/form-data">';
	if ( $type == "gallery" ) : $printBtn .= '<label class="gallery-name">'.$text.' <input type="text" class="regular_text" name="gallery-name" /></label>';
	else: $printBtn .= '<label>'.$text.'</label>';
	endif;
	$printBtn .= '<input type="file" name="files[]" id='.$type.'-photo-browse" class="file-browse-btn" '.$multiple.' accept=".png, .jpg, .jpeg" />';		
	$printBtn .= '<input type="submit" name="submit-data" id='.$type.'-photo-submit" value="'.$submit.'" class="file-upload-btn" />';
	$printBtn .= '</form>';
	
	return $printBtn;	
}

function bp_handle_attachment($file_handler, $post_id, $set_thu=false) {
  if ($_FILES[$file_handler]['error'] !== UPLOAD_ERR_OK) __return_false();
  require_once(ABSPATH . "wp-admin" . '/includes/image.php');
  require_once(ABSPATH . "wp-admin" . '/includes/file.php');
  require_once(ABSPATH . "wp-admin" . '/includes/media.php');
   
  $attach_id = media_handle_upload( $file_handler, $post_id );
    
  return $attach_id;
}

// Display user profile pic
function displayUserPic( $identifier=null, $size='thumbnail' ) {
	$getUser = battleplan_identifyUser( $identifier );	
	$getUserID = $getUser->ID;	
	$getUserPicID = esc_attr( get_the_author_meta( 'user-avatar', $getUserID ) );
	
	$getUserPicMeta = wp_get_attachment_metadata( $getUserPicID );
	$getUserPicSrc = "/wp-content/uploads/".$getUserPicMeta['sizes'][$size]['file'];
	$getUserPicPath = wp_upload_dir()['path'].'/'.$getUserPicMeta['sizes'][$size]['file'];
	
	if ( $getUserPicMeta['sizes'][$size]['file'] ) :
		$getUserPicW = $getUserPicMeta['sizes'][$size]['width'];
		$getUserPicH = $getUserPicMeta['sizes'][$size]['height'];
	else:
		$getUserPicSrc = "/wp-content/themes/battleplantheme/common/logos/generic-user-img.png";
		$getUserPicID = "generic";
		$getUserPicW = "320";
		$getUserPicH = "320";
	endif;	
	
	return '<img alt="Profile avatar for '.$getUser->display_name.'" src="'.$getUserPicSrc.'" class="avatar user-image profile-pic wp-user-'.$getUserID.' wp-image-'.$getUserPicID.'" width="'.$getUserPicW.'" height="'.$getUserPicH.'" style="aspect-ratio:'.$getUserPicW.'/'.$getUserPicH.'"/>';	
}

/*--------------------------------------------------------------
# User Info
--------------------------------------------------------------*/

add_shortcode( 'get-user', 'battleplan_getUser' );
function battleplan_getUser( $atts, $content = null ) {
	$a = shortcode_atts( array( 'user'=>'', 'info'=>'role', 'size'=>'thumbnail' ), $atts );
	$user = battleplan_identifyUser( esc_attr($a['user']) );
	$info = esc_attr($a['info']);
	$size = esc_attr($a['size']);
	if ( $user ) :
		if ( $info == "role" ) : return battleplan_getUserRole( $user->ID, 'display' ); endif;
		if ( $info == "username" || $info == "user" ) : return $user->user_login; endif;
		if ( $info == "email" ) : return $user->user_email; endif;
		if ( $info == "first_name" || $info == "first name" || $info == "first" ) : return $user->user_firstname; endif;
		if ( $info == "last_name" || $info == "last name" || $info == "last" ) : return $user->user_lastname; endif;		
		if ( $info == "login" || $info == "log in" ) : return $user->user_login; endif;
		if ( $info == "name" || $info == "display name" || $info == "display_name" ) : return $user->display_name; endif;		
		if ( $info == "nickname" ) : return $user->nickname; endif;
		if ( $info == "id" ) : return $user->ID; endif;
		if ( $info == "pic" || $info == "picture" || $info == "image" || $info == "avatar" ) : return displayUserPic( $user->ID, $size ); endif;
	else:
		return "";
	endif; 
}

add_shortcode( 'display-user', 'battleplan_displayUser' );
function battleplan_displayUser( $atts, $content = null ) {
	$a = shortcode_atts( array( 'user'=>_USER_ID, 'info'=>'username', 'identity'=>get_option('site_login')['identity'], 'link'=>'true' ), $atts );	
	$user = esc_attr($a['user']);
	$info = esc_attr($a['info']);
	$identity = esc_attr($a['identity']);
	if ( $info == $identity ) $icon = get_option('site_login')['icon'];
	$link = esc_attr($a['link']);
	
	$displayName = "";
	
	if ( $link == "true" ) : $displayName .= '<a class="user-name" href="/profile?user='.$user.'">';	
	else: '<span class="user-name">'; endif;
	
	if ( $icon != "" && $icon != null ) $displayName .= '<i class="'.$icon.' fa"></i>';
	$displayName .= do_shortcode('[get-user user="'.$user.'" info="'.$info.'"]');
	
	if ( $link == "true" ) : $displayName .= '</a>';	
	else: '</span>'; endif;
	
	return $displayName;
}

// Handle user info update 
if ( is_user_logged_in() && isset($_POST['user_info_upload'])) :
	if ( isset( $_POST['first_name'] ) ) update_user_meta(_USER_ID, 'user_firstname', sanitize_text_field($_POST['first_name']));
	if ( isset( $_POST['last_name'] ) ) update_user_meta(_USER_ID, 'user_lastname', sanitize_text_field($_POST['last_name']));	
	if ( isset( $_POST['display'] ) ) update_user_meta(_USER_ID, 'display_name', sanitize_text_field($_POST['display']));
	if ( isset( $_POST['nickname'] ) ) update_user_meta(_USER_ID, 'nickname', sanitize_text_field($_POST['nickname']));
	if ( isset( $_POST['email'] ) ) update_user_meta(_USER_ID, 'user_email', sanitize_text_field($_POST['email']));
	if ( isset( $_POST['bio'] ) ) update_user_meta(_USER_ID, 'description', sanitize_text_field($_POST['bio']));
	if ( isset( $_POST['facebook'] ) ) update_user_meta(_USER_ID, 'facebook', sanitize_text_field($_POST['facebook']));
	if ( isset( $_POST['twitter'] ) ) update_user_meta(_USER_ID, 'twitter', sanitize_text_field($_POST['twitter']));
	if ( isset( $_POST['instagram'] ) ) update_user_meta(_USER_ID, 'instagram', sanitize_text_field($_POST['instagram']));
	if ( isset( $_POST['linkedin'] ) ) update_user_meta(_USER_ID, 'linkedin', sanitize_text_field($_POST['linkedin']));
	if ( isset( $_POST['pinterest'] ) ) update_user_meta(_USER_ID, 'pinterest', sanitize_text_field($_POST['pinterest']));
	if ( isset( $_POST['youtube'] ) ) update_user_meta(_USER_ID, 'youtube', sanitize_text_field($_POST['youtube']));

	wp_redirect( "/profile/" );
endif;

// Update user activity time 
add_action( 'init', 'battleplan_updateActivityTime', 0 );
function battleplan_updateActivityTime() {
	update_user_meta( _USER_ID, 'last-login-when', time(), false );
}

// Add role to classes on <body> 
add_filter( 'body_class', 'battleplan_addUserRoleToBodyClass', 99 );
function battleplan_addUserRoleToBodyClass( $classes ) {
	array_push($classes, 'role-'.battleplan_getUserRole( _USER_ID, '' ));
	return $classes;
}

/*--------------------------------------------------------------
# Status Updates
--------------------------------------------------------------*/
add_action( 'init', 'battleplan_registerStatusUpdates', 0 );
function battleplan_registerStatusUpdates() {
	register_post_type( 'updates', array (
		'label'=>				__( 'updates', 'battleplan' ),
		'labels'=>array(
			'name'=>			_x( 'Updates', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>	_x( 'Update', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>				true,
		'publicly_queryable'=>	true,
		'exclude_from_search'=>	false,
		'supports'=>			array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
		'hierarchical'=>		false,
		'menu_position'=>		20,
		'menu_icon'=>			'dashicons-megaphone',
		'has_archive'=>			true,
		'capability_type'=>		'post',		
		'show_ui'=>				true,
	));
}

/* Handle user post */
if ( is_user_logged_in() && isset($_POST['user_post_update'])) :
	$currUserID = _USER_ID;
	$currUserDisplay = do_shortcode('[display-user user="'.$currUserID.'"]');

	$post_title = $_POST['title'];
	$post_content = $_POST['content'];
	
	$lastPos = 0;
	$positions = array();
	$keys = array();
	$userLogs = array();
	$trigger = '@';
	$siteUsers = get_users();

	while (($lastPos = strpos($post_content, $trigger, $lastPos))!== false) :
		$positions[] = $lastPos;
		$lastPos = $lastPos + strlen($trigger);
	endwhile;

	foreach ($positions as $pos ) :
		$pos += strlen($trigger);
		$len = strpos($post_content, ' ', $pos) - $pos;
		$keys[] = substr($post_content, $pos, $len);
	endforeach;

	foreach ($keys as $key) :	
		foreach ( $siteUsers as $siteUser ) :
			if ( strtolower($siteUser->display_name) == strtolower($key) || strtolower($siteUser->nickname) == strtolower($key) ) :
				$keyLink = do_shortcode('[display-user user="'.$key.'"]');
				$key = '@'.$key;
				$post_content = str_replace($key, $keyLink, $post_content);	
				$userLogs[] = $siteUser->ID;				
				break;
			endif;
		endforeach;
	endforeach;	

	$new_post = array(
		'post_title' => $post_title,
		'post_content' => $post_content,
		'post_status' => 'publish',
		'post_type' => 'updates',
		'post_author' => $currUserID,
		//'post_category' => $category
	);

	$pid = wp_insert_post($new_post);
	add_post_meta($pid, 'meta_key', true);
	
	foreach ( $userLogs as $user ) :	
		$userLog = get_user_meta($user, 'log', true);				
		if ( !is_array($userLog) ) $userLog = array();				
		array_push($userLog, $currUserDisplay.' mentioned you in a <a  href="/updates#update-'.$pid.'">wall post</a>.');	
		update_user_meta( $user, 'log', $userLog, false );		
	endforeach;				
	
	if ( !function_exists('wp_generate_attachment_metadata') ) :
		require_once(ABSPATH.'wp-admin/includes/image.php');
		require_once(ABSPATH.'wp-admin/includes/file.php');
		require_once(ABSPATH.'wp-admin/includes/media.php');
	endif;
	
	if ($_FILES) : foreach ($_FILES as $file => $array) :
		if ($_FILES[$file]['error'] !== UPLOAD_ERR_OK) return "upload error : " . $_FILES[$file]['error'];
		$attach_id = media_handle_upload( $file, $pid );
	endforeach;	endif;
	
	if ($attach_id > 0) update_post_meta($pid, '_thumbnail_id', $attach_id);	
	$post = array_merge(get_post($attach_id), get_post($pid));
	
	wp_redirect( "/updates/" );
endif;

/*--------------------------------------------------------------
# Restriction
--------------------------------------------------------------*/

// Add "Restrict Page" checkbox to Page Attributes meta box
add_action( 'page_attributes_misc_attributes', 'battleplan_restrict_page_checkbox', 10, 1 );
function battleplan_restrict_page_checkbox($post) { 
	echo '<p class="post-attributes-label-wrapper">';
	$getRestrictPage = get_post_meta($post->ID, "_bp_restrict_page", true);

	if ( $getRestrictPage == "" ) : echo '<input name="restrict_page" type="checkbox" value="true">';
	else: echo '<input name="restrict_page" type="checkbox" value="true" checked>';
	endif;	
	
	echo '<label class="post-attributes-label" for="restrict_page">Restrict Page</label>';
} 
	 
add_action("save_post", "battleplan_save_restrict_page", 10, 3);
function battleplan_save_restrict_page($post_id, $post, $update) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
	if ( defined('DOING_AJAX') && DOING_AJAX ) return $post_id;
    if ( !current_user_can("edit_post", $post_id) ) return $post_id;

    $updateRestrictPage = "";
    if ( isset($_POST["restrict_page"]) ) $updateRestrictPage = $_POST["restrict_page"];   
    update_post_meta($post_id, "_bp_restrict_page", $updateRestrictPage);
}

// If post has "restrict page" checked, add log in form
add_action('bp_before_site_main_inner', 'battle_plan_restrict_the_content');
function battle_plan_restrict_the_content () {
	if ( readMeta( get_the_ID(), '_bp_restrict_page', true ) ) :
		$addToContent = '[restrict max="none"]';
		$addToContent .= '<h1>Log In</h1>';
		$addToContent .= '<h3>To Access '.get_the_title().'</h3>';
		$addToContent .= '[get-login]';
		$addToContent .= '[/restrict]';
		echo do_shortcode($addToContent);
	endif;
}

/*--------------------------------------------------------------
# Activity Sign Up Form
--------------------------------------------------------------*/

add_shortcode( 'get-signup-form', 'battleplan_getSignUpForm' );
function battleplan_getSignUpForm($atts, $content = null ) {
	$a = shortcode_atts( array( 'num' => '5', 'type' => 'ol', 'meta' => 'generic-signup-form', 'btn' => 'true', 'btn_text' => 'Sign Up', 'remove_text' => 'Remove' ), $atts );
	$num = esc_attr($a['num']);	
	$type = esc_attr($a['type']);
	$meta = esc_attr($a['meta']);
	$btn = esc_attr($a['btn']);
	$btnText = esc_attr($a['btn_text']);
	$removeText = esc_attr($a['remove_text']);
	$buildSignup = '';
	$postID = get_the_ID();
	
	$getCurrSignups = readMeta( $postID, '_bp_'.$meta, true );
	if ( !is_array($getCurrSignups) ) $getCurrSignups = array();				
		
	if ( isset( $_POST['add-'.$meta] ) ) :	
		if ( !in_array($_POST['add-'.$meta], $getCurrSignups) ) :
			array_push($getCurrSignups, $_POST['add-'.$meta]);
			updateMeta($postID, '_bp_'.$meta, $getCurrSignups);
		endif;
	endif;
	
	if ( isset( $_POST['remove-'.$meta] ) ) :	
		if ( in_array($_POST['remove-'.$meta], $getCurrSignups) ) :
			unset($getCurrSignups[array_search($_POST['remove-'.$meta],$getCurrSignups)]);
			updateMeta($postID, '_bp_'.$meta, $getCurrSignups);
		endif;
	endif;
	
	$buildSignup .= '<div class = "signup-form">';
	if ( $type == 'ol' || $type == 'ul' ) : $buildSignup .= '<'.$type.'>'; $line = 'li';
	else: $line = $type;
	endif;	
	
	for ($x = 0; $x < $num; $x++) :
		if ( $getCurrSignups[$x] ) :
			$buildSignup .= '<'.$line.'>[get-user user="'.$getCurrSignups[$x].'" info="name"]</'.$line.'>';	
			$btnHide = "true";
		else:
			$buildSignup .= '<'.$line.'>&nbsp;</'.$line.'>';
			$btnHide = "false";
		endif;
	endfor;
	
	if ( $btn == 'true' && $btnHide == 'false' ) :	
		$buildSignup .= '<form id="'.$meta.'-signup" class="profiles-signup" action="" method="post" enctype="multipart/form-data">';
		$buildSignup .= '<input type="hidden" name="add-'.$meta.'" value="'._USER_ID.'"/>';
		$buildSignup .= '<input type="submit" name="'.$meta.'-submit" id="'.$meta.'-signup-btn" value="'.$btnText.'" class="profiles-signup-btn" />';
		$buildSignup .= '</form>';
	endif;
	
	if ( in_array(_USER_ID, $getCurrSignups) ) :
		$buildSignup .= '<form id="'.$meta.'-remove" class="profiles-signup" action="" method="post" enctype="multipart/form-data">';
		$buildSignup .= '<input type="hidden" name="remove-'.$meta.'" value="'._USER_ID.'"/>';
		$buildSignup .= '<input type="submit" name="'.$meta.'-remove" id="'.$meta.'-remove-btn" value="'.$removeText.'" class="profiles-remove-btn" />';
		$buildSignup .= '</form>';
	endif;
	
	if ( $type == 'ol' || $type == 'ul' ) $buildSignup .= '</'.$type.'>';
	$buildSignup .= '</div>';
	
	return do_shortcode($buildSignup);
}

?>