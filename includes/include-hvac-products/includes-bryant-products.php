<?php
/* Battle Plan Web Design - Mass Site Update */
 
add_action( 'wp_loaded', 'add_bryant_products', 10 );
function add_bryant_products() {

	$brand = "bryant";
	$productImgAlt = "Bryant Heating & Cooling Product"; 

	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


	$addProducts = array (
	
	// Air Conditioners
		array ( 
			'post_title'	=>	'Evolution™ Extreme 26 Variable-Speed Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Experience Extreme Comfort with our Top-of-the-Line Air Conditioner</span>
<p>Elevate your home comfort with this variable-speed air conditioner that can deliver extreme humidity control, ultra-quiet operation and even receive over-the-air updates providing you with our latest software for enhanced performance. </p>

<ul>
 	<li>Up to 26 SEER</li>
	<li>Up to 50% quieter than our nearest competitor</li>
	<li>Extreme humidity control</li>
	<li>True variable-speed operation with longer, lower speed cycles</li>
	<li>Over-the-air software update capabilities</li>
	<li>Bluetooth® connectivity for enhanced service & diagnostics</li>
	<li>Senses operating conditions and adjusts to enhance system reliability</li>
	<li>Excellent performance with zoned systems</li>
	<li>Uses non-ozone depleting Puron® refrigerant</li>
	<li>DuraGuard™ Plus protection for lasting durability against the elements</li>
	<li>10-year parts limited warranty upon timely registration</li>
</ul>', 
			'post_excerpt'	=>	'Elevate your home comfort with this variable-speed air conditioner that can deliver up to 26 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1000,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/08/01-8110-1547-25.pdf'),
			'image_name'	=>	'bryant-ac-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Evolution™ Variable-Speed Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Taking Comfort and Quiet to Whole New Level</span>
<p>Small but powerful, this unique air conditioner uses inverter technology and a rotary compressor to deliver variable-speed comfort control. That translates to highly efficient, low-stage cooling that can operate down to 25% capacity for ultra-quiet operation. What\'s more, it can provide exceptional dehumidification and comfort.</p>

<ul>
 	<li>Enjoy cool, summer comfort with up to 19 SEER efficiency</li>
	<li>Optimal dehumidification capability with the Evolution Connex™ control</li>
	<li>5 stages of variable-speed operation with longer, lower stage cooling cycles</li>
	<li>Exceptionally quiet operation in low speeds down to 56 decibels</li>
	<li>Soft start and smooth ramp up to operating speeds</li>
	<li>Excellent performance with zoned systems</li>
	<li>Bryant\'s smallest ducted, variable-speed air conditioner will fit discreetly into your landscape</li>
	<li>Uses non-ozone depleting Puron® refrigerant</li>
	<li>Sheet metal construction with baked-on, complete outer paint coverage for lasting durability against the elements</li>
	<li>Attractive, louvered cabinet protects the coil against physical damage</li>
	<li>10-year parts limited warranty upon registration</li>
</ul>', 
			'post_excerpt'	=>	'Small but powerful, this unique air conditioner deliverd variable-speed comfort control and up to 19 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1010,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/08/01-8110-1547-25.pdf'),
			'image_name'	=>	'bryant-ac-02.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'Preferred™ Single-Stage Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">You Can Almost Hear the Savings</span>
<p>Our quiet single-stage air conditioner provides outstanding energy-efficient performance with SEER2 ratings up to 16.5.</p>

<ul>
	<li>Enjoy cool summer comfort with up to 16.5 SEER2 efficiency</li>
	<li>Sound as low as 72 dB</li>
	<li>Improved indoor air quality</li>
	<li>Enjoy energy savings, remote access capability and in-depth energy reporting with the ecobee Smart Thermostat by Bryant</li>
	<li>DuraGuard™ Plus protection system</li>
	<li>Environmentally-sound Puron® refrigerant</li>
	<li>10-year parts limited warranty upon registration</li>
</ul>', 
			'post_excerpt'	=>	'Our quiet single-stage air conditioner provides outstanding energy-efficient performance with SEER2 ratings up to 16.5.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1020,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/00/01-8110-1685-25.pdf'),
			'image_name'	=>	'bryant-ac-03.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'Legacy™ Line Single-Stage Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Cool Your Home with High Efficiency and High Value</span>
