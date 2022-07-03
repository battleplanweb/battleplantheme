<?php
/* Battle Plan Web Design - Mass Site Update */
 
add_action( 'wp_loaded', 'add_products', 10 );
function add_products() {

	$brand = "american-standard";
	$productImgAlt = "American Standard Heating & Cooling Product"; 

	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	
	/*
	$addProducts = array (
		array ( 
			'post_title'	=>	'Silver S9X1 Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">A system that works smarter. Quality and reliability with a cutting-edge design.</span>
<ul>
	<li>Up to 96% AFUE for efficient operation</li>
	<li>Matches with single- and two-stage AC and HP models</li>
	<li>High-efficiency constant torque ECM (Electronically Commutated Motor) blower motor</li>
	<li>Exclusive Vortica™ II blower design that increases airflow efficiency</li>
	<li>Stainless steel primary and secondary heat exchangers resist corrosion for long-lasting performance</li>
	<li>Self-diagnosing integrated furnace control (IFC)</li>
	<li>Certified 1% airtight, meeting stringent building codes and saving energy</li>
	<li>ENERGY STAR certification validates performance and efficiency</li>
	<li>Fully insulated cabinet reduces operating noise</li>
	<li>Match your gas furnace with a heat pump to enjoy the energy-saving benefits of a hybrid system. In milder temperatures, your heat pump acts as the primary source of heat for your home. When the weather gets too cold, your system activates your furnace to deliver the heat you need.</li>
</ul>', 
			'post_excerpt'	=>	'A system that works smarter. Quality and reliability with a cutting-edge design.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1250,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'silver-series'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/pdf/TT_10-1173-18_AS-90-95_Gas%20Furnace_AS_SV.pdf', 'comfort'=>'na', 'efficiency'=>'na', 'price'=>4),
			'image_name'	=>	'American-Standard-44.jpg'		
		),
		
		array ( 
			'post_title'	=>	'American Standard S9B1 Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">Designed with value in mind, this gas furnace provides you with the comfort you want, the efficiency and durability you need and, of course, the American Standard commitment to quality you expect.</span>
<ul>
	<li>Converts up to 92.1% of the fuel you pay for into heat for your home.</li>
	<li>Heavy steel cabinet holds in heat and reduces operating noise – keeping you comfortable without the extra sound.</li>
	<li>Stainless steel, tubular primary and secondary heat exchangers are more resistant to corrosion.</li>
	<li>Patented, durable Vortica™ II blower design is more efficient than standard blowers.</li>
	<li>Match your gas furnace with a heat pump to enjoy the energy-saving benefits of a hybrid system. In milder temperatures, your heat pump acts as the primary source of heat for your home. When the weather gets too cold, your system activates your furnace to deliver the heat you need.</li>
</ul>', 
			'post_excerpt'	=>	'Designed with value in mind, this gas furnace provides you with comfort, efficiency, durability and quality.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1220,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'platinum-series'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/pdf/TT_10-1173-18_AS-90-95_Gas%20Furnace_AS_SV.pdf', 'comfort'=>2, 'efficiency'=>4, 'price'=>3),
			'image_name'	=>	'American-Standard-45.jpg'		
		),
		
		array ( 
			'post_title'	=>	'American Standard S8B1 Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">Get efficient performance with this gas furnace.  A solid and reliable unit, this residential furnace is designed to provide affordable heating throughout your entire home.</span>
<ul>
	<li>Converts up to 80 percent of the fuel that you purchase to heat your home.</li>
	<li>Save on energy usage while also reducing greenhouse gas emissions* by significantly surpassing government efficiency standards.</li>
	<li>This furnace provides comfort with its tubular, steel heat exchanger and patented Vortica™ II blower design.</li>
	<li>With its heavy steel, AirTite™ cabinet this furnace is durable and holds in more heat to better warm your home.</li>
	<li>Match your gas furnace with a heat pump to enjoy the energy-saving benefits of a hybrid system. In milder temperatures, your heat pump acts as the primary source of heat for your home. When the weather gets too cold, your system activates your furnace to deliver the heat you need.</li>
</ul>', 
			'post_excerpt'	=>	'A solid and reliable unit, this residential furnace is designed to provide affordable heating throughout your entire home.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1270,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'silver-series'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/pdf/TT_10-1111-26_AS%2080%20Gas%20Furnaces_AS_SV.pdf', 'comfort'=>2, 'efficiency'=>2, 'price'=>1),
			'image_name'	=>	'American-Standard-46.jpg'		
		),
	);

*/

	$editProducts = array (
	// Air Conditioners
		array ( 'post_slug'	=> 'accucomfort-platinum-20-air-conditioner', 'menu_order' => 1000, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1190-11_AS_Variable_Speed_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'best')),
		
		array ( 'post_slug'	=> 'accucomfort-platinum-18-air-conditioner', 'menu_order' => 1010, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1190-11_AS_Variable_Speed_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'best')),
		
		array ( 'post_slug'	=> 'gold-17-air-conditioner', 'menu_order' => 1020, 'meta_input' => array('brochure' => 'https://www.americanstandardair.com/content/dam/americanstandarair/brochure/airconditioner/10-1112-30_HR.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'better')),
		
		array ( 'post_slug'	=> 'silver-16-air-conditioner', 'menu_order' => 1030, 'meta_input' => array('brochure' => 'https://www.americanstandardair.com/content/dam/americanstandarair/brochure/airconditioner/10-1112-30_HR.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'good')),
		
		array ( 'post_slug'	=> 'silver-14-air-conditioner', 'menu_order' => 1040, 'meta_input' => array('brochure' => 'https://www.americanstandardair.com/content/dam/americanstandarair/brochure/airconditioner/10-1112-30_HR.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'good')),
		
	// Air Handlers		
		array ( 'post_slug'	=> 'forefront-platinum-tam9-air-handler', 'menu_order' => 1300, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_15-4142-20_AS-Air-Handler-ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'best')),
		
		array ( 'post_slug'	=> 'forefront-gold-tam4-air-handler', 'menu_order' => 1310, 'meta_input' => array('brochure' => 'https://www.americanstandardair.com/content/dam/americanstandarair/brochure/airhandlers/15-4142-15.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'better')),
		
		array ( 'post_slug'	=> 'silver-tem8-air-handler', 'menu_order' => 1320, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_15-4142-20_AS-Air-Handler-ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'good')),
		
		array ( 'post_slug'	=> 'silver-tem6-air-handler', 'menu_order' => 1330, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_15-4142-20_AS-Air-Handler-ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'good')),
		
		array ( 'post_slug'	=> 'silver-tem4-air-handler', 'menu_order' => 1340, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_15-4142-20_AS-Air-Handler-ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'good')),
		
	// Furnaces		
		array ( 'post_slug'	=> 'platinum-95-furnace', 'menu_order' => 1200, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1173-18_AS-90-95_Gas%20Furnace_AS_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'best')),
		
		array ( 'post_slug'	=> 'platinum-80-furnace', 'menu_order' => 1210, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1111-26_AS%2080%20Gas%20Furnaces_AS_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'best')),		
		
		array ( 'post_slug'	=> 'american-standard-s9b1-gas-furnace', 'menu_order' => 1220, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1173-18_AS-90-95_Gas%20Furnace_AS_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'best')),

		array ( 'post_slug'	=> 'gold-s9v2-gas-furnace', 'menu_order' => 1230, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1173-18_AS-90-95_Gas%20Furnace_AS_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'better')),
		
		array ( 'post_slug'	=> 'gold-80v-furnace', 'menu_order' => 1240, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1111-26_AS%2080%20Gas%20Furnaces_AS_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'better')),		
		
		array ( 'post_slug'	=> 'silver-s9x1-gas-furnace', 'menu_order' => 1250, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1173-18_AS-90-95_Gas%20Furnace_AS_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'good')),
		
		array ( 'post_slug'	=> 'silver-s8x1-gas-furnace', 'menu_order' => 1260, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1111-26_AS%2080%20Gas%20Furnaces_AS_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'good')),		
				
		array ( 'post_slug'	=> 'american-standard-s8b1-gas-furnace', 'menu_order' => 1270, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1111-26_AS%2080%20Gas%20Furnaces_AS_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'good')),		
		
	// Heat Pumps	
		array ( 'post_slug'	=> 'accucomfort-platinum-20-heat-pump', 'menu_order' => 1100, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1190-11_AS_Variable_Speed_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'best')),		
		
		array ( 'post_slug'	=> 'accucomfort-platinum-18-heat-pump', 'menu_order' => 1110, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1190-11_AS_Variable_Speed_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'best')),		
		
		array ( 'post_slug'	=> 'gold-17-heat-pump', 'menu_order' => 1120, 'meta_input' => array('brochure' => 'https://www.americanstandardair.com/content/dam/americanstandarair/brochure/heatpumps/10-1113-30%20AS%20Heat%20Pumps.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'better')),		
		
		array ( 'post_slug'	=> 'silver-16-heat-pump', 'menu_order' => 1130, 'meta_input' => array('brochure' => 'https://www.americanstandardair.com/content/dam/americanstandarair/brochure/heatpumps/10-1113-30%20AS%20Heat%20Pumps.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'good')),		
		
		array ( 'post_slug'	=> 'silver-14-heat-pump', 'menu_order' => 1140, 'meta_input' => array('brochure' => 'https://www.americanstandardair.com/content/dam/americanstandarair/brochure/heatpumps/10-1113-30%20AS%20Heat%20Pumps.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'good')),			
				
	// Package Units
		array ( 'post_slug'	=> 'platinum-16-hybrid-system', 'menu_order' => 1400, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1174-11_AS_Package_Unit_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'best')),		
		
		array ( 'post_slug'	=> 'platinum-16-gaselectric-system', 'menu_order' => 1410, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1174-11_AS_Package_Unit_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'best')),		
		
		array ( 'post_slug'	=> 'platinum-16-heat-pump-system', 'menu_order' => 1420, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1174-11_AS_Package_Unit_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'best')),		
		
		array ( 'post_slug'	=> 'gold-15-gaselectric-system', 'menu_order' => 1430, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1174-11_AS_Package_Unit_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'better')),		
		
		array ( 'post_slug'	=> 'gold-15-heat-pump-system', 'menu_order' => 1440, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1174-11_AS_Package_Unit_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'better')),		
		
		array ( 'post_slug'	=> 'gold-14-hybrid-system', 'menu_order' => 1450, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1174-11_AS_Package_Unit_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'better')),		
		
		array ( 'post_slug'	=> 'silver-14-gaselectric-system', 'menu_order' => 1460, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1174-11_AS_Package_Unit_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'good')),		
		
		array ( 'post_slug'	=> 'silver-14-heat-pump-system', 'menu_order' => 1470, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1174-11_AS_Package_Unit_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'good')),		
		
		array ( 'post_slug'	=> 'silver-14-air-conditioner-system', 'menu_order' => 1480, 'meta_input' => array('brochure' => 'https://americanstandardair.com/assets/pdf/TT_10-1174-11_AS_Package_Unit_ConsumerBrochure_SV.pdf'), 'tax_input' =>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'good')),		
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
	
			
// Edit Products
	foreach ( $editProducts as $product ) :
		$productName = $product['post_slug'];
		$productOrder = $product['menu_order'];
		$productMeta = $product['meta_input'];
		$productTax = $product['tax_input'];
		
		$productPage = get_page_by_path( $productName, OBJECT, 'products' );
		
		if ( !empty( $productPage ) ) : 
			wp_update_post(array(
				'ID' 			 => $productPage->ID,
				//'menu_order'     => $productOrder,
				//'meta_input'	 =>	$productMeta,
				'tax_input'	 =>	$productTax,
			));	
		endif;
	endforeach;

