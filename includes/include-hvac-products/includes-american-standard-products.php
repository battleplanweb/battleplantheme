<?php
/* Battle Plan Web Design - Add & Remove American Standard Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-american-standard-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );
endif;
*/




/* PRODUCT OVERVIEW
[product-overview type="american standard air conditioners"]

[product-overview type="american standard air handlers"]

[product-overview type="american standard heat pumps"]

[product-overview type="american standard furnaces"]

[product-overview type="american standard packaged units"]

[product-overview type="american standard thermostats"]
*/




add_action( 'wp_loaded', 'add_american_standard_products', 10 );
function add_american_standard_products() {

	$brand = "american-standard"; // lowercase
	$productImgAlt = "American Standard Heating & Cooling Product";



	//$removeImages = array('American-Standard-46.jpg', 'American-Standard-45.jpg', 'American-Standard-44.jpg', 'American-Standard-43.jpg', 'Nexia-Home-Intelligence.jpg', 'American-Standard-22.jpg', 'American-Standard-14.jpg', 'American-Standard-13.jpg', 'American-Standard-42.jpg', 'American-Standard-34.jpg', 'American-Standard-33.jpg', 'American-Standard-41.jpg', 'American-Standard-32.jpg', 'American-Standard-02.jpg', 'American-Standard-12.jpg', 'American-Standard-11.jpg', 'American-Standard-31.jpg', 'American-Standard-21.jpg', 'American-Standard-04.jpg', 'American-Standard-01.jpg');



	$removeProducts = array('platinum-20-variable-speed-air-conditioner', 'platinum-18-variable-speed-air-conditioner', 'platinum-17-variable-speed-air-conditioner', 'gold-16-two-stage-air-conditioner', 'silver-15-single-stage-air-conditioner', 'silver-14-single-stage-air-conditioner', 'platinum-20-variable-speed-heat-pump', 'platinum-18-variable-speed-heat-pump', 'platinum-17-variable-speed-heat-pump', 'gold-16-two-stage-heat-pump', 'silver-15-single-stage-heat-pump', 'silver-14-single-stage-heat-pump', 'gold-s9v2-gas-furnace', 'silver-s9x1-gas-furnace', 'silver-tem8-air-handler', 'silver-tem6-air-handler', 'silver-tem4-air-handler' );



	$addProducts = array (

	// Air Conditioners
	array ( 'post_title'	=>	'AccuComfort Variable Speed Platinum 20 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Be in control of your home temperature with a smart, efficient air conditioner.</span>

		<ul>
			<li><b>Precise temperature control:</b> With AccuComfort™ variable speed technology, the Platinum 20 can gradually adjust its speed at a broader range to create a consistent flow of cool, comfortable air. This way, your system can continuously match the temperature you want at home.</li>
			<li><b>Reliable durability:</b> Rely on your air conditioner for years to come. The Platinum 20 is built for durability with quality materials, innovative features, and a sturdy construction.</li>
			<li><b>Optimize indoor air quality:</b> If you add an AccuClean® Air Cleaner, your air conditioner can filter out more dust and harmful irritants from the air so you can breathe easier at home.</li>
			<li><b>Communication technology:</b> With Link communicating technology, your Platinum 20 controls communication between the thermostat, indoor unit and outdoor unit. This way, it can maximize efficiency and home comfort.</li>
			<li><b>SEER2:</b> Up to 24</li>
			<li><b>Sound:</b> 64-76 dBA</li>
			<li><b>Cooling Stages:</b> Variable</li>
			<li><b>Fan Stages:</b> Variable</li>
			<li><b>Energy Savings:</b> Up to 55%</li>
		</ul>',
			'post_excerpt'	=>	'Be in control of your home temperature with a smart, efficient air conditioner.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1000,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://proformaprostores.com/Resources/Proforma/ProformaQ55/Trane/Downloads/TT_10-1112_SV.pdf'),
			'image_name'	=>	'American-Standard-01.webp'
	),




	array ( 'post_title'	=>	'AccuComfort Variable Speed Platinum 18 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Cool your home with an air conditioner that\'s both quiet and efficient.</span>

		<ul>
			<li><b>Precise temperature control:</b> With AccuComfort™ variable speed technology, the Platinum 18 can gradually adjust its speed at a broader range to create a consistent flow of cool, comfortable air. This way, your system can continuously match the temperature you want at home. </li>
			<li><b>Reliable durability:</b> Rely on your air conditioner for years to come. The Platinum 18 is built for durability with quality materials, innovative features, and a sturdy construction.</li>
			<li><b>Optimize indoor air quality:</b> If you add an AccuClean® Air Cleaner, your air conditioner can filter out more dust and harmful irritants from the air so you can breathe easier at home.</li>
			<li><b>Communication technology:</b> With Link communicating technology, your Platinum 18 controls communication between the thermostat, indoor unit and outdoor unit. This way, it can maximize efficiency and home comfort.</li>
			<li><b>SEER2:</b> Up to 18.1</li>
			<li><b>Sound:</b> 67-76 dBA</li>
			<li><b>Cooling Stages:</b> Variable</li>
		</ul>',
			'post_excerpt'	=>	'Cool your home with an air conditioner that\'s both quiet and efficient.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1010,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://proformaprostores.com/Resources/Proforma/ProformaQ55/Trane/Downloads/TT_10-1112_SV.pdf'),
			'image_name'	=>	'American-Standard-01.webp'
	),





	array ( 'post_title'	=>	'Gold 17 Multi-Speed Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Stay comfortably cool no matter how hot the weather gets.</span>

		<ul>
			<li><b>Engineered with ComfortSeek™ technology:</b> ComfortSeek™ dynamically adjusts compressor speed in response to outdoor temperature changes – working harder when you need it and conserving energy when you don\'t – ensuring optimal heating and cooling capacity even in extreme conditions. Its patent-pending design maximizes comfort, enhances energy efficiency, and reduces utility bills, setting a new standard in home climate control.</li>
			<li><b>Higher performance at a lower cost:</b> Achieve long-term energy savings with a SEER2 cooling rating of up to 17.1 and EER2 of up to 12.5 for performance in extreme heat. This air conditioner is an energy-efficient bridge between a traditional two-stage system and a premium variable-speed system.</li>
			<li><b>Multi-speed technology = enhanced comfort and reliability:</b> The Gold 17 Multi-Speed Air Conditioner is more efficient than traditional two-stage units, with inverter-driven technology that adjusts compressor speeds to minimize energy usage and maximize comfort. It also offers enhanced humidity control and built-in reliability, thanks to sophisticated compressor protections and fewer failure-prone components.</li>
			<li><b>Universal 24V compatibility to save you money:</b> 24V system compatibility means you don\'t have to replace your existing furnace with a higher-cost communicating unit, and you can keep any brand two-stage thermostat.</li>
			<li><b>SEER2:</b> Up to 17.1</li>
			<li><b>Sound:</b> 72-74 dBA</li>
			<li><b>Cooling Stages:</b> Multiple</li>
			<li><b>Fan Stages:</b> Multiple</li>
		</ul>',
			'post_excerpt'	=>	'Stay comfortably cool no matter how hot the weather gets.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1020,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://proformaprostores.com/Resources/Proforma/ProformaQ55/Trane/Downloads/TT_10-1112_SV.pdf'),
			'image_name'	=>	'American-Standard-01.webp'
	),




	array ( 'post_title'	=>	'Silver 16 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Keep your home energy-efficient no matter how hot the weather gets.</span>

		<ul>
			<li><b>Comfort plus performance:</b> This air conditioner keeps your home comfortable while providing energy-efficient performance that keeps utility bills in check.</li>
			<li><b>Built for durability:</b> Year after year, you can count on the Silver 16 to beat the heat thanks to high-quality materials, sturdy construction and innovative features.</li>
			<li><b>Breathe easier:</b> For a cleaner, healthier indoor environment, add an AccuClean® Air Cleaner to your air conditioning system to filter out more dust and harmful airborne irritants.</li>
			<li><b>The greener option:</b> American Standard air conditioners keep your home cool and comfortable with next-generation refrigerants—ozone-safe and environmentally friendly.</li>
			<li><b>SEER2:</b> Up to 17</li>
			<li><b>Sound:</b> 71-73 dBA</li>
			<li><b>Coling Stages:</b> One</li>
		</ul>',
			'post_excerpt'	=>	'Keep your home energy-efficient no matter how hot the weather gets.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1030,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://proformaprostores.com/Resources/Proforma/ProformaQ55/Trane/Downloads/TT_10-1112_SV.pdf'),
			'image_name'	=>	'American-Standard-01.webp'
	),





	array ( 'post_title'	=>	'Silver 14 Single-Stage Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Enjoy a great blend of reliable cooling, energy efficiency and value.</span>

		<ul>
			<li><b>Efficient at a great value:</b> Help lower your energy costs with this 14.8 SEER2 air conditioner that balances energy efficiency and cooling strength.</li>
			<li><b>Reliable durability:</b> Rely on your air conditioner for years to come. The Silver 14 is built for durability with quality materials, innovative features, and a sturdy construction.</li>
			<li><b>Optimize indoor air quality:</b> If you add an AccuClean® Air Cleaner, your air conditioner can filter out more dust and harmful irritants from the air so you can breathe easier.</li>
			<li><b>Environmentally friendly:</b> Take care of your environment. American Standard air conditioners cool your home with a refrigerant that\'s ozone-safe.</li>
			<li><b>SEER2:</b> Up to 14.8</li>
			<li><b>Sound:</b> 72-73 dBA</li>
			<li><b>Cooling Stages:</b> One</li>
			<li><b>ENERGY STAR® Qualified:</b> Yes</li>
			<li><b>Energy Savings:</b> Up to 38%</li>
		</ul>',
			'post_excerpt'	=>	'Enjoy a great blend of reliable cooling, energy efficiency and value.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1040,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/asa-air-conditioner-brochure-06-02-23.pdf'),
			'image_name'	=>	'American-Standard-01.webp'
	),













		// Heat Pumps
	array ( 'post_title'	=>	'AccuComfort Variable Speed Platinum 20 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Experience home comfort efficiency at a whole new level with state-of-the-art heating and cooling technology.</span>

		<ul>
			<li><b>Comfort and quality meets efficiency:</b> Built with quality materials and innovative features, the AccuComfort™ Platinum 20 Heat Pump is one of the industry\'s most efficient systems on the market, with ratings up to 20.5 SEER2 and 8.7 HSPF.</li>
			<li><b>Multi-stage heating and cooling technology:</b> State-of-the-art, multi-stage heating and cooling system that consistently adjusts to run at a more efficient speed to maintain optimal levels of comfort.</li>
			<li><b>Quiet, reliable AccuComfort™ technology:</b> Enjoy calm comfort through variable speed heating and cooling, designed to meet your unique needs. Consistent with ½ degree in 1/10th of 1% increments, so you get the comfort you set and the AccuComfort™ technology does the rest.</li>
			<li><b>Clean Air technology:</b> The lower compressor modulation and fan speeds yield amazingly low sound levels and max out the benefits of AccuClean® Air Cleaner technology, giving you the advantage of optimized air quality.</li>
			<li><b>The hybrid system advantage:</b> Pair your heat pump with a gas furnace to enjoy the benefits of a hybrid system. Once your heat pump reaches its heating capacity, your gas furnace steps in to keep you comfortable. Together, they offer you reliable comfort that could lower your energy costs.</li>
			<li><b>SEER2:</b> Up to 22.4/li>
			<li><b>HSPF2:</b> Up to 10.5</li>
			<li><b>Sound:</b> 54-74 dBA</li>
			<li><b>Fan Stages:</b> Variable</li>
		</ul>',
			'post_excerpt'	=>	'Experience home comfort efficiency at a whole new level with state-of-the-art heating and cooling technology.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1100,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.americanstandardair.com/assets/product-brochures/heat-pumps/TT_10-1113-R-45_AMSD_Heat_Pump-Brochure_SV.pdf'),
			'image_name'	=>	'American-Standard-02.webp'
	),





	array ( 'post_title'	=>	'AccuComfort Variable Speed Platinum 18 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Get year-round comfort with a variety of heating and cooling speeds to meet your temperature needs.</span>

		<ul>
			<li><b>Variable speeds, maximum comfort:</b> AccuComfort™ technology allows the variable-speed system to consistently adjust to run at a more efficient speed to maintain your personal level of home comfort.</li>
			<li><b>Top-ranked, highly efficient:</b> This heat pump is ranked one of the most efficient on the market as it has top SEER2 and HSPF2 ratings and automatically adjusts to keep you comfortable.</li>
			<li><b>Quiet comfort:</b> Quiet system operation compared to competitors for dependable comfort that works smarter for ideal home enjoyment.</li>
			<li><b>A system you can count on:</b> Built with quality materials, innovative features, durable construction and backed by our independent American Standard Heating & Air Conditioning Dealers to ensure you get dependable comfort for years to come.</li>
			<li><b>The hybrid system advantage:</b> Pair your heat pump with a gas furnace to enjoy the benefits of a hybrid system. Once your system reaches its heating capacity, your gas furnace steps in to keep you comfortable. Together, they offer you reliable comfort that could lower your energy costs.</li>
			<li><b>SEER2:</b> Up to 18.1</li>
			<li><b>HSPF2:</b> Up to 8.5</li>
			<li><b>Sound:</b> 54-76 dBA</li>
			<li><b>Fan Stages:</b> Variable</li>
		</ul>',
			'post_excerpt'	=>	'Get year-round comfort with a variety of heating and cooling speeds to meet your temperature needs.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1110,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.americanstandardair.com/assets/product-brochures/heat-pumps/TT_10-1113-R-45_AMSD_Heat_Pump-Brochure_SV.pdf'),
			'image_name'	=>	'American-Standard-01.webp'
	),





	array ( 'post_title'	=>	'Gold 17 Multi-Speed Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">In-home comfort and high efficiency.</span>

		<ul>
			<li><b>Engineered with ComfortSeek™ technology:</b> ComfortSeek™ dynamically adjusts compressor speed in response to outdoor temperature changes – working harder when you need it and conserving energy when you don\'t – ensuring optimal heating and cooling capacity even in extreme conditions. Its patent-pending design maximizes comfort, enhances energy efficiency, and reduces utility bills, setting a new standard in home climate control.</li>
			<li><b>Higher performance at a lower cost:</b> The numbers add up to long-term energy savings. With a SEER2 cooling rating of up to 17.1, EER2 of up to 12.5 for performance in extreme heat, and a high HSPF2 heating rating of up to 11, this heat pump is an energy-efficient bridge between a two-stage system and a premium variable-speed system.</li>
			<li><b>Multi-speed technology delivers increased comfort and reliability:</b> The inverter-driven Gold 17 Multi-Speed Heat Pump adjusts compressor speed for maximum energy efficiency and comfort while providing enhanced humidity control. Its sophisticated design, free of failure-prone components, ensures built-in reliability.</li>
			<li><b>Efficient heat pump for cold climates:</b> This heat pump is tested to provide a 70% heating capacity ratio at 5° F and deliver 100% heating capacity down to 27° F. If your winters are long and frigid, you may want to pair it with a gas furnace for a dual-fuel heating system.</li>
			<li><b>SEER2:</b> Up to 17.1</li>
			<li><b>HSPF2:</b> Up to 11</li>
			<li><b>Sound:</b> 63-75 dBA</li>
			<li><b>Fan Stages:</b> Multiple</li>
		</ul>',
			'post_excerpt'	=>	'In-home comfort and high efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1120,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://www.americanstandardair.com/assets/product-brochures/heat-pumps/TT_10-1113-R-45_AMSD_Heat_Pump-Brochure_SV.pdf'),
			'image_name'	=>	'American-Standard-01.webp'
	),





	array ( 'post_title'	=>	'Silver 16 Heat Pump',
			'post_content' =>	'<span class="descriptionText">Experience incredible in-home comfort with a highly efficient heat pump that\'s environmentally friendly and quiet.</span>

		<ul>
			<li><b>Efficiency plus value:</b> Our best value, the Silver 16 Heat Pump can help save you on your heating and cooling energy usage with a SEER2 up to 17 and HSPF2 up to 8.5.</li>
			<li><b>Environmentally friendly comfort:</b> The Silver 16 Heat Pump not only exceeds government efficiency standards, it cools and heats with the newest ozone-safe refrigerant that helps reduce greenhouse gas emissions. </li>
			<li><b>You can count on what\'s inside:</b> The Silver 16 Heat Pump\'s Duration™ compressor and Spine Fin™ coil ensure that you and your family can enjoy heating and cooling that\'s affordable, efficient, and reliable.</li>
			<li><b>Compatible with hybrid systems:</b> The Silver 16 Heat Pump can be paired with a gas furnace to create a hybrid system. When your system detects that it\'s more efficient to use the furnace, it automatically steps in. The result? Reliable comfort that helps you manage your energy costs too.</li>
			<li><b>SEER2:</b> Up to 17</li>
			<li><b>HSPF2:</b> Up to 8.5</li>
			<li><b>Sound:</b> 71-75 dBA</li>
			<li><b>Fan Stages:</b> One</li>
		</ul>',
			'post_excerpt'	=>	'Experience incredible in-home comfort with a highly efficient heat pump that\'s environmentally friendly and quiet.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1130,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://www.americanstandardair.com/assets/product-brochures/heat-pumps/TT_10-1113-R-45_AMSD_Heat_Pump-Brochure_SV.pdf'),
			'image_name'	=>	'American-Standard-01.webp'
	),






	array ( 'post_title'	=>	'Silver 14 Heat Pump',
			'post_content' =>	'<span class="descriptionText">Experience incredible in-home comfort with a highly efficient heat pump that\'s environmentally friendly and quiet.</span>

		<ul>
			<li><b>Efficiency that helps you save:</b> The Silver 14 Heat Pump has a SEER2 rating of up to 14.8, making it a very efficient system that can provide you comfort year after year and help you save on your energy bill.</li>
			<li><b>Quiet, reliable comfort:</b> Quiet system operation allows for dependable and distraction-free home comfort that allows you to enjoy your surroundings.</li>
			<li><b>Environmentally friendly, great value:</b>The Silver 14 Heat Pump helps you save up to 47 percent on your heating and cooling energy usage while helping to reduce greenhouse gas emissions.</li>
			<li><b>A system you can count on:</b> The Silver 14 Heat Pump offers affordable heating and cooling that provides efficient and reliable cooling, thanks to its Spine Fin™ coil and Duration™ compressor.</li>
			<li><b>The hybrid system advantage:</b> Pair your heat pump with a gas furnace to enjoy the benefits of a hybrid system. Once your heat pump reaches its heating capacity, your gas furnace steps in to keep you comfortable. Together, they offer you reliable comfort that could lower your energy costs.</li>
			<li><b>SEER2:</b> Up to 14.3</li>
			<li><b>HSPF2:</b> Up to 7.8</li>
			<li><b>Sound:</b> 70.72 dBA</li>
			<li><b>Fan Stages:</b> One</li>
			<li><b>Energy Savings:</b> Up to 38%</li>
		</ul>',
			'post_excerpt'	=>	'Experience incredible in-home comfort with a highly efficient heat pump that\'s environmentally friendly and quiet.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1140,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://www.americanstandardair.com/assets/product-brochures/heat-pumps/TT_10-1113-R-45_AMSD_Heat_Pump-Brochure_SV.pdf'),
			'image_name'	=>	'American-Standard-01.webp'
	),







		// Furnaces
	array ( 'post_title'	=>	'Platinum S9V2-VS Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">Stay warm and comfortable inside with a gas furnace featuring fully modulating heating.</span>

		<ul>
			<li><b>Steady, warm air:</b> With the Platinum S9V2-VS gas furnace, you\'ll be met with consistent, steady flows of warm air, so you don\'t need to worry about uneven indoor temperatures again.</li>
			<li><b>Flexible temperature control:</b> While this gas furnace does a great job heating, match it with a heat pump to enjoy energy saving benefits and a more complete temperature control experience. That means, the heat pump can act as the primary source of heat in milder temperatures, but when the weather gets cold, your system activates your furnace to deliver the heat you need.</li>
			<li><b>Improved indoor air quality:</b> Combine the Platinum S9V2-VS Gas Furnace with the American Standard AccuClean® Air Cleaner for more comfortable air in your home. This air cleaner helps remove allergens, bacteria, and viruses from the air you breathe in your home.</li>
			<li><b>Built to last:</b> You won\'t have to worry about this furnace withstanding the test of time. Its cabinet and components are durable and built to deliver comfort for years to come.</li>
			<li><b>AFUE:</b> Up to 97%</li>
			<li><b>Heating Stages:</b> Two</li>
			<li><b>ENERGY STAR® Qualified:</b> Yes</li>
		</ul>',
			'post_excerpt'	=>	'Stay warm and comfortable inside with a gas furnace featuring fully modulating heating.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1210,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/TT_10-1173-23_AS_90-95_Gas%20Furnace-Brochure.pdf'),
			'image_name'	=>	'American-Standard-04.webp'
	),





	array ( 'post_title'	=>	'Gold S9X2 Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">Enjoy warm air throughout your home with this two stage heating system.</span>

		<ul>
			<li><b>Built to last:</b> You won\'t have to worry about this furnace withstanding the test of time. Its cabinet and components are durable and built to deliver comfort for years to come.</li>
			<li><b>Flexible temperature control:</b> While this gas furnace does a great job heating, match it with a heat pump to enjoy energy saving benefits and a more complete temperature control experience. That means, the heat pump can act as the primary source of heat in milder temperatures, but when the weather gets cold, your system activates your furnace to deliver the heat you need.</li>
			<li><b>Steady, warm air:</b> With the Gold S9V2 gas furnace, you\'ll be met with consistent, steady flows of warm air, so you don\'t need to worry about uneven indoor temperatures again.</li>
			<li><b>Versatile system:</b> With two stages of gas heat, three way poise (U, HL, HR) plus dedicated DF, and 24v IFC that\'s compatible with most thermostats, this furnace is versatile in more ways than one.</li>
			<li><b>AFUE:</b> Up to 96%</li>
			<li><b>Heating Stages:</b> Two</li>
			<li><b>ENERGY STAR® Qualified:</b> Yes</li>
		</ul>',
			'post_excerpt'	=>	'Enjoy warm air throughout your home with this two stage heating system.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1250,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/TT_10-1173-23_AS_90-95_Gas%20Furnace-Brochure.pdf'),
			'image_name'	=>	'American-Standard-04.webp'
	),






	array ( 'post_title'	=>	'Silver S9X1 Gas Furnace',
			'post_content' 	=>	'<span class="descriptionText">Feel warm and cozy inside all year long with this single-stage heating system.</span>

		<ul>
			<li><b>Precise blower operation:</b> Exclusive Vortica™ II blower design delivers consistent, quiet heating in both the winter and summer months, giving you the temperature control you need all year long.</li>
			<li><b>Versatile system:</b> Matching with single and two stage air conditioner and heat pump models, and including self diagnosing integrated furnace control (IFC) this furnace is versatile in more ways than one.</li>
			<li><b>Built to last:</b> You won\'t have to worry about this furnace withstanding the test of time. Its cabinet and components are durable and built to deliver comfort for years to come.</li>
			<li><b>Flexible temperature control:</b> While this gas furnace does a great job heating, match it with a heat pump to enjoy energy saving benefits and a more complete temperature control experience. That means, the heat pump can act as the primary source of heat in milder temperatures, but when the weather gets cold, your system activates your furnace to deliver the heat you need.</li>
			<li><b>AFUE:</b> Up to 96%</li>
			<li><b>Heating Stages:</b> One</li>
		</ul>',
			'post_excerpt'	=>	'Feel warm and cozy inside all year long with this single-stage heating system.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1255,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/TT_10-1173-23_AS_90-95_Gas%20Furnace-Brochure.pdf'),
			'image_name'	=>	'American-Standard-05.webp'
	),






		// Air Handlers
	array ( 'post_title'	=>	'Platinum 5TEMC Air Handler',
			'post_content' 	=>	'<span class="descriptionText">Enjoy communicating technology with this durable and efficient air handler.</span>

		<ul>
			<li><b>Reliably built:</b> The Platinum 5TEMC\'s unique cabinet helps it lose less energy. It also takes in less dust and moisture from the space around it, allowing it to work efficiently all year long.</li>
			<li><b>Communication technology:</b> With AccuLink™ technology or 24V connectivity, your Platinum 5TEMC can communicate with key parts of your system. This means you can enjoy enhanced comfort and efficiency at home.</li>
			<li><b>Quiet comfort:</b> Discover the Vortica™ Fan Blower that improves airflow, runs quietly, and uses less energy.</li>
			<li><b>Durable all-aluminum coils:</b> An all-aluminum coil is more resistant to rust and corrosion than a standard copper coil. This coil extends the life of your air handler so you can enjoy comfort for many years.</li>
			<li><b>Fan Stages:</b> Variable</li>
			<li><b>Communicating:</b> Yes</li>
		</ul>',
			'post_excerpt'	=>	'Enjoy communicating technology with this durable and efficient air handler.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1310,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/air-handlers/TT_15-4142-R-25E_AMSD_Air-Handler-ConsumerBrochure_digital.pdf'),
			'image_name'	=>	'American-Standard-09.webp'
	),





	array ( 'post_title'	=>	'Gold 5TEM6 Air Handler',
			'post_content' 	=>	'<span class="descriptionText">Find comfort with a variable-speed, communicating system at a lower price.</span>

		<ul>
			<li><b>Reliably built:</b> The Silver TEM8\'s unique cabinet helps it lose less energy. It also takes in less dust and moisture from the space around it, allowing it to work efficiently all year long.</li>
			<li><b>Adjustable for easy installation:</b> This air handler is a four-way convertible, perfectly fit for different installation scenarios.</li>
			<li><b>Quiet comfort:</b> Discover the Vortica™ Fan Blower that improves airflow, runs quietly, and uses less energy.</li>
			<li><b>Durable all-aluminum coils:</b> An all-aluminum coil is more resistant to rust and corrosion than a standard copper coil. This coil extends the life of your air handler so you can enjoy comfort for many years.</li>
			<li><b>Fan Stages:</b> Variable</li>
		</ul>',
			'post_excerpt'	=>	'Find comfort with a variable-speed, communicating system at a lower price.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1320,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/air-handlers/TT_15-4142-R-25E_AMSD_Air-Handler-ConsumerBrochure_digital.pdf'),
			'image_name'	=>	'American-Standard-09.webp'
	),







	array ( 'post_title'	=>	'Silver TEM4 Air Handler',
			'post_content' 	=>	'<span class="descriptionText">Help reduce your energy costs with a quiet, efficient air handler.</span>

		<ul>
			<li><b>Reliably built:</b> The Silver TEM4\'s unique cabinet helps it lose less energy. It also takes in less dust and moisture from the space around it allowing it to work efficiently all year long.</li>
			<li><b>Quiet comfort:</b> Discover the Vortica™ air blower that improves airflow, runs quietly, and uses less energy.</li>
			<li><b>Durable all-aluminum coils:</b> An all-aluminum coil is more resistant to rust and corrosion than a standard copper coil. This coil extends the life of your air handler so you can enjoy comfort for many years.</li>
			<li><b>Fan Stages:</b> Constant Torque</li>
		</ul>',
			'post_excerpt'	=>	'Help reduce your energy costs with a quiet, efficient air handler.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1330,
			'tax_input'		=>  array('product-brand'=>'american-standard', 'product-type'=>'air-handlers', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://americanstandardair.com/assets/product-brochures/air-handlers/TT_15-4142-R-25E_AMSD_Air-Handler-ConsumerBrochure_digital.pdf'),
			'image_name'	=>	'American-Standard-09.webp'
	),






	// Packaged Units




	// Thermostats


	);

	// NOT require_once: the uploader is procedural and runs against this function's locals, so every
	// brand that imports in the same request has to include it again. require_once would make the
	// second brand a silent no-op.
	require get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>