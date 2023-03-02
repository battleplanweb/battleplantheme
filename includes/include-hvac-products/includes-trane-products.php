<?php
/* Battle Plan Web Design - Mass Site Update */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-trane-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_trane_products', 10 );
function add_trane_products() {

	$brand = "trane";
	$productImgAlt = "Trane Heating & Cooling Product"; 

	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


	$addProducts = array (
	
	// Air Conditioners
		array ( 
			'post_title'	=>	'XL18i Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Choose between two cooling speeds with a two-stage system. For everyday cooling, the “low” setting can help beat the summer heat. On hot days where you may want an extra blast of cold air, switch you unit’s fan to “high.” Every Trane Air Conditioner is packed with high quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XL18i Air Conditioner includes:</span>
<ul>
	<li>Durable Climatuff™ compressor</li>
	<li>Full-side louvered panels</li>
	<li>WeatherGuard™ II top</li>
	<li>Baked-on powder paint</li>
	<li>Corrosion-resistant Weatherguard™ fasteners</li>
	<li>All-aluminum Spine Fin™ outdoor coil</li>
	<li>DuraTuff™ non-corrosive base pan</li>
	<li>Unique mounting of shaft down fan motor</li>
	<li>Variable-speed fan motor</li>
	<li>Quick-Sess cabinet with full coil protection</li>
	<li>Low sound with advanced fan system and sound insulators on compressors (on select models)</li>
</ul>', 
			'post_excerpt'	=>	'The 18.0 SEER rating makes this air conditioner energy-efficient while providing home comfort.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1000,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1208-35_TR_AC-Brochure_SV.pdf'),
			'image_name'	=>	'Trane-AC-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'XR17 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Choose between two cooling speeds with a two-stage system. For everyday cooling, the “low” setting can help beat the summer heat. On hot days where you may want an extra blast of cold air, switch you unit’s fan to “high.” Every Trane Air Conditioner is packed with high quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XR17 Air Conditioner includes:</span>
<ul>
	<li>Two-Stage Climatuff™ compressor</li>
	<li>Spine Fin™ outdoor coil</li>
	<li>Upgraded fan motor</li>
	<li>Full-Side louvered panels protect your investment</li>
	<li>Corrosion-resistant Weatherguard™ fasteners</li>
	<li>Unique DuraTuff™ non-corrosive base pan</li>
	<li>Quick-Sess cabinet with full coil protection</li>
	<li>Sound insulator on the compressor (select models)</li>
</ul>', 
			'post_excerpt'	=>	'The 18.0 SEER rating makes this air conditioner energy-efficient while providing home comfort.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1010,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1208-35_TR_AC-Brochure_SV.pdf'),
			'image_name'	=>	'Trane-AC-02.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'XL16i Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Single-stage systems provides powerful cooling to your entire home. The fan in your air conditioner turns on when your home’s temperature rises a degree or two, and automatically cools your living space back down to your comfort level. Every Trane Air Conditioner is packed with high quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XL16i Air Conditioner includes:</span>
<ul>
	<li>Durable Climatuff™ compressor</li>
	<li>Full-side louvered panels</li>
	<li>WeatherGuard™ II top</li>
	<li>Baked-on powder paint</li>
	<li>Corrosion-resistant Weatherguard™ fasteners</li>
	<li>All-aluminum Spine Fin™ outdoor coil</li>
	<li>DuraTuff™ non-corrosive base pan</li>
	<li>Unique mounting of shaft down fan motor</li>
	<li>Variable-speed fan motor</li>
	<li>Quick-Sess cabinet with full coil protection</li>
	<li>Low sound with advanced fan system and sound insulators on compressors (on select models)</li>
</ul>', 
			'post_excerpt'	=>	'The 17.0 SEER rating makes this air conditioner energy-efficient while providing home comfort.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1020,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1208-35_TR_AC-Brochure_SV.pdf'),
			'image_name'	=>	'Trane-AC-01.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'XR14 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Single-stage systems provides powerful cooling to your entire home. The fan in your air conditioner turns on when your home’s temperature rises a degree or two, and automatically cools your living space back down to your comfort level. Every Trane Air Conditioner is packed with high quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XR13 Air Conditioner includes:</span>
<ul>
	<li>Climatuff™ compressor</li>
	<li>Spine Fin™ outdoor coil</li>
	<li>Upgraded fan motor</li>
	<li>Full-Side louvered panels protect your investment</li>
	<li>Corrosion-resistant Weatherguard™ fasteners</li>
	<li>Unique DuraTuff™ non-corrosive base pan</li>
	<li>Quick-Sess cabinet with full coil protection</li>