// Add, Edit & Delete Taxonomies & Terms
	$editTerms = array( 'best' => 'Best', 'better' => 'Better', 'good' => 'Good');
	
	foreach ( $editTerms as $slug=>$name ) :
		$findTerm = get_term_by( 'slug', $slug, 'product-class' );
		if ( $findTerm !== false ) wp_update_term( $findTerm->term_id, 'product-class', array ( 'name' => $name) );	
	endforeach;
	
	$deleteTerms = array( 'platinum-series', 'gold-series', 'silver-series', 'samsung-series');
	
	foreach ( $deleteTerms as $slug ) :
		$findTerm = get_term_by( 'slug', $slug, 'product-class' );
		if ( $findTerm !== false ) wp_delete_term( $findTerm->term_id, 'product-class');
	endforeach;


// Update 
	$productPage = get_page_by_path('reme·halo', OBJECT, 'products' );
	if ( empty( $productPage ) ) $productPage = get_page_by_path('reme-halo', OBJECT, 'products' );
	if ( empty( $productPage ) ) $productPage = get_page_by_path('products/reme·halo', OBJECT, 'products' );
	if ( empty( $productPage ) ) $productPage = get_page_by_path('products/reme-halo', OBJECT, 'products' );
	
		
	if ( !empty( $productPage ) ) : 
		wp_update_post(array(
			'ID' 			 => $productPage->ID,
			'menu_order'     => 100,
			'post_content' => '<span class="descriptionText">Join the evolution of clean air!  The Reme·Halo neutralizes odors, particulates, air pollutants, VOCs (chemical odors), smoke, mold bacteria and viruses.</span>

<p>The Reme·Halo is proactive and sends ionized aggressive advanced oxidizers into conditioned areas to destroy pollutants at the source in the air and on surfaces, before they can reach humans. This process ensures that microbials are killed at the source for 99% reduction.</p>

<p>Easily mounted into air conditioning and heating system air ducts, the Reme·Halo creates an Advanced Oxidation Plasma consisting of Ionized Hydro-Peroxides, Super Oxide ions and Hydroxide ions that revert back to Oxygen and Hydrogen after the oxidation of the pollutants.  This occurs any time the HVAC system is in operation.</p>

<ul>
	<li>Kills airborne and surface microbials, bacteria, viruses and mold</li>
        <li>Reduces smoke, odors, VOCs, allergens, dust and particulates</li>
        <li>Automatic self-cleaning ionizers with carbon fiber brushes</li>
        <li>Unlimited cycling capability designed to turn on/off with HVAC</li>
        <li>Enhanced zinc ceramic catalyst for superior bacteria and virus reduction</li>
        <li>7 year limited warranty; 4 year cell warranty</li>
	<li>Recommended by major hotel and restaurant chains & cruise lines</li>
</ul>

[vid link="https://www.youtube.com/embed/L-t1JyUGUf4"]',
		));	
	endif;
			

}
?>