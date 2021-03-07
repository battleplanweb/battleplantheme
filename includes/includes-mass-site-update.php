<?php
/* Battle Plan Web Design - Mass Site Update */
 

/* LOGIC ---

in the includes-hvac.php file --
readMeta in Site Header for most recent product update -- if it's before the newest update, include this file
updateMeta with today's date, to keep file from being included again

--OR--


function my_run_only_once() {
 
    if ( get_option( 'my_run_only_once_01' ) != 'completed' ) {
  
        // PLACE YOUR CODE BELOW THIS LINE
          
  
         
  
  
  
        update_option( 'my_run_only_once_01', 'completed' );
    }
}
add_action( 'init', 'my_run_only_once' );



*/



function add_products() {
	$check_page_exist = get_page_by_path('new-page', OBJECT, 'products');
	
	if ( empty($check_page_exist) ) : 
		$user = get_user_by('login', 'battleplanweb');
		$userID = $user->ID;
		$postID = wp_insert_post(array(
				'comment_status' => 'close',
				'ping_status'    => 'close',
				'post_author'	 => $userID,
				'post_title'     => ucwords('New Page'),
				'post_name'      => strtolower(str_replace(' ', '-', trim('New Page'))),
				'post_status'    => 'publish',
				'post_content'   => 'Content of the page',
				'post_excerpt'   => 'Excerpt of the page',
				'post_type'      => 'products',
				'menu_order'     => 100,
				'tax_input'		 => array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'platinum-series'),
				'meta_input'	 =>	array('comfort'=>5, 'efficiency'=>4, 'price'=>3),
			));
	else:		
		$user = get_user_by('login', 'battleplanweb');
		$userID = $user->ID;
		$postID = $check_page_exist->ID;
		wp_update_post(array(
				'ID' 			 => $postID,
				'post_title'     => ucwords('Newest Page'),
				'post_content'   => 'Content of the page 2',
				'post_excerpt'   => 'Excerpt of the page 2',
				'menu_order'     => 100,
				'tax_input'		 => array('product-brand'=>'samsung', 'product-type'=>'air-conditioners', 'product-class'=>'platinum-series'),
				'meta_input'	 =>	array('comfort'=>5, 'efficiency'=>4, 'price'=>3),
			));	
	
	endif;

	$IMGFileName = 'American-Standard-01.jpg';
	$IMGFilePath = ABSPATH . '/wp-content/themes/battleplantheme/common/hvac-american-standard/products/'.$IMGFileName;
	$IMGFileTitle = str_replace('-', ' ', $IMGFileName);

	$checkID = getID($IMGFileTitle);		

	if( $checkID == false ) :
		$upload = wp_upload_bits($IMGFileName , null, file_get_contents($IMGFilePath, FILE_USE_INCLUDE_PATH));
		$imageFile = $upload['file'];
		$wpFileType = wp_check_filetype($imageFile, null);		
		$attachment = array(
			 'post_mime_type' => $wpFileType['type'],
			 'post_title' => sanitize_file_name($IMGFileName),
			 'post_content' => '',
			 'post_status' => 'inherit'
		);
		$attachmentId = wp_insert_attachment( $attachment, $imageFile, $postID );		
	else:
		$attachmentId = $checkID;
	endif;		

	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	$attach_data = wp_generate_attachment_metadata( $attachmentId, $imageFile );
	wp_update_attachment_metadata( $attachmentId, $attach_data );	 

	set_post_thumbnail( $postID, $attachmentId );
}
add_action( 'wp_loaded', 'add_products', 10 );




?>