<p>Enjoy reliable, money-saving cooling for your home with impressive efficiency ratings up to 16.5 SEER2.</p>

<ul>
	<li>Enjoy cool summer comfort with up to 16.5 SEER2 efficiency</li>
	<li>Quiet performance with sound as low as 73 dB</li>
	<li>Enjoy energy savings, remote access capability and in-depth energy reporting with the ecobee Smart Thermostat by Bryant</li>
	<li>DuraGuard™ protection system</li>
	<li>Environmentally-sound Puron® refrigerant</li>
	<li>Quick-Sess cabinet with full coil protection</li>
</ul>', 
			'post_excerpt'	=>	'Enjoy reliable, money-saving cooling for your home with impressive efficiency ratings up to 16.5 SEER2.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1030,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/05/01-8110-1695-25.pdf'),
			'image_name'	=>	'bryant-ac-04.jpg'		
		),
		
	
	// Heat Pumps
		array ( 
			'post_title'	=>	'Evolution™ Extreme 24 Variable-Speed Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Top of the Line Heat Pump, Top-Rated Performance</span>
<p>Elevate your home comfort with this variable-speed heat pump that can deliver extreme humidity control, ultra-quiet operation and even receive over-the-air updates providing you with our latest software for enhanced performance.</p>

<ul>
	<li>Up to 50% quieter than our nearest competitor</li>
	<li>Quiet Mode feature enables homeowners to cap sound levels at 69 dBA </li>
	<li>Extreme humidity control</li>
	<li>True variable-speed operation with longer, lower speed cycles</li>
	<li>Over-the-air software update capabilities</li>
	<li>Bluetooth® connectivity for enhanced service & diagnostics</li>
	<li>Senses operating conditions and adjusts to enhance system reliability</li>
	<li>Excellent performance with zoned systems</li>
	<li>Uses non-ozone depleting Puron® refrigerant</li>
	<li>DuraGuard™ Plus protection for  lasting durability against the elements</li>
	<li>10-year parts limited warranty upon timely registration</li>
</ul>', 
			'post_excerpt'	=>	'Elevate your home comfort with this variable-speed heat pump that can deliver up to 24 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1100,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/08/01-8110-1548-25.pdf'),
			'image_name'	=>	'bryant-ac-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Evolution™ Variable-Speed Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Taking Comfort and Quiet to Whole New Level</span>
<p>Small but powerful, this unique heat pump uses inverter technology and a rotary compressor to deliver variable-speed control. That translates to highly efficient heating and cooling operation optimized to current conditions down to 25% capacity for ultra-quiet, even-temperature comfort. What\'s more, it can provide exceptional summer dehumidification.</p>

<ul>
	<li>Enjoy cool, summer comfort with up to 19 SEER efficiency</li>
	<li>Take advantage of efficient electric heating well into the winter months with up to 11 HSPF efficiency</li>
	<li>Optimal dehumidification capability with the Evolution ConnexTM control</li>
	<li>5 stages of variable-speed operation with longer, lower stage cycles</li>
	<li>Exceptionally quiet operation in low speeds down to 55 decibels</li>
	<li>Soft start and smooth ramp up to operating speeds</li>
	<li>Senses operating conditions and adjusts to enhance system reliability</li>
	<li>Excellent performance with zoned systems</li>
	<li>Bryant\'s smallest ducted, variable-speed heat pump can fit discreetly into your landscape</li>
	<li>Uses non-ozone depleting Puron® refrigerant</li>
	<li>Sheet metal construction with baked-on, complete outer paint coverage for lasting durability against the elements</li>
	<li>Attractive, louvered cabinet protects the coil against physical damage</li>
	<li>10-year parts limited warranty upon registration</li>
