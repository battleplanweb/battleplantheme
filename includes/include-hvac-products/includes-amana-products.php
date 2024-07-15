<?php
/* Battle Plan Web Design - Add & Remove Amana Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-amana-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_amana_products', 10 );
function add_amana_products() {

	$brand = "amana"; // lowercase
	$productImgAlt = "Amana Heating & Cooling Product"; 


	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


	$addProducts = array (		
	
	// Air Conditioners
array ( 'post_title'	=>	'ASXV9 Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">ComfortBridge™ Technology ‘bridges’ indoor comfort with smart technology, and is factory-installed into select, premium Amana® brand gas furnaces and air handlers. ComfortBridge™ Technology helps your Amana® brand air conditioner heating and cooling systems cost-effectively operate at peak performance.</span>

<p>In the past, communicating technology was limited to a few expensive, proprietary thermostats. Now, ComfortBridge™ Technology technology is installed securely in Amana® brand furnace and air handler equipment, providing you consistent, energy-efficient home comfort.</p>

<ul>
	<li>Variable-Speed, Inverter Driven</li>
	<li>Up TO 22.5 SEER2</li>
	<li>2 to 5 Tons</li>
</ul>', 
		'post_excerpt'	=>	'High-Efficiency Split System Air Conditioner',
		'post_type'     =>	'products',
		'menu_order'  	=>  1000,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/ss-asxv9.pdf?view=true'),
		'image_name'	=>	'Amana-01.jpg'		
),
				
array ( 'post_title'	=>	'ASXC7 Air Conditioner',
		'post_content' 	=>	'<ul>
	<li>Up to 17.2 SEER2 Performance</li>
	<li>High efficiency 2-stage scroll compressor </li>
	<li>Enhanced air flow</li>
	<li>Two-Stage Copeland Ultra-Tech scroll compressor</li>
	<li>Quiet two-speed ECM outdoor fan motor</li>
	<li>Integrated communicating ComfortBridge™ Technology</li>
	<li>Commissioning and diagnostics via Bluetooth indoor board via CoolCloud™ App</li>
	<li>Copeland® ComfortAlert™ built in diagnostics </li>
</ul>', 
		'post_excerpt'	=>	'High-Efficiency Air Conditioner',
		'post_type'     =>	'products',
		'menu_order'  	=>  1010,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-conditioners', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-asxc7.pdf'),
		'image_name'	=>	'Amana-01.jpg'		
),		
				
array ( 'post_title'	=>	'ASXH5 Air Conditioner',
		'post_content' 	=>	'<ul>
	<li>Split System Air Conditioner</li>
	<li>Up to 15.2 SEER2</li>
	<li>1½ To 5 Tons</li>
	<li>Heavy-gauge galvanized-steel cabinet</li>
	<li>Baked-on powder-paint finish with 500-hour salt-spray approval</li>
	<li>Steel louver coil guard with Rust-resistant screws</li>
</ul>', 
		'post_excerpt'	=>	'High-Efficiency Air Conditioner',
		'post_type'     =>	'products',
		'menu_order'  	=>  1020,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-conditioners', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-asxh5.pdf'),
		'image_name'	=>	'Amana-01.jpg'		
),		
				
array ( 'post_title'	=>	'ASXH4 Air Conditioner',
		'post_content' 	=>	'<span class="descriptionText">Constantly monitoring the performance of your Amana® brand air conditioner, this advanced diagnostics system provides insight for accurate troubleshooting and quick diagnosis and repairs. Using the compressor as a sensor, CoreSense Diagnostics delivers active protection and will proactively shut the system down should it detect conditions that could damage the compressor. As a result, catastrophic failures and extensive, costly repairs are often avoided. </span>
<ul>
	<li>UP TO 14.3 SEER2</li>
	<li>1½ TO 5 Tons </li>
</ul>', 
		'post_excerpt'	=>	'Energy-Efficient Base Plus Air Conditioner',
		'post_type'     =>	'products',
		'menu_order'  	=>  1030,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-asxh4.pdf'),
		'image_name'	=>	'Amana-02.jpg'		
),
		
		
		
		
	
	// Heat Pumps
array ( 'post_title'	=>	'ASZV9 Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">SEER or Seasonal Energy Efficiency Rating is a measure designated by the U.S. Department of Energy and gives you a good idea of the performance you can expect from heat pumps.</span>
			
<p>When you choose an energy efficient Amana brand heat pump, year-round indoor comfort is combined with year-round savings compared to lower SEER-rated heat pumps. </p>
<ul>
	<li>Inverter Driven</li>
    <li>Up to 22.5 SEER2</li>
    <li>Quiet DC outdoor fan motor</li>
</ul>', 
		'post_excerpt'	=>	'High-Efficiency Split System Heat Pump',
		'post_type'     =>	'products',
		'menu_order'  	=>  1100,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-aszv9.pdf'),
		'image_name'	=>	'Amana-01.jpg'		
),		
		
array ( 'post_title'	=>	'ASZC7 Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">ComfortBridge™ Technology ‘bridges’ indoor comfort with smart technology, and is factory-installed into select, premium Amana® brand gas furnaces and air handlers. ComfortBridge™ Technology helps your Amana® brand air conditioner heating and cooling systems cost-effectively operate at peak performance.</span>			

<p>In the past, communicating technology was limited to a few expensive, proprietary thermostats. Now, ComfortBridge™ Technology technology is installed securely in Amana® brand furnace and air handler equipment, providing you consistent, energy-efficient home comfort.</p>

<ul>
	<li>UP TO 17.2 SEER2 and 8.2 HSPF2</li>
    <li>ComfortBridge™ Technology</li>
    <li>Two-Stage scroll compressors</li>
</ul>', 
		'post_excerpt'	=>	'High-Efficiency Heat Pump Two-Stage',
		'post_type'     =>	'products',
		'menu_order'  	=>  1110,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'heat-pumps', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-aszc7.pdf'),
		'image_name'	=>	'Amana-01.jpg'		
),		
		
array ( 'post_title'	=>	'ASZH5 Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">SEER or Seasonal Energy Efficiency Rating is a measure designated by the U.S. Department of Energy and gives you a good idea of the performance you can expect from heat pumps. At a 15.2-SEER2 cooling rating, the Amana® brand ASZH5 Heat Pump can deliver an up to 7.8 HSPF2 (Heating Seasonal Performance Factor). </span>
<ul>
	<li>UP TO 15.2 SEER2 & 7.8 HSPF2</li>
	<li>1½ to 5 Tons</li>
</ul>', 
		'post_excerpt'	=>	'High-Efficiency Heat Pump',
		'post_type'     =>	'products',
		'menu_order'  	=>  1120,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'heat-pumps', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-aszh5.pdf'),
		'image_name'	=>	'Amana-01.jpg'		
),
		
array ( 'post_title'	=>	'ASZH4 Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">SEER or Seasonal Energy Efficiency Rating is a measure designated by the U.S. Department of Energy and gives you a good idea of the performance you can expect from heat pumps. At a 14.3 SEER2 cooling rating, the Amana® brand ASZH4 Heat Pump can deliver an 7.5 HSPF2 (Heating Seasonal Performance Factor).</span>

<p>When you choose an energy efficient Amana brand heat pump, year-round indoor comfort is combined with year-round savings compared to lower SEER-rated heat pumps.</p>
<ul>
	<li>14.3 SEER2/ 7.5 HSPF2</li>
    <li>High-Efficiency Copeland® scroll compressor</li>
    <li>Advanced Copeland® CoreSense technology</li>
    <li>SmartShift® technology</li>
</ul>', 
		'post_excerpt'	=>	'High-Efficiency Split System Heat Pump',
		'post_type'     =>	'products',
		'menu_order'  	=>  1130,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-aszh4.pdf'),
		'image_name'	=>	'Amana-02.jpg'		
),
		
		
		
		
	
	// Furnaces
array ( 'post_title'	=>	'AMVC96 - 90+% AFUE Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">ComfortBridge™ Technology ‘bridges’ indoor comfort with smart technology, and is factory-installed into select, premium Amana® brand gas furnaces and air handlers. ComfortBridge™ Technology helps your Amana® brand air conditioner heating and cooling systems cost-effectively operate at peak performance.</span>

<p>In the past, communicating technology was limited to a few expensive, proprietary thermostats. Now, ComfortBridge™ Technology technology is installed securely in Amana® brand furnace and air handler equipment, providing you consistent, energy-efficient home comfort.</p>

<p>Many homeowners may think the best gas furnaces are not seen or heard. That’s why an Amana® brand AMVC96 Variable-Speed Furnace strives to operate on low capacity as often and as long as possible providing quiet and highly-efficient performance compared to units containing a single speed motor.</p>

<p>Energy efficiency equates to cost savings. A gas furnace’s efficiency rating can primarily be determined by two factors: its AFUE rating (Annual Fuel Utilization Efficiency), which indicates what percentage of each dollar of natural gas purchased is actually used to heat your home; and the type of blower used in the furnace.</p>
<ul>
	<li>96% AFUE</li>
	<li>Stainless-Steel Tubular Primary Heat Exchanger</li>
    <li>Integrated communicating ComfortBridge™ Technology</li>
    <li>Efficient and Quiet Variable-Speed Circulator Motor</li>
    <li>Durable silicon nitride igniter</li>
    <li>Two-Stage Gas Valve</li>
    <li>Quiet, Two-Stage, Induced-Draft Blower</li>
    <li>Continuous Air Circulation</li>
    <li>Self-Diagnostic Control Board</li>
    <li>Heavy-Gauge Steel Cabinet with Durable Finish</li>
    <li>Thermally Insulated Cabinet</li>
</ul>', 
		'post_excerpt'	=>	'Two-Stage Variable-Speed Gas Furnace',
		'post_type'     =>	'products',
		'menu_order'  	=>  1200,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'furnaces', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/pb-amvc9694d9450022fa6258827eff0a00754798.pdf'),
		'image_name'	=>	'Amana-F-01.jpg'		
),

array ( 'post_title'	=>	'AM9S96-U - 90+% AFUE Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">Many homeowners may think the best gas furnaces are not seen or heard. That’s why an Amana® brand AM9S96-U Multi-Speed Furnace is insulated for noise reduction. With a sound-isolated blower assembly and a heavy-gauge steel cabinet, it offers quiet and efficient performance as compared to a natural draft furnace.</span>
			
			<p>Energy efficiency equates to cost savings. A gas furnace’s efficiency rating can primarily be determined by two factors: its AFUE rating (Annual Fuel Utilization Efficiency), which indicates what percentage of each dollar of natural gas purchased is actually used to heat your home; and the type of blower used in the furnace</p>
<ul>
	<li>Up to 96% AFUE</li>
    <li>Super-ferritic stainless-steel secondary heat exchanger</li>
    <li>Efficient and Quiet Multi-Speed ECM Blower Motor</li>
    <li>Durable silicon nitride igniter</li>
    <li>Quiet, Single-Speed, Induced-Draft Blower</li>
    <li>Continuous Air Circulation</li>
    <li>Self-Diagnostic Control Board</li>
    <li>Heavy-Gauge Steel Cabinet with Durable Finish</li>
    <li>Thermally insulated cabinet</li>
</ul>', 
		'post_excerpt'	=>	'Single-Stage Multi-Speed Gas Furnace',
		'post_type'     =>	'products',
		'menu_order'  	=>  1210,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'furnaces', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/cb-am9s96-u_12-21.pdf'),
		'image_name'	=>	'Amana-F-01.jpg'		
),				
		
array ( 'post_title'	=>	'AM9S80 - 80% AFUE Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">A gas furnace is a piece of equipment from which you expect years of uninterrupted service. Amana® brand gas furnaces live up to expectations through intelligently designed components that benefit from decades of performance testing and refinement, like our new stainless-steel heat exchanger. And with outstanding warranties* that demonstrate our confidence, you can purchase an Amana brand furnace safe in the knowledge it will Last and Last and Last®.</span>
			
			<p>Many homeowners may think the best gas furnaces are not seen or heard. That’s why an Amana brand AM9S80/AC9S80 Multi-Speed ECM Furnace is insulated for noise reduction. With a sound-isolated blower assembly and a heavy-gauge steel cabinet, it offers quiet and efficient performance compared to furnaces with single-speed motors.</p>
			
			<p>Energy efficiency equates to cost savings. A gas furnace’s efficiency rating can primarily be determined by two factors: its AFUE rating (Annual Fuel Utilization Efficiency), which indicates what percentage of each dollar of natural gas purchased is actually used to heat your home; and the type of blower used in the furnace.</p>
<ul>
	<li>80% AFUE</li>
	<li>Heavy-Duty Aluminized-Steel, Dual-diameter Tubular Heat Exchanger</li>
    <li>Single-stage Gas Valve</li>
    <li>Durable Hot-surface igniter</li>
    <li>Quiet, Single-speed Draft Induced</li>
    <li>Self-diagnostic Control Bboard</li>
   <li>Color-coded Low-voltage Terminals</li>
    <li>Multi-speed ECM Blower Motor</li>
    <li>Heavy-gauge Steel Cabinet with Durable Baked-enamel Finish</li>
    <li>Foil Faced Insulated Heat Exchanger</li>
</ul>', 
		'post_excerpt'	=>	'Single-Stage Multi-Speed Gas Furnace',
		'post_type'     =>	'products',
		'menu_order'  	=>  1220,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'furnaces', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/cb-am9s80.pdf'),
		'image_name'	=>	'Amana-F-02.jpg'		
),	
		
array ( 'post_title'	=>	'AMVC8 / ADVC8 - 80% AFUE Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">A gas furnace is a piece of equipment from which you expect years of uninterrupted service. Amana® brand gas furnaces live up to expectations through intelligently designed components that benefit from decades of performance testing and refinement, like our new stainless-steel heat exchanger. And with outstanding warranties* that demonstrate our confidence, you can purchase an Amana brand furnace safe in the knowledge it will Last and Last and Last®.</span>
			
			<p>Many homeowners may think the best gas furnaces are not seen or heard. That’s why an Amana brand AM9S80/AC9S80 Multi-Speed ECM Furnace is insulated for noise reduction. With a sound-isolated blower assembly and a heavy-gauge steel cabinet, it offers quiet and efficient performance compared to furnaces with single-speed motors.</p>
			
			<p>Energy efficiency equates to cost savings. A gas furnace’s efficiency rating can primarily be determined by two factors: its AFUE rating (Annual Fuel Utilization Efficiency), which indicates what percentage of each dollar of natural gas purchased is actually used to heat your home; and the type of blower used in the furnace.</p>
<ul>
	<li>80% AFUE</li>
	<li>Heavy-Duty Aluminized-Steel, Dual-diameter Tubular Heat Exchanger</li>
    <li>Single-stage Gas Valve</li>
    <li>Durable Hot-surface igniter</li>
    <li>Quiet, Single-speed Draft Induced</li>
    <li>Self-diagnostic Control Bboard</li>
    <li>Color-coded Low-voltage Terminals</li>
    <li>Multi-speed ECM Blower Motor</li>
    <li>Heavy-gauge Steel Cabinet with Durable Baked-enamel Finish</li>
    <li>Foil Faced Insulated Heat Exchanger</li>
</ul>', 
		'post_excerpt'	=>	'Two-Stage Multi-Speed Gas Furnace',
		'post_type'     =>	'products',
		'menu_order'  	=>  1230,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'furnaces', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/cb-amvc8.pdf'),
		'image_name'	=>	'Amana-F-02.jpg'		
),
		
		
		
		
	
	// Air Handlers
array ( 'post_title'	=>	'AMVE Air Handler',
		'post_content' 	=>	'<span class="descriptionText">ComfortBridge™ Technology ‘bridges’ indoor comfort with smart technology, and is factory-installed into select, premium Amana® brand gas furnaces and air handlers. ComfortBridge™ Technology helps your Amana® brand air conditioner heating and cooling systems cost-effectively operate at peak performance.</span>

<p>In the past, communicating technology was limited to a few expensive, proprietary thermostats. Now, ComfortBridge™ Technology technology is installed securely in Amana® brand furnace and air handler equipment, providing you consistent, energy-efficient home comfort.</p>

<p>Variable Speed ECM Blower Motors provide gradual startup and shutdown for quiet, unobtrusive operation with lower energy consumption compared to standard efficiency motors across a wide range of operating speeds. Constant low-speed operation for outstanding filtration and comfort level (models AVPEC and MBVC).</p>
<ul>
	<li>All-Aluminum Coil</li>
    <li>ComfortBridge™ Technology</li>
    <li>Electronic Expansion Valve, Inverter-tuned</li>
    <li>SmartFrame™ Sub-Structure</li>
    <li>2 to 5 tons</li>
    <li>Integrated communicating ComfortBridge™ Technology</li>
    <li>Variable-speed ECM blower motor</li>
    <li>Provides constant CFM over a wide range of static pressure conditions independent of duct system</li>
    <li>Improved humidity and comfort control</li>
    <li>Horizontal or vertical configuration capabilities</li>
</ul>', 
		'post_excerpt'	=>	'Multi-Position, Variable-Speed Air Handler',
		'post_type'     =>	'products',
		'menu_order'  	=>  1300,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-handlers', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-amve.pdf'),
		'image_name'	=>	'Amana-AH-01.jpg'		
),

array ( 'post_title'	=>	'AHVE Air Handler',
		'post_content' 	=>	'<ul>
	<li>ECM-Based Air Handler with Internal EEV</li>
    <li>Communicating For S-Series matchups 1½ to 5 Tons </li>
    <li>Electronic Expansion Valve (EEV) for cooling and heat pump applications.</li>
    <li>7mm evaporator coil tube size</li>
    <li>Variable-speed ECM blower motor</li>
    <li>Compatible with Amana® brand smart thermostat</li>
    <li>Provides constant CFM over a wide range of static pressure conditions independent of duct system</li>
    <li>CFM indicator</li>
    <li>Thermostat provides adjustable low-CFM for efficient fan-only operation</li>
    <li>All-aluminum evaporator coil</li>
    <li>Fault recall of six most recent faults</li>
    <li>Improved humidity and comfort control</li>
    <li>AHRI certified; ETL listed</li>
    <li>Coil mounting track for quick repositioning</li>
    <li>Rigid SmartFrame™ cabinet</li>
</ul>', 
		'post_excerpt'	=>	'Variable-Speed Air Handler',
		'post_type'     =>	'products',
		'menu_order'  	=>  1310,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-handlers', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-ahve.pdf'),
		'image_name'	=>	'Amana-AH-01.jpg'		
),

array ( 'post_title'	=>	'AMST Air Handler',
		'post_content' 	=>	'<span class="descriptionText">The AMST Multi-Position, 9-Speed ECM air handler with internal TXV is optimized for single-stage AC and HP outdoor units up to 15.2 SEER2.</span>
<ul>
	<li>Energy-Efficient</li>
    <li>Multi-Position</li>
    <li>Electronic Expansion Valve, Inverter-tuned</li>
    <li>Internal factory-installed thermal expansion valves for cooling and heat pump applications</li>
	<li>7mm evaporator coil tube size on 1½ - 3½ Ton models and ⅜" evaporator coil tube size on 4 to 5 Ton models</li>
	<li>Direct-Drive, 9-speed ECM blower motor</li>
	<li>All-aluminum evaporator coil with high performance coated fin stock</li>
	<li>Coil mounting track for quick repositioning</li>
	<li>Optimized for use with R-410A refrigerant</li>
	<li>Cabinet air leakage less than 2.0%at 1.0 inch H₂O when tested in accordance with ASHRAE standard 193</li>
	<li>Cabinet air leakage less than 1.4%at 0.5 inch H₂O when tested in accordance with ASHRAE standard 193</li>
	<li>3 kW – 25 kW electric heater kits</li>
	<li>AHRI certified; ETL listed</li>
</ul>', 
		'post_excerpt'	=>	'Energy Efficient Air Handlers',
		'post_type'     =>	'products',
		'menu_order'  	=>  1320,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-handlers', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/docs/default-source/default-document-library/ss-amve.pdf'),
		'image_name'	=>	'Amana-AH-01.jpg'		
),		
			
);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>