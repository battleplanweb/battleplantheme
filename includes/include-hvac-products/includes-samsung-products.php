<?php
/* Battle Plan Web Design - Add & Remove Samsung Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-samsung-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );
endif;
*/





/* PRODUCT OVERVIEW
[product-overview type="samsung ductless systems"]
*/



add_action( 'wp_loaded', 'add_samsung_products', 10 );
function add_samsung_products() {

	$brand = "samsung"; // lowercase
	$productImgAlt = "Samsung Ductless Mini Split System";


	$removeImages = array('Samsung-Wind-Free.jpg', 'Samsung-Smart-Pearl.jpg', 'Samsung-Smart-Whisper.jpg', 'Samsung-Max.jpg', 'Samsung-Quantum-17-SEER.jpg', 'Samsung-FJM-Free-Joint-Multi.jpg', 'Samsung-CAC-Multi-position-AHU.jpg');



	$removeProducts = array('samsung-cac-multi-position-ahu', 'samsung-free-joint-multi', 'samsung-max', 'samsung-quantum-17-seer', 'samsung-smart-pearl', 'samsung-whisper-wi-fi', 'samsung-wind-free');



	$addProducts = array (

	array ( 'post_title'	=>	'Samsung WindFree™* 3.0',
			'post_content' 	=>	'<span class="descriptionText">Energy efficiency is just the beginning.</span>

	<p>Our ductless mini splits have major advantages. A ductless mini split system consists of a wall-mounted indoor unit connected to an outdoor unit, and they\'re a simple way to heat and cool your home without the ductwork of a traditional central air system. For homeowners with a single room or  addition that has different heating and cooling needs than the rest of the house, a mini split system can be the perfect solution. They are often more efficient and less invasive than a ducted system and offer greater control over the temperature of these types of spaces. </p>

	<ul>
		<li>Equipped with a motion sensor to optimize energy savings and comfort.</li>
		<li>Reusable filter ensures you have a clean air filter on a regular basis can help improve your air conditioner\'s efficiency and boost the air quality in your home.</li>
		<li><b>Freeze Wash</b> allows users to maintain optimal performance with the push of a button.</li>
		<li><b>SmartThings</b> app allows users to remotely regulate temperature, adjust settings, receive real-time updates about system performance and energy usage.</li>

		<li><b>Available Capacities (Btu/h):</b> 7K / 9K / 12K / 15K / 18K / 24K</li>
		<li><b>SEER2 Rating:</b> Up to 24.5</li>
		<li><b>HSPF2 Rating:</b> Up to 10.8</li>
	</ul>',
			'post_excerpt'	=>	'Our new WindFree™* 3.0 has been upgraded to perform even more amazingly in any setting. If you\'re looking for a residential HVAC solution, this is an excellent choice.',
			'post_type'     =>	'products',
			'menu_order'  	=>  6000,
			'tax_input'		=>  array('product-brand'=>'samsung', 'product-type'=>'ductless-systems', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://samsungminisplit.com/wp-content/uploads/2023/05/82213_Samsung_2023_WindFree_3.0_Brochure_LowRes_Digital_5-15-2023.pdf'),
			'image_name'	=>	'samsung-wind-free-3.webp'
	),





	array ( 'post_title'	=>	'Samsung WindFree™* 3.0e',
			'post_content' 	=>	'<span class="descriptionText">Designed with WindFree™* Cooling technology to eliminate cold drafts.</span>

	<p>Our ductless mini splits have major advantages. A ductless mini split system consists of a wall-mounted indoor unit connected to an outdoor unit, and they\'re a simple way to heat and cool your home without the ductwork of a traditional central air system. For homeowners with a single room or  addition that has different heating and cooling needs than the rest of the house, a mini split system can be the perfect solution. They are often more efficient and less invasive than a ducted system and offer greater control over the temperature of these types of spaces. </p>

	<ul>
		<li>Equipped with a motion sensor to optimize energy savings and comfort.</li>
		<li>Reusable filter ensures you have a clean air filter on a regular basis can help improve your air conditioner\'s efficiency and boost the air quality in your home.</li>
		<li><b>Freeze Wash</b> allows users to maintain optimal performance with the push of a button.</li>
		<li><b>SmartThings</b> app allows users to remotely regulate temperature, adjust settings, receive real-time updates about system performance and energy usage.</li>

		<li><b>Available Capacities (Btu/h):</b> 7K / 9K / 12K / 15K / 18K / 24K</li>
		<li><b>SEER2 Rating:</b> Up to 20</li>
		<li><b>HSPF2 Rating:</b> Up to 8.3</li>
	</ul>',
			'post_excerpt'	=>	'Our new WindFree™* 3.0e has been upgraded to perform even more amazingly in any setting. If you\'re looking for a residential HVAC solution, this is an excellent choice.',
			'post_type'     =>	'products',
			'menu_order'  	=>  6010,
			'tax_input'		=>  array('product-brand'=>'samsung', 'product-type'=>'ductless-systems', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://samsungminisplit.com/wp-content/uploads/2023/05/82213_Samsung_2023_WindFree_3.0_Brochure_LowRes_Digital_5-15-2023.pdf'),
			'image_name'	=>	'samsung-wind-free-3e.webp'
	),





	array ( 'post_title'	=>	'Samsung WindFree™* 3.0i',
			'post_content' 	=>	'<span class="descriptionText">Equipped with a PM 1.0 filter that captures ultrafine dust particles up to 0.3µm in size.</span>

	<p>Our ductless mini splits have major advantages. A ductless mini split system consists of a wall-mounted indoor unit connected to an outdoor unit, and they\'re a simple way to heat and cool your home without the ductwork of a traditional central air system. For homeowners with a single room or  addition that has different heating and cooling needs than the rest of the house, a mini split system can be the perfect solution. They are often more efficient and less invasive than a ducted system and offer greater control over the temperature of these types of spaces. </p>

	<ul>
		<li>Equipped with a motion sensor to optimize energy savings and comfort.</li>
		<li>Reusable filter ensures you have a clean air filter on a regular basis can help improve your air conditioner\'s efficiency and boost the air quality in your home.</li>
		<li><b>Freeze Wash</b> allows users to maintain optimal performance with the push of a button.</li>
		<li><b>SmartThings</b> app allows users to remotely regulate temperature, adjust settings, receive real-time updates about system performance and energy usage.</li>

		<li><b>Available Capacities (Btu/h):</b> 7K / 9K / 12K / 15K / 18K / 24K</li>
		<li><b>SEER2 Rating:</b> Up to 20</li>
		<li><b>HSPF2 Rating:</b> Up to 8.3</li>
	</ul>',
			'post_excerpt'	=>	'WindFree™* 3.0i features a PM 1.0 air filter that can capture ultrafine dust particles smaller than the eye can see. If you\'re looking for a residential HVAC solution to improve your indoor air quality, this is an excellent choice.',
			'post_type'     =>	'products',
			'menu_order'  	=>  6020,
			'tax_input'		=>  array('product-brand'=>'samsung', 'product-type'=>'ductless-systems', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://samsungminisplit.com/wp-content/uploads/2023/05/82213_Samsung_2023_WindFree_3.0_Brochure_LowRes_Digital_5-15-2023.pdf'),
			'image_name'	=>	'samsung-wind-free-3i.webp'
	),





	array ( 'post_title'	=>	'Samsung WindFree™* 2.0e',
			'post_content' 	=>	'<span class="descriptionText">WindFree™* Cooling technology provides users with a cool indoor climate without the discomfort of direct cold airflow. It\'s an advantage no other system can match.</span>

	<p>WindFree™* Cooling technology maintains the desired temperature and eliminates cold drafts by delivering air through micro holes on the unit\'s louver and fascia panel when the louver is closed, producing a dispersed and gentle flow of air defined as “still air.”</p>

	<p>Comes with built-in Wi-Fi, allowing users to remotely regulate temperature, adjust settings, receive real-time updates about system performance and energy usage, as well as troubleshoot solutions when a repair is needed. Compatible with SmartThings app and Bixby 2.0. </p>

	<ul>
		<li><b>Scenes:</b> Control multiple devices with a single tap or voice command through the SmartThings app. A Scene can be used as a part of an Automation.</li>
		<li><b>Automations:</b>  Control your devices without manual intervention. Automations can be set to run at certain times on certain days of the week or to trigger when another device reports a certain condition (such as detecting motion or a door or window opening).</li>
		<li><b>Energy Monitoring:</b> View daily, weekly, and monthly energy consumption for your A/C system (for reference use only and not revenue grade).</li>
		<li><b>Geofencing: </b>Detects when the smartphone you’re carrying comes in and out of the range you set up on the map, and then triggers the different actions you’ve set to happen (such as turning your A/C off while you’re away, or on when you arrive home).</li>

		<li><b>Available Capacities (Btu/h):</b> 9K / 12K / 18K / 24K</li>
		<li><b>SEER2 Rating:</b> Up to 23.5</li>
		<li><b>HSPF2 Rating:</b> Up to 12.0</li>
	</ul>',
			'post_excerpt'	=>	'WindFree™* Cooling technology provides users with a cool indoor climate without the discomfort of direct cold airflow. It\'s an advantage no other system can match.',
			'post_type'     =>	'products',
			'menu_order'  	=>  6030,
			'tax_input'		=>  array('product-brand'=>'samsung', 'product-type'=>'ductless-systems', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/samsung-files/Tech_Files/Marketing_Dept/Literature/77785+Samsung+2022+RAC+Brochure+Update+rev_LR.pdf'),
			'image_name'	=>	'samsung-wind-free-2e.webp'
	),





	array ( 'post_title'	=>	'Samsung Max',
			'post_content' 	=>	'<span class="descriptionText">Heating and cooling solution for a larger, high occupancy space.</span>

	<p>Max embodies simplicity, transcending air conditioning into a whole new concept. Max\'s wide array of innovative features and benefits only enhances it\'s appeal as a great addition to your living area.</p>

	<p>Unlike conventional mini splits, Samsung systems equipped with a Digital Inverter system reaches and maintains the desired set temperature without constantly turning the compressor off and on. Smart Inverter technology automatically adjusts compressor speed to cope with any variances, assuring you experience less temperature fluctuation for optimal comfort. Conventional mini splits shut off when the set temperature is reached and turns back on when the temperature variance is high, causing large fluctuations in temperature and in comfort.</p>

	<ul>
		<li><b>Available Capacities (Btu/h):</b> 36K</li>
		<li><b>SEER2 Rating:</b> Up to 19.2</li>
		<li><b>HSPF2 Rating:</b> Up to 8.5</li>
	</ul>',
			'post_excerpt'	=>	'Heating and cooling solution for a larger, high occupancy space.',
			'post_type'     =>	'products',
			'menu_order'  	=>  6040,
			'tax_input'		=>  array('product-brand'=>'samsung', 'product-type'=>'ductless-systems', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/samsung-files/Tech_Files/Marketing_Dept/Literature/77785+Samsung+2022+RAC+Brochure+Update+rev_LR.pdf'),
			'image_name'	=>	'samsung-max.webp'
	),





	array ( 'post_title'	=>	'Samsung Hylex',
			'post_content' 	=>	'<span class="descriptionText">An HVAC game changer.</span>

	<p>Universal, inverter-driven heat pump that serves as a direct replacement for a traditional cooling-only or heat pump unitary outdoor unit. In other words, the game is about to change for residential HVAC.</p>

	<p>The Hylex™ from Samsung is compatible with virtually any existing HVAC system out there. Even better, there\'s practically no additional equipment needed - no new wires, lines sets or piping when replacing a traditional cooling-only or heat pump unitary outdoor unit. This also makes for much faster and easier installation.</p>

	<ul>
		<li>Connects to any coil with TXV</li>
		<li>Can be used with a furnace or A-coil for dual fuel applications</li>
		<li>Compatible with virtually any 24VAC thermostat</li>

		<li><b>Capacities from 2-5 tons; single and double fan options for 3 ton unit</li>
		<li><b>SEER2 Rating:</b> Up to 18.5</li>
		<li><b>HSPF2 Rating:</b> Up to 9.0</li>
	</ul>',
			'post_excerpt'	=>	'The Hylex™ unit is a universal, inverter-driven heat pump that serves as a direct replacement for a traditional cooling-only or heat pump unitary outdoor unit. In other words, the game is about to change for residential HVAC.',
			'post_type'     =>	'products',
			'menu_order'  	=>  6050,
			'tax_input'		=>  array('product-brand'=>'samsung', 'product-type'=>'ductless-systems', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/samsung-files/Tech_Files/Marketing_Dept/Literature/77785+Samsung+2022+RAC+Brochure+Update+rev_LR.pdf'),
			'image_name'	=>	'hylex.webp'
	),





	array ( 'post_title'	=>	'Samsung Multi–position Air Handling Unit',
			'post_content' 	=>	'<span class="descriptionText">Highly adaptable, and able to effectively operate in both warm and cold climates, the Multi-Position Air Handling (MPAH) unit can be installed in numerous positions for added installation flexibility.</span>

	<p>Samsung’s CAC Multi-Position AHU heat pump system offers up to 54,000 Btu/h providing efficient and quiet heating and cooling. Control can also be done with a wired controller or using our Smart Home app to adjust the temperature from anywhere at any time with a Wi-Fi connection (requires accessory Wi-Fi adapter).</p>

	<p>As long as your smartphone is connected to the internet, you can control your Ducted system from anywhere. On a hot day, you can turn on your system with your smartphone and come home to a cool house. On a chilly day, turn on the heat ahead of time so you dont have to walk into a cold living room. Having total control of your Samsung A/C was never easier.</p>

	<p>Four different ways. The condensing unit of the Duct system has four different ways you can connect the piping. Because you can position the condensing unit in different ways, this feature provides great flexibility during installation, and also gives a clean and organized look after the job is complete.</p>

	<ul>
		<li><b>Available Capacities (Btu/h):</b> 12K / 18K / 24K / 30K / 36K / 42K / 48K</li>
		<li><b>SEER2 Rating:</b> Up to 19.2</li>
		<li><b>HSPF2 Rating:</b> Up to 10.0</li>
	</ul>',
			'post_excerpt'	=>	'Highly adaptable, and able to effectively operate in both warm and cold climates, the Multi-Position Air Handling (MPAH) unit can be installed in numerous positions for added installation flexibility.',
			'post_type'     =>	'products',
			'menu_order'  	=>  6060,
			'tax_input'		=>  array('product-brand'=>'samsung', 'product-type'=>'ductless-systems', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://samsungminisplit.com/wp-content/uploads/2023/05/78755_Samsung_2022_RLC_Product_Reference_Guide_LR.pdf'),
			'image_name'	=>	'Multi–position-Air-Handling-Unit.webp'
	),





);

	// NOT require_once: the uploader is procedural and runs against this function's locals, so every
	// brand that imports in the same request has to include it again. require_once would make the
	// second brand a silent no-op.
	require get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>