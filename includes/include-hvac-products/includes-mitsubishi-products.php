<?php
/* Battle Plan Web Design - Add & Remove Mitsubishi Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-mitsubishi-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_mitsubishi_products', 10 );
function add_mitsubishi_products() {

	$brand = "mitsubishi"; // lowercase
	$productImgAlt = "Mitsubishi Heating & Cooling Product"; 


	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/
	
	

	$addProducts = array (		
	
	// Ductless Systems
array ( 'post_title'	=>	'MSZ-GL Wall-Mounted Indoor Unit',
	   	'post_content' 	=>	'<span class="descriptionText">The MSZ-GL Wall-mounted Indoor Unit offers a wide range of sizes providing the most application solutions.</span>

<p>The MSZ-GL indoor unit matches the single-zone heat pump, multi-zone heat pump, or H2i® Hyper-Heating INVERTER® heat pump systems. Its counterpart, the MSY-GL, is a single-zone air conditioner for climates where heating is unnecessary.</p>

<ul>
	<li>Capacities: 6,000 to 24,000 BTU/H</li>
	<li>Sound: as low as 19 dB(A)</li>
	<li>SEER: up to 24.6</li>
	<li>HSPF: up to 12.8</li>
	<li>COP: up to 4.44</li>
	<li>ENERGY STAR®: Yes</li>
</ul>', 
		'post_excerpt'	=>	'Offers a wide range of sizes providing the most application solutions.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2000,
		'tax_input'		=>  array('product-brand'=>'mitsubishi', 'product-type'=>'ductless-systems', 'product-class'=>''),
		'meta_input'	=>	array('brochure'=>'https://www.mitsubishicomfort.com/themes/custom/MitsubishiMegaSite/src/img/productPDFs/Consumer%20Brochure%202021.pdf'),
		'image_name'	=>	'Mitsubishi-01.jpg'		
),
			
				
array ( 'post_title'	=>	'MSZ-GS Large Capacity Wall-Mounted Indoor Unit',
	   	'post_content' 	=>	'<span class="descriptionText">The MSZ-GS Large Capacity Wall-mounted Indoor Unit has wide airflow capabilities to ensure conditioned supply air reaches every corner of a room.</span>

<p>The unit\'s interior air duct/vane, coil, and fan features Dual Barrier Coating, which maintains efficiency by keeping the inside clean. The MSZ-GS also features a Powerful Mode for rapidly cooling or heating a space to the desired temperature. This indoor unit comes as a single-zone heat pump, while its counterpart, the MSY-GS, is a single-zone air conditioner suitable for climates where heating is unnecessary.</p>

<ul>
	<li>Capacities: 30,000 to 36,000 BTU/H</li>
	<li>Sound: as low as 32 dB(A)</li>
	<li>SEER: up to 18.10</li>
	<li>HSPF: up to 10.0</li>
	<li>COP: up to 2.86</li>
	<li>ENERGY STAR®: No</li>
</ul>', 
		'post_excerpt'	=>	'Wide airflow capabilities to ensure conditioned supply air reaches every corner of a room.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2010,
		'tax_input'		=>  array('product-brand'=>'mitsubishi', 'product-type'=>'ductless-systems', 'product-class'=>''),
		'meta_input'	=>	array('brochure'=>'https://www.mitsubishicomfort.com/themes/custom/MitsubishiMegaSite/src/img/productPDFs/Consumer%20Brochure%202021.pdf'),
		'image_name'	=>	'Mitsubishi-02.jpg'		
),
			
				
array ( 'post_title'	=>	'MSZ-WR 16 SEER Wall-Mounted Indoor Unit',
	   	'post_content' 	=>	'<span class="descriptionText">The MSZ-WR 16 SEER Wall-mounted Indoor Unit pairs with a single-zone heat pump outdoor unit. </span>

<p>The MSZ-WR features Econo Cool Energy Savings Mode intelligent temperature control and a stylish flat panel design.</p>

<ul>
	<li>Capacities: 9,000 to 24,000 BTU/H</li>
	<li>Sound: as low as 22 dB(A)</li>
	<li>SEER: up to 16.0</li>
	<li>HSPF: up to 8.5</li>
	<li>COP: up to 3.28</li>
	<li>ENERGY STAR®: No</li>
</ul>', 
		'post_excerpt'	=>	'Pairs with a single-zone heat pump outdoor unit.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2020,
		'tax_input'		=>  array('product-brand'=>'mitsubishi', 'product-type'=>'ductless-systems', 'product-class'=>''),
		'meta_input'	=>	array('brochure'=>'https://www.mitsubishicomfort.com/themes/custom/MitsubishiMegaSite/src/img/productPDFs/Consumer%20Brochure%202021.pdf'),
		'image_name'	=>	'Mitsubishi-03.jpg'		
),
			
				
array ( 'post_title'	=>	'SLZ Four-Way Ceiling Cassette',
	   	'post_content' 	=>	'<span class="descriptionText">The SLZ Four-way Ceiling Cassette features customizable airflow and an optional 3D i-see Sensor®. </span>

<p>These recessed ceiling cassettes mount flush with the ceiling and fit into a 2’ x 2’ suspended ceiling grid. The optional 3D i-see Sensor scans the room, measuring temperature and determining occupant location. Indirect or Direct airflow settings direct supply air away from or toward room occupants. Each of the four vanes is fully customizable to provide 72 unique airflow patterns to suit the room\'s comfort requirements perfectly.</p>

<ul>
	<li>Capacities: 9,000 to 18,000 BTU/H</li>
	<li>Sound: as low as 24 dB(A)</li>
	<li>SEER: up to 22.4</li>
	<li>HSPF: up to 12.2</li>
	<li>COP: up to 3.9</li>
	<li>ENERGY STAR®: Most Systems</li>
</ul>', 
		'post_excerpt'	=>	'Features customizable airflow and an optional 3D i-see Sensor®. ',
		'post_type'     =>	'products',
		'menu_order'  	=>  2100,
		'tax_input'		=>  array('product-brand'=>'mitsubishi', 'product-type'=>'ductless-systems', 'product-class'=>''),
		'meta_input'	=>	array('brochure'=>'https://www.mitsubishicomfort.com/themes/custom/MitsubishiMegaSite/src/img/productPDFs/Consumer%20Brochure%202021.pdf'),
		'image_name'	=>	'Mitsubishi-04.jpg'		
),
			
				
array ( 'post_title'	=>	'MUZ-GL Wall-Mounted Single-Zone Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">The MUY-GL Air Conditioner pairs with the MSY-GL Wall-mounted Indoor Unit to create a single-zone cooling-only system.</span>

<p>This solution is perfect for applications or climates where heating is unnecessary, while its counterpart, the MUZ-GL Heat Pump, offers a single-zone heating and cooling solution. The factory applies Blue Fin anti-corrosion coating to the heat exchanger\'s aluminum fins for increased coil protection and longer life.</p>

<ul>
	<li>Capacities: 9,000 to 24,000 BTU/H</li>
	<li>Sound: as low as 51 dB(A)</li>
	<li>SEER: up to 24.6</li>
	<li>ENERGY STAR®: Some Systems</li>
</ul>', 
		'post_excerpt'	=>	'Creates a single-zone cooling-only system.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2200,
		'tax_input'		=>  array('product-brand'=>'mitsubishi', 'product-type'=>'ductless-systems', 'product-class'=>''),
		'meta_input'	=>	array('brochure'=>'https://www.mitsubishicomfort.com/themes/custom/MitsubishiMegaSite/src/img/productPDFs/Consumer%20Brochure%202021.pdf'),
		'image_name'	=>	'Mitsubishi-05.jpg'		
),
			
				
array ( 'post_title'	=>	'MUZ-GS Large Capacity Wall-Mounted Single-Zone Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">The MUY-GS Air Conditioner pairs with the MSY-GS Wall-mounted Indoor Unit to create a single-zone cooling-only system.</span>

<p> This solution is perfect for applications or climates where heating is unnecessary, while its counterpart, the MUZ-GS Heat Pump, offers a single-zone heating and cooling solution. The factory applies Blue Fin anti-corrosion coating to the heat exchanger\'s aluminum fins for increased coil protection and longer life.</p>

<ul>
	<li>Capacities: 30,000 to 36,000 BTU/H</li>
	<li>Sound: as low as 48 dB(A)</li>
	<li>SEER: up to 18.1</li>
	<li>ENERGY STAR®: No</li>
</ul>', 
		'post_excerpt'	=>	'Creates a single-zone cooling-only system.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2210,
		'tax_input'		=>  array('product-brand'=>'mitsubishi', 'product-type'=>'ductless-systems', 'product-class'=>''),
		'meta_input'	=>	array('brochure'=>'https://www.mitsubishicomfort.com/themes/custom/MitsubishiMegaSite/src/img/productPDFs/Consumer%20Brochure%202021.pdf'),
		'image_name'	=>	'Mitsubishi-06.jpg'		
),
			
				
array ( 'post_title'	=>	'MUZ-WR 16 SEER Wall-Mounted Single-Zone Heat Pump',
	   	'post_content' 	=>	'<span class="descriptionText">The MUZ-WR 16 SEER Heat Pump pairs with the MSZ-WR 16 SEER Wall-mounted Indoor Unit to create a single-zone heating and cooling system.</span>

<p>The factory applies Blue Fin anti-corrosion coating to the heat exchanger\'s aluminum fins for increased coil protection and longer life.</p>

<ul>
	<li>Capacities: 9,000 to 24,000 BTU/H</li>
	<li>Sound: as low as 48 dB(A)</li>
	<li>SEER: up to 16.0</li>
	<li>HSPF: up to 8.5</li>
	<li>COP: up to 3.28</li>
	<li>ENERGY STAR®: No</li>
</ul>', 
		'post_excerpt'	=>	'Created a single-zone heating and cooling system.',
		'post_type'     =>	'products',
		'menu_order'  	=>  2220,
		'tax_input'		=>  array('product-brand'=>'mitsubishi', 'product-type'=>'ductless-systems', 'product-class'=>''),
		'meta_input'	=>	array('brochure'=>'https://www.mitsubishicomfort.com/themes/custom/MitsubishiMegaSite/src/img/productPDFs/Consumer%20Brochure%202021.pdf'),
		'image_name'	=>	'Mitsubishi-05.jpg'		
),			
			
);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>