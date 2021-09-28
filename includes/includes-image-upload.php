<?php require( dirname(__FILE__) . '/../../../../wp-load.php' );

$currUserID = wp_get_current_user()->ID;
$imageUpload = $_FILES['image_upload'];
$new_file_mime = mime_content_type( $imageUpload['tmp_name'] );
$new_file_path = wp_upload_dir()['path'].'/'.$currUserFilename;
$new_file_url = wp_upload_dir()['url'].'/'.$currUserFilename;
$uploadType = $_POST['upload_type'];
$currUserFilename = wp_get_current_user()->user_firstname.'-'.wp_get_current_user()->user_lastname.'_'.$currUserID.'_'.$uploadType;
if ( $uploadType == "avatar" ) : $imgCats = array('User', 'Avatar'); else: $imgCats = array('User'); $imgTags = array( 
$uploadType."-".strtolower(wp_get_current_user()->user_firstname).'-'.strtolower(wp_get_current_user()->user_lastname), $uploadType."-user" ); endif;

if ( empty( $imageUpload ) ) echo 'File is not selected.'; 
if ( $imageUpload['error'] ) echo $imageUpload['error'];	
if ( $imageUpload['size'] > wp_max_upload_size() ) echo 'File is too large.';	
if ( !in_array( $new_file_mime, get_allowed_mime_types() ) ) echo 'File type is not allowed.';

$i = 1;
while( file_exists( $new_file_path ) ) {
	$new_file_path = wp_upload_dir()['path'].'/'.$currUserFilename.'-'.$i;
	$i++;
} 

if( move_uploaded_file( $imageUpload['tmp_name'], $new_file_path ) ) {
	$upload_id = wp_insert_attachment( array(
		'guid'          	=> $new_file_path, 
		'post_mime_type' 	=> $new_file_mime,
		'post_title'     	=> $currUserFilename,
		'post_content'   	=> '',
		'post_status'    	=> 'inherit',
	), $new_file_path );

	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	wp_update_attachment_metadata( $upload_id, wp_generate_attachment_metadata( $upload_id, $new_file_path ) );
	wp_set_object_terms( $upload_id, $imgCats, 'image-categories', true );
	if ( $uploadType != "avatar" ) wp_set_object_terms( $upload_id, $imgTags, 'image-tags', true );
	//update_post_meta($upload_id, '_wp_attachment_image_alt', wp_get_current_user()->user_firstname.' '.wp_get_current_user()->user_lastname.' Profile Picture');			
	if ( $uploadType == "avatar" ) update_user_meta($currUserID, 'user-avatar', $upload_id);

	wp_redirect( "/profile/" );
} 

?>