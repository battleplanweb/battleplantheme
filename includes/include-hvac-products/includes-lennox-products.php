<?php
/* Battle Plan Web Design - Add & Remove Lennox Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-lennox-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_lennox_products', 10 );
function add_lennox_products() {

	$brand = "lennox"; // lowercase
	$productImgAlt = "Lennox Heating & Cooling Product"; 


	
	$removeProducts = array('elite-series-el16xc1-air-conditioner', 'merit-series-16acx-air-conditioner', 'merit-series-ml14xc1-air-conditioner', 'elite-series-cx13-air-conditioner', 'merit-series-13acx-air-conditioner', 'elite-series-el16xp1-heat-pump', 'merit-series-ml16xp1-heat-pump', 'merit-series-16hpx-heat-pump', 'elite-series-el15xp1-heat-pump', 'elite-series-el18xpv-heat-pump', 'elite-series-xp20-heat-pump', 'merit-series-ml14xp1-heat-pump');
	


	$addProducts = array (
	
	// Air Conditioners
		array ( 
			'post_title'	=>	'Elite Series XC20 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Variable-capacity air conditioner, providing up to 22 SEER efficiency and true variable capacity.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Softer lines create an appliance-like appearance</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
</ul>', 
			'post_excerpt'	=>	'Variable-capacity air conditioner, providing up to 22 SEER efficiency and true variable capacity.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1000,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/75f22c60-237f-4490-8d89-1a179637442920B07_ELITE_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-01.jpg'		
		),

		
		
		array ( 
			'post_title'	=>	'Elite Series EL18XCV Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Truly variable. Truly digital. Provides up to 18 SEER efficiency.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Softer lines create an appliance-like appearance</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
</ul>', 
			'post_excerpt'	=>	'Truly variable. Truly digital. Provides up to 18 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1010,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/f06dcffb-d4df-4386-9397-a488132270a820B07_ELITE_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-01.jpg'		
		),
		
		
		
		array ( 
			'post_title'	=>	'Elite Series EL17XC1 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Mid-efficiency, single stage air conditioner that provides up to 18.6 SEER efficiency.</span>
<ul>
	<li>With efficiencies of up to 18.60 SEER, the EL17XC1 can deliver significant energy savings up to several hundred dollars per year.</li>
	<li>ENERGY STAR® Certified</li>
	<li>Precision-balanced, direct-drive fan to ensure smooth, quiet operation.</li>
	<li>Quantum Coil, a proprietary aluminum alloy exclusive to Lennox, designed to weather the harshest elements.</li>
	<li>Compatible with the iComfort E30 Smart Thermostat, giving you easier access, control, and comfort.</li>
</ul>', 
			'post_excerpt'	=>	'Mid-efficiency, single stage air conditioner that provides up to 18.6 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1020,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/644a3d11-4d8b-464f-86a2-fee0964d5ae220B07_ELITE_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-01.jpg'		
		),
		
		
		
		array ( 
			'post_title'	=>	'Merit Series 18XC2 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Mid-efficiency, two-stage air conditioner that provides affordable, efficient cooling up to 18 SEER.</span>
<ul>
	<li>Balanced comfort and efficiency, heating or cooling at two different speeds.</li>
	<li>Quantum Coil, a proprietary aluminum alloy exclusive to Lennox, designed to weather the harshest elements.</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>The ML18XC2 offers energy efficiencies of up to 18.00 SEER, delivering comfort and energy savings at a more affordable price.</li>
	<li>ENERGY STAR® Certified</li>
</ul>', 
			'post_excerpt'	=>	'Mid-efficiency, two-stage air conditioner that provides affordable, efficient cooling up to 18 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1030,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/be43ad60-3ee2-4c28-9f95-76dadb9e180920B10_MERIT_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-02.jpg'		
		),
		
		
		
		array ( 
			'post_title'	=>	'Merit Series ML17XC1 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Standard-efficiency, single-Stage Air Conditioner provides durability and up to 17 SEER efficiency.</span>
<ul>
	<li>The ML17XC1 offers energy efficiencies of up to 17.00 SEER, delivering comfort and energy savings at a more affordable price.</li>
	<li>ENERGY STAR® Certified</li>
	<li>Quantum Coil, a proprietary aluminum alloy exclusive to Lennox, designed to weather the harshest elements.</li>
	<li>Pairing your air conditioner with a variable-speed furnace or air handler allows you to decrease humidity for improved indoor air quality and comfort.</li>
</ul>', 
			'post_excerpt'	=>	'Standard-efficiency, single-Stage Air Conditioner provides durability and up to 17 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1040,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/34681ace-ceae-4d3e-b7d9-293382c44dc120B10_MERIT_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-02.jpg'		
		),
		
		
	// Heat Pumps
		array ( 
			'post_title'	=>	'Elite Series EL22XPV Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">High-efficiency, digitally-enabled, variable-capacity heat pump, providing up to 22 SEER efficiency.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>With efficiencies of up to 22.00 SEER2, the EL22XPV can deliver energy savings of several hundred dollars per year.</li>
	<li>Most Efficient of ENERGY STAR</li>
	<li>When paired with a digital furnace or air handler and a Lennox S40 or S30 Smart Thermostat, it can step into a fully communicating home comfort system.</li>
	<li>Quantum Coil, a proprietary aluminum alloy exclusive to Lennox, designed to weather the harshest elements.</li>
</ul>', 
			'post_excerpt'	=>	'High-efficiency, digitally-enabled, variable-capacity heat pump, providing up to 22 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1100,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/f988fd9e-907f-43eb-8fa8-e7b6487aa7b620B09_ELITE_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Elite Series XP20 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Variable-capacity heat pump, providing up to 20 SEER efficiency and true variable capacity.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Softer lines create an appliance-like appearance</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>Dual-fuel compatible with Lennox furnaces for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Variable-capacity heat pump, providing up to 20 SEER efficiency and true variable capacity.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1110,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/f988fd9e-907f-43eb-8fa8-e7b6487aa7b620B09_ELITE_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Elite Series EL18XPV Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Digitally-enabled, variable-capacity heat pump producing up to 20 SEER efficiency.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Softer lines create an appliance-like appearance</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>Dual-fuel compatible with Lennox furnaces for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Digitally-enabled, variable-capacity heat pump producing up to 20 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1120,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/f988fd9e-907f-43eb-8fa8-e7b6487aa7b620B09_ELITE_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Elite Series EL17XP1 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Mid-efficiency, single-stage heat pump producing up to 18.6 SEER efficiency.</span>
<ul>
	<li>With efficiencies of up to 18.60 SEER, the EL17XC1 can deliver significant energy savings up to several hundred dollars per year.</li>
	<li>ENERGY STAR® Certified</li>
	<li>Precision-balanced, direct-drive fan ensures smooth, quiet operation.</li>
	<li>Quantum Coil, a proprietary aluminum alloy exclusive to Lennox, designed to weather the harshest elements.</li>
	<li>Compatible with the Lennox E30 Smart Thermostat, giving you easier access, control, and comfort. </li>
</ul>', 
			'post_excerpt'	=>	'Mid-efficiency, single-stage heat pump producing up to 18.6 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1130,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/f988fd9e-907f-43eb-8fa8-e7b6487aa7b620B09_ELITE_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Merit Series ML17XP1 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Single-stage heat pump that delivers economical comfort with up to 18.6 SEER efficiency.</span>
<ul>
	<li>The ML17XP1 offers energy efficiencies of up to 18.60 SEER, delivering comfort and energy savings at a more affordable price.</li>
	<li>ENERGY STAR® Certified</li>
	<li>Quantum Coil, a proprietary aluminum alloy exclusive to Lennox, designed to weather the harshest elements.</li>
	<li>Pairing your air conditioner with a variable-speed furnace or air handler allows you to decrease humidity for improved indoor air quality and comfort.</li>
</ul>', 
			'post_excerpt'	=>	'Single-stage heat pump that delivers economical comfort with up to 18.6 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1140,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/81ce8134-9660-41d7-8fb9-2a092a04b16120B12_MERIT_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-02.jpg'		
		),
		
		
	// Furnaces
		array ( 
			'post_title'	=>	'Elite Series EL296V Furnace',
			'post_content' 	=>	'<span class="descriptionText">Variable-Speed, Two-Stage Gas Furnace offering an efficiency rating of up to 96% AFUE.</span>
<ul>
	<li>Insulated blower compartment to minimize heat loss and maximize efficiency</li>
	<li>Designed to integrate with the PureAir™ air purification system</li>
	<li>Sturdy burners reduce noise and maintain efficiency throughout the unit life</li>
	<li>Patented clamshell design reduces air leakage from the unit, sending more heated air to the home</li>
	<li>Additional design elements, like pre-cut holes, pre-bent flanges, and factory installed flue collars, make the installation quicker and more precise</li>
	<li>Dual-fuel compatible with Lennox® heat pumps for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Variable-Speed, Two-Stage Gas Furnace offering an efficiency rating of up to 96% AFUE.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1200,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/61b56e84-dcf7-410d-89ac-f1b741cc5df920B08_ELITE_GasFurnaceCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-F-01.jpg'		
		),		
		
		
		array ( 
			'post_title'	=>	'Elite Series EL296E Furnace',
			'post_content' 	=>	'<span class="descriptionText">Two-stage furnace with Power Saver™ technology, offering an efficiency rating of up to 96% AFUE.</span>
<ul>
	<li>Insulated blower compartment to minimize heat loss and maximize efficiency</li>
	<li>Designed to integrate with the PureAir™ air purification system</li>
	<li>Sturdy burners reduce noise and maintain efficiency throughout the unit life</li>
	<li>Patented clamshell design reduces air leakage from the unit, sending more heated air to the home</li>
	<li>Additional design elements, like pre-cut holes, pre-bent flanges, and factory installed flue collars, make the installation quicker and more precise</li>
	<li>Dual-fuel compatible with Lennox® heat pumps for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Two-stage furnace with Power Saver™ technology, offering an efficiency rating of up to 96% AFUE.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1210,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'furnaces', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/96e1af4f-97fb-4035-800d-3190b7ad0a3620B08_ELITE_GasFurnaceCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-F-01.jpg'		
		),	
		
		
		array ( 
			'post_title'	=>	'Merit Series ML296V Furnace',
			'post_content' 	=>	'<span class="descriptionText">Variable-speed gas furnace that heats the home evenly with fficiency rating of up to 96% AFUE.</span>
<ul>
	<li>Sturdy burners reduce noise and maintain efficiency throughout the unit life</li>
	<li>Patented clamshell design reduces air leakage from the unit, sending more heated air to the home</li>
	<li>Additional design elements, like pre-cut holes, pre-bent flanges, and factory installed flue collars, make the installation quicker and more precise</li>
	<li>Dual-fuel compatible with Lennox® heat pumps for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Variable-speed gas furnace that heats the home evenly with fficiency rating of up to 96% AFUE.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1220,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'furnaces', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/f7170f8b-5f84-47f3-ab06-fe44e696877820B11_MERIT_GasFurnaceCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-F-03.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Merit Series ML196E Furnace',
			'post_content' 	=>	'<span class="descriptionText">Single-stage, 96% fuel-efficient gas furnace with Power Saver™ constant-torque motor design.</span>
<ul>
	<li>Sturdy burners reduce noise and maintain efficiency throughout the unit life</li>
	<li>Patented clamshell design reduces air leakage from the unit, sending more heated air to the home</li>
	<li>Additional design elements, like pre-cut holes, pre-bent flanges, and factory installed flue collars, make the installation quicker and more precise</li>
	<li>Dual-fuel compatible with Lennox® heat pumps for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Single-stage, 96% fuel-efficient gas furnace with Power Saver™ constant-torque motor design.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1230,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'furnaces', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/72b2803a-33ab-4783-b5aa-266dafafc1ee20B11_MERIT_GasFurnaceCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-F-03.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Elite Series EL280E Furnace',
			'post_content' 	=>	'<span class="descriptionText">Two-stage gas furnace with constant-torque motor design for even temperatures. Efficiency rating of up to 80% AFUE.</span>
<ul>
	<li>Insulated blower compartment to minimize heat loss and maximize efficiency</li>
	<li>Designed to integrate with the PureAir™ air purification system</li>
	<li>Sturdy burners reduce noise and maintain efficiency throughout the unit life</li>
	<li>Patented clamshell design reduces air leakage from the unit, sending more heated air to the home</li>
	<li>Additional design elements, like pre-cut holes, pre-bent flanges, and factory installed flue collars, make the installation quicker and more precise</li>
	<li>Dual-fuel compatible with Lennox® heat pumps for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Two-stage gas furnace with constant-torque motor design for even temperatures. Efficiency rating of up to 80% AFUE.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1240,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/81194a78-514e-4282-8cb7-543d593ddad320B08_ELITE_GasFurnaceCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-F-02.jpg'		
		),	
		
		
		array ( 
			'post_title'	=>	'Merit Series ML180V Furnace',
			'post_content' 	=>	'<span class="descriptionText">Single-stage, 80% fuel-efficient gas furnace with variable-speed fan motor.</span>
<ul>
	<li>Sturdy burners reduce noise and maintain efficiency throughout the unit life</li>
	<li>Patented clamshell design reduces air leakage from the unit, sending more heated air to the home</li>
	<li>Additional design elements, like pre-cut holes, pre-bent flanges, and factory installed flue collars, make the installation quicker and more precise</li>
	<li>Dual-fuel compatible with Lennox® heat pumps for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Single-stage, 80% fuel-efficient gas furnace with variable-speed fan motor.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1250,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/59e3ddde-0c18-42cc-a74e-0eeca2755adb20B11_MERIT_GasFurnaceCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-F-04.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Merit Series ML180E Furnace',
			'post_content' 	=>	'<span class="descriptionText">Economical gas furnace with Power Saver™ technology, providing up to 80% AFUE efficiency.</span>
<ul>
	<li>Sturdy burners reduce noise and maintain efficiency throughout the unit life</li>
	<li>Patented clamshell design reduces air leakage from the unit, sending more heated air to the home</li>
	<li>Additional design elements, like pre-cut holes, pre-bent flanges, and factory installed flue collars, make the installation quicker and more precise</li>
	<li>Dual-fuel compatible with Lennox® heat pumps for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Economical gas furnace with Power Saver™ technology, providing up to 80% AFUE efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1260,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'furnaces', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/df7018f9-38ce-48f0-96c7-f8a2d806d31820B11_MERIT_GasFurnaceCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-F-04.jpg'		
),		
			
);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}

?>