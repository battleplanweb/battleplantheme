<?php
/* Battle Plan Web Design - Add & Remove Amana Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-amana-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );
endif;
*/

/* PRODUCT OVERVIEW
[product-overview type="amana air conditioners"]

[product-overview type="amana air handlers"]

[product-overview type="amana heat pumps"]

[product-overview type="amana furnaces"]
*/


add_action( 'wp_loaded', 'add_amana_products', 10 );
function add_amana_products() {

	$brand = "amana"; // lowercase
	$productImgAlt = "Amana Heating & Cooling Product";

	$removeImages = array('Amana-AH-01.jpg', 'Amana-F-02.jpg', 'Amana-F-01.jpg', 'Amana-02.jpg', 'Amana-01.jpg');

	$removeProducts = array('asxv9-air-conditioner', 'asxc7-air-conditioner', 'asxh5-air-conditioner', 'asxh4-air-conditioner', 'aszv9-heat-pump', 'aszc7-heat-pump', 'aszh5-heat-pump', 'aszh4-heat-pump', 'amvc96-90-afue-gas-furnace', 'amvc8-advc8-80-afue-gas-furnace', 'am9s96-u-90-afue-gas-furnace', 'am9s80-80-afue-gas-furnace', 'amve-air-handler', 'ahve-air-handler', 'amst-air-handler' );



	$addProducts = array (

	// Air Conditioners
array ( 'post_title'	=>	'AXV9S Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">A slim side discharge style system that provides a premium mid-efficiency solution at an affordable price. It connects to traditional ducted equipment with ease, and the slim profile provides a solution when traditional cube style options cannot.</span>

<p>Compatible with the S-Series Smart Thermostat System, its slim design and compact footprint provide flexible installation options, including pad or wall mounting, making it ideal for zero-lot-line homes and small patios or backyards.</p>

<ul>
	<li>R-32 Split System Air Conditioner</li>
	<li>Up TO 19 SEER2</li>
	<li>Variable-Speed, Inverter Driven</li>
	<li>Compatible with the Amana® brand Smart thermostat</li>
	<li><b>Blue Fin Corrosion Coating:</b> 1000 hours salt spray rated coil as standard. This hydrophilic coating helps keep the coil clean.</li>
	<li><b>Quiet Mode:</b> provides enhanced acoustical comfort. When activated (before sleep, etc.) homeowners will enjoy additional quiet within their space.</li>
	<li><b>Intelligent Defrost Mode:</b> prevents frost/ice from building up in cold climate conditions and helps with longer heating operation time for additional comfort for occupants.</li>
</ul>',
		'post_excerpt'	=>	'A slim side discharge style system that provides a premium mid-efficiency solution at an affordable price. It connects to traditional ducted equipment with ease, and the slim profile provides a solution when traditional cube style options cannot.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1000,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-s-series-heac.pdf?view=true'),
		'image_name'	=>	'AXV9S.webp'
),






array ( 'post_title'	=>	'AXV6S Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">An affordable, premium mid-efficiency solution with a slim, side-discharge design that easily connects to traditional ducted systems. Its compact profile makes it an excellent choice for tight spaces where conventional cube-style outdoor units may not fit.</span>

<p>Compatible with the S-Series Smart Thermostat System and features a slim, space-saving design for greater installation flexibility. It can be installed on a pad or wall-mounted, making it ideal for zero-lot-line properties, small patios, backyards, and other tight spaces.</p>

<ul>
	<li>R-32 Split System Air Conditioner</li>
	<li>Up TO 17.2 SEER2</li>
	<li>Slim style, small footprint</li>
	<li>Compatible with the Amana® brand Smart thermostat</li>
	<li><b>Blue Fin Corrosion Coating:</b> 1000 hours salt spray rated coil as standard. This hydrophilic coating helps keep the coil clean.</li>
	<li><b>Quiet Mode:</b> provides enhanced acoustical comfort. When activated (before sleep, etc.) homeowners will enjoy additional quiet within their space.</li>
	<li><b>Intelligent Defrost Mode:</b> prevents frost/ice from building up in cold climate conditions and helps with longer heating operation time for additional comfort for occupants.</li>
</ul>',
		'post_excerpt'	=>	'An affordable, premium mid-efficiency solution with a slim, side-discharge design that easily connects to traditional ducted systems. Its compact profile makes it an excellent choice for tight spaces where conventional cube-style outdoor units may not fit.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1010,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-s-series-heac.pdf?view=true'),
		'image_name'	=>	'AXV6S.webp'
),






