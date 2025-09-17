<?php
/* Battle Plan Web Design - Add & Remove Amana Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-tempstar-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_tempstar_products', 10 );
function add_tempstar_products() {

	$brand = "tempstar"; // lowercase
	$productImgAlt = "Tempstar Heating & Cooling Product"; 


	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


	$addProducts = array (		
	
	// Air Conditioners
array ( 'post_title'	=>	'Performance 17 Central Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Who says you can\'t have premium comfort on a bargain budget? This cost-efficient air conditioner beats the summer heat with longer cooling cycles to keep temperatures even throughout the house and summer humidity at a minimum. And when temperatures soar, it kicks into high gear to keep you from working up a sweat.</span>
<ul>
	<li>Up to 17 SEER</li>
	<li>Two-stage compressor for improved temperature and summer humidity control</li>
	<li>Two-speed fan works with compressor for better levels of quiet, efficient operation</li>
	<li>Durably built with tight wire grille and protective corner posts to withstand bad weather and debris</li>
	<li>Designed for corrosion resistance and lasting performance</li>
        <li>Quiet performance (as low as 71 decibels)</li>
        <li>10-Year Parts Limited Warranty</li>
</ul>', 
		'post_excerpt'	=>	'Sit back and take in the quiet comfort of our affordable two-stage air conditioner. You\'ll enjoy smooth, consistent comfort when it\'s hot outside.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1000,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'AC-01.webp'		
),
		
array ( 'post_title'	=>	'Performance 16 Central Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Now you can keep cool and comfortable in the warm months with our high efficiency air conditioner with a single-stage compressor. It delivers reliable cooling comfort and is ENERGY STAR® qualified so you can enjoy money-saving, efficient comfort.</span>
<ul>
	<li>Up to 16 SEER</li>
	<li>Single-stage compressor operation</li>
	<li>Single-speed fan motor</li>
	<li>Durably built with tight wire grille and protective corner posts to withstand bad weather and debris</li>
	<li>Durable design for corrosion resistance and lasting performance</li>
        <li>Quiet performance (as low as 76 decibels)</li>
        <li>10-Year Parts Limited Warranty</li>
</ul>', 
		'post_excerpt'	=>	'Our budget-friendly air conditioner features a single-stage scroll compressor that still delivers high-efficiency cooling performance so you can beat the heat during the summer.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1100,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'AC-01.webp'		
),	
		
array ( 'post_title'	=>	'Performance 14 Central Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Keep your cool in the summer with this single-stage air conditioner. It\'s designed to meet your need for a budget-friendly design that you can count on for reliability and durability for years to come.</span>
<ul>
	<li>Up to 14 SEER</li>
	<li>Two-stage compressor for improved temperature and summer humidity control</li>
	<li>Two-speed fan works with compressor for better levels of quiet, efficient operation</li>
	<li>Durably built with tight wire grille and protective corner posts to withstand bad weather and debris</li>
	<li>Designed for corrosion resistance and lasting performance</li>
        <li>Quiet performance (as low as 75 decibels)</li>
        <li>10-Year Parts Limited Warranty</li>
</ul>', 
		'post_excerpt'	=>	'Count on this model for reliable performance year after year. Its single-stage scroll compressor design can deliver the cool and help keep the humidity in check.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1200,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'AC-01.webp'		
),		
		
		
		
		
	
	// Heat Pumps		
array ( 'post_title'	=>	'Performance 16 Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">Who says you can\'t have premium comfort on a bargain budget? This cost-efficient heat pump beats the summer heat and winter\'s icy chill with longer cooling and heating cycles. It keeps temperatures even throughout the house and summer humidity at a minimum. And when outdoor conditions are more extreme, it kicks into high gear to keep you cozy all year long.</span>
<ul>
	<li>Up to 17.5 SEER</li>
        <li>Quiet performance (as low as 70 decibels)</li>
        <li>Dual fuel capable with a compatible gas furnace and thermostat for energy-saving heating</li>
        <li>Durably built to withstand bad weather and debris</li>
        <li>Designed for corrosion resistance and lasting performance</li>
        <li>10-Year Parts Limited Warranty</li>
</ul>
<span class="disclaimerText"></span>', 
		'post_excerpt'	=>	'Sit back and take in the comfort all year long with our most affordable two-stage heat pump. You\'ll enjoy smooth, consistent temperatures and energy-saving efficiency.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2000,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'AC-01.webp'		
),	
		
array ( 'post_title'	=>	'Performance 15 Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">You’ll find the reliable comfort you want with the efficient, budget-friendly heat pump that gets a 15 SEER cooling rating. It features a single-stage scroll compressor for quiet electric heating and cooling. Use it year round in warmer climates, or pair it with a compatible gas furnace and thermostat to gain dual fuel heating efficiency in colder climates.</span>
<ul>
	<li>Up to 16 SEER</li>
        <li>Quiet performance (as low as 69 decibels)</li>
        <li>Single-stage compressor operation</li>
        <li>Dual fuel capable with a compatible gas furnace and thermostat for energy-saving heating</li>
        <li>Durably built to withstand bad weather and debris</li>
        <li>Designed for corrosion resistance and lasting performance</li>
        <li>10-Year Parts Limited Warranty</li>
</ul>
<span class="disclaimerText"></span>', 
		'post_excerpt'	=>	'Stay comfortable year-round with this economical ENERGY STAR qualified heat pump.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2100,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'AC-01.webp'		
),			
		
array ( 'post_title'	=>	'Performance 14 Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">Now you can get the reliable comfort you want with the budget-friendly heat pump that gets a 14 SEER cooling rating. It features a single-stage scroll compressor for quiet electric heating and cooling. Use it year round in warmer climates, or pair it with a compatible gas furnace and thermostat to gain dual fuel heating efficiency in colder climates.</span>
<ul>
	<li>Up to 14 SEER</li>
        <li>Quiet performance (as low as 69 decibels)</li>
        <li>Dual fuel capable with a compatible gas furnace and thermostat for energy-saving heating</li>
        <li>Durably built to withstand bad weather and debris</li>
        <li>Designed for corrosion resistance and lasting performance</li>
        <li>10-Year Parts Limited Warranty</li>
</ul>
<span class="disclaimerText"></span>', 
		'post_excerpt'	=>	'Enjoy dependable comfort with the Performance 14 heat pump. It\'s even dual fuel heating capable when paired with a gas furnace and the right thermostat.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2200,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'AC-01.webp'		
),		
		
		
		
		
	
	// Packaged Units			
array ( 'post_title'	=>	'QuietComfort® 14 Packaged Gas Furnace/Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Now you can enjoy optimum comfort with our efficient packaged gas furnace and electric air conditioner product that delivers reliable temperatures every season and reduced humidity during the hot months. It is ENERGY STAR® qualified, which means efficient year-round comfort.</span>
<ul>
	<li>Up to 14 SEER</li>
        <li>Quiet performance (as low as 73 decibels)</li>
        <li>Multi-speed blower motor for improved temperature and humidity control</li>
        <li>Durably built to withstand weather and debris</li>
        <li>Designed for corrosion resistance and lasting performance</li>
        <li>3-Year No Hassle Replacement™ Limited Warranty</li>
        <li>10-Year Parts Limited Warranty</li>
        <li>Lifetime Heat Exchanger Limited Warranty</li>
</ul>
<span class="disclaimerText"></span>', 
		'post_excerpt'	=>	'Improve home comfort with our packaged heating and cooling combination that features single-stage simplicity and quiet performance.',
		'post_type'     =>	'products',
		'menu_order'  	=>  3000,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'pacakged-units', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'Furnace-01.webp'		
),				
		
		
		
		
	
	// Furnaces		
array ( 'post_title'	=>	'Performance 80 Gas Furnace',
	   	'post_content' 	=>	'<span class="descriptionText">As part of a complete, year-round comfort system, this single-speed furnace can be setup to deliver optimized airflow to your living spaces with our discrete tapped blower.</span>
<ul>
	<li>80% AFUE</li>
        <li>Quiet performance</li>
	<li>Multi-speed blower motor and single-stage gas valve also provide even levels of temperature control and comfort</li>
	<li>Dual fuel capable with a compatible heat pump and thermostat for energy-saving heating performance</li>
	<li>Air purifier and humidifier compatible</li>
	<li>10-Year Parts Limited Warranty upon timely registration</li>
	<li>20-Year Heat Exchanger Limited Warranty</li>
</ul>
<span class="disclaimerText"></span>', 
		'post_excerpt'	=>	'Get efficient performance with our value gas furnace that delivers single-stage operation and a fixed-speed blower for reliable temperature control and quiet performance.',
		'post_type'     =>	'products',
		'menu_order'  	=>  4000,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'furnaces', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'Furnace-03.webp'		
),		
			
array ( 'post_title'	=>	'QuietComfort® 80 Gas Furnace',
	   	'post_content' 	=>	'<span class="descriptionText">An energy-saving two-stage gas valve and variable-speed blower motor give you enhanced control with this gas furnace. Pair with a compatible heat pump and thermostat for energy-saving dual fuel heating.</span>
<ul>
	<li>80% AFUE</li>
        <li>Variable-speed blower motor and two-stage gas valve provide enhanced levels of even temperature control and comfort</li>
        <li>Dual fuel capable with a compatible heat pump and thermostat for energy-saving heating performance</li>
        <li>Fully insulated cabinet helps keep the heat moving to your ductwork</li>
        <li>Air purifier and humidifier compatible</li>
        <li>High temperature limit control prevents overheating</li>
        <li>5-Year No Hassle Replacement™ Limited Warranty* 10-Year Parts Limited Warranty upon timely registration</li>
        <li>Lifetime Heat Exchanger Limited Warranty upon timely registration</li>
</ul>
<span class="disclaimerText"></span>', 
		'post_excerpt'	=>	'An energy-saving two-stage gas valve and variable-speed blower motor give you enhanced control with this gas furnace. Pair with a compatible heat pump and thermostat for energy-saving dual fuel heating.',
		'post_type'     =>	'products',
		'menu_order'  	=>  4100,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'furnaces', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'Furnace-02.webp'		
),				
		
		
		
		
	
	// Ductless		
array ( 'post_title'	=>	'QuietComfort® High Wall Indoor Unit',
	   	'post_content' 	=>	'<span class="descriptionText">This new High Wall indoor unit brings together the high efficiency heating and cooling performance you need combined with the attractive styling you want. This unit is available in both 115V and 208/230V models and features five modes plus Turbo Mode, Sleep Mode, and ECO Mode. This unit also features industry leading features such as Follow Me, which senses the temperature at the handheld remote’s location.</span>
<ul>
	<li>Four fan speeds</li>
        <li>Sleep Mode</li>
        <li>ECO Mode</li>
        <li>Up-down louver control (fixed or swing)</li>
        <li>Follow Me (senses temperature at handheld remote)</li>
        <li>Heating Setback (46° F Heating Mode)</li>
        <li>Quiet indoor operation, as low as 25 dB(A)1</li>
        <li>Anti-corrosive fin coating</li>
        <li>10-Year Parts Limited Warranty</li>
</ul>', 
		'post_excerpt'	=>	'Bring comfort into any space, no matter the shape. With our new energy-efficient, innovative, and stylish High Wall indoor unit, that once limited use space may just become your new favorite retreat.',
		'post_type'     =>	'products',
		'menu_order'  	=>  5000,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'ductless-systems', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'Ductless-02.webp'		
),		
			
array ( 'post_title'	=>	'QuietComfort® Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">The new heat pump is a powerhouse unit with up to 24.7 SEER and up to 14.0 EER efficiency. Features like Refrigerant Leakage Detection and Condenser High-Temperature Protection make this unit intelligent as well as efficient. Available in sizes from 9 to 36 with four different style indoor unit options, there is certainly a system to meet your cooling and heating needs.</span>
<ul>
	<li>Up to 24.7 SEER cooling efficiency</li>
        <li>Up to 14.0 EER heating efficiency</li>
        <li>Inverter Compressor</li>
        <li>100% heating capacity at 5° F (-15° C) Sizes 12-18 (208/230V)</li>
        <li>Auto-restart function</li>
        <li>Condenser high-temperature protection</li>
        <li>Refrigerant leakage detection</li>
        <li>Quiet outdoor operation, as low as 54.6 dB(A)</li>
        <li>Anti-corrosive fin coating</li>
        <li>10-Year Parts Limited Warranty</li>
</ul>', 
		'post_excerpt'	=>	'Efficient. Quiet. Cost-effective. This new heat pump checks all the boxes.',
		'post_type'     =>	'products',
		'menu_order'  	=>  5100,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'ductless-systems', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'Ductless-03.webp'		
),		
			
array ( 'post_title'	=>	'QuietComfort® Multi-zone Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">Bring comfort to multiple spaces with the new multi-zone heat pump. This energy-efficient unit provides ratings of up to 21.4 SEER and up to 10.8 EER with the flexibility to be connected from two to five indoor units. Equipped with Condenser High-temperature Protection, Refrigerant Leakage Detection and available in sizes 18 to 48, there is certainly a size to meet your cooling and heating needs.</span>
<ul>
	<li>Up to 21.4 SEER cooling efficiency</li>
	<li>Up to 12.5 EER heating efficiency</li>
        <li>Inverter Compressor</li>
	<li>Condenser high-temperature protection</li>
	<li>Refrigerant leakage detection</li>
	<li>Quiet outdoor operation, as low as 62 dB(A)</li>
	<li>Anti-corrosive fin coating</li>
	<li>10-Year Parts Limited Warranty</li>
</ul>', 
		'post_excerpt'	=>	'Powerful. Flexible. Cost-effective.',
		'post_type'     =>	'products',
		'menu_order'  	=>  5200,
		'tax_input'		=>  array('product-brand'=>'tempstar', 'product-type'=>'ductless-systems', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'Ductless-01.webp'		
),		
		
		
		
		
		
		
		
			
);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>