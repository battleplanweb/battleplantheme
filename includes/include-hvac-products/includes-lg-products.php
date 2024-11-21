<?php
/* Battle Plan Web Design - Add & Remove LG Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-lg-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_lg_products', 10 );
function add_lg_products() {

	$brand = "lg"; // lowercase
	$productImgAlt = "LG Heating & Cooling Product"; 


	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


	$addProducts = array (		
	
	// Ductless Mini Splits
array ( 'post_title'	=>	'LS-HSV5 Single Zone High Efficiency',
	   	'post_content' 	=>	'<span class="descriptionText">LG\'s most energy-efficient line of heat pump duct-free products.</span>

<p>The single zone systems feature energy-saving Inverter (variable-speed compressor) technology and can be controlled through a standard wireless remote or an optional wired wall controller. Systems are available in 9,000, 12,000, and 18,000 Btu/h capacities.</p>

<ul>
	<li>Energy Saving up to 21.5 SEER</li>
	<li>Ultra Quiet Operation - Indoor unit sound pressure 19 dB (Sleep Mode)</li>
	<li>Cooling: 14°F DB to 118°F DB *</li>
	<li>Heating: -4°F WB to 65°F WB</li>
	<li>Inverter driven precision load matching - maximizes compressor efficiency, minimizing power consumption</li>
	<li>* Optional Low Ambient Wind Baffle Kit allows operation down to 0°F in Cooling</li>
</ul>', 
		'post_excerpt'	=>	'Single Zone High Efficiency Wall Mounted',
		'post_type'     =>	'products',
		'menu_order'  	=>  2000,
		'tax_input'		=>  array('product-brand'=>'lg', 'product-type'=>'mini-split-systems', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'LG-LS240HFV3.webp'		
),
				
array ( 'post_title'	=>	'LS-HFV3 Single Zone Standard Efficiency',
	   	'post_content' 	=>	'<span class="descriptionText">LG\'s new line of heat pump duct-free split products.</span>

<p>These single-zone systems feature energy-saving Inverter (variable-speed compressor) technology and are controlled through a standard wireless remote. Standard Efficiency (HFV3 series) systems are available in 9,000, 12,000, 18,000, and 24,000 Btu/h capacities.</p>

<ul>
	<li>24 Hour on/off timer</li>
	<li>4-Way auto swing</li>
	<li>Auto changeover</li>
	<li>Auto restart
    Built-in low ambient standard, down to 14°F (cooling mode)</li>
	<li>Chaos wind</li>
	<li>Jet cool/Jet heat</li>
	<li>3M HAF Filter</li>
	<li>Sleep mode</li>
	<li>Cooling only function</li>
	<li>Defrost control</li>
	<li>Inverter (variable speed compressor)</li>
	<li>Condensate sensor connection</li>
	<li>Temp display on indoor unit</li>
</ul>', 
		'post_excerpt'	=>	'Single Zone Standard Efficiency Wall Mounted',
		'post_type'     =>	'products',
		'menu_order'  	=>  2010,
		'tax_input'		=>  array('product-brand'=>'lg', 'product-type'=>'mini-split-systems', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'LG-LS240HFV3.webp'		
),
				
array ( 'post_title'	=>	'LH-HV1 Mid Static Ducted',
	   	'post_content' 	=>	'<span class="descriptionText">Mid static ducted indoor units for ceiling-concealed installation.</span>

<ul>
	<li>Inverter variable-speed fan</li>
	<li>Built-in drain pump</li>
	<li>Convertible bottom return and rear return</li>
	<li>Horizontal and vertical installation available </li>
	<li>Higher E.S.P. capability up to 0.59 in. wg</li>
</ul>', 
		'post_excerpt'	=>	'Mid static ducted indoor units for ceiling-concealed installation.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2020,
		'tax_input'		=>  array('product-brand'=>'lg', 'product-type'=>'mini-split-systems', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'LG-LH188HV1.webp'		
),	
				
array ( 'post_title'	=>	'LV-HCV Vertical AHU',
	   	'post_content' 	=>	'<span class="descriptionText">Air handlers with convertible vertical upflow or horizontal left air distribution.</span>

<p>Customers desiring traditional, ducted infrastructure can still benefit from inverter technology with the LG Vertical Air Handler (VAHU). As 4-way configurable unit contractors have the flexibility to install the unit in a way that’s best suited for the application.</p>

<ul>
	<li>Increased design flexibility with configurable 4-way installation</li>
	<li>Energy-efficient operation reduces total cost of ownership</li>
	<li>Features an Electronically commutated motor (ECM) motor making it eligible for a number of rebates</li>
	<li>Wi-Fi capable enabling effortless control using the SmartThinQ app</li>
	<li>Built-in LG Dry Contact allows for easy integration of 3rd party thermostats</li>
</ul>', 
		'post_excerpt'	=>	'Air handlers with convertible vertical upflow or horizontal left air distribution.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2030,
		'tax_input'		=>  array('product-brand'=>'lg', 'product-type'=>'mini-split-systems', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'LG-LV181HV.webp'		
),		
				
array ( 'post_title'	=>	'LC-HV 4-Way Cassette',
	   	'post_content' 	=>	'<span class="descriptionText">Four-way air-flow ceiling-cassette indoor units.</span>

<p>The ceiling-cassette indoor units in these duct-free split heat-pump systems provide comfort in large, open spaces. Duct-free installation with an aesthetically pleasing indoor unit design. The four-way controlled louvers and fan speed features on these ceiling cassette indoor units allow for even air distribution.  Easy control through the included wireless remote or optional wired wall-mounted controller.</p>

<ul>
	<li>Inverter variable-speed compressor on the outdoor unit for energy-saving operation</li>
	<li>Many modes included, controlled through wireless remote or wired wall controller: Cooling, Heating, Dehumidifying, Fan</li>
	<li>Independent louver control</li>
	<li>Internal condensate pump included</li>
	<li>Swirl Wind controls the louvers and fan speeds to create a stronger, wider air flow</li>
	<li>Jet Cool operates at high fan speeds for 30 minutes to quickly cool down a room</li>
	<li>Operates down to 5°F in cooling mode</li>
	<li>Allows refrigerant piping lengths up to 164 or 246 feet (depending on the model), and elevation difference of 98 feet
	<li>R410A refrigerant</li>
</ul>', 
		'post_excerpt'	=>	'Four-way air-flow ceiling-cassette indoor units.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2040,
		'tax_input'		=>  array('product-brand'=>'lg', 'product-type'=>'mini-split-systems', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'LG-LC188HV.webp'		
),		
			
);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>