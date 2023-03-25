<?php
/* Battle Plan Web Design - Mass Site Update */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-ruud-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_ruud_products', 10 );
function add_ruud_products() {

	$brand = "Ruud";
	$productImgAlt = "Ruud Heating & Cooling Product"; 

	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


	$addProducts = array (
	
	// Air Conditioners
		array ( 
			'post_title'	=>	'EcoNet™ Enabled Ultra® Series Variable Speed Air Conditioner (UA20)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>

    <li><strong>PlusOne&#8482; Energy</strong> Efficiency offers minimum of 20 SEER and 13 EER system performance across all capacities.</li>

    <li><strong>PlusOne&#8482; Expanded Valve Space</strong> - 3"-4"-5" service valve space - provides a minimum working area of 27-square inches for easier access</li>

    <li><strong>PlusOne&#8482; Triple Service Access</strong> - 15" wide, industry leading corner service access - makes repairs easier and faster. The two fastener removable corner allows optimal access to internal unit components. Individual louver panels come out once fastener is removed, for faster coil cleaning and easier cabinet reassembly</li>

    <li>EcoNet&#8482; Enabled product. The EcoNet Smart Home System provides advanced air &amp; water control for maximum energy savings and ideal comfort.</li>

    <li>New composite base pan - dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li>

    <li>Powder coat paint system - for a long lasting professional finish</li>

    <li>The Copeland Scroll&#8482; Variable Speed Compressor has a modulating technology which provides more precise temperature control, lower humidity and greater efficiency. The overdrive feature provides cooling load up to 107&#176;F.</li>

    <li>Modern cabinet aesthetics - increased curb appeal with visually appealing design</li>

    <li>Curved louver panels - provide ultimate coil protection, enhance cabinet strength, and increased cabinet rigidity</li>

    <li>Optimized fan orifice - optimizes airflow and reduces unit sound</li>

    <li>Rust resistant screws - confirmed through 1500-hour salt spray testing</li>

    <li>Diagnostic service window with two-fastener opening - provides access to the high and low pressure.</li>

    <li>External gauge port access - allows easy connection of "low-loss&#8221; gauge ports</li>

    <li>Single-row condenser coil (up thru 4 tons) - makes unit lighter and allows thorough coil cleaning to maintain "out of the box&#8221; performance</li>

    <li>35% fewer cabinet fasteners and fastener-free base - allow for faster access to internal components and hassle-free panel removal</li>

    <li>Service trays - hold fasteners or caps during service calls</li>

    <li>QR code - provides technical information on demand for faster service calls</li>

    <li>Fan motor harness with extra long wires allows unit top to be removed without disconnecting fan wire.</li>

    <li>High and low pressure standard on all models.</li>

</ul>', 
			'post_excerpt'	=>	'Cooling efficiencies up to 20.5 SEER and 14.5 EER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1000,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/BC9DC5E2-3C3C-43B6-A316-2050B0C0F3E0.pdf'),
			'image_name'	=>	'Ruud-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'EcoNet™ Enabled Achiever Plus® Series Two-Stage Air Conditioner (UA17)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>

<li>EcoNet™ Enabled product. The EcoNet Smart Home System provides advanced air &amp; water control for maximum energy savings and ideal comfort.</li>

<li>New composite base pan - dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li>

<li>Powder coat paint system - for a long lasting professional finish</li>

<li>The Two Stage Copeland Scroll™ Ultra Tech™ Compressor modulates between two capacity settings - 67% and 100% - providing more precise temperature control, lower humidity and greater efficiency in comparison to single stage compressors. It uses 70% fewer moving parts which also increases efficiency and reliability.</li>

<li>Modern cabinet aesthetics - increased curb appeal with visually appealing design</li>

<li>Curved louver panels - provide ultimate coil protection, enhance cabinet strength, and increased cabinet rigidity</li>

<li>Optimized fan orifice - optimizes airflow and reduces unit sound</li>

<li>Rust resistant screws - confirmed through 1500-hour salt spray testing</li>

<li>PlusOne™ <strong>Expanded Valve Space</strong> - 3"-4"-5" service valve space - provides a minimum working area of 27-square inches for easier access</li>

<li>PlusOne™ <strong>Triple Service Access</strong> - 15" wide, industry leading corner service access - makes repairs easier and faster. The two fastener removable corner allows optimal access to internal unit components. Individual louver panels come out once fastener is removed, for faster coil cleaning and easier cabinet reassembly</li>

<li>Diagnostic service window with two-fastener opening - provides access to the high and low pressure.</li>

<li>External gauge port access - allows easy connection of "low-loss" gauge ports</li>

<li>Single-row condenser coil - makes unit lighter and allows thorough coil cleaning to maintain "out of the box" performance35% fewer cabinet fasteners and fastener-free base - allow for faster access to internal components and hassle-free panel removal</li>

<li>Service trays - hold fasteners or caps during service calls</li>

<li>QR code - provides technical information on demand for faster service calls</li>

<li>Fan motor harness with extra long wires allows unit top to be removed without disconnecting fan wire.</li>

<li>High and low pressure standard on all models.</li>

</ul>	', 
			'post_excerpt'	=>	'Efficiencies up to 17 SEER/13 EER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1010,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/1F01E4BC-1B4D-4070-92E9-4A96BA1629D2.pdf'),
			'image_name'	=>	'Ruud-01.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'Achiever Series: Single Stage Air Conditioner (RA16)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul><li>New composite base pan – dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li><li> coat paint system – for a long lasting professional finish</li><li>Scroll compressor – uses 70% fewer moving parts for higher efficiency and increased reliability</li><li>Modern cabinet aesthetics – increased curb appeal with visually appealing design</li><li>Curved louver panels – provide ultimate coil protection, enhance cabinet strength, and increased cabinet rigidity</li><li>Optimized fan orifice – optimizes airflow and reduces unit sound</li><li>Rust resistant screws – confirmed through 1500-hour salt spray testing</li><li>PlusOne™ <strong>Expanded Valve Space</strong> – 3"-4"-5" service valve space – provides a minimum working area of 27-square inches for easier access</li><li>PlusOne™ <strong>Triple Service Access</strong> – 15" wide, industry leading corner service access – makes repairs easier and faster. The two fastener removable corner allows optimal access to internal unit components. Individual louver panels come out once fastener is removed, for faster coil cleaning and easier cabinet reassembly</li><li>Diagnostic service window with two-fastener opening – provides access to the high and low pressure</li><li>External gauge port access – allows easy connection of “low-loss” gauge ports</li><li>Single-row condenser coil – makes unit lighter and allows thorough coil cleaning to maintain “out of the box” performance</li><li>35% fewer cabinet fasteners and fastener-free base – allow for faster access to internal components and hassle-free panel removal</li><li>Service trays – hold fasteners or caps during service calls</li><li>QR code – provides technical information on demand for faster service calls</li><li>Fan motor harness with extra long wires allows unit top to be removed without disconnecting fan wire</li></ul>	', 
			'post_excerpt'	=>	'Efficiencies up to 16 SEER/13 EER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1020,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/6C1F8102-EACD-4C84-8239-FB0212B61C58.pdf'),
			'image_name'	=>	'Ruud-03.jpg'		
		),
		
				
		array ( 
			'post_title'	=>	'Achiever Series: Single Stage Air Conditioner (RA14)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>
<li>New composite base pan – dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li>
<li> coat paint system – for a long lasting professional finish</li>
<li>Scroll compressor – uses 70% fewer moving parts for higher efficiency and increased reliability</li>
<li>Modern cabinet aesthetics – increased curb appeal with visually appealing design</li>
<li>Curved louver panels – provide ultimate coil protection, enhance cabinet strength, and increased cabinet rigidity</li>
<li>Optimized fan orifice – optimizes airflow and reduces unit sound</li>
<li>Rust resistant screws – confirmed through 1500-hour salt spray testing</li>
<li>PlusOne™ <strong>Expanded Valve Space</strong> – 3"-4"-5" service valve space – provides a minimum working area of 27-square inches for easier access</li>
<li>PlusOne™ <strong>Triple Service Access</strong> – 15" wide, industry leading corner service access – makes repairs easier and faster. The two fastener removable corner allows optimal access to internal unit components. Individual louver panels come out once fastener is removed, for faster coil cleaning and easier cabinet reassembly</li>
<li>Diagnostic service window with two-fastener opening – provides access to the high and low pressure</li>
<li>External gauge port access – allows easy connection of “low-loss” gauge ports</li>
<li>Single-row condenser coil – makes unit lighter and allows thorough coil cleaning to maintain “out of the box” performance</li>
<li>35% fewer cabinet fasteners and fastener-free base – allow for faster access to internal components and hassle-free panel removal</li>
<li>Service trays – hold fasteners or caps during service calls</li>
<li>QR code – provides technical information on demand for faster service calls</li>
<li>Fan motor harness with extra long wires allows unit top to be removed without disconnecting fan wire</li>
</ul>', 
			'post_excerpt'	=>	'Efficiencies up to 15 SEER/12.5 EER ',
			'post_type'     =>	'products',
			'menu_order'  	=>  1030,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/67EAC91C-7A9B-4F83-8297-5585F19B9643.pdf'),
			'image_name'	=>	'Ruud-03.jpg'		
		),
		
		
	
	// Heat Pumps
		array ( 
			'post_title'	=>	'EcoNet™ Enabled Ultra® Series Variable Speed Heat Pump (UP20)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>
<li><strong>PlusOne™ Energy</strong> Efficiency offers minimum of 20 SEER and up to 11 HSPF system performance.</li>
<li><strong>PlusOne™ Expanded Valve Space</strong> – 3"-4"-5" service valve space – provides a minimum working area of 27-square inches for easier access</li>
<li><strong>PlusOne™ Triple Service Access</strong> – 15" wide, industry leading corner service access – makes repairs easier and faster. The two fastener removable corner allows optimal access to internal unit components. Individual louver panels come out once fastener is removed, for faster coil cleaning and easier cabinet reassembly</li>
<li>EcoNet™ Enabled product. The EcoNet Smart Home System provides advanced air &amp; water control for maximum energy savings and ideal comfort.</li>
<li>New composite base pan – dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li>
<li>Powder coat paint system – for a long lasting professional finish</li>
<li>The Copeland Scroll™ Variable Speed Compressor has a modulating technology which provides more precise temperature control, lower humidity and greater efficiency. The overdrive feature provides cooling load up to 107°F and heating load down to 7°F.</li>
<li>Modern cabinet aesthetics – increased curb appeal with visually appealing design</li>
<li>Equipped with electronic expansion valve to precisely control variable refrigerant flow.</li>
<li>Improved tubing design – reduces vibration and stress, making unit quieter and reducing opportunity for leaks</li>
<li>Optimized defrost characteristics - decrease defrosting and provide better home comfort</li>
<li>Optimized reversing valve sizing – improves shifting performance for quieter unit operation and increased life of the system</li>
<li>Enhanced mufflers – help to dissipate vibration energy for quieter unit operation</li>
<li>Integrated heat pump lift receptacle – allows standard CPVC stands to be inserted into the base</li>
<li>Curved louver panels – provide ultimate coil protection, enhance cabinet strength, and increased cabinet rigidity</li>
<li>Optimized fan orifice – optimizes airflow and reduces unit sound</li>
<li>Rust resistant screws – confirmed through 1500-hour salt spray testing</li>
<li>Diagnostic service window with two-fastener opening – provides access to the high and low pressure.</li>
<li>External gauge port access – allows easy connection of “low-loss” gauge ports</li>
<li>Single-row condenser coil (up thru 4 tons) – makes unit lighter and allows thorough coil cleaning to maintain “out of the box” performance</li>
<li>35% fewer cabinet fasteners and fastener-free base – allow for faster access to internal components and hassle-free panel removal</li>
<li>Service trays – hold fasteners or caps during service calls</li>
<li>QR code – provides technical information on demand for faster service calls</li>
<li>Fan motor harness with extra long wires allows unit top to be removed without disconnecting fan wire.</li>
<li>High and low pressure standard on all models.</li>
</ul>', 
			'post_excerpt'	=>	'Efficiencies up to 21.95 SEER/15.3 EER/11.5 HSPF',
			'post_type'     =>	'products',
			'menu_order'  	=>  1100,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/79445173-7019-461F-ADA9-99E864B5EB72.pdf'),
			'image_name'	=>	'Ruud-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'EcoNet™ Enabled Achiever Plus® Series Three-Stage Heat Pump (UP17)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>

    <li>EcoNet™ Enabled product. The EcoNet Smart Home System provides advanced air &amp; water control for maximum energy savings and ideal comfort [1].</li>

    <li>Energy Efficiency -&nbsp;Feature&nbsp;17-SEER&nbsp;&nbsp;cooling rating across all capacities [2].</li>

    <li>Copeland Scroll™ variable speed compressor. This inverter driven compressor provides three stages of heating and cooling operation for maximum comfort and energy savings. The overdrive feature in heating provides heating down to 7°F.</li>

    <li>Equipped with electronic expansion valve to precisely control variable refrigerant flow.</li>

    <li>New composite base pan – dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li>

    <li>Improved tubing design – reduces vibration and stress, making unit quieter and reducing opportunity for leaks</li>

    <li>Optimized defrost characteristics - decrease defrosting and provide better home comfort</li>

    <li>Powder coat paint system – for a long lasting professional finish</li>

    <li>Optimized reversing valve sizing – improves shifting performance for quieter unit operation and increased life of the system</li>

    <li>Enhanced mufflers – help to dissipate vibration energy for quieter unit operation</li>

    <li>Modern cabinet aesthetics – increased curb appeal with visually appealing design</li>

    <li>&nbsp;Curved louver panels – provide ultimate coil protection, enhance cabinet strength, and increased cabinet rigidity</li>

    <li>Optimized fan orifice – optimizes airflow and reduces unit sound</li>

    <li>Rust resistant screws – confirmed through 1500-hour salt spray testing</li>

    <li>PlusOne™ <strong>Expanded Valve Space</strong>– 3"-4"-5" service valve space – provides a minimum working area of 27-square inches for easier access</li>

    <li>Integrated heat pump lift receptacle – allows standard CPVC stands to be inserted into the base</li>

    <li>PlusOne™ <strong>Triple Service Access</strong> – 15" wide, industry leading corner service access – makes repairs easier and faster. The two fastener removable corner allows optimal access to internal unit components. Individual louver panels come out once fastener is removed, for faster coil cleaning and easier cabinet reassembly</li>

    <li>Diagnostic service window with two-fastener opening – provides access to the TXV valves and the heat pump reversing valve before opening the unit.</li>

    <li>External gauge port access – allows easy connection of “low-loss” gauge ports</li>

    <li>Single-row condenser coil – makes unit lighter and allows thorough coil cleaning to maintain “out of the box” performance</li>

    <li>35% fewer cabinet fasteners and fastener-free base – allow for faster access to internal components and hassle-free panel removal</li>

    <li>Service trays – hold fasteners or caps during service calls</li>

    <li>QR code – provides technical information on demand for faster service calls</li>
    <li>Fan motor harness with extra-long wires – allows unit top to be removed without disconnecting fan wire</li>

</ul>', 
			'post_excerpt'	=>	'Efficiencies up to 15 SEER/13 EER/9 HSPF',
			'post_type'     =>	'products',
			'menu_order'  	=>  1110,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/B94BFDDA-FA2E-498B-B2CF-8B92C38F7D37.pdf'),
			'image_name'	=>	'Ruud-12.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Achiever Series: Two Stage Heat Pump (RP16)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>

    <li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">New composite base pan – dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Improved tubing design – reduces vibration and stress, making unit quieter and reducing opportunity for leaks</li><li style="padding-left: 0px; transition: background-color 0.75s ease; background-color: transparent; margin-left: 0px; padding-bottom: 1em;">Optimized defrost characteristics - decrease defrosting and provide better home comfort</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Powder coat paint system – for a long lasting professional finish</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Optimized reversing valve sizing – improves shifting performance for quieter unit operation and increased life of the system</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Enhanced mufflers – help to dissipate vibration energy for quieter unit operation</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Scroll compressor – a sound abating feature added to the compressor significantly reduces noise when system transitions in and out of defrost mode</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Modern cabinet aesthetics – increased curb appeal with visually appealing design</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Curved louver panels – provide ultimate coil protection, enhance cabinet strength, and increased cabinet rigidity</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Optimized fan orifice – optimizes airflow and reduces unit sound</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Rust resistant screws – confirmed through 1500-hour salt spray testing</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">PlusOne™ Expanded Valve Space – 3"-4"-5" service valve space – provides a minimum working area of 27-square inches for easier access</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Integrated heat pump lift receptacle – allows standard CPVC stands to be inserted into the base</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">PlusOne™ Triple Service Access – 15" wide, industry leading corner service access – makes repairs easier and faster. The two fastener removable corner allows optimal access to internal unit components. Individual louver panels come out once fastener is removed, for faster coil cleaning and easier cabinet reassembly</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Diagnostic service window with two-fastener opening – provides access to the TXV valves and the heat pump reversing valve before opening the unit</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">External gauge port access – allows easy connection of “low-loss” gauge ports</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Single-row condenser coil – makes unit lighter and allows thorough coil cleaning to maintain “out of the box” performance</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">35% fewer cabinet fasteners and fastener-free base – allow for faster access to internal components and hassle-free panel removal</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Service trays – hold fasteners or caps during service calls</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">QR code – provides technical information on demand for faster service calls</li><li style="padding-left: 0px; transition: background-color 0.75s ease; margin-left: 0px; padding-bottom: 1em;">Fan motor harness with extra-long wires – allows unit top to be removed without disconnecting fan wire</li>

</ul>', 
			'post_excerpt'	=>	'Efficiencies up to 16 SEER/13 EER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1120,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/48CDF5E4-A60C-4FB1-BB6B-577BDFC2005A.pdf'),
			'image_name'	=>	'Ruud-03.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Achiever Series: Single Stage Heat Pump (RP15)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>