array ( 'post_title'	=>	'ALXT7C Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Engineered for quiet, efficient comfort, featuring a specially designed sound-control top to help minimize operating noise. With energy-efficiency ratings of up to 17.2 SEER2, it can also help reduce energy consumption and potentially lower cooling costs while keeping your home comfortable.</span>

<p>Features a durable copper tube and enhanced aluminum fin coil, simplified communicating or non-communicating setup, and numerous factory-installed components for easier installation and reliable operation. Additional features include a compressor sound blanket for quieter performance, ambient temperature sensors, factory refrigerant charge for up to 15 feet of tubing, and built-in high- and low-pressure switches. AHRI Certified and ETL Listed.</p>

<ul>
	<li>R-32 High-Efficiency Air Conditioner</li>
	<li>Up to 17.2 SEER2</li>
	<li>Two-Stage Copeland Ultra-Tech scroll compressor</li>
	<li>Quiet two-speed ECM outdoor fan motor</li>
	<li>Integrated communicating ComfortBridge™ Technology</li>
	<li>Commissioning and diagnostics via Bluetooth indoor board via CoolCloud™ App</li>
	<li>Copeland® ComfortAlert™ built in diagnostics</li>
</ul>',
		'post_excerpt'	=>	'Engineered for quiet, efficient comfort, featuring a specially designed sound-control top to help minimize operating noise. With energy-efficiency ratings of up to 17.2 SEER2, it can also help reduce energy consumption and potentially lower cooling costs.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1020,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_acsplits.pdf?view=true'),
		'image_name'	=>	'Amana-AC-01.webp'
),






array ( 'post_title'	=>	'ALXS5B Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Built to deliver dependable cooling, lasting durability, and comfortable indoor temperatures—even during the hottest weather. With a range of efficiency options available, choosing a system with a higher SEER2 rating can provide greater energy efficiency and help reduce cooling costs over time.</span>

<p>Built for efficient, quiet, and dependable performance, this system features a high-efficiency scroll compressor with a sound-reducing foam blanket and advanced Copeland® CoreSense™ Technology. Durable components include an enhanced copper tube/aluminum fin coil, extended-life capacitors, factory-installed filter drier, high-pressure protection, and convenient service connections. The unit comes factory charged for 15 feet of tubing and is AHRI Certified and ETL Listed.</p>

<ul>
	<li>R-32 Split System Air Conditioner</li>
	<li>Up to 17 SEER2</li>
	<li>Heavy-gauge galvanized-steel cabinet</li>
	<li>Baked-on powder-paint finish with 500-hour salt-spray approval</li>
	<li>Steel louver coil guard with Rust-resistant screws</li>
</ul>',
		'post_excerpt'	=>	'Built to deliver dependable cooling, lasting durability, and comfortable indoor temperatures—even during the hottest weather. With a range of efficiency options available, choosing a system with a higher SEER2 rating can provide greater energy efficiency and help reduce cooling costs over time.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1030,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_acsplits.pdf?view=true'),
		'image_name'	=>	'Amana-AC-01.webp'
),






array ( 'post_title'	=>	'ALXS4B Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Combines reliable performance, lasting durability, and powerful cooling to keep your home comfortable even on the hottest days. With a variety of efficiency options available, systems with higher SEER2 ratings provide greater energy efficiency and the potential for increased savings on cooling costs.</span>

<p>Built for efficient, reliable performance, this system features an energy-efficient compressor with advanced Copeland® technology, a sound-reducing compressor blanket, and a durable copper tube/aluminum fin coil. Factory-installed components and easy-access service connections help simplify installation and maintenance, while extended-life capacitors and high-pressure protection enhance durability. The unit comes factory charged for 15 feet of tubing and is AHRI Certified and ETL Listed.</p>

<ul>
	<li>R-32 Split System Air Conditioner</li>
	<li>Up to 15.2 SEER2</li>
	<li><b>Copeland® CoreSense Diagnostics:</b> Using the compressor as a sensor, CoreSense Diagnostics delivers active protection and will proactively shut the system down should it detect conditions that could damage the compressor. As a result, catastrophic failures and extensive, costly repairs are often avoided.</li>
	<li><b>Quiet Performance:</b> Acoustically engineered with enhancements such as a specially designed sound-control top.</li>
	<li><b>Energy Efficiency:</b> Provides significant savings on your electric bill compared to lower SEER units found in many homes.</li>
</ul>',
		'post_excerpt'	=>	'Combines reliable performance, lasting durability, and powerful cooling to keep your home comfortable even on the hottest days. With a variety of efficiency options available, systems with higher SEER2 ratings provide greater energy efficiency and the potential for increased savings on cooling costs.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1040,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_acsplits.pdf?view=true'),
		'image_name'	=>	'Amana-AC-01.webp'
),








	// Heat Pumps
