<?php
/* Battle Plan Web Design - Mass Site Update */
 
add_action( 'wp_loaded', 'add_lennox_products', 10 );
function add_lennox_products() {

	$brand = "lennox";
	$productImgAlt = "Lennox Heating & Cooling Product"; 

	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


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
			'post_title'	=>	'Elite Series EL16XC1 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">High-efficiency air conditioner that provides up to 17 SEER efficiency.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Softer lines create an appliance-like appearance</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
</ul>', 
			'post_excerpt'	=>	'High-efficiency air conditioner that provides up to 17 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1020,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/644a3d11-4d8b-464f-86a2-fee0964d5ae220B07_ELITE_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-01.jpg'		
		),
		
		
		
		array ( 
			'post_title'	=>	'Merit Series 16ACX Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Multi-Stage Air Conditioner that provides affordable, efficient cooling up to 17 SEER.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>Fan and blades enhance air circulation and decrease noise from the unit</li>
</ul>', 
			'post_excerpt'	=>	'Multi-Stage Air Conditioner that provides affordable, efficient cooling up to 17 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1030,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/be43ad60-3ee2-4c28-9f95-76dadb9e180920B10_MERIT_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-02.jpg'		
		),
		
		
		
		array ( 
			'post_title'	=>	'Merit Series ML14XC1 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Single-Stage Air Conditioner provides durability and up to 17 SEER efficiency.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>Fan and blades enhance air circulation and decrease noise from the unit</li>
</ul>', 
			'post_excerpt'	=>	'Single-Stage Air Conditioner provides durability and up to 17 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1040,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/34681ace-ceae-4d3e-b7d9-293382c44dc120B10_MERIT_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-02.jpg'		
		),
		
		
		
		array ( 
			'post_title'	=>	'Elite Series CX13 Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Single-Stage, reliable air conditioner provides up to 16 SEER efficiency.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Softer lines create an appliance-like appearance</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
</ul>', 
			'post_excerpt'	=>	'Single-Stage, reliable air conditioner provides up to 16 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1050,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/922c0ea6-aec6-4be3-aaa7-be3b6e7a8afe20B07_ELITE_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-01.jpg'		
		),
				
		
		array ( 
			'post_title'	=>	'Merit Series 13ACX Air Conditioner',
			'post_content' 	=>	'<span class="descriptionText">Single-Stage Air Conditioner offers affordable cooling up to 13 SEER.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>Fan and blades enhance air circulation and decrease noise from the unit</li>
</ul>', 
			'post_excerpt'	=>	'Single-Stage Air Conditioner offers affordable cooling up to 13 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1060,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/34681ace-ceae-4d3e-b7d9-293382c44dc120B10_MERIT_ACCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-AC-02.jpg'		
		),
		
		
	// Heat Pumps
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
			'menu_order'  	=>  1100,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
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
			'menu_order'  	=>  1110,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/f988fd9e-907f-43eb-8fa8-e7b6487aa7b620B09_ELITE_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Elite Series EL16XP1 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Single-Stage heat pump producing up to 17 SEER efficiency.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Softer lines create an appliance-like appearance</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>Dual-fuel compatible with Lennox furnaces for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Single-Stage heat pump producing up to 17 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1120,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/f988fd9e-907f-43eb-8fa8-e7b6487aa7b620B09_ELITE_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Merit Series ML16XP1 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Single-stage heat pump that delivers economical comfort with up to 17 SEER efficiency.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Fan and blades enhance air circulation and decrease noise from the unit</li>
	<li>Dual-fuel compatible with Lennox furnaces for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Single-stage heat pump that delivers economical comfort with up to 17 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1130,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/81ce8134-9660-41d7-8fb9-2a092a04b16120B12_MERIT_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-02.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Merit Series 16HPX Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Affordable, efficient cooling from this multi-stage heat pump with up to 16.5 SEER.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Fan and blades enhance air circulation and decrease noise from the unit</li>
	<li>Dual-fuel compatible with Lennox furnaces for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Affordable, efficient cooling from this multi-stage heat pump with up to 16.5 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1140,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/4579e82e-28c1-4be8-858f-e9836f044a1b20B12_MERIT_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-02.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Elite Series EL15XP1 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Superior single-stage heat pump that delivers quiet and efficient comfort up to 16 SEER.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Softer lines create an appliance-like appearance</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>Dual-fuel compatible with Lennox furnaces for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Superior single-stage heat pump that delivers quiet and efficient comfort up to 16 SEER.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1150,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/f988fd9e-907f-43eb-8fa8-e7b6487aa7b620B09_ELITE_HeatPumpCard_1021_CLEAN_v2.pdf'),
			'image_name'	=>	'Lennox-HP-01.jpg'		
		),
		
		
		array ( 
			'post_title'	=>	'Merit Series ML14XP1 Heat Pump',
			'post_content' 	=>	'<span class="descriptionText">Single-stage heat pump that provides up to 16 SEER efficiency.</span>
<ul>
	<li>Precision-balanced, direct drive fan to keep the noise low and the savings high</li>
	<li>Reinforced with a PermaGuard™ cabinet for long-lasting protection against rust and corrosion</li>
	<li>Cabinets built using superior materials and proprietary designs make Lennox® units more durable, safer and easier to install</li>
	<li>SmartHinge™ coil protection allows for easy coil cleaning</li>
	<li>Fan and blades enhance air circulation and decrease noise from the unit</li>
	<li>Dual-fuel compatible with Lennox furnaces for added cost savings</li>
</ul>', 
			'post_excerpt'	=>	'Single-stage heat pump that provides up to 16 SEER efficiency.',
			'post_type'     =>	'products',
			'menu_order'  	=>  1160,
			'tax_input'		=>  array('product-brand'=>'Lennox', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
			'meta_input'	=>	array('brochure'=>'https://resources.lennox.com/fileuploads/05520e24-a699-44a8-bb93-6084b82a018f20B12_MERIT_HeatPumpCard_1021_CLEAN_v2.pdf'),
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
				
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $attachmentID, $imageFile );
		wp_update_attachment_metadata( $attachmentID, $attach_data );
		update_post_meta( $attachmentID, '_wp_attachment_image_alt', $productImgAlt );
		wp_set_object_terms( $attachmentID, array('Products'), 'image-categories', true );
		
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