<li>New composite base pan – dampens sound, captures louver&nbsp;panels, eliminates corrosion and reduces number of fasteners needed</li>
    <li>Improved tubing design – reduces vibration and stress, making unit quieter and reducing opportunity for leaks</li>
    <li>Optimized defrost characteristics - decrease defrosting and provide better home comfort</li>
    <li>Powder coat paint system – for a long lasting professional finish</li>
    <li>Optimized reversing valve sizing – improves shifting performance for quieter unit operation and increased life of the system</li>
    <li>Enhanced mufflers – help to dissipate vibration energy for quieter unit operation</li>
    <li>Scroll compressor – a sound abating feature added to the compressor significantly reduces noise when system transitions in and out of defrost mode</li>
    <li>Modern cabinet aesthetics – increased curb appeal with visually appealing design</li>
    <li>Curved louver panels – provide ultimate coil protection, enhance cabinet strength, and increased cabinet rigidity</li>
    <li>Optimized fan orifice – optimizes airflow and reduces unit sound</li>
    <li>Rust resistant screws – confirmed through 1500-hour salt spray testing</li>
    <li>PlusOne™ <strong>Expanded Valve Space</strong> – 3"-4"-5" service valve space – provides a minimum working area of 27-square inches for easier access</li>
    <li>Integrated heat pump lift receptacle – allows standard CPVC stands to be inserted into the base</li>
    <li>PlusOne™ <strong>Triple Service Access</strong> – 15" wide, industry leading corner service access – makes repairs easier and faster. The two fastener removable corner allows optimal access to internal unit components. Individual louver panels come out once fastener is removed, for faster coil cleaning and easier cabinet reassembly</li>
    <li>Diagnostic service window with two-fastener opening – provides access to the TXV valves and the heat pump reversing valve before opening the unit</li>
    <li>External gauge port access – allows easy connection of “low-loss” gauge ports</li>
    <li>Single-row condenser coil – makes unit lighter and allows thorough coil cleaning to maintain “out of the box” performance</li>
    <li>35% fewer cabinet fasteners and fastener-free base – allow for faster access to internal components and hassle-free panel removal</li>
    <li>Service trays – hold fasteners or caps during service calls</li>
    <li>QR code – provides technical information on demand for faster service calls</li>
    <li>Fan motor harness with extra-long wires – allows unit top to&nbsp;be removed without disconnecting fan wire</li>

