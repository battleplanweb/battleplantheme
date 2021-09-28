<?php
/* Battle Plan Web Design Pedigree Includes

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Log In & Registration Forms
# User Profile Pics
# User Info
# Status Updates


/*--------------------------------------------------------------
# Log In & Registration Forms
--------------------------------------------------------------*/

add_shortcode( 'get-login', 'battleplan_getLogInForm' );
function battleplan_getLogInForm( $atts, $content = null ) {
	if ( get_option('site_login')['width'] ) : $width = get_option('site_login')['width']; else: $width = '500'; endif;
	if ( get_option('site_login')['height'] ) : $height = get_option('site_login')['height']; else: $height = '900'; endif;
	
    return '<iframe id="siteLoginForm" title="Site Login" width="'.$width.'" height="'.$height.'" allowfullscreen="false" src="/wp-login.php"></iframe>';
} 
 
add_action( 'register_form', 'battleplan_registration_form_fields' );
function battleplan_registration_form_fields() {
	if ( get_option('site_login')['first_name'] == "true" ) $buildFields = '<p><label for="first_name">'.esc_html__( 'First Name', 'first_name' ).'</label><input type="text" class="regular_text" name="first_name" /></p>';
	if ( get_option('site_login')['last_name'] == "true" ) $buildFields .= '<p><label for="last_name">'.esc_html__( 'Last Name', 'last_name' ).'</label><input type="text" class="regular_text" name="last_name" /></p>';
	if ( get_option('site_login')['invite_code'] ) $buildFields .= '<p><label for="invite_code">'.esc_html__( 'Invite Code', 'invite_code' ).'</label><input type="text" class="regular_text" name="invite_code" /></p>';	
	echo $buildFields;
} 

add_filter( 'registration_errors', 'battleplan_registration_errors', 10, 3 );
function battleplan_registration_errors( $errors, $sanitized_user_login, $user_email ) {
	if ( empty( $_POST['first_name'] ) || empty( $_POST['last_name']) ) :
		$errors->add( 'first_or_last', __( '<strong>ERROR</strong>: Please type your first and last name.', 'battleplan' ) );
	endif;
	
	if ( get_option('site_login')['invite_code'] ) : 
		if ( empty( $_POST['invite_code'] ) || !in_array($_POST['invite_code'], get_option('site_login')['invite_code']) ) :
			$errors->add( 'invite', __( '<strong>ERROR</strong>: Please type a valid invite code.', 'battleplan' ) );
		endif;
	endif;

	return $errors;
}

add_action( 'user_register', 'battleplan_save_data' );
function battleplan_save_data( $user_id ) {
	if ( ! empty( $_POST['first_name'] ) ) update_user_meta( $user_id, 'first_name', trim( $_POST['first_name'] ) ) ;		
	if ( ! empty( $_POST['last_name'] ) ) update_user_meta( $user_id, 'last_name', trim( $_POST['last_name'] ) );
}

add_filter("login_redirect", "battleplan_login_redirect", 10, 3);
function battleplan_login_redirect( $redirect_to, $request, $user ) {
	global $user;   
	if ( isset( $user->roles ) && is_array( $user->roles ) ) :
		if ( in_array( "administrator", $user->roles ) || in_array( "bp_manager", $user->roles ) ) : 
		 	return "/wp-admin/"; 
		else:
			if ( get_option('site_login')['redirect'] ) : return get_option('site_login')['redirect'];
			else: return $redirect_to; endif;
		endif;
	else :
		if ( get_option('site_login')['redirect'] ) : return get_option('site_login')['redirect'];
		else: return $redirect_to; endif;			
	endif;
}

add_action( 'login_head', 'battleplan_addBaseToLoginPage' );
function battleplan_addBaseToLoginPage() {
	$addScript = '<base target="_parent">'; 
	$findThis = "a[href*='wp-login.php']";
	$addScript .= '<script>setTimeout(function() {var getAll = document.querySelectorAll("'.$findThis.'"); for (var i=0; i<getAll.length; i++) {getAll[i].setAttribute("target", "_self");}}, 1000);</script>';
	echo $addScript;
} 