array ( 'post_title'	=>	'AZV9S Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">A slim, side-discharge design with dependable mid-efficiency performance at an affordable price. It easily connects with traditional ducted equipment, while its compact profile makes it an ideal solution for tight spaces where conventional cube-style outdoor units may not fit.</span>

<p>Designed for efficient, quiet, and dependable year-round comfort, this system features a variable-speed swing compressor with strong heating performance for cold climates and reliable operation in high-ambient conditions. Advanced communicating controls provide smart diagnostics, simplified two-wire installation, Amana Smart Thermostat compatibility, and selectable performance settings. Quiet-mode operation can reduce sound levels to as low as 47 dBA, while boost mode provides additional capacity during unusually high heating or cooling demands.</p>

<ul>
	<li>Inverter Driven</li>
    	<li>Up to 21 SEER2</li>
    	<li>Quiet DC outdoor fan motor</li>
   	<li>Variable-speed swing compressors</li>
    	<li><b>Quiet Performance:</b> Acoustically engineered with enhancements such as a specially designed sound-control top.</li>
    	<li><b>Energy Efficiency:</b> Provides significant savings on your electric bill compared to lower SEER units found in many homes.</li>
</ul>',
		'post_excerpt'	=>	'A slim, side-discharge design with dependable mid-efficiency performance at an affordable price. It easily connects with traditional ducted equipment, while its compact profile makes it an ideal solution for tight spaces where conventional cube-style outdoor units may not fit.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1100,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-s-series-hehp.pdf?view=true'),
		'image_name'	=>	'AZV9S.webp'
),





array ( 'post_title'	=>	'AZV6S Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">Delivers efficient year-round comfort with ratings of up to 19.0 SEER2 and 8.8 HSPF2, available in 1½- to 5-ton capacities. Its slim, space-saving design features intelligent defrost, compatibility with the Amana Smart Thermostat, and multiple quiet-mode settings with operation as low as 45 dBA.</span>

<p>Compatible with the S-Series Smart Thermostat System, this unit features a slim, compact design that provides greater installation flexibility. It can be installed on a pad or wall-mounted, making it an excellent solution for zero-lot-line homes, small patios, backyards, and other limited spaces.</p>

<ul>
	<li>Heat Pump with Inverter Technology</li>
    	<li>Up to 19 SEER2</li>
	<li>Up to 8.8 HSPF2</li>
	<li>Slim style, small footprint</li>
    	<li>Compatible with the Amana® Brand Smart thermostat</li>
	<li><b>Blue Fin Corrosion Coating:</b> 1000 hours salt spray rated coil as standard. This hydrophilic coating helps keep the coil clean.</li>
	<li><b>Quiet Mode:</b> provides enhanced acoustical comfort. When activated (before sleep, etc.) homeowners will enjoy additional quiet within their space.</li>
	<li><b>Intelligent Defrost Mode:</b> prevents frost/ice from building up in cold climate conditions and helps with longer heating operation time for additional comfort for occupants.</li>
</ul>',
		'post_excerpt'	=>	'Delivers efficient year-round comfort with ratings of up to 19.0 SEER2 and 8.8 HSPF2, available in 1½- to 5-ton capacities. Its slim, space-saving design features intelligent defrost, compatibility with the Amana Smart Thermostat, and multiple quiet-mode settings with operation as low as 45 dBA.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1110,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-s-series-achp.pdf?view=true'),
		'image_name'	=>	'AZV9S.webp'
),