</ul>', 
			'post_excerpt'	=>	'Efficiencies up to 15 SEER/13 EER/9 HSPF',
			'post_type'     =>	'products',
			'menu_order'  	=>  1130,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/421453C4-3CE5-4F6A-874A-4264FF1B322B.pdf'),
			'image_name'	=>	'Ruud-13.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Achiever Series: Single Stage Heat Pump (RP14)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>

<li>New composite base pan – dampens sound, captures louver panels, eliminates corrosion and reduces number of fasteners needed</li>
    <li>Powder coat paint system – for a long lasting professional finish</li>
    <li>Scroll compressor – uses 70% fewer moving parts for higher efficiency and increased reliability</li>
    <li>Modern cabinet aesthetics – increased curb appeal with visually appealing design</li>
    <li>Curved louver panels – provide ultimate coil protection, enhance cabinet strength, and increased cabinet rigidity</li>
    <li>Optimized fan orifice – optimizes airflow and reduces unit sound</li>
    <li>Rust resistant screws – confirmed through 1500-hour salt spray testing</li>
    <li>PlusOne™ <span style="font-weight: bold;">Expanded Valve Space</span> –    3"-4"-5" service valve space – provides a minimum working area of    27-square inches for easier access</li>
    <li>PlusOne™ <span style="font-weight: bold;">Triple Service Access</span> –    15" wide, industry leading corner service access – makes repairs easier    and faster. The two fastener removable corner allows optimal access to    internal unit components. Individual louver panels come out once    fastener is removed, for faster coil cleaning and easier cabinet    reassembly</li>
    <li>Diagnostic service window with two-fastener opening – provides access to the high and low pressure.</li>
    <li>External gauge port access – allows easy connection of “low-loss” gauge ports</li>
    <li>Single-row condenser coil – makes unit lighter and allows thorough coil cleaning to maintain “out of the box” performance</li>
    <li>35% fewer cabinet fasteners and    fastener-free base – allow for faster access to internal components and    hassle-free panel removal</li>
    <li>Service trays – hold fasteners or caps during service calls</li>
    <li>QR code – provides technical information on demand for faster service calls</li>
    <li>Fan motor harness with extra long wires allows unit top to be removed without disconnecting fan wire.<br></li>