</ul>', 
			'post_excerpt'	=>	'Small but powerful, this unique heat pump can deliver up to 19 SEER efficiency with variable-speed control. ',
			'post_type'     =>	'products',
			'menu_order'  	=>  1110,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/08/01-8110-1548-25.pdf'),
			'image_name'	=>	'bryant-ac-02.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Preferred™ 2-stage Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Reliable Year-Round Comfort That\'s Also Cost-Effective</span>
<p>Enjoy the energy-saving, quiet operating, consistent comfort of two-speed operation with this Preferred™ Series heat pump. With cooling efficiency up to 17 SEER and heating efficiency up to 9.5 HSPF, you’ll save money while enjoying smooth, reliable comfort all year long. For extra comfort during the hot, sticky summer months, enhanced humidity control is part of the package.</p>

<ul>
	<li>Enjoy cool, summer comfort with up to 17 SEER efficiency</li>
	<li>Take advantage of efficient electric heating well into the winter months with up to 9.5 HSPF efficiency</li>
	<li>Two-stage operation allows longer, more consistent comfort cycles on low stage</li>
	<li>Enhanced summer dehumidification through two-stage operation</li>
	<li>Sound as low as 70 dB</li>
	<li>Enjoy Wi-Fi®±remote access capability to allow complete programming and change control from anywhere with the Bryant® Housewise™ thermostat</li>
	<li>DuraGuard™ Plus protection system</li>
	<li>Environmentally-sound Puron® refrigerant</li>
	<li>10-year parts limited warranty upon registration</li>
</ul>', 
			'post_excerpt'	=>	'Enjoy the energy-saving, quiet operating, consistent comfort of two-speed operation and up to 17 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1120,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/0F/01-8110-1573-25.pdf'),
			'image_name'	=>	'bryant-ac-03.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Legacy™ Single-Stage Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">A Budget-friendly Heat Pump to Cool and Warm Your Home</span>
<p>This Legacy™ heat pump provides low-cost all-season comfort, cooling your home in summer with its up to 15 SEER rating, then reversing when temperatures drop for economical electric heat. Our most affordable ENERGY STAR® qualified heat pump, it adds up to year-round savings on your utility bills. </p>

<ul>
	<li>Enjoy cool, summer comfort with up to 15 SEER efficiency</li>
	<li>Take advantage of efficient electric heating well into the winter months with up to 8.5 HSPF efficiency</li>
	<li>Sound as low as 69 dB</li>
	<li>Enjoy Wi-Fi®± remote access capability to allow complete programming and change control from anywhere with the Bryant® Housewise™ thermostat</li>
	<li>DuraGuard™ protection system</li>
	<li>Environmentally-sound Puron® refrigerant</li>
	<li>10-year parts limited warranty upon registration</li>
</ul>', 
			'post_excerpt'	=>	'A heat pump that cools your home in summer with its up to 15 SEER rating and reverses when temperatures drop for economical electric heat.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1130,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://www.trane.com/pdf/TT_72-1209-31_Heat-Pump-Brochure_Bryant_SV.pdf'),
			'image_name'	=>	'bryant-ac-04.jpg'		
		),
		
	
	// Furnaces
		array ( 
			'post_title'	=>	'Evolution™ 96 Variable-Speed Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">Exceptional Efficiency and Cozy Comfort</span>
<p>Choose the Model 986T with Perfect Heat® technology and up to 96.7% AFUE efficiency. It’s the perfect way to indulge your senses in consistently warm and impeccably controlled comfort all winter long. As a part of your year-round comfort system, this variable speed furnace also provides welcome relief from hot, sticky summer humidity.</p>

<ul>
 	<li>Manage your utility costs with high-efficiency comfort: up to 96.7% AFUE</li>
	<li>Enjoy the ultra-quiet performance and even-temperature comfort of variable speed air delivery</li>
	<li>Two-stage operation allows longer, more consistent heating cycles on low stage for savings and comfort</li>
	<li>Perfect Heat™ technology means consistent comfort by adjusting system operation to changing conditions</li>
	<li>Perfect Humidity™ technology removes more moisture than a standard furnace during cooling operation</li>
	<li>Fan On Plus™ technology lets you choose between four speeds of continuous fan operation with a compatible control</li>
	<li>Insulated cabinet for quieter operation</li>
	<li>External filter cabinet makes filter changes easier</li>
	<li>Lifetime limited warranty on heat exchanger upon timely registration</li>
	<li>10-year parts limited warranty upon registration</li>
</ul>', 
			'post_excerpt'	=>	'The perfect way to indulge your senses in consistently warm and impeccably controlled comfort all winter long.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1200,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/03/01-8110-1568-25.pdf'),
			'image_name'	=>	'bryant-f-01.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'Evolution™ 80 Variable-Speed Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">Energy-Efficient Heating with Consistent Comfort</span>
