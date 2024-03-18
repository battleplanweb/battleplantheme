<?php
/* Battle Plan Web Design - Add & Remove American Standard Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-american-standard-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_american_standard_products', 10 );
function add_american_standard_products() {

	$brand = "american-standard"; // lowercase
	$productImgAlt = "American Standard Heating & Cooling Product"; 


	
	$removeImages = array('American-Standard-46.jpg', 'American-Standard-45.jpg', 'American-Standard-44.jpg', 'American-Standard-43.jpg', 'Nexia-Home-Intelligence.jpg', 'American-Standard-22.jpg', 'American-Standard-14.jpg', 'American-Standard-13.jpg', 'American-Standard-42.jpg', 'American-Standard-34.jpg', 'American-Standard-33.jpg', 'American-Standard-41.jpg', 'American-Standard-32.jpg', 'American-Standard-02.jpg', 'American-Standard-12.jpg', 'American-Standard-11.jpg', 'American-Standard-31.jpg', 'American-Standard-21.jpg', 'American-Standard-04.jpg', 'American-Standard-01.jpg');

	
	
	$removeProducts = array('accucomfort-platinum-18-air-conditioner', 'gold-17-air-conditioner', 'silver-16-air-conditioner', 'silver-14-air-conditioner', 'accucomfort-platinum-20-air-conditioner', 'silver-tem6-air-handler', 'forefront-platinum-tam9-air-handler', 'silver-tem8-air-handler', 'silver-tem4-air-handler', 'forefront-gold-tam4-air-handler', 'nexia-home-intelligence', 'silver-s8x1-gas-furnace', 'american-standard-s8b1-gas-furnace', 'silver-s9x1-gas-furnace', 'american-standard-s9b1-gas-furnace', 'gold-s9v2-gas-furnace', 'platinum-80-furnace', 'gold-80v-furnace', 'platinum-95-furnace', 'accucomfort-platinum-20-heat-pump', 'accucomfort-platinum-18-heat-pump', 'gold-17-heat-pump', 'silver-14-heat-pump', 'silver-16-heat-pump', 'platinum-16-gaselectric-system', 'silver-14-air-conditioner-system', 'gold-15-gaselectric-system', 'silver-14-gaselectric-system', 'platinum-16-heat-pump-system', 'gold-15-heat-pump-system', 'silver-14-heat-pump-system', 'platinum-16-hybrid-system', 'gold-14-hybrid-system' );	
	
	

	$addProducts = array (		
	
	// Air Conditioners
array ( 'post_title'	=>	'Platinum 20 Variable Speed Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Be in control of your home temperature with a smart, efficient air conditioner.</span>
		
		

<ul>
	<li><b>Precise temperature control:</b> With AccuComfort™ variable speed technology, the Platinum 20 can gradually adjust its speed at a broader range to create a consistent flow of cool, comfortable air. This way, your system can continuously match the temperature you want at home.</li>
	<li><b>Reliable durability:</b> Rely on your air conditioner for years to come. The Platinum 20 is built for durability with quality materials, innovative features, and a sturdy construction.</li>
	<li><b>Optimize indoor air quality:</b> If you add an AccuClean® Air Cleaner, your air conditioner can filter out more dust and harmful irritants from the air so you can breathe easier at home.</li>
	<li><b>Communication technology:</b> With Link communicating technology, your Platinum 20 controls communication between the thermostat, indoor unit and outdoor unit. This way, it can maximize efficiency and home comfort.</li>
	<li><b>SEER2:</b> Up to 21.5</li>
	<li><b>Sound:</b> 55-75 dBA</li>
	<li><b>Cooling Stages:</b> Variable</li>
	<li><b>Energy Savings:</b> Up to 55%</li>
</ul>', 
		'post_excerpt'	=>	'Be in control of your home temperature with a smart, efficient air conditioner.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1000,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-air-conditioner-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-01.webp'		
),
				
array ( 'post_title'	=>	'Platinum 18 Variable Speed Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Cool your home with an air conditioner that’s both quiet and efficient.</span>
		
		

<ul>
	<li><b>Precise temperature control:</b> With AccuComfort™ variable speed technology, the Platinum 18 can gradually adjust its speed at a broader range to create a consistent flow of cool, comfortable air. This way, your system can continuously match the temperature you want at home. </li>
	<li><b>Reliable durability:</b> Rely on your air conditioner for years to come. The Platinum 18 is built for durability with quality materials, innovative features, and a sturdy construction.</li>
	<li><b>Optimize indoor air quality:</b> If you add an AccuClean® Air Cleaner, your air conditioner can filter out more dust and harmful irritants from the air so you can breathe easier at home.</li>
	<li><b>Communication technology:</b> With Link communicating technology, your Platinum 18 controls communication between the thermostat, indoor unit and outdoor unit. This way, it can maximize efficiency and home comfort.</li>
	<li><b>SEER2:</b> Up to 18</li>
	<li><b>Sound:</b> 55-75 dBA</li>
	<li><b>Cooling Stages:</b> Variable</li>
	<li><b>Energy Savings:</b> Up to 44%</li>
</ul>', 
		'post_excerpt'	=>	'Cool your home with an air conditioner that’s both quiet and efficient.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1010,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-air-conditioner-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-01.webp'		
),
				
array ( 'post_title'	=>	'Platinum 17 Variable Speed Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Stay comfortably cool no matter how hot the weather gets.</span>
		
		

<ul>
	<li><b>Precise temperature control:</b> With AccuComfort™ variable speed technology, the Platinum 17 can gradually adjust its speed at a broader range to create a consistent flow of cool, comfortable air. This way, your system can continuously match the temperature you want at home.</li>
	<li><b>Reliable durability:</b> Rely on your air conditioner for years to come. The Platinum 17 is built for durability quality materials, innovative features and a sturdy construction.</li>
	<li><b>Optimize indoor air quality:</b> If you add an AccuClean® Air Cleaner, your air conditioner can filter out more dust and harmful irritants from the air so you can breathe easier at home.</li>
	<li><b>Communication technology:</b> With Link communicating technology, your Platinum 17 controls communication between the thermostat, indoor unit and outdoor unit. This way, it can maximize efficiency and home comfort.</li>
	<li><b>SEER2:</b> Up to 17</li>
	<li><b>Sound:</b> 66-85 dBA</li>
	<li><b>Cooling Stages:</b> Variable</li>
	<li><b>Energy Savings:</b> Up to 44%</li>
</ul>', 
		'post_excerpt'	=>	'Stay comfortably cool no matter how hot the weather gets.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1020,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-air-conditioner-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-02.webp'		
),
				
array ( 'post_title'	=>	'Gold 16 Two-Stage Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Keep your home energy-efficient no matter how hot the weather gets.</span>

<ul>
	<li><b>Efficient performance:</b> With a 16.2 SEER2 rating, this air conditioner comfortably cools your home while staying energy efficient.</li>
	<li><b>Reliable durability:</b> Rely on your air conditioner for years to come. The Gold 16 is built for durability with quality materials, innovative features, and a sturdy construction.</li>
	<li><b>Optimize indoor air quality:</b> If you add an AccuClean® Air Cleaner, your air conditioner can filter out more dust and harmful irritants from the air so you can breathe easier at home.</li>
	<li><b>Environmentally friendly:</b> Take care of your environment. American Standard air conditioners cool your home with a refrigerant that’s ozone-safe.</li>
	<li><b>SEER2:</b> Up to 16.2</li>
	<li><b>Sound:</b> 72-74 dBA</li>
	<li><b>Cooling Stages:</b> Two</li>
	<li><b>ENERGY STAR® Qualified:</b> Yes</li>
	<li><b>Energy Savings:</b> Up to 44%</li>
</ul>', 
		'post_excerpt'	=>	'Keep your home energy-efficient no matter how hot the weather gets.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1030,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-air-conditioner-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-01.webp'		
),
				
array ( 'post_title'	=>	'Silver 15 Single-Stage Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Stay cool with a durable air conditioner for many seasons to come.</span>

<ul>
	<li><b>Efficient performance:</b> With a 15.6 SEER2 rating, this air conditioner comfortably cools your home while staying energy efficient.</li>
	<li><b>Reliable durability:</b> Rely on your air conditioner for years to come. The Silver 15 is built for durability with quality materials, innovative features, and a sturdy construction.</li>
	<li><b>Optimize indoor air quality:</b> If you add an AccuClean® Air Cleaner, your air conditioner can filter out more dust and harmful irritants from the air so you can breathe easier at home.</li>
	<li><b>Environmentally friendly:</b> Take care of your environment. American Standard air conditioners cool your home with a refrigerant that’s ozone-safe.</li>
	<li><b>SEER2:</b> Up to 16</li>
	<li><b>Sound:</b> 71-74 dBA</li>
	<li><b>Cooling Stages:</b> One</li>
	<li><b>ENERGY STAR® Qualified:</b> Yes</li>
	<li><b>Energy Savings:</b> Up to 41%</li>
</ul>', 
		'post_excerpt'	=>	'Stay cool with a durable air conditioner for many seasons to come.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1040,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-air-conditioner-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-01.webp'		
),
				
array ( 'post_title'	=>	'Silver 14 Single-Stage Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Enjoy a great blend of reliable cooling, energy efficiency and value.</span>

<ul>
	<li><b>Efficient at a great value:</b> Help lower your energy costs with this 14.8 SEER2 air conditioner that balances energy efficiency and cooling strength.</li>
	<li><b>Reliable durability:</b> Rely on your air conditioner for years to come. The Silver 14 is built for durability with quality materials, innovative features, and a sturdy construction.</li>
	<li><b>Optimize indoor air quality:</b> If you add an AccuClean® Air Cleaner, your air conditioner can filter out more dust and harmful irritants from the air so you can breathe easier.</li>
	<li><b>Environmentally friendly:</b> Take care of your environment. American Standard air conditioners cool your home with a refrigerant that’s ozone-safe.</li>
	<li><b>SEER2:</b> Up to 14.8</li>
	<li><b>Sound:</b> 72-73 dBA</li>
	<li><b>Cooling Stages:</b> One</li>
	<li><b>ENERGY STAR® Qualified:</b> Yes</li>
	<li><b>Energy Savings:</b> Up to 38%</li>
</ul>', 
		'post_excerpt'	=>	'Enjoy a great blend of reliable cooling, energy efficiency and value.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1050,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-air-conditioner-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-01.webp'		
),
		
		
		
		
	
	// Heat Pumps
array ( 'post_title'	=>	'Platinum 20 Variable Speed Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">Experience home comfort efficiency at a whole new level with state-of-the-art heating and cooling technology.</span>
		
<ul>
	<li><b>Comfort and quality meets efficiency:</b> Built with quality materials and innovative features, the AccuComfort™ Platinum 20 Heat Pump is one of the industry’s most efficient systems on the market, with ratings up to 20.5 SEER2 and 8.7 HSPF.</li>
	<li><b>Multi-stage heating and cooling technology:</b> State-of-the-art, multi-stage heating and cooling system that consistently adjusts to run at a more efficient speed to maintain optimal levels of comfort.</li>
	<li><b>Quiet, reliable AccuComfort™ technology:</b> Enjoy calm comfort through variable speed heating and cooling, designed to meet your unique needs. Consistent with ½ degree in 1/10th of 1% increments, so you get the comfort you set and the AccuComfort™ technology does the rest.</li>
	<li><b>Clean Air technology:</b> The lower compressor modulation and fan speeds yield amazingly low sound levels and max out the benefits of AccuClean® Air Cleaner technology, giving you the advantage of optimized air quality.</li>
	<li><b>The hybrid system advantage:</b> Pair your heat pump with a gas furnace to enjoy the benefits of a hybrid system. Once your heat pump reaches its heating capacity, your gas furnace steps in to keep you comfortable. Together, they offer you reliable comfort that could lower your energy costs.</li>
	<li><b>SEER2:</b> Up to 20.5</li>
	<li><b>HSPF2:</b> Up to 8.7</li>
	<li><b>Sound:</b> 54-76 dBA</li>
	<li><b>Fan Stages:</b> Variable</li>
	<li><b>Energy Savings:</b> Up to 50%</li>
</ul>', 
		'post_excerpt'	=>	'Experience home comfort efficiency at a whole new level with state-of-the-art heating and cooling technology.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1100,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-heat-pump-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-02.webp'		
),		
	
array ( 'post_title'	=>	'Platinum 18 Variable Speed Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">Get year-round comfort with a variety of heating and cooling speeds to meet your temperature needs.</span>
		
		

<ul>
	<li><b>Variable speeds, maximum comfort:</b> AccuComfort™ technology allows the variable-speed system to consistently adjust to run at a more efficient speed to maintain your personal level of home comfort.</li>
	<li><b>Top-ranked, highly efficient:</b> This heat pump is ranked one of the most efficient on the market as it has top SEER2 and HSPF2 ratings and automatically adjusts to keep you comfortable.</li>
	<li><b>Quiet comfort:</b> Quiet system operation compared to competitors for dependable comfort that works smarter for ideal home enjoyment.</li>
	<li><b>A system you can count on:</b> Built with quality materials, innovative features, durable construction and backed by our independent American Standard Heating & Air Conditioning Dealers to ensure you get dependable comfort for years to come.</li>
	<li><b>The hybrid system advantage:</b> Pair your heat pump with a gas furnace to enjoy the benefits of a hybrid system. Once your system reaches its heating capacity, your gas furnace steps in to keep you comfortable. Together, they offer you reliable comfort that could lower your energy costs.</li>
	<li><b>SEER2:</b> Up to 18</li>
	<li><b>HSPF2:</b> Up to 8.5</li>
	<li><b>Sound:</b> 54-76 dBA</li>
	<li><b>Fan Stages:</b> Variable</li>
	<li><b>Energy Savings:</b> Up to 44%</li>
</ul>', 
		'post_excerpt'	=>	'Get year-round comfort with a variety of heating and cooling speeds to meet your temperature needs.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1110,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-heat-pump-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-02.webp'		
),		
	
array ( 'post_title'	=>	'Platinum 17 Variable Speed Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">In-home comfort and high efficiency.</span>		

<ul>
	<li><b>Variable speeds, maximum comfort:</b> AccuComfort™ technology allows the variable-speed system to consistently adjust to run at a more efficient speed to maintain your personal level of home comfort.</li>
	<li><b>A system you can count on:</b> Built with quality materials, innovative features, durable construction and backed by our independent American Standard Heating & Air Conditioning Dealers to ensure you get dependable comfort for years to come.</li>
	<li><b>The hybrid system advantage:</b> Pair your heat pump with a gas furnace to enjoy the benefits of a hybrid system. Once your system reaches its heating capacity, your gas furnace steps in to keep you comfortable. Together, they offer you reliable comfort that could lower your energy costs.</li>
	<li><b>SEER2:</b> Up to 17</li>
	<li><b>HSPF2:</b> Up to 8.5</li>
	<li><b>Sound:</b> 55-76 dBA</li>
	<li><b>Fan Stages:</b> Variable</li>
	<li><b>Energy Savings:</b> Up to 44%</li>
</ul>', 
		'post_excerpt'	=>	'In-home comfort and high efficiency.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1120,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-heat-pump-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-02.webp'		
),		
	
array ( 'post_title'	=>	'Gold 16 Two-Stage Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">Experience incredible in-home comfort with a highly efficient heat pump that’s environmentally friendly and quiet.</span>

<ul>
	<li><b>Very efficient, environmentally friendly:</b> Save on your heating and cooling energy usage while reducing greenhouse gas emissions for feel-good, responsible home comfort.</li>
	<li><b>Year-round comfort:</b> Uses incredible two-stage heat pumps in multiple speeds to provide an excellent mix of value, contentment, and efficiency all year long.</li>
	<li><b>Save energy at home:</b> The Gold 16 heat pump is a great choice that may help save energy and lower your your monthly energy use.</li>
	<li><b>Quiet operation:</b> A system that’s quiet and has top-tier efficiency ratings so that you can save on energy bills and enjoy a comfortable house without the noise.</li>
	<li><b>The hybrid system advantage:</b> Pair your heat pump with a gas furnace to enjoy the benefits of a hybrid system. Once your heat pump reaches its heating capacity, your gas furnace steps in to keep you comfortable. Together, they offer you reliable comfort that could lower your energy costs.</li>
	<li><b>SEER2:</b> Up to 16.2</li>
	<li><b>HSPF2:</b> Up to 8.1</li>
	<li><b>Sound:</b> 72-74 dBA</li>
	<li><b>Fan Stages:</b> Two</li>
	<li><b>ENERGY STAR® Qualified:</b> Yes</li>
	<li><b>Energy Savings:</b> Up to 44%</li>
</ul>', 
		'post_excerpt'	=>	'Experience incredible in-home comfort with a highly efficient heat pump that’s environmentally friendly and quiet.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1130,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-heat-pump-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-01.webp'		
),		
	
array ( 'post_title'	=>	'Silver 15 Single-Stage Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">Enjoy efficient heating and cooling performance and premium comfort—with a value that can’t be beat.</span>

<ul>
	<li><b>Value meets efficiency:</b> Our best value, the Silver 15 Heat Pump has a 15.6 SEER2 rating and saves you up to 50 percent on your heating and cooling energy usage.</li>
	<li><b>Reliable comfort, environmentally friendly:</b> The Silver 15 Heat Pump surpasses government efficiency standards and cools and heats with an environmentally-friendly refrigerant that is ozone safe and helps to reduce greenhouse gas emissions.</li>
	<li><b>A system you can count on:</b> The Silver 15 Heat Pump offers affordable heating and cooling that provides efficient and reliable cooling, thanks to its Spine Fin™ coil and Duration™ compressor.</li>
	<li><b>Quiet operation:</b> A system that’s quiet and has top-tier efficiency ratings so that you can save on energy bills and enjoy a comfortable house without the noise.</li>
	<li><b>The hybrid system advantage:</b> Pair your heat pump with a gas furnace to enjoy the benefits of a hybrid system. Once your heat pump reaches its heating capacity, your gas furnace steps in to keep you comfortable. Together, they offer you reliable comfort that could lower your energy costs.</li>
	<li><b>SEER2:</b> Up to 16</li>
	<li><b>HSPF2:</b> Up to 8.1</li>
	<li><b>Sound:</b> 70-75 dBA</li>
	<li><b>Fan Stages:</b> One</li>
	<li><b>ENERGY STAR® Qualified:</b> Yes</li>
	<li><b>Energy Savings:</b> Up to 41%</li>
</ul>', 
		'post_excerpt'	=>	'Enjoy efficient heating and cooling performance and premium comfort—with a value that can’t be beat.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1140,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-heat-pump-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-01.webp'		
),		
	
array ( 'post_title'	=>	'Silver 14 Single-Stage Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">Enjoy dependable heating and cooling in your home that is energy efficient, environmentally friendly, and comfortable. </span>

<ul>
	<li><b>Efficiency that helps you save:</b> The Silver 14 Heat Pump has a SEER2 rating of up to 14.8, making it a very efficient system that can provide you comfort year after year and help you save on your energy bill.</li>
	<li><b>Quiet, reliable comfort:</b> Quiet system operation allows for dependable and distraction-free home comfort that allows you to enjoy your surroundings.</li>
	<li><b>Environmentally friendly, great value:</b> The Silver 14 Heat Pump helps you save up to 47 percent on your heating and cooling energy usage while helping to reduce greenhouse gas emissions.</li>
	<li><b>A system you can count on:</b> The Silver 14 Heat Pump offers affordable heating and cooling that provides efficient and reliable cooling, thanks to its Spine Fin™ coil and Duration™ compressor.</li>
	<li><b>The hybrid system advantage:</b> Pair your heat pump with a gas furnace to enjoy the benefits of a hybrid system. Once your heat pump reaches its heating capacity, your gas furnace steps in to keep you comfortable. Together, they offer you reliable comfort that could lower your energy costs.</li>
	<li><b>SEER2:</b> Up to 14.8</li>
	<li><b>HSPF2:</b> Up to 7.8</li>
	<li><b>Sound:</b> 71-76 dBA</li>
	<li><b>Fan Stages:</b> One</li>
	<li><b>Energy Savings:</b> Up to 38%</li>
</ul>', 
		'post_excerpt'	=>	'Enjoy dependable heating and cooling in your home that is energy efficient, environmentally friendly, and comfortable. ',
		'post_type'     =>	'products',
		'menu_order'  	=>  1150,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-heat-pump-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-01.webp'		
),	
		
		
		
		
	
	// Furnaces
array ( 'post_title'	=>	'Platinum 95 Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">Stay warm and comfortable inside with a gas furnace featuring fully modulating heating.</span>

<ul>
	<li><b>Communication technology:</b> The Platinum 95 Gas Furnace is compatible with American Standard AccuLink™ Communicating System. With this communicating technology, homeowners can remotely adjust settings and program alerts, so you know your system is working at its best.</li>
	<li><b>Quiet operation</b> Thanks to a heavy steel insulated cabin, this furnace is quiet and holds more heat in the furnace to better warm your home. With operation as quiet as this, you can enjoy the benefits of warm air without any disruption to your living environment.</li>
	<li><b>Flexible temperature control:</b> While this gas furnace does a great job heating, match it with a heat pump to enjoy energy saving benefits and a more complete temperature control experience. That means, the heat pump can act as the primary source of heat in milder temperatures, but when the weather gets cold, your system activates your furnace to deliver the heat you need.</li>
	<li><b>Built to last:</b> You won’t have to worry about this furnace withstanding the test of time. Its cabinet and components are durable and built to deliver comfort for years to come.</li>
	<li><b>AFUE:</b> Up to 97%</li>
	<li><b>Heating Stages:</b> Modulating</li>
</ul>', 
		'post_excerpt'	=>	'Stay warm and comfortable inside with a gas furnace featuring fully modulating heating.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1200,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-fur-90-brochure-current.pdf'),
		'image_name'	=>	'American-Standard-03.webp'		
),
		
array ( 'post_title'	=>	'Gold S9V2 Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">Enjoy warm air throughout your home with this two stage heating system.</span>

<ul>
	<li><b>Flexible temperature control:</b> While this gas furnace does a great job heating, match it with a heat pump to enjoy energy saving benefits and a more complete temperature control experience. That means, the heat pump can act as the primary source of heat in milder temperatures, but when the weather gets cold, your system activates your furnace to deliver the heat you need.</li>
	<li><b>Built to last:</b> You won\'t have to worry about this furnace withstanding the test of time. Its cabinet and components are durable and built to deliver comfort for years to come.</li>
	<li><b>Improved indoor air quality:</b> Combine the Gold S9V2 Gas Furnace with the American Standard AccuClean® Air Cleaner for more comfortable air in your home. This air cleaner helps remove allergens, bacteria, and viruses from the air you breathe in your home.</li>
	<li><b>Steady, warm air:</b> With the Gold S9V2 gas furnace, you\'ll be met with consistent, steady flows of warm air, so you don\'t need to worry about uneven indoor temperatures again.</li>
	<li><b>AFUE:</b> Up to 96%</li>
	<li><b>Heating Stages:</b> Two</li>
	<li><b>ENERGY STAR® Qualified:</b> Yes</li>
</ul>', 
		'post_excerpt'	=>	'Enjoy warm air throughout your home with this two stage heating system.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1210,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-fur-90-brochure-current.pdf'),
		'image_name'	=>	'American-Standard-04.webp'		
),
		
array ( 'post_title'	=>	'Silver S9X1 Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">Feel warm and cozy inside all year long with this single-stage heating system.</span>

<ul>
	<li><b>Flexible temperature control:</b> While this gas furnace does a great job heating, match it with a heat pump to enjoy energy saving benefits and a more complete temperature control experience. That means, the heat pump can act as the primary source of heat in milder temperatures, but when the weather gets cold, your system activates your furnace to deliver the heat you need.</li>
	<li><b>Built to last:</b> You won\'t have to worry about this furnace withstanding the test of time. Its cabinet and components are durable and built to deliver comfort for years to come.</li>
	<li><b>Improved indoor air quality:</b> Combine the Silver S9X1 Gas Furnace with the American Standard AccuClean® Air Cleaner for more comfortable air in your home. This air cleaner helps remove allergens, bacteria, and viruses from the air you breathe in your home.</li>
	<li><b>Steady, warm air:</b> With the S9X1 Gas Furnace, you\'ll be met with consistent, steady flows of warm air, so you don\'t need to worry about uneven indoor temperatures again.</li>
	<li><b>AFUE:</b> Up to 96%</li>
	<li><b>Heating Stages:</b> One</li>
	<li><b>ENERGY STAR® Qualified:</b> Yes</li>
</ul>', 
		'post_excerpt'	=>	'Feel warm and cozy inside all year long with this single-stage heating system.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1220,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-fur-90-brochure-current.pdf'),
		'image_name'	=>	'American-Standard-05.webp'		
),
		
array ( 'post_title'	=>	'Platinum S8V2-C Furnace',
		'post_content' 	=>	'<span class="descriptionText">Use American Standard AccuLink™ Technology to ensure this furnace delivers you exactly the heat you need.</span>

<ul>
	<li><b>Communication technology:</b> The Platinum S8V2-C Gas Furnace is compatible with American Standard AccuLink™ Communicating System. With this communicating technology, homeowners can remotely adjust settings and program alerts, so you know your system is working at its best.</li>
	<li><b>Precise blower operation:</b> Exclusive Vortica™ II blower design delivers consistent, quiet heating in both the winter and summer months, giving you the temperature control you need all year long.</li>
	<li><b>Steady, warm air:</b> With the American Standard S8V2-C Gas Furnace, you\'ll be met with consistent, steady flows of warm air, so you don\'t need to worry about uneven indoor temperatures again.</li>
	<li><b>Built to last:</b> You won\'t have to worry about this furnace withstanding the test of time. Its cabinet and components are durable and built to deliver comfort for years to come.</li>
	<li><b>AFUE:</b> Up to 80%</li>
	<li><b>Heating Stages:</b> Two</li>
</ul>', 
		'post_excerpt'	=>	'Use American Standard AccuLink™ Technology to ensure this furnace delivers you exactly the heat you need.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1240,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-fur-80-brochure-11-09-22.pdf'),
		'image_name'	=>	'American-Standard-04.webp'		
),
		
array ( 'post_title'	=>	'Silver S8X1 Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">Heating you can trust from a furnace that’s built to a higher standard.</span>

<ul>
	<li><b>Steady, warm air:</b> With the Silver S8X1 gas furnace, you’ll be met with consistent, steady flows of warm air, so you don\'t need to worry about uneven indoor temperatures again.</li>
	<li><b>Flexible temperature control:</b> While this gas furnace does a great job heating, match it with a heat pump to enjoy energy saving benefits and a more complete temperature control experience. That means, the heat pump can act as the primary source of heat in milder temperatures, but when the weather gets cold, your system activates your furnace to deliver the heat you need.</li>
	<li><b>Energy efficient system:</b> This gas furnace may help you increase your overall cooling efficiency rating by two SEER points when installed as part of a complete system. Plus, save on energy while reducing greenhouse gas emissions with a system that surpasses government energy efficiency standards.</li>
	<li><b>Built to last:</b> You won’t have to worry about this furnace withstanding the test of time. Its cabinet and components are durable and built to deliver comfort for years to come.</li>
	<li><b>AFUE:</b> Up to 80%</li>
	<li><b>Heating Stages:</b> One</li>
</ul>', 
		'post_excerpt'	=>	'Increase energy savings and home comfort with this gas furnace.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1260,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-fur-80-brochure-11-09-22.pdf'),
		'image_name'	=>	'American-Standard-07.webp'		
),
		
array ( 'post_title'	=>	'Silver S8B1 Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">Stay warm during the winter months with this energy efficient gas furnace.</span>

<ul>
	<li><b>Precise blower operation:</b> Exclusive Vortica™ II blower design delivers consistent, quiet heating in both the winter and summer months, giving you the temperature control you need all year long.</li>
	<li><b>Flexible temperature control:</b> While this gas furnace does a great job heating, match it with a heat pump to enjoy energy saving benefits and a more complete temperature control experience. That means, the heat pump can act as the primary source of heat in milder temperatures, but when the weather gets cold, your system activates your furnace to deliver the heat you need.</li>
	<li><b>Steady, warm air:</b> With the American Standard S8B1 gas furnace, you’ll be met with consistent, steady flows of warm air, so you don\'t need to worry about uneven indoor temperatures again.</li>
	<li><b>Energy efficient system:</b> This gas furnace may help you save on energy usage while reducing greenhouse gas emissions because it\'s a system that surpasses government energy efficiency standards.</li>
	<li><b>AFUE:</b> Up to 80%</li>
	<li><b>Heating Stages:</b> One</li>
</ul>', 
		'post_excerpt'	=>	'Stay warm during the winter months with this energy efficient gas furnace.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1270,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-fur-80-brochure-11-09-22.pdf'),
		'image_name'	=>	'American-Standard-07.webp'		
),
		
		
		
		
	
	// Air Handlers
array ( 'post_title'	=>	'Platinum TAMX Air Handler',
		'post_content' 	=>	'<span class="descriptionText">Enjoy communicating technology with this durable and efficient air handler.</span>

<ul>
	<li><b>Communicating features:</b> This exceptional air handler is equipped with American Standard Link® communicating technology that can provide homeowners with more in-depth performance information about their HVAC system.</li>
	<li><b>Built to last:</b> The American Standard Link® air handler is built to last for years to come. Featuring fully enclosed insulation that eliminates the possibility of loose fibers, a sweat eliminating design, and an epoxy coated coil, this is an air handler you can trust.</li>
	<li><b>Variable speed technology:</b> Enjoy highly efficient and effective variable speed technology with this air handler. A variable speed Vortica™ blower motor works to circulate the air in exactly the way your home needs it. </li>
	<li><b>Diagnostic compatible:</b> This air handler works with American Standard Diagnostics, which allows your dealer (with your permission) to remotely diagnose potential issues with your HVAC system, plus provides perks like alert code notifications.</li>
	<li><b>Fan Stages:</b> Variable</li>
	<li><b>Communicating:</b> Yes</li>
</ul>', 
		'post_excerpt'	=>	'Enjoy communicating technology with this durable and efficient air handler.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1300,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-ah-standard-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-08.webp'		
),

array ( 'post_title'	=>	'Silver TEM8 Air Handler',
		'post_content' 	=>	'<span class="descriptionText">Find comfort with a variable-speed, communicating system at a lower price.</span>

<ul>
	<li><b>Reliably built:</b> The Silver TEM8’s unique cabinet helps it lose less energy. It also takes in less dust and moisture from the space around it, allowing it to work efficiently all year long.</li>
	<li><b>Communication technology:</b> With AccuLink™ technology or 24V connectivity, your Platinum TAM9 can communicate with key parts of your system. This means you can enjoy enhanced comfort and efficiency at home.</li>
	<li><b>Quiet comfort:</b> Discover the Vortica™ Fan Blower that improves airflow, runs quietly, and uses less energy.</li>
	<li><b>Durable all-aluminum coils:</b> An all-aluminum coil is more resistant to rust and corrosion than a standard copper coil. This coil extends the life of your air handler so you can enjoy comfort for many years.</li>
	<li><b>Fan Stages:</b> Variable</li>
	<li><b>Communicating:</b> Yes</li>
</ul>', 
		'post_excerpt'	=>	'Find comfort with a variable-speed, communicating system at a lower price.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1310,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-ah-standard-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-09.webp'		
),

array ( 'post_title'	=>	'Silver TEM6 Air Handler',
		'post_content' 	=>	'<span class="descriptionText">Get high performance and lasting comfort at a lower price.</span>

<ul>
	<li><b>Reliably built:</b> The Silver TEM6’s unique cabinet helps it lose less energy. It also takes in less dust and moisture from the space around it allowing it to work efficiently all year long.</li>
	<li><b>Adjustable for easy installation:</b> This air handler is a four-way convertible, perfectly fit for different installation scenarios.</li>
	<li><b>Quiet comfort:</b> Discover the Vortica™ air blower that improves airflow, runs quietly, and uses less energy.</li>
	<li><b>Durable all-aluminum coils:</b> An all-aluminum coil is more resistant to rust and corrosion than a standard copper coil. This coil extends the life of your air handler so you can enjoy comfort for many years.</li>
	<li><b>Fan Stages:</b> Variable</li>
</ul>', 
		'post_excerpt'	=>	'Get high performance and lasting comfort at a lower price.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1320,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-ah-standard-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-09.webp'		
),

array ( 'post_title'	=>	'Silver TEM4 Air Handler',
		'post_content' 	=>	'<span class="descriptionText">Help reduce your energy costs with a quiet, efficient air handler.</span>

<ul>
	<li><b>Reliably built:</b> The Silver TEM4\'s unique cabinet helps it lose less energy. It also takes in less dust and moisture from the space around it allowing it to work efficiently all year long.</li>
	<li><b>Quiet comfort:</b> Discover the Vortica™ air blower that improves airflow, runs quietly, and uses less energy.</li>
	<li><b>Durable all-aluminum coils:</b> An all-aluminum coil is more resistant to rust and corrosion than a standard copper coil. This coil extends the life of your air handler so you can enjoy comfort for many years.</li>
	<li><b>Fan Stages:</b> Multi-speed</li>
</ul>', 
		'post_excerpt'	=>	'Help reduce your energy costs with a quiet, efficient air handler.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1330,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-ah-standard-brochure-06-02-23.pdf'),
		'image_name'	=>	'American-Standard-09.webp'		
),
		
		
		
		
	
	// Packaged Units
array ( 'post_title'	=>	'Silver 13.4 Packaged Air Conditioner System',
		'post_content' 	=>	'<span class="descriptionText">Experience cool air just the way you want it with this packaged air conditioner.</span>

<ul>
	<li><b>Communicating features:</b> This exceptional air handler is equipped with American Standard Link® communicating technology that can provide homeowners with more in-depth performance information about their HVAC system.</li>
	<li><b>Built to last:</b> The American Standard Link® air handler is built to last for years to come. Featuring fully enclosed insulation that eliminates the possibility of loose fibers, a sweat eliminating design, and an epoxy coated coil, this is an air handler you can trust.</li>
	<li><b>Variable speed technology:</b> Enjoy highly efficient and effective variable speed technology with this air handler. A variable speed Vortica™ blower motor works to circulate the air in exactly the way your home needs it. </li>
	<li><b>Diagnostic compatible:</b> This air handler works with American Standard Diagnostics, which allows your dealer (with your permission) to remotely diagnose potential issues with your HVAC system, plus provides perks like alert code notifications.</li>
	<li><b>SEER2:</b> Up to 13.4</li>
	<li><b>Cooling Stages:</b> One</li>
	<li><b>Energy Savings:</b> Up to 29%</li>
</ul>', 
		'post_excerpt'	=>	'Experience cool air just the way you want it with this packaged air conditioner.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1400,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-packaged-brochure-12-09-22.pdf'),
		'image_name'	=>	'American-Standard-10.webp'		
),
		
array ( 'post_title'	=>	'Gold 15 Packaged Heat Pump System',
		'post_content' 	=>	'<span class="descriptionText">This system gives you your choice of energy sources and customized comfort.</span>

<ul>
	<li><b>Efficient performance:</b> The two-stage Duration™ compressor provides two stages of heating and cooling for a higher level of efficiency than most single-stage compressor units. It runs at 70% capacity most of the time, but steps up to the second stage on the most extreme days to provide efficiency in temperature control.</li>
	<li><b>Improved indoor air quality:</b> This packaged unit system is compatible with the American Standard AccuClean® Air Cleaner (horizontal applications only) which can optimize air in your home. This air cleaner helps remove allergens, bacteria, and certain viruses from the air you breathe in your home.</li>
	<li><b>Quiet performance:</b> As one of the quietest packaged units on the market, you can count on this system to deliver the temperature control you desire with a performance so quiet, you might not even know your system is turned on. </li>
	<li><b>Humidity control that helps:</b> With precise humidity control features, this packaged system helps remove unwanted humidity from the air in your home. Unregulated humidity levels indoors can result in problems for both your health and your home, which is why this system works to keep humidity at optimal levels.</li>
	<li><b>SEER2:</b> Up to 15.2</li>
	<li><b>HSPF2:</b> Up to 7.5</li>
	<li><b>Cooling Stages:</b> Two</li>
	<li><b>Energy Savings:</b> Up to 33%</li>
</ul>', 
		'post_excerpt'	=>	'This system gives you your choice of energy sources and customized comfort.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1410,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-packaged-brochure-12-09-22.pdf'),
		'image_name'	=>	'American-Standard-11.webp'		
),
		
array ( 'post_title'	=>	'Silver 13.4 Packaged Heat Pump System',
		'post_content' 	=>	'<span class="descriptionText">Enjoy a system that works smarter, not harder, to deliver year round comfort.</span>

<ul>
	<li><b>Efficient performance:</b> The two-stage Duration™ compressor provides two stages of heating and cooling for a higher level of efficiency than most single-stage compressor units. It runs at 70% capacity most of the time, but steps up to the second stage on the most extreme days to provide efficiency in temperature control.</li>
	<li><b>Improved indoor air quality:</b> This packaged unit system is compatible with the American Standard AccuClean® Air Cleaner (horizontal applications only) which can optimize air in your home. This air cleaner helps remove allergens, bacteria, and certain viruses from the air you breathe in your home.</li>
	<li><b>Quiet performance:</b> As one of the quietest packaged units on the market, you can count on this system to deliver the temperature control you desire with a performance so quiet, you might not even know your system is turned on. </li>
	<li><b>Humidity control that helps:</b> With precise humidity control features, this packaged system helps remove unwanted humidity from the air in your home. Unregulated humidity levels indoors can result in problems for both your health and your home, which is why this system works to keep humidity at optimal levels.</li>
	<li><b>SEER2:</b> Up to 13.4</li>
	<li><b>HSPF2:</b> Up to 7</li>
	<li><b>Cooling Stages:</b> Two</li>
	<li><b>Energy Savings:</b> Up to 29%</li>
</ul>', 
		'post_excerpt'	=>	'Enjoy a system that works smarter, not harder, to deliver year round comfort.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1420,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-packaged-brochure-12-09-22.pdf'),
		'image_name'	=>	'American-Standard-10.webp'		
),
		
array ( 'post_title'	=>	'Gold 15 Hybrid Comfort System',
		'post_content' 	=>	'<span class="descriptionText">Welcome higher quality heating and cooling to your home with this hybrid system.</span>

<ul>
	<li><b>Efficient performance:</b> The Duration™ compressor has been tested to provide efficient and long-lasting durability to ensure that you get the most out of your packaged heating and cooling system.</li>
	<li><b>Improved indoor air quality:</b> This packaged unit system is compatible with the American Standard AccuClean® Air Cleaner (horizontal applications only) which can optimize air in your home. This air cleaner helps remove allergens, bacteria, and certain viruses from the air you breathe in your home.</li>
	<li><b>Quiet performance:</b> As one of the quietest packaged units on the market, you can count on this system to deliver the temperature control you desire with a performance so quiet, you might not even know your system is turned on.</li>
	<li><b>Humidity control that helps:</b> With precise humidity control features, this packaged system helps remove unwanted humidity from the air in your home. Unregulated humidity levels indoors can result in problems for both your health and your home, which is why this system works to keep humidity at optimal levels.</li>
	<li><b>SEER2:</b> Up to 15.2</li>
	<li><b>HSPF2:</b> Up to 7.45</li>
	<li><b>AFUE:</b> Up to 81%</li>
	<li><b>Cooling Stages:</b> Two</li>
	<li><b>Energy Savings:</b> Up to 38%</li>
</ul>', 
		'post_excerpt'	=>	'Welcome higher quality heating and cooling to your home with this hybrid system.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1430,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-packaged-brochure-12-09-22.pdf'),
		'image_name'	=>	'American-Standard-11.webp'		
),
		
array ( 'post_title'	=>	'Gold 15 Gas/Electric Packaged System',
		'post_content' 	=>	'<span class="descriptionText">Stay warm in the winter and cool in the summer with a durable packaged system.</span>

<ul>
	<li><b>Efficient performance:</b> The two-stage Duration™ compressor provides two stages of heating and cooling for a higher level of efficiency than most single-stage compressor units. It runs at 70% capacity most of the time, but steps up to the second stage on the most extreme days to provide efficiency in temperature control.</li>
	<li><b>Improved indoor air quality:</b> This packaged unit system is compatible with the American Standard AccuClean® Air Cleaner (horizontal applications only) which can optimize air in your home. This air cleaner helps remove allergens, bacteria, and certain viruses from the air you breathe in your home.</li>
	<li><b>Quiet performance:</b> As one of the quietest packaged units on the market, you can count on this system to deliver the temperature control you desire with a performance so quiet, you might not even know your system is turned on.</li>
	<li><b>Humidity control that helps:</b> With precise humidity control features, this packaged system helps remove unwanted humidity from the air in your home. Unregulated humidity levels indoors can result in problems for both your health and your home, which is why this system works to keep humidity at optimal levels.</li>
	<li><b>SEER2:</b> Up to 15.2</li>
	<li><b>AFUE:</b> Up to 81%</li>
	<li><b>Cooling Stages:</b> Two</li>
	<li><b>Energy Savings:</b> Up to 33%</li>
</ul>', 
		'post_excerpt'	=>	'Stay warm in the winter and cool in the summer with a durable packaged system.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1440,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'packaged-units', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-packaged-brochure-12-09-22.pdf'),
		'image_name'	=>	'American-Standard-11.webp'		
),
		
		
		
		
	
	// Thermostats
array ( 'post_title'	=>	'AccuLink™ Platinum 850 Thermostat',
		'post_content' 	=>	'<span class="descriptionText">Never leave your home unprepared thanks to this thermostat that gives you a 5-day weather forecast and radar.</span>

<ul>
	<li><b>Alerts you can count on:</b> Not only does this thermostat tell you the temperature in your home, it also gives you a five day forecast and alerts for the weather outside. Plus, it gives you maintenance and filter service reminder alerts as well, so you can help ensure your product stays in peak performance condition.</li>
	<li><b>Easy installation:</b> Enjoy an easy installation after purchasing the AccuLink™ Platinum 850. This thermostat can quickly and efficiently be installed almost anywhere in your home, and once it\'s turned on, it has a one touch installation set up with six preset configurations for homeowners to choose from.</li>
	<li><b>Communication technology:</b> The AccuLink™ Platinum 850 is compatible with American Standard AccuLink™ Communicating System and AccuComfort™ Variable Speed Systems to give you control over your home comfort system. Enjoy communication technology that allows your home systems such as lights, security, HVAC, and more to all be controlled by the touch of a button.</li>
	<li><b>Humidity control:</b> Unregulated levels of humidity inside can lead to health problems for you and structural problems for your home. This thermostat works to keep optimal levels of humidity in your home and reduce these issues with a built-in humidity sensor that alerts the system when indoor humidity is too low or too high.</li>
	<li><b>Cooling Stages:</b> 2</li>
	<li><b>Heating Stages:</b> 5</li>
	<li><b>Smart Thermostat:</b> Yes</li>
	<li><b>Communicating:</b> Yes</li>
	<li><b>Z Wave Compatible:</b> Yes</li>
	<li><b>Screen:</b> 4.3" color touchscreen</li>
	<li><b>Diagnostics:</b> Yes</li>
	<li><b>Programmable:</b> Yes</li>
</ul>', 
		'post_excerpt'	=>	'Never leave your home unprepared thanks to this thermostat that gives you a 5-day weather forecast and radar.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1500,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'Thermostats', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-tstat-smart-brochure-01-02-23.pdf'),
		'image_name'	=>	'AS-Thermostat-01.webp'		
),
		
array ( 'post_title'	=>	'Gold 824 Thermostat',
		'post_content' 	=>	'<span class="descriptionText">Programing capabilities on this thermostat make temperature control a breeze.</span>

<ul>
	<li><b>Easy installation:</b> Enjoy an easy installation after purchasing the Gold 824. This thermostat can quickly and efficiently be installed almost anywhere in your home, and once it\'s turned on, setup only takes a few clicks. Not to mention, there\'s upgradable software to enhance your temperature control experience.</li>
	<li><b>Humidity control:</b> Unregulated levels of humidity inside can lead to health problems for you and structural problems for your home. This thermostat works to keep optimal levels of humidity in your home and reduce these issues with a built-in indoor relative humidity display that shows when indoor humidity is too low or too high. </li>
	<li><b>Alerts you can count on:</b> Not only does this thermostat tell you the temperature in your home, it also gives you a five day forecast and alerts for the weather outside. Plus, it gives you maintenance and filter service reminder alerts as well, so you can help ensure your product stays in peak performance condition.</li>
	<li><b>Scheduling capabilities:</b> Program your temperature schedule exactly how you want it with the scheduling capabilities on the Gold 824 thermostat. Capabilities include up to four daily heating and cooling periods.</li>
	<li><b>ENERGY STAR® Qualified:</b> Yes</li>
	<li><b>Cooling Stages:</b> 2</li>
	<li><b>Heating Stages:</b> 5</li>
	<li><b>Smart Thermostat:</b> Yes</li>
	<li><b>Z Wave Compatible:</b> Yes</li>
	<li><b>Screen:</b> 4.3" color touchscreen</li>
	<li><b>Diagnostics:</b> Yes</li>
	<li><b>Programmable:</b> Yes</li>
</ul>', 
		'post_excerpt'	=>	'Programing capabilities on this thermostat make temperature control a breeze.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1510,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'Thermostats', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-tstat-smart-brochure-01-02-23.pdf'),
		'image_name'	=>	'AS-Thermostat-01.webp'		
),
		
array ( 'post_title'	=>	'Silver 724 Thermostat',
		'post_content' 	=>	'<span class="descriptionText">Temperature control you can trust to make your home comfortable.</span>

<ul>
	<li><b>Easy installation:</b> Enjoy an easy installation after purchasing the Silver 724. This thermostat can quickly and efficiently be installed almost anywhere in your home, and once it\'s turned on, setup only takes a few clicks.</li>
	<li><b>Scheduling capabilities:</b> Program your temperature schedule exactly how you want it with the scheduling capabilities on the Silver 724 thermostat. Capabilities include temperature scheduling for seven days a week.</li>
	<li><b>Seamless remote controls:</b> Remote control capabilities on the Silver 724 thermostat allow you to make your home an oasis without even touching your thermostat. Simply use the remote control from anywhere in your home and enjoy the benefits of warm or cool air when you need it.</li>
	<li><b>Digital touchscreen:</b> Experience a large touchscreen on this thermostat that makes it easy to see and easy to use all year long. Controls are simple and work efficiently to get you the temperature you desire.</li>
	<li><b>Cooling Stages:</b> 2</li>
	<li><b>Heating Stages:</b> 4</li>
	<li><b>Smart Thermostat:</b> Yes</li>
	<li><b>Screen:</b> 4.3" color touchscreen</li>
	<li><b>Diagnostics:</b> Yes</li>
	<li><b>Programmable:</b> Yes</li>
</ul>', 
		'post_excerpt'	=>	'Temperature control you can trust to make your home comfortable.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1520,
		'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'Thermostats', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-tstat-smart-brochure-01-02-23.pdf'),
		'image_name'	=>	'AS-Thermostat-02.webp'		
),
		
		
		
		
		
		
		
		
			
);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>