</ul>', 
			'post_excerpt'	=>	'Efficiencies up to 14 SEER/11.5 EER/9 HSPF',
			'post_type'     =>	'products',
			'menu_order'  	=>  1140,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/F44F58AE-5FD0-4A4F-AC37-EE3C410340AD.pdf'),
			'image_name'	=>	'Ruud-03.jpg'		
		),
		
	
	// Furnaces
		array ( 
			'post_title'	=>	'EcoNet™ Enabled Ultra® Series Variable Speed Multi Position Gas Furnace (U96V)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>
<li>96% residential gas furnace CSA certified</li>
    <li>4 way multi-poise design</li>
    <li>Two stages of operation to save energy and maintain optimal comfort level.</li>
    <li>Variable speed blower motor technology provides ultimate humidity control, quieter sound levels, and year round energy savings.</li>
    <li>EcoNet™ enabled HVAC Product</li>
    <li>PlusOne™ Diagnostics 7-Segment LED all units</li>
    <li>PlusOne™ Ignition System – DSI for reliability and longevity</li>
    <li>PlusOne™ Water Management System with patented Blocked Drain Sensor</li>
    <li>Heat exchanger is removable for    improved serviceability. Aluminized steel primary and stainless steel    secondary construction provide maximum corrosion resistance and thermal    fatigue reliability.</li>
    <li>Low profile “34 inch” cabinet ideal for space constrained installations.</li>
    <li>Blower Shelf design – serviceable in all furnace orientations</li>
    <li>Pre marked hoses – insures proper system drainage</li>
    <li>Vent with 2" or 3" PVC</li>
    <li>Replaceable Collector box</li>
    <li>Hemmed edges on cabinet and doors</li>
    <li>Quarter turn fasteners for tool less access</li>
    <li>Integrated control boards feature dip switches for easy system set up </li>
    <li>Self priming condensate trap</li>
    <li>Solid bottom included</li>
    <li>Compatible with single or two stage thermostats. For optimal performance a two stage thermostat is recommended.</li>
</ul>', 
			'post_excerpt'	=>	'96% residential gas furnace CSA certified',
			'post_type'     =>	'products',
			'menu_order'  	=>  1200,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/985BE6DB-6E12-4D66-A428-E1F3620EBF58.pdf'),
			'image_name'	=>	'Ruud-06.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'Achiever Plus Series: Up to 95% AFUE ECM Motor Multi Position Gas Furnace (R95T)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>

<li>95% residential gas furnace CSA certified</li>

    <li>4 way multi-poise design</li>

    <li>PlusOne™ Diagnostics 7-Segment LED all units</li>

    <li>PlusOne™ Ignition System – DSI for reliability and longevity</li>

    <li>PlusOne™ Water Management System with patented Blocked Drain Sensor</li>

    <li>Heat exchanger is removable for improved serviceability. Aluminized steel primary and stainless steel secondary construction provide maximum corrosion resistance and thermal fatigue reliability.</li>

    <li>Low profile “34 inch” cabinet ideal for space constrained installations.</li>

    <li>Blower Shelf design – serviceable in all furnace orientations</li>

    <li>Pre marked hoses – insures proper system drainage</li>

    <li>Vent with 2" or 3" PVC</li>

    <li>Replaceable Collector box</li>

    <li>Hemmed edges on cabinet and doors</li>

    <li>Constant Torque electrically commutated motor</li>

    <li>Quarter turn fasteners for tool less access</li>

    <li>Integrated control boards feature dip switches for easy system set up</li>

    <li>Self priming condensate trap</li>

    <li>Solid bottom included</li>

</ul>', 
			'post_excerpt'	=>	'95% residential gas furnace CSA certified',
			'post_type'     =>	'products',
			'menu_order'  	=>  1210,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'furnaces', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/E7B33437-EF6C-4C10-B8AF-1F39CCF0219E.pdf'),
			'image_name'	=>	'Ruud-07.jpg'		
		),	
		
		
		array ( 
			'post_title'	=>	'Achiever Plus Series Ultra Low NOx 80% AFUE R801T Upflow/Horizontal Gas Furnace',
			'post_content' 	=>	'<b>Features & Benefits:</b>


<ul>
<li>FIRST CERTIFIED, lowest emission furnace in the industry*</li>
<li>Industry - First, Ultra Low NOx technology </li>
<li>Environmentally friendly and responsible.</li>
<li>Eligible for the $500 SCAQMD CLEANair  furnace rebate**</li>
<li>Single Stage, Same 34" Cabinet Height, Simple Technology, Same Installation</li>
<li>For Upflow/Horizontal configurations only</li>
<li>80% residential Gas Furnaces CSA certified</li>
<li>PlusOne™ Diagnostics: 7-Segment LED for faster and more accurate diagnostics</li>
<li>PlusOne™ Ignition System: DSI for unmatched durability & years of worry-free operation</li>
<li>Removeable heat exchanger</li>
<li>Constant Torque electrically commutated motor</li>
<li>3-Phase induced draft blower motor</li>
<li>Pre-Mix Burner</li>
<li>Aluminized steel construction provides maximum corrosion resistance and thermal fatigue reliability</li>
<li>Solid doors provide quiet operation</li>
<li>Solid bottom</li>
<li>Insulated blower compartment</li>
<li>Low Profile 34" cabinet ideal for space constrained installations</li>
<li>Blower shelf design - serviceable in all furnace orientations</li>
<li>Hemmed edges on cabinets and doors</li>
<li>1/4 turn door knobs for tool less access</li>
<li>Integrated control board features dip switches for easy system set up</li>
<li>QR code for quick access to product information from your smartphone or tablet</li>
</ul>

<p><i>*The R801TA Ultra Low NOx Furnace was the first to be certified, in June 2016, by meeting the SCAQMD Rule 1111, as published in the SCAQMD Advisor, Volume 24 March/April 2017 issue.</i></p>

<p><i>** Refer to http://www.cleanairfurnacerebate.com for complete program and rebate details. </i></p>', 
			'post_excerpt'	=>	'FIRST CERTIFIED, lowest emission furnace in the industry',
			'post_type'     =>	'products',
			'menu_order'  	=>  1220,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/E7B33437-EF6C-4C10-B8AF-1F39CCF0219E.pdf'),
			'image_name'	=>	'Ruud-14.jpg'		
		),
			
	
	
	// Air Handlers
		array ( 
			'post_title'	=>	'High Efficiency Constant Torque (ECM) Motor (RH1T)',
			'post_content' 	=>	'<b>Features:</b>

<ul>
<li>RH1T feature a Constant Torque motor (ECM) which provides enhanced SEER performance with most Ruud outdoor units.</li>
<li>Versatile 4-way convertible design for upflow, downflow, horizontal left and horizontal right applications.</li>
<li>Factory-installed indoor coil. • Sturdy cabinet construction with 1.0 inch [25.4 mm] of foil faced insulation for excellent sound and insulating characteristics.</li>
<li>Field-installed auxiliary electric heater kits provide exact heat for indoor comfort. Kits include circuit breakers which meet U.L. and cUL requirements for service disconnect.</li>
<li>11/2 ton [5.3 kW] through 5 ton [17.6 kW] models are between 421/2 to 551/2 inches [1080 to 1410 mm] tall and 22 inches [559 mm] deep.</li>
<li>All models meet or exceed 330 to 400 CFM [156 to 189 L/s] per ton at .3 inches [.7 kPa] of external static pressure.</li>
<li>Enhanced airflow up to .7" external static pressure.</li>
<li>Evaporator is constructed of aluminum fins bonded to internally grooved aluminum tubing</li>
</ul>
', 
			'post_excerpt'	=>	'Efficiencies up to 16 SEER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1300,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=5895A634-864D-447C-934A-1C111EB2A715'),
			'image_name'	=>	'Ruud-11.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'High Efficiency - Two-Stage ECM Motor (RH2T)',
			'post_content' 	=>	'<b>Features:</b>