/*--------------------------------------------------------------
# User Profile Pics
--------------------------------------------------------------*/
add_shortcode( 'get-upload-btn', 'battleplan_getUploadBtn' );
function battleplan_getUploadBtn($atts, $content = null) {	
	$a = shortcode_atts( array( 'type'=>'avatar', 'text'=>'Upload Avatar:', 'empty'=>'No File Selected', 'browse'=>'Browse', 'submit'=>'Submit' ), $atts );
	$type = esc_attr($a['type']);
	$text = esc_attr($a['text']);
	$empty = esc_attr($a['empty']);	
	$browse = esc_attr($a['browse']);
	$submit = esc_attr($a['submit']);
	$formID = rand(1000,9999);
	
	$printBtn = '<form id="image-upload_'.$formID.'" action="'.get_template_directory_uri().'/includes/includes-image-upload.php" method="post" enctype="multipart/form-data">';
	$printBtn .= '<span class="upload-text"><b>'.$text.'&nbsp;&nbsp;</b></span><input readonly type="text" id="upload-file-name_'.$formID.'" value="'.$empty.'" />';
	$printBtn .= '<label class="file-upload-btn"><input type="file" name="image_upload" onchange="getFile'.$formID.'(this)"/>'.$browse.'</label>';	
	$printBtn .= '<script>function getFile'.$formID.'(object) { var file = object.files[0]; var name = file.name; document.getElementById("upload-file-name_'.$formID.'").value = name; }</script>';		
	$printBtn .= '<input class="file-submit-btn" type="submit" name="submit" value="'.$submit.'" />';
	$printBtn .= '<input type="hidden" name="upload_type" value="'.$type.'" />';
	$printBtn .= '</form>';
	
	return $printBtn;	
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
	 	return '<img alt="'.$getUser->user_firstname.' '.$getUser->user_lastname.'\'s profile picture" src="'.$getUserPicSrc.'" class="avatar user-image profile-pic wp-user-'.$getUserID.' wp-image-'.$getUserPicID.'" width="'.$getUserPicW.'" height="'.$getUserPicH.'" style="aspect-ratio:'.$getUserPicW.'/'.$getUserPicH.'"/>';
	else:
		 return '<img alt="'.$getUser->user_firstname.' '.$getUser->user_lastname.' has no profile picture" src="/wp-content/themes/battleplantheme/common/logos/generic-user-img.png" class="avatar user-image profile-pic wp-user-'.$getUserID.' wp-image-generic" width="320" height="320" style="aspect-ratio:320/320"/>';
	endif;
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
		if ( $info == "first name" || $info == "first" ) : return $user->user_firstname; endif;
		if ( $info == "last name" || $info == "last" ) : return $user->user_lastname; endif;		
		if ( $info == "login" || $info == "log in" ) : return $user->user_login; endif;
		if ( $info == "display name" || $info == "display" ) : return $user->display_name; endif;
		if ( $info == "id" ) : return $user->ID; endif;
		if ( $info == "pic" || $info == "picture" || $info == "image" || $info == "avatar" ) : return displayUserPic( $user->ID, $size ); endif;
	else:
		return "";
	endif;
}

/* Handle user info update */
if ( is_user_logged_in() && isset($_POST['user_info_upload'])) :
	$currUserID = wp_get_current_user()->ID;
	if ( isset( $_POST['first_name'] ) ) update_user_meta($currUserID, 'user_firstname', $_POST['first_name']);
	if ( isset( $_POST['last_name'] ) ) update_user_meta($currUserID, 'user_lastname', $_POST['last_name']);
	if ( isset( $_POST['email'] ) ) update_user_meta($currUserID, 'user_email', $_POST['email']);
	if ( isset( $_POST['bio'] ) ) update_user_meta($currUserID, 'description', $_POST['bio']);
	if ( isset( $_POST['facebook'] ) ) update_user_meta($currUserID, 'facebook', $_POST['facebook']);
	if ( isset( $_POST['twitter'] ) ) update_user_meta($currUserID, 'twitter', $_POST['twitter']);
	if ( isset( $_POST['instagram'] ) ) update_user_meta($currUserID, 'instagram', $_POST['instagram']);
	if ( isset( $_POST['linkedin'] ) ) update_user_meta($currUserID, 'linkedin', $_POST['linkedin']);
	if ( isset( $_POST['pinterest'] ) ) update_user_meta($currUserID, 'pinterest', $_POST['pinterest']);
	if ( isset( $_POST['youtube'] ) ) update_user_meta($currUserID, 'youtube', $_POST['youtube']);

	wp_redirect( "/profile/" );
endif;


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
	$currUserID = wp_get_current_user()->ID;
	$currUserLogin = wp_get_current_user()->user_login;
	$currUserEmail = wp_get_current_user()->user_email;
	$currUserFirst = wp_get_current_user()->user_firstname;
	$currUserLast = wp_get_current_user()->user_lastname;

	$post_title = $_POST['title'];
	$post_content = $_POST['content'];
	//$category = $_POST['category'];
	//$sample_image = $_FILES['sample_image']['name'];

	$new_post = array(
		'post_title' => $post_title,
		'post_content' => $post_content,
		'post_status' => 'publish',
		'post_type' => 'updates',
		//'post_category' => $category
	);

	$pid = wp_insert_post($new_post);
	add_post_meta($pid, 'meta_key', true);
	
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

?>