array ( 'post_title'	=>	'ALZT7C Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">Provides efficient heating and cooling from a single system, and helps keep your home comfortable throughout the year. With options designed to fit a variety of needs and budgets, higher-efficiency models can help reduce energy costs compared with lower-rated systems. For the greatest potential energy savings, look for models with higher SEER2 and HSPF2 ratings.</span>

<p>This system combines a two-stage Copeland® UltraTech scroll compressor with advanced ComfortBridge™ communicating technology for efficient, dependable comfort. Built-in diagnostics, Bluetooth commissioning through the CoolCloud™ app, two-speed condenser fan operation, and simplified wiring make setup and service easier. Additional protections include high- and low-pressure switches, fault-code storage, and short-cycle protection for quiet, reliable operation and defrost performance.</p>

<ul>
	<li>High-Efficiency Heat Pump</li>
	<li>Up to 17.5 SEER2</li>
	<li>Up to 8.2HSPF2</li>
    	<li>Two-Stage scroll compressors</li>
   	<li><b>ComfortBridge™ Technology:</b> Bridges indoor comfort with smart technology, and helps cost-effectively operate at peak performance.	   </li>
    	<li><b>Quiet Performance:</b> Acoustically engineered with enhancements such as a specially designed sound-control top.</li>
    	<li><b>Energy Efficiency:</b> Provides significant savings on your electric bill compared to lower SEER units found in many homes.</li>
</ul>',
		'post_excerpt'	=>	'Provides efficient heating and cooling from a single system, and helps keep your home comfortable throughout the year. With options designed to fit a variety of needs and budgets, higher-efficiency models can help reduce energy costs compared with lower-rated systems.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1120,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_hp.pdf?view=true'),
		'image_name'	=>	'Amana-AC-01.webp'
),







array ( 'post_title'	=>	'ALZS5B Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">Provides reliable heating and cooling from a single system, delivering comfortable indoor temperatures throughout the year. A system with higher SEER2 and HSPF2 ratings can provide greater energy efficiency and help reduce year-round heating and cooling costs.</span>

<p>Built for efficient, quiet, and dependable year-round performance, this heat pump features a high-efficiency Copeland scroll compressor with advanced CoreSense® technology and durable copper tube/aluminum fin coils. SmartShift® technology helps provide quiet, reliable defrost operation, while factory-installed protective components, extended-life capacitors, and easy-access service connections enhance durability and serviceability. The system is AHRI Certified and ETL Listed..</p>

<ul>
	<li>R-32 Split System Heat Pump</li>
    	<li>Up to 16 SEER2</li>
    	<li>Up to 8.2 HSPF2</li>
    	<li>Enhanced aluminum fin coil</li>
   	<li>Heavy-gauge galvanized-steel cabinet</li>
    	<li>Baked-on powder-paint finish with 500-hour salt-spray approval</li>
    	<li>Top and side maintenance access</li>
</ul>',
		'post_excerpt'	=>	'Provides reliable heating and cooling from a single system, delivering comfortable indoor temperatures throughout the year. A system with higher SEER2 and HSPF2 ratings can provide greater energy efficiency and help reduce year-round heating and cooling costs.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1130,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_hp.pdf?view=true'),
		'image_name'	=>	'Amana-AC-01.webp'
),