<ul>
<li>The RH2T is EcoNet Enabled (EEV Option): This allows the RH2T to directly communicate with the EcoNet Smart Home System.</li>
<li>Feature an Electronic Expansion Valve (EEV)</li>
<li>The RH2T features a Constant Torque Two-stage motor (ECM) which provides enhanced SEER performance with most Ruud outdoor units.</li>
<li>Evaporator is constructed of aluminum fins bonded to internally grooved aluminum tubing.</li>
<li>Versatile 4-way convertible design for upflow, downflow, horizontal left and horizontal right applications.</li>
<li>Factory-installed indoor coil.</li>
<li>Sturdy cabinet construction with 1.0 inch [25.4 mm] of foil faced insulation for excellent sound and insulating characteristics.</li>
<li>Field-installed auxiliary electric heater kits provide exact heat for indoor comfort. Kits include circuit breakers which meet U.L. and cUL requirements for service disconnect.</li>
<li>1½ ton [5.3 kW] through 5 ton [17.6 kW] models are between 42½ to 57 inches [1080 to 1448 mm] tall and 22 inches [559 mm] deep.</li>
<li>All models meet or exceed 330 to 400 CFM [156 to 189 L/s] per ton at .3 inches [.7 kPa] of external static pressure.</li>
<li>Enhanced airflow up to .7" external static pressure.</li>
<li>Suitable for application in mobile homes.</li>
</ul>
', 
			'post_excerpt'	=>	'Efficiencies up to 16 SEER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1310,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'furnaces', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=C5262978-82FE-4F80-836A-16415625B19E'),
			'image_name'	=>	'Ruud-11.jpg'		
		),	
		
		
		array ( 
			'post_title'	=>	'High Efficiency - ECM Motor (RH1V)',
			'post_content' 	=>	'<b>Features:</b>

<ul>
<li>Includes an energy efficient ECM® Motor, which in most applications, enhances the SEER rating of the outdoor unit. It also slowly ramps its speed up for quiet operation and enhanced customer satisfaction.</li>
<li>Versatile 4-way convertible design for upflow, downflow, horizontal left and horizontal right applications.</li>
<li>Nominal airflow up to 1.0" external static pressure.</li>
<li>Factory-installed indoor coil.</li>
<li>Sturdy cabinet construction with 1.0 inch [25.4 mm] of foil faced insulation for excellent sound and insulating characteristics.</li>
<li>Field-installed auxiliary electric heater kits provide exact heat for indoor comfort. Kits include circuit breakers which meet U.L. and cUL requirements for service disconnect.</li>
<li>Dip switch settings for selectable, customized cooling airflow over a wide variety of applications.</li>
<li>On-demand dehumidification terminal that adjusts airflow to help control humidity for unsurpassed comfort in cooling mode.</li>
<li>External filter required.</li>
<li>Evaporator coil is constructed of aluminum fins bonded to internally grooved aluminum tubing.</li>
</ul>', 
			'post_excerpt'	=>	'FIRST CERTIFIED, lowest emission furnace in the industry',
			'post_type'     =>	'products',
			'menu_order'  	=>  1320,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'furnaces', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/E7B33437-EF6C-4C10-B8AF-1F39CCF0219E.pdf'),
			'image_name'	=>	'Ruud-14.jpg'		
		),
		
		array ( 		
			'post_title'	=>	'High Efficiency Constant Torque (ECM) Motor (RH1T)',
			'post_content' 	=>	'<b>Features:</b>

<ul>
<li>RH1T feature a Constant Torque motor (ECM) which provides enhanced SEER performance with most Ruud outdoor units.</li>
<li>Versatile 4-way convertible design for upflow, downflow, horizontal left and horizontal right applications.</li>
<li>Factory-installed indoor coil. • Sturdy cabinet construction with 1.0 inch [25.4 mm] of foil faced insulation for excellent sound and insulating characteristics.</li>
<li>Field-installed auxiliary electric heater kits provide exact heat for indoor comfort. Kits include circuit breakers which meet U.L. and cUL requirements for service disconnect.</li>
<li>11/2 ton [5.3 kW] through 5 ton [17.6 kW] models are between 421/2 to 551/2 inches [1080 to 1410 mm] tall and 22 inches [559 mm] deep.</li>
<li>All models meet or exceed 330 to 400 CFM [156 to 189 L/s] per ton at .3 inches [.7 kPa] of external static pressure.</li>
<li>Enhanced airflow up to .7" external static pressure.</li>
<li>Evaporator is constructed of aluminum fins bonded to internally grooved aluminum tubing</li>
</ul>
', 
			'post_excerpt'	=>	'Efficiencies up to 16 SEER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1340,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=5895A634-864D-447C-934A-1C111EB2A715'),
			'image_name'	=>	'Ruud-11.jpg'		
		),		
			
		array ( 		
			'post_title'	=>	'EcoNet™ Enabled High Efficiency Modulating with CFM Motor (RHMV)',
			'post_content' 	=>	'<b>Features & Benefits:</b>

<ul>
<li>The RHMV is EcoNet Enabled: This allows the RHMV to directly communicate with the EcoNet Smart Home System.</li>
<li>The RHMV features an Electronic Expansion Valve (EEV)</li>
<li>Features a constant CFM variable speed motor (ECM) which provides enhanced SEER performance. The RHMV is rated with UA17, UA20 air conditioners and UP17, UP20 heat pumps.</li>
<li>Evaporator is constructed of aluminum fins bonded to internally grooved aluminum tubing.</li>
<li>Versatile 4-way convertible design for upflow, downflow, horizontal left and horizontal right applications.</li>
<li>Factory-installed indoor coil.</li>
<li>Sturdy cabinet construction with 1.0 inch [25.4 mm] of foil faced insulation for excellent sound and insulating characteristics.</li>
<li>Field-installed auxiliary electric heater kits provide exact heat for indoor comfort. Kits include circuit breakers which meet U.L. and cUL requirements for service disconnect.</li>
<li>11/2 ton [5.3 kW] through 5 ton [17.6 kW] models are between 421/2 to 57 inches [1080 to 1448 mm] tall and 22 inches [559 mm] deep.</li>
<li>All models meet or exceed 330 to 400 CFM [156 to 189 L/s] per ton at .3 inches [.7 kPa] of external static pressure.</li>
<li>Enhanced airflow up to .7" external static pressure.</li>
<li>Suitable for application in mobile homes.</li>
</ul>', 
			'post_excerpt'	=>	'Efficiencies up to 20.5 SEER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1350,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://s3.amazonaws.com/WebPartners/ProductDocuments/81D20B8A-D04E-4130-971E-FAE0CD32D6CE.pdf'),
			'image_name'	=>	'Ruud-11.jpg'		
		),	
		
		
			
	// Packaged Units
		array ( 
			'post_title'	=>	'RQPM-15 Package Heat Pump',
			'post_content' 	=>	'<p>The 2-5 ton Ruud 14 SEER RQPM & 15/16 SEER RQRM Dedicated Horizontal Package Heat Pump units feature earth-friendly R-410A refrigerant. This platform provides you with a full line of capacities that are each AHRI-certified. The design is certified by CSA International.</p>

<h3>The Scroll® Compressor</h3>

<p>All of our residential package units, regardless of efficiency level, feature the Scroll Compressor. These scroll compressors use a more advanced technology than traditional reciprocating compressors. The compressor is hermetically sealed and incorporates internal high temperature motor overload protection and durable insulation on the motor windings. The RQRM- A060 uses a two stage scroll compressor. It is externally mounted on rubber grommets to reduce vibration and noise. They have fewer moving parts, and they are quieter, more efficient, and longer lasting, and quiet operation.</p>

<h3>Durable Cabinet with Louvered Condenser Compartment</h3>

<p>Our louvered condenser compartment is the best in the industry for protecting the condenser coil against yard hazards and weather extremes. There is a One-piece top with a drip lip to help keep water off of the unit sides. Supply and return air openings feature a one-inch tall flange to prevent water migration into the ductwork. Access panels have “weep holes” and channels to further help manage water run-off. Side and down discharge options available on all models. (Shipped Downflow Standard). Easily accessible blower section complete with slide-out blower. Refrigerant connections are conveniently located for easy service diagnostics. Low pressure/loss of charge protection is standard on all models.</p>

<h3>Grille/Fan Motor Mount</h3>

<p>Our mount helps protect the fan motor from the elements for longer life. Its design dependability helps reduce vibration and noise.</p>

<h3>Easily Accessible Control Box</h3>

<p>Operational controls are “up front” for quick installation and easy service diagnostics.</p>

<h3>Matched Blower/Evaporator Coil Unit</h3>

<p>The blower is responsible for the flow of air into your duct work. It has been designed to match the MultiFlex® evaporator coil and other system components for maximum efficiency and quiet operation. In addition, the coil features rifled copper tubing and enhanced fins for improved efficiency.</p>

<h3>Service Fittings</h3>

<p>Exterior service fittings enable a serviceman to quickly determine unit operating conditions.</p>

<h3>Supplemental Electric Heating Strips</h3>

<p>Supplemental electric heat strips up to 20 kW are available (field or factory installed) for periods of extreme cold temperatures. Single point wiring simplifies installation.</p>

<h3>Other Benefits</h3>
<ul>
<li>Package heat pump utilizes demand defrost control which monitors the outdoor ambient temperature, outdoor coil temperature, and compressor run-time to determine when a defrost cycle is required.</li>
<li>Rugged base rails included for improved installation and handling.</li>
<li>Low pressure control standard on all models.</li>
<li>High pressure control standard on all 5ton models.</li>
</ul>', 
			'post_excerpt'	=>	'Up to 16 SEER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1400,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'packaged-units', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=46C0C310-C119-438E-9DE3-4E58D4953F23'),
			'image_name'	=>	'Ruud-09.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'RQPM-14 Package Heat Pump',
			'post_content' 	=>	'<p>The 2-5 ton Ruud 14 SEER RQPM & 15/16 SEER RQRM Dedicated Horizontal Package Heat Pump units feature earth-friendly R-410A refrigerant. This platform provides you with a full line of capacities that are each AHRI-certified. The design is certified by CSA International.</p>

