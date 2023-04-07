<?php
/* Battle Plan Web Design - Add & Remove Rheem Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-rheem-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_rheem_products', 10 );
function add_rheem_products() {

	$brand = "rheem"; // lowercase
	$productImgAlt = "Rheem Heating & Cooling Product"; 


	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


	$addProducts = array (
	
	// Air Conditioners
		array ( 
			'post_title'	=>	'Prestige Series Air Conditioner (RA20)',
			'post_content' 	=>	'<span class="descriptionText"></span>
<ul>
 <li><b>PlusOne Energy</b> Cooling efficiency: 20.5 SEER / 14.5 EER</li>
 <li><b>PlusOne Triple Service Access</b> 15" wide, industry leading corner service access makes repairs easier and faster.</li>
 <li><b>EcoNet Enabled</b> product. The EcoNet Smart Home System provides advanced air & water control for maximum energy savings and ideal comfort.</li>
 <li>Powder coat paint system for a long lasting professional finish</li>
 <li><b>The Copeland Scroll Variable Speed Compressor</b> has a modulating technology which provides more precise temperature control, lower humidity and greater efficiency. The overdrive feature provides cooling load up to 107F</li>
 <li>Modern cabinet aesthetics increased curb appeal with visually appealing design</li>
 <li>Optimized fan orifice optimizes airflow and reduces unit sound</li>
 <li>Rust resistant screws confirmed through 1500-hour salt spray testing</li>
 <li>High and low pressure standard on all models.</li>
 <li><b>Product Warranty:</b> 10 Years</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'This variable speed unit is EcoNet enabled and offers a cooling efficiency of 20.5 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1000,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/49383578-127B-411E-9348-C5FA254C2B19.pdf'),
			'image_name'	=>	'rheem-ac-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Classic Series Air Conditioner (RA17)',
			'post_content' 	=>	'<span class="descriptionText"></span>
<ul>
 <li><b>EcoNet Enabled</b> product. The EcoNet Smart Home System provides advanced air & water control for maximum energy savings and ideal comfort.</li></li>
 <li>Powder coat paint system - for a long lasting professional finish</li>
 <li><b>The Two Stage Copeland Scroll Ultra Tech Compressor</b> modulates between two capacity settings - 67% and 100% - providing more precise temperature control, lower humidity and greater efficiency in comparison to single stage compressors. It uses 70% fewer moving parts which also increases efficiency and reliability.</li>
 <li>Modern cabinet aesthetics - increased curb appeal with visually appealing design</li>
 <li>Optimized fan orifice - optimizes airflow and reduces unit sound</li>
 <li>Single-row condenser coil - makes unit lighter and allows thorough coil cleaning to maintain "out of the box" performance</li>
 <li>High and low pressure standard on all models</li>
 <li><b>Product Warranty:</b> 10 Years</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'This two-stage unit is EcoNet enabled and offers a cooling efficiency of 17 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1010,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/1031A120-08CA-45D2-A6AC-4D7FA64D3DC0.pdf'),
			'image_name'	=>	'rheem-ac-02.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'Classic Series Air Conditioner (RA16)',
			'post_content' 	=>	'<span class="descriptionText"></span>
<ul>
 <li>New composite base pan dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li>
 <li>Powder coat paint system for a long lasting professional finish</li>
 <li>Scroll compressor uses 70% fewer moving parts for higher efficiency and increased reliability</li>
 <li>Modern cabinet aesthetics increased curb appeal with visually appealing design</li>
 <li>Optimized fan orifice optimizes airflow and reduces unit sound</li>
 <li>Rust resistant screws confirmed through 1500-hour salt spray testing</li>
 <li><b>PlusOne Triple Service Access</b> 15" wide, industry leading corner service access makes repairs easier and faster.</li>
 <li>Single-row condenser coil makes unit lighter and allows thorough coil cleaning to maintain out of the box performance</li>
 <li>Fan motor harness with extra long wires allows unit top to be removed without disconnecting fan wire.</li>
 <li><b>Product Warranty:</b> 10 Years</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'The Scroll compressor allows for efficiencies of up to 16 SEER, while composite base pan makes for quieter operation.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1020,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/476AACE1-70EA-41AF-B245-2DC47B69F7DC.pdf'),
			'image_name'	=>	'rheem-ac-03.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'Select Series Air Conditioner (WA14)',
			'post_content' 	=>	'<span class="descriptionText">These units offer comfort and dependability for single, multi-family and light commercial applications.</span>
<ul>
 <li>Designed for ground level or rooftop installations</li>
 <li>Painted louvered steel cabinet</li>
 <li>Condenser coils constructed with copper tubing and enhanced aluminum fins</li>
 <li>Grille/Motor mount for quiet fan operation</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'These units offer comfort and dependability for single, multi-family and light commercial applications.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1030,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/61B7CC7B-0E80-4098-BF8C-D9D7F2A82B1E.pdf'),
			'image_name'	=>	'rheem-ac-04.jpg'		
		),
		
	
	// Heat Pumps
		array ( 
			'post_title'	=>	'Prestige Series Heat Pump (RP20)',
			'post_content' 	=>	'<span class="descriptionText"></span>
<ul>
 <li><b>PlusOne Energy</b> Cooling efficiency: 21.95 SEER/15.3 EER/11.5 HSPF</li>
 <li><b>PlusOne Triple Service Access</b> 15" wide, industry leading corner service access makes repairs easier and faster.</li>
 <li><b>EcoNet Enabled</b> product. The EcoNet Smart Home System provides advanced air & water control for maximum energy savings and ideal comfort.</li>
 <li>Powder coat paint system for a long lasting professional finish</li>
 <li><b>The Copeland Scroll Variable Speed Compressor</b> has a modulating technology which provides more precise temperature control, lower humidity and greater efficiency. The overdrive feature provides cooling load up to 107F and heating load down to 7F.</li>
 <li>Modern cabinet aesthetics increased curb appeal with visually appealing design</li>
 <li>Equipped with electronic expansion valve to precisely control variable refrigerant flow.</li>
 <li>Improved tubing design reduces vibration and stress, making unit quieter and reducing opportunity for leaks</li>
 <li>Optimized defrost characteristics - decrease defrosting and provide better home comfort</li>
 <li>Optimized reversing valve sizing improves shifting performance for quieter unit operation and increased life of the system</li>
 <li>Enhanced mufflers help to dissipate vibration energy for quieter unit operation</li>
 <li>Optimized fan orifice optimizes airflow and reduces unit sound</li>
 <li>Rust resistant screws confirmed through 1500-hour salt spray testing</li>
 <li>Single-row condenser coil (up thru 4 tons) makes unit lighter and allows thorough coil cleaning to maintain out of the box performance</li>
 <li>Fan motor harness with extra long wires allows unit top to be removed without disconnecting fan wire.</li>
 <li>High and low pressure standard on all models.</li>
 <li><b>Product Warranty:</b> 10 Years</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'This variable speed unit is EcoNet enabled and offers a cooling efficiency of 21.95 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1100,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/0A46666E-215B-407B-8807-83AE12BB1E73.pdf'),
			'image_name'	=>	'rheem-hp-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Classic Series Heat Pump (RP16)',
			'post_content' 	=>	'<span class="descriptionText"></span>
<ul>
 <li>New composite base pan dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li>
 <li>Improved tubing design reduces vibration and stress, making unit quieter and reducing opportunity for leaks</li>
 <li>Optimized defrost characteristics - decrease defrosting and provide better home comfort</li>
 <li>Powder coat paint system for a long lasting professional finish</li>
 <li>Optimized reversing valve sizing improves shifting performance for quieter unit operation and increased life of the system</li>
 <li>Enhanced mufflers help to dissipate vibration energy for quieter unit operation</li>
 <li>Scroll compressor a sound abating feature added to the compressor significantly reduces noise when system transitions in and out of defrost mode</li>
 <li>Modern cabinet aesthetics increased curb appeal with visually appealing design</li>
 <li>Optimized fan orifice optimizes airflow and reduces unit sound</li>
 <li>Rust resistant screws confirmed through 1500-hour salt spray testing</li>
 <li>Integrated heat pump lift receptacle allows standard CPVC stands to be inserted into the base</li>
 <li>Single-row condenser coil makes unit lighter and allows thorough coil cleaning to maintain out of the box performance</li>
 <li>Fan motor harness with extra-long wires allows unit top to be removed without disconnecting fan wire.</li>
 <li><b>Product Warranty:</b> 10 Years</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'Utilizing a two-stage scroll compressor, this unit offers a cooling efficiency of 16 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1110,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/8C6C7C5D-B8F8-4253-9719-BC81C952D361.pdf'),
			'image_name'	=>	'rheem-hp-02.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Classic Series Heat Pump (RP14)',
			'post_content' 	=>	'<span class="descriptionText"></span>
<ul>
 <li>New composite base pan dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li>
 <li>Powder coat paint system for a long lasting professional finish</li>
 <li>Scroll compressor uses 70% fewer moving parts for higher efficiency and increased reliability</li>
 <li>Modern cabinet aesthetics increased curb appeal with visually appealing design</li>
 <li>Optimized fan orifice optimizes airflow and reduces unit sound</li>
 <li>Rust resistant screws confirmed through 1500-hour salt spray testing</li>
 <li>Single-row condenser coil makes unit lighter and allows thorough coil cleaning to maintain out of the box performance</li>
 <li>Fan motor harness with extra long wires allows unit top to be removed without disconnecting fan wire.</li>
 <li><b>Product Warranty:</b> 10 Years</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'With a scroll compressor and composite base pan for quieter operation, this unit produces efficiencies of up to 15 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1120,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/1326AAD6-C9FD-49EA-8B7A-5A7A321D59BF.pdf'),
			'image_name'	=>	'rheem-hp-03.jpg'		
		),
		
	
	// Furnaces
		array ( 
			'post_title'	=>	'Prestige Series Gas Furnace (R97V)',
			'post_content' 	=>	'<span class="descriptionText">Industry-first patented features and 360+1 engineering make the Rheem R97V Prestige Series Gas Furnace a smart option. From top to bottom, inside and out, and every angle in between, weve thought of everything to bring you efficient and reliable indoor
comfort.</span>
<ul>
 <li><b>Enjoy greater comfort and performance</b> thanks to a two-stage operation that offers a more consistent indoor environment keeping cold spots to a minimum. The two-stage heating design primarily operates on low-speed, only temporarily switching to high during peak cold-weather conditions.</li>

 <li><b>Quiet and efficient comfort</b> is what you get with a Rheem Prestige Series Gas Furnace. The new patented heat exchanger design provides improved airflow, which reduces operating sound by 20%**. The variable-speed ECM motor also contributes to a quiet, more efficient operation. Its engineered to provide better humidity control while using less power. Overall, this is one of the quietest furnaces on the market. And because its high-efficiency, your monthly energy bill will benefit, too.</li>

 <li><b>Reliability</b> is of the utmost importance when it comes to your homes comfort. Our Prestige Series Gas Furnace comes standard with the Rheem-exclusive PlusOne Ignition System. This proven direct spark ignition (DSI) is one of the most reliable ignition systems available today. Used exclusively by Rheem in the Heating and Cooling industry, this proven technology is also used on ovens and stoves appliances that you rely on daily to ignite.</li>

 <li><b>Protect your home</b> with our PlusOne Water Management System. The industrys first blocked drain sensor will shut off your furnace when a drain is blocked, preventing water spillage and potential water damage to your home. All this and one of the best warranties in the industry translate to fewer repair bills and more cozy nights at home.</li>

 <li><b>Easy installation and maintenance</b> features benefit consumers, too. This means savings on installation costs and maintenance. The Rheem Prestige Series Gas Furnace is designed with PlusOne Diagnostics our industry-first, 7-segment LED display that makes service calls quick and easy.</li>

 <li><b>Reap Savings Through Maximum Efficiency.</b> The R96V Rheem Prestige Series Gas Furnace is not only high-performing, it also saves you energy and money. A 96% AFUE rating may qualify you for local and/or utility rebates. Its everything you need in a gas furnace and more.</li>

 <li><b>Energy Savings</b> are a welcome bonus to any heating system. Maximum airflow and a patented heat exchange design mean the Rheem Prestige Series Gas Furnace uses fuel efficiently and economically. It gets an ENERGY STAR rating for maintaining comfortable temperatures while reducing energy consumption and lowering utility bills.</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'EcoNet Enabled gas furnace with ECM Motor and Rheem-Exclusive PlusOne Ignition System.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1200,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/5178A6B8-64EC-4F87-9E78-D624A684BDB2.pdf'),
			'image_name'	=>	'rheem-f-01.jpg'		
		),
		
		

		array ( 
			'post_title'	=>	'Prestige Series Gas Furnace (R96V)',
			'post_content' 	=>	'<span class="descriptionText">Industry-first patented features and 360+1 engineering make the Rheem R96V Prestige Series Gas Furnace a smart option. From top to bottom, inside and out, and every angle in between, weve thought of everything to bring you efficient and reliable indoor
comfort.</span>
<ul>
 <li><b>Enjoy greater comfort and performance</b> thanks to a two-stage operation that offers a more consistent indoor environment keeping cold spots to a minimum. The two-stage heating design primarily operates on low-speed, only temporarily switching to high during peak cold-weather conditions.</li>

 <li><b>Quiet and efficient comfort</b> is what you get with a Rheem Prestige Series Gas Furnace. The new patented heat exchanger design provides improved airflow, which reduces operating sound by 20%**. The variable-speed ECM motor also contributes to a quiet, more efficient operation. Its engineered to provide better humidity control while using less power. Overall, this is one of the quietest furnaces on the market. And because its high-efficiency, your monthly energy bill will benefit, too.</li>

 <li><b>Reliability</b> is of the utmost importance when it comes to your homes comfort. Our Prestige Series Gas Furnace comes standard with the Rheem-exclusive PlusOne Ignition System. This proven direct spark ignition (DSI) is one of the most reliable ignition systems available today. Used exclusively by Rheem in the Heating and Cooling industry, this proven technology is also used on ovens and stoves appliances that you rely on daily to ignite.</li>

 <li><b>Protect your home</b> with our PlusOne Water Management System. The industrys first blocked drain sensor will shut off your furnace when a drain is blocked, preventing water spillage and potential water damage to your home. All this and one of the best warranties in the industry translate to fewer repair bills and more cozy nights at home.</li>

 <li><b>Easy installation and maintenance</b> features benefit consumers, too. This means savings on installation costs and maintenance. The Rheem Prestige Series Gas Furnace is designed with PlusOne Diagnostics our industry-first, 7-segment LED display that makes service calls quick and easy.</li>

 <li><b>Reap Savings Through Maximum Efficiency.</b> The R96V Rheem Prestige Series Gas Furnace is not only high-performing, it also saves you energy and money. A 96% AFUE rating may qualify you for local and/or utility rebates. Its everything you need in a gas furnace and more.</li>

 <li><b>Energy Savings</b> are a welcome bonus to any heating system. Maximum airflow and a patented heat exchange design mean the Rheem Prestige Series Gas Furnace uses fuel efficiently and economically. It gets an ENERGY STAR rating for maintaining comfortable temperatures while reducing energy consumption and lowering utility bills.</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'Industry-first patented features and 360+1 engineering make the Rheem R96V Prestige Series Gas Furnace a smart option.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1210,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/RheemPublic/PublicDocuments/7556874A-7D7D-4C89-AC67-AB49701A0A67.pdf'),
			'image_name'	=>	'rheem-f-02.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'Classic Series Gas Furnace (R802T)',
			'post_content' 	=>	'<span class="descriptionText">Industry-first patented features and 360+1 engineering make the Rheem R96V Prestige Series Gas Furnace a smart option. From top to bottom, inside and out, and every angle in between, weve thought of everything to bring you efficient and reliable indoor
comfort.</span>

<ul>
 <li>80% residential Gas Furnace CSA certified</li>
 <li>Two stages of operation to save energy and maintain optimal comfort level.</li>
 <li>Constant Torque Electrically Commutated motor</li>
 <li>3 way multi poise design UF / HZ</li>
 <li><b>PlusOne™ Ignition System</b> – DSI for reliability and longevity</li>
 <li>Solid doors provide quiet operation</li>
 <li>Integrated Control board features dip switches for easy system set up</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'This gas furnace has three way multi-poise design, constant torque motor and is CSA certified.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1220,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'furnaces', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/5D88FD66-A74E-4753-9FA6-E97C1B82E9ED.pdf'),
			'image_name'	=>	'rheem-f-03.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'Classic Series Oil Furnace (ROLA-070E)',
			'post_content' 	=>	'<span class="descriptionText">The Rheem Classic Series upflow oil furnace is for installation in properly ventilated utility rooms, closets or alcoves. </span>

<ul>
 <li><b>Direct Drive</b> blower assemblies for heating and air conditioning applications</li>
 <li>10-gauge primary and 14-gauge secondary heat exchanger</li>
 <li>Efficiencies up to 85.9%</li>
 <li>All furnaces have standard Honeywell controls</li>
 <li>ECM blower assemblies for heating and air conditioning applications</li>
 <li>ECM controls set for 2-stage air conditioning, 2-stage heat pump back up</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'This upflow oil furnace is for installation in properly ventilated utility rooms, closets or alcoves. ',
			'post_type'     =>	'products',
			'menu_order'  	=>  1230,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://globalimageserver.com/FetchDocument.aspx?ID=4cda6dea-1397-4671-9a1a-82b6d419b531'),
			'image_name'	=>	'rheem-f-04.jpg'		
		),	
		
		
		// Air Handlers
		array ( 
			'post_title'	=>	'Hydronic Air Handler (RW1T)',
			'post_content' 	=>	'<span class="descriptionText"></span>
<ul>
 <li>Integrated control board features diagnostics, manages all operational functions and provides hookups for humidifier and electronic air cleaner</li>
 <li>An insulated blower compartment makes it one of the quietest hydronic air handlers on the market today</li>
 <li>Pre-paint galvanized steel cabinet</li>
 <li>Transformer and control fuse protection</li>
 <li>Variety of cooling coils and plenums designed to use with the Hydronic Air Handler are available as optional accessories for air conditioning models</li>
 <li>Stainless steel water pump</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'Integrated Heating utilizing a Hydronic heating coil and constant torque motor.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1300,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'air-handlers', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=F2CC23D7-F96C-4A5F-A1B6-1B87740A39C3'),
			'image_name'	=>	'rheem-ah-01.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'High Efficiency Air Handler (RH2V)',
			'post_content' 	=>	'<span class="descriptionText"></span>
<ul>
 <li>Includes an energy efficient ECM Motor, which in most applications, enhances the SEER rating of the outdoor unit. It also slowly ramps its speed up for quiet operation and enhanced customer satisfaction.</li>
 <li>Versatile 4-way convertible design for upflow, downflow, horizontal left and horizontal right applications.</li>
 <li>Factory-installed indoor coil</li>
 <li>Dip switch settings for selectable, customized cooling airflow over a wide variety of applications.</li>
 <li>On-demand dehumidification terminal that adjusts airflow to help control humidity for unsurpassed comfort in cooling mode.</li>
 <li>External filter required.</li>
 <li>Evaporator coil is constructed of aluminum fins bonded to internally grooved aluminum tubing.</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'High Efficiency, constant CFM two-stage air flow with up to 18 SEER performance.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1310,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'air-handlers', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=DB3BF827-58EA-4924-A950-950E9C8BE57E'),
			'image_name'	=>	'rheem-ah-02.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'Front or Bottom Return Air Handler (RF1P)',
			'post_content' 	=>	'<span class="descriptionText"></span>
<ul>
 <li>Front or Bottom Return</li>
 <li>Flow Check Piston for cooling or heat pump operation</li>
 <li>AHRI Certified</li>
 <li>UL Certified</li>
 <li>Dual Voltage Direct Drive Blower with multi-speed motor</li>
 <li>Optional Factory Installed Condensate Float Switch which shuts off the outdoor unit in event the condensate pan becomes clogged</li>
</ul>
<span class="disclaimerText"></span>', 
			'post_excerpt'	=>	'All aluminum coil provides PSC single-stage airflow, resulting in efficiencies up to 15.5 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1320,
			'tax_input'		=>  array('product-brand'=>'rheem', 'product-type'=>'air-handlers', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=89ED6C81-3D16-49B0-81D9-B49B631FB141'),
			'image_name'	=>	'rheem-ah-03.jpg'		
),		
			
);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>