array ( 'post_title'	=>	'ALZS4B Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">Provides dependable heating and cooling for year-round indoor comfort while helping reduce energy consumption. Compared with lower SEER-rated systems, a higher-efficiency Amana heat pump can deliver greater energy savings throughout the year.</span>

<p>Built for efficient, quiet, and reliable performance, this heat pump features a high-efficiency Copeland scroll compressor with advanced CoreSense technology, durable copper tube/aluminum fin coils, and SmartShift® technology for quieter, dependable defrost operation. Factory-installed protective components, extended-life capacitors, a compressor sound blanket, and convenient service connections enhance durability, comfort, and serviceability. The system is AHRI Certified and ETL Listed.</p>

<ul>
	<li>R-32 Split System Heat Pump</li>
    	<li>Up to 15.2 SEER2</li>
    	<li>Up to 7.8 HSPF2</li>
    	<li>Enhanced aluminum fin coil</li>
   	<li>Variable-speed swing compressors</li>
    	<li>Quiet reliable defrost</li>
</ul>',
		'post_excerpt'	=>	'Provides dependable heating and cooling for year-round indoor comfort while helping reduce energy consumption. Compared with lower SEER-rated systems, a higher-efficiency Amana heat pump can deliver greater energy savings throughout the year.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1140,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_hp.pdf?view=true'),
		'image_name'	=>	'Amana-AC-01.webp'
),









	// Furnaces
	array ( 'post_title'	=>	'ARVT96 90+% AFUE Gas Furnaces',
	'post_content' 	=>	'<span class="descriptionText">Provides efficient, reliable heating to keep your home warm and comfortable throughout the colder months. When an aging furnace becomes less dependable or costly to repair, replacing it with a high-efficiency Amana system can provide improved performance, greater efficiency, and dependable long-term comfort.</span>

<p>Built for **quiet, efficient, and dependable heating**, this furnace features **ComfortBridge™ communicating technology**, a two-stage gas valve, durable stainless-steel heat exchangers, and a variable-speed airflow system that automatically adjusts to your home\'s comfort demands. Built-in Bluetooth commissioning and advanced diagnostics through the CoolCloud™ app simplify setup and service, while multiple fan speeds and enhanced dehumidification options provide greater control over year-round indoor comfort.
</p>

<ul>
	<li>Two-Stage Variable-Speed Gas Furnace</li>
	<li>97.5% AFUE</li>
	<li>Efficient and Quiet Variable-Speed Circulator Motor</li>
	<li>Quiet comfort</li>
	<li>Energy Efficient</li>
	<li><b>ComfortBridge™ Technology:</b> Bridges indoor comfort with smart technology, and cost-effectively operate at peak performance.</li>
</ul>',
  'post_excerpt'	=>	'Provides efficient, reliable heating to keep your home warm and comfortable throughout the colder months. A high-efficiency Amana system can provide improved performance, greater efficiency, and dependable long-term comfort.',
  'post_type'     =>	'products',
  'menu_order'  	=>  1200,
  'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'furnaces', 'product-class'=>'best'),
  'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_90furn.pdf?view=true'),
  'image_name'	=>	'Amana-F-01.webp'
),





array ( 'post_title'	=>	'ARVT80 80% AFUE Gas Furnaces',
	'post_content' 	=>	'<span class="descriptionText">Provides reliable, efficient heating to keep your home warm and comfortable throughout the colder months. When an older furnace becomes unreliable or increasingly expensive to repair, replacing it with an Amana system can provide dependable performance, improved comfort, and reliable heating for years to come.</span>

	<p>Designed for quiet, efficient, and dependable heating, this furnace features ComfortBridge communicating technology, a two-stage gas valve, and a variable-speed ECM motor that automatically adjusts airflow based on your home\'s heating and cooling needs. CoolCloud Bluetooth diagnostics, a long-life SureStart igniter, built-in fault-code history, and quiet two-speed operation help simplify service while delivering consistent comfort. AHRI Certified and ETL Listed.</p>

	<ul>
		<li>80% AFUE</li>
		<li>Efficient and Quiet Variable-Speed Circulator Motor</li>
		<li>Quiet comfort</li>
		<li>Energy Efficient</li>
		<li><b>ComfortBridge™ Technology:</b> Bridges indoor comfort with smart technology, and cost-effectively operate at peak performance.</li>
	</ul>',
	'post_excerpt'	=>	'Provides reliable, efficient heating to keep your home warm and comfortable throughout the colder months. Provides dependable performance, improved comfort, and reliable heating for years to come.',
	'post_type'     =>	'products',
	'menu_order'  	=>  1210,
	'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'furnaces', 'product-class'=>'better'),
	'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_80furn.pdf?view=true'),
	'image_name'	=>	'Amana-F-02.webp'
),






	// Air Handlers
	array ( 'post_title'	=>	'AMST R-32 Air Handler',
			'post_content' 	=>	'<span class="descriptionText">This 9-Speed ECM Air Handler with Internal TXV efficiently circulates heated and cooled air throughout your home for consistent, year-round comfort. The flexible multi-position design helps provide dependable airflow and efficient performance when properly matched with an Amana heat pump.</span>

	<p>Available in 1½- to 5-ton capacities, this air handler offers flexible horizontal or vertical installation to accommodate a variety of home layouts. A factory-installed thermal expansion valve and durable all-aluminum evaporator coil support efficient performance, while the coil mounting track allows for quick repositioning and easier installation.</p>

	<ul>
		<li>All-Aluminum Coil</li>
	    <li>SmartFrame™ Sub-Structure</li>

	    <li>Internal factory-installed thermal expansion valves for cooling and heat pump applications</li>
	    <li>Direct-Drive, 9-speed ECM blower motor</li>
	    <li>Designed to work with R32 refrigerant to provide the best performance</li>
	</ul>',
			'post_excerpt'	=>	'This 9-Speed ECM Air Handler with Internal TXV efficiently circulates heated and cooled air throughout your home for consistent, year-round comfort. The flexible multi-position design helps provide dependable airflow and efficient performance when properly matched with an Amana heat pump.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1300,
			'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-handlers', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_ah.pdf?view=true'),
			'image_name'	=>	'Amana-AH-01.jpg'
	),