<h3>The Scroll® Compressor</h3>

<p>All of our residential package units, regardless of efficiency level, feature the Scroll Compressor. These scroll compressors use a more advanced technology than traditional reciprocating compressors. The compressor is hermetically sealed and incorporates internal high temperature motor overload protection and durable insulation on the motor windings. The RQRM- A060 uses a two stage scroll compressor. It is externally mounted on rubber grommets to reduce vibration and noise. They have fewer moving parts, and they are quieter, more efficient, and longer lasting, and quiet operation.</p>

<h3>Durable Cabinet with Louvered Condenser Compartment</h3>

<p>Our louvered condenser compartment is the best in the industry for protecting the condenser coil against yard hazards and weather extremes. There is a One-piece top with a drip lip to help keep water off of the unit sides. Supply and return air openings feature a one-inch tall flange to prevent water migration into the ductwork. Access panels have “weep holes” and channels to further help manage water run-off. Side and down discharge options available on all models. (Shipped Downflow Standard). Easily accessible blower section complete with slide-out blower. Refrigerant connections are conveniently located for easy service diagnostics. Low pressure/loss of charge protection is standard on all models.</p>

<h3>Grille/Fan Motor Mount</h3>

<p>Our mount helps protect the fan motor from the elements for longer life. Its design dependability helps reduce vibration and noise.</p>