</ul>', 
			'post_excerpt'	=>	'The 16.0 SEER rating balances energy efficiency and cooling strength to help lower your home cooling costs.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1030,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1208-35_TR_AC-Brochure_SV.pdf'),
			'image_name'	=>	'Trane-AC-02.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'XR13 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Single-stage systems provides powerful cooling to your entire home. The fan in your air conditioner turns on when your home’s temperature rises a degree or two, and automatically cools your living space back down to your comfort level. Every Trane Air Conditioner is packed with high quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XR13 Air Conditioner includes:</span>
<ul>
	<li>Climatuff™ compressor</li>
	<li>Spine Fin™ outdoor coil</li>
	<li>Full-Side louvered panels protect your investment</li>
	<li>Baked-on powder paint</li>
	<li>Unique DuraTuff™ non-corrosive base pan</li>
	<li>Quick-Sess cabinet with full coil protection</li>
	<li>Sound insulator (select models)</li>
</ul>', 
			'post_excerpt'	=>	'The 14.5 SEER rating balances energy efficiency and cooling strength to help lower your home cooling costs.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1040,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1208-35_TR_AC-Brochure_SV.pdf'),
			'image_name'	=>	'Trane-AC-02.jpg'		
		),
		
	
	// Heat Pumps
		array ( 
			'post_title'	=>	'XL18i Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Enjoy comfort you can count with a two-speed system. Every Trane home heat pump is packed with high-quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XL18i two-stage heat pump includes:</span>
<ul>
	<li>Two-Stage Climatuff™ compressor</li>
	<li>Full-side louvered panels</li>
	<li>WeatherGuard™ top protects components</li>
	<li>Baked-on powder paint</li>
	<li>Corrosion-resistant Weatherguard™ fasteners</li>
	<li>Spine Fin™ outdoor coil</li>
	<li>Unique DuraTuff™ non-corrosive basepan</li>
	<li>Unique mounting of shaft down fan motor</li>
	<li>Variable-speed fan motor</li>
	<li>Low-resistance airflow</li>
	<li>Sound insulator on the compressor (select models)</li>
</ul>', 
			'post_excerpt'	=>	'The XR18i’s 18 SEER and 9.5 HSPF ratings make this unit a great choice for saving energy and lowering your monthly energy use.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1100,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1209-31_Heat-Pump-Brochure_Trane_SV.pdf'),
			'image_name'	=>	'Trane-HP-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'XR17 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Enjoy comfort you can count with a two-speed system. Every Trane home heat pump is packed with high-quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XR17 includes:</span>
<ul>
	<li>Two-Stage Climatuff™ compressor</li>
	<li>Galvanized-steel louvered panels</li>
	<li>Baked-on powder paint</li>
	<li>Corrosion-resistant Weatherguard™ fasteners</li>
	<li>Spine Fin™ outdoor coil</li>
	<li>DuraTuff™ rust-proof basepan</li>
	<li>Sound insulator on the compressor (select models)</li>
</ul>', 
			'post_excerpt'	=>	'The XR17’s 17.25 SEER rating makes this unit a great choice for saving energy and lowering your monthly energy use.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1110,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1209-31_Heat-Pump-Brochure_Trane_SV.pdf'),
			'image_name'	=>	'Trane-HP-02.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'XL16i Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Single-stage heat pumps provide powerful, consistent heating to your entire home. Every Trane home heat pump is packed with high-quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XL16i heat pump includes:</span>
<ul>
	<li>Climatuff™ compressor</li>
	<li>Full-side louvered panels</li>
	<li>WeatherGuard™ top protects components</li>
	<li>Baked-on powder paint</li>
	<li>Corrosion-resistant Weatherguard™ fasteners</li>
	<li>Spine Fin™ outdoor coil</li>
	<li>Unique DuraTuff™ non-corrosive basepan</li>
	<li>Unique mounting of shaft down fan motor</li>
</ul>', 
			'post_excerpt'	=>	'The XL16i’s 20 SEER rating makes it a great choice for saving energy and lowering your monthly electricity use.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1120,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1209-31_Heat-Pump-Brochure_Trane_SV.pdf'),
			'image_name'	=>	'Trane-HP-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'XR14 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Single-stage heat pumps provide powerful, consistent heating to your entire home. Every Trane home heat pump is packed with high-quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XL16i heat pump includes:</span>
<ul>
	<li>Climatuff™ compressor</li>
	<li>Galvanized-steel louvered panels</li>
	<li>Baked-on powder paint</li>
	<li>Spine Fin™ outdoor coil</li>
</ul>', 
			'post_excerpt'	=>	'This single-speed heat pump is a great option for keeping you comfortable all year round. The XR14 will help keep you cool in the summer and warm throughout the fall and winter months.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1130,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1209-31_Heat-Pump-Brochure_Trane_SV.pdf'),
			'image_name'	=>	'Trane-HP-02.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'XR15 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Single-stage heat pumps provide powerful, consistent heating to your entire home. Every Trane home heat pump is packed with high-quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The XL16i heat pump includes:</span>