array ( 'post_title'	=>	'AMVT R-32 Air Handler',
		'post_content' 	=>	'<span class="descriptionText">Efficiently circulates heated or cooled air throughout your home\'s ductwork to help maintain consistent indoor comfort. Helps deliver efficient airflow, dependable performance, and comfortable temperatures throughout your home.</span>

<p>This Amana Brand air handler combines a variable-speed ECM blower motor with integrated ComfortBridge™ Technology to automatically optimize airflow, efficiency, humidity control, and overall comfort. Built-in Bluetooth commissioning and CoolCloud™ diagnostics simplify setup and service, while the system can automatically configure airflow and tonnage when operating in communicating mode. Additional features include a durable all-aluminum coil, factory-installed expansion valve, R-32 sensor, SmartFrame™ sub-structure, and compatibility with multi-stage heat pump and cooling systems. AHRI Certified and ETL Listed.</p>

<ul>
	<li>All-Aluminum Coil</li>
    <li>ComfortBridge™ Technology</li>
    <li>Factory mounted Expansion Valve</li>
    <li>SmartFrame™ Sub-Structure</li>

    <li>Factory installed R32 Sensor designed to last life span of coil</li>
    <li>Variable-speed ECM blower motor</li>
    <li>Provides adjustable low CFM for efficient fan-only operation</li>
    <li>Improved humidity and comfort control</li>
    <li>Built-in compatibility with multi-stage heat pump and cooling applications</li>
    <li><b>ComfortBridge™ Technology:</b> Bridges indoor comfort with smart technology, and cost-effectively operate at peak performance.</li>
</ul>',
		'post_excerpt'	=>	'Efficiently circulates heated or cooled air throughout your home\'s ductwork to help maintain consistent indoor comfort. Helps deliver efficient airflow, dependable performance, and comfortable temperatures throughout your home.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1300,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-handlers', 'product-class'=>'better'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_ah.pdf?view=true'),
		'image_name'	=>	'Amana-AH-01.jpg'
),






array ( 'post_title'	=>	'AHVE R-32 Air Handler',
		'post_content' 	=>	'<span class="descriptionText">Maintains consistent indoor comfort by efficiently circulating heated or cooled air throughout your home\'s ductwork. When properly matched with an Amana heat pump, the air handler helps your system deliver balanced airflow, efficient performance, and dependable year-round comfort.</span>

<p>Designed for efficient airflow and flexible installation, this air handler features a tightly sealed cabinet that minimizes air leakage and helps support overall system efficiency. Its horizontal or vertical configuration options, compact 21-inch depth for easier attic access, and durable DecaBDE-free thermoplastic drain pan make it adaptable to a wide range of home installations.</p>

<ul>
	<li>Variable-Speed Air Handler</li>
    <li>ECM-Based Air Handler with Internal EEV</li>
    <li>Factory installed R32 Sensor designed to last life span of coil</li>
    <li>All-aluminum evaporator coil</li>
    <li>Coil mounting track for quick repositioning</li>
    <li>Improved humidity and comfort control</li>
    <li>Compatible with Amana® brand smart thermostat</li>
</ul>',
		'post_excerpt'	=>	'Maintains consistent indoor comfort by efficiently circulating heated or cooled air throughout your home\'s ductwork and delivers balanced airflow, efficient performance, and dependable year-round comfort.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1310,
		'tax_input'		=>  array('product-brand'=>'amana', 'product-type'=>'air-handlers', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://www.amana-hac.com/pdfviewer.aspx?pdfurl=docs/default-source/default-document-library/pf-a_ah.pdf?view=true'),
		'image_name'	=>	'Amana-AH-01.jpg'
),

);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>