<h3>Easily Accessible Control Box</h3>

<p>Operational controls are “up front” for quick installation and easy service diagnostics.</p>

<h3>Matched Blower/Evaporator Coil Unit</h3>

<p>The blower is responsible for the flow of air into your duct work. It has been designed to match the MultiFlex® evaporator coil and other system components for maximum efficiency and quiet operation. In addition, the coil features rifled copper tubing and enhanced fins for improved efficiency.</p>

<h3>Service Fittings</h3>

<p>Exterior service fittings enable a serviceman to quickly determine unit operating conditions.</p>

<h3>Supplemental Electric Heating Strips</h3>

<p>Supplemental electric heat strips up to 20 kW are available (field or factory installed) for periods of extreme cold temperatures. Single point wiring simplifies installation.</p>

<h3>Other Benefits</h3>
<ul>
<li>Package heat pump utilizes demand defrost control which monitors the outdoor ambient temperature, outdoor coil temperature, and compressor run-time to determine when a defrost cycle is required.</li>
<li>Rugged base rails included for improved installation and handling.</li>
<li>Low pressure control standard on all models.</li>
<li>High pressure control standard on all 5ton models.</li>
</ul>
', 
			'post_excerpt'	=>	'Up to 16 SEER',
			'post_type'     =>	'products',
			'menu_order'  	=>  1410,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'packaged-units', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=46C0C310-C119-438E-9DE3-4E58D4953F23'),
			'image_name'	=>	'Ruud-10.jpg'		
		),	
		
				array ( 
			'post_title'	=>	'RGEA16 Gas/ Electric Package',
			'post_content' 	=>	'<p>All models feature Scroll® compressors for maximum efficiency and quiet operation. 5 Ton RGEA16 models feature UltraTech™ Scroll 2-Stage compressors with Comfort Alert™ diagnostics (see below), high/low pressure switches, and hard start kits.</p>

<ul>
<li>Louvered condenser compartment for protecting the coil against yard hazards and/or weather extremes.</li>
<li>One-piece top with a deep flange to help keep water out of the unit.</li>
<li>Supply and return air openings feature a one-inch tall flange to prevent water migration into the ductwork.</li>
<li>Access panels have “weep holes” and channels to further help manage water run-off.</li>
<li>Side and down discharge options available on all models. All models are shipped ready for horizontal application.</li>
<li>Easily accessible blower section complete with slide-out blower. The RGEA16 comes standard with variable speed motor with adjustable airflow in heating and cooling. The variable speed motor also comes with a interface that allows for dehumidification when used in continuous humidistat. The variable speed system is capable of 1 inch external static.</li>
<li>Refrigerant connections are conveniently located for easy service diagnostics.</li>
<li>Condenser and evaporator coils feature enhanced fins for better heat transfer and rifled copper tubing for greater efficiency.</li>
<li>Inside the easily accessible furnace compartment is the draft inducer motor. This motor is specially designed for quiet reliable operation. In addition to the draft inducer motor, the in-shot gas burners and manifold efficiently regulate the flow of gas for combustion. These new gas/electric units also feature direct-spark ignition and remote flame sensors for added reliability and efficiency.</li>
<li>All units feature an internal trap on the condensate line eliminating the need for installing an on-site external trap.</li>
<li>Easily accessible control box.</li>
<li>Single point wiring simplifies installation.</li>
<li>Our gas/electric package units feature a tubular heat exchanger design. Tubular heat exchangers are more efficient and durable than older-style clamshell heat exchangers. Stainless Steel Heat Exchanger is a standard feature on the RGEA16 and is backed by a limited lifetime warranty when installed in a residential application, and a 20 year warranty when installed in a commercial application. Two stage gas heat is standard on the RGEA16 models.</li>
<li>Thermal expansion valve standard on all models for superior superheat control, reliability, and energy efficiency at all operating conditions.</li>
<li>Filter drier standard on all models (not shown).</li>
<li>Rugged baserail included for improved installation and handling</li>
<li>Complete factory charged, wired and run tested.</li>
<li>Molded compressor plugs.</li>
</ul>', 
			'post_excerpt'	=>	'',
			'post_type'     =>	'products',
			'menu_order'  	=>  1420,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'packaged-units', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=388F66F0-A1B0-4E72-84F1-D97C4C0EF34D'),
			'image_name'	=>	'Ruud-09.jpg'		
		),	
		
		array ( 
			'post_title'	=>	'RGEA15 Gas/ Electric Package',
			'post_content' 	=>	'<p>All models feature Scroll® compressors for maximum efficiency and quiet operation. 5 Ton RGEA16 models feature UltraTech™ Scroll 2-Stage compressors with Comfort Alert™ diagnostics (see below), high/low pressure switches, and hard start kits.</p>

<ul>
<li>Louvered condenser compartment for protecting the coil against yard hazards and/or weather extremes.</li>
<li>One-piece top with a deep flange to help keep water out of the unit.</li>
<li>Supply and return air openings feature a one-inch tall flange to prevent water migration into the ductwork.</li>
<li>Access panels have “weep holes” and channels to further help manage water run-off.</li>
<li>Side and down discharge options available on all models. All models are shipped ready for horizontal application.</li>
<li>Easily accessible blower section complete with slide-out blower. The RGEA16 comes standard with variable speed motor with adjustable airflow in heating and cooling. The variable speed motor also comes with a interface that allows for dehumidification when used in continuous humidistat. The variable speed system is capable of 1 inch external static.</li>
<li>Refrigerant connections are conveniently located for easy service diagnostics.</li>
<li>Condenser and evaporator coils feature enhanced fins for better heat transfer and rifled copper tubing for greater efficiency.</li>
<li>Inside the easily accessible furnace compartment is the draft inducer motor. This motor is specially designed for quiet reliable operation. In addition to the draft inducer motor, the in-shot gas burners and manifold efficiently regulate the flow of gas for combustion. These new gas/electric units also feature direct-spark ignition and remote flame sensors for added reliability and efficiency.</li>
<li>All units feature an internal trap on the condensate line eliminating the need for installing an on-site external trap.</li>
<li>Easily accessible control box.</li>
<li>Single point wiring simplifies installation.</li>
<li>Our gas/electric package units feature a tubular heat exchanger design. Tubular heat exchangers are more efficient and durable than older-style clamshell heat exchangers. Stainless Steel Heat Exchanger is a standard feature on the RGEA16 and is backed by a limited lifetime warranty when installed in a residential application, and a 20 year warranty when installed in a commercial application. Two stage gas heat is standard on the RGEA16 models.</li>
<li>Thermal expansion valve standard on all models for superior superheat control, reliability, and energy efficiency at all operating conditions.</li>
<li>Filter drier standard on all models (not shown).</li>
<li>Rugged baserail included for improved installation and handling</li>
<li>Complete factory charged, wired and run tested.</li>
<li>Molded compressor plugs.</li>
<li>A double sloped evaporator coil drain pan assures all water is removed from the unit to improve indoor air quality.</li>
</ul>', 
			'post_excerpt'	=>	'',
			'post_type'     =>	'products',
			'menu_order'  	=>  1430,
			'tax_input'		=>  array('product-brand'=>'ruud', 'product-type'=>'packaged-units', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://cdn.globalimageserver.com/FetchDocument.aspx?ID=F556179D-7CAD-47E9-BBBD-057A7B11FD07'),
			'image_name'	=>	'Ruud-09.jpg'		
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
				
		/*require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $attachmentID, $imageFile );
		wp_update_attachment_metadata( $attachmentID, $attach_data );
		update_post_meta( $attachmentID, '_wp_attachment_image_alt', $productImgAlt );
		wp_set_object_terms( $attachmentID, array('Products'), 'image-categories', true );*/
		
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