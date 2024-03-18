<?php
/* Battle Plan Web Design - Mass Site Update */

	require_once(ABSPATH . 'wp-admin/includes/media.php');
	require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once(ABSPATH . 'wp-admin/includes/image.php');

 	$user = get_user_by('login', 'battleplanweb');
	$userID = $user->ID;	


// Remove Images
	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		)
	);

	foreach ($attachments as $attachment) :
		$metadata = wp_get_attachment_metadata($attachment->ID);

		if (!empty($metadata) && isset($metadata['file'])) :
			$filename = basename($metadata['file']);

			if (in_array($filename, $removeImages)) :
				wp_delete_attachment($attachment->ID, true); 
			endif;
		endif;
	endforeach;

		
// Remove Products
	foreach ( $removeProducts as $product ) :
		$productPage = get_page_by_path( $product, OBJECT, 'products' );
		$productID = $productPage->ID;
		if ( !empty( $productPage ) ) :
			wp_delete_post( $productID, true );
			if( has_post_thumbnail( $productID ) ) :
				$attachment_id = get_post_thumbnail_id( $productID );
				wp_delete_attachment($attachment_id, true);
			endif;
		endif;
	endforeach;
	
	
// Add Products
	foreach ( $addProducts as $product ) :
		$productTitle = $product['post_title'];
		$productContent = $product['post_content'];
		$productExcerpt = $product['post_excerpt'];
		$productType = $product['post_type'];
		$productOrder = $product['menu_order'];
		$productTax = $product['tax_input'];
		$productMeta = $product['meta_input'];
		$productImg = $product['image_name'];
		$productName = strtolower(str_replace(' ', '-', trim($productTitle)));		
		$productPage = get_page_by_path( $productName, OBJECT, 'products' );			

		$IMGFilePath = ABSPATH . 'wp-content/themes/battleplantheme/common/hvac-'.$brand.'/products/'.$productImg;
		$IMGFileTitle = str_replace('-', ' ', $productImg);
		$checkID = getID($IMGFileTitle);		
		
		if( $checkID == false ) :
			$upload = wp_upload_bits($productImg , null, file_get_contents($IMGFilePath, FILE_USE_INCLUDE_PATH));
			$imageFile = $upload['file'];
			$wpFileType = wp_check_filetype($imageFile, null);		
			$attachment = array(
				 'post_mime_type' => $wpFileType['type'],
				 'post_title' => sanitize_file_name($productImg),
				 'post_content' => '',
				 'post_status' => 'inherit'
			);
			$attachmentID = wp_insert_attachment( $attachment, $imageFile, $productPage->ID );		
					
			$attach_data = wp_generate_attachment_metadata( $attachmentID, $imageFile );
			wp_update_attachment_metadata( $attachmentID, $attach_data );
			update_post_meta( $attachmentID, '_wp_attachment_image_alt', $productImgAlt );
			wp_set_object_terms( $attachmentID, array('Products'), 'image-categories', true );
		else:
			$attachmentID = $checkID;
		endif;	

		if ( empty( $productPage ) ) : 
			$productPage = wp_insert_post( array(
				'comment_status' => 'close',
				'ping_status'    => 'close',
				'post_author'	 => $userID,
				'post_title'     => ucwords($productTitle),
				'post_name'      => $productName,
				'post_content'   => $productContent,
				'post_excerpt'   => $productExcerpt,
				'post_type'      => $productType,
				'menu_order'     => $productOrder,
				'meta_input'	 =>	$productMeta,
				'post_status'    => 'publish',
			));
		else:		
			wp_update_post(array(
				'ID' 			 => $productPage->ID,
				'post_title'     => ucwords($productTitle),
				'post_content'   => $productContent,
				'post_excerpt'   => $productExcerpt,
				'menu_order'     => $productOrder,
				'meta_input'	 =>	$productMeta,
			));	
		endif;
		
		foreach ( $productTax as $tax=>$term ) :
			wp_set_object_terms( $productPage, $term, $tax );
		endforeach;
		
		set_post_thumbnail( $productPage, $attachmentID );
	endforeach;	
?>