<p>Enjoying energy-wise comfort is now easier than ever with our Model 880TA gas furnace and simplified system control. You\'ll have the cozy and consistent comfort of variable speed operation with a furnace that is extremely efficient in its electrical use year round. During the summer months, variable speed airflow also provides enhanced comfort through humidity control.</p>

<ul>
 	<li>Quiet performance and even-temperature comfort from variable-speed air delivery</li>
	<li>Two-stage operation allows longer, more even heating cycles on low stage</li>
	<li>Perfect Heat™ technology closely manages your comfort by adjusting system operation to changing conditions</li>
	<li>Perfect Humidity™ technology ensures optimal summertime cooling dehumidification</li>
	<li>Fan On Plus™ technology lets you choose the speed of continuous fan operation with a compatible control</li>
	<li>Insulated cabinet for quieter operation</li>
	<li>Lifetime heat exchanger limited warranty upon timely registration</li>
	<li>10-year parts limited warranty upon registration</li>
</ul>', 
			'post_excerpt'	=>	'Enjoying energy-wise comfort is now easier than ever with this 80% AFUE efficient gas furnace.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1210,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/03/01-8110-1568-25.pdf'),
			'image_name'	=>	'bryant-f-02.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'Preferred™ 80 Series Variable-Speed Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">Enjoy Even, Indoor Temperatures as Outdoor Conditions Change</span>
<p>There\'s a reason people love our Preferred™ Series gas furnaces. Several reasons, actually – they’re energy efficient, quiet and ultra-reliable. The Model 820(1)TA uses variable speed technology to achieve extra-consistent comfort during the colder months and squeeze humidity out of the air during the hotter ones.</p>

<ul>
	<li>Quiet performance and even-temperature comfort from variable-speed air delivery</li>
	<li>Two-stage operation allows longer, more even heating cycles on low stage</li>
	<li>Perfect Heat® technology means consistent comfort by adjusting system operation to changing conditions</li>
	<li>Fan On Plus™ technology lets you choose the speed of continuous fan operation with a compatible control</li>
	<li>Insulated cabinet for quieter operation</li>
	<li>20-year limited warranty on heat exchanger</li>
	<li>10-year parts limited warranty upon registration upon timely registration</li>
</ul>', 
			'post_excerpt'	=>	'Variable speed technology helps this gas furnace achieve extra-consistent comfort during the colder months and squeeze humidity out of the air during the hotter ones.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1220,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'furnaces', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/0D/01-8110-1569-25.pdf'),
			'image_name'	=>	'bryant-f-02.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'Legacy™ 80 Line Fixed-Speeds 80% Efficiency Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">Trusted Value in Heating Comfort</span>
<p>Join the ranks of homeowners who put their trust in Bryant for their indoor comfort needs. The Model 810SA delivers solid, dependable heating with an added touch: -- Fan On Plus™ technology for enhanced control over constant fan airflow.</p>

<ul>
	<li>Fan On Plus™ technology lets you choose the speed of continuous fan operation with a compatible control</li>
	<li>QuieTech™ noise reduction system</li>
	<li>Pilot-free PerfectLight™ ignition</li>
	<li>Insulated cabinet for quieter operation</li>
	<li>20-year limited warranty on heat exchanger</li>
	<li>10-year parts limited warranty upon registration upon timely registration</li>