<ul>
	<li>Climatuff™ compressor</li>
	<li>Galvanized-steel louvered panels</li>
	<li>Baked-on powder paint</li>
	<li>Corrosion-resistant Weatherguard™ fasteners</li>
	<li>Spine Fin™ outdoor coil</li>
	<li>DuraTuff™ rust-proof basepan</li>
	<li>Sound insulator on the compressor (select models)</li>
</ul>', 
			'post_excerpt'	=>	'The XR15 has a SEER rating of 16 and HSPF of 9.5, which makes it a great choice for an energy efficient home.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1140,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1209-31_Heat-Pump-Brochure_Trane_SV.pdf'),
			'image_name'	=>	'Trane-HP-02.jpg'		
		),
		
	
	// Furnaces
		array ( 
			'post_title'	=>	'S8X2 Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">These gas furnaces pair energy efficiency with budget-friendly prices to help you find the perfect furnace for your home. Every Trane furnace is packed with high-quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The S8X2 includes:</span>
<ul>
	<li>Nine tap blower motor</li>
	<li>Two-stage gas heat</li>
	<li>Microelectronic controller</li>
	<li>Heavy steel cabinet</li>
	<li>Durable silicon nitride hot surface igniter</li>
	<li>Multi-port, in-shot burners</li>
	<li>Tubular steel heat exchanger</li>
	<li>Pre-painted galvanized steel cabinet</li>
	<li>Insulated cabinet for quiet operation</li>
</ul>', 
			'post_excerpt'	=>	'The S8X2 carries an 80% AFUE rating — a clear sign that this furnace uses most of its fuel for heating.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1200,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1215-26_TR_80-Gas-Furnaces-Trane_SV_072021.pdf'),
			'image_name'	=>	'Trane-F-01.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'S8X1 Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">These gas furnaces pair energy efficiency with budget-friendly prices to help you find the perfect furnace for your home. Every Trane furnace is packed with high-quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The S8X1 furnace includes:</span>
<ul>
	<li>Nine tap blower motor</li>
	<li>Self-diagnostic microelectronic controller</li>
	<li>Heavy steel cabinet</li>
	<li>Durable silicon nitride hot surface igniter</li>
	<li>Multi-port, in-shot burners</li>
	<li>Tubular steel heat exchanger</li>
	<li>Pre-painted galvanized steel cabinet</li>
	<li>Insulated cabinet for quiet operation</li>
</ul>', 
			'post_excerpt'	=>	'With an AFUE rating of 80%, the S8X1 can help reduce monthly energy bills while supplying you with the heat you need at the coldest times of the year.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1210,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'furnaces', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1215-26_TR_80-Gas-Furnaces-Trane_SV_072021.pdf'),
			'image_name'	=>	'Trane-F-02.jpg'		
		),	
		
		
		array ( 
			'post_title'	=>	'S8B1 Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">These gas furnaces pair energy efficiency with budget-friendly prices to help you find the perfect furnace for your home. Every Trane furnace is packed with high-quality components. Each helps ensure that time after time, your unit will provide total comfort your family can rely on. The S8B1 furnace includes:</span>
<ul>
	<li>Silicon nitride igniter</li>
	<li>Multi-port, in-shot burners</li>
	<li>Pre-painted galvanized steel cabinet</li>
	<li>Tubular steel heat exchanger</li>
	<li>Patented Vortica™ II blower design</li>
</ul>', 
			'post_excerpt'	=>	'The S8B1 is rated up to 80% AFUE, meaning it’s designed to use less energy to warm your home. For you, that means more comfort and lower energy use.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1220,
			'tax_input'		=>  array('product-brand'=>'Trane', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1215-26_TR_80-Gas-Furnaces-Trane_SV_072021.pdf'),
			'image_name'	=>	'Trane-F-02.jpg'		
		),
			
	);


	$user = get_user_by('login', 'battleplanweb');
	$userID = $user->ID;
	
		
// Remove Products
	foreach ( $removeProducts as $product ) :
		$productPage = get_page_by_path( $product, OBJECT, 'products' );
		if ( !empty( $productPage ) ) wp_delete_post( $productPage->ID, true );	
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
			
		$IMGFilePath = ABSPATH . '/wp-content/themes/battleplantheme/common/hvac-'.$brand.'/products/'.$productImg;
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
		else:
			$attachmentID = $checkID;
		endif;		
				
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $attachmentID, $imageFile );
		wp_update_attachment_metadata( $attachmentID, $attach_data );
		update_post_meta( $attachmentID, '_wp_attachment_image_alt', $productImgAlt );
		wp_set_object_terms( $attachmentID, array('Products'), 'image-categories', true );
		
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

}
?>