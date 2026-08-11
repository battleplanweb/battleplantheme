<?php
/* Battle Plan Web Design - Mass Site Update */

	require_once(ABSPATH . 'wp-admin/includes/media.php');
	require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once(ABSPATH . 'wp-admin/includes/image.php');

 	$user = get_user_by('login', 'battleplanweb');
	$userID = $user->ID;	





// Remove Images

// in the includes-{brand}-products.php, you will include the list of filenames you wish to remove: $removeImages = array('American-Standard-46.jpg', 'American-Standard-45.jpg');
	if ( is_array($removeImages) ) :
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
	endif;

		
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
		// Build the slug with the SAME transform wp_insert_post() stores it with, and look it up with
		// a plain name query rather than get_page_by_path(). get_page_by_path() rawurlencodes before
		// sanitising, so titles carrying '™', '*' or an en dash resolve to a different string than the
		// one on disk — the lookup misses and the product gets inserted a second time as -2.
		$productName = sanitize_title( $productTitle );
		$existing = get_posts([
			'post_type'      => 'products',
			'post_status'    => 'any',
			'name'           => $productName,
			'posts_per_page' => 1,
		]);
		$productPage = $existing ? $existing[0] : null;

		$IMGFilePath = get_template_directory().'/common/hvac-'.$brand.'/products/'.$productImg;
		$attachmentID = bp_product_image_id($productImg);

		if( $attachmentID == false ) :
			if ( !is_readable($IMGFilePath) || @filesize($IMGFilePath) < 1 ) :
				bp_product_image_failed($brand, $productImg, 'source file missing or empty at '.$IMGFilePath);
				$attachmentID = 0;
			else:
				$upload = wp_upload_bits($productImg , null, file_get_contents($IMGFilePath));

				// wp_upload_bits() returns ['error' => ...] and no 'file' when the mime type is
				// blocked or the bits are empty. Unchecked, that produced a product with a broken
				// featured image and no image in the media library.
				if ( !empty($upload['error']) || empty($upload['file']) ) :
					bp_product_image_failed($brand, $productImg, ($upload['error'] ?? '') ?: 'wp_upload_bits returned no file');
					$attachmentID = 0;
				else:
					$imageFile = $upload['file'];
					$wpFileType = wp_check_filetype($imageFile, null);
					$attachment = array(
						 'post_mime_type' => $wpFileType['type'],
						 'post_title' => sanitize_file_name($productImg),
						 'post_content' => '',
						 'post_status' => 'inherit'
					);
					$attachmentID = wp_insert_attachment( $attachment, $imageFile, $productPage->ID ?? 0 );

					if ( is_wp_error($attachmentID) || empty($attachmentID) ) :
						bp_product_image_failed($brand, $productImg, 'wp_insert_attachment failed');
						$attachmentID = 0;
					else:
						$attach_data = wp_generate_attachment_metadata( $attachmentID, $imageFile );
						wp_update_attachment_metadata( $attachmentID, $attach_data );
						update_post_meta( $attachmentID, '_wp_attachment_image_alt', $productImgAlt );
						wp_set_object_terms( $attachmentID, array('Products'), 'image-categories', true );
					endif;
				endif;
			endif;
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
		
		if ( $attachmentID ) set_post_thumbnail( $productPage, $attachmentID );
	endforeach;

	bp_product_image_report($brand);
?>