</ul>', 
			'post_excerpt'	=>	'This gas furnace delivers solid, dependable heating and enhanced control over constant fan airflow.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1220,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/0B/01-8110-1570-25.pdf'),
			'image_name'	=>	'bryant-f-02.jpg'		
		),
		
		
		// Air Handlers
		array ( 
			'post_title'	=>	'Evolution™ System Fan Coil',
			'post_content' 	=>	'<span class="descriptionText">Fan Coils for the Ultimate in Cooling and Heating Efficiency</span>
<p>Evolution™ fan coils are an easy way to cut cooling costs by up to 16% and heating costs up to 10%. They also feature extra quiet, efficient and consistent temperatures with variable speed control, along with environmentally sound Puron® refrigerant. This fan coil can also provide relief from hot, sticky summers with superior humidity control capabilities.</p>

<ul>
 	<li>Cooling savings up to 16%</li>
	<li>Heating savings up to 10%</li>
	<li>PuronR refrigerant</li>
	<li>Puron refrigerant-specific Thermostatic Expansion Valve metering device</li>
	<li>Smart Diagnostics</li>
	<li>Evolution™ control board</li>
	<li>DuraTech™ coil protection for enhanced corrosion resistance</li>
	<li>Advanced user interface</li>
	<li>Improved humidity control</li>
	<li>Advanced temperature control</li>
	<li>10-year parts limited warranty upon timely registration</li>
</ul>', 
			'post_excerpt'	=>	'This fan coil can cut cooling costs by up to 16% and heating costs up to 10%.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1300,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'air-handlers', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/00/01-8110-1429-01.pdf'),
			'image_name'	=>	'bryant-ah-01.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'Preferred™ Series Fan Coil',
			'post_content' 	=>	'<span class="descriptionText">Fan Coils for Improved Cooling and Heating Efficiency</span>
<p>Preferred™ fan coils are a reliable way to boost comfort and energy savings. The model FV4C fan coil can help increase your air conditioner’s published efficiency rating by up to 2 SEER. That means cutting cooling costs by up to 16% and heating costs up to 10%. All aluminum coils assures durable performance and year-round comfort. </p>

<ul>
	<li>Environmentally sound Puron® refrigerant</li>
	<li>Puron refrigerant-specific Thermostatic Expansion Valve metering device</li>
	<li>Cooling savings up to 16%</li>
	<li>Heating savings up to 10%</li>
	<li>DuraTech™ coil protection for enhanced corrosion resistance</li>
	<li>Variable speed fan </li>
	<li>Improved humidity control</li>
	<li>Enhanced indoor air quality</li>
	<li>Optional electrical resistance heat</li>
	<li>10-year parts limited warranty upon registration</li>
</ul>', 
			'post_excerpt'	=>	'This fan coil is a reliable way to boost comfort and energy savings.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1310,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'air-handlers', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/00/01-8110-1429-01.pdf'),
			'image_name'	=>	'bryant-ah-01.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'Legacy™ Line Fan Coil ',
			'post_content' 	=>	'<span class="descriptionText">Fan Coils for Economical Cooling and Heating Comfort</span>
<p>Legacy™ fan coils are an affordable addition to cooling and heating systems. These dependable products can help improve indoor air quality and reduce energy costs, year-round. Environmentally sound Puron® refrigerant helps improve outdoor air quality, too. </p>

<ul>
	<li>Environmentally sound Puron® refrigerant</li>
	<li>Puron refrigerant-specific Thermostatic Expansion Valve or piston metering device</li>
	<li>Foil-faced insulation</li>
	<li>DuraTech™ coil protection for enhanced corrosion resistance</li>
	<li>High efficiency, 5 speed fan</li>
	<li>Enhanced indoor air quality</li>
	<li>Optional electrical resistance heat</li>
	<li>10-year parts limited warranty upon registration</li>
</ul>', 
			'post_excerpt'	=>	'This fan coil is an affordable addition to cooling and heating systems',
			'post_type'     =>	'products',
			'menu_order'  	=>  1320,
			'tax_input'		=>  array('product-brand'=>'Bryant', 'product-type'=>'air-handlers', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://www.shareddocs.com/hvac/docs/1010/Public/00/01-8110-1429-01.pdf'),
			'image_name'	=>	'bryant-ah-01.jpg'		
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